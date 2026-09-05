# Rekorde, „An diesem Tag" und die Sonnenbahn

**Datum:** 2026-09-05
**Betrifft:** `includes/class-naws-records.php` (neu), `includes/class-naws-climate.php`, `includes/class-naws-calc.php`, `includes/class-naws-database.php`, `includes/class-naws-astro.php`, `includes/class-naws-shortcodes.php`, `includes/class-naws-labels.php`, `templates/records.php` (neu), `templates/on-this-day.php` (neu), `templates/sunpath.php` (neu), `assets/css/frontend.css`, `admin/views/shortcodes.php`, `readme.txt`, `README.md`, `CHANGELOG.md`, `docs/site/website.{de,en}.json`, `docs/i18n/catalog/*`, `languages/*`
**Auslöser:** Zweieinhalb Jahre Tagesübersicht liegen in der Datenbank, und kein Baustein erzählt, was darin steckt: der heißeste Tag, die längste Trockenperiode, was am selben Datum vor einem Jahr war. Und die Sonnenzeiten stehen als zwei Uhrzeiten in der Infobar, wo ein Bogen zeigen könnte, wo die Sonne gerade steht.

---

## 1. Ausgangslage

### 1.1 Die Tagesübersicht

`wp_naws_daily_summary` führt je Tag und Modul eine Zeile. Die Außenwerte stehen auf der
Zeile der Basisstation (`module_id = station_id`), siehe die Heatmap-Spec vom 02.09. Spalten,
die diese Arbeit braucht: `day_date`, `temp_min`, `temp_max`, `temp_avg`, `rain_sum`,
`gust_max`. Stand auf der Produktseite am 05.09.2026: 2024-03-28 bis 2026-09-05, 892 Tage mit
`gust_max`, Extremwerte 39,1 °C / −8,5 °C / 26,4 mm / 46 km/h.

`NAWS_Database::get_daily_summaries()` liefert diese Zeilen sortiert, nach Modul und Datum
gefiltert und mit Transient-Cache — aber nur die Spalten `temp_min`, `temp_max`, `temp_avg`,
`pressure_avg`, `rain_sum` (`$allowed_fields`). **`gust_max` fehlt dort** und wird ergänzt;
nichts Bestehendes ändert sich dadurch, weil jeder Aufrufer seine Felder nennt.

### 1.2 Was der Kalkulator schon kann

`NAWS_Calc` hat die Zeitraum- und Stationslogik, die auch die Rekorde brauchen:
`station_row_id( $atts )` (die erste `NAMain`, oder die per `station=` genannte) und
`period_range( $atts )` (`year="2025"` → das Jahr; `period="all"` → seit 1900; sonst das
laufende Jahr). Beide sind `private`. Sie werden **`public static`**, sonst müsste die
Rekordklasse sie abschreiben, und zwei Kopien laufen auseinander.

`NAWS_Climate::max_streak( $rows, $matches )` liefert die längste Serie aufeinanderfolgender
Treffer und bricht an Datenlücken ab (ein fehlender Kalendertag beendet die Serie — die
vorsichtige Lesart). Sie liefert nur die **Länge**, nicht das Datum. Die Rekorde brauchen
beides.

### 1.3 Die Astronomie

`NAWS_Astro::get_coords()` liest Breite und Länge der Basisstation aus der Modultabelle
(Netatmo liefert die Position mit; `null`, solange kein Abgleich lief). `sun_times()` gibt
Aufgang und Untergang als fertig formatierte Uhrzeiten. Für einen Bogen braucht es
Zeitstempel, die Kulmination und die Position der Sonne — das ist eine neue Funktion.

---

## 2. Die Rekorde: `NAWS_Records`

Neue Klasse `includes/class-naws-records.php`, **ohne WordPress-Aufrufe** in der Rechnung:
`catalogue()` und `compute()` nehmen Tageszeilen und geben Zahlen zurück. So ist sie mit
Kunstdaten testbar, wie `NAWS_Climate`.

### 2.1 Der Katalog

