<?php
/**
 * Tests fuer templates/windrose.php: Attribute, Panels, SVG, Kennzahlen,
 * Legende, Tabelle, Umschaltung, und dass nichts Unescaptes, kein Skript
 * und keine MAC-Adresse durchkommt. Die Rechnung steht in test-windrose.php.
 *
 *   php tests/test-windrose-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'wind_unit' => 'kmh' ], 'date_format' => 'd.m.Y' ];
$GLOBALS['naws_test_now']     = gmmktime( 10, 0, 0, 9, 7, 2026 );
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? $GLOBALS['naws_test_now'] ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', ',' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : $s; }
function absint( $n ) { return abs( (int) $n ); }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-windrose.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Dieselben Gruppenzeilen wie in test-windrose.php. */
$rows = [
    [ 'sector' => '0',  'bin' => '0',  'n' => '2701', 'sum_v' => '8103',  'max_v' => '5',  'first_at' => '1776771809' ],
    [ 'sector' => '0',  'bin' => '1',  'n' => '328',  'sum_v' => '2624',  'max_v' => '11', 'first_at' => '1776800000' ],
    [ 'sector' => '1',  'bin' => '0',  'n' => '3379', 'sum_v' => '10137', 'max_v' => '5',  'first_at' => '1776771809' ],
    [ 'sector' => '1',  'bin' => '3',  'n' => '1',    'sum_v' => '22',    'max_v' => '22', 'first_at' => '1779602428' ],
    [ 'sector' => '14', 'bin' => '2',  'n' => '540',  'sum_v' => '7560',  'max_v' => '19', 'first_at' => '1777000000' ],
    [ 'sector' => '-1', 'bin' => '-1', 'n' => '20',   'sum_v' => '0',     'max_v' => '0.5','first_at' => '1778000000' ],
    [ 'sector' => '-2', 'bin' => '0',  'n' => '89',   'sum_v' => '267',   'max_v' => '4',  'first_at' => '1776771809' ],
];
$rose16 = NAWS_Windrose::shape( $rows, 16, 1779602428 );
$rose8  = NAWS_Windrose::shape( $rows, 8, 1779602428 );

/** Attribute, wie shortcode_atts() sie liefert. */
function atts( array $over = [] ): array {
    return array_merge( [
        'period' => '90d', 'measure' => 'wind', 'sectors' => '16', 'from' => '', 'to' => '',
        'show' => 'legend,summary', 'switcher' => 'yes', 'size' => '', 'title' => 'Wind rose',
    ], $over );
}
function render_windrose( array $atts, ?array $naws_roses ): string {
    ob_start();
    include dirname( __DIR__ ) . '/templates/windrose.php';
    return ob_get_clean();
}

