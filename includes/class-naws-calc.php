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
     * kind      – instant | dayclass | sum | index; decides which attributes apply
     * param     – NAWS_Helpers parameter name for unit and unit conversion,
     *             or null for values that are text or carry their own unit
     * unit      – literal unit string, for values with no sensor parameter
     *             to borrow one from (day counts, degree days); '' means
     *             explicitly unitless, not "forgot to set it"
     * decimals  – default decimal places; -1 means "leave as produced"
     * label     – language key of the human-readable name
     * field     – daily-summary column a dayclass entry matches on
     *             (temp_min | temp_max | temp_avg)
     * op        – comparison operator a dayclass entry matches with
     *             ('<' | '>' | '>=')
     * threshold – comparison value for a dayclass entry; null means "take
     *             it from the settings" (heating_limit or cooling_limit),
     *             which is what keeps that limit country-configurable
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
            'next_lunar_eclipse' => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_next_lunar_eclipse' ],

            // ── Tagesklassen aus der Tagestabelle ──────────────────────
            'ice_days'          => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_ice_days',        'field' => 'temp_max', 'op' => '<',  'threshold' => 0.0 ],
            'frost_days'        => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_frost_days',      'field' => 'temp_min', 'op' => '<',  'threshold' => 0.0 ],
            'summer_days'       => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_summer_days',     'field' => 'temp_max', 'op' => '>=', 'threshold' => 25.0 ],
            'hot_days'          => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_hot_days',        'field' => 'temp_max', 'op' => '>=', 'threshold' => 30.0 ],
            'tropical_nights'   => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_tropical_nights', 'field' => 'temp_min', 'op' => '>=', 'threshold' => 20.0 ],
            'heating_days'      => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_heating_days',    'field' => 'temp_avg', 'op' => '<',  'threshold' => null ],
            'cooling_days'      => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_cooling_days',    'field' => 'temp_avg', 'op' => '>',  'threshold' => null ],
        ];
    }

    /**
     * Is this a key the catalogue knows?
     */
    public static function has( string $key ): bool {
        return isset( self::catalogue()[ $key ] );
    }

    /**
     * Unit label for a catalogue entry.
     *
     * Two sources, never both: an entry either names a NAWS_Helpers sensor
     * parameter — which brings its own unit and its own °C/°F conversion —
     * or it carries a literal unit of its own. Degree days (Kd) and day
     * counts have no sensor parameter to borrow from, which is why the
     * literal exists.
     */
    public static function unit_for( string $key ): string {
        $entry = self::catalogue()[ $key ] ?? null;
        if ( $entry === null ) {
            return '';
        }
        if ( ! empty( $entry['unit'] ) ) {
            return (string) $entry['unit'];
        }
        return $entry['param'] ? NAWS_Helpers::get_unit( $entry['param'] ) : '';
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
     * The module_id of the station row in naws_daily_summary.
     *
     * Measured on a real installation: the daily table holds rows for the
     * station (NAMain) and for indoor modules only — outdoor, wind and rain
     * modules have no row of their own. compute_daily_summary() writes the
     * station aggregates under the station_id, so outdoor temperatures and
     * rain both live on the station row. Reading "the outdoor module" here
     * would return nothing at all.
     */
    private static function station_row_id( array $atts ): ?string {
        $wanted = isset( $atts['station'] ) ? sanitize_text_field( (string) $atts['station'] ) : '';
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            if ( $m['module_type'] !== 'NAMain' ) {
                continue;
            }
            if ( $wanted === '' || $m['module_id'] === $wanted || $m['station_id'] === $wanted ) {
                return $m['module_id'];
            }
        }
        return null;
    }

    /**
     * Resolve period/year attributes into a date range, in the site timezone.
     *
     * @return array{from:string,to:string} Both 'Y-m-d'.
     */
    private static function period_range( array $atts ): array {
        $today = wp_date( 'Y-m-d' );

        $year = isset( $atts['year'] ) ? intval( $atts['year'] ) : 0;
        if ( $year >= 1900 && $year <= 2999 ) {
            return [ 'from' => sprintf( '%04d-01-01', $year ), 'to' => sprintf( '%04d-12-31', $year ) ];
        }

        $period = strtolower( (string) ( $atts['period'] ?? 'year' ) );

        if ( $period === 'all' ) {
            return [ 'from' => '1900-01-01', 'to' => $today ];
        }
        if ( $period === 'month' ) {
            return [ 'from' => wp_date( 'Y-m-01' ), 'to' => $today ];
        }
        if ( preg_match( '/^(\d+)d$/', $period, $m ) ) {
            $days = max( 1, intval( $m[1] ) );
            return [ 'from' => wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ), 'to' => $today ];
        }

        // 'year' — the running calendar year, and the default.
        return [ 'from' => wp_date( 'Y-01-01' ), 'to' => $today ];
    }

    /**
     * Daily rows of the station for the requested period.
     *
     * Deliberately reuses NAWS_Database::get_daily_summaries() rather than
     * querying here: it already selects by date range and module, sorts
     * ascending, and carries its own transient cache. Ten shortcodes on one
     * page therefore cost one query, not ten.
     */
    private static function daily_rows( array $atts, array $fields ): array {
        $station = self::station_row_id( $atts );
        if ( $station === null ) {
            return [];
        }
        $range = self::period_range( $atts );

        return NAWS_Database::get_daily_summaries( [
            'module_id' => $station,
            'date_from' => $range['from'],
            'date_to'   => $range['to'],
            'fields'    => $fields,
            'group_by'  => 'day',
        ] );
    }

    /**
     * Build the matcher for a day class from its catalogue metadata.
     *
     * A null threshold means "take it from the settings" — that is how the
     * heating and cooling limits stay country-configurable without every
     * shortcode repeating them.
     */
    private static function day_matcher( array $entry ): callable {
        $field = (string) $entry['field'];
        $op    = (string) $entry['op'];
        $limit = $entry['threshold'];

        if ( $limit === null ) {
            $opts  = get_option( 'naws_settings', [] );
            $limit = ( $op === '>' )
                ? floatval( $opts['cooling_limit'] ?? 18.0 )
                : floatval( $opts['heating_limit'] ?? 15.0 );
        }
        $limit = (float) $limit;

        return static function ( array $row ) use ( $field, $op, $limit ): bool {
            $v = $row[ $field ] ?? null;
            if ( $v === null ) {
                return false;
            }
            $v = (float) $v;
            if ( $op === '<' )  return $v <  $limit;
            if ( $op === '>' )  return $v >  $limit;
            if ( $op === '>=' ) return $v >= $limit;
            return false;
        };
    }

    /**
     * The raw value behind a catalogue key.
     *
     * Dispatches on the entry's kind. Each kind reads different sources and
     * honours different attributes, so keeping them in one switch would mean
     * every branch paying for every other branch's setup — an instant value
     * needs a current reading, a day class needs a range of daily rows.
     *
     * @return float|string|null null means "the data does not support this".
     */
    public static function raw( string $key, array $atts ) {
        if ( ! self::has( $key ) ) {
            static $logged = [];
            if ( ! isset( $logged[ $key ] ) ) {
                $logged[ $key ] = true;
                NAWS_Logger::warning( 'calc', 'Unknown [naws_calc] value key: ' . $key );
            }
            return null;
        }

        switch ( self::catalogue()[ $key ]['kind'] ) {
            case 'instant':
                return self::raw_instant( $key, $atts );
            case 'dayclass':
                return self::raw_dayclass( $key, $atts );
        }

        return null;
    }

    /**
     * Values that follow from the current reading or the station location.
     */
    private static function raw_instant( string $key, array $atts ) {
        $module = self::module_id( (string) ( $atts['module'] ?? 'outdoor' ) );
        $temp   = self::reading( $module, 'Temperature' );
        $hum    = self::reading( $module, 'Humidity' );

        switch ( $key ) {
            case 'dewpoint':
                return ( $temp === null || $hum === null || $hum <= 0.0 ) ? null : NAWS_Astro::dew_point( $temp, $hum );

            case 'wet_bulb':
                return ( $temp === null || $hum === null || $hum <= 0.0 ) ? null : NAWS_Astro::wet_bulb( $temp, $hum );

            case 'heat_index':
                if ( $temp === null || $hum === null || ! NAWS_Astro::heat_index_applies( $temp ) ) {
                    return null; // out of the regression's domain -> fallback
                }
                return NAWS_Astro::heat_index( $temp, $hum );

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

            case 'sunrise':
            case 'sunset': {
                $coords = NAWS_Astro::get_coords();
                if ( ! $coords ) {
                    return null;
                }
                $sun  = NAWS_Astro::sun_times( $coords['lat'], $coords['lng'] );
                $time = ( $key === 'sunrise' ) ? ( $sun['rise'] ?? '' ) : ( $sun['set'] ?? '' );

                // '--:--' means the sun did not cross the horizon that day.
                // Above the Arctic Circle that is normal, not an error — and
                // this plugin ships a Norwegian translation.
                return ( $time === '' || $time === '--:--' ) ? null : $time;
            }

            case 'daylength': {
                $coords = NAWS_Astro::get_coords();
                if ( ! $coords ) {
                    return null;
                }
                // date_sun_info() is used directly here because sun_times()
                // hands back formatted strings, which cannot be subtracted.
                $info = date_sun_info( time(), $coords['lat'], $coords['lng'] );

                // Polar day and polar night come back as bool true/false
                // rather than a timestamp. Neither has a length to report.
                if ( ! is_int( $info['sunrise'] ?? null ) || ! is_int( $info['sunset'] ?? null ) ) {
                    return null;
                }
                $seconds = $info['sunset'] - $info['sunrise'];
                if ( $seconds <= 0 ) {
                    return null;
                }
                return sprintf( '%d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ) );
            }

            case 'moon_phase': {
                $moon = NAWS_Astro::moon_data();
                // Already translated by moon_data() — do not translate twice.
                return empty( $moon['name'] ) ? null : $moon['name'];
            }

            case 'moon_illumination': {
                $moon = NAWS_Astro::moon_data();
                return isset( $moon['phase_pct'] ) ? floatval( $moon['phase_pct'] ) : null;
            }

            case 'next_supermoon': {
                $ev = NAWS_Astro::next_supermoon();
                return empty( $ev['date'] ) ? null : $ev['date'];
            }

            case 'next_lunar_eclipse': {
                $ev = NAWS_Astro::next_lunar_eclipse();
                return empty( $ev['date'] ) ? null : $ev['date'];
            }
        }

        return null;
    }

    /**
     * Day classes: countable and streakable over a range of daily rows.
     */
    private static function raw_dayclass( string $key, array $atts ) {
        $entry = self::catalogue()[ $key ];
        $rows  = self::daily_rows( $atts, [ 'temp_min', 'temp_max', 'temp_avg' ] );

        // "No data" and "no such days" must not look alike: an empty range
        // gives the fallback, a range with rows and no hits gives 0.
        if ( empty( $rows ) ) {
            return null;
        }

        $matches = self::day_matcher( $entry );
        $mode    = strtolower( (string) ( $atts['mode'] ?? 'count' ) );

        if ( $mode === 'streak' ) {
            return (float) NAWS_Climate::current_streak( $rows, $matches );
        }
        if ( $mode === 'max_streak' ) {
            return (float) NAWS_Climate::max_streak( $rows, $matches );
        }
        return (float) NAWS_Climate::count_days( $rows, $matches );
    }
}
