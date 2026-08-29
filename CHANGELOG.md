# Changelog

All notable changes to the XTX Netatmo plugin are documented here.

## [Unreleased]

Merged and waiting for the release it will ship in. Nothing here is published yet.

### Changed

- **`Plugin URI` points at the plugin's own page now.** It named `www.frank-neumann.de/netatmo-wetter-plugin/`, which is a weather page carrying a short section about the plugin — the readings for Leipzig, a six-day forecast, a button. The plugin's actual home is `netatmo.frank-neumann.de`: what it does, how to install it, the shortcodes, the roadmap and a live dashboard to look at before installing anything. That is what the header is for, and it is what the "Visit plugin site" link in the plugins list opens.

  The directory page is deliberately not what it points to. WordPress.org asks that `Plugin URI` name the plugin's own site rather than its listing, and a header that points back at the directory tells a reader nothing they did not already have.
### Fixed

- **A schema change would never have reached anyone who updates through WordPress.** `NAWS_Database::install()` — which carries `dbDelta()` and the column migrations in `maybe_migrate()` — hung on `register_activation_hook()` alone, and that hook does not fire on an update. WordPress reactivates the plugin silently and says so in as many words in core: *"If a plugin is silently activated (such as during an update), this hook does not fire."*

  Until 1.9.7 that was almost harmless, because the plugin was installed by hand from a downloaded archive and everyone who did that also activated it. From 1.9.7 on it is in the directory, and the update button is the ordinary way to get a new version — which is precisely the path that skipped the migration. The next time `NAWS_DB_VERSION` moves, existing installations would have run new code against an old schema: a query against a column that is not there yet, failing loudly in one place and quietly returning nothing in another.

  `init()` now compares the stored schema version against the constant and runs the install routine when they differ. The comparison costs nothing — `naws_db_version` is an autoloaded option, it is in memory anyway.

  It sits at the top of `init()` because every line below it queries those tables. And it is deliberately **not** inside the `is_admin()` branch that the crypto migration lives in: a site whose admin nobody opens still renders shortcodes, and a shortcode reading a column that does not exist is exactly the failure this prevents. `upgrader_process_complete` would have been the other candidate and covers less — it never fires for WP-CLI or for a file swap on the server.

  Two simultaneous requests can both enter the branch. That is accepted rather than locked: `dbDelta()` and `maybe_migrate()` are repeatable, and a lock that got stuck would block the migration for good, which is the worse failure.

  The cron half of this was never at risk. The watchdog in `init()` already reschedules a missing or stale event on its own, so `NAWS_Cron::schedule()` not running on an update changed nothing.
## [1.9.7] – 2026-08-29

The first release published through the WordPress.org plugin directory. Everything the two `1.9.7-beta` pre-releases carried is in here, plus one permission check written after `beta.2` was cut. Anyone testing a beta is offered this as an ordinary update — `version_compare()` orders `1.9.7-beta.2` below `1.9.7`, so nobody is left stranded on a pre-release.

If you use the REST API, read **Changed** before updating: the key is accepted in the `X-NAWS-Key` header only from here on, and the old query-string form answers 401.
### Added
- **The order of the charts and of the live cards is a setting now.** The yearly comparisons came out in the order the template happened to print them — temperature, pressure, rain, humidity, then whatever indoor modules exist — and there was no way to say that rain matters more here than pressure. Both lists on *Live-Dashboard* are sortable now: pick a row up anywhere and drop it where it belongs, and the front end follows. The two lists sit side by side, because stacked they run to some thirty rows and nobody wants to scroll that far to reach the second one. Without a mouse the six dots take focus and the arrow keys move the row — the same reordering, at no cost in screen space.

  A saved order decides position, never membership. Every read matches it against what actually exists, so an id left over from a renamed module is passed over, and a chart the order has never heard of — a new indoor module, a card an update brings along — takes its place at the end instead of disappearing. That is the failure this had to avoid: an invisible chart looks exactly like a lost one.

  The card list shows what the dashboard actually draws. A card whose reading is switched off has no place to be sorted to, so it is not listed — and switching a whole module off takes all of its cards with it, right there while you click, without reloading the page. The rows stay in the form while hidden, which is what keeps a card's position: switch it back on and it returns where you put it, instead of reappearing at the bottom.

  The daily minimum and maximum are not in the list either, for the same reason: they are sub-rows of the temperature card and move with it. `buildLive()` promotes them to cards of their own only when the temperature card itself is switched off, and that is exactly when they appear in the list — offering two positions for one box is worse than offering none.

  Fixing that turned up an old discrepancy. The module-to-reading map in `templates/live.php` left out `Humidity_indoor`, so switching the base station off hid all of its readings except that one, which stayed on the dashboard with nothing around it. The map now lives in `NAWS_Helpers::module_param_map()`, complete, and both the template and the settings screen read it — a second copy is what let the two drift apart in the first place.

  The live cards are ordered rather than rebuilt. `buildLive()` still prints them in the order it always has and the grid lays them out along the saved list, which leaves the refresh path and the soft update untouched. The card list holds only what is really a box of its own on the page: the absolute pressure stands in for the relative one, the gust lives inside the wind gauge, the rain sums are sub-rows — none of them can be dragged, because none of them has a place to be dragged to. Showing and hiding cards stays where it was, with the module toggles; the sort list shows that state but does not duplicate the switch.

- **Admin stylesheet and script carry their modification time in the URL.** Both hung on `NAWS_VERSION` alone, which only moves at a release. Change a stylesheet between two releases and the URL stays what it was, so the browser serves the copy it already has — the change sits on the server and is nowhere to be seen, which is a fine way to spend an afternoon debugging a layout that was correct all along. `NAWS_Admin::asset_version()` appends `filemtime()`. Every release ships new files with new timestamps, so nothing is cached less aggressively than before.

- **The encryption speaks up when it does not work.** `NAWS_Crypto::encrypt()` returned the plaintext on failure so the credentials would not be lost — the caller could not tell that from the return value and wrote an unencrypted secret into `wp_options`, which only `error_log()` ever heard about. That was precisely the one outcome the class exists to prevent: its key lives in `wp-config.php`, its ciphertext in the database, so a dump on its own is not enough.

  A failed encrypt now writes nothing at all. The value already stored stays as it is, the rest of the save runs through, and a notice says the entry was not accepted. Nothing is blocked — the capability check is a prediction and must not bar anyone for whom it would in truth have worked.

