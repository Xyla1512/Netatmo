# Berechnete Werte als Shortcode — Design

**Datum:** 2026-08-18
**Plugin:** XTX Integration for Netatmo (`xtx-integration-for-netatmo`), Stand v1.9.6
**Zielversion:** die nächste Feature-Version (Nummer noch nicht festgelegt)
**Status:** Design abgestimmt, Implementierungsplan steht aus

---

**Umfang beachten:** Diese Spec umfasst zwei Dinge — einen **neuen Shortcode** für berechnete Einzelwerte und eine **neue Rechenschicht** für rund ein Dutzend Klimakennzahlen. Sie ändert außerdem sichtbar das Verhalten der bestehenden Infobar, weil `NAWS_Astro::feels_like()` umgebaut wird (§6.1).

Die Umsetzung erfolgt in **drei Stufen** (§12). Die Grammatik wird hier vollständig festgelegt, damit Stufe 2 und 3 keine bereits veröffentlichten Schreibweisen brechen müssen.

---

## 1 · Ziel

Berechnete Werte — Taupunkt, gefühlte Temperatur, Frosttage, Heizgradtage — sollen sich **einzeln in Fließtext und Tabellen einbauen** lassen, statt nur als Teil fertiger Dashboards zu erscheinen.

Bisher gibt es dafür nichts: `[naws_value]` liefert ausschließlich **rohe Sensorparameter** aus `naws_readings`. Alles Gerechnete steckt in Templates fest.

Beispiel für das Ziel:

```
Der Taupunkt liegt bei [naws_calc value="dewpoint"] und es fühlt sich
wie [naws_calc value="feels_like"] an.

In diesem Jahr gab es [naws_calc value="summer_days" period="year"]
Sommertage, davon [naws_calc value="summer_days" mode="max_streak"]
am Stück.
```

---

## 2 · Getroffene Entscheidungen

| Frage | Entscheidung |
|---|---|
| Shortcode-Form | **Einer für alle**: `[naws_calc value="…"]`, kein Shortcode pro Wert, keine Aliasse |
| Vorhandene Rechenwerte | **Alle vier Familien** aufnehmen: thermisch, Astronomie, seltene Himmelsereignisse, abgeleitete Kleinigkeiten |
| Gefühlte Temperatur | Drei-Regime-Modell **ersetzt** das bisherige Steadman-only — gilt auch für die bestehende Infobar |
| Bioklima | Kein zweiter Zahlenwert, sondern die **Empfindungsstufe** zur gefühlten Temperatur |
| SPI | Wird gebaut, obwohl die Referenzinstallation zu wenig Historie hat — andere Nutzer haben lange Reihen |
| Frost- und Eistage | **Beide** im Katalog; sie messen Verschiedenes (§6.3) |
| Datenquelle Tageskennzahlen | **Stationszeile** von `naws_daily_summary`, nicht das Außenmodul (§5) |
| Lücke in einer Serie | **Bricht die Serie.** Über nicht gemessene Tage wird nichts behauptet |
| Keine Daten vs. null Tage | Müssen unterscheidbar bleiben — `0` ist eine Antwort, fehlende Daten ergeben den `fallback` |
| Schemaänderung | **Keine.** DB-Version bleibt 1.4, keine Migration |
| Schwellwerte | Länderabhängige in die Einstellungen, anwendungsabhängige ans Attribut (§9) |

---

## 3 · Shortcode-Grammatik

`value` ist das einzige Pflichtattribut. Jeder Katalogeintrag hat eine **Art**, und die entscheidet, welche Attribute überhaupt greifen.

| Art | Bedeutung | Wirksame Attribute |
|---|---|---|
| `instant` | Momentanwert aus der letzten Messung oder dem Standort | `module` |
| `dayclass` | Tagesklasse, zählbar und serienfähig | `station`, `period`, `year`, `mode` |
| `sum` | Summenkennzahl über einen Zeitraum | `station`, `period`, `year` |
| `index` | SPI | `station`, `months` |

### 3.1 Attribute

