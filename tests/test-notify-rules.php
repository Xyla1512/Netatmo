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
check( 'dreizehn Regeln in dieser Reihenfolge', array_keys( $cat ), [ 'battery', 'rf', 'wifi', 'station_silent', 'module_silent', 'sync_failed', 'auth_required', 'frost', 'heat', 'gust', 'rain', 'rain_start', 'co2' ] );
$complete = true;
foreach ( $cat as $id => $d ) {
    foreach ( [ 'group', 'scope', 'types', 'param', 'kind', 'default', 'hold_on', 'hold_off', 'clears' ] as $k ) {
        if ( ! array_key_exists( $k, $d ) ) { $complete = false; echo "    fehlt: $id.$k\n"; }
    }
}
check( 'jede Regel hat alle Schluessel', $complete, true );
check( 'Gruppen',        array_map( fn( $d ) => $d['group'],    $cat ), [ 'battery' => 'station', 'rf' => 'station', 'wifi' => 'station', 'station_silent' => 'station', 'module_silent' => 'station', 'sync_failed' => 'station', 'auth_required' => 'station', 'frost' => 'weather', 'heat' => 'weather', 'gust' => 'weather', 'rain' => 'weather', 'rain_start' => 'weather', 'co2' => 'weather' ] );
check( 'Scopes',         array_map( fn( $d ) => $d['scope'],    $cat ), [ 'battery' => 'module', 'rf' => 'module', 'wifi' => 'station', 'station_silent' => 'station', 'module_silent' => 'module', 'sync_failed' => 'site', 'auth_required' => 'site', 'frost' => 'module', 'heat' => 'module', 'gust' => 'module', 'rain' => 'module', 'rain_start' => 'module', 'co2' => 'module' ] );
check( 'Vorgaben',       array_map( fn( $d ) => $d['default'],  $cat ), [ 'battery' => 20, 'rf' => 'low', 'wifi' => 'bad', 'station_silent' => 60, 'module_silent' => 60, 'sync_failed' => null, 'auth_required' => null, 'frost' => 0.0, 'heat' => 30.0, 'gust' => 60.0, 'rain' => 20.0, 'rain_start' => 0.2, 'co2' => 1000 ] );
check( 'Beharrungen an', array_map( fn( $d ) => $d['hold_on'],  $cat ), [ 'battery' => 0, 'rf' => 1800, 'wifi' => 1800, 'station_silent' => 0, 'module_silent' => 0, 'sync_failed' => 0, 'auth_required' => 0, 'frost' => 0, 'heat' => 0, 'gust' => 0, 'rain' => 0, 'rain_start' => 0, 'co2' => 0 ] );
check( 'Beharrungen aus',array_map( fn( $d ) => $d['hold_off'], $cat ), [ 'battery' => 0, 'rf' => 1800, 'wifi' => 1800, 'station_silent' => 1800, 'module_silent' => 1800, 'sync_failed' => 0, 'auth_required' => 0, 'frost' => 3600, 'heat' => 3600, 'gust' => 3600, 'rain' => 0, 'rain_start' => 0, 'co2' => 1800 ] );
check( 'ohne Entwarnung: rain und rain_start', array_keys( array_filter( $cat, fn( $d ) => ! $d['clears'] ) ), [ 'rain', 'rain_start' ] );
check( 'Stufen Funk und WLAN', [ $cat['rf']['levels'], $cat['wifi']['levels'] ], [ [ 'low' => [ 90, 80 ], 'medium' => [ 80, 70 ] ], [ 'bad' => [ 86, 71 ], 'average' => [ 71, 56 ] ] ] );
$def = NAWS_Notify_Rules::defaults();
check( 'defaults(): alles aus, Empfaenger leer', [ $def['enabled'], $def['recipients'], $def['rules']['battery'], $def['rules']['rf'], $def['rules']['sync_failed'], $def['rules']['gust'] ], [ 1, [], [ 'enabled' => 0, 'threshold' => 20 ], [ 'enabled' => 0, 'level' => 'low' ], [ 'enabled' => 0 ], [ 'enabled' => 0, 'threshold' => 60.0 ] ] );

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
check( 'unit_label', [ NAWS_Notify_Rules::unit_label( 'temp', $U ), NAWS_Notify_Rules::unit_label( 'temp', $F ), NAWS_Notify_Rules::unit_label( 'wind', $U ), NAWS_Notify_Rules::unit_label( 'wind', $F ), NAWS_Notify_Rules::unit_label( 'rain', $F ), NAWS_Notify_Rules::unit_label( 'percent', $U ), NAWS_Notify_Rules::unit_label( 'minutes', $U ), NAWS_Notify_Rules::unit_label( 'level', $U ), NAWS_Notify_Rules::unit_label( 'ppm', $U ) ], [ '°C', '°F', 'km/h', 'mph', 'in', '%', 'min', '', 'ppm' ] );
check( 'to_base/to_display: ppm unveraendert', [ NAWS_Notify_Rules::to_base( 'ppm', 1000.0, $F ), NAWS_Notify_Rules::to_display( 'ppm', 1000.0, $F ) ], [ 1000.0, 1000.0 ] );

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
check( 'heat: 30.0 warnt, 29.9 nicht',        [ $c( 'heat', $out( 30.0 ), [ 'threshold' => 30.0 ] ), $c( 'heat', $out( 29.9 ), [ 'threshold' => 30.0 ] ) ], [ true, false ] );
check( 'heat aktiv: 29.5 haelt, 28.9 entwarnt', [ $c( 'heat', $out( 29.5 ), [ 'threshold' => 30.0 ], true ), $c( 'heat', $out( 28.9 ), [ 'threshold' => 30.0 ], true ) ], [ true, false ] );
check( 'heat: Innenmodul -> ausgesetzt',      $c( 'heat', mod( 'NAModule4', [ 'readings' => [ 'Temperature' => [ 'value' => 35.0, 'at' => $NOW ] ] ] ), [ 'threshold' => 30.0 ] ), null );
$rain1 = fn( float $r ) => mod( 'NAModule3', [ 'readings' => [ 'sum_rain_1' => [ 'value' => $r, 'at' => $GLOBALS['NOW'] ] ] ] );
check( 'rain_start: 0.2 warnt, 0.1 nicht',   [ $c( 'rain_start', $rain1( 0.2 ), [ 'threshold' => 0.2 ] ), $c( 'rain_start', $rain1( 0.1 ), [ 'threshold' => 0.2 ] ) ], [ true, false ] );
check( 'rain_start aktiv: 0.0 beendet',       $c( 'rain_start', $rain1( 0.0 ), [ 'threshold' => 0.2 ], true ), false );
$co2 = fn( string $type, float $v ) => mod( $type, [ 'readings' => [ 'CO2' => [ 'value' => $v, 'at' => $GLOBALS['NOW'] ] ] ] );
check( 'co2: 1000 warnt, 999 nicht, Basis und Innenmodul', [ $c( 'co2', $co2( 'NAMain', 1000.0 ), [ 'threshold' => 1000 ] ), $c( 'co2', $co2( 'NAModule4', 999.0 ), [ 'threshold' => 1000 ] ) ], [ true, false ] );
check( 'co2 aktiv: 950 haelt, 899 entwarnt', [ $c( 'co2', $co2( 'NAModule4', 950.0 ), [ 'threshold' => 1000 ], true ), $c( 'co2', $co2( 'NAModule4', 899.0 ), [ 'threshold' => 1000 ], true ) ], [ true, false ] );
check( 'co2: Aussenmodul -> ausgesetzt',       $c( 'co2', $co2( 'NAModule1', 2000.0 ), [ 'threshold' => 1000 ] ), null );
check( 'value_of: Batterie, Frost, Zeitstempel', [ NAWS_Notify_Rules::value_of( 'battery', mod( 'NAModule4', [ 'battery_percent' => 23 ] ) ), NAWS_Notify_Rules::value_of( 'frost', $out( -2.5 ) ), NAWS_Notify_Rules::value_of( 'station_silent', mod( 'NAMain', [ 'last_status_store' => 5 ] ) ), NAWS_Notify_Rules::value_of( 'module_silent', mod( 'NAModule1', [ 'last_seen' => 7 ] ) ) ], [ 23, -2.5, 5, 7 ] );

