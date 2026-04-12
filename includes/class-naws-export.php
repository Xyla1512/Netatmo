<?php
/**
 * Export / Import handler for NAWS.
 *
 * Supports two export modes:
 *  1. Weather data only  – naws_daily_summary rows
 *  2. Full backup        – daily_summary + modules + settings + display prefs
 *
 * Sensitive data (API tokens, keys) is NEVER included in exports.
 *
 * @package NAWS
 * @since   1.3.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Export {

    /** Format version for forward compatibility. */
    const EXPORT_VERSION = '1.0';

    /** Max rows per DB query batch during export. */
    const BATCH_SIZE = 5000;

    /** Max rows per import chunk (AJAX). */
    const IMPORT_BATCH = 500;

    /** Options that must NEVER be exported. */
    const SENSITIVE_OPTIONS = [
        'naws_access_token',
        'naws_refresh_token',
        'naws_token_expiry',
        'naws_rest_api_key',
        'naws_oauth_state',
        'naws_oauth_state_time',
        'naws_oauth_debug',
    ];

    /** Columns to include from naws_daily_summary (excluding auto-generated). */
    const DAILY_COLUMNS = [
        'module_id', 'station_id', 'day_date',
        'temp_min', 'temp_max', 'temp_avg',
        'pressure_avg', 'rain_sum', 'humidity_avg',
        'indoor_temp_avg', 'indoor_humidity_avg',
        'co2_avg', 'noise_avg',
        'wind_avg', 'gust_max', 'wind_angle',
    ];

    /** Columns to include from naws_modules (excluding auto-generated). */
    const MODULE_COLUMNS = [
        'module_id', 'station_id', 'module_name', 'module_type',
        'data_types', 'last_seen', 'firmware', 'battery_vp',
        'rf_status', 'is_active', 'latitude', 'longitude',
    ];

    // ================================================================
    // Export: Weather Data Only
    // ================================================================

    /**
     * Stream weather data export as JSON download.
     * Uses php://output for memory efficiency on large datasets.
     */
    public static function export_weather_data() {
        global $wpdb;

        $table = $wpdb->prefix . NAWS_TABLE_DAILY;
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $filename = 'naws-weather-data-' . gmdate( 'Y-m-d' ) . '.json';
        self::send_download_headers( $filename );

        $out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        // Write opening JSON + meta
        $meta = self::build_meta( 'weather_data', $total );
        fwrite( $out, '{"meta":' . wp_json_encode( $meta ) . ',"daily_summary":[' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

        // Stream rows in batches
        $offset    = 0;
        $first_row = true;
        $cols      = implode( ', ', self::DAILY_COLUMNS );

        while ( $offset < $total ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT {$cols} FROM {$table} ORDER BY day_date ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    self::BATCH_SIZE,
                    $offset
                ),
                ARRAY_A
            );

            if ( empty( $rows ) ) break;

            foreach ( $rows as $row ) {
                $row = self::cast_daily_row( $row );
                fwrite( $out, ( $first_row ? '' : ',' ) . wp_json_encode( $row ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
                $first_row = false;
            }

            $offset += self::BATCH_SIZE;
        }

        fwrite( $out, ']}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    // ================================================================
    // Export: Full Backup
    // ================================================================

    /**
     * Stream full backup export as JSON download.
     * Includes modules, settings, display prefs and daily_summary.
     */
    public static function export_full_backup() {
        global $wpdb;

        $table_daily   = $wpdb->prefix . NAWS_TABLE_DAILY;
        $table_modules = $wpdb->prefix . NAWS_TABLE_MODULES;

        $total_daily = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_daily}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $filename = 'naws-full-backup-' . gmdate( 'Y-m-d' ) . '.json';
        self::send_download_headers( $filename );

        $out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        // Meta
        $meta = self::build_meta( 'full_backup', $total_daily );
        fwrite( $out, '{"meta":' . wp_json_encode( $meta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

        // Modules (small dataset, safe to load all)
        $mod_cols = implode( ', ', self::MODULE_COLUMNS );
        $modules  = $wpdb->get_results( "SELECT {$mod_cols} FROM {$table_modules} ORDER BY module_id", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        fwrite( $out, ',"modules":' . wp_json_encode( $modules ?: [] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

        // Settings (strip sensitive data)
        $settings = self::get_exportable_settings();
        fwrite( $out, ',"settings":' . wp_json_encode( $settings ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

        // Display settings
        $display = self::get_display_settings();
        fwrite( $out, ',"display_settings":' . wp_json_encode( $display ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

        // Daily summary (streamed)
        fwrite( $out, ',"daily_summary":[' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

        $offset    = 0;
        $first_row = true;
        $cols      = implode( ', ', self::DAILY_COLUMNS );

        while ( $offset < $total_daily ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT {$cols} FROM {$table_daily} ORDER BY day_date ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    self::BATCH_SIZE,
                    $offset
                ),
                ARRAY_A
            );

            if ( empty( $rows ) ) break;

            foreach ( $rows as $row ) {
                $row = self::cast_daily_row( $row );
                fwrite( $out, ( $first_row ? '' : ',' ) . wp_json_encode( $row ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
                $first_row = false;
            }

            $offset += self::BATCH_SIZE;
        }

        fwrite( $out, ']}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    // ================================================================
    // Validate Import File
    // ================================================================

    /**
     * Validate an uploaded JSON file before importing.
     *
     * @param  string $file_path Absolute path to the uploaded file.
     * @return array  { valid: bool, meta: array|null, error: string|null }
     */
    public static function validate_import_file( $file_path ) {
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return [ 'valid' => false, 'meta' => null, 'error' => 'File not found or not readable.' ];
        }

        $size = filesize( $file_path );
        if ( $size > 100 * MB_IN_BYTES ) {
            return [ 'valid' => false, 'meta' => null, 'error' => 'File exceeds 100 MB limit.' ];
        }

        // Read entire file (practical limit: daily data rarely exceeds 10 MB)
        $contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $contents ) {
            return [ 'valid' => false, 'meta' => null, 'error' => 'Could not read file.' ];
        }

        $data = json_decode( $contents, true );
        if ( null === $data || ! is_array( $data ) ) {
            return [ 'valid' => false, 'meta' => null, 'error' => 'Invalid JSON format.' ];
        }

        // Check meta block
        if ( empty( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
            return [ 'valid' => false, 'meta' => null, 'error' => 'Missing meta block. This does not appear to be a NAWS export file.' ];
        }

        $meta = $data['meta'];

        if ( empty( $meta['export_type'] ) || ! in_array( $meta['export_type'], [ 'weather_data', 'full_backup' ], true ) ) {
            return [ 'valid' => false, 'meta' => $meta, 'error' => 'Unknown export type.' ];
        }

        if ( empty( $meta['export_version'] ) ) {
            return [ 'valid' => false, 'meta' => $meta, 'error' => 'Missing export version.' ];
        }

        // Check that daily_summary exists
        if ( ! isset( $data['daily_summary'] ) || ! is_array( $data['daily_summary'] ) ) {
            return [ 'valid' => false, 'meta' => $meta, 'error' => 'Missing daily_summary data in file.' ];
        }

        return [ 'valid' => true, 'meta' => $meta, 'error' => null ];
    }

    // ================================================================
    // Import: Chunked Weather Data
    // ================================================================

    /**
     * Import daily_summary rows from a JSON file in chunks.
     *
     * @param  string $file_path  Path to validated JSON file.
     * @param  int    $offset     Row offset to start from.
     * @param  int    $batch_size Rows per chunk.
     * @return array  { imported: int, skipped: int, total: int, offset: int, done: bool }
     */
    public static function import_weather_data( $file_path, $offset = 0, $batch_size = 500 ) {
        global $wpdb;

        $contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data     = json_decode( $contents, true );

        if ( ! $data || empty( $data['daily_summary'] ) ) {
            return [ 'imported' => 0, 'skipped' => 0, 'total' => 0, 'offset' => 0, 'done' => true, 'error' => 'No data found.' ];
        }

        $all_rows  = $data['daily_summary'];
        $total     = count( $all_rows );
        $batch     = array_slice( $all_rows, $offset, $batch_size );
        $imported  = 0;
        $skipped   = 0;
        $table     = $wpdb->prefix . NAWS_TABLE_DAILY;

        foreach ( $batch as $row ) {
            $clean = self::sanitize_daily_row( $row );
            if ( null === $clean ) {
                $skipped++;
                continue;
            }

            // Upsert: INSERT ... ON DUPLICATE KEY UPDATE
            $result = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant
                "INSERT INTO {$table}
                    (module_id, station_id, day_date, temp_min, temp_max, temp_avg,
                     pressure_avg, rain_sum, humidity_avg, indoor_temp_avg,
                     indoor_humidity_avg, co2_avg, noise_avg, wind_avg, gust_max, wind_angle)
                 VALUES (%s, %s, %s, %f, %f, %f, %f, %f, %f, %f, %f, %f, %f, %f, %f, %f)
                 ON DUPLICATE KEY UPDATE
                    temp_min = VALUES(temp_min), temp_max = VALUES(temp_max),
                    temp_avg = VALUES(temp_avg), pressure_avg = VALUES(pressure_avg),
                    rain_sum = VALUES(rain_sum), humidity_avg = VALUES(humidity_avg),
                    indoor_temp_avg = VALUES(indoor_temp_avg),
                    indoor_humidity_avg = VALUES(indoor_humidity_avg),
                    co2_avg = VALUES(co2_avg), noise_avg = VALUES(noise_avg),
                    wind_avg = VALUES(wind_avg), gust_max = VALUES(gust_max),
                    wind_angle = VALUES(wind_angle)",
                $clean['module_id'],
                $clean['station_id'],
                $clean['day_date'],
                $clean['temp_min'],
                $clean['temp_max'],
                $clean['temp_avg'],
                $clean['pressure_avg'],
                $clean['rain_sum'],
                $clean['humidity_avg'],
                $clean['indoor_temp_avg'],
                $clean['indoor_humidity_avg'],
                $clean['co2_avg'],
                $clean['noise_avg'],
                $clean['wind_avg'],
                $clean['gust_max'],
                $clean['wind_angle'],
            ) );

            if ( false === $result ) {
                $skipped++;
                NAWS_Logger::error( 'import', 'Failed to import row', [ 'date' => $clean['day_date'], 'error' => $wpdb->last_error ] );
            } else {
                $imported++;
            }
        }

        $new_offset = $offset + $batch_size;
        $done       = $new_offset >= $total;

        // Flush caches after last batch
        if ( $done ) {
            self::flush_caches();
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'total'    => $total,
            'offset'   => $new_offset,
            'done'     => $done,
        ];
    }

    // ================================================================
    // Import: Full Backup (modules + settings, called once)
    // ================================================================

    /**
     * Import modules and settings from a full backup.
     * Daily summary data is handled separately via import_weather_data().
     *
     * @param  string $file_path         Path to validated JSON file.
     * @param  bool   $overwrite_settings Whether to overwrite existing settings.
     * @return array  { modules_imported: int, settings_imported: bool, error: string|null }
     */
    public static function import_full_backup_meta( $file_path, $overwrite_settings = false ) {
        global $wpdb;

        $contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data     = json_decode( $contents, true );

        if ( ! $data ) {
            return [ 'modules_imported' => 0, 'settings_imported' => false, 'error' => 'Could not parse file.' ];
        }

        $modules_imported = 0;

        // ── Import modules ──────────────────────────────────────────
        if ( ! empty( $data['modules'] ) && is_array( $data['modules'] ) ) {
            $table = $wpdb->prefix . NAWS_TABLE_MODULES;

            foreach ( $data['modules'] as $mod ) {
                $clean = self::sanitize_module_row( $mod );
                if ( null === $clean ) continue;

                $result = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant
                    "INSERT INTO {$table}
                        (module_id, station_id, module_name, module_type, data_types,
                         last_seen, firmware, battery_vp, rf_status, is_active, latitude, longitude)
                     VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %f)
                     ON DUPLICATE KEY UPDATE
                        station_id = VALUES(station_id), module_name = VALUES(module_name),
                        module_type = VALUES(module_type), data_types = VALUES(data_types),
                        last_seen = VALUES(last_seen), firmware = VALUES(firmware),
                        battery_vp = VALUES(battery_vp), rf_status = VALUES(rf_status),
                        is_active = VALUES(is_active), latitude = VALUES(latitude),
                        longitude = VALUES(longitude)",
                    $clean['module_id'],
                    $clean['station_id'],
                    $clean['module_name'],
                    $clean['module_type'],
                    $clean['data_types'],
                    $clean['last_seen'],
                    $clean['firmware'],
                    $clean['battery_vp'],
                    $clean['rf_status'],
                    $clean['is_active'],
                    $clean['latitude'],
                    $clean['longitude'],
                ) );

                if ( false !== $result ) {
                    $modules_imported++;
                }
            }
        }

        // ── Import settings ─────────────────────────────────────────
        $settings_imported = false;

        if ( $overwrite_settings ) {
            // Plugin settings
            if ( ! empty( $data['settings']['naws_settings'] ) && is_array( $data['settings']['naws_settings'] ) ) {
                // Merge with existing to preserve client_id/secret + tokens
                $existing = get_option( 'naws_settings', [] );
                $imported = $data['settings']['naws_settings'];

                // Preserve local credentials (user must re-auth on new instance anyway)
                $preserve_keys = [ 'client_id', 'client_secret' ];
                foreach ( $preserve_keys as $key ) {
                    if ( ! empty( $existing[ $key ] ) && empty( $imported[ $key ] ) ) {
                        $imported[ $key ] = $existing[ $key ];
                    }
                }

                // Sanitize through the admin handler
                $sanitized = NAWS_Admin::instance()->sanitize_settings( $imported );
                update_option( 'naws_settings', $sanitized );
                $settings_imported = true;
            }

            // REST API settings (not the key itself)
            if ( isset( $data['settings']['naws_rest_enabled'] ) ) {
                update_option( 'naws_rest_enabled', (int) $data['settings']['naws_rest_enabled'] );
            }
            if ( isset( $data['settings']['naws_rest_rate_limit'] ) ) {
                update_option( 'naws_rest_rate_limit', max( 1, (int) $data['settings']['naws_rest_rate_limit'] ) );
            }

            // Display settings
            if ( ! empty( $data['display_settings'] ) && is_array( $data['display_settings'] ) ) {
                $display_keys = [
                    'naws_live_hidden_params',
                    'naws_live_hidden_modules',
                    'naws_live_hidden_charts',
                    'naws_history_hidden_charts',
                ];
                foreach ( $display_keys as $key ) {
                    if ( isset( $data['display_settings'][ $key ] ) && is_array( $data['display_settings'][ $key ] ) ) {
                        $clean_vals = array_map( 'sanitize_text_field', $data['display_settings'][ $key ] );
                        update_option( $key, $clean_vals );
                    }
                }
                // Appearance colors
                $appearance_key = NAWS_Colors::OPTION_KEY;
                if ( isset( $data['display_settings'][ $appearance_key ] ) && is_array( $data['display_settings'][ $appearance_key ] ) ) {
                    update_option( $appearance_key, NAWS_Colors::sanitize( $data['display_settings'][ $appearance_key ] ) );
                    NAWS_Colors::flush_cache();
                }
            }
        }

        // Flush caches
        self::flush_caches();

        return [
            'modules_imported'  => $modules_imported,
            'settings_imported' => $settings_imported,
            'error'             => null,
        ];
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Build meta block for export JSON.
     */
    private static function build_meta( $export_type, $row_count ) {
        return [
            'plugin_version' => NAWS_VERSION,
            'export_version' => self::EXPORT_VERSION,
            'export_date'    => wp_date( 'c' ),
            'export_type'    => $export_type,
            'site_url'       => get_site_url(),
            'row_count'      => $row_count,
        ];
    }

    /**
     * Send HTTP headers for a JSON file download.
     */
    private static function send_download_headers( $filename ) {
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'X-Robots-Tag: noindex, nofollow' );
    }

    /**
     * Cast numeric values in a daily_summary row to proper types.
     */
    private static function cast_daily_row( $row ) {
        $numeric = [
            'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum',
            'humidity_avg', 'indoor_temp_avg', 'indoor_humidity_avg',
            'co2_avg', 'noise_avg', 'wind_avg', 'gust_max', 'wind_angle',
        ];
        foreach ( $numeric as $col ) {
            if ( isset( $row[ $col ] ) && '' !== $row[ $col ] ) {
                $row[ $col ] = round( (float) $row[ $col ], 4 );
            } else {
                $row[ $col ] = null;
            }
        }
        return $row;
    }

    /**
     * Sanitize and validate one daily_summary row from import data.
     *
     * @param  array $row Raw row from JSON.
     * @return array|null Cleaned row or null if invalid.
     */
    private static function sanitize_daily_row( $row ) {
        if ( ! is_array( $row ) ) return null;

        // Required fields
        if ( empty( $row['module_id'] ) || empty( $row['station_id'] ) || empty( $row['day_date'] ) ) {
            return null;
        }

        // Validate date format YYYY-MM-DD
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row['day_date'] ) ) {
            return null;
        }

        $clean = [
            'module_id'  => sanitize_text_field( $row['module_id'] ),
            'station_id' => sanitize_text_field( $row['station_id'] ),
            'day_date'   => sanitize_text_field( $row['day_date'] ),
        ];

        // Numeric columns: cast to float or 0 if null/missing
        $numeric = [
            'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum',
            'humidity_avg', 'indoor_temp_avg', 'indoor_humidity_avg',
            'co2_avg', 'noise_avg', 'wind_avg', 'gust_max', 'wind_angle',
        ];
        foreach ( $numeric as $col ) {
            $clean[ $col ] = isset( $row[ $col ] ) && '' !== $row[ $col ] && null !== $row[ $col ]
                ? (float) $row[ $col ]
                : 0;
        }

        return $clean;
    }

    /**
     * Sanitize and validate one module row from import data.
     *
     * @param  array $row Raw row from JSON.
     * @return array|null Cleaned row or null if invalid.
     */
    private static function sanitize_module_row( $row ) {
        if ( ! is_array( $row ) ) return null;

        if ( empty( $row['module_id'] ) || empty( $row['station_id'] ) ) {
            return null;
        }

        return [
            'module_id'   => sanitize_text_field( $row['module_id'] ),
            'station_id'  => sanitize_text_field( $row['station_id'] ),
            'module_name' => sanitize_text_field( $row['module_name'] ?? '' ),
            'module_type' => sanitize_text_field( $row['module_type'] ?? '' ),
            'data_types'  => sanitize_text_field( $row['data_types'] ?? '' ),
            'last_seen'   => isset( $row['last_seen'] ) ? intval( $row['last_seen'] ) : null,
            'firmware'    => isset( $row['firmware'] )   ? intval( $row['firmware'] )  : null,
            'battery_vp'  => isset( $row['battery_vp'] ) ? intval( $row['battery_vp'] ) : null,
            'rf_status'   => isset( $row['rf_status'] )  ? intval( $row['rf_status'] ) : null,
            'is_active'   => isset( $row['is_active'] )  ? intval( $row['is_active'] ) : 1,
            'latitude'    => isset( $row['latitude'] )   ? (float) $row['latitude']  : 0,
            'longitude'   => isset( $row['longitude'] )  ? (float) $row['longitude'] : 0,
        ];
    }

    /**
     * Get plugin settings for export (sensitive data stripped).
     */
    private static function get_exportable_settings() {
        $settings = get_option( 'naws_settings', [] );

        // Remove sensitive keys from settings array (client credentials are kept)
        $strip_from_settings = [ 'forecast_auto_name' ];
        foreach ( $strip_from_settings as $key ) {
            unset( $settings[ $key ] );
        }

        return [
            'naws_settings'        => $settings,
            'naws_rest_enabled'    => get_option( 'naws_rest_enabled', 0 ),
            'naws_rest_rate_limit' => get_option( 'naws_rest_rate_limit', 60 ),
        ];
    }

    /**
     * Get display settings for export.
     */
    private static function get_display_settings() {
        return [
            'naws_live_hidden_params'    => get_option( 'naws_live_hidden_params', [] ),
            'naws_live_hidden_modules'   => get_option( 'naws_live_hidden_modules', [] ),
            'naws_live_hidden_charts'    => get_option( 'naws_live_hidden_charts', [] ),
            'naws_history_hidden_charts' => get_option( 'naws_history_hidden_charts', [] ),
            NAWS_Colors::OPTION_KEY      => get_option( NAWS_Colors::OPTION_KEY, [] ),
        ];
    }

    /**
     * Flush all NAWS transient caches after import.
     */
    private static function flush_caches() {
        // Flush module caches if method exists
        if ( method_exists( 'NAWS_Database', 'flush_module_caches' ) ) {
            NAWS_Database::flush_module_caches();
        }

        // Delete all known NAWS transients
        global $wpdb;
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_naws_cache_%',
                '_transient_timeout_naws_cache_%'
            )
        );
    }
}
