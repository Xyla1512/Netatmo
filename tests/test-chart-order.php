<?php
/**
 * Tests fuer die Reihenfolge der Jahresvergleich-Charts und der
 * Live-Kacheln.
 *
 * Beide Listen werden im Backend per Drag & Drop sortiert und als
 * Options-Array aus Schluesseln abgelegt — naws_history_chart_order und
 * naws_live_card_order. Die gespeicherte Reihenfolge und die tatsaechlich
 * vorhandenen Charts bzw. Kacheln koennen jederzeit auseinanderlaufen: ein
 * NAModule4 wird umbenannt oder abgemeldet, ein neues kommt dazu, ein
 * Update bringt eine Kachel mit, die es beim letzten Speichern noch nicht
 * gab. Keiner dieser Faelle darf die Anzeige leerlaufen lassen oder eine
 * Kachel verschlucken.
 *
 * Deshalb ist die Reihenfolge nur ein Wunsch, nie die Wahrheit: welche
 * Eintraege es gibt, sagen die Definitionen; in welcher Folge sie stehen,
 * sagt die Option — soweit sie passt.
 *
 *   php tests/test-chart-order.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_modules'] = [];
$GLOBALS['naws_test_options'] = [];

// ── Minimal WordPress surface ────────────────────────────────────────────
function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['naws_test_options'] )
        ? $GLOBALS['naws_test_options'][ $key ]
        : $default;
}
// Uebersetzungen geben ihren englischen Text zurueck, damit die Erwartungen
// lesbar bleiben. Einzige Ausnahme ist "Wind &amp; Gusts": dieser Text traegt
// eine HTML-Entity, weil das Frontend ihn als Markup einsetzt. Die
// Sortierliste im Backend escaped dagegen selbst — kaeme die Entity dort roh
// an, stuende in der Liste "Wind &amp;amp; Boeen". Er wird hier uebersetzt,
// damit der Fall so aussieht wie auf einer deutschen Seite.
function __( $s, $d = null ) {
    return $s === 'Wind &amp; Gusts' ? 'Wind &amp; Böen' : $s;
}
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
require_once __DIR__ . '/i18n-stubs.php';

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

/** Setzt eine Option fuer den naechsten Aufruf. */
function option( string $key, $value ): void {
    $GLOBALS['naws_test_options'][ $key ] = $value;
}

echo "\nNAWS_Helpers::apply_order()\n";
echo str_repeat( '-', 74 ) . "\n";

$ids = [ 'temp_minmax', 'temp_avg', 'pressure', 'rain', 'humidity' ];

// ── Ohne gespeicherte Reihenfolge bleibt alles, wie es war ───────────────
check( 'keine Reihenfolge gespeichert — Liste bleibt unveraendert',
    NAWS_Helpers::apply_order( $ids, [] ), $ids );

// ── Die gespeicherte Reihenfolge gewinnt ─────────────────────────────────
check( 'die gespeicherte Reihenfolge wird uebernommen',
    NAWS_Helpers::apply_order( $ids, [ 'rain', 'humidity', 'temp_avg', 'pressure', 'temp_minmax' ] ),
    [ 'rain', 'humidity', 'temp_avg', 'pressure', 'temp_minmax' ] );

// ── Ein Chart, den es nicht mehr gibt, darf nichts erfinden ──────────────
// Passiert, sobald ein NAModule4 umbenannt oder abgemeldet wird: seine
// Kennung steht noch in der Option, das Chart selbst gibt es nicht mehr.
check( 'eine Kennung ohne Definition wird uebergangen',
    NAWS_Helpers::apply_order( $ids, [ 'rain', 'indoor_temp_weg', 'temp_avg' ] ),
    [ 'rain', 'temp_avg', 'temp_minmax', 'pressure', 'humidity' ] );

// ── Was die Option nicht kennt, faellt hinten an — nie unter den Tisch ───
// Der wichtigste Fall: ein neues Innenmodul, oder eine Kachel, die ein
// Update mitbringt. Unsichtbar waere hier ein Datenverlust auf Zeit.
check( 'unbekannte Eintraege landen hinten, in ihrer eigenen Reihenfolge',
    NAWS_Helpers::apply_order( $ids, [ 'humidity', 'rain' ] ),
    [ 'humidity', 'rain', 'temp_minmax', 'temp_avg', 'pressure' ] );

