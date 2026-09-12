# E-Mail-Benachrichtigungen: Störungen und Wetterereignisse

**Datum:** 2026-09-11
**Betrifft:** neu `includes/class-naws-notify-rules.php`, `includes/class-naws-notifications.php`, `admin/views/notifications.php`, vier Tests; geändert `includes/class-naws-database.php` (Schema 1.5, `save_module()`), `includes/class-naws-cron.php` (Aktion im Fehlerzweig), `includes/class-naws-admin.php` (Untermenü, zwei Handler), `admin/views/modules.php` (Batterie aus Prozent), `includes/class-naws-labels.php`, `xtx-integration-for-netatmo.php` (Laden, Versionen), `readme.txt`, `CHANGELOG.md`, Kataloge de/nb, `docs/site/website.{de,en}.json`
**Auslöser:** Frank, 11.09.2026: „Wichtig wären mir zum einen Batteriestände der Module, WLAN erreichbarkeit, Konnektivität der Basis zum Netatmo server. Und natürlich auch Wetterereignisse welche ich später noch genauer definiere. Es muss in einem neuen Submenü Benachrichtigungen im Backend administrierbar sein und auswählbar wozu ich benachrichtigt werden möchte und wohin die Benachrichtigung gehen soll" — und: „bitte alle Sicherheitsaspekte berücksichtigen und WordPress-Vorgaben"
**Ziel-Release:** 2.0.0

---

## 1. Was das Feature tut

Nach jedem Abruf der Netatmo-Daten prüft das Plugin eine Liste von Regeln. Jede Regel beschreibt einen Zustand, der Aufmerksamkeit braucht: ein Modul mit schwacher Batterie, eine schwache Funkverbindung, schlechtes WLAN der Basis, eine Basis, die sich nicht mehr beim Netatmo-Server meldet, ein Modul, das sich nicht mehr bei der Basis meldet, ein Abruf, der wiederholt scheitert, verfallene Zugangsdaten — und drei Wetterereignisse: Frost, Böe über einer Schwelle, Regen der letzten 24 Stunden über einer Schwelle.

Tritt ein Zustand ein, geht eine E-Mail an eine Empfängerliste. Verschwindet er, geht eine Entwarnung. Dazwischen ist Ruhe: kein Zustand erzeugt mehr als eine Mail, solange er anhält. Alle Wechsel eines Abrufs stehen in einer einzigen Mail.

Verwaltet wird das auf einer neuen Backend-Seite „Benachrichtigungen": Empfänger, je Regel ein Schalter und, wo sinnvoll, eine Schwelle, ein Knopf für eine Testmail, eine Tabelle mit dem aktuellen Zustand jeder Regel und ein Protokoll der letzten Versände.

## 2. Entscheidungen

1. **Prüfung nach jedem Abruf, nicht in einem eigenen Cron.** Die bestehende Aktion `naws_data_synced` feuert nach jedem erfolgreichen Sync, nachdem die Caches geleert sind. Dort hängt sich die Auswertung ein. Ein eigener Zeitplan wäre eine zweite Fehlerquelle und hätte Böen und Ausfälle spät gemeldet. Für gescheiterte Abrufe bekommt der Cron eine zweite Aktion `naws_sync_failed`.
2. **Nur beim Wechsel, plus Entwarnung.** Frank, 11.09.: „Nur beim Wechsel + Entwarnung". Das Plugin merkt sich je Regel und Modul den letzten Zustand. Eine Mail entsteht nur, wenn sich dieser Zustand ändert. Keine Erinnerungen, keine Wiederholung je Prüfung.
3. **Eine Empfängerliste, je Regel an oder aus.** Frank, 11.09. Jede Mail geht an alle Adressen. Keine Empfänger je Regel.
4. **Statusfelder wandern in die Modultabelle (Schema 1.5).** Netatmo liefert je Modul `battery_percent`, `rf_status`, `reachable`, `last_seen`, `last_message`, je Basis `wifi_status`, `reachable`, `last_status_store`. Bisher speichert das Plugin nur `last_seen`, `firmware`, `battery_vp`, `rf_status`. Die fünf fehlenden Felder kommen als Spalten dazu. Nebeneffekt: die Modulseite zeigt echte Batterieprozente. Auf dev meldet Netatmo für „Gast" 23 %; die bisherige Näherung aus der Spannung ergäbe 44 %.
5. **Reine Auswertung, getrennt von WordPress.** `NAWS_Notify_Rules` bekommt Schnappschuss, Einstellungen, alten Zustand und die Uhrzeit hinein und gibt neuen Zustand und Wechsel zurück. Keine Datenbank, keine Optionen, keine Zeitfunktion darin. `NAWS_Notifications` ist die Anbindung. So sind alle Übergänge ohne WordPress testbar.
6. **Regelmodell, in das spätere Wetterereignisse passen.** Frank, 11.09.: „Störungen + Rahmen für Wetter, erste drei Regeln". Eine Wetterregel ist ein Eintrag im Katalog: Modultyp, Messgröße, Vergleich, Schwelle, Einheit, Beharrung, ob sie eine Entwarnung kennt. Eine vierte Wetterregel ist ein weiterer Eintrag plus Label.
7. **Schwellen in Basiseinheiten.** Gespeichert wird in °C, km/h und mm, so wie Netatmo liefert und die Tabellen speichern. Die Oberfläche rechnet in die Einheiten aus `naws_settings` um (`temperature_unit`, `wind_unit`, `rain_unit`) und zurück. Wer die Einheit wechselt, behält physikalisch dieselbe Schwelle.
8. **Alle Regeln sind bei Auslieferung aus.** Nach dem Update auf 2.0 bekommt niemand ungefragt Post. Die Empfängerliste ist leer und bedeutet dann „Admin-Adresse der Site".
9. **Klartext-Mail über `wp_mail()`, Absender bleibt WordPress.** Kein eigener Absender-Filter, keine HTML-Vorlage. Wer ein SMTP-Plugin einsetzt, bekommt dessen Zustellung automatisch. Ein Testmail-Knopf deckt die häufigste Fehlerquelle auf: der Server verschickt gar nichts.
10. **Export und Import bleiben unverändert.** Die fünf neuen Spalten sind Momentaufnahmen und nach dem nächsten Abruf wieder da. `NAWS_Export::MODULE_COLUMNS` bleibt wie sie ist; alte Exportdateien bleiben importierbar.

