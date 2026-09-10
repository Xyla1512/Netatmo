<?php
/**
 * Die Umschalter der Windrose sind <button>-Elemente. Hello Elementor
 * bringt in seiner reset.css "button:hover, button:focus { background:
 * #cc3366; color:#fff }" mit — Spezifitaet 0,1,1 — und das schlug am
 * 10.09.2026 auf dev die Plugin-Regel ".naws-wr-btn" (0,1,0): beim
 * Ueberfahren wurden die Pills rot. Dieser Test haelt Franks Regel fest:
 * jeder Knopf aus einem Template hat eigene Hover-Regeln, die Windrosen-
 * Knoepfe tragen in jedem Selektor zwei Klassen, nehmen ihre Farbe aus
 * der Rosen-Palette (--naws-wr-switch) und nie aus --naws-primary.
 *
 *   php tests/test-windrose-buttons.php
 *
 * @package NAWS
 */
$root = dirname( __DIR__ );
$css  = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $root . '/assets/css/frontend.css' ) );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

// Alle Regelbloecke als [selektor, koerper]; @media-Huellen fallen heraus,
// ihre inneren Regeln bleiben.
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER );
$rules = [];
foreach ( $m as $r ) {
    $sel = trim( preg_replace( '/\s+/', ' ', $r[1] ) );
    if ( $sel === '' || $sel[0] === '@' ) { continue; }
    $rules[] = [ 'sel' => $sel, 'body' => $r[2] ];
}

/** Regeln, in deren Selektorliste die Klasse vorkommt. */
$with = static function ( string $class ) use ( $rules ): array {
    return array_values( array_filter( $rules, static fn( $r ) => preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![\w-])/', $r['sel'] ) ) );
};
/** Gibt es eine Regel, deren Selektor die Klasse mit dem Zustand verbindet? */
$has_state = static function ( string $class, string $state ) use ( $with ): bool {
    foreach ( $with( $class ) as $r ) {
        foreach ( explode( ',', $r['sel'] ) as $one ) {
            if ( preg_match( '/\.' . preg_quote( $class, '/' ) . '[^\s,]*:' . preg_quote( $state, '/' ) . '(?![\w-])/', $one ) ) { return true; }
        }
    }
    return false;
};
/** Eigenschaft im Regelkoerper gesetzt? */
$sets = static fn( array $r, string $prop ): bool => (bool) preg_match( '/(^|[\s;])' . preg_quote( $prop, '/' ) . '\s*:/', $r['body'] );

// ── 1. Jeder Knopf aus einem Template hat eine eigene Hover-Regel ─────────
echo "\nKnoepfe aus den Templates\n" . str_repeat( '-', 74 ) . "\n";
$buttons = [];
foreach ( glob( $root . '/templates/*.php' ) as $file ) {
    if ( preg_match_all( '/<button[^>]*class="([^"]*)"/', file_get_contents( $file ), $b ) ) {
        foreach ( $b[1] as $classes ) {
            $names = array_values( array_filter( preg_split( '/[\s<]+/', $classes ), static fn( $k ) => preg_match( '/^naws-[a-z0-9-]+$/', $k ) ) );
            $buttons[ basename( $file ) . ': ' . implode( ' ', $names ) ] = $names;
        }
    }
}
check( 'es gibt Knoepfe in den Templates', count( $buttons ) > 0, true );
foreach ( $buttons as $where => $names ) {
    $hover = false;
    foreach ( $names as $k ) { if ( $has_state( $k, 'hover' ) ) { $hover = true; break; } }
    check( sprintf( '%-45s hat eine :hover-Regel', $where ), $hover, true );
}

// ── 2. Die Windrosen-Knoepfe im Einzelnen ─────────────────────────────────
echo "\nWindrose: .naws-wr-btn\n" . str_repeat( '-', 74 ) . "\n";
$wr = $with( 'naws-wr-btn' );
check( 'es gibt Regeln fuer .naws-wr-btn',        count( $wr ) > 0, true );
check( ':hover-Regel vorhanden',                  $has_state( 'naws-wr-btn', 'hover' ), true );
check( ':focus-visible-Regel vorhanden',          $has_state( 'naws-wr-btn', 'focus-visible' ), true );

$weak = [];
foreach ( $wr as $r ) {
    foreach ( explode( ',', $r['sel'] ) as $one ) {
        if ( str_contains( $one, '.naws-wr-btn' ) && preg_match_all( '/\.[a-zA-Z_-][\w-]*/', $one ) < 2 ) { $weak[] = trim( $one ); }
    }
}
check( 'jeder Selektor traegt mindestens zwei Klassen (schlaegt button:hover des Themes)', $weak, [] );

$hover_sets = [ 'background' => false, 'color' => false, 'border-color' => false ];
foreach ( $wr as $r ) {
    if ( ! preg_match( '/\.naws-wr-btn[^\s,]*:hover/', $r['sel'] ) ) { continue; }
    foreach ( $hover_sets as $p => $v ) { if ( $sets( $r, $p ) || ( $p === 'background' && $sets( $r, 'background-color' ) ) ) { $hover_sets[ $p ] = true; } }
}
check( ':hover setzt Hintergrund, Schrift und Rahmen', $hover_sets, [ 'background' => true, 'color' => true, 'border-color' => true ] );

$active_uses_switch = false; $any_primary = false;
foreach ( $wr as $r ) {
    if ( str_contains( $r['body'], '--naws-primary' ) ) { $any_primary = true; }
    if ( preg_match( '/is-active|aria-pressed/', $r['sel'] ) && str_contains( $r['body'], '--naws-wr-switch' ) ) { $active_uses_switch = true; }
}
check( 'aktiver Knopf nimmt --naws-wr-switch',     $active_uses_switch, true );
check( 'keine Regel der Knoepfe nimmt --naws-primary', $any_primary, false );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
