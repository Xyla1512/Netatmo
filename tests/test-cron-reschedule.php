<?php
/**
 * Tests: nach "Jetzt synchronisieren" ist der naechste Abruf ein Intervall
 * entfernt, nicht sofort.
 *
 * Gemeldet am 2026-10-19: Das Dashboard zeigte "Naechste Ausfuehrung" und
 * "Letzte Synchronisierung" auf dieselbe Sekunde, das Cron-Log Abrufe im
 * Abstand weniger Sekunden. handle_manual_sync() rief nach dem Abruf
 * reschedule() auf, und schedule() plante den Cron mit time() — faellig
 * sofort. Der naechste Seitenaufruf stiess WP-Cron an, der gleich noch
 * einmal abrief: jeder Klick zwei Abrufe.
 *
 * Nach gespeicherten Einstellungen bleibt es beim sofortigen Lauf; dort
 * hat kein Abruf stattgefunden.
 *
 *   php tests/test-cron-reschedule.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['naws_test_events']  = [];
$GLOBALS['naws_test_options'] = [];

function add_filter( ...$a ) {}
function add_action( ...$a ) {}
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function delete_option( $k ) { unset( $GLOBALS['naws_test_options'][ $k ] ); return true; }
function wp_next_scheduled( $hook ) { return $GLOBALS['naws_test_events'][ $hook ]['ts'] ?? false; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['naws_test_events'][ $hook ] ); }
function wp_schedule_event( $ts, $rec, $hook ) { $GLOBALS['naws_test_events'][ $hook ] = [ 'ts' => $ts, 'rec' => $rec ]; return true; }
function naws_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }

require_once __DIR__ . '/../includes/class-naws-cron.php';

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

$GLOBALS['naws_test_options']['naws_settings'] = [ 'cron_interval' => 10 ];

// ── Nach einem Handabruf: ein Intervall spaeter ──────────────────────────
$before = time();
NAWS_Cron::instance()->reschedule( true );
$next = wp_next_scheduled( NAWS_Cron::HOOK_FETCH );
check( 'Handabruf: naechster Lauf frühestens in 10 min', $next >= $before + 600, true );
check( 'Handabruf: naechster Lauf spaetestens in 10 min (+ Laufzeit)', $next <= time() + 600, true );
check( 'Handabruf: Zeitplan 10 Minuten', $GLOBALS['naws_test_events'][ NAWS_Cron::HOOK_FETCH ]['rec'], 'naws_10_minutes' );

// ── Gespeicherte Einstellungen: do_action() reicht '' durch, sofort ──────
$GLOBALS['naws_test_events'] = [];
NAWS_Cron::instance()->reschedule( '' );
check( 'Einstellungen: sofort faellig', wp_next_scheduled( NAWS_Cron::HOOK_FETCH ) <= time(), true );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
