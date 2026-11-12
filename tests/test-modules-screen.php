<?php
/**
 * Tests fuer das Skript der Modulseite (admin/views/modules.php).
 *
 * Der Fehler, der diese Datei rechtfertigt: der Aktiv-Schalter suchte seine
 * Zeile ueber die id "naws-module-row-" + MAC, mit ":" durch "-" ersetzt.
 * PHP vergibt die id aber mit sanitize_html_class(), das ":" ganz entfernt.
 * Die Suche fand nie etwas: die Zeile wurde beim Abschalten nicht
 * ausgegraut und bekam kein "Inactive"-Abzeichen, erst nach dem Neuladen.
 * Die Zeile kommt jetzt vom Schalter selbst (closest('tr')); damit kann
 * keine zweite Schreibweise der id mehr auseinanderlaufen.
 *
 *   php tests/test-modules-screen.php
 *
 * @package NAWS
 */
$src = file_get_contents( dirname( __DIR__ ) . '/admin/views/modules.php' );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nModulseite: Zeile zum Schalter\n" . str_repeat( '-', 74 ) . "\n";
check( 'das Skript baut die Zeilen-id nicht selbst nach', strpos( $src, "$('#naws-module-row-'" ), false );
check( 'der Schalter findet seine Zeile ueber closest(\'tr\')', (bool) preg_match( "/const row\s*=\s*checkbox\.closest\('tr'\);/", $src ), true );
check( 'das Abzeichen haengt am Anzeigenamen', strpos( $src, "row.find('.naws-module-display').after(" ) !== false, true );

echo "\nModulseite: Anzeigename\n" . str_repeat( '-', 74 ) . "\n";
check( 'Eingabefeld je Modul', strpos( $src, 'class="naws-display-name"' ) !== false, true );
check( 'Feld begrenzt auf DISPLAY_NAME_MAX', strpos( $src, 'NAWS_Database::DISPLAY_NAME_MAX' ) !== false, true );
check( 'Speichern ueber naws_save_display_name', strpos( $src, "action:       'naws_save_display_name'" ) !== false, true );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
