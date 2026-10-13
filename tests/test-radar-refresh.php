<?php
/**
 * Tests fuer NAWS_Radar::refresh(): was der Hintergrundlauf holt, was er
 * wiederverwendet, was er bei Fehlern tut — alles ohne echte Abrufe.
 *
 *   php tests/test-radar-refresh.php
 *
 * @package NAWS
 */
require_once __DIR__ . '/radar-stubs.php';
$P = dirname( __DIR__ ) . '/includes/';
require_once $P . 'class-naws-radar-geo.php';
require_once $P . 'class-naws-radar-source.php';
require_once $P . 'class-naws-radar-store.php';
require_once $P . 'class-naws-radar.php';

function reset_http(): void { $GLOBALS['calls'] = []; }
$GLOBALS['http_routes'] = [
    'https://tile.openstreetmap.org/' => png( 'osm' ),
    'https://maps.dwd.de/'            => png( 'dwd' ),
];
$key = NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' );
$now = gmmktime( 15, 26, 0, 10, 13, 2026 );
NAWS_Radar_Store::remember( $key, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now );

echo "\nDWD: erster Lauf\n" . str_repeat( '-', 74 ) . "\n";
check( 'Lauf klappt',                       NAWS_Radar::refresh( $key, $now ), true );
$m = NAWS_Radar_Store::manifest( $key );
check( '13 Bilder',                         count( $m['frames'] ), 13 );
check( '8 davon Vorschau',                  count( array_filter( $m['frames'], fn( $f ) => $f['forecast'] ) ), 8 );
check( '49 Kartenkacheln',                  count( $m['map']['tiles'] ), 49 );
check( '13 DWD-Abrufe',                     calls_to( 'https://maps.dwd.de/' ), 13 );
check( '49 OSM-Abrufe',                     calls_to( 'https://tile.openstreetmap.org/' ), 49 );
check( 'Stand',                             $m['updated'], $now );
check( 'gemessenes Bild heisst frames/<t>.png', $m['frames'][4]['file'], 'frames/' . gmmktime( 15, 15, 0, 10, 13, 2026 ) . '.png' );
check( 'Sperre wieder frei',                NAWS_Radar_Store::lock( $key ), true );
NAWS_Radar_Store::unlock( $key );

echo "\nDWD: 15 Minuten spaeter\n" . str_repeat( '-', 74 ) . "\n";
reset_http();
check( 'Lauf klappt',                       NAWS_Radar::refresh( $key, $now + 900 ), true );
check( 'nur 1 gemessenes + 8 Vorschau',     calls_to( 'https://maps.dwd.de/' ), 9 );
check( 'Karte nicht neu geholt',            calls_to( 'https://tile.openstreetmap.org/' ), 0 );
$m2 = NAWS_Radar_Store::manifest( $key );
check( 'alte Vorschau ausgemustert (8) + aeltestes gemessenes (1)', count( $m2['retired'] ), 9 );
reset_http();
NAWS_Radar::refresh( $key, $now + 2700 );
$m3 = NAWS_Radar_Store::manifest( $key );
$first_forecast = $m['frames'][5]['file'];
check( 'nach 30 min: erste Vorschau-Dateien geloescht', NAWS_Radar_Store::has( $key, $first_forecast ), false );
check( 'keine verwaiste Datei', count( array_diff(
    array_keys( array_filter( fs()->files, fn( $c, $p ) => str_contains( $p, "/$key/frames/" ), ARRAY_FILTER_USE_BOTH ) ),
    array_map( fn( $f ) => NAWS_Radar_Store::dir( $key ) . '/' . $f, array_merge( NAWS_Radar_Store::files_of( $m3 ), array_keys( $m3['retired'] ) ) )
) ), 0 );

