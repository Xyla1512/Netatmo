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
 * get_param() hands back whatever arrived, so ?api_key[]=x delivers an
 * array — which empty() waves through and hash_equals() rejects with a
 * TypeError, trading an unauthenticated 401 for a fatal 500. That was
 * the parameter's doing, not the header's: get_header() joins its values
 * and always returns a string or null. Dropping the parameter closes the
 * path; pinning the value to a string keeps it closed.
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

/**
 * Steht fuer die echte Anfrage: Header und Parameter, sonst nichts.
 *
 * Die beiden Zugriffe verhalten sich unterschiedlich, und genau darauf
 * kommt es hier an — gemessen an der echten WP_REST_Request unter
 * WordPress 7.1:
 *
 *   get_header() fuehrt Werte intern als Liste und gibt sie mit Komma
 *   verbunden zurueck: ['aaa'] wird 'aaa', ['aaa','bbb'] wird 'aaa,bbb',
 *   ein fehlender Header wird NULL. Ein Array kommt nie heraus.
 *
 *   get_param() reicht durch, was ankam. ?api_key[]=aaa ergibt ein Array.
 */
class WP_REST_Request {
    private $headers;
    private $params;
    public function __construct( array $headers = [], array $params = [] ) {
        $this->headers = $headers;
        $this->params  = $params;
    }
    public function get_header( $name ) {
        if ( ! isset( $this->headers[ $name ] ) ) {
            return null;
        }
        $value = $this->headers[ $name ];
        return is_array( $value ) ? implode( ',', $value ) : $value;
    }
    public function get_param( $name ) { return $this->params[ $name ] ?? null; }
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
// Der Absturzweg lief ueber den Parameter, nicht ueber den Header:
// ?api_key[]=x liefert aus get_param() ein Array, das empty() durchwinkt
// und hash_equals() mit einem TypeError quittiert — aus der 401 wuerde
// ein Fatal 500, unauthentifiziert ausloesbar. Der Parameter wird jetzt
// gar nicht mehr gelesen, der Weg ist also zu.
check( 'ein Array im Parameter ergibt 401, keinen Absturz',
    ergebnis( [], [ 'api_key' => [ SCHLUESSEL ] ] ), 'naws_unauthorized/401' );

// Der Header dagegen kommt immer als String an, weil get_header() die
// Werte verbindet. Ein einzeln gesetzter Wert bleibt damit gueltig —
// alles andere waere eine Behauptung ueber die Attrappe, nicht ueber
// WordPress.
check( 'ein einzeln gelisteter Header-Wert bleibt derselbe Schluessel',
    ergebnis( [ 'X-NAWS-Key' => [ SCHLUESSEL ] ] ), true );
check( 'ein doppelt gesendeter Header wird zu einem falschen Schluessel',
    ergebnis( [ 'X-NAWS-Key' => [ SCHLUESSEL, 'zweiter' ] ] ), 'naws_unauthorized/401' );

// ── Ohne hinterlegten Schluessel ist der Dienst nicht eingerichtet ───────
konfiguriere( '' );
check( 'ohne erzeugten Schluessel antwortet der Dienst mit 503',
    ergebnis( [ 'X-NAWS-Key' => SCHLUESSEL ] ), 'naws_api_not_configured/503' );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
