# Dynamisches Wetter-Icon — Design

**Datum:** 2026-08-07, überarbeitet 2026-08-08
**Plugin:** XTX Integration for Netatmo (`xtx-integration-for-netatmo`), Stand v1.6.5
**Status:** Design abgestimmt, Implementierungsplan steht aus

**Überarbeitung 2026-08-08 — zwei Fehler der Erstfassung behoben:**

1. **Die Rangfolge ließ die WMO-Codes 45–86 fallen.** §4 kannte für die API nur 95/96/99 und 0–3; Nebel, Niesel, Regen, Schnee und Schauer landeten sämtlich bei „kein Icon". Das widersprach §7, wo für den fehlenden Regenmesser steht „API übernimmt" — es gab keine Regel, mit der sie das getan hätte. Behoben durch die neuen Ränge 8 und 9.
2. **`NAWS_Forecast` liefert keinen aktuellen WMO-Code, nur einen Tageswert.** Die Erstfassung setzte einen aktuellen Code voraus, den es im Plugin nicht gab. Behoben durch `get_current_conditions()`, beschrieben in §5.

3. **Die Phasenbestimmung hing an der Lufttemperatur.** Schnee erreicht den Boden je nach Luftfeuchte zwischen etwa 1 °C und 4 °C Lufttemperatur — eine feste Schwelle darauf liegt im Grenzbereich regelmäßig daneben. Behoben durch `NAWS_Astro::wet_bulb()`; die Schwelle misst jetzt Feuchtkugeltemperatur. Damit bleibt die Schnee-Regen-Entscheidung vollständig bei der Station.

Mitgezogen: Schnee als Zwei-Quellen-Fall in §3 (Phase Station, Vorkommen API) statt als Ausnahme vom Grundsatz; der Schmelzwasser-Filter Rang 5a; `snowfall` im `current`-Block; WMO 68/69 in Rang 8 und der `?? 3`-Rückfall in `yr_symbol_to_wmo()`; `date_sun_info()` statt `sun_times()` in §5; die neue Option `naws_weather_last_wmo`; §7, §10 und §11.

**Zielversion:** 1.7.0.

---

## 1 · Ziel

Ein Icon, das den aktuellen Wetterzustand wiedergibt — Sonne, bewölkt, neblig, Regen, Sturm, Schnee, Graupel, Gewitter. Animiert, in Apples Formensprache, selbst gezeichnet.

Die Wetterstation misst nur einen Teil davon. Das Design löst das über eine Hybrid-Quelle, bei der die eigene Messung Vorrang vor der Vorhersage hat.

Visueller Entwurf mit allen zwölf Zuständen: https://claude.ai/code/artifact/c6a203fd-a4c2-4955-b2be-a841c584192f

---

## 2 · Getroffene Entscheidungen

| Frage | Entscheidung |
|---|---|
| Datenquelle | Hybrid — Station schlägt API, API füllt die Lücken |
| Platzierung | Shortcode `[naws_weather_icon]` **und** fest über dem Live-Dashboard |
| Dashboard-Platzierung | Im Backend ein- und ausschaltbar |
| Icon-Stil | Eigenes Set, mehrfarbig, animiert, an Apple orientiert |
| Kleinste Größe | 64 px — keine reduzierte Variante nötig |
| Beschriftung | Kein sichtbarer Text. Nur `aria-label` im SVG |

### Lizenz

Apples SF-Symbols- und Weather-Grafiken sind lizenziert und dürfen nicht in ein GPL-Plugin. Die Icons sind **selbst gezeichnet** in Apples Formensprache — weiche Rundungen, mehrfarbige Verläufe, keine harten Konturen. Es werden keine Apple-Assets ausgeliefert.

---

## 3 · Die zwölf Zustände

| Zustand | Interner Name | WMO | Bevorzugte Quelle | Ersatzquelle |
|---|---|---|---|---|
| Klar (Tag) | `clear_day` | 0 | API | — |
| Klar (Nacht) | `clear_night` | 0 | API | — |
| Heiter | `fair` | 1 | API | — |
| Teilweise bewölkt | `partly` | 2 | API | — |
| Bedeckt | `overcast` | 3 | API | — |
| Nebel | `fog` | 45 / 48 | Station | API |
| Regen | `rain` | 51–57 / 61 / 63 / 80 / 81 | Station | API |
| Starkregen | `rain_heavy` | 65 / 82 | Station | API |
| Schnee | `snow` | 71 / 73 / 75 / 77 / 85 / 86 | Station (Phase) | API (Vorkommen) |
| Graupel / Hagel | `sleet` | 66 / 67 / 68 / 69 / 96 / 99 | API | — |
| Gewitter | `thunder` | 95 | API | — |
| Sturm | `storm` | — | Station | — |

