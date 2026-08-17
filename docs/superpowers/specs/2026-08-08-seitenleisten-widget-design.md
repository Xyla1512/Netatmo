# Seitenleisten-Widget — Design

**Datum:** 2026-08-08
**Plugin:** XTX Integration for Netatmo (`xtx-integration-for-netatmo`), Stand v1.7.0
**Zielversion:** 1.8.0
**Status:** Design abgestimmt, Implementierungsplan steht aus

Visueller Entwurf, vom Nutzer abgenommen: https://claude.ai/code/artifact/4980af24-87b7-48ae-9eda-af5e71a344bd

---

**Umfang beachten:** Diese Spec umfasst zwei Dinge — das neue Widget und die **Vereinheitlichung des Icon-Sets über das ganze Plugin** (§7a). Letzteres ändert sichtbar, wie `[naws_forecast]` und der Vorhersagestreifen im Live-Dashboard aussehen.

---

## 1 · Ziel

Ein kompaktes Wetter-Widget für schmale Seitenleisten, ausgelegt auf **250 px**. Es zeigt oben das animierte Wetter-Icon mit der Außentemperatur daneben, darunter Regen und Wind, darunter eine Vorhersage über drei oder fünf Tage.

Das Widget fasst zusammen, was das Plugin schon einzeln kann, in einer Form, die in eine Spalte passt. Es ersetzt nichts.

---

## 2 · Getroffene Entscheidungen

| Frage | Entscheidung |
|---|---|
| Ausspielweg | **Shortcode** `[naws_weather_widget]`, kein `WP_Widget`, kein Gutenberg-Block |
| Konfiguration | Backend-Abschnitt mit Live-Vorschau, dort werden die Vorgaben gesetzt |
| Layout | Variante B des Entwurfs: Kopf mit Icon und Temperatur, zwei Kacheln, Vorhersage als Spaltenstreifen |
| Icon-Set | Das neue mehrfarbige Set **überall**, auch in `[naws_forecast]` und im Dashboard-Vorhersagestreifen (§7a) |
| Inhalt | **Fest verdrahtet.** Keine Auswahl einzelner Messwerte |
| Vorhersagelänge | **Nur 3 oder 5 Tage.** Kein Zwischenwert |
| Böen | **Nicht enthalten** |
| Modulname in der Anzeige | **Nicht enthalten** — kein „Außenmodul“ unter der Temperatur |
| Wind | Mit Einheit, also `12 km/h` |
| Regen | Aktuelle Rate in mm/h, nicht die 24-Stunden-Summe |
| Fußzeile | Bleibt: Ort links, Uhrzeit rechts |
| Überschrift | Keine. Themes setzen die Widget-Überschrift selbst |

### Warum Shortcode und nicht Widget oder Block

Das Plugin hat bislang **weder** eine `register_widget()`- **noch** eine `register_block_type()`-Stelle; alles läuft über Shortcodes. Ein klassisches `WP_Widget` wäre seit WordPress 5.8 nur noch über die Legacy-Ansicht oder das Plugin *Classic Widgets* voll nutzbar — mehr Code für weniger Reichweite. Ein echter Block brächte eine JavaScript-Build-Kette in ein Plugin, das bisher ohne auskommt, und wäre für die WordPress.org-Prüfung ein deutlich größerer Brocken.

Der Shortcode lässt sich über den Shortcode-Block in jede blockbasierte Seitenleiste setzen und funktioniert zusätzlich in Theme-Buildern und direkt in Theme-Dateien über `do_shortcode()`.

---

## 3 · Aufbau

Von oben nach unten, alles in einem Rahmen mit 12 px Radius:

1. **Kopf** — Icon 64 px links, rechts daneben die Außentemperatur groß und darunter das Zustandswort (`Regen`, `Bedeckt` …).
2. **Kachelzeile** — zwei gleich breite Kacheln nebeneinander: `Regen 0,4 mm/h` und `Wind 12 km/h`. Beide gleich gebaut: Beschriftung klein und versal, darunter Zahl und Einheit, Einheit in gedeckter Farbe.
3. **Vorhersagestreifen** — drei oder fünf gleich breite Spalten, je Spalte Wochentagskürzel, Icon, Höchst- und Tiefstwert untereinander.
4. **Fußzeile** — Ort links, Uhrzeit des letzten Abrufs rechts, klein und einfarbig.

