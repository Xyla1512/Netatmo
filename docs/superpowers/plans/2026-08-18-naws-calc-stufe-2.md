# [naws_calc] Stufe 2 — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `[naws_calc]` beantwortet die zwölf Tageskennzahlen — Eistag bis Grünlandtemperatursumme — mit Zeitraum- und Seriengrammatik.

**Architecture:** Eine neue Klasse `NAWS_Climate` hält die reine Mathematik über Tageszeilen: keine Optionen, keine Datenbank, keine Uhr, testbar ohne WordPress. `NAWS_Calc` wird nach der Art des Wertes aufgeteilt (`raw_instant()` / `raw_dayclass()` / `raw_sum()`) und holt die Zeilen über die **vorhandene** `NAWS_Database::get_daily_summaries()`. `sc_calc()` bekommt die neuen Attribute.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, keine Composer-Abhängigkeiten im Plugin. Tests sind einfache PHP-Skripte ohne WordPress-Bootstrap. PHPCS/WPCS über `.phpcs.xml.dist`.

**Spec:** `docs/superpowers/specs/2026-08-18-berechnete-werte-shortcode-design.md` (§4.2, §4.3, §6.3–6.8, §9; Stufe 2 in §12)

## Global Constraints

- **Arbeitsverzeichnis `C:\Users\xyla1\.claude\Netatmo\`**, Branch `main`, kein Feature-Branch.
- **Kein Versions-Bump.** Header, `readme.txt`, `README.md` bleiben auf 1.9.6.
- **Keine Schemaänderung.** `NAWS_DB_VERSION` bleibt `'1.4'`.
- **Requires PHP 8.0** — keine Sprachfeatures ab 8.1 (kein `readonly`, keine `enum`, keine `never`-Rückgabe).
- **Drei Sprachen vollständig:** jeder neue Schlüssel in `languages/de.php`, `en.php` **und** `no.php`. Aktueller Stand: **je 641**.
- **Ausgabe:** Shortcodes geben zurück, sie echoen nicht. `esc_html()` für Text, `esc_attr()` für Attributwerte.
- **Zeitzone:** alle Tagesgrenzen über `naws_timezone()` bzw. `wp_date()`, niemals eine fest verdrahtete Zone oder bares `date()`.
- **„Keine Daten" ≠ „null Tage":** ein Zeitraum ohne verwertbare Zeilen ergibt `null` (→ Ersatztext), ein Zeitraum mit Zeilen und null Treffern ergibt `0`.
- **Qualitätsgate:** `php vendor/bin/phpcs --standard=.phpcs.xml.dist <dateien>` mit **0 Befunden**, `php -l` fehlerfrei. `tests/` ist von PHPCS ausgenommen.
- **Testsuite:** `php tests/test-*.php` **plus** `php tests/smoke-render-inline.php` (kein `test-`-Präfix!). Ergebniszeile ist nicht die letzte Ausgabezeile — auf `bestanden`/`fehlgeschlagen` grepen.
- **Nichts aus Stufe 3** (SPI) umsetzen.

## Zwei bewusste Abweichungen von der Spec

**§7.2 verlangt eine neue Lesemethode für Tagesbereiche — die wird nicht gebaut.** `NAWS_Database::get_daily_summaries( $args )` existiert bereits und liefert genau das Nötige: Zeilen mit `module_id`, `station_id`, `day_date` und den angeforderten Feldern, gefiltert über `date_from`/`date_to` und `module_id`, aufsteigend nach Datum sortiert. Ihre Feld-Whitelist ist `temp_min`, `temp_max`, `temp_avg`, `pressure_avg`, `rain_sum` — alles, was Stufe 2 braucht.

**§7.3 verlangt eine eigene Transient-Schicht bis Mitternacht — die wird nicht gebaut.** `get_daily_summaries()` hat bereits einen Transient-Cache (`CACHE_PREFIX . 'daily_' . md5(...)`, `TTL_DAILY` = 1 Stunde) und hängt an der vorhandenen Cache-Leerung. Die teure Operation ist die Datenbankabfrage, nicht die Arithmetik: `NAWS_Climate` rechnet über höchstens ein paar hundert Zeilen im Speicher. Zehn Shortcodes auf einer Seite kosten damit **eine** Abfrage. Eine zweite Cache-Schicht darüber wäre ein eigener Invalidierungspfad ohne Gegenwert.

---

### Task 1: Vorarbeiten — Katalog-Einheiten, Aufteilung nach Art

Drei Aufräumschritte aus der Gesamtreview der Stufe 1, **ohne jede Verhaltensänderung**. Sie machen Stufe 2 möglich: der Katalog kann bisher keine Einheit ausdrücken, die kein Sensorparameter ist (`Kd`, Tage), und `raw()` ist ein einzelner `switch`, der mit 27 Einträgen unhaltbar würde.

**Files:**
- Modify: `includes/class-naws-calc.php`
- Modify: `includes/class-naws-shortcodes.php` (nur `sc_calc()`s Einheitenzeile)
- Test: `tests/test-calc-catalogue.php` (erweitern)

**Interfaces:**
- Consumes: `NAWS_Calc::catalogue()`, `NAWS_Calc::has()`, `NAWS_Calc::raw()` (Stufe 1)
- Produces:
  - Katalogeinträge dürfen zusätzlich `'unit' => string` tragen (literale Einheit, wenn `param` null ist)
  - `NAWS_Calc::unit_for( string $key ): string` — literale Einheit, sonst `NAWS_Helpers::get_unit( param )`, sonst `''`
  - `NAWS_Calc::raw_instant( string $key, array $atts )` — die 14 bisherigen Zweige
  - `NAWS_Calc::raw()` wird zur Weiche über `$entry['kind']`

- [ ] **Step 1: Katalogtest um die neue Zusage erweitern**

In `tests/test-calc-catalogue.php`, in der Schleife über die Katalogeinträge, hinter die vorhandenen Prüfungen einfügen:

```php
    check( "$key: unit fehlt oder ist String", ( ! isset( $entry['unit'] ) || is_string( $entry['unit'] ) ), true );
    check( "$key: unit nur ohne param",        ( ! isset( $entry['unit'] ) || ! isset( $entry['param'] ) || $entry['param'] === null ), true );
```

Die zweite Prüfung hält die Regel fest: **entweder** ein Sensorparameter (der Einheit und Umrechnung mitbringt) **oder** eine literale Einheit — nie beides, sonst wäre unklar, welche gewinnt.

- [ ] **Step 2: Test laufen lassen**

Run: `php tests/test-calc-catalogue.php | grep -E "bestanden|FAIL"`
Expected: weiterhin `0 fehlgeschlagen` — kein Stufe-1-Eintrag trägt `unit`, die Prüfungen sind noch leer erfüllt. Die Zahl steigt von 142 auf 170 (zwei Prüfungen × 14 Einträge).

- [ ] **Step 3: `unit_for()` ergänzen**

In `includes/class-naws-calc.php`, hinter `has()`:

```php
    /**
     * Unit label for a catalogue entry.
     *
     * Two sources, never both: an entry either names a NAWS_Helpers sensor
     * parameter — which brings its own unit and its own °C/°F conversion —
     * or it carries a literal unit of its own. Degree days (Kd) and day
     * counts have no sensor parameter to borrow from, which is why the
     * literal exists.
     */
    public static function unit_for( string $key ): string {
        $entry = self::catalogue()[ $key ] ?? null;
        if ( $entry === null ) {
            return '';
        }
        if ( ! empty( $entry['unit'] ) ) {
            return (string) $entry['unit'];
        }
        return $entry['param'] ? NAWS_Helpers::get_unit( $entry['param'] ) : '';
    }
