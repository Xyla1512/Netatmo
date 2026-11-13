# Modulvergleich `[naws_compare]` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Shortcode zeigt zwei beliebige Tagesreihen beliebiger Module auf einer durchgehenden Zeitachse; ein Baukasten im Backend erzeugt den Shortcode mit Live-Vorschau.

**Architecture:** Reine Klasse `NAWS_Compare` (Katalog der Reihen, Parser, Zeitraum, Zusammenführen/Verdichten, Achsen) plus dünne WP-Schicht (Zeilen holen, rendern, Admin-Katalog). Eine Vorlage schreibt Canvas + JSON-Daten in die Seite, `compare-boot.js` baut daraus das Chart.js-Diagramm — im Frontend und in der Admin-Vorschau. Die Vorschau holt per Admin-AJAX dieselbe Renderausgabe.

**Tech Stack:** PHP 8 (WordPress-Plugin), Chart.js 4.5.1 + date-fns-Adapter (im Plugin unter `assets/vendor/`), Vanilla JS ES2017, gettext über `naws_label()`, Tests als eigenständige PHP-Skripte.

**Spec:** `docs/superpowers/specs/2026-11-13-modulvergleich-design.md` (freigegeben 13.11.2026). Bei Widerspruch gilt die Spec; §11 dort enthält die nach dem Builder-Check getroffenen Festlegungen.

## Global Constraints

- Zweig `modulvergleich`; nichts pushen, mergen, taggen oder schneiden ohne Franks Ansage.
- Textdomain `xtx-integration-for-netatmo`; sichtbare Texte als `naws_label()`-Schlüssel mit Präfix `cmp_`, Platzhalter mit `/* translators: */`.
- Keine MAC-Adresse (module_id) im Frontend-HTML oder -JSON; Module nur über `NAWS_Helpers::module_ref_map()`-Referenzen (`outdoor`, `indoor`, `wind`, `rain`, `in-<slug>`).
- Reihen-Katalog exakt Spec §3 (15 Einträge; Spalten, `get_unit`-Parameter `Temperature`, `Humidity`, `CO2`, `Noise`, `Pressure`, `Rain`, `WindStrength`, `GustStrength`; Regen = Balken).
- Shortcode-Angaben: `a` Vorgabe `indoor:humidity`, `b` Vorgabe `outdoor:humidity` (`b=""` = eine Reihe), `from`/`to` `JJJJ-MM` oder `JJJJ-MM-TT`, `months` 1–120 (`from` hat Vorrang), `title`, `height` Vorgabe 360, geklemmt 200–900.
- Ohne `from`/`to`: Achse vom ersten bis letzten Tag, an dem mindestens eine Reihe einen Wert hat; `to` leer = höchstens gestern (Site-Zeitzone, `wp_date()`); `to` < `from` → tauschen.
- Lücke = `null`, Linie unterbrochen (`spanGaps: false`); Balkenachse beginnt bei 0.
- Mehr als 3 650 Tage im Zeitraum → je Reihe auf ISO-Wochen verdichten (Mittel; Regen: Summe), Datum = Montag der Woche.
- Werte in Einstellungseinheiten, umgerechnet auf dem Server mit `NAWS_Helpers::format_value()`; Einheitstext mit `get_unit()`.
- Deaktivierte Module = nicht vorhanden; Redakteure (`current_user_can('edit_posts')`) sehen Hinweise, Besucher nie Fehlertexte.
- Frontend nutzt den Transient-Cache von `get_daily_summaries()`; Admin-Vorschau mit `no_cache => true`.
- Knöpfe: eigene `:hover`/`:focus-visible` mit zwei Klassen, Palettenfarben (Franks Regel 10.09.).
- LF; Tests, `php -l`, `vendor/bin/phpcs` grün vor jedem Commit; Commits enden mit `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Reihe mit großer Lücke (Basis-Innentemperatur, 76 von 961 Tagen)** — Linie bricht an jeder Lücke ab, kein Strich quer über Monate. → Test in Task 2 (`null`-Einträge) + Abnahme Task 6.
2. **Zwei Reihen derselben Einheit, aber verschiedener Größe (Außentemperatur gegen Innentemperatur Gast)** — eine gemeinsame Achse; zwei verschiedene Einheiten → zwei Achsen. → Test in Task 2 (`axes()`).
3. **Einheiten °F / in / mph** — Tooltip und Achse in Einstellungseinheiten, Regen in Zoll mit 3 Stellen. → Test in Task 3.
4. **Tippfehler im Shortcode (`a="outdor:humidity"`) oder Größe passt nicht zum Modul (`rain:humidity`)** — Besucher sehen die andere Reihe bzw. nichts, Redakteur sieht, welche Angabe falsch ist. → Test in Task 1 + Task 3.
5. **Zwei Vergleiche auf einer Seite** — jeder zeichnet seine eigenen Daten (eindeutige IDs). → Test in Task 3.

---

### Task 1: Katalog, Parser, Zeitraum

**Files:**
- Create: `includes/class-naws-compare.php`
- Modify: `xtx-integration-for-netatmo.php` (`naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-compare.php' );` nach der Zeile für `class-naws-month-review.php`), `includes/class-naws-database.php` (`$allowed_fields` in `get_daily_summaries()`, ~Z. 1015: `indoor_temp_avg`, `indoor_humidity_avg` ergänzen)
- Test: Create `tests/test-compare.php` (Kopf/Stubs/`check()` wie `tests/test-month-review.php`)