echo "\nFehler\n" . str_repeat( '-', 74 ) . "\n";
// Eine Vorschau noch nicht gerechnet: 200 mit XML statt PNG.
$GLOBALS['http_routes'] = [
    'https://maps.dwd.de/' => function ( $url ) { return str_contains( $url, 'Radar_wn' ) && str_contains( $url, 'T17%3A' ) ? resp( 200, '<ServiceExceptionReport/>' ) : png( 'dwd' ); },
] + $GLOBALS['http_routes'];
reset_http();
check( 'Lauf klappt trotzdem',              NAWS_Radar::refresh( $key, $now + 3600 ), true );
check( 'fehlende Bilder uebersprungen, keine Sperrfrist', [ count( NAWS_Radar_Store::manifest( $key )['frames'] ) < 13, NAWS_Radar_Store::in_backoff( 'dwd' ) ], [ true, false ] );

// 429 nach drei Bildern.
$n = 0;
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => function () use ( &$n ) { return ++$n > 3 ? resp( 429, '', [ 'retry-after' => '600' ] ) : png( 'dwd' ); } ] + $GLOBALS['http_routes'];
$before = NAWS_Radar_Store::manifest( $key );
reset_http();
NAWS_Radar::refresh( $key, $now + 4500 );
check( '429: Sperrfrist gesetzt',           NAWS_Radar_Store::in_backoff( 'dwd' ), true );
check( '429: nach dem Fehler keine weiteren Abrufe', calls_to( 'https://maps.dwd.de/' ), 4 );
$after = NAWS_Radar_Store::manifest( $key );
check( '429: Manifest bleibt nutzbar',      count( $after['frames'] ) >= 1, true );
reset_http();
check( 'waehrend der Sperrfrist kein Lauf', [ NAWS_Radar::refresh( $key, $now + 4600 ), count( $GLOBALS['calls'] ) ], [ false, 0 ] );

echo "\nSchalter, unbekannter Satz, Sperre\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['transients'] = [];
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 0;
reset_http();
check( 'Schalter aus: kein Lauf, kein Abruf', [ NAWS_Radar::refresh( $key, $now + 5400 ), count( $GLOBALS['calls'] ) ], [ false, 0 ] );
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 1;
check( 'unbekannter Satz: kein Lauf',       NAWS_Radar::refresh( 'ffffffffffff', $now ), false );
NAWS_Radar_Store::lock( $key );
check( 'gesperrt: kein Lauf',               NAWS_Radar::refresh( $key, $now + 5400 ), false );
NAWS_Radar_Store::unlock( $key );

echo "\nRainViewer\n" . str_repeat( '-', 74 ) . "\n";
$rk  = NAWS_Radar_Store::key( 59.91, 10.75, 150, 'rainviewer' );
$rnow = 1791904826;
NAWS_Radar_Store::remember( $rk, [ 'lat' => 59.91, 'lon' => 10.75, 'km' => 150, 'source' => 'rainviewer' ], $rnow );
$past = [];
for ( $i = 12; $i >= 0; $i-- ) { $past[] = [ 'time' => $rnow - $i * 600, 'path' => '/v2/radar/p' . $i ]; }
$GLOBALS['http_routes'] = [
    'https://api.rainviewer.com/'      => resp( 200, json_encode( [ 'host' => 'https://tilecache.rainviewer.com', 'radar' => [ 'past' => $past ] ] ) ),
    'https://tilecache.rainviewer.com/' => png( 'rv' ),
    'https://tile.openstreetmap.org/'  => png( 'osm' ),
];
reset_http();
check( 'Lauf klappt',                       NAWS_Radar::refresh( $rk, $rnow ), true );
$rm = NAWS_Radar_Store::manifest( $rk );
check( '13 Bilder, keine Vorschau',         [ count( $rm['frames'] ), count( array_filter( $rm['frames'], fn( $f ) => $f['forecast'] ) ) ], [ 13, 0 ] );
check( 'jedes Bild aus Kacheln',            isset( $rm['frames'][0]['tiles'][0]['file'] ), true );
$per = count( $rm['frames'][0]['tiles'] );
check( 'Kacheln = Bilder x Kacheln je Bild', calls_to( 'https://tilecache.rainviewer.com/' ), 13 * $per );
reset_http();
NAWS_Radar::refresh( $rk, $rnow + 900 );
check( 'zweiter Lauf: nur der Index, keine alten Kacheln', calls_to( 'https://tilecache.rainviewer.com/' ), 0 );
check( 'User-Agent nennt Plugin und Site',  NAWS_Radar::user_agent(), 'XTX-Integration-for-Netatmo/test (+https://example.org/)' );

