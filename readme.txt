=== XTX Integration for Netatmo ===
Contributors: xylaender
Tags: netatmo, weather, weather station, temperature, chart
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.1.6
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
* **18 Shortcodes** – Dashboard, current readings, infobar, single value, computed value, sparkline, sparkline tiles, history charts, heatmap, records, this day in earlier years, sun path, wind rose, forecast, rain radar, table, widget, weather icon
* **Export / Import** – Full backup and restore of weather data, modules and settings
* **E-Mail Notifications** – battery, radio and Wi-Fi, station offline, failed syncs, frost, heat, gusts, rain, rain starting, CO₂; per-rule thresholds, one mail per state change and an all-clear when the state ends, and a master switch
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

* `[naws_live]` – Live sensor tiles with 24h trend charts and wind rose
* `[naws_current]` – Current readings of one or all modules as tiles or a list (`module_id`, `parameters`, `layout`, `title`)
* `[naws_infobar]` – Astronomy bar with sunrise, moon phase, felt temperature
* `[naws_value]` – Single inline sensor value
* `[naws_calc]` – Single computed value (dew point, felt temperature, sunrise, moon phase, …); full list on the Shortcodes page in the backend
* `[naws_history]` – Year-over-year comparison charts (supports `year` parameter)
* `[naws_heatmap]` – One year of outdoor daily average temperature as a calendar grid, one tile per day, with a year selector (`year`, `title`, `legend`)
* `[naws_records]` – Fifteen records from the daily summary with their dates, as tiles or a table (`year`, `records`, `layout`, `title`)
* `[naws_on_this_day]` – This calendar day in every earlier year, with the day's records marked (`date`, `title`)
* `[naws_sunpath]` – The sun on its arc over the station, with sunrise, solar noon, sunset and the day length (`title`)
* `[naws_windrose]` – Where the wind comes from and how hard, as a rose of 16 or 8 directions stacked by Beaufort class, with a period switcher (`period`, `from`, `to`, `measure`, `sectors`, `show`, `switcher`, `size`, `title`)
* `[naws_sparkline]` – A curve the size of a word: the raw readings of the last hours or a column of the daily summary, rain as bars, the band between daily low and high, time and value on hover; as a card or a month block too (`param`, `hours`, `days`, `module`, `width`, `height`, `show`, `type`, `band`, `layout`, `title`)
* `[naws_sparkline_tiles]` – The sparkline cards side by side in a grid that wraps by itself: temperature, humidity, pressure, wind, rain and CO2 by default (`params`, `hours`)
* `[naws_forecast]` – Multi-day weather forecast
* `[naws_radar]` – Animated rain radar around the station: Deutscher Wetterdienst in Germany (last hour plus two-hour nowcast), RainViewer elsewhere, on a grey OpenStreetMap map. Images are fetched by your server and served from your site (`width`, `height`, `km`, `source`). Must be switched on under Settings → Rain radar.
* `[naws_table]` – Readings as a table over a period, grouped by hour, day, week, month or year (`module_id`, `parameters`, `period`, `limit`, `group_by`, `title`)
* `[naws_weather_widget]` – Compact forecast widget for a sidebar (`days` 3 or 5, `width` 250–500, `scheme` light, dark or transparent, `sparklines` 0 or 1)
* `[naws_weather_icon]` – Just the animated icon for the current weather state (`size`); renders nothing when the state is unknown

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

Yes. Every endpoint is read-only, rate-limited, and requires an API key generated in the admin panel. The key is accepted in the X-NAWS-Key header only — never as a query parameter, so it cannot end up in access logs, the Referer header, browser history or a proxy along the way.

= Are my Netatmo credentials safe? =

All sensitive data (OAuth tokens, client ID, client secret, API keys) is encrypted with AES-256-GCM before being stored in the database.

= Can I customize the appearance? =

