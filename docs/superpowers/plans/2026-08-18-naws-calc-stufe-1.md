# [naws_calc] Stufe 1 — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Der Shortcode `[naws_calc]` liefert die 14 Momentanwerte des Katalogs, und `NAWS_Astro::feels_like()` rechnet in drei Regimen statt nur nach Steadman.

**Architecture:** Zwei Schichten. `NAWS_Astro` bekommt die reine Thermodynamik (Windchill, Regime-Weiche, Empfindungsstufe) — pure statische Funktionen ohne Optionen und ohne Uhr, testbar ohne WordPress. `NAWS_Calc` hält den Katalog als reines Metadaten-Array und löst Schlüssel zu Werten auf; `sc_calc()` in `NAWS_Shortcodes` formatiert und verpackt. Formatierung und Einheiten laufen über die vorhandenen `NAWS_Helpers::format_value()` / `get_unit()`, damit die °C/°F-Einstellung ohne Sonderweg gilt.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, keine Composer-Abhängigkeiten im Plugin. Tests sind einfache PHP-Skripte ohne WordPress-Bootstrap. PHPCS/WPCS über `.phpcs.xml.dist`.

**Spec:** `docs/superpowers/specs/2026-08-18-berechnete-werte-shortcode-design.md`

## Global Constraints

- **Arbeitsverzeichnis ist `C:\Users\xyla1\.claude\Netatmo\`.** Dort wird entwickelt, committet und gepusht; es ist ein vollwertiger Klon von `https://github.com/Xyla1512/Netatmo.git` auf `main`. Kein Feature-Branch.
- **Kein Versions-Bump.** Header, `readme.txt` und `README.md` bleiben auf 1.9.6. Die nächste Versionsnummer wird erst vergeben, wenn weitere Features fertig sind.
- **Keine Schemaänderung.** `NAWS_DB_VERSION` bleibt `'1.4'`, keine Migration, keine neue Spalte.
- **Requires PHP 8.0, Requires at least WP 6.2** — keine Sprachfeatures ab 8.1 (kein `readonly`, keine `enum`, keine `never`-Rückgabe).
- **Drei Sprachen vollständig:** jeder neue Sprachschlüssel muss in `languages/de.php`, `languages/en.php` **und** `languages/no.php` stehen. Norwegisch wird nicht nachgereicht.
- **Ausgabe:** Shortcodes geben zurück, sie echoen nicht. Jede Ausgabe durch `esc_html()`, Attributwerte durch `esc_attr()`.
- **Zeitzone:** immer `naws_timezone()` aus `class-naws-helpers.php`, niemals eine fest verdrahtete Zone.
- **Qualitätsgate:** `php vendor/bin/phpcs --standard=.phpcs.xml.dist <dateien>` muss **0 Befunde** melden, `php -l` über jede geänderte Datei fehlerfrei sein.
- **PHP-Binärpfad auf dieser Maschine:** `/c/Users/xyla1/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php` — steht als `php` im PATH der Bash-Umgebung.
- **Testsuite ausführen:** `php tests/test-*.php` erfasst `tests/smoke-render-inline.php` **nicht** (kein `test-`-Präfix). Immer zusätzlich `php tests/smoke-render-inline.php` laufen lassen.
- **Auswertungsfalle:** Die Ergebniszeile einer Testdatei ist nicht die letzte Ausgabezeile — danach folgt eine Leerzeile. Auf `bestanden`/`fehlgeschlagen` grepen statt `tail -1`.

---

### Task 1: Drei-Regime-Modell in NAWS_Astro

Baut die Rechenkerne, auf denen `feels_like` und `bioclimate` später stehen. Nach dieser Aufgabe zeigt die bestehende Infobar bei Kaltwind andere Werte — teils wärmere, teils kältere. Das ist beabsichtigt (Spec §6.1).

**Files:**
- Modify: `includes/class-naws-astro.php:63-75` (Rumpf von `feels_like()`)
- Test: `tests/test-thermal.php` (neu)

**Interfaces:**
- Consumes: `NAWS_Astro::heat_index( float $t, float $rh ): float` (vorhanden, unverändert)
- Produces:
  - `NAWS_Astro::wind_chill( float $temp_c, float $wind_kmh ): float`
  - `NAWS_Astro::apparent_temperature( float $temp_c, float $humidity_pct, float $wind_kmh ): float`
  - `NAWS_Astro::feels_like( float $temp_c, float $humidity_pct, float $wind_kmh ): float`
  - `NAWS_Astro::thermal_sensation( float $felt_c ): string` — gibt einen Sprachschlüssel zurück, einen von: `sens_very_cold`, `sens_cold`, `sens_cool`, `sens_pleasantly_cool`, `sens_pleasant`, `sens_warm`, `sens_hot`, `sens_extremely_hot`

- [ ] **Step 1: Testdatei anlegen, die zuerst fehlschlägt**

Create `tests/test-thermal.php`:

