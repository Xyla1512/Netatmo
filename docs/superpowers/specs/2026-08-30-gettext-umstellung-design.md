# Von eigenen Sprachdateien auf gettext

**Datum:** 2026-08-30
**Betrifft:** `includes/class-naws-lang.php`, `languages/*.php`, 788 Aufrufstellen, `assets/js/*.js`, 16 Testdateien
**Auslöser:** Die Community soll Übersetzungen anlegen und pflegen können, ohne den Umweg über den Autor

---

> ## Status: umgesetzt in 1.9.9, am 31.08.2026
>
> **Diese Analyse ist eingelöst.** Am 30.08. war sie zurückgestellt worden — der Nutzen
> hänge daran, dass *andere* übersetzen, und bei drei selbst gepflegten Sprachen sei
> `NAWS_Lang` die günstigere Lösung. Einen Tag später ist die Umstellung gemacht worden;
> die Fassung, die tatsächlich ausgeliefert wurde, steht im Changelog unter 1.9.9. Wer
> wissen will, warum es so aussieht, wie es aussieht, liest hier weiter — die Erhebung
> unten (716 Schlüssel, 788 Aufrufstellen, die 15 Gruppen, bei denen Deutsch oder
> Norwegisch unterscheidet, wo Englisch das nicht tut) ist die Grundlage, auf der es
> gebaut wurde, und die `_x()`-Kontexte in der ausgelieferten Fassung kommen aus ihr.
>
> **Zwei Zahlen, die anders ausgingen als hier gerechnet:** aus 716 Schlüsseln je Sprache
> wurden 652 Katalogeinträge (gleiche englische Texte fallen zusammen, wo kein Kontext sie
> trennt), und 108 Schlüssel ließen sich nicht an ihre Aufrufstelle schieben, weil sie zur
> Laufzeit zusammengesetzt werden — sie stehen jetzt in `naws_label()`.
>
> **Der Teil, der schon vorher umgesetzt war:** die fest verdrahteten Anzeigetexte in
> `assets/js/frontend.js` (Abschnitt 2). Sie waren kein gettext-Thema, sondern ein Fehler
> für sich — drei deutsche Sätze, die keine Sprachdatei erreichen konnte. Die Umstellung
> hat sie *nicht* mitgenommen, weil sie nie im PHP standen; nachgeholt wurde das erst
> danach, abgesichert durch `tests/test-frontend-i18n.php`.
>
> Die Zahl **66** in Abschnitt 2 war zu hoch gegriffen. Sie stammte aus einem groben Filter,
> der auch Bezeichner wie `IntersectionObserver` mitzählte. Nachgezählt sind es **drei
> deutsche Sätze an fünf Stellen**, alle in `frontend.js`. `live-boot.js` war bereits
> vollständig übersetzt (über `NAWS_LIVE.I18N` aus `templates/live.php`), `history-boot.js`
> hat keine eigenen Anzeigetexte.

---

## 1. Ausgangslage

`NAWS_Lang` ist ein eigenes, kleines gettext: 211 Zeilen Klasse, drei Sprachdateien
(`de.php`, `en.php`, `no.php`) mit je **716 Schlüsseln**, zusammen 168 KB. Angesprochen wird
sie über drei Helfer:

| Helfer | Aufrufe (ohne `tests/`) | wo |
| --- | --- | --- |
| `naws__( $key )` | 342 | `includes/` 149, `admin/` 101, `templates/` 92 |
| `naws_e( $key )` | 436 | `admin/` 433, sonst 3 |
| `NAWS_Lang::r( $key )` | 10 | `admin/` |
| **gesamt** | **788** | |

Der entscheidende Unterschied zu gettext steckt nicht in der Mechanik, sondern in den
Schlüsseln: `naws__( 'ls_hint_toggles' )` benennt einen *Platz*, nicht einen *Satz*. Gettext
kennt keine Plätze — dort ist die englische Zeichenkette selbst der Schlüssel.

Genau daran hängt alles Weitere. Ein Zeichenketten-Sammler, egal ob `wp i18n make-pot` oder
der Parser auf translate.wordpress.org, liest den Quelltext. Er findet dort `'ls_hint_toggles'`
und hat damit nichts, was ein Mensch übersetzen könnte. **Die Umstellung besteht im Kern
darin, die englischen Sätze aus `en.php` zurück in den Quelltext zu holen.**

## 2. Was gemessen wurde

