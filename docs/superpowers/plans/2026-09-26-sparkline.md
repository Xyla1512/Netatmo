# Sparkline `[naws_sparkline]` — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein neuer Shortcode `[naws_sparkline]`, der den Verlauf einer Messgröße als Kurve in Wortgröße zeigt (Rohwerte der letzten Stunden oder eine Spalte der Tagestabelle, Regen als Balken, Band zwischen Tagestief und -hoch), mit Sprechblase beim Überfahren, Farben in einem eigenen Reiter des Erscheinungsbilds, optionalen Kurven im Seitenleisten-Widget und den Reitern des Erscheinungsbilds als echte WordPress-Reiter, die nach dem Speichern offen bleiben.

**Architecture:** Eine Klasse `NAWS_Sparkline` mit reiner Rechnung (Attribute prüfen, ausdünnen, Regen-Eimer, Pfadgeometrie, Aufbereitung in Anzeige-Einheiten) und einer einzigen DB-Methode `fetch()`, die über die vorhandenen, bereits gecachten `NAWS_Database::get_readings()` bzw. `get_daily_summaries()` liest. `markup()` rendert `templates/sparkline.php` zu einem Inline-SVG; `sparkline-boot.js` legt nur die Sprechblase darüber. Farben kommen als CSS-Variablen direkt an `.naws-sl` aus `NAWS_Colors::sparkline_css()`.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, kein Build-Schritt, keine neue Bibliothek. Tests sind eigenständige PHP-Dateien ohne Runner (`php tests/test-x.php`). PHP 8.4 liegt im WinGet-Pfad (`command -v php` in Git Bash), Node 24 für `node --check`.

**Spec:** `docs/superpowers/specs/2026-09-26-sparkline-design.md` (Stand `9f8c5ce`)

**Branch:** `sparkline` von `main` (`9f8c5ce`); am Ende `git merge --no-ff` nach `main`, wie `windrose`.

## Global Constraints

- Text-Domain überall `xtx-integration-for-netatmo`; jeder sichtbare Text durch `__()`/`_x()`/`_n()`/`esc_html_e()` oder `naws_label()`. Laufzeit-Schlüssel (`sl_*`, `wgt_sparklines_*`) gehören nach `includes/class-naws-labels.php`, sonst findet `makepot.php` sie nicht. Jeder Platzhalter-String trägt einen `/* translators: … */`-Kommentar direkt vor dem Aufruf.
- Jedes `echo` in Templates und Views trägt `esc_html()`/`esc_attr()`/`absint()` sichtbar. Ausnahme ist allein das fertige Sparkline-Markup aus `NAWS_Sparkline::markup()` beim Einbetten ins Widget und in die Vorschau; dort steht `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php`.
- Keine eigene SQL-Abfrage. Gelesen wird nur über `NAWS_Database::get_readings()` und `get_daily_summaries()`.
- Kein AJAX, keine Nonce im Frontend, kein Inline-Skript im Frontend. `ob_start()` nur paarig mit `ob_get_clean()` in derselben Methode, die ein Template einbindet.
- **Kein `color`-Attribut** am Shortcode (Frank, 26.09.). **Keine Sparklines in der Infobar** (Frank, 26.09.).
- Farbvorgaben: `sparkline_line` `#427272`, `sparkline_line_dark` `#7cc7c7`, `sparkline_rain` `#3585b0`, `sparkline_rain_dark` `#78ace8`, `sparkline_band` `#42727229`, `sparkline_dots` `#7aa0a0`, `sparkline_tip_bg` `#2d5252`, `sparkline_tip_text` `#ffffff`.
- Grenzen: `hours` 1–168 (Vorgabe 24), `days` 2–366 (Vorgabe 30 bei Tagesspalten), `width` 20–600, `height` 10–200; ohne `width`/`height` viewBox 80 × 18, dargestellt als 4.6em × 1.05em.
- Widget-Sparklines sind **aus**, bis jemand sie einschaltet (`wgt_sparklines` = 0).
- Version bleibt 2.0.2; der Schnitt auf 2.1.0 ist nicht Teil dieses Plans.
- Neue PHP-Dateien beginnen mit `if ( ! defined( 'ABSPATH' ) )`-Ausstieg; Templates tragen die zwei `phpcs:disable`-Zeilen für Variablennamen.

## Definition of Done — gilt für jeden Task ohne Ausnahme

1. **PHPCS ohne Befund** auf jeder angefassten PHP-Datei: `vendor/bin/phpcs --report=full <dateien>` (Git Bash). `.phpcs.xml.dist` ist das Gate für WordPress.org.
2. **Ein `phpcs:ignore`/`phpcs:disable` wird begründet oder gar nicht gesetzt.**
3. **Die ganze Suite ist grün:** `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done` — erwartet wird keine Ausgabe.
4. **`php -l` auf jeder angefassten PHP-Datei**, `node --check` auf jeder angefassten JS-Datei.

Wer einen der vier Punkte nicht erfüllen kann, meldet das und umgeht ihn nicht.

## Review Focus

1. **Deutsches Gebietsschema auf dem Server** (`LC_NUMERIC=de_DE`): Ein Komma als Dezimaltrenner im Pfad würde jede Kurve zerstören. Erwartung: Pfade enthalten nur Punkte. Test in Task 2 (`setlocale` vor `geometry()`).
2. **Cache-Schlüssel ändert sich bei jedem Aufruf:** `get_readings()` cacht unter dem Hash seiner Argumente; ein sekundengenaues Zeitfenster legte bei jedem Seitenaufruf einen neuen Transient an. Erwartung: zwei Aufrufe im selben 5-Minuten-Schritt fragen mit identischen Argumenten. Test in Task 3.
3. **Station meldet seit Stunden nichts / Regenmesser fehlt:** Erwartung: leere Ausgabe, kein leerer Rahmen, kein PHP-Hinweis. Tests in Task 3 (`prepare()` → `null`) und Task 5 (`render()` → `''`).
4. **Imperiale Einheiten** (°F, inch, inHg, mph): Erwartung: Kurve, Sprechblase, Vorlesetext und `show="value"` zeigen dieselben Zahlen wie der Rest der Seite. Tests in Task 3 (F, inch).
5. **Zwei Sparklines auf derselben Seite** (Fließtext plus Widget): Erwartung: keine doppelten `id`-Attribute, eine Sprechblase für alle. Test in Task 5 (kein `id=` im Markup), Abnahme in Task 12.

## Dateien

| Datei | Verantwortung |
|---|---|
| `includes/class-naws-sparkline.php` (neu) | Attribute, Ausdünnen, Regen-Eimer, Geometrie, `fetch()`, `prepare()`, `markup()`, `render()`, `widget_set()`, `preview_set()`, `sample()` |
| `templates/sparkline.php` (neu) | ein `<span class="naws-sl">` mit Inline-SVG |
| `assets/js/sparkline-boot.js` (neu) | Sprechblase |
| `assets/css/frontend.css` | `.naws-sl*`-Block am Ende, Widget-Regeln |
| `assets/css/admin.css` | Reiter im WordPress-Stil, Vorschau-Flächen |
| `includes/class-naws-shortcodes.php` | `TAGS`, Skript-Registrierung, `sc_sparkline()`, `sc_weather_widget()` |
| `includes/class-naws-colors.php` | acht Schlüssel, `SPARKLINE_KEYS`, `sparkline_css()`, Gruppe, `appearance_tabs()`, `appearance_tab()` |
| `includes/class-naws-database.php` | vier weitere Tagesspalten in `get_daily_summaries()` |
| `includes/class-naws-labels.php` | `sl_*`, `wgt_sparklines_*` |
| `includes/class-naws-widget-data.php` | `sparklines_on()` |
| `includes/class-naws-admin.php` | `wgt_sparklines` sanitieren, Reiter nach dem Speichern, Farbvariablen im Backend |
| `templates/weather-widget.php` | Kurven in Kopf und Kacheln |
| `admin/views/appearance.php` | Reiterleiste, Reiter „Sparkline", Widget-Haken, Vorschau |
| `admin/views/rest-api-docs.php` | Reiter im WordPress-Stil |
| `admin/views/shortcodes.php` | Karte `[naws_sparkline]`, Widget-Attribut |
| `tests/test-sparkline.php`, `tests/test-sparkline-render.php`, `tests/test-widget-sparklines.php` (neu) | Rechnung, Markup, Widget |
| `tests/test-appearance.php`, `tests/test-settings-merge.php`, `tests/test-asset-version.php` | erweitert |
| `xtx-integration-for-netatmo.php`, `readme.txt`, `README.md`, `CHANGELOG.md`, `docs/site/website.{de,en}.json`, `languages/*`, `docs/i18n/catalog/*.po` | Laden, Doku, Kataloge |

---

### Task 0: Zweig anlegen

- [ ] **Step 1:** `cd "C:/Users/xyla1/Documents/GitHub/Netatmo" && git checkout -b sparkline main && git log --oneline -1` — erwartet `9f8c5ce Docs: sparkline spec — daily values come from the station row, …` (oder der Plan-Commit darüber).

---

### Task 1: `NAWS_Sparkline` — Attribute prüfen

**Files:**
- Create: `includes/class-naws-sparkline.php`
- Modify: `xtx-integration-for-netatmo.php:55` (eine `naws_require()`-Zeile nach `class-naws-windrose.php`)
- Test: `tests/test-sparkline.php`

**Interfaces:**
- Produces: `NAWS_Sparkline::RAW` (Rohgröße ⇒ Modul-Alias), `::DAILY` (Tagesspalte ⇒ Rohgröße für Einheit), `::VIEW_W` = 80, `::VIEW_H` = 18, `::MAX_POINTS` = 200, `::RAIN_BARS` = 48, `::SLOT` = 300; `normalise_atts( array $atts ): ?array` mit den Schlüsseln `param` (string), `base` (string, Rohgröße), `source` (`'raw'|'day'`), `hours` (int), `days` (int, 0 bei raw), `module` (string, `''` bei day), `w`, `h` (int), `sized_w`, `sized_h` (bool), `show` (`'none'|'value'|'minmax'`), `type` (`'line'|'bars'`), `band` (bool); `null` bei unbekanntem `param` oder Rohgröße mit `days`.

- [ ] **Step 1: Den Test schreiben** — `tests/test-sparkline.php`:

```php
<?php
/**
 * Tests fuer NAWS_Sparkline: Attribute, Ausduennen, Regen-Eimer,
 * Geometrie, der Datenweg ueber eine gestubbte Datenbank und die
 * Aufbereitung in Anzeige-Einheiten. Das Markup steht in
 * test-sparkline-render.php.
 *
 *   php tests/test-sparkline.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'time_format' => 'H:i', 'date_format' => 'd.m.Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? 0 ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', ',' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : ''; }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-sparkline.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nnormalise_atts()\n" . str_repeat( '-', 74 ) . "\n";

$n = NAWS_Sparkline::normalise_atts( [] );
check( 'Vorgabe: Temperatur',                 $n['param'], 'Temperature' );
check( 'Vorgabe: Rohwerte',                   $n['source'], 'raw' );
check( 'Vorgabe: 24 Stunden',                 $n['hours'], 24 );
check( 'Vorgabe: keine Tage',                 $n['days'], 0 );
check( 'Vorgabe: aussen gemessen',            $n['module'], 'outdoor' );
check( 'Vorgabe: 80 breit',                   $n['w'], 80 );
check( 'Vorgabe: 18 hoch',                    $n['h'], 18 );
check( 'Vorgabe: Breite nicht gesetzt',       $n['sized_w'], false );
check( 'Vorgabe: Hoehe nicht gesetzt',        $n['sized_h'], false );
check( 'Vorgabe: nur die Kurve',              $n['show'], 'none' );
check( 'Vorgabe: Linie',                      $n['type'], 'line' );
check( 'Vorgabe: kein Band',                  $n['band'], false );

check( 'hours 500 wird 168',                  NAWS_Sparkline::normalise_atts( [ 'hours' => '500' ] )['hours'], 168 );
check( 'hours 0 wird 1',                      NAWS_Sparkline::normalise_atts( [ 'hours' => '0' ] )['hours'], 1 );
check( 'hours leer bleibt 24',                NAWS_Sparkline::normalise_atts( [ 'hours' => '' ] )['hours'], 24 );
check( 'hours Unsinn wird 1',                 NAWS_Sparkline::normalise_atts( [ 'hours' => 'abc' ] )['hours'], 1 );

check( 'Druck misst die Basisstation',        NAWS_Sparkline::normalise_atts( [ 'param' => 'Pressure' ] )['module'], 'indoor' );
check( 'CO2 misst die Basisstation',          NAWS_Sparkline::normalise_atts( [ 'param' => 'CO2' ] )['module'], 'indoor' );
check( 'Boeen misst der Windmesser',          NAWS_Sparkline::normalise_atts( [ 'param' => 'GustStrength' ] )['module'], 'wind' );
$r = NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain' ] );
check( 'Regen misst der Regenmesser',         $r['module'], 'rain' );
check( 'Regen wird zu Balken',                $r['type'], 'bars' );
check( 'ausdruecklich gesetztes Modul bleibt', NAWS_Sparkline::normalise_atts( [ 'module' => 'in-keller' ] )['module'], 'in-keller' );
check( 'Modul wird von Tags befreit',         NAWS_Sparkline::normalise_atts( [ 'module' => '<b>in-keller</b>' ] )['module'], 'in-keller' );

check( 'unbekannte Groesse: nichts',          NAWS_Sparkline::normalise_atts( [ 'param' => 'Unsinn' ] ), null );
check( 'Kleinschreibung zaehlt',              NAWS_Sparkline::normalise_atts( [ 'param' => 'temperature' ] ), null );
check( 'Rohgroesse mit days: nichts',         NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'days' => '30' ] ), null );

$d = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg' ] );
check( 'Tagesspalte: Tagestabelle',           $d['source'], 'day' );
check( 'Tagesspalte ohne days: 30',           $d['days'], 30 );
check( 'Tagesspalte leiht die Einheit',       $d['base'], 'Temperature' );
check( 'Tagesspalte: kein Modul',             $d['module'], '' );
check( 'days 1 wird 2',                       NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '1' ] )['days'], 2 );
check( 'days 999 wird 366',                   NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '999' ] )['days'], 366 );
check( 'rain_sum wird zu Balken',             NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum' ] )['type'], 'bars' );

check( 'Band bei temp_avg',                   NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'band' => 'minmax' ] )['band'], true );
check( 'Band nicht bei temp_max',             NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_max', 'band' => 'minmax' ] )['band'], false );
check( 'Band nicht bei Balken',               NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'band' => 'minmax', 'type' => 'bars' ] )['band'], false );
check( 'Band nicht bei Rohwerten',            NAWS_Sparkline::normalise_atts( [ 'band' => 'minmax' ] )['band'], false );

check( 'type bars gilt auch fuer Temperatur', NAWS_Sparkline::normalise_atts( [ 'type' => 'bars' ] )['type'], 'bars' );
check( 'type Unsinn wird automatisch',        NAWS_Sparkline::normalise_atts( [ 'type' => 'pie' ] )['type'], 'line' );

$s = NAWS_Sparkline::normalise_atts( [ 'width' => '5', 'height' => '500' ] );
check( 'width 5 wird 20',                     $s['w'], 20 );
check( 'height 500 wird 200',                 $s['h'], 200 );
check( 'Breite gilt als gesetzt',             $s['sized_w'], true );
check( 'Hoehe gilt als gesetzt',              $s['sized_h'], true );
check( 'width 1000 wird 600',                 NAWS_Sparkline::normalise_atts( [ 'width' => '1000' ] )['w'], 600 );
check( 'height 3 wird 10',                    NAWS_Sparkline::normalise_atts( [ 'height' => '3' ] )['h'], 10 );

check( 'show VALUE wird value',               NAWS_Sparkline::normalise_atts( [ 'show' => 'VALUE' ] )['show'], 'value' );
check( 'show Unsinn wird none',               NAWS_Sparkline::normalise_atts( [ 'show' => 'x' ] )['show'], 'none' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

Alle weiteren Abschnitte dieser Datei (Tasks 2 und 3) kommen **vor** die Zeile `echo "\n" . str_repeat( '-', 74 ) . "\n";` am Ende.

- [ ] **Step 2:** `php tests/test-sparkline.php` → Fehler `Failed opening required '…/includes/class-naws-sparkline.php'`.

- [ ] **Step 3: Die Klasse** — `includes/class-naws-sparkline.php`:

```php
<?php
/**
 * Sparkline: a curve the size of a word.
 *
 * The arithmetic is pure — points in, path strings out — and is tested on
 * hand-built series. fetch() is the only method that touches the database,
 * and render() is the one the shortcode and the widget call.
 *
 * Coordinates live in a viewBox of width × height (80 × 18 unless the
 * shortcode says otherwise), drawn with preserveAspectRatio="none" so the
 * curve stretches to whatever box CSS gives it. Lines keep their weight
 * through vector-effect="non-scaling-stroke"; dots are zero-length strokes
 * with round caps for the same reason, since a <circle> would stretch into
 * an ellipse.
 *
 * @package NAWS
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Sparkline {

    /** Raw parameters as Netatmo names them, and the module alias that measures each. */
    const RAW = [
        'Temperature'  => 'outdoor',
        'Humidity'     => 'outdoor',
        'Pressure'     => 'indoor',
        'CO2'          => 'indoor',
        'Noise'        => 'indoor',
        'WindStrength' => 'wind',
        'GustStrength' => 'wind',
        'Rain'         => 'rain',
    ];

    /** Daily-summary columns, and the raw parameter that lends each its unit. */
    const DAILY = [
        'temp_avg'     => 'Temperature',
        'temp_min'     => 'Temperature',
        'temp_max'     => 'Temperature',
        'humidity_avg' => 'Humidity',
        'pressure_avg' => 'Pressure',
        'rain_sum'     => 'Rain',
        'wind_avg'     => 'WindStrength',
        'gust_max'     => 'GustStrength',
        'co2_avg'      => 'CO2',
        'noise_avg'    => 'Noise',
    ];

    /** The viewBox without width/height: a word in running text. */
    const VIEW_W = 80;
    const VIEW_H = 18;

    /** A line never carries more points than this; see thin(). */
    const MAX_POINTS = 200;

    /** Rain over hours is summed into this many bars; see buckets(). */
    const RAIN_BARS = 48;

    /** The raw-reading window moves in steps of this many seconds; see slot_end(). */
    const SLOT = 300;

    // ── Attributes ──────────────────────────────────────────────────

    /**
     * The shortcode attributes cast, clamped and whitelisted. Null when
     * there is nothing sensible to draw: an unknown parameter, or a raw
     * parameter together with days (the daily table knows rain_sum, not
     * Rain, and a silent reinterpretation would draw something else than
     * was asked for).
     */
    public static function normalise_atts( array $atts ): ?array {
        $param    = trim( (string) ( $atts['param'] ?? 'Temperature' ) );
        $days_raw = trim( (string) ( $atts['days'] ?? '' ) );

        if ( isset( self::RAW[ $param ] ) ) {
            if ( $days_raw !== '' ) {
                return null;
            }
            $source = 'raw';
            $base   = $param;
        } elseif ( isset( self::DAILY[ $param ] ) ) {
            $source = 'day';
            $base   = self::DAILY[ $param ];
        } else {
            return null;
        }

        $hours_raw = trim( (string) ( $atts['hours'] ?? '' ) );
        $w_raw     = trim( (string) ( $atts['width'] ?? '' ) );
        $h_raw     = trim( (string) ( $atts['height'] ?? '' ) );

        $show = strtolower( trim( (string) ( $atts['show'] ?? '' ) ) );
        $show = in_array( $show, [ 'none', 'value', 'minmax' ], true ) ? $show : 'none';

        $type = strtolower( trim( (string) ( $atts['type'] ?? '' ) ) );
        if ( ! in_array( $type, [ 'line', 'bars' ], true ) ) {
            $type = $base === 'Rain' ? 'bars' : 'line';
        }

        $band = $source === 'day' && $param === 'temp_avg' && $type === 'line'
            && strtolower( trim( (string) ( $atts['band'] ?? '' ) ) ) === 'minmax';

        $module = '';
        if ( $source === 'raw' ) {
            $module = sanitize_text_field( (string) ( $atts['module'] ?? '' ) );
            if ( $module === '' ) {
                $module = self::RAW[ $param ];
            }
        }

        return [
            'param'   => $param,
            'base'    => $base,
            'source'  => $source,
            'hours'   => $hours_raw === '' ? 24 : self::clamp( intval( $hours_raw ), 1, 168 ),
            'days'    => $source === 'day' ? ( $days_raw === '' ? 30 : self::clamp( intval( $days_raw ), 2, 366 ) ) : 0,
            'module'  => $module,
            'w'       => $w_raw === '' ? self::VIEW_W : self::clamp( intval( $w_raw ), 20, 600 ),
            'h'       => $h_raw === '' ? self::VIEW_H : self::clamp( intval( $h_raw ), 10, 200 ),
            'sized_w' => $w_raw !== '',
            'sized_h' => $h_raw !== '',
            'show'    => $show,
            'type'    => $type,
            'band'    => $band,
        ];
    }

    private static function clamp( int $v, int $lo, int $hi ): int {
        return max( $lo, min( $hi, $v ) );
    }
}
```

- [ ] **Step 4: Laden** — in `xtx-integration-for-netatmo.php` nach `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-windrose.php' );`:

```php
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-sparkline.php' );
```

