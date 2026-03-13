<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Historical data importer
 *
 * Strategy:
 *  - One row per day per station in naws_daily_summary
 *  - Each module contributes its columns to that one row via UPSERT
 *  - Fetches hourly data from getmeasure API, aggregates to daily values
 *  - Rain: sum of hourly values = daily total mm
 *  - Temp: min/max/avg of hourly values
 *  - Pressure: avg of hourly values (filtered 850-1100 hPa)
 *  - Wind: avg WindStrength, max GustStrength, circular-mean WindAngle
 */
class NAWS_Importer {

    const FETCH_TYPES = [
        'NAMain'    => [ 'Pressure' ],
        'NAModule1' => [ 'Temperature' ],
        'NAModule2' => [ 'WindStrength', 'WindAngle', 'GustStrength', 'GustAngle' ],
        'NAModule3' => [ 'sum_rain' ],   // scale=1day: actual daily total mm
    ];

    // Rain: scale=1day, type=sum_rain, optimize=true, real_time=true
    //       → one value per day = correct Netatmo daily total in mm
    // Temp/Pressure/Wind: scale=1hour → 24 values → aggregated daily
    const RAIN_SCALE     = '1day';
    const TEMP_SCALE     = '1hour';
    const DAYS_PER_CHUNK = 40;

    // ──────────────────────────────────────────────────────────────────
    // Public
    // ──────────────────────────────────────────────────────────────────

    public static function get_import_types( $module_type ) {
        $class = self::classify_module( $module_type );
        if ( ! $class ) return [];
        return self::FETCH_TYPES[ $class ] ?? [];
    }

    public static function classify_module( $module_type ) {
        $map = [
            'NAMain'    => 'NAMain',
            'NAModule1' => 'NAModule1',
            'NAModule2' => 'NAModule2',
            'NAModule3' => 'NAModule3',
            'NAModule4' => 'NAMain',
            'NHC'       => 'NAMain',
        ];
        return $map[ $module_type ] ?? null;
    }

