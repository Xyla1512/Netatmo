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

Stand zuletzt: **de_DE vollständig**, **nb_NO 612 von 652**. Die vierzig
offenen sind die Wochentags-, Monats- und Wetterlagennamen, die erst mit
1.9.9 übersetzbar wurden und nie eine norwegische Fassung hatten.

Drei Dinge, an denen es schiefgeht:

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
Import braucht Franks eigenen wordpress.org-Login. Ohne PTE-Rechte für das
eigene Plugin landen die Beiträge auf „Waiting", bis ein deutscher Editor
sie freigibt.

| Datei | Projekt | Strings |
|---|---|---|
| `xtx-integration-for-netatmo-de_DE-readme.po` | Stable Readme (latest release) → German | 106 von 273 |
| `code-import-de_DE.po` | Stable (latest release) → German | historisch, siehe unten |

Die 167 Changelog-Einträge des Readme sind bewusst nicht übersetzt:
GlotPress führt sie selbst als `gp-priority: low`, sie machen zwei Drittel
der Arbeit aus, und mit jedem Release kommen neue dazu. Nicht übersetzte
Strings fallen auf das englische Original zurück.

`code-import-de_DE.po` ist **überholt**. Es entstand, als das Code-Projekt
auf GlotPress aus sechs Strings bestand — Plugin-Header und ein Satz aus
dem WordPress-Kern. Seit 1.9.9 stehen dort über 650, und die deutsche
Fassung davon liegt fertig in `catalog/`. `build-code.php` gehört zu dieser
alten Rechnung und ist aus demselben Grund nur noch Beleg.

Neu erzeugen:

```bash
cd docs/i18n/glotpress
curl -o readme-de.po 'https://translate.wordpress.org/projects/wp-plugins/xtx-integration-for-netatmo/stable-readme/de/default/export-translations/?format=po'
php dump.php readme-de.po    # die 106 Originale zum Übersetzen auflisten
php build.php                # de.php + Export -> readme-.po, mit Prüfungen
php verify.php               # jede msgid gegen den Export
```

`de.php` trägt die Übersetzungen, nummeriert wie die Ausgabe von `dump.php`.

**Die msgid wird nie neu getippt, sondern immer aus dem Export übernommen.**
GlotPress ordnet einen Import über sie zu; weicht ein Zeichen ab, landet der
Eintrag als unbekannter String im Nichts — **ohne Fehlermeldung**. Genau
dagegen prüfen `build.php` (Stichproben gegen eine Verschiebung, HTML-Tags,
Linkziele, Shortcodes, 150-Zeichen-Grenze) und `verify.php` (jede erzeugte
`msgid` muss buchstabengleich im Export stehen).

**Anrede:** `de_DE` (Standard) wird geduzt. Die Sie-Form ist ein eigenes
Locale (`de_DE_formal`) mit eigenem Satz; diese Dateien passen dort nicht
hinein.

**Nach einem Release** heißt das Projekt weiter „Stable Readme (*latest
release*)": geänderte `readme.txt`-Abschnitte sind neue Strings und
brauchen eine Übersetzung, unveränderte behalten ihre.
