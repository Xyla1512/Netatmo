<?php
/**
 * Tests fuer GET /naws/v1/modules (NAWS_Rest_API::endpoint_modules).
 *
 * Der Fehler, der diese Datei rechtfertigt: der Endpunkt zerlegte
 * data_types mit explode( ',', ... ), als waere es noch die Spalte aus der
 * Datenbank. get_modules() liefert es aber laengst als Liste (json_decode
 * in NAWS_Database). Unter PHP 8 ist explode() auf ein Array ein TypeError
 * — jeder Aufruf von /modules endete mit HTTP 500.
 *
 *   php tests/test-rest-modules.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

function add_action( ...$a ) {}
function rest_ensure_response( $d ) { return $d; }
class WP_REST_Request {
    public function get_param( $k ) { return null; }
}
class NAWS_Database {
    public static function get_modules( $active_only = false ): array {
        return [
            [ 'module_id' => '02:00:00:00:00:02', 'module_type' => 'NAModule1', 'module_name' => 'Aussen',
              'custom_name' => 'Garten', 'display_name' => 'Garten',
              'data_types' => [ 'Temperature', 'Humidity' ], 'last_seen' => '1741420500', 'firmware' => '50', 'battery_vp' => '5600' ],
            [ 'module_id' => '70:ee:50:00:00:01', 'module_type' => 'NAMain', 'module_name' => 'Basis',
              'custom_name' => '', 'display_name' => 'Basis',
              'data_types' => [], 'last_seen' => '', 'firmware' => null, 'battery_vp' => null ],
        ];
    }
}
class NAWS_Helpers {
    public static function display_name( array $m ): string { return (string) ( $m['display_name'] ?? $m['module_name'] ); }
}
require_once dirname( __DIR__ ) . '/includes/class-naws-rest-api.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nGET /modules\n" . str_repeat( '-', 74 ) . "\n";
try {
    $out = NAWS_Rest_API::endpoint_modules( new WP_REST_Request() );
} catch ( \Throwable $e ) {
    $out = null;
    check( 'der Endpunkt laeuft ohne Fehler durch', get_class( $e ) . ': ' . $e->getMessage(), 'kein Fehler' );
}
$mods = $out['modules'] ?? [];
check( 'beide Module kommen zurueck', count( $mods ), 2 );
check( 'data_types ist die Liste aus get_modules()', $mods[0]['data_types'] ?? null, [ 'Temperature', 'Humidity' ] );
check( 'eine leere Liste bleibt eine leere Liste', $mods[1]['data_types'] ?? null, [] );
check( 'module_name bleibt der Netatmo-Name', $mods[0]['module_name'] ?? null, 'Aussen' );
check( 'display_name traegt den Anzeigenamen', $mods[0]['display_name'] ?? null, 'Garten' );
check( 'last_seen als Zahl', $mods[0]['last_seen'] ?? null, 1741420500 );
check( 'leeres last_seen wird null', array_key_exists( 'last_seen', $mods[1] ?? [] ) ? $mods[1]['last_seen'] : 'fehlt', null );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
