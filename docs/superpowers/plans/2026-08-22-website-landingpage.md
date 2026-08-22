# Website-Landingpage — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eine Landingpage auf der Wurzel-URL von `netatmo.frank-neumann.de`, die das Plugin für Laien verständlich vorstellt, sich beim Erscheinen neuer Versionen selbst aktualisiert, und für Suchmaschinen wie für Sprachmodelle vollständig erschlossen ist.

**Architecture:** Drei Lieferstücke in linearer Abhängigkeit. Ein eigenständiges WordPress-Plugin `xtx-netatmo-site` liest die GitHub-Releases-API und eine deutsche Textdatei aus dem Produkt-Repo, sortiert Einträge per Versionsvergleich in „in Arbeit" / „neu" / „Bestand" und stellt das als Shortcodes, JSON-LD und `llms.txt` bereit. Die Landingpage ist ein handgeschriebener HTML/CSS-Block im `post_content` von Seite 7, ausgeliefert über Elementors Canvas-Template. Das heutige Testbett zieht vorher auf `/live-demo/` um.

**Tech Stack:** PHP 8.0+ ohne Composer-Abhängigkeiten, WordPress 6.2+, eigenständige PHP-Testdateien nach dem Vorbild des Produkt-Plugins (`php tests/test-*.php`, Exit-Code), Novamira-MCP für Eingriffe auf der Installation, Chrome-MCP für Screenshots.

**Spec:** `docs/superpowers/specs/2026-08-22-website-landingpage-design.md`

## Global Constraints

Diese Vorgaben gelten für **jede** Aufgabe und werden nicht wiederholt.

- **PHP ≥ 8.0, WordPress ≥ 6.2.** Keine Composer-Abhängigkeit im ausgelieferten Code.
- **Kein Aufruf an `fonts.googleapis.com` oder `fonts.gstatic.com`** im HTML der Landingpage. Wird am Ende gegengeprüft.
- **Serverseitiges Rendern.** Kein Inhalt, der erst durch JavaScript entsteht. Was nicht im ausgelieferten HTML steht, sehen weder Google noch ein Sprachmodell.
- **Keine externen HTTP-Aufrufe außer diesen zwei:** `https://api.github.com/repos/Xyla1512/Netatmo/releases` und `https://raw.githubusercontent.com/Xyla1512/Netatmo/main/docs/site/website.de.json`.
- **Letzter guter Stand gewinnt.** Ein fehlgeschlagener Abruf darf nie zu leerem oder halbem Inhalt führen.
- **Sichtbarer Text ist Deutsch.** Code, Bezeichner und Kommentare sind Englisch, wie im Produkt-Plugin.
- **Ausgabe wird escaped.** `esc_html()`, `esc_url()`, `esc_attr()` an jeder Stelle, an der Fremddaten in HTML landen — die Daten kommen von GitHub, nicht aus dem eigenen Haus.
- **Farbanker `#2d5252`**, das Petrol der Plugin-Kopfleisten.
- **Kein Screenshot mit Secret, API-Schlüssel oder Modul-MAC** wird hochgeladen.
- **Genau eine H1** auf der Landingpage.
- **Präfix `XNS_`** für alle Klassen des Begleit-Plugins, `xns_`/`naws_site_` für Optionen und Shortcodes.
- Commit-Nachrichten in der Sprache und dem Stil des Produkt-Repos: Aussagesatz, kein `feat:`-Präfix, mit `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.

---

# Phase A — Begleit-Plugin `xtx-netatmo-site`

Arbeitsverzeichnis: `C:\Users\xyla1\.claude\NetatmoSite\`, eigenes Git-Repo, getrennt vom Produkt-Plugin.

## File Structure

| Datei | Verantwortung |
|---|---|
| `xtx-netatmo-site.php` | Plugin-Kopf, Konstanten, Einbinden, Hooks. Sonst nichts. |
| `includes/class-xns-roadmap.php` | Die Beförderungsregel. Rein, kein WordPress. |
| `includes/class-xns-github.php` | Antwort der Releases-API in eine flache Struktur überführen. Rein. |
| `includes/class-xns-content.php` | `website.de.json` prüfen und normalisieren. Rein. |
| `includes/class-xns-store.php` | Option-gestützter Speicher mit letztem gutem Stand. |
| `includes/class-xns-fetch.php` | HTTP-Abruf und Cron-Ereignis. Der einzige Ort mit Netzzugriff. |
| `includes/class-xns-shortcodes.php` | Die fünf Shortcodes. |
| `includes/class-xns-schema.php` | `SoftwareApplication` und `HowTo` als JSON-LD. |
| `includes/class-xns-files.php` | `llms.txt` und `robots.txt` über Rewrite-Regeln. |
| `assets/site.css` | CSS des gemeinsamen Headers. |
| `tests/test-*.php` | Je eine Datei pro reiner Einheit. |

Trennung nach Verantwortung, nicht nach Schicht: **`class-xns-fetch.php` ist der einzige Ort, der das Netz anfasst.** Alles andere ist rein und ohne WordPress testbar — genau das macht die Beförderungsregel prüfbar, ohne je GitHub zu befragen.

---

### Task 1: Die Beförderungsregel

Das Herzstück. Sie entscheidet, ob ein Eintrag unter „woran gerade gearbeitet wird" oder unter „neu in Version X" steht. Rein, ohne WordPress, ohne Netz — deshalb zuerst.

**Files:**
- Create: `C:\Users\xyla1\.claude\NetatmoSite\includes\class-xns-roadmap.php`
- Create: `C:\Users\xyla1\.claude\NetatmoSite\tests\test-roadmap.php`
- Create: `C:\Users\xyla1\.claude\NetatmoSite\xtx-netatmo-site.php`
- Create: `C:\Users\xyla1\.claude\NetatmoSite\.gitignore`

**Interfaces:**
- Consumes: nichts.
- Produces:
  - `XNS_Roadmap::base_version( string $version ): string`
  - `XNS_Roadmap::classify( array $vorhaben, string $stable, ?string $prerelease = null ): array` — liefert `['arbeit' => array, 'neu' => array, 'bestand' => array]`, jeder Eintrag zusätzlich mit Schlüssel `beta` (bool).

**Der Fallstrick, um den es hier geht:** `version_compare( '1.9.7-beta.1', '1.9.7', '>=' )` ist **falsch** — eine Beta rangiert unter ihrer eigenen Endfassung. Ein Eintrag mit `ab: "1.9.7"` steckt aber sehr wohl schon in `1.9.7-beta.1`. Deshalb wird für das Beta-Abzeichen nicht die Beta-Version verglichen, sondern ihre **Grundversion** ohne Suffix. Genau dafür existiert `base_version()`.

- [ ] **Step 1: Projekt anlegen**

```bash
mkdir -p "/c/Users/xyla1/.claude/NetatmoSite/includes" "/c/Users/xyla1/.claude/NetatmoSite/tests" "/c/Users/xyla1/.claude/NetatmoSite/assets"
cd "/c/Users/xyla1/.claude/NetatmoSite"
git init
printf 'vendor/\n*.zip\n.DS_Store\nThumbs.db\n' > .gitignore
```

- [ ] **Step 2: Plugin-Kopf schreiben**

`xtx-netatmo-site.php` — trägt in dieser Aufgabe nur den Kopf und das Einbinden. Hooks kommen in Task 5.

```php
<?php
/**
 * Plugin Name: XTX Netatmo – Website-Bausteine
 * Description: Liefert der Website netatmo.frank-neumann.de die Bausteine, die sich aus dem Produkt-Repository speisen: Fahrplan, Neuerungen, Fakten, strukturierte Daten.
 * Version: 0.1.0
 * Author: Frank Neumann
 * License: GPL v2 or later
 * Requires at least: 6.2
 * Requires PHP: 8.0
 *
 * @package XNS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'XNS_VERSION',     '0.1.0' );
define( 'XNS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'XNS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'XNS_REPO',        'Xyla1512/Netatmo' );

require_once XNS_PLUGIN_DIR . 'includes/class-xns-roadmap.php';
```

- [ ] **Step 3: Die fehlschlagende Testdatei schreiben**

`tests/test-roadmap.php` — vollständig, nicht als Skizze:

```php
<?php
/**
 * Tests for XNS_Roadmap — the rule that decides where an entry appears.
 *
 * Pure arithmetic over version strings: no WordPress, no network, no clock.
 * What it guards:
 *
 *   base_version()  – strips a pre-release suffix, so that 1.9.7-beta.1 can be
 *                     recognised as carrying everything marked for 1.9.7.
 *   classify()      – sorts entries into "being worked on", "new in X" and
 *                     "existing", and flags those already testable in a beta.
 *
 *   php tests/test-roadmap.php
 *
 * @package XNS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-xns-roadmap.php';

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

/** Sammelt die IDs eines Eimers, damit sich Zuordnungen knapp prüfen lassen. */
function ids( array $bucket ): array {
    return array_map( static fn( $i ) => $i['id'], $bucket );
}

echo "\nXNS_Roadmap::base_version()\n" . str_repeat( '-', 74 ) . "\n";