- **The admin shows the state of the encryption**, including when it is fine. A tile of its own next to the polling display, and on the settings page one notice per finding: a missing openssl extension, a missing aes-256-gcm, an `AUTH_KEY` that is still the sample value from `wp-config-sample.php`, and changed WordPress salts.

  The placeholder was the least conspicuous of the four: `defined( 'AUTH_KEY' )` is true for it as well, so it did not take the emergency route but the regular one — and derived the key from a phrase that sits in every WordPress archive. The *translated* phrase from a localized `wp-config-sample.php`, which is long enough to pass for a real key otherwise, is now recognized too.

- **A fingerprint of the key** beside the ciphertext, so a salt change is recognizable as one. When the salts are renewed, every decryption fails, and until now the plugin stopped working without an explanation. The comparison needs no ciphertext at all and therefore sits in the state display rather than in the decryption. It is recorded when a secret is stored, not when one is merely encrypted: saving the credentials would otherwise move the fingerprint along while both tokens still lie under the old key. Whoever updates only after a change has no fingerprint to compare against — then nothing is claimed and nothing is said, and that one constellation stays unexplained. It is the price of a state display that needs no ciphertext.

- **`migrate()` sets the done flag only when it is true.** It used to carry the version number even when not a single field could be encrypted.

- **`[naws_calc]` — one shortcode for every computed value.** Twenty-seven of them, in four kinds, and the kind decides which attributes apply. Fourteen *instant* values read the latest measurement or the location: dew point, apparent temperature, wet-bulb temperature, heat index, thermal sensation, CO₂ rating, wind compass, sunrise, sunset, day length, moon phase and illumination, next supermoon, next lunar eclipse. Seven *day classes* count and streak over the daily table — ice days, frost days, summer days, hot days, tropical nights, heating days, cooling days — with `mode="count|streak|max_streak"`. Five *sums* aggregate it: heating and cooling degree days, growing degree days, the grassland temperature sum and the date the growing season started. One *index*, the SPI.

  The single most important rule in it is that "nobody measured this" and "it happened on zero days" must never look alike. A row in the daily table exists as soon as any column carries a value, so a stretch of days with only a pressure reading would otherwise produce a confident "0 frost days". Every value that reads the daily table declares the columns it actually needs, and a period whose rows never carry them gives the fallback, not a zero.

  `note="1"` appends how much of the period really carries data — `31 (from 230 of 230 days)` — because a frost-day total means something different over a complete year than over a year with a three-week gap in it.

- **The Standardized Precipitation Index**, `[naws_calc value="spi" months="1|3|6|12"]`. It fits a gamma distribution to every window of that length in the record and reports where the newest one falls, as a standard normal deviate. Two departures from the textbook are documented at the function: the windows are pooled across the calendar rather than grouped by end month, because the classic form needs decades before any single month has a sample worth fitting; and it computes from two complete years rather than the customary thirty. The documentation page states the reference length with the installation's own numbers and says plainly that below about thirty years the index is a tendency rather than a measurement.

  Months are only counted when every one of their days carries a rain reading. A month missing three days is not a drier month, it is an unknown one, and feeding it to the fit would turn a gauge outage into a drought.

- **The header bar is a setting now.** The dark bar above the live widget, above both forecast variants and above the history block reached that color by three different routes: one read `--ink2`, which the inline CSS fed from the *dark text* color, and the other two carried `#2d5252` as a literal. So the only way to recolor a header was to change a text color — which repainted text elsewhere and still left two of the four bars standing. All four now read `--naws-header-bg` and `--naws-header-text` from *Appearance → Basis-Theme*, and the muted meta lines derive from the text color instead of being fixed white. Defaults are exactly what those bars painted before; anyone who had bent *dark text* to reach the bar keeps that shade, it is carried over to the new key once.

- **A font setting, listing only fonts the page already serves.** *Appearance → Basis-Theme* offers the families declared in the theme's `theme.json`, those installed through the WordPress font library, those Elementor actually enqueued, and the generic stacks — plus a free-text field for anything else. The plugin still loads no font file of its own: an external font request would be a disclosure matter at WordPress.org, and a family nobody enqueued would simply fall back in the browser and make the setting a lie.

  Elementor deliberately does not go through its own font catalogue. That lists more than 1600 families, nearly all Google Fonts it fetches only where a widget uses one; what it really put on the page is recorded in the active kit's compiled CSS. Uploaded Pro families are read too. Both paths are guarded — no Elementor, no reading.

  A stored slug the list no longer knows falls back to inheritance rather than writing a dangling family into the stylesheet, and the free-text field takes a font family and nothing else: anything carrying a semicolon, brace or parenthesis is discarded whole rather than trimmed into something that still runs.

- **Three settings for the degree-day limits** — heating limit, room temperature, cooling limit — because they depend on the country (Germany 15 °C per VDI 2067, Austria and Switzerland 12 °C) and would otherwise have to be repeated at every shortcode.

### Changed
- **Breaking: the REST API key is accepted in the `X-NAWS-Key` header only.** It used to be read from `?api_key=` as a fallback, and the documentation page advertised that form with a ready-made example URL containing the live key. A secret in a query string is written down by access logs, by the `Referer` header, by browser history and by every proxy and CDN in between; RFC 6750 §5.3 makes the same point about bearer tokens, and every route here is `GET`, so that parameter was always a query string. Calls of the older form now answer `401`. The admin documentation, the FAQ and all three translations were rewritten to match.

- **The cron settings and the cron log now explain what WP-Cron does not do.** WordPress cron runs on page views: without visitors there is no fetch, the night mode setting has nothing to suppress, and the readings show a gap that the daily summary later fills in. Both screens now say so and name the remedy — `DISABLE_WP_CRON` plus a real server cron on `wp-cron.php`.

### Fixed

- **`readme.txt` advertised five shortcodes and documented six; ten are registered.** The feature list said "5 Shortcodes", the shortcode section then listed six, and `add_shortcode()` runs ten times. `[naws_current]`, `[naws_table]`, `[naws_weather_widget]` and `[naws_weather_icon]` had no entry at all — four working features that nobody reading the plugin page would have known existed.

  The count and the list are now both taken from the code. Recount with:

  ```
  grep -rho "add_shortcode( *'[a-z_]*'" includes/ | sort -u | wc -l
  ```

  This lands in the next release. The 1.9.6 package currently under review at WordPress.org still carries the wrong figures.

