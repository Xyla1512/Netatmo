# Seitenleisten-Widget — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein 250-px-Seitenleisten-Widget als Shortcode `[naws_weather_widget]`, und die Umstellung aller Wetterdarstellungen im Plugin auf das mehrfarbige Icon-Set.

**Architecture:** Die Aufbereitung liegt in einer reinen Funktion `NAWS_Widget_Data::build()` ohne WordPress-Bezug, damit die Degradation ohne Framework prüfbar ist — derselbe Schnitt wie bei `NAWS_Weather_State::decide()`. Die Darstellung liegt in `templates/weather-widget.php` als literales Markup, weil die mehrfarbigen Icons den kses-Filter nicht überleben. Eine neue gemeinsame Abbildung `NAWS_Weather_State::wmo_to_state()` versorgt Widget, `[naws_forecast]` und den Dashboard-Vorhersagestreifen mit denselben Zustandsnamen.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, kein Build-Schritt, kein JavaScript-Framework. Tests sind reine PHP-Skripte in `tests/`, ausgeführt mit der lokalen PHP-8.4-CLI.

## Global Constraints

- **Arbeitsverzeichnis:** `C:\Users\xyla1\.claude\Netatmo\`. Nach Abschluss ins GitHub-Verzeichnis spiegeln, hash-verifiziert. `tests/`, `docs/`, `build-zip.ps1`, `.distignore` **nicht** spiegeln.
- **Zielversion:** `1.8.0` in Plugin-Header, `NAWS_VERSION`, `readme.txt` (Stable tag), `README.md`, `CHANGELOG.md`.
- **Namensräume im CSS:** `.naws-wgt-*` für das Widget. `.naws-wx` ist bereits die Vorhersagekarte, `.naws-wxi` sind die Icon-SVGs — beide **nicht** überschreiben.
- **Nullwerte:** `null` heißt „Modul fehlt oder meldet nicht", `0.0` heißt „gemessen, es ist null". Nie mit `empty()` oder losem `!` prüfen.
- **Icon-Ausgabe:** Icons aus `NAWS_Weather_Icons` gehen **nie** durch `wp_kses()`. Sie sind literales Template-Markup. Alles andere wird regulär escaped.
- **PHP-CLI:** `C:\Users\xyla1\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`
- **Sprachdateien:** Jeder neue Schlüssel muss in **allen drei** Dateien stehen — `de.php`, `en.php`, `no.php`.

---

### Task 1: `wmo_to_state()` — die gemeinsame Zuordnung

**Files:**
- Modify: `includes/class-naws-weather-state.php` (Methode ans Ende der Klasse, vor `nf()`)
- Test: `tests/test-wmo-mapping.php` (neu)

**Interfaces:**
- Consumes: die vorhandenen Konstanten `WMO_SNOW`, `WMO_RAIN`, `WMO_RAIN_HEAVY`, `WMO_SLEET`, `WMO_FOG` derselben Klasse
- Produces: `NAWS_Weather_State::wmo_to_state( int $wmo, bool $is_day = true ): string` — liefert einen Namen aus `STATES` oder `''`

- [ ] **Step 1: Testdatei schreiben**

```php
<?php
/**
 * Tests for NAWS_Weather_State::wmo_to_state().
 *
 * The mapping feeds three separate places (widget, [naws_forecast], the
 * dashboard forecast strip). If it drifts, they disagree with each other
 * and with the live icon, which is exactly what this file prevents.
 *
 *   php tests/test-wmo-mapping.php
 *
 * @package NAWS
 * @since   1.8.0
 */
define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../includes/class-naws-astro.php';
require_once __DIR__ . '/../includes/class-naws-weather-state.php';

$passed = 0;
$failed = 0;

function check( int $wmo, bool $is_day, string $expected, string $why ): void {
    global $passed, $failed;
    $actual = NAWS_Weather_State::wmo_to_state( $wmo, $is_day );
    if ( $actual === $expected ) {
        $passed++;
        printf( "  ok    WMO %-3d %-9s -> %-12s %s\n", $wmo, $is_day ? 'Tag' : 'Nacht', $actual === '' ? '(keins)' : $actual, $why );
        return;
    }
    $failed++;
    printf( "  FAIL  WMO %-3d %-9s -> erwartet '%s', bekommen '%s'\n", $wmo, $is_day ? 'Tag' : 'Nacht', $expected, $actual );
}

echo "\nNAWS_Weather_State::wmo_to_state()\n" . str_repeat( '-', 74 ) . "\n";

echo "\nJe ein Code aus jeder Gruppe\n";
check( 0,  true,  'clear_day',  'klar' );
check( 1,  true,  'fair',       'heiter' );
check( 2,  true,  'partly',     'teilweise bewoelkt' );
check( 3,  true,  'overcast',   'bedeckt' );
check( 45, true,  'fog',        'Nebel' );
check( 63, true,  'rain',       'Regen' );
check( 65, true,  'rain_heavy', 'Starkregen' );
check( 73, true,  'snow',       'Schnee' );
check( 68, true,  'sleet',      'Schneeregen' );
check( 95, true,  'thunder',    'Gewitter' );
check( 96, true,  'sleet',      'Gewitter mit Hagel' );

echo "\nZusammenfallende Codes\n";
foreach ( [ 51, 53, 55, 61, 80, 81 ] as $c ) { check( $c, true, 'rain', 'faellt auf Regen zusammen' ); }
foreach ( [ 71, 75, 77, 85, 86 ] as $c )     { check( $c, true, 'snow', 'faellt auf Schnee zusammen' ); }
foreach ( [ 66, 67, 69 ] as $c )             { check( $c, true, 'sleet', 'gefrierend/Schneeregen' ); }
check( 82, true, 'rain_heavy', 'starker Schauer' );
check( 48, true, 'fog',        'Raureifnebel' );

echo "\nTag und Nacht\n";
check( 0, false, 'clear_night', 'nur klar hat eine Nachtvariante' );
check( 3, false, 'overcast',    'bedeckt bleibt bedeckt' );
check( 63, false, 'rain',       'Regen bleibt Regen' );