**Spaltenbreite:** Bei fünf Tagen 46 px je Spalte, bei drei Tagen 77 px. Das ist der spürbarste Unterschied zwischen den beiden Einstellungen und der Grund, warum vier Tage bewusst entfallen — sie brächten keinen eigenen Nutzen.

**Höhe:** rund 250 px bei fünf Tagen, rund 230 px bei drei.

---

## 4 · Datenquellen

| Wert | Quelle |
|---|---|
| Zustand und Icon | `NAWS_Weather_State::get_current()` + `NAWS_Weather_Icons::render()` |
| Vorhersage-Icons | `NAWS_Weather_State::wmo_to_state()` + `NAWS_Weather_Icons::render_inline()` |
| Zustandswort | `NAWS_Weather_Icons::label()` |
| Außentemperatur | `NAModule1`, Parameter `Temperature` |
| Regen | `NAModule3`, Parameter `Rain` |
| Wind | `NAModule2`, Parameter `WindStrength` |
| Vorhersage | `NAWS_Forecast::get_forecast( 3\|5 )` |
| Ort | `$forecast['location_name']` |
| Uhrzeit | `$forecast['fetched_at']` |

Einheiten und Umrechnung laufen über die vorhandenen `NAWS_Helpers::format_value()` und `NAWS_Helpers::get_unit()`, damit die Einstellungen für °C/°F, km/h und mm/in auch hier greifen.

### `read_station()` wird öffentlich

`NAWS_Weather_State::read_station()` löst bereits Außen-, Regen- und Windmodul auf und liefert `[ 'rain', 'wind', 'temp', 'humidity' ]` mit `null` für fehlende Werte. Genau das braucht das Widget. Die Methode wird von `private` auf `public` gehoben und wiederverwendet, statt die Modulauflösung ein zweites Mal zu schreiben.

Ein Unterschied bleibt: `read_station()` bevorzugt beim Wind die **Böenspitze**, weil die Sturmregel daran hängt. Das Widget zeigt aber die mittlere Windgeschwindigkeit. Die Methode bekommt daher beide Werte im Rückgabefeld — `wind` (Böe bevorzugt, wie bisher) und zusätzlich `wind_avg`. Bestehende Aufrufer bleiben unberührt.

---

## 5 · Komponenten

### Erweitert: `includes/class-naws-shortcodes.php`

`[naws_weather_widget]` mit einem einzigen Attribut:

| Attribut | Werte | Standard |
|---|---|---|
| `days` | `3` oder `5` | Backend-Einstellung, ab Werk `5` |

Andere Werte werden auf den nächstliegenden erlaubten gezogen: alles unter 4 wird 3, alles ab 4 wird 5.

### Neu: `includes/class-naws-widget-data.php`

Die Aufbereitung, getrennt von der Darstellung — derselbe Schnitt wie bei `NAWS_Weather_State`, und aus demselben Grund: nur so ist die Degradation ohne Framework prüfbar.

```php
public static function build( array $station, array $forecast, int $days ): array
```

Reine Funktion: kein WordPress, keine Datenbank, kein HTML. Sie normalisiert die Tageszahl, entscheidet welche Kacheln es gibt, und schneidet die Vorhersage zu. Ergebnis:

```php
[
  'tiles' => [ [ 'key' => 'rain', 'value' => '0,4', 'unit' => 'mm/h' ], … ],  // 0–2 Einträge
  'days'  => [ [ 'label' => 'Sa', 'icon' => 'rain', 'max' => 11, 'min' => 6 ], … ],
  'empty' => false,   // true, wenn gar nichts darstellbar ist
]
```

Formatierung und Einheiten kommen von `NAWS_Helpers`, werden aber **vom Aufrufer** hineingereicht, damit `build()` frei von WordPress bleibt.

### Neu: `templates/weather-widget.php`