echo "\nZustandsmaschine\n" . str_repeat( '-', 74 ) . "\n";
$on   = fn( array $over = [] ) => [ 'recipients' => [], 'rules' => array_replace_recursive( NAWS_Notify_Rules::defaults()['rules'], $over ) ];
$ctx  = [ 'interval' => 600, 'sync' => 'ok', 'consecutive_errors' => 0, 'auth_required' => false, 'error' => '' ];
$base = mod( 'NAMain',    [ 'module_id' => 'base', 'station_id' => 'base', 'module_name' => 'Basis', 'reachable' => 1, 'last_status_store' => $NOW - 300, 'wifi_status' => 60 ] );
$gast = mod( 'NAModule4', [ 'module_id' => 'gast', 'module_name' => 'Gast', 'battery_percent' => 23, 'rf_status' => 74, 'reachable' => 1, 'last_message' => $NOW - 300 ] );
$snap = fn( array ...$ms ) => [ 'modules' => array_combine( array_map( fn( $m ) => $m['module_id'], $ms ), $ms ) ];
$ev   = fn( array $r ) => array_map( fn( $e ) => [ $e['rule'], $e['kind'], $e['module_name'], $e['value'], $e['threshold'] ], $r['events'] );
$row  = function ( array $r, string $rule, string $id ): ?array { foreach ( $r['rows'] as $x ) { if ( $x['rule'] === $rule && $x['module_id'] === $id ) { return [ $x['status'], $x['reason'] ]; } } return null; };

