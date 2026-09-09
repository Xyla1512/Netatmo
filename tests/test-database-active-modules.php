<?php
/**
 * Tests fuer NAWS_Database::get_daily_summaries() und get_readings(): die
 * Zeilen inaktiver Module bleiben draussen — aber ohne Vergleich ueber zwei
 * Tabellen hinweg. Der fruehere EXISTS-Join auf module_id kippte, sobald die
 * Tabellen verschiedene Kollationen trugen ("Illegal mix of collations",
 * gemessen am 09.09.2026 auf einer echten Installation), und das Plugin gab
 * dann still [] zurueck. Die aktiven IDs kommen aus get_modules(true) und
 * wandern als IN (...) in die Abfrage; ein SQL-Fehler bleibt ueber
 * last_error() abrufbar, damit ein Redakteur ihn zu sehen bekommt.
 *
 *   php tests/test-database-active-modules.php
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

$GLOBALS['naws_test_transients'] = [];
function get_transient( $k )               { return $GLOBALS['naws_test_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['naws_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k )            { unset( $GLOBALS['naws_test_transients'][ $k ] ); return true; }
function sanitize_text_field( $s )         { return is_string( $s ) ? trim( $s ) : $s; }
function wp_json_encode( $v )              { return json_encode( $v ); }
function wp_parse_args( $a, $d )           { return array_merge( $d, (array) $a ); }
function wp_date( $f, $t = null )          { return gmdate( $f, $t ?? time() ); }
function wp_cache_flush_group( $g )        { return true; }
class NAWS_Logger {
    public static $errors = [];
    public static function error( $ctx, $msg, $extra = [] ) { self::$errors[] = $msg; }
    public static function warning( ...$a ) {}
    public static function info( ...$a ) {}
}

/** Ein wpdb, der je Tabelle eigene Zeilen liefert und jede Abfrage festhaelt. */
class NAWS_Test_WPDB {
    public $prefix     = 'wp_';
    public $options    = 'wp_options';
    public $last_error = '';
    public $modules    = [];
    public $daily      = [];
    public $readings   = [];
    public $fail_daily = '';     // wenn gesetzt: die Tagesabfrage scheitert mit diesem Text
    public $queries    = [];     // [ sql, params ] je prepare()
    public $seen       = [];     // jede SQL, die get_results() bekam
    public $fail_readings = ''; // wenn gesetzt: die Rohwertabfrage scheitert mit diesem Text
    public function prepare( $q, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
        $this->queries[] = [ $q, $args ];
        return $q;
    }
    public function get_results( $q, $out = null ) {
        $this->last_error = '';
        $this->seen[] = $q;
        if ( str_contains( $q, 'naws_modules' ) ) {
            return str_contains( $q, 'is_active = 1' ) ? array_values( array_filter( $this->modules, fn( $m ) => (int) $m['is_active'] === 1 ) ) : $this->modules;
        }
        if ( str_contains( $q, 'naws_daily_summary' ) ) {
            if ( $this->fail_daily !== '' ) { $this->last_error = $this->fail_daily; return null; }
            return $this->daily;
        }
        if ( str_contains( $q, 'naws_readings' ) ) {
            if ( $this->fail_readings !== '' ) { $this->last_error = $this->fail_readings; return null; }
            return $this->readings;
        }
        return [];
    }
    public function get_var( $q ) { return null; }
    public function query( $q ) { return 0; }
    public function update( ...$a ) { return 1; }
}
$wpdb = new NAWS_Test_WPDB();
require_once dirname( __DIR__ ) . '/includes/class-naws-database.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
/** Die letzte vorbereitete Abfrage auf $table: [ sql, params ] oder null. */
function last_sql( NAWS_Test_WPDB $db, string $table ): ?array {
    foreach ( array_reverse( $db->queries ) as $q ) { if ( str_contains( $q[0], $table ) ) { return $q; } }
    return null;
}
/** Die letzte rohe SQL auf $table, wie get_results() sie bekam, oder null. */
function last_seen( NAWS_Test_WPDB $db, string $table ): ?string {
    foreach ( array_reverse( $db->seen ) as $q ) { if ( str_contains( $q, $table ) ) { return $q; } }
    return null;
}
function fresh( NAWS_Test_WPDB $db, array $modules ): void {
    $GLOBALS['naws_test_transients'] = [];
    $db->queries = []; $db->modules = $modules; $db->fail_daily = ''; $db->daily = []; $db->readings = []; $db->seen = []; $db->fail_readings = '';
}
function last_error_or( string $fallback ): string {
    return method_exists( 'NAWS_Database', 'last_error' ) ? NAWS_Database::last_error() : $fallback;
}

