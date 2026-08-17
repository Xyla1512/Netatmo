<?php
/**
 * Precedence-table tests for NAWS_Weather_State::decide().
 *
 * The plugin has no test suite and introducing PHPUnit for this one class
 * would be disproportionate. decide() is a pure function, so a plain PHP
 * script covers the whole table:
 *
 *   php tests/test-weather-state.php
 *
 * Exits with status 1 if any case fails, so it can be wired into CI later.
 *
 * @package NAWS
 * @since   1.7.0
 */

// The classes guard against direct access; this runner is the exception.
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-astro.php';
require_once __DIR__ . '/../includes/class-naws-weather-state.php';

$passed = 0;
$failed = 0;

/**
 * @param string $name     What the case demonstrates.
 * @param array  $input    Partial decide() input; defaults fill the rest.
 * @param string $expected Expected state name.
 */
function check( string $name, array $input, string $expected ): void {
    global $passed, $failed;

    $result = NAWS_Weather_State::decide( $input + [
        'rain'        => null,
        'wind'        => null,
        'temp'        => null,
        'humidity'    => null,
        'wmo'         => null,
        'snowfall'    => null,
        'cloud_total' => null,
        'cloud_low'   => null,
        'cloud_mid'   => null,
        'cloud_high'  => null,
        'is_day'      => true,
        'stale'       => false,
        'ts'          => 1786122303,
    ] );

    $actual = $result['state'];
    if ( $actual === $expected ) {
        $passed++;
        printf( "  ok    %-52s -> %s\n", $name, $actual === '' ? '(kein Icon)' : $actual );
        return;
    }

    $failed++;
    printf( "  FAIL  %-52s -> erwartet '%s', bekommen '%s'\n", $name, $expected, $actual );
}

echo "\nNAWS_Weather_State::decide() – Rangfolge\n";
echo str_repeat( '-', 78 ) . "\n";

// Reference wet-bulb values used below (verified against the formula):
//   T  3.5 / rF 30  -> tw -1.6      T  3.5 / rF 95  -> tw  2.9
//   T  1.0 / rF 100 -> tw  0.9      T -3.0 / rF 90  -> tw -3.9
//   T 12.0 / rF 60  -> tw  9.0      T  8.0 / rF 99  -> tw  7.9

echo "\nJeder Rang einzeln\n";
check( 'Rang 1  Sturm',                 [ 'wind' => 90.0 ], 'storm' );
check( 'Rang 2  Gewitter',              [ 'wmo' => 95 ], 'thunder' );
check( 'Rang 2  Hagel',                 [ 'wmo' => 96 ], 'sleet' );
check( 'Rang 3  Schneecode + kalt',     [ 'wmo' => 71, 'temp' => 3.5, 'humidity' => 30.0 ], 'snow' );
check( 'Rang 4  Regen + kalt',          [ 'rain' => 0.6, 'temp' => 1.0, 'humidity' => 100.0 ], 'snow' );
check( 'Rang 5  Starkregen',            [ 'rain' => 6.0, 'temp' => 12.0, 'humidity' => 60.0 ], 'rain_heavy' );
check( 'Rang 6  Regen',                 [ 'rain' => 0.8, 'temp' => 12.0, 'humidity' => 60.0 ], 'rain' );
check( 'Rang 7  Nebel',                 [ 'rain' => 0.0, 'temp' => 8.0, 'humidity' => 99.0 ], 'fog' );
check( 'Rang 8  API-Regen ohne Messer',  [ 'wmo' => 63 ], 'rain' );
check( 'Rang 8  API-Starkregen',        [ 'wmo' => 65 ], 'rain_heavy' );
check( 'Rang 8  API-Schneeregen (68)',  [ 'wmo' => 68 ], 'sleet' );
check( 'Rang 9  API-Nebel ohne Modul',  [ 'wmo' => 45 ], 'fog' );
check( 'Rang 10 klar, Tag',             [ 'wmo' => 0, 'is_day' => true ], 'clear_day' );
check( 'Rang 10 klar, Nacht',           [ 'wmo' => 0, 'is_day' => false ], 'clear_night' );
check( 'Rang 10 heiter',                [ 'wmo' => 1 ], 'fair' );
check( 'Rang 10 teilweise bewoelkt',    [ 'wmo' => 2 ], 'partly' );
check( 'Rang 10 bedeckt',               [ 'wmo' => 3 ], 'overcast' );
check( 'Rang 11 nichts bekannt',        [], '' );

