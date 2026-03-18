# Changelog

All notable changes to the XTX Netatmo plugin are documented here.

## [1.5.0] – 2026-03-18

### Added
- **GitHub Auto-Updater**: New `NAWS_Updater` class enables automatic plugin updates via GitHub Releases. WordPress checks the `Xyla1512/Netatmo` repository for new release tags and offers one-click updates in the admin panel — no WordPress.org listing required.
  - Checks `https://api.github.com/repos/Xyla1512/Netatmo/releases/latest` every 12 hours
  - Supports attached `.zip` assets or GitHub's auto-generated source ZIP as fallback
  - "View details" popup shows release notes from GitHub
  - Post-install hook ensures the plugin folder name stays correct after extraction
  - Optional: private repo support via `github_token` in settings

## [1.4.3] – 2026-03-18

### Changed
- **Plugin renamed**: "Netatmo Weather Station" → "XTX Netatmo" across all display names, language files, file headers, and documentation.

### Added
- **4 Frontend Icon Sets**: Emoji (default), Outline, Filled, and Minimal icon sets selectable in the Appearance settings. Each set provides 7 sensor icons (temperature, humidity, pressure, wind, rain, CO₂, noise) with distinct visual styles.
- **New "Icons" tab in Appearance**: Visual radio-button selector showing all 4 icon sets with real icon previews. No more guessing — see exactly how each set looks before selecting.
- **Per-sensor icon colors**: 7 new configurable colors (one per sensor type) with live preview. These colors control both the icon tint and the card accent bar in the Live Dashboard.
- **Dynamic icon rendering**: `live.php` now loads icons dynamically from `NAWS_Icons` class instead of hardcoded SVGs. The `data-icon-set` attribute on `.naws-wx` enables CSS-based rendering variants (filled uses `fill`, minimal uses thinner strokes).

### Changed
- **Sensor accent colors unified**: Per-card accent colors (`.c-temp`, `.c-wind`, etc.) now reference the new `--naws-ico-*` CSS variables, ensuring consistent colors between icons and card accents.

## [1.4.2] – 2026-03-17

### Fixed
- **Forecast provider ignored**: Selecting Yr.no as forecast provider still fetched data from Open-Meteo. Root cause: single cache key was shared between providers, so cached Open-Meteo data was served even after switching. Fix: provider-aware cache keys (`naws_forecast_data_open_meteo` / `_yr_no`), `flush_cache()` now clears both, and `normalise()` for Open-Meteo now includes `'provider'` key.
- **Forecast source label hardcoded**: Templates (live.php, forecast.php) always showed "Open-Meteo.com" regardless of selected provider. Now dynamically displays the correct provider name.

### Added
- **History shortcode `year` parameter**: `[naws_history year="2025"]` shows data for a specific year only. Supports comma-separated values (`year="2023,2025"`). Without parameter, behavior is unchanged (all years).

### Improved
- **Appearance admin page streamlined**: Removed "Akzent-Farben" and "Sensor-Kachel-Farben" sections (unused). All panels now use consistent `naws-panel-header`/`naws-panel-body` wrappers for proper padding. Cleaned up unused PHP, JS, and CSS code.

## [1.4.1] – 2026-03-15

### Fixed
- **24h chart gradient fill broken**: Charts showed solid flat colors instead of gradient fill after color system migration. Root cause: `makeDataset()` used `rgb()`→`rgba()` string replacement which failed on hex color values from `NAWS_Colors`. Now uses proper `hexToRgba()` conversion with `createLinearGradient()` on the actual canvas context for smooth top-to-bottom gradient fill.

### Improved
- **Appearance admin page redesigned with live previews**: Each color group now shows a real-time preview panel next to the color pickers
  - **Theme colors**: Mini-card preview with labeled text variants (title, value, muted, meta)
  - **Accent colors**: Swatch grid with title-gradient preview
  - **Sensor tiles**: 8 interactive tile previews update gradient bars live when colors change
  - **24h chart colors**: SVG line+fill preview per sensor showing the actual line color and gradient fill
  - **Chart theming**: Annotated SVG mock chart showing grid lines, axis labels, tooltip, and axis title
  - **Year comparison**: Horizontal bar chart with 15 year-colored bars

## [1.4.0] – 2026-03-15

### Added
- **Appearance / Color Customization**: New admin page "Appearance" with comprehensive color picker for 130+ configurable colors
  - Base theme colors (background, surfaces, text variants, borders, shadows)
  - Accent colors (primary, secondary, success, warning, danger)
  - Sensor tile gradient colors for all 8 sensor types (temperature, humidity, pressure, CO2, noise, wind, rain, health)
  - 24-hour chart line colors per sensor type
  - Chart theming (grid lines, axis labels, tooltips, axis titles)
  - Year comparison palette with 15 distinct colors for multi-year history charts
  - Icon set selection (emoji, outline, filled, minimal)
  - Reset-to-defaults functionality
- New class `NAWS_Colors` with centralized color management, caching, and hex color sanitization
- New admin view `admin/views/appearance.php` with WordPress Color Picker integration
- Helper methods for templates: `get_sensor_colors()`, `get_history_palette()`, `get_chart_theme()`, `get_inline_css()`
- 60+ new translation strings in German and English for all color settings

### Improved
- **Frontend CSS architecture**: All colors use CSS custom properties, dynamically overridden via `NAWS_Colors::get_inline_css()`
- **Database version**: Bumped to 1.4

## [1.3.0] – 2026-03-15

### Added
- **Export / Import feature**: New admin page under "Export / Import" menu
  - **Weather Data Export**: Download all daily summary data (temperature, pressure, rain, etc.) as JSON file
  - **Full Backup Export**: Download weather data + module configuration + all plugin settings as JSON – ideal for migrating to a new WordPress installation
  - **File Import**: Upload previously exported JSON files to restore data, with chunked AJAX processing for large files and real-time progress feedback
  - Security: API tokens, refresh tokens and API keys are **never** included in exports
  - Idempotent imports: re-importing the same file safely updates existing records (ON DUPLICATE KEY UPDATE)
  - File validation: JSON structure, export version and data integrity are verified before import begins
- New class `NAWS_Export` with streaming export (memory-efficient for large datasets)
- New admin view `admin/views/export.php` with two-column layout matching existing plugin design
- Translation strings for German and English

## [1.2.1] – 2026-03-15

### Fixed
- **WordPress Plugin Check compliance**: Replaced all `json_encode()` calls with `wp_json_encode()` across templates and admin views
- **SQL injection hardening**: DELETE query in activation cleanup now uses `$wpdb->prepare()` with parameterised placeholders
- **TRUNCATE replaced with DELETE**: `clear_daily_summary()` now uses `DELETE FROM` instead of `TRUNCATE TABLE` for better WordPress compatibility
- **Deprecated `date_i18n()` replaced**: All occurrences replaced with `wp_date()` (deprecated since WordPress 5.3)
- **Debug endpoint sanitised**: `import_debug()` now truncates raw API responses to 2000 chars and strips access tokens from output

### Improved
- **phpcs compliance**: Removed file-level `phpcs:disable` from `class-naws-ajax.php`; replaced with targeted inline `phpcs:ignore` comments on each affected line
- **Database class documentation**: Added detailed justification comment block for file-level phpcs suppressions in `class-naws-database.php`

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