**Interfaces:**
- Produces (rein, `public static`):
  - `series_catalogue(): array` — `[ 'outdoor' => [ 'temp' => [ 'type' => 'NAModule1', 'row' => 'station', 'col' => 'temp_avg', 'param' => 'Temperature', 'chart' => 'line', 'label' => 'cmp_q_temp' ], … ], 'indoor' => […], 'rain' => […], 'wind' => […], 'in' => […] ]` — Schlüssel `in` gilt für alle `in-<slug>`-Referenzen, `row` ist `'station'` oder `'own'`. Label-Schlüssel: `cmp_q_temp`, `cmp_q_temp_min`, `cmp_q_temp_max`, `cmp_q_humidity`, `cmp_q_co2`, `cmp_q_noise`, `cmp_q_pressure`, `cmp_q_rain`, `cmp_q_wind`, `cmp_q_gust`.
  - `parse_series( string $spec ): array` — `[ 'ok' => bool, 'ref' => string, 'qty' => string, 'error' => ''|'syntax'|'unknown_module'|'unknown_quantity' ]`; Kleinschreibung normalisiert, Leerzeichen getrimmt; prüft nur gegen den Katalog (Typ-Gruppe aus dem Referenz-Präfix), nicht gegen die Datenbank.
  - `period( array $atts, ?string $first_day, string $yesterday ): array` — `[ 'from' => ?string, 'to' => string, 'auto_from' => bool, 'auto_to' => bool ]` (Y-m-d). `from` `JJJJ-MM` → erster des Monats, `to` `JJJJ-MM` → letzter des Monats; `months=N` → `from` = erster Tag des Monats N−1 Monate vor `to`; `to` > `$yesterday` → `$yesterday`; vertauscht → tauschen; ungültiges Format → wie leer.
- [ ] **Step 1: Failing tests**