$sb = $on( [ 'battery' => [ 'enabled' => 1, 'threshold' => 25 ] ] );
$r  = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sb, [], $NOW, $ctx );
check( 'Eintritt ohne Beharrung: ein raise fuer Gast', $ev( $r ), [ [ 'battery', 'raise', 'Gast', 23, 25 ] ] );
check( 'Zustand: aktiv seit jetzt, nur dieser Eintrag', [ array_keys( $r['state'] ), $r['state']['battery|gast']['active'], $r['state']['battery|gast']['since'], $r['state']['battery|gast']['pending_since'] ], [ [ 'battery|gast' ], true, $NOW, null ] );
check( 'Zeile: aktiv',                       $row( $r, 'battery', 'gast' ), [ 'active', '' ] );
check( 'Zeile: ausgeschaltete Regel',        $row( $r, 'rf', 'gast' ), [ 'suspended', 'disabled' ] );
check( 'Zeile: Site-Regel aus',              $row( $r, 'sync_failed', '' ), [ 'suspended', 'disabled' ] );
$r2 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sb, $r['state'], $NOW + 600, $ctx );
check( 'gleicher Zustand: kein Wechsel, since bleibt', [ $r2['events'], $r2['state']['battery|gast']['since'] ], [ [], $NOW ] );
$r3 = NAWS_Notify_Rules::evaluate( $snap( $base, array_merge( $gast, [ 'battery_percent' => 30 ] ) ), $sb, $r['state'], $NOW + 1200, $ctx );
check( 'Hysterese: 30 entwarnt nicht',       [ $r3['events'], $r3['state']['battery|gast']['active'] ], [ [], true ] );
$r4 = NAWS_Notify_Rules::evaluate( $snap( $base, array_merge( $gast, [ 'battery_percent' => 100 ] ) ), $sb, $r['state'], $NOW + 1800, $ctx );
check( 'Entwarnung: clear, Zustand leer',    [ $ev( $r4 ), $r4['state'] ], [ [ [ 'battery', 'clear', 'Gast', 100, 25 ] ], [] ] );
check( 'Entwarnung traegt Beginn und Ende', [ $r4['events'][0]['since'], $r4['events'][0]['now'] ], [ $NOW, $NOW + 1800 ] );