- **A five-second hiccup at Netatmo cost a whole ten-minute polling cycle.** `getstationsdata` answers `HTTP 503` with error code 27 and a `Retry-After: 5` header when the service is briefly out of breath — caught in the cron context of the reference installation on 2026-08-22, and visible in its log for days before that: bursts of one to three cycles, several times an hour, heaviest overnight. Thirty-five failures in thirty-one hours, and every one of them a red row in the cron log with a data gap behind it.

  The server says exactly when it will be back, and the plugin threw that away. `refresh_access_token()`, against the very same host, has always knocked three times before giving up; the data path knocked once. That asymmetry was the bug — Netatmo's hiccup is not something this plugin can fix, but treating it as a final answer was. A transient answer is now repeated up to three times, waiting as long as `Retry-After` asks. Nothing is invented: a server asking for longer than fifteen seconds is not shortened to fit but left to the next cycle, and an expired token still takes the refresh path rather than eating a retry.

  The decision itself is a pure function, `NAWS_API::retry_delay()` — response state in, wait or give up out, no clock and no options — so the rules about what counts as transient are testable without a network. Only 5xx, a dead connection, and code 27 qualify. A bad token, a rate limit and a malformed request are final, because asking again would only cost time and blur the log.

  Measured on the reference installation the same morning: seventeen missed ten-minute slots between midnight and 08:00, none at all between 09:00 and 12:00 — with one 503 in that window, at 11:00:26, whose cycle finished five seconds later as `Sync OK` instead of a red row.

  `get_measure()` reads through the same decision. That path feeds the daily summary and the historical import, where giving up costs a day of a module rather than a single reading, and where a 503 answering with an HTML page used to surface as `Invalid JSON response`.

- **An error without an error code was handed back as an empty result.** Both API methods asked whether Netatmo had sent an error *code*; a body carrying `error` but no `code` therefore fell through to `return $data['body'] ?? []`, turning a failure into an empty device list or an empty measurement series — which the callers read as "nothing to save". Netatmo always sends the code, so this was never observed, but the check now gates on the error key itself and prints `?` where a code is missing.

- **The cron log could not tell an outage from a bad token.** `get_stations_data()` reported Netatmo's error *message* and discarded the error code and the HTTP status, so `Sync failed: Service temporarily unavailable` was all one had to go on — enough to see that something broke, not enough to say what. The message now carries both: `Service temporarily unavailable (Netatmo code 27, HTTP 503)`.

- **The sidebar widget never named its location.** In automatic mode the name came from the geocoding API, which cannot supply it: that endpoint searches *by name*, and it was being handed coordinates. Measured against the live service, `?name=51.35,12.37` answers HTTP 200 with `{"generationtime_ms":0.18763542}` and no results key at all, while `?name=Leipzig` returns Leipzig — Open-Meteo has no reverse geocoding, so the call spent a five-second timeout to always fall through to the placeholder word `Station Location`, which then travelled into the forecast cache and showed up in the footer as if it were a place.

  The name now comes from Netatmo, which sends the station's city and country with every sync. The plugin was reading that same object already and keeping only the coordinates. No second external service, and nothing new to declare.


- **The time in that footer looked like a clock and stood still.** It was the moment the forecast was last fetched, and the forecast is cached for three hours — so the same `HH:MM` sat next to the location for hours at a time. It now shows the newest measurement from the station, which moves with the ten-minute rhythm the values above it already follow.
- **A server without the openssl extension died on the first read of a token.** `openssl_decrypt()` is an undefined function there, so reading a stored secret ended the request with a fatal error instead of returning one — and since the cron watchdog runs in the frontend as well, that reached visitors, not only the administrator. The function now checks for the extension itself and answers with the same failure value a failed decryption gives, which is what lets the site stay up long enough for the warning in the admin to be read at all.

- **An unauthenticated request could turn a 401 into a fatal 500.** `?api_key[]=x` reached `hash_equals()` as an array — `empty()` waves a non-empty array through — and PHP answered with a `TypeError`. It was the query parameter's doing, not the header's: `WP_REST_Request::get_header()` joins its values and always returns a string or null. Dropping the parameter closes the path, and the comparison now refuses anything that is not a string.

- **The apparent temperature ignored wind chill.** `feels_like()` computed Steadman's formula at every temperature. It now runs three regimes: wind chill below 10 °C with wind above 5 km/h, the heat index from 27 °C with humidity above 40 %, Steadman in between. In cold wind the displayed value changes — sometimes colder, sometimes warmer, since the two formulas cross.

- **The heat index was computed outside its domain.** Rothfusz's regression is a fit for hot, humid conditions and returns nonsense below about 25 °C: at −5 °C and 80 % humidity it produced 103.9 °C. The infobar had always gated it; the new catalogue had not inherited that. It is now gated in one place both callers use.

- **The next supermoon, the next lunar eclipse and the next full moon were dated in German** regardless of the language setting — a fixed `d.m.Y – H:i` plus the word "Uhr", in a plugin that ships a complete Norwegian translation. All three now use the site's own date and time format through `wp_date()`, the same route the start of the growing season already took.

- **The plugin now uses the theme's font throughout, and the charts finally do too.** Most of it always inherited the surrounding font; two places did not. `[naws_current]` carried a hardcoded system stack in the `--naws-font` variable, and — more visibly — every chart asked `document.documentElement` for its font. That is `<html>`, which most themes never style, so the axis labels, tooltips and legends came out in the browser default while the page around them was the theme font. On a Twenty Twenty-Five site with Manrope, all eleven charts on a page rendered in Times New Roman. The `|| 'sans-serif'` fallback never caught it, because the browser default is a perfectly valid value.

  Charts now read the font from the widget's own element, so they match the card they sit in even inside a section with its own typography. The compass letters in the live dashboard lost their hardcoded `sans-serif` for the same reason. `--naws-font` stays a variable, so overriding it is still one line: `.naws-wrap { --naws-font: Georgia, serif; }`.

- **An uppercase MAC address in `[naws_value module="…"]` matched nothing.** The shortcode kept its own copy of the alias table (`outdoor`, `indoor`, `wind`, `rain`) and lower-cased the alias for the lookup while passing a MAC address on with its original case. There is now one table, in `NAWS_Calc`, and both shortcodes resolve modules through it.

- **An emptied degree-day limit field silently stored 0 °C.** `floatval('')` is `0.0`, which the inline clamp then treated as a deliberate setting rather than as "use the default".

### Security

- **The OAuth return route ran without a permission check.** `handle_oauth_callback()` hangs on `admin_init` and takes Netatmo's redirect — `?page=naws-settings&code=…&state=…`. It exchanged the code for an access and a refresh token, wrote both into `wp_options` and started a synchronization, and it did all of that for whoever happened to be logged in.

  The OAuth state was verified, and it does what a state is for: it proves the request belongs to a flow started on this site. It says nothing about *who* is following the redirect, and Netatmo returns the code to one fixed URL. `current_user_can( 'manage_options' )` now stands at the top of the method — before the state is read, not after, so a request without the capability cannot consume the pending authorization and make the administrator's own attempt fail.

  Raised by the WordPress plugin review team.