Das Markup. Der Kopf-Icon-Aufruf gibt literales Template-Markup aus (siehe §7), alles andere wird regulär escaped.

### Erweitert: Plugin-Stylesheet

Ein eigener Block `.naws-wgt-*` in `assets/css/frontend.css`. **Nicht** `.naws-wx` verwenden — das ist bereits die Vorhersagekarte, und `.naws-wxi` sind die Icon-SVGs.

Das Widget setzt keine feste Breite. Es füllt seinen Container und ist ab etwa 220 px lesbar; die 250 px des Entwurfs sind der Bezugswert, nicht eine Bedingung.

### Erweitert: `includes/class-naws-weather-icons.php`

Eine zweite Ausgabemethode für kleine, ruhige Icons in Reihen:

```php
public static function render_inline( string $state, int $size ): string
```

Unterschiede zu `render()`: keine Mindestgröße, kein `.naws-weather-icon`-Wrapper, und die Animation ist über die Klasse `naws-wxi--still` abgeschaltet. Zwei benannte Methoden statt Schalterparametern, damit an der Aufrufstelle lesbar ist, welche Sorte Icon dort steht.

`queue_defs()` gilt für beide — der gemeinsame `<defs>`-Block wird weiterhin genau einmal pro Seite gedruckt, egal wie viele Icons welcher Sorte auf ihr stehen.

### Erweitert: `templates/forecast.php` und `templates/live.php`

Beide ersetzen ihren `wp_kses( NAWS_Forecast::get_weather_svg( … ) )`-Aufruf durch `NAWS_Weather_Icons::render_inline()` mit literaler Ausgabe. Das ist der einzige Eingriff in bestehende Vorlagen.

### Erweitert: `admin/views/appearance.php`

Ein Abschnitt **Seitenleisten-Widget** mit der Tageszahl als Auswahl und einer **Live-Vorschau** in einer 250 px breiten Spalte — dieselbe Technik wie die Icon-Vorschau auf der Shortcode-Seite, inklusive der beiden Admin-Eigenheiten: der `<defs>`-Block hängt im Backend an `admin_footer`, und `frontend.css` muss dort unter eigenem Handle geladen werden.

Die Appearance-Seite ist der richtige Ort, weil es um Darstellung geht und dort schon Icon-Sets und Farben eingestellt werden.

### Erweitert: `admin/views/shortcodes.php`

Eine Karte für `[naws_weather_widget]` in der Layout-Sektion, mit Kopier-Knopf und dem `days`-Attribut — so wie es für `[naws_weather_icon]` bereits geschehen ist.

---

## 6 · Einstellungen

Eine einzige neue Einstellung, im bestehenden `naws_settings`-Array:

| Einstellung | Standard | Sanitierung |
|---|---|---|
| Vorhersagetage im Widget (`wgt_days`) | 5 | nur `3` oder `5`, sonst 5 |

Gespeichert über den vorhandenen Nonce-geschützten Handler. Da `sanitize_settings()` seit 1.7.0 über die gespeicherten Optionen **merged**, genügt es, den Schlüssel dort zu ergänzen; versteckte Spiegelfelder in den anderen Formularen sind nicht mehr nötig.

---

## 7 · Ausgabe-Sicherheit

Zwei verschiedene Icon-Quellen, und sie werden **unterschiedlich** behandelt:

**Kopf-Icon** — kommt aus `NAWS_Weather_Icons::render()`. Mehrfarbig, animiert, mit `<defs>`, `class`, `style` und `aria-label`. Das überlebt `naws_svg_kses_args()` nicht und wird deshalb als literales Markup ausgegeben, genau wie beim Dashboard-Icon. Der gemeinsame `<defs>`-Block wird weiterhin einmal pro Seite über `wp_footer` beziehungsweise `admin_footer` gedruckt.

**Vorhersage-Icons** — ebenfalls aus `NAWS_Weather_Icons`, statisch und ohne Wrapper. Auch sie werden als literales Markup ausgegeben. Siehe §7a.

Das Kopf-Icon wird mit **64 px** gerendert, nicht mit den 56 px des Entwurfs — das ist die Untergrenze des Sets. Der Unterschied kostet 8 px Höhe.

