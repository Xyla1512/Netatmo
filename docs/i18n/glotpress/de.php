<?php
/**
 * Deutsche Uebersetzung der Plugin-Seite (Stable Readme), ohne Changelog.
 *
 * Locale de_DE (Standard) = Duzen. Die formelle Variante ist ein eigenes
 * Locale (de_DE_formal) und ein eigener Uebersetzungssatz.
 *
 * Schluessel ist die laufende Nummer aus dump.php. build.php prueft die
 * Zuordnung stichprobenartig gegen den Originaltext, damit eine
 * Verschiebung nicht stillschweigend durchgeht.
 */
return [

// ── Kopf ────────────────────────────────────────────────────────────────
1 => 'Verbindet sich mit der Netatmo-API, speichert alle Sensordaten lokal und zeigt Live-Dashboards, animierte Diagramme, Verlauf und Vorhersagen.',
2 => 'XTX Integration for Netatmo',

// ── FAQ: Antworten ──────────────────────────────────────────────────────
3 => 'Ja. Jeder Endpunkt ist nur lesend, ratenbegrenzt und verlangt einen API-Schlüssel, den du im Backend erzeugst. Der Schlüssel wird ausschließlich im Header X-NAWS-Key angenommen — nie als Query-Parameter, damit er nicht in Zugriffsprotokollen, im Referer-Header, im Browserverlauf oder in einem Proxy dazwischen landet.',
10 => 'Open-Meteo (weltweit, Standard) und Yr.no / MET Norway (auf Nordeuropa optimiert). Beide sind kostenlos und brauchen keinen API-Schlüssel.',
11 => 'Ja. Über Export/Import lädst du Wetterdaten, Modulkonfiguration und alle Einstellungen als JSON herunter. Ideal für den Umzug auf eine neue WordPress-Installation.',
12 => 'Ja. Die Darstellungsseite bietet über 130 einstellbare Farben mit Live-Vorschau, 4 Symbolsätze, Farben je Sensor, Diagramm-Themes und Paletten für den Jahresvergleich.',
13 => 'Alle sensiblen Daten (OAuth-Token, Client-ID, Client-Secret, API-Schlüssel) werden vor dem Speichern in der Datenbank mit AES-256-GCM verschlüsselt.',
14 => 'Ja. Das Plugin bringt einen abschnittsweise arbeitenden Importer für historische Daten mit, der die getmeasure-API von Netatmo abfragt, ohne an die Ratenbegrenzung zu stoßen.',
15 => 'Netatmo-Sensoren senden alle 5 Minuten. Das Abrufintervall des Plugins ist einstellbar (5–1440 Minuten). Der Nachtmodus reduziert die Abfragen zwischen 23:00 und 06:00 Uhr.',
16 => 'Öffne <a href="https://dev.netatmo.com">dev.netatmo.com</a>, melde dich mit deinem Netatmo-Konto an, erstelle eine neue Anwendung und kopiere Client-ID und Client-Secret.',

// ── FAQ: Fragen ─────────────────────────────────────────────────────────
17 => 'Welche Vorhersage-Anbieter werden unterstützt?',
18 => 'Kann ich meine Wetterdaten sichern?',
19 => 'Kann ich das Aussehen anpassen?',
20 => 'Sind meine Netatmo-Zugangsdaten sicher?',
21 => 'Ist die REST-API sicher?',
22 => 'Kann ich historische Daten importieren?',
23 => 'Wie oft werden die Daten aktualisiert?',
24 => 'Wie bekomme ich Netatmo-API-Zugangsdaten?',

// ── Installation ────────────────────────────────────────────────────────
25 => 'Die Daten werden ab jetzt automatisch abgeglichen – füge Shortcodes in eine beliebige Seite ein',
26 => 'Klicke auf „Mit Netatmo verbinden“ und erteile die Berechtigung',
// Die Beispieladresse bleibt "yoursite.com". Eingedeutscht ("deine-seite.de")
// war sie lesbarer, aber GlotPress prueft automatisch, ob die Links in
// Original und Uebersetzung uebereinstimmen, und markierte den Eintrag mit
// einer Warnung — die einzige im ganzen Import. Ein GTE, der 106 Strings
// am Stueck freigibt, soll sich damit nicht aufhalten muessen.
27 => 'Trage in deiner Netatmo-Anwendung als Redirect-URI ein: <code>https://yoursite.com/wp-admin/admin.php?page=naws-settings</code>',
28 => 'Trage Client-ID und Client-Secret ein',
29 => 'Erstelle unter <a href="https://dev.netatmo.com">dev.netatmo.com</a> eine Netatmo-Entwickleranwendung',
30 => 'Gehe zu <strong>XTX Netatmo &gt; Einstellungen</strong>',
31 => 'Aktiviere das Plugin im WordPress-Adminbereich unter &gt; Plugins',
32 => 'Lade den Ordner <code>xtx-integration-for-netatmo</code> nach <code>/wp-content/plugins/</code> hoch',

// ── Beschreibung: Absaetze ──────────────────────────────────────────────
33 => 'Weiterer Fremdcode ist nicht enthalten. Keine Bibliothek wird von einem CDN geladen; alles kommt von deiner eigenen Installation. Bibliotheken, die WordPress selbst mitbringt, werden von WordPress bezogen und nicht mitgeliefert.',
34 => 'Diesem Plugin liegen zwei JavaScript-Bibliotheken bei, beide unter der MIT-Lizenz, die GPL-kompatibel ist. Sie werden als minifizierte Distributions-Builds ausgeliefert; der unminifizierte Quellcode und die Build-Werkzeuge sind jeweils über die Links unten erreichbar.',
35 => 'Dieses Plugin erhebt und übermittelt keine personenbezogenen Daten (Namen, E-Mail-Adressen, IP-Adressen). Alle Sensordaten liegen ausschließlich in deiner lokalen WordPress-Datenbank.',
36 => 'Dieses Plugin verbindet sich mit den folgenden externen Diensten:',
37 => '<strong>XTX Integration for Netatmo</strong> verbindet deine Netatmo-Hardware mit WordPress. Es liest alle Sensordaten über die offizielle Netatmo-API, speichert die Messwerte in deiner lokalen Datenbank und zeigt sie in ansehnlichen Live-Dashboards, animierten Diagrammen und Wettervorhersagen.',

// ── Bibliotheken ────────────────────────────────────────────────────────
38 => '<strong>Verwendet für:</strong> Formatierung der Zeitachse in den Diagrammen. Dies ist der gebündelte Build, der date-fns enthält (ebenfalls MIT).',
39 => '<strong>Quellcode und Build-Werkzeuge:</strong> <a href="https://github.com/chartjs/chartjs-adapter-date-fns">https://github.com/chartjs/chartjs-adapter-date-fns</a> — mitgeliefert ist genau die Fassung <a href="https://github.com/chartjs/chartjs-adapter-date-fns/releases/tag/v3.0.0">v3.0.0</a>',
40 => '<strong>Datei:</strong> <code>assets/vendor/chartjs-adapter-date-fns.bundle.min.js</code>',
41 => '<strong>Verwendet für:</strong> Alle Diagramme — die 24-Stunden-Verläufe auf dem Live-Dashboard und die Jahresvergleiche im Verlauf',
42 => '<strong>Quellcode und Build-Werkzeuge:</strong> <a href="https://github.com/chartjs/Chart.js">https://github.com/chartjs/Chart.js</a> — mitgeliefert ist genau die Fassung <a href="https://github.com/chartjs/Chart.js/releases/tag/v4.5.1">v4.5.1</a>',
43 => '<strong>Website:</strong> <a href="https://www.chartjs.org">https://www.chartjs.org</a>',
44 => '<strong>Lizenz:</strong> MIT',
45 => '<strong>Datei:</strong> <code>assets/vendor/chart.umd.min.js</code>',

// ── Externe Dienste: Yr.no / MET Norway ─────────────────────────────────
46 => '<strong>Hinweis:</strong> Kostenlose API, kein API-Schlüssel nötig. Die Nutzungsbedingungen von MET Norway verlangen, dass sich jeder Client zu erkennen gibt; Anfragen an diesen Dienst tragen deshalb einen User-Agent mit dem Namen des Plugins, seiner Version und deiner Seitenadresse — über diese Adresse würde MET Norway dich erreichen, bevor ein auffälliger Client eingeschränkt wird. Das geht ausschließlich an api.met.no und nur, solange Yr.no als Anbieter ausgewählt ist.',
47 => '<strong>Nutzungsbedingungen:</strong> <a href="https://developer.yr.no/doc/TermsOfService/">https://developer.yr.no/doc/TermsOfService/</a>',
48 => '<strong>Datenschutzerklärung:</strong> <a href="https://www.met.no/en/About-us/privacy">https://www.met.no/en/About-us/privacy</a>',
49 => '<strong>Wann:</strong> Wenn der Vorhersage-Shortcode angezeigt wird und Yr.no als Anbieter ausgewählt ist (3 Stunden zwischengespeichert)',
50 => '<strong>Zweck:</strong> Wettervorhersagedaten abrufen (optionaler Anbieter, in den Einstellungen wählbar)',

// ── Externe Dienste: Open-Meteo Geocoding ───────────────────────────────
51 => '<strong>Dokumentation:</strong> <a href="https://open-meteo.com/en/docs/geocoding-api">https://open-meteo.com/en/docs/geocoding-api</a>',
52 => '<strong>Wann:</strong> Im manuellen Modus immer dann, wenn kein zwischengespeichertes Ergebnis vorliegt (7 Tage zwischengespeichert). Im automatischen Modus genau einmal — der ermittelte Name wird in den Plugin-Einstellungen gespeichert und nie wieder nachgeschlagen.',
53 => '<strong>Übermittelte Daten:</strong> Im Standortmodus „manuell“ der Ortsname oder die Postleitzahl, die in den Plugin-Einstellungen eingetragen sind. Im Modus „automatisch“ Breiten- und Längengrad deiner Wetterstation, auf zwei Nachkommastellen gerundet, um den Namen des nächstgelegenen Ortes nachzuschlagen.',
54 => '<strong>Zweck:</strong> Einen Ort in Koordinaten übersetzen und Koordinaten in einen Ortsnamen für die Überschrift der Vorhersage',

// ── Externe Dienste: Open-Meteo ─────────────────────────────────────────
55 => '<strong>Hinweis:</strong> Open-Meteo ist eine kostenlose, quelloffene Wetter-API. Weder API-Schlüssel noch Registrierung sind nötig.',
56 => '<strong>Bedingungen und Datenschutz:</strong> <a href="https://open-meteo.com/en/terms">https://open-meteo.com/en/terms</a>',
57 => '<strong>Wann:</strong> Wenn der Vorhersage-Shortcode angezeigt wird (3 Stunden zwischengespeichert)',
58 => '<strong>Übermittelte Daten:</strong> Breiten- und Längengrad deiner Wetterstation',
59 => '<strong>Zweck:</strong> Wettervorhersagedaten anhand der Stationskoordinaten abrufen (Standardanbieter)',

// ── Externe Dienste: Netatmo ────────────────────────────────────────────
60 => '<strong>Datenschutzerklärung:</strong> <a href="https://legals.netatmo.com/?goto=privacy">https://legals.netatmo.com/?goto=privacy</a>',
61 => '<strong>Nutzungsbedingungen:</strong> <a href="https://dev.netatmo.com/legal">https://dev.netatmo.com/legal</a>',
62 => '<strong>Wann:</strong> Bei der ersten Anmeldung, bei jedem automatischen Abgleich, bei jeder Token-Erneuerung und während ein historischer Import läuft',
63 => '<strong>Übermittelte Daten:</strong> Client-ID und Client-Secret der von dir erstellten Netatmo-Anwendung, im Austausch gegen ein Access-Token; danach mit jeder Anfrage das Access- oder Refresh-Token sowie die Stations- und Modul-IDs, deren Messwerte angefragt werden',
64 => '<strong>Zweck:</strong> Authentifizierung über OAuth2, Abruf von Sensormesswerten und Stationsdaten',

// ── Shortcodes ──────────────────────────────────────────────────────────
4 => '<code>[naws_weather_icon]</code> – Nur das animierte Symbol für den aktuellen Wetterzustand (<code>size</code>); gibt nichts aus, wenn der Zustand unbekannt ist',
5 => '<code>[naws_weather_widget]</code> – Kompaktes Vorhersage-Widget für eine Seitenleiste (<code>days</code> 3 oder 5, <code>width</code> 250–500)',
6 => '<code>[naws_table]</code> – Messwerte als Tabelle über einen Zeitraum, nach Stunde oder Tag gruppiert (<code>period</code>, <code>group_by</code>, <code>limit</code>, <code>parameters</code>)',
7 => '<code>[naws_calc]</code> – Einzelner berechneter Wert (Taupunkt, gefühlte Temperatur, Sonnenaufgang, Mondphase, …); vollständige Liste auf der Shortcode-Seite im Backend',
8 => '<code>[naws_current]</code> – Aktuelle Messwerte eines oder aller Module als Kacheln oder Liste (<code>module_id</code>, <code>parameters</code>, <code>layout</code>, <code>title</code>)',
65 => '<code>[naws_forecast]</code> – Mehrtägige Wettervorhersage',
66 => '<code>[naws_history]</code> – Jahresvergleichsdiagramme (unterstützt den Parameter <code>year</code>)',
67 => '<code>[naws_value]</code> – Einzelner Sensorwert im Fließtext',
68 => '<code>[naws_infobar]</code> – Astronomieleiste mit Sonnenaufgang, Mondphase und gefühlter Temperatur',
69 => '<code>[naws_live]</code> – Live-Sensorkacheln mit 24-Stunden-Verläufen und Vorhersage',

// ── Module ──────────────────────────────────────────────────────────────
70 => '<strong>NAModule4</strong> – Zusätzliches Innenmodul (Temperatur, Luftfeuchte, CO2)',
71 => '<strong>NAModule3</strong> – Regenmesser (stündlich, täglich, rollende 24 Stunden)',
72 => '<strong>NAModule2</strong> – Wind (Geschwindigkeit, Richtung, Böen)',
73 => '<strong>NAModule1</strong> – Außenmodul (Temperatur, Luftfeuchte)',
74 => '<strong>NAMain</strong> – Basisstation (Temperatur, Luftfeuchte, CO2, Lärm, Luftdruck)',

// ── Funktionen ──────────────────────────────────────────────────────────
9 => '<strong>10 Shortcodes</strong> – Dashboard, aktuelle Messwerte, Infoleiste, Einzelwert, berechneter Wert, Verlaufsdiagramme, Vorhersage, Tabelle, Widget, Wettersymbol',
75 => '<strong>4 Symbolsätze</strong> – Emoji, Outline, Filled und Minimal, mit Farbsteuerung je Sensor',
76 => '<strong>Über 130 einstellbare Farben</strong> – Vollständige Anpassung der Darstellung mit Live-Vorschau',
77 => '<strong>Mobile First und responsiv</strong> – Alle Ansichten für Smartphone, Tablet und Desktop optimiert',
78 => '<strong>Export / Import</strong> – Vollständige Sicherung und Wiederherstellung von Wetterdaten, Modulen und Einstellungen',
79 => '<strong>Mehrsprachig</strong> – Vollständige Oberfläche auf Deutsch, Englisch und Norwegisch',
80 => '<strong>Einstellbare Einheiten</strong> – C/F, mm/Zoll, mbar/inHg/mmHg, km/h/m/s/mph/kn',
81 => '<strong>Verschlüsselte Speicherung</strong> – Alle Zugangsdaten (OAuth-Token, Client-Secret, API-Schlüssel) liegen AES-256-GCM-verschlüsselt in der Datenbank',
82 => '<strong>REST-API</strong> – Nur lesende JSON-API mit Schlüssel-Authentifizierung und Ratenbegrenzung für externe Werkzeuge (Google Charts, Grafana usw.)',
83 => '<strong>Wettervorhersage</strong> – 5-Tage-Vorhersage anhand der Stationskoordinaten über Open-Meteo oder Yr.no',
84 => '<strong>Verlaufsdiagramme</strong> – Jahresvergleich für Temperatur, Luftdruck und Niederschlag mit interaktiver Legende',
85 => '<strong>Abgeleitete Wetterdaten</strong> – Gefühlte Temperatur, Hitzeindex, Taupunkt, Windchill',
86 => '<strong>Astronomie</strong> – Sonnenauf- und -untergang, Mondphase mit Beleuchtungsgrad, nächster Vollmond',
87 => '<strong>Live-Dashboard</strong> – Sensorkacheln in Echtzeit mit animierten Zählern, 24-Stunden-Verläufen, Luftdrucktendenz, Windkompass und CO2-Luftqualitätsstufen',
88 => '<strong>Vollständige Netatmo-Integration</strong> – OAuth2-Anmeldung, automatischer Abgleich, alle Modultypen unterstützt (Basis, Außen, Wind, Regen, Innen)',

// ── Ueberschriften ──────────────────────────────────────────────────────
89 => 'chartjs-adapter-date-fns 3.0.0',
90 => 'Chart.js 4.5.1',
91 => 'Bibliotheken von Drittanbietern',
92 => 'Yr.no / MET Norway API (api.met.no)',
93 => 'Open-Meteo Geocoding API (geocoding-api.open-meteo.com)',
94 => 'Open-Meteo API (api.open-meteo.com)',
95 => 'Netatmo API (api.netatmo.com)',
96 => 'Datenschutz &amp; externe Dienste',
97 => 'Shortcodes',
98 => 'Unterstützte Module',
99 => 'Wichtigste Funktionen',

// ── Screenshots ─────────────────────────────────────────────────────────
100 => 'Export-/Import-Seite für Sicherungen',
101 => 'Darstellungsseite mit Live-Vorschau der Farben',
102 => 'Wettervorhersage-Widget',
103 => 'REST-API-Dokumentation im Adminbereich',
104 => 'Admin-Einstellungsseite mit Status der Netatmo-Verbindung',
105 => 'Jahresvergleichsdiagramme für Temperatur und Niederschlag',
106 => 'Live-Dashboard mit Sensorkacheln und 24-Stunden-Verläufen',

];
