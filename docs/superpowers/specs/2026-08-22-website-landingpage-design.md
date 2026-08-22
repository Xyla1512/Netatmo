# Website-Landingpage für netatmo.frank-neumann.de

Stand 2026-08-22. Entwurf abgenommen; dieses Dokument ist die Grundlage für den
Umsetzungsplan.

## Was gebaut wird

Eine Landingpage auf der Wurzel-URL von `netatmo.frank-neumann.de`, die das
Plugin *XTX Integration for Netatmo* vorstellt — für Menschen lesbar, für
Suchmaschinen auffindbar und für Sprachmodelle zitierbar. Dazu ein kleines
Begleit-Plugin, das die Seite mit der Entwicklung des Produkts mitwachsen
lässt, und der Umzug des heutigen Testbetts auf `/live-demo/`.

Drei Dinge werden geliefert, nicht eines:

1. Die **Landingpage** als versionierte HTML-Datei, ausgeliefert über das
   Elementor-Canvas-Template.
2. Das **Begleit-Plugin** `xtx-netatmo-site` — Datenanbindung ans Repository,
   gemeinsamer Header, strukturierte Daten, `llms.txt`.
3. Die **Demo-Seite** `/live-demo/` mit dem Inhalt der heutigen Startseite.

## Erfolgskriterien

Die Arbeit ist fertig, wenn all das zutrifft:

- Ein Besucher ohne technischen Hintergrund versteht in unter zehn Sekunden,
  was das Plugin für ihn tut.
- Die Seite nennt keine Eigenschaft, die sie nicht auf derselben Seite belegt —
  durch Screenshot, Live-Wert oder Link.
- Im ausgelieferten HTML steht **kein** Aufruf an `fonts.googleapis.com`.
- Version, Datum, Download-Link und die Liste laufender Arbeiten stammen aus
  dem Repository und veralten nicht.
- Die strukturierten Daten sind gültig und **doppelungsfrei**.
- Kein veröffentlichter Screenshot zeigt ein Secret, einen API-Schlüssel oder
  eine Modul-MAC.
- Die Seite rendert sauber bei 360, 768 und 1440 px Breite, hell wie dunkel.

## Getroffene Entscheidungen

| Frage | Entscheidung | Warum |
|---|---|---|
| Ort | Landingpage auf die Wurzel-URL, Testbett zieht auf `/live-demo/` | Die Autorität liegt auf der Wurzel; zwei fokussierte Seiten ranken besser als eine überladene |
| Auslieferung | Elementor-Canvas + handgeschriebener HTML/CSS-Block im `post_content` | Volle Gestaltungshoheit, kein Theme-Gerüst, minimales Gewicht — und `the_content()` führt Shortcodes aus, also echte Live-Werte im Verkaufstext |
| Ziel | Herunterladen und installieren | GitHub-Release jetzt, WP.org sobald das Review durch ist |
| Sprache | Deutsch | de_DE-Installation, `.de`-Domain, kein Mehrsprachigkeits-Plugin; die englische Zielgruppe bedient die WP.org-Seite |
| Screenshots | Selbst erzeugt, Frontend und Admin | Echte Aufnahmen einer echten Station; Admin über temporären Zugangslink |
| SEO-Reichweite | Rank Math vollständig konfigurieren, **ohne** aktives Anpingen | Instant Indexing wirkt nach außen und ist nicht zurücknehmbar |
| Automatik | Begleit-Plugin auf der Website, serverseitig gerendert | Sichtbar für Crawler; überlebt einen GitHub-Ausfall; keine Token bei Dritten |
| Pflegetext | Deutsche Datei `docs/site/website.de.json` im Plugin-Repo | `CHANGELOG.md` ist englisch; englische Brocken auf einer deutschen Verkaufsseite sind schlechter als eine gepflegte Zeile |
| Rechtslage | Keine KI-Kennzeichnung | Siehe eigener Abschnitt |
| Impressum, Datenschutz | Nutzer zieht selbst nach | Juristische Texte gehören nicht aus dieser Feder; im Footer verlinkt |

