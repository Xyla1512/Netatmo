<?php
/**
 * Tests fuer NAWS_Records — die Rekorde aus der Tagesuebersicht.
 *
 * Die Rechnung ist rein: Tageszeilen rein, Zahlen raus. Deshalb laeuft sie
 * hier auf einem handgebauten Jahr, in dem jeder Rekord an einem bekannten
 * Tag steht. Was nicht rein ist (die Zeilen holen, Einheiten lesen), ist
 * absichtlich duenn und wird auf dev geprueft, nicht hier.
 *
 *   php tests/test-records.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function close( string $name, $got, float $want, float $tol = 0.001 ): void {
    global $passed, $failed;
    if ( is_float( $got ) && abs( $got - $want ) <= $tol ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nDie Helfer, die die Rekorde mitbenutzen\n" . str_repeat( '-', 74 ) . "\n";

check( 'station_row_id() ist oeffentlich', ( new ReflectionMethod( 'NAWS_Calc', 'station_row_id' ) )->isPublic(), true );
check( 'period_range() ist oeffentlich',   ( new ReflectionMethod( 'NAWS_Calc', 'period_range' ) )->isPublic(), true );

// get_daily_summaries() braucht $wpdb und laeuft hier nicht; die Freigabe
// von gust_max steht in einer Liste, die sich lesen laesst.
$db_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'get_daily_summaries() gibt gust_max heraus', (bool) preg_match( "/\\\$allowed_fields\\s*=\\s*\\[[^\\]]*'gust_max'/", $db_src ), true );

echo "\nNAWS_Records::catalogue()\n" . str_repeat( '-', 74 ) . "\n";

$cat = NAWS_Records::catalogue();
check( 'fuenfzehn Rekorde', count( $cat ), 15 );
check( 'Reihenfolge wie in der Spec', array_keys( $cat ), [
    'hottest_day', 'coldest_night', 'warmest_night', 'coldest_day', 'widest_range',
    'warmest_month', 'coldest_month', 'wettest_day', 'wettest_month',
    'longest_dry_spell', 'longest_wet_spell', 'strongest_gust',
    'longest_frost', 'longest_heat_wave', 'longest_summer_run',
] );
foreach ( $cat as $key => $entry ) {
    check( "$key hat eine Art",            in_array( $entry['kind'] ?? '', [ 'extreme', 'month', 'streak' ], true ), true );
    check( "$key hat einen Sprachkey",     $entry['label'] ?? null, 'rec_' . $key );
    check( "$key hat Nachkommastellen",    is_int( $entry['decimals'] ?? null ), true );
    check( "$key: param ist String/null",  array_key_exists( 'param', $entry ) && ( $entry['param'] === null || is_string( $entry['param'] ) ), true );
}

echo "\nNAWS_Records::compute() auf einem gebauten Jahr\n" . str_repeat( '-', 74 ) . "\n";

/**
 * Ein volles Jahr 2025, Tag fuer Tag, mit bekannten Extremen:
 *  - 1. Juli:    temp_max 39.1 (heissester Tag), temp_min 24.0 (waermste Nacht)
 *  - 10. Jan.:   temp_min -8.5 (kaelteste Nacht), temp_max -3.0 (kaeltester Tag)
 *  - 15. Aug.:   temp_max 35.0, temp_min 10.0 → Spanne 25.0 (groesste Spanne)
 *  - 3. Juni:    rain_sum 26.4 (nassester Tag), gust_max 46.0 (staerkste Boe)
 *  - Januar 8.–14.: temp_min < 0 an sieben Tagen (laengste Frostperiode)
 *  - Juli 1.–5.: temp_max >= 30 an fuenf Tagen (Hitzewelle), 1.–9. >= 25 (Sommerserie neun)
 *  - Regen (0.5) am 1. und 15. jedes Monats ausser August/September, dazu am
 *    31. Juli und 21. September: die Trockenperiode 1. Aug.–20. Sep. ist 51 Tage,
 *    jede andere hoechstens 16
 *  - November: 18 mm am 1., 8., 15., 22., 29. = 90 mm (nassester Monat), ohne Serie
 *  - Regenserie 10.–16. Oktober = sieben Tage
 * Alle anderen Tage: mild, trocken, ohne Boe.
 */
