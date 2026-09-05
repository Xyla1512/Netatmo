<?php
/**
 * Minimaler PO -> MO Compiler (msgfmt ist auf diesem Rechner nicht installiert).
 * Aufruf: php make_mo.php <in.po> <out.mo>
 */
$in  = $argv[1] ?? null;
$out = $argv[2] ?? null;
if ( ! $in || ! $out ) { fwrite( STDERR, "Aufruf: php make_mo.php in.po out.mo\n" ); exit( 1 ); }

$lines = file( $in, FILE_IGNORE_NEW_LINES );
$entries = [];
$cur = [ 'ctx' => null, 'id' => null, 'id_plural' => null, 'strs' => [] ];
$field = null;

$unq = function ( string $l ): string {
    $l = trim( $l );
    $i = strpos( $l, '"' );
    $s = substr( $l, $i + 1, strrpos( $l, '"' ) - $i - 1 );
    return stripcslashes( $s );
};

$flush = function () use ( &$cur, &$entries ) {
    if ( $cur['id'] !== null ) {
        $key = $cur['ctx'] !== null ? $cur['ctx'] . "\x04" . $cur['id'] : $cur['id'];
        ksort( $cur['strs'] );
        $translated = false;
        foreach ( $cur['strs'] as $s ) { if ( '' !== $s ) { $translated = true; break; } }
        // Nur uebersetzte Eintraege wandern in die .mo; leere msgstr wuerden
        // den englischen Originaltext durch einen leeren String ersetzen.
        // Bei Plural-Eintraegen (msgstr[0]/msgstr[1]/...) werden alle Formen
        // mit NUL aneinandergehaengt, wie WordPress' MO::translate_plural()
        // es per select_plural_form()-Index aus dem Uebersetzungs-Blob liest.
        if ( $translated || $cur['id'] === '' ) {
            $entries[ $key ] = [ 'str' => implode( "\0", $cur['strs'] ), 'plural' => $cur['id_plural'] ];
        }
    }
    $cur = [ 'ctx' => null, 'id' => null, 'id_plural' => null, 'strs' => [] ];
};

foreach ( $lines as $l ) {
    $t = trim( $l );
    if ( $t === '' ) { $flush(); $field = null; continue; }
    if ( $t[0] === '#' ) { continue; }
    if ( str_starts_with( $t, 'msgctxt ' ) ) { $flush(); $cur['ctx'] = $unq( $t ); $field = 'ctx'; continue; }
    if ( str_starts_with( $t, 'msgid_plural ' ) ) { $cur['id_plural'] = $unq( $t ); $field = 'id_plural'; continue; }
    if ( str_starts_with( $t, 'msgid ' ) )  { if ( $cur['id'] !== null ) $flush(); $cur['id'] = $unq( $t ); $field = 'id'; continue; }
    if ( preg_match( '/^msgstr\[(\d+)\] /', $t, $mm ) ) { $cur['strs'][ (int) $mm[1] ] = $unq( $t ); $field = 'str:' . $mm[1]; continue; }
    if ( str_starts_with( $t, 'msgstr ' ) ) { $cur['strs'][0] = $unq( $t ); $field = 'str:0'; continue; }
    if ( $t[0] === '"' && $field ) {
        if ( 'ctx' === $field ) { $cur['ctx'] .= $unq( $t ); }
        elseif ( 'id' === $field ) { $cur['id'] .= $unq( $t ); }
        elseif ( 'id_plural' === $field ) { $cur['id_plural'] .= $unq( $t ); }
        elseif ( str_starts_with( $field, 'str:' ) ) { $cur['strs'][ (int) substr( $field, 4 ) ] .= $unq( $t ); }
    }
}
$flush();

ksort( $entries );

$n = count( $entries );
$ids = [];
$strs = [];
foreach ( $entries as $key => $entry ) {
    // Original-Blob fuer Plural-Eintraege: "singular\0plural", wie msgfmt es
    // schreibt -- MO::make_entry() erkennt is_plural nur so beim Einlesen.
    $ids[]  = null !== $entry['plural'] ? $key . "\0" . $entry['plural'] : $key;
    $strs[] = $entry['str'];
}

$id_blob = ''; $str_blob = '';
$id_tab = []; $str_tab = [];
foreach ( $ids as $i => $id ) {
    $id_tab[] = [ strlen( $id ), strlen( $id_blob ) ];
    $id_blob .= $id . "\0";
}
foreach ( $strs as $i => $s ) {
    $str_tab[] = [ strlen( $s ), strlen( $str_blob ) ];
    $str_blob .= $s . "\0";
}

$header_size   = 28;
$id_tab_off    = $header_size;
$str_tab_off   = $id_tab_off + $n * 8;
$id_blob_off   = $str_tab_off + $n * 8;
$str_blob_off  = $id_blob_off + strlen( $id_blob );

$mo  = pack( 'V', 0x950412de );   // magic
$mo .= pack( 'V', 0 );            // revision
$mo .= pack( 'V', $n );
$mo .= pack( 'V', $id_tab_off );
$mo .= pack( 'V', $str_tab_off );
$mo .= pack( 'V', 0 );            // hash size
// Die Adresse der Hashtabelle, auch wenn keine geschrieben wird: sie muss
// direkt hinter die zweite Indextabelle zeigen. WordPress rechnet in
// MO::import_from_reader() nach, ob hash_addr minus str_tab_off genau
// total * 8 ergibt, und gibt sonst kommentarlos false zurueck -- die
// ganze Datei wird dann uebergangen, ohne Fehlermeldung irgendwo.
// Hier stand die Dateigroesse, weshalb 1.9.9 zwei .mo ausgeliefert hat,
// die WordPress nie gelesen hat.
$mo .= pack( 'V', $str_tab_off + $n * 8 );

foreach ( $id_tab as [ $len, $off ] )  { $mo .= pack( 'VV', $len, $id_blob_off + $off ); }
foreach ( $str_tab as [ $len, $off ] ) { $mo .= pack( 'VV', $len, $str_blob_off + $off ); }
$mo .= $id_blob . $str_blob;

file_put_contents( $out, $mo );
printf( "%s: %d Eintraege, %d Bytes\n", basename( $out ), $n, strlen( $mo ) );
