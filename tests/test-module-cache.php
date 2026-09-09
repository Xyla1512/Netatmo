<?php
/**
 * Tests fuer den Modul-Zwischenspeicher in NAWS_Database: get_modules()
 * haelt seine Zeilen eine Stunde in zwei Transients (aktiv / alle). Wer
 * ein Modul umschaltet, muss beide leeren, sonst liest das Frontend bis
 * zu einer Stunde lang den alten Stand, waehrend der Admin frisch liest.
 *
 *   php tests/test-module-cache.php
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
    public static function error( ...$a ) {}
    public static function warning( ...$a ) {}
    public static function info( ...$a ) {}
}

/** Ein wpdb, der Modulzeilen aus $rows liefert und Schreibzugriffe zaehlt. */
class NAWS_Test_WPDB {
    public $prefix     = 'wp_';
    public $options    = 'wp_options';
    public $last_error = '';
    public $rows       = [];
    public $updates    = [];
    public function prepare( $q, ...$args ) { return $q; }
    public function get_results( $q, $out = null ) { return $this->rows; }
    public function get_var( $q ) { return null; }
    public function query( $q ) { return 0; }
    public function update( $table, $data, $where, $fmt = null, $wfmt = null ) {
        $this->updates[] = [ $table, $data, $where ];
        return 1;
    }
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

$station = [ 'module_id' => '70:ee:50:aa:bb:cc', 'station_id' => '70:ee:50:aa:bb:cc', 'module_type' => 'NAMain', 'module_name' => 'Innen', 'is_active' => 1, 'data_types' => '[]' ];

echo "\nModul-Zwischenspeicher nach dem Umschalten\n" . str_repeat( '-', 74 ) . "\n";

// Erster Aufruf fuellt beide Transients.
$wpdb->rows = [ $station ];
NAWS_Database::get_modules( true );
NAWS_Database::get_modules( false );
check( 'get_modules(true) fuellt naws_cache_modules_1',  isset( $GLOBALS['naws_test_transients']['naws_cache_modules_1'] ), true );
check( 'get_modules(false) fuellt naws_cache_modules_0', isset( $GLOBALS['naws_test_transients']['naws_cache_modules_0'] ), true );

// Die Station wird deaktiviert: in der Datenbank ist sie danach nicht mehr aktiv.
NAWS_Database::set_module_active( $station['module_id'], 0 );
check( 'set_module_active schreibt in die Datenbank', count( $wpdb->updates ), 1 );
$wpdb->rows = [];   // was WHERE is_active = 1 jetzt liefern wuerde

// Das Frontend muss die Aenderung sofort sehen, nicht erst nach einer Stunde.
check( 'nach dem Umschalten liest get_modules(true) frisch aus der Datenbank', NAWS_Database::get_modules( true ), [] );
check( 'naws_cache_modules_1 ist nach dem Umschalten geleert', get_transient( 'naws_cache_modules_1' ) === false || get_transient( 'naws_cache_modules_1' ) === [], true );
check( 'naws_cache_modules_0 ist nach dem Umschalten geleert', get_transient( 'naws_cache_modules_0' ), false );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
