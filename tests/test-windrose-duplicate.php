<?php
/**
 * Windrose: "alles" und "dieses Jahr" nicht doppelt anbieten.
 *
 * Der Befund (Durchsicht der Seite, November 2026): beide Knoepfe zeigten
 * dieselben 35.509 Messungen — die Windrose braucht Richtungen aus den
 * Rohwerten, und die reichen auf der Seite erst bis Februar 2026 zurueck.
 * Kein Rechenfehler, aber ein Knopf, der nichts Neues zeigt.
 *
 *   php tests/test-windrose-duplicate.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
function get_option( $k, $d = false ) { return $d; }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-windrose.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$keys  = [ '7d', '30d', '90d', 'year', 'all' ];
$same  = [ 'wind|year' => [ 'n' => 35509, 'first' => 1771092285 ], 'wind|all' => [ 'n' => 35509, 'first' => 1771092285 ] ];
$diff  = [ 'wind|year' => [ 'n' => 35509, 'first' => 1771092285 ], 'wind|all' => [ 'n' => 90000, 'first' => 1711600000 ] ];
$both  = $same + [ 'gust|year' => [ 'n' => 35509, 'first' => 1771092285 ], 'gust|all' => [ 'n' => 35600, 'first' => 1771000000 ] ];

check( 'gleich: "alles" faellt weg', NAWS_Windrose::without_duplicate_period( $keys, $same, [ 'wind' ], '90d' ), [ '7d', '30d', '90d', 'year' ] );
check( 'verschieden: beide bleiben', NAWS_Windrose::without_duplicate_period( $keys, $diff, [ 'wind' ], '90d' ), $keys );
check( '"alles" ist eingestellt: "dieses Jahr" faellt weg', NAWS_Windrose::without_duplicate_period( $keys, $same, [ 'wind' ], 'all' ), [ '7d', '30d', '90d', 'all' ] );
check( 'Wind und Boeen: nur wenn beide gleich', NAWS_Windrose::without_duplicate_period( $keys, $both, [ 'wind', 'gust' ], '90d' ), $keys );
check( 'ohne Umschalter nichts zu tun', NAWS_Windrose::without_duplicate_period( [ '90d' ], [], [ 'wind' ], '90d' ), [ '90d' ] );
$tpl = file_get_contents( dirname( __DIR__ ) . '/templates/windrose.php' );
check( 'Vorlage nutzt es', str_contains( $tpl, 'NAWS_Windrose::without_duplicate_period(' ), true );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
