# Krypto-Härtung: Fehlschläge sichtbar machen

**Datum:** 2026-08-21
**Betrifft:** `includes/class-naws-crypto.php` und ihre vier Aufrufer
**Auslöser:** Zwei Befunde einer externen Durchsicht, beide zutreffend, beide dieselbe Krankheit

---

## 1. Ausgangslage

`NAWS_Crypto` verschlüsselt vier Geheimnisse mit AES-256-GCM, bevor sie in `wp_options`
landen: Access-Token, Refresh-Token, `client_id` und `client_secret`. Der Schlüssel wird
per HKDF aus `AUTH_KEY` abgeleitet, und `AUTH_KEY` steht in `wp-config.php`.

Genau darin liegt der Sinn der Klasse: **Schlüssel und Daten liegen an verschiedenen
Orten.** Wer einen Datenbank-Dump, ein geteiltes Backup oder eine SQL-Injection hat,
aber nicht das Dateisystem, kommt an die Netatmo-Zugangsdaten nicht heran. Das ist ein
enges, aber reales Bedrohungsmodell, und es ist das einzige, das diese Klasse bedient.

Zwei Stellen heben dieses Modell auf, beide still:

**1. Der Klartext-Rückfall.** `encrypt()` gibt bei einem Fehlschlag von
`openssl_encrypt()` den Klartext zurück (Zeile 52–55), damit die Zugangsdaten nicht
verloren gehen. Der Aufrufer merkt davon nichts — die Rückgabe ist ein String wie jeder
andere — und schreibt das Geheimnis unverschlüsselt in die Datenbank. Bemerkt wird es
nur über `error_log()`, das auf den meisten Installationen niemand liest.

Der Zustand heilt nie: `get_option()` erkennt den Klartext beim nächsten Lesen als
Legacy-Wert, ruft `save_option()` → `encrypt()` auf, das scheitert wieder, und der
Klartext bleibt liegen. (Kein Schreibsturm — `update_option()` kürzt bei identischem
Wert ab — aber eben auch keine Selbstheilung.)

**2. Der schwache Schlüssel.** `derive_key()` (Zeile 262) fragt `defined( 'AUTH_KEY' )`.
Für den Platzhalter aus `wp-config-sample.php` ist das **true**. Der Platzhalter nimmt
also nicht den schwachen `DB_NAME`-Zweig, er nimmt den regulären — und leitet den
Schlüssel aus einem String ab, der in jedem WordPress-Tarball steht.

Das ist schlimmer als der `DB_NAME`-Rückfall, den die Durchsicht kritisiert hat:
`DB_NAME` ist pro Installation verschieden, der Platzhalter ist global identisch. Ein
einziger vorberechneter Schlüssel öffnet alle betroffenen Installationen.

Beide Male dasselbe Muster: **stille Degradierung unter Beibehaltung des Anscheins.**
Die Klasse sieht von außen unverändert aus, während sie nicht mehr tut, wofür es sie
gibt.

---

## 2. Was gemessen wurde

Auf der Referenzinstallation (WP 7.1, PHP 8.5.8), rein lesend:

| Prüfung | Ergebnis |
|---|---|
| `naws_access_token`, `naws_refresh_token` | beide mit `naws_enc:`-Präfix, kein Klartext |
| `settings.client_id`, `settings.client_secret` | beide mit `naws_enc:`-Präfix |
| `naws_rest_api` | Option existiert nicht, API abgeschaltet |
| `openssl` + `aes-256-gcm` | vorhanden |
| Rundlauf `encrypt()` / `decrypt()` | funktioniert |
| Alle acht Salts | 64 Zeichen, 44–49 verschiedene Zeichen, keine Dubletten, kein Platzhalter |
| `naws_crypto_migrated` | `1.9.6` |