```php
check( '15 Reihen im Katalog', array_sum( array_map( 'count', NAWS_Compare::series_catalogue() ) ), 15 );
check( 'Matthew: indoor:humidity', NAWS_Compare::parse_series( 'indoor:humidity' ), [ 'ok' => true, 'ref' => 'indoor', 'qty' => 'humidity', 'error' => '' ] );
check( 'Gross/Klein + Leerzeichen', NAWS_Compare::parse_series( ' Outdoor:Humidity ' )['ok'], true );
check( 'Innenmodul-Referenz', NAWS_Compare::parse_series( 'in-gast:co2' )['ok'], true );
check( 'Tippfehler Modul', NAWS_Compare::parse_series( 'outdor:humidity' )['error'], 'unknown_module' );
check( 'Groesse passt nicht', NAWS_Compare::parse_series( 'rain:humidity' )['error'], 'unknown_quantity' );
check( 'ohne Doppelpunkt', NAWS_Compare::parse_series( 'outdoor' )['error'], 'syntax' );
check( 'Regen ist Balken', NAWS_Compare::series_catalogue()['rain']['rain']['chart'], 'bar' );
check( 'Basis innen Temp aus indoor_temp_avg', NAWS_Compare::series_catalogue()['indoor']['temp']['col'], 'indoor_temp_avg' );
$y = '2026-11-12';
check( 'from Monat', NAWS_Compare::period( [ 'from' => '2024-04' ], '2024-03-28', $y )['from'], '2024-04-01' );
check( 'to Monat = Monatsende', NAWS_Compare::period( [ 'to' => '2025-02' ], '2024-03-28', $y )['to'], '2025-02-28' );
check( 'months=24', NAWS_Compare::period( [ 'months' => '24' ], '2024-03-28', $y )['from'], '2024-12-01' );
check( 'from schlaegt months', NAWS_Compare::period( [ 'from' => '2025-01', 'months' => '3' ], '2024-03-28', $y )['from'], '2025-01-01' );
check( 'leer -> auto', [ NAWS_Compare::period( [], '2024-03-28', $y )['auto_from'], NAWS_Compare::period( [], '2024-03-28', $y )['to'] ], [ true, $y ] );
check( 'Zukunft gekappt', NAWS_Compare::period( [ 'to' => '2030-01-01' ], '2024-03-28', $y )['to'], $y );
check( 'vertauscht', array_values( array_intersect_key( NAWS_Compare::period( [ 'from' => '2025-06', 'to' => '2025-01' ], '2024-03-28', $y ), [ 'from' => 1, 'to' => 1 ] ) ), [ '2025-01-01', '2025-06-30' ] );
check( 'ungueltig wie leer', NAWS_Compare::period( [ 'from' => '2025-13' ], '2024-03-28', $y )['auto_from'], true );
$db = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'get_daily_summaries kennt indoor_humidity_avg', (bool) preg_match( "/\\\$allowed_fields\\s*=\\s*\\[[^\\]]*'indoor_humidity_avg'/", $db ), true );
```
- [ ] **Step 2:** `php tests/test-compare.php` → FAIL (Klasse fehlt).
- [ ] **Step 3:** Klasse mit den drei Funktionen, `naws_require`, Feldliste ergänzen.
- [ ] **Step 4:** `php tests/test-compare.php`, `php tests/test-main-requires.php`, Suite → grün.
- [ ] **Step 5: Commit** `"Compare: series catalogue, parser and period"`

---

### Task 2: Zusammenführen, Verdichten, Achsen

**Files:**
- Modify: `includes/class-naws-compare.php`
- Test: `tests/test-compare.php` (erweitern)

**Interfaces:**
- Consumes: Katalog-Einträge aus Task 1.
- Produces (rein):
  - `build( array $series, string $from, string $to, bool $auto_from, bool $auto_to ): array` — `$series` = Liste von `[ 'values' => [ 'Y-m-d' => ?float ], 'chart' => 'line'|'bar' ]` (0–2 Einträge). Rückgabe `[ 'dates' => string[], 'values' => float|null[][] /* je Reihe, gleiche Länge wie dates */, 'weekly' => bool ]`. Achse = jeder Kalendertag `from..to`; bei `auto_*` auf den ersten/letzten Tag mit Wert in irgendeiner Reihe zugeschnitten; fehlender Tag → `null`. Mehr als 3 650 Tage → ISO-Wochen (Montag als Datum), Linie = Mittel der vorhandenen Werte, Balken = Summe, Woche ohne Werte = `null`, `weekly = true`. Keine Reihe mit Wert → `dates = []`.
  - `axes( string $unit_a, ?string $unit_b ): array` — `[ 'a' => 'y', 'b' => 'y'|'y1'|null, 'right' => bool ]`: gleiche Einheit → beide `y`; verschieden → `b` auf `y1` rechts; `$unit_b === null` → `b = null`.
