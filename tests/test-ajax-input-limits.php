<?php
/**
 * Tests fuer die Grenzen der oeffentlichen Diagramm-Endpunkte.
 *
 * Die Nonce fuer naws_get_daily_data und naws_get_history_data steht im
 * Quelltext jeder Seite mit einem Diagramm, die Endpunkte sind also fuer
 * jeden Besucher offen. Bis 2.1.5 nahmen beide fields[] ungeprueft auf
 * Wiederholungen entgegen: jede Wiederholung baute dieselbe Datenreihe ein
 * weiteres Mal. Gemessen am 22.10.2026 auf dev (2817 Tageszeilen): rund
 * 0,27 MB je Wiederholung, bei 1000 Wiederholungen (max_input_vars) also
 * gut 270 MB plus 24 MB JSON - genug, um einen Worker mit memory_limit
 * 512M an den Rand zu bringen und auf groesseren Datenbestaenden darueber.
 * Ein vom Besucher ausloesbarer Speicherfehler ist genau der Zustand, den
 * CVE-2026-109618/109619 ("PHP2Shell") ausnutzen.
 *
 * Ausserdem legte naws_get_daily_data fuer jedes neue Datumspaar ein
 * eigenes Transient mit allen Zeilen an.
 *
 *   php tests/test-ajax-input-limits.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'NAWS_TABLE_MODULES',  'naws_modules' );
define( 'NAWS_TABLE_READINGS', 'naws_readings' );
define( 'NAWS_TABLE_DAILY',    'naws_daily_summary' );

class NAWS_Test_Sent extends Exception {}

$GLOBALS['naws_test_transients'] = [];
$GLOBALS['sent']                 = null;
function add_action( ...$a ) {}
function check_ajax_referer( ...$a ) { return true; }
function nocache_headers() {}
function get_transient( $k )               { return $GLOBALS['naws_test_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['naws_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k )            { unset( $GLOBALS['naws_test_transients'][ $k ] ); return true; }
function sanitize_text_field( $s )         { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s )                { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
function wp_unslash( $s )                  { return $s; }
function wp_json_encode( $v )              { return json_encode( $v ); }
function wp_parse_args( $a, $d )           { return array_merge( $d, (array) $a ); }
function wp_date( $f, $t = null )          { return gmdate( $f, $t ?? time() ); }
function wp_cache_flush_group( $g )        { return true; }
function get_option( $k, $d = false )      { return $d; }
require_once __DIR__ . '/i18n-stubs.php';
function wp_send_json_success( $data = null ) {
    $GLOBALS['sent'] = [ 'ok' => true, 'data' => $data ];
    throw new NAWS_Test_Sent();
}
function wp_send_json_error( $data = null, $code = null ) {
    $GLOBALS['sent'] = [ 'ok' => false, 'data' => $data ];
    throw new NAWS_Test_Sent();
}
class NAWS_Logger {
    public static function error( ...$a ) {}
    public static function warning( ...$a ) {}
    public static function info( ...$a ) {}
    public static function debug( ...$a ) {}
}

/** Ein wpdb mit einem Modul und drei Tagen, der jede Abfrage festhaelt. */
class NAWS_Test_WPDB {
    public $prefix     = 'wp_';
    public $options    = 'wp_options';
    public $last_error = '';
    public $queries    = [];
    public function prepare( $q, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
        $this->queries[] = [ $q, $args ];
        return $q;
    }
    public function get_results( $q, $out = null ) {
        if ( str_contains( $q, 'naws_modules' ) ) {
            return [ [ 'module_id' => '02:00:00:00:00:02', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAModule1', 'module_name' => 'Aussen', 'is_active' => 1, 'data_types' => '[]' ] ];
        }
        if ( str_contains( $q, 'naws_daily_summary' ) ) {
            $rows = [];
            foreach ( [ '2025-10-20', '2025-10-21', '2026-10-21' ] as $d ) {
                $rows[] = [ 'module_id' => '02:00:00:00:00:02', 'station_id' => '70:ee:50:00:00:01', 'day_date' => $d, 'temp_min' => '4.2', 'temp_max' => '12.1' ];
            }
            return $rows;
        }
        return [];
    }
    public function get_var( $q ) { return null; }
    public function query( $q ) { return 0; }
}
$GLOBALS['wpdb'] = new NAWS_Test_WPDB();

require_once __DIR__ . '/../includes/class-naws-database.php';
require_once __DIR__ . '/../includes/class-naws-helpers.php';
require_once __DIR__ . '/../includes/class-naws-ajax.php';

$passed = 0;
$failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function call( string $method, array $post ): array {
    $GLOBALS['sent'] = null;
    $_POST           = $post;
    try {
        NAWS_Ajax::instance()->$method();
    } catch ( NAWS_Test_Sent $e ) {
        // wp_send_json_* beendet in WordPress die Anfrage; hier tut es das auch.
    }
    $_POST = [];
    return $GLOBALS['sent'] ?? [ 'ok' => null, 'data' => null ];
}
/** Die letzte vorbereitete Abfrage auf die Tagestabelle. */
function last_daily(): array {
    foreach ( array_reverse( $GLOBALS['wpdb']->queries ) as $q ) {
        if ( str_contains( $q[0], 'naws_daily_summary' ) ) { return $q; }
    }
    return [ '', [] ];
}

/** Nur die Transients der Tagesabfrage; der Modul-Cache zaehlt nicht. */
function daily_transients(): int {
    return count( preg_grep( '/daily_/', array_keys( $GLOBALS['naws_test_transients'] ) ) );
}

$repeated = array_fill( 0, 1000, 'temp_min' );

echo "\nNAWS_Ajax: Grenzen der oeffentlichen Endpunkte\n" . str_repeat( '-', 74 ) . "\n";

// ── naws_get_daily_data ──────────────────────────────────────────────────
$r = call( 'get_daily_data', [ 'fields' => $repeated, 'date_from' => '2025-01-01', 'date_to' => '2026-12-31' ] );
check( 'Tagesdaten: 1000 x temp_min ergibt eine Datenreihe', count( $r['data']['datasets'] ?? [] ), 1 );
check( 'mit einem Punkt je Tag, nicht tausend',               count( $r['data']['datasets'][0]['data'] ?? [] ), 3 );
[ $sql ] = last_daily();
check( 'und die Abfrage waehlt die Spalte einmal',            substr_count( $sql, '%i' ), 1 );

$r = call( 'get_daily_data', [ 'fields' => [ 'temp_min', 'nicht_da', 'temp_max', 'temp_min' ], 'date_from' => '2025-01-01', 'date_to' => '2026-12-31' ] );
check( 'unbekannte Felder fallen weg, bekannte bleiben',     count( $r['data']['datasets'] ?? [] ), 2 );

$r = call( 'get_daily_data', [ 'fields' => [ 'nicht_da' ] ] );
check( 'nur unbekannte Felder: abgelehnt',                    $r['ok'], false );

$r = call( 'get_daily_data', [ 'date_from' => 'gestern', 'date_to' => '2026-12-31' ] );
check( 'ein Datum ausserhalb von JJJJ-MM-TT: abgelehnt',      $r['ok'], false );

$GLOBALS['naws_test_transients'] = [];
foreach ( [ '2025-01-01', '2025-01-02', '2025-01-03' ] as $d ) {
    call( 'get_daily_data', [ 'date_from' => $d, 'date_to' => '2026-12-31' ] );
}
check( 'Besucher-Datumspaare legen kein Transient an',        daily_transients(), 0 );

NAWS_Database::get_daily_summaries( [ 'date_from' => '2025-01-01', 'date_to' => '2026-12-31', 'fields' => [ 'temp_min' ] ] );
check( 'andere Aufrufer cachen weiter',                       daily_transients(), 1 );

$r = NAWS_Database::get_daily_chart_data( [ 'date_from' => '2025-01-01', 'date_to' => '2026-12-31', 'fields' => $repeated, 'no_cache' => true ] );
check( 'get_daily_chart_data selbst entfernt Wiederholungen', count( $r['datasets'] ), 1 );

// ── naws_get_history_data ────────────────────────────────────────────────
$r = call( 'get_history_data', [ 'fields' => $repeated, 'year_from' => 2025, 'year_to' => 2026 ] );
check( 'Jahresvergleich: eine Reihe je Jahr, nicht tausend',  count( $r['data']['series'] ?? [] ), 2 );
[ $sql, $params ] = last_daily();
check( 'und die Abfrage waehlt die Spalte einmal',            substr_count( $sql, '%i' ), 1 );

call( 'get_history_data', [ 'year_from' => -99999999, 'year_to' => 99999999 ] );
[ , $params ] = last_daily();
check( 'Jahre werden auf 1900 bis 2999 begrenzt',             array_slice( $params, -2 ), [ '1900-01-01', '2999-12-31' ] );

// ── naws_get_chart_data ──────────────────────────────────────────────────
call( 'get_chart_data', [ 'parameter' => array_fill( 0, 1000, 'Temperature' ) ] );
$q = null;
foreach ( array_reverse( $GLOBALS['wpdb']->queries ) as $x ) {
    if ( str_contains( $x[0], 'naws_readings' ) ) { $q = $x; break; }
}
check( 'Rohwerte: 1000 x Temperature wird ein Platzhalter',  $q ? substr_count( $q[0], 'r.parameter IN (%s)' ) : -1, 1 );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