- [ ] **Step 5:** `php tests/test-sparkline.php` → alle bestanden; `php tests/test-main-requires.php` → grün; `php -l includes/class-naws-sparkline.php`; PHPCS auf beide PHP-Dateien.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-sparkline.php xtx-integration-for-netatmo.php tests/test-sparkline.php
git commit -m "Sparkline: attributes cast, clamped and whitelisted"
```

---

### Task 2: Reine Rechnung — Zeitfenster, Ausdünnen, Regen-Eimer, Geometrie

**Files:**
- Modify: `includes/class-naws-sparkline.php` (neue Methoden vor `clamp()`)
- Test: `tests/test-sparkline.php`

**Interfaces:**
- Consumes: Task 1.
- Produces:
  - `slot_end( int $time ): int` — Ende des 5-Minuten-Schritts, in dem `$time` liegt.
  - `thin( array $pts, int $max = self::MAX_POINTS ): array` — `$pts` = `[[ts, value], …]` oder `[[ts, value, low, high], …]`, zeitlich sortiert.
  - `buckets( array $pts, int $from, int $to, int $n = self::RAIN_BARS ): array` — `[[start, end, sum], …]`, `start`/`end` int, `sum` float.
  - `geometry( array $pts, int $w, int $h, bool $band = false ): array` mit `line`, `area`, `band` (string), `end`, `lo`, `hi` (je `[string x, string y]`), `xs` (float-Liste, eine Nachkommastelle).
  - `bar_geometry( array $sums, int $w, int $h ): array` mit `rects` (`[[x, y, w, h], …]` als Strings), `base` (string), `xs` (float-Liste).
  - `num( float $v ): string` — `sprintf( '%.2F', $v )`.

- [ ] **Step 1: Tests anhängen** (vor dem Abschluss der Datei):

```php
echo "\nslot_end()\n" . str_repeat( '-', 74 ) . "\n";
check( 'mitten im Schritt: dessen Ende',      NAWS_Sparkline::slot_end( 1000 ), 1200 );
check( 'auf der Grenze: der naechste',        NAWS_Sparkline::slot_end( 1200 ), 1500 );

echo "\nthin()\n" . str_repeat( '-', 74 ) . "\n";
$zehn = [];
for ( $i = 0; $i < 10; $i++ ) { $zehn[] = [ $i, (float) $i ]; }
check( 'unter der Grenze unveraendert',       NAWS_Sparkline::thin( [ [ 0, 1.0 ], [ 5, 2.0 ] ], 5 ), [ [ 0, 1.0 ], [ 5, 2.0 ] ] );
check( 'zehn auf fuenf: Mittel je Fenster',   NAWS_Sparkline::thin( $zehn, 5 ), [ [ 1, 0.5 ], [ 3, 2.5 ], [ 5, 4.5 ], [ 7, 6.5 ], [ 9, 8.5 ] ] );
$band4 = [ [ 0, 10.0, 8.0, 12.0 ], [ 1, 11.0, 5.0, 13.0 ], [ 2, 12.0, 9.0, 20.0 ], [ 3, 13.0, 10.0, 14.0 ] ];
check( 'Band: Tief ist das Minimum, Hoch das Maximum',
    NAWS_Sparkline::thin( $band4, 2 ), [ [ 1, 10.5, 5.0, 13.0 ], [ 3, 12.5, 9.0, 20.0 ] ] );
$tausend = [];
for ( $i = 0; $i < 1000; $i++ ) { $tausend[] = [ $i * 600, (float) ( $i % 7 ) ]; }
check( 'tausend auf hoechstens zweihundert',  count( NAWS_Sparkline::thin( $tausend ) ) <= 200, true );

echo "\nbuckets()\n" . str_repeat( '-', 74 ) . "\n";
$eimer = NAWS_Sparkline::buckets( [ [ 0, 1.0 ], [ 24, 2.0 ], [ 25, 0.5 ], [ 99, 1.0 ], [ 100, 3.0 ], [ -1, 9.0 ], [ 101, 9.0 ] ], 0, 100, 4 );
check( 'vier Fenster mit Summen, Rand $to zaehlt ins letzte, ausserhalb in keins',
    $eimer, [ [ 0, 25, 3.0 ], [ 25, 50, 0.5 ], [ 50, 75, 0.0 ], [ 75, 100, 4.0 ] ] );
check( 'ohne Meldung: lauter Nullen',         NAWS_Sparkline::buckets( [], 0, 100, 2 ), [ [ 0, 50, 0.0 ], [ 50, 100, 0.0 ] ] );
check( 'leeres Fenster: keine Eimer',         NAWS_Sparkline::buckets( [ [ 5, 1.0 ] ], 100, 100, 4 ), [] );

echo "\ngeometry()\n" . str_repeat( '-', 74 ) . "\n";
$g = NAWS_Sparkline::geometry( [ [ 0, 0.0 ], [ 10, 10.0 ] ], 80, 18 );
check( 'Linie von links unten nach rechts oben', $g['line'], 'M2.00 16.00 L78.00 2.00' );
check( 'Flaeche bis zur Grundlinie',          $g['area'], 'M2.00 16.00 L78.00 2.00 L78.00 16.00 L2.00 16.00 Z' );
check( 'kein Band',                           $g['band'], '' );
check( 'Endpunkt ist der letzte',             $g['end'], [ '78.00', '2.00' ] );
check( 'Tief am ersten Punkt',                $g['lo'], [ '2.00', '16.00' ] );
check( 'Hoch am letzten Punkt',               $g['hi'], [ '78.00', '2.00' ] );
check( 'x-Positionen fuer die Sprechblase',   $g['xs'], [ 2.0, 78.0 ] );

check( 'flache Reihe liegt auf halber Hoehe', NAWS_Sparkline::geometry( [ [ 0, 5.0 ], [ 10, 5.0 ] ], 80, 18 )['line'], 'M2.00 9.00 L78.00 9.00' );

$luecke = NAWS_Sparkline::geometry( [ [ 0, 1.0 ], [ 10, 1.0 ], [ 20, 1.0 ], [ 100, 1.0 ], [ 110, 1.0 ] ], 80, 18 );
check( 'eine Luecke bricht die Linie',        substr_count( $luecke['line'], 'M' ), 2 );
check( 'und die Flaeche',                     substr_count( $luecke['area'], 'Z' ), 2 );

$erst = NAWS_Sparkline::geometry( [ [ 0, 3.0 ], [ 1, 1.0 ], [ 2, 1.0 ], [ 3, 5.0 ], [ 4, 5.0 ] ], 80, 18 );
check( 'Tief beim ersten Auftreten',          $erst['lo'][0], '21.00' );
check( 'Hoch beim ersten Auftreten',          $erst['hi'][0], '59.00' );

$bg = NAWS_Sparkline::geometry( [ [ 0, 10.0, 8.0, 12.0 ], [ 10, 11.0, 9.0, 14.0 ] ], 80, 18, true );
check( 'Band: oben die Hochs, zurueck die Tiefs', $bg['band'], 'M2.00 6.67 L78.00 2.00 L78.00 13.67 L2.00 16.00 Z' );
check( 'Band: die Mittellinie im selben Massstab', $bg['line'], 'M2.00 11.33 L78.00 9.00' );

