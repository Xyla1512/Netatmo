<?php
/**
 * Tests for the climate arithmetic in NAWS_Climate.
 *
 * Every function under test is pure — it receives finished daily rows and
 * returns a number. No options, no database, no clock, so this runs without
 * a WordPress bootstrap.
 *
 * The rule these tests exist to pin down: a MISSING DAY BREAKS A STREAK.
 * Three frost days, a gap, two more frost days is 3 and 2 — never 5. The
 * cautious reading, because nothing is known about a day nobody measured.
 *
 *   php tests/test-climate-indices.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-climate.php';

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

function close( string $name, float $got, float $want, float $tol = 0.001 ): void {
    global $passed, $failed;
    if ( abs( $got - $want ) <= $tol ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %.4f (+-%.4f), ist %.4f\n", $name, $want, $tol, $got );
}

/** Baut Tageszeilen aus [ 'Y-m-d' => [ min, max, avg ] ]. */
function rows( array $spec ): array {
    $out = [];
    foreach ( $spec as $date => $v ) {
        $out[] = [
            'day_date' => $date,
            'temp_min' => $v[0],
            'temp_max' => $v[1],
            'temp_avg' => $v[2],
        ];
    }
    return $out;
}

$frost = function ( array $r ): bool {
    return $r['temp_min'] !== null && $r['temp_min'] < 0.0;
};

echo "\nNAWS_Climate::count_days()\n" . str_repeat( '-', 74 ) . "\n";

$w = rows( [
    '2026-01-01' => [ -3.0, 2.0, -0.5 ],
    '2026-01-02' => [ -1.0, 4.0,  1.5 ],
    '2026-01-03' => [  1.0, 6.0,  3.5 ],
    '2026-01-04' => [ -2.0, 1.0, -0.5 ],
] );
check( 'drei Frosttage von vier', NAWS_Climate::count_days( $w, $frost ), 3 );
check( 'leere Liste ergibt 0',    NAWS_Climate::count_days( [], $frost ), 0 );
check( 'kein Treffer ergibt 0',   NAWS_Climate::count_days( rows( [ '2026-07-01' => [ 12.0, 25.0, 18.0 ] ] ), $frost ), 0 );

// Ein null-Feld ist kein Treffer, aber auch kein Fehler.
check( 'null-Minimum zaehlt nicht', NAWS_Climate::count_days( rows( [ '2026-01-01' => [ null, 3.0, 1.0 ] ] ), $frost ), 0 );

echo "\nNAWS_Climate::max_streak() — die Lueckenregel\n" . str_repeat( '-', 74 ) . "\n";

// Drei Frosttage, fehlender 4. Januar, zwei Frosttage: 3 und 2, nicht 5.
$luecke = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [ -4.0, -1.0, -2.5 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-05' => [ -5.0, -2.0, -3.5 ],
    '2026-01-06' => [ -1.0, -0.2, -0.6 ],
] );
check( 'Luecke bricht die Serie', NAWS_Climate::max_streak( $luecke, $frost ), 3 );

$durchgehend = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [ -4.0, -1.0, -2.5 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-04' => [ -5.0, -2.0, -3.5 ],
    '2026-01-05' => [ -1.0, -0.2, -0.6 ],
] );
check( 'ohne Luecke fuenf am Stueck', NAWS_Climate::max_streak( $durchgehend, $frost ), 5 );

// Ueber den Jahreswechsel muss die Serie halten.
$jahreswechsel = rows( [
    '2025-12-30' => [ -2.0, -0.5, -1.0 ],
    '2025-12-31' => [ -3.0, -1.0, -2.0 ],
    '2026-01-01' => [ -4.0, -1.5, -2.5 ],
    '2026-01-02' => [ -1.0, -0.2, -0.5 ],
] );
check( 'Serie ueber den Jahreswechsel', NAWS_Climate::max_streak( $jahreswechsel, $frost ), 4 );

// Ein nicht passender Tag mittendrin bricht ebenfalls.
$unterbrochen = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [  1.0,  5.0,  3.0 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-04' => [ -2.0, -0.5, -1.2 ],
] );
check( 'Tauwetter bricht die Serie', NAWS_Climate::max_streak( $unterbrochen, $frost ), 2 );

