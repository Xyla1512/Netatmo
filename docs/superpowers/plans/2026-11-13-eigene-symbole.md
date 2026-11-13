# Eigene Symbole statt Emojis — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (Frank: „bau selbst“). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kein Emoji mehr im Frontend; Satz „Emoji“ und Vorhersage zeigen eigene farbige SVG-Symbole; readme sagt, dass nichts Fremdes in den Browser geladen wird.

**Architecture:** Eine Quelle für die farbigen Bilder (`NAWS_Icons::pictogram()`), die `set_emoji()` und `templates/forecast.php` nutzen; eine CSS-Klasse `naws-picto` nimmt sie von den Linien-Regeln aus; ein Wächter-Test verbietet Emojis im Frontend-Code.

**Tech Stack:** PHP 8, CSS, gettext, PHP-Testskripte.

**Spec:** `docs/superpowers/specs/2026-11-13-eigene-symbole-design.md` (Demo: https://claude.ai/artifact/YACCrsQ8SMqMaS3s7CNSFx, Quelle der SVGs: `scratchpad/symbole-entwurf.html`, Objekt `ICONS`).

## Global Constraints

- SVGs exakt wie in der Demo (32er-viewBox, Farben, Kontur `#3d4b52`); jedes `<svg>` trägt `class="naws-picto"`, `aria-hidden="true"`, `focusable="false"`; Kindelemente ohne eigene Kontur bekommen `stroke="none"`, damit geerbte CSS-Konturen nichts zeichnen.
- Ausgabe nur über `wp_kses( …, naws_svg_kses_args() )` bzw. die bestehenden Wege; prüfen, dass `naws_svg_kses_args()` `text`-Elemente und deren Attribute (`x`, `y`, `text-anchor`, `font-family`, `font-size`, `font-weight`, `fill`) für das CO₂-Symbol erlaubt — sonst ergänzen.
- Emoji-Bereiche für den Wächter: U+1F300–1FAFF, U+2600–27BF, U+2B50, U+FE0F; geprüfte Pfade: `templates/`, `assets/js/`, `assets/css/`, `includes/` ohne `class-naws-admin.php` (Admin-Menü bleibt).
- Zweig `eigene-symbole`; nichts pushen/mergen ohne Ansage; LF; Suite, `php -l`, phpcs grün; Commits mit `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **`[naws_current]` mit Satz „Emoji“** — Symbole farbig, nicht einfarbig umrandet (CSS `frontend.css:211`). → Test Task 1 (Klasse vorhanden + CSS-Regel mit `.naws-picto`).
2. **`[naws_live]` mit Satz „Emoji“** — JS setzt die Symbole per `get_js_object()` ein; JSON-Escaping darf das SVG nicht zerstören. → Test Task 1 (`get_js_object('emoji')` enthält `naws-picto`).
3. **CO₂-Text im SVG** übersteht `wp_kses`. → Test Task 1.
4. **Wechsel des Satzes im Backend** zeigt die neuen Symbole in der Vorschau der Darstellungsseite. → Abnahme Task 3.
5. **Seite mit allen Bausteinen** fragt `s.w.org` nicht mehr an. → Abnahme Task 3.

---

### Task 1: Piktogramme, Satz „Emoji“, Vorhersage, CSS, Wächter

**Files:** Modify `includes/class-naws-icons.php`, `templates/forecast.php`, `assets/css/frontend.css`, ggf. `includes/class-naws-helpers.php` (`naws_svg_kses_args`); Create `tests/test-no-emoji.php`.

**Interfaces — Produces:** `NAWS_Icons::pictogram( string $key ): string` mit Schlüsseln `temp, humid, press, wind, rain, co2, noise, pin, prob, compass, gust`; unbekannt → `''`. `set_emoji()` = `pictogram()` für die sieben Kachel-Schlüssel.

- [ ] **Step 1: Failing tests** (`tests/test-no-emoji.php`)
```php
check( 'kein Emoji im Frontend-Code', naws_emoji_hits(), [] );           // Liste "datei:zeile"
check( '11 Piktogramme', count( array_filter( array_map( [ 'NAWS_Icons', 'pictogram' ], [ 'temp','humid','press','wind','rain','co2','noise','pin','prob','compass','gust' ] ) ) ), 11 );
check( 'unbekannt leer', NAWS_Icons::pictogram( 'x' ), '' );
check( 'Satz Emoji = Piktogramme', NAWS_Icons::get_set( 'emoji' )['co2'], NAWS_Icons::pictogram( 'co2' ) );
check( 'Klasse naws-picto', str_contains( NAWS_Icons::pictogram( 'temp' ), 'class="naws-picto"' ), true );
check( 'CO2-Text uebersteht kses', str_contains( wp_kses( NAWS_Icons::pictogram( 'co2' ), naws_svg_kses_args() ), '>CO₂</text>' ), true );
check( 'JS-Objekt traegt die Klasse', str_contains( NAWS_Icons::get_js_object( 'emoji' ), 'naws-picto' ), true );
check( 'CSS nimmt naws-picto aus', (bool) preg_match( '/\.naws-picto[^{]*\{[^}]*stroke:\s*none/', file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' ) ), false === false );
```
(`wp_kses` im Test als Stub mit `strip_tags`-Allowlist aus `naws_svg_kses_args()` oder echte Funktion nicht verfügbar → Allowlist-Prüfung: `naws_svg_kses_args()['text']` enthält `x`, `y`, `text-anchor`, `font-size`.)
- [ ] **Step 2:** `php tests/test-no-emoji.php` → FAIL (Treffer in `class-naws-icons.php`, `forecast.php`, `class-naws-helpers.php`, `class-naws-astro.php`).
- [ ] **Step 3:** `pictogram()` + `set_emoji()`; Vorhersage: `📍`→`pin`, `🌧️`→`rain`, `💧`→`prob`, `🌬️`→`wind`, `🧭`→`compass`, `🌪️`→`gust`, Größe 1em; CSS: `.naws-picto` darf von `.naws-card-icon svg` und `.naws-wx[data-icon-set="emoji"] .naws-ico svg` nicht umgefärbt werden (`fill`/`stroke` zurück auf Attributwerte, also Selektor `svg.naws-picto` mit `fill: initial; stroke: none;`), Vorhersage-Symbole `width/height: 1.05em; vertical-align: -0.15em`. Die übrigen Treffer (Helfer, Astro) gehören zu Task 2 — der Wächter-Test bleibt bis dahin rot; in Task 1 nur dessen übrige Prüfungen grün.
- [ ] **Step 4:** übrige Prüfungen grün, Suite sonst grün, phpcs.
- [ ] **Step 5: Commit** `"Pictograms instead of emojis: emoji icon set and forecast"`

### Task 2: Unsichtbare Emojis, Darstellungsseite, Übersetzungen, Doku

**Files:** Modify `includes/class-naws-helpers.php` (Rückfall `param_icon` → `NAWS_Icons::get_set( 'outline' )`-Eintrag bzw. `''`; `history_chart_defs`/`indoor_chart_defs` `icon` → Piktogramm-Schlüssel oder entfernen, wenn kein Leser — vorher `grep "'icon'"`), `includes/class-naws-astro.php` (`emoji` aus `moon_data()` entfernen, wenn kein Leser — `grep "\['emoji'\]"`), `includes/class-naws-icons.php` (Beschreibung „Colourful pictograms in emoji style — the same on every device.“), Kataloge de/nb, `readme.txt` (Absatz am Anfang von „Privacy & External Services“ gemäß Spec §6), `CHANGELOG.md` `[Unreleased]`.

- [ ] Step 1: Wächter-Test aus Task 1 ist rot (Helfer/Astro). Zusätzlich: `check( 'readme: kein Fremdinhalt im Browser', str_contains( $readme, 'loads nothing from other servers into the visitor' ), true );`
- [ ] Step 2: → FAIL.
- [ ] Step 3: umsetzen; Kataloge (makepot, merge_po, fill_po: de „Farbige Symbole im Emoji-Stil – auf allen Geräten gleich.“, nb „Fargerike symboler i emoji-stil – like på alle enheter.“), make_mo, Prüfung nach Franks Regel.
- [ ] Step 4: Suite grün inkl. `test-no-emoji.php`, `test-mo-files.php`, `test-readme-sections.php`; phpcs.
- [ ] Step 5: Commit `"Pictograms: no emoji left in the front end; privacy note in the readme"`

### Task 3: Gesamtprüfung, dev, Abnahme

- [ ] Gesamtprüfung durch frischen Prüfer (Opus) über `main..HEAD`, Korrekturen mit Test.
- [ ] dev: Sicherung, einspielen, `opcache_reset()`, Startseite 200.
- [ ] Playwright: Darstellung auf Satz „Emoji“ (dev-Einstellung vorher notieren und nachher zurücksetzen), `/wetter/` + `/shortcode-demo/`: Symbole farbig in `[naws_live]`, `[naws_current]`, `[naws_forecast]`; Anfragen an `s.w.org` = 0; Darstellungsseite zeigt die neuen Symbole.
- [ ] Frank zeigen; nichts mergen/pushen ohne Ansage.
