<?php
/**
 * Tests: ein Abruf, dessen Daten die Datenbank nicht annimmt, ist kein Erfolg.
 *
 * Gemeldet am 2026-10-19 von einem Nutzer: Das Cron-Log zeigte alle zehn
 * Minuten "Sync OK – 0 readings saved", das Dashboard 0 Module und 0 MB,
 * daneben "184 Fehler in der letzten Stunde". Netatmo lieferte die Station,
 * aber save_module() und bulk_insert_readings() scheiterten an der Datenbank.
 * sync_current_data() sah nur auf die Antwort von Netatmo und gab trotzdem
 * die Zahl 0 zurueck — fuer Cron und Knopf ein Erfolg.
 *
 * Ebenso eine leere Geraeteliste: ohne Station kann das Plugin nichts zeigen,
 * und "Sync OK" laesst den Nutzer im Glauben, es fehle nur Geduld.
 *
 *   php tests/test-sync-db-errors.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// ── WordPress-Stubs ──────────────────────────────────────────────────────
function get_option( $k, $d = false )        { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null )  { $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function delete_transient( $k )              { return true; }
function is_wp_error( $x )                   { return $x instanceof WP_Error; }
class WP_Error {
    private $code;
    private $msg;
    public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->msg = $msg; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->msg; }
}

// ── Plugin-Stubs ─────────────────────────────────────────────────────────
class NAWS_Crypto {
    public static function is_encrypted( $v ) { return false; }
    public static function decrypt( $v ) { return $v; }
    public static function get_option( $k, $d = '' ) { return $d; }
}
class NAWS_Logger {
    public static function error( $c, $m, $x = [] ) {}
    public static function warning( $c, $m, $x = [] ) {}
    public static function info( $c, $m, $x = [] ) {}
}
class NAWS_Database {
    const CACHE_PREFIX = 'naws_cache_';
    public static $module_error = '';
    public static $insert_error = '';
    private static $last_error  = '';
    public static function last_error(): string { return self::$last_error; }
    public static function is_module_active( $id ) { return true; }
    public static function save_module( $m ) {
        if ( self::$module_error !== '' ) {
            return new WP_Error( 'db_error', 'Failed to save module: ' . self::$module_error );
        }
        return true;
    }
    public static function bulk_insert_readings( $rows ) {
        self::$last_error = self::$insert_error;
        return self::$insert_error === '' ? count( $rows ) : 0;
    }
}

require_once __DIR__ . '/../includes/class-naws-api.php';

class Fake_API extends NAWS_API {
    public static $devices = [];
    public function get_stations_data( $_refreshed = false ) { return self::$devices; }
    public function get_measure( $device_id, $module_id, $types, $date_begin, $date_end, $scale = '30min', $optimize = false, $limit = 1024, $real_time = false, $_retry = false ) {
        return [];
    }
}

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

const STATION = [
    '_id'            => '70:ee:50:a9:5a:08',
    'type'           => 'NAMain',
    'dashboard_data' => [ 'time_utc' => 1789567474, 'Pressure' => 1013.2, 'Temperature' => 21.5 ],
    'modules'        => [
        [ '_id' => '02:00:00:a9:85:54', 'type' => 'NAModule1', 'dashboard_data' => [ 'time_utc' => 1789567462, 'Temperature' => 12.3 ] ],
    ],
];

function run_sync( array $devices, string $module_error = '', string $insert_error = '' ) {
    $GLOBALS['naws_test_options']  = [ 'naws_settings' => [] ];
    NAWS_Database::$module_error   = $module_error;
    NAWS_Database::$insert_error   = $insert_error;
    Fake_API::$devices             = $devices;
    return ( new Fake_API() )->sync_current_data();
}

$missing = "Table 'wp_naws_modules' doesn't exist";

// ── 1. Alles in Ordnung: Zahl der gespeicherten Messwerte ────────────────
$r = run_sync( [ STATION ] );
check( 'ok: Zahl statt Fehler', $r, 3 );
check( 'ok: kein Fehlertext gemerkt', get_option( 'naws_last_sync_error' ), '' );

// ── 2. Module lassen sich nicht speichern (Kais Fall) ────────────────────
$r = run_sync( [ STATION ], $missing, "Table 'wp_naws_readings' doesn't exist" );
check( 'Modul-Fehler: WP_Error', is_wp_error( $r ), true );
check( 'Modul-Fehler: Code db_error', is_wp_error( $r ) ? $r->get_error_code() : null, 'db_error' );
check( 'Modul-Fehler: Datenbanktext in der Meldung', is_wp_error( $r ) && strpos( $r->get_error_message(), $missing ) !== false, true );
check( 'Modul-Fehler: als letzter Fehler gemerkt', is_wp_error( $r ) ? get_option( 'naws_last_sync_error' ) : null, is_wp_error( $r ) ? $r->get_error_message() : 'x' );
check( 'Modul-Fehler: kein Erfolgszeitpunkt', get_option( 'naws_last_sync', 'unberuehrt' ), 'unberuehrt' );

// ── 3. Module gehen, Messwerte nicht ─────────────────────────────────────
$insert = "Unknown column 'parameter' in 'field list'";
$r = run_sync( [ STATION ], '', $insert );
check( 'Messwert-Fehler: WP_Error', is_wp_error( $r ), true );
check( 'Messwert-Fehler: Datenbanktext in der Meldung', is_wp_error( $r ) && strpos( $r->get_error_message(), $insert ) !== false, true );

// ── 4. Netatmo liefert keine Station ─────────────────────────────────────
$r = run_sync( [] );
check( 'keine Station: WP_Error', is_wp_error( $r ), true );
check( 'keine Station: Code no_devices', is_wp_error( $r ) ? $r->get_error_code() : null, 'no_devices' );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