- [ ] **Step 1: Failing tests**

```php
$a = [ 'values' => [ '2025-01-01' => 50.0, '2025-01-03' => 52.0 ], 'chart' => 'line' ];
$b = [ 'values' => [ '2025-01-02' => 80.0 ], 'chart' => 'line' ];
$r = NAWS_Compare::build( [ $a, $b ], '2024-12-30', '2025-01-05', true, true );
check( 'auto zugeschnitten', $r['dates'], [ '2025-01-01', '2025-01-02', '2025-01-03' ] );
check( 'Luecke = null (a)', $r['values'][0], [ 50.0, null, 52.0 ] );
check( 'Luecke = null (b)', $r['values'][1], [ null, 80.0, null ] );
check( 'fester Zeitraum bleibt', count( NAWS_Compare::build( [ $a ], '2024-12-30', '2025-01-05', false, false )['dates'] ), 7 );
check( 'nichts -> leer', NAWS_Compare::build( [ [ 'values' => [], 'chart' => 'line' ] ], '2025-01-01', '2025-01-31', true, true )['dates'], [] );
// 3650 Tage bleiben taeglich, 3651 werden Wochen
check( '3650 Tage taeglich', NAWS_Compare::build( [ $lang3650 ], $f, $t3650, false, false )['weekly'], false );
$w = NAWS_Compare::build( [ $lang3651, $regen3651 ], $f, $t3651, false, false );
check( '3651 Tage -> Wochen', $w['weekly'], true );
check( 'Wochendatum ist Montag', gmdate( 'N', strtotime( $w['dates'][1] ) ), '1' );
check( 'Woche Linie = Mittel', $w['values'][0][1], 10.0 );  // Fixture: jede volle Woche 7× 10.0
check( 'Woche Balken = Summe', $w['values'][1][1], 7.0 );   // Fixture: jeder Tag 1.0 mm
check( 'gleiche Einheit eine Achse', NAWS_Compare::axes( '%', '%' ), [ 'a' => 'y', 'b' => 'y', 'right' => false ] );
check( 'zwei Einheiten zwei Achsen', NAWS_Compare::axes( '°C', '%' ), [ 'a' => 'y', 'b' => 'y1', 'right' => true ] );
check( 'eine Reihe', NAWS_Compare::axes( '°C', null ), [ 'a' => 'y', 'b' => null, 'right' => false ] );
```
(`$lang3650`/`$lang3651`/`$regen3651` mit `$f = '2015-01-05'` (Montag) erzeugen; Tagesanzahl inkl. beider Enden.)
- [ ] **Step 2:** → FAIL.
- [ ] **Step 3:** `build()`, `axes()`.
- [ ] **Step 4:** → grün, Suite grün.
- [ ] **Step 5: Commit** `"Compare: merge series, weekly thinning and axes"`

---

### Task 3: Shortcode, Vorlage, Frontend-Skript

**Files:**
- Modify: `includes/class-naws-compare.php` (+ `rows_for()`, `series_name()`, `render()`), `includes/class-naws-shortcodes.php` (TAGS `'naws_compare' => 'sc_compare'` nach `naws_month_review`, Kommentar „nineteen“ → „twenty“, `sc_compare()`, Skript-Registrierung `naws-compare-boot` mit Abhängigkeiten `naws-chartjs`, `naws-chartjs-adapter`), `includes/class-naws-labels.php` (`cmp_*`), `assets/css/frontend.css` (Block `.naws-cmp`)
- Create: `templates/compare.php`, `assets/js/compare-boot.js`
- Test: Create `tests/test-compare-render.php` (Stubs wie `tests/test-month-review-render.php`, `NAWS_Database::get_daily_summaries()` liefert Zeilen je `module_id`, `get_modules()` liefert Basis + Außen + Regen + zwei Innenmodule, eines davon `is_active=0`); Modify `tests/test-shortcode-atts.php` (19 → 20)

