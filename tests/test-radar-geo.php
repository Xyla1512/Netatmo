<?php
/**
 * Tests fuer NAWS_Radar_Geo: Ausschnitt, Zoomstufe und Kacheln des
 * Regenradars. Reine Rechnung, keine Abrufe.
 *
 * Die Sollwerte stammen aus einer unabhaengigen Rechnung (Node, 13.10.2026).
 *
 *   php tests/test-radar-geo.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-geo.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function covers( array $tiles ): bool {
    $minl = min( array_column( $tiles, 'left' ) );
    $mint = min( array_column( $tiles, 'top' ) );
    $maxr = max( array_map( fn( $t ) => $t['left'] + $t['size'], $tiles ) );
    $maxb = max( array_map( fn( $t ) => $t['top'] + $t['size'], $tiles ) );
    return $minl <= 0 && $mint <= 0 && $maxr >= 1024 && $maxb >= 1024;
}

echo "\nclamp_km()\n" . str_repeat( '-', 74 ) . "\n";
check( 'DWD: 150 bleibt',              NAWS_Radar_Geo::clamp_km( '150', 'dwd' ), 150 );
check( 'DWD: 10 wird 50',              NAWS_Radar_Geo::clamp_km( '10', 'dwd' ), 50 );
check( 'DWD: 5000 wird 600',           NAWS_Radar_Geo::clamp_km( '5000', 'dwd' ), 600 );
check( 'RainViewer: 50 wird 150',      NAWS_Radar_Geo::clamp_km( '50', 'rainviewer' ), 150 );
check( 'RainViewer: 5000 wird 1500',   NAWS_Radar_Geo::clamp_km( '5000', 'rainviewer' ), 1500 );
check( 'Unsinn wird Vorgabe 150',      NAWS_Radar_Geo::clamp_km( 'abc', 'dwd' ), 150 );
check( 'Kommazahl wird gerundet',      NAWS_Radar_Geo::clamp_km( '99.6', 'dwd' ), 100 );

echo "\nbox() und zoom_for(): Leipzig, 150 km\n" . str_repeat( '-', 74 ) . "\n";
$b = NAWS_Radar_Geo::box( 51.35, 12.37, 150 );
check( 'minx',                         (int) round( $b[0] ), 1256938 );
check( 'miny',                         (int) round( $b[1] ), 6563355 );
check( 'maxx',                         (int) round( $b[2] ), 1497106 );
check( 'maxy',                         (int) round( $b[3] ), 6803524 );
check( 'Box ist quadratisch',          abs( ( $b[2] - $b[0] ) - ( $b[3] - $b[1] ) ) < 0.001, true );
check( 'Zoomstufe 10',                 NAWS_Radar_Geo::zoom_for( $b ), 10 );

$t = NAWS_Radar_Geo::tiles( $b, 10 );
check( '49 Kacheln',                   count( $t ), 49 );
check( 'x 544 bis 550',                [ min( array_column( $t, 'x' ) ), max( array_column( $t, 'x' ) ) ], [ 544, 550 ] );
check( 'y 338 bis 344',                [ min( array_column( $t, 'y' ) ), max( array_column( $t, 'y' ) ) ], [ 338, 344 ] );
check( 'erste Kachel links/oben',      [ $t[0]['left'], $t[0]['top'] ], [ -19.59, -26.0 ] );
check( 'Kachelgroesse',                $t[0]['size'], 166.86 );
check( 'Leipzig liegt in Kachel 547/341', count( array_filter( $t, fn( $q ) => 547 === $q['x'] && 341 === $q['y'] ) ), 1 );
check( 'Kacheln decken das Bild ganz', covers( $t ), true );

echo "\nKleiner Ausschnitt, hoher Norden, Datumsgrenze, RainViewer\n" . str_repeat( '-', 74 ) . "\n";
$b50 = NAWS_Radar_Geo::box( 51.35, 12.37, 50 );
check( '50 km: Zoomstufe 11',          NAWS_Radar_Geo::zoom_for( $b50 ), 11 );
check( '50 km: 25 Kacheln',            count( NAWS_Radar_Geo::tiles( $b50, 11 ) ), 25 );

$bt = NAWS_Radar_Geo::box( 69.65, 18.96, 150 );
check( 'Tromsoe: Zoomstufe 9',         NAWS_Radar_Geo::zoom_for( $bt ), 9 );
$tt = NAWS_Radar_Geo::tiles( $bt, 9 );
check( 'Tromsoe: 36 Kacheln',          count( $tt ), 36 );
check( 'Tromsoe: deckt das Bild',      covers( $tt ), true );

$bd = NAWS_Radar_Geo::box( 0.0, 179.9, 600 );
$td = NAWS_Radar_Geo::tiles( $bd, NAWS_Radar_Geo::zoom_for( $bd ) );
$xs = array_values( array_unique( array_column( $td, 'x' ) ) );
check( 'Datumsgrenze: x laeuft 508..511, 0..3', $xs, [ 508, 509, 510, 511, 0, 1, 2, 3 ] );
check( 'Datumsgrenze: deckt das Bild', covers( $td ), true );

$rv = NAWS_Radar_Geo::tiles( $b, min( NAWS_Radar_Geo::zoom_for( $b ), NAWS_Radar_Geo::RV_MAX_ZOOM ) );
check( 'RainViewer Leipzig: Zoom 7, 2 Kacheln', [ $rv[0]['z'], count( $rv ) ], [ 7, 2 ] );
check( 'RainViewer: Kachel 68/42 zuerst', [ $rv[0]['x'], $rv[0]['y'] ], [ 68, 42 ] );
check( 'RainViewer: deckt das Bild',   covers( $rv ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
