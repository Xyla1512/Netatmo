=== XTX Integration for Netatmo ===
Contributors: xylaender
Tags: netatmo, weather, weather station, temperature, chart
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, animated charts, history and weather forecasts.

== Description ==

**XTX Integration for Netatmo** connects your Netatmo hardware to WordPress. It reads all sensor data via the official Netatmo API, stores readings in your local database and displays them with beautiful live dashboards, animated charts and weather forecasts.

= Key Features =

* **Full Netatmo Integration** – OAuth2 authentication, automatic sync, all module types supported (Base, Outdoor, Wind, Rain, Indoor)
* **Live Dashboard** – Real-time sensor cards with animated counters, 24h trend charts, pressure trend indicator, wind compass, CO2 air quality levels
* **Astronomy** – Sunrise/sunset, moon phase with illumination, next full moon
* **Derived Weather Data** – Feels-like temperature, heat index, dew point, wind chill
* **Historical Charts** – Year-over-year comparison for temperature, pressure and rainfall with interactive legend
* **Weather Forecast** – 5-day forecast based on station coordinates via Open-Meteo or Yr.no
* **REST API** – Read-only JSON API with key authentication and rate limiting for external tools (Google Charts, Grafana, etc.)
* **Encrypted Storage** – All credentials (OAuth tokens, client secret, API keys) are AES-256-GCM encrypted at rest
* **Configurable Units** – C/F, mm/inch, mbar/inHg/mmHg, km/h/m/s/mph/kn
* **Multilingual** – Full German, English and Norwegian interface
* **5 Shortcodes** – Live widget, infobar, single value, history charts, forecast
* **Export / Import** – Full backup and restore of weather data, modules and settings
* **Mobile-First Responsive** – All views optimized for smartphones, tablets and desktops
* **130+ Configurable Colors** – Full appearance customization with live preview
* **4 Icon Sets** – Emoji, Outline, Filled, Minimal with per-sensor color control

= Supported Modules =

* **NAMain** – Base Station (Temperature, Humidity, CO2, Noise, Pressure)
* **NAModule1** – Outdoor (Temperature, Humidity)
* **NAModule2** – Wind (Speed, Direction, Gusts)
* **NAModule3** – Rain Gauge (Hourly, Daily, Rolling 24h)
* **NAModule4** – Additional Indoor (Temperature, Humidity, CO2)

= Shortcodes =

* `[naws_live]` – Live sensor tiles with 24h trend charts and forecast
* `[naws_infobar]` – Astronomy bar with sunrise, moon phase, felt temperature
* `[naws_value]` – Single inline sensor value
* `[naws_history]` – Year-over-year comparison charts (supports `year` parameter)
* `[naws_forecast]` – Multi-day weather forecast

== Installation ==

