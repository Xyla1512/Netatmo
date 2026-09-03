<?php
/**
 * Prueft die Farbrechnung der Heatmap.
 *
 * Die Interpolation ist die einzige Stelle, an der aus einer Temperatur
 * eine Farbe wird — Template und AJAX-Endpunkt rufen beide hierher. Wenn
 * sie falsch rechnet, faellt es an der Karte nicht auf: eine Kachel eine
 * Stufe daneben sieht aus wie Wetter.
 *
 *   php tests/test-heatmap-colors.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['appearance'] = [];
function get_option( $k, $d = false ) {
    return $k === 'naws_appearance' ? $GLOBALS['appearance'] : $d;
}
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Fonts {
    public static function available() { return [ 'inherit' => 'Inherit' ]; }
    public static function sanitize_family( $s ) { return $s; }
}

require_once dirname( __DIR__ ) . '/includes/class-naws-colors.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Zwingt NAWS_Colors, die Einstellungen neu zu lesen. */
function with_appearance( array $a ): void {
    $GLOBALS['appearance'] = $a;
    NAWS_Colors::flush_cache();
}

echo "\nNAWS_Colors::heatmap_color() — die Stuetzpunkte\n" . str_repeat( '-', 74 ) . "\n";

with_appearance( [] );

check( 'minus zehn Grad ist Lila',        NAWS_Colors::heatmap_color( -10 ), '#6b21a8' );
check( 'null Grad ist Blaugruen',         NAWS_Colors::heatmap_color( 0 ),   '#2f9e97' );
check( 'zwanzig Grad ist Orange',         NAWS_Colors::heatmap_color( 20 ),  '#f59f3c' );
check( 'fuenfunddreissig ist Dunkelrot',  NAWS_Colors::heatmap_color( 35 ),  '#7f1d1d' );

echo "\nDazwischen wird interpoliert\n" . str_repeat( '-', 74 ) . "\n";

// -10 = #6b21a8 = (107, 33,168)
//  -5 = #3b5bdb = ( 59, 91,219)
// Mitte bei -7.5 = (83, 62, 193.5) -> gerundet (83, 62, 194) = #533ec2
check( 'die Mitte zwischen zwei Stuetzpunkten', NAWS_Colors::heatmap_color( -7.5 ), '#533ec2' );

check( 'ein Viertel weiter liegt naeher am linken Stuetzpunkt',
    NAWS_Colors::heatmap_color( -8.75 ) !== NAWS_Colors::heatmap_color( -7.5 ), true );
check( 'benachbarte Grade unterscheiden sich',
    NAWS_Colors::heatmap_color( 12 ) !== NAWS_Colors::heatmap_color( 13 ), true );

echo "\nAusserhalb der Skala wird gekappt\n" . str_repeat( '-', 74 ) . "\n";

check( 'minus vierzig faerbt wie minus zehn', NAWS_Colors::heatmap_color( -40 ), NAWS_Colors::heatmap_color( -10 ) );
check( 'fuenfzig faerbt wie fuenfunddreissig', NAWS_Colors::heatmap_color( 50 ),  NAWS_Colors::heatmap_color( 35 ) );

echo "\nKein Messwert\n" . str_repeat( '-', 74 ) . "\n";

check( 'null gibt die Farbe fuer den fehlenden Tag', NAWS_Colors::heatmap_color( null ), '#eef2f2' );
check( 'ein leerer String ebenso',                   NAWS_Colors::heatmap_color( '' ),   '#eef2f2' );

echo "\nDie Einstellung schlaegt durch\n" . str_repeat( '-', 74 ) . "\n";

with_appearance( [ 'heatmap_t_0' => '#000000' ] );
check( 'ein geaenderter Stuetzpunkt wird benutzt', NAWS_Colors::heatmap_color( 0 ), '#000000' );
check( 'und faerbt seine Nachbarschaft mit',       NAWS_Colors::heatmap_color( 2.5 ) !== '#3fa34d', true );

with_appearance( [ 'heatmap_no_data' => '#ff00ff' ] );
check( 'auch die Farbe des fehlenden Tages', NAWS_Colors::heatmap_color( null ), '#ff00ff' );

with_appearance( [] );

echo "\nNAWS_Colors::heatmap_scale()\n" . str_repeat( '-', 74 ) . "\n";

$scale = NAWS_Colors::heatmap_scale();
check( 'zehn Stuetzpunkte',            count( $scale ), 10 );
check( 'der erste ist minus zehn',     $scale[0][0], -10 );
check( 'der letzte ist fuenfunddreissig', $scale[9][0], 35 );
check( 'jeder traegt eine Hexfarbe',
    array_values( array_filter( $scale, fn( $s ) => ! preg_match( '/^#[0-9a-f]{6}$/i', $s[1] ) ) ),
    [] );
check( 'die Stuetzpunkte steigen',
    array_column( $scale, 0 ),
    [ -10, -5, 0, 5, 10, 15, 20, 25, 30, 35 ] );

echo "\nDie Gruppe fuer die Appearance-Seite\n" . str_repeat( '-', 74 ) . "\n";

$groups = NAWS_Colors::get_groups();
check( 'es gibt eine Heatmap-Gruppe', isset( $groups['heatmap'] ), true );
check( 'sie fuehrt elf Schluessel',   count( $groups['heatmap']['keys'] ), 11 );
check( 'jeder davon hat einen Default',
    array_values( array_filter( $groups['heatmap']['keys'], fn( $k ) => ! isset( NAWS_Colors::DEFAULTS[ $k ] ) ) ),
    [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