$sr   = $on( [ 'rf' => [ 'enabled' => 1, 'level' => 'low' ] ] );
$weak = array_merge( $gast, [ 'rf_status' => 92 ] );
$a = NAWS_Notify_Rules::evaluate( $snap( $base, $weak ), $sr, [], $NOW, $ctx );
check( 'Beharrung: erster Lauf wartet',      [ $a['events'], $a['state']['rf|gast']['pending_since'], $a['state']['rf|gast']['active'], $row( $a, 'rf', 'gast' ) ], [ [], $NOW, false, [ 'pending', '' ] ] );
$b = NAWS_Notify_Rules::evaluate( $snap( $base, $weak ), $sr, $a['state'], $NOW + 1800, $ctx );
check( 'Beharrung: nach 30 min raise',       $ev( $b ), [ [ 'rf', 'raise', 'Gast', 92, 'low' ] ] );
$c1 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sr, $a['state'], $NOW + 600, $ctx );
check( 'Flattern: gut dazwischen setzt zurueck', [ $c1['events'], $c1['state'] ], [ [], [] ] );
$c2 = NAWS_Notify_Rules::evaluate( $snap( $base, $weak ), $sr, $c1['state'], $NOW + 1200, $ctx );
check( 'Flattern: Beharrung beginnt neu',    $c2['state']['rf|gast']['pending_since'], $NOW + 1200 );

$sg   = $on( [ 'gust' => [ 'enabled' => 1, 'threshold' => 60.0 ] ] );
$wm   = fn( float $g, int $at ) => mod( 'NAModule2', [ 'module_id' => 'wind', 'module_name' => 'Wind', 'reachable' => 1, 'last_message' => $at, 'readings' => [ 'GustStrength' => [ 'value' => $g, 'at' => $at ] ] ] );
$g1 = NAWS_Notify_Rules::evaluate( $snap( $base, $wm( 70.0, $NOW ) ), $sg, [], $NOW, $ctx );
check( 'Boe: raise sofort',                  $ev( $g1 ), [ [ 'gust', 'raise', 'Wind', 70.0, 60.0 ] ] );
$g2 = NAWS_Notify_Rules::evaluate( $snap( $base, $wm( 40.0, $NOW + 600 ) ), $sg, $g1['state'], $NOW + 600, $ctx );
check( 'Boe: unter Schwelle wartet 60 min', [ $g2['events'], $g2['state']['gust|wind']['active'], $row( $g2, 'gust', 'wind' ) ], [ [], true, [ 'pending', '' ] ] );
// Die Basis meldet sich im Szenario mit — sonst gilt sie nach 60 min als still und friert die Wetterregel ein.
$g3 = NAWS_Notify_Rules::evaluate( $snap( array_merge( $base, [ 'last_status_store' => $NOW + 3900 ] ), $wm( 40.0, $NOW + 4200 ) ), $sg, $g2['state'], $NOW + 4200, $ctx );
check( 'Boe: nach 60 min clear',             [ $ev( $g3 ), $g3['state'] ], [ [ [ 'gust', 'clear', 'Wind', 40.0, 60.0 ] ], [] ] );
$g2row = null; foreach ( $g2['rows'] as $x ) { if ( $x['rule'] === 'gust' && $x['module_id'] === 'wind' ) { $g2row = $x; } }
check( 'Zeile: Entwarnungs-Beharrung zeigt ihren eigenen Beginn, nicht den der Warnung', [ $g2row['status'], $g2row['since'] ], [ 'pending', $NOW + 600 ] );

$sn = $on( [ 'rain' => [ 'enabled' => 1, 'threshold' => 20.0 ] ] );
$rm = fn( float $r, int $at ) => mod( 'NAModule3', [ 'module_id' => 'rain', 'module_name' => 'Regen', 'reachable' => 1, 'last_message' => $at, 'readings' => [ 'sum_rain_24' => [ 'value' => $r, 'at' => $at ] ] ] );
$n1 = NAWS_Notify_Rules::evaluate( $snap( $base, $rm( 25.0, $NOW ) ), $sn, [], $NOW, $ctx );
$n2 = NAWS_Notify_Rules::evaluate( $snap( $base, $rm( 10.0, $NOW + 600 ) ), $sn, $n1['state'], $NOW + 600, $ctx );
check( 'Regen: raise, dann stilles Ende',    [ $ev( $n1 ), $n2['events'], $n2['state'] ], [ [ [ 'rain', 'raise', 'Regen', 25.0, 20.0 ] ], [], [] ] );

