<?php
/**
 * Cache-busting Version fuer die eigenen Frontend-Assets des Plugins.
 *
 * enqueue_frontend_assets() in includes/class-naws-shortcodes.php registrierte
 * die eigenen Stylesheets und Skripte bisher mit der nackten NAWS_VERSION.
 * Ein Deploy, das frontend.css aendert, ohne die Pluginversion zu erhoehen,
 * liess die URL ?ver=1.9.11 unveraendert -- der Browser lieferte die alte
 * Datei aus dem Cache. NAWS_Admin::asset_version() loest das fuer die
 * Admin-Assets bereits seit 1.9.7 ueber filemtime(); dieser Test prueft, dass
 * NAWS_Helpers denselben Mechanismus als gemeinsamen Helfer traegt und dass
 * er an allen sechs eigenen Registrierungen sowie am Admin-Vorschau-Enqueue
 * ankommt, waehrend die beiden Vendor-Bibliotheken ihre feste Version
 * behalten.
 *
 *   php tests/test-asset-version.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

define( 'NAWS_PLUGIN_DIR', $PLUGIN );
define( 'NAWS_VERSION', '1.9.11' );

require_once __DIR__ . '/i18n-stubs.php';
require_once $PLUGIN . 'includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Helpers::asset_version()\n" . str_repeat( '-', 74 ) . "\n";

$real_rel  = 'assets/css/frontend.css';
$real_path = NAWS_PLUGIN_DIR . $real_rel;
check( 'echte Datei traegt NAWS_VERSION + filemtime()',
    NAWS_Helpers::asset_version( $real_rel ),
    NAWS_VERSION . '.' . filemtime( $real_path ) );

check( 'fehlende Datei faellt auf die nackte NAWS_VERSION zurueck',
    NAWS_Helpers::asset_version( 'assets/css/does-not-exist.css' ),
    NAWS_VERSION );

echo "\nincludes/class-naws-shortcodes.php -- sechs eigene Registrierungen\n" . str_repeat( '-', 74 ) . "\n";

$sc = (string) file_get_contents( $PLUGIN . 'includes/class-naws-shortcodes.php' );

check( 'naws-frontend style nutzt den Helfer',
    (bool) preg_match( "/wp_register_style\(\s*'naws-frontend',[^;]*NAWS_Helpers::asset_version\(\s*'assets\/css\/frontend\.css'\s*\)/s", $sc ),
    true );

foreach ( [
    'naws-frontend'      => 'assets/js/frontend.js',
    'naws-live-boot'     => 'assets/js/live-boot.js',
    'naws-history-boot'  => 'assets/js/history-boot.js',
    'naws-heatmap-boot'  => 'assets/js/heatmap-boot.js',
    'naws-windrose-boot' => 'assets/js/windrose-boot.js',
] as $handle => $rel ) {
    check( "$handle script nutzt den Helfer fuer $rel",
        (bool) preg_match( "/wp_register_script\(\s*'" . preg_quote( $handle, '/' ) . "',[^;]*NAWS_Helpers::asset_version\(\s*'" . preg_quote( $rel, '/' ) . "'\s*\)/s", $sc ),
        true );
}

echo "\nVendor-Bibliotheken behalten ihre feste Version\n" . str_repeat( '-', 74 ) . "\n";

check( 'naws-chartjs bleibt bei 4.5.1',
    (bool) preg_match( "/wp_register_script\(\s*'naws-chartjs',[^;]*'4\.5\.1'/s", $sc ),
    true );
check( 'naws-chartjs-adapter bleibt bei 3.0.0',
    (bool) preg_match( "/wp_register_script\(\s*'naws-chartjs-adapter',[^;]*'3\.0\.0'/s", $sc ),
    true );

echo "\nincludes/class-naws-admin.php -- delegiert und nutzt den Helfer\n" . str_repeat( '-', 74 ) . "\n";

$admin = (string) file_get_contents( $PLUGIN . 'includes/class-naws-admin.php' );

check( 'frontend.css wird nicht mehr mit einer nackten NAWS_VERSION registriert',
    (bool) preg_match( "/wp_enqueue_style\(\s*'naws-weather-icon',[^;]*NAWS_VERSION\s*\)/s", $admin ),
    false );
check( 'frontend.css nutzt stattdessen self::asset_version()',
    (bool) preg_match( "/wp_enqueue_style\(\s*'naws-weather-icon',[^;]*self::asset_version\(\s*'assets\/css\/frontend\.css'\s*\)/s", $admin ),
    true );
check( 'NAWS_Admin::asset_version() delegiert an NAWS_Helpers',
    (bool) preg_match( '/function asset_version\( string \$rel \): string \{\s*return NAWS_Helpers::asset_version\( \$rel \);\s*\}/', $admin ),
    true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
