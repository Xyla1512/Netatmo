# Monatsrückblick: `[naws_month_review]`

**Datum:** 2026-11-13
**Betrifft:** neu `includes/class-naws-month-review.php`, `templates/month-review.php`, `assets/js/month-review.js`, Tests; geändert `class-naws-shortcodes.php`, `class-naws-ajax.php`, `class-naws-labels.php`, `frontend.css`, `admin/views/shortcodes.php`, `readme.txt`, `CHANGELOG.md`, Kataloge (`.pot`, de/nb `.po`/`.mo`)
**Auslöser:** Frank, 13.11.2026: „Monatsrückblick … wenn es die Messwerte hergeben ein Vergleich des Monats mit den selben Monaten der Vorjahre“; „für die Besucher der Webseite … mit einem Shortcode einbauen … mehrsprachig (übersetzbar)“. Steht seit 14.10. auf der Produktseite unter „In Arbeit“ (`docs/site/website.*.json`, id `monatsrueckblick`).
**Ziel-Release:** offen (Schnitt auf Franks Ansage)

---

## 1. Ziel und Festlegungen

Ein Besucher der Wetterseite sieht auf einen Blick, wie der letzte Monat war, und wie er im Vergleich zu demselben Monat der Vorjahre dasteht. Plugin-Nutzer binden das per Shortcode ein.

| Frage | Entscheidung (Brainstorming 13.11.) |
|---|---|
| Publikum | Besucher der Website, nicht der Betreiber |
| Einbindung | eigener Shortcode `[naws_month_review]` |
| Sprache | alle Texte übersetzbar (gettext → GlotPress de/nb) |
| Form | **Variante B:** Kopfsatz + Kennzahlen-Kacheln mit Vorjahreswerten + 2–3 Sätze darunter |
| Monat | standardmäßig der **zuletzt abgeschlossene** Monat; `month="2026-10"` legt fest |
| Blättern | **ohne Neuladen** (‹ Vormonat · Monat · Folgemonat ›) |
| Bauweise | **Ansatz A:** Server rendert den Block in PHP; beim Blättern holt ein Skript denselben Block als HTML |
| Kacheln | Mittel · Höchstwert (mit Datum) · Tiefstwert (mit Datum) · Regen (+ Regentage) · Stärkste Böe (mit Datum) |
| Sätze | Frosttage, Sommertage, nassester Tag, Rekordhinweise (★) |
| Nicht dabei | Luftdruck, Feuchte, Innenwerte (erzählen Besuchern zu wenig) |

Datenlage auf dev (geprüft 13.11.): Tageszeilen der Basisstation lückenlos ab 2024-03-28; Außentemperatur, Regen und Böen liegen in `wp_naws_daily_summary` unter der Modul-ID der **Basisstation** (so wie `NAWS_Records::rows()` sie über `NAWS_Calc::station_row_id()` liest). Für fast jeden Monat gibt es heute zwei Vorjahre.

## 2. Ziele, Nicht-Ziele, Anti-Ziele

**Ziele**
- Ein abgeschlossener Monat in wenigen Sekunden erfassbar: ein Satz, fünf Zahlen, je mit Vorjahren.
- Ehrliche Sprache: „seit Messbeginn“, nie „überdurchschnittlich“ oder „normal“ (2–3 Jahre sind kein Klimamittel).
- Jeder Monat hat eine teilbare Adresse; ohne JavaScript bleibt der Block voll lesbar und blätterbar.

**Nicht-Ziele (diese Version)**
- laufender Monat („bisher im November“)
- Vergleich mit Klimanormalen / DWD-Mittelwerten
- Diagramme im Rückblick, Luftdruck/Feuchte/Innenwerte
- Gutenberg-Block, Elementor-Widget, E-Mail-Versand
- Einbindung in `[naws_live]` oder das Widget

**Anti-Ziele (wäre ein Fehlschlag, auch wenn es technisch läuft)**
- Ein Geräteausfall erscheint als Rekord („trockenster Oktober“, weil 10 Tage fehlen).
- Sätze aus zusammengeklebten Wortstücken, die sich nicht übersetzen lassen.
- Besucher sehen Fehlermeldungen oder eine kaputte Seite (Fehler nur für Redakteure, wie bei `[naws_records]`).
- Wertende Farben (rot = wärmer = schlecht): Pfeile und Markierungen bleiben neutral.