1. Upload the `xtx-netatmo` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress Admin > Plugins
3. Go to **XTX Netatmo > Settings**
4. Create a Netatmo developer app at [dev.netatmo.com](https://dev.netatmo.com)
5. Enter your Client ID and Client Secret
6. Set the Redirect URI in your Netatmo app to: `https://yoursite.com/wp-admin/admin.php?page=naws-settings`
7. Click "Connect to Netatmo" and authorize
8. Data syncs automatically – add shortcodes to any page

== Frequently Asked Questions ==

= How do I get Netatmo API credentials? =

Visit [dev.netatmo.com](https://dev.netatmo.com), log in with your Netatmo account, create a new application and copy the Client ID and Client Secret.

= How often does data update? =

Netatmo sensors transmit every 5 minutes. The plugin sync interval is configurable (5–1440 minutes). Night mode reduces polling between 23:00–06:00.

= Can I import historical data? =

Yes. The plugin includes a chunk-based historical importer that fetches data from the Netatmo getmeasure API without hitting rate limits.

= Is the REST API secure? =

Yes. The API requires an API key (generated in the admin panel), supports rate limiting, and all endpoints are read-only.

= Are my Netatmo credentials safe? =

All sensitive data (OAuth tokens, client ID, client secret, API keys) is encrypted with AES-256-GCM before being stored in the database.

= Can I customize the appearance? =

Yes. The Appearance page offers 130+ configurable colors with live preview, 4 icon sets, per-sensor colors, chart theming and year comparison palettes.

= Can I back up my weather data? =

Yes. The Export/Import feature lets you download weather data, module configs and all settings as JSON. Ideal for migrating to a new WordPress installation.

= Which forecast providers are supported? =

Open-Meteo (global, default) and Yr.no / MET Norway (optimized for Northern Europe). Both are free and require no API key.

== Screenshots ==

1. Live dashboard with sensor cards and 24h trend charts
2. Year-over-year comparison charts for temperature and rainfall
3. Admin settings page with Netatmo connection status
4. REST API documentation in the admin panel
5. Weather forecast widget
6. Appearance page with live color preview
7. Export / Import page for backups

== Changelog ==

= 1.6.0 =
* WordPress.org compliance: all inline `<script>` blocks converted to `wp_add_inline_script()`
* WordPress.org compliance: all inline `<style>` blocks moved to enqueued stylesheet
* WordPress.org compliance: plugin renamed to "XTX Integration for Netatmo" (trademark)
* WordPress.org compliance: removed direct `<script src>` for Chart.js in frontend templates
* Updated Chart.js vendor from 4.4.0 to 4.5.1
* REST API docs: replaced Google Charts CDN example with bundled Chart.js example
* Fix: AJAX capability-check failures now return proper JSON 403 instead of plain `wp_die()`
* Fix: privacy policy URL in readme.txt corrected

= 1.5.7 =
* Removed GitHub Auto-Updater (WordPress.org compliance – hosted plugins must not include custom updaters)
* Fix: `move_uploaded_file()` replaced with `copy()` (WordPress Coding Standards)
* Fix: `rand()` replaced with `wp_rand()`
* Fix: SVG output escaping documented with `phpcs:ignore`
* Fix: 361 NonPrefixedVariableFound warnings resolved with scoped phpcs disable

= 1.5.6 =
* Security: Client ID and Client Secret now AES-256-GCM encrypted at rest (all 5 secrets fully encrypted)
* Migration updated to encrypt plaintext credentials instead of forcing plaintext
* Removed legacy plaintext-enforcement from init

= 1.5.5 =
* Fix: Escaping fixes for admin views (`esc_attr()` in modules.php, cron-log.php)
* Fix: readme.txt stable tag synchronized with plugin header version
* Fix: Option name inconsistency `naws_token_expires` unified to `naws_token_expiry`

= 1.5.4 =
* Fix: History chart year buttons now wrap on mobile instead of overflowing
* Fix: 24h chart modal overlay positioned correctly inside `.naws-wx` wrapper

= 1.5.3 =
* Fix: Auto-update toggle now visible in WordPress plugin list (registered in `$transient->no_update`)

= 1.5.2 =
* Fix: Plugin URI corrected to `https://www.frank-neumann.de/netatmo-wetter-plugin/`

= 1.5.1 =
* Fix: Dashboard SVG icons no longer rendered as raw source code

= 1.5.0 =
* New: GitHub Auto-Updater via `NAWS_Updater` class (later removed in 1.5.7 for WordPress.org compliance)

= 1.4.3 =
* Plugin renamed: "Netatmo Weather Station" to "XTX Netatmo"
* New: 4 frontend icon sets (Emoji, Outline, Filled, Minimal) selectable in Appearance
* New: Per-sensor icon colors with live preview (7 configurable colors)
* New: Dynamic icon rendering via `NAWS_Icons` class

= 1.4.2 =
* Fix: Forecast provider selection now works correctly (provider-aware cache keys)
* Fix: Forecast source label dynamically shows correct provider name
* New: History shortcode `year` parameter (`[naws_history year="2025"]`)
* Improved: Appearance admin page streamlined, unused sections removed

= 1.4.1 =
* Fix: 24h chart gradient fill restored (hex-to-RGBA conversion with canvas gradient)
* Improved: Appearance page redesigned with live previews for all color groups

= 1.4.0 =
* New: Appearance page with 130+ configurable colors and WordPress Color Picker
* New: `NAWS_Colors` class with centralized color management and caching
* New: Theme colors, accent colors, sensor tile gradients, chart theming, year palette
* New: Reset-to-defaults functionality
* New: 60+ translation strings for color settings
* Improved: All frontend colors use CSS custom properties

= 1.3.0 =
* New: Export / Import feature with full backup and restore
* New: Weather data export as JSON, full backup export (data + modules + settings)
* New: File import with chunked AJAX processing and real-time progress
* Security: API tokens are never included in exports
* New: `NAWS_Export` class with streaming export for large datasets

= 1.2.1 =
* Fix: All `json_encode()` replaced with `wp_json_encode()` (Plugin Check compliance)
* Fix: SQL injection hardening with `$wpdb->prepare()` for DELETE queries
* Fix: `TRUNCATE` replaced with `DELETE FROM` for WordPress compatibility
* Fix: Deprecated `date_i18n()` replaced with `wp_date()`
* Fix: Debug endpoint sanitized (truncated responses, stripped tokens)

= 1.2.0 =
* New: Mobile-first responsive redesign with standardized breakpoints (480/600/768/1024px)
* New: Touch-friendly targets meeting WCAG 44x44px minimum
* New: Responsive wind compass with `clamp()` sizing
* New: Dynamic chart font sizing based on viewport
* Improved: ~400 lines of inline styles extracted to centralized `frontend.css`
* Improved: ID selectors replaced with reusable class selectors

= 1.1.0 =
* New: Central error logging (`NAWS_Logger`) with severity levels and sensitive data redaction
* New: Transient caching layer for database queries (modules, readings, daily summaries)
* New: Adaptive polling with error backoff (doubles interval after 3 failures)
* New: Night mode with reduced polling between 23:00–06:00
* New: Health status indicator in admin dashboard (green/yellow/red)
* New: Frontend error UI and AJAX retry logic with exponential backoff
* New: `naws_data_synced` action hook for extensibility
* Fix: N+1 query in history data replaced with single query
* Fix: Silent DB errors now logged
* Fix: Chart.js blank page prevented with try/catch

= 1.0.2 =
* Removed shortcodes: `[naws_chart]`, `[naws_gauge]`, `[naws_dashboard]`, `[naws_card]` – use `[naws_live]` and `[naws_history]` instead
* Removed: gauge.min.js vendor library and unused templates
* Fix: Fatal error on activation (`spawn_cron()` called too early)

= 1.0.1 =
* New: Forecast provider selection (Open-Meteo + Yr.no / MET Norway)
* New: Norwegian (Bokmal) language with 326 translated keys
* New: File-based language system (one PHP file per language, auto-discovered)
* New: Configurable station name in Settings
* Fix: OAuth flow broken by encryption (auto-migration)
* Fix: OAuth state validation with `hash_equals()` and 10-min expiry
* Fix: Cron stops after plugin update (activation hook + watchdog fix)
* Fix: SVG weather icons stripped by `wp_kses_post()`
* Improved: Plugin Check compliance (126 errors to 0)
* Improved: Vendor JS files bundled in ZIP

= 1.0.0 =
* Initial public release
* Full Netatmo OAuth2 integration with all module types
* Live dashboard with animated sensor cards and 24h charts
* Astronomy: sunrise/sunset, moon phase, next full moon
* Derived weather: feels-like temperature, heat index, dew point
* Year-over-year history charts (temperature, pressure, monthly rainfall)
* 5-day weather forecast via Open-Meteo
* REST API with API key authentication and rate limiting
* AES-256-GCM encryption for all stored credentials
* Full German and English localization
* Configurable units (temperature, rain, wind, pressure)
* Cron watchdog with self-healing for stuck sync jobs
* Historical data importer with batch processing

== Upgrade Notice ==

= 1.5.7 =
WordPress.org compliance release. Removed GitHub Auto-Updater. Plugin Check fixes for move_uploaded_file, rand, SVG escaping.

= 1.5.6 =
Security update: Client ID and Client Secret are now fully AES-256-GCM encrypted at rest.

= 1.4.3 =
Plugin renamed to "XTX Netatmo". New icon sets and per-sensor colors.

= 1.4.0 =
Major visual update: 130+ configurable colors with live preview on new Appearance page.

= 1.3.0 =
New Export / Import feature for full data backup and migration.

= 1.2.0 =
Complete mobile-first responsive redesign. All views optimized for smartphones.

= 1.1.0 =
Error logging, caching, adaptive polling, night mode and health dashboard.

= 1.0.2 =
Removed shortcodes: naws_chart, naws_gauge, naws_dashboard, naws_card. Use [naws_live] and [naws_history] instead.

= 1.0.0 =
Initial release.

== Privacy & External Services ==

This plugin connects to the following external services:

= Netatmo API (api.netatmo.com) =

* **Purpose:** Authenticate via OAuth2, fetch sensor readings and station data
* **Data sent:** OAuth tokens, station/module IDs
* **When:** During initial authentication and every automatic sync cycle
* **Privacy policy:** [https://www.netatmo.com/en-us/legal/privacy-policy](https://www.netatmo.com/en-us/legal/privacy-policy)

= Open-Meteo API (api.open-meteo.com) =

* **Purpose:** Fetch weather forecast data based on station coordinates (default provider)
* **Data sent:** Latitude and longitude of your weather station
* **When:** When the forecast shortcode is displayed (cached for 3 hours)
* **Privacy policy:** [https://open-meteo.com/en/terms](https://open-meteo.com/en/terms)
* **Note:** Open-Meteo is a free, open-source weather API. No API key or registration required.

= Yr.no / MET Norway API (api.met.no) =

* **Purpose:** Fetch weather forecast data (optional provider, selectable in settings)
* **Data sent:** Latitude and longitude of your weather station
* **When:** When the forecast shortcode is displayed and Yr.no is selected as provider (cached for 3 hours)
* **Privacy policy:** [https://www.met.no/en/About-us/privacy](https://www.met.no/en/About-us/privacy)
* **Terms:** [https://developer.yr.no/doc/TermsOfService/](https://developer.yr.no/doc/TermsOfService/)
* **Note:** Free API, requires User-Agent header (sent automatically). No API key needed.

No personal user data (names, emails, IP addresses) is collected or transmitted by this plugin. All sensor data is stored exclusively in your local WordPress database.
