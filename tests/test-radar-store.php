<?php
/**
 * Tests fuer NAWS_Radar_Store: Schluessel, Ablage, Manifest, Sperre,
 * Sperrfrist und das Loeschen alter Bilder (Frank, 13.10.2026: "alte Bilder
 * sollten auch wieder vom Server geloescht werden").
 *
 *   php tests/test-radar-store.php
 *
 * @package NAWS
 */
require_once __DIR__ . '/radar-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-store.php';

echo "\nSchluessel, Pfade, Saetze\n" . str_repeat( '-', 74 ) . "\n";
$k1 = NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' );
$k2 = NAWS_Radar_Store::key( 51.35, 12.37, 100, 'dwd' );
check( 'Schluessel: 12 Hex-Zeichen',       (bool) preg_match( '/^[a-f0-9]{12}$/', $k1 ), true );
check( 'anderes km, anderer Satz',          $k1 !== $k2, true );
check( 'gleiche Eingabe, gleicher Schluessel', NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' ), $k1 );
check( 'Ordner',                            NAWS_Radar_Store::dir( $k1 ), '/up/naws-radar/' . $k1 );
check( 'URL',                               NAWS_Radar_Store::url( $k1, 'frames/1.png' ), 'https://example.org/wp-content/uploads/naws-radar/' . $k1 . '/frames/1.png' );

$now = 1791900000;
NAWS_Radar_Store::remember( $k1, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now );
check( 'Satz gemerkt',                      NAWS_Radar_Store::sets()[ $k1 ]['last_seen'] ?? null, $now );
NAWS_Radar_Store::remember( $k1, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now + 600 );
check( 'innerhalb einer Stunde nicht neu geschrieben', NAWS_Radar_Store::sets()[ $k1 ]['last_seen'], $now );
NAWS_Radar_Store::remember( $k1, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now + 3700 );
check( 'nach einer Stunde aufgefrischt',    NAWS_Radar_Store::sets()[ $k1 ]['last_seen'], $now + 3700 );

echo "\nAblage und Manifest\n" . str_repeat( '-', 74 ) . "\n";
check( 'noch kein Manifest',                NAWS_Radar_Store::manifest( $k1 ), null );
check( 'Bild ablegen',                      NAWS_Radar_Store::put( $k1, 'frames/1.png', 'PNG' ), true );
check( 'Bild ist da',                       NAWS_Radar_Store::has( $k1, 'frames/1.png' ), true );
$m = [ 'v' => 1, 'source' => 'dwd', 'updated' => $now, 'map' => [ 'tiles' => [ [ 'file' => 'map/10-1-2.png', 'left' => 0, 'top' => 0, 'size' => 10 ] ] ],
       'frames' => [ [ 't' => 1, 'forecast' => false, 'file' => 'frames/1.png' ], [ 't' => 2, 'forecast' => false, 'tiles' => [ [ 'file' => 'frames/2-7-1-1.png', 'left' => 0, 'top' => 0, 'size' => 1 ] ] ] ],
       'retired' => [] ];
check( 'Manifest schreiben',                NAWS_Radar_Store::save_manifest( $k1, $m ), true );
check( 'Manifest lesen',                    NAWS_Radar_Store::manifest( $k1 ), $m );
check( 'files_of: Bilder ohne Karte',       NAWS_Radar_Store::files_of( $m ), [ 'frames/1.png', 'frames/2-7-1-1.png' ] );
fs()->put_contents( NAWS_Radar_Store::dir( $k1 ) . '/manifest.json', '{kaputt' );
check( 'kaputtes Manifest gilt als keins',  NAWS_Radar_Store::manifest( $k1 ), null );

echo "\nprune(): voriger Satz bleibt 30 Minuten\n" . str_repeat( '-', 74 ) . "\n";
foreach ( [ 'frames/a.png', 'frames/b.png', 'frames/c.png' ] as $f ) { NAWS_Radar_Store::put( $k1, $f, 'x' ); }
$old = [ 'frames' => [ [ 't' => 1, 'file' => 'frames/a.png' ], [ 't' => 2, 'file' => 'frames/b.png' ] ], 'retired' => [] ];
$new = [ 'frames' => [ [ 't' => 2, 'file' => 'frames/b.png' ], [ 't' => 3, 'file' => 'frames/c.png' ] ] ];
$ret = NAWS_Radar_Store::prune( $k1, $old, $new, $now );
check( 'a ist ausgemustert, nicht geloescht', [ $ret, NAWS_Radar_Store::has( $k1, 'frames/a.png' ) ], [ [ 'frames/a.png' => $now ], true ] );
$ret = NAWS_Radar_Store::prune( $k1, $new + [ 'retired' => $ret ], $new, $now + 900 );
check( 'nach 15 min noch da',               NAWS_Radar_Store::has( $k1, 'frames/a.png' ), true );
$ret = NAWS_Radar_Store::prune( $k1, $new + [ 'retired' => $ret ], $new, $now + 1800 );
check( 'nach 30 min geloescht',             [ $ret, NAWS_Radar_Store::has( $k1, 'frames/a.png' ) ], [ [], false ] );
check( 'b und c bleiben',                   [ NAWS_Radar_Store::has( $k1, 'frames/b.png' ), NAWS_Radar_Store::has( $k1, 'frames/c.png' ) ], [ true, true ] );
$ret = NAWS_Radar_Store::prune( $k1, [ 'frames' => [ [ 'file' => 'frames/b.png' ] ], 'retired' => [ 'frames/c.png' => $now ] ], [ 'frames' => [ [ 'file' => 'frames/c.png' ] ] ], $now + 99999 );
check( 'wieder benutztes Bild wird nicht geloescht', [ NAWS_Radar_Store::has( $k1, 'frames/c.png' ), array_key_exists( 'frames/c.png', $ret ) ], [ true, false ] );

echo "\nPfadwaechter\n" . str_repeat( '-', 74 ) . "\n";
fs()->put_contents( '/up/wp-config.php', 'secret' );
fs()->put_contents( '/up/naws-radar/other.txt', 'o' );
check( 'put: ../x.png abgelehnt',           NAWS_Radar_Store::put( $k1, '../x.png', 'x' ), false );
check( 'put: /etc/x.png abgelehnt',         NAWS_Radar_Store::put( $k1, '/etc/x.png', 'x' ), false );
check( 'put: ungueltiger Schluessel',       NAWS_Radar_Store::put( '..', 'frames/a.png', 'x' ), false );
check( 'has: ../x.png',                     NAWS_Radar_Store::has( $k1, '../x.png' ), false );
check( 'has: /etc/x.png',                   NAWS_Radar_Store::has( $k1, '/etc/x.png' ), false );
NAWS_Radar_Store::delete_set( '' );
NAWS_Radar_Store::delete_set( '..' );
check( 'delete_set leer/..: nichts geloescht', [ fs()->exists( '/up/naws-radar/other.txt' ), fs()->exists( '/up/wp-config.php' ), NAWS_Radar_Store::has( $k1, 'frames/b.png' ) ], [ true, true, true ] );
$evil = [ 'frames' => [ [ 'file' => '../../wp-config.php' ] ], 'retired' => [ '../../wp-config.php' => 0, '../wp-config.php' => 0 ] ];
$ret  = NAWS_Radar_Store::prune( $k1, $evil, [ 'frames' => [] ], $now + 99999 );
check( 'feindliches Manifest loescht nichts', [ fs()->exists( '/up/wp-config.php' ), $ret ], [ true, [] ] );

echo "\nSperre und Sperrfrist\n" . str_repeat( '-', 74 ) . "\n";
check( 'erste Sperre klappt',               NAWS_Radar_Store::lock( $k1 ), true );
check( 'zweite nicht',                      NAWS_Radar_Store::lock( $k1 ), false );
NAWS_Radar_Store::unlock( $k1 );
check( 'nach unlock wieder',                NAWS_Radar_Store::lock( $k1 ), true );
NAWS_Radar_Store::unlock( $k1 );
check( 'keine Sperrfrist',                  NAWS_Radar_Store::in_backoff( 'dwd' ), false );
NAWS_Radar_Store::start_backoff( 'dwd', resp( 429, '', [ 'retry-after' => '120' ] ) );
check( '429: Sperrfrist',                   NAWS_Radar_Store::in_backoff( 'dwd' ), true );
check( '429: Retry-After 120 s',            $GLOBALS['transients']['naws_radar_backoff_dwd'][1], 120 );
check( 'andere Quelle frei',                NAWS_Radar_Store::in_backoff( 'rainviewer' ), false );
NAWS_Radar_Store::start_backoff( 'osm', new WP_Error( 'x', 'timeout' ) );
check( 'kein Netz: 5 min',                  $GLOBALS['transients']['naws_radar_backoff_osm'][1], 300 );
check( 'ins Protokoll',                     str_contains( implode( "\n", $GLOBALS['logged'] ), 'dwd request failed (429)' ), true );

echo "\nAufraeumen\n" . str_repeat( '-', 74 ) . "\n";
NAWS_Radar_Store::remember( $k2, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 100, 'source' => 'dwd' ], $now );
NAWS_Radar_Store::put( $k2, 'frames/z.png', 'zz' );
fs()->put_contents( '/up/naws-radar/verwaist/frames/q.png', 'q' );
check( 'Platzbedarf k2',                    NAWS_Radar_Store::usage( $k2 ), 2 );
check( 'nach 6 Tagen nichts geloescht',     NAWS_Radar_Store::cleanup( $now + 6 * DAY_IN_SECONDS ), 1 ); // nur der verwaiste Ordner
check( 'verwaister Ordner weg',             fs()->exists( '/up/naws-radar/verwaist' ), false );
check( 'k2 noch da',                        NAWS_Radar_Store::has( $k2, 'frames/z.png' ), true );
check( 'nach 7 Tagen: k2 weg (k1 war spaeter gesehen)', NAWS_Radar_Store::cleanup( $now + 7 * DAY_IN_SECONDS ), 1 );
check( 'k2 aus der Option',                 isset( NAWS_Radar_Store::sets()[ $k2 ] ), false );
check( 'k2 Dateien weg',                    NAWS_Radar_Store::has( $k2, 'frames/z.png' ), false );
check( 'k1 bleibt',                         isset( NAWS_Radar_Store::sets()[ $k1 ] ), true );
NAWS_Radar_Store::clear_all();
check( 'clear_all: Ordner leer',            fs()->exists( '/up/naws-radar' ), false );
check( 'clear_all: Option weg',             get_option( NAWS_Radar_Store::OPTION, 'weg' ), 'weg' );
check( 'clear_all: Sperrfristen weg',       NAWS_Radar_Store::in_backoff( 'dwd' ), false );

done();
