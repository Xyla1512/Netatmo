# Modulvergleich: `[naws_compare]`

**Datum:** 2026-11-13
**Betrifft:** neu `includes/class-naws-compare.php`, `templates/compare.php`, `assets/js/compare-boot.js`, Baukasten-Teil in `admin/views/shortcodes.php` + `assets/js/admin.js`, Tests; geändert `class-naws-shortcodes.php`, `class-naws-database.php` (`get_daily_summaries()` Feldliste), `class-naws-labels.php`, `frontend.css`, `admin.css`, `readme.txt`, `CHANGELOG.md`, Kataloge
**Auslöser:** WP.org-Thread „Using Alias names for sensors“ (Matthew/beaminstermuseum), Frage 2: Luftfeuchte innen (Basis) und außen über zwei Jahre in einem Diagramm. Steht seit 12.11. als `modulvergleich` unter „Geplant“ auf der Produktseite. Frank, 13.11.: „wenn möglich flexibel … nicht nur die Luftfeuchte“, „auswählen tut nur der Seitenbetreiber“, „B bitte“ (Shortcode + Baukasten im Backend).
**Ziel-Release:** offen (Schnitt auf Franks Ansage)

---

## 1. Ziel und Festlegungen

Zwei beliebige Tagesreihen aus beliebigen Modulen in **einem** Diagramm mit **durchgehender Zeitachse** über Monate und Jahre. Datenquelle ist ausschließlich die Tagesübersicht (`wp_naws_daily_summary`), ein Punkt je Tag.

| Frage | Entscheidung (Brainstorming 13.11.) |
|---|---|
| Was vergleichbar | jede Messgröße jedes Moduls gegen jede andere (Tabelle §3) |
| Wer wählt | **nur der Seitenbetreiber**; Besucher sehen ein festes Diagramm |
| Wie wählt er | **Ansatz B:** Angaben im Shortcode **plus** Baukasten auf der Shortcode-Seite im Backend (Auswahllisten → fertiger Shortcode zum Kopieren, mit Vorschau) |
| Anzahl Reihen | genau zwei (`a`, `b`); `b` darf fehlen (dann eine Reihe) |
| Einheiten | gleiche Einheit → eine Achse; verschiedene → Achse links (a) und rechts (b) |
| Lücken | Linie unterbrochen (Chart.js `spanGaps: false`), nie als 0 |

Datenlage auf dev (13.11.): Tageszeilen seit 2024-03-28. Außenwerte, Regen, Wind **und** die Innenwerte der Basis stehen in der Zeile der Basisstation (`module_id` = NAMain). Innenmodule (NAModule4) haben eigene Zeilen. Lückenlos: Außen Temperatur/Feuchte/Druck, Basis innen Feuchte, Innenmodule Temperatur/Feuchte/CO₂. **Basis innen Temperatur, CO₂, Lärm nur an 76 von 961 Tagen** (historischer Import hat sie offenbar nicht geliefert — eigenes Thema, nicht Teil dieses Vorhabens; das Diagramm zeigt die Lücke ehrlich).

## 2. Ziele, Nicht-Ziele, Anti-Ziele

**Ziele**
- Matthews Fall (Feuchte innen/außen über zwei Jahre) mit einer Zeile Shortcode.
- Jede sinnvolle Kombination (Zimmer gegen Zimmer, Temperatur gegen Regen, CO₂ gegen Außentemperatur).
- Der Betreiber muss keine Kürzel raten: Baukasten erzeugt den Shortcode.

**Nicht-Ziele**
- Auswahl durch Besucher, Zoomen/Schieben im Diagramm, mehr als zwei Reihen
- Werte unterhalb der Tagesauflösung (Rohmesswerte), gleitende Mittel/Glättung
- Reparatur der Lücken der Basis-Innentemperatur im Import
- Gutenberg-Block / Elementor-Widget