**Der Rückfall hat auf dieser Installation nie gefeuert.** Das macht den Umbau
risikolos: Es gibt keinen Bestand im Klartext, der auf das alte Verhalten angewiesen
wäre. Es sagt aber nichts über fremde Hosts, und dorthin geht das Plugin.

**Zur Frage, ob der Core seine Platzhalter-Prüfung hergibt:** `wp_salt( 'auth' )` liefert
`AUTH_KEY . AUTH_SALT` (gemessen: 128 Zeichen) und ersetzt eine untaugliche Konstante
**still** durch einen generierten Wert, den es in `wp_options` ablegt. Damit taugt es
weder als Prüffrage — ob die eigene Konstante gut war, ist daraus nicht ablesbar — noch
als Schlüsselquelle: Der Ersatz läge in derselben Datenbank wie der Chiffretext und höbe
die Trennung auf, die der ganze Zweck ist. Die Bedingungen werden deshalb nachgebaut.

---

## 3. Entscheidungen

Vier Festlegungen, alle vom Auftraggeber bestätigt:

**E1 — Fehlgeschlagene Verschlüsselung schreibt nicht.** Das betroffene Feld wird
ausgelassen, der alte Wert in der Datenbank bleibt unberührt, eine Warnung erklärt es.
Der übrige Speichervorgang läuft normal durch. Kein Klartext gelangt je in die
Datenbank.

*Preis:* Auf einem Host mit kaputtem `openssl` lassen sich Zugangsdaten nicht eintragen.
Das Plugin ist dort unbenutzbar — aber es sagt einem das, statt Schutz vorzutäuschen.

**E2 — Nichts wird gesperrt, es wird gewarnt.** Der Verbinden-Knopf bleibt bedienbar,
auch wenn die Fähigkeitsprüfung fehlschlägt. Eine Warnung steht vorher sichtbar über dem
Verbinden-Bereich, eine zweite erklärt einen tatsächlichen Fehlschlag danach.

*Begründung:* Die Fähigkeitsprüfung ist eine Vorhersage. Sie darf niemandem den Weg
versperren, bei dem es in Wahrheit funktioniert hätte.

*Preis:* Ein vergeblicher OAuth-Umweg bleibt möglich, trotz Vorwarnung.

**E3 — Ein schwacher Schlüssel wird gewarnt, nicht verweigert.** Die Ableitung bleibt
unverändert, bestehende Chiffretexte bleiben lesbar, die Anzeige nennt den Zustand und
verweist auf den WordPress-Salt-Generator.

*Begründung:* Ein Verweigern wäre ehrlicher, würde aber auf einer laufenden Installation
mit Platzhalter-Salt das Speichern plötzlich abbrechen — eine Regression, die von außen
wie ein Plugin-Fehler aussieht.

**E4 — Der Zustand wird auch im Normalfall angezeigt.** Eine dauerhafte Kachel auf dem
Dashboard, nicht nur eine Meldung im Fehlerfall.

*Begründung:* Die ursprüngliche Beschwerde war die Stille. Eine sichtbare Zusicherung
ist deren Gegenteil, und im Supportfall ist der Zustand sofort ablesbar.

---

## 4. Architektur

Die Klasse bekommt eine Trennung, die sie heute nicht hat: **reine Prädikate gegen
WordPress-berührenden Code**, wie `NAWS_Climate` sie gegenüber `NAWS_Calc` schon
vormacht. Die reinen Funktionen sind ohne WordPress-Bootstrap testbar.

```php
// rein - kein get_option, keine Konstante, keine Uhr, kein __()
public static function weak_key_source( string $source, array $siblings, array $placeholders ): bool
public static function key_fingerprint( string $key ): string

// beruehrt WordPress
public static function health(): array
public static function encrypt( string $plaintext ): ?string   // war: string
```

### `weak_key_source( string $source, array $siblings, array $placeholders ): bool`

`true`, wenn eine der vier Bedingungen zutrifft:

