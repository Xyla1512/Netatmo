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

// Nur 'cooling' schaltet um; alles andere heizt. Das ist die dokumentierte
// Zusage, nicht ein Versehen — hier festgenagelt, damit ein spaeterer
// "Aufraeumer" nicht stillschweigend eine dritte Bedeutung einfuehrt.
close( 'ein unbekannter Richtungsstring rechnet wie heating',
    NAWS_Climate::degree_days( $heiz, 15.0, 20.0, 'Cooling' ),
    NAWS_Climate::degree_days( $heiz, 15.0, 20.0, 'heating' ) );
close( 'auch ein leerer Richtungsstring',
    NAWS_Climate::degree_days( $heiz, 15.0, 20.0, '' ),
    NAWS_Climate::degree_days( $heiz, 15.0, 20.0, 'heating' ) );

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
// Basis bewusst 5.0 statt 10.0: bei Basis 10 wuerde eine falsch auf 0.0
// gecastete Null-Ablesung durch max(0.0, ...) ohnehin auf 0 gekappt und
// waere vom korrekten "uebersprungen" nicht zu unterscheiden. Bei Basis 5
// trennen sich die Ergebnisse: korrekt 0.0 gegen fehlerhaft 10.0 bzw. 5.0.
close( 'null-Minimum wird uebersprungen',
    NAWS_Climate::growing_degree_days( rows( [ '2026-05-01' => [ null, 30.0, 14.0 ] ] ), 5.0, 30.0 ), 0.0 );
close( 'null-Maximum wird uebersprungen',
    NAWS_Climate::growing_degree_days( rows( [ '2026-05-01' => [ 20.0, null, 14.0 ] ] ), 5.0, 30.0 ), 0.0 );

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

