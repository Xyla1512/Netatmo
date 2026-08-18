<?php
/**
 * Guards the [naws_calc] catalogue against drift.
 *
 * The catalogue is a plain metadata array, so it can be checked without a
 * WordPress bootstrap. What this guards:
 *
 *   – every entry declares kind, decimals and a label key
 *   – every label key exists in ALL THREE language files
 *
 * The second point is the reason this file exists. With this many entries,
 * "forgot to translate it into Norwegian" is not a risk, it is a certainty.
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
check( 'Stufe 1 liefert 14 Eintraege', count( $catalogue ), 14 );

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

echo "\nSprachschluessel in allen drei Dateien\n" . str_repeat( '-', 74 ) . "\n";

$sens_keys = [
    'sens_very_cold', 'sens_cold', 'sens_cool', 'sens_pleasantly_cool',
    'sens_pleasant', 'sens_warm', 'sens_hot', 'sens_extremely_hot',
];

foreach ( [ 'de', 'en', 'no' ] as $lang ) {
    $strings = include __DIR__ . '/../languages/' . $lang . '.php';
    check( "$lang.php liefert ein Array", is_array( $strings ), true );

    foreach ( $catalogue as $key => $entry ) {
        check( "$lang: {$entry['label']}", isset( $strings[ $entry['label'] ] ) && $strings[ $entry['label'] ] !== '', true );
    }
    foreach ( $sens_keys as $sk ) {
        check( "$lang: $sk", isset( $strings[ $sk ] ) && $strings[ $sk ] !== '', true );
    }
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
