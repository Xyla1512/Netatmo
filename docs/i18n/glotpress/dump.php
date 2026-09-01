<?php
require __DIR__ . '/po.php';

$eintraege = po_lesen( $argv[1] );
$n = 0;
foreach ( $eintraege as $e ) {
    $quelle = '';
    foreach ( $e['kommentare'] as $k ) {
        if ( str_starts_with( $k, '#.' ) ) { $quelle = trim( substr( $k, 2 ) ); }
    }
    if ( str_contains( $quelle, 'changelog' ) ) { continue; }
    $n++;
    printf( "[%d] %s\n%s\n\n", $n, $quelle, $e['msgid'] );
}
fwrite( STDERR, "nicht-changelog: {$n} von " . count( $eintraege ) . "\n" );
