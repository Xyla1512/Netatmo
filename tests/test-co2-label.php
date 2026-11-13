<?php
/**
 * CO2-Stufe 1.000–1.500 ppm auf Deutsch: "Erhöht" statt "Befriedigend".
 *
 * Der Befund (Durchsicht der Seite, November 2026): 1.305 ppm stand als
 * "Befriedigend" da, waehrend der Seitentext ab etwa 1.000 ppm zum Lueften
 * riet. Die Schwellen sind richtig (unter 1.000 gut), das Wort klang nach
 * "alles in Ordnung". Franks Wahl: "Erhöht".
 *
 *   php tests/test-co2-label.php
 *
 * @package NAWS
 */
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
/** msgstr of a msgctxt/msgid pair in a .po file. */
function po_msgstr( string $file, string $ctx, string $id ): ?string {
    $po = file_get_contents( $file );
    if ( ! preg_match( '/msgctxt "' . preg_quote( $ctx, '/' ) . '"\nmsgid "' . preg_quote( $id, '/' ) . '"\nmsgstr "([^"]*)"/', $po, $m ) ) {
        return null;
    }
    return $m[1];
}
$dir = dirname( __DIR__ ) . '/docs/i18n/catalog/';
check( 'de: CO2 1.000–1.500 ppm ist "Erhöht"', po_msgstr( $dir . 'xtx-integration-for-netatmo-de_DE.po', 'co2_fair', 'Fair' ), 'Erhöht' );
check( 'nb: CO2 1.000–1.500 ppm ist "Forhøyet"', po_msgstr( $dir . 'xtx-integration-for-netatmo-nb_NO.po', 'co2_fair', 'Fair' ), 'Forhøyet' );
check( 'Signalstaerke bleibt "Mittel"', po_msgstr( $dir . 'xtx-integration-for-netatmo-de_DE.po', 'signal strength', 'Fair' ), 'Mittel' );
check( 'Wetterlage bleibt "Heiter"', po_msgstr( $dir . 'xtx-integration-for-netatmo-de_DE.po', 'wx_state_fair', 'Fair' ), 'Heiter' );

printf( "\n%d bestanden, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
