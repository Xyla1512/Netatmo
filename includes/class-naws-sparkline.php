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
     * A tile fixes its own viewBox (200 × 44) and always marks low and high.
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

        // layout (since the addendum): inline is the curve in running text
        // as before; tile is the card from the demo; month is the month
        // block, which render() sends to render_month() before it gets here.
        $layout = strtolower( trim( (string) ( $atts['layout'] ?? '' ) ) );
        $layout = in_array( $layout, [ 'tile', 'month' ], true ) ? $layout : 'inline';
        $tile   = $layout === 'tile';

        return [
            'param'   => $param,
            'base'    => $base,
            'source'  => $source,
            'hours'   => $hours_raw === '' ? 24 : self::clamp( intval( $hours_raw ), 1, 168 ),
            'days'    => $source === 'day' ? ( $days_raw === '' ? 30 : self::clamp( intval( $days_raw ), 2, 366 ) ) : 0,
            'module'  => $module,
            'w'       => $tile ? 200 : ( $w_raw === '' ? self::VIEW_W : self::clamp( intval( $w_raw ), 20, 600 ) ),
            'h'       => $tile ? 44 : ( $h_raw === '' ? self::VIEW_H : self::clamp( intval( $h_raw ), 10, 200 ) ),
            'sized_w' => ! $tile && $w_raw !== '',
            'sized_h' => ! $tile && $h_raw !== '',
            'show'    => $tile ? 'minmax' : $show,
            'type'    => $type,
            'band'    => $band,
            'layout'  => $layout,
            'title'   => sanitize_text_field( (string) ( $atts['title'] ?? '' ) ),
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
     * $t0/$t1, when both given, fix the x-domain instead of letting it
     * follow the first and last point with data. render_month() needs
     * this: with days missing at the start of the span, the band's own
     * points would otherwise start later than the rain bars and the axis,
     * which always cover the whole span.
     *
     * @param array $pts [[ts, value], …] or, with $band, [[ts, value, low, high], …].
     */
    public static function geometry( array $pts, int $w, int $h, bool $band = false, ?float $t0 = null, ?float $t1 = null ): array {
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
        if ( $t0 !== null && $t1 !== null ) {
            $dom0  = $t0;
            $tspan = $t1 - $t0;
        } else {
            $dom0  = (float) $pts[0][0];
            $tspan = (float) $pts[ $n - 1 ][0] - $dom0;
        }
        $tspan = $tspan > 0 ? $tspan : 1.0;

        $x = static fn( $t ): float => $pad + ( $t - $dom0 ) / $tspan * ( $w - 2 * $pad );
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
     * Also returns name, unit and period for the tile and the month block
     * (bars also carry the total as a number; lines also carry last,
     * last_ts, lo and hi — the latest reading and the real extremes from
     * every point that came in, not the thinned curve).
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
                // Fewer windows for a short span: at 48 fixed windows, one
                // or two hours split into slices shorter than the rain
                // gauge's five-minute reports, and most bars stay empty.
                $n = (int) max( 1, min( self::RAIN_BARS, intdiv( $f['to'] - $f['from'], self::SLOT ) ) );
                foreach ( self::buckets( $f['rows'], $f['from'], $f['to'], $n ) as $w ) {
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
                'name'   => $name,
                'unit'   => $unit,
                'period' => $period,
                'total'  => $conv( $raw_total ),
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
        if ( count( $pts ) < 2 ) {
            return null;
        }

        // The latest reading and the real extremes, from every point that
        // came in — before thin() below narrows the series to at most
        // MAX_POINTS for the curve's geometry. Otherwise a spike gets
        // averaged away and "latest" turns into a window mean.
        $last_pt  = $pts[ count( $pts ) - 1 ];
        $last     = (float) $last_pt[1];
        $last_ts  = (int) $last_pt[0];
        $raw_vals = array_column( $pts, 1 );
        $mean_lo  = min( $raw_vals );
        $mean_hi  = max( $raw_vals );
        if ( $a['band'] ) {
            $lo = min( array_column( $pts, 2 ) );
            $hi = max( array_column( $pts, 3 ) );
        } else {
            $lo = $mean_lo;
            $hi = $mean_hi;
        }

        $pts = self::thin( $pts );

        $tips    = [];
        $weekday = $a['source'] === 'raw' && $a['hours'] > 24;
        foreach ( $pts as $p ) {
            $when   = $a['source'] === 'raw' ? self::stamp( $p[0], $weekday ) : wp_date( (string) get_option( 'date_format', 'Y-m-d' ), $p[0] );
            $tip    = $when . ' · ' . $with( $p[1] );
            $tips[] = $a['band'] ? $tip . ' · ' . self::number( $base, $p[2] ) . '–' . $with( $p[3] ) : $tip;
        }

        if ( $a['band'] ) {
            $aria = sprintf( naws_label( 'sl_aria_band' ), $name, $period, $with( $mean_lo ), $with( $mean_hi ), $with( $lo ), $with( $hi ) );
        } else {
            $aria = sprintf( naws_label( 'sl_aria_line' ), $name, $period, $with( $lo ), $with( $hi ), $with( $last ) );
        }

        return [
            'kind'    => 'line',
            'pts'     => $pts,
            'band'    => (bool) $a['band'],
            'tips'    => $tips,
            'aria'    => $aria,
            'value'   => $with( $last ),
            'last'    => $last,
            'last_ts' => $last_ts,
            'lo'      => $lo,
            'hi'      => $hi,
            'name'    => $name,
            'unit'    => $unit,
            'period'  => $period,
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
            $geo  = isset( $d['domain'] )
                ? self::geometry( $d['pts'], $a['w'], $a['h'], $band, (float) $d['domain'][0], (float) $d['domain'][1] )
                : self::geometry( $d['pts'], $a['w'], $a['h'], $band );
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
     * '' whenever there is nothing to draw. layout="month" has its own
     * data path; layout="tile" wraps the curve in a card.
     */
    public static function render( array $atts ): string {
        if ( strtolower( trim( (string) ( $atts['layout'] ?? '' ) ) ) === 'month' ) {
            return self::render_month( $atts );
        }
        $a = self::normalise_atts( $atts );
        if ( $a === null ) {
            return '';
        }
        $d = self::prepare( $a, self::fetch( $a, time() ) );
        if ( $d === null ) {
            return '';
        }
        return $a['layout'] === 'tile' ? self::tile_markup( $a, $d ) : self::markup( $a, $d );
    }

    /** The six tiles of the demo, in its order: [naws_sparkline_tiles] without params. */
    const TILE_PARAMS = [ 'Temperature', 'Humidity', 'Pressure', 'WindStrength', 'Rain', 'CO2' ];

    /**
     * The quantities for [naws_sparkline_tiles]: a comma list of raw
     * parameters, case ignored, each once, in the order given. Daily
     * columns and unknown names drop out; an empty list is the demo's six.
     */
    public static function tile_params( string $raw ): array {
        if ( trim( $raw ) === '' ) {
            return self::TILE_PARAMS;
        }
        $known = [];
        foreach ( array_keys( self::RAW ) as $p ) {
            $known[ strtolower( $p ) ] = $p;
        }
        $out = [];
        foreach ( explode( ',', $raw ) as $p ) {
            $p = $known[ strtolower( trim( $p ) ) ] ?? null;
            if ( $p !== null && ! in_array( $p, $out, true ) ) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** The grid around the cards; a card that drew nothing leaves no gap, no card leaves no grid. */
    public static function tiles_markup( array $cards ): string {
        $cards = array_values( array_filter( $cards, static function ( $c ) { return $c !== ''; } ) );
        return $cards === [] ? '' : '<div class="naws-sl-tiles">' . implode( "\n", $cards ) . '</div>';
    }

    /** [naws_sparkline_tiles params="" hours="24"]: one tile per quantity, side by side. */
    public static function render_tiles( array $atts ): string {
        $hours = (string) ( $atts['hours'] ?? '' );
        $cards = [];
        foreach ( self::tile_params( (string) ( $atts['params'] ?? '' ) ) as $p ) {
            $cards[] = self::render( [ 'param' => $p, 'hours' => $hours, 'layout' => 'tile' ] );
        }
        return self::tiles_markup( $cards );
    }

    /** The widget's tiles under the head, key => raw parameter; the temperature stays in the head. */
    const WIDGET_CHIPS = [
        'humidity' => 'Humidity',
        'pressure' => 'Pressure',
        'wind'     => 'WindStrength',
        'rain'     => 'Rain',
        'co2'      => 'CO2',
    ];

    /**
     * One widget tile from what prepare() returned: name, figure, unit,
     * the curve, and the line under the figure in two parts — low and
     * high, or for rain the peak and its window — which the stylesheet
     * stacks in a narrow widget and joins with a dot in a wide one. The
     * parts carry no unit (it stands at the figure above them), and rain
     * keeps its bare unit, because "mm in 24 hours" does not fit half a
     * sidebar. A dry window has no parts.
     */
    public static function widget_chip( string $key, array $a, array $d ): array {
        $facts = self::tile_facts( $a, $d );
        $lines = [];
        if ( $d['kind'] === 'bars' ) {
            $max = $d['sums'] ? max( $d['sums'] ) : 0.0;
            if ( $max > 0 ) {
                $i     = (int) array_search( $max, $d['sums'], true );
                $lines = [
                    sprintf( naws_label( 'sl_wgt_peak' ), self::number( $a['base'], $max ) . ' ' . $d['unit'] ),
                    explode( ' · ', (string) $d['tips'][ $i ], 2 )[0],
                ];
            }
        } else {
            $lines = [
                sprintf( naws_label( 'sl_wgt_low' ), self::number( $a['base'], (float) $d['lo'] ) ),
                sprintf( naws_label( 'sl_wgt_high' ), self::number( $a['base'], (float) $d['hi'] ) ),
            ];
        }
        return [
            'key'   => $key,
            'name'  => $facts['name'],
            'value' => $facts['value'],
            'unit'  => $d['kind'] === 'bars' ? $d['unit'] : $facts['unit'],
            'lines' => $lines,
            'curve' => self::markup( $a, $d ),
        ];
    }

    /**
     * The widget's curves over 24 hours: the temperature for the head,
     * and a tile for each of WIDGET_CHIPS whose module has something to
     * show. '' and [] where there is nothing.
     */
    public static function widget_set(): array {
        $now   = time();
        $chips = [];
        foreach ( self::WIDGET_CHIPS as $key => $param ) {
            $a = self::normalise_atts( [ 'param' => $param, 'show' => 'minmax' ] );
            $d = self::prepare( $a, self::fetch( $a, $now ) );
            if ( $d !== null ) {
                $chips[] = self::widget_chip( $key, $a, $d );
            }
        }
        return [
            'temp'  => self::render( [ 'param' => 'Temperature' ] ),
            'chips' => $chips,
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

    // ── Tile and month block (addendum) ─────────────────────────────

    /**
     * The words on a tile, from what prepare() returned: the name (or the
     * title), the figure, its unit, and the line under it — low and high
     * for a line or a band, the wettest window for rain (none on a dry
     * span). No query, no markup.
     */
    public static function tile_facts( array $a, array $d ): array {
        $name = $a['title'] !== '' ? $a['title'] : $d['name'];

        if ( $d['kind'] === 'bars' ) {
            if ( $a['source'] === 'raw' ) {
                /* translators: 1: unit such as "mm", 2: number of hours. */
                $unit = sprintf( _n( '%1$s in %2$d hour', '%1$s in %2$d hours', $a['hours'], 'xtx-integration-for-netatmo' ), $d['unit'], $a['hours'] );
            } else {
                /* translators: 1: unit such as "mm", 2: number of days. */
                $unit = sprintf( _n( '%1$s in %2$d day', '%1$s in %2$d days', $a['days'], 'xtx-integration-for-netatmo' ), $d['unit'], $a['days'] );
            }
            $max = $d['sums'] ? max( $d['sums'] ) : 0.0;
            $sub = '';
            if ( $max > 0 ) {
                $i   = (int) array_search( $max, $d['sums'], true );
                $sub = sprintf( naws_label( 'sl_tile_peak' ), $d['tips'][ $i ] );
            }
            return [ 'name' => $name, 'value' => self::number( $a['base'], (float) $d['total'] ), 'unit' => $unit, 'sub' => $sub ];
        }

        return [
            'name'  => $name,
            'value' => self::number( $a['base'], (float) $d['last'] ),
            'unit'  => $d['unit'],
            'sub'   => sprintf( naws_label( 'sl_tile_range' ), self::number( $a['base'], (float) $d['lo'] ), self::number( $a['base'], (float) $d['hi'] ) . ' ' . $d['unit'] ),
        ];
    }

    /**
     * The words around the month block: the daily mean of the last day
     * with data and its date, the rain total over the span, and the
     * first and last day for the axis. $dr is null when the station has
     * no rain gauge; the rain fields are empty then.
     */
    public static function month_facts( array $dt, ?array $dr, array $dates, int $days ): array {
        $fmt  = naws_label( 'sl_month_axis_format' );
        $rain = $dr !== null;
        return [
            'mean_value' => self::number( 'Temperature', (float) $dt['last'] ) . ' ' . $dt['unit'],
            'mean_label' => sprintf( naws_label( 'sl_month_mean' ), wp_date( $fmt, $dt['last_ts'] ) ),
            'rain_value' => $rain ? $dr['value'] : '',
            /* translators: %d: number of days. */
            'rain_label' => $rain ? sprintf( _n( 'Rain in %d day', 'Rain in %d days', $days, 'xtx-integration-for-netatmo' ), $days ) : '',
            'axis_from'  => wp_date( $fmt, self::noon( $dates[0] ) ),
            'axis_to'    => wp_date( $fmt, self::noon( $dates[ count( $dates ) - 1 ] ) ),
        ];
    }

    /** One tile: the curve from markup(), laid into the card template. */
    public static function tile_markup( array $a, array $d ): string {
        $naws_slt          = self::tile_facts( $a, $d );
        $naws_slt['curve'] = self::markup( $a, $d );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sparkline-tile.php';
        return trim( (string) ob_get_clean() );
    }

    /**
     * The month block: the band row, the rain row when there is rain data,
     * and the date axis. $at/$ar come from normalise_atts() with their
     * viewBox set (680 × 64, 680 × 40), $dt/$dr from prepare().
     */
    public static function month_markup( array $at, array $dt, array $ar, ?array $dr, array $dates, int $days ): string {
        $naws_slm         = self::month_facts( $dt, $dr, $dates, $days );
        $naws_slm['band'] = self::markup( $at, $dt );
        $naws_slm['rain'] = $dr !== null ? self::markup( $ar, $dr ) : '';

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sparkline-month.php';
        return trim( (string) ob_get_clean() );
    }

    /** Days for the month block from the shortcode attribute: '' becomes 30, otherwise clamped to 7–366. */
    public static function month_days( $raw ): int {
        $raw = trim( (string) $raw );
        return $raw === '' ? 30 : self::clamp( intval( $raw ), 7, 366 );
    }

    /**
     * [naws_sparkline layout="month" days="30"]: temperature (band) and
     * rain per day from the station's daily row, 7–366 days. Nothing
     * when there is no temperature to draw; no rain row without rain data.
     */
    public static function render_month( array $atts ): string {
        $days = self::month_days( $atts['days'] ?? '' );
        $now  = time();

        $at = self::normalise_atts( [ 'param' => 'temp_avg', 'days' => (string) $days, 'band' => 'minmax' ] );
        $at['w'] = 680;
        $at['h'] = 64;
        $ft    = self::fetch( $at, $now );
        $dates = $ft['dates'];
        $dt    = self::prepare( $at, $ft );
        if ( $dt === null ) {
            return '';
        }
        // The band's own points may start later (or end earlier) than the
        // full span when days are missing — a fresh install, an outage.
        // Rain bars and the axis always cover every day, so the band's
        // geometry is pinned to the same span here.
        $dt['domain'] = [ self::noon( $dates[0] ), self::noon( $dates[ count( $dates ) - 1 ] ) ];

        $ar = self::normalise_atts( [ 'param' => 'rain_sum', 'days' => (string) $days ] );
        $ar['w'] = 680;
        $ar['h'] = 40;
        $dr = self::prepare( $ar, self::fetch( $ar, $now ) );

        return self::month_markup( $at, $dt, $ar, $dr, $dates, $days );
    }

    private static function clamp( int $v, int $lo, int $hi ): int {
        return max( $lo, min( $hi, $v ) );
    }
}
