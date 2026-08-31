<?php
/**
 * Tests for NAWS_Helpers::indoor_chart_defs().
 *
 * An NAModule4 keeps its readings in indoor_temp_avg and
 * indoor_humidity_avg — the temp_* columns on its rows carry nothing,
 * because those belong to the station's outdoor values. So every indoor
 * module needs its own pair of yearly-comparison charts, and two places
 * need the same pair: the history template that draws them and the
 * settings screen that switches them on and off. This is the one list
 * both read.
 *
 * The id is the part that must not drift: it is what the
 * naws_history_hidden_charts option stores. A chart somebody switched off
 * must stay switched off across an update, which is why the humidity id
 * still reads exactly as it did when humidity was the only indoor chart.
 *
 *   php tests/test-indoor-charts.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_modules']  = [];
$GLOBALS['naws_test_settings'] = [];

// ── Minimal WordPress surface ────────────────────────────────────────────
function get_option( $key, $default = false ) {
    return $key === 'naws_settings' ? $GLOBALS['naws_test_settings'] : $default;
}
require_once __DIR__ . '/i18n-stubs.php';
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function __( $s, $d = null ) { return $s; }

class NAWS_Database {
    public static function get_modules( $active_only = false ): array {
        return $GLOBALS['naws_test_modules'];
    }
}

require_once __DIR__ . '/../includes/class-naws-helpers.php';

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

/** Setzt die Modulliste fuer den naechsten Aufruf. */
function modules( array $spec ): void {
    $GLOBALS['naws_test_modules'] = array_map( static function ( $m ) {
        return [ 'module_id' => $m[0], 'module_type' => $m[1], 'module_name' => $m[2] ];
    }, $spec );
}

echo "\nNAWS_Helpers::indoor_chart_defs()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Ohne Innenmodul gibt es nichts zu zeichnen ───────────────────────────
modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis' ],
    [ '02:00:00:a9:85:54', 'NAModule1', 'Aussen' ],
    [ '05:00:00:0a:a6:46', 'NAModule3', 'Regenmesser' ],
] );
check( 'ohne NAModule4 ist die Liste leer', NAWS_Helpers::indoor_chart_defs(), [] );

// ── Ein Innenmodul ergibt zwei Kacheln ───────────────────────────────────
modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis' ],
    [ '03:00:00:0e:21:72', 'NAModule4', 'Sleeping' ],
] );
$defs = NAWS_Helpers::indoor_chart_defs();

check( 'ein Innenmodul ergibt zwei Kacheln', count( $defs ), 2 );
check( 'die Temperatur kommt zuerst', $defs[0]['field'], 'indoor_temp_avg' );
check( 'die Feuchte danach',          $defs[1]['field'], 'indoor_humidity_avg' );

// Der Schluessel, an dem die Sichtbarkeit haengt.
check( 'Temperatur-Kennung', $defs[0]['id'], 'indoor_temp_sleeping' );
check( 'Feuchte-Kennung unveraendert gegenueber frueher', $defs[1]['id'], 'indoor_humidity_sleeping' );

check( 'beide zeigen auf dasselbe Modul',
    [ $defs[0]['module_id'], $defs[1]['module_id'] ],
    [ '03:00:00:0e:21:72', '03:00:00:0e:21:72' ] );
check( 'die Beschriftung nennt Modul und Groesse', $defs[0]['label'], 'Sleeping – Temperature' );
check( 'ebenso bei der Feuchte',                   $defs[1]['label'], 'Sleeping – Humidity' );

// Die Einheit kommt aus den Einstellungen, nicht aus einem festen String —
// genau der Fehler, den die alte Fassung hatte: sie schrieb '%' fuer jede
// Innenkachel, was fuer eine Temperatur falsch waere.
check( 'Grad Celsius als Vorgabe', $defs[0]['unit'], '°C' );
check( 'Prozent bei der Feuchte',  $defs[1]['unit'], '%' );

$GLOBALS['naws_test_settings'] = [ 'temperature_unit' => 'F' ];
$fahrenheit = NAWS_Helpers::indoor_chart_defs();
check( 'die Fahrenheit-Einstellung schlaegt durch', $fahrenheit[0]['unit'], '°F' );
check( 'die Feuchte bleibt Prozent',                $fahrenheit[1]['unit'], '%' );
$GLOBALS['naws_test_settings'] = [];

// Der Parameter steuert serverseitig die Umrechnung in NAWS_Ajax.
check( 'Temperatur-Parameter', $defs[0]['param'], 'Temperature' );
check( 'Feuchte-Parameter',    $defs[1]['param'], 'Humidity' );

// ── Die Kennung entsteht aus dem Modulnamen ──────────────────────────────
modules( [ [ '03:00:00:0d:aa:ca', 'NAModule4', 'Gäste-Zimmer 2' ] ] );
$defs = NAWS_Helpers::indoor_chart_defs();
check( 'Umlaute, Bindestrich und Leerzeichen fallen aus der Kennung',
    $defs[0]['id'], 'indoor_temp_gstezimmer2' );
check( 'der Name selbst bleibt in der Beschriftung erhalten',
    $defs[0]['label'], 'Gäste-Zimmer 2 – Temperature' );

modules( [ [ '03:00:00:0d:aa:ca', 'NAModule4', 'Schlafzimmer im Dachgeschoss' ] ] );
$defs = NAWS_Helpers::indoor_chart_defs();
check( 'lange Namen werden auf 16 Zeichen gekuerzt',
    $defs[0]['id'], 'indoor_temp_schlafzimmerimda' );

modules( [ [ '03:00:00:0d:aa:ca', 'NAModule4', '🛏️' ] ] );
$defs = NAWS_Helpers::indoor_chart_defs();
check( 'ein Name ganz ohne verwertbare Zeichen faellt auf die MAC zurueck',
    $defs[0]['id'], 'indoor_temp_indooraaca' );

// ── Zwei Innenmodule bleiben auseinanderzuhalten ─────────────────────────
modules( [
    [ '03:00:00:0e:21:72', 'NAModule4', 'Sleeping' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Gast' ],
] );
$defs = NAWS_Helpers::indoor_chart_defs();
check( 'zwei Innenmodule ergeben vier Kacheln', count( $defs ), 4 );
check( 'die Kennungen sind alle verschieden',
    count( array_unique( array_column( $defs, 'id' ) ) ), 4 );
check( 'nach Modul gruppiert, nicht nach Groesse',
    array_column( $defs, 'id' ),
    [ 'indoor_temp_sleeping', 'indoor_humidity_sleeping', 'indoor_temp_gast', 'indoor_humidity_gast' ] );

// Zwei Module mit demselben Namen sind ein Konfigurationsfehler des Nutzers,
// aber die Kacheln duerfen sich dabei nicht gegenseitig ueberschreiben —
// die Sichtbarkeitsschalter werden nach id gefuehrt.
modules( [
    [ '03:00:00:0e:21:72', 'NAModule4', 'Zimmer' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Zimmer' ],
] );
$defs = NAWS_Helpers::indoor_chart_defs();
check( 'gleiche Namen ergeben gleiche Kennungen — bekannt und hier festgehalten',
    count( array_unique( array_column( $defs, 'id' ) ) ), 2 );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