| Attribut | Gilt für | Werte | Vorgabe |
|---|---|---|---|
| `value` | alle | Katalogschlüssel (§4) | — (Pflicht) |
| `module` | `instant` | `outdoor`, `indoor`, `wind`, `rain` oder MAC-Adresse | `outdoor` |
| `station` | `dayclass`, `sum`, `index` | Stations-ID | erste aktive Station |
| `period` | `dayclass`, `sum`, `index` | `year`, `month`, `all`, `<N>d` (z. B. `30d`) | `year` |
| `year` | `dayclass`, `sum` | vierstellige Jahreszahl; schlägt `period` | — |
| `mode` | `dayclass` | `count`, `streak`, `max_streak` | `count` |
| `months` | `index` | `1`, `3`, `6`, `12` | `3` |
| `base` | `sum` | Basis- bzw. Grenztemperatur, überschreibt die Einstellung | je Wert (§6) |
| `cap` | `gdd` | obere Kappung | `30` |
| `decimals` | alle | Nachkommastellen | je Wert |
| `unit` | alle | `1` / `0` — Einheit anzeigen | `1` |
| `note` | `dayclass`, `sum`, `index` | `1` / `0` — Datengrundlage anhängen | `0` |
| `fallback` | alle | Ersatztext bei fehlenden Daten | `--` |
| `tag` | alle | umschließendes Element | `span` |
| `class` | alle | zusätzliche CSS-Klasse | — |

`module`, `unit`, `decimals`, `fallback`, `tag` und `class` heißen und wirken **exakt wie bei `[naws_value]`**. Wer den einen Shortcode kennt, kennt den anderen.

**Zwei Festlegungen, die sonst mehrdeutig blieben:**

`base` setzt bei `hdd` und `cooling_days`/`cdd` die **Grenztemperatur**, bei `gdd` die **Basistemperatur**. Die **Bezugstemperatur** der Heizgradtage (Raumtemperatur, 20 °C) hat bewusst **kein** Attribut — sie ist in allen genannten Normen einheitlich und kommt ausschließlich aus den Einstellungen.

`mode="streak"` misst die Serie, die **am Ende des gewählten Zeitraums** endet — nicht zwingend die bis heute laufende. Ohne Zeitraumangabe ist das die aktuelle Serie; mit `year="2025"` die Serie, die am 31.12.2025 endete. Damit bleibt `streak` auch für abgeschlossene Zeiträume sinnvoll definiert.

### 3.2 Nicht anwendbare Attribute

Ein Attribut, das für die Art des Wertes keine Bedeutung hat, wird **ignoriert und dokumentiert**, nicht stillschweigend geschluckt und nicht als Fehler behandelt. `mode="streak"` an einer Summe liefert die Summe, keine erfundene Zahl.

---

## 4 · Wertekatalog

### 4.1 Momentanwerte (`instant`)

| Schlüssel | Bedeutung | Einheit | Grundlage |
|---|---|---|---|
| `dewpoint` | Taupunkt | °C/°F | `NAWS_Astro::dew_point()`, vorhanden |
| `feels_like` | Gefühlte Temperatur | °C/°F | umgebaut, §6.1 |
| `heat_index` | Hitzeindex | °C/°F | `NAWS_Astro::heat_index()`, vorhanden |
| `wet_bulb` | Feuchtkugeltemperatur | °C/°F | `NAWS_Astro::wet_bulb()`, vorhanden |
| `bioclimate` | Bioklimatische Empfindungsstufe | Text | §6.2 |
| `wind_compass` | Windrichtung als Himmelsrichtung | Text | `NAWS_Helpers::degrees_to_compass()` |
| `co2_level` | CO₂-Bewertung | Text | `NAWS_Helpers::get_co2_level()` |
| `sunrise` | Sonnenaufgang | Uhrzeit | `NAWS_Astro::sun_times()` |
| `sunset` | Sonnenuntergang | Uhrzeit | `NAWS_Astro::sun_times()` |
| `daylength` | Tageslänge | h:mm | aus `sun_times()` |
| `moon_phase` | Mondphase | Text | `NAWS_Astro::moon_data()` |
| `moon_illumination` | Beleuchtungsgrad | % | `NAWS_Astro::moon_data()` |
| `next_supermoon` | Nächster Supermond | Datum | `NAWS_Astro::next_supermoon()` |
| `next_lunar_eclipse` | Nächste Mondfinsternis | Datum | `NAWS_Astro::next_lunar_eclipse()` |

