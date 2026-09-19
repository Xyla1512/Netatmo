<?php
/**
 * Tests for the automatic retention of raw readings (since 2.1.0).
 *
 * The settings page had promised "all data is stored permanently" while the
 * dashboard sidebar showed a "Data Retention: 365" that nothing applied —
 * the value had no field and no effect. Retention is a real, switchable
 * thing now: once a night, after the daily summary, the cron deletes raw
 * readings older than the configured number of days — but only while the
 * switch is on. The daily table is never touched.
 *
 * What the cases guard:
 *
 *   NAWS_Helpers::retention_days() – the one place that reads the two
 *                          settings: null while the switch is off, else the
 *                          number of days, never below 30, 365 when unset.
 *   NAWS_Cron::run_retention()     – does nothing while off; while on it
 *                          purges, remembers the run and logs it.
 *   NAWS_Cron::run_daily_summary() – actually calls the retention, so the
 *                          nightly run is the only place readings vanish.
 *   The views              – the settings page carries the switch and the
 *                          field, the old promise is gone, the sidebar
 *                          shows "off" or the days, not a bare number.
 *
 * Runs without a WordPress bootstrap and without a database.
 *
 *   php tests/test-retention.php
 *
 * @package NAWS
 * @since   2.1.0
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

// ── Minimal WordPress surface ────────────────────────────────────────────
$GLOBALS['naws_test_options'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function do_action( ...$a ) {}
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? time() ); return $d->format( $fmt ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
class WP_Error {}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
require_once __DIR__ . '/i18n-stubs.php';

/** Records what the cron asks the database to do. */
class NAWS_Database {
    public static $purge_calls  = [];
    public static $purge_result = 0;
    public static $flushed      = 0;
    public static function purge_old_readings( $days ) { self::$purge_calls[] = $days; return self::$purge_result; }
    public static function flush_caches() { self::$flushed++; }
}
/** The daily summary asks the importer for jobs; none keeps the run short. */
class NAWS_Importer {
    public static function build_job_list( $from, $to ) { return []; }
}
class NAWS_Logger {
    public static function __callStatic( $name, $args ) {}
}

require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-cron.php';

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

// ── retention_days(): der Leser der beiden Einstellungen ─────────────────
echo "\nNAWS_Helpers::retention_days()\n" . str_repeat( '-', 74 ) . "\n";

check( 'ohne Einstellungen: aus',                       NAWS_Helpers::retention_days( [] ), null );
check( 'Schalter 0: aus, auch mit Tagen',               NAWS_Helpers::retention_days( [ 'retention_enabled' => 0, 'data_retention' => 90 ] ), null );
check( 'Schalter "0" (hidden-zero): aus',               NAWS_Helpers::retention_days( [ 'retention_enabled' => '0' ] ), null );
check( 'Schalter an ohne Tage: Vorgabe 365',            NAWS_Helpers::retention_days( [ 'retention_enabled' => 1 ] ), 365 );
check( 'Schalter "1" mit 90 Tagen',                     NAWS_Helpers::retention_days( [ 'retention_enabled' => '1', 'data_retention' => 90 ] ), 90 );
check( 'Tage als String',                               NAWS_Helpers::retention_days( [ 'retention_enabled' => 1, 'data_retention' => '400' ] ), 400 );
check( 'unter 30 -> Untergrenze 30',                    NAWS_Helpers::retention_days( [ 'retention_enabled' => 1, 'data_retention' => 10 ] ), 30 );
check( 'genau 30 bleibt',                               NAWS_Helpers::retention_days( [ 'retention_enabled' => 1, 'data_retention' => 30 ] ), 30 );
check( '0 Tage -> Vorgabe 365',                         NAWS_Helpers::retention_days( [ 'retention_enabled' => 1, 'data_retention' => 0 ] ), 365 );
check( 'Unsinn -> Vorgabe 365',                         NAWS_Helpers::retention_days( [ 'retention_enabled' => 1, 'data_retention' => 'oft' ] ), 365 );
check( 'negativ -> Vorgabe 365',                        NAWS_Helpers::retention_days( [ 'retention_enabled' => 1, 'data_retention' => -5 ] ), 365 );