**Interfaces:**
- Consumes: Task 1–2.
- Produces:
  - `NAWS_Compare::rows_for( array $parsed, string $from, string $to, bool $no_cache ): ?array` — löst `ref` über `NAWS_Helpers::resolve_module_ref()` auf (`null` → Modul unbekannt/deaktiviert; deaktiviert: Modul aus `get_modules(false)` mit `is_active=0` erkennen und als eigener Fehler `inactive_module` melden), wählt Zeile `station` (`NAWS_Calc::station_row_id([])`) oder `own`, holt `get_daily_summaries( [ 'module_id', 'date_from', 'date_to', 'fields' => [ col ], 'group_by' => 'day', 'no_cache' ] )`, rechnet jeden Wert mit `NAWS_Helpers::format_value( param, v )` um; Rückgabe `[ 'values' => [ 'Y-m-d' => ?float ], 'chart', 'unit' => get_unit( param ), 'name' => series_name(…) ]` oder `[ 'error' => 'unknown_module'|'inactive_module'|'unknown_quantity'|'no_data' ]`.
  - `NAWS_Compare::series_name( string $ref, string $qty ): string` — „Größe · Modul“ über `cmp_series_name` (`%1$s · %2$s`); Modulname: gesetzter Anzeigename (`custom_name`), sonst für `outdoor`/`indoor`/`wind`/`rain` die Labels `cmp_mod_outdoor` „Outdoor“, `cmp_mod_indoor` „Base station“, `cmp_mod_wind` „Wind gauge“, `cmp_mod_rain` „Rain gauge“; Innenmodule `NAWS_Helpers::display_name()`.
  - `NAWS_Compare::render( array $atts, bool $no_cache = false ): string` — `$atts` nach `shortcode_atts` (Task 3 Shortcode). Erste Tag = `NAWS_Database::get_daily_data_range()['date_begin']`, gestern = `wp_date( 'Y-m-d', strtotime( '-1 day' ) )`. Leer/Fehler: Redakteure `NAWS_Records::notice( 'naws_compare', $reason, $detail )` (Mapping in `notice()` für `naws_compare` + `unknown_module|inactive_module|unknown_quantity|no_data` → `cmp_notice_*`, `$detail` = die falsche Angabe), Besucher `''`. Eine von zwei Reihen fehlerhaft → Diagramm mit der anderen + Hinweis nur für Redakteure.
- HTML-/JSON-Vertrag (Task 4 nutzt dieselbe Ausgabe in der Vorschau):
  ```
  <figure class="naws-cmp" data-naws-cmp>
    <div class="naws-cmp-bar"><figcaption class="naws-cmp-title">…</figcaption>
      <button type="button" class="naws-cmp-expand" aria-label="{cmp_expand}">…svg…</button></div>
    <div class="naws-cmp-canvas" style="height:{height}px"><canvas id="naws-cmp-{wp_unique_id}"></canvas></div>
    <script type="application/json" class="naws-cmp-data">{…}</script>
  </figure>
  ```
  JSON: `{ "dates": [...], "weekly": bool, "series": [ { "name", "unit", "chart": "line"|"bar", "axis": "y"|"y1", "decimals": int, "values": [...] } ], "right": bool, "locale": "de-DE", "decimal": ",", "colors": [ "#…", "#…" ] }` (`wp_json_encode` mit `JSON_HEX_TAG|JSON_HEX_AMP`; Locale aus `get_locale()` mit `-`; Dezimalzeichen aus `number_format_i18n(1.5,1)`; Farben aus `NAWS_Colors`/Erscheinungsbild: zwei kontrastierende Palettenfarben, nicht rot/grün). Keine module_id im JSON.