1. leer
2. kürzer als 32 Zeichen
3. gleich einem Eintrag aus `$placeholders` — dort stehen der englische Platzhalter und
   dessen Übersetzung, denn eine lokalisierte `wp-config-sample.php` trägt den
   übersetzten Satz, und für eine deutsche Installation ist das der eigentlich
   relevante Fall
4. der Wert kommt in `$siblings` noch einmal vor (dieselbe Phrase in zwei Konstanten)

Die Vergleichsphrasen und die Geschwisterliste kommen als Parameter herein, damit die
Funktion rein bleibt — insbesondere ruft sie `__()` nicht selbst auf. Der Aufrufer
`health()` stellt beides aus den echten Konstanten und der Übersetzung zusammen.

### `key_fingerprint( string $key ): string`

`substr( hash_hmac( 'sha256', 'naws-keyfp-v1', $key ), 0, 16 )` — der Schlüssel ist der
HMAC-Schlüssel, nicht die Nachricht. Der Abdruck verrät damit nichts über den Schlüssel
und erlaubt trotzdem den Vergleich, ob es noch derselbe ist.

### `health(): array`

```php
[
  'status' => 'ok' | 'warning',
  'issues' => [ 'no_openssl', 'weak_key', ... ],   // nur Codes
]
```

Eine **Liste**, keine einzelne Meldung, weil vier unabhängige Zustände zusammenkommen
können. **Codes, keine fertigen Texte:** Die Übersetzung macht die Ansicht, nicht die
Klasse. Sonst hinge `NAWS_Crypto` — die über `migrate()` beim Plugin-Start läuft — an der
Ladereihenfolge von `NAWS_Lang`, und der Test bräuchte eine Übersetzungsattrappe für eine
Aussage, die mit Übersetzung nichts zu tun hat. Die Codes:

| Code | Bedingung | Aussage |
|---|---|---|
| `no_openssl` | `! extension_loaded( 'openssl' )` | Zugangsdaten können nicht verschlüsselt gespeichert werden |
| `no_gcm` | `aes-256-gcm` fehlt in `openssl_get_cipher_methods()` | dasselbe, anderer Grund |
| `weak_key` | `weak_key_source()` trifft auf `AUTH_KEY` zu — bei nicht definierter Konstante wird `''` übergeben, was die Leer-Bedingung auslöst und damit auch den `DB_NAME`-Rückfall erfasst | verschlüsselt, aber mit bekanntem Schlüssel; Salt-Generator verlinken |
| `key_changed` | gespeicherter Abdruck ≠ aktueller Abdruck | Salts wurden geändert, neu verbinden |

`openssl_get_cipher_methods()` baut ein Array mit über hundert Einträgen. `health()`
bekommt deshalb einen statischen Zwischenspeicher je Request, wie `catalogue()` seit dem
19.08.

### Zwei Nähte für die Testbarkeit

Ohne Verhaltensänderung in Produktion:

- `derive_key()` von `private` auf `protected` — eine Testunterklasse kann einen anderen
  Schlüssel liefern. Anders ist eine Salt-Rotation nicht simulierbar, weil Konstanten
  sich nicht neu definieren lassen.
- Der Cipher wird über `static::cipher()` statt `self::CIPHER` gelesen — eine
  Testunterklasse liefert einen unbekannten Cipher und erzeugt damit einen **echten**
  `openssl_encrypt()`-Fehlschlag statt einer nachgebauten Rückgabe.

Die Konstante `CIPHER` bleibt, `cipher()` gibt sie zurück.

---

## 5. Verhalten je Aufrufer

Vier Stellen rufen heute `encrypt()`. Alle prüfen künftig auf `null`.

### `class-naws-admin.php:99–110` — Einstellungsformular

```
sanitize() -> encrypt() -> null
  -> das Feld wird aus $clean ausgelassen
  -> der alte verschluesselte Wert bleibt in der DB stehen
  -> add_settings_error( 'naws', 'naws_crypto_failed', ... )
  -> Anzeige ueber die bestehende admin_notices()-Route
```