Sturm hat bewusst keinen WMO-Code: er kommt allein aus dem Windmesser.

### Schnee: zwei Fragen, zwei Quellen

Schnee ist der einzige Zustand, der auf zwei Quellen aufgeteilt ist, und das ist keine Ausnahme vom Grundsatz „Station zuerst", sondern seine genaue Anwendung.

**„Ist es Schnee oder Regen?" — Station.** Entscheidend ist nicht die Lufttemperatur, sondern die **Feuchtkugeltemperatur**. Eine fallende Flocke kühlt sich durch Verdunstung selbst; in trockener Luft kommt Schnee noch bei 3–4 °C Lufttemperatur unten an, bei 1 °C und gesättigter Luft regnet es längst. Der Übergang liegt stabil um etwa 1 °C Feuchtkugel. Berechnet wird sie aus Außentemperatur und Außenfeuchte — beides Werte des eigenen Moduls. Diese Frage beantwortet die Station also exakt am Standort und in der richtigen Höhenlage, besser als es jede externe Quelle könnte.

**„Fällt überhaupt etwas?" — hier ist die Station blind.** Der Netatmo-Regenmesser ist eine unbeheizte Kippwaage. Schnee kippt sie nicht, er bleibt im Trichter liegen und wird erst beim Auftauen — womöglich Tage später und bei Plusgraden — als Regen gezählt.

Daraus folgt die entscheidende Auslegung: **Meldet die Kippwaage während Schneefall 0,0 mm, so misst sie nicht „kein Niederschlag", sondern gar nichts.** Der Wert ist keine Aussage, sondern deren Abwesenheit — genau wie bei einem Nutzer ohne Regenmesser. Die API springt hier also nicht über eine Stationsmessung hinweg, sondern füllt eine Lücke, in der die Station strukturell nichts zu sagen hat.

Umgekehrt gilt dasselbe: Meldet die Kippwaage Regen, während die Feuchtkugel klar unter dem Gefrierpunkt liegt, ist das Schmelzwasser oder ein auftauender Trichter — kein aktueller Regen. Auch das ist keine Messung, sondern ein Artefakt, und wird verworfen (Rang 5a).

---

## 4 · Rangfolge

Die erste zutreffende Regel bestimmt das Icon. Alle weiteren werden nicht mehr geprüft.

Maß für die Phase ist durchgehend die **Feuchtkugeltemperatur** `tw` (§3), nicht die Lufttemperatur.

**Vorfilter F (Schmelzwasser), läuft vor allen Rängen:** Regen > 0 **und** `tw` < Schneeschwelle − 1 K → Regenwert auf „nicht vorhanden" setzen.

Bei dieser Kälte kann keine Flüssigkeit fallen; die Kippe stammt aus auftauendem Altschnee oder einem zugefrorenen Trichter. Danach läuft die Kaskade so, als hätte die Station nie einen Wert geliefert — was der Sache nach zutrifft, und die API-Ränge 8/9 übernehmen. Der Abstand von 1 K ist bewusst: direkt an der Schwelle ist nasser Schnee plausibel, der im Trichter schmilzt und kippt — das ist echter Schneefall und wird von Rang 4 als solcher erkannt.

**Der Filter muss vor der Kaskade laufen**, nicht zwischen den Rängen: Rang 4 deckt seine Bedingung vollständig ab und käme ihm zuvor.