## Zur KI-Kennzeichnung

Die Transparenzpflichten aus Artikel 50 KI-VO gelten seit dem 2. August 2026.
Für diese Seite entsteht daraus **keine Kennzeichnungspflicht**, aus zwei
voneinander unabhängigen Gründen.

Der Anwendungsbereich trifft nicht zu: Art. 50 Abs. 4 UAbs. 2 erfasst Text,
der veröffentlicht wird, „um die Öffentlichkeit über Angelegenheiten von
öffentlichem Interesse zu informieren". Eine Produktseite wirbt; sie informiert
die Öffentlichkeit nicht über einen Gegenstand öffentlicher Debatte.

Und die Ausnahme greift ohnehin: die Pflicht entfällt bei menschlicher
Überprüfung und redaktioneller Verantwortung einer natürlichen Person.

**Bedingung, die eingehalten werden muss:** die Kommission schließt
oberflächliche, rein formale Prüfungen ausdrücklich aus. Der fertige Text wird
dem Betreiber deshalb vor der Veröffentlichung zur inhaltlichen Abnahme
vorgelegt. Das ist kein Höflichkeitsschritt, sondern die Bedingung der
Ausnahme.

Ein sichtbares „KI-generiert"-Abzeichen wäre freiwillig und wird bewusst
weggelassen: auf einer Produktseite liest es sich als Entschuldigung.

## Seitenarchitektur

Zwölf Abschnitte, Reihenfolge nach Nutzen sortiert, nicht nach Technik.

| # | Anker | Abschnitt | Aufgabe |
|---|---|---|---|
| 0 | — | Header, mitlaufend | Wortmarke, vier Sprungmarken, Download-Knopf |
| 1 | `#start` | Hero | H1, ein Satz, **echter Live-Messwert mit Uhrzeit** |
| 2 | `#nutzen` | Was du bekommst | Drei Versprechen in Alltagssprache, je mit Bild |
| 3 | `#fuer-wen` | Für wen das gedacht ist | Drei Zielgruppen zum Wiedererkennen |
| 4 | `#funktionen` | Sechs Funktionsblöcke | Nutzen-Überschrift, Technikzeile klein darunter, Screenshot |
| 5 | `#neu` | Neu und in Arbeit | **Automatisch** aus dem Repository |
| 6 | `#demo` | Live-Demo | Verweis auf `/live-demo/` |
| 7 | `#installation` | In drei Schritten installiert | Trägt das `HowTo`-Schema |
| 8 | `#daten` | Und was ist mit meinen Daten? | Vertrauen in Alltagssprache |
| 9 | `#technik` | Für Technikinteressierte | Aufklappbar per `<details>`; alle Shortcodes, alle Rechenwerte, REST-API |
| 10 | `#fakten` | Fakten auf einen Blick | Dichte Tabelle; trägt die Extraktion |
| 11 | `#faq` | Häufige Fragen | Trägt das `FAQPage`-Schema |
| 12 | `#ueber` | Über mich | Name, Foto, Station, Beweggrund |
| — | — | Footer | GitHub, Changelog, Lizenz, Impressum, Datenschutz |

**H1:** „Netatmo in WordPress — deine Wetterstation auf deiner eigenen Website"

**Genau eine H1.** Die zwölf Abschnitte tragen H2, ihre Unterpunkte H3. Keine
Überschriftenebene wird zur Gestaltung übersprungen.

**H1 und SEO-Titel weichen absichtlich voneinander ab.** Die H1 spricht den
Menschen an, der schon auf der Seite ist; der Titel im Suchergebnis muss in
etwa 55 Zeichen die Suchanfrage treffen. Zwei Aufgaben, zwei Formulierungen —
kein Versehen.

### Die sechs Funktionsblöcke

Reihenfolge nach Wirkung auf einen Laien, nicht nach technischem Gewicht. Jeder
Block: Nutzen-Überschrift, ein bis zwei Sätze, kleine Technikzeile, Screenshot
mit Bildunterschrift.

