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
     * needs     – daily-summary columns this value requires to be non-NULL
     *             on a row before that row counts as measured. A row exists
     *             as soon as ANY column has a value, so a day with only a
     *             pressure reading still produces a row whose temperatures
     *             are all NULL — without this, such days silently count as
     *             "measured" and turn "nobody measured this" into "0 frost
     *             days". Absent (instant values) means no filtering applies.
     *
     * @return array<string, array{kind:string, param:?string, decimals:int, label:string, unit?:string, field?:string, op?:string, threshold?:?float, needs?:string[]}>
     */
    public static function catalogue(): array {
        // The table is constant in everything but syntax: every entry is a
        // literal, with no option, no clock and no translated string in it —
        // labels are language KEYS. So it is built once per request, which is
        // worth doing because it is asked for often: has(), unit_for(), raw()
        // and the documentation table each want it, the last one per row.
        static $cache = null;
        if ( $cache === null ) {
            $cache = self::build_catalogue();
        }
        return $cache;
    }

    /**
     * The literal table behind catalogue().
     */
    private static function build_catalogue(): array {
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
            'ice_days'          => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_ice_days',        'field' => 'temp_max', 'op' => '<',  'threshold' => 0.0,  'needs' => [ 'temp_max' ] ],
            'frost_days'        => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_frost_days',      'field' => 'temp_min', 'op' => '<',  'threshold' => 0.0,  'needs' => [ 'temp_min' ] ],
            'summer_days'       => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_summer_days',     'field' => 'temp_max', 'op' => '>=', 'threshold' => 25.0, 'needs' => [ 'temp_max' ] ],
            'hot_days'          => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_hot_days',        'field' => 'temp_max', 'op' => '>=', 'threshold' => 30.0, 'needs' => [ 'temp_max' ] ],
            'tropical_nights'   => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_tropical_nights', 'field' => 'temp_min', 'op' => '>=', 'threshold' => 20.0, 'needs' => [ 'temp_min' ] ],
            'heating_days'      => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_heating_days',    'field' => 'temp_avg', 'op' => '<',  'threshold' => null, 'needs' => [ 'temp_avg' ] ],
            'cooling_days'      => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_cooling_days',    'field' => 'temp_avg', 'op' => '>',  'threshold' => null, 'needs' => [ 'temp_avg' ] ],

            // ── Summenkennzahlen ──────────────────────────────────────
            'hdd'        => [ 'kind' => 'sum', 'param' => null, 'unit' => 'Kd', 'decimals' => 0, 'label' => 'calc_hdd',        'needs' => [ 'temp_avg' ] ],
            'cdd'        => [ 'kind' => 'sum', 'param' => null, 'unit' => 'Kd', 'decimals' => 0, 'label' => 'calc_cdd',        'needs' => [ 'temp_avg' ] ],
            'gdd'        => [ 'kind' => 'sum', 'param' => null, 'unit' => 'Kd', 'decimals' => 0, 'label' => 'calc_gdd',        'needs' => [ 'temp_min', 'temp_max' ] ],
            'glts'       => [ 'kind' => 'sum', 'param' => null, 'unit' => '°C', 'decimals' => 1, 'label' => 'calc_glts',       'needs' => [ 'temp_avg' ] ],
            'glts_start' => [ 'kind' => 'sum', 'param' => null, 'unit' => '',   'decimals' => 0, 'label' => 'calc_glts_start', 'needs' => [ 'temp_avg' ] ],

            // ── Index ────────────────────────────────────────────────────
            // Dimensionless by definition: the deviate IS the value, so the
            // unit is explicitly empty rather than merely unset.
            'spi'        => [ 'kind' => 'index', 'param' => null, 'unit' => '', 'decimals' => 2, 'label' => 'calc_spi', 'needs' => [ 'rain_sum' ] ],
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
     * Public because [naws_value] resolves the same four aliases and used to
     * carry its own copy of the table. Two copies of "what outdoor means" is
     * one too many — and they had already drifted: the copy in sc_value()
     * lower-cased the alias for the lookup but handed a MAC address on with
     * its original case, so an uppercase MAC matched nothing.
     *
     * @return string|null null when the station has no such module.
     */
    public static function module_id( string $alias ): ?string {
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
     * Rows that actually carry every column this value needs.
     *
     * A daily row exists as soon as ANY column has a value — a day with only
     * a pressure reading still produces a row whose temperatures are all
     * NULL. Counting those as measured days is how "nobody measured this
     * month" turns into "0 frost days", which is exactly the confusion the
     * fallback exists to prevent.
     */
    private static function rows_with( array $rows, array $needs ): array {
        if ( empty( $needs ) ) {
            return $rows;
        }
        return array_values( array_filter( $rows, static function ( $row ) use ( $needs ) {
            foreach ( $needs as $field ) {
                if ( ( $row[ $field ] ?? null ) === null ) {
                    return false;
                }
            }
            return true;
        } ) );
    }

    /**
     * Force the period a catalogue key is defined over, regardless of what
     * the shortcode attribute says.
     *
     * glts and glts_start are defined as "since 1 January", so they ignore
     * an explicit period and honour only an explicit year. spi is defined
     * over the whole record — its own history IS the reference — so it
     * ignores both. Every reader of the daily table calls this, which is
     * what keeps the coverage note from ever describing a different window
     * than the number it annotates.
     */
    private static function normalise_period_atts( string $key, array $atts ): array {
        if ( $key === 'glts' || $key === 'glts_start' ) {
            $atts['period'] = 'year';
        }
        if ( $key === 'spi' ) {
            $atts['period'] = 'all';
            unset( $atts['year'] );
        }
        return $atts;
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
            case 'sum':
                return self::raw_sum( $key, $atts );
            case 'index':
                return self::raw_index( $key, $atts );
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
                return naws_label( NAWS_Astro::thermal_sensation( $felt ) );

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
        $rows  = self::rows_with(
            self::daily_rows( $atts, [ 'temp_min', 'temp_max', 'temp_avg' ] ),
            $entry['needs'] ?? []
        );

        // "No data" and "no such days" must not look alike: an empty range
        // (or a range with rows that never carry the column this value
        // needs) gives the fallback, a range with matching rows and no
        // hits gives 0.
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

    /**
     * The index: a distribution fit over the whole record.
     *
     * Unlike the other kinds this one has no period of its own to argue
     * about — the record IS the reference, so normalise_period_atts() pins
     * it to 'all'. What it does have is a window length, and the arithmetic
     * lives one layer down in NAWS_Climate.
     */
    private static function raw_index( string $key, array $atts ) {
        $rows = self::rows_with(
            self::daily_rows( self::normalise_period_atts( $key, $atts ), [ 'rain_sum' ] ),
            [ 'rain_sum' ]
        );
        if ( empty( $rows ) ) {
            return null;
        }

        return NAWS_Climate::spi(
            NAWS_Climate::monthly_sums( $rows, 'rain_sum' ),
            self::window_months( $atts )
        );
    }

    /**
     * How much of a reference this record offers the index.
     *
     * The documentation page shows it so the value can be judged before it
     * is built into a page: below two years the index refuses, thirty is the
     * customary reference, and everything between is a tendency.
     *
     * @return array{months:int,years:int}
     */
    public static function spi_basis( array $atts = [] ): array {
        $rows = self::rows_with(
            self::daily_rows( self::normalise_period_atts( 'spi', $atts ), [ 'rain_sum' ] ),
            [ 'rain_sum' ]
        );
        $months = count( NAWS_Climate::monthly_sums( $rows, 'rain_sum' ) );

        return [ 'months' => $months, 'years' => intdiv( $months, 12 ) ];
    }

    /**
     * Window length for the index, in months.
     *
     * Four lengths are documented, and anything else falls back to three
     * rather than computing an index nobody asked for — the same silent
     * fallback period= and mode= already use for values they do not know.
     */
    private static function window_months( array $atts ): int {
        $months = isset( $atts['months'] ) ? intval( $atts['months'] ) : 0;
        return in_array( $months, [ 1, 3, 6, 12 ], true ) ? $months : 3;
    }

    /**
     * How much of the requested period actually carries data.
     *
     * Only meaningful for kinds that read the daily table; instant values
     * return null. Counting gaps is the honest way to publish a frost-day
     * total: 31 out of 31 days means something different from 31 out of 200.
     */
    public static function coverage( string $key, array $atts ): ?array {
        $entry = self::catalogue()[ $key ] ?? null;
        if ( $entry === null || $entry['kind'] === 'instant' ) {
            return null;
        }

        // Read exactly the columns this value needs — otherwise the index,
        // which lives on rain_sum, would filter every row away for want of a
        // column nobody selected.
        $needs    = $entry['needs'] ?? [];
        $cov_atts = self::normalise_period_atts( $key, $atts );
        $rows     = self::rows_with(
            self::daily_rows( $cov_atts, $needs ?: [ 'temp_min', 'temp_max', 'temp_avg' ] ),
            $needs
        );
        $range    = self::period_range( $cov_atts );

        $from_str = $range['from'];

        // 'all' resolves to 1900-01-01 in period_range() — a denominator no
        // station's data can ever fill, which turns an honest coverage note
        // into 126 years of noise. Anchor it to the first day that actually
        // carries this value instead.
        if ( $from_str === '1900-01-01' ) {
            if ( empty( $rows ) ) {
                return [ 'rows' => 0, 'days' => 0 ];
            }
            $from_str = (string) $rows[0]['day_date'];
        }

        $from = strtotime( $from_str . ' 12:00:00' );
        $to   = strtotime( $range['to'] . ' 12:00:00' );
        $days = ( $from && $to && $to >= $from ) ? intval( round( ( $to - $from ) / DAY_IN_SECONDS ) ) + 1 : 0;

        return [ 'rows' => count( $rows ), 'days' => $days ];
    }

    /**
     * A float attribute, falling back to a default when absent or empty.
     *
     * The empty-string check matters: shortcode_atts() defaults these to '',
     * and floatval('') is 0.0 — which would silently turn every day into a
     * heating day rather than falling back to the configured limit.
     */
    private static function att_float( array $atts, string $key, float $default ): float {
        return ( isset( $atts[ $key ] ) && $atts[ $key ] !== '' ) ? floatval( $atts[ $key ] ) : $default;
    }

    /**
     * Sum indices over a range of daily rows.
     */
    private static function raw_sum( string $key, array $atts ) {
        $entry = self::catalogue()[ $key ];

        // The grassland sum is defined as "since the first of January", so it
        // ignores period and honours only an explicit year — normalise_period_atts()
        // applies that override.
        $sum_atts = self::normalise_period_atts( $key, $atts );

        $rows = self::rows_with(
            self::daily_rows( $sum_atts, [ 'temp_min', 'temp_max', 'temp_avg' ] ),
            $entry['needs'] ?? []
        );
        if ( empty( $rows ) ) {
            return null;
        }

        switch ( $key ) {
            // Only the two degree-day branches read the settings, so only
            // they pay for the option lookup.
            case 'hdd': {
                $opts  = get_option( 'naws_settings', [] );
                $limit = self::att_float( $atts, 'base', floatval( $opts['heating_limit'] ?? 15.0 ) );
                return NAWS_Climate::degree_days( $rows, $limit, floatval( $opts['room_temp'] ?? 20.0 ), 'heating' );
            }

            case 'cdd': {
                $opts  = get_option( 'naws_settings', [] );
                $limit = self::att_float( $atts, 'base', floatval( $opts['cooling_limit'] ?? 18.0 ) );
                return NAWS_Climate::degree_days( $rows, $limit, 0.0, 'cooling' );
            }

            case 'gdd':
                $base = self::att_float( $atts, 'base', 10.0 );
                $cap  = self::att_float( $atts, 'cap', 30.0 );
                return NAWS_Climate::growing_degree_days( $rows, $base, $cap );

            case 'glts':
                return NAWS_Climate::grassland_sum( $rows );

            case 'glts_start':
                $date = NAWS_Climate::grassland_start( $rows );
                // A sum below 200 is a correct value, not a missing one — say
                // so in words rather than showing an empty field.
                return $date === null
                    ? __( 'not yet reached', 'xtx-integration-for-netatmo' )
                    : wp_date( get_option( 'date_format', 'd.m.Y' ), strtotime( $date . ' 12:00:00' ) );
        }

        return null;
    }
}
