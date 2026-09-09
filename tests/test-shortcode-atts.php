<?php
/**
 * Tests fuer die Attribut-Uebergabe an die Shortcodes: Seitenbauer wie
 * Elementor schreiben Anfuehrungszeichen gern als &quot; in den Text. WordPress
 * reicht das unveraendert durch, sanitize_key() macht daraus quotdewpointquot,
 * und der Shortcode zeigt nur noch seinen Fallback. Die Registrierung muss
 * die Entities dekodieren, bevor ein Handler die Attribute sieht.
 *
 *   php tests/test-shortcode-atts.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'NAWS_PLUGIN_URL', 'https://example.test/wp-content/plugins/xtx-integration-for-netatmo/' );
define( 'NAWS_VERSION', '0.0.0-test' );

$GLOBALS['naws_test_shortcodes'] = [];
function add_shortcode( $tag, $cb )   { $GLOBALS['naws_test_shortcodes'][ $tag ] = $cb; }
function add_action( ...$a )          {}
function wp_enqueue_style( ...$a )    {}
function wp_add_inline_style( ...$a ) {}
function esc_html( $s )               { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr( $s )               { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function sanitize_key( $k )           { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_specialchars_decode( $s, $q = ENT_NOQUOTES ) {
    return str_replace( [ '&quot;', '&#039;', '&#x27;', '&lt;', '&gt;', '&amp;' ], [ '"', "'", "'", '<', '>', '&' ], (string) $s );
}
function shortcode_atts( array $pairs, $atts, $tag = '' ) {
    $atts = (array) $atts; $out = $pairs;
    foreach ( $pairs as $name => $default ) { if ( array_key_exists( $name, $atts ) ) { $out[ $name ] = $atts[ $name ]; } }
    return $out;
}
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Colors { public static function get_inline_css() { return ''; } }
class NAWS_Calc   { public static function has( $k ) { return false; } }
class NAWS_Logger {
    public static $warnings = [];
    public static function warning( $ctx, $msg, $extra = [] ) { self::$warnings[] = $msg; }
    public static function error( ...$a ) {}
    public static function info( ...$a ) {}
}
require_once dirname( __DIR__ ) . '/includes/class-naws-shortcodes.php';
NAWS_Shortcodes::instance();

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
/** Der Schluessel, den [naws_calc] fuer $atts ins Log schreibt. sc_calc loggt
 *  jeden Schluessel nur einmal (static $logged), darum je Fall ein eigener. */
function logged_key( $atts ): string {
    NAWS_Logger::$warnings = [];
    call_user_func( $GLOBALS['naws_test_shortcodes']['naws_calc'], $atts, '' );
    $last = end( NAWS_Logger::$warnings );
    return $last ? (string) preg_replace( '/^.*: /', '', $last ) : '(nichts geloggt)';
}

echo "\nEntities in Shortcode-Attributen\n" . str_repeat( '-', 74 ) . "\n";
check( 'alle 15 Shortcodes sind registriert', count( $GLOBALS['naws_test_shortcodes'] ), 15 );
check( 'sauberes value="daylength" kommt als daylength an',         logged_key( [ 'value' => 'daylength' ] ),              'daylength' );
check( 'value=&quot;dewpoint&quot; (Elementor) kommt als dewpoint an', logged_key( [ 'value' => '&quot;dewpoint&quot;' ] ), 'dewpoint' );
check( 'value=&#039;moon_phase&#039; kommt als moon_phase an',       logged_key( [ 'value' => '&#039;moon_phase&#039;' ] ), 'moon_phase' );
check( 'ohne Attribute (WordPress uebergibt "") gibt es keinen Absturz', call_user_func( $GLOBALS['naws_test_shortcodes']['naws_calc'], '', '' ), '--' );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