// ── Eine doppelt gespeicherte Kennung zeichnet nichts doppelt ────────────
check( 'eine doppelte Kennung erscheint genau einmal',
    NAWS_Helpers::apply_order( $ids, [ 'rain', 'rain', 'temp_avg' ] ),
    [ 'rain', 'temp_avg', 'temp_minmax', 'pressure', 'humidity' ] );

// ── Der Rueckgabewert ist eine Liste, kein Array mit Loechern ────────────
// history.php und die Sortier-UI zaehlen mit; lueckenhafte Schluessel waeren
// ein stiller Fehler beim ersten foreach mit Index.
check( 'das Ergebnis ist lueckenlos durchnummeriert',
    array_keys( NAWS_Helpers::apply_order( $ids, [ 'rain', 'unbekannt' ] ) ),
    [ 0, 1, 2, 3, 4 ] );

echo "\nNAWS_Helpers::sanitize_order()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Die Schreibweise ist die halbe Kennung ───────────────────────────────
// Kachelkennungen kommen aus den Netatmo-Parametern und sind gemischt
// geschrieben: Temperature, WindStrength, CO2. sanitize_key() waere hier
// falsch — es schreibt alles klein, die gespeicherte Reihenfolge fiele nie
// mehr mit den Kacheln zusammen, und das Sortieren haette schlicht keine
// Wirkung mehr.
check( 'Grossbuchstaben bleiben stehen',
    NAWS_Helpers::sanitize_order( [ 'Temperature', 'WindStrength', 'CO2' ] ),
    [ 'Temperature', 'WindStrength', 'CO2' ] );
check( 'Unterstriche halten die Modulkennung zusammen',
    NAWS_Helpers::sanitize_order( [ 'Temperature_gast', 'indoor_temp_gast' ] ),
    [ 'Temperature_gast', 'indoor_temp_gast' ] );

// ── Alles andere kommt gar nicht erst in die Datenbank ───────────────────
check( 'Fremdzeichen werden entfernt',
    NAWS_Helpers::sanitize_order( [ '<script>alert(1)</script>' ] ),
    [ 'scriptalert1script' ] );
check( 'was nach dem Filtern leer ist, faellt weg',
    NAWS_Helpers::sanitize_order( [ 'rain', '...', '' ] ), [ 'rain' ] );
check( 'ein verschachteltes Feld ist keine Kennung',
    NAWS_Helpers::sanitize_order( [ 'rain', [ 'boes' ] ] ), [ 'rain' ] );
check( 'das Ergebnis ist wieder eine lueckenlose Liste',
    array_keys( NAWS_Helpers::sanitize_order( [ '', 'rain', '', 'humidity' ] ) ),
    [ 0, 1 ] );

echo "\nNAWS_Helpers::history_chart_defs()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Ohne Innenmodul und ohne Option: die fuenf festen Charts ─────────────
$GLOBALS['naws_test_options'] = [];
modules( [ [ '70:ee:50:a9:5a:08', 'NAMain', 'Basis' ] ] );
$defs = NAWS_Helpers::history_chart_defs();
check( 'fuenf feste Charts in ihrer angestammten Reihenfolge',
    array_column( $defs, 'id' ),
    [ 'temp_minmax', 'temp_avg', 'pressure', 'rain', 'humidity' ] );
check( 'jeder Chart bringt seine Beschriftung mit',
    $defs[0]['label'], 'Temperature Min / Max' );
check( 'und sein Symbol — die Sortierliste zeigt beides',
    $defs[3]['icon'], '🌧️' );

// ── Innenmodule haengen hinten an ────────────────────────────────────────
modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Gast' ],
] );
$defs = NAWS_Helpers::history_chart_defs();
check( 'die Kacheln des Innenmoduls stehen hinter den festen Charts',
    array_column( $defs, 'id' ),
    [ 'temp_minmax', 'temp_avg', 'pressure', 'rain', 'humidity',
      'indoor_temp_gast', 'indoor_humidity_gast' ] );

// ── Die gespeicherte Reihenfolge zieht ein Innen-Chart nach vorn ─────────
option( 'naws_history_chart_order', [ 'indoor_temp_gast', 'rain', 'temp_minmax' ] );
check( 'ein Innen-Chart laesst sich vor die festen Charts ziehen',
    array_column( NAWS_Helpers::history_chart_defs(), 'id' ),
    [ 'indoor_temp_gast', 'rain', 'temp_minmax',
      'temp_avg', 'pressure', 'humidity', 'indoor_humidity_gast' ] );