| Block | Überschrift (Arbeitsstand) | Screenshot |
|---|---|---|
| 1 | Sieh auf einen Blick, wie das Wetter gerade ist | 1 Live-Dashboard |
| 2 | Nicht nur wie es ist — wie es war | 2 Jahresvergleich |
| 3 | Was die Zahlen bedeuten, ausgerechnet | 5 Rechenwerte-Tabelle |
| 4 | Die nächsten fünf Tage, ohne fremdes Widget | 3 Vorhersage |
| 5 | Ein Platz in der Seitenleiste, auf jedem Gerät | 4 Widget + Infobar, 6 Handy |
| 6 | Passt sich deiner Seite an, nicht umgekehrt | 10 Erscheinungsbild |

Die Admin-Aufnahmen 7, 8, 9 und 11 gehören nicht hierher, sondern in
Abschnitt 8 (Cron-Log, Verschlüsselungsstatus) und Abschnitt 9
(Verbindungseinstellungen, Shortcode-Dokuseite).

### Drei Schreibregeln

**Antwort zuerst.** Der erste Satz jedes Abschnitts steht für sich allein und
ist zitierbar — mit vollem Namen der Sache, nicht mit „es".

**Kein Satz verweist auf einen anderen.** Kein „wie oben erwähnt". Abrufsysteme
zitieren einzelne Schnipsel; ein Schnipsel, der seinen Nachbarn braucht, wird
nicht zitiert.

**Zahlen statt Adjektive.** Nicht „umfangreiche Auswertungen", sondern
„27 berechnete Werte". Nicht „zuverlässig", sondern „ruft alle zehn Minuten ab".

### Ein Absatz über die Grenzen

Abschnitt 8 endet mit dem, was das Plugin **nicht** kann: keine Netatmo-Kameras,
keine Thermostat-Steuerung, kein Ersatz für die Netatmo-App auf dem Handy, und
ohne Wetterstation ist es nutzlos. Ehrliche Grenzen kaufen mehr Glaubwürdigkeit
als jede weitere Funktionszeile.

## Das Begleit-Plugin `xtx-netatmo-site`

Eigenes Projekt in `C:\Users\xyla1\.claude\NetatmoSite\` mit eigenem Git.
**Getrennt vom Produkt-Plugin**, damit das laufende WP.org-Review nicht berührt
wird.

### Datenquellen

Zwei, beide öffentlich und ohne Token erreichbar:

- `https://api.github.com/repos/Xyla1512/Netatmo/releases` — Version, Datum,
  ZIP-Link, Dateigröße, Pre-Release-Kennzeichen.
- `https://raw.githubusercontent.com/Xyla1512/Netatmo/main/docs/site/website.de.json`
  — die deutschen Texte.

### Holen, Zwischenspeichern, Überleben

Ein WP-Cron-Ereignis alle sechs Stunden. Das Ergebnis wandert in eine
**Option**, nicht bloß in ein Transient: fällt GitHub aus oder antwortet
fehlerhaft, bleibt der zuletzt erfolgreich geholte Stand stehen. Die Seite ist
nie leer, nie halb. Ein fehlgeschlagener Abruf wird protokolliert, nicht
angezeigt.

Alle Ausgaben werden **serverseitig gerendert**. Kein Nachladen per JavaScript —
was nicht im ausgelieferten HTML steht, sehen weder Google noch ein
Sprachmodell.

### Die Beförderungsregel

Das Herzstück, und der Grund, warum veröffentlichte Funktionen ohne Zutun in
den Funktionsteil wandern. Jeder Eintrag in `website.de.json` trägt ein Feld
`ab` — die Version, in der er erscheint.

Das Plugin vergleicht `ab` per `version_compare()` gegen die neueste **stabile**
Veröffentlichung und ordnet selbst zu:

