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

echo "\nAdmin-Klasse\n" . str_repeat( '-', 74 ) . "\n";
$admin = file_get_contents( $PLUGIN . 'includes/class-naws-admin.php' );
check( 'Untermenue direkt nach Modules',            preg_match( "/'naws-modules',\s*\[ \\\$this, 'page_modules' \] \);\s*\n\s*add_submenu_page\( 'naws-dashboard', __\( 'Notifications', 'xtx-integration-for-netatmo' \),.*'naws-notifications',\s*\[ \\\$this, 'page_notifications' \] \);/", $admin ), 1 );
check( 'beide Handler registriert',                  [ str_contains( $admin, "add_action( 'admin_post_naws_save_notifications', [ \$this, 'handle_save_notifications' ] );" ), str_contains( $admin, "add_action( 'admin_post_naws_test_notification', [ \$this, 'handle_test_notification' ] );" ) ], [ true, true ] );
foreach ( [ 'handle_save_notifications' => 'naws_save_notifications', 'handle_test_notification' => 'naws_test_notification' ] as $fn => $nonce ) {
    check( "$fn: Nonce, dann Capability",            preg_match( "/function $fn\(\) \{\s*\n\s*check_admin_referer\( '$nonce' \);\s*\n\s*if \( ! current_user_can\( 'manage_options' \) \) wp_die\( 'Unauthorized' \);/", $admin ), 1 );
}
check( 'Empfaenger sichtbar per sanitize_textarea_field', str_contains( $admin, "sanitize_textarea_field( wp_unslash( \$_POST['naws_notifications']['recipients'] ) )" ), true );
check( 'Regeln sichtbar per map_deep',               str_contains( $admin, "map_deep( wp_unslash( \$_POST['naws_notifications']['rules'] ), 'sanitize_text_field' )" ), true );
check( 'Rueckleitung per wp_safe_redirect + exit in beiden Handlern', preg_match_all( '/wp_safe_redirect\( \$url \);\s*\n\s*exit;/', $admin ) >= 2, true );

echo "\nView\n" . str_repeat( '-', 74 ) . "\n";
$view  = (string) file_get_contents( $PLUGIN . 'admin/views/notifications.php' );
$lines = explode( "\n", $view );
check( 'View vorhanden und nicht leer',              $view !== '', true );
check( 'kein ob_start, kein script, kein style',      [ str_contains( $view, 'ob_start' ), stripos( $view, '<script' ) !== false, stripos( $view, '<style' ) !== false ], [ false, false, false ] );
check( 'zwei Formulare mit Nonce',                   [ substr_count( $view, 'method="post"' ), str_contains( $view, "wp_nonce_field( 'naws_save_notifications' )" ), str_contains( $view, "wp_nonce_field( 'naws_test_notification' )" ) ], [ 2, true, true ] );
check( 'jeder $_GET-Zugriff steht bei einem Nonce-Check', preg_match_all( "/isset\( \\\$_GET\['(updated|dropped|test)'\] \) && wp_verify_nonce\( sanitize_text_field\( wp_unslash\( \\\$_GET\['_wpnonce'\] \?\? '' \) \), 'naws_notifications_notice' \)/", $view ), 3 );
$naked = [];
foreach ( $lines as $n => $l ) {
    if ( preg_match( '/\becho\b/', $l ) && ! preg_match( '/esc_html|esc_attr|esc_url|esc_textarea|wp_kses_post/', $l ) ) { $naked[] = $n + 1; }
}
check( 'jedes echo traegt eine Escaping-Funktion',   $naked, [] );
check( 'Hidden 0 direkt vor der Checkbox (eine Schleife fuer alle zehn Regeln)', preg_match( '/type="hidden" name="<\?php echo esc_attr\( \$name \); \?>\[enabled\]" value="0">\s*<label>\s*<input type="checkbox" name="<\?php echo esc_attr\( \$name \); \?>\[enabled\]" value="1"/', $view ), 1 );
check( 'Empfaengerfeld per esc_textarea',            str_contains( $view, 'esc_textarea( implode( "\n", $settings[\'recipients\'] ) )' ), true );
check( 'View beginnt mit ABSPATH-Wache',             str_contains( substr( $view, 0, 300 ), "if ( ! defined( 'ABSPATH' ) ) exit;" ), true );
check( 'Generalschalter: Hidden 0 direkt vor der Checkbox',  preg_match( '/type="hidden" name="naws_notifications\[enabled\]" value="0">\s*<label>\s*<input type="checkbox" name="naws_notifications\[enabled\]" value="1"/', $view ), 1 );
check( 'Handler liest den Generalschalter sichtbar sanitiert', str_contains( $admin, "'1' === sanitize_text_field( wp_unslash( \$_POST['naws_notifications']['enabled'] ) )" ), true );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
