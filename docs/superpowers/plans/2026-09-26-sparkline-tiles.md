# Sparkline-Kachel und Monatsblock — Umsetzungsplan (Nachtrag)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `[naws_sparkline]` bekommt `layout="tile"` (eine Karte wie in der Demo: Name, Wert, Tief/Hoch bzw. Regenspitze, große Kurve mit Sprechblase) und `layout="month"` (Tagesmittel mit Band, Regen je Tag, Datumsachse), dazu `title` für die Kachel.

**Architecture:** Zwei reine Methoden (`tile_facts()`, `month_facts()`) rechnen die Beschriftungen aus den Daten, die `prepare()` schon liefert (dafür bekommt `prepare()` vier zusätzliche Schlüssel). Zwei Templates legen die vorhandene Sparkline aus `markup()` in eine Karte. `render()` verzweigt nach `layout`; `render_month()` holt Temperatur und Regen über den vorhandenen Datenweg. Farben: Karte aus den Basis-Theme-Variablen, Kurven wie gehabt aus dem Reiter „Sparkline".

**Tech Stack:** PHP 8.0+, WordPress 6.2+, keine neue Bibliothek. Tests: `php tests/test-x.php`.

**Spec:** `docs/superpowers/specs/2026-09-26-sparkline-design.md` §14 (Nachtrag, Commit `741ece1`). Vorlage fürs Aussehen: Demo „Sparkline Leipzig" (https://claude.ai/artifact/WqBPakvVZjQKA8qfntjzgD), Abschnitte „In den Kacheln …" und „Für Zeiträume aus der Tagestabelle".

**Branch:** `sparkline` (weiter auf demselben Zweig, Kopf `741ece1`).

## Global Constraints

- Alle Regeln aus `.superpowers/sdd/2026-09-26-sparkline/constraints.md` gelten unverändert (Text-Domain, Escaping, kein SQL, kein Inline-Skript, `ob_start` gepaart, CRLF-Arbeitskopie, DoD).
- Keine neuen Farbschlüssel. Karte: `--naws-surface`, `--naws-border`, `--naws-text`, `--naws-text-dark`, `--naws-text-muted`, `--naws-font` (Basis-Theme); Kurven: `--naws-sl-*`.
- Kachel: viewBox 200 × 44, dargestellt 100 % × 44 px, Tief/Hoch-Punkte bei Linien immer an. Monat: viewBox 680 × 64 (Band) und 680 × 40 (Regen), dargestellt 100 % × 64 / 40 px; `days` 7–366, Vorgabe 30.
- `layout` ∈ `inline` (Vorgabe, bisheriges Verhalten unverändert), `tile`, `month`; alles andere → `inline`. `title` nur bei `tile`, `sanitize_text_field()`.
- Beschriftungen laut Spec §14 (Frank hat sie so bestätigt): Name ohne „außen" (über `title` setzbar); Regen-Nebenzeile „Am meisten: 14:00–14:30 · 0,6 mm"; Monat „Tagesmittel am 16.9.".
- Zahlen im Plural mit Einheit (Stunden/Tage) laufen über `_n()`, damit „in 30 Tagen" grammatisch stimmt.

## Review Focus

1. **Kachel in einer schmalen Elementor-Spalte oder auf dem Handy:** Erwartung: sie füllt die Spalte, nichts ragt heraus. Test in Task 2 (CSS setzt `width:100%` an Karte, Sparkline und SVG).
2. **Monatsblock auf einer Station ohne Regenmesser:** Erwartung: nur die Temperaturzeile und die Achse. Test in Task 2.
3. **`title` mit HTML oder Anführungszeichen:** Erwartung: escaped, nie als Markup. Test in Task 2.
4. **Fahrenheit/Zoll:** Erwartung: Wert, Tief/Hoch und Regensumme in der Anzeige-Einheit. Test in Task 1.
5. **Tageswerte in der Kachel (`days="30"`):** Erwartung: „mm in 30 days" mit richtigem Plural, Tief/Hoch aus der Reihe. Test in Task 1.

---

### Task 1: Rechnung — `layout`/`title`, Zusatzschlüssel in `prepare()`, `tile_facts()`, `month_facts()`, Beschriftungen

**Files:**
- Modify: `includes/class-naws-sparkline.php` (`normalise_atts()`, `prepare()`, neue Methoden vor `private static function clamp(`)
- Modify: `includes/class-naws-labels.php` (Block nach `case 'sl_preview_aria':`)
- Test: `tests/test-sparkline.php`

**Interfaces:**
- Produces:
  - `normalise_atts()` liefert zusätzlich `layout` (`'inline'|'tile'|'month'`) und `title` (string). Bei `tile`: `w` = 200, `h` = 44, `sized_w`/`sized_h` = false, `show` = `'minmax'`.
  - `prepare()` liefert zusätzlich `name` (string), `unit` (string), `period` (string wie im Vorlesetext) — bei Balken außerdem `total` (float, Anzeige-Einheit).
  - `tile_facts( array $a, array $d ): array` mit `name`, `value`, `unit`, `sub` (alle string; `sub` darf `''` sein).
  - `month_facts( array $dt, ?array $dr, array $dates, int $days ): array` mit `mean_value`, `mean_label`, `rain_value`, `rain_label`, `axis_from`, `axis_to` (alle string; Regenfelder `''` ohne `$dr`).
  - Labels `sl_tile_range`, `sl_tile_peak`, `sl_month_mean`, `sl_month_axis_format`.

- [ ] **Step 1: Tests anhängen** — in `tests/test-sparkline.php` vor der Zeile `echo "\n" . str_repeat( '-', 74 ) . "\n";` am Ende:

