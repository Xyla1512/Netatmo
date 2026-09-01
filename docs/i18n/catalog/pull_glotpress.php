<?php
/**
 * Holt Uebersetzungen von translate.wordpress.org in unsere .po zurueck.
 *
 * Seit 1.9.9 uebersetzen auch andere. Was dort entsteht, landet ueber die
 * Sprachpakete zwar bei den Nutzern, aber nicht in der mitgelieferten .mo —
 * und die ist es, die eine Installation liest, bis ihr Paket da ist. Ohne
 * diesen Schritt faellt die Bruecke also immer weiter hinter das zurueck,
 * was laengst uebersetzt ist.
 *
 * Aufruf:
 *   curl -o nb.po 'https://translate.wordpress.org/projects/wp-plugins/xtx-integration-for-netatmo/stable/nb/default/export-translations/?format=po'
 *   php docs/i18n/catalog/pull_glotpress.php nb_NO nb.po
 *
 * Gefuellt werden nur **leere** msgstr. Wo beide etwas stehen haben und es
 * sich unterscheidet, wird der Unterschied nur gemeldet: welche Fassung die
 * richtige ist, entscheidet ein Mensch, und eine stille Uebernahme waere
 * genau der Weg, auf dem schon einmal 199 norwegische Saetze in den
 * deutschen Katalog geraten sind.
 *
 * Danach make_mo.php laufen lassen.
 */

$locale = $argv[1] ?? null;
$export = $argv[2] ?? null;
if ( ! $locale || ! $export ) {
    fwrite( STDERR, "Aufruf: php pull_glotpress.php <de_DE|nb_NO> <export.po>\n" );
    exit( 1 );
}

$ziel = __DIR__ . "/xtx-integration-for-netatmo-$locale.po";
foreach ( [ $ziel, $export ] as $f ) {
    if ( ! is_file( $f ) ) {
        fwrite( STDERR, "fehlt: $f\n" );
        exit( 1 );
    }
}

/** Liest eine .po zu [ "ctx\4msgid" => msgstr ]. */
function po_paare( string $pfad ): array {
    $zeilen = preg_split( '/\r\n|\n/', (string) file_get_contents( $pfad ) );
    $paare  = [];
    $ctx = null; $id = null; $str = null; $feld = null;

    $unq = static function ( string $l ): string {
        $i = strpos( $l, '"' );
        if ( false === $i ) { return ''; }
        return stripcslashes( substr( $l, $i + 1, strrpos( $l, '"' ) - $i - 1 ) );
    };
    $ablegen = static function () use ( &$paare, &$ctx, &$id, &$str ) {
        if ( null !== $id && '' !== $id ) {
            $paare[ ( null !== $ctx ? $ctx . "\x04" : '' ) . $id ] = (string) $str;
        }
        $ctx = null; $id = null; $str = null;
    };

    foreach ( $zeilen as $z ) {
        $t = trim( $z );
        if ( '' === $t ) { $ablegen(); $feld = null; continue; }
        if ( '#' === $t[0] ) { continue; }
        if ( str_starts_with( $t, 'msgctxt ' ) )      { $ctx = $unq( $t ); $feld = 'c'; continue; }
        if ( str_starts_with( $t, 'msgid_plural ' ) ) { $feld = null; continue; }
        if ( str_starts_with( $t, 'msgid ' ) )        { $id  = $unq( $t ); $feld = 'i'; continue; }
        if ( str_starts_with( $t, 'msgstr' ) )        { $str = $unq( $t ); $feld = 's'; continue; }
        if ( '"' === $t[0] ) {
            $s = $unq( $t );
            if ( 'c' === $feld ) { $ctx .= $s; }
            if ( 'i' === $feld ) { $id  .= $s; }
            if ( 's' === $feld ) { $str .= $s; }
        }
    }
    $ablegen();

    return $paare;
}

$oben = po_paare( $export );

// Zeilenweise fuellen, damit Reihenfolge, Fundstellen und Kommentare der
// Zieldatei unangetastet bleiben.
$zeilen = preg_split( '/\r\n|\n/', (string) file_get_contents( $ziel ) );
$crlf   = str_contains( (string) file_get_contents( $ziel ), "\r\n" );

$ctx = null; $id = null;
$gefuellt = []; $strittig = []; $unbekannt = 0;
foreach ( $zeilen as $i => $z ) {
    $t = trim( $z );
    if ( str_starts_with( $t, 'msgctxt "' ) ) { $ctx = substr( $t, 9, -1 ); continue; }
    if ( str_starts_with( $t, 'msgid "' ) )   { $id  = substr( $t, 7, -1 ); continue; }
    if ( str_starts_with( $t, 'msgstr "' ) && null !== $id && '' !== $id ) {
        $key  = ( null !== $ctx ? $ctx . "\x04" : '' ) . $id;
        $hier = substr( $t, 8, -1 );
        if ( ! isset( $oben[ $key ] ) ) {
            if ( '' === $hier ) { $unbekannt++; }
        } elseif ( '' === $hier && '' !== $oben[ $key ] ) {
            $zeilen[ $i ] = 'msgstr "' . str_replace( [ chr( 92 ), chr( 34 ) ], [ chr( 92 ) . chr( 92 ), chr( 92 ) . chr( 34 ) ], $oben[ $key ] ) . '"';
            $gefuellt[]   = $id;
        } elseif ( '' !== $hier && '' !== $oben[ $key ] && $hier !== $oben[ $key ] ) {
            $strittig[ $id ] = [ $hier, $oben[ $key ] ];
        }
        $ctx = null; $id = null;
    }
    if ( '' === $t ) { $ctx = null; $id = null; }
}

file_put_contents( $ziel, implode( $crlf ? "\r\n" : "\n", $zeilen ) );

printf( "%s: %d Uebersetzungen uebernommen, %d strittig, %d hier offen und oben unbekannt\n",
    basename( $ziel ), count( $gefuellt ), count( $strittig ), $unbekannt );
foreach ( $gefuellt as $g )            { echo '  neu:      ' . mb_substr( $g, 0, 60 ) . "\n"; }
foreach ( $strittig as $k => $v )      { printf( "  STRITTIG: %s\n      hier: %s\n      oben: %s\n", mb_substr( $k, 0, 50 ), mb_substr( $v[0], 0, 60 ), mb_substr( $v[1], 0, 60 ) ); }