```php
<?php
/**
 * Tests for the thermal arithmetic in NAWS_Astro.
 *
 * All functions under test are pure — no options, no clock, no database — so
 * they run without a WordPress bootstrap. What they guard:
 *
 *   wind_chill()        – NOAA 2001, metric. Must always come out below the
 *                         air temperature when it is cold and windy.
 *   feels_like()        – the three-regime switch: wind chill below 10 °C
 *                         with wind, the heat index in hot humid air,
 *                         Steadman between. Guards that each regime is
 *                         actually selected at its boundaries.
 *   thermal_sensation() – the band a felt temperature falls into. The bands
 *                         are the source's, not invented here.
 *
 *   php tests/test-thermal.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-astro.php';

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Vergleich mit Toleranz — schützt vor Bruch durch die letzte Nachkommastelle. */
function close( string $name, float $got, float $want, float $tol = 0.05 ): void {
    global $passed, $failed;
    if ( abs( $got - $want ) <= $tol ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %.3f (+-%.3f), ist %.3f\n", $name, $want, $tol, $got );
}

echo "\nNAWS_Astro::wind_chill()\n" . str_repeat( '-', 74 ) . "\n";

// T_wc = 13,12 + 0,6215*T - 11,37*v^0,16 + 0,3965*T*v^0,16   (NOAA 2001, metrisch)
close( '-5 C bei 20 km/h',   NAWS_Astro::wind_chill( -5.0, 20.0 ), -11.6 );
close( '0 C bei 30 km/h',    NAWS_Astro::wind_chill( 0.0, 30.0 ),   -6.5 );
close( '5 C bei 10 km/h',    NAWS_Astro::wind_chill( 5.0, 10.0 ),    2.7 );

// Eigenschaften, die immer gelten müssen — unabhängig von der Formel.
$a = NAWS_Astro::wind_chill( -5.0, 10.0 );
$b = NAWS_Astro::wind_chill( -5.0, 40.0 );
check( 'mehr Wind kuehlt staerker', $b < $a, true );
check( 'liegt unter der Lufttemperatur', NAWS_Astro::wind_chill( -5.0, 20.0 ) < -5.0, true );

echo "\nNAWS_Astro::feels_like() — Regime-Weiche\n" . str_repeat( '-', 74 ) . "\n";

// Kalt und windig -> Windchill
check( '-5 C, 80 %, 20 km/h -> Windchill',
    NAWS_Astro::feels_like( -5.0, 80.0, 20.0 ),
    NAWS_Astro::wind_chill( -5.0, 20.0 ) );

// Heiss und feucht -> Hitzeindex
check( '30 C, 60 %, 5 km/h -> Hitzeindex',
    NAWS_Astro::feels_like( 30.0, 60.0, 5.0 ),
    NAWS_Astro::heat_index( 30.0, 60.0 ) );

// Dazwischen -> Steadman
check( '15 C, 50 %, 10 km/h -> Steadman',
    NAWS_Astro::feels_like( 15.0, 50.0, 10.0 ),
    NAWS_Astro::apparent_temperature( 15.0, 50.0, 10.0 ) );

// Grenzfaelle: die Bedingungen sind < 10 und > 5 bzw. >= 27 und > 40.
check( 'genau 10 C ist kein Windchill',
    NAWS_Astro::feels_like( 10.0, 50.0, 20.0 ),
    NAWS_Astro::apparent_temperature( 10.0, 50.0, 20.0 ) );
check( 'genau 5 km/h ist kein Windchill',
    NAWS_Astro::feels_like( 5.0, 50.0, 5.0 ),
    NAWS_Astro::apparent_temperature( 5.0, 50.0, 5.0 ) );
check( 'genau 27 C bei 41 % ist Hitzeindex',
    NAWS_Astro::feels_like( 27.0, 41.0, 2.0 ),
    NAWS_Astro::heat_index( 27.0, 41.0 ) );
check( 'genau 40 % ist kein Hitzeindex',
    NAWS_Astro::feels_like( 27.0, 40.0, 2.0 ),
    NAWS_Astro::apparent_temperature( 27.0, 40.0, 2.0 ) );

echo "\nNAWS_Astro::thermal_sensation()\n" . str_repeat( '-', 74 ) . "\n";

check( '-11 -> sehr kalt',        NAWS_Astro::thermal_sensation( -11.0 ), 'sens_very_cold' );
check( '-10 -> kalt (Grenze)',    NAWS_Astro::thermal_sensation( -10.0 ), 'sens_cold' );
check( '-0.1 -> kalt',            NAWS_Astro::thermal_sensation( -0.1 ),  'sens_cold' );
check( '0 -> kuehl (Grenze)',     NAWS_Astro::thermal_sensation( 0.0 ),   'sens_cool' );
check( '9.9 -> kuehl',            NAWS_Astro::thermal_sensation( 9.9 ),   'sens_cool' );
check( '10 -> angenehm kuehl',    NAWS_Astro::thermal_sensation( 10.0 ),  'sens_pleasantly_cool' );
check( '20 -> angenehm',          NAWS_Astro::thermal_sensation( 20.0 ),  'sens_pleasant' );
check( '24.9 -> angenehm',        NAWS_Astro::thermal_sensation( 24.9 ),  'sens_pleasant' );
check( '25 -> warm',              NAWS_Astro::thermal_sensation( 25.0 ),  'sens_warm' );
check( '32 -> heiss',             NAWS_Astro::thermal_sensation( 32.0 ),  'sens_hot' );
check( '39.9 -> heiss',           NAWS_Astro::thermal_sensation( 39.9 ),  'sens_hot' );
check( '40 -> extrem heiss',      NAWS_Astro::thermal_sensation( 40.0 ),  'sens_extremely_hot' );
check( '55 -> extrem heiss',      NAWS_Astro::thermal_sensation( 55.0 ),  'sens_extremely_hot' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und Fehlschlag bestätigen**

Run: `php tests/test-thermal.php`

Expected: **Fatal error**, `Call to undefined method NAWS_Astro::wind_chill()`. Das ist der gewünschte Ausgangszustand — die Datei bricht ab, bevor sie zählt.

- [ ] **Step 3: Die vier Funktionen schreiben**

In `includes/class-naws-astro.php` den Rumpf von `feels_like()` (Zeilen 63–75) durch die folgenden vier Methoden ersetzen. Der bisherige Steadman-Körper wandert unverändert nach `apparent_temperature()` — er verschwindet nicht, er bekommt nur den ehrlichen Namen.

```php
    /**
     * Wind chill (NOAA 2001, metric form).
     *
     * Valid below roughly 10 °C and above roughly 5 km/h; outside that range
     * the regression drifts, which is why feels_like() gates it.
     *
     * @param float $temp_c   Air temperature in °C.
     * @param float $wind_kmh Wind speed in km/h.
     * @return float          Wind chill temperature in °C.
     */
    public static function wind_chill( float $temp_c, float $wind_kmh ): float {
        $v = pow( max( 0.0, $wind_kmh ), 0.16 );
        return round( 13.12 + 0.6215 * $temp_c - 11.37 * $v + 0.3965 * $temp_c * $v, 1 );
    }

    /**
     * Apparent temperature (Steadman / BOM, °C).
     *
     * This used to be the whole of feels_like(). It stays available under its
     * own name because it is the right model for the middle of the range —
     * it just never belonged in freezing wind.
     *
     * @see Steadman, R. G. (1984). "A Universal Scale of Apparent Temperature"
     */
    public static function apparent_temperature( float $temp_c, float $humidity_pct, float $wind_kmh ): float {
        // Water vapour pressure (hPa) via Magnus formula
        $e = ( $humidity_pct / 100.0 ) * 6.105 * exp( ( 17.27 * $temp_c ) / ( 237.7 + $temp_c ) );

        // Wind speed in m/s (Netatmo delivers km/h)
        $ws = $wind_kmh / 3.6;

        $at = $temp_c + 0.33 * $e - 0.70 * $ws - 4.00;

        return round( $at, 1 );
    }

    /**
     * Felt temperature, switched by weather regime.
     *
     * Three models, because no single formula covers the range: wind chill
     * carries cold windy air, the heat index carries hot humid air, and
     * Steadman carries everything between. Which of them reads coldest
     * varies with temperature and wind speed; the point is that each
     * regime is now evaluated by the model built for it.
     *
     * @param float $temp_c       Air temperature in °C.
     * @param float $humidity_pct Relative humidity in % (0–100).
     * @param float $wind_kmh     Wind speed in km/h.
     * @return float              Felt temperature in °C.
     */
    public static function feels_like( float $temp_c, float $humidity_pct, float $wind_kmh ): float {
        if ( $temp_c < 10.0 && $wind_kmh > 5.0 ) {
            return self::wind_chill( $temp_c, $wind_kmh );
        }
        if ( $temp_c >= 27.0 && $humidity_pct > 40.0 ) {
            return self::heat_index( $temp_c, $humidity_pct );
        }
        return self::apparent_temperature( $temp_c, $humidity_pct, $wind_kmh );
    }

    /**
     * Thermal sensation band for a felt temperature.
     *
     * Returns a language key, not a finished string — the caller translates.
     * The bands are taken from the source model, not chosen here.
     *
     * @param float $felt_c Felt temperature in °C.
     * @return string       One of the sens_* language keys.
     */
    public static function thermal_sensation( float $felt_c ): string {
        if ( $felt_c < -10.0 ) return 'sens_very_cold';
        if ( $felt_c <   0.0 ) return 'sens_cold';
        if ( $felt_c <  10.0 ) return 'sens_cool';
        if ( $felt_c <  20.0 ) return 'sens_pleasantly_cool';
        if ( $felt_c <  25.0 ) return 'sens_pleasant';
        if ( $felt_c <  32.0 ) return 'sens_warm';
        if ( $felt_c <  40.0 ) return 'sens_hot';
        return 'sens_extremely_hot';
    }