$main    = [ 'module_id' => '70:ee:50:00:00:01', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAMain',    'module_name' => 'Innen',     'is_active' => 1, 'data_types' => '[]' ];
$outdoor = [ 'module_id' => '02:00:00:00:00:02', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAModule1', 'module_name' => 'Aussen',    'is_active' => 1, 'data_types' => '[]' ];
$old     = [ 'module_id' => '05:00:00:00:00:03', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAModule3', 'module_name' => 'Regen alt', 'is_active' => 0, 'data_types' => '[]' ];
$args    = [ 'module_id' => null, 'date_from' => '2024-01-01', 'date_to' => '2026-09-09', 'fields' => [ 'temp_min', 'temp_max' ], 'group_by' => 'day' ];

echo "\nAktive Module ohne Tabellen-Join\n" . str_repeat( '-', 74 ) . "\n";
fresh( $wpdb, [ $main, $outdoor, $old ] );
$wpdb->daily = [ [ 'day_date' => '2026-09-08' ] ];
$rows = NAWS_Database::get_daily_summaries( $args );
[ $sql, $params ] = last_sql( $wpdb, 'naws_daily_summary' ) ?? [ '', [] ];
check( 'Tagesabfrage: kein EXISTS-Join auf naws_modules',      str_contains( $sql, 'EXISTS' ), false );
check( 'Tagesabfrage: aktive IDs als IN (...)',                 preg_match( '/d\.module_id IN \(%s,%s\)/', $sql ) === 1, true );
check( 'Tagesabfrage: nur die aktiven IDs als Parameter',       array_slice( $params, -2 ), [ $main['module_id'], $outdoor['module_id'] ] );
check( 'Tagesabfrage: Zeilen kommen an',                        count( $rows ), 1 );

fresh( $wpdb, [ $main, $outdoor, $old ] );
NAWS_Database::get_daily_summaries( [ 'module_id' => [ $main['module_id'], $old['module_id'] ] ] + $args );
[ $sql, $params ] = last_sql( $wpdb, 'naws_daily_summary' ) ?? [ '', [] ];
check( 'gewuenschtes, aber inaktives Modul faellt aus der Liste', array_slice( $params, -1 ), [ $main['module_id'] ] );

fresh( $wpdb, [ $old ] );
$rows = NAWS_Database::get_daily_summaries( $args );
check( 'ohne aktives Modul: leer, und keine Tagesabfrage',      [ $rows, last_sql( $wpdb, 'naws_daily_summary' ) ], [ [], null ] );

fresh( $wpdb, [ $main, $old ] );
NAWS_Database::get_readings( [ 'module_id' => $main['module_id'], 'parameter' => 'Temperature' ] );
[ $sql, $params ] = last_sql( $wpdb, 'naws_readings' ) ?? [ '', [] ];
check( 'Rohwertabfrage: kein EXISTS-Join auf naws_modules',    str_contains( $sql, 'EXISTS' ), false );
check( 'Rohwertabfrage: aktive IDs als IN (...)',               str_contains( $sql, 'r.module_id IN (%s)' ), true );
check( 'Rohwertabfrage: Zeitraum, ID, Parameter in dieser Reihenfolge', array_slice( $params, 2 ), [ $main['module_id'], 'Temperature' ] );

echo "\nEin SQL-Fehler bleibt abrufbar\n" . str_repeat( '-', 74 ) . "\n";
fresh( $wpdb, [ $main ] );
$wpdb->fail_daily = "Illegal mix of collations (utf8mb4_unicode_520_ci,IMPLICIT) and (utf8mb4_general_ci,IMPLICIT) for operation '='";
$rows = NAWS_Database::get_daily_summaries( $args );
check( 'bei Fehler: leeres Ergebnis',                           $rows, [] );
check( 'bei Fehler: last_error() nennt den Grund',              str_contains( last_error_or( '(fehlt)' ), 'Illegal mix of collations' ), true );
check( 'bei Fehler: nichts im Zwischenspeicher',                count( array_filter( array_keys( $GLOBALS['naws_test_transients'] ), fn( $k ) => str_contains( $k, 'daily_' ) ) ), 0 );
fresh( $wpdb, [ $main ] );
NAWS_Database::get_daily_summaries( $args );
check( 'der naechste Erfolg loescht last_error()',              last_error_or( '(fehlt)' ), '' );

echo "\nLetzte Messwerte ohne Tabellen-Join\n" . str_repeat( '-', 74 ) . "\n";
fresh( $wpdb, [ $main, $old ] );
$wpdb->readings = [ [ 'module_id' => $main['module_id'], 'parameter' => 'Temperature', 'recorded_at' => 1, 'value' => 21.5 ] ];
$rows = NAWS_Database::get_latest_readings( $main['module_id'] );
check( 'ein Modul: kein Join auf naws_modules',                 str_contains( (string) last_seen( $wpdb, 'naws_readings' ), 'naws_modules' ), false );
check( 'ein Modul: Zeilen kommen an',                           count( $rows ), 1 );
fresh( $wpdb, [ $main, $old ] );
$rows = NAWS_Database::get_latest_readings( $old['module_id'] );
check( 'inaktives Modul: leer, und keine Abfrage',              [ $rows, last_seen( $wpdb, 'naws_readings' ) ], [ [], null ] );
fresh( $wpdb, [ $main, $outdoor, $old ] );
NAWS_Database::get_latest_readings();
$sql = (string) last_seen( $wpdb, 'naws_readings' );
[ , $params ] = last_sql( $wpdb, 'naws_readings' ) ?? [ '', [] ];
check( 'alle Module: kein Join auf naws_modules',               str_contains( $sql, 'naws_modules' ), false );
check( 'alle Module: aktive IDs als IN (...)',                  str_contains( $sql, 'r1.module_id IN (%s,%s)' ), true );
check( 'alle Module: nur aktive IDs als Parameter',             $params, [ $main['module_id'], $outdoor['module_id'] ] );
fresh( $wpdb, [ $main ] );
$wpdb->fail_readings = 'Illegal mix of collations (letzte Messwerte)';
check( 'bei Fehler: leer, und last_error() nennt den Grund',   [ NAWS_Database::get_latest_readings(), str_contains( last_error_or( '(fehlt)' ), 'Illegal mix' ) ], [ [], true ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
