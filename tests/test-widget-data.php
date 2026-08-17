<?php
/**
 * Tests for NAWS_Widget_Data::build().
 *
 * Rain and wind gauges are paid add-on modules, so most installations run
 * this code with holes in the input. The degradation is the point.
 *
 *   php tests/test-widget-data.php
 *
 * @package NAWS
 * @since   1.8.0
 */
define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../includes/class-naws-astro.php';
require_once __DIR__ . '/../includes/class-naws-weather-state.php';
require_once __DIR__ . '/../includes/class-naws-widget-data.php';

$passed = 0;
$failed = 0;

function fc( int $n ): array {
    $days = [];
    $codes = [ 63, 3, 2, 0, 71, 95, 45 ];
    for ( $i = 0; $i < $n; $i++ ) {
        $days[] = [
            'date'        => sprintf( '2026-08-%02d', 9 + $i ),
            'weathercode' => $codes[ $i % count( $codes ) ],
            'temp_max'    => 11.0 + $i,
            'temp_min'    => 6.0 + $i,
        ];
    }
    return [ 'days' => $days, 'location_name' => 'Muenster', 'fetched_at' => 1786205132 ];
}

function station( ?string $rain = '0,4', ?string $wind = '12', ?string $temp = '8,4' ): array {
    return [
        'temp' => $temp === null ? null : [ 'value' => $temp, 'unit' => '°C' ],
        'rain' => $rain === null ? null : [ 'value' => $rain, 'unit' => 'mm/h' ],
        'wind' => $wind === null ? null : [ 'value' => $wind, 'unit' => 'km/h' ],
    ];
}

function check( string $name, array $out, array $expect ): void {
    global $passed, $failed;
    $problems = [];
    foreach ( $expect as $path => $want ) {
        $got = $out;
        foreach ( explode( '.', $path ) as $k ) {
            $got = is_array( $got ) && array_key_exists( $k, $got ) ? $got[ $k ] : null;
        }
        if ( $got !== $want ) {
            $problems[] = sprintf( '%s: erwartet %s, ist %s', $path, var_export( $want, true ), var_export( $got, true ) );
        }
    }
    if ( $problems ) {
        $failed++;
        echo "  FAIL  {$name}\n";
        foreach ( $problems as $p ) { echo "          - {$p}\n"; }
        return;
    }
    $passed++;
    echo "  ok    {$name}\n";
}

echo "\nNAWS_Widget_Data::build()\n" . str_repeat( '-', 74 ) . "\n";

echo "\nTageszahl\n";
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 3 ); check( 'drei Tage', [ 'n' => count( $o['days'] ) ], [ 'n' => 3 ] );
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 5 ); check( 'fuenf Tage', [ 'n' => count( $o['days'] ) ], [ 'n' => 5 ] );
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 4 ); check( 'vier wird auf fuenf gezogen', [ 'n' => count( $o['days'] ) ], [ 'n' => 5 ] );
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 1 ); check( 'eins wird auf drei gezogen', [ 'n' => count( $o['days'] ) ], [ 'n' => 3 ] );
$o = NAWS_Widget_Data::build( station(), fc( 2 ), 5 ); check( 'weniger Tage vorhanden als verlangt', [ 'n' => count( $o['days'] ) ], [ 'n' => 2 ] );

echo "\nZustandsnamen der Tage\n";
$o = NAWS_Widget_Data::build( station(), fc( 5 ), 5 );
check( 'Codes werden abgebildet', [
    'a' => $o['days'][0]['state'], 'b' => $o['days'][1]['state'],
    'c' => $o['days'][3]['state'], 'd' => $o['days'][4]['state'],
], [ 'a' => 'rain', 'b' => 'overcast', 'c' => 'clear_day', 'd' => 'snow' ] );