check( 'Beta-Suffix faellt weg',   XNS_Roadmap::base_version( '1.9.7-beta.1' ), '1.9.7' );
check( 'rc-Suffix faellt weg',     XNS_Roadmap::base_version( '2.0.0-rc.2' ),   '2.0.0' );
check( 'Build-Suffix faellt weg',  XNS_Roadmap::base_version( '1.9.7+build5' ), '1.9.7' );
check( 'v-Praefix faellt weg',     XNS_Roadmap::base_version( 'v1.9.6' ),       '1.9.6' );
check( 'reine Version bleibt',     XNS_Roadmap::base_version( '1.9.6' ),        '1.9.6' );
check( 'Leerraum wird getrimmt',   XNS_Roadmap::base_version( '  1.9.6 ' ),     '1.9.6' );

echo "\nXNS_Roadmap::classify() – Grundzuordnung\n" . str_repeat( '-', 74 ) . "\n";

$vorhaben = [
    [ 'id' => 'ohne-version', 'ab' => null ],
    [ 'id' => 'kommt-noch',   'ab' => '1.9.7' ],
    [ 'id' => 'gerade-raus',  'ab' => '1.9.6' ],
    [ 'id' => 'laengst-drin', 'ab' => '1.9.0' ],
];

$r = XNS_Roadmap::classify( $vorhaben, '1.9.6' );

check( 'ohne Version zaehlt als in Arbeit', ids( $r['arbeit'] ),   [ 'ohne-version', 'kommt-noch' ] );
check( 'gleiche Version ist neu',           ids( $r['neu'] ),      [ 'gerade-raus' ] );
check( 'aeltere Version ist Bestand',       ids( $r['bestand'] ),  [ 'laengst-drin' ] );

echo "\nXNS_Roadmap::classify() – das Beta-Abzeichen\n" . str_repeat( '-', 74 ) . "\n";

// Der eigentliche Punkt: 1.9.7-beta.1 traegt alles, was fuer 1.9.7 vorgesehen
// ist. version_compare() allein sagt das Gegenteil, weil eine Beta unter ihrer
// Endfassung rangiert - deshalb wird die Grundversion verglichen.
$r = XNS_Roadmap::classify( $vorhaben, '1.9.6', '1.9.7-beta.1' );
$arbeit = [];
foreach ( $r['arbeit'] as $i ) {
    $arbeit[ $i['id'] ] = $i['beta'];
}

check( '1.9.7 ist in der 1.9.7-Beta testbar', $arbeit['kommt-noch'],  true );
check( 'ohne Zielversion kein Abzeichen',     $arbeit['ohne-version'], false );

// Eine Beta einer aelteren Reihe traegt ein spaeteres Vorhaben nicht.
$spaeter = [ [ 'id' => 'spaeter', 'ab' => '1.9.8' ] ];
$r = XNS_Roadmap::classify( $spaeter, '1.9.6', '1.9.7-beta.1' );
check( '1.9.8 steckt nicht in der 1.9.7-Beta', $r['arbeit'][0]['beta'], false );

// Ohne Pre-Release gibt es nie ein Abzeichen.
$r = XNS_Roadmap::classify( $vorhaben, '1.9.6', null );
foreach ( $r['arbeit'] as $i ) {
    check( 'ohne Pre-Release kein Abzeichen: ' . $i['id'], $i['beta'], false );
}

echo "\nXNS_Roadmap::classify() – nach dem stabilen Release\n" . str_repeat( '-', 74 ) . "\n";

// Regression: sobald 1.9.7 stabil erscheint, muss der Eintrag von selbst
// umziehen - ohne dass jemand die Datei anfasst. Das ist der ganze Zweck.
$r = XNS_Roadmap::classify( $vorhaben, '1.9.7', '1.9.7-beta.1' );

check( 'kommt-noch ist jetzt neu',        ids( $r['neu'] ),     [ 'kommt-noch' ] );
check( 'gerade-raus faellt in Bestand',   ids( $r['bestand'] ), [ 'gerade-raus', 'laengst-drin' ] );
check( 'nur noch das Versionslose offen', ids( $r['arbeit'] ),  [ 'ohne-version' ] );
check( 'kein Abzeichen mehr noetig',      $r['neu'][0]['beta'], false );

echo "\nXNS_Roadmap::classify() – Randfaelle\n" . str_repeat( '-', 74 ) . "\n";

check( 'leere Liste ergibt leere Eimer', XNS_Roadmap::classify( [], '1.9.6' ), [ 'arbeit' => [], 'neu' => [], 'bestand' => [] ] );

$leer = XNS_Roadmap::classify( [ [ 'id' => 'leerstring', 'ab' => '' ] ], '1.9.6' );
check( 'leerer ab-Wert zaehlt wie null', ids( $leer['arbeit'] ), [ 'leerstring' ] );

$fehlt = XNS_Roadmap::classify( [ [ 'id' => 'kein-ab-feld' ] ], '1.9.6' );
check( 'fehlendes ab-Feld zaehlt wie null', ids( $fehlt['arbeit'] ), [ 'kein-ab-feld' ] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 4: Test laufen lassen und Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-roadmap.php
```

Erwartet: Abbruch mit `Failed opening required .../class-xns-roadmap.php`. Wenn der Test **besteht**, stimmt etwas nicht — dann steht die Datei schon.

- [ ] **Step 5: Die Klasse schreiben**

`includes/class-xns-roadmap.php`:

```php
<?php
/**
 * Where an entry belongs: still being worked on, new in the current release,
 * or long since part of the plugin.
 *
 * Pure arithmetic over version strings. No WordPress, no network, no clock —
 * which is what makes the rule testable without ever asking GitHub.
 *
 * @package XNS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class XNS_Roadmap {

    /**
     * The version without its pre-release or build suffix.
     *
     * 1.9.7-beta.1 becomes 1.9.7. This exists for one reason: version_compare()
     * ranks a beta BELOW its own final version, which is correct for update
     * checks and wrong for the question asked here. An entry marked for 1.9.7
     * is in fact already shipping inside 1.9.7-beta.1, and comparing the raw
     * strings would deny it.
     */
    public static function base_version( string $version ): string {
        $v = ltrim( trim( $version ), 'vV' );
        return substr( $v, 0, strcspn( $v, '-+' ) );
    }

    /**
     * Sorts entries into three buckets and flags those already testable.
     *
     * @param array       $vorhaben   Entries; each may carry 'ab' => version string or null.
     * @param string      $stable     Newest stable release, e.g. '1.9.6'.
     * @param string|null $prerelease Newest pre-release, e.g. '1.9.7-beta.1', or null.
     * @return array{arbeit:array,neu:array,bestand:array}
     */
    public static function classify( array $vorhaben, string $stable, ?string $prerelease = null ): array {
        $out       = [ 'arbeit' => [], 'neu' => [], 'bestand' => [] ];
        $stable    = self::base_version( $stable );
        $beta_base = ( null === $prerelease || '' === $prerelease ) ? null : self::base_version( $prerelease );

        foreach ( $vorhaben as $item ) {
            $ab = $item['ab'] ?? null;

            if ( null === $ab || '' === $ab ) {
                $item['beta']     = false;
                $out['arbeit'][]  = $item;
                continue;
            }

            $ab = self::base_version( (string) $ab );

            if ( version_compare( $ab, $stable, '>' ) ) {
                $item['beta']    = ( null !== $beta_base && version_compare( $beta_base, $ab, '>=' ) );
                $out['arbeit'][] = $item;
            } elseif ( version_compare( $ab, $stable, '==' ) ) {
                $item['beta']  = false;
                $out['neu'][]  = $item;
            } else {
                $item['beta']     = false;
                $out['bestand'][] = $item;
            }
        }

        return $out;
    }
}
```

- [ ] **Step 6: Test laufen lassen und Bestehen bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-roadmap.php
```

Erwartet: `21 bestanden, 0 fehlgeschlagen`, Exit-Code 0.

- [ ] **Step 7: Commit**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
git add .
git commit -m "$(cat <<'EOF'
Decide where an entry belongs by comparing versions, not by hand

A beta ranks below its own final version, which is right for update
checks and wrong here: an entry marked for 1.9.7 is already shipping
inside 1.9.7-beta.1. base_version() strips the suffix so the comparison
answers the question actually being asked.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Die Releases-Antwort auswerten

**Files:**
- Create: `includes/class-xns-github.php`
- Create: `tests/fixtures/releases.json` — echte Antwort der API, einmal abgeholt und eingefroren
- Create: `tests/test-github.php`

**Interfaces:**
- Consumes: `XNS_Roadmap::base_version()`, `XNS_Roadmap::base_version_keep_suffix()` — letztere wird in **Step 2 dieser Aufgabe** ergänzt, bevor sie benutzt wird.
- Produces: `XNS_Github::parse_releases( array $releases ): array` — liefert
  `['stable' => ['version','datum','zip_url','zip_size'], 'beta' => ['version','datum','zip_url']|null]`.

