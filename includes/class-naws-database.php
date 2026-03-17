<?php
/**
 * Dedicated database abstraction layer for NAWS.
 *
 * All queries use $wpdb->prepare() where user input is involved.
 * Table names are constructed from plugin constants (NAWS_TABLE_*) prefixed
 * with $wpdb->prefix — never from user input. Caching is handled via the
 * WordPress Transient API at the method level (see TTL_* constants).
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery   -- this IS the DB layer
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching     -- transient caching at method level
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names use constants only
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange  -- install/migration methods
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Database layer
 *
 * Tables:
 *  naws_modules       – Module metadata + active/inactive flag
 *  naws_readings      – Raw sensor readings (rolling window, e.g. last 90 days)
 *  naws_daily_summary – Aggregated daily statistics kept forever
 */
class NAWS_Database {

    // ================================================================
    // Cache constants
    // ================================================================

    /** Transient prefix for all NAWS caches. */
    const CACHE_PREFIX = 'naws_cache_';

    /** Cache TTLs in seconds. */
    const TTL_MODULES  = HOUR_IN_SECONDS;         // Modules change rarely
    const TTL_LATEST   = 5 * MINUTE_IN_SECONDS;   // Refreshed on every sync
    const TTL_RAIN24   = 5 * MINUTE_IN_SECONDS;   // Rolling rain window
    const TTL_READINGS = 10 * MINUTE_IN_SECONDS;   // Grouped time-series
    const TTL_DAILY    = HOUR_IN_SECONDS;          // Historical daily data

    // ================================================================
    // Install / Migrations
    // ================================================================

