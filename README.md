Demo: https://www.frank-neumann.de/netatmo-wetter-plugin/


=== Netatmo Weather Station ===
Contributors: xylaender
Tags: netatmo, weather, weather station, temperature, chart
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, animated charts, history and weather forecasts.

== Description ==

**Netatmo Weather Station** connects your Netatmo hardware to WordPress. It reads all sensor data via the official Netatmo API, stores readings in your local database and displays them with beautiful live dashboards, animated charts and weather forecasts.

= Key Features =

* **Full Netatmo Integration** – OAuth2 authentication, automatic sync, all module types supported (Base, Outdoor, Wind, Rain, Indoor)
* **Live Dashboard** – Real-time sensor cards with animated counters, 24h trend charts, pressure trend indicator, wind compass, CO₂ air quality levels
* **Astronomy** – Sunrise/sunset, moon phase with illumination, next full moon
* **Derived Weather Data** – Feels-like temperature, heat index, dew point, wind chill
* **Historical Charts** – Year-over-year comparison for temperature, pressure and rainfall with interactive legend
* **Weather Forecast** – 5-day forecast based on station coordinates via Open-Meteo
* **REST API** – Read-only JSON API with key authentication and rate limiting for external tools (Google Charts, Grafana, etc.)
* **Encrypted Storage** – All credentials (OAuth tokens, API keys) are AES-256-GCM encrypted at rest
* **Configurable Units** – °C/°F, mm/inch, mbar/inHg/mmHg, km/h/m/s/mph/kn
* **Bilingual** – Full German and English interface
* **7 Shortcodes** – Dashboard, live widget, charts, gauge, cards, history table, forecast

= Supported Modules =

* **NAMain** – Base Station (Temperature, Humidity, CO₂, Noise, Pressure)
* **NAModule1** – Outdoor (Temperature, Humidity)
* **NAModule2** – Wind (Speed, Direction, Gusts)
* **NAModule3** – Rain Gauge (Hourly, Daily, Rolling 24h)
* **NAModule4** – Additional Indoor (Temperature, Humidity, CO₂)

= Shortcodes =

* `[naws_live]` – Live sensor tiles with 24h trend charts and forecast
* `[naws_infobar]` – Astronomy bar with sunrise, moon phase, felt temperature
* `[naws_value]` – Single inline sensor value
* `[naws_history]` – Year-over-year comparison charts
* `[naws_forecast]` – Multi-day weather forecast

== Installation ==

