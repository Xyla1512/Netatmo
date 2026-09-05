<?php
/**
 * Tests fuer assets/js/admin.js -- die Datei, die auf jeder Admin-Seite des
 * Plugins laeuft.
 *
 * Der Fehler, der diese Datei rechtfertigt: seit 1.6.4 standen zwei Handler
 * HINTER dem schliessenden })(jQuery); -- ausserhalb des Blocks, in dem $
 * definiert ist. Folge auf jeder Plugin-Seite im Backend: "TypeError: $ is
 * not a function", und der Knopf "Jetzt bereinigen" in den Einstellungen tat
 * nichts, weil sein Handler nie registriert wurde. Die zweite Haelfte des
 * Blocks war ausserdem tot: die Tagesberechnung hat laengst einen eigenen
 * Handler in admin/views/dashboard.php.
 *
 * Es gibt keinen JS-Test-Runner in diesem Projekt; diese Datei prueft die
 * Struktur des Skripts von PHP aus und laesst node --check laufen, wo node
 * vorhanden ist. Das faengt genau die Fehlerklasse, die 1.6.4 eingeschleppt
 * hat: Code ausserhalb des Blocks, Texte ausserhalb der Uebersetzung.
 *
 *   php tests/test-admin-js.php
 *
 * @package NAWS
 */
$PLUGIN = dirname( __DIR__ ) . '/';
$js     = file_get_contents( $PLUGIN . 'assets/js/admin.js' );
$php    = file_get_contents( $PLUGIN . 'includes/class-naws-admin.php' );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nassets/js/admin.js -- Struktur\n" . str_repeat( '-', 74 ) . "\n";

check( 'genau ein })(jQuery)',                        preg_match_all( '/\}\)\(\s*jQuery\s*\)/', $js ), 1 );
check( 'nach })(jQuery); kommt nichts mehr',           (bool) preg_match( '/\}\)\(\s*jQuery\s*\);\s*$/', $js ), true );
$nach = preg_replace( '/^.*\}\)\(\s*jQuery\s*\);/s', '', $js );
check( 'kein $( ausserhalb des Blocks',                 str_contains( $nach, '$(' ), false );
check( 'der Purge-Knopf hat seinen Handler',           str_contains( $js, "'#naws-purge-btn'" ), true );
check( 'die Tagesberechnung wohnt im Dashboard, nicht hier', str_contains( $js, 'naws-run-daily-btn' ), false );
check( 'der Sichtbarkeits-Schalter hat seinen Handler', str_contains( $js, "'#naws-toggle-secret'" ), true );

echo "\nassets/js/admin.js -- Texte\n" . str_repeat( '-', 74 ) . "\n";

foreach ( [ 'Bitte mindestens', 'Wirklich alle Daten', 'Jetzt bereinigen', 'Einträge gelöscht', 'Jetzt berechnen', 'Request fehlgeschlagen', "text('Show')", "'Show' : 'Hide'", "'🔄 Sync Now'" ] as $wort ) {
    check( "kein fester Text: $wort", str_contains( $js, $wort ), false );
}
check( 'alert() und confirm() bekommen keine Literale', preg_match( '/\b(alert|confirm)\(\s*[\'"`]/', $js ), 0 );

preg_match_all( '/nawsAdmin\.strings\.([a-z_]+)/', $js, $m );
$benutzt = array_unique( $m[1] );
check( 'das Skript benutzt uebersetzte Texte',         count( $benutzt ) > 0, true );
foreach ( $benutzt as $key ) {
    check( "strings.$key wird von PHP geliefert",       (bool) preg_match( "/'" . preg_quote( $key, '/' ) . "'\s*=>/", $php ), true );
}
foreach ( [ 'purge_min_days', 'purge_confirm', 'purge_done', 'show', 'hide' ] as $key ) {
    check( "strings.$key wird im Skript gebraucht",     in_array( $key, $benutzt, true ), true );
}

echo "\nnode --check\n" . str_repeat( '-', 74 ) . "\n";

$node = trim( (string) @shell_exec( 'node --version 2>&1' ) );
if ( preg_match( '/^v\d+/', $node ) ) {
    $out = []; $rc = 1;
    exec( 'node --check ' . escapeshellarg( $PLUGIN . 'assets/js/admin.js' ) . ' 2>&1', $out, $rc );
    check( "Syntax laut node $node", $rc, 0 );
    if ( 0 !== $rc ) { echo '          ' . implode( "\n          ", $out ) . "\n"; }
} else {
    echo "  (kein node im PATH -- Syntaxpruefung uebersprungen)\n";
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