function naws_test_year(): array {
    $rows = [];
    for ( $d = new DateTime( '2025-01-01' ); $d->format( 'Y' ) === '2025'; $d->modify( '+1 day' ) ) {
        $md  = $d->format( 'm-d' );
        $m   = (int) $d->format( 'n' );
        $row = [
            'day_date' => $d->format( 'Y-m-d' ),
            'temp_min' => 8.0,
            'temp_max' => 18.0,
            'temp_avg' => 13.0,
            'rain_sum' => 0.0,
            'gust_max' => 20.0,
        ];
        if ( $m === 1 )  { $row['temp_avg'] = 1.0; }   // kaeltester Monat
        if ( $m === 7 )  { $row['temp_avg'] = 22.0; }  // waermster Monat
        $dom = (int) $d->format( 'j' );
        if ( ( $dom === 1 || $dom === 15 ) && $m !== 8 && $m !== 9 ) { $row['rain_sum'] = 0.5; } // begrenzt jede Trockenperiode auf ~16 Tage
        if ( $m === 11 && in_array( $dom, [ 1, 8, 15, 22, 29 ], true ) ) { $row['rain_sum'] = 18.0; } // nassester Monat: 90 mm, ohne Regenserie
        if ( $md >= '01-08' && $md <= '01-14' ) { $row['temp_min'] = -2.0; }
        if ( $md === '01-10' ) { $row['temp_min'] = -8.5; $row['temp_max'] = -3.0; }
        if ( $md >= '07-01' && $md <= '07-09' ) { $row['temp_max'] = 27.0; }
        if ( $md >= '07-01' && $md <= '07-05' ) { $row['temp_max'] = 31.0; }
        if ( $md === '07-01' ) { $row['temp_max'] = 39.1; $row['temp_min'] = 24.0; }
        if ( $md === '08-15' ) { $row['temp_max'] = 35.0; $row['temp_min'] = 10.0; }
        if ( $md === '06-03' ) { $row['rain_sum'] = 26.4; $row['gust_max'] = 46.0; }
        if ( $md >= '10-10' && $md <= '10-16' ) { $row['rain_sum'] = 1.5; }
        if ( $md === '07-31' ) { $row['rain_sum'] = 0.5; }  // begrenzt die Trockenperiode nach vorn
        if ( $md === '09-21' ) { $row['rain_sum'] = 0.5; }  // ... und nach hinten
        $rows[] = $row;
    }
    return $rows;
}
$year = naws_test_year();
check( 'das gebaute Jahr hat 365 Tage', count( $year ), 365 );

$r = NAWS_Records::compute( $year, 'hottest_day' );
close( 'heissester Tag: 39.1',                     $r['value'] ?? null, 39.1 );
check( 'heissester Tag: 1. Juli',                  $r['date'] ?? null, '2025-07-01' );
$r = NAWS_Records::compute( $year, 'coldest_night' );
close( 'kaelteste Nacht: -8.5',                    $r['value'] ?? null, -8.5 );
check( 'kaelteste Nacht: 10. Januar',              $r['date'] ?? null, '2025-01-10' );
$r = NAWS_Records::compute( $year, 'warmest_night' );
close( 'waermste Nacht: 24.0',                     $r['value'] ?? null, 24.0 );
$r = NAWS_Records::compute( $year, 'coldest_day' );
close( 'kaeltester Tag: -3.0',                     $r['value'] ?? null, -3.0 );
check( 'kaeltester Tag: 10. Januar',               $r['date'] ?? null, '2025-01-10' );
$r = NAWS_Records::compute( $year, 'widest_range' );
close( 'groesste Spanne: 25.0',                    $r['value'] ?? null, 25.0 );
check( 'groesste Spanne: 15. August',              $r['date'] ?? null, '2025-08-15' );
$r = NAWS_Records::compute( $year, 'warmest_month' );
close( 'waermster Monat: 22.0',                    $r['value'] ?? null, 22.0 );
check( 'waermster Monat: Juli',                    $r['month'] ?? null, '2025-07' );
$r = NAWS_Records::compute( $year, 'coldest_month' );
check( 'kaeltester Monat: Januar',                 $r['month'] ?? null, '2025-01' );
$r = NAWS_Records::compute( $year, 'wettest_day' );
close( 'nassester Tag: 26.4',                      $r['value'] ?? null, 26.4 );
check( 'nassester Tag: 3. Juni',                   $r['date'] ?? null, '2025-06-03' );
$r = NAWS_Records::compute( $year, 'wettest_month' );
close( 'nassester Monat: 90 mm',                   $r['value'] ?? null, 90.0 );
check( 'nassester Monat: November',                $r['month'] ?? null, '2025-11' );
$r = NAWS_Records::compute( $year, 'longest_dry_spell' );
check( 'laengste Trockenperiode: 51 Tage',         $r['value'] ?? null, 51 );
check( 'sie beginnt am 1. August',                 $r['from'] ?? null, '2025-08-01' );
check( 'und endet am 20. September',               $r['to'] ?? null, '2025-09-20' );
$r = NAWS_Records::compute( $year, 'longest_wet_spell' );
check( 'laengste Regenperiode: sieben Tage',       $r['value'] ?? null, 7 );
check( 'sie beginnt am 10. Oktober',               $r['from'] ?? null, '2025-10-10' );
$r = NAWS_Records::compute( $year, 'strongest_gust' );
close( 'staerkste Boe: 46',                        $r['value'] ?? null, 46.0 );
check( 'staerkste Boe: 3. Juni',                   $r['date'] ?? null, '2025-06-03' );
$r = NAWS_Records::compute( $year, 'longest_frost' );
check( 'laengste Frostperiode: sieben Tage',       $r['value'] ?? null, 7 );
check( 'Frost: 8.–14. Januar',                     ( $r['from'] ?? '' ) . '/' . ( $r['to'] ?? '' ), '2025-01-08/2025-01-14' );
$r = NAWS_Records::compute( $year, 'longest_heat_wave' );
check( 'Hitzewelle: fuenf Tage',                   $r['value'] ?? null, 5 );
$r = NAWS_Records::compute( $year, 'longest_summer_run' );
check( 'Sommerserie: neun Tage',                   $r['value'] ?? null, 9 );

