<?php
/**
 * Guards the [naws_calc] catalogue against drift.
 *
 * The catalogue is a plain metadata array, so it can be checked without a
 * WordPress bootstrap. What this guards:
 *
 *   – every entry declares kind, decimals and a label key
 *   – every label key is known to naws_label()
 *
 * The second point is the reason this file exists. The translations themselves
 * live on translate.wordpress.org now, but the lookup table is still code: a
 * label key missing from it makes the interface fall back to an empty string
 * without saying anything.
 *
 *   php tests/test-calc-catalogue.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-calc.php';

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

$catalogue = NAWS_Calc::catalogue();
$kinds     = [ 'instant', 'dayclass', 'sum', 'index' ];

echo "\nKatalog-Struktur\n" . str_repeat( '-', 74 ) . "\n";

check( 'Katalog ist nicht leer', count( $catalogue ) > 0, true );

// Ein absoluter Gesamtwert veraltet bei jedem Ausbau der Katalog-Stufen —
// genau das ist hier passiert: eine Stufe-1-Pruefung hielt 14 fest, waehrend
// Stufe 2 sieben Tagesklassen hinzufuegte. Zaehlung je Art sagt etwas
// Sinnvolles aus und macht sichtbar, welche Aufgabe die Zahlen pflegen muss.
$by_kind = array_count_values( array_column( $catalogue, 'kind' ) );
check( 'Katalog: 14 Momentanwerte', $by_kind['instant']  ?? 0, 14 );
check( 'Katalog: 7 Tagesklassen',   $by_kind['dayclass'] ?? 0, 7  );
check( 'Katalog: 5 Summen', $by_kind['sum'] ?? 0, 5 );
check( 'Katalog: 1 Index',  $by_kind['index'] ?? 0, 1 );

foreach ( $catalogue as $key => $entry ) {
    check( "$key hat eine gueltige Art",   in_array( $entry['kind'] ?? '', $kinds, true ), true );
    check( "$key hat Nachkommastellen",    is_int( $entry['decimals'] ?? null ),          true );
    check( "$key hat einen Sprachkey",     ! empty( $entry['label'] ),                    true );
    check( "$key: param ist String/null",  ( ! isset( $entry['param'] ) || $entry['param'] === null || is_string( $entry['param'] ) ), true );
    check( "has('$key') ist wahr",         NAWS_Calc::has( $key ),                        true );
    check( "$key: unit fehlt oder ist String", ( ! isset( $entry['unit'] ) || is_string( $entry['unit'] ) ), true );
    check( "$key: unit nur ohne param",        ( ! isset( $entry['unit'] ) || ! isset( $entry['param'] ) || $entry['param'] === null ), true );
}

check( 'has() weist Unbekanntes ab', NAWS_Calc::has( 'gibt_es_nicht' ), false );

echo "\nJeder Label-Schluessel ist naws_label() bekannt\n" . str_repeat( '-', 74 ) . "\n";

$sens_keys = [
    'sens_very_cold', 'sens_cold', 'sens_cool', 'sens_pleasantly_cool',
    'sens_pleasant', 'sens_warm', 'sens_hot', 'sens_extremely_hot',
];

require_once __DIR__ . '/i18n-stubs.php';

foreach ( $catalogue as $key => $entry ) {
    check( "naws_label( {$entry['label']} )", naws_label( $entry['label'] ) !== '', true );
}
foreach ( $sens_keys as $sk ) {
    check( "naws_label( $sk )", naws_label( $sk ) !== '', true );
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