```

- [ ] **Step 4: `sc_calc()` auf `unit_for()` umstellen**

In `includes/class-naws-shortcodes.php`, in `sc_calc()`, die Zeile die `$unit_str` bildet ersetzen. Vorher steht dort sinngemäß `( $atts['unit'] !== '0' && $param ) ? ' ' . NAWS_Helpers::get_unit( $param ) : ''`. Neu:

```php
            $unit_label = NAWS_Calc::unit_for( $key );
            $unit_str   = ( $atts['unit'] !== '0' && $unit_label !== '' ) ? ' ' . $unit_label : '';
```

Damit hängt die Einheit nicht mehr daran, ob ein `param` gesetzt ist.

- [ ] **Step 5: `raw()` in eine Weiche verwandeln**

In `includes/class-naws-calc.php` den Kopf von `raw()` durch Folgendes ersetzen, und den kompletten bisherigen Rumpf (Modulauflösung, `$temp`, `$hum`, den ganzen `switch` samt abschließendem `return null;`) unverändert in die neue Methode `raw_instant()` verschieben:

```php
    /**
     * The raw value behind a catalogue key.
     *
     * Dispatches on the entry's kind. Each kind reads different sources and
     * honours different attributes, so keeping them in one switch would mean
     * every branch paying for every other branch's setup — an instant value
     * needs a current reading, a day class needs a range of daily rows.
     *
     * @return float|string|null null means "the data does not support this".
     */
    public static function raw( string $key, array $atts ) {
        if ( ! self::has( $key ) ) {
            static $logged = [];
            if ( ! isset( $logged[ $key ] ) ) {
                $logged[ $key ] = true;
                NAWS_Logger::warning( 'calc', 'Unknown [naws_calc] value key: ' . $key );
            }
            return null;
        }

        switch ( self::catalogue()[ $key ]['kind'] ) {
            case 'instant':
                return self::raw_instant( $key, $atts );
        }

        return null;
    }

    /**
     * Values that follow from the current reading or the station location.
     */
    private static function raw_instant( string $key, array $atts ) {
        $module = self::module_id( (string) ( $atts['module'] ?? 'outdoor' ) );
        $temp   = self::reading( $module, 'Temperature' );
        $hum    = self::reading( $module, 'Humidity' );

        switch ( $key ) {
            // … der unveränderte Stufe-1-switch …
        }

        return null;
    }
```

**Die eifrigen Abfragen sind damit erledigt:** `$module`, `$temp` und `$hum` liegen jetzt in `raw_instant()` und werden von Tagesklassen und Summen nicht mehr angefasst.

- [ ] **Step 6: Alles muss unverändert funktionieren**

Run: `for t in tests/test-*.php; do echo -n "$(basename $t): "; php "$t" | grep -E "bestanden|Szenarien" | tail -1; done; php tests/smoke-render-inline.php | tail -2 | head -1`
Expected: alle grün, `test-calc-catalogue.php` bei 170.

Run: `php -l includes/class-naws-calc.php includes/class-naws-shortcodes.php && php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-calc.php includes/class-naws-shortcodes.php`
Expected: fehlerfrei, 0 Befunde.

- [ ] **Step 7: Commit**

```bash
git add includes/class-naws-calc.php includes/class-naws-shortcodes.php tests/test-calc-catalogue.php
git commit -m "Prepare the catalogue for values that are not sensor readings

Three groundwork changes, no behaviour change. Entries may now carry a
literal unit, because degree days and day counts have no sensor
parameter to borrow one from. raw() became a dispatch on kind, so the
twelve day-based values arriving next do not join a switch that already
runs ninety lines. And the eager Temperature/Humidity lookups moved
into raw_instant(), which is the only kind that needs them."
```

---

### Task 2: NAWS_Climate — die reine Mathematik

Die Rechenschicht, vollständig testgetrieben. Kein WordPress, keine Datenbank, keine Uhr.

**Files:**
- Create: `includes/class-naws-climate.php`
- Modify: `xtx-integration-for-netatmo.php` (Ladeliste)
- Test: `tests/test-climate-indices.php` (neu)

**Interfaces:**
- Consumes: nichts
- Produces (alle `public static`, alle rein):
  - `NAWS_Climate::count_days( array $rows, callable $matches ): int`
  - `NAWS_Climate::max_streak( array $rows, callable $matches ): int`
  - `NAWS_Climate::current_streak( array $rows, callable $matches ): int`
  - `NAWS_Climate::degree_days( array $rows, float $threshold, float $reference, string $direction ): float`
  - `NAWS_Climate::growing_degree_days( array $rows, float $base, float $cap ): float`
  - `NAWS_Climate::grassland_sum( array $rows ): float`
  - `NAWS_Climate::grassland_start( array $rows ): ?string`

**Zeilenformat, das alle sieben erwarten:** eine aufsteigend nach `day_date` sortierte Liste von Arrays mit `'day_date' => 'Y-m-d'` und den benötigten Feldern (`temp_min`, `temp_max`, `temp_avg`) als `float` oder `null`.

- [ ] **Step 1: Testdatei schreiben**

Create `tests/test-climate-indices.php`:

```php
<?php
/**
 * Tests for the climate arithmetic in NAWS_Climate.
 *
 * Every function under test is pure — it receives finished daily rows and
 * returns a number. No options, no database, no clock, so this runs without
 * a WordPress bootstrap.
 *
 * The rule these tests exist to pin down: a MISSING DAY BREAKS A STREAK.
 * Three frost days, a gap, two more frost days is 3 and 2 — never 5. The
 * cautious reading, because nothing is known about a day nobody measured.
 *
 *   php tests/test-climate-indices.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-naws-climate.php';

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

function close( string $name, float $got, float $want, float $tol = 0.001 ): void {
    global $passed, $failed;
    if ( abs( $got - $want ) <= $tol ) {
        $passed++;
        return;
    }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %.4f (+-%.4f), ist %.4f\n", $name, $want, $tol, $got );
}

/** Baut Tageszeilen aus [ 'Y-m-d' => [ min, max, avg ] ]. */
function rows( array $spec ): array {
    $out = [];
    foreach ( $spec as $date => $v ) {
        $out[] = [
            'day_date' => $date,
            'temp_min' => $v[0],
            'temp_max' => $v[1],
            'temp_avg' => $v[2],
        ];
    }
    return $out;
}

$frost = function ( array $r ): bool {
    return $r['temp_min'] !== null && $r['temp_min'] < 0.0;
};

echo "\nNAWS_Climate::count_days()\n" . str_repeat( '-', 74 ) . "\n";

$w = rows( [
    '2026-01-01' => [ -3.0, 2.0, -0.5 ],
    '2026-01-02' => [ -1.0, 4.0,  1.5 ],
    '2026-01-03' => [  1.0, 6.0,  3.5 ],
    '2026-01-04' => [ -2.0, 1.0, -0.5 ],
] );
check( 'drei Frosttage von vier', NAWS_Climate::count_days( $w, $frost ), 3 );
check( 'leere Liste ergibt 0',    NAWS_Climate::count_days( [], $frost ), 0 );
check( 'kein Treffer ergibt 0',   NAWS_Climate::count_days( rows( [ '2026-07-01' => [ 12.0, 25.0, 18.0 ] ] ), $frost ), 0 );

// Ein null-Feld ist kein Treffer, aber auch kein Fehler.
check( 'null-Minimum zaehlt nicht', NAWS_Climate::count_days( rows( [ '2026-01-01' => [ null, 3.0, 1.0 ] ] ), $frost ), 0 );

echo "\nNAWS_Climate::max_streak() — die Lueckenregel\n" . str_repeat( '-', 74 ) . "\n";

// Drei Frosttage, fehlender 4. Januar, zwei Frosttage: 3 und 2, nicht 5.
$luecke = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [ -4.0, -1.0, -2.5 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-05' => [ -5.0, -2.0, -3.5 ],
    '2026-01-06' => [ -1.0, -0.2, -0.6 ],
] );
check( 'Luecke bricht die Serie', NAWS_Climate::max_streak( $luecke, $frost ), 3 );

