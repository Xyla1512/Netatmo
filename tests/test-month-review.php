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

echo "\nVergleich, Rekorde, Kopfsatz, Saetze\n" . str_repeat( '-', 74 ) . "\n";

/**
 * A full 31-day October with the given monthly figures: mean on every day,
 * the minimum on day 1, the maximum on day 2, all rain on day 5, the gust on day 3.
 */
function oktober( int $year, float $mean, float $min, float $max, ?float $rain, float $gust ): array {
    return month_rows( sprintf( '%d-10', $year ), 31, static function ( int $d ) use ( $mean, $min, $max, $rain, $gust ): array {
        return [
            'temp_avg' => $mean,
            'temp_min' => $d === 1 ? $min : $min + 2.0,
            'temp_max' => $d === 2 ? $max : $max - 3.0,
            'rain_sum' => $rain === null ? null : ( $d === 5 ? $rain : 0.0 ),
            'gust_max' => $d === 3 ? $gust : $gust - 10.0,
        ];
    } );
}

$drei_oktober = array_merge(
    oktober( 2024, 12.9, 5.9, 20.9, 29.3, 40.0 ),
    oktober( 2025, 11.3, 3.1, 17.6, 37.3, 34.0 ),
    oktober( 2026, 12.2, 1.9, 24.0, 39.6, 26.0 )
);

$c = NAWS_Month_Review::compare( $drei_oktober, '2026-10', 5 );
check( 'previous neueste zuerst',     array_map( 'strval', array_keys( $c['previous'] ) ), [ '2025', '2024' ] );
check( 'current ist summary',         $c['current']['ym'], '2026-10' );
check( 'Schluessel von compare',      array_keys( $c ), [ 'current', 'previous', 'record', 'trend' ] );
check( 'record-Schluessel',           array_keys( $c['record'] ), [ 'mean', 'max', 'min', 'rain', 'gust' ] );
check( 'Regen 39,6 > 37,3/29,3 -> Stern', $c['record']['rain'], 'high' );
check( 'Max 24,0 -> Stern',           $c['record']['max'], 'high' );
check( 'Min 1,9 -> Stern tief',       $c['record']['min'], 'low' );
check( 'Boee 26 < Mittel 37 -> runter', $c['trend']['gust'], 'down' );
check( 'Boee 26 hat keinen Stern (nur high)', $c['record']['gust'], null );
check( 'Mittel 12,2 vs 12,1 -> kein Pfeil', $c['trend']['mean'], null );
check( 'Mittel 12,2 -> kein Stern',   $c['record']['mean'], null );
check( 'Stern unterdrueckt Pfeil',    $c['trend']['rain'], null );
check( 'years=1 begrenzt previous',   array_map( 'strval', array_keys( NAWS_Month_Review::compare( $drei_oktober, '2026-10', 1 )['previous'] ) ), [ '2025' ] );
check( 'years=1: Stern gilt gegen alle Vorjahre', NAWS_Month_Review::compare( $drei_oktober, '2026-10', 1 )['record']['rain'], 'high' );
$c0 = NAWS_Month_Review::compare( $drei_oktober, '2026-10', 0 );
check( 'years=0 -> kein Stern',       $c0['record']['rain'], null );
check( 'years=0 -> kein Pfeil',       $c0['trend']['gust'], null );
check( 'years=0 -> previous leer',    $c0['previous'], [] );
check( 'alter Monat ignoriert spaetere Zeilen', NAWS_Month_Review::compare( $drei_oktober, '2025-10', 5 )['record']['rain'], 'high' );
check( 'alter Monat: previous nur 2024', array_map( 'strval', array_keys( NAWS_Month_Review::compare( $drei_oktober, '2025-10', 5 )['previous'] ) ), [ '2024' ] );
check( 'erster Oktober -> kein Stern', NAWS_Month_Review::compare( $drei_oktober, '2024-10', 5 )['record']['rain'], null );

