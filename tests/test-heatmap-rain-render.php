<?php
/**
 * Prueft das Markup von templates/heatmap.php mit type="rain", die
 * Weitergabe des Typs an den Jahreswechsel und den Shortcode.
 *
 *   php tests/test-heatmap-rain-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [ 'naws_settings' => [ 'rain_unit' => 'mm', 'temperature_unit' => 'C' ] ];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d, '.', ',' ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';
function wp_date( $fmt, $ts = null ) { return gmdate( $fmt, $ts ?? time() ); }
function wp_unique_id( $p = '' ) { static $n = 0; return $p . ( ++$n ); }
function wp_create_nonce( $a ) { return 'testnonce'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }

class NAWS_Fonts {
    public static function available() { return [ 'inherit' => 'Inherit' ]; }
    public static function sanitize_family( $s ) { return $s; }
}

require_once $PLUGIN . 'includes/class-naws-helpers.php';
require_once $PLUGIN . 'includes/class-naws-colors.php';

class NAWS_Database {
    public static $temp  = null;
    public static $rain  = null;
    public static $calls = [];
    public static $range = [ 'date_begin' => '2024-03-28', 'date_end' => '2026-11-20' ];
    public static function get_daily_data_range( $m = null ) { return self::$range; }
    public static function get_heatmap_year( $y ) { self::$calls[] = 'temp'; return self::$temp; }
    public static function get_rain_heatmap_year( $y ) { self::$calls[] = 'rain'; return self::$rain; }
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

function grid( int $year ): array {
    $v = []; $s = [];
    for ( $m = 1; $m <= 12; $m++ ) {
        $d = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $year ) );
        $v[ $m - 1 ] = array_fill( 0, $d, null );
        $s[ $m - 1 ] = array_fill( 0, $d, null );
    }
    return [ 'values' => $v, 'sources' => $s ];
}

function render_hm( array $atts_in ): string {
    $atts = array_merge( [ 'year' => '2026', 'title' => 'Heatmap', 'legend' => 'yes', 'type' => 'temp' ], $atts_in );
    ob_start();
    include dirname( __DIR__ ) . '/templates/heatmap.php';
    return ob_get_clean();
}

echo "\ntemplates/heatmap.php, type=\"rain\"\n" . str_repeat( '-', 74 ) . "\n";

$g = grid( 2026 );
$g['values'][0][0] = 3.4; $g['sources'][0][0] = 'sum';
$g['values'][0][1] = 0.0; $g['sources'][0][1] = 'sum';
NAWS_Database::$rain = $g;
NAWS_Database::$temp = grid( 2026 );

NAWS_Database::$calls = [];
$html = render_hm( [ 'type' => 'rain' ] );

check( 'die Regendaten werden gelesen, nicht die Temperatur', NAWS_Database::$calls, [ 'rain' ] );
check( 'der Typ steht am Wrapper',          str_contains( $html, 'data-type="rain"' ), true );
check( 'dieselbe Wurzelklasse, also dasselbe CSS', str_contains( $html, 'class="naws-hm"' ), true );
check( 'dasselbe Raster: zwoelf Zeilen',    substr_count( $html, '<tr class="naws-hm-row"' ), 12 );
check( 'ein Regentag traegt seine Farbe',
    str_contains( $html, 'background:' . NAWS_Colors::rain_heatmap_color( 3.4 ) ), true );
check( 'ein trockener Tag die Trocken-Farbe',
    str_contains( $html, 'background:' . NAWS_Colors::rain_heatmap_color( 0 ) ), true );
check( 'die Beschriftung nennt Millimeter',  str_contains( $html, 'data-l="3.4 mm"' ), true );
check( 'ein trockener Tag heisst trocken',   str_contains( $html, 'data-l="Dry"' ), true );
check( 'der Wert steht roh in data-v',       str_contains( $html, 'data-v="0"' ), true );

check( 'die Legende hat ein Feld fuer trocken', str_contains( $html, 'naws-hm-legend-dry' ), true );
check( 'der Verlauf beginnt bei 0,1 mm',     str_contains( $html, '<span class="naws-hm-legend-min">0.1 mm</span>' ), true );
check( 'und endet bei 30 mm',                str_contains( $html, '<span class="naws-hm-legend-max">30 mm</span>' ), true );
check( 'der Verlauf traegt die Regenfarben',
    str_contains( $html, NAWS_Colors::get_all()['heatmap_r_30'] . ' 100%' ), true );

echo "\nDie Temperaturkarte bleibt, wie sie war\n" . str_repeat( '-', 74 ) . "\n";

NAWS_Database::$calls = [];
$t = render_hm( [] );
check( 'ohne type liest sie die Temperatur', NAWS_Database::$calls, [ 'temp' ] );
check( 'und heisst temp',                    str_contains( $t, 'data-type="temp"' ), true );
check( 'ohne Trocken-Feld',                  str_contains( $t, 'naws-hm-legend-dry' ), false );

NAWS_Database::$calls = [];
$x = render_hm( [ 'type' => '"><script>' ] );
check( 'ein unbekannter Typ faellt auf temp zurueck', NAWS_Database::$calls, [ 'temp' ] );
check( 'und landet nicht im Markup',         str_contains( $x, '<script' ), false );

echo "\nShortcode, AJAX, Skript\n" . str_repeat( '-', 74 ) . "\n";

$sc   = (string) file_get_contents( $PLUGIN . 'includes/class-naws-shortcodes.php' );
$ajax = (string) file_get_contents( $PLUGIN . 'includes/class-naws-ajax.php' );
$js   = (string) file_get_contents( $PLUGIN . 'assets/js/heatmap-boot.js' );

preg_match( '/function sc_heatmap\(.*?\n    \}/s', $sc, $scm );
check( 'der Shortcode kennt type',           str_contains( $scm[0] ?? '', "'type'" ), true );
check( 'und einen eigenen Titel fuer Regen', str_contains( $scm[0] ?? '', 'Daily Precipitation' ), true );

preg_match( '/function get_heatmap_data\(.*?\n    \}/s', $ajax, $am );
check( 'der Endpunkt liest type',            str_contains( $am[0] ?? '', "\$_POST['type']" ), true );
check( 'und holt dann die Regendaten',       str_contains( $am[0] ?? '', 'get_rain_heatmap_year' ), true );
check( 'mit der Regenfarbe',                 str_contains( $am[0] ?? '', 'rain_heatmap_color' ), true );

check( 'das Skript schickt den Typ mit',
    (bool) preg_match( "/body\.append\('type',\s*root\.getAttribute\('data-type'\)/", $js ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