$durchgehend = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [ -4.0, -1.0, -2.5 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-04' => [ -5.0, -2.0, -3.5 ],
    '2026-01-05' => [ -1.0, -0.2, -0.6 ],
] );
check( 'ohne Luecke fuenf am Stueck', NAWS_Climate::max_streak( $durchgehend, $frost ), 5 );

// Ueber den Jahreswechsel muss die Serie halten.
$jahreswechsel = rows( [
    '2025-12-30' => [ -2.0, -0.5, -1.0 ],
    '2025-12-31' => [ -3.0, -1.0, -2.0 ],
    '2026-01-01' => [ -4.0, -1.5, -2.5 ],
    '2026-01-02' => [ -1.0, -0.2, -0.5 ],
] );
check( 'Serie ueber den Jahreswechsel', NAWS_Climate::max_streak( $jahreswechsel, $frost ), 4 );

// Ein nicht passender Tag mittendrin bricht ebenfalls.
$unterbrochen = rows( [
    '2026-01-01' => [ -3.0, -1.0, -2.0 ],
    '2026-01-02' => [  1.0,  5.0,  3.0 ],
    '2026-01-03' => [ -2.0, -0.5, -1.2 ],
    '2026-01-04' => [ -2.0, -0.5, -1.2 ],
] );
check( 'Tauwetter bricht die Serie', NAWS_Climate::max_streak( $unterbrochen, $frost ), 2 );

check( 'ein einzelner Tag',   NAWS_Climate::max_streak( rows( [ '2026-01-01' => [ -3.0, -1.0, -2.0 ] ] ), $frost ), 1 );
check( 'leere Liste ergibt 0', NAWS_Climate::max_streak( [], $frost ), 0 );

echo "\nNAWS_Climate::current_streak()\n" . str_repeat( '-', 74 ) . "\n";

// Zaehlt vom Ende der Liste rueckwaerts.
check( 'laufende Serie am Ende',  NAWS_Climate::current_streak( $luecke, $frost ), 2 );
check( 'zwei Frosttage am Ende',  NAWS_Climate::current_streak( $unterbrochen, $frost ), 2 );
check( 'Tauwetter davor bricht ab', NAWS_Climate::current_streak( $w, $frost ), 1 );
check( 'leere Liste ergibt 0',    NAWS_Climate::current_streak( [], $frost ), 0 );

echo "\nNAWS_Climate::degree_days()\n" . str_repeat( '-', 74 ) . "\n";

$heiz = rows( [
    '2026-01-01' => [ -3.0, 2.0, 10.0 ],   // Heiztag: 20 - 10 = 10
    '2026-01-02' => [ -1.0, 8.0, 14.0 ],   // Heiztag: 20 - 14 =  6
    '2026-01-03' => [  8.0, 22.0, 16.0 ],  // kein Heiztag (16 >= 15)
] );
close( 'Heizgradtage 15/20',  NAWS_Climate::degree_days( $heiz, 15.0, 20.0, 'heating' ), 16.0 );
close( 'Heizgradtage 12/20',  NAWS_Climate::degree_days( $heiz, 12.0, 20.0, 'heating' ), 10.0 );

$kuehl = rows( [
    '2026-07-01' => [ 18.0, 30.0, 24.0 ],  // Kuehltag: 24 - 18 = 6
    '2026-07-02' => [ 14.0, 22.0, 17.0 ],  // kein Kuehltag (17 <= 18)
    '2026-07-03' => [ 20.0, 34.0, 27.0 ],  // Kuehltag: 27 - 18 = 9
] );
close( 'Kuehlgradtage Grenze 18', NAWS_Climate::degree_days( $kuehl, 18.0, 18.0, 'cooling' ), 15.0 );

close( 'leere Liste ergibt 0.0', NAWS_Climate::degree_days( [], 15.0, 20.0, 'heating' ), 0.0 );
close( 'null-Mittel wird uebersprungen',
    NAWS_Climate::degree_days( rows( [ '2026-01-01' => [ null, null, null ] ] ), 15.0, 20.0, 'heating' ), 0.0 );

echo "\nNAWS_Climate::growing_degree_days()\n" . str_repeat( '-', 74 ) . "\n";

// (min(Tmax,cap) + Tmin)/2 - Basis, negative Beitraege auf 0.
$wachstum = rows( [
    '2026-05-01' => [ 8.0,  20.0, 14.0 ],  // (20+8)/2 - 10 = 4
    '2026-05-02' => [ 7.0,  13.0, 10.0 ],  // (13+7)/2 - 10 = 0
    '2026-05-03' => [ 4.0,  12.0,  8.0 ],  // (12+4)/2 - 10 = -2 -> 0
    '2026-05-04' => [ 18.0, 36.0, 27.0 ],  // Kappung: (30+18)/2 - 10 = 14
] );
close( 'Basis 10, Kappung 30', NAWS_Climate::growing_degree_days( $wachstum, 10.0, 30.0 ), 18.0 );
close( 'ohne Kappung waere es mehr', NAWS_Climate::growing_degree_days( $wachstum, 10.0, 99.0 ), 21.0 );
close( 'Basis 5 statt 10',     NAWS_Climate::growing_degree_days( $wachstum, 5.0, 30.0 ), 36.0 );
close( 'leere Liste ergibt 0.0', NAWS_Climate::growing_degree_days( [], 10.0, 30.0 ), 0.0 );

echo "\nNAWS_Climate::grassland_sum() — Monatsgewichte\n" . str_repeat( '-', 74 ) . "\n";

// Januar x0,5 · Februar x0,75 · ab Maerz x1,0 · nur Mittel ueber 0.
$gruen = rows( [
    '2026-01-10' => [ 0.0, 8.0, 4.0 ],    // 4 * 0,5  = 2,0
    '2026-01-11' => [ -5.0, -1.0, -3.0 ], // <= 0 -> faellt weg
    '2026-02-10' => [ 2.0, 10.0, 8.0 ],   // 8 * 0,75 = 6,0
    '2026-03-10' => [ 4.0, 14.0, 9.0 ],   // 9 * 1,0  = 9,0
    '2026-04-10' => [ 6.0, 18.0, 12.0 ],  // 12 * 1,0 = 12,0
] );
close( 'gewichtete Summe', NAWS_Climate::grassland_sum( $gruen ), 29.0 );
close( 'genau 0 Grad zaehlt nicht',
    NAWS_Climate::grassland_sum( rows( [ '2026-03-01' => [ -2.0, 2.0, 0.0 ] ] ) ), 0.0 );
close( 'leere Liste ergibt 0.0', NAWS_Climate::grassland_sum( [] ), 0.0 );

// Schaltjahr: der 29. Februar traegt das Februar-Gewicht.
close( 'Schaltjahr 29.02. mit 0,75',
    NAWS_Climate::grassland_sum( rows( [ '2024-02-29' => [ 0.0, 8.0, 4.0 ] ] ) ), 3.0 );

echo "\nNAWS_Climate::grassland_start()\n" . str_repeat( '-', 74 ) . "\n";

// Erst wenn die laufende Summe 200 ueberschreitet.
$lang = [];
foreach ( range( 1, 30 ) as $tag ) {
    $lang[ sprintf( '2026-03-%02d', $tag ) ] = [ 2.0, 14.0, 9.0 ]; // 9 pro Tag
}
// 9 * 22 = 198, 9 * 23 = 207 -> Ueberschreitung am 23. Maerz
check( 'Datum der Ueberschreitung', NAWS_Climate::grassland_start( rows( $lang ) ), '2026-03-23' );
check( 'unter 200 ergibt null',
    NAWS_Climate::grassland_start( rows( [ '2026-03-01' => [ 2.0, 14.0, 9.0 ] ] ) ), null );
check( 'leere Liste ergibt null', NAWS_Climate::grassland_start( [] ), null );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und Fehlschlag bestätigen**

