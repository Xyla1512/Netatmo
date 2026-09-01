<?php
/**
 * Prueft die mitgelieferten .mo so, wie WordPress sie liest.
 *
 * 1.9.9 hat zwei .mo ausgeliefert, die WordPress **nie gelesen hat**.
 * MO::import_from_reader() rechnet den Kopf nach und gibt bei der kleinsten
 * Unstimmigkeit `false` zurueck — ohne Meldung, ohne Log, ohne sichtbaren
 * Unterschied ausser dem, dass alles englisch bleibt. Der Fehler war die
 * Adresse der Hashtabelle: dort stand die Dateigroesse statt der Stelle
 * direkt hinter der zweiten Indextabelle.
 *
 * Aufgefallen ist es nur, weil auf der Testinstallation ein Sprachpaket von
 * wordpress.org lag, das die Luecke verdeckte. Ohne Paket — und fuer
 * Norwegisch gibt es keins — bekommt der Leser englischen Text.
 *
 * Deshalb pruefen die Faelle unten den Kopf **mit derselben Rechnung wie
 * WordPress**, nicht mit einem eigenen, nachsichtigeren Leser.
 *
 *   php tests/test-mo-files.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/**
 * Der Kopf einer .mo, gelesen und geprueft wie in wp-includes/pomo/mo.php.
 *
 * @return array{ok:bool,grund:string,total:int}
 */
function mo_kopf( string $pfad ): array {
    $bin = (string) file_get_contents( $pfad );
    if ( strlen( $bin ) < 28 ) {
        return [ 'ok' => false, 'grund' => 'kuerzer als ein Kopf', 'total' => 0 ];
    }

    $magie = unpack( 'V', substr( $bin, 0, 4 ) )[1];
    if ( 0x950412de !== $magie && 0xde120495 !== $magie ) {
        return [ 'ok' => false, 'grund' => 'Magie stimmt nicht', 'total' => 0 ];
    }
    $f = 0x950412de === $magie ? 'V' : 'N';

    $h = unpack( "{$f}rev/{$f}total/{$f}orig/{$f}trans/{$f}hlen/{$f}haddr", substr( $bin, 4, 24 ) );
    if ( 0 !== $h['rev'] ) {
        return [ 'ok' => false, 'grund' => 'Revision ist nicht 0', 'total' => $h['total'] ];
    }
    // Genau diese beiden Rechnungen macht WordPress, und genau die zweite
    // ist 1.9.9 durchgefallen.
    if ( $h['trans'] - $h['orig'] !== $h['total'] * 8 ) {
        return [ 'ok' => false, 'grund' => 'Indextabelle der Originale hat die falsche Laenge', 'total' => $h['total'] ];
    }
    if ( $h['haddr'] - $h['trans'] !== $h['total'] * 8 ) {
        return [ 'ok' => false, 'grund' => 'hash_addr zeigt nicht hinter die zweite Indextabelle', 'total' => $h['total'] ];
    }
    if ( $h['haddr'] + $h['hlen'] * 4 > strlen( $bin ) ) {
        return [ 'ok' => false, 'grund' => 'Zeichenketten beginnen hinter dem Dateiende', 'total' => $h['total'] ];
    }

    return [ 'ok' => true, 'grund' => '', 'total' => $h['total'] ];
}

/** Liest eine .mo zu [ "ctx\4msgid" => msgstr ]. */
function mo_lesen( string $pfad ): array {
    $bin = (string) file_get_contents( $pfad );
    $f   = 0x950412de === unpack( 'V', substr( $bin, 0, 4 ) )[1] ? 'V' : 'N';
    $n   = unpack( $f, substr( $bin,  8, 4 ) )[1];
    $oi  = unpack( $f, substr( $bin, 12, 4 ) )[1];
    $si  = unpack( $f, substr( $bin, 16, 4 ) )[1];

    $out = [];
    for ( $i = 0; $i < $n; $i++ ) {
        $lo = unpack( $f, substr( $bin, $oi + $i * 8, 4 ) )[1];
        $po = unpack( $f, substr( $bin, $oi + $i * 8 + 4, 4 ) )[1];
        $ls = unpack( $f, substr( $bin, $si + $i * 8, 4 ) )[1];
        $ps = unpack( $f, substr( $bin, $si + $i * 8 + 4, 4 ) )[1];
        $out[ substr( $bin, $po, $lo ) ] = substr( $bin, $ps, $ls );
    }

    return $out;
}

$wurzel   = dirname( __DIR__ );
$sprachen = [ 'de_DE', 'nb_NO' ];

echo "\nWordPress kann die mitgelieferten Kataloge lesen\n" . str_repeat( '-', 74 ) . "\n";

foreach ( $sprachen as $locale ) {
    $pfad = $wurzel . '/languages/xtx-integration-for-netatmo-' . $locale . '.mo';
    check( "$locale ist vorhanden", is_file( $pfad ), true );
    $kopf = mo_kopf( $pfad );
    check( "$locale hat einen Kopf, den WordPress annimmt" . ( $kopf['ok'] ? '' : " ({$kopf['grund']})" ), $kopf['ok'], true );
    // Ein Katalog mit einer Handvoll Eintraege waere ein Baufehler, kein Katalog.
    check( "$locale traegt mehr als 500 Eintraege", $kopf['total'] > 500, true );
}

echo "\nUnd es steht auch etwas drin\n" . str_repeat( '-', 74 ) . "\n";

$de = mo_lesen( $wurzel . '/languages/xtx-integration-for-netatmo-de_DE.mo' );
check( 'de_DE: der Kopfeintrag ist dabei', isset( $de[''] ), true );
check( 'de_DE: Pressure ist uebersetzt', $de['Pressure'] ?? '', 'Luftdruck' );
check( 'de_DE: keine leere Uebersetzung', count( array_filter( $de, fn( $t, $k ) => '' === $t && '' !== $k, ARRAY_FILTER_USE_BOTH ) ), 0 );

// Deutsch und Norwegisch sind zweimal durcheinandergeraten: einmal beim
// Import nach translate.wordpress.org, wo 199 norwegische Saetze im
// deutschen Projekt landeten. Der Ring auf dem a und der Strich durch das o
// kommen im Deutschen nicht vor — ausser im Ø, das hier als Mittelwert-
// Zeichen in zwei Spaltenkoepfen steht.
$nordisch = [];
foreach ( $de as $k => $t ) {
    if ( '' === $t ) {
        continue;
    }
    if ( preg_match( '/[\x{00E5}\x{00F8}\x{00C5}]/u', $t ) ) {
        $nordisch[] = $k;
    }
}
check( 'de_DE: keine norwegischen Sonderzeichen', $nordisch, [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