- [ ] **Step 1: Echte Antwort einfrieren**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
mkdir -p tests/fixtures
curl -s "https://api.github.com/repos/Xyla1512/Netatmo/releases?per_page=10" -o tests/fixtures/releases.json
php -r '$d=json_decode(file_get_contents("tests/fixtures/releases.json"),true); printf("%d Releases, neuestes: %s (prerelease=%s)\n", count($d), $d[0]["tag_name"], var_export($d[0]["prerelease"],true));'
```

Erwartet: mindestens zwei Releases, das neueste `v1.9.7-beta.1` mit `prerelease=true`.

- [ ] **Step 2: Die Hilfsfunktion in `XNS_Roadmap` ergänzen, bevor sie gebraucht wird**

In `includes/class-xns-roadmap.php`, direkt vor `base_version()`:

```php
    /**
     * The tag without its leading "v", suffix intact.
     *
     * v1.9.7-beta.1 becomes 1.9.7-beta.1. Distinct from base_version(), which
     * also drops the suffix: this one is for display and for version_compare()
     * against installed versions, that one for asking which release line an
     * entry belongs to.
     */
    public static function base_version_keep_suffix( string $tag ): string {
        return ltrim( trim( $tag ), 'vV' );
    }
```

Und in `tests/test-roadmap.php` vor der Schlusszeile ergänzen:

```php
echo "\nXNS_Roadmap::base_version_keep_suffix()\n" . str_repeat( '-', 74 ) . "\n";

check( 'v faellt weg, Suffix bleibt', XNS_Roadmap::base_version_keep_suffix( 'v1.9.7-beta.1' ), '1.9.7-beta.1' );
check( 'ohne v unveraendert',         XNS_Roadmap::base_version_keep_suffix( '1.9.6' ),         '1.9.6' );
```

Prüfen: `php tests/test-roadmap.php` muss jetzt `23 bestanden` melden.

- [ ] **Step 3: Testdatei schreiben**

`tests/test-github.php`:

```php
<?php
/**
 * Tests for XNS_Github::parse_releases().
 *
 * Runs against a frozen copy of the real API answer, so the test says
 * something about the shape GitHub actually sends rather than the shape
 * assumed while writing the parser.
 *
 *   php tests/test-github.php
 *
 * @package XNS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-xns-roadmap.php';
require_once __DIR__ . '/../includes/class-xns-github.php';

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

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/releases.json' ), true );

echo "\nXNS_Github::parse_releases() – echte Antwort\n" . str_repeat( '-', 74 ) . "\n";

$r = XNS_Github::parse_releases( $fixture );

check( 'stabil ist nicht die Beta',      $r['stable']['version'], '1.9.6' );
check( 'Beta wird erkannt',              $r['beta']['version'],   '1.9.7-beta.1' );
check( 'v-Praefix ist entfernt',         str_starts_with( $r['stable']['version'], 'v' ), false );
check( 'ZIP-Anhang statt Quellarchiv',   str_ends_with( $r['stable']['zip_url'], '.zip' ), true );
check( 'Quellarchiv wird nicht genommen', str_contains( $r['stable']['zip_url'], 'zipball' ), false );
check( 'Groesse ist eine Zahl',          is_int( $r['stable']['zip_size'] ), true );
check( 'Groesse ist plausibel',          $r['stable']['zip_size'] > 100000, true );
check( 'Datum im ISO-Format',            (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $r['stable']['datum'] ), true );

echo "\nXNS_Github::parse_releases() – Randfaelle\n" . str_repeat( '-', 74 ) . "\n";

check( 'leere Antwort ergibt null', XNS_Github::parse_releases( [] ), [ 'stable' => null, 'beta' => null ] );

// Nur Betas, kein stabiles Release: darf nicht behaupten, es gaebe eines.
$nur_beta = [ [ 'tag_name' => 'v2.0.0-beta.1', 'prerelease' => true, 'draft' => false, 'published_at' => '2026-08-22T10:00:00Z', 'assets' => [] ] ];
$r = XNS_Github::parse_releases( $nur_beta );
check( 'ohne stabiles Release bleibt stable null', $r['stable'], null );
check( 'die Beta wird trotzdem erkannt',           $r['beta']['version'], '2.0.0-beta.1' );

// Entwuerfe zaehlen nicht.
$entwurf = [ [ 'tag_name' => 'v9.9.9', 'prerelease' => false, 'draft' => true, 'published_at' => '2026-08-22T10:00:00Z', 'assets' => [] ] ];
check( 'Entwuerfe werden uebergangen', XNS_Github::parse_releases( $entwurf )['stable'], null );

// Release ohne Anhang: kein Download-Link, aber auch kein Absturz.
$ohne_asset = [ [ 'tag_name' => 'v1.0.0', 'prerelease' => false, 'draft' => false, 'published_at' => '2026-01-01T10:00:00Z', 'assets' => [] ] ];
$r = XNS_Github::parse_releases( $ohne_asset );
check( 'ohne Anhang kein ZIP-Link', $r['stable']['zip_url'], '' );
check( 'ohne Anhang Groesse null',  $r['stable']['zip_size'], 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 4: Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-github.php
```

Erwartet: Abbruch, `class-xns-github.php` fehlt.

- [ ] **Step 5: Die Klasse schreiben**

```php
<?php
/**
 * Reads the GitHub releases answer down to the handful of facts the website
 * shows: which version is current, when it appeared, where its ZIP is, and
 * whether a pre-release is available for testing.
 *
 * Pure: the answer comes in as an array, the facts come out. The fetching
 * lives in XNS_Fetch.
 *
 * @package XNS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class XNS_Github {

    /**
     * @param array $releases Decoded answer of /repos/{owner}/{repo}/releases.
     * @return array{stable:?array,beta:?array}
     */
    public static function parse_releases( array $releases ): array {
        $out = [ 'stable' => null, 'beta' => null ];

        foreach ( $releases as $r ) {
            if ( ! empty( $r['draft'] ) ) {
                continue;
            }

            $entry = [
                'version'  => XNS_Roadmap::base_version_keep_suffix( (string) ( $r['tag_name'] ?? '' ) ),
                'datum'    => substr( (string) ( $r['published_at'] ?? '' ), 0, 10 ),
                'zip_url'  => '',
                'zip_size' => 0,
            ];

            // Nur der angehaengte Bauartefakt zaehlt. GitHubs Quellarchive
            // entpacken nach Netatmo-<tag>/ und wuerden ein zweites Plugin
            // installieren statt das vorhandene zu aktualisieren.
            foreach ( (array) ( $r['assets'] ?? [] ) as $a ) {
                if ( str_ends_with( (string) ( $a['name'] ?? '' ), '.zip' ) ) {
                    $entry['zip_url']  = (string) ( $a['browser_download_url'] ?? '' );
                    $entry['zip_size'] = (int) ( $a['size'] ?? 0 );
                    break;
                }
            }

            if ( ! empty( $r['prerelease'] ) ) {
                if ( null === $out['beta'] ) {
                    $out['beta'] = $entry;
                }
                continue;
            }

            if ( null === $out['stable'] ) {
                $out['stable'] = $entry;
            }
        }

        return $out;
    }
}
```

`base_version_keep_suffix()` steht bereits aus Step 2 zur Verfügung — sie streift nur das `v` ab und behält das Suffix, damit `1.9.7-beta.1` als Beta erkennbar bleibt.

- [ ] **Step 6: Beide Tests laufen lassen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-roadmap.php && php tests/test-github.php
```

Erwartet: beide bestehen, Exit-Code 0.

- [ ] **Step 7: Commit**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
git add .
git commit -m "$(cat <<'EOF'
Read the releases answer down to the facts the page shows

Tested against a frozen copy of the real API response rather than an
imagined one. Only the attached build artifact counts as the download:
GitHub's own source archives unpack to Netatmo-<tag>/ and would install
a second plugin beside the existing one.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Die Inhaltsdatei prüfen und normalisieren

**Files:**
- Create: `includes/class-xns-content.php`
- Create: `tests/test-content.php`
- Create im **Produkt-Repo**: `C:\Users\xyla1\.claude\Netatmo\docs\site\website.de.json`

**Interfaces:**
- Consumes: nichts.
- Produces: `XNS_Content::parse( $raw ): array` — nimmt den rohen JSON-Text und liefert eine bereinigte Liste von Vorhaben. Ungültige Einträge werden **verworfen, nicht repariert**.

**Warum streng:** die Datei kommt über HTTP von einem fremden Host. Sie wird behandelt wie jede Fremdeingabe — was nicht dem Format entspricht, fliegt raus, statt später halb gerendert in der Seite zu stehen.

- [ ] **Step 1: Die Inhaltsdatei im Produkt-Repo anlegen**

`C:\Users\xyla1\.claude\Netatmo\docs\site\website.de.json` — mit echten Einträgen aus dem aktuellen Stand:

```json
{
  "aktualisiert": "2026-08-22",
  "vorhaben": [
    {
      "id": "naws-calc",
      "titel": "Ein Kurzbefehl für 27 berechnete Werte",
      "satz": "Taupunkt, gefühlte Temperatur, Frost- und Sommertage, Wärmesummen und der Dürreindex SPI — alle über einen einzigen Shortcode, ohne dass eine Zahl von Hand gerechnet werden muss.",
      "ab": "1.9.7",
      "bild": "calc-tabelle"
    },
    {
      "id": "api-retry",
      "titel": "Kurze Störungen bei Netatmo kosten keine Messung mehr",
      "satz": "Meldet der Netatmo-Dienst eine vorübergehende Überlastung, wartet das Plugin genau so lange, wie der Server verlangt, und fragt erneut — statt eine Lücke in die Messreihe zu schreiben.",
      "ab": "1.9.7",
      "bild": null
    },
    {
      "id": "krypto-sichtbar",
      "titel": "Die Verschlüsselung sagt Bescheid, wenn sie nicht arbeitet",
      "satz": "Ein neuer Statusbereich zeigt, ob die Zugangsdaten wirklich verschlüsselt liegen — und benennt die vier Ursachen, aus denen das schiefgehen kann.",
      "ab": "1.9.7",
      "bild": null
    },
    {
      "id": "kopfleiste-schrift",
      "titel": "Kopfleisten-Farbe und Schriftart sind Einstellungen",
      "satz": "Die dunklen Kopfleisten und die verwendete Schrift lassen sich im Erscheinungsbild wählen, statt im Stylesheet gesucht werden zu müssen.",
      "ab": "1.9.7",
      "bild": "erscheinungsbild"
    }
  ]
}
```

- [ ] **Step 2: Testdatei schreiben**

`tests/test-content.php`:

```php
<?php
/**
 * Tests for XNS_Content::parse().
 *
 * The file arrives over HTTP from a foreign host, so it is treated as foreign
 * input: what does not match the format is dropped, never repaired. A half
 * valid entry rendered into the page is worse than a missing one.
 *
 *   php tests/test-content.php
 *
 * @package XNS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-xns-content.php';

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

echo "\nXNS_Content::parse() – gueltige Datei\n" . str_repeat( '-', 74 ) . "\n";

$gut = json_encode( [
    'aktualisiert' => '2026-08-22',
    'vorhaben'     => [
        [ 'id' => 'a', 'titel' => 'Titel A', 'satz' => 'Satz A.', 'ab' => '1.9.7', 'bild' => 'bild-a' ],
        [ 'id' => 'b', 'titel' => 'Titel B', 'satz' => 'Satz B.', 'ab' => null,    'bild' => null ],
    ],
] );

$r = XNS_Content::parse( $gut );

check( 'beide Eintraege kommen an', count( $r ), 2 );
check( 'ID bleibt',                 $r[0]['id'], 'a' );
check( 'Titel bleibt',              $r[0]['titel'], 'Titel A' );
check( 'ab bleibt',                 $r[0]['ab'], '1.9.7' );
check( 'null bleibt null',          $r[1]['ab'], null );
check( 'bild bleibt',               $r[0]['bild'], 'bild-a' );

echo "\nXNS_Content::parse() – wird verworfen\n" . str_repeat( '-', 74 ) . "\n";

check( 'kaputtes JSON ergibt leer',   XNS_Content::parse( '{nicht wirklich json' ), [] );
check( 'leerer Text ergibt leer',     XNS_Content::parse( '' ), [] );
check( 'JSON ohne vorhaben ist leer', XNS_Content::parse( '{"aktualisiert":"2026-08-22"}' ), [] );
check( 'Liste statt Objekt ist leer', XNS_Content::parse( '[1,2,3]' ), [] );

$luecken = json_encode( [ 'vorhaben' => [
    [ 'id' => 'ok',        'titel' => 'T', 'satz' => 'S' ],
    [ 'titel' => 'ohne id', 'satz' => 'S' ],
    [ 'id' => 'ohne-titel', 'satz' => 'S' ],
    [ 'id' => 'ohne-satz',  'titel' => 'T' ],
    'gar kein Objekt',
] ] );
$r = XNS_Content::parse( $luecken );
check( 'nur der vollstaendige Eintrag bleibt', count( $r ), 1 );
check( 'und zwar der richtige',                $r[0]['id'], 'ok' );

echo "\nXNS_Content::parse() – Fremdeingabe wird entschaerft\n" . str_repeat( '-', 74 ) . "\n";

$boese = json_encode( [ 'vorhaben' => [
    [ 'id' => 'x<script>', 'titel' => '<script>alert(1)</script>', 'satz' => 'Harmlos.' ],
] ] );
$r = XNS_Content::parse( $boese );
check( 'ID wird auf Slug-Zeichen beschraenkt', $r[0]['id'], 'xscript' );
check( 'Markup im Titel faellt weg',           $r[0]['titel'], 'alert(1)' );

$lang = json_encode( [ 'vorhaben' => [
    [ 'id' => 'lang', 'titel' => str_repeat( 'A', 300 ), 'satz' => str_repeat( 'B', 2000 ) ],
] ] );
$r = XNS_Content::parse( $lang );
check( 'Titel wird gedeckelt', strlen( $r[0]['titel'] ) <= 120, true );
check( 'Satz wird gedeckelt',  strlen( $r[0]['satz'] ) <= 400, true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 3: Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-content.php
```

- [ ] **Step 4: Die Klasse schreiben**

```php
<?php
/**
 * Validates the German text file that feeds the roadmap and the release notes.
 *
 * The file arrives over HTTP from raw.githubusercontent.com. That makes it
 * foreign input, and it is treated as such: entries that do not match the
 * format are dropped rather than repaired, because a half-rendered entry on
 * the page is worse than a missing one.
 *
 * @package XNS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class XNS_Content {

    private const MAX_TITEL = 120;
    private const MAX_SATZ  = 400;

    /**
     * @param string $raw Raw JSON text.
     * @return array List of clean entries; empty when nothing survives.
     */
    public static function parse( $raw ): array {
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            return [];
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) || ! isset( $data['vorhaben'] ) || ! is_array( $data['vorhaben'] ) ) {
            return [];
        }

        $out = [];
        foreach ( $data['vorhaben'] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $id    = self::slug( (string) ( $item['id'] ?? '' ) );
            $titel = self::plain( (string) ( $item['titel'] ?? '' ), self::MAX_TITEL );
            $satz  = self::plain( (string) ( $item['satz'] ?? '' ), self::MAX_SATZ );

            if ( '' === $id || '' === $titel || '' === $satz ) {
                continue;
            }

            $ab = $item['ab'] ?? null;
            $ab = ( is_string( $ab ) && '' !== $ab ) ? preg_replace( '/[^0-9A-Za-z.\-+]/', '', $ab ) : null;

            $bild = $item['bild'] ?? null;
            $bild = is_string( $bild ) ? ( self::slug( $bild ) ?: null ) : null;

            $out[] = [
                'id'    => $id,
                'titel' => $titel,
                'satz'  => $satz,
                'ab'    => $ab,
                'bild'  => $bild,
            ];
        }

        return $out;
    }

    /** Slug characters only — the id ends up in an HTML id attribute. */
    private static function slug( string $v ): string {
        return substr( preg_replace( '/[^a-z0-9\-_]/', '', strtolower( trim( $v ) ) ), 0, 64 );
    }

    /** Markup out, length capped. The page decides how text looks, not the file. */
    private static function plain( string $v, int $max ): string {
        $v = trim( preg_replace( '/\s+/u', ' ', strip_tags( $v ) ) );
        return mb_substr( $v, 0, $max );
    }
}
```

- [ ] **Step 5: Test laufen lassen, Bestehen bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-content.php
```

- [ ] **Step 6: Inhaltsdatei im Produkt-Repo committen**

```bash
cd "/c/Users/xyla1/.claude/Netatmo"
git add docs/site/website.de.json
git commit -m "$(cat <<'EOF'
Say in German what the website should show about work in progress

