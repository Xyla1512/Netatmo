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
}
