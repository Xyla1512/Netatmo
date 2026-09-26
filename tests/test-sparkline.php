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

echo "\nslot_end()\n" . str_repeat( '-', 74 ) . "\n";
check( 'mitten im Schritt: dessen Ende',      NAWS_Sparkline::slot_end( 1000 ), 1200 );
check( 'auf der Grenze: der naechste',        NAWS_Sparkline::slot_end( 1200 ), 1500 );

echo "\nthin()\n" . str_repeat( '-', 74 ) . "\n";
$zehn = [];
for ( $i = 0; $i < 10; $i++ ) { $zehn[] = [ $i, (float) $i ]; }
check( 'unter der Grenze unveraendert',       NAWS_Sparkline::thin( [ [ 0, 1.0 ], [ 5, 2.0 ] ], 5 ), [ [ 0, 1.0 ], [ 5, 2.0 ] ] );
check( 'zehn auf fuenf: Mittel je Fenster',   NAWS_Sparkline::thin( $zehn, 5 ), [ [ 1, 0.5 ], [ 3, 2.5 ], [ 5, 4.5 ], [ 7, 6.5 ], [ 9, 8.5 ] ] );
$band4 = [ [ 0, 10.0, 8.0, 12.0 ], [ 1, 11.0, 5.0, 13.0 ], [ 2, 12.0, 9.0, 20.0 ], [ 3, 13.0, 10.0, 14.0 ] ];
check( 'Band: Tief ist das Minimum, Hoch das Maximum',
    NAWS_Sparkline::thin( $band4, 2 ), [ [ 1, 10.5, 5.0, 13.0 ], [ 3, 12.5, 9.0, 20.0 ] ] );
$tausend = [];
for ( $i = 0; $i < 1000; $i++ ) { $tausend[] = [ $i * 600, (float) ( $i % 7 ) ]; }
check( 'tausend auf hoechstens zweihundert',  count( NAWS_Sparkline::thin( $tausend ) ) <= 200, true );

echo "\nbuckets()\n" . str_repeat( '-', 74 ) . "\n";
$eimer = NAWS_Sparkline::buckets( [ [ 0, 1.0 ], [ 24, 2.0 ], [ 25, 0.5 ], [ 99, 1.0 ], [ 100, 3.0 ], [ -1, 9.0 ], [ 101, 9.0 ] ], 0, 100, 4 );
check( 'vier Fenster mit Summen, Rand $to zaehlt ins letzte, ausserhalb in keins',
    $eimer, [ [ 0, 25, 3.0 ], [ 25, 50, 0.5 ], [ 50, 75, 0.0 ], [ 75, 100, 4.0 ] ] );
check( 'ohne Meldung: lauter Nullen',         NAWS_Sparkline::buckets( [], 0, 100, 2 ), [ [ 0, 50, 0.0 ], [ 50, 100, 0.0 ] ] );
check( 'leeres Fenster: keine Eimer',         NAWS_Sparkline::buckets( [ [ 5, 1.0 ] ], 100, 100, 4 ), [] );

echo "\ngeometry()\n" . str_repeat( '-', 74 ) . "\n";
$g = NAWS_Sparkline::geometry( [ [ 0, 0.0 ], [ 10, 10.0 ] ], 80, 18 );
check( 'Linie von links unten nach rechts oben', $g['line'], 'M2.00 16.00 L78.00 2.00' );
check( 'Flaeche bis zur Grundlinie',          $g['area'], 'M2.00 16.00 L78.00 2.00 L78.00 16.00 L2.00 16.00 Z' );
check( 'kein Band',                           $g['band'], '' );
check( 'Endpunkt ist der letzte',             $g['end'], [ '78.00', '2.00' ] );
check( 'Tief am ersten Punkt',                $g['lo'], [ '2.00', '16.00' ] );
check( 'Hoch am letzten Punkt',               $g['hi'], [ '78.00', '2.00' ] );
check( 'x-Positionen fuer die Sprechblase',   $g['xs'], [ 2.0, 78.0 ] );

check( 'flache Reihe liegt auf halber Hoehe', NAWS_Sparkline::geometry( [ [ 0, 5.0 ], [ 10, 5.0 ] ], 80, 18 )['line'], 'M2.00 9.00 L78.00 9.00' );

$luecke = NAWS_Sparkline::geometry( [ [ 0, 1.0 ], [ 10, 1.0 ], [ 20, 1.0 ], [ 100, 1.0 ], [ 110, 1.0 ] ], 80, 18 );
check( 'eine Luecke bricht die Linie',        substr_count( $luecke['line'], 'M' ), 2 );
check( 'und die Flaeche',                     substr_count( $luecke['area'], 'Z' ), 2 );

$erst = NAWS_Sparkline::geometry( [ [ 0, 3.0 ], [ 1, 1.0 ], [ 2, 1.0 ], [ 3, 5.0 ], [ 4, 5.0 ] ], 80, 18 );
check( 'Tief beim ersten Auftreten',          $erst['lo'][0], '21.00' );
check( 'Hoch beim ersten Auftreten',          $erst['hi'][0], '59.00' );

$bg = NAWS_Sparkline::geometry( [ [ 0, 10.0, 8.0, 12.0 ], [ 10, 11.0, 9.0, 14.0 ] ], 80, 18, true );
check( 'Band: oben die Hochs, zurueck die Tiefs', $bg['band'], 'M2.00 6.67 L78.00 2.00 L78.00 13.67 L2.00 16.00 Z' );
check( 'Band: die Mittellinie im selben Massstab', $bg['line'], 'M2.00 11.33 L78.00 9.00' );

// Review Focus 1: ein deutsches Gebietsschema darf kein Komma in den Pfad schreiben.
$vorher = setlocale( LC_NUMERIC, '0' );
setlocale( LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany', 'deu' );
$de = NAWS_Sparkline::geometry( [ [ 0, 0.5 ], [ 7, 1.25 ], [ 13, 0.75 ] ], 80, 18 );
setlocale( LC_NUMERIC, $vorher );
check( 'kein Komma im Pfad unter de_DE',      str_contains( $de['line'] . $de['area'], ',' ), false );

echo "\nbar_geometry()\n" . str_repeat( '-', 74 ) . "\n";
$b = NAWS_Sparkline::bar_geometry( [ 0.0, 2.0, 1.0, 0.0 ], 80, 18 );
check( 'nur Fenster mit Regen werden Balken', $b['rects'], [ [ '20.50', '1.00', '18.50', '16.00' ], [ '40.00', '9.00', '18.50', '8.00' ] ] );
check( 'Grundlinie',                          $b['base'], '17.50' );
check( 'x-Mitte jedes Fensters',              $b['xs'], [ 10.8, 30.3, 49.8, 69.3 ] );
check( 'kein Regen: keine Balken',            NAWS_Sparkline::bar_geometry( [ 0.0, 0.0 ], 80, 18 )['rects'], [] );
check( 'keine Fenster: nichts',               NAWS_Sparkline::bar_geometry( [], 80, 18 )['rects'], [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