echo "\nVorrang bei Widerspruch\n";
check( 'Regen laeuft, API meldet klar',  [ 'rain' => 0.8, 'temp' => 12.0, 'humidity' => 60.0, 'wmo' => 0 ], 'rain' );
check( 'Sturm bei gleichzeitigem Regen', [ 'wind' => 90.0, 'rain' => 6.0, 'temp' => 12.0, 'humidity' => 60.0 ], 'storm' );
check( 'Gewitter schlaegt Regenmesser',  [ 'wmo' => 95, 'rain' => 0.5, 'temp' => 12.0, 'humidity' => 60.0 ], 'thunder' );

echo "\nnull ist nicht 0.0 – der Kern der Raenge 8 und 9\n";
check( 'kein Messer (null) + WMO 63',    [ 'wmo' => 63 ], 'rain' );
check( 'Messer misst 0.0 + WMO 63',      [ 'rain' => 0.0, 'temp' => 12.0, 'humidity' => 60.0, 'wmo' => 63 ], 'overcast' );
check( 'keine rF (null) + WMO 45',       [ 'wmo' => 45 ], 'fog' );
check( 'rF vorhanden, zu trocken + 45',  [ 'temp' => 12.0, 'humidity' => 60.0, 'wmo' => 45 ], 'overcast' );

echo "\nFeuchtkugel statt Lufttemperatur\n";
check( '3.5 C bei 30% rF  -> tw -1.6',   [ 'wmo' => 71, 'temp' => 3.5, 'humidity' => 30.0 ], 'snow' );
check( '3.5 C bei 95% rF  -> tw  2.9',   [ 'wmo' => 71, 'temp' => 3.5, 'humidity' => 95.0 ], 'overcast' );
check( 'Schnee ueber snowfall ohne Code', [ 'snowfall' => 0.6, 'temp' => 1.0, 'humidity' => 100.0 ], 'snow' );

echo "\nVorfilter F – Schmelzwasser\n";
check( 'Regen 0.4 bei tw -3.9, kein Code', [ 'rain' => 0.4, 'temp' => -3.0, 'humidity' => 90.0 ], '' );
check( 'Regen 0.4 bei tw -3.9 + WMO 63',   [ 'rain' => 0.4, 'temp' => -3.0, 'humidity' => 90.0, 'wmo' => 63 ], 'rain' );
check( 'Regen 0.6 bei tw  0.9 (knapp)',    [ 'rain' => 0.6, 'temp' => 1.0, 'humidity' => 100.0 ], 'snow' );

echo "\nFehlende Module degradieren einzeln\n";
check( 'kein Windmesser, Rang 1 entfaellt', [ 'wmo' => 3 ], 'overcast' );
check( 'kein Aussenmodul, Rang 7 entfaellt', [ 'rain' => 0.0, 'wmo' => 45 ], 'fog' );
check( 'weder Station noch API',             [], '' );

// Layered cloud cover. Every case below is a real Open-Meteo response for
// Leipzig on 2026-08-09, the day the bug was reported: weather_code said 3
// ("bedeckt") while low and mid cover were flat 0 all morning and the whole
// figure was cirrus, which reads as a blue sky from the ground.
//
//   eff = max( low, mid, high * 0.4 )
//   < 12.5 clear · < 37.5 fair · < 75 partly · sonst overcast
echo "\nBewoelkung aus den Wolkenschichten – schlaegt den WMO-Sammelcode\n";
$cl = static fn( $low, $mid, $high ): array => [
    'cloud_total' => (float) max( $low, $mid, $high ),
    'cloud_low'   => (float) $low,
    'cloud_mid'   => (float) $mid,
    'cloud_high'  => (float) $high,
];