## 3. Shortcode

```
[naws_month_review month="" years="5" title=""]
```

| Angabe | Bedeutung | Vorgabe | Grenzen |
|---|---|---|---|
| `month` | fester Monat `JJJJ-MM` | leer = zuletzt abgeschlossener Monat | ungültig oder außerhalb der Daten → zuletzt abgeschlossener Monat |
| `years` | höchstens so viele Vorjahre je Kachel | `5` | 0–10 (geklemmt); `0` = keine Vorjahreszeilen und keine Vergleichssätze |
| `title` | eigene Überschrift über der Leiste | keine (die Leiste nennt den Monat) | Text, escaped |

Weitere Angaben wie `station` werden nur angenommen, wenn `NAWS_Calc::station_row_id()` sie schon für `[naws_records]` auswertet (gleiche Bedeutung, kein neues Konzept).

**Adressparameter:** `?naws_month=JJJJ-MM` (englisch wie die Shortcode-Namen, weil das Plugin weltweit läuft — *meine Festlegung; im Brainstorming hatte ich `naws_monat` geschrieben*). Er hat Vorrang vor dem Vorgabewert, aber **nicht** vor einem fest gesetzten `month=""` — wer einen Monat festnagelt, will ihn behalten; dann gibt es auch keine Blätterleiste (nur der Monatsname als Überschrift). Mehrere Rückblicke auf einer Seite folgen alle demselben Parameter.

Registrierung wie alle anderen über `NAWS_Shortcodes::TAGS` (`'naws_month_review' => 'sc_month_review'`), Platz in der Liste nach `naws_on_this_day`. Die Zählung „achtzehn“ im Kommentar des Konstruktors wird „neunzehn“.

## 4. Rechnung (`NAWS_Month_Review`, rein)

Wie `NAWS_Records`: eine `final class` mit reinen statischen Funktionen (Tageszeilen rein, Zahlen raus) plus genau einer Funktion `rows()`, die WordPress anfasst.

**`rows( array $atts ): array`** — alle Tageszeilen der Station über die ganze Aufzeichnung, Felder `temp_min, temp_max, temp_avg, rain_sum, gust_max`, `group_by => day` (identisch zu `NAWS_Records::rows()` mit `period=all`; gern direkt wiederverwenden). Bei heute ~1000 Zeilen ist das billig; **kein Zwischenspeicher** in dieser Version (die Rekorde rechnen ebenfalls live).

**`months( array $rows ): array`** — Liste aller Monate `JJJJ-MM` mit mindestens einer Zeile, aufsteigend. Daraus: erster Monat = erster Monat mit Daten, letzter blätterbarer Monat = Vormonat von „heute“ in der Zeitzone der Site (`naws_timezone()`).

**`summary( array $rows, string $ym ): array`** — Kennzahlen eines Monats:

| Kennzahl | Rechnung | Datum |
|---|---|---|
| `mean` | Mittel von `temp_avg` über die Tage mit Wert | — |
| `max` | größtes `temp_max` | Tag (bei Gleichstand der erste) |
| `min` | kleinstes `temp_min` | Tag (erster) |
| `rain` | Summe `rain_sum` | — |
| `rain_days` | Tage mit `rain_sum >= NAWS_Records::RAIN_DAY_MM` (0,1 mm) | — |
| `wettest_day` | größtes `rain_sum` | Tag |
| `gust` | größtes `gust_max` | Tag |
| `frost_days` | Tage mit `temp_min < 0` | — |
| `summer_days` | Tage mit `temp_max >= 25` | — |
| `days` | Tage mit Wert, **je Feld** gezählt | — |
| `length` | Tage des Kalendermonats | — |

Schwellen für Frost-/Sommertage aus `NAWS_Calc` übernehmen (Katalogeinträge `frost_days`, `summer_days`), nicht neu hart kodieren. Hinweis: im Brainstorming-Beispiel standen Regentage ab 0,2 mm — gerechnet wird mit 0,1 mm wie bei den Rekorden.

