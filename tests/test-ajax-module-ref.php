<?php
/**
 * Tests fuer die Aufloesung der Modulreferenzen in NAWS_Ajax.
 *
 * Die vier oeffentlichen Endpunkte nahmen bisher eine module_id — also eine
 * MAC-Adresse — direkt vom Browser entgegen und reichten sie an die
 * Datenbankabfrage weiter. Jetzt kommt eine Referenz an, und der Server
 * loest sie auf.
 *
 * Der heikle Fall ist die Referenz, die auf nichts passt. Ein leerer Filter
 * heisst in allen vier Abfragen "alle Module"; wenn eine unbekannte
 * Referenz stillschweigend zum leeren Filter wird, beantwortet der Server
 * die Frage nach einem Modul mit den Messwerten aller. Deshalb muss sie
 * abgelehnt werden, und deshalb steht dieser Fall hier fuer jeden Endpunkt.
 *
 * Die Rueckgabe muss ausserdem selbst frei von MACs sein — die Antwort von
 * get_latest() trug die module_id jeder einzelnen Messung, und der
 * Debug-Block von get_chart_data() spiegelte den Filter zurueck.
 *
 *   php tests/test-ajax-module-ref.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'NAWS_TABLE_DAILY', 'naws_daily_summary' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['mods'] = [
    [ 'module_id' => '70:ee:50:a9:5a:08', 'module_type' => 'NAMain',    'module_name' => 'Basis',    'is_active' => 1 ],
    [ 'module_id' => '02:00:00:a9:5a:08', 'module_type' => 'NAModule1', 'module_name' => 'Aussen',   'is_active' => 1 ],
    [ 'module_id' => '03:00:00:0d:aa:ca', 'module_type' => 'NAModule4', 'module_name' => 'Gast',     'is_active' => 1 ],
];

/** Was der Endpunkt zurueckgegeben hat, und womit er die Datenbank gefragt hat. */
$GLOBALS['sent']  = null;
$GLOBALS['asked'] = null;

// ── Minimale WordPress-Oberflaeche ───────────────────────────────────────
class NAWS_Test_Sent extends Exception {}

function add_action( ...$a ) {}
function check_ajax_referer( ...$a ) { return true; }
function nocache_headers() {}
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t ?? time() ); }
function get_option( $k, $d = false ) { return $d; }
// Die Uebersetzungsfunktionen kommen aus dem gemeinsamen Stub: seit 1.9.9
// uebersetzt das Plugin ueber gettext und nicht mehr ueber NAWS_Lang.
require_once __DIR__ . '/i18n-stubs.php';
function wp_send_json_success( $data = null ) {
    $GLOBALS['sent'] = [ 'ok' => true, 'data' => $data ];
    throw new NAWS_Test_Sent();
}
function wp_send_json_error( $data = null, $code = null ) {
    $GLOBALS['sent'] = [ 'ok' => false, 'data' => $data ];
    throw new NAWS_Test_Sent();
}

class NAWS_Database {
    public static function get_modules( $active_only = false ) { return $GLOBALS['mods']; }
    public static function get_readings( $args ) {
        $GLOBALS['asked'] = $args['module_id'];
        return [ [ 'module_id' => '03:00:00:0d:aa:ca', 'parameter' => 'Temperature', 'value' => '21.5', 'recorded_at' => 1786205132 ] ];
    }
    public static function get_daily_chart_data( $args ) {
        $GLOBALS['asked'] = $args['module_id'];
        return [ 'series' => [] ];
    }
    /**
     * Die Zeilen kommen so aus der Tabelle, wie sie dort stehen — mit
     * Primaerschluessel, station_id und Anlagezeitpunkt. Genau daran hing
     * der Fehler: array_merge() reichte die ganze Zeile an den Browser
     * weiter, und station_id ist die MAC der Basisstation.
     */
    public static function get_latest_readings( $module_id = null ) {
        $GLOBALS['asked'] = $module_id;
        return [
            [ 'id' => '2041', 'station_id' => '70:ee:50:a9:5a:08', 'module_id' => '02:00:00:a9:5a:08', 'parameter' => 'Temperature', 'value' => '8.4', 'recorded_at' => 1786205132, 'created_at' => '2026-08-30 19:33:55' ],
            [ 'id' => '2044', 'station_id' => '70:ee:50:a9:5a:08', 'module_id' => '03:00:00:0d:aa:ca', 'parameter' => 'Temperature', 'value' => '21.5', 'recorded_at' => 1786205132, 'created_at' => '2026-08-30 19:33:55' ],
        ];
    }
    public static function get_rain_rolling_24h( $module_id ) { return null; }
}
class NAWS_Logger {
    public static function error( ...$a ) {}
    public static function debug( ...$a ) {}
    public static function info( ...$a ) {}
}

class NAWS_Test_Wpdb {
    public $prefix     = 'wp_';
    public $last_error = '';
    public $args       = [];
    public function prepare( $q, ...$a ) { $this->args = is_array( $a[0] ?? null ) ? $a[0] : $a; return $q; }
    public function get_results( $q, $mode = null ) { return []; }
}
$GLOBALS['wpdb'] = new NAWS_Test_Wpdb();

require_once __DIR__ . '/../includes/class-naws-helpers.php';
require_once __DIR__ . '/../includes/class-naws-ajax.php';

