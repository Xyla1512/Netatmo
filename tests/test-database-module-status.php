<?php
/**
 * Tests fuer die fuenf Statusfelder, die Netatmo je Modul liefert und die
 * seit Schema 1.5 in naws_modules liegen: battery_percent, wifi_status,
 * reachable, last_status_store, last_message. Die Benachrichtigungen
 * lesen sie; die Modulseite zeigt die Batterie daraus.
 *
 *   php tests/test-database-module-status.php
 *
 * @package NAWS
 */
// install() laedt ABSPATH . 'wp-admin/includes/upgrade.php' per require_once,
// bevor es dbDelta() ruft. Ein leerer Stub an genau dieser Stelle laesst
// den Aufruf durch; dbDelta() selbst wird unten gestubbt.
$naws_test_abspath = rtrim( sys_get_temp_dir(), '/\\' ) . '/naws-test-abspath-' . getmypid() . '/';
@mkdir( $naws_test_abspath . 'wp-admin/includes', 0777, true );
file_put_contents( $naws_test_abspath . 'wp-admin/includes/upgrade.php', "<?php\n" );
define( 'ABSPATH', $naws_test_abspath );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'NAWS_TABLE_MODULES',  'naws_modules' );
define( 'NAWS_TABLE_READINGS', 'naws_readings' );
define( 'NAWS_TABLE_DAILY',    'naws_daily_summary' );
define( 'NAWS_DB_VERSION',     '1.5' );

