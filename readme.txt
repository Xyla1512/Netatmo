=== XTX Integration for Netatmo ===
Contributors: xylaender
Tags: netatmo, weather, weather station, temperature, chart
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.9.13
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
* **15 Shortcodes** – Dashboard, current readings, infobar, single value, computed value, history charts, heatmap, records, this day in earlier years, sun path, wind rose, forecast, table, widget, weather icon
* **Export / Import** – Full backup and restore of weather data, modules and settings
* **E-Mail Notifications** – battery, radio and Wi-Fi, station offline, failed syncs, frost, gusts, rain; per-rule thresholds, one mail per state change and an all-clear when the state ends
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
* `[naws_forecast]` – Multi-day weather forecast
* `[naws_table]` – Readings as a table over a period, grouped by hour, day, week, month or year (`module_id`, `parameters`, `period`, `limit`, `group_by`, `title`)
* `[naws_weather_widget]` – Compact forecast widget for a sidebar (`days` 3 or 5, `width` 250–500, `scheme` light, dark or transparent)
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

Open XTX Netatmo → Notifications and press "Send test mail". If the page reports that the mail could not be sent, your server sends no mail at all: an SMTP plugin or your hosting provider fixes that, not the plugin. If the test mail arrives but no notifications do, check that the rule is switched on and look at the "Current state" table on the same page — it says what every rule sees right now and why one is suspended. Notifications go out only when a state changes; a battery that has been low since before you switched the rule on is reported at the next fetch, not again afterwards.

== Screenshots ==

1. Live dashboard with sensor cards and 24h trend charts
2. Year-over-year comparison charts for temperature and rainfall
3. Admin settings page with Netatmo connection status
4. REST API documentation in the admin panel
5. Weather forecast widget
6. Appearance page with live color preview
7. Export / Import page for backups

== Changelog ==

= 1.9.13 =
* Fix: `[naws_records]` and `[naws_on_this_day]` stayed empty on an installation whose daily summary table carried a different collation than the modules table. The three big queries compared `module_id` across two tables; MySQL refuses that as soon as the collations differ, the query failed, and the plugin returned nothing without a word. The active modules now go into every query as an id list from PHP, so no comparison crosses a table any more — `[naws_live]`, `[naws_infobar]` and the history are read the same way.
* Fix: when a block has nothing to show, an editor who is logged in reads why in the block — no active base station, no daily rows, a failed query with the database's own message, unknown record names — and the log gets a line. Visitors see what they saw before. An unknown name in `records="…"` is skipped with a note instead of emptying the whole block.
* Fix: switching a module on or off under Netatmo → Modules reaches the front end at once. The module list was cached for an hour, and only the admin read fresh.
* Fix: attributes that a page builder writes with HTML entities (`value=&quot;dewpoint&quot;`) reach the shortcode as intended; they used to arrive as `quotdewpointquot` and fall back to `--`. All fifteen shortcodes decode them first.
* Fix: on Android, Chrome's automatic dark theme inverted the wind rose and the sun's arc into black shapes. The blocks declare `color-scheme: only light` now, which takes exactly them out of that and leaves the page alone.

= 1.9.12 =
* Added: `[naws_windrose]` — where the wind comes from, how often, and how hard: one ray per compass direction (16 or 8), its length the share of readings from there, stacked by Beaufort class from the centre outwards, calm in the hub. Below it the main directions, mean, peak and calm share, a legend, and a table for screen readers. Built from the raw ten-minute readings with one grouped query per period, cached as a transient. `period` (`7d`, `30d`, `90d`, `year`, `all`), `from`/`to` for a fixed range, `measure` (`wind`, `gust`, `both`), `sectors`, `show`, `switcher`, `size`, `title`. Every period of the switcher is rendered on the server; the script only swaps panels and dresses the tooltips. Seven colours on a new Appearance tab.
* Added: the "Wind & Gusts" card of `[naws_live]` shows the day's strongest gust as a third value, in the configured wind unit, and the gauge gets a third, red needle for it.
* Fix: the compass directions are translated. The forecast in `[naws_live]` and `[naws_forecast]` showed a German visitor "ESE" where "OSO" belongs; the sixteen codes go through gettext now, in German and Norwegian.
* Fix: the frontend stylesheet and scripts carry the file's modification time in their version, so a changed file is fetched even when the plugin version stays the same — the admin assets have done this since 1.9.7.
* Changed: this readme carries only the five most recent versions of the changelog; the full history since 1.0.0 lives in CHANGELOG.md on GitHub.

