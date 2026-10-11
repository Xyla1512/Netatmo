<?php
/**
 * Der 29. Februar in den Tagesdiagrammen von [naws_history].
 *
 * Der Fehler (Nutzermeldung Oktober 2026, mit Bild): Im Jahr 2024 zogen
 * sich durch "Temperatur Min / Max" zwei fast waagerechte Linien von Anfang
 * Maerz bis zum Jahresende. Die x-Achse der Tagesdiagramme kannte 365 feste
 * Beschriftungen ohne "02-29". Der Server liefert x als "MM-DD"
 * (class-naws-ajax.php, get_history_data), in Schaltjahren also auch
 * "02-29". Eine unbekannte Beschriftung haengt Chart.js an das Ende der
 * Kategorie-Achse: Der Punkt landete hinter dem 31.12., die Linie lief vom
 * 28.02. nach ganz rechts und von dort zurueck zum 01.03.
 *
 * Seit 2.1.2 hat die Achse 366 Tage. Jahre ohne Schalttag haben dort keinen
 * Punkt, die Linie geht vom 28.02. direkt zum 01.03.
 *
 * Der Test zieht dayLabels() aus history-boot.js und laesst sie in node
 * laufen; ohne node wird er uebersprungen.
 *
 *   php tests/test-history-leap-day.php
 *
 * @package NAWS
 */
$PLUGIN = dirname( __DIR__ ) . '/';
$js     = (string) file_get_contents( $PLUGIN . 'assets/js/history-boot.js' );

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nhistory-boot.js: Tagesachse und 29. Februar\n" . str_repeat( '-', 74 ) . "\n";

$has_fn = (bool) preg_match( '/function dayLabels\(\)\{.*?\n\}/s', $js, $m );
check( 'dayLabels() steht als eigene Funktion in history-boot.js', $has_fn, true );
check( 'die Tagesachse nimmt dayLabels()', (bool) preg_match( '/labels:\s*dayLabels\(\)/', $js ), true );

$node = trim( (string) shell_exec( ( DIRECTORY_SEPARATOR === '\\' ? 'where node 2>NUL' : 'command -v node 2>/dev/null' ) ) );
if ( $has_fn && $node !== '' ) {
    $script = tempnam( sys_get_temp_dir(), 'naws' ) . '.js';
    file_put_contents( $script, $m[0] . "\nprocess.stdout.write(JSON.stringify(dayLabels()));\n" );
    $labels = json_decode( (string) shell_exec( 'node ' . escapeshellarg( $script ) ), true );
    unlink( $script );

    check( '366 Tage', is_array( $labels ) ? count( $labels ) : null, 366 );
    check( 'keine Beschriftung doppelt', is_array( $labels ) ? count( array_unique( $labels ) ) : null, 366 );

    // Jedes Datum, das der Server fuer 2024 liefern kann, steht auf der
    // Achse, und zwar in Kalenderreihenfolge.
    $server = [];
    for ( $t = strtotime( '2024-01-01 12:00 UTC' ); gmdate( 'Y', $t ) === '2024'; $t += 86400 ) {
        $server[] = substr( gmdate( 'Y-m-d', $t ), 5 );
    }
    check( 'Achse = alle Tage eines Schaltjahres in Reihenfolge', $labels, $server );
    $i = is_array( $labels ) ? array_search( '02-29', $labels, true ) : false;
    check( '02-29 steht zwischen 02-28 und 03-01',
        $i !== false && $labels[ $i - 1 ] === '02-28' && $labels[ $i + 1 ] === '03-01', true );
    check( 'letzte Beschriftung 12-31', is_array( $labels ) ? end( $labels ) : null, '12-31' );
} elseif ( $has_fn ) {
    echo "  --    node nicht gefunden, Laufzeitpruefung uebersprungen\n";
}

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