---

## 7a · Das neue Icon-Set gilt überall

Das flache Set aus `NAWS_Forecast::get_weather_svg()` wird **nicht mehr verwendet**. Alle Wetterdarstellungen im Plugin nutzen das mehrfarbige Set aus `NAWS_Weather_Icons`.

Betroffen sind genau drei Stellen:

| Ort | Größe | Zustand |
|---|---|---|
| `templates/weather-widget.php` (neu) | 28 px | neu |
| `templates/forecast.php:81` — `[naws_forecast]` | 36 px, ab 600 px Breite 44 px | umgestellt |
| `templates/live.php:296` — Vorhersagestreifen im Dashboard | wie dort gesetzt | umgestellt |

Die Infobar nutzt Sensorsymbole aus `NAWS_Icons`, keine Wetter-Icons, und bleibt unberührt.

### Drei Dinge, die das nach sich zieht

**1 · Die Mindestgröße von 64 px gilt hier nicht.** Sie wurde für das freistehende Zustands-Icon festgelegt, das allein die Aussage trägt und keine Beschriftung hat. In einer Vorhersagespalte steht das Icon neben Wochentag und zwei Temperaturen — es stützt die Aussage, statt sie allein zu tragen. Bei 28 px sind Gewitter und Graupel nicht mehr sicher zu unterscheiden; das wird in Kauf genommen, weil die Zahlen danebenstehen.

**2 · Die Tages-Icons laufen nicht.** Fünf bis sieben gleichzeitig animierte Icons in einer Reihe sind unruhig, und Bewegung neben Zahlen zieht den Blick von den Zahlen weg. Sie bekommen dasselbe Markup, aber die Animation wird über eine Modifikatorklasse abgeschaltet. Animiert bleibt allein das große Zustands-Icon im Kopf.

**3 · Die Feinabstufung wird gröber.** Das alte Set kannte fünfzehn Zustände, darunter `drizzle`, `rain-light`, `shower`, `shower-heavy`, `snow-light`, `snow-heavy` und `freezing`. Das neue kennt zwölf. Beim Umsetzen fallen zusammen:

| Alt | Neu |
|---|---|
| `drizzle`, `rain-light`, `rain`, `shower` | `rain` |
| `rain-heavy`, `shower-heavy` | `rain_heavy` |
| `snow-light`, `snow`, `snow-heavy` | `snow` |
| `freezing` | `sleet` |
| `cloudy` | `overcast` |

Leichter Regen und Starkregen bleiben unterschieden, Schauer und Dauerregen nicht mehr. Das ist der Preis der Vereinheitlichung und bewusst akzeptiert.

### Neue gemeinsame Zuordnung

WMO-Code zu Zustandsnamen, damit alle drei Stellen dieselbe Abbildung benutzen:

```php
NAWS_Weather_State::wmo_to_state( int $wmo, bool $is_day = true ): string
```

Sie nutzt die bereits vorhandenen Konstanten `WMO_SNOW`, `WMO_RAIN`, `WMO_RAIN_HEAVY`, `WMO_SLEET` und `WMO_FOG` und ist damit **automatisch deckungsgleich** mit der Rangfolge des Live-Icons. Für Vorhersagetage wird `is_day = true` übergeben; ein Vorhersagetag hat keine Nachtvariante.

### `get_weather_svg()` bleibt bestehen

Die Methode wird nicht mehr aufgerufen, aber nicht entfernt: Sie ist `public static` und könnte in eigenen Theme-Schnipseln von Nutzern stecken. Sie bekommt einen Hinweis im Docblock, dass sie durch `NAWS_Weather_Icons` abgelöst ist. Kosten: ein paar Kilobyte, die niemanden stören.

---

## 8 · Fehlende Module

Regen- und Windmesser sind bei Netatmo kostenpflichtige Zusatzmodule; die Mehrheit der Nutzer hat sie nicht. Das Widget muss **pro Wert** degradieren:

| Fall | Verhalten |
|---|---|
| Kein Regenwert | Kachel entfällt, Wind-Kachel nimmt die volle Breite |
| Kein Windwert | Kachel entfällt, Regen-Kachel nimmt die volle Breite |
| Beides fehlt | Die ganze Kachelzeile entfällt, das Widget schließt früher |
| Keine Außentemperatur | Kopf zeigt nur Icon und Zustandswort |
| Kein Zustand bestimmbar | Kein Icon, Kopf beginnt mit der Temperatur |
| Vorhersage nicht erreichbar | Streifen und Fußzeile entfallen, der obere Teil bleibt |
| Nichts davon verfügbar | Es wird **gar nichts** ausgegeben, kein leerer Rahmen |

Kein Platzhalter, keine Striche, keine Fehlermeldung im Frontend. Ein Widget, das Lücken zeigt, sieht kaputt aus; eines, das kürzer wird, sieht gewollt aus.

---

## 9 · Bewusst nicht enthalten

- **Auswahl einzelner Messwerte.** Ausdrücklich nicht gewünscht.
- **Böen.** Ausdrücklich gestrichen.
- **Vier Tage Vorhersage.** Nur 3 oder 5.
- **Eigene Aktualisierung.** Das Widget wird beim Seitenaufbau gerendert und bleibt stehen. Genau wie beim freistehenden Icon gibt es keine eigene Abfrageschleife; die Uhrzeit in der Fußzeile macht das Alter sichtbar.
- **Überschrift, Titelattribut, Farbwahl.** Das Widget nimmt die Farben des Plugins.
- **Luftfeuchte, Luftdruck, gefühlte Temperatur, Mondphase.** Nicht im Umfang.

---

## 10 · Tests

Wie beim Wetter-Icon gilt: keine Testsuite im Plugin, PHPUnit wäre unverhältnismäßig. Prüfbar ohne Framework ist die Auswahl der Tageszahl und die Degradation, sofern die Aufbereitung von der Ausgabe getrennt bleibt.

Dafür bekommt das Widget eine reine Funktion, die aus Stationswerten und Vorhersage die Anzeigestruktur baut:

```php
NAWS_Widget_Data::build( array $station, array $forecast, int $days ): array
```

Sie kennt weder WordPress noch Datenbank noch HTML. Abzudecken:

- `days` 3 → drei Spalten; `days` 5 → fünf; `days` 4 → auf 5 gezogen; `days` 1 → auf 3 gezogen
- Regen `null` → keine Regenkachel; Regen `0.0` → Kachel mit `0,0` (der Unterschied zwischen „kein Messer“ und „misst null“ gilt hier genauso)
- Wind `null` → keine Windkachel
- Beide `null` → leere Kachelliste
- Vorhersage mit `error` → leerer Streifen, oberer Teil unberührt
- Weder Station noch Vorhersage → Ergebnis leer, Template gibt nichts aus

Für die Icon-Vereinheitlichung aus §7a zusätzlich `NAWS_Weather_State::wmo_to_state()`, ebenfalls ohne Framework prüfbar:

- Je ein Code aus jeder Gruppe: 0 → `clear_day`, 1 → `fair`, 2 → `partly`, 3 → `overcast`, 45 → `fog`, 63 → `rain`, 65 → `rain_heavy`, 73 → `snow`, 68 → `sleet`, 95 → `thunder`
- Zusammenfallende Codes: 51, 61, 80 und 81 ergeben alle `rain`; 71, 75, 85 und 86 alle `snow`
- `is_day = false` bei Code 0 → `clear_night`, bei jedem anderen Code ohne Wirkung
- Unbekannter Code, etwa 4 → leerer String; das Template zeigt dann kein Icon statt eines falschen

---

## 11 · Offene Punkte

- **Genauer Ort der Backend-Einstellung.** Vorgeschlagen ist die Appearance-Seite; falls die Vorhersage-Einstellungen als passender empfunden werden, ist der Wechsel trivial.
- **Sprachdateien.** Neue Schlüssel für die Kachelbeschriftungen (`wgt_rain`, `wgt_wind`), die Backend-Texte und die Shortcode-Karte, je in `de.php`, `en.php`, `no.php`.
- **Version.** 1.8.0, additiv, bricht nichts.
