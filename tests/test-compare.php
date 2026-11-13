<?php
/**
 * Tests fuer NAWS_Compare -- den Modulvergleich [naws_compare].
 *
 * Rein gerechnet: Katalog der Reihen, Lesen der Shortcode-Angaben,
 * Zeitraum, Zusammenfuehren auf eine Tagesachse, Verdichten und Achsen.
 * Was WordPress braucht (Zeilen holen, rendern), steht in
 * test-compare-render.php.
 *
 *   php tests/test-compare.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

require_once dirname( __DIR__ ) . '/includes/class-naws-compare.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nKatalog und Parser\n" . str_repeat( '-', 74 ) . "\n";

$cat = NAWS_Compare::series_catalogue();
check( '15 Reihen im Katalog', array_sum( array_map( 'count', $cat ) ), 15 );
check( 'Gruppen', array_keys( $cat ), [ 'outdoor', 'indoor', 'rain', 'wind', 'in' ] );
check( 'Regen ist Balken', $cat['rain']['rain']['chart'], 'bar' );
check( 'sonst Linien', count( array_filter( array_merge( ...array_map( 'array_values', array_values( $cat ) ) ), static fn( $e ) => $e['chart'] === 'line' ) ), 14 );
check( 'Basis innen Temp aus indoor_temp_avg', $cat['indoor']['temp']['col'], 'indoor_temp_avg' );
check( 'Aussen-Feuchte aus der Stationszeile', [ $cat['outdoor']['humidity']['col'], $cat['outdoor']['humidity']['row'] ], [ 'humidity_avg', 'station' ] );
check( 'Innenmodul aus eigener Zeile', $cat['in']['co2']['row'], 'own' );
check( 'Einheiten-Parameter', $cat['wind']['gust']['param'], 'GustStrength' );

check( 'Matthew: indoor:humidity', NAWS_Compare::parse_series( 'indoor:humidity' ), [ 'ok' => true, 'ref' => 'indoor', 'qty' => 'humidity', 'error' => '' ] );
check( 'Gross/Klein + Leerzeichen', NAWS_Compare::parse_series( ' Outdoor:Humidity ' ), [ 'ok' => true, 'ref' => 'outdoor', 'qty' => 'humidity', 'error' => '' ] );
check( 'Innenmodul-Referenz', NAWS_Compare::parse_series( 'in-gast:co2' ), [ 'ok' => true, 'ref' => 'in-gast', 'qty' => 'co2', 'error' => '' ] );
check( 'Tippfehler Modul', NAWS_Compare::parse_series( 'outdor:humidity' )['error'], 'unknown_module' );
check( 'Groesse passt nicht', NAWS_Compare::parse_series( 'rain:humidity' )['error'], 'unknown_quantity' );
check( 'ohne Doppelpunkt', NAWS_Compare::parse_series( 'outdoor' )['error'], 'syntax' );
check( 'leer', NAWS_Compare::parse_series( '' )['error'], 'syntax' );
check( 'in- ohne Namen', NAWS_Compare::parse_series( 'in-:temp' )['error'], 'unknown_module' );

echo "\nZeitraum\n" . str_repeat( '-', 74 ) . "\n";

$y = '2026-11-12';
check( 'from Monat', NAWS_Compare::period( [ 'from' => '2024-04' ], '2024-03-28', $y )['from'], '2024-04-01' );
check( 'from Tag', NAWS_Compare::period( [ 'from' => '2024-04-15' ], '2024-03-28', $y )['from'], '2024-04-15' );
check( 'to Monat = Monatsende', NAWS_Compare::period( [ 'to' => '2025-02' ], '2024-03-28', $y )['to'], '2025-02-28' );
check( 'months=24', NAWS_Compare::period( [ 'months' => '24' ], '2024-03-28', $y )['from'], '2024-12-01' );
check( 'months geklemmt 120', NAWS_Compare::period( [ 'months' => '500' ], '2010-01-01', $y )['from'], '2016-12-01' );
check( 'from schlaegt months', NAWS_Compare::period( [ 'from' => '2025-01', 'months' => '3' ], '2024-03-28', $y )['from'], '2025-01-01' );
$p = NAWS_Compare::period( [], '2024-03-28', $y );
check( 'leer -> auto', [ $p['from'], $p['to'], $p['auto_from'], $p['auto_to'] ], [ '2024-03-28', $y, true, true ] );
check( 'ohne Daten: from null', NAWS_Compare::period( [], null, $y )['from'], null );
check( 'Zukunft gekappt', NAWS_Compare::period( [ 'to' => '2030-01-01' ], '2024-03-28', $y )['to'], $y );
$p = NAWS_Compare::period( [ 'from' => '2025-06', 'to' => '2025-01' ], '2024-03-28', $y );
check( 'vertauscht', [ $p['from'], $p['to'] ], [ '2025-01-01', '2025-06-30' ] );
check( 'ungueltig wie leer', NAWS_Compare::period( [ 'from' => '2025-13' ], '2024-03-28', $y )['auto_from'], true );
check( 'fester Zeitraum nicht auto', NAWS_Compare::period( [ 'from' => '2025-01', 'to' => '2025-03' ], '2024-03-28', $y )['auto_to'], false );

$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'get_daily_summaries kennt indoor_humidity_avg', (bool) preg_match( "/\\\$allowed_fields\\s*=\\s*\\[[^\\]]*'indoor_humidity_avg'/", $db ), true );
check( 'get_daily_summaries kennt indoor_temp_avg', (bool) preg_match( "/\\\$allowed_fields\\s*=\\s*\\[[^\\]]*'indoor_temp_avg'/", $db ), true );

echo "\nZusammenfuehren, Verdichten, Achsen\n" . str_repeat( '-', 74 ) . "\n";

$a = [ 'values' => [ '2025-01-01' => 50.0, '2025-01-03' => 52.0 ], 'chart' => 'line' ];
$b = [ 'values' => [ '2025-01-02' => 80.0 ], 'chart' => 'line' ];
$r = NAWS_Compare::build( [ $a, $b ], '2024-12-30', '2025-01-05', true, true );
check( 'auto zugeschnitten', $r['dates'], [ '2025-01-01', '2025-01-02', '2025-01-03' ] );
check( 'Luecke = null (a)', $r['values'][0], [ 50.0, null, 52.0 ] );
check( 'Luecke = null (b)', $r['values'][1], [ null, 80.0, null ] );
check( 'taeglich', $r['weekly'], false );
check( 'fester Zeitraum bleibt', count( NAWS_Compare::build( [ $a ], '2024-12-30', '2025-01-05', false, false )['dates'] ), 7 );
check( 'nur auto_to schneidet hinten', NAWS_Compare::build( [ $a ], '2024-12-30', '2025-01-05', false, true )['dates'][0], '2024-12-30' );
check( 'nichts -> leer', NAWS_Compare::build( [ [ 'values' => [], 'chart' => 'line' ] ], '2025-01-01', '2025-01-31', true, true )['dates'], [] );
check( 'Wert ausserhalb des Zeitraums zaehlt nicht', NAWS_Compare::build( [ [ 'values' => [ '2026-01-01' => 1.0 ], 'chart' => 'line' ] ], '2025-01-01', '2025-01-31', true, true )['dates'], [] );

/** $n days from $from, every day $value. */
function naws_series_days( string $from, int $n, float $value, string $chart ): array {
    $v = [];
    for ( $i = 0; $i < $n; $i++ ) {
        $v[ gmdate( 'Y-m-d', strtotime( $from . ' +' . $i . ' days' ) ) ] = $value;
    }
    return [ 'values' => $v, 'chart' => $chart ];
}
$f      = '2015-01-05'; // a Monday
$t3650  = gmdate( 'Y-m-d', strtotime( $f . ' +3649 days' ) );
$t3651  = gmdate( 'Y-m-d', strtotime( $f . ' +3650 days' ) );
check( '3650 Tage taeglich', NAWS_Compare::build( [ naws_series_days( $f, 3650, 10.0, 'line' ) ], $f, $t3650, false, false )['weekly'], false );
$w = NAWS_Compare::build( [ naws_series_days( $f, 3651, 10.0, 'line' ), naws_series_days( $f, 3651, 1.0, 'bar' ) ], $f, $t3651, false, false );
check( '3651 Tage -> Wochen', $w['weekly'], true );
check( 'erste Woche beginnt am Montag', $w['dates'][0], '2015-01-05' );
check( 'Wochendatum ist Montag', gmdate( 'N', strtotime( $w['dates'][1] ) ), '1' );
check( 'Woche Linie = Mittel', $w['values'][0][1], 10.0 );
check( 'Woche Balken = Summe', $w['values'][1][1], 7.0 );
check( 'gleich lang', count( $w['values'][0] ), count( $w['dates'] ) );
$gap = naws_series_days( $f, 3651, 10.0, 'line' );
foreach ( array_slice( array_keys( $gap['values'] ), 7, 7 ) as $d ) { $gap['values'][ $d ] = null; }
check( 'Woche ohne Werte = null', NAWS_Compare::build( [ $gap ], $f, $t3651, false, false )['values'][0][1], null );

check( 'gleiche Einheit eine Achse', NAWS_Compare::axes( '%', '%' ), [ 'a' => 'y', 'b' => 'y', 'right' => false ] );
check( 'zwei Einheiten zwei Achsen', NAWS_Compare::axes( '°C', '%' ), [ 'a' => 'y', 'b' => 'y1', 'right' => true ] );
check( 'eine Reihe', NAWS_Compare::axes( '°C', null ), [ 'a' => 'y', 'b' => null, 'right' => false ] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
