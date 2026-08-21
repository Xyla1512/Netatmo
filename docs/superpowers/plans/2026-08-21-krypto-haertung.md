# Krypto-Härtung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `NAWS_Crypto` schreibt bei einem Verschlüsselungsfehler kein Klartext-Geheimnis mehr in die Datenbank, sondern meldet den Fehlschlag — und macht Umgebungsprobleme (fehlendes openssl, schwacher `AUTH_KEY`, gewechselte Salts) im Backend sichtbar.

**Architecture:** Die Klasse bekommt zwei reine Prädikate (`weak_key_source()`, `key_fingerprint()`), die ohne WordPress testbar sind, und zwei Nähte (`cipher()`, `protected derive_key()`), über die ein Test einen echten Fehlschlag erzeugen kann. `encrypt()` wechselt von `string` auf `?string`; alle vier Aufrufer lassen bei `null` das Feld aus, statt es zu überschreiben. `health()` sammelt vier unabhängige Umgebungsbefunde als Codes; Dashboard und Einstellungsseite übersetzen sie.

**Tech Stack:** PHP 8.0+ (Zielinstallation 8.5.8, lokal 8.4.24), WordPress 6.2+, PHPCS mit `.phpcs.xml.dist` als Null-Fehler-Gate, handgeschriebene Testharnische ohne Framework.

**Spec:** `docs/superpowers/specs/2026-08-21-krypto-haertung-design.md`

## Global Constraints

- **Arbeitsverzeichnis:** `C:\Users\xyla1\.claude\Netatmo\` — nicht im GitHub-Verzeichnis arbeiten.
- **PHP-Untergrenze 8.0.** Keine Syntax ab 8.1: kein `readonly`, keine `enum`, kein `never`, kein `new` in Initialisierern, keine Aufzählungen in Konstanten-Ausdrücken.
- **Version bleibt `1.9.6`.** Kein Versions-Bump, kein Tag, kein Release. Der Changelog-Eintrag geht in den bestehenden `## [Unreleased]`-Abschnitt.
- **Kommentarsprache:** Produktionscode Englisch (Konvention der Klasse), Testdateien Deutsch (Konvention von `tests/`).
- **Commit-Stil:** ganze Sätze im Imperativ, **keine** `feat:`/`fix:`-Präfixe. Vorbilder aus dem Log: „The API key belongs in the header, and nowhere else". Jeder Commit endet mit `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- **Sprachdateien:** `languages/de.php`, `en.php`, `no.php` haben aktuell **je 694 Schlüssel** und müssen nach der Arbeit **je 704** haben — identische Schlüsselmengen, nicht nur identische Zahlen.
- **PHPCS-Gate:** `vendor\bin\phpcs` muss null Fehler melden. `tests/` ist in `.phpcs.xml.dist:32` vom Scan ausgeschlossen; neue Testdateien tauchen in der Dateizahl 52 nie auf und müssen einzeln geprüft werden.
- **`error_log()`-Aufrufe** brauchen `// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log`, **`base64_encode/decode`** brauchen `// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_*` mit Begründung — beide Muster stehen schon in der Datei.
- **Yoda-Bedingungen** sind über `WordPress.PHP.YodaConditions` aktiv; die bestehende Datei ist davon offenbar ausgenommen — beim Hinzufügen dem Stil der umgebenden Zeilen folgen und am Ende PHPCS laufen lassen.

---

### Task 1: Die zwei reinen Prädikate

Sie hängen an nichts und nichts hängt an ihnen — deshalb zuerst. Die Testdatei entsteht hier und wächst in den folgenden Tasks.

**Files:**
- Modify: `includes/class-naws-crypto.php` (neue Konstanten + zwei Methoden, eingefügt vor dem Abschnitt `Key derivation`)
- Test: `tests/test-crypto.php` (neu)

**Interfaces:**
- Consumes: nichts
- Produces:
  - `NAWS_Crypto::SAMPLE_PHRASE` (string `'put your unique phrase here'`)
  - `NAWS_Crypto::MIN_KEY_LENGTH` (int `32`)
  - `NAWS_Crypto::weak_key_source( string $source, array $siblings, array $placeholders ): bool`
  - `NAWS_Crypto::key_fingerprint( string $key ): string` — 16 Hexzeichen

- [ ] **Step 1: Testdatei mit Harnisch und den fehlschlagenden Zusicherungen anlegen**

Erstelle `tests/test-crypto.php`:

```php
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

echo str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php
```

Erwartet: **Fatal error**, `Undefined constant NAWS_Crypto::SAMPLE_PHRASE` — die Methoden gibt es noch nicht. Exit-Code ≠ 0.

- [ ] **Step 3: Die Prädikate implementieren**

In `includes/class-naws-crypto.php` direkt nach der Konstante `TAG_LEN` einfügen:

```php
    /**
     * The placeholder wp-config-sample.php ships with.
     *
     * At 27 characters it is already shorter than MIN_KEY_LENGTH, so the
     * length rule alone catches the English original. It is named here for
     * the localized sample files, whose translated phrase is long enough to
     * pass for a real key.
     */
    const SAMPLE_PHRASE = 'put your unique phrase here';

    /** Shortest key source we are willing to treat as real. */
    const MIN_KEY_LENGTH = 32;
```

Und vor dem Abschnittskommentar `Key derivation` die zwei Methoden:

```php
    /* ================================================================
     * Pure predicates - no options, no constants, no clock
     * ================================================================*/

    /**
     * Is this key source too weak to derive an encryption key from?
     *
     * Everything it judges arrives as an argument, including the phrases to
     * compare against, so the function never calls __() itself and stays
     * testable without WordPress.
     *
     * @param string   $source       The value of AUTH_KEY, or '' when undefined.
     * @param string[] $siblings     All defined KEY/SALT constants, $source included.
     * @param string[] $placeholders Sample-file phrases, original and translated.
     */
    public static function weak_key_source( string $source, array $siblings, array $placeholders ): bool {
        if ( $source === '' || strlen( $source ) < self::MIN_KEY_LENGTH ) {
            return true;
        }

        foreach ( $placeholders as $phrase ) {
            if ( is_string( $phrase ) && $phrase !== '' && $source === $phrase ) {
                return true;
            }
        }

        // The same phrase in two constants means one was pasted over the
        // other, which halves the entropy of both.
        $seen = 0;
        foreach ( $siblings as $value ) {
            if ( is_string( $value ) && $value === $source ) {
                $seen++;
            }
        }

        return $seen > 1;
    }

    /**
     * A short, non-reversible marker for a derived key.
     *
     * The key is the HMAC key rather than the message, so the fingerprint
     * says nothing about it beyond "the same one" or "a different one".
     */
    public static function key_fingerprint( string $key ): string {
        return substr( hash_hmac( 'sha256', 'naws-keyfp-v1', $key ), 0, 16 );
    }
```

- [ ] **Step 4: Test laufen lassen und grün sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `11 bestanden, 0 fehlgeschlagen`, `exit=0`.