| Bedingung | Wo der Eintrag erscheint |
|---|---|
| `ab` ist `null` oder größer als die neueste stabile Version | „Woran gerade gearbeitet wird" |
| `ab` entspricht der neuesten stabilen Version | „Neu in Version X", oben im Funktionsteil |
| `ab` ist älter | Geht in die dauerhafte Funktionsliste ein |
| Ein Pre-Release ist mindestens `ab` | Zusätzlich ein Abzeichen: „schon in 1.9.7-beta.1 testbar" |

Ein Release verschiebt damit von allein, was wo steht. Kein Handgriff an der
Seite.

### Was die Automatik nicht leistet

Die sechs bebilderten Funktionsblöcke schreibt sie nicht. Ein Screenshot und
ein Satz, der erklärt, warum eine Funktion jemanden interessiert, brauchen ein
Auge. Was die Automatik stattdessen garantiert: **eine neue Funktion ist ab dem
Release sichtbar**, prominent, mit deutschem Text. Nichts sieht je veraltet aus.
Einen eigenen bebilderten Block bekommt sie von Hand, wenn sie groß genug ist.

Diese Grenze steht hier ausdrücklich, damit sie später nicht als Versäumnis
gelesen wird.

### Bereitgestellte Shortcodes

| Shortcode | Liefert |
|---|---|
| `[naws_site_header]` | Gemeinsamer Header für Landingpage und Demo-Seite |
| `[naws_site_roadmap]` | „Woran gerade gearbeitet wird" |
| `[naws_site_whatsnew]` | „Neu in Version X" |
| `[naws_site_versions]` | Kompakte Versionshistorie — steht im Footer, nicht in Abschnitt 5 |
| `[naws_site_fact key="…"]` | Einzelwert: `version`, `released`, `zip_url`, `zip_size`, `beta` |

Zusätzlich, nicht als Shortcode: `SoftwareApplication`- und `HowTo`-JSON-LD im
`wp_head`, sowie `llms.txt` und `robots.txt` über eine Rewrite-Regel.

### Format von `website.de.json`

```json
{
  "aktualisiert": "2026-08-22",
  "vorhaben": [
    {
      "id": "naws-calc",
      "titel": "Ein Kurzbefehl für 27 berechnete Werte",
      "satz": "Taupunkt, gefühlte Temperatur, Frost- und Sommertage, Wärmesummen und der Dürreindex SPI — alle über einen einzigen Shortcode.",
      "ab": "1.9.7",
      "bild": null
    }
  ]
}
```

`ab: null` bedeutet: noch keiner Version zugeordnet, erscheint unter „in
Arbeit". `bild` verweist optional auf einen Screenshot-Slug, falls der Eintrag
später einen eigenen Block bekommt.

**Pflegepflicht:** Wird am Plugin gearbeitet, wandert die Zeile im selben
Commit mit. Das gehört ins Release-Ritual.

## Gestaltung

**Header.** Das Canvas-Template liefert bewusst keinen; er wird gebaut. Schmale,
mitlaufende Leiste: links eine typografische Wortmarke — ein Logo existiert
nicht, und ein schnell erfundenes wäre schlechter als gut gesetzte Schrift —,
mittig vier Sprungmarken, rechts der Download-Knopf. Beim Scrollen verdichtet
sie sich und legt eine feine Trennlinie an. Unter 768 px klappen die
Sprungmarken weg, der Knopf bleibt. Derselbe Header steht über `/live-demo/`.

**Farbe aus dem eigenen Produkt.** Die dunklen Kopfleisten des Plugins tragen
`#2d5252`. Dieses tiefe Petrol wird der Anker der Seite. Wer von der Seite ins
Plugin geht, erlebt keinen Bruch.

**Schrift vom eigenen Server.** Eine Variable-Font-Datei, `font-display: swap`,
vorgeladen, Systemschriften als Rückfall. Kein Aufruf an Google — zugleich die
DSGVO-saubere und die schnellste Lösung.

