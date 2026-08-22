<?php
/**
 * Tests fuer die Wiederholung fehlgeschlagener Netatmo-Abrufe.
 *
 * Gemessen auf der Referenzinstallation am 2026-08-22, im Cron-Kontext
 * mitgeschnitten:
 *
 *   GET https://api.netatmo.com/api/getstationsdata
 *   HTTP 503 Service Unavailable
 *   Retry-After: 5
 *   {"error":{"code":27,"message":"Service temporarily unavailable"}}
 *
 * Der Server sagt also ausdruecklich, wann er wieder da ist. get_stations_data()
 * hat das bisher weggeworfen und den ganzen Zehn-Minuten-Zyklus aufgegeben --
 * waehrend refresh_access_token() auf demselben Host seit jeher dreimal
 * anklopft. Diese Asymmetrie ist der Fehler, nicht Netatmos Aussetzer.
 *
 * Gegenstand des Tests ist der reine Entscheider retry_delay(): Antwortlage
 * rein, Wartezeit oder Aufgabe raus. Keine Uhr, keine Optionen, kein HTTP --
 * dasselbe Muster wie NAWS_Cron::backoff_interval().
 *
 *   php tests/test-api-retry.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-api.php';

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

echo "\nNetatmo-Abruf: Wiederholung\n" . str_repeat( '-', 74 ) . "\n";

// ── Der gemessene Fall ───────────────────────────────────────────────────
check( 'HTTP 503 mit Code 27 und Retry-After: 5 wartet genau 5 s',
    NAWS_API::retry_delay( 503, 27, '5', 1 ), 5 );

check( 'derselbe Fall im zweiten Anlauf wartet wieder',
    NAWS_API::retry_delay( 503, 27, '5', 2 ), 5 );

// ── Die Obergrenze der Versuche ──────────────────────────────────────────
// Drei Anlaeufe, dann gehoert das Feld dem naechsten Cron-Lauf. Ein Zyklus
// darf nicht beliebig lange in sleep() haengen.
check( 'nach dem dritten Anlauf wird aufgegeben',
    NAWS_API::retry_delay( 503, 27, '5', 3 ), null );
check( 'auch ein vierter Aufruf gibt auf',
    NAWS_API::retry_delay( 503, 27, '5', 4 ), null );

// ── Was der Header sagt, gilt ────────────────────────────────────────────
check( 'Retry-After: 2 wird uebernommen',
    NAWS_API::retry_delay( 503, 27, '2', 1 ), 2 );
check( 'Retry-After: 15 liegt genau auf der Grenze',
    NAWS_API::retry_delay( 503, 27, '15', 1 ), 15 );

// Laenger als die Grenze: nicht kuerzen, sondern aufgeben. Wer 900 Sekunden
// verlangt, will keinen Anruf in 15 -- und der naechste Zyklus kommt ohnehin
// in zehn Minuten. Kuerzen waere ein Widerspruch zur Aussage des Servers.
check( 'Retry-After: 900 gibt auf statt zu kuerzen',
    NAWS_API::retry_delay( 503, 27, '900', 1 ), null );

check( 'fehlender Header faellt auf 5 s zurueck',
    NAWS_API::retry_delay( 503, 27, '', 1 ), 5 );
check( 'Retry-After als HTTP-Datum ist nicht auswertbar und faellt zurueck',
    NAWS_API::retry_delay( 503, 27, 'Sat, 22 Aug 2026 08:30:09 GMT', 1 ), 5 );
check( 'Retry-After: 0 ist keine Wartezeit und faellt zurueck',
    NAWS_API::retry_delay( 503, 27, '0', 1 ), 5 );
check( 'negativer Retry-After faellt zurueck',
    NAWS_API::retry_delay( 503, 27, '-3', 1 ), 5 );

// ── Was sonst noch voruebergehend ist ────────────────────────────────────
check( 'HTTP 500 ohne Fehlercode wird wiederholt',
    NAWS_API::retry_delay( 500, null, '', 1 ), 5 );
check( 'HTTP 502 wird wiederholt',
    NAWS_API::retry_delay( 502, null, '', 1 ), 5 );
check( 'HTTP 504 wird wiederholt',
    NAWS_API::retry_delay( 504, null, '', 1 ), 5 );

// Transportfehler: die Anfrage kam nie bei einer Antwort an (WP_Error,
// Zeitueberschreitung, DNS). Kein Status, aber genauso voruebergehend.
check( 'ein Transportfehler (Status 0) wird wiederholt',
    NAWS_API::retry_delay( 0, null, '', 1 ), 5 );

// Code 27 zaehlt auch dann, wenn der Status es nicht verraet. Netatmo hat
// schon 200er mit Fehlerrumpf geliefert; der Rumpf ist die genauere Quelle.
check( 'Code 27 bei HTTP 200 wird wiederholt',
    NAWS_API::retry_delay( 200, 27, '', 1 ), 5 );

// ── Was endgueltig ist ───────────────────────────────────────────────────
// Hier darf nicht wiederholt werden: ein zweiter Anlauf kostet nur Zeit und
// verschleiert im Cron-Log, was wirklich los ist.
check( 'Code 2 (ungueltiges Token) wird nicht wiederholt',
    NAWS_API::retry_delay( 403, 2, '', 1 ), null );
check( 'Code 3 (abgelaufenes Token) wird nicht wiederholt -- dafuer gibt es den Refresh',
    NAWS_API::retry_delay( 403, 3, '', 1 ), null );
check( 'Code 26 (Nutzungsgrenze) wird nicht wiederholt',
    NAWS_API::retry_delay( 429, 26, '', 1 ), null );
check( 'HTTP 400 wird nicht wiederholt',
    NAWS_API::retry_delay( 400, 21, '', 1 ), null );
check( 'HTTP 404 wird nicht wiederholt',
    NAWS_API::retry_delay( 404, null, '', 1 ), null );
check( 'eine gelungene Antwort wird nicht wiederholt',
    NAWS_API::retry_delay( 200, null, '', 1 ), null );

// ── Reinheit ─────────────────────────────────────────────────────────────
// Zweimal dieselbe Frage, zweimal dieselbe Antwort: keine Uhr, kein Zaehler,
// kein Zustand zwischen den Aufrufen.
$a = NAWS_API::retry_delay( 503, 27, '5', 1 );
$b = NAWS_API::retry_delay( 503, 27, '5', 1 );
check( 'der Entscheider haelt sich keinen Zustand', $a, $b );

printf( "\n  %d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