| Schlüssel | Art | Grundlage | Richtung | Einheit | Beschriftung (EN) |
| --- | --- | --- | --- | --- | --- |
| `hottest_day` | extreme | `temp_max` | max | Temperature | Hottest day |
| `coldest_night` | extreme | `temp_min` | min | Temperature | Coldest night |
| `warmest_night` | extreme | `temp_min` | max | Temperature | Warmest night |
| `coldest_day` | extreme | `temp_max` | min | Temperature | Coldest day |
| `widest_range` | extreme | `temp_max − temp_min` | max | Temperaturdifferenz | Largest daily range |
| `warmest_month` | month | Mittel von `temp_avg` | max | Temperature | Warmest month |
| `coldest_month` | month | Mittel von `temp_avg` | min | Temperature | Coldest month |
| `wettest_day` | extreme | `rain_sum` | max | Rain | Wettest day |
| `wettest_month` | month | Summe von `rain_sum` | max | Rain | Wettest month |
| `longest_dry_spell` | streak | `rain_sum < 0.1` | — | Tage | Longest dry spell |
| `longest_wet_spell` | streak | `rain_sum ≥ 0.1` | — | Tage | Longest wet spell |
| `strongest_gust` | extreme | `gust_max` | max | GustStrength | Strongest gust |
| `longest_frost` | streak | `temp_min < 0` | — | Tage | Longest frost period |
| `longest_heat_wave` | streak | `temp_max ≥ 30` | — | Tage | Longest heat wave |
| `longest_summer_run` | streak | `temp_max ≥ 25` | — | Tage | Longest run of summer days |

Die Schwellen der drei Temperaturserien sind die der Tagesklassen `frost_days`, `hot_days`,
`summer_days` aus `NAWS_Calc::catalogue()`; die Rekordeinträge verweisen auf diese Schlüssel
und lesen Feld, Operator und Schwelle von dort, statt sie zu wiederholen. 0,1 mm ist die
WMO-Grenze für einen Niederschlagstag.

Jeder Katalogeintrag trägt: `kind` (`extreme`|`month`|`streak`), `label` (Schlüssel für
`naws_label()`, Muster `rec_<key>`), `param` (für `NAWS_Helpers::get_unit()` /
`format_value()`: `Temperature`, `Rain`, `GustStrength`; bei Serien `null`), `decimals`,
und je nach Art `field` + `dir`, oder `agg` (`avg`|`sum`) + `field` + `dir`, oder
`dayclass` bzw. `field` + `op` + `threshold`.

### 2.2 Die Rechnung

`NAWS_Records::compute( array $rows, string $key ): ?array` — `$rows` sind Tageszeilen wie
von `get_daily_summaries()`, aufsteigend nach `day_date`. Rückgabe:

```php
[ 'value' => float, 'date' => 'Y-m-d' ]                     // extreme
[ 'value' => float, 'month' => 'Y-m' ]                      // month
[ 'value' => int,   'from' => 'Y-m-d', 'to' => 'Y-m-d' ]    // streak, value = Tage
```

oder `null`, wenn keine Zeile die nötige Spalte trägt (bei Serien: wenn kein Tag trifft).
Ein Rekord, der `null` ist, wird nicht angezeigt — nicht als 0, nicht als „–".

Regeln:

- **Zeilen ohne die Spalte** zählen nicht (wie `NAWS_Calc::rows_with()`): ein Tag, der nur
  Druck kennt, ist kein Kandidat für den kältesten Tag.
- **Gleichstand:** das frühere Datum gewinnt. Bei Monaten der frühere Monat, bei Serien die
  frühere Serie. Damit ist die Rechnung deterministisch und ein Test kann es prüfen.
- **Monate** zählen nur mit **mindestens 20 Tagen**, die die Spalte tragen
  (`NAWS_Records::MONTH_MIN_DAYS = 20`). Sonst gewinnt der angebrochene Monat, in dem die
  Aufzeichnung begann, den „kältesten Monat" mit drei Märztagen. Das Mittel ist das
  arithmetische Mittel der vorhandenen Tage, die Summe die Summe der vorhandenen Tage.
