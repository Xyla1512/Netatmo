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
  next: c.nextOk(3, 13, {}), wrap: c.nextOk(12, 13, {}), skip: c.nextOk(3, 13, { 4: true, 5: true }),
  allBroken: c.nextOk(0, 2, { 0: true, 1: true }), zero: c.nextOk(0, 0, {}),
  hold: c.delay(12, 13), step: c.delay(3, 13),
  auto: c.autoplay(p, false), reduced: c.autoplay(p, true), single: c.autoplay(f([false]), false),
  pct: c.pct(512), pctNeg: c.pct(-19.59)
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
check( 'nach dem letzten wieder vorn',   $r['wrap'], 0 );
check( 'kaputte Bilder uebersprungen',   $r['skip'], 6 );
check( 'alle kaputt: bleibt stehen',     $r['allBroken'], 0 );
check( 'keine Bilder: 0',                $r['zero'], 0 );
check( 'am Ende 1,5 s Halt',             $r['hold'], 1500 );
check( 'sonst 600 ms',                   $r['step'], 600 );
check( 'spielt von selbst',              $r['auto'], true );
check( 'nicht bei reduzierter Bewegung', $r['reduced'], false );
check( 'nicht bei nur einem Bild',       $r['single'], false );
check( 'Prozent',                        $r['pct'], '50.0000%' );
check( 'Prozent negativ',                $r['pctNeg'], '-1.9131%' );

echo "\nDatenschutz\n" . str_repeat( '-', 74 ) . "\n";
$src = (string) file_get_contents( $file );
check( 'keine Adresse mit http im Skript', preg_match( '#https?://#', $src ), 0 );
check( 'kein fremder Dienst genannt',      preg_match( '/rainviewer|dwd\.de|openstreetmap/i', $src ), 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
