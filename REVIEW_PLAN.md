# WordPress.org Review Plan — XTX Integration for Netatmo

**Review ID:** R xtx-integration-for-netatmo/xylaender/2Apr26/T2 9Apr26/3.9 (P0TDX294194HGN)
**Target Version:** 1.7.0
**Ziel:** Alle Beanstandungen des WordPress Plugin Review Teams beheben

---

## Fortschritt

- [x] Phase 1 abgeschlossen
- [x] Phase 2 abgeschlossen
- [x] Phase 3 abgeschlossen
- [x] Phase 4 abgeschlossen
- [ ] Phase 5 abgeschlossen
- [ ] Phase 6 abgeschlossen
- [ ] Phase 7 abgeschlossen
- [ ] Phase 8 abgeschlossen
- [ ] Plugin auf WordPress.org hochgeladen (v1.7.0)
- [ ] Antwort an Review-Team gesendet

---

## Phase 1 — Einfache Fixes

- [x] **1.1** `readme.txt`: Netatmo Privacy-URL korrigiert → `https://www.netatmo.com/legal/privacy-policy`
- [x] **1.2** `xtx-integration-for-netatmo.php:75`: `class Netatmo_Weather_Station` → `class NAWS_Plugin`
- [x] **1.3** `assets/vendor/chart.umd.min.js`: bereits v4.5.1 — war schon erledigt
- [x] **1.4** `admin/views/rest-api-docs.php`: Google Charts CDN war bereits entfernt (in 1.6.0 behoben)

---

## Phase 2 — REST API permission_callback

Datei: `includes/class-naws-rest-api.php`

- [x] **2.1** `$auth`-Variable-Merge-Pattern entfernt
- [x] **2.2** `permission_callback => [__CLASS__, 'authenticate']` direkt und sichtbar in alle 5 Routen eingetragen
- [x] **2.3** Entscheidung: Wetterdaten sind öffentlich → `'permission_callback' => '__return_true'` gemäß WP Review Team Vorgabe

---

## Phase 3 — Nonce-Checks hinzufügen

**7 betroffene Stellen:**

- [x] **3.1** `class-naws-ajax.php: get_chart_data()` — `check_ajax_referer('naws_public_nonce', 'nonce')` hinzugefügt
- [x] **3.2** `class-naws-ajax.php: get_daily_data()` — `check_ajax_referer('naws_public_nonce', 'nonce')` hinzugefügt
- [x] **3.3** `class-naws-ajax.php: get_latest()` — `check_ajax_referer('naws_public_nonce', 'nonce')` hinzugefügt
- [x] **3.4** `class-naws-ajax.php: get_history_data()` — `check_ajax_referer('naws_public_nonce', 'nonce')` hinzugefügt
- [x] **3.5** `class-naws-admin.php: handle_oauth_callback()` — `wp_verify_nonce()` bereits vorhanden (state + nonce Fallback)
- [x] **3.6** `admin/views/export.php` — `wp_verify_nonce('naws_notice')` vor GET-Parameter-Anzeige; Redirects mit `wp_nonce_url()` versehen
- [x] **3.7** `admin/views/dashboard.php` — `wp_verify_nonce('naws_notice')` vor GET-Parameter-Anzeige; sanitize_text_field() statt urldecode()
- [x] **3.8** Nonce `naws_public_nonce` war bereits in `wp_localize_script()` vorhanden (class-naws-shortcodes.php:61)

---

## Phase 4 — Output-Escaping für SVGs (17 Stellen)

- [x] **4.1** `naws_kses_svg()` in `class-naws-helpers.php` erstellt — `wp_kses()` mit Whitelist für svg/path/circle/line/polyline/polygon/g
- [x] **4.2** `admin/views/dashboard.php` — alle 11 SVG-echo-Stellen ersetzt (modules, readings, db, daily, oldest, history, lastsync, nextsync, health_icon, type_icon, get_icon)
- [x] **4.3** `templates/live.php:274` — `naws_kses_svg()` verwendet
- [x] **4.4** `templates/forecast.php:81` — `naws_kses_svg()` verwendet
- [x] **4.5** `templates/infobar.php:162` — `naws_kses_svg()` verwendet
- [x] **4.6** `admin/views/appearance.php:246` — `naws_kses_svg()` verwendet

---

## Phase 5 — Input-Sanitierung

