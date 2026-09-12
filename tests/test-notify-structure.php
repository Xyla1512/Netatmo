<?php
/**
 * Strukturtests fuer die Benachrichtigungen: was sich mit Stubs nicht
 * pruefen laesst, wird am Quelltext geprueft — dass der Cron die neue
 * Aktion an jeder Fehlerstelle feuert, dass der Bootstrap die Klasse
 * einhaengt, und (ab Task 7) dass Handler und View die Review-Regeln
 * einhalten: Nonce + Capability, sichtbare Sanitierung, Nonce im
 * $_GET-Vergleich, Escaping an jedem echo, kein ob_start, kein Inline-Skript.
 *
 *   php tests/test-notify-structure.php
 *
 * @package NAWS
 */
$PLUGIN = dirname( __DIR__ ) . '/';
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$cron = file_get_contents( $PLUGIN . 'includes/class-naws-cron.php' );
$main = file_get_contents( $PLUGIN . 'xtx-integration-for-netatmo.php' );

echo "\nCron und Bootstrap\n" . str_repeat( '-', 74 ) . "\n";
check( 'naws_sync_failed an drei Stellen',          preg_match_all( "/do_action\(\s*'naws_sync_failed'/", $cron ), 3 );
check( 'jede Stelle direkt nach record_error()',    preg_match_all( "/self::record_error\(\);\s*\n\s*do_action\(\s*'naws_sync_failed'/", $cron ), 3 );
check( 'base_interval() ist oeffentlich',           preg_match( '/public static function base_interval\(\)/', $cron ), 1 );
check( 'Bootstrap haengt die Benachrichtigungen ein', preg_match( '/NAWS_Rest_API::init\(\);\s*\n\s*NAWS_Notifications::init\(\);/', $main ), 1 );
check( 'beide Klassen werden geladen',              [ str_contains( $main, "includes/class-naws-notify-rules.php'" ), str_contains( $main, "includes/class-naws-notifications.php'" ) ], [ true, true ] );

// ---- Teil 2 (Task 7: Admin und View) wird hier eingefuegt ----

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