- **The state check had a second way in that nothing produced.** When the state did not match, the value was also accepted as `wp_verify_nonce( $state, 'naws_oauth' )`. No line in the plugin ever created that nonce — an acceptance path without a producer, left behind as backwards compatibility. Its cost was structural rather than exploitable: it turned one plain condition into a compound one whose failing branch let the request continue instead of ending it. The check is a single conjunction now, and a state that does not match ends the request.

  What replaces it for the scanner is a `phpcs:ignore` on the two `$_GET['code']` reads that names the OAuth state as the origin proof — a nonce cannot ride along on a redirect that a third party sends.

- **The REST API key page handled its form without asking who was sending it.** `admin/views/rest-api-docs.php` creates and revokes API keys and saves the rate limit on POST, and it verified the nonce for that — but a nonce says the form came from this site, not that whoever submitted it may act. Nobody without `manage_options` could reach the page: it is registered through `add_submenu_page()` under that capability, and the view is only ever `include`d from that locked callback. So this closed nothing that stood open. It is the pattern the review team's finding warns about all the same, and the check now stands in the condition ahead of `check_admin_referer()`, where a request without the capability neither acts nor spends the nonce.

- **`tests/test-oauth-callback.php`** covers both: a call without `manage_options` exchanges nothing, syncs nothing and leaves the stored state in place; an expired, a mismatched and a legacy-nonce state are each rejected; and the ordinary flow still connects.
## [1.9.6.2] – 2026-08-23

The upload of 1.9.6.1 was rejected by the automated scan at WordPress.org with a
single error: `Tested up to: 7.0` is behind the current WordPress release, and a
plugin that lags there is excluded from search results. This release is 1.9.6.1
with that one header corrected. No code changed.

### Fixed
- **`Tested up to` now reads 7.1**, in `readme.txt` and in the plugin header. The
  1.9.6 tag this maintenance line was cut from still said 7.0; the value had
  already been raised on `main` after 1.9.6 was submitted, so the correction
  existed but had never reached the package under review.
## [1.9.6.1] – 2026-08-23

A single security fix on top of 1.9.6, cut from the 1.9.6 tag so that the
package under review at WordPress.org changes by as little as possible.
Everything else in progress stays on `main` and ships with 1.9.7.

### Security

- **The OAuth return route ran without a permission check.** `NAWS_Admin::handle_oauth_callback()` hangs on `admin_init` and takes Netatmo's redirect — `?page=naws-settings&code=…&state=…`. It exchanged the code for an access and a refresh token, wrote both into `wp_options` and started a synchronization, and it did all of that for whoever happened to be logged in.

  The OAuth state was verified, and it does what a state is for: it proves the request belongs to a flow started on this site. It says nothing about *who* is following the redirect, and Netatmo returns the code to one fixed URL. `current_user_can( 'manage_options' )` now stands at the top of the method — before the state is read, not after, so a request without the capability cannot consume the pending authorization and make the administrator's own attempt fail.

  Reported by the WordPress plugin review team.

- **The state check had a second way in that nothing produced.** When the state did not match, the value was also accepted as `wp_verify_nonce( $state, 'naws_oauth' )`. No line in the plugin ever created that nonce — an acceptance path without a producer, left behind as backwards compatibility. Its cost was structural rather than exploitable: it turned one plain condition into a compound one whose failing branch let the request continue instead of ending it. The check is a single conjunction now.

  What replaces it for the scanner is a `phpcs:ignore` on the two `$_GET['code']` reads that names the OAuth state as the origin proof — a nonce cannot ride along on a redirect that a third party sends.

- **`tests/test-oauth-callback.php`** covers both: a call without `manage_options` exchanges nothing, syncs nothing and leaves the stored state in place; an expired, a mismatched and a legacy-nonce state are each rejected; and the ordinary flow still connects. Eleven checks, six of which passed before the fix.

## [1.9.6] – 2026-08-17

### Changed
- **The plugin no longer prints any inline `<script>` block.** The boot routines for `[naws_live]` and `[naws_history]` were the last two, and they existed for a reason: 1.9.3 moved them into `wp_footer` output because `wp_add_inline_script()` is silently dropped on some installations, which left every chart blank with no error to go on. The workaround fixed that symptom but reintroduced exactly the pattern the plugin guidelines ask about, and it would have been the first thing a reviewer noticed.

  Both routines now ship as ordinary registered files — `assets/js/live-boot.js` and `assets/js/history-boot.js` — enqueued through `wp_enqueue_script()` with `naws-frontend` as a dependency, so Chart.js is loaded before either runs. This is a better fix than the one it replaces: a registered file cannot be dropped the way an inline fragment can, so the original 1.9.3 bug is addressed at its root rather than routed around.

  The per-widget payload still travels in the non-executable `<script type="application/json">` element beside each widget. Those elements now carry a `data-naws="live"` or `data-naws="history"` attribute, and each boot file collects its own by that attribute and reads the widget id from the payload. That removes the last thing the old inline block was needed for — a PHP value interpolated into JavaScript — and it means any number of shortcodes on one page boot from a single copy of the file, where previously each occurrence printed its own script block.

  The JavaScript itself is byte-for-byte the code that ran before; only the surrounding function wrapper is new.

## [1.9.5] – 2026-08-17

Compliance pass against the eighteen WordPress.org plugin directory guidelines. Nothing here changes what the plugin does; it changes what it discloses, what it identifies itself as, and what it ships alongside the code.

### Fixed
- **The User-Agent sent to MET Norway named a repository that does not exist.** Two of the five outgoing forecast requests advertised `github.com/naws-plugin` — a 404. MET Norway's terms of service require a User-Agent that identifies the client and offers a way to reach whoever runs it, precisely so they can get in touch before restricting a misbehaving one; an address nobody can open satisfies neither half. The remaining three requests named no contact at all, and the geocoding lookup in automatic mode sent no User-Agent whatsoever.

  All five now go through one `NAWS_Forecast::user_agent()` and send `XTX-Integration-for-Netatmo/<version> (+<site address>)`. The site's own address is the useful contact here: to MET Norway every installation is a separate client, and a plugin homepage would not reach any particular one of them.

- **The Netatmo privacy policy link in the readme was dead** (`netatmo.com/en-us/legal/privacy-policy`, 404). It now points at `legals.netatmo.com`, and the entry gained the API terms of service link the other two services already had.

### Changed
- **The bundled JavaScript libraries now declare their source.** `assets/vendor/chart.umd.min.js` (Chart.js 4.5.1) and `assets/vendor/chartjs-adapter-date-fns.bundle.min.js` (3.0.0) ship as minified distribution builds. Guideline 4 requires that minified code be accompanied either by its source or by a readme link to the source and the build tooling, and neither was present. A new `== Third-Party Libraries ==` readme section names both libraries, their versions, their MIT licenses, the exact release tags and the repositories. Linking is the route the guideline explicitly allows, so no unminified megabyte was added to the package.