Run: `php tests/test-climate-indices.php`
Expected: **Fatal error**, `Failed opening required .../class-naws-climate.php`.

- [ ] **Step 3: Die Klasse schreiben**

Create `includes/class-naws-climate.php`:

```php
<?php
/**
 * Climate indices over daily summary rows.
 *
 * Every function here is pure: it takes finished rows and returns a number.
 * No options, no database, no clock — which is what lets
 * tests/test-climate-indices.php run without a WordPress bootstrap, and
 * what lets a reviewer check the arithmetic against a textbook instead of
 * against a fixture.
 *
 * Rows arrive sorted ascending by day_date, shaped:
 *   [ 'day_date' => 'Y-m-d', 'temp_min' => ?float, 'temp_max' => ?float, 'temp_avg' => ?float ]
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Climate {

    /** Grassland temperature sum: the threshold that marks the start of the growing season. */
    const GRASSLAND_THRESHOLD = 200.0;

    /**
     * How many days match.
     */
    public static function count_days( array $rows, callable $matches ): int {
        $n = 0;
        foreach ( $rows as $row ) {
            if ( $matches( $row ) ) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Longest run of consecutive matching days.
     *
     * A missing calendar day breaks the run. That is the cautious reading:
     * nothing is known about a day nobody measured, so claiming it continued
     * a frost period would be an invention. Two frost days either side of a
     * data gap are two runs of two, not one run of four.
     */
    public static function max_streak( array $rows, callable $matches ): int {
        $best    = 0;
        $current = 0;
        $prev    = null;

        foreach ( $rows as $row ) {
            $date = $row['day_date'] ?? '';
            if ( ! $matches( $row ) ) {
                $current = 0;
                $prev    = $date;
                continue;
            }
            $current = ( $prev !== null && self::is_next_day( $prev, $date ) ) ? $current + 1 : 1;
            $best    = max( $best, $current );
            $prev    = $date;
        }

        return $best;
    }

    /**
     * Run of matching days ending on the last row.
     *
     * Counts backwards from the end of the range, so it answers "how many in
     * a row right now" for an open range and "how many in a row at the end"
     * for a closed one.
     */
    public static function current_streak( array $rows, callable $matches ): int {
        $n    = 0;
        $next = null;

        foreach ( array_reverse( $rows ) as $row ) {
            $date = $row['day_date'] ?? '';
            if ( ! $matches( $row ) ) {
                break;
            }
            if ( $next !== null && ! self::is_next_day( $date, $next ) ) {
                break;
            }
            $n++;
            $next = $date;
        }

        return $n;
    }

    /**
     * Heating or cooling degree days, in Kelvin-days.
     *
     * The two directions are not symmetric, and that is the standard, not an
     * oversight. Heating counts days below the heating threshold and sums the
     * distance from the ROOM temperature (20 °C), because that is the gap the
     * heating has to close. Cooling counts days above the cooling threshold
     * and sums the distance from that same THRESHOLD.
     *
     * @param float  $threshold Heating or cooling limit temperature.
     * @param float  $reference Room temperature; used by 'heating' only.
     * @param string $direction 'heating' or 'cooling'.
     */
    public static function degree_days( array $rows, float $threshold, float $reference, string $direction ): float {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $avg = $row['temp_avg'] ?? null;
            if ( $avg === null ) {
                continue;
            }
            $avg = (float) $avg;
            if ( $direction === 'cooling' ) {
                if ( $avg > $threshold ) {
                    $sum += $avg - $threshold;
                }
                continue;
            }
            if ( $avg < $threshold ) {
                $sum += $reference - $avg;
            }
        }
        return $sum;
    }

    /**
     * Growing degree days (simple average method).
     *
     * WGT = (min(Tmax, cap) + Tmin) / 2 - base, negative contributions clipped
     * to zero. Base and cap are crop-dependent — 10 °C and 30 °C are the usual
     * pair, but the caller decides.
     */
    public static function growing_degree_days( array $rows, float $base, float $cap ): float {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $min = $row['temp_min'] ?? null;
            $max = $row['temp_max'] ?? null;
            if ( $min === null || $max === null ) {
                continue;
            }
            $mean = ( min( (float) $max, $cap ) + (float) $min ) / 2.0;
            $sum += max( 0.0, $mean - $base );
        }
        return $sum;
    }

    /**
     * Grassland temperature sum.
     *
     * Sums daily means above 0 °C from the first of January, weighting
     * January by 0.5 and February by 0.75 — early warmth is worth less to
     * grassland than the same warmth in spring.
     */
    public static function grassland_sum( array $rows ): float {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $sum += self::grassland_contribution( $row );
        }
        return $sum;
    }

    /**
     * The day the grassland sum first passed 200 °C, or null if it has not.
     *
     * That crossing is the point of the index: it marks the sustained start
     * of the growing season. A sum below it is not a broken value, only a
     * season that has not started.
     */
    public static function grassland_start( array $rows ): ?string {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $sum += self::grassland_contribution( $row );
            if ( $sum > self::GRASSLAND_THRESHOLD ) {
                return isset( $row['day_date'] ) ? (string) $row['day_date'] : null;
            }
        }
        return null;
    }

    /**
     * One day's weighted contribution to the grassland sum.
     */
    private static function grassland_contribution( array $row ): float {
        $avg = $row['temp_avg'] ?? null;
        if ( $avg === null || (float) $avg <= 0.0 ) {
            return 0.0;
        }
        $month  = (int) substr( (string) ( $row['day_date'] ?? '' ), 5, 2 );
        $weight = 1.0;
        if ( $month === 1 ) {
            $weight = 0.5;
        } elseif ( $month === 2 ) {
            $weight = 0.75;
        }
        return (float) $avg * $weight;
    }

    /**
     * Is $b the calendar day directly after $a? Both 'Y-m-d'.
     *
     * Uses DateTimeImmutable rather than string arithmetic so month ends,
     * year ends and leap days are the calendar's problem, not ours.
     */
    private static function is_next_day( string $a, string $b ): bool {
        $da = DateTimeImmutable::createFromFormat( 'Y-m-d', $a, new DateTimeZone( 'UTC' ) );
        $db = DateTimeImmutable::createFromFormat( 'Y-m-d', $b, new DateTimeZone( 'UTC' ) );
        if ( ! $da || ! $db ) {
            return false;
        }
        return $da->modify( '+1 day' )->format( 'Y-m-d' ) === $db->format( 'Y-m-d' );
    }
}
```

**Warum `DateTimeZone('UTC')` und nicht `naws_timezone()`:** Hier wird nur geprüft, ob zwei Kalendertage aufeinanderfolgen. Beide Werte sind reine Datumsangaben ohne Uhrzeit; eine feste Zone hält die Funktion rein und uhrfrei. Die Zeitzone entscheidet weiter oben, welche Tage überhaupt in den Zeitraum fallen.

- [ ] **Step 4: Test laufen lassen**

Run: `php tests/test-climate-indices.php | grep -E "bestanden|FAIL"`
Expected: `30 bestanden, 0 fehlgeschlagen` (4 count_days, 6 max_streak, 4 current_streak, 5 degree_days, 4 growing, 4 grassland_sum, 3 grassland_start).

- [ ] **Step 5: Klasse in die Ladeliste eintragen**

In `xtx-integration-for-netatmo.php` direkt nach der Zeile mit `class-naws-calc.php` einfügen:

```php
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-climate.php' );
```

- [ ] **Step 6: Gate und Commit**

```bash
php -l includes/class-naws-climate.php tests/test-climate-indices.php xtx-integration-for-netatmo.php
php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-climate.php xtx-integration-for-netatmo.php
git add includes/class-naws-climate.php tests/test-climate-indices.php xtx-integration-for-netatmo.php
git commit -m "Climate indices as pure arithmetic over daily rows

Seven functions, no options, no database, no clock — so the tests run
without WordPress and the formulas can be checked against their sources
rather than against fixtures.

The rule worth naming: a missing calendar day breaks a streak. Nothing
is known about a day nobody measured, so three frost days either side of
a gap are two runs, never one run of six."
```