Vierzehn Einträge, davon **zwölf auf bereits vorhandenen Funktionen** — sie werden bisher nur in Templates verwendet. Neue Rechenlogik brauchen nur `feels_like` (Umbau) und `bioclimate`.

### 4.2 Tagesklassen (`dayclass`)

Alle zählbar (`count`), serienfähig (`streak`, `max_streak`). Quelle ist die Stationszeile.

| Schlüssel | Bedingung | Kennzahl |
|---|---|---|
| `ice_days` | `temp_max` < 0 °C | Eistag / Dauerfrosttag |
| `frost_days` | `temp_min` < 0 °C | Frosttag |
| `summer_days` | `temp_max` ≥ 25 °C | Sommertag |
| `hot_days` | `temp_max` ≥ 30 °C | Heißer Tag |
| `tropical_nights` | `temp_min` ≥ 20 °C | Tropennacht, §6.4 |
| `heating_days` | `temp_avg` < Heizgrenze | Heiztag, §6.5 |
| `cooling_days` | `temp_avg` > Kühlgrenze | Kühltag, §6.6 |

### 4.3 Summen (`sum`)

| Schlüssel | Formel | Einheit |
|---|---|---|
| `hdd` | Σ (Raumtemperatur − `temp_avg`) über alle Heiztage | Kd |
| `cdd` | Σ (`temp_avg` − Kühlgrenze) über alle Kühltage | Kd |
| `gdd` | Σ max(0, (min(`temp_max`, Kappung) + `temp_min`)/2 − Basis) | Kd |
| `glts` | Σ `temp_avg` > 0 ab 1. Januar, monatsgewichtet | °C |
| `glts_start` | Datum der Überschreitung von 200 | Datum |

### 4.4 Index

| Schlüssel | Bedeutung | Einheit |
|---|---|---|
| `spi` | Standardized Precipitation Index über `months` Monate | dimensionslos |

**Gesamt: 27 Katalogeinträge.**

---

## 5 · Datenquelle — ein Befund, der den Entwurf geändert hat

Der erste Entwurf sah vor, Tageskennzahlen aus der Tagestabelle **des Außenmoduls** zu lesen. Eine Messung an der Referenzinstallation zeigte: Das hätte **leere Ergebnisse** geliefert.

`naws_daily_summary` enthält Zeilen für genau drei Modul-IDs, nicht für sechs:

| Zeile | Inhalt |
|---|---|
| Stationszeile (NAMain) | Außentemperaturen (`temp_min`, `temp_max`, `temp_avg`), Regen, Wind, Druck |
| NAModule4 Sleeping | nur Innenwerte |
| NAModule4 Gast | nur Innenwerte |

Für NAModule1 (außen), NAModule2 (Wind) und NAModule3 (Regen) existiert **keine eigene Tageszeile**. `compute_daily_summary()` schreibt die Stationsaggregate unter der `station_id`.

**Folge für den Entwurf:** Tagesklassen, Summen und Index lesen immer die Stationszeile. Ihr Auswahlattribut heißt deshalb `station`, nicht `module`. Ein `module="outdoor"` an einer Tageskennzahl wird dokumentiert ignoriert.

**Momentanwerte sind davon nicht betroffen** — sie lesen `naws_readings`, wo die Modulunterscheidung real ist und ein Innentaupunkt eine sinnvolle Angabe darstellt (Schimmel- und Kondensatbeurteilung).

### 5.1 Datenlage der Referenzinstallation (Stand 2026-08-18)

874 Tage von 2024-03-28 bis 2026-08-18; an 846 davon Temperaturen, an allen 874 Regenwerte.

**Korrektur einer früheren Messung:** In dieser Sitzung wurde zwischenzeitlich eine 108-tägige Regenlücke für Januar bis April 2026 gemeldet. Die Wiederholung derselben Abfrage ergab **null Treffer**; Januar 2026 hat 31 von 31 Tagen mit Regenwert. Die Zeilen vor Mai wurden am 2026-08-18 um 08:30:37 zuletzt geändert. **Es gibt keine Regenlücke.**

---

## 6 · Formeln und Definitionen

### 6.1 Gefühlte Temperatur — Umbau von `NAWS_Astro::feels_like()`

