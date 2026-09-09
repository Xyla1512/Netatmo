<?php
/**
 * Chrome fuer Android legt sein "automatisches Dunkeldesign" ueber Seiten,
 * die kein color-scheme deklarieren, und invertiert dabei die weissen
 * Flaechen der serverseitigen SVGs zu Schwarz. Gemessen am 09.09.2026 per
 * CDP (Emulation.setAutoDarkModeOverride): "color-scheme: light" aendert
 * nichts, "color-scheme: only light" auf den Wurzelelementen nimmt die
 * Bloecke heraus, ohne dem Theme dreinzureden. Dieser Test haelt fest, dass
 * jedes Template-Wurzelelement von dieser Regel erfasst ist.
 *
 *   php tests/test-color-scheme.php
 *
 * @package NAWS
 */
$root = dirname( __DIR__ );
$css  = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $root . '/assets/css/frontend.css' ) ); // ohne Kommentare

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

// Alle Selektoren der Regelbloecke, die "color-scheme: only light" setzen.
preg_match_all( '/([^{}]+)\{[^{}]*color-scheme\s*:\s*only\s+light[^{}]*\}/i', $css, $m );
$covered = [];
foreach ( $m[1] as $sel ) {
    foreach ( explode( ',', $sel ) as $one ) { $covered[] = trim( preg_replace( '/\s+/', ' ', $one ) ); }
}

// Die Wurzelklasse je Template: das erste class-Attribut mit einer naws-Klasse.
// Das Widget setzt seine Wurzelklasse dynamisch (naws-wgt, ggf. naws-wgt--dark).
$roots = [];
foreach ( glob( $root . '/templates/*.php' ) as $file ) {
    $base = basename( $file );
    if ( in_array( $base, [ 'index.php', 'weather-icon-defs.php' ], true ) ) { continue; }
    if ( $base === 'weather-widget.php' ) { $roots[ $base ] = [ 'naws-wgt' ]; continue; }
    if ( preg_match( '/class="([^"]*\bnaws-[a-z0-9-]+[^"]*)"/', file_get_contents( $file ), $c ) ) {
        $roots[ $base ] = array_values( array_filter( explode( ' ', $c[1] ), static fn( $k ) => str_starts_with( $k, 'naws-' ) ) );
    }
}

echo "\ncolor-scheme: only light auf den Wurzelelementen\n" . str_repeat( '-', 74 ) . "\n";
check( 'es gibt eine Regel mit color-scheme: only light', count( $covered ) > 0, true );
check( 'alle Templates mit Wurzelelement erfasst', count( $roots ), 13 );
foreach ( $roots as $base => $classes ) {
    $hit = false;
    foreach ( $classes as $k ) { if ( in_array( '.' . $k, $covered, true ) ) { $hit = true; break; } }
    check( sprintf( '%-22s -> .%s', $base, implode( ' / .', $classes ) ), $hit, true );
}
check( 'die Regel bleibt bei "only light" (kein "light dark", kein :root)', ! in_array( ':root', $covered, true ) && ! preg_match( '/color-scheme\s*:\s*light\s+dark/i', $css ), true );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
