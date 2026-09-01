<?php
/**
 * Tests fuer die oeffentlichen Modulkennungen in NAWS_Helpers.
 *
 * Die module_id eines Netatmo-Moduls ist seine MAC-Adresse. Sie stand
 * frueher im Quelltext jeder Seite, die [naws_live] oder [naws_history]
 * zeichnet, und ging in jedem AJAX-Aufruf zurueck an den Server. Fuer die
 * Anzeige braucht der Browser aber nur zu wissen, *welches* Modul gemeint
 * ist — nicht, wie es heisst. Genau das ist eine Referenz: ein sprechender,
 * seitenweit eindeutiger Name, den der Server wieder aufloest.
 *
 * Zwei Dinge muessen dabei halten:
 *
 *   1. Keine Referenz darf eine MAC enthalten — sonst war die Uebung umsonst.
 *   2. Die Aufloesung muss eindeutig sein. Zwei Module mit demselben Namen
 *      sind ein Konfigurationsfehler des Nutzers, aber sie duerfen nicht
 *      dieselbe Referenz bekommen: sonst zeigt die eine Kachel die Werte
 *      der anderen. indoor_chart_defs() laesst diese Kollision zu (die
 *      Kennungen dort sind Sichtbarkeitsschalter, keine Adressen) — hier
 *      ist sie nicht erlaubt.
 *
 *   php tests/test-module-refs.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_modules']  = [];
$GLOBALS['naws_test_settings'] = [];

// ── Minimale WordPress-Oberflaeche ───────────────────────────────────────
function get_option( $key, $default = false ) {
    return $key === 'naws_settings' ? $GLOBALS['naws_test_settings'] : $default;
}
function naws__( $k ) { return $k; }
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function __( $s, $d = null ) { return $s; }

class NAWS_Database {
    public static function get_modules( $active_only = false ): array {
        if ( ! $active_only ) {
            return $GLOBALS['naws_test_modules'];
        }
        return array_values( array_filter(
            $GLOBALS['naws_test_modules'],
            static function ( $m ) { return ! empty( $m['is_active'] ); }
        ) );
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

/** Setzt die Modulliste. Alle Module aktiv, sofern nicht anders gesagt. */
function modules( array $spec ): void {
    $GLOBALS['naws_test_modules'] = array_map( static function ( $m ) {
        return [
            'module_id'   => $m[0],
            'module_type' => $m[1],
            'module_name' => $m[2],
            'is_active'   => $m[3] ?? 1,
        ];
    }, $spec );
}

