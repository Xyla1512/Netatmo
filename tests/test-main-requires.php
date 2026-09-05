<?php
/**
 * Tests fuer die Klassen-Ladeliste in xtx-integration-for-netatmo.php.
 *
 * Der Fehler, der diese Datei rechtfertigt: der Plan "rekorde-sonnenbahn"
 * (05.09.2026) hat includes/class-naws-records.php angelegt, die Klasse in
 * templates/records.php und im Shortcode benutzt -- aber nie in die
 * Hauptdatei eingetragen. PHP prueft eine Klassendefinition nicht beim
 * Laden, sondern erst, wenn sie gebraucht wird: `php -l` war gruen, phpcs
 * war gruen, und die Seite ist erst im Backend gestorben, auf der ersten
 * Seite mit [naws_records] oder [naws_on_this_day]:
 * "PHP Fatal error: Uncaught Error: Class "NAWS_Records" not found".
 *
 * Diese Datei prueft strukturell, dass jede Datei includes/class-naws-*.php
 * auch tatsaechlich per naws_require() geladen wird -- und umgekehrt, dass
 * keine naws_require()-Zeile eine Datei nennt, die es nicht gibt. Damit
 * kann eine neue Klassendatei nie wieder unbemerkt im Verzeichnis liegen
 * bleiben, ohne geladen zu werden.
 *
 *   php tests/test-main-requires.php
 *
 * @package NAWS
 */
$PLUGIN = dirname( __DIR__ ) . '/';
$php    = file_get_contents( $PLUGIN . 'xtx-integration-for-netatmo.php' );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nxtx-integration-for-netatmo.php -- jede Klasse wird geladen\n" . str_repeat( '-', 74 ) . "\n";

$klassendateien = glob( $PLUGIN . 'includes/class-naws-*.php' );
sort( $klassendateien );

foreach ( $klassendateien as $datei ) {
    $basename = basename( $datei );
    $zeile    = "naws_require( NAWS_PLUGIN_DIR . 'includes/$basename' );";
    check( "$basename wird per naws_require geladen", str_contains( $php, $zeile ), true );
}

echo "\nxtx-integration-for-netatmo.php -- jede geladene Datei existiert\n" . str_repeat( '-', 74 ) . "\n";

preg_match_all( "/naws_require\\(\\s*NAWS_PLUGIN_DIR\\s*\\.\\s*'([^']+)'\\s*\\)/", $php, $treffer );
$genannte_dateien = $treffer[1];

check( 'mindestens eine naws_require-Zeile gefunden', count( $genannte_dateien ) > 0, true );

foreach ( $genannte_dateien as $relativ ) {
    check( "$relativ existiert im Plugin", is_file( $PLUGIN . $relativ ), true );
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