Alles hier ist an den Dateien nachgezählt, nicht geschätzt.

**Der Bestand ist ungewöhnlich sauber.** Alle drei Sprachen tragen exakt dieselben 716
Schlüssel — keine Lücke, keine Waise, kein Schlüssel, den nur eine Sprache kennt. Das ist die
beste Ausgangslage, die eine solche Migration haben kann, und sie erlaubt eine vollständig
maschinelle Umschreibung.

| Eigenschaft | Zahl |
| --- | --- |
| Schlüssel je Sprache | 716 |
| englischer Text gesamt | 22.255 Zeichen, rund 3.500 Wörter |
| Länge im Schnitt / längster | 31 / 500 Zeichen |
| mit `sprintf`-Platzhalter | 19 |
| mit HTML-Auszeichnung | 8 |
| englische Texte, die mehrfach vorkommen | 38 Texte über 93 Schlüssel |
| davon mit **abweichender** Übersetzung | **15 Gruppen** |

Dazu **66 fest verdrahtete deutsche Sätze im Frontend-JavaScript**, die heute in keiner Sprache
übersetzt sind: „Chart konnte nicht gerendert werden.", „Keine Daten für diesen Zeitraum.",
„Daten konnten nicht geladen werden (HTTP …". Ein englischer Besucher sieht sie auf Deutsch.
Das ist ein bestehender Mangel, kein neuer.

**Kein Aufruf steht vor `init`.** Das Plugin hängt seine eigene `init()` an `plugins_loaded`,
und dort wird nichts übersetzt — geprüft. WordPress 6.7 und neuer melden zu früh geladene
Textdomänen als Fehler; dieser Fall tritt hier nicht ein, muss nach der Umstellung aber
erneut geprüft werden.

## 3. Warum überhaupt

Das Plugin liegt seit dem 29.08.2026 im WordPress-Verzeichnis. Damit steht
**translate.wordpress.org** bereit — mit Übersetzungsoberfläche, Glossar,
Übersetzungsspeicher, Vorschlagswesen und pro Sprache organisierten Freiwilligen. Ein
Übersetzer braucht dann weder ein GitHub-Konto noch einen Pull Request noch den Autor als
Zwischenstation. Das ist genau das Ziel, und die Infrastruktur dafür ist bereits da; sie
wartet nur auf Zeichenketten in einer Form, die ihr Parser lesen kann.

Zwei Nebengewinne, die kein Zusatzaufwand sind:

- **Sprachpakete kommen unabhängig von Releases.** Heute erfordert jede neue Sprache eine
  neue Plugin-Version. Künftig liefert WordPress sie selbst aus.
- **168 KB Sprachdateien und 211 Zeilen eigener Code verlassen das Plugin.**

## 4. Entscheidungen

### 4.1 Die eigene Sprachwahl bleibt — über `plugin_locale`

Das Plugin hat heute eine Einstellung `naws_settings['language']` (`de` | `en` | `no` |
`auto`), die das Gebietsschema der Website übersteuert. Sie bleibt, wird aber über den
WordPress-Filter umgesetzt statt über eigenen Code:

```php
add_filter( 'plugin_locale', static function ( $locale, $domain ) {
    if ( 'xtx-integration-for-netatmo' !== $domain ) {
        return $locale;
    }
    $wahl = get_option( 'naws_settings', [] )['language'] ?? 'auto';
    return 'auto' === $wahl ? $locale : $wahl;
}, 10, 2 );
```

**Warum behalten:** Bestehende Installationen merken keinen Unterschied. Ein Update, das
stillschweigend die Anzeigesprache wechselt, ist eine schlechte Überraschung, und sie träfe
jeden, der nicht auf `auto` steht.

**Was sich ändert:** Die Auswahlliste zeigt künftig nicht mehr die drei mitgelieferten
Dateien, sondern die tatsächlich installierten Sprachpakete. `NAWS_Lang::get_available_languages()`
mit seiner handgepflegten Liste von Eigennamen entfällt zugunsten der WordPress-eigenen
Auskunft.

### 4.2 Das JavaScript kommt mit

Die 66 Sätze wandern nach `wp.i18n.__()`, die Übersetzungen dazu als `.json` neben die
`.mo`-Dateien (`wp i18n make-json`). Ein zweiter Durchgang durch dieselben Dateien in einer
späteren Version kostet mehr als ein Durchgang jetzt.

### 4.3 Englisch ist die Quellsprache

Im Quelltext stehen künftig die englischen Sätze. Das ist die Konvention des Verzeichnisses,
und `en.php` liefert sie bereits vollständig — es ist keine Übersetzungsarbeit, sondern eine
Umschichtung.

Folge: **Es gibt keine englische Übersetzungsdatei mehr.** Englisch ist der Quelltext.

### 4.4 Norwegisch wird `nb_NO`

`no` ist kein WordPress-Gebietsschema. WordPress kennt `nb_NO` (Bokmål) und `nn_NO`
(Nynorsk). Die vorhandenen Texte sind Bokmål („Nedbør", „Luftfuktighet", „Støynivå"), also
`nb_NO`. Die Zuordnung `nn` → `no` in `detect_from_locale()` entfällt ersatzlos: Nynorsk-Nutzer
bekommen dann Englisch, bis jemand `nn_NO` übersetzt — was ehrlicher ist, als ihnen Bokmål
als ihre Sprache auszugeben.

### 4.5 Mitgeliefert genau eine Version lang, dann nicht mehr

Auf Dauer gehören Deutsch und Norwegisch nach translate.wordpress.org und werden von dort als
Sprachpaket ausgeliefert, statt im Plugin mitzureisen. Das Verzeichnis empfiehlt das so, und
es verhindert den Fall, dass eine mitgelieferte Datei eine neuere aus dem Sprachpaket
verdeckt.

**Aber nicht sofort.** Ein Sprachpaket entsteht erst, wenn die Übersetzung auf
translate.wordpress.org eine Schwelle überschreitet und dort gebaut wird — das dauert und
liegt nicht in unserer Hand. In dieser Lücke sähe jeder, der heute Deutsch sieht, plötzlich
Englisch. Das ist derselbe Bruch, den Abschnitt 4.1 für die Sprachwahl ausdrücklich vermeidet,
und er wäre hier genauso unnötig.

Deshalb: **`de_DE.mo` und `nb_NO.mo` reisen in der Umstellungsversion mit** und werden in der
darauffolgenden Version entfernt — sobald nachgesehen und bestätigt ist, dass die Sprachpakete
tatsächlich ausgeliefert werden. Das Entfernen gehört als eigener Punkt in `[Unreleased]`,
sonst bleibt es liegen.

`Domain Path: /languages` bleibt im Kopf stehen, auch danach — für Installationen, die eigene
Dateien dort ablegen wollen.

## 5. Die 15 Kontextfälle

Gettext verschmilzt gleiche Quelltexte zu einem Eintrag. Bei 23 der 38 mehrfach vorkommenden
Texte ist das richtig. Bei 15 Gruppen ist es falsch, weil Deutsch oder Norwegisch
unterscheiden, wo Englisch das nicht tut. Sie brauchen `_x()` mit Kontext, sonst geht die
Unterscheidung still verloren:

| Englisch | Schlüssel | was verloren ginge |
| --- | --- | --- |
| Rain | `unit_rain` vs. 4 weitere | Niederschlag / Regen · Nedbør / Regn |
| Humidity | `param_humidity`, `card_humidity`, `chart_humid_prefix`, `appearance_sensor_humidity` | Luftfeuchtigkeit / Luftfeuchte / Feuchte |
| Noise | `card_noise` vs. `appearance_*_noise` | Lautstärke / Lärm · Støynivå / Støy |
| Fair | `co2_fair` vs. `wx_state_fair` | Befriedigend / Heiter · Middels / Lettskyet |
| Shortcode | `ls_shortcode` vs. `sc_calc_col_key` | Shortcode / Schreibweise |
| Copy | `sc_copy` vs. `rest_copy` | Copy / Kopieren |
| Feels like | `calc_feels_like` vs. `infobar_feels_like` | volle vs. gekürzte Form |
| Wind | `card_wind` vs. 4 weitere | Vind / Vind og vindkast (nur `no`) |
| Active / Inactive | `rest_active`, `rest_inactive` | API aktiv / Aktiv (nur `no`) |
| Importing… | `importing` vs. `import_running` | Importerer / Import kjører (nur `no`) |
| Source | `forecast_source` vs. `sc_wxicon_source` | Kilde: Open-Meteo.com / Kilde (nur `no`) |
| Forecast days | `forecast_days_label` vs. `wgt_days_label` | Vorhersage-Tage / Vorhersagetage |
| Sunrise / Sunset | `calc_*` vs. `infobar_*` | Großschreibung in `no` |

Bei Sonnenauf- und -untergang ist die Abweichung reine Auszeichnung (`SOLOPPGANG` gegen
`Soloppgang`); die gehört in CSS und nicht in die Übersetzung. Diese beiden werden
zusammengelegt, die Großschreibung übernimmt `text-transform`.

## 6. Architektur

`NAWS_Lang` verschwindet nicht ersatzlos — die Klasse bleibt als **dünne Fassade** bestehen,
damit die Kopplung nach außen nicht bricht (siehe Abschnitt 8), aber ihr Inhalt wird ein
anderer:

| heute | künftig |
| --- | --- |
| `NAWS_Lang::t( $key, $args )` | entfällt; Aufrufstellen rufen `__()` / `_x()` |
| `NAWS_Lang::e()` / `naws_e()` | entfällt; `esc_html_e()` |
| `NAWS_Lang::r()` | entfällt; `echo wp_kses_post( __( … ) )` |
| `NAWS_Lang::lang()` | bleibt, liest `determine_locale()` |
| `NAWS_Lang::reset()` | bleibt, leert den Zwischenspeicher der Textdomäne |
| `NAWS_Lang::get_available_languages()` | bleibt, liefert installierte Sprachpakete |
| `NAWS_Lang::js_strings()` | entfällt ersatzlos (heute schon ohne Aufrufer) |
| `languages/{de,en,no}.php` | gelöscht |

### Escaping

`naws_e()` escaped heute selbst mit `esc_html()`, `__()` tut das nicht. Die Umschreibung
muss deshalb pro Stelle die richtige Form wählen:

| Fundort | wird zu |
| --- | --- |
| Textinhalt (der Regelfall) | `esc_html_e( '…', 'xtx-integration-for-netatmo' )` |
| in einem Attributwert | `esc_attr_e( … )` |
| Text mit erlaubtem HTML (`NAWS_Lang::r`) | `echo wp_kses_post( __( … ) )` |
| als Wert weitergereicht (`naws__`) | `__( … )`, Escaping bleibt beim Empfänger |

Eine Stichprobe hat keine `naws_e()`-Aufrufe innerhalb von Attributen gefunden. Das ist eine
Stichprobe und keine Zusicherung — die Prüfung jeder einzelnen Stelle steht im Plan.

## 7. Werkzeug für die Umschreibung

Ein einmaliges Skript, das nicht im Plugin landet (`bin/` oder außerhalb des Repos):

1. `en.php` einlesen → Abbildung Schlüssel → englischer Satz.
2. Jede `*.php` unter `includes/`, `admin/`, `templates/` durchgehen und
   `naws__( 'k' )` / `naws_e( 'k' )` / `NAWS_Lang::r( 'k' )` ersetzen, Escaping nach der
   Tabelle in Abschnitt 6, Kontext aus der Liste in Abschnitt 5.
3. `sprintf`-Argumente bleiben unangetastet — `__()` gibt die Vorlage zurück, `sprintf`
   steht schon davor oder muss ergänzt werden (19 Fälle, einzeln zu prüfen).
4. Aus `de.php` und `no.php` je eine `.po` erzeugen: `msgid` = englischer Satz aus `en.php`,
   `msgstr` = übersetzter Satz, `msgctxt` bei den Kontextfällen.

Das Skript ist Wegwerfwerkzeug, wird aber im Repo abgelegt: Wer die Umstellung später
nachvollziehen will, braucht sie.

## 8. Das Website-Plugin hängt daran

`xtx-netatmo-site.php:381–382` ruft `NAWS_Lang::reset()`, nachdem es für Anfragen unter
`/en/` per `locale`-Filter auf `en_US` umgeschaltet hat. Der Aufruf ist mit `class_exists`
abgesichert — er würde also nicht auf die Nase fallen, sondern **wirkungslos**, und die
englische Seite auf netatmo.frank-neumann.de zeigte wieder deutsche Plugin-Ausgaben.

Deshalb bleibt `NAWS_Lang::reset()` bestehen und leert künftig `unload_textdomain()`. Der
`locale`-Filter des Website-Plugins wirkt danach auch auf die Textdomäne — sogar
zuverlässiger als heute, weil WordPress die Zuordnung selbst übernimmt.

Die Prüfung gehört auf die Testseite dev.frank-neumann.de, bevor irgendetwas auf die
Produktseite geht.

## 9. Tests

Zehn der 16 Testdateien definieren eigene `naws__()`-Attrappen (`function naws__( $k ) { return $k; }`).
Sie prüfen damit heute gegen *Schlüssel*. Künftig müssen sie gegen *englische Sätze* prüfen —
das ist keine mechanische Ersetzung, sondern eine Durchsicht: Manche Zusicherung ist mit dem
Schlüssel als Prüfwert überhaupt erst sinnvoll geworden.

`test-appearance.php`, `test-calc-rows.php`, `test-chart-order.php`, `test-fonts.php`,
`test-history-render.php`, `test-indoor-charts.php`, `test-settings-merge.php`,
`test-widget-footer.php`, `smoke-render-inline.php` sind betroffen.

Neu dazu kommt eine Prüfung, die es heute nicht geben kann: **dass jeder Text im Quelltext
auch im Katalog steht** — `wp i18n make-pot` laufen lassen und die Zahl der Einträge gegen
die erwartete halten.

## 10. Reihenfolge

Jede Etappe ist für sich auslieferbar und lässt das Plugin lauffähig.

1. **Kontextfälle entscheiden** (Abschnitt 5), Sonnenauf-/untergang in CSS lösen.
2. **Skript bauen**, gegen eine einzelne Datei erproben (`templates/` ist am kleinsten).
3. **PHP umschreiben**, Verzeichnis für Verzeichnis: `templates/` (94), `includes/` (150),
   `admin/` (544).
4. **`NAWS_Lang` auf die Fassade eindampfen**, `plugin_locale`-Filter setzen,
   Einstellungsseite auf installierte Sprachpakete umstellen.
5. **`.po` aus `de.php`/`no.php` erzeugen**, gegen `make-pot` gegenprüfen, Sprachdateien
   löschen.
6. **JavaScript** auf `wp.i18n` umstellen, `wp_set_script_translations`, `make-json`.
7. **Tests nachziehen**, PHPCS-Durchlauf, `make-pot`-Zählprüfung.
8. **Auf dev.frank-neumann.de prüfen**, mit dem Website-Plugin zusammen.
9. **Ausliefern**, danach `de` und `nb_NO` auf translate.wordpress.org hochladen.

## 11. Nicht Teil dieser Arbeit

- Neue Sprachen übersetzen. Das ist ja der Sinn: das übernimmt danach die Community.
- Die Texte selbst verbessern. Wer beim Umschichten anfängt umzuformulieren, kann hinterher
  nicht mehr sagen, ob eine Abweichung Absicht war.
- Das Website-Plugin auf gettext umstellen. Es ist zweisprachig und nicht verteilt; `XNS_I18n`
  bleibt, wie es ist.

## 12. Ehrliche Grenzen

**Die Umschreibung ist maschinell, die Durchsicht nicht.** 788 Stellen sind zu viele, um jede
einzeln zu begründen, und zu wenige, um Stichproben zu vertrauen. Der ehrliche Weg ist:
Skript schreibt, PHPCS prüft das Escaping, `make-pot` prüft die Vollständigkeit, und die
Durchsicht konzentriert sich auf die Stellen, an denen das Skript eine *Entscheidung* treffen
musste — Attribut oder Text, Kontext oder nicht.

**Die Zahl 716 ist die geparste, nicht die gezählte.** Ein einfaches `grep` kommt auf 708;
PHP selbst sagt 716. Der Unterschied sind Schlüssel, die nicht am Zeilenanfang stehen. Für
den Plan zählt die geparste Zahl.

**Die Umstellung ist für den Autor keine Ersparnis, sondern eine Abgabe.** Sie kostet einmal
deutlich mehr, als sie je an eigener Arbeit zurückgibt — der Gewinn ist, dass *andere*
übersetzen können. Wer das nicht will oder erwartet, dass niemand kommt, hat von dieser
Arbeit nichts. Bei drei Sprachen, die der Autor selbst pflegt, wäre `NAWS_Lang` weiterhin die
günstigere Lösung; ab der ersten fremden Sprache dreht sich das Verhältnis.

**Der Übergang ist die einzige Stelle mit einem Rückschritt** — und Abschnitt 4.5 entschärft
ihn, indem `de_DE.mo` und `nb_NO.mo` eine Version lang mitreisen. Bleibt dieser Punkt liegen,
bleiben die Dateien liegen: ein mitgeliefertes `.mo` verdeckt ein neueres Sprachpaket, und
dann übersetzt die Community ins Leere. Das Entfernen ist keine Kür.
