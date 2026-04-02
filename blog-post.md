<!--
=======================================================================
  SEO / META-DATEN
=======================================================================

  SEO-Title (max. 60 Zeichen):
  Netatmo Wetterdaten in WordPress anzeigen – XTX Netatmo Plugin

  Meta-Description (max. 155 Zeichen):
  Zeige live Wetterdaten deiner Netatmo-Station auf deiner WordPress-Website.
  Kostenlos, verschluesselt, automatisch synchronisiert. Jetzt auf GitHub laden.

  Focus-Keyword:        Netatmo WordPress Plugin
  Sekundaere Keywords:  Netatmo Wetterdaten Website, Netatmo Wetterstation WordPress,
                        Wetterdaten WordPress anzeigen, Netatmo API WordPress,
                        Netatmo Dashboard WordPress, Wetterstation Website einbinden,
                        XTX Netatmo, Netatmo Plugin kostenlos

  Slug:                 netatmo-wordpress-plugin-wetterdaten-website
  Canonical:            (deine Domain)/netatmo-wordpress-plugin-wetterdaten-website

  Open-Graph / Social:
    og:title        →  Netatmo Wetterdaten auf deiner WordPress-Website – so geht's
    og:description  →  Kostenloses WordPress-Plugin fuer Netatmo-Wetterstationen:
                       Live-Dashboard, Charts, Vorhersage, REST-API. Verschluesselt
                       und vollautomatisch.
    og:type         →  article
    article:tag     →  Netatmo, WordPress, Wetterstation, Plugin, Wetterdaten, Dashboard

  Schema.org (JSON-LD):
    @type           →  BlogPosting / TechArticle
    headline        →  Netatmo Wetterdaten in WordPress anzeigen – XTX Netatmo Plugin
    author          →  Frank Neumann
    datePublished   →  2026-03-20
=======================================================================
-->

# Netatmo-Wetterdaten auf deiner WordPress-Website: So holst du das Maximum aus deiner Wetterstation

Du hast eine Netatmo-Wetterstation zu Hause stehen? Dann kennst du das: Temperatur, Luftfeuchtigkeit, CO2-Werte, Windgeschwindigkeit, Regenmengen -- alles wird sauber gemessen und landet in der Netatmo-App auf deinem Handy. Funktioniert prima. Aber was, wenn du diese Daten auch auf deiner eigenen Website zeigen moechtest? Vielleicht betreibst du einen Blog, eine lokale Vereinsseite, oder du bist einfach jemand, der gerne teilt, wie das Wetter bei dir vor der Haustuer gerade aussieht?

Genau dafuer habe ich **XTX Netatmo** entwickelt -- ein kostenloses WordPress-Plugin, das deine Netatmo-Wetterstation direkt mit deiner Website verbindet. Und zwar nicht als halbherzige Bastelei, sondern als vollwertiges Plugin mit Live-Dashboard, animierten Charts, Wettervorhersage, verschluesselter Datenhaltung und einer REST-API fuer Entwickler.

In diesem Beitrag zeige ich dir, was das Plugin alles kann, wie es unter der Haube funktioniert und warum ich es gerade offiziell bei WordPress.org einreiche.

---

## Die Idee dahinter: Wetterdaten gehoeren auf deine Website

Wer eine Netatmo-Station besitzt, sammelt rund um die Uhr hochpraezise Messdaten. Die offizielle App und das Netatmo-Webinterface sind dafuer voellig ausreichend -- solange man die Daten nur fuer sich selbst braucht. Aber es gibt ueberraschend viele Leute, die ihre Wetterdaten auch oeffentlich zugaenglich machen moechten: Hobby-Meteorologen, die ihre Station im Garten stehen haben und die Nachbarschaft informieren wollen. Vereine, die auf ihrer Website das aktuelle Wetter am Vereinsgelaende anzeigen moechten. Blogger, die einen Wetter-Widget in ihre Sidebar einbauen wollen. Oder Entwickler, die Wetterdaten programmatisch abfragen moechten, um sie in eigene Projekte einzubinden.

Fuer all diese Faelle gab es bisher keine wirklich gute Loesung im WordPress-Universum. Genau das wollte ich aendern.

---

## Was XTX Netatmo alles kann

Lass mich dir einen Rundgang durch die wichtigsten Features geben. Ich verspreche: Es ist mehr, als du auf den ersten Blick erwarten wuerdest.

### Ein Live-Dashboard, das sich sehen lassen kann

