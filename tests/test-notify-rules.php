<?php
/**
 * Tests fuer NAWS_Notify_Rules: Katalog, Einheiten, Bedingungen und die
 * Zustandsmaschine der E-Mail-Benachrichtigungen. Alles rein, ohne
 * WordPress; die Uhrzeit wird hineingereicht.
 *
 *   php tests/test-notify-rules.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-naws-notify-rules.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$NOW = 1789145173;
$U   = [ 'temperature_unit' => 'C', 'wind_unit' => 'kmh', 'rain_unit' => 'mm' ];
function mod( string $type, array $extra = [] ): array {
    return $extra + [ 'module_id' => 'id-' . $type, 'station_id' => 'base', 'module_name' => 'M ' . $type, 'module_type' => $type,
        'battery_percent' => null, 'rf_status' => null, 'wifi_status' => null, 'reachable' => null,
        'last_status_store' => null, 'last_seen' => null, 'last_message' => null, 'readings' => [] ];
}

echo "\nKatalog\n" . str_repeat( '-', 74 ) . "\n";
$cat = NAWS_Notify_Rules::catalog();
check( 'zehn Regeln in dieser Reihenfolge', array_keys( $cat ), [ 'battery', 'rf', 'wifi', 'station_silent', 'module_silent', 'sync_failed', 'auth_required', 'frost', 'gust', 'rain' ] );
$complete = true;
foreach ( $cat as $id => $d ) {
    foreach ( [ 'group', 'scope', 'types', 'param', 'kind', 'default', 'hold_on', 'hold_off', 'clears' ] as $k ) {
        if ( ! array_key_exists( $k, $d ) ) { $complete = false; echo "    fehlt: $id.$k\n"; }
    }
}
check( 'jede Regel hat alle Schluessel', $complete, true );
check( 'Gruppen',        array_map( fn( $d ) => $d['group'],    $cat ), [ 'battery' => 'station', 'rf' => 'station', 'wifi' => 'station', 'station_silent' => 'station', 'module_silent' => 'station', 'sync_failed' => 'station', 'auth_required' => 'station', 'frost' => 'weather', 'gust' => 'weather', 'rain' => 'weather' ] );
check( 'Scopes',         array_map( fn( $d ) => $d['scope'],    $cat ), [ 'battery' => 'module', 'rf' => 'module', 'wifi' => 'station', 'station_silent' => 'station', 'module_silent' => 'module', 'sync_failed' => 'site', 'auth_required' => 'site', 'frost' => 'module', 'gust' => 'module', 'rain' => 'module' ] );
check( 'Vorgaben',       array_map( fn( $d ) => $d['default'],  $cat ), [ 'battery' => 20, 'rf' => 'low', 'wifi' => 'bad', 'station_silent' => 60, 'module_silent' => 60, 'sync_failed' => null, 'auth_required' => null, 'frost' => 0.0, 'gust' => 60.0, 'rain' => 20.0 ] );
check( 'Beharrungen an', array_map( fn( $d ) => $d['hold_on'],  $cat ), [ 'battery' => 0, 'rf' => 1800, 'wifi' => 1800, 'station_silent' => 0, 'module_silent' => 0, 'sync_failed' => 0, 'auth_required' => 0, 'frost' => 0, 'gust' => 0, 'rain' => 0 ] );
check( 'Beharrungen aus',array_map( fn( $d ) => $d['hold_off'], $cat ), [ 'battery' => 0, 'rf' => 1800, 'wifi' => 1800, 'station_silent' => 0, 'module_silent' => 0, 'sync_failed' => 0, 'auth_required' => 0, 'frost' => 3600, 'gust' => 3600, 'rain' => 0 ] );
check( 'nur Regen ohne Entwarnung', array_keys( array_filter( $cat, fn( $d ) => ! $d['clears'] ) ), [ 'rain' ] );
check( 'Stufen Funk und WLAN', [ $cat['rf']['levels'], $cat['wifi']['levels'] ], [ [ 'low' => [ 90, 80 ], 'medium' => [ 80, 70 ] ], [ 'bad' => [ 86, 71 ], 'average' => [ 71, 56 ] ] ] );
$def = NAWS_Notify_Rules::defaults();
check( 'defaults(): alles aus, Empfaenger leer', [ $def['recipients'], $def['rules']['battery'], $def['rules']['rf'], $def['rules']['sync_failed'], $def['rules']['gust'] ], [ [], [ 'enabled' => 0, 'threshold' => 20 ], [ 'enabled' => 0, 'level' => 'low' ], [ 'enabled' => 0 ], [ 'enabled' => 0, 'threshold' => 60.0 ] ] );

echo "\nEinheiten\n" . str_repeat( '-', 74 ) . "\n";
$F = [ 'temperature_unit' => 'F', 'wind_unit' => 'mph', 'rain_unit' => 'in' ];
check( 'to_base: 32 F = 0 C',            round( NAWS_Notify_Rules::to_base( 'temp', 32.0, $F ), 3 ), 0.0 );
check( 'to_base: 37.28 mph = 60 km/h',   round( NAWS_Notify_Rules::to_base( 'wind', 37.28, $F ), 1 ), 60.0 );
check( 'to_base: 10 m/s = 36 km/h',      round( NAWS_Notify_Rules::to_base( 'wind', 10.0, [ 'wind_unit' => 'ms' ] + $U ), 1 ), 36.0 );
check( 'to_base: 20 kn = 37 km/h',       round( NAWS_Notify_Rules::to_base( 'wind', 20.0, [ 'wind_unit' => 'kn' ] + $U ), 1 ), 37.0 );
check( 'to_base: 1 in = 25.4 mm',        round( NAWS_Notify_Rules::to_base( 'rain', 1.0, $F ), 2 ), 25.4 );
check( 'to_base: Prozent unveraendert',  NAWS_Notify_Rules::to_base( 'percent', 20.0, $F ), 20.0 );
foreach ( [ [ 'temp', -7.5 ], [ 'wind', 60.0 ], [ 'rain', 20.0 ] ] as [ $k, $v ] ) {
    // to_display() rundet auf die Anzeigestelle; zurueckgerechnet bleibt ein Rest unter 0,1.
    check( "hin und zurueck $k", abs( NAWS_Notify_Rules::to_base( $k, NAWS_Notify_Rules::to_display( $k, $v, $F ), $F ) - $v ) < 0.1, true );
}
check( 'unit_label', [ NAWS_Notify_Rules::unit_label( 'temp', $U ), NAWS_Notify_Rules::unit_label( 'temp', $F ), NAWS_Notify_Rules::unit_label( 'wind', $U ), NAWS_Notify_Rules::unit_label( 'wind', $F ), NAWS_Notify_Rules::unit_label( 'rain', $F ), NAWS_Notify_Rules::unit_label( 'percent', $U ), NAWS_Notify_Rules::unit_label( 'minutes', $U ), NAWS_Notify_Rules::unit_label( 'level', $U ) ], [ '°C', '°F', 'km/h', 'mph', 'in', '%', 'min', '' ] );

echo "\nBedingungen\n" . str_repeat( '-', 74 ) . "\n";
$c = fn( string $r, array $m, array $cfg, bool $active = false ) => NAWS_Notify_Rules::condition( $r, $m, $cfg, $active, $GLOBALS['NOW'], 1200 );
check( 'battery: 23 < 25 warnt',              $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 23 ] ), [ 'threshold' => 25 ] ), true );
check( 'battery: 25 warnt nicht',             $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 25 ] ), [ 'threshold' => 25 ] ), false );
check( 'battery aktiv: 30 haelt (Hysterese)', $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 30 ] ), [ 'threshold' => 25 ], true ), true );
check( 'battery aktiv: 35 entwarnt',          $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 35 ] ), [ 'threshold' => 25 ], true ), false );
check( 'battery: Wert fehlt -> ausgesetzt',   $c( 'battery', mod( 'NAModule4' ), [ 'threshold' => 25 ] ), null );
check( 'battery: Basis -> ausgesetzt',        $c( 'battery', mod( 'NAMain', [ 'battery_percent' => 5 ] ), [ 'threshold' => 25 ] ), null );
check( 'rf low: 90 warnt, 89 nicht',          [ $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 90 ] ), [ 'level' => 'low' ] ), $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 89 ] ), [ 'level' => 'low' ] ) ], [ true, false ] );
check( 'rf low aktiv: 81 haelt, 80 entwarnt', [ $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 81 ] ), [ 'level' => 'low' ], true ), $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 80 ] ), [ 'level' => 'low' ], true ) ], [ true, false ] );
check( 'rf medium: 80 warnt',                 $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 80 ] ), [ 'level' => 'medium' ] ), true );
check( 'wifi bad: 86 warnt, 60 nicht',        [ $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 86 ] ), [ 'level' => 'bad' ] ), $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 60 ] ), [ 'level' => 'bad' ] ) ], [ true, false ] );
check( 'wifi bad aktiv: 72 haelt, 71 entwarnt', [ $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 72 ] ), [ 'level' => 'bad' ], true ), $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 71 ] ), [ 'level' => 'bad' ], true ) ], [ true, false ] );
check( 'wifi: Modul -> ausgesetzt',           $c( 'wifi', mod( 'NAModule1', [ 'wifi_status' => 99 ] ), [ 'level' => 'bad' ] ), null );
check( 'station_silent: reachable 0 warnt',   $c( 'station_silent', mod( 'NAMain', [ 'reachable' => 0, 'last_status_store' => $NOW - 60 ] ), [ 'minutes' => 60 ] ), true );
check( 'station_silent: 61 min alt warnt',    $c( 'station_silent', mod( 'NAMain', [ 'reachable' => 1, 'last_status_store' => $NOW - 61 * 60 ] ), [ 'minutes' => 60 ] ), true );
check( 'station_silent: 59 min alt nicht',    $c( 'station_silent', mod( 'NAMain', [ 'reachable' => 1, 'last_status_store' => $NOW - 59 * 60 ] ), [ 'minutes' => 60 ] ), false );
check( 'station_silent: nur Zeitstempel',     $c( 'station_silent', mod( 'NAMain', [ 'last_status_store' => $NOW - 10 * 60 ] ), [ 'minutes' => 60 ] ), false );
check( 'station_silent: nichts da -> ausgesetzt', $c( 'station_silent', mod( 'NAMain' ), [ 'minutes' => 60 ] ), null );
check( 'module_silent: last_message zaehlt',  $c( 'module_silent', mod( 'NAModule3', [ 'reachable' => 1, 'last_message' => $NOW - 90 * 60, 'last_seen' => $NOW ] ), [ 'minutes' => 60 ] ), true );
check( 'module_silent: Rueckfall last_seen',  $c( 'module_silent', mod( 'NAModule3', [ 'last_seen' => $NOW - 5 * 60 ] ), [ 'minutes' => 60 ] ), false );
$out = fn( float $t, int $age = 0 ) => mod( 'NAModule1', [ 'readings' => [ 'Temperature' => [ 'value' => $t, 'at' => $GLOBALS['NOW'] - $age ] ] ] );
check( 'frost: 0.0 warnt, 0.1 nicht',         [ $c( 'frost', $out( 0.0 ), [ 'threshold' => 0.0 ] ), $c( 'frost', $out( 0.1 ), [ 'threshold' => 0.0 ] ) ], [ true, false ] );
check( 'frost aktiv: 0.9 haelt, 1.1 entwarnt', [ $c( 'frost', $out( 0.9 ), [ 'threshold' => 0.0 ], true ), $c( 'frost', $out( 1.1 ), [ 'threshold' => 0.0 ], true ) ], [ true, false ] );
check( 'frost: Messwert 21 min alt -> ausgesetzt', $c( 'frost', $out( -3.0, 21 * 60 ), [ 'threshold' => 0.0 ] ), null );
check( 'frost: Messwert 19 min alt zaehlt',   $c( 'frost', $out( -3.0, 19 * 60 ), [ 'threshold' => 0.0 ] ), true );
check( 'frost: kein Messwert -> ausgesetzt',  $c( 'frost', mod( 'NAModule1' ), [ 'threshold' => 0.0 ] ), null );
$wind = fn( float $g ) => mod( 'NAModule2', [ 'readings' => [ 'GustStrength' => [ 'value' => $g, 'at' => $GLOBALS['NOW'] ] ] ] );
check( 'gust: 60 warnt, 59.9 nicht',          [ $c( 'gust', $wind( 60.0 ), [ 'threshold' => 60.0 ] ), $c( 'gust', $wind( 59.9 ), [ 'threshold' => 60.0 ] ) ], [ true, false ] );
check( 'gust aktiv: 59.9 entwarnt',           $c( 'gust', $wind( 59.9 ), [ 'threshold' => 60.0 ], true ), false );
$rain = fn( float $r ) => mod( 'NAModule3', [ 'readings' => [ 'sum_rain_24' => [ 'value' => $r, 'at' => $GLOBALS['NOW'] ] ] ] );
check( 'rain: 20 warnt, 19.9 nicht',          [ $c( 'rain', $rain( 20.0 ), [ 'threshold' => 20.0 ] ), $c( 'rain', $rain( 19.9 ), [ 'threshold' => 20.0 ] ) ], [ true, false ] );
check( 'Site-Regel per condition() -> null',  $c( 'sync_failed', mod( 'NAMain' ), [] ), null );
check( 'value_of: Batterie, Frost, Zeitstempel', [ NAWS_Notify_Rules::value_of( 'battery', mod( 'NAModule4', [ 'battery_percent' => 23 ] ) ), NAWS_Notify_Rules::value_of( 'frost', $out( -2.5 ) ), NAWS_Notify_Rules::value_of( 'station_silent', mod( 'NAMain', [ 'last_status_store' => 5 ] ) ), NAWS_Notify_Rules::value_of( 'module_silent', mod( 'NAModule1', [ 'last_seen' => 7 ] ) ) ], [ 23, -2.5, 5, 7 ] );

// ---- Teil 2 (Task 3: Zustandsmaschine) wird hier eingefuegt ----

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
