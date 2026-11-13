# Monatsrückblick `[naws_month_review]` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Shortcode zeigt Besuchern den zuletzt abgeschlossenen Monat (Kopfsatz, fünf Kennzahl-Kacheln mit Vorjahren, bis zu drei Sätze) und blättert ohne Neuladen zu anderen Monaten.

**Architecture:** Reine Rechenklasse `NAWS_Month_Review` (Tageszeilen rein, Ergebnis-Arrays raus) nach dem Muster von `NAWS_Records`; eine PHP-Vorlage rendert den Block; der Shortcode und ein öffentlicher `admin-ajax`-Abruf benutzen **dieselbe** Renderfunktion; ein kleines Vanilla-JS tauscht den Block aus und fällt bei jedem Fehler auf den normalen Link zurück.

**Tech Stack:** PHP 8 (WordPress-Plugin, kein Composer zur Laufzeit), Vanilla JS ohne Abhängigkeiten, gettext über `naws_label()`, Tests als eigenständige PHP-Skripte (`php tests/test-*.php`, Exit-Code 1 bei Fehler).

**Spec:** `docs/superpowers/specs/2026-11-13-monatsrueckblick-design.md` (von Frank freigegeben 13.11.2026). Bei Widerspruch gilt die Spec.

## Global Constraints

- Zweig `monatsrueckblick`; nichts pushen, mergen, taggen oder schneiden ohne Franks Ansage.
- Textdomain `xtx-integration-for-netatmo`; jeder sichtbare Text als `naws_label()`-Schlüssel mit Präfix `mr_` in `includes/class-naws-labels.php`, jeder Platzhalter mit `/* translators: */`-Kommentar; keine zusammengeklebten Satzteile; Plural über `_n()`.
- Basiseinheiten in der Rechnung: °C, mm, km/h. Umrechnung nur bei der Ausgabe über `NAWS_Helpers::format_value()`/`get_unit()`, Zahlen über `number_format_i18n()`.
- Datumsausgabe immer auf Mittag verankert: `wp_date( $fmt, strtotime( $ymd . ' 12:00:00' ) )` (siehe `templates/records.php:76`).
- Schwellen: Regentag `NAWS_Records::RAIN_DAY_MM` (0,1 mm); Mindesttage `NAWS_Records::MONTH_MIN_DAYS` (20); Frost `temp_min < 0.0`, Sommertag `temp_max >= 25.0` (Werte aus `NAWS_Calc`-Katalog `frost_days`/`summer_days`, `includes/class-naws-calc.php:87-88`, dort lesen, nicht hart kodieren).
- Tendenz-Schwellen (Konstanten in `NAWS_Month_Review`): `TREND_TEMP = 0.5` (K), `TREND_RAIN_PCT = 0.10` **und** `TREND_RAIN_MM = 2.0`, `TREND_GUST = 5.0` (km/h).
- `years`: Vorgabe 5, geklemmt 0–10; `years=0` = keine Vorjahreszeilen, kein ★, kein ▲/▼, neutraler Kopfsatz, keine Vergleichsangaben in Sätzen.
- Adressparameter `naws_month`, Format `^\d{4}-(0[1-9]|1[0-2])$`.
- Besucher sehen nie Fehlertexte; Redakteure (`current_user_can('edit_posts')`, wie bei den Rekorden) sehen den Grund.
- Neue Knöpfe/Links: eigene `:hover`/`:focus-visible`-Regeln mit **zwei Klassen** im Selektor, Farben aus der Palette des Bausteins (nicht `--naws-primary`).
- Dateien mit LF-Zeilenenden; Tests, `php -l`, `vendor/bin/phpcs` grün vor jedem Commit.
- Commit-Nachrichten enden mit `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Monat mit Lücken beim Regen, aber vollständiger Temperatur** — Temperatur-Kacheln vergleichen und tragen ★/Pfeile, die Regen-Kachel zeigt den Wert mit Vermerk „unvollständig (X von Y Tagen)“, ohne ★/Pfeil/Vorjahresvergleich. → Test in Task 1 + Task 2.
2. **Seite aus dem Cache mit abgelaufener Nonce** — Klick auf ‹ führt per normalem Link zum Monat, nie zu Fehlertext. → Task 4 (Quelltextprüfung des Rückfalls) + Abnahme Task 6.
3. **Station mit Pause (Monate ohne Zeilen)** — Blättern überspringt die Lücke. → Test in Task 1 (`neighbours()`).
4. **Englischsprachige Seite / Einheiten °F, in, mph** — Kacheln in Einstellungseinheiten, Regen in Zoll mit 3 Nachkommastellen wie bei den Rekorden. → Test in Task 3.
5. **Zwei Rückblicke auf einer Seite, einer mit festem `month`** — der feste zeigt keine Leiste und reagiert nicht auf `?naws_month`, der andere schon; das Skript tauscht nur den angeklickten Block. → Test in Task 3 + Task 4.

---

### Task 1: Rechenkern — Monate und Monatszusammenfassung

**Files:**
- Create: `includes/class-naws-month-review.php`
- Modify: `xtx-integration-for-netatmo.php` (eine `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-month-review.php' );` direkt nach der Zeile für `class-naws-records.php`, Z. 54)
- Test: `tests/test-month-review.php` (Kopf/Stubs/`check()`/`close()` wie `tests/test-records.php:1-37`)