check( 'ein einzelner Tag',   NAWS_Climate::max_streak( rows( [ '2026-01-01' => [ -3.0, -1.0, -2.0 ] ] ), $frost ), 1 );
check( 'leere Liste ergibt 0', NAWS_Climate::max_streak( [], $frost ), 0 );

// Endet die Liste mit einem nicht passenden Tag, bleibt die beste
// fruehere Serie erhalten -- der letzte Tag darf sie nicht loeschen.
$endet_nicht_passend = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [ -4.0, -1.0, -2.5 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-04' => [  5.0,  9.0,  7.0 ],
] );
check( 'endet nicht passend, bester Wert bleibt', NAWS_Climate::max_streak( $endet_nicht_passend, $frost ), 3 );

echo "\nNAWS_Climate::current_streak()\n" . str_repeat( '-', 74 ) . "\n";

// Zaehlt vom Ende der Liste rueckwaerts.
check( 'laufende Serie am Ende',  NAWS_Climate::current_streak( $luecke, $frost ), 2 );
check( 'zwei Frosttage am Ende',  NAWS_Climate::current_streak( $unterbrochen, $frost ), 2 );
check( 'Tauwetter davor bricht ab', NAWS_Climate::current_streak( $w, $frost ), 1 );
check( 'leere Liste ergibt 0',    NAWS_Climate::current_streak( [], $frost ), 0 );

// Passt der letzte Tag nicht, ist die laufende Serie 0 -- unabhaengig
// davon, was davor lag.
$letzter_bricht = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [ -2.0, -0.5, -1.2 ],
    '2026-01-03' => [  5.0,  9.0,  7.0 ],
] );
check( 'letzter Tag passt nicht -> 0', NAWS_Climate::current_streak( $letzter_bricht, $frost ), 0 );

echo "\nNAWS_Climate::degree_days()\n" . str_repeat( '-', 74 ) . "\n";

$heiz = rows( [
    '2026-01-01' => [ -3.0, 2.0, 10.0 ],   // Heiztag: 20 - 10 = 10
    '2026-01-02' => [ -1.0, 8.0, 14.0 ],   // Heiztag: 20 - 14 =  6
    '2026-01-03' => [  8.0, 22.0, 16.0 ],  // kein Heiztag (16 >= 15)
] );
close( 'Heizgradtage 15/20',  NAWS_Climate::degree_days( $heiz, 15.0, 20.0, 'heating' ), 16.0 );
close( 'Heizgradtage 12/20',  NAWS_Climate::degree_days( $heiz, 12.0, 20.0, 'heating' ), 10.0 );

$kuehl = rows( [
    '2026-07-01' => [ 18.0, 30.0, 24.0 ],  // Kuehltag: 24 - 18 = 6
    '2026-07-02' => [ 14.0, 22.0, 17.0 ],  // kein Kuehltag (17 <= 18)
    '2026-07-03' => [ 20.0, 34.0, 27.0 ],  // Kuehltag: 27 - 18 = 9
] );
close( 'Kuehlgradtage Grenze 18', NAWS_Climate::degree_days( $kuehl, 18.0, 18.0, 'cooling' ), 15.0 );

close( 'leere Liste ergibt 0.0', NAWS_Climate::degree_days( [], 15.0, 20.0, 'heating' ), 0.0 );
close( 'null-Mittel wird uebersprungen',
    NAWS_Climate::degree_days( rows( [ '2026-01-01' => [ null, null, null ] ] ), 15.0, 20.0, 'heating' ), 0.0 );

echo "\nNAWS_Climate::growing_degree_days()\n" . str_repeat( '-', 74 ) . "\n";

// (min(Tmax,cap) + Tmin)/2 - Basis, negative Beitraege auf 0.
$wachstum = rows( [
    '2026-05-01' => [ 8.0,  20.0, 14.0 ],  // (20+8)/2 - 10 = 4
    '2026-05-02' => [ 7.0,  13.0, 10.0 ],  // (13+7)/2 - 10 = 0
    '2026-05-03' => [ 4.0,  12.0,  8.0 ],  // (12+4)/2 - 10 = -2 -> 0
    '2026-05-04' => [ 18.0, 36.0, 27.0 ],  // Kappung: (30+18)/2 - 10 = 14
] );
close( 'Basis 10, Kappung 30', NAWS_Climate::growing_degree_days( $wachstum, 10.0, 30.0 ), 18.0 );
close( 'ohne Kappung waere es mehr', NAWS_Climate::growing_degree_days( $wachstum, 10.0, 99.0 ), 21.0 );
close( 'Basis 5 statt 10',     NAWS_Climate::growing_degree_days( $wachstum, 5.0, 30.0 ), 36.0 );
close( 'leere Liste ergibt 0.0', NAWS_Climate::growing_degree_days( [], 10.0, 30.0 ), 0.0 );

