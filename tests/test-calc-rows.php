<?php
/**
 * Tests for the row-selection and period helpers in NAWS_Calc.
 *
 * These pin down the rule the spec calls its most important one: "nobody
 * measured this" and "it happened on zero days" must never look alike.
 *
 * A row in naws_daily_summary exists as soon as ANY column has a value, so
 * a day with only a pressure reading still produces a row whose temperatures
 * are all NULL. Deciding "no data" by row existence therefore turned an
 * unmeasured stretch into a confident "0 frost days" — the reference
 * installation has exactly such a stretch, 28 consecutive rows without a
 * single temperature (2024-03-28 to 2024-04-24). rows_with() filters on the
 * columns a value actually needs, before the fallback check.
 *
 * The daily table is faked, so this runs without a WordPress bootstrap and
 * without a database. Only the clock-free branches are exercised: 'month'
 * stands in for a relative period because the Nd branch reads time()
 * directly, which would hang the expectations on the day the suite runs.
 *
 *   php tests/test-calc-rows.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

/** Fixed clock, so every "up to today" range is reproducible. */
define( 'NAWS_TEST_TODAY', '2026-08-19' );

// ── Minimal WordPress surface ────────────────────────────────────────────
function wp_date( $format, $timestamp = null ) {
    return gmdate( $format, $timestamp ?? strtotime( NAWS_TEST_TODAY . ' 12:00:00' ) );
}
function get_option( $key, $default = false ) {
    return $key === 'naws_settings' ? [] : $default;
}
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function naws__( $k ) { return $k; }

/**
 * The daily table, as rows keyed by day.
 *
 * [ 'Y-m-d' => [ temp_min, temp_max, temp_avg ] ] — null stands for a row
 * that exists but carries nothing in that column.
 */
function naws_test_block( string $from, int $days, $min, $max, $avg ): array {
    $out = [];
    $ts  = strtotime( $from . ' 12:00:00' );
    for ( $i = 0; $i < $days; $i++ ) {
        $out[ gmdate( 'Y-m-d', $ts + $i * DAY_IN_SECONDS ) ] = [ $min, $max, $avg ];
    }
    return $out;
}

$GLOBALS['naws_test_days'] =
      naws_test_block( '2023-01-01', 31, null, null, null )   // ein ganzes Jahr ohne Messung
    + naws_test_block( '2024-12-01',  5, -4.0, null, null )   // nur ein Minimum abgelesen
    + naws_test_block( '2025-01-01',  5, -3.0,  2.0, -0.5 )   // 5 Frosttage am Stueck
    + naws_test_block( '2025-01-06',  3, null, null, null )   // Luecke mitten in der Serie
    + naws_test_block( '2025-01-09',  2, -1.0,  3.0,  1.0 )   // 2 weitere Frosttage
    + naws_test_block( '2025-02-01', 28, null, null, null )   // das Muster aus der Anlage
    + naws_test_block( '2025-07-01',  5, 12.0, 25.0, 18.0 )   // Sommer, kein Frost
    + naws_test_block( '2026-01-01', 10, -2.0,  4.0,  1.0 );

class NAWS_Database {
    public static function get_modules( $active_only = false ): array {
        return [ [
            'module_id'   => '70:ee:50:00:00:01',
            'station_id'  => '70:ee:50:00:00:01',
            'module_type' => 'NAMain',
        ] ];
    }

    /** Mirrors the real query: day rows in range, ascending by date. */
    public static function get_daily_summaries( array $args ): array {
        $days = $GLOBALS['naws_test_days'];
        ksort( $days );

        $out = [];
        foreach ( $days as $date => $t ) {
            if ( $date < $args['date_from'] || $date > $args['date_to'] ) {
                continue;
            }
            $out[] = [
                'module_id'  => '70:ee:50:00:00:01',
                'station_id' => '70:ee:50:00:00:01',
                'day_date'   => $date,
                'temp_min'   => $t[0],
                'temp_max'   => $t[1],
                'temp_avg'   => $t[2],
            ];
        }
        return $out;
    }
}

