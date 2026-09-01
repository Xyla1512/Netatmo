# Übersetzungen

Entwicklungsmaterial. `docs/` steht in `.distignore`, nichts davon wird
ausgeliefert. Zwei verschiedene Dinge liegen hier, und sie werden leicht
verwechselt:

| Ordner | Worum es geht | Landet wo |
|---|---|---|
| `catalog/` | Die **Oberfläche** des Plugins — `.pot`, die beiden `.po` und die daraus gebauten `.mo` in `languages/` | Im ausgelieferten Paket |
| `glotpress/` | Die **Plugin-Seite** auf wordpress.org (Beschreibung, Installation, FAQ, Changelog) | Nur auf translate.wordpress.org |

Seit 1.9.9 übersetzt das Plugin über gettext. `NAWS_Lang` und
`languages/{de,en,no}.php` gibt es nicht mehr; die englischen Texte stehen
an ihren Aufrufstellen, Deutsch und Norwegisch stehen in `catalog/`.

## catalog/ — die Oberfläche

```bash
php docs/i18n/catalog/makepot.php              # languages/*.pot neu aus dem Code
php docs/i18n/catalog/merge_po.php de_DE       # .pot in die Übersetzung nachtragen
php docs/i18n/catalog/merge_po.php nb_NO
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po languages/xtx-integration-for-netatmo-de_DE.mo
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po languages/xtx-integration-for-netatmo-nb_NO.mo
```

**Diese Reihenfolge, und nach jeder Textänderung im Code.** `makepot.php`
liest die Aufrufe mit dem PHP-Tokenizer (kein `wp-cli` nötig, kein `msgfmt`
auf diesem Rechner), `merge_po.php` ersetzt `msgmerge`, `make_mo.php`
ersetzt `msgfmt`. `merge_po.php` sagt am Ende, was offen und was weggefallen
ist — die Liste ist die eigentliche Ausgabe, nicht nur ein Protokoll.

Stand 01.09.2026: **de_DE und nb_NO je 652 von 652.**

### Was von translate.wordpress.org zurückkommt

Seit 1.9.9 übersetzen auch andere. Was dort entsteht, erreicht die Nutzer
über die Sprachpakete — aber **nicht** die mitgelieferte `.mo`, und die ist
es, die eine Installation liest, bis ihr Paket da ist. Deshalb regelmäßig
zurückholen:

```bash
curl -o /tmp/nb.po 'https://translate.wordpress.org/projects/wp-plugins/xtx-integration-for-netatmo/stable/nb/default/export-translations/?format=po'
php docs/i18n/catalog/pull_glotpress.php nb_NO /tmp/nb.po
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po languages/xtx-integration-for-netatmo-nb_NO.mo
```

Gefüllt werden nur **leere** `msgstr`. Wo beide Seiten etwas stehen haben und
es sich unterscheidet, meldet das Werkzeug den Unterschied und ändert nichts:
welche Fassung die richtige ist, entscheidet ein Mensch. Eine stille
Übernahme ist genau der Weg, auf dem am 31.08. **199 norwegische Sätze in den
deutschen Katalog** gerieten.

### Vier Dinge, an denen es schiefgeht

- **Der `.mo`-Kopf.** `MO::import_from_reader()` in WordPress prüft, ob die
  Adresse der Hashtabelle direkt hinter der zweiten Indextabelle liegt
  (`hash_addr - translations_addr === total * 8`). Stimmt das nicht, gibt es
  ein **stilles `false`** — kein Log, keine Warnung, nur englischer Text.
  1.9.9 hat auf diese Weise zwei `.mo` ausgeliefert, die nie gelesen wurden.
  `tests/test-mo-files.php` rechnet seither mit **derselben** Formel nach;
  einen Katalog nie mit einem eigenen, nachsichtigen Leser prüfen — genau der
  verdeckt so etwas.
- **Ein leeres `msgstr` ist kein Mangel.** `make_mo.php` lässt solche
  Einträge weg, und gettext antwortet dann mit dem englischen Original.
  Ein leerer Eintrag *in* der `.mo` würde den Text durch nichts ersetzen.
