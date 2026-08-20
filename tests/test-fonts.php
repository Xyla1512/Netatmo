<?php
/**
 * Tests for NAWS_Fonts — the list of fonts the plugin may offer.
 *
 * The rule the whole class exists to enforce: offer only fonts the page
 * already serves. The plugin loads no font file of its own, so a family
 * nobody enqueued would simply fall back in the browser and the setting
 * would lie. That is why the list is assembled from what is demonstrably
 * present — WordPress theme.json and font library, the fonts Elementor
 * enqueues — plus the generic stacks every browser resolves without help.
 *
 * The slug is the part that must not drift: it is what naws_appearance
 * stores. A saved font has to keep meaning the same family after an
 * update, and a slug that is no longer available must fall back rather
 * than write a dangling family into the stylesheet.
 *
 *   php tests/test-fonts.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_global_settings'] = [];
$GLOBALS['naws_test_options']         = [];
$GLOBALS['naws_test_post_meta']       = [];
$GLOBALS['naws_test_post_types']      = [];
$GLOBALS['naws_test_font_posts']      = [];

// ── Minimal WordPress surface ────────────────────────────────────────────
function wp_get_global_settings() {
    return $GLOBALS['naws_test_global_settings'];
}
function get_option( $key, $default = false ) {
    return $GLOBALS['naws_test_options'][ $key ] ?? $default;
}
function get_post_meta( $post_id, $key = '', $single = false ) {
    return $GLOBALS['naws_test_post_meta'][ $post_id ][ $key ] ?? '';
}
function post_type_exists( $type ) {
    return in_array( $type, $GLOBALS['naws_test_post_types'], true );
}
function get_posts( $args = [] ) {
    return array_keys( $GLOBALS['naws_test_font_posts'] );
}
function get_the_title( $id = 0 ) {
    return $GLOBALS['naws_test_font_posts'][ $id ] ?? '';
}
function naws__( $k ) { return $k; }
function sanitize_text_field( $s ) { return trim( wp_strip_all_tags( $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }

require_once __DIR__ . '/../includes/class-naws-fonts.php';

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Leert alle Quellen, damit jeder Abschnitt von vorne anfaengt. */
function reset_sources(): void {
    $GLOBALS['naws_test_global_settings'] = [];
    $GLOBALS['naws_test_options']         = [];
    $GLOBALS['naws_test_post_meta']       = [];
    $GLOBALS['naws_test_post_types']      = [];
    $GLOBALS['naws_test_font_posts']      = [];
    NAWS_Fonts::flush_cache();
}

echo "\nNAWS_Fonts\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Ohne jede Quelle bleiben Vererbung und die Standardstapel ────────────
reset_sources();
$fonts = NAWS_Fonts::available();

check( 'die Vererbung steht immer zur Verfuegung',
    isset( $fonts['inherit'] ), true );
check( 'und sie ist woertlich inherit, nicht ein Stapel',
    $fonts['inherit']['stack'], 'inherit' );
check( 'die Systemstapel sind ohne jede Quelle da',
    isset( $fonts['system'], $fonts['serif'], $fonts['sans-serif'], $fonts['monospace'] ), true );
check( 'ein generischer Stapel braucht keine Datei',
    $fonts['serif']['stack'], 'Georgia, "Times New Roman", Times, serif' );
check( 'jeder Eintrag nennt seine Herkunft',
    $fonts['serif']['origin'], 'generic' );

// ── theme.json und Schriftbibliothek ─────────────────────────────────────
// Beide Herkuenfte liefert wp_get_global_settings() im selben Knoten:
// 'theme' sind die Schriften der theme.json, 'custom' die ueber die
// Schriftbibliothek installierten. WordPress gibt fuer beide @font-face
// aus, sie stehen also tatsaechlich zur Verfuegung.
reset_sources();
$GLOBALS['naws_test_global_settings'] = [
    'typography' => [
        'fontFamilies' => [
            'theme' => [
                [ 'name' => 'Manrope', 'slug' => 'manrope', 'fontFamily' => 'Manrope, sans-serif' ],
                [ 'name' => 'Ohne Familie', 'slug' => 'kaputt' ],
            ],
            'custom' => [
                [ 'name' => 'Inter', 'slug' => 'inter', 'fontFamily' => '"Inter", sans-serif' ],
            ],
        ],
    ],
];
$fonts = NAWS_Fonts::available();

check( 'eine theme.json-Schrift wird uebernommen',
    $fonts['wp-manrope']['stack'] ?? null, 'Manrope, sans-serif' );
