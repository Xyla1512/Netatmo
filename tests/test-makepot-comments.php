<?php
/**
 * Tests fuer docs/i18n/catalog/makepot.php: die translators-Kommentare.
 *
 * Der Fehler, der diese Datei rechtfertigt: makepot.php suchte den
 * Kommentar nur INNERHALB der Klammern des Aufrufs. Im Code steht er nach
 * WordPress-Konvention davor -- als Blockkommentar direkt vor dem __()
 * hinter einem return, oder auf der Zeile darueber, wenn der Aufruf in
 * einem sprintf( _n( ... ) ) steckt. 56 Kommentare im Code, keine einzige
 * "#."-Zeile in der .pot; die Uebersetzer sahen nie, wofuer %s steht.
 *
 * Das Skript bekommt hier ein eigenes Wurzelverzeichnis mit einer
 * Fixture-Datei und schreibt seine .pot dorthin -- die .pot des Plugins
 * bleibt unberuehrt.
 *
 *   php tests/test-makepot-comments.php
 *
 * @package NAWS
 */

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$root = rtrim( sys_get_temp_dir(), '/\\' ) . '/naws-makepot-test-' . getmypid();
@mkdir( $root . '/languages', 0777, true );
@mkdir( $root . '/includes', 0777, true );

$fixture = <<<'PHP'
<?php
function labels( $key, $n, $a, $b ) {
    switch ( $key ) {
        case 'from': return /* translators: %s: a date. */ __( 'readings from %s', 'xtx-integration-for-netatmo' );
        case 'plain': return __( 'no comment here', 'xtx-integration-for-netatmo' );
    }
    /* translators: %d: number of days. */
    $x = sprintf( _n( 'last %d day', 'last %d days', $n, 'xtx-integration-for-netatmo' ), $n );
    $y = sprintf( /* translators: 1: first day, 2: last day. */ __( '%1$s to %2$s', 'xtx-integration-for-netatmo' ), $a, $b );
    // translators: %d: a count, line-comment style.
    $w = __( 'line comment %d', 'xtx-integration-for-netatmo' );
    $arr = [
        /* translators: %d: number of days */
        'first'  => __( 'first entry %d', 'xtx-integration-for-netatmo' ),
        'second' => __( 'second entry, no comment', 'xtx-integration-for-netatmo' ),
    ];
    $z = __( 'after the array, no comment', 'xtx-integration-for-netatmo' );
    return [ $x, $y, $w, $arr, $z ];
}
PHP;
file_put_contents( $root . '/includes/fixture.php', $fixture );

$script = dirname( __DIR__ ) . '/docs/i18n/catalog/makepot.php';
exec( 'php ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $root ) . ' 2>&1', $lines, $rc );
$pot = (string) @file_get_contents( $root . '/languages/xtx-integration-for-netatmo.pot' );

echo "\nmakepot.php mit eigenem Wurzelverzeichnis\n" . str_repeat( '-', 74 ) . "\n";
check( 'laeuft durch',                          $rc, 0 );
check( 'schreibt die .pot ins eigene Wurzelverzeichnis', $pot !== '', true );
check( 'findet die Fixture-Strings',            substr_count( $pot, 'msgid "' ), 9 ); // 8 Strings + Kopf

/** Die #.-Zeilen direkt vor einer msgid, in Reihenfolge. */
function comments_before( string $pot, string $msgid ): array {
    $blocks = preg_split( "/\n\n/", $pot );
    foreach ( $blocks as $b ) {
        if ( ! str_contains( $b, "\nmsgid \"$msgid\"" ) && ! str_starts_with( $b, "msgid \"$msgid\"" ) ) continue;
        preg_match_all( '/^#\. (.*)$/m', $b, $m );
        return $m[1];
    }
    return [ 'BLOCK NOT FOUND' ];
}

echo "\nKommentare vor dem Aufruf\n" . str_repeat( '-', 74 ) . "\n";
check( 'direkt vor dem Aufruf (return /* */ __)',   comments_before( $pot, 'readings from %s' ), [ 'translators: %s: a date.' ] );
check( 'auf der Zeile darueber (vor return sprintf( _n( ))', comments_before( $pot, 'last %d day' ), [ 'translators: %d: number of days.' ] );
check( 'in den Argumenten von sprintf',           comments_before( $pot, '%1$s to %2$s' ), [ 'translators: 1: first day, 2: last day.' ] );
check( 'Zeilenkommentar // translators:',         comments_before( $pot, 'line comment %d' ), [ 'translators: %d: a count, line-comment style.' ] );
check( 'im Array vor dem Eintrag',                comments_before( $pot, 'first entry %d' ), [ 'translators: %d: number of days' ] );

echo "\nKein fremder Kommentar\n" . str_repeat( '-', 74 ) . "\n";
check( 'ohne Kommentar bleibt ohne',              comments_before( $pot, 'no comment here' ), [] );
check( 'der naechste Array-Eintrag erbt nichts',  comments_before( $pot, 'second entry, no comment' ), [] );
check( 'nach dem Array erbt nichts',              comments_before( $pot, 'after the array, no comment' ), [] );
check( 'kein Kommentar-Rahmen in der Ausgabe',    (bool) preg_match( '/^#\. .*(\/\*|\*\/|^#\. \/\/)/m', $pot ), false );

// aufraeumen
foreach ( [ '/languages/xtx-integration-for-netatmo.pot', '/includes/fixture.php' ] as $f ) { @unlink( $root . $f ); }
foreach ( [ '/languages', '/includes', '' ] as $d ) { @rmdir( $root . $d ); }

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
