<?php
/**
 * Rendert templates/table.php gegen eine gemockte WordPress-Umgebung.
 *
 * Dieses Template hat von 1.0 bis 1.9.7 gefehlt. Der Shortcode war
 * registriert, in der Referenz dokumentiert und in der readme aufgezaehlt,
 * das Stylesheet trug seine Klassen — nur die Datei gab es nie, und
 * [naws_table] lieferte einen leeren String plus zwei PHP-Warnungen. Der
 * erste Zweck dieses Tests ist deshalb schlicht: er schlaegt fehl, wenn die
 * Datei wieder verschwindet.
 *
 * Der zweite Zweck ist die Spaltenwahl. get_readings() liefert je nach
 * group_by zwei verschiedene Zeilenformen: gruppiert kommen min_value und
 * max_value dazu und value ist ein Mittelwert, ungruppiert gibt es nur den
 * einzelnen Messwert. Min und Max duerfen deshalb nur erscheinen, wenn sie
 * etwas bedeuten — sonst stuende der Wert dreimal nebeneinander.
 *
 *   php tests/test-table-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [ 'date_format' => 'Y-m-d', 'time_format' => 'H:i' ];
$GLOBALS['mods'] = [
    [ 'module_id' => '02:00:00:a9:5a:08', 'module_type' => 'NAModule1', 'module_name' => 'Aussen' ],
];

function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
require_once __DIR__ . '/i18n-stubs.php';
function wp_date( $fmt, $ts ) { return gmdate( $fmt, $ts ); }

class NAWS_Database {
    public static function get_modules( $a = false ) { return $GLOBALS['mods']; }
}

require_once $PLUGIN . 'includes/class-naws-helpers.php';

/** Eine Zeile, wie get_readings() sie mit einem Bucket liefert. */
function grouped_row( string $param, float $avg, float $min, float $max ): array {
    return [
        'module_id'   => '02:00:00:a9:5a:08',
        'parameter'   => $param,
        'recorded_at' => 1756600000,
        'value'       => $avg,
        'min_value'   => $min,
        'max_value'   => $max,
        'data_points' => 6,
    ];
}

/** Eine Zeile, wie get_readings() sie mit group_by=raw liefert. */
function raw_row( string $param, float $value ): array {
    return [
        'module_id'   => '02:00:00:a9:5a:08',
        'parameter'   => $param,
        'recorded_at' => 1756600000,
        'value'       => $value,
    ];
}