The changelog is English and stays that way; this file carries the one
or two German sentences per undertaking that the site renders. The `ab`
field names the version an entry ships in, which is what lets the site
move it from "in progress" to "new" without anyone touching the page.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
git push origin main
```

- [ ] **Step 7: Begleit-Plugin committen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
git add .
git commit -m "$(cat <<'EOF'
Treat the text file as the foreign input it is

It arrives over HTTP from raw.githubusercontent.com. Entries missing an
id, a title or a sentence are dropped rather than repaired, markup is
stripped and lengths are capped — a half-rendered entry on the page is
worse than a missing one.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Speicher mit letztem gutem Stand

**Files:**
- Create: `includes/class-xns-store.php`
- Create: `tests/test-store.php`

**Interfaces:**
- Consumes: nichts.
- Produces:
  - `XNS_Store::read(): array` — liefert immer eine vollständige Struktur, notfalls die leere Vorgabe.
  - `XNS_Store::write( array $data ): bool` — schreibt nur, wenn `$data` vollständig ist.
  - `XNS_Store::note_failure( string $reason ): void`
  - `XNS_Store::$option_get` / `$option_set` — einsetzbare Rückrufe, damit der Test ohne WordPress läuft.

**Die Eigenschaft, um die es geht:** ein misslungener Abruf darf den gespeicherten Stand **nicht** überschreiben. Genau das prüft der Test.

- [ ] **Step 1: Testdatei schreiben**

`tests/test-store.php`:

```php
<?php
/**
 * Tests for XNS_Store — the guarantee that a failed fetch never blanks the page.
 *
 * WordPress is replaced by two closures, so the behaviour is testable without
 * a bootstrap. The property under test is not "it stores things" but "it
 * refuses to store nothing over something".
 *
 *   php tests/test-store.php
 *
 * @package XNS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-xns-store.php';

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

// WordPress durch einen Speicher im Arbeitsspeicher ersetzen.
$db = [];
XNS_Store::$option_get = static function ( string $k, $default ) use ( &$db ) {
    return $db[ $k ] ?? $default;
};
XNS_Store::$option_set = static function ( string $k, $v ) use ( &$db ) {
    $db[ $k ] = $v;
    return true;
};

$voll = [
    'stable'   => [ 'version' => '1.9.6', 'datum' => '2026-08-17', 'zip_url' => 'https://example.test/a.zip', 'zip_size' => 362000 ],
    'beta'     => [ 'version' => '1.9.7-beta.1', 'datum' => '2026-08-22', 'zip_url' => 'https://example.test/b.zip' ],
    'vorhaben' => [ [ 'id' => 'a', 'titel' => 'T', 'satz' => 'S', 'ab' => '1.9.7', 'bild' => null ] ],
];

echo "\nXNS_Store – Grundverhalten\n" . str_repeat( '-', 74 ) . "\n";

$leer = XNS_Store::read();
check( 'ohne Inhalt kommt die Vorgabe', $leer['stable'], null );
check( 'Vorhaben ist dann leer',        $leer['vorhaben'], [] );
check( 'nie geholt',                    $leer['geholt'], null );

check( 'vollstaendige Daten werden geschrieben', XNS_Store::write( $voll ), true );
$gelesen = XNS_Store::read();
check( 'und kommen zurueck',       $gelesen['stable']['version'], '1.9.6' );
check( 'mit Zeitstempel',          is_int( $gelesen['geholt'] ), true );

echo "\nXNS_Store – der letzte gute Stand bleibt\n" . str_repeat( '-', 74 ) . "\n";

check( 'leeres Feld wird abgelehnt',        XNS_Store::write( [] ), false );
check( 'stable null wird abgelehnt',        XNS_Store::write( [ 'stable' => null, 'beta' => null, 'vorhaben' => [] ] ), false );
check( 'ohne Vorhaben wird abgelehnt',      XNS_Store::write( [ 'stable' => $voll['stable'], 'beta' => null, 'vorhaben' => [] ] ), false );

$nach = XNS_Store::read();
check( 'der alte Stand steht unveraendert', $nach['stable']['version'], '1.9.6' );
check( 'und seine Vorhaben auch',           count( $nach['vorhaben'] ), 1 );

echo "\nXNS_Store – Fehlschlaege werden vermerkt, nicht gezeigt\n" . str_repeat( '-', 74 ) . "\n";

XNS_Store::note_failure( 'HTTP 503' );
$nach = XNS_Store::read();
check( 'Grund ist vermerkt',            $nach['fehler']['grund'], 'HTTP 503' );
check( 'Zeitpunkt ist vermerkt',        is_int( $nach['fehler']['wann'] ), true );
check( 'die Daten sind unberuehrt',     $nach['stable']['version'], '1.9.6' );

XNS_Store::write( $voll );
check( 'erfolgreicher Abruf loescht den Fehler', XNS_Store::read()['fehler'], null );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-store.php
```

- [ ] **Step 3: Die Klasse schreiben**

```php
<?php
/**
 * The one guarantee the website rests on: a failed fetch never blanks a page.
 *
 * The data lives in an option, not a transient. A transient may vanish at any
 * moment — and the moment it vanishes is precisely the moment GitHub is also
 * unreachable, which is how a page ends up empty. An option persists until it
 * is deliberately overwritten, and write() refuses to overwrite something with
 * nothing.
 *
 * The two closures exist so the rule is testable without a WordPress
 * bootstrap; in production they are get_option()/update_option().
 *
 * @package XNS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class XNS_Store {

    public const OPTION = 'xns_daten';

    /** @var callable|null */
    public static $option_get = null;

    /** @var callable|null */
    public static $option_set = null;

    private static function defaults(): array {
        return [
            'stable'   => null,
            'beta'     => null,
            'vorhaben' => [],
            'geholt'   => null,
            'fehler'   => null,
        ];
    }

    private static function get( $default ) {
        $fn = self::$option_get ?? static fn( $k, $d ) => get_option( $k, $d );
        return $fn( self::OPTION, $default );
    }

    private static function set( $value ): bool {
        $fn = self::$option_set ?? static fn( $k, $v ) => update_option( $k, $v, false );
        return (bool) $fn( self::OPTION, $value );
    }

    public static function read(): array {
        $stored = self::get( null );
        if ( ! is_array( $stored ) ) {
            return self::defaults();
        }
        return array_merge( self::defaults(), $stored );
    }

    /**
     * Writes only a complete set. Incomplete input leaves the stored state
     * exactly as it was — that is the whole point of this class.
     */
    public static function write( array $data ): bool {
        if ( empty( $data['stable']['version'] ) || empty( $data['vorhaben'] ) ) {
            return false;
        }

        $data['geholt'] = time();
        $data['fehler'] = null;

        return self::set( array_merge( self::defaults(), $data ) );
    }

    /** Records why a fetch failed. Never shown on the front end. */
    public static function note_failure( string $reason ): void {
        $state           = self::read();
        $state['fehler'] = [ 'grund' => $reason, 'wann' => time() ];
        self::set( $state );
    }
}
```

- [ ] **Step 4: Test laufen lassen, Bestehen bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-store.php
```

- [ ] **Step 5: Commit**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
git add .
git commit -m "$(cat <<'EOF'
Refuse to overwrite something with nothing

The data lives in an option rather than a transient. A transient may
vanish at any moment, and that moment tends to coincide with GitHub
being unreachable — which is exactly how a page ends up empty. write()
rejects an incomplete set, so the last good state survives every failed
fetch.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Abruf und Cron verdrahten

**Files:**
- Create: `includes/class-xns-fetch.php`
- Create: `tests/test-fetch.php`
- Modify: `xtx-netatmo-site.php` — Einbinden und Hooks

**Interfaces:**
- Consumes: `XNS_Github::parse_releases()`, `XNS_Content::parse()`, `XNS_Store::write()`, `XNS_Store::note_failure()`
- Produces:
  - `XNS_Fetch::run(): bool` — holt beides, schreibt bei Erfolg, vermerkt bei Misserfolg.
  - `XNS_Fetch::$http` — einsetzbarer Rückruf `fn(string $url): array{code:int,body:string}`, damit der Test nie ins Netz geht.
  - `XNS_Fetch::schedule()` / `unschedule()` — Cron alle sechs Stunden, Hook `xns_refresh`.

- [ ] **Step 1: Testdatei schreiben**

`tests/test-fetch.php`:

```php
<?php
/**
 * Tests for XNS_Fetch::run() — with the network replaced by a closure.
 *
 * No test here touches GitHub. What is being checked is the decision the
 * fetcher makes about its own answers: which failures are failures, and what
 * it does to the stored state when one occurs.
 *
 *   php tests/test-fetch.php
 *
 * @package XNS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-xns-roadmap.php';
require_once __DIR__ . '/../includes/class-xns-github.php';
require_once __DIR__ . '/../includes/class-xns-content.php';
require_once __DIR__ . '/../includes/class-xns-store.php';
require_once __DIR__ . '/../includes/class-xns-fetch.php';

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

$db = [];
XNS_Store::$option_get = static function ( string $k, $d ) use ( &$db ) {
    return $db[ $k ] ?? $d;
};
XNS_Store::$option_set = static function ( string $k, $v ) use ( &$db ) {
    $db[ $k ] = $v;
    return true;
};

$releases = file_get_contents( __DIR__ . '/fixtures/releases.json' );
$inhalt   = json_encode( [ 'vorhaben' => [ [ 'id' => 'a', 'titel' => 'T', 'satz' => 'S', 'ab' => '1.9.7' ] ] ] );

/** Baut einen Netz-Ersatz, der je URL eine feste Antwort liefert. */
function http_stub( array $map ): callable {
    return static function ( string $url ) use ( $map ) {
        foreach ( $map as $needle => $answer ) {
            if ( str_contains( $url, $needle ) ) {
                return $answer;
            }
        }
        return [ 'code' => 404, 'body' => '' ];
    };
}

echo "\nXNS_Fetch::run() – der gute Fall\n" . str_repeat( '-', 74 ) . "\n";

XNS_Fetch::$http = http_stub( [
    'api.github.com'         => [ 'code' => 200, 'body' => $releases ],
    'raw.githubusercontent'  => [ 'code' => 200, 'body' => $inhalt ],
] );

check( 'Abruf gelingt', XNS_Fetch::run(), true );
$s = XNS_Store::read();
check( 'stabile Version steht',  $s['stable']['version'], '1.9.6' );
check( 'Beta steht',             $s['beta']['version'], '1.9.7-beta.1' );
check( 'Vorhaben steht',         count( $s['vorhaben'] ), 1 );
check( 'kein Fehler vermerkt',   $s['fehler'], null );

echo "\nXNS_Fetch::run() – GitHub faellt aus\n" . str_repeat( '-', 74 ) . "\n";

XNS_Fetch::$http = http_stub( [
    'api.github.com'        => [ 'code' => 503, 'body' => '' ],
    'raw.githubusercontent' => [ 'code' => 200, 'body' => $inhalt ],
] );

check( 'Abruf meldet Misserfolg', XNS_Fetch::run(), false );
$s = XNS_Store::read();
check( 'der alte Stand steht noch',   $s['stable']['version'], '1.9.6' );
check( 'der Grund ist vermerkt',      str_contains( $s['fehler']['grund'], '503' ), true );

echo "\nXNS_Fetch::run() – die Inhaltsdatei fehlt\n" . str_repeat( '-', 74 ) . "\n";

XNS_Fetch::$http = http_stub( [
    'api.github.com'        => [ 'code' => 200, 'body' => $releases ],
    'raw.githubusercontent' => [ 'code' => 404, 'body' => '' ],
] );

check( 'auch das ist ein Misserfolg', XNS_Fetch::run(), false );
check( 'und der Stand bleibt',        XNS_Store::read()['stable']['version'], '1.9.6' );

echo "\nXNS_Fetch::run() – Antwort 200 mit Muell\n" . str_repeat( '-', 74 ) . "\n";

XNS_Fetch::$http = http_stub( [
    'api.github.com'        => [ 'code' => 200, 'body' => 'kein json' ],
    'raw.githubusercontent' => [ 'code' => 200, 'body' => $inhalt ],
] );

check( 'Status 200 allein genuegt nicht', XNS_Fetch::run(), false );
check( 'der Stand bleibt auch dann',      XNS_Store::read()['stable']['version'], '1.9.6' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-fetch.php
```

