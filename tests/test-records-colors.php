<?php
/**
 * Tests fuer zwei Farbluecken, die Frank am 15.10.2026 fand:
 *
 *  1. [naws_records], [naws_on_this_day] und [naws_sunpath] stehen ausserhalb
 *     von .naws-wrap und bekamen das Grundthema nie — ihr Hintergrund blieb
 *     der Rueckfall #fff. Jetzt folgen sie dem Grundthema, und Rekorde/
 *     "An diesem Tag" haben einen eigenen Reiter (leer = Grundthema).
 *  2. Die Vergroessern-Knoepfe der Live-Diagramme und des Verlaufs hatten
 *     keine eigenen Farben, der Verlauf gar feste. Ein Satz fuer beide im
 *     Reiter "Chart Theming", Hover/Fokus mit zwei Klassen.
 *
 *   php tests/test-records-colors.php
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
/** Koerper der Regel mit genau diesem Selektor aus dem Inline-CSS. */
function rule( string $css, string $sel ): string {
    return preg_match( '/(?:^|\n)' . preg_quote( $sel, '/' ) . ' \{(.*?)\}/s', $css, $m ) ? $m[1] : '';
}

$REC = [
    'records_bg'      => '--naws-rec-bg',
    'records_tile_bg' => '--naws-rec-tile-bg',
    'records_value'   => '--naws-rec-value',
    'records_label'   => '--naws-rec-label',
];
$EXP = [
    'chart_expand'            => [ '#7aa0a0', '--naws-expand' ],
    'chart_expand_hover_bg'   => [ '#2d5252', '--naws-expand-hover-bg' ],
    'chart_expand_hover_icon' => [ '#ffffff', '--naws-expand-hover-icon' ],
];

echo "\nRekorde: Vorgaben und Reiter\n" . str_repeat( '-', 74 ) . "\n";
saved( [] );
$d = NAWS_Colors::get_defaults();
foreach ( $REC as $k => $_ ) { check( "Vorgabe $k leer (= Grundthema)", $d[ $k ] ?? null, '' ); }
check( 'Gruppe records', NAWS_Colors::get_groups()['records']['keys'] ?? null, array_keys( $REC ) );
check( 'Reiter records', array_key_exists( 'records', NAWS_Colors::appearance_tabs() ), true );

echo "\nRekorde: Inline-CSS\n" . str_repeat( '-', 74 ) . "\n";
saved( [ 'theme_surface' => '#101010', 'theme_surface_alt' => '#202020', 'theme_border' => '#303030', 'theme_text' => '#404040' ] );
$css  = NAWS_Colors::get_inline_css();
$base = rule( $css, '.naws-rec, .naws-otd, .naws-sun' );
check( 'Grundthema erreicht Rekorde/Tag/Sonne', $base !== '', true );
foreach ( [ '--naws-surface: #101010', '--naws-surface-2: #202020', '--naws-border: #303030', '--naws-text: #404040' ] as $want ) {
    check( "… $want", str_contains( $base, $want . ';' ), true );
}
check( 'leere Rekordfarben schreiben nichts', str_contains( $css, '--naws-rec-' ), false );
$set = []; $i = 0;
foreach ( $REC as $k => $_ ) { $set[ $k ] = sprintf( '#%06x', 0x111111 * ( ++$i ) ); }
saved( $set );
$own = rule( NAWS_Colors::get_inline_css(), '.naws-rec, .naws-otd' );
foreach ( $REC as $k => $var ) {
    check( "$var traegt den Wert", str_contains( $own, "$var: {$set[ $k ]};" ), true );
}
check( 'sanitize nimmt an',  array_intersect_key( NAWS_Colors::sanitize( $set ), $REC ) == $set, true );
check( 'sanitize nimmt leer', NAWS_Colors::sanitize( [ 'records_bg' => '' ] )['records_bg'] ?? null, '' );
check( 'sanitize verwirft',  isset( NAWS_Colors::sanitize( [ 'records_bg' => 'red;x' ] )['records_bg'] ), false );