$ss   = $on( [ 'battery' => [ 'enabled' => 1, 'threshold' => 25 ], 'station_silent' => [ 'enabled' => 1, 'minutes' => 60 ], 'wifi' => [ 'enabled' => 1, 'level' => 'bad' ] ] );
$dead = array_merge( $base, [ 'reachable' => 0 ] );
$d = NAWS_Notify_Rules::evaluate( $snap( $dead, $gast ), $ss, [], $NOW, $ctx );
check( 'Basis still: nur station_silent meldet', $ev( $d ), [ [ 'station_silent', 'raise', 'Basis', $NOW - 300, 60 ] ] );
check( 'Basis still: Batterie ausgesetzt, WLAN nicht', [ $row( $d, 'battery', 'gast' ), $row( $d, 'wifi', 'base' ) ], [ [ 'suspended', 'station_silent' ], [ 'ok', '' ] ] );
check( 'Basis still: Zustand nur die Basis', array_keys( $d['state'] ), [ 'station_silent|base' ] );
$d0 = NAWS_Notify_Rules::evaluate( $snap( $dead, $gast ), $on( [ 'battery' => [ 'enabled' => 1, 'threshold' => 25 ] ] ), [], $NOW, $ctx );
check( 'Basis still mit station_silent AUS: Modulregeln trotzdem ausgesetzt, kein Wechsel', [ $d0['events'], $row( $d0, 'battery', 'gast' ), $d0['state'] ], [ [], [ 'suspended', 'station_silent' ], [] ] );
$d2 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $ss, $d['state'], $NOW + 600, $ctx );
check( 'Basis zurueck: Batterie meldet, Entwarnung wartet 30 min', [ $ev( $d2 ), $row( $d2, 'station_silent', 'base' ), $d2['state']['station_silent|base']['active'] ], [ [ [ 'battery', 'raise', 'Gast', 23, 25 ] ], [ 'pending', '' ], true ] );
$d3 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $ss, $d2['state'], $NOW + 600 + 1800, $ctx );
check( 'Basis zurueck: nach 30 min Entwarnung', [ $ev( $d3 ), array_keys( $d3['state'] ) ], [ [ [ 'station_silent', 'clear', 'Basis', $NOW - 300, 60 ] ], [ 'battery|gast' ] ] );

$was = [ 'battery|gast' => [ 'active' => true, 'since' => $NOW - 100, 'pending_since' => null, 'value' => 23 ] ];
$e = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $on(), $was, $NOW, $ctx );
check( 'Regel aus: Eintrag weg, kein Wechsel', [ $e['events'], $e['state'] ], [ [], [] ] );
$f = NAWS_Notify_Rules::evaluate( $snap( $base ), $sb, $was, $NOW, $ctx );
check( 'Modul deaktiviert: Eintrag weg',     [ $f['events'], $f['state'] ], [ [], [] ] );

$sf   = $on( [ 'sync_failed' => [ 'enabled' => 1 ], 'battery' => [ 'enabled' => 1, 'threshold' => 25 ] ] );
$ctxF = [ 'sync' => 'failed', 'consecutive_errors' => 3, 'error' => 'Resolving timed out' ] + $ctx;
$h = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $sf, $was, $NOW, $ctxF );
check( 'Abruf scheitert: raise mit Fehlerzahl', [ $ev( $h ), $h['events'][0]['error'], $h['events'][0]['module_id'] ], [ [ [ 'sync_failed', 'raise', '', 3, null ] ], 'Resolving timed out', '' ] );
check( 'Abruf scheitert: Moduleintrag eingefroren', [ $h['state']['battery|gast'], $row( $h, 'battery', 'gast' ) ], [ $was['battery|gast'], [ 'suspended', 'no_sync' ] ] );
$h0 = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $sf, [], $NOW, [ 'consecutive_errors' => 2 ] + $ctxF );
check( 'Abruf scheitert: zwei Fehler reichen nicht', $h0['events'], [] );
$h2 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sf, $h['state'], $NOW + 600, $ctx );
check( 'naechster Erfolg: clear, Batterie bleibt aktiv ohne Wechsel', [ $ev( $h2 ), $h2['state']['battery|gast']['active'] ], [ [ [ 'sync_failed', 'clear', '', 0, null ] ], true ] );
$h3 = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $on( [ 'sync_failed' => [ 'enabled' => 1 ] ] ), $was, $NOW, $ctxF );
check( 'kein Abruf + Regel aus: Eintrag faellt trotzdem weg', array_keys( $h3['state'] ), [ 'sync_failed|site' ] );

