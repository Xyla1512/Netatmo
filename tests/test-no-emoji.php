<?php
/**
 * Kein Emoji im Frontend: eigene Piktogramme statt Systememojis.
 *
 * WordPress ersetzt Emojis in Browsern ohne eigene Emoji-Schrift durch
 * Bilder von s.w.org — die IP des Besuchers geht dabei an WordPress.org.
 * Deshalb waechst hier ein Waechter ueber den ganzen Frontend-Code, und die
 * Piktogramme muessen wp_kses() unbeschaedigt ueberstehen.
 *
 *   php tests/test-no-emoji.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_js( $s ) { return (string) $s; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
// wp_kses stand-in: keeps the markup; the allowlist itself is checked below.
function wp_kses( $s, $allowed ) { return $s; }
class NAWS_Colors { public static function get_icon_set() { return 'emoji'; } }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-icons.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** "file:line" for every emoji in front-end code (admin screens excluded). */
function naws_emoji_hits(): array {
    $root = dirname( __DIR__ );
    $hits = [];
    $it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $it as $f ) {
        $p = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $root ) + 1 ) );
        if ( ! preg_match( '#^(templates|includes|assets/js|assets/css)/.+\.(php|js|css)$#', $p ) ) {
            continue;
        }
        if ( $p === 'includes/class-naws-admin.php' ) {
            continue; // admin menu labels, seen by logged-in users only
        }
        foreach ( file( $f->getPathname() ) as $n => $line ) {
            if ( preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B50}\x{FE0F}]/u', $line ) ) {
                $hits[] = $p . ':' . ( $n + 1 );
            }
        }
    }
    return $hits;
}

/** Every tag and attribute of an SVG string that the allowlist does not know. */
function naws_kses_gaps( string $svg ): array {
    $allowed = naws_svg_kses_args();
    $gaps    = [];
    preg_match_all( '/<([a-z]+)([^>]*)>/i', $svg, $tags, PREG_SET_ORDER );
    foreach ( $tags as $t ) {
        $tag = strtolower( $t[1] );
        if ( ! isset( $allowed[ $tag ] ) ) {
            $gaps[] = $tag;
            continue;
        }
        preg_match_all( '/([a-zA-Z-]+)="/', $t[2], $attrs );
        foreach ( $attrs[1] as $a ) {
            // wp_kses_attr_check() looks attributes up in lower case only,
            // so a camelCase key such as 'viewBox' never matches.
            if ( empty( $allowed[ $tag ][ strtolower( $a ) ] ) ) {
                $gaps[] = $tag . '@' . $a;
            }
        }
    }
    return array_values( array_unique( $gaps ) );
}

$keys = [ 'temp', 'humid', 'press', 'wind', 'rain', 'co2', 'noise', 'pin', 'prob', 'compass', 'gust' ];

echo "\nPiktogramme\n" . str_repeat( '-', 74 ) . "\n";
check( 'pictogram() vorhanden', method_exists( 'NAWS_Icons', 'pictogram' ), true );
if ( method_exists( 'NAWS_Icons', 'pictogram' ) ) {
    check( '11 Piktogramme', count( array_filter( array_map( [ 'NAWS_Icons', 'pictogram' ], $keys ) ) ), 11 );
    check( 'unbekannt leer', NAWS_Icons::pictogram( 'x' ), '' );
    foreach ( $keys as $k ) {
        $svg = NAWS_Icons::pictogram( $k );
        check( "$k: Klasse naws-picto", str_contains( $svg, 'class="naws-picto"' ), true );
        check( "$k: aria-hidden", str_contains( $svg, 'aria-hidden="true"' ), true );
        check( "$k: uebersteht kses", naws_kses_gaps( $svg ), [] );
    }
    check( 'viewBox ueberlebt kses (Schluessel klein)', ! empty( naws_svg_kses_args()['svg']['viewbox'] ), true );
    check( 'Groesse als Rueckfall ohne CSS', str_contains( NAWS_Icons::pictogram( 'temp' ), 'width="1em" height="1em"' ), true );
    check( 'CO2-Symbol mit Text', str_contains( NAWS_Icons::pictogram( 'co2' ), '>CO₂</text>' ), true );
    foreach ( [ 'temp', 'humid', 'press', 'wind', 'rain', 'co2', 'noise' ] as $k ) {
        check( "Satz Emoji $k = Piktogramm", NAWS_Icons::get_set( 'emoji' )[ $k ], NAWS_Icons::pictogram( $k ) );
    }
    check( 'JS-Objekt traegt die Klasse', str_contains( NAWS_Icons::get_js_object( 'emoji' ), 'naws-picto' ), true );
}
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );
check( 'CSS nimmt naws-picto von den Linienregeln aus', (bool) preg_match( '/svg\.naws-picto[^{]*\{[^}]*stroke:\s*none/', $css ), true );
$fc = file_get_contents( dirname( __DIR__ ) . '/templates/forecast.php' );
check( 'Vorhersage nutzt Piktogramme', substr_count( $fc, 'NAWS_Icons::pictogram(' ), 6 );

echo "\nRueckfall und Doku\n" . str_repeat( '-', 74 ) . "\n";
check( 'unbekannter Messwert: kein Ersatzsymbol', NAWS_Helpers::get_icon( 'Unbekannt' ), '' );
$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
check( 'readme: kein Fremdinhalt im Browser', str_contains( $readme, 'loads nothing from other servers into the visitor' ), true );
check( 'readme: keine Rechtsberatung', str_contains( $readme, 'This plugin adds nothing to your privacy notice on the visitor side' ), true );

echo "\nWaechter\n" . str_repeat( '-', 74 ) . "\n";
check( 'kein Emoji im Frontend-Code', naws_emoji_hits(), [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