```

- [ ] **Step 4: Test laufen lassen und Erfolg bestätigen**

Run: `php tests/test-thermal.php | grep -E "bestanden|FAIL"`

Expected: `25 bestanden, 0 fehlgeschlagen`, keine `FAIL`-Zeile. (5 Windchill, 7 Regime-Weiche, 13 Empfindungsstufen.)

- [ ] **Step 5: Sicherstellen, dass nichts anderes gebrochen ist**

Run: `for t in tests/test-*.php; do echo "--- $t"; php "$t" | grep -E "bestanden|fehlgeschlagen|Szenarien"; done; php tests/smoke-render-inline.php | tail -2`

Expected: alle bisherigen Testdateien weiterhin grün. `templates/infobar.php` ruft `feels_like()` mit derselben Signatur — es darf kein Fatal auftreten.

- [ ] **Step 6: Qualitätsgate**

Run: `php -l includes/class-naws-astro.php tests/test-thermal.php && php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-astro.php`

Expected: keine Syntaxfehler, PHPCS ohne Befund.

- [ ] **Step 7: Commit**

```bash
git add includes/class-naws-astro.php tests/test-thermal.php
git commit -m "Felt temperature switches by regime instead of one formula

Steadman alone covered the whole range, including regimes it was never
built for.
wind_chill() (NOAA 2001, metric) now carries the cold end, the existing
heat index carries the hot humid end, and the old Steadman body keeps
the middle under the honest name apparent_temperature().

thermal_sensation() returns the band as a language key, which is what
the bioclimate catalogue entry will render.

The infobar shows different numbers in cold wind from now on. That is
the fix, not a regression."
```

---

### Task 2: Katalog und Sprachschlüssel

Legt `NAWS_Calc` an — zunächst **nur** die Metadaten, keine Wertauflösung. Der Test bewacht, dass Katalog und Sprachdateien nicht auseinanderlaufen.

**Files:**
- Create: `includes/class-naws-calc.php`
- Modify: `xtx-integration-for-netatmo.php:51` (Ladeliste)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`
- Test: `tests/test-calc-catalogue.php` (neu)

**Interfaces:**
- Consumes: nichts aus Task 1 (der Katalog nennt Funktionen nur als Zeichenketten)
- Produces:
  - `NAWS_Calc::catalogue(): array` — Schlüssel → `[ 'kind' => string, 'param' => ?string, 'decimals' => int, 'label' => string ]`
    - `kind` ist einer von `instant`, `dayclass`, `sum`, `index`. In Stufe 1 kommt nur `instant` vor.
    - `param` ist der `NAWS_Helpers`-Parametername für Einheit und Umrechnung (`Temperature`, `Humidity`, …) oder `null` bei Textwerten.
    - `label` ist der Sprachschlüssel der Bezeichnung.
  - `NAWS_Calc::has( string $key ): bool`

- [ ] **Step 1: Den Katalogtest schreiben**

Create `tests/test-calc-catalogue.php`:

