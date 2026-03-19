=== XTX Netatmo ===
Contributors: xylaender
Tags: netatmo, weather, weather station, windrose, dashboard, climate
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.5.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional Netatmo integration for WordPress. Features live dashboards, animated charts, a visual design editor, and historical data sync.

== Description ==

**XTX Netatmo** is the most comprehensive solution to connect your Netatmo hardware to WordPress. It goes far beyond simple data display by storing all readings locally to provide deep insights, year-over-year comparisons, and beautiful visualizations.

The plugin features a **built-in Design Editor**, allowing you to customize colors, icons, and layouts to match your theme perfectly without touching any code.

= Key Features =

* **Full Netatmo Integration** – Secure OAuth2 authentication with automatic token refresh.
* **Visual Design Editor** – Customize the frontend look (colors, icons, styles) directly in the backend.
* **Advanced Dashboards** – Real-time sensor cards with animated counters, 24h trends, and a graphical **Wind Compass**.
* **Historical Data & Charts** – Year-over-year comparisons for temperature, pressure, and rainfall with interactive legends.
* **Data Privacy & Security** – All credentials (OAuth tokens, API keys) are **AES-256-GCM encrypted** at rest.
* **Astronomy & Derived Data** – Sunrise/sunset, moon phases, feels-like temperature, dew point, and wind chill.
* **Local Storage & Import** – Chunk-based historical importer to fetch past data without hitting API rate limits.
* **Weather Forecast** – 5-day forecast based on your station's coordinates (via Open-Meteo).
* **Developer Friendly** – Includes a read-only REST API (JSON) with key authentication for external tools like Grafana.

= Supported Modules =
Supports all Netatmo Smart Home modules: Base Station (NAMain), Outdoor (NAModule1), Wind Gauge (NAModule2), Rain Gauge (NAModule3), and additional Indoor Modules (NAModule4).

== Installation ==