check( 'ihr Name wird zur Beschriftung',
    $fonts['wp-manrope']['label'] ?? null, 'Manrope' );
check( 'eine Schrift der Bibliothek ebenso',
    $fonts['wp-inter']['stack'] ?? null, '"Inter", sans-serif' );
check( 'die Herkunft unterscheidet WordPress von generisch',
    $fonts['wp-manrope']['origin'] ?? null, 'wp' );
check( 'ein Eintrag ohne fontFamily wird uebergangen',
    isset( $fonts['wp-kaputt'] ), false );
check( 'die Systemstapel bleiben daneben bestehen',
    isset( $fonts['system'] ), true );

// ── Kein typography-Knoten ist der Normalfall, kein Fehler ───────────────
reset_sources();
$GLOBALS['naws_test_global_settings'] = [ 'color' => [ 'palette' => [] ] ];
$fonts = NAWS_Fonts::available();
check( 'ohne typography-Knoten bleibt nur das Generische',
    array_keys( $fonts ), [ 'inherit', 'system', 'sans-serif', 'serif', 'monospace' ] );

// ── Elementor ────────────────────────────────────────────────────────────
// Elementors eigener Katalog umfasst ueber 1600 Familien, fast alle davon
// Google Fonts, die nur geladen werden, wenn die Seite sie benutzt. Genau
// deshalb liest das Plugin ihn NICHT: gelesen wird, was Elementor
// tatsaechlich ausliefert, und das steht im Kit-CSS.
//
// Die Reihenfolge ab hier ist Absicht — ELEMENTOR_VERSION laesst sich nicht
// wieder entfernen, also muss der Fall "kein Elementor" vorher geprueft sein.
reset_sources();
$GLOBALS['naws_test_options']['elementor_active_kit'] = 46;
$GLOBALS['naws_test_post_meta'][46]['_elementor_css'] = [ 'fonts' => [ 'Roboto', 'Roboto Slab' ] ];
$fonts = NAWS_Fonts::available();
check( 'ohne Elementor bleibt sein Kit unbeachtet',
    isset( $fonts['el-roboto'] ), false );

define( 'ELEMENTOR_VERSION', '4.2.3' );

reset_sources();
$GLOBALS['naws_test_options']['elementor_active_kit'] = 46;
$GLOBALS['naws_test_post_meta'][46]['_elementor_css'] = [ 'fonts' => [ 'Roboto', 'Roboto Slab' ] ];
$fonts = NAWS_Fonts::available();

check( 'die Schriften aus dem Kit-CSS stehen zur Wahl',
    isset( $fonts['el-roboto'], $fonts['el-roboto-slab'] ), true );
check( 'der Name wird zitiert und bekommt einen Rueckfall',
    $fonts['el-roboto-slab']['stack'], '"Roboto Slab", sans-serif' );
check( 'beschriftet wird mit dem Namen, den Elementor fuehrt',
    $fonts['el-roboto-slab']['label'], 'Roboto Slab' );
check( 'die Herkunft ist benannt',
    $fonts['el-roboto']['origin'], 'elementor' );

// ── Ein leeres oder fehlendes Kit ist kein Fehler ────────────────────────
reset_sources();
$fonts = NAWS_Fonts::available();
check( 'ohne aktives Kit bleibt nur das Generische',
    array_keys( $fonts ), [ 'inherit', 'system', 'sans-serif', 'serif', 'monospace' ] );

reset_sources();
$GLOBALS['naws_test_options']['elementor_active_kit'] = 46;
$GLOBALS['naws_test_post_meta'][46]['_elementor_css'] = '';
$fonts = NAWS_Fonts::available();
check( 'ein Kit ohne CSS-Meta wird uebergangen',
    count( $fonts ), 5 );

reset_sources();
$GLOBALS['naws_test_options']['elementor_active_kit'] = 46;
$GLOBALS['naws_test_post_meta'][46]['_elementor_css'] = [ 'fonts' => [ '', '   ', [ 'Roboto' ] ] ];
$fonts = NAWS_Fonts::available();
check( 'leere und unsinnige Eintraege fallen heraus',
    count( $fonts ), 5 );

// ── Elementor Pro: hochgeladene Schriften ────────────────────────────────
// Die liegen als Beitraege vor und werden von der Seite selbst
// ausgeliefert — sie gehoeren also auf die Liste. Ohne Pro gibt es den
// Beitragstyp nicht, dann wird gar nicht erst gesucht.
reset_sources();
$GLOBALS['naws_test_font_posts'] = [ 12 => 'Hausschrift' ];
$fonts = NAWS_Fonts::available();
check( 'ohne den Beitragstyp wird nicht gesucht',
    isset( $fonts['el-hausschrift'] ), false );

