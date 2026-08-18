<?php
/**
 * Tests for the thermal arithmetic in NAWS_Astro.
 *
 * All functions under test are pure — no options, no clock, no database — so
 * they run without a WordPress bootstrap. What they guard:
 *
 *   wind_chill()        – NOAA 2001, metric. Must always come out below the
 *                         air temperature when it is cold and windy.
 *   feels_like()        – the three-regime switch. Before 2026-08 this was
 *                         Steadman across the whole range, which reported
 *                         cold windy weather as far milder than it feels.
 *   thermal_sensation() – the band a felt temperature falls into. The bands
 *                         are the source's, not invented here.
 *
 *   php tests/test-thermal.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-astro.php';

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

/** Vergleich mit Toleranz — schützt vor Bruch durch die letzte Nachkommastelle. */
function close( string $name, float $got, float $want, float $tol = 0.05 ): void {
    global $passed, $failed;
    if ( abs( $got - $want ) <= $tol ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %.3f (+-%.3f), ist %.3f\n", $name, $want, $tol, $got );
}

echo "\nNAWS_Astro::wind_chill()\n" . str_repeat( '-', 74 ) . "\n";

// T_wc = 13,12 + 0,6215*T - 11,37*v^0,16 + 0,3965*T*v^0,16   (NOAA 2001, metrisch)
close( '-5 C bei 20 km/h',   NAWS_Astro::wind_chill( -5.0, 20.0 ), -11.6 );
close( '0 C bei 30 km/h',    NAWS_Astro::wind_chill( 0.0, 30.0 ),   -6.5 );
close( '5 C bei 10 km/h',    NAWS_Astro::wind_chill( 5.0, 10.0 ),    2.7 );

// Eigenschaften, die immer gelten müssen — unabhängig von der Formel.
$a = NAWS_Astro::wind_chill( -5.0, 10.0 );
$b = NAWS_Astro::wind_chill( -5.0, 40.0 );
check( 'mehr Wind kuehlt staerker', $b < $a, true );
check( 'liegt unter der Lufttemperatur', NAWS_Astro::wind_chill( -5.0, 20.0 ) < -5.0, true );

echo "\nNAWS_Astro::feels_like() — Regime-Weiche\n" . str_repeat( '-', 74 ) . "\n";

// Kalt und windig -> Windchill
check( '-5 C, 80 %, 20 km/h -> Windchill',
    NAWS_Astro::feels_like( -5.0, 80.0, 20.0 ),
    NAWS_Astro::wind_chill( -5.0, 20.0 ) );

// Heiss und feucht -> Hitzeindex
check( '30 C, 60 %, 5 km/h -> Hitzeindex',
    NAWS_Astro::feels_like( 30.0, 60.0, 5.0 ),
    NAWS_Astro::heat_index( 30.0, 60.0 ) );

// Dazwischen -> Steadman
check( '15 C, 50 %, 10 km/h -> Steadman',
    NAWS_Astro::feels_like( 15.0, 50.0, 10.0 ),
    NAWS_Astro::apparent_temperature( 15.0, 50.0, 10.0 ) );

// Grenzfaelle: die Bedingungen sind < 10 und > 5 bzw. >= 27 und > 40.
check( 'genau 10 C ist kein Windchill',
    NAWS_Astro::feels_like( 10.0, 50.0, 20.0 ),
    NAWS_Astro::apparent_temperature( 10.0, 50.0, 20.0 ) );
check( 'genau 5 km/h ist kein Windchill',
    NAWS_Astro::feels_like( 5.0, 50.0, 5.0 ),
    NAWS_Astro::apparent_temperature( 5.0, 50.0, 5.0 ) );
check( 'genau 27 C bei 41 % ist Hitzeindex',
    NAWS_Astro::feels_like( 27.0, 41.0, 2.0 ),
    NAWS_Astro::heat_index( 27.0, 41.0 ) );
check( 'genau 40 % ist kein Hitzeindex',
    NAWS_Astro::feels_like( 27.0, 40.0, 2.0 ),
    NAWS_Astro::apparent_temperature( 27.0, 40.0, 2.0 ) );

// Der Fehler, der behoben wird: Steadman meldete Kaltwind zu mild.
check( 'Windchill ist kaelter als der alte Steadman-Wert',
    NAWS_Astro::feels_like( -5.0, 80.0, 25.0 ) < NAWS_Astro::apparent_temperature( -5.0, 80.0, 25.0 ),
    true );

echo "\nNAWS_Astro::thermal_sensation()\n" . str_repeat( '-', 74 ) . "\n";

check( '-11 -> sehr kalt',        NAWS_Astro::thermal_sensation( -11.0 ), 'sens_very_cold' );
check( '-10 -> kalt (Grenze)',    NAWS_Astro::thermal_sensation( -10.0 ), 'sens_cold' );
check( '-0.1 -> kalt',            NAWS_Astro::thermal_sensation( -0.1 ),  'sens_cold' );
check( '0 -> kuehl (Grenze)',     NAWS_Astro::thermal_sensation( 0.0 ),   'sens_cool' );
check( '9.9 -> kuehl',            NAWS_Astro::thermal_sensation( 9.9 ),   'sens_cool' );
check( '10 -> angenehm kuehl',    NAWS_Astro::thermal_sensation( 10.0 ),  'sens_pleasantly_cool' );
check( '20 -> angenehm',          NAWS_Astro::thermal_sensation( 20.0 ),  'sens_pleasant' );
check( '24.9 -> angenehm',        NAWS_Astro::thermal_sensation( 24.9 ),  'sens_pleasant' );
check( '25 -> warm',              NAWS_Astro::thermal_sensation( 25.0 ),  'sens_warm' );
check( '32 -> heiss',             NAWS_Astro::thermal_sensation( 32.0 ),  'sens_hot' );
check( '39.9 -> heiss',           NAWS_Astro::thermal_sensation( 39.9 ),  'sens_hot' );
check( '40 -> extrem heiss',      NAWS_Astro::thermal_sensation( 40.0 ),  'sens_extremely_hot' );
check( '55 -> extrem heiss',      NAWS_Astro::thermal_sensation( 55.0 ),  'sens_extremely_hot' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