// Review Focus 1: ein deutsches Gebietsschema darf kein Komma in den Pfad schreiben.
$vorher = setlocale( LC_NUMERIC, '0' );
setlocale( LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany', 'deu' );
$de = NAWS_Sparkline::geometry( [ [ 0, 0.5 ], [ 7, 1.25 ], [ 13, 0.75 ] ], 80, 18 );
setlocale( LC_NUMERIC, $vorher );
check( 'kein Komma im Pfad unter de_DE',      str_contains( $de['line'] . $de['area'], ',' ), false );

echo "\nbar_geometry()\n" . str_repeat( '-', 74 ) . "\n";
$b = NAWS_Sparkline::bar_geometry( [ 0.0, 2.0, 1.0, 0.0 ], 80, 18 );
check( 'nur Fenster mit Regen werden Balken', $b['rects'], [ [ '20.50', '1.00', '18.50', '16.00' ], [ '40.00', '9.00', '18.50', '8.00' ] ] );
check( 'Grundlinie',                          $b['base'], '17.50' );
check( 'x-Mitte jedes Fensters',              $b['xs'], [ 10.8, 30.3, 49.8, 69.3 ] );
check( 'kein Regen: keine Balken',            NAWS_Sparkline::bar_geometry( [ 0.0, 0.0 ], 80, 18 )['rects'], [] );
check( 'keine Fenster: nichts',               NAWS_Sparkline::bar_geometry( [], 80, 18 )['rects'], [] );
```

- [ ] **Step 2:** `php tests/test-sparkline.php` → `Call to undefined method NAWS_Sparkline::slot_end()`.

- [ ] **Step 3: Die Methoden** — in `class-naws-sparkline.php` vor `private static function clamp(`:

```php
    // ── Pure arithmetic ─────────────────────────────────────────────

    /**
     * End of the five-minute slot $time falls in. The raw-reading window
     * ends here rather than at the second, because get_readings() caches
     * under a hash of its arguments: a window that moved every second
     * would leave a new transient behind on every page view.
     */
    public static function slot_end( int $time ): int {
        return intdiv( $time, self::SLOT ) * self::SLOT + self::SLOT;
    }

    /**
     * At most $max points. The time axis is cut into $max equal windows
     * and every window that holds points becomes one point, at the mean
     * of their times and values. A band's low (index 2) takes the window's
     * minimum and its high (index 3) the maximum, so thinning never makes
     * the range look narrower than it was.
     */
    public static function thin( array $pts, int $max = self::MAX_POINTS ): array {
        $n = count( $pts );
        if ( $n <= $max || $max < 2 ) {
            return array_values( $pts );
        }
        $t0   = (float) $pts[0][0];
        $span = ( (float) $pts[ $n - 1 ][0] - $t0 ) / $max;
        if ( $span <= 0 ) {
            return array_values( $pts );
        }

        $acc = [];
        foreach ( $pts as $p ) {
            $i = min( $max - 1, (int) floor( ( $p[0] - $t0 ) / $span ) );
            if ( ! isset( $acc[ $i ] ) ) {
                $acc[ $i ] = [ 'n' => 0, 't' => 0.0, 'v' => 0.0, 'lo' => null, 'hi' => null ];
            }
            $acc[ $i ]['n']++;
            $acc[ $i ]['t'] += $p[0];
            $acc[ $i ]['v'] += $p[1];
            if ( isset( $p[2], $p[3] ) ) {
                $acc[ $i ]['lo'] = $acc[ $i ]['lo'] === null ? $p[2] : min( $acc[ $i ]['lo'], $p[2] );
                $acc[ $i ]['hi'] = $acc[ $i ]['hi'] === null ? $p[3] : max( $acc[ $i ]['hi'], $p[3] );
            }
        }
        ksort( $acc );

        $out = [];
        foreach ( $acc as $a ) {
            $row = [ (int) round( $a['t'] / $a['n'] ), $a['v'] / $a['n'] ];
            if ( $a['lo'] !== null ) {
                $row[] = $a['lo'];
                $row[] = $a['hi'];
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Rain reports summed into $n equal windows from $from to $to, each
     * [ start, end, sum ]. A window without a report sums to 0. A report
     * at $to itself counts into the last window; one before $from or after
     * $to counts into none.
     */
    public static function buckets( array $pts, int $from, int $to, int $n = self::RAIN_BARS ): array {
        $width = ( $to - $from ) / $n;
        if ( $width <= 0 ) {
            return [];
        }
        $out = [];
        for ( $i = 0; $i < $n; $i++ ) {
            $out[] = [ (int) round( $from + $i * $width ), (int) round( $from + ( $i + 1 ) * $width ), 0.0 ];
        }
        foreach ( $pts as $p ) {
            if ( $p[0] < $from || $p[0] > $to ) {
                continue;
            }
            $i = min( $n - 1, (int) floor( ( $p[0] - $from ) / $width ) );
            $out[ $i ][2] += (float) $p[1];
        }
        return $out;
    }

    /**
     * A line through $pts in a $w × $h box, with everything the template
     * and the hover script need:
     *   line  – "M… L…", a new M after every gap
     *   area  – the same, run by run, closed down to the baseline
     *   band  – the highs forward and the lows back as one closed shape,
     *           '' without $band
     *   end   – [x, y] of the last point
     *   lo/hi – [x, y] of the first lowest and the first highest value
     *   xs    – x of every point, one decimal, for the hover script
     * A gap is a step longer than three times the median step. A flat
     * series sits at half height instead of dividing by zero.
     *
     * @param array $pts [[ts, value], …] or, with $band, [[ts, value, low, high], …].
     */
    public static function geometry( array $pts, int $w, int $h, bool $band = false ): array {
        $n    = count( $pts );
        $pad  = max( 2.0, $h / 9 );
        $vals = array_column( $pts, 1 );
        $lo   = min( $vals );
        $hi   = max( $vals );
        if ( $band ) {
            $lo = min( $lo, min( array_column( $pts, 2 ) ) );
            $hi = max( $hi, max( array_column( $pts, 3 ) ) );
        }
        if ( $hi - $lo < 1e-9 ) {
            $lo -= 0.5;
            $hi += 0.5;
        }
        $t0    = (float) $pts[0][0];
        $tspan = (float) $pts[ $n - 1 ][0] - $t0;
        $tspan = $tspan > 0 ? $tspan : 1.0;

        $x = static fn( $t ): float => $pad + ( $t - $t0 ) / $tspan * ( $w - 2 * $pad );
        $y = static fn( $v ): float => $pad + ( 1 - ( $v - $lo ) / ( $hi - $lo ) ) * ( $h - 2 * $pad );

        $steps = [];
        for ( $i = 1; $i < $n; $i++ ) {
            $steps[] = $pts[ $i ][0] - $pts[ $i - 1 ][0];
        }
        sort( $steps );
        $median = $steps ? $steps[ intdiv( count( $steps ), 2 ) ] : 0;
        $gap    = $median > 0 ? 3 * $median : INF;

        $runs = [];
        $run  = [];
        foreach ( $pts as $i => $p ) {
            if ( $i > 0 && $p[0] - $pts[ $i - 1 ][0] > $gap ) {
                $runs[] = $run;
                $run    = [];
            }
            $run[] = [ $x( $p[0] ), $y( $p[1] ) ];
        }
        $runs[] = $run;

        $base = self::num( $h - $pad );
        $line = [];
        $area = [];
        foreach ( $runs as $r ) {
            $seg = '';
            foreach ( $r as $k => $xy ) {
                $seg .= ( $k ? ' L' : 'M' ) . self::num( $xy[0] ) . ' ' . self::num( $xy[1] );
            }
            $last   = $r[ count( $r ) - 1 ];
            $line[] = $seg;
            $area[] = $seg . ' L' . self::num( $last[0] ) . ' ' . $base . ' L' . self::num( $r[0][0] ) . ' ' . $base . ' Z';
        }

        $band_d = '';
        if ( $band ) {
            foreach ( $pts as $i => $p ) {
                $band_d .= ( $i ? ' L' : 'M' ) . self::num( $x( $p[0] ) ) . ' ' . self::num( $y( $p[3] ) );
            }
            for ( $i = $n - 1; $i >= 0; $i-- ) {
                $band_d .= ' L' . self::num( $x( $pts[ $i ][0] ) ) . ' ' . self::num( $y( $pts[ $i ][2] ) );
            }
            $band_d .= ' Z';
        }

        $at = static fn( int $i ): array => [ self::num( $x( $pts[ $i ][0] ) ), self::num( $y( $pts[ $i ][1] ) ) ];

        return [
            'line' => implode( ' ', $line ),
            'area' => implode( ' ', $area ),
            'band' => $band_d,
            'end'  => $at( $n - 1 ),
            'lo'   => $at( (int) array_search( min( $vals ), $vals, true ) ),
            'hi'   => $at( (int) array_search( max( $vals ), $vals, true ) ),
            'xs'   => array_map( static fn( $p ): float => round( $x( $p[0] ), 1 ), $pts ),
        ];
    }

    /**
     * Bars for $sums in a $w × $h box: one slot per sum, a bar only where
     * it rained, heights relative to the wettest slot. Without any rain
     * the baseline stands alone.
     */
    public static function bar_geometry( array $sums, int $w, int $h ): array {
        $n = count( $sums );
        if ( $n === 0 ) {
            return [ 'rects' => [], 'base' => self::num( $h - 0.5 ), 'xs' => [] ];
        }
        $max = max( $sums );
        $max = $max > 0 ? $max : 1.0;
        $bw  = ( $w - 2 ) / $n;
        $gap = min( 1.0, $bw * 0.25 );

        $rects = [];
        $xs    = [];
        foreach ( array_values( $sums ) as $i => $s ) {
            $x0   = 1 + $i * $bw;
            $xs[] = round( $x0 + $bw / 2, 1 );
            if ( $s <= 0 ) {
                continue;
            }
            $bh      = $s / $max * ( $h - 2 );
            $rects[] = [ self::num( $x0 ), self::num( $h - 1 - $bh ), self::num( max( 0.6, $bw - $gap ) ), self::num( $bh ) ];
        }
        return [ 'rects' => $rects, 'base' => self::num( $h - 0.5 ), 'xs' => $xs ];
    }

    /** A coordinate with a decimal point whatever the locale says. */
    public static function num( float $v ): string {
        return sprintf( '%.2F', $v );
    }
```

- [ ] **Step 4:** `php tests/test-sparkline.php` → alle bestanden. Schlägt `kein Komma im Pfad unter de_DE` fehl, ist irgendwo `%f` oder `number_format` statt `%.2F` im Spiel. Schlägt nur `setlocale` still fehl (Windows kennt `de_DE` nicht), bleibt der Test grün, prüft aber nichts — dann zusätzlich `setlocale( LC_NUMERIC, 'German' )` in die Liste nehmen.

- [ ] **Step 5:** `php -l`; PHPCS auf `includes/class-naws-sparkline.php`.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-sparkline.php tests/test-sparkline.php
git commit -m "Sparkline: thinning, rain buckets, and path geometry, locale-proof"
```

---

### Task 3: Datenweg — `fetch()`, `prepare()`, Beschriftungen, vier weitere Tagesspalten

**Files:**
- Modify: `includes/class-naws-sparkline.php` (Abschnitt „Data" nach `num()`)
- Modify: `includes/class-naws-database.php:919` (`$allowed_fields`) und nach `$agg_sql = implode(…)` in `get_daily_summaries()`
- Modify: `includes/class-naws-labels.php` (Block vor `// [naws_sunpath] (since 1.9.11).`)
- Test: `tests/test-sparkline.php`

**Interfaces:**
- Consumes: Tasks 1–2; `NAWS_Helpers::resolve_module_ref( string ): ?string`, `NAWS_Helpers::format_value()`, `NAWS_Helpers::get_unit()`, `NAWS_Calc::station_row_id( array ): ?string`, `NAWS_Database::get_readings( array )`, `NAWS_Database::get_daily_summaries( array )`.
- Produces:
  - `fetch( array $a, int $now ): array` — raw: `[ 'from' => int, 'to' => int, 'rows' => [[ts, value], …] ]`; day: `[ 'dates' => ['Y-m-d', …], 'rows' => [ 'Y-m-d' => [value] | [value, low, high] ] ]`. Werte in Netatmo-Einheiten.
  - `prepare( array $a, array $f ): ?array` — Linie: `[ 'kind' => 'line', 'pts' => [[ts, v(, lo, hi)], …], 'band' => bool, 'tips' => string[], 'aria' => string, 'value' => string ]`; Balken: `[ 'kind' => 'bars', 'sums' => float[], 'tips' => string[], 'aria' => string, 'value' => string ]`. Werte in Anzeige-Einheiten. `null`, wenn es nichts zu zeichnen gibt.
  - `number( string $base, float $v ): string` — Anzeige mit passender Stellenzahl.
  - Labels `sl_name_<param in Kleinbuchstaben>` (18), `sl_aria_line`, `sl_aria_bars`, `sl_aria_band`.

- [ ] **Step 1: Stubs** — in `tests/test-sparkline.php` direkt **unter** den `require_once`-Zeilen einfügen:

```php
/** Stand-in for the database: records what was asked, answers from $GLOBALS. */
class NAWS_Database {
    public static array $asked = [];
    public static function get_modules( $active_only = false ) { return $GLOBALS['naws_test_modules']; }
    public static function get_readings( $args = [] ) { self::$asked[] = [ 'readings', $args ]; return $GLOBALS['naws_test_readings']; }
    public static function get_daily_summaries( $args = [] ) { self::$asked[] = [ 'daily', $args ]; return $GLOBALS['naws_test_daily']; }
}
class NAWS_Calc {
    public static function station_row_id( array $atts ): ?string { return $GLOBALS['naws_test_station']; }
}
$GLOBALS['naws_test_modules'] = [
    [ 'module_id' => '70:ee:50:00:00:01', 'module_type' => 'NAMain',    'module_name' => 'Wohnzimmer', 'station_id' => '70:ee:50:00:00:01' ],
    [ 'module_id' => '02:00:00:00:00:02', 'module_type' => 'NAModule1', 'module_name' => 'Aussen',     'station_id' => '70:ee:50:00:00:01' ],
    [ 'module_id' => '05:00:00:00:00:03', 'module_type' => 'NAModule3', 'module_name' => 'Regen',      'station_id' => '70:ee:50:00:00:01' ],
    [ 'module_id' => '03:00:00:00:00:04', 'module_type' => 'NAModule4', 'module_name' => 'Keller',     'station_id' => '70:ee:50:00:00:01' ],
];
$GLOBALS['naws_test_readings'] = [];
$GLOBALS['naws_test_daily']    = [];
$GLOBALS['naws_test_station']  = '70:ee:50:00:00:01';
```

- [ ] **Step 2: Tests anhängen** (vor dem Abschluss):

```php
echo "\nfetch()\n" . str_repeat( '-', 74 ) . "\n";
$now = 1790000123;
$GLOBALS['naws_test_readings'] = [ [ 'module_id' => '02:00:00:00:00:02', 'parameter' => 'Temperature', 'recorded_at' => '1789990000', 'value' => '12.5' ] ];
$f = NAWS_Sparkline::fetch( NAWS_Sparkline::normalise_atts( [] ), $now );
$gefragt = NAWS_Database::$asked[0][1];
check( 'Rohwerte vom Aussenmodul',            $gefragt['module_id'], '02:00:00:00:00:02' );
check( 'nur die eine Groesse',                $gefragt['parameter'], 'Temperature' );
check( 'Fenster endet am Ende des 5-Minuten-Schritts', $gefragt['date_to'], 1790000400 );
check( 'Fenster ist 24 Stunden lang',         $gefragt['date_from'], 1790000400 - 86400 );
check( 'ungebuendelt, ohne Kappung',          [ $gefragt['group_by'], $gefragt['limit'] ], [ 'raw', 0 ] );
check( 'Zeilen als [ts, Wert]',               $f['rows'], [ [ 1789990000, 12.5 ] ] );
// Review Focus 2: im selben 5-Minuten-Schritt dieselben Argumente, also derselbe Cache-Eintrag.
NAWS_Sparkline::fetch( NAWS_Sparkline::normalise_atts( [] ), $now + 100 );
check( 'gleicher Schritt, gleiche Frage',     NAWS_Database::$asked[1][1], $gefragt );

$vorher = count( NAWS_Database::$asked );
$f = NAWS_Sparkline::fetch( NAWS_Sparkline::normalise_atts( [ 'param' => 'WindStrength' ] ), $now );
check( 'kein Windmesser: keine Zeilen',       $f['rows'], [] );
check( '… und keine Abfrage',                 count( NAWS_Database::$asked ), $vorher );
NAWS_Sparkline::fetch( NAWS_Sparkline::normalise_atts( [ 'module' => 'in-keller' ] ), $now );
check( 'in-keller loest zum Innenmodul auf',  end( NAWS_Database::$asked )[1]['module_id'], '03:00:00:00:00:04' );

$GLOBALS['naws_test_daily'] = [
    [ 'day_date' => '2026-09-24', 'temp_avg' => '12.0', 'temp_min' => '8.0', 'temp_max' => '16.0' ],
    [ 'day_date' => '2026-09-25', 'temp_avg' => null,   'temp_min' => null,  'temp_max' => null ],
    [ 'day_date' => '2026-09-26', 'temp_avg' => '14.0', 'temp_min' => '9.0', 'temp_max' => '19.0' ],
];
$tag = gmmktime( 10, 0, 0, 9, 26, 2026 );
$f   = NAWS_Sparkline::fetch( NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax' ] ), $tag );
$gefragt = end( NAWS_Database::$asked )[1];
check( 'Tage: die Stationszeile',             $gefragt['module_id'], '70:ee:50:00:00:01' );
check( 'Tage: drei Kalendertage bis heute',   [ $gefragt['date_from'], $gefragt['date_to'] ], [ '2026-09-24', '2026-09-26' ] );
check( 'Band: Mittel, Tief und Hoch',         $gefragt['fields'], [ 'temp_avg', 'temp_min', 'temp_max' ] );
check( 'die Tage des Fensters',               $f['dates'], [ '2026-09-24', '2026-09-25', '2026-09-26' ] );
check( 'ein Tag ohne Wert faellt weg',        $f['rows'], [ '2026-09-24' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] );
$GLOBALS['naws_test_station'] = null;
check( 'keine Station: keine Zeilen',         NAWS_Sparkline::fetch( NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg' ] ), $tag )['rows'], [] );
$GLOBALS['naws_test_station'] = '70:ee:50:00:00:01';

$db = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'get_daily_summaries() kennt die vier neuen Spalten',
    (bool) preg_match( "/\\\$allowed_fields\s*=\s*\[[^\]]*'humidity_avg'[^\]]*'wind_avg'[^\]]*'co2_avg'[^\]]*'noise_avg'/", $db ), true );

echo "\nprepare()\n" . str_repeat( '-', 74 ) . "\n";
$a = NAWS_Sparkline::normalise_atts( [] );
$p = NAWS_Sparkline::prepare( $a, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 1000, 10.0 ], [ 2000, 12.5 ], [ 3000, 11.0 ] ] ] );
check( 'eine Linie',                          $p['kind'], 'line' );
check( 'drei Punkte',                         count( $p['pts'] ), 3 );
check( 'Vorlesetext',                         $p['aria'], 'Temperature, last 24 hours: from 10.0 °C to 12.5 °C, latest 11.0 °C' );
check( 'show=value: der letzte Wert',         $p['value'], '11.0 °C' );
check( 'Sprechblase: Uhrzeit und Wert',       $p['tips'][0], '01:16 · 10.0 °C' );
check( 'ein Punkt ist keine Linie',           NAWS_Sparkline::prepare( $a, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 1000, 10.0 ] ] ] ), null );
// Review Focus 3: eine stumme Station ergibt nichts.
check( 'keine Zeilen: nichts',                NAWS_Sparkline::prepare( $a, [ 'from' => 0, 'to' => 86400, 'rows' => [] ] ), null );

$lang = NAWS_Sparkline::prepare( NAWS_Sparkline::normalise_atts( [ 'hours' => '48' ] ), [ 'from' => 0, 'to' => 172800, 'rows' => [ [ 1000, 10.0 ], [ 2000, 12.5 ] ] ] );
check( 'ueber 24 Stunden mit Wochentag',      $lang['tips'][0], 'Thu 01:16 · 10.0 °C' );

// Review Focus 4: imperiale Einheiten.
$GLOBALS['naws_test_options']['naws_settings'] = [ 'temperature_unit' => 'F', 'rain_unit' => 'in' ];
$pf = NAWS_Sparkline::prepare( $a, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 1000, 10.0 ], [ 2000, 20.0 ] ] ] );
check( 'Fahrenheit im Vorlesetext',           $pf['aria'], 'Temperature, last 24 hours: from 50.0 °F to 68.0 °F, latest 68.0 °F' );
$pi = NAWS_Sparkline::prepare( NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain' ] ), [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 900, 25.4 ] ] ] );
check( 'Zoll mit zwei Stellen',               $pi['value'], '1.00 in' );
$GLOBALS['naws_test_options']['naws_settings'] = [];

$pw = NAWS_Sparkline::prepare( NAWS_Sparkline::normalise_atts( [ 'param' => 'WindStrength' ] ), [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 1, 12.0 ], [ 2, 13.0 ] ] ] );
check( 'Wind: ganze Zahl ohne Stelle',        $pw['value'], '13 km/h' );

$ar = NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain' ] );
$pr = NAWS_Sparkline::prepare( $ar, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 900, 0.2 ], [ 1200, 0.3 ] ] ] );
check( 'Regen: Balken',                       $pr['kind'], 'bars' );
check( 'Regen: 48 Fenster',                   count( $pr['sums'] ), 48 );
check( 'Regen: erstes Fenster summiert',      $pr['sums'][0], 0.5 );
check( 'Regen: Sprechblase mit Spanne',       $pr['tips'][0], '01:00–01:30 · 0.5 mm' );
check( 'Regen: Vorlesetext mit Summe',        $pr['aria'], 'Rain, last 24 hours: 0.5 mm in total' );
check( 'Regen: show=value ist die Summe',     $pr['value'], '0.5 mm' );
check( 'trocken, aber gemeldet: gueltig',     NAWS_Sparkline::prepare( $ar, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 900, 0.0 ] ] ] )['value'], '0.0 mm' );
check( 'Regenmesser stumm: nichts',           NAWS_Sparkline::prepare( $ar, [ 'from' => 0, 'to' => 86400, 'rows' => [] ] ), null );

$tage = [ '2026-09-24', '2026-09-25', '2026-09-26' ];
$ab   = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax' ] );
$pb   = NAWS_Sparkline::prepare( $ab, [ 'dates' => $tage, 'rows' => [ '2026-09-24' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] ] );
check( 'Band: eine Linie mit Band',           [ $pb['kind'], $pb['band'] ], [ 'line', true ] );
check( 'Band: zwei Tage, vier Werte je Punkt', [ count( $pb['pts'] ), count( $pb['pts'][0] ) ], [ 2, 4 ] );
check( 'Band: Vorlesetext',                   $pb['aria'], 'Daily mean temperature, last 3 days: daily means from 12.0 °C to 14.0 °C, range 8.0 °C to 19.0 °C' );
check( 'Band: Sprechblase mit Spanne',        $pb['tips'][0], '24.09.2026 · 12.0 °C · 8.0–16.0 °C' );

$pd = NAWS_Sparkline::prepare( NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum', 'days' => '3' ] ), [ 'dates' => $tage, 'rows' => [ '2026-09-25' => [ 4.2 ] ] ] );
check( 'Regen je Tag: fehlende Tage sind 0',  $pd['sums'], [ 0.0, 4.2, 0.0 ] );
check( 'Regen je Tag: Vorlesetext',           $pd['aria'], 'Rain per day, last 3 days: 4.2 mm in total' );
check( 'Regen je Tag ohne Zeilen: nichts',    NAWS_Sparkline::prepare( NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum', 'days' => '3' ] ), [ 'dates' => $tage, 'rows' => [] ] ), null );
```

- [ ] **Step 3:** `php tests/test-sparkline.php` → `Call to undefined method NAWS_Sparkline::fetch()`.

- [ ] **Step 4: Tagesspalten** — in `includes/class-naws-database.php`, `get_daily_summaries()`:

```php
        $allowed_fields = [ 'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum', 'gust_max', 'humidity_avg', 'wind_avg', 'co2_avg', 'noise_avg' ];
```

und direkt nach `$agg_sql = implode( ', ', $agg_parts );` (die vier neuen Spalten haben kein Aggregat; nur der Tageszweig liest sie):

```php
        // The aggregated branch knows only the five fields of $agg_map. A
        // request made of nothing else would leave "SELECT …, FROM" behind.
        if ( $agg_sql === '' ) {
            return [];
        }
```

- [ ] **Step 5: Beschriftungen** — in `includes/class-naws-labels.php` vor `        // [naws_sunpath] (since 1.9.11).`:

```php
        // [naws_sparkline] (since 2.1.0): what a curve is called in the text a screen reader hears.
        case 'sl_name_temperature':                return _x( 'Temperature', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_humidity':                   return _x( 'Humidity', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_pressure':                   return _x( 'Pressure', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_co2':                        return _x( 'CO2', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_noise':                      return _x( 'Noise', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_windstrength':               return _x( 'Wind', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_guststrength':               return _x( 'Gusts', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_rain':                       return _x( 'Rain', 'sparkline', 'xtx-integration-for-netatmo' );
        case 'sl_name_temp_avg':                   return __( 'Daily mean temperature', 'xtx-integration-for-netatmo' );
        case 'sl_name_temp_min':                   return __( 'Daily low', 'xtx-integration-for-netatmo' );
        case 'sl_name_temp_max':                   return __( 'Daily high', 'xtx-integration-for-netatmo' );
        case 'sl_name_humidity_avg':               return __( 'Daily mean humidity', 'xtx-integration-for-netatmo' );
        case 'sl_name_pressure_avg':               return __( 'Daily mean pressure', 'xtx-integration-for-netatmo' );
        case 'sl_name_rain_sum':                   return __( 'Rain per day', 'xtx-integration-for-netatmo' );
        case 'sl_name_wind_avg':                   return __( 'Daily mean wind', 'xtx-integration-for-netatmo' );
        case 'sl_name_gust_max':                   return __( 'Strongest gust per day', 'xtx-integration-for-netatmo' );
        case 'sl_name_co2_avg':                    return __( 'Daily mean CO2', 'xtx-integration-for-netatmo' );
        case 'sl_name_noise_avg':                  return __( 'Daily mean noise', 'xtx-integration-for-netatmo' );
        case 'sl_aria_line':                       return /* translators: 1: what is measured, 2: period such as "24 hours", 3: lowest value, 4: highest value, 5: latest value; each value carries its unit. */ __( '%1$s, last %2$s: from %3$s to %4$s, latest %5$s', 'xtx-integration-for-netatmo' );
        case 'sl_aria_bars':                       return /* translators: 1: what is measured, 2: period such as "24 hours", 3: the total with its unit. */ __( '%1$s, last %2$s: %3$s in total', 'xtx-integration-for-netatmo' );
        case 'sl_aria_band':                       return /* translators: 1: what is measured, 2: period such as "30 days", 3 and 4: lowest and highest daily mean, 5 and 6: lowest low and highest high; each value carries its unit. */ __( '%1$s, last %2$s: daily means from %3$s to %4$s, range %5$s to %6$s', 'xtx-integration-for-netatmo' );
        case 'sl_preview_aria':                    return __( 'Sample sparkline in the chosen colours', 'xtx-integration-for-netatmo' );

```

- [ ] **Step 6: Der Datenweg** — in `class-naws-sparkline.php` nach `num()`:

```php
    // ── Data ────────────────────────────────────────────────────────

    /**
     * The rows behind one sparkline, in the units Netatmo stores.
     *
     * Raw: [ 'from', 'to', 'rows' => [ [ts, value], … ] ] for the module
     * the attributes name. Daily: [ 'dates' => [ 'Y-m-d', … ], 'rows' =>
     * [ 'Y-m-d' => [ value ] or [ value, low, high ] ] ] from the station
     * row, because compute_daily_summary() writes outdoor, rain, wind and
     * base-station values into one row per station — the same row
     * [naws_records] reads. A day without a value is left out.
     */
    public static function fetch( array $a, int $now ): array {
        if ( $a['source'] === 'raw' ) {
            $to   = self::slot_end( $now );
            $from = $to - $a['hours'] * 3600;
            $id   = NAWS_Helpers::resolve_module_ref( (string) $a['module'] );
            if ( $id === null ) {
                return [ 'from' => $from, 'to' => $to, 'rows' => [] ];
            }
            $rows = NAWS_Database::get_readings( [
                'module_id' => $id,
                'parameter' => $a['param'],
                'date_from' => $from,
                'date_to'   => $to,
                'group_by'  => 'raw',
                'limit'     => 0,
            ] );
            $out = [];
            foreach ( $rows as $r ) {
                $out[] = [ (int) $r['recorded_at'], (float) $r['value'] ];
            }
            return [ 'from' => $from, 'to' => $to, 'rows' => $out ];
        }

        $today = new DateTimeImmutable( wp_date( 'Y-m-d', $now ), wp_timezone() );
        $dates = [];
        for ( $i = $a['days'] - 1; $i >= 0; $i-- ) {
            $dates[] = $today->modify( '-' . $i . ' days' )->format( 'Y-m-d' );
        }

        $station = NAWS_Calc::station_row_id( [] );
        if ( $station === null ) {
            return [ 'dates' => $dates, 'rows' => [] ];
        }
        $rows = NAWS_Database::get_daily_summaries( [
            'module_id' => $station,
            'date_from' => $dates[0],
            'date_to'   => $dates[ count( $dates ) - 1 ],
            'fields'    => $a['band'] ? [ 'temp_avg', 'temp_min', 'temp_max' ] : [ $a['param'] ],
            'group_by'  => 'day',
        ] );

        $out = [];
        foreach ( $rows as $r ) {
            $v = $r[ $a['param'] ] ?? null;
            if ( $v === null || $v === '' ) {
                continue;
            }
            $row = [ (float) $v ];
            if ( $a['band'] ) {
                if ( ( $r['temp_min'] ?? null ) === null || ( $r['temp_max'] ?? null ) === null ) {
                    continue;
                }
                $row[] = (float) $r['temp_min'];
                $row[] = (float) $r['temp_max'];
            }
            $out[ (string) $r['day_date'] ] = $row;
        }
        return [ 'dates' => $dates, 'rows' => $out ];
    }

    /**
     * What the template draws, in display units: the series (thinned) or
     * the bars (bucketed), a hover text per point, the text a screen
     * reader hears, and the figure show="value" prints. Null when there
     * is nothing to draw: fewer than two points on a line, or not a
     * single rain report in the window. A window in which the gauge
     * reported but no rain fell is a valid result and draws a baseline.
     */
    public static function prepare( array $a, array $f ): ?array {
        $base = $a['base'];
        $unit = NAWS_Helpers::get_unit( $base );
        $name = naws_label( 'sl_name_' . strtolower( $a['param'] ) );
        if ( $a['source'] === 'raw' ) {
            /* translators: %d: number of hours. */
            $period = sprintf( _n( '%d hour', '%d hours', $a['hours'], 'xtx-integration-for-netatmo' ), $a['hours'] );
        } else {
            /* translators: %d: number of days. */
            $period = sprintf( _n( '%d day', '%d days', $a['days'], 'xtx-integration-for-netatmo' ), $a['days'] );
        }
        $with = static fn( float $v ): string => self::number( $base, $v ) . ' ' . $unit;
        $conv = static fn( float $v ): float => (float) NAWS_Helpers::format_value( $base, $v );

        if ( $a['type'] === 'bars' ) {
            if ( ! $f['rows'] ) {
                return null;
            }
            $sums = [];
            $tips = [];
            $raw_total = 0.0;
            if ( $a['source'] === 'raw' ) {
                $weekday = $a['hours'] > 24;
                foreach ( self::buckets( $f['rows'], $f['from'], $f['to'] ) as $w ) {
                    $v         = $conv( $w[2] );
                    $raw_total += $w[2];
                    $sums[]    = $v;
                    $tips[]    = self::stamp( $w[0], $weekday ) . '–' . self::stamp( $w[1], false ) . ' · ' . $with( $v );
                }
            } else {
                foreach ( $f['dates'] as $d ) {
                    $raw        = (float) ( $f['rows'][ $d ][0] ?? 0.0 );
                    $v          = $conv( $raw );
                    $raw_total += $raw;
                    $sums[]     = $v;
                    $tips[]     = self::day( $d ) . ' · ' . $with( $v );
                }
            }
            $total = $with( $conv( $raw_total ) );
            return [
                'kind'  => 'bars',
                'sums'  => $sums,
                'tips'  => $tips,
                'aria'  => sprintf( naws_label( 'sl_aria_bars' ), $name, $period, $total ),
                'value' => $total,
            ];
        }

        $pts = [];
        if ( $a['source'] === 'raw' ) {
            foreach ( $f['rows'] as $r ) {
                $pts[] = [ $r[0], $conv( $r[1] ) ];
            }
        } else {
            foreach ( $f['dates'] as $d ) {
                if ( ! isset( $f['rows'][ $d ] ) ) {
                    continue;
                }
                $row = $f['rows'][ $d ];
                $p   = [ self::noon( $d ), $conv( $row[0] ) ];
                if ( $a['band'] ) {
                    $p[] = $conv( $row[1] );
                    $p[] = $conv( $row[2] );
                }
                $pts[] = $p;
            }
        }
        $pts = self::thin( $pts );
        if ( count( $pts ) < 2 ) {
            return null;
        }

        $tips    = [];
        $weekday = $a['source'] === 'raw' && $a['hours'] > 24;
        foreach ( $pts as $p ) {
            $when   = $a['source'] === 'raw' ? self::stamp( $p[0], $weekday ) : wp_date( (string) get_option( 'date_format', 'Y-m-d' ), $p[0] );
            $tip    = $when . ' · ' . $with( $p[1] );
            $tips[] = $a['band'] ? $tip . ' · ' . self::number( $base, $p[2] ) . '–' . $with( $p[3] ) : $tip;
        }

        $vals = array_column( $pts, 1 );
        if ( $a['band'] ) {
            $aria = sprintf( naws_label( 'sl_aria_band' ), $name, $period, $with( min( $vals ) ), $with( max( $vals ) ), $with( min( array_column( $pts, 2 ) ) ), $with( max( array_column( $pts, 3 ) ) ) );
        } else {
            $aria = sprintf( naws_label( 'sl_aria_line' ), $name, $period, $with( min( $vals ) ), $with( max( $vals ) ), $with( $vals[ count( $vals ) - 1 ] ) );
        }

        return [
            'kind'  => 'line',
            'pts'   => $pts,
            'band'  => (bool) $a['band'],
            'tips'  => $tips,
            'aria'  => $aria,
            'value' => $with( $vals[ count( $vals ) - 1 ] ),
        ];
    }

    /**
     * A value as text with as many decimals as the quantity deserves:
     * none for humidity, CO₂ and noise, none for a whole wind speed, two
     * for inches of rain and inches of mercury, one for everything else.
     */
    public static function number( string $base, float $v ): string {
        $opts = get_option( 'naws_settings', [] );
        switch ( $base ) {
            case 'Humidity':
            case 'CO2':
            case 'Noise':
                $d = 0;
                break;
            case 'Rain':
                $d = ( $opts['rain_unit'] ?? 'mm' ) === 'in' ? 2 : 1;
                break;
            case 'Pressure':
                $d = ( $opts['pressure_unit'] ?? 'mbar' ) === 'inHg' ? 2 : 1;
                break;
            case 'WindStrength':
            case 'GustStrength':
                $d = abs( $v - round( $v ) ) < 0.05 ? 0 : 1;
                break;
            default:
                $d = 1;
        }
        return number_format_i18n( $v, $d );
    }

    /** The clock time of $ts in the site's format, with the weekday in front if asked. */
    private static function stamp( int $ts, bool $weekday ): string {
        $time = wp_date( (string) get_option( 'time_format', 'H:i' ), $ts );
        return $weekday ? wp_date( 'D', $ts ) . ' ' . $time : $time;
    }

    /** A day of the daily table in the site's date format. */
    private static function day( string $ymd ): string {
        return wp_date( (string) get_option( 'date_format', 'Y-m-d' ), self::noon( $ymd ) );
    }

    /** Noon of a day in the site's timezone: evenly spaced, and never across midnight by DST. */
    private static function noon( string $ymd ): int {
        return ( new DateTimeImmutable( $ymd . ' 12:00:00', wp_timezone() ) )->getTimestamp();
    }
```

- [ ] **Step 7:** `php tests/test-sparkline.php` → alle bestanden. `php tests/test-records.php` und `php tests/test-heatmap-year.php` → grün (sie lesen dieselbe Funktion). `php -l` auf die drei PHP-Dateien; PHPCS darauf.

- [ ] **Step 8: Commit**

```bash
git add includes/class-naws-sparkline.php includes/class-naws-database.php includes/class-naws-labels.php tests/test-sparkline.php
git commit -m "Sparkline: rows from the readings or the station's daily row, prepared in display units"
```

---

### Task 4: Farben — acht Schlüssel, `sparkline_css()`, eigene Gruppe

**Files:**
- Modify: `includes/class-naws-colors.php` (`DEFAULTS` nach `'windrose_switch' => '#1c5cab',`; Konstante `SPARKLINE_KEYS` nach `LIVE_WIND_KEYS`; neue Methode `sparkline_css()` direkt nach `get_inline_css()`; eine Zeile in `get_inline_css()`; `get_groups()` nach `'live_wind'`)
- Test: `tests/test-appearance.php`

**Interfaces:**
- Produces: `NAWS_Colors::SPARKLINE_KEYS` (Reihenfolge wie unten); `NAWS_Colors::sparkline_css(): string` — die Regel `.naws-sl, .naws-sl-tip { --naws-sl-line: …; … }`, `sparkline_line_dark` → `--naws-sl-line-dark`, `sparkline_tip_bg` → `--naws-sl-tip-bg` usw.; leere Werte schreiben keine Variable. Gruppe `sparkline` in `get_groups()`.

- [ ] **Step 1: Tests anhängen** — in `tests/test-appearance.php` vor der Zeile `echo str_repeat( '-', 74 ) . "\n";` am Ende:

```php
echo "\nSparkline-Farben\n";

saved( [] );
check( 'acht Schluessel in fester Reihenfolge', NAWS_Colors::SPARKLINE_KEYS, [
    'sparkline_line', 'sparkline_line_dark', 'sparkline_rain', 'sparkline_rain_dark',
    'sparkline_band', 'sparkline_dots', 'sparkline_tip_bg', 'sparkline_tip_text',
] );
check( 'die Vorgaben', array_intersect_key( NAWS_Colors::DEFAULTS, array_flip( NAWS_Colors::SPARKLINE_KEYS ) ), [
    'sparkline_line'      => '#427272',
    'sparkline_line_dark' => '#7cc7c7',
    'sparkline_rain'      => '#3585b0',
    'sparkline_rain_dark' => '#78ace8',
    'sparkline_band'      => '#42727229',
    'sparkline_dots'      => '#7aa0a0',
    'sparkline_tip_bg'    => '#2d5252',
    'sparkline_tip_text'  => '#ffffff',
] );
check( 'eigene Gruppe',                        NAWS_Colors::get_groups()['sparkline']['keys'], NAWS_Colors::SPARKLINE_KEYS );

$sl = NAWS_Colors::sparkline_css();
check( 'eine Regel fuer Kurve und Sprechblase', str_starts_with( $sl, ".naws-sl, .naws-sl-tip {\n" ), true );
check( 'Linie als Variable',                   str_contains( $sl, "  --naws-sl-line: #427272;\n" ), true );
check( 'Linie auf dunklem Grund',              str_contains( $sl, "  --naws-sl-line-dark: #7cc7c7;\n" ), true );
check( 'Band mit Deckung',                     str_contains( $sl, "  --naws-sl-band: #42727229;\n" ), true );
check( 'Sprechblase',                          str_contains( $sl, "  --naws-sl-tip-bg: #2d5252;\n" ), true );
check( 'get_inline_css() haengt die Regel an', str_contains( NAWS_Colors::get_inline_css(), $sl ), true );

saved( [ 'sparkline_line' => '#123456', 'sparkline_rain' => '' ] );
check( 'gespeicherte Farbe',                   str_contains( NAWS_Colors::sparkline_css(), '--naws-sl-line: #123456;' ), true );
check( 'leere Farbe schreibt keine Variable',  str_contains( NAWS_Colors::sparkline_css(), '--naws-sl-rain:' ), false );

$san = NAWS_Colors::sanitize( [ 'sparkline_line' => '#abcdef', 'sparkline_dots' => 'red' ] );
check( 'Hex bleibt',                           $san['sparkline_line'] ?? null, '#abcdef' );
check( 'ein Farbname faellt weg',              array_key_exists( 'sparkline_dots', $san ), false );
saved( [] );
```

- [ ] **Step 2:** `php tests/test-appearance.php` → `Undefined constant NAWS_Colors::SPARKLINE_KEYS`.

- [ ] **Step 3: `DEFAULTS`** — nach `'windrose_switch' => '#1c5cab',` einfügen:

```php

        // [naws_sparkline] and the widget's curves (since 2.1.0): one line
        // colour for every quantity and a second for the widget's dark
        // scheme, the rain bars likewise, the band between daily low and
        // high, the low/high dots, and the hover bubble. The area under a
        // line is the line colour, lightly filled, and has no key.
        'sparkline_line'      => '#427272',
        'sparkline_line_dark' => '#7cc7c7',
        'sparkline_rain'      => '#3585b0',
        'sparkline_rain_dark' => '#78ace8',
        'sparkline_band'      => '#42727229',
        'sparkline_dots'      => '#7aa0a0',
        'sparkline_tip_bg'    => '#2d5252',
        'sparkline_tip_text'  => '#ffffff',
```

- [ ] **Step 4: Konstante** — nach dem schließenden `];` von `LIVE_WIND_KEYS`:

```php

    /** The [naws_sparkline] keys, in the order the Appearance page shows them. */
    const SPARKLINE_KEYS = [
        'sparkline_line', 'sparkline_line_dark', 'sparkline_rain', 'sparkline_rain_dark',
        'sparkline_band', 'sparkline_dots', 'sparkline_tip_bg', 'sparkline_tip_text',
    ];
```

- [ ] **Step 5: `get_inline_css()`** — die letzte Regel endet mit

```php
        $css .= '  --naws-font: ' . NAWS_Fonts::stack( (string) $c['font_family'], (string) $c['font_custom'] ) . ";\n";
        $css .= "}\n";
```

Direkt darunter (vor `return $css;`) einfügen:

```php

        // [naws_sparkline]: its own rule, see sparkline_css().
        $css .= self::sparkline_css();
```

- [ ] **Step 6: `sparkline_css()`** — direkt nach der schließenden Klammer von `get_inline_css()`:

```php

    /**
     * The sparkline colours as variables on .naws-sl and .naws-sl-tip
     * themselves. A sparkline usually sits in theme text outside every
     * plugin wrapper, and the bubble hangs on <body>, so the variables
     * cannot come from .naws-wrap like the others. An empty value writes
     * no variable; the stylesheet's fallback, the default, applies then.
     * The Appearance page adds the same rule to its own stylesheet.
     */
    public static function sparkline_css(): string {
        $c   = self::get_all();
        $css = ".naws-sl, .naws-sl-tip {\n";
        foreach ( self::SPARKLINE_KEYS as $key ) {
            if ( (string) $c[ $key ] === '' ) {
                continue;
            }
            $css .= '  --naws-sl-' . str_replace( '_', '-', substr( $key, 10 ) ) . ": {$c[ $key ]};\n";
        }
        return $css . "}\n";
    }
```

- [ ] **Step 7: Gruppe** — in `get_groups()` nach dem `'live_wind'`-Eintrag:

```php
            'sparkline' => [
                'label' => 'appearance_group_sparkline',
                'keys'  => self::SPARKLINE_KEYS,
            ],
```

- [ ] **Step 8:** `php tests/test-appearance.php`, `php tests/test-color-scheme.php`, `php tests/test-heatmap-colors.php`, `php tests/test-live-wind-colors.php` → grün; `php -l`; PHPCS auf `includes/class-naws-colors.php`.

- [ ] **Step 9: Commit**

```bash
git add includes/class-naws-colors.php tests/test-appearance.php
git commit -m "Sparkline: eight appearance colours, as variables on the curve itself"
```

---

### Task 5: Template, `markup()`, `render()` und der Shortcode

**Files:**
- Create: `templates/sparkline.php`
- Modify: `includes/class-naws-sparkline.php` (Abschnitt „Markup" nach `noon()`)
- Modify: `includes/class-naws-shortcodes.php` (`TAGS` nach `'naws_windrose'`; Kommentar „all fifteen" → „all sixteen"; `sc_sparkline()` nach `sc_windrose()`)
- Test: `tests/test-sparkline-render.php`

**Interfaces:**
- Consumes: Tasks 1–3.
- Produces: `NAWS_Sparkline::markup( array $a, array $d ): string`; `NAWS_Sparkline::render( array $atts ): string` (`''`, wenn nichts zu zeichnen ist); `NAWS_Shortcodes::sc_sparkline( $atts ): string`, das `naws-sparkline-boot` einreiht (registriert in Task 6). Markup-Klassen: `naws-sl`, `naws-sl--line|--bars|--band`, `naws-sl-line`, `naws-sl-area`, `naws-sl-band`, `naws-sl-bar`, `naws-sl-base`, `naws-sl-mm`, `naws-sl-ring`, `naws-sl-end`, `naws-sl-val`; Datenattribut `data-naws-sl` = JSON `{"x":[…],"t":[…]}`.

- [ ] **Step 1: Den Test schreiben** — `tests/test-sparkline-render.php`:

```php
<?php
/**
 * Tests fuer templates/sparkline.php ueber NAWS_Sparkline::markup(): die
 * Wurzel, das SVG mit Rolle und Vorlesetext, die Daten fuer die
 * Sprechblase, die Pfade, und dass weder Farben noch ids noch Skript im
 * Markup stehen. Die Rechnung steht in test-sparkline.php.
 *
 *   php tests/test-sparkline-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'time_format' => 'H:i', 'date_format' => 'd.m.Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? 0 ); return $d->format( $fmt ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', ',' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : ''; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-sparkline.php';

/** A station with nothing in it: render() must come back empty. */
class NAWS_Database {
    public static function get_modules( $active_only = false ) { return []; }
    public static function get_readings( $args = [] ) { return []; }
    public static function get_daily_summaries( $args = [] ) { return []; }
}
class NAWS_Calc {
    public static function station_row_id( array $atts ): ?string { return null; }
}

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Die Daten fuer die Sprechblase, so wie das Skript sie liest. */
function hover( string $html ): array {
    if ( ! preg_match( '/data-naws-sl="([^"]*)"/', $html, $m ) ) {
        return [];
    }
    return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
}

echo "\nLinie\n" . str_repeat( '-', 74 ) . "\n";
$d    = [ 'kind' => 'line', 'pts' => [ [ 0, 0.0 ], [ 10, 10.0 ] ], 'band' => false, 'tips' => [ '01:00 · 0.0 °C', '01:10 · 10.0 °C' ], 'aria' => 'Temperature <x> & "y"', 'value' => '10.0 °C' ];
$html = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [] ), $d );
check( 'Wurzel ohne Groessenangabe',          str_starts_with( $html, '<span class="naws-sl naws-sl--line"><svg ' ), true );
check( 'viewBox 80 x 18, gestreckt',          str_contains( $html, 'viewBox="0 0 80 18" preserveAspectRatio="none"' ), true );
check( 'Rolle img',                           str_contains( $html, 'role="img"' ), true );
check( 'Vorlesetext escaped',                 str_contains( $html, 'aria-label="Temperature &lt;x&gt; &amp; &quot;y&quot;"' ), true );
check( 'Sprechblase: x-Positionen (JSON ohne .0)', hover( $html )['x'], [ 2, 78 ] );
check( 'Sprechblase: Texte',                  hover( $html )['t'], [ '01:00 · 0.0 °C', '01:10 · 10.0 °C' ] );
check( 'Linienpfad',                          str_contains( $html, '<path class="naws-sl-line" d="M2.00 16.00 L78.00 2.00" vector-effect="non-scaling-stroke"/>' ), true );
check( 'Flaeche darunter',                    str_contains( $html, '<path class="naws-sl-area" d="M2.00 16.00 L78.00 2.00 L78.00 16.00 L2.00 16.00 Z"/>' ), true );
check( 'Ring und Endpunkt als runde Striche', substr_count( $html, 'd="M78.00 2.00 h0"' ), 2 );
check( 'ohne minmax keine Punkte',            str_contains( $html, 'naws-sl-mm' ), false );
check( 'ohne value keine Zahl',               str_contains( $html, 'naws-sl-val' ), false );
check( 'kein Kreis',                          str_contains( $html, '<circle' ), false );
check( 'keine Farbe im Markup',               (bool) preg_match( '/(fill|stroke)="#|style="[^"]*(color|fill|stroke)/', $html ), false );
// Review Focus 5: zwei Sparklines auf einer Seite duerfen sich keine id teilen.
check( 'keine id',                            str_contains( $html, ' id=' ), false );
check( 'kein Skript',                         str_contains( $html, '<script' ), false );
check( 'endet mit der Wurzel',                str_ends_with( $html, '</span>' ), true );

$v = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'show' => 'value' ] ), $d );
check( 'show=value haengt den Wert an',       str_ends_with( $v, '</svg><span class="naws-sl-val">10.0 °C</span></span>' ), true );
$m = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'show' => 'minmax' ] ), $d );
check( 'show=minmax: zwei Punkte',            substr_count( $m, 'class="naws-sl-mm"' ), 2 );
check( 'Tief links unten',                    str_contains( $m, '<path class="naws-sl-mm" d="M2.00 16.00 h0"' ), true );
$s = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'width' => '120', 'height' => '24' ] ), $d );
check( 'Groesse als Variablen an der Wurzel', str_starts_with( $s, '<span class="naws-sl naws-sl--line" style="--naws-sl-w:120px;--naws-sl-h:24px;">' ), true );
check( 'und als viewBox',                     str_contains( $s, 'viewBox="0 0 120 24"' ), true );
$w = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'width' => '120' ] ), $d );
check( 'nur die Breite gesetzt',              str_contains( $w, 'style="--naws-sl-w:120px;"' ), true );

echo "\nBand\n" . str_repeat( '-', 74 ) . "\n";
$b = NAWS_Sparkline::markup(
    NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '30', 'band' => 'minmax' ] ),
    [ 'kind' => 'line', 'pts' => [ [ 0, 10.0, 8.0, 12.0 ], [ 10, 11.0, 9.0, 14.0 ] ], 'band' => true, 'tips' => [ 'a', 'b' ], 'aria' => 'x', 'value' => '' ]
);
check( 'Modifikator band',                    str_starts_with( $b, '<span class="naws-sl naws-sl--band">' ), true );
check( 'das Band',                            str_contains( $b, '<path class="naws-sl-band" d="M2.00 6.67 L78.00 2.00 L78.00 13.67 L2.00 16.00 Z"/>' ), true );
check( 'statt der Flaeche',                   str_contains( $b, 'naws-sl-area' ), false );

echo "\nBalken\n" . str_repeat( '-', 74 ) . "\n";
$r = NAWS_Sparkline::markup(
    NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'show' => 'minmax' ] ),
    [ 'kind' => 'bars', 'sums' => [ 0.0, 2.0, 1.0, 0.0 ], 'tips' => [ 'a', 'b', 'c', 'd' ], 'aria' => 'Rain', 'value' => '3.0 mm' ]
);
check( 'Modifikator bars',                    str_starts_with( $r, '<span class="naws-sl naws-sl--bars">' ), true );
check( 'zwei Balken',                         substr_count( $r, '<rect class="naws-sl-bar"' ), 2 );
check( 'der erste Balken',                    str_contains( $r, '<rect class="naws-sl-bar" x="20.50" y="1.00" width="18.50" height="16.00"/>' ), true );
check( 'Grundlinie',                          str_contains( $r, '<path class="naws-sl-base" d="M0 17.50 H80" vector-effect="non-scaling-stroke"/>' ), true );
check( 'keine Linie',                         str_contains( $r, 'naws-sl-line' ), false );
check( 'minmax gilt nicht fuer Balken',       str_contains( $r, 'naws-sl-mm' ), false );
check( 'eine x-Position je Fenster',          count( hover( $r )['x'] ), 4 );
$trocken = NAWS_Sparkline::markup( NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain' ] ), [ 'kind' => 'bars', 'sums' => [ 0.0, 0.0 ], 'tips' => [ '', '' ], 'aria' => 'Rain', 'value' => '' ] );
check( 'trocken: nur die Grundlinie',         [ str_contains( $trocken, '<rect' ), str_contains( $trocken, 'naws-sl-base' ) ], [ false, true ] );

echo "\nrender()\n" . str_repeat( '-', 74 ) . "\n";
check( 'unbekannte Groesse: leer',            NAWS_Sparkline::render( [ 'param' => 'Unsinn' ] ), '' );
// Review Focus 3: eine Station ohne Daten ergibt keinen leeren Rahmen.
check( 'Station ohne Daten: leer',            NAWS_Sparkline::render( [ 'param' => 'Temperature' ] ), '' );
check( 'ohne Stationszeile: leer',            NAWS_Sparkline::render( [ 'param' => 'temp_avg', 'days' => '30' ] ), '' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

Weitere Abschnitte dieser Datei (Tasks 6 und 9) kommen **vor** die Zeile `echo "\n" . str_repeat( '-', 74 ) . "\n";` am Ende.

- [ ] **Step 2:** `php tests/test-sparkline-render.php` → `Call to undefined method NAWS_Sparkline::markup()`.

- [ ] **Step 3: Das Template** — `templates/sparkline.php`:

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: one sparkline, rendered by NAWS_Sparkline::markup().
 *
 * Every coordinate went through NAWS_Sparkline::num() and is a plain
 * decimal; texts are escaped here. Colours come from classes and CSS
 * variables only, so nothing in the markup carries a colour — that is
 * what lets the Appearance page, the widget's schemes and the theme
 * restyle a curve without touching it. No ids: a page may carry any
 * number of sparklines.
 *
 * Dots (low, high, ring, end) are zero-length paths with round caps. A
 * <circle> would stretch into an ellipse under preserveAspectRatio="none";
 * a stroke with vector-effect keeps its size and its roundness.
 *
 * Expected variables:
 * @var array $naws_sl kind (line|bars), mod (line|bars|band), w, h, style,
 *                     geo (geometry() or bar_geometry()), minmax (bool),
 *                     hover ([ 'x' => float[], 't' => string[] ]), aria,
 *                     value ('' for none)
 *
 * @package NAWS
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$naws_sl_geo = $naws_sl['geo'];
$naws_sl_dot = static fn( array $xy ): string => 'M' . $xy[0] . ' ' . $xy[1] . ' h0';
?>
<span class="naws-sl naws-sl--<?php echo esc_attr( $naws_sl['mod'] ); ?>"<?php if ( $naws_sl['style'] !== '' ) : ?> style="<?php echo esc_attr( $naws_sl['style'] ); ?>"<?php endif; ?>><svg viewBox="0 0 <?php echo absint( $naws_sl['w'] ); ?> <?php echo absint( $naws_sl['h'] ); ?>" preserveAspectRatio="none" role="img" focusable="false" aria-label="<?php echo esc_attr( $naws_sl['aria'] ); ?>" data-naws-sl="<?php echo esc_attr( (string) wp_json_encode( $naws_sl['hover'] ) ); ?>">
<?php if ( $naws_sl['kind'] === 'bars' ) : ?>
<path class="naws-sl-base" d="M0 <?php echo esc_attr( $naws_sl_geo['base'] ); ?> H<?php echo absint( $naws_sl['w'] ); ?>" vector-effect="non-scaling-stroke"/>
<?php foreach ( $naws_sl_geo['rects'] as $naws_sl_r ) : ?>
<rect class="naws-sl-bar" x="<?php echo esc_attr( $naws_sl_r[0] ); ?>" y="<?php echo esc_attr( $naws_sl_r[1] ); ?>" width="<?php echo esc_attr( $naws_sl_r[2] ); ?>" height="<?php echo esc_attr( $naws_sl_r[3] ); ?>"/>
<?php endforeach; ?>
<?php else : ?>
<?php if ( $naws_sl_geo['band'] !== '' ) : ?>
<path class="naws-sl-band" d="<?php echo esc_attr( $naws_sl_geo['band'] ); ?>"/>
<?php else : ?>
<path class="naws-sl-area" d="<?php echo esc_attr( $naws_sl_geo['area'] ); ?>"/>
<?php endif; ?>
<path class="naws-sl-line" d="<?php echo esc_attr( $naws_sl_geo['line'] ); ?>" vector-effect="non-scaling-stroke"/>
<?php if ( $naws_sl['minmax'] ) : ?>
<path class="naws-sl-mm" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['lo'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<path class="naws-sl-mm" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['hi'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<?php endif; ?>
<path class="naws-sl-ring" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['end'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<path class="naws-sl-end" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['end'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<?php endif; ?>
</svg><?php if ( $naws_sl['value'] !== '' ) : ?><span class="naws-sl-val"><?php echo esc_html( $naws_sl['value'] ); ?></span><?php endif; ?></span>
```

- [ ] **Step 4: `markup()` und `render()`** — in `class-naws-sparkline.php` nach `noon()`:

```php

    // ── Markup ──────────────────────────────────────────────────────

    /**
     * One sparkline as HTML: the geometry for the prepared data, then the
     * template. $a comes from normalise_atts(), $d from prepare() — or is
     * built by hand for the Appearance preview, see sample().
     */
    public static function markup( array $a, array $d ): string {
        if ( $d['kind'] === 'bars' ) {
            $geo = self::bar_geometry( $d['sums'], $a['w'], $a['h'] );
            $mod = 'bars';
        } else {
            $band = ! empty( $d['band'] );
            $geo  = self::geometry( $d['pts'], $a['w'], $a['h'], $band );
            $mod  = $band ? 'band' : 'line';
        }

        $naws_sl = [
            'kind'   => $d['kind'],
            'mod'    => $mod,
            'w'      => $a['w'],
            'h'      => $a['h'],
            'style'  => ( $a['sized_w'] ? '--naws-sl-w:' . $a['w'] . 'px;' : '' ) . ( $a['sized_h'] ? '--naws-sl-h:' . $a['h'] . 'px;' : '' ),
            'geo'    => $geo,
            'minmax' => $a['show'] === 'minmax' && $d['kind'] === 'line',
            'hover'  => [ 'x' => $geo['xs'], 't' => array_values( $d['tips'] ) ],
            'aria'   => $d['aria'],
            'value'  => $a['show'] === 'value' ? $d['value'] : '',
        ];

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sparkline.php';
        return trim( (string) ob_get_clean() );
    }

    /**
     * The entry for the shortcode and the widget: attributes in, HTML out,
     * '' whenever there is nothing to draw.
     */
    public static function render( array $atts ): string {
        $a = self::normalise_atts( $atts );
        if ( $a === null ) {
            return '';
        }
        $d = self::prepare( $a, self::fetch( $a, time() ) );
        return $d === null ? '' : self::markup( $a, $d );
    }
```

- [ ] **Step 5: Shortcode** — in `includes/class-naws-shortcodes.php`:

In `TAGS` nach `'naws_windrose'       => 'sc_windrose',`:

```php
        'naws_sparkline'      => 'sc_sparkline',
```

Im Konstruktor `// One wrapper for all fifteen:` → `// One wrapper for all sixteen:`.

Nach dem Ende von `sc_windrose()`:

```php

    // ----------------------------------------------------------------
    // [naws_sparkline param="Temperature" hours="24" days="" module="" width="" height="" show="none" type="" band=""]
    // A curve the size of a word, since 2.1.0
    // ----------------------------------------------------------------
    public function sc_sparkline( $atts ) {
        $atts = shortcode_atts( [
            'param'  => 'Temperature',
            'hours'  => '',
            'days'   => '',
            'module' => '',
            'width'  => '',
            'height' => '',
            'show'   => 'none',
            'type'   => '',
            'band'   => '',
        ], $atts, 'naws_sparkline' );

        $html = NAWS_Sparkline::render( $atts );
        if ( $html === '' ) {
            return '';
        }

        // Styles and the hover script only for a sparkline that is drawn.
        $this->enqueue_frontend_styles();
        wp_enqueue_script( 'naws-sparkline-boot' );

        return $html;
    }
```

- [ ] **Step 6:** `php tests/test-sparkline-render.php` → alle bestanden; `php tests/test-sparkline.php`, `php tests/test-shortcode-atts.php` → grün. `php -l` auf Template, Klasse, Shortcodes; PHPCS darauf.

- [ ] **Step 7: Commit**

```bash
git add templates/sparkline.php includes/class-naws-sparkline.php includes/class-naws-shortcodes.php tests/test-sparkline-render.php
git commit -m "Sparkline: the template and the shortcode, sixteen now"
```

---

### Task 6: Stylesheet und Sprechblase

**Files:**
- Modify: `assets/css/frontend.css` (Block am Dateiende)
- Create: `assets/js/sparkline-boot.js`
- Modify: `includes/class-naws-shortcodes.php` (`enqueue_frontend_assets()`, nach der Registrierung von `naws-windrose-boot`)
- Test: `tests/test-asset-version.php`, `tests/test-sparkline-render.php`

**Interfaces:**
- Consumes: Markup-Klassen aus Task 5, Variablen aus Task 4 (`--naws-sl-line`, `--naws-sl-line-dark`, `--naws-sl-rain`, `--naws-sl-rain-dark`, `--naws-sl-band`, `--naws-sl-dots`, `--naws-sl-tip-bg`, `--naws-sl-tip-text`), dazu `--naws-sl-ring` (nur aus dem Stylesheet, Task 7) und `--naws-sl-w`/`--naws-sl-h` (Template).
- Produces: Skript-Handle `naws-sparkline-boot`; Kontextklasse `.naws-sl-dark` (dunkler Grund, auch für die Backend-Vorschau in Task 9); Sprechblase `.naws-sl-tip`.

- [ ] **Step 1: Tests anhängen** — `tests/test-asset-version.php`: in der Liste der `foreach`-Schleife nach `'naws-windrose-boot' => 'assets/js/windrose-boot.js',`:

```php
    'naws-sparkline-boot' => 'assets/js/sparkline-boot.js',
```

`tests/test-sparkline-render.php`, vor dem Abschluss:

```php
echo "\nStylesheet und Skript\n" . str_repeat( '-', 74 ) . "\n";
$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );
foreach ( [ 'naws-sl-line', 'naws-sl-area', 'naws-sl-band', 'naws-sl-bar', 'naws-sl-base', 'naws-sl-mm', 'naws-sl-ring', 'naws-sl-end', 'naws-sl-val', 'naws-sl-tip' ] as $klasse ) {
    check( "Regel fuer .$klasse", str_contains( $css, ".$klasse" ), true );
}
check( 'Groesse aus Variablen mit em-Vorgabe', str_contains( $css, 'width:var(--naws-sl-w, 4.6em); height:var(--naws-sl-h, 1.05em);' ), true );
check( 'jede Farbvariable hat die Vorgabe als Rueckfall',
    str_contains( $css, 'var(--naws-sl-line, #427272)' ) && str_contains( $css, 'var(--naws-sl-rain, #3585b0)' ) && str_contains( $css, 'var(--naws-sl-tip-bg, #2d5252)' ), true );
check( 'dunkler Grund liest die Dunkel-Farben', str_contains( $css, 'var(--naws-sl-line-dark, #7cc7c7)' ) && str_contains( $css, 'var(--naws-sl-rain-dark, #78ace8)' ), true );
$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/sparkline-boot.js' );
check( 'das Skript setzt Text, nie HTML',       [ str_contains( $js, 'textContent' ), str_contains( $js, 'innerHTML' ) ], [ true, false ] );
check( 'Delegation am Dokument',                str_contains( $js, "document.addEventListener('pointermove'" ), true );
```

- [ ] **Step 2:** `php tests/test-asset-version.php` und `php tests/test-sparkline-render.php` → FAIL (Registrierung, Regeln und Datei fehlen).

- [ ] **Step 3: Registrierung** — in `enqueue_frontend_assets()` nach dem `wp_register_script( 'naws-windrose-boot', … );`-Aufruf:

```php

        // [naws_sparkline]: the hover bubble. The curves are complete
        // without it; it needs neither jQuery nor the charts.
        wp_register_script( 'naws-sparkline-boot',
            NAWS_PLUGIN_URL . 'assets/js/sparkline-boot.js',
            [], NAWS_Helpers::asset_version( 'assets/js/sparkline-boot.js' ), true );
```

- [ ] **Step 4: Stylesheet** — am Ende von `assets/css/frontend.css` anhängen:

```css

/* ══════════════════════════════════════════════════════════════════
   [naws_sparkline] — a curve the size of a word (since 2.1.0)
   ══════════════════════════════════════════════════════════════════
   The colours arrive as variables on .naws-sl itself (NAWS_Colors::
   sparkline_css()), because a sparkline usually sits in theme text
   outside every plugin wrapper. Every var() carries the default as its
   fallback. Without width/height on the shortcode the curve is sized in
   em and grows with the text around it. Dots are round-capped strokes
   of zero length; their size is the stroke width, in pixels. */
.naws-sl { display:inline-flex; align-items:center; gap:.3em; vertical-align:-.18em; line-height:1; white-space:nowrap; }
.naws-sl svg { display:block; width:var(--naws-sl-w, 4.6em); height:var(--naws-sl-h, 1.05em); max-width:none; overflow:visible; }
.naws-sl-line { fill:none; stroke:var(--naws-sl-line, #427272); stroke-width:1.5px; stroke-linejoin:round; stroke-linecap:round; }
.naws-sl-area { fill:var(--naws-sl-line, #427272); fill-opacity:.14; stroke:none; }
.naws-sl-band { fill:var(--naws-sl-band, #42727229); stroke:none; }
.naws-sl-bar  { fill:var(--naws-sl-rain, #3585b0); stroke:none; }
.naws-sl-base { fill:none; stroke:var(--naws-sl-dots, #7aa0a0); stroke-width:1px; opacity:.5; }
.naws-sl-mm, .naws-sl-ring, .naws-sl-end { fill:none; stroke-linecap:round; }
.naws-sl-mm   { stroke:var(--naws-sl-dots, #7aa0a0); stroke-width:3px; }
.naws-sl-ring { stroke:var(--naws-sl-ring, var(--naws-surface, #fff)); stroke-width:7px; }
.naws-sl-end  { stroke:var(--naws-sl-line, #427272); stroke-width:4px; }
.naws-sl-val  { font-variant-numeric:tabular-nums; }

/* Dark grounds: the sidebar widget's dark scheme and the dark half of the
   Appearance preview read the "on dark ground" colours. The variables are
   not redirected, the drawing rules read another one — so the preview can
   set any variable on the element without undoing this. */
.naws-wgt--dark .naws-sl-line, .naws-wgt--dark .naws-sl-end,
.naws-sl-dark .naws-sl-line, .naws-sl-dark .naws-sl-end { stroke:var(--naws-sl-line-dark, #7cc7c7); }
.naws-wgt--dark .naws-sl-area, .naws-sl-dark .naws-sl-area { fill:var(--naws-sl-line-dark, #7cc7c7); }
.naws-wgt--dark .naws-sl-bar, .naws-sl-dark .naws-sl-bar { fill:var(--naws-sl-rain-dark, #78ace8); }
.naws-sl-dark .naws-sl-ring { stroke:#1c2433; }

/* The hover bubble: one element for the whole page, hung on <body> by
   sparkline-boot.js and placed above the point. */
.naws-sl-tip {
  position:fixed; z-index:99999; pointer-events:none;
  transform:translate(-50%, -100%); margin-top:-6px;
  padding:3px 7px; border-radius:4px;
  font-family:var(--naws-font, inherit); font-size:12px; line-height:1.3; white-space:nowrap;
  background:var(--naws-sl-tip-bg, #2d5252); color:var(--naws-sl-tip-text, #fff);
}
.naws-sl-tip[hidden] { display:none; }
```

- [ ] **Step 5: Skript** — `assets/js/sparkline-boot.js`:

```js
/**
 * [naws_sparkline] — the hover bubble.
 *
 * The curves are complete without this file. Every <svg data-naws-sl>
 * carries the x position of each point, in viewBox units, and the text for
 * it, formatted on the server in the site's language and units; this only
 * finds the point nearest the pointer and shows its text. One bubble and
 * three listeners serve every sparkline on the page, however many there are.
 *
 * Mouse: the bubble follows the pointer and goes when it leaves the curve.
 * Touch: a tap shows it, a tap anywhere else or a scroll hides it.
 *
 * @package NAWS
 */
(function () {
    'use strict';

    var tip = null;
    var cache = typeof WeakMap === 'function' ? new WeakMap() : null;

    function data(svg) {
        var d = cache ? cache.get(svg) : null;
        if (d) { return d; }
        try {
            d = JSON.parse(svg.getAttribute('data-naws-sl') || '{}');
        } catch (e) {
            d = {};
        }
        if (!d || !d.x || !d.t) { d = { x: [], t: [] }; }
        if (cache) { cache.set(svg, d); }
        return d;
    }

    function bubble() {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'naws-sl-tip';
            tip.setAttribute('role', 'tooltip');
            tip.hidden = true;
            document.body.appendChild(tip);
        }
        return tip;
    }

    function hide() {
        if (tip) { tip.hidden = true; }
    }

    function show(svg, clientX) {
        var d = data(svg);
        var vb = svg.viewBox && svg.viewBox.baseVal ? svg.viewBox.baseVal.width : 0;
        var r = svg.getBoundingClientRect();
        if (!d.x.length || !vb || !r.width) { hide(); return; }

        var fx = (clientX - r.left) / r.width * vb, best = 0, bd = Infinity, i;
        for (i = 0; i < d.x.length; i++) {
            var dd = Math.abs(d.x[i] - fx);
            if (dd < bd) { bd = dd; best = i; }
        }

        var t = bubble();
        t.textContent = d.t[best] || '';
        if (t.textContent === '') { t.hidden = true; return; }
        t.hidden = false;

        // Above the point, caught at the edges of the window.
        var half = t.offsetWidth / 2;
        var vw = document.documentElement.clientWidth;
        var x = r.left + d.x[best] / vb * r.width;
        t.style.left = Math.max(half + 4, Math.min(vw - half - 4, x)) + 'px';
        t.style.top = r.top + 'px';
    }

    function target(e) {
        return e.target && e.target.closest ? e.target.closest('svg[data-naws-sl]') : null;
    }

    document.addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') { return; }
        var svg = target(e);
        if (svg) { show(svg, e.clientX); } else { hide(); }
    });
    document.addEventListener('pointerdown', function (e) {
        var svg = target(e);
        if (svg) { show(svg, e.clientX); } else { hide(); }
    });
    window.addEventListener('scroll', hide, { passive: true });
})();
```

- [ ] **Step 6:** `node --check assets/js/sparkline-boot.js`; `php tests/test-asset-version.php`, `php tests/test-sparkline-render.php` → grün; `php -l includes/class-naws-shortcodes.php`; PHPCS darauf.

- [ ] **Step 7: Commit**

```bash
git add assets/css/frontend.css assets/js/sparkline-boot.js includes/class-naws-shortcodes.php tests/test-asset-version.php tests/test-sparkline-render.php
git commit -m "Sparkline: the stylesheet, dark grounds, and one hover bubble for the page"
```

---

### Task 7: Widget — Haken, Attribut, Kurven in Kopf und Kacheln

**Files:**
- Modify: `includes/class-naws-widget-data.php` (neue Methode `sparklines_on()` nach `normalise_scheme()`)
- Modify: `includes/class-naws-sparkline.php` (neue Methode `widget_set()` nach `render()`)
- Modify: `includes/class-naws-shortcodes.php` (`sc_weather_widget()`)
- Modify: `templates/weather-widget.php`
- Modify: `includes/class-naws-admin.php` (`sanitize_settings()` nach dem `wgt_scheme`-Block; Asset-Methode nach der Einbindung von `naws-weather-icon`)
- Modify: `includes/class-naws-labels.php` (nach `case 'wgt_scheme_desc':`)
- Modify: `admin/views/appearance.php` (Widget-Formular: Zeile nach der Farbschema-Zeile; Vorschau)
- Modify: `assets/css/frontend.css` (Block am Dateiende)
- Test: `tests/test-widget-sparklines.php` (neu), `tests/test-settings-merge.php`

**Interfaces:**
- Consumes: `NAWS_Sparkline::render()` (Task 5), `.naws-wgt--dark`-Regeln (Task 6).
- Produces: `NAWS_Widget_Data::sparklines_on( $raw ): bool` (wahr für `1`, `yes`, `true`, `on`, sonst falsch); `NAWS_Sparkline::widget_set(): array` mit `temp`, `rain`, `wind` (je HTML oder `''`); Einstellung `naws_settings['wgt_sparklines']` (0/1); Shortcode-Attribut `sparklines`; Template-Variable `$naws_wgt_spark` (Array wie `widget_set()`, darf fehlen); Labels `wgt_sparklines_label`, `wgt_sparklines_check`, `wgt_sparklines_desc`.

- [ ] **Step 1: Den Test schreiben** — `tests/test-widget-sparklines.php`:

```php
<?php
/**
 * Tests fuer die Sparklines im Seitenleisten-Widget (seit 2.1.0): der
 * Schalter, das Template mit und ohne Kurven, die Sanitierung der
 * Einstellung, der Shortcode und das Stylesheet.
 *
 *   php tests/test-widget-sparklines.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['naws_stored'] = [ 'wgt_days' => 5, 'wgt_width' => 250, 'wgt_scheme' => 'light', 'wgt_sparklines' => 1 ];
function get_option( $key, $default = false ) {
    return 'naws_settings' === $key ? $GLOBALS['naws_stored'] : $default;
}
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function do_action( ...$a ) {}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function absint( $n ) { return abs( (int) $n ); }
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Crypto {
    public static function is_encrypted( $s ) { return str_starts_with( (string) $s, 'ENC:' ); }
    public static function encrypt( $s ) { return 'ENC:' . $s; }
    public static function migrate() {}
}
class NAWS_Forecast {
    public static function flush_cache() {}
}

require_once $PLUGIN . 'includes/class-naws-widget-data.php';
require_once $PLUGIN . 'includes/class-naws-cron.php';
require_once $PLUGIN . 'includes/class-naws-admin.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Widget_Data::sparklines_on()\n" . str_repeat( '-', 74 ) . "\n";
foreach ( [ [ '1', true ], [ 1, true ], [ 'yes', true ], [ ' TRUE ', true ], [ 'on', true ],
            [ '0', false ], [ 0, false ], [ '', false ], [ null, false ], [ 'ja', false ], [ '2', false ] ] as list( $in, $want ) ) {
    check( 'sparklines_on( ' . var_export( $in, true ) . ' )', NAWS_Widget_Data::sparklines_on( $in ), $want );
}

echo "\ntemplates/weather-widget.php\n" . str_repeat( '-', 74 ) . "\n";

function render_widget( array $vars ): string {
    $naws_wgt = [
        'empty' => false,
        'temp'  => [ 'value' => '12.3', 'unit' => '°C' ],
        'tiles' => [ [ 'key' => 'rain', 'value' => '4.2', 'unit' => 'mm' ], [ 'key' => 'wind', 'value' => '6', 'unit' => 'km/h' ] ],
        'days'  => [],
    ];
    $naws_wgt_state  = '';
    $naws_wgt_place  = '';
    $naws_wgt_time   = '';
    $naws_wgt_width  = 250;
    $naws_wgt_scheme = 'light';
    extract( $vars, EXTR_OVERWRITE );
    ob_start();
    include dirname( __DIR__ ) . '/templates/weather-widget.php';
    return ob_get_clean();
}

$ohne = render_widget( [] );
check( 'ohne Variable: keine Kurve (wie 2.0.2)', str_contains( $ohne, 'naws-wgt-spark' ), false );

$leer = render_widget( [ 'naws_wgt_spark' => [ 'temp' => '', 'rain' => '', 'wind' => '' ] ] );
check( 'leere Kurven: kein Kasten',              str_contains( $leer, 'naws-wgt-spark' ), false );

$mit = render_widget( [ 'naws_wgt_spark' => [ 'temp' => '<span class="naws-sl">T</span>', 'rain' => '<span class="naws-sl">R</span>', 'wind' => '<span class="naws-sl">W</span>' ] ] );
check( 'Temperatur im Kopf',                     str_contains( $mit, '<div class="naws-wgt-spark naws-wgt-spark--head"><span class="naws-sl">T</span></div>' ), true );
check( 'Regen in seiner Kachel',                 str_contains( $mit, '<div class="naws-wgt-spark"><span class="naws-sl">R</span></div>' ), true );
check( 'Wind in seiner Kachel',                  str_contains( $mit, '<div class="naws-wgt-spark"><span class="naws-sl">W</span></div>' ), true );
check( 'Kurve steht unter dem Wert',             strpos( $mit, '4.2' ) < strpos( $mit, '>R<' ), true );

$teil = render_widget( [ 'naws_wgt_spark' => [ 'temp' => '', 'rain' => '<span class="naws-sl">R</span>', 'wind' => '' ] ] );
check( 'fehlende Kurve faellt einzeln weg',      substr_count( $teil, 'naws-wgt-spark' ), 1 );

echo "\nNAWS_Admin::sanitize_settings()\n" . str_repeat( '-', 74 ) . "\n";
$admin = ( new ReflectionClass( 'NAWS_Admin' ) )->newInstanceWithoutConstructor();
check( 'Haken an',                               $admin->sanitize_settings( [ 'wgt_sparklines' => '1' ] )['wgt_sparklines'] ?? null, 1 );
check( 'verstecktes Feld 0 schaltet aus',        $admin->sanitize_settings( [ 'wgt_sparklines' => '0' ] )['wgt_sparklines'] ?? null, 0 );
check( 'ohne Feld bleibt der Haken stehen',      $admin->sanitize_settings( [ 'wgt_days' => '3' ] )['wgt_sparklines'] ?? null, 1 );

echo "\nShortcode und Stylesheet\n" . str_repeat( '-', 74 ) . "\n";
$sc = (string) file_get_contents( $PLUGIN . 'includes/class-naws-shortcodes.php' );
check( 'Attribut mit der Einstellung als Vorgabe', str_contains( $sc, "'sparklines' => (string) ( \$opts['wgt_sparklines'] ?? 0 )," ), true );
check( 'der Schalter entscheidet',                 str_contains( $sc, "NAWS_Widget_Data::sparklines_on( \$atts['sparklines'] )" ), true );
check( 'das Skript nur mit Kurven',                str_contains( $sc, "if ( implode( '', \$naws_wgt_spark ) !== '' ) {" ), true );
$css = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
check( 'Kurven fuellen ihre Kachel',               str_contains( $css, '.naws-wgt-spark .naws-sl svg { width:100%;' ), true );
check( 'transparent zeichnet in der Textfarbe',    str_contains( $css, '.naws-wgt--transparent .naws-sl-line' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

In `tests/test-settings-merge.php`: im Array `$GLOBALS['naws_stored']` nach `'data_retention'    => 200,` die Zeile `'wgt_sparklines'    => 1,` ergänzen, und vor `echo str_repeat( '-', 70 ) . "\n";` am Ende:

```php
scenario(
    'Zugangsdaten speichern laesst den Sparkline-Haken stehen',
    [ 'client_id' => 'newid' ],
    [ 'wgt_sparklines' ],
    [ 'client_id' => 'ENC:newid' ]
);
```

- [ ] **Step 2:** `php tests/test-widget-sparklines.php` → `Call to undefined method NAWS_Widget_Data::sparklines_on()`.

- [ ] **Step 3: Schalter** — in `includes/class-naws-widget-data.php` nach `normalise_scheme()`:

```php

    /**
     * Whether the widget draws its sparklines (since 2.1.0). The setting
     * stores 0 or 1; the shortcode attribute may also say yes, true or on.
     * Anything else is off, so a typo never switches curves on.
     *
     * @param mixed $raw
     */
    public static function sparklines_on( $raw ): bool {
        return in_array( strtolower( trim( (string) $raw ) ), [ '1', 'yes', 'true', 'on' ], true );
    }
```

- [ ] **Step 4: `widget_set()`** — in `class-naws-sparkline.php` nach `render()`:

```php

    /** The widget's three curves over 24 hours; '' where a module is missing or silent. */
    public static function widget_set(): array {
        return [
            'temp' => self::render( [ 'param' => 'Temperature' ] ),
            'rain' => self::render( [ 'param' => 'Rain' ] ),
            'wind' => self::render( [ 'param' => 'WindStrength' ] ),
        ];
    }
```

- [ ] **Step 5: Shortcode** — in `sc_weather_widget()`:

`shortcode_atts` bekommt nach der `'scheme'`-Zeile:

```php
            'sparklines' => (string) ( $opts['wgt_sparklines'] ?? 0 ),
```

und nach `$naws_wgt_scheme = NAWS_Widget_Data::normalise_scheme( $atts['scheme'] );`:

```php
        // Sparklines (since 2.1.0, off unless switched on): 24 hours of
        // temperature, rain and wind. A missing module drops its curve.
        $naws_wgt_spark = NAWS_Widget_Data::sparklines_on( $atts['sparklines'] )
            ? NAWS_Sparkline::widget_set()
            : [ 'temp' => '', 'rain' => '', 'wind' => '' ];
        if ( implode( '', $naws_wgt_spark ) !== '' ) {
            wp_enqueue_script( 'naws-sparkline-boot' );
        }
```

Den Kommentar über der Methode `// [naws_weather_widget days="3|5" width="250..500"]` ersetzen durch `// [naws_weather_widget days="3|5" width="250..500" scheme="light|dark|transparent" sparklines="0|1"]`.

- [ ] **Step 6: Template** — `templates/weather-widget.php`:

Im Kopfkommentar unter `@var string  $naws_wgt_scheme …`:

```php
 * @var array   $naws_wgt_spark  temp|rain|wind => sparkline HTML or ''; optional (since 2.1.0)
```

Nach der Zeile `$naws_wgt_class  = 'naws-wgt' . …;`:

```php
// Absent before 2.1.0 and in every caller that does not ask for curves.
$naws_wgt_spark = isset( $naws_wgt_spark ) && is_array( $naws_wgt_spark ) ? $naws_wgt_spark : [];
```

Im Kopf nach dem `naws-wgt-cond`-Block (nach dessen `<?php endif; ?>`, noch innerhalb von `naws-wgt-head-txt`):

```php
      <?php if ( ( $naws_wgt_spark['temp'] ?? '' ) !== '' ) : ?>
        <div class="naws-wgt-spark naws-wgt-spark--head"><?php echo $naws_wgt_spark['temp']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
      <?php endif; ?>
```

In der Kachel nach der Zeile mit `<span class="naws-wgt-v">…</span>`:

```php
          <?php if ( ( $naws_wgt_spark[ $naws_wgt_tile['key'] ] ?? '' ) !== '' ) : ?>
            <div class="naws-wgt-spark"><?php echo $naws_wgt_spark[ $naws_wgt_tile['key'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
          <?php endif; ?>
```

- [ ] **Step 7: Sanitierung und Farbvariablen im Backend** — in `includes/class-naws-admin.php`, `sanitize_settings()`, direkt nach dem Block `if ( $sent( 'wgt_scheme' ) ) { … }`:

```php

        // Sparklines in the widget (since 2.1.0): a hidden-zero checkbox.
        if ( $sent( 'wgt_sparklines' ) ) {
            $clean['wgt_sparklines'] = empty( $input['wgt_sparklines'] ) ? 0 : 1;
        }
```

Und in der Asset-Methode nach dem Block, der `naws-weather-icon` einbindet (endet mit `wp_enqueue_style( 'naws-weather-icon', … );` und `}`):

```php

        // The sparklines on the Appearance page — the Sparkline tab and the
        // widget preview — read their colours from the variables that the
        // frontend gets inline. The same method writes them here.
        if ( strpos( $hook, 'naws-appearance' ) !== false ) {
            wp_add_inline_style( 'naws-weather-icon', NAWS_Colors::sparkline_css() );
        }
```

- [ ] **Step 8: Beschriftungen** — in `includes/class-naws-labels.php` nach `case 'wgt_scheme_desc': …`:

```php
        case 'wgt_sparklines_label':               return __( 'Sparklines', 'xtx-integration-for-netatmo' );
        case 'wgt_sparklines_check':               return __( 'Show the last 24 hours as small curves', 'xtx-integration-for-netatmo' );
        case 'wgt_sparklines_desc':                return __( 'Temperature under the figure in the head, rain as bars and wind as a line in their tiles. Colours on the Sparkline tab above. sparklines="1" or sparklines="0" on the shortcode overrides this for one placement.', 'xtx-integration-for-netatmo' );
```

- [ ] **Step 9: Backend-Formular und Vorschau** — `admin/views/appearance.php`, im Widget-Formular nach der `</tr>` der Farbschema-Zeile (die mit `wgt_scheme_desc` endet), vor `</table>`:

```php
                    <tr>
                        <th><?php echo esc_html( naws_label( 'wgt_sparklines_label' ) ); ?></th>
                        <td>
                            <input type="hidden" name="naws_settings[wgt_sparklines]" value="0">
                            <label><input type="checkbox" name="naws_settings[wgt_sparklines]" value="1" <?php checked( ! empty( $naws_wgt_opts['wgt_sparklines'] ) ); ?>> <?php echo esc_html( naws_label( 'wgt_sparklines_check' ) ); ?></label>
                            <p class="description"><?php echo esc_html( naws_label( 'wgt_sparklines_desc' ) ); ?></p>
                        </td>
                    </tr>
```

In der Vorschau im `else`-Zweig nach `$naws_wgt_scheme = $naws_wgt_scheme_now;`:

```php
                        $naws_wgt_spark  = ! empty( $naws_wgt_opts['wgt_sparklines'] ) ? NAWS_Sparkline::widget_set() : [];
```

- [ ] **Step 10: Stylesheet** — am Ende von `assets/css/frontend.css`:

```css

/* Sparklines in the sidebar widget (since 2.1.0, off by default). The
   curve fills its box; the viewBox stretches with it and the strokes keep
   their weight. The ring under the end dot takes the colour of what it
   sits on: the card in the head, the chip in the tiles. */
.naws-wgt-spark { margin-top:4px; line-height:0; }
.naws-wgt-spark .naws-sl { display:flex; width:100%; }
.naws-wgt-spark .naws-sl svg { width:100%; height:20px; }
.naws-wgt-spark--head .naws-sl svg { height:16px; }
.naws-wgt-head-txt:has(> .naws-wgt-spark) { flex:1 1 auto; min-width:0; }
.naws-wgt .naws-sl { --naws-sl-ring:var(--naws-wgt-bg, #fff); }
.naws-wgt-chip .naws-sl { --naws-sl-ring:var(--naws-wgt-chip, #f2f6fc); }
.naws-wgt--transparent .naws-sl-line, .naws-wgt--transparent .naws-sl-end { stroke:currentColor; }
.naws-wgt--transparent .naws-sl-area, .naws-wgt--transparent .naws-sl-bar { fill:currentColor; }
.naws-wgt--transparent .naws-sl-mm, .naws-wgt--transparent .naws-sl-base { stroke:var(--naws-wgt-muted); }
.naws-wgt--transparent .naws-sl-ring { stroke:transparent; }
```

- [ ] **Step 11:** `php tests/test-widget-sparklines.php`, `php tests/test-settings-merge.php`, `php tests/test-widget-scheme.php`, `php tests/test-widget-footer.php`, `php tests/test-widget-data.php` → grün. `php -l` auf alle angefassten PHP-Dateien; PHPCS darauf.

- [ ] **Step 12: Commit**

```bash
git add includes/class-naws-widget-data.php includes/class-naws-sparkline.php includes/class-naws-shortcodes.php templates/weather-widget.php includes/class-naws-admin.php includes/class-naws-labels.php admin/views/appearance.php assets/css/frontend.css tests/test-widget-sparklines.php tests/test-settings-merge.php
git commit -m "Widget: optional sparklines for temperature, rain and wind, off by default"
```

---

### Task 8: Reiter im WordPress-Stil, und der offene Reiter bleibt nach dem Speichern

**Files:**
- Modify: `includes/class-naws-colors.php` (zwei neue Methoden vor dem Docblock `Get color groups with their keys, for rendering the admin form.`)
- Modify: `includes/class-naws-admin.php` (`handle_save_appearance()`)
- Modify: `admin/views/appearance.php` (`$tabs`, Reiterleiste, verstecktes Feld, alle acht Flächen, Umschalt-Skript)
- Modify: `admin/views/rest-api-docs.php` (zwei Reiterleisten, Umschalt-Skript)
- Modify: `assets/css/admin.css` (Regeln `.naws-appearance-tabs`/`.naws-appearance-tab*` und `.naws-tab-bar`/`.naws-tab*`)
- Test: `tests/test-appearance.php`

**Interfaces:**
- Produces: `NAWS_Colors::appearance_tabs(): array` (id ⇒ übersetzte Beschriftung, Reihenfolge der Seite); `NAWS_Colors::appearance_tab( string $id ): string` (bekannte id oder `'theme'`); Formularfeld `naws_tab`; Weiterleitung `admin.php?page=naws-appearance&updated=1&tab=<id>`; View-Hilfen `$naws_tab_now` und `$naws_pane_class( string $id ): string` (`' active'` oder `''`).

- [ ] **Step 1: Tests anhängen** — in `tests/test-appearance.php` vor der Zeile `echo str_repeat( '-', 74 ) . "\n";` am Ende:

```php
echo "\nReiter des Erscheinungsbilds\n";

check( 'acht Reiter, Basis zuerst', array_keys( NAWS_Colors::appearance_tabs() ), [
    'theme', 'icons', 'live_wind', 'chart24h', 'charttheme', 'history', 'heatmap', 'windrose',
] );
check( 'ein bekannter Reiter bleibt',         NAWS_Colors::appearance_tab( 'windrose' ), 'windrose' );
check( 'ein unbekannter wird Basis',          NAWS_Colors::appearance_tab( 'nonsense' ), 'theme' );
check( 'leer wird Basis',                     NAWS_Colors::appearance_tab( '' ), 'theme' );

$admin_src = (string) file_get_contents( __DIR__ . '/../includes/class-naws-admin.php' );
check( 'Speichern liest den Reiter sanitiert', str_contains( $admin_src, "NAWS_Colors::appearance_tab( isset( \$_POST['naws_tab'] ) ? sanitize_key( wp_unslash( \$_POST['naws_tab'] ) ) : '' )" ), true );
check( '… und kehrt dorthin zurueck',         str_contains( $admin_src, "admin_url( 'admin.php?page=naws-appearance&updated=1&tab=' . \$tab )" ), true );

$view = (string) file_get_contents( __DIR__ . '/../admin/views/appearance.php' );
check( 'Reiterleiste im WordPress-Stil',      str_contains( $view, '<nav class="nav-tab-wrapper naws-appearance-tabs"' ), true );
check( 'der View liest die Liste aus NAWS_Colors', str_contains( $view, '$tabs = NAWS_Colors::appearance_tabs();' ), true );
check( 'jede Flaeche kennt ihren Zustand',    substr_count( $view, 'class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class(' ), count( NAWS_Colors::appearance_tabs() ) );
check( 'keine fest aktive Flaeche mehr',      str_contains( $view, 'class="naws-appearance-pane active"' ), false );
check( 'verstecktes Feld fuer den Reiter',    str_contains( $view, '<input type="hidden" name="naws_tab" id="naws-tab-field"' ), true );

$rest = (string) file_get_contents( __DIR__ . '/../admin/views/rest-api-docs.php' );
check( 'REST-Seite: zwei Reiterleisten im WordPress-Stil', substr_count( $rest, 'class="nav-tab-wrapper naws-tab-bar"' ), 2 );
check( 'REST-Seite: aktive Reiter markiert',  substr_count( $rest, 'class="nav-tab naws-tab nav-tab-active active"' ), 2 );
```

- [ ] **Step 2:** `php tests/test-appearance.php` → `Call to undefined method NAWS_Colors::appearance_tabs()`.

- [ ] **Step 3: Die Liste** — in `includes/class-naws-colors.php` vor dem Docblock `Get color groups with their keys, for rendering the admin form.`:

```php
    /**
     * The tabs of the Appearance page, id => label, in display order. One
     * list for the view that draws them and for the handler that returns
     * to the one that was open, so the two cannot disagree.
     */
    public static function appearance_tabs(): array {
        return [
            'theme'      => __( 'Base Theme', 'xtx-integration-for-netatmo' ),
            'icons'      => __( 'Icons', 'xtx-integration-for-netatmo' ),
            'live_wind'  => __( 'Live dashboard: wind', 'xtx-integration-for-netatmo' ),
            'chart24h'   => __( '24h Chart Colors', 'xtx-integration-for-netatmo' ),
            'charttheme' => __( 'Chart Theming', 'xtx-integration-for-netatmo' ),
            'history'    => __( 'Year Comparison Palette', 'xtx-integration-for-netatmo' ),
            'heatmap'    => __( 'Heatmap Scale', 'xtx-integration-for-netatmo' ),
            'windrose'   => __( 'Wind Rose', 'xtx-integration-for-netatmo' ),
        ];
    }

    /** A tab id that exists, or 'theme'. */
    public static function appearance_tab( string $id ): string {
        return array_key_exists( $id, self::appearance_tabs() ) ? $id : 'theme';
    }

```

- [ ] **Step 4: Der Handler** — in `includes/class-naws-admin.php`, `handle_save_appearance()`, die Zeile

```php
        wp_safe_redirect( admin_url( 'admin.php?page=naws-appearance&updated=1' ) );
```

ersetzen durch:

```php
        // Back to the tab that was open (since 2.1.0). The tab switcher keeps
        // the field current; anything that is not a tab id becomes the first.
        $tab = NAWS_Colors::appearance_tab( isset( $_POST['naws_tab'] ) ? sanitize_key( wp_unslash( $_POST['naws_tab'] ) ) : '' );

        wp_safe_redirect( admin_url( 'admin.php?page=naws-appearance&updated=1&tab=' . $tab ) );
```

- [ ] **Step 5: Der View** — `admin/views/appearance.php`:

(a) Den Block

```php
// Tab definitions
$tabs = [
    'theme'     => __( 'Base Theme', 'xtx-integration-for-netatmo' ),
    …
    'windrose'  => __( 'Wind Rose', 'xtx-integration-for-netatmo' ),
];
```

(von `// Tab definitions` bis einschließlich des schließenden `];`) ersetzen durch:

```php
// Tab definitions: one list with the handler that returns to the open tab.
$tabs = NAWS_Colors::appearance_tabs();
// The open tab: after a save the handler passes it back as ?tab=.
$naws_tab_now    = NAWS_Colors::appearance_tab( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab is open, read-only UI state, validated against the tab list
$naws_pane_class = static fn( string $id ): string => $id === $naws_tab_now ? ' active' : '';
```

(b) Die Reiterleiste

```php
        <!-- ── Tab Navigation ── -->
        <div class="naws-appearance-tabs">
            <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                <button type="button" class="naws-appearance-tab<?php echo $tab_id === 'theme' ? ' active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </button>
            <?php endforeach; ?>
        </div>
```

ersetzen durch:

```php
        <!-- ── Tab Navigation ── -->
        <nav class="nav-tab-wrapper naws-appearance-tabs" aria-label="<?php esc_attr_e( 'Appearance sections', 'xtx-integration-for-netatmo' ); ?>">
            <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                <button type="button" class="nav-tab naws-appearance-tab<?php echo esc_attr( $tab_id === $naws_tab_now ? ' nav-tab-active active' : '' ); ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>" aria-pressed="<?php echo esc_attr( $tab_id === $naws_tab_now ? 'true' : 'false' ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </button>
            <?php endforeach; ?>
        </nav>
        <input type="hidden" name="naws_tab" id="naws-tab-field" value="<?php echo esc_attr( $naws_tab_now ); ?>">
```

(c) Die acht Flächen — jede öffnende Zeile ersetzen (die Reihenfolge in der Datei ist theme, icons, live_wind, chart24h, charttheme, history, heatmap, windrose):

```php
        <div class="naws-appearance-pane active" data-pane="theme">
```
→
```php
        <div class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class( 'theme' ) ); ?>" data-pane="theme">
```

und für jede der sieben übrigen `<div class="naws-appearance-pane" data-pane="X">` →
`<div class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class( 'X' ) ); ?>" data-pane="X">`, mit `X` = `icons`, `live_wind`, `chart24h`, `charttheme`, `history`, `heatmap`, `windrose`. Mechanisch in Git Bash:

```bash
f=admin/views/appearance.php
sed -i 's/<div class="naws-appearance-pane active" data-pane="theme">/<div class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class( '"'"'theme'"'"' ) ); ?>" data-pane="theme">/' "$f"
for p in icons live_wind chart24h charttheme history heatmap windrose; do
  sed -i "s/<div class=\"naws-appearance-pane\" data-pane=\"$p\">/<div class=\"naws-appearance-pane<?php echo esc_attr( \$naws_pane_class( '$p' ) ); ?>\" data-pane=\"$p\">/" "$f"
done
grep -c 'naws_pane_class(' "$f"   # 8
```

Nach dem `sed` die Zeilenenden prüfen (`git diff --stat` darf nur die geänderten Zeilen zählen, nicht die ganze Datei; siehe Gedächtnis „Zeilenenden-Fallen").

(d) Das Umschalt-Skript — im Nowdoc am Seitenende die ersten sechs Zeilen des Klick-Handlers

```js
    $('.naws-appearance-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.naws-appearance-tab').removeClass('active');
        $(this).addClass('active');
        $('.naws-appearance-pane').removeClass('active');
        $('.naws-appearance-pane[data-pane="'+tab+'"]').addClass('active');
```

ersetzen durch:

```js
    $('.naws-appearance-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.naws-appearance-tab').removeClass('active nav-tab-active').attr('aria-pressed', 'false');
        $(this).addClass('active nav-tab-active').attr('aria-pressed', 'true');
        $('.naws-appearance-pane').removeClass('active');
        $('.naws-appearance-pane[data-pane="'+tab+'"]').addClass('active');
        // Remember the tab: the colour form sends it back through its hidden
        // field, the address carries it for a reload, and the referer fields
        // follow, so the widget form further down returns here as well.
        $('#naws-tab-field').val(tab);
        if (window.history && window.history.replaceState && window.URL) {
            var u = new URL(window.location.href);
            u.searchParams.set('tab', tab);
            u.searchParams.delete('updated');
            u.searchParams.delete('reset');
            window.history.replaceState(null, '', u.toString());
            $('input[name="_wp_http_referer"]').val(u.pathname + u.search);
        }
```

- [ ] **Step 6: REST-Seite** — `admin/views/rest-api-docs.php`:

```bash
f=admin/views/rest-api-docs.php
sed -i 's/<div class="naws-tab-bar">/<div class="nav-tab-wrapper naws-tab-bar">/; s/<button class="naws-tab active"/<button type="button" class="nav-tab naws-tab nav-tab-active active"/; s/<button class="naws-tab"/<button type="button" class="nav-tab naws-tab"/' "$f"
grep -c 'nav-tab-wrapper naws-tab-bar' "$f"   # 2
```

Das `s///` ohne `g` ersetzt je Zeile einmal; die Leisten stehen je Knopf auf einer eigenen Zeile, das reicht. Im Skript am Dateiende (`/* Tab switching */`) die zwei Zeilen

```js
            panel.querySelectorAll(\'.naws-tab\').forEach(function(t){ t.classList.remove(\'active\'); });
```
und
```js
            this.classList.add(\'active\');
```

ersetzen durch

```js
            panel.querySelectorAll(\'.naws-tab\').forEach(function(t){ t.classList.remove(\'active\', \'nav-tab-active\'); });
```
und
```js
            this.classList.add(\'active\', \'nav-tab-active\');
```

(Das Skript steht dort in einem PHP-String mit einfachen Anführungszeichen; die `\'` bleiben.)

- [ ] **Step 7: Stylesheet** — in `assets/css/admin.css` die vier Zeilen

```css
.naws-appearance-tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin:1rem 0 0;}
.naws-appearance-tab{padding:10px 20px;border:none;background:none;font-size:13px;font-weight:600;color:#646970;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color 0.15s,border-color 0.15s;white-space:nowrap;}
.naws-appearance-tab:hover{color:#1d2327;}
.naws-appearance-tab.active{color:#2271b1;border-bottom-color:#2271b1;}
```

ersetzen durch:

```css
/* The Appearance tabs are WordPress's own nav-tabs since 2.1.0: grey file
   cards, the active one joined to the page, floating and wrapping like the
   core ones. They are buttons rather than links, so they need the font and
   the cursor a link would bring. */
.naws-appearance-tabs.nav-tab-wrapper{margin:1rem 0 0;}
.naws-appearance-tabs .nav-tab{font-family:inherit;cursor:pointer;}
.naws-appearance-tabs .nav-tab:focus{outline:2px solid #2271b1;outline-offset:-2px;box-shadow:none;}
```

und die vier Zeilen

```css
.naws-tab-bar{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:16px}
.naws-tab{padding:8px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s}
.naws-tab:hover{color:#334155}
.naws-tab.active{color:#3b82f6;border-bottom-color:#3b82f6}
```

ersetzen durch:

```css
.naws-tab-bar.nav-tab-wrapper{margin-bottom:16px}
.naws-tab-bar .nav-tab{font-family:inherit;cursor:pointer}
```

- [ ] **Step 8:** `php tests/test-appearance.php` → grün; `php -l` auf die vier PHP-Dateien; PHPCS darauf (die `$_GET`-Zeile trägt ihren begründeten `phpcs:ignore`).

- [ ] **Step 9: Commit**

```bash
git add includes/class-naws-colors.php includes/class-naws-admin.php admin/views/appearance.php admin/views/rest-api-docs.php assets/css/admin.css tests/test-appearance.php
git commit -m "Appearance: WordPress tabs that wrap, and the open tab survives a save"
```

---

### Task 9: Erscheinungsbild — Reiter „Sparkline" mit Live-Vorschau

**Files:**
- Modify: `includes/class-naws-colors.php` (`appearance_tabs()`: ein Eintrag)
- Modify: `includes/class-naws-sparkline.php` (neue Methoden `sample()` und `preview_set()` nach `widget_set()`)
- Modify: `admin/views/appearance.php` (`$color_labels`, neue Fläche nach der Windrose, `updatePreview()`)
- Modify: `assets/css/admin.css` (Block am Dateiende)
- Test: `tests/test-appearance.php`, `tests/test-sparkline-render.php`

**Interfaces:**
- Consumes: `NAWS_Colors::SPARKLINE_KEYS`/Gruppe `sparkline` (Task 4), `.naws-sl-dark` (Task 6), `render()`/`markup()` (Task 5), `$naws_pane_class` (Task 8).
- Produces: `NAWS_Sparkline::sample( string $kind ): string` (`line`, `bars`, `band`; feste Beispielkurve, 200 × 40); `NAWS_Sparkline::preview_set(): array` (`line`, `bars`, `band` ⇒ HTML: Stationsdaten, sonst Beispiel); Vorschau-Container `#naws-preview-sparkline`; Vorschau-Gruppe `data-preview="sparkline"`.

- [ ] **Step 1: Tests** — in `tests/test-appearance.php` im Check `acht Reiter, Basis zuerst` den Namen in `neun Reiter, Basis zuerst` ändern und `'sparkline'` ans Ende der erwarteten Liste setzen. Dazu, vor dem Abschluss:

```php
check( 'die Sparkline-Flaeche liest ihre Gruppe', str_contains( $view, "\$groups['sparkline']['keys']" ), true );
check( 'Vorschau-Container',                   str_contains( $view, '<div id="naws-preview-sparkline">' ), true );
check( 'das Skript kennt die Gruppe',          str_contains( $view, "if (group === 'sparkline') {" ), true );
```

In `tests/test-sparkline-render.php` vor dem Abschluss:

```php
echo "\nVorschau im Erscheinungsbild\n" . str_repeat( '-', 74 ) . "\n";
$pv = NAWS_Sparkline::preview_set();
check( 'drei Kurven',                         array_keys( $pv ), [ 'line', 'bars', 'band' ] );
check( 'ohne Stationsdaten: Beispiel-Linie mit Tief und Hoch', [ str_contains( $pv['line'], 'naws-sl--line' ), substr_count( $pv['line'], 'class="naws-sl-mm"' ) ], [ true, 2 ] );
check( 'Beispiel-Regen: sechs Balken',        substr_count( $pv['bars'], '<rect class="naws-sl-bar"' ), 6 );
check( 'Beispiel-Band',                       str_contains( $pv['band'], 'class="naws-sl-band"' ), true );
check( 'feste Groesse 200 x 40',              str_contains( $pv['line'], 'style="--naws-sl-w:200px;--naws-sl-h:40px;"' ), true );
check( 'Vorlesetext der Beispiele',           str_contains( $pv['bars'], 'aria-label="Sample sparkline in the chosen colours"' ), true );
check( 'das Beispiel ist fest',               NAWS_Sparkline::sample( 'line' ), NAWS_Sparkline::sample( 'line' ) );
```

- [ ] **Step 2:** `php tests/test-appearance.php` und `php tests/test-sparkline-render.php` → FAIL.

- [ ] **Step 3: Der Reiter** — in `NAWS_Colors::appearance_tabs()` nach der `'windrose'`-Zeile:

```php
            'sparkline'  => __( 'Sparkline', 'xtx-integration-for-netatmo' ),
```

- [ ] **Step 4: Beispiel und Vorschau** — in `class-naws-sparkline.php` nach `widget_set()`:

```php

    /**
     * A fixed curve for the Appearance preview, for a station that has
     * nothing to show yet: a mild day for the line, a shower for the bars,
     * twelve days for the band. Never random, so the preview does not
     * change between two looks.
     */
    public static function sample( string $kind ): string {
        $v = [ 14.2, 13.1, 12.4, 12.0, 13.5, 16.8, 19.4, 21.0, 21.6, 20.2, 18.1, 16.0 ];
        $a = self::normalise_atts( [
            'param'  => $kind === 'bars' ? 'Rain' : ( $kind === 'band' ? 'temp_avg' : 'Temperature' ),
            'days'   => $kind === 'band' ? '12' : '',
            'band'   => 'minmax',
            'show'   => 'minmax',
            'width'  => '200',
            'height' => '40',
        ] );
        $tips = array_fill( 0, count( $v ), '' );
        $aria = naws_label( 'sl_preview_aria' );

        if ( $kind === 'bars' ) {
            $d = [ 'kind' => 'bars', 'sums' => [ 0.0, 0.0, 0.4, 1.2, 0.2, 0.0, 0.0, 0.0, 0.8, 2.1, 1.0, 0.0 ], 'tips' => $tips, 'aria' => $aria, 'value' => '' ];
        } else {
            $pts = [];
            foreach ( $v as $i => $x ) {
                $pts[] = $kind === 'band'
                    ? [ $i * 86400, $x, $x - 3.5 - ( $i % 3 ), $x + 4.0 + ( $i % 2 ) ]
                    : [ $i * 3600, $x ];
            }
            $d = [ 'kind' => 'line', 'pts' => $pts, 'band' => $kind === 'band', 'tips' => $tips, 'aria' => $aria, 'value' => '' ];
        }
        return self::markup( $a, $d );
    }

    /**
     * The three curves on the Sparkline tab: the station's own where it has
     * them, the sample otherwise. Bars count only with rain in them — a dry
     * day would show the baseline and none of the colour being chosen.
     */
    public static function preview_set(): array {
        $line = self::render( [ 'param' => 'Temperature', 'show' => 'minmax', 'width' => '200', 'height' => '40' ] );
        $bars = self::render( [ 'param' => 'Rain', 'width' => '200', 'height' => '40' ] );
        $band = self::render( [ 'param' => 'temp_avg', 'days' => '30', 'band' => 'minmax', 'width' => '200', 'height' => '40' ] );
        return [
            'line' => $line !== '' ? $line : self::sample( 'line' ),
            'bars' => str_contains( $bars, 'naws-sl-bar' ) ? $bars : self::sample( 'bars' ),
            'band' => $band !== '' ? $band : self::sample( 'band' ),
        ];
    }
```

- [ ] **Step 5: Farbnamen** — in `admin/views/appearance.php` im Array `$color_labels` nach der Zeile `'windrose_switch' => __( 'Period switcher (active button)', 'xtx-integration-for-netatmo' ),`:

```php
    // [naws_sparkline] (since 2.1.0)
    'sparkline_line'      => __( 'Line', 'xtx-integration-for-netatmo' ),
    'sparkline_line_dark' => __( 'Line on dark ground', 'xtx-integration-for-netatmo' ),
    'sparkline_rain'      => __( 'Rain bars', 'xtx-integration-for-netatmo' ),
    'sparkline_rain_dark' => __( 'Rain bars on dark ground', 'xtx-integration-for-netatmo' ),
    'sparkline_band'      => __( 'Band (daily low to high)', 'xtx-integration-for-netatmo' ),
    'sparkline_dots'      => __( 'Low and high dots', 'xtx-integration-for-netatmo' ),
    'sparkline_tip_bg'    => __( 'Hover bubble – background', 'xtx-integration-for-netatmo' ),
    'sparkline_tip_text'  => __( 'Hover bubble – text', 'xtx-integration-for-netatmo' ),
```

- [ ] **Step 6: Die Fläche** — nach dem Ende der Windrosen-Fläche und vor

```php
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'xtx-integration-for-netatmo' ); ?></button>
        </p>
    </form>

    <!-- ============================================================
         Sidebar widget: forecast length + live preview.
```

einfügen:

```php
        <!-- ============================================================
             Tab 9: Sparkline (since 2.1.0)
             ============================================================ -->
        <div class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class( 'sparkline' ) ); ?>" data-pane="sparkline">
            <p class="description"><?php esc_html_e( 'Colours for [naws_sparkline] and the curves in the sidebar widget. One line colour serves every quantity; the dark scheme of the widget uses the two colours "on dark ground". The area under a line is the line colour, lightly filled.', 'xtx-integration-for-netatmo' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
                    <table class="form-table naws-color-table">
                        <tbody>
                        <?php foreach ( $groups['sparkline']['keys'] as $key ) : ?>
                            <tr>
                                <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                                <td>
                                    <input type="text"
                                           id="naws-<?php echo esc_attr( $key ); ?>"
                                           name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                           value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                           class="naws-color-picker"
                                           data-preview="sparkline"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label"><?php esc_html_e( 'Live preview — sparkline', 'xtx-integration-for-netatmo' ); ?></div>
                    <?php $naws_pv_sl = NAWS_Sparkline::preview_set(); ?>
                    <div id="naws-preview-sparkline">
                        <?php foreach ( [ 'light', 'dark' ] as $naws_pv_ground ) : ?>
                        <div class="naws-pv-sl-ground<?php echo esc_attr( 'dark' === $naws_pv_ground ? ' naws-sl-dark' : '' ); ?>">
                            <?php foreach ( $naws_pv_sl as $naws_pv_html ) : ?>
                            <div class="naws-pv-sl-row"><?php echo $naws_pv_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        <span class="naws-sl-tip naws-pv-sl-tip"><?php echo esc_html( wp_date( 'D ' . get_option( 'time_format', 'H:i' ) ) . ' · ' . NAWS_Sparkline::number( 'Temperature', (float) NAWS_Helpers::format_value( 'Temperature', 18.2 ) ) . ' ' . NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span>
                    </div>
                </div>
            </div>
        </div>

```

- [ ] **Step 7: Live-Vorschau** — in `updatePreview()` nach dem Windrosen-Block, der mit

```js
                wr.find('.naws-pv-wr-' + suffix).css(suffix === 'grid' ? 'stroke' : 'fill', val);
            }
        }
```

endet, einfügen:

```js

        // ── Sparkline: the variable the key names, set on every curve, on
        // the sample bubble and on the curves in the widget preview. Only
        // that one variable is touched, so the dark half keeps reading its
        // "on dark ground" colours.
        if (group === 'sparkline') {
            var slVar = '--naws-sl-' + String(key).replace('sparkline_', '').replace(/_/g, '-');
            document.querySelectorAll('#naws-preview-sparkline .naws-sl, #naws-preview-sparkline .naws-sl-tip, .naws-wgt .naws-sl').forEach(function (el) {
                el.style.setProperty(slVar, val);
            });
        }
```

- [ ] **Step 8: Stylesheet** — am Ende von `assets/css/admin.css`:

```css

/* Sparkline tab preview (since 2.1.0): the three curves on a light and a
   dark ground, the dark one reading the "on dark ground" colours through
   .naws-sl-dark (frontend.css), and the bubble standing still below. */
#naws-preview-sparkline{max-width:260px;}
.naws-pv-sl-ground{padding:10px 14px;border:1px solid #cbd4e0;border-radius:8px;background:#fff;color:#1e293b;margin-bottom:10px;}
.naws-pv-sl-ground.naws-sl-dark{background:#1c2433;color:#e8edf5;border-color:#39465c;}
.naws-pv-sl-row{margin:6px 0;line-height:0;}
.naws-pv-sl-tip.naws-sl-tip{position:static;transform:none;margin:0;display:inline-block;}
```

- [ ] **Step 9:** `php tests/test-appearance.php`, `php tests/test-sparkline-render.php` → grün; `php -l`; PHPCS auf `includes/class-naws-colors.php`, `includes/class-naws-sparkline.php`, `admin/views/appearance.php`.

- [ ] **Step 10: Commit**

```bash
git add includes/class-naws-colors.php includes/class-naws-sparkline.php admin/views/appearance.php assets/css/admin.css tests/test-appearance.php tests/test-sparkline-render.php
git commit -m "Appearance: the Sparkline tab, with the station's curves on light and dark as preview"
```

---

### Task 10: Dokumentation — Shortcode-Seite, readme, README, CHANGELOG, Website

**Files:**
- Modify: `admin/views/shortcodes.php` (neue Karte nach der `[naws_windrose]`-Karte; Widget-Karte: Zeile und Beispiel)
- Modify: `readme.txt` (Zeile 29, nach Zeile 56, Zeile 59), `README.md` (Widget-Zeile, nach der `[naws_windrose]`-Zeile), `CHANGELOG.md` (neuer Abschnitt `[Unreleased]`), `docs/site/website.de.json`, `docs/site/website.en.json`

- [ ] **Step 1: Karte** — in `admin/views/shortcodes.php` nach dem schließenden `</div>` der `[naws_windrose]`-Karte (nach der Zeile mit `one period, the table visible, 420 px wide` folgen `        </div>` und `    </div>`; danach einfügen):

```php

    <div class="naws-sc-card">
        <h3><code>[naws_sparkline]</code></h3>
        <p><?php esc_html_e( 'A curve the size of a word: how a reading got to where it is, drawn next to the number in running text. The raw readings of the last hours, or a column of the daily summary with the band between daily low and high; rain as bars. Rendered on the server as SVG; hovering shows time and value. Colours on the Appearance page, tab Sparkline.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_sparkline param="Temperature"]</pre><button class="naws-copy-btn" data-copy='[naws_sparkline param="Temperature"]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>param</code></td><td><?php esc_html_e( 'Raw readings: Temperature, Humidity, Pressure, WindStrength, GustStrength, Rain, CO2, Noise. Daily summary: temp_avg, temp_min, temp_max, humidity_avg, pressure_avg, rain_sum, wind_avg, gust_max, co2_avg, noise_avg.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">Temperature</span></td></tr>
            <tr><td><code>hours</code></td><td><?php esc_html_e( 'Window of raw readings in hours, 1–168.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">24</span></td></tr>
            <tr><td><code>days</code></td><td><?php esc_html_e( 'Window in days from the daily summary, 2–366. For the daily columns; 30 if left out. Not allowed with a raw reading.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'empty', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>module</code></td><td><?php esc_html_e( 'outdoor, indoor, wind, rain, in-<name> or a MAC address. Left out, the module that measures the reading. Ignored with days.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'from param', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>width</code>, <code>height</code></td><td><?php esc_html_e( 'Size in pixels, width 20–600 and height 10–200. Left out, the curve is 4.6 × 1.05 em and grows with the text.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">4.6 × 1.05 em</span></td></tr>
            <tr><td><code>show</code></td><td><?php esc_html_e( 'none, value (the latest value, or the rain total, after the curve) or minmax (dots on the lowest and the highest point).', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">none</span></td></tr>
            <tr><td><code>type</code></td><td><?php esc_html_e( 'line or bars. Bars are for rain only; left out, rain gets bars and everything else a line.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'automatic', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>band</code></td><td><?php esc_html_e( 'minmax: with days and temp_avg, the band between daily low and high under the line of daily means.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'empty', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_sparkline param="Temperature"]</code> &rarr; <?php esc_html_e( 'temperature of the last 24 hours, the size of a word', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_sparkline param="Pressure" hours="48" show="value"]</code> &rarr; <?php esc_html_e( 'pressure over two days, the latest value after it', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_sparkline param="Rain" width="120" height="24"]</code> &rarr; <?php esc_html_e( 'rain of the last day as bars, 120 × 24 pixels', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_sparkline param="temp_avg" days="30" band="minmax"]</code> &rarr; <?php esc_html_e( 'the last 30 days: daily means with the band from low to high', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
    </div>
```

In der Widget-Karte nach der `scheme`-Zeile der Tabelle:

```php
            <tr><td><code>sparklines</code></td><td><?php esc_html_e( 'Small curves of the last 24 hours for temperature, rain and wind: 1 shows them, 0 hides them.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo absint( ! empty( get_option( 'naws_settings', [] )['wgt_sparklines'] ) ); ?></span></td></tr>
```

und nach dem Beispiel `[naws_weather_widget scheme="dark"]`:

```php
            <div class="naws-inline-ex"><code>[naws_weather_widget sparklines="1"]</code> <?php esc_html_e( 'with small curves of the last 24 hours', 'xtx-integration-for-netatmo' ); ?></div>
```

- [ ] **Step 2: readme.txt** — Zeile 29:

```
* **16 Shortcodes** – Dashboard, current readings, infobar, single value, computed value, sparkline, history charts, heatmap, records, this day in earlier years, sun path, wind rose, forecast, table, widget, weather icon
```

Nach der `[naws_windrose]`-Zeile der Shortcode-Liste (Zeile 56):

```
* `[naws_sparkline]` – A curve the size of a word: the raw readings of the last hours or a column of the daily summary, rain as bars, the band between daily low and high, time and value on hover (`param`, `hours`, `days`, `module`, `width`, `height`, `show`, `type`, `band`)
```

Die Widget-Zeile (Zeile 59) wird:

```
* `[naws_weather_widget]` – Compact forecast widget for a sidebar (`days` 3 or 5, `width` 250–500, `scheme` light, dark or transparent, `sparklines` 0 or 1)
```

- [ ] **Step 3: README.md** — die Widget-Zeile der Tabelle wird:

```
| `[naws_weather_widget days="3\|5"]` | Compact sidebar widget: weather icon, outdoor temperature, rain and wind, plus a three- or five-day forecast, in a light, dark or transparent scheme, optionally with small curves of the last 24 hours |
```

und nach der `[naws_windrose]`-Zeile:

```
| `[naws_sparkline param="Temperature"]` | A curve the size of a word: the raw readings of the last hours or a column of the daily summary, rain as bars, the band between daily low and high; time and value on hover |
```

- [ ] **Step 4: CHANGELOG.md** — nach der Zeile `All notable changes to the XTX Netatmo plugin are documented here.` und ihrer Leerzeile, vor `## [2.0.2]`:

```
## [Unreleased]

### Added
- **`[naws_sparkline]`: a curve the size of a word.** How a reading got to where it is, drawn next to the number in running text — no axes, no labels, a dot on the latest value. From the raw readings of the last `hours` (1–168, 24 by default) of any of eight quantities, or from a column of the daily summary over `days` (2–366); rain as bars, the daily means of the temperature optionally over the band between daily low and high (`band="minmax"`). `show="value"` puts the latest value or the rain total after the curve, `show="minmax"` marks the lowest and highest point. Without `width`/`height` the curve is 4.6 × 1.05 em and grows with the text around it. Rendered on the server as SVG and complete without JavaScript; a small script adds a bubble with time and value on hover or tap. A screen reader hears the range and the latest value. The module follows from the quantity (pressure from the base station, rain from the gauge), and a station that has nothing to show renders nothing. Eight colours on a new Appearance tab, Sparkline — one line colour for every quantity, and a second one each for the line and the rain bars on a dark ground.
- **Sparklines in the sidebar widget.** A new switch under Appearance › Sidebar widget, and `sparklines="1"` on the shortcode, add 24-hour curves: the temperature under the figure in the head, rain as bars and wind as a line in their tiles. Off by default, so an existing widget looks as before. The dark scheme draws them in the "on dark ground" colours, the transparent one in the sidebar's own text colour.

### Changed
- **The Appearance tabs look like tabs.** They were a row of words with a line under the active one, and with nine of them they ran off the edge of a narrow window. They are WordPress's own tabs now — grey file cards, the active one joined to the page — and wrap into a second row when there is no room. After "Save Settings" the tab that was open stays open; it used to jump back to Base Theme. The tabs on the REST API page look the same.
- `get_daily_summaries()` also returns `humidity_avg`, `wind_avg`, `co2_avg` and `noise_avg` for day-by-day requests.
```

- [ ] **Step 5: Website** — in `docs/site/website.de.json` den `satz` des Vorhabens `sparkline` ersetzen durch

```
"[naws_sparkline] zeichnet den Verlauf einer Messgröße als winzige Kurve, so hoch wie eine Textzeile: neben der Zahl im Fließtext und auf Wunsch in den Kacheln des Widgets. Aus den Rohmessungen der letzten Stunden oder aus der Tagestabelle mit der Spanne zwischen Tief und Hoch, Regen als Balken. Serverseitiges SVG, beim Überfahren Uhrzeit und Wert, Farben aus dem Erscheinungsbild."
```

und in `docs/site/website.en.json` durch

```
"[naws_sparkline] draws the course of a reading as a tiny curve, as tall as a line of text: next to the number in running text and, if you like, in the widget's tiles. From the raw readings of the last hours or from the daily table with the span between low and high, rain as bars. Server-side SVG, time and value on hover, colours from the appearance settings."
```

In beiden `"aktualisiert"` auf `"2026-09-26"`; `"ab"` bleibt `null`. Prüfen:

```bash
for f in docs/site/website.de.json docs/site/website.en.json; do php -r 'json_decode(file_get_contents($argv[1]), false, 512, JSON_THROW_ON_ERROR); echo "ok\n";' "$f"; done
grep -c -i "infobar" docs/site/website.de.json docs/site/website.en.json   # der Sparkline-Satz nennt sie nicht mehr
```

- [ ] **Step 6:** `php -l admin/views/shortcodes.php`; PHPCS darauf; `php tests/test-readme-sections.php`; ganze Suite.

- [ ] **Step 7: Commit**

```bash
git add admin/views/shortcodes.php readme.txt README.md CHANGELOG.md docs/site/website.de.json docs/site/website.en.json
git commit -m "Document [naws_sparkline] and the widget's curves: the Shortcodes page, both readmes, the changelog, the site"
```

---

### Task 11: Sprache — Kataloge neu bauen, Deutsch und Norwegisch eintragen

**Files:**
- Modify (erzeugt): `languages/xtx-integration-for-netatmo.pot`, `docs/i18n/catalog/xtx-integration-for-netatmo-{de_DE,nb_NO}.po`, `languages/xtx-integration-for-netatmo-{de_DE,nb_NO}.mo`
- Test: `tests/test-mo-files.php`, `tests/test-makepot-comments.php` (vorhanden); Zählung der leeren `msgstr`

**Interfaces:**
- Consumes: `docs/i18n/catalog/makepot.php`, `merge_po.php`, `fill_po.php`, `make_mo.php`.

- [ ] **Step 1: Die deutsche Liste** — im Scratchpad als `sparkline-de_DE.php` (nicht im Repo; die `.po` ist das Ergebnis):

```php
<?php
$s = "sparkline\x04";
return [
    $s . 'Temperature' => 'Temperatur',
    $s . 'Humidity'    => 'Luftfeuchte',
    $s . 'Pressure'    => 'Luftdruck',
    $s . 'CO2'         => 'CO2',
    $s . 'Noise'       => 'Lärm',
    $s . 'Wind'        => 'Wind',
    $s . 'Gusts'       => 'Böen',
    $s . 'Rain'        => 'Regen',
    'Daily mean temperature' => 'Tagesmittel der Temperatur',
    'Daily low'              => 'Tagestief',
    'Daily high'             => 'Tageshoch',
    'Daily mean humidity'    => 'Tagesmittel der Luftfeuchte',
    'Daily mean pressure'    => 'Tagesmittel des Luftdrucks',
    'Rain per day'           => 'Regen je Tag',
    'Daily mean wind'        => 'Tagesmittel des Windes',
    'Strongest gust per day' => 'Stärkste Böe je Tag',
    'Daily mean CO2'         => 'Tagesmittel CO2',
    'Daily mean noise'       => 'Tagesmittel Lärm',
    '%1$s, last %2$s: from %3$s to %4$s, latest %5$s' => '%1$s, letzte %2$s: von %3$s bis %4$s, zuletzt %5$s',
    '%1$s, last %2$s: %3$s in total' => '%1$s, letzte %2$s: insgesamt %3$s',
    '%1$s, last %2$s: daily means from %3$s to %4$s, range %5$s to %6$s' => '%1$s, letzte %2$s: Tagesmittel von %3$s bis %4$s, Spanne %5$s bis %6$s',
    'Sample sparkline in the chosen colours' => 'Beispiel-Sparkline in den gewählten Farben',
    '%d hour' => [ '%d Stunde', '%d Stunden' ],
    'Sparklines' => 'Sparklines',
    'Show the last 24 hours as small curves' => 'Die letzten 24 Stunden als kleine Kurven zeigen',
    'Temperature under the figure in the head, rain as bars and wind as a line in their tiles. Colours on the Sparkline tab above. sparklines="1" or sparklines="0" on the shortcode overrides this for one placement.' => 'Temperatur unter der Zahl im Kopf, Regen als Balken und Wind als Linie in ihren Kacheln. Farben im Reiter „Sparkline“ oben. sparklines="1" oder sparklines="0" am Shortcode überschreibt das für eine Stelle.',
    'Sparkline' => 'Sparkline',
    'Appearance sections' => 'Bereiche des Erscheinungsbilds',
    'Line' => 'Linie',
    'Line on dark ground' => 'Linie auf dunklem Grund',
    'Rain bars' => 'Regenbalken',
    'Rain bars on dark ground' => 'Regenbalken auf dunklem Grund',
    'Band (daily low to high)' => 'Band (Tagestief bis Tageshoch)',
    'Low and high dots' => 'Tief- und Hochpunkte',
    'Hover bubble – background' => 'Sprechblase – Hintergrund',
    'Hover bubble – text' => 'Sprechblase – Text',
    'Colours for [naws_sparkline] and the curves in the sidebar widget. One line colour serves every quantity; the dark scheme of the widget uses the two colours "on dark ground". The area under a line is the line colour, lightly filled.' => 'Farben für [naws_sparkline] und die Kurven im Seitenleisten-Widget. Eine Linienfarbe gilt für alle Größen; das dunkle Schema des Widgets nimmt die beiden Farben „auf dunklem Grund“. Die Fläche unter einer Linie ist die Linienfarbe, leicht gefüllt.',
    'Live preview — sparkline' => 'Live-Vorschau — Sparkline',
    'A curve the size of a word: how a reading got to where it is, drawn next to the number in running text. The raw readings of the last hours, or a column of the daily summary with the band between daily low and high; rain as bars. Rendered on the server as SVG; hovering shows time and value. Colours on the Appearance page, tab Sparkline.' => 'Eine Kurve in Wortgröße: wie ein Messwert dahin kam, wo er steht, neben der Zahl im Fließtext. Die Rohwerte der letzten Stunden oder eine Spalte der Tagestabelle mit dem Band zwischen Tagestief und Tageshoch; Regen als Balken. Auf dem Server als SVG gezeichnet; beim Überfahren erscheinen Uhrzeit und Wert. Farben im Erscheinungsbild, Reiter Sparkline.',
    'Raw readings: Temperature, Humidity, Pressure, WindStrength, GustStrength, Rain, CO2, Noise. Daily summary: temp_avg, temp_min, temp_max, humidity_avg, pressure_avg, rain_sum, wind_avg, gust_max, co2_avg, noise_avg.' => 'Rohwerte: Temperature, Humidity, Pressure, WindStrength, GustStrength, Rain, CO2, Noise. Tagestabelle: temp_avg, temp_min, temp_max, humidity_avg, pressure_avg, rain_sum, wind_avg, gust_max, co2_avg, noise_avg.',
    'Window of raw readings in hours, 1–168.' => 'Zeitfenster der Rohwerte in Stunden, 1–168.',
    'Window in days from the daily summary, 2–366. For the daily columns; 30 if left out. Not allowed with a raw reading.' => 'Zeitfenster in Tagen aus der Tagestabelle, 2–366. Für die Tagesspalten; ohne Angabe 30. Mit einem Rohwert nicht erlaubt.',
    'outdoor, indoor, wind, rain, in-<name> or a MAC address. Left out, the module that measures the reading. Ignored with days.' => 'outdoor, indoor, wind, rain, in-<name> oder eine MAC-Adresse. Ohne Angabe das Modul, das den Wert misst. Mit days ohne Wirkung.',
    'from param' => 'aus param',
    'Size in pixels, width 20–600 and height 10–200. Left out, the curve is 4.6 × 1.05 em and grows with the text.' => 'Größe in Pixeln, Breite 20–600 und Höhe 10–200. Ohne Angabe ist die Kurve 4,6 × 1,05 em groß und wächst mit der Schrift.',
    'none, value (the latest value, or the rain total, after the curve) or minmax (dots on the lowest and the highest point).' => 'none, value (der letzte Wert oder die Regensumme hinter der Kurve) oder minmax (Punkte am tiefsten und am höchsten Wert).',
    'line or bars. Bars are for rain only; left out, rain gets bars and everything else a line.' => 'line oder bars. Balken gibt es nur für Regen; ohne Angabe bekommt Regen Balken und alles andere eine Linie.',
    'automatic' => 'automatisch',
    'minmax: with days and temp_avg, the band between daily low and high under the line of daily means.' => 'minmax: mit days und temp_avg das Band zwischen Tagestief und Tageshoch unter der Linie der Tagesmittel.',
    'temperature of the last 24 hours, the size of a word' => 'Temperatur der letzten 24 Stunden, so groß wie ein Wort',
    'pressure over two days, the latest value after it' => 'Luftdruck über zwei Tage, dahinter der letzte Wert',
    'rain of the last day as bars, 120 × 24 pixels' => 'Regen des letzten Tages als Balken, 120 × 24 Pixel',
    'the last 30 days: daily means with the band from low to high' => 'die letzten 30 Tage: Tagesmittel mit dem Band von Tief bis Hoch',
    'Small curves of the last 24 hours for temperature, rain and wind: 1 shows them, 0 hides them.' => 'Kleine Kurven der letzten 24 Stunden für Temperatur, Regen und Wind: 1 zeigt sie, 0 blendet sie aus.',
    'with small curves of the last 24 hours' => 'mit kleinen Kurven der letzten 24 Stunden',
];
```

- [ ] **Step 2: Die norwegische Liste** — `sparkline-nb_NO.php`, gleiche Schlüssel:

```php
<?php
$s = "sparkline\x04";
return [
    $s . 'Temperature' => 'Temperatur',
    $s . 'Humidity'    => 'Luftfuktighet',
    $s . 'Pressure'    => 'Lufttrykk',
    $s . 'CO2'         => 'CO2',
    $s . 'Noise'       => 'Støy',
    $s . 'Wind'        => 'Vind',
    $s . 'Gusts'       => 'Vindkast',
    $s . 'Rain'        => 'Nedbør',
    'Daily mean temperature' => 'Døgnmiddeltemperatur',
    'Daily low'              => 'Døgnets laveste',
    'Daily high'             => 'Døgnets høyeste',
    'Daily mean humidity'    => 'Døgnmiddel for luftfuktighet',
    'Daily mean pressure'    => 'Døgnmiddel for lufttrykk',
    'Rain per day'           => 'Nedbør per døgn',
    'Daily mean wind'        => 'Døgnmiddel for vind',
    'Strongest gust per day' => 'Sterkeste vindkast per døgn',
    'Daily mean CO2'         => 'Døgnmiddel CO2',
    'Daily mean noise'       => 'Døgnmiddel støy',
    '%1$s, last %2$s: from %3$s to %4$s, latest %5$s' => '%1$s, siste %2$s: fra %3$s til %4$s, sist %5$s',
    '%1$s, last %2$s: %3$s in total' => '%1$s, siste %2$s: totalt %3$s',
    '%1$s, last %2$s: daily means from %3$s to %4$s, range %5$s to %6$s' => '%1$s, siste %2$s: døgnmiddel fra %3$s til %4$s, spenn %5$s til %6$s',
    'Sample sparkline in the chosen colours' => 'Eksempel på sparkline i de valgte fargene',
    '%d hour' => [ '%d time', '%d timer' ],
    'Sparklines' => 'Sparklines',
    'Show the last 24 hours as small curves' => 'Vis de siste 24 timene som små kurver',
    'Temperature under the figure in the head, rain as bars and wind as a line in their tiles. Colours on the Sparkline tab above. sparklines="1" or sparklines="0" on the shortcode overrides this for one placement.' => 'Temperatur under tallet øverst, nedbør som søyler og vind som linje i sine fliser. Farger i fanen «Sparkline» ovenfor. sparklines="1" eller sparklines="0" på kortkoden overstyrer dette for én plassering.',
    'Sparkline' => 'Sparkline',
    'Appearance sections' => 'Deler av utseendet',
    'Line' => 'Linje',
    'Line on dark ground' => 'Linje på mørk bakgrunn',
    'Rain bars' => 'Nedbørsøyler',
    'Rain bars on dark ground' => 'Nedbørsøyler på mørk bakgrunn',
    'Band (daily low to high)' => 'Bånd (døgnets laveste til høyeste)',
    'Low and high dots' => 'Punkter for laveste og høyeste',
    'Hover bubble – background' => 'Boble – bakgrunn',
    'Hover bubble – text' => 'Boble – tekst',
    'Colours for [naws_sparkline] and the curves in the sidebar widget. One line colour serves every quantity; the dark scheme of the widget uses the two colours "on dark ground". The area under a line is the line colour, lightly filled.' => 'Farger for [naws_sparkline] og kurvene i sidefelt-widgeten. Én linjefarge gjelder for alle måleverdier; widgetens mørke fargeskjema bruker de to fargene «på mørk bakgrunn». Flaten under en linje er linjefargen, svakt fylt.',
    'Live preview — sparkline' => 'Forhåndsvisning — sparkline',
    'A curve the size of a word: how a reading got to where it is, drawn next to the number in running text. The raw readings of the last hours, or a column of the daily summary with the band between daily low and high; rain as bars. Rendered on the server as SVG; hovering shows time and value. Colours on the Appearance page, tab Sparkline.' => 'En kurve på størrelse med et ord: hvordan en måling kom dit den er, tegnet ved siden av tallet i løpende tekst. Råmålingene fra de siste timene, eller en kolonne i døgnoversikten med båndet mellom døgnets laveste og høyeste; nedbør som søyler. Tegnet på serveren som SVG; når musen er over, vises tid og verdi. Farger på siden Utseende, fanen Sparkline.',
    'Raw readings: Temperature, Humidity, Pressure, WindStrength, GustStrength, Rain, CO2, Noise. Daily summary: temp_avg, temp_min, temp_max, humidity_avg, pressure_avg, rain_sum, wind_avg, gust_max, co2_avg, noise_avg.' => 'Råmålinger: Temperature, Humidity, Pressure, WindStrength, GustStrength, Rain, CO2, Noise. Døgnoversikt: temp_avg, temp_min, temp_max, humidity_avg, pressure_avg, rain_sum, wind_avg, gust_max, co2_avg, noise_avg.',
    'Window of raw readings in hours, 1–168.' => 'Tidsvindu for råmålinger i timer, 1–168.',
    'Window in days from the daily summary, 2–366. For the daily columns; 30 if left out. Not allowed with a raw reading.' => 'Tidsvindu i dager fra døgnoversikten, 2–366. For døgnkolonnene; 30 hvis utelatt. Ikke tillatt med en råmåling.',
    'outdoor, indoor, wind, rain, in-<name> or a MAC address. Left out, the module that measures the reading. Ignored with days.' => 'outdoor, indoor, wind, rain, in-<name> eller en MAC-adresse. Utelatt: modulen som måler verdien. Ignoreres med days.',
    'from param' => 'fra param',
    'Size in pixels, width 20–600 and height 10–200. Left out, the curve is 4.6 × 1.05 em and grows with the text.' => 'Størrelse i piksler, bredde 20–600 og høyde 10–200. Utelatt er kurven 4,6 × 1,05 em og vokser med teksten.',
    'none, value (the latest value, or the rain total, after the curve) or minmax (dots on the lowest and the highest point).' => 'none, value (siste verdi eller nedbørssummen etter kurven) eller minmax (punkter på laveste og høyeste verdi).',
    'line or bars. Bars are for rain only; left out, rain gets bars and everything else a line.' => 'line eller bars. Søyler finnes bare for nedbør; utelatt får nedbør søyler og alt annet linje.',
    'automatic' => 'automatisk',
    'minmax: with days and temp_avg, the band between daily low and high under the line of daily means.' => 'minmax: med days og temp_avg, båndet mellom døgnets laveste og høyeste under linjen for døgnmiddel.',
    'temperature of the last 24 hours, the size of a word' => 'temperatur de siste 24 timene, på størrelse med et ord',
    'pressure over two days, the latest value after it' => 'lufttrykk over to dager, med siste verdi etter',
    'rain of the last day as bars, 120 × 24 pixels' => 'nedbør det siste døgnet som søyler, 120 × 24 piksler',
    'the last 30 days: daily means with the band from low to high' => 'de siste 30 dagene: døgnmiddel med båndet fra laveste til høyeste',
    'Small curves of the last 24 hours for temperature, rain and wind: 1 shows them, 0 hides them.' => 'Små kurver for de siste 24 timene for temperatur, nedbør og vind: 1 viser dem, 0 skjuler dem.',
    'with small curves of the last 24 hours' => 'med små kurver for de siste 24 timene',
];
```

- [ ] **Step 3: Bauen** — vom Plugin-Root, `$SCRATCH` = Session-Scratchpad:

```bash
php docs/i18n/catalog/makepot.php
php docs/i18n/catalog/merge_po.php de_DE
php docs/i18n/catalog/merge_po.php nb_NO
php docs/i18n/catalog/fill_po.php de_DE "$SCRATCH/sparkline-de_DE.php"
php docs/i18n/catalog/fill_po.php nb_NO "$SCRATCH/sparkline-nb_NO.php"
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po languages/xtx-integration-for-netatmo-de_DE.mo
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po languages/xtx-integration-for-netatmo-nb_NO.mo
```

`fill_po.php` darf keinen Schlüssel als „nicht in der .po" melden. Meldet es einen, weicht die msgid um ein Zeichen vom Quelltext ab — dann die Liste korrigieren, nicht die `.po`. Strings, die es schon gab (`Line`, `Sparkline`, `automatic` …), sind nach `merge_po.php` vielleicht schon gefüllt und bleiben unangetastet.

- [ ] **Step 4: Prüfen**

```bash
grep -c '^msgstr ""$' docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po   # 1 (nur der Kopf)
grep -c '^msgstr ""$' docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po   # 1
grep -c '^msgstr\[[01]\] ""$' docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po  # 0
grep -c '^msgstr\[[01]\] ""$' docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po  # 0
php tests/test-mo-files.php
php tests/test-makepot-comments.php
```

Steht ein `msgstr ""` außerhalb des Kopfes, ist ein neuer String in keiner Liste: in der `.po` suchen (`grep -B3 '^msgstr ""$'`), übersetzen, in beide Listen aufnehmen, Step 3 ab `fill_po.php` wiederholen.

- [ ] **Step 5: Commit**

```bash
git add docs/i18n/catalog/*.po languages/
git commit -m "i18n: the sparkline, its tab and the widget switch in German and Norwegian"
```

---

### Task 12: Abnahme auf dev, Plugin Check, Merge

**Files:** keine Änderung am Code, außer Befunde aus dieser Abnahme (jeder Befund: Test, Fix, Commit auf dem Zweig).

- [ ] **Step 1: Gesamtlauf** — Suite ohne Ausgabe, `vendor/bin/phpcs --report=full` auf alle geänderten PHP-Dateien ohne Befund, `node --check assets/js/sparkline-boot.js`, `git status` sauber.

- [ ] **Step 2: Nach dev** — die geänderten und neuen Dateien als ZIP nach dev (Weg aus dem Gedächtnis „Live-Deployment per Patch": `novamira/create-upload-link` + `curl`, dann `novamira/execute-php` mit Prüfsummen; vorher Sicherung `wp-content/naws-backup-vor-sparkline-<datum>` der überschriebenen Dateien; **nichts nach `novamira-sandbox/`**). Betroffen: `includes/class-naws-sparkline.php`, `class-naws-shortcodes.php`, `class-naws-colors.php`, `class-naws-database.php`, `class-naws-labels.php`, `class-naws-widget-data.php`, `class-naws-admin.php`, `templates/sparkline.php`, `templates/weather-widget.php`, `assets/js/sparkline-boot.js`, `assets/css/frontend.css`, `assets/css/admin.css`, `admin/views/appearance.php`, `admin/views/rest-api-docs.php`, `admin/views/shortcodes.php`, `languages/*`, `xtx-integration-for-netatmo.php`. Danach Opcache leeren (neue Klasse!) und `NAWS_Database::flush_caches()` per `execute-php`.

- [ ] **Step 3: Testseite** — auf dev per `execute-php` eine Seite `sparkline-test` anlegen (Seite 20 `homepage` bleibt unberührt) mit: einem Absatz Fließtext mit `[naws_sparkline param="Temperature"]`, `[naws_sparkline param="Pressure" hours="48" show="value"]`, `[naws_sparkline param="Rain" width="120" height="24"]`, `[naws_sparkline param="temp_avg" days="30" band="minmax"]`, `[naws_sparkline param="Humidity" show="minmax"]`, `[naws_sparkline param="rain_sum" days="30"]`, `[naws_sparkline param="Unsinn"]` (muss spurlos fehlen); darunter `[naws_weather_widget sparklines="1"]`, `[naws_weather_widget sparklines="1" scheme="dark"]` und `[naws_weather_widget sparklines="1" scheme="transparent"]` (letzteres in einem Block mit dunklem Hintergrund). Mit Playwright ansehen (1280 × 900 und 390 × 844):
  - Kurven sitzen auf der Textzeile, keine hat Höhe 0 (Themes setzen gern `svg { height:auto }` — die Regel `.naws-sl svg` muss gewinnen; per `browser_evaluate` `getBoundingClientRect().height` jeder `.naws-sl svg` > 0).
  - Endpunkte sind rund, nicht oval, auch im Widget.
  - Sprechblase beim Überfahren (Desktop) und Antippen (Handy-Kontext per `run_code_unsafe`), verschwindet beim Verlassen, Antippen daneben und Scrollen; **eine** `.naws-sl-tip` im DOM, egal wie viele Kurven (Review Focus 5).
  - Widget dunkel: helle Linie, heller Regen; transparent: Kurven in der Textfarbe.
  - `[naws_sparkline param="Unsinn"]` hinterlässt nichts, keine Konsolenfehler (`browser_console_messages`).
  - Screenshots ins Scratchpad.

- [ ] **Step 4: Erscheinungsbild** — im dev-Backend (Chrome-MCP über `novamira/create-admin-access-link`):
  - Reiter sehen aus wie WordPress-Reiter, brechen bei schmalem Fenster um (im `<iframe>` mit 700 px messen, das Browserfenster lässt sich nicht verkleinern).
  - Reiter „Sparkline" öffnen, Linienfarbe ändern: helle Hälfte der Vorschau folgt, dunkle bleibt bei „auf dunklem Grund"; „Linie auf dunklem Grund" ändern: nur die dunkle Hälfte folgt; Sprechblasen-Farben: das Beispiel folgt.
  - „Änderungen speichern" → Reiter „Sparkline" ist nach dem Neuladen offen, die Adresse trägt `&tab=sparkline`.
  - Widget-Haken setzen, speichern → Widget-Vorschau zeigt drei Kurven; Haken raus, speichern → keine.
  - REST-API-Seite: beide Reiterleisten im neuen Aussehen, Umschalten geht.
  - Farbe danach auf die Vorgabe zurücksetzen (nicht „Reset All").

- [ ] **Step 5: Plugin Check** — im dev-Backend Plugin Check auf „XTX Integration for Netatmo". Erwartet: keine Fehler in den neuen oder geänderten Dateien; vorbestehende Warnungen notieren, nicht in diesem Vorhaben beheben.

- [ ] **Step 6: Frank zeigen** — Link zur Testseite und Screenshots; Merge erst nach seinem OK.

- [ ] **Step 7: Merge** — bei grünem Stand und Franks OK:

```bash
git checkout main && git merge --no-ff sparkline -m "Merge branch 'sparkline': [naws_sparkline], the widget's curves and real Appearance tabs for 2.1.0" && git push origin main && git branch -d sparkline
```

Der Schnitt 2.1.0 (Version, readme-Changelog, SVN, GitHub-Release, Website `"ab": "2.1.0"`, „16 Shortcodes", Startseiten-Block, Live-Demos 109/183, GlotPress-Readme-Kette) ist ein eigener Schritt auf Franks Ansage.
