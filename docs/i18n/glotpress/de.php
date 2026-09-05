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
 * Stand: 2026-09-05 — 110 Eintraege, alle uebersetzt.
 */
return [

// ── Short description ─────────────────────────────────────────────────────
1 => 'Verbindet sich mit der Netatmo-API, speichert alle Sensordaten lokal und zeigt Live-Dashboards, animierte Diagramme, Verlauf und Vorhersagen.',

// ── Plugin name ───────────────────────────────────────────────────────────
2 => 'XTX Integration for Netatmo',

// ── Found in description list item ────────────────────────────────────────
3 => '<code>[naws_weather_widget]</code> – Kompaktes Vorhersage-Widget für eine Seitenleiste (<code>days</code> 3 oder 5, <code>width</code> 250–500, <code>scheme</code> light, dark oder transparent)',
4 => '<code>[naws_sunpath]</code> – Die Sonne auf ihrem Bogen über der Station, mit Aufgang, Kulmination, Untergang und Tageslänge (<code>title</code>)',
5 => '<code>[naws_on_this_day]</code> – Dieser Kalendertag in jedem früheren Jahr, die Tagesrekorde markiert (<code>date</code>, <code>title</code>)',
6 => '<code>[naws_records]</code> – Fünfzehn Rekorde aus der Tagesübersicht mit ihren Daten, als Kacheln oder Tabelle (<code>year</code>, <code>records</code>, <code>layout</code>, <code>title</code>)',
7 => '<strong>14 Shortcodes</strong> – Dashboard, aktuelle Messwerte, Infoleiste, Einzelwert, berechneter Wert, Verlaufsdiagramme, Heatmap, Rekorde, dieser Tag in früheren Jahren, Sonnenbahn, Vorhersage, Tabelle, Widget, Wettersymbol',
8 => '<code>[naws_heatmap]</code> – Ein Jahr Außen-Tagesdurchschnittstemperatur als Kalenderraster, eine Kachel pro Tag, mit Jahreswahl (<code>year</code>, <code>title</code>, <code>legend</code>)',
9 => '<code>[naws_table]</code> – Messwerte als Tabelle über einen Zeitraum, gruppiert nach Stunde, Tag, Woche, Monat oder Jahr (<code>module_id</code>, <code>parameters</code>, <code>period</code>, <code>limit</code>, <code>group_by</code>, <code>title</code>)',
11 => '<code>[naws_weather_icon]</code> – Nur das animierte Symbol für den aktuellen Wetterzustand (<code>size</code>); gibt nichts aus, wenn der Zustand unbekannt ist',
12 => '<code>[naws_calc]</code> – Einzelner berechneter Wert (Taupunkt, gefühlte Temperatur, Sonnenaufgang, Mondphase, …); vollständige Liste auf der Shortcode-Seite im Backend',
13 => '<code>[naws_current]</code> – Aktuelle Messwerte eines oder aller Module als Kacheln oder Liste (<code>module_id</code>, <code>parameters</code>, <code>layout</code>, <code>title</code>)',
42 => '<strong>Verwendet für:</strong> Formatierung der Zeitachse in den Diagrammen. Dies ist der gebündelte Build, der date-fns enthält (ebenfalls MIT).',
43 => '<strong>Quellcode und Build-Werkzeuge:</strong> <a href="https://github.com/chartjs/chartjs-adapter-date-fns">https://github.com/chartjs/chartjs-adapter-date-fns</a> — mitgeliefert ist genau die Fassung <a href="https://github.com/chartjs/chartjs-adapter-date-fns/releases/tag/v3.0.0">v3.0.0</a>',
44 => '<strong>Datei:</strong> <code>assets/vendor/chartjs-adapter-date-fns.bundle.min.js</code>',
45 => '<strong>Verwendet für:</strong> Alle Diagramme — die 24-Stunden-Verläufe auf dem Live-Dashboard und die Jahresvergleiche im Verlauf',
46 => '<strong>Quellcode und Build-Werkzeuge:</strong> <a href="https://github.com/chartjs/Chart.js">https://github.com/chartjs/Chart.js</a> — mitgeliefert ist genau die Fassung <a href="https://github.com/chartjs/Chart.js/releases/tag/v4.5.1">v4.5.1</a>',
47 => '<strong>Website:</strong> <a href="https://www.chartjs.org">https://www.chartjs.org</a>',
48 => '<strong>Lizenz:</strong> MIT',
49 => '<strong>Datei:</strong> <code>assets/vendor/chart.umd.min.js</code>',
50 => '<strong>Hinweis:</strong> Kostenlose API, kein API-Schlüssel nötig. Die Nutzungsbedingungen von MET Norway verlangen, dass sich jeder Client zu erkennen gibt; Anfragen an diesen Dienst tragen deshalb einen User-Agent mit dem Namen des Plugins, seiner Version und deiner Seitenadresse — über diese Adresse würde MET Norway dich erreichen, bevor ein auffälliger Client eingeschränkt wird. Das geht ausschließlich an api.met.no und nur, solange Yr.no als Anbieter ausgewählt ist.',
51 => '<strong>Nutzungsbedingungen:</strong> <a href="https://developer.yr.no/doc/TermsOfService/">https://developer.yr.no/doc/TermsOfService/</a>',
52 => '<strong>Datenschutzerklärung:</strong> <a href="https://www.met.no/en/About-us/privacy">https://www.met.no/en/About-us/privacy</a>',
53 => '<strong>Wann:</strong> Wenn der Vorhersage-Shortcode angezeigt wird und Yr.no als Anbieter ausgewählt ist (3 Stunden zwischengespeichert)',
54 => '<strong>Zweck:</strong> Wettervorhersagedaten abrufen (optionaler Anbieter, in den Einstellungen wählbar)',
55 => '<strong>Dokumentation:</strong> <a href="https://open-meteo.com/en/docs/geocoding-api">https://open-meteo.com/en/docs/geocoding-api</a>',
56 => '<strong>Wann:</strong> Im manuellen Modus immer dann, wenn kein zwischengespeichertes Ergebnis vorliegt (7 Tage zwischengespeichert). Im automatischen Modus genau einmal — der ermittelte Name wird in den Plugin-Einstellungen gespeichert und nie wieder nachgeschlagen.',
57 => '<strong>Übermittelte Daten:</strong> Im Standortmodus „manuell“ der Ortsname oder die Postleitzahl, die in den Plugin-Einstellungen eingetragen sind. Im Modus „automatisch“ Breiten- und Längengrad deiner Wetterstation, auf zwei Nachkommastellen gerundet, um den Namen des nächstgelegenen Ortes nachzuschlagen.',
58 => '<strong>Zweck:</strong> Einen Ort in Koordinaten übersetzen und Koordinaten in einen Ortsnamen für die Überschrift der Vorhersage',
59 => '<strong>Hinweis:</strong> Open-Meteo ist eine kostenlose, quelloffene Wetter-API. Weder API-Schlüssel noch Registrierung sind nötig.',
60 => '<strong>Bedingungen und Datenschutz:</strong> <a href="https://open-meteo.com/en/terms">https://open-meteo.com/en/terms</a>',
61 => '<strong>Wann:</strong> Wenn der Vorhersage-Shortcode angezeigt wird (3 Stunden zwischengespeichert)',
62 => '<strong>Übermittelte Daten:</strong> Breiten- und Längengrad deiner Wetterstation',
63 => '<strong>Zweck:</strong> Wettervorhersagedaten anhand der Stationskoordinaten abrufen (Standardanbieter)',
64 => '<strong>Datenschutzerklärung:</strong> <a href="https://legals.netatmo.com/?goto=privacy">https://legals.netatmo.com/?goto=privacy</a>',
65 => '<strong>Nutzungsbedingungen:</strong> <a href="https://dev.netatmo.com/legal">https://dev.netatmo.com/legal</a>',
66 => '<strong>Wann:</strong> Bei der ersten Anmeldung, bei jedem automatischen Abgleich, bei jeder Token-Erneuerung und während ein historischer Import läuft',
67 => '<strong>Übermittelte Daten:</strong> Client-ID und Client-Secret der von dir erstellten Netatmo-Anwendung, im Austausch gegen ein Access-Token; danach mit jeder Anfrage das Access- oder Refresh-Token sowie die Stations- und Modul-IDs, deren Messwerte angefragt werden',
68 => '<strong>Zweck:</strong> Authentifizierung über OAuth2, Abruf von Sensormesswerten und Stationsdaten',
69 => '<code>[naws_forecast]</code> – Mehrtägige Wettervorhersage',
70 => '<code>[naws_history]</code> – Jahresvergleichsdiagramme (unterstützt den Parameter <code>year</code>)',
71 => '<code>[naws_value]</code> – Einzelner Sensorwert im Fließtext',
72 => '<code>[naws_infobar]</code> – Astronomieleiste mit Sonnenaufgang, Mondphase und gefühlter Temperatur',
73 => '<code>[naws_live]</code> – Live-Sensorkacheln mit 24-Stunden-Verläufen und Vorhersage',
74 => '<strong>NAModule4</strong> – Zusätzliches Innenmodul (Temperatur, Luftfeuchte, CO2)',
75 => '<strong>NAModule3</strong> – Regenmesser (stündlich, täglich, rollende 24 Stunden)',
76 => '<strong>NAModule2</strong> – Wind (Geschwindigkeit, Richtung, Böen)',
77 => '<strong>NAModule1</strong> – Außenmodul (Temperatur, Luftfeuchte)',
78 => '<strong>NAMain</strong> – Basisstation (Temperatur, Luftfeuchte, CO2, Lärm, Luftdruck)',
79 => '<strong>4 Symbolsätze</strong> – Emoji, Outline, Filled und Minimal, mit Farbsteuerung je Sensor',
80 => '<strong>Über 130 einstellbare Farben</strong> – Vollständige Anpassung der Darstellung mit Live-Vorschau',
81 => '<strong>Mobile First und responsiv</strong> – Alle Ansichten für Smartphone, Tablet und Desktop optimiert',
82 => '<strong>Export / Import</strong> – Vollständige Sicherung und Wiederherstellung von Wetterdaten, Modulen und Einstellungen',
83 => '<strong>Mehrsprachig</strong> – Vollständige Oberfläche auf Deutsch, Englisch und Norwegisch',
84 => '<strong>Einstellbare Einheiten</strong> – C/F, mm/Zoll, mbar/inHg/mmHg, km/h/m/s/mph/kn',
85 => '<strong>Verschlüsselte Speicherung</strong> – Alle Zugangsdaten (OAuth-Token, Client-Secret, API-Schlüssel) liegen AES-256-GCM-verschlüsselt in der Datenbank',
86 => '<strong>REST-API</strong> – Nur lesende JSON-API mit Schlüssel-Authentifizierung und Ratenbegrenzung für externe Werkzeuge (Google Charts, Grafana usw.)',
87 => '<strong>Wettervorhersage</strong> – 5-Tage-Vorhersage anhand der Stationskoordinaten über Open-Meteo oder Yr.no',
88 => '<strong>Verlaufsdiagramme</strong> – Jahresvergleich für Temperatur, Luftdruck und Niederschlag mit interaktiver Legende',
89 => '<strong>Abgeleitete Wetterdaten</strong> – Gefühlte Temperatur, Hitzeindex, Taupunkt, Windchill',
90 => '<strong>Astronomie</strong> – Sonnenauf- und -untergang, Mondphase mit Beleuchtungsgrad, nächster Vollmond',
91 => '<strong>Live-Dashboard</strong> – Sensorkacheln in Echtzeit mit animierten Zählern, 24-Stunden-Verläufen, Luftdrucktendenz, Windkompass und CO2-Luftqualitätsstufen',
92 => '<strong>Vollständige Netatmo-Integration</strong> – OAuth2-Anmeldung, automatischer Abgleich, alle Modultypen unterstützt (Basis, Außen, Wind, Regen, Innen)',

// ── Found in faq paragraph ────────────────────────────────────────────────
10 => 'Ja. Jeder Endpunkt ist nur lesend, ratenbegrenzt und verlangt einen API-Schlüssel, den du im Backend erzeugst. Der Schlüssel wird ausschließlich im Header X-NAWS-Key angenommen — nie als Query-Parameter, damit er nicht in Zugriffsprotokollen, im Referer-Header, im Browserverlauf oder in einem Proxy dazwischen landet.',
14 => 'Open-Meteo (weltweit, Standard) und Yr.no / MET Norway (auf Nordeuropa optimiert). Beide sind kostenlos und brauchen keinen API-Schlüssel.',
15 => 'Ja. Über Export/Import lädst du Wetterdaten, Modulkonfiguration und alle Einstellungen als JSON herunter. Ideal für den Umzug auf eine neue WordPress-Installation.',
16 => 'Ja. Die Darstellungsseite bietet über 130 einstellbare Farben mit Live-Vorschau, 4 Symbolsätze, Farben je Sensor, Diagramm-Themes und Paletten für den Jahresvergleich.',
17 => 'Alle sensiblen Daten (OAuth-Token, Client-ID, Client-Secret, API-Schlüssel) werden vor dem Speichern in der Datenbank mit AES-256-GCM verschlüsselt.',
18 => 'Ja. Das Plugin bringt einen abschnittsweise arbeitenden Importer für historische Daten mit, der die getmeasure-API von Netatmo abfragt, ohne an die Ratenbegrenzung zu stoßen.',
19 => 'Netatmo-Sensoren senden alle 5 Minuten. Das Abrufintervall des Plugins ist einstellbar (5–1440 Minuten). Der Nachtmodus reduziert die Abfragen zwischen 23:00 und 06:00 Uhr.',
20 => 'Öffne <a href="https://dev.netatmo.com">dev.netatmo.com</a>, melde dich mit deinem Netatmo-Konto an, erstelle eine neue Anwendung und kopiere Client-ID und Client-Secret.',

// ── Found in faq header ───────────────────────────────────────────────────
21 => 'Welche Vorhersage-Anbieter werden unterstützt?',
22 => 'Kann ich meine Wetterdaten sichern?',
23 => 'Kann ich das Aussehen anpassen?',
24 => 'Sind meine Netatmo-Zugangsdaten sicher?',
25 => 'Ist die REST-API sicher?',
26 => 'Kann ich historische Daten importieren?',
27 => 'Wie oft werden die Daten aktualisiert?',
28 => 'Wie bekomme ich Netatmo-API-Zugangsdaten?',

// ── Found in installation list item ───────────────────────────────────────
29 => 'Die Daten werden ab jetzt automatisch abgeglichen – füge Shortcodes in eine beliebige Seite ein',
30 => 'Klicke auf „Mit Netatmo verbinden“ und erteile die Berechtigung',
31 => 'Trage in deiner Netatmo-Anwendung als Redirect-URI ein: <code>https://yoursite.com/wp-admin/admin.php?page=naws-settings</code>',
32 => 'Trage Client-ID und Client-Secret ein',
33 => 'Erstelle unter <a href="https://dev.netatmo.com">dev.netatmo.com</a> eine Netatmo-Entwickleranwendung',
34 => 'Gehe zu <strong>XTX Netatmo &gt; Einstellungen</strong>',
35 => 'Aktiviere das Plugin im WordPress-Adminbereich unter &gt; Plugins',
36 => 'Lade den Ordner <code>xtx-integration-for-netatmo</code> nach <code>/wp-content/plugins/</code> hoch',

// ── Found in description paragraph ────────────────────────────────────────
37 => 'Weiterer Fremdcode ist nicht enthalten. Keine Bibliothek wird von einem CDN geladen; alles kommt von deiner eigenen Installation. Bibliotheken, die WordPress selbst mitbringt, werden von WordPress bezogen und nicht mitgeliefert.',
38 => 'Diesem Plugin liegen zwei JavaScript-Bibliotheken bei, beide unter der MIT-Lizenz, die GPL-kompatibel ist. Sie werden als minifizierte Distributions-Builds ausgeliefert; der unminifizierte Quellcode und die Build-Werkzeuge sind jeweils über die Links unten erreichbar.',
39 => 'Dieses Plugin erhebt und übermittelt keine personenbezogenen Daten (Namen, E-Mail-Adressen, IP-Adressen). Alle Sensordaten liegen ausschließlich in deiner lokalen WordPress-Datenbank.',
40 => 'Dieses Plugin verbindet sich mit den folgenden externen Diensten:',
41 => '<strong>XTX Integration for Netatmo</strong> verbindet deine Netatmo-Hardware mit WordPress. Es liest alle Sensordaten über die offizielle Netatmo-API, speichert die Messwerte in deiner lokalen Datenbank und zeigt sie in ansehnlichen Live-Dashboards, animierten Diagrammen und Wettervorhersagen.',

// ── Found in description header ───────────────────────────────────────────
93 => 'chartjs-adapter-date-fns 3.0.0',
94 => 'Chart.js 4.5.1',
95 => 'Bibliotheken von Drittanbietern',
96 => 'Yr.no / MET Norway API (api.met.no)',
97 => 'Open-Meteo Geocoding API (geocoding-api.open-meteo.com)',
98 => 'Open-Meteo API (api.open-meteo.com)',
99 => 'Netatmo API (api.netatmo.com)',
100 => 'Datenschutz &amp; externe Dienste',
101 => 'Shortcodes',
102 => 'Unterstützte Module',
103 => 'Wichtigste Funktionen',

// ── Screenshot description ────────────────────────────────────────────────
104 => 'Export-/Import-Seite für Sicherungen',
105 => 'Darstellungsseite mit Live-Vorschau der Farben',
106 => 'Wettervorhersage-Widget',
107 => 'REST-API-Dokumentation im Adminbereich',
108 => 'Admin-Einstellungsseite mit Status der Netatmo-Verbindung',
109 => 'Jahresvergleichsdiagramme für Temperatur und Niederschlag',
110 => 'Live-Dashboard mit Sensorkacheln und 24-Stunden-Verläufen',

];