echo "\nVergroessern-Knopf: Vorgaben und Inline-CSS\n" . str_repeat( '-', 74 ) . "\n";
saved( [] );
$d = NAWS_Colors::get_defaults();
foreach ( $EXP as $k => [ $def ] ) { check( "Vorgabe $k", $d[ $k ] ?? null, $def ); }
$keys = NAWS_Colors::get_groups()['chart_theme']['keys'] ?? [];
check( 'in der Gruppe chart_theme', array_values( array_intersect( $keys, array_keys( $EXP ) ) ), array_keys( $EXP ) );
$set = []; $i = 0;
foreach ( $EXP as $k => $_ ) { $set[ $k ] = sprintf( '#%06x', 0x121212 * ( ++$i ) ); }
saved( $set );
$exp = rule( NAWS_Colors::get_inline_css(), '.naws-wx, .naws-hist' );
foreach ( $EXP as $k => [ $_, $var ] ) {
    check( "$var traegt den Wert", str_contains( $exp, "$var: {$set[ $k ]};" ), true );
}

echo "\nfrontend.css\n" . str_repeat( '-', 74 ) . "\n";
$fcss = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' ) );
check( 'Rekordblock liest --naws-rec-bg',        (bool) preg_match( '/\.naws-rec, \.naws-otd \{[^}]*background: var\(--naws-rec-bg, var\(--naws-surface/', $fcss ), true );
check( 'Kachel liest --naws-rec-tile-bg',        (bool) preg_match( '/\.naws-rec-tile \{[^}]*background: var\(--naws-rec-tile-bg,/', $fcss ), true );
check( 'Wert liest --naws-rec-value',            (bool) preg_match( '/\.naws-rec-value \{[^}]*color: var\(--naws-rec-value,/', $fcss ), true );
check( 'Beschriftung liest --naws-rec-label',    (bool) preg_match( '/\.naws-rec-label \{[^}]*color: var\(--naws-rec-label,/', $fcss ), true );
check( 'Rekordzelle Tag liest Kachel + Wert',    (bool) preg_match( '/\.naws-otd-record \{[^}]*var\(--naws-rec-value,[^}]*var\(--naws-rec-tile-bg,/', $fcss ), true );
foreach ( [ '.naws-wx .naws-chart-expand', '.naws-hist .naws-hc-expand' ] as $btn ) {
    check( "$btn liest --naws-expand",          (bool) preg_match( '/' . preg_quote( $btn, '/' ) . ' ?\{[^}]*color: ?var\(--naws-expand,/', $fcss ), true );
    check( "$btn hover und focus",      str_contains( $fcss, "$btn:hover, $btn:focus" ), true );
    check( "$btn hover liest Hover-Farben",     (bool) preg_match( '/' . preg_quote( "$btn:focus", '/' ) . ' ?\{[^}]*var\(--naws-expand-hover-bg,[^}]*var\(--naws-expand-hover-icon,/', $fcss ), true );
}
check( 'keine Ein-Klassen-Hoverregel mehr', str_contains( $fcss, "\n.naws-hc-expand:hover" ), false );

echo "\nErscheinungsbild\n" . str_repeat( '-', 74 ) . "\n";
$view = (string) file_get_contents( $PLUGIN . 'admin/views/appearance.php' );
check( 'Bereich records',               str_contains( $view, 'data-pane="records"' ), true );
check( 'Felder aus der Gruppe',         str_contains( $view, "\$groups['records']['keys']" ), true );
check( 'Vorschau records',              str_contains( $view, 'data-preview="records"' ), true );
check( 'Skript kennt records',          (bool) preg_match( "/group === 'records'/", $view ), true );
check( 'Vorschau Knopf in Chart Theming', str_contains( $view, 'naws-pv-expand' ), true );
foreach ( array_merge( array_keys( $REC ), array_keys( $EXP ) ) as $k ) { check( "Beschriftung $k", (bool) preg_match( "/'{$k}'\s*=>\s*__\(/", $view ), true ); }

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
