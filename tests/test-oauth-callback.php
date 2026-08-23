<?php
/**
 * Tests fuer NAWS_Admin::handle_oauth_callback().
 *
 * Die Methode haengt an admin_init und nimmt den Rueckweg von Netatmo
 * entgegen: ?page=naws-settings&code=...&state=... Sie tauscht damit einen
 * Autorisierungscode gegen Zugriffs- und Erneuerungstoken, schreibt beide in
 * wp_options und stoesst eine Synchronisation an.
 *
 * Zwei Dinge fehlten dabei, und das WordPress-Review-Team hat das erste davon
 * benannt:
 *
 *   1. Es gab keine Rechtepruefung. Der State-Wert belegt, dass der Aufruf zu
 *      einem hier begonnenen Vorgang gehoert -- er sagt nichts darueber, wer
 *      ihn ausloest. Ein Abonnent, der den Rueckleitungslink in die Finger
 *      bekommt, durchlief denselben Weg wie ein Administrator.
 *
 *   2. Schlug die State-Pruefung fehl, sprang ein zweiter Zweig ein und
 *      akzeptierte den Wert auch als wp_verify_nonce($state, 'naws_oauth').
 *      Diese Nonce erzeugt im ganzen Plugin niemand -- ein Annahmepfad ohne
 *      Erzeuger, und genau die Art zusammengesetzter Bedingung, vor der die
 *      Review-Antwort warnt.
 *
 * Gepruefte Reihenfolge: die Rechtepruefung muss VOR dem Verbrauch des State
 * stehen. Sonst loescht ein Aufruf ohne Rechte den gespeicherten State und der
 * Administrator faengt von vorn an.
 *
 *   php tests/test-oauth-callback.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'NAWS_VERSION', '1.9.6.1' );

$GLOBALS['naws_test_options']  = [];
$GLOBALS['naws_test_caps']     = true;
$GLOBALS['naws_test_exchange'] = [];
$GLOBALS['naws_test_sync']     = 0;
$GLOBALS['naws_test_errors']   = [];

// ── Minimale WordPress-Oberflaeche ───────────────────────────────────────
function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['naws_test_options'] )
        ? $GLOBALS['naws_test_options'][ $key ]
        : $default;
}
function update_option( $key, $value, $autoload = true ) {
    $GLOBALS['naws_test_options'][ $key ] = $value;
    return true;
}
function delete_option( $key ) {
    unset( $GLOBALS['naws_test_options'][ $key ] );
    return true;
}
function current_user_can( $cap ) {
    // Nur manage_options ist hier ueberhaupt eine Frage; alles andere waere
    // ein Tippfehler und soll auffallen statt still true zu liefern.
    return 'manage_options' === $cap ? $GLOBALS['naws_test_caps'] : false;
}
function sanitize_text_field( $value ) {
    return is_string( $value ) ? trim( strip_tags( $value ) ) : '';
}
function wp_unslash( $value ) {
    return is_string( $value ) ? stripslashes( $value ) : $value;
}
function admin_url( $path = '' ) {
    return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
    $GLOBALS['naws_test_errors'][] = $code;
}
function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}
function wp_verify_nonce( $nonce, $action = -1 ) {
    // Die Altlast, die hier abgeloest wird: eine Nonce, die als gueltig
    // durchginge. Erzeugt wird sie nirgends -- der Stub tut hier also
    // absichtlich mehr, als die echte Installation je koennte.
    return ( 'naws_oauth' === $action && 'alte-nonce' === $nonce ) ? 1 : false;
}

class WP_Error {
    private $message;
    public function __construct( $message = '' ) {
        $this->message = $message;
    }
    public function get_error_message() {
        return $this->message;
    }
}

/**
 * Steht anstelle der echten API-Klasse. handle_oauth_callback() erzeugt sie
 * mit `new NAWS_API()`, also genuegt es, den Namen vorher zu belegen.
 */
class NAWS_API {
    public function exchange_code( $code, $redirect_uri ) {
        $GLOBALS['naws_test_exchange'][] = $code;
        return true;
    }
    public function sync_current_data() {
        $GLOBALS['naws_test_sync']++;
        return true;
    }
}