**Interfaces:**
- Produces (alle `public static`, rein, keine WordPress-Aufrufe):
  - `months( array $rows, string $today ): array` — sortierte `'JJJJ-MM'` mit ≥ 1 Zeile, ohne den Monat von `$today` (`'Y-m-d'`) und spätere.
  - `resolve_month( string $wanted, array $months ): ?string` — `$wanted`, wenn in `$months`; sonst letzter Eintrag; `null` bei leerer Liste.
  - `neighbours( string $ym, array $months ): array` — `[ 'prev' => ?string, 'next' => ?string ]`, nächster Listeneintrag (Lücken übersprungen).
  - `summary( array $rows, string $ym ): array` mit den Schlüsseln
    `ym, length, mean, max, min, rain, rain_days, wettest_day, gust, frost_days, summer_days, days, complete`.
    `max/min/wettest_day/gust` = `[ 'value' => float, 'date' => 'Y-m-d' ]` oder `null`; `mean/rain` float oder `null`; Zählungen int oder `null` (null, wenn das Feld im Monat gar keine Werte hat); `days` = `[ 'temp_avg'=>int, 'temp_max'=>int, 'temp_min'=>int, 'rain_sum'=>int, 'gust_max'=>int ]`; `complete` = `[ kpi => bool ]` für alle neun Kennzahlen.
- Vollständigkeitsregel (`complete`): `mean`←`temp_avg`, `max`←`temp_max`, `min`←`temp_min`, `gust`←`gust_max`, `wettest_day`←`rain_sum`: je ≥ `MONTH_MIN_DAYS`. `rain`, `rain_days`←`rain_sum`, `frost_days`←`temp_min`, `summer_days`←`temp_max`: je `== length`.
- Gleichstand bei Extremen: der **erste** Tag gewinnt.

- [ ] **Step 1: Failing tests schreiben** — handgebaute Zeilen (Form `[ 'day_date' => 'Y-m-d', 'temp_min'=>…, 'temp_max'=>…, 'temp_avg'=>…, 'rain_sum'=>…, 'gust_max'=>… ]`, fehlende Werte als `null`):

```php
// months(): Lücke 2025-02, laufender Monat fällt weg
check( 'months ohne laufenden Monat', NAWS_Month_Review::months( $rows_jan_mar_2025_und_nov_2026, '2026-11-13' ), [ '2025-01', '2025-03' ] );
check( 'resolve: unbekannt -> letzter', NAWS_Month_Review::resolve_month( '2030-01', [ '2025-01', '2025-03' ] ), '2025-03' );
check( 'resolve: leer -> null',          NAWS_Month_Review::resolve_month( '', [] ), null );
check( 'neighbours überspringt Lücke',   NAWS_Month_Review::neighbours( '2025-03', [ '2025-01', '2025-03' ] ), [ 'prev' => '2025-01', 'next' => null ] );
// summary(): Oktober 2026 (31 Tage voll) mit bekannten Werten
$s = NAWS_Month_Review::summary( $okt2026, '2026-10' );
check( 'length',                 $s['length'], 31 );
check( 'max mit erstem Tag',     $s['max'], [ 'value' => 24.0, 'date' => '2026-10-03' ] ); // 24.0 auch am 2026-10-20
check( 'rain_days ab 0,1',       $s['rain_days'], 16 );   // ein Tag mit genau 0.1 zählt, einer mit 0.09 nicht
check( 'frost_days < 0',         $s['frost_days'], 2 );   // ein Tag mit genau 0.0 zählt nicht
check( 'summer_days >= 25',      $s['summer_days'], 0 );  // 24.99 zählt nicht
// Vollständigkeit: 19 vs. 20 Tage, Summen brauchen alle Tage
check( '19 Tage temp_avg -> mean unvollständig', NAWS_Month_Review::summary( $mit19, '2026-10' )['complete']['mean'], false );
check( '20 Tage temp_avg -> mean vollständig',   NAWS_Month_Review::summary( $mit20, '2026-10' )['complete']['mean'], true );
check( '30/31 rain_sum -> rain unvollständig',   NAWS_Month_Review::summary( $regen30, '2026-10' )['complete']['rain'], false );
check( '30/31 rain_sum -> wettest_day vollst.',  NAWS_Month_Review::summary( $regen30, '2026-10' )['complete']['wettest_day'], true );
check( 'ohne gust_max -> gust null',             NAWS_Month_Review::summary( $ohne_wind, '2026-10' )['gust'], null );
```
Die Tests brauchen `class-naws-climate.php`, `class-naws-calc.php`, `class-naws-records.php` (wie `tests/test-records.php:20-22`).

