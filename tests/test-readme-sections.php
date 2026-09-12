<?php
/**
 * Strukturtest fuer readme.txt: die Abschnitte, die WordPress.org verlangt
 * und die kein Scanner prueft.
 *
 * Am 12.09.2026 hat das Schnitt-Skript fuer 2.0.0 beim Kuerzen der Upgrade
 * Notices alles bis zum Dateiende mitgenommen: "Privacy & External Services"
 * mit den vier Hosts und "Third-Party Libraries" fehlten im veroeffentlichten
 * Paket, und keine Pruefung hat es bemerkt. Diese Datei haelt die Liste der
 * Pflichtabschnitte, der offengelegten Hosts und die Grenzen der beiden
 * Fenster (Changelog, Upgrade Notice) fest.
 *
 *   php tests/test-readme-sections.php
 *
 * @package NAWS
 */
$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
$main   = file_get_contents( dirname( __DIR__ ) . '/xtx-integration-for-netatmo.php' );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nreadme.txt -- Pflichtabschnitte und Fenster\n" . str_repeat( '-', 74 ) . "\n";
preg_match_all( '/^== (.+) ==$/m', $readme, $m );
check( 'Abschnitte in dieser Reihenfolge', $m[1], [ 'Description', 'Installation', 'Frequently Asked Questions', 'Screenshots', 'Changelog', 'Upgrade Notice', 'Privacy & External Services', 'Third-Party Libraries' ] );

foreach ( [ 'api.netatmo.com', 'api.open-meteo.com', 'geocoding-api.open-meteo.com', 'api.met.no' ] as $host ) {
    check( "External Services nennt $host als eigenen Eintrag", preg_match( '/^= [^=\n]*' . preg_quote( $host, '/' ) . '[^=\n]* =$/m', $readme ) === 1, true );
}
check( 'Third-Party Libraries nennt Chart.js', str_contains( $readme, '= Chart.js ' ), true );

preg_match( '/^Stable tag: (\S+)/m', $readme, $stable );
preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $version );
check( 'Stable tag = Version im Plugin-Header', $stable[1] ?? null, $version[1] ?? '?' );

$cl_start = strpos( $readme, '== Changelog ==' );
$un_start = strpos( $readme, '== Upgrade Notice ==' );
$pr_start = strpos( $readme, '== Privacy & External Services ==' );
preg_match_all( '/^= (\d+\.\d+\.\d+) =$/m', substr( $readme, $cl_start, $un_start - $cl_start ), $cl );
check( 'Changelog-Fenster: hoechstens 5 Versionen, neueste = Stable tag', [ count( $cl[1] ) <= 5, $cl[1][0] ?? null ], [ true, $stable[1] ?? null ] );

preg_match_all( '/^= (\d+\.\d+\.\d+) =\n(.+)$/m', substr( $readme, $un_start, $pr_start - $un_start ), $un );
$too_long = array_values( array_filter( $un[2], fn( $t ) => mb_strlen( trim( $t ) ) > 300 ) );
check( 'Upgrade Notices: hoechstens 3, neueste = Stable tag, keine ueber 300 Zeichen', [ count( $un[1] ) <= 3, $un[1][0] ?? null, count( $too_long ) ], [ true, $stable[1] ?? null, 0 ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