- **Die `msgid` ist der Schlüssel, buchstabengleich.** Ändert sich ein
  englischer Satz im Code, ist er ein neuer Eintrag und seine Übersetzung
  fällt weg — `merge_po.php` nennt sie dann unter „weg:".
- **Gleiche englische Wörter mit verschiedener Bedeutung brauchen `_x()`.**
  „Rain" ist *Niederschlag* als Messgröße und *Regen* als Wetterlage; ohne
  Kontext verschmilzt gettext beide still zur zuerst gefundenen Übersetzung.

Und eine Eigenheit von WordPress, die aussieht wie ein Fehler: `.mo`-Dateien
im Plugin werden **nur dort gelesen, wo das Sprachpaket von wordpress.org
schweigt**. `load_plugin_textdomain()` hört beim ersten gefundenen Paket
auf; das Plugin lädt deshalb beides, Paket zuerst.

## glotpress/ — die Plugin-Seite

Der Hinweis „Dieses Plugin wurde noch nicht auf Deutsch übersetzt" auf
wordpress.org ist **nicht** über SVN zu beheben. Diese Übersetzungen
entstehen ausschließlich auf translate.wordpress.org (GlotPress), und der
Import braucht Franks eigenen wordpress.org-Login. Er ist **PTE für Deutsch
und Englisch**, seine Importe sind damit sofort „current".

**Stand 01.09.2026: das deutsche Readme ist zu 281 von 281 übersetzt**, die
Changelog-Zeilen eingeschlossen. `readme-de.po` ist der **aktuelle Export**
und dient als Vergleichsstand für das nächste Mal. Eine eigene Importdatei
liegt nicht mehr hier: sie war für die 106 Einträge gedacht, die damals
fehlten, und eine veraltete Importdatei ist eine Falle.

`code-de.po` und `code-import-de_DE.po` sind **überholt**. Sie entstanden,
als das Code-Projekt auf GlotPress aus sechs Strings bestand — Plugin-Header
und ein Satz aus dem WordPress-Kern. Seit 1.9.9 stehen dort über 650, und
die deutsche Fassung davon liegt in `catalog/`. `build-code.php` gehört zu
dieser alten Rechnung und ist aus demselben Grund nur noch Beleg.

### Nach einem Release

Das Projekt heißt „Stable Readme (*latest release*)": ändert sich
`readme.txt`, sind die geänderten Abschnitte **neue** Strings und brauchen
eine Übersetzung, unveränderte behalten ihre.

```bash
cd docs/i18n/glotpress
curl -o neu.po 'https://translate.wordpress.org/projects/wp-plugins/xtx-integration-for-netatmo/stable-readme/de/default/export-translations/?format=po'
diff <(grep '^msgid' readme-de.po) <(grep '^msgid' neu.po)   # was ist dazugekommen
php dump.php neu.po          # die offenen Originale auflisten
php build.php                # de.php + Export -> Importdatei, mit Prüfungen
php verify.php               # jede msgid gegen den Export
```

**Die msgid wird nie neu getippt, sondern immer aus dem Export übernommen.**
GlotPress ordnet einen Import über sie zu; weicht ein Zeichen ab, landet der
Eintrag als unbekannter String im Nichts — **ohne Fehlermeldung**. Genau so
ist die `[naws_table]`-Zeile aus der alten Vorlage ins Leere gelaufen,
nachdem 1.9.8 ihren englischen Text umformuliert hatte.

**Anrede:** `de_DE` (Standard) wird geduzt. Die Sie-Form ist ein eigenes
Locale (`de_DE_formal`) mit eigenem Satz; diese Dateien passen dort nicht
hinein.

**Sprachpakete werden nicht sofort gebaut.** Ob WP.org den neuen Stand schon
hat, sagt:

```bash
curl -s "https://api.wordpress.org/translations/plugins/1.0/?slug=xtx-integration-for-netatmo&version=1.9.9"
```

Das `updated` je Locale ist das Revisionsdatum, aus dem das Paket gebaut
wurde — steht dort noch ein älteres Datum als das eigene `PO-Revision-Date`,
läuft der Bau noch.