## 3. Regelkatalog

Kennungen sind die Schlüssel in Option und Zustand. „Beharrung" heißt: die Bedingung muss so lange durchgehend gelten, bevor der Wechsel gilt. Sie steht im Katalog, nicht in der Oberfläche.

| Kennung | Regel | Gilt je | Quelle | Schwelle (Vorgabe) | Warnung, wenn | Entwarnung, wenn | Beharrung an / aus |
|---|---|---|---|---|---|---|---|
| `battery` | Batterie niedrig | Modul (NAModule1–4) | `battery_percent` | Prozent, 1–99 (20) | Wert < Schwelle | Wert ≥ Schwelle + 10 | 0 / 0 |
| `rf` | Funk zur Basis schwach | Modul (NAModule1–4) | `rf_status` | Stufe `low` (ab 90) oder `medium` (ab 80); Vorgabe `low` | Wert ≥ Stufenwert | Wert ≤ Stufenwert − 10 | 30 min / 30 min |
| `wifi` | WLAN der Basis schlecht | Basis (NAMain) | `wifi_status` | Stufe `bad` (ab 86) oder `average` (ab 71); Vorgabe `bad` | Wert ≥ Stufenwert | Wert ≤ Stufenwert − 15 | 30 min / 30 min |
| `station_silent` | Basis meldet sich nicht | Basis | `reachable`, `last_status_store` | Minuten, 10–1440 (60) | `reachable` = 0 oder Meldung älter als Schwelle | `reachable` = 1 und Meldung jünger als Schwelle | 0 / 30 min |
| `module_silent` | Modul meldet sich nicht | Modul (NAModule1–4) | `reachable`, `last_message` (Rückfall `last_seen`) | Minuten, 10–1440 (60) | `reachable` = 0 oder Meldung älter als Schwelle | `reachable` = 1 und Meldung jünger als Schwelle | 0 / 30 min |
| `sync_failed` | Abruf scheitert | Site | Fehlerzähler des Cron (`naws_polling_state`) | keine, fest 3 | 3 Fehler in Folge (Aktion `naws_sync_failed`) | nächster Erfolg (Aktion `naws_data_synced`) | 0 / 0 |
| `auth_required` | Zugangsdaten verfallen | Site | Option `naws_auth_required` | keine | Option gesetzt | Option leer beim nächsten Erfolg | 0 / 0 |
| `frost` | Frost | Modul (NAModule1) | Messwert `Temperature` | °C, −50…50 (0) | Wert ≤ Schwelle | Wert > Schwelle + 1 | 0 / 60 min |
| `heat` | Hitze | Modul (NAModule1) | Messwert `Temperature` | °C, −50…50 (30) | Wert ≥ Schwelle | Wert < Schwelle − 1 | 0 / 60 min |
| `gust` | Böe | Modul (NAModule2) | Messwert `GustStrength` | km/h, 1…300 (60) | Wert ≥ Schwelle | Wert < Schwelle | 0 / 60 min |
| `rain` | Regen in 24 Stunden | Modul (NAModule3) | Messwert `sum_rain_24`, im Schnappschuss ersetzt durch die rollierende 24-h-Summe aus den Rohwerten (`get_rain_rolling_24h()`), weil Netatmos Feld um Mitternacht zurückspringt | mm, 0,1…500 (20) | Wert ≥ Schwelle | Wert < Schwelle, **ohne Mail** | 0 / 0 |
| `rain_start` | Regen beginnt | Modul (NAModule3) | Messwert `sum_rain_1` (Regen der letzten Stunde) | mm, 0,1…50 (0,2) | Wert ≥ Schwelle | Wert < Schwelle, **ohne Mail** | 0 / 0 |
| `co2` | CO₂ hoch | Basis (NAMain) oder Innenmodul (NAModule4) | Messwert `CO2` | ppm, 400…5000 (1000) | Wert ≥ Schwelle | Wert < Schwelle − 100 | 0 / 30 min |

Die beiden Stille-Regeln entwarnen erst, wenn die Basis beziehungsweise das Modul 30 Minuten lang wieder gemeldet hat — sonst würde ein Zeitstempel, der um die Schwelle pendelt, bei jedem Abruf eine Mail auslösen.