1. Upload the `netatmo-weather-station` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress Admin → Plugins
3. Go to **Weather Station → Settings**
4. Create a Netatmo developer app at [dev.netatmo.com](https://dev.netatmo.com)
5. Enter your Client ID and Client Secret
6. Set the Redirect URI in your Netatmo app to: `https://yoursite.com/wp-admin/admin.php?page=naws-settings`
7. Click "Connect to Netatmo" and authorize
8. Data syncs automatically – add shortcodes to any page

== Frequently Asked Questions ==

= How do I get Netatmo API credentials? =

Visit [dev.netatmo.com](https://dev.netatmo.com), log in with your Netatmo account, create a new application and copy the Client ID and Client Secret.

= How often does data update? =

Netatmo sensors transmit every 5 minutes. The plugin sync interval is configurable (5–1440 minutes).

= Can I import historical data? =

Yes. The plugin includes a chunk-based historical importer that fetches data from the Netatmo getmeasure API without hitting rate limits.

= Is the REST API secure? =

Yes. The API requires an API key (generated in the admin panel), supports rate limiting, and all endpoints are read-only.

= Are my Netatmo credentials safe? =

All sensitive data (OAuth tokens, client secret, API keys) is encrypted with AES-256-GCM before being stored in the database.

== Screenshots ==

1. Live dashboard with sensor cards and 24h trend charts
2. Year-over-year comparison charts for temperature and rainfall
3. Admin settings page with Netatmo connection status
4. REST API documentation in the admin panel
5. Weather forecast widget

== Changelog ==

[1.1.0] – 2026-03-14
Added
Central error logging (NAWS_Logger): Unified logging with severity levels (error, warning, info), stored in naws_error_log option with rolling window (max 200 entries) and automatic sensitive data redaction
Transient caching layer: Database queries for modules (1h), latest readings (5min), rain 24h (5min), readings (10min) and daily summaries (1h) are now cached via WordPress Transient API
Cache invalidation: All caches are automatically flushed after each successful data sync
Adaptive polling with error backoff: After 3 consecutive sync failures the polling interval doubles (max 60 min), resets immediately on first success
Night mode: Reduced polling between 23:00–06:00 (Europe/Berlin) – configurable in Settings
Health status indicator: Admin dashboard shows sync health (green/yellow/red) with status message and recent error count
Frontend error UI: AJAX requests show user-facing error messages in the DOM instead of silent console errors
AJAX retry logic: Transient network errors (status 0 or 500+) are retried up to 2 times with exponential backoff
WordPress action hook: naws_data_synced fires after each successful sync for extensibility
Fixed
N+1 query in history data: Replaced per-year database loop with single query + PHP-side grouping by year
Silent DB errors: All $wpdb->query(), $wpdb->get_results() and $wpdb->get_var() calls now check $wpdb->last_error and log failures
AJAX toggle_module() missing return: Added early return after wp_send_json_error() to prevent further execution
Chart.js blank page: Wrapped new Chart() calls in try/catch to prevent white pages on JS errors
REST API empty modules: endpoint_station() now returns proper WP_Error (404) when no modules are found
Improved
Database error handling: upsert_by_station(), bulk_insert_readings(), compute_daily_summary() all return/propagate errors instead of silently failing
AJAX error responses: save_live_settings(), clear_daily_summary(), db_check() validate operation results and return specific error messages
Importer error handling: upsert_by_station() logs DB failures, get_station_id() logs fallback usage
Tested up to: WordPress 6.9.4 / PHP 8.4

= 1.0.2 =
* **Removed: Shortcodes** `[naws_chart]`, `[naws_gauge]`, `[naws_dashboard]`, `[naws_card]` – Use `[naws_live]` and `[naws_history]` instead.
* **Removed: gauge.min.js** vendor library and `card.php` template (no longer needed).
* **Fix: Fatal error on activation** – `spawn_cron()` was called too early during `plugins_loaded`.

= 1.0.1 =
* **New: Forecast provider selection** – Choose between Open-Meteo (global) and Yr.no / MET Norway (Northern Europe) in Settings
* **New: Norwegian (Bokmål) language** – Full translation with 326 translated keys, rest falls back to English
* **New: File-based language system** – Languages are now separate files in /languages/. Adding a new language = adding one PHP file, no core code changes needed. Dropdown auto-discovers available languages.
* **New: Configurable station name** – Set a custom name in Settings, displayed as default title in live dashboard and shortcodes (fallback: WordPress site title)
* **Fix: OAuth flow** – Removed AES encryption from Client ID/Secret (broke OAuth redirect). Tokens (access, refresh, API key) remain encrypted. Auto-migration decrypts previously encrypted credentials.
* **Fix: OAuth state validation** – Replaced transient-based state with wp_option (survives cache purges). Added timing-safe hash_equals() comparison. State no longer overwritten during callback page render.
* **Fix: Disconnect button** – Added missing action URL (admin-post.php) to the disconnect form.
* **Fix: Forecast manual location** – Open-Meteo Geocoding API doesn't support postcodes. Input is now auto-cleaned: "Leipzig / 04105" → "Leipzig".
* **Fix: Cron stops after plugin update** – Added register_activation_hook for NAWS_Cron::schedule(). Watchdog now schedules next run in the future (not "now") and calls spawn_cron(). Silent do_fetch() abort now logs reason.
* **Fix: Manual sync logging** – "Sync Now" button now writes entries to the Cron Log.
* **Fix: SVG weather icons stripped** – Replaced wp_kses_post() with direct output + phpcs:ignore for trusted SVG from internal methods.
* **Fix: "Uhr" hardcoded in English** – Replaced with language key `time_suffix` (DE: "Uhr", EN/NO: empty).
* **Fix: Plugin Check compliance** – Reduced errors from 126 to 0. Added esc_html/esc_attr, replaced date() with wp_date()/gmdate(), added phpcs:ignore for legitimate direct DB queries and SVG output.
* **Improvement: Vendor JS files bundled** – chart.umd.min.js, chartjs-adapter-date-fns, gauge.min.js now included in ZIP (no manual download needed after updates).
* **Improvement: Yr.no privacy disclosure** added to readme.txt.

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
* 5 shortcodes for flexible frontend display
* Configurable units (temperature, rain, wind, pressure)
* Cron watchdog with self-healing for stuck sync jobs
* Historical data importer with progress tracking

== Upgrade Notice ==

= 1.0.2 =
Removed shortcodes: naws_chart, naws_gauge, naws_dashboard, naws_card. Use [naws_live], [naws_value] and [naws_history] instead. Fixed fatal error on activation.

= 1.0.1 =
Multi-provider forecast (Open-Meteo + Yr.no), Norwegian language, file-based i18n system, configurable station name. Critical fixes for OAuth, cron scheduling, and Plugin Check compliance. Vendor JS files now bundled.

= 1.0.0 =
Initial release.

== Privacy & External Services ==

This plugin connects to the following external services:

= Netatmo API (api.netatmo.com) =

* **Purpose:** Authenticate via OAuth2, fetch sensor readings and station data
* **Data sent:** OAuth tokens, station/module IDs
* **When:** During initial authentication and every automatic sync cycle
* **Privacy policy:** [https://www.netatmo.com/en-gb/legal/privacy](https://www.netatmo.com/en-gb/legal/privacy)

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