**Bisher:** ausschließlich Steadman/BOM über alle Temperaturbereiche, ohne Windchill-Zweig. Bei Kälte mit Wind liefert das einen zu milden Wert.

**Neu:** Drei Regime, umgeschaltet nach Wetterlage.

| Bedingung | Modell |
|---|---|
| Temperatur < 10 °C **und** Wind > 5 km/h | Windchill (NOAA 2001) |
| Temperatur ≥ 27 °C **und** relative Feuchte > 40 % | Hitzeindex (Rothfusz, NOAA 2011) — vorhanden |
| sonst | Apparente Temperatur nach Steadman — vorhanden |

Quelle: <https://rechner-portal.de/wetter-klima/gefuehlte-temperatur/gefuehlte-temperatur-rechner>

**Neu zu schreiben ist nur `wind_chill()`**; die anderen beiden Zweige rufen vorhandene Funktionen.

**Bewusste Nebenwirkung:** Die Infobar (`templates/infobar.php`) zeigt danach bei Kaltwind andere Werte als bisher. Das ist die Korrektur einer Schwäche, keine Regression — gehört aber in die Release-Notes.

**Nicht umgesetzt wird das Klima-Michel-Modell des DWD.** Es verlangt Strahlungsbilanz sowie Bekleidungs- und Aktivitätsannahmen; eine Netatmo-Station misst nichts davon.

### 6.2 Bioklima

Der Begriff bezeichnet kein rechenbares Maß, sondern die Gesamtheit atmosphärischer Einflüsse auf Lebewesen (thermischer, aktinischer und lufthygienischer Wirkungskomplex). Die Quelle nennt **keine einzige konkrete Kennzahl**.

Quelle: <https://de.wikipedia.org/wiki/Bioklima>

**Umsetzung:** `bioclimate` gibt die **Empfindungsstufe** zur gefühlten Temperatur als Text aus — von sehr kalt bis extrem heiß, entsprechend der Stufentabelle der Quelle aus §6.1. Kein zweiter Zahlenwert, sondern die Einordnung des vorhandenen.

### 6.3 Frosttag und Eistag sind nicht dasselbe

| Kennzahl | Bedingung | Bedeutung |
|---|---|---|
| Frosttag | `temp_min` < 0 °C | es hat irgendwann gefroren |
| Eistag | `temp_max` < 0 °C | es ist den ganzen Tag nicht aufgetaut |

Jeder Eistag ist zugleich Frosttag, umgekehrt nicht. An der Referenzinstallation gemessen: **2025 vierzig Frosttage gegen vier Eistage.** Beispiel 2026-02-20: Minimum −5,5 °C, Maximum +2,5 °C — Frosttag, kein Eistag.

Quellen: <https://de.wikipedia.org/wiki/Eistag>, <https://de.wikipedia.org/wiki/Frosttag>

### 6.4 Tropennacht

Schwellenwert: Minimumtemperatur ≥ 20 °C. **Zwei anerkannte Messfenster:** der DWD misst 18 bis 06 Uhr UTC, MeteoSchweiz den ganzen Kalendertag (00 bis 00 Uhr UTC).

`temp_min` aus der Tagestabelle entspricht der **MeteoSchweiz-Konvention**. Das ist ein gültiger Standard, kein Notbehelf, und wird in der Doku ausgewiesen. Das DWD-Fenster bräuchte Rohdaten, die nicht über die gesamte Historie zurückreichen.

Quelle: <https://de.wikipedia.org/wiki/Tropennacht>

### 6.5 Heiztag und Heizgradtage

Ein Heiztag ist ein Tag, dessen mittlere Außentemperatur unter der **Heizgrenze** liegt.

| Region | Heizgrenze | Raumtemperatur | Schreibweise |
|---|---|---|---|
| Deutschland (VDI 2067, DIN 4108 T6) | 15 °C | 20 °C | HT20/15 |
| Österreich, Schweiz, Liechtenstein | 12 °C | 20 °C | HT20/12 |

Heizgradtage: Σ (Raumtemperatur − Tagesmittel) über alle Heiztage, Einheit Kd.

Quelle: <https://de.wikipedia.org/wiki/Heiztag>

### 6.6 Kühltag und Kühlgradtage

