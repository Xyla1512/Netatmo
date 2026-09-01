<?php
/**
 * Traegt den Stand der .pot in eine Uebersetzung nach (was msgmerge tut,
 * das hier nicht installiert ist).
 *
 * Aufruf: php docs/i18n/catalog/merge_po.php <de_DE|nb_NO>
 *
 * Massgeblich ist die .pot: sie bestimmt Reihenfolge, Fundstellen und
 * Uebersetzerkommentare. Aus der bestehenden .po kommen die msgstr, ueber
 * msgctxt und msgid zugeordnet — buchstabengleich, denn genau daran haengt
 * ein gettext-Katalog. Was in der .pot fehlt, faellt weg; was neu ist,
 * kommt mit leerem msgstr dazu und wird am Ende benannt.
 *
 * Ein leeres msgstr ist kein Mangel: make_mo.php laesst solche Eintraege
 * aus, und gettext antwortet dann mit dem englischen Original.
 */

$lang = $argv[1] ?? null;
if ( ! $lang ) {
    fwrite( STDERR, "Aufruf: php merge_po.php <de_DE|nb_NO>\n" );
    exit( 1 );
}

$root = str_replace( DIRECTORY_SEPARATOR, '/', dirname( __DIR__, 3 ) ) . '/';
$pot  = $root . 'languages/xtx-integration-for-netatmo.pot';
$po   = __DIR__ . "/xtx-integration-for-netatmo-$lang.po";
foreach ( [ $pot, $po ] as $f ) {
    if ( ! is_file( $f ) ) { fwrite( STDERR, "fehlt: $f\n" ); exit( 1 ); }
}

/**
 * Liest eine .po/.pot zu [ key => ['ctx','id','plural','str','refs','comments'] ].
 * Der Schluessel ist ctx\4msgid, wie ihn auch gettext bildet.
 */
function po_read( string $pfad ): array {
    $zeilen = file( $pfad, FILE_IGNORE_NEW_LINES );
    $out    = [];
    $cur    = [ 'ctx' => null, 'id' => null, 'plural' => null, 'str' => '', 'strs' => [], 'refs' => [], 'comments' => [] ];
    $feld   = null;

    $unq = static function ( string $l ): string {
        $i = strpos( $l, '"' );
        if ( $i === false ) { return ''; }
        return stripcslashes( substr( $l, $i + 1, strrpos( $l, '"' ) - $i - 1 ) );
    };
    $flush = static function () use ( &$cur, &$out ) {
        if ( $cur['id'] !== null && $cur['id'] !== '' ) {
            $key = $cur['ctx'] !== null ? $cur['ctx'] . "\x04" . $cur['id'] : $cur['id'];
            $out[ $key ] = $cur;
        }
        $cur = [ 'ctx' => null, 'id' => null, 'plural' => null, 'str' => '', 'strs' => [], 'refs' => [], 'comments' => [] ];
    };

    foreach ( $zeilen as $z ) {
        $t = trim( $z );
        if ( $t === '' ) { $flush(); $feld = null; continue; }
        if ( str_starts_with( $t, '#:' ) ) { foreach ( preg_split( '/\s+/', trim( substr( $t, 2 ) ) ) as $r ) { if ( $r !== '' ) { $cur['refs'][] = $r; } } continue; }
        if ( str_starts_with( $t, '#.' ) ) { $cur['comments'][] = trim( substr( $t, 2 ) ); continue; }
        if ( str_starts_with( $t, '#' ) ) { continue; }
        if ( str_starts_with( $t, 'msgctxt ' ) )       { $cur['ctx']    = $unq( $t ); $feld = 'ctx';    continue; }
        if ( str_starts_with( $t, 'msgid_plural ' ) )  { $cur['plural'] = $unq( $t ); $feld = 'plural'; continue; }
        if ( str_starts_with( $t, 'msgid ' ) )         { $cur['id']     = $unq( $t ); $feld = 'id';     continue; }
        if ( str_starts_with( $t, 'msgstr[0] ' ) )     { $cur['strs'][0] = $unq( $t ); $feld = 'str0';  continue; }
        if ( str_starts_with( $t, 'msgstr[1] ' ) )     { $cur['strs'][1] = $unq( $t ); $feld = 'str1';  continue; }
        if ( str_starts_with( $t, 'msgstr ' ) )        { $cur['str']    = $unq( $t ); $feld = 'str';    continue; }
        if ( $t[0] === '"' && $feld !== null ) {
            $stueck = $unq( $t );
            if ( $feld === 'ctx' )    { $cur['ctx']    .= $stueck; }
            if ( $feld === 'plural' ) { $cur['plural'] .= $stueck; }
            if ( $feld === 'id' )     { $cur['id']     .= $stueck; }
            if ( $feld === 'str' )    { $cur['str']    .= $stueck; }
            if ( $feld === 'str0' )   { $cur['strs'][0] = ( $cur['strs'][0] ?? '' ) . $stueck; }
            if ( $feld === 'str1' )   { $cur['strs'][1] = ( $cur['strs'][1] ?? '' ) . $stueck; }
        }
    }
    $flush();

    return $out;
}