| Rang | Bedingung | Ergebnis | Warum an dieser Stelle |
|---|---|---|---|
| 1 | Wind ≥ Sturmschwelle | `storm` | Gefahrenlage schlägt alles andere |
| 2 | WMO 95 | `thunder` | Station ist dafür blind |
| 2 | WMO 96 / 99 | `sleet` | Station ist dafür blind |
| 3 | (`snowfall` > 0 **oder** WMO 71/73/75/77/85/86) **und** (`tw` < Schneeschwelle **oder** `tw` fehlt) | `snow` | Kippwaage ist bei Schnee blind; Phase kommt trotzdem von der Station (§3) |
| 4 | Regen > 0 **und** `tw` < Schneeschwelle | `snow` | Greift, wenn gar kein WMO-Code vorliegt |
| 5 | Regen ≥ Starkregenschwelle | `rain_heavy` | Regenmesser vor Ort schlägt Vorhersage |
| 6 | Regen > 0 | `rain` | Regenmesser vor Ort schlägt Vorhersage |
| 7 | rF ≥ Nebelschwelle **und** Taupunkt-Spread ≤ Grenze | `fog` | Aus `dew_point()` ableitbar |
| 8 | **Kein Regenwert** **und** WMO 51–57 / 61 / 63 / 80 / 81 | `rain` | Ohne Regenmesser ist die API die einzige Quelle |
| 8 | **Kein Regenwert** **und** WMO 65 / 82 | `rain_heavy` | dito |
| 8 | **Kein Regenwert** **und** WMO 66 / 67 / 68 / 69 | `sleet` | Gefrierender Regen und Schneeregen, dito |
| 9 | **Keine Außen-rF** **und** WMO 45 / 48 | `fog` | Ohne Außenmodul kein Taupunkt |
| 10 | WMO 0 | `clear_day` / `clear_night` | Bewölkung kann nur die API liefern |
| 10 | WMO 1 / 2 / 3 | `fair` / `partly` / `overcast` | dito |
| 10b | WMO ≥ 45, von der Station widerlegt | `overcast` | Rest­aussage des Codes, siehe unten |
| 11 | Keine Regel greift | Kein Icon | Lieber nichts als falsch |

**Rang 10b** fängt den Fall ab, dass die API einen Niederschlagscode meldet, der Regenmesser aber 0,0 mm misst. Rang 8 darf dann nicht greifen — die Station hat gemessen, und sie gewinnt. Rang 10 greift auch nicht, weil der Code nicht in 0–3 liegt. Ohne 10b bliebe das Icon leer, obwohl eine Aussage gesichert ist: aus heiterem Himmel fällt nichts. Die Bewölkungsaussage im Code bleibt also gültig, auch wenn die Niederschlagsaussage vor Ort widerlegt ist. Das ist der häufige Fall von Niesel, der die Kippwaage nicht auslöst, oder von Regen, der gerade aufgehört hat.

Temperatur und Luftfeuchte für Vorfilter F und die Ränge 3–7 und 9 stammen vom **Außenmodul** (`NAModule1`), nicht vom Innenmodul.

`snowfall` in Rang 3 ist die Zentimeter-Angabe der letzten Stunde aus dem `current`-Block von Open-Meteo (§5). Sie steht vor dem WMO-Code, weil sie eine Menge nennt statt einer Kategorie. Bei Yr.no fehlt sie; dort trägt allein der Code.

### Warum die Ränge 8 und 9 an „kein Wert" hängen, nicht an „Wert ist null"

Ein Regenmesser, der 0,0 mm meldet, ist eine Aussage: es regnet hier gerade nicht. Die soll die Vorhersage nicht überstimmen, sonst wäre der Hybrid-Grundsatz aufgehoben. Ränge 8 und 9 greifen deshalb ausschließlich, wenn das zuständige Modul **gar nicht vorhanden ist oder keinen aktuellen Wert liefert** — bei Regen also `null`, nicht `0.0`.

Das ist der Normalfall und kein Randfall: Regen- und Windmesser sind bei Netatmo kostenpflichtige Zusatzmodule. Ohne die Ränge 8 und 9 zeigte das Icon bei allen Nutzern ohne Regenmesser während eines Landregens gar nichts.

Rang 3 steht über den Stationsrängen, übergeht dabei aber **keine** Stationsmessung: Eine Kippwaage, die bei Schneefall 0,0 mm meldet, hat nichts gemessen (§3). Vorfilter F formalisiert dieselbe Auslegung für die Gegenrichtung. Die Station behält damit in jeder Zeile das letzte Wort, außer dort, wo sie physikalisch blind ist.

### Ausfall der API