1. Go to **Plugins > Add New** in your WordPress dashboard.
2. Click **Upload Plugin** and select the `xtx-netatmo.zip` file.
3. Activate the plugin.
4. Navigate to **XTX Netatmo > Settings**.
5. Create a developer app at [dev.netatmo.com](https://dev.netatmo.com) and enter your Client ID and Client Secret.
6. Copy the **Redirect URI** shown in the plugin settings to your Netatmo app configuration.
7. Click "Connect to Netatmo", authorize, and you are ready to go!

== Frequently Asked Questions ==

= How often does the data update? =
Netatmo sensors transmit every 5 minutes. The plugin's sync interval is fully configurable (from 5 minutes up to 24 hours).

= Is the connection stable? =
Yes. The plugin handles the OAuth2 Refresh Token automatically. Once authorized, it runs indefinitely without manual intervention.

= Can I customize the look? =
Absolutely. The plugin includes a dedicated Design Editor in the backend where you can change colors, choose icons, and adjust the layout.

= Is my data secure? =
Yes. We take security seriously. All sensitive API credentials and tokens are stored using AES-256-GCM encryption in your database.

== Screenshots ==

1. Professional Live Dashboard with animated sensor cards and 24h trends.
2. The Visual Design Editor to customize the frontend appearance.
3. Interactive year-over-year historical comparison charts.
4. Detailed Windrose and Rain Gauge visualization.
5. Admin settings and secure connection status.

== Changelog ==

= 1.5.4 =
* Current stable release.
* Optimized OAuth2 token handling and encryption.
* Enhanced Design Editor features.
* Official WordPress Repository preparation.

= 1.0.2 =
* Added support for Wind and Rain modules.
* Improved historical data import.


== Changelog ==


## [1.2.0] – 2026-03-14

### Added
- **Mobile-first responsive redesign**: Complete rewrite of all CSS from desktop-first (`max-width`) to mobile-first (`min-width`) media queries
- **Standardized breakpoints**: 480px (sm), 600px (md), 768px (lg), 1024px (xl)
- **Responsive grids**: All grid layouts start at 1 column on mobile and progressively enhance (1→2→auto-fill)
- **Touch-friendly targets**: All buttons and interactive elements now meet WCAG minimum of 44×44px
- **Responsive compass**: Wind rose uses `clamp(120px, 35vw, 160px)` instead of fixed 160px, SVGs use percentage-based sizing
- **Responsive chart fonts**: Dynamic font sizing based on viewport width (9px/10px/11px) with debounced resize handlers
- **CSS custom properties for grids**: Forecast grids use `--naws-fc-cols` and `--naws-fc-days` variables for dynamic column counts

### Improved
- **Inline styles extracted**: ~400 lines of `<style>` blocks removed from `live.php`, `forecast.php`, and `history.php` into centralized `frontend.css`
- **ID selectors replaced**: Template-scoped `#widget_id` selectors replaced with reusable class selectors (`.naws-wx`, `.naws-fc-wrap`, `.naws-hist`)
- **Forecast cards**: Inline `style=` attributes replaced with semantic CSS classes (`.naws-fcc-day`, `.naws-fcc-temps`, `.naws-fcc-meta`, etc.)
- **History modal**: Canvas height changed from fixed `420px` to `clamp(200px, 50vh, 420px)` for mobile usability
- **Admin tables**: Added `overflow-x: auto` for horizontal scrolling on small screens
- **Version bump to 1.2.0**

## [1.1.0] – 2026-03-14

### Added
- **Central error logging** (`NAWS_Logger`): Unified logging with severity levels (error, warning, info), stored in `naws_error_log` option with rolling window (max 200 entries) and automatic sensitive data redaction
- **Transient caching layer**: Database queries for modules (1h), latest readings (5min), rain 24h (5min), readings (10min) and daily summaries (1h) are now cached via WordPress Transient API
- **Cache invalidation**: All caches are automatically flushed after each successful data sync
- **Adaptive polling with error backoff**: After 3 consecutive sync failures the polling interval doubles (max 60 min), resets immediately on first success
- **Night mode**: Reduced polling between 23:00–06:00 (Europe/Berlin) – configurable in Settings
- **Health status indicator**: Admin dashboard shows sync health (green/yellow/red) with status message and recent error count
- **Frontend error UI**: AJAX requests show user-facing error messages in the DOM instead of silent console errors
- **AJAX retry logic**: Transient network errors (status 0 or 500+) are retried up to 2 times with exponential backoff
- **WordPress action hook**: `naws_data_synced` fires after each successful sync for extensibility

### Fixed
- **N+1 query in history data**: Replaced per-year database loop with single query + PHP-side grouping by year
- **Silent DB errors**: All `$wpdb->query()`, `$wpdb->get_results()` and `$wpdb->get_var()` calls now check `$wpdb->last_error` and log failures
- **AJAX `toggle_module()` missing return**: Added early return after `wp_send_json_error()` to prevent further execution
- **Chart.js blank page**: Wrapped `new Chart()` calls in try/catch to prevent white pages on JS errors
- **REST API empty modules**: `endpoint_station()` now returns proper WP_Error (404) when no modules are found

### Improved
- **Database error handling**: `upsert_by_station()`, `bulk_insert_readings()`, `compute_daily_summary()` all return/propagate errors instead of silently failing
- **AJAX error responses**: `save_live_settings()`, `clear_daily_summary()`, `db_check()` validate operation results and return specific error messages
- **Importer error handling**: `upsert_by_station()` logs DB failures, `get_station_id()` logs fallback usage
- **Tested up to**: WordPress 6.9.4 / PHP 8.4

## [1.0.2] – 2026-03-10

### Removed
- **Shortcode `[naws_chart]`** – Standalone chart widget removed (charts remain in `[naws_live]` and `[naws_history]`)
- **Shortcode `[naws_gauge]`** – Gauge widget removed
- **Shortcode `[naws_dashboard]`** – Dashboard widget removed (use `[naws_live]` instead)
- **Shortcode `[naws_card]`** – Single metric card removed (use `[naws_value]` instead)
- **gauge.min.js** vendor library – No longer needed
- Templates: `chart.php`, `dashboard.php`, `gauge.php`, `card.php`

### Fixed
- **Fatal error on activation**: `spawn_cron()` was called during `plugins_loaded` when `WP_CRON_LOCK_TIMEOUT` is not yet defined. Removed `spawn_cron()` call.

## [1.0.1] – 2026-03-10

### Added
- **Forecast provider selection**: Choose between Open-Meteo (global) and Yr.no / MET Norway (Northern Europe)
- **Norwegian (Bokmål) language**: 326 translated keys (`languages/no.php`)
- **File-based language system**: Each language is a separate PHP file in `/languages/`. Auto-discovered by settings dropdown. Adding a language = adding one file.
- **Configurable station name**: New field in Settings → General. Used as default title in live dashboard and shortcodes. Fallback: WordPress site title.
- **Manual sync logging**: "Sync Now" button now writes entries to the Cron Log
- **Yr.no privacy disclosure** in `readme.txt`

### Fixed
- **OAuth flow broken by encryption**: Removed AES encryption from Client ID/Secret (appeared as `naws_enc:...` in OAuth URL). Tokens remain encrypted. Auto-migration decrypts on update.
- **OAuth state validation**: Replaced unreliable transient with `wp_option`. Added `hash_equals()` + 10-min expiry. Prevents state overwrite during callback page render.
- **Disconnect button non-functional**: Missing `action="admin-post.php"` in form.
- **Forecast manual location fails**: Open-Meteo Geocoding API rejects postcodes. Input now auto-cleaned (`"Leipzig / 04105"` → `"Leipzig"`).
- **Cron stops after plugin update**: Added `register_activation_hook` for `NAWS_Cron::schedule()`. Watchdog schedules next run in future (not `time()`), calls `spawn_cron()`, and runs immediate sync.
- **Silent cron abort**: `do_fetch()` no longer returns silently when credentials missing – always logs reason.
- **SVG weather icons stripped**: `wp_kses_post()` removes SVG tags. Replaced with direct output + `phpcs:ignore` for trusted internal SVGs.
- **"Uhr" hardcoded in English**: Replaced with language key `time_suffix` (DE: "Uhr", EN/NO: empty).
- **REST API docs parse error**: `phpcs:ignore` comments had swallowed semicolons and closing parentheses.
- **Shortcodes page parse error**: Extra closing parenthesis in `esc_html()` wrapper.

### Improved
- **Plugin Check compliance**: 126 errors → 0. Added `esc_html()`/`esc_attr()`, replaced `date()` with `wp_date()`/`gmdate()`, added `phpcs:ignore` for legitimate patterns.
- **Vendor JS files bundled**: `chart.umd.min.js`, `chartjs-adapter-date-fns.bundle.min.js`, `gauge.min.js` now included in ZIP. No manual download after updates.
- **Language class refactored**: From 1035-line monolithic file to 195-line loader + separate language files. Scales to unlimited languages.

## [1.0.0] – 2026-03-09

### Added
- Initial public release
- Full Netatmo OAuth2 integration with all module types
- Live dashboard with animated sensor cards and 24h charts
- Astronomy: sunrise/sunset, moon phase, next full moon, supermoon, eclipses
- Derived weather: feels-like temperature (Steadman/BoM), heat index, dew point
- Year-over-year history charts (temperature, pressure, monthly rainfall)
- 5-day weather forecast via Open-Meteo
- REST API with API key authentication and rate limiting
- AES-256-GCM encryption for stored tokens
- Full German and English localization
- 7 shortcodes for flexible frontend display
- Configurable units (temperature, rain, wind, pressure)
- Cron watchdog with self-healing for stuck sync jobs
- Historical data importer with batch processing


No personal user data (names, emails, IP addresses) is collected or transmitted by this plugin. All sensor data is stored exclusively in your local WordPress database.