Die übrigen Felder sind zu diesem Zeitpunkt längst in `$clean` und werden normal
gespeichert. Kein neuer Mechanismus, nur die vorhandene Schiene.

### `class-naws-api.php:164–167` — Token nach dem OAuth-Tausch

Der heikelste Fall: Der Refresh-Token ist das langlebigste Geheimnis und nach dem Tausch
nicht wiederbeschaffbar. Nach E1 wird er **nicht** im Klartext abgelegt. Statt dessen
`error_log()` plus die Option `naws_crypto_write_failed` mit dem Unix-Zeitstempel des
Fehlschlags (`autoload = false`). Die Einstellungsseite übersetzt sie in eine Meldung:
Die Verbindung konnte nicht sicher gespeichert werden, Ursache siehe Warnungsliste.

Die Option wird gelöscht, sobald ein `encrypt()` wieder gelingt — sonst bliebe die
Meldung stehen, nachdem das Problem behoben ist. Sie ist bewusst getrennt von
`health()`: `health()` beschreibt den *aktuellen* Zustand der Umgebung,
`naws_crypto_write_failed` erinnert an ein *vergangenes* Ereignis, das `health()` nicht
mehr sehen kann.

Nach E2 bleibt der Verbinden-Knopf bedienbar; die Warnung steht schon vor dem Klick oben
auf der Seite.

### `class-naws-rest-api.php:464` — API-Schlüssel

Selbst erzeugt und trivial neu erzeugbar. `null` → nicht speichern, Meldung. Die Option
existiert auf der Referenzinstallation gar nicht.

### `NAWS_Crypto::save_option()` und `encrypt_fields()`

Reichen `null` durch, statt es zu verschlucken: `save_option()` schreibt bei `null` nicht
und meldet das per Rückgabewert `bool`; `encrypt_fields()` lässt das betroffene Feld
unverändert im Array stehen und ist damit für den Aufrufer erkennbar — der Wert trägt
kein `naws_enc:`-Präfix.

---

## 6. Anzeige

**Dashboard — dauerhafte Kachel** (E4), als Geschwister der bestehenden Health-Kachel und
im selben Markup: `naws-stat-card` mit
`naws-stat-icon-wrap naws-stat-color-{green|orange}`, Schloss-Symbol, Wert als Kurzform
des Zustands, Label über `naws_e()`. Bei Befunden wird sie orange und nennt den ersten;
die vollständige Liste steht auf der Einstellungsseite.

Die **Cron-Health-Kachel bleibt unangetastet.** Verschlüsselung hat mit Polling nichts zu
tun, und eine grüne Polling-Anzeige rot zu färben, weil `openssl` fehlt, macht beide
Aussagen unbrauchbar.

**Einstellungsseite — `notice notice-warning` nur bei Befund**, ein Absatz je Eintrag aus
`health()['issues']`, dort platziert, wo `token_revoked` und `not_connected_warn` schon
stehen. Dazu die anlassbezogene `add_settings_error()`-Meldung aus Abschnitt 5, die
`health()` nicht kennen kann: dass die gerade gemachte Eingabe nicht übernommen wurde.

---

## 7. Fingerabdruck

`AUTH_KEY` ist als Schlüsselquelle gut für die Vertraulichkeit und spröde für die
Verfügbarkeit. Werden die Salts gewechselt — Serverumzug, Sicherheitsvorfall, ein
Security-Plugin mit einem Knopf zum Erneuern der Salts —, ändert sich `derive_key()`,
jedes `openssl_decrypt()` scheitert am GCM-Tag, `decrypt()` liefert `''`, und die
Zugangsdaten sind lautlos weg. Von außen sieht das so aus, als hörte das Plugin ohne
Vorwarnung auf zu pollen.

