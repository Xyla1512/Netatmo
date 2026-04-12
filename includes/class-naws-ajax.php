<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Ajax {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin-only AJAX
        add_action( 'wp_ajax_naws_sync_now',           [ $this, 'sync_now' ] );
        add_action( 'wp_ajax_naws_import_chunk',        [ $this, 'import_chunk' ] );
        add_action( 'wp_ajax_naws_import_get_jobs',      [ $this, 'import_get_jobs' ] );
        add_action( 'wp_ajax_naws_import_debug',          [ $this, 'import_debug' ] );
        add_action( 'wp_ajax_naws_migrate_readings',      [ $this, 'migrate_readings' ] );
        add_action( 'wp_ajax_naws_clear_daily_summary',   [ $this, 'clear_daily_summary' ] );
        add_action( 'wp_ajax_naws_db_check',               [ $this, 'db_check' ] );
        add_action( 'wp_ajax_naws_save_live_settings',     [ $this, 'save_live_settings' ] );
        add_action( 'wp_ajax_naws_import_process_chunk',   [ $this, 'import_process_chunk' ] );
        add_action( 'wp_ajax_naws_import_meta',            [ $this, 'import_meta' ] );
        add_action( 'wp_ajax_naws_import_cleanup',         [ $this, 'import_cleanup' ] );
        add_action( 'wp_ajax_naws_get_modules',         [ $this, 'get_modules' ] );
        add_action( 'wp_ajax_naws_delete_readings',     [ $this, 'delete_readings' ] );
        add_action( 'wp_ajax_naws_toggle_module',        [ $this, 'toggle_module' ] );

        // Public AJAX (for frontend widgets)
        add_action( 'wp_ajax_naws_get_chart_data',         [ $this, 'get_chart_data' ] );
        add_action( 'wp_ajax_nopriv_naws_get_chart_data',  [ $this, 'get_chart_data' ] );

        add_action( 'wp_ajax_naws_get_latest',             [ $this, 'get_latest' ] );
        add_action( 'wp_ajax_nopriv_naws_get_latest',      [ $this, 'get_latest' ] );

        add_action( 'wp_ajax_naws_get_daily_data',         [ $this, 'get_daily_data' ] );
        add_action( 'wp_ajax_nopriv_naws_get_daily_data',  [ $this, 'get_daily_data' ] );

        add_action( 'wp_ajax_naws_get_history_data',        [ $this, 'get_history_data' ] );
        add_action( 'wp_ajax_nopriv_naws_get_history_data', [ $this, 'get_history_data' ] );

        // Admin: trigger daily summary manually
        add_action( 'wp_ajax_naws_run_daily_summary',      [ $this, 'run_daily_summary' ] );
    }

    // ----------------------------------------------------------------
    // Admin AJAX
    // ----------------------------------------------------------------

    public function sync_now() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $api    = new NAWS_API();
        $result = $api->sync_current_data();

        // Log to cron log so manual syncs are visible too
        $log = get_option( 'naws_cron_log', [] );
        if ( is_wp_error( $result ) ) {
            array_unshift( $log, [
                'time'    => time(),
                'status'  => 'error',
                'message' => 'Manual sync: ' . $result->get_error_message(),
            ] );
            update_option( 'naws_cron_log', array_slice( $log, 0, 150 ) );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $expiry = (int) get_option( 'naws_token_expiry', 0 );
        array_unshift( $log, [
            'time'    => time(),
            'status'  => 'ok',
            'message' => sprintf(
                'Manual sync OK – %d readings saved. Token valid until %s.',
                intval( $result ),
                $expiry ? wp_date( 'H:i', $expiry ) : '?'
            ),
        ] );
        update_option( 'naws_cron_log', array_slice( $log, 0, 150 ) );

        wp_send_json_success( [
            'message'   => sprintf( 'Synced successfully. %d new readings saved.', intval( $result ) ),
            'timestamp' => time(),
            'readings'  => intval( $result ),
        ] );
    }

    public function import_chunk() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $device_id   = sanitize_text_field( wp_unslash( $_POST['device_id']   ?? ''  ) );
        $module_id   = sanitize_text_field( wp_unslash( $_POST['module_id']   ?? ''  ) );
        $module_type = sanitize_text_field( wp_unslash( $_POST['module_type'] ?? ''  ) );
        $date_begin  = intval( $_POST['date_begin'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $date_end    = intval( $_POST['date_end']   ?? time() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if ( empty( $device_id ) || empty( $module_id ) || empty( $module_type ) || ! $date_begin ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ] );
            return;
        }

        $result = NAWS_Importer::fetch_chunk( $device_id, $module_id, $module_type, $date_begin, $date_end );

        if ( is_wp_error( $result ) ) {
            // "skip" errors are informational, not real errors
            if ( $result->get_error_code() === 'skip' ) {
                wp_send_json_success( [
                    'inserted'     => 0,
                    'rows_fetched' => 0,
                    'next_begin'   => null,
                    'skipped'      => true,
                    'message'      => $result->get_error_message(),
                ] );
            } else {
                wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            }
            return;
        }

        wp_send_json_success( $result );
    }

    public function import_debug() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $device_id   = sanitize_text_field( wp_unslash( $_POST['device_id']   ?? ''  ) );
        $module_id   = sanitize_text_field( wp_unslash( $_POST['module_id']   ?? ''  ) );
        $module_type = sanitize_text_field( wp_unslash( $_POST['module_type'] ?? ''  ) );
        $date_begin  = intval( $_POST['date_begin'] ?? strtotime( '2025-01-01' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $date_end    = intval( $_POST['date_end']   ?? strtotime( '2025-01-03' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $types_raw   = sanitize_text_field( wp_unslash( $_POST['types'] ?? ''  ) );

        $types = $types_raw
            ? explode( ',', $types_raw )
            : NAWS_Importer::get_import_types( $module_type );
        $sends_module_id = ( ! empty( $module_id ) && $module_id !== $device_id );

        // Get the token directly to make a raw request
        $access_token = NAWS_Crypto::get_option( 'naws_access_token', '' );

        // Mirror importer: rain uses 1day+sum_rain+optimize=true+real_time=true
        $is_rain = ( $module_type === 'NAModule3' );

        if ( $is_rain ) {
            // Align to Europe/Berlin midnight (same as importer)
            $tz = new DateTimeZone( 'Europe/Berlin' );
            $dt = new DateTime( '@' . $date_begin );
            $dt->setTimezone( $tz ); $dt->setTime( 0, 0, 0 );
            $date_begin = $dt->getTimestamp();

            $dt2 = new DateTime( '@' . $date_end );
            $dt2->setTimezone( $tz ); $dt2->modify( '+1 day' ); $dt2->setTime( 0, 0, 0 );
            $date_end = $dt2->getTimestamp();
        }

        // Build POST body exactly as get_measure does
        $post_body = [
            'device_id'  => $device_id,
            'type'       => $is_rain ? 'sum_rain' : implode( ',', $types ),
            'scale'      => $is_rain ? '1day'     : '1hour',
            'date_begin' => $date_begin,
            'date_end'   => $date_end,
            'optimize'   => $is_rain ? 'true'     : 'false',
            'real_time'  => $is_rain ? 'true'      : 'false',
            'limit'      => 1024,
        ];
        if ( $sends_module_id ) {
            $post_body['module_id'] = $module_id;
        }

        // Make raw HTTP request – capture everything
        $response = wp_remote_post( 'https://api.netatmo.com/api/getmeasure', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => $post_body,
            'timeout' => 30,
        ] );

        $http_code   = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $parsed      = json_decode( $raw_body, true );
        $wp_error    = is_wp_error( $response ) ? $response->get_error_message() : null;

        // Also check token expiry
        $token_expires = intval( get_option( 'naws_token_expiry', 0 ) );
        $token_expired = $token_expires > 0 && $token_expires < time();
        $token_age_min = $token_expires > 0 ? round( ( $token_expires - time() ) / 60 ) : null;

        // Sanitize: strip access token from post_body before returning
        $safe_post_body = $post_body;
        unset( $safe_post_body['access_token'] );

        // Limit raw_body to 2000 chars to avoid leaking excessive API data
        $safe_raw_body = mb_strlen( $raw_body ) > 2000
            ? mb_substr( $raw_body, 0, 2000 ) . '… (truncated)'
            : $raw_body;

        wp_send_json_success( [
            'http_code'         => $http_code,
            'wp_error'          => $wp_error,
            'raw_body'          => $safe_raw_body,
            'parsed'            => $parsed,
            'sends_module_id'   => $sends_module_id,
            'post_body_sent'    => $safe_post_body,
            'token_present'     => ! empty( $access_token ),
            'token_expired'     => $token_expired,
            'token_expires_in'  => $token_age_min !== null
                ? ( $token_expired ? "ABGELAUFEN vor " . abs($token_age_min) . " min" : "gültig noch {$token_age_min} min" ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                : 'unbekannt',
            'date_begin_human'  => wp_date(  'd.m.Y H:i', $date_begin ),
            'date_end_human'    => wp_date(  'd.m.Y H:i', $date_end ),
        ] );
    }

    public function save_live_settings() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $errors = [];

        // Hidden individual params
        $hidden = isset( $_POST['hidden'] ) ? (array) $_POST['hidden'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $hidden = array_map( 'sanitize_text_field', $hidden );
        if ( ! update_option( 'naws_live_hidden_params', $hidden ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            // update_option returns false both for unchanged value and actual failure — check if value matches
            if ( get_option( 'naws_live_hidden_params' ) !== $hidden ) {
                $errors[] = 'hidden_params';
            }
        }

        // Hidden modules (master toggle)
        $hidden_modules = isset( $_POST['hidden_modules'] ) ? (array) $_POST['hidden_modules'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $hidden_modules = array_map( 'sanitize_text_field', $hidden_modules );
        if ( ! update_option( 'naws_live_hidden_modules', $hidden_modules ) ) {
            if ( get_option( 'naws_live_hidden_modules' ) !== $hidden_modules ) {
                $errors[] = 'hidden_modules';
            }
        }

        // Hidden charts (per-sensor 24h chart toggle)
        $hidden_charts = isset( $_POST['hidden_charts'] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['hidden_charts'] ) )
            : [];
        if ( ! update_option( 'naws_live_hidden_charts', $hidden_charts ) ) {
            if ( get_option( 'naws_live_hidden_charts' ) !== $hidden_charts ) {
                $errors[] = 'hidden_charts';
            }
        }

        // Hidden history (yearly comparison) charts
        $hidden_history_charts = isset( $_POST['hidden_history_charts'] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['hidden_history_charts'] ) )
            : [];
        if ( ! update_option( 'naws_history_hidden_charts', $hidden_history_charts ) ) {
            if ( get_option( 'naws_history_hidden_charts' ) !== $hidden_history_charts ) {
                $errors[] = 'hidden_history_charts';
            }
        }

        if ( ! empty( $errors ) ) {
            NAWS_Logger::error( 'ajax', 'save_live_settings: failed to save options', [ 'keys' => $errors ] );
            wp_send_json_error( [ 'message' => 'Failed to save: ' . implode( ', ', $errors ) ] );
            return;
        }

        wp_send_json_success( [ 'saved_params' => count( $hidden ), 'saved_modules' => count( $hidden_modules ), 'saved_charts' => count( $hidden_charts ) ] );
    }

    public function db_check() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }
        global $wpdb;
        $table     = $wpdb->prefix . NAWS_TABLE_DAILY;
        $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? ''  ) );
        $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? ''  ) );
        if ( ! $date_from ) $date_from = gmdate(  'Y-m-d', strtotime( '-7 days' ) );
        if ( ! $date_to   ) $date_to   = gmdate(  'Y-m-d' );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from constant
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT day_date,
                    ROUND(temp_min,1)     AS temp_min,
                    ROUND(temp_max,1)     AS temp_max,
                    ROUND(pressure_avg,1) AS pressure_avg,
                    ROUND(rain_sum,2)     AS rain_sum
             FROM {$table}
             WHERE day_date BETWEEN %s AND %s
             ORDER BY day_date ASC",
            $date_from, $date_to
        ), ARRAY_A );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'ajax', 'db_check query failed: ' . $wpdb->last_error );
            wp_send_json_error( [ 'message' => 'Database query failed: ' . $wpdb->last_error ] );
            return;
        }

        wp_send_json_success( [ 'rows' => $rows ?: [] ] );
    }

    public function clear_daily_summary() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }
        global $wpdb;
        $table   = $wpdb->prefix . NAWS_TABLE_DAILY;
        $deleted = $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant, no user input

        if ( $deleted === false ) {
            NAWS_Logger::error( 'ajax', 'clear_daily_summary DELETE failed: ' . $wpdb->last_error );
            wp_send_json_error( [ 'message' => 'Failed to clear daily summary table: ' . $wpdb->last_error ] );
            return;
        }

        NAWS_Logger::info( 'ajax', 'Daily summary table cleared by admin' );
        wp_send_json_success( [ 'deleted' => 'alle' ] );
    }

    /**
     * Migrate existing naws_readings into naws_daily_summary.
     * Processes one batch of dates per AJAX call to avoid timeouts.
     */
    public function migrate_readings() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        global $wpdb;
        $r = $wpdb->prefix . NAWS_TABLE_READINGS;

        // Get the date range of existing readings
        $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? ''  ) );
        $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? ''  ) );
        $batch     = max( 1, intval( $_POST['batch'] ?? 30 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval sanitizes

        // Detect range from readings table, always exclude today (Cron handles today at 00:01)
        $today = gmdate(  'Y-m-d' );

        if ( ! $date_from || ! $date_to ) {
            $range = NAWS_Database::get_data_range();
            if ( empty( $range['date_begin'] ) ) {
                wp_send_json_error( [ 'message' => 'Keine Daten in naws_readings gefunden.' ] );
                return;
            }
            $date_from = gmdate(  'Y-m-d', $range['date_begin'] );
            // date_to = yesterday at most – today is handled by the nightly cron
            $date_to = gmdate(  'Y-m-d', strtotime( 'yesterday' ) );
        }

        // Safety: never process today
        if ( $date_to >= $today ) {
            $date_to = gmdate(  'Y-m-d', strtotime( 'yesterday' ) );
        }

        if ( $date_from > $date_to ) {
            wp_send_json_success( [
                'processed' => 0,
                'saved'     => 0,
                'next_from' => null,
                'message'   => 'Alle vergangenen Tage bereits migriert. Heutiger Tag wird um 00:01 Uhr automatisch verarbeitet.',
            ] );
            return;
        }

        // Get distinct completed days (not today) that have readings
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from constant
        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT DATE(FROM_UNIXTIME(recorded_at)) as day
             FROM {$r}
             WHERE recorded_at >= UNIX_TIMESTAMP(%s)
               AND recorded_at <= UNIX_TIMESTAMP(%s)
               AND DATE(FROM_UNIXTIME(recorded_at)) < CURDATE()
             ORDER BY day ASC
             LIMIT %d",
            $date_from . ' 00:00:00',
            $date_to   . ' 23:59:59',
            $batch
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( empty( $dates ) ) {
            wp_send_json_success( [
                'processed' => 0,
                'saved'     => 0,
                'next_from' => null,
                'message'   => 'Alle Tage migriert.',
            ] );
            return;
        }

        $saved = 0;
        foreach ( $dates as $date ) {
            $result = NAWS_Database::compute_daily_summary( $date );
            $saved += $result;
        }

        // Next batch starts after the last processed date
        $last_date = end( $dates );
        $next_from = gmdate(  'Y-m-d', strtotime( $last_date . ' +1 day' ) );
        $has_more  = ( $next_from <= $date_to && count( $dates ) === $batch );

        wp_send_json_success( [
            'processed' => count( $dates ),
            'saved'     => $saved,
            'first'     => $dates[0],
            'last'      => $last_date,
            'next_from' => $has_more ? $next_from : null,
            'date_to'   => $date_to,
        ] );
    }

    public function import_get_jobs() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? ''  ) );
        $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? ''  ) );

        if ( ! $date_from || ! $date_to ) {
            wp_send_json_error( [ 'message' => 'date_from and date_to required.' ] );
            return;
        }

        $date_begin = strtotime( $date_from . ' 00:00:00' );
        $date_end   = strtotime( $date_to   . ' 23:59:59' );

        $jobs = NAWS_Importer::build_job_list( $date_begin, $date_end );
        wp_send_json_success( [ 'jobs' => $jobs ] );
    }

    public function get_modules() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        wp_send_json_success( NAWS_Database::get_modules() );
    }

    public function delete_readings() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $days = intval( $_POST['days'] ?? 365 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval sanitizes
        $deleted = NAWS_Database::purge_old_readings( $days );
        wp_send_json_success( [ 'deleted' => $deleted ] );
    }

    public function get_daily_data() {
        check_ajax_referer( 'naws_public_nonce', 'nonce' );
        nocache_headers();

        $module_id  = sanitize_text_field( wp_unslash( $_POST['module_id'] ?? ''  ) );
        $date_from  = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? gmdate(  'Y-m-d', strtotime('-365 days' ) ) ) );
        $date_to    = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? gmdate(  'Y-m-d'  ) ) );
        $group_by   = in_array( $_POST['group_by'] ?? 'day', [ 'day','week','month','year' ], true ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    ? sanitize_key( wp_unslash( $_POST['group_by']  ) ) : 'day';

        $raw_fields = isset( $_POST['fields'] )
            ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ) )
            : [ 'temp_min','temp_max','temp_avg','pressure_avg','rain_sum' ];

        $result = NAWS_Database::get_daily_chart_data( [
            'module_id' => $module_id ?: null,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'group_by'  => $group_by,
            'fields'    => $raw_fields,
        ] );

        wp_send_json_success( $result );
    }

    public function get_history_data() {
        check_ajax_referer( 'naws_public_nonce', 'nonce' );
        nocache_headers();
        global $wpdb;

        $table      = $wpdb->prefix . NAWS_TABLE_DAILY;
        $allowed    = [ 'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum' ];
        $raw_fields = isset( $_POST['fields'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    ? array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ) ), $allowed )
                    : $allowed;
        if ( empty( $raw_fields ) ) $raw_fields = $allowed;

        $year_from = intval( $_POST['year_from'] ?? gmdate( 'Y') ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $year_to   = intval( $_POST['year_to']   ?? gmdate( 'Y') ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // ── Single query for all years (replaces N+1 per-year loop) ──────
        // %i placeholders for field identifiers (WP 6.2+); field args come before WHERE args
        $field_ph = implode( ', ', array_fill( 0, count( $raw_fields ), '%i' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table} is prefix+constant; field names passed as %i identifiers; placeholder count is dynamic
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT d.day_date, {$field_ph}
             FROM {$table} d
             WHERE d.day_date BETWEEN %s AND %s
             ORDER BY d.day_date ASC",
            array_merge( $raw_fields, [ "{$year_from}-01-01", "{$year_to}-12-31" ] )
        ), ARRAY_A );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( $wpdb->last_error ) {
            NAWS_Logger::error( 'ajax', 'get_history_data query failed: ' . $wpdb->last_error );
            wp_send_json_error( [ 'message' => 'Database query failed.' ] );
            return;
        }

        if ( empty( $rows ) ) {
            wp_send_json_success( [ 'series' => [] ] );
            return;
        }

        // Group rows by year in PHP
        $by_year = [];
        foreach ( $rows as $row ) {
            $year = intval( substr( $row['day_date'], 0, 4 ) );
            $by_year[ $year ][] = $row;
        }

        // Field → parameter mapping for unit conversion
        $field_param_map = [
            'temp_min'     => 'Temperature',
            'temp_max'     => 'Temperature',
            'temp_avg'     => 'Temperature',
            'pressure_avg' => 'Pressure',
            'rain_sum'     => 'Rain',
        ];

        $result = [];

        foreach ( $by_year as $year => $year_rows ) {
            foreach ( $raw_fields as $field ) {
                $data  = [];
                $param = $field_param_map[ $field ] ?? null;
                foreach ( $year_rows as $row ) {
                    $val = $row[ $field ] ?? null;
                    if ( $val === null ) continue;
                    $converted = $param
                        ? NAWS_Helpers::format_value( $param, floatval( $val ) )
                        : round( floatval( $val ), 2 );
                    $data[] = [
                        'x' => substr( $row['day_date'], 5 ), // "MM-DD"
                        'y' => $converted,
                    ];
                }
                if ( empty( $data ) ) continue;
                $result[] = [
                    'year'  => $year,
                    'field' => $field,
                    'data'  => $data,
                ];
            }
        }

        wp_send_json_success( [ 'series' => $result ] );
    }

    public function run_daily_summary() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? ''  ) );
        if ( ! $date ) {
            $date = wp_date( 'Y-m-d', strtotime( 'yesterday' ) );
        }

        // Fetch directly from Netatmo API – same as History Import, independent of naws_readings
        $tz        = new DateTimeZone( 'Europe/Berlin' );
        $day_start = ( new DateTimeImmutable( $date . ' 00:00:00', $tz ) )->getTimestamp();
        $day_end   = ( new DateTimeImmutable( $date . ' 23:59:59', $tz ) )->getTimestamp();

        $jobs   = NAWS_Importer::build_job_list( $day_start, $day_end );
        $ok     = 0;
        $errors = [];

        foreach ( $jobs as $job ) {
            $result = NAWS_Importer::fetch_chunk(
                $job['device_id'],
                $job['module_id'],
                $job['module_type'],
                $day_start,
                $day_end
            );
            if ( is_wp_error( $result ) ) {
                $errors[] = $job['module_name'] . ': ' . $result->get_error_message();
            } else {
                $ok++;
            }
        }

        update_option( 'naws_last_daily_summary', $date, false );

        wp_send_json_success( [
            'message' => sprintf(
                '%d Modul(e) verarbeitet%s',
                $ok,
                ! empty( $errors ) ? ' — Fehler: ' . implode( '; ', $errors ) : '.' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ),
            'date' => $date,
            'ok'   => $ok,
        ] );
    }

    public function toggle_module() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $module_id = sanitize_text_field( wp_unslash( $_POST['module_id'] ?? ''  ) );
        $is_active = intval( $_POST['is_active'] ?? 1 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval sanitizes

        if ( empty( $module_id ) ) {
            wp_send_json_error( [ 'message' => 'Missing module_id.' ] );
            return; // Early return – wp_send_json_error does die(), but be explicit
        }

        $result = NAWS_Database::set_module_active( $module_id, $is_active );

        if ( is_wp_error( $result ) ) {
            NAWS_Logger::error( 'ajax', 'toggle_module failed: ' . $result->get_error_message(), [ 'module_id' => $module_id ] );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }

        // Flush module caches so the change is reflected immediately
        NAWS_Database::flush_module_caches();

        wp_send_json_success( [ 'module_id' => $module_id, 'is_active' => $is_active ] );
    }

    // ----------------------------------------------------------------
    // Public AJAX
    // ----------------------------------------------------------------

    public function get_chart_data() {
        check_ajax_referer( 'naws_public_nonce', 'nonce' );
        nocache_headers();

        $module_id = sanitize_text_field( wp_unslash( $_POST['module_id'] ?? ''  ) );
        $parameter = isset( $_POST['parameter'] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['parameter'] ) )
            : [];
        $date_from = intval( $_POST['date_from'] ?? strtotime( '-7 days' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $date_to   = intval( $_POST['date_to']   ?? time() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $group_by  = in_array( // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $_POST['group_by'] ?? 'raw',
            [ 'raw', 'hour', 'day', 'week', 'month', 'year' ], true
        ) ? sanitize_key( wp_unslash( $_POST['group_by']  ) ) : 'raw';

        $readings = NAWS_Database::get_readings( [
            'module_id' => $module_id ?: null,
            'parameter' => $parameter ?: null,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'group_by'  => $group_by,
            'limit'     => 3000,
        ] );

        // Build readable module map for dataset labels
        $modules    = NAWS_Database::get_modules( false ); // include inactive (still shown if explicitly queried)
        $module_map = [];
        foreach ( $modules as $m ) {
            $module_map[ $m['module_id'] ] = $m['module_name'];
        }

        // Decimal places per parameter type
        $decimals_map = [
            'Temperature'      => 1,
            'Humidity'         => 1,
            'Pressure'         => 1,
            'AbsolutePressure' => 1,
            'WindStrength'     => 1,
            'GustStrength'     => 1,
            'WindAngle'        => 0,
            'GustAngle'        => 0,
            'Rain'             => 1,
            'sum_rain_1'       => 1,
            'sum_rain_24'      => 1,
            'CO2'              => 0,
            'Noise'            => 0,
        ];

        // Group by module+parameter key
        $data_by_key = [];
        foreach ( $readings as $row ) {
            $key = $row['module_id'] . '||' . $row['parameter'];
            $data_by_key[ $key ][] = [
                'x' => intval( $row['recorded_at'] ) * 1000, // ms for Chart.js
                'y' => NAWS_Helpers::format_value( $row['parameter'], floatval( $row['value'] ) ),
            ];
        }

        $colors = [
            '#00d4ff','#10b981','#f59e0b','#7c3aed',
            '#ef4444','#3b82f6','#ec4899','#8b5cf6',
        ];
        $datasets = [];
        $i        = 0;
        foreach ( $data_by_key as $key => $points ) {
            [ $mod_id, $param ] = explode( '||', $key, 2 );
            $mod_name = $module_map[ $mod_id ] ?? $mod_id;
            $label    = count( $data_by_key ) > 1
                ? $mod_name . ' – ' . NAWS_Helpers::get_label( $param )
                : NAWS_Helpers::get_label( $param );

            $color      = $colors[ $i % count( $colors ) ];
            $datasets[] = [
                'label'           => $label,
                'data'            => $points,
                'borderColor'     => $color,
                'backgroundColor' => $color . '33',
                'borderWidth'     => 2,
                'pointRadius'     => count( $points ) > 100 ? 0 : 3,
                'tension'         => 0.4,
                'fill'            => false,
            ];
            $i++;
        }

        wp_send_json_success( [
            'datasets' => $datasets,
            'count'    => count( $readings ),
            'debug'    => [
                'date_from'  => wp_date(  'Y-m-d H:i', $date_from ),
                'date_to'    => wp_date(  'Y-m-d H:i', $date_to ),
                'group_by'   => $group_by,
                'module_id'  => $module_id,
                'parameters' => $parameter,
                'rows_found' => count( $readings ),
            ],
        ] );
    }

    public function get_latest() {
        check_ajax_referer( 'naws_public_nonce', 'nonce' );
        nocache_headers();

        $module_id = sanitize_text_field( wp_unslash( $_POST['module_id'] ?? ''  ) );
        $readings  = NAWS_Database::get_latest_readings( $module_id ?: null );
        $modules   = NAWS_Database::get_modules();

        // Enrich with module names
        $module_map = [];
        foreach ( $modules as $m ) {
            $module_map[ $m['module_id'] ] = $m;
        }

        $formatted = [];
        foreach ( $readings as $row ) {
            $formatted[] = array_merge( $row, [
                'module_name' => $module_map[ $row['module_id'] ]['module_name'] ?? $row['module_id'],
                'module_type' => $module_map[ $row['module_id'] ]['module_type'] ?? '',
                'value'       => NAWS_Helpers::format_value( $row['parameter'], floatval( $row['value'] ) ),
                'unit'        => NAWS_Helpers::get_unit( $row['parameter'] ),
                'icon'        => NAWS_Helpers::get_icon( $row['parameter'] ),
            ] );
        }

        // Compute rolling 24h rain sum from our own readings (Netatmo's sum_rain_24 resets at midnight)
        $rain_module = null;
        foreach ( $module_map as $mid => $mod ) {
            if ( ( $mod['module_type'] ?? '' ) === 'NAModule3' && ( $mod['is_active'] ?? 0 ) ) {
                $rain_module = $mid;
                break;
            }
        }
        if ( $rain_module ) {
            $rolling = NAWS_Database::get_rain_rolling_24h( $rain_module );
            if ( $rolling !== null ) {
                $formatted[] = [
                    'module_id'   => $rain_module,
                    'module_type' => 'NAModule3',
                    'parameter'   => 'rain_rolling_24h',
                    'value'       => NAWS_Helpers::format_value( 'Rain', $rolling ),
                    'unit'        => NAWS_Helpers::get_unit( 'Rain' ),
                    'recorded_at' => time(),
                    'module_name' => $module_map[ $rain_module ]['module_name'] ?? '',
                    'icon'        => NAWS_Helpers::get_icon( 'Rain' ),
                ];
            }
        }

        wp_send_json_success( $formatted );
    }

    // ----------------------------------------------------------------
    // Export / Import AJAX
    // ----------------------------------------------------------------

    /**
     * Process one chunk of daily_summary rows from an uploaded import file.
     */
    public function import_process_chunk() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $file_path = get_transient( 'naws_import_temp_file' );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            wp_send_json_error( [ 'message' => 'No import file found. Please upload again.' ] );
            return;
        }

        $offset = intval( $_POST['offset'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval sanitizes
        $result = NAWS_Export::import_weather_data( $file_path, $offset, NAWS_Export::IMPORT_BATCH );

        if ( ! empty( $result['error'] ) ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
            return;
        }

        wp_send_json_success( $result );
    }

    /**
     * Import modules and settings from a full backup file (called once before chunked data import).
     */
    public function import_meta() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $file_path = get_transient( 'naws_import_temp_file' );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            wp_send_json_error( [ 'message' => 'No import file found.' ] );
            return;
        }

        $meta              = get_transient( 'naws_import_meta' );
        $overwrite         = ! empty( $meta['overwrite_settings'] );
        $overwrite_from_post = intval( $_POST['overwrite_settings'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval sanitizes
        if ( $overwrite_from_post ) {
            $overwrite = true;
        }

        $result = NAWS_Export::import_full_backup_meta( $file_path, $overwrite );

        if ( ! empty( $result['error'] ) ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
            return;
        }

        NAWS_Logger::info( 'export', 'Full backup meta imported', [
            'modules' => $result['modules_imported'],
            'settings' => $result['settings_imported'],
        ] );

        wp_send_json_success( $result );
    }

    /**
     * Clean up temporary import file and transients after import is done.
     */
    public function import_cleanup() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 ); }

        $file_path = get_transient( 'naws_import_temp_file' );
        if ( $file_path && file_exists( $file_path ) ) {
            wp_delete_file( $file_path );
        }

        delete_transient( 'naws_import_temp_file' );
        delete_transient( 'naws_import_meta' );

        wp_send_json_success();
    }
}
