<?php
/**
 * Tests fuer die Farben der beiden Windkacheln im Live-Dashboard.
 *
 * Der Fehler: live-boot.js zeichnete Kompassrose, Zeiger und die Wind/Boeen-
 * Skala mit festen Hex-Farben. Die Einstellung "Kompassnadel" wurde als
 * CSS-Variable ausgegeben, aber nur von einer Klasse gelesen, die kein
 * Template mehr erzeugt — und die Vorschau im Erscheinungsbild zeigte den
 * Kompass in Theme-Farben, die das Frontend nie uebernahm. Fuer Wind und
 * Boeen gab es gar kein Feld.
 *
 * Seit 2.0.2 hat das Erscheinungsbild einen Reiter "Live-Dashboard: Wind"
 * mit sechs Farben; die SVG-Teile tragen Klassen, frontend.css faerbt sie
 * ueber Variablen, und die Vorgaben sind die Farben, die das Skript bisher
 * fest eingebaut hatte — eine unveraenderte Installation sieht gleich aus.
 *
 *   php tests/test-live-wind-colors.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options']         = [];
$GLOBALS['naws_test_global_settings'] = [];

// ── Minimal WordPress surface ────────────────────────────────────────────
function get_option( $key, $default = false ) {
    return $GLOBALS['naws_test_options'][ $key ] ?? $default;
}
function get_post_meta( $post_id, $key = '', $single = false ) { return ''; }
function post_type_exists( $type ) { return false; }
function wp_get_global_settings() { return $GLOBALS['naws_test_global_settings']; }
require_once __DIR__ . '/i18n-stubs.php';
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }

$PLUGIN = dirname( __DIR__ ) . '/';
require_once $PLUGIN . 'includes/class-naws-fonts.php';
require_once $PLUGIN . 'includes/class-naws-colors.php';

$passed = 0;
$failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function saved( array $option ): void {
    $GLOBALS['naws_test_options']['naws_appearance'] = $option;
    NAWS_Colors::flush_cache();
    NAWS_Fonts::flush_cache();
}

/** Schluessel -> [ Vorgabe, CSS-Variable ]. Die Vorgaben sind die alten festen Farben aus live-boot.js. */
$KEYS = [
    'live_compass_bg'      => [ '#f4fafa', '--naws-live-compass-bg' ],
    'live_compass_rose'    => [ '#427272', '--naws-live-compass-rose' ],
    'theme_compass_needle' => [ '#c0392b', '--naws-compass-needle' ],
    'live_gauge_wind'      => [ '#427272', '--naws-live-gauge-wind' ],
    'live_gauge_gust'      => [ '#7aa0a0', '--naws-live-gauge-gust' ],
    'live_gauge_peak'      => [ '#c0392b', '--naws-live-gauge-peak' ],
];

echo "\nNAWS_Colors: Vorgaben, Gruppe, CSS\n" . str_repeat( '-', 74 ) . "\n";

saved( [] );
$d = NAWS_Colors::get_defaults();
foreach ( $KEYS as $k => [ $default ] ) {
    check( "Vorgabe {$k} ist die bisher feste Farbe", $d[ $k ] ?? null, $default );
}

$groups = NAWS_Colors::get_groups();
check( 'es gibt die Gruppe live_wind', isset( $groups['live_wind'] ), true );
check( 'sie fuehrt die sechs Schluessel in Anzeigereihenfolge', $groups['live_wind']['keys'] ?? null, array_keys( $KEYS ) );
check( 'die Kompassnadel steht nicht mehr im Basis-Theme', in_array( 'theme_compass_needle', $groups['theme']['keys'], true ), false );
$unknown = array_diff( $groups['live_wind']['keys'] ?? [], array_keys( $d ) );
check( 'jeder Gruppenschluessel hat eine Vorgabe', $unknown, [] );

// Gesetzte Werte kommen als Variablen im .naws-wx-Block an — dort, wo das Dashboard sie liest.
$set = [];
$i   = 0;
foreach ( $KEYS as $k => $_ ) { $set[ $k ] = sprintf( '#%06x', 0x101010 * ( ++$i ) ); }
saved( $set );
$css = NAWS_Colors::get_inline_css();
preg_match( '/\.naws-wx \{(.*?)\}/s', $css, $m );
$wx = $m[1] ?? '';
check( 'der .naws-wx-Block ist da', $wx !== '', true );
foreach ( $KEYS as $k => [ $_, $var ] ) {
    $where = $k === 'theme_compass_needle' ? $css : $wx;
    check( "{$var} traegt den gesetzten Wert", (bool) preg_match( '/' . preg_quote( $var, '/' ) . ':\s*' . $set[ $k ] . ';/', $where ), true );
}

$clean = NAWS_Colors::sanitize( $set );
$kept = array_intersect_key( $clean, $KEYS );
ksort( $kept );
ksort( $set );
check( 'sanitize() nimmt die neuen Schluessel an', $kept, $set );
$clean = NAWS_Colors::sanitize( [ 'live_gauge_wind' => 'url(evil)' ] );
check( 'und verwirft, was keine Farbe ist', isset( $clean['live_gauge_wind'] ), false );

