# Rekorde, „An diesem Tag" und Sonnenbahn — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Drei neue Shortcodes: `[naws_records]` zeigt 15 Rekorde aus der Tagesübersicht, `[naws_on_this_day]` die Werte desselben Kalendertags aus früheren Jahren, `[naws_sunpath]` die Sonne auf ihrem Tagesbogen über der Station.

**Architecture:** Die Rekordrechnung ist eine reine Klasse `NAWS_Records` (Tageszeilen rein, Zahlen raus), die Stations- und Zeitraumlogik kommt unverändert aus `NAWS_Calc`, die Serienlogik aus `NAWS_Climate`. Die Sonnenbahn ist eine reine Funktion `NAWS_Astro::sun_path()` über PHPs `date_sun_info()`. Drei Templates rendern serverseitig, ohne JavaScript, mit den Theme-Variablen der Erscheinungsbild-Seite.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, kein Build-Schritt, keine neue Bibliothek. Tests sind eigenständige PHP-Dateien ohne Runner (`php tests/test-x.php`), wie die 30 vorhandenen.

**Spec:** `docs/superpowers/specs/2026-09-05-rekorde-sonnenbahn-design.md`

**Branch:** `records-sunpath` (existiert, ein Commit mit der Spec)

## Definition of Done — gilt für jeden Task ohne Ausnahme

Ein Task ist erst fertig, wenn **alle vier** Punkte erfüllt sind. Kein Commit ohne sie.

1. **Das Review-Gate steht auf null.**

   ```
   vendor\bin\phpcs.bat --report=full
   ```

   `.phpcs.xml.dist` ist das Gate, das über die Annahme bei WordPress.org entscheidet. Wer einen Befund hinterlässt, hat den Task nicht beendet.

2. **Ein `phpcs:ignore` wird begründet oder gar nicht gesetzt.** Keine eigene Escaping-Wrapper-Funktion; das Review-Team will `esc_html()`/`esc_attr()` im `echo` selbst sehen.

3. **Die ganze Testsuite ist grün.**

   ```
   for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done
   ```

   Erwartet wird keine Ausgabe.

4. **`php -l` auf jeder angefassten PHP-Datei.**

Wenn einer der vier Punkte nicht zu erfüllen ist, wird das gemeldet und **nicht** umgangen.

---

## Global Constraints

- **Textdomain ist immer `xtx-integration-for-netatmo`.**
- **Prefix `naws_` / `NAWS_`** für jede globale Funktion, Klasse, Konstante und Option. CSS-Klassen beginnen mit `naws-`.
- **Kein `<style>`- und kein `<script>`-Block in der Ausgabe.** Inline-SVG mit `style`-Attributen an Elementen ist zulässig; JavaScript gibt es in dieser Arbeit nicht.
- **Jede Ausgabe wird escaped** — `esc_html()`, `esc_attr()`, spät und sichtbar an der Ausgabestelle. Ohne Ausnahme. Im SVG gilt das für jeden berechneten Attributwert (`esc_attr()`) und jeden Text (`esc_html()`).
- **Keine Änderung an bestehender Ausgabe.** `max_streak()` liefert dieselbe Zahl, `get_daily_summaries()` dieselben Zeilen für bisherige Aufrufer. `test-calc-*.php` und `test-climate-indices.php` müssen unverändert grün bleiben.
- **Keine MAC-Adresse in öffentlicher Ausgabe.** Jeder Render-Test prüft es.
- **Beschriftungen nur über `naws_label()`** (`includes/class-naws-labels.php`), damit sie in `.pot`/`.po`/`.mo` landen. Kein `__()` direkt im Template — ausgenommen `_n()` für Einzahl/Mehrzahl, das `naws_label()` nicht kann.
- **Alle Zeiten in der Zeitzone der Site:** `wp_date()`, nie `date()`; in den reinen Klassen nur Unix-Zeitstempel und `Y-m-d`-Strings.
- **Voreinstellung der Rekorde ist `period = all`.** Ein Rekord ist einer seit Aufzeichnungsbeginn.
- **Dateien sind LF**, PHP ohne schließendes `?>` (außer Templates, die mit HTML enden).
- **Commits auf Englisch im Ton des Repos** — ein Satz, der benennt, was die Änderung bewirkt.

---

## Dateiübersicht

| Datei | Verantwortung |
| --- | --- |
| `includes/class-naws-climate.php` | `longest_run()` (neu), `max_streak()` ruft sie |
| `includes/class-naws-calc.php` | `station_row_id()`, `period_range()` werden `public` |
| `includes/class-naws-database.php` | `gust_max` in `$allowed_fields` |
| `includes/class-naws-records.php` | *neu* — Katalog, `compute()`, `all()`, `on_this_day()`, `delta_parts()`, `rows()` |
| `includes/class-naws-astro.php` | `sun_path()` (neu) |
| `includes/class-naws-shortcodes.php` | `sc_records()`, `sc_on_this_day()`, `sc_sunpath()`, Registrierung |
| `includes/class-naws-labels.php` | `rec_*`, `otd_*`, `sun_*` |
| `templates/records.php` | *neu* — Kacheln oder Tabelle, Fußzeile |
| `templates/on-this-day.php` | *neu* — Tabelle je Jahr |
| `templates/sunpath.php` | *neu* — SVG und Textzeile |
| `assets/css/frontend.css` | Blöcke `.naws-rec`, `.naws-otd`, `.naws-sun` |
| `admin/views/shortcodes.php`, `readme.txt`, `README.md`, `CHANGELOG.md`, `docs/site/website.{de,en}.json` | Dokumentation |
| `docs/i18n/catalog/*`, `languages/*` | Übersetzung |
| `tests/test-records.php` | *neu* — Rechnung |
| `tests/test-records-render.php` | *neu* — Templates |
| `tests/test-sunpath.php` | *neu* — Sonnenbahn |
| `tests/test-climate-indices.php` | `longest_run()` |

---

## Task 1: Serien mit Datum

**Files:**
- Modify: `includes/class-naws-climate.php` (`max_streak()` ~Zeile 50)
- Test: `tests/test-climate-indices.php` (am Ende, vor der Zusammenfassung)

**Interfaces:**
- Consumes: `NAWS_Climate::is_next_day( string $a, string $b ): bool` (private, in der Klasse)
- Produces: `NAWS_Climate::longest_run( array $rows, callable $matches ): ?array` — `[ 'length' => int, 'from' => 'Y-m-d', 'to' => 'Y-m-d' ]` oder `null`, wenn kein Tag trifft; bei Gleichstand die frühere Serie. `max_streak()` liefert weiter `int`, 0 ohne Treffer.

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

In `tests/test-climate-indices.php` vor der Zeile `echo "\n" . str_repeat( '-', 74 ) . "\n";` am Dateiende einfügen:

```php
echo "\nNAWS_Climate::longest_run()\n" . str_repeat( '-', 74 ) . "\n";

// Frosttage: 3.–5. Januar (drei), Lücke am 8., dann 9.–13. (fuenf).
$run_rows = [];
foreach ( [ '01-01' => 2, '01-02' => 1, '01-03' => -1, '01-04' => -2, '01-05' => -1, '01-06' => 1, '01-07' => 3,
            '01-09' => -1, '01-10' => -3, '01-11' => -2, '01-12' => -1, '01-13' => -1, '01-14' => 2 ] as $md => $tmin ) {
    $run_rows[] = [ 'day_date' => "2025-$md", 'temp_min' => $tmin ];
}
$frost = static fn( $r ) => $r['temp_min'] < 0;

$run = NAWS_Climate::longest_run( $run_rows, $frost );
check( 'laengste Serie: fuenf Tage',          $run['length'] ?? null, 5 );
check( 'sie beginnt am 9. Januar',            $run['from'] ?? null, '2025-01-09' );
check( 'und endet am 13.',                    $run['to'] ?? null, '2025-01-13' );
check( 'max_streak() sagt dieselbe Zahl',     NAWS_Climate::max_streak( $run_rows, $frost ), 5 );

// Gleichstand: zwei Serien zu je drei Tagen — die fruehere gewinnt.
$tie_rows = [];
foreach ( [ '02-01' => -1, '02-02' => -1, '02-03' => -1, '02-04' => 2, '02-05' => -1, '02-06' => -1, '02-07' => -1 ] as $md => $tmin ) {
    $tie_rows[] = [ 'day_date' => "2025-$md", 'temp_min' => $tmin ];
}
$tie = NAWS_Climate::longest_run( $tie_rows, $frost );
check( 'Gleichstand: die fruehere Serie',     $tie['from'] ?? null, '2025-02-01' );

// Eine Datenluecke bricht die Serie: 1., 2., (3. fehlt), 4., 5. → zwei Serien zu zwei.
$gap_rows = [];
foreach ( [ '03-01', '03-02', '03-04', '03-05' ] as $md ) {
    $gap_rows[] = [ 'day_date' => "2025-$md", 'temp_min' => -1 ];
}
$gap = NAWS_Climate::longest_run( $gap_rows, $frost );
check( 'Luecke bricht die Serie',             $gap['length'] ?? null, 2 );
check( 'Luecke: die erste Serie gewinnt',     $gap['from'] ?? null, '2025-03-01' );

check( 'ohne Treffer null',                   NAWS_Climate::longest_run( [ [ 'day_date' => '2025-04-01', 'temp_min' => 5 ] ], $frost ), null );
check( 'ohne Treffer bleibt max_streak 0',    NAWS_Climate::max_streak( [ [ 'day_date' => '2025-04-01', 'temp_min' => 5 ] ], $frost ), 0 );
check( 'ohne Zeilen null',                    NAWS_Climate::longest_run( [], $frost ), null );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-climate-indices.php`
Expected: `Fatal error: Uncaught Error: Call to undefined method NAWS_Climate::longest_run()`

- [ ] **Step 3: `longest_run()` schreiben und `max_streak()` darauf setzen**

In `includes/class-naws-climate.php` die bestehende `max_streak()` ersetzen durch:

```php
    /**
     * Longest run of consecutive matching days.
     *
     * A missing calendar day breaks the run. That is the cautious reading:
     * nothing is known about a day nobody measured, so claiming it continued
     * a frost period would be an invention. Two frost days either side of a
     * data gap are two runs of two, not one run of four.
     */
    public static function max_streak( array $rows, callable $matches ): int {
        $run = self::longest_run( $rows, $matches );
        return $run === null ? 0 : $run['length'];
    }

    /**
     * The longest run, with its dates.
     *
     * Same walk as max_streak() used to do on its own — one loop, two
     * answers, so the number on a [naws_calc] and the dates on a record can
     * never disagree. A tie goes to the earlier run: the comparison is
     * strict, and the rows are walked in date order.
     *
     * @return array{length:int,from:string,to:string}|null Null when no day matches.
     */
    public static function longest_run( array $rows, callable $matches ): ?array {
        $best    = null;
        $current = 0;
        $start   = null;
        $prev    = null;
        foreach ( $rows as $row ) {
            $date = (string) ( $row['day_date'] ?? '' );
            if ( ! $matches( $row ) ) {
                $current = 0;
                $prev    = $date;
                continue;
            }
            if ( $current > 0 && $prev !== null && self::is_next_day( $prev, $date ) ) {
                $current++;
            } else {
                $current = 1;
                $start   = $date;
            }
            if ( $best === null || $current > $best['length'] ) {
                $best = [ 'length' => $current, 'from' => $start, 'to' => $date ];
            }
            $prev = $date;
        }
        return $best;
    }
```

- [ ] **Step 4: Test laufen lassen**

Run: `php tests/test-climate-indices.php`
Expected: alle Prüfungen bestanden, darunter die neun neuen.