    /**
     * Fetch one chunk for one module and upsert into naws_daily_summary.
     * Uses station_id (not module_id) as the row key so all modules
     * contribute to the same row per day.
     */
    public static function fetch_chunk( $device_id, $module_id, $module_type, $date_begin, $date_end ) {
        $class = self::classify_module( $module_type );
        $types = $class ? ( self::FETCH_TYPES[ $class ] ?? [] ) : [];

        if ( empty( $types ) ) {
            return new WP_Error( 'skip', "Modultyp {$module_type} wird nicht importiert." );
        }

        // Rain: 1day scale + real_time=true → API returns actual daily total mm per day
        // Temp/Pressure/Wind: 1hour scale → we aggregate to min/max/avg
        $is_rain   = ( $class === 'NAModule3' );
        $scale     = $is_rain ? self::RAIN_SCALE : self::TEMP_SCALE;
        $optimize  = $is_rain;   // optimize=true for rain (compact format with sum_rain)
        $real_time = $is_rain;   // real_time=true for rain (correct day alignment)

        $chunk_end = min( $date_end, $date_begin + ( self::DAYS_PER_CHUNK * DAY_IN_SECONDS ) - 1 );

        // No timestamp alignment needed: the JS already sends Berlin-midnight for date_begin
        // and Berlin 23:59:59 for date_end. The filter below uses date_begin/date_end directly.

        $api  = new NAWS_API();
        $body = $api->get_measure(
            $device_id, $module_id, $types,
            $date_begin, $chunk_end,
            $scale, $optimize, 1024, $real_time
        );

        if ( is_wp_error( $body ) ) return $body;

        if ( empty( $body ) ) {
            // Empty body = no data for this period (e.g. dry days, sensor offline)
            // Advance by 1 day so we don't skip 40 days of potential data
            $next = $date_begin + DAY_IN_SECONDS;
            return [
                'inserted'     => 0,
                'rows_fetched' => 0,
                'next_begin'   => $next <= $date_end ? $next : null,
                'debug'        => [ 'note' => 'API returned empty body – advancing 1 day' ],
            ];
        }

        // Parse API response into daily buckets
        $daily = self::parse_hourly_to_daily( $body, $types );

        if ( empty( $daily ) ) {
            return [
                'inserted'     => 0,
                'rows_fetched' => 0,
                'next_begin'   => $chunk_end < $date_end ? $chunk_end + 1 : null,
                'debug'        => [ 'note' => 'Response received but parsed 0 days' ],
            ];
        }

        $station_id = self::get_station_id( $module_id ) ?: $device_id;
        $inserted   = 0;

        // Compute allowed day range from the ORIGINAL function parameters (not modified vars).
        // The API sometimes returns ±1 extra day; we only save days within [date_begin..date_end].
        $tz_filter   = new DateTimeZone( 'Europe/Berlin' );
        $allowed_min   = ( new DateTimeImmutable( '@' . $date_begin ) )
                            ->setTimezone( $tz_filter )
                            ->format( 'Y-m-d' );
        $allowed_max   = ( new DateTimeImmutable( '@' . $date_end ) )
                            ->setTimezone( $tz_filter )
                            ->format( 'Y-m-d' );

        $skipped_days = [];

        foreach ( $daily as $day_date => $vals ) {
            // Skip days outside the requested range
            if ( $day_date < $allowed_min || $day_date > $allowed_max ) {
                $skipped_days[] = $day_date;
                continue;
            }

            // Build only the columns this module contributes
            $cols = self::map_to_columns( $class, $vals );
            if ( empty( $cols ) ) continue;

            // Upsert using station_id – one row per day per station
            self::upsert_by_station( $station_id, $day_date, $cols, $allowed_min, $allowed_max );
            $inserted++;
        }

        $last_day = max( array_keys( $daily ) );
        if ( $chunk_end < $date_end ) {
            // next_begin = midnight Berlin of the day after last_day
            $tz_b      = new DateTimeZone( 'Europe/Berlin' );
            $dt_next   = new DateTime( $last_day . ' 00:00:00', $tz_b );
            $dt_next->modify( '+1 day' );
            $next_begin = $dt_next->getTimestamp();
        } else {
            $next_begin = null;
        }

        return [
            'inserted'     => $inserted,
            'rows_fetched' => count( $daily ),
            'next_begin'   => $next_begin,
            'debug'        => [
                'class' => $class,
                'types' => $types,
                'first' => array_key_first( $daily ),
                'last'  => $last_day,
            ],
        ];
    }

    public static function build_job_list( $date_begin, $date_end ) {
        $modules = NAWS_Database::get_modules( true );
        $jobs    = [];
        foreach ( $modules as $m ) {
            $types = self::get_import_types( $m['module_type'] );
            if ( empty( $types ) ) continue;
            $jobs[] = [
                'module_id'   => $m['module_id'],
                'module_name' => $m['module_name'],
                'module_type' => $m['module_type'],
                'device_id'   => $m['station_id'],
                'types'       => $types,
                'date_begin'  => $date_begin,
                'date_end'    => $date_end,
                'total_days'  => (int) ceil( ( $date_end - $date_begin ) / DAY_IN_SECONDS ),
            ];
        }
        return $jobs;
    }

