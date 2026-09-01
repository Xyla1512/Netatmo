<?php
/**
 * Prueft die erzeugte .po gegen den Original-Export.
 *
 * Der entscheidende Punkt ist die buchstabengleiche msgid: GlotPress ordnet
 * einen Import ueber sie zu. Weicht ein Zeichen ab, landet der Eintrag als
 * neuer, unbekannter String im Nichts — der Import meldet dabei keinen
 * Fehler, er tut nur nichts. Deshalb wird hier jede msgid gegen die Menge
 * aus dem Export geprueft, statt sich auf die Erzeugung zu verlassen.
 */
require __DIR__ . '/po.php';

/** Liest msgid/msgstr-Paare. */
function po_paare( string $pfad ): array {
    $zeilen = file( $pfad, FILE_IGNORE_NEW_LINES );
    $paare  = [];
    $id = null; $str = null; $modus = null;
    $ablegen = static function () use ( &$paare, &$id, &$str ) {
        if ( $id !== null && $id !== '' ) { $paare[ $id ] = (string) $str; }
        $id = null; $str = null;
    };
    foreach ( $zeilen as $z ) {
        if ( str_starts_with( $z, '#' ) )      { $modus = null; continue; }
        if ( str_starts_with( $z, 'msgid ' ) ) { $ablegen(); $id = po_string( substr( $z, 6 ) ); $modus = 'id'; continue; }
        if ( str_starts_with( $z, 'msgstr ' ) ){ $str = po_string( substr( $z, 7 ) ); $modus = 'str'; continue; }
        if ( trim( $z ) === '' )               { $ablegen(); $modus = null; continue; }
        if ( str_starts_with( ltrim( $z ), '"' ) ) {
            if ( $modus === 'id' )  { $id  .= po_string( $z ); }
            if ( $modus === 'str' ) { $str .= po_string( $z ); }
        }
    }
    $ablegen();
    return $paare;
}

$export  = po_paare( __DIR__ . '/readme-de.po' );
$fertig  = po_paare( __DIR__ . '/xtx-integration-for-netatmo-de_DE-readme.po' );

// Absichtlich unveraendert: Eigennamen, Versionsnummern, Hostnamen.
$absicht = [
    'XTX Integration for Netatmo',
    'chartjs-adapter-date-fns 3.0.0',
    'Chart.js 4.5.1',
    'Yr.no / MET Norway API (api.met.no)',
    'Open-Meteo Geocoding API (geocoding-api.open-meteo.com)',
    'Open-Meteo API (api.open-meteo.com)',
    'Netatmo API (api.netatmo.com)',
    'Shortcodes',
];

$fehler = [];
$gleich = [];
foreach ( $fertig as $id => $str ) {
    if ( ! array_key_exists( $id, $export ) ) {
        $fehler[] = 'msgid steht nicht im Export: "' . substr( $id, 0, 70 ) . '"';
    }
    if ( trim( $str ) === '' ) {
        $fehler[] = 'leere Uebersetzung: "' . substr( $id, 0, 70 ) . '"';
    }
    if ( $str === $id && ! in_array( $id, $absicht, true ) ) {
        $gleich[] = substr( $id, 0, 70 );
    }
}

printf( "Export: %d Strings, erzeugte Datei: %d Strings\n", count( $export ), count( $fertig ) );
printf( "unveraendert uebernommen (Eigennamen): %d\n", count( array_intersect( array_keys( $fertig ), $absicht ) ) );

if ( $gleich ) {
    echo "\nIdentisch mit dem Original, aber nicht als Eigenname gefuehrt:\n  " . implode( "\n  ", $gleich ) . "\n";
}
if ( $fehler ) {
    fwrite( STDERR, "\nFEHLER:\n  " . implode( "\n  ", $fehler ) . "\n" );
    exit( 1 );
}
echo "\nJede msgid ist buchstabengleich im Export vorhanden, keine Uebersetzung leer.\n";