- [ ] **Step 5: PHPCS auf die geänderte Klasse**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-crypto.php
```

Erwartet: null Fehler.

- [ ] **Step 6: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add includes/class-naws-crypto.php tests/test-crypto.php && git commit -F- <<'MSG'
Judge a key source without asking WordPress

Two predicates that take everything they judge as an argument: whether a
key source is too weak to derive from, and a fingerprint that identifies a
derived key without revealing it.

The length rule already catches the English placeholder, which is 27
characters. The phrase list exists for the localized sample files, whose
translated phrase is long enough to pass for a real key -- the test uses a
long phrase so it cannot pass for the wrong reason.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 2: `encrypt()` meldet den Fehlschlag, statt Klartext zu liefern

Der Kern der Arbeit. Nach diesem Task kann kein Klartext-Geheimnis mehr in die Datenbank gelangen.

**Files:**
- Modify: `includes/class-naws-crypto.php:34-61` (`encrypt()`), `:127-130` (`save_option()`), `:174-181` (`encrypt_fields()`), `:260` (`derive_key()`-Sichtbarkeit)
- Test: `tests/test-crypto.php` (erweitern)

**Interfaces:**
- Consumes: nichts aus Task 1
- Produces:
  - `NAWS_Crypto::encrypt( string $plaintext ): ?string` — `null` bei Fehlschlag, `''` bei leerer Eingabe
  - `NAWS_Crypto::save_option( string $option_name, string $plaintext, bool $autoload = true ): bool` — war `void`
  - `NAWS_Crypto::cipher(): string` (protected)
  - `NAWS_Crypto::derive_key(): string` (protected, war private)

- [ ] **Step 1: Die fehlschlagenden Zusicherungen ergänzen**

In `tests/test-crypto.php` **vor** dem `echo str_repeat( '-', 74 )`-Abschluss einfügen:

```php
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
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: **Fatal error** — `Krypto_Kaputt::cipher()` überschreibt eine Methode, die es noch nicht gibt, bzw. `Cannot make static method NAWS_Crypto::...`. Exit-Code ≠ 0. Sollte statt dessen `FAIL`-Zeilen erscheinen (etwa weil `encrypt()` noch den Klartext liefert), ist das ebenfalls der erwartete rote Zustand.

- [ ] **Step 3: Die Naht für den Cipher einziehen**

In `includes/class-naws-crypto.php` unmittelbar vor `encrypt()` einfügen:

```php
    /**
     * The cipher, read through a method so a test double can break it.
     *
     * Production behaviour is unchanged: it returns the constant.
     */
    protected static function cipher(): string {
        return self::CIPHER;
    }
```

- [ ] **Step 4: `encrypt()` umbauen**

`encrypt()` vollständig ersetzen durch:

```php
    /**
     * Encrypt a plaintext string.
     *
     * @param  string $plaintext
     * @return string|null  Prefixed base64, '' for empty input, or null when
     *                      the secret could not be encrypted.
     */
    public static function encrypt( string $plaintext ): ?string {
        if ( $plaintext === '' ) return '';

        // Without the extension this would be a call to an undefined
        // function, which is a fatal error rather than a return value.
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            error_log( 'NAWS Crypto: ext-openssl is missing, refusing to store a secret' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }

        // static::, not self:: — derive_key() is overridable, and self::
        // would bypass the override even though it forwards late static
        // binding. This is the seam the rotation test depends on.
        $key = static::derive_key();
        $iv  = random_bytes( 12 ); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            static::cipher(),
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',           // AAD
            self::TAG_LEN
        );

        if ( $ciphertext === false ) {
            error_log( 'NAWS Crypto: encryption failed - ' . openssl_error_string() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            // Never the plaintext. An unencrypted secret in the database is
            // the single outcome this class exists to prevent, and the caller
            // cannot tell a plaintext return from a successful one.
            return null;
        }

        // Pack: IV (12) + tag (16) + ciphertext
        // Binary AES-GCM output has to survive a text option column, so it is
        // base64 for transport, not to obscure anything.
        return self::PREFIX . base64_encode( $iv . $tag . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding binary ciphertext for storage, not obfuscation
    }
```

- [ ] **Step 5: `save_option()` und `encrypt_fields()` nachziehen, `derive_key()` öffnen**

`save_option()` ersetzen:

```php
    /**
     * Store a secret in wp_options (encrypted).
     *
     * @param string $option_name  The option key.
     * @param string $plaintext    The value to store.
     * @param bool   $autoload     WordPress autoload flag.
     * @return bool  False when the value could not be encrypted; nothing is
     *               written in that case and whatever is stored stays.
     */
    public static function save_option( string $option_name, string $plaintext, bool $autoload = true ): bool {
        $encrypted = self::encrypt( $plaintext );
        if ( $encrypted === null ) {
            return false;
        }
        update_option( $option_name, $encrypted, $autoload );
        return true;
    }
```

Die Schleife in `encrypt_fields()` ersetzen:

```php
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) && $data[ $field ] !== '' ) {
                $encrypted = self::encrypt( $data[ $field ] );
                if ( $encrypted !== null ) {
                    $data[ $field ] = $encrypted;
                }
                // On failure the field keeps its plaintext value and carries
                // no naws_enc: prefix, which is how the caller tells.
            }
        }
```

Und die Signatur von `derive_key()` öffnen — nur das Schlüsselwort ändert sich:

```php
    protected static function derive_key(): string {
```

Zuletzt in `decrypt()` dieselbe Umstellung wie in `encrypt()`, damit beide Richtungen denselben Schlüssel benutzen:

```php
        $key        = static::derive_key();
```

- [ ] **Step 6: Test laufen lassen und grün sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `21 bestanden, 0 fehlgeschlagen`, `exit=0`.

- [ ] **Step 7: PHPCS**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-crypto.php
```

Erwartet: null Fehler.

- [ ] **Step 8: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add includes/class-naws-crypto.php tests/test-crypto.php && git commit -F- <<'MSG'
Refuse to store a secret that could not be encrypted

encrypt() returned the plaintext when the cipher failed, so the caller --
which cannot tell one string from another -- wrote an unencrypted secret
into wp_options and only error_log() knew. That is the one outcome this
class exists to prevent.

It returns null now, save_option() writes nothing and reports false, and
encrypt_fields() leaves the field without its prefix so the caller can
see it. The old value in the database stays untouched either way.

The failure is tested for real rather than simulated: a subclass hands
openssl_encrypt() a cipher it does not know, and it genuinely returns
false.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 3: `health()` und der Schlüssel-Fingerabdruck

**Files:**
- Modify: `includes/class-naws-crypto.php` (Konstante `OPT_KEYFP`, `remember_key()`, `health()`, eine Zeile in `encrypt()`)
- Modify: `.phpcs.xml.dist:79-85` (Textdomain `default` freigeben)
- Test: `tests/test-crypto.php` (erweitern)

**Interfaces:**
- Consumes: `weak_key_source()`, `key_fingerprint()` aus Task 1; `encrypt()` aus Task 2
- Produces:
  - `NAWS_Crypto::OPT_KEYFP` (string `'naws_crypto_keyfp'`)
  - `NAWS_Crypto::health(): array` — `[ 'status' => 'ok'|'warning', 'issues' => string[] ]`, Codes: `no_openssl`, `no_gcm`, `weak_key`, `key_changed`

- [ ] **Step 1: Die fehlschlagenden Zusicherungen ergänzen**

In `tests/test-crypto.php` vor dem Abschluss einfügen:

```php
// ── health() ─────────────────────────────────────────────────────────────
// AUTH_KEY ist im Test nicht definiert, health() bekommt also '' und muss
// weak_key melden. Das ist zugleich der DB_NAME-Rueckfall aus derive_key().
$GLOBALS['naws_test_options'] = [];
$zustand = NAWS_Crypto::health();
check( 'ohne AUTH_KEY meldet health() eine Warnung',
    $zustand['status'], 'warning' );
