<?php
/**
 * Erzeugt de.php aus dem GlotPress-Export readme-de.po.
 *
 * Seit 03.09.2026 ist der Export die Quelle, nicht mehr die von Hand
 * nummerierte de.php. Grund: GlotPress haelt die Reihenfolge der Originale
 * nicht stabil. Nach dem Readme-Nachtrag von 1.9.8 wanderte [naws_table]
 * von Position 6 auf 3 und schob drei Eintraege nach hinten; die Stichproben
 * in build.php (1, 17, 32, ...) sahen davon nichts, weil sie diesen Bereich
 * nicht abdecken, und build.php scheiterte erst an den <code>-Zaehlungen.
 * Dieses Skript leitet die Nummern bei jedem Lauf neu aus dem Export ab und
 * uebernimmt die dort stehende Uebersetzung.
 *
 * Aufruf:  php pull_de.php          liest readme-de.po, schreibt de.php
 * Danach:  php build.php && php verify.php
 *
 * Neue oder geaenderte Saetze: erst in GlotPress uebersetzen (direkt oder
 * per Import-Datei wie imports/2026-09-03-import-readme-de_DE.po), dann den
 * Export als readme-de.po ablegen, dann dieses Skript. Nicht andersherum.
 */
require __DIR__ . '/po.php';

/** Wie po_lesen(), nur dass auch msgstr mitkommt. */
function po_lesen_mit_str( string $pfad ): array {
    $zeilen = file( $pfad, FILE_IGNORE_NEW_LINES );
    $out    = [];
    $komm   = [];
    $id     = null;
    $str    = null;
    $offen  = '';

    $abschliessen = static function () use ( &$out, &$komm, &$id, &$str, &$offen ) {
        if ( $id !== null && $id !== '' ) {
            $out[] = [ 'kommentare' => $komm, 'msgid' => $id, 'msgstr' => (string) $str ];
        }
        $komm  = [];
        $id    = null;
        $str   = null;
        $offen = '';
    };

    foreach ( $zeilen as $z ) {
        if ( str_starts_with( $z, '#' ) ) {
            if ( $id !== null ) { $abschliessen(); }
            $komm[] = trim( $z );
            continue;
        }
        if ( str_starts_with( $z, 'msgid ' ) ) {
            if ( $id !== null ) { $abschliessen(); }
            $id    = po_string( substr( $z, 6 ) );
            $offen = 'id';
            continue;
        }
        if ( str_starts_with( $z, 'msgstr' ) ) {
            $str   = po_string( substr( $z, strpos( $z, '"' ) ) );
            $offen = 'str';
            continue;
        }
        if ( str_starts_with( ltrim( $z ), '"' ) && $offen !== '' ) {
            if ( $offen === 'id' ) { $id .= po_string( trim( $z ) ); } else { $str .= po_string( trim( $z ) ); }
            continue;
        }
        if ( trim( $z ) === '' ) {
            $abschliessen();
        }
    }
    $abschliessen();

    return $out;
}

$export    = __DIR__ . '/readme-de.po';
$eintraege = po_lesen_mit_str( $export );
$n         = 0;
$gruppen   = [];
$leer      = [];

foreach ( $eintraege as $e ) {
    $quelle = '';
    foreach ( $e['kommentare'] as $k ) {
        if ( str_starts_with( $k, '#.' ) ) { $quelle = trim( substr( $k, 2 ) ); }
    }
    if ( str_contains( $quelle, 'changelog' ) ) { continue; }
    $n++;
    if ( $e['msgstr'] === '' ) { $leer[] = $n; }
    $gruppen[ $quelle ][] = [ $n, $e['msgstr'] ];
}

$kopf  = "<?php\n";
$kopf .= "/**\n";
$kopf .= " * Deutsche Uebersetzung der Plugin-Seite (Stable Readme), ohne Changelog.\n";
$kopf .= " *\n";
$kopf .= " * ERZEUGT von pull_de.php aus readme-de.po (GlotPress-Export). Nicht von\n";
$kopf .= " * Hand nummerieren: Schluessel ist die laufende Nummer der Nicht-Changelog-\n";
$kopf .= " * Eintraege im Export, so wie dump.php sie zaehlt und build.php sie liest.\n";
$kopf .= " * Aendert sich die Reihenfolge im Export, aendern sich die Nummern hier.\n";
$kopf .= " *\n";
$kopf .= " * Locale de_DE (Standard) = Duzen. Die formelle Variante ist ein eigenes\n";
$kopf .= " * Locale (de_DE_formal) und ein eigener Uebersetzungssatz.\n";
$kopf .= " *\n";
$kopf .= " * Die Beispieladresse bleibt \"yoursite.com\": GlotPress prueft, ob die Links\n";
$kopf .= " * in Original und Uebersetzung uebereinstimmen, und warnt sonst.\n";
$kopf .= " *\n";
$kopf .= " * Stand: " . gmdate( 'Y-m-d' ) . " — {$n} Eintraege" . ( $leer ? ', ohne Uebersetzung: ' . implode( ', ', $leer ) : ', alle uebersetzt' ) . ".\n";
$kopf .= " */\n";
$kopf .= "return [\n";

$body = '';
foreach ( $gruppen as $quelle => $liste ) {
    $titel = rtrim( $quelle, '.' );
    $body .= "\n// ── " . $titel . ' ' . str_repeat( '─', max( 3, 70 - mb_strlen( $titel ) ) ) . "\n";
    foreach ( $liste as [ $nr, $str ] ) {
        $body .= $nr . " => '" . addcslashes( $str, "'\\" ) . "',\n";
    }
}

file_put_contents( __DIR__ . '/de.php', $kopf . $body . "\n];\n" );
echo "de.php: {$n} Eintraege in " . count( $gruppen ) . " Gruppen" . ( $leer ? ', ohne Uebersetzung: ' . implode( ', ', $leer ) : ', alle uebersetzt' ) . "\n";