    // ──────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Parse Netatmo getmeasure response into daily buckets.
     *
     * Works for both scale=1hour and scale=1day:
     *   scale=1hour → many timestamps per day, aggregated into daily min/max/avg
     *   scale=1day  → one timestamp per day, value IS the daily total (rain)
     *
     * Netatmo returns: { "1735687800": [val], "1735774200": [val], ... }
     *
     * Returns: [ 'Y-m-d' => [ 'TypeName' => [v, ...], ... ], ... ]
     */
    /**
     * Convert a Unix timestamp to a calendar date string in Europe/Berlin timezone.
     * This is critical: Netatmo returns UTC timestamps, but we want local calendar days.
     * e.g. 1713479400 = 2024-04-18 22:30 UTC = 2024-04-19 00:30 Berlin → "2024-04-19"
     */
    private static function ts_to_berlin_date( int $ts ): string {
        static $tz = null;
        if ( $tz === null ) $tz = new DateTimeZone( 'Europe/Berlin' );
        // '@timestamp' creates UTC object; setTimezone() then converts to local date correctly
        return ( new DateTimeImmutable( '@' . $ts ) )
                    ->setTimezone( $tz )
                    ->format( 'Y-m-d' );
    }

    private static function parse_hourly_to_daily( $body, $types ) {
        $daily = [];

        // Detect format: optimize=true returns a sequential array of entry objects
        // [ { "beg_time": ts, "step_time": s, "value": [[v],[v],...] } ]
        // optimize=false returns an associative array keyed by Unix timestamp
        // { "1713479400": [v], "1713483000": [v], ... }
        //
        // Key distinction: optimize=true → first key is integer 0, value is an array with beg_time
        //                  optimize=false → first key is a large Unix timestamp string
        $first_key = array_key_first( $body );
        $is_optimized = (
            $first_key === 0
            && is_array( $body[0] )
            && isset( $body[0]['beg_time'] )
        );

        if ( $is_optimized ) {
            // Format B: optimize=true → [ { beg_time, step_time, value: [[v1,v2], ...] } ]
            foreach ( $body as $entry ) {
                if ( ! isset( $entry['beg_time'], $entry['value'] ) ) continue;
                $ts   = intval( $entry['beg_time'] );
                $step = intval( $entry['step_time'] ?? 86400 );
                foreach ( $entry['value'] as $i => $val_arr ) {
                    if ( ! is_array( $val_arr ) ) $val_arr = [ $val_arr ];
                    $entry_ts = $ts + ( $i * $step );
                    $day_key  = self::ts_to_berlin_date( $entry_ts );
                    foreach ( $types as $j => $type ) {
                        $v = $val_arr[ $j ] ?? null;
                        if ( $v !== null && $v !== false ) {
                            $daily[ $day_key ][ $type ][] = floatval( $v );
                        }
                    }
                }
            }
            return $daily;
        }

        // Format A: optimize=false → { "1713479400": [v1, v2], ... }
        foreach ( $body as $ts_str => $val_arr ) {
            if ( ! is_numeric( $ts_str ) ) continue;
            $ts      = intval( $ts_str );
            $day_key = self::ts_to_berlin_date( $ts );
            if ( ! is_array( $val_arr ) ) $val_arr = [ $val_arr ];
            foreach ( $types as $j => $type ) {
                $v = $val_arr[ $j ] ?? null;
                if ( $v !== null && $v !== false ) {
                    $daily[ $day_key ][ $type ][] = floatval( $v );
                }
            }
        }
        return $daily;
    }

