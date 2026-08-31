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

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
