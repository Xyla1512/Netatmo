=== XTX Integration for Netatmo ===
Contributors: xylaender
Tags: netatmo, weather, weather station, temperature, chart
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.1.1
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
* **17 Shortcodes** – Dashboard, current readings, infobar, single value, computed value, sparkline, sparkline tiles, history charts, heatmap, records, this day in earlier years, sun path, wind rose, forecast, table, widget, weather icon
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

= 2.1.1 =
* Added: a Signal column on the Modules page — the Wi-Fi of the base station as a fan, the radio link of every other module as four bars, each with a word (Excellent, Good, Fair, Weak) and Netatmo's raw value on hover. A device Netatmo reports as unreachable shows "Not reachable".
* Fix: `[naws_forecast]` printed decimal points on a German page ("17.4 / 14.7 °C"). Temperatures, precipitation, wind and gusts of the forecast use the site's decimal mark now.

= 2.1.0 =
* Added: `[naws_sparkline]` — a curve the size of a word, next to the number in running text: the raw readings of the last hours or a column of the daily summary, rain as bars, the band between daily low and high, a bubble with time and value on hover. `layout="tile"` draws it as a card with value, low and high; `layout="month"` as a block of daily means with the rain per day. Server-side SVG, complete without JavaScript; eight colours on a new Appearance tab, Sparkline.
* Added: `[naws_sparkline_tiles]` — the sparkline cards as a grid that wraps by itself: temperature, humidity, pressure, wind, rain and CO2 by default (`params`, `hours`).
* Added: sparklines in the sidebar widget. A switch under Appearance › Sidebar widget, or `sparklines="1"` on the shortcode, adds a 24-hour curve under the temperature and five tiles for humidity, pressure, wind, rain and CO2. Off by default.
* Changed: the forecast's colours can be set on a new Appearance tab, Forecast; texts, frames and the card background follow the base theme. The Appearance tabs are WordPress tabs now, wrap on a narrow screen and stay open after saving.
* Changed: the REST endpoint `/daily` returns `humidity_avg`, `wind_avg`, `co2_avg` and `noise_avg` when asked for them.
* Fix: numbers follow the language of the page — "23,4 °C" and "1.019,1 mbar" on a German page — in the widget, the tables, single and computed values, the infobar, the heatmap, the e-mails and the axes and tooltips of the charts.
* Fix: the heatmap no longer colours today. The running mean of a day that is not over looked like a finished day; the cell now gets its colour the next day.
* Fix: `[naws_current]` shows its icons again instead of their SVG source as text.
* Fix: "11 Minuten ago" — relative times are translatable now ("vor 11 Minuten").

= 2.0.2 =
* Added: retention of raw readings, switchable. The settings page promised "all data is stored permanently" while the dashboard sidebar showed a "Data Retention: 365" that nothing applied. Retention is a real thing now, in a section of its own on the settings page and off by default: switch it on, give it a number of days (365 by default, never fewer than 30), and once a night, after the daily summary, the plugin deletes raw readings older than that and notes the run on the settings page and in the cron log. Only the raw readings go — the ten-minute values behind the live dashboard, `[naws_table]`, `[naws_chart]`, the wind rose and the REST readings endpoint. The daily table is never touched, so history, heatmap, records and climate indices keep their full range. Switching it on, and the manual purge, ask once more and say what is lost. An update changes nothing: the switch is off until you turn it on.
* Fix: the wind cards of the live dashboard ignored every colour setting. `[naws_live]` drew its compass, the pointer and the half-circle gauge for wind and gusts with literal colours; the "Compass Needle" colour went into the page as a variable nothing read, and for wind and gusts there was no field at all. The two cards now take their colours from a tab of their own, Appearance › Live dashboard: wind — background (compass face and the empty part of the gauge), compass rose, compass pointer (the old "Compass Needle", same key, so a saved colour survives), gauge wind, gauge gusts, gauge peak gust of the day — with a preview built from the real compass and gauge. The defaults are the colours the dashboard has always used.
* Fix: the wind rose said "no wind readings" for a period that had readings — all of them calm. It now says "Calm the whole time: 20 readings, all below 1 km/h."
* Fix: the translators comments never reached the catalogues; the extractor looked for them inside the call's parentheses, where WordPress convention never puts them. The `.pot` and both `.po` files carry them now.