echo "\nEin Panel, ohne Umschaltung\n" . str_repeat( '-', 74 ) . "\n";
$html = render_windrose( atts( [ 'switcher' => 'no' ] ), [ '*' => $rose16 ] );
check( 'Wurzel mit Klassen und Marker',    str_contains( $html, '<div class="naws-wrap naws-wr" data-naws-windrose>' ), true );
check( 'Ueberschrift',                     str_contains( $html, '<h3 class="naws-wr-title">Wind rose</h3>' ), true );
check( 'keine Umschaltung',                str_contains( $html, 'naws-wr-switch' ), false );
check( 'genau ein Panel',                  substr_count( $html, 'class="naws-wr-panel"' ), 1 );
check( 'das Panel ist sichtbar',           (bool) preg_match( '/class="naws-wr-panel"[^>]*hidden/', $html ), false );
check( 'Panel traegt Zeitraum und Messgroesse', str_contains( $html, 'data-period="90d" data-measure="wind"' ), true );
check( 'Meta-Zeile',                       str_contains( $html, '<p class="naws-wr-meta">Wind, 10-minute mean · last 90 days · 7,058 readings</p>' ), true );
check( 'ein SVG mit Rolle',                (bool) preg_match( '/<svg class="naws-wr-svg" viewBox="0 0 660 660" role="img" aria-label="Wind rose, last 90 days: 7,058 readings, main direction North-northeast \(47\.9 %\)\."/', $html ), true );
check( '16 Sektoren',                      substr_count( $html, 'class="naws-wr-sector"' ), 16 );
check( 'ein Tooltip je Sektor',            substr_count( $html, '<title>' ), 16 );
check( 'Tooltip von NNE',                  str_contains( $html, '<title>NNE · North-northeast: 47.9 %, 3,380 readings, Mean 3.0 km/h, Max 22 km/h</title>' ), true );
check( 'zehn Ringe bis 50 %',              substr_count( $html, 'class="naws-wr-ring"' ), 10 );
check( 'Ringbeschriftung 50 %',            str_contains( $html, '>50 %</text>' ), true );
check( 'fuenf Klassenpfade',               substr_count( $html, 'class="naws-wr-seg naws-wr-b' ), 5 );
check( 'Bft-4-Pfad von NNE',               str_contains( $html, 'class="naws-wr-seg naws-wr-b4"' ), true );
check( '16 Trefferflaechen',               substr_count( $html, 'class="naws-wr-hit"' ), 16 );
check( '16 Richtungskuerzel',              substr_count( $html, 'class="naws-wr-dir' ), 16 );
check( 'Hauptrichtungen fett',             substr_count( $html, 'naws-wr-dir--p' ), 4 );
check( 'Zwischenrichtungen gedaempft',     substr_count( $html, 'naws-wr-dir--i' ), 8 );
check( 'drei Prozentbeschriftungen',       substr_count( $html, 'class="naws-wr-share"' ), 3 );
check( 'Nabe mit Windstille',              str_contains( $html, '<circle class="naws-wr-hub"' ) && str_contains( $html, '>Calm</text>' ) && str_contains( $html, '>0.3 %</text>' ), true );
check( 'keine Bildunterschrift, wenn die Daten den Zeitraum fuellen', str_contains( $html, 'naws-wr-caption' ), false );
check( 'Kennzahlen',                       str_contains( $html, '<dl class="naws-wr-summary">' ), true );
check( 'Hauptrichtung NNE',                str_contains( $html, '<dd>NNE <small>North-northeast · 47.9 %</small></dd>' ), true );
check( 'zweite Richtung N',                str_contains( $html, '<dd>N <small>42.9 %</small></dd>' ), true );
check( 'Mittel',                           str_contains( $html, '<dd>4.1 km/h</dd>' ), true );
check( 'Spitze mit Datum',                 str_contains( $html, '<dd>22 km/h <small>on ' . wp_date( 'd.m.Y', 1779602428 ) . '</small></dd>' ), true );
check( 'Windstille mit Grenze',            str_contains( $html, '<dd>0.3 % <small>below 1 km/h</small></dd>' ), true );
check( 'Anzahl',                           str_contains( $html, '<dd>7,058</dd>' ), true );
check( 'Legende mit fuenf Klassen',        substr_count( $html, '<li' ), 5 );
check( 'Legende: Klasse 1 mit Anteil',     str_contains( $html, '<span>Bft 1 · 1–5 km/h</span><em>86.1 %</em>' ), true );
check( 'Legende: leere Klasse',            str_contains( $html, '<li class="is-none"><i class="naws-wr-sw naws-wr-b5"></i><span>Bft 5+ · from 29 km/h</span><em>none</em>' ), true );
check( 'Tabelle nur fuer Screenreader',    str_contains( $html, '<table class="naws-wr-table naws-wr-sr">' ), true );
check( 'Tabelle: 16 Zeilen plus Kopf',     substr_count( $html, '<tr>' ), 17 );
check( 'Tabelle: Kopf mit Bft 5+',         str_contains( $html, '<th scope="col">Bft 5+</th>' ), true );
check( 'Tabelle: Zeile NNE',               (bool) preg_match( '#<th scope="row">NNE <small>North-northeast</small></th><td>47\.9 %</td><td>3,380</td><td>3\.0</td><td>22</td><td>3,379</td><td>·</td><td>·</td><td>1</td><td>·</td>#', $html ), true );
check( 'kein style-Block',                 str_contains( $html, '<style' ), false );
check( 'kein Skript',                      str_contains( $html, '<script' ), false );
check( 'kein Handler',                     str_contains( $html, 'onclick' ), false );
check( 'keine MAC-Adresse',                (bool) preg_match( '/[0-9a-f]{2}(:[0-9a-f]{2}){5}/i', $html ), false );