**Zurückhaltung als Stilmittel.** Großzügiger Weißraum, wenige Schriftgrößen
mit klarem Abstand, echte Bildgrößen ohne Beschnitt-Trickserei.

**Bewegung an genau zwei Stellen:** Inhalte erscheinen beim Scrollen sanft, die
Live-Zahl im Hero zählt hoch. Beides schaltet sich bei `prefers-reduced-motion`
ab.

**Dunkelmodus** folgt der Systemeinstellung. Vollständige Palette als Tokens auf
`:root`, im Dunkelblock nur die Tokens neu belegt.

## SEO

### Rank Math

Rank Math Pro 1.0.276 ist installiert und **vollständig unkonfiguriert**.

- Startseiten-Titel: „Netatmo in WordPress einbinden — kostenloses Plugin"
- Beschreibung um 155 Zeichen, mit Nutzen, Preis und einer konkreten Zahl
- Wissensgraph als **Person**, verknüpft mit GitHub und `frank-neumann.de`
- `website_name`: „XTX Integration for Netatmo"
- Sitemap an, Breadcrumbs für `/live-demo/`
- Open-Graph-Karte 1200 × 630, eigens entworfen; Twitter-Karte
  `summary_large_image`
- **Kein** Instant Indexing, kein IndexNow — ausdrückliche Entscheidung

### Aufräumen

- **„Beispiel-Seite" entfernen.** Eine WordPress-Vorlagenseite in der Sitemap
  ist ein Qualitätssignal gegen die eigene Domain.
- Permalinks von `/%year%/%monthnum%/%day%/%postname%/` auf `/%postname%/`,
  mit Weiterleitung für den einen bestehenden Beitrag. Optional.

### Technisch

Bilder mit gesetzten `width`/`height`, `loading="lazy"` außer im Hero, dort
`fetchpriority="high"`. CSS vollständig eingebettet und auf die Seite begrenzt.
Kein rendernd blockierender Fremdinhalt.

## GEO

**Strukturierte Daten mit klarer Zuständigkeit**, damit sich nichts doppelt —
doppeltes JSON-LD ist schlechter als gar keines:

| Typ | Wer liefert |
|---|---|
| `WebSite`, `Person`, `BreadcrumbList`, `FAQPage` | Rank Math |
| `SoftwareApplication`, `HowTo` | Begleit-Plugin, mit Live-Version |

Rank Maths eigenes Schema für die Startseite wird abgeschaltet.

`SoftwareApplication` trägt: Name, `applicationCategory`, `operatingSystem:
WordPress`, `softwareVersion` (live), `datePublished`, `dateModified`,
`offers.price: 0` mit `priceCurrency: EUR`, `license`, `downloadUrl`,
`softwareRequirements: WordPress 6.2+, PHP 8.0+`, `featureList`, `screenshot`,
`author` als Person.

**`llms.txt` unter der Wurzel**, aus denselben Daten erzeugt und damit nie
veraltet: was das Plugin ist, die harten Fakten, die wichtigen Links, die
laufende Version.

**`robots.txt`, die KI-Crawler ausdrücklich einlädt** — GPTBot, ClaudeBot,
PerplexityBot, Google-Extended. Bislang existiert keine eigene `robots.txt`.
Das ist der Punkt, an dem GEO steht oder fällt.

**Jeder Screenshot bekommt eine Bildunterschrift**, die in Worten sagt, was zu
sehen ist. Text, der nur im Bild steht, existiert für ein Sprachmodell nicht.

**Zu `<details>`:** Inhalt darin steht vollständig im ausgelieferten HTML und
wird indexiert. Zugeklappt ist eine Anzeige-Eigenschaft, keine
Auslieferungs-Eigenschaft. Deshalb kann Abschnitt 9 gleichzeitig schlank für
Laien und faktendicht für Modelle sein.

## Screenshots

Sechs vom Frontend über den Browser, fünf aus dem Admin über einen temporären
Zugangslink.

