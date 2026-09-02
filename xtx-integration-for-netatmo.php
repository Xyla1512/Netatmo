<?php
/**
 * Plugin Name: XTX Integration for Netatmo
 * Plugin URI: https://netatmo.frank-neumann.de/
 * Description: Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, charts, history and forecasts via shortcodes.
 * Version: 1.9.11-dev
 * Author: Frank Neumann
 * Author URI: https://frank-neumann.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xtx-integration-for-netatmo
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 7.1
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NAWS_VERSION',        '1.9.11-dev' );
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
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-logger.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-labels.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-crypto.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-helpers.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-database.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-api.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-importer-v2.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-cron.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-astro.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-calc.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-climate.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-forecast.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-fonts.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-colors.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-icons.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-weather-state.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-weather-icons.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-widget-data.php' );
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-export.php' );

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
final class NAWS_Plugin {

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

        add_action( 'init', [ $this, 'load_textdomain' ] );
    }

    /**
     * Load translations: the language pack first, the shipped copies after.
     *
     * Both are loaded, and the order is the whole point.
     *
     * WordPress.org builds a pack for every locale, and it is the one that
     * should win — it carries what translators approved most recently. But on
     * the day this release goes out that pack holds almost nothing: the
     * strings are new and nobody has translated them yet. A German site would
     * find an English interface where it had a German one yesterday.
     *
     * load_plugin_textdomain() stops as soon as it finds a pack and never
     * looks at the plugin's own directory, so it cannot bridge that on its
     * own. Loading the shipped copy afterwards does: load_textdomain() keeps
     * the entries already in memory, so every string the pack carries stays
     * exactly as the pack has it, and the bundled file answers only where the
     * pack is silent. As the packs fill up, these files stop being consulted
     * and can eventually go.
     */
    public function load_textdomain() {
        $domain = 'xtx-integration-for-netatmo';

        load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        $mofile = NAWS_PLUGIN_DIR . 'languages/' . $domain . '-' . determine_locale() . '.mo';
        if ( is_readable( $mofile ) ) {
            load_textdomain( $domain, $mofile );
        }
    }

    public function init() {
        // ── Schema: die eine Migration, die ausserhalb des Backends laufen muss ──
        //
        // register_activation_hook() feuert bei einem Update NICHT. WordPress
        // reaktiviert das Plugin stumm, und der Kern sagt das in eigenen Worten:
        // "If a plugin is silently activated (such as during an update), this
        // hook does not fire." NAWS_Database::install() - und darin dbDelta()
        // und maybe_migrate() - laeuft also fuer niemanden, der ueber das
        // Verzeichnis aktualisiert. Seit das Plugin dort liegt, ist genau das
        // der gewoehnliche Weg.
        //
        // Die Pruefung kostet einen Vergleich gegen eine autogeladene Option.
        // Sie steht vor allem anderen in dieser Methode, weil jede Zeile
        // darunter die Tabellen abfragt, und sie steht bewusst NICHT in einem
        // is_admin()-Zweig: eine Seite, deren Backend niemand aufruft, zeigt
        // trotzdem Shortcodes an, und ein Shortcode, der eine noch nicht
        // existierende Spalte liest, ist genau der Fehler, den das hier
        // verhindert.
        //
        // Zwei gleichzeitige Anfragen koennen beide hineinlaufen. Das ist
        // hingenommen: dbDelta() und maybe_migrate() sind wiederholbar, und
        // eine Sperre haette den schlechteren Ausfall - eine haengengebliebene
        // Sperre verhinderte die Migration dauerhaft.
        if ( get_option( 'naws_db_version' ) !== NAWS_DB_VERSION ) {
            NAWS_Database::install();
        }

        // Increase cURL connect timeout for Netatmo API calls.
        // Some hosting DNS resolvers are slow; the default 10s connect timeout
        // causes "Resolving timed out" errors during cron syncs.
        add_action( 'http_api_curl', [ $this, 'adjust_curl_timeout' ], 10, 3 );

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
                // ── Encrypt all plaintext secrets (one-time migration) ───────
                if ( get_option( 'naws_crypto_migrated' ) !== NAWS_VERSION ) {
                    NAWS_Crypto::migrate();
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
                $params = [ 'time_utc', 'date_min_temp', 'date_max_temp', 'date_min_pressure', 'date_max_pressure', 'date_max_wind_str', 'date_max_gust' ];
                $placeholders = implode( ', ', array_fill( 0, count( $params ), '%s' ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE parameter IN ({$placeholders})", $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name from constant; placeholders built dynamically from fixed array
                update_option( 'naws_cleanup_timestamp_readings', true, false );
            }
        }

    }

    /**
     * Increase cURL connect timeout for Netatmo & forecast API requests.
     *
     * @param resource $handle  cURL handle.
     * @param array    $parsed  Parsed request arguments.
     * @param string   $url     Request URL.
     */
    public function adjust_curl_timeout( $handle, $parsed, $url ) {
        $domains = [ 'api.netatmo.com', 'api.open-meteo.com', 'api.met.no' ];
        $host    = wp_parse_url( $url, PHP_URL_HOST );

        if ( in_array( $host, $domains, true ) ) {
            curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 30 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Required to set cURL connect timeout via http_api_curl hook; no WP-native alternative exists
        }
    }
}

function naws() {
    return NAWS_Plugin::instance();
}
naws();