```php
<?php
/**
 * Guards the [naws_calc] catalogue against drift.
 *
 * The catalogue is a plain metadata array, so it can be checked without a
 * WordPress bootstrap. What this guards:
 *
 *   – every entry declares kind, decimals and a label key
 *   – every label key exists in ALL THREE language files
 *
 * The second point is the reason this file exists. With this many entries,
 * "forgot to translate it into Norwegian" is not a risk, it is a certainty.
 *
 *   php tests/test-calc-catalogue.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-calc.php';

$passed = 0;
$failed = 0;

function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$catalogue = NAWS_Calc::catalogue();
$kinds     = [ 'instant', 'dayclass', 'sum', 'index' ];

echo "\nKatalog-Struktur\n" . str_repeat( '-', 74 ) . "\n";

check( 'Katalog ist nicht leer', count( $catalogue ) > 0, true );
check( 'Stufe 1 liefert 14 Eintraege', count( $catalogue ), 14 );

foreach ( $catalogue as $key => $entry ) {
    check( "$key hat eine gueltige Art",   in_array( $entry['kind'] ?? '', $kinds, true ), true );
    check( "$key hat Nachkommastellen",    is_int( $entry['decimals'] ?? null ),          true );
    check( "$key hat einen Sprachkey",     ! empty( $entry['label'] ),                    true );
    check( "$key: param ist String/null",  ( ! isset( $entry['param'] ) || $entry['param'] === null || is_string( $entry['param'] ) ), true );
    check( "has('$key') ist wahr",         NAWS_Calc::has( $key ),                        true );
}

check( 'has() weist Unbekanntes ab', NAWS_Calc::has( 'gibt_es_nicht' ), false );

echo "\nSprachschluessel in allen drei Dateien\n" . str_repeat( '-', 74 ) . "\n";

$sens_keys = [
    'sens_very_cold', 'sens_cold', 'sens_cool', 'sens_pleasantly_cool',
    'sens_pleasant', 'sens_warm', 'sens_hot', 'sens_extremely_hot',
];

foreach ( [ 'de', 'en', 'no' ] as $lang ) {
    $strings = include __DIR__ . '/../languages/' . $lang . '.php';
    check( "$lang.php liefert ein Array", is_array( $strings ), true );

    foreach ( $catalogue as $key => $entry ) {
        check( "$lang: {$entry['label']}", isset( $strings[ $entry['label'] ] ) && $strings[ $entry['label'] ] !== '', true );
    }
    foreach ( $sens_keys as $sk ) {
        check( "$lang: $sk", isset( $strings[ $sk ] ) && $strings[ $sk ] !== '', true );
    }
}

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und Fehlschlag bestätigen**

Run: `php tests/test-calc-catalogue.php`

Expected: **Fatal error**, `Failed opening required .../class-naws-calc.php`.

- [ ] **Step 3: Die Katalogklasse anlegen**

Create `includes/class-naws-calc.php`:

```php
<?php
/**
 * Catalogue and dispatch for [naws_calc].
 *
 * Holds every computed value the shortcode can render, as plain metadata:
 * what kind it is, which NAWS_Helpers parameter supplies its unit and
 * conversion, how many decimals it carries, and which language key names it.
 *
 * catalogue() deliberately touches nothing — no options, no database, no
 * clock — so tests/test-calc-catalogue.php can read it without WordPress.
 * Everything that needs WordPress lives in the resolver methods.
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Calc {

    /**
     * Every value [naws_calc] knows.
     *
     * kind     – instant | dayclass | sum | index; decides which attributes apply
     * param    – NAWS_Helpers parameter name for unit and unit conversion,
     *            or null for values that are text or carry their own unit
     * decimals – default decimal places; -1 means "leave as produced"
     * label    – language key of the human-readable name
     *
     * @return array<string, array{kind:string, param:?string, decimals:int, label:string}>
     */
    public static function catalogue(): array {
        return [
            // ── thermal, from the current reading ──────────────────────
            'dewpoint'          => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_dewpoint' ],
            'feels_like'        => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_feels_like' ],
            'heat_index'        => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_heat_index' ],
            'wet_bulb'          => [ 'kind' => 'instant', 'param' => 'Temperature', 'decimals' => 1,  'label' => 'calc_wet_bulb' ],
            'bioclimate'        => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_bioclimate' ],

            // ── derived from a single reading ──────────────────────────
            'wind_compass'      => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_wind_compass' ],
            'co2_level'         => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_co2_level' ],

            // ── astronomy, from the station coordinates ────────────────
            'sunrise'           => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_sunrise' ],
            'sunset'            => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_sunset' ],
            'daylength'         => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_daylength' ],
            'moon_phase'        => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_moon_phase' ],
            'moon_illumination' => [ 'kind' => 'instant', 'param' => 'Humidity',    'decimals' => 0,  'label' => 'calc_moon_illumination' ],
            'next_supermoon'    => [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_next_supermoon' ],
            'next_lunar_eclipse'=> [ 'kind' => 'instant', 'param' => null,          'decimals' => 0,  'label' => 'calc_next_lunar_eclipse' ],
        ];
    }

    /**
     * Is this a key the catalogue knows?
     */
    public static function has( string $key ): bool {
        return isset( self::catalogue()[ $key ] );
    }
}
```

**Zur `param`-Wahl bei `moon_illumination`:** `Humidity` ist der einzige vorhandene Parameter, dessen Einheit `%` ist und der auf Ganzzahl rundet — genau das Verhalten, das der Beleuchtungsgrad braucht. Das ist Absicht und spart einen Sonderfall in `NAWS_Helpers`.

- [ ] **Step 4: Klasse in die Ladeliste eintragen**

In `xtx-integration-for-netatmo.php` direkt nach der Zeile mit `class-naws-astro.php` einfügen:

```php
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-calc.php' );
```

Die Reihenfolge ist wichtig: `NAWS_Calc` ruft später `NAWS_Astro` und `NAWS_Helpers`, beide sind vorher geladen.

- [ ] **Step 5: Sprachschlüssel in allen drei Dateien ergänzen**

In `languages/de.php` hinter dem Block der Mondphasen (`moon_waning_crescent`) einfügen:

```php
    // [naws_calc] – Bezeichnungen der berechneten Werte
    'calc_dewpoint'           => 'Taupunkt',
    'calc_feels_like'         => 'Gefühlte Temperatur',
    'calc_heat_index'         => 'Hitzeindex',
    'calc_wet_bulb'           => 'Feuchtkugeltemperatur',
    'calc_bioclimate'         => 'Bioklimatisches Empfinden',
    'calc_wind_compass'       => 'Windrichtung',
    'calc_co2_level'          => 'CO₂-Bewertung',
    'calc_sunrise'            => 'Sonnenaufgang',
    'calc_sunset'             => 'Sonnenuntergang',
    'calc_daylength'          => 'Tageslänge',
    'calc_moon_phase'         => 'Mondphase',
    'calc_moon_illumination'  => 'Beleuchtungsgrad des Mondes',
    'calc_next_supermoon'     => 'Nächster Supermond',
    'calc_next_lunar_eclipse' => 'Nächste Mondfinsternis',

    // [naws_calc] – Empfindungsstufen zur gefühlten Temperatur
    'sens_very_cold'          => 'sehr kalt',
    'sens_cold'               => 'kalt',
    'sens_cool'               => 'kühl',
    'sens_pleasantly_cool'    => 'angenehm kühl',
    'sens_pleasant'           => 'angenehm',
    'sens_warm'               => 'warm',
    'sens_hot'                => 'heiß',
    'sens_extremely_hot'      => 'extrem heiß',
```

In `languages/en.php` an derselben Stelle:

```php
    // [naws_calc] – names of the computed values
    'calc_dewpoint'           => 'Dew point',
    'calc_feels_like'         => 'Feels like',
    'calc_heat_index'         => 'Heat index',
    'calc_wet_bulb'           => 'Wet-bulb temperature',
    'calc_bioclimate'         => 'Thermal sensation',
    'calc_wind_compass'       => 'Wind direction',
    'calc_co2_level'          => 'CO₂ rating',
    'calc_sunrise'            => 'Sunrise',
    'calc_sunset'             => 'Sunset',
    'calc_daylength'          => 'Length of day',
    'calc_moon_phase'         => 'Moon phase',
    'calc_moon_illumination'  => 'Moon illumination',
    'calc_next_supermoon'     => 'Next supermoon',
    'calc_next_lunar_eclipse' => 'Next lunar eclipse',

    // [naws_calc] – thermal sensation bands
    'sens_very_cold'          => 'very cold',
    'sens_cold'               => 'cold',
    'sens_cool'               => 'cool',
    'sens_pleasantly_cool'    => 'pleasantly cool',
    'sens_pleasant'           => 'pleasant',
    'sens_warm'               => 'warm',
    'sens_hot'                => 'hot',
    'sens_extremely_hot'      => 'extremely hot',
