<?php
/**
 * Tests fuer die Sparklines im Seitenleisten-Widget (seit 2.1.0): der
 * Schalter, das Template mit und ohne Kurven, die Sanitierung der
 * Einstellung, der Shortcode und das Stylesheet.
 *
 *   php tests/test-widget-sparklines.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['naws_stored'] = [ 'wgt_days' => 5, 'wgt_width' => 250, 'wgt_scheme' => 'light', 'wgt_sparklines' => 1 ];
function get_option( $key, $default = false ) {
    return 'naws_settings' === $key ? $GLOBALS['naws_stored'] : $default;
}
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function do_action( ...$a ) {}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function absint( $n ) { return abs( (int) $n ); }
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Crypto {
    public static function is_encrypted( $s ) { return str_starts_with( (string) $s, 'ENC:' ); }
    public static function encrypt( $s ) { return 'ENC:' . $s; }
    public static function migrate() {}
}
class NAWS_Forecast {
    public static function flush_cache() {}
}

require_once $PLUGIN . 'includes/class-naws-widget-data.php';
require_once $PLUGIN . 'includes/class-naws-cron.php';
require_once $PLUGIN . 'includes/class-naws-admin.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Widget_Data::sparklines_on()\n" . str_repeat( '-', 74 ) . "\n";
foreach ( [ [ '1', true ], [ 1, true ], [ 'yes', true ], [ ' TRUE ', true ], [ 'on', true ],
            [ '0', false ], [ 0, false ], [ '', false ], [ null, false ], [ 'ja', false ], [ '2', false ] ] as list( $in, $want ) ) {
    check( 'sparklines_on( ' . var_export( $in, true ) . ' )', NAWS_Widget_Data::sparklines_on( $in ), $want );
}

echo "\ntemplates/weather-widget.php\n" . str_repeat( '-', 74 ) . "\n";

function render_widget( array $vars ): string {
    $naws_wgt = [
        'empty' => false,
        'temp'  => [ 'value' => '12.3', 'unit' => '°C' ],
        'tiles' => [ [ 'key' => 'rain', 'value' => '4.2', 'unit' => 'mm' ], [ 'key' => 'wind', 'value' => '6', 'unit' => 'km/h' ] ],
        'days'  => [],
    ];
    $naws_wgt_state  = '';
    $naws_wgt_place  = '';
    $naws_wgt_time   = '';
    $naws_wgt_width  = 250;
    $naws_wgt_scheme = 'light';
    extract( $vars, EXTR_OVERWRITE );
    ob_start();
    include dirname( __DIR__ ) . '/templates/weather-widget.php';
    return ob_get_clean();
}

$ohne = render_widget( [] );
check( 'ohne Variable: keine Kurve (wie 2.0.2)', str_contains( $ohne, 'naws-wgt-spark' ), false );

$leer = render_widget( [ 'naws_wgt_spark' => [ 'temp' => '', 'chips' => [] ] ] );
check( 'leere Kurven: kein Kasten',              str_contains( $leer, 'naws-wgt-spark' ), false );
check( 'leere Kacheln: Regen und Wind wie bisher', substr_count( $leer, 'class="naws-wgt-chip"' ), 2 );

function chip( string $key, array $lines ): array {
    return [ 'key' => $key, 'name' => strtoupper( $key ), 'value' => '1.5', 'unit' => 'u', 'lines' => $lines, 'curve' => '<span class="naws-sl">' . $key . '</span>' ];
}
$fuenf = [ chip( 'humidity', [ 'Low 1', 'High 2' ] ), chip( 'pressure', [ 'L', 'H' ] ), chip( 'wind', [ 'W', 'X' ] ), chip( 'rain', [] ), chip( 'co2', [ 'C <b>', 'D' ] ) ];
$mit = render_widget( [ 'naws_wgt_spark' => [ 'temp' => '<span class="naws-sl">T</span>', 'chips' => $fuenf ] ] );
check( 'Temperatur im Kopf',                     str_contains( $mit, '<div class="naws-wgt-spark naws-wgt-spark--head"><span class="naws-sl">T</span></div>' ), true );
check( 'fuenf Kacheln im Raster',                substr_count( $mit, 'class="naws-wgt-chip"' ), 5 );
check( 'Raster hat eigene Klasse',               str_contains( $mit, '<div class="naws-wgt-chips naws-wgt-chips--spark">' ), true );
check( 'alte Regen/Wind-Werte weichen',          str_contains( $mit, '4.2' ), false );
check( 'Name der Kachel',                        str_contains( $mit, '<span class="naws-wgt-k">HUMIDITY</span>' ), true );
check( 'Wert mit Einheit',                       str_contains( $mit, '<span class="naws-wgt-v">1.5<span class="naws-wgt-sub"> u</span></span>' ), true );
check( 'Tief/Hoch als zwei Teile',               str_contains( $mit, '<span class="naws-wgt-range"><span>Low 1</span><span>High 2</span></span>' ), true );
check( 'Tief/Hoch escaped',                      str_contains( $mit, 'C &lt;b&gt;' ), true );
check( 'leere Nebenzeile faellt weg',            substr_count( $mit, 'naws-wgt-range' ), 4 );
check( 'Kurve in der Kachel',                    str_contains( $mit, '<div class="naws-wgt-spark"><span class="naws-sl">co2</span></div>' ), true );
check( 'Kurve steht unter der Nebenzeile',       strpos( $mit, 'High 2' ) < strpos( $mit, '>humidity<' ), true );

$nurkopf = render_widget( [ 'naws_wgt_spark' => [ 'temp' => '<span class="naws-sl">T</span>', 'chips' => [] ] ] );
check( 'ohne Kacheldaten: Kopfkurve, Regen und Wind wie bisher', [ substr_count( $nurkopf, 'class="naws-wgt-spark' ), substr_count( $nurkopf, 'class="naws-wgt-chip"' ) ], [ 1, 2 ] );

echo "\nNAWS_Admin::sanitize_settings()\n" . str_repeat( '-', 74 ) . "\n";
$admin = ( new ReflectionClass( 'NAWS_Admin' ) )->newInstanceWithoutConstructor();
check( 'Haken an',                               $admin->sanitize_settings( [ 'wgt_sparklines' => '1' ] )['wgt_sparklines'] ?? null, 1 );
check( 'verstecktes Feld 0 schaltet aus',        $admin->sanitize_settings( [ 'wgt_sparklines' => '0' ] )['wgt_sparklines'] ?? null, 0 );
check( 'ohne Feld bleibt der Haken stehen',      $admin->sanitize_settings( [ 'wgt_days' => '3' ] )['wgt_sparklines'] ?? null, 1 );

echo "\nShortcode und Stylesheet\n" . str_repeat( '-', 74 ) . "\n";
$sc = (string) file_get_contents( $PLUGIN . 'includes/class-naws-shortcodes.php' );
check( 'Attribut mit der Einstellung als Vorgabe', str_contains( $sc, "'sparklines' => (string) ( \$opts['wgt_sparklines'] ?? 0 )," ), true );
check( 'der Schalter entscheidet',                 str_contains( $sc, "NAWS_Widget_Data::sparklines_on( \$atts['sparklines'] )" ), true );
check( 'das Skript nur mit Kurven',                str_contains( $sc, "if ( \$naws_wgt_spark['temp'] !== '' || \$naws_wgt_spark['chips'] ) {" ), true );
$css = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
check( 'Kurven fuellen ihre Kachel',               str_contains( $css, '.naws-wgt-spark .naws-sl svg { width:100%;' ), true );
check( 'schmal: Teile untereinander',            str_contains( $css, '.naws-wgt-range > span { display:block; }' ), true );
check( 'breit: Teile in einer Zeile',            str_contains( $css, '@container (min-width: 460px) { .naws-wgt-range > span { display:inline; white-space:nowrap; } .naws-wgt-range > span + span::before { content:" · "; } }' ), true );
check( 'Kacheln zweispaltig',                     str_contains( $css, '.naws-wgt-chips--spark { display:grid; grid-template-columns:1fr 1fr; }' ), true );
check( 'transparent zeichnet in der Textfarbe',    str_contains( $css, '.naws-wgt--transparent .naws-sl-line' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
