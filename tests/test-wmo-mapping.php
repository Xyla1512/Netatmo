<?php
/**
 * Tests for NAWS_Weather_State::wmo_to_state().
 *
 * The mapping feeds three separate places (widget, [naws_forecast], the
 * dashboard forecast strip). If it drifts, they disagree with each other
 * and with the live icon, which is exactly what this file prevents.
 *
 *   php tests/test-wmo-mapping.php
 *
 * @package NAWS
 * @since   1.8.0
 */
define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../includes/class-naws-astro.php';
require_once __DIR__ . '/../includes/class-naws-weather-state.php';

$passed = 0;
$failed = 0;

function check( int $wmo, bool $is_day, string $expected, string $why ): void {
    global $passed, $failed;
    $actual = NAWS_Weather_State::wmo_to_state( $wmo, $is_day );
    if ( $actual === $expected ) {
        $passed++;
        printf( "  ok    WMO %-3d %-9s -> %-12s %s\n", $wmo, $is_day ? 'Tag' : 'Nacht', $actual === '' ? '(keins)' : $actual, $why );
        return;
    }
    $failed++;
    printf( "  FAIL  WMO %-3d %-9s -> erwartet '%s', bekommen '%s'\n", $wmo, $is_day ? 'Tag' : 'Nacht', $expected, $actual );
}

echo "\nNAWS_Weather_State::wmo_to_state()\n" . str_repeat( '-', 74 ) . "\n";

echo "\nJe ein Code aus jeder Gruppe\n";
check( 0,  true,  'clear_day',  'klar' );
check( 1,  true,  'fair',       'heiter' );
check( 2,  true,  'partly',     'teilweise bewoelkt' );
check( 3,  true,  'overcast',   'bedeckt' );
check( 45, true,  'fog',        'Nebel' );
check( 63, true,  'rain',       'Regen' );
check( 65, true,  'rain_heavy', 'Starkregen' );
check( 73, true,  'snow',       'Schnee' );
check( 68, true,  'sleet',      'Schneeregen' );
check( 95, true,  'thunder',    'Gewitter' );
check( 96, true,  'sleet',      'Gewitter mit Hagel' );

echo "\nZusammenfallende Codes\n";
foreach ( [ 51, 53, 55, 61, 80, 81 ] as $c ) { check( $c, true, 'rain', 'faellt auf Regen zusammen' ); }
foreach ( [ 71, 75, 77, 85, 86 ] as $c )     { check( $c, true, 'snow', 'faellt auf Schnee zusammen' ); }
foreach ( [ 66, 67, 69 ] as $c )             { check( $c, true, 'sleet', 'gefrierend/Schneeregen' ); }
check( 82, true, 'rain_heavy', 'starker Schauer' );
check( 48, true, 'fog',        'Raureifnebel' );

echo "\nTag und Nacht\n";
check( 0, false, 'clear_night', 'nur klar hat eine Nachtvariante' );
check( 3, false, 'overcast',    'bedeckt bleibt bedeckt' );
check( 63, false, 'rain',       'Regen bleibt Regen' );

echo "\nUnbekannt\n";
check( 4,   true, '', 'kein Icon statt eines falschen' );
check( 999, true, '', 'kein Icon statt eines falschen' );
check( -1,  true, '', 'kein Icon statt eines falschen' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
