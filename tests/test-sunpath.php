<?php
/**
 * Tests fuer NAWS_Astro::sun_path() und templates/sunpath.php.
 *
 * Die Rechnung ist rein: Koordinaten und Zeitstempel rein, Zeitstempel und
 * Anteile raus. Leipzig (51.34 N, 12.37 O) ist die Referenz, weil die
 * Station des Autors dort steht und die Zahlen sich mit jeder Sonnentabelle
 * gegenpruefen lassen. Sydney prueft die andere Halbkugel.
 *
 *   php tests/test-sunpath.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'time_format' => 'H:i', 'timezone_string' => 'Europe/Berlin' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? time() ); return $d->format( $fmt ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function esc_sql( $s ) { return addslashes( (string) $s ); }
require_once __DIR__ . '/i18n-stubs.php';

// NAWS_Astro liest Koordinaten aus $wpdb; die Sonnenbahn bekommt sie hier direkt.
class NAWS_Test_Coords { public static $coords = [ 'lat' => 51.34, 'lng' => 12.37 ]; }

// templates/sunpath.php falls back to NAWS_Astro::get_coords() when no
// $naws_coords is injected. That method reads $wpdb, so a minimal stub lets
// the "no coordinates yet" path (SHOW COLUMNS finds nothing) run without a
// real database instead of fataling on an undefined global.
class NAWS_Test_WPDB {
    public $prefix = 'wp_';
    public function get_var( $query ) { return null; }
    public function get_row( $query, $output = null ) { return null; }
    public function query( $query ) { return false; }
    public function prepare( $query, ...$args ) { return $query; }
}
$wpdb = new NAWS_Test_WPDB();
define( 'NAWS_TABLE_MODULES', 'naws_modules' );

require_once dirname( __DIR__ ) . '/includes/class-naws-astro.php';

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

echo "\nNAWS_Astro::sun_path() — Leipzig, 5. September 2026\n" . str_repeat( '-', 74 ) . "\n";

$lat = 51.34; $lng = 12.37;
$noon = gmmktime( 10, 0, 0, 9, 5, 2026 );            // 12:00 MESZ
$sp   = NAWS_Astro::sun_path( $lat, $lng, $noon, $noon );
check( 'liefert ein Array',                        is_array( $sp ), true );
check( 'Aufgang vor Kulmination vor Untergang',   $sp['sunrise'] < $sp['transit'] && $sp['transit'] < $sp['sunset'], true );
close( 'Aufgang gegen 06:29 MESZ',                 $sp['sunrise'], gmmktime( 4, 29, 0, 9, 5, 2026 ), 300 );
close( 'Untergang gegen 19:48 MESZ',               $sp['sunset'],  gmmktime( 17, 48, 0, 9, 5, 2026 ), 300 );
close( 'Tageslaenge rund 13:19',                   $sp['day_length'], 13 * 3600 + 19 * 60, 300 );
close( 'mittags ist etwas weniger als die Haelfte um', $sp['progress'], 0.42, 0.03 );
check( 'mittags keine Nacht',                      $sp['night_progress'], null );
check( 'im September werden die Tage kuerzer',    $sp['delta_day'] < 0, true );
close( 'um gut drei Minuten',                      $sp['delta_day'], -3 * 60 - 40, 60 );
check( 'laengster Tag laenger als der kuerzeste', $sp['longest'] > $sp['shortest'], true );
close( 'laengster Tag rund 16:41',                 $sp['longest'],  16 * 3600 + 41 * 60, 600 );
close( 'kuerzester Tag rund 7:56',                 $sp['shortest'], 7 * 3600 + 56 * 60, 600 );
check( 'Daemmerung liegt vor dem Aufgang',        $sp['dawn'] < $sp['sunrise'], true );

$at_rise = NAWS_Astro::sun_path( $lat, $lng, $sp['sunrise'], $noon );
close( 'beim Aufgang steht progress auf 0',        $at_rise['progress'], 0.0, 0.001 );
$at_set = NAWS_Astro::sun_path( $lat, $lng, $sp['sunset'], $noon );
close( 'beim Untergang auf 1',                     $at_set['progress'], 1.0, 0.001 );
$at_transit = NAWS_Astro::sun_path( $lat, $lng, $sp['transit'], $noon );
close( 'bei der Kulmination nahe 0.5',             $at_transit['progress'], 0.5, 0.01 );

$night = NAWS_Astro::sun_path( $lat, $lng, gmmktime( 21, 0, 0, 9, 5, 2026 ), $noon ); // 23:00 MESZ
check( 'nachts kein progress',                     $night['progress'], null );
check( 'nachts ein night_progress zwischen 0 und 1', $night['night_progress'] > 0 && $night['night_progress'] < 1, true );
$early = NAWS_Astro::sun_path( $lat, $lng, gmmktime( 1, 0, 0, 9, 5, 2026 ), $noon ); // 03:00 MESZ
check( 'vor dem Aufgang: spaete Nacht',            $early['night_progress'] > 0.5, true );

echo "\nDie andere Halbkugel und der Rand\n" . str_repeat( '-', 74 ) . "\n";

$syd = NAWS_Astro::sun_path( -33.87, 151.21, gmmktime( 2, 0, 0, 9, 5, 2026 ), gmmktime( 2, 0, 0, 9, 5, 2026 ) );
check( 'Sydney: im September werden die Tage laenger', $syd['delta_day'] > 0, true );
check( 'Sydney: der laengste Tag ist laenger als der kuerzeste', $syd['longest'] > $syd['shortest'], true );
close( 'Sydney: laengster Tag rund 14:24',         $syd['longest'], 14 * 3600 + 24 * 60, 600 );

$polar = NAWS_Astro::sun_path( 78.22, 15.63, gmmktime( 12, 0, 0, 6, 21, 2026 ), gmmktime( 12, 0, 0, 6, 21, 2026 ) ); // Longyearbyen, Polartag
check( 'Polartag: null',                           $polar, null );
$polar_night = NAWS_Astro::sun_path( 78.22, 15.63, gmmktime( 12, 0, 0, 12, 21, 2026 ), gmmktime( 12, 0, 0, 12, 21, 2026 ) ); // Longyearbyen, Polarnacht
check( 'Polarnacht: null',                         $polar_night, null );

echo "\ntemplates/sunpath.php\n" . str_repeat( '-', 74 ) . "\n";

function render_sun( array $atts, ?array $naws_coords, int $naws_now ): string {
    ob_start();
    include dirname( __DIR__ ) . '/templates/sunpath.php';
    return ob_get_clean();
}

$day_html = render_sun( [ 'title' => 'Sun path' ], [ 'lat' => 51.34, 'lng' => 12.37 ], gmmktime( 10, 0, 0, 9, 5, 2026 ) );
check( 'Wurzel mit Klasse',                    str_contains( $day_html, '<section class="naws-sun">' ), true );
check( 'Ueberschrift',                         str_contains( $day_html, '<h3 class="naws-sun-title">Sun path</h3>' ), true );
check( 'ein SVG mit Rolle und Beschreibung',   (bool) preg_match( '/<svg[^>]*role="img"[^>]*aria-label="[^"]*06:2\d[^"]*19:4\d[^"]*"/', $day_html ), true );
check( 'der Bogen ist ein Halbkreis um (200,170)', str_contains( $day_html, 'd="M 60 170 A 140 140 0 0 1 340 170"' ), true );
check( 'am Tag steht die Sonne ueber der Linie', (bool) preg_match( '/<circle class="naws-sun-disc"[^>]*cy="([0-9.]+)"/', $day_html, $m ) && (float) $m[1] < 170, true );
check( 'der vergangene Teil ist gezeichnet',   str_contains( $day_html, 'class="naws-sun-done"' ), true );
check( 'Aufgang, Mittag, Untergang beschriftet', substr_count( $day_html, '<text' ), 3 );
check( 'Textzeile mit Tageslaenge',            (bool) preg_match( '#<p class="naws-sun-text">Day length 13:1\d · \d+ min shorter than yesterday · longest day 16:[34]\d, shortest 7:5\d</p>#', $day_html ), true );
check( 'kein style-Block',                     str_contains( $day_html, '<style' ), false );
check( 'keine MAC-Adresse',                    (bool) preg_match( '/[0-9a-f]{2}(:[0-9a-f]{2}){5}/i', $day_html ), false );

$night_html = render_sun( [ 'title' => '' ], [ 'lat' => 51.34, 'lng' => 12.37 ], gmmktime( 21, 0, 0, 9, 5, 2026 ) );
check( 'nachts steht die Sonne unter der Linie', (bool) preg_match( '/<circle class="naws-sun-disc naws-sun-disc--night"[^>]*cy="([0-9.]+)"/', $night_html, $m ) && (float) $m[1] > 170, true );
check( 'nachts kein vergangener Teil',         str_contains( $night_html, 'class="naws-sun-done"' ), false );
check( 'ohne Titel keine Ueberschrift',        str_contains( $night_html, '<h3' ), false );

check( 'ohne Koordinaten nichts',              render_sun( [ 'title' => 'x' ], null, gmmktime( 10, 0, 0, 9, 5, 2026 ) ), '' );
check( 'Polartag: nichts',                     render_sun( [ 'title' => 'x' ], [ 'lat' => 78.22, 'lng' => 15.63 ], gmmktime( 12, 0, 0, 6, 21, 2026 ) ), '' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