echo "\nRegeln: Gleichstand, Monatsschwelle, fehlende Spalten\n" . str_repeat( '-', 74 ) . "\n";

$tie = [
    [ 'day_date' => '2025-05-01', 'temp_max' => 30.0 ],
    [ 'day_date' => '2025-05-02', 'temp_max' => 30.0 ],
];
check( 'Gleichstand: das fruehere Datum gewinnt', NAWS_Records::compute( $tie, 'hottest_day' )['date'] ?? null, '2025-05-01' );

$months = [];
for ( $i = 1; $i <= 19; $i++ ) { $months[] = [ 'day_date' => sprintf( '2025-03-%02d', $i ), 'temp_avg' => -5.0 ]; } // kalt, aber nur 19 Tage
for ( $i = 1; $i <= 20; $i++ ) { $months[] = [ 'day_date' => sprintf( '2025-04-%02d', $i ), 'temp_avg' => 4.0 ]; }  // 20 Tage zaehlen
check( 'ein Monat mit 19 Tagen zaehlt nicht',    NAWS_Records::compute( $months, 'coldest_month' )['month'] ?? null, '2025-04' );
$months[] = [ 'day_date' => '2025-03-20', 'temp_avg' => -5.0 ];
check( 'mit dem 20. Tag zaehlt er',              NAWS_Records::compute( $months, 'coldest_month' )['month'] ?? null, '2025-03' );

$month_tie = [];
for ( $i = 1; $i <= 20; $i++ ) { $month_tie[] = [ 'day_date' => sprintf( '2025-01-%02d', $i ), 'rain_sum' => 1.0 ]; }
for ( $i = 1; $i <= 20; $i++ ) { $month_tie[] = [ 'day_date' => sprintf( '2025-02-%02d', $i ), 'rain_sum' => 1.0 ]; }
check( 'Gleichstand bei Monaten: der fruehere', NAWS_Records::compute( $month_tie, 'wettest_month' )['month'] ?? null, '2025-01' );

$sparse = [
    [ 'day_date' => '2025-06-01', 'pressure_avg' => 1010.0 ],       // kennt keine Temperatur
    [ 'day_date' => '2025-06-02', 'temp_max' => 12.0 ],
];
check( 'Zeilen ohne die Spalte zaehlen nicht',   NAWS_Records::compute( $sparse, 'hottest_day' )['date'] ?? null, '2025-06-02' );
check( 'ohne brauchbare Zeile null',             NAWS_Records::compute( [ [ 'day_date' => '2025-06-01', 'pressure_avg' => 1010.0 ] ], 'hottest_day' ), null );
check( 'die Spanne braucht beide Werte',         NAWS_Records::compute( [ [ 'day_date' => '2025-06-01', 'temp_max' => 20.0 ] ], 'widest_range' ), null );
check( 'eine Serie ohne Treffer ist null',       NAWS_Records::compute( [ [ 'day_date' => '2025-06-01', 'temp_min' => 5.0 ] ], 'longest_frost' ), null );
check( 'ohne Zeilen null',                       NAWS_Records::compute( [], 'hottest_day' ), null );
check( 'ein unbekannter Schluessel ist null',    NAWS_Records::compute( $year, 'bestes_wetter' ), null );

echo "\nNAWS_Records::all()\n" . str_repeat( '-', 74 ) . "\n";

$all = NAWS_Records::all( $year );
check( 'alle 15 auf dem vollen Jahr',            count( $all ), 15 );
check( 'in Katalogreihenfolge',                  array_keys( $all ), array_keys( $cat ) );
$some = NAWS_Records::all( $year, [ 'wettest_day', 'hottest_day', 'bestes_wetter' ] );
check( 'Auswahl in Aufrufreihenfolge, Unbekanntes uebergangen', array_keys( $some ), [ 'wettest_day', 'hottest_day' ] );
$none = NAWS_Records::all( [ [ 'day_date' => '2025-06-01', 'pressure_avg' => 1010.0 ] ] );
check( 'ohne berechenbaren Rekord leer',         $none, [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
