=== XTX Integration for Netatmo ===
Contributors: xylaender
Tags: netatmo, weather, weather station, temperature, chart
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.9.5
Requires PHP: 8.0
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

1. Upload the `xtx-integration-for-netatmo` folder to `/wp-content/plugins/`
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

= 1.9.5 =
* Fix: the User-Agent sent to the Yr.no / MET Norway forecast API named a repository that does not exist, and three other outgoing requests named no contact at all. MET Norway's terms require every client to identify itself with a reachable contact. All requests now send the plugin name, its version and your site address.
* Fix: the Netatmo privacy policy link in this readme was dead and has been replaced. The Netatmo entry also gained a terms of service link and now states that the Client ID and Client Secret are sent to the token endpoint.
* New: a Third-Party Libraries section documenting the two bundled JavaScript libraries (Chart.js and chartjs-adapter-date-fns), their MIT licenses and where to get their unminified source.
* New: the Open-Meteo geocoding service is now documented separately from the forecast service. It is a different host and had no entry of its own.
* Changed: the shipped LICENSE file is now GPL v2, matching the "GPLv2 or later" stated in the plugin header and this readme. The license itself is unchanged.
* Changed: removed a source map reference at the end of the bundled Chart.js file that pointed at a file the package never contained.

= 1.9.4 =
* Changed: the database migration that adds the v1.4 sensor columns no longer assembles its SQL from variables. The column names came from a hardcoded list and were never user input, but Plugin Check's security scanner cannot verify that and flagged the query. Every ALTER TABLE statement is now written out in full, which leaves nothing to flag.
* Changed: that migration checked for each of its eight columns with a separate query. It reads the column list once now. The migration only runs on activation and upgrade, so this changes nothing you would notice.

= 1.9.3 =
* Fix: the history charts stayed empty on some setups. The chart data and the chart script were injected with wp_add_inline_script(), which is silently dropped on those installations, so nothing at all was rendered. Both now use the same reliable pattern the live dashboard already used: a plain JSON data element plus a footer script block.
* Changed: the year buttons in the history charts sit below their chart instead of beside the title. They now use the full chart width and wrap over as many rows as needed, so stations with ten or more years of records no longer push the buttons out to the right. The enlarged chart view follows the same layout.
* Fix: hiding a year from the enlarged chart view left the corresponding button in the small chart looking active, even though the chart below had already dropped that year. Both legends are refreshed together now.

= 1.9.2 =
* Fix: repeated API errors made the plugin poll harder rather than easing off. The interval is meant to double after three failures, but the ceiling on that calculation sat below the longest intervals the settings offer, so a 120-minute interval was halved and a 60-minute one never changed at all.
* Fix: a cron interval that is not one of the available schedules stopped polling completely. The field accepted any value from 5 to 1440 minutes while only seven of them exist as schedules, and an unlisted value such as 45 left the site with no fetch cron. The interval is a dropdown now, and stored values are snapped to the nearest schedule.
* Fix: night mode stopped reducing polling as soon as the API had trouble, because it measured from the last successful sync rather than the last attempt.
* Fix: the dashboard warned about a stale sync during normal night operation. The warning threshold now accounts for the doubled night interval.
* Changed: night mode, daily summaries and the history importer now use the timezone configured in WordPress instead of Europe/Berlin. On sites in other timezones the night window fell on the wrong hours and daily boundaries could be cut at the wrong midnight.
* Changed: the error backoff is described under the interval setting rather than on the night mode checkbox. It applies regardless of night mode.

= 1.9.1 =
* Changed: the settings screen has been reorganised. The connection card now spans the full width and the settings below sit in two balanced columns, so the forecast settings are visible near the top instead of at the bottom. The page is around 600 pixels shorter and no longer has a large empty area beside the connection card.
* Changed: one save button for all settings instead of three. The API credentials keep their own form and button.
* Fix: every save rewrote every setting. Each form carried hidden copies of the fields it did not own, a leftover from before the merge behaviour added in 1.7.0. Saving the units also rewrote the forecast settings and credentials from stale values, so edits made in a second browser tab could be lost. A save now only touches the fields shown in that form.
* Fix: the decrypted client secret was written into the page three times instead of once, and was submitted when saving unrelated settings.

= 1.9.0 =
* New: the sidebar widget's width can be set between 250 and 500 pixels, in Appearance or per placement with `[naws_weather_widget width="400"]`. Icon, figures and spacing scale with it — at 500 pixels the weather icon is 96 pixels instead of 64. The width acts as a maximum, so the widget still shrinks to fit a narrower column.
* Fix: the weather icon at the head of the sidebar widget was frozen. It was rendered through the same call as the small forecast icons, which are deliberately still, and inherited their frozen state. It now animates, while the forecast icons stay still as intended.