```php
echo "\nlayout und title\n" . str_repeat( '-', 74 ) . "\n";
check( 'Vorgabe: inline',                    NAWS_Sparkline::normalise_atts( [] )['layout'], 'inline' );
check( 'Unsinn wird inline',                 NAWS_Sparkline::normalise_atts( [ 'layout' => 'kachel' ] )['layout'], 'inline' );
$t = NAWS_Sparkline::normalise_atts( [ 'layout' => 'TILE', 'width' => '500', 'height' => '10', 'show' => 'value', 'title' => '<b>Aussen</b>' ] );
check( 'tile erkannt',                       $t['layout'], 'tile' );
check( 'Kachel: feste viewBox 200 x 44',     [ $t['w'], $t['h'], $t['sized_w'], $t['sized_h'] ], [ 200, 44, false, false ] );
check( 'Kachel: Tief/Hoch-Punkte immer an',  $t['show'], 'minmax' );
check( 'title ohne Tags',                    $t['title'], 'Aussen' );
check( 'month erkannt',                      NAWS_Sparkline::normalise_atts( [ 'layout' => 'month' ] )['layout'], 'month' );

echo "\ntile_facts()\n" . str_repeat( '-', 74 ) . "\n";
$at = NAWS_Sparkline::normalise_atts( [ 'layout' => 'tile' ] );
$dt = NAWS_Sparkline::prepare( $at, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 1000, 10.0 ], [ 2000, 12.5 ], [ 3000, 11.0 ] ] ] );
check( 'prepare: Name, Einheit, Zeitraum',   [ $dt['name'], $dt['unit'], $dt['period'] ], [ 'Temperature', '°C', '24 hours' ] );
check( 'Linie: Name, Wert, Einheit, Tief/Hoch', NAWS_Sparkline::tile_facts( $at, $dt ), [ 'name' => 'Temperature', 'value' => '11.0', 'unit' => '°C', 'sub' => 'Low 10.0 · High 12.5 °C' ] );
$at2 = NAWS_Sparkline::normalise_atts( [ 'layout' => 'tile', 'title' => 'Temperatur außen' ] );
check( 'title ersetzt den Namen',            NAWS_Sparkline::tile_facts( $at2, $dt )['name'], 'Temperatur außen' );

$ar = NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'layout' => 'tile' ] );
$dr = NAWS_Sparkline::prepare( $ar, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 900, 0.2 ], [ 1200, 0.3 ], [ 4000, 0.6 ] ] ] );
check( 'prepare: Regensumme als Zahl',       $dr['total'], 1.1 );
check( 'Regen: Summe, Zeitraum, nassestes Fenster', NAWS_Sparkline::tile_facts( $ar, $dr ), [ 'name' => 'Rain', 'value' => '1.1', 'unit' => 'mm in 24 hours', 'sub' => 'Most: 02:00–02:30 · 0.6 mm' ] );
$dtrocken = NAWS_Sparkline::prepare( $ar, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 900, 0.0 ] ] ] );
check( 'trocken: keine Nebenzeile',          NAWS_Sparkline::tile_facts( $ar, $dtrocken )['sub'], '' );

$tage = [ '2026-09-24', '2026-09-25', '2026-09-26' ];
$ab = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax', 'layout' => 'tile' ] );
$db = NAWS_Sparkline::prepare( $ab, [ 'dates' => $tage, 'rows' => [ '2026-09-24' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] ] );
check( 'Band: Tief/Hoch aus den Tagesspalten', NAWS_Sparkline::tile_facts( $ab, $db )['sub'], 'Low 8.0 · High 19.0 °C' );
// Review Focus 5: Tageswerte in der Kachel, Plural im Zeitraum.
$ars = NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum', 'days' => '3', 'layout' => 'tile' ] );
$drs = NAWS_Sparkline::prepare( $ars, [ 'dates' => $tage, 'rows' => [ '2026-09-25' => [ 4.2 ] ] ] );
check( 'Regen je Tag: Summe in Tagen',       [ NAWS_Sparkline::tile_facts( $ars, $drs )['value'], NAWS_Sparkline::tile_facts( $ars, $drs )['unit'] ], [ '4.2', 'mm in 3 days' ] );
check( 'Regen je Tag: nassester Tag',        NAWS_Sparkline::tile_facts( $ars, $drs )['sub'], 'Most: 25.09.2026 · 4.2 mm' );

// Review Focus 4: imperiale Einheiten in der Kachel.
$GLOBALS['naws_test_options']['naws_settings'] = [ 'temperature_unit' => 'F', 'rain_unit' => 'in' ];
$dtf = NAWS_Sparkline::prepare( $at, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 1000, 10.0 ], [ 2000, 20.0 ] ] ] );
check( 'Fahrenheit: Wert und Tief/Hoch',     NAWS_Sparkline::tile_facts( $at, $dtf ), [ 'name' => 'Temperature', 'value' => '68.0', 'unit' => '°F', 'sub' => 'Low 50.0 · High 68.0 °F' ] );
$dri = NAWS_Sparkline::prepare( $ar, [ 'from' => 0, 'to' => 86400, 'rows' => [ [ 900, 25.4 ] ] ] );
check( 'Zoll: Summe',                        NAWS_Sparkline::tile_facts( $ar, $dri )['value'], '1.00' );
$GLOBALS['naws_test_options']['naws_settings'] = [];

echo "\nmonth_facts()\n" . str_repeat( '-', 74 ) . "\n";
$am  = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax' ] );
$dm  = NAWS_Sparkline::prepare( $am, [ 'dates' => $tage, 'rows' => [ '2026-09-24' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] ] );
$amr = NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum', 'days' => '3' ] );
$dmr = NAWS_Sparkline::prepare( $amr, [ 'dates' => $tage, 'rows' => [ '2026-09-25' => [ 4.2 ] ] ] );
check( 'Monat: Mittel, Regen, Achse', NAWS_Sparkline::month_facts( $dm, $dmr, $tage, 3 ), [
    'mean_value' => '14.0 °C', 'mean_label' => 'Daily mean on 9/26',
    'rain_value' => '4.2 mm',  'rain_label' => 'Rain in 3 days',
    'axis_from'  => '9/24',    'axis_to'    => '9/26',
] );
check( 'Monat ohne Regenmesser: keine Regenfelder', [ NAWS_Sparkline::month_facts( $dm, null, $tage, 3 )['rain_value'], NAWS_Sparkline::month_facts( $dm, null, $tage, 3 )['rain_label'] ], [ '', '' ] );
```

