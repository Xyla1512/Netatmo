<?php
/**
 * Rendert templates/live.php gegen eine gemockte WordPress-Umgebung.
 *
 * Das Dashboard war die groesste Quelle offengelegter MAC-Adressen: die
 * Modul-IDs standen im JSON-Block als MODULE4_INFO.id, an jeder
 * Chart-Konfiguration und in der Zuordnungstabelle data-module4 — und von
 * dort schickte sie live-boot.js mit jedem Chart-Aufruf zurueck an
 * admin-ajax.php. Der Browser bekommt jetzt ueberall die oeffentliche
 * Referenz.
 *
 * Geprueft wird am erzeugten Markup, nicht an den Variablen davor: was
 * gemeint ist, ist genau das, was ein Besucher im Quelltext sieht.
 *
 * Die zweite Haelfte der Pruefungen ist die Gegenprobe — die Kacheln,
 * Charts und Ausblendschalter muessen unveraendert dieselben sein.
 *
 *   php tests/test-live-render.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'NAWS_TABLE_READINGS', 'naws_readings' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts'] = [];
$GLOBALS['mods'] = [
    [ 'module_id' => '70:ee:50:a9:5a:08', 'module_type' => 'NAMain',    'module_name' => 'Basis' ],
    [ 'module_id' => '02:00:00:a9:5a:08', 'module_type' => 'NAModule1', 'module_name' => 'Aussen' ],
    [ 'module_id' => '05:00:00:04:1c:2e', 'module_type' => 'NAModule2', 'module_name' => 'Wind' ],
    [ 'module_id' => '05:00:00:03:9d:88', 'module_type' => 'NAModule3', 'module_name' => 'Regen' ],
    [ 'module_id' => '03:00:00:0d:aa:ca', 'module_type' => 'NAModule4', 'module_name' => 'Gast' ],
    [ 'module_id' => '03:00:00:0e:21:72', 'module_type' => 'NAModule4', 'module_name' => 'Sleeping' ],
];

// ── Minimale WordPress-Oberflaeche ───────────────────────────────────────
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return $s; }
// Die Uebersetzungsfunktionen kommen aus dem gemeinsamen Stub: seit 1.9.9
// uebersetzt das Plugin ueber gettext und nicht mehr ueber NAWS_Lang.
require_once __DIR__ . '/i18n-stubs.php';
function wp_unique_id() { static $n = 0; return ++$n; }
function wp_create_nonce( $a ) { return 'nonce'; }
function admin_url( $p ) { return '/wp-admin/' . $p; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function wp_kses( $s, $a ) { return $s; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t ?? time() ); }
function get_bloginfo( $k ) { return 'Testseite'; }

class NAWS_Database {
    public static function get_modules( $active_only = false ) { return $GLOBALS['mods']; }
}
class NAWS_Colors {
    public static function get( $k ) { return '#000000'; }
}
class NAWS_Icons {
    public static function get_current_set() { return 'line'; }
    public static function get_set() { return [ 'temp' => '<svg></svg>' ]; }
}
class NAWS_Weather_State {
    public static function get_current() { return [ 'state' => '' ]; }
    public static function wmo_to_state( $c, $day = true ) { return ''; }
}
class NAWS_Weather_Icons {
    public static function render( $s, $px = 96 ) { return ''; }
    public static function render_inline( $s, $px = 28 ) { return ''; }
}
class NAWS_Forecast {
    // Keine Vorhersage: der Abschnitt faellt weg und bringt keine Module mit.
    public static function get_forecast( $days ) { return [ 'error' => 'kein Netz' ]; }
}

// Der Drucktrend fragt zwei Werte ab; ohne Antwort meldet er "stabil".
class NAWS_Test_Wpdb {
    public $prefix = 'wp_';
    public function prepare( $q, ...$a ) { return $q; }
    public function get_var( $q ) { return null; }
}
$GLOBALS['wpdb'] = new NAWS_Test_Wpdb();

require_once NAWS_PLUGIN_DIR . 'includes/class-naws-helpers.php';

$fail = 0;
function check( $name, $got, $want ) {
    global $fail;
    if ( $got === $want ) { printf( "  ok    %s\n", $name ); return; }
    $fail++;
    printf( "  FAIL  %s\n          erwartet %s\n          ist      %s\n",
        $name, var_export( $want, true ), var_export( $got, true ) );
}

function render( array $options ): string {
    $GLOBALS['opts'] = $options;
    $atts = [ 'title' => 'Wetter', 'refresh' => '60' ];
    ob_start();
    include NAWS_PLUGIN_DIR . 'templates/live.php';
    return ob_get_clean();
}

/** Findet jede MAC-Adresse im Markup. */
function macs( string $html ): array {
    preg_match_all( '/(?:[0-9a-f]{2}:){5}[0-9a-f]{2}/i', $html, $m );
    return array_values( array_unique( $m[0] ) );
}