- Skript `compare-boot.js`: für jedes `figure[data-naws-cmp]` JSON lesen, Chart.js `type: 'line'` mit Datasets je Reihe (`type: chart`), `x: { type: 'time', time: { unit: auto → 'month' bei > 120 Punkten, sonst 'day'; tooltipFormat lokalisiert } }`, `y`/`y1` (y1 `position: 'right'`, Gitterlinien aus), Achsentitel = Einheit, Balkenachse `beginAtZero`, `spanGaps: false`, `pointRadius: 0`, `pointHoverRadius: 4`, Zahlen über `Intl.NumberFormat( locale, { maximumFractionDigits: decimals } )`. Wenn `window.NAWS_whenInView` existiert, erst im Blick zeichnen (wie andere Diagramme), sonst sofort. Vergrößern: `<dialog class="naws-cmp-dialog">` mit zweitem Chart derselben Konfiguration; Schließen per Knopf und Esc. Export `window.NAWS_compareInit( root )` für die Admin-Vorschau.

- [ ] **Step 1: Failing render tests**

```php
$h = NAWS_Compare::render( [ 'a' => 'indoor:humidity', 'b' => 'outdoor:humidity', 'from' => '', 'to' => '', 'months' => '', 'title' => 'Feuchte', 'height' => '360' ] );
$j = json_decode( html_entity_decode( naws_test_json( $h ) ), true );
check( 'zwei Reihen', count( $j['series'] ), 2 );
check( 'gleiche Einheit eine Achse', [ $j['series'][0]['axis'], $j['series'][1]['axis'], $j['right'] ], [ 'y', 'y', false ] );
check( 'Namen mit Modul', $j['series'][1]['name'], 'Humidity · Outdoor' );
check( 'Anzeigename schlaegt Typname', /* Basis mit custom_name 'Museum' */ $j['series'][0]['name'], 'Humidity · Museum' );
check( 'keine MAC im HTML', (bool) preg_match( '/([0-9a-f]{2}:){5}[0-9a-f]{2}/i', $h ), false );
check( 'Luecke als null', in_array( null, $j['series'][0]['values'], true ), true );
check( 'zwei Einheiten zwei Achsen', json_decode( html_entity_decode( naws_test_json( NAWS_Compare::render( atts( 'outdoor:temp', 'indoor:humidity' ) ) ) ), true )['right'], true );
check( 'Regen Balken', naws_cmp_series( atts( 'rain:rain', '' ) )[0]['chart'], 'bar' );
check( 'Fahrenheit umgerechnet', naws_cmp_series_with_settings( [ 'temperature_unit' => 'F' ], atts( 'outdoor:temp', '' ) )[0]['unit'], '°F' );
check( 'Zoll 3 Stellen', naws_cmp_series_with_settings( [ 'rain_unit' => 'in' ], atts( 'rain:rain', '' ) )[0]['decimals'], 3 );
check( 'Tippfehler: Besucher sieht andere Reihe', count( naws_cmp_series( atts( 'outdor:humidity', 'outdoor:temp' ) ) ), 1 );
check( 'Tippfehler: Redakteur sieht Angabe', str_contains( render_as_editor( atts( 'outdor:humidity', 'outdoor:temp' ) ), 'outdor:humidity' ), true );
check( 'deaktiviertes Modul: eigener Grund', str_contains( render_as_editor( atts( 'in-sleeping:temp', '' ) ), /* cmp_notice_inactive_module */ 'deactivated' ), true );
check( 'beide falsch: Besucher leer', NAWS_Compare::render( atts( 'x:y', 'z:w' ) ), '' );
check( 'zwei Vergleiche eindeutige IDs', naws_canvas_id( NAWS_Compare::render( atts() ) ) !== naws_canvas_id( NAWS_Compare::render( atts() ) ), true );
check( 'height geklemmt', str_contains( NAWS_Compare::render( atts() + [ 'height' => '5000' ] ), 'height:900px' ), true );
```
(Hilfsfunktionen `atts()`, `naws_test_json()`, `naws_cmp_series()`, `render_as_editor()`, `naws_canvas_id()` im Test definieren.) Dazu `tests/test-shortcode-atts.php` erwartet 20.
- [ ] **Step 2:** → FAIL.
- [ ] **Step 3:** WP-Schicht, Labels, Vorlage, Shortcode, CSS (Canvas-Box, Titelzeile, Vergrößern-Knopf `.naws-cmp .naws-cmp-expand:hover/:focus-visible`, Dialog), `compare-boot.js`.
- [ ] **Step 4:** Suite, `php -l`, `vendor/bin/phpcs includes/class-naws-compare.php templates/compare.php includes/class-naws-shortcodes.php includes/class-naws-records.php`, `node --check assets/js/compare-boot.js` → grün.
- [ ] **Step 5: Commit** `"Compare: [naws_compare] shortcode, template and chart"`