= 2.0.1 =
* Fix: the 24-hour rain in the dashboard's rain card was far too low. The card sums the plugin's own `Rain` readings over the last 24 hours, because Netatmo's `sum_rain_24` resets at midnight and the card promises a rolling day. But the rain gauge reports every five minutes, each report carrying the rain of those five minutes, and getstationsdata shows only the newest report — so a fetch every ten minutes stored one report in two, and the card showed 1.6 mm on a day with 3.9 mm; a fetch every 30 or 60 minutes lost even more. After every fetch the plugin now asks getmeasure for the five-minute reports since the last one it has and stores them under their own timestamps; the first fetch after the update closes the last 24 hours in one call. A dry hour needs no extra call. The daily, monthly and yearly sums were never affected — they come from Netatmo's own daily counter. The rain rule of the e-mail notifications, which uses the same rolling sum, is corrected along with the card.
* Changed: `[naws_live]` no longer carries a forecast strip of its own. The dashboard fetched the forecast itself and rendered a copy of the day cards that `[naws_forecast]` shows, and on a page carrying both shortcodes the forecast appeared twice. Whoever wants the forecast under the dashboard places `[naws_forecast]` below it — same cards, same settings. The "forecast days" setting now describes itself as the default for `[naws_forecast]`.
* Changed: the heatmap builds up when it comes into view, not when the page loads. On a page where `[naws_heatmap]` sits below the fold the wave of tiles was over before anyone scrolled to it. The tiles now stay hidden until the top edge of the map reaches the middle of the screen — or until the page is scrolled to its end, for a map near the bottom of a short page; then the wave runs once. Visitors who ask for reduced motion, and print, get the map at once; without JavaScript nothing is hidden.

= 2.0.0 =
* Added: e-mail notifications. After every fetch the plugin checks up to thirteen rules and mails every change of state — once when it begins and, for every rule but rain, once when it is over; all changes of one fetch go into one mail. Seven rules watch the station: battery of a module below a percentage, weak radio link of a module, poor Wi-Fi of the base station, base station not reporting to Netatmo, module not reporting to the base station, three failed fetches in a row, expired credentials. Six watch the weather and the indoor air: frost, heat, a gust above a threshold, rain of the last 24 hours above a threshold, rain starting, CO₂ of the base station or an indoor module above a threshold. Radio, Wi-Fi and the two silence rules hold for a while before they speak or clear; frost and gust wait an hour below the threshold before the all-clear; rain sends no all-clear. All rules ship switched off, and a master switch pauses every mail without touching the rules. The new page XTX Netatmo → Notifications holds the switch, the recipients, the rules with their thresholds in your units, a test-mail button and the last fifty mails.
* Added: the five status fields Netatmo sends with every module are stored now (schema 1.5): battery percentage, Wi-Fi of the base station, whether a device is reachable, when the base station last reported to Netatmo and when a module last spoke to the base station. The Modules page shows Netatmo's own battery percentage instead of an estimate from the voltage.
* Added: two actions for other code — `naws_sync_failed( $message, $consecutive_errors )` fires wherever a fetch fails; `naws_data_synced( $readings )` existed before and is documented now.
* Fix: the wind rose's period buttons turned red under the mouse on Hello Elementor and took the "primary" accent colour when active. Every rule for them now carries two classes and sets rest, hover, focus and active state itself, and the active button has its own colour — an eighth one on the Wind Rose tab under Appearance.
* Fix: Appearance: saving without the icon set in the input no longer logs "Undefined array key".

Older versions: the complete changelog since 1.0.0 is kept in [CHANGELOG.md](https://github.com/Xyla1512/Netatmo/blob/main/CHANGELOG.md) on GitHub.

== Upgrade Notice ==

= 2.1.1 =
New: a Signal column on the Modules page shows the Wi-Fi of the base station and the radio link of every module. Fix: the forecast uses the decimal comma on German pages. Nothing to reconfigure.

= 2.1.0 =
New: sparklines — [naws_sparkline] inline, as a card or a month block, [naws_sparkline_tiles], and optional curves in the sidebar widget. Forecast colours on their own Appearance tab. Fix: decimal commas on German pages, charts included; the heatmap leaves today empty.

= 2.0.2 =
New: switchable retention of raw readings (off by default; the daily table is never touched). Fix: the wind cards of the live dashboard take their colours from a new Appearance tab — compass, pointer, wind and gust gauge. Nothing to reconfigure.

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
