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
check( 'viewBox 80 x 18, gestreckt',          str_contains( $html, 'viewBox="0 0 80 18" width="80" height="18" preserveAspectRatio="none"' ), true );
check( 'Rolle img',                           str_contains( $html, 'role="img"' ), true );
check( 'Vorlesetext escaped',                 str_contains( $html, 'aria-label="Temperature &lt;x&gt; &amp; &quot;y&quot;"' ), true );
check( 'Sprechblase: x-Positionen (JSON ohne .0)', hover( $html )['x'], [ 2, 78 ] );
check( 'Sprechblase: Texte',                  hover( $html )['t'], [ '01:00 · 0.0 °C', '01:10 · 10.0 °C' ] );
check( 'Linienpfad',                          str_contains( $html, '<path class="naws-sl-line" d="M2.00 16.00 L78.00 2.00" fill="none" stroke="currentColor" vector-effect="non-scaling-stroke"/>' ), true );
check( 'Flaeche darunter',                    str_contains( $html, '<path class="naws-sl-area" d="M2.00 16.00 L78.00 2.00 L78.00 16.00 L2.00 16.00 Z" fill-opacity=".14"/>' ), true );
check( 'Ring und Endpunkt als runde Striche', substr_count( $html, 'd="M78.00 2.00 h0"' ), 2 );
check( 'ohne minmax keine Punkte',            str_contains( $html, 'naws-sl-mm' ), false );
check( 'ohne value keine Zahl',               str_contains( $html, 'naws-sl-val' ), false );
check( 'kein Kreis',                          str_contains( $html, '<circle' ), false );
check( 'keine Farbe im Markup',               (bool) preg_match( '/(fill|stroke)="#|style="[^"]*(color|fill|stroke)/', $html ), false );
check( 'ohne CSS: feste Groesse und keine schwarze Flaeche', str_contains( $html, 'width="80" height="18"' ) && str_contains( $html, 'fill="none"' ), true );
check( 'ohne CSS: Linie in Textfarbe, Band durchscheinend', str_contains( $html, 'stroke="currentColor"' ), true );
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
check( 'das Band',                            str_contains( $b, '<path class="naws-sl-band" d="M2.00 6.67 L78.00 2.00 L78.00 13.67 L2.00 16.00 Z" fill-opacity=".16"/>' ), true );
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
check( 'feste Breite schrumpft mit der Spalte', str_contains( $css, '.naws-sl { display:inline-flex;' ) && str_contains( $css, 'max-width:100%; min-width:0;' ), true );
check( 'jede Farbvariable hat die Vorgabe als Rueckfall',
    str_contains( $css, 'var(--naws-sl-line, #427272)' ) && str_contains( $css, 'var(--naws-sl-rain, #3585b0)' ) && str_contains( $css, 'var(--naws-sl-tip-bg, #2d5252)' ), true );
check( 'dunkler Grund liest die Dunkel-Farben', str_contains( $css, 'var(--naws-sl-line-dark, #7cc7c7)' ) && str_contains( $css, 'var(--naws-sl-rain-dark, #78ace8)' ), true );
$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/sparkline-boot.js' );
check( 'das Skript setzt Text, nie HTML',       [ str_contains( $js, 'textContent' ), str_contains( $js, 'innerHTML' ) ], [ true, false ] );
check( 'Delegation am Dokument',                str_contains( $js, "document.addEventListener('pointermove'" ), true );
check( 'die Sprechblase geht, wenn der Zeiger das Fenster verlaesst', str_contains( $js, "document.addEventListener('pointerout'" ) && str_contains( $js, "window.addEventListener('blur', hide)" ), true );
check( 'Loslassen nach Antippen blendet nicht aus', str_contains( $js, "e.pointerType === 'touch' || e.relatedTarget" ), true );
check( 'am oberen Rand klappt sie nach unten', str_contains( $js, "classList.add('is-below')" ) && str_contains( $css, '.naws-sl-tip.is-below {' ), true );