// Sanity-Check, keine Regressionswache: grassland_contribution() prueft
// "$avg === null || (float) $avg <= 0.0" in einer einzigen Bedingung, daher
// waere eine falsch auf 0.0 gecastete Null-Ablesung durch den zweiten Teil
// derselben Bedingung ohnehin abgefangen. Die Zeile stellt nur sicher, dass
// ein fehlendes Mittel nicht zum Fehler fuehrt, nicht dass der Null-Check
// selbst greift -- das laesst sich mit keiner Fixture trennen.
close( 'null-Mittel fuehrt nicht zum Fehler und traegt 0 bei',
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

// Sanity-Check, keine Regressionswache: dieselbe "=== null || <= 0.0"
// Bedingung wie bei grassland_sum() macht eine falsch gecastete Null-Ablesung
// strukturell nicht von einer korrekt uebersprungenen unterscheidbar. Zeigt
// nur, dass ein fehlendes Mittel die Schwelle nicht faelschlich ausloest.
check( 'null-Mittel fuehrt nicht zum Fehler und loest die Schwelle nicht aus',
    NAWS_Climate::grassland_start( rows( [ '2026-03-01' => [ 2.0, 14.0, null ] ] ) ), null );

// ══ SPI ═════════════════════════════════════════════════════════════════
//
// Der Index ist die einzige Stelle im Plugin mit Verteilungsmathematik, und
// eine falsche Gammafunktion faellt nirgends auf — sie liefert ja Zahlen.
// Deshalb werden die Bausteine hier gegen geschlossene Formen geprueft, nicht
// gegen frueher gemessene Ausgaben: P(k, x) hat fuer ganzzahliges k eine
// endliche Reihe, P(0.5, x) ist erf(sqrt(x)), lnG faellt auf Fakultaeten
// zurueck, und die Normalquantile stehen in jeder Tabelle.

echo "\n" . str_repeat( '-', 74 ) . "\n";
echo "SPI\n";

/** Ruft eine private Statik von NAWS_Climate. */
function priv( string $method, ...$args ) {
    $m = new ReflectionMethod( 'NAWS_Climate', $method );
    if ( PHP_VERSION_ID < 80100 ) {
        $m->setAccessible( true );
    }
    return $m->invoke( null, ...$args );
}

/** P(k, x) fuer ganzzahliges k: 1 - e^-x * Summe x^i/i!, i < k. */
function gamma_p_exakt( int $k, float $x ): float {
    $sum  = 0.0;
    $term = 1.0;
    for ( $i = 0; $i < $k; $i++ ) {
        $sum  += $term;
        $term *= $x / ( $i + 1 );
    }
    return 1.0 - exp( -$x ) * $sum;
}

foreach ( [ [ 1, 0.5 ], [ 1, 3.0 ], [ 2, 2.0 ], [ 3, 5.0 ], [ 5, 4.0 ], [ 10, 25.0 ] ] as [ $k, $x ] ) {
    close( sprintf( 'unvollstaendige Gammafunktion P(%d, %.1f)', $k, $x ),
        priv( 'gamma_p', (float) $k, $x ), gamma_p_exakt( $k, $x ), 1e-12 );
}

// P(0.5, x) = erf(sqrt(x)); erf(0.5), erf(1) und erf(2) aus der Tabelle.
close( 'P(0.5, 0.25) ist erf(0.5)', priv( 'gamma_p', 0.5, 0.25 ), 0.520499877813047, 1e-12 );
close( 'P(0.5, 1) ist erf(1)',      priv( 'gamma_p', 0.5, 1.0 ),  0.842700792949715, 1e-12 );
close( 'P(0.5, 4) ist erf(2)',      priv( 'gamma_p', 0.5, 4.0 ),  0.995322265018953, 1e-12 );
check( 'P(a, 0) ist 0', priv( 'gamma_p', 2.0, 0.0 ), 0.0 );

close( 'lnGamma(1) = 0',        priv( 'log_gamma', 1.0 ),  0.0,               1e-10 );
close( 'lnGamma(5) = ln 4!',    priv( 'log_gamma', 5.0 ),  log( 24.0 ),       1e-10 );
close( 'lnGamma(10) = ln 9!',   priv( 'log_gamma', 10.0 ), log( 362880.0 ),   1e-10 );
close( 'lnGamma(0.5) = ln sqrt(pi)', priv( 'log_gamma', 0.5 ), log( sqrt( M_PI ) ), 1e-10 );

// Abramowitz & Stegun 26.2.23 verspricht 4.5e-4 — daran wird es gemessen.
close( 'Normalquantil bei 0.5',   priv( 'inverse_normal', 0.5 ),   0.0,          4.5e-4 );
close( 'Normalquantil bei 0.95',  priv( 'inverse_normal', 0.95 ),  1.6448536270, 4.5e-4 );
close( 'Normalquantil bei 0.975', priv( 'inverse_normal', 0.975 ), 1.9599639845, 4.5e-4 );
close( 'Normalquantil bei 0.025', priv( 'inverse_normal', 0.025 ), -1.9599639845, 4.5e-4 );
close( 'Normalquantil bei 0.275', priv( 'inverse_normal', 0.275 ), -0.5977601260, 4.5e-4 );

/** Quantil einer Gamma(2, beta) — geschlossene CDF, per Bisektion invertiert. */
function gamma2_quantil( float $p, float $beta ): float {
    $lo = 0.0;
    $hi = 10000.0;
    for ( $i = 0; $i < 200; $i++ ) {
        $mid = ( $lo + $hi ) / 2;
        $t   = $mid / $beta;
        if ( 1.0 - exp( -$t ) * ( 1.0 + $t ) < $p ) {
            $lo = $mid;
        } else {
            $hi = $mid;
        }
    }
    return ( $lo + $hi ) / 2;
}

/** Monatsreihe ab 2000-01, Werte der Reihe nach. */
function monate( array $werte ): array {
    $out = [];
    $ts  = strtotime( '2000-01-01' );
    foreach ( array_values( $werte ) as $i => $v ) {
        $out[ gmdate( 'Y-m', strtotime( "+{$i} month", $ts ) ) ] = $v;
    }
    return $out;
}

// Die Stichprobe ist der exakte Quantilsatz einer Gamma(2, 20). Legt man
// einen Wert vom bekannten Quantil q als juengsten Monat hinein, muss der
// Index das zugehoerige Normalquantil zurueckgeben. Die Toleranz ist die
// Schaetzunsicherheit von Thoms Verfahren auf 40 Punkten, nicht Rechenfehler.
$stichprobe = [];
for ( $i = 0; $i < 40; $i++ ) {
    $stichprobe[] = gamma2_quantil( ( $i + 0.5 ) / 40, 20.0 );
}
foreach ( [ [ 0.5, 0.0 ], [ 0.1586552539, -1.0 ], [ 0.8413447461, 1.0 ],
            [ 0.0227501319, -2.0 ], [ 0.9772498681, 2.0 ] ] as [ $p, $z ] ) {
    $reihe = $stichprobe;
    $reihe[ count( $reihe ) - 1 ] = gamma2_quantil( $p, 20.0 );
    close( sprintf( 'Wert am %.4f-Quantil ergibt SPI %+.0f', $p, $z ),
        NAWS_Climate::spi( monate( $reihe ), 1 ), $z, 0.15 );
}

// Mehr Regen im juengsten Monat kann den Index nur anheben.
$trocken = $stichprobe;
$trocken[ 39 ] = 5.0;
$nass = $stichprobe;
$nass[ 39 ] = 200.0;
check( 'nasser ist nie kleiner als trockener',
    NAWS_Climate::spi( monate( $nass ), 1 ) > NAWS_Climate::spi( monate( $trocken ), 1 ), true );

// Trockene Fenster tragen die Gammaverteilung nicht — sie werden als eigene
// Wahrscheinlichkeit q herausgehalten. Ist das juengste Fenster selbst
// trocken, ist H genau q, hier 11/40, und der Index dessen Normalquantil.
$mit_nullen = [];
for ( $i = 0; $i < 40; $i++ ) {
    $mit_nullen[] = $i < 10 ? 0.0 : gamma2_quantil( ( $i - 10 + 0.5 ) / 29, 20.0 );
}
$mit_nullen[ 39 ] = 0.0;
close( 'trockenes Fenster landet exakt auf dem Quantil von q = 11/40',
    NAWS_Climate::spi( monate( $mit_nullen ), 1 ), -0.5977601260, 1e-3 );

// Fenster ueber mehrere Monate: die Summe zaehlt, nicht der einzelne Monat.
$dreier = NAWS_Climate::spi( monate( $stichprobe ), 3 );
check( 'ein Dreimonatsfenster liefert eine Zahl', is_float( $dreier ), true );

// ── Wo der Index sich weigert ────────────────────────────────────────────
check( '23 Monate sind zu wenig',
    NAWS_Climate::spi( monate( array_slice( $stichprobe, 0, 23 ) ), 1 ), null );
check( '24 Monate reichen fuer 12 Fenster',
    is_float( NAWS_Climate::spi( monate( array_slice( $stichprobe, 0, 24 ) ), 13 ) ), true );
check( 'bei 11 Fenstern verweigert er',
    NAWS_Climate::spi( monate( array_slice( $stichprobe, 0, 24 ) ), 14 ), null );
check( 'months = 0 ergibt null',
    NAWS_Climate::spi( monate( $stichprobe ), 0 ), null );
check( 'eine konstante Reihe hat keine Verteilung',
    NAWS_Climate::spi( monate( array_fill( 0, 30, 42.0 ) ), 1 ), null );
check( 'eine durchweg trockene Reihe auch nicht',
    NAWS_Climate::spi( monate( array_fill( 0, 30, 0.0 ) ), 1 ), null );

// Ein Loch direkt vor dem juengsten Monat macht dessen Dreimonatsfenster
// unvollstaendig — dann gibt es nichts zu bewerten, auch wenn aeltere
// Fenster vollstaendig waeren.
$mit_loch = monate( $stichprobe );
unset( $mit_loch['2003-03'] );          // der vorletzte Monat der Reihe
check( 'Loch im juengsten Dreimonatsfenster ergibt null',
    NAWS_Climate::spi( $mit_loch, 3 ), null );
check( 'dasselbe Loch stoert das Einmonatsfenster nicht',
    is_float( NAWS_Climate::spi( $mit_loch, 1 ) ), true );

// ── monthly_sums(): nur lueckenlose Monate zaehlen ───────────────────────
/** Tageszeilen fuer einen ganzen Monat mit festem Wert. */
function regen_monat( string $month, int $days, $wert ): array {
    $out = [];
    for ( $d = 1; $d <= $days; $d++ ) {
        $out[] = [ 'day_date' => sprintf( '%s-%02d', $month, $d ), 'rain_sum' => $wert ];
    }
    return $out;
}

$voll = regen_monat( '2025-04', 30, 1.5 );
check( 'ein lueckenloser April zaehlt',
    NAWS_Climate::monthly_sums( $voll, 'rain_sum' ), [ '2025-04' => 45.0 ] );
check( 'ein April mit 29 Tagen zaehlt nicht',
    NAWS_Climate::monthly_sums( array_slice( $voll, 0, 29 ), 'rain_sum' ), [] );
check( 'der Februar eines Schaltjahres braucht 29 Tage',
    NAWS_Climate::monthly_sums( regen_monat( '2024-02', 29, 2.0 ), 'rain_sum' ), [ '2024-02' => 58.0 ] );
check( 'im Schaltjahr reichen 28 Tage nicht',
    NAWS_Climate::monthly_sums( regen_monat( '2024-02', 28, 2.0 ), 'rain_sum' ), [] );
check( 'im Nicht-Schaltjahr sind 28 Tage vollstaendig',
    NAWS_Climate::monthly_sums( regen_monat( '2025-02', 28, 2.0 ), 'rain_sum' ), [ '2025-02' => 56.0 ] );

// Ein Tag ohne Regenwert ist kein trockener Tag, sondern ein fehlender —
// der Monat faellt heraus, statt zu klein in die Verteilung einzugehen.
$mit_luecke      = regen_monat( '2025-04', 30, 1.5 );
$mit_luecke[10]['rain_sum'] = null;
check( 'ein einziger fehlender Regenwert kippt den Monat',
    NAWS_Climate::monthly_sums( $mit_luecke, 'rain_sum' ), [] );

check( 'trockene Tage sind keine fehlenden',
    NAWS_Climate::monthly_sums( regen_monat( '2025-04', 30, 0.0 ), 'rain_sum' ), [ '2025-04' => 0.0 ] );

echo "\nNAWS_Climate::longest_run()\n" . str_repeat( '-', 74 ) . "\n";

// Frosttage: 3.–5. Januar (drei), Lücke am 8., dann 9.–13. (fuenf).
$run_rows = [];
foreach ( [ '01-01' => 2, '01-02' => 1, '01-03' => -1, '01-04' => -2, '01-05' => -1, '01-06' => 1, '01-07' => 3,
            '01-09' => -1, '01-10' => -3, '01-11' => -2, '01-12' => -1, '01-13' => -1, '01-14' => 2 ] as $md => $tmin ) {
    $run_rows[] = [ 'day_date' => "2025-$md", 'temp_min' => $tmin ];
}
$frost = static fn( $r ) => $r['temp_min'] < 0;

$run = NAWS_Climate::longest_run( $run_rows, $frost );
check( 'laengste Serie: fuenf Tage',          $run['length'] ?? null, 5 );
check( 'sie beginnt am 9. Januar',            $run['from'] ?? null, '2025-01-09' );
check( 'und endet am 13.',                    $run['to'] ?? null, '2025-01-13' );
check( 'max_streak() sagt dieselbe Zahl',     NAWS_Climate::max_streak( $run_rows, $frost ), 5 );

// Gleichstand: zwei Serien zu je drei Tagen — die fruehere gewinnt.
$tie_rows = [];
foreach ( [ '02-01' => -1, '02-02' => -1, '02-03' => -1, '02-04' => 2, '02-05' => -1, '02-06' => -1, '02-07' => -1 ] as $md => $tmin ) {
    $tie_rows[] = [ 'day_date' => "2025-$md", 'temp_min' => $tmin ];
}
$tie = NAWS_Climate::longest_run( $tie_rows, $frost );
check( 'Gleichstand: die fruehere Serie',     $tie['from'] ?? null, '2025-02-01' );

// Eine Datenluecke bricht die Serie: 1., 2., (3. fehlt), 4., 5. → zwei Serien zu zwei.
$gap_rows = [];
foreach ( [ '03-01', '03-02', '03-04', '03-05' ] as $md ) {
    $gap_rows[] = [ 'day_date' => "2025-$md", 'temp_min' => -1 ];
}
$gap = NAWS_Climate::longest_run( $gap_rows, $frost );
check( 'Luecke bricht die Serie',             $gap['length'] ?? null, 2 );
check( 'Luecke: die erste Serie gewinnt',     $gap['from'] ?? null, '2025-03-01' );

check( 'ohne Treffer null',                   NAWS_Climate::longest_run( [ [ 'day_date' => '2025-04-01', 'temp_min' => 5 ] ], $frost ), null );
check( 'ohne Treffer bleibt max_streak 0',    NAWS_Climate::max_streak( [ [ 'day_date' => '2025-04-01', 'temp_min' => 5 ] ], $frost ), 0 );
check( 'ohne Zeilen null',                    NAWS_Climate::longest_run( [], $frost ), null );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
