<?php
/**
 * Zeilenweiser .po-Leser — kein Regex auf mehrzeilige Bloecke.
 *
 * Liefert [ ['kommentare'=>[], 'msgid'=>string] ], den Kopfblock (leere
 * msgid) ausgelassen.
 */
function po_lesen( string $pfad ): array {
    $zeilen = file( $pfad, FILE_IGNORE_NEW_LINES );
    $out    = [];
    $komm   = [];
    $id     = null;   // null = kein msgid offen
    $in_id  = false;

    $abschliessen = static function () use ( &$out, &$komm, &$id ) {
        if ( $id !== null && $id !== '' ) {
            $out[] = [ 'kommentare' => $komm, 'msgid' => $id ];
        }
        $komm = [];
        $id   = null;
    };

    foreach ( $zeilen as $z ) {
        if ( str_starts_with( $z, '#' ) ) {
            if ( $id !== null ) { $abschliessen(); }
            $komm[] = trim( $z );
            $in_id  = false;
            continue;
        }
        if ( str_starts_with( $z, 'msgid ' ) ) {
            if ( $id !== null ) { $abschliessen(); }
            $id    = po_string( substr( $z, 6 ) );
            $in_id = true;
            continue;
        }
        if ( str_starts_with( $z, 'msgstr' ) ) {
            $in_id = false;
            continue;
        }
        if ( $in_id && str_starts_with( ltrim( $z ), '"' ) ) {
            $id .= po_string( trim( $z ) );
            continue;
        }
        if ( trim( $z ) === '' ) {
            $abschliessen();
            $in_id = false;
        }
    }
    $abschliessen();

    return $out;
}

/** Entpackt eine .po-Zeichenkette ("…" mit Escapes) zum Rohtext. */
function po_string( string $roh ): string {
    $roh = trim( $roh );
    if ( strlen( $roh ) < 2 || $roh[0] !== '"' ) {
        return '';
    }
    $inhalt = substr( $roh, 1, strrpos( $roh, '"' ) - 1 );

    return stripcslashes( $inhalt );
}

/** Packt Rohtext als .po-Zeichenkette. */
function po_quote( string $text ): string {
    return '"' . str_replace(
        [ '\\', '"', "\n", "\t" ],
        [ '\\\\', '\"', '\n', '\t' ],
        $text
    ) . '"';
}
