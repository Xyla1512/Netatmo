# Eigene Symbole statt Emojis im Frontend

**Datum:** 2026-11-13 · **Größe:** kleine Änderung (kurzer Entwurf) · **Zweig:** `eigene-symbole` (von `main` 59ef81f)
**Auslöser:** Datenschutz-Prüfung 13.11.: Das Regenradar lädt nichts Fremdes im Browser. Aber die Emojis, die das Plugin im Frontend ausgibt, ersetzt das Emoji-Skript von WordPress in Browsern ohne eigene Emoji-Schrift durch Bilder von `s.w.org`. Dabei geht die IP des Besuchers an WordPress.org/Automattic. Frank: „die Emojis durch eigene Symbole im PlugIn ersetzen“ und in der Plugin-Beschreibung festhalten, dass keine fremden Inhalte in den Browser geladen werden.
**Demo (von Frank abgenommen: „ja passt“):** https://claude.ai/artifact/YACCrsQ8SMqMaS3s7CNSFx

## Ziel

Im Frontend steht kein einziges Emoji mehr. Der Symbolsatz „Emoji“ bleibt wählbar, besteht aber aus eigenen farbigen SVG-Symbolen im Emoji-Stil, auf allen Geräten gleich. Danach steht in der readme, dass das Plugin keine Inhalte von fremden Seiten in den Browser der Besucher lädt.

## Ansätze (Franks Wahl: B)

| | Ansatz | Ergebnis |
|---|---|---|
| A | Satz „Emoji“ abschaffen, Bestand fällt auf „Outline“ | verworfen: Aussehen aller Bestandsseiten ändert sich |
| **B** | **Satz „Emoji“ behalten, selbst gezeichnet** | **gewählt:** Aussehen bleibt nah am Gewohnten, keine fremden Bilder |
| C | „Emoji“ unverändert, nur neue Voreinstellung „Outline“ | verworfen: Datenschutz-Satz stimmte nur eingeschränkt |

## Umfang

1. **`NAWS_Icons::set_emoji()`**: die sieben Schlüssel `temp, humid, press, wind, rain, co2, noise` als farbige SVGs genau wie in der Demo (32er-Raster, eigene Füllfarben, dunkle Kontur `#3d4b52`). Abweichungen gegenüber den alten Emojis wie gezeigt: CO₂ = Wolke mit „CO₂“ statt 💨.
2. **`templates/forecast.php`**: 📍 🌧️ 💧 🌬️ 🧭 🌪️ durch die Demo-Symbole ersetzen (Ort, Niederschlag, Regenwahrscheinlichkeit = halb gefüllter Tropfen, Wind, Windrichtung, Böen = kräftige Windlinien). Gemeinsame Quelle: neue Methode, z. B. `NAWS_Icons::pictogram( string $key ): string`, die auch `set_emoji()` speist (*meine Festlegung, damit jedes Bild nur einmal im Code steht*).
3. **CSS**: Die farbigen Symbole dürfen nicht von den bestehenden Linien-Regeln überschrieben werden (`.naws-card-icon svg { fill:none; stroke:currentColor }` in `frontend.css:211`, `.naws-wx[data-icon-set="emoji"] .naws-ico svg { stroke:…; fill:none }` in `frontend.css:1139`). Die Symbole bekommen eine eigene Klasse (z. B. `naws-picto`), die diese Regeln aufhebt; Größe wie die übrigen Symbole an derselben Stelle.
4. **Unsichtbare Emojis im Code ersetzen**, damit `grep` im Frontend-Code nichts mehr findet: Rückfall in `NAWS_Helpers::param_icon` (~Z. 126–138) → Outline-Symbol bzw. leerer Text; `icon`-Felder in `history_chart_defs()`/`indoor_chart_defs()` (nur Admin) → Schlüssel statt Emoji oder entfernen, wenn ungenutzt; Mondphasen-Emoji in `NAWS_Astro::_moon_name()` → aus der Rückgabe streichen, wenn nirgends gelesen (sonst SVG).
5. **Darstellungsseite**: Beschreibung des Satzes „Emoji“ („Native system emojis — appearance varies by device“) → „Colourful pictograms in emoji style — the same on every device.“ (übersetzt de/nb).
6. **readme.txt**: am Anfang von `== Privacy & External Services ==` ein Absatz: Alles, was Besucher sehen — Diagramme, Symbole, Radarbilder, Karten, Schriften — kommt von der eigenen Website; das Plugin lädt keine Inhalte von fremden Servern in den Browser, für die Besucherseite ist daher kein Hinweis in der Datenschutzerklärung nötig. Die folgenden Dienste ruft nur der Server ab. CHANGELOG `[Unreleased]`.
7. **Test**: neuer Test, der alle Dateien unter `templates/`, `includes/` (ohne Admin-Klassen) und `assets/js|css` nach Emoji-Zeichen (U+1F300–1FAFF, U+2600–27BF, U+2B50) durchsucht und keinen Treffer erlaubt; dazu Tests, dass `set_emoji()` sieben SVGs liefert und die Vorhersage keine Emojis mehr ausgibt.

## Nicht-Ziele

Admin-Bereich (nur angemeldete Nutzer), die Sätze „Outline“/„Filled“/„Minimal“, Wetterlagen-Symbole (schon SVG), WordPress' eigenes Emoji-Skript abschalten (Sache des Betreibers).

## Abnahme

dev: `[naws_live]` und `[naws_current]` mit Satz „Emoji“, `[naws_forecast]`, Hover; per Playwright prüfen, dass keine Anfrage an `s.w.org` mehr geht (vorher auf `/wetter/` gemessen: ja).