- **Serien** brechen an einem fehlenden Kalendertag ab, genau wie `max_streak()`. Dafür
  bekommt `NAWS_Climate` eine neue Funktion `longest_run( $rows, $matches ): ?array` mit
  `[ 'length' => int, 'from' => 'Y-m-d', 'to' => 'Y-m-d' ]` oder `null`; `max_streak()`
  ruft sie auf und gibt `length` zurück — eine Rechnung, zwei Antworten.
- **`widest_range`** ist eine Differenz. Sie wird in Fahrenheit mit dem Faktor 1,8 und
  **ohne** den Versatz 32 umgerechnet; `format_value( 'Temperature', … )` täte das falsch.
  Die Klasse liefert die Differenz in Kelvin; das Template rechnet mit
  `NAWS_Records::format_delta( $kelvin )`, das die Temperatureinheit aus den Einstellungen
  liest und `°C` bzw. `°F` anhängt.

`NAWS_Records::all( array $rows, array $keys = [] ): array` rechnet die gewünschten
Schlüssel (leer = alle, Reihenfolge des Katalogs) und lässt `null`-Ergebnisse weg.
Unbekannte Schlüssel in `$keys` werden übergangen, nicht gemeldet — ein Tippfehler im
Shortcode kostet eine Kachel, nicht die Seite.

### 2.3 Die Zeilen

`NAWS_Records::rows( array $atts ): array` ist die einzige Stelle mit WordPress: sie ruft
`NAWS_Calc::station_row_id()`, `NAWS_Calc::period_range()` und
`NAWS_Database::get_daily_summaries()` mit den Feldern `temp_min, temp_max, temp_avg,
rain_sum, gust_max`. **Voreinstellung ist `period = all`** — ein Rekord ist einer seit
Aufzeichnungsbeginn, nicht seit Januar. `year="2025"` grenzt auf ein Jahr ein. Die
Voreinstellung wird gesetzt, bevor `period_range()` sie sieht, damit dort nichts umgebaut
werden muss.

---

## 3. `[naws_records year="" records="" layout="cards" title=""]`

| Attribut | Werte | Voreinstellung |
| --- | --- | --- |
| `year` | vierstellige Jahreszahl | leer = seit Aufzeichnungsbeginn |
| `records` | Schlüssel aus 2.1, kommagetrennt, Reihenfolge = Anzeigereihenfolge | leer = alle 15 in Katalogreihenfolge |
| `layout` | `cards` \| `table` | `cards` |
| `title` | Überschrift | „Records" bzw. „Records %d" mit Jahr; leer lässt die Überschrift weg |

Template `templates/records.php`:

```html
<section class="naws-rec">
  <h3 class="naws-rec-title">Rekorde seit Aufzeichnungsbeginn</h3>
  <div class="naws-rec-grid">
    <div class="naws-rec-tile naws-rec-hottest_day">
      <span class="naws-rec-label">Heißester Tag</span>
      <span class="naws-rec-value">39,1 <span class="naws-rec-unit">°C</span></span>
      <span class="naws-rec-when">1. Juli 2025</span>
    </div>
    …
  </div>
  <p class="naws-rec-foot">Seit 28. März 2024 · 861 Messtage</p>
</section>
```

- Werte über `NAWS_Helpers::format_value( $param, $value )` und `get_unit( $param )`, also
  in der eingestellten Temperatur-, Regen- und Windeinheit; Serien als ganze Zahl mit
  „Tage" (Einzahl/Mehrzahl über `_n()`).
- Datum über `wp_date( get_option( 'date_format' ) )`; Serien als „3. Februar 2025 –
  14. Februar 2025"; Monate über `wp_date( 'F Y' )` — alles in der Sprache der Seite.
- `layout="table"`: dieselben Daten als `<table class="naws-rec-table">` mit den Spalten
  Rekord | Wert | Wann, Kopfzeile mit `<th>`.
