<?php
/**
 * Extrahiert die Gettext-Aufrufe per PHP-Tokenizer und schreibt
 * languages/xtx-integration-for-netatmo.pot.
 */
define( 'ABSPATH', __DIR__ );
// Vom Ort dieser Datei aus, nicht ueber einen festen Pfad: das Werkzeug
// liegt jetzt im Projekt und wird auch aus einem Arbeitsbaum heraus
// aufgerufen, der anderswo steht.
$root   = str_replace( DIRECTORY_SEPARATOR, '/', dirname( __DIR__, 3 ) ) . '/';
$domain = 'xtx-integration-for-netatmo';

$fns = [
    '__' => [ 'text' => 0, 'domain' => 1 ],
    '_e' => [ 'text' => 0, 'domain' => 1 ],
    'esc_html__' => [ 'text' => 0, 'domain' => 1 ],
    'esc_html_e' => [ 'text' => 0, 'domain' => 1 ],
    'esc_attr__' => [ 'text' => 0, 'domain' => 1 ],
    'esc_attr_e' => [ 'text' => 0, 'domain' => 1 ],
    '_x'  => [ 'text' => 0, 'context' => 1, 'domain' => 2 ],
    '_ex' => [ 'text' => 0, 'context' => 1, 'domain' => 2 ],
    'esc_html_x' => [ 'text' => 0, 'context' => 1, 'domain' => 2 ],
    'esc_attr_x' => [ 'text' => 0, 'context' => 1, 'domain' => 2 ],
    '_n'  => [ 'text' => 0, 'plural' => 1, 'domain' => 3 ],
];

$files = [];
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
    $p = str_replace( '\\', '/', $f->getPathname() );
    if ( substr( $p, -4 ) !== '.php' ) continue;
    // /docs/ steht mit in der Liste: dort liegen die Uebersetzungswerkzeuge
    // selbst, und die werden nicht mit ausgeliefert.
    foreach ( [ '/vendor/', '/tests/', '/node_modules/', '/.git/', '/docs/' ] as $skip ) {
        if ( strpos( $p, $skip ) !== false ) continue 2;
    }
    $files[] = $p;
}
sort( $files );

$entries = []; // key "ctx\4msgid" => ['msgid'=>, 'ctx'=>, 'plural'=>, 'refs'=>[], 'comments'=>[]]

foreach ( $files as $path ) {
    $rel = str_replace( $root, '', $path );
    $tokens = token_get_all( file_get_contents( $path ) );
    $n = count( $tokens );
    for ( $i = 0; $i < $n; $i++ ) {
        $t = $tokens[ $i ];
        if ( ! is_array( $t ) || $t[0] !== T_STRING || ! isset( $fns[ $t[1] ] ) ) continue;
        // Vorheriges bedeutendes Token darf kein -> oder :: sein (Methodenaufruf)
        for ( $b = $i - 1; $b >= 0 && is_array( $tokens[ $b ] ) && in_array( $tokens[ $b ][0], [ T_WHITESPACE ], true ); $b-- );
        if ( $b >= 0 && is_array( $tokens[ $b ] ) && in_array( $tokens[ $b ][0], [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ], true ) ) continue;

        // '(' muss folgen
        for ( $j = $i + 1; $j < $n && is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_WHITESPACE; $j++ );
        if ( $j >= $n || $tokens[ $j ] !== '(' ) continue;

        // Argumente auf Top-Level einsammeln
        $args = []; $cur = null; $depth = 0; $comment = null; $ok = true;
        for ( $k = $j + 1; $k < $n; $k++ ) {
            $tk = $tokens[ $k ];
            if ( $tk === '(' || $tk === '[' ) { $depth++; $cur = null; continue; }
            if ( $tk === ')' ) { if ( $depth === 0 ) break; $depth--; continue; }
            if ( $tk === ']' ) { $depth--; continue; }
            if ( $tk === ',' && $depth === 0 ) { $args[] = $cur; $cur = null; continue; }
            if ( is_array( $tk ) ) {
                if ( $tk[0] === T_WHITESPACE ) continue;
                if ( $tk[0] === T_COMMENT && str_contains( $tk[1], 'translators:' ) ) { $comment = trim( $tk[1] ); continue; }
                if ( $tk[0] === T_CONSTANT_ENCAPSED_STRING && $depth === 0 && $cur === null ) { $cur = $tk[1]; continue; }
            }
            if ( $depth === 0 ) { $cur = false; } // kein reines Literal
        }
        $args[] = $cur;

        $spec = $fns[ $t[1] ];
        $text = $args[ $spec['text'] ] ?? null;
        if ( ! is_string( $text ) ) continue; // dynamisch -> nicht extrahierbar
        $dom  = $args[ $spec['domain'] ] ?? null;
        if ( is_string( $dom ) && trim( $dom, "'\"" ) !== $domain ) continue; // fremde Textdomain
        $ctx  = isset( $spec['context'] ) ? ( $args[ $spec['context'] ] ?? null ) : null;
        $plur = isset( $spec['plural'] )  ? ( $args[ $spec['plural'] ]  ?? null ) : null;

        $unq = function ( $lit ) {
            if ( ! is_string( $lit ) ) return null;
            $q = $lit[0];
            $inner = substr( $lit, 1, -1 );
            return $q === "'"
                ? str_replace( [ "\\\\", "\\'" ], [ "\\", "'" ], $inner )
                : stripcslashes( $inner );
        };

        $msgid = $unq( $text );
        $mctx  = $unq( $ctx );
        $mplur = $unq( $plur );
        if ( $msgid === null || $msgid === '' ) continue;

        $key = ( $mctx ?? '' ) . "\4" . $msgid;
        if ( ! isset( $entries[ $key ] ) ) {
            $entries[ $key ] = [ 'msgid' => $msgid, 'ctx' => $mctx, 'plural' => $mplur, 'refs' => [], 'comments' => [] ];
        }
        $entries[ $key ]['refs'][] = $rel . ':' . $t[2];
        if ( $comment ) { $entries[ $key ]['comments'][ $comment ] = true; }
        if ( $mplur && ! $entries[ $key ]['plural'] ) { $entries[ $key ]['plural'] = $mplur; }
    }
}

