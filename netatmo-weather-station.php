<?php
/**
 * Plugin Name: Netatmo Weather Station
 * Plugin URI: https://frank-neumann.de/netatmo-weather-station
 * Description: Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, charts, history and forecasts via shortcodes.
 * Version: 1.0.2
 * Author: Frank Neumann
 * Author URI: https://frank-neumann.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: netatmo-weather-station
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NAWS_VERSION',        '1.0.2' );
define( 'NAWS_PLUGIN_FILE',    __FILE__ );
define( 'NAWS_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'NAWS_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'NAWS_PLUGIN_BASENAME',plugin_basename( __FILE__ ) );
define( 'NAWS_DB_VERSION',     '1.4' );
define( 'NAWS_TABLE_READINGS', 'naws_readings' );
define( 'NAWS_TABLE_MODULES',  'naws_modules' );
define( 'NAWS_TABLE_DAILY',    'naws_daily_summary' );

// ── Safe require helper ────────────────────────────────────────────────────
function naws_require( $file ) {
    if ( file_exists( $file ) ) {
        require_once $file;
    } else {
        // Log missing file, don't crash
        error_log( 'NAWS: Missing file ' . $file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}

// ── Core classes (always needed) ──────────────────────────────────────────
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-lang.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-crypto.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-helpers.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-database.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-api.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-importer-v2.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-cron.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-astro.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-forecast.php' );

// ── Admin classes (only in admin context) ─────────────────────────────────
if ( is_admin() ) {
    naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-admin.php' );
}

// ── Frontend / shortcode classes ──────────────────────────────────────────
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-shortcodes.php' );

// ── AJAX (admin-ajax.php runs in admin context but serves frontend too) ───
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-ajax.php' );

// ── REST API (always loaded – routes registered on rest_api_init) ─────────
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-rest-api.php' );


/**
 * Main plugin bootstrap
 */
final class Netatmo_Weather_Station {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( NAWS_PLUGIN_FILE,   [ 'NAWS_Database', 'install' ] );
        // Note: NAWS_Cron::schedule() is NOT called here because custom intervals
        // (add_schedules filter) aren't registered yet during activation.
        // The watchdog in init() handles scheduling on first run.
        register_deactivation_hook( NAWS_PLUGIN_FILE, [ 'NAWS_Cron', 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        // Always boot cron and shortcodes
        NAWS_Cron::instance();
        NAWS_Shortcodes::instance();
        NAWS_Ajax::instance();
        NAWS_Rest_API::init();

        // ── Cron watchdog: schedule if missing OR stale ─────────────────────
        $next_fetch = wp_next_scheduled( NAWS_Cron::HOOK_FETCH );
        $next_daily = wp_next_scheduled( NAWS_Cron::HOOK_DAILY );
        $opts       = get_option( 'naws_settings', [] );
        $cron_min   = max( 5, intval( $opts['cron_interval'] ?? 10 ) );
        $cron_sec   = $cron_min * MINUTE_IN_SECONDS;
        $needs_sync = false;

        // Fetch event missing or stale → schedule in the future + sync now
        if ( ! $next_fetch ) {
            wp_schedule_event( time() + $cron_sec, 'naws_' . $cron_min . '_minutes', NAWS_Cron::HOOK_FETCH );
            $needs_sync = true;
        } elseif ( ( time() - $next_fetch ) > $cron_sec * 2 ) {
            wp_clear_scheduled_hook( NAWS_Cron::HOOK_FETCH );
            wp_schedule_event( time() + $cron_sec, 'naws_' . $cron_min . '_minutes', NAWS_Cron::HOOK_FETCH );
            $needs_sync = true;
        }

        // Daily event missing → schedule it (NAWS_Cron::schedule handles daily only if missing)
        if ( ! $next_daily ) {
            NAWS_Cron::schedule();
        }

        if ( $needs_sync && ! defined( 'NAWS_WATCHDOG_SYNC_DONE' ) ) {
            define( 'NAWS_WATCHDOG_SYNC_DONE', true );
            NAWS_Cron::instance()->run_fetch();
        }

        // Admin only
        if ( is_admin() ) {
            NAWS_Admin::instance();

            try {
                // ── Encrypt tokens + decrypt client credentials (one-time) ───────
                if ( get_option( 'naws_crypto_migrated' ) !== NAWS_VERSION ) {
                    NAWS_Crypto::migrate();
                }
                // ── v2 fix: ensure client_id/secret are plaintext, not encrypted ──
                $s = get_option( 'naws_settings', [] );
                if ( is_array( $s ) ) {
                    $changed = false;
                    foreach ( [ 'client_id', 'client_secret' ] as $k ) {
                        if ( isset( $s[ $k ] ) && is_string( $s[ $k ] ) && NAWS_Crypto::is_encrypted( $s[ $k ] ) ) {
                            $s[ $k ] = NAWS_Crypto::decrypt( $s[ $k ] );
                            $changed = true;
                        }
                    }
                    if ( $changed ) {
                        update_option( 'naws_settings', $s );
                        delete_option( 'naws_auth_required' );
                    }
                }
            } catch ( \Throwable $e ) {
                error_log( 'NAWS migration error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }

            // ── One-time cleanup: remove stale timestamp readings from DB ─────
            // Before v0.9.93, date_min_temp / date_max_temp etc. were saved as
            // sensor readings (huge integers). Delete them once, then flag done.
            if ( ! get_option( 'naws_cleanup_timestamp_readings' ) ) {
                global $wpdb;
                $table = $wpdb->prefix . NAWS_TABLE_READINGS;
                $wpdb->query( "DELETE FROM {$table} WHERE parameter IN (
                    'time_utc','date_min_temp','date_max_temp',
                    'date_min_pressure','date_max_pressure',
                    'date_max_wind_str','date_max_gust'
                )" );
                update_option( 'naws_cleanup_timestamp_readings', true, false );
            }
        }

    }
}

function naws() {
    return Netatmo_Weather_Station::instance();
}
naws();