check( 'und nennt weak_key',
    in_array( 'weak_key', $zustand['issues'], true ), true );
check( 'openssl ist hier vorhanden, no_openssl faellt weg',
    in_array( 'no_openssl', $zustand['issues'], true ), false );

// ── Rotation ─────────────────────────────────────────────────────────────
// Zwei Unterklassen mit verschiedenen Schluesseln. Anders ist ein
// Salt-Wechsel nicht nachstellbar, weil Konstanten sich nicht neu
// definieren lassen.
class Krypto_SchluesselA extends NAWS_Crypto {
    protected static function derive_key(): string {
        return str_repeat( 'A', 32 );
    }
    public static function zustand(): array { return static::health(); }
}
class Krypto_SchluesselB extends NAWS_Crypto {
    protected static function derive_key(): string {
        return str_repeat( 'B', 32 );
    }
    public static function zustand(): array { return static::health(); }
}

$GLOBALS['naws_test_options'] = [];
Krypto_SchluesselA::encrypt( 'geheim' );
check( 'ein gelungenes encrypt() hinterlegt den Abdruck',
    get_option( NAWS_Crypto::OPT_KEYFP ), NAWS_Crypto::key_fingerprint( str_repeat( 'A', 32 ) ) );

check( 'unter demselben Schluessel meldet health() kein key_changed',
    in_array( 'key_changed', Krypto_SchluesselA::zustand()['issues'], true ), false );
check( 'unter einem anderen Schluessel schon',
    in_array( 'key_changed', Krypto_SchluesselB::zustand()['issues'], true ), true );

// Ohne hinterlegten Abdruck darf nichts behauptet werden.
// Eine DRITTE Unterklasse, nicht noch einmal B: health() legt sein
// Ergebnis je Klasse ab, ein zweiter Aufruf von B bekaeme die
// zwischengespeicherte Antwort von eben und der Test pruefte nichts.
class Krypto_OhneAbdruck extends NAWS_Crypto {
    protected static function derive_key(): string {
        return str_repeat( 'B', 32 );
    }
    public static function zustand(): array { return static::health(); }
}

$GLOBALS['naws_test_options'] = [];
check( 'ohne gespeicherten Abdruck gibt es kein key_changed',
    in_array( 'key_changed', Krypto_OhneAbdruck::zustand()['issues'], true ), false );
```

**Wichtig:** `health()` benutzt einen statischen Zwischenspeicher je Request. Damit die Zusicherungen oben verschiedene Ergebnisse sehen können, darf der Speicher **nicht** in `health()` selbst liegen, sondern muss pro aufrufender Klasse getrennt sein. Die Implementierung unten löst das über `static::class` als Schlüssel.

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: **Fatal error**, `Call to undefined method NAWS_Crypto::health()`. Exit-Code ≠ 0.

- [ ] **Step 3: Konstante, `remember_key()` und `health()` implementieren**

Bei den übrigen Konstanten ergänzen:

```php
    /** Option holding the fingerprint of the key our ciphertexts were made with. */
    const OPT_KEYFP = 'naws_crypto_keyfp';
```

Nach `key_fingerprint()` einfügen:

```php
    /**
     * Record which key the ciphertexts in the database were made with.
     *
     * Called on every successful encrypt, so reconnecting after a salt
     * rotation clears the warning by itself: the new values carry the new
     * key and the fingerprint moves with them.
     */
    private static function remember_key(): void {
        $fp = self::key_fingerprint( static::derive_key() );
        if ( \get_option( self::OPT_KEYFP, '' ) !== $fp ) {
            \update_option( self::OPT_KEYFP, $fp, false );
        }
    }

    /**
     * What is wrong with the environment, if anything.
     *
     * Returns codes rather than sentences: NAWS_Crypto runs at plugin load
     * through migrate(), and making it depend on NAWS_Lang's load order for
     * a statement that has nothing to do with translation would be a
     * needless coupling. The views do the wording.
     *
     * @return array{status:string,issues:string[]}
     */
    public static function health(): array {
        static $cache = [];
        $who = static::class;
        if ( isset( $cache[ $who ] ) ) {
            return $cache[ $who ];
        }

        $issues = [];

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            $issues[] = 'no_openssl';
        } elseif ( ! in_array( self::CIPHER, openssl_get_cipher_methods(), true ) ) {
            $issues[] = 'no_gcm';
        }

        $source   = ( defined( 'AUTH_KEY' ) && is_string( AUTH_KEY ) ) ? AUTH_KEY : '';
        $siblings = [];
        foreach ( [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ] as $name ) {
            if ( defined( $name ) && is_string( constant( $name ) ) ) {
                $siblings[] = constant( $name );
            }
        }

        // The translated phrase is looked up in core's own text domain,
        // because it is core's string in core's sample file.
        $placeholders = [ self::SAMPLE_PHRASE, __( 'put your unique phrase here', 'default' ) ];

        if ( self::weak_key_source( $source, $siblings, $placeholders ) ) {
            $issues[] = 'weak_key';
        }

        // A fingerprint that is missing is not a fingerprint that matches:
        // whoever updated after a rotation has nothing to compare against,
        // and nothing may be claimed then.
        $stored = \get_option( self::OPT_KEYFP, '' );
        if ( is_string( $stored ) && $stored !== ''
            && $stored !== self::key_fingerprint( static::derive_key() ) ) {
            $issues[] = 'key_changed';
        }

        $cache[ $who ] = [
            'status' => $issues === [] ? 'ok' : 'warning',
            'issues' => $issues,
        ];
        return $cache[ $who ];
    }
```

- [ ] **Step 4: Den Abdruck beim Verschlüsseln schreiben**

In `encrypt()`, unmittelbar **vor** der `return self::PREFIX . base64_encode( ... )`-Zeile:

```php
        self::remember_key();
```

- [ ] **Step 5: Die Textdomain `default` im Prüf-Gate freigeben**

In `.phpcs.xml.dist` die I18n-Regel ersetzen:

```xml
	<!-- Two domains on purpose. The plugin's own strings go through
	     NAWS_Lang, not through __(); the single __() call in
	     NAWS_Crypto::health() deliberately looks up a CORE string in CORE's
	     domain - the phrase wp-config-sample.php ships - so that a localized
	     sample file is recognized too. -->
	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array">
				<element value="xtx-integration-for-netatmo"/>
				<element value="default"/>
			</property>
		</properties>
	</rule>