- [ ] **Step 2:** `php tests/test-month-review.php` → FAIL (Klasse fehlt).
- [ ] **Step 3:** `final class NAWS_Month_Review` mit Konstanten (`TREND_*`, `YEARS_DEFAULT = 5`, `YEARS_MAX = 10`) und den vier Funktionen implementieren; `naws_require`-Zeile ergänzen.
- [ ] **Step 4:** `php tests/test-month-review.php` und `php tests/test-main-requires.php` → PASS.
- [ ] **Step 5: Commit** `git add includes/class-naws-month-review.php xtx-integration-for-netatmo.php tests/test-month-review.php && git commit -m "Month review: months and monthly summary"`

---

### Task 2: Rechenkern — Vergleich, Rekorde, Kopfsatz, Sätze

**Files:**
- Modify: `includes/class-naws-month-review.php`
- Test: `tests/test-month-review.php` (erweitern)

**Interfaces:**
- Consumes: `summary()`, `months()` aus Task 1.
- Produces:
  - `compare( array $rows, string $ym, int $years ): array` →
    `[ 'current' => summary, 'previous' => [ 'JJJJ' => summary, … ] /* höchstens $years, neueste zuerst, nur Jahre mit Zeilen */, 'record' => [ kpi => 'high'|'low'|null ], 'trend' => [ kpi => 'up'|'down'|null ] ]` für die Kachel-KPIs `mean, max, min, rain, gust`.
    - Nur Zeilen **≤ letzter Tag von `$ym`** fließen ein („seit Messbeginn“ = bis Monatsende).
    - ★ (`record`): Richtungen `mean` high|low, `max` high, `min` low, `rain` high|low, `gust` high. Nur wenn `current.complete[kpi]` und ≥ 1 Vorjahr desselben Monatsnamens mit `complete[kpi]` (über **alle** Vorjahre, unabhängig von `$years`), und der Wert alle vollständigen Vorjahre **echt** übertrifft. `$years === 0` → alle `null`.
    - Tendenz (`trend`): nur ohne ★, gegen das Mittel der in `previous` gezeigten **vollständigen** Vorjahre; Temperatur `|Δ| >= TREND_TEMP`, Regen `|Δ| >= max( TREND_RAIN_MM, TREND_RAIN_PCT * mittel )`, Böe `|Δ| >= TREND_GUST`; kein vollständiges Vorjahr → `null`.
  - `station_records( array $rows, string $ym ): array` → `[ 'hottest_day' => bool, 'coldest_night' => bool, 'strongest_gust' => bool, 'wettest_day' => bool ]`: Monatswert übertrifft `NAWS_Records::compute()` über die Zeilen **vor dem 1. von `$ym`** echt; keine Zeilen davor → alle `false`.
  - `headline( array $cmp ): array` → `[ 'key' => string, 'args' => array ]`, erste zutreffende Regel: `rain` ★high → `mr_head_wettest`, ★low → `mr_head_driest`; `mean` ★high → `mr_head_warmest`, ★low → `mr_head_coldest`; `max` ★ → `mr_head_hottest_day`; `min` ★ → `mr_head_coldest_night`; `gust` ★ → `mr_head_gust`; sonst `mr_head_neutral`. `args` = `[ 'month' => 'JJJJ-MM' ]` (Formatierung in der Vorlage).
  - `sentences( array $cmp, array $station ): array` → Liste von `[ 'key' => string, 'args' => array ]`, Reihenfolge, nur zutreffende, **höchstens 3**:
    1. `mr_frost` (wenn `frost_days > 0` und vollständig; `args` = `[ 'n' => int, 'prev' => ?int /* letztes vollständiges Vorjahr, null bei years=0 */, 'prev_year' => ?string ]`)
    2. `mr_summer` (dasselbe für `summer_days`)
    3. `mr_wettest_day` (wenn `wettest_day.value >= 1.0`; `args` = `[ 'date' => 'Y-m-d', 'value' => float ]`)
    4. `mr_station_record_{hottest_day|coldest_night|strongest_gust|wettest_day}` — nur der **erste** zutreffende aus `station_records()` (`args` = `[ 'date', 'value' ]`)

