<?php
/**
 * Baut die deutsche .po fuer das Code-Projekt (6 Strings).
 *
 * Diese sechs entscheiden ueber das Sprachpaket: WordPress.org erzeugt es,
 * sobald 90 % der Code-Strings uebersetzt und freigegeben sind — das Readme
 * zaehlt dafuer nicht mit. Bei sechs Strings heisst 90 % : alle sechs.
 *
 * Name, Autor und die beiden Adressen bleiben stehen. Ein Plugin-Name wird
 * nicht uebersetzt, und eine URL zeigt sonst ins Leere; sie werden trotzdem
 * eingetragen, weil GlotPress sie sonst als unuebersetzt zaehlt und die
 * Schwelle nie erreicht wird.
 */
require __DIR__ . '/po.php';

$uebersetzung = [
    // Kerns eigener String aus wp-config-sample.php. NAWS_Crypto schlaegt ihn
    // zur Laufzeit in der Domain 'default' nach, also wird dieser Eintrag hier
    // nie benutzt — er steht nur im Projekt, weil der Extraktor die Datei
    // liest. Uebernommen ist deshalb genau die Fassung, die WordPress selbst
    // ausliefert (auf einer de_DE-Installation nachgesehen).
    'put your unique phrase here' => 'füge hier deine einmalig genutzte Zeichenfolge ein',

    'https://frank-neumann.de'    => 'https://frank-neumann.de',
    'Frank Neumann'               => 'Frank Neumann',
    'XTX Integration for Netatmo' => 'XTX Integration for Netatmo',
    'https://www.frank-neumann.de/netatmo-wetter-plugin/' => 'https://www.frank-neumann.de/netatmo-wetter-plugin/',

    'Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, charts, history and forecasts via shortcodes.'
        => 'Verbindet sich mit der Netatmo-API, speichert alle Sensordaten lokal und zeigt per Shortcode Live-Dashboards, Diagramme, Verlauf und Vorhersagen.',
];

$export = __DIR__ . '/code-de.po';
$ziel   = __DIR__ . '/xtx-integration-for-netatmo-de_DE.po';

$eintraege = po_lesen( $export );
$fehler    = [];

foreach ( $eintraege as $e ) {
    if ( ! array_key_exists( $e['msgid'], $uebersetzung ) ) {
        $fehler[] = 'ohne Uebersetzung: "' . $e['msgid'] . '"';
    }
}
foreach ( array_keys( $uebersetzung ) as $id ) {
    if ( ! in_array( $id, array_column( $eintraege, 'msgid' ), true ) ) {
        $fehler[] = 'steht nicht im Export: "' . $id . '"';
    }
}
foreach ( $uebersetzung as $id => $str ) {
    if ( str_starts_with( $id, 'http' ) && $id !== $str ) {
        $fehler[] = 'Adresse veraendert: ' . $id;
    }
    if ( mb_strlen( $str ) > 150 && ! str_starts_with( $id, 'http' ) ) {
        $fehler[] = 'zu lang (' . mb_strlen( $str ) . ' Zeichen): "' . substr( $str, 0, 40 ) . '"';
    }
}

if ( $fehler ) {
    fwrite( STDERR, "FEHLER:\n  " . implode( "\n  ", $fehler ) . "\n" );
    exit( 1 );
}

$out  = "# Translation of Plugins - XTX Integration for Netatmo - Stable (latest release) in German\n";
$out .= "# This file is distributed under the same license as the Plugins - XTX Integration for Netatmo - Stable (latest release) package.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"Plural-Forms: nplurals=2; plural=n != 1;\\n\"\n";
$out .= "\"Language: de\\n\"\n";
$out .= "\"Project-Id-Version: Plugins - XTX Integration for Netatmo - Stable (latest release)\\n\"\n\n";

foreach ( $eintraege as $e ) {
    foreach ( $e['kommentare'] as $k ) {
        if ( str_starts_with( $k, '#.' ) || str_starts_with( $k, '#:' ) ) { $out .= $k . "\n"; }
    }
    $out .= 'msgid ' . po_quote( $e['msgid'] ) . "\n";
    $out .= 'msgstr ' . po_quote( $uebersetzung[ $e['msgid'] ] ) . "\n\n";
}

file_put_contents( $ziel, $out );
printf(
    "%s\n%d Eintraege, %d Bytes\nBeschreibung: %d Zeichen\nAlle Pruefungen bestanden.\n",
    basename( $ziel ),
    count( $eintraege ),
    filesize( $ziel ),
    mb_strlen( $uebersetzung['Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, charts, history and forecasts via shortcodes.'] )
);
