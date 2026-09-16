<?php
/**
 * Tests fuer das Schliessen der Luecken in der Regenreihe.
 *
 * Gemessen auf der Produktinstallation am 2026-09-16, Regenmesser
 * 05:00:00:0a:a6:46, Abruf alle zehn Minuten:
 *
 *   getmeasure scale=max, type=Rain, heute:   186 Meldungen im Abstand von
 *                                             295-308 s, Summe 3,939 mm
 *   getstationsdata sum_rain_24:              3,9 mm (die Netatmo-App zeigt dasselbe)
 *   naws_readings, parameter=Rain, 24 h:      133 Zeilen im Abstand von ~600 s,
 *                                             Summe 1,616 mm -> Kachel "24h: 1,6 mm"
 *
 * Der Regenmesser meldet alle fuenf Minuten, und jede Meldung traegt den Regen
 * dieser fuenf Minuten. dashboard_data zeigt nur die neueste Meldung; ein Abruf
 * alle zehn Minuten speichert also jede zweite, und die rollende 24-h-Summe aus
 * der Tabelle lag bei weniger als der Haelfte dessen, was gefallen ist.
 *
 * NAWS_API::fill_rain_ticks() holt nach jedem Abruf die fehlenden Meldungen per
 * getmeasure (scale=max) nach. Die Zeitstempel dort sind dieselben wie time_utc
 * im Dashboard (16:04:34 = 1789567474 in beiden), der eindeutige Schluessel der
 * Tabelle faltet beide Quellen zu einer Reihe. Eine Option merkt sich je
 * Regenmesser, bis wohin die Reihe vollstaendig ist.
 *
 *   php tests/test-rain-ticks.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// ── WordPress-Stubs ──────────────────────────────────────────────────────
function get_option( $k, $d = false )        { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null )  { $GLOBALS['naws_test_options'][ $k ] = $v; $GLOBALS['naws_test_autoload'][ $k ] = $a; return true; }
function delete_transient( $k )              { $GLOBALS['naws_test_deleted'][] = $k; return true; }
function is_wp_error( $x )                   { return $x instanceof WP_Error; }
class WP_Error {
    private $msg;
    public function __construct( $code = '', $msg = '' ) { $this->msg = $msg; }
    public function get_error_message() { return $this->msg; }
}

// ── Plugin-Stubs ─────────────────────────────────────────────────────────
class NAWS_Crypto {
    public static function is_encrypted( $v ) { return false; }
    public static function decrypt( $v ) { return $v; }
    public static function get_option( $k, $d = '' ) { return $d; }
}
class NAWS_Logger {
    public static $warnings = [];
    public static function error( $c, $m, $x = [] ) {}
    public static function warning( $c, $m, $x = [] ) { self::$warnings[] = $m; }
    public static function info( $c, $m, $x = [] ) {}
}
class NAWS_Database {
    const CACHE_PREFIX = 'naws_cache_';
    public static $rows     = [];
    public static $inactive = [];
    public static function is_module_active( $id ) { return ! in_array( $id, self::$inactive, true ); }
    public static function save_module( $m ) {}
    public static function bulk_insert_readings( $rows ) {
        foreach ( $rows as $r ) { self::$rows[] = $r; }
        return count( $rows );
    }
}

require_once __DIR__ . '/../includes/class-naws-api.php';

// Die Klasse unter Test, mit Netatmo abgeklemmt: Stationsdaten und
// getmeasure-Antwort kommen aus dem Test, jeder getmeasure-Aufruf wird notiert.
class Fake_API extends NAWS_API {
    public static $devices = [];
    public static $measure = [];
    public static $calls   = [];
    public function get_stations_data( $_refreshed = false ) { return self::$devices; }
    public function get_measure( $device_id, $module_id, $types, $date_begin, $date_end, $scale = '30min', $optimize = false, $limit = 1024, $real_time = false, $_retry = false ) {
        self::$calls[] = compact( 'device_id', 'module_id', 'types', 'date_begin', 'date_end', 'scale', 'limit' );
        return self::$measure;
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

// ── Die gemessene Lage ───────────────────────────────────────────────────
const DEVICE = '70:ee:50:a9:5a:08';
const RAIN   = '05:00:00:0a:a6:46';
const OUTDOOR = '02:00:00:a9:85:54';
const TICK   = 1789567474; // 16:04:34 — die neueste Meldung, im Dashboard und in getmeasure
const PREV   = 1789566858; // 15:54:18 — die Meldung des vorigen Abrufs
const MISSED = 1789567166; // 15:59:26 — die Meldung dazwischen, die das Dashboard nie zeigte

const WET_HOUR = [ 'time_utc' => TICK, 'Rain' => 0.404, 'sum_rain_1' => 1.515, 'sum_rain_24' => 3.9 ];
const DRY_HOUR = [ 'time_utc' => TICK, 'Rain' => 0,     'sum_rain_1' => 0,     'sum_rain_24' => 3.9 ];
const MEASURED = [ PREV => [ 0 ], MISSED => [ 0.606 ], TICK => [ 0.404 ] ];

/**
 * Ein Cron-Abruf: Basis + Aussenmodul + Regenmesser, Regenmesser-Dashboard und
 * getmeasure-Antwort aus dem Test. Liefert, was danach zu sehen ist.
 */
