<?php
/**
 * Tests fuer die Ausgabe von [naws_radar]: Zustaende, Planung des
 * Hintergrundlaufs, Nutzlast, REST-Antwort, Datenschutz (kein fremder Host
 * im HTML) und keine id-Attribute (zwei Radare auf einer Seite).
 *
 *   php tests/test-radar-render.php
 *
 * @package NAWS
 */
require_once __DIR__ . '/radar-stubs.php';
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; return true; }
function rest_ensure_response( $d ) { return new WP_REST_Response( $d ); }
class WP_REST_Response { public $data; public $headers = []; public function __construct( $d ) { $this->data = $d; } public function header( $k, $v ) { $this->headers[ $k ] = $v; } }
$P = NAWS_PLUGIN_DIR . 'includes/';
require_once $P . 'class-naws-radar-geo.php';
require_once $P . 'class-naws-radar-source.php';
require_once $P . 'class-naws-radar-store.php';
require_once $P . 'class-naws-radar.php';

$now = gmmktime( 15, 26, 0, 10, 13, 2026 );
$key = NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' );

echo "\nnormalise_atts()\n" . str_repeat( '-', 74 ) . "\n";
$a = NAWS_Radar::normalise_atts( [] );
check( 'Vorgaben',                         $a, [ 'width' => '100%', 'height' => 420, 'km' => '150', 'source' => 'auto' ] );
check( 'Breite 600 wird 600px',            NAWS_Radar::normalise_atts( [ 'width' => '600' ] )['width'], '600px' );
check( 'Breite 80% bleibt',                NAWS_Radar::normalise_atts( [ 'width' => '80%' ] )['width'], '80%' );
check( 'Breite mit CSS-Unsinn wird 100%',  NAWS_Radar::normalise_atts( [ 'width' => '100%;background:red' ] )['width'], '100%' );
check( 'Hoehe 50 wird 200',                NAWS_Radar::normalise_atts( [ 'height' => '50' ] )['height'], 200 );
check( 'Hoehe 5000 wird 1200',             NAWS_Radar::normalise_atts( [ 'height' => '5000' ] )['height'], 1200 );
check( 'Quelle Unsinn wird auto',          NAWS_Radar::normalise_atts( [ 'source' => 'x' ] )['source'], 'auto' );

echo "\nZustaende\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 0;
check( 'aus: Besucher sehen nichts',       NAWS_Radar::render( [], $now ), '' );
$GLOBALS['is_admin'] = true;
check( 'aus: Admin sieht Hinweis',         str_contains( NAWS_Radar::render( [], $now ), 'naws-radar-hint' ), true );
$GLOBALS['is_admin'] = false;
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 1;
$GLOBALS['loc'] = [ 'error' => 'kein Ort' ];
check( 'ohne Ort: nichts',                 NAWS_Radar::render( [], $now ), '' );
$GLOBALS['loc'] = [ 'lat' => 51.35, 'lon' => 12.37, 'name' => 'Leipzig' ];

$html = NAWS_Radar::render( [], $now );
check( 'erster Aufruf: wird vorbereitet',  str_contains( $html, 'naws-radar--preparing' ), true );
check( 'Satz gemerkt',                     isset( NAWS_Radar_Store::sets()[ $key ] ), true );
check( 'Hintergrundlauf geplant',          wp_next_scheduled( NAWS_Radar::HOOK_REFRESH, [ $key ] ), $now );
check( 'Rahmenhoehe als Variable',         str_contains( $html, '--naws-radar-h:420px' ), true );

$m = [ 'v' => 1, 'source' => 'dwd', 'updated' => $now - 60, 'map' => [ 'tiles' => [ [ 'file' => 'map/10-547-341.png', 'left' => 0, 'top' => 0, 'size' => 166.86 ] ] ],
       'frames' => [ [ 't' => $now - 1800, 'forecast' => false, 'file' => 'frames/a.png' ], [ 't' => $now - 900, 'forecast' => false, 'file' => 'frames/b.png' ], [ 't' => $now + 900, 'forecast' => true, 'file' => 'frames/f.png' ] ],
       'retired' => [] ];