function render( array $rows, array $atts_in = [] ): string {
    $atts = array_merge( [
        'module_id' => '', 'parameters' => '', 'period' => '24h',
        'limit' => '100', 'group_by' => 'hour', 'title' => '',
    ], $atts_in );
    $readings = $rows;
    ob_start();
    include dirname( __DIR__ ) . '/templates/table.php';
    return ob_get_clean();
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\ntemplates/table.php\n" . str_repeat( '-', 74 ) . "\n";

check(
    'das Template existiert ueberhaupt',
    file_exists( dirname( __DIR__ ) . '/templates/table.php' ),
    true
);

$html = render( [ grouped_row( 'Temperature', 21.34, 18.2, 24.9 ) ] );

check( 'die Tabelle wird in ihren Wrapper gesetzt', substr_count( $html, 'naws-table-wrap' ), 1 );
check( 'eine Datenzeile im tbody',                  substr_count( $html, '<tr>' ), 2 );
check( 'gruppiert erscheinen Min und Max',          substr_count( $html, '<th>Min</th>' ) === 1 && substr_count( $html, '<th>Max</th>' ) === 1, true );
check( 'der Mittelwert bekommt seine Ueberschrift', str_contains( $html, '<th>Average</th>' ), true );
check( 'der Modulname statt der MAC',               str_contains( $html, 'Aussen' ), true );
check( 'die MAC steht nicht in der Zeile',          str_contains( $html, '02:00:00:a9:5a:08' ), false );
check( 'der Wert traegt seine Einheit',             str_contains( $html, '21.3 °C' ), true );
check( 'Min und Max ebenso',                        str_contains( $html, '18.2 °C' ) && str_contains( $html, '24.9 °C' ), true );

$raw = render( [ raw_row( 'Temperature', 21.34 ) ], [ 'group_by' => 'raw' ] );

check( 'ungruppiert entfallen Min und Max',   str_contains( $raw, '<th>Min</th>' ) || str_contains( $raw, '<th>Max</th>' ), false );
check( 'und die Spalte heisst schlicht Wert', str_contains( $raw, '<th>Average</th>' ), false );
check( 'vier Spalten statt sechs',            substr_count( $raw, '<th>' ), 4 );

$day = render( [ grouped_row( 'Temperature', 21.34, 18.2, 24.9 ) ], [ 'group_by' => 'day' ] );

check( 'Tagesbuckets zeigen nur das Datum', str_contains( $day, gmdate( 'Y-m-d', 1756600000 ) ) && ! str_contains( $day, gmdate( 'H:i', 1756600000 ) ), true );

$titled = render( [ raw_row( 'Temperature', 1.0 ) ], [ 'group_by' => 'raw', 'title' => 'Messwerte' ] );
check( 'der Titel wird ausgegeben, wenn einer gesetzt ist', str_contains( $titled, 'Messwerte' ), true );
check( 'ohne Titel keine leere Kopfzeile',                  str_contains( $raw, 'naws-header' ), false );

$empty = render( [] );
check( 'ohne Daten der Hinweis statt der Tabelle', str_contains( $empty, 'No data available yet.' ) && ! str_contains( $empty, '<table' ), true );

$xss = render( [ array_merge( raw_row( 'Temperature', 1.0 ), [ 'module_id' => '<script>x</script>' ] ) ], [ 'group_by' => 'raw' ] );
check( 'ein unbekannter Modulbezeichner wird escaped', str_contains( $xss, '<script>' ), false );

echo "\nWelche Parameter die Tabelle zeigt\n" . str_repeat( '-', 74 ) . "\n";

/*
 * Ohne ausdrueckliche parameters-Angabe fragt sc_table() nur die Parameter
 * ab, die get_all_parameters() kennt. Netatmo legt daneben Buchhaltungswerte
 * ab: max_wind_angle und max_wind_str tragen weder Namen noch Einheit und
 * standen als roher Schluessel neben einer nackten Zahl in der Tabelle.
 *
 * Die Grenze laeuft deshalb ueber diese Liste, und nicht ueber eine
 * Ausschlussliste im Template — wer einen dieser Werte ausdruecklich
 * anfordert, bekommt ihn weiterhin.
 */
$naws_params = array_keys( NAWS_Helpers::get_all_parameters() );

check( 'max_wind_angle ist kein darstellbarer Parameter', in_array( 'max_wind_angle', $naws_params, true ), false );
check( 'max_wind_str ebenso wenig',                       in_array( 'max_wind_str', $naws_params, true ), false );

check( 'die Tagesminimum-Temperatur ist benannt',  in_array( 'min_temp', $naws_params, true ), true );
check( 'das Tagesmaximum ebenfalls',               in_array( 'max_temp', $naws_params, true ), true );
check( 'die Stundensumme Regen ebenfalls',         in_array( 'sum_rain_1', $naws_params, true ), true );

check( 'jeder darstellbare Parameter hat auch eine Einheit oder ist bewusst ohne',
    array_values( array_filter( $naws_params, fn( $p ) => NAWS_Helpers::get_unit( $p ) === '' && $p !== 'health_idx' ) ),
    [] );

echo "\nNAWS_Helpers::period_start()\n" . str_repeat( '-', 74 ) . "\n";

/*
 * Die Referenz nennt 24h, 7d und 30d als Schreibweise, und genau die konnte
 * PHP nicht lesen: strtotime('-24h') landete einen Tag in der ZUKUNFT,
 * strtotime('-365d') scheiterte ganz. Beides schob den Anfang des Zeitraums
 * hinter sein Ende, die Abfrage lieferte nichts, und [naws_table] sah aus
 * wie eine Station ohne Daten.
 */
$now = mktime( 12, 0, 0, 8, 31, 2026 );

function ago( string $period, int $now ): int {
    return $now - NAWS_Helpers::period_start( $period, $now );
}

check( 'die dokumentierte Kurzform 24h sind 24 Stunden', ago( '24h', $now ), 24 * 3600 );
check( '7d sind sieben Tage',                            ago( '7d', $now ),  7 * 86400 );
check( '30d sind dreissig Tage',                         ago( '30d', $now ), 30 * 86400 );
check( '365d scheitert nicht mehr',                      ago( '365d', $now ), 365 * 86400 );
check( '1h ist eine Stunde',                             ago( '1h', $now ),  3600 );
check( '2w sind zwei Wochen',                            ago( '2w', $now ),  14 * 86400 );

check( 'ausgeschriebene Angaben gehen weiter',  ago( '12 hours', $now ), 12 * 3600 );
check( 'Grossschreibung stoert nicht',          ago( '7D', $now ),       7 * 86400 );
check( 'ein fuehrendes Minus ebenfalls nicht',  ago( '-7d', $now ),      7 * 86400 );

check( 'der Anfang liegt nie in der Zukunft', NAWS_Helpers::period_start( '24h', $now ) <= $now, true );
check( 'Unsinn faellt auf die 24 Stunden der Vorgabe zurueck', ago( 'quatsch', $now ), 24 * 3600 );
check( 'und nicht auf 1970',                                   NAWS_Helpers::period_start( 'quatsch', $now ) > 0, true );
check( 'ein leeres period ebenso',                             ago( '', $now ), 24 * 3600 );


// ── Ein Messwert, dessen Modul es nicht mehr gibt ────────────────────────
// Die Messwerte ueberleben das Modul: wird eines entfernt, bleiben seine
// Zeilen in der Datenbank. Die Zelle fiel dann auf die module_id zurueck,
// und die ist die MAC-Adresse des Moduls.
$fremd = raw_row( 'Temperature', 21.34 );
$fremd['module_id'] = '03:00:00:0d:aa:ca';
$html = render( [ $fremd ] );
check( 'ein unbekanntes Modul bringt keine MAC in die Tabelle',
    str_contains( $html, '03:00:00:0d:aa:ca' ), false );
check( 'seine Zelle bleibt schlicht leer', substr_count( $html, '<td></td>' ), 1 );
check( 'der Messwert selbst steht weiter da', str_contains( $html, '21.3 °C' ), true );
echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