Der Ausfall der API ist **keine eigene Rangstufe**. Ist kein frischer WMO-Code verfügbar, wird der letzte bekannte eingesetzt und mit `stale => true` markiert; die Rangfolge läuft unverändert durch, die Stationsregeln 1 und 4–7 greifen also weiter. Erst wenn auch kein alter Code vorliegt, fallen die Ränge 2, 3, 8, 9 und 10 aus — dann bleibt, was die Station allein hergibt.

Tag/Nacht wird **nach** der Zustandsbestimmung angewendet und betrifft nur `clear`.

---

## 5 · Komponenten

### Neu: `includes/class-naws-weather-state.php`

Entscheidungslogik, getrennt von Darstellung. Zwei Methoden:

```php
public static function get_current(): array        // liest Daten, ruft decide()
public static function decide( array $in ): array  // reine Funktion, kein WordPress
```

`get_current()` liefert:

```php
[ 'state' => 'rain', 'wmo' => 63, 'source' => 'station',
  'is_day' => true, 'stale' => false, 'ts' => 1786122303 ]
```

`state` ist immer einer der internen Namen aus §3 oder `''` (kein Icon). `source` ist `station`, `api` oder `''` und dient nur der Nachvollziehbarkeit, nicht der Darstellung.

Datenquellen: `NAWS_Database::get_latest_readings()`, `NAWS_Forecast::get_current_conditions()`, `NAWS_Astro::dew_point()`.

**Tag/Nacht nicht über `NAWS_Astro::sun_times()`.** Die Methode formatiert auf `'HH:MM'`-Strings (`class-naws-astro.php:110-128`) und taugt zur Anzeige, nicht zum Vergleich. `get_current()` nutzt stattdessen `date_sun_info()` direkt — dieselbe Funktion, die `sun_times()` intern verwendet, nur ohne die Formatierung. Liefert Open-Meteo `is_day` mit, hat dieser Wert Vorrang; `date_sun_info()` ist der Ersatz, wenn keine Koordinaten oder keine API-Antwort vorliegen (`NAWS_Astro::get_coords()` kann `null` liefern, bevor die Station einmal synchronisiert wurde — dann gilt Tag).

Kein HTML, kein `echo`.

### Neu: `includes/class-naws-weather-icons.php`

Die zwölf SVGs und `get( string $state, bool $is_day ): string`.

Bewusst getrennt von `class-naws-icons.php`: die bestehende Klasse liefert kleine, einfarbige Sensorsymbole in vier Stilen (`emoji`, `outline`, `filled`, `minimal`). Die Wetter-Icons sind mehrfarbig, animiert und folgen diesen Sets nicht. Zusammengelegt wären beide Klassen unklar.

### Erweitert: `includes/class-naws-forecast.php` — aktueller Zustand statt Tageswert

**Die Klasse liefert heute keinen aktuellen WMO-Code.** Beide Provider werden auf reine Tageszeilen normalisiert:

- Open-Meteo (`class-naws-forecast.php:28-41`): Der Request holt ausschließlich `daily=…`. `days[0]['weathercode']` ist der *signifikanteste Code des ganzen Tages*.
- Yr.no (`class-naws-forecast.php:210-215`): nimmt bewusst das **12:00-Symbol** als Tagessymbol.

Für eine Fünf-Tage-Vorhersagekachel ist das richtig. Für ein Icon, das das Wetter *jetzt* zeigen soll, ist es die falsche Granularität: ein Gewitter um 18 Uhr setzt den Tagescode auf 95, Rang 2 feuert damit von 0 bis 24 Uhr, und da Rang 2 über den Stationsrängen steht, kann die Station das nicht korrigieren. Rang 10 zeigte entsprechend die Tages-Bewölkung — klarer Abend nach bedecktem Morgen ergäbe `overcast`.

Neue Methode, getrennt von `get_forecast()` und mit eigenem Cache:

```php
public static function get_current_conditions(): array
// [ 'wmo' => ?int, 'is_day' => ?bool, 'cloud_cover' => ?int,
//   'snowfall' => ?float, 'precipitation' => ?float,
//   'fetched_at' => int, 'stale' => bool ]
```