**Der Vergleich gehört nicht in `decrypt()`.** `health()` kann jederzeit
`get_option( 'naws_crypto_keyfp' )` gegen `key_fingerprint( derive_key() )` stellen —
beide Seiten sind ohne jeden Chiffretext verfügbar. Damit braucht es keinen Merker, den
ein fehlgeschlagener Entschlüsselungsversuch setzt, und keine Annahme darüber, wer wann
zuerst läuft. **`decrypt()` bleibt unverändert.**

**Lebenszyklus des Abdrucks:**

- geschrieben in `migrate()`, wenn er fehlt — das ist die Nachrüstung für bestehende
  Installationen wie die Referenzinstallation, die heute verschlüsselte Werte, aber
  keinen Abdruck hat
- geschrieben nach jedem erfolgreichen `encrypt()` — deshalb verschwindet die Warnung
  nach einem Neuverbinden von allein: die neuen Werte tragen den neuen Schlüssel, der
  Abdruck zieht mit

**Grenze, die das Design nicht überspielen darf:** Wer erst *nach* einer Rotation
aktualisiert, hat keinen gespeicherten Abdruck, gegen den zu vergleichen wäre. Dann
bleibt die Ursache unbekannt, und die Meldung muss das sagen — nicht lesbare
Zugangsdaten, mögliche Ursache geänderte WordPress-Salts — statt einer Behauptung. Ein
Abdruck, der fehlt, ist kein Abdruck, der passt.

---

## 8. `migrate()`

Heute setzt `migrate()` `naws_crypto_migrated` auf die Version, **auch wenn kein einziges
Feld verschlüsselt werden konnte** — die Flagge sagt dann, es sei migriert, während alles
im Klartext steht. Künftig wird sie nur gesetzt, wenn jedes vorgesehene Feld tatsächlich
verschlüsselt vorliegt.

Die Folge ist beabsichtigt: Auf einem kaputten Host bleibt die Flagge ungesetzt, und der
Aufruf in `xtx-integration-for-netatmo.php:149` (`get_option( ... ) !== NAWS_VERSION`)
läuft bei jedem Admin-Seitenaufruf erneut. Das kostet ein paar `get_option` und zwei
sofort scheiternde `encrypt()`, schreibt unter E1 **nichts** in die Datenbank, und heilt
sich in der Sekunde selbst, in der `openssl` zurückkommt. Der Preis für eine ehrliche
Flagge ist ein billiger Wiederholungsversuch.

---

## 9. Sprachschlüssel

Rund zehn neue Schlüssel je Datei (`de.php`, `en.php`, `no.php`, aktuell je 694), für die
vier `health()`-Codes, die Dashboard-Kachel, die `add_settings_error()`-Meldungen und den
Hinweis auf den Salt-Generator. Alle drei Dateien müssen dieselbe Schlüsselzahl behalten.

**Eine Änderung am Prüf-Gate ist nötig und gemessen:** `weak_key_source()` vergleicht
gegen die **übersetzte** Platzhalterphrase, die in Cores Textdomain steht. Der Aufruf
`__( 'put your unique phrase here', 'default' )` meldet unter der aktuellen
`.phpcs.xml.dist` einen `WordPress.WP.I18n.TextDomainMismatch` — nachgemessen, und das
Gate steht auf null Fehler. Die Regel bekommt deshalb `default` als zweite erlaubte
Domain, mit Begründung im Kommentar: Hier wird bewusst ein Core-String in Cores Domain
nachgeschlagen, statt einen eigenen zu übersetzen. Ebenfalls nachgemessen: Mit der
Freigabe läuft die Datei fehlerfrei durch.

---

## 10. Tests

Neue Datei `tests/test-crypto.php` im Stil der dreizehn vorhandenen: eigenständig,
handgebaute WordPress-Attrappen, Aufruf `php tests/test-crypto.php`. Damit **14
Testdateien + `smoke-render-inline.php`** (nachgezählt, die frühere Notiz „zwölf" war
zu niedrig).

Abgedeckt:

1. `weak_key_source()` — Platzhalter englisch, Platzhalter übersetzt, leer, zu kurz,
   Dublette über zwei Konstanten, echter 64-Zeichen-Salt
2. `key_fingerprint()` — stabil für denselben Schlüssel, verschieden für verschiedene,
   und der Schlüssel selbst taucht im Abdruck nicht auf
3. Der **echte** Fehlschlagzweig über eine Unterklasse mit unbekanntem Cipher:
   `encrypt()` liefert `null`, nicht den Klartext
4. Rotation über zwei Unterklassen mit verschiedenen Schlüsseln: verschlüsseln mit A,
   `health()` unter B muss `key_changed` melden
5. `health()` meldet `ok` im Normalfall und sammelt mehrere Befunde gleichzeitig
6. `migrate()` setzt die Flagge bei Fehlschlag **nicht**, bei Erfolg schon
7. `encrypt()` → `null` → der Aufrufer lässt das Feld aus → der alte Wert steht
   unverändert in der Attrappen-Datenbank

**Mutationsproben** gehören dazu, jede einzeln geprüft. Zwei Regeln aus den Fehlern vom
19. und 20.08., die diesmal von vornherein gelten:

- **Erst committen, dann mutieren.** Beide Male ging uncommittete Arbeit durch ein
  `git checkout -- <datei>` verloren.
- **Exit-Code und Fatal-Error-Zeilen mitzählen**, nicht nur `FAIL`-Zeilen: Eine Mutation,
  die einen Fatal Error auslöst, liefert null FAIL-Zeilen und sieht wie „nicht gefangen"
  aus.

Zusätzlich `php -l` unter der lokalen 8.4 **und** auf der Installation unter 8.5.8, plus
PHPCS direkt auf die neue Testdatei — `tests/` ist in `.phpcs.xml.dist:32` vom Scan
ausgeschlossen, ein neuer Test taucht in der Dateizahl 52 nie auf.

---

## 11. Nicht Teil dieser Arbeit

- **Kein Versions-Bump, kein Tag, kein Release.** Es bleibt bei 1.9.6; der Eintrag geht
  in den bestehenden `## [Unreleased]`-Abschnitt.
- **Keine Änderung an `decrypt()`.** Der Rückgabewert `''` bei Fehlschlag bleibt: Er
  wählt bewusst Datenverlust statt falscher Daten und ist damit richtig. Neu ist nur,
  dass `health()` die Ursache erklären kann.
- **Kein Wechsel der Schlüsselquelle.** Weder auf `wp_salt()` — läge in der Datenbank —
  noch auf eine eigene Schlüsseldatei.
- **Keine Verschlüsselung weiterer Felder.** Der Umfang bleibt bei den vier Geheimnissen.

---

## 12. Ehrliche Grenzen

- Dass ein Angreifer mit Datenbankzugriff einen Platzhalter-Schlüssel nachrechnen kann,
  prüft kein Unit-Test. Der Test hält fest, dass die **Erkennung** anschlägt — nicht,
  dass die Warnung jemanden erreicht.
- Die Fähigkeitsprüfung ist eine Vorhersage. `extension_loaded( 'openssl' )` kann wahr
  sein und der konkrete Aufruf trotzdem scheitern, etwa im FIPS-Modus oder unter
  Richtlinien der Distribution. Deshalb E2: warnen, nicht sperren.
- Auf einem Host ohne `ext-openssl` ist `openssl_encrypt()` eine **undefinierte
  Funktion** — ein Fatal Error, bevor der `null`-Zweig überhaupt erreicht wird. Deshalb
  prüft `encrypt()` die Verfügbarkeit selbst und liefert `null`, statt sich darauf zu
  verlassen, dass die Anzeige vorher gewarnt hat.
- Die Warnung erreicht nur, wer wp-admin betritt. Für eine Installation, die niemand
  ansieht, ändert sich nichts.
