<?php
/**
 * Tests fuer die Anzeigenamen der Module (Aliasse).
 *
 * Anlass: ein Nutzer im WP.org-Forum wollte "Outdoor" als "Rose Gallery"
 * und "Base" als "Organ Corner" sehen. Die Namen kommen aus Netatmo und
 * werden bei jedem Sync ueberschrieben; "Outdoor" und "Base" auf den
 * Live-Kacheln sind sogar feste Abzeichen des Plugins. Ein Anzeigename je
 * Modul, auf der Modulseite gepflegt, ersetzt beides ueberall — ohne die
 * internen Kennungen anzufassen:
 *
 *   1. get_modules() liefert display_name (Alias, sonst Netatmo-Name).
 *   2. Der Alias liegt in einer Option, nicht in der Modultabelle — der
 *      Sync schreibt module_name bei jedem Lauf neu und darf ihn nie
 *      ueberschreiben. Er gilt sofort, auch wenn die Modulliste noch im
 *      Transient liegt.
 *   3. module_slug() bleibt am Netatmo-Namen: an ihm haengen gespeicherte
 *      Sichtbarkeitsschalter und Shortcode-Parameter wie Temperature_gast.
 *
 *   php tests/test-module-display-names.php
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
$GLOBALS['naws_test_options']    = [];
function get_transient( $k )               { return $GLOBALS['naws_test_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['naws_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k )            { unset( $GLOBALS['naws_test_transients'][ $k ] ); return true; }
function get_option( $k, $d = false )      { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function delete_option( $k )               { unset( $GLOBALS['naws_test_options'][ $k ] ); return true; }
function sanitize_text_field( $s )         { return is_string( $s ) ? trim( preg_replace( '/<[^>]*>/', '', $s ) ) : ''; }
function wp_json_encode( $v )              { return json_encode( $v ); }
function wp_parse_args( $a, $d )           { return array_merge( $d, (array) $a ); }
function wp_date( $f, $t = null )          { return gmdate( $f, $t ?? time() ); }
function wp_cache_flush_group( $g )        { return true; }
function __( $s, $d = null )               { return $s; }
function _x( $s, $c, $d = null )           { return $s; }
function naws__( $k )                      { return $k; }
function naws_label( $k )                  { return $k; }
function esc_html( $s )                    { return $s; }
function esc_attr( $s )                    { return $s; }
class NAWS_Logger {
    public static function error( ...$a ) {}
    public static function warning( ...$a ) {}
    public static function info( ...$a ) {}
}

class NAWS_Test_WPDB {
    public $prefix     = 'wp_';
    public $options    = 'wp_options';
    public $last_error = '';
    public $rows       = [];
    public $queries    = 0;
    public function prepare( $q, ...$args ) { return $q; }
    public function get_results( $q, $out = null ) {
        $this->queries++;
        if ( strpos( $q, 'is_active = 1' ) !== false ) {
            return array_values( array_filter( $this->rows, static fn( $r ) => ! empty( $r['is_active'] ) ) );
        }
        return $this->rows;
    }
    public function get_var( $q ) { return null; }
    public function query( $q ) { return 0; }
    public function update( ...$a ) { return 1; }
}
$wpdb = new NAWS_Test_WPDB();
require_once dirname( __DIR__ ) . '/includes/class-naws-database.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function by_id( array $mods ): array {
    $out = [];
    foreach ( $mods as $m ) { $out[ $m['module_id'] ] = $m; }
    return $out;
}

$base    = [ 'module_id' => '70:ee:50:00:00:01', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAMain',    'module_name' => 'Base',    'is_active' => 1, 'data_types' => '[]' ];
$outdoor = [ 'module_id' => '02:00:00:00:00:02', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAModule1', 'module_name' => 'Outdoor', 'is_active' => 1, 'data_types' => '[]' ];
$gast    = [ 'module_id' => '03:00:00:00:00:03', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAModule4', 'module_name' => 'Gast',    'is_active' => 1, 'data_types' => '[]' ];
$wpdb->rows = [ $base, $outdoor, $gast ];

echo "\nOhne Alias bleibt alles beim Netatmo-Namen\n" . str_repeat( '-', 74 ) . "\n";
$mods = by_id( NAWS_Database::get_modules( true ) );
check( 'display_name = module_name, solange kein Alias gesetzt ist', $mods[ $outdoor['module_id'] ]['display_name'], 'Outdoor' );
check( 'custom_name ist leer', $mods[ $outdoor['module_id'] ]['custom_name'], '' );
check( 'type_display_name() ohne Alias ist leer (Aufrufer nimmt sein festes Abzeichen)', NAWS_Helpers::type_display_name( 'NAModule1' ), '' );

echo "\nAlias setzen\n" . str_repeat( '-', 74 ) . "\n";
check( 'set_display_name() meldet Erfolg', NAWS_Database::set_display_name( $outdoor['module_id'], '  Rose Gallery  ' ), true );
NAWS_Database::set_display_name( $base['module_id'], 'Organ Corner' );
$queries_before = $wpdb->queries;
$mods = by_id( NAWS_Database::get_modules( true ) );
check( 'Alias gilt sofort, obwohl die Modulliste noch im Transient liegt', $wpdb->queries, $queries_before );
check( 'display_name traegt den Alias (getrimmt)', $mods[ $outdoor['module_id'] ]['display_name'], 'Rose Gallery' );
check( 'module_name bleibt der Netatmo-Name', $mods[ $outdoor['module_id'] ]['module_name'], 'Outdoor' );
check( 'custom_name traegt den Alias', $mods[ $outdoor['module_id'] ]['custom_name'], 'Rose Gallery' );
check( 'Basis bekommt ihren eigenen Alias', $mods[ $base['module_id'] ]['display_name'], 'Organ Corner' );
check( 'Modul ohne Alias bleibt unberuehrt', $mods[ $gast['module_id'] ]['display_name'], 'Gast' );
check( 'get_modules(false) traegt ihn ebenso', by_id( NAWS_Database::get_modules( false ) )[ $outdoor['module_id'] ]['display_name'], 'Rose Gallery' );
check( 'Alias steht in der Option, nicht in der Modultabelle', get_option( NAWS_Database::OPT_DISPLAY_NAMES ), [ $outdoor['module_id'] => 'Rose Gallery', $base['module_id'] => 'Organ Corner' ] );

echo "\nAbzeichen je Modultyp\n" . str_repeat( '-', 74 ) . "\n";
check( 'type_display_name(NAModule1) = Alias des Aussenmoduls', NAWS_Helpers::type_display_name( 'NAModule1' ), 'Rose Gallery' );
check( 'type_display_name(NAMain) = Alias der Basis', NAWS_Helpers::type_display_name( 'NAMain' ), 'Organ Corner' );
check( 'type_display_name() fuer einen Typ ohne Modul ist leer', NAWS_Helpers::type_display_name( 'NAModule2' ), '' );
check( 'display_name() nimmt den Alias', NAWS_Helpers::display_name( $mods[ $outdoor['module_id'] ] ), 'Rose Gallery' );
check( 'display_name() faellt ohne Feld auf module_name zurueck', NAWS_Helpers::display_name( $gast ), 'Gast' );

echo "\nInterne Kennungen bleiben am Netatmo-Namen\n" . str_repeat( '-', 74 ) . "\n";
NAWS_Database::set_display_name( $gast['module_id'], 'Gästezimmer Süd' );
$slugs = NAWS_Helpers::indoor_module_slugs();
check( 'Slug des Innenmoduls bleibt "gast"', $slugs[0]['slug'], 'gast' );
check( 'Anzeigename des Innenmoduls ist der Alias', $slugs[0]['name'], 'Gästezimmer Süd' );
check( 'Kartenkennung bleibt Temperature_gast', NAWS_Helpers::module_param_map()['NAModule4_gast'][0], 'Temperature_gast' );

echo "\nAlias pruefen und loeschen\n" . str_repeat( '-', 74 ) . "\n";
NAWS_Database::set_display_name( $gast['module_id'], '<b>Gast</b>zimmer' );
check( 'HTML wird entfernt', by_id( NAWS_Database::get_modules( true ) )[ $gast['module_id'] ]['display_name'], 'Gastzimmer' );
NAWS_Database::set_display_name( $gast['module_id'], str_repeat( 'ä', 60 ) );
check( 'Alias wird auf 40 Zeichen gekuerzt (mehrbytefest)', mb_strlen( by_id( NAWS_Database::get_modules( true ) )[ $gast['module_id'] ]['display_name'] ), NAWS_Database::DISPLAY_NAME_MAX );
NAWS_Database::set_display_name( $gast['module_id'], '   ' );
check( 'leerer Alias loescht den Eintrag', array_key_exists( $gast['module_id'], get_option( NAWS_Database::OPT_DISPLAY_NAMES ) ), false );
check( 'danach wieder der Netatmo-Name', by_id( NAWS_Database::get_modules( true ) )[ $gast['module_id'] ]['display_name'], 'Gast' );
check( 'leere module_id wird abgelehnt', NAWS_Database::set_display_name( '', 'X' ), false );
$GLOBALS['naws_test_options'][ NAWS_Database::OPT_DISPLAY_NAMES ] = 'kaputt';
check( 'kaputte Option wird wie keine behandelt', by_id( NAWS_Database::get_modules( true ) )[ $outdoor['module_id'] ]['display_name'], 'Outdoor' );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