Das Herzstück des Plugins ist das **Live-Dashboard**. Sobald du den definierten Shortcode in eine Seite oder einen Beitrag einbaust, erscheint ein vollstaendiges Dashboard mit Sensorkarten fuer alle deine Module. Jede Karte zeigt den aktuellen Messwert, einen **24-Stunden-Trendchart** mit fliessenden Farbverlaeufen und den Zeitpunkt der letzten Messung.

Die Darstellung ist dabei nicht statisch -- die Werte aktualisieren sich automatisch, die Charts sind mit **Chart.js** animiert, und das gesamte Layout ist **vollstaendig responsive**. Egal ob jemand deine Seite auf einem grossen Monitor, einem Tablet oder einem Smartphone oeffnet: Es sieht ueberall gut aus. Dafuer sorgt ein durchdachtes Mobile-First-Design mit standardisierten Breakpoints, das seit Version 1.2.0 fest im Plugin verankert ist.

### Alle Netatmo-Module werden unterstuetzt

XTX Netatmo arbeitet mit dem kompletten Netatmo-Oekosystem zusammen:

- **Basisstation**: Temperatur, Luftfeuchtigkeit, CO2, Laermpegel, Luftdruck
- **Aussenmodul**: Temperatur und Luftfeuchtigkeit im Freien
- **Windmesser**: Aktuelle Windgeschwindigkeit, Windrichtung und Boen
- **Regenmesser**: Stuendliche, taegliche und rollierende 24-Stunden-Regenmengen
- **Zusaetzliches Innenmodul**: Temperatur, Luftfeuchtigkeit und CO2 fuer weitere Raeume

Egal ob du nur die Basisstation mit Aussenmodul hast oder das volle Programm mit Wind, Regen und mehreren Innenmodulen -- das Plugin erkennt automatisch, welche Module verbunden sind, und zeigt genau die Daten an, die verfuegbar sind.

### Mehr als nur Rohdaten: Berechnete Wetterwerte

Neben den direkten Messwerten berechnet XTX Netatmo auch **abgeleitete Metriken**, die du sonst nur von professionellen Wetterdiensten kennst:

- **Gefuehlte Temperatur** -- wie warm oder kalt es sich tatsaechlich anfuehlt
- **Hitzeindex** -- besonders relevant im Sommer
- **Taupunkt** -- ab wann Kondensation einsetzt
- **Windchill** -- die gefuehlte Kaelte bei Wind

Diese Werte werden automatisch aus den vorhandenen Sensordaten errechnet und im Dashboard dargestellt. Du musst dafuer nichts konfigurieren.

### Wettervorhersage direkt eingebaut

Manchmal will man nicht nur wissen, wie das Wetter *jetzt* ist, sondern auch, wie es *morgen* wird. Deshalb hat XTX Netatmo eine integrierte **5-Tage-Wettervorhersage**. Du kannst zwischen zwei Anbietern waehlen:

- **Open-Meteo** -- funktioniert weltweit, braucht keinen API-Key, und liefert zuverlaessige Prognosen
- **Yr.no (MET Norway)** -- der Wetterdienst aus Norwegen, bekannt fuer besonders praezise Vorhersagen, gerade in Nordeuropa

Die Vorhersage wird intelligent gecacht, sodass nicht bei jedem Seitenaufruf eine neue API-Anfrage rausgeht. Den Shortcode `[naws_forecast]` einbauen -- fertig.

### Astronomie zum Staunen

Ein Detail, das ich persoenlich besonders mag: Das Plugin berechnet **Astronomie-Daten** passend zu deinem Standort. Sonnenaufgang und Sonnenuntergang, die aktuelle Mondphase, und sogar bevorstehende Sonnen- und Mondfinsternisse. Das alles erscheint uebersichtlich im Dashboard und gibt deiner Wetterseite diesen zusaetzlichen "Wow, das ist ja richtig durchdacht"-Faktor.

### Historische Daten und Jahresvergleiche

Wetterdaten sind erst dann richtig spannend, wenn man sie ueber laengere Zeitraeume vergleichen kann. War der Februar dieses Jahr kaelter als letztes Jahr? Hat es im Sommer 2025 mehr geregnet als 2024?

Mit dem Shortcode `[naws_history]` erzeugst du **interaktive Jahresvergleichs-Charts**. Mehrere Jahre werden uebereinander dargestellt, sodass Trends und Ausreisser sofort ins Auge fallen. Falls du nur ein bestimmtes Jahr anzeigen moechtest, geht das mit `[naws_history year="2025"]`.

Das Plugin speichert alle Messdaten lokal in deiner WordPress-Datenbank -- mit automatischen Tageszusammenfassungen fuer schnelle Abfragen auch ueber lange Zeitraeume.

