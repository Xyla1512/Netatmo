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
check( '16 Kartenkacheln (Zoom 9)',         count( $m['map']['tiles'] ), 16 );
check( '13 DWD-Abrufe',                     calls_to( 'https://maps.dwd.de/' ), 13 );
check( 'feine Ebene: 49 Kacheln (Zoom 10)', [ count( $m['map']['fine'] ?? [] ), substr( $m['map']['fine'][0]['file'] ?? '', 0, 7 ) ], [ 49, 'map/10-' ] );
check( 'feine Ebene vollstaendig',          $m['map']['fine_ok'] ?? null, true );
check( '65 OSM-Abrufe (16 + 49)',           calls_to( 'https://tile.openstreetmap.org/' ), 65 );
check( 'alles in einem Lauf (<= 100)',      count( $GLOBALS['calls'] ) <= 100, true );
$pl = NAWS_Radar::payload( $key, $m, 'dwd', $now );
check( 'Player bekommt die feine Ebene',    count( $pl['fine'] ?? [] ), 49 );
check( '... als [url, links, oben, groesse]', count( $pl['fine'][0] ?? [] ), 4 );
$pl = NAWS_Radar::payload( $key, [ 'map' => [ 'fine_ok' => false ] + $m['map'] ] + $m, 'dwd', $now );
check( 'unvollstaendig: keine feine Ebene', $pl['fine'], [] );
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

echo "\nKarte einer alten Zoomstufe\n" . str_repeat( '-', 74 ) . "\n";
// Ein Ausschnitt aus 2.1.3-Vorabstaenden hat Zoom-10-Kacheln: der naechste
// Lauf loescht, was die neue Karte nicht mehr nutzt.
$m3['map']['tiles'][] = [ 'file' => 'map/11-1-1.png', 'left' => 0, 'top' => 0, 'size' => 166.86 ];
NAWS_Radar_Store::save_manifest( $key, $m3 );
NAWS_Radar_Store::put( $key, 'map/11-1-1.png', 'x' );
reset_http();
NAWS_Radar::refresh( $key, $now + 2760 );
$m4 = NAWS_Radar_Store::manifest( $key );
check( 'alte Kachel geloescht',             NAWS_Radar_Store::has( $key, 'map/11-1-1.png' ), false );
check( 'neue Kacheln bleiben',              NAWS_Radar_Store::has( $key, $m4['map']['tiles'][0]['file'] ), true );
check( 'Karte bleibt 16 Kacheln',           count( $m4['map']['tiles'] ), 16 );

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
// 1) Erster Lauf bricht beim ersten Bild ab: ohne Radarbild keine Karte
//    (Bilder kommen zuerst), der naechste Lauf holt beides.
$GLOBALS['transients'] = [];
$k3 = NAWS_Radar_Store::key( 48.14, 11.58, 150, 'dwd' );
NAWS_Radar_Store::remember( $k3, [ 'lat' => 48.14, 'lon' => 11.58, 'km' => 150, 'source' => 'dwd' ], $now );
$GLOBALS['http_routes'] = [
    'https://tile.openstreetmap.org/' => png( 'osm' ),
    'https://maps.dwd.de/'            => resp( 429, '', [ 'retry-after' => '600' ] ),
];
reset_http();
check( 'erster Lauf bricht ab: kein Manifest', [ NAWS_Radar::refresh( $k3, $now ), NAWS_Radar_Store::manifest( $k3 ) ], [ false, null ] );
check( 'ohne Radarbild keine Kartenabrufe', calls_to( 'https://tile.openstreetmap.org/' ), 0 );
check( '429: Retry-After bleibt (keine 900 s darueber)', $GLOBALS['transients']['naws_radar_backoff_dwd'][1], 600 );
$GLOBALS['transients'] = [];
$GLOBALS['http_routes']['https://maps.dwd.de/'] = png( 'dwd' );
reset_http();
check( 'zweiter Lauf klappt', NAWS_Radar::refresh( $k3, $now + 60 ), true );
check( 'Karte einmal geholt (12 + 42 fein)', calls_to( 'https://tile.openstreetmap.org/' ), 54 );
$b3 = NAWS_Radar_Geo::box( 48.14, 11.58, 150 );
check( 'Manifest mit allen Kartenkacheln (Muenchen: 12)', [ count( NAWS_Radar_Store::manifest( $k3 )['map']['tiles'] ), count( NAWS_Radar_Geo::tiles( $b3, NAWS_Radar_Geo::map_zoom( $b3 ) ) ) ], [ 12, 12 ] );

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

