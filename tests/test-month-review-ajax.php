<?php
/**
 * Tests fuer wp_ajax_naws_get_month_review und das Skript month-review.js.
 *
 *   php tests/test-month-review-ajax.php
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
class NAWS_Test_Sent extends Exception {}
$GLOBALS['sent'] = null;
$GLOBALS['hooks'] = [];
function add_action( $hook, ...$a ) { $GLOBALS['hooks'][] = $hook; }
function check_ajax_referer( ...$a ) { return true; }
function nocache_headers() {}
function wp_unslash( $s ) { return $s; }
function wp_send_json_success( $data = null ) { $GLOBALS['sent'] = [ 'ok' => true, 'status' => 200, 'data' => $data ]; throw new NAWS_Test_Sent(); }
function wp_send_json_error( $data = null, $code = null ) { $GLOBALS['sent'] = [ 'ok' => false, 'status' => $code, 'data' => $data ]; throw new NAWS_Test_Sent(); }
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
require_once dirname( __DIR__ ) . '/includes/class-naws-ajax.php';

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


function call_month_review( array $post ): array {
    NAWS_Database::$daily = naws_test_rows();
    $GLOBALS['sent'] = null;
    $_POST = $post;
    try { NAWS_Ajax::instance()->get_month_review(); } catch ( NAWS_Test_Sent $e ) {}
    $_POST = [];
    return $GLOBALS['sent'] ?? [ 'ok' => null, 'status' => null, 'data' => null ];
}
function has_action_registered( string $h ): bool { return in_array( $h, $GLOBALS['hooks'], true ); }

echo "\nwp_ajax_naws_get_month_review\n" . str_repeat( '-', 74 ) . "\n";
check( 'ungueltiges Format -> 400',  call_month_review( [ 'month' => '2026-13' ] )['status'], 400 );
check( 'laufender Monat -> 400',     call_month_review( [ 'month' => '2026-11' ] )['status'], 400 );
check( 'fehlender Monat -> 400',     call_month_review( [] )['status'], 400 );
check( 'gueltig -> HTML wie Erstaufruf', call_month_review( [ 'month' => '2025-10', 'years' => '5' ] )['data']['html'], NAWS_Month_Review::render( [ 'years' => 5, 'title' => '' ], '2025-10', true ) );
check( 'Antwort nennt den Monat',    call_month_review( [ 'month' => '2025-10' ] )['data']['month'], '2025-10' );
check( 'years geklemmt',             str_contains( call_month_review( [ 'month' => '2026-10', 'years' => '99' ] )['data']['html'], 'data-years="10"' ), true );
check( 'title uebernommen',          str_contains( call_month_review( [ 'month' => '2026-10', 'title' => 'Mein Titel' ] )['data']['html'], 'data-title="Mein Titel"' ), true );
check( 'nopriv registriert',         has_action_registered( 'wp_ajax_nopriv_naws_get_month_review' ), true );
check( 'angemeldet registriert',     has_action_registered( 'wp_ajax_naws_get_month_review' ), true );

$js = is_file( dirname( __DIR__ ) . '/assets/js/month-review.js' ) ? (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/month-review.js' ) : '';
check( 'Rueckfall location.href',     (bool) preg_match( '/location\.href\s*=\s*\w+\.href/', $js ), true );
check( 'replaceState statt pushState', [ str_contains( $js, 'replaceState' ), str_contains( $js, 'pushState' ) ], [ true, false ] );
check( 'nur Bloecke mit data-naws-mr', str_contains( $js, '[data-naws-mr]' ), true );

$sc = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-shortcodes.php' );
$fn = substr( $sc, (int) strpos( $sc, 'function sc_month_review' ), 1800 );
check( 'Skript nur mit Leiste eingereiht', (bool) preg_match( '/if \( \$nav \)|wp_enqueue_script\( \'naws-month-review\' \)/', $fn ) && ! preg_match( '/sc_month_review\( \$atts \) \{\s*\$this->enqueue_frontend_styles\(\);\s*wp_enqueue_script/', $fn ), true );

printf( "\n%d passed, %d failed\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