echo "\nBausteine und Attribute\n" . str_repeat( '-', 74 ) . "\n";
$h = render_windrose( atts( [ 'switcher' => 'no', 'show' => 'legend,summary,table' ] ), [ '*' => $rose16 ] );
check( 'show=table macht die Tabelle sichtbar', str_contains( $h, '<table class="naws-wr-table">' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'show' => '' ] ), [ '*' => $rose16 ] );
check( 'show="" : keine Legende',          str_contains( $h, 'naws-wr-legend' ), false );
check( 'show="" : keine Kennzahlen',       str_contains( $h, 'naws-wr-summary' ), false );
check( 'show="" : Tabelle bleibt fuer Screenreader', str_contains( $h, 'naws-wr-table naws-wr-sr' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'show' => 'legend, bogus ,SUMMARY' ] ), [ '*' => $rose16 ] );
check( 'show ist unempfindlich gegen Leerzeichen und Grossschreibung', str_contains( $h, 'naws-wr-legend' ) && str_contains( $h, 'naws-wr-summary' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'sectors' => '8' ] ), [ '*' => $rose8 ] );
check( '8 Sektoren',                       substr_count( $h, 'class="naws-wr-sector"' ), 8 );
check( '8 Sektoren: NE statt NNE',         str_contains( $h, '>NE</text>' ) && ! str_contains( $h, '>NNE</text>' ), true );
check( '8 Sektoren: keine gedaempften Kuerzel', str_contains( $h, 'naws-wr-dir--i' ), false );
$h = render_windrose( atts( [ 'switcher' => 'no', 'sectors' => '12' ] ), [ '*' => $rose16 ] );
check( 'sectors=12 faellt auf 16',         substr_count( $h, 'class="naws-wr-sector"' ), 16 );
$h = render_windrose( atts( [ 'switcher' => 'no', 'size' => '420' ] ), [ '*' => $rose16 ] );
check( 'size setzt die Hoechstbreite',     str_contains( $h, ' style="max-width:420px"' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'size' => '50' ] ), [ '*' => $rose16 ] );
check( 'size ausserhalb 200–1200 wird ignoriert', str_contains( $h, 'style=' ), false );
$h = render_windrose( atts( [ 'switcher' => 'no', 'size' => '420px" onload="x' ] ), [ '*' => $rose16 ] );
check( 'size mit Anhang wird zur Zahl',    str_contains( $h, ' style="max-width:420px"' ) && ! str_contains( $h, 'onload' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'title' => '' ] ), [ '*' => $rose16 ] );
check( 'title="" laesst die Ueberschrift weg', str_contains( $h, '<h3' ), false );
$h = render_windrose( atts( [ 'switcher' => 'no', 'title' => 'Mein <b>Wind</b>' ] ), [ '*' => $rose16 ] );
check( 'title wird bereinigt und escaped', str_contains( $h, '<h3 class="naws-wr-title">Mein Wind</h3>' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'measure' => 'gust' ] ), [ '*' => $rose16 ] );
check( 'Boeen: Meta-Zeile',                str_contains( $h, 'Gusts, 10-minute peak' ), true );
check( 'Boeen: Spitze heisst anders',      str_contains( $h, '<dt>Strongest gust</dt>' ), true );
$h = render_windrose( atts( [ 'switcher' => 'no', 'measure' => 'storm' ] ), [ '*' => $rose16 ] );
check( 'measure=storm faellt auf wind',    str_contains( $h, 'data-measure="wind"' ), true );
$GLOBALS['naws_test_options']['naws_settings']['wind_unit'] = 'ms';
$h = render_windrose( atts( [ 'switcher' => 'no' ] ), [ '*' => $rose16 ] );
check( 'm/s: Legende umgerechnet',         str_contains( $h, '<span>Bft 1 · 0.3–1.4 m/s</span>' ), true );
check( 'm/s: Mittel umgerechnet',          str_contains( $h, '<dd>1.1 m/s</dd>' ), true );
$GLOBALS['naws_test_options']['naws_settings']['wind_unit'] = 'kmh';