- **Open-Meteo:** zusätzlicher Request-Parameter `current=weather_code,cloud_cover,is_day,snowfall,precipitation`. `snowfall` ist die Zentimeter-Summe der vergangenen Stunde und die belastbarste Schneeaussage, die extern zu bekommen ist — eine Menge statt einer Kategorie. `is_day` kommt gratis mit.
- **Yr.no:** der erste `timeseries`-Eintrag ab jetzt, dessen `next_1_hours.summary.symbol_code` über das vorhandene `yr_symbol_to_wmo()` läuft. Die Daten liegen in der Antwort bereits vor und werden derzeit nur weggeworfen. `snowfall` gibt `compact` nicht her — dort trägt allein der Code.
- **Zwei Korrekturen an `yr_symbol_to_wmo()`** (`class-naws-forecast.php:256-297`), beide für das Icon nötig:
  1. Schneeregen wird auf **WMO 68** abgebildet (Zeilen 271–276). 68/69 stehen jetzt in Rang 8 als `sleet`; ohne diese Ergänzung fiele Schneeregen bei Yr.no-Nutzern durch alle Ränge.
  2. Der Rückfallwert ist `?? 3` (Zeile 296), also „bedeckt". Für die Vorhersagekachel vertretbar, für ein Icon, das aktuelles Wetter behauptet, nicht — ein unbekanntes Symbol würde als Bewölkungsaussage ausgegeben. `get_current_conditions()` braucht daher eine Variante, die bei unbekanntem Symbol `null` liefert. `yr_symbol_to_wmo()` selbst bleibt für die Vorhersage unverändert.
- **Transient:** eigener Schlüssel `naws_forecast_current_<provider>`, **TTL 30 Minuten**. Nicht die 3 h von `CACHE_TTL` — die sind für Tageswerte gedacht und wären für „aktuell" zu träge.
- **Kein gemeinsamer Cache mit `get_forecast()`.** Getrennte Schlüssel, damit `flush_cache()` beide löschen kann, ohne dass eine Vorhersage-Anzeige den Icon-Cache aufwärmt oder umgekehrt.

### Erweitert: `includes/class-naws-astro.php` — `wet_bulb()`

```php
public static function wet_bulb( float $temp_c, float $humidity_pct ): float
```

Feuchtkugeltemperatur nach der Stull-Näherung, direkt neben `dew_point()`, aus denselben zwei Eingangswerten. Sie ist das Phasenmaß für die Ränge 3, 4 und 5a.

Die Näherung gilt für rF etwa 5–99 % und Temperaturen von −20 bis +50 °C mit einem Fehler unter etwa 1 K. Für den Schnee-Regen-Grenzbereich um 0 °C ist sie gut; außerhalb des Gültigkeitsbereichs spielt sie für diesen Zweck keine Rolle. Die Eingangswerte werden vorher geklemmt, damit `log()` kein Argument ≤ 0 bekommt.

### Neu: Option `naws_weather_last_wmo`

§7 verlangt „letzter bekannter WMO-Code" bei API-Ausfall. Ein Transient kann das nicht leisten — er verfällt definitionsgemäß, und genau dann, wenn die API nicht erreichbar ist, liegt auch kein frischer vor.