Die Skalen für Funk und WLAN stammen aus der Netatmo-Dokumentation (WLAN 86 = schlecht, 71 = mittel, 56 = gut; Funk 90 = schwach, 60 = voll; kleiner ist jeweils besser). Sie passen zu den auf dev gemessenen Werten (Basis 60, Module 60–74). Die Dokumentationsseite ist eine JavaScript-Anwendung und wird bei der Umsetzung im Browser gegengeprüft; weichen die Zahlen ab, ändert sich nur der Katalog.

**Abhängigkeiten.** Ist die Basis in diesem Lauf still (Rohbedingung von `station_silent`, unabhängig davon, ob die Regel eingeschaltet ist), werden `battery`, `rf`, `module_silent`, `frost`, `gust` und `rain` für die Module dieser Basis ausgesetzt: ihre Einträge bleiben, wie sie sind, ohne Wechsel. Die Werte wären veraltet. Bei gescheitertem Abruf gibt es keinen Schnappschuss; nur `sync_failed` und `auth_required` werden bewertet.

**Veraltete Messwerte.** Ein Messwert, dessen `recorded_at` älter ist als das Doppelte des effektiven Abrufintervalls (`NAWS_Cron::base_interval()`, nachts das Vierfache, mindestens 20 Minuten), setzt die Wetterregeln dieses Moduls aus. Fehlt ein Feld (Spalte noch `NULL` direkt nach dem Update, Modul ohne den Messwert), ist die Regel für dieses Modul ausgesetzt, nie ausgelöst.

**Inaktive Module.** Regeln laufen nur über Module mit `is_active = 1`. Wer ein Modul nicht überwachen will, deaktiviert es auf der Modulseite; das beendet ohnehin die Datensammlung dafür. Eine Modul-Auswahl je Regel gibt es nicht.

## 4. Datenweg

### 4.1 Schema 1.5

`naws_modules` bekommt fünf Spalten, in der `CREATE TABLE`-Definition für Neuinstallationen und in `maybe_migrate()` für Bestandsinstallationen nach dem vorhandenen Muster (`SHOW COLUMNS` per `get_col`, dann je Spalte `ALTER TABLE … ADD COLUMN` als vollständiges SQL-Literal, Tabellenname aus Konstante und Präfix über `esc_sql()`):

| Spalte | Typ | Quelle | Gilt für |
|---|---|---|---|
| `battery_percent` | `TINYINT UNSIGNED DEFAULT NULL` | `battery_percent`, auf 0–100 begrenzt | Module |
| `wifi_status` | `INT DEFAULT NULL` | `wifi_status` | Basis |
| `reachable` | `TINYINT(1) DEFAULT NULL` | `reachable` als 0/1 | beide |
| `last_status_store` | `BIGINT DEFAULT NULL` | `last_status_store` | Basis |
| `last_message` | `BIGINT DEFAULT NULL` | `last_message` | Module |

Reihenfolge: nach `rf_status`, vor `is_active`. `NAWS_DB_VERSION` wird `'1.5'`; `install()` läuft dann beim nächsten `plugins_loaded` (bestehende Prüfung in der Hauptdatei). `save_module()` schreibt die fünf Felder im vorhandenen `INSERT … ON DUPLICATE KEY UPDATE` mit (`%d` beziehungsweise `NULL`, wenn Netatmo das Feld nicht liefert; `is_active` bleibt wie bisher unangetastet).

`admin/views/modules.php` nimmt die Batterie aus `battery_percent`, wenn die Spalte einen Wert hat, und fällt sonst auf die bisherige Näherung aus `battery_vp` zurück.

### 4.2 Schnappschuss

`NAWS_Notifications::snapshot(): array` baut nach dem Sync aus `NAWS_Database::get_modules( true )` und `NAWS_Database::get_latest_readings()` (beide frisch, weil `flush_caches()` vor der Aktion läuft):

```
[
  'modules' => [
    '<module_id>' => [
      'module_id', 'station_id', 'module_name', 'module_type',
      'battery_percent', 'rf_status', 'wifi_status', 'reachable',
      'last_status_store', 'last_seen', 'last_message',
      'readings' => [ 'Temperature' => [ 'value' => 2.4, 'at' => 1789140000 ], … ],
    ],
  ],
]
```

Die Basis ist das Modul mit `module_type === 'NAMain'`. Bei zwei Basen (zwei Stationen im Konto) bekommt jede ihre eigenen Zeilen; Module gehören über `station_id` zu ihrer Basis.

### 4.3 Klasse `NAWS_Notify_Rules` (`includes/class-naws-notify-rules.php`)

Statisch, keine WordPress-Aufrufe, wird über `naws_require()` geladen (`tests/test-main-requires.php` verlangt das).