echo "\nlive-boot.js: Klassen statt fester Farben\n" . str_repeat( '-', 74 ) . "\n";

$js = file_get_contents( $PLUGIN . 'assets/js/live-boot.js' );
preg_match( '/var ROSE=(.*?);\s*\r?\nfunction arrowSVG/s', $js, $m );
$rose = $m[1] ?? '';
preg_match( '/function arrowSVG\(deg\)\{(.*?)\r?\n\}/s', $js, $m );
$arrow = $m[1] ?? '';
preg_match( '/function gaugeSVG\(wv,gv,gm\)\{(.*?)\r?\n\}/s', $js, $m );
$gauge = $m[1] ?? '';
check( 'ROSE gefunden', $rose !== '', true );
check( 'arrowSVG gefunden', $arrow !== '', true );
check( 'gaugeSVG gefunden', $gauge !== '', true );
foreach ( [ 'ROSE' => $rose, 'arrowSVG' => $arrow, 'gaugeSVG' => $gauge ] as $name => $src ) {
    preg_match_all( '/(?:fill|stroke)="#[0-9a-fA-F]{3,8}"/', $src, $hex );
    check( "{$name} hat keine feste Farbe mehr", $hex[0], [] );
}
$CLASSES = [
    'ROSE'     => [ 'face', 'ring', 'main', 'shade', 'minor', 'hub', 'letter', 'letter-minor' ],
    'arrowSVG' => [ 'needle', 'tail' ],
    'gaugeSVG' => [ 'track', 'gust', 'wind', 'tick', 'tick-label', 'peak', 'gauge-hub' ],
];
foreach ( $CLASSES as $name => $list ) {
    $src = [ 'ROSE' => $rose, 'arrowSVG' => $arrow, 'gaugeSVG' => $gauge ][ $name ];
    foreach ( $list as $c ) {
        check( "{$name} traegt naws-lw-{$c}", (bool) preg_match( '/class="naws-lw-' . preg_quote( $c, '/' ) . '"/', $src ), true );
    }
}
check( 'der Zeiger (Kopf und Schaft) heisst zweimal naws-lw-needle', preg_match_all( '/class="naws-lw-needle"/', $arrow ), 2 );
check( 'die Skala nutzt naws-lw-wind fuer Bogen und Zeiger', preg_match_all( '/class="naws-lw-wind"/', $gauge ) >= 2, true );
check( 'und naws-lw-gust fuer Boeen-Bogen und -Zeiger', preg_match_all( '/class="naws-lw-gust"/', $gauge ) >= 2, true );

$node = trim( (string) shell_exec( 'node --version 2>&1' ) );
if ( str_starts_with( $node, 'v' ) ) {
    exec( 'node --check ' . escapeshellarg( $PLUGIN . 'assets/js/live-boot.js' ) . ' 2>&1', $out, $rc );
    check( 'node --check live-boot.js', $rc, 0 );
}

echo "\nfrontend.css: jede Klasse hat eine Regel, die Einstellungen ihre Variable\n" . str_repeat( '-', 74 ) . "\n";