ksort( $entries );

function po_quote( string $s ): string {
    return '"' . str_replace( [ "\\", '"', "\n", "\t" ], [ "\\\\", '\"', "\\n", "\\t" ], $s ) . '"';
}

$now = gmdate( 'Y-m-d H:i' ) . '+0000';
$out  = "# Copyright (C) " . gmdate( 'Y' ) . " Frank Neumann\n";
$out .= "# This file is distributed under the same license as the XTX Integration for Netatmo plugin.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"Project-Id-Version: XTX Integration for Netatmo\\n\"\n";
$out .= "\"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/$domain\\n\"\n";
$out .= "\"POT-Creation-Date: $now\\n\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"Plural-Forms: nplurals=2; plural=n != 1;\\n\"\n";
$out .= "\"X-Domain: $domain\\n\"\n";

foreach ( $entries as $e ) {
    $out .= "\n";
    foreach ( array_keys( $e['comments'] ) as $c ) {
        $c = preg_replace( '#^/\*+\s*|\s*\*+/$#', '', $c );
        $out .= "#. " . trim( $c ) . "\n";
    }
    $refs = array_unique( $e['refs'] );
    foreach ( array_chunk( $refs, 4 ) as $chunk ) { $out .= "#: " . implode( ' ', $chunk ) . "\n"; }
    if ( $e['ctx'] !== null ) { $out .= "msgctxt " . po_quote( $e['ctx'] ) . "\n"; }
    $out .= "msgid " . po_quote( $e['msgid'] ) . "\n";
    if ( $e['plural'] ) {
        $out .= "msgid_plural " . po_quote( $e['plural'] ) . "\n";
        $out .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n";
    } else {
        $out .= "msgstr \"\"\n";
    }
}

file_put_contents( $root . "languages/$domain.pot", $out );
printf( "languages/%s.pot: %d Strings aus %d Dateien\n", $domain, count( $entries ), count( $files ) );

// Zur Weiterverarbeitung ablegen
file_put_contents( sys_get_temp_dir() . '/naws-entries.json', json_encode( array_values( array_map( fn( $e ) => [
    'msgid' => $e['msgid'], 'ctx' => $e['ctx'], 'plural' => $e['plural'], 'refs' => array_values( array_unique( $e['refs'] ) ),
    'comments' => array_keys( $e['comments'] ),
], $entries ) ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