- [ ] **Step 1: Failing tests** — drei Oktober (2024, 2025, 2026) mit den echten Monatswerten aus Spec §1 als Gerüst, plus Sonderfälle:

```php
$c = NAWS_Month_Review::compare( $drei_oktober, '2026-10', 5 );
check( 'previous neueste zuerst',     array_keys( $c['previous'] ), [ '2025', '2024' ] );
check( 'Regen 39,6 > 37,3/29,3 -> ★', $c['record']['rain'], 'high' );
check( 'Max 24,0 -> ★',               $c['record']['max'], 'high' );
check( 'Böe 26 < Mittel 37 -> ▼',     $c['trend']['gust'], 'down' );
check( 'Mittel 12,2 vs 12,1 -> kein Pfeil', $c['trend']['mean'], null );
check( 'years=1 begrenzt previous',   array_keys( NAWS_Month_Review::compare( $drei_oktober, '2026-10', 1 )['previous'] ), [ '2025' ] );
check( 'years=0 -> kein ★',           NAWS_Month_Review::compare( $drei_oktober, '2026-10', 0 )['record']['rain'], null );
check( 'Gleichstand ist kein Rekord', NAWS_Month_Review::compare( $gleichstand, '2026-10', 5 )['record']['max'], null );
check( 'alter Monat ignoriert spätere Zeilen', NAWS_Month_Review::compare( $drei_oktober, '2025-10', 5 )['record']['rain'], 'high' ); // 37,3 > 29,3; 2026 zählt nicht
check( 'erster Oktober -> kein ★',    NAWS_Month_Review::compare( $drei_oktober, '2024-10', 5 )['record']['rain'], null );
check( 'unvollst. Regen -> kein ★, kein Pfeil', [ NAWS_Month_Review::compare( $regen_luecke, '2026-10', 5 )['record']['rain'], NAWS_Month_Review::compare( $regen_luecke, '2026-10', 5 )['trend']['rain'] ], [ null, null ] );
check( 'unvollst. Regen, Mittel vergleicht trotzdem', NAWS_Month_Review::compare( $regen_luecke, '2026-10', 5 )['record']['mean'] !== null || NAWS_Month_Review::compare( $regen_luecke, '2026-10', 5 )['trend']['mean'] !== null, true );
// $regen_paar( 11.9 ) = Oktober 2025 mit 10,0 mm, Oktober 2026 mit 11,9 mm (beide 31 Tage voll, Regen je auf einen Tag gelegt)
check( 'Regen-Schwelle: +1,9 mm bei Mittel 10 -> kein Pfeil', NAWS_Month_Review::compare( $regen_paar( 11.9 ), '2026-10', 5 )['trend']['rain'], null );
check( 'Regen-Schwelle: +2,0 mm bei Mittel 10 -> ▲',          NAWS_Month_Review::compare( $regen_paar( 12.0 ), '2026-10', 5 )['trend']['rain'], 'up' );
check( 'Kopfsatz Regen vor Mittel',   NAWS_Month_Review::headline( $c )['key'], 'mr_head_wettest' );
check( 'Kopfsatz ohne Vorjahr neutral', NAWS_Month_Review::headline( NAWS_Month_Review::compare( $drei_oktober, '2024-10', 5 ) )['key'], 'mr_head_neutral' );
check( 'Stationsrekord nur bei echtem Übertreffen', NAWS_Month_Review::station_records( $drei_oktober, '2026-10' )['hottest_day'], /* true, wenn 24,0 > alle Zeilen davor */ true );
check( 'Stationsrekord ohne Zeilen davor', NAWS_Month_Review::station_records( $drei_oktober, '2024-10' ), [ 'hottest_day' => false, 'coldest_night' => false, 'strongest_gust' => false, 'wettest_day' => false ] );
check( 'höchstens drei Sätze', count( NAWS_Month_Review::sentences( $alles_trifft_zu, [ 'hottest_day' => true, 'coldest_night' => false, 'strongest_gust' => false, 'wettest_day' => false ] ) ) <= 3, true );
```
(`$drei_oktober` enthält 2024-10 bis 2026-10 vollständig mit den Monatswerten aus Spec §1; `$gleichstand` = zwei Oktober mit gleichem `temp_max`; `$regen_luecke` = `$drei_oktober` mit einem `rain_sum = null` im Oktober 2026.)

