<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Cron {

    const HOOK_FETCH   = 'naws_fetch_data';
    const HOOK_DAILY   = 'naws_daily_summary';

    // Keep backwards compat for code that used the old constant name
    const HOOK = self::HOOK_FETCH;

    /** Option key for adaptive polling state. */
    const OPT_POLLING_STATE = 'naws_polling_state';

    /**
     * The only fetch intervals (minutes) that exist as WP-Cron schedules.
     * Anything stored in the settings has to be one of these — see
     * normalise_interval().
     */
    const INTERVALS = [ 5, 10, 15, 20, 30, 60, 120 ];

    /** Default fetch interval (minutes) when nothing is configured. */
    const DEFAULT_INTERVAL = 10;

    /**
     * Maximum interval (seconds) during error backoff: the longest schedule
     * that actually exists. A lower cap would make the backoff *shorten* the
     * interval for anyone polling every 60 or 120 minutes.
     */
    const MAX_BACKOFF_INTERVAL = 7200; // 120 minutes

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
        // Intervals are built in add_schedules() from a fixed 5..120 minute
        // list, so the sniff cannot read the value from this line. Five
        // minutes is deliberate: it matches how often Netatmo itself updates.
        add_filter( 'cron_schedules', [ $this, 'add_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- intervals defined in add_schedules(), minimum 5 minutes by design
        add_action( self::HOOK_FETCH,      [ $this, 'run_fetch' ] );
        add_action( self::HOOK_DAILY,      [ $this, 'run_daily_summary' ] );
        add_action( 'naws_settings_saved', [ $this, 'reschedule' ] );
    }

    // ────────────────────────────────────────────────────────────────
    // Schedules
    // ────────────────────────────────────────────────────────────────

    /**
     * Snap a requested interval to the nearest schedule that exists.
     *
     * wp_schedule_event() silently fails on an unknown schedule key, so an
     * unlisted value such as 45 would leave the site with no fetch cron at
     * all. Ties go to the longer interval (less polling).
     *
     * @param  mixed $minutes  Requested interval in minutes.
     * @return int             One of self::INTERVALS.
     */
    public static function normalise_interval( $minutes ) {
        $minutes = is_numeric( $minutes ) ? intval( $minutes ) : self::DEFAULT_INTERVAL;
        if ( $minutes <= 0 ) {
            return self::DEFAULT_INTERVAL;
        }

        $best = self::INTERVALS[0];
        foreach ( self::INTERVALS as $candidate ) {
            if ( abs( $candidate - $minutes ) <= abs( $best - $minutes ) ) {
                $best = $candidate;
            }
        }
        return $best;
    }

    /**
     * Configured fetch interval in seconds, snapped to a real schedule.
     *
     * @return int
     */
    private static function base_interval() {
        $opts = get_option( 'naws_settings', [] );
        return self::normalise_interval( $opts['cron_interval'] ?? self::DEFAULT_INTERVAL ) * MINUTE_IN_SECONDS;
    }

    public function add_schedules( $schedules ) {
        foreach ( self::INTERVALS as $min ) {
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
            $interval = intval( self::base_interval() / MINUTE_IN_SECONDS );
            wp_schedule_event( time(), 'naws_' . $interval . '_minutes', self::HOOK_FETCH );
        }

        // Daily summary: fire at 00:01 site-local time each night
        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            $tz             = naws_timezone();
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
     *   last_attempt:       int (timestamp of the last fetch that ran, success or not),
     * }
     */
    public static function get_polling_state() {
        $defaults = [
            'consecutive_errors' => 0,
            'current_interval'   => 0, // 0 = use configured interval
            'last_success'       => 0,
            'last_error'         => 0,
            'last_attempt'       => 0,
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
        $state['last_attempt']       = time();
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
        $state['last_error']   = time();
        $state['last_attempt'] = time();

        // Apply backoff after threshold
        if ( $state['consecutive_errors'] >= self::ERROR_THRESHOLD ) {
            $base_min = intval( self::base_interval() / MINUTE_IN_SECONDS );
            $new_min  = self::backoff_interval( $base_min );

            if ( $new_min > $base_min ) {
                $state['current_interval'] = $new_min * MINUTE_IN_SECONDS;

                // Reschedule with the longer interval
                wp_clear_scheduled_hook( self::HOOK_FETCH );
                wp_schedule_event(
                    time() + ( $new_min * MINUTE_IN_SECONDS ),
                    'naws_' . $new_min . '_minutes',
                    self::HOOK_FETCH
                );
                NAWS_Logger::warning( 'cron', sprintf(
                    'Error backoff active: %d consecutive errors. Polling interval increased to %d minutes.',
                    $state['consecutive_errors'],
                    $new_min
                ) );
            } else {
                // Already at the longest schedule – nothing left to back off to.
                $state['current_interval'] = 0;
                NAWS_Logger::warning( 'cron', sprintf(
                    'Error backoff: %d consecutive errors. Interval already at the maximum of %d minutes.',
                    $state['consecutive_errors'],
                    $base_min
                ) );
            }
        }

        update_option( self::OPT_POLLING_STATE, $state, false );
    }

    /**
     * The interval (minutes) to fall back to while errors persist.
     *
     * Doubles the configured interval, then rounds up to a schedule that
     * actually exists. Two guarantees the previous version broke: the result is
     * never *shorter* than the configured interval — capping at 60 minutes used
     * to make a 120-minute setting poll twice as often after an error — and it
     * is always a key from self::INTERVALS, so wp_schedule_event() cannot fail
     * silently. Returns $base_minutes unchanged when nothing longer exists.
     *
     * Pure function: no options, no clock. Covered by tests/test-cron-polling.php.
     *
     * @param  int $base_minutes  Configured interval, already normalised.
     * @return int                Backoff interval in minutes.
     */
    public static function backoff_interval( $base_minutes ) {
        $base_minutes = self::normalise_interval( $base_minutes );
        $wanted       = min( $base_minutes * 2, intval( self::MAX_BACKOFF_INTERVAL / MINUTE_IN_SECONDS ) );

        foreach ( self::INTERVALS as $candidate ) {
            if ( $candidate >= $wanted ) {
                return max( $base_minutes, $candidate );
            }
        }
        return max( $base_minutes, self::INTERVALS[ count( self::INTERVALS ) - 1 ] );
    }

    /**
     * Whether a due fetch should be skipped to reach the reduced night rate.
     *
     * Pure function: no options, no clock. Covered by tests/test-cron-polling.php.
     *
     * @param  int  $now           Current timestamp.
     * @param  int  $last_attempt  Timestamp of the last fetch that ran (0 = never).
     * @param  int  $base_seconds  Configured interval in seconds.
     * @param  bool $night         Whether the night window is currently open.
     * @return bool
     */
    public static function should_skip( $now, $last_attempt, $base_seconds, $night ) {
        if ( ! $night || $last_attempt <= 0 ) {
            return false;
        }

        // Skip when less than 1.5x the interval has passed, so every other run
        // survives and the effective interval is doubled. The half-slot margin
        // is deliberate: WP-Cron fires late, and a strict 2x test would let a
        // slightly delayed run through and cancel the reduction.
        return ( $now - $last_attempt ) < ( $base_seconds * 1.5 );
    }

    /**
     * Reset polling state to defaults.
     */
    public static function reset_polling_state() {
        delete_option( self::OPT_POLLING_STATE );
    }

    /** First hour (site-local) of the night window. */
    const NIGHT_START_HOUR = 23;

    /** First hour (site-local) after the night window. */
    const NIGHT_END_HOUR = 6;

    /**
     * Check if we're currently in night mode (reduced polling 23:00–06:00
     * in the site's timezone, the same one wp_date() formats with).
     *
     * @return bool
     */
    public static function is_night_mode() {
        $opts = get_option( 'naws_settings', [] );
        if ( empty( $opts['night_mode'] ) ) {
            return false;
        }

        $hour = intval( ( new DateTimeImmutable( 'now', naws_timezone() ) )->format( 'G' ) );
        return ( $hour >= self::NIGHT_START_HOUR || $hour < self::NIGHT_END_HOUR );
    }

    /**
     * Determine if fetch should be skipped due to night mode.
     * Night mode doubles the interval by skipping every other run.
     *
     * @return bool True if this run should be skipped.
     */
    private static function should_skip_night_mode() {
        $state = self::get_polling_state();

        // Measure from the last *attempt*, not the last success: if the API is
        // failing all night, last_success never moves and the skip would never
        // fire — dropping the load reduction exactly when it matters most.
        return self::should_skip(
            time(),
            intval( $state['last_attempt'] ),
            self::base_interval(),
            self::is_night_mode()
        );
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

        $cid = $options['client_id'] ?? '';
        $cse = $options['client_secret'] ?? '';
        $cid = NAWS_Crypto::is_encrypted( $cid ) ? NAWS_Crypto::decrypt( $cid ) : $cid;
        $cse = NAWS_Crypto::is_encrypted( $cse ) ? NAWS_Crypto::decrypt( $cse ) : $cse;
        if ( empty( $cid ) || empty( $cse ) ) {
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
                $tz        = naws_timezone();
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
        $tz        = naws_timezone();
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
        $base  = self::base_interval();

        // No successful sync ever
        if ( $state['last_success'] === 0 ) {
            return [
                'status'  => 'warning',
                'message' => __( 'No successful sync yet', 'xtx-integration-for-netatmo' ),
            ];
        }

        $since_last = time() - $state['last_success'];

        // Error backoff active
        if ( $state['consecutive_errors'] >= self::ERROR_THRESHOLD ) {
            return [
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: 1: number of consecutive errors, 2: minutes since the last sync. */ __( 'Error backoff: %1$d consecutive errors, last sync %2$d min ago', 'xtx-integration-for-netatmo' ),
                    $state['consecutive_errors'],
                    intval( $since_last / 60 )
                ),
            ];
        }

        // Stale: no sync for > 3x interval. Night mode already runs at 2x, so
        // the threshold scales with it — otherwise one late cron run during the
        // night raises a warning for perfectly normal operation.
        $stale_factor = self::is_night_mode() ? 6 : 3;
        if ( $since_last > $base * $stale_factor ) {
            return [
                'status'  => 'warning',
                'message' => sprintf( /* translators: %d: minutes since the last sync. */ __( 'Warning: last sync %d minutes ago', 'xtx-integration-for-netatmo' ), intval( $since_last / 60 ) ),
            ];
        }

        // Night mode active
        if ( self::is_night_mode() ) {
            return [
                'status'  => 'ok',
                'message' => __( 'Night mode active (reduced polling)', 'xtx-integration-for-netatmo' ),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => sprintf( /* translators: %d: minutes since the last sync. */ __( 'Sync OK (%d min ago)', 'xtx-integration-for-netatmo' ), intval( $since_last / 60 ) ),
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