```

In `languages/no.php` an derselben Stelle:

```php
    // [naws_calc] – navn på de beregnede verdiene
    'calc_dewpoint'           => 'Duggpunkt',
    'calc_feels_like'         => 'Følt temperatur',
    'calc_heat_index'         => 'Varmeindeks',
    'calc_wet_bulb'           => 'Våtkuletemperatur',
    'calc_bioclimate'         => 'Termisk opplevelse',
    'calc_wind_compass'       => 'Vindretning',
    'calc_co2_level'          => 'CO₂-vurdering',
    'calc_sunrise'            => 'Soloppgang',
    'calc_sunset'             => 'Solnedgang',
    'calc_daylength'          => 'Dagens lengde',
    'calc_moon_phase'         => 'Månefase',
    'calc_moon_illumination'  => 'Månens belysningsgrad',
    'calc_next_supermoon'     => 'Neste supermåne',
    'calc_next_lunar_eclipse' => 'Neste måneformørkelse',

    // [naws_calc] – trinn for termisk opplevelse
    'sens_very_cold'          => 'svært kaldt',
    'sens_cold'               => 'kaldt',
    'sens_cool'               => 'kjølig',
    'sens_pleasantly_cool'    => 'behagelig kjølig',
    'sens_pleasant'           => 'behagelig',
    'sens_warm'               => 'varmt',
    'sens_hot'                => 'hett',
    'sens_extremely_hot'      => 'ekstremt hett',
```

- [ ] **Step 6: Test laufen lassen und Erfolg bestätigen**

Run: `php tests/test-calc-catalogue.php | grep -E "bestanden|FAIL"`

Expected: `0 fehlgeschlagen`, keine `FAIL`-Zeile.

- [ ] **Step 7: Schlüsselzahl in allen drei Dateien vergleichen**

Run: `for f in de en no; do echo -n "$f: "; grep -c "^\s*'" languages/$f.php; done`

Expected: dreimal dieselbe Zahl (614 + 22 = **636**). Weichen sie ab, fehlt irgendwo eine Zeile.

- [ ] **Step 8: Qualitätsgate und Commit**

```bash
php -l includes/class-naws-calc.php languages/de.php languages/en.php languages/no.php tests/test-calc-catalogue.php
php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-calc.php languages/de.php languages/en.php languages/no.php
git add includes/class-naws-calc.php xtx-integration-for-netatmo.php languages/ tests/test-calc-catalogue.php
git commit -m "Catalogue for [naws_calc]: 14 instant values in three languages

Plain metadata only — kind, unit parameter, decimals, label key — so the
catalogue can be read and tested without WordPress. The test that ships
with it checks every label against all three language files, because at
this size a missing Norwegian string is a certainty, not a risk."
```

---

### Task 3: Der Shortcode mit den thermischen Werten

Registriert `[naws_calc]` und löst die fünf Werte auf, die aus der aktuellen Messung folgen. Nach dieser Aufgabe ist der Shortcode benutzbar.

**Files:**
- Modify: `includes/class-naws-calc.php` (Resolver ergänzen)
- Modify: `includes/class-naws-shortcodes.php:19-27` (Registrierung) und Ende der Klasse (`sc_calc()`)

**Interfaces:**
- Consumes:
  - `NAWS_Calc::catalogue()`, `NAWS_Calc::has()` aus Task 2
  - `NAWS_Astro::dew_point()`, `heat_index()`, `wet_bulb()`, `feels_like()`, `thermal_sensation()` aus Task 1
  - `NAWS_Database::get_modules( bool $active_only )`, `NAWS_Database::get_latest_readings( ?string $module_id )` (vorhanden)
  - `NAWS_Helpers::format_value( string $param, float $value )`, `NAWS_Helpers::get_unit( string $param )` (vorhanden)
- Produces:
  - `NAWS_Calc::raw( string $key, array $atts ): mixed` — Rohwert (float für Zahlen, string für Textwerte) oder `null`, wenn die Datenlage ihn nicht hergibt
  - `NAWS_Shortcodes::sc_calc( array $atts ): string`

- [ ] **Step 1: Auflösung der Messwerte in NAWS_Calc ergänzen**

In `includes/class-naws-calc.php` vor der schließenden Klammer der Klasse einfügen:

```php
    /**
     * Module type behind each alias, same mapping [naws_value] uses.
     */
    private const TYPE_MAP = [
        'outdoor' => 'NAModule1',
        'indoor'  => 'NAMain',
        'wind'    => 'NAModule2',
        'rain'    => 'NAModule3',
    ];

    /**
     * Resolve a module alias or MAC address to a module_id.
     *
     * @return string|null null when the station has no such module.
     */
    private static function module_id( string $alias ): ?string {
        $alias = strtolower( $alias );
        if ( ! isset( self::TYPE_MAP[ $alias ] ) ) {
            return $alias; // treated as a direct MAC address
        }
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            if ( $m['module_type'] === self::TYPE_MAP[ $alias ] ) {
                return $m['module_id'];
            }
        }
        return null;
    }

    /**
     * Latest value of one parameter on one module, in the unit Netatmo sends.
     *
     * Deliberately NOT run through NAWS_Helpers::format_value() — the maths
     * below needs °C and km/h, not whatever the display setting says. The
     * conversion happens once, at the very end, in the shortcode.
     */
    private static function reading( ?string $module_id, string $param ): ?float {
        if ( $module_id === null ) {
            return null;
        }
        foreach ( NAWS_Database::get_latest_readings( $module_id ) as $row ) {
            if ( $row['parameter'] === $param ) {
                return floatval( $row['value'] );
            }
        }
        return null;
    }

    /**
     * Wind speed in km/h from the wind module, 0.0 when there is none.
     *
     * A station without a wind gauge is normal, and the thermal formulas
     * behave sensibly at zero wind — so this returns 0.0 rather than null,
     * which would otherwise suppress the dew point on half the stations.
     */
    private static function wind_kmh(): float {
        return self::reading( self::module_id( 'wind' ), 'WindStrength' ) ?? 0.0;
    }

    /**
     * The raw value behind a catalogue key.
     *
     * Numbers come back in metric base units (°C, %); text values come back
     * as finished, translated strings. null means "the data does not support
     * this value" — the shortcode turns that into the fallback.
     *
     * @param string $key  Catalogue key.
     * @param array  $atts Shortcode attributes, already sanitised.
     * @return float|string|null
     */
    public static function raw( string $key, array $atts ) {
        if ( ! self::has( $key ) ) {
            NAWS_Logger::warning( 'calc', 'Unknown [naws_calc] value key: ' . $key );
            return null;
        }

        $module = self::module_id( (string) ( $atts['module'] ?? 'outdoor' ) );
        $temp   = self::reading( $module, 'Temperature' );
        $hum    = self::reading( $module, 'Humidity' );

        switch ( $key ) {
            case 'dewpoint':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::dew_point( $temp, $hum );

            case 'wet_bulb':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::wet_bulb( $temp, $hum );

            case 'heat_index':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::heat_index( $temp, $hum );

            case 'feels_like':
                return ( $temp === null || $hum === null ) ? null : NAWS_Astro::feels_like( $temp, $hum, self::wind_kmh() );

            case 'bioclimate':
                if ( $temp === null || $hum === null ) {
                    return null;
                }
                $felt = NAWS_Astro::feels_like( $temp, $hum, self::wind_kmh() );
                return naws__( NAWS_Astro::thermal_sensation( $felt ) );

            case 'wind_compass':
                $angle = self::reading( self::module_id( 'wind' ), 'WindAngle' );
                return $angle === null ? null : NAWS_Helpers::degrees_to_compass( $angle );

            case 'co2_level':
                $ppm = self::reading( self::module_id( 'indoor' ), 'CO2' );
                if ( $ppm === null ) {
                    return null;
                }
                // get_co2_level() returns [ level, color, label ] — only the
                // already-translated label belongs in running text.
                $level = NAWS_Helpers::get_co2_level( $ppm );
                return $level['label'];
        }

        return null;
    }
