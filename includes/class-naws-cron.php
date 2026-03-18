<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Cron {

    const HOOK_FETCH   = 'naws_fetch_data';
    const HOOK_DAILY   = 'naws_daily_summary';

    // Keep backwards compat for code that used the old constant name
    const HOOK = self::HOOK_FETCH;

    /** Option key for adaptive polling state. */
    const OPT_POLLING_STATE = 'naws_polling_state';

    /** Maximum interval (seconds) during error backoff. */
    const MAX_BACKOFF_INTERVAL = 3600; // 60 minutes

    /** Number of consecutive errors before backoff kicks in. */
    const ERROR_THRESHOLD = 3;

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'cron_schedules',      [ $this, 'add_schedules' ] );
        add_action( self::HOOK_FETCH,      [ $this, 'run_fetch' ] );
        add_action( self::HOOK_DAILY,      [ $this, 'run_daily_summary' ] );
        add_action( 'naws_settings_saved', [ $this, 'reschedule' ] );
    }

    // ────────────────────────────────────────────────────────────────
    // Schedules
    // ────────────────────────────────────────────────────────────────

    public function add_schedules( $schedules ) {
        foreach ( [ 5, 10, 15, 20, 30, 60, 120 ] as $min ) {
            $key = 'naws_' . $min . '_minutes';
            if ( ! isset( $schedules[$key] ) ) {
                $schedules[$key] = [
                    'interval' => $min * MINUTE_IN_SECONDS,
                    'display'  => sprintf( 'Every %d Minutes (Netatmo)', $min ),
                ];
            }
        }

        // Daily at midnight+1
        if ( ! isset( $schedules['naws_daily'] ) ) {
            $schedules['naws_daily'] = [
                'interval' => DAY_IN_SECONDS,
                'display'  => 'Once Daily (Netatmo Summary)',
            ];
        }

        return $schedules;
    }

    public static function schedule() {
        // Fetch interval
        if ( ! wp_next_scheduled( self::HOOK_FETCH ) ) {
            $options  = get_option( 'naws_settings', [] );
            $interval = max( 5, intval( $options['cron_interval'] ?? 10 ) );
            wp_schedule_event( time(), 'naws_' . $interval . '_minutes', self::HOOK_FETCH );
        }

        // Daily summary: fire at 00:01 Berlin time each night
        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            $tz             = new DateTimeZone( 'Europe/Berlin' );
            $today_00_01    = ( new DateTimeImmutable( 'today 00:01:00', $tz ) )->getTimestamp();
            $next_run       = $today_00_01 < time()
                ? $today_00_01 + DAY_IN_SECONDS
                : $today_00_01;
            wp_schedule_event( $next_run, 'naws_daily', self::HOOK_DAILY );
        }
    }

    public function reschedule() {
        wp_clear_scheduled_hook( self::HOOK_FETCH );
        // Reset polling state when settings change
        self::reset_polling_state();
        self::schedule();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::HOOK_FETCH );
        wp_clear_scheduled_hook( self::HOOK_DAILY );
    }

    // ────────────────────────────────────────────────────────────────
    // Adaptive Polling State
    // ────────────────────────────────────────────────────────────────

    /**
     * Get the current polling state.
     *
     * @return array {
     *   consecutive_errors: int,
     *   current_interval:   int (seconds),
     *   last_success:       int (timestamp),
     *   last_error:         int (timestamp),
     * }
     */
    public static function get_polling_state() {
        $defaults = [
            'consecutive_errors' => 0,
            'current_interval'   => 0, // 0 = use configured interval
            'last_success'       => 0,
            'last_error'         => 0,
        ];
        $state = get_option( self::OPT_POLLING_STATE, [] );
        return wp_parse_args( $state, $defaults );
    }

    /**
     * Update polling state after a successful sync.
     * Resets error counter and restores normal interval.
     */
    private static function record_success() {
        $state = self::get_polling_state();
        $was_in_backoff = ( $state['consecutive_errors'] >= self::ERROR_THRESHOLD );

        $state['consecutive_errors'] = 0;
        $state['current_interval']   = 0; // Reset to configured interval
        $state['last_success']       = time();
        update_option( self::OPT_POLLING_STATE, $state, false );

        // If we were in backoff, reschedule to normal interval
        if ( $was_in_backoff ) {
            wp_clear_scheduled_hook( self::HOOK_FETCH );
            self::schedule();
            NAWS_Logger::info( 'cron', 'Recovered from error backoff, restored normal polling interval.' );
        }
    }

    /**
     * Update polling state after a failed sync.
     * Increases error counter; after threshold, doubles interval.
     */
    private static function record_error() {
        $state = self::get_polling_state();
        $state['consecutive_errors']++;
        $state['last_error'] = time();

        // Apply backoff after threshold
        if ( $state['consecutive_errors'] >= self::ERROR_THRESHOLD ) {
            $opts        = get_option( 'naws_settings', [] );
            $base_sec    = max( 5, intval( $opts['cron_interval'] ?? 10 ) ) * MINUTE_IN_SECONDS;
            $new_interval = min( $base_sec * 2, self::MAX_BACKOFF_INTERVAL );
            $state['current_interval'] = $new_interval;

            // Reschedule with longer interval
            wp_clear_scheduled_hook( self::HOOK_FETCH );
            $new_min = intval( $new_interval / MINUTE_IN_SECONDS );
            // Find nearest valid schedule key
            $valid_mins = [ 5, 10, 15, 20, 30, 60, 120 ];
            $schedule_min = $valid_mins[0];
            foreach ( $valid_mins as $vm ) {
                if ( $vm <= $new_min ) {
                    $schedule_min = $vm;
                }
            }
            wp_schedule_event( time() + $new_interval, 'naws_' . $schedule_min . '_minutes', self::HOOK_FETCH );
            NAWS_Logger::warning( 'cron', sprintf(
                'Error backoff active: %d consecutive errors. Polling interval increased to %d minutes.',
                $state['consecutive_errors'],
                $new_min
            ) );
        }

        update_option( self::OPT_POLLING_STATE, $state, false );
    }

    /**
     * Reset polling state to defaults.
     */
    public static function reset_polling_state() {
        delete_option( self::OPT_POLLING_STATE );
    }

    /**
     * Check if we're currently in night mode (reduced polling 23:00–06:00).
     *
     * @return bool
     */
    public static function is_night_mode() {
        $opts = get_option( 'naws_settings', [] );
        if ( empty( $opts['night_mode'] ) ) {
            return false;
        }

        $tz   = new DateTimeZone( 'Europe/Berlin' );
        $hour = intval( ( new DateTimeImmutable( 'now', $tz ) )->format( 'G' ) );
        return ( $hour >= 23 || $hour < 6 );
    }

    /**
     * Determine if fetch should be skipped due to night mode.
     * Night mode doubles the interval by skipping every other run.
     *
     * @return bool True if this run should be skipped.
     */
    private static function should_skip_night_mode() {
        if ( ! self::is_night_mode() ) {
            return false;
        }

        // Use a simple toggle: skip if the last run was recent (within normal interval)
        $state = self::get_polling_state();
        $opts  = get_option( 'naws_settings', [] );
        $base  = max( 5, intval( $opts['cron_interval'] ?? 10 ) ) * MINUTE_IN_SECONDS;

        // Skip if last success was less than 2x interval ago (effectively doubling interval)
        if ( $state['last_success'] > 0 && ( time() - $state['last_success'] ) < ( $base * 1.5 ) ) {
            return true;
        }

        return false;
    }

    // ────────────────────────────────────────────────────────────────
    // Fetch callback (runs every N minutes)
    // ────────────────────────────────────────────────────────────────

    public function run_fetch() {
        try {
            $this->do_fetch();
        } catch ( \Throwable $e ) {
            // NEVER let an uncaught exception kill the cron callback.
            $this->log( 'error', 'Uncaught exception: ' . $e->getMessage() );
            NAWS_Logger::error( 'cron', 'Uncaught exception in run_fetch: ' . $e->getMessage() );
            self::record_error();
        }
    }

    /**
     * Actual fetch logic, separated so run_fetch() can wrap it safely.
     */
    private function do_fetch() {
        $options = get_option( 'naws_settings', [] );

        if ( empty( $options['client_id'] ) || empty( $options['client_secret'] ) ) {
            $this->log( 'error', 'Skipped: Client ID or Secret not configured.' );
            return;
        }

        if ( get_option( 'naws_auth_required' ) ) {
            $this->log( 'error', 'Re-authentication required. Please visit XTX Netatmo → Settings.' );
            self::record_error();
            return;
        }

        // Night mode: skip every other fetch to reduce polling frequency
        if ( self::should_skip_night_mode() ) {
            $this->log( 'ok', 'Night mode: skipping this cycle (reduced polling 23:00–06:00).' );
            return;
        }

        $api    = new NAWS_API();
        $result = $api->sync_current_data();

        if ( is_wp_error( $result ) ) {
            $this->log( 'error', $result->get_error_message() );
            NAWS_Logger::error( 'cron', 'Sync failed: ' . $result->get_error_message() );
            self::record_error();
        } else {
            $expiry  = (int) get_option( 'naws_token_expiry', 0 );
            $this->log( 'ok', sprintf(
                'Sync OK – %d readings saved. Token valid until %s.',
                intval( $result ),
                $expiry ? wp_date('H:i', $expiry ) : '?'
            ) );

            // Record success and reset any error backoff
            self::record_success();

            // Flush all caches after successful sync
            NAWS_Database::flush_caches();

            // Fire action hook so other components can react
            do_action( 'naws_data_synced', intval( $result ) );

            // Update last sync timestamp
            update_option( 'naws_last_sync_time', time(), false );
        }

        $today     = wp_date( 'Y-m-d' );
        $yesterday = wp_date( 'Y-m-d', strtotime( 'yesterday' ) );

        // ── Running summary for today (updated on every fetch) ────────────────
        try {
            NAWS_Database::compute_daily_summary( $today );
        } catch ( \Throwable $e ) {
            $this->log( 'error', 'Running summary (today) failed: ' . $e->getMessage() );
        }

        // ── Catchup: ensure yesterday's final summary exists ──────────────────
        $last_run = get_option( 'naws_last_daily_summary', '' );
        if ( $last_run !== $yesterday ) {
            try {
                $tz        = new DateTimeZone( 'Europe/Berlin' );
                $day_start = ( new DateTimeImmutable( $yesterday . ' 00:00:00', $tz ) )->getTimestamp();
                $day_end   = ( new DateTimeImmutable( $yesterday . ' 23:59:59', $tz ) )->getTimestamp();
                $jobs      = NAWS_Importer::build_job_list( $day_start, $day_end );
                $ok        = 0;
                foreach ( $jobs as $job ) {
                    $r = NAWS_Importer::fetch_chunk(
                        $job['device_id'], $job['module_id'], $job['module_type'],
                        $day_start, $day_end
                    );
                    if ( ! is_wp_error( $r ) ) $ok++;
                }
                update_option( 'naws_last_daily_summary', $yesterday, false );
                $this->log( 'daily', sprintf(
                    'Catchup daily summary for %s – %d module(s) fetched from API.',
                    $yesterday, $ok
                ) );
            } catch ( \Throwable $e ) {
                $this->log( 'error', 'Catchup summary (' . $yesterday . ') failed: ' . $e->getMessage() );
            }
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Daily summary callback (runs at 00:01)
    // ────────────────────────────────────────────────────────────────

    public function run_daily_summary() {
        $tz        = new DateTimeZone( 'Europe/Berlin' );
        $yesterday = wp_date( 'Y-m-d', strtotime( 'yesterday' ) );

        $day_start = ( new DateTimeImmutable( $yesterday . ' 00:00:00', $tz ) )->getTimestamp();
        $day_end   = ( new DateTimeImmutable( $yesterday . ' 23:59:59', $tz ) )->getTimestamp();

        $jobs    = NAWS_Importer::build_job_list( $day_start, $day_end );
        $ok      = 0;
        $errors  = [];

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

        update_option( 'naws_last_daily_summary', $yesterday, false );

        // Flush daily caches after summary computation
        NAWS_Database::flush_caches();

        if ( empty( $errors ) ) {
            $this->log( 'daily', sprintf(
                'Daily summary for %s fetched from API – %d module(s) processed.',
                $yesterday, $ok
            ) );
        } else {
            $this->log( 'daily', sprintf(
                'Daily summary for %s – %d OK, errors: %s',
                $yesterday, $ok, implode( '; ', $errors )
            ) );
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Health check helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Get health status for the admin dashboard.
     *
     * @return array { status: 'ok'|'warning'|'error', message: string }
     */
    public static function get_health_status() {
        $state = self::get_polling_state();
        $opts  = get_option( 'naws_settings', [] );
        $base  = max( 5, intval( $opts['cron_interval'] ?? 10 ) ) * MINUTE_IN_SECONDS;

        // No successful sync ever
        if ( $state['last_success'] === 0 ) {
            return [
                'status'  => 'warning',
                'message' => naws__( 'health_no_sync_yet' ),
            ];
        }

        $since_last = time() - $state['last_success'];

        // Error backoff active
        if ( $state['consecutive_errors'] >= self::ERROR_THRESHOLD ) {
            return [
                'status'  => 'error',
                'message' => sprintf(
                    naws__( 'health_error_backoff' ),
                    $state['consecutive_errors'],
                    intval( $since_last / 60 )
                ),
            ];
        }

        // Stale: no sync for > 3x interval
        if ( $since_last > $base * 3 ) {
            return [
                'status'  => 'warning',
                'message' => sprintf( naws__( 'health_stale_sync' ), intval( $since_last / 60 ) ),
            ];
        }

        // Night mode active
        if ( self::is_night_mode() ) {
            return [
                'status'  => 'ok',
                'message' => naws__( 'health_night_mode' ),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => sprintf( naws__( 'health_ok' ), intval( $since_last / 60 ) ),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Logging
    // ────────────────────────────────────────────────────────────────

    private function log( $status, $message ) {
        $log = get_option( 'naws_cron_log', [] );
        array_unshift( $log, [
            'time'    => time(),
            'status'  => $status,
            'message' => $message,
        ] );
        update_option( 'naws_cron_log', array_slice( $log, 0, 150 ) );
    }

    public static function get_next_run() {
        return wp_next_scheduled( self::HOOK_FETCH );
    }

    public static function get_next_daily_run() {
        return wp_next_scheduled( self::HOOK_DAILY );
    }
}