```

- [ ] **Step 6: Test laufen lassen und grün sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `28 bestanden, 0 fehlgeschlagen`, `exit=0`.

- [ ] **Step 7: PHPCS über die ganze Codebasis**

Das Gate ändert sich hier, deshalb ausnahmsweise der volle Lauf:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && vendor/bin/phpcs --standard=.phpcs.xml.dist
```

Erwartet: null Fehler, 52 geprüfte Dateien.

- [ ] **Step 8: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add includes/class-naws-crypto.php tests/test-crypto.php .phpcs.xml.dist && git commit -F- <<'MSG'
Report what is wrong with the environment, in codes

health() collects four independent findings: no openssl, no GCM, a key
source anyone can reconstruct, and a stored fingerprint that no longer
matches the derived key. The last one is how a salt rotation stops being
a mystery -- every decrypt fails at the tag, and without this the plugin
just goes quiet.

The comparison lives here rather than in decrypt(), because both sides
are available without any ciphertext at all. decrypt() stays as it was.

Codes, not sentences: this class runs at plugin load through migrate(),
and the views can do the wording without dragging NAWS_Lang's load order
into it.

The i18n rule now allows core's domain as well. The one __() call looks
up core's own sample-file phrase, so a localized wp-config-sample.php is
recognized too.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 4: `migrate()` sagt die Wahrheit

**Files:**
- Modify: `includes/class-naws-crypto.php:208-245` (`migrate()`)
- Test: `tests/test-crypto.php` (erweitern)

**Interfaces:**
- Consumes: `encrypt()` aus Task 2, `OPT_KEYFP` und `remember_key()` aus Task 3
- Produces: `NAWS_Crypto::migrate(): bool` — war ohne Rückgabewert

- [ ] **Step 1: Die fehlschlagenden Zusicherungen ergänzen**

```php
// ── migrate() ────────────────────────────────────────────────────────────
// Die Flagge behauptete frueher "migriert", auch wenn kein einziges Feld
// verschluesselt werden konnte.
$GLOBALS['naws_test_options'] = [
    'naws_access_token' => 'roher-token',
    'naws_settings'     => [ 'client_id' => 'roh-id', 'client_secret' => 'roh-secret' ],
];
check( 'eine gelungene Migration meldet true',
    NAWS_Crypto::migrate(), true );
check( 'und setzt die Flagge',
    get_option( 'naws_crypto_migrated' ), NAWS_VERSION );
check( 'der Token liegt danach verschluesselt vor',
    NAWS_Crypto::is_encrypted( get_option( 'naws_access_token' ) ), true );
check( 'und der Abdruck ist nachgetragen',
    is_string( get_option( NAWS_Crypto::OPT_KEYFP ) ) && get_option( NAWS_Crypto::OPT_KEYFP ) !== '', true );

$GLOBALS['naws_test_options'] = [
    'naws_access_token' => 'roher-token',
];
check( 'eine gescheiterte Migration meldet false',
    ohne_warnung( static function () { return Krypto_Kaputt::migrate(); } ), false );
check( 'und setzt die Flagge NICHT',
    get_option( 'naws_crypto_migrated' ), false );
check( 'der Token bleibt dabei unveraendert im Klartext, nicht geloescht',
    get_option( 'naws_access_token' ), 'roher-token' );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `FAIL`-Zeilen bei „eine gelungene Migration meldet true" (`migrate()` gibt heute `null` zurück) und bei „setzt die Flagge NICHT". Exit-Code ≠ 0.

- [ ] **Step 3: `migrate()` umbauen**

`migrate()` vollständig ersetzen:

```php
    /**
     * Migrate all plaintext secrets to encrypted storage.
     *
     * Safe to call repeatedly - it skips values that already carry the
     * prefix. The flag is only set when every field really ended up
     * encrypted; on a host where that cannot happen it stays unset and the
     * caller tries again on the next admin page. That costs a handful of
     * get_option calls and writes nothing, and it heals itself the moment
     * openssl comes back.
     *
     * @return bool  True when everything intended is encrypted.
     */
    public static function migrate(): bool {
        $all_done = true;

        // 1. Individual token options
        $secret_options = [ 'naws_access_token', 'naws_refresh_token' ];
        foreach ( $secret_options as $opt ) {
            $val = \get_option( $opt, '' );
            if ( is_string( $val ) && $val !== '' && ! self::is_encrypted( $val ) ) {
                if ( ! self::save_option( $opt, $val ) ) {
                    $all_done = false;
                }
            }
        }

        // 2. Settings array: encrypt client_id/secret if still plaintext
        $settings = \get_option( 'naws_settings', [] );
        if ( is_array( $settings ) ) {
            $needs_save = false;
            foreach ( [ 'client_id', 'client_secret' ] as $field ) {
                $val = $settings[ $field ] ?? '';
                if ( is_string( $val ) && $val !== '' && ! self::is_encrypted( $val ) ) {
                    $encrypted = self::encrypt( $val );
                    if ( $encrypted === null ) {
                        $all_done = false;
                        continue;
                    }
                    $settings[ $field ] = $encrypted;
                    $needs_save         = true;
                }
            }
            if ( $needs_save ) {
                \update_option( 'naws_settings', $settings );
            }
        }

        // 3. REST API key
        $rest_cfg = \get_option( 'naws_rest_api', [] );
        if ( is_array( $rest_cfg ) ) {
            $key = $rest_cfg['api_key'] ?? '';
            if ( is_string( $key ) && $key !== '' && ! self::is_encrypted( $key ) ) {
                $encrypted = self::encrypt( $key );
                if ( $encrypted === null ) {
                    $all_done = false;
                } else {
                    $rest_cfg['api_key'] = $encrypted;
                    \update_option( 'naws_rest_api', $rest_cfg );
                }
            }
        }

        // Existing installations carry ciphertext but no fingerprint yet.
        // This is where they get one, so a later rotation is recognisable.
        if ( \get_option( self::OPT_KEYFP, '' ) === '' && $all_done ) {
            self::remember_key();
        }

        if ( $all_done ) {
            \update_option( 'naws_crypto_migrated', NAWS_VERSION, false );
        }

        return $all_done;
    }
```

- [ ] **Step 4: Test laufen lassen und grün sehen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `35 bestanden, 0 fehlgeschlagen`, `exit=0`.

- [ ] **Step 5: PHPCS**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-crypto.php
```

Erwartet: null Fehler.

- [ ] **Step 6: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add includes/class-naws-crypto.php tests/test-crypto.php && git commit -F- <<'MSG'
Set the migrated flag only when it is true

migrate() stamped the version on naws_crypto_migrated even when not one
field could be encrypted, so the flag read "migrated" while everything sat
in plaintext.

It now reports whether it finished, and only stamps the flag when it did.
On a host where encryption cannot work the flag stays unset and the loader
retries on the next admin page -- a few get_option calls that write
nothing, and that heal themselves the moment openssl returns.

It also backfills the key fingerprint, which is how installations that
already hold ciphertext get something to compare against later.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 5: Die zehn Sprachschlüssel

Vor den Anzeige-Tasks, weil beide sie brauchen.

