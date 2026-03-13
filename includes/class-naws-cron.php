<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Cron {

    const HOOK_FETCH   = 'naws_fetch_data';
    const HOOK_DAILY   = 'naws_daily_summary';

    // Keep backwards compat for code that used the old constant name
    const HOOK = self::HOOK_FETCH;

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
        foreach ( [ 5, 10, 15, 30, 60, 120 ] as $min ) {
            $key = 'naws_' . $min . '_minutes';
            if ( ! isset( $schedules[$key] ) ) {
                $schedules[$key] = [
                    'interval' => $min * MINUTE_IN_SECONDS,
                    'display'  => sprintf( 'Every %d Minutes (Netatmo)', $min ),
                ];
            }
        }

        // Daily at midnight+1 – WordPress cron doesn't support exact times,
        // so we use a 'daily' interval and anchor it correctly on schedule()
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
            // Use DateTimeImmutable with explicit timezone so strtotime() UTC ambiguity is avoided.
            // strtotime( date_i18n(...) ) interprets in server-TZ (UTC) → would fire at 01:01 Berlin.
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
        // Don't reschedule the daily summary – keep it at its midnight anchor
        self::schedule();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::HOOK_FETCH );
        wp_clear_scheduled_hook( self::HOOK_DAILY );
    }

    // ────────────────────────────────────────────────────────────────
    // Fetch callback (runs every N minutes)
    // ────────────────────────────────────────────────────────────────

    public function run_fetch() {
        try {
            $this->do_fetch();
        } catch ( \Throwable $e ) {
            // NEVER let an uncaught exception kill the cron callback.
            // WordPress reschedules BEFORE running the callback, but a fatal
            // during execution can prevent other hooks in the same request.
            $this->log( 'error', 'Uncaught exception: ' . $e->getMessage() );
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
            $this->log( 'error', 'Re-authentication required. Please visit Weather Station → Settings.' );
            return;
        }

        $api    = new NAWS_API();
        $result = $api->sync_current_data();

        if ( is_wp_error( $result ) ) {
            $this->log( 'error', $result->get_error_message() );
        } else {
            $expiry  = (int) get_option( 'naws_token_expiry', 0 );
            $this->log( 'ok', sprintf(
                'Sync OK – %d readings saved. Token valid until %s.',
                intval( $result ),
                $expiry ? wp_date('H:i', $expiry ) : '?'
            ) );
        }

        $today     = date_i18n( 'Y-m-d' );
        $yesterday = date_i18n( 'Y-m-d', strtotime( 'yesterday' ) );

        // ── Running summary for today (updated on every fetch) ────────────────
        // This ensures today's min/max/avg are always current so the 00:01
        // cron is no longer a single point of failure.
        try {
            NAWS_Database::compute_daily_summary( $today );
        } catch ( \Throwable $e ) {
            $this->log( 'error', 'Running summary (today) failed: ' . $e->getMessage() );
        }

        // ── Catchup: ensure yesterday's final summary exists ──────────────────
        // Runs if yesterday was not yet finalized (e.g. 00:01 cron was missed).
        // Uses the Importer (Netatmo API) directly – same as manual History Import.
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

    /**
     * Fetches yesterday's hourly data directly from the Netatmo API
     * via getmeasure – identical to the manual History Import.
     * This is independent of naws_readings (which only holds live snapshots)
     * and produces the same reliable min/max/avg values as a manual import.
     */
    public function run_daily_summary() {
        $tz        = new DateTimeZone( 'Europe/Berlin' );
        $yesterday = date_i18n( 'Y-m-d', strtotime( 'yesterday' ) );

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