$sa = $on( [ 'auth_required' => [ 'enabled' => 1 ] ] );
$i1 = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $sa, [], $NOW, [ 'auth_required' => true ] + $ctxF );
$i2 = NAWS_Notify_Rules::evaluate( $snap( $base ), $sa, $i1['state'], $NOW + 600, $ctx );
check( 'Zugangsdaten: raise, dann clear',    [ $ev( $i1 ), $ev( $i2 ) ], [ [ [ 'auth_required', 'raise', '', null, null ] ], [ [ 'auth_required', 'clear', '', null, null ] ] ] );

$sfr = $on( [ 'frost' => [ 'enabled' => 1, 'threshold' => 0.0 ] ] );
$aus = mod( 'NAModule1', [ 'module_id' => 'aus', 'module_name' => 'Aussen', 'reachable' => 1, 'last_message' => $NOW, 'readings' => [ 'Temperature' => [ 'value' => -2.0, 'at' => $NOW - 1500 ] ] ] );
$j = NAWS_Notify_Rules::evaluate( $snap( $base, $aus ), $sfr, [], $NOW, $ctx );
check( 'veralteter Messwert: ausgesetzt, kein Wechsel', [ $j['events'], $row( $j, 'frost', 'aus' ) ], [ [], [ 'suspended', 'stale' ] ] );
$k = NAWS_Notify_Rules::evaluate( $snap( $base, array_merge( $aus, [ 'readings' => [] ] ) ), $sfr, [], $NOW, $ctx );
check( 'fehlender Messwert: ausgesetzt',     $row( $k, 'frost', 'aus' ), [ 'suspended', 'missing' ] );
$l = NAWS_Notify_Rules::evaluate( $snap( $base, $aus ), $sfr, [], $NOW, [ 'interval' => 1800 ] + $ctx );
check( 'Intervall 30 min: 25 min alt zaehlt noch', $ev( $l ), [ [ 'frost', 'raise', 'Aussen', -2.0, 0.0 ] ] );
$sr1 = $on( [ 'rain_start' => [ 'enabled' => 1, 'threshold' => 0.2 ] ] );
$rs  = fn( float $r, int $at ) => mod( 'NAModule3', [ 'module_id' => 'rain', 'module_name' => 'Regen', 'reachable' => 1, 'last_message' => $at, 'readings' => [ 'sum_rain_1' => [ 'value' => $r, 'at' => $at ] ] ] );
$p1 = NAWS_Notify_Rules::evaluate( $snap( $base, $rs( 0.5, $NOW ) ), $sr1, [], $NOW, $ctx );
$p2 = NAWS_Notify_Rules::evaluate( $snap( array_merge( $base, [ 'last_status_store' => $NOW + 300 ] ), $rs( 0.0, $NOW + 600 ) ), $sr1, $p1['state'], $NOW + 600, $ctx );
$p3 = NAWS_Notify_Rules::evaluate( $snap( array_merge( $base, [ 'last_status_store' => $NOW + 900 ] ), $rs( 0.3, $NOW + 1200 ) ), $sr1, $p2['state'], $NOW + 1200, $ctx );
check( 'Regen beginnt: eine Mail, stilles Ende, neuer Schauer nach trockener Stunde meldet wieder', [ $ev( $p1 ), $p2['events'], $p2['state'], $ev( $p3 ) ], [ [ [ 'rain_start', 'raise', 'Regen', 0.5, 0.2 ] ], [], [], [ [ 'rain_start', 'raise', 'Regen', 0.3, 0.2 ] ] ] );

check( 'rule_cfg fuellt Vorgaben auf',        NAWS_Notify_Rules::rule_cfg( 'battery', [ 'rules' => [ 'battery' => [ 'enabled' => 1 ] ] ] ), [ 'enabled' => 1, 'threshold' => 20 ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
