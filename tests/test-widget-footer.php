<?php
/**
 * Tests fuer die Fusszeile des Seitenleisten-Widgets.
 *
 * Zwei Fehler, eine Datei. Beide sassen nicht in der Anzeige, sondern in
 * dem, was ihr geliefert wurde.
 *
 * Der Ortsname: get_auto_location_name() schickte die Koordinaten an
 * geocoding-api.open-meteo.com/v1/search?name= -- einen Endpunkt, der nach
 * ORTSNAMEN sucht. Auf dem Server nachgemessen: name=51.35,12.37 liefert
 * HTTP 200 mit dem Rumpf {"generationtime_ms":0.18763542}, also nicht
 * einmal einen results-Schluessel; dieselbe URL mit name=Leipzig liefert
 * Leipzig. Open-Meteo hat keine Rueckwaerts-Geokodierung, der Aufruf konnte
 * nie etwas finden. Netatmo schickt city und country ohnehin bei jeder
 * Synchronisation mit -- save_module() warf sie bisher weg und behielt nur
 * die Koordinaten.
 *
 * Die Uhrzeit: sie war nie eine Uhr, sondern fetched_at der Vorhersage bei
 * CACHE_TTL = 3 Stunden. Gemessen: geholt 18:01, Ablauf 21:01, Anzeige um
 * 20:36 -- also zweieinhalb Stunden dieselbe Zahl. Jetzt zeigt sie den
 * juengsten Messwert der Station, der alle zehn Minuten weiterwandert.
 *
 *   php tests/test-widget-footer.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [];

function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['naws_test_options'] )
        ? $GLOBALS['naws_test_options'][ $key ]
        : $default;
}
function update_option( $key, $value, $autoload = true ) {
    $GLOBALS['naws_test_options'][ $key ] = $value;
    return true;
}
function naws__( $k ) { return $k; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( wp_strip_all_tags( $s ) ) : ''; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }

require_once __DIR__ . '/../includes/class-naws-forecast.php';
require_once __DIR__ . '/../includes/class-naws-weather-state.php';

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

echo "\nWidget-Fusszeile\n" . str_repeat( '-', 74 ) . "\n";

// ── NAWS_Forecast::place_name() ──────────────────────────────────────────
// Baut den Ortsnamen aus dem, was Netatmo mitschickt. Rein: der Aufrufer
// reicht das durch, was die API geliefert hat.
check( 'Stadt und Land werden verbunden',
    NAWS_Forecast::place_name( [ 'city' => 'Leipzig', 'country' => 'DE' ] ), 'Leipzig, DE' );
check( 'nur die Stadt reicht',
    NAWS_Forecast::place_name( [ 'city' => 'Leipzig' ] ), 'Leipzig' );
check( 'nur das Land reicht auch',
    NAWS_Forecast::place_name( [ 'country' => 'DE' ] ), 'DE' );
check( 'ein leeres Feld zaehlt nicht mit',
    NAWS_Forecast::place_name( [ 'city' => '', 'country' => 'DE' ] ), 'DE' );
check( 'Leerzeichen allein zaehlen ebenfalls nicht',
    NAWS_Forecast::place_name( [ 'city' => '   ', 'country' => 'DE' ] ), 'DE' );
check( 'ohne alles bleibt es leer',
    NAWS_Forecast::place_name( [] ), '' );
check( 'was kein String ist, wird uebergangen',
    NAWS_Forecast::place_name( [ 'city' => [ 'Leipzig' ], 'country' => 'DE' ] ), 'DE' );

// ── NAWS_Weather_State::newest_ts() ──────────────────────────────────────
// Der juengste Messzeitpunkt aus einer Reihe von Messwerten.
$mess = [
    [ 'parameter' => 'Temperature', 'recorded_at' => '1787330000' ],
    [ 'parameter' => 'Humidity',    'recorded_at' => '1787336300' ],
    [ 'parameter' => 'Pressure',    'recorded_at' => '1787331111' ],
];
check( 'der groesste Zeitstempel gewinnt',
    NAWS_Weather_State::newest_ts( $mess ), 1787336300 );
check( 'eine leere Reihe ergibt null',
    NAWS_Weather_State::newest_ts( [] ), null );
check( 'ein fehlendes Feld wird uebersprungen',
    NAWS_Weather_State::newest_ts( [ [ 'parameter' => 'Rain' ] ] ), null );
check( 'eine Null ist kein gueltiger Zeitpunkt',
    NAWS_Weather_State::newest_ts( [ [ 'recorded_at' => '0' ] ] ), null );
check( 'ein einzelner Wert kommt unveraendert zurueck',
    NAWS_Weather_State::newest_ts( [ [ 'recorded_at' => 1787336300 ] ] ), 1787336300 );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