```

- [ ] **Step 2: Den Shortcode registrieren**

In `includes/class-naws-shortcodes.php` hinter der Zeile mit `add_shortcode( 'naws_value', … )` einfügen:

```php
        add_shortcode( 'naws_calc',      [ $this, 'sc_calc' ] );
```

- [ ] **Step 3: sc_calc() schreiben**

In `includes/class-naws-shortcodes.php` hinter `sc_value()` einfügen:

```php
    // ----------------------------------------------------------------
    // [naws_calc value="dewpoint" module="outdoor"]
    // A single computed value, for dropping into running text or a table.
    // ----------------------------------------------------------------
    public function sc_calc( $atts ) {
        $atts = shortcode_atts( [
            'value'    => '',
            'module'   => 'outdoor',
            'unit'     => '1',
            'decimals' => '-1',
            'fallback' => '--',
            'tag'      => 'span',
            'class'    => '',
        ], $atts, 'naws_calc' );

        $key      = sanitize_key( $atts['value'] );
        $fallback = esc_html( $atts['fallback'] );

        if ( $key === '' || ! NAWS_Calc::has( $key ) ) {
            NAWS_Logger::warning( 'calc', 'Unknown or missing value attribute on [naws_calc]: ' . $atts['value'] );
            return $fallback;
        }

        $entry = NAWS_Calc::catalogue()[ $key ];
        $raw   = NAWS_Calc::raw( $key, [ 'module' => sanitize_text_field( $atts['module'] ) ] );

        if ( $raw === null ) {
            return $fallback;
        }

        // Text values carry no unit and need no conversion.
        if ( is_string( $raw ) ) {
            $output = esc_html( $raw );
        } else {
            $param = $entry['param'];
            $value = $param ? NAWS_Helpers::format_value( $param, floatval( $raw ) ) : $raw;

            $dec = intval( $atts['decimals'] );
            if ( $dec < 0 ) {
                $dec = intval( $entry['decimals'] );
            }
            $value = round( floatval( $value ), $dec );

            $unit_str = ( $atts['unit'] !== '0' && $param ) ? ' ' . NAWS_Helpers::get_unit( $param ) : '';
            $output   = esc_html( $value . $unit_str );
        }

        $tag = sanitize_key( $atts['tag'] );
        if ( $tag === 'none' || $tag === '' ) {
            return $output;
        }
        $class = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        return "<{$tag}{$class}>{$output}</{$tag}>";
    }
```

- [ ] **Step 4: Syntax und Gate prüfen**

Run: `php -l includes/class-naws-calc.php includes/class-naws-shortcodes.php && php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-calc.php includes/class-naws-shortcodes.php`

Expected: keine Syntaxfehler, PHPCS ohne Befund.

- [ ] **Step 5: Auf der Testinstallation gegen echte Messwerte prüfen**

Die fünf geänderten/neuen Dateien nach `netatmo.frank-neumann.de` spiegeln, dann über `novamira/execute-php`:

```php
return [
  'taupunkt'   => do_shortcode( '[naws_calc value="dewpoint"]' ),
  'gefuehlt'   => do_shortcode( '[naws_calc value="feels_like"]' ),
  'bioklima'   => do_shortcode( '[naws_calc value="bioclimate"]' ),
  'windricht'  => do_shortcode( '[naws_calc value="wind_compass"]' ),
  'co2'        => do_shortcode( '[naws_calc value="co2_level"]' ),
  'unbekannt'  => do_shortcode( '[naws_calc value="quatsch"]' ),
  'ohne_wert'  => do_shortcode( '[naws_calc]' ),
  'roh'        => do_shortcode( '[naws_calc value="dewpoint" tag="none" unit="0"]' ),
];
```

Expected: `taupunkt` und `gefuehlt` mit `°C`, `bioklima` als deutscher Text, `unbekannt` und `ohne_wert` beide `--`, `roh` eine nackte Zahl ohne Tag und ohne Einheit.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-calc.php includes/class-naws-shortcodes.php
git commit -m "[naws_calc] renders the thermal values

Resolver keeps metric base units all the way through and converts once,
at the end, through NAWS_Helpers — so the C/F setting applies without a
second code path. Attribute names mirror [naws_value] deliberately.

An unknown value key renders the fallback and logs; it never dies
silently, because a typo in a shortcode is otherwise invisible."
```

---

### Task 4: Astronomische und abgeleitete Werte

Vervollständigt die 14 Momentanwerte um Sonne und Mond.