- [ ] **Step 3: Die Klasse schreiben**

```php
<?php
/**
 * The only place in this plugin that touches the network.
 *
 * Everything else is pure, which is why the rules can be tested without ever
 * asking GitHub. A run either produces a complete set or changes nothing but
 * the failure note: partial success is treated as failure, because half a
 * page is worse than yesterday's whole one.
 *
 * @package XNS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class XNS_Fetch {

    public const HOOK     = 'xns_refresh';
    public const INTERVAL = 'xns_six_hours';

    private const URL_RELEASES = 'https://api.github.com/repos/Xyla1512/Netatmo/releases?per_page=10';
    private const URL_CONTENT  = 'https://raw.githubusercontent.com/Xyla1512/Netatmo/main/docs/site/website.de.json';

    /** @var callable|null fn(string $url): array{code:int,body:string} */
    public static $http = null;

    private static function get( string $url ): array {
        if ( null !== self::$http ) {
            return ( self::$http )( $url );
        }

        $r = wp_remote_get(
            $url,
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'xtx-netatmo-site/' . XNS_VERSION,
                ],
            ]
        );

        if ( is_wp_error( $r ) ) {
            return [ 'code' => 0, 'body' => $r->get_error_message() ];
        }

        return [
            'code' => (int) wp_remote_retrieve_response_code( $r ),
            'body' => (string) wp_remote_retrieve_body( $r ),
        ];
    }

    public static function run(): bool {
        $a = self::get( self::URL_RELEASES );
        if ( 200 !== $a['code'] ) {
            XNS_Store::note_failure( 'Releases-API: HTTP ' . $a['code'] );
            return false;
        }

        $releases = json_decode( $a['body'], true );
        if ( ! is_array( $releases ) ) {
            XNS_Store::note_failure( 'Releases-API: Antwort ist kein JSON' );
            return false;
        }

        $parsed = XNS_Github::parse_releases( $releases );
        if ( null === $parsed['stable'] ) {
            XNS_Store::note_failure( 'Releases-API: kein stabiles Release gefunden' );
            return false;
        }

        $b = self::get( self::URL_CONTENT );
        if ( 200 !== $b['code'] ) {
            XNS_Store::note_failure( 'Inhaltsdatei: HTTP ' . $b['code'] );
            return false;
        }

        $vorhaben = XNS_Content::parse( $b['body'] );
        if ( empty( $vorhaben ) ) {
            XNS_Store::note_failure( 'Inhaltsdatei: kein gueltiger Eintrag' );
            return false;
        }

        return XNS_Store::write(
            [
                'stable'   => $parsed['stable'],
                'beta'     => $parsed['beta'],
                'vorhaben' => $vorhaben,
            ]
        );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, self::INTERVAL, self::HOOK );
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled( self::HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::HOOK );
        }
    }

    public static function add_interval( array $schedules ): array {
        $schedules[ self::INTERVAL ] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Alle sechs Stunden',
        ];
        return $schedules;
    }
}
```

- [ ] **Step 4: Hooks im Plugin-Kopf verdrahten**

An `xtx-netatmo-site.php` anfügen:

```php
require_once XNS_PLUGIN_DIR . 'includes/class-xns-github.php';
require_once XNS_PLUGIN_DIR . 'includes/class-xns-content.php';
require_once XNS_PLUGIN_DIR . 'includes/class-xns-store.php';
require_once XNS_PLUGIN_DIR . 'includes/class-xns-fetch.php';

add_filter( 'cron_schedules', [ 'XNS_Fetch', 'add_interval' ] );
add_action( XNS_Fetch::HOOK, [ 'XNS_Fetch', 'run' ] );

register_activation_hook(
    __FILE__,
    static function () {
        XNS_Fetch::schedule();
        XNS_Fetch::run();
    }
);

register_deactivation_hook( __FILE__, [ 'XNS_Fetch', 'unschedule' ] );
```

- [ ] **Step 5: Alle Tests laufen lassen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
for f in tests/test-*.php; do php "$f" >/dev/null 2>&1 && echo "ok   $f" || echo "FAIL $f"; done
```

Erwartet: fünf Zeilen `ok`.

- [ ] **Step 6: Commit**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
git add .
git commit -m "$(cat <<'EOF'
Keep the network in one place, and treat partial success as failure

Every rule in this plugin is pure and testable without GitHub; only this
class knows a URL. A run either produces a complete set or changes
nothing but the failure note, because half a page is worse than
yesterday's whole one. HTTP 200 with a garbage body counts as failure
too.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Die fünf Shortcodes

**Files:**
- Create: `includes/class-xns-shortcodes.php`
- Create: `assets/site.css`
- Create: `tests/test-shortcodes.php`
- Modify: `xtx-netatmo-site.php`

**Interfaces:**
- Consumes: `XNS_Store::read()`, `XNS_Roadmap::classify()`
- Produces: die Shortcodes `naws_site_header`, `naws_site_roadmap`, `naws_site_whatsnew`, `naws_site_versions`, `naws_site_fact`.

**Getestet wird die erzeugte Zeichenkette**, nicht das Aussehen: dass Fremddaten escaped ankommen, dass ein leerer Speicher nichts Kaputtes ausgibt, dass das Beta-Abzeichen genau dann erscheint, wenn es soll.

- [ ] **Step 1: Testdatei schreiben**

`tests/test-shortcodes.php`:

```php
<?php
/**
 * Tests for the rendered strings — not for how they look.
 *
 * Three properties matter and are worth a failing test: foreign data arrives
 * escaped, an empty store produces nothing broken, and the beta badge appears
 * exactly when the promotion rule says it should.
 *
 *   php tests/test-shortcodes.php
 *
 * @package XNS
 */
define( 'ABSPATH', __DIR__ );

// Minimale WordPress-Ersaetze. Bewusst so knapp wie moeglich - was hier
// nachgebaut wird, wird auch getestet, und ein nachgebautes esc_html() das
// anders escaped als das echte waere schlimmer als keines.
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u )  { return filter_var( (string) $u, FILTER_VALIDATE_URL ) ? (string) $u : ''; }
function size_format( $b ) { return round( $b / 1024 / 1024, 1 ) . ' MB'; }

require_once __DIR__ . '/../includes/class-xns-roadmap.php';
require_once __DIR__ . '/../includes/class-xns-store.php';
require_once __DIR__ . '/../includes/class-xns-shortcodes.php';

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

$db = [];
XNS_Store::$option_get = static function ( string $k, $d ) use ( &$db ) { return $db[ $k ] ?? $d; };
XNS_Store::$option_set = static function ( string $k, $v ) use ( &$db ) { $db[ $k ] = $v; return true; };

echo "\nLeerer Speicher\n" . str_repeat( '-', 74 ) . "\n";

check( 'Fahrplan bleibt stumm',   trim( XNS_Shortcodes::roadmap( [] ) ), '' );
check( 'Neuerungen bleiben stumm', trim( XNS_Shortcodes::whatsnew( [] ) ), '' );
check( 'Fakt ohne Daten ist leer', XNS_Shortcodes::fact( [ 'key' => 'version' ] ), '' );

echo "\nMit Daten\n" . str_repeat( '-', 74 ) . "\n";