### 130 Farben und 4 Icon-Sets: Dein Design, deine Regeln

Ich weiss, wie wichtig es ist, dass ein Plugin zum bestehenden Website-Design passt. Deshalb habe ich ein **umfangreiches Farbsystem** eingebaut: Ueber 130 einzelne Farbwerte lassen sich im Admin-Bereich anpassen. Von den Theme-Grundfarben ueber die Chart-Linienfarben bis hin zur Hintergrundfarbe einzelner Sensor-Kacheln -- du hast die volle Kontrolle.

Dazu kommen **vier Icon-Sets** zur Auswahl:

- **Emoji** -- bunt und verspielt
- **Outline** -- schlank und modern
- **Filled** -- kraeftig und praesent
- **Minimal** -- reduziert auf das Wesentliche

Jedes Sensor-Icon (Temperatur, Feuchte, Druck, Wind, Regen, CO2, Laerm) kann ausserdem **individuell eingefaerbt** werden. Die gesamte Konfiguration passiert ueber eine uebersichtliche Appearance-Seite mit **Live-Vorschau** -- du siehst sofort, wie deine Aenderungen aussehen werden.

### Shortcodes fuer jede Situation

Nicht jeder will ein komplettes Dashboard auf seiner Seite. Manchmal reicht eine dezente Infobar im Header, oder man moechte nur einen einzelnen Wert in einen Fliesstext einbauen. XTX Netatmo bietet dafuer verschiedene Shortcodes:

| Shortcode | Was es macht |
|---|---|
| `[naws_live]` | Das volle Live-Dashboard mit allen Sensorkarten und Charts |
| `[naws_infobar]` | Eine kompakte Zeile mit den wichtigsten Werten -- perfekt fuer Header oder Footer |
| `[naws_current]` | Aktuelle Messwerte als einzelne Karten |
| `[naws_value]` | Einen einzelnen Messwert inline in Text einbetten |
| `[naws_history]` | Historische Daten als Jahresvergleichs-Charts |
| `[naws_forecast]` | Die 5-Tage-Wettervorhersage |

Alle Shortcodes sind responsive und passen sich automatisch an die Bildschirmgroesse an.

### Eine REST-API fuer Entwickler und Bastler

Wenn du ueber WordPress hinaus mit deinen Wetterdaten arbeiten moechtest, bietet XTX Netatmo eine **vollstaendige REST-API**. Die Endpunkte liefern JSON-Daten und sind mit **API-Key-Authentifizierung** und **Rate-Limiting** abgesichert. Eine komplette Dokumentation findest du direkt im Admin-Bereich des Plugins.

Moegliche Einsatzszenarien: Wetterdaten in ein Smart-Home-Dashboard einbinden, eigene Visualisierungen bauen, Daten an externe Dienste weiterleiten -- oder einfach per Skript abfragen, wie warm es gerade draussen ist.

### Daten-Export und -Import

Alle gesammelten Wetterdaten lassen sich als **JSON exportieren** und bei Bedarf wieder importieren. Das ist praktisch fuer Backups, fuer den Umzug auf einen neuen Server, oder wenn du deine Daten in anderen Tools weiterverarbeiten moechtest. Der Export laeuft ueber **chunked AJAX-Processing**, sodass auch grosse Datenmengen problemlos und ohne Timeout verarbeitet werden.

### Dreisprachig: Deutsch, Englisch, Norwegisch

Das Plugin ist komplett in **Deutsch**, **Englisch** und **Norwegisch** verfuegbar -- sowohl im Backend als auch im Frontend. Das Sprachsystem ist dateibasiert aufgebaut, sodass weitere Uebersetzungen leicht hinzugefuegt werden koennen.

---

## Unter der Haube: Sicherheit, die keine Kompromisse macht

Jetzt wird es etwas technischer -- aber gerade dieses Thema liegt mir am Herzen, weil es bei vielen WordPress-Plugins zu kurz kommt.

### AES-256-GCM-Verschluesselung fuer alle Zugangsdaten

Wenn du dein Netatmo-Konto mit dem Plugin verbindest, entstehen zwangslaeufig sensible Daten: OAuth2-Tokens, deine Client-ID, dein Client-Secret, der REST-API-Schluessel. Bei vielen Plugins landen solche Daten einfach im Klartext in der WordPress-Datenbank. Das fand ich nicht akzeptabel.