**Vollständigkeit:** Ein Monat zählt für Vergleiche und Rekorde nur, wenn das jeweilige Feld an mindestens `NAWS_Records::MONTH_MIN_DAYS` (20) Tagen einen Wert hat — dieselbe Regel wie beim Rekord „wärmster Monat“. Ist der **angezeigte** Monat darunter, erscheint er trotzdem, mit dem Vermerk „unvollständig (X von Y Tagen)“, aber ohne ★ und ohne Rekord-/Vergleichssätze für dieses Feld. Fehlen Tage, liegen aber über 20, steht der Vermerk ebenfalls (Zahl der Tage), Vergleiche bleiben erlaubt.

**`compare( array $rows, string $ym, int $years ): array`** — derselbe Kalendermonat in den Vorjahren (neueste zuerst, höchstens `$years`, nur vollständige je Feld) plus:
- **★ Rekord** je Kachel, wenn der Wert extremer ist als in **allen** vollständigen Vorjahresmonaten desselben Namens und es mindestens **ein** solches Vorjahr gibt. Richtung je Kachel: Mittel (wärmster/kältester), Höchstwert (höchster), Tiefstwert (tiefster), Regen (nassester/trockenster), Böe (stärkste). Gleichstand ist kein Rekord.
- **▲/▼ Tendenz** gegenüber dem **Mittel** der gezeigten Vorjahre, nur wenn kein ★ und die Abweichung die Schwelle erreicht: Temperatur 0,5 K, Regen 10 % *und* mindestens 2 mm, Böe 5 km/h (in Basiseinheit; Umrechnung nur für die Anzeige). Darunter: kein Pfeil. *Schwellen sind meine Festlegung — reine Konstanten, später leicht änderbar.*

Alle Funktionen arbeiten in den gespeicherten Basiseinheiten (°C, mm, km/h); umgerechnet wird erst beim Ausgeben.

## 5. Sätze

Ganze Sätze mit Platzhaltern, jeder ein eigener `naws_label()`-Schlüssel mit `/* translators: */`-Kommentar, der jeden Platzhalter erklärt. Monatsnamen kommen aus `wp_date()`/`date_i18n()` (lokalisiert), Daten mit übersetzbarem Format (Schlüssel `mr_date_format`, de `j. F`, en `F j`). Keine Satzteile aneinanderkleben.

**Kopfsatz** (genau einer, erste zutreffende Regel gewinnt):
1. Monats-Rekord Regen: „Der nasseste %1$s seit Messbeginn“ / „Der trockenste …“
2. Monats-Rekord Mittel: „Der wärmste %1$s seit Messbeginn“ / „Der kälteste …“
3. Tages-Rekord Höchst-/Tiefstwert/Böe des Monatsnamens: „Mit dem wärmsten %1$s-Tag seit Messbeginn“ o. ä.
4. sonst neutral: „%1$s im Rückblick“
(`%1$s` = Monatsname ohne Jahr bei 1–3, mit Jahr bei 4.) Ohne Vorjahr (erster Messmonat dieses Namens) gilt immer Regel 4.

**Sätze darunter** (höchstens drei, in dieser Reihenfolge, nur zutreffende):
1. Frost-/Sommertage, wenn > 0: „%1$d Frosttage (2025: %2$d)“ — Vorjahresangabe nur bei vollständigem Vorjahr.
2. Nassester Tag, wenn ≥ 1 mm: „Der meiste Regen fiel am %1$s: %2$s“.
3. Stationsrekord über **alle** Monate, wenn der Monatswert ihn bricht (Abgleich mit `NAWS_Records::compute()`): „%1$s war zugleich die stärkste Böe seit Messbeginn überhaupt.“

Singular/Plural über `_n()` (Frosttage, Tage). Die genaue Satzliste darf der Bauer verfeinern, solange die Reihenfolge-Logik und die Regeln aus §2 (ehrlich, keine Wertung) halten; alle Endfassungen der englischen Quelltexte gehen vor dem Schnitt einmal an Frank.

## 6. Darstellung (`templates/month-review.php`)

