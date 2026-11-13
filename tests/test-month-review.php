<?php
/**
 * Tests fuer NAWS_Month_Review -- Monate und Monatszusammenfassung.
 *
 * Die Rechnung ist rein: Tageszeilen rein, Zahlen raus. Die Monate sind von
 * Hand gebaut, damit jeder erwartete Wert aus dem Aufbau folgt.
 *
 *   php tests/test-month-review.php
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
require_once dirname( __DIR__ ) . '/includes/class-naws-month-review.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** One day row; omitted values are null. */
function day( string $date, array $v = [] ): array {
    return array_merge( [ 'day_date' => $date, 'temp_min' => null, 'temp_max' => null, 'temp_avg' => null, 'rain_sum' => null, 'gust_max' => null ], $v );
}

/** The first $days days of a month, each built by $f( day number ) -> values. */
function month_rows( string $ym, int $days, callable $f ): array {
    $rows = [];
    for ( $d = 1; $d <= $days; $d++ ) {
        $rows[] = day( sprintf( '%s-%02d', $ym, $d ), $f( $d ) );
    }
    return $rows;
}

echo "\nMonate, Aufloesung, Nachbarn\n" . str_repeat( '-', 74 ) . "\n";

$rows_months = [
    day( '2025-01-15', [ 'temp_avg' => 1.0 ] ),
    day( '2025-03-02', [ 'temp_avg' => 5.0 ] ),
    day( '2026-11-01', [ 'temp_avg' => 7.0 ] ),
];
check( 'months ohne laufenden Monat', NAWS_Month_Review::months( $rows_months, '2026-11-13' ), [ '2025-01', '2025-03' ] );
check( 'months: spaetere Monate fallen weg', NAWS_Month_Review::months( array_merge( $rows_months, [ day( '2027-01-01' ) ] ), '2026-11-13' ), [ '2025-01', '2025-03' ] );
check( 'months: leer', NAWS_Month_Review::months( [], '2026-11-13' ), [] );
check( 'resolve: bekannt', NAWS_Month_Review::resolve_month( '2025-01', [ '2025-01', '2025-03' ] ), '2025-01' );
check( 'resolve: unbekannt -> letzter', NAWS_Month_Review::resolve_month( '2030-01', [ '2025-01', '2025-03' ] ), '2025-03' );
check( 'resolve: leer -> null', NAWS_Month_Review::resolve_month( '', [] ), null );
check( 'neighbours überspringt Lücke', NAWS_Month_Review::neighbours( '2025-03', [ '2025-01', '2025-03' ] ), [ 'prev' => '2025-01', 'next' => null ] );
check( 'neighbours: erster', NAWS_Month_Review::neighbours( '2025-01', [ '2025-01', '2025-03' ] ), [ 'prev' => null, 'next' => '2025-03' ] );

echo "\nsummary(): Oktober 2026\n" . str_repeat( '-', 74 ) . "\n";

// Hand-built so every value below is true by construction.
// temp_max: 15.0, Tage 3 und 20 = 24.0, Tag 4 = 23.99, Tag 8 = 12.0.
// temp_min: 5.0, Tag 5 = -1.5, Tag 6 = -0.1 (Frost), Tag 7 = 0.0 (kein Frost).
// rain_sum: Tage 1-14 = 1.0, Tag 15 = 0.1 (zaehlt), Tag 16 = 0.09 (nicht), Tag 17 = 12.5, sonst 0.0
//           -> 14 + 1 + 1 = 16 Regentage.
$okt2026 = month_rows( '2026-10', 31, static function ( int $d ): array {
    $max  = 15.0;
    $min  = 5.0;
    $rain = 0.0;
    if ( $d === 3 || $d === 20 ) { $max = 24.0; }
    if ( $d === 4 ) { $max = 23.99; }
    if ( $d === 8 ) { $max = 12.0; }
    if ( $d === 5 ) { $min = -1.5; }
    if ( $d === 6 ) { $min = -0.1; }
    if ( $d === 7 ) { $min = 0.0; }
    if ( $d <= 14 ) { $rain = 1.0; }
    if ( $d === 15 ) { $rain = 0.1; }
    if ( $d === 16 ) { $rain = 0.09; }
    if ( $d === 17 ) { $rain = 12.5; }
    return [
        'temp_min' => $min,
        'temp_max' => $max,
        'temp_avg' => ( $min + $max ) / 2,
        'rain_sum' => $rain,
        'gust_max' => 30.0 + $d,
    ];
} );
$s = NAWS_Month_Review::summary( $okt2026, '2026-10' );
check( 'ym', $s['ym'], '2026-10' );
check( 'length', $s['length'], 31 );
check( 'max mit erstem Tag', $s['max'], [ 'value' => 24.0, 'date' => '2026-10-03' ] );
check( 'min', $s['min'], [ 'value' => -1.5, 'date' => '2026-10-05' ] );
check( 'rain_days ab 0,1', $s['rain_days'], 16 );
check( 'frost_days < 0', $s['frost_days'], 2 );
check( 'summer_days >= 25', $s['summer_days'], 0 );
check( 'wettest_day', $s['wettest_day'], [ 'value' => 12.5, 'date' => '2026-10-17' ] );
check( 'gust', $s['gust'], [ 'value' => 61.0, 'date' => '2026-10-31' ] );
check( 'rain Summe', abs( $s['rain'] - ( 14 * 1.0 + 0.1 + 0.09 + 12.5 ) ) < 1e-9, true );
check( 'mean ist float', is_float( $s['mean'] ), true );
check( 'days', $s['days'], [ 'temp_avg' => 31, 'temp_max' => 31, 'temp_min' => 31, 'rain_sum' => 31, 'gust_max' => 31 ] );
check( 'complete: alle neun true', array_values( array_unique( $s['complete'] ) ), [ true ] );
check( 'complete: neun Schluessel', array_keys( $s['complete'] ), [ 'mean', 'max', 'min', 'rain', 'rain_days', 'wettest_day', 'gust', 'frost_days', 'summer_days' ] );
check( 'Schluessel von summary', array_keys( $s ), [ 'ym', 'length', 'mean', 'max', 'min', 'rain', 'rain_days', 'wettest_day', 'gust', 'frost_days', 'summer_days', 'days', 'complete' ] );

