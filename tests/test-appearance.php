<?php
/**
 * Tests for the appearance settings that are not plain colors:
 * the header bar and the font.
 *
 * The header bars of the live widget, of both forecast variants and of the
 * history block all painted themselves the same dark teal, but by three
 * different routes: one read --ink2, which the inline CSS fed from the
 * "dark text" color, and the other two carried the value as a literal. So
 * the only way to recolor a header was to change a text color — which
 * repainted text elsewhere and still left two of the four bars standing.
 * They share one key now.
 *
 * The font is not a color and cannot be validated as one. It is stored as
 * a slug that NAWS_Fonts has to still know, or as a hand-entered family
 * that has to survive sanitize_family() — anything else falls back to
 * inheritance rather than writing a broken declaration into the page.
 *
 *   php tests/test-appearance.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options']         = [];
$GLOBALS['naws_test_global_settings'] = [];

// ── Minimal WordPress surface ────────────────────────────────────────────
function get_option( $key, $default = false ) {
    return $GLOBALS['naws_test_options'][ $key ] ?? $default;
}
function get_post_meta( $post_id, $key = '', $single = false ) { return ''; }
function post_type_exists( $type ) { return false; }
function wp_get_global_settings() { return $GLOBALS['naws_test_global_settings']; }
require_once __DIR__ . '/i18n-stubs.php';
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }

require_once __DIR__ . '/../includes/class-naws-fonts.php';
require_once __DIR__ . '/../includes/class-naws-colors.php';

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

/** Setzt die gespeicherte Option und leert beide Zwischenspeicher. */
function saved( array $option ): void {
    $GLOBALS['naws_test_options']['naws_appearance'] = $option;
    NAWS_Colors::flush_cache();
    NAWS_Fonts::flush_cache();
}

echo "\nKopfleisten und Schrift\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Die Standardwerte halten das heutige Aussehen fest ───────────────────
saved( [] );
$d = NAWS_Colors::get_defaults();
check( 'der Kopfhintergrund hat einen eigenen Schluessel',
    $d['header_bg'] ?? null, '#2d5252' );
check( 'die Kopfschrift ebenfalls',
    $d['header_text'] ?? null, '#ffffff' );
check( 'die Schrift erbt ab Werk',
    $d['font_family'] ?? null, 'inherit' );
check( 'und es ist keine eigene Familie hinterlegt',
    $d['font_custom'] ?? null, '' );

// ── Ausgabe als CSS-Variablen ────────────────────────────────────────────
saved( [ 'header_bg' => '#123456', 'header_text' => '#fedcba' ] );
$css = NAWS_Colors::get_inline_css();
check( 'der Kopfhintergrund wird als Variable ausgegeben',
    (bool) preg_match( '/--naws-header-bg:\s*#123456;/', $css ), true );
check( 'die Kopfschrift auch',
    (bool) preg_match( '/--naws-header-text:\s*#fedcba;/', $css ), true );

// Die Historie und die alleinstehende Vorhersage haben bisher gar keine
// Variablen bekommen — ohne sie im Selektor bliebe ihr Kopf, wie er war.
foreach ( [ '.naws-wrap', '.naws-wx', '.naws-hist', '.naws-hist-modal', '.naws-fc-wrap' ] as $sel ) {
    check( "der Selektor deckt {$sel} ab",
        (bool) preg_match( '/' . preg_quote( $sel, '/' ) . '[,\s]/', $css ), true );
}

// ── Ein verbogenes "Text dunkel" wird einmalig uebernommen ───────────────
// Wer bisher die Kopfzeile faerben wollte, konnte nur dieses Feld
// verstellen. Nach dem Update darf die Seite deshalb nicht zurueckspringen.
saved( [ 'theme_text_dark' => '#800020' ] );
check( 'der alte Umweg wird zur Kopffarbe',
    NAWS_Colors::get( 'header_bg' ), '#800020' );
check( 'die Textfarbe bleibt dabei, was sie war',
    NAWS_Colors::get( 'theme_text_dark' ), '#800020' );

saved( [ 'theme_text_dark' => '#800020', 'header_bg' => '#111111' ] );
check( 'eine bereits gesetzte Kopffarbe wird nicht ueberschrieben',
    NAWS_Colors::get( 'header_bg' ), '#111111' );

saved( [] );
check( 'ohne Abweichung bleibt es beim Standard',
    NAWS_Colors::get( 'header_bg' ), '#2d5252' );

// ── Die Schrift ist keine Farbe ──────────────────────────────────────────
$clean = NAWS_Colors::sanitize( [ 'font_family' => 'monospace' ] );
check( 'ein bekannter Schluessel wird gespeichert',
    $clean['font_family'], 'monospace' );

