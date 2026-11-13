<?php
/**
 * Tests fuer templates/month-review.php und NAWS_Month_Review::render().
 *
 * Die Rechnung steht in test-month-review.php; hier geht es um das Markup:
 * Vorgabemonat, Kacheln, Vorjahreszeilen, Blaetterleiste, Leerfaelle.
 *
 *   php tests/test-month-review-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C', 'rain_unit' => 'mm', 'wind_unit' => 'kmh' ], 'date_format' => 'j. F Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
// wp_date( 'Y-m-d' ) ohne Zeitstempel ist "heute" und steht hier fest auf dem 13.11.2026.
function wp_date( $fmt, $ts = null ) {
    if ( $ts === null && $fmt === 'Y-m-d' ) { return '2026-11-13'; }
    $d = new DateTime( 'now', new DateTimeZone( 'America/New_York' ) ); $d->setTimestamp( $ts ?? time() ); return $d->format( $fmt );
}
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', '' ); }
function add_query_arg( $k, $v ) { return '/wetter/?' . $k . '=' . $v; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_create_nonce( $a ) { return 'nonce123'; }
$GLOBALS['naws_test_can_edit'] = false;
function current_user_can( $cap ) { return $GLOBALS['naws_test_can_edit']; }
class NAWS_Logger {
    public static function warning( $ctx, $msg, $extra = [] ) {}
    public static function error( ...$a ) {}
    public static function info( ...$a ) {}
}
class NAWS_Database {
    public static $modules = [ [ 'module_id' => '70:ee:50:00:00:01', 'station_id' => '70:ee:50:00:00:01', 'module_type' => 'NAMain', 'is_active' => 1 ] ];
    public static $daily   = [];
    public static function last_error() { return ''; }
    public static function get_modules( $active_only = false ) { return self::$modules; }
    public static function get_daily_summaries( array $args ) { return self::$daily; }
}
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-labels.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-month-review.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/**
 * Drei Oktober (2024, 2025, 2026) und der November 2026 bis zum 13.
 * 2026: nass (39,6 mm an 17 Tagen), Hoechstwert 24,0 am 3., Frost am 27./28.,
 * Boe 26 am 9. 2025/2024: trocken, Boe 34/40.
 */
function naws_test_rows(): array {
    $rows = [];
    $oct  = [
        2024 => [ 'avg' => 11.0, 'max' => 15.0, 'min' => 5.0, 'rain' => 0.3, 'gust' => 40.0 ],
        2025 => [ 'avg' => 10.0, 'max' => 15.0, 'min' => 4.0, 'rain' => 0.5, 'gust' => 34.0 ],
        2026 => [ 'avg' => 12.0, 'max' => 15.0, 'min' => 5.0, 'rain' => 0.0, 'gust' => 20.0 ],
    ];
    foreach ( $oct as $y => $o ) {
        for ( $d = 1; $d <= 31; $d++ ) {
            $row = [ 'day_date' => sprintf( '%d-10-%02d', $y, $d ), 'temp_avg' => $o['avg'], 'temp_max' => $o['max'], 'temp_min' => $o['min'], 'rain_sum' => $o['rain'], 'gust_max' => $o['gust'] ];
            if ( $y === 2026 ) {
                $row['rain_sum'] = $d <= 16 ? 2.4 : ( $d === 17 ? 1.2 : 0.0 );
                if ( $d === 3 )  { $row['temp_max'] = 24.0; }
                if ( $d === 27 ) { $row['temp_min'] = -0.4; }
                if ( $d === 28 ) { $row['temp_min'] = -1.2; }
                if ( $d === 9 )  { $row['gust_max'] = 26.0; }
            }
            if ( $y === 2025 && $d === 30 ) { $row['temp_min'] = -0.2; }
            $rows[] = $row;
        }
    }
    for ( $d = 1; $d <= 13; $d++ ) {
        $rows[] = [ 'day_date' => sprintf( '2026-11-%02d', $d ), 'temp_avg' => 6.0, 'temp_max' => 9.0, 'temp_min' => 3.0, 'rain_sum' => 0.0, 'gust_max' => 15.0 ];
    }
    return $rows;
}

function review( array $rows, ?string $month = null, bool $nav = true, int $years = 5 ): string {
    NAWS_Database::$daily = $rows;
    return NAWS_Month_Review::render( [ 'years' => $years, 'title' => '' ], $month, $nav );
}

