<?php
/**
 * Tests fuer NAWS_Compare::render() und templates/compare.php.
 *
 * Die Rechnung steht in test-compare.php; hier geht es um das, was in die
 * Seite kommt: JSON-Daten je Reihe, Namen, Achsen, Einheiten, Leerfaelle
 * und dass keine MAC-Adresse im HTML steht.
 *
 *   php tests/test-compare-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C', 'rain_unit' => 'mm', 'wind_unit' => 'kmh' ] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
// "heute" steht fest auf dem 13.11.2026.
function wp_date( $fmt, $ts = null ) {
    if ( $ts === null && $fmt === 'Y-m-d' ) { return '2026-11-13'; }
    return gmdate( $fmt, $ts ?? time() );
}
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, ',', '.' ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function get_locale() { return 'de_DE'; }
$GLOBALS['naws_uid'] = 0;
function wp_unique_id( $p = '' ) { return $p . ( ++$GLOBALS['naws_uid'] ); }
$GLOBALS['naws_test_can_edit'] = false;
function current_user_can( $cap ) { return $GLOBALS['naws_test_can_edit']; }
class NAWS_Logger {
    public static function warning( ...$a ) {}
    public static function error( ...$a ) {}
    public static function info( ...$a ) {}
}
class NAWS_Colors {
    public static function get_history_palette() { return [ '#427272', '#c08a3e', '#7a5ea8' ]; }
    public static function get_chart_theme() { return [ 'grid' => '#eee', 'tick' => '#777', 'tooltip_bg' => '#fff', 'tooltip_title' => '#000', 'tooltip_text' => '#333', 'axis_title' => '#555' ]; }
}

const BASE = '70:ee:50:00:00:01';
const GAST = '03:00:00:00:00:02';
const SLEEP = '03:00:00:00:00:03';
class NAWS_Database {
    public static $modules = [];
    public static $daily   = [];
    public static $last    = [];
    public static function last_error() { return ''; }
    public static function get_modules( $active_only = false ) {
        return $active_only ? array_values( array_filter( self::$modules, static fn( $m ) => ! empty( $m['is_active'] ) ) ) : self::$modules;
    }
    public static function get_daily_data_range( $module_id = null ) { return [ 'date_begin' => '2025-01-01', 'date_end' => '2026-11-12' ]; }
    public static function get_daily_summaries( array $args ) {
        self::$last = $args;
        $out = [];
        foreach ( self::$daily as $row ) {
            if ( $row['module_id'] !== $args['module_id'] || $row['day_date'] < $args['date_from'] || $row['day_date'] > $args['date_to'] ) {
                continue;
            }
            $r = [ 'day_date' => $row['day_date'] ];
            foreach ( $args['fields'] as $f ) {
                $r[ $f ] = $row[ $f ] ?? null;
            }
            $out[] = $r;
        }
        return $out;
    }
}
NAWS_Database::$modules = [
    [ 'module_id' => BASE, 'station_id' => BASE, 'module_type' => 'NAMain', 'module_name' => 'Basis', 'custom_name' => 'Museum', 'display_name' => 'Museum', 'is_active' => 1 ],
    [ 'module_id' => '02:00:00:00:00:04', 'station_id' => BASE, 'module_type' => 'NAModule1', 'module_name' => 'Aussen', 'custom_name' => '', 'display_name' => 'Aussen', 'is_active' => 1 ],
    [ 'module_id' => '05:00:00:00:00:05', 'station_id' => BASE, 'module_type' => 'NAModule3', 'module_name' => 'Regen', 'custom_name' => '', 'display_name' => 'Regen', 'is_active' => 1 ],
    [ 'module_id' => GAST, 'station_id' => BASE, 'module_type' => 'NAModule4', 'module_name' => 'Gast', 'custom_name' => '', 'display_name' => 'Gast', 'is_active' => 1 ],
    [ 'module_id' => SLEEP, 'station_id' => BASE, 'module_type' => 'NAModule4', 'module_name' => 'Sleeping', 'custom_name' => '', 'display_name' => 'Sleeping', 'is_active' => 0 ],
];
for ( $d = 1; $d <= 10; $d++ ) {
    $day = sprintf( '2025-01-%02d', $d );
    NAWS_Database::$daily[] = [ 'module_id' => BASE, 'day_date' => $day, 'temp_avg' => 5.0, 'humidity_avg' => 80.0, 'indoor_humidity_avg' => $d === 5 ? null : 45.0, 'rain_sum' => 39.6, 'indoor_temp_avg' => $d === 1 ? 21.0 : null ];
    NAWS_Database::$daily[] = [ 'module_id' => GAST, 'day_date' => $day, 'indoor_temp_avg' => 20.0 ];
    NAWS_Database::$daily[] = [ 'module_id' => SLEEP, 'day_date' => $day, 'indoor_temp_avg' => 18.0 ];
}

require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-labels.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-compare.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

function atts( string $a = 'indoor:humidity', string $b = 'outdoor:humidity', array $more = [] ): array {
    return array_merge( [ 'a' => $a, 'b' => $b, 'from' => '', 'to' => '', 'months' => '', 'title' => '', 'height' => '360' ], $more );
}
function naws_test_json( string $html ): ?array {
    if ( ! preg_match( '#<script type="application/json" class="naws-cmp-data">(.*?)</script>#s', $html, $m ) ) {
        return null;
    }
    return json_decode( $m[1], true );
}
function naws_cmp_series( array $atts ): array {
    return naws_test_json( NAWS_Compare::render( $atts ) )['series'] ?? [];
}
function naws_with_settings( array $s, callable $f ) {
    $old = $GLOBALS['naws_test_options']['naws_settings'];
    $GLOBALS['naws_test_options']['naws_settings'] = array_merge( $old, $s );
    $r = $f();
    $GLOBALS['naws_test_options']['naws_settings'] = $old;
    return $r;
}
function render_as_editor( array $atts ): string {
    $GLOBALS['naws_test_can_edit'] = true;
    $h = NAWS_Compare::render( $atts );
    $GLOBALS['naws_test_can_edit'] = false;
    return $h;
}
function naws_canvas_id( string $html ): string {
    return preg_match( '#<canvas id="([^"]+)"#', $html, $m ) ? $m[1] : '';
}

echo "\nZwei Reihen\n" . str_repeat( '-', 74 ) . "\n";

$h = NAWS_Compare::render( atts( 'indoor:humidity', 'outdoor:humidity', [ 'title' => 'Feuchte' ] ) );
$j = naws_test_json( $h );
check( 'JSON vorhanden', is_array( $j ), true );
check( 'zwei Reihen', count( $j['series'] ?? [] ), 2 );
check( 'gleiche Einheit eine Achse', [ $j['series'][0]['axis'], $j['series'][1]['axis'], $j['right'] ], [ 'y', 'y', false ] );
check( 'Typname, wenn kein Anzeigename', $j['series'][1]['name'], 'Humidity · Outdoor' );
check( 'Anzeigename schlaegt Typname', $j['series'][0]['name'], 'Humidity · Museum' );
check( 'Achse ueber die Daten', [ $j['dates'][0], end( $j['dates'] ) ], [ '2025-01-01', '2025-01-10' ] );
check( 'Luecke als null', $j['series'][0]['values'][4], null );
check( 'Einheit', $j['series'][0]['unit'], '%' );
check( 'Feuchte ohne Nachkommastellen', $j['series'][0]['decimals'], 0 );
check( 'Locale', [ $j['locale'], $j['decimal'] ], [ 'de-DE', ',' ] );
check( 'zwei Farben', count( $j['colors'] ), 2 );
check( 'Titel', str_contains( $h, 'Feuchte</figcaption>' ), true );
check( 'keine MAC im HTML', (bool) preg_match( '/([0-9a-f]{2}:){5}[0-9a-f]{2}/i', $h ), false );
check( 'Wurzel mit data-naws-cmp', str_contains( $h, '<figure class="naws-cmp" data-naws-cmp>' ), true );
check( 'Frontend nutzt den Cache', NAWS_Database::$last['no_cache'] ?? null, false );

$t = naws_test_json( NAWS_Compare::render( atts( 'outdoor:temp', 'indoor:humidity' ) ) );
check( 'zwei Einheiten zwei Achsen', [ $t['series'][1]['axis'], $t['right'] ], [ 'y1', true ] );
check( 'Innenmodul + Aussen gleiche Achse', naws_test_json( NAWS_Compare::render( atts( 'in-gast:temp', 'outdoor:temp' ) ) )['right'], false );
check( 'Innenmodul-Name', naws_cmp_series( atts( 'in-gast:temp', '' ) )[0]['name'], 'Temperature · Gast' );

echo "\nEinheiten\n" . str_repeat( '-', 74 ) . "\n";

check( 'Regen Balken', naws_cmp_series( atts( 'rain:rain', '' ) )[0]['chart'], 'bar' );
check( 'eine Reihe', count( naws_cmp_series( atts( 'rain:rain', '' ) ) ), 1 );
check( 'Fahrenheit Einheit', naws_with_settings( [ 'temperature_unit' => 'F' ], fn() => naws_cmp_series( atts( 'outdoor:temp', '' ) )[0]['unit'] ), '°F' );
check( 'Fahrenheit Wert', naws_with_settings( [ 'temperature_unit' => 'F' ], fn() => (float) naws_cmp_series( atts( 'outdoor:temp', '' ) )[0]['values'][0] ), 41.0 );
check( 'Zoll 3 Stellen', naws_with_settings( [ 'rain_unit' => 'in' ], fn() => naws_cmp_series( atts( 'rain:rain', '' ) )[0]['decimals'] ), 3 );
check( 'Zoll Wert', naws_with_settings( [ 'rain_unit' => 'in' ], fn() => naws_cmp_series( atts( 'rain:rain', '' ) )[0]['values'][0] ), 1.559 );

echo "\nFehler und Leerfaelle\n" . str_repeat( '-', 74 ) . "\n";

check( 'Tippfehler: Besucher sieht andere Reihe', count( naws_cmp_series( atts( 'outdor:humidity', 'outdoor:temp' ) ) ), 1 );
check( 'Tippfehler: Besucher sieht keinen Hinweis', str_contains( NAWS_Compare::render( atts( 'outdor:humidity', 'outdoor:temp' ) ), 'naws-notice' ), false );
check( 'Tippfehler: Redakteur sieht Angabe', str_contains( render_as_editor( atts( 'outdor:humidity', 'outdoor:temp' ) ), 'outdor:humidity' ), true );
check( 'deaktiviertes Modul: eigener Grund', str_contains( render_as_editor( atts( 'in-sleeping:temp', '' ) ), 'deactivated' ), true );
check( 'unbekanntes Innenmodul', str_contains( render_as_editor( atts( 'in-keller:temp', '' ) ), 'in-keller:temp' ), true );
check( 'beide falsch: Besucher leer', NAWS_Compare::render( atts( 'x:y', 'z:w' ) ), '' );
check( 'keine Daten im Zeitraum: Besucher leer', NAWS_Compare::render( atts( 'outdoor:temp', '', [ 'from' => '2026-01', 'to' => '2026-02' ] ) ), '' );
check( 'Basis-Innentemp: nur ein Tag', count( naws_test_json( NAWS_Compare::render( atts( 'indoor:temp', '' ) ) )['dates'] ), 1 );

echo "\nMehrere auf einer Seite, Hoehe\n" . str_repeat( '-', 74 ) . "\n";

check( 'zwei Vergleiche eindeutige IDs', naws_canvas_id( NAWS_Compare::render( atts() ) ) !== naws_canvas_id( NAWS_Compare::render( atts() ) ), true );
check( 'height geklemmt', str_contains( NAWS_Compare::render( atts( 'indoor:humidity', 'outdoor:humidity', [ 'height' => '5000' ] ) ), 'height:900px' ), true );
check( 'height Vorgabe', str_contains( NAWS_Compare::render( atts() ), 'height:360px' ), true );
NAWS_Compare::render( atts(), true );
check( 'Vorschau ohne Cache', NAWS_Database::$last['no_cache'] ?? null, true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