| Methode | Zweck |
|---|---|
| `catalog(): array` | Der Katalog aus Abschnitt 3: je Kennung `group` (`station`/`weather`), `scope` (`site`/`station`/`module`), `types`, `field` oder `reading`, `param` (`threshold`/`level`/`minutes`/keiner), `kind` (`percent`/`level`/`minutes`/`temp`/`wind`/`rain`), `default`, `levels` (für `rf`/`wifi`: Stufe → Warn- und Entwarnwert), `hold_on`, `hold_off` in Sekunden, `clears`. |
| `defaults(): array` | Vorgabe-Einstellungen: `recipients => []`, jede Regel `enabled => 0` mit ihrer Vorgabe. |
| `evaluate( array $snapshot, array $settings, array $state, int $now, array $ctx ): array` | Kern. `$ctx` = `[ 'interval' => Sekunden, 'sync' => 'ok'\|'failed', 'consecutive_errors' => int, 'auth_required' => bool, 'error' => string ]`. Gibt `[ 'state' => neuer Zustand, 'events' => Wechsel, 'rows' => Zustandszeilen für die Seite ]` zurück. |
| `condition( string $rule, array $module, array $settings, int $now ): ?bool` | Rohbedingung einer Regel für ein Modul: `true` (Warnbedingung), `false` (Entwarnbedingung), `null` (ausgesetzt: Feld fehlt, Messwert veraltet, Modultyp passt nicht). |
| `to_base( string $kind, float $value, array $units ): float` und `to_display( … )` | Umrechnung der Schwellen zwischen Basiseinheit und den Einheiten aus `naws_settings`; die Faktoren sind dieselben wie in `NAWS_Helpers::format_value()`. |

### 4.4 Zustandsmaschine

Option `naws_notify_state` (Autoload aus). Schlüssel `<rule>|<module_id>`, für Site-Regeln `<rule>|site`:

```
'battery|03:00:00:0d:aa:ca' => [ 'active' => true, 'since' => 1789140000, 'pending_since' => null, 'value' => 23 ]
```

Übergänge je Eintrag, mit `$now` aus dem Aufruf:

1. Regel aus, oder Modul nicht mehr aktiv, oder Modultyp passt nicht: Eintrag wird entfernt, kein Wechsel.
2. Bedingung `null` (ausgesetzt): Eintrag bleibt unverändert, Zeile für die Seite sagt „ausgesetzt" mit Grund.
3. Nicht aktiv, Bedingung `true`: `pending_since` wird gesetzt, falls leer. Ist `$now − pending_since ≥ hold_on`, wird der Eintrag aktiv (`since = $now`, `pending_since = null`) und erzeugt den Wechsel `raise`.
4. Nicht aktiv, Bedingung `false`: `pending_since = null`.
5. Aktiv, Bedingung `false`: `pending_since` wird gesetzt, falls leer. Ist `$now − pending_since ≥ hold_off`, wird der Eintrag inaktiv; Wechsel `clear`, wenn die Regel `clears` hat, sonst still.
6. Aktiv, Bedingung `true`: `pending_since = null`, `value` wird aktualisiert.

Ein Wechsel ist `[ 'rule', 'kind' => 'raise'|'clear', 'module_id', 'module_name', 'module_type', 'value', 'threshold', 'since', 'now', 'error' ]`. Eine frisch eingeschaltete Regel wird beim nächsten Lauf normal bewertet: „Gast" mit 23 % unter Schwelle 25 löst sofort aus, weil `hold_on` für Batterie 0 ist.

### 4.5 Klasse `NAWS_Notifications` (`includes/class-naws-notifications.php`)

Statisch, immer geladen (der Cron braucht sie), Hooks werden in `NAWS_Plugin::init()` über `NAWS_Notifications::init()` registriert.

| Konstante / Methode | Zweck |
|---|---|
| `OPTION_KEY = 'naws_notifications'`, `STATE_KEY = 'naws_notify_state'`, `LOG_KEY = 'naws_notify_log'`, `LOCK_KEY = 'naws_notify_lock'` | Optionsnamen; Zustand und Protokoll ohne Autoload. |
| `init()` | `add_action( 'naws_data_synced', … )`, `add_action( 'naws_sync_failed', …, 10, 2 )`. |
| `get_settings(): array` | Option gelesen und über `sanitize()` mit den Vorgaben verschmolzen; beschädigte Option → Vorgaben. |
| `sanitize( array $input ): array` | Abschnitt 5. |
| `recipients(): array` | Gespeicherte Adressen; leer → `[ get_option( 'admin_email' ) ]`. |
| `on_synced( int $saved )` / `on_failed( string $message, int $errors )` | Die beiden Hook-Callbacks: Lock holen, Schnappschuss (nur bei Erfolg), `evaluate()`, Zustand speichern, bei Wechseln `compose()` + `send()`, Protokoll, Lock freigeben. Alles in `try/catch (\Throwable)`; ein Fehler geht an `NAWS_Logger::error( 'notify', … )` und bricht den Cron nicht ab. |
| `snapshot(): array` | Abschnitt 4.2. |
| `compose( array $events, array $ctx ): array` | `[ 'subject', 'body' ]`, Abschnitt 6. |
| `send( array $to, string $subject, string $body ): bool` | `wp_mail()`; Ergebnis ins Protokoll. |
| `send_test(): bool` | Testmail an `recipients()`. |
| `status_rows(): array` | `evaluate()` als Trockenlauf auf dem aktuellen Schnappschuss: Zeilen für die Tabelle „Aktueller Zustand", kein Speichern, keine Mail. |
| `log( array $entry )` / `get_log(): array` | Rollendes Protokoll, 50 Einträge, neueste zuerst. |

**Lock.** `add_option( LOCK_KEY, time(), '', 'no' )` gibt `false` zurück, wenn der Eintrag existiert; das ist die einzige nicht überholbare Prüfung, die WordPress ohne Objekt-Cache bietet. Ein Lock, der älter als 120 Sekunden ist, gilt als verwaist und wird überschrieben. Am Ende `delete_option()`. So erzeugen zwei überlappende Cron-Läufe keine Doppelmail.