echo "\nKacheln — null ist nicht 0.0\n";
$o = NAWS_Widget_Data::build( station( '0,4', '12' ), fc( 5 ), 5 );
check( 'beide Module da', [ 'n' => count( $o['tiles'] ), 'k0' => $o['tiles'][0]['key'], 'k1' => $o['tiles'][1]['key'] ], [ 'n' => 2, 'k0' => 'rain', 'k1' => 'wind' ] );
$o = NAWS_Widget_Data::build( station( '0,0', '12' ), fc( 5 ), 5 );
check( 'Regen misst 0,0 -> Kachel bleibt', [ 'n' => count( $o['tiles'] ), 'v' => $o['tiles'][0]['value'] ], [ 'n' => 2, 'v' => '0,0' ] );
$o = NAWS_Widget_Data::build( station( null, '12' ), fc( 5 ), 5 );
check( 'kein Regenmesser -> nur Wind', [ 'n' => count( $o['tiles'] ), 'k0' => $o['tiles'][0]['key'] ], [ 'n' => 1, 'k0' => 'wind' ] );
$o = NAWS_Widget_Data::build( station( '0,4', null ), fc( 5 ), 5 );
check( 'kein Windmesser -> nur Regen', [ 'n' => count( $o['tiles'] ), 'k0' => $o['tiles'][0]['key'] ], [ 'n' => 1, 'k0' => 'rain' ] );
$o = NAWS_Widget_Data::build( station( null, null ), fc( 5 ), 5 );
check( 'kein Zusatzmodul -> keine Kacheln', [ 'n' => count( $o['tiles'] ) ], [ 'n' => 0 ] );

echo "\nLeere Faelle\n";
$o = NAWS_Widget_Data::build( station( null, null, null ), [ 'error' => 'API down' ], 5 );
check( 'nichts verfuegbar -> empty', [ 'e' => $o['empty'], 'n' => count( $o['days'] ) ], [ 'e' => true, 'n' => 0 ] );
$o = NAWS_Widget_Data::build( station(), [ 'error' => 'API down' ], 5 );
check( 'Vorhersage kaputt, Station da', [ 'e' => $o['empty'], 'n' => count( $o['days'] ) ], [ 'e' => false, 'n' => 0 ] );
$o = NAWS_Widget_Data::build( station( null, null, '8,4' ), fc( 5 ), 5 );
check( 'nur Temperatur und Vorhersage', [
    'e' => $o['empty'], 't' => $o['temp']['value'], 'n' => count( $o['tiles'] ), 'd' => count( $o['days'] ),
], [ 'e' => false, 't' => '8,4', 'n' => 0, 'd' => 5 ] );

echo "\nWeitere Randfaelle der Vorhersage\n";
$o = NAWS_Widget_Data::build( station(), [ 'days' => [], 'location_name' => 'Muenster', 'fetched_at' => 1786205132 ], 5 );
check( 'Vorhersage vorhanden aber leer', [ 'e' => $o['empty'], 'n' => count( $o['days'] ) ], [ 'e' => false, 'n' => 0 ] );
$o = NAWS_Widget_Data::build( station(), [ 'days' => [ [ 'date' => '2026-08-09', 'weathercode' => 3 ] ], 'location_name' => 'Muenster', 'fetched_at' => 1786205132 ], 5 );
check( 'Tag ohne temp_max/temp_min -> null statt 0.0', [ 'max' => $o['days'][0]['max'], 'min' => $o['days'][0]['min'] ], [ 'max' => null, 'min' => null ] );
$o = NAWS_Widget_Data::build( station(), [ 'days' => [ [ 'date' => '2026-08-09', 'temp_max' => 11.0, 'temp_min' => 6.0 ] ], 'location_name' => 'Muenster', 'fetched_at' => 1786205132 ], 5 );
check( 'Tag ohne weathercode -> kein Zustand', [ 's' => $o['days'][0]['state'] ], [ 's' => '' ] );

echo "\nBreite (normalise_width)\n";
foreach ( [
    [ 'Untergrenze 250 bleibt',        250,     250 ],
    [ 'Obergrenze 500 bleibt',         500,     500 ],
    [ 'Mittelwert bleibt',             380,     380 ],
    [ 'zu schmal -> Untergrenze',      100,     250 ],
    [ 'zu breit -> Obergrenze',        900,     500 ],
    [ 'knapp darunter -> Untergrenze', 249,     250 ],
    [ 'knapp darueber -> Obergrenze',  501,     500 ],
    [ 'null -> Standard',              null,    250 ],
    [ 'leerer String -> Standard',     '',      250 ],
    [ 'Unsinn -> Standard',            'breit', 250 ],
    [ 'null-Wert 0 -> Standard',       0,       250 ],
    [ 'negativ -> Standard',           -300,    250 ],
    [ 'Zahl als String',               '400',   400 ],
    [ 'Kommazahl wird abgeschnitten',  312.7,   312 ],
] as list( $name, $in, $want ) ) {
    check( $name, [ 'w' => NAWS_Widget_Data::normalise_width( $in ) ], [ 'w' => $want ] );
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