reset_sources();
$GLOBALS['naws_test_post_types'] = [ 'elementor_font' ];
$GLOBALS['naws_test_font_posts'] = [ 12 => 'Hausschrift', 13 => '' ];
$fonts = NAWS_Fonts::available();
check( 'eine hochgeladene Schrift steht zur Wahl',
    $fonts['el-hausschrift']['stack'] ?? null, '"Hausschrift", sans-serif' );
check( 'ein Beitrag ohne Titel faellt heraus',
    count( $fonts ), 6 );

// ── Dieselbe Schrift aus zwei Quellen erscheint einmal ────────────────────
// Sonst stuenden im Auswahlfeld zwei Zeilen "Inter", die dasselbe tun.
reset_sources();
$GLOBALS['naws_test_global_settings'] = [
    'typography' => [ 'fontFamilies' => [ 'theme' => [
        [ 'name' => 'Inter', 'slug' => 'inter', 'fontFamily' => '"Inter", sans-serif' ],
    ] ] ],
];
$GLOBALS['naws_test_options']['elementor_active_kit'] = 46;
$GLOBALS['naws_test_post_meta'][46]['_elementor_css'] = [ 'fonts' => [ 'Inter' ] ];
$fonts = NAWS_Fonts::available();
check( 'die Doppelung wird verworfen',
    isset( $fonts['el-inter'] ), false );
check( 'und zwar zugunsten der WordPress-Quelle',
    $fonts['wp-inter']['stack'], '"Inter", sans-serif' );

// ── Vom gespeicherten Wert zum Stapel ────────────────────────────────────
reset_sources();
check( 'ein bekannter Schluessel liefert seinen Stapel',
    NAWS_Fonts::stack( 'monospace' ), 'Consolas, Monaco, "Courier New", monospace' );
check( 'nichts gespeichert heisst erben',
    NAWS_Fonts::stack( '' ), 'inherit' );
check( 'ein Schluessel, den es nicht mehr gibt, erbt ebenfalls',
    NAWS_Fonts::stack( 'el-verschwunden' ), 'inherit' );

// Der Notausgang: wer eine Schrift hat, die keine Quelle kennt, traegt sie
// von Hand ein. Was dabei ankommt, muss aber eine Schriftliste sein und
// nichts anderes — der Wert landet unveraendert im Stylesheet.
check( 'die eigene Familie wird uebernommen',
    NAWS_Fonts::stack( 'custom', 'Roboto Slab, sans-serif' ), 'Roboto Slab, sans-serif' );
check( 'eine leere eigene Familie erbt',
    NAWS_Fonts::stack( 'custom', '   ' ), 'inherit' );
check( 'CSS, das sich als Schrift ausgibt, wird verworfen',
    NAWS_Fonts::stack( 'custom', 'Foo; background:url(evil)' ), 'inherit' );
check( 'eine geschweifte Klammer beendet die Regel — verworfen',
    NAWS_Fonts::stack( 'custom', 'Foo} body{display:none' ), 'inherit' );
check( 'Anfuehrungszeichen und Bindestriche sind erlaubt',
    NAWS_Fonts::stack( 'custom', '"PT Sans-Narrow", Arial' ), '"PT Sans-Narrow", Arial' );
check( 'uebermaessige Laenge wird verworfen',
    NAWS_Fonts::stack( 'custom', str_repeat( 'a', 121 ) ), 'inherit' );

// ── Die Gruppierung fuer das Auswahlfeld ─────────────────────────────────
reset_sources();
$GLOBALS['naws_test_global_settings'] = [
    'typography' => [ 'fontFamilies' => [ 'theme' => [
        [ 'name' => 'Manrope', 'slug' => 'manrope', 'fontFamily' => 'Manrope, sans-serif' ],
    ] ] ],
];
$grouped = NAWS_Fonts::grouped();
check( 'die Gruppen stehen in der Reihenfolge der Naehe zur Seite',
    array_keys( $grouped ), [ 'inherit', 'wp', 'generic' ] );
check( 'jede Gruppe fuehrt Schluessel auf Beschriftung',
    $grouped['wp'], [ 'wp-manrope' => 'Manrope' ] );

reset_sources();
$grouped = NAWS_Fonts::grouped();
check( 'leere Gruppen tauchen nicht auf',
    array_keys( $grouped ), [ 'inherit', 'generic' ] );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