$GLOBALS['naws_test_options']['naws_settings'] = [ 'retention_enabled' => 1, 'data_retention' => 120 ];
check( 'ohne Argument: liest naws_settings',            NAWS_Helpers::retention_days(), 120 );
$GLOBALS['naws_test_options']['naws_settings'] = [];
check( 'ohne Argument, leere Einstellungen: aus',       NAWS_Helpers::retention_days(), null );

// ── run_retention(): nichts, solange der Schalter aus ist ────────────────
echo "\nNAWS_Cron::run_retention()\n" . str_repeat( '-', 74 ) . "\n";

$cron = NAWS_Cron::instance();

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'data_retention' => 90 ] ];
NAWS_Database::$purge_calls   = [];
NAWS_Database::$purge_result  = 77;
check( 'aus: gibt null zurueck',                        $cron->run_retention(), null );
check( 'aus: loescht nichts',                           NAWS_Database::$purge_calls, [] );
check( 'aus: merkt sich keinen Lauf',                   get_option( 'naws_last_retention' ), false );
check( 'aus: schreibt kein Protokoll',                  get_option( 'naws_cron_log', [] ), [] );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'retention_enabled' => 1, 'data_retention' => 90 ] ];
NAWS_Database::$purge_calls   = [];
NAWS_Database::$purge_result  = 1234;
$before  = time();
$deleted = $cron->run_retention();
$after   = time();
check( 'an: gibt die Zahl der geloeschten Zeilen zurueck', $deleted, 1234 );
check( 'an: loescht genau einmal mit den eingestellten Tagen', NAWS_Database::$purge_calls, [ 90 ] );
$last = get_option( 'naws_last_retention' );
check( 'an: merkt sich Tage und Zeilen',                [ $last['days'] ?? null, $last['deleted'] ?? null ], [ 90, 1234 ] );
check( 'an: merkt sich den Zeitpunkt',                  is_int( $last['time'] ?? null ) && $last['time'] >= $before && $last['time'] <= $after, true );
$log = get_option( 'naws_cron_log', [] );
check( 'an: eine Protokollzeile mit Status daily',      [ count( $log ), $log[0]['status'] ?? null ], [ 1, 'daily' ] );
check( 'an: die Zeile nennt Zeilen und Tage',           str_contains( $log[0]['message'] ?? '', '1234' ) && str_contains( $log[0]['message'] ?? '', '90' ), true );

// Der Merker der Rohwerte-Abfrage (rain ticks) bleibt unberuehrt: nur die
// beiden Optionen oben duerfen sich aendern.
check( 'an: fasst keine anderen Optionen an',           array_keys( $GLOBALS['naws_test_options'] ), [ 'naws_settings', 'naws_last_retention', 'naws_cron_log' ] );

// Untergrenze auch im Lauf: 10 Tage eingestellt -> 30 Tage geloescht.
$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'retention_enabled' => 1, 'data_retention' => 10 ] ];
NAWS_Database::$purge_calls   = [];
$cron->run_retention();
check( 'an: nie unter 30 Tage',                         NAWS_Database::$purge_calls, [ 30 ] );

// Ein fehlgeschlagenes DELETE ($wpdb->query() gibt false) darf nicht als
// "0 geloescht" durchgehen: Fehler ins Protokoll, der Merker bleibt.
$GLOBALS['naws_test_options'] = [
    'naws_settings'       => [ 'retention_enabled' => 1, 'data_retention' => 90 ],
    'naws_last_retention' => [ 'time' => 1000, 'days' => 90, 'deleted' => 3 ],
];
NAWS_Database::$purge_calls   = [];
NAWS_Database::$purge_result  = false;
check( 'Fehler: gibt 0 zurueck',                        $cron->run_retention(), 0 );
check( 'Fehler: Protokoll mit Status error',            get_option( 'naws_cron_log' )[0]['status'] ?? null, 'error' );
check( 'Fehler: Merker des letzten Laufs bleibt',       get_option( 'naws_last_retention' )['time'] ?? null, 1000 );
NAWS_Database::$purge_result  = 0;