function po_quote( string $s ): string {
    return '"' . str_replace( [ "\\", '"', "\n", "\t" ], [ "\\\\", '\"', "\n", "\t" ], $s ) . '"';
}

$neu  = po_read( $pot );
$alt  = po_read( $po );

// Den Kopfblock der Uebersetzung unveraendert uebernehmen: Sprache und
// Pluralregel stehen dort, und die kommen nicht aus der .pot.
$kopf = [];
foreach ( file( $po, FILE_IGNORE_NEW_LINES ) as $z ) {
    $kopf[] = $z;
    if ( trim( $z ) === '' && count( $kopf ) > 3 ) { break; }
}
$out = rtrim( implode( "\n", $kopf ), "\n" ) . "\n";

$uebernommen = 0;
$offen       = [];
foreach ( $neu as $key => $e ) {
    $out .= "\n";
    foreach ( $e['comments'] as $c ) { $out .= "#. $c\n"; }
    foreach ( array_chunk( array_values( array_unique( $e['refs'] ) ), 4 ) as $chunk ) {
        $out .= '#: ' . implode( ' ', $chunk ) . "\n";
    }
    if ( $e['ctx'] !== null ) { $out .= 'msgctxt ' . po_quote( $e['ctx'] ) . "\n"; }
    $out .= 'msgid ' . po_quote( $e['id'] ) . "\n";

    if ( $e['plural'] !== null ) {
        $out .= 'msgid_plural ' . po_quote( $e['plural'] ) . "\n";
        $s0 = $alt[ $key ]['strs'][0] ?? '';
        $s1 = $alt[ $key ]['strs'][1] ?? '';
        $out .= 'msgstr[0] ' . po_quote( $s0 ) . "\n";
        $out .= 'msgstr[1] ' . po_quote( $s1 ) . "\n";
        if ( $s0 === '' ) { $offen[] = $e['id']; } else { $uebernommen++; }
        continue;
    }

    $str  = $alt[ $key ]['str'] ?? '';
    $out .= 'msgstr ' . po_quote( $str ) . "\n";
    if ( $str === '' ) { $offen[] = $e['id']; } else { $uebernommen++; }
}

file_put_contents( $po, $out );

$weggefallen = array_diff( array_keys( $alt ), array_keys( $neu ) );
printf(
    "%s: %d Eintraege, %d uebersetzt, %d offen, %d weggefallen\n",
    basename( $po ), count( $neu ), $uebernommen, count( $offen ), count( $weggefallen )
);
foreach ( $offen as $o ) { echo "  offen: $o\n"; }
foreach ( $weggefallen as $w ) { echo '  weg:   ' . str_replace( "\x04", ' | ', $w ) . "\n"; }