**Files:**
- Modify: `includes/class-naws-calc.php` (weitere `case`-Zweige in `raw()`)

**Interfaces:**
- Consumes: `NAWS_Astro::get_coords()`, `sun_times( float $lat, float $lng, ?int $ts )`, `moon_data( ?int $ts )`, `next_supermoon( ?int $ts )`, `next_lunar_eclipse( ?int $ts )` (alle vorhanden)
- Produces: keine neuen Signaturen; `NAWS_Calc::raw()` beantwortet sieben weitere Schlüssel

**Die tatsächlichen Rückgabeformen — nachgesehen, nicht geraten:**

| Funktion | Rückgabe |
|---|---|
| `get_coords()` | `[ 'lat' => float, 'lng' => float ]` **oder `null`** |
| `sun_times( $lat, $lng )` | `[ 'rise' => 'HH:MM', 'set' => 'HH:MM' ]` — **fertige Strings, keine Zeitstempel**; `'--:--'`, wenn die Sonne nicht auf- oder untergeht |
| `moon_data()` | `[ 'phase', 'phase_pct', 'name', 'emoji', 'next_full', 'next_full_ts' ]` — `name` ist **bereits übersetzt** |
| `next_supermoon()` | `[ 'date' => string, 'distance_km' => int ]` oder `null` — **kein Zeitstempel** |
| `next_lunar_eclipse()` | `[ 'date' => string, 'type' => string, 'ts' => int ]` oder `null` |

Zwei Konsequenzen daraus, die den naheliegenden Entwurf verhindern: `sun_times()` taugt **nicht** zur Berechnung der Tageslänge, weil es nur formatierte Uhrzeiten liefert. Und `moon_data()['name']` darf **nicht** noch einmal durch `naws__()` — es ist schon Klartext.

- [ ] **Step 1: Die sieben Zweige ergänzen**

In `NAWS_Calc::raw()` vor dem abschließenden `return null;` einfügen:

```php
            case 'sunrise':
            case 'sunset': {
                $coords = NAWS_Astro::get_coords();
                if ( ! $coords ) {
                    return null;
                }
                $sun  = NAWS_Astro::sun_times( $coords['lat'], $coords['lng'] );
                $time = ( $key === 'sunrise' ) ? ( $sun['rise'] ?? '' ) : ( $sun['set'] ?? '' );

                // '--:--' means the sun did not cross the horizon that day.
                // Above the Arctic Circle that is normal, not an error — and
                // this plugin ships a Norwegian translation.
                return ( $time === '' || $time === '--:--' ) ? null : $time;
            }

            case 'daylength': {
                $coords = NAWS_Astro::get_coords();
                if ( ! $coords ) {
                    return null;
                }
                // date_sun_info() is used directly here because sun_times()
                // hands back formatted strings, which cannot be subtracted.
                $info = date_sun_info( time(), $coords['lat'], $coords['lng'] );

                // Polar day and polar night come back as bool true/false
                // rather than a timestamp. Neither has a length to report.
                if ( ! is_int( $info['sunrise'] ?? null ) || ! is_int( $info['sunset'] ?? null ) ) {
                    return null;
                }
                $seconds = $info['sunset'] - $info['sunrise'];
                if ( $seconds <= 0 ) {
                    return null;
                }
                return sprintf( '%d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ) );
            }

            case 'moon_phase': {
                $moon = NAWS_Astro::moon_data();
                // Already translated by moon_data() — do not translate twice.
                return empty( $moon['name'] ) ? null : $moon['name'];
            }

            case 'moon_illumination': {
                $moon = NAWS_Astro::moon_data();
                return isset( $moon['phase_pct'] ) ? floatval( $moon['phase_pct'] ) : null;
            }

            case 'next_supermoon': {
                $ev = NAWS_Astro::next_supermoon();
                return empty( $ev['date'] ) ? null : $ev['date'];
            }

            case 'next_lunar_eclipse': {
                $ev = NAWS_Astro::next_lunar_eclipse();
                return empty( $ev['date'] ) ? null : $ev['date'];
            }
```

**Bekannte Einschränkung, hier bewusst nicht behoben:** `next_supermoon()` und `next_lunar_eclipse()` formatieren ihr Datum in `NAWS_Astro` fest als `d.m.Y – H:i` mit angehängtem `Uhr` — deutsch, unabhängig von der eingestellten Sprache. Das ist ein vorbestehender Mangel dieser Funktionen, den auch die Infobar heute schon zeigt. Ihn zu beheben hieße, die Rückgabe zu ändern und alle Aufrufer nachzuziehen; das gehört in eine eigene Aufgabe, nicht in diese. **Nicht nebenbei mitfixen.**

- [ ] **Step 2: Syntax und Gate**

Run: `php -l includes/class-naws-calc.php && php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-calc.php`

Expected: keine Syntaxfehler, PHPCS ohne Befund.

- [ ] **Step 3: Alle 14 Werte auf der Testinstallation durchprüfen**

Datei spiegeln, dann über `novamira/execute-php`:

```php
$out = [];
foreach ( array_keys( NAWS_Calc::catalogue() ) as $k ) {
    $out[ $k ] = do_shortcode( '[naws_calc value="' . $k . '" tag="none"]' );
}
return $out;
```

Expected: **kein einziger Eintrag ist `--`.** Ein `--` bedeutet entweder einen falschen Array-Schlüssel aus Schritt 1 oder eine fehlende Messgröße — beides muss geklärt werden, bevor es weitergeht.

- [ ] **Step 4: Commit**

```bash
git add includes/class-naws-calc.php
git commit -m "[naws_calc] covers sun and moon as well

Seven more keys, all on functions that already existed and were only
reachable from inside templates. Dates and times go through wp_date()
so they land in the site timezone rather than UTC."
```

---

### Task 5: Katalog-Referenztabelle im Backend

Gibt der Doku-Seite eine Tabelle, die für jeden Wert Schreibweise, Bedeutung **und den bei dieser Installation tatsächlich herauskommenden Wert** zeigt.

**Files:**
- Modify: `admin/views/shortcodes.php`
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php` (vier Überschriften)

**Interfaces:**
- Consumes: `NAWS_Calc::catalogue()`, `NAWS_Calc::raw()` aus Task 2 und 3
- Produces: keine

- [ ] **Step 1: Vier Sprachschlüssel ergänzen**

In allen drei Sprachdateien hinter dem `sens_*`-Block:

```php
    // de.php
    'sc_calc_title'    => 'Berechnete Werte',
    'sc_calc_intro'    => 'Ein Shortcode für alle berechneten Werte. Der Wert steckt im Attribut <code>value</code>, alles andere ist optional und wirkt wie bei <code>[naws_value]</code>.',
    'sc_calc_col_key'  => 'Schreibweise',
    'sc_calc_col_live' => 'Aktuell bei dir',