echo "\nPausen statt Wiederholung im Seitentakt\n" . str_repeat( '-', 74 ) . "\n";
/** Neuer DWD-Satz ohne Dateien, Sperrfristen und Zeitplan leer. */
function fresh_dwd( float $lat ): string {
    $GLOBALS['transients'] = [];
    $GLOBALS['scheduled']  = [];
    $k = NAWS_Radar_Store::key( $lat, 12.37, 150, 'dwd' );
    NAWS_Radar_Store::remember( $k, [ 'lat' => $lat, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $GLOBALS['now'] );
    reset_http();
    return $k;
}
$osm_ok = png( 'osm' );

$k = fresh_dwd( 50.10 );
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => resp( 500, 'oops' ), 'https://tile.openstreetmap.org/' => $osm_ok ];
NAWS_Radar::refresh( $k, $now );
check( 'DWD 500: Pause',                    [ NAWS_Radar_Store::in_backoff( 'dwd' ), calls_to( 'https://maps.dwd.de/' ) ], [ true, 1 ] );

$k = fresh_dwd( 50.11 );
$GLOBALS['http_routes'] = [ 'https://tile.openstreetmap.org/' => $osm_ok ]; // DWD: keine Route -> WP_Error
NAWS_Radar::refresh( $k, $now );
check( 'kein Netz (WP_Error): Pause',       [ NAWS_Radar_Store::in_backoff( 'dwd' ), calls_to( 'https://maps.dwd.de/' ) ], [ true, 1 ] );

foreach ( [ 401, 403, 418 ] as $i => $code ) {
    $k = fresh_dwd( 50.12 + $i / 100 );
    $GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => resp( $code, 'nein' ), 'https://tile.openstreetmap.org/' => $osm_ok ];
    NAWS_Radar::refresh( $k, $now );
    check( "DWD $code: Pause nach dem ersten Abruf", [ NAWS_Radar_Store::in_backoff( 'dwd' ), calls_to( 'https://maps.dwd.de/' ) ], [ true, 1 ] );
}

$k = fresh_dwd( 50.20 );
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => png( 'dwd' ), 'https://tile.openstreetmap.org/' => resp( 403, 'blocked' ) ];
check( 'OSM 403: Lauf klappt trotzdem',     NAWS_Radar::refresh( $k, $now ), true );
check( 'OSM 403: OSM pausiert nach einem Abruf', [ NAWS_Radar_Store::in_backoff( 'osm' ), calls_to( 'https://tile.openstreetmap.org/' ) ], [ true, 1 ] );
check( 'OSM 403: DWD-Bilder gespeichert, DWD frei', [ count( NAWS_Radar_Store::manifest( $k )['frames'] ), NAWS_Radar_Store::in_backoff( 'dwd' ) ], [ 13, false ] );

$k = fresh_dwd( 50.21 );
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => resp( 200, '<ServiceExceptionReport/>' ), 'https://tile.openstreetmap.org/' => $osm_ok ];
check( 'nur XML statt PNG: kein Manifest',  [ NAWS_Radar::refresh( $k, $now ), NAWS_Radar_Store::manifest( $k ) ], [ false, null ] );
check( 'ohne Bild: DWD 15 min pausiert',    [ NAWS_Radar_Store::in_backoff( 'dwd' ), $GLOBALS['transients']['naws_radar_backoff_dwd'][1] ?? 0 ], [ true, 900 ] );
check( 'ohne Bild: keine Kartenabrufe',     calls_to( 'https://tile.openstreetmap.org/' ), 0 );

// maybe_schedule(): nach einem Versuch nicht vor Ablauf von 15 Minuten wieder.
$GLOBALS['transients'] = [];
NAWS_Radar::maybe_schedule( $k, null, $now + 60 );
check( 'letzter Versuch vor 1 min: nicht geplant', $GLOBALS['scheduled'], [] );
NAWS_Radar::maybe_schedule( $k, null, $now + 899 );
check( 'vor 899 s: nicht geplant',          $GLOBALS['scheduled'], [] );
NAWS_Radar::maybe_schedule( $k, null, $now + 900 );
check( 'nach 15 min: geplant',              wp_next_scheduled( NAWS_Radar::HOOK_REFRESH, [ $k ] ), $now + 900 );
check( 'last_try steht im Satz',            NAWS_Radar_Store::sets()[ $k ]['last_try'] ?? null, $now );
NAWS_Radar_Store::remember( $k, [ 'lat' => 50.21, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now + 7200 );
check( 'remember() behaelt last_try',       NAWS_Radar_Store::sets()[ $k ]['last_try'] ?? null, $now );

$k = fresh_dwd( 50.22 );
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => png( 'dwd' ), 'https://tile.openstreetmap.org/' => $osm_ok ];
$GLOBALS['logged'] = [];
fs()->fail_put = true;
check( 'Uploads nicht beschreibbar: kein Manifest', NAWS_Radar::refresh( $k, $now ), false );
fs()->fail_put = false;
check( 'Uploads: genau eine Fehlermeldung', count( array_filter( $GLOBALS['logged'], fn( $l ) => str_contains( $l, 'not writable' ) ) ), 1 );
check( 'Uploads: DWD 15 min pausiert',      [ NAWS_Radar_Store::in_backoff( 'dwd' ), $GLOBALS['transients']['naws_radar_backoff_dwd'][1] ?? 0 ], [ true, 900 ] );
check( 'Uploads: nach dem ersten Fehlschlag keine Abrufe mehr', count( $GLOBALS['calls'] ), 1 );