---

### Task 4: Baukasten im Backend

**Files:**
- Modify: `includes/class-naws-compare.php` (+ `catalogue_for_admin()`), `includes/class-naws-database.php` (+ `daily_coverage(): array`), `includes/class-naws-ajax.php` (`wp_ajax_naws_compare_preview`), `includes/class-naws-admin.php` (auf `naws-shortcodes`: `naws-chartjs`/`naws-chartjs-adapter`/`naws-compare-boot` im Admin registrieren und laden — die Frontend-Handles existieren dort nicht, daher eigene `wp_register_script`-Aufrufe mit denselben Dateien), `admin/views/shortcodes.php` (Abschnitt `[naws_compare]`), `assets/js/admin.js` (Baukasten), `assets/css/admin.css`
- Test: Create `tests/test-compare-admin.php`

**Interfaces:**
- Consumes: `series_catalogue()`, `render()`, HTML-Vertrag aus Task 3, `window.NAWS_compareInit( root )`.
- Produces:
  - `NAWS_Database::daily_coverage(): array` — `[ module_id => [ col => int /* Tage mit Wert */, '_days' => int /* Tage gesamt */ ] ]`, eine Abfrage `SELECT module_id, COUNT(*) AS _days, COUNT(col1) …, COUNT(colN) FROM daily GROUP BY module_id` (prepared/whitelist, Spalten aus dem Katalog).
  - `NAWS_Compare::catalogue_for_admin(): array` — nur aktive Module, je Eintrag `[ 'ref', 'name' /* wie series_name-Modulteil */, 'qtys' => [ [ 'qty', 'label', 'state' => 'ok'|'sparse'|'empty' ] ] ]`; `empty` = 0 Tage, `sparse` = weniger als 50 % von `_days` der jeweiligen Zeile.
  - AJAX `naws_compare_preview`: `check_ajax_referer( 'naws_admin_nonce', 'nonce' )`, `current_user_can( 'manage_options' )` sonst 403, Eingaben `a`, `b`, `from`, `to`, `months`, `title`, `height` (sanitize_text_field + wp_unslash), Antwort `wp_send_json_success( [ 'html' => NAWS_Compare::render( $atts, true ) ] )`.
- Baukasten (Spec §6): Auswahl Modul/Größe für A und B (B mit „keine zweite Reihe“), Zeitraum (ganze Aufzeichnung | ab Monat | letzte N Monate), Titel, Höhe; ausgegraute Größen (`empty`) nicht wählbar, `sparse` mit Zusatz „lückenhaft“; Shortcode-Zeile aktualisiert sich bei jeder Änderung (`a` und `b` immer, sonst nur Gesetztes); Kopieren-Knopf wie `naws-copy-btn`; Vorschau 400 ms nach letzter Änderung per AJAX, eingesetzt und mit `NAWS_compareInit()` gezeichnet. Katalog als `<script type="application/json" id="naws-cmp-catalogue">` im Abschnitt.

- [ ] **Step 1: Failing tests**