- [ ] **Step 5: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe. `test-calc-rows.php` und `test-calc-catalogue.php` prüfen `max_streak()` indirekt.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-climate.php tests/test-climate-indices.php
git commit -m "Let the climate class say when the longest run was, not only how long"
```

---

## Task 2: Die Zeilen, die die Rekorde brauchen

**Files:**
- Modify: `includes/class-naws-database.php` (`get_daily_summaries()`, `$allowed_fields` ~Zeile 815)
- Modify: `includes/class-naws-calc.php` (`station_row_id()` ~Zeile 202, `period_range()` ~Zeile 220)
- Create: `tests/test-records.php` (Anfang; die weiteren Tasks hängen ihre Abschnitte an)

**Interfaces:**
- Produces: `NAWS_Calc::station_row_id( array $atts ): ?string` und `NAWS_Calc::period_range( array $atts ): array{from:string,to:string}` sind `public static`; `get_daily_summaries()` akzeptiert `gust_max` in `fields`.

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

Neue Datei `tests/test-records.php`:

```php
<?php
/**
 * Tests fuer NAWS_Records — die Rekorde aus der Tagesuebersicht.
 *
 * Die Rechnung ist rein: Tageszeilen rein, Zahlen raus. Deshalb laeuft sie
 * hier auf einem handgebauten Jahr, in dem jeder Rekord an einem bekannten
 * Tag steht. Was nicht rein ist (die Zeilen holen, Einheiten lesen), ist
 * absichtlich duenn und wird auf dev geprueft, nicht hier.
 *
 *   php tests/test-records.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function close( string $name, $got, float $want, float $tol = 0.001 ): void {
    global $passed, $failed;
    if ( is_float( $got ) && abs( $got - $want ) <= $tol ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nDie Helfer, die die Rekorde mitbenutzen\n" . str_repeat( '-', 74 ) . "\n";

check( 'station_row_id() ist oeffentlich', ( new ReflectionMethod( 'NAWS_Calc', 'station_row_id' ) )->isPublic(), true );
check( 'period_range() ist oeffentlich',   ( new ReflectionMethod( 'NAWS_Calc', 'period_range' ) )->isPublic(), true );

// get_daily_summaries() braucht $wpdb und laeuft hier nicht; die Freigabe
// von gust_max steht in einer Liste, die sich lesen laesst.
$db_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-naws-database.php' );
check( 'get_daily_summaries() gibt gust_max heraus', (bool) preg_match( "/\\\$allowed_fields\\s*=\\s*\\[[^\\]]*'gust_max'/", $db_src ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-records.php`
Expected: `Warning: require_once(.../class-naws-records.php): Failed to open stream` — die Klasse gibt es noch nicht. Für diesen Task genügt eine leere Klasse, damit die drei Prüfungen laufen; Task 3 füllt sie.

- [ ] **Step 3: Die leere Klasse anlegen und die drei Änderungen machen**

Neue Datei `includes/class-naws-records.php`:

```php
<?php
/**
 * Records from the daily summary: the hottest day, the longest dry spell,
 * the wettest month, and what this calendar day looked like in earlier
 * years.
 *
 * The arithmetic is pure — daily rows in, numbers out — so it is tested on
 * a hand-built year. Only rows() and delta_parts() touch WordPress.
 *
 * @package NAWS
 * @since   1.9.11
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Records {
}
```

In `includes/class-naws-calc.php` bei beiden Funktionen `private static function` durch `public static function` ersetzen — `station_row_id()` und `period_range()` — und über jede einen Satz in den DocBlock setzen:

```php
     * Public since 1.9.11: NAWS_Records resolves station and period through
     * these two, so a record and a [naws_calc] can never disagree on which
     * rows they looked at.
```

In `includes/class-naws-database.php`, `get_daily_summaries()`:

```php
        // Only select requested fields + join with active modules
        $allowed_fields = [ 'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum', 'gust_max' ];
```

- [ ] **Step 4: Test laufen lassen**

Run: `php tests/test-records.php`
Expected: `3 bestanden, 0 fehlgeschlagen`

- [ ] **Step 5: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-records.php includes/class-naws-calc.php includes/class-naws-database.php tests/test-records.php
git commit -m "Open the calculator's station and period helpers, and let the daily query hand out gust_max"
```

---

## Task 3: Der Katalog und die Rechnung

**Files:**
- Modify: `includes/class-naws-records.php`
- Test: `tests/test-records.php` (Abschnitt anhängen, vor der Zusammenfassung)

**Interfaces:**
- Consumes: `NAWS_Climate::longest_run()`, `NAWS_Calc::catalogue()` (für `frost_days`, `hot_days`, `summer_days`: `field`, `op`, `threshold`)
- Produces:
  - `NAWS_Records::MONTH_MIN_DAYS = 20`, `NAWS_Records::RAIN_DAY_MM = 0.1`
  - `NAWS_Records::catalogue(): array` — 15 Einträge, Schlüssel wie in der Spec 2.1; jeder mit `kind`, `label`, `param`, `decimals`
  - `NAWS_Records::compute( array $rows, string $key ): ?array` — Formen wie in der Spec 2.2
  - `NAWS_Records::all( array $rows, array $keys = [] ): array` — `[ key => Ergebnis ]`, ohne `null`, in Katalog- bzw. Aufrufreihenfolge

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

In `tests/test-records.php` vor `echo "\n" . str_repeat( '-', 74 ) . "\n";` anhängen:

```php
echo "\nNAWS_Records::catalogue()\n" . str_repeat( '-', 74 ) . "\n";

$cat = NAWS_Records::catalogue();
check( 'fuenfzehn Rekorde', count( $cat ), 15 );
check( 'Reihenfolge wie in der Spec', array_keys( $cat ), [
    'hottest_day', 'coldest_night', 'warmest_night', 'coldest_day', 'widest_range',
    'warmest_month', 'coldest_month', 'wettest_day', 'wettest_month',
    'longest_dry_spell', 'longest_wet_spell', 'strongest_gust',
    'longest_frost', 'longest_heat_wave', 'longest_summer_run',
] );
foreach ( $cat as $key => $entry ) {
    check( "$key hat eine Art",            in_array( $entry['kind'] ?? '', [ 'extreme', 'month', 'streak' ], true ), true );
    check( "$key hat einen Sprachkey",     $entry['label'] ?? null, 'rec_' . $key );
    check( "$key hat Nachkommastellen",    is_int( $entry['decimals'] ?? null ), true );
    check( "$key: param ist String/null",  array_key_exists( 'param', $entry ) && ( $entry['param'] === null || is_string( $entry['param'] ) ), true );
}

echo "\nNAWS_Records::compute() auf einem gebauten Jahr\n" . str_repeat( '-', 74 ) . "\n";

/**
 * Ein volles Jahr 2025, Tag fuer Tag, mit bekannten Extremen:
 *  - 1. Juli:    temp_max 39.1 (heissester Tag), temp_min 24.0 (waermste Nacht)
 *  - 10. Jan.:   temp_min -8.5 (kaelteste Nacht), temp_max -3.0 (kaeltester Tag)
 *  - 15. Aug.:   temp_max 35.0, temp_min 10.0 → Spanne 25.0 (groesste Spanne)
 *  - 3. Juni:    rain_sum 26.4 (nassester Tag), gust_max 46.0 (staerkste Boe)
 *  - Januar 8.–14.: temp_min < 0 an sieben Tagen (laengste Frostperiode)
 *  - Juli 1.–5.: temp_max >= 30 an fuenf Tagen (Hitzewelle), 1.–9. >= 25 (Sommerserie neun)
 *  - Regen (0.5) am 1. und 15. jedes Monats ausser August/September, dazu am
 *    31. Juli und 21. September: die Trockenperiode 1. Aug.–20. Sep. ist 51 Tage,
 *    jede andere hoechstens 16
 *  - November: 18 mm am 1., 8., 15., 22., 29. = 90 mm (nassester Monat), ohne Serie
 *  - Regenserie 10.–16. Oktober = sieben Tage
 * Alle anderen Tage: mild, trocken, ohne Boe.
 */
function naws_test_year(): array {
    $rows = [];
    for ( $d = new DateTime( '2025-01-01' ); $d->format( 'Y' ) === '2025'; $d->modify( '+1 day' ) ) {
        $md  = $d->format( 'm-d' );
        $m   = (int) $d->format( 'n' );
        $row = [
            'day_date' => $d->format( 'Y-m-d' ),
            'temp_min' => 8.0,
            'temp_max' => 18.0,
            'temp_avg' => 13.0,
            'rain_sum' => 0.0,
            'gust_max' => 20.0,
        ];
        if ( $m === 1 )  { $row['temp_avg'] = 1.0; }   // kaeltester Monat
        if ( $m === 7 )  { $row['temp_avg'] = 22.0; }  // waermster Monat
        $dom = (int) $d->format( 'j' );
        if ( ( $dom === 1 || $dom === 15 ) && $m !== 8 && $m !== 9 ) { $row['rain_sum'] = 0.5; } // begrenzt jede Trockenperiode auf ~16 Tage
        if ( $m === 11 && in_array( $dom, [ 1, 8, 15, 22, 29 ], true ) ) { $row['rain_sum'] = 18.0; } // nassester Monat: 90 mm, ohne Regenserie
        if ( $md >= '01-08' && $md <= '01-14' ) { $row['temp_min'] = -2.0; }
        if ( $md === '01-10' ) { $row['temp_min'] = -8.5; $row['temp_max'] = -3.0; }
        if ( $md >= '07-01' && $md <= '07-09' ) { $row['temp_max'] = 27.0; }
        if ( $md >= '07-01' && $md <= '07-05' ) { $row['temp_max'] = 31.0; }
        if ( $md === '07-01' ) { $row['temp_max'] = 39.1; $row['temp_min'] = 24.0; }
        if ( $md === '08-15' ) { $row['temp_max'] = 35.0; $row['temp_min'] = 10.0; }
        if ( $md === '06-03' ) { $row['rain_sum'] = 26.4; $row['gust_max'] = 46.0; }
        if ( $md >= '10-10' && $md <= '10-16' ) { $row['rain_sum'] = 1.5; }
        if ( $md === '07-31' ) { $row['rain_sum'] = 0.5; }  // begrenzt die Trockenperiode nach vorn
        if ( $md === '09-21' ) { $row['rain_sum'] = 0.5; }  // ... und nach hinten
        $rows[] = $row;
    }
    return $rows;
}
$year = naws_test_year();
check( 'das gebaute Jahr hat 365 Tage', count( $year ), 365 );

$r = NAWS_Records::compute( $year, 'hottest_day' );
close( 'heissester Tag: 39.1',                     $r['value'] ?? null, 39.1 );
check( 'heissester Tag: 1. Juli',                  $r['date'] ?? null, '2025-07-01' );
$r = NAWS_Records::compute( $year, 'coldest_night' );
close( 'kaelteste Nacht: -8.5',                    $r['value'] ?? null, -8.5 );
check( 'kaelteste Nacht: 10. Januar',              $r['date'] ?? null, '2025-01-10' );
$r = NAWS_Records::compute( $year, 'warmest_night' );
close( 'waermste Nacht: 24.0',                     $r['value'] ?? null, 24.0 );
$r = NAWS_Records::compute( $year, 'coldest_day' );
close( 'kaeltester Tag: -3.0',                     $r['value'] ?? null, -3.0 );
check( 'kaeltester Tag: 10. Januar',               $r['date'] ?? null, '2025-01-10' );
$r = NAWS_Records::compute( $year, 'widest_range' );
close( 'groesste Spanne: 25.0',                    $r['value'] ?? null, 25.0 );
check( 'groesste Spanne: 15. August',              $r['date'] ?? null, '2025-08-15' );
$r = NAWS_Records::compute( $year, 'warmest_month' );
close( 'waermster Monat: 22.0',                    $r['value'] ?? null, 22.0 );
check( 'waermster Monat: Juli',                    $r['month'] ?? null, '2025-07' );
$r = NAWS_Records::compute( $year, 'coldest_month' );
check( 'kaeltester Monat: Januar',                 $r['month'] ?? null, '2025-01' );
$r = NAWS_Records::compute( $year, 'wettest_day' );
close( 'nassester Tag: 26.4',                      $r['value'] ?? null, 26.4 );
check( 'nassester Tag: 3. Juni',                   $r['date'] ?? null, '2025-06-03' );
$r = NAWS_Records::compute( $year, 'wettest_month' );
close( 'nassester Monat: 90 mm',                   $r['value'] ?? null, 90.0 );
check( 'nassester Monat: November',                $r['month'] ?? null, '2025-11' );
$r = NAWS_Records::compute( $year, 'longest_dry_spell' );
check( 'laengste Trockenperiode: 51 Tage',         $r['value'] ?? null, 51 );
check( 'sie beginnt am 1. August',                 $r['from'] ?? null, '2025-08-01' );
check( 'und endet am 20. September',               $r['to'] ?? null, '2025-09-20' );
$r = NAWS_Records::compute( $year, 'longest_wet_spell' );
check( 'laengste Regenperiode: sieben Tage',       $r['value'] ?? null, 7 );
check( 'sie beginnt am 10. Oktober',               $r['from'] ?? null, '2025-10-10' );
$r = NAWS_Records::compute( $year, 'strongest_gust' );
close( 'staerkste Boe: 46',                        $r['value'] ?? null, 46.0 );
check( 'staerkste Boe: 3. Juni',                   $r['date'] ?? null, '2025-06-03' );
$r = NAWS_Records::compute( $year, 'longest_frost' );
check( 'laengste Frostperiode: sieben Tage',       $r['value'] ?? null, 7 );
check( 'Frost: 8.–14. Januar',                     ( $r['from'] ?? '' ) . '/' . ( $r['to'] ?? '' ), '2025-01-08/2025-01-14' );
$r = NAWS_Records::compute( $year, 'longest_heat_wave' );
check( 'Hitzewelle: fuenf Tage',                   $r['value'] ?? null, 5 );
$r = NAWS_Records::compute( $year, 'longest_summer_run' );
check( 'Sommerserie: neun Tage',                   $r['value'] ?? null, 9 );

echo "\nRegeln: Gleichstand, Monatsschwelle, fehlende Spalten\n" . str_repeat( '-', 74 ) . "\n";

$tie = [
    [ 'day_date' => '2025-05-01', 'temp_max' => 30.0 ],
    [ 'day_date' => '2025-05-02', 'temp_max' => 30.0 ],
];
check( 'Gleichstand: das fruehere Datum gewinnt', NAWS_Records::compute( $tie, 'hottest_day' )['date'] ?? null, '2025-05-01' );

$months = [];
for ( $i = 1; $i <= 19; $i++ ) { $months[] = [ 'day_date' => sprintf( '2025-03-%02d', $i ), 'temp_avg' => -5.0 ]; } // kalt, aber nur 19 Tage
for ( $i = 1; $i <= 20; $i++ ) { $months[] = [ 'day_date' => sprintf( '2025-04-%02d', $i ), 'temp_avg' => 4.0 ]; }  // 20 Tage zaehlen
check( 'ein Monat mit 19 Tagen zaehlt nicht',    NAWS_Records::compute( $months, 'coldest_month' )['month'] ?? null, '2025-04' );
$months[] = [ 'day_date' => '2025-03-20', 'temp_avg' => -5.0 ];
check( 'mit dem 20. Tag zaehlt er',              NAWS_Records::compute( $months, 'coldest_month' )['month'] ?? null, '2025-03' );

$month_tie = [];
for ( $i = 1; $i <= 20; $i++ ) { $month_tie[] = [ 'day_date' => sprintf( '2025-01-%02d', $i ), 'rain_sum' => 1.0 ]; }
for ( $i = 1; $i <= 20; $i++ ) { $month_tie[] = [ 'day_date' => sprintf( '2025-02-%02d', $i ), 'rain_sum' => 1.0 ]; }
check( 'Gleichstand bei Monaten: der fruehere', NAWS_Records::compute( $month_tie, 'wettest_month' )['month'] ?? null, '2025-01' );

$sparse = [
    [ 'day_date' => '2025-06-01', 'pressure_avg' => 1010.0 ],       // kennt keine Temperatur
    [ 'day_date' => '2025-06-02', 'temp_max' => 12.0 ],
];
check( 'Zeilen ohne die Spalte zaehlen nicht',   NAWS_Records::compute( $sparse, 'hottest_day' )['date'] ?? null, '2025-06-02' );
check( 'ohne brauchbare Zeile null',             NAWS_Records::compute( [ [ 'day_date' => '2025-06-01', 'pressure_avg' => 1010.0 ] ], 'hottest_day' ), null );
check( 'die Spanne braucht beide Werte',         NAWS_Records::compute( [ [ 'day_date' => '2025-06-01', 'temp_max' => 20.0 ] ], 'widest_range' ), null );
check( 'eine Serie ohne Treffer ist null',       NAWS_Records::compute( [ [ 'day_date' => '2025-06-01', 'temp_min' => 5.0 ] ], 'longest_frost' ), null );
check( 'ohne Zeilen null',                       NAWS_Records::compute( [], 'hottest_day' ), null );
check( 'ein unbekannter Schluessel ist null',    NAWS_Records::compute( $year, 'bestes_wetter' ), null );

echo "\nNAWS_Records::all()\n" . str_repeat( '-', 74 ) . "\n";

$all = NAWS_Records::all( $year );
check( 'alle 15 auf dem vollen Jahr',            count( $all ), 15 );
check( 'in Katalogreihenfolge',                  array_keys( $all ), array_keys( $cat ) );
$some = NAWS_Records::all( $year, [ 'wettest_day', 'hottest_day', 'bestes_wetter' ] );
check( 'Auswahl in Aufrufreihenfolge, Unbekanntes uebergangen', array_keys( $some ), [ 'wettest_day', 'hottest_day' ] );
$none = NAWS_Records::all( [ [ 'day_date' => '2025-06-01', 'pressure_avg' => 1010.0 ] ] );
check( 'ohne berechenbaren Rekord leer',         $none, [] );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-records.php`
Expected: `Fatal error: Uncaught Error: Call to undefined method NAWS_Records::catalogue()`

- [ ] **Step 3: Katalog und Rechnung schreiben**

In `includes/class-naws-records.php` den Klassenrumpf füllen:

```php
final class NAWS_Records {

