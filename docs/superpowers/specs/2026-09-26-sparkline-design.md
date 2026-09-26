# Sparkline: `[naws_sparkline]`

**Datum:** 2026-09-26
**Betrifft:** neu `includes/class-naws-sparkline.php`, `templates/sparkline.php`, `assets/js/sparkline-boot.js`, Tests; geändert `class-naws-shortcodes.php`, `class-naws-colors.php`, `class-naws-database.php`, `class-naws-admin.php`, `class-naws-labels.php`, `templates/weather-widget.php`, `admin/views/appearance.php`, `admin/views/rest-api-docs.php`, `admin/views/shortcodes.php`, `frontend.css`, `admin.css`, Kataloge, Doku
**Auslöser:** Frank, 16.09.2026 nach der Demo „Sparkline Leipzig" (https://claude.ai/artifact/WqBPakvVZjQKA8qfntjzgD): „die Sparklines sehen interessant aus"; 19.09.: kommt in die nächste Version; 26.09.: „mit Zusätzen, bitte denk daran, dass man sie im Backend farblich auch anpassen können muss, nicht mit color im Shortcode"
**Ziel-Release:** 2.1.0

---

## 1. Was die Sparkline zeigt

Ein Verlaufsdiagramm in Wortgröße: so hoch wie eine Textzeile, so breit wie ein Wort, ohne Achsen, Beschriftung oder Legende. Es steht neben der Zahl, zu der es gehört, und zeigt nur, wie es dahin kam: rauf oder runter, ruhig oder unruhig. Der Punkt am rechten Ende ist der letzte Wert. Wer einen genauen Wert will, fährt mit der Maus darüber (oder tippt darauf) und bekommt Uhrzeit und Wert in einer Sprechblase.

Drei Formen:

- **Linie** für alle Größen außer Regen, optional mit Tief- und Hoch-Punkt.
- **Balken** für Regen: Mengen je Zeitabschnitt von der Grundlinie aus.
- **Band** für Zeiträume aus der Tagestabelle: die Fläche zwischen Tagestief und Tageshoch, darauf die Linie des Tagesmittels.

Einsatzorte: frei im Fließtext per Shortcode, und auf Wunsch in `[naws_weather_widget]` (Temperatur im Kopf, Regen und Wind in den Kacheln). Die Infobar bekommt **keine** Sparklines (Frank, 26.09.).

## 2. Entscheidungen

1. **Serverseitiges SVG, Skript nur als Aufsatz.** Die Kurve ist ohne JavaScript vollständig (RSS, Seitencache, abgeschaltetes JS). `sparkline-boot.js` ergänzt ausschließlich die Sprechblase. Kein AJAX, keine Nonce. Gleiches Muster wie Windrose und Sonnenbahn.
2. **Farben nur im Backend.** Eigener Reiter „Sparkline" im Erscheinungsbild mit acht Schlüsseln (Abschnitt 6). **Kein `color`-Attribut am Shortcode** (Frank, 26.09.). Eine Linienfarbe für alle Größen (Frank, 26.09.), dazu eine zweite für dunklen Grund.
3. **Kein eigener Transient.** Die Rohwerte kommen über `NAWS_Database::get_readings()`, die Tageswerte über `get_daily_summaries()`; beide cachen bereits als `naws_cache_`-Transient (10 min bzw. `TTL_DAILY`) und werden beim Sync von `flush_caches()` geleert. Der Cache-Schlüssel dort ist der Hash der Abfrage-Argumente; deshalb wird das Zeitfenster der Rohwerte auf 5-Minuten-Schritte gerundet (Ende = nächste volle 5 Minuten), sonst entstünde bei jedem Seitenaufruf ein neuer Transient. Die Umrechnung in Geometrie ist bei höchstens 200 Punkten billiger als ein weiterer Options-Zugriff. (Im Entwurfsgespräch war ein eigener 5-Minuten-Transient genannt; er brächte nur eine zweite Cache-Schicht mit eigener Ablaufzeit.)
4. **Modul aus `param` abgeleitet.** Ohne `module` sucht sich die Sparkline das Modul, das die Größe misst. `[naws_value]` gibt fest `outdoor` vor, was bei Druck jedes Mal `module="indoor"` erzwingt; das wird hier nicht wiederholt.
5. **Anzeige-Einheit vor der Geometrie.** Jeder Wert läuft durch `NAWS_Helpers::format_value()` (°F, mph, m/s, kn, inHg, in), bevor Kurve, Sprechblase und Vorlesetext entstehen. Alle drei zeigen dieselben Zahlen wie der Rest der Seite.
6. **Nichts statt Fehlermeldung.** Eine Sparkline ohne Daten gibt einen leeren String zurück. Sie steht mitten im Fließtext; ein Rahmen oder „Keine Daten" würde den Satz zerreißen.
7. **Widget: Vorgabe aus.** Bestehende Widgets sehen nach dem Update unverändert aus (www fährt Auto-Update). Einschalten über das Erscheinungsbild oder `sparklines="1"` am Shortcode.