    /**
     * Map parsed hourly values for one module class to DB column values.
     * Returns only the columns this module contributes.
     */
    private static function map_to_columns( $class, $vals ) {
        $cols = [];

        if ( $class === 'NAModule1' ) {
            // Outdoor temperature module
            $temps = $vals['Temperature'] ?? [];
            if ( ! empty( $temps ) ) {
                $cols['temp_min'] = round( min( $temps ), 1 );
                $cols['temp_max'] = round( max( $temps ), 1 );
                $cols['temp_avg'] = round( array_sum( $temps ) / count( $temps ), 1 );
            }

        } elseif ( $class === 'NAMain' ) {
            // Main station: pressure
            $press = $vals['Pressure'] ?? $vals['AbsolutePressure'] ?? [];
            // Filter invalid values (only sea-level pressure range)
            $press = array_values( array_filter( $press, fn($v) => $v > 850 && $v < 1100 ) );
            if ( ! empty( $press ) ) {
                $cols['pressure_avg'] = round( array_sum( $press ) / count( $press ), 1 );
            }

        } elseif ( $class === 'NAModule2' ) {
            // Wind module
            $wind = $vals['WindStrength'] ?? [];
            if ( ! empty( $wind ) ) {
                $cols['wind_avg'] = round( array_sum( $wind ) / count( $wind ), 1 );
            }
            $gust = $vals['GustStrength'] ?? [];
            if ( ! empty( $gust ) ) {
                $cols['gust_max'] = round( max( $gust ), 1 );
            }
            // Circular mean for wind angle
            $angles = $vals['WindAngle'] ?? [];
            if ( ! empty( $angles ) ) {
                $sin_sum = array_sum( array_map( fn($a) => sin( deg2rad( $a ) ), $angles ) );
                $cos_sum = array_sum( array_map( fn($a) => cos( deg2rad( $a ) ), $angles ) );
                $cols['wind_angle'] = round( fmod( rad2deg( atan2( $sin_sum, $cos_sum ) ) + 360, 360 ), 0 );
            }

        } elseif ( $class === 'NAModule3' ) {
            // scale=1day + type=sum_rain + optimize=true + real_time=true
            // → one value per day = correct daily total in mm
            $rain = array_values( array_filter(
                $vals['sum_rain'] ?? $vals['Rain'] ?? [],
                fn($v) => $v >= 0
            ) );
            if ( ! empty( $rain ) ) {
                // 1day scale returns exactly one value; sum as safety net
                $cols['rain_sum'] = round( array_sum( $rain ), 1 );
            }
            // Dry days: API omits the day entirely → rain_sum stays NULL
        }

        return $cols;
    }

    /**
     * Upsert a daily row keyed by station_id + day_date.
     * All modules write to the SAME row – so Temp, Pressure and Rain
     * end up in one row per day instead of three separate rows.
     */
    private static function upsert_by_station( $station_id, $day_date, $cols, $min_date = null, $max_date = null ) {
        // Hard date-range guard: reject days outside the requested window
        // This is the final safety net regardless of any upstream filter issues
        if ( $min_date !== null && $day_date < $min_date ) return;
        if ( $max_date !== null && $day_date > $max_date ) return;

        global $wpdb;
        $table = $wpdb->prefix . NAWS_TABLE_DAILY;

        $allowed = [
            'temp_min', 'temp_max', 'temp_avg',
            'pressure_avg',
            'rain_sum',
            'humidity_avg',
            'indoor_temp_avg', 'indoor_humidity_avg',
            'co2_avg', 'noise_avg',
            'wind_avg', 'gust_max', 'wind_angle',
        ];
        $cols    = array_intersect_key( $cols, array_flip( $allowed ) );
        if ( empty( $cols ) ) return;

        $col_list = implode( ', ', array_keys( $cols ) );
        $val_ph   = implode( ', ', array_fill( 0, count( $cols ), '%f' ) );

        $on_dup = implode( ', ', array_map(
            fn($k) => "{$k} = COALESCE(VALUES({$k}), {$k})",
            array_keys( $cols )
        ) );

        $params = array_merge(
            [ $station_id, $station_id, $day_date, $day_date . ' 00:00:00' ],
            array_values( $cols )
        );

        $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "INSERT INTO {$table} (module_id, station_id, day_date, created_at, {$col_list})
             VALUES (%s, %s, %s, %s, {$val_ph})
             ON DUPLICATE KEY UPDATE {$on_dup}",
            $params
        ) );
    }

    private static function get_station_id( $module_id ) {
        global $wpdb;
        $sid = $wpdb->get_var( $wpdb->prepare(
            "SELECT station_id FROM {$wpdb->prefix}naws_modules WHERE module_id = %s",
            $module_id
        ) );
        return $sid ?: $module_id;
    }
}