- **A dangling source map reference was removed** from the end of `chart.umd.min.js`. It pointed at `chart.umd.min.js.map`, which has never been part of the package, so every browser that opened developer tools on a page with a chart requested a file that was not there.

- **Two undisclosed uses of the Open-Meteo geocoding API are now documented.** `geocoding-api.open-meteo.com` is a different host from `api.open-meteo.com` and had no entry of its own. It is contacted in manual location mode with the city or postal code from the settings, and — this was the less obvious one — once in automatic mode with the station's coordinates, to resolve the place name shown above the forecast.

- **The Netatmo entry no longer understates what is sent.** It said "OAuth tokens, station/module IDs". The Client ID and Client Secret of the Netatmo application go to the token endpoint as well, and a historical import is a further occasion on which the service is contacted. Both are now stated.

- **The shipped `LICENSE` file is GPL v2, matching the declared license.** The plugin header, `readme.txt` and `README.md` all state "GPLv2 or later" while the file next to them carried the GPL v3 text. Distribution under v3 is something "or later" permits, so this was never a licensing conflict — but a package whose license file contradicts its own header invites exactly the question nobody wants to answer during review. The declaration is unchanged; only the file now agrees with it.

## [1.9.4] – 2026-08-17

### Changed
- **The v1.4 column migration no longer builds its SQL from variables.** `NAWS_Database::maybe_migrate()` looped over a hardcoded array of column names and definitions and concatenated both into the `ALTER TABLE` statement. The values never came from anywhere but that array, so nothing was ever injectable — but static analysis cannot see that. Plugin Check's `PluginCheck.Security.DirectDB` sniff traces each query parameter back to its assignment, arrives at the `foreach`, and reports `Unescaped parameter $col used in $wpdb->query()`. A comment saying the array is hardcoded convinces a reviewer, not a scanner, and the WordPress.org review queue runs the scanner.

  Each `ALTER TABLE … ADD COLUMN` is now written out in full as a literal. No identifier is interpolated from a variable, so there is no parameter left to trace. Verified against Plugin Check 2.1.0's own PHPCS ruleset: one warning before, none after.

- **The same migration ran nine schema queries where two suffice.** Each of the eight columns was probed with its own `SHOW COLUMNS … LIKE`. The column list is now read once with a single `SHOW COLUMNS` and checked in PHP. This runs on plugin activation and upgrade only, so it was never a performance problem — it is simply eight round trips that had no reason to exist.

## [1.9.3] – 2026-08-16

### Fixed
- **The history charts rendered nothing at all on some installations.** `templates/history.php` handed all three of its payloads — `NAWS_HIST`, `ALL_CHART_DEFS` and the entire chart script — to `wp_add_inline_script( 'naws-frontend', … )`. On setups where that call is silently dropped, none of the fragments reached the page: `naws-frontend-js` was present in the markup, the JSON blobs and the boot code were not, and every canvas stayed blank. There was no error and no warning — the shortcode simply produced an empty widget.

  `templates/live.php` already worked around this in 1.7.0 with a `<script type="application/json">` data element plus a `wp_footer` script block. The history template now uses exactly the same pattern: the chart definitions moved into the JSON container as `DEFS`, and the chart script is emitted on `wp_footer` at priority 20, after `wp_print_footer_scripts()` has printed Chart.js. A `_nawsHistBoot_<widget-id>` guard keeps several `[naws_history]` shortcodes on one page from booting each other twice.

- **Hiding a year from the enlarged chart left the small chart's button looking active.** `toggleYear()` rebuilt the legend it was clicked in and the modal legend — which, for a click inside the modal, meant rebuilding the same container twice and never the one in the card. The chart in the card did drop the year, so the card showed a button marked active for a year that was no longer plotted. Both legends are now rebuilt unconditionally, and the unused `legendId` parameter is gone.

### Changed
- **The year buttons sit below their chart instead of beside the title.** They shared the header row with the chart title and the expand button, so they were confined to whatever horizontal space those two left over — a narrow column in the middle of the card. For a station with a decade or more of records the buttons ran past the right-hand edge instead of using the width available to them.

  `.naws-hc-legend` moved out of `.naws-hc-bar` to below the `<canvas>` in all six chart blocks, and is now a full-width wrapping flex row (`width:100%`, `flex-wrap:wrap`, centred). Measured with 20 years of history at a 744 px viewport, every legend is exactly the card's inner width with no horizontal overflow, wrapping over two rows (four for the rain chart, whose buttons carry the annual total). The enlarged chart view follows the same layout, capped at `max-height:26vh` with its own scroll so a very long history cannot push the chart off screen.

  Both legend rules carry an explicit `box-sizing:border-box`; the plugin stylesheet sets none globally, and without it the modal legend overran its box by its own padding.

- The chart title now shrinks and wraps (`flex:1 1 auto; min-width:0`) rather than forcing the header row wider.

## [1.9.2] – 2026-08-15

### Fixed
- **Errors made the plugin poll the API harder instead of easing off.** After three consecutive failures the fetch interval is meant to double. It was computed as `min(interval × 2, 60 minutes)`, and that cap sits below two of the intervals the settings offer: at 120 minutes the backoff halved the interval, and at 60 minutes it did nothing at all. The cap is now 120 minutes — the longest schedule that exists — and the result can never fall below the configured interval. When there is nothing longer to back off to, that is now stated in the log instead of being silently ignored.

- **The backoff interval could land on a schedule that was never registered.** The doubled value was rounded *down* to a known schedule, so a 20-minute interval backed off to 30 rather than 40. Rounding now goes up, to 60 in that case, and the timestamp and the schedule key are derived from the same number instead of being computed separately.

- **A cron interval that is not on the list stopped polling entirely.** The field accepted any number from 5 to 1440, but only 5, 10, 15, 20, 30, 60 and 120 minutes exist as WP-Cron schedules. Saving 45 handed `wp_schedule_event()` an unknown key, which fails without a word — leaving the site with no fetch cron until the setting was changed again. The field is a dropdown now, and any stored value is snapped to the nearest schedule on the way in.

- **Night mode stopped reducing anything as soon as the API had trouble.** The decision to skip a run was measured from the last *successful* sync. During an outage that timestamp stops moving, so the skip never triggered and the plugin polled at full rate through the night — exactly when easing off matters. It is measured from the last attempt now, successful or not.

