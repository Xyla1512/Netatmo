<?php
/**
 * Traegt Uebersetzungen in eine .po ein, wo msgstr noch leer ist.
 *
 * Aufruf: php docs/i18n/catalog/fill_po.php <de_DE|nb_NO> <liste.php>
 *
 * Die Liste ist eine PHP-Datei, die ein Array zurueckgibt:
 *   'msgid'            => 'msgstr'
 *   "ctx\x04msgid"     => 'msgstr'                 (Eintrag mit msgctxt)
 *   'singular msgid'   => [ 'singular', 'plural' ] (Eintrag mit msgid_plural)
 * Ein Schluessel, den die .po nicht kennt, wird am Ende gemeldet — meist
 * ein Tippfehler gegenueber dem Quelltext. Ein msgstr, das schon gefuellt
 * ist, bleibt unangetastet.
 *
 * Reihenfolge: makepot.php, merge_po.php <lang>, fill_po.php <lang> <liste>,
 * make_mo.php — siehe docs/i18n/README.md.
 */

$lang = $argv[1] ?? null;
$list = $argv[2] ?? null;
if ( ! $lang || ! $list || ! is_file( $list ) ) {
    fwrite( STDERR, "Aufruf: php fill_po.php <de_DE|nb_NO> <liste.php>\n" );
    exit( 1 );
}
$po = __DIR__ . "/xtx-integration-for-netatmo-$lang.po";
if ( ! is_file( $po ) ) {
    fwrite( STDERR, "fehlt: $po\n" );
    exit( 1 );
}
$map = require $list;

$unq = static function ( string $l ): string {
    $i = strpos( $l, '"' );
    return $i === false ? '' : stripcslashes( substr( $l, $i + 1, strrpos( $l, '"' ) - $i - 1 ) );
};
$q = static fn( string $s ): string => '"' . addcslashes( $s, "\\\"\n" ) . '"';

$text    = file_get_contents( $po );
$blocks  = preg_split( "/\n\n/", $text );
$filled  = 0;
$missing = array_fill_keys( array_keys( $map ), true );

foreach ( $blocks as $bi => $block ) {
    $lines  = explode( "\n", $block );
    $ctx    = null; $id = null; $plural = null;
    foreach ( $lines as $l ) {
        if ( str_starts_with( $l, 'msgctxt ' ) )      { $ctx    = $unq( $l ); }
        elseif ( str_starts_with( $l, 'msgid_plural ' ) ) { $plural = $unq( $l ); }
        elseif ( str_starts_with( $l, 'msgid ' ) )    { $id     = $unq( $l ); }
    }
    if ( $id === null || $id === '' ) { continue; }
    $key = $ctx !== null ? $ctx . "\x04" . $id : $id;
    if ( ! array_key_exists( $key, $map ) ) { continue; }
    unset( $missing[ $key ] );
    $tr = $map[ $key ];
    foreach ( $lines as $n => $l ) {
        if ( $plural === null && $l === 'msgstr ""' && is_string( $tr ) ) {
            $lines[ $n ] = 'msgstr ' . $q( $tr ); $filled++;
        } elseif ( $plural !== null && $l === 'msgstr[0] ""' && is_array( $tr ) ) {
            $lines[ $n ] = 'msgstr[0] ' . $q( $tr[0] ); $filled++;
        } elseif ( $plural !== null && $l === 'msgstr[1] ""' && is_array( $tr ) ) {
            $lines[ $n ] = 'msgstr[1] ' . $q( $tr[1] ); $filled++;
        }
    }
    $blocks[ $bi ] = implode( "\n", $lines );
}

file_put_contents( $po, implode( "\n\n", $blocks ) );
printf( "%s: %d msgstr eingetragen\n", $lang, $filled );
foreach ( array_keys( $missing ) as $k ) {
    fwrite( STDERR, "nicht in der .po: " . str_replace( "\x04", ' | ', $k ) . "\n" );
}
exit( $missing ? 2 : 0 );
