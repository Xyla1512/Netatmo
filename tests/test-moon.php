<?php
/**
 * Tests fuer die Mondrechnung in NAWS_Astro: wahrer Vollmond (Meeus,
 * Astronomical Algorithms, Kap. 49), Supermond und Mondfinsternisse.
 *
 * "Jetzt" ist fest der 13. November 2026, 12:00 Europe/Berlin; keine
 * Pruefung haengt an der Uhr des Rechners. Die Referenzzeiten stammen aus
 * den Vollmondtabellen und dem Five Millennium Canon of Lunar Eclipses der
 * NASA (Espenak/Meeus).
 *
 *   php tests/test-moon.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'date_format' => 'd.m.Y', 'time_format' => 'H:i', 'timezone_string' => 'Europe/Berlin' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? time() ); return $d->format( $fmt ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-astro.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function close( string $name, $got, float $want, float $tol ): void {
    global $passed, $failed;
    if ( is_numeric( $got ) && abs( (float) $got - $want ) <= $tol ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s ± %s, ist %s\n", $name, gmdate( 'Y-m-d H:i', (int) $want ), $tol, is_numeric( $got ) ? gmdate( 'Y-m-d H:i', (int) $got ) : var_export( $got, true ) );
}

$now = gmmktime( 11, 0, 0, 11, 13, 2026 ); // 12:00 MEZ

echo "\nWahrer Vollmond (Meeus Kap. 49)\n" . str_repeat( '-', 74 ) . "\n";

check( 'next_full_moon() gibt es',                 method_exists( 'NAWS_Astro', 'next_full_moon' ), true );
$moon = NAWS_Astro::moon_data( $now );
close( 'naechster Vollmond 24.11.2026 14:53 UTC',  $moon['next_full_ts'], gmmktime( 14, 53, 0, 11, 24, 2026 ), 3600 );
check( 'Anzeige in Ortszeit (15:5x MEZ)',          (bool) preg_match( '/^24\.11\.2026 – 15:5\d$/', $moon['next_full'] ), true );
close( 'Vollmond 25.01.2024 17:54 UTC',            NAWS_Astro::next_full_moon( gmmktime( 0, 0, 0, 1, 20, 2024 ) ), gmmktime( 17, 54, 0, 1, 25, 2024 ), 3600 );
close( 'Vollmond 09.08.2025 07:55 UTC',            NAWS_Astro::next_full_moon( gmmktime( 0, 0, 0, 8, 1, 2025 ) ), gmmktime( 7, 55, 0, 8, 9, 2025 ), 3600 );
close( 'Vollmond 21.01.2000 04:40 UTC',            NAWS_Astro::next_full_moon( gmmktime( 0, 0, 0, 1, 15, 2000 ) ), gmmktime( 4, 40, 0, 1, 21, 2000 ), 3600 );
$after = NAWS_Astro::next_full_moon( gmmktime( 14, 53, 0, 11, 24, 2026 ) + 7200 );
check( 'danach kommt der vom 24.12.2026',          gmdate( 'Y-m-d', $after ), '2026-12-24' );
check( 'streng nach dem Ausgangszeitpunkt',        NAWS_Astro::next_full_moon( $after ) > $after, true );

echo "\nSupermond\n" . str_repeat( '-', 74 ) . "\n";

$sm = NAWS_Astro::next_supermoon( $now );
check( 'naechster Supermond am 24.12.2026',        is_array( $sm ) ? gmdate( 'Y-m-d', $sm['ts'] ) : $sm, '2026-12-24' );
check( 'Rueckgabe hat date, ts, distance_km',      is_array( $sm ) && isset( $sm['date'], $sm['ts'], $sm['distance_km'] ), true );
check( 'Abstand unter 360.000 km',                 is_array( $sm ) && $sm['distance_km'] < 360000, true );
$dec = NAWS_Astro::moon_data( gmmktime( 12, 0, 0, 12, 15, 2026 ) );
check( 'moon_data() und Supermond nennen dieselbe Zeit', $dec['next_full_ts'], $sm['ts'] ?? null );
check( 'Supermond-Zeit ist ein Vollmond',          is_array( $sm ) && $sm['ts'] === NAWS_Astro::next_full_moon( $sm['ts'] - 86400 ), true );

echo "\nMondfinsternisse (NASA-Canon)\n" . str_repeat( '-', 74 ) . "\n";

$ecl = NAWS_Astro::next_lunar_eclipse( $now );
check( 'naechste Finsternis am 20.02.2027',        is_array( $ecl ) ? gmdate( 'Y-m-d', $ecl['ts'] ) : $ecl, '2027-02-20' );
check( '... halbschattig',                         $ecl['type'] ?? null, 'penumbral' );
close( '... Maximum 23:14 UTC',                    $ecl['ts'] ?? null, gmmktime( 23, 14, 0, 2, 20, 2027 ), 120 );
check( 'Rueckgabe hat date, type, ts',             is_array( $ecl ) && isset( $ecl['date'], $ecl['type'], $ecl['ts'] ), true );

$seq = [];
$t   = $now;
while ( ( $e = NAWS_Astro::next_lunar_eclipse( $t ) ) !== null && count( $seq ) < 10 ) {
    $seq[] = gmdate( 'Y-m-d', $e['ts'] ) . ' ' . $e['type'];
    if ( $e['type'] === 'total' ) break;
    $t = $e['ts'];
}
check( 'Folge bis zur naechsten totalen', $seq, [
    '2027-02-20 penumbral',
    '2027-07-18 penumbral',
    '2027-08-17 penumbral',
    '2028-01-12 partial',
    '2028-07-06 partial',
    '2028-12-31 total',
] );

check( 'Tabelle gibt es',                          method_exists( 'NAWS_Astro', 'lunar_eclipses' ), true );
$table = method_exists( 'NAWS_Astro', 'lunar_eclipses' ) ? NAWS_Astro::lunar_eclipses() : [];
check( 'Tabelle reicht von 2024 bis 2040',         $table ? [ gmdate( 'Y', $table[0]['ts'] ), gmdate( 'Y', end( $table )['ts'] ) ] : null, [ '2024', '2040' ] );
$bad = [];
$prev = 0;
foreach ( $table as $row ) {
    $full = NAWS_Astro::next_full_moon( $row['ts'] - 2 * 86400 );
    if ( abs( $full - $row['ts'] ) > 6 * 3600 ) $bad[] = gmdate( 'Y-m-d H:i', $row['ts'] );
    if ( ! in_array( $row['type'], [ 'penumbral', 'partial', 'total' ], true ) ) $bad[] = $row['type'];
    if ( $row['ts'] <= $prev ) $bad[] = 'Reihenfolge ' . gmdate( 'Y-m-d', $row['ts'] );
    $prev = $row['ts'];
}
check( 'jede Finsternis liegt binnen 6 h an einem Vollmond, sortiert', $bad, [] );
check( 'nach dem Tabellenende: null',              NAWS_Astro::next_lunar_eclipse( gmmktime( 0, 0, 0, 1, 1, 2041 ) ), null );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