- **The dashboard reported a stale sync during normal night operation.** The staleness warning fires after three times the configured interval, but night mode deliberately runs at twice that. A single late cron run — normal for WP-Cron — was enough to raise a warning for a perfectly healthy site. The threshold now scales with night mode.

### Changed
- **Local day boundaries follow the timezone configured in WordPress.** Night mode, the daily summary, the history importer and the manual day fetch all had `Europe/Berlin` compiled in, while the dates they were compared against came from `wp_date()`, which uses the site timezone. On any site not set to Berlin the two disagreed: the night window fell on the wrong hours and daily summaries could be cut at the wrong midnight. All of them now go through one `naws_timezone()` helper.

- The night mode description no longer explains the error backoff. Backoff applies whether night mode is on or off, and describing it on the night mode checkbox suggested that switching the checkbox off would switch the backoff off too. It has its own note under the interval setting.

### Added
- `tests/test-cron-polling.php` — 48 cases covering interval normalisation, the backoff arithmetic and the night-mode skip, including a simulated night with a late-firing cron.

## [1.9.1] – 2026-08-14

### Changed
- **The settings screen has been reorganised.** It used a two-column grid where every row grew as tall as its tallest panel. The connection card is short and the general settings panel is long, so the first row left 561 px of empty space beside the connection card — and the forecast panel, being the third item, started below all of that in the left column with its whole right side empty. Roughly 2180 px of a 3088 px page was blank, and the forecast settings sat near the bottom.

  The connection card now spans the full width, and the settings below are split into five panels assigned by hand to two columns: *Language & station*, *Operation* and *Units* on the left, *Forecast* and *Weather icon* on the right. The forecast panel now begins at 737 px instead of 1389 px, and the page is 2491 px instead of 3088 px.

- **One save button instead of three.** All settings except the API credentials now live in a single form. The credentials keep their own form and their own button, which is also what keeps the client secret out of the rest of the page.

- The *General settings* panel no longer contains a subsection with the same name; the operational settings are grouped under *Operation*. The weather-icon thresholds have their own panel rather than being appended to the forecast settings. Inline styles on this screen moved into stylesheet classes.

### Fixed
- **Every save wrote every setting back, defeating the merge semantics added in 1.7.0.** Each of the three forms carried hidden mirror copies of the fields it did not own — the workaround from before 1.7.0, left in place after the fix that made it unnecessary. Because of it, saving the units also rewrote the forecast settings, the credentials and the icon thresholds from whatever values the page happened to be loaded with. Anyone editing settings in two browser tabs could silently lose the older tab's changes. The mirrors are gone; a save now touches only the fields actually shown in that form.
- **The decrypted Netatmo client secret was rendered into the page three times.** Once in its own password field, which is necessary to edit it, and twice more as hidden mirror fields in the other two forms, which was not. It now appears exactly once, and it is no longer submitted when saving unrelated settings.

## [1.9.0] – 2026-08-14

### Added
- **The sidebar widget has an adjustable width.** It was fixed to the 250 px column it was drawn against; it can now be set anywhere between 250 and 500 px, either once in *Appearance → Sidebar widget* or per placement through the new `width` attribute: `[naws_weather_widget width="400"]`. Values outside the range are pulled to the nearer bound rather than rejected, the same way `days` already pulls 4 to 5.

  The contents scale with it rather than the frame merely stretching: at 500 px the weather icon is 96 px instead of 64, the temperature 44 px instead of 32, and the forecast icons 40 px instead of 28, all continuously in between. The width is applied as a maximum, not a fixed size, so a widget set to 400 px placed in a 280 px column shrinks to fit instead of overflowing it. Browsers without container query support fall back to exactly the 1.8.x layout.

### Fixed
- **The widget's weather icon did not move.** The icon at the head of the sidebar widget is that widget's statement — the animated one, as designed. In the 1.8.0 implementation it was rendered through the same call as the small forecast icons, which are deliberately still, and inherited their frozen state: the sun did not turn, the rain did not fall. It now animates. The forecast icons in the widget, in `[naws_forecast]` and in the dashboard strip stay still, which is intentional — moving pictures beside numbers pull the eye off the numbers.

  The cause was that only two of the three sensible icon variants existed. `NAWS_Weather_Icons` now offers `render_head()` alongside `render()` and `render_inline()`, and all three share one private implementation, so wrapper and animation are separate switches instead of one.

## [1.8.3] – 2026-08-13

### Fixed
- **The Norwegian interface was only half translated.** Norwegian was added in 1.6.0 with 326 keys; the file has since grown to 612, and every string added after that point stayed in English. A Norwegian user saw Bokmål and English side by side — often within a single screen, since the newer areas (forecast, REST API documentation, shortcode reference, live dashboard settings) were entirely untranslated while the surrounding chrome was not. All 272 missing strings are now translated, following the terminology already established in the file: *nedbør*, *lufttrykk*, *luftfuktighet*, *vindkast*, *støynivå*, *diagram*, *24t*.

  36 values remain identical to English on purpose: the product name, API terms (`Client ID`, `Client Secret`), shortcode attribute names that must stay literal (`title`, `refresh`), month abbreviations that are the same in Norwegian, words Norwegian shares with English (*Status*, *Type*, *Live*, *Total*, *Storm*, *Min*, *Alias*), the CO₂ symbol, the icon-set style names that are English in every language, and `time_suffix`, which is empty because Norwegian has no equivalent of the German "Uhr".

  Verified mechanically as well as by eye: all three language files carry the same 612 keys with no duplicates, and every `printf` placeholder, HTML tag, entity and `[naws_*]` shortcode name survives translation intact.

## [1.8.2] – 2026-08-13

### Fixed
- **The shortcode reference in the backend described the wrong shortcodes.** All three language files carried a leftover block of four `sc_*_desc` keys that duplicated entries defined earlier in the same array. Because a later key wins in a PHP array literal, the short, older wording silently overrode the detailed descriptions — and `sc_history_desc` had drifted furthest, describing `[naws_table]` ("historical data in a styled table") on the documentation entry for `[naws_history]`, which is the annual comparison chart. The duplicates are gone and the detailed descriptions, the ones that match the attribute lists printed beside them, are now the ones displayed.

### Security
- `$_POST['naws_appearance']` is sanitized on the superglobal itself rather than through an intermediate variable. The behaviour is unchanged — `map_deep( …, 'sanitize_text_field' )` ran before and runs now — but neither PHP_CodeSniffer nor the plugin review scanner tracks sanitization across an assignment, so the previous form read as unsanitized input to both. This is the same class of finding the review team raised in earlier rounds.
- The `phpcs:ignore` on the `$_FILES` entry now also covers `InputNotSanitized` and states why the array is passed to `wp_handle_upload()` whole.
- A `phpcs:disable` block in `class-naws-ajax.php` re-enabled fewer sniffs than it disabled, leaving `PreparedSQLPlaceholders.ReplacementsWrongNumber` switched off for the remainder of the file. Both lists now match.

