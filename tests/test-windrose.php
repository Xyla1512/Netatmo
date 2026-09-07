<?php
/**
 * Tests fuer NAWS_Windrose: Klassen, Sektoren, Geometrie, die Buendelung
 * der Gruppenzeilen zur Rose, Zeitraeume, Beschriftungen, der Datenweg
 * ueber einen wpdb-Stub, und die uebersetzten Himmelsrichtungen in
 * NAWS_Helpers::degrees_to_compass().
 *
 *   php tests/test-windrose.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'wind_unit' => 'kmh' ], 'date_format' => 'd.m.Y' ];
$GLOBALS['naws_test_now']     = gmmktime( 10, 0, 0, 9, 7, 2026 );   // 07.09.2026, 12:00 MESZ
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? $GLOBALS['naws_test_now'] ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', ',' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
$GLOBALS['naws_test_transients'] = [];
function get_transient( $k ) { return $GLOBALS['naws_test_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['naws_test_transients'][ $k ] = $v; return true; }
require_once __DIR__ . '/i18n-stubs.php';

define( 'NAWS_TABLE_READINGS', 'naws_readings' );
class NAWS_Database { const CACHE_PREFIX = 'naws_cache_'; }
class NAWS_Test_WPDB {
    public $prefix = 'wp_';
    public $last   = [];
    public $rows   = [];
    public $peak   = 0;
    public function prepare( $q, ...$args ) { $this->last[] = [ $q, $args ]; return $q; }
    public function get_results( $q, $out = null ) { return $this->rows; }
    public function get_var( $q ) { return $this->peak; }
}
$wpdb = new NAWS_Test_WPDB();

require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-windrose.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function close( string $name, $got, float $want, float $tol ): void {
    global $passed, $failed;
    if ( is_numeric( $got ) && abs( (float) $got - $want ) <= $tol ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s ± %s, ist %s\n", $name, $want, $tol, var_export( $got, true ) );
}

echo "\nKlassen und Sektoren\n" . str_repeat( '-', 74 ) . "\n";
check( '0,9 km/h ist Windstille',            NAWS_Windrose::bin( 0.9 ), -1 );
check( '1 km/h ist Klasse 0 (Bft 1)',        NAWS_Windrose::bin( 1.0 ), 0 );
check( '5,9 km/h bleibt Bft 1',              NAWS_Windrose::bin( 5.9 ), 0 );
check( '6 km/h ist Bft 2',                   NAWS_Windrose::bin( 6.0 ), 1 );
check( '12 km/h ist Bft 3',                  NAWS_Windrose::bin( 12.0 ), 2 );
check( '19,9 km/h bleibt Bft 3',             NAWS_Windrose::bin( 19.9 ), 2 );
check( '20 km/h ist Bft 4',                  NAWS_Windrose::bin( 20.0 ), 3 );
check( '29 km/h ist Bft 5+',                 NAWS_Windrose::bin( 29.0 ), 4 );
check( '41 km/h bleibt Bft 5+',              NAWS_Windrose::bin( 41.0 ), 4 );
check( '0° ist Norden',                      NAWS_Windrose::sector( 0.0, 16 ), 0 );
check( '11,24° ist noch Norden',             NAWS_Windrose::sector( 11.24, 16 ), 0 );
check( '11,25° ist NNE',                     NAWS_Windrose::sector( 11.25, 16 ), 1 );
check( '348,75° ist wieder Norden',          NAWS_Windrose::sector( 348.75, 16 ), 0 );
check( '348,74° ist NNW',                    NAWS_Windrose::sector( 348.74, 16 ), 15 );
check( '359,9° ist Norden',                  NAWS_Windrose::sector( 359.9, 16 ), 0 );
check( '360° ist Norden',                    NAWS_Windrose::sector( 360.0, 16 ), 0 );
check( '-1° ist ungueltig',                  NAWS_Windrose::sector( -1.0, 16 ), null );
check( '361° ist ungueltig',                 NAWS_Windrose::sector( 361.0, 16 ), null );
check( '8 Sektoren: 22,5° ist NE',           NAWS_Windrose::sector( 22.5, 8 ), 1 );
check( '8 Sektoren: 22,49° ist Norden',      NAWS_Windrose::sector( 22.49, 8 ), 0 );
check( '8 Sektoren: 337,5° ist Norden',      NAWS_Windrose::sector( 337.5, 8 ), 0 );
check( '16 Codes',                           count( NAWS_Windrose::codes( 16 ) ), 16 );
check( '8 Codes beginnen N, NE',             array_slice( NAWS_Windrose::codes( 8 ), 0, 2 ), [ 'N', 'NE' ] );

echo "\nGeometrie\n" . str_repeat( '-', 74 ) . "\n";
[ $x, $y ] = NAWS_Windrose::point( 0.0, 100.0, 330.0 );
close( 'Norden liegt oben',                  $x, 330.0, 0.001 ); close( '… bei y = 230', $y, 230.0, 0.001 );
[ $x, $y ] = NAWS_Windrose::point( 90.0, 100.0, 330.0 );
close( 'Osten liegt rechts',                 $x, 430.0, 0.001 ); close( '… auf der Mittellinie', $y, 330.0, 0.001 );
$d = NAWS_Windrose::arc( -11.25, 11.25, 40.0, 100.0, 330.0 );
check( 'Pfad beginnt mit M',                 str_starts_with( $d, 'M' ), true );
check( 'Pfad hat zwei Boegen',               substr_count( $d, 'A' ), 2 );
check( 'Pfad ist geschlossen',               str_ends_with( $d, 'Z' ), true );
check( 'Nordsektor ist symmetrisch',         (bool) preg_match( '/^M([\d.]+) ([\d.]+)A[\d.]+ [\d.]+ 0 0 1 ([\d.]+) ([\d.]+)L/', $d, $m ) && $m[2] === $m[4] && abs( (float) $m[1] + (float) $m[3] - 660 ) < 0.01, true );

echo "\nBuendelung der Gruppenzeilen\n" . str_repeat( '-', 74 ) . "\n";
// Zeilen, wie die Abfrage sie liefert (Strings, wie $wpdb sie gibt), nach
// den Leipziger Zahlen vom 07.09.2026 gekuerzt: N und NNE stark, NW mittel.
$rows = [
    [ 'sector' => '0',  'bin' => '0',  'n' => '2701', 'sum_v' => '8103',  'max_v' => '5',  'first_at' => '1776771809' ],
    [ 'sector' => '0',  'bin' => '1',  'n' => '328',  'sum_v' => '2624',  'max_v' => '11', 'first_at' => '1776800000' ],
    [ 'sector' => '1',  'bin' => '0',  'n' => '3379', 'sum_v' => '10137', 'max_v' => '5',  'first_at' => '1776771809' ],
    [ 'sector' => '1',  'bin' => '3',  'n' => '1',    'sum_v' => '22',    'max_v' => '22', 'first_at' => '1779602428' ],
    [ 'sector' => '14', 'bin' => '2',  'n' => '540',  'sum_v' => '7560',  'max_v' => '19', 'first_at' => '1777000000' ],
    [ 'sector' => '-1', 'bin' => '-1', 'n' => '20',   'sum_v' => '0',     'max_v' => '0.5','first_at' => '1778000000' ],
    [ 'sector' => '-2', 'bin' => '0',  'n' => '89',   'sum_v' => '267',   'max_v' => '4',  'first_at' => '1776771809' ],
];
$rose = NAWS_Windrose::shape( $rows, 16, 1779602428 );
check( 'n zaehlt alles, auch Windstille und ungueltige Richtung', $rose['n'], 7058 );
check( 'Windstille',                         $rose['calm'], 20 );
check( 'ungueltige Richtungen',              $rose['invalid'], 89 );
check( 'Mittel ueber alle',                  $rose['mean'], 4.1 );
check( 'Spitze',                             $rose['max'], 22.0 );
check( 'Zeit der Spitze wird durchgereicht', $rose['max_at'], 1779602428 );
check( 'aeltester Rohwert',                  $rose['first'], 1776771809 );
check( '16 Sektoren',                        count( $rose['sectors'] ), 16 );
check( 'N: 3029 Messungen',                  $rose['sectors'][0]['n'], 3029 );
check( 'N: Klassen',                         $rose['sectors'][0]['bins'], [ 2701, 328, 0, 0, 0 ] );
close( 'N: Anteil 42,9 %',                   $rose['sectors'][0]['share'], 0.4292, 0.0005 );
check( 'N: Mittel 3,5',                      $rose['sectors'][0]['mean'], 3.5 );
check( 'N: Spitze 11',                       $rose['sectors'][0]['max'], 11.0 );
check( 'leerer Sektor',                      $rose['sectors'][4], [ 'n' => 0, 'share' => 0.0, 'mean' => 0.0, 'max' => 0.0, 'bins' => [ 0, 0, 0, 0, 0 ] ] );
check( 'die drei haeufigsten: NNE, N, NW',   $rose['top'], [ 1, 0, 14 ] );
close( 'Skala endet bei 50 %',               $rose['ring'], 0.5, 0.0001 );
$empty = NAWS_Windrose::shape( [], 16, 123 );
check( 'leer: n = 0',                        $empty['n'], 0 );
check( 'leer: keine Spitzenzeit',            $empty['max_at'], 0 );
check( 'leer: keine Hauptrichtung',          $empty['top'], [] );
close( 'leer: Skala 5 %',                    $empty['ring'], 0.05, 0.0001 );
check( '8 Sektoren',                         count( NAWS_Windrose::shape( [], 8 )['sectors'] ), 8 );
$small = NAWS_Windrose::shape( [ [ 'sector' => 3, 'bin' => 0, 'n' => 3, 'sum_v' => 6, 'max_v' => 3, 'first_at' => 5 ], [ 'sector' => -1, 'bin' => -1, 'n' => 997, 'sum_v' => 0, 'max_v' => 0, 'first_at' => 5 ] ], 16 );
close( 'Skala rundet 0,3 % auf 5 % auf',     $small['ring'], 0.05, 0.0001 );
$flat = NAWS_Windrose::shape( [ [ 'sector' => 2, 'bin' => 0, 'n' => 17, 'sum_v' => 34, 'max_v' => 3, 'first_at' => 5 ], [ 'sector' => 5, 'bin' => 0, 'n' => 3, 'sum_v' => 6, 'max_v' => 3, 'first_at' => 5 ], [ 'sector' => -1, 'bin' => -1, 'n' => 80, 'sum_v' => 0, 'max_v' => 0, 'first_at' => 5 ] ], 16 );
close( '17,0 % rundet auf 20 %',             $flat['ring'], 0.2, 0.0001 );
check( 'top laesst leere Sektoren aus',      $flat['top'], [ 2, 5 ] );

echo "\nHimmelsrichtungen\n" . str_repeat( '-', 74 ) . "\n";
check( 'Kuerzel NNE',                        NAWS_Windrose::compass( 1, 16 ), 'NNE' );
check( 'Kuerzel bei 8 Sektoren: NE',         NAWS_Windrose::compass( 1, 8 ), 'NE' );
check( 'Kuerzel laeuft rund',                NAWS_Windrose::compass( 16, 16 ), 'N' );
check( 'Langform',                           NAWS_Windrose::compass_long( 1, 16 ), 'North-northeast' );
check( 'Langform bei 8 Sektoren',            NAWS_Windrose::compass_long( 7, 8 ), 'Northwest' );
check( 'alle 16 Kuerzel haben ein Label',    count( array_filter( array_map( fn( $i ) => NAWS_Windrose::compass( $i, 16 ), range( 0, 15 ) ) ) ), 16 );
check( 'alle 16 Langformen haben ein Label', count( array_filter( array_map( fn( $i ) => NAWS_Windrose::compass_long( $i, 16 ), range( 0, 15 ) ) ) ), 16 );
check( 'degrees_to_compass(112.5) = ESE',    NAWS_Helpers::degrees_to_compass( 112.5 ), 'ESE' );
check( 'degrees_to_compass(0) = N',          NAWS_Helpers::degrees_to_compass( 0 ), 'N' );
check( 'degrees_to_compass(359) = N',        NAWS_Helpers::degrees_to_compass( 359 ), 'N' );
check( 'degrees_to_compass(-5) stirbt nicht', NAWS_Helpers::degrees_to_compass( -5 ), 'N' );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
