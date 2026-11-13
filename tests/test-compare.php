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

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