- [ ] **Step 2:** `php tests/test-sparkline.php` → `Undefined array key "layout"` bzw. `Call to undefined method NAWS_Sparkline::tile_facts()`.

- [ ] **Step 3: `normalise_atts()`** — direkt vor dem `return [` von `normalise_atts()`:

```php
        // layout (since the addendum): inline is the curve in running text
        // as before; tile is the card from the demo; month is the month
        // block, which render() sends to render_month() before it gets here.
        $layout = strtolower( trim( (string) ( $atts['layout'] ?? '' ) ) );
        $layout = in_array( $layout, [ 'tile', 'month' ], true ) ? $layout : 'inline';
        $tile   = $layout === 'tile';
```

Im `return [ … ]` die Zeilen für `w`, `h`, `sized_w`, `sized_h`, `show` ersetzen und zwei Schlüssel anhängen:

```php
            'w'       => $tile ? 200 : ( $w_raw === '' ? self::VIEW_W : self::clamp( intval( $w_raw ), 20, 600 ) ),
            'h'       => $tile ? 44 : ( $h_raw === '' ? self::VIEW_H : self::clamp( intval( $h_raw ), 10, 200 ) ),
            'sized_w' => ! $tile && $w_raw !== '',
            'sized_h' => ! $tile && $h_raw !== '',
            'show'    => $tile ? 'minmax' : $show,
```

und nach `'band'    => $band,`:

```php
            'layout'  => $layout,
            'title'   => sanitize_text_field( (string) ( $atts['title'] ?? '' ) ),
```

Den Docblock von `normalise_atts()` um einen Satz ergänzen: „A tile fixes its own viewBox (200 × 44) and always marks low and high."

- [ ] **Step 4: `prepare()`** — im Balken-`return [` nach `'value' => $total,`:

```php
                'name'   => $name,
                'unit'   => $unit,
                'period' => $period,
                'total'  => $conv( $raw_total ),
```

und im Linien-`return [` nach `'value' => $with( $vals[ count( $vals ) - 1 ] ),`:

```php
            'name'   => $name,
            'unit'   => $unit,
            'period' => $period,
```

Docblock von `prepare()`: „… and name, unit and period for the tile and the month block (bars also carry the total as a number)."

- [ ] **Step 5: Beschriftungen** — in `includes/class-naws-labels.php` nach der Zeile `case 'sl_preview_aria': …`:

```php
        case 'sl_tile_range':                      return /* translators: 1: lowest value, 2: highest value with its unit, e.g. "Low 16.3 · High 24.5 °C". */ __( 'Low %1$s · High %2$s', 'xtx-integration-for-netatmo' );
        case 'sl_tile_peak':                       return /* translators: %s: the wettest window with its amount, e.g. "14:00–14:30 · 0.6 mm". */ __( 'Most: %s', 'xtx-integration-for-netatmo' );
        case 'sl_month_mean':                      return /* translators: %s: a short date, e.g. "9/16". */ __( 'Daily mean on %s', 'xtx-integration-for-netatmo' );
        case 'sl_month_axis_format':               return /* translators: PHP date format for the short dates on the month block's axis; German "j.n.", English "n/j". */ _x( 'n/j', 'sparkline month axis date format', 'xtx-integration-for-netatmo' );
```

- [ ] **Step 6: Die zwei Methoden** — in `class-naws-sparkline.php` vor `private static function clamp(`:

```php
    // ── Tile and month block (addendum) ─────────────────────────────

    /**
     * The words on a tile, from what prepare() returned: the name (or the
     * title), the figure, its unit, and the line under it — low and high
     * for a line or a band, the wettest window for rain (none on a dry
     * span). No query, no markup.
     */
    public static function tile_facts( array $a, array $d ): array {
        $name = $a['title'] !== '' ? $a['title'] : $d['name'];

        if ( $d['kind'] === 'bars' ) {
            if ( $a['source'] === 'raw' ) {
                /* translators: 1: unit such as "mm", 2: number of hours. */
                $unit = sprintf( _n( '%1$s in %2$d hour', '%1$s in %2$d hours', $a['hours'], 'xtx-integration-for-netatmo' ), $d['unit'], $a['hours'] );
            } else {
                /* translators: 1: unit such as "mm", 2: number of days. */
                $unit = sprintf( _n( '%1$s in %2$d day', '%1$s in %2$d days', $a['days'], 'xtx-integration-for-netatmo' ), $d['unit'], $a['days'] );
            }
            $max = $d['sums'] ? max( $d['sums'] ) : 0.0;
            $sub = '';
            if ( $max > 0 ) {
                $i   = (int) array_search( $max, $d['sums'], true );
                $sub = sprintf( naws_label( 'sl_tile_peak' ), $d['tips'][ $i ] );
            }
            return [ 'name' => $name, 'value' => self::number( $a['base'], (float) $d['total'] ), 'unit' => $unit, 'sub' => $sub ];
        }

        $vals = array_column( $d['pts'], 1 );
        $lo   = $d['band'] ? min( array_column( $d['pts'], 2 ) ) : min( $vals );
        $hi   = $d['band'] ? max( array_column( $d['pts'], 3 ) ) : max( $vals );
        return [
            'name'  => $name,
            'value' => self::number( $a['base'], (float) $vals[ count( $vals ) - 1 ] ),
            'unit'  => $d['unit'],
            'sub'   => sprintf( naws_label( 'sl_tile_range' ), self::number( $a['base'], (float) $lo ), self::number( $a['base'], (float) $hi ) . ' ' . $d['unit'] ),
        ];
    }

    /**
     * The words around the month block: the daily mean of the last day
     * with data and its date, the rain total over the span, and the
     * first and last day for the axis. $dr is null when the station has
     * no rain gauge; the rain fields are empty then.
     */
    public static function month_facts( array $dt, ?array $dr, array $dates, int $days ): array {
        $fmt  = naws_label( 'sl_month_axis_format' );
        $last = $dt['pts'][ count( $dt['pts'] ) - 1 ];
        $rain = $dr !== null;
        return [
            'mean_value' => self::number( 'Temperature', (float) $last[1] ) . ' ' . $dt['unit'],
            'mean_label' => sprintf( naws_label( 'sl_month_mean' ), wp_date( $fmt, (int) $last[0] ) ),
            'rain_value' => $rain ? $dr['value'] : '',
            /* translators: %d: number of days. */
            'rain_label' => $rain ? sprintf( _n( 'Rain in %d day', 'Rain in %d days', $days, 'xtx-integration-for-netatmo' ), $days ) : '',
            'axis_from'  => wp_date( $fmt, self::noon( $dates[0] ) ),
            'axis_to'    => wp_date( $fmt, self::noon( $dates[ count( $dates ) - 1 ] ) ),
        ];
    }

```