echo "\ntemplates/month-review.php\n" . str_repeat( '-', 74 ) . "\n";
$html = review( naws_test_rows() );
check( 'Vorgabe = letzter abgeschlossener Monat', str_contains( $html, 'data-month="2026-10"' ), true );
check( 'fuenf Kacheln',            substr_count( $html, 'class="naws-mr-tile ' ), 5 );
check( 'Kopfsatz nassester',       str_contains( $html, 'The wettest October since records began' ), true );
check( 'Vorjahreszeilen',          substr_count( $html, 'naws-mr-prev-year' ), 10 );
check( 'kein naechster Monat hinter Oktober', str_contains( $html, 'naws-mr-next' ), false );
check( 'Platzhalter haelt den Platz', str_contains( $html, 'naws-mr-step naws-mr-step--none' ), true );
check( 'Zurueck-Link springt ueber Luecken (Oktober 2025)', str_contains( $html, 'href="/wetter/?naws_month=2025-10"' ), true );
check( 'Rekord-Stern mit Textalternative', str_contains( $html, 'Record since records began' ), true );
check( 'Tendenz abwaerts mit Textalternative', str_contains( $html, 'Less than the average of earlier years' ), true );
check( 'Wurzel mit data-Vertrag', str_contains( $html, '<section class="naws-mr" data-naws-mr data-month="2026-10" data-years="5" data-title="" data-ajax="https://example.test/wp-admin/admin-ajax.php" data-nonce="nonce123">' ), true );
check( 'Kopfsatz-Ueberschrift fokussierbar', str_contains( $html, '<h3 class="naws-mr-head" tabindex="-1">' ), true );
check( 'austauschbarer Inhalt mit aria-live', str_contains( $html, '<div class="naws-mr-body" aria-live="polite">' ), true );
check( 'Regenwert mit Einheit',    str_contains( $html, '39.6 <small>mm</small>' ), true );
check( 'Regentage',                str_contains( $html, '17 rain days' ), true );
check( 'Hoechstwert mit Datum',    str_contains( $html, 'on October 3' ), true );
check( 'Frosttage mit Vorjahr',    str_contains( $html, '2 frost days (2025: 1)' ), true );
check( 'nassester Tag',            str_contains( $html, 'The most rain fell on October 1: 2.4 mm' ), true );
check( 'Stationsrekord-Satz',      str_contains( $html, '24.0 °C on October 3 was also the warmest day since records began.' ), true );
check( 'Vorjahreszeile mit Wert',  str_contains( $html, '>2025: 34.0<' ), true );
check( 'Monatsname in der Leiste', str_contains( $html, 'October 2026' ), true );

// Mit Vormonat: Zurueck-Link vorhanden, Weiter-Link nur wenn ein Folgemonat existiert.
$rows = naws_test_rows();
for ( $d = 1; $d <= 30; $d++ ) { $rows[] = [ 'day_date' => sprintf( '2026-09-%02d', $d ), 'temp_avg' => 14.0, 'temp_max' => 20.0, 'temp_min' => 8.0, 'rain_sum' => 0.0, 'gust_max' => 20.0 ]; }
usort( $rows, static fn( $a, $b ) => strcmp( $a['day_date'], $b['day_date'] ) );
$sep = review( $rows, '2026-09' );
check( 'September: Zurueck zeigt auf Oktober 2025, Weiter auf Oktober', [ str_contains( $sep, 'naws_month=2025-10' ), str_contains( $sep, 'href="/wetter/?naws_month=2026-10"' ) ], [ true, true ] );
$oct_html = review( $rows );
check( 'Oktober: Zurueck zeigt auf September', str_contains( $oct_html, 'href="/wetter/?naws_month=2026-09"' ), true );
check( 'aria-label der Pfeile', str_contains( $oct_html, 'aria-label="Previous month: September 2026"' ), true );

// Erster Monat: kein Zurueck-Link, Platzhalter.
check( 'erster Monat: kein Zurueck-Link', str_contains( review( naws_test_rows(), '2024-10' ), 'naws-mr-prev"' ), false );

// Ohne Windmesser keine Boeen-Kachel.
$nogust = array_map( static function ( $r ) { $r['gust_max'] = null; return $r; }, naws_test_rows() );
check( 'ohne Wind keine Boeen-Kachel', substr_count( review( $nogust ), 'naws-mr-tile--gust' ), 0 );

// Fester Monat: keine Leiste, kein Skript-Anker, Monatsname als Ueberschrift.
$fest = review( naws_test_rows(), '2026-10', false );
check( 'fester Monat: keine Leiste, kein data-naws-mr', [ str_contains( $fest, '<nav' ), str_contains( $fest, 'data-naws-mr' ) ], [ false, false ] );
check( 'fester Monat: Monatsname als h3', str_contains( $fest, '<h3 class="naws-mr-month">October 2026</h3>' ), true );

// years=0: kein Stern, keine Vorjahre, neutraler Kopfsatz.
$zero = review( naws_test_rows(), null, true, 0 );
check( 'years=0: neutral, keine Vorjahre, kein Stern', [ str_contains( $zero, 'October 2026 in review' ), str_contains( $zero, 'naws-mr-prev-year' ), str_contains( $zero, 'Record since' ) ], [ true, false, false ] );

// Zoll: 39,6 mm = 1,559 in (drei Stellen).
$GLOBALS['naws_test_options']['naws_settings']['rain_unit'] = 'in';
check( 'Zoll: Regen mit 3 Stellen', str_contains( review( naws_test_rows() ), '1.559' ), true );
$GLOBALS['naws_test_options']['naws_settings']['rain_unit'] = 'mm';

// Regen mit Luecke: ein Tag ohne Regenwert -> Wert bleibt, Vermerk dazu.
$gap = array_values( array_filter( naws_test_rows(), static fn( $r ) => $r['day_date'] !== '2026-10-31' ) );
check( 'unvollstaendiger Regen: Vermerk', str_contains( review( $gap ), 'incomplete (30 of 31 days)' ), true );
check( 'unvollstaendiger Regen: Wert bleibt', str_contains( review( $gap ), '39.6' ), true );

// Leerfaelle.
check( 'Besucher-Leerfall leer', review( [] ), '' );
$GLOBALS['naws_test_can_edit'] = true;
check( 'Redakteur sieht Grund', review( [] ) !== '', true );
$only_nov = array_values( array_filter( naws_test_rows(), static fn( $r ) => str_starts_with( $r['day_date'], '2026-11' ) ) );
check( 'nur laufender Monat: eigener Grund', str_contains( review( $only_nov ), 'needs at least one completed month' ), true );
$GLOBALS['naws_test_can_edit'] = false;
check( 'nur laufender Monat: Besucher sehen nichts', review( $only_nov ), '' );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