Yes. The Appearance page offers 130+ configurable colors with live preview, 4 icon sets, per-sensor colors, chart theming and year comparison palettes.

= Can I back up my weather data? =

Yes. The Export/Import feature lets you download weather data, module configs and all settings as JSON. Ideal for migrating to a new WordPress installation.

= Which forecast providers are supported? =

Open-Meteo (global, default) and Yr.no / MET Norway (optimized for Northern Europe). Both are free and require no API key.

= I don't receive notification e-mails =

Open XTX Netatmo → Notifications and press "Send test mail". If the page reports that the mail could not be sent, your server sends no mail at all: an SMTP plugin or your hosting provider fixes that, not the plugin. If the test mail arrives but no notifications do, check that the master switch and the rule are switched on and look at the log on the same page. Notifications go out only when a state changes; a battery that has been low since before you switched the rule on is reported at the next fetch, not again afterwards.

== Screenshots ==

1. Live dashboard with sensor cards and 24h trend charts
2. Year-over-year comparison charts for temperature and rainfall
3. Admin settings page with Netatmo connection status
4. REST API documentation in the admin panel
5. Weather forecast widget
6. Appearance page with live color preview
7. Export / Import page for backups

== Changelog ==

= 2.1.6 =
* Security: the public chart endpoints took repeated fields — a single visitor request could build hundreds of megabytes and run PHP out of memory, the starting point of CVE-2026-109618/109619 ("PHP2Shell"). Repeats are dropped and unknown fields refused; the daily-data endpoint is for logged-in users only and no longer stores a cache entry for every date pair it is sent. The fix in PHP itself comes from your host: PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or a build with the backport.
* Fix: the hourly rain chart showed rain half an hour late. Each bar is now the sum of the rain gauge's reports in that hour; the bars of a day add up to the 24-hour total.
* Fix: grouped rain in `[naws_table]` and the REST API was a twelfth of what fell — it is a sum now, not an average.
* Fix: the y-axis of the rain chart repeated its labels (0, 0.1, 0.1, 0.2); the steps are at least 0.1 mm now.

= 2.1.5 =
* Added: Cron Log › Error Log — the errors and warnings the plugin has always collected (database, Netatmo connection, weather services), which no page showed; the dashboard only counted them. A button clears the log, and the error count on the dashboard links to it.
* Added: Cron Log › Support Report — plugin, WordPress, PHP and database versions, whether the plugin tables exist, the cron state and the newest log entries, with a "Copy report" button for support requests. It contains no passwords, tokens or API keys.
* Fix: a sync whose data the database refused reported "Sync OK – 0 readings saved". It now fails with the database's own message, in the cron log, on the "Sync now" button and for the e-mail notification on failed syncs. An account without a weather station gets its own message instead of "Sync OK".
* Fix: "Sync now" fetched twice and showed the next run on the second of the last one; the next fetch now follows one interval later, and manual syncs appear in the cron log. An overdue next run reads "overdue by …" instead of "in …".

= 2.1.4 =
* Fix: the PHP example on the REST API page failed in snippet plugins such as Code Snippets or WPCode — it read address and key with `global`, which finds nothing inside the function these plugins run. The tab, now "PHP / Snippet", shows a `[wetter_aktuell]` shortcode with the key in the header, a five-minute cache and a copy button.
* Fix: the REST API page still offered the key as a query parameter, which has been refused since the key moved to the `X-NAWS-Key` header.
* Fix: the JavaScript and Chart.js examples now warn that a key used in the browser is readable in the page source.