$clean = NAWS_Colors::sanitize( [ 'font_family' => 'el-gibtsnicht' ] );
check( 'ein unbekannter Schluessel faellt auf Vererbung zurueck',
    $clean['font_family'], 'inherit' );

$clean = NAWS_Colors::sanitize( [ 'font_family' => '#2d5252' ] );
check( 'eine Farbe ist kein Schriftschluessel',
    $clean['font_family'], 'inherit' );

$clean = NAWS_Colors::sanitize( [ 'font_family' => 'custom', 'font_custom' => 'Roboto Slab, sans-serif' ] );
check( 'die eigene Familie wird uebernommen',
    $clean['font_custom'], 'Roboto Slab, sans-serif' );

$clean = NAWS_Colors::sanitize( [ 'font_family' => 'custom', 'font_custom' => 'Foo; background:url(evil)' ] );
check( 'CSS im Freitextfeld wird verworfen',
    $clean['font_custom'], '' );

// ── icon_set fehlt im Eingang ─────────────────────────────────────────────
$naws_warnings = [];
set_error_handler( function ( $no, $msg ) use ( &$naws_warnings ) { $naws_warnings[] = $msg; return true; }, E_WARNING | E_NOTICE );
$clean = NAWS_Colors::sanitize( [ 'font_family' => 'serif' ] );
restore_error_handler();
check( 'ohne icon_set im Eingang: Standard emoji',
    $clean['icon_set'], 'emoji' );
check( 'ohne icon_set im Eingang: keine Warnung',
    $naws_warnings, [] );
$clean = NAWS_Colors::sanitize( [ 'icon_set' => 'filled' ] );
check( 'ein gueltiges icon_set wird uebernommen',
    $clean['icon_set'], 'filled' );
$clean = NAWS_Colors::sanitize( [ 'icon_set' => 'gibtsnicht' ] );
check( 'ein unbekanntes icon_set faellt auf emoji zurueck',
    $clean['icon_set'], 'emoji' );

// ── Ausgabe der Schrift ──────────────────────────────────────────────────
saved( [ 'font_family' => 'serif' ] );
$css = NAWS_Colors::get_inline_css();
check( 'die gewaehlte Schrift wird als Variable ausgegeben',
    (bool) preg_match( '/--naws-font:\s*Georgia, "Times New Roman", Times, serif;/', $css ), true );

saved( [ 'font_family' => 'custom', 'font_custom' => '"PT Sans", Arial' ] );
$css = NAWS_Colors::get_inline_css();
check( 'die eigene Familie ebenso',
    (bool) preg_match( '/--naws-font:\s*"PT Sans", Arial;/', $css ), true );

saved( [ 'font_family' => 'el-verschwunden' ] );
$css = NAWS_Colors::get_inline_css();
check( 'eine Schrift, die es nicht mehr gibt, erbt',
    (bool) preg_match( '/--naws-font:\s*inherit;/', $css ), true );

echo "\nSparkline-Farben\n";

saved( [] );
check( 'acht Schluessel in fester Reihenfolge', NAWS_Colors::SPARKLINE_KEYS, [
    'sparkline_line', 'sparkline_line_dark', 'sparkline_rain', 'sparkline_rain_dark',
    'sparkline_band', 'sparkline_dots', 'sparkline_tip_bg', 'sparkline_tip_text',
] );
check( 'die Vorgaben', array_intersect_key( NAWS_Colors::DEFAULTS, array_flip( NAWS_Colors::SPARKLINE_KEYS ) ), [
    'sparkline_line'      => '#427272',
    'sparkline_line_dark' => '#7cc7c7',
    'sparkline_rain'      => '#3585b0',
    'sparkline_rain_dark' => '#78ace8',
    'sparkline_band'      => '#427272',
    'sparkline_dots'      => '#7aa0a0',
    'sparkline_tip_bg'    => '#2d5252',
    'sparkline_tip_text'  => '#ffffff',
] );
check( 'eigene Gruppe',                        NAWS_Colors::get_groups()['sparkline']['keys'], NAWS_Colors::SPARKLINE_KEYS );

