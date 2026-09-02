<?php
/**
 * Prueft die Beschriftung einer Kachel und das Markup von
 * templates/heatmap.php.
 *
 *   php tests/test-heatmap-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';
function wp_date( $fmt, $ts ) { return gmdate( $fmt, $ts ); }

require_once $PLUGIN . 'includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Helpers::heatmap_label()\n" . str_repeat( '-', 74 ) . "\n";

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];

check( 'ein Wert traegt seine Einheit',   NAWS_Helpers::heatmap_label( 8.2, 'avg' ), '8.2 °C' );
check( 'kein Wert sagt das',              NAWS_Helpers::heatmap_label( null, null ), 'No reading' );
check( 'ein gerechneter Wert nennt seine Herkunft',
    NAWS_Helpers::heatmap_label( 6.0, 'minmax' ), '6 °C · computed from min and max' );

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'F' ] ];

check( 'in Fahrenheit wird umgerechnet', NAWS_Helpers::heatmap_label( 0, 'avg' ), '32 °F' );
check( 'und die Einheit stimmt mit',     str_contains( NAWS_Helpers::heatmap_label( 20, 'avg' ), '°F' ), true );

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
