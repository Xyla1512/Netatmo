<?php
/**
 * Tests fuer templates/records.php und templates/on-this-day.php.
 *
 * Die Rechnung ist in test-records.php abgesichert; hier geht es um das
 * Markup: eine Kachel je Rekord, beide Layouts, die Auswahl, die
 * Fusszeile, und dass ein leerer Baustein leer bleibt.
 *
 *   php tests/test-records-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C', 'rain_unit' => 'mm', 'wind_unit' => 'kmh' ], 'date_format' => 'j. F Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', new DateTimeZone( 'America/New_York' ) ); $d->setTimestamp( $ts ?? time() ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', '' ); }
require_once __DIR__ . '/i18n-stubs.php';

require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Ein Jahr mit allen 15 Rekorden — dieselbe Form wie in test-records.php. */
function naws_test_year(): array {
    $rows = [];
    for ( $d = new DateTime( '2025-01-01' ); $d->format( 'Y' ) === '2025'; $d->modify( '+1 day' ) ) {
        $md  = $d->format( 'm-d' );
        $m   = (int) $d->format( 'n' );
        $row = [ 'day_date' => $d->format( 'Y-m-d' ), 'temp_min' => 8.0, 'temp_max' => 18.0, 'temp_avg' => 13.0, 'rain_sum' => 0.0, 'gust_max' => 20.0 ];
        if ( $m === 1 )  { $row['temp_avg'] = 1.0; }
        if ( $m === 7 )  { $row['temp_avg'] = 22.0; }
        $dom = (int) $d->format( 'j' );
        if ( ( $dom === 1 || $dom === 15 ) && $m !== 8 && $m !== 9 ) { $row['rain_sum'] = 0.5; }
        if ( $m === 11 && in_array( $dom, [ 1, 8, 15, 22, 29 ], true ) ) { $row['rain_sum'] = 18.0; }
        if ( $md >= '01-08' && $md <= '01-14' ) { $row['temp_min'] = -2.0; }
        if ( $md === '01-10' ) { $row['temp_min'] = -8.5; $row['temp_max'] = -3.0; }
        if ( $md >= '07-01' && $md <= '07-09' ) { $row['temp_max'] = 27.0; }
        if ( $md >= '07-01' && $md <= '07-05' ) { $row['temp_max'] = 31.0; }
        if ( $md === '07-01' ) { $row['temp_max'] = 39.1; $row['temp_min'] = 24.0; }
        if ( $md === '08-15' ) { $row['temp_max'] = 35.0; $row['temp_min'] = 10.0; }
        if ( $md === '06-03' ) { $row['rain_sum'] = 26.4; $row['gust_max'] = 46.0; }
        if ( $md >= '10-10' && $md <= '10-16' ) { $row['rain_sum'] = 1.5; }
        if ( $md === '07-31' || $md === '09-21' ) { $row['rain_sum'] = 0.5; }
        $rows[] = $row;
    }
    return $rows;
}

// Die Templates holen ihre Zeilen ueber NAWS_Records::rows(); hier kommen
// sie aus der Testdatei. Der Ersatz sitzt in einer Unterklasse mit
// demselben Namen wie das Template sie ruft — deshalb wird
// NAWS_Records::rows() ueber einen Hook austauschbar gemacht: das Template
// liest $naws_rows, wenn die Variable gesetzt ist, sonst rows().
function render( string $template, array $atts, ?array $naws_rows ): string {
    ob_start();
    include dirname( __DIR__ ) . '/templates/' . $template;
    return ob_get_clean();
}

echo "\ntemplates/records.php\n" . str_repeat( '-', 74 ) . "\n";

