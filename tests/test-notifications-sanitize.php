<?php
/**
 * Tests fuer NAWS_Notifications::sanitize(), from_form(), split_recipients(),
 * recipients() und units(): die Option ist eine Whitelist ueber den Katalog,
 * Adressen gehen durch sanitize_email()+is_email(), Zahlen werden geklemmt,
 * Schwellen liegen in Basiseinheiten.
 *
 *   php tests/test-notifications-sanitize.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['naws_test_options'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function sanitize_email( $e )         { return preg_replace( '/[^a-z0-9._%+\-@]/i', '', trim( (string) $e ) ); }
function is_email( $e )               { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', (string) $e ); }
function absint( $v )                 { return abs( (int) $v ); }
function add_action( ...$a )          {}
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-notify-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-notifications.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$D = NAWS_Notify_Rules::defaults();

echo "\nsanitize(): Whitelist ueber den Katalog\n" . str_repeat( '-', 74 ) . "\n";
check( 'leer -> Vorgaben',                        NAWS_Notifications::sanitize( [] ), $D );
check( 'unbekannte Regel und unbekanntes Feld fallen weg', NAWS_Notifications::sanitize( [ 'rules' => [ 'moon' => [ 'enabled' => 1 ], 'battery' => [ 'enabled' => 1, 'colour' => 'red' ] ] ] )['rules']['battery'], [ 'enabled' => 1, 'threshold' => 20 ] );
check( 'enabled: "1" -> 1, fehlt -> 0, "0" -> 0', [ NAWS_Notifications::sanitize( [ 'rules' => [ 'frost' => [ 'enabled' => '1' ] ] ] )['rules']['frost']['enabled'], NAWS_Notifications::sanitize( [ 'rules' => [ 'frost' => [] ] ] )['rules']['frost']['enabled'], NAWS_Notifications::sanitize( [ 'rules' => [ 'frost' => [ 'enabled' => '0' ] ] ] )['rules']['frost']['enabled'] ], [ 1, 0, 0 ] );
$t = fn( string $rule, $v ) => NAWS_Notifications::sanitize( [ 'rules' => [ $rule => [ 'threshold' => $v ] ] ] )['rules'][ $rule ]['threshold'];
check( 'battery: 150 -> 99, 0 -> 1, "abc" -> 20, "35" -> 35', [ $t( 'battery', '150' ), $t( 'battery', '0' ), $t( 'battery', 'abc' ), $t( 'battery', '35' ) ], [ 99, 1, 20, 35 ] );
check( 'frost: -60 -> -50.0, "2.55" -> 2.6 (eine Stelle)', [ $t( 'frost', '-60' ), $t( 'frost', '2.55' ) ], [ -50.0, 2.6 ] );
check( 'gust: 0.5 -> 1.0, 999 -> 300.0',            [ $t( 'gust', '0.5' ), $t( 'gust', '999' ) ], [ 1.0, 300.0 ] );
check( 'rain: 600 -> 500.0, 0 -> 0.1',              [ $t( 'rain', '600' ), $t( 'rain', '0' ) ], [ 500.0, 0.1 ] );
$l = fn( string $rule, $v ) => NAWS_Notifications::sanitize( [ 'rules' => [ $rule => [ 'level' => $v ] ] ] )['rules'][ $rule ]['level'];
check( 'rf: medium bleibt, xxx -> low',            [ $l( 'rf', 'medium' ), $l( 'rf', 'xxx' ) ], [ 'medium', 'low' ] );
check( 'wifi: average bleibt, low -> bad',         [ $l( 'wifi', 'average' ), $l( 'wifi', 'low' ) ], [ 'average', 'bad' ] );
$m = fn( string $rule, $v ) => NAWS_Notifications::sanitize( [ 'rules' => [ $rule => [ 'minutes' => $v ] ] ] )['rules'][ $rule ]['minutes'];
check( 'minutes: 5 -> 10, 99999 -> 1440, "45" -> 45', [ $m( 'station_silent', '5' ), $m( 'module_silent', '99999' ), $m( 'station_silent', '45' ) ], [ 10, 1440, 45 ] );
check( 'Site-Regel kennt nur enabled',             NAWS_Notifications::sanitize( [ 'rules' => [ 'sync_failed' => [ 'enabled' => 1, 'threshold' => 5 ] ] ] )['rules']['sync_failed'], [ 'enabled' => 1 ] );

echo "\nEmpfaenger\n" . str_repeat( '-', 74 ) . "\n";
check( 'Text: Zeilen, Komma, Semikolon, Dublette, Ungueltiges', NAWS_Notifications::sanitize( [ 'recipients' => "a@x.de\r\nb@x.de, c@x.de; a@x.de\nkein-mail\n <d@x.de> " ] )['recipients'], [ 'a@x.de', 'b@x.de', 'c@x.de', 'd@x.de' ] );
check( 'Array (gespeichert) bleibt',              NAWS_Notifications::sanitize( [ 'recipients' => [ 'a@x.de', 'nope', 'b@x.de' ] ] )['recipients'], [ 'a@x.de', 'b@x.de' ] );
$many = implode( "\n", array_map( fn( $i ) => "u$i@x.de", range( 1, 25 ) ) );
check( 'hoechstens 20',                           count( NAWS_Notifications::sanitize( [ 'recipients' => $many ] )['recipients'] ), 20 );
check( 'split_recipients: nicht-leere Token',     NAWS_Notifications::split_recipients( "a@x.de\n\nnope,b@x.de; " ), [ 'a@x.de', 'nope', 'b@x.de' ] );
$GLOBALS['naws_test_options'] = [ 'admin_email' => 'admin@x.de' ];
check( 'recipients(): leer -> Admin-Adresse',     NAWS_Notifications::recipients(), [ 'admin@x.de' ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = [ 'recipients' => [ 'f@x.de' ] ];
check( 'recipients(): gespeicherte Liste',        NAWS_Notifications::recipients(), [ 'f@x.de' ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = 'kaputt';
check( 'get_settings(): beschaedigte Option -> Vorgaben', NAWS_Notifications::get_settings(), $D );

echo "\nfrom_form(): Anzeigeeinheit -> Basis\n" . str_repeat( '-', 74 ) . "\n";
$F = [ 'temperature_unit' => 'F', 'wind_unit' => 'mph', 'rain_unit' => 'in' ];
$s = NAWS_Notifications::from_form( "f@x.de", [ 'frost' => [ 'enabled' => '1', 'threshold' => '32' ], 'gust' => [ 'threshold' => '37.28' ], 'rain' => [ 'threshold' => '1' ], 'battery' => [ 'threshold' => '30' ] ], $F );
check( 'frost 32 F -> 0.0 C',                     $s['rules']['frost'], [ 'enabled' => 1, 'threshold' => 0.0 ] );
check( 'gust 37.28 mph -> 60.0 km/h',             $s['rules']['gust']['threshold'], 60.0 );
check( 'rain 1 in -> 25.4 mm',                    $s['rules']['rain']['threshold'], 25.4 );
check( 'Prozent bleibt',                          $s['rules']['battery']['threshold'], 30 );
check( 'Empfaenger aus dem Text',                 $s['recipients'], [ 'f@x.de' ] );
$GLOBALS['naws_test_options']['naws_settings'] = [ 'temperature_unit' => 'F', 'wind_unit' => 'kn' ];
check( 'units(): aus naws_settings mit Vorgaben', NAWS_Notifications::units(), [ 'temperature_unit' => 'F', 'wind_unit' => 'kn', 'rain_unit' => 'mm' ] );
$GLOBALS['naws_test_options']['naws_settings'] = [ 'temperature_unit' => 'C' ];
check( 'units(): fehlendes wind_unit -> kmh, ohne Warnung', NAWS_Notifications::units(), [ 'temperature_unit' => 'C', 'wind_unit' => 'kmh', 'rain_unit' => 'mm' ] );
$GLOBALS['naws_test_options']['naws_settings'] = [ 'wind_unit' => 'furlongs', 'rain_unit' => 'cups', 'temperature_unit' => 'K' ];
check( 'units(): ungueltige Werte -> Vorgaben', NAWS_Notifications::units(), [ 'temperature_unit' => 'C', 'wind_unit' => 'kmh', 'rain_unit' => 'mm' ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