$gleichstand = array_merge( oktober( 2025, 11.0, 3.0, 20.0, 10.0, 30.0 ), oktober( 2026, 11.0, 3.0, 20.0, 10.0, 30.0 ) );
$cg = NAWS_Month_Review::compare( $gleichstand, '2026-10', 5 );
check( 'Gleichstand Max ist kein Rekord', $cg['record']['max'], null );
check( 'Gleichstand Min ist kein Rekord', $cg['record']['min'], null );
check( 'Gleichstand Regen ist kein Rekord', $cg['record']['rain'], null );

$regen_luecke = array_merge(
    oktober( 2024, 12.9, 5.9, 20.9, 29.3, 40.0 ),
    oktober( 2025, 11.3, 3.1, 17.6, 37.3, 34.0 ),
    oktober( 2026, 12.2, 1.9, 24.0, null, 26.0 )
);
$cl = NAWS_Month_Review::compare( $regen_luecke, '2026-10', 5 );
check( 'unvollst. Regen -> kein Stern, kein Pfeil', [ $cl['record']['rain'], $cl['trend']['rain'] ], [ null, null ] );
check( 'unvollst. Regen: Mittel bleibt vollstaendig', $cl['current']['complete']['mean'], true );
check( 'unvollst. Regen: Max-Stern bleibt', $cl['record']['max'], 'high' );

// Rain trend needs a month between the earlier ones (else it is a star): 5 mm and 15 mm -> mean 10.
$regen_paar = static fn( float $rain ): array => array_merge( oktober( 2024, 11.0, 3.0, 20.0, 5.0, 30.0 ), oktober( 2025, 11.0, 3.0, 20.0, 15.0, 30.0 ), oktober( 2026, 11.0, 3.0, 20.0, $rain, 30.0 ) );
check( 'Regen-Schwelle: +1,9 mm bei Mittel 10 -> kein Pfeil', NAWS_Month_Review::compare( $regen_paar( 11.9 ), '2026-10', 5 )['trend']['rain'], null );
check( 'Regen-Schwelle: +2,0 mm bei Mittel 10 -> hoch',       NAWS_Month_Review::compare( $regen_paar( 12.0 ), '2026-10', 5 )['trend']['rain'], 'up' );
check( 'Regen-Schwelle: -2,0 mm bei Mittel 10 -> runter',     NAWS_Month_Review::compare( $regen_paar( 8.0 ), '2026-10', 5 )['trend']['rain'], 'down' );
check( 'Regen-Schwelle: Mittel 10, 12,0 ohne Stern',          NAWS_Month_Review::compare( $regen_paar( 12.0 ), '2026-10', 5 )['record']['rain'], null );
// Mean 30 mm: the 10 % share (3 mm) is the stricter limit.
$regen_30 = static fn( float $rain ): array => array_merge( oktober( 2024, 11.0, 3.0, 20.0, 24.0, 30.0 ), oktober( 2025, 11.0, 3.0, 20.0, 36.0, 30.0 ), oktober( 2026, 11.0, 3.0, 20.0, $rain, 30.0 ) );
check( 'Regen-Schwelle: Mittel 30, -2,1 mm -> kein Pfeil', NAWS_Month_Review::compare( $regen_30( 27.9 ), '2026-10', 5 )['trend']['rain'], null );
check( 'Regen-Schwelle: Mittel 30, -3,0 mm -> runter',     NAWS_Month_Review::compare( $regen_30( 27.0 ), '2026-10', 5 )['trend']['rain'], 'down' );
// Temperature: mean of earlier years 12.0.
$temp_paar = static fn( float $mean ): array => array_merge( oktober( 2024, 11.0, 3.0, 20.0, 5.0, 30.0 ), oktober( 2025, 13.0, 3.0, 20.0, 5.0, 30.0 ), oktober( 2026, $mean, 3.0, 20.0, 5.0, 30.0 ) );
check( 'Temperatur-Schwelle: -0,5 K -> runter', NAWS_Month_Review::compare( $temp_paar( 11.5 ), '2026-10', 5 )['trend']['mean'], 'down' );
check( 'Temperatur-Schwelle: +0,4 K -> kein Pfeil', NAWS_Month_Review::compare( $temp_paar( 12.4 ), '2026-10', 5 )['trend']['mean'], null );
check( 'Temperatur-Schwelle: +0,5 K -> hoch', NAWS_Month_Review::compare( $temp_paar( 12.5 ), '2026-10', 5 )['trend']['mean'], 'up' );