$fcss = file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
foreach ( array_merge( ...array_values( $CLASSES ) ) as $c ) {
    check( "Regel fuer .naws-wx .naws-lw-{$c}", (bool) preg_match( '/\.naws-wx \.naws-lw-' . preg_quote( $c, '/' ) . '\b[^{]*\{/', $fcss ), true );
}
/** Klasse -> [ Eigenschaft, Schluessel ] fuer die sechs einstellbaren Farben. */
$BOUND = [
    'face'      => [ 'fill',   'live_compass_bg' ],
    'track'     => [ 'stroke', 'live_compass_bg' ], // Franks Wunsch 25.09.: die leere Laufbahn der Skala wie der Kompasshintergrund
    'main'      => [ 'fill',   'live_compass_rose' ],
    'hub'       => [ 'fill',   'live_compass_rose' ],
    'needle'    => [ 'stroke', 'theme_compass_needle' ],
    'wind'      => [ 'stroke', 'live_gauge_wind' ],
    'gust'      => [ 'stroke', 'live_gauge_gust' ],
    'peak'      => [ 'stroke', 'live_gauge_peak' ],
    'gauge-hub' => [ 'fill',   'live_gauge_wind' ],
];
/** Die Deklarationen aller Regeln, deren Selektor die Klasse nennt und den Geltungsbereich enthaelt. */
function rules_for( string $css, string $scope, string $class ): string {
    preg_match_all( '/([^{}]*\.' . preg_quote( $class, '/' ) . '\b[^{]*)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER );
    $out = '';
    foreach ( $m as $r ) {
        if ( str_contains( $r[1], $scope ) ) { $out .= $r[2] . ';'; }
    }
    return $out;
}
foreach ( $BOUND as $c => [ $prop, $key ] ) {
    [ $default, $var ] = $KEYS[ $key ];
    $decl = rules_for( $fcss, '.naws-wx', "naws-lw-{$c}" );
    check( "naws-lw-{$c}: {$prop} aus {$var} mit Vorgabe {$default}", (bool) preg_match( '/' . $prop . ':\s*var\(' . preg_quote( $var, '/' ) . ',\s*' . $default . '\)/', $decl ), true );
}
check( 'der Zeiger nimmt die Nadelfarbe auch als Fuellung (Pfeilkopf)', (bool) preg_match( '/fill:\s*var\(--naws-compass-needle,\s*#c0392b\)/', rules_for( $fcss, '.naws-wx', 'naws-lw-needle' ) ), true );

echo "\nErscheinungsbild: eigener Reiter mit Vorschau\n" . str_repeat( '-', 74 ) . "\n";

$view = file_get_contents( $PLUGIN . 'admin/views/appearance.php' );
check( 'der Reiter live_wind ist definiert', (bool) preg_match( "/'live_wind'\s*=>\s*__\(/", $view ), true );
check( 'es gibt den Bereich data-pane="live_wind"', str_contains( $view, 'data-pane="live_wind"' ), true );
check( 'die Felder kommen aus der Gruppe', str_contains( $view, "\$groups['live_wind']['keys']" ), true );
check( 'die Felder melden sich als Vorschau livewind', str_contains( $view, 'data-preview="livewind"' ), true );
check( 'der alte Kompass ist aus der Theme-Vorschau verschwunden', str_contains( $view, 'naws-pv-rose-bg' ), false );
check( 'die Theme-Vorschau faerbt keinen Zeiger mehr', (bool) preg_match( "/'theme_compass_needle':\s*function/", $view ), false );
check( 'die Vorschau hat einen Rahmen mit den Variablen', (bool) preg_match( '/id="naws-preview-livewind"[^>]*style="[^"]*--naws-live-compass-bg:/', $view ), true );
foreach ( array_merge( ...array_values( $CLASSES ) ) as $c ) {
    check( "die Vorschau zeigt naws-lw-{$c}", (bool) preg_match( '/id="naws-preview-livewind".*class="naws-lw-' . preg_quote( $c, '/' ) . '"/s', $view ), true );
}
foreach ( array_keys( $KEYS ) as $k ) {
    check( "Beschriftung fuer {$k}", (bool) preg_match( "/'{$k}'\s*=>\s*__\(/", $view ), true );
}
check( 'die alte Beschriftung "Compass Needle (Wind Rose)" ist weg', str_contains( $view, 'Compass Needle (Wind Rose)' ), false );
check( 'das Vorschau-Skript kennt die Gruppe livewind', (bool) preg_match( "/group === 'livewind'/", $view ), true );
foreach ( $KEYS as $k => [ $_, $var ] ) {
    check( "und bildet {$k} auf {$var} ab", (bool) preg_match( '/' . preg_quote( $k, '/' ) . "\W+'" . preg_quote( $var, '/' ) . "'/", $view ), true );
}
check( 'die Theme-Farben faerben die Wind-Vorschau mit (Rahmen, gedaempft, dunkel, Karte)', preg_match_all( "/setProperty\('--(line|muted|ink2|card)'/", $view ), 4 );

$acss = file_get_contents( $PLUGIN . 'assets/css/admin.css' );
foreach ( $BOUND as $c => [ $prop, $key ] ) {
    [ $default, $var ] = $KEYS[ $key ];
    $decl = rules_for( $acss, '.naws-pv-livewind', "naws-lw-{$c}" );
    check( "Vorschau-Regel naws-lw-{$c} liest {$var}", (bool) preg_match( '/' . $prop . ':\s*var\(' . preg_quote( $var, '/' ) . '/', $decl ), true );
}

echo "\nKataloge: die neuen Texte sind uebersetzt\n" . str_repeat( '-', 74 ) . "\n";

$LABELS = [
    'Live dashboard: wind',
    'Background (compass and gauge)',
    'Compass – rose',
    'Compass – pointer',
    'Gauge – wind',
    'Gauge – gusts',
    'Gauge – peak gust of the day',
    'Live preview — wind cards',
];
$pot = file_get_contents( $PLUGIN . 'languages/xtx-integration-for-netatmo.pot' );
foreach ( $LABELS as $l ) {
    check( ".pot kennt \"{$l}\"", str_contains( $pot, 'msgid "' . $l . '"' ), true );
}
foreach ( [ 'de_DE', 'nb_NO' ] as $loc ) {
    $po = file_get_contents( $PLUGIN . "docs/i18n/catalog/xtx-integration-for-netatmo-{$loc}.po" );
    foreach ( $LABELS as $l ) {
        $ok = (bool) preg_match( '/^msgid "' . preg_quote( $l, '/' ) . '"\r?\nmsgstr "(.+)"/m', $po );
        check( "{$loc} uebersetzt \"{$l}\"", $ok, true );
    }
}
check( 'die alte Beschriftung ist aus der .pot verschwunden', str_contains( $pot, 'msgid "Compass Needle (Wind Rose)"' ), false );
check( 'und "Compass – background" auch (seit die Skala den Hintergrund teilt)', str_contains( $pot, 'msgid "Compass – background"' ), false );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