echo "\nUnbekannt\n";
check( 4,   true, '', 'kein Icon statt eines falschen' );
check( 999, true, '', 'kein Icon statt eines falschen' );
check( -1,  true, '', 'kein Icon statt eines falschen' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

```
php tests/test-wmo-mapping.php
```

Erwartet: Fatal error, `Call to undefined method NAWS_Weather_State::wmo_to_state()`.

- [ ] **Step 3: Methode implementieren**

In `includes/class-naws-weather-state.php`, direkt vor der privaten `nf()`:

```php
    /**
     * Map a WMO weather code to one of the twelve icon states.
     *
     * Shared by the sidebar widget, [naws_forecast] and the dashboard
     * forecast strip so all three agree with each other — and, because it
     * reuses the same constants decide() does, with the live icon too.
     *
     * The twelve states are coarser than the WMO list: drizzle, showers and
     * steady rain all become 'rain', and the three snow intensities all
     * become 'snow'. That is the accepted cost of one shared icon set.
     *
     * @param  int  $wmo     WMO weather code.
     * @param  bool $is_day  Only affects code 0. Forecast days pass true.
     * @return string        A name from self::STATES, or '' if unknown.
     */
    public static function wmo_to_state( int $wmo, bool $is_day = true ): string {
        if ( $wmo === 0 ) return $is_day ? 'clear_day' : 'clear_night';
        if ( $wmo === 1 ) return 'fair';
        if ( $wmo === 2 ) return 'partly';
        if ( $wmo === 3 ) return 'overcast';
        if ( $wmo === 95 ) return 'thunder';
        if ( $wmo === 96 || $wmo === 99 ) return 'sleet';

        if ( in_array( $wmo, self::WMO_FOG, true ) )        return 'fog';
        if ( in_array( $wmo, self::WMO_SNOW, true ) )       return 'snow';
        if ( in_array( $wmo, self::WMO_SLEET, true ) )      return 'sleet';
        if ( in_array( $wmo, self::WMO_RAIN_HEAVY, true ) ) return 'rain_heavy';
        if ( in_array( $wmo, self::WMO_RAIN, true ) )       return 'rain';

        return '';
    }
```

- [ ] **Step 4: Test laufen lassen, grün erwarten**

```
php tests/test-wmo-mapping.php
```

Erwartet: `33 bestanden, 0 fehlgeschlagen`.

- [ ] **Step 5: Regression prüfen**

```
php tests/test-weather-state.php
```

Erwartet: weiterhin `36 bestanden, 0 fehlgeschlagen`.

---

### Task 2: `render_inline()` — kleine, ruhige Icons

**Files:**
- Modify: `includes/class-naws-weather-icons.php`
- Modify: `assets/css/frontend.css` (Modifikatorklasse)

**Interfaces:**
- Consumes: `templates/weather-icon.php`, `NAWS_Weather_State::STATES`
- Produces: `NAWS_Weather_Icons::render_inline( string $state, int $size ): string` — SVG ohne Wrapper, ohne Mindestgröße, ohne Animation

- [ ] **Step 1: Methode ergänzen**

In `includes/class-naws-weather-icons.php`, nach `render()`:

```php
    /**
     * Render a small, still icon for use inside a row of them.
     *
     * Differs from render() on three counts, all deliberate:
     *   - no minimum size: the 64 px floor exists for the standalone state
     *     icon, which carries the whole statement on its own. In a forecast
     *     column the icon sits beside a weekday and two temperatures.
     *   - no wrapper element: the caller owns the layout.
     *   - no animation: five to seven moving icons in a row pull attention
     *     away from the numbers they sit next to.
     *
     * @param  string $state  One of NAWS_Weather_State::STATES.
     * @param  int    $size   Edge length in px.
     * @return string         Icon markup, or '' for an unknown state.
     */
    public static function render_inline( string $state, int $size ): string {
        if ( ! in_array( $state, NAWS_Weather_State::STATES, true ) ) {
            return '';
        }

        self::queue_defs();

        $naws_wx_state = $state;
        $naws_wx_size  = max( 1, $size );
        $naws_wx_label = self::label( $state );
        $naws_wx_still = true;

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/weather-icon.php';
        return ob_get_clean();
    }
```

- [ ] **Step 2: Template um den stillen Modus erweitern**

In `templates/weather-icon.php` die Wrapper-Zeile ersetzen. Alt:

```php
<div class="naws-weather-icon" style="--naws-wx-size:<?php echo absint( $naws_wx_size ); ?>px">
```

Neu:

```php
<?php
// render_inline() sets $naws_wx_still: no wrapper div, no animation, and
// the size goes straight onto the svg. render() leaves it unset.
$naws_wx_still = ! empty( $naws_wx_still );
$naws_wx_cls   = 'naws-wxi' . ( $naws_wx_still ? ' naws-wxi--still' : '' );
if ( ! $naws_wx_still ) : ?>
<div class="naws-weather-icon" style="--naws-wx-size:<?php echo absint( $naws_wx_size ); ?>px">
<?php endif; ?>
```

Die schließende Zeile am Dateiende, alt:

```php
endswitch; ?>
</div>
```

Neu:

```php
endswitch; ?>
<?php if ( ! $naws_wx_still ) : ?></div><?php endif; ?>
```

Und **alle zwölf** `<svg class="naws-wxi" …>`-Öffnungen bekommen die Klasse und die Größe aus den Variablen:

```php
<svg class="<?php echo esc_attr( $naws_wx_cls ); ?>" viewBox="0 0 64 64" role="img"
     style="width:<?php echo absint( $naws_wx_size ); ?>px;height:<?php echo absint( $naws_wx_size ); ?>px"
     aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
```

- [ ] **Step 3: CSS ergänzen**

Ans Ende des Wetter-Icon-Blocks in `assets/css/frontend.css`:

```css
/* Icons inside a row of them: same drawing, held still. Elements that
   animate in from opacity 0 must be forced visible or they never appear. */
.naws-wxi--still * { animation: none !important; }
.naws-wxi--still .drop, .naws-wxi--still .drop-f, .naws-wxi--still .flake,
.naws-wxi--still .pellet, .naws-wxi--still .bolt, .naws-wxi--still .band,
.naws-wxi--still .gust, .naws-wxi--still .star { opacity: 1; }

/* The inline variant sizes itself; the wrapper rule must not win over it. */
.naws-wxi--still { display: block; }
```

**Zur Größe:** Die Inline-Variante trägt ihre Maße im `style`-Attribut, das gegen jede Klassenregel gewinnt. Die bestehende Regel `.naws-weather-icon .naws-wxi { width: var(--naws-wx-size, 96px) }` greift nur innerhalb des Wrappers, den `render_inline()` gar nicht erzeugt — sie stören sich also nicht.

- [ ] **Step 4: Rauchtest**

Datei `tests/smoke-render-inline.php`:

```php
<?php
define( 'ABSPATH', __DIR__ );
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function absint( $n ) { return abs( (int) $n ); }
function naws__( $k ) { return $k; }
function add_action( ...$a ) {}
function is_admin() { return false; }
require_once NAWS_PLUGIN_DIR . 'includes/class-naws-astro.php';
require_once NAWS_PLUGIN_DIR . 'includes/class-naws-weather-state.php';
require_once NAWS_PLUGIN_DIR . 'includes/class-naws-weather-icons.php';

$fail = 0;
foreach ( NAWS_Weather_State::STATES as $s ) {
    $in  = NAWS_Weather_Icons::render_inline( $s, 28 );
    $big = NAWS_Weather_Icons::render( $s, 96 );

    $checks = [
        'inline hat svg'            => str_contains( $in, '<svg' ),
        'inline ohne wrapper'       => ! str_contains( $in, 'naws-weather-icon' ),
        'inline still-Klasse'       => str_contains( $in, 'naws-wxi--still' ),
        'inline 28px'               => str_contains( $in, 'width:28px' ),
        'render behaelt wrapper'    => str_contains( $big, 'naws-weather-icon' ),
        'render ohne still-Klasse'  => ! str_contains( $big, 'naws-wxi--still' ),
    ];
    foreach ( $checks as $name => $ok ) {
        if ( ! $ok ) { $fail++; echo "  FAIL  {$s}: {$name}\n"; }
    }
}
echo $fail === 0 ? "render_inline(): alle 12 Zustaende ok\n" : "{$fail} Fehler\n";
exit( $fail > 0 ? 1 : 0 );
```

Run: `php tests/smoke-render-inline.php`
Erwartet: `render_inline(): alle 12 Zustaende ok`

- [ ] **Step 5: Bestehende Icon-Prüfungen wiederholen**

```
php tests/test-weather-state.php
php tests/test-wmo-mapping.php
```

Beide grün.

---

### Task 3: Vorhandene Vorlagen auf das neue Set umstellen

**Files:**
- Modify: `templates/forecast.php:81`
- Modify: `templates/live.php:296`
- Modify: `includes/class-naws-forecast.php` (nur Docblock über `get_weather_svg()`)

**Interfaces:**
- Consumes: `NAWS_Weather_State::wmo_to_state()` (Task 1), `NAWS_Weather_Icons::render_inline()` (Task 2)
- Produces: nichts Neues; ändert sichtbar die Ausgabe zweier bestehender Shortcodes

- [ ] **Step 1: `templates/forecast.php` umstellen**

Alt (Zeile 81):

```php
          <div class="naws-fc-svg"><?php echo wp_kses( NAWS_Forecast::get_weather_svg( $wmo['icon'] ), naws_svg_kses_args() ); ?></div>
```

Neu:

```php
          <div class="naws-fc-svg"><?php
            // Literal icon markup, never kses-filtered: the multi-colour set
            // uses defs/gradients/filters that naws_svg_kses_args() strips.
            // Forecast days are days, so is_day stays true.
            $fc_state = NAWS_Weather_State::wmo_to_state( (int) $day['weathercode'], true );
            if ( $fc_state !== '' ) {
                echo NAWS_Weather_Icons::render_inline( $fc_state, 44 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG, see templates/weather-icon.php
            }
          ?></div>
```

- [ ] **Step 2: `templates/live.php` umstellen**

Alt (Zeile 296):

```php
            <div class="naws-fcc-svg"><?php echo wp_kses( NAWS_Forecast::get_weather_svg( $fc_wmo['icon'] ), naws_svg_kses_args() ); ?></div>
```

Neu:

```php
            <div class="naws-fcc-svg"><?php
            $fcc_state = NAWS_Weather_State::wmo_to_state( (int) $fc_day['weathercode'], true );
            if ( $fcc_state !== '' ) {
                echo NAWS_Weather_Icons::render_inline( $fcc_state, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG, see templates/weather-icon.php
            }
            ?></div>
```

**Vorher prüfen:** Der Variablenname der Tageszeile in `live.php` heißt dort möglicherweise anders als `$fc_day`. Mit `grep -n "fc_wmo" templates/live.php` die umgebende Schleife lesen und den tatsächlichen Namen einsetzen.

- [ ] **Step 3: `get_weather_svg()` als abgelöst kennzeichnen**

Docblock in `includes/class-naws-forecast.php` über der Methode ersetzen:

```php
    /**
     * Flat weather SVGs by icon id.
     *
     * @deprecated 1.8.0 Superseded by NAWS_Weather_Icons::render_inline(),
     *             which serves every weather display in the plugin from one
     *             set. Kept because it is public static and may sit in a
     *             user's own theme snippet; nothing in the plugin calls it.
     */
```

- [ ] **Step 4: Syntax und Referenzen prüfen**

```
php -l templates/forecast.php
php -l templates/live.php
php -l includes/class-naws-forecast.php
grep -rn "get_weather_svg" --include="*.php" . | grep -v "^./docs" | grep -v "^./tests"
```

Erwartet: keine Syntaxfehler; der Grep liefert **nur noch** die Definition in `class-naws-forecast.php`, keine Aufrufe mehr.

- [ ] **Step 5: Commit**

```bash
git add includes/class-naws-weather-state.php includes/class-naws-weather-icons.php templates/weather-icon.php templates/forecast.php templates/live.php assets/css/frontend.css tests/
git commit -m "One weather icon set across the whole plugin"
```

---

### Task 4: `NAWS_Widget_Data::build()` — die Aufbereitung

**Files:**
- Create: `includes/class-naws-widget-data.php`
- Test: `tests/test-widget-data.php`

**Interfaces:**
- Consumes: `NAWS_Weather_State::wmo_to_state()` (Task 1)
- Produces: `NAWS_Widget_Data::build( array $station, array $forecast, int $days ): array`

Eingabeform — der Aufrufer formatiert vor, damit `build()` frei von WordPress bleibt:

```php
$station = [
    'temp' => [ 'value' => '8,4', 'unit' => '°C' ],   // oder null
    'rain' => [ 'value' => '0,4', 'unit' => 'mm/h' ], // oder null
    'wind' => [ 'value' => '12',  'unit' => 'km/h' ], // oder null
];
```

Ausgabeform:

```php
[
    'temp'  => [ 'value' => '8,4', 'unit' => '°C' ] | null,
    'tiles' => [ [ 'key' => 'rain', 'value' => '0,4', 'unit' => 'mm/h' ], … ],  // 0–2
    'days'  => [ [ 'date' => '2026-08-09', 'state' => 'rain', 'max' => 11.0, 'min' => 6.0 ], … ],
    'empty' => false,
]
```

- [ ] **Step 1: Testdatei schreiben**

```php
<?php
/**
 * Tests for NAWS_Widget_Data::build().
 *
 * Rain and wind gauges are paid add-on modules, so most installations run
 * this code with holes in the input. The degradation is the point.
 *
 *   php tests/test-widget-data.php
 *
 * @package NAWS
 * @since   1.8.0
 */
define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../includes/class-naws-astro.php';
require_once __DIR__ . '/../includes/class-naws-weather-state.php';
require_once __DIR__ . '/../includes/class-naws-widget-data.php';

$passed = 0;
$failed = 0;

function fc( int $n ): array {
    $days = [];
    $codes = [ 63, 3, 2, 0, 71, 95, 45 ];
    for ( $i = 0; $i < $n; $i++ ) {
        $days[] = [
            'date'        => sprintf( '2026-08-%02d', 9 + $i ),
            'weathercode' => $codes[ $i % count( $codes ) ],
            'temp_max'    => 11.0 + $i,
            'temp_min'    => 6.0 + $i,
        ];
    }
    return [ 'days' => $days, 'location_name' => 'Muenster', 'fetched_at' => 1786205132 ];
}

function station( ?string $rain = '0,4', ?string $wind = '12', ?string $temp = '8,4' ): array {
    return [
        'temp' => $temp === null ? null : [ 'value' => $temp, 'unit' => '°C' ],
        'rain' => $rain === null ? null : [ 'value' => $rain, 'unit' => 'mm/h' ],
        'wind' => $wind === null ? null : [ 'value' => $wind, 'unit' => 'km/h' ],
    ];
}

function check( string $name, array $out, array $expect ): void {
    global $passed, $failed;
    $problems = [];
    foreach ( $expect as $path => $want ) {
        $got = $out;
        foreach ( explode( '.', $path ) as $k ) {
            $got = is_array( $got ) && array_key_exists( $k, $got ) ? $got[ $k ] : null;
        }
        if ( $got !== $want ) {
            $problems[] = sprintf( '%s: erwartet %s, ist %s', $path, var_export( $want, true ), var_export( $got, true ) );
        }
    }
    if ( $problems ) {
        $failed++;
        echo "  FAIL  {$name}\n";
        foreach ( $problems as $p ) { echo "          - {$p}\n"; }
        return;
    }
    $passed++;
    echo "  ok    {$name}\n";
}

echo "\nNAWS_Widget_Data::build()\n" . str_repeat( '-', 74 ) . "\n";

echo "\nTageszahl\n";
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 3 ); check( 'drei Tage', [ 'n' => count( $o['days'] ) ], [ 'n' => 3 ] );
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 5 ); check( 'fuenf Tage', [ 'n' => count( $o['days'] ) ], [ 'n' => 5 ] );
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 4 ); check( 'vier wird auf fuenf gezogen', [ 'n' => count( $o['days'] ) ], [ 'n' => 5 ] );
$o = NAWS_Widget_Data::build( station(), fc( 7 ), 1 ); check( 'eins wird auf drei gezogen', [ 'n' => count( $o['days'] ) ], [ 'n' => 3 ] );
$o = NAWS_Widget_Data::build( station(), fc( 2 ), 5 ); check( 'weniger Tage vorhanden als verlangt', [ 'n' => count( $o['days'] ) ], [ 'n' => 2 ] );

echo "\nZustandsnamen der Tage\n";
$o = NAWS_Widget_Data::build( station(), fc( 5 ), 5 );
check( 'Codes werden abgebildet', [
    'a' => $o['days'][0]['state'], 'b' => $o['days'][1]['state'],
    'c' => $o['days'][3]['state'], 'd' => $o['days'][4]['state'],
], [ 'a' => 'rain', 'b' => 'overcast', 'c' => 'clear_day', 'd' => 'snow' ] );

echo "\nKacheln — null ist nicht 0.0\n";
$o = NAWS_Widget_Data::build( station( '0,4', '12' ), fc( 5 ), 5 );
check( 'beide Module da', [ 'n' => count( $o['tiles'] ), 'k0' => $o['tiles'][0]['key'], 'k1' => $o['tiles'][1]['key'] ], [ 'n' => 2, 'k0' => 'rain', 'k1' => 'wind' ] );
$o = NAWS_Widget_Data::build( station( '0,0', '12' ), fc( 5 ), 5 );
check( 'Regen misst 0,0 -> Kachel bleibt', [ 'n' => count( $o['tiles'] ), 'v' => $o['tiles'][0]['value'] ], [ 'n' => 2, 'v' => '0,0' ] );
$o = NAWS_Widget_Data::build( station( null, '12' ), fc( 5 ), 5 );
check( 'kein Regenmesser -> nur Wind', [ 'n' => count( $o['tiles'] ), 'k0' => $o['tiles'][0]['key'] ], [ 'n' => 1, 'k0' => 'wind' ] );
$o = NAWS_Widget_Data::build( station( '0,4', null ), fc( 5 ), 5 );
check( 'kein Windmesser -> nur Regen', [ 'n' => count( $o['tiles'] ), 'k0' => $o['tiles'][0]['key'] ], [ 'n' => 1, 'k0' => 'rain' ] );
$o = NAWS_Widget_Data::build( station( null, null ), fc( 5 ), 5 );
check( 'kein Zusatzmodul -> keine Kacheln', [ 'n' => count( $o['tiles'] ) ], [ 'n' => 0 ] );

echo "\nLeere Faelle\n";
$o = NAWS_Widget_Data::build( station( null, null, null ), [ 'error' => 'API down' ], 5 );
check( 'nichts verfuegbar -> empty', [ 'e' => $o['empty'], 'n' => count( $o['days'] ) ], [ 'e' => true, 'n' => 0 ] );
$o = NAWS_Widget_Data::build( station(), [ 'error' => 'API down' ], 5 );
check( 'Vorhersage kaputt, Station da', [ 'e' => $o['empty'], 'n' => count( $o['days'] ) ], [ 'e' => false, 'n' => 0 ] );
$o = NAWS_Widget_Data::build( station( null, null, '8,4' ), fc( 5 ), 5 );
check( 'nur Temperatur und Vorhersage', [ 'e' => $o['empty'], 't' => $o['temp']['value'], 'n' => count( $o['tiles'] ) ], [ 'e' => false, 't' => '8,4', 'n' => 0 ] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

```
php tests/test-widget-data.php
```

Erwartet: Fatal error, `Failed opening required '…/class-naws-widget-data.php'`.

- [ ] **Step 3: Klasse implementieren**

Neue Datei `includes/class-naws-widget-data.php`:

```php
<?php
/**
 * NAWS_Widget_Data – prepares the sidebar widget's display structure.
 *
 * Pure function, deliberately: no WordPress, no database, no HTML. The
 * caller formats values and hands them in already rendered, so the whole
 * degradation matrix can be exercised by a plain PHP script.
 *
 * Rain and wind gauges are paid Netatmo add-ons that most installations do
 * not have, so holes in the input are the normal case, not an edge case.
 *
 * @package NAWS
 * @since   1.8.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Widget_Data {

    /** The only two forecast lengths the widget offers. */
    const DAY_CHOICES = [ 3, 5 ];

    /**
     * Build the display structure.
     *
     * @param array $station  [ 'temp'|'rain'|'wind' => [ 'value','unit' ] | null ]
     * @param array $forecast Result of NAWS_Forecast::get_forecast(), may hold 'error'.
     * @param int   $days     Requested forecast length; normalised to 3 or 5.
     * @return array{temp: ?array, tiles: array, days: array, empty: bool}
     */
    public static function build( array $station, array $forecast, int $days ): array {
        $days = self::normalise_days( $days );

        $temp = self::pair( $station['temp'] ?? null );

        // Order is fixed: rain then wind. A missing gauge drops its tile
        // entirely; the remaining one takes the full width in CSS.
        $tiles = [];
        foreach ( [ 'rain', 'wind' ] as $key ) {
            $pair = self::pair( $station[ $key ] ?? null );
            if ( $pair !== null ) {
                $tiles[] = [ 'key' => $key, 'value' => $pair['value'], 'unit' => $pair['unit'] ];
            }
        }

        $rows = [];
        if ( ! isset( $forecast['error'] ) && ! empty( $forecast['days'] ) && is_array( $forecast['days'] ) ) {
            foreach ( array_slice( $forecast['days'], 0, $days ) as $day ) {
                // Forecast entries are days, so is_day is always true.
                $state = NAWS_Weather_State::wmo_to_state( (int) ( $day['weathercode'] ?? -1 ), true );
                $rows[] = [
                    'date'  => (string) ( $day['date'] ?? '' ),
                    'state' => $state,
                    'max'   => isset( $day['temp_max'] ) ? (float) $day['temp_max'] : null,
                    'min'   => isset( $day['temp_min'] ) ? (float) $day['temp_min'] : null,
                ];
            }
        }

        return [
            'temp'  => $temp,
            'tiles' => $tiles,
            'days'  => $rows,
            'empty' => ( $temp === null && $tiles === [] && $rows === [] ),
        ];
    }

    /**
     * Clamp to one of the two offered lengths.
     *
     * Four days is not offered: it would look like five with one column
     * missing and adds a third layout to maintain for no gain.
     */
    private static function normalise_days( int $days ): int {
        return $days < 4 ? 3 : 5;
    }

    /** Validate a value/unit pair, returning null for anything unusable. */
    private static function pair( $raw ): ?array {
        if ( ! is_array( $raw ) || ! isset( $raw['value'] ) || $raw['value'] === '' ) {
            return null;
        }
        return [
            'value' => (string) $raw['value'],
            'unit'  => (string) ( $raw['unit'] ?? '' ),
        ];
    }
}
```

- [ ] **Step 4: Test laufen lassen, grün erwarten**

```
php tests/test-widget-data.php
```

Erwartet: alle Fälle `ok`, `0 fehlgeschlagen`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-naws-widget-data.php tests/test-widget-data.php
git commit -m "Add NAWS_Widget_Data: pure preparation for the sidebar widget"
```