- [ ] **Step 7:** `php tests/test-sparkline.php` → alle bestanden; die übrigen Sparkline-Tests (`php tests/test-sparkline-render.php`) bleiben grün (inline unverändert). `php -l`, PHPCS auf beide PHP-Dateien.

- [ ] **Step 8: Commit**

```bash
git add includes/class-naws-sparkline.php includes/class-naws-labels.php tests/test-sparkline.php
git commit -m "Sparkline: the words on a tile and around the month block"
```

---

### Task 2: Darstellung — Templates, `tile_markup()`, `month_markup()`, `render()`/`render_month()`, Shortcode, CSS, Farben

**Files:**
- Create: `templates/sparkline-tile.php`, `templates/sparkline-month.php`
- Modify: `includes/class-naws-sparkline.php` (`render()`; neue Methoden `tile_markup()`, `month_markup()`, `render_month()` nach `month_facts()`)
- Modify: `includes/class-naws-shortcodes.php` (`sc_sparkline()`: zwei Attribute, Kommentarzeile)
- Modify: `includes/class-naws-colors.php` (`get_inline_css()`: zwei Selektorlisten)
- Modify: `assets/css/frontend.css` (Regel `color-scheme: only light` oben; neuer Block am Dateiende)
- Test: `tests/test-sparkline-render.php`, `tests/test-color-scheme.php` (Anzahl Template-Wurzeln), `tests/test-appearance.php`

**Interfaces:**
- Consumes: Task 1 (`normalise_atts()` mit `layout`/`title`, `prepare()` mit `name`/`unit`/`period`/`total`, `tile_facts()`, `month_facts()`), `markup()`, `fetch()`.
- Produces: `tile_markup( array $a, array $d ): string`; `month_markup( array $at, array $dt, array $ar, ?array $dr, array $dates, int $days ): string`; `render_month( array $atts ): string`; `render()` verzweigt nach `layout`. Markup-Klassen `naws-sl-card`, `naws-sl-tile`, `naws-sl-tile-name`, `naws-sl-tile-val`, `naws-sl-tile-sub`, `naws-sl-tile-plot`, `naws-sl-month`, `naws-sl-month-row`, `naws-sl-month-row--band`, `naws-sl-month-k`, `naws-sl-month-plot`, `naws-sl-month-axis`.

- [ ] **Step 1: Tests anhängen** — in `tests/test-sparkline-render.php` vor der Zeile `echo "\n" . str_repeat( '-', 74 ) . "\n";` am Ende:

```php
echo "\nKachel\n" . str_repeat( '-', 74 ) . "\n";
$ta = NAWS_Sparkline::normalise_atts( [ 'layout' => 'tile', 'title' => 'Aussen "Garten" & Hof' ] );
$td = [ 'kind' => 'line', 'pts' => [ [ 0, 10.0 ], [ 10, 12.5 ], [ 20, 11.0 ] ], 'band' => false, 'tips' => [ 'a', 'b', 'c' ], 'aria' => 'x', 'value' => '11.0 °C', 'name' => 'Temperature', 'unit' => '°C', 'period' => '24 hours' ];
$tile = NAWS_Sparkline::tile_markup( $ta, $td );
check( 'Wurzel der Karte',                    str_starts_with( $tile, '<div class="naws-sl-card naws-sl-tile">' ), true );
// Review Focus 3: title wird escaped.
check( 'Name escaped',                        str_contains( $tile, '<div class="naws-sl-tile-name">Aussen &quot;Garten&quot; &amp; Hof</div>' ), true );
check( 'Wert mit kleiner Einheit',            str_contains( $tile, '<div class="naws-sl-tile-val">11.0<small>°C</small></div>' ), true );
check( 'Nebenzeile Tief/Hoch',                str_contains( $tile, '<div class="naws-sl-tile-sub">Low 10.0 · High 12.5 °C</div>' ), true );
check( 'Kurve 200 x 44 mit Tief/Hoch',        str_contains( $tile, 'viewBox="0 0 200 44"' ) && substr_count( $tile, 'class="naws-sl-mm"' ) === 2, true );
check( 'Kurve in der Kachel',                 str_contains( $tile, '<div class="naws-sl-tile-plot"><span class="naws-sl naws-sl--line">' ), true );
check( 'Karte: keine Farben, keine id',       (bool) preg_match( '/(fill|stroke)="#|style="[^"]*(color|background)| id=/', $tile ), false );
check( 'endet mit der Karte',                 str_ends_with( $tile, '</div>' ), true );
$trocken = NAWS_Sparkline::tile_markup( NAWS_Sparkline::normalise_atts( [ 'param' => 'Rain', 'layout' => 'tile' ] ), [ 'kind' => 'bars', 'sums' => [ 0.0, 0.0 ], 'tips' => [ '', '' ], 'aria' => 'x', 'value' => '0.0 mm', 'name' => 'Rain', 'unit' => 'mm', 'period' => '24 hours', 'total' => 0.0 ] );
check( 'trockene Regenkachel ohne Nebenzeile', str_contains( $trocken, 'naws-sl-tile-sub' ), false );

echo "\nMonatsblock\n" . str_repeat( '-', 74 ) . "\n";
$tage = [ '2026-09-24', '2026-09-25', '2026-09-26' ];
$ma   = NAWS_Sparkline::normalise_atts( [ 'param' => 'temp_avg', 'days' => '3', 'band' => 'minmax' ] );
$ma['w'] = 680; $ma['h'] = 64;
$md   = NAWS_Sparkline::prepare( $ma, [ 'dates' => $tage, 'rows' => [ '2026-09-24' => [ 12.0, 8.0, 16.0 ], '2026-09-26' => [ 14.0, 9.0, 19.0 ] ] ] );
$mra  = NAWS_Sparkline::normalise_atts( [ 'param' => 'rain_sum', 'days' => '3' ] );
$mra['w'] = 680; $mra['h'] = 40;
$mrd  = NAWS_Sparkline::prepare( $mra, [ 'dates' => $tage, 'rows' => [ '2026-09-25' => [ 4.2 ] ] ] );
$month = NAWS_Sparkline::month_markup( $ma, $md, $mra, $mrd, $tage, 3 );
check( 'Wurzel des Blocks',                   str_starts_with( $month, '<div class="naws-sl-card naws-sl-month">' ), true );
check( 'Band-Zeile mit Mittel',               str_contains( $month, '<div class="naws-sl-month-k"><b>14.0 °C</b>Daily mean on 9/26</div>' ), true );
check( 'Band 680 x 64',                       str_contains( $month, 'viewBox="0 0 680 64"' ), true );
check( 'Regen-Zeile',                         str_contains( $month, '<div class="naws-sl-month-k"><b>4.2 mm</b>Rain in 3 days</div>' ) && str_contains( $month, 'viewBox="0 0 680 40"' ), true );
check( 'Achse',                               str_contains( $month, '<div class="naws-sl-month-axis"><div></div><div><span>9/24</span><span>9/26</span></div></div>' ), true );
// Review Focus 2: ohne Regenmesser nur Temperatur und Achse.
$ohne = NAWS_Sparkline::month_markup( $ma, $md, $mra, null, $tage, 3 );
check( 'ohne Regen: eine Zeile',              substr_count( $ohne, 'class="naws-sl-month-row' ), 1 );
check( 'ohne Regen: Achse bleibt',            str_contains( $ohne, 'naws-sl-month-axis' ), true );
check( 'leere Station: Monat leer',           NAWS_Sparkline::render( [ 'layout' => 'month' ] ), '' );
check( 'leere Station: Kachel leer',          NAWS_Sparkline::render( [ 'layout' => 'tile' ] ), '' );

$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend.css' );
// Review Focus 1: Karte und Kurve füllen jede Spalte.
check( 'Karte füllt die Spalte',              str_contains( $css, '.naws-sl-card { display:block; box-sizing:border-box; width:100%;' ), true );
check( 'Kurve in der Karte über die volle Breite', str_contains( $css, '.naws-sl-tile-plot .naws-sl svg { width:100%; height:44px; }' ), true );
check( 'Monat auf dem Handy einspaltig',       str_contains( $css, '@media (max-width:520px) { .naws-sl-month-row, .naws-sl-month-axis { grid-template-columns:1fr;' ), true );
```

In `tests/test-appearance.php` vor der Zeile `echo str_repeat( '-', 74 ) . "\n";` am Ende:

```php
echo "\nSparkline-Karten nehmen die Basis-Theme-Farben\n";
saved( [] );
check( 'Theme-Variablen auch an .naws-sl-card', str_contains( NAWS_Colors::get_inline_css(), ".naws-wrap, .naws-wx, .naws-hm, .naws-sl-card {\n" ), true );
check( 'Schrift und Kopfleiste auch dort',      str_contains( NAWS_Colors::get_inline_css(), '.naws-fc-wrap, .naws-sl-card {' ), true );
```

- [ ] **Step 2:** `php tests/test-sparkline-render.php` und `php tests/test-appearance.php` → FAIL (`Call to undefined method NAWS_Sparkline::tile_markup()` bzw. Selektor fehlt).

- [ ] **Step 3: Template Kachel** — `templates/sparkline-tile.php` (LF):

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: one sparkline tile, rendered by NAWS_Sparkline::tile_markup().
 *
 * The card from the demo: the name in small capitals, the figure large
 * with its unit small, one line under it (low and high, or the wettest
 * window), and the curve across the card. Colours come from the Base
 * Theme variables (card) and the Sparkline tab (curve); nothing here
 * carries a colour.
 *
 * Expected variables:
 * @var array $naws_slt name, value, unit, sub ('' for none), curve (markup
 *                      from NAWS_Sparkline::markup(), already escaped there)
 *
 * @package NAWS
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="naws-sl-card naws-sl-tile">
<div class="naws-sl-tile-name"><?php echo esc_html( $naws_slt['name'] ); ?></div>
<div class="naws-sl-tile-val"><?php echo esc_html( $naws_slt['value'] ); ?><small><?php echo esc_html( $naws_slt['unit'] ); ?></small></div>
<?php if ( $naws_slt['sub'] !== '' ) : ?>
<div class="naws-sl-tile-sub"><?php echo esc_html( $naws_slt['sub'] ); ?></div>
<?php endif; ?>
<div class="naws-sl-tile-plot"><?php echo $naws_slt['curve']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
</div>
```

- [ ] **Step 4: Template Monat** — `templates/sparkline-month.php` (LF):

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: the month block, rendered by NAWS_Sparkline::month_markup().
 *
 * As in the demo: a row for the temperature (the daily mean of the last
 * day large, the band from low to high with the mean line), a row for
 * the rain per day, and the first and last day under the curves. The
 * rain row is left out when the station has no rain gauge.
 *
 * Expected variables:
 * @var array $naws_slm mean_value, mean_label, rain_value, rain_label,
 *                      axis_from, axis_to (strings), band and rain
 *                      (markup from NAWS_Sparkline::markup(), rain '' for none)
 *
 * @package NAWS
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="naws-sl-card naws-sl-month">
<div class="naws-sl-month-row naws-sl-month-row--band">
<div class="naws-sl-month-k"><b><?php echo esc_html( $naws_slm['mean_value'] ); ?></b><?php echo esc_html( $naws_slm['mean_label'] ); ?></div>
<div class="naws-sl-month-plot"><?php echo $naws_slm['band']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
</div>
<?php if ( $naws_slm['rain'] !== '' ) : ?>
<div class="naws-sl-month-row">
<div class="naws-sl-month-k"><b><?php echo esc_html( $naws_slm['rain_value'] ); ?></b><?php echo esc_html( $naws_slm['rain_label'] ); ?></div>
<div class="naws-sl-month-plot"><?php echo $naws_slm['rain']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
</div>
<?php endif; ?>
<div class="naws-sl-month-axis"><div></div><div><span><?php echo esc_html( $naws_slm['axis_from'] ); ?></span><span><?php echo esc_html( $naws_slm['axis_to'] ); ?></span></div></div>
</div>
```