- [ ] **Step 2:** Test laufen lassen → FAIL.
- [ ] **Step 3:** `compare()`, `station_records()`, `headline()`, `sentences()` implementieren. `station_records()` darf `NAWS_Records::compute( $rows_before, 'hottest_day' )` usw. nutzen (Rückgabe enthält `value`).
- [ ] **Step 4:** `php tests/test-month-review.php` und `php tests/test-records.php` → PASS.
- [ ] **Step 5: Commit** `"Month review: comparison, records, headline and sentences"`

---

### Task 3: Vorlage, Texte, Shortcode, CSS

**Files:**
- Create: `templates/month-review.php`
- Modify: `includes/class-naws-month-review.php` (+ `rows()`, `render()`), `includes/class-naws-labels.php` (Schlüssel `mr_*`), `includes/class-naws-shortcodes.php` (TAGS-Eintrag nach `naws_on_this_day`, `sc_month_review()`, Kommentar „eighteen“ → „nineteen“ Z. 42), `assets/css/frontend.css` (Block `.naws-mr*` am Ende), `templates/records.php` (nur falls der Wertformatierer geteilt wird, siehe Step 3)
- Test: Create `tests/test-month-review-render.php` (Stubs wie `tests/test-records-render.php:13-45`); Modify `tests/test-shortcode-atts.php:64` (18 → 19)

**Interfaces:**
- Consumes: alles aus Task 1–2.
- Produces:
  - `NAWS_Month_Review::rows( array $atts ): array` — `NAWS_Records::rows( [ 'period' => 'all' ] )` (einzige WP-Berührung der Rechnung).
  - `NAWS_Month_Review::render( array $atts, ?string $month, bool $nav ): string` — `$atts` = `[ 'years' => int, 'title' => string ]` (schon geklemmt/bereinigt); `$month` = gewünschter Monat oder `null` (→ `resolve_month`); `$nav` = Blätterleiste zeigen. Bestimmt `$today = wp_date( 'Y-m-d' )`, ruft `rows()`, `months()`, `resolve_month()`, `compare()`, `station_records()`, `headline()`, `sentences()`, `neighbours()` und bindet `templates/month-review.php` per `ob_start()` ein. Leerfall: Redakteure `NAWS_Records::notice( 'naws_month_review', $reason )`, Besucher `''`; zusätzlicher Grund `no_complete_month` (Zeilen vorhanden, aber `months()` leer) mit eigenem Label `mr_notice_no_complete_month` — dafür in `NAWS_Records::notice()` (`includes/class-naws-records.php:254`) die Schlüsselwahl um `naws_month_review` + `no_complete_month` → `mr_notice_no_complete_month` erweitern (wie dort schon `otd_notice_no_hits`).
  - Shortcode: `sc_month_review( $atts )`: `shortcode_atts( [ 'month' => '', 'years' => '5', 'title' => '' ], …, 'naws_month_review' )`; `years` int geklemmt 0–10; fester `month` (gültiges Format) → `render( …, $month, false )`; sonst `$_GET['naws_month']` (unslash, sanitize, Regex) → `render( …, $get_or_null, true )`. Lädt `naws-frontend`-Stil und registriertes Skript `naws-month-review` (Task 4; in Task 3 nur `wp_register_script` vorbereiten, falls bequem).
- HTML-Vertrag (Task 4 hängt daran):
  `<section class="naws-mr" data-naws-mr data-month="JJJJ-MM" data-years="5" data-title="…" data-ajax="{admin-ajax-URL}" data-nonce="{naws_public_nonce}">`
  darin `<nav class="naws-mr-nav">` mit `<a class="naws-mr-step naws-mr-prev" href="{add_query_arg('naws_month', prev)}" data-month="prev">` bzw. `naws-mr-next`, fehlender Pfeil als leeres `<span class="naws-mr-step naws-mr-step--none" aria-hidden="true">`; Kopfsatz `<h3 class="naws-mr-head" tabindex="-1">`; Kacheln `<div class="naws-mr-tiles">` mit je `<div class="naws-mr-tile naws-mr-tile--{kpi}">`; Sätze `<ul class="naws-mr-notes">`; austauschbarer Inhalt in `<div class="naws-mr-body" aria-live="polite">`. Ohne `$nav`: Monatsname als `<h3>` statt `<nav>`; `data-naws-mr` fehlt (Skript ignoriert den Block).