// ── run_daily_summary(): der naechtliche Lauf ruft die Aufbewahrung ──────
echo "\nNAWS_Cron::run_daily_summary() ruft die Aufbewahrung\n" . str_repeat( '-', 74 ) . "\n";

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'retention_enabled' => 1, 'data_retention' => 60 ] ];
NAWS_Database::$purge_calls   = [];
NAWS_Database::$purge_result  = 5;
NAWS_Database::$flushed       = 0;
$cron->run_daily_summary();
check( 'Tageslauf loescht mit den eingestellten Tagen', NAWS_Database::$purge_calls, [ 60 ] );
check( 'Tageslauf leert die Caches danach',             NAWS_Database::$flushed >= 1, true );
check( 'Tageslauf merkt sich den Lauf',                 get_option( 'naws_last_retention' )['deleted'] ?? null, 5 );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'data_retention' => 60 ] ];
NAWS_Database::$purge_calls   = [];
$cron->run_daily_summary();
check( 'Tageslauf mit Schalter aus loescht nichts',     NAWS_Database::$purge_calls, [] );

// ── Die Ansichten ────────────────────────────────────────────────────────
echo "\nEinstellungsseite und Dashboard\n" . str_repeat( '-', 74 ) . "\n";

$settings  = str_replace( "\r\n", "\n", (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/settings.php' ) );
$dashboard = str_replace( "\r\n", "\n", (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/dashboard.php' ) );

check( 'Schalter als hidden-zero + checkbox',
    str_contains( $settings, '<input type="hidden" name="naws_settings[retention_enabled]" value="0">' )
    && str_contains( $settings, '<input type="checkbox" name="naws_settings[retention_enabled]" value="1"' ), true );
check( 'Tagesfeld mit Untergrenze 30',
    (bool) preg_match( '/<input type="number"[^>]*name="naws_settings\[data_retention\]"[^>]*min="30"/', $settings ), true );
check( 'das alte Versprechen ist weg',
    str_contains( $settings, 'All data is stored permanently' ) || str_contains( $settings, 'No automatic deletion' ), false );
check( 'Bereinigen-Knopf nimmt die eingestellten Tage als Vorgabe',
    (bool) preg_match( '/id="naws-purge-days"[^>]*value="<\?php/', $settings ), true );
// Frank, 19.09.: ein eigener Abschnitt, damit die Datenhaltung hervorsticht —
// nicht eine Zeile zwischen Nachtmodus und Heizgrenze im Panel "Operation".
check( 'eigenes Panel mit Kopfzeile "Data Retention"',
    (bool) preg_match( '/<div class="naws-panel-header"><h2><\?php esc_html_e\( \'Data Retention\'/', $settings ), true );
check( 'Zeile "Automatic deletion" im eigenen Panel',
    str_contains( $settings, "<th><?php esc_html_e( 'Automatic deletion', 'xtx-integration-for-netatmo' ); ?></th>" ), true );
check( 'Zeile "Manual purge" im eigenen Panel',
    str_contains( $settings, "<th><?php esc_html_e( 'Manual purge', 'xtx-integration-for-netatmo' ); ?></th>" ), true );
check( 'Datenhaltung ist keine Zeile mehr im Panel "Operation"',
    (bool) preg_match( '/Operation.*?<th><\?php esc_html_e\( \'Data Retention\'.*?<h2><\?php esc_html_e\( \'Units\'/s', $settings ), false );
check( 'Seitenleiste zeigt nicht mehr die nackte Zahl',
    str_contains( $dashboard, "\$options['data_retention'] ?? 365" ), false );
check( 'Seitenleiste fragt den Helfer',
    str_contains( $dashboard, 'NAWS_Helpers::retention_days(' ), true );

// ── Summary ──────────────────────────────────────────────────────────────
echo "\n" . str_repeat( '=', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
