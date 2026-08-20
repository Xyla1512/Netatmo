<?php
/**
 * Tests for NAWS_Rest_API::authenticate().
 *
 * The key travels in the X-NAWS-Key header and nowhere else. It used to
 * be accepted from ?api_key= as well, which is convenient and wrong: a
 * secret in a query string is written down by access logs, by the
 * Referer header, by browser history and by every proxy and CDN in
 * between. RFC 6750 §5.3 says the same about bearer tokens. Every route
 * here is GET, so that parameter was always a query string.
 *
 * The other case is what a request can do to the comparison itself.
 * get_param() and get_header() do not have to return a string —
 * ?api_key[]=x hands over an array, which empty() waves through and
 * hash_equals() rejects with a TypeError. That turns an unauthenticated
 * 401 into a fatal 500, so the value is pinned to a string first.
 *
 *   php tests/test-rest-auth.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options']    = [];
$GLOBALS['naws_test_transients'] = [];

// ── Minimal WordPress surface ────────────────────────────────────────────
function get_option( $key, $default = false ) {
    return $GLOBALS['naws_test_options'][ $key ] ?? $default;
}
function get_transient( $key ) {
    return $GLOBALS['naws_test_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl = 0 ) {
    $GLOBALS['naws_test_transients'][ $key ] = $value;
    return true;
}
function wp_parse_args( $args, $defaults = [] ) {
    return array_merge( $defaults, (array) $args );
}
function add_action( ...$a ) {}

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code = '', $message = '', $data = [] ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_status() { return $this->data['status'] ?? 0; }
}

/** Steht fuer die echte Anfrage: Header und Parameter, sonst nichts. */
class WP_REST_Request {
    private $headers;
    private $params;
    public function __construct( array $headers = [], array $params = [] ) {
        $this->headers = $headers;
        $this->params  = $params;
    }
    public function get_header( $name ) { return $this->headers[ $name ] ?? null; }
    public function get_param( $name )  { return $this->params[ $name ] ?? null; }
}

class NAWS_Crypto {
    public static function decrypt( $v ) { return $v; }
    public static function is_encrypted( $v ) { return false; }
}

require_once __DIR__ . '/../includes/class-naws-rest-api.php';

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

const SCHLUESSEL = 'naws_0123456789abcdef0123456789abcdef0123456789abcd';

/** Setzt den hinterlegten Schluessel und leert die Ratenzaehler. */
function konfiguriere( string $key ): void {
    $GLOBALS['naws_test_options']['naws_rest_api'] = [
        'enabled'    => true,
        'api_key'    => $key,
        'rate_limit' => 60,
    ];
    $GLOBALS['naws_test_transients'] = [];
}

/** Kurzform: Ergebnis von authenticate() als Fehlercode oder true. */
function ergebnis( array $headers = [], array $params = [] ) {
    $r = NAWS_Rest_API::authenticate( new WP_REST_Request( $headers, $params ) );
    return $r instanceof WP_Error ? $r->get_error_code() . '/' . $r->get_status() : $r;
}

echo "\nNAWS_Rest_API::authenticate()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Der Header ist der einzige Weg ───────────────────────────────────────
konfiguriere( SCHLUESSEL );
check( 'der richtige Schluessel im Header laesst durch',
    ergebnis( [ 'X-NAWS-Key' => SCHLUESSEL ] ), true );
check( 'ein falscher Schluessel im Header nicht',
    ergebnis( [ 'X-NAWS-Key' => 'naws_falsch' ] ), 'naws_unauthorized/401' );
check( 'gar kein Schluessel ebenfalls nicht',
    ergebnis(), 'naws_unauthorized/401' );

// ── Der Query-Parameter wird nicht mehr angenommen ───────────────────────
// Der eigentliche Punkt: derselbe, richtige Schluessel — nur am falschen
// Ort. Frueher liess ihn das durch.
check( 'der richtige Schluessel als Parameter wird abgewiesen',
    ergebnis( [], [ 'api_key' => SCHLUESSEL ] ), 'naws_unauthorized/401' );
check( 'auch wenn der Header leer mitgeschickt wird',
    ergebnis( [ 'X-NAWS-Key' => '' ], [ 'api_key' => SCHLUESSEL ] ), 'naws_unauthorized/401' );

// ── Was kein String ist, darf nichts zum Absturz bringen ─────────────────
// ?api_key[]=x bzw. ein doppelter Header: empty() winkt ein nicht-leeres
// Array durch, hash_equals() wirft darauf einen TypeError — aus der 401
// wuerde ein Fatal 500, unauthentifiziert ausloesbar.
check( 'ein Array im Header ergibt 401, keinen Absturz',
    ergebnis( [ 'X-NAWS-Key' => [ SCHLUESSEL ] ] ), 'naws_unauthorized/401' );
check( 'ein Array im Parameter ebenso',
    ergebnis( [], [ 'api_key' => [ SCHLUESSEL ] ] ), 'naws_unauthorized/401' );

// ── Ohne hinterlegten Schluessel ist der Dienst nicht eingerichtet ───────
konfiguriere( '' );
check( 'ohne erzeugten Schluessel antwortet der Dienst mit 503',
    ergebnis( [ 'X-NAWS-Key' => SCHLUESSEL ] ), 'naws_api_not_configured/503' );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
