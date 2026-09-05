<?php
/**
 * Tests fuer NAWS_Records — die Rekorde aus der Tagesuebersicht.
 *
 * Die Rechnung ist rein: Tageszeilen rein, Zahlen raus. Deshalb laeuft sie
 * hier auf einem handgebauten Jahr, in dem jeder Rekord an einem bekannten
 * Tag steht. Was nicht rein ist (die Zeilen holen, Einheiten lesen), ist
 * absichtlich duenn und wird auf dev geprueft, nicht hier.
 *
 *   php tests/test-records.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function close( string $name, $got, float $want, float $tol = 0.001 ): void {
    global $passed, $failed;
    if ( is_float( $got ) && abs( $got - $want ) <= $tol ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nDie Helfer, die die Rekorde mitbenutzen\n" . str_repeat( '-', 74 ) . "\n";

check( 'station_row_id() ist oeffentlich', ( new ReflectionMethod( 'NAWS_Calc', 'station_row_id' ) )->isPublic(), true );
check( 'period_range() ist oeffentlich',   ( new ReflectionMethod( 'NAWS_Calc', 'period_range' ) )->isPublic(), true );

// get_daily_summaries() braucht $wpdb und laeuft hier nicht; die Freigabe
// von gust_max steht in einer Liste, die sich lesen laesst.
$db_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'get_daily_summaries() gibt gust_max heraus', (bool) preg_match( "/\\\$allowed_fields\\s*=\\s*\\[[^\\]]*'gust_max'/", $db_src ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