echo "\nNachbesserung\n" . str_repeat( '-', 74 ) . "\n";
// 1) Erster Lauf bricht beim ersten Bild ab: Karte bleibt, wird nicht neu geholt.
$GLOBALS['transients'] = [];
$k3 = NAWS_Radar_Store::key( 48.14, 11.58, 150, 'dwd' );
NAWS_Radar_Store::remember( $k3, [ 'lat' => 48.14, 'lon' => 11.58, 'km' => 150, 'source' => 'dwd' ], $now );
$GLOBALS['http_routes'] = [
    'https://tile.openstreetmap.org/' => png( 'osm' ),
    'https://maps.dwd.de/'            => resp( 429, '', [ 'retry-after' => '600' ] ),
];
reset_http();
check( 'erster Lauf bricht ab: kein Manifest', [ NAWS_Radar::refresh( $k3, $now ), NAWS_Radar_Store::manifest( $k3 ) ], [ false, null ] );
$GLOBALS['transients'] = [];
$GLOBALS['http_routes']['https://maps.dwd.de/'] = png( 'dwd' );
reset_http();
check( 'zweiter Lauf klappt', NAWS_Radar::refresh( $k3, $now + 60 ), true );
check( 'Karte nicht neu geholt (0 OSM-Abrufe)', calls_to( 'https://tile.openstreetmap.org/' ), 0 );
$b3 = NAWS_Radar_Geo::box( 48.14, 11.58, 150 );
check( 'Manifest mit allen Kartenkacheln (Muenchen: 42)', [ count( NAWS_Radar_Store::manifest( $k3 )['map']['tiles'] ), count( NAWS_Radar_Geo::tiles( $b3, NAWS_Radar_Geo::zoom_for( $b3 ) ) ) ], [ 42, 42 ] );

// 2) RainViewer: 2. Kachel eines Bildes liefert 429 -> keine Datei dieses Bildes bleibt.
$GLOBALS['transients'] = [];
$rk2 = NAWS_Radar_Store::key( 59.91, 10.75, 300, 'rainviewer' );
NAWS_Radar_Store::remember( $rk2, [ 'lat' => 59.91, 'lon' => 10.75, 'km' => 300, 'source' => 'rainviewer' ], $rnow );
$m_tiles = 0;
$GLOBALS['http_routes'] = [
    'https://api.rainviewer.com/'       => resp( 200, json_encode( [ 'host' => 'https://tilecache.rainviewer.com', 'radar' => [ 'past' => $past ] ] ) ),
    'https://tilecache.rainviewer.com/' => function () use ( &$m_tiles ) { return 2 === ++$m_tiles ? resp( 429, '', [ 'retry-after' => '600' ] ) : png( 'rv' ); },
    'https://tile.openstreetmap.org/'   => png( 'osm' ),
];
reset_http();
NAWS_Radar::refresh( $rk2, $rnow );
$left = array_filter( array_keys( fs()->files ), fn( $p ) => str_contains( $p, "/$rk2/frames/" ) );
check( 'RainViewer: abgebrochenes Bild hinterlaesst keine Kacheln', count( $left ), 0 );

// 3) Kaputter Index startet die Sperrfrist.
$GLOBALS['transients'] = [];
$GLOBALS['http_routes'] = [ 'https://api.rainviewer.com/' => resp( 200, 'kein json' ) ] + $GLOBALS['http_routes'];
reset_http();
NAWS_Radar::refresh( $rk2, $rnow + 900 );
check( 'kaputter Index: Sperrfrist gesetzt', NAWS_Radar_Store::in_backoff( 'rainviewer' ), true );

done();