- [ ] **Step 5: Methoden** — in `class-naws-sparkline.php` nach `month_facts()` (vor `clamp()`):

```php
    /** One tile: the curve from markup(), laid into the card template. */
    public static function tile_markup( array $a, array $d ): string {
        $naws_slt          = self::tile_facts( $a, $d );
        $naws_slt['curve'] = self::markup( $a, $d );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sparkline-tile.php';
        return trim( (string) ob_get_clean() );
    }

    /**
     * The month block: the band row, the rain row when there is rain data,
     * and the date axis. $at/$ar come from normalise_atts() with their
     * viewBox set (680 × 64, 680 × 40), $dt/$dr from prepare().
     */
    public static function month_markup( array $at, array $dt, array $ar, ?array $dr, array $dates, int $days ): string {
        $naws_slm         = self::month_facts( $dt, $dr, $dates, $days );
        $naws_slm['band'] = self::markup( $at, $dt );
        $naws_slm['rain'] = $dr !== null ? self::markup( $ar, $dr ) : '';

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sparkline-month.php';
        return trim( (string) ob_get_clean() );
    }

    /**
     * [naws_sparkline layout="month" days="30"]: temperature (band) and
     * rain per day from the station's daily row, 7–366 days. Nothing
     * when there is no temperature to draw; no rain row without rain data.
     */
    public static function render_month( array $atts ): string {
        $days_raw = trim( (string) ( $atts['days'] ?? '' ) );
        $days     = $days_raw === '' ? 30 : self::clamp( intval( $days_raw ), 7, 366 );
        $now      = time();

        $at = self::normalise_atts( [ 'param' => 'temp_avg', 'days' => (string) $days, 'band' => 'minmax' ] );
        $at['w'] = 680;
        $at['h'] = 64;
        $ft = self::fetch( $at, $now );
        $dt = self::prepare( $at, $ft );
        if ( $dt === null ) {
            return '';
        }

        $ar = self::normalise_atts( [ 'param' => 'rain_sum', 'days' => (string) $days ] );
        $ar['w'] = 680;
        $ar['h'] = 40;
        $dr = self::prepare( $ar, self::fetch( $ar, $now ) );

        return self::month_markup( $at, $dt, $ar, $dr, $ft['dates'], $days );
    }

```

`render()` ersetzen durch:

```php
    /**
     * The entry for the shortcode and the widget: attributes in, HTML out,
     * '' whenever there is nothing to draw. layout="month" has its own
     * data path; layout="tile" wraps the curve in a card.
     */
    public static function render( array $atts ): string {
        if ( strtolower( trim( (string) ( $atts['layout'] ?? '' ) ) ) === 'month' ) {
            return self::render_month( $atts );
        }
        $a = self::normalise_atts( $atts );
        if ( $a === null ) {
            return '';
        }
        $d = self::prepare( $a, self::fetch( $a, time() ) );
        if ( $d === null ) {
            return '';
        }
        return $a['layout'] === 'tile' ? self::tile_markup( $a, $d ) : self::markup( $a, $d );
    }
```

- [ ] **Step 6: Shortcode** — in `sc_sparkline()` im `shortcode_atts( [ … ] )` nach `'band'   => '',`:

```php
            'layout' => '',
            'title'  => '',
```

und die Kommentarzeile darüber auf `// [naws_sparkline param="Temperature" hours="24" days="" module="" width="" height="" show="none" type="" band="" layout="inline|tile|month" title=""]` ändern.

- [ ] **Step 7: Farben** — in `includes/class-naws-colors.php`, `get_inline_css()`:
  - `$css = ".naws-wrap, .naws-wx, .naws-hm {\n";` → `$css = ".naws-wrap, .naws-wx, .naws-hm, .naws-sl-card {\n";`
  - `$css .= ".naws-wrap, .naws-wx, .naws-hm, .naws-hist, .naws-hist-modal, .naws-fc-wrap {\n";` → `$css .= ".naws-wrap, .naws-wx, .naws-hm, .naws-hist, .naws-hist-modal, .naws-fc-wrap, .naws-sl-card {\n";`

- [ ] **Step 8: Stylesheet** — in `assets/css/frontend.css` (CRLF, Edit-Werkzeug):
  - In der Regel mit `color-scheme: only light` die Selektorliste `.naws-fc-wrap, .naws-wgt, .naws-weather-icon, .naws-sl {` → `.naws-fc-wrap, .naws-wgt, .naws-weather-icon, .naws-sl, .naws-sl-card {`.
  - Am Dateiende anhängen:

```css

/* [naws_sparkline layout="tile|month"] — the card and the month block from
   the demo (since 2.1.0). The card's colours are the Base Theme variables,
   which .naws-sl-card receives with .naws-wrap (NAWS_Colors::get_inline_css());
   the curves keep the Sparkline tab's colours. A card fills its column; the
   page builder makes the grid. */
.naws-sl-card { display:block; box-sizing:border-box; width:100%; max-width:100%; background:var(--naws-surface, #fff); border:1px solid var(--naws-border, #e0eeee); border-radius:8px; color:var(--naws-text, #427272); font-family:var(--naws-font, inherit); }
.naws-sl-card .naws-sl { --naws-sl-ring:var(--naws-surface, #fff); }
.naws-sl-tile { padding:12px 14px 10px; }
.naws-sl-tile-name { font-size:.78rem; font-weight:500; letter-spacing:.04em; text-transform:uppercase; color:var(--naws-text-muted, #7aa0a0); }
.naws-sl-tile-val { margin-top:2px; font-size:1.55rem; font-weight:500; line-height:1.15; font-variant-numeric:tabular-nums; color:var(--naws-text-dark, #2d5252); }
.naws-sl-tile-val small { margin-left:.15em; font-size:.85rem; font-weight:400; color:var(--naws-text-muted, #7aa0a0); }
.naws-sl-tile-sub { margin-top:2px; font-size:.8rem; font-variant-numeric:tabular-nums; color:var(--naws-text-muted, #7aa0a0); }
.naws-sl-tile-plot, .naws-sl-month-plot { line-height:0; }
.naws-sl-tile-plot { margin-top:8px; }
.naws-sl-tile-plot .naws-sl, .naws-sl-month-plot .naws-sl { display:flex; width:100%; }
.naws-sl-tile-plot .naws-sl svg { width:100%; height:44px; }
.naws-sl-month { padding:14px 16px 10px; }
.naws-sl-month-row { display:grid; grid-template-columns:7.5em 1fr; gap:12px; align-items:center; }
.naws-sl-month-row + .naws-sl-month-row { margin-top:6px; }
.naws-sl-month-k { font-size:.85rem; color:var(--naws-text-muted, #7aa0a0); }
.naws-sl-month-k b { display:block; font-size:1rem; font-weight:500; font-variant-numeric:tabular-nums; color:var(--naws-text-dark, #2d5252); }
.naws-sl-month-plot .naws-sl svg { width:100%; height:40px; }
.naws-sl-month-row--band .naws-sl-month-plot .naws-sl svg { height:64px; }
.naws-sl-month-axis { display:grid; grid-template-columns:7.5em 1fr; gap:12px; margin-top:4px; font-size:.74rem; color:var(--naws-text-muted, #7aa0a0); }
.naws-sl-month-axis > div:last-child { display:flex; justify-content:space-between; }
@media (max-width:520px) { .naws-sl-month-row, .naws-sl-month-axis { grid-template-columns:1fr; gap:4px; } .naws-sl-month-axis > div:first-child { display:none; } }
```

- [ ] **Step 9: Nachbartests** — `tests/test-color-scheme.php` zählt die Template-Wurzeln (`check( 'alle Templates mit Wurzelelement erfasst', count( $roots ), 14 );`): mit den zwei neuen Templates wird es 16 — die Zahl anpassen, sobald der Test das meldet, und nur die Zahl. Danach alle Sparkline-Tests, `test-appearance.php`, `test-color-scheme.php`, `test-shortcode-atts.php` grün; `php -l` auf Templates und Klassen; PHPCS auf alle angefassten PHP-Dateien; ganze Suite.

- [ ] **Step 10: Commit**

```bash
git add templates/sparkline-tile.php templates/sparkline-month.php includes/class-naws-sparkline.php includes/class-naws-shortcodes.php includes/class-naws-colors.php assets/css/frontend.css tests/test-sparkline-render.php tests/test-appearance.php tests/test-color-scheme.php
git commit -m "Sparkline: the tile and the month block, as in the demo"
```

---

### Task 3: Doku und Sprache

**Files:**
- Modify: `admin/views/shortcodes.php` (Karte `[naws_sparkline]`: zwei Tabellenzeilen, zwei Beispiele), `readme.txt` (Zeile `[naws_sparkline]`), `README.md` (Zeile `[naws_sparkline …]`), `CHANGELOG.md` (`[Unreleased]` → `### Added`)
- Modify (erzeugt): `languages/*.pot`, `docs/i18n/catalog/*.po`, `languages/*.mo`
- Modify: `tests/test-mo-files.php` (Plural-Liste, falls der Test es verlangt)

- [ ] **Step 1: Shortcode-Seite** — in `admin/views/shortcodes.php`, Karte `[naws_sparkline]`, nach der Tabellenzeile `band`:

```php
            <tr><td><code>layout</code></td><td><?php esc_html_e( 'inline (the curve in running text), tile (a card: name, current value, low and high, a large curve) or month (daily means with the band from low to high and rain per day, with a date axis; needs no param, days 7–366).', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">inline</span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Only with layout="tile": the name at the top of the card instead of the name of the quantity.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'empty', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
```

und nach dem letzten Beispiel der Karte (`the last 30 days: daily means with the band from low to high`):

```php
            <div class="naws-inline-ex"><code>[naws_sparkline param="Temperature" layout="tile" title="Temperatur außen"]</code> &rarr; <?php esc_html_e( 'a card: name, current value, low and high, a large curve', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_sparkline layout="month" days="32"]</code> &rarr; <?php esc_html_e( 'the last 32 days in one block: daily means with the band, rain per day, dates', 'xtx-integration-for-netatmo' ); ?></div>
```

- [ ] **Step 2: readme.txt / README.md / CHANGELOG.md**
  - readme.txt, Zeile `[naws_sparkline]`: in der Attributliste nach `` `band` `` ergänzen: `` , `layout` (inline, tile, month), `title` ``; vor der Klammer den Satzteil „…, time and value on hover" ergänzen um „; as a card or a month block too".
  - README.md, Zeile `` | `[naws_sparkline param="Temperature"]` | … | ``: am Ende der Beschreibung „; also as a card (`layout="tile"`) or a month block (`layout="month"`)".
  - CHANGELOG.md, unter `## [Unreleased]` → `### Added` als dritter Punkt:

```
- **Sparkline cards and the month block.** `layout="tile"` draws a card as in the preview shown before the build: the name in small capitals (or your own `title`), the current value large, low and high underneath (for rain: the total and the wettest window), and the curve across the card with the hover bubble. One card per shortcode; the page builder lays them out. `layout="month"` draws the daily means of the last `days` (7–366, 30 by default) with the band from low to high, the rain per day below, and the first and last date — one block, one column on phones. Both take their card colours from the Base Theme and the curves from the Sparkline tab.
```

