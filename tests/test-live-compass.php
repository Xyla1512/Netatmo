<?php
/**
 * Himmelsrichtungen in der Kompass-Kachel von [naws_live].
 *
 * Der Fehler (Durchsicht der Seite, November 2026): Die Rose zeigte NE, SE,
 * SW, E, W fest auf Englisch, die Zeile darunter ("225° · SW") stand fest
 * auf Deutsch — beides im Skript, unerreichbar fuer die Uebersetzung. Die
 * Windrose holt dieselben Kuerzel aus naws_label( 'compass_*' ).
 *
 *   php tests/test-live-compass.php
 *
 * @package NAWS
 */
$root = dirname( __DIR__ );
$js   = file_get_contents( $root . '/assets/js/live-boot.js' );
$tpl  = file_get_contents( $root . '/templates/live.php' );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

check( 'Vorlage gibt die 16 Richtungen aus naws_label() mit', (bool) preg_match( "/'compass'\s*=>/", $tpl ) && str_contains( $tpl, "naws_label( 'compass_' ." ), true );
check( 'keine feste deutsche Liste im Skript', str_contains( $js, "'NNO'" ), false );
check( 'keine festen Buchstaben in der Rose', (bool) preg_match( '/font-weight="(800|600)">(N|S|E|W|NE|SE|SW|NW)</', $js ), false );
check( 'Liste C kommt aus NAWS_I18N.compass', (bool) preg_match( '/var C=\(NAWS_I18N&&NAWS_I18N\.compass/', $js ), true );
check( 'cdir() nutzt die Liste C', (bool) preg_match( '/function cdir\(deg\)\{\s*return esc\(C\[/', $js ), true );
check( 'Buchstaben werden escaped', (bool) preg_match( '/esc\(C\[\d+\]\)/', $js ), true );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
