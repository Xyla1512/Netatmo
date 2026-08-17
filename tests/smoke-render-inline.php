<?php
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function absint( $n ) { return abs( (int) $n ); }
function naws__( $k ) { return $k; }
function add_action( ...$a ) {}
function is_admin() { return false; }
require_once NAWS_PLUGIN_DIR . 'includes/class-naws-astro.php';
require_once NAWS_PLUGIN_DIR . 'includes/class-naws-weather-state.php';
require_once NAWS_PLUGIN_DIR . 'includes/class-naws-weather-icons.php';

// Wrapper and animation are independent switches, and each of the three
// public renderers picks a different pair. The point of this smoke test is
// that they stay different: the widget head must NOT inherit the forecast
// row's --still class, which is exactly the bug fixed in 1.9.0.
$fail = 0;
foreach ( NAWS_Weather_State::STATES as $s ) {
    $in   = NAWS_Weather_Icons::render_inline( $s, 28 );
    $big  = NAWS_Weather_Icons::render( $s, 96 );
    $head = NAWS_Weather_Icons::render_head( $s, 64 );

    $checks = [
        'inline hat svg'            => str_contains( $in, '<svg' ),
        'inline ohne wrapper'       => ! str_contains( $in, 'naws-weather-icon' ),
        'inline still-Klasse'       => str_contains( $in, 'naws-wxi--still' ),
        'inline 28px'               => str_contains( $in, 'width="28" height="28"' ),
        'render behaelt wrapper'    => str_contains( $big, 'naws-weather-icon' ),
        'render ohne still-Klasse'  => ! str_contains( $big, 'naws-wxi--still' ),
        'head hat svg'              => str_contains( $head, '<svg' ),
        'head ohne wrapper'         => ! str_contains( $head, 'naws-weather-icon' ),
        'head ohne still-Klasse'    => ! str_contains( $head, 'naws-wxi--still' ),
        'head 64px als Untergrenze' => str_contains( $head, 'width="64" height="64"' ),
    ];
    foreach ( $checks as $name => $ok ) {
        if ( ! $ok ) { $fail++; echo "  FAIL  {$s}: {$name}\n"; }
    }
}

// An unknown state yields nothing from all three, rather than a broken icon.
foreach ( [ 'render', 'render_inline', 'render_head' ] as $m ) {
    if ( NAWS_Weather_Icons::$m( 'nonsense', 64 ) !== '' ) {
        $fail++; echo "  FAIL  {$m}(): unbekannter Zustand liefert Markup\n";
    }
}

echo $fail === 0 ? "Icon-Renderer: 12 Zustaende x 3 Methoden ok\n" : "{$fail} Fehler\n";
exit( $fail > 0 ? 1 : 0 );