## 3. Shortcode

```
[naws_sparkline param="Temperature" hours="24" days="" module="" width="" height="" show="none" type="" band=""]
```

| Attribut | Werte | Standard | Regel |
|---|---|---|---|
| `param` | Rohwerte: `Temperature`, `Humidity`, `Pressure`, `WindStrength`, `GustStrength`, `Rain`, `CO2`, `Noise`. Tagesspalten: `temp_avg`, `temp_min`, `temp_max`, `humidity_avg`, `pressure_avg`, `rain_sum`, `wind_avg`, `gust_max`, `co2_avg`, `noise_avg` | `Temperature` | Positivliste, Groß-/Kleinschreibung wie angegeben. Unbekannt → leere Ausgabe. Eine Tagesspalte ohne `days` bekommt `days="30"`; eine Rohgröße mit `days` wird zur leeren Ausgabe (die Tagestabelle kennt `Rain`, nicht `rain_sum`; kein stilles Umdeuten). |
| `hours` | 1–168 | `24` | Ganzzahl, auf die Grenzen gesetzt (500 → 168, 0 → 1). Nur für Rohwerte. |
| `days` | 2–366 | leer | Ganzzahl, auf die Grenzen gesetzt. Gesetzt → Quelle ist die Tagestabelle; `hours` wird ignoriert. Zeitraum: die letzten `days` Kalendertage bis einschließlich heute. |
| `module` | `outdoor`, `indoor`, `wind`, `rain`, `in-<name>`, MAC | leer | Nur für Rohwerte. Auflösung über `NAWS_Helpers::resolve_module_ref()` (kennt die vier Aliase, `in-<name>` und die MAC ohne Rücksicht auf Groß-/Kleinschreibung). Leer → Ableitung nach 4.2. Modul nicht vorhanden oder inaktiv → leere Ausgabe. Mit `days` ohne Wirkung (Stationszeile, siehe 4.2). |
| `width` | 20–600 | leer | Pixel, auf die Grenzen gesetzt. Leer → viewBox-Breite 80, dargestellt als `4.6em` (wächst mit der Schrift, siehe 5.3). |
| `height` | 10–200 | leer | Pixel, auf die Grenzen gesetzt. Leer → viewBox-Höhe 18, dargestellt als `1.05em`. |
| `show` | `none`, `value`, `minmax` | `none` | `value`: der letzte Wert mit Einheit hinter der Kurve (bei Regen die Summe des Zeitraums). `minmax`: Tief- und Hoch-Punkt auf der Linie. Unbekannt → `none`. |
| `type` | `line`, `bars` | leer | Leer → `bars` für `Rain`/`rain_sum`, sonst `line`. `line` gilt für jede Größe; `bars` nur für Regen (ein Balken ist eine Summe, und eine Summe von Temperaturen oder Drücken hat keine Bedeutung) — bei allen anderen Größen wird `bars` zu `line`. |
| `band` | `minmax` | leer | Nur mit `days` und `param="temp_avg"`: Fläche `temp_min`–`temp_max` unter der Mittelwertlinie. In jedem anderen Fall still ignoriert. |

Die Attribute gehen durch `shortcode_atts()` und werden in `NAWS_Sparkline::normalise_atts()` gecastet, begrenzt und gegen Positivlisten geprüft (rein, testbar). Das Ergebnis ist ein Array mit festen Typen; nichts davon gelangt ungeprüft in eine Abfrage oder ins Markup.

## 4. Datenweg

### 4.1 Klasse `NAWS_Sparkline` (`includes/class-naws-sparkline.php`)

Statische Methoden, keine Instanz, wie `NAWS_Windrose`. Laden per `naws_require()` in `xtx-integration-for-netatmo.php` (`tests/test-main-requires.php` verlangt das).

