# Regenradar: `[naws_radar]`

**Datum:** 2026-10-13
**Betrifft:** neu `includes/class-naws-radar-geo.php`, `includes/class-naws-radar-source.php`, `includes/class-naws-radar-store.php`, `includes/class-naws-radar.php`, `templates/radar.php`, `assets/js/radar.js`, `uninstall.php`, Tests; geändert `class-naws-shortcodes.php`, `class-naws-rest-api.php`, `class-naws-cron.php`, `class-naws-colors.php`, `class-naws-admin.php`, `admin/views/` (neuer Reiter + Shortcode-Hilfe), `frontend.css`, `admin.css`, `readme.txt`, Kataloge, Doku
**Auslöser:** Frank, 13.10.2026: „eine Satelliten-Karte, Regenradar … ohne dass man sich irgendwo registrieren muss“; nach der Demo „Regenradar am Standort“ (https://claude.ai/artifact/GFBfbCfpujbc7HJ4YJsdLs): „wichtig ist auf alle Fälle, dass wir den User schützen und keine IPs übermitteln. Die Animation ist schon sehr verlockend und für den Rest der Welt halt RainViewer. Wichtig wäre, dass man die Größe der Karten frei definieren kann“; „alte Bilder sollten auch wieder vom Server gelöscht werden“
**Ziel-Release:** offen (Schnitt auf Franks Ansage)

---

## 1. Ziel und Festlegungen

Ein animiertes Regenradar um die Station, eingebunden per Shortcode. Festgelegt im Brainstorming am 13.10.:

| Frage | Entscheidung |
|---|---|
| Datenschutz | Der Browser des Besuchers spricht **nur** mit der eigenen Website. Alle fremden Bilder holt der Server und legt sie als Dateien ab. |
| Quellen | Deutschland: DWD (Vergangenheit + 2 h Vorschau). Rest der Welt: RainViewer (nur Vergangenheit). Automatisch nach Koordinaten. |
| Größe | Darstellungsgröße (`width`, `height`) **und** Ausschnitt (`km`) unabhängig einstellbar. |
| Animation | Kompakt: 15-Minuten-Schritte, 1 h zurück + 2 h Vorschau = 13 Bilder (DWD), Erneuerung alle 15 min. |
| Hintergrundkarte | OpenStreetMap, gedämpft (grau), einmal geholt und dauerhaft gespeichert. |
| Einbindung | Nur eigener Shortcode `[naws_radar]` (Live-Dashboard/Widget ggf. später). |
| Abruf | Bei Bedarf im Hintergrund: Seite zeigt sofort den letzten Stand, ein veralteter Satz stößt einen einmaligen Hintergrundlauf an. Ohne Besucher keine Abrufe. |
| Aufräumen | Alte Bilder werden vom Server gelöscht (siehe 6). |

Geprüft am 13.10. ohne Schlüssel: `maps.dwd.de/geoserver/dwd/wms` (Ebenen `dwd:Niederschlagsradar` und `dwd:Radar_wn-product_1x1km_ger`, Zeitdimension 5 min, Vorschau bis +2 h) und `api.rainviewer.com/public/weather-maps.json` (seit 01.01.2026: nur Vergangenheit ~2 h in 10-min-Schritten, max. Zoom 7, ein Farbschema, 100 Anfragen/IP/min, Quellenangabe Pflicht, nur kleine/nichtkommerzielle Nutzung).

## 2. Shortcode

```
[naws_radar width="100%" height="420" km="150" source="auto"]
```

| Angabe | Bedeutung | Vorgabe | Grenzen |
|---|---|---|---|
| `width` | Breite des Rahmens (px oder %) | `100%` | wie bei anderen Shortcodes sanitiert |
| `height` | Höhe des Rahmens in px | `420` | 200–1200 (geklemmt) |
| `km` | Kantenlänge des Ausschnitts in km | `150` | DWD 50–600, RainViewer 150–1500 (geklemmt) |
| `source` | Quelle | `auto` | `auto`, `dwd`, `rainviewer`; `dwd` außerhalb Deutschlands fällt auf `rainviewer` zurück |

`km` gilt für die längere Seite des Rahmens (siehe 4).

## 3. Aussehen und Bedienung

- Gedämpfte Karte (CSS-Filter, Graustufen + aufgehellt), darüber das Radar halbtransparent, Station als Punkt mit Hof.
- Oben links die Uhrzeit groß; Vorschau-Bilder tragen „Vorschau“ in eigener Farbe.
- Unten eine Leiste: Abspielen/Pause, Zeitregler (`<input type="range">`) mit Markierung „jetzt“. Pfeiltasten springen ein Bild, Touch-Ziehen geht.
- Kleine Farblegende (leicht · mäßig · stark) passend zur Quelle; rechts unten die Quellenangabe (DWD bzw. „Weather data by RainViewer“, „© OpenStreetMap-Mitwirkende“).
- Animation startet automatisch, Bildwechsel ~600 ms, am letzten Bild 1,5 s Halt. Bei `prefers-reduced-motion: reduce` kein Autostart, angezeigt wird das Bild „jetzt“.
- Knöpfe nach Franks Regel vom 10.09.: eigene `:hover`/`:focus`-Regeln mit zwei Klassen im Selektor, Farben aus der Palette des Bausteins.
- Farben über einen eigenen Reiter „Regenradar“ im Erscheinungsbild (Stationspunkt, Zeitanzeige, Vorschau-Kennzeichnung, Leiste/Knöpfe), mit Vorschau wie bei den anderen Reitern. Keine Farbangaben im Shortcode.

## 4. Geometrie

Der Server erzeugt pro Ausschnitt einen **quadratischen** Bildsatz `km × km` mit 1024 px Kantenlänge in Web-Mercator (EPSG:3857), die Station in der Mitte. Der Browser legt ihn mit `object-fit: cover`-Logik mittig in den Rahmen: im Querformat ist die volle Breite zu sehen und oben/unten wird beschnitten, im Hochformat umgekehrt.

Folgen: `width`/`height` erzeugen keine eigenen Bildsätze; nur `km` und Quelle bestimmen den Schlüssel. Die Mercator-Box wird so berechnet, dass `km` am Stationsbreitengrad gilt (Maßstabsfaktor `1/cos(lat)`).

## 5. Bausteine

1. **`NAWS_Radar_Geo`** (rein, ohne Abrufe): Box aus Koordinaten + km, OSM-Zoomstufe (kleinste, bei der 1024 px mindestens erreicht werden, höchstens 12), benötigte Kacheln samt Pixelversatz, Stationsposition, RainViewer-Kacheln bei Zoom ≤ 7 mit Vergrößerungsfaktor. Behandelt die Datumsgrenze (Kachel-x modulo `2^z`).
2. **`NAWS_Radar_Source`**: `for_location(lat, lon, source)` → `dwd` oder `rainviewer` (Deutschland-Test über ein grobes Umriss-Polygon, nicht über Geocoding). Baut die Bildliste:
   - DWD: Zeiten auf 15 min gerastert ab dem letzten verfügbaren Zeitpunkt; 4 zurück + „jetzt“ aus `Niederschlagsradar`, 8 voraus aus `Radar_wn-product_1x1km_ger`; je ein WMS-`GetMap`-PNG genau für die Box.
   - RainViewer: `weather-maps.json` lesen, alle Bilder aus `radar.past` nehmen (10-min-Raster der Quelle, bis 2 h zurück, also bis 13 Bilder; ein 15-min-Raster passt auf deren Zeiten nicht), je Bild die Kacheln `256/{z}/{x}/{y}/2/1_1.png`.
3. **`NAWS_Radar_Store`**: Ablage unter `wp-content/uploads/naws-radar/<schlüssel>/` (`map/` für Kacheln, `frames/` für Radar, `manifest.json`). Schlüssel = Hash aus gerundeten Koordinaten (4 Stellen), km, Quelle. Sperre gegen parallele Läufe (Transient, 5 min), Sperrfrist je Quelle nach Fehlern wie `NAWS_Forecast::backoff_seconds()` (429 mit Retry-After 60 s–6 h, sonst 15 min; andere Fehler 5 min). Schreibt `last_seen` je Schlüssel in eine Option `naws_radar_sets`.
4. **`NAWS_Radar`**: Shortcode-Ausgabe über `templates/radar.php`. Liest das Manifest, gibt Rahmen, Kartenkacheln und Bildliste sofort aus. Ist der Satz älter als 15 min (oder fehlt), wird `wp_schedule_single_event( time(), 'naws_radar_refresh', [ $key ] )` geplant (nur wenn noch keiner geplant ist). Die Parameter je Schlüssel liegen in `naws_radar_sets`, damit der Hintergrundlauf weiß, was zu holen ist.
5. **REST** `GET /naws/v1/radar/<schlüssel>`: öffentlich, liefert nur das Manifest (Zeiten, Datei-URLs auf der eigenen Site, Vorschau-Kennzeichen, Stand). Unbekannter Schlüssel → 404. Kein Durchreichen fremder Daten.
6. **`assets/js/radar.js`**: Player ohne Bibliothek. Lädt Bilder vor, spielt ab, Regler, Tastatur, reduzierte Bewegung. Fragt das Manifest neu ab, wenn beim Laden noch nichts da war (alle 20 s, höchstens 10-mal) und danach alle 15 min, solange die Seite sichtbar ist. Nur Anfragen an die eigene Site.

Abrufe des Servers tragen einen eigenen User-Agent `XTX-Integration-for-Netatmo/<version> (+<site-url>)`, wie es die OSM-Kachelregeln verlangen.

## 6. Aufräumen

- **Nach jeder Erneuerung:** Radarbilder, die nicht im neuen Manifest stehen, kommen in eine Löschliste; der **vorige** Satz bleibt 30 min liegen (offene Player), danach wird gelöscht. Pro Ausschnitt also höchstens ~26 Radarbilder.
- **Unbenutzte Ausschnitte:** Ein Satz, dessen `last_seen` älter als 7 Tage ist, wird samt Kartenkacheln gelöscht und aus `naws_radar_sets` entfernt. Läuft im bestehenden täglichen Cron (`naws_daily_summary`), kein neuer Zeitplan.
- **Knopf** im Reiter „Regenradar“: „Radar-Zwischenspeicher leeren“ (Nonce, `manage_options`).
- **Deinstallation:** neue `uninstall.php` löscht **nur** `uploads/naws-radar/` und die Optionen/Transients des Radars. (Das Plugin hat bisher keine `uninstall.php`; Messdaten und übrige Einstellungen bleiben bewusst unberührt.)
- **Schalter aus:** Wird „Regenradar erlauben“ ausgeschaltet, wird der Ordner ebenfalls geleert.

## 7. Fehlerfälle

| Fall | Verhalten |
|---|---|
| Quelle antwortet nicht / 429 / 5xx | Sperrfrist je Quelle; Player zeigt letzte gute Bilder mit „Stand HH:MM Uhr“ |
| Letzte gute Bilder älter als 3 h | Keine Animation; Text „Das Regenradar ist vorübergehend nicht verfügbar.“ |
| Einzelne Bilder fehlen | Werden übersprungen; mindestens 2 Bilder für eine Animation, sonst Standbild |
| Kartenkacheln fehlen | Radar ohne Karte (neutrale Fläche), nächster Lauf versucht sie erneut |
| Uploads nicht beschreibbar | Frontend: Hinweistext wie oben; Admin: Meldung im Reiter; Protokolleintrag |
| Keine Stationskoordinaten | Keine Ausgabe; eingeloggte Admins sehen einen Hinweis |
| Schalter „Regenradar erlauben“ aus | Keine Abrufe, keine Ausgabe; Admins sehen einen Hinweis mit Link zum Reiter |

## 8. Einstellungen und Offenlegung

- Reiter „Regenradar“: Schalter **„Regenradar erlauben“ (Vorgabe aus)**, Farben (siehe 3), Knopf „Zwischenspeicher leeren“, Anzeige der vorhandenen Sätze (Schlüssel, km, Quelle, Stand, Größe auf der Platte).
- `readme.txt` → „Privacy & External Services“: drei neue Einträge **DWD (maps.dwd.de)**, **RainViewer (api.rainviewer.com, tilecache.rainviewer.com)**, **OpenStreetMap (tile.openstreetmap.org)** — was gesendet wird (Koordinaten-Box bzw. Kachelnummern und Zeit, nie Besucherdaten), wann (nur wenn der Schalter an ist und ein Radar-Shortcode angezeigt wurde, höchstens alle 15 min), Links zu Nutzungsbedingungen/Datenschutz.
- Textvorschlag für die Datenschutzerklärung per `wp_add_privacy_policy_content`.
- Shortcode-Hilfe im Admin, REST-Doku, Liste der Shortcodes in readme.

## 9. Tests

- `tests/test-radar-geo.php`: Box/Zoom/Kacheln/Stationsposition für Leipzig, Oslo, Tromsø, Punkt nahe 180°; Klemmen von km.
- `tests/test-radar-source.php`: DE-Entscheidung (Leipzig, Flensburg, Basel, Oslo); DWD-Zeitraster aus einem GetCapabilities-Ausschnitt; RainViewer-Auswahl aus gespeicherter `weather-maps.json`.
- `tests/test-radar-store.php`: Manifest schreiben/lesen, Löschen alter Bilder mit 30-min-Gnade, 7-Tage-Aufräumen, Sperre, Sperrfrist — alles über `pre_http_request`, keine echten Abrufe.
- `tests/test-radar-privacy.php`: Shortcode-Ausgabe und `radar.js` enthalten keine fremden Hosts (nur Site-URL).
- `tests/test-radar-player.php`: Player-Logik in Node (nächstes Bild, Halt am Ende, Pause, reduzierte Bewegung, Überspringen fehlender Bilder).
- Danach auf dev: Playwright Desktop + Handy, Netzwerkprotokoll: nur Anfragen an dev; Knöpfe hovern.

## 10. Nachtrag beim Planen (13.10.2026)

- Die Einstellungsseite hat keine Reiter: Schalter, Liste der Bildsätze und „Zwischenspeicher leeren“ stehen als Block „Regenradar“ auf der Einstellungsseite; die Farben als Reiter „Regenradar“ im Erscheinungsbild.
- DWD-Vergangenheitsbilder werden wiederverwendet (ein gemessenes Bild ändert sich nicht): pro Erneuerung 1 neues Vergangenheitsbild + 8 Vorschau-Bilder.
- Karte bei 150 km: Zoom 10, ~49 Kacheln einmalig; bei 50 km Zoom 11, 25 Kacheln.
- RainViewer-Bilder nehmen das 10-min-Raster der Quelle (bis 13 Bilder in 2 h).