**Anti-Ziele**
- Lücken als Nullwerte oder durchgezogene Linie über fehlende Wochen.
- MAC-Adressen im Seitenquelltext (das Plugin verwendet seit 1.9.x Modul-Referenzen, siehe `NAWS_Helpers::module_ref_map()`).
- Ein Diagramm, das bei falscher Angabe die Seite zerlegt: Besucher sehen dann nichts, Redakteure einen Hinweis (Muster `NAWS_Records::notice()`).

## 3. Reihen: Modul + Messgröße

Eine Reihe ist `modul:größe`. **Modul** = vorhandene Referenz aus `NAWS_Helpers::module_ref_map()`: `outdoor`, `indoor` (Basis), `wind`, `rain`, `in-<slug>` (Innenmodule). **Größe** je Modultyp und die Spalte, aus der sie kommt:

| Modul (Typ) | Größe | Zeile | Spalte | Einheit (`get_unit`-Param) | Darstellung |
|---|---|---|---|---|---|
| `outdoor` (NAModule1) | `temp` | Basis | `temp_avg` | Temperature | Linie |
| | `temp_min` | Basis | `temp_min` | Temperature | Linie |
| | `temp_max` | Basis | `temp_max` | Temperature | Linie |
| | `humidity` | Basis | `humidity_avg` | Humidity | Linie |
| `indoor` (NAMain) | `temp` | Basis | `indoor_temp_avg` | Temperature | Linie |
| | `humidity` | Basis | `indoor_humidity_avg` | Humidity | Linie |
| | `co2` | Basis | `co2_avg` | CO2 | Linie |
| | `noise` | Basis | `noise_avg` | Noise | Linie |
| | `pressure` | Basis | `pressure_avg` | Pressure | Linie |
| `rain` (NAModule3) | `rain` | Basis | `rain_sum` | Rain | **Balken** |
| `wind` (NAModule2) | `wind` | Basis | `wind_avg` | WindStrength | Linie |
| | `gust` | Basis | `gust_max` | GustStrength | Linie |
| `in-*` (NAModule4) | `temp` | eigene | `indoor_temp_avg` | Temperature | Linie |
| | `humidity` | eigene | `indoor_humidity_avg` | Humidity | Linie |
| | `co2` | eigene | `co2_avg` | CO2 | Linie |

„Zeile Basis“ = die Tageszeile mit `module_id` der Basisstation (wie `NAWS_Calc::station_row_id()`). Die genauen `get_unit()`-Parameternamen (z. B. `CO2`, `Noise`, `Humidity`) prüft der Bauer in `NAWS_Helpers::get_unit()` und nimmt die dort vorhandenen.

`NAWS_Database::get_daily_summaries()` erlaubt heute `indoor_temp_avg` und `indoor_humidity_avg` nicht (`$allowed_fields`, class-naws-database.php ~1015) — beide werden ergänzt.

Eine Reihe ohne einen einzigen Wert im Zeitraum (Modul fehlt, Größe nie gemessen) gilt als **leer**: Hinweis für Redakteure, Besucher sehen das Diagramm mit der anderen Reihe bzw. gar nichts, wenn beide leer sind.

## 4. Shortcode

```
[naws_compare a="indoor:humidity" b="outdoor:humidity" from="2024-04" to="" title="" height="360"]
```

| Angabe | Bedeutung | Vorgabe | Grenzen |
|---|---|---|---|
| `a` | erste Reihe `modul:größe` | `indoor:humidity` | Pflicht in der Praxis; unbekannt → Hinweis für Redakteure |
| `b` | zweite Reihe | `outdoor:humidity` | leer erlaubt (`b=""` = nur eine Reihe) |
| `from` | Beginn `JJJJ-MM` oder `JJJJ-MM-TT` | leer = erster Tag mit Daten | |
| `to` | Ende, gleiches Format | leer = gestern (Site-Zeitzone) | `to` vor `from` → vertauschen |
| `months` | Alternative zu `from`: die letzten N Monate bis `to` | leer | 1–120; `from` hat Vorrang |
| `title` | Überschrift | leer = keine | Text, escaped |
| `height` | Diagrammhöhe in px | `360` | 200–900 geklemmt |