    public static function install() {
        global $wpdb;

        // MUST load upgrade functions before calling dbDelta()
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $cc = $wpdb->get_charset_collate();

        /* ── modules ─────────────────────────────────────────────── */
        $t_mod = $wpdb->prefix . NAWS_TABLE_MODULES;
        dbDelta( "CREATE TABLE IF NOT EXISTS {$t_mod} (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id     VARCHAR(32)  NOT NULL,
            station_id    VARCHAR(32)  NOT NULL,
            module_name   VARCHAR(100) NOT NULL DEFAULT '',
            module_type   VARCHAR(50)  NOT NULL DEFAULT '',
            data_types    TEXT         NOT NULL,
            last_seen     BIGINT       DEFAULT NULL,
            firmware      INT          DEFAULT NULL,
            battery_vp    INT          DEFAULT NULL,
            rf_status     INT          DEFAULT NULL,
            is_active     TINYINT(1)   NOT NULL DEFAULT 1,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_module_id (module_id),
            KEY idx_station  (station_id),
            KEY idx_active   (is_active)
        ) {$cc};" );

        // v1.4: Add lat/lng to modules
        if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t_mod} LIKE 'latitude'" ) ) {
            $wpdb->query( "ALTER TABLE {$t_mod} ADD COLUMN latitude  DOUBLE DEFAULT NULL" );
            $wpdb->query( "ALTER TABLE {$t_mod} ADD COLUMN longitude DOUBLE DEFAULT NULL" );
        }

        /* ── raw readings ────────────────────────────────────────── */
        $t_raw = $wpdb->prefix . NAWS_TABLE_READINGS;
        dbDelta( "CREATE TABLE IF NOT EXISTS {$t_raw} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id     VARCHAR(32)     NOT NULL,
            station_id    VARCHAR(32)     NOT NULL,
            recorded_at   BIGINT          NOT NULL,
            parameter     VARCHAR(50)     NOT NULL,
            value         DOUBLE          NOT NULL,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_reading        (module_id, recorded_at, parameter),
            KEY idx_module_time          (module_id, recorded_at),
            KEY idx_station_time         (station_id, recorded_at),
            KEY idx_parameter            (parameter),
            KEY idx_recorded_at          (recorded_at)
        ) {$cc};" );

        /* ── daily summary (permanent history) ───────────────────── */
        // All sensor columns from all supported module types (v1.4)
        $t_day = $wpdb->prefix . NAWS_TABLE_DAILY;
        dbDelta( "CREATE TABLE IF NOT EXISTS {$t_day} (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id           VARCHAR(32)     NOT NULL,
            station_id          VARCHAR(32)     NOT NULL,
            day_date            DATE            NOT NULL,
            temp_min            DOUBLE          DEFAULT NULL,
            temp_max            DOUBLE          DEFAULT NULL,
            temp_avg            DOUBLE          DEFAULT NULL,
            pressure_avg        DOUBLE          DEFAULT NULL,
            rain_sum            DOUBLE          DEFAULT NULL,
            humidity_avg        DOUBLE          DEFAULT NULL,
            indoor_temp_avg     DOUBLE          DEFAULT NULL,
            indoor_humidity_avg DOUBLE          DEFAULT NULL,
            co2_avg             DOUBLE          DEFAULT NULL,
            noise_avg           DOUBLE          DEFAULT NULL,
            wind_avg            DOUBLE          DEFAULT NULL,
            gust_max            DOUBLE          DEFAULT NULL,
            wind_angle          DOUBLE          DEFAULT NULL,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_module_day (module_id, day_date),
            KEY idx_day_date         (day_date),
            KEY idx_module_id        (module_id),
            KEY idx_station_day      (station_id, day_date)
        ) {$cc};" );

        /* ── run migrations on existing installs ─────────────────── */
        self::maybe_migrate();

        update_option( 'naws_db_version', NAWS_DB_VERSION );
        NAWS_Cron::schedule();
    }

    private static function maybe_migrate() {
        global $wpdb;

        $t_day = $wpdb->prefix . NAWS_TABLE_DAILY;
        if ( $wpdb->get_results( "SHOW TABLES LIKE '{$t_day}'" ) ) {

            // v1.3: Remove old incorrectly-named column wind_max (was replaced)
            // (humidity_avg, co2_avg, noise_avg were also dropped in v1.3 but are re-added in v1.4)
            if ( $wpdb->get_results( "SHOW COLUMNS FROM {$t_day} LIKE 'wind_max'" ) ) {
                $wpdb->query( "ALTER TABLE {$t_day} DROP COLUMN wind_max" );
            }

            // v1.4: Add new sensor columns for all module types
            $add_cols = [
                'humidity_avg'        => "DOUBLE DEFAULT NULL AFTER rain_sum",
                'indoor_temp_avg'     => "DOUBLE DEFAULT NULL AFTER humidity_avg",
                'indoor_humidity_avg' => "DOUBLE DEFAULT NULL AFTER indoor_temp_avg",
                'co2_avg'             => "DOUBLE DEFAULT NULL AFTER indoor_humidity_avg",
                'noise_avg'           => "DOUBLE DEFAULT NULL AFTER co2_avg",
                'wind_avg'            => "DOUBLE DEFAULT NULL AFTER noise_avg",
                'gust_max'            => "DOUBLE DEFAULT NULL AFTER wind_avg",
                'wind_angle'          => "DOUBLE DEFAULT NULL AFTER gust_max",
            ];
            foreach ( $add_cols as $col => $def ) {
                if ( ! $wpdb->get_results( "SHOW COLUMNS FROM {$t_day} LIKE '{$col}'" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
                    $wpdb->query( "ALTER TABLE {$t_day} ADD COLUMN {$col} {$def}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                }
            }
        }

        $t_mod = $wpdb->prefix . NAWS_TABLE_MODULES;

        // v1→v1.1: add is_active
        if ( ! $wpdb->get_results( "SHOW COLUMNS FROM {$t_mod} LIKE 'is_active'" ) ) {
            $wpdb->query( "ALTER TABLE {$t_mod} ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER rf_status" );
            $wpdb->query( "ALTER TABLE {$t_mod} ADD KEY idx_active (is_active)" );
        }
    }

    // ================================================================
    // Modules
    // ================================================================

    /**
     * Upsert module metadata.
     * IMPORTANT: uses INSERT ... ON DUPLICATE KEY UPDATE so that
     * is_active is NOT overwritten on subsequent syncs.
     *
     * @return true|WP_Error
     */
    public static function save_module( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . NAWS_TABLE_MODULES;

        $module_id   = sanitize_text_field( $data['_id'] );
        // Netatmo place.location = [longitude, latitude]
        $location  = $data['place']['location'] ?? null;
        $latitude  = isset( $location[1] ) ? floatval( $location[1] ) : null;
        $longitude = isset( $location[0] ) ? floatval( $location[0] ) : null;
        $station_id  = sanitize_text_field( $data['station_id'] );
        $module_name = sanitize_text_field( $data['module_name'] ?? $data['station_name'] ?? 'Unknown' );
        $module_type = sanitize_text_field( $data['type'] ?? 'NAMain' );
        $data_types  = wp_json_encode( $data['data_type'] ?? [] );
        $last_seen   = isset( $data['last_seen'] )  ? intval( $data['last_seen'] )  : null;
        $firmware    = isset( $data['firmware'] )   ? intval( $data['firmware'] )   : null;
        $battery_vp  = isset( $data['battery_vp'] ) ? intval( $data['battery_vp'] ) : null;
        $rf_status   = isset( $data['rf_status'] )  ? intval( $data['rf_status'] )  : null;

        // INSERT … ON DUPLICATE KEY: never touches is_active on update
        $result = $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table}
                (module_id, station_id, module_name, module_type, data_types, last_seen, firmware, battery_vp, rf_status, latitude, longitude, is_active)
             VALUES (%s, %s, %s, %s, %s, %d, %d, %d, %d, %f, %f, 1)
             ON DUPLICATE KEY UPDATE
                station_id  = VALUES(station_id),
                module_name = VALUES(module_name),
                module_type = VALUES(module_type),
                data_types  = VALUES(data_types),
                last_seen   = VALUES(last_seen),
                firmware    = VALUES(firmware),
                battery_vp  = VALUES(battery_vp),
                rf_status   = VALUES(rf_status),
                latitude    = COALESCE(VALUES(latitude), latitude),
                longitude   = COALESCE(VALUES(longitude), longitude)",
            $module_id, $station_id, $module_name, $module_type,
            $data_types, $last_seen, $firmware, $battery_vp, $rf_status, $latitude, $longitude
        ) );

        if ( $result === false ) {
            NAWS_Logger::error( 'database', 'save_module failed: ' . $wpdb->last_error, [
                'module_id' => $module_id,
            ] );
            return new WP_Error( 'db_error', 'Failed to save module: ' . $wpdb->last_error );
        }

        return true;
    }

    /**
     * @param bool $active_only  false = all (for admin UI), true = active only (for frontend)
     */
    public static function get_modules( $active_only = false ) {
        // Check transient cache first
        $cache_key = self::CACHE_PREFIX . 'modules_' . ( $active_only ? '1' : '0' );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . NAWS_TABLE_MODULES;
        $where = $active_only ? 'WHERE is_active = 1' : '';
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY is_active DESC, module_type, module_name",
            ARRAY_A
        );

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'database', 'get_modules query failed: ' . $wpdb->last_error );
            return [];
        }

        if ( ! is_array( $rows ) ) {
            $rows = [];
        }

        foreach ( $rows as &$row ) {
            $row['data_types'] = json_decode( $row['data_types'], true ) ?: [];
        }

        set_transient( $cache_key, $rows, self::TTL_MODULES );

        return $rows;
    }

    public static function set_module_active( $module_id, $is_active ) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . NAWS_TABLE_MODULES,
            [ 'is_active' => $is_active ? 1 : 0 ],
            [ 'module_id' => sanitize_text_field( $module_id ) ],
            [ '%d' ], [ '%s' ]
        );
    }

    public static function is_module_active( $module_id ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_active FROM {$wpdb->prefix}naws_modules WHERE module_id = %s",
            $module_id
        ) );
        // If not yet in DB → active by default (first sync inserts it)
        return $result === null ? true : (bool) intval( $result );
    }

    // ================================================================
    // Raw Readings (naws_readings)
    // ================================================================

    /**
     * Bulk insert, duplicates silently ignored.
     */
    /**
     * Sum all incremental Rain readings from NAModule3 over the last 24 rolling hours.
     * Netatmo's sum_rain_24 resets at midnight, so this gives the true rolling 24h value.
     */
    public static function get_rain_rolling_24h( $module_id ) {
        $cache_key = self::CACHE_PREFIX . 'rain24h_' . md5( $module_id );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached === 'null' ? null : floatval( $cached );
        }

        global $wpdb;
        $table    = $wpdb->prefix . NAWS_TABLE_READINGS;
        $since    = time() - DAY_IN_SECONDS;
        $result   = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(value) FROM {$table}
             WHERE module_id = %s AND parameter = 'Rain' AND recorded_at >= %d",
            $module_id,
            $since
        ) );

        $value = $result !== null ? round( floatval( $result ), 1 ) : null;
        set_transient( $cache_key, $value === null ? 'null' : $value, self::TTL_RAIN24 );

        return $value;
    }

    public static function bulk_insert_readings( $rows ) {
        global $wpdb;
        if ( empty( $rows ) ) return 0;

        $table        = $wpdb->prefix . NAWS_TABLE_READINGS;
        $placeholders = [];
        $values       = [];

        foreach ( $rows as $row ) {
            $placeholders[] = '(%s,%s,%d,%s,%f)';
            $values[]       = sanitize_text_field( $row['module_id'] );
            $values[]       = sanitize_text_field( $row['station_id'] );
            $values[]       = intval( $row['recorded_at'] );
            $values[]       = sanitize_text_field( $row['parameter'] );
            $values[]       = floatval( $row['value'] );
        }

        $sql = "INSERT IGNORE INTO {$table}
                    (module_id, station_id, recorded_at, parameter, value)
                VALUES " . implode( ',', $placeholders );

        $result = $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( $result === false ) {
            NAWS_Logger::error( 'database', 'bulk_insert_readings failed: ' . $wpdb->last_error, [
                'row_count' => count( $rows ),
            ] );
            return 0;
        }

        return $result;
    }

    /**
     * Flexible query – respects is_active automatically.
     *
     * group_by: 'raw' | 'hour' | 'day' | 'week' | 'month' | 'year'
     */
    public static function get_readings( $args = [] ) {
        global $wpdb;
        $r = $wpdb->prefix . NAWS_TABLE_READINGS;
        $m = $wpdb->prefix . NAWS_TABLE_MODULES;

        $args = wp_parse_args( $args, [
            'module_id'  => null,
            'parameter'  => null,
            'date_from'  => strtotime( '-7 days' ),
            'date_to'    => time(),
            'limit'      => 5000,
            'group_by'   => 'raw',
        ] );

        // ── Transient cache ──────────────────────────────────────────
        $cache_key = self::CACHE_PREFIX . 'readings_' . md5( wp_json_encode( $args ) );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) ) {
            return $cached;
        }

        // WHERE
        $where  = [
            "r.recorded_at BETWEEN %d AND %d",
            "EXISTS (SELECT 1 FROM {$m} mx WHERE mx.module_id = r.module_id AND mx.is_active = 1)",
        ];
        $params = [ intval( $args['date_from'] ), intval( $args['date_to'] ) ];

        if ( ! empty( $args['module_id'] ) ) {
            $ids = (array) $args['module_id'];
            $ph  = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
            $where[]  = "r.module_id IN ({$ph})";
            $params   = array_merge( $params, $ids );
        }
        if ( ! empty( $args['parameter'] ) ) {
            $ps = (array) $args['parameter'];
            $ph = implode( ',', array_fill( 0, count( $ps ), '%s' ) );
            $where[]  = "r.parameter IN ({$ph})";
            $params   = array_merge( $params, $ps );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );
        $limit_sql = $args['limit'] > 0 ? 'LIMIT ' . intval( $args['limit'] ) : '';

        // SELECT + GROUP
        $buckets = [
            'hour'  => 'FLOOR(r.recorded_at/3600)*3600',
            'day'   => 'FLOOR(r.recorded_at/86400)*86400',
            'week'  => 'FLOOR(r.recorded_at/604800)*604800',
            'month' => "UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME(r.recorded_at),'%%Y-%%m-01'))",
            'year'  => "UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME(r.recorded_at),'%%Y-01-01'))",
        ];

        $group_by = $args['group_by'];
        if ( isset( $buckets[ $group_by ] ) ) {
            $bkt    = $buckets[ $group_by ];
            $select = "r.module_id, r.parameter, {$bkt} AS recorded_at,
                        AVG(r.value) AS value,
                        MIN(r.value) AS min_value,
                        MAX(r.value) AS max_value,
                        COUNT(*)     AS data_points";
            $group  = "GROUP BY r.module_id, r.parameter, {$bkt}";
            $order  = "ORDER BY recorded_at ASC";
        } else {
            $select = "r.module_id, r.parameter, r.recorded_at, r.value";
            $group  = "";
            $order  = "ORDER BY r.recorded_at ASC";
        }

        $sql     = "SELECT {$select} FROM {$r} r {$where_sql} {$group} {$order} {$limit_sql}";
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'database', 'get_readings query failed: ' . $wpdb->last_error, [
                'group_by' => $args['group_by'],
            ] );
            return [];
        }

        $results = $results ?: [];
        set_transient( $cache_key, $results, self::TTL_READINGS );

        return $results;
    }

    /**
     * Latest value per module+parameter (current reading widget).
     * Only active modules.
     */
    public static function get_latest_readings( $module_id = null ) {
        $cache_key = self::CACHE_PREFIX . 'latest_' . ( $module_id ? md5( $module_id ) : 'all' );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;
        $r = $wpdb->prefix . NAWS_TABLE_READINGS;
        $m = $wpdb->prefix . NAWS_TABLE_MODULES;

        if ( $module_id ) {
            $sql = $wpdb->prepare(
                "SELECT r1.*
                 FROM {$r} r1
                 INNER JOIN (
                     SELECT parameter, MAX(recorded_at) AS max_ts
                     FROM {$r} WHERE module_id = %s GROUP BY parameter
                 ) r2 ON r1.parameter = r2.parameter AND r1.recorded_at = r2.max_ts
                 INNER JOIN {$m} m ON m.module_id = r1.module_id AND m.is_active = 1
                 WHERE r1.module_id = %s",
                $module_id, $module_id
            );
        } else {
            $sql = "SELECT r1.*
                    FROM {$r} r1
                    INNER JOIN (
                        SELECT module_id, parameter, MAX(recorded_at) AS max_ts
                        FROM {$r} GROUP BY module_id, parameter
                    ) r2 ON  r1.module_id  = r2.module_id
                         AND r1.parameter  = r2.parameter
                         AND r1.recorded_at = r2.max_ts
                    INNER JOIN {$m} m ON m.module_id = r1.module_id AND m.is_active = 1";
        }

        $results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query built from constants

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'database', 'get_latest_readings query failed: ' . $wpdb->last_error );
            return [];
        }

        $results = $results ?: [];
        set_transient( $cache_key, $results, self::TTL_LATEST );

        return $results;
    }

    // ================================================================
    // Daily Summary (naws_daily_summary)
    // ================================================================

    /**
     * Compute and store the daily summary for a given date.
     * Reads from naws_readings (raw data), writes to naws_daily_summary.
     *
     * Architecture (identical to NAWS_Importer):
     *   – One row per STATION per day (station_id used as module_id key)
     *   – Temperature sourced exclusively from NAModule1 (outdoor sensor)
     *   – Pressure sourced from NAMain / NAModule4 / NHC
     *   – Rain sourced from NAModule3
     * This prevents indoor base-station temperature from polluting the summary
     * and avoids writing multiple per-module rows that the charts cannot merge.
     *
     * @param string $date  'Y-m-d'  – defaults to yesterday
     */
    public static function compute_daily_summary( $date = null ) {
        global $wpdb;

        if ( ! $date ) {
            $date = wp_date( 'Y-m-d', strtotime( 'yesterday' ) );
        }

        // Use Europe/Berlin timezone so local day boundaries are correct
        $tz        = new DateTimeZone( 'Europe/Berlin' );
        $day_start = ( new DateTimeImmutable( $date . ' 00:00:00', $tz ) )->getTimestamp();
        $day_end   = ( new DateTimeImmutable( $date . ' 23:59:59', $tz ) )->getTimestamp();

        $r     = $wpdb->prefix . NAWS_TABLE_READINGS;
        $t_mod = $wpdb->prefix . NAWS_TABLE_MODULES;

        // Load all active modules and group them by station_id
        $all_modules = $wpdb->get_results(
            "SELECT module_id, station_id, module_type FROM {$t_mod} WHERE is_active = 1",
            ARRAY_A
        );

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'database', 'compute_daily_summary: failed to load modules: ' . $wpdb->last_error );
            return 0;
        }

        $stations = [];
        foreach ( $all_modules as $m ) {
            $stations[ $m['station_id'] ][] = $m;
        }

        $saved = 0;

        foreach ( $stations as $station_id => $station_modules ) {

            $cols = [];

            foreach ( $station_modules as $module ) {
                $mid   = $module['module_id'];
                $mtype = $module['module_type'];

                // Fetch all readings for this module on this day
                $readings = $wpdb->get_results( $wpdb->prepare(
                    "SELECT parameter, value FROM {$r}
                     WHERE module_id   = %s
                       AND recorded_at BETWEEN %d AND %d",
                    $mid, $day_start, $day_end
                ), ARRAY_A );

                if ( $wpdb->last_error ) {
                    NAWS_Logger::warning( 'database', 'compute_daily_summary: query failed for module ' . $mid . ': ' . $wpdb->last_error );
                    continue;
                }

                if ( empty( $readings ) ) continue;

                // Group values by parameter name
                $p = [];
                foreach ( $readings as $row ) {
                    $p[ $row['parameter'] ][] = floatval( $row['value'] );
                }

                // ── NAModule1: Outdoor temperature + humidity ──────────
                if ( $mtype === 'NAModule1' ) {
                    $temps = array_merge( $p['Temperature'] ?? [], $p['temperature'] ?? [] );
                    if ( ! empty( $temps ) ) {
                        $cols['temp_min'] = round(
                            isset( $p['min_temp'] ) ? min( $p['min_temp'] ) : min( $temps ), 1
                        );
                        $cols['temp_max'] = round(
                            isset( $p['max_temp'] ) ? max( $p['max_temp'] ) : max( $temps ), 1
                        );
                        $cols['temp_avg'] = round( array_sum( $temps ) / count( $temps ), 1 );
                    }
                    $humid = $p['Humidity'] ?? $p['humidity'] ?? [];
                    if ( ! empty( $humid ) ) {
                        $cols['humidity_avg'] = round( array_sum( $humid ) / count( $humid ), 1 );
                    }
                }

                // ── NAMain: Base station – pressure, CO₂, noise, humidity, indoor temp ─
                elseif ( in_array( $mtype, [ 'NAMain', 'NHC' ], true ) ) {
                    $press_raw = array_merge(
                        $p['Pressure']         ?? [],
                        $p['AbsolutePressure'] ?? []
                    );
                    $press = array_values( array_filter(
                        $press_raw,
                        fn( $v ) => $v > 850 && $v < 1100
                    ) );
                    if ( ! empty( $press ) ) {
                        $cols['pressure_avg'] = round( array_sum( $press ) / count( $press ), 1 );
                    }
                    // Indoor temperature from main station
                    $itemps = array_merge( $p['Temperature'] ?? [], $p['temperature'] ?? [] );
                    if ( ! empty( $itemps ) ) {
                        $cols['indoor_temp_avg'] = round( array_sum( $itemps ) / count( $itemps ), 1 );
                    }
                    $ihumid = $p['Humidity'] ?? $p['humidity'] ?? [];
                    if ( ! empty( $ihumid ) ) {
                        $cols['indoor_humidity_avg'] = round( array_sum( $ihumid ) / count( $ihumid ), 1 );
                    }
                    $co2 = $p['CO2'] ?? $p['co2'] ?? [];
                    if ( ! empty( $co2 ) ) {
                        $cols['co2_avg'] = round( array_sum( $co2 ) / count( $co2 ), 0 );
                    }
                    $noise = $p['Noise'] ?? $p['noise'] ?? [];
                    if ( ! empty( $noise ) ) {
                        $cols['noise_avg'] = round( array_sum( $noise ) / count( $noise ), 1 );
                    }
                }

                // ── NAModule4: Indoor extra module – temp, humidity, CO₂, noise ─
                elseif ( $mtype === 'NAModule4' ) {
                    $press_raw = array_merge(
                        $p['Pressure']         ?? [],
                        $p['AbsolutePressure'] ?? []
                    );
                    $press = array_values( array_filter(
                        $press_raw,
                        fn( $v ) => $v > 850 && $v < 1100
                    ) );
                    if ( ! empty( $press ) ) {
                        $cols['pressure_avg'] = round( array_sum( $press ) / count( $press ), 1 );
                    }
                    $itemps = array_merge( $p['Temperature'] ?? [], $p['temperature'] ?? [] );
                    if ( ! empty( $itemps ) ) {
                        $cols['indoor_temp_avg'] = round( array_sum( $itemps ) / count( $itemps ), 1 );
                    }
                    $ihumid = $p['Humidity'] ?? $p['humidity'] ?? [];
                    if ( ! empty( $ihumid ) ) {
                        $cols['indoor_humidity_avg'] = round( array_sum( $ihumid ) / count( $ihumid ), 1 );
                    }
                    $co2 = $p['CO2'] ?? $p['co2'] ?? [];
                    if ( ! empty( $co2 ) ) {
                        $cols['co2_avg'] = round( array_sum( $co2 ) / count( $co2 ), 0 );
                    }
                    $noise = $p['Noise'] ?? $p['noise'] ?? [];
                    if ( ! empty( $noise ) ) {
                        $cols['noise_avg'] = round( array_sum( $noise ) / count( $noise ), 1 );
                    }
                }

                // ── NAModule3: Rain gauge ────────────────────────────────
                elseif ( $mtype === 'NAModule3' ) {
                    if ( isset( $p['sum_rain_24'] ) ) {
                        $cols['rain_sum'] = round( max( $p['sum_rain_24'] ), 2 );
                    } elseif ( isset( $p['sum_rain_1'] ) ) {
                        $cols['rain_sum'] = round( array_sum( $p['sum_rain_1'] ), 2 );
                    } elseif ( isset( $p['Rain'] ) ) {
                        $cols['rain_sum'] = round( array_sum( $p['Rain'] ), 2 );
                    }
                }

                // ── NAModule2: Wind gauge ────────────────────────────────
                elseif ( $mtype === 'NAModule2' ) {
                    $wind = $p['WindStrength'] ?? $p['windstrength'] ?? [];
                    if ( ! empty( $wind ) ) {
                        $cols['wind_avg'] = round( array_sum( $wind ) / count( $wind ), 1 );
                    }
                    $gust = $p['GustStrength'] ?? $p['guststrength'] ?? [];
                    if ( ! empty( $gust ) ) {
                        $cols['gust_max'] = round( max( $gust ), 1 );
                    }
                    // Use max_wind_str if available (more precise peak gust)
                    if ( isset( $p['max_wind_str'] ) ) {
                        $cols['gust_max'] = round( max( $p['max_wind_str'] ), 1 );
                    }
                    // Circular mean of wind angles
                    $angles = $p['WindAngle'] ?? $p['windangle'] ?? [];
                    if ( ! empty( $angles ) ) {
                        $sin_sum = array_sum( array_map( fn( $a ) => sin( deg2rad( $a ) ), $angles ) );
                        $cos_sum = array_sum( array_map( fn( $a ) => cos( deg2rad( $a ) ), $angles ) );
                        $cols['wind_angle'] = round( fmod( rad2deg( atan2( $sin_sum, $cos_sum ) ) + 360, 360 ), 0 );
                    }
                }
            }

            if ( empty( $cols ) ) continue;

            self::upsert_daily_summary( $station_id, $date, $cols );
            $saved++;
        }

        return $saved;
    }

    /**
     * Upsert a daily summary row for a station.
     *
     * Only the columns present in $cols are written; missing columns are never
     * set to zero – they stay NULL or keep their existing value (COALESCE).
     * Uses %f for all numeric values so PHP nulls cannot slip through as zeros.
     *
     * @param string $station_id  Used as both module_id and station_id (importer convention)
     * @param string $day_date    'Y-m-d'
     * @param array  $cols        Associative array of column → value (only non-null values)
     */
    private static function upsert_daily_summary( $station_id, $day_date, $cols ) {
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
        // %f ensures all numeric values are properly cast – never inserts '' or null as 0
        $val_ph   = implode( ', ', array_fill( 0, count( $cols ), '%f' ) );

        $on_dup = implode( ', ', array_map(
            fn( $k ) => "{$k} = COALESCE(VALUES({$k}), {$k})",
            array_keys( $cols )
        ) );

        $params = array_merge(
            [ $station_id, $station_id, $day_date, $day_date . ' 00:00:00' ],
            array_values( $cols )
        );

        $result = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "INSERT INTO {$table}
                (module_id, station_id, day_date, created_at, {$col_list})
             VALUES (%s, %s, %s, %s, {$val_ph})
             ON DUPLICATE KEY UPDATE {$on_dup}, updated_at = NOW()",
            $params
        ) );

        if ( $result === false ) {
            NAWS_Logger::error( 'database', 'upsert_daily_summary failed: ' . $wpdb->last_error, [
                'station_id' => $station_id,
                'day_date'   => $day_date,
            ] );
        }
    }


    public static function get_daily_summaries( $args = [] ) {
        global $wpdb;
        $t   = $wpdb->prefix . NAWS_TABLE_DAILY;
        $m   = $wpdb->prefix . NAWS_TABLE_MODULES;

        $args = wp_parse_args( $args, [
            'module_id'  => null,
            'date_from'  => wp_date( 'Y-m-d', strtotime( '-365 days' ) ),
            'date_to'    => wp_date( 'Y-m-d' ),
            'fields'     => [ 'temp_min','temp_max','temp_avg','pressure_avg','rain_sum' ],
            'group_by'   => 'day',
            'limit'      => 0,
        ] );

        // ── Transient cache ──────────────────────────────────────────
        $cache_key = self::CACHE_PREFIX . 'daily_' . md5( wp_json_encode( $args ) );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) ) {
            return $cached;
        }

        // Only select requested fields + join with active modules
        $allowed_fields = [ 'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum' ];
        $fields = array_intersect( (array)$args['fields'], $allowed_fields );
        if ( empty( $fields ) ) $fields = $allowed_fields;

        $field_sql = implode( ', ', array_map( function($f) { return "d.{$f}"; }, $fields ) );

        // WHERE
        $where  = [
            "d.day_date BETWEEN %s AND %s",
            "EXISTS (SELECT 1 FROM {$m} mx WHERE mx.module_id = d.module_id AND mx.is_active = 1)",
        ];
        $params = [ $args['date_from'], $args['date_to'] ];

        if ( ! empty( $args['module_id'] ) ) {
            $ids = (array) $args['module_id'];
            $ph  = implode( ',', array_fill( 0, count($ids), '%s' ) );
            $where[]  = "d.module_id IN ({$ph})";
            $params   = array_merge( $params, $ids );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );
        $limit_sql = $args['limit'] > 0 ? 'LIMIT ' . intval( $args['limit'] ) : '';

        // Further grouping (for multi-year comparison)
        $group_by = $args['group_by'];
        if ( $group_by === 'week' ) {
            $date_expr = "DATE_FORMAT(d.day_date, '%%Y-%%u')";
            $date_sel  = "MIN(d.day_date) AS day_date";
        } elseif ( $group_by === 'month' ) {
            $date_expr = "DATE_FORMAT(d.day_date, '%%Y-%%m')";
            $date_sel  = "DATE_FORMAT(MIN(d.day_date), '%%Y-%%m-01') AS day_date";
        } elseif ( $group_by === 'year' ) {
            $date_expr = "YEAR(d.day_date)";
            $date_sel  = "DATE_FORMAT(MIN(d.day_date), '%%Y-01-01') AS day_date";
        } else {
            // day (default – no grouping)
            $sql     = "SELECT d.module_id, d.station_id, d.day_date, {$field_sql}
                        FROM {$t} d {$where_sql} ORDER BY d.day_date ASC {$limit_sql}";
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ( $wpdb->last_error ) {
                NAWS_Logger::error( 'database', 'get_daily_summaries query failed: ' . $wpdb->last_error );
                return [];
            }

            $results = $results ?: [];
            set_transient( $cache_key, $results, self::TTL_DAILY );
            return $results;
        }

        // Aggregated fields
        $agg_parts = [];
        foreach ( $fields as $f ) {
            if      ( $f === 'temp_min' )     $agg_parts[] = "MIN(d.temp_min)  AS temp_min";
            elseif  ( $f === 'temp_max' )     $agg_parts[] = "MAX(d.temp_max)  AS temp_max";
            elseif  ( $f === 'rain_sum' )     $agg_parts[] = "SUM(d.rain_sum)  AS rain_sum";
            else                              $agg_parts[] = "AVG(d.{$f})      AS {$f}";
        }

        $agg_sql = implode( ', ', $agg_parts );
        $sql = "SELECT d.module_id, d.station_id, {$date_sel}, {$agg_sql}
                FROM {$t} d {$where_sql}
                GROUP BY d.module_id, {$date_expr}
                ORDER BY day_date ASC {$limit_sql}";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'database', 'get_daily_summaries aggregated query failed: ' . $wpdb->last_error, [
                'group_by' => $group_by,
            ] );
            return [];
        }

        $results = $results ?: [];
        set_transient( $cache_key, $results, self::TTL_DAILY );

        return $results;
    }

    /**
     * Convenience: get daily summaries in Chart.js-ready format.
     * Returns [ 'datasets' => [...], 'count' => n ]
     */
    public static function get_daily_chart_data( $args = [] ) {
        $rows = self::get_daily_summaries( $args );
        if ( empty( $rows ) ) return [ 'datasets' => [], 'count' => 0 ];

        $modules = self::get_modules( false );
        $mod_map = array_column( $modules, 'module_name', 'module_id' );

        $field_labels = [
            'temp_min'     => naws__( 'param_temp_min' ),
            'temp_max'     => naws__( 'param_temp_max' ),
            'temp_avg'     => naws__( 'param_temp_avg' ),
            'pressure_avg' => naws__( 'param_pressure_avg' ),
            'rain_sum'     => naws__( 'param_rain_24h' ),
        ];

        $colors = [
            'temp_min'     => '#3b82f6',
            'temp_max'     => '#ef4444',
            'temp_avg'     => '#00d4ff',
            'pressure_avg' => '#8b5cf6',
            'rain_sum'     => '#06b6d4',
        ];

        $fields  = $args['fields'] ?? [ 'temp_min','temp_max','temp_avg' ];
        $by_key  = [];

        foreach ( $rows as $row ) {
            foreach ( $fields as $field ) {
                if ( ! isset( $row[$field] ) || $row[$field] === null ) continue;
                $key = $row['module_id'] . '||' . $field;
                $by_key[$key][] = [
                    'x' => $row['day_date'],          // ISO date string
                    'y' => round( floatval( $row[$field] ), 2 ),
                ];
            }
        }

        $datasets = [];
        $i = 0;
        foreach ( $by_key as $key => $points ) {
            [ $mid, $field ] = explode( '||', $key, 2 );
            $mod_name = $mod_map[$mid] ?? $mid;
            $label    = count($by_key) > count($fields)
                ? $mod_name . ' – ' . ( $field_labels[$field] ?? $field )
                : ( $field_labels[$field] ?? $field );

            $color      = $colors[$field] ?? ( '#' . substr( md5($field), 0, 6 ) );
            $datasets[] = [
                'label'           => $label,
                'data'            => $points,
                'borderColor'     => $color,
                'backgroundColor' => $color . '33',
                'borderWidth'     => 2,
                'pointRadius'     => count($points) > 100 ? 0 : 3,
                'tension'         => 0.4,
                'fill'            => false,
            ];
            $i++;
        }

        return [ 'datasets' => $datasets, 'count' => count($rows) ];
    }

    // ================================================================
    // Utility
    // ================================================================

    public static function get_data_range( $module_id = null ) {
        global $wpdb;
        $r     = $wpdb->prefix . NAWS_TABLE_READINGS;
        $where = $module_id ? $wpdb->prepare( 'WHERE module_id = %s', $module_id ) : '';
        return $wpdb->get_row( "SELECT MIN(recorded_at) AS date_begin, MAX(recorded_at) AS date_end FROM {$r} {$where}", ARRAY_A );
    }

    public static function get_daily_data_range( $module_id = null ) {
        global $wpdb;
        $t     = $wpdb->prefix . NAWS_TABLE_DAILY;
        $where = $module_id ? $wpdb->prepare( 'WHERE module_id = %s', $module_id ) : '';
        return $wpdb->get_row( "SELECT MIN(day_date) AS date_begin, MAX(day_date) AS date_end FROM {$t} {$where}", ARRAY_A );
    }

    public static function count_readings() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}" . NAWS_TABLE_READINGS ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a constant
    }

    public static function count_daily_summaries() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}" . NAWS_TABLE_DAILY ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a constant
    }

    public static function purge_old_readings( $days ) {
        global $wpdb;
        $table     = $wpdb->prefix . NAWS_TABLE_READINGS;
        $threshold = time() - ( intval($days) * DAY_IN_SECONDS );
        return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE recorded_at < %d", $threshold ) );
    }

    public static function get_table_sizes_mb() {
        global $wpdb;
        $tables = [
            NAWS_TABLE_READINGS => $wpdb->prefix . NAWS_TABLE_READINGS,
            NAWS_TABLE_DAILY    => $wpdb->prefix . NAWS_TABLE_DAILY,
        ];
        $sizes = [];
        foreach ( $tables as $key => $full_name ) {
            $sizes[$key] = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2)
                 FROM information_schema.TABLES
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME, $full_name
            ) );
        }
        return $sizes;
    }

    // ================================================================
    // Cache Management
    // ================================================================

    /**
     * Flush all NAWS transient caches.
     *
     * Called after successful data sync and module toggle to ensure
     * fresh data is served on the next request.
     */
    public static function flush_caches() {
        global $wpdb;

        // Delete all transients with our prefix (pattern-based)
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . self::CACHE_PREFIX . '%',
            '_transient_timeout_' . self::CACHE_PREFIX . '%'
        ) );

        // Also clear the WordPress object cache group
        wp_cache_flush_group( 'naws' );

        NAWS_Logger::info( 'cache', 'All NAWS caches flushed.' );
    }

    /**
     * Flush only module-related caches.
     */
    public static function flush_module_caches() {
        delete_transient( self::CACHE_PREFIX . 'modules_0' );
        delete_transient( self::CACHE_PREFIX . 'modules_1' );
    }
}