= 1.8.3 =
* Fix: the Norwegian interface was only half translated. Norwegian arrived with 326 keys and the plugin has since grown to 612, so everything added later — the forecast, the REST API documentation, the shortcode reference and the live dashboard settings — stayed in English. All 272 missing strings are now translated into Bokmal.

= 1.8.2 =
* Fix: the shortcode reference in the backend showed the wrong descriptions. A leftover block of duplicate entries in the language files overrode the detailed ones, and the entry for `[naws_history]` actually described `[naws_table]`.
* Security: input sanitization on the appearance settings form is now applied directly to the submitted data instead of through an intermediate variable, so automated review tools can see it. The data was sanitized before as well; only the form of the code changed.
* Changed: redirect URLs are built with RFC 3986 encoding.

= 1.8.1 =
* Fix: the weather icon showed "overcast" under a cloudless sky. The forecast provider's weather code lumps all cloud layers together, so a thin veil of cirrus counted the same as a low, closed deck. Cloudiness is now read from the cover percentage per layer, with high cloud weighted down — a cirrus sky reads as fair, not overcast.
* Fix: the corrected cloud figures are also kept as the last known reading, so an outage of the forecast API no longer brings the old behaviour back.

= 1.8.0 =
* New: sidebar widget `[naws_weather_widget]` — weather icon, outdoor temperature, rain and wind, plus a three- or five-day forecast, built for narrow columns
* New: forecast length selectable in the backend, with a live preview at the real width
* Changed: the colourful weather icons introduced in 1.7.0 are now used everywhere, including the forecast shortcode and the dashboard forecast strip
* Fix: saving settings on the Appearance page no longer redirects to Settings — forms now return to the page they were submitted from

= 1.7.0 =
* New: animated weather icon with twelve states — shortcode `[naws_weather_icon size="96"]` and, switchable in the backend, above the live dashboard
* New: the icon combines station readings with the forecast — your own measurements win, the forecast only fills in what the station cannot measure (cloud cover, thunderstorms, and precipitation if you have no rain gauge)
* New: rain-versus-snow is decided by wet-bulb temperature rather than air temperature, so snow is still recognised at 3–4 °C in dry air
* New: six thresholds in the settings (heavy rain, snow, fog humidity and spread, storm wind, dashboard placement)
* New: current conditions are fetched separately from the daily forecast, on a 30-minute cache, so the icon reflects the weather now instead of a whole-day summary
* Fix: saving one settings form no longer resets the others — saving your Netatmo credentials used to silently reset language, units, sync interval and all forecast settings back to their defaults
* Fix: REST API routes were not running their authentication callback — with the REST API enabled the endpoints, including station coordinates, were reachable without an API key

= 1.6.5 =
* Compatibility: tested against WordPress 7.0
* Compatibility: minimum PHP requirement raised to 8.0 (`Requires PHP: 8.0`)
* Fix: history chart no longer emits a PHP 8.1 deprecation notice (`substr(): Passing null`) when the daily-summary table is still empty — the year range now falls back to the current year instead of year 0

= 1.6.4 =
* Fix: `$file['size']` in import handler now wrapped in `intval()` for explicit sanitization (WordPress.org compliance)
* Fix: `SHOW COLUMNS` query in `class-naws-astro.php` now uses `$wpdb->prepare()` consistently with the rest of the codebase
* Fix: `naws_svg_kses_args()` helper properly available at plugin load time; resolves fatal error on admin dashboard when function was called before class-naws-helpers.php was loaded

= 1.6.3 =
* WordPress.org compliance: all file-scope `ob_start()` / `ob_get_clean()` patterns removed from admin views and frontend templates
* WordPress.org compliance: PHP values injected into inline scripts via `wp_add_inline_script( $handle, $data, 'before' )` with `wp_json_encode()` instead of echoing PHP inside JS blocks
* WordPress.org compliance: all JS strings previously echoed from PHP now served via `nawsAdmin.strings` (localized with `wp_localize_script`) – eliminates PHP interpolation in JavaScript
* WordPress.org compliance: icon SVGs sanitized with `wp_kses()` before JSON-encoding; `NAWS_Icons::get_js_object()` (raw JS literal) replaced by `wp_json_encode( NAWS_Icons::get_set() )`
* Added `ls_saving`, `ls_saved`, `ls_error` strings to `nawsAdmin` localization (live-settings page)

= 1.6.2 =
* WordPress.org compliance: file upload sanitized with `sanitize_file_name()` and `move_uploaded_file()`
* WordPress.org compliance: all `ob_start()` blocks closed with `ob_get_clean()` in same scope
* WordPress.org compliance: all remaining inline `<script>`/`<style>` blocks converted to `wp_add_inline_script()` / `wp_add_inline_style()`
* WordPress.org compliance: dynamic SQL column names validated against explicit whitelist before query execution
* Fix: `phpcs` annotation for `naws_appearance` input sanitization made explicit (sanitized via `NAWS_Colors::sanitize()`)

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