- Die Fußzeile nennt den ersten Tag mit Daten und die Zahl der Tage, die mindestens eine
  der fünf Spalten tragen.
- **Ohne Zeilen** (keine Station, keine Daten) gibt der Shortcode `''` zurück — kein leeres
  Gerüst, wie beim Widget. Ohne einen einzigen berechenbaren Rekord ebenfalls `''`.
- Kein JavaScript. Farben und Radien aus den Theme-Variablen (`--naws-surface`,
  `--naws-border`, `--naws-text`, `--naws-text-muted`, `--naws-radius`), damit die
  Erscheinungsbild-Seite greift. Neue Regeln unter `.naws-rec` in `frontend.css`; das
  Raster ist `grid` mit `repeat(auto-fill, minmax(180px, 1fr))`, auf dem Handy also eine
  bis zwei Spalten ohne eigene Medienabfrage.

---

## 4. `[naws_on_this_day date="" title=""]`

Für ein Kalenderdatum die Werte aller **früheren** Jahre. Das laufende Jahr bleibt draußen:
seine Zeile für heute entsteht erst am Tagesende, und „dieser Tag in früheren Jahren" ist
dann auch als Überschrift wahr.

| Attribut | Werte | Voreinstellung |
| --- | --- | --- |
| `date` | `MM-DD` oder `YYYY-MM-DD` (das Jahr wird ignoriert) | heute in der Zeitzone der Site |
| `title` | Überschrift | „This day in earlier years"; leer lässt sie weg |

`NAWS_Records::on_this_day( array $rows, string $month_day, int $before_year ): array`
filtert die Zeilen auf `substr( day_date, 5 ) === $month_day` und Jahr `< $before_year`,
neueste zuerst, und markiert je Spalte das Extrem (wärmster Max-Wert, kältester Min-Wert,
nassester Tag) — rein, testbar. Der 29. Februar liefert nur Schaltjahre; ein unbrauchbares
`date` fällt auf heute zurück.

Template `templates/on-this-day.php`: `<section class="naws-otd">` mit `<h3>`, dann
`<table class="naws-otd-table">` mit Jahr | Tiefst | Höchst | Mittel | Regen; Rekordzellen
tragen `class="naws-otd-record"` und werden per CSS hervorgehoben. Ohne frühere Jahre
gibt der Shortcode `''` zurück. Die Zeilen kommen aus `NAWS_Records::rows()` mit
`period = all`.

---

## 5. `[naws_sunpath title=""]`

### 5.1 Die Rechnung: `NAWS_Astro::sun_path()`

```php
public static function sun_path( float $lat, float $lng, ?int $ts = null ): ?array
```

Rein (nur `date_sun_info()`, PHP-Kern), gibt Zeitstempel und Zahlen zurück, keine Uhrzeiten:

| Schlüssel | Bedeutung |
| --- | --- |
| `dawn`, `sunrise`, `transit`, `sunset`, `dusk` | Unix-Zeitstempel (bürgerliche Dämmerung, Aufgang, Kulmination, Untergang, Dämmerungsende) |
| `day_length` | Sekunden zwischen Aufgang und Untergang |
| `progress` | Anteil des vergangenen Tageslichts, 0…1; `null`, wenn die Sonne unter dem Horizont steht |
| `night_progress` | Anteil der vergangenen Nacht (Untergang bis nächster Aufgang), 0…1; `null` am Tag |
| `delta_day` | `day_length` heute minus gestern, Sekunden, mit Vorzeichen |
| `longest`, `shortest` | Tageslänge am 21. Juni und 21. Dezember des laufenden Jahres, davon Maximum und Minimum — stimmt auf beiden Halbkugeln |

Gibt `date_sun_info()` für Aufgang oder Untergang `true`/`false` zurück (Polartag,
Polarnacht), gibt `sun_path()` **`null`** zurück, und der Shortcode rendert nichts. Das ist
der einfachste ehrliche Umgang mit einem Fall, den keine Station dieses Plugins bisher hat.

### 5.2 Das Bild