    /**
     * A month counts only with this many days that carry the column.
     * Without it the month the record began in — three days of March —
     * would win "coldest month" against every full one.
     */
    const MONTH_MIN_DAYS = 20;

    /** The WMO line for a day with precipitation, in millimetres. */
    const RAIN_DAY_MM = 0.1;

    /**
     * The fifteen records, in display order.
     *
     * kind    extreme — one row, field and direction
     *         month   — rows grouped by month, agg (avg|sum), then direction
     *         streak  — longest run of days matching field/op/threshold, or
     *                   the dayclass of the same name in NAWS_Calc
     * param   what NAWS_Helpers::get_unit()/format_value() convert with;
     *         'delta' for a temperature difference, null for a day count
     * label   the naws_label() key
     */
    public static function catalogue(): array {
        return [
            'hottest_day'        => [ 'kind' => 'extreme', 'field' => 'temp_max', 'dir' => 'max', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_hottest_day' ],
            'coldest_night'      => [ 'kind' => 'extreme', 'field' => 'temp_min', 'dir' => 'min', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_coldest_night' ],
            'warmest_night'      => [ 'kind' => 'extreme', 'field' => 'temp_min', 'dir' => 'max', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_warmest_night' ],
            'coldest_day'        => [ 'kind' => 'extreme', 'field' => 'temp_max', 'dir' => 'min', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_coldest_day' ],
            'widest_range'       => [ 'kind' => 'extreme', 'field' => 'range',    'dir' => 'max', 'param' => 'delta',        'decimals' => 1, 'label' => 'rec_widest_range' ],
            'warmest_month'      => [ 'kind' => 'month',   'field' => 'temp_avg', 'agg' => 'avg', 'dir' => 'max', 'param' => 'Temperature', 'decimals' => 1, 'label' => 'rec_warmest_month' ],
            'coldest_month'      => [ 'kind' => 'month',   'field' => 'temp_avg', 'agg' => 'avg', 'dir' => 'min', 'param' => 'Temperature', 'decimals' => 1, 'label' => 'rec_coldest_month' ],
            'wettest_day'        => [ 'kind' => 'extreme', 'field' => 'rain_sum', 'dir' => 'max', 'param' => 'Rain',         'decimals' => 1, 'label' => 'rec_wettest_day' ],
            'wettest_month'      => [ 'kind' => 'month',   'field' => 'rain_sum', 'agg' => 'sum', 'dir' => 'max', 'param' => 'Rain',        'decimals' => 1, 'label' => 'rec_wettest_month' ],
            'longest_dry_spell'  => [ 'kind' => 'streak',  'field' => 'rain_sum', 'op' => '<',  'threshold' => self::RAIN_DAY_MM, 'param' => null, 'decimals' => 0, 'label' => 'rec_longest_dry_spell' ],
            'longest_wet_spell'  => [ 'kind' => 'streak',  'field' => 'rain_sum', 'op' => '>=', 'threshold' => self::RAIN_DAY_MM, 'param' => null, 'decimals' => 0, 'label' => 'rec_longest_wet_spell' ],
            'strongest_gust'     => [ 'kind' => 'extreme', 'field' => 'gust_max', 'dir' => 'max', 'param' => 'GustStrength', 'decimals' => 1, 'label' => 'rec_strongest_gust' ],
            'longest_frost'      => [ 'kind' => 'streak',  'dayclass' => 'frost_days',  'param' => null, 'decimals' => 0, 'label' => 'rec_longest_frost' ],
            'longest_heat_wave'  => [ 'kind' => 'streak',  'dayclass' => 'hot_days',    'param' => null, 'decimals' => 0, 'label' => 'rec_longest_heat_wave' ],
            'longest_summer_run' => [ 'kind' => 'streak',  'dayclass' => 'summer_days', 'param' => null, 'decimals' => 0, 'label' => 'rec_longest_summer_run' ],
        ];
    }

    /**
     * One record over the given daily rows (ascending by day_date).
     *
     * @return array|null extreme: [value, date]; month: [value, month];
     *                    streak: [value (days), from, to]; null when no row
     *                    carries what the record needs — or for a key the
     *                    catalogue does not know.
     */
    public static function compute( array $rows, string $key ): ?array {
        $entry = self::catalogue()[ $key ] ?? null;
        if ( $entry === null ) {
            return null;
        }
        switch ( $entry['kind'] ) {
            case 'extreme':
                return self::extreme( $rows, $entry['field'], $entry['dir'] );
            case 'month':
                return self::month( $rows, $entry['field'], $entry['agg'], $entry['dir'] );
            case 'streak':
                return self::streak( $rows, self::matcher( $entry ), self::field_of( $entry ) );
        }
        return null;
    }

    /**
     * Every record that can be computed, without the ones that cannot.
     *
     * @param array $keys Subset in display order; empty = the whole catalogue.
     *                    Unknown keys are skipped, not reported: a typo in a
     *                    shortcode costs one tile, not the page.
     */
    public static function all( array $rows, array $keys = [] ): array {
        $wanted = $keys === [] ? array_keys( self::catalogue() ) : $keys;
        $out    = [];
        foreach ( $wanted as $key ) {
            $key    = (string) $key;
            $result = self::compute( $rows, $key );
            if ( $result !== null ) {
                $out[ $key ] = $result;
            }
        }
        return $out;
    }

    // ── The three kinds ──────────────────────────────────────────────────

    /** Strict comparison, rows in date order: a tie goes to the earlier day. */
    private static function extreme( array $rows, string $field, string $dir ): ?array {
        $best = null;
        foreach ( $rows as $row ) {
            $v = self::value_of( $row, $field );
            if ( $v === null ) {
                continue;
            }
            if ( $best === null || ( $dir === 'max' ? $v > $best['value'] : $v < $best['value'] ) ) {
                $best = [ 'value' => $v, 'date' => (string) $row['day_date'] ];
            }
        }
        return $best;
    }

    /** Months with fewer than MONTH_MIN_DAYS carrying days do not compete. */
    private static function month( array $rows, string $field, string $agg, string $dir ): ?array {
        $by_month = [];
        foreach ( $rows as $row ) {
            $v = self::value_of( $row, $field );
            if ( $v === null ) {
                continue;
            }
            $by_month[ substr( (string) $row['day_date'], 0, 7 ) ][] = $v;
        }
        ksort( $by_month );
        $best = null;
        foreach ( $by_month as $month => $values ) {
            if ( count( $values ) < self::MONTH_MIN_DAYS ) {
                continue;
            }
            $v = $agg === 'sum' ? array_sum( $values ) : array_sum( $values ) / count( $values );
            if ( $best === null || ( $dir === 'max' ? $v > $best['value'] : $v < $best['value'] ) ) {
                $best = [ 'value' => (float) $v, 'month' => (string) $month ];
            }
        }
        return $best;
    }

    /**
     * Rows that do not carry the field are dropped first, so a gap in the
     * column is a gap in the calendar — and breaks the run, as it should.
     */
    private static function streak( array $rows, callable $matches, string $field ): ?array {
        $carrying = array_values( array_filter( $rows, static function ( $row ) use ( $field ) {
            return self::value_of( $row, $field ) !== null;
        } ) );
        $run = NAWS_Climate::longest_run( $carrying, $matches );
        if ( $run === null ) {
            return null;
        }
        return [ 'value' => $run['length'], 'from' => $run['from'], 'to' => $run['to'] ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** 'range' is the daily span and needs both temperatures. */
    private static function value_of( array $row, string $field ): ?float {
        if ( $field === 'range' ) {
            if ( ! isset( $row['temp_max'], $row['temp_min'] ) ) {
                return null;
            }
            return (float) $row['temp_max'] - (float) $row['temp_min'];
        }
        return isset( $row[ $field ] ) ? (float) $row[ $field ] : null;
    }

    /** The field a streak looks at, from the entry or its dayclass. */
    private static function field_of( array $entry ): string {
        if ( isset( $entry['dayclass'] ) ) {
            return (string) NAWS_Calc::catalogue()[ $entry['dayclass'] ]['field'];
        }
        return (string) $entry['field'];
    }

    /**
     * The matcher for a streak. A dayclass reference reads field, operator
     * and threshold from NAWS_Calc, so "heat wave" and "hot days" can never
     * mean different temperatures.
     */
    private static function matcher( array $entry ): callable {
        if ( isset( $entry['dayclass'] ) ) {
            $entry = NAWS_Calc::catalogue()[ $entry['dayclass'] ];
        }
        $field = (string) $entry['field'];
        $op    = (string) $entry['op'];
        $limit = (float) $entry['threshold'];
        return static function ( array $row ) use ( $field, $op, $limit ): bool {
            $v = (float) $row[ $field ];
            switch ( $op ) {
                case '<':  return $v <  $limit;
                case '<=': return $v <= $limit;
                case '>':  return $v >  $limit;
                default:   return $v >= $limit;
            }
        };
    }
}
```

- [ ] **Step 4: Test laufen lassen**

Run: `php tests/test-records.php`
Expected: alle Prüfungen bestanden, keine Fatal.

Falls „Sommerserie: neun Tage" oder „Hitzewelle: fuenf Tage" abweichen: die Schwellen kommen aus `NAWS_Calc::catalogue()` — `hot_days` ist `temp_max >= 30.0`, `summer_days` `temp_max >= 25.0`, `frost_days` `temp_min < 0.0`. Prüfen, dass die Testdaten diese Grenzen treffen, nicht die Schwellen ändern.

- [ ] **Step 5: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-records.php tests/test-records.php
git commit -m "Compute fifteen records from the daily summary"
```

---

## Task 4: „An diesem Tag", die Differenz und die Zeilen

**Files:**
- Modify: `includes/class-naws-records.php`
- Test: `tests/test-records.php` (Abschnitt anhängen)

**Interfaces:**
- Consumes: `NAWS_Calc::station_row_id()`, `NAWS_Calc::period_range()`, `NAWS_Database::get_daily_summaries()`, `get_option( 'naws_settings' )`
- Produces:
  - `NAWS_Records::on_this_day( array $rows, string $month_day, int $before_year ): array` — Liste, neuestes Jahr zuerst, je Eintrag `year`, `day_date`, `temp_min`, `temp_max`, `temp_avg`, `rain_sum` (float|null) und `record` = `[ 'temp_max' => bool, 'temp_min' => bool, 'rain_sum' => bool ]`
  - `NAWS_Records::delta_parts( float $kelvin ): array{value:float,unit:string}` — Temperaturdifferenz in der eingestellten Einheit, Faktor 1,8 ohne Versatz
  - `NAWS_Records::rows( array $atts ): array` — Tageszeilen der Station für den Zeitraum, Voreinstellung `period = all`
  - `NAWS_Records::coverage( array $rows ): array{first:?string,days:int}` — erster Tag und Zahl der Tage mit mindestens einer der fünf Spalten, für die Fußzeile

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

In `tests/test-records.php` vor der Zusammenfassung anhängen:

```php
echo "\nNAWS_Records::on_this_day()\n" . str_repeat( '-', 74 ) . "\n";

$otd_rows = [
    [ 'day_date' => '2023-09-05', 'temp_min' => 9.0,  'temp_max' => 21.0, 'temp_avg' => 15.0, 'rain_sum' => 0.0 ],
    [ 'day_date' => '2024-02-29', 'temp_min' => 1.0,  'temp_max' => 6.0,  'temp_avg' => 3.0,  'rain_sum' => 2.0 ],
    [ 'day_date' => '2024-09-05', 'temp_min' => 12.0, 'temp_max' => 24.0, 'temp_avg' => 18.0, 'rain_sum' => 4.2 ],
    [ 'day_date' => '2025-09-05', 'temp_min' => 12.0, 'temp_max' => 28.0, 'temp_avg' => 20.0, 'rain_sum' => 0.0 ],
    [ 'day_date' => '2026-09-05', 'temp_min' => 15.0, 'temp_max' => 30.0, 'temp_avg' => 22.0, 'rain_sum' => 0.0 ],
];
$otd = NAWS_Records::on_this_day( $otd_rows, '09-05', 2026 );
check( 'drei fruehere Jahre, das laufende nicht', array_column( $otd, 'year' ), [ 2025, 2024, 2023 ] );
check( 'waermstes Maximum: 2025',                 $otd[0]['record']['temp_max'], true );
check( 'nicht 2024',                              $otd[1]['record']['temp_max'], false );
check( 'kaeltestes Minimum: 2023',                $otd[2]['record']['temp_min'], true );
check( 'Gleichstand beim Minimum geht an das fruehere Jahr', $otd[0]['record']['temp_min'] . '/' . $otd[1]['record']['temp_min'], '/' );
check( 'nassester Tag: 2024',                     $otd[1]['record']['rain_sum'], true );
check( 'der 29. Februar kommt nur aus Schaltjahren', array_column( NAWS_Records::on_this_day( $otd_rows, '02-29', 2026 ), 'year' ), [ 2024 ] );
check( 'ohne fruehere Jahre leer',                NAWS_Records::on_this_day( $otd_rows, '09-05', 2023 ), [] );

echo "\nNAWS_Records::delta_parts() und coverage()\n" . str_repeat( '-', 74 ) . "\n";

$GLOBALS['naws_test_options']['naws_settings']['temperature_unit'] = 'C';
check( '10 K in Celsius',    NAWS_Records::delta_parts( 10.0 ), [ 'value' => 10.0, 'unit' => '°C' ] );
$GLOBALS['naws_test_options']['naws_settings']['temperature_unit'] = 'F';
check( '10 K in Fahrenheit sind 18, ohne Versatz', NAWS_Records::delta_parts( 10.0 ), [ 'value' => 18.0, 'unit' => '°F' ] );
$GLOBALS['naws_test_options']['naws_settings']['temperature_unit'] = 'C';

$cov = NAWS_Records::coverage( [
    [ 'day_date' => '2024-03-28', 'pressure_avg' => 1010.0 ],                // zaehlt nicht: keine der fuenf Spalten
    [ 'day_date' => '2024-03-29', 'temp_avg' => 5.0 ],
    [ 'day_date' => '2024-03-30', 'rain_sum' => 0.0 ],
] );
check( 'Abdeckung: erster Tag mit Daten', $cov['first'], '2024-03-29' );
check( 'Abdeckung: zwei Tage',            $cov['days'], 2 );
check( 'Abdeckung ohne Zeilen',           NAWS_Records::coverage( [] ), [ 'first' => null, 'days' => 0 ] );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-records.php`
Expected: `Fatal error: Uncaught Error: Call to undefined method NAWS_Records::on_this_day()`

- [ ] **Step 3: Die vier Funktionen schreiben**

In `includes/class-naws-records.php` vor `// ── The three kinds` einfügen:

```php
    /**
     * This calendar day in earlier years, newest first.
     *
     * The running year is left out: its row for today is written at the end
     * of the day, and "in earlier years" is then also true as a heading.
     * Each row is marked where it holds the day's record — warmest maximum,
     * coldest minimum, wettest — with a strict comparison walked from the
     * earliest year, so a tie goes to the earlier year.
     *
     * @param string $month_day   'MM-DD'.
     * @param int    $before_year Years >= this one are excluded.
     */
    public static function on_this_day( array $rows, string $month_day, int $before_year ): array {
        $hits = [];
        foreach ( $rows as $row ) {
            $date = (string) ( $row['day_date'] ?? '' );
            if ( substr( $date, 5 ) !== $month_day ) {
                continue;
            }
            $year = (int) substr( $date, 0, 4 );
            if ( $year >= $before_year ) {
                continue;
            }
            $hits[] = [
                'year'     => $year,
                'day_date' => $date,
                'temp_min' => isset( $row['temp_min'] ) ? (float) $row['temp_min'] : null,
                'temp_max' => isset( $row['temp_max'] ) ? (float) $row['temp_max'] : null,
                'temp_avg' => isset( $row['temp_avg'] ) ? (float) $row['temp_avg'] : null,
                'rain_sum' => isset( $row['rain_sum'] ) ? (float) $row['rain_sum'] : null,
                'record'   => [ 'temp_max' => false, 'temp_min' => false, 'rain_sum' => false ],
            ];
        }
        usort( $hits, static fn( $a, $b ) => $a['year'] <=> $b['year'] ); // ascending for the tie rule
        foreach ( [ 'temp_max' => 'max', 'temp_min' => 'min', 'rain_sum' => 'max' ] as $field => $dir ) {
            $best = null;
            foreach ( $hits as $i => $hit ) {
                $v = $hit[ $field ];
                if ( $v === null ) {
                    continue;
                }
                if ( $best === null || ( $dir === 'max' ? $v > $hits[ $best ][ $field ] : $v < $hits[ $best ][ $field ] ) ) {
                    $best = $i;
                }
            }
            if ( $best !== null ) {
                $hits[ $best ]['record'][ $field ] = true;
            }
        }
        return array_reverse( $hits );
    }

    /**
     * A temperature difference in the site's unit.
     *
     * A difference converts with the factor alone: 10 K are 18 °F, not
     * 50 °F. NAWS_Helpers::format_value() would add the offset, which is
     * right for a temperature and wrong for a span.
     *
     * @return array{value:float,unit:string}
     */
    public static function delta_parts( float $kelvin ): array {
        $unit = get_option( 'naws_settings', [] )['temperature_unit'] ?? 'C';
        if ( $unit === 'F' ) {
            return [ 'value' => round( $kelvin * 1.8, 1 ), 'unit' => '°F' ];
        }
        return [ 'value' => round( $kelvin, 1 ), 'unit' => '°C' ];
    }

    /**
     * First day with data and the number of days that carry at least one
     * of the five columns — for the footer under the tiles.
     *
     * @return array{first:?string,days:int}
     */
    public static function coverage( array $rows ): array {
        $first = null;
        $days  = 0;
        foreach ( $rows as $row ) {
            foreach ( [ 'temp_min', 'temp_max', 'temp_avg', 'rain_sum', 'gust_max' ] as $field ) {
                if ( isset( $row[ $field ] ) ) {
                    $days++;
                    $first = $first ?? (string) $row['day_date'];
                    break;
                }
            }
        }
        return [ 'first' => $first, 'days' => $days ];
    }

    /**
     * The station's daily rows for the requested period — the only place
     * this class talks to WordPress.
     *
     * Records default to the whole record: `period` is set to 'all' before
     * NAWS_Calc::period_range() sees it, which keeps that function
     * untouched. year="2025" still narrows to one year, as it does for
     * [naws_calc].
     */
    public static function rows( array $atts ): array {
        if ( ! isset( $atts['period'] ) || $atts['period'] === '' ) {
            $atts['period'] = 'all';
        }
        $station = NAWS_Calc::station_row_id( $atts );
        if ( $station === null ) {
            return [];
        }
        $range = NAWS_Calc::period_range( $atts );
        return NAWS_Database::get_daily_summaries( [
            'module_id' => $station,
            'date_from' => $range['from'],
            'date_to'   => $range['to'],
            'fields'    => [ 'temp_min', 'temp_max', 'temp_avg', 'rain_sum', 'gust_max' ],
            'group_by'  => 'day',
        ] );
    }
```

- [ ] **Step 4: Test laufen lassen**

Run: `php tests/test-records.php`
Expected: alle Prüfungen bestanden.

- [ ] **Step 5: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-records.php tests/test-records.php
git commit -m "Look up this day in earlier years, convert a span without the offset, and fetch the station's rows"
```

---

## Task 5: Die Templates der Rekorde und die Shortcodes

**Files:**
- Create: `templates/records.php`, `templates/on-this-day.php`
- Modify: `includes/class-naws-shortcodes.php` (Registrierung ~Zeile 19–29, Handler nach `sc_heatmap()` ~Zeile 256)
- Modify: `includes/class-naws-labels.php` (nach `case 'calc_spi':` ~Zeile 111)
- Modify: `assets/css/frontend.css` (vor dem Block `[naws_heatmap]` ~Zeile 1640)
- Test: `tests/test-records-render.php` (neu)

**Interfaces:**
- Consumes: `NAWS_Records::rows()`, `all()`, `on_this_day()`, `coverage()`, `delta_parts()`; `NAWS_Helpers::format_value()`, `get_unit()`; `naws_label()`
- Produces: Shortcodes `naws_records`, `naws_on_this_day`; Template-Variablen `$atts`; Labels `rec_title`, `rec_title_year`, `rec_<key>` (15), `rec_col_record`, `rec_col_value`, `rec_col_when`, `rec_since`, `otd_title`, `otd_col_year`, `otd_col_min`, `otd_col_max`, `otd_col_avg`, `otd_col_rain`, `otd_record`

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

Neue Datei `tests/test-records-render.php`:

```php
<?php
/**
 * Tests fuer templates/records.php und templates/on-this-day.php.
 *
 * Die Rechnung ist in test-records.php abgesichert; hier geht es um das
 * Markup: eine Kachel je Rekord, beide Layouts, die Auswahl, die
 * Fusszeile, und dass ein leerer Baustein leer bleibt.
 *
 *   php tests/test-records-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [ 'temperature_unit' => 'C', 'rain_unit' => 'mm', 'wind_unit' => 'kmh' ], 'date_format' => 'j. F Y' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_date( $fmt, $ts = null ) { return gmdate( $fmt, $ts ?? time() ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, '.', '' ); }
require_once __DIR__ . '/i18n-stubs.php';

require_once dirname( __DIR__ ) . '/includes/class-naws-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-climate.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-records.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Ein Jahr mit allen 15 Rekorden — dieselbe Form wie in test-records.php. */
function naws_test_year(): array {
    $rows = [];
    for ( $d = new DateTime( '2025-01-01' ); $d->format( 'Y' ) === '2025'; $d->modify( '+1 day' ) ) {
        $md  = $d->format( 'm-d' );
        $m   = (int) $d->format( 'n' );
        $row = [ 'day_date' => $d->format( 'Y-m-d' ), 'temp_min' => 8.0, 'temp_max' => 18.0, 'temp_avg' => 13.0, 'rain_sum' => 0.0, 'gust_max' => 20.0 ];
        if ( $m === 1 )  { $row['temp_avg'] = 1.0; }
        if ( $m === 7 )  { $row['temp_avg'] = 22.0; }
        $dom = (int) $d->format( 'j' );
        if ( ( $dom === 1 || $dom === 15 ) && $m !== 8 && $m !== 9 ) { $row['rain_sum'] = 0.5; }
        if ( $m === 11 && in_array( $dom, [ 1, 8, 15, 22, 29 ], true ) ) { $row['rain_sum'] = 18.0; }
        if ( $md >= '01-08' && $md <= '01-14' ) { $row['temp_min'] = -2.0; }
        if ( $md === '01-10' ) { $row['temp_min'] = -8.5; $row['temp_max'] = -3.0; }
        if ( $md >= '07-01' && $md <= '07-09' ) { $row['temp_max'] = 27.0; }
        if ( $md >= '07-01' && $md <= '07-05' ) { $row['temp_max'] = 31.0; }
        if ( $md === '07-01' ) { $row['temp_max'] = 39.1; $row['temp_min'] = 24.0; }
        if ( $md === '08-15' ) { $row['temp_max'] = 35.0; $row['temp_min'] = 10.0; }
        if ( $md === '06-03' ) { $row['rain_sum'] = 26.4; $row['gust_max'] = 46.0; }
        if ( $md >= '10-10' && $md <= '10-16' ) { $row['rain_sum'] = 1.5; }
        if ( $md === '07-31' || $md === '09-21' ) { $row['rain_sum'] = 0.5; }
        $rows[] = $row;
    }
    return $rows;
}

// Die Templates holen ihre Zeilen ueber NAWS_Records::rows(); hier kommen
// sie aus der Testdatei. Der Ersatz sitzt in einer Unterklasse mit
// demselben Namen wie das Template sie ruft — deshalb wird
// NAWS_Records::rows() ueber einen Hook austauschbar gemacht: das Template
// liest $naws_rows, wenn die Variable gesetzt ist, sonst rows().
function render( string $template, array $atts, ?array $naws_rows ): string {
    ob_start();
    include dirname( __DIR__ ) . '/templates/' . $template;
    return ob_get_clean();
}

echo "\ntemplates/records.php\n" . str_repeat( '-', 74 ) . "\n";

$year = naws_test_year();
$html = render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'cards', 'title' => 'Records' ], $year );
check( 'Wurzel mit Klasse',                     str_contains( $html, '<section class="naws-rec">' ), true );
check( 'Ueberschrift',                          str_contains( $html, '<h3 class="naws-rec-title">Records</h3>' ), true );
check( 'fuenfzehn Kacheln',                     substr_count( $html, 'class="naws-rec-tile ' ), 15 );
check( 'die Kachel traegt ihren Schluessel',    str_contains( $html, 'naws-rec-tile naws-rec-hottest_day' ), true );
check( 'Wert mit Einheit',                      str_contains( $html, '39.1 <span class="naws-rec-unit">°C</span>' ), true );
check( 'Datum in der Einstellung der Site',     str_contains( $html, '<span class="naws-rec-when">1. July 2025</span>' ), true );
check( 'Serie mit Tagen und Spanne',            str_contains( $html, '51 days' ) && str_contains( $html, '1. August 2025 – 20. September 2025' ), true );
check( 'Monat als Monatsname',                  str_contains( $html, 'July 2025' ), true );
check( 'Spanne in Grad, nicht in Fahrenheit-Versatz', str_contains( $html, '25.0 <span class="naws-rec-unit">°C</span>' ), true );
check( 'Fusszeile mit erstem Tag und Tagen',    (bool) preg_match( '#<p class="naws-rec-foot">Since 1\. January 2025 · 365 days with readings</p>#', $html ), true );
check( 'keine MAC-Adresse',                     (bool) preg_match( '/[0-9a-f]{2}(:[0-9a-f]{2}){5}/i', $html ), false );
check( 'kein style-Block',                      str_contains( $html, '<style' ), false );

$table = render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'table', 'title' => '' ], $year );
check( 'Tabelle statt Kacheln',                 str_contains( $table, '<table class="naws-rec-table">' ), true );
check( 'fuenfzehn Zeilen im Rumpf',             substr_count( $table, '<tr class="naws-rec-row' ), 15 );
check( 'ohne Titel keine Ueberschrift',         str_contains( $table, '<h3' ), false );

$some = render( 'records.php', [ 'year' => '', 'records' => 'wettest_day, hottest_day, unbekannt', 'layout' => 'cards', 'title' => '' ], $year );
check( 'Auswahl: zwei Kacheln',                 substr_count( $some, 'class="naws-rec-tile ' ), 2 );
check( 'Auswahl in Aufrufreihenfolge',          strpos( $some, 'naws-rec-wettest_day' ) < strpos( $some, 'naws-rec-hottest_day' ), true );

check( 'ohne Zeilen nichts',                    render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'cards', 'title' => 'x' ], [] ), '' );
check( 'ohne berechenbaren Rekord nichts',      render( 'records.php', [ 'year' => '', 'records' => '', 'layout' => 'cards', 'title' => 'x' ], [ [ 'day_date' => '2025-01-01', 'pressure_avg' => 1000.0 ] ] ), '' );

