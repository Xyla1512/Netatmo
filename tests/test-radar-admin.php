<?php
/**
 * Regenradar im Backend: Schalter, Leeren-Knopf, Tagesaufraeumen,
 * Deinstallation, Offenlegung. Prueft den Quelltext, der Ablauf selbst
 * steht in test-radar-store.php und test-radar-render.php.
 *
 *   php tests/test-radar-admin.php
 *
 * @package NAWS
 */
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$R     = dirname( __DIR__ ) . '/';
$admin = (string) file_get_contents( $R . 'includes/class-naws-admin.php' );
$ajax  = (string) file_get_contents( $R . 'includes/class-naws-ajax.php' );
$view  = (string) file_get_contents( $R . 'admin/views/settings.php' );
$js    = (string) file_get_contents( $R . 'assets/js/admin.js' );
$cron  = (string) file_get_contents( $R . 'includes/class-naws-cron.php' );
$un    = (string) @file_get_contents( $R . 'uninstall.php' );

echo "\nSchalter und Knopf\n" . str_repeat( '-', 74 ) . "\n";
check( 'Schalter wird sanitiert (versteckte 0)', str_contains( $admin, "\$clean['radar_enabled'] = empty( \$input['radar_enabled'] ) ? 0 : 1;" ), true );
check( 'Schalter im Formular mit versteckter 0',  str_contains( $view, '<input type="hidden" name="naws_settings[radar_enabled]" value="0">' ), true );
check( 'Schalter-Haken',                          str_contains( $view, 'name="naws_settings[radar_enabled]" value="1"' ), true );
check( 'Knopf im Block',                          str_contains( $view, 'id="naws-radar-clear"' ), true );
check( 'AJAX-Aktion registriert',                 str_contains( $ajax, "add_action( 'wp_ajax_naws_radar_clear'" ), true );
check( 'Handler prueft Nonce',                    (bool) preg_match( '/function radar_clear\(\).*?check_ajax_referer\( \'naws_admin_nonce\', \'nonce\' \)/s', $ajax ), true );
check( 'Handler prueft Recht',                    (bool) preg_match( '/function radar_clear\(\).*?current_user_can\( \'manage_options\' \)/s', $ajax ), true );
check( 'Handler leert',                           (bool) preg_match( '/function radar_clear\(\).*?NAWS_Radar_Store::clear_all\(\)/s', $ajax ), true );
check( 'JS ruft die Aktion',                      str_contains( $js, "action: 'naws_radar_clear'" ), true );
check( 'Hinweis bei schreibgeschuetztem Uploads-Ordner', (bool) preg_match( "/! wp_is_writable\( \\\$naws_rd_up\['basedir'\] \).*?esc_html_e\( 'The uploads folder is not writable, so the rain radar cannot store images\./s", $view ), true );
check( 'JS-Strings vorhanden',                   str_contains( $admin, "'radar_clear_confirm'" ) && str_contains( $admin, "'radar_cleared'" ), true );

echo "\nCron und Deinstallation\n" . str_repeat( '-', 74 ) . "\n";
check( 'Tagesaufraeumen nach der Aufbewahrung',   (bool) preg_match( '/\$this->run_retention\(\);.*?NAWS_Radar::daily_cleanup\(\)/s', $cron ), true );
check( 'deactivate raeumt geplante Laeufe',      str_contains( $cron, "wp_unschedule_hook( 'naws_radar_refresh' )" ), true );
check( 'deactivate: kein clear_scheduled_hook',   str_contains( $cron, "wp_clear_scheduled_hook( 'naws_radar_refresh' )" ), false );
check( 'uninstall raeumt geplante Laeufe',        str_contains( $un, "wp_unschedule_hook( 'naws_radar_refresh' )" ), true );
check( 'uninstall: kein clear_scheduled_hook',    str_contains( $un, "wp_clear_scheduled_hook( 'naws_radar_refresh' )" ), false );
check( 'uninstall: Rueckfall WP_Filesystem_Direct', str_contains( $un, 'WP_Filesystem_Direct' ), true );
check( 'uninstall.php existiert',                 $un !== '', true );
check( 'uninstall prueft WP_UNINSTALL_PLUGIN',    str_contains( $un, "defined( 'WP_UNINSTALL_PLUGIN' )" ), true );
check( 'uninstall loescht nur den Radar-Ordner',  str_contains( $un, "'/naws-radar'" ), true );
check( 'uninstall loescht die Radar-Option',      str_contains( $un, "delete_option( 'naws_radar_sets' )" ), true );
check( 'uninstall laesst Messdaten in Ruhe',      preg_match( '/DROP TABLE|naws_readings|naws_settings/', $un ), 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