```

```php
    // en.php
    'sc_calc_title'    => 'Computed values',
    'sc_calc_intro'    => 'One shortcode for every computed value. The value goes in the <code>value</code> attribute; everything else is optional and behaves as it does in <code>[naws_value]</code>.',
    'sc_calc_col_key'  => 'Shortcode',
    'sc_calc_col_live' => 'Right now',
```

```php
    // no.php
    'sc_calc_title'    => 'Beregnede verdier',
    'sc_calc_intro'    => 'Én kortkode for alle beregnede verdier. Verdien står i attributtet <code>value</code>; resten er valgfritt og virker som i <code>[naws_value]</code>.',
    'sc_calc_col_key'  => 'Skrivemåte',
    'sc_calc_col_live' => 'Akkurat nå',
```

- [ ] **Step 2: Die Tabelle in die Doku-Seite einfügen**

In `admin/views/shortcodes.php` einen neuen Abschnitt nach dem `[naws_value]`-Abschnitt einfügen. `NAWS_Lang::r()` für den Einleitungstext, weil er `<code>`-Markup trägt und `naws_e()` es zerlegen würde:

```php
<div class="naws-admin-panel" style="margin-top:1rem;">
    <div class="naws-panel-header">
        <h2><?php naws_e( 'sc_calc_title' ); ?></h2>
    </div>
    <p style="padding:0 1.25rem;"><?php NAWS_Lang::r( 'sc_calc_intro' ); ?></p>
    <table class="wp-list-table widefat striped naws-list-table">
        <thead>
            <tr>
                <th><?php naws_e( 'sc_calc_col_key' ); ?></th>
                <th><?php naws_e( 'description' ); ?></th>
                <th><?php naws_e( 'sc_calc_col_live' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( NAWS_Calc::catalogue() as $calc_key => $calc_entry ) : ?>
            <tr>
                <td><code>[naws_calc value="<?php echo esc_attr( $calc_key ); ?>"]</code></td>
                <td><?php echo esc_html( naws__( $calc_entry['label'] ) ); ?></td>
                <td><?php echo do_shortcode( '[naws_calc value="' . esc_attr( $calc_key ) . '" tag="none"]' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

**Zur Vorschau-Spalte:** `do_shortcode()` gibt hier bereits escapten Text zurück (`sc_calc()` läuft durch `esc_html()`), deshalb kein zweites Escaping — das würde `°C` in Entities zerlegen. Falls PHPCS die Zeile beanstandet, `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sc_calc() escapes its own output` anhängen und **nicht** blind ein `esc_html()` darumlegen.

- [ ] **Step 3: Prüfen, ob der Sprachschlüssel `description` existiert**

Run: `grep -n "^\s*'description'" languages/de.php`

Falls die Zeile fehlt: einen eigenen Schlüssel `sc_calc_col_desc` mit „Bedeutung" / „Meaning" / „Betydning" in allen drei Dateien anlegen und im Markup statt `description` verwenden.

- [ ] **Step 4: Gate und Sichtprüfung**

Run: `php -l admin/views/shortcodes.php languages/de.php languages/en.php languages/no.php && php vendor/bin/phpcs --standard=.phpcs.xml.dist admin/views/shortcodes.php`

Danach die Datei spiegeln und die Seite **XTX Netatmo → Shortcodes** im Backend ansehen: 14 Zeilen, in der rechten Spalte überall ein echter Wert, nirgends `--`.

- [ ] **Step 5: Commit**

```bash
git add admin/views/shortcodes.php languages/
git commit -m "Shortcode reference lists the computed values with live output

The right-hand column runs each catalogue entry against this very
installation, so you can see whether a value produces anything here
before you paste it into a page."
```

---

### Task 6: Abschluss — Gesamtprüfung

**Files:** keine Änderung, nur Verifikation.

- [ ] **Step 1: Gesamte Testsuite**

Run: `for t in tests/test-*.php; do echo "--- $t"; php "$t" | grep -E "bestanden|fehlgeschlagen|Szenarien"; done; echo "--- smoke"; php tests/smoke-render-inline.php | tail -2`

Expected: alle Dateien grün, einschließlich der beiden neuen. `smoke-render-inline.php` nicht vergessen — das Glob erfasst sie nicht.

- [ ] **Step 2: PHPCS über alle geänderten Dateien**

Run: `php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-astro.php includes/class-naws-calc.php includes/class-naws-shortcodes.php admin/views/shortcodes.php languages/de.php languages/en.php languages/no.php xtx-integration-for-netatmo.php`

Expected: **0 Befunde.**

- [ ] **Step 3: Sprachdateien auf Gleichstand prüfen**

Run: `for f in de en no; do echo -n "$f: "; grep -c "^\s*'" languages/$f.php; done`

Expected: dreimal dieselbe Zahl.

- [ ] **Step 4: Nachweis, dass die Infobar noch rendert**

Auf der Testinstallation über `novamira/execute-php`:

```php
return [ 'infobar' => wp_strip_all_tags( do_shortcode( '[naws_infobar]' ) ) ];
```

Expected: gefüllte Ausgabe mit gefühlter Temperatur und Taupunkt. Der Wert der gefühlten Temperatur darf sich gegenüber vorher unterscheiden — er darf nicht fehlen.

- [ ] **Step 5: Push**

```bash
git push origin main
```

**Kein Tag, kein Release, kein Versions-Bump.** Stufe 1 sammelt sich für die nächste Feature-Version zusammen mit Stufe 2 und 3.

---

## Was dieser Plan nicht enthält

| Punkt | Wo es hingehört |
|---|---|
| Tagesklassen, Serien, Summen | Stufe 2 |
| Einstellungsfelder für Heiz- und Kühlgrenze | Stufe 2 — sie werden erst von Summen gebraucht |
| Zwischenspeicher | Stufe 2 — Momentanwerte lesen eine Zeile, das braucht keinen Transient |
| Attribute `station`, `period`, `year`, `mode`, `months`, `note` | Stufe 2 und 3; die Grammatik steht in der Spec, aber kein Wert in Stufe 1 wertet sie aus |
| SPI | Stufe 3 |
| `NAWS_Climate` | Stufe 2 — in Stufe 1 gibt es nichts über Tagesreihen zu rechnen |