$sl = NAWS_Colors::sparkline_css();
check( 'eine Regel fuer Kurve und Sprechblase', str_starts_with( $sl, ".naws-sl, .naws-sl-tip {\n" ), true );
check( 'Linie als Variable',                   str_contains( $sl, "  --naws-sl-line: #427272;\n" ), true );
check( 'Linie auf dunklem Grund',              str_contains( $sl, "  --naws-sl-line-dark: #7cc7c7;\n" ), true );
check( 'Band mit Deckung',                     str_contains( $sl, "  --naws-sl-band: #427272;\n" ), true );
check( 'Sprechblase',                          str_contains( $sl, "  --naws-sl-tip-bg: #2d5252;\n" ), true );
check( 'get_inline_css() haengt die Regel an', str_contains( NAWS_Colors::get_inline_css(), $sl ), true );

saved( [ 'sparkline_line' => '#123456', 'sparkline_rain' => '' ] );
check( 'gespeicherte Farbe',                   str_contains( NAWS_Colors::sparkline_css(), '--naws-sl-line: #123456;' ), true );
check( 'leere Farbe schreibt keine Variable',  str_contains( NAWS_Colors::sparkline_css(), '--naws-sl-rain:' ), false );

$san = NAWS_Colors::sanitize( [ 'sparkline_line' => '#abcdef', 'sparkline_dots' => 'red' ] );
check( 'Hex bleibt',                           $san['sparkline_line'] ?? null, '#abcdef' );
check( 'ein Farbname faellt weg',              array_key_exists( 'sparkline_dots', $san ), false );
saved( [] );

echo "\nReiter des Erscheinungsbilds\n";

check( 'zehn Reiter, Basis zuerst', array_keys( NAWS_Colors::appearance_tabs() ), [
    'theme', 'icons', 'live_wind', 'chart24h', 'charttheme', 'history', 'heatmap', 'windrose', 'sparkline', 'forecast',
] );
check( 'ein bekannter Reiter bleibt',         NAWS_Colors::appearance_tab( 'windrose' ), 'windrose' );
check( 'ein unbekannter wird Basis',          NAWS_Colors::appearance_tab( 'nonsense' ), 'theme' );
check( 'leer wird Basis',                     NAWS_Colors::appearance_tab( '' ), 'theme' );

$admin_src = (string) file_get_contents( __DIR__ . '/../includes/class-naws-admin.php' );
check( 'Speichern liest den Reiter sanitiert', str_contains( $admin_src, "NAWS_Colors::appearance_tab( isset( \$_POST['naws_tab'] ) ? sanitize_key( wp_unslash( \$_POST['naws_tab'] ) ) : '' )" ), true );
check( '… und kehrt dorthin zurueck',         str_contains( $admin_src, "admin_url( 'admin.php?page=naws-appearance&updated=1&tab=' . \$tab )" ), true );

$view = (string) file_get_contents( __DIR__ . '/../admin/views/appearance.php' );
check( 'Reiterleiste im WordPress-Stil',      str_contains( $view, '<nav class="nav-tab-wrapper naws-appearance-tabs"' ), true );
check( 'der View liest die Liste aus NAWS_Colors', str_contains( $view, '$tabs = NAWS_Colors::appearance_tabs();' ), true );
check( 'jede Flaeche kennt ihren Zustand',    substr_count( $view, 'class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class(' ), count( NAWS_Colors::appearance_tabs() ) );
check( 'keine fest aktive Flaeche mehr',      str_contains( $view, 'class="naws-appearance-pane active"' ), false );
check( 'verstecktes Feld fuer den Reiter',    str_contains( $view, '<input type="hidden" name="naws_tab" id="naws-tab-field"' ), true );
check( 'die Sparkline-Flaeche liest ihre Gruppe', str_contains( $view, "\$groups['sparkline']['keys']" ), true );
check( 'Vorschau-Container',                   str_contains( $view, '<div id="naws-preview-sparkline">' ), true );
check( 'das Skript kennt die Gruppe',          str_contains( $view, "if (group === 'sparkline') {" ), true );

$rest = (string) file_get_contents( __DIR__ . '/../admin/views/rest-api-docs.php' );
check( 'REST-Seite: zwei Reiterleisten im WordPress-Stil', substr_count( $rest, 'class="nav-tab-wrapper naws-tab-bar"' ), 2 );
check( 'REST-Seite: aktive Reiter markiert',  substr_count( $rest, 'class="nav-tab naws-tab nav-tab-active active"' ), 2 );

echo "\nSparkline-Karten nehmen die Basis-Theme-Farben\n";
saved( [] );
check( 'Theme-Variablen auch an .naws-sl-card', str_contains( NAWS_Colors::get_inline_css(), ".naws-wrap, .naws-wx, .naws-hm, .naws-sl-card {\n" ), true );
check( 'Schrift und Kopfleiste auch dort',      str_contains( NAWS_Colors::get_inline_css(), '.naws-fc-wrap, .naws-sl-card {' ), true );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