$sommer = month_rows( '2026-07', 31, static fn( int $d ): array => [ 'temp_max' => $d <= 3 ? 25.0 : 24.99, 'temp_min' => 12.0 ] );
check( 'summer_days: genau 25.0 zaehlt', NAWS_Month_Review::summary( $sommer, '2026-07' )['summer_days'], 3 );
check( 'Schaltjahr-Februar length', NAWS_Month_Review::summary( [], '2028-02' )['length'], 29 );
check( 'Februar length', NAWS_Month_Review::summary( [], '2026-02' )['length'], 28 );

echo "\nVollstaendigkeit\n" . str_repeat( '-', 74 ) . "\n";

$mit19 = month_rows( '2026-10', 19, static fn( int $d ): array => [ 'temp_avg' => 8.0 ] );
$mit20 = month_rows( '2026-10', 20, static fn( int $d ): array => [ 'temp_avg' => 8.0 ] );
check( '19 Tage temp_avg -> mean unvollständig', NAWS_Month_Review::summary( $mit19, '2026-10' )['complete']['mean'], false );
check( '20 Tage temp_avg -> mean vollständig', NAWS_Month_Review::summary( $mit20, '2026-10' )['complete']['mean'], true );
$regen30 = month_rows( '2026-10', 30, static fn( int $d ): array => [ 'rain_sum' => $d === 9 ? 3.0 : 0.0 ] );
$sr = NAWS_Month_Review::summary( $regen30, '2026-10' );
check( '30/31 rain_sum -> rain unvollständig', $sr['complete']['rain'], false );
check( '30/31 rain_sum -> rain_days unvollständig', $sr['complete']['rain_days'], false );
check( '30/31 rain_sum -> wettest_day vollst.', $sr['complete']['wettest_day'], true );
check( '30/31 rain_sum -> days', $sr['days']['rain_sum'], 30 );
$regen31 = month_rows( '2026-10', 31, static fn( int $d ): array => [ 'rain_sum' => 0.0 ] );
check( '31/31 rain_sum -> rain vollstaendig', NAWS_Month_Review::summary( $regen31, '2026-10' )['complete']['rain'], true );
$frost30 = month_rows( '2026-10', 30, static fn( int $d ): array => [ 'temp_min' => -1.0, 'temp_max' => 26.0 ] );
$sf = NAWS_Month_Review::summary( $frost30, '2026-10' );
check( 'frost_days 30/31 unvollständig', $sf['complete']['frost_days'], false );
check( 'summer_days 30/31 unvollständig', $sf['complete']['summer_days'], false );
check( 'min 30 Tage vollständig', $sf['complete']['min'], true );

echo "\nLuecken und Leerfaelle\n" . str_repeat( '-', 74 ) . "\n";

$ohne_wind = month_rows( '2026-10', 31, static fn( int $d ): array => [ 'temp_avg' => 8.0 ] );
check( 'ohne gust_max -> gust null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['gust'], null );
check( 'ohne rain_sum -> rain null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['rain'], null );
check( 'ohne rain_sum -> rain_days null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['rain_days'], null );
check( 'ohne rain_sum -> wettest_day null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['wettest_day'], null );
check( 'ohne temp_min -> frost_days null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['frost_days'], null );
check( 'ohne temp_max -> summer_days null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['summer_days'], null );
check( 'ohne temp_max -> max null', NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['max'], null );
$leer = NAWS_Month_Review::summary( [], '2026-10' );
check( 'leerer Monat: mean null', $leer['mean'], null );
check( 'leerer Monat: complete alle false', array_values( array_unique( $leer['complete'] ) ), [ false ] );
check( 'leerer Monat: days alle 0', array_values( array_unique( $leer['days'] ) ), [ 0 ] );
$fremd = [ day( '2026-09-30', [ 'temp_avg' => 99.0 ] ), day( '2026-11-01', [ 'temp_avg' => 99.0 ] ) ];
check( 'andere Monate zaehlen nicht', NAWS_Month_Review::summary( $fremd, '2026-10' )['mean'], null );

echo "\nKonstanten\n" . str_repeat( '-', 74 ) . "\n";
check( 'YEARS_DEFAULT', NAWS_Month_Review::YEARS_DEFAULT, 5 );
check( 'YEARS_MAX', NAWS_Month_Review::YEARS_MAX, 10 );
check( 'TREND_TEMP', NAWS_Month_Review::TREND_TEMP, 0.5 );
check( 'TREND_RAIN_PCT', NAWS_Month_Review::TREND_RAIN_PCT, 0.10 );
check( 'TREND_RAIN_MM', NAWS_Month_Review::TREND_RAIN_MM, 2.0 );
check( 'TREND_GUST', NAWS_Month_Review::TREND_GUST, 5.0 );

echo "\n" . str_repeat( '=', 74 ) . "\n";
printf( "  %d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
