<?php
/**
 * Tests fuer NAWS_Sparkline: Attribute, Ausduennen, Regen-Eimer,
 * Geometrie, der Datenweg ueber eine gestubbte Datenbank und die
 * Aufbereitung in Anzeige-Einheiten. Das Markup steht in
 * test-sparkline-render.php.
 *
 *   php tests/test-sparkline.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'time_format' => 'H:i', 'date_format' => 'd.m.Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? 0 ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', ',' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : ''; }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-sparkline.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nnormalise_atts()\n" . str_repeat( '-', 74 ) . "\n";

$n = NAWS_Sparkline::normalise_atts( [] );
check( 'Vorgabe: Temperatur',                 $n['param'], 'Temperature' );
check( 'Vorgabe: Rohwerte',                   $n['source'], 'raw' );
check( 'Vorgabe: 24 Stunden',                 $n['hours'], 24 );
check( 'Vorgabe: keine Tage',                 $n['days'], 0 );
check( 'Vorgabe: aussen gemessen',            $n['module'], 'outdoor' );
check( 'Vorgabe: 80 breit',                   $n['w'], 80 );
check( 'Vorgabe: 18 hoch',                    $n['h'], 18 );
check( 'Vorgabe: Breite nicht gesetzt',       $n['sized_w'], false );
check( 'Vorgabe: Hoehe nicht gesetzt',        $n['sized_h'], false );
check( 'Vorgabe: nur die Kurve',              $n['show'], 'none' );
check( 'Vorgabe: Linie',                      $n['type'], 'line' );
check( 'Vorgabe: kein Band',                  $n['band'], false );

check( 'hours 500 wird 168',                  NAWS_Sparkline::normalise_atts( [ 'hours' => '500' ] )['hours'], 168 );
check( 'hours 0 wird 1',                      NAWS_Sparkline::normalise_atts( [ 'hours' => '0' ] )['hours'], 1 );
check( 'hours leer bleibt 24',                NAWS_Sparkline::normalise_atts( [ 'hours' => '' ] )['hours'], 24 );
check( 'hours Unsinn wird 1',                 NAWS_Sparkline::normalise_atts( [ 'hours' => 'abc' ] )['hours'], 1 );

check( 'Druck misst die Basisstation',        NAWS_Sparkline::normalise_atts( [ 'param' => 'Pressure' ] )['module'], 'indoor' );
check( 'CO2 misst die Basisstation',          NAWS_Sparkline::normalise_atts( [ 'param' => 'CO2' ] )['module'], 'indoor' );
check( 'Boeen misst der Windmesser',          NAWS_Sparkline::normalise_atts( [ 'param' => 'GustStrength' ] )['module'], 'wind' );
$r = NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain' ] );
check( 'Regen misst der Regenmesser',         $r['module'], 'rain' );
check( 'Regen wird zu Balken',                $r['type'], 'bars' );
check( 'ausdruecklich gesetztes Modul bleibt', NAWS_Sparkline::normalise_atts( [ 'module' => 'in-keller' ] )['module'], 'in-keller' );
check( 'Modul wird von Tags befreit',         NAWS_Sparkline::normalise_atts( [ 'module' => '<b>in-keller</b>' ] )['module'], 'in-keller' );

check( 'unbekannte Groesse: nichts',          NAWS_Sparkline::normalise_atts( [ 'param' => 'Unsinn' ] ), null );
check( 'Kleinschreibung zaehlt',              NAWS_Sparkline::normalise_atts( [ 'param' => 'temperature' ] ), null );
check( 'Rohgroesse mit days: nichts',         NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'days' => '30' ] ), null );

$d = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg' ] );
check( 'Tagesspalte: Tagestabelle',           $d['source'], 'day' );
check( 'Tagesspalte ohne days: 30',           $d['days'], 30 );
check( 'Tagesspalte leiht die Einheit',       $d['base'], 'Temperature' );
check( 'Tagesspalte: kein Modul',             $d['module'], '' );
check( 'days 1 wird 2',                       NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '1' ] )['days'], 2 );
check( 'days 999 wird 366',                   NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '999' ] )['days'], 366 );
check( 'rain_sum wird zu Balken',             NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum' ] )['type'], 'bars' );

check( 'Band bei temp_avg',                   NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'band' => 'minmax' ] )['band'], true );
check( 'Band nicht bei temp_max',             NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_max', 'band' => 'minmax' ] )['band'], false );
check( 'Band nicht bei Balken',               NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'band' => 'minmax', 'type' => 'bars' ] )['band'], false );
check( 'Band nicht bei Rohwerten',            NAWS_Sparkline::normalise_atts( [ 'band' => 'minmax' ] )['band'], false );

check( 'type bars gilt auch fuer Temperatur', NAWS_Sparkline::normalise_atts( [ 'type' => 'bars' ] )['type'], 'bars' );
check( 'type Unsinn wird automatisch',        NAWS_Sparkline::normalise_atts( [ 'type' => 'pie' ] )['type'], 'line' );

$s = NAWS_Sparkline::normalise_atts( [ 'width' => '5', 'height' => '500' ] );
check( 'width 5 wird 20',                     $s['w'], 20 );
check( 'height 500 wird 200',                 $s['h'], 200 );
check( 'Breite gilt als gesetzt',             $s['sized_w'], true );
check( 'Hoehe gilt als gesetzt',              $s['sized_h'], true );
check( 'width 1000 wird 600',                 NAWS_Sparkline::normalise_atts( [ 'width' => '1000' ] )['w'], 600 );
check( 'height 3 wird 10',                    NAWS_Sparkline::normalise_atts( [ 'height' => '3' ] )['h'], 10 );

check( 'show VALUE wird value',               NAWS_Sparkline::normalise_atts( [ 'show' => 'VALUE' ] )['show'], 'value' );
check( 'show Unsinn wird none',               NAWS_Sparkline::normalise_atts( [ 'show' => 'x' ] )['show'], 'none' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
