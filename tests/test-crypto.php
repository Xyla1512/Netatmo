<?php
/**
 * Tests fuer NAWS_Crypto.
 *
 * Die Klasse haelt den Schluessel in wp-config.php und den Chiffretext in
 * der Datenbank. Das ist ihr ganzer Zweck: ein Dump oder eine Injection
 * allein reicht dann nicht. Zwei Stellen gaben das frueher still auf --
 * encrypt() lieferte bei einem Fehlschlag den Klartext zurueck, und
 * derive_key() fragte defined('AUTH_KEY'), was auch fuer den Platzhalter
 * aus wp-config-sample.php wahr ist.
 *
 *   php tests/test-crypto.php
 *
 * @package NAWS
 */

define( 'ABSPATH', __DIR__ );
define( 'NAWS_VERSION', '1.9.6' );

// AUTH_KEY bleibt absichtlich UNdefiniert: dann laeuft derive_key() ueber
// seinen DB_NAME-Rueckfall (der deshalb definiert sein muss, sonst ist es
// ein Fatal Error), und health() sieht genau den schwachen Schluessel, den
// es melden soll. Beides zugleich in einem Prozess pruefbar.
define( 'DB_NAME', 'naws_test' );

$GLOBALS['naws_test_options'] = [];

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
function __( $text, $domain = 'default' ) {
    return $text;
}

require_once __DIR__ . '/../includes/class-naws-crypto.php';

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

/** Ein echter Salt aus dem WordPress-Generator: 64 Zeichen. */
const ECHTER_SALT = 'nJ4#vQ8!wR2$zX7%mB5^tL9&yH3*pD6(fG1)kC0-sN8+aV4=uT2_eW6/iO5';

/** Ein zweiter, damit Geschwisterlisten realistisch sind. */
const ZWEITER_SALT = 'qE7@rT3#yU9$iO1%pA5^sD8&fG2*hJ6(kL4)zX0-cV7+bN3=mQ9_wR1/tY5';

echo "\nNAWS_Crypto\n" . str_repeat( '-', 74 ) . "\n";

// ── weak_key_source() ────────────────────────────────────────────────────
// Der englische Platzhalter ist 27 Zeichen lang und faellt damit schon
// ueber die Laengenregel. Der Grund fuer die Phrasenliste ist die
// UEBERSETZTE Variante aus einer lokalisierten wp-config-sample.php: die
// ist laenger als 32 Zeichen und wuerde sonst als echter Schluessel
// durchgehen. Der Test benutzt deshalb eine lange Ersatzphrase.
const LANGE_UEBERSETZUNG = 'trage hier deine einzigartige phrase ein und aendere sie';

$phrasen = [ NAWS_Crypto::SAMPLE_PHRASE, LANGE_UEBERSETZUNG ];

check( 'ein leerer Schluessel ist schwach',
    NAWS_Crypto::weak_key_source( '', [], $phrasen ), true );
check( 'ein zu kurzer Schluessel ist schwach',
    NAWS_Crypto::weak_key_source( str_repeat( 'x', 31 ), [], $phrasen ), true );
check( 'genau 32 Zeichen reichen',
    NAWS_Crypto::weak_key_source( str_repeat( 'x', 32 ), [], $phrasen ), false );
check( 'der englische Platzhalter ist schwach',
    NAWS_Crypto::weak_key_source( NAWS_Crypto::SAMPLE_PHRASE, [], $phrasen ), true );
check( 'die lange uebersetzte Phrase ist schwach, obwohl lang genug',
    NAWS_Crypto::weak_key_source( LANGE_UEBERSETZUNG, [], $phrasen ), true );
check( 'ein echter Salt ist es nicht',
    NAWS_Crypto::weak_key_source( ECHTER_SALT, [ ECHTER_SALT, ZWEITER_SALT ], $phrasen ), false );
check( 'derselbe Wert in zwei Konstanten ist schwach',
    NAWS_Crypto::weak_key_source( ECHTER_SALT, [ ECHTER_SALT, ECHTER_SALT ], $phrasen ), true );

