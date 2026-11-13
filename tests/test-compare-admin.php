<?php
/**
 * Tests fuer den Baukasten von [naws_compare] im Backend:
 * NAWS_Compare::catalogue_for_admin(), die Vorschau per Admin-AJAX und die
 * Bausteine auf der Shortcode-Seite.
 *
 *   php tests/test-compare-admin.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C', 'rain_unit' => 'mm', 'wind_unit' => 'kmh' ] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_date( $fmt, $ts = null ) {
    if ( $ts === null && $fmt === 'Y-m-d' ) { return '2026-11-13'; }
    return gmdate( $fmt, $ts ?? time() );
}
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, ',', '.' ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function get_locale() { return 'de_DE'; }
function wp_unique_id( $p = '' ) { return $p . '1'; } // fest, damit Vorschau und Shortcode gleich aussehen
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_create_nonce( $a ) { return 'nonce123'; }
class NAWS_Test_Sent extends Exception {}
$GLOBALS['sent']  = null;
$GLOBALS['hooks'] = [];
$GLOBALS['referer'] = [];
function add_action( $hook, ...$a ) { $GLOBALS['hooks'][] = $hook; }
function check_ajax_referer( $action, $field = false ) { $GLOBALS['referer'][] = $action; return true; }
function nocache_headers() {}
function wp_unslash( $s ) { return $s; }
function wp_send_json_success( $data = null ) { $GLOBALS['sent'] = [ 'ok' => true, 'status' => 200, 'data' => $data ]; throw new NAWS_Test_Sent(); }
function wp_send_json_error( $data = null, $code = null ) { $GLOBALS['sent'] = [ 'ok' => false, 'status' => $code, 'data' => $data ]; throw new NAWS_Test_Sent(); }
$GLOBALS['naws_caps'] = [];
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['naws_caps'], true ); }
class NAWS_Logger {
    public static function warning( ...$a ) {}
    public static function error( ...$a ) {}
    public static function info( ...$a ) {}
}
class NAWS_Colors {
    public static function get_history_palette() { return [ '#427272', '#c08a3e' ]; }
    public static function get_chart_theme() { return []; }
}
const BASE = '70:ee:50:00:00:01';
const GAST = '03:00:00:00:00:02';
class NAWS_Database {
    public static $modules = [];
    public static $daily   = [];
    public static $last    = [];
    public static function last_error() { return ''; }
    public static function get_modules( $active_only = false ) {
        return $active_only ? array_values( array_filter( self::$modules, static fn( $m ) => ! empty( $m['is_active'] ) ) ) : self::$modules;
    }
    public static function get_daily_data_range( $module_id = null ) { return [ 'date_begin' => '2025-01-01', 'date_end' => '2026-11-12' ]; }
    public static function daily_coverage() {
        return [
            BASE => [ '_days' => 961, 'temp_avg' => 933, 'temp_min' => 933, 'temp_max' => 933, 'humidity_avg' => 933, 'indoor_temp_avg' => 76, 'indoor_humidity_avg' => 961, 'co2_avg' => 76, 'noise_avg' => 0, 'pressure_avg' => 961, 'rain_sum' => 961, 'wind_avg' => 961, 'gust_max' => 961 ],
            GAST => [ '_days' => 961, 'indoor_temp_avg' => 961, 'indoor_humidity_avg' => 961, 'co2_avg' => 961 ],
        ];
    }
    public static function get_daily_summaries( array $args ) {
        self::$last = $args;
        $out = [];
        foreach ( self::$daily as $row ) {
            if ( $row['module_id'] !== $args['module_id'] || $row['day_date'] < $args['date_from'] || $row['day_date'] > $args['date_to'] ) { continue; }
            $r = [ 'day_date' => $row['day_date'] ];
            foreach ( $args['fields'] as $f ) { $r[ $f ] = $row[ $f ] ?? null; }
            $out[] = $r;
        }
        return $out;
    }
}
NAWS_Database::$modules = [
    [ 'module_id' => BASE, 'station_id' => BASE, 'module_type' => 'NAMain', 'module_name' => 'Basis', 'custom_name' => '', 'display_name' => 'Basis', 'is_active' => 1 ],
    [ 'module_id' => '02:00:00:00:00:04', 'station_id' => BASE, 'module_type' => 'NAModule1', 'module_name' => 'Aussen', 'custom_name' => '', 'display_name' => 'Aussen', 'is_active' => 1 ],
    [ 'module_id' => '05:00:00:00:00:05', 'station_id' => BASE, 'module_type' => 'NAModule3', 'module_name' => 'Regen', 'custom_name' => '', 'display_name' => 'Regen', 'is_active' => 1 ],
    [ 'module_id' => GAST, 'station_id' => BASE, 'module_type' => 'NAModule4', 'module_name' => 'Gast', 'custom_name' => '', 'display_name' => 'Gast', 'is_active' => 1 ],
    [ 'module_id' => '03:00:00:00:00:03', 'station_id' => BASE, 'module_type' => 'NAModule4', 'module_name' => 'Sleeping', 'custom_name' => '', 'display_name' => 'Sleeping', 'is_active' => 0 ],
];
for ( $d = 1; $d <= 5; $d++ ) {
    NAWS_Database::$daily[] = [ 'module_id' => BASE, 'day_date' => sprintf( '2025-01-%02d', $d ), 'humidity_avg' => 80.0, 'indoor_humidity_avg' => 45.0 ];
}

require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-labels.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-month-review.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-compare.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-ajax.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function naws_state( string $ref, string $qty ): ?string {
    foreach ( NAWS_Compare::catalogue_for_admin() as $m ) {
        if ( $m['ref'] !== $ref ) { continue; }
        foreach ( $m['qtys'] as $q ) {
            if ( $q['qty'] === $qty ) { return $q['state']; }
        }
    }
    return null;
}
function call_preview( array $post, bool $allowed ): array {
    $GLOBALS['naws_caps'] = $allowed ? [ 'manage_options', 'edit_posts' ] : [];
    $GLOBALS['sent'] = null;
    $_POST = $post + [ 'nonce' => 'x' ];
    try { NAWS_Ajax::instance()->compare_preview(); } catch ( NAWS_Test_Sent $e ) {}
    $_POST = [];
    return $GLOBALS['sent'] ?? [ 'ok' => null, 'status' => null, 'data' => null ];
}

echo "\nKatalog fuer den Baukasten\n" . str_repeat( '-', 74 ) . "\n";

$cat = NAWS_Compare::catalogue_for_admin();
check( 'nur aktive Module', array_column( $cat, 'ref' ), [ 'indoor', 'outdoor', 'rain', 'in-gast' ] );
check( 'Name Basis ohne Anzeigename', $cat[0]['name'], 'Base station' );
check( 'Name Innenmodul', $cat[3]['name'], 'Gast' );
check( 'Groessen der Basis', array_column( $cat[0]['qtys'], 'qty' ), [ 'temp', 'humidity', 'co2', 'noise', 'pressure' ] );
check( 'Groessen tragen ihr Label', $cat[0]['qtys'][1]['label'], 'Humidity' );
check( 'Basis-Innentemp lueckenhaft', naws_state( 'indoor', 'temp' ), 'sparse' );
check( 'Basis-Innenfeuchte ok', naws_state( 'indoor', 'humidity' ), 'ok' );
check( 'nie gemessen = empty', naws_state( 'indoor', 'noise' ), 'empty' );
check( 'Aussen aus der Stationszeile', naws_state( 'outdoor', 'humidity' ), 'ok' );
check( 'Innenmodul aus eigener Zeile', naws_state( 'in-gast', 'co2' ), 'ok' );

echo "\nVorschau (wp_ajax_naws_compare_preview)\n" . str_repeat( '-', 74 ) . "\n";

NAWS_Ajax::instance();
check( 'Hook registriert', in_array( 'wp_ajax_naws_compare_preview', $GLOBALS['hooks'], true ), true );
check( 'kein nopriv', in_array( 'wp_ajax_nopriv_naws_compare_preview', $GLOBALS['hooks'], true ), false );
check( 'Vorschau ohne Recht 403', call_preview( [ 'a' => 'indoor:humidity' ], false )['status'], 403 );
check( 'Admin-Nonce geprueft', end( $GLOBALS['referer'] ), 'naws_admin_nonce' );
$atts = [ 'a' => 'indoor:humidity', 'b' => 'outdoor:humidity', 'from' => '', 'to' => '', 'months' => '', 'title' => '', 'height' => '360' ];
$GLOBALS['naws_caps'] = [ 'manage_options', 'edit_posts' ];
$want = NAWS_Compare::render( $atts, true );
check( 'Vorschau = Shortcode', call_preview( [ 'a' => 'indoor:humidity', 'b' => 'outdoor:humidity' ], true )['data']['html'] ?? null, $want );
check( 'Vorschau ohne Cache', NAWS_Database::$last['no_cache'] ?? null, true );
check( 'leeres b bleibt leer', substr_count( call_preview( [ 'a' => 'indoor:humidity', 'b' => '' ], true )['data']['html'] ?? '', '"axis"' ), 1 );

echo "\nShortcode-Seite und Skript\n" . str_repeat( '-', 74 ) . "\n";

$v = file_get_contents( dirname( __DIR__ ) . '/admin/views/shortcodes.php' );
check( 'Abschnitt vorhanden', str_contains( $v, '[naws_compare' ), true );
check( 'Katalog eingebettet', str_contains( $v, 'naws-cmp-catalogue' ), true );
$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.js' );
check( 'Vorschau ruft NAWS_compareInit', str_contains( $js, 'NAWS_compareInit' ), true );
check( 'eckige Klammern aus dem Shortcode entfernt', str_contains( $js, 'replace(/[\[\]"]/g, \'\')' ), true );
check( 'alte Vorschau wird abgebaut', str_contains( $js, 'getChart(old)' ), true );
check( 'Vorschau-Action', str_contains( $js, 'naws_compare_preview' ), true );
$adm = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-admin.php' );
check( 'Admin laedt das Vergleichsskript', str_contains( $adm, 'compare-boot.js' ), true );
$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'daily_coverage gruppiert nach Modul', (bool) preg_match( '/function daily_coverage\(.*?GROUP BY module_id/s', $db ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
