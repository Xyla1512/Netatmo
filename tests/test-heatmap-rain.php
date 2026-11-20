<?php
/**
 * Prueft die Regen-Heatmap: [naws_heatmap type="rain"].
 *
 * Drei Dinge muessen hier stimmen, weil sie sich auf der fertigen Karte
 * nicht unterscheiden lassen: ein trockener Tag (0 mm) ist etwas anderes
 * als ein Tag ohne Messung, die Skala liegt in Millimetern auch dann, wenn
 * die Seite Zoll anzeigt, und der laufende Tag bleibt leer — seine Summe
 * waechst noch.
 *
 *   php tests/test-heatmap-rain.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d, '.', ',' ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';
function wp_date( $fmt, $ts = null ) { return gmdate( $fmt, $ts ?? time() ); }
function wp_unique_id( $p = '' ) { static $n = 0; return $p . ( ++$n ); }
function wp_create_nonce( $a ) { return 'testnonce'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }

class NAWS_Fonts {
    public static function available() { return [ 'inherit' => 'Inherit' ]; }
    public static function sanitize_family( $s ) { return $s; }
}

require_once $PLUGIN . 'includes/class-naws-helpers.php';
require_once $PLUGIN . 'includes/class-naws-colors.php';
require_once $PLUGIN . 'includes/class-naws-database.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

function with_settings( array $s, array $appearance = [] ): void {
    $GLOBALS['opts'] = [ 'naws_settings' => $s, 'naws_appearance' => $appearance ];
    NAWS_Colors::flush_cache();
}

/** Dieselbe Mischung wie NAWS_Colors::mix_hex(), unabhaengig nachgerechnet. */
function mixh( string $a, string $b, float $t ): string {
    $x = sscanf( $a, '#%02x%02x%02x' );
    $y = sscanf( $b, '#%02x%02x%02x' );
    $o = '#';
    for ( $i = 0; $i < 3; $i++ ) { $o .= sprintf( '%02x', (int) round( $x[ $i ] + ( $y[ $i ] - $x[ $i ] ) * $t ) ); }
    return $o;
}

function rrow( string $date, $mm, string $mid = '70:ee:50:00:00:01' ): array {
    return [ 'day_date' => $date, 'module_id' => $mid, 'rain_sum' => $mm ];
}

// ─────────────────────────────────────────────────────────────────────
echo "\nNAWS_Database::shape_rain_year()\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_rain_year( [], 2025 );
check( 'zwoelf Monatszeilen',         count( $r['values'] ), 12 );
check( 'der Februar 2025 hat 28',     count( $r['values'][1] ), 28 );
check( 'ohne Zeilen ist alles null',  $r['values'][0][0], null );

$r = NAWS_Database::shape_rain_year( [
    rrow( '2025-01-01', '3.4' ),
    rrow( '2025-01-02', '0' ),
    rrow( '2025-01-03', null ),
    rrow( '2025-07-15', '26.43' ),
], 2025 );
check( 'eine Tagessumme landet an ihrem Tag',    $r['values'][0][0], 3.4 );
check( 'ein trockener Tag ist 0, nicht null',    $r['values'][0][1], 0.0 );
check( 'ein Tag ohne Wert bleibt null',          $r['values'][0][2], null );
check( 'zwei Nachkommastellen bleiben',          $r['values'][6][14], 26.43 );
check( 'die Herkunft heisst sum',                $r['sources'][0][0], 'sum' );
check( 'ohne Wert keine Herkunft',               $r['sources'][0][2], null );

// Innenmodule fuehren je Tag eine eigene Zeile ohne rain_sum — sie duerfen
// den Wert der Stationszeile nicht ueberschreiben, egal in welcher
// Reihenfolge die Zeilen kommen.
$r = NAWS_Database::shape_rain_year( [
    rrow( '2025-03-05', null, '03:00:00:00:00:01' ),
    rrow( '2025-03-05', '1.2' ),
    rrow( '2025-03-05', null, '03:00:00:00:00:02' ),
], 2025 );
check( 'eine leere Modulzeile loescht die Summe nicht', $r['values'][2][4], 1.2 );

$r = NAWS_Database::shape_rain_year( [ rrow( '2025-03-05', '-0.5' ) ], 2025 );
check( 'ein negativer Wert ist kein Regen und bleibt leer', $r['values'][2][4], null );

$r = NAWS_Database::shape_rain_year( [
    rrow( '2026-11-19', '2.0' ),
    rrow( '2026-11-20', '0.4' ),
], 2026, '2026-11-20' );
check( 'gestern steht da',                     $r['values'][10][18], 2.0 );
check( 'der laufende Tag bleibt leer',          $r['values'][10][19], null );