XNS_Store::write( [
    'stable'   => [ 'version' => '1.9.6', 'datum' => '2026-08-17', 'zip_url' => 'https://example.test/a.zip', 'zip_size' => 362496 ],
    'beta'     => [ 'version' => '1.9.7-beta.1', 'datum' => '2026-08-22', 'zip_url' => 'https://example.test/b.zip' ],
    'vorhaben' => [
        [ 'id' => 'neu-drin',  'titel' => 'Schon drin',  'satz' => 'Satz A.', 'ab' => '1.9.6', 'bild' => null ],
        [ 'id' => 'in-arbeit', 'titel' => 'In Arbeit',   'satz' => 'Satz B.', 'ab' => '1.9.7', 'bild' => null ],
        [ 'id' => 'irgendwann','titel' => 'Irgendwann',  'satz' => 'Satz C.', 'ab' => null,    'bild' => null ],
    ],
] );

check( 'Version als Fakt',      XNS_Shortcodes::fact( [ 'key' => 'version' ] ), '1.9.6' );
check( 'Beta als Fakt',         XNS_Shortcodes::fact( [ 'key' => 'beta' ] ), '1.9.7-beta.1' );
check( 'unbekannter Schluessel', XNS_Shortcodes::fact( [ 'key' => 'gibtsnicht' ] ), '' );

$roadmap = XNS_Shortcodes::roadmap( [] );
check( 'in Arbeit erscheint',        str_contains( $roadmap, 'In Arbeit' ), true );
check( 'ohne Version erscheint',     str_contains( $roadmap, 'Irgendwann' ), true );
check( 'Veroeffentlichtes nicht',    str_contains( $roadmap, 'Schon drin' ), false );
check( 'Beta-Abzeichen fuer 1.9.7',  str_contains( $roadmap, '1.9.7-beta.1' ), true );

$neu = XNS_Shortcodes::whatsnew( [] );
check( 'Neuerung erscheint',      str_contains( $neu, 'Schon drin' ), true );
check( 'Version steht dabei',     str_contains( $neu, '1.9.6' ), true );
check( 'Laufendes nicht dabei',   str_contains( $neu, 'In Arbeit' ), false );

echo "\nFremddaten werden escaped\n" . str_repeat( '-', 74 ) . "\n";

XNS_Store::write( [
    'stable'   => [ 'version' => '1.9.6', 'datum' => '2026-08-17', 'zip_url' => 'javascript:alert(1)', 'zip_size' => 1 ],
    'beta'     => null,
    'vorhaben' => [ [ 'id' => 'x', 'titel' => 'A & B <b>fett</b>', 'satz' => '"Zitat"', 'ab' => '1.9.7', 'bild' => null ] ],
] );

$roadmap = XNS_Shortcodes::roadmap( [] );
check( 'Kaufmanns-Und escaped',   str_contains( $roadmap, 'A &amp; B' ), true );
check( 'kein rohes Markup',       str_contains( $roadmap, '<b>fett</b>' ), false );
check( 'Anfuehrungszeichen escaped', str_contains( $roadmap, '&quot;Zitat&quot;' ), true );
check( 'javascript-URL faellt weg',  XNS_Shortcodes::fact( [ 'key' => 'zip_url' ] ), '' );

echo "\nKein JavaScript in der Ausgabe\n" . str_repeat( '-', 74 ) . "\n";

$alles = XNS_Shortcodes::roadmap( [] ) . XNS_Shortcodes::whatsnew( [] ) . XNS_Shortcodes::header( [] );
check( 'kein script-Tag',    str_contains( $alles, '<script' ), false );
check( 'kein onclick o.ae.', (bool) preg_match( '/\son[a-z]+=/i', $alles ), false );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-shortcodes.php
```

- [ ] **Step 3: Die Klasse schreiben**

`includes/class-xns-shortcodes.php` — jede Methode nimmt `array $atts` und liefert eine Zeichenkette. Alle Fremdwerte laufen durch `esc_html()`, `esc_attr()` oder `esc_url()`. Kein `<script>`, kein `on*`-Attribut, keine eingebettete JavaScript-Zeile: die Seite muss ohne JavaScript vollständig lesbar sein.

Aufbau der Methoden:

- `header( array $atts ): string` — `<header class="xns-kopf">` mit Wortmarke, vier Sprungmarken (`#funktionen`, `#neu`, `#installation`, `#faq`) und dem Download-Knopf, dessen Ziel aus `XNS_Store::read()['stable']['zip_url']` kommt.
- `roadmap( array $atts ): string` — `XNS_Roadmap::classify()` aufrufen, den Eimer `arbeit` als `<ul>` ausgeben, jeder Eintrag mit `<h3>` und `<p>`; bei `beta === true` zusätzlich ein `<span class="xns-beta">` mit Version und Link auf das Pre-Release. Leerer Eimer → leere Zeichenkette.
- `whatsnew( array $atts ): string` — derselbe Aufruf, Eimer `neu`, mit der Versionsnummer in der Überschrift.
- `versions( array $atts ): string` — kompakte Liste aus `stable` und `beta` mit Datum.
- `fact( array $atts ): string` — Einzelwert für `version`, `released`, `zip_url`, `zip_size`, `beta`. Unbekannter Schlüssel → leere Zeichenkette. `zip_size` durch `size_format()`.

Registrierung am Ende der Datei:

```php
add_shortcode( 'naws_site_header',   [ 'XNS_Shortcodes', 'header' ] );
add_shortcode( 'naws_site_roadmap',  [ 'XNS_Shortcodes', 'roadmap' ] );
add_shortcode( 'naws_site_whatsnew', [ 'XNS_Shortcodes', 'whatsnew' ] );
add_shortcode( 'naws_site_versions', [ 'XNS_Shortcodes', 'versions' ] );
add_shortcode( 'naws_site_fact',     [ 'XNS_Shortcodes', 'fact' ] );
```

Die Registrierung darf nur laufen, wenn `function_exists( 'add_shortcode' )` — sonst bricht die Testdatei ab.

- [ ] **Step 4: Test laufen lassen, Bestehen bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-shortcodes.php
```

- [ ] **Step 5: Header-CSS schreiben**

`assets/site.css` — nur der Header. Farbtoken `--xns-petrol: #2d5252`, mitlaufend über `position: sticky`, unter 768 px klappen die Sprungmarken weg. Kein `@import`, keine externe Schrift.

- [ ] **Step 6: Commit**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite"
git add .
git commit -m "$(cat <<'EOF'
Render the five blocks, and escape what came from elsewhere

The data arrives from GitHub, so every value goes through esc_html,
esc_attr or esc_url on the way into the markup. The tests check the
string, not the appearance: escaping, an empty store producing nothing
broken, and the beta badge appearing exactly when the promotion rule
says it should.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Strukturierte Daten, `llms.txt` und `robots.txt`

**Files:**
- Create: `includes/class-xns-schema.php`
- Create: `includes/class-xns-files.php`
- Create: `tests/test-schema.php`
- Modify: `xtx-netatmo-site.php`

**Interfaces:**
- Consumes: `XNS_Store::read()`
- Produces:
  - `XNS_Schema::software_application(): array` — das JSON-LD als Array, damit es prüfbar ist.
  - `XNS_Schema::howto(): array`
  - `XNS_Schema::print_head(): void` — gibt beides gebündelt im `wp_head` aus, **nur auf der Startseite**.
  - `XNS_Files::llms_txt(): string`
  - `XNS_Files::robots_txt( string $output, $public ): string`

**Doppelungsfreiheit ist der Kern.** Rank Math liefert `WebSite`, `Person`, `BreadcrumbList` und `FAQPage`. Dieses Plugin liefert `SoftwareApplication` und `HowTo` — und **nur** diese beiden. Rank Maths eigenes Schema für die Startseite wird in Task 16 abgeschaltet.

- [ ] **Step 1: Testdatei schreiben**

`tests/test-schema.php` prüft:

```php
check( 'Typ ist SoftwareApplication', $s['@type'], 'SoftwareApplication' );
check( 'Version kommt aus dem Speicher', $s['softwareVersion'], '1.9.6' );
check( 'Preis ist null', $s['offers']['price'], '0' );
check( 'Waehrung ist EUR', $s['offers']['priceCurrency'], 'EUR' );
check( 'Betriebssystem ist WordPress', $s['operatingSystem'], 'WordPress' );
check( 'Voraussetzungen genannt', str_contains( $s['softwareRequirements'], 'PHP 8.0' ), true );
check( 'Autor ist eine Person', $s['author']['@type'], 'Person' );
check( 'kein Angebot ohne Download', isset( $s['downloadUrl'] ), true );
check( 'HowTo hat drei Schritte', count( $h['step'] ), 3 );
check( 'llms.txt nennt die Version', str_contains( XNS_Files::llms_txt(), '1.9.6' ), true );
check( 'robots erlaubt GPTBot', str_contains( $r, 'GPTBot' ), true );
check( 'robots erlaubt ClaudeBot', str_contains( $r, 'ClaudeBot' ), true );
check( 'robots nennt die Sitemap', str_contains( $r, 'sitemap' ), true );
```