check( 'Kopfsatz Regen vor Mittel',   NAWS_Month_Review::headline( $c )['key'], 'mr_head_wettest' );
check( 'Kopfsatz args',               NAWS_Month_Review::headline( $c )['args'], [ 'month' => '2026-10' ] );
check( 'Kopfsatz ohne Vorjahr neutral', NAWS_Month_Review::headline( NAWS_Month_Review::compare( $drei_oktober, '2024-10', 5 ) )['key'], 'mr_head_neutral' );
check( 'Kopfsatz neutral args',       NAWS_Month_Review::headline( NAWS_Month_Review::compare( $drei_oktober, '2024-10', 5 ) )['args'], [ 'month' => '2024-10' ] );
$stern = static fn( array $record ): array => [ 'current' => [ 'ym' => '2026-10' ], 'record' => array_merge( [ 'mean' => null, 'max' => null, 'min' => null, 'rain' => null, 'gust' => null ], $record ) ];
check( 'Kopfsatz driest',        NAWS_Month_Review::headline( $stern( [ 'rain' => 'low' ] ) )['key'], 'mr_head_driest' );
check( 'Kopfsatz warmest',       NAWS_Month_Review::headline( $stern( [ 'mean' => 'high', 'max' => 'high' ] ) )['key'], 'mr_head_warmest' );
check( 'Kopfsatz coldest',       NAWS_Month_Review::headline( $stern( [ 'mean' => 'low' ] ) )['key'], 'mr_head_coldest' );
check( 'Kopfsatz hottest_day',   NAWS_Month_Review::headline( $stern( [ 'max' => 'high', 'min' => 'low', 'gust' => 'high' ] ) )['key'], 'mr_head_hottest_day' );
check( 'Kopfsatz coldest_night', NAWS_Month_Review::headline( $stern( [ 'min' => 'low', 'gust' => 'high' ] ) )['key'], 'mr_head_coldest_night' );
check( 'Kopfsatz gust',          NAWS_Month_Review::headline( $stern( [ 'gust' => 'high' ] ) )['key'], 'mr_head_gust' );

$sr_alle_false = [ 'hottest_day' => false, 'coldest_night' => false, 'strongest_gust' => false, 'wettest_day' => false ];
check( 'Stationsrekord nur bei echtem Uebertreffen', NAWS_Month_Review::station_records( $drei_oktober, '2026-10' )['hottest_day'], true );
check( 'Stationsrekord kaeltere Nacht', NAWS_Month_Review::station_records( $drei_oktober, '2026-10' )['coldest_night'], true );
check( 'Stationsrekord keine staerkere Boee', NAWS_Month_Review::station_records( $drei_oktober, '2026-10' )['strongest_gust'], false );
check( 'Stationsrekord nassester Tag', NAWS_Month_Review::station_records( $drei_oktober, '2026-10' )['wettest_day'], true );
check( 'Stationsrekord ohne Zeilen davor', NAWS_Month_Review::station_records( $drei_oktober, '2024-10' ), $sr_alle_false );
check( 'Stationsrekord Gleichstand nein', NAWS_Month_Review::station_records( $gleichstand, '2026-10' ), $sr_alle_false );
check( 'Stationsrekord ignoriert Spaeteres', NAWS_Month_Review::station_records( $drei_oktober, '2025-10' )['hottest_day'], false );

