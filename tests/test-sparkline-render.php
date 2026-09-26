<?php
/**
 * Tests fuer templates/sparkline.php ueber NAWS_Sparkline::markup(): die
 * Wurzel, das SVG mit Rolle und Vorlesetext, die Daten fuer die
 * Sprechblase, die Pfade, und dass weder Farben noch ids noch Skript im
 * Markup stehen. Die Rechnung steht in test-sparkline.php.
 *
 *   php tests/test-sparkline-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'time_format' => 'H:i', 'date_format' => 'd.m.Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? 0 ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', ',' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : ''; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-sparkline.php';

/** A station with nothing in it: render() must come back empty. */
class NAWS_Database {
    public static function get_modules( $active_only = false ) { return []; }
    public static function get_readings( $args = [] ) { return []; }
    public static function get_daily_summaries( $args = [] ) { return []; }
}
class NAWS_Calc {
    public static function station_row_id( array $atts ): ?string { return null; }
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Die Daten fuer die Sprechblase, so wie das Skript sie liest. */
function hover( string $html ): array {
    if ( ! preg_match( '/data-naws-sl="([^"]*)"/', $html, $m ) ) {
        return [];
    }
    return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
}

echo "\nLinie\n" . str_repeat( '-', 74 ) . "\n";
$d    = [ 'kind' => 'line', 'pts' => [ [ 0, 0.0 ], [ 10, 10.0 ] ], 'band' => false, 'tips' => [ '01:00 · 0.0 °C', '01:10 · 10.0 °C' ], 'aria' => 'Temperature <x> & "y"', 'value' => '10.0 °C' ];
$html = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [] ), $d );
check( 'Wurzel ohne Groessenangabe',          str_starts_with( $html, '<span class="naws-sl naws-sl--line"><svg ' ), true );
check( 'viewBox 80 x 18, gestreckt',          str_contains( $html, 'viewBox="0 0 80 18" preserveAspectRatio="none"' ), true );
check( 'Rolle img',                           str_contains( $html, 'role="img"' ), true );
check( 'Vorlesetext escaped',                 str_contains( $html, 'aria-label="Temperature &lt;x&gt; &amp; &quot;y&quot;"' ), true );
check( 'Sprechblase: x-Positionen (JSON ohne .0)', hover( $html )['x'], [ 2, 78 ] );
check( 'Sprechblase: Texte',                  hover( $html )['t'], [ '01:00 · 0.0 °C', '01:10 · 10.0 °C' ] );
check( 'Linienpfad',                          str_contains( $html, '<path class="naws-sl-line" d="M2.00 16.00 L78.00 2.00" vector-effect="non-scaling-stroke"/>' ), true );
check( 'Flaeche darunter',                    str_contains( $html, '<path class="naws-sl-area" d="M2.00 16.00 L78.00 2.00 L78.00 16.00 L2.00 16.00 Z"/>' ), true );
check( 'Ring und Endpunkt als runde Striche', substr_count( $html, 'd="M78.00 2.00 h0"' ), 2 );
check( 'ohne minmax keine Punkte',            str_contains( $html, 'naws-sl-mm' ), false );
check( 'ohne value keine Zahl',               str_contains( $html, 'naws-sl-val' ), false );
check( 'kein Kreis',                          str_contains( $html, '<circle' ), false );
check( 'keine Farbe im Markup',               (bool) preg_match( '/(fill|stroke)="#|style="[^"]*(color|fill|stroke)/', $html ), false );
// Review Focus 5: zwei Sparklines auf einer Seite duerfen sich keine id teilen.
check( 'keine id',                            str_contains( $html, ' id=' ), false );
check( 'kein Skript',                         str_contains( $html, '<script' ), false );
check( 'endet mit der Wurzel',                str_ends_with( $html, '</span>' ), true );

$v = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'show' => 'value' ] ), $d );
check( 'show=value haengt den Wert an',       str_ends_with( $v, '</svg><span class="naws-sl-val">10.0 °C</span></span>' ), true );
$m = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'show' => 'minmax' ] ), $d );
check( 'show=minmax: zwei Punkte',            substr_count( $m, 'class="naws-sl-mm"' ), 2 );
check( 'Tief links unten',                    str_contains( $m, '<path class="naws-sl-mm" d="M2.00 16.00 h0"' ), true );
$s = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'width' => '120', 'height' => '24' ] ), $d );
check( 'Groesse als Variablen an der Wurzel', str_starts_with( $s, '<span class="naws-sl naws-sl--line" style="--naws-sl-w:120px;--naws-sl-h:24px;">' ), true );
check( 'und als viewBox',                     str_contains( $s, 'viewBox="0 0 120 24"' ), true );
$w = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'width' => '120' ] ), $d );
check( 'nur die Breite gesetzt',              str_contains( $w, 'style="--naws-sl-w:120px;"' ), true );

