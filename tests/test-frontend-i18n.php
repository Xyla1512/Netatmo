<?php
/**
 * Tests that the frontend script carries no display text of its own.
 *
 * Three sentences sat hard-wired in German inside assets/js/frontend.js —
 * the chart render failure, the empty period, the failed request. They were
 * the only strings in the plugin that no translation could reach: a visitor
 * running WordPress in English or Norwegian read them in German, and no
 * setting changed that. Migrating the interface to gettext in 1.9.9 did not
 * move them either, because they were never in the PHP.
 *
 * Why a test and not just a fix: nothing else would have caught it. The
 * catalogue can be complete, every PHP call site can be correct, and a
 * literal in a JavaScript file still slips past all of it. This test looks
 * at the shipped script the way a translator would — anything that reaches
 * the page as words has to come from the payload, not from the file.
 *
 * The check is deliberately about the mechanism rather than about these
 * three sentences. A fourth one added tomorrow fails here too.
 *
 *   php tests/test-frontend-i18n.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/**
 * Liest eine .mo zu [ "ctx\4msgid" => msgstr ].
 *
 * Geprueft wird die ausgelieferte Datei, nicht die .po daneben: die .po ist
 * Entwicklungsmaterial und wird gar nicht mitgeliefert (docs/ steht in
 * .distignore). Was auf einer Installation ankommt, ist die .mo.
 */
function mo_lesen( string $pfad ): array {
    $bin = (string) file_get_contents( $pfad );
    $magie = unpack( 'V', substr( $bin, 0, 4 ) )[1];
    $f = 0x950412de === $magie ? 'V' : 'N';   // little oder big endian
    $anzahl  = unpack( $f, substr( $bin,  8, 4 ) )[1];
    $off_id  = unpack( $f, substr( $bin, 12, 4 ) )[1];
    $off_str = unpack( $f, substr( $bin, 16, 4 ) )[1];

    $out = [];
    for ( $i = 0; $i < $anzahl; $i++ ) {
        $l_id = unpack( $f, substr( $bin, $off_id + $i * 8, 4 ) )[1];
        $p_id = unpack( $f, substr( $bin, $off_id + $i * 8 + 4, 4 ) )[1];
        $l_st = unpack( $f, substr( $bin, $off_str + $i * 8, 4 ) )[1];
        $p_st = unpack( $f, substr( $bin, $off_str + $i * 8 + 4, 4 ) )[1];
        $out[ substr( $bin, $p_id, $l_id ) ] = substr( $bin, $p_st, $l_st );
    }

    return $out;
}

$wurzel = dirname( __DIR__ );
$js     = (string) file_get_contents( $wurzel . '/assets/js/frontend.js' );

echo "\nfrontend.js traegt keinen eigenen Anzeigetext\n" . str_repeat( '-', 74 ) . "\n";

// Deutsche Umlaute und das Eszett in einer Zeichenkette sind der sicherste
// einzelne Hinweis auf einen vergessenen Satz: Bezeichner, Auswahlausdruecke
// und Datenschluessel in dieser Datei kommen ohne sie aus.
preg_match_all( '/([\'"])((?:(?!\1).)*?[äöüßÄÖÜ](?:(?!\1).)*?)\1/u', $js, $treffer );
check( 'keine deutschen Umlaute in Zeichenketten', $treffer[2], [] );

// Und die drei namentlich, damit die Meldung beim Fehlschlag sagt, worum es
// geht, statt nur "irgendwo ein Umlaut".
foreach ( [
    'Chart konnte nicht gerendert werden.',
    'Keine Daten für diesen Zeitraum.',
    'Daten konnten nicht geladen werden',
] as $satz ) {
    check( 'nicht mehr fest verdrahtet: ' . mb_substr( $satz, 0, 28 ), str_contains( $js, $satz ), false );
}

echo "\nDie Texte kommen aus der Nutzlast\n" . str_repeat( '-', 74 ) . "\n";

// nawsFrontend.i18n ist der Weg. Die Datei muss ihn benutzen, sonst ist der
// Text zwar verschwunden, aber durch nichts ersetzt.
check( 'frontend.js liest nawsFrontend.i18n', (bool) preg_match( '/nawsFrontend\.i18n\./', $js ), true );

// Der Notnagel oben in der Datei faengt den Fall ab, dass die Nutzlast
// ausbleibt — das eingefuegte Skript fehlt, oder ein zwischengespeichertes
// stammt noch aus der Zeit vor diesen Schluesseln. Ohne Ersatzwerte stuende
// dort "undefined" statt eines Satzes.
//
// Geprueft wird, DASS es fuer jeden der drei Schluessel einen Ersatz gibt,
// nicht wie er hinterlegt ist: ob als Objektliteral oder Schluessel fuer
// Schluessel aufgefuellt, ist eine Frage der Bauweise und keine Zusicherung.
$notnagel = substr( $js, 0, (int) strpos( $js, 'nawsChartFontSize' ) );
check( 'der Notnagel fasst nawsFrontend.i18n an', str_contains( $notnagel, 'nawsFrontend.i18n' ), true );
foreach ( [ 'js_chart_failed', 'js_no_data_period', 'js_load_failed' ] as $k ) {
    check( "der Notnagel kennt $k", str_contains( $notnagel, $k ), true );
}

echo "\nDas PHP reicht sie uebersetzt weiter\n" . str_repeat( '-', 74 ) . "\n";

$saetze = [
    'js_chart_failed'   => 'Chart could not be rendered.',
    'js_no_data_period' => 'No data for this period.',
    'js_load_failed'    => 'Could not load data (HTTP %s)',
];

$sc = (string) file_get_contents( $wurzel . '/includes/class-naws-shortcodes.php' );
foreach ( $saetze as $k => $text ) {
    check( "Nutzlast enthaelt $k", str_contains( $sc, "'" . $k . "'" ), true );
    // Mit Textdomain, sonst schlaegt der Aufruf im Katalog von WordPress
    // selbst nach und der Extraktor traegt ihn gar nicht erst ein.
    check( "$k geht durch __() mit Textdomain",
        str_contains( $sc, "__( '" . $text . "', 'xtx-integration-for-netatmo' )" ), true );
}

echo "\nUnd der Katalog kennt sie\n" . str_repeat( '-', 74 ) . "\n";

$pot = (string) file_get_contents( $wurzel . '/languages/xtx-integration-for-netatmo.pot' );
foreach ( $saetze as $k => $text ) {
    check( "die .pot fuehrt $k", str_contains( $pot, 'msgid "' . $text . '"' ), true );
}

// Die mitgelieferten Sprachen sind die Bruecke, bis die Sprachpakete von
// wordpress.org gefuellt sind. Faellt eine der drei dort aus, liest ein
// deutscher Besucher wieder Englisch — ohne dass irgendetwas auffaellt.
foreach ( [ 'de_DE', 'nb_NO' ] as $locale ) {
    $mo = mo_lesen( $wurzel . '/languages/xtx-integration-for-netatmo-' . $locale . '.mo' );
    foreach ( $saetze as $k => $text ) {
        $u = $mo[ $text ] ?? '';
        check( "$locale uebersetzt $k", $u !== '' && $u !== $text, true );
    }
    // Der HTTP-Status wird eingesetzt, nicht angehaengt: wer den Satz
    // uebersetzt, muss den Platzhalter behalten, sonst verschwindet die Zahl.
    check( "$locale behaelt den %s-Platzhalter",
        str_contains( $mo[ $saetze['js_load_failed'] ] ?? '', '%s' ), true );
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