### 4.6 Einhängen im Cron

`NAWS_Cron` feuert `do_action( 'naws_sync_failed', string $message, int $consecutive_errors )` an den drei Stellen, an denen heute `record_error()` steht: im Zweig „Neuanmeldung nötig" (`$message = 'auth_required'`), im Fehlerzweig nach `sync_current_data()` und im `catch` von `run_fetch()`. Der Erfolgszweig bleibt unverändert; `naws_data_synced` feuert dort bereits nach `flush_caches()`.

## 5. Einstellungen

Option `naws_notifications`:

```
[
  'enabled' => 1,                                   // Generalschalter
  'recipients' => [ 'frank@example.org', … ],       // leer erlaubt
  'rules' => [
    'battery'        => [ 'enabled' => 0, 'threshold' => 20 ],
    'rf'             => [ 'enabled' => 0, 'level' => 'low' ],
    'wifi'           => [ 'enabled' => 0, 'level' => 'bad' ],
    'station_silent' => [ 'enabled' => 0, 'minutes' => 60 ],
    'module_silent'  => [ 'enabled' => 0, 'minutes' => 60 ],
    'sync_failed'    => [ 'enabled' => 0 ],
    'auth_required'  => [ 'enabled' => 0 ],
    'frost'          => [ 'enabled' => 0, 'threshold' => 0.0 ],   // °C
    'heat'           => [ 'enabled' => 0, 'threshold' => 30.0 ],  // °C
    'gust'           => [ 'enabled' => 0, 'threshold' => 60.0 ],  // km/h
    'rain'           => [ 'enabled' => 0, 'threshold' => 20.0 ],  // mm
    'rain_start'     => [ 'enabled' => 0, 'threshold' => 0.2 ],   // mm
    'co2'            => [ 'enabled' => 0, 'threshold' => 1000 ],  // ppm
  ],
]
```

`sanitize( $input )` ist eine Whitelist über den Katalog: unbekannte Regeln und Felder fallen weg, jede bekannte Regel wird mit ihrer Vorgabe aufgefüllt.

- `enabled` ist der Generalschalter (Vorgabe 1): steht er auf 0, wertet der Lauf nichts aus und verschickt nichts; Zustand und Regeln bleiben, die Testmail geht weiter.
- `recipients`: Der Handler übergibt das Feld als Text (siehe 8). `sanitize()` trennt an Zeilenumbruch, Komma und Semikolon, führt jede Adresse durch `sanitize_email()` und behält nur, was `is_email()` besteht, ohne Dubletten, höchstens 20.
- `enabled`: `! empty()` → 0/1.
- `threshold` je `kind`: `percent` → `absint()`, 1–99; `temp`/`wind`/`rain` → `floatval()` des in Anzeigeeinheit eingegebenen Werts, mit `to_base()` in die Basiseinheit gerechnet, dann in den Bereich der Tabelle in Abschnitt 3 geklemmt.
- `level`: `in_array( $v, [ 'low', 'medium' ], true )` beziehungsweise `[ 'bad', 'average' ]`, sonst Vorgabe.
- `minutes`: `absint()`, 10–1440.

## 6. E-Mail

**Sprache und Zahlen.** Alle Texte über `naws_label()` beziehungsweise `__()` in der Site-Sprache. Beim Testversand aus dem Backend wird mit `switch_to_locale( get_locale() )` auf die Site-Sprache gewechselt und danach `restore_previous_locale()` gerufen, damit die Testmail so aussieht wie die echten. Werte über `NAWS_Helpers::format_value()` und `get_unit()` in den eingestellten Einheiten, Uhrzeiten über `wp_date()` in der Site-Zeitzone.

**Empfänger.** `recipients()` als Array an `wp_mail()`; alle stehen im An-Feld.

**Betreff.** Präfix `[<Site-Name>] Netatmo:`. Bei genau einem Wechsel `<Regel> – <Modul> (<Wert>)`, bei einer Entwarnung mit vorangestelltem „Entwarnung:"; bei mehreren `<n> Warnungen, <m> Entwarnungen` (Singular/Plural über `_n()`). Der fertige Betreff läuft durch `sanitize_text_field()`, das entfernt Zeilenumbrüche und schließt Header-Injektion über einen Modulnamen aus.

**Inhalt.** Ein Absatz je Wechsel, Leerzeile dazwischen, dann eine Leerzeile und die Zeile „Regeln anpassen: <URL der Seite>" (`admin_url( 'admin.php?page=naws-notifications' )`).

```
Warnung: Batterie niedrig
Modul: Gast (Innenmodul)
Batterie: 23 % (Schwelle 25 %)
Seit: 11.09.2026, 09:10

Entwarnung: Frost
Modul: Aussen (Außenmodul)
Temperatur: 2,4 °C (Schwelle 0 °C)
Frost seit 11.09.2026, 03:40, vorbei seit 08:50

Regeln anpassen: https://…/wp-admin/admin.php?page=naws-notifications
```

Site-Regeln haben keine Modulzeile; `sync_failed` trägt stattdessen den Fehlertext des Cron und die Zahl der Fehler in Folge, `auth_required` einen Satz mit dem Weg zur Einstellungsseite.

