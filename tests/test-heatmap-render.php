<?php
/**
 * Prueft die Beschriftung einer Kachel und das Markup von
 * templates/heatmap.php.
 *
 *   php tests/test-heatmap-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';
function wp_date( $fmt, $ts ) { return gmdate( $fmt, $ts ); }

require_once $PLUGIN . 'includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Helpers::heatmap_label()\n" . str_repeat( '-', 74 ) . "\n";

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];

check( 'ein Wert traegt seine Einheit',   NAWS_Helpers::heatmap_label( 8.2, 'avg' ), '8.2 °C' );
check( 'kein Wert sagt das',              NAWS_Helpers::heatmap_label( null, null ), 'No reading' );
check( 'ein gerechneter Wert nennt seine Herkunft',
    NAWS_Helpers::heatmap_label( 6.0, 'minmax' ), '6 °C · computed from min and max' );

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'F' ] ];

check( 'in Fahrenheit wird umgerechnet', NAWS_Helpers::heatmap_label( 0, 'avg' ), '32 °F' );
check( 'und die Einheit stimmt mit',     str_contains( NAWS_Helpers::heatmap_label( 20, 'avg' ), '°F' ), true );

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];

echo "\ntemplates/heatmap.php\n" . str_repeat( '-', 74 ) . "\n";

function wp_unique_id( $p = '' ) { static $n = 0; return $p . ( ++$n ); }
function wp_create_nonce( $a ) { return 'testnonce'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }

require_once dirname( __DIR__ ) . '/includes/class-naws-colors.php';

class NAWS_Fonts {
    public static function available() { return [ 'inherit' => 'Inherit' ]; }
    public static function sanitize_family( $s ) { return $s; }
}

class NAWS_Database {
    public static $year_data = [ 'values' => [], 'sources' => [] ];
    public static $range     = [ 'date_begin' => '2024-03-28', 'date_end' => '2026-09-02' ];
    public static function get_daily_data_range( $m = null ) { return self::$range; }
    public static function get_heatmap_year( $y ) { return self::$year_data; }
}

/** Ein leeres Raster fuer $year, in das der Test einzelne Tage setzt. */
function grid( int $year ): array {
    $v = []; $s = [];
    for ( $m = 1; $m <= 12; $m++ ) {
        $d = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $year ) );
        $v[ $m - 1 ] = array_fill( 0, $d, null );
        $s[ $m - 1 ] = array_fill( 0, $d, null );
    }
    return [ 'values' => $v, 'sources' => $s ];
}

function render_hm( array $atts_in = [], ?array $data = null, int $year = 2026 ): string {
    NAWS_Database::$year_data = $data ?? grid( $year );
    $atts = array_merge( [ 'year' => '', 'title' => 'Heatmap', 'legend' => 'yes' ], $atts_in );
    ob_start();
    include dirname( __DIR__ ) . '/templates/heatmap.php';
    return ob_get_clean();
}

check( 'das Template existiert', file_exists( dirname( __DIR__ ) . '/templates/heatmap.php' ), true );

$g = grid( 2026 );
$g['values'][0][0]  = 4.2;  $g['sources'][0][0]  = 'avg';
$g['values'][1][0]  = 6.0;  $g['sources'][1][0]  = 'minmax';
$g['values'][6][14] = 31.5; $g['sources'][6][14] = 'avg';
$html = render_hm( [ 'year' => '2026' ], $g );

check( 'ein Wrapper',                    substr_count( $html, 'class="naws-hm"' ), 1 );
check( 'zwoelf Monatszeilen',            substr_count( $html, '<tr class="naws-hm-row"' ), 12 );
check( 'jede Zeile hat 31 Spalten',      substr_count( $html, '<td' ), 12 * 31 );
check( 'der 31. April existiert nicht',  substr_count( $html, 'naws-hm-x' ), 7 ); // 4x30 Tage + Feb 2026 (28) = 4 + 3
check( 'der erste Januar traegt seine Farbe',
    str_contains( $html, 'data-d="2026-01-01"' ) && str_contains( $html, NAWS_Colors::heatmap_color( 4.2 ) ), true );
check( 'ein gerechneter Tag weist sich aus', str_contains( $html, 'data-src="minmax"' ), true );
check( 'ein gespeicherter ebenso',           str_contains( $html, 'data-src="avg"' ), true );
check( 'ein Tag ohne Messwert bekommt die Grau-Farbe',
    substr_count( $html, NAWS_Colors::heatmap_color( null ) ) > 300, true );
check( 'jede Kachel traegt ihre Tagesnummer', str_contains( $html, 'data-day="1"' ), true );

check( 'die Jahresknoepfe stehen da',   substr_count( $html, 'naws-hm-year' ) >= 3, true );
check( 'das gewaehlte Jahr ist markiert', substr_count( $html, 'is-active' ), 1 );
check( 'der Nonce reist mit',            str_contains( $html, 'data-nonce="testnonce"' ), true );

check( 'keine MAC-Adresse im Markup',
    (bool) preg_match( '/\b[0-9a-f]{2}(?::[0-9a-f]{2}){5}\b/i', $html ), false );
check( 'kein style-Block in der Ausgabe', str_contains( $html, '<style' ), false );
check( 'kein script-Block ebenso',        str_contains( $html, '<script' ), false );

$leap = render_hm( [ 'year' => '2024' ], grid( 2024 ), 2024 );
check( 'im Schaltjahr hat der Februar einen 29.', str_contains( $leap, 'data-d="2024-02-29"' ), true );
check( 'im Normaljahr nicht',                     str_contains( $html, 'data-d="2026-02-29"' ), false );

$noleg = render_hm( [ 'year' => '2026', 'legend' => 'no' ] );
check( 'legend=no laesst die Legende weg', str_contains( $noleg, 'naws-hm-legend' ), false );
check( 'sonst ist sie da',                 str_contains( $html, 'naws-hm-legend' ), true );

$bad = render_hm( [ 'year' => '1998' ] );
check( 'ein Jahr ausserhalb des Bereichs faellt zurueck', str_contains( $bad, 'data-year="1998"' ), false );

$xss = render_hm( [ 'year' => '2026', 'title' => '<script>x</script>' ] );
check( 'der Titel wird escaped', str_contains( $xss, '<script>x' ), false );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