function run_sync( array $rain_dash, $measure, $marks = null, bool $active = true ): array {
    $GLOBALS['naws_test_options']  = [ 'naws_settings' => [] ];
    $GLOBALS['naws_test_autoload'] = [];
    $GLOBALS['naws_test_deleted']  = [];
    if ( $marks !== null ) {
        $GLOBALS['naws_test_options'][ NAWS_API::OPT_RAIN_TICKS ] = $marks;
    }
    NAWS_Database::$rows     = [];
    NAWS_Database::$inactive = $active ? [] : [ RAIN ];
    NAWS_Logger::$warnings   = [];
    Fake_API::$calls   = [];
    Fake_API::$measure = $measure;
    Fake_API::$devices = [ [
        '_id'            => DEVICE,
        'type'           => 'NAMain',
        'dashboard_data' => [ 'time_utc' => TICK, 'Pressure' => 1013.2 ],
        'modules'        => [
            [ '_id' => OUTDOOR, 'type' => 'NAModule1', 'dashboard_data' => [ 'time_utc' => TICK - 12, 'Temperature' => 12.3, 'Humidity' => 80 ] ],
            [ '_id' => RAIN,    'type' => 'NAModule3', 'dashboard_data' => $rain_dash ],
        ],
    ] ];

    $api    = new Fake_API();
    $result = $api->sync_current_data();

    $rain = [];
    foreach ( NAWS_Database::$rows as $r ) {
        if ( $r['module_id'] === RAIN && $r['parameter'] === 'Rain' ) {
            $rain[ (int) $r['recorded_at'] ] = (float) $r['value'];
        }
    }
    ksort( $rain );

    return [
        'result'   => $result,
        'calls'    => Fake_API::$calls,
        'rain'     => $rain,
        'rows'     => NAWS_Database::$rows,
        'marks'    => $GLOBALS['naws_test_options'][ NAWS_API::OPT_RAIN_TICKS ] ?? null,
        'autoload' => $GLOBALS['naws_test_autoload'][ NAWS_API::OPT_RAIN_TICKS ] ?? 'nicht geschrieben',
        'deleted'  => $GLOBALS['naws_test_deleted'],
        'warnings' => NAWS_Logger::$warnings,
    ];
}

echo "\nRegenmesser: Luecken in der Regenreihe\n" . str_repeat( '-', 74 ) . "\n";

// ── Der gemessene Fall: nasse Stunde, Abruf alle zehn Minuten ────────────
$r = run_sync( WET_HOUR, MEASURED, [ RAIN => PREV ] );
check( 'nasse Stunde: genau ein getmeasure-Aufruf', count( $r['calls'] ), 1 );
check( 'der Aufruf gilt dem Regenmesser der Station', [ $r['calls'][0]['device_id'] ?? null, $r['calls'][0]['module_id'] ?? null ], [ DEVICE, RAIN ] );
check( 'der Aufruf fragt Rain in scale=max', [ $r['calls'][0]['types'] ?? null, $r['calls'][0]['scale'] ?? null ], [ [ 'Rain' ], 'max' ] );
check( 'das Fenster reicht von der vorigen bis zur neuesten Meldung', [ $r['calls'][0]['date_begin'] ?? null, $r['calls'][0]['date_end'] ?? null ], [ PREV, TICK ] );
check( 'die Meldung, die das Dashboard nie zeigte, steht jetzt in der Reihe', $r['rain'][ MISSED ] ?? null, 0.606 );
check( 'die Reihe der Stunde ist vollstaendig', array_keys( $r['rain'] ), [ PREV, MISSED, TICK ] );
check( 'die Meldungen liegen unter der Station des Regenmessers', array_values( array_unique( array_map( fn( $x ) => $x['station_id'], array_filter( $r['rows'], fn( $x ) => $x['module_id'] === RAIN ) ) ) ), [ DEVICE ] );
check( 'die Reihe gilt als vollstaendig bis zur neuesten Meldung', $r['marks'][ RAIN ] ?? null, TICK );
check( 'die Option laedt nicht bei jedem Seitenaufruf mit', $r['autoload'], false );
check( 'die rollende 24-h-Summe wird neu gerechnet', in_array( 'naws_cache_rain24h_' . md5( RAIN ), $r['deleted'], true ), true );
check( 'der Abruf zaehlt die nachgeholten Meldungen mit', $r['result'], 9 );
check( 'keine Warnung', $r['warnings'], [] );