```
[title, falls gesetzt]
‹ September 2026        Oktober 2026        November 2026 ›     ← <nav>, Links mit ?naws_month=
Der nasseste Oktober seit Messbeginn                             ← <h3> Kopfsatz
┌ Mittel ──────┐ ┌ Höchstwert ──┐ ┌ Tiefstwert ──┐ ┌ Regen ───────┐ ┌ Böe ─────────┐
│ 12,2 °C      │ │ 24,0 °C ★    │ │ 1,9 °C       │ │ 39,6 mm ★    │ │ 26 km/h ▼    │
│              │ │ am 3. Okt.   │ │ am 28. Okt.  │ │ 16 Regentage │ │ am 9. Okt.   │
│ 2025: 11,3   │ │ 2025: 17,6   │ │ 2025: 3,1    │ │ 2025: 37,3   │ │ 2025: 34     │
│ 2024: 12,9   │ │ 2024: 20,9   │ │ 2024: 5,9    │ │ 2024: 29,3   │ │ 2024: 40     │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
2 Frosttage (2025: 4). Der meiste Regen fiel am 17. Oktober: 9,8 mm.
```
(Zahlen illustrativ.)

- Ein Wurzelelement `<section class="naws-mr" data-…>` mit `aria-live="polite"` auf dem austauschbaren Teil; die `data-`Attribute tragen, was der Abruf zum Neurendern braucht (`years`, `title`, ggf. `station`).
- Blätterleiste: `‹`-Link fehlt vor dem ersten Monat, `›`-Link fehlt hinter dem letzten abgeschlossenen Monat (Platz bleibt, damit nichts springt). Die Links haben ausgeschriebene `aria-label`s („Vormonat: September 2026“).
- Kacheln im Stil der Sparkline-Kacheln (`frontend.css`, Farben aus `NAWS_Colors`/Erscheinungsbild-Einstellungen). Raster: 5 nebeneinander auf breiten Flächen, umbrechend bis 2 bzw. 1 Spalte auf dem Handy.
- ★ und ▲/▼ haben Textalternativen (`<span class="screen-reader-text">Rekord seit Messbeginn</span>` bzw. „mehr als im Mittel der Vorjahre“) und eine neutrale Farbe.
- Fehlt ein Feld ganz (keine Werte im Monat, z. B. kein Windmesser), entfällt die Kachel. Fehlen alle, greift der Leerfall unten.
- Werte formatiert über `NAWS_Helpers::format_value()`/`get_unit()` (Dezimalkomma nach Sprache, Einheiten nach Einstellungen).
- **Franks Knopf-Regel (10.09.):** Blätterlinks bekommen eigene `:hover`/`:focus-visible`-Regeln mit zwei Klassen im Selektor und Farben aus der Palette des Bausteins (Hello Elementor färbt sonst rot). Bei der Abnahme auf dev hovern.

**Leerfälle:** wie `[naws_records]` über `NAWS_Records::empty_reason()`/`notice()` (bzw. eine gleichgebaute Variante mit Shortcode-Name `naws_month_review`): Redakteure sehen den Grund, Besucher einen leeren String. Kein abgeschlossener Monat vorhanden (Station läuft erst seit diesem Monat) = eigener Grund „noch kein abgeschlossener Monat“.

## 7. Blättern ohne Neuladen

**Abruf:** `wp_ajax_naws_get_month_review` + `wp_ajax_nopriv_naws_get_month_review` in `NAWS_Ajax`, nach dem Muster von `get_heatmap_data()`:
- `check_ajax_referer( 'naws_public_nonce', 'nonce' )`, `nocache_headers()`
- Eingaben: `month` (Regex `^\d{4}-(0[1-9]|1[0-2])$`), `years` (int, geklemmt), `title` (sanitize_text_field), ggf. `station` — **nur** die Angaben des Shortcodes, nichts Freies
- Monat außerhalb der blätterbaren Spanne → `wp_send_json_error( …, 400 )`
- Antwort `wp_send_json_success( [ 'html' => …, 'month' => 'JJJJ-MM' ] )`, das HTML aus **derselben** Vorlage wie der Erstaufruf

**Skript `assets/js/month-review.js`** (ohne Abhängigkeiten, nur geladen, wenn der Shortcode auf der Seite steht; Nonce und `ajax_url` über dasselbe Lokalisierungsobjekt wie die übrigen Frontend-Skripte, siehe `class-naws-shortcodes.php` Z. ~131):
- fängt Klicks auf die Blätterlinks im eigenen Block ab, setzt `aria-busy`, holt den Monat, ersetzt den Inhalt, setzt den Fokus auf den neuen Kopfsatz
- passt die Adresse mit `history.replaceState` an (`?naws_month=…`, übrige Parameter bleiben) — Zurück-Taste verlässt die Seite wie gewohnt; *meine Festlegung*
- **bei jedem Fehler** (Netz, 400, abgelaufene Nonce auf einer gecachten Seite) folgt es einfach dem Link → normaler Seitenaufruf; kein Fehlertext für Besucher
- Doppelklicks während eines Abrufs werden ignoriert