$GLOBALS['naws_test_transients'] = [];
$GLOBALS['naws_test_options']    = [];
function get_transient( $k )               { return $GLOBALS['naws_test_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['naws_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k )            { unset( $GLOBALS['naws_test_transients'][ $k ] ); return true; }
function get_option( $k, $d = false )      { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ){ $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function sanitize_text_field( $s )         { return is_string( $s ) ? trim( $s ) : $s; }
function wp_json_encode( $v )              { return json_encode( $v ); }
function wp_parse_args( $a, $d )           { return array_merge( $d, (array) $a ); }
function wp_date( $f, $t = null )          { return gmdate( $f, $t ?? time() ); }
function wp_cache_flush_group( $g )        { return true; }
function esc_sql( $s )                     { return $s; }
function dbDelta( $sql )                   { $GLOBALS['naws_test_ddl'][] = $sql; return []; }
class NAWS_Logger {
    public static $errors = [];
    public static function error( $ctx, $msg, $extra = [] ) { self::$errors[] = $msg; }
    public static function warning( ...$a ) {}
    public static function info( ...$a ) {}
}
class NAWS_Cron { public static function schedule() {} }
class WP_Error { public function __construct( ...$a ) {} }

/** Ein wpdb, der Upsert und update() festhaelt und SHOW COLUMNS beantwortet. */
class NAWS_Test_WPDB {
    public $prefix     = 'wp_';
    public $last_error = '';
    public $queries    = [];   // [ sql, params ] je prepare()
    public $raw        = [];   // jede SQL an query()
    public $updates    = [];   // [ table, data, where, format ] je update()
    public $columns    = [];   // Antwort auf SHOW COLUMNS (get_col)
    public function prepare( $q, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
        $this->queries[] = [ $q, $args ];
        return $q;
    }
    public function get_charset_collate() { return ''; }
    // SHOW COLUMNS … LIKE %s fragt nur nach is_active: vorhanden, wenn es in $columns steht.
    public function get_results( $q, $out = null ) { return ( str_contains( $q, 'SHOW COLUMNS' ) && in_array( 'is_active', $this->columns, true ) ) ? [ 1 ] : []; }
    public function get_var( $q ) { return 'x'; }          // latitude vorhanden: kein ALTER dafuer
    public function get_col( $q ) { return $this->columns; }
    public function query( $q ) { $this->raw[] = $q; return 1; }
    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        $this->updates[] = [ $table, $data, $where, $format ];
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

$base = [ '_id' => '70:ee:50:a9:5a:08', 'station_id' => '70:ee:50:a9:5a:08', 'type' => 'NAMain', 'station_name' => 'HOME (Basis)',
          'wifi_status' => 60, 'reachable' => true, 'last_status_store' => 1789144822, 'firmware' => 300 ];
$gast = [ '_id' => '03:00:00:0d:aa:ca', 'station_id' => '70:ee:50:a9:5a:08', 'type' => 'NAModule4', 'module_name' => 'Gast',
          'battery_percent' => 23, 'battery_vp' => 4612, 'rf_status' => 74, 'reachable' => true,
          'last_seen' => 1789144813, 'last_message' => 1789144820, 'firmware' => 53 ];

echo "\nstatus_fields(): was aus der Netatmo-Antwort wird\n" . str_repeat( '-', 74 ) . "\n";
$f = NAWS_Database::status_fields( $gast );
check( 'Modul: battery_percent, rf bleibt draussen, wifi null',
    [ $f['battery_percent'], $f['wifi_status'], $f['reachable'], $f['last_status_store'], $f['last_message'] ],
    [ 23, null, 1, null, 1789144820 ] );
$f = NAWS_Database::status_fields( $base );
check( 'Basis: wifi_status und last_status_store, kein battery_percent',
    [ $f['battery_percent'], $f['wifi_status'], $f['reachable'], $f['last_status_store'], $f['last_message'] ],
    [ null, 60, 1, 1789144822, null ] );
check( 'reachable false wird 0',            NAWS_Database::status_fields( [ 'reachable' => false ] )['reachable'], 0 );
check( 'fehlendes reachable bleibt null',   NAWS_Database::status_fields( [] )['reachable'], null );
check( 'battery_percent wird auf 0..100 geklemmt', [ NAWS_Database::status_fields( [ 'type' => 'NAModule1', 'battery_percent' => 140 ] )['battery_percent'], NAWS_Database::status_fields( [ 'type' => 'NAModule1', 'battery_percent' => -3 ] )['battery_percent'] ], [ 100, 0 ] );
check( 'wifi_status nur an der Basis',      NAWS_Database::status_fields( [ 'type' => 'NAModule1', 'wifi_status' => 60 ] )['wifi_status'], null );

echo "\nsave_module(): Upsert unveraendert, Statusfelder per update()\n" . str_repeat( '-', 74 ) . "\n";
$wpdb->queries = []; $wpdb->updates = [];
NAWS_Database::save_module( $gast );
[ $sql, $params ] = $wpdb->queries[0] ?? [ '', [] ];
check( 'Upsert nennt is_active nicht im UPDATE-Teil', preg_match( '/ON DUPLICATE KEY UPDATE(?:(?!is_active).)*$/s', $sql ) === 1, true );
check( 'ein update() auf naws_modules',              [ count( $wpdb->updates ), $wpdb->updates[0][0] ?? '' ], [ 1, 'wp_naws_modules' ] );
check( 'update(): die fuenf Felder, NULL bleibt NULL', $wpdb->updates[0][1] ?? [], [ 'battery_percent' => 23, 'wifi_status' => null, 'reachable' => 1, 'last_status_store' => null, 'last_message' => 1789144820 ] );
check( 'update(): WHERE module_id',                   $wpdb->updates[0][2] ?? [], [ 'module_id' => '03:00:00:0d:aa:ca' ] );
check( 'update(): Formate %d fuer alle fuenf',        $wpdb->updates[0][3] ?? [], [ '%d', '%d', '%d', '%d', '%d' ] );

echo "\ninstall()/maybe_migrate(): Spalten anlegen, vorhandene in Ruhe lassen\n" . str_repeat( '-', 74 ) . "\n";
$wpdb->raw = []; $wpdb->columns = [ 'id', 'module_id', 'rf_status', 'is_active' ];
NAWS_Database::install();
$ddl = implode( "\n", $GLOBALS['naws_test_ddl'] ?? [] );
check( 'CREATE TABLE nennt alle fuenf Spalten',
    [ str_contains( $ddl, 'battery_percent' ), str_contains( $ddl, 'wifi_status' ), str_contains( $ddl, 'reachable' ), str_contains( $ddl, 'last_status_store' ), str_contains( $ddl, 'last_message' ) ],
    [ true, true, true, true, true ] );
$alters = array_values( array_filter( $wpdb->raw, fn( $q ) => str_contains( $q, 'naws_modules' ) && str_contains( $q, 'ADD COLUMN' ) ) );
check( 'fuenf ALTER auf naws_modules, wenn alle fehlen', count( $alters ), 5 );
check( 'ALTER-Reihenfolge nach rf_status', str_contains( $alters[0] ?? '', 'ADD COLUMN battery_percent TINYINT UNSIGNED DEFAULT NULL AFTER rf_status' ), true );
check( 'db_version wird 1.5',               $GLOBALS['naws_test_options']['naws_db_version'] ?? '', '1.5' );
$wpdb->raw = []; $wpdb->columns = [ 'id', 'module_id', 'rf_status', 'battery_percent', 'wifi_status', 'reachable', 'last_status_store', 'last_message', 'is_active' ];
NAWS_Database::install();
$alters = array_filter( $wpdb->raw, fn( $q ) => str_contains( $q, 'naws_modules' ) && str_contains( $q, 'ADD COLUMN' ) );
check( 'kein ALTER, wenn alle da sind',     count( $alters ), 0 );

@unlink( $naws_test_abspath . 'wp-admin/includes/upgrade.php' );
@rmdir( $naws_test_abspath . 'wp-admin/includes' ); @rmdir( $naws_test_abspath . 'wp-admin' ); @rmdir( $naws_test_abspath );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
