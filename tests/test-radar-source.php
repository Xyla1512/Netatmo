<?php
/**
 * Tests fuer NAWS_Radar_Source: Deutschland oder nicht, die Zeiten der
 * DWD-Animation, die WMS-Adresse und die Auswahl aus dem RainViewer-Index.
 *
 *   php tests/test-radar-source.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-geo.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-source.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nin_germany() und pick()\n" . str_repeat( '-', 74 ) . "\n";
$in  = [ 'Leipzig' => [ 51.35, 12.37 ], 'Flensburg' => [ 54.79, 9.43 ], 'Aachen' => [ 50.78, 6.08 ], 'Goerlitz' => [ 51.15, 14.99 ],
         'Konstanz' => [ 47.66, 9.17 ], 'Passau' => [ 48.57, 13.43 ], 'Saarbruecken' => [ 49.24, 6.99 ], 'Stralsund' => [ 54.31, 13.09 ] ];
$out = [ 'Zuerich' => [ 47.37, 8.54 ], 'Basel' => [ 47.56, 7.59 ], 'Strassburg' => [ 48.58, 7.75 ], 'Prag' => [ 50.08, 14.42 ],
         'Salzburg' => [ 47.80, 13.04 ], 'Amsterdam' => [ 52.37, 4.90 ], 'Kopenhagen' => [ 55.68, 12.57 ], 'Oslo' => [ 59.91, 10.75 ] ];
foreach ( $in as $name => [ $lat, $lon ] )  { check( "$name liegt in Deutschland", NAWS_Radar_Source::in_germany( $lat, $lon ), true ); }
foreach ( $out as $name => [ $lat, $lon ] ) { check( "$name liegt nicht in Deutschland", NAWS_Radar_Source::in_germany( $lat, $lon ), false ); }
check( 'auto in Leipzig: DWD',            NAWS_Radar_Source::pick( 'auto', 51.35, 12.37 ), 'dwd' );
check( 'auto in Oslo: RainViewer',        NAWS_Radar_Source::pick( 'auto', 59.91, 10.75 ), 'rainviewer' );
check( 'dwd in Oslo faellt zurueck',      NAWS_Radar_Source::pick( 'dwd', 59.91, 10.75 ), 'rainviewer' );
check( 'rainviewer in Leipzig erzwungen', NAWS_Radar_Source::pick( 'rainviewer', 51.35, 12.37 ), 'rainviewer' );
check( 'Unsinn gilt als auto',            NAWS_Radar_Source::pick( 'xyz', 51.35, 12.37 ), 'dwd' );

echo "\ndwd_times()\n" . str_repeat( '-', 74 ) . "\n";
$now   = gmmktime( 15, 26, 0, 10, 13, 2026 );       // 15:26 UTC
$times = NAWS_Radar_Source::dwd_times( $now );
check( '13 Bilder',                       count( $times ), 13 );
check( 'erstes Bild 14:15 UTC',           gmdate( 'H:i', $times[0]['t'] ), '14:15' );
check( 'Bild "jetzt" 15:15 UTC',          gmdate( 'H:i', $times[4]['t'] ), '15:15' );
check( 'letztes Bild 17:15 UTC',          gmdate( 'H:i', $times[12]['t'] ), '17:15' );
check( '5 gemessen, 8 Vorschau',          array_count_values( array_map( fn( $t ) => $t['forecast'] ? 'f' : 'p', $times ) ), [ 'p' => 5, 'f' => 8 ] );
check( 'um 15:24 ist "jetzt" noch 15:00', gmdate( 'H:i', NAWS_Radar_Source::dwd_times( $now - 120 )[4]['t'] ), '15:00' );

echo "\ndwd_url()\n" . str_repeat( '-', 74 ) . "\n";
$box = NAWS_Radar_Geo::box( 51.35, 12.37, 150 );
$u   = NAWS_Radar_Source::dwd_url( $box, $times[4]['t'], false );
check( 'Host ist maps.dwd.de',            strpos( $u, 'https://maps.dwd.de/geoserver/dwd/wms?' ), 0 );
check( 'Ebene Niederschlagsradar',        str_contains( $u, 'layers=dwd%3ANiederschlagsradar' ), true );
check( 'Vorschau-Ebene',                  str_contains( NAWS_Radar_Source::dwd_url( $box, $times[5]['t'], true ), 'layers=dwd%3ARadar_wn-product_1x1km_ger' ), true );
check( 'Zeit im ISO-Format',              str_contains( $u, 'time=2026-10-13T15%3A15%3A00Z' ), true );
check( '1024 x 1024 px',                  str_contains( $u, 'width=1024&height=1024' ), true );
check( 'Web-Mercator',                    str_contains( $u, 'srs=EPSG%3A3857' ), true );
setlocale( LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'deu' );
$ul = NAWS_Radar_Source::dwd_url( $box, $times[4]['t'], false );
setlocale( LC_NUMERIC, 'C' );
preg_match( '/bbox=([^&]+)/', $ul, $m );
check( 'bbox auch unter de_DE mit Punkten', rawurldecode( $m[1] ?? '' ), '1256937.82,6563355.48,1497106.38,6803524.03' );

echo "\nRainViewer\n" . str_repeat( '-', 74 ) . "\n";
$now   = 1791904826;
$past  = [];
for ( $i = 14; $i >= 0; $i-- ) { $past[] = [ 'time' => $now - $i * 600, 'path' => '/v2/radar/abc' . $i ]; }
$past[] = [ 'time' => $now - 9000, 'path' => '/v2/radar/zualt' ];
$past[] = [ 'time' => $now - 1200, 'path' => '/v2/radar/../../etc' ];
$index = [ 'host' => 'https://tilecache.rainviewer.com', 'radar' => [ 'past' => $past ] ];
check( 'Host wird angenommen',            NAWS_Radar_Source::rv_host( $index ), 'https://tilecache.rainviewer.com' );
check( 'fremder Host wird verworfen',     NAWS_Radar_Source::rv_host( [ 'host' => 'https://evil.example' ] ), null );
check( 'http wird verworfen',             NAWS_Radar_Source::rv_host( [ 'host' => 'http://tilecache.rainviewer.com' ] ), null );
check( 'Host mit Zeilenumbruch verworfen', NAWS_Radar_Source::rv_host( [ 'host' => "https://tilecache.rainviewer.com\n" ] ), null );
$warned = [];
set_error_handler( function ( $no, $str ) use ( &$warned ) { $warned[] = $str; return true; } );
$odd = NAWS_Radar_Source::rv_frames( [ 'radar' => [ 'past' => [
    [ 'time' => $now - 600, 'path' => [ 'x' ] ],
    [ 'time' => [ 1 ], 'path' => '/v2/radar/arr' ],
    [ 'time' => $now - 300, 'path' => "/v2/radar/nl\n" ],
    'kein Array',
    [ 'time' => $now, 'path' => '/v2/radar/ok' ],
] ] ], $now );
restore_error_handler();
check( 'seltsame Eintraege uebersprungen', array_column( $odd, 'path' ), [ '/v2/radar/ok' ] );
check( 'ohne PHP-Warnung',                 $warned, [] );
$fr = NAWS_Radar_Source::rv_frames( $index, $now );
check( 'hoechstens 13 Bilder',            count( $fr ), 13 );
check( 'aufsteigend',                     $fr[0]['t'] < $fr[12]['t'], true );
check( 'juengstes zuletzt',               $fr[12]['path'], '/v2/radar/abc0' );
check( 'zu alte und kaputte Pfade fehlen', array_filter( $fr, fn( $f ) => in_array( $f['path'], [ '/v2/radar/zualt', '/v2/radar/../../etc' ], true ) ), [] );
check( 'leerer Index: keine Bilder',      NAWS_Radar_Source::rv_frames( [], $now ), [] );
check( 'Kachel-URL',                      NAWS_Radar_Source::rv_tile_url( 'https://tilecache.rainviewer.com', '/v2/radar/abc0', 7, 68, 42 ), 'https://tilecache.rainviewer.com/v2/radar/abc0/256/7/68/42/2/1_1.png' );
check( 'OSM-Kachel-URL',                  NAWS_Radar_Source::osm_tile_url( 10, 547, 341 ), 'https://tile.openstreetmap.org/10/547/341.png' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