$r = NAWS_Database::shape_rain_year( [ rrow( '2024-12-31', '5' ), rrow( 'kaputt', '5' ) ], 2025 );
check( 'ein anderes Jahr und Muell werden ignoriert', $r['values'][11][30], null );

// ─────────────────────────────────────────────────────────────────────
echo "\nNAWS_Colors — die Regenskala\n" . str_repeat( '-', 74 ) . "\n";

with_settings( [] );
$d = NAWS_Colors::get_defaults();

check( 'Stuetzpunkte und Schluessel gleich lang',
    count( NAWS_Colors::RAIN_HEATMAP_STOPS ), count( NAWS_Colors::RAIN_HEATMAP_KEYS ) );
check( 'jeder Schluessel hat eine Vorgabe',
    count( array_diff( NAWS_Colors::RAIN_HEATMAP_KEYS, array_keys( $d ) ) ), 0 );
check( 'auch der trockene Tag',             isset( $d['heatmap_r_dry'] ), true );
check( 'trocken ist nicht das Grau fuer keinen Messwert',
    $d['heatmap_r_dry'] !== $d['heatmap_no_data'], true );

check( 'kein Messwert ist grau',            NAWS_Colors::rain_heatmap_color( null ), $d['heatmap_no_data'] );
check( 'null Millimeter sind trocken',      NAWS_Colors::rain_heatmap_color( 0 ), $d['heatmap_r_dry'] );
check( 'der erste Stuetzpunkt',             NAWS_Colors::rain_heatmap_color( 0.1 ), $d['heatmap_r_0_1'] );
check( 'unter 0,1 mm, aber nass, faerbt wie 0,1',
    NAWS_Colors::rain_heatmap_color( 0.05 ), $d['heatmap_r_0_1'] );
check( 'zehn Millimeter',                   NAWS_Colors::rain_heatmap_color( 10 ), $d['heatmap_r_10'] );
check( 'oben wird gekappt',                 NAWS_Colors::rain_heatmap_color( 80 ), $d['heatmap_r_30'] );
check( 'dazwischen wird gemischt',
    NAWS_Colors::rain_heatmap_color( 7.5 ),
    mixh( $d['heatmap_r_5'], $d['heatmap_r_10'], 0.5 ) );

with_settings( [], [ 'heatmap_r_dry' => '#123456' ] );
check( 'die eingestellte Farbe gilt',       NAWS_Colors::rain_heatmap_color( 0 ), '#123456' );
with_settings( [] );

check( 'die Skala beginnt bei 0,1 mm',      NAWS_Colors::rain_heatmap_scale()[0][0], 0.1 );
check( 'und endet bei 30 mm',               NAWS_Colors::rain_heatmap_scale()[ count( NAWS_Colors::rain_heatmap_scale() ) - 1 ][0], 30 );

$g = NAWS_Colors::get_groups();
check( 'eine eigene Gruppe im Erscheinungsbild',
    $g['heatmap_rain']['keys'] ?? null, array_merge( [ 'heatmap_r_dry' ], NAWS_Colors::RAIN_HEATMAP_KEYS ) );
check( 'ein eigener Reiter, direkt nach der Temperaturskala',
    array_slice( array_keys( NAWS_Colors::appearance_tabs() ), 6, 2 ), [ 'heatmap', 'heatmap_rain' ] );

// ─────────────────────────────────────────────────────────────────────
echo "\nNAWS_Helpers::rain_heatmap_label()\n" . str_repeat( '-', 74 ) . "\n";

with_settings( [ 'rain_unit' => 'mm' ] );
check( 'ein Wert traegt seine Einheit',     NAWS_Helpers::rain_heatmap_label( 3.4 ), '3.4 mm' );
check( 'ein Kipp wird auf 0,1 gerundet',    NAWS_Helpers::rain_heatmap_label( 0.101 ), '0.1 mm' );
check( 'ein trockener Tag sagt das',        NAWS_Helpers::rain_heatmap_label( 0 ), 'Dry' );
check( 'kein Wert sagt das',                NAWS_Helpers::rain_heatmap_label( null ), 'No reading' );
with_settings( [ 'rain_unit' => 'in' ] );
check( 'in Zoll wird umgerechnet',          NAWS_Helpers::rain_heatmap_label( 25.4 ), '1 in' );
with_settings( [ 'rain_unit' => 'mm' ] );

// Das Markup prueft tests/test-heatmap-rain-render.php — dort ersetzt ein
// Stub die Datenbank, und die echte Klasse ist hier schon geladen.

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