---

### Task 5: Das Widget — Template, CSS, Shortcode

**Files:**
- Create: `templates/weather-widget.php`
- Modify: `assets/css/frontend.css`
- Modify: `includes/class-naws-shortcodes.php`
- Modify: `includes/class-naws-weather-state.php` (`read_station()` öffentlich, `wind_avg` ergänzen)
- Modify: `xtx-integration-for-netatmo.php` (neue Klasse einbinden)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`

**Interfaces:**
- Consumes: `NAWS_Widget_Data::build()` (Task 4), `NAWS_Weather_Icons::render()` und `render_inline()` (Task 2), `NAWS_Weather_State::get_current()` und `read_station()`
- Produces: Shortcode `[naws_weather_icon]`-Nachbar `[naws_weather_widget days="3|5"]`

- [ ] **Step 1: `read_station()` öffentlich machen und um `wind_avg` erweitern**

In `includes/class-naws-weather-state.php` die Signatur ändern:

```php
    /**
     * Read the station values the precedence table needs.
     *
     * Public since 1.8.0: the sidebar widget needs exactly this module
     * resolution and should not repeat it.
     *
     * Every value may be null, and null is meaningful: it means "this
     * module is absent or has not reported", which is different from a
     * measured zero. The API fallback ranks hinge on that difference.
     *
     * @return array{rain: ?float, wind: ?float, wind_avg: ?float, temp: ?float, humidity: ?float}
     */
    public static function read_station(): array {
        $out = [ 'rain' => null, 'wind' => null, 'wind_avg' => null, 'temp' => null, 'humidity' => null ];
```

Im Windblock beide Werte getrennt füllen:

```php
        $wind_mod = $by_type['NAModule2'][0] ?? null;
        if ( $wind_mod ) {
            foreach ( NAWS_Database::get_latest_readings( $wind_mod ) as $r ) {
                // 'wind' drives the storm rule and prefers the gust peak:
                // a gale is felt in the gusts, not the ten-minute mean.
                // 'wind_avg' is the mean, which is what the widget shows.
                if ( $r['parameter'] === 'GustStrength' ) $out['wind']     = (float) $r['value'];
                if ( $r['parameter'] === 'WindStrength' ) $out['wind_avg'] = (float) $r['value'];
            }
            if ( $out['wind'] === null ) {
                $out['wind'] = $out['wind_avg'];
            }
        }
```

- [ ] **Step 2: Regression prüfen**

```
php tests/test-weather-state.php
```

Erwartet: weiterhin `36 bestanden, 0 fehlgeschlagen` — `decide()` ist von der Änderung nicht betroffen.

- [ ] **Step 3: Template anlegen**

Neue Datei `templates/weather-widget.php`:

```php
<?php
/**
 * Template: [naws_weather_widget days="3|5"]
 *
 * The head icon is literal markup because the multi-colour set does not
 * survive naws_svg_kses_args(); see templates/weather-icon.php. Everything
 * else on this page is escaped normally.
 *
 * Expected variables:
 * @var array   $naws_wgt       Result of NAWS_Widget_Data::build()
 * @var string  $naws_wgt_state Current weather state, '' if unknown
 * @var string  $naws_wgt_place Location name, '' to omit
 * @var string  $naws_wgt_time  Formatted time of last fetch, '' to omit
 *
 * @package NAWS
 * @since   1.8.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! empty( $naws_wgt['empty'] ) ) {
    return; // Nothing determinable – render nothing, not an empty frame.
}
$naws_wgt_cols = count( $naws_wgt['days'] );
?>
<div class="naws-wgt">

  <div class="naws-wgt-head">
    <?php if ( $naws_wgt_state !== '' ) : ?>
      <?php echo NAWS_Weather_Icons::render_inline( $naws_wgt_state, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG ?>
    <?php endif; ?>
    <div class="naws-wgt-head-txt">
      <?php if ( $naws_wgt['temp'] !== null ) : ?>
        <div class="naws-wgt-temp"><?php echo esc_html( $naws_wgt['temp']['value'] ); ?><span class="naws-wgt-deg"> <?php echo esc_html( $naws_wgt['temp']['unit'] ); ?></span></div>
      <?php endif; ?>
      <?php if ( $naws_wgt_state !== '' ) : ?>
        <div class="naws-wgt-cond"><?php echo esc_html( NAWS_Weather_Icons::label( $naws_wgt_state ) ); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ( $naws_wgt['tiles'] ) : ?>
    <div class="naws-wgt-chips">
      <?php foreach ( $naws_wgt['tiles'] as $naws_wgt_tile ) : ?>
        <div class="naws-wgt-chip">
          <span class="naws-wgt-k"><?php echo esc_html( naws__( 'wgt_' . $naws_wgt_tile['key'] ) ); ?></span>
          <span class="naws-wgt-v"><?php echo esc_html( $naws_wgt_tile['value'] ); ?><span class="naws-wgt-sub"> <?php echo esc_html( $naws_wgt_tile['unit'] ); ?></span></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( $naws_wgt_cols > 0 ) : ?>
    <div class="naws-wgt-strip" style="--naws-wgt-cols:<?php echo absint( $naws_wgt_cols ); ?>">
      <?php foreach ( $naws_wgt['days'] as $naws_wgt_day ) : ?>
        <div class="naws-wgt-day">
          <span class="naws-wgt-dow"><?php echo esc_html( NAWS_Forecast::weekday_short( $naws_wgt_day['date'] ) ); ?></span>
          <?php if ( $naws_wgt_day['state'] !== '' ) : ?>
            <?php echo NAWS_Weather_Icons::render_inline( $naws_wgt_day['state'], 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG ?>
          <?php endif; ?>
          <span class="naws-wgt-t">
            <?php echo esc_html( null === $naws_wgt_day['max'] ? '–' : round( $naws_wgt_day['max'] ) . '°' ); ?><br>
            <span class="naws-wgt-lo"><?php echo esc_html( null === $naws_wgt_day['min'] ? '–' : round( $naws_wgt_day['min'] ) . '°' ); ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( $naws_wgt_place !== '' || $naws_wgt_time !== '' ) : ?>
    <div class="naws-wgt-foot">
      <span><?php echo esc_html( $naws_wgt_place ); ?></span>
      <span><?php echo esc_html( $naws_wgt_time ); ?></span>
    </div>
  <?php endif; ?>

</div>
```

- [ ] **Step 4: CSS ergänzen**

Ans Ende von `assets/css/frontend.css`:

```css
/* ===============================================================
   Sidebar widget (v1.8.0)
   Designed against a 250 px column; it fills its container and stays
   legible from about 220 px. Namespace .naws-wgt-* — .naws-wx is the
   forecast card and .naws-wxi are the icon SVGs.
   =============================================================== */

.naws-wgt {
  background:#fff; border:1px solid #e2e8f1; border-radius:12px;
  overflow:hidden; color:#121822; font-size:14px; line-height:1.45;
}
.naws-wgt-head { display:flex; align-items:center; gap:10px; padding:12px 12px 10px; }
.naws-wgt-head .naws-wxi { flex:0 0 auto; }
.naws-wgt-temp { font-size:32px; font-weight:640; letter-spacing:-.03em; line-height:1; font-variant-numeric:tabular-nums; }
.naws-wgt-deg { font-size:18px; font-weight:500; color:#5f6b7b; }
.naws-wgt-cond { font-size:12.5px; color:#5f6b7b; margin-top:3px; }

.naws-wgt-chips { display:flex; gap:8px; padding:0 12px 12px; }
.naws-wgt-chip { flex:1; background:#f2f6fc; border-radius:8px; padding:7px 9px; display:flex; flex-direction:column; gap:2px; }
.naws-wgt-k { font-size:9.5px; letter-spacing:.1em; text-transform:uppercase; color:#5f6b7b; }
.naws-wgt-v { font-size:13.5px; font-variant-numeric:tabular-nums; }
.naws-wgt-sub { color:#5f6b7b; font-size:11px; }

/* One column per forecast day, set from the template so 3 and 5 share
   one rule instead of two hard-coded variants. */
.naws-wgt-strip { display:grid; grid-template-columns:repeat(var(--naws-wgt-cols,5), 1fr); border-top:1px solid #e2e8f1; }
.naws-wgt-day { display:flex; flex-direction:column; align-items:center; gap:3px; padding:9px 2px 10px; }
.naws-wgt-day + .naws-wgt-day { border-left:1px solid #e2e8f1; }
.naws-wgt-dow { font-size:9.5px; letter-spacing:.09em; text-transform:uppercase; color:#5f6b7b; }
.naws-wgt-t { font-size:11.5px; font-variant-numeric:tabular-nums; text-align:center; }
.naws-wgt-lo { color:#5f6b7b; }

.naws-wgt-foot {
  border-top:1px solid #e2e8f1; padding:6px 12px 8px;
  font-size:10px; color:#5f6b7b; letter-spacing:.04em;
  display:flex; justify-content:space-between; gap:8px;
}
```

- [ ] **Step 5: Sprachschlüssel ergänzen**

In `languages/de.php` vor der schließenden `];`:

```php
    // ── Seitenleisten-Widget (v1.8.0) ─────────────────────────────────
    'wgt_rain'                  => 'Regen',
    'wgt_wind'                  => 'Wind',
```

In `languages/en.php`:

```php
    // ── Sidebar widget (v1.8.0) ───────────────────────────────────────
    'wgt_rain'                  => 'Rain',
    'wgt_wind'                  => 'Wind',
```

In `languages/no.php`:

```php
    // ── Sidefelt-widget (v1.8.0) ──────────────────────────────────────
    'wgt_rain'                  => 'Regn',
    'wgt_wind'                  => 'Vind',
```

- [ ] **Step 6: Shortcode ergänzen**

In `includes/class-naws-shortcodes.php` bei den anderen `add_shortcode`-Zeilen:

```php
        add_shortcode( 'naws_weather_widget', [ $this, 'sc_weather_widget' ] );
```

Und die Methode ans Ende der Klasse, vor der schließenden Klammer:

```php
    // ----------------------------------------------------------------
    // [naws_weather_widget days="3|5"]
    // Compact sidebar widget: icon and temperature, rain and wind,
    // three or five forecast days.
    // ----------------------------------------------------------------
    public function sc_weather_widget( $atts ) {
        $opts = get_option( 'naws_settings', [] );

        $atts = shortcode_atts( [
            'days' => (string) ( $opts['wgt_days'] ?? 5 ),
        ], $atts, 'naws_weather_widget' );

        $station = NAWS_Weather_State::read_station();
        $state   = NAWS_Weather_State::get_current();
        $days    = intval( $atts['days'] );

        // Values are formatted here so NAWS_Widget_Data::build() stays free
        // of WordPress and remains testable without a framework.
        $fmt = static function ( ?float $raw, string $param ): ?array {
            if ( $raw === null ) {
                return null;
            }
            return [
                'value' => (string) NAWS_Helpers::format_value( $param, $raw ),
                'unit'  => NAWS_Helpers::get_unit( $param ),
            ];
        };

        $forecast = NAWS_Forecast::get_forecast( $days < 4 ? 3 : 5 );

        $naws_wgt = NAWS_Widget_Data::build(
            [
                'temp' => $fmt( $station['temp'], 'Temperature' ),
                'rain' => $fmt( $station['rain'], 'Rain' ),
                'wind' => $fmt( $station['wind_avg'], 'WindStrength' ),
            ],
            $forecast,
            $days
        );

        if ( $naws_wgt['empty'] ) {
            return '';
        }

        $this->enqueue_frontend();

        $naws_wgt_state = $state['state'];
        $naws_wgt_place = (string) ( $forecast['location_name'] ?? '' );
        $naws_wgt_time  = empty( $forecast['fetched_at'] )
            ? ''
            : wp_date( get_option( 'time_format', 'H:i' ), (int) $forecast['fetched_at'] );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/weather-widget.php';
        return ob_get_clean();
    }
```

- [ ] **Step 7: Klasse einbinden**

In `xtx-integration-for-netatmo.php` nach der `class-naws-weather-icons.php`-Zeile:

```php
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-widget-data.php' );
```

- [ ] **Step 8: Syntax prüfen und Tests wiederholen**

```
php -l templates/weather-widget.php
php -l includes/class-naws-shortcodes.php
php -l includes/class-naws-weather-state.php
php tests/test-widget-data.php
php tests/test-weather-state.php
php tests/test-wmo-mapping.php
```

Alle grün, keine Syntaxfehler.

- [ ] **Step 9: Commit**

```bash
git add includes/ templates/weather-widget.php assets/css/frontend.css languages/ xtx-integration-for-netatmo.php
git commit -m "Add [naws_weather_widget] sidebar widget"
```

---

### Task 6: Backend — Einstellung, Vorschau, Shortcode-Karte

**Files:**
- Modify: `includes/class-naws-admin.php` (Sanitierung, Stylesheet auf der Appearance-Seite)
- Modify: `admin/views/appearance.php` (Einstellung und Vorschau)
- Modify: `admin/views/shortcodes.php` (Karte)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`

**Interfaces:**
- Consumes: alles aus Task 5
- Produces: Option `naws_settings['wgt_days']`

- [ ] **Step 1: Sanitierung ergänzen**

In `includes/class-naws-admin.php`, in `sanitize_settings()` bei den anderen `wx_`-Zeilen:

```php
        // Sidebar widget: only two lengths are offered, so anything else
        // is pulled to the nearer one rather than rejected.
        if ( $sent( 'wgt_days' ) ) {
            $clean['wgt_days'] = intval( $input['wgt_days'] ) < 4 ? 3 : 5;
        }
```

- [ ] **Step 2: Stylesheet für die Vorschau laden**

In `enqueue_assets()` die vorhandene Bedingung erweitern:

```php
        // The shortcodes page previews the live weather icon and the
        // appearance page previews the sidebar widget; both need the
        // frontend stylesheet for keyframes and layout. The
        // 'naws-frontend' handle is registered on wp_enqueue_scripts and
        // does not exist in the admin, so the file gets its own handle.
        if ( strpos( $hook, 'naws-shortcodes' ) !== false || strpos( $hook, 'naws-appearance' ) !== false ) {
            wp_enqueue_style( 'naws-weather-icon', NAWS_PLUGIN_URL . 'assets/css/frontend.css', [], NAWS_VERSION );
        }
```

- [ ] **Step 3: Abschnitt auf der Appearance-Seite**

Ans Ende des Formulars in `admin/views/appearance.php`, vor dem Speichern-Knopf. **Zuerst** mit `grep -n "<form\|submit_button\|naws_settings\[" admin/views/appearance.php` prüfen, ob die Seite überhaupt in `naws_settings` schreibt; falls sie einen eigenen Options-Schlüssel nutzt, stattdessen ein eigenes kleines Formular mit `wp_nonce_field( 'naws_save_settings' )` und `action=naws_save_settings` einsetzen und die übrigen `naws_settings`-Werte **nicht** mitsenden — die Merge-Semantik aus 1.7.0 erhält sie von selbst.

```php
<h3><?php naws_e( 'wgt_heading' ); ?></h3>
<p class="description" style="margin-bottom:1rem;"><?php naws_e( 'wgt_desc' ); ?></p>

<table class="form-table naws-form-table">
    <tr>
        <th><?php naws_e( 'wgt_days_label' ); ?></th>
        <td>
            <select name="naws_settings[wgt_days]">
                <option value="3" <?php selected( intval( $options['wgt_days'] ?? 5 ), 3 ); ?>><?php naws_e( 'wgt_days_3' ); ?></option>
                <option value="5" <?php selected( intval( $options['wgt_days'] ?? 5 ), 5 ); ?>><?php naws_e( 'wgt_days_5' ); ?></option>
            </select>
            <p class="description"><?php naws_e( 'wgt_days_desc' ); ?></p>
        </td>
    </tr>
</table>

<?php
// Live preview in a real 250 px column, so the setting is judged at the
// width it will actually be used at.
$naws_prev_station = NAWS_Weather_State::read_station();
$naws_prev_state   = NAWS_Weather_State::get_current();
$naws_prev_days    = intval( $options['wgt_days'] ?? 5 );
$naws_prev_fc      = NAWS_Forecast::get_forecast( $naws_prev_days );
$naws_prev_fmt     = static function ( ?float $raw, string $param ): ?array {
    if ( $raw === null ) return null;
    return [ 'value' => (string) NAWS_Helpers::format_value( $param, $raw ), 'unit' => NAWS_Helpers::get_unit( $param ) ];
};
$naws_wgt = NAWS_Widget_Data::build(
    [
        'temp' => $naws_prev_fmt( $naws_prev_station['temp'], 'Temperature' ),
        'rain' => $naws_prev_fmt( $naws_prev_station['rain'], 'Rain' ),
        'wind' => $naws_prev_fmt( $naws_prev_station['wind_avg'], 'WindStrength' ),
    ],
    $naws_prev_fc,
    $naws_prev_days
);
?>
<div style="max-width:250px;padding:14px 12px;background:#fbfcfe;border:1px solid #cbd4e0;border-radius:12px;margin:0 0 1rem;">
    <?php
    if ( $naws_wgt['empty'] ) {
        echo '<small style="color:#64748b">' . esc_html( naws__( 'wgt_preview_none' ) ) . '</small>';
    } else {
        $naws_wgt_state = $naws_prev_state['state'];
        $naws_wgt_place = (string) ( $naws_prev_fc['location_name'] ?? '' );
        $naws_wgt_time  = empty( $naws_prev_fc['fetched_at'] ) ? '' : wp_date( get_option( 'time_format', 'H:i' ), (int) $naws_prev_fc['fetched_at'] );
        include NAWS_PLUGIN_DIR . 'templates/weather-widget.php';
    }
    ?>
</div>
```

- [ ] **Step 4: Karte auf der Shortcode-Seite**

In `admin/views/shortcodes.php`, direkt nach der `[naws_weather_icon]`-Karte:

```php
    <div class="naws-sc-card">
        <h3><code>[naws_weather_widget]</code> &ndash; <?php naws_e('sc_wgt_desc'); ?></h3>
        <p><?php naws_e('sc_wgt_long_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_weather_widget]</pre><button class="naws-copy-btn" data-copy='[naws_weather_widget]'><?php naws_e('sc_copy'); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr>
                <th><?php naws_e('sc_th_attribute'); ?></th>
                <th><?php naws_e('sc_th_description'); ?></th>
                <th><?php naws_e('sc_th_default'); ?></th>
            </tr>
            <tr><td><code>days</code></td><td><?php naws_e('sc_wgt_attr_days'); ?></td><td><span class="naws-tag-default"><?php echo intval( get_option('naws_settings', [])['wgt_days'] ?? 5 ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_weather_widget]</code> <?php naws_e('sc_wgt_ex_default'); ?></div>
            <div class="naws-inline-ex"><code>[naws_weather_widget days="3"]</code> <?php naws_e('sc_wgt_ex_three'); ?></div>
        </div>
    </div>
```

- [ ] **Step 5: Sprachschlüssel ergänzen**

`languages/de.php`:

```php
    'wgt_heading'               => 'Seitenleisten-Widget',
    'wgt_desc'                  => 'Kompaktes Wetter-Widget für schmale Spalten. Zeigt Icon und Außentemperatur, darunter Regen und Wind, darunter die Vorhersage. Einzufügen über den Shortcode [naws_weather_widget].',
    'wgt_days_label'            => 'Vorhersagetage',
    'wgt_days_3'                => '3 Tage',
    'wgt_days_5'                => '5 Tage',
    'wgt_days_desc'             => 'Bei drei Tagen ist jede Spalte 77 Pixel breit, bei fünf nur 46. Auf schmalen Seitenleisten sind drei deutlich besser lesbar.',
    'wgt_preview_none'          => 'Zurzeit nichts darstellbar — weder Stationswerte noch Vorhersage liegen vor. Das Widget würde nichts ausgeben.',
    'sc_wgt_desc'               => 'Kompaktes Wetter-Widget für Seitenleisten',
    'sc_wgt_long_desc'          => 'Icon und Außentemperatur, darunter Regen und Wind, darunter die Vorhersage. Ausgelegt auf 250 Pixel Breite, füllt aber jeden Container. Fehlt ein Zusatzmodul, entfällt der zugehörige Wert ersatzlos. Wird beim Seitenaufbau erzeugt und aktualisiert sich nicht von selbst — die Uhrzeit in der Fußzeile zeigt das Alter.',
    'sc_wgt_attr_days'          => 'Länge der Vorhersage. Nur 3 oder 5; andere Werte werden auf den nächstliegenden gezogen.',
    'sc_wgt_ex_default'         => 'nutzt die Einstellung aus dem Backend',
    'sc_wgt_ex_three'           => 'kürzer, für sehr schmale Spalten',
```

`languages/en.php`:

```php
    'wgt_heading'               => 'Sidebar widget',
    'wgt_desc'                  => 'Compact weather widget for narrow columns. Shows the icon and outdoor temperature, rain and wind below that, then the forecast. Placed with the [naws_weather_widget] shortcode.',
    'wgt_days_label'            => 'Forecast days',
    'wgt_days_3'                => '3 days',
    'wgt_days_5'                => '5 days',
    'wgt_days_desc'             => 'Three days gives each column 77 pixels, five gives only 46. On narrow sidebars three reads considerably better.',
    'wgt_preview_none'          => 'Nothing displayable right now — neither station readings nor forecast are available. The widget would output nothing.',
    'sc_wgt_desc'               => 'Compact weather widget for sidebars',
    'sc_wgt_long_desc'          => 'Icon and outdoor temperature, rain and wind below that, then the forecast. Designed for 250 pixels but fills any container. A missing add-on module drops its value entirely. Rendered on page load and does not refresh by itself — the time in the footer shows its age.',
    'sc_wgt_attr_days'          => 'Forecast length. Only 3 or 5; other values are pulled to the nearer one.',
    'sc_wgt_ex_default'         => 'uses the backend setting',
    'sc_wgt_ex_three'           => 'shorter, for very narrow columns',
```

`languages/no.php`:

```php
    'wgt_heading'               => 'Sidefelt-widget',
    'wgt_desc'                  => 'Kompakt værwidget for smale spalter. Viser ikon og utetemperatur, under det regn og vind, deretter varselet. Settes inn med kortkoden [naws_weather_widget].',
    'wgt_days_label'            => 'Varseldager',
    'wgt_days_3'                => '3 dager',
    'wgt_days_5'                => '5 dager',
    'wgt_days_desc'             => 'Tre dager gir hver kolonne 77 piksler, fem gir bare 46. I smale sidefelt er tre merkbart lettere å lese.',
    'wgt_preview_none'          => 'Ingenting kan vises nå — verken stasjonsmålinger eller varsel foreligger. Widgeten ville ikke vist noe.',
    'sc_wgt_desc'               => 'Kompakt værwidget for sidefelt',
    'sc_wgt_long_desc'          => 'Ikon og utetemperatur, under det regn og vind, deretter varselet. Laget for 250 piksler, men fyller enhver beholder. Mangler en tilleggsmodul, faller verdien bort uten erstatning. Bygges ved sidelasting og oppdateres ikke av seg selv — klokkeslettet i bunnlinjen viser alderen.',
    'sc_wgt_attr_days'          => 'Varselets lengde. Bare 3 eller 5; andre verdier trekkes til nærmeste.',
    'sc_wgt_ex_default'         => 'bruker innstillingen fra administrasjonen',
    'sc_wgt_ex_three'           => 'kortere, for svært smale spalter',
```

- [ ] **Step 6: Schlüssel-Vollständigkeit prüfen**

```bash
for k in wgt_rain wgt_wind wgt_heading wgt_desc wgt_days_label wgt_days_3 wgt_days_5 wgt_days_desc wgt_preview_none sc_wgt_desc sc_wgt_long_desc sc_wgt_attr_days sc_wgt_ex_default sc_wgt_ex_three; do
  printf "%-20s de=%s en=%s no=%s\n" "$k" "$(grep -c "'$k'" languages/de.php)" "$(grep -c "'$k'" languages/en.php)" "$(grep -c "'$k'" languages/no.php)"
done
```

Erwartet: überall `de=1 en=1 no=1`.

- [ ] **Step 7: Sanitierung testen**

`tests/test-settings-merge.php` um ein Szenario ergänzen, direkt vor der Schlussausgabe:

```php
scenario(
    'Widget-Tage werden auf 3 oder 5 gezogen',
    [ 'wgt_days' => 4 ],
    [ 'language', 'forecast_city' ],
    [ 'wgt_days' => 5 ]
);
scenario(
    'Widget-Tage 2 wird zu 3',
    [ 'wgt_days' => 2 ],
    [ 'language' ],
    [ 'wgt_days' => 3 ]
);
```

Run: `php tests/test-settings-merge.php`
Erwartet: `alle Szenarien bestanden`.

- [ ] **Step 8: Commit**

```bash
git add includes/class-naws-admin.php admin/views/ languages/ tests/test-settings-merge.php
git commit -m "Backend: widget forecast length, live preview, shortcode card"
```

---

### Task 7: Version 1.8.0, Verifikation, Auslieferung

**Files:**
- Modify: `xtx-integration-for-netatmo.php`, `readme.txt`, `README.md`, `CHANGELOG.md`

- [ ] **Step 1: Version setzen**

```bash
sed -i 's/^ \* Version: 1\.7\.0/ * Version: 1.8.0/' xtx-integration-for-netatmo.php
sed -i "s/define( 'NAWS_VERSION',        '1\.7\.0' );/define( 'NAWS_VERSION',        '1.8.0' );/" xtx-integration-for-netatmo.php
sed -i 's/^Stable tag: 1\.7\.0/Stable tag: 1.8.0/' readme.txt README.md
```

- [ ] **Step 2: Changelog-Einträge**

In `CHANGELOG.md` über den 1.7.0-Block:

```markdown
## [1.8.0] – 2026-08-08

### Added
- **Sidebar widget.** `[naws_weather_widget days="3|5"]` — weather icon and outdoor temperature, rain and wind below that, then a three- or five-day forecast. Designed against a 250 px column and fills any container. A missing rain or wind module drops its value entirely rather than showing a placeholder.
- **Forecast length setting** with a live preview at true width on the appearance page.

### Changed
- **One weather icon set across the whole plugin.** `[naws_forecast]` and the dashboard forecast strip now use the multi-colour set introduced in 1.7.0 instead of the older flat one. Forecast-day icons are rendered still: a row of animated icons pulls attention away from the numbers beside them.
- The mapping from WMO codes to icon states moved into `NAWS_Weather_State::wmo_to_state()`, shared by all three display places, so they cannot drift apart from each other or from the live icon.
- `NAWS_Forecast::get_weather_svg()` is deprecated. Nothing in the plugin calls it; it is kept because it is public and may sit in a user's theme snippet.
```

In `readme.txt` über `= 1.7.0 =`:

```
= 1.8.0 =
* New: sidebar widget `[naws_weather_widget]` — weather icon, outdoor temperature, rain and wind, plus a three- or five-day forecast, built for narrow columns
* New: forecast length selectable in the backend, with a live preview at the real width
* Changed: the colourful weather icons introduced in 1.7.0 are now used everywhere, including the forecast shortcode and the dashboard forecast strip
```

- [ ] **Step 3: Vollständige Verifikation**

```bash
PHP="/c/Users/xyla1/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
for f in $(find . -name "*.php" -not -path "./.git/*"); do "$PHP" -l "$f"; done | grep -v "No syntax errors" || echo "0 Syntaxfehler"
"$PHP" tests/test-weather-state.php   | grep -E "bestanden"
"$PHP" tests/test-wmo-mapping.php     | grep -E "bestanden"
"$PHP" tests/test-widget-data.php     | grep -E "bestanden"
"$PHP" tests/test-settings-merge.php  | grep -E "bestanden"
"$PHP" tests/smoke-render-inline.php
```

Erwartet: keine Syntaxfehler, alle vier Testdateien grün, Rauchtest ok.

- [ ] **Step 4: Ins GitHub-Verzeichnis spiegeln**

```powershell
$src = "C:\Users\xyla1\.claude\Netatmo"; $dst = "C:\Users\xyla1\Documents\GitHub\Netatmo"
$files = @(
  "includes\class-naws-weather-state.php","includes\class-naws-weather-icons.php",
  "includes\class-naws-widget-data.php","includes\class-naws-shortcodes.php",
  "includes\class-naws-admin.php","includes\class-naws-forecast.php",
  "templates\weather-icon.php","templates\weather-widget.php",
  "templates\forecast.php","templates\live.php",
  "admin\views\appearance.php","admin\views\shortcodes.php",
  "assets\css\frontend.css",
  "languages\de.php","languages\en.php","languages\no.php",
  "xtx-integration-for-netatmo.php","readme.txt","README.md","CHANGELOG.md"
)
$ok = 0; $bad = 0
foreach ($f in $files) {
  $s = Join-Path $src $f; $d = Join-Path $dst $f
  $dir = Split-Path $d -Parent
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Force $dir | Out-Null }
  Copy-Item $s $d -Force
  if ((Get-FileHash $s).Hash -eq (Get-FileHash $d).Hash) { $ok++ } else { $bad++; "ABWEICHUNG: $f" }
}
"$ok identisch, $bad Abweichungen"
```

Erwartet: `20 identisch, 0 Abweichungen`. `tests/`, `docs/`, `build-zip.ps1` und `.distignore` stehen bewusst **nicht** in der Liste.

- [ ] **Step 5: Committen und pushen**

```bash
cd /c/Users/xyla1/Documents/GitHub/Netatmo
git add -A
git commit -F - <<'MSGEOF'
Version 1.8.0 – sidebar widget and one icon set everywhere
MSGEOF
git push origin main
git tag -a v1.8.0 -m "Version 1.8.0 - sidebar widget" && git push origin v1.8.0
```

- [ ] **Step 6: Paket bauen und Release anlegen**

```bash
cd /c/Users/xyla1/.claude/Netatmo
powershell -ExecutionPolicy Bypass -File ./build-zip.ps1
```

Das Build-Skript prüft selbst, dass keine Backslashes in den Einträgen stehen und die Wurzel den Plugin-Slug trägt; es bricht sonst ab.

Danach die Release-Notes aus dem 1.8.0-Abschnitt der `CHANGELOG.md` in eine Datei schreiben und das Release anlegen:

```bash
cd /c/Users/xyla1/Documents/GitHub/Netatmo
gh release create v1.8.0 \
  "C:/Users/xyla1/.claude/xtx-integration-for-netatmo.1.8.0.zip#Installable plugin ZIP (correct folder name)" \
  --title "Version 1.8.0 – sidebar widget" \
  --notes-file /tmp/rel-1.8.0.md
```

Die Notes müssen den Hinweis enthalten, das **angehängte** ZIP zu verwenden und nicht die automatisch erzeugten Quellarchive — deren Wurzelordner heißt `Netatmo-1.8.0`, wodurch WordPress ein zweites Plugin anlegt statt zu aktualisieren.

- [ ] **Step 7: Release prüfen**

```bash
gh release view v1.8.0 --json tagName,isDraft,assets --jq '{tag:.tagName, entwurf:.isDraft, assets:[.assets[]|.name]}'
```

Erwartet: `v1.8.0`, kein Entwurf, ein Asset namens `xtx-integration-for-netatmo.1.8.0.zip`.