echo "\nBand\n" . str_repeat( '-', 74 ) . "\n";
$b = NAWS_Sparkline::markup(
    NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '30', 'band' => 'minmax' ] ),
    [ 'kind' => 'line', 'pts' => [ [ 0, 10.0, 8.0, 12.0 ], [ 10, 11.0, 9.0, 14.0 ] ], 'band' => true, 'tips' => [ 'a', 'b' ], 'aria' => 'x', 'value' => '' ]
);
check( 'Modifikator band',                    str_starts_with( $b, '<span class="naws-sl naws-sl--band">' ), true );
check( 'das Band',                            str_contains( $b, '<path class="naws-sl-band" d="M2.00 6.67 L78.00 2.00 L78.00 13.67 L2.00 16.00 Z"/>' ), true );
check( 'statt der Flaeche',                   str_contains( $b, 'naws-sl-area' ), false );

echo "\nBalken\n" . str_repeat( '-', 74 ) . "\n";
$r = NAWS_Sparkline::markup(
    NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'show' => 'minmax' ] ),
    [ 'kind' => 'bars', 'sums' => [ 0.0, 2.0, 1.0, 0.0 ], 'tips' => [ 'a', 'b', 'c', 'd' ], 'aria' => 'Rain', 'value' => '3.0 mm' ]
);
check( 'Modifikator bars',                    str_starts_with( $r, '<span class="naws-sl naws-sl--bars">' ), true );
check( 'zwei Balken',                         substr_count( $r, '<rect class="naws-sl-bar"' ), 2 );
check( 'der erste Balken',                    str_contains( $r, '<rect class="naws-sl-bar" x="20.50" y="1.00" width="18.50" height="16.00"/>' ), true );
check( 'Grundlinie',                          str_contains( $r, '<path class="naws-sl-base" d="M0 17.50 H80" vector-effect="non-scaling-stroke"/>' ), true );
check( 'keine Linie',                         str_contains( $r, 'naws-sl-line' ), false );
check( 'minmax gilt nicht fuer Balken',       str_contains( $r, 'naws-sl-mm' ), false );
check( 'eine x-Position je Fenster',          count( hover( $r )['x'] ), 4 );
$trocken = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain' ] ), [ 'kind' => 'bars', 'sums' => [ 0.0, 0.0 ], 'tips' => [ '', '' ], 'aria' => 'Rain', 'value' => '' ] );
check( 'trocken: nur die Grundlinie',         [ str_contains( $trocken, '<rect' ), str_contains( $trocken, 'naws-sl-base' ) ], [ false, true ] );

echo "\nrender()\n" . str_repeat( '-', 74 ) . "\n";
check( 'unbekannte Groesse: leer',            NAWS_Sparkline::render( [ 'param' => 'Unsinn' ] ), '' );
// Review Focus 3: eine Station ohne Daten ergibt keinen leeren Rahmen.
check( 'Station ohne Daten: leer',            NAWS_Sparkline::render( [ 'param' => 'Temperature' ] ), '' );
check( 'ohne Stationszeile: leer',            NAWS_Sparkline::render( [ 'param' => 'temp_avg', 'days' => '30' ] ), '' );

echo "\nStylesheet und Skript\n" . str_repeat( '-', 74 ) . "\n";
$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );
foreach ( [ 'naws-sl-line', 'naws-sl-area', 'naws-sl-band', 'naws-sl-bar', 'naws-sl-base', 'naws-sl-mm', 'naws-sl-ring', 'naws-sl-end', 'naws-sl-val', 'naws-sl-tip' ] as $klasse ) {
    check( "Regel fuer .$klasse", str_contains( $css, ".$klasse" ), true );
}
check( 'Groesse aus Variablen mit em-Vorgabe', str_contains( $css, 'width:var(--naws-sl-w, 4.6em); height:var(--naws-sl-h, 1.05em);' ), true );
check( 'jede Farbvariable hat die Vorgabe als Rueckfall',
    str_contains( $css, 'var(--naws-sl-line, #427272)' ) && str_contains( $css, 'var(--naws-sl-rain, #3585b0)' ) && str_contains( $css, 'var(--naws-sl-tip-bg, #2d5252)' ), true );
check( 'dunkler Grund liest die Dunkel-Farben', str_contains( $css, 'var(--naws-sl-line-dark, #7cc7c7)' ) && str_contains( $css, 'var(--naws-sl-rain-dark, #78ace8)' ), true );
$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/sparkline-boot.js' );
check( 'das Skript setzt Text, nie HTML',       [ str_contains( $js, 'textContent' ), str_contains( $js, 'innerHTML' ) ], [ true, false ] );
check( 'Delegation am Dokument',                str_contains( $js, "document.addEventListener('pointermove'" ), true );
check( 'die Sprechblase geht, wenn der Zeiger das Fenster verlaesst', str_contains( $js, "document.addEventListener('pointerout'" ) && str_contains( $js, "window.addEventListener('blur', hide)" ), true );
check( 'am oberen Rand klappt sie nach unten', str_contains( $js, "classList.add('is-below')" ) && str_contains( $css, '.naws-sl-tip.is-below {' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