echo "\nAbrufgrenze: hoechstens 100 je Lauf\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['transients'] = [];
$ok = NAWS_Radar_Store::key( 59.91, 10.75, 1000, 'rainviewer' );
NAWS_Radar_Store::remember( $ok, [ 'lat' => 59.91, 'lon' => 10.75, 'km' => 1000, 'source' => 'rainviewer' ], $rnow );
$GLOBALS['http_routes'] = [
    'https://api.rainviewer.com/'       => resp( 200, json_encode( [ 'host' => 'https://tilecache.rainviewer.com', 'radar' => [ 'past' => $past ] ] ) ),
    'https://tilecache.rainviewer.com/' => png( 'rv' ),
    'https://tile.openstreetmap.org/'   => png( 'osm' ),
];
reset_http();
check( 'Oslo 1000 km: Lauf klappt',         NAWS_Radar::refresh( $ok, $rnow ), true );
check( 'Oslo 1000 km: hoechstens 100 Abrufe', count( $GLOBALS['calls'] ) <= 100, true );
$om = NAWS_Radar_Store::manifest( $ok );
check( 'Oslo 1000 km: hoechstens 9 Kacheln je Bild', max( array_map( fn( $f ) => count( $f['tiles'] ), $om['frames'] ) ) <= 9, true );
check( 'Oslo 1000 km: neuestes Bild dabei', end( $om['frames'] )['t'], end( $past )['time'] );
$ts = array_column( $om['frames'], 't' );
$sorted = $ts;
sort( $sorted );
check( 'Oslo 1000 km: aufsteigend sortiert', $ts, $sorted );
check( 'Oslo 1000 km: keine Pause',         NAWS_Radar_Store::in_backoff( 'rainviewer' ), false );

// DWD: Bilder zuerst, die Karte nimmt den Rest; fehlende Kacheln im naechsten Lauf.
$GLOBALS['transients'] = [];
$kc = NAWS_Radar_Store::key( 51.35, 12.37, 90, 'dwd' );
NAWS_Radar_Store::remember( $kc, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 90, 'source' => 'dwd' ], $now );
$bc   = NAWS_Radar_Geo::box( 51.35, 12.37, 90 );
$need = count( NAWS_Radar_Geo::tiles( $bc, NAWS_Radar_Geo::map_zoom( $bc ) ) );
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => png( 'dwd' ), 'https://tile.openstreetmap.org/' => png( 'osm' ) ];
reset_http();
check( 'gedeckelt (30): Lauf klappt',       NAWS_Radar::refresh( $kc, $now, 30 ), true );
$cm = NAWS_Radar_Store::manifest( $kc );
check( 'gedeckelt: 30 Abrufe, 13 Bilder, 17 Kartenkacheln', [ count( $GLOBALS['calls'] ), count( $cm['frames'] ), count( $cm['map']['tiles'] ) ], [ 30, 13, 17 ] );
check( 'gedeckelt: feine Ebene erst nach der groben', [ count( $cm['map']['fine'] ), $cm['map']['fine_ok'] ], [ 0, false ] );
check( 'gedeckelt: keine Pause',            [ NAWS_Radar_Store::in_backoff( 'dwd' ), NAWS_Radar_Store::in_backoff( 'osm' ) ], [ false, false ] );
reset_http();
check( 'naechster Lauf klappt',             NAWS_Radar::refresh( $kc, $now + 60 ), true );
$mc = NAWS_Radar_Store::manifest( $kc );
$fn = count( NAWS_Radar_Geo::tiles( $bc, NAWS_Radar_Geo::map_zoom( $bc ) + 1 ) );
check( 'Karte vollstaendig, nur der Rest + feine Ebene', [ count( $mc['map']['tiles'] ), count( $mc['map']['fine'] ), calls_to( 'https://tile.openstreetmap.org/' ) ], [ $need, $fn, $need - 17 + $fn ] );
check( 'Bilder plus Karte gingen nicht in einen Lauf mit 30', 13 + $need > 30, true );
check( 'Sperrdauer 10 min',                 NAWS_Radar_Store::LOCK_TTL, 600 );

done();
