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

    private static function clamp( int $v, int $lo, int $hi ): int {
        return max( $lo, min( $hi, $v ) );
    }
}