Deshalb **eine** neue Option: `[ 'wmo' => int, 'is_day' => bool, 'ts' => int ]`, geschrieben bei jedem erfolgreichen Abruf. Das weicht bewusst von §8 („keine neuen Options") ab; §8 meint die *Einstellungen*, die weiterhin vollständig in `naws_settings` bleiben. Ist der gespeicherte Eintrag älter als 6 Stunden, gilt er als unbrauchbar und wird verworfen — dann fallen die API-Ränge aus, statt ein Wetter von gestern zu zeigen.

### Erweitert: `includes/class-naws-shortcodes.php`

`[naws_weather_icon]`, Attribut `size` (Standard 96, Minimum 64). Kein Beschriftungsattribut.

### Erweitert: `templates/live.php`

Icon über dem Dashboard, gekapselt hinter der Backend-Option. Ist sie aus, wird weder gerendert noch CSS geladen.

### Erweitert: `includes/class-naws-ajax.php`

Feld `weather_state` in der Antwort des bestehenden Live-Zyklus.

### Erweitert: Plugin-Stylesheet

Keyframes und Animationsregeln einmal zentral, nicht pro SVG.

---

## 6 · Datenfluss

Berechnet wird **serverseitig in PHP**. Das JavaScript bekommt einen fertigen Zustandsnamen, nie die Rohwerte — sonst wäre die Schwellenlogik doppelt gepflegt.

**Dashboard-Icon:** hängt sich in den vorhandenen AJAX-Zyklus ein, JS tauscht das SVG. Kein zusätzlicher Request.

**Freistehender Shortcode:** wird beim Seitenaufbau gerendert und bleibt stehen. Steht er auf derselben Seite wie das Dashboard, läuft er über dieselbe Antwort mit. Eine eigene Polling-Schleife bekommt er nicht.

**Caching:** Der WMO-Code kommt aus dem 30-Minuten-Transient von `get_current_conditions()` — **nicht** aus dem 3-Stunden-Transient von `get_forecast()`, der Tageswerte enthält. Die Stationswerte sind die letzte Zeile aus `naws_readings`. Der **berechnete Zustand wird nicht zwischengespeichert** — ein Transient darüber würde nur dafür sorgen, dass der Regenmesser verzögert durchschlägt.

Damit hat das Icon zwei unterschiedlich schnelle Hälften: die Station schlägt im Live-Zyklus sofort durch, die API-Hälfte höchstens halbstündlich. Das ist gewollt — die API-Ränge betreffen Bewölkung, Gewitter und den Ersatz für fehlende Module, und keiner davon rechtfertigt einen Fremdabruf pro Seitenaufruf.

---

## 7 · Fallback

| Fall | Verhalten |
|---|---|
| API nicht erreichbar | Letzter bekannter WMO-Code aus `naws_weather_last_wmo`, Stationsregeln greifen normal, `stale => true` |
| Letzter Code älter als 6 h | Wird verworfen; Ränge 2, 3, 8, 9, 10 fallen aus |
| Station liefert nicht | Nur API-Zustand über die Ränge 2, 3, 8, 9, 10 |
| Beides fehlt | Es wird **nichts** gerendert — kein Platzhalter, kein falsches Icon |
| Kein Regenmesser | Ränge 4–6 fallen still aus, **Rang 8 übernimmt** |
| Kein Außenmodul | Rang 7 fällt still aus, **Rang 9 übernimmt** |
| Kein Windmesser | Sturmregel (Rang 1) fällt still aus, ohne Ersatz |

Der modulweise Ausfall ist kein Randfall: Regen- und Windmesser sind bei Netatmo Zusatzmodule. Bei den meisten Nutzern des Plugins läuft die Hybrid-Logik teilweise ins Leere. Sie muss **pro Modul** degradieren, nicht als Ganzes.

Für Sturm gibt es bewusst keinen API-Ersatz: `get_current_conditions()` liefert keine Windgeschwindigkeit, und die Tages-Böenspitze aus `get_forecast()` wäre wieder der Fehler, der in §5 beschrieben ist — sie stünde den ganzen Tag an. Ohne Windmesser gibt es kein Sturm-Icon.

---

## 8 · Einstellungen

Alles in das bestehende `naws_settings`-Array. Keine neuen Options.

| Einstellung | Standard | Sanitierung |
|---|---|---|
| Icon über Dashboard | an | Boolean |
| Starkregen ab | 4,0 mm/h | `floatval`, geklemmt 0,1–50 |
| Schnee unter (Feuchtkugel) | 1,0 °C | `floatval`, geklemmt −20–5 |
| Nebel ab rF | 97 % | `floatval`, geklemmt 80–100 |
| Nebel Spread ≤ | 0,5 K | `floatval`, geklemmt 0,1–5 |
| Sturm ab | 75 km/h | `floatval`, geklemmt 20–200 |

Die Werte gehören in die Einstellungen und nicht fest in den Code: eine Station in Norddeutschland braucht andere Schwellen als eine im Gebirge.

Speichern über den bestehenden Nonce-geschützten Handler, keine eigene Route.

---

## 9 · Ausgabe-Sicherheit

### Der kses-Filter würde die Icons zerlegen

`naws_svg_kses_args()` (`includes/class-naws-helpers.php:10`) erlaubt heute nur `svg`, `path`, `circle`, `line`, `polyline`, `polygon`, `g` und daran ausschließlich Geometrie- und Strichattribute.

Nicht erlaubt und für die Icons nötig: `defs`, `linearGradient`, `stop`, `filter`, `feDropShadow`, `rect`, `ellipse` sowie die Attribute `class`, `style`, `transform`, `role`, `aria-label`.

Durch `naws_kses_svg()` geschickt bliebe von jedem Icon ein Torso übrig.

**Die Allowlist zu erweitern ist der schlechtere Weg.** `wp_kses` jagt `style` durch `safecss_filter_attr`, und das entfernt CSS-Custom-Properties — also genau die `--d`-Verzögerungen, über die Tropfen und Flocken versetzt fallen.

**Stattdessen:** Die Icons sind fest im Quelltext stehende Konstanten, kein Nutzereingang. Sie werden als **literales Markup in einer Template-Datei** ausgegeben, nicht als String durch eine Variable. Dann gibt es nichts zu escapen, weil nichts interpoliert wird. Die `--d`-Verzögerungen wandern ins Stylesheet als `nth-of-type`-Regeln.

`naws_svg_kses_args()` bleibt unverändert und weiter zuständig für die Sensor-Icons.

### Der defs-Block darf nur einmal pro Seite stehen

Alle Icons referenzieren dieselben Verläufe über `url(#g-cloud)` und so weiter. IDs müssen im Dokument eindeutig sein — bei Shortcode plus Dashboard-Icon gäbe es sonst Dubletten.

Der `<defs>`-Block wird **einmal pro Seite** über `wp_footer` ausgegeben, gesteuert über ein statisches Flag.

### AJAX-Übergabe

Der Zustandsname geht über einen `wp_footer`-Block mit `script[type="application/json"]` ins JavaScript, **nicht** über `wp_add_inline_script` — letzteres hat sich in diesem Plugin wiederholt als unzuverlässig erwiesen.

### SVG-Transform-Falle

Beim Zeichnen der Icons aufgetreten und für die Umsetzung relevant: **Das SVG-Attribut `transform` und die CSS-Eigenschaft `transform` schließen sich gegenseitig aus — CSS gewinnt.** Ein per `transform="translate(…)"` positioniertes Element, dessen CSS-Animation ebenfalls `transform` setzt, springt zum Koordinatenursprung.

Lösung: Positionierung in eine äußere Gruppe, Animation in eine innere. Bei Elementen, die um sich selbst rotieren oder skalieren, zusätzlich `transform-box: fill-box` setzen oder den Drehpunkt explizit über eine Custom-Property übergeben.

---

## 10 · Tests

Das Plugin hat keine Testsuite. PHPUnit dafür einzuführen wäre am Ziel vorbei.

Der Schnitt zwischen `get_current()` und `decide()` macht die gesamte Rangfolge ohne Framework prüfbar: `decide()` kennt weder Datenbank noch API noch WordPress. Ein einfaches PHP-Skript spielt die Tabelle aus Abschnitt 4 durch — Messwerte rein, erwarteter Zustand raus.

Mindestens abzudecken:

- Jeden der elf Ränge einzeln
- Vorrang bei Konflikt: Regen läuft **und** API meldet WMO 0 → Regen gewinnt
- Sturm bei gleichzeitigem Regen → Sturm gewinnt
- Fehlender Regenmesser (`rain === null`) + WMO 63 → `rain` über Rang 8
- Regenmesser meldet **0.0** + WMO 63 → **kein** `rain` (Rang 8 greift nicht, die Station hat gemessen), sondern `overcast` über Rang 10b
- Fehlendes Außenmodul + WMO 45 → `fog` über Rang 9
- Schnee: WMO 71 bei `tw` −3 °C **und** Regenmesser 0.0 → `snow` (Rang 3; die Kippwaage hat nichts gemessen)
- Schnee: WMO 71 bei `tw` +8 °C → **nicht** `snow`, Rang 3 fällt durch
- Schnee ohne Code: `snowfall` 0.6 cm bei `tw` −1 °C → `snow` (Rang 3 über die Menge)
- Feuchtkugel gegen Lufttemperatur (gerechnete Werte): 3,5 °C Luft bei 30 % rF ergibt `tw` = **−1,6 °C** → `snow`; **dieselbe** Lufttemperatur bei 95 % rF ergibt `tw` = **2,9 °C** → kein `snow`. Eine reine Lufttemperaturschwelle hätte beide Fälle gleich entschieden — das ist der ganze Grund für `wet_bulb()`
- Kontrollpunkt der Formel selbst: 20 °C bei 50 % rF → `tw` = 13,7 °C (psychrometrischer Standardwert)
- Schmelzwasser (Vorfilter F): Regen 0,4 mm bei `tw` −4 °C, kein WMO-Code → **kein** `rain` und **kein** `snow`; der Wert wird verworfen, kein Icon
- Schmelzwasser knapp: Regen 0,4 mm bei `tw` 0,5 °C bei Schneeschwelle 1,0 → Filter greift **nicht** (weniger als 1 K unter der Schwelle), Rang 4 liefert `snow`
- Fehlender Windmesser → Rang 1 wird übersprungen
- Weder Station noch API → leeres Ergebnis, kein Icon
- Tag/Nacht-Umschaltung nur bei `clear`

Die Unterscheidung zwischen `null` und `0.0` beim Regenwert ist der Kern der Ränge 8/9 und der Test, der am ehesten schweigend kaputtgeht — `empty()` oder ein loses `!` würde beides gleich behandeln und die Regel unbrauchbar machen.

---

## 11 · Bewusst nicht enthalten

- **Reduzierte Icon-Variante für kleine Größen.** Die Untergrenze liegt bei 64 px.
- **Einbindung in Infobar und `[naws_current]`.** Nicht gewünscht.
- **Sichtbare Beschriftung.** Nur `aria-label`.
- **Mondphase im Nacht-Icon.** `NAWS_Astro::moon_data()` könnte es liefern; später möglich, jetzt nicht im Umfang.
- **Historie der Wetterzustände.** Es wird nur der aktuelle Zustand bestimmt, nichts gespeichert.

### Eine dritte Datenquelle — geprüft und verworfen

Untersucht wurden **BrightSky/DWD** (`api.brightsky.dev`, ohne Schlüssel, echte Synop-Beobachtungen mit `condition` und mitgeliefertem `distance`-Feld, nur Deutschland) und **NOAA METAR** (`aviationweather.gov/api/data/metar`, ohne Schlüssel, JSON, weltweit, Present-Weather-Codes wie `SN`, `SHSN`, `RASN`).

Beide sind technisch brauchbar und liefern echte Beobachtungen statt Modellrechnungen. Sie werden trotzdem **nicht** eingebaut, aus einem sachlichen Grund:

Setzt man die Station an die erste Stelle, bleibt für eine externe Quelle nur ein Fall übrig — Regenmesser meldet nichts, Feuchtkugel unter der Schwelle. Genau diesen Fall beantwortet `snowfall` aus dem Open-Meteo-`current`-Block bereits, und zwar aus einer Gitterzelle, **in der die Station liegt**. Eine Synop-Beobachtung aus typischerweise 15–20 km Entfernung ist zwar wahrer, aber über einen anderen Ort. Da sich die Schneefallgrenze über wenige Kilometer und zweihundert Höhenmeter verschiebt, ist sie ausgerechnet im Grenzfall am wenigsten übertragbar. Ein unvollkommenes Stellvertretermaß würde gegen ein anderes getauscht.

Dazu kommt der Preis: jeder externe Dienst braucht eine eigene Offenlegung in der `readme.txt` samt Datenschutzlink und ist ein weiterer Ausfallpfad.

Lohnend wäre es erst bei einer Beobachtung in zwei bis drei Kilometern Entfernung — das ist geografischer Zufall, darauf kann ein Plugin nicht bauen. Die Frage gilt damit als beantwortet und muss nicht erneut aufgemacht werden.

---

## 12 · Offene Punkte

- **Genaue Einbettung über dem Dashboard** — Größe, Abstand, Ausrichtung. Wird beim Umsetzen am konkreten Layout entschieden.
- **Sprachdateien.** Die `aria-label`-Texte brauchen je einen Eintrag pro Zustand in `de.php`, `en.php`, `no.php`, nach dem Schema `wx_state_<interner Name>` — also `wx_state_clear_day`, `wx_state_clear_night`, `wx_state_fair`, `wx_state_partly`, `wx_state_overcast`, `wx_state_fog`, `wx_state_rain`, `wx_state_rain_heavy`, `wx_state_snow`, `wx_state_sleet`, `wx_state_thunder`, `wx_state_storm`. Die internen Namen stehen in der zweiten Spalte von Abschnitt 3.
- **Version.** Ob das Feature in 1.6.6 oder 1.7.0 geht, ist offen — es ist additiv, bricht nichts.