// Fehlt Tmin oder Tmax, wird der Tag uebersprungen, nicht mit 0 gerechnet.
close( 'null-Minimum wird uebersprungen',
    NAWS_Climate::growing_degree_days( rows( [ '2026-05-01' => [ null, 20.0, 14.0 ] ] ), 10.0, 30.0 ), 0.0 );
close( 'null-Maximum wird uebersprungen',
    NAWS_Climate::growing_degree_days( rows( [ '2026-05-01' => [ 5.0, null, 14.0 ] ] ), 10.0, 30.0 ), 0.0 );

echo "\nNAWS_Climate::grassland_sum() — Monatsgewichte\n" . str_repeat( '-', 74 ) . "\n";

// Januar x0,5 · Februar x0,75 · ab Maerz x1,0 · nur Mittel ueber 0.
$gruen = rows( [
    '2026-01-10' => [ 0.0, 8.0, 4.0 ],    // 4 * 0,5  = 2,0
    '2026-01-11' => [ -5.0, -1.0, -3.0 ], // <= 0 -> faellt weg
    '2026-02-10' => [ 2.0, 10.0, 8.0 ],   // 8 * 0,75 = 6,0
    '2026-03-10' => [ 4.0, 14.0, 9.0 ],   // 9 * 1,0  = 9,0
    '2026-04-10' => [ 6.0, 18.0, 12.0 ],  // 12 * 1,0 = 12,0
] );
close( 'gewichtete Summe', NAWS_Climate::grassland_sum( $gruen ), 29.0 );
close( 'genau 0 Grad zaehlt nicht',
    NAWS_Climate::grassland_sum( rows( [ '2026-03-01' => [ -2.0, 2.0, 0.0 ] ] ) ), 0.0 );
close( 'leere Liste ergibt 0.0', NAWS_Climate::grassland_sum( [] ), 0.0 );

// Schaltjahr: der 29. Februar traegt das Februar-Gewicht.
close( 'Schaltjahr 29.02. mit 0,75',
    NAWS_Climate::grassland_sum( rows( [ '2024-02-29' => [ 0.0, 8.0, 4.0 ] ] ) ), 3.0 );

// Fehlt temp_avg, traegt der Tag 0 bei -- kein Fehler, kein Fallback auf 0 Grad.
close( 'null-Mittel traegt 0 bei',
    NAWS_Climate::grassland_sum( rows( [ '2026-03-01' => [ 2.0, 14.0, null ] ] ) ), 0.0 );

echo "\nNAWS_Climate::grassland_start()\n" . str_repeat( '-', 74 ) . "\n";

// Erst wenn die laufende Summe 200 ueberschreitet.
$lang = [];
foreach ( range( 1, 30 ) as $tag ) {
    $lang[ sprintf( '2026-03-%02d', $tag ) ] = [ 2.0, 14.0, 9.0 ]; // 9 pro Tag
}
// 9 * 22 = 198, 9 * 23 = 207 -> Ueberschreitung am 23. Maerz
check( 'Datum der Ueberschreitung', NAWS_Climate::grassland_start( rows( $lang ) ), '2026-03-23' );
check( 'unter 200 ergibt null',
    NAWS_Climate::grassland_start( rows( [ '2026-03-01' => [ 2.0, 14.0, 9.0 ] ] ) ), null );
check( 'leere Liste ergibt null', NAWS_Climate::grassland_start( [] ), null );

// Fehlt temp_avg, traegt der Tag 0 bei und loest die Schwelle nicht aus.
check( 'null-Mittel loest die Schwelle nicht aus',
    NAWS_Climate::grassland_start( rows( [ '2026-03-01' => [ 2.0, 14.0, null ] ] ) ), null );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
