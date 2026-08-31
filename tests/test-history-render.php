<?php
/**
 * Rendert templates/history.php gegen eine gemockte WordPress-Umgebung.
 *
 * Das Template zeichnete die fuenf festen Jahresvergleiche frueher als fuenf
 * gleich aufgebaute if-Bloecke, dazu eine Schleife fuer die Innenmodule.
 * Seit die Reihenfolge einstellbar ist, macht eine einzige Schleife die
 * Arbeit — und die Reihenfolge, die sie ausgibt, ist das, was der Leser vom
 * Sortieren im Backend ueberhaupt zu sehen bekommt.
 *
 * Geprueft wird deshalb am erzeugten Markup und nicht an den Definitionen:
 * dass jeder Chart sein Canvas und seine Legende mit der ID bekommt, unter
 * der history-boot.js sie sucht, dass ausgeblendete Charts verschwinden
 * ohne die Ordnung der uebrigen zu stoeren, und dass am Ende der Hinweis
 * statt des Ladebalkens steht, wenn nichts mehr uebrig ist.
 *
 *   php tests/test-history-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];
$GLOBALS['mods'] = [
    [ 'module_id' => '70:ee:50:a9:5a:08', 'module_type' => 'NAMain',    'module_name' => 'Basis' ],
    [ 'module_id' => '02:00:00:a9:5a:08', 'module_type' => 'NAModule1', 'module_name' => 'Aussen' ],
    [ 'module_id' => '03:00:00:0d:aa:ca', 'module_type' => 'NAModule4', 'module_name' => 'Gast' ],
];

function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
require_once __DIR__ . '/i18n-stubs.php';
function wp_unique_id() { static $n = 0; return ++$n; }
function wp_create_nonce( $a ) { return 'nonce'; }
function admin_url( $p ) { return '/wp-admin/' . $p; }
function wp_json_encode( $d ) { return json_encode( $d ); }

class NAWS_Database {
    public static function get_modules( $a = false ) { return $GLOBALS['mods']; }
    public static function get_daily_data_range() {
        return [ 'date_begin' => '2022-01-01', 'date_end' => '2025-12-31' ];
    }
}
class NAWS_Colors {
    public static function get_history_palette() { return []; }
    public static function get_chart_theme() { return []; }
}

require_once $PLUGIN . 'includes/class-naws-helpers.php';

function render( array $options ): string {
    $GLOBALS['opts'] = $options;
    $atts = [ 'title' => 'T', 'year' => '' ];
    ob_start();
    include dirname( __DIR__ ) . '/templates/history.php';
    return ob_get_clean();
}

/** Liest die gezeichneten Charts in Dokumentreihenfolge. */
function charts( string $html ): array {
    preg_match_all( '/naws-hc-wrap" data-chart="([^"]+)"/', $html, $m );
    return $m[1];
}

$fail = 0;
function check( $name, $got, $want ) {
    global $fail;
    if ( $got === $want ) { printf( "  ok    %s\n", $name ); return; }
    $fail++;
    printf( "  FAIL  %s\n          erwartet %s\n          ist      %s\n",
        $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\ntemplates/history.php\n" . str_repeat( '-', 70 ) . "\n";

$html = render( [] );
check( 'ohne Option: die gewohnte Reihenfolge, Innenmodul hinten',
    charts( $html ),
    [ 'temp_minmax', 'temp_avg', 'pressure', 'rain', 'humidity',
      'indoor_temp_gast', 'indoor_humidity_gast' ] );

// Jeder Block braucht Canvas und Legende mit passender ID, sonst findet
// history-boot.js sie nicht.
check( 'jeder Chart bekommt sein Canvas',
    substr_count( $html, '-temp_minmax" height="90"' ), 1 );
check( 'und seine Legende',
    substr_count( $html, '-leg-indoor_temp_gast"' ), 1 );
check( 'die Beschriftung steht im Kopf',
    (bool) strpos( $html, '<div class="naws-hc-title">Annual Precipitation</div>' ), true );

$html = render( [ 'naws_history_chart_order' => [ 'rain', 'indoor_temp_gast' ] ] );
check( 'die gespeicherte Reihenfolge zieht Regen nach vorn',
    charts( $html ),
    [ 'rain', 'indoor_temp_gast', 'temp_minmax', 'temp_avg', 'pressure',
      'humidity', 'indoor_humidity_gast' ] );

$html = render( [
    'naws_history_chart_order'   => [ 'rain', 'indoor_temp_gast' ],
    'naws_history_hidden_charts' => [ 'rain', 'humidity' ],
] );
check( 'ausgeblendete Charts fehlen, der Rest behaelt seine Ordnung',
    charts( $html ),
    [ 'indoor_temp_gast', 'temp_minmax', 'temp_avg', 'pressure',
      'indoor_humidity_gast' ] );

$html = render( [ 'naws_history_hidden_charts' => [
    'temp_minmax', 'temp_avg', 'pressure', 'rain', 'humidity',
    'indoor_temp_gast', 'indoor_humidity_gast',
] ] );
check( 'sind alle aus, erscheint der Hinweis statt des Laders',
    [ charts( $html ), (bool) strpos( $html, 'naws-hist-all-hidden' ) ],
    [ [], true ] );

echo str_repeat( '-', 70 ) . "\n";
echo $fail ? "$fail fehlgeschlagen\n\n" : "alles bestanden\n\n";
exit( $fail ? 1 : 0 );