- [ ] **Step 3: Kataloge** — wie in der vorigen Sprachaufgabe (`docs/i18n/README.md`, Abschnitt catalog/): `makepot.php`, `merge_po.php de_DE`, `merge_po.php nb_NO`, dann `fill_po.php` mit diesen Listen (im Plan-Arbeitsbereich `.superpowers/sdd/2026-09-26-sparkline/`, nicht committen), `make_mo.php` für beide.

Deutsch (`tiles-de_DE.php`):

```php
<?php
return [
    'Low %1$s · High %2$s' => 'Tief %1$s · Hoch %2$s',
    'Most: %s' => 'Am meisten: %s',
    'Daily mean on %s' => 'Tagesmittel am %s',
    "sparkline month axis date format\x04n/j" => 'j.n.',
    '%1$s in %2$d hour' => [ '%1$s in %2$d Stunde', '%1$s in %2$d Stunden' ],
    '%1$s in %2$d day' => [ '%1$s in %2$d Tag', '%1$s in %2$d Tagen' ],
    'Rain in %d day' => [ 'Regen in %d Tag', 'Regen in %d Tagen' ],
    'inline (the curve in running text), tile (a card: name, current value, low and high, a large curve) or month (daily means with the band from low to high and rain per day, with a date axis; needs no param, days 7–366).' => 'inline (die Kurve im Fließtext), tile (eine Karte: Name, aktueller Wert, Tief und Hoch, große Kurve) oder month (Tagesmittel mit dem Band von Tief bis Hoch und Regen je Tag, mit Datumsachse; braucht kein param, days 7–366).',
    'Only with layout="tile": the name at the top of the card instead of the name of the quantity.' => 'Nur mit layout="tile": der Name oben auf der Karte statt des Namens der Größe.',
    'a card: name, current value, low and high, a large curve' => 'eine Karte: Name, aktueller Wert, Tief und Hoch, große Kurve',
    'the last 32 days in one block: daily means with the band, rain per day, dates' => 'die letzten 32 Tage in einem Block: Tagesmittel mit Band, Regen je Tag, Datum',
];
```

Norwegisch (`tiles-nb_NO.php`):

```php
<?php
return [
    'Low %1$s · High %2$s' => 'Lavest %1$s · Høyest %2$s',
    'Most: %s' => 'Mest: %s',
    'Daily mean on %s' => 'Døgnmiddel %s',
    "sparkline month axis date format\x04n/j" => 'j.n.',
    '%1$s in %2$d hour' => [ '%1$s på %2$d time', '%1$s på %2$d timer' ],
    '%1$s in %2$d day' => [ '%1$s på %2$d døgn', '%1$s på %2$d døgn' ],
    'Rain in %d day' => [ 'Nedbør på %d døgn', 'Nedbør på %d døgn' ],
    'inline (the curve in running text), tile (a card: name, current value, low and high, a large curve) or month (daily means with the band from low to high and rain per day, with a date axis; needs no param, days 7–366).' => 'inline (kurven i løpende tekst), tile (et kort: navn, gjeldende verdi, lavest og høyest, en stor kurve) eller month (døgnmiddel med båndet fra lavest til høyest og nedbør per døgn, med datoakse; trenger ikke param, days 7–366).',
    'Only with layout="tile": the name at the top of the card instead of the name of the quantity.' => 'Bare med layout="tile": navnet øverst på kortet i stedet for navnet på måleverdien.',
    'a card: name, current value, low and high, a large curve' => 'et kort: navn, gjeldende verdi, lavest og høyest, en stor kurve',
    'the last 32 days in one block: daily means with the band, rain per day, dates' => 'de siste 32 dagene i én blokk: døgnmiddel med bånd, nedbør per døgn, datoer',
];
```

`fill_po.php` darf keinen Schlüssel als „nicht in der .po" melden; weicht eine msgid ab, die Liste korrigieren. `tests/test-mo-files.php` führt eine Liste der Plural-Originale; die drei neuen (`%1$s in %2$d hour`, `%1$s in %2$d day`, `Rain in %d day`) mit ihren de/nb-Formen dort eintragen, wenn der Test es verlangt. Prüfen: genau ein leeres `msgstr ""` je `.po` (Kopf), keine leeren `msgstr[0|1] ""`; `php tests/test-mo-files.php`, `php tests/test-makepot-comments.php`; ganze Suite.

- [ ] **Step 4:** `php -l admin/views/shortcodes.php`; PHPCS darauf; `php tests/test-readme-sections.php`; ganze Suite.

- [ ] **Step 5: Commit** (zwei Commits)

```bash
git add admin/views/shortcodes.php readme.txt README.md CHANGELOG.md
git commit -m "Document the sparkline card and the month block"
git add docs/i18n/catalog/*.po languages/ tests/test-mo-files.php
git commit -m "i18n: the sparkline card and the month block in German and Norwegian"
```

---

### Task 4: Abnahme auf dev (Controller)

- [ ] Gesamtlauf lokal (Suite, PHPCS, `node --check`).
- [ ] Geänderte Laufzeitdateien per ZIP nach dev (Weg wie beim ersten Deploy; Sicherung `wp-content/naws-backup-vor-sparkline-tiles-<datum>`), Opcache leeren, Caches leeren.
- [ ] Testseite 94 `/sparkline-test/` oben um zwei Abschnitte ergänzen: „Kacheln wie in der Demo" — ein HTML-Raster (`display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px`) mit sechs Kacheln (Temperature `title="Temperatur außen"`, Humidity `title="Luftfeuchte außen"`, Pressure, WindStrength, Rain, CO2 `title="CO₂ Basis"`); „Monat in einer Zeile" — `[naws_sparkline layout="month" days="32"]`.
- [ ] Playwright Desktop 1280 und Handy 390: Kacheln füllen ihre Spalten, keine Seitenbreite > Viewport, Sprechblase per Maus und Antippen in Kachel und Monatsblock, Monat einspaltig auf dem Handy, keine Konsolenfehler. Screenshot neben die Demo legen.
- [ ] Frank zeigen; Merge des Zweigs `sparkline` weiter erst nach seinem OK.