echo "\nVorschau im Erscheinungsbild\n" . str_repeat( '-', 74 ) . "\n";
$pv = NAWS_Sparkline::preview_set();
check( 'drei Kurven',                         array_keys( $pv ), [ 'line', 'bars', 'band' ] );
check( 'ohne Stationsdaten: Beispiel-Linie mit Tief und Hoch', [ str_contains( $pv['line'], 'naws-sl--line' ), substr_count( $pv['line'], 'class="naws-sl-mm"' ) ], [ true, 2 ] );
check( 'Beispiel-Regen: sechs Balken',        substr_count( $pv['bars'], '<rect class="naws-sl-bar"' ), 6 );
check( 'Beispiel-Band',                       str_contains( $pv['band'], 'class="naws-sl-band"' ), true );
check( 'feste Groesse 200 x 40',              str_contains( $pv['line'], 'style="--naws-sl-w:200px;--naws-sl-h:40px;"' ), true );
check( 'Vorlesetext der Beispiele',           str_contains( $pv['bars'], 'aria-label="Sample sparkline in the chosen colours"' ), true );
check( 'das Beispiel ist fest',               NAWS_Sparkline::sample( 'line' ), NAWS_Sparkline::sample( 'line' ) );

echo "\nKachel\n" . str_repeat( '-', 74 ) . "\n";
$ta = NAWS_Sparkline::normalise_atts( [ 'layout' => 'tile', 'title' => 'Aussen "Garten" & Hof' ] );
$td = [ 'kind' => 'line', 'pts' => [ [ 0, 10.0 ], [ 10, 12.5 ], [ 20, 11.0 ] ], 'band' => false, 'tips' => [ 'a', 'b', 'c' ], 'aria' => 'x', 'value' => '11.0 °C', 'last' => 11.0, 'lo' => 10.0, 'hi' => 12.5, 'name' => 'Temperature', 'unit' => '°C', 'period' => '24 hours' ];
$tile = NAWS_Sparkline::tile_markup( $ta, $td );
check( 'Wurzel der Karte',                    str_starts_with( $tile, '<div class="naws-sl-card naws-sl-tile">' ), true );
// Review Focus 3: title wird escaped.
check( 'Name escaped',                        str_contains( $tile, '<div class="naws-sl-tile-name">Aussen &quot;Garten&quot; &amp; Hof</div>' ), true );
check( 'Wert mit kleiner Einheit',            str_contains( $tile, '<div class="naws-sl-tile-val">11.0<small>°C</small></div>' ), true );
check( 'Nebenzeile Tief/Hoch',                str_contains( $tile, '<div class="naws-sl-tile-sub">Low 10.0 · High 12.5 °C</div>' ), true );
check( 'Kurve 200 x 44 mit Tief/Hoch',        str_contains( $tile, 'viewBox="0 0 200 44"' ) && substr_count( $tile, 'class="naws-sl-mm"' ) === 2, true );
check( 'Kurve in der Kachel',                 str_contains( $tile, '<div class="naws-sl-tile-plot"><span class="naws-sl naws-sl--line">' ), true );
check( 'Karte: keine Farben, keine id',       (bool) preg_match( '/(fill|stroke)="#|style="[^"]*(color|background)| id=/', $tile ), false );
check( 'endet mit der Karte',                 str_ends_with( $tile, '</div>' ), true );
$trocken = NAWS_Sparkline::tile_markup( NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'layout' => 'tile' ] ), [ 'kind' => 'bars', 'sums' => [ 0.0, 0.0 ], 'tips' => [ '', '' ], 'aria' => 'x', 'value' => '0.0 mm', 'name' => 'Rain', 'unit' => 'mm', 'period' => '24 hours', 'total' => 0.0 ] );
check( 'trockene Regenkachel ohne Nebenzeile', str_contains( $trocken, 'naws-sl-tile-sub' ), false );

