# Windrose: `[naws_windrose]`

**Datum:** 2026-09-07
**Betrifft:** neu `includes/class-naws-windrose.php`, `templates/windrose.php`, `assets/js/windrose-boot.js`, zwei Tests; geändert `class-naws-shortcodes.php`, `class-naws-colors.php`, `class-naws-helpers.php`, `class-naws-labels.php`, `class-naws-database.php`, `admin/views/appearance.php`, `admin/views/shortcodes.php`, `frontend.css`, Kataloge, Doku
**Auslöser:** Frank, 07.09.2026: „Ja super, setze das so um, denke bitte an farbliche Anpassbarkeit, englische Übersetzung, Flexibilität" — nach der Demo mit echten Daten (Artifact „Windrose Leipzig", https://claude.ai/code/artifact/3d18979a-bd86-4cca-9937-1c03dcaeeb8b)
**Ziel-Release:** 1.9.12

---

## 1. Was die Windrose zeigt

Aus welcher Richtung der Wind an der Station kommt, wie oft, und wie kräftig. Sechzehn (oder acht) Strahlen, einer je Himmelsrichtung, deren Länge der Anteil der Messungen aus dieser Richtung ist. Jeder Strahl ist von innen nach außen in Beaufort-Klassen gestapelt: innen die leichten Winde, außen die kräftigen. Windstille steht als Prozentwert in der Mitte, weil sie keine Richtung hat. Ringe in Fünf-Prozent-Schritten machen die Länge lesbar. Die drei häufigsten Richtungen tragen ihren Prozentwert als Beschriftung.

Unter der Rose stehen Kennzahlen (Hauptrichtung mit Anteil, zweite Richtung, Mittel, Spitze mit Datum, Windstille, Anzahl der Messungen), die Legende der Klassen und eine Tabelle mit denselben Zahlen je Richtung. Die Tabelle ist immer im Markup; sichtbar wird sie nur auf Wunsch, sonst dient sie Screenreadern.

Die Demo vom 07.09. ist die visuelle Vorlage. Was dort JavaScript rechnete, rechnet hier der Server.

## 2. Entscheidungen

1. **Nur Rohwerte.** Quelle ist ausschließlich `wp_naws_readings`: `WindAngle` mit `WindStrength` (bzw. `GustAngle` mit `GustStrength`), gepaart über `module_id` und `recorded_at`. Die Tagestabelle hat mit `wind_angle` nur eine Richtung je Tag; eine Rose daraus wäre ein anderes, gröberes Bild. Reicht der gewünschte Zeitraum weiter zurück als die vorhandenen Rohwerte (Aufbewahrung `data_retention`, Standard 365 Tage), zeigt die Rose, was da ist, und die Bildunterschrift sagt „Messungen ab 21.04.2026".
2. **Bündelung in SQL, eine Abfrage je Zeitraum.** Sektor und Klasse werden in der Abfrage berechnet und gruppiert; zurück kommen höchstens 16 × 6 Zeilen. Der Join läuft über den vorhandenen eindeutigen Schlüssel `(module_id, recorded_at, parameter)`, der Zeitraum über `idx_recorded_at`. Das Ergebnis wird als Transient unter dem Präfix `naws_cache_` abgelegt und beim Sync mit allen anderen Caches geleert (`NAWS_Database::flush_caches()`).
3. **Serverseitiges SVG, Skript nur als Aufsatz.** Wie bei der Sonnenbahn ist das Bild ohne JavaScript vollständig. Das Skript `windrose-boot.js` blendet die Umschaltung ein und ersetzt die nativen `<title>`-Tooltips durch gestaltete. Es holt nichts nach: **alle Zeiträume der Umschaltung werden serverseitig mitgerendert**, die inaktiven Rosen liegen mit `hidden` im Markup. Kein AJAX, keine Nonce, verträglich mit jedem Seitencache.
4. **Beaufort-Klassen mit festen km/h-Grenzen.** Windstille < 1 km/h; Klasse 1: 1–5; 2: 6–11; 3: 12–19; 4: 20–28; 5+: ab 29 km/h. Fünf Klassen statt sechs: in fünf Monaten Leipzig gab es vier Böen über 39 km/h, und eine sechsstufige Blaurampe bestand die Kontrastprüfung nicht. Die Grenzen bleiben intern km/h (so speichert Netatmo); angezeigt werden sie umgerechnet in der Windeinheit des Plugins (`wind_unit`: km/h, m/s, mph, kn) über `NAWS_Helpers::format_value( 'WindStrength', … )`.
5. **Farben aus dem Erscheinungsbild.** Neue Gruppe „Windrose" mit sieben Schlüsseln, Ausgabe als CSS-Variablen, Live-Vorschau im Backend. Beschriftungen nehmen die vorhandenen Textfarben des Themes.
6. **Himmelsrichtungen zentral übersetzen.** `NAWS_Helpers::degrees_to_compass()` liefert heute hart `N, NNE, …`; deutsche Besucher sehen in der Vorhersage der Live-Karte „ESE" statt „OSO". Die 16 Kürzel wandern als übersetzbare Labels nach `naws_label()`; die Hilfsfunktion und die Windrose nutzen dieselben. Das repariert die Live-Karte nebenbei.