Dazu ein Test, der bei leerem Speicher **kein** JSON-LD erzeugt — ein `SoftwareApplication` ohne Version wäre eine Falschangabe.

- [ ] **Step 2: Fehlschlag bestätigen**

```bash
cd "/c/Users/xyla1/.claude/NetatmoSite" && php tests/test-schema.php
```

- [ ] **Step 3: Die beiden Klassen schreiben**

`XNS_Schema::print_head()` hängt an `wp_head` und gibt nur auf `is_front_page()` aus. Ausgabe über `wp_json_encode()` mit `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, eingebettet in `<script type="application/ld+json">`. Bei leerem Speicher: gar nichts.

`XNS_Files::llms_txt()` erzeugt reinen Text: Name, ein Satz, Version, Lizenz, Preis, Anforderungen, die Links auf Startseite, Demo, GitHub und Changelog. Ausgeliefert über eine Rewrite-Regel auf `/llms.txt` mit `Content-Type: text/plain; charset=utf-8`.

`XNS_Files::robots_txt()` hängt an den Filter `robots_txt` und ergänzt ausdrückliche `Allow`-Regeln für `GPTBot`, `ClaudeBot`, `PerplexityBot`, `Google-Extended`, `CCBot` und `Applebot-Extended`.

- [ ] **Step 4: Test laufen lassen, Bestehen bestätigen**

- [ ] **Step 5: Commit**

---

### Task 8: Auf die Installation bringen und dort prüfen

**Files:** keine neuen — Übertragung und Prüfung.

- [ ] **Step 1: Plugin auf den Server übertragen**

Über Novamira nach `wp-content/plugins/xtx-netatmo-site/`, nach dem Muster aus `[[live-deployment-per-patch]]`. **Opcache beachten:** neue Klassen bleiben sonst unsichtbar.

- [ ] **Step 2: Aktivieren und ersten Abruf erzwingen**

```php
activate_plugin( 'xtx-netatmo-site/xtx-netatmo-site.php' );
$ok = XNS_Fetch::run();
return [ 'ok' => $ok, 'store' => XNS_Store::read() ];
```

Erwartet: `ok = true`, `stable.version = 1.9.6`, `beta.version = 1.9.7-beta.1`, vier Vorhaben.

- [ ] **Step 3: Shortcodes auf einer Kladdeseite rendern**

Eine Entwurfsseite anlegen, alle fünf Shortcodes einsetzen, `do_shortcode()` auswerten und die Ausgabe ansehen. Erwartet: der Fahrplan zeigt die Einträge mit `ab: 1.9.7` **samt Beta-Abzeichen**, „Neu in Version" bleibt leer, weil kein Vorhaben auf 1.9.6 zeigt.

- [ ] **Step 4: Ausfallprobe**

`XNS_Fetch::$http` künstlich auf HTTP 503 setzen, `run()` aufrufen, danach `XNS_Store::read()` prüfen: die Daten müssen unverändert stehen, `fehler.grund` gesetzt sein.

- [ ] **Step 5: `llms.txt` und `robots.txt` abrufen**

```bash
curl -s https://netatmo.frank-neumann.de/llms.txt | head -20
curl -s https://netatmo.frank-neumann.de/robots.txt
```

- [ ] **Step 6: Kladdeseite löschen, Commit**

---

# Phase B — Screenshots

### Task 9: Frontend-Aufnahmen

**Files:** Bilder in die Mediathek.

- [ ] **Step 1: Kladdeseite mit allen Shortcodes anlegen**, damit jede Ansicht einzeln und unbeschnitten aufnehmbar ist.
- [ ] **Step 2: Chrome-MCP starten**, Fenster auf 1440 px, die sechs Aufnahmen machen: Live-Dashboard, Jahresvergleich, Vorhersage, Widget mit Infobar, Rechenwerte-Tabelle, und bei 390 px die Handy-Ansicht.
- [ ] **Step 3: Jede Aufnahme ansehen** — nicht nur speichern. Abgeschnittene Diagramme, fehlende Werte oder ein leeres Chart sind auf einer Verkaufsseite schlimmer als kein Bild.
- [ ] **Step 4: In die Mediathek hochladen** mit deutschem Alt-Text, der beschreibt, was zu sehen ist, nicht wie die Datei heißt.
- [ ] **Step 5: Kladdeseite löschen.**

### Task 10: Admin-Aufnahmen mit Maskierung

- [ ] **Step 1: Temporären Admin-Zugangslink erzeugen** über Novamira.
- [ ] **Step 2: Die fünf Ansichten aufnehmen:** Verbindungseinstellungen, Cron-Log, Verschlüsselungsstatus, Erscheinungsbild, Shortcode-Dokuseite.
- [ ] **Step 3: Maskieren.** Client-Secret, API-Schlüssel und Modul-MACs unkenntlich machen. **Jede Aufnahme einzeln ansehen und gegenprüfen** — ein Screenshot mit echtem Secret ist aus dem Netz nicht zurückzuholen.
- [ ] **Step 4: Dem Nutzer vorlegen**, bevor irgendetwas hochgeladen wird.
- [ ] **Step 5: Nach Freigabe hochladen** mit Alt-Text.
- [ ] **Step 6: Zugangslink zurückziehen.**

---

# Phase C — Seite, Umzug, SEO

### Task 11: Sicherung und Umzug auf `/live-demo/`

- [ ] **Step 1: Seite 7 vollständig sichern** — `post_content`, `_elementor_data`, `_elementor_edit_mode`, `_elementor_css`, `_wp_page_template` — in eine Datei unter `C:\Users\xyla1\.claude\NetatmoSite\backup\`. **Ohne diese Datei wird nichts angefasst.**
- [ ] **Step 2: Seite `/live-demo/` anlegen**, Elementor-Metadaten hinüberkopieren, Elementor-CSS neu erzeugen lassen.
- [ ] **Step 3: `/live-demo/` im Browser ansehen** — Dashboard, Charts und Tabelle müssen dort so aussehen wie bisher auf der Startseite. Erst wenn das stimmt, geht es weiter.

### Task 12–14: Die Landingpage schreiben

**REQUIRED SUB-SKILL:** `frontend-design` vor dem ersten Markup laden — hier entsteht die gestalterische Substanz, und der Skill ist genau dafür da.

Aufgeteilt in drei Abschnitte, damit jeder für sich begutachtet werden kann:

- **Task 12:** Gerüst, Design-Tokens (hell und dunkel), selbst ausgelieferte Schrift, Header, Hero mit echtem Live-Wert, Abschnitte 2 und 3.
- **Task 13:** Die sechs Funktionsblöcke mit Screenshots und Bildunterschriften, „Neu und in Arbeit", Live-Demo-Verweis, Installation.
- **Task 14:** Datenschutz-Abschnitt mit dem Absatz über die Grenzen, Technikteil in `<details>`, Faktentabelle, FAQ, Über mich mit Foto, Footer.

Jede dieser Aufgaben endet mit: Datei speichern, per Novamira in eine **Entwurfsseite** setzen, im Browser bei 360, 768 und 1440 px ansehen, hell und dunkel.

### Task 15: Startseite umstellen

- [ ] Seite 7 auf `elementor_canvas` setzen, `_elementor_edit_mode` entfernen, Landingpage-HTML in `post_content` schreiben.
- [ ] Startseite abrufen und prüfen.
- [ ] **Gegenprobe: `curl -s https://netatmo.frank-neumann.de/ | grep -c fonts.googleapis` muss `0` ergeben.**

### Task 16: Rank Math konfigurieren

- [ ] Titel, Beschreibung, Wissensgraph als Person, `website_name`, Sitemap, Breadcrumbs, Open-Graph-Karte.
- [ ] **Rank Maths eigenes Schema für Seite 7 abschalten**, damit sich `SoftwareApplication` nicht doppelt.
- [ ] „Beispiel-Seite" entfernen.
- [ ] **Permalinks** von `/%year%/%monthnum%/%day%/%postname%/` auf `/%postname%/` umstellen und für den einen bestehenden Beitrag eine 301-Weiterleitung in Rank Math anlegen. Vorher die alte URL notieren; danach beide URLs abrufen und prüfen, dass die alte weiterleitet statt 404 zu liefern.
- [ ] **Kein Instant Indexing, kein IndexNow.**

### Task 17: Abnahme

Die Verifikationsliste der Spec Punkt für Punkt, mit Belegen:

- [ ] Beide Seiten HTTP 200
- [ ] Null Treffer für `fonts.googleapis.com`
- [ ] JSON-LD gültig und doppelungsfrei
- [ ] 360 / 768 / 1440 px, hell und dunkel
- [ ] `llms.txt` und `robots.txt` erreichbar
- [ ] Alle elf Screenshots geladen, kein Secret sichtbar
- [ ] Beförderungsregel gegen die echte Release-Lage geprüft
- [ ] Ausfallprobe bestanden
- [ ] **Dem Nutzer den vollständigen Text zur inhaltlichen Abnahme vorlegen** — das ist die Bedingung der Ausnahme von der KI-Kennzeichnungspflicht, kein Höflichkeitsschritt.
