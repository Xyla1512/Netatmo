<?php
/**
 * Tests fuer die Farben der Vorhersage [naws_forecast].
 *
 * Der Fehler: frontend.css malte die Karten der Vorhersage mit festen
 * Hex-Farben — den schmalen Balken ueber jeder Karte, Balken, Rahmen und
 * Wochentag der Karte "Heute", dazu Texte, Rahmen und Hintergrund. Nichts
 * davon liess sich im Erscheinungsbild aendern.
 *
 * Seit 2.1.0 hat das Erscheinungsbild einen Reiter "Vorhersage" mit vier
 * Farben; die Vorgaben sind die bisher festen Farben, eine unveraenderte
 * Installation sieht also gleich aus. Texte, Rahmen und Hintergrund der
 * Karten folgen dem Basis-Theme.
 *
 *   php tests/test-forecast-colors.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options']         = [];
$GLOBALS['naws_test_global_settings'] = [];

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

/** Schluessel -> [ Vorgabe, CSS-Variable ]. Die Vorgaben sind die alten festen Farben aus frontend.css. */
$KEYS = [
    'forecast_bar'          => [ '#427272', '--naws-fc-bar' ],
    'forecast_today_bar'    => [ '#3d9e74', '--naws-fc-today-bar' ],
    'forecast_today_border' => [ '#427272', '--naws-fc-today-border' ],
    'forecast_today_day'    => [ '#3d9e74', '--naws-fc-today-day' ],
];

echo "\nNAWS_Colors: Vorgaben, Gruppe, Reiter, CSS\n" . str_repeat( '-', 74 ) . "\n";

saved( [] );
$d = NAWS_Colors::get_defaults();
foreach ( $KEYS as $k => [ $default ] ) {
    check( "Vorgabe {$k} ist die bisher feste Farbe", $d[ $k ] ?? null, $default );
}
$groups = NAWS_Colors::get_groups();
check( 'es gibt die Gruppe forecast', $groups['forecast']['keys'] ?? null, array_keys( $KEYS ) );
check( 'es gibt den Reiter forecast', array_key_exists( 'forecast', NAWS_Colors::appearance_tabs() ), true );

$set = [];
$i   = 0;
foreach ( $KEYS as $k => $_ ) { $set[ $k ] = sprintf( '#%06x', 0x111111 * ( ++$i ) ); }
saved( $set + [ 'theme_text_dark' => '#123456' ] );
$css = NAWS_Colors::get_inline_css();
preg_match( '/\.naws-fc-wrap \{(.*?)\}/s', $css, $m );
$fc = $m[1] ?? '';
check( 'es gibt einen eigenen .naws-fc-wrap-Block', $fc !== '', true );
foreach ( $KEYS as $k => [ $_, $var ] ) {
    check( "{$var} traegt den gesetzten Wert", (bool) preg_match( '/' . preg_quote( $var, '/' ) . ':\s*' . $set[ $k ] . ';/', $fc ), true );
}
check( 'die Vorhersage bekommt das Basis-Theme', (bool) preg_match( '/--naws-text-dark:\s*#123456;/', $fc ), true );

$clean = NAWS_Colors::sanitize( $set );
check( 'sanitize() nimmt die Schluessel an', array_intersect_key( $clean, $KEYS ) == $set, true );
check( 'und verwirft, was keine Farbe ist', isset( NAWS_Colors::sanitize( [ 'forecast_bar' => 'url(x)' ] )['forecast_bar'] ), false );

echo "\nfrontend.css: keine feste Farbe mehr in der Vorhersage\n" . str_repeat( '-', 74 ) . "\n";

$fcss = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
preg_match( '/Forecast Grid.*?(?=\/\* =====)/s', $fcss, $m );
$block = $m[0] ?? '';
check( 'der Block ist gefunden', $block !== '', true );
preg_match_all( '/(?<!var\(--[a-z0-9-]{1,40}, )#[0-9a-fA-F]{3,8}\b/', $block, $hex );
check( 'jede Hex-Farbe steht nur noch als Rueckfall in var()', $hex[0], [] );
check( 'Balken ueber jeder Karte',   str_contains( $block, 'height: 3.5px; background: var(--naws-fc-bar, #427272);' ), true );
check( 'Heute: Balken',              str_contains( $block, '.naws-fc-wrap .naws-fc-card-today::before { height: 4px; background: var(--naws-fc-today-bar, #3d9e74); }' ), true );
check( 'Heute: Rahmen',              str_contains( $block, '.naws-fc-wrap .naws-fc-card-today { border-color: var(--naws-fc-today-border, #427272);' ), true );
check( 'Heute: Wochentag',           str_contains( $block, '.naws-fc-wrap .naws-fc-card-today .naws-fc-day { color: var(--naws-fc-today-day, #3d9e74); }' ), true );
check( 'Karte: Hintergrund und Rahmen aus dem Theme', str_contains( $block, 'background: var(--naws-surface, #ffffff); border: 1.5px solid var(--naws-border, #e0eeee); border-radius: 14px;' ), true );

echo "\nErscheinungsbild: eigener Reiter mit Vorschau\n" . str_repeat( '-', 74 ) . "\n";

$view = (string) file_get_contents( $PLUGIN . 'admin/views/appearance.php' );
check( 'es gibt den Bereich data-pane="forecast"',    str_contains( $view, 'data-pane="forecast"' ), true );
check( 'die Felder kommen aus der Gruppe',             str_contains( $view, "\$groups['forecast']['keys']" ), true );
check( 'die Felder melden sich als Vorschau forecast', str_contains( $view, 'data-preview="forecast"' ), true );
check( 'die Vorschau hat einen Rahmen mit den Variablen', (bool) preg_match( '/id="naws-preview-forecast"[^>]*style="[^"]*--naws-fc-bar:/', $view ), true );
check( 'das Vorschau-Skript kennt die Gruppe forecast',  (bool) preg_match( "/group === 'forecast'/", $view ), true );
foreach ( array_keys( $KEYS ) as $k ) {
    check( "Beschriftung fuer {$k}", (bool) preg_match( "/'{$k}'\s*=>\s*__\(/", $view ), true );
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
