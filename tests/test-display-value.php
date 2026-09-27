<?php
/**
 * Tests fuer NAWS_Helpers::display_value() und display_number(): ein
 * Messwert als Text mit den Trennzeichen der Sprache — "1.019,1" auf
 * Deutsch, "1,019.1" auf Englisch. format_value() rechnet weiter nur um
 * und gibt eine Zahl zurueck; die Nachkommastellen, die es stehen laesst,
 * bleiben stehen, nur das Trennzeichen folgt der Sprache.
 *
 *   php tests/test-display-value.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }

/** Wie WordPress: die Trennzeichen kommen aus der Sprache ($wp_locale). */
$GLOBALS['naws_test_sep'] = [ ',', '.' ];
function number_format_i18n( $n, $d = 0 ) {
    return number_format( (float) $n, absint( $d ), $GLOBALS['naws_test_sep'][0], $GLOBALS['naws_test_sep'][1] );
}
function absint( $n ) { return abs( (int) $n ); }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nDeutsch\n" . str_repeat( '-', 74 ) . "\n";
check( 'Temperatur mit Komma',                NAWS_Helpers::display_value( 'Temperature', 23.44 ), '23,4' );
check( 'ganze Temperatur ohne Nachkomma',     NAWS_Helpers::display_value( 'Temperature', 23.0 ), '23' );
check( 'negative Temperatur',                 NAWS_Helpers::display_value( 'Temperature', -4.26 ), '-4,3' );
check( 'Luftdruck mit Tausenderpunkt',        NAWS_Helpers::display_value( 'Pressure', 1019.14 ), '1.019,1' );
check( 'Feuchte ganzzahlig',                  NAWS_Helpers::display_value( 'Humidity', 78.6 ), '78' );
check( 'CO2 mit Tausenderpunkt',              NAWS_Helpers::display_value( 'CO2', 1240 ), '1.240' );
check( 'Regen eine Stelle',                   NAWS_Helpers::display_value( 'Rain', 0.63 ), '0,6' );
check( 'feste Stellen auf Wunsch',            NAWS_Helpers::display_value( 'Temperature', 23.0, 1 ), '23,0' );
check( 'Zahl ohne Umrechnung',                NAWS_Helpers::display_number( 12.5 ), '12,5' );
check( 'Zahl mit festen Stellen',             NAWS_Helpers::display_number( 3.1, 2 ), '3,10' );
check( 'negative Stellen heissen: wie sie ist', NAWS_Helpers::display_number( 3.25, -1 ), '3,25' );

echo "\nEnglisch\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['naws_test_sep'] = [ '.', ',' ];
check( 'Temperatur mit Punkt',                NAWS_Helpers::display_value( 'Temperature', 23.44 ), '23.4' );
check( 'Luftdruck mit Tausenderkomma',        NAWS_Helpers::display_value( 'Pressure', 1019.14 ), '1,019.1' );

echo "\nUmrechnung bleibt\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['naws_test_sep'] = [ ',', '.' ];
$GLOBALS['naws_test_options']['naws_settings'] = [ 'temperature_unit' => 'F', 'rain_unit' => 'in' ];
check( 'Fahrenheit',                          NAWS_Helpers::display_value( 'Temperature', 20.0 ), '68' );
check( 'Zoll mit drei Stellen',               NAWS_Helpers::display_value( 'Rain', 12.7 ), '0,5' );
check( 'Zoll mit drei Stellen, krumm',        NAWS_Helpers::display_value( 'Rain', 3.3 ), '0,13' );
check( 'format_value bleibt eine Zahl',       is_float( NAWS_Helpers::format_value( 'Temperature', 20.0 ) ), true );

echo "\nAusgabestellen\n" . str_repeat( '-', 74 ) . "\n";
// Wo eine Zahl als Text auf der Seite, in der Mail oder im Backend landet,
// geht sie ueber display_value()/display_number(). format_value() bleibt
// fuer JSON (AJAX, REST) und Rechnungen.
$src = static fn( string $f ): string => (string) file_get_contents( dirname( __DIR__ ) . '/' . $f );
foreach ( [
    'templates/table.php'                  => 3,
    'includes/class-naws-notifications.php' => 2,
    'admin/views/dashboard.php'            => 5,
    'admin/views/shortcodes.php'           => 2,
    'admin/views/appearance.php'           => 1,
] as $f => $n ) {
    check( "$f: $n Stellen ueber display_value()", substr_count( $src( $f ), 'NAWS_Helpers::display_value(' ), $n );
    // appearance.php: die eine uebrige Stelle geht schon durch NAWS_Sparkline::number().
    check( "$f: keine rohe Zahl mehr",             substr_count( $src( $f ), 'NAWS_Helpers::format_value(' ) - substr_count( $src( $f ), 'NAWS_Sparkline::number( \'Temperature\', (float) NAWS_Helpers::format_value(' ), 0 );
}
$h = $src( 'includes/class-naws-helpers.php' );
check( 'Heatmap-Beschriftung',                str_contains( $h, "\$text = self::display_value( 'Temperature', \$value ) . ' ' . self::get_unit( 'Temperature' );" ), true );
$sc = $src( 'includes/class-naws-shortcodes.php' );
check( 'Widget-Kopf',                         str_contains( $sc, "'value' => NAWS_Helpers::display_value( \$param, \$raw )," ), true );
check( '[naws_value] schreibt mit der Sprache', str_contains( $sc, "\$output   = esc_html( NAWS_Helpers::display_number( \$value, \$dec ) . \$unit_str );" ), true );
check( '[naws_calc] schreibt mit der Sprache',  str_contains( $sc, "\$output   = esc_html( NAWS_Helpers::display_number( \$value, \$dec ) . \$unit_str );\n        }" ) || substr_count( $sc, 'NAWS_Helpers::display_number( $value, $dec )' ) === 2, true );
check( '[naws_current] hat einen Anzeigetext', str_contains( $sc, "'display'  => NAWS_Helpers::display_value( \$r['parameter'], floatval( \$r['value'] ) )," ), true );
check( '[naws_current] zeigt ihn',            str_contains( $src( 'templates/current.php' ), 'esc_html($data[\'display\'])' ), true );
$ib = $src( 'templates/infobar.php' );
check( 'Infobar: gefuehlt, Taupunkt, Hitze',  substr_count( $ib, 'NAWS_Helpers::display_number( $' ), 3 );
$js = $src( 'assets/js/frontend.js' );
check( 'Hochzaehlen schreibt mit der Sprache', str_contains( $js, 'el.textContent = nawsNum(start + range * eased, decimals);' ), true );
$lv = $src( 'assets/js/live-boot.js' );
check( 'Live: Karten mit der Sprache',        substr_count( $lv, 'esc(num(val))' ) + substr_count( $lv, 'esc(num(s.v))' ), 2 );
check( 'Live: Aktualisierung mit der Sprache', str_contains( $lv, "var nv=num(p[k].value);" ), true );
check( 'Live: Wind mit der Sprache',          substr_count( $lv, 'num(wv)' ) + substr_count( $lv, 'num(gv)' ) + substr_count( $lv, 'num(gm)' ), 6 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