| # | Aufnahme | Quelle |
|---|---|---|
| 1 | Live-Dashboard `[naws_live]` | Frontend |
| 2 | Jahresvergleich `[naws_history]` | Frontend |
| 3 | Vorhersage `[naws_forecast]` | Frontend |
| 4 | Widget mit Infobar | Frontend |
| 5 | Rechenwerte-Tabelle | Frontend |
| 6 | Handy-Ansicht | Frontend, 390 px |
| 7 | Verbindungseinstellungen | Admin |
| 8 | Cron-Log | Admin |
| 9 | Verschlüsselungsstatus | Admin |
| 10 | Erscheinungsbild mit Farbwahl | Admin |
| 11 | Shortcode-Dokuseite | Admin |

**Maskierung ist Pflicht.** Die Admin-Ansichten zeigen Client-Secret,
API-Schlüssel und Modul-MACs. Alles davon wird vor dem Hochladen unkenntlich
gemacht, und die Bilder werden vor der Veröffentlichung vorgelegt. Ein
Screenshot mit echtem Secret ist aus dem Netz nicht zurückzuholen.

Hochgeladen wird in die Mediathek mit deutschem Alt-Text, in moderner
Kompression, mit gesetzten Abmessungen.

## Umzug auf `/live-demo/`

**Erst sichern, dann anfassen.** Vor jedem Eingriff werden `post_content`,
`_elementor_data`, `_elementor_edit_mode` und `_wp_page_template` von Seite 7
in eine Datei gesichert. Der Rückweg muss ein einzelner Befehl bleiben.

Schritte:

1. Sicherung von Seite 7 ziehen und ablegen.
2. Neue Seite `/live-demo/` anlegen, Elementor-Metadaten hinüberkopieren,
   Darstellung prüfen.
3. Seite 7 auf Canvas umstellen, Elementor abschalten, Landingpage-HTML in
   `post_content` setzen.
4. Beide Seiten über HTTP abrufen und prüfen.

## Verifikation

Geprüft wird am echten Abruf, nicht am Markup:

- Beide Seiten per HTTP holen, Statuscode 200
- **Gegenprobe auf `fonts.googleapis.com` — null Treffer**
- JSON-LD gegen einen Validator, und auf Doppelungen prüfen
- Darstellung bei 360, 768 und 1440 px, hell und dunkel
- `llms.txt` und `robots.txt` erreichbar und korrekt
- Alle elf Screenshots geladen, kein Secret sichtbar
- Automatik: Zwischenspeicher leeren, Abruf erzwingen, Beförderungsregel gegen
  die tatsächliche Release-Lage prüfen
- Ausfallprobe: GitHub-Antwort künstlich scheitern lassen, letzter guter Stand
  muss stehen bleiben

## Ausdrücklich außerhalb des Umfangs

- Impressum und Datenschutzerklärung — zieht der Betreiber selbst nach
- Änderungen am Produkt-Plugin
- Einreichung oder Anpingen bei Suchmaschinen
- Mehrsprachigkeit
- Die falsche Shortcode-Zahl in `readme.txt` (5 statt 10) — eigenes Thema,
  betrifft das laufende WP.org-Review

## Risiken

**Der Umzug von Seite 7 trifft die Startseite.** Zwischen Schritt 3 und 4 ist
die Seite kurz unfertig. Sicherung vorher, Prüfung sofort danach.

**Die GitHub-API begrenzt Abrufe ohne Token** auf 60 pro Stunde und IP. Bei
sechs Stunden Abstand unkritisch, aber der Zwischenspeicher muss halten, auch
wenn ein Abruf scheitert.

**Elementor lädt auf `/live-demo/` weiterhin Google Fonts.** Die Landingpage
ist davon frei, die Demo-Seite nicht. Behebbar, aber nicht Teil dieses
Vorhabens — hier festgehalten, damit es nicht untergeht.

**Die Ausnahme von der KI-Kennzeichnung trägt nur bei echter Abnahme.** Ohne
inhaltliches Gegenlesen entfällt sie.