/** Die Station, wie sie auf der Referenzinstallation steht. */
function station(): void {
    modules( [
        [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis' ],
        [ '02:00:00:a9:5a:08', 'NAModule1', 'Aussen' ],
        [ '05:00:00:04:1c:2e', 'NAModule2', 'Wind' ],
        [ '05:00:00:03:9d:88', 'NAModule3', 'Regen' ],
        [ '03:00:00:0d:aa:ca', 'NAModule4', 'Gast' ],
        [ '03:00:00:0e:21:72', 'NAModule4', 'Sleeping' ],
    ] );
}

echo "\nNAWS_Helpers: oeffentliche Modulkennungen\n" . str_repeat( '-', 74 ) . "\n";

// ── Die vier festen Aliasse sind die, die [naws_value] schon kennt ───────
station();
$map = NAWS_Helpers::module_ref_map();

check( 'die Aussenmessung heisst outdoor',      $map['outdoor'] ?? null, '02:00:00:a9:5a:08' );
check( 'die Basisstation heisst indoor',        $map['indoor']  ?? null, '70:ee:50:a9:5a:08' );
check( 'der Windmesser heisst wind',            $map['wind']    ?? null, '05:00:00:04:1c:2e' );
check( 'der Regenmesser heisst rain',           $map['rain']    ?? null, '05:00:00:03:9d:88' );
check( 'ein Innenmodul heisst in- plus Slug',   $map['in-gast'] ?? null, '03:00:00:0d:aa:ca' );
check( 'jedes Modul bekommt genau eine Kennung', count( $map ), 6 );

// ── Der Punkt der ganzen Uebung ──────────────────────────────────────────
$colons = array_values( array_filter( array_keys( $map ), static function ( $ref ) {
    return str_contains( $ref, ':' );
} ) );
check( 'keine Kennung enthaelt eine MAC', $colons, [] );

// ── Hin und zurueck ──────────────────────────────────────────────────────
$roundtrip = [];
foreach ( $map as $ref => $module_id ) {
    $roundtrip[ $ref ] = NAWS_Helpers::resolve_module_ref( $ref ) === $module_id
        && NAWS_Helpers::module_ref( $module_id ) === $ref;
}
check( 'jede Kennung loest auf ihr Modul auf und zurueck',
    array_keys( array_filter( $roundtrip ) ), array_keys( $map ) );

check( 'ein unbekanntes Modul hat keine Kennung',
    NAWS_Helpers::module_ref( '03:00:00:ff:ff:ff' ), '' );

// ── Was der Server ablehnen muss ─────────────────────────────────────────
// Eine nicht aufloesbare Referenz darf nicht als "kein Filter" durchgehen:
// der Aufrufer bekaeme sonst die Messwerte aller Module statt einer Absage.
check( 'eine erfundene Referenz loest auf nichts auf',
    NAWS_Helpers::resolve_module_ref( 'in-keller' ), null );
check( 'die leere Referenz loest auf nichts auf',
    NAWS_Helpers::resolve_module_ref( '' ), null );

// ── Uebergangsfrist: gecachte Seiten schicken weiter die MAC ─────────────
// Ausserdem nimmt die dokumentierte JS-Schnittstelle NAWS_Chart eine
// module_id direkt entgegen. Beides muss weiter funktionieren.
check( 'eine echte MAC loest weiter auf sich selbst auf',
    NAWS_Helpers::resolve_module_ref( '03:00:00:0d:aa:ca' ), '03:00:00:0d:aa:ca' );
check( 'auch gross geschrieben — derselbe Fehler wie bei den Alias-Tabellen',
    NAWS_Helpers::resolve_module_ref( '03:00:00:0D:AA:CA' ), '03:00:00:0d:aa:ca' );
check( 'eine unbekannte MAC nicht',
    NAWS_Helpers::resolve_module_ref( '03:00:00:ff:ff:ff' ), null );

// ── Der Slug ist derselbe wie ueberall sonst ─────────────────────────────
// Wenn die Kennung ihren eigenen Slug bildete, liefen die beiden Regeln
// auseinander wie die Alias-Tabellen es schon getan haben.
station();
$map   = NAWS_Helpers::module_ref_map();
$slugs = array_column( NAWS_Helpers::indoor_module_slugs(), 'slug' );
$refs  = array_values( array_filter( array_keys( $map ), static function ( $r ) {
    return str_starts_with( $r, 'in-' );
} ) );
check( 'die Innenmodul-Kennungen tragen die Slugs aus indoor_module_slugs()',
    $refs, array_map( static function ( $s ) { return 'in-' . $s; }, $slugs ) );

// ── Und dieselben vier Aliasse, die der Shortcode annimmt ────────────────
// NAWS_Calc::module_id() fuehrte die Zuordnung Alias -> Modultyp als
// eigene Tabelle. Zwei Kopien derselben Tatsache sind in diesem Plugin
// schon zweimal auseinandergelaufen; diese Zusicherung haelt sie zusammen.
require_once __DIR__ . '/../includes/class-naws-calc.php';
foreach ( NAWS_Helpers::module_type_aliases() as $alias => $type ) {
    check( "[naws_value module=\"{$alias}\"] trifft dasselbe Modul wie die Referenz",
        NAWS_Calc::module_id( $alias ), NAWS_Helpers::resolve_module_ref( $alias ) );
}

// ── Kollisionen ──────────────────────────────────────────────────────────
modules( [
    [ '03:00:00:0e:21:72', 'NAModule4', 'Zimmer' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Zimmer' ],
] );
$map = NAWS_Helpers::module_ref_map();
check( 'zwei gleich benannte Module bekommen zwei Kennungen', count( $map ), 2 );
check( 'die zweite wird durchnummeriert',
    array_keys( $map ), [ 'in-zimmer-2', 'in-zimmer' ] );
check( 'und die Nummer folgt der module_id, nicht der Listenposition',
    $map['in-zimmer'] ?? null, '03:00:00:0d:aa:ca' );

modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain', 'Basis' ],
    [ '70:ee:50:b1:00:11', 'NAMain', 'Gartenhaus' ],
] );
check( 'auch zwei Basisstationen bleiben unterscheidbar',
    array_keys( NAWS_Helpers::module_ref_map() ), [ 'indoor', 'indoor-2' ] );