Template `templates/sunpath.php`, Inline-SVG, `viewBox="0 0 400 220"`:

- Horizont: Linie von (30,170) nach (370,170), `stroke: var(--naws-border)`.
- Bogen: Halbkreis um (200,170) mit Radius 140, gestrichelt, `var(--naws-border)`.
- Vergangener Teil des Bogens: zweiter Pfad vom Aufgangspunkt bis zur Sonne, durchgezogen,
  `var(--naws-warning)`.
- Sonne: Kreis mit Radius 10 in `var(--naws-warning)` bei Winkel θ = π·(1 − `progress`):
  x = 200 + 140·cos θ, y = 170 − 140·sin θ.
- Nachts: die Sonne sitzt unter der Linie auf einem kleineren Bogen (Radius 60) bei
  θ = π·`night_progress`, halb transparent; der große Bogen bleibt gestrichelt, ohne
  durchgezogenen Teil.
- Beschriftungen: Aufgangszeit links unter der Linie, Untergangszeit rechts, Kulmination
  über dem Scheitel (`y = 18`), alle in `var(--naws-text-muted)`, Schrift erbt.
- `<svg role="img" aria-label="…">` mit einem Satz, der Aufgang, Untergang und Tageslänge
  nennt; die sichtbare Textzeile darunter wiederholt ihn nicht wörtlich, sondern ergänzt.

Darunter `<p class="naws-sun-text">`: „Day length 13:18 · 3 min shorter than yesterday ·
longest day 16:41, shortest 7:56". Bei `delta_day` zwischen −30 und +30 Sekunden steht
„as long as yesterday". Zeiten über `wp_date( get_option( 'time_format' ) )`, Dauern als
`H:MM` — in der Zeitzone der Site.

Kein JavaScript; die Sonne steht, wo sie beim Seitenaufbau stand. Bei einem Seiten-Cache
kann das Bild deshalb bis zur nächsten Erzeugung alt sein — steht so in der Doku des
Shortcodes. `NAWS_Astro::get_coords()` ohne Ergebnis → `''`.

CSS unter `.naws-sun`: `max-width: 480px`, `svg { width: 100%; height: auto }`, Karte mit
`--naws-surface`/`--naws-border`/`--naws-radius` wie `.naws-hm`.

---

## 6. Änderungen an bestehendem Code

| Stelle | Änderung | Warum |
| --- | --- | --- |
| `NAWS_Database::get_daily_summaries()` | `gust_max` in `$allowed_fields` | Böenrekord |
| `NAWS_Calc::station_row_id()`, `period_range()` | `private` → `public` | Rekorde nutzen dieselbe Stations- und Zeitraumlogik |
| `NAWS_Climate::longest_run()` neu, `max_streak()` ruft sie | Serien mit Datum | Rekorde brauchen von–bis |
| `NAWS_Astro::sun_path()` neu | Zeitstempel statt Uhrzeiten | Bogen |
| `NAWS_Shortcodes` | drei Handler `sc_records`, `sc_on_this_day`, `sc_sunpath`; Registrierung | — |
| `NAWS_Labels` | Schlüssel `rec_*`, `otd_*`, `sun_*` | Übersetzung |
| `frontend.css` | Blöcke `.naws-rec`, `.naws-otd`, `.naws-sun` | — |

Nichts davon ändert eine bestehende Ausgabe: `max_streak()` liefert dieselbe Zahl, die
Datenbankfunktion dieselben Zeilen für alle bisherigen Aufrufer.

---

## 7. Sprache und Dokumentation

- Alle Beschriftungen über `naws_label()` und damit über `.pot` → `.po` → `.mo`
  (`makepot.php`, `merge_po.php de_DE|nb_NO`, `make_mo.php`), Deutsch und Norwegisch werden
  mitgeliefert. Rund 30 neue Sätze.
- `admin/views/shortcodes.php`: drei neue Karten mit Attributtabelle und Beispielen, im
  Muster der Heatmap-Karte.