| Methode | Zweck | Reinheit |
|---|---|---|
| `normalise_atts( array $atts ): ?array` | Abschnitt 3; `null` bei unbekanntem `param` oder Rohgröße mit `days`. | rein |
| `default_module( string $param ): string` | Alias nach 4.2. | rein |
| `series( array $a ): array` | Holt die Punkte `[[ts, value], …]` in Anzeige-Einheit; bei `band` zusätzlich `[[ts, min, max], …]`. | DB |
| `thin( array $pts, int $max = 200 ): array` | Dünnt aus: teilt die Zeitachse in `$max` gleiche Fenster und nimmt je Fenster den Mittelwert von Zeit und Wert. Unter `$max` Punkten unverändert. | rein |
| `buckets( array $pts, int $from, int $to, int $n = 48 ): array` | Regen: summiert Meldungen in `$n` gleiche Zeitfenster `[[start, end, sum], …]`. Fenster ohne Meldung = 0. | rein |
| `geometry( array $pts, int $w, int $h, array $opt ): array` | Linienpfad (mit Lückenbrüchen), Fläche, Endpunkt, Tief-/Hoch-Index, X-Positionen für die Sprechblase. | rein |
| `bar_geometry( array $buckets, int $w, int $h ): array` | Rechtecke der Balken, Grundlinie, X-Positionen. | rein |
| `aria( array $a, array $pts ): string` | Vorlesetext (Abschnitt 7). | rein bis auf gettext |
| `render( array $a ): string` | `series()` → Form → `templates/sparkline.php`; leerer String bei weniger als 2 Punkten (Linie/Band) bzw. ohne jede Meldung im Zeitraum (Balken, siehe 4.4). | DB |

### 4.2 Quelle und Modul

**Rohwerte** (`hours`): `NAWS_Database::get_readings( [ 'module_id' => $id, 'parameter' => $param, 'date_from' => now − hours·3600, 'date_to' => now, 'group_by' => 'raw', 'limit' => 0 ] )`. Bei 168 h sind das rund 1.000 Zeilen (10-min-Takt) bzw. 2.000 (Regen, 5-min-Takt); `limit` 0 statt der Vorgabe 5.000, damit ein dichter getakteter Regenmesser nicht abgeschnitten wird.

**Tageswerte** (`days`): Die Tagestabelle führt **eine Zeile je Station**, nicht je Modul: `compute_daily_summary()` sammelt Außen-, Regen-, Wind- und Basiswerte in eine Zeile mit `module_id = station_id` (nur Zusatz-Innenmodule haben eigene Zeilen). Die Quelle ist deshalb immer `NAWS_Calc::station_row_id( [] )`, genau wie bei `[naws_records]`; `module` hat mit `days` keine Wirkung. `NAWS_Database::get_daily_summaries( [ 'module_id' => $station, 'fields' => [ $spalte ] bzw. [ 'temp_avg', 'temp_min', 'temp_max' ], 'date_from' => …, 'date_to' => heute, 'group_by' => 'day' ] )`. Die Positivliste `$allowed_fields` dort umfasst heute nur `temp_min, temp_max, temp_avg, pressure_avg, rain_sum, gust_max`; sie wird um `humidity_avg, wind_avg, co2_avg, noise_avg` erweitert. Der Tageszweig (`group_by = 'day'`) setzt Spalten nur als `%i`-Platzhalter ein, die Aggregat-Zweige (`week`/`month`/`year`) bleiben unverändert und nutzen die neuen Felder nicht.

**Modul-Ableitung** ohne `module` (nur für Rohwerte):

| Größe | Alias |
|---|---|
| `Temperature`, `Humidity` | `outdoor` |
| `Pressure`, `CO2`, `Noise` | `indoor` |
| `WindStrength`, `GustStrength` | `wind` |
| `Rain` | `rain` |

### 4.3 Umrechnung

1. Jeder Wert → `NAWS_Helpers::format_value( $param_fuer_einheit, $wert )` als float. Für Tagesspalten die zugehörige Rohgröße (`temp_*` → `Temperature`, `pressure_avg` → `Pressure`, `rain_sum` → `Rain`, `wind_avg`/`gust_max` → `WindStrength`, `humidity_avg` → `Humidity`, `co2_avg` → `CO2`, `noise_avg` → `Noise`). Einheit dazu aus `NAWS_Helpers::get_unit()` mit derselben Rohgröße.
2. Zeitstempel: Rohwerte `recorded_at`; Tageswerte 12:00 Uhr Ortszeit des `day_date` (für gleichmäßige Abstände und die Wochentagsangabe).
3. Linie: `thin()` auf höchstens 200 Punkte.
4. Regen mit `hours`: `buckets()` mit 48 Fenstern über `[now − hours·3600, now]` (24 h → 30 min, 168 h → 3,5 h). Regen mit `days`: ein Balken je Tag, fehlende Tage = 0.