### Changed
- The six `urlencode()` calls that build redirect query strings are now `rawurlencode()`, which is the RFC 3986 encoding WordPress recommends.

### Development
- **PHP_CodeSniffer with the WordPress Coding Standards is now set up** (`composer.json`, `.phpcs.xml.dist`). The ruleset is a review gate rather than a style guide: security, database, WordPress API misuse, i18n, prefixing, PHP cross-version compatibility and genuine bug classes such as duplicate array keys. It reports zero findings. The full `WordPress` standard reports roughly 20,700, of which about 19,500 are indentation and array alignment; reformatting for those would touch nearly every line of the plugin without changing what a reviewer sees, so they are deliberately out of the gate and remain available ad hoc.

## [1.8.1] – 2026-08-09

### Fixed
- **Weather icon showed "overcast" under a cloudless sky.** Open-Meteo's `weather_code` collapses all cloud layers into one four-way bucket, so a veil of cirrus counted the same as a low deck — over Leipzig on 9 August the code read 3 ("bedeckt") while low and mid cover were flat 0 and the entire figure was high cloud, which from the ground is a blue sky. The cloudiness ranks now decide on the cover percentage instead of the code, weighting high cloud at 0.4 and taking the thresholds at the octa boundaries 12.5 / 37.5 / 75 %. `cloud_cover` was already being downloaded and discarded; `cloud_cover_low`, `_mid` and `_high` were added to the same request at no cost. Providers that give only a total (Yr.no compact) fall back to it, and without any cover figure the WMO code still decides as before. The layers are stored alongside the last known code so an API outage does not reinstate the old behaviour. Only codes 0–3 are affected; precipitation, fog, thunder and storm keep their existing precedence. `tests/test-weather-state.php` grew from 36 to 53 cases.

## [1.8.0] – 2026-08-08

### Added
- **Sidebar widget.** `[naws_weather_widget days="3|5"]` — weather icon and outdoor temperature, rain and wind below that, then a three- or five-day forecast. Designed against a 250 px column and fills any container. A missing rain or wind module drops its value entirely rather than showing a placeholder.
- **Forecast length setting** with a live preview at true width on the appearance page.

### Changed
- **One weather icon set across the whole plugin.** `[naws_forecast]` and the dashboard forecast strip now use the multi-colour set introduced in 1.7.0 instead of the older flat one. Forecast-day icons are rendered still: a row of animated icons pulls attention away from the numbers beside them.
- The mapping from WMO codes to icon states moved into `NAWS_Weather_State::wmo_to_state()`, shared by all three display places, so they cannot drift apart from each other or from the live icon.
- `NAWS_Forecast::get_weather_svg()` is deprecated. Nothing in the plugin calls it; it is kept because it is public and may sit in a user's theme snippet.

### Fixed
- **Appearance page form redirect reset to Settings.** Several forms on the admin settings screens all post to the same save action handler. Because the handler always redirected to the Settings tab, saving settings on the Appearance page threw the user onto Settings instead of keeping them where they were. The handler now detects which page the form was submitted from and redirects back there, preserving both the "settings saved" notice and the user's workflow.

## [1.7.0] – 2026-08-08

### Added
- **Animated weather icon.** Twelve states — clear day/night, fair, partly cloudy, overcast, fog, rain, heavy rain, snow, sleet/hail, thunderstorm, storm. Available as the shortcode `[naws_weather_icon size="96"]` and, switchable in the backend, above the live dashboard. Multi-colour SVGs drawn for this plugin; all motion is CSS, no JavaScript, and `prefers-reduced-motion` holds them still. No visible caption by design — the state is carried by the `aria-label`.
- **`NAWS_Weather_State`** — the precedence table as a pure `decide()` function plus a WordPress-facing `get_current()`. Station readings win over the forecast; the forecast only fills in where the station is structurally blind (cloud cover, thunder, and precipitation when no rain gauge is fitted).
- **`NAWS_Forecast::get_current_conditions()`** — current conditions on their own 30-minute cache, separate from the 3-hour daily forecast cache. Open-Meteo via `current=weather_code,cloud_cover,is_day,snowfall,precipitation`; Yr.no via the `next_1_hours` block that was previously discarded.
- **`NAWS_Astro::wet_bulb()`** — wet-bulb temperature (Stull approximation), the criterion that decides rain versus snow.
- **Six settings** on the forecast tab: icon above dashboard, heavy-rain rate, snow wet-bulb threshold, fog humidity and dew-point spread, storm wind speed. All clamped to physically sensible bands.
- **`tests/test-weather-state.php`** — 36 cases covering the whole precedence table. Runs without a framework: `php tests/test-weather-state.php`.

### Fixed
- **Saving one settings form reset the others.** The settings screen is split across three forms, each posting only the fields it owns, but `sanitize_settings()` rebuilt the options array from scratch — so saving the credentials form silently reset language, units, cron interval, data retention and every forecast setting to their defaults. The callback now merges over the stored options and only touches keys the submitted form actually carried. Checkboxes are preceded by a hidden input of the same name with value `0`, so an unchecked box can still be distinguished from a field the form does not manage. Covered by `tests/test-settings-merge.php`.
- **REST API authentication was not wired up.** All five routes carried `'permission_callback' => '__return_true'` while the implemented `authenticate()` (API key plus rate limit) was never invoked. With the REST API enabled, `/naws/v1/station` — which returns the station latitude and longitude — was readable without a key. Every route is key-protected again.

### Notes
- Snow is the one state split across two sources, and deliberately so: the unheated tipping-bucket gauge cannot register snow at all, so a reading of `0.0 mm` during snowfall is the *absence* of a measurement rather than a measurement of "no precipitation". Occurrence therefore comes from the forecast, while the phase decision stays with the station's own wet-bulb temperature.

## [1.6.5] – 2026-08-07

### Changed
- **Minimum PHP raised to 8.0** — `Requires PHP: 8.0` in plugin header and `readme.txt`. WordPress core blocks activation/update on older PHP, so no additional runtime guard is needed.
- **Tested up to WordPress 7.0** — header and `readme.txt` updated; `README.md` headers re-synchronised (they were still on 1.5.7 / WP 5.8).

