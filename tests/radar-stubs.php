<?php
/**
 * Gemeinsame Stubs fuer die Regenradar-Tests: Optionen, Transients,
 * Uploads, ein WP_Filesystem im Speicher (keine Datei auf der Platte),
 * HTTP-Antworten nach URL-Anfang und Zeitplan.
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ ); }
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' )   || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' )    || define( 'DAY_IN_SECONDS', 86400 );
defined( 'NAWS_VERSION' )      || define( 'NAWS_VERSION', 'test' );

$GLOBALS['opts']        = [ 'naws_settings' => [ 'radar_enabled' => 1 ], 'time_format' => 'H:i' ];
$GLOBALS['transients']  = [];
$GLOBALS['http_routes'] = [];
$GLOBALS['calls']       = [];
$GLOBALS['logged']      = [];
$GLOBALS['scheduled']   = [];
$GLOBALS['loc']         = [ 'lat' => 51.35, 'lon' => 12.37, 'name' => 'Leipzig' ];
$GLOBALS['is_admin']    = false;

function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ][0] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = [ $v, $ttl ]; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function wp_upload_dir( $time = null, $create = true ) { return [ 'basedir' => '/up', 'baseurl' => 'https://example.org/wp-content/uploads' ]; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_mkdir_p( $d ) { return true; }
function home_url( $p = '' ) { return 'https://example.org' . $p; }
function rest_url( $p = '' ) { return 'https://example.org/wp-json/' . ltrim( $p, '/' ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); }
function current_user_can( $cap ) { return $GLOBALS['is_admin']; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function absint( $v ) { return abs( (int) $v ); }
function disabled( $d ) { if ( $d ) { echo ' disabled="disabled"'; } }
function admin_url( $p = '' ) { return 'https://example.org/wp-admin/' . $p; }
function wp_next_scheduled( $hook, $args = [] ) { return $GLOBALS['scheduled'][ $hook . json_encode( $args ) ] ?? false; }
function wp_schedule_single_event( $ts, $hook, $args = [] ) { $GLOBALS['scheduled'][ $hook . json_encode( $args ) ] = $ts; return true; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {
    public $code; public $msg; public $data;
    public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->msg = $m; $this->data = $d; }
    public function get_error_message() { return $this->msg; }
    public function get_error_data() { return $this->data; }
}
function wp_remote_get( $url, $args = [] ) {
    $GLOBALS['calls'][] = $url;
    foreach ( $GLOBALS['http_routes'] as $prefix => $resp ) {
        if ( 0 === strpos( $url, $prefix ) ) { return is_callable( $resp ) ? $resp( $url ) : $resp; }
    }
    return new WP_Error( 'none', 'no route for ' . $url );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? ( $r['headers'][ strtolower( $h ) ] ?? '' ) : ''; }
function resp( int $code, string $body, array $headers = [] ): array { return [ 'code' => $code, 'body' => $body, 'headers' => $headers ]; }
function png( string $tag = '' ): array { return resp( 200, "\x89PNG\r\n\x1a\n" . $tag, [ 'content-type' => 'image/png' ] ); }
function calls_to( string $prefix ): int { return count( array_filter( $GLOBALS['calls'], fn( $u ) => 0 === strpos( $u, $prefix ) ) ); }

class NAWS_Logger {
    public static function error( $c, $m, $d = [] ) { $GLOBALS['logged'][] = $m; }
    public static function warning( $c, $m, $d = [] ) { $GLOBALS['logged'][] = $m; }
    public static function info( $c, $m, $d = [] ) { $GLOBALS['logged'][] = $m; }
}
class NAWS_Forecast {
    public static function backoff_seconds( int $code, string $retry_after ): int {
        if ( 429 === $code ) { return is_numeric( $retry_after ) ? max( 60, min( 21600, (int) $retry_after ) ) : 900; }
        return 300;
    }
    public static function resolve_location(): array { return $GLOBALS['loc']; }
}

/** WP_Filesystem im Speicher: Pfad => Inhalt. Ordner gibt es nur implizit. */
class Fake_FS {
    public $files = [];
    public function put_contents( $f, $c, $mode = false ) { $this->files[ $f ] = (string) $c; return true; }
    public function get_contents( $f ) { return $this->files[ $f ] ?? false; }
    public function exists( $f ) {
        if ( isset( $this->files[ $f ] ) ) { return true; }
        $p = rtrim( $f, '/' ) . '/';
        foreach ( array_keys( $this->files ) as $k ) { if ( 0 === strpos( $k, $p ) ) { return true; } }
        return false;
    }
    public function delete( $f, $recursive = false, $type = false ) {
        unset( $this->files[ $f ] );
        if ( $recursive ) {
            $p = rtrim( $f, '/' ) . '/';
            foreach ( array_keys( $this->files ) as $k ) { if ( 0 === strpos( $k, $p ) ) { unset( $this->files[ $k ] ); } }
        }
        return true;
    }
    public function dirlist( $path, $hidden = true, $recursive = false ) {
        $p   = rtrim( $path, '/' ) . '/';
        $out = [];
        foreach ( $this->files as $k => $c ) {
            if ( 0 !== strpos( $k, $p ) ) { continue; }
            $parts = explode( '/', substr( $k, strlen( $p ) ) );
            $name  = array_shift( $parts );
            if ( ! $parts ) { $out[ $name ] = [ 'name' => $name, 'type' => 'f', 'size' => strlen( $c ) ]; continue; }
            $out[ $name ] = $out[ $name ] ?? [ 'name' => $name, 'type' => 'd', 'size' => 0, 'files' => [] ];
        }
        if ( $recursive ) {
            foreach ( $out as $name => $e ) { if ( 'd' === $e['type'] ) { $out[ $name ]['files'] = $this->dirlist( $p . $name, $hidden, true ); } }
        }
        return $out ?: false;
    }
}
$GLOBALS['wp_filesystem'] = new Fake_FS();
function fs(): Fake_FS { return $GLOBALS['wp_filesystem']; }

require_once __DIR__ . '/i18n-stubs.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function done(): void {
    global $passed, $failed;
    echo "\n" . str_repeat( '-', 74 ) . "\n";
    printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
    exit( $failed > 0 ? 1 : 0 );
}