check( 'Leipzig 08:45  nur Cirren 72, Code 3',  [ 'wmo' => 3 ] + $cl( 0, 0, 72 ),   'fair' );
check( 'Leipzig 09:00  nur Cirren 83, Code 3',  [ 'wmo' => 3 ] + $cl( 0, 0, 83 ),   'fair' );
check( 'Leipzig 13:00  nur Cirren 100, Code 3', [ 'wmo' => 3 ] + $cl( 0, 0, 100 ),  'partly' );
check( 'dieselbe Menge, aber tiefe Wolken',     [ 'wmo' => 3 ] + $cl( 83, 0, 0 ),   'overcast' );
check( 'mittelhohe Decke 72 + Cirren 100',      [ 'wmo' => 3 ] + $cl( 0, 72, 100 ), 'partly' );
check( 'wolkenlos -> clear_day',                [ 'wmo' => 3 ] + $cl( 0, 0, 0 ),    'clear_day' );
check( 'wolkenlos nachts -> clear_night',       [ 'wmo' => 3, 'is_day' => false ] + $cl( 0, 0, 0 ), 'clear_night' );

echo "\nSchwellen der Bewoelkung (Achtel: 12.5 / 37.5 / 75)\n";
check( 'Cirren 31   -> eff 12.4, noch klar',    [ 'wmo' => 3 ] + $cl( 0, 0, 31 ),   'clear_day' );
check( 'tief 12.5   -> Grenze zaehlt nach oben', [ 'wmo' => 0 ] + $cl( 12.5, 0, 0 ), 'fair' );
check( 'tief 37.5   -> Grenze zaehlt nach oben', [ 'wmo' => 0 ] + $cl( 37.5, 0, 0 ), 'partly' );
check( 'tief 75     -> Grenze zaehlt nach oben', [ 'wmo' => 0 ] + $cl( 75, 0, 0 ),  'overcast' );

echo "\nDegradation der Wolkendaten\n";
check( 'nur Gesamtwert (Yr.no compact) 72',     [ 'wmo' => 3, 'cloud_total' => 72.0 ], 'partly' );
check( 'unvollstaendige Schichten -> Gesamtwert', [ 'wmo' => 3, 'cloud_total' => 83.0, 'cloud_high' => 83.0 ], 'overcast' );
check( 'gar keine Wolkendaten -> WMO-Code',     [ 'wmo' => 3 ], 'overcast' );

echo "\nWolkendaten aendern nur die Raenge 0-3\n";
check( 'Regen laeuft, Himmel wolkenlos gemeldet', [ 'rain' => 0.8, 'temp' => 12.0, 'humidity' => 60.0, 'wmo' => 3 ] + $cl( 0, 0, 0 ), 'rain' );
check( 'Messer 0.0 + WMO 63 bleibt bedeckt',      [ 'rain' => 0.0, 'temp' => 12.0, 'humidity' => 60.0, 'wmo' => 63 ] + $cl( 0, 0, 83 ), 'overcast' );
check( 'Gewitter schlaegt Wolkendaten',           [ 'wmo' => 95 ] + $cl( 0, 0, 0 ), 'thunder' );

echo "\nTag/Nacht nur bei klar\n";
check( 'Nacht + WMO 3 bleibt bedeckt',   [ 'wmo' => 3, 'is_day' => false ], 'overcast' );
check( 'Nacht + WMO 0 wird clear_night', [ 'wmo' => 0, 'is_day' => false ], 'clear_night' );

echo "\n" . str_repeat( '-', 78 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );

exit( $failed > 0 ? 1 : 0 );
