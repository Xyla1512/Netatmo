<?php
/**
 * Tests for the polling arithmetic in NAWS_Cron.
 *
 * All three functions under test are pure — no options, no clock — so they run
 * without a WordPress bootstrap. What they guard:
 *
 *   normalise_interval() – wp_schedule_event() fails silently on a schedule key
 *                          that was never registered, which would leave a site
 *                          with no fetch cron at all.
 *   backoff_interval()   – the error backoff must never shorten the interval.
 *                          Capping at 60 minutes used to make a 120-minute
 *                          setting poll twice as often after an error.
 *   should_skip()        – night mode has to skip exactly every other run.
 *
 *   php tests/test-cron-polling.php
 *
 * @package NAWS
 * @since   1.9.2
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

require_once __DIR__ . '/../includes/class-naws-cron.php';

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

echo "\nNAWS_Cron::normalise_interval()\n" . str_repeat( '-', 74 ) . "\n";

foreach ( [
    [ 'Standardwert bleibt',              10,      10  ],
    [ 'Untergrenze bleibt',               5,       5   ],
    [ 'Obergrenze bleibt',                120,     120 ],
    [ 'jeder Listenwert bleibt (15)',     15,      15  ],
    [ 'jeder Listenwert bleibt (20)',     20,      20  ],
    [ 'jeder Listenwert bleibt (30)',     30,      30  ],
    [ 'jeder Listenwert bleibt (60)',     60,      60  ],
    [ '7 -> naechster Wert 5',            7,       5   ],
    [ '8 -> naechster Wert 10',           8,       10  ],
    [ '45 -> Gleichstand geht nach oben', 45,      60  ],
    [ '90 -> Gleichstand geht nach oben', 90,      120 ],
    [ 'zu klein -> Untergrenze',          1,       5   ],
    [ 'zu gross -> Obergrenze',           9999,    120 ],
    [ 'null -> Standard',                 null,    10  ],
    [ 'leerer String -> Standard',        '',      10  ],
    [ 'Unsinn -> Standard',               'oft',   10  ],
    [ 'Wert 0 -> Standard',               0,       10  ],
    [ 'negativ -> Standard',              -30,     10  ],
    [ 'Zahl als String',                  '30',    30  ],
    [ 'Kommazahl wird abgeschnitten',     29.9,    30  ],
] as list( $name, $in, $want ) ) {
    check( $name, NAWS_Cron::normalise_interval( $in ), $want );
}

echo "\nNAWS_Cron::backoff_interval()\n" . str_repeat( '-', 74 ) . "\n";

foreach ( [
    [ '5 verdoppelt auf 10',                        5,    10  ],
    [ '10 verdoppelt auf 20',                       10,   20  ],
    [ '15 verdoppelt auf 30',                       15,   30  ],
    [ '20 -> 40 gibt es nicht, aufrunden auf 60',   20,   60  ],
    [ '30 verdoppelt auf 60',                       30,   60  ],
    [ '60 verdoppelt auf 120',                      60,   120 ],
    [ '120 bleibt 120 (nichts Laengeres vorhanden)', 120, 120 ],
    [ 'unnormierter Eingabewert wird gesnappt',     45,   120 ],
    [ 'Unsinn faellt auf den Standard zurueck',     'x',  20  ],
] as list( $name, $in, $want ) ) {
    check( $name, NAWS_Cron::backoff_interval( $in ), $want );
}

// Die eigentliche Regression: das Backoff darf nie haeufiger pollen lassen.
foreach ( NAWS_Cron::INTERVALS as $base ) {
    check(
        sprintf( 'Backoff verkuerzt %d min nicht', $base ),
        NAWS_Cron::backoff_interval( $base ) >= $base,
        true
    );
}

echo "\nNAWS_Cron::should_skip()\n" . str_repeat( '-', 74 ) . "\n";

$base = 10 * 60; // 10 Minuten

foreach ( [
    [ 'ausserhalb des Nachtfensters nie',      1000,  1000 - 60,        $base, false, false ],
    [ 'ohne vorherigen Versuch nie',           1000,  0,                $base, true,  false ],
    [ 'gerade eben gelaufen -> ueberspringen', 1000,  1000,             $base, true,  true  ],
    [ 'nach 1x Intervall -> ueberspringen',    1000,  1000 - $base,     $base, true,  true  ],
    [ 'knapp unter 1,5x -> ueberspringen',     1000,  1000 - 899,       $base, true,  true  ],
    [ 'genau 1,5x -> laufen lassen',           1000,  1000 - 900,       $base, true,  false ],
    [ 'nach 2x Intervall -> laufen lassen',    1000,  1000 - 2 * $base, $base, true,  false ],
    [ 'lange Pause -> laufen lassen',          9999,  1,                $base, true,  false ],
] as list( $name, $now, $last, $b, $night, $want ) ) {
    check( $name, NAWS_Cron::should_skip( $now, $last, $b, $night ), $want );
}

/**
 * Spielt $ticks faellige Cron-Laeufe durch und zaehlt, wie viele davon
 * tatsaechlich abfragen. $drift bildet ab, dass WP-Cron regelmaessig zu spaet
 * feuert. Der Startzeitpunkt ist ein echter Unix-Zeitstempel, denn 0 heisst
 * "noch nie gelaufen" und wuerde den ersten Tick verfaelschen.
 */
function simulate( int $ticks, int $base, bool $night, float $drift = 1.0 ): int {
    $t0           = 1786205132;
    $last_attempt = 0;
    $ran          = 0;
    for ( $tick = 0; $tick < $ticks; $tick++ ) {
        $now = $t0 + intval( $tick * $base * $drift );
        if ( NAWS_Cron::should_skip( $now, $last_attempt, $base, $night ) ) {
            continue;
        }
        $ran++;
        $last_attempt = $now; // record_success()/record_error() setzen last_attempt
    }
    return $ran;
}

// Nachts darf genau jeder zweite Tick abfragen — das ist die Verdopplung.
check( '12 Ticks nachts -> 6 echte Abfragen',        simulate( 12, $base, true ),       6  );
check( '12 verspaetete Ticks -> 6 echte Abfragen',   simulate( 12, $base, true, 1.2 ),  6  );
check( '13 Ticks nachts -> 7 echte Abfragen',        simulate( 13, $base, true ),       7  );
check( '12 Ticks tagsueber -> 12 echte Abfragen',    simulate( 12, $base, false ),      12 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
