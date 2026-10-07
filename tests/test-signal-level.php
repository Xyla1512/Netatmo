<?php
/**
 * Tests fuer NAWS_Helpers::signal_level(): die Spalte "Signal" auf der
 * Modulseite. Die Basisstation zeigt ihr WLAN (wifi_status), jedes Modul
 * seinen Funkempfang zur Basis (rf_status) — beide als Netatmo-Rohwert,
 * kleiner ist besser. Die Schwellen sind dieselben wie bei den
 * E-Mail-Regeln (86/71 fuer WLAN, 90/80 fuer Funk) plus je eine Stufe
 * darueber (56 bzw. 70) fuer "Sehr gut".
 *
 *   php tests/test-signal-level.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/i18n-stubs.php';
require dirname( __DIR__ ) . '/includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function bars( $r ) { return is_array( $r ) ? $r['bars'] : $r; }

echo "\nWLAN der Basisstation\n" . str_repeat( '-', 74 ) . "\n";
check( 'wifi 40 -> 4 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 40, null ) ), 4 );
check( 'wifi 55 -> 4 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 55, null ) ), 4 );
check( 'wifi 56 -> 3 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 56, null ) ), 3 );
check( 'wifi 70 -> 3 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 70, null ) ), 3 );
check( 'wifi 71 -> 2 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 71, null ) ), 2 );
check( 'wifi 85 -> 2 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 85, null ) ), 2 );
check( 'wifi 86 -> 1 Balken',  bars( NAWS_Helpers::signal_level( 'NAMain', 86, null ) ), 1 );
$w = NAWS_Helpers::signal_level( 'NAMain', 64, 75 );
check( 'Basis nimmt wifi, nicht rf', $w['kind'], 'wifi' );
check( 'Basis: Rohwert ist wifi',    $w['raw'], 64 );
check( 'Basis: Text "Good"',         $w['label'], 'Good' );
check( 'Basis ohne wifi -> null',    NAWS_Helpers::signal_level( 'NAMain', null, 60 ), null );

echo "\nFunk der Module\n" . str_repeat( '-', 74 ) . "\n";
foreach ( [ 'NAModule1', 'NAModule2', 'NAModule3', 'NAModule4' ] as $t ) {
    check( "$t rf 60 -> 4", bars( NAWS_Helpers::signal_level( $t, null, 60 ) ), 4 );
}
check( 'rf 69 -> 4 Balken', bars( NAWS_Helpers::signal_level( 'NAModule1', null, 69 ) ), 4 );
check( 'rf 70 -> 3 Balken', bars( NAWS_Helpers::signal_level( 'NAModule1', null, 70 ) ), 3 );
check( 'rf 79 -> 3 Balken', bars( NAWS_Helpers::signal_level( 'NAModule1', null, 79 ) ), 3 );
check( 'rf 80 -> 2 Balken', bars( NAWS_Helpers::signal_level( 'NAModule1', null, 80 ) ), 2 );
check( 'rf 89 -> 2 Balken', bars( NAWS_Helpers::signal_level( 'NAModule1', null, 89 ) ), 2 );
check( 'rf 90 -> 1 Balken', bars( NAWS_Helpers::signal_level( 'NAModule1', null, 90 ) ), 1 );
$r = NAWS_Helpers::signal_level( 'NAModule2', 50, 95 );
check( 'Modul nimmt rf, nicht wifi', $r['kind'], 'rf' );
check( 'Modul: Text "Weak"',         $r['label'], 'Weak' );
check( 'Modul: Farbe rot',           $r['color'], '#ef4444' );
check( 'Modul ohne rf -> null',      NAWS_Helpers::signal_level( 'NAModule2', 50, null ), null );

echo "\nTexte und Farben je Stufe\n" . str_repeat( '-', 74 ) . "\n";
$want = [ 4 => [ 'Excellent', '#10b981' ], 3 => [ 'Good', '#10b981' ], 2 => [ 'Fair', '#f59e0b' ], 1 => [ 'Weak', '#ef4444' ] ];
foreach ( [ 4 => 60, 3 => 75, 2 => 85, 1 => 92 ] as $b => $raw ) {
    $s = NAWS_Helpers::signal_level( 'NAModule1', null, $raw );
    check( "$b Balken: Text",  $s['label'], $want[ $b ][0] );
    check( "$b Balken: Farbe", $s['color'], $want[ $b ][1] );
}
check( 'Art WLAN heisst "Wi-Fi"', NAWS_Helpers::signal_level( 'NAMain', 60, null )['kind_label'], 'Wi-Fi' );
check( 'Art Funk heisst "Radio"', NAWS_Helpers::signal_level( 'NAModule1', null, 60 )['kind_label'], 'Radio' );

echo "\nRohwerte als Text aus der Datenbank\n" . str_repeat( '-', 74 ) . "\n";
check( '"64" wie 64',   bars( NAWS_Helpers::signal_level( 'NAMain', '64', null ) ), 3 );
check( '"" wie null',   NAWS_Helpers::signal_level( 'NAMain', '', null ), null );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
