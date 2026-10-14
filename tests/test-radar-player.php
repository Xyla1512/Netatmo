<?php
/**
 * Tests fuer die Logik des Radar-Players (assets/js/radar.js) in Node,
 * dazu: das Skript nennt keinen fremden Host.
 *
 *   php tests/test-radar-player.php
 *
 * @package NAWS
 */
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$file = dirname( __DIR__ ) . '/assets/js/radar.js';
$js   = <<<'JS'
const c = require(process.argv[2]); // argv[1] is "-" (script on stdin)
const f = (fc) => fc.map((x) => ({ forecast: x }));
const p = f([false, false, false, false, false, true, true, true, true, true, true, true, true]);
console.log(JSON.stringify({
  now: c.nowIndex(p), nowAllPast: c.nowIndex(f([false, false, false])), nowEmpty: c.nowIndex([]),
  next: c.stepOn(3, 13, {}), end: c.stepOn(12, 13, {}), skip: c.stepOn(3, 13, { 4: true, 5: true }),
  brokenToEnd: c.stepOn(10, 13, { 11: true, 12: true }), first: c.stepOn(-1, 13, {}),
  firstBroken: c.stepOn(-1, 13, { 0: true }), zero: c.stepOn(-1, 0, {}),
  noAuto: typeof c.autoplay, noLoop: typeof c.nextOk,
  goneSoon: c.goneWait(1), goneLate: c.goneWait(10),
  pct: c.pct(512), pctNeg: c.pct(-19.59),
  keepShift: c.keepIndex([{ t: 1 }, { t: 2 }, { t: 3 }], 1, [{ t: 0 }, { t: 1 }, { t: 2 }, { t: 3 }]),
  keepGone: c.keepIndex([{ t: 1 }, { t: 2 }], 1, [{ t: 5, forecast: false }, { t: 6, forecast: false }, { t: 7, forecast: true }]),
  keepEmpty: c.keepIndex([], 0, [{ t: 5, forecast: false }, { t: 6, forecast: true }]),
  pollFresh: c.needsPoll(1000, 1900), pollDue: c.needsPoll(1000, 1901),
  staleNo: c.isStale(1000, 11800), staleYes: c.isStale(1000, 11801),
  asofNo: c.showAsOf(1000, 2800), asofYes: c.showAsOf(1000, 2801), asofNone: c.showAsOf(0, 99999),
  markMid: c.markPct(4, 13), markEnd: c.markPct(12, 13), markOne: c.markPct(0, 1)
}));
JS;
$proc = proc_open( [ 'node', '-', $file ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
fwrite( $pipes[0], $js );
fclose( $pipes[0] );
$out = stream_get_contents( $pipes[1] );
$err = stream_get_contents( $pipes[2] );
proc_close( $proc );
$r = json_decode( (string) $out, true );
if ( ! is_array( $r ) ) { echo "Node-Fehler: $err\n"; exit( 1 ); }

echo "\nradar.js core\n" . str_repeat( '-', 74 ) . "\n";
check( '"jetzt" ist das letzte gemessene Bild', $r['now'], 4 );
check( 'nur gemessene: das letzte',      $r['nowAllPast'], 2 );
check( 'leer: -1',                       $r['nowEmpty'], -1 );
check( 'naechstes Bild',                 $r['next'], 4 );
check( 'nach dem letzten: Ende (-1)',    $r['end'], -1 );
check( 'kaputte Bilder uebersprungen',   $r['skip'], 6 );
check( 'nur kaputte bis zum Ende: Ende', $r['brokenToEnd'], -1 );
check( 'Start: erstes Bild',             $r['first'], 0 );
check( 'Start: erstes heiles Bild',      $r['firstBroken'], 1 );
check( 'keine Bilder: Ende',             $r['zero'], -1 );
check( 'kein Autoplay mehr',             $r['noAuto'], 'undefined' );
check( 'keine Endlosschleife mehr',      $r['noLoop'], 'undefined' );
// Zu alt beim Aufruf: die Erneuerung laeuft gerade, also bald wieder fragen.
check( 'zu alt: nach 20 s erneut fragen', $r['goneSoon'], 20000 );
check( 'zu alt, 10 Versuche: 15 min',     $r['goneLate'], 900000 );
check( 'Prozent',                        $r['pct'], '50.0000%' );
check( 'Prozent negativ',                $r['pctNeg'], '-1.9131%' );
check( 'gleicher Zeitpunkt nach Verschiebung', $r['keepShift'], 2 );
check( 'Zeitpunkt weg: jetzt der neuen Liste', $r['keepGone'], 1 );
check( 'vorher leer: jetzt',                   $r['keepEmpty'], 0 );
check( 'needsPoll: 15 min alt noch nicht',     $r['pollFresh'], false );
check( 'needsPoll: danach sofort fragen',      $r['pollDue'], true );
check( 'isStale: 3 h alt noch nicht',          $r['staleNo'], false );
check( 'isStale: danach nicht verfuegbar',     $r['staleYes'], true );
check( 'showAsOf: 30 min alt noch nicht',      $r['asofNo'], false );
check( 'showAsOf: danach "Stand"',             $r['asofYes'], true );
check( 'showAsOf: ohne Stand nie',             $r['asofNone'], false );
check( 'Jetzt-Marke: Bild 5 von 13',           $r['markMid'], '33.3333%' );
check( 'Jetzt-Marke: letztes Bild',            $r['markEnd'], '100.0000%' );
check( 'Jetzt-Marke: ein Bild',                $r['markOne'], '0.0000%' );

echo "\nDatenschutz\n" . str_repeat( '-', 74 ) . "\n";
$src = (string) file_get_contents( $file );
check( 'keine Adresse mit http im Skript', preg_match( '#https?://#', $src ), 0 );
check( 'kein fremder Dienst genannt',      preg_match( '/rainviewer|dwd\.de|openstreetmap/i', $src ), 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