### 4.4 Geometrie

Koordinatensystem = `viewBox="0 0 {width} {height}"`, `preserveAspectRatio="none"`; die Linie trägt `vector-effect="non-scaling-stroke"`, damit sie beim Strecken nicht dicker wird.

- **Innenabstand** `pad = max(2, height/9)` rundum, damit Endpunkt und Tief/Hoch-Punkte nicht abgeschnitten werden.
- **X** linear über `[ts_erster, ts_letzter]`, **Y** linear über `[min, max]` der Reihe (bzw. `[min(temp_min), max(temp_max)]` beim Band). **Flache Reihe** (`max − min < 1e−9`): `max = min + 1`, die Linie liegt dann auf halber Höhe statt einer Division durch null.
- **Lückenbruch:** Liegt zwischen zwei Punkten mehr als das Dreifache des Median-Abstands der Reihe, beginnt ein neues Teilstück (`M` statt `L`). Die Fläche unter der Linie wird je Teilstück geschlossen.
- **Punkte** werden nicht als `<circle>` gezeichnet, sondern als Pfad der Länge null mit runder Kappe (`d="M x y h0"`, `stroke-linecap:round`, `vector-effect:non-scaling-stroke`). Ein `<circle>` würde bei `preserveAspectRatio="none"` zur Ellipse gestreckt, sobald die Sparkline breiter dargestellt wird als ihre viewBox (im Widget immer); ein Strich mit runder Kappe bleibt rund und behält seine Pixelgröße. Größen im CSS: Endpunkt 4 px, Ring darunter 7 px, Tief/Hoch 3 px.
- **Endpunkt:** am letzten Punkt, in Linienfarbe, darunter der Ring in der Grundfarbe.
- **Tief/Hoch** (`show="minmax"`): je ein Punkt in der Punktfarbe am ersten Auftreten von Minimum und Maximum.
- **Balken:** Breite `(width − 2) / n`, Zwischenraum `min(1, 0,25·Breite)`, Höhe proportional zum größten Fenster (größtes Fenster = 0 → Skala 1, alle Balken fehlen, Grundlinie bleibt). Eine Grundlinie über die ganze Breite.
- Alle Koordinaten werden mit `sprintf( '%.2F', … )` geschrieben (Punkt als Dezimaltrenner unabhängig vom Gebietsschema).

**Wann „nichts" ausgegeben wird:** weniger als 2 Punkte (Linie, Band), Modul fehlt, `param` ungültig. **Regen ohne Regen** ist dagegen ein gültiges Ergebnis: Sind im Zeitraum Meldungen vorhanden, aber alle 0, erscheint die Grundlinie allein, Vorlesetext „0 mm". Fehlen Meldungen ganz, ist der Regenmesser stumm → nichts.

## 5. Darstellung

### 5.1 Markup (`templates/sparkline.php`)

```html
<span class="naws-sl naws-sl--line">                              <!-- style="--naws-sl-w:120px;…" nur bei width/height -->

  <svg viewBox="0 0 80 18" preserveAspectRatio="none" role="img" aria-label="…" focusable="false"
       data-naws-sl='{"x":[…],"t":[…]}'>
    <path class="naws-sl-area" d="…"/>
    <path class="naws-sl-line" d="…" vector-effect="non-scaling-stroke"/>
    <path class="naws-sl-mm" d="M x y h0"/><path class="naws-sl-mm" …/>   <!-- nur show=minmax -->
    <path class="naws-sl-ring" d="M x y h0"/><path class="naws-sl-end" …/>
  </svg>
  <span class="naws-sl-val">17,6 °C</span>                          <!-- nur show=value -->
</span>
```

- Wurzel ist ein `<span>` (inline, im Fließtext zulässig). Modifier `naws-sl--line`, `naws-sl--bars`, `naws-sl--band`.
- `data-naws-sl`: JSON mit den X-Positionen (in viewBox-Einheiten, 1 Nachkommastelle) und den fertig formatierten Sprechblasen-Texten, erzeugt mit `wp_json_encode()`, ausgegeben über `esc_attr()`. Die Texte werden serverseitig formatiert (Wochentag, Uhrzeit, Wert, Einheit in der Sprache der Site); das Skript formatiert nichts selbst.
- Ohne Skript gibt es keine Sprechblase und auch keinen Browser-Tooltip je Punkt (bei 200 Punkten wären das 200 `<title>`-Elemente); der Vorlesetext und `show="value"` tragen die Information.
- Farben ausschließlich über Klassen und CSS-Variablen, keine `fill`/`stroke`-Attribute mit Farbwerten im Markup.