require_once __DIR__ . '/../includes/class-naws-admin.php';

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

/**
 * Ein Rueckweg von Netatmo, unter kontrollierten Bedingungen.
 *
 * @param bool   $darf        Antwort von current_user_can('manage_options').
 * @param string $state_link  Der State-Wert im Rueckleitungslink.
 * @param array  $optionen    Vorbelegung von wp_options.
 */
function naws_test_rueckweg( bool $darf, string $state_link, array $optionen ): array {
    $GLOBALS['naws_test_options']  = $optionen;
    $GLOBALS['naws_test_caps']     = $darf;
    $GLOBALS['naws_test_exchange'] = [];
    $GLOBALS['naws_test_sync']     = 0;
    $GLOBALS['naws_test_errors']   = [];

    $_GET = [
        'page'  => 'naws-settings',
        'code'  => 'der-code',
        'state' => $state_link,
    ];

    $admin = ( new ReflectionClass( 'NAWS_Admin' ) )->newInstanceWithoutConstructor();
    $admin->handle_oauth_callback();

    return [
        'getauscht'   => $GLOBALS['naws_test_exchange'],
        'sync'        => $GLOBALS['naws_test_sync'],
        'meldungen'   => $GLOBALS['naws_test_errors'],
        'state_bleibt'=> get_option( 'naws_oauth_state', '' ),
    ];
}

/** Ein frischer, gueltiger Vorgang: State gespeichert, Zeitstempel von eben. */
function naws_test_frisch(): array {
    return [
        'naws_oauth_state'      => 'echter-state',
        'naws_oauth_state_time' => time(),
    ];
}

echo "\nNAWS_Admin::handle_oauth_callback()\n";
echo str_repeat( '-', 74 ) . "\n";

// ── Rechtepruefung ───────────────────────────────────────────────────────
$ohne = naws_test_rueckweg( false, 'echter-state', naws_test_frisch() );

check( 'ohne manage_options wird kein Code gegen Token getauscht',
    $ohne['getauscht'], [] );

check( 'ohne manage_options laeuft keine Synchronisation',
    $ohne['sync'], 0 );

check( 'ohne manage_options bleibt der gespeicherte State stehen',
    $ohne['state_bleibt'], 'echter-state' );

// ── Der Normalfall bleibt unberuehrt ─────────────────────────────────────
$mit = naws_test_rueckweg( true, 'echter-state', naws_test_frisch() );

check( 'mit manage_options und gueltigem State wird der Code getauscht',
    $mit['getauscht'], [ 'der-code' ] );

check( 'und die Synchronisation laeuft genau einmal',
    $mit['sync'], 1 );

check( 'und der verbrauchte State wird geloescht',
    $mit['state_bleibt'], '' );

// ── State-Pruefung ───────────────────────────────────────────────────────
$abgelaufen = naws_test_rueckweg( true, 'echter-state', [
    'naws_oauth_state'      => 'echter-state',
    'naws_oauth_state_time' => time() - 601,
] );

check( 'ein State aelter als zehn Minuten wird abgelehnt',
    $abgelaufen['getauscht'], [] );

check( 'und der Ablehnungsgrund wird gemeldet',
    $abgelaufen['meldungen'], [ 'naws_oauth_invalid' ] );

$fremd = naws_test_rueckweg( true, 'fremder-state', naws_test_frisch() );

check( 'ein State, der nicht zum gespeicherten passt, wird abgelehnt',
    $fremd['getauscht'], [] );

// Der Zweig, den es abzuloesen gilt: kein passender State, aber eine Nonce,
// die wp_verify_nonce durchwinken wuerde.
$altlast = naws_test_rueckweg( true, 'alte-nonce', naws_test_frisch() );

check( 'eine Alt-Nonce ersetzt den State nicht mehr',
    $altlast['getauscht'], [] );

check( 'und auch sie wird als ungueltiger State gemeldet',
    $altlast['meldungen'], [ 'naws_oauth_invalid' ] );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