- [ ] **5.1** `class-naws-admin.php:375` — `$_FILES['naws_import_file']`:
  - `$file['name']` → `sanitize_file_name()`
  - `@copy()` → `move_uploaded_file()` (korrekte WP-Methode, kein `@`-Silencing)
- [ ] **5.2** `admin/views/dashboard.php:47` — `$_GET['error']`:
  - `echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) )` (sanitize VOR escape)
- [ ] **5.3** `class-naws-admin.php:317` — `$_POST['naws_appearance']`:
  - Sicherstellen dass `NAWS_Colors::sanitize()` wirklich alle Felder bereinigt, phpcs-Kommentar präzisieren

---

## Phase 6 — ob_start() schließen (11 Stellen)

Jede Datei: `ob_start()` muss im gleichen Scope mit `ob_get_clean()` oder `ob_end_flush()` geschlossen werden.

- [ ] **6.1** `admin/views/shortcodes.php:276`
- [ ] **6.2** `templates/live.php:324`
- [ ] **6.3** `admin/views/export.php:180`
- [ ] **6.4** `admin/views/appearance.php:455`
- [ ] **6.5** `admin/views/live-settings.php:296`
- [ ] **6.6** `admin/views/modules.php:102`
- [ ] **6.7** `admin/views/settings.php:375`
- [ ] **6.8** `admin/views/dashboard.php:393`
- [ ] **6.9** + 3 weitere ob_start()-Stellen suchen und fixen

---

## Phase 7 — Inline `<script>`/`<style>` entfernen (19 Stellen)

Alle auf `wp_add_inline_script()` / `wp_add_inline_style()` / `wp_enqueue_script()` umstellen.

- [ ] **7.1** `admin/views/settings.php:374` — `<script>` → `wp_add_inline_script()`
- [ ] **7.2** `templates/live.php:166` — `<script src="...chart.umd.min.js">` → `wp_enqueue_script()`
- [ ] **7.3** `templates/history.php:147` — `<script>` → `wp_add_inline_script()`
- [ ] **7.4** `admin/views/live-settings.php:294` — `<style>` → `wp_add_inline_style()`
- [ ] **7.5** `admin/views/import.php:223` — `<script>` → `wp_add_inline_script()`
- [ ] **7.6** `admin/views/export.php:179` — `<script>` → `wp_add_inline_script()`
- [ ] **7.7** `admin/views/dashboard.php:392` — `<script>` → `wp_add_inline_script()`
- [ ] **7.8** `admin/views/shortcodes.php:48` — `<style>` → `wp_add_inline_style()`
- [ ] **7.9** + 11 weitere Stellen suchen und fixen
- [ ] **7.10** Sicherstellen, dass alle Scripts/Styles vorher per `wp_register_script/style()` registriert sind

---

## Phase 8 — Unsafe SQL (13 Stellen)

**Problem:** Spaltennamen werden direkt interpoliert — nicht über `wpdb::prepare()` absicherbar.
**Lösung:** Whitelist-Validierung für alle dynamischen Spaltennamen.

- [ ] **8.1** Whitelist-Konstante für erlaubte Spalten in `class-naws-database.php` definieren
- [ ] **8.2** `class-naws-database.php:789` — `$field_sql` mit Whitelist absichern
- [ ] **8.3** `class-naws-database.php:821` — SQL-String mit validierten Feldern
- [ ] **8.4** `class-naws-export.php:152` — `self::DAILY_COLUMNS` direkt in SQL: durch Whitelist-Prüfung ersetzen
- [ ] **8.5** `class-naws-export.php:156` — Readings-Export-Query absichern
- [ ] **8.6** `class-naws-ajax.php:492` — `$field_sql` mit Whitelist absichern
- [ ] **8.7** `class-naws-importer-v2.php:359` — `$col_list` absichern
- [ ] **8.8** + 6 weitere Stellen suchen und fixen

---

## Notizen / Entscheidungen

- **REST API Endpoints:** Sind diese öffentlich zugänglich (Wetterdaten für Besucher) oder nur für eingeloggte Admins? → Entscheidung beeinflusst Phase 2
- **SVG-Escaping:** `wp_kses()` ist die einzige WP-native Lösung; eigene Whitelist nötig
- **ob_start() Pattern:** In Admin-Views wird ob_start() oft für AJAX-Antworten verwendet — Refaktorierung muss Funktion erhalten

---

*Zuletzt aktualisiert: 2026-04-10 — Session 1 abgeschlossen*
*Nächster offener Schritt: Phase 5 — Input-Sanitierung*