= 1.6.3 =
WordPress.org compliance release. All file-scope ob_start() patterns replaced with wp_add_inline_script(). PHP values passed to JS via wp_json_encode() instead of direct echoing.

= 1.6.2 =
WordPress.org compliance release. Input sanitization, ob_start() fixes, inline script/style removal, SQL whitelist validation.

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
* **Data sent:** The Client ID and Client Secret of the Netatmo application you created, in exchange for an access token; afterwards the access or refresh token with every request, plus the station and module IDs whose measurements are being requested
* **When:** During initial authentication, on every automatic sync cycle, on every token refresh, and while a historical import is running
* **Terms of service:** [https://dev.netatmo.com/legal](https://dev.netatmo.com/legal)
* **Privacy policy:** [https://legals.netatmo.com/?goto=privacy](https://legals.netatmo.com/?goto=privacy)

= Open-Meteo API (api.open-meteo.com) =

* **Purpose:** Fetch weather forecast data based on station coordinates (default provider)
* **Data sent:** Latitude and longitude of your weather station
* **When:** When the forecast shortcode is displayed (cached for 3 hours)
* **Terms and privacy:** [https://open-meteo.com/en/terms](https://open-meteo.com/en/terms)
* **Note:** Open-Meteo is a free, open-source weather API. No API key or registration required.

= Open-Meteo Geocoding API (geocoding-api.open-meteo.com) =

* **Purpose:** Turn a place into coordinates, and coordinates into a place name for the forecast heading
* **Data sent:** In "manual" location mode, the city name or postal code entered in the plugin settings. In "automatic" mode, the latitude and longitude of your weather station, rounded to two decimal places, in order to look up the name of the nearest place.
* **When:** In manual mode whenever no cached result exists (cached for 7 days). In automatic mode exactly once — the resolved name is stored in the plugin settings and never looked up again.
* **Terms and privacy:** [https://open-meteo.com/en/terms](https://open-meteo.com/en/terms)
* **Documentation:** [https://open-meteo.com/en/docs/geocoding-api](https://open-meteo.com/en/docs/geocoding-api)

= Yr.no / MET Norway API (api.met.no) =

* **Purpose:** Fetch weather forecast data (optional provider, selectable in settings)
* **Data sent:** Latitude and longitude of your weather station
* **When:** When the forecast shortcode is displayed and Yr.no is selected as provider (cached for 3 hours)
* **Privacy policy:** [https://www.met.no/en/About-us/privacy](https://www.met.no/en/About-us/privacy)
* **Terms:** [https://developer.yr.no/doc/TermsOfService/](https://developer.yr.no/doc/TermsOfService/)
* **Note:** Free API, no API key needed. MET Norway's terms require every client to identify itself, so requests to this service carry a User-Agent naming the plugin, its version and your site address — that address is how MET Norway would reach you before restricting a misbehaving client. This is sent to api.met.no only, and only while Yr.no is the selected provider.

No personal user data (names, emails, IP addresses) is collected or transmitted by this plugin. All sensor data is stored exclusively in your local WordPress database.

== Third-Party Libraries ==

Two JavaScript libraries are bundled with this plugin, both under the MIT license, which is GPL-compatible. They ship in their minified distribution builds; the unminified source and the build tooling for each are available at the links below.

= Chart.js 4.5.1 =

* **File:** `assets/vendor/chart.umd.min.js`
* **License:** MIT
* **Homepage:** [https://www.chartjs.org](https://www.chartjs.org)
* **Source and build tools:** [https://github.com/chartjs/Chart.js](https://github.com/chartjs/Chart.js) — the exact release bundled here is [v4.5.1](https://github.com/chartjs/Chart.js/releases/tag/v4.5.1)
* **Used for:** All charts — 24h trend lines on the live dashboard and the year-over-year history charts

= chartjs-adapter-date-fns 3.0.0 =

* **File:** `assets/vendor/chartjs-adapter-date-fns.bundle.min.js`
* **License:** MIT
* **Source and build tools:** [https://github.com/chartjs/chartjs-adapter-date-fns](https://github.com/chartjs/chartjs-adapter-date-fns) — the exact release bundled here is [v3.0.0](https://github.com/chartjs/chartjs-adapter-date-fns/releases/tag/v3.0.0)
* **Used for:** Time axis formatting in the charts. This is the bundled build, which includes date-fns (also MIT).

No other third-party code is included. No library is loaded from a CDN; everything is served from your own installation. Libraries that ship with WordPress itself are used from WordPress and are not bundled.