---

### Task 3: Einstellungen für die Grenztemperaturen

Drei Felder, weil sie **vom Land abhängen** (Spec §9) und sonst an jedem Shortcode wiederholt werden müssten.

**Files:**
- Modify: `admin/views/settings.php` (Abschnitt „Betrieb", hinter dem Nachtmodus)
- Modify: `includes/class-naws-admin.php` (`sanitize_settings()`)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`

**Interfaces:**
- Consumes: nichts aus Task 1/2
- Produces: `naws_settings['heating_limit']` (float, Vorgabe 15.0), `['room_temp']` (float, 20.0), `['cooling_limit']` (float, 18.0)

- [ ] **Step 1: Sprachschlüssel in allen drei Dateien ergänzen**

Hinter dem `sc_calc_*`-Block in `languages/de.php`:

```php
    // Grenztemperaturen für die Gradtag-Kennzahlen
    'deg_limits'        => 'Grenztemperaturen',
    'heating_limit'     => 'Heizgrenztemperatur (°C)',
    'heating_limit_desc'=> 'Unterhalb dieses Tagesmittels gilt ein Tag als Heiztag. Deutschland 15 °C (VDI 2067), Österreich und Schweiz 12 °C.',
    'room_temp'         => 'Raumtemperatur (°C)',
    'room_temp_desc'    => 'Bezugstemperatur der Heizgradtage. In allen genannten Normen 20 °C.',
    'cooling_limit'     => 'Kühlgrenztemperatur (°C)',
    'cooling_limit_desc'=> 'Oberhalb dieses Tagesmittels gilt ein Tag als Kühltag. Hier gibt es keinen einheitlichen Standard — 18 °C und 21 °C sind beide gebräuchlich.',
```

`languages/en.php`:

```php
    // Limit temperatures for the degree-day indices
    'deg_limits'        => 'Limit temperatures',
    'heating_limit'     => 'Heating limit (°C)',
    'heating_limit_desc'=> 'A day counts as a heating day when its mean falls below this. Germany 15 °C (VDI 2067), Austria and Switzerland 12 °C.',
    'room_temp'         => 'Room temperature (°C)',
    'room_temp_desc'    => 'Reference temperature for heating degree days. 20 °C in every standard named here.',
    'cooling_limit'     => 'Cooling limit (°C)',
    'cooling_limit_desc'=> 'A day counts as a cooling day when its mean rises above this. There is no single standard here — 18 °C and 21 °C are both in common use.',
```

`languages/no.php`:

```php
    // Grensetemperaturer for graddag-tallene
    'deg_limits'        => 'Grensetemperaturer',
    'heating_limit'     => 'Fyringsgrense (°C)',
    'heating_limit_desc'=> 'En dag regnes som fyringsdag når døgnmiddelet faller under denne. Tyskland 15 °C (VDI 2067), Østerrike og Sveits 12 °C.',
    'room_temp'         => 'Romtemperatur (°C)',
    'room_temp_desc'    => 'Referansetemperatur for fyringsgraddager. 20 °C i alle standardene som er nevnt her.',
    'cooling_limit'     => 'Kjølegrense (°C)',
    'cooling_limit_desc'=> 'En dag regnes som kjøledag når døgnmiddelet stiger over denne. Her finnes ingen enhetlig standard — både 18 °C og 21 °C er i vanlig bruk.',
```

- [ ] **Step 2: Felder in die Einstellungsseite**

In `admin/views/settings.php`, im Abschnitt „Betrieb", **hinter** der Nachtmodus-Zeile (`night_mode_desc`) und **vor** der Datenhaltung, einfügen:

```php
                            <tr>
                                <th><?php naws_e( 'deg_limits' ); ?></th>
                                <td>
                                    <p>
                                        <label><?php naws_e( 'heating_limit' ); ?><br>
                                        <input type="number" step="0.5" min="-10" max="30" name="naws_settings[heating_limit]"
                                            value="<?php echo esc_attr( $options['heating_limit'] ?? 15 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php naws_e( 'heating_limit_desc' ); ?></p>
                                    <p>
                                        <label><?php naws_e( 'room_temp' ); ?><br>
                                        <input type="number" step="0.5" min="10" max="30" name="naws_settings[room_temp]"
                                            value="<?php echo esc_attr( $options['room_temp'] ?? 20 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php naws_e( 'room_temp_desc' ); ?></p>
                                    <p>
                                        <label><?php naws_e( 'cooling_limit' ); ?><br>
                                        <input type="number" step="0.5" min="0" max="40" name="naws_settings[cooling_limit]"
                                            value="<?php echo esc_attr( $options['cooling_limit'] ?? 18 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php naws_e( 'cooling_limit_desc' ); ?></p>
                                </td>
                            </tr>
```

- [ ] **Step 3: In `sanitize_settings()` aufnehmen**

In `includes/class-naws-admin.php`, bei den anderen `$sent( … )`-Zeilen des Betriebsabschnitts (neben `cron_interval` und `night_mode`):

```php
        if ( $sent( 'heating_limit' ) ) $clean['heating_limit'] = max( -10.0, min( 30.0, floatval( $input['heating_limit'] ) ) );
        if ( $sent( 'room_temp' ) )     $clean['room_temp']     = max(  10.0, min( 30.0, floatval( $input['room_temp'] ) ) );
        if ( $sent( 'cooling_limit' ) ) $clean['cooling_limit'] = max(   0.0, min( 40.0, floatval( $input['cooling_limit'] ) ) );
```

**Die Klemmung ist Absicht:** `sanitize_settings()` folgt hier der Merge-Semantik aus 1.7.0 — nur gesendete Schlüssel werden angefasst, damit das Speichern eines anderen Formulars diese Werte nicht zurücksetzt.

- [ ] **Step 4: Prüfen und committen**

Run: `for f in de en no; do echo -n "$f: "; grep -c "^\s*'" languages/$f.php; done`
Expected: dreimal dieselbe Zahl (641 + 7 = **648**).

Run: `php tests/test-settings-merge.php | grep -E "bestanden|Szenarien"` — die vorhandene Merge-Testdatei muss weiterhin durchlaufen.

```bash
php -l admin/views/settings.php includes/class-naws-admin.php languages/de.php languages/en.php languages/no.php
php vendor/bin/phpcs --standard=.phpcs.xml.dist admin/views/settings.php includes/class-naws-admin.php languages/de.php languages/en.php languages/no.php
git add admin/views/settings.php includes/class-naws-admin.php languages/
git commit -m "Limit temperatures for the degree-day indices are settings, not attributes

Heating limit, room temperature and cooling limit depend on the country,
not on the page: 15/20 in Germany, 12/20 in Austria and Switzerland. A
Swiss site would otherwise repeat base=\"12\" on every shortcode.

The cooling limit gets a default of 18 and a description saying plainly
that no single standard exists for it, rather than presenting one number
as authoritative."
```

---

### Task 4: Tagesklassen — Katalog, Zeitraum-Grammatik, Auflösung

Die sieben zählbaren, serienfähigen Werte.

**Files:**
- Modify: `includes/class-naws-calc.php`
- Modify: `includes/class-naws-shortcodes.php` (`sc_calc()`-Attribute)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`

**Interfaces:**
- Consumes: `NAWS_Climate::count_days()`, `max_streak()`, `current_streak()` (Task 2); `unit_for()`, `raw()`-Weiche (Task 1)
- Produces:
  - `NAWS_Calc::station_row_id( array $atts ): ?string`
  - `NAWS_Calc::period_range( array $atts ): array` → `[ 'from' => 'Y-m-d', 'to' => 'Y-m-d' ]`
  - `NAWS_Calc::daily_rows( array $atts, array $fields ): array`
  - `NAWS_Calc::raw_dayclass( string $key, array $atts )`
  - sieben Katalogeinträge mit `kind => 'dayclass'`

- [ ] **Step 1: Katalogeinträge ergänzen**

In `NAWS_Calc::catalogue()`, hinter dem Astronomie-Block:

```php
            // ── Tagesklassen aus der Tagestabelle ──────────────────────
            'ice_days'          => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_ice_days',        'field' => 'temp_max', 'op' => '<',  'threshold' => 0.0 ],
            'frost_days'        => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_frost_days',      'field' => 'temp_min', 'op' => '<',  'threshold' => 0.0 ],
            'summer_days'       => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_summer_days',     'field' => 'temp_max', 'op' => '>=', 'threshold' => 25.0 ],
            'hot_days'          => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_hot_days',        'field' => 'temp_max', 'op' => '>=', 'threshold' => 30.0 ],
            'tropical_nights'   => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_tropical_nights', 'field' => 'temp_min', 'op' => '>=', 'threshold' => 20.0 ],
            'heating_days'      => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_heating_days',    'field' => 'temp_avg', 'op' => '<',  'threshold' => null ],
            'cooling_days'      => [ 'kind' => 'dayclass', 'param' => null, 'unit' => '', 'decimals' => 0, 'label' => 'calc_cooling_days',    'field' => 'temp_avg', 'op' => '>',  'threshold' => null ],
```

`'threshold' => null` heißt: aus den Einstellungen holen (Heiz- bzw. Kühlgrenze). Die Zählwerte tragen `'unit' => ''`, weil eine Anzahl von Tagen keine Einheit hat — der leere String macht das ausdrücklich statt es dem Zufall zu überlassen.

- [ ] **Step 2: Sprachschlüssel in allen drei Dateien**

`de.php`:

```php
    'calc_ice_days'        => 'Eistage',
    'calc_frost_days'      => 'Frosttage',
    'calc_summer_days'     => 'Sommertage',
    'calc_hot_days'        => 'Heiße Tage',
    'calc_tropical_nights' => 'Tropennächte',
    'calc_heating_days'    => 'Heiztage',
    'calc_cooling_days'    => 'Kühltage',
```

`en.php`:

```php
    'calc_ice_days'        => 'Ice days',
    'calc_frost_days'      => 'Frost days',
    'calc_summer_days'     => 'Summer days',
    'calc_hot_days'        => 'Hot days',
    'calc_tropical_nights' => 'Tropical nights',
    'calc_heating_days'    => 'Heating days',
    'calc_cooling_days'    => 'Cooling days',
```

`no.php`:

```php
    'calc_ice_days'        => 'Isdager',
    'calc_frost_days'      => 'Frostdager',
    'calc_summer_days'     => 'Sommerdager',
    'calc_hot_days'        => 'Hete dager',
    'calc_tropical_nights' => 'Tropenetter',
    'calc_heating_days'    => 'Fyringsdager',
    'calc_cooling_days'    => 'Kjøledager',
```

- [ ] **Step 3: Zeitraum und Zeilenbeschaffung in `NAWS_Calc`**

Hinter `wind_kmh()` einfügen:

```php
    /**
     * The module_id of the station row in naws_daily_summary.
     *
     * Measured on a real installation: the daily table holds rows for the
     * station (NAMain) and for indoor modules only — outdoor, wind and rain
     * modules have no row of their own. compute_daily_summary() writes the
     * station aggregates under the station_id, so outdoor temperatures and
     * rain both live on the station row. Reading "the outdoor module" here
     * would return nothing at all.
     */
    private static function station_row_id( array $atts ): ?string {
        $wanted = isset( $atts['station'] ) ? sanitize_text_field( (string) $atts['station'] ) : '';
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            if ( $m['module_type'] !== 'NAMain' ) {
                continue;
            }
            if ( $wanted === '' || $m['module_id'] === $wanted || $m['station_id'] === $wanted ) {
                return $m['module_id'];
            }
        }
        return null;
    }

    /**
     * Resolve period/year attributes into a date range, in the site timezone.
     *
     * @return array{from:string,to:string} Both 'Y-m-d'.
     */
    private static function period_range( array $atts ): array {
        $today = wp_date( 'Y-m-d' );

        $year = isset( $atts['year'] ) ? intval( $atts['year'] ) : 0;
        if ( $year >= 1900 && $year <= 2999 ) {
            return [ 'from' => sprintf( '%04d-01-01', $year ), 'to' => sprintf( '%04d-12-31', $year ) ];
        }

        $period = strtolower( (string) ( $atts['period'] ?? 'year' ) );

        if ( $period === 'all' ) {
            return [ 'from' => '1900-01-01', 'to' => $today ];
        }
        if ( $period === 'month' ) {
            return [ 'from' => wp_date( 'Y-m-01' ), 'to' => $today ];
        }
        if ( preg_match( '/^(\d+)d$/', $period, $m ) ) {
            $days = max( 1, intval( $m[1] ) );
            return [ 'from' => wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ), 'to' => $today ];
        }

        // 'year' — the running calendar year, and the default.
        return [ 'from' => wp_date( 'Y-01-01' ), 'to' => $today ];
    }

    /**
     * Daily rows of the station for the requested period.
     *
     * Deliberately reuses NAWS_Database::get_daily_summaries() rather than
     * querying here: it already selects by date range and module, sorts
     * ascending, and carries its own transient cache. Ten shortcodes on one
     * page therefore cost one query, not ten.
     */
    private static function daily_rows( array $atts, array $fields ): array {
        $station = self::station_row_id( $atts );
        if ( $station === null ) {
            return [];
        }
        $range = self::period_range( $atts );

        return NAWS_Database::get_daily_summaries( [
            'module_id' => $station,
            'date_from' => $range['from'],
            'date_to'   => $range['to'],
            'fields'    => $fields,
            'group_by'  => 'day',
        ] );
    }

    /**
     * Build the matcher for a day class from its catalogue metadata.
     *
     * A null threshold means "take it from the settings" — that is how the
     * heating and cooling limits stay country-configurable without every
     * shortcode repeating them.
     */
    private static function day_matcher( array $entry ): callable {
        $field = (string) $entry['field'];
        $op    = (string) $entry['op'];
        $limit = $entry['threshold'];

        if ( $limit === null ) {
            $opts  = get_option( 'naws_settings', [] );
            $limit = ( $op === '>' )
                ? floatval( $opts['cooling_limit'] ?? 18.0 )
                : floatval( $opts['heating_limit'] ?? 15.0 );
        }
        $limit = (float) $limit;

        return static function ( array $row ) use ( $field, $op, $limit ): bool {
            $v = $row[ $field ] ?? null;
            if ( $v === null ) {
                return false;
            }
            $v = (float) $v;
            if ( $op === '<' )  return $v <  $limit;
            if ( $op === '>' )  return $v >  $limit;
            if ( $op === '>=' ) return $v >= $limit;
            return false;
        };
    }
```

- [ ] **Step 4: `raw_dayclass()` und die Weiche**

Hinter `raw_instant()` einfügen:

```php
    /**
     * Day classes: countable and streakable over a range of daily rows.
     */
    private static function raw_dayclass( string $key, array $atts ) {
        $entry = self::catalogue()[ $key ];
        $rows  = self::daily_rows( $atts, [ 'temp_min', 'temp_max', 'temp_avg' ] );

        // "No data" and "no such days" must not look alike: an empty range
        // gives the fallback, a range with rows and no hits gives 0.
        if ( empty( $rows ) ) {
            return null;
        }

        $matches = self::day_matcher( $entry );
        $mode    = strtolower( (string) ( $atts['mode'] ?? 'count' ) );

        if ( $mode === 'streak' ) {
            return (float) NAWS_Climate::current_streak( $rows, $matches );
        }
        if ( $mode === 'max_streak' ) {
            return (float) NAWS_Climate::max_streak( $rows, $matches );
        }
        return (float) NAWS_Climate::count_days( $rows, $matches );
    }
```

Und in `raw()` die Weiche erweitern:

```php
            case 'dayclass':
                return self::raw_dayclass( $key, $atts );
```

- [ ] **Step 5: Die neuen Attribute in `sc_calc()`**

In `includes/class-naws-shortcodes.php`, `shortcode_atts()` in `sc_calc()` um fünf Einträge erweitern:

```php
            'station'  => '',
            'period'   => 'year',
            'year'     => '',
            'mode'     => 'count',
            'note'     => '0',
```

Und beim Aufruf von `NAWS_Calc::raw()` weiterreichen:

```php
        $raw = NAWS_Calc::raw( $key, [
            'module'  => sanitize_text_field( $atts['module'] ),
            'station' => sanitize_text_field( $atts['station'] ),
            'period'  => sanitize_text_field( $atts['period'] ),
            'year'    => sanitize_text_field( $atts['year'] ),
            'mode'    => sanitize_key( $atts['mode'] ),
        ] );
```

`note` wird in Task 6 ausgewertet; es hier schon zu deklarieren verhindert, dass WordPress es als unbekanntes Attribut verwirft.

- [ ] **Step 6: Prüfen**

Run: `for t in tests/test-*.php; do echo -n "$(basename $t): "; php "$t" | grep -E "bestanden|Szenarien" | tail -1; done`
Expected: alle grün; `test-calc-catalogue.php` steigt auf **212** (21 Einträge × 4 Struktur- + 2 Unit-Prüfungen, plus 21 × 3 Sprachprüfungen und die Rahmenprüfungen — maßgeblich ist `0 fehlgeschlagen`, nicht die genaue Zahl).

Run: `for f in de en no; do echo -n "$f: "; grep -c "^\s*'" languages/$f.php; done`
Expected: dreimal **655** (648 + 7).

```bash
php -l includes/class-naws-calc.php includes/class-naws-shortcodes.php languages/de.php languages/en.php languages/no.php
php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-calc.php includes/class-naws-shortcodes.php languages/de.php languages/en.php languages/no.php
```

- [ ] **Step 7: Commit**

```bash
git add includes/class-naws-calc.php includes/class-naws-shortcodes.php languages/
git commit -m "Day classes: seven countable, streakable values over the daily table

Reads the station row, not the outdoor module — measured on a real
install, the daily table keeps no rows for outdoor, wind or rain
modules; compute_daily_summary() writes the station aggregates under
the station_id.

An empty range returns the fallback while a range with rows and no hits
returns 0. A frost-free winter and a winter nobody measured must not
look the same."
```

---

### Task 5: Summen — Gradtage und Grünlandtemperatursumme

**Files:**
- Modify: `includes/class-naws-calc.php`
- Modify: `includes/class-naws-shortcodes.php` (`base`- und `cap`-Attribut)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`

**Interfaces:**
- Consumes: `NAWS_Climate::degree_days()`, `growing_degree_days()`, `grassland_sum()`, `grassland_start()`; `daily_rows()`, `period_range()` (Task 4)
- Produces: fünf Katalogeinträge mit `kind => 'sum'`, `NAWS_Calc::raw_sum()`

- [ ] **Step 1: Katalogeinträge**

```php
            // ── Summenkennzahlen ──────────────────────────────────────
            'hdd'        => [ 'kind' => 'sum', 'param' => null, 'unit' => 'Kd', 'decimals' => 0, 'label' => 'calc_hdd' ],
            'cdd'        => [ 'kind' => 'sum', 'param' => null, 'unit' => 'Kd', 'decimals' => 0, 'label' => 'calc_cdd' ],
            'gdd'        => [ 'kind' => 'sum', 'param' => null, 'unit' => 'Kd', 'decimals' => 0, 'label' => 'calc_gdd' ],
            'glts'       => [ 'kind' => 'sum', 'param' => null, 'unit' => '°C', 'decimals' => 1, 'label' => 'calc_glts' ],
            'glts_start' => [ 'kind' => 'sum', 'param' => null, 'unit' => '',   'decimals' => 0, 'label' => 'calc_glts_start' ],
```

- [ ] **Step 2: Sprachschlüssel**

`de.php`:

```php
    'calc_hdd'            => 'Heizgradtage',
    'calc_cdd'            => 'Kühlgradtage',
    'calc_gdd'            => 'Wachstumsgradtage',
    'calc_glts'           => 'Grünlandtemperatursumme',
    'calc_glts_start'     => 'Vegetationsbeginn',
    'calc_glts_pending'   => 'noch nicht erreicht',
```

`en.php`:

```php
    'calc_hdd'            => 'Heating degree days',
    'calc_cdd'            => 'Cooling degree days',
    'calc_gdd'            => 'Growing degree days',
    'calc_glts'           => 'Grassland temperature sum',
    'calc_glts_start'     => 'Start of the growing season',
    'calc_glts_pending'   => 'not yet reached',
```

`no.php`:

```php
    'calc_hdd'            => 'Fyringsgraddager',
    'calc_cdd'            => 'Kjølegraddager',
    'calc_gdd'            => 'Veksstgraddager',
    'calc_glts'           => 'Grasmarkstemperatursum',
    'calc_glts_start'     => 'Vekstsesongens start',
    'calc_glts_pending'   => 'ikke nådd ennå',
```

- [ ] **Step 3: `raw_sum()`**

```php
    /**
     * Sum indices over a range of daily rows.
     */
    private static function raw_sum( string $key, array $atts ) {
        $opts = get_option( 'naws_settings', [] );

        // The grassland sum is defined as "since the first of January", so it
        // ignores period and honours only an explicit year.
        $sum_atts = $atts;
        if ( $key === 'glts' || $key === 'glts_start' ) {
            $sum_atts['period'] = 'year';
        }

        $rows = self::daily_rows( $sum_atts, [ 'temp_min', 'temp_max', 'temp_avg' ] );
        if ( empty( $rows ) ) {
            return null;
        }

        switch ( $key ) {
            case 'hdd':
                $limit = isset( $atts['base'] ) && $atts['base'] !== ''
                    ? floatval( $atts['base'] )
                    : floatval( $opts['heating_limit'] ?? 15.0 );
                return NAWS_Climate::degree_days( $rows, $limit, floatval( $opts['room_temp'] ?? 20.0 ), 'heating' );

            case 'cdd':
                $limit = isset( $atts['base'] ) && $atts['base'] !== ''
                    ? floatval( $atts['base'] )
                    : floatval( $opts['cooling_limit'] ?? 18.0 );
                return NAWS_Climate::degree_days( $rows, $limit, 0.0, 'cooling' );

            case 'gdd':
                $base = ( isset( $atts['base'] ) && $atts['base'] !== '' ) ? floatval( $atts['base'] ) : 10.0;
                $cap  = ( isset( $atts['cap'] )  && $atts['cap']  !== '' ) ? floatval( $atts['cap'] )  : 30.0;
                return NAWS_Climate::growing_degree_days( $rows, $base, $cap );

            case 'glts':
                return NAWS_Climate::grassland_sum( $rows );

            case 'glts_start':
                $date = NAWS_Climate::grassland_start( $rows );
                // A sum below 200 is a correct value, not a missing one — say
                // so in words rather than showing an empty field.
                return $date === null
                    ? naws__( 'calc_glts_pending' )
                    : wp_date( get_option( 'date_format', 'd.m.Y' ), strtotime( $date . ' 12:00:00' ) );
        }

        return null;
    }
```

Und in `raw()`:

```php
            case 'sum':
                return self::raw_sum( $key, $atts );
```

**Zur Uhrzeit `12:00:00` in `strtotime()`:** Ein reines Datum wird als Mitternacht interpretiert; bei Zeitzonenverschiebung nach Westen kann `wp_date()` daraus den Vortag machen. Die Mittagszeit ist gegen jede Verschiebung unter zwölf Stunden immun.

- [ ] **Step 4: `base` und `cap` in `sc_calc()`**

`shortcode_atts()` um zwei Einträge erweitern und im `raw()`-Aufruf weiterreichen:

```php
            'base'     => '',
            'cap'      => '',
```

```php
            'base'    => sanitize_text_field( $atts['base'] ),
            'cap'     => sanitize_text_field( $atts['cap'] ),
```

- [ ] **Step 5: Prüfen und committen**

Run: Testsuite komplett, Sprachdateien-Gleichstand (dreimal **661** = 655 + 6), `php -l`, PHPCS.

```bash
git add includes/class-naws-calc.php includes/class-naws-shortcodes.php languages/
git commit -m "Sum indices: degree days and the grassland temperature sum

Heating and cooling degree days are deliberately asymmetric — heating
sums the distance from room temperature, cooling from its own limit.
That is the standard, not an oversight, and the comment in
NAWS_Climate::degree_days() says so.

glts_start answers in words when the season has not started. 200 °C is
the agronomic meaning of the index, not a quality threshold, so a sum
below it deserves a sentence rather than an empty field."
```

---

### Task 6: Datengrundlage anzeigen und Abschluss

**Files:**
- Modify: `includes/class-naws-shortcodes.php` (`note`-Auswertung)
- Modify: `languages/de.php`, `languages/en.php`, `languages/no.php`
- Modify: `includes/class-naws-calc.php` (`coverage()`)

**Interfaces:**
- Consumes: `daily_rows()`, `period_range()` (Task 4)
- Produces: `NAWS_Calc::coverage( string $key, array $atts ): ?array` → `[ 'rows' => int, 'days' => int ]`

- [ ] **Step 1: Sprachschlüssel**

`de.php`: `'calc_note' => '(bei %1$d von %2$d Tagen)',`
`en.php`: `'calc_note' => '(from %1$d of %2$d days)',`
`no.php`: `'calc_note' => '(fra %1$d av %2$d dager)',`

- [ ] **Step 2: `coverage()` in `NAWS_Calc`**

```php
    /**
     * How much of the requested period actually carries data.
     *
     * Only meaningful for kinds that read the daily table; instant values
     * return null. Counting gaps is the honest way to publish a frost-day
     * total: 31 out of 31 days means something different from 31 out of 200.
     */
    public static function coverage( string $key, array $atts ): ?array {
        $entry = self::catalogue()[ $key ] ?? null;
        if ( $entry === null || $entry['kind'] === 'instant' ) {
            return null;
        }
        $rows  = self::daily_rows( $atts, [ 'temp_min', 'temp_max', 'temp_avg' ] );
        $range = self::period_range( $atts );

        $from = strtotime( $range['from'] . ' 12:00:00' );
        $to   = strtotime( $range['to'] . ' 12:00:00' );
        $days = ( $from && $to && $to >= $from ) ? intval( round( ( $to - $from ) / DAY_IN_SECONDS ) ) + 1 : 0;

        return [ 'rows' => count( $rows ), 'days' => $days ];
    }
```

`coverage()` ist `public`, ruft aber die `private static` Helfer `daily_rows()` und `period_range()` aus Task 4. Das ist zulässig, weil beide in derselben Klasse liegen — **keine Sichtbarkeit ändern.**

- [ ] **Step 3: `note` in `sc_calc()` auswerten**

Nach dem Bilden von `$output`, vor dem Verpacken in das Tag:

```php
        if ( $atts['note'] === '1' ) {
            $cov = NAWS_Calc::coverage( $key, [
                'station' => sanitize_text_field( $atts['station'] ),
                'period'  => sanitize_text_field( $atts['period'] ),
                'year'    => sanitize_text_field( $atts['year'] ),
            ] );
            if ( $cov !== null && $cov['days'] > 0 ) {
                $output .= ' ' . esc_html( sprintf( naws__( 'calc_note' ), $cov['rows'], $cov['days'] ) );
            }
        }
```

- [ ] **Step 4: Gesamtprüfung**

Run: `for t in tests/test-*.php; do echo -n "$(basename $t): "; php "$t" | grep -E "bestanden|Szenarien" | tail -1; done; php tests/smoke-render-inline.php | tail -2 | head -1`
Expected: alle grün, **0 fehlgeschlagen** überall.

Run: `php vendor/bin/phpcs --standard=.phpcs.xml.dist includes/class-naws-calc.php includes/class-naws-climate.php includes/class-naws-shortcodes.php includes/class-naws-admin.php admin/views/settings.php languages/de.php languages/en.php languages/no.php xtx-integration-for-netatmo.php`
Expected: **0 Befunde.**

Run: `for f in de en no; do echo -n "$f: "; grep -c "^\s*'" languages/$f.php; done`
Expected: dreimal dieselbe Zahl (**662**).

- [ ] **Step 5: Die Backend-Referenztabelle gegenprüfen**

Die Tabelle aus Stufe 1 läuft über `NAWS_Calc::catalogue()` und nimmt die zwölf neuen Einträge **von selbst** auf — es ist keine Änderung an `admin/views/shortcodes.php` nötig. Genau deshalb muss geprüft werden, dass sie nicht bricht:

Run: `php -r 'define("ABSPATH",__DIR__); require "includes/class-naws-calc.php"; $c = NAWS_Calc::catalogue(); echo count($c) . " Eintraege\n"; $k = array_count_values( array_column( $c, "kind" ) ); print_r($k);'`

Expected: **26 Einträge**, davon 14 `instant`, 7 `dayclass`, 5 `sum`. (27 werden es erst mit dem SPI in Stufe 3.)

Damit zeigt die Doku-Seite künftig für jeden neuen Wert auch seine aktuelle Ausgabe — inklusive `--` dort, wo die Installation die Daten nicht hat. Das ist die Anforderung aus Spec §8.3, ohne eine Zeile neuen Code.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-calc.php includes/class-naws-shortcodes.php languages/
git commit -m "note=\"1\" publishes how much of the period actually carries data

31 frost days out of 31 measured days means something different from 31
out of 200, and a bare number cannot tell them apart. Off by default so
running text stays clean."
```

---

## Abnahme gegen echte Zahlen

An der Referenzinstallation am 2026-08-18 gemessen (Stationszeile). Nach Task 4 müssen diese Shortcodes **exakt** diese Werte liefern:

| Shortcode | Erwartet |
|---|---|
| `[naws_calc value="frost_days" year="2025"]` | **40** |
| `[naws_calc value="ice_days" year="2025"]` | **4** |
| `[naws_calc value="summer_days" year="2025"]` | **54** |
| `[naws_calc value="hot_days" year="2025"]` | **17** |
| `[naws_calc value="tropical_nights" year="2025"]` | **5** |
| `[naws_calc value="frost_days" year="2024"]` | **8** |
| `[naws_calc value="ice_days" year="2024"]` | **0** |

Ein `--` bei `ice_days year="2024"` wäre ein Fehler: 2024 hat Zeilen, nur keine Eistage. Das ist genau die Unterscheidung aus den Global Constraints.

## Was dieser Plan nicht enthält

| Punkt | Wo es hingehört |
|---|---|
| SPI | Stufe 3 |
| Neue Lesemethode in `NAWS_Database` | Nicht nötig — `get_daily_summaries()` deckt es ab |
| Eigene Transient-Schicht | Nicht nötig — `TTL_DAILY` cached bereits die Zeilen |
| Gemeinsame Konstante für `TYPE_MAP` | Aufgeschobener Minor-Befund aus Stufe 1, eigene Aufräumaufgabe |
| Datumsformate in `NAWS_Astro` entdeutschen | Eigene Aufgabe, blockiert das Release |
| Versions-Bump und `CHANGELOG.md` | Release-Commit |