NAWS_Radar_Store::save_manifest( $key, $m );
$GLOBALS['scheduled'] = [];
$html = NAWS_Radar::render( [ 'height' => '300' ], $now );
check( 'frisch: bereit',                   str_contains( $html, 'naws-radar--ready' ), true );
check( 'frisch: kein neuer Lauf geplant',  $GLOBALS['scheduled'], [] );
check( 'Standbild "jetzt" ist b.png',      str_contains( $html, '/frames/b.png' ), true );
check( 'Uhrzeit von "jetzt"',              str_contains( $html, gmdate( 'H:i', $now - 900 ) ), true );
check( 'Karte im HTML',                    str_contains( $html, '/map/10-547-341.png' ), true );
check( 'Quellenangabe DWD + OSM',          str_contains( $html, 'Deutscher Wetterdienst' ) && str_contains( $html, 'OpenStreetMap' ), true );
check( 'Regler mit 3 Bildern',             str_contains( $html, 'max="2"' ), true );
check( 'kein id-Attribut',                 preg_match( '/\sid="/', $html ), 0 );
preg_match_all( '#https?://([^/"\s]+)#', $html, $hosts );
check( 'nur die eigene Site im HTML',      array_values( array_unique( $hosts[1] ) ), [ 'example.org' ] );
preg_match( '#<script type="application/json" data-naws="radar">(.*?)</script>#s', $html, $j );
$pl = json_decode( $j[1] ?? '', true );
check( 'JSON: 3 Bilder',                   count( $pl['frames'] ?? [] ), 3 );
check( 'JSON: Vorschau markiert',          $pl['frames'][2]['forecast'] ?? null, true );
check( 'JSON: REST-Adresse',               $pl['rest'] ?? '', 'https://example.org/wp-json/naws/v1/radar/' . $key );

NAWS_Radar_Store::save_manifest( $key, [ 'updated' => $now - 1000 ] + $m );
NAWS_Radar::render( [], $now );
check( 'aelter als 15 min: Lauf geplant',  wp_next_scheduled( NAWS_Radar::HOOK_REFRESH, [ $key ] ), $now );
NAWS_Radar_Store::save_manifest( $key, [ 'updated' => $now - 4 * HOUR_IN_SECONDS ] + $m );
$html = NAWS_Radar::render( [], $now );
check( 'aelter als 3 h: nicht verfuegbar', str_contains( $html, 'naws-radar--unavailable' ), true );
check( 'aelter als 3 h: keine Bilder im JSON', str_contains( $html, '"frames":[]' ), true );

echo "\nREST\n" . str_repeat( '-', 74 ) . "\n";
NAWS_Radar::register_routes();
$route = $GLOBALS['routes']['naws/v1/radar/(?P<key>[a-f0-9]{12})'] ?? null;
check( 'Route registriert, oeffentlich',   $route['permission_callback'] ?? null, '__return_true' );
NAWS_Radar_Store::save_manifest( $key, $m );
$r = NAWS_Radar::rest_manifest( [ 'key' => $key ], $now );
check( 'bekannter Satz: Nutzlast',         count( $r->data['frames'] ?? [] ), 3 );
check( 'nicht zwischenspeichern',          $r->headers['Cache-Control'] ?? '', 'no-store' );
check( 'unbekannter Satz: 404',            NAWS_Radar::rest_manifest( [ 'key' => 'ffffffffffff' ] )->get_error_data()['status'] ?? 0, 404 );
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 0;
check( 'Schalter aus: 404',                NAWS_Radar::rest_manifest( [ 'key' => $key ] ) instanceof WP_Error, true );
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 1;

echo "\nTagesaufraeumen\n" . str_repeat( '-', 74 ) . "\n";
check( 'nach 8 Tagen: Satz weg',           NAWS_Radar::daily_cleanup( $now + 8 * DAY_IN_SECONDS ), 1 );
check( 'Ordner leer',                      NAWS_Radar_Store::manifest( $key ), null );

done();