// ── key_fingerprint() ────────────────────────────────────────────────────
// Der Goldwert ist mit PHP 8.4.24 vorgerechnet und nagelt den Algorithmus
// fest: substr( hash_hmac( 'sha256', 'naws-keyfp-v1', $key ), 0, 16 ).
check( 'der Abdruck trifft den vorgerechneten Goldwert',
    NAWS_Crypto::key_fingerprint( str_repeat( 'A', 32 ) ), '03fd7e47141a1054' );
check( 'derselbe Schluessel ergibt denselben Abdruck',
    NAWS_Crypto::key_fingerprint( ECHTER_SALT ), NAWS_Crypto::key_fingerprint( ECHTER_SALT ) );
check( 'ein anderer Schluessel einen anderen',
    NAWS_Crypto::key_fingerprint( ECHTER_SALT ) === NAWS_Crypto::key_fingerprint( ZWEITER_SALT ), false );
check( 'der Abdruck ist 16 Hexzeichen lang',
    (bool) preg_match( '/^[0-9a-f]{16}$/', NAWS_Crypto::key_fingerprint( ECHTER_SALT ) ), true );

// ── encrypt() bei kaputtem Cipher ────────────────────────────────────────
// Der Fehlschlag wird echt erzeugt, nicht nachgebaut: die Unterklasse
// liefert einen Cipher, den OpenSSL nicht kennt, und openssl_encrypt()
// gibt daraufhin tatsaechlich false zurueck.
class Krypto_Kaputt extends NAWS_Crypto {
    protected static function cipher(): string {
        return 'kein-solcher-cipher';
    }
}

/** Ruft etwas auf und schluckt die PHP-Warnung des unbekannten Ciphers. */
function ohne_warnung( callable $fn ) {
    set_error_handler( static function () { return true; } );
    $ergebnis = $fn();
    restore_error_handler();
    return $ergebnis;
}

check( 'ein kaputter Cipher liefert null, nicht den Klartext',
    ohne_warnung( static function () { return Krypto_Kaputt::encrypt( 'geheim' ); } ), null );

check( 'der heile Weg liefert weiterhin einen Chiffretext',
    strpos( (string) NAWS_Crypto::encrypt( 'geheim' ), NAWS_Crypto::PREFIX ), 0 );

check( 'ein leerer Wert ist kein Fehlschlag',
    NAWS_Crypto::encrypt( '' ), '' );

check( 'was verschluesselt wurde, kommt auch wieder heraus',
    NAWS_Crypto::decrypt( (string) NAWS_Crypto::encrypt( 'geheim' ) ), 'geheim' );

// ── save_option() schreibt bei Fehlschlag nicht ──────────────────────────
// Der eigentliche Punkt der ganzen Aenderung: der alte Wert bleibt stehen.
$GLOBALS['naws_test_options']['naws_test_secret'] = 'naws_enc:alter-wert';

check( 'ein fehlgeschlagenes Speichern meldet false',
    ohne_warnung( static function () { return Krypto_Kaputt::save_option( 'naws_test_secret', 'neu' ); } ), false );
check( 'und laesst den alten Wert unberuehrt',
    $GLOBALS['naws_test_options']['naws_test_secret'], 'naws_enc:alter-wert' );

check( 'ein gelungenes Speichern meldet true',
    NAWS_Crypto::save_option( 'naws_test_secret', 'neu' ), true );
check( 'und hat den Wert ersetzt',
    NAWS_Crypto::decrypt( $GLOBALS['naws_test_options']['naws_test_secret'] ), 'neu' );

// ── encrypt_fields() laesst das Feld erkennbar stehen ────────────────────
$felder = ohne_warnung( static function () {
    return Krypto_Kaputt::encrypt_fields( [ 'client_secret' => 'roh' ], [ 'client_secret' ] );
} );
check( 'ein nicht verschluesseltes Feld behaelt seinen Wert',
    $felder['client_secret'], 'roh' );
check( 'und traegt kein Praefix, woran der Aufrufer es erkennt',
    NAWS_Crypto::is_encrypted( $felder['client_secret'] ), false );

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
