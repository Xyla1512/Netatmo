<?php
/**
 * Yr.no: Regenmenge und Regenwahrscheinlichkeit je Tag.
 *
 * Zwei Fehler (Durchsicht der Seite, November 2026):
 *   - Die Wahrscheinlichkeit stand fest auf 0 %: abgefragt wurde der
 *     compact-Endpunkt, der probability_of_precipitation nicht kennt.
 *   - Die Regenmenge der ersten Tage war etwa sechsfach zu hoch: Yr liefert
 *     dort stuendliche Eintraege, und jeder traegt ein 6-Stunden-Fenster.
 *     Summiert wurde fuer jede Stunde das ganze Fenster — die Fenster
 *     ueberlappen sich.
 *
 *   php tests/test-forecast-yr.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_VERSION', 'test' );
function get_option( $k, $d = false ) { return $d; }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-forecast.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** A complete-endpoint entry. $n1/$n6: [amount, probability] or null. */
function entry( string $time, ?array $n1, ?array $n6 ): array {
    $data = [ 'instant' => [ 'details' => [ 'air_temperature' => 5.0, 'wind_speed' => 3.0, 'wind_from_direction' => 200 ] ] ];
    if ( $n1 !== null ) {
        $data['next_1_hours'] = [ 'summary' => [ 'symbol_code' => 'rain' ], 'details' => [ 'precipitation_amount' => $n1[0], 'probability_of_precipitation' => $n1[1] ] ];
    }
    if ( $n6 !== null ) {
        $data['next_6_hours'] = [ 'summary' => [ 'symbol_code' => 'rain' ], 'details' => [ 'precipitation_amount' => $n6[0], 'probability_of_precipitation' => $n6[1] ] ];
    }
    return [ 'time' => $time, 'data' => $data ];
}

$series = [];
// 14.11.: hourly entries, 0.1 mm each hour, 6 h windows of 0.6 mm; probability 20 %, one hour 80 %.
for ( $h = 0; $h < 24; $h++ ) {
    $series[] = entry( sprintf( '2026-11-14T%02d:00:00Z', $h ), [ 0.1, $h === 15 ? 80.0 : 20.0 ], [ 0.6, 50.0 ] );
}
// 17.11.: six-hourly entries only (later part of the forecast), 1.0 mm and 30 % each.
foreach ( [ 0, 6, 12, 18 ] as $h ) {
    $series[] = entry( sprintf( '2026-11-17T%02d:00:00Z', $h ), null, [ 1.0, 30.0 ] );
}
// 18.11.: compact-style entry — no probability at all.
$series[] = [ 'time' => '2026-11-18T00:00:00Z', 'data' => [
    'instant'      => [ 'details' => [ 'air_temperature' => 4.0 ] ],
    'next_6_hours' => [ 'summary' => [ 'symbol_code' => 'cloudy' ], 'details' => [ 'precipitation_amount' => 0.0 ] ],
] ];

$m = new ReflectionMethod( 'NAWS_Forecast', 'normalise_yr' );
$m->setAccessible( true );
$out  = $m->invoke( null, [ 'properties' => [ 'timeseries' => $series ] ], [ 'lat' => 51.3, 'lon' => 12.4 ] );
$days = [];
foreach ( $out['days'] as $d ) {
    $days[ $d['date'] ] = $d;
}

echo "\nRegenmenge\n" . str_repeat( '-', 74 ) . "\n";
check( 'stuendlicher Teil: 24 x 0,1 mm, nicht ueberlappende 6-h-Fenster', $days['2026-11-14']['precip_sum'], 2.4 );
check( 'sechsstuendlicher Teil: 4 x 1,0 mm', $days['2026-11-17']['precip_sum'], 4.0 );

echo "\nRegenwahrscheinlichkeit\n" . str_repeat( '-', 74 ) . "\n";
check( 'hoechster Stundenwert des Tages', $days['2026-11-14']['precip_prob'], 80 );
check( 'aus den 6-h-Fenstern, wo es keine Stunden gibt', $days['2026-11-17']['precip_prob'], 30 );
check( 'ohne Angabe: null statt 0', $days['2026-11-18']['precip_prob'], null );

echo "\nAbfrage\n" . str_repeat( '-', 74 ) . "\n";
$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-forecast.php' );
check( 'complete-Endpunkt fuer Vorhersage und aktuelle Lage', substr_count( $src, 'locationforecast/2.0/complete' ), 2 );
check( 'kein compact-Endpunkt mehr', str_contains( $src, 'locationforecast/2.0/compact' ), false );
check( 'Open-Meteo: fehlender Wert bleibt null', str_contains( $src, "(int)(\$daily['precipitation_probability_max'][\$i]?? 0 )" ), false );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