require_once __DIR__ . '/../includes/class-naws-climate.php';
require_once __DIR__ . '/../includes/class-naws-calc.php';

/** Calls a private static of NAWS_Calc. */
function calc( string $method, ...$args ) {
    $m = new ReflectionMethod( 'NAWS_Calc', $method );
    if ( PHP_VERSION_ID < 80100 ) {
        $m->setAccessible( true );
    }
    return $m->invoke( null, ...$args );
}

/** The rows the daily table would hand a value, unfiltered. */
function raw_rows( array $atts ): array {
    return calc( 'daily_rows', $atts, [ 'temp_min', 'temp_max', 'temp_avg' ] );
}

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

echo "\nNAWS_Calc – Zeilenauswahl (needs/rows_with), Zeitraum-Fixierung, coverage()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── rows_with(): welche Zeilen als gemessen gelten ───────────────────────
$rows_2025 = raw_rows( [ 'year' => 2025 ] );
$rows_2024 = raw_rows( [ 'year' => 2024 ] );

check( 'die Testtabelle liefert 2025 alle 43 Zeilen', count( $rows_2025 ), 43 );
check( 'leeres needs laesst jede Zeile stehen',
    count( calc( 'rows_with', $rows_2025, [] ) ), 43 );
check( 'temp_min-Bedarf wirft die temperaturlosen Zeilen raus',
    count( calc( 'rows_with', $rows_2025, [ 'temp_min' ] ) ), 12 );
check( 'zwei Pflichtspalten: ein abgelesenes Minimum reicht nicht',
    count( calc( 'rows_with', $rows_2024, [ 'temp_min', 'temp_max' ] ) ), 0 );
check( 'dieselben Zeilen genuegen dem Bedarf nach nur temp_min',
    count( calc( 'rows_with', $rows_2024, [ 'temp_min' ] ) ), 5 );

// coverage() nimmt $rows[0] als fruehesten Tag — das haelt nur, solange
// gefiltert wird, ohne umzusortieren oder Luecken in den Schluesseln zu lassen.
$filtered = calc( 'rows_with', $rows_2025, [ 'temp_min' ] );
check( 'die Schluessel bleiben luckenlos ab 0',
    array_keys( $filtered ), range( 0, 11 ) );
check( 'die erste gefilterte Zeile ist der fruehste gemessene Tag',
    $filtered[0]['day_date'], '2025-01-01' );
check( 'die letzte ist der spaeteste',
    $filtered[11]['day_date'], '2025-07-05' );

// ── normalise_period_atts(): der Zeitraum, ueber den ein Wert definiert ist
check( 'glts erzwingt period=year',
    calc( 'normalise_period_atts', 'glts', [ 'period' => 'month' ] )['period'], 'year' );
check( 'glts_start ebenso',
    calc( 'normalise_period_atts', 'glts_start', [ 'period' => 'month' ] )['period'], 'year' );
check( 'ein anderer Schluessel behaelt seinen Zeitraum',
    calc( 'normalise_period_atts', 'hdd', [ 'period' => 'month' ] )['period'], 'month' );
check( 'ein ausdrueckliches Jahr ueberlebt die Fixierung',
    calc( 'normalise_period_atts', 'glts', [ 'period' => 'month', 'year' => '2025' ] ),
    [ 'period' => 'year', 'year' => '2025' ] );

// ── raw_dayclass(): Fallback gegen echte Null ────────────────────────────
// Der Kern: 2023 hat 31 Zeilen, aber keine einzige Temperatur.
check( 'nur temperaturlose Zeilen ergeben den Fallback, nicht 0.0',
    calc( 'raw_dayclass', 'frost_days', [ 'year' => 2023 ] ), null );
check( 'Zeilen ohne Treffer ergeben dagegen echte 0.0',
    calc( 'raw_dayclass', 'ice_days', [ 'year' => 2025 ] ), 0.0 );
check( 'Treffer werden gezaehlt',
    calc( 'raw_dayclass', 'frost_days', [ 'year' => 2025 ] ), 7.0 );