### Fixed
- **PHP 8.1 deprecation in the history chart** (`templates/history.php`): `get_daily_data_range()` runs `SELECT MIN(day_date), MAX(day_date)` without `GROUP BY`, which always returns one row — with `NULL` columns when the table is empty. The old check `$range ? …` was therefore truthy and passed `null` into `substr()`. Now the *values* are checked (as everywhere else in the codebase), falling back to the current year. This also fixes a latent bug where a fresh install rendered a year range starting at `0`.

## [1.6.4] – 2026-04-20

### Fixed
- **WordPress.org compliance**: `$file['size']` in import handler wrapped in `intval()` for explicit sanitization (`class-naws-admin.php`)
- **WordPress.org compliance**: `SHOW COLUMNS` query in `class-naws-astro.php` converted to `$wpdb->prepare()` — consistent with the rest of the codebase
- **Fatal error on admin dashboard**: `naws_svg_kses_args()` is defined in `class-naws-helpers.php` which is loaded at plugin boot; resolves `Call to undefined function` error when server had a stale copy of the helper file

## [1.6.3] – 2026-04-14

### Fixed
- **WordPress.org compliance**: all file-scope `ob_start()` / `ob_get_clean()` patterns removed from admin views and frontend templates
- **WordPress.org compliance**: PHP values injected into inline scripts via `wp_add_inline_script()` with `wp_json_encode()` instead of echoing PHP inside JS blocks
- **WordPress.org compliance**: JS strings previously echoed from PHP now served via `nawsAdmin.strings` (localized with `wp_localize_script`)
- **WordPress.org compliance**: icon SVGs sanitized with `wp_kses()` before JSON-encoding; `NAWS_Icons::get_js_object()` replaced by `wp_json_encode( NAWS_Icons::get_set() )`
- **WordPress.org compliance**: `naws_kses_svg()` / `naws_svg_kses_args()` wrapper replaced with direct `wp_kses( $svg, naws_svg_kses_args() )` calls in all view and template files

## [1.6.2] – 2026-04-10

### Fixed
- **WordPress.org compliance**: file upload sanitized with `sanitize_file_name()` and `wp_handle_upload()`
- **WordPress.org compliance**: all remaining inline `<script>`/`<style>` blocks converted to `wp_add_inline_script()` / `wp_add_inline_style()`
- **WordPress.org compliance**: dynamic SQL column names validated against explicit whitelist before query execution
- **WordPress.org compliance**: `naws_appearance` input sanitization made explicit in code (not just phpcs comment)

## [1.6.0] – 2026-04-07

### Added
- **`naws_svg_kses_args()` helper**: Central SVG allowlist for `wp_kses()` — eliminates custom wrapper pattern flagged by WP review team

### Changed
- **Plugin renamed** to "XTX Integration for Netatmo" (trademark compliance)
- **Chart.js** updated from 4.4.0 to 4.5.1

### Fixed
- **WordPress.org compliance**: all inline `<script>` blocks converted to `wp_add_inline_script()`
- **WordPress.org compliance**: all inline `<style>` blocks moved to enqueued stylesheet
- **WordPress.org compliance**: removed direct `<script src>` for Chart.js in frontend templates
- **AJAX 403**: capability-check failures now return proper JSON 403 instead of plain `wp_die()`

## [1.5.7] – 2026-03-19

### Changed
- **GitHub Auto-Updater entfernt**: `NAWS_Updater` Klasse komplett entfernt — bei WordPress.org gehostete Plugins dürfen keinen eigenen Updater verwenden. Updates laufen künftig über WordPress.org.

### Fixed
- **PCP Error: `move_uploaded_file()` verboten**: Import-Upload nutzt jetzt `copy()` statt `move_uploaded_file()` (WordPress Coding Standards Compliance).
- **PCP Error: `rand()` verboten**: `rand()` in `appearance.php` durch `wp_rand()` ersetzt.
- **PCP Error: SVG Output Escaping**: `NAWS_Icons::get_js_object()` Ausgabe in `live.php` mit `phpcs:ignore` dokumentiert (vertrauenswürdige interne SVG-Icons).
- **PCP Warnings: 361× NonPrefixedVariableFound**: `phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` zu allen Template- und Admin-View-Dateien hinzugefügt (Variablen laufen im Scope der `include`-Funktion, nicht global).

## [1.5.6] – 2026-03-19

### Security
- **Client ID & Client Secret now AES-256-GCM encrypted at rest**: Credentials are encrypted on save (`sanitize_settings()`) and transparently decrypted on read. The original v1.0.1 bug (encrypted value leaking into OAuth URL) is resolved by decrypting before use in API class and Cron. All 5 secrets (access token, refresh token, client ID, client secret, REST API key) are now fully encrypted.

### Changed
- **Migration updated**: `NAWS_Crypto::migrate()` now encrypts plaintext client credentials instead of forcing them to plaintext.
- **Removed legacy plaintext-enforcement** from `netatmo-weather-station.php` init (no longer needed).

## [1.5.5] – 2026-03-19

### Fixed
- **WordPress.org Compliance**: Escaping-Fixes für Admin-Views (`esc_attr()` in `modules.php` Zeile 51/78, `cron-log.php` Zeile 29)
- **readme.txt Stable tag**: Version synchronisiert mit Plugin-Header (war `1.5.0`, jetzt korrekt)
- **Option-Name Inkonsistenz**: `naws_token_expires` → `naws_token_expiry` in `class-naws-ajax.php` (einheitlich mit allen anderen Dateien)

## [1.5.4] – 2026-03-19

### Fixed
- **Historische Charts – Jahres-Buttons auf Mobile**: Buttons umbrechen jetzt mehrzeilig statt aus dem sichtbaren Bereich zu scrollen. Größe, Padding und Dot-Durchmesser reduziert für kompakte Darstellung auf kleinen Bildschirmen.
- **24h-Charts öffnen sich nicht im Popup**: Das Modal-Overlay war außerhalb des `.naws-wx`-Wrappers platziert, wodurch die CSS-Regeln (`position:fixed`) nicht griffen. Modal ist jetzt korrekt innerhalb des Wrappers.

## [1.5.3] – 2026-03-18

### Fixed
- **Auto-update toggle missing**: WordPress showed no "Automatische Aktualisierung aktivieren" link for the plugin. The updater now registers the plugin in `$transient->no_update` when the installed version is current, which enables the WordPress auto-update toggle.

## [1.5.2] – 2026-03-18

### Fixed
- **Plugin URI corrected**: "Plugin Website aufrufen" link now points to `https://www.frank-neumann.de/netatmo-wetter-plugin/`.

## [1.5.1] – 2026-03-18

### Fixed
- **Dashboard SVG icons rendered as source code**: The readings table in the admin dashboard escaped SVG icons with `esc_html()`, causing raw SVG markup to display instead of rendered icons. Icon output is now separated from the escaped label text.

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