// ── Das umbenannte Modul reisst die Reihenfolge nicht mit ────────────────
modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Wohnzimmer' ],
] );
$defs = NAWS_Helpers::history_chart_defs();
check( 'nach dem Umbenennen sind alle sieben Charts noch da',
    count( $defs ), 7 );
check( 'die verwaiste Kennung zieht keinen Platz mehr',
    array_column( $defs, 'id' ),
    [ 'rain', 'temp_minmax', 'temp_avg', 'pressure', 'humidity',
      'indoor_temp_wohnzimmer', 'indoor_humidity_wohnzimmer' ] );

echo "\nNAWS_Helpers::live_card_defs()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Die Kacheln, die das Live-Dashboard wirklich zeichnet ────────────────
// Nicht jeder Messwert ist eine Kachel: der absolute Luftdruck ist nur der
// Rueckfallwert des relativen, die Boe steht im Wind-Tacho, und die
// Regensummen sind Unterzeilen der Regenkachel. Sortierbar ist, was als
// eigener Kasten im Raster steht — mehr nicht, sonst zeigt die Sortierung
// Eintraege, die im Frontend niemand wiederfindet.
$GLOBALS['naws_test_options'] = [];
modules( [ [ '70:ee:50:a9:5a:08', 'NAMain', 'Basis' ] ] );
$card_ids = array_column( NAWS_Helpers::live_card_defs(), 'id' );
check( 'die zwoelf festen Kacheln in der Reihenfolge des Rasters',
    $card_ids,
    [ 'Temperature', 'min_temp', 'max_temp', 'Humidity', 'Pressure', 'CO2',
      'Noise', 'Temperature_indoor', 'Humidity_indoor', 'Rain',
      'WindStrength', 'WindAngle' ] );
check( 'der absolute Luftdruck ist keine eigene Kachel',
    in_array( 'AbsolutePressure', $card_ids, true ), false );
check( 'die Boe ist keine eigene Kachel',
    in_array( 'GustStrength', $card_ids, true ), false );
check( 'die Regensumme ist keine eigene Kachel',
    in_array( 'sum_rain_1', $card_ids, true ), false );

// ── Name und Herkunft bleiben getrennt ───────────────────────────────────
// Temperatur gibt es aussen, in der Basis und in jedem Innenmodul. Die
// Sortierliste braucht denselben Kachelnamen wie das Frontend plus die
// Herkunft daneben, sonst stehen dort drei Zeilen "Temperatur" und keine
// ist von der anderen zu unterscheiden.
$defs = NAWS_Helpers::live_card_defs();
check( 'die Kachel heisst wie im Frontend',
    $defs[0]['label'], 'Temperature' );
check( 'ihre Herkunft steht daneben, nicht im Namen',
    $defs[0]['group'], 'Outdoor' );
check( 'dieselbe Kachel aus der Basis traegt denselben Namen',
    $defs[7]['label'], 'Temperature' );
check( 'unterschieden werden die beiden allein ueber die Herkunft',
    $defs[7]['group'], 'Base' );
check( 'der Regenmesser hat nur ein Modul und braucht keinen Zusatz',
    $defs[9]['group'], '' );
check( 'kaufmaennische Und-Zeichen kommen als Text, nicht als Entity',
    $defs[10]['label'], 'Wind & Böen' );

// ── Ein Innenmodul bringt vier eigene Kacheln mit ────────────────────────
modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Gast' ],
] );
$defs = NAWS_Helpers::live_card_defs();
check( 'das Innenmodul haengt vier Kacheln hinten an',
    array_slice( array_column( $defs, 'id' ), -4 ),
    [ 'Temperature_gast', 'Humidity_gast', 'CO2_gast', 'Noise_gast' ] );
check( 'die Kachel des Innenmoduls traegt den Modulnamen als Herkunft',
    [ $defs[12]['label'], $defs[12]['group'] ], [ 'Temperature', 'Gast' ] );