### 5.2 Skript `sparkline-boot.js`

Registriert in `enqueue_frontend_assets()` als `naws-sparkline-boot` (ohne Abhängigkeiten, `NAWS_Helpers::asset_version()`, im Footer), eingereiht vom Shortcode-Handler und vom Widget, sobald eine Sparkline ausgegeben wird. Kein Inline-Skript, keine globalen Variablen.

- **Ein** `pointermove`-, ein `pointerdown`- und ein `pointerleave`-Horcher auf `document` (Delegation), gleich wie viele Sparklines auf der Seite stehen.
- Nächster Punkt zur Zeigerposition über die X-Positionen aus `data-naws-sl`; die Sprechblase ist ein einziges `<div class="naws-sl-tip" role="tooltip">`, das beim ersten Bedarf an `body` gehängt und wiederverwendet wird; Position `fixed` über dem Punkt, am Bildschirmrand eingefangen.
- Touch: Antippen zeigt, Antippen außerhalb schließt.
- Text wird mit `textContent` gesetzt, nie mit `innerHTML`.
- Tastatur: Die Sparkline ist kein Bedienelement und bekommt keinen Fokus; Screenreader lesen `aria-label`.

### 5.3 CSS (`frontend.css`)

- `.naws-sl` : `display:inline-flex; align-items:center; gap:.3em; vertical-align:-.18em;` Größe aus `--naws-sl-w/-h`, Vorgabe `4.6em`/`1.05em`: Ohne `width`/`height` am Shortcode **skaliert die Kurve mit der Schrift**. Das `style`-Attribut mit Pixelwerten schreibt das Template nur, wenn sie ausdrücklich angegeben sind (je Maß einzeln).
- Linie `stroke: var(--naws-sl-line)`, Fläche `fill: var(--naws-sl-line)` mit `fill-opacity: .14`, Balken `fill: var(--naws-sl-rain)`, Band `fill: var(--naws-sl-band)`, Tief/Hoch `fill: var(--naws-sl-dots)`, Ring `fill: var(--naws-sl-ring, var(--naws-surface, #fff))`, Grundlinie der Balken `stroke: var(--naws-sl-dots)` mit `opacity:.5`.
- Sprechblase `.naws-sl-tip`: `background: var(--naws-sl-tip-bg); color: var(--naws-sl-tip-text);` kleine Schrift, `pointer-events:none`, `white-space:nowrap`.
- **Die Farbvariablen hängen an `.naws-sl` und `.naws-sl-tip` selbst**, nicht an `.naws-wrap`: Eine Sparkline steht oft außerhalb jedes Plugin-Containers im Theme-Text, und die Sprechblase hängt an `body`.
- Keine Knöpfe, also keine Hover-Regel nach Franks Knopf-Regel nötig; die Sprechblase ist kein interaktives Element.

## 6. Farben im Erscheinungsbild

Acht neue Schlüssel in `NAWS_Colors::DEFAULTS`, Konstante `SPARKLINE_KEYS` in dieser Reihenfolge, Gruppe `sparkline` in `get_groups()`, eigener Reiter „Sparkline" in `admin/views/appearance.php`:

| Schlüssel | CSS-Variable | Standard | Begründung |
|---|---|---|---|
| `sparkline_line` | `--naws-sl-line` | `#427272` | Petrol des Plugins (Grundtext, Wind-Chart) |
| `sparkline_line_dark` | `--naws-sl-line-dark` | `#7cc7c7` | helles Petrol für das dunkle Widget |
| `sparkline_rain` | `--naws-sl-rain` | `#3585b0` | wie `chart_rain` |
| `sparkline_rain_dark` | `--naws-sl-rain-dark` | `#78ace8` | helleres Blau für dunklen Grund |
| `sparkline_band` | `--naws-sl-band` | `#42727229` | Linienfarbe mit ca. 16 % Deckung |
| `sparkline_dots` | `--naws-sl-dots` | `#7aa0a0` | gedämpftes Petrol, wie `theme_text_muted` |
| `sparkline_tip_bg` | `--naws-sl-tip-bg` | `#2d5252` | wie `header_bg` |
| `sparkline_tip_text` | `--naws-sl-tip-text` | `#ffffff` | |