- Englische Quelltexte (Endfassung geht vor dem Schnitt an Frank; `%1$s` Monatsname aus `wp_date( 'F', … )` bzw. `'F Y'`):

| Schlüssel | Text |
|---|---|
| `mr_head_wettest` | `The wettest %1$s since records began` |
| `mr_head_driest` | `The driest %1$s since records began` |
| `mr_head_warmest` | `The warmest %1$s since records began` |
| `mr_head_coldest` | `The coldest %1$s since records began` |
| `mr_head_hottest_day` | `%1$s brought the warmest %2$s day since records began` (%1$s = Monat+Jahr, %2$s = Monatsname) |
| `mr_head_coldest_night` | `%1$s brought the coldest %2$s night since records began` |
| `mr_head_gust` | `%1$s brought the strongest %2$s gust since records began` |
| `mr_head_neutral` | `%1$s in review` |
| `mr_tile_mean` / `_max` / `_min` / `_rain` / `_gust` | `Mean` / `Highest` / `Lowest` / `Rain` / `Strongest gust` |
| `mr_on_date` | `on %s` |
| `mr_rain_days` | `_n( '%d rain day', '%d rain days' )` |
| `mr_incomplete` | `incomplete (%1$d of %2$d days)` |
| `mr_record_sr` / `mr_up_sr` / `mr_down_sr` | `Record since records began` / `More than the average of earlier years` / `Less than the average of earlier years` |
| `mr_frost` | `_n( '%d frost day', '%d frost days' )`, mit Vorjahr Zusatz `mr_prev_count` = `(%1$s: %2$d)` |
| `mr_summer` | `_n( '%d summer day', '%d summer days' )` + `mr_prev_count` |
| `mr_wettest_day` | `The most rain fell on %1$s: %2$s` |
| `mr_station_record_hottest_day` | `%1$s on %2$s was also the warmest day since records began.` |
| `mr_station_record_coldest_night` | `%1$s on %2$s was also the coldest night since records began.` |
| `mr_station_record_strongest_gust` | `%1$s on %2$s was also the strongest gust since records began.` |
| `mr_station_record_wettest_day` | `%1$s on %2$s was also the wettest day since records began.` |
| `mr_nav_prev` / `mr_nav_next` | `Previous month: %s` / `Next month: %s` (aria-label) |
| `mr_date_format` | `F j` (de: `j. F`) — `_x(..., 'date format for the month review')` |
| `mr_notice_no_complete_month` | `The month review needs at least one completed month of data.` |

- [ ] **Step 1: Failing render tests** (`tests/test-month-review-render.php`, `NAWS_Database::$daily` = drei Oktober + November 2026 bis 13.):

```php
$html = NAWS_Month_Review::render( [ 'years' => 5, 'title' => '' ], null, true );
check( 'Vorgabe = letzter abgeschlossener Monat', str_contains( $html, 'data-month="2026-10"' ), true );
check( 'fünf Kacheln',            substr_count( $html, 'class="naws-mr-tile ' ), 5 );
check( 'Kopfsatz nassester',      str_contains( $html, 'The wettest October since records began' ), true );
check( 'Vorjahreszeilen',         substr_count( $html, 'naws-mr-prev-year' ), 10 ); // 5 Kacheln × 2 Jahre
check( 'kein › hinter Oktober',   str_contains( $html, 'naws-mr-next' ), false );
check( '★ mit Textalternative',   str_contains( $html, 'Record since records began' ), true );
check( 'ohne Wind keine Böen-Kachel', substr_count( render_ohne_gust(), 'naws-mr-tile--gust' ), 0 );
check( 'fester Monat: keine Leiste, kein data-naws-mr', [ str_contains( $fest, '<nav' ), str_contains( $fest, 'data-naws-mr' ) ], [ false, false ] );
check( 'Zoll: Regen mit 3 Stellen', str_contains( render_mit_einheiten( 'in' ), '1.559' ), true ); // 39,6 mm
check( 'unvollständiger Regen: Vermerk', str_contains( render_regen_luecke(), 'incomplete (30 of 31 days)' ), true );
check( 'Besucher-Leerfall leer',  NAWS_Month_Review::render( [ 'years' => 5, 'title' => '' ], null, true ) /* bei $daily = [] */, '' );
check( 'Redakteur sieht Grund',   render_leer_als_redakteur() !== '', true );
check( 'nur laufender Monat: eigener Grund', str_contains( render_nur_november_als_redakteur(), 'needs at least one completed month' ), true );
```
plus Shortcode: `tests/test-shortcode-atts.php:64` erwartet 19.

