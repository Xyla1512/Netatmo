<?php
/**
 * Tests fuer das Farbschema des Seitenleisten-Widgets.
 *
 * Das Widget hing bis 1.9.10 an keiner Einstellung: seine Farben standen als
 * Literale im Stylesheet, und wer eine dunkle Seitenleiste hat, bekam eine
 * weisse Karte hinein. Seit 1.9.11 gibt es drei Schemata -- light, dark,
 * transparent -- als Einstellung und als Shortcode-Attribut. Vier Dinge sind
 * hier abgesichert: die Normalisierung kennt genau diese drei Werte und
 * faellt sonst auf light zurueck; das Template haengt die Klasse an und
 * laesst sie bei light weg; die Sanitierung der Einstellung tut dasselbe;
 * und die Klassen, die das Template schreibt, gibt es im Stylesheet.
 *
 *   php tests/test-widget-scheme.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['naws_stored'] = [ 'wgt_days' => 5, 'wgt_width' => 250, 'wgt_scheme' => 'dark' ];
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

echo "\nNAWS_Widget_Data::normalise_scheme()\n" . str_repeat( '-', 74 ) . "\n";

check( 'genau drei Schemata, light zuerst',    NAWS_Widget_Data::SCHEMES, [ 'light', 'dark', 'transparent' ] );
check( 'light bleibt light',                    NAWS_Widget_Data::normalise_scheme( 'light' ), 'light' );
check( 'dark bleibt dark',                      NAWS_Widget_Data::normalise_scheme( 'dark' ), 'dark' );
check( 'transparent bleibt transparent',        NAWS_Widget_Data::normalise_scheme( 'transparent' ), 'transparent' );
check( 'Grossschreibung und Leerraum stoeren nicht', NAWS_Widget_Data::normalise_scheme( ' Dark ' ), 'dark' );
check( 'ein unbekanntes Wort wird light',       NAWS_Widget_Data::normalise_scheme( 'blau' ), 'light' );
check( 'null wird light',                       NAWS_Widget_Data::normalise_scheme( null ), 'light' );
check( 'leer wird light',                       NAWS_Widget_Data::normalise_scheme( '' ), 'light' );
check( 'eine Zahl wird light',                  NAWS_Widget_Data::normalise_scheme( 3 ), 'light' );

echo "\ntemplates/weather-widget.php\n" . str_repeat( '-', 74 ) . "\n";

/**
 * Rendert das Template mit dem kleinsten Datensatz, der nicht leer ist:
 * eine Temperatur, keine Chips, keine Tage, kein Zustand -- dann braucht es
 * weder Icons noch Wochentage.
 */
function render_widget( array $vars ): string {
    $naws_wgt       = [ 'empty' => false, 'temp' => [ 'value' => '12.3', 'unit' => '°C' ], 'tiles' => [], 'days' => [] ];
    $naws_wgt_state = '';
    $naws_wgt_place = '';
    $naws_wgt_time  = '';
    $naws_wgt_width = 250;
    extract( $vars, EXTR_OVERWRITE );
    ob_start();
    include dirname( __DIR__ ) . '/templates/weather-widget.php';
    return ob_get_clean();
}

$hell = render_widget( [ 'naws_wgt_scheme' => 'light' ] );
check( 'light: die Wurzel traegt nur ihre Klasse',    str_contains( $hell, '<div class="naws-wgt" style=' ), true );
check( 'light: kein Modifikator',                      str_contains( $hell, 'naws-wgt--' ), false );

$dunkel = render_widget( [ 'naws_wgt_scheme' => 'dark' ] );
check( 'dark: Modifikator an der Wurzel',              str_contains( $dunkel, '<div class="naws-wgt naws-wgt--dark" style=' ), true );

$durchsichtig = render_widget( [ 'naws_wgt_scheme' => 'transparent' ] );
check( 'transparent: Modifikator an der Wurzel',       str_contains( $durchsichtig, '<div class="naws-wgt naws-wgt--transparent" style=' ), true );

$unbekannt = render_widget( [ 'naws_wgt_scheme' => 'neon' ] );
check( 'ein unbekanntes Schema faellt auf light',      str_contains( $unbekannt, 'naws-wgt--' ), false );

$ohne = render_widget( [] );
check( 'ohne Variable rendert das Template wie 1.9.10', str_contains( $ohne, '<div class="naws-wgt" style=' ), true );
check( 'und die Breite bleibt dabei',                   str_contains( $ohne, '--naws-wgt-max:250px' ), true );

echo "\nNAWS_Admin::sanitize_settings()\n" . str_repeat( '-', 74 ) . "\n";

$admin = ( new ReflectionClass( 'NAWS_Admin' ) )->newInstanceWithoutConstructor();

$out = $admin->sanitize_settings( [ 'wgt_scheme' => 'transparent' ] );
check( 'ein gueltiges Schema wird uebernommen',         $out['wgt_scheme'] ?? null, 'transparent' );

$out = $admin->sanitize_settings( [ 'wgt_scheme' => 'neon' ] );
check( 'ein ungueltiges Schema wird light',             $out['wgt_scheme'] ?? null, 'light' );

$out = $admin->sanitize_settings( [ 'wgt_days' => '3' ] );
check( 'ohne Feld bleibt das gespeicherte Schema stehen', $out['wgt_scheme'] ?? null, 'dark' );

echo "\nassets/css/frontend.css\n" . str_repeat( '-', 74 ) . "\n";

$css = file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
check( 'die Wurzel liest ihre Farben aus Variablen',    (bool) preg_match( '/\.naws-wgt\s*\{[^}]*var\(--naws-wgt-bg/s', $css ), true );
check( 'dark ist im Stylesheet definiert',              str_contains( $css, '.naws-wgt--dark' ), true );
check( 'transparent ist im Stylesheet definiert',       str_contains( $css, '.naws-wgt--transparent' ), true );
check( 'die Chips lesen ihre Farbe aus einer Variablen', (bool) preg_match( '/\.naws-wgt-chip\s*\{[^}]*var\(--naws-wgt-chip/s', $css ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