= 1.9.11 =
* Added: `[naws_records]` — fifteen records from the daily summary, each with its date: hottest day, coldest night, warmest night, coldest day, largest daily range, warmest and coldest month, wettest day and month, longest dry and wet spell, strongest gust, longest frost period, longest heat wave and longest run of summer days. As tiles or a table, since the first day with readings or for one year (`year="2025"`), a subset with `records="…"`. A tie goes to the earlier date, a month needs twenty days to compete, and a gap in the data breaks a run rather than bridging it.
* Added: `[naws_on_this_day]` — this calendar day in every earlier year: low, high, mean and rain, newest year first, with the day's record marked in each column. The running year is left out.
* Added: `[naws_sunpath]` — the sun on its arc over the station as an inline SVG: sunrise, solar noon and sunset, the part of the day already travelled, and the sun where it stands; at night below the horizon. Under it the day length, the change since yesterday and the year's longest and shortest day at the station's latitude. No script — a page cache shows the sun where it stood when the cache was filled.
* Added: a colour scheme for the sidebar widget. `[naws_weather_widget]` was a white card whatever the sidebar looked like. It now has `light`, `dark` and `transparent` — the last draws no card at all and takes the sidebar's own colours. Chosen under Appearance → Sidebar widget, where the preview shows it on a dark ground, or per placement with `scheme="dark"`.
* Fix: the purge button under Settings → Manual Data Purge did nothing, and every admin page of the plugin threw "$ is not a function". Since 1.6.4 two handlers in `admin.js` stood behind the line that closes the jQuery block. The purge button works again, and its messages are translated instead of German literals in the script.
* Fix: the bundled catalogue builder now writes plural forms, so "1 day / 2 days" reads right in German and Norwegian. Both bundled catalogues are complete at 735 strings.
= 1.9.10 =
* Added: `[naws_heatmap]` — a year of outdoor daily mean temperatures as a calendar grid: twelve rows of months, thirty-one columns of days, one coloured tile per day, and a row of buttons to page through the years. What a curve makes you read, a grid lets you see: the cold fortnight in February and the hot week in July are shapes on the page rather than wiggles on a line. It reads the daily averages the annual chart already uses, so there is nothing to import. The ten colour stops are settings under Appearance → Heatmap Scale, values between two stops are interpolated, and they are anchored in Celsius even when the display is set to Fahrenheit, because the colour comes from the stored value and the tooltip from the unit you chose. Where a day has no stored average but does have a minimum and a maximum, the tile shows the mean of the two and its tooltip says so. The grid is a table rather than a drawing: the month column stays put while the days scroll sideways on a phone, colours are rendered on the server so the map is complete without JavaScript, and a screen reader reads "March, 14th, 8.2 °C" instead of "image". Attributes: `year`, `title`, `legend`.
* Changed: the MAC addresses of your modules are no longer written into public pages. A module id in this plugin is the hardware address of a Netatmo module, and until now it travelled into every page carrying `[naws_live]` or `[naws_history]` — in the data block, on every chart configuration, in the `data-module4`, `data-indoor` and `data-outdoor` attributes — and it came back out with every request the dashboard made, whose reply carried the base station's address on every one of its thirty-odd readings as well. What travels now is a public reference: `outdoor`, `indoor`, `wind`, `rain` and `in-<name>`, resolved back on the server. Pages cached from before the update and the documented `NAWS_Chart` JavaScript interface both keep working.
* Fix: the two language files 1.9.9 shipped were never read. German and Norwegian travelled along as `.mo` files so that an installation with a German interface would not find an English one the day the update arrived — but WordPress refused both, because the address of the hash table in the file header was wrong. A refused catalogue produces no warning, no log line and no visible difference except that everything stays English. Norwegian, which has no language pack yet, therefore read English throughout 1.9.9.
* Fix: fourteen German and thirteen Norwegian strings had lost their translation in 1.9.9. The table columns from 1.9.8 — time, module, parameter, average — and the card-order screen came through the migration empty, so a German reader without a language pack saw English column headings in a German interface. The texts were taken from the 1.9.8 language files rather than written afresh, so nothing changed wording that a reader had already got used to.
* Fix: three sentences in the chart script were German whatever language WordPress was set to — the chart that failed to render, the period with no readings, the request that came back with an error code. They sat in the JavaScript as literals, which the move to gettext in 1.9.9 never reached.
* Fix: `[naws_table]` printed a MAC address in its module column when the reading's module was no longer in the modules table. Readings outlive the module they came from, so this was reachable rather than theoretical. The cell stays empty now, the way the chart legend does.
* Fix: a chart request that left out `group_by` wrote a PHP notice into the log. The plugin's own scripts always send it; anything else calling the endpoint did not have to.

= 1.9.9 =
* Changed: **breaking** — the plugin no longer has its own language setting; the WordPress locale decides. The old setting was a single site-wide value for the front end and the back end at once, and it read the site language rather than your own, so it could never give you a back end in one language and your visitors another. Site Language plus the per-user Language in your profile do exactly that, and for your theme and every other plugin at the same time. If you had the plugin set to a language other than your site's, set the site language instead — or your own user language, if you meant only your own screen.
* Changed: the interface translates through WordPress now instead of through the plugin's own language files. Until now translate.wordpress.org saw six strings of this plugin; it sees all 649. That means anyone can contribute a language without touching the code, and every language gets a proper WordPress language pack.
* Changed: weekday, month and weather-condition names are translatable. They used to be two hardcoded lists, German and English, so a Norwegian reader got English with no way to change it.
* New: German and Norwegian ship with this release as a bridge. Language packs do not exist the moment an update goes out, and an installation that had a German interface yesterday should not find an English one today. A pack always takes precedence once it is built.

Older versions: the complete changelog since 1.0.0 is kept in [CHANGELOG.md](https://github.com/Xyla1512/Netatmo/blob/main/CHANGELOG.md) on GitHub.

== Upgrade Notice ==

= 1.9.13 =
Fix: [naws_records] and [naws_on_this_day] stayed empty where the daily summary table had a different collation. Editors now read why a block is empty. Module switches show at once, entity attributes work, Android Chrome no longer blackens the wind rose. Nothing to reconfigure.

= 1.9.12 =
New: [naws_windrose] shows where the wind comes from, how often and how hard, with a period switcher and seven colours under Appearance. The Wind & Gusts card shows the day's strongest gust. Compass directions are translated. Nothing to reconfigure.

= 1.9.11 =
New: [naws_records] shows fifteen records from your daily summary with their dates, [naws_on_this_day] this day in earlier years, [naws_sunpath] the sun on its arc. The sidebar widget gets a dark and a transparent scheme. Fix: the purge button in the settings works again. Nothing to reconfigure.

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