- Eine neue Methode `NAWS_Colors::sparkline_css(): string` liefert die Regel `.naws-sl, .naws-sl-tip { … }` mit den acht Variablen (siehe 5.3). `get_inline_css()` hängt sie an; die Admin-Seite „Erscheinungsbild" hängt sie an ihr Frontend-Stylesheet (`naws-weather-icon`), damit Widget- und Reiter-Vorschau die Sparklines in den gespeicherten Farben zeigen.
- Die Kontraste der Vorgaben werden bei der Umsetzung gemessen (Linie gegen `#ffffff` und gegen `#1c2433` für die Dunkel-Varianten, Ziel ≥ 3:1 für grafische Elemente nach WCAG 1.4.11).
- `sanitize()` braucht keine Änderung: Die Schlüssel stehen in `DEFAULTS` und laufen durch die vorhandene Hex-Prüfung.
- **Vorschau** oben im Reiter: je eine Linie mit Tief/Hoch-Punkten, Regenbalken und ein Band, nebeneinander auf hellem (`#ffffff`) und dunklem (`#1c2433`) Grund, gerendert mit `NAWS_Sparkline` aus den echten Stationsdaten; ohne Daten aus einer eingebauten Beispielreihe (fester Array im Admin-View, keine Zufallswerte). Die Vorschau folgt dem Farbwähler über die vorhandene `updatePreview()`-Logik, indem sie die CSS-Variablen am Vorschau-Container setzt.

## 7. Sprache

Neue Katalogsätze über `naws_label()` bzw. direkt `__()`/`_x()` wie in den Nachbarn, jeweils mit `translators:`-Kommentar, wo Platzhalter stehen:

- Vorlesetext Linie: „%1$s, last %2$s: from %3$s to %4$s, latest %5$s" (Größe, Zeitraum, Tief, Hoch, letzter Wert — Werte mit Einheit).
- Vorlesetext Balken: „%1$s, last %2$s: %3$s in total".
- Vorlesetext Band: „%1$s, last %2$d days: daily means from %3$s to %4$s, range %5$s to %6$s".
- Zeiträume: „%d hours", „%d days" (mit `_n()`), „24 hours" ergibt sich daraus.
- Größennamen: vorhandene Labels wiederverwenden, wo es sie gibt (Temperatur, Luftfeuchte, Luftdruck, Wind, Böen, Regen, CO₂, Lärm), sonst neu.
- Sprechblase Regen: „%1$s–%2$s · %3$s" (Beginn, Ende, Menge).
- Backend: Reitername „Sparkline", acht Farbnamen, Widget-Haken „Sparklines" mit Beschreibung, Hinweis zur Vorschau.

Kataloge `.pot`/`.po`/`.mo` (de, nb) vollständig nachziehen; `tests/test-mo-files.php` und `tests/test-makepot-comments.php` müssen grün bleiben.

## 8. Widget `[naws_weather_widget]`

- **Einstellung** `naws_settings['wgt_sparklines']` (0/1, Standard 0) im Abschnitt „Seitenleisten-Widget" des Erscheinungsbilds, neben Tage/Breite/Farbschema. Formular: verstecktes Feld `0` vor der Checkbox, weil eine leere Checkbox nicht gesendet wird und die Settings-Sanitierung mit Merge-Semantik (`$sent()`) sonst den alten Wert behielte. Sanitierung in `class-naws-admin.php`: `$clean['wgt_sparklines'] = ! empty( $input['wgt_sparklines'] ) ? 1 : 0;` innerhalb `if ( $sent( 'wgt_sparklines' ) )`.
- **Attribut** `sparklines="0|1"` am Shortcode, Vorgabe aus der Einstellung, wie `days`/`width`/`scheme`.
- **Kurven** (je 24 h, über `NAWS_Sparkline::render()` mit festen Attributen, ohne Sprechblasen-Sonderweg):
  - Kopf: `Temperature`, Linie ohne Punkte außer dem Endpunkt, unter der Zustandszeile, volle Breite des Textblocks, Höhe 16 px.
  - Kachel Regen: `Rain`, Balken, unten in der Kachel, volle Kachelbreite, Höhe 20 px.
  - Kachel Wind: `WindStrength`, Linie, unten in der Kachel, volle Kachelbreite, Höhe 20 px.
  - Breite fließend über CSS (`width:100%` im Widget-Kontext), die viewBox bleibt fest; `non-scaling-stroke` hält die Linie gleich dick.
