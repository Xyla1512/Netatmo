<?php
/**
 * Die Tagesboee in der Karte "Wind & Boeen" von [naws_live].
 *
 * Netatmo meldet mit jedem Abruf sein eigenes Tagesmaximum der Boee
 * (max_wind_str). Das Plugin speichert es seit jeher als Messwert, und
 * naws_get_latest liefert es mit -- angezeigt hat es bis 1.9.11 niemand.
 * Und es lief an den Einheiten vorbei: format_value() rechnete WindStrength
 * und GustStrength in m/s, mph oder Knoten um, max_wind_str nicht, und
 * get_unit() kannte den Namen gar nicht.
 *
 * Drei Dinge werden hier geprueft:
 *   1. NAWS_Helpers behandelt max_wind_str in Einheit und Umrechnung genau
 *      wie GustStrength.
 *   2. live-boot.js zeigt den Wert als dritten Block der Windkarte und
 *      fuehrt ihn im Live-Zyklus nach.
 *   3. Jeder Text, den live-boot.js ueber NAWS_I18N anspricht, wird von
 *      templates/live.php auch geliefert -- nicht nur der neue.
 *
 *   php tests/test-gust-max.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_date( $fmt, $ts ) { return gmdate( $fmt, $ts ); }
require_once __DIR__ . '/i18n-stubs.php';
require_once $PLUGIN . 'includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Helpers -- max_wind_str wie GustStrength\n" . str_repeat( '-', 74 ) . "\n";

// 37 km/h ist die Tagesboee der Produktseite am 06.09.2026.
foreach ( [ 'kmh' => [ 'km/h', 37.0 ], 'ms' => [ 'm/s', 10.3 ], 'mph' => [ 'mph', 23.0 ], 'kn' => [ 'kn', 20.0 ] ] as $unit => [ $label, $want ] ) {
    $GLOBALS['opts'] = [ 'naws_settings' => [ 'wind_unit' => $unit ] ];
    check( "Einheit $unit: max_wind_str traegt $label",            NAWS_Helpers::get_unit( 'max_wind_str' ), $label );
    check( "Einheit $unit: 37 km/h werden zu $want",               NAWS_Helpers::format_value( 'max_wind_str', 37 ), $want );
    check( "Einheit $unit: genau wie GustStrength",                NAWS_Helpers::format_value( 'max_wind_str', 37 ), NAWS_Helpers::format_value( 'GustStrength', 37 ) );
}
$GLOBALS['opts'] = [];
check( 'ohne Einstellung bleibt es bei km/h',                       NAWS_Helpers::get_unit( 'max_wind_str' ), 'km/h' );

echo "\nassets/js/live-boot.js -- dritter Block der Windkarte\n" . str_repeat( '-', 74 ) . "\n";

// Kommentarzeilen zaehlen nicht mit.
$js = (string) preg_replace( '/^\s*\/\/.*$/m', '', (string) file_get_contents( $PLUGIN . 'assets/js/live-boot.js' ) );

check( 'buildLive liest Netatmos Tagesmaximum',                     str_contains( $js, 'p.max_wind_str' ), true );
check( 'und beschriftet es mit card_gust_max',                      str_contains( $js, 'NAWS_I18N.card_gust_max' ), true );
check( 'der Block hat eine Kennung fuer den Live-Zyklus',           substr_count( $js, "WID+'-gm" ) >= 2, true );
check( 'er steht hinter Wind und Boeen',                            strpos( $js, "WID+'-gm" ) > strpos( $js, "WID+'-gv" ), true );
check( 'Wind und Boeen bleiben, wo sie sind',                       substr_count( $js, "WID+'-wv" ) >= 2 && substr_count( $js, "WID+'-gv" ) >= 2, true );
check( 'der Tacho nimmt die Tagesboee als dritten Wert',          (bool) preg_match( '/function gaugeSVG\(wv,gv,gm\)/', $js ), true );
check( 'und richtet seine Skala auch nach ihr',                    str_contains( $js, 'Math.max(+wv||0,+gv||0,+gm||0)' ), true );
check( 'ein dritter Zeiger: duenn, rot, gestrichelt',              (bool) preg_match( '/if\(gm>0\) s\+=\'<line [^\n]*stroke="#c0392b"[^\n]*stroke-dasharray=/', $js ), true );
check( 'beide Aufrufe reichen die Tagesboee an den Tacho durch',   preg_match_all( '/(?<!function )gaugeSVG\(wv,gv(\|\|0)?,gm\)/', $js ), 2 );

$css = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
check( 'die Wertezeile hat Platz fuer drei Bloecke',                (bool) preg_match( '/\.naws-wvrow\{[^}]*max-width:\s*280px/', $css ), true );

echo "\ntemplates/live.php -- liefert jeden Text, den das Skript anspricht\n" . str_repeat( '-', 74 ) . "\n";

$php = (string) file_get_contents( $PLUGIN . 'templates/live.php' );
preg_match_all( '/NAWS_I18N\.([a-z0-9_]+)/', $js, $m );
$benutzt = array_values( array_unique( $m[1] ) );
check( 'das Skript spricht Texte ueber NAWS_I18N an',               count( $benutzt ) > 0, true );
check( 'darunter card_gust_max',                                    in_array( 'card_gust_max', $benutzt, true ), true );
foreach ( $benutzt as $key ) {
    check( "I18N.$key wird von live.php geliefert",                 (bool) preg_match( "/'" . preg_quote( $key, '/' ) . "'\s*=>/", $php ), true );
}

echo "\nnode --check\n" . str_repeat( '-', 74 ) . "\n";

$node = trim( (string) @shell_exec( 'node --version 2>&1' ) );
if ( preg_match( '/^v\d+/', $node ) ) {
    $out = []; $rc = 1;
    exec( 'node --check ' . escapeshellarg( $PLUGIN . 'assets/js/live-boot.js' ) . ' 2>&1', $out, $rc );
    check( "Syntax laut node $node", $rc, 0 );
    if ( 0 !== $rc ) { echo '          ' . implode( "\n          ", $out ) . "\n"; }
} else {
    echo "  (kein node im PATH -- Syntaxpruefung uebersprungen)\n";
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