// Gleicher Zeitraum, verschiedener Bedarf: die 5 Dezembertage tragen ein
// Minimum, aber kein Maximum. Frosttage zaehlen sie, Wachstumsgradtage nicht.
check( 'frost_days zaehlt die Zeilen mit blossem Minimum',
    calc( 'raw_dayclass', 'frost_days', [ 'year' => 2024 ] ), 5.0 );
check( 'gdd faellt im selben Zeitraum zurueck, weil temp_max fehlt',
    calc( 'raw_sum', 'gdd', [ 'year' => 2024 ] ), null );

// Der Filter darf die Serienlogik nicht verschieben: eine entfernte Zeile
// muss dieselbe Luecke erzeugen wie eine als Nicht-Treffer gewertete.
$frost = static function ( array $row ): bool {
    return $row['temp_min'] !== null && (float) $row['temp_min'] < 0.0;
};
check( 'laengste Serie 2025 ueber die gefilterten Zeilen',
    calc( 'raw_dayclass', 'frost_days', [ 'year' => 2025, 'mode' => 'max_streak' ] ), 5.0 );
check( 'ungefiltert kaeme dieselbe Serie heraus',
    (float) NAWS_Climate::max_streak( $rows_2025, $frost ), 5.0 );
check( 'und dieselbe Anzahl',
    (float) NAWS_Climate::count_days( $rows_2025, $frost ), 7.0 );

// ── coverage(): der Nenner der note="1"-Angabe ───────────────────────────
check( 'Momentanwerte haben keine Abdeckung',
    NAWS_Calc::coverage( 'feels_like', [] ), null );

// 2025 hat 43 Zeilen, aber nur 12 mit Temperaturen. Vor dem Filter haette
// der Nenner die 31 leeren Zeilen als gemessen ausgegeben.
check( 'Jahr: gezaehlt werden gemessene Tage, nicht vorhandene Zeilen',
    NAWS_Calc::coverage( 'frost_days', [ 'year' => 2025 ] ),
    [ 'rows' => 12, 'days' => 365 ] );

// period="all" loest in period_range() zu 1900-01-01 auf — als Nenner waeren
// das ueber 46000 Tage. Der Anker ist stattdessen der erste gemessene Tag,
// hier der 1. Dezember 2024, bis zum festen Heute.
check( 'period=all ankert am ersten gemessenen Tag statt an 1900',
    NAWS_Calc::coverage( 'frost_days', [ 'period' => 'all' ] ),
    [ 'rows' => 27, 'days' => 627 ] );

// Ohne eine einzige gemessene Zeile gibt es keinen Anker — dann ist die
// ehrliche Antwort 0 von 0, nicht 0 von 46000.
$alle_tage = $GLOBALS['naws_test_days'];
$GLOBALS['naws_test_days'] = naws_test_block( '2025-03-01', 10, null, null, null );
check( 'period=all ohne gemessene Zeile ergibt 0 von 0',
    NAWS_Calc::coverage( 'frost_days', [ 'period' => 'all' ] ),
    [ 'rows' => 0, 'days' => 0 ] );
$GLOBALS['naws_test_days'] = $alle_tage;

// Die Notiz muss denselben Zeitraum beschreiben, ueber den gerechnet wurde:
// glts rechnet seit dem 1. Januar, auch wenn period etwas anderes sagt.
check( 'glts spiegelt seinen eigenen Zeitraum, nicht das Attribut',
    NAWS_Calc::coverage( 'glts', [ 'period' => 'month' ] ),
    [ 'rows' => 10, 'days' => 231 ] );
check( 'ein Wert ohne Fixierung folgt dem Attribut',
    NAWS_Calc::coverage( 'hdd', [ 'period' => 'month' ] ),
    [ 'rows' => 0, 'days' => 19 ] );
check( 'und faellt ohne Zeilen im Zeitraum zurueck',
    calc( 'raw_sum', 'hdd', [ 'period' => 'month' ] ), null );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