**Testmail.** Betreff `[<Site-Name>] Netatmo: Testmail`, Inhalt: Site-Name, Uhrzeit, Liste der eingeschalteten Regeln mit Schwellen, Hinweis, dass diese Mail von Hand ausgelöst wurde.

**Protokoll.** Option `naws_notify_log`, höchstens 50 Einträge, neueste zuerst: `[ 'time', 'subject', 'to' => [...], 'sent' => bool, 'events' => [ [ 'rule', 'kind', 'module_name', 'value' ], … ] ]`. Testmails stehen mit `events => []` darin. Bei `sent = false` zusätzlich `NAWS_Logger::error( 'notify', … )`; der Zustand wird trotzdem übernommen. Keine Wiederholungsschleife bei dauerhaft kaputtem Mailversand, die Testmail zeigt diesen Fall.

## 7. Backend-Seite

**Registrierung.** In `NAWS_Admin::add_menu()` direkt nach `naws-modules`: `add_submenu_page( 'naws-dashboard', __( 'Notifications' ), …, 'manage_options', 'naws-notifications', [ $this, 'page_notifications' ] )`. `page_notifications()` lädt `$settings`, `$catalog`, `$units`, `$log`, und bindet `admin/views/notifications.php` ein. Zwei Handler im Konstruktor: `admin_post_naws_save_notifications` und `admin_post_naws_test_notification`.

**Aufbau der Seite**, von oben:

0. Generalschalter „Benachrichtigungen senden" mit Hinweis, wenn er aus ist.
1. Titel, ein Satz Erklärung.
2. Formular „Speichern" (`admin-post.php`, `wp_nonce_field( 'naws_save_notifications' )`, Hidden `action`):
   - Empfänger: `<textarea name="naws_notifications[recipients]">`, Platzhalter die Admin-Adresse, Hilfetext „eine Adresse je Zeile, leer = Admin-Adresse".
   - Gruppe „Station" und Gruppe „Wetter": je Regel eine Zeile mit Checkbox (davor ein Hidden-Feld mit Wert 0, wie überall im Plugin), Name, ein Satz Erklärung, und je nach `param` ein Zahlenfeld mit Einheit (`step` passend), ein `<select>` mit den Stufen oder ein Minutenfeld. Regeln ohne Parameter zeigen die feste Bedingung als Text.
   - Knopf „Speichern" (`submit_button()`).
3. Formular „Testmail senden" (eigenes kleines Formular, `wp_nonce_field( 'naws_test_notification' )`), daneben der Hinweis, dass die Testmail an die **gespeicherten** Empfänger geht.
4. Kein Zustandsabschnitt (Frank, 12.09.: die Seite soll kurz bleiben; `status_rows()` bleibt als Methode für Tests und eine spätere Kompaktanzeige).
5. Tabelle „Letzte Benachrichtigungen": Zeit, Ergebnis, Empfänger, Betreff, sechs Zeilen sichtbar, der Rest scrollt.

**Rückmeldungen.** Die Handler leiten mit `wp_safe_redirect()` auf eine URL zurück, die `wp_nonce_url( …, 'naws_notifications_notice' )` erzeugt hat: `&updated=1`, `&dropped=<n>` (ungültige Adressen verworfen), `&test=1|0`. Die View liest diese Parameter nur in einer Bedingung, die den Nonce direkt prüft (Abschnitt 8).

**Kein JavaScript, kein neues CSS.** Die Seite nutzt die WordPress-Klassen `form-table`, `widefat`, `notice` und das vorhandene Admin-Stylesheet.

## 8. Sicherheit und WordPress-Vorgaben

Frank, 11.09.: „bitte alle Sicherheitsaspekte berücksichtigen und WordPress-Vorgaben". Jede Regel aus den Review-Unterlagen (Coding Rules, wiederkehrende Fehler, Directory-Richtlinien), auf dieses Vorhaben angewandt:

**Berechtigung und Herkunft**
- Beide Handler: `check_admin_referer( '<action>' )` als erste Zeile, danach `if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );`. Nonce prüft Herkunft, Capability prüft Berechtigung; beides, immer.
- Die Testmail ist ein POST mit Nonce, kein Link. Ein GET darf nichts auslösen.
- Die View liest `$_GET['updated']`, `$_GET['dropped']`, `$_GET['test']` ausschließlich so: `if ( isset( $_GET['updated'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) )`. Der Nonce-Check steht in der Bedingung, nie in einer Variablen. Die Werte laufen durch `absint()`.
- `$_POST`/`$_GET`-Zugriffe nur in Hook-Callbacks, nie auf Dateiebene.

**Eingaben**
- Handler „Speichern": `$recipients = isset( $_POST['naws_notifications']['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['naws_notifications']['recipients'] ) ) : '';` und `$rules = isset( $_POST['naws_notifications']['rules'] ) && is_array( $_POST['naws_notifications']['rules'] ) ? map_deep( wp_unslash( $_POST['naws_notifications']['rules'] ), 'sanitize_text_field' ) : [];`. Die Sanitierung steht sichtbar im Handler, nicht hinter einem `phpcs:ignore`. Das Empfängerfeld muss über `sanitize_textarea_field()` gehen, weil `sanitize_text_field()` die Zeilenumbrüche entfernen und die Adressen verschmelzen würde.
- Danach `update_option( NAWS_Notifications::OPTION_KEY, NAWS_Notifications::sanitize( [ 'recipients' => $recipients, 'rules' => $rules ] ) )`. `sanitize()` ist eine Whitelist über den Katalog: `sanitize_email()` + `is_email()`, `absint()`, `floatval()` mit Bereichsklemmung, `in_array( …, true )` für Stufen.
- Rückleitung nur über `wp_safe_redirect( admin_url( … ) )` und `exit`.

