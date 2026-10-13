<?php
/**
 * Tests fuer die Farben des Regenradars: Vorgaben, Gruppe, Reiter,
 * CSS-Block, sanitize, feste Farben in frontend.css nur als Rueckfall,
 * Reiter im Erscheinungsbild mit Vorschau.
 *
 *   php tests/test-radar-colors.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['naws_test_options']         = [];
$GLOBALS['naws_test_global_settings'] = [];
function get_option( $key, $default = false ) { return $GLOBALS['naws_test_options'][ $key ] ?? $default; }
function get_post_meta( $post_id, $key = '', $single = false ) { return ''; }
function post_type_exists( $type ) { return false; }
function wp_get_global_settings() { return $GLOBALS['naws_test_global_settings']; }
require_once __DIR__ . '/i18n-stubs.php';
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
$PLUGIN = dirname( __DIR__ ) . '/';
require_once $PLUGIN . 'includes/class-naws-fonts.php';
require_once $PLUGIN . 'includes/class-naws-colors.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function saved( array $o ): void { $GLOBALS['naws_test_options']['naws_appearance'] = $o; NAWS_Colors::flush_cache(); NAWS_Fonts::flush_cache(); }

$KEYS = [
    'radar_station'     => [ '#c0392b', '--naws-radar-station' ],
    'radar_time_bg'     => [ '#2d5252', '--naws-radar-time-bg' ],
    'radar_time_text'   => [ '#ffffff', '--naws-radar-time-text' ],
    'radar_forecast'    => [ '#dd9933', '--naws-radar-forecast' ],
    'radar_bar_bg'      => [ '#f4fafa', '--naws-radar-bar-bg' ],
    'radar_button'      => [ '#427272', '--naws-radar-button' ],
    'radar_button_text' => [ '#ffffff', '--naws-radar-button-text' ],
];

echo "\nNAWS_Colors\n" . str_repeat( '-', 74 ) . "\n";
saved( [] );
$d = NAWS_Colors::get_defaults();
foreach ( $KEYS as $k => [ $def ] ) { check( "Vorgabe $k", $d[ $k ] ?? null, $def ); }
check( 'Gruppe radar',  NAWS_Colors::get_groups()['radar']['keys'] ?? null, array_keys( $KEYS ) );
check( 'Reiter radar',  array_key_exists( 'radar', NAWS_Colors::appearance_tabs() ), true );
$set = []; $i = 0;
foreach ( $KEYS as $k => $_ ) { $set[ $k ] = sprintf( '#%06x', 0x101010 * ( ++$i ) ); }
saved( $set );
preg_match( '/\.naws-radar \{(.*?)\}/s', NAWS_Colors::get_inline_css(), $m );
foreach ( $KEYS as $k => [ $_, $var ] ) {
    check( "$var traegt den Wert", (bool) preg_match( '/' . preg_quote( $var, '/' ) . ':\s*' . $set[ $k ] . ';/', $m[1] ?? '' ), true );
}
check( 'sanitize nimmt an',    array_intersect_key( NAWS_Colors::sanitize( $set ), $KEYS ) == $set, true );
check( 'sanitize verwirft',    isset( NAWS_Colors::sanitize( [ 'radar_station' => 'red;x' ] )['radar_station'] ), false );

echo "\nfrontend.css\n" . str_repeat( '-', 74 ) . "\n";
$css = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
preg_match( '/\[naws_radar\] — rain radar.*$/s', $css, $m );
$block = $m[0] ?? '';
check( 'Block gefunden', $block !== '', true );
$block_wo_legend = preg_replace( '/\.naws-radar-legend i \{[^}]*\}/', '', $block );
preg_match_all( '/(?<!var\(--[a-z0-9-]{1,40}, )#[0-9a-fA-F]{3,8}\b/', $block_wo_legend, $hex );
check( 'Hex nur als Rueckfall (ausser Legendenverlauf)', $hex[0], [] );
check( 'Knopf hover/focus mit zwei Klassen', str_contains( $block, '.naws-radar .naws-radar-btn:hover, .naws-radar .naws-radar-btn:focus' ), true );

echo "\nErscheinungsbild\n" . str_repeat( '-', 74 ) . "\n";
$view = (string) file_get_contents( $PLUGIN . 'admin/views/appearance.php' );
check( 'Bereich radar',             str_contains( $view, 'data-pane="radar"' ), true );
check( 'Felder aus der Gruppe',     str_contains( $view, "\$groups['radar']['keys']" ), true );
check( 'Vorschau radar',            str_contains( $view, 'data-preview="radar"' ), true );
check( 'Vorschau-Rahmen mit Variablen', (bool) preg_match( '/id="naws-preview-radar"[^>]*style="[^"]*--naws-radar-station:/', $view ), true );
check( 'Skript kennt radar',        (bool) preg_match( "/group === 'radar'/", $view ), true );
foreach ( array_keys( $KEYS ) as $k ) { check( "Beschriftung $k", (bool) preg_match( "/'{$k}'\s*=>\s*__\(/", $view ), true ); }

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