echo "\ntemplates/on-this-day.php\n" . str_repeat( '-', 74 ) . "\n";

$otd_rows = [
    [ 'day_date' => '2024-09-05', 'temp_min' => 12.0, 'temp_max' => 24.0, 'temp_avg' => 18.0, 'rain_sum' => 4.2 ],
    [ 'day_date' => '2025-09-05', 'temp_min' => 12.0, 'temp_max' => 28.0, 'temp_avg' => 20.0, 'rain_sum' => 0.0 ],
];
$otd = render( 'on-this-day.php', [ 'date' => '2026-09-05', 'title' => 'This day in earlier years' ], $otd_rows );
check( 'Wurzel mit Klasse',                     str_contains( $otd, '<section class="naws-otd">' ), true );
check( 'eine Zeile je Jahr',                    substr_count( $otd, '<tr class="naws-otd-row">' ), 2 );
check( 'neuestes Jahr zuerst',                  strpos( $otd, '>2025<' ) < strpos( $otd, '>2024<' ), true );
check( 'Rekordzelle markiert',                  substr_count( $otd, 'class="naws-otd-record"' ), 3 );
check( 'Kopfzeile',                             str_contains( $otd, '<th>Year</th>' ), true );
check( 'ohne fruehere Jahre nichts',            render( 'on-this-day.php', [ 'date' => '2024-09-05', 'title' => '' ], $otd_rows ), '' );
check( 'unbrauchbares Datum faellt auf heute',  str_contains( render( 'on-this-day.php', [ 'date' => 'gestern', 'title' => '' ], [ [ 'day_date' => '2000-' . gmdate( 'm-d' ), 'temp_max' => 1.0 ] ] ), '>2000<' ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-records-render.php`
Expected: `Warning: include(.../templates/records.php): Failed to open stream` und Fehlschläge.

- [ ] **Step 3: Die Labels eintragen**

In `includes/class-naws-labels.php` nach der Zeile `case 'calc_spi':` einfügen:

```php

        // [naws_records] and [naws_on_this_day] (since 1.9.11).
        case 'rec_title':                          return __( 'Records', 'xtx-integration-for-netatmo' );
        case 'rec_title_year':                     return __( 'Records %d', 'xtx-integration-for-netatmo' );
        case 'rec_hottest_day':                    return __( 'Hottest day', 'xtx-integration-for-netatmo' );
        case 'rec_coldest_night':                  return __( 'Coldest night', 'xtx-integration-for-netatmo' );
        case 'rec_warmest_night':                  return __( 'Warmest night', 'xtx-integration-for-netatmo' );
        case 'rec_coldest_day':                    return __( 'Coldest day', 'xtx-integration-for-netatmo' );
        case 'rec_widest_range':                   return __( 'Largest daily range', 'xtx-integration-for-netatmo' );
        case 'rec_warmest_month':                  return __( 'Warmest month', 'xtx-integration-for-netatmo' );
        case 'rec_coldest_month':                  return __( 'Coldest month', 'xtx-integration-for-netatmo' );
        case 'rec_wettest_day':                    return __( 'Wettest day', 'xtx-integration-for-netatmo' );
        case 'rec_wettest_month':                  return __( 'Wettest month', 'xtx-integration-for-netatmo' );
        case 'rec_longest_dry_spell':              return __( 'Longest dry spell', 'xtx-integration-for-netatmo' );
        case 'rec_longest_wet_spell':              return __( 'Longest wet spell', 'xtx-integration-for-netatmo' );
        case 'rec_strongest_gust':                 return __( 'Strongest gust', 'xtx-integration-for-netatmo' );
        case 'rec_longest_frost':                  return __( 'Longest frost period', 'xtx-integration-for-netatmo' );
        case 'rec_longest_heat_wave':              return __( 'Longest heat wave', 'xtx-integration-for-netatmo' );
        case 'rec_longest_summer_run':             return __( 'Longest run of summer days', 'xtx-integration-for-netatmo' );
        case 'rec_col_record':                     return _x( 'Record', 'table column', 'xtx-integration-for-netatmo' );
        case 'rec_col_value':                      return _x( 'Value', 'table column', 'xtx-integration-for-netatmo' );
        case 'rec_col_when':                       return _x( 'When', 'table column', 'xtx-integration-for-netatmo' );
        case 'rec_since':                          return __( 'Since %1$s · %2$s with readings', 'xtx-integration-for-netatmo' );
        case 'otd_title':                          return __( 'This day in earlier years', 'xtx-integration-for-netatmo' );
        case 'otd_col_year':                       return __( 'Year', 'xtx-integration-for-netatmo' );
        case 'otd_col_min':                        return _x( 'Low', 'daily minimum', 'xtx-integration-for-netatmo' );
        case 'otd_col_max':                        return _x( 'High', 'daily maximum', 'xtx-integration-for-netatmo' );
        case 'otd_col_avg':                        return _x( 'Mean', 'daily average', 'xtx-integration-for-netatmo' );
        case 'otd_col_rain':                       return _x( 'Rain', 'daily sum', 'xtx-integration-for-netatmo' );
        case 'otd_record':                         return __( 'record for this day', 'xtx-integration-for-netatmo' );
```

- [ ] **Step 4: Das Rekorde-Template schreiben**

Neue Datei `templates/records.php`:

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_records year="" records="" layout="cards" title=""]
 *
 * Fifteen records from the daily summary as tiles or as a table. Rendered
 * on the server, no script: a record does not change between two page
 * loads. Colours come from the theme variables, so the Appearance page
 * reaches these tiles like every other block.
 *
 * Expected variables:
 * @var array      $atts      Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_rows Daily rows; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.11
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$rows = $naws_rows ?? NAWS_Records::rows( $atts );
if ( empty( $rows ) ) {
    return;
}

$wanted = array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $atts['records'] ?? '' ) ) ) );
$found  = NAWS_Records::all( $rows, array_values( $wanted ) );
if ( empty( $found ) ) {
    return;
}

$catalogue = NAWS_Records::catalogue();
$layout    = ( $atts['layout'] ?? 'cards' ) === 'table' ? 'table' : 'cards';
$title     = (string) ( $atts['title'] ?? '' );
$coverage  = NAWS_Records::coverage( $rows );
$date_fmt  = get_option( 'date_format', 'j. F Y' );

/**
 * Value and unit of one result, in the site's units.
 * @return array{value:string,unit:string}
 */
$naws_rec_parts = static function ( array $entry, array $result ): array {
    if ( $entry['kind'] === 'streak' ) {
        $n = (int) $result['value'];
        /* translators: %d: number of days */
        return [ 'value' => sprintf( _n( '%d day', '%d days', $n, 'xtx-integration-for-netatmo' ), $n ), 'unit' => '' ];
    }
    if ( $entry['param'] === 'delta' ) {
        $d = NAWS_Records::delta_parts( (float) $result['value'] );
        return [ 'value' => number_format_i18n( $d['value'], $entry['decimals'] ), 'unit' => $d['unit'] ];
    }
    return [
        'value' => number_format_i18n( (float) NAWS_Helpers::format_value( $entry['param'], (float) $result['value'] ), $entry['decimals'] ),
        'unit'  => (string) NAWS_Helpers::get_unit( $entry['param'] ),
    ];
};

/** When it happened: a day, a month, or a span. */
$naws_rec_when = static function ( array $entry, array $result ) use ( $date_fmt ): string {
    if ( $entry['kind'] === 'month' ) {
        return wp_date( 'F Y', strtotime( $result['month'] . '-15' ) );
    }
    if ( $entry['kind'] === 'streak' ) {
        return wp_date( $date_fmt, strtotime( $result['from'] ) ) . ' – ' . wp_date( $date_fmt, strtotime( $result['to'] ) );
    }
    return wp_date( $date_fmt, strtotime( $result['date'] ) );
};
?>
<section class="naws-rec">
  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-rec-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>

  <?php if ( $layout === 'table' ) : ?>
    <table class="naws-rec-table">
      <thead>
        <tr>
          <th><?php echo esc_html( naws_label( 'rec_col_record' ) ); ?></th>
          <th><?php echo esc_html( naws_label( 'rec_col_value' ) ); ?></th>
          <th><?php echo esc_html( naws_label( 'rec_col_when' ) ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $found as $key => $result ) : $entry = $catalogue[ $key ]; $parts = $naws_rec_parts( $entry, $result ); ?>
          <tr class="naws-rec-row naws-rec-<?php echo esc_attr( $key ); ?>">
            <td><?php echo esc_html( naws_label( $entry['label'] ) ); ?></td>
            <td><?php echo esc_html( $parts['value'] ); ?><?php if ( $parts['unit'] !== '' ) : ?> <span class="naws-rec-unit"><?php echo esc_html( $parts['unit'] ); ?></span><?php endif; ?></td>
            <td><?php echo esc_html( $naws_rec_when( $entry, $result ) ); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else : ?>
    <div class="naws-rec-grid">
      <?php foreach ( $found as $key => $result ) : $entry = $catalogue[ $key ]; $parts = $naws_rec_parts( $entry, $result ); ?>
        <div class="naws-rec-tile naws-rec-<?php echo esc_attr( $key ); ?>">
          <span class="naws-rec-label"><?php echo esc_html( naws_label( $entry['label'] ) ); ?></span>
          <span class="naws-rec-value"><?php echo esc_html( $parts['value'] ); ?><?php if ( $parts['unit'] !== '' ) : ?> <span class="naws-rec-unit"><?php echo esc_html( $parts['unit'] ); ?></span><?php endif; ?></span>
          <span class="naws-rec-when"><?php echo esc_html( $naws_rec_when( $entry, $result ) ); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( $coverage['first'] !== null ) : ?>
    <p class="naws-rec-foot"><?php
      /* translators: 1: first date with readings, 2: "365 days" */
      echo esc_html( sprintf(
          naws_label( 'rec_since' ),
          wp_date( $date_fmt, strtotime( $coverage['first'] ) ),
          /* translators: %d: number of days */
          sprintf( _n( '%d day', '%d days', $coverage['days'], 'xtx-integration-for-netatmo' ), $coverage['days'] )
      ) );
    ?></p>
  <?php endif; ?>
</section>
```

- [ ] **Step 5: Das „An diesem Tag"-Template schreiben**

Neue Datei `templates/on-this-day.php`:

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_on_this_day date="" title=""]
 *
 * This calendar day in every earlier year: low, high, mean and rain, with
 * the day's record marked in each column. The running year is left out —
 * its row for today is written at the end of the day.
 *
 * Expected variables:
 * @var array      $atts      Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_rows Daily rows; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.11
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The day: MM-DD or YYYY-MM-DD; anything else is today. The year of the
// attribute is only used to know which years count as "earlier".
$raw   = trim( (string) ( $atts['date'] ?? '' ) );
$today = wp_date( 'Y-m-d' );
if ( preg_match( '/^(\d{4})-(\d{2}-\d{2})$/', $raw, $m ) && checkdate( (int) substr( $m[2], 0, 2 ), (int) substr( $m[2], 3, 2 ), (int) $m[1] ) ) {
    $before    = (int) $m[1];
    $month_day = $m[2];
} elseif ( preg_match( '/^(\d{2})-(\d{2})$/', $raw, $m ) && checkdate( (int) $m[1], (int) $m[2], 2024 ) ) {
    $before    = (int) substr( $today, 0, 4 );
    $month_day = $raw;
} else {
    $before    = (int) substr( $today, 0, 4 );
    $month_day = substr( $today, 5 );
}

$rows = $naws_rows ?? NAWS_Records::rows( $atts );
$hits = NAWS_Records::on_this_day( $rows, $month_day, $before );
if ( empty( $hits ) ) {
    return;
}

$title = (string) ( $atts['title'] ?? '' );
$temp  = static fn( $v ) => $v === null ? '–' : number_format_i18n( (float) NAWS_Helpers::format_value( 'Temperature', $v ), 1 );
$rain  = static fn( $v ) => $v === null ? '–' : number_format_i18n( (float) NAWS_Helpers::format_value( 'Rain', $v ), 1 );
// The four value cells, in order: text and whether it is the day's record.
$cells = static fn( array $hit ): array => [
    [ $temp( $hit['temp_min'] ), $hit['record']['temp_min'] ],
    [ $temp( $hit['temp_max'] ), $hit['record']['temp_max'] ],
    [ $temp( $hit['temp_avg'] ), false ],
    [ $rain( $hit['rain_sum'] ), $hit['record']['rain_sum'] ],
];
?>
<section class="naws-otd">
  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-otd-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>
  <table class="naws-otd-table">
    <thead>
      <tr>
        <th><?php echo esc_html( naws_label( 'otd_col_year' ) ); ?></th>
        <th><?php echo esc_html( naws_label( 'otd_col_min' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span></th>
        <th><?php echo esc_html( naws_label( 'otd_col_max' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span></th>
        <th><?php echo esc_html( naws_label( 'otd_col_avg' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span></th>
        <th><?php echo esc_html( naws_label( 'otd_col_rain' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Rain' ) ); ?></span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $hits as $hit ) : ?>
        <tr class="naws-otd-row">
          <td><?php echo esc_html( (string) $hit['year'] ); ?></td>
          <?php foreach ( $cells( $hit ) as [ $text, $is_record ] ) : ?>
            <?php if ( $is_record ) : ?>
              <td class="naws-otd-record" title="<?php echo esc_attr( naws_label( 'otd_record' ) ); ?>"><?php echo esc_html( $text ); ?></td>
            <?php else : ?>
              <td><?php echo esc_html( $text ); ?></td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
```

- [ ] **Step 6: Handler und Registrierung**

In `includes/class-naws-shortcodes.php` in der Registrierung nach `add_shortcode( 'naws_heatmap', … );`:

```php
        add_shortcode( 'naws_records',     [ $this, 'sc_records' ] );
        add_shortcode( 'naws_on_this_day', [ $this, 'sc_on_this_day' ] );
```

Nach `sc_heatmap()` einfügen:

```php
    // ----------------------------------------------------------------
    // [naws_records year="" records="" layout="cards" title=""]
    // Fifteen records from the daily summary, since 1.9.11
    // ----------------------------------------------------------------
    public function sc_records( $atts ) {
        $this->enqueue_frontend_styles();

        $atts = shortcode_atts( [
            'year'    => '',
            'records' => '',
            'layout'  => 'cards',
            'title'   => null,
        ], $atts, 'naws_records' );

        // The default title names the year when there is one; an explicit
        // empty title="" leaves the heading out.
        if ( $atts['title'] === null ) {
            $year          = intval( $atts['year'] );
            $atts['title'] = $year > 0 ? sprintf( naws_label( 'rec_title_year' ), $year ) : naws_label( 'rec_title' );
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/records.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_on_this_day date="" title=""]
    // ----------------------------------------------------------------
    public function sc_on_this_day( $atts ) {
        $this->enqueue_frontend_styles();

        $atts = shortcode_atts( [
            'date'  => '',
            'title' => null,
        ], $atts, 'naws_on_this_day' );

        if ( $atts['title'] === null ) {
            $atts['title'] = naws_label( 'otd_title' );
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/on-this-day.php';
        return ob_get_clean();
    }
```

- [ ] **Step 7: Die Stile**

In `assets/css/frontend.css` vor dem Block `[naws_heatmap] — Kalenderraster` einfügen:

```css
/* ══════════════════════════════════════════════════════════════════
   [naws_records] und [naws_on_this_day] — Rekorde aus der Tagesuebersicht
   ══════════════════════════════════════════════════════════════════ */

.naws-rec, .naws-otd {
  background: var(--naws-surface, #fff);
  border: 1px solid var(--naws-border, #e0eeee);
  border-radius: var(--naws-radius, 12px);
  padding: 14px;
  color: var(--naws-text, #427272);
}
.naws-rec-title, .naws-otd-title { margin: 0 0 12px; font-size: 1.1em; color: var(--naws-text-dark, #2d5252); }

.naws-rec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
}
.naws-rec-tile {
  display: flex; flex-direction: column; gap: 4px;
  padding: 12px 14px;
  background: var(--naws-surface-2, #f8fafc);
  border: 1px solid var(--naws-border, #e0eeee);
  border-radius: var(--naws-radius-sm, 10px);
}
.naws-rec-label { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--naws-text-muted, #7aa0a0); }
.naws-rec-value { font-size: 22px; font-weight: 640; line-height: 1.1; font-variant-numeric: tabular-nums; color: var(--naws-text-darkest, #1a3535); }
.naws-rec-unit  { font-size: 13px; font-weight: 500; color: var(--naws-text-muted, #7aa0a0); }
.naws-rec-when  { font-size: 12px; color: var(--naws-text-muted, #7aa0a0); }
.naws-rec-foot  { margin: 12px 0 0; font-size: 12px; color: var(--naws-text-muted, #7aa0a0); }

.naws-rec-table, .naws-otd-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
.naws-rec-table th, .naws-otd-table th { text-align: left; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--naws-text-muted, #7aa0a0); padding: 6px 8px; border-bottom: 1px solid var(--naws-border, #e0eeee); }
.naws-rec-table td, .naws-otd-table td { padding: 8px; border-bottom: 1px solid var(--naws-border, #e0eeee); }
.naws-otd-unit { font-weight: 400; text-transform: none; letter-spacing: 0; }
.naws-otd-record { font-weight: 640; color: var(--naws-text-darkest, #1a3535); background: var(--naws-surface-2, #f8fafc); }
```

- [ ] **Step 8: Test laufen lassen**

Run: `php tests/test-records-render.php`
Expected: alle Prüfungen bestanden.

- [ ] **Step 9: Die ganze Suite und `php -l`**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done; for f in templates/records.php templates/on-this-day.php includes/class-naws-shortcodes.php includes/class-naws-labels.php; do php -l $f; done`
Expected: keine FAIL-Zeile, viermal `No syntax errors`.

- [ ] **Step 10: Commit**

```bash
git add templates/records.php templates/on-this-day.php includes/class-naws-shortcodes.php includes/class-naws-labels.php assets/css/frontend.css tests/test-records-render.php
git commit -m "Show the records as tiles or a table, and this day in earlier years"
```

---

## Task 6: Die Sonnenbahn rechnen

**Files:**
- Modify: `includes/class-naws-astro.php` (nach `sun_times()` ~Zeile 226)
- Test: `tests/test-sunpath.php` (neu)

**Interfaces:**
- Produces: `NAWS_Astro::sun_path( float $lat, float $lng, ?int $now = null, ?int $day = null ): ?array` — Schlüssel `dawn`, `sunrise`, `transit`, `sunset`, `dusk` (int|null), `day_length` (int Sekunden), `progress` (float|null), `night_progress` (float|null), `delta_day` (int), `longest` (int), `shortest` (int). `$day` ist ein Zeitstempel innerhalb des gewünschten Kalendertags (Voreinstellung `$now`); `null` bei Polartag/Polarnacht.

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

Neue Datei `tests/test-sunpath.php`:

```php
<?php
/**
 * Tests fuer NAWS_Astro::sun_path() und templates/sunpath.php.
 *
 * Die Rechnung ist rein: Koordinaten und Zeitstempel rein, Zeitstempel und
 * Anteile raus. Leipzig (51.34 N, 12.37 O) ist die Referenz, weil die
 * Station des Autors dort steht und die Zahlen sich mit jeder Sonnentabelle
 * gegenpruefen lassen. Sydney prueft die andere Halbkugel.
 *
 *   php tests/test-sunpath.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['naws_test_options'] = [ 'naws_settings' => [], 'time_format' => 'H:i', 'timezone_string' => 'Europe/Berlin' ];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function wp_date( $fmt, $ts = null ) { $d = new DateTime( 'now', wp_timezone() ); $d->setTimestamp( $ts ?? time() ); return $d->format( $fmt ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

// NAWS_Astro liest Koordinaten aus $wpdb; die Sonnenbahn bekommt sie hier direkt.
class NAWS_Test_Coords { public static $coords = [ 'lat' => 51.34, 'lng' => 12.37 ]; }

require_once dirname( __DIR__ ) . '/includes/class-naws-astro.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function close( string $name, $got, float $want, float $tol ): void {
    global $passed, $failed;
    if ( is_numeric( $got ) && abs( (float) $got - $want ) <= $tol ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s ± %s, ist %s\n", $name, $want, $tol, var_export( $got, true ) );
}

echo "\nNAWS_Astro::sun_path() — Leipzig, 5. September 2026\n" . str_repeat( '-', 74 ) . "\n";

$lat = 51.34; $lng = 12.37;
$noon = gmmktime( 10, 0, 0, 9, 5, 2026 );            // 12:00 MESZ
$sp   = NAWS_Astro::sun_path( $lat, $lng, $noon, $noon );
check( 'liefert ein Array',                        is_array( $sp ), true );
check( 'Aufgang vor Kulmination vor Untergang',   $sp['sunrise'] < $sp['transit'] && $sp['transit'] < $sp['sunset'], true );
close( 'Aufgang gegen 06:29 MESZ',                 $sp['sunrise'], gmmktime( 4, 29, 0, 9, 5, 2026 ), 300 );
close( 'Untergang gegen 19:48 MESZ',               $sp['sunset'],  gmmktime( 17, 48, 0, 9, 5, 2026 ), 300 );
close( 'Tageslaenge rund 13:19',                   $sp['day_length'], 13 * 3600 + 19 * 60, 300 );
close( 'mittags ist etwas weniger als die Haelfte um', $sp['progress'], 0.42, 0.03 );
check( 'mittags keine Nacht',                      $sp['night_progress'], null );
check( 'im September werden die Tage kuerzer',    $sp['delta_day'] < 0, true );
close( 'um gut drei Minuten',                      $sp['delta_day'], -3 * 60 - 40, 60 );
check( 'laengster Tag laenger als der kuerzeste', $sp['longest'] > $sp['shortest'], true );
close( 'laengster Tag rund 16:41',                 $sp['longest'],  16 * 3600 + 41 * 60, 600 );
close( 'kuerzester Tag rund 7:56',                 $sp['shortest'], 7 * 3600 + 56 * 60, 600 );
check( 'Daemmerung liegt vor dem Aufgang',        $sp['dawn'] < $sp['sunrise'], true );

$at_rise = NAWS_Astro::sun_path( $lat, $lng, $sp['sunrise'], $noon );
close( 'beim Aufgang steht progress auf 0',        $at_rise['progress'], 0.0, 0.001 );
$at_set = NAWS_Astro::sun_path( $lat, $lng, $sp['sunset'], $noon );
close( 'beim Untergang auf 1',                     $at_set['progress'], 1.0, 0.001 );
$at_transit = NAWS_Astro::sun_path( $lat, $lng, $sp['transit'], $noon );
close( 'bei der Kulmination nahe 0.5',             $at_transit['progress'], 0.5, 0.01 );

$night = NAWS_Astro::sun_path( $lat, $lng, gmmktime( 21, 0, 0, 9, 5, 2026 ), $noon ); // 23:00 MESZ
check( 'nachts kein progress',                     $night['progress'], null );
check( 'nachts ein night_progress zwischen 0 und 1', $night['night_progress'] > 0 && $night['night_progress'] < 1, true );
$early = NAWS_Astro::sun_path( $lat, $lng, gmmktime( 1, 0, 0, 9, 5, 2026 ), $noon ); // 03:00 MESZ
check( 'vor dem Aufgang: spaete Nacht',            $early['night_progress'] > 0.5, true );

echo "\nDie andere Halbkugel und der Rand\n" . str_repeat( '-', 74 ) . "\n";

$syd = NAWS_Astro::sun_path( -33.87, 151.21, gmmktime( 2, 0, 0, 9, 5, 2026 ), gmmktime( 2, 0, 0, 9, 5, 2026 ) );
check( 'Sydney: im September werden die Tage laenger', $syd['delta_day'] > 0, true );
check( 'Sydney: der laengste Tag ist laenger als der kuerzeste', $syd['longest'] > $syd['shortest'], true );
close( 'Sydney: laengster Tag rund 14:24',         $syd['longest'], 14 * 3600 + 24 * 60, 600 );

$polar = NAWS_Astro::sun_path( 78.22, 15.63, gmmktime( 12, 0, 0, 6, 21, 2026 ), gmmktime( 12, 0, 0, 6, 21, 2026 ) ); // Longyearbyen, Polartag
check( 'Polartag: null',                           $polar, null );
```

Die Zusammenfassung (`printf( "%d bestanden…` und `exit`) kommt in Task 7 ans Ende, wenn der Template-Abschnitt dazukommt. Für diesen Task provisorisch anhängen:

```php
echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-sunpath.php`
Expected: `Fatal error: Uncaught Error: Call to undefined method NAWS_Astro::sun_path()`

- [ ] **Step 3: `sun_path()` schreiben**

In `includes/class-naws-astro.php` nach `sun_times()` einfügen:

```php
    /**
     * The sun's day over one place: the events as timestamps, and where in
     * the day (or the night) a given moment sits.
     *
     * Pure — date_sun_info() is PHP core — so a template can format the
     * timestamps in the site's timezone and a test can check the arithmetic
     * without WordPress.
     *
     * @param float    $lat Latitude.
     * @param float    $lng Longitude.
     * @param int|null $now The moment to place the sun at; defaults to time().
     * @param int|null $day Any timestamp inside the calendar day wanted;
     *                      defaults to $now. Callers pass the site's local
     *                      noon so that "today" is the site's today, not UTC's.
     * @return array|null Null on a polar day or night, when there is no
     *                    sunrise or sunset to draw.
     */
    public static function sun_path( float $lat, float $lng, ?int $now = null, ?int $day = null ): ?array {
        $now = $now ?? time();
        $day = $day ?? $now;

        $today = date_sun_info( $day, $lat, $lng );
        if ( ! is_int( $today['sunrise'] ) || ! is_int( $today['sunset'] ) || ! is_int( $today['transit'] ) ) {
            return null;
        }

        $length = $today['sunset'] - $today['sunrise'];

        $yesterday = date_sun_info( $day - 86400, $lat, $lng );
        $tomorrow  = date_sun_info( $day + 86400, $lat, $lng );
        $len_y     = ( is_int( $yesterday['sunrise'] ) && is_int( $yesterday['sunset'] ) ) ? $yesterday['sunset'] - $yesterday['sunrise'] : $length;

        $progress = null;
        $night    = null;
        if ( $now >= $today['sunrise'] && $now <= $today['sunset'] ) {
            $progress = ( $now - $today['sunrise'] ) / max( 1, $length );
        } elseif ( $now < $today['sunrise'] ) {
            $from  = is_int( $yesterday['sunset'] ) ? $yesterday['sunset'] : $today['sunrise'] - 43200;
            $night = ( $now - $from ) / max( 1, $today['sunrise'] - $from );
        } else {
            $to    = is_int( $tomorrow['sunrise'] ) ? $tomorrow['sunrise'] : $today['sunset'] + 43200;
            $night = ( $now - $today['sunset'] ) / max( 1, $to - $today['sunset'] );
        }

        // The year's extremes at this latitude: the solstices, and whichever
        // of the two is longer — that is what makes it right south of the
        // equator too.
        $year  = (int) gmdate( 'Y', $day );
        $june  = date_sun_info( gmmktime( 12, 0, 0, 6, 21, $year ), $lat, $lng );
        $dec   = date_sun_info( gmmktime( 12, 0, 0, 12, 21, $year ), $lat, $lng );
        $len_j = ( is_int( $june['sunrise'] ) && is_int( $june['sunset'] ) ) ? $june['sunset'] - $june['sunrise'] : $length;
        $len_d = ( is_int( $dec['sunrise'] ) && is_int( $dec['sunset'] ) ) ? $dec['sunset'] - $dec['sunrise'] : $length;

        return [
            'dawn'           => is_int( $today['civil_twilight_begin'] ) ? $today['civil_twilight_begin'] : null,
            'sunrise'        => $today['sunrise'],
            'transit'        => $today['transit'],
            'sunset'         => $today['sunset'],
            'dusk'           => is_int( $today['civil_twilight_end'] ) ? $today['civil_twilight_end'] : null,
            'day_length'     => $length,
            'progress'       => $progress === null ? null : max( 0.0, min( 1.0, $progress ) ),
            'night_progress' => $night === null ? null : max( 0.0, min( 1.0, $night ) ),
            'delta_day'      => $length - $len_y,
            'longest'        => max( $len_j, $len_d ),
            'shortest'       => min( $len_j, $len_d ),
        ];
    }
```

- [ ] **Step 4: Test laufen lassen**

Run: `php tests/test-sunpath.php`
Expected: alle Prüfungen bestanden. Weichen Aufgang oder Untergang um mehr als fünf Minuten ab, stimmt die Referenz nicht die Rechnung: die Zeiten der Infobar auf der Produktseite (Sonnenaufgang 06:29, Untergang 19:48 am 05.09.2026) sind die Vergleichswerte.

- [ ] **Step 5: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-astro.php tests/test-sunpath.php
git commit -m "Compute the sun's day as timestamps and a position on the arc"
```

---

## Task 7: Das Bild der Sonnenbahn

**Files:**
- Create: `templates/sunpath.php`
- Modify: `includes/class-naws-shortcodes.php` (Registrierung, Handler nach `sc_on_this_day()`)
- Modify: `includes/class-naws-labels.php` (nach `case 'otd_record':`)
- Modify: `assets/css/frontend.css` (nach dem Block aus Task 5)
- Test: `tests/test-sunpath.php` (Abschnitt anhängen, vor der Zusammenfassung)

**Interfaces:**
- Consumes: `NAWS_Astro::sun_path()`, `NAWS_Astro::get_coords()`, `wp_timezone()`, `wp_date()`
- Produces: Shortcode `naws_sunpath`; Template-Variablen `$atts`, `$naws_coords` (Test), `$naws_now` (Test); Labels `sun_title`, `sun_aria`, `sun_day_length`, `sun_shorter`, `sun_longer`, `sun_same`, `sun_extremes`, `sun_minutes`

- [ ] **Step 1: Den fehlschlagenden Test schreiben**

In `tests/test-sunpath.php` vor `echo "\n" . str_repeat( '-', 74 ) . "\n";` anhängen:

```php
echo "\ntemplates/sunpath.php\n" . str_repeat( '-', 74 ) . "\n";

function render_sun( array $atts, ?array $naws_coords, int $naws_now ): string {
    ob_start();
    include dirname( __DIR__ ) . '/templates/sunpath.php';
    return ob_get_clean();
}

$day_html = render_sun( [ 'title' => 'Sun path' ], [ 'lat' => 51.34, 'lng' => 12.37 ], gmmktime( 10, 0, 0, 9, 5, 2026 ) );
check( 'Wurzel mit Klasse',                    str_contains( $day_html, '<section class="naws-sun">' ), true );
check( 'Ueberschrift',                         str_contains( $day_html, '<h3 class="naws-sun-title">Sun path</h3>' ), true );
check( 'ein SVG mit Rolle und Beschreibung',   (bool) preg_match( '/<svg[^>]*role="img"[^>]*aria-label="[^"]*06:2\d[^"]*19:4\d[^"]*"/', $day_html ), true );
check( 'der Bogen ist ein Halbkreis um (200,170)', str_contains( $day_html, 'd="M 60 170 A 140 140 0 0 1 340 170"' ), true );
check( 'am Tag steht die Sonne ueber der Linie', (bool) preg_match( '/<circle class="naws-sun-disc"[^>]*cy="([0-9.]+)"/', $day_html, $m ) && (float) $m[1] < 170, true );
check( 'der vergangene Teil ist gezeichnet',   str_contains( $day_html, 'class="naws-sun-done"' ), true );
check( 'Aufgang, Mittag, Untergang beschriftet', substr_count( $day_html, '<text' ), 3 );
check( 'Textzeile mit Tageslaenge',            (bool) preg_match( '#<p class="naws-sun-text">Day length 13:1\d · \d+ min shorter than yesterday · longest day 16:[34]\d, shortest 7:5\d</p>#', $day_html ), true );
check( 'kein style-Block',                     str_contains( $day_html, '<style' ), false );
check( 'keine MAC-Adresse',                    (bool) preg_match( '/[0-9a-f]{2}(:[0-9a-f]{2}){5}/i', $day_html ), false );

$night_html = render_sun( [ 'title' => '' ], [ 'lat' => 51.34, 'lng' => 12.37 ], gmmktime( 21, 0, 0, 9, 5, 2026 ) );
check( 'nachts steht die Sonne unter der Linie', (bool) preg_match( '/<circle class="naws-sun-disc naws-sun-disc--night"[^>]*cy="([0-9.]+)"/', $night_html, $m ) && (float) $m[1] > 170, true );
check( 'nachts kein vergangener Teil',         str_contains( $night_html, 'class="naws-sun-done"' ), false );
check( 'ohne Titel keine Ueberschrift',        str_contains( $night_html, '<h3' ), false );

check( 'ohne Koordinaten nichts',              render_sun( [ 'title' => 'x' ], null, gmmktime( 10, 0, 0, 9, 5, 2026 ) ), '' );
check( 'Polartag: nichts',                     render_sun( [ 'title' => 'x' ], [ 'lat' => 78.22, 'lng' => 15.63 ], gmmktime( 12, 0, 0, 6, 21, 2026 ) ), '' );
```

- [ ] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-sunpath.php`
Expected: `Warning: include(.../templates/sunpath.php): Failed to open stream` und Fehlschläge im neuen Abschnitt.

- [ ] **Step 3: Die Labels**

In `includes/class-naws-labels.php` nach `case 'otd_record':`:

```php

        // [naws_sunpath] (since 1.9.11).
        case 'sun_title':                          return __( 'Sun path', 'xtx-integration-for-netatmo' );
        case 'sun_aria':                           return __( 'Sun path: sunrise %1$s, sunset %2$s, day length %3$s.', 'xtx-integration-for-netatmo' );
        case 'sun_day_length':                     return __( 'Day length %s', 'xtx-integration-for-netatmo' );
        case 'sun_shorter':                        return __( '%s shorter than yesterday', 'xtx-integration-for-netatmo' );
        case 'sun_longer':                         return __( '%s longer than yesterday', 'xtx-integration-for-netatmo' );
        case 'sun_same':                           return __( 'as long as yesterday', 'xtx-integration-for-netatmo' );
        case 'sun_extremes':                       return __( 'longest day %1$s, shortest %2$s', 'xtx-integration-for-netatmo' );
        case 'sun_minutes':                        return __( '%d min', 'xtx-integration-for-netatmo' );
```

- [ ] **Step 4: Das Template**

Neue Datei `templates/sunpath.php`:

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_sunpath title=""]
 *
 * The sun on its arc over the station: horizon, a dashed semicircle, the
 * part already travelled drawn solid, and the sun where it stands at the
 * moment the page is built. At night it sits below the horizon on a
 * smaller arc. No script: the picture is right when it is made, and a
 * cached page shows the sun where it was when the cache was filled — the
 * shortcode's documentation says so.
 *
 * Geometry (viewBox 400 × 220): horizon y = 170 from x = 30 to 370, arc
 * centre (200,170), radius 140, so the zenith is at y = 30. Sun angle
 * θ = π·(1 − progress): x = 200 + 140·cos θ, y = 170 − 140·sin θ. Night arc:
 * radius 60 below the line, θ = π·night_progress, moving from the west
 * (right) to the east (left), where it will rise.
 *
 * Expected variables:
 * @var array      $atts        Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_coords [lat, lng]; set by the tests, null on a real page
 * @var int|null   $naws_now    The moment; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.11
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$coords = $naws_coords ?? NAWS_Astro::get_coords();
if ( ! $coords ) {
    return;
}

$now = $naws_now ?? time();
// Local noon of the site's calendar day, so "today" is the site's today.
$noon_dt = new DateTime( 'now', wp_timezone() );
$noon_dt->setTimestamp( $now );
$noon_dt->setTime( 12, 0, 0 );
$sun = NAWS_Astro::sun_path( (float) $coords['lat'], (float) $coords['lng'], $now, $noon_dt->getTimestamp() );
if ( $sun === null ) {
    return;
}

$title    = (string) ( $atts['title'] ?? '' );
$time_fmt = get_option( 'time_format', 'H:i' );
$clock    = static fn( int $ts ): string => wp_date( $time_fmt, $ts );
$hm       = static fn( int $s ): string => sprintf( '%d:%02d', intdiv( $s, 3600 ), intdiv( $s % 3600, 60 ) );

$rise = $clock( $sun['sunrise'] );
$set  = $clock( $sun['sunset'] );
$peak = $clock( $sun['transit'] );

$is_day = $sun['progress'] !== null;
if ( $is_day ) {
    $theta = M_PI * ( 1 - $sun['progress'] );
    $sx    = 200 + 140 * cos( $theta );
    $sy    = 170 - 140 * sin( $theta );
} else {
    $theta = M_PI * $sun['night_progress'];
    $sx    = 200 + 60 * cos( $theta );
    $sy    = 170 + 60 * sin( $theta );
}
$fmt = static fn( float $v ): string => number_format( $v, 1, '.', '' );

$delta_min = (int) round( abs( $sun['delta_day'] ) / 60 );
if ( abs( $sun['delta_day'] ) <= 30 ) {
    $delta_text = naws_label( 'sun_same' );
} else {
    $delta_text = sprintf( naws_label( $sun['delta_day'] < 0 ? 'sun_shorter' : 'sun_longer' ), sprintf( naws_label( 'sun_minutes' ), $delta_min ) );
}
$text = sprintf( naws_label( 'sun_day_length' ), $hm( $sun['day_length'] ) )
      . ' · ' . $delta_text
      . ' · ' . sprintf( naws_label( 'sun_extremes' ), $hm( $sun['longest'] ), $hm( $sun['shortest'] ) );
$aria = sprintf( naws_label( 'sun_aria' ), $rise, $set, $hm( $sun['day_length'] ) );
?>
<section class="naws-sun">
  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-sun-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>
  <svg class="naws-sun-svg" viewBox="0 0 400 220" role="img" aria-label="<?php echo esc_attr( $aria ); ?>">
    <line class="naws-sun-horizon" x1="30" y1="170" x2="370" y2="170"/>
    <path class="naws-sun-arc" d="M 60 170 A 140 140 0 0 1 340 170"/>
    <?php if ( $is_day ) : ?>
      <path class="naws-sun-done" d="M 60 170 A 140 140 0 0 1 <?php echo esc_attr( $fmt( $sx ) ); ?> <?php echo esc_attr( $fmt( $sy ) ); ?>"/>
      <circle class="naws-sun-disc" cx="<?php echo esc_attr( $fmt( $sx ) ); ?>" cy="<?php echo esc_attr( $fmt( $sy ) ); ?>" r="10"/>
    <?php else : ?>
      <path class="naws-sun-arc naws-sun-arc--night" d="M 260 170 A 60 60 0 0 1 140 170"/>
      <circle class="naws-sun-disc naws-sun-disc--night" cx="<?php echo esc_attr( $fmt( $sx ) ); ?>" cy="<?php echo esc_attr( $fmt( $sy ) ); ?>" r="8"/>
    <?php endif; ?>
    <text class="naws-sun-label" x="30" y="192"><?php echo esc_html( $rise ); ?></text>
    <text class="naws-sun-label" x="200" y="18" text-anchor="middle"><?php echo esc_html( $peak ); ?></text>
    <text class="naws-sun-label" x="370" y="192" text-anchor="end"><?php echo esc_html( $set ); ?></text>
  </svg>
  <p class="naws-sun-text"><?php echo esc_html( $text ); ?></p>
</section>
```

- [ ] **Step 5: Handler, Registrierung, Stile**

In `includes/class-naws-shortcodes.php` in der Registrierung nach `add_shortcode( 'naws_on_this_day', … );`:

```php
        add_shortcode( 'naws_sunpath',     [ $this, 'sc_sunpath' ] );
```

Nach `sc_on_this_day()`:

```php
    // ----------------------------------------------------------------
    // [naws_sunpath title=""]
    // The sun on its arc over the station, since 1.9.11
    // ----------------------------------------------------------------
    public function sc_sunpath( $atts ) {
        $this->enqueue_frontend_styles();

        $atts = shortcode_atts( [
            'title' => null,
        ], $atts, 'naws_sunpath' );

        if ( $atts['title'] === null ) {
            $atts['title'] = naws_label( 'sun_title' );
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sunpath.php';
        return ob_get_clean();
    }
```

In `assets/css/frontend.css` nach dem Block aus Task 5:

```css
/* ══════════════════════════════════════════════════════════════════
   [naws_sunpath] — die Sonne auf ihrem Tagesbogen
   ══════════════════════════════════════════════════════════════════ */

.naws-sun {
  max-width: 480px;
  background: var(--naws-surface, #fff);
  border: 1px solid var(--naws-border, #e0eeee);
  border-radius: var(--naws-radius, 12px);
  padding: 14px;
  color: var(--naws-text, #427272);
}
.naws-sun-title { margin: 0 0 8px; font-size: 1.1em; color: var(--naws-text-dark, #2d5252); }
.naws-sun-svg   { display: block; width: 100%; height: auto; font-size: 13px; }
.naws-sun-horizon { stroke: var(--naws-border, #e0eeee); stroke-width: 2; }
.naws-sun-arc   { fill: none; stroke: var(--naws-border, #e0eeee); stroke-width: 2; stroke-dasharray: 4 6; }
.naws-sun-arc--night { stroke-dasharray: 3 5; opacity: .7; }
.naws-sun-done  { fill: none; stroke: var(--naws-warning, #f59e0b); stroke-width: 3; stroke-linecap: round; }
.naws-sun-disc  { fill: var(--naws-warning, #f59e0b); }
.naws-sun-disc--night { opacity: .45; }
.naws-sun-label { fill: var(--naws-text-muted, #7aa0a0); font-variant-numeric: tabular-nums; }
.naws-sun-text  { margin: 6px 0 0; font-size: 12px; color: var(--naws-text-muted, #7aa0a0); }
```

- [ ] **Step 6: Test laufen lassen**

Run: `php tests/test-sunpath.php`
Expected: alle Prüfungen bestanden.

- [ ] **Step 7: Die ganze Suite und `php -l`**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done; php -l templates/sunpath.php; php -l includes/class-naws-shortcodes.php; php -l includes/class-naws-labels.php`
Expected: keine FAIL-Zeile, dreimal `No syntax errors`.

- [ ] **Step 8: Commit**

```bash
git add templates/sunpath.php includes/class-naws-shortcodes.php includes/class-naws-labels.php assets/css/frontend.css tests/test-sunpath.php
git commit -m "Draw the sun on its arc over the station"
```

---

## Task 8: Deutsch und Norwegisch

**Files:**
- Modify: `languages/xtx-integration-for-netatmo.pot`, `docs/i18n/catalog/xtx-integration-for-netatmo-{de_DE,nb_NO}.po`, `languages/xtx-integration-for-netatmo-{de_DE,nb_NO}.mo`

**Interfaces:**
- Consumes: alle `__()`/`_x()`/`_n()`-Aufrufe aus Task 5 und 7
- Produces: beide Kataloge vollständig, `.mo` neu gebaut

- [ ] **Step 1: Katalog erzeugen und zusammenführen**

Run:
```bash
php docs/i18n/catalog/makepot.php
php docs/i18n/catalog/merge_po.php de_DE
php docs/i18n/catalog/merge_po.php nb_NO
```
Expected: beide melden die neuen Einträge als „offen" (rund 40: 27 aus Task 5, 8 aus Task 7, dazu die `_n()`-Paare `%d day`/`%d days`), 0 weggefallen.

- [ ] **Step 2: Übersetzen**

Die offenen `msgstr` in beiden `.po` füllen — **msgid nie neu tippen**, sondern die Datei bearbeiten (Muster: `docs/i18n/glotpress/imports/`, oder ein Skript nach dem Vorbild von `fill-po.php` aus der Sitzung vom 05.09.). Die Sätze:

| msgid | de_DE | nb_NO |
| --- | --- | --- |
| Records | Rekorde | Rekorder |
| Records %d | Rekorde %d | Rekorder %d |
| Hottest day | Heißester Tag | Varmeste dag |
| Coldest night | Kälteste Nacht | Kaldeste natt |
| Warmest night | Wärmste Nacht | Varmeste natt |
| Coldest day | Kältester Tag | Kaldeste dag |
| Largest daily range | Größte Tagesspanne | Største døgnvariasjon |
| Warmest month | Wärmster Monat | Varmeste måned |
| Coldest month | Kältester Monat | Kaldeste måned |
| Wettest day | Nassester Tag | Våteste dag |
| Wettest month | Nassester Monat | Våteste måned |
| Longest dry spell | Längste Trockenperiode | Lengste tørkeperiode |
| Longest wet spell | Längste Regenperiode | Lengste regnperiode |
| Strongest gust | Stärkste Böe | Sterkeste vindkast |
| Longest frost period | Längste Frostperiode | Lengste frostperiode |
| Longest heat wave | Längste Hitzewelle | Lengste hetebølge |
| Longest run of summer days | Längste Sommerserie | Lengste rekke sommerdager |
| Record (table column) | Rekord | Rekord |
| Value (table column) | Wert | Verdi |
| When (table column) | Wann | Når |
| Since %1$s · %2$s with readings | Seit %1$s · %2$s mit Messwerten | Siden %1$s · %2$s med målinger |
| %d day / %d days | %d Tag / %d Tage | %d dag / %d dager |
| This day in earlier years | Dieser Tag in früheren Jahren | Denne dagen i tidligere år |
| Year | Jahr | År |
| Low (daily minimum) | Tiefst | Laveste |
| High (daily maximum) | Höchst | Høyeste |
| Mean (daily average) | Mittel | Middel |
| Rain (daily sum) | Regen | Nedbør |
| record for this day | Rekord für diesen Tag | rekord for denne dagen |
| Sun path | Sonnenbahn | Solbane |
| Sun path: sunrise %1$s, sunset %2$s, day length %3$s. | Sonnenbahn: Aufgang %1$s, Untergang %2$s, Tageslänge %3$s. | Solbane: soloppgang %1$s, solnedgang %2$s, daglengde %3$s. |
| Day length %s | Tageslänge %s | Daglengde %s |
| %s shorter than yesterday | %s kürzer als gestern | %s kortere enn i går |
| %s longer than yesterday | %s länger als gestern | %s lengre enn i går |
| as long as yesterday | so lang wie gestern | like lang som i går |
| longest day %1$s, shortest %2$s | längster Tag %1$s, kürzester %2$s | lengste dag %1$s, korteste %2$s |
| %d min | %d Min. | %d min |

Existiert eine msgid schon (etwa „Year" oder „Rain" ohne Kontext), zeigt `merge_po.php` sie nicht als offen — dann nichts tun. Das Paar `%d day` / `%d days` steht als `msgid` + `msgid_plural` mit `msgstr[0]` und `msgstr[1]`; ein Füll-Skript muss diese Form kennen (Deutsch: `%d Tag` / `%d Tage`, Norwegisch: `%d dag` / `%d dager`).

- [ ] **Step 3: `.mo` bauen und prüfen**

Run:
```bash
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po languages/xtx-integration-for-netatmo-de_DE.mo
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po languages/xtx-integration-for-netatmo-nb_NO.mo
php docs/i18n/catalog/merge_po.php de_DE
php docs/i18n/catalog/merge_po.php nb_NO
php tests/test-mo-files.php
```
Expected: beide `merge_po` melden `0 offen`; `test-mo-files.php` grün.

- [ ] **Step 4: Commit**

```bash
git add languages docs/i18n/catalog
git commit -m "Say the records and the sun path in German and Norwegian"
```

---

## Task 9: Dokumentation

**Files:**
- Modify: `admin/views/shortcodes.php` (nach der Karte `[naws_heatmap]` ~Zeile 248)
- Modify: `readme.txt` (Zeile 29 „10 Shortcodes", Liste ~Zeile 44–56)
- Modify: `README.md` (Tabelle ~Zeile 37–47)
- Modify: `CHANGELOG.md` (`## [Unreleased]` → `### Added`)
- Modify: `docs/site/website.de.json`, `docs/site/website.en.json`
- Modify: `docs/superpowers/specs/2026-09-05-rekorde-sonnenbahn-design.md` (Abschnitt 2.2: `format_delta()` heißt `delta_parts()` und gibt `[value, unit]`)

- [ ] **Step 1: Die drei Karten auf der Shortcode-Seite**

In `admin/views/shortcodes.php` nach dem `</div>` der Heatmap-Karte einfügen:

```php
    <div class="naws-sc-card">
        <h3><code>[naws_records]</code></h3>
        <p><?php esc_html_e( 'Fifteen records from the daily summary — hottest day, coldest night, wettest month, longest dry spell, strongest gust and more — each with its date, as tiles or as a table.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_records]</pre><button class="naws-copy-btn" data-copy='[naws_records]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>year</code></td><td><?php esc_html_e( 'Records of one year only. Empty means since the first day with readings.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>records</code></td><td><?php esc_html_e( 'Comma-separated keys to show, in that order: hottest_day, coldest_night, warmest_night, coldest_day, widest_range, warmest_month, coldest_month, wettest_day, wettest_month, longest_dry_spell, longest_wet_spell, strongest_gust, longest_frost, longest_heat_wave, longest_summer_run. Unknown keys are ignored.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'all', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>layout</code></td><td><?php esc_html_e( 'cards or table', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">cards</span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Heading; an empty title="" leaves it out', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo esc_html( naws_label( 'rec_title' ) ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_records year="2025"]</code> &rarr; <?php esc_html_e( 'the records of 2025', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_records records="hottest_day,coldest_night,wettest_day" layout="table"]</code> &rarr; <?php esc_html_e( 'three records as a table', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_on_this_day]</code></h3>
        <p><?php esc_html_e( 'This calendar day in every earlier year: low, high, mean and rain, with the day’s record marked in each column. The running year is left out.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_on_this_day]</pre><button class="naws-copy-btn" data-copy='[naws_on_this_day]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>date</code></td><td><?php esc_html_e( 'MM-DD or YYYY-MM-DD; the year only decides which years count as earlier. Anything else is today.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'today', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Heading; an empty title="" leaves it out', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo esc_html( naws_label( 'otd_title' ) ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_on_this_day date="12-24"]</code> &rarr; <?php esc_html_e( 'Christmas Eve in earlier years', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_sunpath]</code></h3>
        <p><?php esc_html_e( 'The sun on its arc over the station: sunrise, solar noon and sunset, the part of the day already travelled, and the sun where it stands. Below it the day length, the change since yesterday, and the year’s longest and shortest day. Computed from the station’s coordinates. No script: the sun stands where it stood when the page was built, so a page cache shows it until the cache is refreshed.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_sunpath]</pre><button class="naws-copy-btn" data-copy='[naws_sunpath]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Heading; an empty title="" leaves it out', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo esc_html( naws_label( 'sun_title' ) ); ?></span></td></tr>
        </table>
    </div>
```

Die neuen `esc_html_e()`-Sätze dieser Karten landen ebenfalls im Katalog — nach diesem Schritt **Task 8, Schritte 1–3 wiederholen** (makepot, merge, übersetzen, make_mo), damit die Kataloge vollständig bleiben. Übersetzungen der Kartentexte: sinngemäß, nicht wörtlich; Deutsch und Norwegisch.

- [ ] **Step 2: readme.txt**

Zeile 29 ersetzen:

```
* **14 Shortcodes** – Dashboard, current readings, infobar, single value, computed value, history charts, heatmap, records, this day in earlier years, sun path, forecast, table, widget, weather icon
```

In der Shortcode-Liste nach der `[naws_heatmap]`-Zeile:

```
* `[naws_records]` – Fifteen records from the daily summary with their dates, as tiles or a table (`year`, `records`, `layout`, `title`)
* `[naws_on_this_day]` – This calendar day in every earlier year, with the day's records marked (`date`, `title`)
* `[naws_sunpath]` – The sun on its arc over the station, with sunrise, solar noon, sunset and the day length (`title`)
```

- [ ] **Step 3: README.md**

In der Tabelle nach der `[naws_heatmap]`-Zeile:

```
| `[naws_records year="2025"]` | Fifteen records from the daily summary — hottest day, longest dry spell, strongest gust … — with their dates, as tiles or a table |
| `[naws_on_this_day]` | This calendar day in every earlier year, low/high/mean/rain, records marked |
| `[naws_sunpath]` | The sun on its arc over the station: sunrise, solar noon, sunset, day length and its change since yesterday |
```

- [ ] **Step 4: CHANGELOG.md**

Unter `## [Unreleased]` → `### Added`, nach dem Absatz zum Widget-Farbschema:

```markdown
- **`[naws_records]` — fifteen records from the daily summary.** Hottest day, coldest night, warmest night, coldest day, largest daily range, warmest and coldest month, wettest day and month, longest dry and wet spell, strongest gust, longest frost period, heat wave and run of summer days — each with its date, as tiles or a table, since the first day with readings or for one year (`year="2025"`). The arithmetic is a pure class over the same daily rows `[naws_calc]` reads, with the same station and period logic; a tie goes to the earlier date, a month needs twenty days to compete, and a gap in the data breaks a run rather than bridging it.

- **`[naws_on_this_day]` — this calendar day in every earlier year.** Low, high, mean and rain, newest year first, with the day's record marked in each column. The running year is left out: its row is written at the end of the day.

- **`[naws_sunpath]` — the sun on its arc over the station.** An inline SVG with sunrise, solar noon and sunset, the part of the day already travelled drawn solid, and the sun where it stands; at night it sits below the horizon. Under it the day length, the change since yesterday, and the year's longest and shortest day at the station's latitude. Computed from the station's coordinates with PHP's own sun arithmetic, no script.

### Changed

- `NAWS_Climate::max_streak()` now derives from `longest_run()`, which also returns the dates; the numbers are unchanged. `NAWS_Database::get_daily_summaries()` hands out `gust_max` on request. The calculator's station and period helpers are public, so records and computed values can never disagree on which rows they looked at.
```

- [ ] **Step 5: Website-JSON**

In beiden Dateien `docs/site/website.{de,en}.json` zwei Vorhaben **vor** `widget-farbschema` einfügen und `aktualisiert` auf das Datum des Commits setzen. Deutsch:

```json
{ "id": "rekorde", "titel": "Rekorde und dieser Tag in früheren Jahren", "satz": "[naws_records] holt aus der Tagesübersicht, was darin steckt: heißester Tag, kälteste Nacht, nassester Monat, längste Trockenperiode, stärkste Böe — fünfzehn Rekorde mit Datum, als Kacheln oder Tabelle, seit Aufzeichnungsbeginn oder für ein Jahr. [naws_on_this_day] zeigt dazu, was am selben Datum in jedem früheren Jahr war.", "ab": null, "bild": null },
{ "id": "sonnenbahn", "titel": "Die Sonne auf ihrem Bogen", "satz": "[naws_sunpath] zeichnet den Tagesbogen der Sonne über der Station: Aufgang, Mittag, Untergang, der schon vergangene Teil und die Sonne, wo sie gerade steht. Darunter Tageslänge, die Änderung seit gestern und der längste und kürzeste Tag des Jahres am Standort.", "ab": null, "bild": null }
```

Englisch:

```json
{ "id": "rekorde", "titel": "Records, and this day in earlier years", "satz": "[naws_records] pulls out what the daily summary holds: hottest day, coldest night, wettest month, longest dry spell, strongest gust — fifteen records with their dates, as tiles or a table, since the first reading or for one year. [naws_on_this_day] adds what the same date looked like in every earlier year.", "ab": null, "bild": null },
{ "id": "sonnenbahn", "titel": "The sun on its arc", "satz": "[naws_sunpath] draws the sun's day over the station: sunrise, noon, sunset, the part already travelled and the sun where it stands. Below it the day length, the change since yesterday and the year's longest and shortest day at the station.", "ab": null, "bild": null }
```

Die Dateien sind CRLF und mit vier Leerzeichen eingerückt; mit `json_encode( …, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )` und dem Zeilenende der Datei zurückschreiben (Muster: `patch-scheme-2.php` aus der Sitzung vom 05.09.), danach `json_decode()` als Gültigkeitsprüfung.

- [ ] **Step 6: Die Spec nachziehen**

In `docs/superpowers/specs/2026-09-05-rekorde-sonnenbahn-design.md`, Abschnitt 2.2, den Satz zu `format_delta()` ersetzen durch:

```
  Die Klasse liefert die Differenz in Kelvin; das Template rechnet mit
  `NAWS_Records::delta_parts( $kelvin )`, das `[ 'value' => float, 'unit' => '°C'|'°F' ]`
  zurückgibt — die Temperatureinheit aus den Einstellungen, der Faktor 1,8 ohne Versatz.
```

- [ ] **Step 7: Die ganze Suite, `php -l`, PHPCS**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done; php -l admin/views/shortcodes.php; vendor\bin\phpcs.bat --report=summary`
Expected: keine FAIL-Zeile, `No syntax errors`, PHPCS ohne Befund.

- [ ] **Step 8: Commit**

```bash
git add admin/views/shortcodes.php readme.txt README.md CHANGELOG.md docs/site/website.de.json docs/site/website.en.json docs/superpowers/specs/2026-09-05-rekorde-sonnenbahn-design.md languages docs/i18n/catalog
git commit -m "Write the records and the sun path down where people look for them"
```

---

## Task 10: Prüfung auf dev und Merge

**Files:** keine Änderung im Repo außer dem Merge.

- [ ] **Step 1: Nach dev bringen**

Die ausgelieferten Dateien dieses Zweigs (alles außer `tests/`, `docs/`, `CHANGELOG.md`, `README.md`) aus `git show HEAD:<pfad>` in ein Staging-Verzeichnis stellen (LF), als ZIP mit `ZipArchive` bauen, per `novamira/create-upload-link` auf dev hochladen und mit einem `execute-php` einspielen, das Dateiliste und md5 prüft, jede PHP-Datei mit `token_get_all( …, TOKEN_PARSE )` parst, vorher nach `wp-content/naws-backup-vor-records-<zeit>/` sichert und danach `opcache_reset()` ruft. Muster: der Deploy des Widget-Farbschemas am 05.09. (Notizen).

- [ ] **Step 2: Eine Testseite**

Auf dev eine Seite `rekorde-test` (Template `elementor_canvas`) mit:

```
[naws_records]
[naws_records year="2025" layout="table" records="hottest_day,coldest_night,wettest_day,strongest_gust"]
[naws_on_this_day]
[naws_on_this_day date="12-24"]
[naws_sunpath]
```

Per curl prüfen: HTTP 200, `naws-rec-tile` 15-mal, `naws-rec-table` einmal mit vier Zeilen, `naws-otd-row` mindestens zweimal, ein `<svg class="naws-sun-svg"`, keine MAC-Adresse, kein rohes `[naws_`. Dann `debug.log` auf neue Zeilen prüfen — keine erwartet.

- [ ] **Step 3: Sichtprüfung**

Mit Playwright einen Screenshot der Seite bei 1000 px und bei 390 px Breite nehmen; auf 390 px muss das Rekordraster ein- bis zweispaltig sein und die Sonnenbahn die volle Breite füllen, ohne waagerechten Überlauf (`document.documentElement.scrollWidth <= innerWidth`).

- [ ] **Step 4: Die Zahlen gegenprüfen**

Die vier Rekorde der Tabelle (heißester Tag, kälteste Nacht, nassester Tag, stärkste Böe) gegen eine direkte Abfrage der Tagesübersicht auf dev stellen (`SELECT day_date, temp_max FROM wp_naws_daily_summary WHERE module_id = station_id ORDER BY temp_max DESC LIMIT 1` usw.). Sonnenaufgang und -untergang der Sonnenbahn gegen die Infobar derselben Seite.

- [ ] **Step 5: Merge**

```bash
git checkout main
git merge --no-ff records-sunpath -m "Merge branch 'records-sunpath'"
git push origin main records-sunpath
```

Kein Versionsbump: 1.9.11 wird nach Franks Ansage geschnitten.

---

## Was danach noch aussteht

Kein Task, aber vor dem Release fällig:

1. **Site-Repo:** `class-xns-schema.php` („Zehn Shortcodes") und `site/landing.de.html` („Alle 10 Shortcodes") auf 14 setzen; die Startseiten DE/EN bekommen die neuen Blöcke, sobald 1.9.11 draußen ist.
2. **GlotPress:** nach dem Release die neuen Sätze auf translate.wordpress.org importieren (Rezept in den Notizen).
3. **Version schneiden** nach dem Release-Ritual: drei Stellen, Changelog in beiden Dateien, ZIP, erst SVN, dann GitHub.
