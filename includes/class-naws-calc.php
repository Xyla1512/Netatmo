<?php
/**
 * Catalogue and dispatch for [naws_calc].
 *
 * Holds every computed value the shortcode can render, as plain metadata:
 * what kind it is, which NAWS_Helpers parameter supplies its unit and
 * conversion, how many decimals it carries, and which language key names it.
 *
 * catalogue() deliberately touches nothing — no options, no database, no
 * clock — so tests/test-calc-catalogue.php can read it without WordPress.
 * Everything that needs WordPress lives in the resolver methods.
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Calc {

    /**
     * Every value [naws_calc] knows.
     *
     * kind     – instant | dayclass | sum | index; decides which attributes apply
     * param    – NAWS_Helpers parameter name for unit and unit conversion,
     *            or null for values that are text or carry their own unit
     * decimals – default decimal places; -1 means "leave as produced"
     * label    – language key of the human-readable name
     *
     * @return array<string, array{kind:string, param:?string, decimals:int, label:string}>
     */
    public static function catalogue(): array {
        return [
            // ── thermal, from the current reading ──────────────────────────
            'dewpoint'          => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_dewpoint' ],
            'feels_like'        => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_feels_like' ],
            'heat_index'        => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_heat_index' ],
            'wet_bulb'          => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_wet_bulb' ],
            'bioclimate'        => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_bioclimate' ],

            // ── derived from a single reading ──────────────────────────────
            'wind_compass'      => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_wind_compass' ],
            'co2_level'         => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_co2_level' ],

            // ── astronomy, from the station coordinates ────────────────
            'sunrise'           => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_sunrise' ],
            'sunset'            => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_sunset' ],
            'daylength'         => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_daylength' ],
            'moon_phase'        => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_moon_phase' ],
            'moon_illumination' => [ 'kind' => 'instant', 'param' => 'Humidity',    'decimals' => 0,  'label' => 'calc_moon_illumination' ],
            'next_supermoon'    => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_next_supermoon' ],
            'next_lunar_eclipse'=> [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_next_lunar_eclipse' ],
        ];
    }

    /**
     * Is this a key the catalogue knows?
     */
    public static function has( string $key ): bool {
        return isset( self::catalogue()[ $key ] );
    }

    /**
     * Module type behind each alias, same mapping [naws_value] uses.
     */
    private const TYPE_MAP = [
        'outdoor' => 'NAModule1',
        'indoor'  => 'NAMain',
        'wind'    => 'NAModule2',
        'rain'    => 'NAModule3',
    ];

    /**
     * Resolve a module alias or MAC address to a module_id.
     *
     * @return string|null null when the station has no such module.
     */
    private static function module_id( string $alias ): ?string {
        $alias = strtolower( $alias );
        if ( ! isset( self::TYPE_MAP[ $alias ] ) ) {
            return $alias; // treated as a direct MAC address
        }
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            if ( $m['module_type'] === self::TYPE_MAP[ $alias ] ) {
                return $m['module_id'];
            }
        }
        return null;
    }

    /**
     * Latest value of one parameter on one module, in the unit Netatmo sends.
     *
     * Deliberately NOT run through NAWS_Helpers::format_value() — the maths
     * below needs °C and km/h, not whatever the display setting says. The
     * conversion happens once, at the very end, in the shortcode.
     */
    private static function reading( ?string $module_id, string $param ): ?float {
        if ( $module_id === null ) {
            return null;
        }
        foreach ( NAWS_Database::get_latest_readings( $module_id ) as $row ) {
            if ( $row['parameter'] === $param ) {
                return floatval( $row['value'] );
            }
        }
        return null;
    }

    /**
     * Wind speed in km/h from the wind module, 0.0 when there is none.
     *
     * A station without a wind gauge is normal, and the thermal formulas
     * behave sensibly at zero wind — so this returns 0.0 rather than null,
     * which would otherwise suppress the dew point on half the stations.
     */
    private static function wind_kmh(): float {
        return self::reading( self::module_id( 'wind' ), 'WindStrength' ) ?? 0.0;
    }

    /**
     * The raw value behind a catalogue key.
     *
     * Numbers come back in metric base units (°C, %); text values come back
     * as finished, translated strings. null means "the data does not support
     * this value" — the shortcode turns that into the fallback.
     *
     * @param string $key  Catalogue key.
     * @param array  $atts Shortcode attributes, already sanitised.
     * @return float|string|null
     */
    public static function raw( string $key, array $atts ) {
        if ( ! self::has( $key ) ) {
            NAWS_Logger::warning( 'calc', 'Unknown [naws_calc] value key: ' . $key );
            return null;
        }

        $module = self::module_id( (string) ( $atts['module'] ?? 'outdoor' ) );
        $temp   = self::reading( $module, 'Temperature' );
        $hum    = self::reading( $module, 'Humidity' );

        switch ( $key ) {
            case 'dewpoint':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::dew_point( $temp, $hum );

            case 'wet_bulb':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::wet_bulb( $temp, $hum );

            case 'heat_index':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::heat_index( $temp, $hum );

            case 'feels_like':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::feels_like( $temp, $hum, self::wind_kmh() );

            case 'bioclimate':
                if ( $temp === null || $hum === null ) {
                    return null;
                }
                $felt = NAWS_Astro::feels_like( $temp, $hum, self::wind_kmh() );
                return naws__( NAWS_Astro::thermal_sensation( $felt ) );

            case 'wind_compass':
                $angle = self::reading( self::module_id( 'wind' ), 'WindAngle' );
                return $angle === null ? null : NAWS_Helpers::degrees_to_compass( $angle );

            case 'co2_level':
                $ppm = self::reading( self::module_id( 'indoor' ), 'CO2' );
                if ( $ppm === null ) {
                    return null;
                }
                // get_co2_level() returns [ level, color, label ] — only the
                // already-translated label belongs in running text.
                $level = NAWS_Helpers::get_co2_level( $ppm );
                return $level['label'];
        }

        return null;
    }
}