// ── Min und Max stehen normalerweise in der Temperaturkachel ─────────────
// buildLive() haengt sie als Unterzeilen an die Temperaturkachel und zeichnet
// sie nur dann als eigene Kacheln, wenn die Temperaturkachel selbst
// ausgeblendet ist. In der Sortierliste haben sie deshalb im Regelfall
// nichts verloren: verschoben werden sie mit der Kachel, in der sie stehen.
$einspringer = array_column( NAWS_Helpers::live_card_defs(), 'stands_in_for', 'id' );
check( 'Min springt nur ein, wenn die Temperaturkachel fehlt',
    $einspringer['min_temp'], 'Temperature' );
check( 'Max ebenso',
    $einspringer['max_temp'], 'Temperature' );
check( 'die Temperaturkachel selbst springt fuer niemanden ein',
    $einspringer['Temperature'], '' );
check( 'und der Rest auch nicht',
    array_unique( [ $einspringer['Rain'], $einspringer['CO2'], $einspringer['WindStrength'] ] ),
    [ '' ] );

// ── Jede Kachel weiss, an welchem Modul sie haengt ───────────────────────
// Wird ein Modul abgeschaltet, verschwinden im Frontend alle seine Kacheln.
// Die Sortierliste im Backend zeigt dasselbe an und muss die Zuordnung
// deshalb kennen — sonst behauptet sie eine Kachel, die es nicht gibt.
$nach_id = array_column( NAWS_Helpers::live_card_defs(), 'module', 'id' );
check( 'die Aussenkacheln haengen am Aussenmodul',
    [ $nach_id['Temperature'], $nach_id['min_temp'], $nach_id['Humidity'] ],
    [ 'NAModule1', 'NAModule1', 'NAModule1' ] );
check( 'Luftdruck, CO2 und Laerm gehoeren zur Basis',
    [ $nach_id['Pressure'], $nach_id['CO2'], $nach_id['Noise'] ],
    [ 'NAMain', 'NAMain', 'NAMain' ] );
check( 'auch die Innenwerte der Basis',
    [ $nach_id['Temperature_indoor'], $nach_id['Humidity_indoor'] ],
    [ 'NAMain', 'NAMain' ] );
check( 'Regen und Wind an ihren eigenen Modulen',
    [ $nach_id['Rain'], $nach_id['WindStrength'], $nach_id['WindAngle'] ],
    [ 'NAModule3', 'NAModule2', 'NAModule2' ] );
check( 'die Innenmodul-Kacheln tragen den Slug im Modulnamen',
    $nach_id['CO2_gast'], 'NAModule4_gast' );

echo "\nNAWS_Helpers::module_param_map()\n";
echo str_repeat( '-', 74 ) . "\n";

$map = NAWS_Helpers::module_param_map();
check( 'jedes vorhandene Modul steht drin',
    array_keys( $map ),
    [ 'NAMain', 'NAModule1', 'NAModule2', 'NAModule3', 'NAModule4_gast' ] );

// Die Basis fuehrt ihre Innenluftfeuchte mit. In templates/live.php fehlte
// sie in dieser Liste, weshalb das Abschalten der Basis alles ausblendete —
// nur diese eine Kachel blieb stehen.
check( 'die Basis nimmt ihre Innenluftfeuchte mit',
    in_array( 'Humidity_indoor', $map['NAMain'], true ), true );
check( 'und den absoluten Luftdruck, der keine eigene Kachel hat',
    in_array( 'AbsolutePressure', $map['NAMain'], true ), true );
check( 'das Windmodul nimmt auch Boe und Boenrichtung mit',
    $map['NAModule2'],
    [ 'WindStrength', 'GustStrength', 'WindAngle', 'GustAngle' ] );
check( 'das Innenmodul fuehrt seine vier eigenen Parameter',
    $map['NAModule4_gast'],
    [ 'Temperature_gast', 'Humidity_gast', 'CO2_gast', 'Noise_gast' ] );

echo "\nNAWS_Helpers::live_card_defs() — Reihenfolge\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Auch hier gewinnt die gespeicherte Reihenfolge ───────────────────────
option( 'naws_live_card_order', [ 'Rain', 'Temperature_gast', 'Temperature' ] );
check( 'die Regenkachel laesst sich an die erste Stelle ziehen',
    array_slice( array_column( NAWS_Helpers::live_card_defs(), 'id' ), 0, 3 ),
    [ 'Rain', 'Temperature_gast', 'Temperature' ] );
check( 'dabei geht keine der sechzehn Kacheln verloren',
    count( NAWS_Helpers::live_card_defs() ), 16 );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