- [ ] **Step 2:** beide Tests laufen lassen → FAIL.
- [ ] **Step 3:** Labels, `rows()`, `render()`, Vorlage, `sc_month_review()`, TAGS, CSS. Wertformatierung wie `$naws_rec_value` in `templates/records.php:60-74` (Zoll → 3 Stellen); diesen Formatierer als `NAWS_Records::display_value( string $param, float $value, int $decimals ): array{value:string,unit:string}` herausziehen und in beiden Vorlagen nutzen (DRY) — `tests/test-records-render.php` muss grün bleiben. CSS: Kacheln wie `.naws-sl-tiles`/`.naws-sl-tile` (`frontend.css:2080-2084`), 5 Spalten breit, 2 bzw. 1 auf schmal; Pfeile/★ neutral (`--naws-text-muted`); `.naws-mr .naws-mr-step:hover`, `:focus-visible` mit Paletten-Farben.
- [ ] **Step 4:** `for f in tests/test-*.php; do php "$f" >/dev/null || echo "FAIL $f"; done` → keine Ausgabe; `php -l` auf neue Dateien; `vendor/bin/phpcs includes/class-naws-month-review.php templates/month-review.php includes/class-naws-shortcodes.php` → rc 0.
- [ ] **Step 5: Commit** `"Month review: template, labels and [naws_month_review]"`

---

### Task 4: Blättern ohne Neuladen (Abruf + Skript)

**Files:**
- Modify: `includes/class-naws-ajax.php` (Hooks neben `naws_get_heatmap_data`, Z. 48-49; Methode `get_month_review()`), `includes/class-naws-shortcodes.php` (`wp_register_script( 'naws-month-review', NAWS_PLUGIN_URL . 'assets/js/month-review.js', [], NAWS_Helpers::asset_version( 'assets/js/month-review.js' ), true )` in `enqueue_frontend_assets()`, enqueue in `sc_month_review()` nur mit `$nav`)
- Create: `assets/js/month-review.js`
- Test: Create `tests/test-month-review-ajax.php` (Stubs: `check_ajax_referer`, `wp_send_json_success/error` werfen Exceptions mit Nutzlast, wie in `tests/test-ajax-input-limits.php`)

**Interfaces:**
- Consumes: `NAWS_Month_Review::render()`, `months()`, `rows()`, HTML-Vertrag aus Task 3.
- Produces: `wp_ajax_naws_get_month_review` + `wp_ajax_nopriv_naws_get_month_review`. POST `nonce`, `month`, `years`, `title`. Ablauf: `check_ajax_referer( 'naws_public_nonce', 'nonce' )`, `nocache_headers()`, `month` per Regex, nicht in `months( rows(), wp_date('Y-m-d') )` → `wp_send_json_error( [ 'message' => 'Month out of range.' ], 400 )`; `years` int geklemmt 0–10; `title` `sanitize_text_field( wp_unslash() )`. Erfolg: `wp_send_json_success( [ 'html' => render( [ 'years', 'title' ], $month, true ), 'month' => $month ] )`.
- Skript (IIFE, kein Build, ES2017): für jedes `[data-naws-mr]` ein Klick-Listener auf `.naws-mr-step[href]`; während eines Abrufs weitere Klicks ignorieren; `aria-busy="true"`; `fetch( data-ajax, { method: 'POST', body: URLSearchParams{ action:'naws_get_month_review', nonce, month, years, title }, credentials: 'same-origin' } )`; bei `ok && json.success`: `section.outerHTML = json.data.html`, neuen Block neu verdrahten, Fokus auf dessen `.naws-mr-head`, `history.replaceState( null, '', url_mit_naws_month )` (übrige Parameter erhalten); **bei jedem anderen Ausgang**: `location.href = link.href`.

- [ ] **Step 1: Failing tests**

