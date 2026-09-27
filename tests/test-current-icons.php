<?php
/**
 * Tests fuer die Symbole in [naws_current]: NAWS_Helpers::get_icon() gibt
 * seit 1.6.4 SVG aus dem Symbolsatz zurueck, das Template schrieb es mit
 * esc_html() — auf der Seite stand der Quelltext statt des Bildes. Das SVG
 * geht jetzt durch wp_kses() mit der SVG-Freigabe, ein Emoji bleibt Emoji,
 * und das Stylesheet gibt dem SVG eine Groesse.
 *
 *   php tests/test-current-icons.php
 *
 * @package NAWS
 */
$root = dirname( __DIR__ ) . '/';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\ntemplates/current.php\n" . str_repeat( '-', 74 ) . "\n";
$tpl = (string) file_get_contents( $root . 'templates/current.php' );
check( 'Symbol als SVG durchgelassen',  str_contains( $tpl, '<span class="naws-card-icon"><?php echo wp_kses( $data[\'icon\'], naws_svg_kses_args() ); ?></span>' ), true );
check( 'kein esc_html mehr am Symbol',  str_contains( $tpl, "esc_html(\$data['icon'])" ), false );

echo "\nassets/css/frontend.css\n" . str_repeat( '-', 74 ) . "\n";
$css = (string) file_get_contents( $root . 'assets/css/frontend.css' );
check( 'SVG hat eine Groesse und einen Strich', str_contains( $css, '.naws-card-icon svg { width:1.75rem; height:1.75rem; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