**Schlechter genormt als die Heizseite.** Für die Kühlgrenze kursieren 18 °C und 21 °C nebeneinander; es gibt keinen einzelnen Wert, der sich als der Standard ausgeben ließe.

**Entscheidung:** Vorgabe 18 °C, einstellbar wie die Heizgrenze. Die Uneindeutigkeit wird in der Doku benannt, statt einen Wert als normativ auszugeben.

### 6.7 Wachstumsgradtage

WGT = (`temp_min` + `temp_max`) / 2 − Basis

Basistemperatur üblicherweise **10 °C**, Maximumtemperatur üblicherweise bei **30 °C gekappt**. Beides pflanzenabhängig, deshalb am Attribut und nicht in den Einstellungen.

**Variante:** einfache Mittelwertmethode — negative Tagesbeiträge werden auf 0 gesetzt. Die modifizierte Methode, die Temperaturen vor der Mittelung auf den Schwellenwert anhebt, wird **nicht** umgesetzt; eine Variante genügt, und die einfache ist die verbreitetere.

Quelle: <https://de.wikipedia.org/wiki/Wachstumsgradtag>

### 6.8 Grünlandtemperatursumme

Summe aller Tagesmitteltemperaturen **über 0 °C** ab dem 1. Januar, mit Monatsgewichten:

| Monat | Faktor |
|---|---|
| Januar | 0,5 |
| Februar | 0,75 |
| ab März | 1,0 |

Wird die Summe im Frühjahr über **200 °C** hinaus überschritten, gilt der nachhaltige Vegetationsbeginn für das Grünland als erreicht.

**Wichtig:** Die 200 °C sind **keine Qualitätsschwelle.** Eine Summe von 80 ist ein korrekter Wert und bedeutet lediglich, dass die Vegetationsperiode noch nicht begonnen hat.

`glts_start` gibt das Datum der Überschreitung. Solange 200 nicht erreicht ist, liefert es einen sprechenden Text (noch nicht erreicht), der über `fallback` überschreibbar bleibt — kein leeres Feld.

Der Zeitraum ist durch die Definition auf seit Jahresbeginn festgelegt; `period` wirkt hier nur über `year`.

Quelle: <https://de.wikipedia.org/wiki/Gr%C3%BCnlandtemperatursumme>

### 6.9 SPI

Der Index vergleicht die Niederschlagssumme eines Zeitraums mit der Verteilung desselben Zeitraums über viele Jahre. Aussagekraft entsteht erst mit langen Reihen; verbreitet empfohlen werden rund **30 Jahre**.

**Entscheidung:** Der SPI wird gebaut, weil andere Installationen lange Importe haben können. Er rechnet **ab zwei vollständigen Jahren** — technisch das Minimum für eine Verteilungsanpassung — und liefert darunter den `fallback`.

Die Datengrundlage wird ausgewiesen (§8.3): die Doku-Seite nennt die tatsächliche Zahl vollständiger Jahre der Installation und sagt im Klartext, dass der Wert unter etwa dreißig Jahren eher Tendenz als Messgröße ist.

Quelle: <https://climatedataguide.ucar.edu/climate-data/standardized-precipitation-index-spi>

---

## 7 · Architektur

**Keine Schemaänderung.** Alle Kennzahlen entstehen aus vorhandenen Spalten. DB-Version bleibt 1.4, keine Migration.

### 7.1 Neue Dateien

**`includes/class-naws-climate.php` — die reine Mathematik.**

```
count_days( array $rows, $condition ): int
current_streak( array $rows, $condition, string $today ): int
max_streak( array $rows, $condition ): int
degree_days( array $rows, float $threshold, float $reference, string $direction ): float
growing_degree_days( array $rows, float $base, float $cap ): float
grassland_sum( array $rows ): float
grassland_start( array $rows ): ?string
spi( array $monthly_sums, int $months ): ?float
```

Alle bekommen fertige Tageszeilen als Array und geben Zahlen zurück: **keine Optionen, keine Datenbank, keine Uhr.** Dasselbe Muster wie `NAWS_Cron::normalise_interval()` und `backoff_interval()` — dadurch läuft die Testdatei ohne WordPress-Bootstrap.

**`includes/class-naws-calc.php` — Katalog und Vermittlung.**