- `readme.txt`: die drei Shortcodes in der Liste; die Zeile „**10 Shortcodes**" ist schon
  seit der Heatmap falsch (elf) und wird auf **14** gesetzt. `README.md`: drei Tabellenzeilen.
- `CHANGELOG.md`: `[Unreleased]` → `### Added`, drei Absätze.
- `docs/site/website.{de,en}.json`: zwei Vorhaben (`rekorde`, `sonnenbahn`) mit `ab: null`,
  `aktualisiert` auf das Datum des Commits.
- **Nachtrag im Site-Repo** (nicht Teil dieser Arbeit, aber notiert): `class-xns-schema.php`
  sagt „Zehn Shortcodes", `site/landing.de.html` „Alle 10 Shortcodes" — beides nach dem
  Release auf 14 ziehen.

---

## 8. Tests

Drei neue Dateien, frameworklos wie die übrigen 30:

**`tests/test-records.php`** — Rechnung auf Kunstdaten:
- jeder der 15 Schlüssel auf einem handgebauten Jahr liefert den erwarteten Wert und das
  erwartete Datum;
- Gleichstand → früheres Datum; Gleichstand bei Serien → frühere Serie;
- ein Monat mit 19 Tagen zählt nicht, mit 20 zählt er;
- eine Datenlücke bricht eine Serie;
- Zeilen ohne die Spalte werden übersprungen; ohne brauchbare Zeile → `null`;
- `all()` lässt `null` weg, hält die Katalogreihenfolge, übergeht unbekannte Schlüssel;
- `longest_run()` und `max_streak()` stimmen überein;
- `on_this_day()`: nur frühere Jahre, neueste zuerst, 29. Februar nur aus Schaltjahren,
  Extremmarkierung je Spalte;
- `format_delta()`: 10 K → „10 °C" bzw. „18 °F".

**`tests/test-records-render.php`** — Templates mit Stubs (`esc_*`, `wp_date`,
`get_option`, `naws_label`):
- Kachellayout mit 15 Kacheln, Tabellenlayout mit 15 Zeilen, `records="wettest_day"` → eine;
- Fußzeile nennt erstes Datum und Tagesanzahl; ohne Zeilen `''`; ohne Rekord `''`;
- „An diesem Tag" rendert eine Tabelle mit einer Zeile je Jahr und den Rekordklassen, ohne
  frühere Jahre `''`.

**`tests/test-sunpath.php`**:
- `sun_path()` für 51,34 N / 12,37 O: `progress` ist 0 bei Aufgang, ≈ 0,5 bei Kulmination,
  1 bei Untergang; nachts `null` und `night_progress` gesetzt; `delta_day` am 5. September
  negativ, um den 21. Juni nahe 0; `longest > shortest`;
- Südhalbkugel (−33,9 / 151,2): `longest` gehört zum Dezember;
- Template: `<svg role="img"`, `aria-label`, ein `<circle>` am Tag, keiner am Tag ohne
  Koordinaten (Ausgabe `''`).

Dazu läuft die ganze Suite; `test-calc-*` und `test-climate-indices` müssen unverändert
grün bleiben, weil `max_streak()` und `get_daily_summaries()` angefasst werden.

---

## 9. Nicht Teil dieser Arbeit

- Windrose, Heatmap für weitere Größen, Sparkline — vorgemerkt, siehe Notizen.
- Ein `station=`-Attribut für die Sonnenbahn; die Rekorde erben es von `station_row_id()`
  ohne eigene Arbeit.
- Eine wandernde Sonne per JavaScript.
- Rekorde aus den Rohmesswerten (Zehn-Minuten-Werte); die reichen nur wenige Monate zurück.
- Ein Versionsbump: alles landet unter `[Unreleased]`, der Schnitt von 1.9.11 ist ein
  eigener Schritt.

---

## 10. Offene Punkte

Keine. Die Rekordliste, die Schwellen, die Monatsschwelle von 20 Tagen und der Ausschluss
des laufenden Jahres bei „An diesem Tag" sind Entscheidungen dieser Spec und oben begründet.