// Sentences. October 2026 below: 3 frost days, 2 summer days, wettest day 12.5 mm.
$satz_okt = static function ( int $year, int $frost, int $summer, float $nass ): array {
    return month_rows( sprintf( '%d-10', $year ), 31, static function ( int $d ) use ( $frost, $summer, $nass ): array {
        return [
            'temp_avg' => 10.0,
            'temp_min' => $d <= $frost ? -1.0 : 4.0,
            'temp_max' => $d <= $summer ? 26.0 : 15.0,
            'rain_sum' => $d === 7 ? $nass : 0.0,
            'gust_max' => 30.0,
        ];
    } );
};
$satz_rows = array_merge( $satz_okt( 2025, 1, 4, 3.0 ), $satz_okt( 2026, 3, 2, 12.5 ) );
$satz_c    = NAWS_Month_Review::compare( $satz_rows, '2026-10', 5 );
$satz      = NAWS_Month_Review::sentences( $satz_c, $sr_alle_false );
check( 'Saetze: Frost zuerst',  $satz[0], [ 'key' => 'mr_frost', 'args' => [ 'n' => 3, 'prev' => 1, 'prev_year' => '2025' ] ] );
check( 'Saetze: Sommertage',    $satz[1], [ 'key' => 'mr_summer', 'args' => [ 'n' => 2, 'prev' => 4, 'prev_year' => '2025' ] ] );
check( 'Saetze: nassester Tag', $satz[2], [ 'key' => 'mr_wettest_day', 'args' => [ 'date' => '2026-10-07', 'value' => 12.5 ] ] );
check( 'Saetze: hoechstens drei', count( NAWS_Month_Review::sentences( $satz_c, [ 'hottest_day' => true, 'coldest_night' => false, 'strongest_gust' => false, 'wettest_day' => false ] ) ), 3 );

$satz_ohne = NAWS_Month_Review::sentences( NAWS_Month_Review::compare( $satz_rows, '2026-10', 0 ), $sr_alle_false );
check( 'Saetze years=0: prev null', $satz_ohne[0]['args'], [ 'n' => 3, 'prev' => null, 'prev_year' => null ] );

$trocken    = array_merge( $satz_okt( 2025, 0, 0, 0.5 ), $satz_okt( 2026, 0, 0, 0.5 ) );
$st_trocken = NAWS_Month_Review::sentences( NAWS_Month_Review::compare( $trocken, '2026-10', 5 ), [ 'hottest_day' => false, 'coldest_night' => true, 'strongest_gust' => true, 'wettest_day' => true ] );
check( 'Saetze: kein Frost, kein Sommer, Tag < 1 mm -> nur der erste Stationsrekord', array_column( $st_trocken, 'key' ), [ 'mr_station_record_coldest_night' ] );
check( 'Stationsrekord-Satz args', $st_trocken[0]['args'], [ 'date' => '2026-10-01', 'value' => 4.0 ] );
$wenig    = array_merge( $satz_okt( 2025, 0, 0, 0.5 ), $satz_okt( 2026, 2, 0, 0.5 ) );
$st_wenig = NAWS_Month_Review::sentences( NAWS_Month_Review::compare( $wenig, '2026-10', 5 ), $sr_alle_false );
check( 'Saetze: Frost ja, Sommer 0 -> weg', array_column( $st_wenig, 'key' ), [ 'mr_frost' ] );
$luecke_frost = array_merge( $satz_okt( 2025, 1, 4, 3.0 ), array_slice( $satz_okt( 2026, 3, 2, 12.5 ), 0, 25 ) );
$st_luecke    = NAWS_Month_Review::sentences( NAWS_Month_Review::compare( $luecke_frost, '2026-10', 5 ), $sr_alle_false );
check( 'Saetze: unvollstaendiger Monat -> kein Frost-/Sommersatz', array_column( $st_luecke, 'key' ), [ 'mr_wettest_day' ] );

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