Ohne JavaScript sind die Links normale Links (Seite lädt mit `?naws_month=` neu).

## 8. Übersetzung und Doku

- Alle Texte als `naws_label()`-Schlüssel mit Präfix `mr_` in `class-naws-labels.php`.
- `.pot` neu bauen, de/nb `.po` + `.mo` ergänzen (Werkzeuge unter `docs/i18n/`), `tests/test-mo-files.php` muss grün bleiben. Beim Schnitt GlotPress-Importdateien wie üblich.
- Shortcode-Hilfe in `admin/views/shortcodes.php` (Angaben, Beispiel), `readme.txt` (Shortcode-Liste + Changelog), `CHANGELOG.md` unter `[Unreleased]`.
- Produktseite (erst beim Schnitt, nicht in diesem Zweig): Eintrag `monatsrueckblick` bekommt `ab`, Startseiten 110/175 „Alle 18 Shortcodes“ → 19, Schema im Site-Plugin.

## 9. Tests

Wie bei Rekorden/Sparkline: handgebaute Tageszeilen, keine Datenbank.
- `summary()`: Kennzahlen, Daten bei Gleichstand, Frost-/Sommer-/Regentage an den Schwellen (0,0 / 25,0 / 0,1)
- Vollständigkeit: Monat mit 19 vs. 20 Tagen; unvollständiges Vorjahr wird übersprungen; angezeigter unvollständiger Monat ohne ★
- `compare()`: ★ nur mit ≥ 1 vollständigem Vorjahr, Gleichstand kein Rekord, Pfeil-Schwellen knapp darunter/darüber, `years`-Begrenzung, Reihenfolge neueste zuerst
- Kopfsatz-Regeln 1–4 in Reihenfolge; erster Monat eines Namens → neutral
- Blätterspanne: erster Monat ohne ‹, Vormonat von heute ohne ›; laufender Monat nicht erreichbar
- Vorlage rendert mit fehlender Böen-Spalte ohne Böen-Kachel; Leerfall Besucher = leerer String
- Abruf: ungültiger Monat / außerhalb → 400; gültig → HTML identisch zum Erstaufruf desselben Monats
- Suite, `php -l`, phpcs wie immer grün.

## 10. Ablauf

Zweig `monatsrueckblick` (von `main` f2ba8d0). Auf dev einspielen und mit Franks Daten abnehmen (Monate Oktober 2026, März 2024 = unvollständig, ein Monat ohne Vorjahr), Handy-Ansicht per Playwright, hovern. Nichts pushen/mergen/schneiden ohne Franks Ansage.

## 11. Ansätze (Bauweise)

| Ansatz | Kurz | Ergebnis |
|---|---|---|
| **A — Server rendert, Skript tauscht aus** | Shortcode rendert den Block in PHP; Blättern holt denselben Block als HTML per `admin-ajax` | **gewählt** (Frank, 13.11.): Übersetzung bleibt in PHP/gettext, eine einzige Darstellung, läuft ohne JS, abgeschlossene Monate später cachebar |
| B — Server liefert JSON, Skript baut | Zahlen per JSON, Kacheln und Sätze im JS | verworfen: Satzbausteine müssten in JS übersetzt werden (eigene JS-Sprachdateien, die das Plugin nicht hat), Darstellung doppelt |
| C — alle Monate ausliefern, ein-/ausblenden | wie die Reiter der Rekorde | verworfen: Seite wächst mit jedem Monat (heute > 30 Blöcke) |

## 12. Dem Bauer überlassen

- genaue CSS-Gestaltung innerhalb der Vorgaben aus §6
- Feinschliff der englischen Quelltexte der Sätze (Endfassung an Frank)
- ob `rows()` `NAWS_Records::rows()` direkt aufruft oder teilt
- interne Struktur der Rückgabe-Arrays
