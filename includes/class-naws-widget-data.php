<?php
/**
 * NAWS_Widget_Data – prepares the sidebar widget's display structure.
 *
 * Pure function, deliberately: no WordPress, no database, no HTML. The
 * caller formats values and hands them in already rendered, so the whole
 * degradation matrix can be exercised by a plain PHP script.
 *
 * Rain and wind gauges are paid Netatmo add-ons that most installations do
 * not have, so holes in the input are the normal case, not an edge case.
 *
 * @package NAWS
 * @since   1.8.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Widget_Data {

    /** The only two forecast lengths the widget offers. */
    const DAY_CHOICES = [ 3, 5 ];

    /**
     * Width bounds in px.
     *
     * MIN is the column the layout was drawn against. MAX is where growing
     * stops paying: past 500 px the five forecast columns are wider than
     * anything in them and the widget reads as an empty frame.
     */
    const MIN_WIDTH     = 250;
    const MAX_WIDTH     = 500;
    const DEFAULT_WIDTH = 250;

    /**
     * Build the display structure.
     *
     * @param array $station  [ 'temp'|'rain'|'wind' => [ 'value','unit' ] | null ]
     * @param array $forecast Result of NAWS_Forecast::get_forecast(), may hold 'error'.
     * @param int   $days     Requested forecast length; normalised to 3 or 5.
     * @return array{temp: ?array, tiles: array, days: array, empty: bool}
     */
    public static function build( array $station, array $forecast, int $days ): array {
        $days = self::normalise_days( $days );

        $temp = self::pair( $station['temp'] ?? null );

        // Order is fixed: rain then wind. A missing gauge drops its tile
        // entirely; the remaining one takes the full width in CSS.
        $tiles = [];
        foreach ( [ 'rain', 'wind' ] as $key ) {
            $pair = self::pair( $station[ $key ] ?? null );
            if ( $pair !== null ) {
                $tiles[] = [ 'key' => $key, 'value' => $pair['value'], 'unit' => $pair['unit'] ];
            }
        }

        $rows = [];
        if ( ! isset( $forecast['error'] ) && ! empty( $forecast['days'] ) && is_array( $forecast['days'] ) ) {
            foreach ( array_slice( $forecast['days'], 0, $days ) as $day ) {
                // Forecast entries are days, so is_day is always true.
                $state = NAWS_Weather_State::wmo_to_state( (int) ( $day['weathercode'] ?? -1 ), true );
                $rows[] = [
                    'date'  => (string) ( $day['date'] ?? '' ),
                    'state' => $state,
                    'max'   => isset( $day['temp_max'] ) ? (float) $day['temp_max'] : null,
                    'min'   => isset( $day['temp_min'] ) ? (float) $day['temp_min'] : null,
                ];
            }
        }

        return [
            'temp'  => $temp,
            'tiles' => $tiles,
            'days'  => $rows,
            'empty' => ( $temp === null && $tiles === [] && $rows === [] ),
        ];
    }

    /**
     * Clamp to one of the two offered lengths.
     *
     * Four days is not offered: it would look like five with one column
     * missing and adds a third layout to maintain for no gain.
     */
    private static function normalise_days( int $days ): int {
        return $days < 4 ? 3 : 5;
    }

    /**
     * Clamp a requested width into the supported range.
     *
     * Out-of-range values are pulled to the nearest bound rather than
     * rejected, the same way normalise_days() pulls 4 to 5: someone who
     * typed width="600" wants a wide widget, not the 250 px default.
     *
     * Anything that is not a positive number at all — an empty attribute, a
     * stray word, a zero — is treated as "not set" and yields the default.
     *
     * @param  mixed $width Raw attribute or option value.
     * @return int          A width between MIN_WIDTH and MAX_WIDTH.
     */
    public static function normalise_width( $width ): int {
        $width = is_numeric( $width ) ? (int) $width : 0;

        if ( $width <= 0 ) {
            return self::DEFAULT_WIDTH;
        }

        return max( self::MIN_WIDTH, min( self::MAX_WIDTH, $width ) );
    }

    /** Validate a value/unit pair, returning null for anything unusable. */
    private static function pair( $raw ): ?array {
        if ( ! is_array( $raw ) || ! isset( $raw['value'] ) || $raw['value'] === '' ) {
            return null;
        }
        return [
            'value' => (string) $raw['value'],
            'unit'  => (string) ( $raw['unit'] ?? '' ),
        ];
    }
}
