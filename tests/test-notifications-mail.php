<?php
/**
 * Tests fuer den Mailtext, das Protokoll, die Testmail und den Lauf nach
 * einem gescheiterten Abruf: Betreff fuer einen und fuer mehrere Wechsel,
 * ein Absatz je Wechsel, Site-Regeln ohne Modulzeile, Link am Ende, kein
 * Zeilenumbruch im Betreff, hoechstens 50 Protokolleintraege, ein Lock
 * gegen Doppelmails.
 *
 *   php tests/test-notifications-mail.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'ENT_QUOTES_STUB', ENT_QUOTES );
$GLOBALS['naws_test_options'] = [ 'date_format' => 'd.m.Y', 'time_format' => 'H:i', 'admin_email' => 'admin@x.de' ];
$GLOBALS['naws_test_mails']   = [];
function get_option( $k, $d = false )        { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null )  { $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function add_option( $k, $v, $dep = '', $a = null ) { if ( isset( $GLOBALS['naws_test_options'][ $k ] ) ) { return false; } $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function delete_option( $k )                 { unset( $GLOBALS['naws_test_options'][ $k ] ); return true; }
function get_bloginfo( $k )                  { return 'Wetterstation &amp; Garten'; }
function wp_specialchars_decode( $s, $q = 0 ){ return html_entity_decode( $s, ENT_QUOTES ); }
function sanitize_text_field( $s )           { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_email( $e )                { return preg_replace( '/[^a-z0-9._%+\-@]/i', '', trim( (string) $e ) ); }
function is_email( $e )                      { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', (string) $e ); }
function absint( $v )                        { return abs( (int) $v ); }
function admin_url( $p = '' )                { return 'https://x.de/wp-admin/' . $p; }
function wp_date( $f, $t = null )            { return gmdate( $f, $t ?? time() ); }
function wp_mail( $to, $subject, $body )     { $GLOBALS['naws_test_mails'][] = [ $to, $subject, $body ]; return $GLOBALS['naws_test_mail_ok'] ?? true; }
function add_action( ...$a )                 {}
function get_locale()                        { return 'de_DE'; }
function determine_locale()                  { return $GLOBALS['naws_test_user_locale'] ?? 'de_DE'; }
function switch_to_locale( $l )              { $GLOBALS['naws_test_locale_calls'][] = 'switch:' . $l; return true; }
function restore_previous_locale()           { $GLOBALS['naws_test_locale_calls'][] = 'restore'; return true; }
require_once __DIR__ . '/i18n-stubs.php';
class NAWS_Helpers {
    public static function format_value( $p, $v ) { return round( $v, 1 ); }
    public static function get_unit( $p ) { return [ 'Temperature' => '°C', 'GustStrength' => 'km/h', 'sum_rain_24' => 'mm' ][ $p ] ?? ''; }
    public static function module_type_label( $t ) { return [ 'NAModule1' => 'Outdoor Module', 'NAModule4' => 'Indoor module' ][ $t ] ?? $t; }
}
class NAWS_Logger { public static $errors = []; public static function error( $c, $m, $x = [] ) { self::$errors[] = $m; } public static function warning( ...$a ) {} public static function info( ...$a ) {} }
class NAWS_Cron {
    public static function base_interval() { return 600; }
    public static function is_night_mode() { return false; }
    public static function get_polling_state() { return [ 'consecutive_errors' => 0 ]; }
}
$GLOBALS['naws_test_deleted']   = [];
function delete_transient( $k )              { $GLOBALS['naws_test_deleted'][] = $k; return true; }
class NAWS_Database {
    const CACHE_PREFIX = 'naws_cache_';
    public static $flushed = 0;
    public static function flush_module_caches() { self::$flushed++; }
    public static function get_modules( $active_only = false ) {
        return [
            [ 'module_id' => 'base', 'station_id' => 'base', 'module_name' => 'Basis', 'module_type' => 'NAMain', 'wifi_status' => '54', 'reachable' => '1', 'last_status_store' => '1789195712', 'battery_percent' => null, 'rf_status' => '0', 'last_seen' => '0', 'last_message' => null ],
            [ 'module_id' => 'rain', 'station_id' => 'base', 'module_name' => 'Regenmesser', 'module_type' => 'NAModule3', 'battery_percent' => '69', 'rf_status' => '74', 'wifi_status' => null, 'reachable' => '1', 'last_status_store' => null, 'last_seen' => '1789195699', 'last_message' => '1789195706' ],
        ];
    }
    public static function get_latest_readings( $module_id = null ) {
        return [ [ 'module_id' => 'rain', 'parameter' => 'sum_rain_24', 'value' => '3.2', 'recorded_at' => '1789195700' ], [ 'module_id' => 'rain', 'parameter' => 'Rain', 'value' => '0.1', 'recorded_at' => '1789195700' ] ];
    }
    public static function get_rain_rolling_24h( $module_id ) { return 12.5; }
}
require_once dirname( __DIR__ ) . '/includes/class-naws-notify-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-notifications.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$T0 = 1789117800; $T1 = $T0 + 5 * 3600 + 600;
$ev = fn( array $o ) => $o + [ 'rule' => 'battery', 'kind' => 'raise', 'module_id' => 'gast', 'module_name' => 'Gast', 'module_type' => 'NAModule4', 'value' => 23, 'threshold' => 25, 'since' => $T0, 'now' => $T0, 'error' => '' ];
$LINK = 'Adjust rules: https://x.de/wp-admin/admin.php?page=naws-notifications';

echo "\ncompose(): ein Wechsel\n" . str_repeat( '-', 74 ) . "\n";
$m = NAWS_Notifications::compose( [ $ev( [] ) ] );
check( 'Betreff: Site-Name entschluesselt, Regel, Modul, Wert', $m['subject'], '[Wetterstation & Garten] Netatmo: Battery low – Gast (23 %)' );
check( 'Body: Absatz + Link', explode( "\n", trim( $m['body'] ) ), [ 'Warning: Battery low', 'Module: Gast (Indoor module)', 'Battery: 23 % (threshold 25 %)', 'Since: ' . gmdate( 'd.m.Y, H:i', $T0 ), '', $LINK ] );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'frost', 'kind' => 'clear', 'module_id' => 'aus', 'module_name' => 'Aussen', 'module_type' => 'NAModule1', 'value' => 2.4, 'threshold' => 0.0, 'now' => $T1 ] ) ] );
check( 'Entwarnung: Betreff',              $m['subject'], '[Wetterstation & Garten] Netatmo: All clear: Frost – Aussen (2.4 °C)' );
check( 'Entwarnung: von/bis',              explode( "\n", trim( $m['body'] ) ), [ 'All clear: Frost', 'Module: Aussen (Outdoor Module)', 'Temperature: 2.4 °C (threshold 0 °C)', 'From ' . gmdate( 'd.m.Y, H:i', $T0 ) . ' to ' . gmdate( 'd.m.Y, H:i', $T1 ), '', $LINK ] );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'sync_failed', 'module_id' => '', 'module_name' => '', 'module_type' => '', 'value' => 3, 'threshold' => null, 'error' => 'Resolving timed out' ] ) ] );
check( 'Site-Regel: keine Modulzeile, Fehlertext', explode( "\n", trim( $m['body'] ) ), [ 'Warning: Fetch failing', 'Errors in a row: 3', 'Error: Resolving timed out', 'Since: ' . gmdate( 'd.m.Y, H:i', $T0 ), '', $LINK ] );
check( 'Site-Regel: Betreff ohne Modul',   $m['subject'], '[Wetterstation & Garten] Netatmo: Fetch failing (3)' );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'rf', 'value' => 92, 'threshold' => 'low' ] ) ] );
check( 'Stufe: Wert roh, Schwelle benannt', explode( "\n", trim( $m['body'] ) )[2], 'Radio: 92 (threshold weak (≥ 90))' );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'station_silent', 'module_id' => 'base', 'module_name' => 'Basis', 'module_type' => 'NAMain', 'value' => $T0 - 4000, 'threshold' => 60 ] ) ] );
check( 'Stille Basis: letzte Meldung als Zeit, Schwelle in Minuten', explode( "\n", trim( $m['body'] ) )[2], 'Last report: ' . gmdate( 'd.m.Y, H:i', $T0 - 4000 ) . ' (threshold 60 min)' );
$m = NAWS_Notifications::compose( [ $ev( [ 'module_name' => "Gast\nBcc: x@y.de" ] ) ] );
check( 'Betreff ohne Zeilenumbruch',       str_contains( $m['subject'], "\n" ), false );

echo "\ncompose(): mehrere Wechsel\n" . str_repeat( '-', 74 ) . "\n";
$m = NAWS_Notifications::compose( [ $ev( [] ), $ev( [ 'module_name' => 'Aussen', 'value' => 18 ] ), $ev( [ 'rule' => 'frost', 'kind' => 'clear', 'module_type' => 'NAModule1', 'value' => 2.0, 'threshold' => 0.0 ] ) ] );
check( 'Betreff zaehlt',                   $m['subject'], '[Wetterstation & Garten] Netatmo: 2 warnings, 1 all-clear' );
check( 'drei Absaetze, Leerzeile dazwischen', substr_count( $m['body'], "\n\n" ), 3 );

echo "\nformat_measure() / format_threshold()\n" . str_repeat( '-', 74 ) . "\n";
check( 'Prozent, Stufe, Temperatur, leer', [ NAWS_Notifications::format_measure( 'battery', 23 ), NAWS_Notifications::format_measure( 'rf', 92 ), NAWS_Notifications::format_measure( 'frost', -2.25 ), NAWS_Notifications::format_measure( 'frost', null ) ], [ '23 %', '92', '-2.3 °C', '' ] );
check( 'Schwellen',                        [ NAWS_Notifications::format_threshold( 'battery', 25 ), NAWS_Notifications::format_threshold( 'wifi', 'average' ), NAWS_Notifications::format_threshold( 'module_silent', 60 ), NAWS_Notifications::format_threshold( 'gust', 60.0 ), NAWS_Notifications::format_threshold( 'sync_failed', null ) ], [ '25 %', 'average or worse (≥ 71)', '60 min', '60 km/h', '' ] );

echo "\nProtokoll und Testmail\n" . str_repeat( '-', 74 ) . "\n";
for ( $i = 1; $i <= 52; $i++ ) { NAWS_Notifications::log( [ 'time' => $i, 'subject' => "s$i", 'to' => [], 'sent' => true, 'events' => [] ] ); }
$log = NAWS_Notifications::get_log();
check( 'hoechstens 50, neueste zuerst',    [ count( $log ), $log[0]['subject'], $log[49]['subject'] ], [ 50, 's52', 's3' ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOG_KEY ] = [];
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = [ 'recipients' => [ 'f@x.de', 'g@x.de' ], 'rules' => [ 'battery' => [ 'enabled' => 1, 'threshold' => 30 ], 'frost' => [ 'enabled' => 1 ] ] ];
$GLOBALS['naws_test_mails'] = [];
check( 'send_test(): gesendet',            NAWS_Notifications::send_test(), true );
[ $to, $subject, $body ] = $GLOBALS['naws_test_mails'][0];
check( 'Testmail: an die Liste, Betreff',  [ $to, $subject ], [ [ 'f@x.de', 'g@x.de' ], '[Wetterstation & Garten] Netatmo: Test mail' ] );
check( 'Testmail: eingeschaltete Regeln mit Schwelle', [ str_contains( $body, '- Battery low (30 %)' ), str_contains( $body, '- Frost (0 °C)' ), str_contains( $body, 'Gust' ), str_contains( $body, $LINK ) ], [ true, true, false, true ] );
check( 'Testmail im Protokoll ohne Wechsel', [ count( NAWS_Notifications::get_log() ), NAWS_Notifications::get_log()[0]['events'] ], [ 1, [] ] );
$GLOBALS['naws_test_mail_ok'] = false;
check( 'send(): false wird protokolliert', [ NAWS_Notifications::send( [ 'f@x.de' ], 'x', 'y' ), count( NAWS_Logger::$errors ) > 0 ], [ false, true ] );
$GLOBALS['naws_test_mail_ok'] = true;

echo "\nTestmail in der Site-Sprache\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['naws_test_user_locale'] = 'en_US'; $GLOBALS['naws_test_locale_calls'] = [];
NAWS_Notifications::send_test();
check( 'send_test(): auf die Site-Sprache umgeschaltet und zurueck', $GLOBALS['naws_test_locale_calls'], [ 'switch:de_DE', 'restore' ] );
$GLOBALS['naws_test_locale_calls'] = []; $GLOBALS['naws_test_mail_ok'] = false;
NAWS_Notifications::send_test();
check( 'send_test(): auch bei Fehlschlag zurueck (finally)', $GLOBALS['naws_test_locale_calls'], [ 'switch:de_DE', 'restore' ] );
$GLOBALS['naws_test_locale_calls'] = []; $GLOBALS['naws_test_mail_ok'] = true; $GLOBALS['naws_test_user_locale'] = 'de_DE';
NAWS_Notifications::send_test();
check( 'send_test(): gleiche Sprache -> kein Umschalten', $GLOBALS['naws_test_locale_calls'], [] );

echo "\nLauf nach gescheitertem Abruf, mit Lock\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = [ 'recipients' => [ 'f@x.de' ], 'rules' => [ 'sync_failed' => [ 'enabled' => 1 ] ] ];
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOG_KEY ] = [];
$GLOBALS['naws_test_mails'] = [];
NAWS_Notifications::on_failed( 'Resolving timed out', 3 );
check( 'dritter Fehler: eine Mail, Zustand aktiv', [ count( $GLOBALS['naws_test_mails'] ), array_keys( $GLOBALS['naws_test_options'][ NAWS_Notifications::STATE_KEY ] ), $GLOBALS['naws_test_mails'][0][1] ], [ 1, [ 'sync_failed|site' ], '[Wetterstation & Garten] Netatmo: Fetch failing (3)' ] );
NAWS_Notifications::on_failed( 'Resolving timed out', 4 );
check( 'vierter Fehler: keine zweite Mail', count( $GLOBALS['naws_test_mails'] ), 1 );
check( 'Lock wieder frei',                 isset( $GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] ), false );
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] = time();
NAWS_Notifications::on_failed( 'x', 9 );
check( 'frischer Lock: Lauf uebersprungen, Lock bleibt', [ count( $GLOBALS['naws_test_mails'] ), isset( $GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] ) ], [ 1, true ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] = time() - 500;
$GLOBALS['naws_test_options'][ NAWS_Notifications::STATE_KEY ] = [];
NAWS_Notifications::on_failed( 'x', 3 );
check( 'verwaister Lock (>120 s) wird uebernommen', [ count( $GLOBALS['naws_test_mails'] ), isset( $GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] ) ], [ 2, false ] );

echo "\nSchnappschuss\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['naws_test_deleted'] = []; NAWS_Database::$flushed = 0;
$snap = NAWS_Notifications::snapshot();
check( 'Schnappschuss: Module nach ID, Statusfelder als int/null', [ array_keys( $snap['modules'] ), $snap['modules']['base']['wifi_status'], $snap['modules']['base']['battery_percent'], $snap['modules']['rain']['reachable'] ], [ [ 'base', 'rain' ], 54, null, 1 ] );
check( 'Schnappschuss: Regen = rollierende 24-h-Summe, nicht das Netatmo-Feld', $snap['modules']['rain']['readings']['sum_rain_24']['value'], 12.5 );
check( 'Schnappschuss: andere Messwerte bleiben', $snap['modules']['rain']['readings']['Rain']['value'], 0.1 );
check( 'Schnappschuss: eigene Caches vorher geloescht', [ NAWS_Database::$flushed, in_array( 'naws_cache_latest_all', $GLOBALS['naws_test_deleted'], true ), in_array( 'naws_cache_rain24h_' . md5( 'rain' ), $GLOBALS['naws_test_deleted'], true ) ], [ 1, true, true ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
