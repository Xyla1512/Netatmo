<?php
/**
 * [naws_table] nach Tagen: sieben Kalendertage, Ortszeit, feste Reihenfolge.
 *
 * Die Fehler (Durchsicht der Seite, November 2026):
 *   - "sieben Tage" zeigte acht: period="7d" hiess "jetzt minus 7 x 24 h",
 *     das beruehrt einen angefangenen achten Tag. Die Tage wurden ausserdem
 *     nach UTC getrennt, beschriftet aber nach Ortszeit.
 *   - Temperatur und Feuchte wechselten je Tag die Reihenfolge: sortiert wurde
 *     nur nach der Zeit, gleich alte Zeilen kamen in Zufallsfolge.
 *
 *   php tests/test-table-days.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
function get_option( $k, $d = false ) { return $d; }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$berlin = static fn( string $s ): int => ( new DateTimeImmutable( $s, new DateTimeZone( 'Europe/Berlin' ) ) )->getTimestamp();

echo "\nKalendertage\n" . str_repeat( '-', 74 ) . "\n";
$now = $berlin( '2026-11-13 14:00:00' );
check( '7 Tage = ab Mitternacht vor 6 Tagen', NAWS_Helpers::calendar_start( 7, $now ), $berlin( '2026-11-07 00:00:00' ) );
check( '1 Tag = heute ab Mitternacht', NAWS_Helpers::calendar_start( 1, $now ), $berlin( '2026-11-13 00:00:00' ) );
check( 'ueber die Zeitumstellung (Ende Oktober)', NAWS_Helpers::calendar_start( 7, $berlin( '2026-10-29 09:00:00' ) ), $berlin( '2026-10-23 00:00:00' ) );
check( 'Tage aus "7d"', NAWS_Helpers::period_days( '7d' ), 7 );
check( 'Tage aus "2w"', NAWS_Helpers::period_days( '2w' ), 14 );
check( 'kein Tageszeitraum', NAWS_Helpers::period_days( '24h' ), null );
check( 'riesige Zahl: begrenzt auf 3660 Tage', NAWS_Helpers::calendar_start( PHP_INT_MAX, $now ), NAWS_Helpers::calendar_start( 3660, $now ) );
check( 'riesiger Zeitraum im Shortcode: keine Tageszahl', NAWS_Helpers::period_days( '99999999999999999999d' ), null );
check( '0 Tage wie 1 Tag', NAWS_Helpers::calendar_start( 0, $now ), $berlin( '2026-11-13 00:00:00' ) );

echo "\nTagesgrenzen ueber die Zeitumstellung\n" . str_repeat( '-', 74 ) . "\n";
// Jeder Messwert braucht den Versatz, der zu SEINER Zeit galt — nicht den
// von heute. Sonst landen im Sommer abgefragte Wintertage auf 23:00 des
// Vortags (Gesamtpruefung des Fehlerbuendels).
$seg = NAWS_Helpers::tz_segments( $berlin( '2027-03-20 00:00:00' ), $berlin( '2027-04-02 00:00:00' ) );
check( 'zwei Abschnitte um die Umstellung im Maerz', count( $seg ), 2 );
check( 'Winterzeit bis 28.03. 01:00 UTC', $seg[0], [ gmmktime( 1, 0, 0, 3, 28, 2027 ), 3600 ] );
check( 'danach Sommerzeit', $seg[1], [ PHP_INT_MAX, 7200 ] );
$day = static fn( int $t ) => NAWS_Helpers::local_day_start( $t, $seg );
check( 'Wintertag behaelt sein Datum', gmdate( 'Y-m-d H:i', $day( $berlin( '2027-03-26 12:00:00' ) ) ), '2027-03-25 23:00' ); // = 26.03. 00:00 MEZ
check( 'Sommertag behaelt sein Datum', gmdate( 'Y-m-d H:i', $day( $berlin( '2027-03-30 12:00:00' ) ) ), '2027-03-29 22:00' ); // = 30.03. 00:00 MESZ
check( 'kurz nach Mitternacht Sommerzeit', $day( $berlin( '2027-03-30 00:30:00' ) ), $berlin( '2027-03-30 00:00:00' ) );
$sql = NAWS_Helpers::local_day_bucket_sql( 'r.recorded_at', $seg );
check( 'SQL: Versatz je Zeile per CASE', str_contains( $sql, 'CASE WHEN r.recorded_at < ' . gmmktime( 1, 0, 0, 3, 28, 2027 ) . ' THEN 3600 ELSE 7200 END' ), true );
check( 'SQL: nur Zahlen', (bool) preg_match( '/^[\sA-Za-z_.()<>+\-*\/0-9]+$/', $sql ), true );
check( 'ohne Umstellung: kein CASE', str_contains( NAWS_Helpers::local_day_bucket_sql( 'r.recorded_at', [ [ PHP_INT_MAX, 3600 ] ] ), 'CASE' ), false );

echo "\nAbfrage und Shortcode\n" . str_repeat( '-', 74 ) . "\n";
$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'gruppiert: Zeit, Modul, Messgroesse', str_contains( $db, 'ORDER BY recorded_at ASC, r.module_id ASC, r.parameter ASC' ), true );
check( 'ungruppiert: Zeit, Modul, Messgroesse', str_contains( $db, 'ORDER BY r.recorded_at ASC, r.module_id ASC, r.parameter ASC' ), true );
check( 'Tage nach Ortszeit getrennt, Versatz je Zeile', str_contains( $db, "'day'   => NAWS_Helpers::local_day_bucket_sql( 'r.recorded_at', NAWS_Helpers::tz_segments( " ), true );
$sc = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-shortcodes.php' );
check( '[naws_table] nach Tagen nutzt Kalendertage', str_contains( $sc, 'NAWS_Helpers::calendar_start(' ), true );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
