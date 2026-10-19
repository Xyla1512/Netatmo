<?php
/**
 * Deutsche Uebersetzung der Plugin-Seite (Stable Readme), ohne Changelog.
 *
 * ERZEUGT von pull_de.php aus readme-de.po (GlotPress-Export). Nicht von
 * Hand nummerieren: Schluessel ist die laufende Nummer der Nicht-Changelog-
 * Eintraege im Export, so wie dump.php sie zaehlt und build.php sie liest.
 * Aendert sich die Reihenfolge im Export, aendern sich die Nummern hier.
 *
 * Locale de_DE (Standard) = Duzen. Die formelle Variante ist ein eigenes
 * Locale (de_DE_formal) und ein eigener Uebersetzungssatz.
 *
 * Die Beispieladresse bleibt "yoursite.com": GlotPress prueft, ob die Links
 * in Original und Uebersetzung uebereinstimmen, und warnt sonst.
 *
 * Stand: 2026-10-19 — 131 Eintraege, alle uebersetzt.
 */
return [

// ── Short description ─────────────────────────────────────────────────────
1 => 'Verbindet sich mit der Netatmo-API, speichert alle Sensordaten lokal und zeigt Live-Dashboards, animierte Diagramme, Verlauf und Vorhersagen.',

// ── Plugin name ───────────────────────────────────────────────────────────
2 => 'XTX Integration for Netatmo',

// ── Found in description list item ────────────────────────────────────────
3 => '<strong>Bedingungen und Datenschutz:</strong> <a href="https://operations.osmfoundation.org/policies/tiles/">https://operations.osmfoundation.org/policies/tiles/</a>, <a href="https://osmfoundation.org/wiki/Privacy_Policy">https://osmfoundation.org/wiki/Privacy_Policy</a>',
4 => '<strong>Wann:</strong> Nur wenn „Regenradar erlauben“ eingeschaltet ist, beim ersten Mal, wenn eine Seite ein Radar mit diesem Kartenausschnitt zeigt',
5 => '<strong>Übermittelte Daten:</strong> Die Kachelnummern des Kartenausschnitts um deine Station. Einmal je Kartenausschnitt abgerufen und auf deinem Server aufbewahrt.',
6 => '<strong>Zweck:</strong> Die Hintergrundkarte des Regenradars <code>[naws_radar]</code>',
7 => '<strong>Bedingungen und Datenschutz:</strong> <a href="https://www.rainviewer.com/terms.html">https://www.rainviewer.com/terms.html</a>, <a href="https://www.rainviewer.com/privacy.html">https://www.rainviewer.com/privacy.html</a>',
8 => '<strong>Übermittelte Daten:</strong> Die Kachelnummern des Kartenausschnitts um deine Station und die Uhrzeit jedes Bildes. Keine Besucherdaten: Die Bilder holt dein Server, ausgeliefert werden sie von deiner Website.',
9 => '<strong>Zweck:</strong> Radarbilder für das Regenradar <code>[naws_radar]</code> außerhalb Deutschlands',
10 => '<strong>Bedingungen und Datenschutz:</strong> <a href="https://www.dwd.de/EN/service/copyright/copyright_node.html">https://www.dwd.de/EN/service/copyright/copyright_node.html</a>, <a href="https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html">https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html</a>',
11 => '<strong>Wann:</strong> Nur wenn „Regenradar erlauben“ eingeschaltet ist, nachdem eine Seite das Radar gezeigt hat, höchstens alle 15 Minuten',
12 => '<strong>Übermittelte Daten:</strong> Der Kartenausschnitt um deine Station (als Begrenzungsrechteck) und die Uhrzeit jedes Bildes. Keine Besucherdaten: Die Bilder holt dein Server, ausgeliefert werden sie von deiner Website.',
13 => '<strong>Zweck:</strong> Radarbilder (gemessen und zwei Stunden Vorschau) für das Regenradar <code>[naws_radar]</code>, wenn die Station in Deutschland steht',
14 => '<code>[naws_radar]</code> – Animiertes Regenradar um die Station: in Deutschland vom Deutschen Wetterdienst (die letzte Stunde und zwei Stunden Vorschau), anderswo von RainViewer, auf einer grauen OpenStreetMap-Karte. Die Bilder holt dein Server, ausgeliefert werden sie von deiner Website (<code>width</code>, <code>height</code>, <code>km</code>, <code>source</code>). Muss unter Einstellungen → Regenradar eingeschaltet werden.',
18 => '<code>[naws_sparkline_tiles]</code> – Die Sparkline-Karten nebeneinander in einem Raster, das von selbst umbricht: ab Werk Temperatur, Luftfeuchte, Luftdruck, Wind, Regen und CO2 (<code>params</code>, <code>hours</code>)',
19 => '<code>[naws_sparkline]</code> – Eine Kurve so groß wie ein Wort: die Rohmessungen der letzten Stunden oder eine Spalte der Tagesübersicht, Regen als Balken, das Band zwischen Tagestief und Tageshoch, Uhrzeit und Wert beim Überfahren; auch als Karte oder Monatsblock (<code>param</code>, <code>hours</code>, <code>days</code>, <code>module</code>, <code>width</code>, <code>height</code>, <code>show</code>, <code>type</code>, <code>band</code>, <code>layout</code>, <code>title</code>)',
22 => '<strong>E-Mail-Benachrichtigungen</strong> – Batterie, Funk und WLAN, Station offline, gescheiterte Abrufe, Frost, Hitze, Böen, Regen, Regenbeginn, CO₂; Schwellen je Regel, eine Mail je Zustandswechsel und eine Entwarnung, wenn der Zustand endet, dazu ein Generalschalter',
23 => '<code>[naws_windrose]</code> – Woher der Wind kommt und wie kräftig, als Rose mit 16 oder 8 Richtungen, nach Beaufort-Klassen gestapelt, mit Zeitraum-Umschaltung (<code>period</code>, <code>from</code>, <code>to</code>, <code>measure</code>, <code>sectors</code>, <code>show</code>, <code>switcher</code>, <code>size</code>, <code>title</code>)',
24 => '<code>[naws_weather_widget]</code> – Kompaktes Vorhersage-Widget für eine Seitenleiste (<code>days</code> 3 oder 5, <code>width</code> 250–500, <code>scheme</code> light, dark oder transparent, <code>sparklines</code> 0 oder 1)',
25 => '<code>[naws_sunpath]</code> – Die Sonne auf ihrem Bogen über der Station, mit Aufgang, Kulmination, Untergang und Tageslänge (<code>title</code>)',
26 => '<code>[naws_on_this_day]</code> – Dieser Kalendertag in jedem früheren Jahr, die Tagesrekorde markiert (<code>date</code>, <code>title</code>)',
27 => '<code>[naws_records]</code> – Fünfzehn Rekorde aus der Tagesübersicht mit ihren Daten, als Kacheln oder Tabelle (<code>year</code>, <code>records</code>, <code>layout</code>, <code>title</code>)',
28 => '<strong>18 Shortcodes</strong> – Dashboard, aktuelle Werte, Infoleiste, Einzelwert, berechneter Wert, Sparkline, Sparkline-Kacheln, Verlaufsdiagramme, Heatmap, Rekorde, dieser Tag in früheren Jahren, Sonnenbahn, Windrose, Vorhersage, Regenradar, Tabelle, Widget, Wettersymbol',
29 => '<code>[naws_heatmap]</code> – Ein Jahr Außen-Tagesdurchschnittstemperatur als Kalenderraster, eine Kachel pro Tag, mit Jahreswahl (<code>year</code>, <code>title</code>, <code>legend</code>)',
30 => '<code>[naws_table]</code> – Messwerte als Tabelle über einen Zeitraum, gruppiert nach Stunde, Tag, Woche, Monat oder Jahr (<code>module_id</code>, <code>parameters</code>, <code>period</code>, <code>limit</code>, <code>group_by</code>, <code>title</code>)',
32 => '<code>[naws_weather_icon]</code> – Nur das animierte Symbol für den aktuellen Wetterzustand (<code>size</code>); gibt nichts aus, wenn der Zustand unbekannt ist',
33 => '<code>[naws_calc]</code> – Einzelner berechneter Wert (Taupunkt, gefühlte Temperatur, Sonnenaufgang, Mondphase, …); vollständige Liste auf der Shortcode-Seite im Backend',
34 => '<code>[naws_current]</code> – Aktuelle Messwerte eines oder aller Module als Kacheln oder Liste (<code>module_id</code>, <code>parameters</code>, <code>layout</code>, <code>title</code>)',
63 => '<strong>Verwendet für:</strong> Formatierung der Zeitachse in den Diagrammen. Dies ist der gebündelte Build, der date-fns enthält (ebenfalls MIT).',
64 => '<strong>Quellcode und Build-Werkzeuge:</strong> <a href="https://github.com/chartjs/chartjs-adapter-date-fns">https://github.com/chartjs/chartjs-adapter-date-fns</a> — mitgeliefert ist genau die Fassung <a href="https://github.com/chartjs/chartjs-adapter-date-fns/releases/tag/v3.0.0">v3.0.0</a>',
65 => '<strong>Datei:</strong> <code>assets/vendor/chartjs-adapter-date-fns.bundle.min.js</code>',
66 => '<strong>Verwendet für:</strong> Alle Diagramme — die 24-Stunden-Verläufe auf dem Live-Dashboard und die Jahresvergleiche im Verlauf',
67 => '<strong>Quellcode und Build-Werkzeuge:</strong> <a href="https://github.com/chartjs/Chart.js">https://github.com/chartjs/Chart.js</a> — mitgeliefert ist genau die Fassung <a href="https://github.com/chartjs/Chart.js/releases/tag/v4.5.1">v4.5.1</a>',
68 => '<strong>Website:</strong> <a href="https://www.chartjs.org">https://www.chartjs.org</a>',
69 => '<strong>Lizenz:</strong> MIT',
70 => '<strong>Datei:</strong> <code>assets/vendor/chart.umd.min.js</code>',
71 => '<strong>Hinweis:</strong> Kostenlose API, kein API-Schlüssel nötig. Die Nutzungsbedingungen von MET Norway verlangen, dass sich jeder Client zu erkennen gibt; Anfragen an diesen Dienst tragen deshalb einen User-Agent mit dem Namen des Plugins, seiner Version und deiner Seitenadresse — über diese Adresse würde MET Norway dich erreichen, bevor ein auffälliger Client eingeschränkt wird. Das geht ausschließlich an api.met.no und nur, solange Yr.no als Anbieter ausgewählt ist.',
72 => '<strong>Nutzungsbedingungen:</strong> <a href="https://developer.yr.no/doc/TermsOfService/">https://developer.yr.no/doc/TermsOfService/</a>',
73 => '<strong>Datenschutzerklärung:</strong> <a href="https://www.met.no/en/About-us/privacy">https://www.met.no/en/About-us/privacy</a>',
74 => '<strong>Wann:</strong> Wenn der Vorhersage-Shortcode angezeigt wird und Yr.no als Anbieter ausgewählt ist (3 Stunden zwischengespeichert)',
75 => '<strong>Zweck:</strong> Wettervorhersagedaten abrufen (optionaler Anbieter, in den Einstellungen wählbar)',
76 => '<strong>Dokumentation:</strong> <a href="https://open-meteo.com/en/docs/geocoding-api">https://open-meteo.com/en/docs/geocoding-api</a>',
77 => '<strong>Wann:</strong> Im manuellen Modus immer dann, wenn kein zwischengespeichertes Ergebnis vorliegt (7 Tage zwischengespeichert). Im automatischen Modus genau einmal — der ermittelte Name wird in den Plugin-Einstellungen gespeichert und nie wieder nachgeschlagen.',
78 => '<strong>Übermittelte Daten:</strong> Im Standortmodus „manuell“ der Ortsname oder die Postleitzahl, die in den Plugin-Einstellungen eingetragen sind. Im Modus „automatisch“ Breiten- und Längengrad deiner Wetterstation, auf zwei Nachkommastellen gerundet, um den Namen des nächstgelegenen Ortes nachzuschlagen.',
79 => '<strong>Zweck:</strong> Einen Ort in Koordinaten übersetzen und Koordinaten in einen Ortsnamen für die Überschrift der Vorhersage',
80 => '<strong>Hinweis:</strong> Open-Meteo ist eine kostenlose, quelloffene Wetter-API. Weder API-Schlüssel noch Registrierung sind nötig.',
81 => '<strong>Bedingungen und Datenschutz:</strong> <a href="https://open-meteo.com/en/terms">https://open-meteo.com/en/terms</a>',
82 => '<strong>Wann:</strong> Wenn der Vorhersage-Shortcode angezeigt wird (3 Stunden zwischengespeichert)',
83 => '<strong>Übermittelte Daten:</strong> Breiten- und Längengrad deiner Wetterstation',
84 => '<strong>Zweck:</strong> Wettervorhersagedaten anhand der Stationskoordinaten abrufen (Standardanbieter)',
85 => '<strong>Datenschutzerklärung:</strong> <a href="https://legals.netatmo.com/?goto=privacy">https://legals.netatmo.com/?goto=privacy</a>',
86 => '<strong>Nutzungsbedingungen:</strong> <a href="https://dev.netatmo.com/legal">https://dev.netatmo.com/legal</a>',
87 => '<strong>Wann:</strong> Bei der ersten Anmeldung, bei jedem automatischen Abgleich, bei jeder Token-Erneuerung und während ein historischer Import läuft',
88 => '<strong>Übermittelte Daten:</strong> Client-ID und Client-Secret der von dir erstellten Netatmo-Anwendung, im Austausch gegen ein Access-Token; danach mit jeder Anfrage das Access- oder Refresh-Token sowie die Stations- und Modul-IDs, deren Messwerte angefragt werden',
89 => '<strong>Zweck:</strong> Authentifizierung über OAuth2, Abruf von Sensormesswerten und Stationsdaten',
90 => '<code>[naws_forecast]</code> – Mehrtägige Wettervorhersage',
91 => '<code>[naws_history]</code> – Jahresvergleichsdiagramme (unterstützt den Parameter <code>year</code>)',
92 => '<code>[naws_value]</code> – Einzelner Sensorwert im Fließtext',
93 => '<code>[naws_infobar]</code> – Astronomieleiste mit Sonnenaufgang, Mondphase und gefühlter Temperatur',
94 => '<code>[naws_live]</code> – Live-Sensorkacheln mit 24-Stunden-Verläufen und Windrose',
95 => '<strong>NAModule4</strong> – Zusätzliches Innenmodul (Temperatur, Luftfeuchte, CO2)',
96 => '<strong>NAModule3</strong> – Regenmesser (stündlich, täglich, rollende 24 Stunden)',
97 => '<strong>NAModule2</strong> – Wind (Geschwindigkeit, Richtung, Böen)',
98 => '<strong>NAModule1</strong> – Außenmodul (Temperatur, Luftfeuchte)',
99 => '<strong>NAMain</strong> – Basisstation (Temperatur, Luftfeuchte, CO2, Lärm, Luftdruck)',
100 => '<strong>4 Symbolsätze</strong> – Emoji, Outline, Filled und Minimal, mit Farbsteuerung je Sensor',
101 => '<strong>Über 130 einstellbare Farben</strong> – Vollständige Anpassung der Darstellung mit Live-Vorschau',
102 => '<strong>Mobile First und responsiv</strong> – Alle Ansichten für Smartphone, Tablet und Desktop optimiert',
103 => '<strong>Export / Import</strong> – Vollständige Sicherung und Wiederherstellung von Wetterdaten, Modulen und Einstellungen',
104 => '<strong>Mehrsprachig</strong> – Vollständige Oberfläche auf Deutsch, Englisch und Norwegisch',
105 => '<strong>Einstellbare Einheiten</strong> – C/F, mm/Zoll, mbar/inHg/mmHg, km/h/m/s/mph/kn',
106 => '<strong>Verschlüsselte Speicherung</strong> – Alle Zugangsdaten (OAuth-Token, Client-Secret, API-Schlüssel) liegen AES-256-GCM-verschlüsselt in der Datenbank',
107 => '<strong>REST-API</strong> – Nur lesende JSON-API mit Schlüssel-Authentifizierung und Ratenbegrenzung für externe Werkzeuge (Google Charts, Grafana usw.)',
108 => '<strong>Wettervorhersage</strong> – 5-Tage-Vorhersage anhand der Stationskoordinaten über Open-Meteo oder Yr.no',
109 => '<strong>Verlaufsdiagramme</strong> – Jahresvergleich für Temperatur, Luftdruck und Niederschlag mit interaktiver Legende',
110 => '<strong>Abgeleitete Wetterdaten</strong> – Gefühlte Temperatur, Hitzeindex, Taupunkt, Windchill',
111 => '<strong>Astronomie</strong> – Sonnenauf- und -untergang, Mondphase mit Beleuchtungsgrad, nächster Vollmond',
112 => '<strong>Live-Dashboard</strong> – Sensorkacheln in Echtzeit mit animierten Zählern, 24-Stunden-Verläufen, Luftdrucktendenz, Windkompass und CO2-Luftqualitätsstufen',
113 => '<strong>Vollständige Netatmo-Integration</strong> – OAuth2-Anmeldung, automatischer Abgleich, alle Modultypen unterstützt (Basis, Außen, Wind, Regen, Innen)',

// ── Found in description header ───────────────────────────────────────────
15 => 'OpenStreetMap-Kacheln (tile.openstreetmap.org)',
16 => 'RainViewer (api.rainviewer.com, tilecache.rainviewer.com)',
17 => 'Deutscher Wetterdienst (maps.dwd.de)',
114 => 'chartjs-adapter-date-fns 3.0.0',
115 => 'Chart.js 4.5.1',
116 => 'Bibliotheken von Drittanbietern',
117 => 'Yr.no / MET Norway API (api.met.no)',
118 => 'Open-Meteo Geocoding API (geocoding-api.open-meteo.com)',
119 => 'Open-Meteo API (api.open-meteo.com)',
120 => 'Netatmo API (api.netatmo.com)',
121 => 'Datenschutz &amp; externe Dienste',
122 => 'Shortcodes',
123 => 'Unterstützte Module',
124 => 'Wichtigste Funktionen',

// ── Found in faq paragraph ────────────────────────────────────────────────
20 => 'Öffne XTX Netatmo → Benachrichtigungen und drücke „Testmail senden". Meldet die Seite, dass die Mail nicht gesendet werden konnte, verschickt dein Server gar keine Mails: ein SMTP-Plugin oder dein Hoster behebt das, nicht das Plugin. Kommt die Testmail an, aber keine Benachrichtigung, prüfe, ob Generalschalter und Regel eingeschaltet sind, und sieh ins Protokoll auf derselben Seite. Benachrichtigungen gehen nur bei einem Zustandswechsel raus; eine Batterie, die schon vor dem Einschalten der Regel schwach war, wird beim nächsten Abruf gemeldet, danach nicht mehr.',
31 => 'Ja. Jeder Endpunkt ist nur lesend, ratenbegrenzt und verlangt einen API-Schlüssel, den du im Backend erzeugst. Der Schlüssel wird ausschließlich im Header X-NAWS-Key angenommen — nie als Query-Parameter, damit er nicht in Zugriffsprotokollen, im Referer-Header, im Browserverlauf oder in einem Proxy dazwischen landet.',
35 => 'Open-Meteo (weltweit, Standard) und Yr.no / MET Norway (auf Nordeuropa optimiert). Beide sind kostenlos und brauchen keinen API-Schlüssel.',
36 => 'Ja. Über Export/Import lädst du Wetterdaten, Modulkonfiguration und alle Einstellungen als JSON herunter. Ideal für den Umzug auf eine neue WordPress-Installation.',
37 => 'Ja. Die Darstellungsseite bietet über 130 einstellbare Farben mit Live-Vorschau, 4 Symbolsätze, Farben je Sensor, Diagramm-Themes und Paletten für den Jahresvergleich.',
38 => 'Alle sensiblen Daten (OAuth-Token, Client-ID, Client-Secret, API-Schlüssel) werden vor dem Speichern in der Datenbank mit AES-256-GCM verschlüsselt.',
39 => 'Ja. Das Plugin bringt einen abschnittsweise arbeitenden Importer für historische Daten mit, der die getmeasure-API von Netatmo abfragt, ohne an die Ratenbegrenzung zu stoßen.',
40 => 'Netatmo-Sensoren senden alle 5 Minuten. Das Abrufintervall des Plugins ist einstellbar (5–1440 Minuten). Der Nachtmodus reduziert die Abfragen zwischen 23:00 und 06:00 Uhr.',
41 => 'Öffne <a href="https://dev.netatmo.com">dev.netatmo.com</a>, melde dich mit deinem Netatmo-Konto an, erstelle eine neue Anwendung und kopiere Client-ID und Client-Secret.',

// ── Found in faq header ───────────────────────────────────────────────────
21 => 'Ich bekomme keine Benachrichtigungs-Mails',
42 => 'Welche Vorhersage-Anbieter werden unterstützt?',
43 => 'Kann ich meine Wetterdaten sichern?',
44 => 'Kann ich das Aussehen anpassen?',
45 => 'Sind meine Netatmo-Zugangsdaten sicher?',
46 => 'Ist die REST-API sicher?',
47 => 'Kann ich historische Daten importieren?',
48 => 'Wie oft werden die Daten aktualisiert?',
49 => 'Wie bekomme ich Netatmo-API-Zugangsdaten?',

// ── Found in installation list item ───────────────────────────────────────
50 => 'Die Daten werden ab jetzt automatisch abgeglichen – füge Shortcodes in eine beliebige Seite ein',
51 => 'Klicke auf „Mit Netatmo verbinden“ und erteile die Berechtigung',
52 => 'Trage in deiner Netatmo-Anwendung als Redirect-URI ein: <code>https://yoursite.com/wp-admin/admin.php?page=naws-settings</code>',
53 => 'Trage Client-ID und Client-Secret ein',
54 => 'Erstelle unter <a href="https://dev.netatmo.com">dev.netatmo.com</a> eine Netatmo-Entwickleranwendung',
55 => 'Gehe zu <strong>XTX Netatmo &gt; Einstellungen</strong>',
56 => 'Aktiviere das Plugin im WordPress-Adminbereich unter &gt; Plugins',
57 => 'Lade den Ordner <code>xtx-integration-for-netatmo</code> nach <code>/wp-content/plugins/</code> hoch',

// ── Found in description paragraph ────────────────────────────────────────
58 => 'Weiterer Fremdcode ist nicht enthalten. Keine Bibliothek wird von einem CDN geladen; alles kommt von deiner eigenen Installation. Bibliotheken, die WordPress selbst mitbringt, werden von WordPress bezogen und nicht mitgeliefert.',
59 => 'Diesem Plugin liegen zwei JavaScript-Bibliotheken bei, beide unter der MIT-Lizenz, die GPL-kompatibel ist. Sie werden als minifizierte Distributions-Builds ausgeliefert; der unminifizierte Quellcode und die Build-Werkzeuge sind jeweils über die Links unten erreichbar.',
60 => 'Dieses Plugin erhebt und übermittelt keine personenbezogenen Daten (Namen, E-Mail-Adressen, IP-Adressen). Alle Sensordaten liegen ausschließlich in deiner lokalen WordPress-Datenbank.',
61 => 'Dieses Plugin verbindet sich mit den folgenden externen Diensten:',
62 => '<strong>XTX Integration for Netatmo</strong> verbindet deine Netatmo-Hardware mit WordPress. Es liest alle Sensordaten über die offizielle Netatmo-API, speichert die Messwerte in deiner lokalen Datenbank und zeigt sie in ansehnlichen Live-Dashboards, animierten Diagrammen und Wettervorhersagen.',

// ── Screenshot description ────────────────────────────────────────────────
125 => 'Export-/Import-Seite für Sicherungen',
126 => 'Darstellungsseite mit Live-Vorschau der Farben',
127 => 'Wettervorhersage-Widget',
128 => 'REST-API-Dokumentation im Adminbereich',
129 => 'Admin-Einstellungsseite mit Status der Netatmo-Verbindung',
130 => 'Jahresvergleichsdiagramme für Temperatur und Niederschlag',
131 => 'Live-Dashboard mit Sensorkacheln und 24-Stunden-Verläufen',

];