```php
check( 'nur aktive Module', array_column( NAWS_Compare::catalogue_for_admin(), 'ref' ), [ 'indoor', 'outdoor', 'rain', 'in-gast' ] ); // Fixture: in-sleeping inaktiv
check( 'Basis-Innentemp lueckenhaft', naws_state( 'indoor', 'temp' ), 'sparse' );   // Fixture 76 von 961
check( 'Basis-Innenfeuchte ok', naws_state( 'indoor', 'humidity' ), 'ok' );
check( 'nie gemessen = empty', naws_state( 'indoor', 'noise' ), 'empty' );          // Fixture 0
check( 'Vorschau ohne Recht 403', call_preview( [ 'a' => 'indoor:humidity' ], false )['status'], 403 );
check( 'Vorschau = Shortcode', call_preview( [ 'a' => 'indoor:humidity', 'b' => 'outdoor:humidity' ], true )['data']['html'], NAWS_Compare::render( atts_full( 'indoor:humidity', 'outdoor:humidity' ), true ) );
check( 'Vorschau ohne Cache', $GLOBALS['naws_last_daily_args']['no_cache'] ?? false, true );
$v = file_get_contents( dirname( __DIR__ ) . '/admin/views/shortcodes.php' );
check( 'Abschnitt vorhanden', str_contains( $v, '[naws_compare' ), true );
check( 'Katalog eingebettet', str_contains( $v, 'naws-cmp-catalogue' ), true );
$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.js' );
check( 'Vorschau ruft NAWS_compareInit', str_contains( $js, 'NAWS_compareInit' ), true );
check( 'Vorschau-Action', str_contains( $js, 'naws_compare_preview' ), true );
```
- [ ] **Step 2:** → FAIL.
- [ ] **Step 3:** umsetzen.
- [ ] **Step 4:** Suite, `php -l`, phpcs auf geänderte PHP, `node --check assets/js/admin.js` → grün.
- [ ] **Step 5: Commit** `"Compare: shortcode builder with live preview"`

---

### Task 5: Übersetzungen und Doku

**Files:**
- Modify: `.pot`, de/nb `.po`/`.mo` (Werkzeuge `docs/i18n/catalog/`, README dort), `readme.txt` (Shortcode-Liste), `CHANGELOG.md` (`[Unreleased]`, unter dem Monatsrückblick)

- [ ] **Step 1:** readme + CHANGELOG.
- [ ] **Step 2:** `makepot.php`, `merge_po.php de_DE|nb_NO` → offene Liste.
- [ ] **Step 3:** de/nb füllen (Deutsch: „Luftfeuchte“, „Temperatur“, „Tiefstwert“, „Höchstwert“, „CO₂“, „Lärm“, „Luftdruck“, „Regen“, „Wind“, „Böe“, „Außen“, „Basisstation“, „Windmesser“, „Regenmesser“, „lückenhaft“, „keine Daten“, „Vergrößern“; Norwegisch im Ton des Katalogs), `make_mo.php`; Prüfung nach Franks Regel (`cat -A`, `git diff` der .po, make_mo-Probe; Skripte mit Backslash per Write in den Scratchpad).
- [ ] **Step 4:** `tests/test-mo-files.php`, `tests/test-makepot-comments.php`, `tests/test-readme-sections.php`, Suite → grün; merge_po 0 offen.
- [ ] **Step 5: Commit** `"Compare: translations (de, nb), readme, changelog"`

---

### Task 6: Auf dev einspielen und abnehmen (Controller)

Weg wie beim Monatsrückblick (Gedächtnis `reference_live_deploy_patch.md`, ZIP-Weg; PHP nie in die Sandbox; Lint mit `/opt/alt/php85/usr/bin/php`).
- [ ] Sicherung `wp-content/naws-backup-vor-modulvergleich-<Zeit>`, Dateien einspielen, md5 + Lint, `opcache_reset()`, Startseite 200.
- [ ] Hilfsseite: `[naws_compare]` (Matthews Fall), `[naws_compare a="in-gast:temp" b="in-sleeping:temp"]` (falls aktiv), `[naws_compare a="outdoor:temp" b="rain:rain" months="12"]`, `[naws_compare a="indoor:temp" b=""]` (Lücken).
- [ ] Playwright Desktop + Handy: Diagramme sichtbar, Achsen/Einheiten, Lücken, Tooltip mit Komma, Vergrößern + Esc, Hover nicht rot, keine Konsolenfehler.
- [ ] Backend: Shortcode-Seite, Baukasten durchklicken (Modulwechsel passt Größen an, lückenhaft-Markierung, Vorschau aktualisiert, Kopieren).
- [ ] Frank zeigen. Nichts pushen/mergen ohne Ansage.