$passed = 0;
$failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Ruft einen Endpunkt mit einem POST-Rumpf auf und liefert dessen Antwort. */
function call( string $method, array $post ): array {
    $GLOBALS['sent']  = null;
    $GLOBALS['asked'] = 'nicht gefragt';
    $_POST            = $post;
    try {
        NAWS_Ajax::instance()->$method();
    } catch ( NAWS_Test_Sent $e ) {
        // wp_send_json_* beendet in WordPress die Anfrage; hier tut es das auch.
    }
    $_POST = [];
    return $GLOBALS['sent'] ?? [ 'ok' => null, 'data' => null ];
}

/** Findet jede MAC-Adresse in einer Antwort. */
function macs( $data ): array {
    preg_match_all( '/(?:[0-9a-f]{2}:){5}[0-9a-f]{2}/i', wp_json_encode_test( $data ), $m );
    return array_values( array_unique( $m[0] ) );
}
function wp_json_encode_test( $d ) { return json_encode( $d ); }

echo "\nNAWS_Ajax: Modulreferenzen\n" . str_repeat( '-', 74 ) . "\n";

// ── get_chart_data: der Endpunkt hinter jedem Kachel-Diagramm ────────────
$r = call( 'get_chart_data', [ 'module_ref' => 'in-gast', 'parameter' => [ 'Temperature' ] ] );
check( 'die Referenz wird zur module_id aufgeloest', $GLOBALS['asked'], '03:00:00:0d:aa:ca' );
check( 'und die Antwort kommt durch', $r['ok'], true );
check( 'ohne eine MAC zurueckzuspiegeln', macs( $r['data'] ), [] );

$r = call( 'get_chart_data', [ 'module_ref' => 'outdoor', 'parameter' => [ 'Temperature' ] ] );
check( 'auch die feste Referenz loest auf', $GLOBALS['asked'], '02:00:00:a9:5a:08' );

$r = call( 'get_chart_data', [ 'parameter' => [ 'Temperature' ] ] );
check( 'ohne Modulangabe bleibt der Filter offen', $GLOBALS['asked'], null );
check( 'und die Antwort kommt durch', $r['ok'], true );

$r = call( 'get_chart_data', [ 'module_ref' => 'in-keller', 'parameter' => [ 'Temperature' ] ] );
check( 'eine unbekannte Referenz wird abgelehnt', $r['ok'], false );
check( 'und fragt die Datenbank gar nicht erst', $GLOBALS['asked'], 'nicht gefragt' );

$r = call( 'get_chart_data', [ 'module_id' => '03:00:00:0d:aa:ca', 'parameter' => [ 'Temperature' ] ] );
check( 'eine gecachte Seite mit MAC funktioniert weiter', $GLOBALS['asked'], '03:00:00:0d:aa:ca' );

// ── get_history_data: die Jahresvergleiche ───────────────────────────────
$r = call( 'get_history_data', [ 'module_ref' => 'in-gast', 'fields' => [ 'indoor_temp_avg' ], 'year_from' => 2025, 'year_to' => 2025 ] );
check( 'der Jahresvergleich filtert auf das aufgeloeste Modul',
    in_array( '03:00:00:0d:aa:ca', $GLOBALS['wpdb']->args, true ), true );
check( 'und antwortet', $r['ok'], true );

$r = call( 'get_history_data', [ 'module_ref' => 'in-keller', 'fields' => [ 'indoor_temp_avg' ] ] );
check( 'auch hier wird eine unbekannte Referenz abgelehnt', $r['ok'], false );

// ── get_daily_data ───────────────────────────────────────────────────────
$r = call( 'get_daily_data', [ 'module_ref' => 'indoor' ] );
check( 'die Tagesdaten loesen die Referenz auf', $GLOBALS['asked'], '70:ee:50:a9:5a:08' );
$r = call( 'get_daily_data', [ 'module_ref' => 'in-keller' ] );
check( 'und lehnen eine unbekannte ab', $r['ok'], false );

// ── get_latest: die Messwerte, aus denen das Dashboard seine Kacheln baut ─
$r = call( 'get_latest', [] );
check( 'die Messwerte kommen', count( $r['data']['readings'] ?? [] ), 2 );
check( 'keine einzige MAC in der Antwort', macs( $r['data'] ), [] );
check( 'jede Messung nennt stattdessen ihre Referenz',
    array_column( $r['data']['readings'] ?? [], 'module_ref' ),
    [ 'outdoor', 'in-gast' ] );
check( 'der Anzeigename bleibt, er steht ohnehin auf der Seite',
    array_column( $r['data']['readings'] ?? [], 'module_name' ),
    [ 'Aussen', 'Gast' ] );

// Die Antwort beschreibt eine Messung, nicht eine Tabellenzeile. Der
// Primaerschluessel, der Anlagezeitpunkt und vor allem die station_id —
// die MAC der Basisstation — haben im Browser nichts zu suchen.
check( 'eine Messung traegt genau die Felder, die die Anzeige braucht',
    array_keys( $r['data']['readings'][0] ?? [] ),
    [ 'parameter', 'value', 'unit', 'icon', 'recorded_at', 'module_ref', 'module_name', 'module_type' ] );

$r = call( 'get_latest', [ 'module_ref' => 'in-gast' ] );
check( 'get_latest kann weiter auf ein Modul eingeschraenkt werden',
    $GLOBALS['asked'], '03:00:00:0d:aa:ca' );
$r = call( 'get_latest', [ 'module_ref' => 'in-keller' ] );
check( 'und lehnt eine unbekannte Referenz ab', $r['ok'], false );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