Seit Version 1.5.6 werden **alle sicherheitsrelevanten Daten mit AES-256-GCM verschluesselt** gespeichert -- dem gleichen Verschluesselungsstandard, den auch Banken und Geheimdienste einsetzen. Die technischen Details fuer die Interessierten:

- **Schluesselableitung** ueber HKDF-SHA256 aus dem WordPress AUTH_KEY
- **Zufaelliger 96-Bit Initialization Vector** bei jeder einzelnen Verschluesselung
- **128-Bit Authentication Tag** zur Integritaetspruefung -- damit Manipulationen sofort auffallen
- **Automatische Migration**: Wer von einer aelteren Version aktualisiert, dessen bestehende Klartext-Credentials werden beim Update automatisch verschluesselt. Kein manueller Eingriff noetig.

Die Verschluesselung passiert vollstaendig **im Hintergrund**: Beim Speichern wird verschluesselt, beim Lesen entschluesselt. Als Nutzer merkst du davon nichts -- ausser dass deine Daten jetzt sicher sind.

### Automatische OAuth2-Token-Verwaltung: Einrichten und vergessen

Die Verbindung zwischen deiner Website und der Netatmo-API laeuft ueber das **OAuth2-Protokoll**. Das bedeutet: Es gibt Access-Tokens, die nach einigen Stunden ablaufen, und Refresh-Tokens, mit denen man neue Access-Tokens anfordern kann. Bei vielen Implementierungen ist das eine potenzielle Fehlerquelle -- Token laeuft ab, Sync stoppt, niemand merkt es.

XTX Netatmo loest das anders:

- **Proaktive Erneuerung**: Der Token wird nicht erst erneuert, wenn er abgelaufen ist, sondern bereits **15 Minuten vorher**. Damit gibt es kein Zeitfenster, in dem die Verbindung unterbrochen waere.
- **Intelligente Fehlerbehandlung**: Wenn das Netzwerk gerade nicht erreichbar ist, versucht das Plugin es automatisch **bis zu drei Mal** mit steigenden Wartezeiten (Exponential Backoff). Keine Panik bei einem kurzen Netzwerk-Hickup.
- **Saubere Eskalation**: Wenn ein Token tatsaechlich serverseitig widerrufen wurde -- zum Beispiel weil du dein Netatmo-Passwort geaendert hast -- erkennt das Plugin das, setzt ein entsprechendes Flag und fordert dich im Admin-Bereich freundlich zur erneuten Anmeldung auf. Keine Endlosschleifen, keine kryptischen Fehlermeldungen.

Das Ergebnis: Du richtest die Verbindung einmal ein, und danach kuemmert sich das Plugin **vollautomatisch** um alles Weitere. Egal ob dein Server mal kurz offline war, ob Netatmo die Tokens rotiert, oder ob ein Netzwerkproblem dazwischenkommt -- XTX Netatmo faengt das ab.

### Cron-Watchdog: Selbstheilung fuer die Datensynchronisation

Die regelmaessige Abfrage neuer Messdaten laeuft ueber WordPress-Cron-Jobs. Und wer WordPress kennt, weiss: Cron-Jobs koennen manchmal haengenbleiben, doppelt laufen oder einfach verschwinden. Deshalb hat XTX Netatmo einen **integrierten Watchdog**, der die Synchronisation ueberwacht:

- Erkennt automatisch, wenn ein Sync-Job haengengeblieben ist
- Startet den Prozess selbststaendig neu
- Protokolliert alle Vorgaenge im **Cron-Log** (einsehbar im Admin-Bereich)
- Nutzt **adaptives Polling** -- die Abfrageintervalle passen sich der tatsaechlichen Datenverfuegbarkeit an

Dazu kommt ein zentrales **Logging-System**, das Fehler und Warnungen aufzeichnet, dabei aber automatisch **sensible Daten wie Tokens oder API-Keys unkenntlich macht**. Sicherheit bis ins Detail.

---

## Auf dem Weg ins offizielle WordPress-Plugin-Verzeichnis

Aktuell befindet sich XTX Netatmo im **offiziellen Einreichungsprozess bei WordPress.org**. Das bedeutet: Das Plugin wird vom WordPress-Review-Team auf Herz und Nieren geprueft -- Coding-Standards, Sicherheit, Lizenzen, alles.

Um fuer diesen Prozess gewappnet zu sein, habe ich das Plugin gruendlich aufbereitet:

- Alle Funktionen nutzen **WordPress-native APIs** -- `wp_json_encode()` statt `json_encode()`, `wp_date()` statt `date_i18n()`, vorbereitete Datenbankabfragen mit `$wpdb->prepare()`
- **Vollstaendige Eingabe-Sanitization und Ausgabe-Escaping** -- kein Wert wird ungepreuft in die Datenbank geschrieben oder auf der Seite ausgegeben
- Keine externen JavaScript- oder CSS-Bibliotheken, die nicht benoetigt werden
- **GPL v2+ Lizenz**, kompatibel mit dem WordPress-Oekosystem

Sobald das Plugin im offiziellen Verzeichnis gelistet ist, kannst du es direkt ueber **Dashboard → Plugins → Installieren** suchen und mit einem Klick installieren -- inklusive automatischer Updates.

---

## Jetzt herunterladen: Aktuelle Version auf GitHub

Du moechtest nicht auf die WordPress.org-Freigabe warten? Kein Problem. **Die aktuelle Version 1.5.7 steht ab sofort auf GitHub zum Download bereit:**

### [→ XTX Netatmo auf GitHub herunterladen](https://github.com/Xyla1512/Netatmo)

Die Installation dauert keine zwei Minuten:

1. Lade die ZIP-Datei von der GitHub-Seite herunter
2. Gehe in deinem WordPress-Backend zu *Plugins → Installieren → Plugin hochladen*
3. Waehle die heruntergeladene ZIP-Datei aus und klicke auf *Jetzt installieren*
4. Aktiviere das Plugin und verbinde es unter *XTX Netatmo → Einstellungen* mit deinem Netatmo-Konto

Das ist alles. Die erste Synchronisation startet automatisch, und innerhalb weniger Minuten siehst du deine Wetterdaten auf deiner Website.

---

## Fuer wen ist XTX Netatmo das Richtige?

- **Hobby-Meteorologen und Wetter-Enthusiasten**, die ihre Station nicht nur fuer sich selbst betreiben, sondern ihre Messdaten der Oeffentlichkeit zugaenglich machen moechten
- **Blogger und Website-Betreiber**, die lokale Wetterdaten als einzigartiges Content-Element nutzen wollen -- nichts ist authentischer als Echtzeit-Messwerte von der eigenen Station
- **Vereine, Schulen und Bildungseinrichtungen**, die eine Wetterstation betreiben und die Daten auf ihrer Website oder fuer Projekte darstellen moechten
- **Smart-Home-Enthusiasten**, die Netatmo-Daten ueber die REST-API in ihre eigenen Systeme einbinden wollen
- **Entwickler**, die eine zuverlaessige, dokumentierte Schnittstelle zu Netatmo-Wetterdaten suchen

---

## Technische Anforderungen auf einen Blick

| Was du brauchst | Minimum |
|---|---|
| WordPress | Version 5.8 oder neuer |
| PHP | Version 7.4 oder neuer |
| Netatmo-Hardware | Mindestens die Basisstation (weitere Module optional) |
| Netatmo-API-Zugang | Client-ID und Client-Secret -- beides kostenlos ueber [dev.netatmo.com](https://dev.netatmo.com) erhaeltlich |

---

## Mein Fazit: Warum ich dieses Plugin entwickelt habe

Ich wollte ein Plugin, das einfach funktioniert. Das man einmal einrichtet und das dann zuverlaessig seine Arbeit tut -- Tag fuer Tag, ohne dass man sich darum kuemmern muss. Das sicher mit den Zugangsdaten umgeht, anstaendig aussieht, und genug Flexibilitaet bietet, um sich an unterschiedliche Websites anzupassen.

XTX Netatmo ist das Ergebnis. Mit **AES-256-GCM-Verschluesselung**, **automatischer OAuth2-Token-Verwaltung**, **ueber 130 konfigurierbaren Farben**, **vier Icon-Sets**, **zwei Vorhersage-Providern**, **interaktiven Jahresvergleichs-Charts** und einer **vollstaendigen REST-API** deckt es alles ab, was man sich von einem Netatmo-WordPress-Plugin wuenschen kann.

Probier es aus. Ich freue mich ueber Feedback, Feature-Wuensche und natuerlich auch ueber Bug-Reports -- alles gerne ueber die [GitHub-Issues-Seite](https://github.com/Xyla1512/Netatmo/issues).

**[→ XTX Netatmo jetzt kostenlos herunterladen](https://github.com/Xyla1512/Netatmo)**

---

*XTX Netatmo ist ein Open-Source-Projekt von Frank Neumann, lizenziert unter GPL v2+. Der Quellcode ist vollstaendig auf [GitHub](https://github.com/Xyla1512/Netatmo) einsehbar. Beitraege, Uebersetzungen und Feedback sind jederzeit willkommen.*