// Und die Durchnummerierung darf nicht davon abhaengen, in welcher
// Reihenfolge die Datenbank die Zeilen zurueckgibt. get_modules() sortiert
// nach `is_active DESC, module_type, module_name` — bei zwei gleich
// benannten Modulen desselben Typs sind alle drei Schluessel gleich, und
// sobald eines abgeschaltet wird, zieht `is_active DESC` das andere nach
// vorn. Haenge die Nummer an der Listenposition, tauschten die beiden
// Referenzen in genau diesem Moment die Module — und eine Seite, die
// vorher in einen Cache gelaufen ist, fragte danach das falsche ab.
$paar = [
    [ '03:00:00:0e:21:72', 'NAModule4', 'Zimmer' ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Zimmer' ],
];
/** Die Zuordnung Modul -> Kennung, unabhaengig von der Reihenfolge notiert. */
function kennung_je_modul(): array {
    $je_modul = array_flip( NAWS_Helpers::module_ref_map() );
    ksort( $je_modul );
    return $je_modul;
}
modules( $paar );
$vorwaerts = kennung_je_modul();
modules( array_reverse( $paar ) );
check( 'die Referenz haengt am Modul, nicht an der Zeilenreihenfolge',
    kennung_je_modul(), $vorwaerts );
check( 'und zwar an beiden',
    $vorwaerts,
    [ '03:00:00:0d:aa:ca' => 'in-zimmer', '03:00:00:0e:21:72' => 'in-zimmer-2' ] );

// ── Randfaelle ───────────────────────────────────────────────────────────
modules( [ [ '03:00:00:0d:aa:ca', 'NAModule4', '???' ] ] );
check( 'ein Name ohne verwertbare Zeichen faellt auf die MAC-Endung zurueck',
    array_keys( NAWS_Helpers::module_ref_map() ), [ 'in-indooraaca' ] );

modules( [ [ '70:ee:50:a9:5a:08', 'NAOldModule', 'Alt' ] ] );
check( 'ein unbekannter Modultyp bekommt trotzdem eine Kennung',
    array_keys( NAWS_Helpers::module_ref_map() ), [ 'module' ] );

// Abgeschaltete Module behalten ihre Kennung: ihre Messwerte stehen weiter
// in der Datenbank, und eine Kennung, die beim Abschalten eines *anderen*
// Moduls umspringt, macht jede gecachte Seite ungueltig.
modules( [
    [ '70:ee:50:a9:5a:08', 'NAMain',    'Basis',    1 ],
    [ '03:00:00:0d:aa:ca', 'NAModule4', 'Gast',     0 ],
    [ '03:00:00:0e:21:72', 'NAModule4', 'Sleeping', 1 ],
] );
check( 'ein abgeschaltetes Modul behaelt seine Kennung',
    array_keys( NAWS_Helpers::module_ref_map() ), [ 'indoor', 'in-gast', 'in-sleeping' ] );

modules( [] );
check( 'ohne Module ist die Tabelle leer', NAWS_Helpers::module_ref_map(), [] );
check( 'und nichts loest auf', NAWS_Helpers::resolve_module_ref( 'outdoor' ), null );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
