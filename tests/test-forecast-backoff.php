<?php
/**
 * Vorhersage bei HTTP 429 und anderen Fehlern der Wetterdienste.
 *
 * Der Fehler (Nutzermeldung Oktober 2026): Open-Meteo antwortet mit
 * HTTP 429 "Too Many Requests", die Seite zeigt "Open-Meteo API HTTP 429".
 * Open-Meteo begrenzt pro IP-Adresse (600/min, 5.000/h, 10.000/Tag); auf
 * Shared Hosting teilen sich viele Seiten eine Adresse. Das Plugin hat einen
 * Fehler nie gemerkt: Jeder Seitenaufruf fragte sofort wieder an, Vorhersage
 * und aktuelle Bedingungen je fuer sich, und hielt die Adresse damit ueber
 * der Grenze. Eine letzte gute Vorhersage gab es nicht, Besucher sahen den
 * rohen HTTP-Code.
 *
 * Seit 2.1.2:
 *   - nach einem Fehler eine Sperrfrist pro Anbieter (429: Retry-After oder
 *     15 min, sonst 5 min), in der keine Anfrage hinausgeht;
 *   - die letzte gute Vorhersage wird bis 24 h weiter gezeigt, ohne
 *     vergangene Tage, mit stale => true;
 *   - Besucher lesen einen verstaendlichen Satz, der Code steht im Protokoll.
 *
 *   php tests/test-forecast-backoff.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'NAWS_VERSION', 'test' );

// ── WordPress-Stubs ─────────────────────────────────────────────────────
$GLOBALS['opts']       = [];
$GLOBALS['transients'] = [];   // key => [ value, ttl ]
$GLOBALS['http']       = [];   // Warteschlange der Antworten
$GLOBALS['calls']      = [];   // URLs, die hinausgingen
$GLOBALS['logged']     = [];

function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = [ $v, $ttl ]; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function home_url( $p = '' ) { return 'https://example.org' . $p; }
function wp_timezone_string() { return 'UTC'; }
function wp_date( $fmt, $ts = null ) { return gmdate( $fmt, $ts ?? time() ); }
function determine_locale() { return 'de_DE'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {
    private $m;
    public function __construct( $c = '', $m = '' ) { $this->m = $m; }
    public function get_error_message() { return $this->m; }
}
function wp_remote_get( $url, $args = [] ) {
    $GLOBALS['calls'][] = $url;
    return array_shift( $GLOBALS['http'] ) ?? new WP_Error( 'none', 'no response queued' );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? ( $r['headers'][ strtolower( $h ) ] ?? '' ) : ''; }
ini_set( "error_log", sys_get_temp_dir() . "/naws-test-forecast-backoff.log" );
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Logger {
    public static function error( $ctx, $msg, $data = [] ) { $GLOBALS['logged'][] = $msg; }
    public static function warning( $ctx, $msg, $data = [] ) { $GLOBALS['logged'][] = $msg; }
}
class NAWS_Database {
    public static function get_modules( $x = true ) { return [ [ 'latitude' => 51.34, 'longitude' => 12.37 ] ]; }
}

require_once dirname( __DIR__ ) . '/includes/class-naws-forecast.php';

// ── Antworten ───────────────────────────────────────────────────────────
function resp( int $code, string $body, array $headers = [] ): array {
    return [ 'code' => $code, 'body' => $body, 'headers' => $headers ];
}
function day_rel( int $offset ): string { return gmdate( 'Y-m-d', time() + $offset * DAY_IN_SECONDS ); }
function om_daily_ok(): array {
    $time = [];
    for ( $i = 0; $i < 7; $i++ ) $time[] = day_rel( $i );
    $col = array_fill( 0, 7, 10.0 );
    return resp( 200, json_encode( [ 'daily' => [
        'time' => $time, 'weathercode' => array_fill( 0, 7, 3 ),
        'temperature_2m_max' => $col, 'temperature_2m_min' => $col,
        'apparent_temperature_max' => $col, 'apparent_temperature_min' => $col,
        'precipitation_sum' => $col, 'precipitation_probability_max' => $col,
        'windspeed_10m_max' => $col, 'windgusts_10m_max' => $col,
        'winddirection_10m_dominant' => $col, 'sunrise' => $time, 'sunset' => $time,
    ] ] ) );
}
function om_429( string $retry_after = '' ): array {
    $h = $retry_after !== '' ? [ 'retry-after' => $retry_after ] : [];
    return resp( 429, '{"error":true,"reason":"Minutely API request limit exceeded. Please try in one minute."}', $h );
}
function reset_world( array $settings = [] ): void {
    $GLOBALS['opts']       = [ 'naws_settings' => $settings ];
    $GLOBALS['transients'] = [];
    $GLOBALS['http']       = [];
    $GLOBALS['calls']      = [];
    $GLOBALS['logged']     = [];
}
/** Abgelaufene Vorhersage-Caches entfernen, die Sperrfrist bleibt. */
function expire_data_caches(): void {
    foreach ( array_keys( $GLOBALS['transients'] ) as $k ) {
        if ( strpos( $k, 'backoff' ) === false ) unset( $GLOBALS['transients'][ $k ] );
    }
}
function backoff_ttl( string $provider ) {
    foreach ( $GLOBALS['transients'] as $k => $v ) {
        if ( strpos( $k, 'backoff' ) !== false && strpos( $k, $provider ) !== false ) return $v[1];
    }
    return null;
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nVorhersage: Sperrfrist und letzte gute Vorhersage\n" . str_repeat( '-', 74 ) . "\n";

// ── 1. 429 ohne frueheren Stand ─────────────────────────────────────────
reset_world();
$GLOBALS['http'] = [ om_429() ];
$r1 = NAWS_Forecast::get_forecast( 5 );
$r2 = NAWS_Forecast::get_forecast( 5 );
$r3 = NAWS_Forecast::get_forecast( 3 );
check( '429: genau eine Anfrage fuer drei Aufrufe', count( $GLOBALS['calls'] ), 1 );
check( '429: Fehler wird gemeldet', isset( $r1['error'], $r2['error'], $r3['error'] ), true );
check( '429: Besucher sehen keinen HTTP-Code', strpos( (string) ( $r1['error'] ?? '' ), '429' ), false );
check( '429: Besucher sehen keinen HTTP-Code (aus der Sperrfrist)', strpos( (string) ( $r2['error'] ?? '' ), 'HTTP' ), false );
check( '429: Code steht im Protokoll', (bool) preg_grep( '/429/', $GLOBALS['logged'] ), true );
check( '429 ohne Retry-After: Sperrfrist 15 min', backoff_ttl( 'open_meteo' ), 15 * MINUTE_IN_SECONDS );

// ── 2. Retry-After wird genommen ────────────────────────────────────────
reset_world();
$GLOBALS['http'] = [ om_429( '120' ) ];
NAWS_Forecast::get_forecast( 5 );
check( '429 mit Retry-After 120: Sperrfrist 120 s', backoff_ttl( 'open_meteo' ), 120 );

// ── 3. Sperrfrist gilt auch fuer die aktuellen Bedingungen ──────────────
reset_world();
$GLOBALS['http'] = [ om_429() ];
NAWS_Forecast::get_forecast( 5 );
$now = NAWS_Forecast::get_current_conditions();
check( 'aktuelle Bedingungen fragen in der Sperrfrist nicht an', count( $GLOBALS['calls'] ), 1 );
check( 'aktuelle Bedingungen melden stale', $now['stale'], true );

reset_world();
$GLOBALS['http'] = [ om_429() ];
NAWS_Forecast::get_current_conditions();
NAWS_Forecast::get_current_conditions();
NAWS_Forecast::get_forecast( 5 );
check( 'Fehler der aktuellen Bedingungen sperrt auch die Vorhersage', count( $GLOBALS['calls'] ), 1 );

// ── 4. Andere Fehler: kuerzere Sperrfrist ───────────────────────────────
reset_world();
$GLOBALS['http'] = [ resp( 502, 'Bad Gateway' ) ];
NAWS_Forecast::get_forecast( 5 );
NAWS_Forecast::get_forecast( 5 );
check( '502: eine Anfrage', count( $GLOBALS['calls'] ), 1 );
check( '502: Sperrfrist 5 min', backoff_ttl( 'open_meteo' ), 5 * MINUTE_IN_SECONDS );

reset_world();
$GLOBALS['http'] = [];   // wp_remote_get liefert WP_Error
NAWS_Forecast::get_forecast( 5 );
NAWS_Forecast::get_forecast( 5 );
check( 'Netzfehler: eine Anfrage', count( $GLOBALS['calls'] ), 1 );

// ── 5. Nach der Sperrfrist wird wieder gefragt ──────────────────────────
reset_world();
$GLOBALS['http'] = [ om_429(), om_daily_ok() ];
NAWS_Forecast::get_forecast( 5 );
$GLOBALS['transients'] = [];   // Sperrfrist abgelaufen
$ok = NAWS_Forecast::get_forecast( 5 );
check( 'nach der Sperrfrist: zweite Anfrage', count( $GLOBALS['calls'] ), 2 );
check( 'nach der Sperrfrist: fuenf Tage', count( $ok['days'] ?? [] ), 5 );
check( 'frische Daten sind nicht stale', ! empty( $ok['stale'] ), false );

// ── 6. Letzte gute Vorhersage ───────────────────────────────────────────
reset_world();
$GLOBALS['http'] = [ om_daily_ok(), om_429() ];
NAWS_Forecast::get_forecast( 5 );
expire_data_caches();
// Die gespeicherte Vorhersage ist von gestern Abend: der erste Tag liegt
// zurueck und muss wegfallen.
$last = $GLOBALS['opts'][ NAWS_Forecast::OPT_LAST_FORECAST ] ?? null;
check( 'gute Vorhersage wird aufgehoben', is_array( $last ), true );
if ( is_array( $last ) ) {
    foreach ( $last['data']['days'] as &$d ) $d['date'] = gmdate( 'Y-m-d', strtotime( $d['date'] ) - DAY_IN_SECONDS );
    unset( $d );
    $last['data']['fetched_at'] = time() - 20 * HOUR_IN_SECONDS;
    $GLOBALS['opts'][ NAWS_Forecast::OPT_LAST_FORECAST ] = $last;
}
$st = NAWS_Forecast::get_forecast( 5 );
check( 'Rueckfall: kein Fehler', isset( $st['error'] ), false );
check( 'Rueckfall: stale', $st['stale'] ?? null, true );
check( 'Rueckfall: beginnt mit heute', $st['days'][0]['date'] ?? null, day_rel( 0 ) );
check( 'Rueckfall: fuenf Tage', count( $st['days'] ?? [] ), 5 );
check( 'Rueckfall: fetched_at bleibt der alte Stand', $st['fetched_at'] ?? null, time() - 20 * HOUR_IN_SECONDS );
check( 'Rueckfall landet nicht im Cache', get_transient( NAWS_Forecast::CACHE_KEY . '_open_meteo' ), false );
$st2 = NAWS_Forecast::get_forecast( 3 );
check( 'Rueckfall in der Sperrfrist: keine weitere Anfrage', count( $GLOBALS['calls'] ), 2 );
check( 'Rueckfall in der Sperrfrist: drei Tage', count( $st2['days'] ?? [] ), 3 );

// Aelter als 24 h: kein Rueckfall
$last['data']['fetched_at'] = time() - 25 * HOUR_IN_SECONDS;
$GLOBALS['opts'][ NAWS_Forecast::OPT_LAST_FORECAST ] = $last;
$old = NAWS_Forecast::get_forecast( 5 );
check( 'Stand aelter als 24 h: Fehler statt Rueckfall', isset( $old['error'] ), true );

// Anderer Anbieter: kein Rueckfall auf fremde Daten
$last['data']['fetched_at'] = time() - HOUR_IN_SECONDS;
$GLOBALS['opts'][ NAWS_Forecast::OPT_LAST_FORECAST ] = $last;
$GLOBALS['opts']['naws_settings'] = [ 'forecast_provider' => 'yr_no' ];
$GLOBALS['http'] = [ resp( 429, '' ) ];
$yr = NAWS_Forecast::get_forecast( 5 );
check( 'Yr.no-Fehler nimmt keine Open-Meteo-Daten', isset( $yr['error'] ), true );
check( 'Yr.no hat eine eigene Sperrfrist', backoff_ttl( 'yr_no' ), 15 * MINUTE_IN_SECONDS );

// ── 7. Ortssuche (manueller Modus) ──────────────────────────────────────
// Ein 429 der Geocoding-API traegt keine Ergebnisse und erschien bis 2.1.1
// als "Location not found".
reset_world( [ 'forecast_location' => 'manual', 'forecast_city' => 'Leipzig', 'forecast_country' => 'DE' ] );
$GLOBALS['http'] = [ om_429() ];
$g1 = NAWS_Forecast::get_forecast( 5 );
$g2 = NAWS_Forecast::get_forecast( 5 );
check( 'Geocoding 429: kein "Location not found"', strpos( (string) ( $g1['error'] ?? '' ), 'not found' ), false );
check( 'Geocoding 429: eine Anfrage fuer zwei Aufrufe', count( $GLOBALS['calls'] ), 1 );
check( 'Geocoding 429: Sperrfrist fuer Open-Meteo', backoff_ttl( 'open_meteo' ), 15 * MINUTE_IN_SECONDS );

reset_world( [ 'forecast_location' => 'manual', 'forecast_city' => 'Leipzig', 'forecast_country' => 'DE' ] );
$GLOBALS['http'] = [ resp( 200, '{"generationtime_ms":0.2}' ) ];
$g3 = NAWS_Forecast::get_forecast( 5 );
check( 'Geocoding ohne Treffer: weiter "Location not found"', strpos( (string) ( $g3['error'] ?? '' ), 'not found' ) !== false, true );

// ── 8. Der reine Entscheider ────────────────────────────────────────────
check( 'backoff 429 ohne Angabe',      NAWS_Forecast::backoff_seconds( 429, '' ), 900 );
check( 'backoff 429 Retry-After 60',   NAWS_Forecast::backoff_seconds( 429, '60' ), 60 );
check( 'backoff 429 Retry-After 5 -> mind. 60', NAWS_Forecast::backoff_seconds( 429, '5' ), 60 );
check( 'backoff 429 Retry-After 2 Tage -> hoechstens 6 h', NAWS_Forecast::backoff_seconds( 429, '172800' ), 6 * HOUR_IN_SECONDS );
check( 'backoff 429 Retry-After Unsinn', NAWS_Forecast::backoff_seconds( 429, 'bald' ), 900 );
check( 'backoff 503',                  NAWS_Forecast::backoff_seconds( 503, '' ), 300 );
check( 'backoff Netzfehler (0)',       NAWS_Forecast::backoff_seconds( 0, '' ), 300 );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