**Ausgaben**
- Jedes `echo` in der View trägt sichtbar eine WordPress-Funktion: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()` für das Empfängerfeld, `esc_html_e()`/`esc_attr_e()` für Übersetzungen. Keine Wrapper-Funktion als Escaping. Modulnamen, Fehlertexte und Protokolleinträge kommen aus der Datenbank oder aus Optionen und werden trotzdem beim Echo escaped.
- Kein `ob_start()` in der View, kein `<script>`/`<style>` im Template. Die Seite braucht kein JavaScript.

**Datenbank**
- Migration nach dem bestehenden Muster: `SHOW COLUMNS` über `get_col()`, `ALTER TABLE` als vollständiges Literal, Tabellenname aus Konstante und Präfix mit `esc_sql()`, je Zeile der eingeführte `phpcs:ignore`-Kommentar mit Begründung.
- `save_module()` bleibt eine `prepare()`-Abfrage mit `%d`-Platzhaltern; `NULL` wird über getrennte Platzhalter beziehungsweise `COALESCE` wie bei `latitude` behandelt, nie durch Interpolation.
- Der Schnappschuss liest nur über die bestehenden Methoden `get_modules()` und `get_latest_readings()`; keine neue SQL-Abfrage im Feature.

**E-Mail**
- Empfänger ausschließlich aus der gesäuberten Option oder `admin_email`; keine Adresse aus einer Anfrage. Betreff über `sanitize_text_field()`, keine eigenen Header, Klartext. Damit ist Header-Injektion ausgeschlossen.
- Kein Absender-Filter: das Plugin greift nicht in `wp_mail_from` ein.

**Optionen und Zustand**
- Zustand, Protokoll und Lock ohne Autoload (`update_option( …, false )`, `add_option( …, '', 'no' )`), damit sie nicht auf jeder Seite geladen werden.
- Der Lock nutzt `add_option()`, die einzige nicht überholbare Prüfung ohne Objekt-Cache; ein verwaister Lock verfällt nach 120 Sekunden.

**Directory-Richtlinien**
- Kein neuer externer Dienst: `wp_mail()` nutzt den Mailweg der Site. Der Abschnitt „Privacy & External Services" der readme bleibt unverändert.
- Keine Mail an irgendjemanden außer den vom Administrator eingetragenen Adressen; nichts verlässt die Site ohne diese Einstellung. Alle Regeln sind bei Auslieferung aus.
- Keine seitenweiten Admin-Hinweise; Rückmeldungen erscheinen nur auf der eigenen Seite.
- Versionsnummer, readme, `Stable tag` und Changelog werden beim Schnitt gemeinsam gehoben (Richtlinie 15), erst SVN, dann GitHub (Richtlinie 3).

**Abnahme vor dem Commit:** `composer lint` (phpcs mit dem Plugin-Standard) und Plugin Check auf dev müssen ohne neue Meldungen durchlaufen; die Checkliste aus den Review-Unterlagen wird Punkt für Punkt gegen die neuen Dateien geprüft.

## 9. Fehlerfälle

- **Auswertung wirft:** `try/catch (\Throwable)` um jeden Hook-Callback, Fehler an `NAWS_Logger`, Cron läuft weiter. Muster wie `run_fetch()`.
- **Direkt nach dem Update:** Spalten sind `NULL`, bis der erste Abruf sie füllt; Regeln mit fehlendem Wert sind ausgesetzt. Keine Fehlalarme.
- **Beschädigte Option:** `get_settings()` liefert Vorgaben. **Kein aktives Modul, keine Basis:** leere Auswertung, keine Fehler, Tabelle sagt „keine aktiven Module".
- **Modul verschwindet aus dem Konto:** es bleibt in der Tabelle, `reachable` wird nicht mehr aktualisiert; `module_silent` greift, wenn eingeschaltet. Wird das Modul auf der Modulseite deaktiviert, verschwinden seine Einträge.
- **Mailversand scheitert:** Protokoll `sent = false`, Fehler-Log, Zustand übernommen (Abschnitt 6).
- **Plugin deaktiviert:** der Cron wird wie bisher entfernt; Zustand und Einstellungen bleiben in den Optionen. Das Plugin hat keine Deinstallationsroutine (`uninstall.php` gibt es nicht); die neuen Optionen verhalten sich wie alle anderen.
- **Zeitzone:** Zustand in Unix-Sekunden; nur die Anzeige rechnet um.

## 10. Tests

Eigenständige PHP-Skripte wie die 43 vorhandenen (`php tests/test-*.php`, Exit-Code 1 bei Fehlschlag, `check()`-Helfer, Stubs im Kopf, `tests/i18n-stubs.php` für Labels).

1. **`tests/test-notify-rules.php`** — ohne WordPress: Katalog vollständig (dreizehn Regeln, jede mit Gruppe, Scope, Vorgabe, Beharrung, `clears`); `condition()` je Regel für wahr, falsch und ausgesetzt (Feld fehlt, Messwert veraltet, falscher Modultyp); Zustandsmaschine: Eintritt ohne Beharrung, Eintritt mit Beharrung nach zwei Läufen, Flattern setzt die Beharrung zurück, Entwarnung mit Hysterese (Batterie 20 → 25 keine Entwarnung, 30 ja), Regen ohne Entwarnung (Eintrag inaktiv, kein Wechsel), Basis still setzt Modul- und Wetterregeln aus, ausgeschaltete Regel räumt ihren Eintrag ab, deaktiviertes Modul ebenso, Site-Regeln bei `sync = failed`, frisch eingeschaltete Regel löst sofort aus, Wechsel eines Laufs kommen gesammelt zurück; `to_base()`/`to_display()` hin und zurück für °F, m/s, mph, kn, in.
2. **`tests/test-notifications-sanitize.php`** — `sanitize()`: Adressen (Zeilenumbruch, Komma, Semikolon, Dubletten, ungültige, Obergrenze), Bereiche und Klemmung je `kind`, Stufen-Whitelist, unbekannte Schlüssel fallen weg, Vorgaben werden aufgefüllt, beschädigte Eingabe (String statt Array) → Vorgaben.
3. **`tests/test-notifications-mail.php`** — `compose()` mit deutschen Stubs: Betreff für einen Wechsel, für eine Entwarnung, für mehrere; Absätze je Wechsel; Site-Regel ohne Modulzeile; Link am Ende; Betreff ohne Zeilenumbruch, auch wenn der Modulname einen enthält.
4. **`tests/test-database-module-status.php`** — mit dem wpdb-Stub aus `test-database-active-modules.php`: `save_module()` übergibt die fünf Felder (Werte, `NULL` bei Fehlen, `battery_percent` geklemmt, `wifi_status` nur bei NAMain); `install()`/`maybe_migrate()` legt fehlende Spalten an und lässt vorhandene in Ruhe.
5. **Bestehende Tests:** `test-main-requires.php` (beide neuen Klassen geladen), `test-mo-files.php` nach der Katalogkette, die ganze Suite ohne Fehlschlag, `composer lint` sauber.

**Abnahme auf dev** (Plugin per ZIP-Weg, Sicherung vorher): Empfänger eintragen, Batterieregel mit Schwelle 30 % einschalten → nächster Abruf schickt eine Mail für „Gast" (23 %), Tabelle zeigt „Warnung seit"; Testmail ankommen lassen; Basis-Ausfall simulieren, indem `last_status_store` der Basis in der Tabelle um zwei Stunden zurückgedreht wird → Mail „Basis meldet sich nicht", Modulregeln in der Tabelle „ausgesetzt"; nächster Abruf → Entwarnung; Plugin Check auf dev ohne neue Meldungen.

## 11. Release 2.0.0

- `Version: 2.0.0`, `NAWS_VERSION`, `Stable tag`, `NAWS_DB_VERSION = '1.5'`.
- `CHANGELOG.md`: der vorhandene Eintrag `## [1.9.14]` wird zu `## [2.0.0]`; „Added" beschreibt die Benachrichtigungen und die fünf Statusfelder, die Windrosen-Knöpfe und der icon_set-Fix bleiben unter „Fixed"/„Changed".
- `readme.txt`: neuer Punkt in „Key Features" („**E-Mail Notifications** – battery, radio and Wi-Fi, station offline, failed syncs, frost, gusts, rain; per-rule thresholds, one mail per state change with all-clear"), neuer FAQ-Eintrag „I don't receive notification emails" (Testmail, Empfänger, SMTP-Plugin oder Hoster), Changelog-Fenster 1.9.10–2.0.0, Upgrade Notice 2.0.0. „Privacy & External Services" unverändert.
- Labels de/nb, Kette `makepot` → `merge_po` → `make_mo`, `test-mo-files` grün; GlotPress-Importdateien nach der Veröffentlichung wie bei 1.9.13.
- `docs/site/website.de.json` und `website.en.json`: Vorhaben `email-benachrichtigungen` bekommt `"ab": "2.0.0"`, `aktualisiert` auf das Release-Datum. Ein Satz auf der Startseite der Produktseite ist eine eigene Site-Aufgabe danach.
- Release-Notizen `Documents\GitHub\release-notes-2.0.0.de.md`, ZIP aus losgelöstem Worktree, Prüfung mit `zipcheck.php`; erst SVN (Frank: `svn ci`), dann Git-Tag `v2.0.0` und GitHub-Release, dann www / Produkt / dev per Plugin-Update mit Sicherung.

## 12. Nicht Teil dieses Vorhabens

- Empfänger je Regel; Wiederholungs- oder Erinnerungsmails; Ruhezeiten.
- Modul-Auswahl je Regel (die Modulseite deaktiviert Module).
- HTML-Mails, eigener Absender, andere Kanäle (Push, Webhook, Telegram).
- Statusverläufe (Batterie über die Zeit) in einer eigenen Tabelle.
- Weitere Wetterregeln über die drei hinaus; sie sind nach diesem Vorhaben je ein Katalogeintrag plus Label.
- Ein Nachziehen der fünf Spalten in Export/Import.