## 3. Shortcode

```
[naws_windrose period="90d" measure="wind" sectors="16" from="" to="" show="legend,summary" switcher="yes" size="" title=""]
```

| Attribut | Werte | Standard | Regel |
|---|---|---|---|
| `period` | `7d`, `30d`, `90d`, jedes `Nd` (1–3660), `year` (laufendes Kalenderjahr), `all` (alles Vorhandene) | `90d` | Ungültiges fällt auf `90d`. Wird ignoriert, sobald `from` oder `to` gesetzt ist. |
| `from`, `to` | `Y-m-d` | leer | Fester Bereich, Tagesgrenzen in der Zeitzone der Site, `to` einschließlich. Nur `from`: bis heute. Nur `to`: ab dem ältesten Rohwert. Ungültiges Datum → Attribut wird ignoriert. `from > to` → beide ignoriert. Ein fester Bereich schaltet die Umschaltung ab. |
| `measure` | `wind`, `gust`, `both` | `wind` | `both` rendert beide Messgrößen und gibt der Umschaltung ein zweites Paar Schalter „Wind / Böen". Ungültiges → `wind`. |
| `sectors` | `16`, `8` | `16` | Alles andere → `16`. Bei 8 Sektoren heißen die Richtungen N, NO, O, SO, S, SW, W, NW; Sektorbreite 45°. |
| `show` | Liste aus `legend`, `summary`, `table` | `legend,summary` | Unbekannte Einträge werden ignoriert. Ohne `table` bleibt die Tabelle im Markup, aber `screen-reader-text`. Leere Liste (`show=""`) zeigt nur die Rose. |
| `switcher` | `yes`, `no` | `yes` | Umschaltung mit den Zeiträumen 7 Tage / 30 Tage / 90 Tage / dieses Jahr / alles; der aktive ist `period`. Steht `period` auf einem anderen `Nd`, kommt dieser als sechster Schalter hinzu. Bei `from`/`to` immer `no`. |
| `size` | 200–1200 | leer | Höchstbreite der Rose in Pixeln als `max-width` am Wurzelelement; leer heißt fließend bis 640 px. |
| `title` | Text | `null` | `null` → `naws_label( 'wr_title' )` („Wind rose"); leerer Text → keine Überschrift, wie bei Rekorden und Sonnenbahn. |

Die Attribute werden im Shortcode-Handler durch `shortcode_atts()` geführt und **im Template gecastet und gegen Whitelists geprüft**; Ausgabe ausschließlich über `esc_attr()`/`esc_html()`. Das entspricht den bestehenden Templates.

## 4. Datenweg

### 4.1 Klasse `NAWS_Windrose` (`includes/class-naws-windrose.php`)

Statische Methoden, keine Instanz, wie `NAWS_Records`. Wird in `xtx-integration-for-netatmo.php` per `naws_require()` geladen (`tests/test-main-requires.php` verlangt das).

| Methode | Zweck | Reinheit |
|---|---|---|
| `range( array $atts ): array` | Löst `period`/`from`/`to` in `[ 'from' => int, 'to' => int, 'mode' => 'period'\|'fixed', 'key' => '90d' ]` auf (Unix-Zeit, Tagesgrenzen der Site-Zeitzone). `all` liefert `from = 0`. | rein bis auf `wp_date()`/`wp_timezone()` |
| `switch_keys( array $atts ): array` | Die Zeitraum-Schlüssel der Umschaltung in Reihenfolge: `['7d','30d','90d','year','all']`, ergänzt um ein abweichendes `period`. | rein |
| `bin( float $kmh ): int` | Klasse 0–4 nach Abschnitt 2.4; `-1` für Windstille. | rein |
| `sector( float $deg, int $sectors ): ?int` | `floor( fmod( $deg + w/2, 360 ) / w )` mit `w = 360 / $sectors`, `% $sectors`; `null` für Werte außerhalb 0–360. | rein |
| `query( string $measure, int $from, int $to, int $sectors ): array` | Führt die Bündelungsabfrage aus (Abschnitt 4.2), liest den Transient, schreibt ihn. | DB |
| `shape( array $rows, int $sectors, int $max_at, int $first ): array` | Baut aus den Gruppenzeilen die Rose (Abschnitt 4.3). | rein |
| `rose( string $measure, array $range, int $sectors ): array` | `query()` + Spitzenzeitpunkt + `shape()`. | DB |
| `arc( float $a0, float $a1, float $r0, float $r1, float $cx ): string` | Der SVG-Pfad eines Ringsektors: äußerer Bogen im Uhrzeigersinn, innerer zurück. 0° ist Norden, Winkel wachsen im Uhrzeigersinn, `x = cx + r·sin`, `y = cx − r·cos`. | rein |
| `compass( int $i, int $sectors ): string` | Kürzel des Sektors über `naws_label( 'compass_…' )`. | rein |

### 4.2 Die Abfrage

Eine Abfrage je Messgröße und Zeitraum; `$t` ist `$wpdb->prefix . NAWS_TABLE_READINGS`, die Parameternamen und Grenzen kommen aus `$wpdb->prepare()`, die Sektorbreite ist ein geprüfter Integer.

```sql
SELECT
  CASE WHEN s.value < 1 THEN -1
       WHEN a.value < 0 OR a.value > 360 THEN -2
       ELSE FLOOR(MOD(a.value + %f, 360) / %f) MOD %d END AS sector,
  CASE WHEN s.value < 1 THEN -1 WHEN s.value < 6 THEN 0 WHEN s.value < 12 THEN 1
       WHEN s.value < 20 THEN 2 WHEN s.value < 29 THEN 3 ELSE 4 END AS bin,
  COUNT(*) AS n, SUM(s.value) AS sum_v, MAX(s.value) AS max_v, MIN(s.recorded_at) AS first_at
FROM {$t} s
JOIN {$t} a ON a.module_id = s.module_id AND a.recorded_at = s.recorded_at AND a.parameter = %s
WHERE s.parameter = %s AND s.recorded_at BETWEEN %d AND %d
GROUP BY sector, bin
```

`sector = -1` ist Windstille (Richtung egal), `sector = -2` eine ungültige Richtung (zählt zur Gesamtzahl, aber zu keinem Strahl; Leipzig hat 89 solche Zeilen). Der Zeitpunkt der Spitze kommt aus einer zweiten, kleinen Abfrage: `SELECT recorded_at FROM {$t} WHERE parameter = %s AND recorded_at BETWEEN %d AND %d ORDER BY value DESC, recorded_at DESC LIMIT 1`.

Cache: Transient `naws_cache_windrose_` + `md5( wp_json_encode( [ $measure, $from, $to, $sectors ] ) )`, Laufzeit eine Stunde. Der Sync leert ihn ohnehin nach jedem Lauf. Für `period`-Zeiträume ist `to` das Tagesende, damit der Schlüssel innerhalb eines Tages stabil bleibt.

### 4.3 Die Rose als Datenstruktur

```php
[
  'n'       => 17836,        // alle gepaarten Messungen im Zeitraum, inkl. Windstille und ungültiger Richtung
  'calm'    => 20,
  'invalid' => 89,
  'mean'    => 5.4,          // km/h, über alle n
  'max'     => 22.0,         // km/h
  'max_at'  => 1779602428,   // Unix-Zeit der Spitze, 0 wenn n = 0
  'first'   => 1776771809,   // ältester Rohwert im Zeitraum, für „Messungen ab …"
  'sectors' => [             // Index 0 = N, im Uhrzeigersinn
    [ 'n' => 3043, 'share' => 0.1706, 'mean' => 4.0, 'max' => 16.0, 'bins' => [ 2701, 328, 14, 0, 0 ] ],
    …
  ],
  'top'     => [ 1, 14, 0 ], // die drei häufigsten Sektoren, absteigend
  'ring'    => 0.25,         // Skalenmaximum: Anteil des längsten Strahls auf 5 % aufgerundet, mindestens 0.05
]
```

`share` ist der Anteil an `n`; die Summe der `bins` eines Sektors ist sein `n`. Bei `n = 0` liefert `shape()` die leere Struktur, und das Template gibt einen Satz aus („Keine Windmessungen in diesem Zeitraum") statt einer leeren Rose.

## 5. Darstellung

### 5.1 Markup

```html
<div class="naws-wrap naws-wr" data-naws-windrose style="max-width:420px">   <!-- style nur bei size -->
  <h3 class="naws-wr-title">Windrose</h3>
  <div class="naws-wr-switch" hidden>                                          <!-- nur bei switcher=yes; JS entfernt hidden -->
    <div class="naws-wr-seg" role="group" aria-label="Zeitraum">
      <button type="button" data-period="7d" aria-pressed="false">7 Tage</button> …
    </div>
    <div class="naws-wr-seg" role="group" aria-label="Messgröße">…</div>       <!-- nur bei measure=both -->
  </div>
  <div class="naws-wr-panel" data-period="90d" data-measure="wind">           <!-- eine je Kombination, inaktive hidden -->
    <p class="naws-wr-meta">Wind, letzte 90 Tage · 11.635 Messungen</p>
    <figure class="naws-wr-figure">
      <svg class="naws-wr-svg" viewBox="0 0 660 660" role="img" aria-label="…">…</svg>
      <figcaption class="naws-wr-caption">Messungen ab 21.04.2026</figcaption>   <!-- nur wenn first > from -->
    </figure>
    <dl class="naws-wr-summary">…</dl>
    <ul class="naws-wr-legend">…</ul>
    <table class="naws-wr-table screen-reader-text">…</table>
  </div>
</div>
```

Der Wurzelklasse `naws-wrap` verdankt die Rose die CSS-Variablen aus `NAWS_Colors::get_inline_css()`. Die Tabelle ist die Screenreader-Fassung der Rose; das SVG trägt `aria-label` mit Zeitraum, Anzahl und Hauptrichtung, seine Textelemente sind `aria-hidden`.

### 5.2 Geometrie (viewBox 660 × 660)

Wie in der Demo: Mittelpunkt (330, 330), Nabe `R0 = 40`, längster Strahl `RMAX = 248`. Radius eines Anteils `r = R0 + share / ring · (RMAX − R0)`. Ringe bei jedem 5 % bis `ring`, beschriftet bei 107° (Ostsüdost, dort ist bei Leipzig fast nichts; die Beschriftung trägt einen Freistellrand in Flächenfarbe). Sektor `i` von `i·w − w/2` bis `i·w + w/2` mit `w = 360 / sectors`. Klassen werden von innen nach außen gestapelt; jeder Teilpfad hat einen 2-px-Rand in Flächenfarbe, was die Lücken zwischen Klassen und Nachbarn ergibt. Richtungskürzel bei Radius 278 (Haupt- und Nebenrichtungen fett/normal, Zwischenrichtungen kleiner und gedämpft); die Prozentwerte der drei häufigsten Richtungen bei Radius 300, Anker je nach Winkel (`start`/`end`/`middle`), damit sie bei West und Ost nicht ins Kürzel laufen. Nabe: Kreis `R0 − 3` mit „Windstille" und Prozent.

Jeder Sektor ist eine `<g class="naws-wr-sector" data-i="…">` mit den Klassenpfaden, einem unsichtbaren Trefferpfad über den vollen Radius und einem `<title>` (Kürzel, Richtung, Anteil, Anzahl, Mittel, Spitze) als Tooltip ohne Skript.

### 5.3 Skript `windrose-boot.js`

Registriert als `naws-windrose-boot` (abhängig von nichts; kein jQuery), nur bei `switcher="yes"` oder `measure="both"` eingereiht. Es findet alle `[data-naws-windrose]`, entfernt `hidden` von der Umschaltung, schaltet die Panels per `data-period`/`data-measure` um und setzt `aria-pressed`. Tooltips: bei `mouseenter`/`focus` eines Sektors ein `<div class="naws-wr-tip">` mit dem Inhalt des `<title>`, bei Berührung und ohne Maus bleibt der native Tooltip. Alles, was das Skript braucht, steht als `data-*` im Markup; es druckt nichts inline und lädt nichts nach.

### 5.4 CSS

Ein `.naws-wr*`-Block am Ende von `frontend.css`, alle Farben als `var(--naws-wr-…, Fallback)` bzw. die vorhandenen `--naws-text-*`. Klassenpfade: `.naws-wr-b1` … `.naws-wr-b5` mit `fill: var(--naws-wr-b1)`, Ring `stroke: var(--naws-wr-grid)`, Nabe `fill: var(--naws-wr-calm)`. Umschaltknöpfe im Stil der Legendenpillen der Heatmap (`.naws-leg-pill`). Unter 600 px Breite wachsen die SVG-Schriftgrade, weil das Bild mitskaliert.

## 6. Farben im Erscheinungsbild

| Schlüssel | Bedeutung | Vorgabe |
|---|---|---|
| `windrose_b1` … `windrose_b5` | Beaufort 1, 2, 3, 4, 5+ | `#86b6ef`, `#5598e7`, `#2a78d6`, `#1c5cab`, `#0d366b` |
| `windrose_grid` | Ringe | `#dbe3ea` |
| `windrose_calm` | Nabe (Windstille) | `#e9eff5` |

Die Vorgabe ist die Rampe der Demo, geprüft mit dem Palettenprüfer (eine Farbe, gleichmäßig heller nach dunkler, jede Stufe unterscheidbar, die hellste hebt sich von Weiß ab).

Einbau nach dem Heatmap-Muster: Einträge in `NAWS_Colors::DEFAULTS` (Sanitizing folgt daraus automatisch), Konstante `WINDROSE_KEYS`, Gruppe `windrose` in `get_groups()` mit Label `appearance_group_windrose`, sieben Variablen in `get_inline_css()`, Label-Zeilen und Gruppentitel in `admin/views/appearance.php`, ein Reiter `data-pane="windrose"` mit Farbfeldern und einer **statischen Vorschau-Rose** (kleines SVG mit acht Sektoren und allen fünf Klassen, Pfade mit `class="naws-pv-wr-b1"` …), die der Vorschau-Zweig `if (group === 'windrose')` per `fill` bzw. `stroke` nachfärbt.

## 7. Sprache

Alle sichtbaren Texte über gettext mit der Text-Domain des Plugins. Zur Laufzeit zusammengesetzte Schlüssel gehen über `naws_label()`:

- `compass_n`, `compass_nne`, … `compass_nnw` (16): `_x( 'N', 'compass direction', … )` usw. Deutsch: N, NNO, NO, ONO, O, OSO, SO, SSO, S, SSW, SW, WSW, W, WNW, NW, NNW. Norwegisch: N, NNØ, NØ, ØNØ, Ø, ØSØ, SØ, SSØ, S, SSV, SV, VSV, V, VNV, NV, NNV.
- `compass_long_n` … (16 Langformen: „North", „North-northeast" …) für Tooltip, Tabelle und `aria-label`.
- `wr_title` („Wind rose"), `wr_aria`, `wr_meta_wind`/`wr_meta_gust` („Wind, 10-minute mean" / „Gusts, 10-minute peak"), Zeiträume (`wr_period_7d` „last 7 days", …, `wr_period_year` „this year", `wr_period_all` „everything recorded"), `wr_from` („readings from %s"), Kennzahlen (`wr_main` „Main direction", `wr_second` „Second direction", `wr_mean` „Mean", `wr_peak_wind` „Strongest wind", `wr_peak_gust` „Strongest gust", `wr_calm` „Calm", `wr_calm_note` „below %s", `wr_readings` „%s readings"), Klassen (`wr_bft` „Bft %d", `wr_bft_range` „%1$s–%2$s", `wr_bft_from` „from %s"), Tabellenköpfe, `wr_empty` („No wind readings in this period.").

`NAWS_Helpers::degrees_to_compass()` liefert künftig `naws_label( 'compass_' . $code )`; die Rechnung bleibt. Nach der letzten Textänderung werden `.pot`, beide `.po` und beide `.mo` mit den fünf Befehlen aus `docs/i18n/README.md` neu gebaut; die deutschen und norwegischen Übersetzungen der neuen Sätze werden dabei in die `.po` eingetragen, nicht nachträglich in GlotPress.

## 8. Tests

`tests/test-windrose.php` (ohne WordPress, mit `i18n-stubs.php`):
- `sector()`: 0° → 0, 11,24° → 0, 11,25° → 1, 348,75° → 0, 359,9° → 0, 360 → 0, −1 → null, 361 → null; bei 8 Sektoren 22,5° → 1, 337,5° → 0.
- `bin()`: 0,9 → −1, 1 → 0, 5,9 → 0, 6 → 1, 11,9 → 1, 12 → 2, 19,9 → 2, 20 → 3, 28,9 → 3, 29 → 4, 41 → 4.
- `range()`: `90d` endet heute (Tagesende), beginnt vor 89 Tagen (Tagesanfang); `year` beginnt am 1. Januar; `all` beginnt bei 0; `from`/`to` mit Tagesgrenzen; nur `from`; nur `to`; `from > to` ignoriert; ungültiges Datum ignoriert; `mode`.
- `switch_keys()`: Standardfolge; `period="14d"` hängt `14d` an; `from` → leer.
- `shape()`: aus Gruppenzeilen (inkl. Windstille und ungültiger Richtung) `n`, `calm`, `invalid`, `mean`, `max`, `share`, `bins`, `top`, `ring` (0,2 bei 17,1 %; 0,05 bei 0,3 %); leere Zeilen → leere Struktur.
- `arc()`: Pfad beginnt mit `M`, enthält zwei `A`, endet mit `Z`; Sektor 0 ist symmetrisch zur Senkrechten (gleiche `y`-Werte der beiden Außenpunkte).

`tests/test-windrose-render.php` (Template über `render_windrose( array $atts, array $naws_roses )` mit Output-Buffering):
- Standard: ein `<svg role="img"` mit `aria-label`, 16 `naws-wr-sector`, Ringe, fünf Legendenzeilen, `<dl class="naws-wr-summary"`, Tabelle mit `screen-reader-text` und 16 Zeilen, `<title>` je Sektor, drei Prozentbeschriftungen, Nabe mit Windstille.
- `show="table"` → Tabelle ohne `screen-reader-text`; `show=""` → keine Legende, keine Kennzahlen.
- `switcher="yes"` → `naws-wr-switch` mit `hidden` und fünf Knöpfen, fünf Panels, genau eines ohne `hidden`; `switcher="no"` → keine Umschaltung, ein Panel; `from="2026-05-01"` → keine Umschaltung.
- `measure="both"` → zehn Panels und zwei Schaltergruppen; `sectors="8"` → acht Sektoren, Kürzel `NE` statt `NNE`.
- `size="420"` → `max-width:420px`; `size="50"` → kein Style.
- `title=""` → kein `<h3>`; `title="Mein Wind"` → `<h3>Mein Wind</h3>`.
- Leere Rose → Satz `wr_empty`, kein `<svg>`.
- Negativ: kein `<style`, kein `<script`, keine MAC-Adresse, kein `onclick`.
- Einheit `ms`: Legende zeigt `0,3–1,4 m/s` statt km/h (über `$GLOBALS['naws_test_options']`).

Dazu: `test-main-requires.php` (neue Klassendatei), `test-mo-files.php` (Kataloge), die Compass-Prüfung in einem bestehenden Helpers-Test (`degrees_to_compass( 112.5 )` → Label `compass_ese`). Suite: `for f in tests/test-*.php; do php $f; done`; `composer lint` ohne Befund.

## 9. Dokumentation

- `admin/views/shortcodes.php`: Karte mit Attributtabelle und Beispielen (`[naws_windrose]`, `[naws_windrose period="year" measure="both"]`, `[naws_windrose from="2026-05-01" to="2026-08-31" sectors="8" title="Sommerwind"]`).
- `readme.txt`: Zeile in der Shortcode-Liste, Absatz im Changelog von 1.9.12 (beim Release), `README.md`: Tabellenzeile.
- `CHANGELOG.md` `[Unreleased]`: `### Added` Windrose, `### Fixed` Himmelsrichtungen in der Vorhersage der Live-Karte übersetzt.
- `docs/site/website.de.json`/`.en.json`: Vorhaben `windrose` mit `"ab": null` (erscheint als „In Arbeit"), `"aktualisiert"` mitgezogen; Bild folgt beim Release.

## 10. Sicherheit (WordPress-Vorgaben, Frank: „Sehr wichtig")

Die Regeln des Plugin-Review-Teams (Nonces, Sanitierung, Escaping, SQL, keine Custom-Wrapper) gelten für jede Zeile dieses Vorhabens. Konkret:

- **Eingaben.** Die einzige Eingabe sind Shortcode-Attribute. Sie werden im Template sofort nach `shortcode_atts()` gecastet und gegen Whitelists geprüft: `in_array( $measure, [ 'wind', 'gust', 'both' ], true )`, `sectors` → `absint()` und Vergleich mit 8/16, `period` → `preg_match( '/^(\d{1,4})d$/' )` bzw. Wortliste, `from`/`to` → `preg_match( '/^\d{4}-\d{2}-\d{2}$/' )` plus `checkdate()`, `size` → `absint()` mit Bereichsprüfung, `show` → `array_intersect` mit der erlaubten Liste, `title` → `sanitize_text_field()`. Was die Prüfung nicht besteht, wird durch den Standardwert ersetzt, nie „repariert".
- **Ausgabe.** Jedes `echo` im Template trägt `esc_html()` oder `esc_attr()` direkt und sichtbar; Zahlen werden vor der Ausgabe als String formatiert und dann escaped. Das SVG wird nicht als fertiger String zusammengebaut und roh ausgegeben, sondern Element für Element im Template geschrieben, jede Koordinate, Klasse und Beschriftung einzeln escaped. Deshalb ist kein `wp_kses()` mit SVG-Allowlist nötig und kein Custom-Wrapper. Kein nacktes `echo $var`.
- **SQL.** Beide Abfragen laufen durch `$wpdb->prepare()` mit `%s`, `%d`, `%f`. Der Tabellenname ist `$wpdb->prefix . NAWS_TABLE_READINGS` und wird wie in `get_heatmap_year()` mit dem dokumentierten `phpcs:ignore`-Kommentar interpoliert („table name is prefix + constant"). In die Abfrage gelangen nur Werte, die vorher gecastet wurden (Integer-Zeitstempel, die Sektorbreite als `360 / $sectors` mit `$sectors ∈ {8, 16}`, Parameternamen aus einem festen Array). Keine Interpolation von Attributwerten, kein `ORDER BY` aus Eingaben.
- **Nonces und Berechtigungen.** Das Vorhaben hat kein Formular, keinen AJAX-Aufruf und liest weder `$_GET` noch `$_POST` noch `$_REQUEST`. Die Umschaltung arbeitet rein im Browser über vorgerendertes Markup. Deshalb ist kein Nonce nötig; wo keiner nötig ist, wird auch keiner vorgetäuscht. Die Ausgabe ist öffentlich wie jeder andere Frontend-Shortcode und enthält nur aggregierte Wetterwerte, keine Modul-IDs, keine MAC-Adressen (der Render-Test prüft das).
- **Einstellungen.** Die sieben Farbschlüssel gehen den bestehenden Weg: `map_deep( wp_unslash( … ), 'sanitize_text_field' )` in `NAWS_Admin`, danach `NAWS_Colors::sanitize()` mit Hex-Prüfung; ungültige Werte fallen auf die Vorgabe. Die Vorschau im Backend liest nur die Eingabefelder und setzt `fill`/`stroke`, kein `eval`, kein `innerHTML` aus Eingaben.
- **Skripte und Styles.** Das Skript ist eine registrierte Datei (`wp_register_script` mit `NAWS_VERSION`), kein Inline-Skript, kein `wp_add_inline_script()`, kein `ob_start()` außerhalb der Shortcode-Methode. Das einzige Inline-Style ist `max-width:<int>px` aus `absint()` bei gesetztem `size`; die Farben kommen als CSS-Variablen aus dem vorhandenen Inline-Style-Block.
- **Dateien.** Jede neue PHP-Datei beginnt mit `if ( ! defined( 'ABSPATH' ) ) exit;`, Templates tragen die beiden `phpcs:disable`-Zeilen für die Variablennamen wie ihre Geschwister. Transient-Schlüssel entstehen aus geprüften Werten über `wp_json_encode()` und `md5()`.
- **Prüfung.** `composer lint` (WordPress.Security, WordPress.DB, WordPress.WP.I18n, PrefixAllGlobals) ohne Befund, und vor dem Release ein Lauf von **Plugin Check (PCP)** auf dev gegen den Stand von `main`, so wie das Review-Team prüft.

## 11. Nicht enthalten

Keine Rose aus der Tagestabelle, kein REST-Endpunkt, keine Animation, keine Monatsrosen (die Demo hatte sie; sechs Rosen im Shortcode wären ein eigenes Vorhaben), kein Eintrag im Seitenleisten-Widget, kein Vergleich zweier Zeiträume.