Die Vorgaben von `a`/`b` sind Matthews Fall: ein nackter `[naws_compare]` zeigt Feuchte innen gegen außen.

Registrierung über `NAWS_Shortcodes::TAGS` (Kommentar „nineteen“ → „twenty“, `tests/test-shortcode-atts.php` 19 → 20).

## 5. Darstellung (Frontend)

```
[title]
● Luftfeuchte · Basis (innen)   ● Luftfeuchte · Aussen          ← Legende mit Anzeigenamen
 %                                                     %
 90 ┤      ╭─╮                    ╭╮                  ┤
 60 ┤──────╯ ╰──────╮      ╭──────╯╰─────            ┤
 30 ┤               ╰──────╯                          ┤
    └──────────────────────────────────────────────────
     Apr 2024        Okt 2024       Apr 2025       Okt 2025
```

- Chart.js aus dem Plugin (`naws-chartjs`, `naws-chartjs-adapter`, bereits registriert), Zeitachse `type: 'time'`, Einheit automatisch (Monat bei langen Zeiträumen).
- Reihenname in der Legende: „Größe · Anzeigename des Moduls“ (`NAWS_Helpers::display_name()` — die Anzeigenamen aus 2.1.8), Größennamen übersetzbar.
- Achsen: gleiche Einheit (`get_unit()` gleich) → eine Achse links, beschriftet mit der Einheit; sonst `y` links für a, `y1` rechts für b, jede Achse in der Farbe ihrer Reihe.
- Farben: zwei kontrastierende Farben aus der Palette des Plugins (`NAWS_Colors`/Erscheinungsbild), nicht rot/grün als Paar.
- Tooltip: Datum (lokalisiert) + beide Werte mit Einheit, Dezimalkomma nach Sprache (wie die übrigen Diagramme).
- Regen als Balken, alles andere als Linie ohne Punkte (`pointRadius: 0`, Hover-Punkt sichtbar).
- Werte in Einstellungseinheiten (°F, inHg, mph …) — umgerechnet **auf dem Server** über `NAWS_Helpers::format_value()`-Logik, damit JS keine Umrechnung kennt.
- Vergrößern-Knopf wie bei `[naws_history]` (Modal), mit Franks Knopf-Regel (eigene `:hover`/`:focus-visible` mit zwei Klassen, Palettenfarben).
- „Aufbau im Blick“ wie die übrigen Diagramme (2.1.7, Klasse/Helfer dort wiederverwenden), sofern ohne Umbau möglich — sonst sofort zeichnen (*Bauer entscheidet, im Bericht nennen*).

**Daten in die Seite:** Die Reihen (ISO-Datum → Wert, `null` bei Lücke) werden beim Rendern als JSON in ein `<script type="application/json" id="…">` geschrieben (Muster aus `feedback_wp_add_inline_script`: zuverlässiger als `wp_add_inline_script`). Kein AJAX, keine Nonce: ein festes Diagramm braucht keinen Abruf, und gecachte Seiten funktionieren. Bei ~2 × 1000 Tagen ≈ 30–40 KB; bei mehr als 3 650 Tagen (10 Jahre) je Reihe auf Wochenmittel verdichten (*meine Festlegung, Schutz vor riesigen Seiten*).

**Leerfälle:** wie `[naws_records]`: Redakteure sehen den Grund (unbekanntes Modul, unbekannte Größe, keine Daten im Zeitraum), Besucher einen leeren String bzw. das Diagramm mit der verbleibenden Reihe.

## 6. Baukasten im Backend

Auf der Shortcode-Seite (`admin/views/shortcodes.php`) ein Abschnitt `[naws_compare]` mit:

```
Reihe A:  [Modul ▾ Basis (innen)]  [Größe ▾ Luftfeuchte]
Reihe B:  [Modul ▾ Aussen       ]  [Größe ▾ Luftfeuchte]   [ ] keine zweite Reihe
Zeitraum: (•) ganze Aufzeichnung  ( ) ab [2024-04]  ( ) letzte [24] Monate
Titel:    [                      ]

[naws_compare a="indoor:humidity" b="outdoor:humidity"]      [Kopieren]
┌ Vorschau ───────────────────────────────────────────┐
│  (dasselbe Diagramm wie im Frontend)                 │
└──────────────────────────────────────────────────────┘
```

- Die Auswahllisten zeigen **nur die Module der eigenen Station** mit Anzeigenamen, die Größenliste passt sich dem Modultyp an (Tabelle §3). Größen ohne einen einzigen Wert in der Tagesübersicht erscheinen ausgegraut mit Hinweis „keine Daten“; solche mit Lücken > 50 % mit Hinweis „lückenhaft“ (betrifft heute die Basis-Innentemperatur).
- Der Shortcode-Text aktualisiert sich bei jeder Änderung. `a` und `b` stehen immer drin (auch wenn sie der Vorgabe entsprechen), damit der Shortcode für sich lesbar bleibt; leere bzw. vorgegebene `from`/`to`/`months`/`title`/`height` fehlen. „keine zweite Reihe“ erzeugt `b=""`.
- Vorschau: nach kurzer Pause (≈ 400 ms) wird die Vorschau per Admin-AJAX (`wp_ajax_naws_compare_preview`, `manage_options`, Admin-Nonce) mit **derselben Renderfunktion** wie der Shortcode neu gezeichnet.
- Kopieren-Knopf wie die übrigen (`naws-copy-btn`).
- Auswahlliste und Vorschau brauchen die Modulliste mit Größen und Datenabdeckung: einmal beim Seitenaufbau als JSON eingebettet (`NAWS_Compare::catalogue_for_admin()`).

## 7. Aufteilung im Code (Vorschlag)

- `NAWS_Compare` (rein, wo möglich): `series_catalogue(): array` (Tabelle §3 als Daten), `parse_series( string $spec ): ?array`, `period( array $atts, string $first_day, string $yesterday ): array`, `build( array $rows_a, array $rows_b, … ): array` (Datumsachse vereinigt, `null` für Lücken, Verdichtung > 3 650 Tage), `axes( string $unit_a, string $unit_b ): array`.
- dünne WP-Schicht: `rows_for( array $series, array $period ): array` über `get_daily_summaries()`, `render( array $atts ): string`, `catalogue_for_admin(): array`.
- `templates/compare.php` (Wrapper, Legende, Canvas, JSON-Daten), `assets/js/compare-boot.js` (liest JSON, baut Chart.js-Konfiguration; dieselbe Datei zeichnet die Admin-Vorschau).

## 8. Übersetzung, Doku, Tests

- Alle Texte `naws_label()` mit Präfix `cmp_`, translators-Kommentare; de/nb über `docs/i18n/catalog/` (README dort), `tests/test-mo-files.php` grün.
- Shortcode-Hilfe (Baukasten ist die Hilfe), `readme.txt` Shortcode-Liste, `CHANGELOG.md` `[Unreleased]`.
- Tests (handgebaute Tageszeilen, wie bei Rekorden/Monatsrückblick): Katalog vollständig/konsistent; `parse_series` (gültig, unbekanntes Modul, unbekannte Größe, Größe passt nicht zum Typ, Groß/Klein); Zeitraum (`from`, `to`, `months`, vertauscht, leer); `build` (Vereinigung der Daten, Lücke = null, Verdichtung ab 3 651 Tagen); `axes` (gleiche Einheit → eine Achse, verschiedene → zwei); Render (zwei Reihen, eine Reihe, beide leer → Besucher '' / Redakteur Hinweis, keine MAC im HTML, Einheiten °F/in umgerechnet); Admin-Vorschau-Abruf (Rechte, Nonce, gleiche Ausgabe wie Shortcode).

