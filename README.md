# XTX Integration for Netatmo

[![Latest release](https://img.shields.io/github/v/release/Xyla1512/Netatmo?label=release)](https://github.com/Xyla1512/Netatmo/releases/latest)
[![License](https://img.shields.io/badge/license-GPLv2%2B-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![WordPress](https://img.shields.io/badge/WordPress-6.2%E2%80%937.0-21759b)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)

A WordPress plugin that connects your Netatmo weather station to your site. It reads every sensor through the official Netatmo API, stores the readings in your own database, and displays them as live dashboards, animated charts, year-over-year history and forecasts.

**Live demo:** https://www.frank-neumann.de/netatmo-wetter-plugin/

Your data stays yours: readings are written to your WordPress database, not to a third-party service. No names, e-mail addresses or IP addresses are collected or transmitted.

---

## Installation

Download **`xtx-integration-for-netatmo.<version>.zip`** from the [latest release](https://github.com/Xyla1512/Netatmo/releases/latest) and install it under **Plugins → Add New → Upload Plugin**.

> ⚠️ Use the attached ZIP, not GitHub's automatically generated "Source code" archives. Those unpack into a folder named `Netatmo-<tag>`, so WordPress installs a *second* copy of the plugin beside your existing one instead of updating it.

### Connecting to Netatmo

1. Activate the plugin, then open **XTX Netatmo → Settings**.
2. Create an application at [dev.netatmo.com](https://dev.netatmo.com).
3. Set its Redirect URI to `https://yoursite.com/wp-admin/admin.php?page=naws-settings`.
4. Paste the Client ID and Client Secret into the settings page and click **Connect to Netatmo**.

Data then syncs on its own. Drop a shortcode on any page to display it.

---

## Shortcodes

| Shortcode | What it renders |
|---|---|
| `[naws_live]` | Live sensor tiles with 24-hour trend charts and forecast |
| `[naws_weather_widget days="3\|5"]` | Compact sidebar widget: weather icon, outdoor temperature, rain and wind, plus a three- or five-day forecast |
| `[naws_weather_icon size="96"]` | The animated current-weather icon on its own |
| `[naws_forecast]` | Multi-day weather forecast |
| `[naws_history]` | Year-over-year comparison charts |
| `[naws_current]` | Current readings |
| `[naws_table]` | Readings as a table |
| `[naws_infobar]` | Astronomy bar: sunrise, moon phase, felt temperature |
| `[naws_value]` | A single sensor value, inline |
| `[naws_calc]` | A single computed value (dew point, felt temperature, sunrise, moon phase, …), for running text or a table; full list on the Shortcodes page in the backend |

The backend has a **Shortcodes** page listing every attribute with a live preview.

---

## The weather icon

Twelve states — clear (day and night), fair, partly cloudy, overcast, fog, rain, heavy rain, snow, sleet/hail, thunderstorm and storm. Multi-colour SVGs drawn for this plugin, animated in pure CSS with no JavaScript, held still under `prefers-reduced-motion`. There is no visible caption in the standalone icon by design; the state is carried by the `aria-label`.

What makes it worth a section: **your own measurements outrank the forecast.** The forecast only fills in what the station is structurally unable to see.

- **Rain versus snow** is decided by *wet-bulb* temperature, not air temperature, so snow is still recognised at 3–4 °C in dry air.
- **Snow occurrence** comes from the API even when a rain gauge is fitted — an unheated tipping bucket measures snow wrongly, and that is a known limitation of the hardware, not a guess.
- **A rain gauge reading exactly 0.0 mm is not the same as having no gauge.** A measured zero overrules a forecast that claims rain; a missing gauge lets the forecast speak.
- **Cloudiness** is read from the cover percentage per layer, with high cloud weighted down, because a veil of cirrus is a blue sky from the ground even when the provider's summary code calls it overcast.
- **Meltwater is filtered out**: a tip from thawing snow well below freezing is discarded rather than drawn as rain.

Thresholds for heavy rain, the snow wet-bulb point, fog humidity and dew-point spread, and storm wind speed are all configurable and clamped to physically sensible bands.

---

## Supported modules

| Type | Device | Readings |
|---|---|---|
| `NAMain` | Base station | Temperature, humidity, CO₂, noise, pressure |
| `NAModule1` | Outdoor | Temperature, humidity |
| `NAModule2` | Wind gauge | Speed, direction, gusts |
| `NAModule3` | Rain gauge | Hourly, daily, rolling 24 h |
| `NAModule4` | Additional indoor | Temperature, humidity, CO₂ |

Rain and wind gauges are paid add-ons that most stations do not have. Everything degrades cleanly without them: a missing module drops its value rather than showing a placeholder, and the rules that depend on it step aside.

---

## Features

- **Full Netatmo integration** — OAuth2, automatic sync, every module type
- **Live dashboard** — animated sensor cards, 24 h trend charts, pressure tendency, wind compass, CO₂ air-quality bands
- **Historical charts** — year-over-year comparison for temperature, pressure, rainfall and humidity, with an interactive legend
- **Historical importer** — chunk-based backfill from the Netatmo `getmeasure` API that stays inside the rate limits
- **Forecast** — Open-Meteo (global) or Yr.no / MET Norway, from the station's own coordinates or a location you enter
- **Astronomy** — sunrise and sunset, moon phase with illumination, next full moon
- **Derived values** — feels-like temperature, heat index, dew point, wind chill, wet-bulb temperature
- **REST API** — read-only JSON with API-key authentication and rate limiting, for Grafana, Google Charts and the like
- **Encrypted at rest** — OAuth tokens, client secret and API keys are AES-256-GCM encrypted in the database
- **Configurable units** — °C/°F, mm/inch, mbar/inHg/mmHg, km/h / m/s / mph / kn
- **Three languages** — German, English and Norwegian (Bokmål). One file per language in `languages/`; adding a language means adding one file.
- **Self-healing cron** — a watchdog restarts stuck sync jobs, and polling backs off automatically after repeated failures

---

## Requirements

- WordPress 6.2 or newer (tested up to 7.0)
- PHP 8.0 or newer
- A Netatmo weather station and a free developer application at [dev.netatmo.com](https://dev.netatmo.com)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full history, and the [releases page](https://github.com/Xyla1512/Netatmo/releases) for installable packages.

## License

GPL v2 or later — see [LICENSE](LICENSE).

Weather icons and all other assets were drawn for this plugin and carry the same licence.
