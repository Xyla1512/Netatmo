# Eine Heatmap des Außen-Tagesdurchschnitts

**Datum:** 2026-09-02
**Betrifft:** `includes/class-naws-shortcodes.php`, `includes/class-naws-colors.php`, `includes/class-naws-database.php`, `includes/class-naws-ajax.php`, `templates/heatmap.php` (neu), `assets/js/heatmap-boot.js` (neu), `assets/css/frontend.css`, `admin/views/appearance.php`, `admin/views/shortcodes.php`
**Auslöser:** Ein Jahr Temperatur als Kurve zu lesen ist mühsam. Als Kalenderraster sieht man den kalten Februar und die Hitzewoche im Juli auf einen Blick.

---

## 1. Ausgangslage

Das Plugin zeigt Temperaturverläufe bisher ausschließlich als Kurve: `[naws_history]`
zeichnet fünf Jahrescharts, darunter „Jahresdurchschnittstemperatur" (`temp_avg`), und
`[naws_live]` die letzten 24 Stunden. Eine Fläche, auf der ein ganzes Jahr Tag für Tag
nebeneinanderliegt, gibt es nicht.

Die Daten dafür liegen bereits vor. `wp_naws_daily_summary` (`NAWS_TABLE_DAILY`) führt je
Tag und Modul eine Zeile; die Spalte `temp_avg` ist der Tagesdurchschnitt.

### 1.1 Wo der Außenwert wirklich steht

Der Außen-Tagesdurchschnitt steht **nicht** unter der MAC des Außenmoduls, sondern auf der
Zeile der Basisstation — `module_id = station_id`. Die Innenmodule führen ihre Werte in den
`indoor_*`-Spalten; `temp_*` auf der Stationszeile sind die Außenwerte.

Auf der Testinstallation, Stand 2026-09-02:

| `module_id` | Zeilen | davon mit `temp_avg` | Zeitraum |
| --- | --- | --- | --- |
| `70:ee:50:…` (NAMain, = `station_id`) | 889 | **861** | 2024-03-28 – 2026-09-02 |
| `03:00:00:…` (NAModule4 „Gast") | 889 | 0 | — |
| `03:00:00:…` (NAModule4 „Schlafen") | 889 | 0 | — |

Der bestehende Chart merkt davon nichts, weil `get_history_data()` ohne Modulfilter
abfragt. Mit Filter läuft man hinein:

```
fields=[temp_avg]                      → 2024: 251,  2025: 365,  2026: 245 Punkte
fields=[temp_avg] module_ref=outdoor   → nichts
```

Das ist keine Fehlfunktion, sondern die Form der Tabelle — hier festgehalten, weil es beim
Testen von 1.9.10 einmal Zeit gekostet hat und es beim nächsten Mal wieder tun würde.

**Folge für diese Arbeit:** Die Heatmap fragt dieselbe Tabelle, dieselbe Spalte und
denselben Filter ab wie der Chart „Jahresdurchschnittstemperatur" — nämlich keinen. Sie ist
eine zweite Ansicht erprobter Zahlen, keine neue Datenquelle.

---

## 2. Was die Karte zeigt

Ein Jahr als Raster: zwölf Zeilen (Monate), 31 Spalten (Tage), eine Kachel je Tag,
eingefärbt nach dem Tagesdurchschnitt. Über der Karte eine Reihe Jahresknöpfe, darunter
eine Legende mit dem Farbverlauf. Beim Überfahren einer Kachel nennt ein Tooltip Datum und
Wert.

Voreingestellt ist das laufende Jahr.

---

## 3. Entscheidungen

### 3.1 Jahre werden einzeln per AJAX geholt

Nur das Startjahr steht in der Seite; jedes weitere Jahr holt ein Request. Ein Jahr sind
366 Fließkommazahlen, also gut 2 KB — alle Jahre gleich mitzuliefern wäre technisch
problemlos gewesen und hätte den Endpunkt gespart. Die Entscheidung fiel trotzdem für
AJAX, weil `[naws_history]` es so macht und zwei Muster für dieselbe Sache im selben
Plugin teurer sind als ein Endpunkt.

Geholte Jahre bleiben im Speicher der Seite. Ein zweiter Klick auf dasselbe Jahr löst
keinen Request aus.

### 3.2 Gezeichnet wird als HTML-Tabelle, nicht als Grafik

Eine `<table>` — Zeilen Monate, Spalten Tage — ist genau das Element für eine Matrix aus
zwei Achsen. Daraus folgt dreierlei umsonst, was sonst gebaut werden müsste:

- Die Monatsspalte bleibt beim waagerechten Scrollen per `position: sticky` stehen. Kein
  JavaScript.
- Ein Screenreader liest „März, 14., 8,2 °C" statt „Grafik".
- Die Kacheln sind DOM-Elemente und damit für CSS-Animationen unmittelbar zugänglich.

Verworfen wurden **Inline-SVG** (klebende Spalte und Scrollen müssten von Hand nachgebaut
werden, Barrierefreiheit nur über zusätzliche ARIA-Auszeichnung) und **Chart.js mit
Matrix-Plugin** (verlangt eine weitere Datei unter `assets/vendor/`; für farbige Rechtecke
in einem Raster die schwerste Lösung, und jede mitgelieferte Bibliothek ist Prüffläche im
wp.org-Review).

### 3.3 Die Farbrechnung liegt in PHP

`NAWS_Colors::heatmap_color( float $celsius ): string` ist die einzige Stelle, an der aus
einer Temperatur eine Farbe wird. Template und AJAX-Endpunkt benutzen dieselbe Methode; das
JavaScript bekommt fertige Farben und setzt sie nur.

Der naheliegende Gegenentwurf — Rechnung in JavaScript, PHP liefert nur Zahlen und die zehn
Stützfarben — wurde verworfen: er hätte dieselbe Interpolation in zwei Sprachen bedeutet,
und die JavaScript-Hälfte wäre mit den Mitteln dieses Projekts (PHP-Testdateien ohne
Runner) nicht prüfbar gewesen.

Weil die Farben aus PHP kommen, ist die Karte für das Startjahr auch ohne JavaScript
vollständig eingefärbt. Die Animation ist Zugabe, keine Voraussetzung.

### 3.4 Gefärbt nach Celsius, angezeigt in der eingestellten Einheit

Die Farbe kommt immer aus dem **gespeicherten** Wert. Läge die Skala auf dem angezeigten
Wert, kippte sie bei `temperature_unit = F` vollständig — 35 °C sind 95 °F und lägen weit
jenseits des oberen Endes.

Der Tooltip zeigt den Wert durch `NAWS_Helpers::format_value( 'Temperature', $v )` und die
Einheit durch `NAWS_Helpers::get_unit( 'Temperature' )`, also in °F, wo das eingestellt ist.
Auch die Legende beschriftet ihre Marken in der eingestellten Einheit, während die Farben
an den Celsius-Stützpunkten hängen.

### 3.5 Kein `module`-Attribut

Die Karte zeigt den Außenwert und sonst nichts. Innenmodule führen ihre Werte in
`indoor_temp_avg` — eine andere Spalte, ein anderer Wertebereich und eine Farbskala, die
zwischen 18 und 24 °C spielt statt zwischen −10 und 35. Das ist eine eigene Arbeit, kein
Attribut.

### 3.6 Monatsnamen kommen aus WordPress

`wp_date( 'F', … )` liefert sie in der Sprache der Installation. Das sind Kernübersetzungen,
die in jeder Sprache vollständig vorliegen — die Karte fügt dem Katalog des Plugins dafür
keinen einzigen String hinzu. `includes/class-naws-astro.php` macht es bereits so und
begründet es dort.

---

## 4. Die Farbskala

Zehn Stützpunkte, alle als Einstellung auf der Appearance-Seite, dazu eine elfte Farbe für
Tage ohne Messwert:

| Schlüssel | Stützpunkt | Default | |
| --- | --- | --- | --- |
| `heatmap_t_m10` | ≤ −10 °C | `#6b21a8` | Lila |
| `heatmap_t_m5` | −5 °C | `#3b5bdb` | Blau |
| `heatmap_t_0` | 0 °C | `#2f9e97` | Blaugrün |
| `heatmap_t_5` | 5 °C | `#3fa34d` | Grün |
| `heatmap_t_10` | 10 °C | `#a3c644` | Gelbgrün |
| `heatmap_t_15` | 15 °C | `#f2c744` | Gelb |
| `heatmap_t_20` | 20 °C | `#f59f3c` | Orange |
| `heatmap_t_25` | 25 °C | `#ec6a2c` | Dunkelorange |
| `heatmap_t_30` | 30 °C | `#d92b2b` | Rot |
| `heatmap_t_35` | ≥ 35 °C | `#7f1d1d` | Dunkelrot |
| `heatmap_no_data` | — | `#eef2f2` | Tag ohne Messwert |

Zwischen zwei Stützpunkten wird **linear im RGB-Raum interpoliert**, damit 12 °C und 13 °C
sich unterscheiden statt in dieselbe Stufe zu fallen. Unterhalb von −10 und oberhalb von 35
wird gekappt.

Die Gruppe heißt `appearance_group_heatmap` und tritt neben die fünf vorhandenen Gruppen in
`NAWS_Colors`. Anders als die übrigen Farben werden diese elf **nicht** als CSS-Variablen
ausgegeben: sie werden je Kachel gebraucht, in interpolierten Zwischenwerten, die keine
Variable vorhalten kann. `get_inline_css()` bleibt unangetastet.

Auch die Legende zieht ihren Verlauf aus denselben elf Werten — als `linear-gradient` im
`style`-Attribut ihres Balkens, in PHP zusammengesetzt. Sie ist damit automatisch das Bild
der tatsächlich eingestellten Skala und nicht eine zweite, die auseinanderlaufen kann.

---

## 5. Architektur

```
templates/heatmap.php                         assets/js/heatmap-boot.js
  ├─ Jahre aus get_daily_data_range()           ├─ Klick auf Jahresknopf
  ├─ NAWS_Database::get_heatmap_year( $y )      ├─ POST naws_get_heatmap_data
  ├─ NAWS_Colors::heatmap_color( $v )   ────┐   ├─ Kacheln umfärben (250 ms)
  └─ <table> mit fertigen Farben             │   └─ Tooltip
                                             │
includes/class-naws-ajax.php                 │
  get_heatmap_data()                         │
    ├─ NAWS_Database::get_heatmap_year( $y )  │
    └─ NAWS_Colors::heatmap_color( $v )  ─────┘   dieselbe Rechnung
```

`NAWS_Database::get_heatmap_year( int $year ): array` liefert zwölf Arrays mit je bis zu 31
Werten, `null` wo kein Messwert vorliegt. **Beide Achsen sind nullbasiert:** Index 0 ist der
Januar und der Erste des Monats; ein Monat mit 30 Tagen hat 30 Einträge, nicht 31 mit einem
`null` am Ende. Die Unterscheidung zwischen „Tag ohne Messwert" (`null` im Array) und „Tag
gibt es nicht" (kein Eintrag) ist damit die Länge des Arrays, und die Tests prüfen genau
das.

Eine Abfrage über die Tagestabelle, gefiltert auf `YEAR(day_date)`, ohne Modulfilter,
aufsteigend nach `day_date`.

Die SQL gehört in `NAWS_Database` und nicht in die AJAX-Klasse, obwohl `get_history_data()`
es dort tut: nur so ist sie ohne AJAX-Umgebung prüfbar.

---

## 6. Markup

```html
<div class="naws-hm" data-nonce="…" data-ajax="…" data-year="2026">
  <div class="naws-hm-hdr">
    <div class="naws-hm-title">…</div>
    <div class="naws-hm-years">
      <button class="naws-hm-year is-active" data-year="2026">2026</button>
      <button class="naws-hm-year" data-year="2025">2025</button>
    </div>
  </div>

  <div class="naws-hm-scroll">
    <table class="naws-hm-grid">
      <thead><tr><th></th><th>1</th>…<th>31</th></tr></thead>
      <tbody>
        <tr>
          <th scope="row">Januar</th>
          <td class="naws-hm-c" style="background:#3b5bdb"
              data-d="2026-01-01" data-v="-3.4" data-l="−3,4 °C">
            <span class="screen-reader-text">−3,4 °C</span>
          </td>
          …
          <td class="naws-hm-x" aria-hidden="true"></td>   <!-- 31. Februar -->
        </tr>
      </tbody>
    </table>
  </div>

  <div class="naws-hm-legend">…</div>
</div>
```

Die Kachelfarbe steht als `style`-Attribut am Element — nicht als `<style>`-Block. Ein
Block wäre die Sorte Inline-Ausgabe, die das Plugin seit 1.6.2 auf Verlangen des
Review-Teams entfernt hat; ein Attribut ist etwas anderes und bleibt zulässig. Anders geht
es auch nicht: 366 interpolierte Farben sind keine Klassenliste.

`wp_unique_id()` erzeugt die Kennung des Blocks, damit mehrere Karten auf einer Seite sich
nicht in die Quere kommen — dasselbe Verfahren wie in `history.php` und `live.php`.

---

## 7. Aufbau und Animation

Beim ersten Erscheinen laufen die Kacheln als **Welle von links nach rechts** ein: die
Verzögerung hängt am Tag im Monat, nicht am laufenden Index, sodass alle zwölf Monate
gleichzeitig fahren und die Front senkrecht durchs Jahr wandert. Rund 700 ms für das ganze
Raster, `opacity` und ein leichtes `scale`.

Die Verzögerung setzt `heatmap-boot.js` als CSS-Variable je Zelle. Ohne JavaScript steht
die Karte sofort und vollständig — die Animation ist an das Setzen der Variable gebunden,
nicht die Sichtbarkeit.

Bei `prefers-reduced-motion: reduce` entfällt sie ersatzlos.

**Beim Jahreswechsel wird nicht neu aufgebaut.** Dieselben Kacheln färben sich über 250 ms
um (`transition: background-color`). Blättern soll sich wie ein Überblenden anfühlen, nicht
wie ein Seitenwechsel — und ein zweites Mal dieselbe Welle zu sehen wird beim dritten Klick
lästig.

---

## 8. Der Endpunkt

```
action=naws_get_heatmap_data   nonce=naws_public_nonce   year=2025

{ "success": true,
  "data": { "year": 2025,
            "months": [ [ 1.1, 0.8, …, null ], … ],      // 12 × bis zu 31
            "colors": [ [ "#3b5bdb", …, null ], … ],
            "labels": [ [ "1,1 °C", …, null ], … ] } }   // formatiert, mit Einheit
```

Registriert als `wp_ajax_naws_get_heatmap_data` **und** `wp_ajax_nopriv_…`, damit die Karte
auch für nicht angemeldete Besucher lädt — wie die vier vorhandenen öffentlichen Endpunkte.
Nonce ist `naws_public_nonce`, `nocache_headers()` wie dort.

`labels` reist mit, weil die Formatierung an `NAWS_Helpers::format_value()` und der
Einheiteneinstellung hängt. Das im JavaScript nachzubauen hieße, die Einheitenlogik ein
zweites Mal zu schreiben.

Ein Jahr außerhalb des vorhandenen Bereichs wird mit **400** abgelehnt, nicht stillschweigend
zu einem leeren Jahr gemacht. Dieselbe Haltung wie bei der unbekannten Modulreferenz in
1.9.10: eine Anfrage, die nicht beantwortet werden kann, bekommt einen Fehler und keine
Antwort, die wie ein Ergebnis aussieht.

---

## 9. Sonderfälle

- **Tag ohne Messwert** → `heatmap_no_data`, Tooltip sagt „Keine Messung". Betrifft auf der
  Testinstallation 28 Tage in 2024.
- **Tag, den es nicht gibt** (31. Februar, 31. April) → leere Zelle ohne Rahmen und ohne
  Hintergrund, `aria-hidden`. Sie gehört sichtbar nicht zum Raster.
- **Schaltjahr** → der 29. Februar ist eine Zelle wie jede andere. Der Test prüft 2024 gegen
  2025.
- **Jahr ohne jede Zeile** → die Karte bleibt stehen, alle Kacheln tragen `heatmap_no_data`,
  darüber der Satz `No data for this period.` Das ist derselbe englische Quelltext, den
  `frontend.js` seit 1.9.10 als `js_no_data_period` benutzt — ein Katalogeintrag für beide,
  also eine Übersetzung statt zweier, die auseinanderlaufen können.
- **Gar keine Tagesdaten** → der Jahresbereich fällt auf das laufende Jahr zurück, wie in
  `history.php`; die Karte zeigt zwölf leere Monate statt einer Fehlermeldung.

---

## 10. Attribute

```
[naws_heatmap]
[naws_heatmap year="2025" title="Wie warm war 2025?" legend="no"]
```

| Attribut | Default | |
| --- | --- | --- |
| `year` | laufendes Jahr | Startjahr; außerhalb des Bereichs → laufendes Jahr |
| `title` | „Daily Average Temperature" | Überschrift |
| `legend` | `yes` | Farbverlauf unter der Karte |

Mehr nicht. Jedes weitere Attribut wäre geraten, solange niemand danach gefragt hat.

---

## 11. Tests

`tests/test-heatmap-colors.php`

- Die zehn Stützpunkte geben exakt ihre Farbe zurück.
- Die Mitte zwischen zwei Stützpunkten gibt den Mittelwert der beiden RGB-Tripel.
- −40 °C gibt dieselbe Farbe wie −10 °C, 50 °C dieselbe wie 35 °C (Kappung).
- `null` gibt `heatmap_no_data`.
- Eine geänderte Einstellung schlägt durch — sonst wäre die Appearance-Gruppe Zierde.

`tests/test-heatmap-render.php`

- Zwölf Zeilen, 31 Spalten.
- Tageszahl je Monat stimmt; 2024 hat einen 29. Februar, 2025 nicht.
- Nicht existierende Tage sind `naws-hm-x` und tragen keinen Hintergrund.
- Jede Kachel mit Wert trägt eine Farbe, jede ohne trägt `heatmap_no_data`.
- **Keine MAC-Adresse im Markup** — wie in allen Render-Tests seit 1.9.10.
- Kein `<style>`-Block in der Ausgabe.

Beide laufen als eigenständige PHP-Datei über die Stubs in `tests/i18n-stubs.php`, wie die
25 vorhandenen.

---

## 12. Reihenfolge

1. `NAWS_Colors`: elf Defaults, Gruppe, `heatmap_color()` — mit `test-heatmap-colors.php`
   zuerst.
2. `NAWS_Database::get_heatmap_year()`.
3. `templates/heatmap.php` und `sc_heatmap()` — mit `test-heatmap-render.php`.
4. CSS: Raster, klebende Spalte, Scrollbereich, Animation, Legende.
5. `heatmap-boot.js`: Tooltip, Jahreswechsel, Verzögerungsvariablen.
6. `get_heatmap_data()` und Registrierung.
7. Appearance-Seite, Shortcode-Referenz, `readme.txt`, `.pot` neu erzeugen.
8. Auf `dev.frank-neumann.de` prüfen — auch mit `temperature_unit = F`.

Schritt 1 bis 3 ergeben eine funktionierende Karte ohne Jahreswechsel. Wenn dort etwas
grundsätzlich nicht stimmt, fällt es auf, bevor der Endpunkt geschrieben ist.

---

## 13. Nicht Teil dieser Arbeit

- **Innenmodule.** Andere Spalte, anderer Wertebereich, eigene Skala.
- **Andere Größen** — Niederschlag, Wind, Luftfeuchte als Heatmap. Die Rechnung wäre
  dieselbe, die Skalen wären es nicht.
- **Mehrere Jahre nebeneinander.** Der Vergleich ist, was `[naws_history]` tut.
- **Klick auf eine Kachel** öffnet nichts. Ein Tagesdetail wäre eine eigene Ansicht.
- **Export der Karte als Bild.**

---

## 14. Ehrliche Grenzen

**Die Karte ist nur so gut wie die Tagestabelle.** Wo `temp_avg` fehlt, bleibt die Kachel
grau — auch dann, wenn `temp_min` und `temp_max` desselben Tages vorhanden sind. Aus
Minimum und Maximum ein Mittel zu rechnen wäre möglich, wäre aber ein anderer Wert als der,
den die übrigen Ansichten „Durchschnitt" nennen, und der Unterschied wäre nirgends
sichtbar. Lieber eine ehrliche Lücke.

**366 Kacheln sind auf dem Handy 366 Kacheln.** Die Mindestgröße von 14 px hält einen Tag
mit dem Finger treffbar, und die Karte scrollt waagerecht — aber ein Jahr auf einen Blick
ist auf einem schmalen Bildschirm nicht zu haben. Das ist eine bewusste Entscheidung gegen
Schrumpfen (8-px-Kacheln trifft niemand) und gegen ein Kippen der Achsen (die Karte sähe je
nach Gerät anders aus als beschrieben).

**Die Tastatur erreicht die Kacheln nicht.** 366 Tabulatorstationen wären schlimmer als
keine. Die Tabellensemantik gibt Screenreadern Datum und Wert; ein Tooltip per Tastatur
bleibt offen und wäre nachzurüsten, wenn jemand ihn braucht.

**Bei sehr langer Historie wächst die Knopfreihe.** Zehn Jahre sind zehn Knöpfe und passen;
bei zwanzig wird es eng, und dann wäre ein `<select>` die richtigere Form. Vorher nicht.