/** Liest den JSON-Datenblock, aus dem live-boot.js bootet. */
function payload( string $html ): array {
    preg_match( '/data-naws="live"[^>]*>(.*?)<\/script>/s', $html, $m );
    return json_decode( $m[1] ?? '{}', true ) ?: [];
}

/** Liest ein data-Attribut vom Container. */
function attr( string $html, string $name ): ?string {
    return preg_match( '/ ' . $name . '="([^"]*)"/', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null;
}

echo "\ntemplates/live.php\n" . str_repeat( '-', 70 ) . "\n";

$html = render( [] );
$data = payload( $html );

// ── Worum es geht ────────────────────────────────────────────────────────
check( 'keine MAC-Adresse im gerenderten Markup', macs( $html ), [] );

check( 'die Charts nennen ihr Modul als Referenz',
    array_values( array_unique( array_column( $data['CHART_CONFIGS'] ?? [], 'module_ref' ) ) ),
    [ 'outdoor', 'indoor', 'wind', 'rain', 'in-gast', 'in-sleeping' ] );

check( 'und tragen die module_id nicht mehr mit sich',
    array_column( $data['CHART_CONFIGS'] ?? [], 'module_id' ), [] );

check( 'die Zuordnung Modul -> Slug ist nach Referenz gefuehrt',
    json_decode( attr( $html, 'data-module4' ) ?? '{}', true ),
    [ 'in-gast' => 'gast', 'in-sleeping' => 'sleeping' ] );

check( 'MODULE4_INFO nennt nur noch den Anzeigenamen',
    array_keys( $data['MODULE4_INFO']['gast'] ?? [] ), [ 'name' ] );

check( 'data-indoor ist weg — es hat nie jemand gelesen',
    attr( $html, 'data-indoor' ), null );

// ── Gegenprobe: es muss alles bleiben, wie es war ────────────────────────
check( 'dieselben Charts wie zuvor, in derselben Reihenfolge',
    array_column( $data['CHART_CONFIGS'] ?? [], 'key' ),
    [ 'Temperature', 'Humidity', 'Temperature_indoor', 'Pressure', 'CO2', 'Noise',
      'WindStrength', 'GustStrength', 'Rain',
      'Temperature_gast', 'Humidity_gast', 'CO2_gast',
      'Temperature_sleeping', 'Humidity_sleeping', 'CO2_sleeping' ] );

check( 'jeder Chart weiss weiter, welchen Messwert er zieht',
    array_slice( array_column( $data['CHART_CONFIGS'] ?? [], 'param' ), 0, 6 ),
    [ 'Temperature', 'Humidity', 'Temperature', 'Pressure', 'CO2', 'Noise' ] );

check( 'jeder Chart hat sein Canvas im Markup',
    substr_count( $html, 'class="naws-chart-card"' ), 15 );

$html = render( [ 'naws_live_hidden_charts' => [ 'Rain', 'CO2_gast' ] ] );
check( 'ausgeblendete Charts verschwinden weiterhin',
    array_column( payload( $html )['CHART_CONFIGS'] ?? [], 'key' ),
    [ 'Temperature', 'Humidity', 'Temperature_indoor', 'Pressure', 'CO2', 'Noise',
      'WindStrength', 'GustStrength',
      'Temperature_gast', 'Humidity_gast',
      'Temperature_sleeping', 'Humidity_sleeping', 'CO2_sleeping' ] );

$html = render( [ 'naws_live_hidden_modules' => [ 'NAModule4_gast' ] ] );
check( 'ein abgeschaltetes Innenmodul nimmt seine Charts mit',
    array_values( array_filter( array_column( payload( $html )['CHART_CONFIGS'] ?? [], 'key' ),
        static function ( $k ) { return str_contains( $k, 'gast' ); } ) ),
    [] );

// Eine Station ohne Zusatzmodule ist der Normalfall: die meisten
// Installationen haben weder Wind- noch Regenmesser.
$GLOBALS['mods'] = [
    [ 'module_id' => '70:ee:50:a9:5a:08', 'module_type' => 'NAMain',    'module_name' => 'Basis' ],
    [ 'module_id' => '02:00:00:a9:5a:08', 'module_type' => 'NAModule1', 'module_name' => 'Aussen' ],
];
$html = render( [] );
check( 'auch die nackte Station zeigt keine MAC', macs( $html ), [] );
check( 'und zeichnet genau ihre sechs Charts',
    count( payload( $html )['CHART_CONFIGS'] ?? [] ), 6 );
check( 'ohne Innenmodul bleibt die Zuordnungstabelle leer',
    attr( $html, 'data-module4' ), '[]' );

// ── Und das Skript, das diesen Block liest ───────────────────────────────
// Der Datenblock und live-boot.js sind zwei Haelften einer Absprache: was
// das Template nicht mehr schreibt, darf das Skript nicht mehr suchen, und
// was es an admin-ajax.php schickt, muss die Referenz sein. Ein Blick in
// die ausgelieferte Datei ist das Einzige, was das hier pruefen kann —
// aber er faellt auf, sobald jemand eine der beiden Haelften anfasst.
// Kommentarzeilen zaehlen nicht mit: dass dort steht, warum es die
// module_id nicht mehr gibt, ist der Sinn der Sache.
$js = (string) preg_replace( '/^\s*\/\/.*$/m', '',
    (string) file_get_contents( NAWS_PLUGIN_DIR . 'assets/js/live-boot.js' ) );
check( 'live-boot.js ordnet keinen Messwert mehr ueber eine module_id zu',
    str_contains( $js, 'r.module_id' ), false );
check( 'und schickt in keiner Anfrage eine',
    str_contains( $js, 'params.module_id' ), false );
check( 'es ordnet die Messwerte ueber die Referenz einem Innenmodul zu',
    str_contains( $js, 'MODULE4_SLUGS[r.module_ref]' ), true );
check( 'und fragt die Chartdaten mit der Referenz an',
    str_contains( $js, 'params.module_ref=' ), true );

// Eine Seite, die vor dem Update in einen Seitencache gelaufen ist, zeigt
// noch auf die alte Skript-Adresse — aber unter dieser Adresse liegt jetzt
// die neue Datei. Trifft ein leerer Browser-Cache darauf, liest das neue
// Skript ein altes Markup, in dem die Charts noch module_id heissen. Ohne
// diesen Rueckfall schickte es gar keinen Modulfilter, und der Server
// antwortete mit den Messwerten *aller* Module in einem Diagramm.
check( 'ein vor dem Update gecachtes Markup fuehrt nicht zu vermischten Charts',
    str_contains( $js, 'cfg.module_ref||cfg.module_id' ), true );

echo str_repeat( '-', 70 ) . "\n";
echo $fail ? "$fail fehlgeschlagen\n\n" : "alles bestanden\n\n";
exit( $fail ? 1 : 0 );
