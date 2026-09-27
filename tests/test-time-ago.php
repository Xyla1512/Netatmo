<?php
/**
 * Tests fuer die relativen Zeitangaben: human_time_diff() liefert die Dauer
 * in der Sprache der Seite ("11 Minuten"), das "ago" dahinter und das "in"
 * davor waren fest auf Englisch angehaengt — auf einer deutschen Seite stand
 * "11 Minuten ago". Beides geht jetzt ueber gettext, mit Platzhalter, weil
 * die Stellung des Wortes je Sprache wechselt ("vor 11 Minuten").
 *
 *   php tests/test-time-ago.php
 *
 * @package NAWS
 */
$root = dirname( __DIR__ ) . '/';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$files = [
    'templates/current.php'     => [ 'ago' => 1, 'in' => 0 ],
    'admin/views/modules.php'   => [ 'ago' => 1, 'in' => 0 ],
    'admin/views/dashboard.php' => [ 'ago' => 0, 'in' => 2 ],
];
foreach ( $files as $f => $want ) {
    $src = (string) file_get_contents( $root . $f );
    echo "\n$f\n" . str_repeat( '-', 74 ) . "\n";
    check( 'kein festes " ago" mehr',  (bool) preg_match( "/' ago\\)?'/", $src ), false );
    check( 'kein festes "in " mehr',   (bool) preg_match( "/'( — )?in ' \\. human_time_diff/", $src ), false );
    check( "{$want['ago']}x \"%s ago\" ueber gettext", substr_count( $src, "__( '%s ago', 'xtx-integration-for-netatmo' )" ), $want['ago'] );
    check( "{$want['in']}x \"in %s\" ueber gettext",   substr_count( $src, "__( 'in %s', 'xtx-integration-for-netatmo' )" ), $want['in'] );
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