Hält für jeden Schlüssel seine Metadaten (Art, Einheit, Nachkommastellen, Sprachschlüssel, benötigte Spalten), holt die Daten, ruft die Rechenfunktion, formatiert. Diese Klasse kennt WordPress, `NAWS_Climate` nicht.

### 7.2 Geänderte Dateien

| Datei | Änderung |
|---|---|
| `includes/class-naws-shortcodes.php` | `add_shortcode( 'naws_calc', … )` und `sc_calc()` |
| `includes/class-naws-astro.php` | Drei-Regime-`feels_like()`, neu `wind_chill()`, neu `thermal_sensation()` |
| `includes/class-naws-database.php` | Lesemethode für Tagesbereiche der Stationszeile |
| `includes/class-naws-admin.php` | drei neue Einstellungsfelder in `sanitize_settings()` |
| `admin/views/settings.php` | Heizgrenze, Raumtemperatur, Kühlgrenze |
| `admin/views/shortcodes.php` | Katalog als Referenztabelle mit Datenverfügbarkeit |
| `languages/de.php`, `en.php`, `no.php` | rund 100 neue Schlüssel |

### 7.3 Zwischenspeicher

Tageskennzahlen ändern sich einmal täglich. Ergebnis je Kombination aus Wert, Station, Zeitraum und Parametern als **Transient, gültig bis Mitternacht** in der Seitenzeitzone über `naws_timezone()`.

Ohne das löst jede Zeile einer Auswertungstabelle eine eigene Abfrage über bis zu 900 Tageszeilen aus. Mit ihm kosten zehn Shortcodes auf einer Seite eine Abfrage.

---

## 8 · Fehlerbehandlung und Datengrundlage

### 8.1 Keine Daten und null Tage dürfen nicht gleich aussehen

Die wichtigste Regel dieser Spec.

| Lage | Ausgabe |
|---|---|
| Zeitraum hat Tageszeilen, Bedingung trifft nie zu | `0` |
| Zeitraum hat **keine** Tageszeile mit der benötigten Spalte | `fallback` |
| Momentanwert ohne aktuelle Messung | `fallback` |
| Unbekannter `value`-Schlüssel | `fallback` **und** eine Zeile über `NAWS_Logger` |

Andernfalls behauptet die Seite einen frostfreien Winter, wo nur die Messung fehlte.

### 8.2 Datengrundlage am Shortcode

`note="1"` hängt die Abdeckung an: `31 (bei 230 von 230 Tagen)`. Standardmäßig aus, damit Fließtext sauber bleibt.

### 8.3 Datengrundlage im Backend

`admin/views/shortcodes.php` bekommt den Katalog als Referenztabelle: Schlüssel, Bedeutung, Formel, Einheit, benötigte Spalten — und **pro Eintrag, ob diese Installation die Daten dafür hat**.

Damit sieht man vor dem Einbauen, ob ein Wert etwas liefert. Für den SPI steht dort die Zahl vollständiger Jahre samt Einordnung.

### 8.4 Ausgabe und Sicherheit

Der Shortcode **gibt zurück statt zu echoen**. Ausgabe durchgehend über `esc_html()`, Attribute über `esc_attr()`. Alle Tagesgrenzen laufen über `naws_timezone()`, nicht über eine fest verdrahtete Zeitzone.

---

## 9 · Einstellungen

Drei neue Felder im Abschnitt Betrieb, weil sie **vom Land abhängen** und sonst an jedem Shortcode wiederholt werden müssten:

| Feld | Vorgabe | Begründung |
|---|---|---|
| Heizgrenztemperatur | 15 °C | 12 °C in Österreich und der Schweiz |
| Raumtemperatur (Bezug) | 20 °C | einheitlich in allen genannten Normen |
| Kühlgrenztemperatur | 18 °C | uneinheitlich genormt, §6.6 |

**Anwendungsabhängige Schwellwerte bleiben am Attribut:** Basistemperatur und Kappung des Wachstumsgradtages sind pflanzenabhängig; auf einer Seite können mehrere Kulturen nebeneinander stehen.

**Attribut schlägt Einstellung, immer.**

---

## 10 · Sprachdateien

Rund **100 neue Schlüssel** je Datei: 26 Bezeichnungen, die Empfindungsstufen, Mondphasen, Einheiten und die Doku-Texte der Referenztabelle. Stand vor der Umsetzung: 614 je Datei.

