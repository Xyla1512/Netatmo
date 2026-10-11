<?php
/**
 * Rendert templates/forecast.php mit deutschem Zahlenformat.
 *
 * Der Fehler: Beim Dezimalkomma in 2.1.0 (be3636c) blieb [naws_forecast]
 * draussen. Die Karten druckten Temperatur, Niederschlag, Wind und Boeen
 * als rohe PHP-Zahl, eine deutsche Seite zeigte also "17.4 / 14.7 °C"
 * neben "23,4 °C" im Widget. Seit 2.1.1 gehen alle fuenf Werte durch
 * NAWS_Helpers::display_number().
 *
 * Der Stub fuer number_format_i18n() spricht hier Deutsch (Komma, Punkt),
 * damit ein vergessener Wert als Punkt auffaellt.
 *
 *   php tests/test-forecast-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];

function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d, ',', '.' ); }
function wp_unique_id( $p = '' ) { return $p . '1'; }
function wp_date( $fmt, $ts = null ) { return gmdate( $fmt, $ts ?? time() ); }
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Forecast {
    public static function wmo_description( $code ) { return [ 'label' => 'Bewoelkt' ]; }
    public static function is_today( $date ) { return false; }
    public static function weekday_short( $date ) { return 'Mo'; }
    public static function date_short( $date ) { return '05.10.'; }
}
class NAWS_Weather_State {
    public static function wmo_to_state( $code, $is_day ) { return ''; }
}
class NAWS_Weather_Icons {
    public static function render_inline( $state, $size ) { return ''; }
}

require_once $PLUGIN . 'includes/class-naws-helpers.php';

function render( array $day, array $settings = [] ): string {
    $GLOBALS['opts'] = [ 'naws_settings' => $settings ];
    $forecast = [ 'days' => [ array_merge( [
        'date' => '2026-10-05', 'weathercode' => 3,
        'temp_max' => 17.4, 'temp_min' => 14.7,
        'wind_max' => 23.5, 'gust_max' => 41.8, 'wind_dir' => 225,
        'precip_sum' => 2.3, 'precip_prob' => 60,
    ], $day ) ] ];
    $atts = [ 'title' => 'Vorhersage' ];
    ob_start();
    include dirname( __DIR__ ) . '/templates/forecast.php';
    return ob_get_clean();
}

/** Der Text eines Elements mit der Klasse $class, Leerraum gestaucht. */
function text_of( string $html, string $class ): string {
    if ( ! preg_match( '#class="' . preg_quote( $class, '#' ) . '"[^>]*>(.*?)</div>#s', $html, $m ) ) return '';
    return trim( preg_replace( '/\s+/', ' ', strip_tags( $m[1] ) ) );
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\ntemplates/forecast.php mit deutschem Zahlenformat\n" . str_repeat( '-', 74 ) . "\n";

$html = render( [] );
check( 'Temperaturen mit Komma', text_of( $html, 'naws-fc-temps' ), '17,4 / 14,7 °C' );
check( 'Niederschlag, Wahrscheinlichkeit, Wind mit Komma', text_of( $html, 'naws-fc-meta' ), '🌧️ 2,3 mm 💧 60% 🌬️ 23,5 km/h 🧭 SW' );
check( 'Boeen mit Komma', text_of( $html, 'naws-fc-gust' ), '🌪️ Gusts: 41,8 km/h' );

$html = render( [ 'temp_max' => 20.0, 'temp_min' => -3.0, 'precip_sum' => 0.0 ] );
check( 'ganze Zahlen ohne Nachkommastellen', text_of( $html, 'naws-fc-temps' ), '20 / -3 °C' );
check( 'kein Niederschlag ist 0 mm', strpos( text_of( $html, 'naws-fc-meta' ), '🌧️ 0 mm' ) === 0, true );

$html = render( [ 'temp_max' => null, 'temp_min' => null, 'precip_sum' => null, 'wind_max' => null, 'gust_max' => null ] );
check( 'fehlende Temperaturen bleiben --', text_of( $html, 'naws-fc-temps' ), '-- / -- °C' );
check( 'fehlender Niederschlag ist 0 mm', strpos( text_of( $html, 'naws-fc-meta' ), '🌧️ 0 mm' ) === 0, true );
check( 'fehlender Wind bleibt --', strpos( text_of( $html, 'naws-fc-meta' ), '🌬️ -- ' ) !== false, true );
check( 'ohne Boeen keine Boeenzeile', strpos( $html, 'naws-fc-gust' ), false );

$html = render( [ 'temp_max' => 17.4, 'precip_sum' => 12.7 ], [ 'temperature_unit' => 'F', 'rain_unit' => 'in', 'wind_unit' => 'ms' ] );
check( 'Fahrenheit mit Komma', text_of( $html, 'naws-fc-temps' ), '63,3 / 58,5 °F' );
check( 'Zoll und m/s mit Komma', strpos( text_of( $html, 'naws-fc-meta' ), '🌧️ 0,5 in' ) === 0 && strpos( text_of( $html, 'naws-fc-meta' ), '6,5 m/s' ) !== false, true );
check( 'Boeen in m/s mit Komma', text_of( $html, 'naws-fc-gust' ), '🌪️ Gusts: 11,6 m/s' );

// ── Zeile "Updated" bei der letzten guten Vorhersage (2.1.2) ─────────────
// Im Rueckfall kann der Stand von gestern sein; nur die Uhrzeit taete so,
// als waere er von heute.
function render_fc( array $extra ): string {
    $GLOBALS['opts'] = [ 'naws_settings' => [] ];
    $forecast = array_merge( [ 'days' => [ [
        'date' => '2026-10-05', 'weathercode' => 3, 'temp_max' => 1.0, 'temp_min' => 0.0,
        'wind_max' => 1.0, 'gust_max' => 0, 'wind_dir' => 0, 'precip_sum' => 0.0, 'precip_prob' => 0,
    ] ] ], $extra );
    $atts = [ 'title' => 'Vorhersage' ];
    ob_start();
    include dirname( __DIR__ ) . '/templates/forecast.php';
    return ob_get_clean();
}
function hdr_time( string $html ): string {
    return preg_match( '#class="naws-fc-hdr-time">\s*(.*?)\s*</span>#s', $html, $m ) ? $m[1] : '';
}
$gestern = strtotime( '-1 day 12:00' );
check( 'frisch: nur Uhrzeit', hdr_time( render_fc( [ 'fetched_at' => $gestern ] ) ), 'Updated: ' . gmdate( 'H:i', $gestern ) );
check( 'Rueckfall von gestern: mit Datum', hdr_time( render_fc( [ 'fetched_at' => $gestern, 'stale' => true ] ) ), 'Updated: ' . gmdate( 'Y-m-d H:i', $gestern ) );
check( 'Rueckfall von heute: nur Uhrzeit', hdr_time( render_fc( [ 'fetched_at' => time(), 'stale' => true ] ) ), 'Updated: ' . gmdate( 'H:i' ) );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
