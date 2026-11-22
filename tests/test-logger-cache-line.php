<?php
/**
 * Tests: "All NAWS caches flushed." no longer fills the error log
 * (Frank, 22.11.2026: "aus Fehlerprotokoll entfernen"). Up to 2.2.1 every
 * sync wrote that line, 200 places were full after a day and a half, and
 * the errors the log is kept for were gone.
 *
 *   php tests/test-logger-cache-line.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['opts'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
require_once dirname( __DIR__ ) . '/includes/class-naws-logger.php';

$pass = 0; $fail = 0;
function check( $label, $got, $want ) {
	global $pass, $fail;
	if ( $got === $want ) { $pass++; echo "  ok   $label\n"; }
	else { $fail++; echo "  FAIL $label\n       got:  " . var_export( $got, true ) . "\n       want: " . var_export( $want, true ) . "\n"; }
}

echo "\nflush_caches() schreibt nichts mehr\n" . str_repeat( '-', 74 ) . "\n";
$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
preg_match( '/function flush_caches\(\)\s*\{(.*?)\n    \}/s', $db, $m );
check( 'flush_caches gefunden',               isset( $m[1] ), true );
check( 'kein NAWS_Logger in flush_caches',    strpos( $m[1] ?? 'NAWS_Logger', 'NAWS_Logger' ), false );
check( 'Satz kommt im Plugin nicht mehr als Aufruf vor',
	(bool) preg_match( "/NAWS_Logger::\\w+\\(\\s*'cache',\\s*'All NAWS caches flushed/", $db ), false );

echo "\nAlte Zeilen fliegen beim naechsten Eintrag raus\n" . str_repeat( '-', 74 ) . "\n";
$old = [];
for ( $i = 0; $i < 199; $i++ ) {
	$old[] = [ 'time' => 1000 - $i, 'level' => 'info', 'context' => 'cache', 'message' => 'All NAWS caches flushed.' ];
}
$old[] = [ 'time' => 1, 'level' => 'error', 'context' => 'radar', 'message' => 'dwd request failed (no response)' ];
$GLOBALS['opts']['naws_error_log'] = $old;
NAWS_Logger::warning( 'radar', 'dwd request timed out 3 times in a row' );
$log = $GLOBALS['opts']['naws_error_log'];
check( 'nur noch zwei Eintraege',             count( $log ), 2 );
check( 'neuer Eintrag vorn',                  $log[0]['message'], 'dwd request timed out 3 times in a row' );
check( 'alter Fehler bleibt erhalten',        $log[1]['message'], 'dwd request failed (no response)' );

echo "\nAndere Eintraege bleiben\n" . str_repeat( '-', 74 ) . "\n";
check( 'andere cache-Info bleibt',            NAWS_Logger::is_kept( [ 'context' => 'cache', 'message' => 'Module caches flushed.' ] ), true );
check( 'gleicher Text, anderer Kontext bleibt', NAWS_Logger::is_kept( [ 'context' => 'cron', 'message' => 'All NAWS caches flushed.' ] ), true );
check( 'kaputter Eintrag bleibt (kein Absturz)', NAWS_Logger::is_kept( 'x' ), true );
check( 'die Zeile selbst faellt weg',          NAWS_Logger::is_kept( [ 'context' => 'cache', 'message' => 'All NAWS caches flushed.' ] ), false );
NAWS_Logger::info( 'cron', 'Recovered from error backoff, restored normal polling interval.' );
check( 'Info-Eintraege anderer Art kommen weiter an', count( $GLOBALS['opts']['naws_error_log'] ), 3 );

echo "\n$pass ok, $fail failed\n";
exit( $fail ? 1 : 0 );