echo "\nUmschaltung\n" . str_repeat( '-', 74 ) . "\n";
$h = render_windrose( atts(), [ '*' => $rose16 ] );
check( 'Umschaltung versteckt, bis das Skript kommt', str_contains( $h, '<div class="naws-wr-switch" hidden>' ), true );
check( 'fuenf Zeitraum-Knoepfe',           substr_count( $h, ' data-period="' ) - 5, 5 );   // 5 Knoepfe + 5 Panels
check( 'der aktive Knopf',                 str_contains( $h, 'class="naws-leg-pill naws-wr-btn is-active" data-period="90d" aria-pressed="true">90 days</button>' ), true );
check( 'ein inaktiver Knopf',              str_contains( $h, 'class="naws-leg-pill naws-wr-btn" data-period="year" aria-pressed="false">this year</button>' ), true );
check( 'fuenf Panels',                     substr_count( $h, 'class="naws-wr-panel"' ), 5 );
check( 'vier davon versteckt',             preg_match_all( '/class="naws-wr-panel"[^>]*hidden>/', $h ), 4 );
check( 'nur eine Schaltergruppe',          substr_count( $h, 'role="group"' ), 1 );
$h = render_windrose( atts( [ 'period' => '14d' ] ), [ '*' => $rose16 ] );
check( 'period=14d: sechster Knopf',       str_contains( $h, 'data-period="14d" aria-pressed="true">14 days</button>' ), true );
$h = render_windrose( atts( [ 'from' => '2026-05-01', 'to' => '2026-08-31' ] ), [ '*' => $rose16 ] );
check( 'fester Bereich: keine Umschaltung', str_contains( $h, 'naws-wr-switch' ), false );
check( 'fester Bereich: ein Panel',        substr_count( $h, 'class="naws-wr-panel"' ), 1 );
check( 'fester Bereich: Zeitraum im Meta', str_contains( $h, 'last 90 days' ) === false && str_contains( $h, '01.05.2026 to 31.08.2026' ), true );
$h = render_windrose( atts( [ 'measure' => 'both' ] ), [ '*' => $rose16 ] );
check( 'both: zehn Panels',                substr_count( $h, 'class="naws-wr-panel"' ), 10 );
check( 'both: zwei Schaltergruppen',       substr_count( $h, 'role="group"' ), 2 );
check( 'both: Wind- und Boeen-Knopf',      str_contains( $h, 'data-measure="wind" aria-pressed="true">Wind</button>' ) && str_contains( $h, 'data-measure="gust" aria-pressed="false">Gusts</button>' ), true );
$h = render_windrose( atts( [ 'measure' => 'both', 'switcher' => 'no' ] ), [ '*' => $rose16 ] );
check( 'both ohne Zeitraum-Umschaltung: nur der Messgroessen-Schalter', substr_count( $h, 'role="group"' ), 1 );

echo "\nRaender\n" . str_repeat( '-', 74 ) . "\n";
$h = render_windrose( atts( [ 'switcher' => 'no' ] ), [ '*' => NAWS_Windrose::shape( [], 16 ) ] );
check( 'leer: der Satz',                   str_contains( $h, '<p class="naws-wr-empty">No wind readings in this period.</p>' ), true );
check( 'leer: kein SVG',                   str_contains( $h, '<svg' ), false );
check( 'leer: keine Kennzahlen',           str_contains( $h, 'naws-wr-summary' ), false );
$late = NAWS_Windrose::shape( $rows, 16, 1779602428 );
$h = render_windrose( atts( [ 'switcher' => 'no', 'period' => 'all' ] ), [ '*' => $late ] );
check( 'all: Bildunterschrift „readings from 21.04.2026"', str_contains( $h, '<figcaption class="naws-wr-caption">readings from ' . wp_date( 'd.m.Y', 1776771809 ) . '</figcaption>' ), true );
$h = render_windrose( atts(), [ 'wind|90d' => $rose16, 'wind|7d' => NAWS_Windrose::shape( [], 16 ), 'wind|30d' => $rose16, 'wind|year' => $rose16, 'wind|all' => $rose16 ] );
check( 'je Panel seine Rose: 7d ist leer', substr_count( $h, 'naws-wr-empty' ), 1 );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