$year = naws_test_year();
$html = render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'cards', 'title' => 'Records' ], $year );
check( 'Wurzel mit Klasse',                     str_contains( $html, '<section class="naws-rec">' ), true );
check( 'Ueberschrift',                          str_contains( $html, '<h3 class="naws-rec-title">Records</h3>' ), true );
check( 'fuenfzehn Kacheln',                     substr_count( $html, 'class="naws-rec-tile ' ), 15 );
check( 'die Kachel traegt ihren Schluessel',    str_contains( $html, 'naws-rec-tile naws-rec-hottest_day' ), true );
check( 'Wert mit Einheit',                      str_contains( $html, '39.1 <span class="naws-rec-unit">°C</span>' ), true );
check( 'Datum in der Einstellung der Site',     str_contains( $html, '<span class="naws-rec-when">1. July 2025</span>' ), true );
check( 'Serie mit Tagen und Spanne',            str_contains( $html, '51 days' ) && str_contains( $html, '1. August 2025 – 20. September 2025' ), true );
check( 'Monat als Monatsname',                  str_contains( $html, 'July 2025' ), true );
check( 'Spanne in Grad, nicht in Fahrenheit-Versatz', str_contains( $html, '25.0 <span class="naws-rec-unit">°C</span>' ), true );
check( 'Fusszeile mit erstem Tag und Tagen',    (bool) preg_match( '#<p class="naws-rec-foot">Since 1\. January 2025 · 365 days with readings</p>#', $html ), true );
check( 'keine MAC-Adresse',                     (bool) preg_match( '/[0-9a-f]{2}(:[0-9a-f]{2}){5}/i', $html ), false );
check( 'kein style-Block',                      str_contains( $html, '<style' ), false );

// Rain in inches needs three decimals, not the catalogue's one: format_value()
// already returns 26.4 mm as 1.0394 in, and one decimal would round it to 1.0.
$GLOBALS['naws_test_options']['naws_settings']['rain_unit'] = 'in';
$inches = render( 'records.php', [ 'year' => '', 'records' => 'wettest_day', 'layout' => 'cards', 'title' => '' ], $year );
check( 'Regen in Zoll mit drei Nachkommastellen', str_contains( $inches, '1.039 <span class="naws-rec-unit">in</span>' ), true );
$GLOBALS['naws_test_options']['naws_settings']['rain_unit'] = 'mm';

$table = render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'table', 'title' => '' ], $year );
check( 'Tabelle statt Kacheln',                 str_contains( $table, '<table class="naws-rec-table">' ), true );
check( 'fuenfzehn Zeilen im Rumpf',             substr_count( $table, '<tr class="naws-rec-row' ), 15 );
check( 'ohne Titel keine Ueberschrift',         str_contains( $table, '<h3' ), false );

$some = render( 'records.php', [ 'year' => '', 'records' => 'wettest_day, hottest_day, unbekannt', 'layout' => 'cards', 'title' => '' ], $year );
check( 'Auswahl: zwei Kacheln',                 substr_count( $some, 'class="naws-rec-tile ' ), 2 );
check( 'Auswahl in Aufrufreihenfolge',          strpos( $some, 'naws-rec-wettest_day' ) < strpos( $some, 'naws-rec-hottest_day' ), true );

check( 'ohne Zeilen nichts',                    render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'cards', 'title' => 'x' ], [] ), '' );
check( 'ohne berechenbaren Rekord nichts',      render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'cards', 'title' => 'x' ], [ [ 'day_date' => '2025-01-01', 'pressure_avg' => 1000.0 ] ] ), '' );

echo "\ntemplates/on-this-day.php\n" . str_repeat( '-', 74 ) . "\n";

$otd_rows = [
    [ 'day_date' => '2024-09-05', 'temp_min' => 12.0, 'temp_max' => 24.0, 'temp_avg' => 18.0, 'rain_sum' => 4.2 ],
    [ 'day_date' => '2025-09-05', 'temp_min' => 12.0, 'temp_max' => 28.0, 'temp_avg' => 20.0, 'rain_sum' => 0.0 ],
];
$otd = render( 'on-this-day.php', [ 'date' => '2026-09-05', 'title' => 'This day in earlier years' ], $otd_rows );
check( 'Wurzel mit Klasse',                     str_contains( $otd, '<section class="naws-otd">' ), true );
check( 'eine Zeile je Jahr',                    substr_count( $otd, '<tr class="naws-otd-row">' ), 2 );
check( 'neuestes Jahr zuerst',                  strpos( $otd, '>2025<' ) < strpos( $otd, '>2024<' ), true );
check( 'Rekordzelle markiert',                  substr_count( $otd, 'class="naws-otd-record"' ), 3 );
check( 'Kopfzeile',                             str_contains( $otd, '<th>Year</th>' ), true );
check( 'ohne fruehere Jahre nichts',            render( 'on-this-day.php', [ 'date' => '2024-09-05', 'title' => '' ], $otd_rows ), '' );
check( 'unbrauchbares Datum faellt auf heute',  str_contains( render( 'on-this-day.php', [ 'date' => 'gestern', 'title' => '' ], [ [ 'day_date' => '2000-' . gmdate( 'm-d' ), 'temp_max' => 1.0 ] ] ), '>2000<' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