```php
check( 'ungültiges Format -> 400',  call_month_review( [ 'month' => '2026-13' ] )['status'], 400 );
check( 'laufender Monat -> 400',     call_month_review( [ 'month' => '2026-11' ] )['status'], 400 );
check( 'gültig -> HTML wie Erstaufruf', call_month_review( [ 'month' => '2025-10', 'years' => '5' ] )['data']['html'], NAWS_Month_Review::render( [ 'years' => 5, 'title' => '' ], '2025-10', true ) );
check( 'years geklemmt',             str_contains( call_month_review( [ 'month' => '2026-10', 'years' => '99' ] )['data']['html'], 'data-years="10"' ), true );
check( 'nopriv registriert',         has_action_registered( 'wp_ajax_nopriv_naws_get_month_review' ), true );
// Quelltextprüfung des Skripts (wie tests/test-admin-js.php): Rückfall auf den Link vorhanden
$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/month-review.js' );
check( 'Rückfall location.href',     (bool) preg_match( '/location\.href\s*=\s*\w+\.href/', $js ), true );
check( 'replaceState statt pushState', [ str_contains( $js, 'replaceState' ), str_contains( $js, 'pushState' ) ], [ true, false ] );
check( 'nur Blöcke mit data-naws-mr', str_contains( $js, '[data-naws-mr]' ), true );
```
- [ ] **Step 2:** → FAIL.
- [ ] **Step 3:** Abruf, Skriptregistrierung, `month-review.js` umsetzen.
- [ ] **Step 4:** ganze Suite + `php -l` + phpcs → grün.
- [ ] **Step 5: Commit** `"Month review: browse months without reloading"`

---

### Task 5: Übersetzungen und Doku

**Files:**
- Modify: `languages/xtx-integration-for-netatmo.pot`, `docs/i18n/catalog/xtx-integration-for-netatmo-{de_DE,nb_NO}.po`, `languages/xtx-integration-for-netatmo-{de_DE,nb_NO}.mo`, `admin/views/shortcodes.php` (Abschnitt `[naws_month_review]` nach `[naws_on_this_day]`, Z. ~267, im selben Aufbau: Überschrift, Kopierfeld, Angaben `month`/`years`/`title`, zwei Beispiele), `readme.txt` (Shortcode-Liste), `CHANGELOG.md` (`[Unreleased]`: „Added: `[naws_month_review]` …“)

- [ ] **Step 1:** Shortcode-Hilfe, readme, CHANGELOG schreiben (Hilfetexte ebenfalls über gettext).
- [ ] **Step 2:** Kataloge nach `docs/i18n/README.md`: `php docs/i18n/catalog/makepot.php`, `merge_po.php de_DE`, `merge_po.php nb_NO` → Liste der offenen Texte.
- [ ] **Step 3:** de/nb für alle offenen Texte per `fill_po.php` aus einer Liste im Scratchpad füllen (Deutsch nach Spec §1 und den Beispielen dort: „Der nasseste %1$s seit Messbeginn“, „%1$s im Rückblick“, „Mittel“, „Höchstwert“, „Tiefstwert“, „Regen“, „Stärkste Böe“, „am %s“, „unvollständig (%1$d von %2$d Tagen)“, `mr_date_format` = `j. F`), dann `make_mo.php` für beide. Vor Übergabe gemäß Franks Regel prüfen: `cat -A` auf die geänderten Abschnitte, `git diff` der `.po`, `make_mo`-Probe.
- [ ] **Step 4:** `php tests/test-mo-files.php`, `php tests/test-makepot-comments.php`, `php tests/test-readme-sections.php`, ganze Suite → grün; `merge_po.php` meldet de/nb 0 offen.
- [ ] **Step 5: Commit** `"Month review: translations (de, nb), shortcode help, readme, changelog"`

---

### Task 6: Auf dev einspielen und abnehmen

Kein Code, nur Verteilung und Prüfung (Weg und Fallen: Gedächtnis `reference_live_deploy_patch.md`; PHP **nie** nach `novamira-sandbox/`; Lint auf dem Server mit `/opt/alt/php85/usr/bin/php`).

- [ ] **Step 1:** Sicherung `wp-content/naws-backup-vor-monatsrueckblick-<Zeitstempel>` auf dev, dann geänderte Dateien per Patch/Upload einspielen, `php -l` auf dem Server, `opcache_reset()`.
- [ ] **Step 2:** Hilfsseite auf dev (Entwurf/privat) mit `[naws_month_review]` und `[naws_month_review month="2024-03"]`; per `do_shortcode` prüfen: Oktober 2026 → Kopfsatz „nasseste Oktober seit Messbeginn“, ★ bei Regen und Höchstwert; März 2024 → „unvollständig“, ohne ★.
- [ ] **Step 3:** Playwright: Desktop + Handy-Viewport (Gedächtnis `reference_playwright_mobile.md`): Blättern ‹ › ohne Neuladen, Adresszeile `?naws_month=`, Fokus auf Kopfsatz, keine Konsolenfehler, Hover/Fokus der Pfeile nicht rot (Hello Elementor), Rückfall bei manipulierter Nonce führt zum Link.
- [ ] **Step 4:** Frank die Hilfsseite zeigen; Endfassung der englischen und deutschen Sätze vorlegen. Nichts pushen/mergen bis zu seiner Ansage.
