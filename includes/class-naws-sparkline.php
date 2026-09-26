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
     * was asked for).
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
     *
     * @param array $pts [[ts, value], …] or, with $band, [[ts, value, low, high], …].
     */
    public static function geometry( array $pts, int $w, int $h, bool $band = false ): array {
        $n    = count( $pts );
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

    private static function clamp( int $v, int $lo, int $hi ): int {
        return max( $lo, min( $hi, $v ) );
    }
}