echo "\nMonatsblock\n" . str_repeat( '-', 74 ) . "\n";
$tage = [ '2026-09-24', '2026-09-25', '2026-09-26' ];
$ma   = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax' ] );
$ma['w'] = 680; $ma['h'] = 64;
$md   = NAWS_Sparkline::prepare( $ma, [ 'dates' => $tage, 'rows' => [ '2026-09-24' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] ] );
$mra  = NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum', 'days' => '3' ] );
$mra['w'] = 680; $mra['h'] = 40;
$mrd  = NAWS_Sparkline::prepare( $mra, [ 'dates' => $tage, 'rows' => [ '2026-09-25' => [ 4.2 ] ] ] );
$month = NAWS_Sparkline::month_markup( $ma, $md, $mra, $mrd, $tage, 3 );
check( 'Wurzel des Blocks',                   str_starts_with( $month, '<div class="naws-sl-card naws-sl-month">' ), true );
check( 'Band-Zeile mit Mittel',               str_contains( $month, '<div class="naws-sl-month-k"><b>14.0 °C</b>Daily mean on 9/26</div>' ), true );
check( 'Band 680 x 64',                       str_contains( $month, 'viewBox="0 0 680 64"' ), true );
check( 'Regen-Zeile',                         str_contains( $month, '<div class="naws-sl-month-k"><b>4.2 mm</b>Rain in 3 days</div>' ) && str_contains( $month, 'viewBox="0 0 680 40"' ), true );
check( 'Achse',                               str_contains( $month, '<div class="naws-sl-month-axis"><div></div><div><span>9/24</span><span>9/26</span></div></div>' ), true );
// Review Focus 2: ohne Regenmesser nur Temperatur und Achse.
$ohne = NAWS_Sparkline::month_markup( $ma, $md, $mra, null, $tage, 3 );
check( 'ohne Regen: eine Zeile',              substr_count( $ohne, 'class="naws-sl-month-row' ), 1 );
check( 'ohne Regen: Achse bleibt',            str_contains( $ohne, 'naws-sl-month-axis' ), true );
check( 'leere Station: Monat leer',           NAWS_Sparkline::render( [ 'layout' => 'month' ] ), '' );
check( 'leere Station: Kachel leer',          NAWS_Sparkline::render( [ 'layout' => 'tile' ] ), '' );

// Review Focus 1: fehlen die ersten Tage, teilen sich Band, Regen und Achse dieselbe Zeitspanne.
$mg = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax' ] );
$dg = NAWS_Sparkline::prepare( $mg, [ 'dates' => $tage, 'rows' => [ '2026-09-25' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] ] );
$dg['domain'] = [
    ( new DateTimeImmutable( '2026-09-24 12:00:00', wp_timezone() ) )->getTimestamp(),
    ( new DateTimeImmutable( '2026-09-26 12:00:00', wp_timezone() ) )->getTimestamp(),
];
$month_luecke = NAWS_Sparkline::month_markup( $mg, $dg, $mra, null, $tage, 3 );
check( 'Luecke am Anfang: Band beginnt bei der Zeitspanne, nicht beim ersten Messwert',
    str_contains( $month_luecke, '<path class="naws-sl-band" d="M40.00' ), true );
check( 'Luecke am Anfang: Band beginnt nicht am Rand wie ohne Zeitspanne',
    str_contains( $month_luecke, '<path class="naws-sl-band" d="M2.00' ), false );

echo "\nKachel-Raster\n" . str_repeat( '-', 74 ) . "\n";
check( 'Vorgabe: die sechs Kacheln der Demo',  NAWS_Sparkline::tile_params( '' ), [ 'Temperature', 'Humidity', 'Pressure', 'WindStrength', 'Rain', 'CO2' ] );
check( 'eigene Liste, Leerzeichen egal',       NAWS_Sparkline::tile_params( ' Rain , Temperature' ), [ 'Rain', 'Temperature' ] );
check( 'Gross/klein egal, doppelt einmal',     NAWS_Sparkline::tile_params( 'pressure,Pressure,co2' ), [ 'Pressure', 'CO2' ] );
check( 'Unbekanntes und Tagesspalten fallen weg', NAWS_Sparkline::tile_params( 'Unsinn,temp_avg,Noise' ), [ 'Noise' ] );
check( 'nur Unbekanntes: leer',                NAWS_Sparkline::tile_params( 'Unsinn' ), [] );
check( 'Raster um die Karten',                 NAWS_Sparkline::tiles_markup( [ '<div>A</div>', '', '<div>B</div>' ] ), '<div class="naws-sl-tiles"><div>A</div>' . "\n" . '<div>B</div></div>' );
check( 'keine Karte: kein Raster',             NAWS_Sparkline::tiles_markup( [ '', '' ] ), '' );
check( 'leere Station: Raster leer',           NAWS_Sparkline::render_tiles( [] ), '' );

$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );
check( 'Raster bricht selbst um',              str_contains( $css, '.naws-sl-tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:12px; }' ), true );
// Review Focus 1: Karte und Kurve füllen jede Spalte.
check( 'Karte füllt die Spalte',              str_contains( $css, '.naws-sl-card { display:block; box-sizing:border-box; width:100%;' ), true );
check( 'Kurve in der Karte über die volle Breite', str_contains( $css, '.naws-sl-tile-plot .naws-sl svg { width:100%; height:44px; }' ), true );
check( 'Monat auf dem Handy einspaltig',       str_contains( $css, '@media (max-width:520px) { .naws-sl-month-row, .naws-sl-month-axis { grid-template-columns:1fr;' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
