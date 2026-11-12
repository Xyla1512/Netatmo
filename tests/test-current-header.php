<?php
/**
 * Tests fuer den Modulkopf von [naws_current] (templates/current.php).
 *
 * Der Kopf zeigt ein Typ-Abzeichen ("Basisstation (Indoor)") vor dem
 * Modulnamen. Mit einem Anzeigenamen von der Modulseite las sich das wie
 * zwei Namen fuer ein Modul ("Basisstation (Indoor) Wohnzimmer"). Franks
 * Vorgabe (12.11.2026): im Frontend steht dann nur der Anzeigename — das
 * Abzeichen faellt weg, sobald einer gesetzt ist, und bleibt sonst.
 *
 *   php tests/test-current-header.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
class NAWS_Helpers {
    public static function module_type_label( $t ) { return [ 'NAMain' => 'Basisstation (Indoor)', 'NAModule1' => 'Außenmodul' ][ $t ] ?? $t; }
    public static function display_name( array $m ): string { return (string) ( $m['display_name'] ?? $m['module_name'] ); }
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$atts               = [ 'animate' => 'false', 'title' => '', 'module_id' => '', 'layout' => 'grid' ];
$module_map         = [
    'base'    => [ 'module_type' => 'NAMain',    'module_name' => 'Basis',  'custom_name' => 'Wohnzimmer', 'display_name' => 'Wohnzimmer' ],
    'outdoor' => [ 'module_type' => 'NAModule1', 'module_name' => 'Aussen', 'custom_name' => '',           'display_name' => 'Aussen' ],
];
$readings_by_module = [ 'base' => [], 'outdoor' => [] ];
ob_start();
include dirname( __DIR__ ) . '/templates/current.php';
$html = ob_get_clean();

preg_match_all( '#<div class="naws-module-header">(.*?)</div>#s', $html, $m );
$heads = array_map( static fn( $h ) => trim( preg_replace( '/\s+/', ' ', strip_tags( $h ) ) ), $m[1] );

echo "\n[naws_current]: Modulkopf\n" . str_repeat( '-', 74 ) . "\n";
check( 'zwei Modulkoepfe', count( $heads ), 2 );
check( 'mit Anzeigenamen steht nur der Anzeigename', $heads[0] ?? null, 'Wohnzimmer' );
check( 'ohne Anzeigenamen bleibt das Typ-Abzeichen vor dem Netatmo-Namen', $heads[1] ?? null, 'Außenmodul Aussen' );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
