<?php
/**
 * Prueft, wie aus Tageszeilen ein Jahresraster wird.
 *
 * Zwei Dinge muessen hier stimmen, weil sie sich auf der fertigen Karte
 * nicht unterscheiden lassen: die Laenge der Monate — ein 31. April darf
 * keine graue Kachel werden, sondern gar keine — und der Rueckgriff auf
 * (min + max) / 2. Der darf greifen, wenn beides da ist, und er darf
 * nicht greifen, wenn nur eines da ist: aus einem einzelnen Maximum ein
 * "Mittel" zu machen waere schlechter als die Luecke.
 *
 *   php tests/test-heatmap-year.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once dirname( __DIR__ ) . '/includes/class-naws-database.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

function row( string $date, $avg = null, $min = null, $max = null ): array {
    return [ 'day_date' => $date, 'temp_avg' => $avg, 'temp_min' => $min, 'temp_max' => $max ];
}

echo "\nDie Form des Rasters\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [], 2025 );

check( 'zwoelf Monatszeilen fuer die Werte',   count( $r['values'] ), 12 );
check( 'zwoelf ebenso fuer die Herkunft',      count( $r['sources'] ), 12 );
check( 'der Januar hat 31 Eintraege',          count( $r['values'][0] ), 31 );
check( 'der April hat 30, nicht 31',           count( $r['values'][3] ), 30 );
check( 'der Februar 2025 hat 28',              count( $r['values'][1] ), 28 );
check( 'ohne Zeilen ist alles null',           array_unique( $r['values'][0] ), [ null ] );
check( 'und keine Herkunft gesetzt',           array_unique( $r['sources'][0] ), [ null ] );

$leap = NAWS_Database::shape_heatmap_year( [], 2024 );
check( 'der Februar 2024 hat 29', count( $leap['values'][1] ), 29 );

echo "\nWerte landen an der richtigen Stelle\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [
    row( '2025-01-01', '4.2' ),
    row( '2025-03-15', '11.8' ),
    row( '2025-12-31', '-2.5' ),
], 2025 );

check( 'der erste Januar steht auf Index 0/0',   $r['values'][0][0],  4.2 );
check( 'der 15. Maerz auf 2/14',                 $r['values'][2][14], 11.8 );
check( 'der 31. Dezember auf 11/30',             $r['values'][11][30], -2.5 );
check( 'ihre Herkunft ist der gespeicherte Wert', $r['sources'][0][0], 'avg' );
check( 'ein Tag ohne Zeile bleibt null',          $r['values'][0][1],  null );

echo "\nDer Rueckgriff auf Min und Max\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [
    row( '2025-02-01', null, '2.0', '10.0' ),
    row( '2025-02-02', null, '2.0', null ),
    row( '2025-02-03', null, null,  '10.0' ),
    row( '2025-02-04', '7.0', '0.0', '20.0' ),
], 2025 );

check( 'Min und Max ergeben ihr Mittel',        $r['values'][1][0],  6.0 );
check( 'und werden als solches ausgewiesen',    $r['sources'][1][0], 'minmax' );
check( 'nur Min reicht nicht',                  $r['values'][1][1],  null );
check( 'nur Max ebenso wenig',                  $r['values'][1][2],  null );
check( 'ein gespeicherter Durchschnitt gewinnt', $r['values'][1][3],  7.0 );
check( 'auch in der Herkunft',                   $r['sources'][1][3], 'avg' );

echo "\nZeilen, die nicht hierher gehoeren\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [
    row( '2024-06-01', '18.0' ),
    row( '2025-06-01', '19.0' ),
    row( 'kaputt',     '20.0' ),
    row( '2025-13-01', '21.0' ),
], 2025 );

check( 'ein anderes Jahr wird uebergangen',  $r['values'][5][0], 19.0 );
check( 'ein unlesbares Datum stuerzt nicht', is_array( $r['values'] ), true );

// Der Fall aus der Aufgabenbeschreibung: ein 31. April existiert nicht
// und darf deshalb keine (graue) Zelle bekommen, sondern gar keine.
$r = NAWS_Database::shape_heatmap_year( [
    row( '2025-04-31', '99.0' ),
], 2025 );

check( 'der 31. April wird verworfen',      $r['values'][3][29], null );
check( 'der April bleibt bei 30 Eintraegen', count( $r['values'][3] ), 30 );

echo "\nZwei Zeilen fuer denselben Tag\n" . str_repeat( '-', 74 ) . "\n";

// Die Tagestabelle fuehrt je Modul eine Zeile. Die Innenmodule tragen in
// temp_* nichts, aber verlassen darf man sich darauf nicht: der
// gespeicherte Durchschnitt muss gewinnen, in welcher Reihenfolge die
// Zeilen auch kommen.
$a = NAWS_Database::shape_heatmap_year( [
    row( '2025-05-01', null, '1.0', '3.0' ),
    row( '2025-05-01', '9.9' ),
], 2025 );
$b = NAWS_Database::shape_heatmap_year( [
    row( '2025-05-01', '9.9' ),
    row( '2025-05-01', null, '1.0', '3.0' ),
], 2025 );

check( 'der Durchschnitt gewinnt, egal wer zuerst kommt', [ $a['values'][4][0], $b['values'][4][0] ], [ 9.9, 9.9 ] );
check( 'und die Herkunft sagt das auch',                  [ $a['sources'][4][0], $b['sources'][4][0] ], [ 'avg', 'avg' ] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