**Files:**
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php` (je zehn Schlüssel ans Ende des Rückgabe-Arrays, vor der schließenden `];`)

**Interfaces:**
- Consumes: die `health()`-Codes aus Task 3
- Produces: die Schlüssel `crypto_state_label`, `crypto_state_ok`, `crypto_state_warn`, `crypto_no_openssl`, `crypto_no_gcm`, `crypto_weak_key`, `crypto_key_changed`, `crypto_save_failed`, `crypto_connect_failed`, `crypto_salt_link`

- [ ] **Step 1: Die Schlüssel in `languages/de.php` ergänzen**

Vor der abschließenden `];` einfügen:

```php
    // ── Verschluesselung ──────────────────────────────────────────────
    'crypto_state_label'    => 'Zugangsdaten',
    'crypto_state_ok'       => 'Verschlüsselt gespeichert',
    'crypto_state_warn'     => 'Prüfen',
    'crypto_no_openssl'     => 'Die PHP-Erweiterung openssl fehlt auf diesem Server. Zugangsdaten können nicht verschlüsselt gespeichert werden — sie werden deshalb gar nicht gespeichert.',
    'crypto_no_gcm'         => 'Dieser Server kennt das Verfahren aes-256-gcm nicht. Zugangsdaten können nicht verschlüsselt gespeichert werden — sie werden deshalb gar nicht gespeichert.',
    'crypto_weak_key'       => 'AUTH_KEY in der wp-config.php ist der Beispielwert oder zu kurz. Die Zugangsdaten sind zwar verschlüsselt, aber mit einem Schlüssel, den jeder nachrechnen kann.',
    'crypto_key_changed'    => 'Die WordPress-Salts wurden geändert, seit die Zugangsdaten gespeichert wurden. Sie sind damit nicht mehr lesbar — bitte einmal neu mit Netatmo verbinden.',
    'crypto_save_failed'    => 'Die Zugangsdaten wurden NICHT übernommen: Sie ließen sich nicht verschlüsselt speichern. Der bisher gespeicherte Wert bleibt unverändert.',
    'crypto_connect_failed' => 'Die Verbindung ließ sich nicht sicher speichern. Ursache siehe Hinweise oben.',
    'crypto_salt_link'      => 'Neue Salts erzeugen',
```

- [ ] **Step 2: Dieselben Schlüssel in `languages/en.php` ergänzen**

```php
    // ── Encryption ────────────────────────────────────────────────────
    'crypto_state_label'    => 'Credentials',
    'crypto_state_ok'       => 'Stored encrypted',
    'crypto_state_warn'     => 'Needs attention',
    'crypto_no_openssl'     => 'The PHP openssl extension is missing on this server. Credentials cannot be stored encrypted, so they are not stored at all.',
    'crypto_no_gcm'         => 'This server does not know the aes-256-gcm cipher. Credentials cannot be stored encrypted, so they are not stored at all.',
    'crypto_weak_key'       => 'AUTH_KEY in wp-config.php is the sample value or too short. Your credentials are encrypted, but with a key anyone can reconstruct.',
    'crypto_key_changed'    => 'The WordPress salts have changed since the credentials were stored, so they can no longer be read. Please connect to Netatmo again.',
    'crypto_save_failed'    => 'The credentials were NOT saved: they could not be stored encrypted. The previously stored value is unchanged.',
    'crypto_connect_failed' => 'The connection could not be stored securely. See the notices above for the reason.',
    'crypto_salt_link'      => 'Generate new salts',
```

- [ ] **Step 3: Dieselben Schlüssel in `languages/no.php` ergänzen**

```php
    // ── Kryptering ────────────────────────────────────────────────────
    'crypto_state_label'    => 'Tilgangsdata',
    'crypto_state_ok'       => 'Lagret kryptert',
    'crypto_state_warn'     => 'Må sjekkes',
    'crypto_no_openssl'     => 'PHP-utvidelsen openssl mangler på denne serveren. Tilgangsdata kan ikke lagres kryptert, og lagres derfor ikke i det hele tatt.',
    'crypto_no_gcm'         => 'Denne serveren kjenner ikke metoden aes-256-gcm. Tilgangsdata kan ikke lagres kryptert, og lagres derfor ikke i det hele tatt.',
    'crypto_weak_key'       => 'AUTH_KEY i wp-config.php er eksempelverdien eller for kort. Tilgangsdataene er kryptert, men med en nøkkel hvem som helst kan regne ut.',
    'crypto_key_changed'    => 'WordPress-saltene er endret siden tilgangsdataene ble lagret, så de kan ikke leses lenger. Koble til Netatmo på nytt.',
    'crypto_save_failed'    => 'Tilgangsdataene ble IKKE lagret: de kunne ikke lagres kryptert. Verdien som var lagret fra før, er uendret.',
    'crypto_connect_failed' => 'Tilkoblingen kunne ikke lagres sikkert. Se merknadene over for årsaken.',
    'crypto_salt_link'      => 'Lag nye salt',
```

- [ ] **Step 4: Schlüsselzahl und Schlüsselmenge prüfen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && for f in de en no; do printf "%s: " "$f"; grep -cE "^\s+'[a-z0-9_]+'\s*=>" languages/$f.php; done
```

Erwartet: je `704`.

Und die Mengen selbst, nicht nur die Zahlen — zwei Dateien können 704 Schlüssel haben und trotzdem verschiedene:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && for f in de en no; do grep -oE "^\s+'[a-z0-9_]+'" languages/$f.php | tr -d " '" | sort > /tmp/keys_$f.txt; done && diff /tmp/keys_de.txt /tmp/keys_en.txt && diff /tmp/keys_de.txt /tmp/keys_no.txt && echo "alle drei identisch"
```

Erwartet: `alle drei identisch`.

- [ ] **Step 5: Syntax aller drei Dateien prüfen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && for f in de en no; do php -l languages/$f.php; done
```

Erwartet: dreimal `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add languages/de.php languages/en.php languages/no.php && git commit -F- <<'MSG'
Words for the four things that can be wrong

Ten keys per file, 694 to 704, covering the health codes, the dashboard
tile and the two messages that only make sense at the moment of a failed
save: that the value was not taken, and that the previous one still
stands.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 6: Die vier Aufrufer

**Files:**
- Modify: `includes/class-naws-admin.php:99-110` (Einstellungsformular)
- Modify: `includes/class-naws-api.php:161-169` (OAuth-Tausch)
- Modify: `includes/class-naws-rest-api.php:461-467` (API-Schlüssel)
- Modify: `admin/views/rest-api-docs.php:14-33` (die drei save_config-Aufrufe)

**Interfaces:**
- Consumes: `encrypt(): ?string` und `save_option(): bool` aus Task 2; `crypto_save_failed` und `crypto_connect_failed` aus Task 5
- Produces: Option `naws_crypto_write_failed` (int, Unix-Zeitstempel, `autoload = false`) — gelesen von der Einstellungsseite in Task 7

- [ ] **Step 1: Einstellungsformular — Feld auslassen statt überschreiben**

In `includes/class-naws-admin.php` die zwei Blöcke ersetzen:

```php
        if ( $sent( 'client_id' ) ) {
            $raw = sanitize_text_field( $input['client_id'] );
            // Encrypt if plaintext; skip if already encrypted (safety guard).
            // On a failed encrypt the key is left out of $clean entirely, so
            // the stored value survives untouched and no plaintext is written.
            if ( $raw !== '' && ! NAWS_Crypto::is_encrypted( $raw ) ) {
                $encrypted = NAWS_Crypto::encrypt( $raw );
                if ( $encrypted === null ) {
                    add_settings_error( 'naws', 'naws_crypto_failed', naws__( 'crypto_save_failed' ) );
                } else {
                    $clean['client_id'] = $encrypted;
                }
            } else {
                $clean['client_id'] = $raw;
            }
        }
        if ( $sent( 'client_secret' ) ) {
            $raw = sanitize_text_field( $input['client_secret'] );
            if ( $raw !== '' && ! NAWS_Crypto::is_encrypted( $raw ) ) {
                $encrypted = NAWS_Crypto::encrypt( $raw );
                if ( $encrypted === null ) {
                    add_settings_error( 'naws', 'naws_crypto_failed_secret', naws__( 'crypto_save_failed' ) );
                } else {
                    $clean['client_secret'] = $encrypted;
                }
            } else {
                $clean['client_secret'] = $raw;
            }
        }