Norwegisch wird **vollständig** mitgeführt, nicht nachgereicht — die Übersetzung ist seit 1.8.3 komplett und soll es bleiben.

---

## 11 · Tests

### 11.1 `tests/test-climate-indices.php`

Füttert `NAWS_Climate` mit erfundenen Wetterjahren und vergleicht gegen von Hand nachgerechnete Ergebnisse. Ohne WordPress-Bootstrap, wie `tests/test-cron-polling.php`.

Pflichtfälle:

- Serie über den Jahreswechsel
- `streak` am Ende eines abgeschlossenen Zeitraums (§3.1) — die Serie, die am letzten Tag des Zeitraums endet, nicht die heutige
- **Lücke mitten in einer Serie — bricht sie** (drei Frosttage, fehlender Tag, zwei Frosttage ergibt 3, nicht 5)
- Zeitraum mit genau einem Tag
- alle Tage erfüllen die Bedingung; kein Tag erfüllt sie
- Schaltjahr beim Februar-Gewicht der Grünlandtemperatursumme
- Heizgradtage bei 15/20 und bei 12/20
- Wachstumsgradtage mit und ohne Kappung, negativer Tagesbeitrag wird 0
- SPI auf einer synthetischen Reihe mit bekannter Verteilung
- SPI unter zwei Jahren liefert `null`

### 11.2 `tests/test-calc-catalogue.php`

Prüft für **jeden** der 27 Einträge, dass Art, Einheit und Nachkommastellen deklariert sind und der Sprachschlüssel **in allen drei Sprachdateien** existiert. Bei dieser Menge ist ins Norwegische vergessen sonst keine Möglichkeit, sondern eine Gewissheit.

### 11.3 Abnahme gegen echte Zahlen

An der Referenzinstallation am 2026-08-18 gemessen (Stationszeile):

| Jahr | Tage | Frosttage | Eistage | Sommertage | Heiße Tage | Tropennächte |
|---|---|---|---|---|---|---|
| 2024 | 251 | 8 | 0 | 76 | 24 | 23 |
| 2025 | 365 | 40 | 4 | 54 | 17 | 5 |
| 2026 (bis 18.08.) | 230 | 31 | 12 | 60 | 26 | 19 |

`[naws_calc value="frost_days" year="2025"]` muss exakt `40` ergeben. Das ist eine schärfere Prüfung als jeder erfundene Testfall.

### 11.4 Übliche Gates

`php -l` über alle geänderten Dateien, PHPCS gegen `.phpcs.xml.dist` mit **null Befunden**.

---

## 12 · Umsetzung in drei Stufen

| Stufe | Inhalt | Ergebnis |
|---|---|---|
| **1** | `[naws_calc]` mit vollständiger Grammatik, die 14 Momentanwerte, Umbau von `feels_like()`, Doku-Seite | Shortcode benutzbar |
| **2** | 7 Tagesklassen mit Serien, 5 Summen, Zeitraum-Logik, Einstellungen, Zwischenspeicher | Klimakennzahlen vollständig |
| **3** | SPI mit Verteilungsanpassung und Ausweis der Datengrundlage | Katalog komplett |

Jede Stufe ist für sich lauffähig und könnte einzeln veröffentlicht werden. Die Grammatik steht ab Stufe 1 fest.

---

## 13 · Bewusst nicht enthalten

| Punkt | Begründung |
|---|---|
| Ein Shortcode je Wert oder Aliasse | Ein Weg, eine Doku — §2 |
| Klima-Michel-Modell des DWD | Braucht Strahlung, Bekleidung, Aktivität — §6.1 |
| Modifizierte Mittelwertmethode beim Wachstumsgradtag | Eine Variante genügt — §6.7 |
| DWD-Nachtfenster 18–06 UTC für Tropennächte | Rohdaten reichen nicht weit genug zurück — §6.4 |
| Fremddaten als SPI-Referenzreihe | Neue externe Datenquelle, eigenes Vorhaben mit Folgen für Directory-Richtlinie 7 |
| E-Mail-Benachrichtigungen | Vom Nutzer am 2026-08-18 ausdrücklich abgelehnt |
| Schema- oder Migrationsänderungen | Nicht nötig — §7 |