## 9. Ablauf

Zweig `modulvergleich` (von `main` 59ef81f). Auf dev einspielen, Hilfsseite mit Matthews Fall + Temperatur Gast/Sleeping + Außentemperatur/Regen; Baukasten im Backend durchklicken; Playwright Desktop/Handy, hovern. Nichts pushen/mergen/schneiden ohne Franks Ansage.

## 10. Ansätze (Auswahl durch den Betreiber)

| Ansatz | Kurz | Ergebnis |
|---|---|---|
| A — nur Shortcode-Angaben + Liste der Kürzel auf der Hilfeseite | wie alle Shortcodes | verworfen zugunsten B (Frank: Komfort) — bleibt aber der Kern von B |
| **B — Shortcode + Baukasten im Backend** | Auswahllisten erzeugen den Shortcode, Vorschau | **gewählt** (Frank, 13.11.) |
| C — Vergleichspaare in den Einstellungen, Shortcode nennt nur den Namen | zentrale Pflege | verworfen: Diagramm hängt an einem anderen Ort als die Seite |

Auswahl durch Besucher (Auswahllisten im Frontend) wurde von Frank ausgeschlossen.

## 11. Festlegungen nach Prüfung (meine Entscheidungen)

1. **Deaktivierte Module** (Modul-Schalter im Modul-Tab) zählen wie im ganzen Plugin als nicht vorhanden: Im Baukasten erscheinen sie nicht, im Shortcode gibt es für Redakteure den Hinweis „Modul ist deaktiviert“, Besucher sehen die andere Reihe bzw. nichts. `get_daily_summaries()` filtert sie ohnehin (`active_module_ids()`); kein neuer Abfrageweg.
2. **Immer `group_by = day`.** Die Verdichtung auf Wochenmittel ab 3 651 Tagen macht `NAWS_Compare::build()` in PHP. Neu in `get_daily_summaries()` sind nur die zwei Felder in `$allowed_fields` (der Wochen-/Monatszweig `$agg_map` bleibt unberührt).
3. **Zwischenspeicher:** Das Frontend nutzt den vorhandenen Transient-Cache von `get_daily_summaries()` (Schlüssel wechselt täglich mit „gestern“, das ist gewollt). Die Admin-Vorschau ruft mit `no_cache => true` ab, damit jede Bearbeitung keinen Eintrag hinterlässt.
4. **Leerer Zeitraum:** Ohne `from`/`to` wird die Achse auf den ersten bzw. letzten Tag zugeschnitten, an dem **mindestens eine** der beiden Reihen einen Wert hat. Lücken innerhalb bleiben sichtbar.
5. **Regen-Balken:** Achse einer Balkenreihe beginnt bei 0.
6. **Admin-Vorschau ohne „Aufbau im Blick“**: sie zeichnet sofort. `compare-boot.js` darf im Frontend die Helfer aus `frontend.js` (Einblenden im Blick) nutzen, muss aber ohne sie laufen (Admin).
7. **Namen in der Legende:** dieselbe Regel wie in den Kacheln — Anzeigename, wenn gesetzt, sonst die festen, übersetzten Typnamen (`NAWS_Helpers::type_display_name()`, z. B. „Außen“, „Basis“), bei Innenmodulen der Netatmo-Name.
8. **Datenabdeckung im Baukasten** („keine Daten“ / „lückenhaft“ > 50 %) kommt aus einer neuen, einmaligen Zählabfrage je Spalte und Modul (`COUNT(col)` gruppiert nach `module_id`), nur im Backend.

## 12. Dem Bauer überlassen

- genaue Farben und CSS innerhalb §5
- ob „Aufbau im Blick“ ohne Umbau übernehmbar ist
- interne Struktur der Rückgaben
- Debounce-Zeit der Vorschau