```

- [ ] **Step 2: OAuth-Tausch — Fehlschlag merken statt verschlucken**

In `includes/class-naws-api.php` den Block ersetzen:

```php
        // Netatmo ALWAYS returns a refresh_token on initial authorization.
        // On refresh_token grants it may or may not rotate it - keep old one if missing.
        $stored_ok = true;
        if ( ! empty( $body['refresh_token'] ) ) {
            $this->refresh_token = $body['refresh_token'];
            $stored_ok = NAWS_Crypto::save_option( 'naws_refresh_token', $this->refresh_token );
        }

        if ( ! NAWS_Crypto::save_option( 'naws_access_token', $this->access_token ) ) {
            $stored_ok = false;
        }

        // The refresh token cannot be fetched again, so a failure here is
        // worth remembering past this request - the settings page turns it
        // into a sentence. It is cleared as soon as a write succeeds.
        if ( $stored_ok ) {
            delete_option( 'naws_crypto_write_failed' );
        } else {
            error_log( 'NAWS Crypto: could not store the Netatmo tokens encrypted' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            update_option( 'naws_crypto_write_failed', time(), false );
        }

        update_option( 'naws_token_expiry',  $this->token_expiry );
```

- [ ] **Step 3: API-Schlüssel — nicht speichern, wenn er nicht verschlüsselt werden kann**

`save_config()` meldet den Fehlschlag über den **Rückgabewert**, nicht über `add_settings_error()`. Grund (Vorab-Scan, Befund 1): Die Methode wird ausschließlich aus `admin/views/rest-api-docs.php:17,23,29` gerufen, und diese Ansicht rendert ihre Meldungen über eigene `$message`/`$msg_type`-Variablen im Seitenkörper. `add_settings_error()` schreibt dagegen in einen Puffer, den `settings_errors()` auf `admin_notices` leert — dieser Haken ist beim Rendern der Ansicht längst gefeuert, die Meldung erschiene nie.

In `includes/class-naws-rest-api.php` `save_config()` ersetzen:

```php
    /**
     * Save config (encrypts api_key before storing).
     *
     * @return bool  False when the key could not be encrypted; nothing is
     *               written then, and the caller reports it.
     */
    public static function save_config( array $cfg ): bool {
        // Encrypt the API key before saving. A key that cannot be encrypted
        // is not stored at all - it is self-generated and one click away.
        if ( isset( $cfg['api_key'] ) && $cfg['api_key'] !== '' && ! NAWS_Crypto::is_encrypted( $cfg['api_key'] ) ) {
            $encrypted = NAWS_Crypto::encrypt( $cfg['api_key'] );
            if ( $encrypted === null ) {
                return false;
            }
            $cfg['api_key'] = $encrypted;
        }
        update_option( self::OPT, $cfg );
        return true;
    }
```

- [ ] **Step 3b: Die API-Ansicht meldet den Fehlschlag in ihrem eigenen Muster**

In `admin/views/rest-api-docs.php` die drei Aufrufe so ergänzen, dass ein `false` die Erfolgsmeldung ersetzt. Der `save_settings`-Zweig:

```php
    if ( $action === 'save_settings' ) {
        $cfg['enabled']    = ! empty( $_POST['rest_enabled'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- boolean check
        $cfg['rate_limit'] = max( 1, min( 600, intval( $_POST['rate_limit'] ?? 60 ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- cast to int
        if ( NAWS_Rest_API::save_config( $cfg ) ) {
            $message = naws__( 'rest_saved' );
        } else {
            $message  = naws__( 'crypto_save_failed' );
            $msg_type = 'notice-error';
        }
    }
```

Der `generate_key`-Zweig:

```php
    if ( $action === 'generate_key' ) {
        $cfg['api_key'] = NAWS_Rest_API::generate_api_key();
        if ( NAWS_Rest_API::save_config( $cfg ) ) {
            $message = naws__( 'rest_key_generated' );
        } else {
            $message  = naws__( 'crypto_save_failed' );
            $msg_type = 'notice-error';
        }
    }
```

Der `revoke_key`-Zweig bleibt **unverändert**: Er setzt `api_key` auf `''`, und ein leerer Wert läuft gar nicht erst durch `encrypt()` — Widerrufen muss auch auf einem Host funktionieren, auf dem nicht verschlüsselt werden kann.

- [ ] **Step 4: Syntax aller drei Dateien prüfen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php -l includes/class-naws-admin.php && php -l includes/class-naws-api.php && php -l includes/class-naws-rest-api.php
```

Erwartet: dreimal `No syntax errors detected`.

- [ ] **Step 5: Die bestehenden Tests laufen lassen — nichts darf kaputtgehen**

`test-rest-auth.php` und `test-settings-merge.php` fassen genau diese Dateien an:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && for t in tests/test-*.php; do printf "%-34s " "$(basename $t)"; php "$t" > /tmp/out.txt 2>&1 && echo OK || { echo FEHLER; cat /tmp/out.txt; }; done
```

Erwartet: alle `OK`.

- [ ] **Step 6: PHPCS**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && vendor/bin/phpcs --standard=.phpcs.xml.dist
```

Erwartet: null Fehler.

- [ ] **Step 7: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add includes/class-naws-admin.php includes/class-naws-api.php includes/class-naws-rest-api.php admin/views/rest-api-docs.php && git commit -F- <<'MSG'
Leave the stored secret alone when the new one cannot be encrypted

All four callers check for null now. The settings form leaves the key out
of $clean, so the value already in the database survives and the rest of
the form saves as usual. The REST key is not written at all -- it is self
generated and one click away.

The OAuth exchange is the awkward one: the refresh token cannot be
fetched again, and refusing to write it means the round trip was for
nothing. It still refuses, because a long-lived credential in plaintext
is worse, and it records the moment in naws_crypto_write_failed so the
settings page can say what happened. A later successful write clears it.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 7: Die Anzeige

**Files:**
- Modify: `admin/views/dashboard.php:145-172` (Kachel neben der Health-Kachel)
- Modify: `admin/views/settings.php:38-45` (Hinweisblock oben)

**Interfaces:**
- Consumes: `NAWS_Crypto::health()` aus Task 3; alle zehn Schlüssel aus Task 5; `naws_crypto_write_failed` aus Task 6
- Produces: nichts

- [ ] **Step 1: Dashboard-Kachel ergänzen**

In `admin/views/dashboard.php` nach dem schließenden `</div>` der Health-Kachel und **vor** dem `</div>`, das das Kachelraster beendet, einfügen:

```php
        <?php
        // Encryption state. Deliberately its own tile: turning the polling
        // health red because openssl is missing would make both statements
        // useless.
        $crypto        = NAWS_Crypto::health();
        $crypto_ok     = ( $crypto['status'] === 'ok' );
        $crypto_color  = $crypto_ok ? 'green' : 'orange';
        $crypto_icon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
        ?>
        <div class="naws-stat-card">
            <div class="naws-stat-icon-wrap naws-stat-color-<?php echo esc_attr( $crypto_color ); ?>"><?php echo wp_kses( $crypto_icon, naws_svg_kses_args() ); ?></div>
            <div class="naws-stat-body">
                <div class="naws-stat-value naws-stat-value--date" style="font-size:0.8rem;"><?php echo esc_html( $crypto_ok ? naws__( 'crypto_state_ok' ) : naws__( 'crypto_state_warn' ) ); ?></div>
                <div class="naws-stat-label"><?php naws_e( 'crypto_state_label' ); ?></div>
                <?php if ( ! $crypto_ok ) : ?>
                <div class="naws-stat-sub" style="color:#f59e0b;"><?php echo esc_html( naws__( 'crypto_' . $crypto['issues'][0] ) ); ?></div>
                <?php endif; ?>
            </div>
        </div>
```

- [ ] **Step 2: Hinweisblock auf der Einstellungsseite ergänzen**

In `admin/views/settings.php` unmittelbar **vor** dem Block `<?php if ( get_option( 'naws_auth_required' ) ) : ?>` einfügen:

```php
    <?php
    $naws_crypto = NAWS_Crypto::health();
    if ( $naws_crypto['status'] !== 'ok' ) : ?>
        <div class="notice notice-warning">
            <?php foreach ( $naws_crypto['issues'] as $naws_issue ) : ?>
                <p>
                    <?php echo esc_html( naws__( 'crypto_' . $naws_issue ) ); ?>
                    <?php if ( $naws_issue === 'weak_key' ) : ?>
                        <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank" rel="noopener noreferrer"><?php naws_e( 'crypto_salt_link' ); ?></a>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( get_option( 'naws_crypto_write_failed' ) ) : ?>
        <div class="notice notice-error">
            <p><strong><?php naws_e( 'crypto_connect_failed' ); ?></strong></p>
        </div>
    <?php endif; ?>
```

- [ ] **Step 3: Syntax prüfen**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && php -l admin/views/dashboard.php && php -l admin/views/settings.php
```

Erwartet: zweimal `No syntax errors detected`.

- [ ] **Step 4: Prüfen, dass jeder `health()`-Code einen Sprachschlüssel hat**

Die Ansichten bauen den Schlüssel als `'crypto_' . $code` zusammen. Ein Code ohne Schlüssel bliebe stumm:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && for c in no_openssl no_gcm weak_key key_changed; do for f in de en no; do grep -q "'crypto_$c'" languages/$f.php || echo "FEHLT: crypto_$c in $f.php"; done; done; echo "Pruefung durch"
```

Erwartet: nur `Pruefung durch`, keine `FEHLT`-Zeile.

- [ ] **Step 5: PHPCS**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && vendor/bin/phpcs --standard=.phpcs.xml.dist
```

Erwartet: null Fehler.

- [ ] **Step 6: Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add admin/views/dashboard.php admin/views/settings.php && git commit -F- <<'MSG'
Show the encryption state, including when it is fine

A tile of its own next to the polling health, green when the credentials
are stored encrypted and orange with the first finding when they are not.
The original complaint was the silence, so it says so even when there is
nothing wrong.

The settings page lists every finding, links the salt generator where
that is the point, and carries the one message health() cannot know: that
the value just entered was not taken.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

### Task 8: Mutationsproben, Lint auf beiden PHP-Versionen, Changelog

Ein grüner Erstlauf beweist nichts. Dieser Task prüft, dass die Tests die Fehler auch wirklich fangen.

**Files:**
- Modify: `CHANGELOG.md` (Eintrag im bestehenden `## [Unreleased]`-Abschnitt)

**Interfaces:**
- Consumes: alles aus Task 1–7
- Produces: nichts

- [ ] **Step 1: Sicherstellen, dass alles committet ist**

Am 19. und 20.08. ging zweimal uncommittete Arbeit durch ein `git checkout --` in einer Mutationsprobe verloren. Deshalb zuerst:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git status --short
```

Erwartet: keine Zeile zu `includes/`, `admin/`, `languages/` oder `tests/`. Falls doch: erst committen, dann weiter.

- [ ] **Step 2: Mutation 1 — der Klartext-Rückfall kehrt zurück**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && sed -i 's/^            return null;$/            return $plaintext;/' includes/class-naws-crypto.php && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: mindestens eine `FAIL`-Zeile, `exit=1`. **Exit-Code und „Fatal error" mitzählen** — eine Mutation, die einen Fatal Error auslöst, liefert null FAIL-Zeilen und sähe sonst wie „nicht gefangen" aus.

Zurücksetzen:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git checkout -- includes/class-naws-crypto.php && php tests/test-crypto.php > /dev/null && echo "wieder gruen"
```

- [ ] **Step 3: Mutation 2 — die Längenregel fällt weg**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && sed -i "s/if ( \$source === '' || strlen( \$source ) < self::MIN_KEY_LENGTH ) {/if ( \$source === '' ) {/" includes/class-naws-crypto.php && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `FAIL` bei „ein zu kurzer Schluessel ist schwach", `exit=1`.

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git checkout -- includes/class-naws-crypto.php
```

- [ ] **Step 4: Mutation 3 — die Phrasenliste wird ignoriert**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && sed -i 's/^        foreach ( \$placeholders as \$phrase ) {$/        foreach ( [] as $phrase ) {/' includes/class-naws-crypto.php && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `FAIL` bei „die lange uebersetzte Phrase ist schwach, obwohl lang genug", `exit=1`. Das ist die Zusicherung, die beweist, dass die Phrasenliste etwas tut, was die Längenregel nicht schon tut.

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git checkout -- includes/class-naws-crypto.php
```

- [ ] **Step 5: Mutation 4 — der fehlende Abdruck gilt als Abweichung**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && sed -i "s/        if ( is_string( \$stored ) \&\& \$stored !== ''$/        if ( is_string( \$stored )/" includes/class-naws-crypto.php && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `FAIL` bei „ohne gespeicherten Abdruck gibt es kein key_changed", `exit=1`.

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git checkout -- includes/class-naws-crypto.php
```

- [ ] **Step 6: Mutation 5 — die Migrationsflagge wird wieder bedingungslos gesetzt**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && sed -i 's/^        if ( \$all_done ) {$/        if ( true ) {/' includes/class-naws-crypto.php && php tests/test-crypto.php; echo "exit=$?"
```

Erwartet: `FAIL` bei „und setzt die Flagge NICHT", `exit=1`.

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git checkout -- includes/class-naws-crypto.php && php tests/test-crypto.php > /dev/null && echo "alle fuenf gefangen, wieder gruen"
```

Sollte eine Mutation **nicht** gefangen werden, ist der Test unzureichend: Zusicherung ergänzen, committen, Probe wiederholen.

- [ ] **Step 7: Alle Tests und Lint unter der lokalen PHP 8.4**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && for t in tests/test-*.php; do printf "%-34s " "$(basename $t)"; php "$t" > /tmp/out.txt 2>&1 && echo OK || { echo FEHLER; cat /tmp/out.txt; }; done && find includes admin templates -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || echo "Lint sauber"
```

Erwartet: alle 14 Tests `OK`, `Lint sauber`.

- [ ] **Step 8: Auf Konstrukte prüfen, die unter PHP 8.5 anders sind als unter 8.4**

Die Zielinstallation läuft **8.5.8**, also neuer als die lokale 8.4.24 — ein lokal sauberer Lint beweist dort nichts. Ein echter 8.5-Lint ist erst nach der Übertragung möglich; was sich vorher prüfen lässt, ist das Vorkommen der Konstrukte, die 8.5 neu beanstandet. Das Projekt ist über genau eines schon gestolpert:

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git diff --name-only HEAD~7 -- '*.php' | grep -v '^tests/' | xargs grep -n "setAccessible\|create_function\|each(\|\${" 2>/dev/null; echo "--- keine Treffer oben = sauber ---"
```

Erwartet: keine Treffer. `setAccessible()` ist seit 8.5 als überflüssig markiert (die Testdateien dürfen es unter `PHP_VERSION_ID < 80100` weiter benutzen, deshalb sind sie ausgenommen), `${...}`-Interpolation in Strings ist seit 8.2 veraltet.

Der abschließende 8.5-Lint gehört in den Live-Patch und steht unter „Nach dem Plan".

- [ ] **Step 9: Changelog-Eintrag**

In `CHANGELOG.md` im bestehenden `## [Unreleased]`-Abschnitt unter `### Added` ergänzen:

```markdown
- **Verschlüsselung meldet sich, wenn sie nicht greift.** `NAWS_Crypto::encrypt()` gab bei einem Fehlschlag den Klartext zurück, damit die Zugangsdaten nicht verloren gehen — der Aufrufer konnte das an der Rückgabe nicht erkennen und schrieb ein unverschlüsseltes Geheimnis in `wp_options`, wovon nur `error_log()` erfuhr. Das war genau der eine Ausgang, den die Klasse verhindern soll: Ihr Schlüssel liegt in `wp-config.php`, ihr Chiffretext in der Datenbank, damit ein Dump allein nicht reicht.

  Ein fehlgeschlagenes Verschlüsseln schreibt jetzt gar nichts. Der bereits gespeicherte Wert bleibt unverändert, der übrige Speichervorgang läuft durch, und eine Meldung sagt, dass die Eingabe nicht übernommen wurde. Blockiert wird nichts — die Fähigkeitsprüfung ist eine Vorhersage und darf niemandem den Weg versperren, bei dem es in Wahrheit funktioniert hätte.

- **Das Backend zeigt den Zustand der Verschlüsselung**, auch wenn er in Ordnung ist. Eine eigene Kachel neben der Polling-Anzeige, und auf der Einstellungsseite ein Hinweis je Befund: fehlende openssl-Erweiterung, fehlendes aes-256-gcm, ein `AUTH_KEY`, der noch der Beispielwert aus `wp-config-sample.php` ist, und geänderte WordPress-Salts.

  Der Platzhalter war der unauffälligste der vier: `defined( 'AUTH_KEY' )` ist auch für ihn wahr, er nahm also nicht den Notweg, sondern den regulären — und leitete den Schlüssel aus einer Phrase ab, die in jedem WordPress-Archiv steht. Erkannt wird jetzt auch die *übersetzte* Phrase aus einer lokalisierten `wp-config-sample.php`, die lang genug ist, um sonst als echter Schlüssel durchzugehen.

- **Ein Fingerabdruck des Schlüssels** neben dem Chiffretext, damit ein Salt-Wechsel als solcher erkennbar ist. Werden die Salts erneuert, scheitert jede Entschlüsselung, und das Plugin hörte bisher ohne Erklärung auf zu arbeiten. Der Vergleich braucht keinen Chiffretext und steht deshalb in der Zustandsanzeige, nicht im Entschlüsseln. Wer erst nach einem Wechsel aktualisiert, hat keinen Abdruck zum Vergleichen — dann nennt die Meldung die Salts als *mögliche* Ursache und behauptet nichts.

- **`migrate()` setzt die Erledigt-Flagge nur noch, wenn sie stimmt.** Sie trug bisher die Versionsnummer, auch wenn kein einziges Feld verschlüsselt werden konnte.
```

- [ ] **Step 10: Abschluss-Commit**

```bash
cd "C:/Users/xyla1/.claude/Netatmo" && git add CHANGELOG.md && git commit -F- <<'MSG'
Write down what changed about the encryption

Five mutations checked one at a time, each caught by the tests: the
plaintext fallback returning, the length rule dropped, the phrase list
ignored, a missing fingerprint counted as a mismatch, and the migration
flag set unconditionally.

The third one matters most. The English placeholder is 27 characters and
the length rule catches it anyway, so a test that only used the original
would have passed for the wrong reason and the phrase list could have
been dead code.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
MSG
```

---

## Nach dem Plan

Nicht Teil der Tasks, aber vor dem Live-Einspielen zu bedenken:

- **Push** nach `origin/main` und Abgleich mit dem GitHub-Klon `C:\Users\xyla1\Documents\GitHub\Netatmo\`.
- **Live-Patch** über `novamira/create-upload-link` + `curl`, dann `patch -p1 --forward --dry-run` (Rückgabewert prüfen), echt anwenden, **`opcache_reset()`** aufrufen, Dateien gegen die lokalen Hashes stellen. `CHANGELOG.md` liegt nicht auf der Installation und muss aus dem Patch ausgeschlossen werden.
- **Zeilenenden:** Die Installation ist gemischt — 32 LF gegen 30 CRLF, LF genau bei den schon einmal gepatchten Dateien. `admin/views/settings.php` und `class-naws-database.php` gehören zu den CRLF-Dateien. Vor dem Patchen die Zieldateien prüfen und CRLF-Ziele einmal mit `str_replace("\r\n","\n")` normalisieren.
- **Live-Abnahme:** `NAWS_Crypto::health()` muss auf der Referenzinstallation `status => ok` und eine leere `issues`-Liste liefern; der Abdruck ist nach dem ersten Admin-Seitenaufruf nachgetragen. Die vier Geheimnisse müssen weiterhin das `naws_enc:`-Präfix tragen.