- **Schemata** (in `frontend.css`): Die Variablen werden nicht umgebogen, sondern die Zeichenregeln lesen im dunklen Kontext eine andere Variable. So kann die Live-Vorschau im Backend jede Variable direkt am Element setzen, ohne eine Umleitung zu überschreiben.
  - hell: Ring `var(--naws-wgt-bg)`, sonst die Standardregeln.
  - dunkel (`.naws-wgt--dark` und die dunkle Hälfte der Backend-Vorschau `.naws-sl-dark`): Linie, Fläche und Endpunkt lesen `--naws-sl-line-dark`, Balken `--naws-sl-rain-dark`.
  - transparent: Linie, Fläche, Endpunkt und Balken in `currentColor`, Punkte in `--naws-wgt-muted`, kein Ring.
- **Ohne Daten** fällt die jeweilige Kurve weg; die Kachel sieht aus wie heute.
- **Vorschau** im Erscheinungsbild zeigt die Kurven, wenn der Haken gesetzt ist (die Vorschau wird serverseitig gerendert und nach dem Speichern aktualisiert; ein Live-Umschalten ohne Speichern ist nicht Teil dieses Umfangs).
- `NAWS_Widget_Data::build()` bleibt frei von WordPress: Die Sparkline-Strings werden in `sc_weather_widget()` erzeugt und dem Template als eigene Variable `$naws_wgt_spark = [ 'temp' => '', 'rain' => '', 'wind' => '' ]` übergeben.

## 9. Reiter im Backend

- `admin/views/appearance.php`: Die Reiterleiste wird zu `<nav class="nav-tab-wrapper naws-appearance-tabs">` mit `<button type="button" class="nav-tab naws-appearance-tab">`; der aktive trägt zusätzlich `nav-tab-active`. Damit gilt das WordPress-eigene Aussehen (Karteikarten mit Rahmen, aktiver Reiter hell und mit der Fläche verbunden). Die eigenen Regeln `.naws-appearance-tab{…}` in `admin.css` werden entfernt, soweit sie dem widersprechen; `flex-wrap: wrap` sorgt für den Umbruch bei neun Reitern.
- **Offenen Reiter merken:** Das Farbformular geht an `admin-post.php` (`naws_save_appearance`), der Handler leitet selbst weiter. Es bekommt ein verstecktes Feld `naws_tab`, das das Umschalt-Skript mitführt. `handle_save_appearance()` liest es mit `sanitize_key()`, prüft es gegen die Reiterliste und hängt es als `&tab=<key>` an die Weiterleitung. Der View öffnet den Reiter aus `$_GET['tab']` (ebenfalls `sanitize_key()` + Positivliste, sonst `theme`) **serverseitig**, also ohne Aufblitzen des ersten Reiters. Die Reiterliste wird dafür an einer Stelle definiert, die View und Handler beide lesen (z. B. `NAWS_Colors::appearance_tabs()`), statt als lokales `$tabs` im View.
- `admin/views/rest-api-docs.php`: `.naws-tab-bar`/`.naws-tab` auf dieselben WordPress-Klassen umstellen, damit das Plugin einheitlich aussieht.

## 10. Sicherheit (WordPress-Vorgaben)

- Jedes Shortcode-Attribut: Positivliste (`param`, `show`, `type`, `band`, `module`-Alias) oder `intval()` mit Klemmung (`hours`, `days`, `width`, `height`). `module` geht durch `sanitize_text_field()` und `NAWS_Helpers::resolve_module_ref()`, das nur bekannte Module zurückgibt; die Abfrage vergleicht gegen die Liste aktiver Module (`active_module_ids()`), ein erfundener Wert trifft nichts.
- Abfragen ausschließlich über die vorhandenen `NAWS_Database`-Funktionen (`$wpdb->prepare`, `%i` für Spaltennamen aus der Positivliste).
- SVG: Koordinaten nur als `sprintf( '%.2F' )`, Größen als `absint()`; `aria-label` über `esc_attr()`; `data-naws-sl` über `esc_attr( wp_json_encode( … ) )`; `show="value"` über `esc_html()`.
- Skript als Datei über `wp_enqueue_script()`, kein Inline-JS, keine Nonce nötig (keine Anfrage an den Server). Sprechblasentext per `textContent`.
- Backend: Farben durch `NAWS_Colors::sanitize()` (Hex-Muster), Widget-Haken auf 0/1, Formular über die vorhandene Settings-API mit ihrer Nonce, `manage_options` wie die übrigen Felder.
- Kein neuer REST-Endpunkt, keine neue Option, keine neue Tabelle.