= 2.1.3 =
* Added: `[naws_radar]` — an animated rain radar around the station: Deutscher Wetterdienst in Germany (the last hour plus a two-hour nowcast), RainViewer elsewhere, on a grey OpenStreetMap map. Play runs through the images once; + and − zoom in around the station, and the enlarged map can be dragged. Your server fetches and stores the images, so visitors never contact a weather service. Nothing is fetched until "Allow rain radar" is switched on under Settings › Rain radar; colours on a new Appearance tab, Rain radar.
* Added: a new Appearance tab, Records — block background, tile background, value and labels of `[naws_records]` and `[naws_on_this_day]`; an empty field follows the base theme. Under Chart Theming three colours for the expand button of the live and history charts.
* Fix: records, "on this day" and the sun path ignored the base theme; their background stayed white whatever was set.
* Fix: the expand button of the charts could turn red after a click under themes such as Hello Elementor.

= 2.1.2 =
* Fix: the forecast showed "Open-Meteo API HTTP 429". Open-Meteo limits its free API per IP address, and the plugin asked again on every page view after a failure, which kept the address above the limit. A failed request now pauses the provider (after a 429 as long as Open-Meteo asks, otherwise 15 minutes; 5 minutes after other errors), the last good forecast stays on the page for up to 24 hours, and visitors read a plain sentence instead of an HTTP code. Yr.no and the place search of the manual location mode are covered as well.
* Fix: the 29th of February drew two almost flat lines across the daily charts of `[naws_history]` in a leap year. The day was placed after the 31st of December; the axis has 366 days now.

Older versions: the complete changelog since 1.0.0 is kept in [CHANGELOG.md](https://github.com/Xyla1512/Netatmo/blob/main/CHANGELOG.md) on GitHub.

== Upgrade Notice ==

= 2.1.6 =
Security: visitors can no longer make the chart endpoints run PHP out of memory (relevant to CVE-2026-109618/109619 — update PHP as well). Fix: the hourly rain chart and grouped rain show the real sum. Nothing to reconfigure.

= 2.1.5 =
New: the error log and a support report on the Cron Log page. Fix: a sync the database refused no longer reports "Sync OK", and "Sync now" no longer fetches twice. Nothing to reconfigure.

= 2.1.4 =
Fix: the REST API page shows a PHP example that works in snippet plugins, as a [wetter_aktuell] shortcode with a copy button, names the X-NAWS-Key header as the only place for the key, and warns about keys in browser code. Nothing to reconfigure.

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

= Deutscher Wetterdienst (maps.dwd.de) =

* **Purpose:** Radar images (measured and two-hour nowcast) for the rain radar `[naws_radar]` when the station is in Germany
* **Data sent:** The map section around your station (as a bounding box) and the time of each image. No visitor data: your server fetches the images and your site serves them.
* **When:** Only if "Allow rain radar" is on, after a page showed the radar, at most every 15 minutes
* **Terms and privacy:** [https://www.dwd.de/EN/service/copyright/copyright_node.html](https://www.dwd.de/EN/service/copyright/copyright_node.html), [https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html](https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html)

= RainViewer (api.rainviewer.com, tilecache.rainviewer.com) =

* **Purpose:** Radar images for the rain radar `[naws_radar]` outside Germany
* **Data sent:** The tile numbers of the map section around your station and the time of each image. No visitor data: your server fetches the images and your site serves them.
* **When:** Only if "Allow rain radar" is on, after a page showed the radar, at most every 15 minutes
* **Terms and privacy:** [https://www.rainviewer.com/terms.html](https://www.rainviewer.com/terms.html), [https://www.rainviewer.com/privacy.html](https://www.rainviewer.com/privacy.html)

= OpenStreetMap tiles (tile.openstreetmap.org) =

* **Purpose:** The background map of the rain radar `[naws_radar]`
* **Data sent:** The tile numbers of the map section around your station. Fetched once per map section and kept on your server.
* **When:** Only if "Allow rain radar" is on, the first time a page shows a radar with that map section
* **Terms and privacy:** [https://operations.osmfoundation.org/policies/tiles/](https://operations.osmfoundation.org/policies/tiles/), [https://osmfoundation.org/wiki/Privacy_Policy](https://osmfoundation.org/wiki/Privacy_Policy)

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