// ── Erster Lauf nach dem Update oder auf einer frischen Installation ─────
$r = run_sync( WET_HOUR, MEASURED );
check( 'ohne Merker wird der ganze Tag geschlossen', $r['calls'][0]['date_begin'] ?? null, TICK - DAY_IN_SECONDS );
check( 'ohne Merker: das Fenster endet an der neuesten Meldung', $r['calls'][0]['date_end'] ?? null, TICK );

// ── Trockene Stunde: sum_rain_1 buergt fuer die fehlenden Meldungen ──────
$r = run_sync( DRY_HOUR, MEASURED, [ RAIN => PREV ] );
check( 'trockene Stunde, Luecke von zehn Minuten: kein Aufruf', count( $r['calls'] ), 0 );
check( 'trockene Stunde: die Reihe gilt trotzdem als vollstaendig', $r['marks'][ RAIN ] ?? null, TICK );
check( 'trockene Stunde: nur die Dashboard-Meldung wird gespeichert', array_keys( $r['rain'] ), [ TICK ] );

$r = run_sync( DRY_HOUR, MEASURED, [ RAIN => TICK - 2 * HOUR_IN_SECONDS ] );
check( 'trockene Stunde, aber zwei Stunden Luecke: Aufruf', count( $r['calls'] ), 1 );
check( 'zwei Stunden Luecke: das Fenster beginnt am Merker', $r['calls'][0]['date_begin'] ?? null, TICK - 2 * HOUR_IN_SECONDS );

// ── Langer Ausfall: hoechstens ein Tag ───────────────────────────────────
$r = run_sync( WET_HOUR, MEASURED, [ RAIN => TICK - 3 * DAY_IN_SECONDS ] );
check( 'nach drei Tagen Ausfall wird nur der letzte Tag geschlossen', $r['calls'][0]['date_begin'] ?? null, TICK - DAY_IN_SECONDS );

// ── Derselbe Abruf noch einmal (Hand-Abruf im selben Fuenf-Minuten-Slot) ─
$r = run_sync( WET_HOUR, MEASURED, [ RAIN => TICK ] );
check( 'Reihe schon vollstaendig: kein Aufruf', count( $r['calls'] ), 0 );
check( 'Reihe schon vollstaendig: Merker bleibt', $r['marks'][ RAIN ] ?? null, TICK );

// ── Netatmo antwortet nicht ──────────────────────────────────────────────
$r = run_sync( WET_HOUR, new WP_Error( 'api_error', 'Netatmo API error 27: Service temporarily unavailable (HTTP 503)' ), [ RAIN => PREV ] );
check( 'Fehler: der Abruf selbst gelingt weiter', $r['result'], 6 );
check( 'Fehler: nur die Dashboard-Meldung in der Reihe', array_keys( $r['rain'] ), [ TICK ] );
check( 'Fehler: der Merker bleibt, der naechste Lauf fragt wieder', $r['marks'][ RAIN ] ?? null, PREV );
check( 'Fehler: eine Warnung mit dem Fehlertext', count( $r['warnings'] ) === 1 && strpos( $r['warnings'][0], 'Service temporarily unavailable' ) !== false, true );

// ── Leere oder unbrauchbare Antwort ──────────────────────────────────────
$r = run_sync( WET_HOUR, [], [ RAIN => PREV ] );
check( 'leere Antwort: der Merker bleibt', $r['marks'][ RAIN ] ?? null, PREV );
check( 'leere Antwort: keine Warnung', $r['warnings'], [] );

$r = run_sync( WET_HOUR, [ 'foo' => [ 'x' ], MISSED => [ 'n/a' ], TICK - 100 => [ 0.101 ] ], [ RAIN => PREV ] );
check( 'unbrauchbare Eintraege werden uebergangen, brauchbare gespeichert', $r['rain'], [ TICK - 100 => 0.101, TICK => 0.404 ] );
check( 'nach brauchbaren Eintraegen gilt die Reihe als vollstaendig', $r['marks'][ RAIN ] ?? null, TICK );

// ── Abgeschalteter Regenmesser ───────────────────────────────────────────
$r = run_sync( WET_HOUR, MEASURED, [ RAIN => PREV ], false );
check( 'abgeschalteter Regenmesser: kein Aufruf', count( $r['calls'] ), 0 );
check( 'abgeschalteter Regenmesser: keine Meldungen', $r['rain'], [] );

// ── Dashboard ohne Zeitstempel ───────────────────────────────────────────
$r = run_sync( [ 'Rain' => 0.404, 'sum_rain_1' => 1.515 ], MEASURED, [ RAIN => PREV ] );
check( 'ohne time_utc: kein Aufruf', count( $r['calls'] ), 0 );
check( 'ohne time_utc: der Merker bleibt', $r['marks'][ RAIN ] ?? null, PREV );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