## 11. Tests

Im Stil der vorhandenen Dateien unter `tests/` (eigenständige PHP-Skripte mit Stubs, `check()`-Helfer):

- `tests/test-sparkline.php` (rein): `normalise_atts()` (Grenzen, Positivlisten, Tagesspalte ohne `days` → 30, Rohgröße mit `days` → `null`, `type`-Automatik, `band` nur bei `temp_avg`+`days`), `default_module()`, `thin()` (unter/über 200, Mittelwerte), `buckets()` (Summen, leere Fenster, Grenzen einschließlich/ausschließlich), `geometry()` (Pfad beginnt mit `M`, Lückenbruch erzeugt zweites `M`, flache Reihe ohne Division durch null, Tief/Hoch-Index = erstes Auftreten, Endpunkt = letzter Punkt, `%.2F` auch bei `LC_NUMERIC=de_DE`), `bar_geometry()` (alle 0 → keine Rechtecke, Grundlinie vorhanden).
- `tests/test-sparkline-render.php`: Markup mit gestubbter Reihe: `role="img"`, `aria-label` vorhanden und escaped, `data-naws-sl` ist gültiges JSON mit gleich vielen X-Positionen und Texten, keine Farbwerte im Markup, `show="value"` hängt den Wert an, weniger als 2 Punkte → leerer String, Regen alle 0 → Grundlinie und „0".
- `tests/test-appearance.php` / `tests/test-color-scheme.php` erweitern: acht Schlüssel in `DEFAULTS`, Gruppe `sparkline` in `get_groups()`, acht Variablen in `get_inline_css()` in der Regel `.naws-sl, .naws-sl-tip`, ungültiges Hex wird verworfen.
- `tests/test-widget-scheme.php` / `tests/test-settings-merge.php` erweitern: `wgt_sparklines` 0/1, nicht gesendet → alter Wert bleibt, verstecktes `0` schaltet aus; Attribut überschreibt Einstellung.
- `tests/test-main-requires.php`: neue Klasse geladen. `tests/test-asset-version.php`: neues Skript versioniert.
- Kataloge: `test-mo-files.php`, `test-makepot-comments.php` grün.
- **Abnahme auf dev:** Testseite mit Fließtext (alle Größen, `show`-Varianten, `days` mit Band, Regen 24 h/168 h/30 Tage), Widget hell/dunkel/transparent mit Haken, Sprechblase mit Maus und per Playwright im Handy-Viewport (Antippen), Erscheinungsbild: Reiter-Aussehen, Umbruch bei schmalem Fenster (im `<iframe>` gemessen), Vorschau folgt dem Farbwähler, nach dem Speichern bleibt der Reiter offen.

## 12. Dokumentation

- `admin/views/shortcodes.php`: Eintrag `[naws_sparkline]` mit allen Attributen und vier Beispielen.
- `readme.txt`: Shortcode-Liste und Widget-Zeile; `README.md`: Shortcode-Tabelle; `CHANGELOG.md`: Abschnitt `[Unreleased]`. Der readme-Changelog 2.1.0 entsteht beim Schnitt.
- `docs/site/website.{de,en}.json`: Der Satz des Vorhabens `sparkline` nennt noch „in der Infobar" — streichen (bleibt `"ab": null` bis zum Schnitt).
- Website beim Schnitt (nicht Teil dieser Umsetzung): Vorhaben `sparkline` → `"ab": "2.1.0"`, „Alle 15 Shortcodes" → 16, Startseiten-Block, Live-Demos 109/183, GlotPress-Readme-Kette.

## 13. Nicht enthalten

- `color`-Attribut am Shortcode (Frank, 26.09.).
- Sparklines in der Infobar (Frank, 26.09.).
- Sparklines im Live-Dashboard `[naws_live]` oder in `[naws_current]`.
- Mehrere Größen in einer Sparkline, Achsen, Beschriftungen, Zoom.
- Live-Umschalten der Widget-Vorschau ohne Speichern.
- Innenraum-Tagesspalten (`indoor_temp_avg`, `indoor_humidity_avg`) und Zusatzmodule (Module 4) als Tagesquelle; über `module` + Rohwerte sind sie erreichbar.
