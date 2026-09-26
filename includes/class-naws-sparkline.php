<?php
/**
 * Sparkline: a curve the size of a word.
 *
 * The arithmetic is pure — points in, path strings out — and is tested on
 * hand-built series. fetch() is the only method that touches the database,
 * and render() is the one the shortcode and the widget call.
 *
 * Coordinates live in a viewBox of width × height (80 × 18 unless the
 * shortcode says otherwise), drawn with preserveAspectRatio="none" so the
 * curve stretches to whatever box CSS gives it. Lines keep their weight
 * through vector-effect="non-scaling-stroke"; dots are zero-length strokes
 * with round caps for the same reason, since a <circle> would stretch into
 * an ellipse.
 *
 * @package NAWS
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Sparkline {

    /** Raw parameters as Netatmo names them, and the module alias that measures each. */
    const RAW = [
        'Temperature'  => 'outdoor',
        'Humidity'     => 'outdoor',
        'Pressure'     => 'indoor',
        'CO2'          => 'indoor',
        'Noise'        => 'indoor',
        'WindStrength' => 'wind',
        'GustStrength' => 'wind',
        'Rain'         => 'rain',
    ];

    /** Daily-summary columns, and the raw parameter that lends each its unit. */
    const DAILY = [
        'temp_avg'     => 'Temperature',
        'temp_min'     => 'Temperature',
        'temp_max'     => 'Temperature',
        'humidity_avg' => 'Humidity',
        'pressure_avg' => 'Pressure',
        'rain_sum'     => 'Rain',
        'wind_avg'     => 'WindStrength',
        'gust_max'     => 'GustStrength',
        'co2_avg'      => 'CO2',
        'noise_avg'    => 'Noise',
    ];

    /** The viewBox without width/height: a word in running text. */
    const VIEW_W = 80;
    const VIEW_H = 18;

    /** A line never carries more points than this; see thin(). */
    const MAX_POINTS = 200;

    /** Rain over hours is summed into this many bars; see buckets(). */
    const RAIN_BARS = 48;

    /** The raw-reading window moves in steps of this many seconds; see slot_end(). */
    const SLOT = 300;

    // ── Attributes ──────────────────────────────────────────────────

    /**
     * The shortcode attributes cast, clamped and whitelisted. Null when
     * there is nothing sensible to draw: an unknown parameter, or a raw
     * parameter together with days (the daily table knows rain_sum, not
     * Rain, and a silent reinterpretation would draw something else than
     * was asked for). Bars are forced to line for non-rain quantities,
     * because a bar is a sum and a sum of temperatures or pressures means
     * nothing; only multiplicative conversions (mm→in) are valid for bars.
     */
    public static function normalise_atts( array $atts ): ?array {
        $param    = trim( (string) ( $atts['param'] ?? 'Temperature' ) );
        $days_raw = trim( (string) ( $atts['days'] ?? '' ) );

        if ( isset( self::RAW[ $param ] ) ) {
            if ( $days_raw !== '' ) {
                return null;
            }
            $source = 'raw';
            $base   = $param;
        } elseif ( isset( self::DAILY[ $param ] ) ) {
            $source = 'day';
            $base   = self::DAILY[ $param ];
        } else {
            return null;
        }

        $hours_raw = trim( (string) ( $atts['hours'] ?? '' ) );
        $w_raw     = trim( (string) ( $atts['width'] ?? '' ) );
        $h_raw     = trim( (string) ( $atts['height'] ?? '' ) );

        $show = strtolower( trim( (string) ( $atts['show'] ?? '' ) ) );
        $show = in_array( $show, [ 'none', 'value', 'minmax' ], true ) ? $show : 'none';

        $type = strtolower( trim( (string) ( $atts['type'] ?? '' ) ) );
        if ( ! in_array( $type, [ 'line', 'bars' ], true ) ) {
            $type = $base === 'Rain' ? 'bars' : 'line';
        }

        // Bars exist for rain only: a bar is a sum, and a sum of temperatures
        // or pressures means nothing. Only multiplicative conversions (mm→in)
        // are valid for bars.
        if ( $type === 'bars' && $base !== 'Rain' ) {
            $type = 'line';
        }

        $band = $source === 'day' && $param === 'temp_avg' && $type === 'line'
            && strtolower( trim( (string) ( $atts['band'] ?? '' ) ) ) === 'minmax';

        $module = '';
        if ( $source === 'raw' ) {
            $module = sanitize_text_field( (string) ( $atts['module'] ?? '' ) );
            if ( $module === '' ) {
                $module = self::RAW[ $param ];
            }
        }

        return [
            'param'   => $param,
            'base'    => $base,
            'source'  => $source,
            'hours'   => $hours_raw === '' ? 24 : self::clamp( intval( $hours_raw ), 1, 168 ),
            'days'    => $source === 'day' ? ( $days_raw === '' ? 30 : self::clamp( intval( $days_raw ), 2, 366 ) ) : 0,
            'module'  => $module,
            'w'       => $w_raw === '' ? self::VIEW_W : self::clamp( intval( $w_raw ), 20, 600 ),
            'h'       => $h_raw === '' ? self::VIEW_H : self::clamp( intval( $h_raw ), 10, 200 ),
            'sized_w' => $w_raw !== '',
            'sized_h' => $h_raw !== '',
            'show'    => $show,
            'type'    => $type,
            'band'    => $band,
        ];
    }

    // ── Pure arithmetic ─────────────────────────────────────────────

    /**
     * End of the five-minute slot $time falls in. The raw-reading window
     * ends here rather than at the second, because get_readings() caches
     * under a hash of its arguments: a window that moved every second
     * would leave a new transient behind on every page view.
     */
    public static function slot_end( int $time ): int {
        return intdiv( $time, self::SLOT ) * self::SLOT + self::SLOT;
    }

    /**
     * At most $max points. The time axis is cut into $max equal windows
     * and every window that holds points becomes one point, at the mean
     * of their times and values. A band's low (index 2) takes the window's
     * minimum and its high (index 3) the maximum, so thinning never makes
     * the range look narrower than it was.
     */
    public static function thin( array $pts, int $max = self::MAX_POINTS ): array {
        $n = count( $pts );
        if ( $n <= $max || $max < 2 ) {
            return array_values( $pts );
        }
        $t0   = (float) $pts[0][0];
        $span = ( (float) $pts[ $n - 1 ][0] - $t0 ) / $max;
        if ( $span <= 0 ) {
            return array_values( $pts );
        }

        $acc = [];
        foreach ( $pts as $p ) {
            $i = min( $max - 1, (int) floor( ( $p[0] - $t0 ) / $span ) );
            if ( ! isset( $acc[ $i ] ) ) {
                $acc[ $i ] = [ 'n' => 0, 't' => 0.0, 'v' => 0.0, 'lo' => null, 'hi' => null ];
            }
            $acc[ $i ]['n']++;
            $acc[ $i ]['t'] += $p[0];
            $acc[ $i ]['v'] += $p[1];
            if ( isset( $p[2], $p[3] ) ) {
                $acc[ $i ]['lo'] = $acc[ $i ]['lo'] === null ? $p[2] : min( $acc[ $i ]['lo'], $p[2] );
                $acc[ $i ]['hi'] = $acc[ $i ]['hi'] === null ? $p[3] : max( $acc[ $i ]['hi'], $p[3] );
            }
        }
        ksort( $acc );

        $out = [];
        foreach ( $acc as $a ) {
            $row = [ (int) round( $a['t'] / $a['n'] ), $a['v'] / $a['n'] ];
            if ( $a['lo'] !== null ) {
                $row[] = $a['lo'];
                $row[] = $a['hi'];
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Rain reports summed into $n equal windows from $from to $to, each
     * [ start, end, sum ]. A window without a report sums to 0. A report
     * at $to itself counts into the last window; one before $from or after
     * $to counts into none.
     */
    public static function buckets( array $pts, int $from, int $to, int $n = self::RAIN_BARS ): array {
        $width = ( $to - $from ) / $n;
        if ( $width <= 0 ) {
            return [];
        }
        $out = [];
        for ( $i = 0; $i < $n; $i++ ) {
            $out[] = [ (int) round( $from + $i * $width ), (int) round( $from + ( $i + 1 ) * $width ), 0.0 ];
        }
        foreach ( $pts as $p ) {
            if ( $p[0] < $from || $p[0] > $to ) {
                continue;
            }
            $i = min( $n - 1, (int) floor( ( $p[0] - $from ) / $width ) );
            $out[ $i ][2] += (float) $p[1];
        }
        return $out;
    }

    /**
     * A line through $pts in a $w × $h box, with everything the template
     * and the hover script need:
     *   line  – "M… L…", a new M after every gap
     *   area  – the same, run by run, closed down to the baseline
     *   band  – the highs forward and the lows back as one closed shape,
     *           '' without $band
     *   end   – [x, y] of the last point
     *   lo/hi – [x, y] of the first lowest and the first highest value
     *   xs    – x of every point, one decimal, for the hover script
     * A gap is a step longer than three times the median step. A flat
     * series sits at half height instead of dividing by zero.
     * Callers draw nothing below two points (prepare() returns null), so
     * an empty series only ever comes back empty.
     *
     * @param array $pts [[ts, value], …] or, with $band, [[ts, value, low, high], …].
     */
    public static function geometry( array $pts, int $w, int $h, bool $band = false ): array {
        $n    = count( $pts );
        if ( $n === 0 ) {
            return [ 'line' => '', 'area' => '', 'band' => '', 'end' => [], 'lo' => [], 'hi' => [], 'xs' => [] ];
        }
        $pad  = max( 2.0, $h / 9 );
        $vals = array_column( $pts, 1 );
        $lo   = min( $vals );
        $hi   = max( $vals );
        if ( $band ) {
            $lo = min( $lo, min( array_column( $pts, 2 ) ) );
            $hi = max( $hi, max( array_column( $pts, 3 ) ) );
        }
        if ( $hi - $lo < 1e-9 ) {
            $lo -= 0.5;
            $hi += 0.5;
        }
        $t0    = (float) $pts[0][0];
        $tspan = (float) $pts[ $n - 1 ][0] - $t0;
        $tspan = $tspan > 0 ? $tspan : 1.0;

        $x = static fn( $t ): float => $pad + ( $t - $t0 ) / $tspan * ( $w - 2 * $pad );
        $y = static fn( $v ): float => $pad + ( 1 - ( $v - $lo ) / ( $hi - $lo ) ) * ( $h - 2 * $pad );

        $steps = [];
        for ( $i = 1; $i < $n; $i++ ) {
            $steps[] = $pts[ $i ][0] - $pts[ $i - 1 ][0];
        }
        sort( $steps );
        $median = $steps ? $steps[ intdiv( count( $steps ), 2 ) ] : 0;
        $gap    = $median > 0 ? 3 * $median : INF;

        $runs = [];
        $run  = [];
        foreach ( $pts as $i => $p ) {
            if ( $i > 0 && $p[0] - $pts[ $i - 1 ][0] > $gap ) {
                $runs[] = $run;
                $run    = [];
            }
            $run[] = [ $x( $p[0] ), $y( $p[1] ) ];
        }
        $runs[] = $run;

        $base = self::num( $h - $pad );
        $line = [];
        $area = [];
        foreach ( $runs as $r ) {
            $seg = '';
            foreach ( $r as $k => $xy ) {
                $seg .= ( $k ? ' L' : 'M' ) . self::num( $xy[0] ) . ' ' . self::num( $xy[1] );
            }
            $last   = $r[ count( $r ) - 1 ];
            $line[] = $seg;
            $area[] = $seg . ' L' . self::num( $last[0] ) . ' ' . $base . ' L' . self::num( $r[0][0] ) . ' ' . $base . ' Z';
        }

        $band_d = '';
        if ( $band ) {
            foreach ( $pts as $i => $p ) {
                $band_d .= ( $i ? ' L' : 'M' ) . self::num( $x( $p[0] ) ) . ' ' . self::num( $y( $p[3] ) );
            }
            for ( $i = $n - 1; $i >= 0; $i-- ) {
                $band_d .= ' L' . self::num( $x( $pts[ $i ][0] ) ) . ' ' . self::num( $y( $pts[ $i ][2] ) );
            }
            $band_d .= ' Z';
        }

        $at = static fn( int $i ): array => [ self::num( $x( $pts[ $i ][0] ) ), self::num( $y( $pts[ $i ][1] ) ) ];

        return [
            'line' => implode( ' ', $line ),
            'area' => implode( ' ', $area ),
            'band' => $band_d,
            'end'  => $at( $n - 1 ),
            'lo'   => $at( (int) array_search( min( $vals ), $vals, true ) ),
            'hi'   => $at( (int) array_search( max( $vals ), $vals, true ) ),
            'xs'   => array_map( static fn( $p ): float => round( $x( $p[0] ), 1 ), $pts ),
        ];
    }

    /**
     * Bars for $sums in a $w × $h box: one slot per sum, a bar only where
     * it rained, heights relative to the wettest slot. Without any rain
     * the baseline stands alone.
     */
    public static function bar_geometry( array $sums, int $w, int $h ): array {
        $n = count( $sums );
        if ( $n === 0 ) {
            return [ 'rects' => [], 'base' => self::num( $h - 0.5 ), 'xs' => [] ];
        }
        $max = max( $sums );
        $max = $max > 0 ? $max : 1.0;
        $bw  = ( $w - 2 ) / $n;
        $gap = min( 1.0, $bw * 0.25 );

        $rects = [];
        $xs    = [];
        foreach ( array_values( $sums ) as $i => $s ) {
            $x0   = 1 + $i * $bw;
            $xs[] = round( $x0 + $bw / 2, 1 );
            if ( $s <= 0 ) {
                continue;
            }
            $bh      = $s / $max * ( $h - 2 );
            $rects[] = [ self::num( $x0 ), self::num( $h - 1 - $bh ), self::num( max( 0.6, $bw - $gap ) ), self::num( $bh ) ];
        }
        return [ 'rects' => $rects, 'base' => self::num( $h - 0.5 ), 'xs' => $xs ];
    }

    /** A coordinate with a decimal point whatever the locale says. */
    public static function num( float $v ): string {
        return sprintf( '%.2F', $v );
    }

    // ── Data ────────────────────────────────────────────────────────

    /**
     * The rows behind one sparkline, in the units Netatmo stores.
     *
     * Raw: [ 'from', 'to', 'rows' => [ [ts, value], … ] ] for the module
     * the attributes name. Daily: [ 'dates' => [ 'Y-m-d', … ], 'rows' =>
     * [ 'Y-m-d' => [ value ] or [ value, low, high ] ] ] from the station
     * row, because compute_daily_summary() writes outdoor, rain, wind and
     * base-station values into one row per station — the same row
     * [naws_records] reads. A day without a value is left out.
     */
    public static function fetch( array $a, int $now ): array {
        if ( $a['source'] === 'raw' ) {
            $to   = self::slot_end( $now );
            $from = $to - $a['hours'] * 3600;
            $id   = NAWS_Helpers::resolve_module_ref( (string) $a['module'] );
            if ( $id === null ) {
                return [ 'from' => $from, 'to' => $to, 'rows' => [] ];
            }
            $rows = NAWS_Database::get_readings( [
                'module_id' => $id,
                'parameter' => $a['param'],
                'date_from' => $from,
                'date_to'   => $to,
                'group_by'  => 'raw',
                'limit'     => 0,
            ] );
            $out = [];
            foreach ( $rows as $r ) {
                $out[] = [ (int) $r['recorded_at'], (float) $r['value'] ];
            }
            return [ 'from' => $from, 'to' => $to, 'rows' => $out ];
        }

        $today = new DateTimeImmutable( wp_date( 'Y-m-d', $now ), wp_timezone() );
        $dates = [];
        for ( $i = $a['days'] - 1; $i >= 0; $i-- ) {
            $dates[] = $today->modify( '-' . $i . ' days' )->format( 'Y-m-d' );
        }

        $station = NAWS_Calc::station_row_id( [] );
        if ( $station === null ) {
            return [ 'dates' => $dates, 'rows' => [] ];
        }
        $rows = NAWS_Database::get_daily_summaries( [
            'module_id' => $station,
            'date_from' => $dates[0],
            'date_to'   => $dates[ count( $dates ) - 1 ],
            'fields'    => $a['band'] ? [ 'temp_avg', 'temp_min', 'temp_max' ] : [ $a['param'] ],
            'group_by'  => 'day',
        ] );

        $out = [];
        foreach ( $rows as $r ) {
            $v = $r[ $a['param'] ] ?? null;
            if ( $v === null || $v === '' ) {
                continue;
            }
            $row = [ (float) $v ];
            if ( $a['band'] ) {
                if ( ( $r['temp_min'] ?? null ) === null || ( $r['temp_max'] ?? null ) === null ) {
                    continue;
                }
                $row[] = (float) $r['temp_min'];
                $row[] = (float) $r['temp_max'];
            }
            $out[ (string) $r['day_date'] ] = $row;
        }
        return [ 'dates' => $dates, 'rows' => $out ];
    }

    /**
     * What the template draws, in display units: the series (thinned) or
     * the bars (bucketed), a hover text per point, the text a screen
     * reader hears, and the figure show="value" prints. Null when there
     * is nothing to draw: fewer than two points on a line, or not a
     * single rain report in the window. A window in which the gauge
     * reported but no rain fell is a valid result and draws a baseline.
     */
    public static function prepare( array $a, array $f ): ?array {
        $base = $a['base'];
        $unit = NAWS_Helpers::get_unit( $base );
        $name = naws_label( 'sl_name_' . strtolower( $a['param'] ) );
        if ( $a['source'] === 'raw' ) {
            /* translators: %d: number of hours. */
            $period = sprintf( _n( '%d hour', '%d hours', $a['hours'], 'xtx-integration-for-netatmo' ), $a['hours'] );
        } else {
            /* translators: %d: number of days. */
            $period = sprintf( _n( '%d day', '%d days', $a['days'], 'xtx-integration-for-netatmo' ), $a['days'] );
        }
        $with = static fn( float $v ): string => self::number( $base, $v ) . ' ' . $unit;
        $conv = static fn( float $v ): float => (float) NAWS_Helpers::format_value( $base, $v );

        if ( $a['type'] === 'bars' ) {
            if ( ! $f['rows'] ) {
                return null;
            }
            $sums = [];
            $tips = [];
            $raw_total = 0.0;
            if ( $a['source'] === 'raw' ) {
                $weekday = $a['hours'] > 24;
                foreach ( self::buckets( $f['rows'], $f['from'], $f['to'] ) as $w ) {
                    $v         = $conv( $w[2] );
                    $raw_total += $w[2];
                    $sums[]    = $v;
                    $tips[]    = self::stamp( $w[0], $weekday ) . '–' . self::stamp( $w[1], false ) . ' · ' . $with( $v );
                }
            } else {
                foreach ( $f['dates'] as $d ) {
                    $raw        = (float) ( $f['rows'][ $d ][0] ?? 0.0 );
                    $v          = $conv( $raw );
                    $raw_total += $raw;
                    $sums[]     = $v;
                    $tips[]     = self::day( $d ) . ' · ' . $with( $v );
                }
            }
            $total = $with( $conv( $raw_total ) );
            return [
                'kind'  => 'bars',
                'sums'  => $sums,
                'tips'  => $tips,
                'aria'  => sprintf( naws_label( 'sl_aria_bars' ), $name, $period, $total ),
                'value' => $total,
            ];
        }

        $pts = [];
        if ( $a['source'] === 'raw' ) {
            foreach ( $f['rows'] as $r ) {
                $pts[] = [ $r[0], $conv( $r[1] ) ];
            }
        } else {
            foreach ( $f['dates'] as $d ) {
                if ( ! isset( $f['rows'][ $d ] ) ) {
                    continue;
                }
                $row = $f['rows'][ $d ];
                $p   = [ self::noon( $d ), $conv( $row[0] ) ];
                if ( $a['band'] ) {
                    $p[] = $conv( $row[1] );
                    $p[] = $conv( $row[2] );
                }
                $pts[] = $p;
            }
        }
        $pts = self::thin( $pts );
        if ( count( $pts ) < 2 ) {
            return null;
        }

        $tips    = [];
        $weekday = $a['source'] === 'raw' && $a['hours'] > 24;
        foreach ( $pts as $p ) {
            $when   = $a['source'] === 'raw' ? self::stamp( $p[0], $weekday ) : wp_date( (string) get_option( 'date_format', 'Y-m-d' ), $p[0] );
            $tip    = $when . ' · ' . $with( $p[1] );
            $tips[] = $a['band'] ? $tip . ' · ' . self::number( $base, $p[2] ) . '–' . $with( $p[3] ) : $tip;
        }

        $vals = array_column( $pts, 1 );
        if ( $a['band'] ) {
            $aria = sprintf( naws_label( 'sl_aria_band' ), $name, $period, $with( min( $vals ) ), $with( max( $vals ) ), $with( min( array_column( $pts, 2 ) ) ), $with( max( array_column( $pts, 3 ) ) ) );
        } else {
            $aria = sprintf( naws_label( 'sl_aria_line' ), $name, $period, $with( min( $vals ) ), $with( max( $vals ) ), $with( $vals[ count( $vals ) - 1 ] ) );
        }

        return [
            'kind'  => 'line',
            'pts'   => $pts,
            'band'  => (bool) $a['band'],
            'tips'  => $tips,
            'aria'  => $aria,
            'value' => $with( $vals[ count( $vals ) - 1 ] ),
        ];
    }

    /**
     * A value as text with as many decimals as the quantity deserves:
     * none for humidity, CO₂ and noise, none for a whole wind speed, two
     * for inches of rain and inches of mercury, one for everything else.
     */
    public static function number( string $base, float $v ): string {
        $opts = get_option( 'naws_settings', [] );
        switch ( $base ) {
            case 'Humidity':
            case 'CO2':
            case 'Noise':
                $d = 0;
                break;
            case 'Rain':
                $d = ( $opts['rain_unit'] ?? 'mm' ) === 'in' ? 2 : 1;
                break;
            case 'Pressure':
                $d = ( $opts['pressure_unit'] ?? 'mbar' ) === 'inHg' ? 2 : 1;
                break;
            case 'WindStrength':
            case 'GustStrength':
                $d = abs( $v - round( $v ) ) < 0.05 ? 0 : 1;
                break;
            default:
                $d = 1;
        }
        return number_format_i18n( $v, $d );
    }

    /** The clock time of $ts in the site's format, with the weekday in front if asked. */
    private static function stamp( int $ts, bool $weekday ): string {
        $time = wp_date( (string) get_option( 'time_format', 'H:i' ), $ts );
        return $weekday ? wp_date( 'D', $ts ) . ' ' . $time : $time;
    }

    /** A day of the daily table in the site's date format. */
    private static function day( string $ymd ): string {
        return wp_date( (string) get_option( 'date_format', 'Y-m-d' ), self::noon( $ymd ) );
    }

    /** Noon of a day in the site's timezone: evenly spaced, and never across midnight by DST. */
    private static function noon( string $ymd ): int {
        return ( new DateTimeImmutable( $ymd . ' 12:00:00', wp_timezone() ) )->getTimestamp();
    }

    // ── Markup ──────────────────────────────────────────────────────

    /**
     * One sparkline as HTML: the geometry for the prepared data, then the
     * template. $a comes from normalise_atts(), $d from prepare() — or is
     * built by hand for the Appearance preview, see sample().
     */
    public static function markup( array $a, array $d ): string {
        if ( $d['kind'] === 'bars' ) {
            $geo = self::bar_geometry( $d['sums'], $a['w'], $a['h'] );
            $mod = 'bars';
        } else {
            $band = ! empty( $d['band'] );
            $geo  = self::geometry( $d['pts'], $a['w'], $a['h'], $band );
            $mod  = $band ? 'band' : 'line';
        }

        $naws_sl = [
            'kind'   => $d['kind'],
            'mod'    => $mod,
            'w'      => $a['w'],
            'h'      => $a['h'],
            'style'  => ( $a['sized_w'] ? '--naws-sl-w:' . $a['w'] . 'px;' : '' ) . ( $a['sized_h'] ? '--naws-sl-h:' . $a['h'] . 'px;' : '' ),
            'geo'    => $geo,
            'minmax' => $a['show'] === 'minmax' && $d['kind'] === 'line',
            'hover'  => [ 'x' => $geo['xs'], 't' => array_values( $d['tips'] ) ],
            'aria'   => $d['aria'],
            'value'  => $a['show'] === 'value' ? $d['value'] : '',
        ];

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sparkline.php';
        return trim( (string) ob_get_clean() );
    }

    /**
     * The entry for the shortcode and the widget: attributes in, HTML out,
     * '' whenever there is nothing to draw.
     */
    public static function render( array $atts ): string {
        $a = self::normalise_atts( $atts );
        if ( $a === null ) {
            return '';
        }
        $d = self::prepare( $a, self::fetch( $a, time() ) );
        return $d === null ? '' : self::markup( $a, $d );
    }

    /** The widget's three curves over 24 hours; '' where a module is missing or silent. */
    public static function widget_set(): array {
        return [
            'temp' => self::render( [ 'param' => 'Temperature' ] ),
            'rain' => self::render( [ 'param' => 'Rain' ] ),
            'wind' => self::render( [ 'param' => 'WindStrength' ] ),
        ];
    }

    /**
     * A fixed curve for the Appearance preview, for a station that has
     * nothing to show yet: a mild day for the line, a shower for the bars,
     * twelve days for the band. Never random, so the preview does not
     * change between two looks.
     */
    public static function sample( string $kind ): string {
        $v = [ 14.2, 13.1, 12.4, 12.0, 13.5, 16.8, 19.4, 21.0, 21.6, 20.2, 18.1, 16.0 ];
        $a = self::normalise_atts( [
            'param'  => $kind === 'bars' ? 'Rain' : ( $kind === 'band' ? 'temp_avg' : 'Temperature' ),
            'days'   => $kind === 'band' ? '12' : '',
            'band'   => 'minmax',
            'show'   => 'minmax',
            'width'  => '200',
            'height' => '40',
        ] );
        $tips = array_fill( 0, count( $v ), '' );
        $aria = naws_label( 'sl_preview_aria' );

        if ( $kind === 'bars' ) {
            $d = [ 'kind' => 'bars', 'sums' => [ 0.0, 0.0, 0.4, 1.2, 0.2, 0.0, 0.0, 0.0, 0.8, 2.1, 1.0, 0.0 ], 'tips' => $tips, 'aria' => $aria, 'value' => '' ];
        } else {
            $pts = [];
            foreach ( $v as $i => $x ) {
                $pts[] = $kind === 'band'
                    ? [ $i * 86400, $x, $x - 3.5 - ( $i % 3 ), $x + 4.0 + ( $i % 2 ) ]
                    : [ $i * 3600, $x ];
            }
            $d = [ 'kind' => 'line', 'pts' => $pts, 'band' => $kind === 'band', 'tips' => $tips, 'aria' => $aria, 'value' => '' ];
        }
        return self::markup( $a, $d );
    }

    /**
     * The three curves on the Sparkline tab: the station's own where it has
     * them, the sample otherwise. Bars count only with rain in them — a dry
     * day would show the baseline and none of the colour being chosen.
     */
    public static function preview_set(): array {
        $line = self::render( [ 'param' => 'Temperature', 'show' => 'minmax', 'width' => '200', 'height' => '40' ] );
        $bars = self::render( [ 'param' => 'Rain', 'width' => '200', 'height' => '40' ] );
        $band = self::render( [ 'param' => 'temp_avg', 'days' => '30', 'band' => 'minmax', 'width' => '200', 'height' => '40' ] );
        return [
            'line' => $line !== '' ? $line : self::sample( 'line' ),
            'bars' => str_contains( $bars, 'naws-sl-bar' ) ? $bars : self::sample( 'bars' ),
            'band' => $band !== '' ? $band : self::sample( 'band' ),
        ];
    }

    private static function clamp( int $v, int $lo, int $hi ): int {
        return max( $lo, min( $hi, $v ) );
    }
}
