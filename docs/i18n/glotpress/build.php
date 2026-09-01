<?php
/**
 * Baut die deutsche .po aus dem GlotPress-Export und de.php.
 *
 * Die msgid wird nie neu getippt, sondern aus dem Export uebernommen —
 * ein Zeichen Unterschied, und GlotPress ordnet den Eintrag beim Import
 * nicht mehr zu. Geprueft wird ausserdem:
 *
 *   - die Zuordnung ueber Stichproben (eine Verschiebung um eine Position
 *     wuerde sonst 106 falsche Uebersetzungen erzeugen, ohne dass etwas
 *     auffaellt)
 *   - dass jede URL, jedes <code>/<strong>/<a> und jeder Shortcode aus dem
 *     Original auch in der Uebersetzung steht
 *   - die 150-Zeichen-Grenze der Kurzbeschreibung
 */
require __DIR__ . '/po.php';

$export = __DIR__ . '/readme-de.po';
$ziel   = __DIR__ . '/xtx-integration-for-netatmo-de_DE-readme.po';
$de     = require __DIR__ . '/de.php';

// ── Nicht-Changelog-Eintraege in derselben Reihenfolge wie dump.php ──────
$alle = po_lesen( $export );
$liste = [];
foreach ( $alle as $e ) {
    $quelle = '';
    foreach ( $e['kommentare'] as $k ) {
        if ( str_starts_with( $k, '#.' ) ) { $quelle = trim( substr( $k, 2 ) ); }
    }
    if ( str_contains( $quelle, 'changelog' ) ) { continue; }
    $e['quelle'] = $quelle;
    $liste[]     = $e;
}

$fehler = [];

if ( count( $liste ) !== 106 ) {
    $fehler[] = 'Erwartet 106 Eintraege, gefunden ' . count( $liste );
}
$fehlend = array_diff( range( 1, count( $liste ) ), array_keys( $de ) );
if ( $fehlend ) {
    $fehler[] = 'Ohne Uebersetzung: ' . implode( ', ', $fehlend );
}

// ── Stichproben gegen eine Verschiebung ─────────────────────────────────
$anker = [
    1   => 'Connects to the Netatmo API',
    17  => 'Which forecast providers',
    32  => '/wp-content/plugins/',
    46  => 'MET Norway',
    64  => 'Authenticate via OAuth2',
    88  => 'Full Netatmo Integration',
    99  => 'Key Features',
    106 => 'Live dashboard with sensor cards',
];
foreach ( $anker as $nr => $erwartet ) {
    $ist = $liste[ $nr - 1 ]['msgid'] ?? '';
    if ( ! str_contains( $ist, $erwartet ) ) {
        $fehler[] = "Zuordnung verschoben bei [{$nr}]: erwartet \"{$erwartet}\", ist \"" . substr( $ist, 0, 60 ) . '"';
    }
}

// ── Auszeichnung und URLs muessen erhalten bleiben ───────────────────────
foreach ( $liste as $i => $e ) {
    $nr  = $i + 1;
    $org = $e['msgid'];
    $ue  = $de[ $nr ] ?? '';
    if ( $ue === '' ) { continue; }

    foreach ( [ '<code>', '</code>', '<strong>', '</strong>', '<a ', '</a>' ] as $tag ) {
        if ( substr_count( $org, $tag ) !== substr_count( $ue, $tag ) ) {
            $fehler[] = "[{$nr}] {$tag} kommt " . substr_count( $org, $tag ) . 'x im Original, ' . substr_count( $ue, $tag ) . 'x in der Uebersetzung vor';
        }
    }
    // Ein echtes Ziel muss buchstabengleich bleiben. Eine Beispieladresse
    // im Fliesstext — "https://yoursite.com/…" — darf eingedeutscht
    // werden, verschwinden darf sie nicht: dafuer wird nur gezaehlt.
    preg_match_all( '#href="([^"]+)"#', $org, $ziele );
    foreach ( array_unique( $ziele[1] ) as $url ) {
        if ( ! str_contains( $ue, $url ) ) {
            $fehler[] = "[{$nr}] Linkziel fehlt in der Uebersetzung: {$url}";
        }
    }
    $org_urls = preg_match_all( '#https?://#', $org );
    $ue_urls  = preg_match_all( '#https?://#', $ue );
    if ( $org_urls !== $ue_urls ) {
        $fehler[] = "[{$nr}] {$org_urls} Adressen im Original, {$ue_urls} in der Uebersetzung";
    }
    preg_match_all( '#\[naws_[a-z_]+\]#', $org, $sc );
    foreach ( array_unique( $sc[0] ) as $code ) {
        if ( ! str_contains( $ue, $code ) ) {
            $fehler[] = "[{$nr}] Shortcode fehlt in der Uebersetzung: {$code}";
        }
    }
}

// ── Kurzbeschreibung: 150 Zeichen ───────────────────────────────────────
$kurz = mb_strlen( $de[1] );
if ( $kurz > 150 ) {
    $fehler[] = "Kurzbeschreibung ist {$kurz} Zeichen lang, erlaubt sind 150";
}

if ( $fehler ) {
    fwrite( STDERR, "FEHLER:\n  " . implode( "\n  ", $fehler ) . "\n" );
    exit( 1 );
}

// ── .po schreiben ───────────────────────────────────────────────────────
$out  = "# Translation of Plugins - XTX Integration for Netatmo - Stable Readme (latest release) in German\n";
$out .= "# This file is distributed under the same license as the Plugins - XTX Integration for Netatmo - Stable Readme (latest release) package.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"Plural-Forms: nplurals=2; plural=n != 1;\\n\"\n";
$out .= "\"Language: de\\n\"\n";
$out .= "\"Project-Id-Version: Plugins - XTX Integration for Netatmo - Stable Readme (latest release)\\n\"\n\n";

$n = 0;
foreach ( $liste as $i => $e ) {
    $nr = $i + 1;
    $ue = $de[ $nr ] ?? '';
    if ( $ue === '' ) { continue; }
    $out .= '#. ' . $e['quelle'] . "\n";
    $out .= 'msgid ' . po_quote( $e['msgid'] ) . "\n";
    $out .= 'msgstr ' . po_quote( $ue ) . "\n\n";
    $n++;
}

file_put_contents( $ziel, $out );
printf(
    "%s\n%d Eintraege, %d Bytes\nKurzbeschreibung: %d von 150 Zeichen\nAlle Pruefungen bestanden.\n",
    basename( $ziel ), $n, filesize( $ziel ), $kurz
);
