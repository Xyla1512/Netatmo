# Heatmap-Shortcode `[naws_heatmap]` — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Shortcode, der den Außen-Tagesdurchschnitt eines Jahres als Kalenderraster zeigt — zwölf Monatszeilen, 31 Tagesspalten, farbige Kacheln, Jahreswechsel per AJAX.

**Architecture:** Die Farbrechnung liegt als reine Funktion in `NAWS_Colors`, die Datenform als reine Funktion in `NAWS_Database`. Template und AJAX-Endpunkt benutzen beide dieselben Funktionen, sodass ein per AJAX nachgeladenes Jahr exakt so aussieht wie das serverseitig gerenderte. Das JavaScript rechnet nichts — es setzt fertige Farben, zeigt fertige Beschriftungen und steuert die Animation.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, kein Build-Schritt, keine neue Bibliothek. Tests sind eigenständige PHP-Dateien ohne Runner (`php tests/test-x.php`), wie die 25 vorhandenen.

**Spec:** `docs/superpowers/specs/2026-09-02-heatmap-shortcode-design.md`

**Branch:** `heatmap-shortcode` (existiert bereits, zwei Commits mit dem Spec)

## Definition of Done — gilt für jeden Task ohne Ausnahme

Ein Task ist erst fertig, wenn **alle vier** Punkte erfüllt sind. Kein Commit ohne sie.

1. **Das Review-Gate steht auf null.**

   ```
   vendor\bin\phpcs.bat --report=full
   ```

   `.phpcs.xml.dist` ist in diesem Projekt ausdrücklich kein Style-Guide, sondern das Gate, das über die Annahme bei WordPress.org entscheidet: `WordPress.Security` (Nonces, Sanitization, Escaping), `WordPress.DB` (Prepared Statements), `WordPress.WP` (i18n, Enqueueing, API-Missbrauch), `WordPress.NamingConventions.PrefixAllGlobals`, `PHPCompatibilityWP` gegen PHP 8.0+.

   **Ausgangsstand am 2026-09-02: 51 Dateien, null Befunde.** Wer einen Befund hinterlässt, hat den Task nicht beendet.

2. **Ein `phpcs:ignore` wird begründet oder gar nicht gesetzt.** Jedes vorhandene im Codebestand trägt einen Kommentar, der sagt, warum die Regel hier nicht greift. Ein Ignore, das nur den Lärm abstellt, ist eine Verschlechterung gegenüber dem Befund — dann lieber den Code ändern. Insbesondere: **keine eigene Escaping-Wrapper-Funktion** in die `customEscapingFunctions` aufnehmen; das Review-Team will `esc_html()`/`esc_attr()`/`wp_kses()` im `echo` selbst sehen, und die Datei sagt das ausdrücklich.

3. **Die ganze Testsuite ist grün.**

   ```
   for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done
   ```

   Erwartet wird keine Ausgabe. Nicht nur der eigene Test — ein Task, der einen fremden bricht, ist nicht fertig.

4. **`php -l` auf jeder angefassten PHP-Datei.**

Wenn einer der vier Punkte nicht zu erfüllen ist, wird das gemeldet und **nicht** umgangen. Ein rot gemeldeter Task ist brauchbar; ein grün gemeldeter, der es nicht ist, kostet später ein Release.

---

## Global Constraints

- **Textdomain ist immer `xtx-integration-for-netatmo`.** Jeder `__()`, `_x()`, `esc_html__()` trägt sie als zweites bzw. drittes Argument.
- **Prefix `naws_` / `NAWS_`** für jede globale Funktion, Klasse, Konstante und Option. CSS-Klassen beginnen mit `naws-`.
- **Kein `<style>`- und kein `<script>`-Block in der Ausgabe.** Das Plugin hat beides 1.6.2 auf Verlangen des wp.org-Review-Teams entfernt. `style`-Attribute an einzelnen Elementen sind davon nicht betroffen und bleiben zulässig; JavaScript geht ausschließlich über `wp_enqueue_script()` auf eine registrierte Datei.
- **Jede Ausgabe wird escaped** — `esc_html()`, `esc_attr()`, `esc_url()`, spät und sichtbar an der Ausgabestelle, nicht in einer Hilfsfunktion davor. Ohne Ausnahme.
- **Jede Eingabe wird entschlüsselt und gesäubert**: `wp_unslash()` vor `sanitize_*()`, und beides vor der Benutzung — nicht über eine Zwischenvariable, weil weder PHPCS noch der Review-Scanner Sanitization über eine Zuweisung hinweg verfolgen. `handle_save_appearance()` in `class-naws-admin.php` erklärt das an Ort und Stelle und ist das Muster.
- **Öffentliche AJAX-Endpunkte** prüfen `check_ajax_referer( 'naws_public_nonce', 'nonce' )` und rufen `nocache_headers()`.
- **Jede SQL geht durch `$wpdb->prepare()`**, Spalten- und Tabellennamen als `%i`, Werte als `%s`/`%d`/`%f`. Ein Tabellenname aus `$wpdb->prefix` plus Konstante wird interpoliert und trägt dafür ein begründetes `phpcs:ignore` — so wie jede vorhandene Abfrage in `class-naws-database.php`.
- **Keine externe Ressource.** Kein CDN, keine Schriftdatei, kein Skript von fremdem Host. Das Plugin lädt ausschließlich, was es selbst mitbringt.
- **Kein `error_log()`, `var_dump()`, `print_r()` im ausgelieferten Code.** Das Logging läuft über `NAWS_Logger`.
- **Keine MAC-Adresse in öffentlicher Ausgabe.** Seit 1.9.10 prüft das jeder Render-Test.
- **Farbe immer aus dem gespeicherten Celsius-Wert**, nie aus dem angezeigten. Angezeigt wird über `NAWS_Helpers::format_value( 'Temperature', $v )`.
- **Dateien sind LF**, PHP ohne schließendes `?>` am Dateiende (außer in Templates, die mit HTML enden).
- **Commits auf Deutsch oder Englisch im Ton des Repos** — ein Satz, der benennt, was die Änderung bewirkt, nicht was sie anfasst.

---

## Dateiübersicht

| Datei | Verantwortung |
| --- | --- |
| `includes/class-naws-colors.php` | 11 neue Defaults, Gruppe `heatmap`, `heatmap_color()`, `heatmap_scale()` |
| `includes/class-naws-database.php` | `get_heatmap_year()` (SQL), `shape_heatmap_year()` (reine Formung) |
| `includes/class-naws-helpers.php` | `heatmap_label()` — eine Beschriftung, drei Aufrufstellen |
| `templates/heatmap.php` | *neu* — das Raster, Jahresknöpfe, Legende |
| `includes/class-naws-shortcodes.php` | `sc_heatmap()`, Registrierung, Skript-Registrierung |
| `assets/js/heatmap-boot.js` | *neu* — Tooltip, Jahreswechsel, Animationsverzögerung |
| `assets/css/frontend.css` | Raster, klebende Spalte, Scrollbereich, Animation, Legende |
| `includes/class-naws-ajax.php` | `get_heatmap_data()` |
| `admin/views/appearance.php` | Tab „Heatmap" mit den elf Farbfeldern |
| `admin/views/shortcodes.php`, `readme.txt` | Dokumentation |
| `tests/test-heatmap-colors.php` | *neu* — Interpolation, Kappung, Einstellungsdurchgriff |
| `tests/test-heatmap-year.php` | *neu* — Monatslängen, Schaltjahr, Min/Max-Rückgriff |
| `tests/test-heatmap-render.php` | *neu* — Markup, leere Zellen, keine MAC, kein `<style>` |

---

## Task 1: Die Farbrechnung

**Files:**
- Modify: `includes/class-naws-colors.php` (Konstante `DEFAULTS` ~Zeile 28, `get_groups()` ~Zeile 437)
- Test: `tests/test-heatmap-colors.php`

**Interfaces:**
- Consumes: `NAWS_Colors::get_all(): array`, `NAWS_Colors::DEFAULTS`
- Produces:
  - `NAWS_Colors::heatmap_color( $celsius ): string` — `$celsius` ist `float|int|null`; gibt immer `#rrggbb` zurück, bei `null` die Farbe `heatmap_no_data`
  - `NAWS_Colors::heatmap_scale(): array` — zehn Paare `[ int $celsius, string $hex ]`, aufsteigend
  - Elf neue Schlüssel in `DEFAULTS`: `heatmap_t_m10`, `heatmap_t_m5`, `heatmap_t_0`, `heatmap_t_5`, `heatmap_t_10`, `heatmap_t_15`, `heatmap_t_20`, `heatmap_t_25`, `heatmap_t_30`, `heatmap_t_35`, `heatmap_no_data`
  - Gruppe `heatmap` in `get_groups()` mit Label `appearance_group_heatmap`

- [x] **Step 1: Den fehlschlagenden Test schreiben**

Neue Datei `tests/test-heatmap-colors.php`:

```php
<?php
/**
 * Prueft die Farbrechnung der Heatmap.
 *
 * Die Interpolation ist die einzige Stelle, an der aus einer Temperatur
 * eine Farbe wird — Template und AJAX-Endpunkt rufen beide hierher. Wenn
 * sie falsch rechnet, faellt es an der Karte nicht auf: eine Kachel eine
 * Stufe daneben sieht aus wie Wetter.
 *
 *   php tests/test-heatmap-colors.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['appearance'] = [];
function get_option( $k, $d = false ) {
    return $k === 'naws_appearance' ? $GLOBALS['appearance'] : $d;
}
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Fonts {
    public static function available() { return [ 'inherit' => 'Inherit' ]; }
    public static function sanitize_family( $s ) { return $s; }
}

require_once dirname( __DIR__ ) . '/includes/class-naws-colors.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

/** Zwingt NAWS_Colors, die Einstellungen neu zu lesen. */
function with_appearance( array $a ): void {
    $GLOBALS['appearance'] = $a;
    NAWS_Colors::flush_cache();
}

echo "\nNAWS_Colors::heatmap_color() — die Stuetzpunkte\n" . str_repeat( '-', 74 ) . "\n";

with_appearance( [] );

check( 'minus zehn Grad ist Lila',        NAWS_Colors::heatmap_color( -10 ), '#6b21a8' );
check( 'null Grad ist Blaugruen',         NAWS_Colors::heatmap_color( 0 ),   '#2f9e97' );
check( 'zwanzig Grad ist Orange',         NAWS_Colors::heatmap_color( 20 ),  '#f59f3c' );
check( 'fuenfunddreissig ist Dunkelrot',  NAWS_Colors::heatmap_color( 35 ),  '#7f1d1d' );

echo "\nDazwischen wird interpoliert\n" . str_repeat( '-', 74 ) . "\n";

// -10 = #6b21a8 = (107, 33,168)
//  -5 = #3b5bdb = ( 59, 91,219)
// Mitte bei -7.5 = (83, 62, 193.5) -> gerundet (83, 62, 194) = #533ec2
check( 'die Mitte zwischen zwei Stuetzpunkten', NAWS_Colors::heatmap_color( -7.5 ), '#533ec2' );

check( 'ein Viertel weiter liegt naeher am linken Stuetzpunkt',
    NAWS_Colors::heatmap_color( -8.75 ) !== NAWS_Colors::heatmap_color( -7.5 ), true );
check( 'benachbarte Grade unterscheiden sich',
    NAWS_Colors::heatmap_color( 12 ) !== NAWS_Colors::heatmap_color( 13 ), true );

echo "\nAusserhalb der Skala wird gekappt\n" . str_repeat( '-', 74 ) . "\n";

check( 'minus vierzig faerbt wie minus zehn', NAWS_Colors::heatmap_color( -40 ), NAWS_Colors::heatmap_color( -10 ) );
check( 'fuenfzig faerbt wie fuenfunddreissig', NAWS_Colors::heatmap_color( 50 ),  NAWS_Colors::heatmap_color( 35 ) );

echo "\nKein Messwert\n" . str_repeat( '-', 74 ) . "\n";

check( 'null gibt die Farbe fuer den fehlenden Tag', NAWS_Colors::heatmap_color( null ), '#eef2f2' );
check( 'ein leerer String ebenso',                   NAWS_Colors::heatmap_color( '' ),   '#eef2f2' );

echo "\nDie Einstellung schlaegt durch\n" . str_repeat( '-', 74 ) . "\n";

with_appearance( [ 'heatmap_t_0' => '#000000' ] );
check( 'ein geaenderter Stuetzpunkt wird benutzt', NAWS_Colors::heatmap_color( 0 ), '#000000' );
check( 'und faerbt seine Nachbarschaft mit',       NAWS_Colors::heatmap_color( 2.5 ) !== '#3fa34d', true );

with_appearance( [ 'heatmap_no_data' => '#ff00ff' ] );
check( 'auch die Farbe des fehlenden Tages', NAWS_Colors::heatmap_color( null ), '#ff00ff' );

with_appearance( [] );

echo "\nNAWS_Colors::heatmap_scale()\n" . str_repeat( '-', 74 ) . "\n";

$scale = NAWS_Colors::heatmap_scale();
check( 'zehn Stuetzpunkte',            count( $scale ), 10 );
check( 'der erste ist minus zehn',     $scale[0][0], -10 );
check( 'der letzte ist fuenfunddreissig', $scale[9][0], 35 );
check( 'jeder traegt eine Hexfarbe',
    array_values( array_filter( $scale, fn( $s ) => ! preg_match( '/^#[0-9a-f]{6}$/i', $s[1] ) ) ),
    [] );
check( 'die Stuetzpunkte steigen',
    array_column( $scale, 0 ),
    [ -10, -5, 0, 5, 10, 15, 20, 25, 30, 35 ] );

echo "\nDie Gruppe fuer die Appearance-Seite\n" . str_repeat( '-', 74 ) . "\n";

$groups = NAWS_Colors::get_groups();
check( 'es gibt eine Heatmap-Gruppe', isset( $groups['heatmap'] ), true );
check( 'sie fuehrt elf Schluessel',   count( $groups['heatmap']['keys'] ), 11 );
check( 'jeder davon hat einen Default',
    array_values( array_filter( $groups['heatmap']['keys'], fn( $k ) => ! isset( NAWS_Colors::DEFAULTS[ $k ] ) ) ),
    [] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [x] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-heatmap-colors.php`
Expected: FAIL — `Call to undefined method NAWS_Colors::heatmap_color()`

- [x] **Step 3: Die elf Defaults eintragen**

In `includes/class-naws-colors.php`, ans Ende der Konstante `DEFAULTS` (vor der schließenden `];`):

```php
        // ── Gruppe 7: Heatmap ──────────────────────────────────────────
        //
        // Zehn Stuetzpunkte einer Temperaturskala in Celsius, dazwischen
        // wird linear interpoliert. Sie haengen ausdruecklich an Celsius
        // und nicht an der angezeigten Einheit: auf einer Installation mit
        // temperature_unit = F waeren 35 Grad der Wert 95 und laegen weit
        // jenseits des oberen Endes.
        'heatmap_t_m10'   => '#6b21a8',
        'heatmap_t_m5'    => '#3b5bdb',
        'heatmap_t_0'     => '#2f9e97',
        'heatmap_t_5'     => '#3fa34d',
        'heatmap_t_10'    => '#a3c644',
        'heatmap_t_15'    => '#f2c744',
        'heatmap_t_20'    => '#f59f3c',
        'heatmap_t_25'    => '#ec6a2c',
        'heatmap_t_30'    => '#d92b2b',
        'heatmap_t_35'    => '#7f1d1d',
        'heatmap_no_data' => '#eef2f2',
```

- [x] **Step 4: Die Rechnung schreiben**

In `includes/class-naws-colors.php`, vor `get_groups()`:

```php
    // ================================================================
    // Heatmap
    // ================================================================

    /** Die Stuetzpunkte der Skala in Grad Celsius, aufsteigend. */
    const HEATMAP_STOPS = [ -10, -5, 0, 5, 10, 15, 20, 25, 30, 35 ];

    /** Die Einstellungsschluessel zu HEATMAP_STOPS, in derselben Reihenfolge. */
    const HEATMAP_KEYS = [
        'heatmap_t_m10', 'heatmap_t_m5', 'heatmap_t_0', 'heatmap_t_5', 'heatmap_t_10',
        'heatmap_t_15', 'heatmap_t_20', 'heatmap_t_25', 'heatmap_t_30', 'heatmap_t_35',
    ];

    /**
     * Die Skala als Paare aus Temperatur und Farbe.
     *
     * Die Legende zeichnet ihren Verlauf daraus, damit sie das Bild der
     * tatsaechlich eingestellten Skala ist und nicht eine zweite, die
     * davon abweichen kann.
     *
     * @return array<int,array{0:int,1:string}>
     */
    public static function heatmap_scale() {
        $c   = self::get_all();
        $out = [];
        foreach ( self::HEATMAP_STOPS as $i => $deg ) {
            $out[] = [ $deg, $c[ self::HEATMAP_KEYS[ $i ] ] ];
        }
        return $out;
    }

    /**
     * Die Farbe einer Kachel zu einer Temperatur in Grad Celsius.
     *
     * Zwischen zwei Stuetzpunkten wird linear im RGB-Raum interpoliert,
     * damit zwoelf und dreizehn Grad sich unterscheiden statt in dieselbe
     * Stufe zu fallen. Unterhalb des ersten und oberhalb des letzten
     * Stuetzpunktes wird gekappt.
     *
     * @param float|int|string|null $celsius
     * @return string  #rrggbb
     */
    public static function heatmap_color( $celsius ) {
        $c = self::get_all();

        if ( $celsius === null || $celsius === '' || ! is_numeric( $celsius ) ) {
            return $c['heatmap_no_data'];
        }

        $v    = (float) $celsius;
        $last = count( self::HEATMAP_STOPS ) - 1;

        if ( $v <= self::HEATMAP_STOPS[0] )     return $c[ self::HEATMAP_KEYS[0] ];
        if ( $v >= self::HEATMAP_STOPS[ $last ] ) return $c[ self::HEATMAP_KEYS[ $last ] ];

        for ( $i = 0; $i < $last; $i++ ) {
            if ( $v <= self::HEATMAP_STOPS[ $i + 1 ] ) {
                $span = self::HEATMAP_STOPS[ $i + 1 ] - self::HEATMAP_STOPS[ $i ];
                $t    = ( $v - self::HEATMAP_STOPS[ $i ] ) / $span;
                return self::mix_hex( $c[ self::HEATMAP_KEYS[ $i ] ], $c[ self::HEATMAP_KEYS[ $i + 1 ] ], $t );
            }
        }

        return $c[ self::HEATMAP_KEYS[ $last ] ];
    }

    /** Mischt zwei Hexfarben, $t von 0 (ganz $a) bis 1 (ganz $b). */
    private static function mix_hex( $a, $b, $t ) {
        $ca = self::hex_to_rgb( $a );
        $cb = self::hex_to_rgb( $b );
        return sprintf(
            '#%02x%02x%02x',
            (int) round( $ca[0] + ( $cb[0] - $ca[0] ) * $t ),
            (int) round( $ca[1] + ( $cb[1] - $ca[1] ) * $t ),
            (int) round( $ca[2] + ( $cb[2] - $ca[2] ) * $t )
        );
    }

    /**
     * #rgb, #rrggbb und #rrggbbaa zu drei Kanaelen.
     *
     * Die achtstellige Form kommt vor — theme_shadow ist eine —, und ein
     * Alphakanal gehoert nicht in die Mischung. Er faellt hier weg.
     */
    private static function hex_to_rgb( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 || strlen( $hex ) === 4 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) < 6 || ! ctype_xdigit( substr( $hex, 0, 6 ) ) ) {
            return [ 0, 0, 0 ];
        }
        return [
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
        ];
    }
```

- [x] **Step 5: Die Gruppe eintragen**

In `get_groups()`, nach dem Eintrag `history_palette`:

```php
            'heatmap' => [
                'label' => 'appearance_group_heatmap',
                'keys'  => array_merge( self::HEATMAP_KEYS, [ 'heatmap_no_data' ] ),
            ],
```

- [x] **Step 6: Test laufen lassen**

Run: `php tests/test-heatmap-colors.php`
Expected: PASS — die Schlusszeile endet auf „0 fehlgeschlagen". Die Zahl der bestandenen Pruefungen ist die, die herauskommt; sie ist kein Sollwert.

- [x] **Step 7: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe. Die elf neuen Schlüssel dürfen keinen bestehenden Test brechen — `sanitize()` läuft über `DEFAULTS` und nimmt sie automatisch mit.

- [x] **Step 8: Commit**

```bash
git add includes/class-naws-colors.php tests/test-heatmap-colors.php
git commit -m "Give the heatmap a colour scale that can be set"
```

---

## Task 2: Die Jahresabfrage

**Files:**
- Modify: `includes/class-naws-database.php` (neue Methoden neben `get_daily_data_range()`, ~Zeile 985)
- Test: `tests/test-heatmap-year.php`

**Interfaces:**
- Consumes: `$wpdb`, `NAWS_TABLE_DAILY`
- Produces:
  - `NAWS_Database::shape_heatmap_year( array $rows, int $year ): array` — reine Formung, kein `$wpdb`. Rückgabe `[ 'values' => array, 'sources' => array ]`, beide zwölf Arrays, nullbasiert, Länge je Monat = tatsächliche Tageszahl
  - `NAWS_Database::get_heatmap_year( $year ): array` — dasselbe, mit der Abfrage davor
  - `$rows` sind Zeilen mit den Schlüsseln `day_date` (`Y-m-d`), `temp_avg`, `temp_min`, `temp_max`

- [x] **Step 1: Den fehlschlagenden Test schreiben**

Neue Datei `tests/test-heatmap-year.php`:

```php
<?php
/**
 * Prueft, wie aus Tageszeilen ein Jahresraster wird.
 *
 * Zwei Dinge muessen hier stimmen, weil sie sich auf der fertigen Karte
 * nicht unterscheiden lassen: die Laenge der Monate — ein 31. April darf
 * keine graue Kachel werden, sondern gar keine — und der Rueckgriff auf
 * (min + max) / 2. Der darf greifen, wenn beides da ist, und er darf
 * nicht greifen, wenn nur eines da ist: aus einem einzelnen Maximum ein
 * "Mittel" zu machen waere schlechter als die Luecke.
 *
 *   php tests/test-heatmap-year.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );

require_once dirname( __DIR__ ) . '/includes/class-naws-database.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

function row( string $date, $avg = null, $min = null, $max = null ): array {
    return [ 'day_date' => $date, 'temp_avg' => $avg, 'temp_min' => $min, 'temp_max' => $max ];
}

echo "\nDie Form des Rasters\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [], 2025 );

check( 'zwoelf Monatszeilen fuer die Werte',   count( $r['values'] ), 12 );
check( 'zwoelf ebenso fuer die Herkunft',      count( $r['sources'] ), 12 );
check( 'der Januar hat 31 Eintraege',          count( $r['values'][0] ), 31 );
check( 'der April hat 30, nicht 31',           count( $r['values'][3] ), 30 );
check( 'der Februar 2025 hat 28',              count( $r['values'][1] ), 28 );
check( 'ohne Zeilen ist alles null',           array_unique( $r['values'][0] ), [ null ] );
check( 'und keine Herkunft gesetzt',           array_unique( $r['sources'][0] ), [ null ] );

$leap = NAWS_Database::shape_heatmap_year( [], 2024 );
check( 'der Februar 2024 hat 29', count( $leap['values'][1] ), 29 );

echo "\nWerte landen an der richtigen Stelle\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [
    row( '2025-01-01', '4.2' ),
    row( '2025-03-15', '11.8' ),
    row( '2025-12-31', '-2.5' ),
], 2025 );

check( 'der erste Januar steht auf Index 0/0',   $r['values'][0][0],  4.2 );
check( 'der 15. Maerz auf 2/14',                 $r['values'][2][14], 11.8 );
check( 'der 31. Dezember auf 11/30',             $r['values'][11][30], -2.5 );
check( 'ihre Herkunft ist der gespeicherte Wert', $r['sources'][0][0], 'avg' );
check( 'ein Tag ohne Zeile bleibt null',          $r['values'][0][1],  null );

echo "\nDer Rueckgriff auf Min und Max\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [
    row( '2025-02-01', null, '2.0', '10.0' ),
    row( '2025-02-02', null, '2.0', null ),
    row( '2025-02-03', null, null,  '10.0' ),
    row( '2025-02-04', '7.0', '0.0', '20.0' ),
], 2025 );

check( 'Min und Max ergeben ihr Mittel',        $r['values'][1][0],  6.0 );
check( 'und werden als solches ausgewiesen',    $r['sources'][1][0], 'minmax' );
check( 'nur Min reicht nicht',                  $r['values'][1][1],  null );
check( 'nur Max ebenso wenig',                  $r['values'][1][2],  null );
check( 'ein gespeicherter Durchschnitt gewinnt', $r['values'][1][3],  7.0 );
check( 'auch in der Herkunft',                   $r['sources'][1][3], 'avg' );

echo "\nZeilen, die nicht hierher gehoeren\n" . str_repeat( '-', 74 ) . "\n";

$r = NAWS_Database::shape_heatmap_year( [
    row( '2024-06-01', '18.0' ),
    row( '2025-06-01', '19.0' ),
    row( 'kaputt',     '20.0' ),
    row( '2025-13-01', '21.0' ),
], 2025 );

check( 'ein anderes Jahr wird uebergangen',  $r['values'][5][0], 19.0 );
check( 'ein unlesbares Datum stuerzt nicht', is_array( $r['values'] ), true );

echo "\nZwei Zeilen fuer denselben Tag\n" . str_repeat( '-', 74 ) . "\n";

// Die Tagestabelle fuehrt je Modul eine Zeile. Die Innenmodule tragen in
// temp_* nichts, aber verlassen darf man sich darauf nicht: der
// gespeicherte Durchschnitt muss gewinnen, in welcher Reihenfolge die
// Zeilen auch kommen.
$a = NAWS_Database::shape_heatmap_year( [
    row( '2025-05-01', null, '1.0', '3.0' ),
    row( '2025-05-01', '9.9' ),
], 2025 );
$b = NAWS_Database::shape_heatmap_year( [
    row( '2025-05-01', '9.9' ),
    row( '2025-05-01', null, '1.0', '3.0' ),
], 2025 );

check( 'der Durchschnitt gewinnt, egal wer zuerst kommt', [ $a['values'][4][0], $b['values'][4][0] ], [ 9.9, 9.9 ] );
check( 'und die Herkunft sagt das auch',                  [ $a['sources'][4][0], $b['sources'][4][0] ], [ 'avg', 'avg' ] );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [x] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-heatmap-year.php`
Expected: FAIL — `Call to undefined method NAWS_Database::shape_heatmap_year()`

- [x] **Step 3: Die Formung schreiben**

In `includes/class-naws-database.php`, direkt nach `get_daily_data_range()`:

```php
    /**
     * Ein Jahr Tagesdurchschnitte als Raster fuer die Heatmap.
     *
     * Zwoelf Arrays, nullbasiert, jedes so lang wie sein Monat Tage hat —
     * der 31. April existiert nicht und bekommt deshalb keinen Eintrag,
     * waehrend ein Tag ohne Messwert einen Eintrag mit null bekommt. Die
     * Karte unterscheidet daran die leere Zelle von der grauen.
     *
     * Zwei Durchgaenge, und zwar in dieser Reihenfolge: erst die
     * gespeicherten Durchschnitte, dann der Rueckgriff auf (min + max) / 2
     * fuer die Tage, die danach noch offen sind. Die Tagestabelle fuehrt je
     * Modul eine Zeile, also koennen fuer einen Tag mehrere Zeilen kommen;
     * so gewinnt der gespeicherte Wert unabhaengig von ihrer Reihenfolge.
     *
     * @param array $rows  Zeilen mit day_date, temp_avg, temp_min, temp_max.
     * @param int   $year
     * @return array{values:array,sources:array}
     */
    public static function shape_heatmap_year( array $rows, $year ) {
        $year    = (int) $year;
        $values  = [];
        $sources = [];

        for ( $m = 1; $m <= 12; $m++ ) {
            $days = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $year ) );
            $values[ $m - 1 ]  = array_fill( 0, $days, null );
            $sources[ $m - 1 ] = array_fill( 0, $days, null );
        }

        $place = static function ( $row ) use ( $year, &$values ) {
            $date = substr( (string) ( $row['day_date'] ?? '' ), 0, 10 );
            if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $p ) ) return null;
            if ( (int) $p[1] !== $year ) return null;
            $mi = (int) $p[2] - 1;
            $di = (int) $p[3] - 1;
            if ( ! isset( $values[ $mi ] ) || ! array_key_exists( $di, $values[ $mi ] ) ) return null;
            return [ $mi, $di ];
        };

        // 1. Durchgang: der gespeicherte Durchschnitt.
        foreach ( $rows as $row ) {
            $at = $place( $row );
            if ( $at === null ) continue;
            $avg = $row['temp_avg'] ?? null;
            if ( $avg === null || $avg === '' ) continue;
            $values[ $at[0] ][ $at[1] ]  = round( (float) $avg, 1 );
            $sources[ $at[0] ][ $at[1] ] = 'avg';
        }

        // 2. Durchgang: nur, wo der erste nichts gefunden hat.
        foreach ( $rows as $row ) {
            $at = $place( $row );
            if ( $at === null ) continue;
            if ( $values[ $at[0] ][ $at[1] ] !== null ) continue;
            $min = $row['temp_min'] ?? null;
            $max = $row['temp_max'] ?? null;
            if ( $min === null || $min === '' || $max === null || $max === '' ) continue;
            $values[ $at[0] ][ $at[1] ]  = round( ( (float) $min + (float) $max ) / 2, 1 );
            $sources[ $at[0] ][ $at[1] ] = 'minmax';
        }

        return [ 'values' => $values, 'sources' => $sources ];
    }

    /**
     * Holt ein Jahr aus der Tagestabelle und gibt es als Raster zurueck.
     *
     * Ohne Modulfilter — genau wie get_history_data(). Der Aussenwert steht
     * auf der Zeile der Basisstation (module_id = station_id) und nicht
     * unter der MAC des Aussenmoduls; wer nach dem Aussenmodul filtert,
     * bekommt nichts. shape_heatmap_year() sortiert die Zeilen der
     * Innenmodule von selbst aus, weil sie in temp_* nichts tragen.
     */
    public static function get_heatmap_year( $year ) {
        global $wpdb;
        $year = (int) $year;
        $t    = $wpdb->prefix . NAWS_TABLE_DAILY;

        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is prefix + constant
            "SELECT day_date, temp_avg, temp_min, temp_max
               FROM {$t}
              WHERE YEAR(day_date) = %d
              ORDER BY day_date ASC",
            $year
        ), ARRAY_A );

        return self::shape_heatmap_year( is_array( $rows ) ? $rows : [], $year );
    }
```

- [x] **Step 4: Test laufen lassen**

Run: `php tests/test-heatmap-year.php`
Expected: PASS — die Schlusszeile endet auf „0 fehlgeschlagen". Die Zahl der bestandenen Pruefungen ist die, die herauskommt; sie ist kein Sollwert.

Schlägt der Test mit `Class "NAWS_Database" not found` fehl, prüfen, ob `class-naws-database.php` beim Laden ohne WordPress etwas erwartet — dann im Test die fehlende Konstante oder Funktion stubben, so wie `test-table-render.php` es mit `get_option()` tut.

- [x] **Step 5: Commit**

```bash
git add includes/class-naws-database.php tests/test-heatmap-year.php
git commit -m "Turn a year of daily rows into a calendar grid"
```

---

## Task 3: Die Beschriftung einer Kachel

**Files:**
- Modify: `includes/class-naws-helpers.php` (nach `format_value()`, ~Zeile 660)
- Test: `tests/test-heatmap-render.php` (erster Teil; die Datei entsteht hier und wächst in Task 4)

**Interfaces:**
- Consumes: `NAWS_Helpers::format_value( 'Temperature', $v )`, `NAWS_Helpers::get_unit( 'Temperature' )`
- Produces: `NAWS_Helpers::heatmap_label( $value, $source ): string`

**Warum eine eigene Funktion:** dieselbe Zeichenkette wird an drei Stellen gebraucht — im `data-l`-Attribut, im Screenreader-Text und in der AJAX-Antwort. Dreimal zusammengebaut wäre sie dreimal anders.

- [x] **Step 1: Den fehlschlagenden Test schreiben**

Neue Datei `tests/test-heatmap-render.php`:

```php
<?php
/**
 * Prueft die Beschriftung einer Kachel und das Markup von
 * templates/heatmap.php.
 *
 *   php tests/test-heatmap-render.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$PLUGIN = dirname( __DIR__ ) . '/';

$GLOBALS['opts'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
require_once __DIR__ . '/i18n-stubs.php';
function wp_date( $fmt, $ts ) { return gmdate( $fmt, $ts ); }

require_once $PLUGIN . 'includes/class-naws-helpers.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nNAWS_Helpers::heatmap_label()\n" . str_repeat( '-', 74 ) . "\n";

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];

check( 'ein Wert traegt seine Einheit',   NAWS_Helpers::heatmap_label( 8.2, 'avg' ), '8.2 °C' );
check( 'kein Wert sagt das',              NAWS_Helpers::heatmap_label( null, null ), 'No reading' );
check( 'ein gerechneter Wert nennt seine Herkunft',
    NAWS_Helpers::heatmap_label( 6.0, 'minmax' ), '6 °C · computed from min and max' );

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'F' ] ];

check( 'in Fahrenheit wird umgerechnet', NAWS_Helpers::heatmap_label( 0, 'avg' ), '32 °F' );
check( 'und die Einheit stimmt mit',     str_contains( NAWS_Helpers::heatmap_label( 20, 'avg' ), '°F' ), true );

$GLOBALS['opts'] = [ 'naws_settings' => [ 'temperature_unit' => 'C' ] ];

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [x] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-heatmap-render.php`
Expected: FAIL — `Call to undefined method NAWS_Helpers::heatmap_label()`

- [x] **Step 3: Die Funktion schreiben**

In `includes/class-naws-helpers.php`, nach `format_value()`:

```php
    /**
     * Was im Tooltip einer Heatmap-Kachel steht.
     *
     * Die Farbe der Kachel kommt aus dem gespeicherten Celsius-Wert, die
     * Beschriftung aus der eingestellten Einheit — hier wird umgerechnet.
     * Ein aus Minimum und Maximum gerechneter Wert sagt das dazu: er ist
     * eine andere Definition von "Tagesmittel" als der gespeicherte
     * Durchschnitt, und wer die Karte liest, soll das sehen koennen.
     *
     * @param float|null  $value   Grad Celsius, oder null fuer keinen Messwert.
     * @param string|null $source  'avg', 'minmax' oder null.
     */
    public static function heatmap_label( $value, $source = null ) {
        if ( $value === null || $value === '' ) {
            return __( 'No reading', 'xtx-integration-for-netatmo' );
        }

        $text = self::format_value( 'Temperature', $value ) . ' ' . self::get_unit( 'Temperature' );

        if ( $source === 'minmax' ) {
            /* translators: %s is a temperature that already carries its unit, e.g. "6 °C". */
            $text = sprintf( __( '%s · computed from min and max', 'xtx-integration-for-netatmo' ), $text );
        }

        return $text;
    }
```

- [x] **Step 4: Test laufen lassen**

Run: `php tests/test-heatmap-render.php`
Expected: PASS — die Schlusszeile endet auf „0 fehlgeschlagen".

- [x] **Step 5: Commit**

```bash
git add includes/class-naws-helpers.php tests/test-heatmap-render.php
git commit -m "Say in one place what a heatmap cell reads"
```

---

## Task 4: Das Template und der Shortcode

**Files:**
- Create: `templates/heatmap.php`
- Modify: `includes/class-naws-shortcodes.php` (Registrierung ~Zeile 28, Skriptregistrierung ~Zeile 57, `sc_heatmap()` nach `sc_history()`)
- Test: `tests/test-heatmap-render.php` (erweitern)

**Interfaces:**
- Consumes: `NAWS_Database::get_heatmap_year()`, `NAWS_Database::get_daily_data_range()`, `NAWS_Colors::heatmap_color()`, `NAWS_Colors::heatmap_scale()`, `NAWS_Helpers::heatmap_label()`, `NAWS_Helpers::get_unit()`
- Produces: Shortcode `[naws_heatmap]`; Markup mit `.naws-hm`, `.naws-hm-c` (Kachel mit Wert oder ohne), `.naws-hm-x` (Tag existiert nicht), Attributen `data-d`, `data-v`, `data-l`, `data-src`, `data-day`; registriertes Skript-Handle `naws-heatmap-boot`

- [x] **Step 1: Die Testfälle für das Markup anhängen**

In `tests/test-heatmap-render.php`, **vor** dem abschließenden `echo "\n" . str_repeat(...)`-Block einfügen:

```php
echo "\ntemplates/heatmap.php\n" . str_repeat( '-', 74 ) . "\n";

function wp_unique_id( $p = '' ) { static $n = 0; return $p . ( ++$n ); }
function wp_create_nonce( $a ) { return 'testnonce'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }

require_once dirname( __DIR__ ) . '/includes/class-naws-colors.php';

class NAWS_Fonts {
    public static function available() { return [ 'inherit' => 'Inherit' ]; }
    public static function sanitize_family( $s ) { return $s; }
}

class NAWS_Database {
    public static $year_data = [ 'values' => [], 'sources' => [] ];
    public static $range     = [ 'date_begin' => '2024-03-28', 'date_end' => '2026-09-02' ];
    public static function get_daily_data_range( $m = null ) { return self::$range; }
    public static function get_heatmap_year( $y ) { return self::$year_data; }
}

/** Ein leeres Raster fuer $year, in das der Test einzelne Tage setzt. */
function grid( int $year ): array {
    $v = []; $s = [];
    for ( $m = 1; $m <= 12; $m++ ) {
        $d = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $year ) );
        $v[ $m - 1 ] = array_fill( 0, $d, null );
        $s[ $m - 1 ] = array_fill( 0, $d, null );
    }
    return [ 'values' => $v, 'sources' => $s ];
}

function render_hm( array $atts_in = [], ?array $data = null, int $year = 2026 ): string {
    NAWS_Database::$year_data = $data ?? grid( $year );
    $atts = array_merge( [ 'year' => '', 'title' => 'Heatmap', 'legend' => 'yes' ], $atts_in );
    ob_start();
    include dirname( __DIR__ ) . '/templates/heatmap.php';
    return ob_get_clean();
}

check( 'das Template existiert', file_exists( dirname( __DIR__ ) . '/templates/heatmap.php' ), true );

$g = grid( 2026 );
$g['values'][0][0]  = 4.2;  $g['sources'][0][0]  = 'avg';
$g['values'][1][0]  = 6.0;  $g['sources'][1][0]  = 'minmax';
$g['values'][6][14] = 31.5; $g['sources'][6][14] = 'avg';
$html = render_hm( [ 'year' => '2026' ], $g );

check( 'ein Wrapper',                    substr_count( $html, 'class="naws-hm"' ), 1 );
check( 'zwoelf Monatszeilen',            substr_count( $html, '<tr class="naws-hm-row"' ), 12 );
check( 'jede Zeile hat 31 Spalten',      substr_count( $html, '<td' ), 12 * 31 );
check( 'der 31. April existiert nicht',  substr_count( $html, 'naws-hm-x' ), 7 ); // 4x30 Tage + Feb 2026 (28) = 4 + 3
check( 'der erste Januar traegt seine Farbe',
    str_contains( $html, 'data-d="2026-01-01"' ) && str_contains( $html, NAWS_Colors::heatmap_color( 4.2 ) ), true );
check( 'ein gerechneter Tag weist sich aus', str_contains( $html, 'data-src="minmax"' ), true );
check( 'ein gespeicherter ebenso',           str_contains( $html, 'data-src="avg"' ), true );
check( 'ein Tag ohne Messwert bekommt die Grau-Farbe',
    substr_count( $html, NAWS_Colors::heatmap_color( null ) ) > 300, true );
check( 'jede Kachel traegt ihre Tagesnummer', str_contains( $html, 'data-day="1"' ), true );

check( 'die Jahresknoepfe stehen da',   substr_count( $html, 'naws-hm-year' ) >= 3, true );
check( 'das gewaehlte Jahr ist markiert', substr_count( $html, 'is-active' ), 1 );
check( 'der Nonce reist mit',            str_contains( $html, 'data-nonce="testnonce"' ), true );

check( 'keine MAC-Adresse im Markup',
    (bool) preg_match( '/\b[0-9a-f]{2}(?::[0-9a-f]{2}){5}\b/i', $html ), false );
check( 'kein style-Block in der Ausgabe', str_contains( $html, '<style' ), false );
check( 'kein script-Block ebenso',        str_contains( $html, '<script' ), false );

$leap = render_hm( [ 'year' => '2024' ], grid( 2024 ), 2024 );
check( 'im Schaltjahr hat der Februar einen 29.', str_contains( $leap, 'data-d="2024-02-29"' ), true );
check( 'im Normaljahr nicht',                     str_contains( $html, 'data-d="2026-02-29"' ), false );

$noleg = render_hm( [ 'year' => '2026', 'legend' => 'no' ] );
check( 'legend=no laesst die Legende weg', str_contains( $noleg, 'naws-hm-legend' ), false );
check( 'sonst ist sie da',                 str_contains( $html, 'naws-hm-legend' ), true );

$bad = render_hm( [ 'year' => '1998' ] );
check( 'ein Jahr ausserhalb des Bereichs faellt zurueck', str_contains( $bad, 'data-year="1998"' ), false );

$xss = render_hm( [ 'year' => '2026', 'title' => '<script>x</script>' ] );
check( 'der Titel wird escaped', str_contains( $xss, '<script>x' ), false );
```

- [x] **Step 2: Test laufen lassen und den Fehlschlag sehen**

Run: `php tests/test-heatmap-render.php`
Expected: FAIL — das Template fehlt, `include` scheitert

- [x] **Step 3: Das Template schreiben**

Neue Datei `templates/heatmap.php`:

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_heatmap year="" title="" legend="yes"]
 *
 * Ein Jahr Aussen-Tagesdurchschnitt als Kalenderraster. Zeilen sind
 * Monate, Spalten Tage. Eine <table> und keine Grafik: die klebende
 * Monatsspalte ist damit eine Zeile CSS statt einer Zeile JavaScript, und
 * ein Screenreader liest "Maerz, 14., 8,2 °C" statt "Grafik".
 *
 * Die Farben stehen fertig im style-Attribut jeder Zelle, gerechnet von
 * NAWS_Colors::heatmap_color(). Ohne JavaScript ist die Karte deshalb
 * vollstaendig da — das Skript ergaenzt Tooltip, Jahreswechsel und die
 * Animation, es baut nichts auf.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$widget_id = 'naws-hm-' . wp_unique_id();
$nonce     = wp_create_nonce( 'naws_public_nonce' );
$ajax_url  = admin_url( 'admin-ajax.php' );

// Welche Jahre es gibt. MIN()/MAX() liefern immer eine Zeile — auf einer
// leeren Tabelle sind beide Spalten NULL, deshalb die Werte pruefen und
// nicht die Zeile (substr(null) ist seit PHP 8.1 deprecated).
$range   = NAWS_Database::get_daily_data_range();
$y_first = ! empty( $range['date_begin'] ) ? (int) substr( $range['date_begin'], 0, 4 ) : (int) gmdate( 'Y' );
$y_last  = ! empty( $range['date_end'] )   ? (int) substr( $range['date_end'],   0, 4 ) : (int) gmdate( 'Y' );
if ( $y_first < 2000 || $y_first > $y_last ) $y_first = $y_last;
$years = range( $y_last, $y_first ); // neuestes zuerst

$wanted = (int) ( $atts['year'] ?? 0 );
$now    = (int) gmdate( 'Y' );
$year   = in_array( $wanted, $years, true ) ? $wanted
        : ( in_array( $now, $years, true ) ? $now : $y_last );

$data   = NAWS_Database::get_heatmap_year( $year );
$values = $data['values'];
$srcs   = $data['sources'];
$grey   = NAWS_Colors::heatmap_color( null );

$has_any = false;
foreach ( $values as $month ) {
    foreach ( $month as $v ) { if ( $v !== null ) { $has_any = true; break 2; } }
}
?>
<div id="<?php echo esc_attr( $widget_id ); ?>" class="naws-hm"
     data-nonce="<?php echo esc_attr( $nonce ); ?>"
     data-ajax="<?php echo esc_attr( $ajax_url ); ?>"
     data-year="<?php echo esc_attr( (string) $year ); ?>">

  <div class="naws-hm-hdr">
    <div class="naws-hm-title"><?php echo esc_html( $atts['title'] ?? '' ); ?></div>
    <div class="naws-hm-years">
      <?php foreach ( $years as $y ) : ?>
        <button type="button" class="naws-hm-year<?php echo $y === $year ? ' is-active' : ''; ?>"
                data-year="<?php echo esc_attr( (string) $y ); ?>"><?php echo esc_html( (string) $y ); ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ( ! $has_any ) : ?>
    <div class="naws-hm-empty"><?php esc_html_e( 'No data for this period.', 'xtx-integration-for-netatmo' ); ?></div>
  <?php endif; ?>

  <div class="naws-hm-scroll">
    <table class="naws-hm-grid">
      <thead>
        <tr>
          <th class="naws-hm-corner"><span class="screen-reader-text"><?php esc_html_e( 'Month', 'xtx-integration-for-netatmo' ); ?></span></th>
          <?php for ( $d = 1; $d <= 31; $d++ ) : ?>
            <th class="naws-hm-dh" scope="col"><?php echo esc_html( (string) $d ); ?></th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php for ( $m = 1; $m <= 12; $m++ ) :
            $days  = count( $values[ $m - 1 ] ?? [] );
            $mname = wp_date( 'F', gmmktime( 12, 0, 0, $m, 1, $year ) );
        ?>
        <tr class="naws-hm-row">
          <th class="naws-hm-mh" scope="row"><?php echo esc_html( $mname ); ?></th>
          <?php for ( $d = 1; $d <= 31; $d++ ) :
              if ( $d > $days ) : ?>
                <td class="naws-hm-x" aria-hidden="true"></td>
              <?php continue; endif;

              $v     = $values[ $m - 1 ][ $d - 1 ] ?? null;
              $src   = $srcs[ $m - 1 ][ $d - 1 ] ?? null;
              $color = $v === null ? $grey : NAWS_Colors::heatmap_color( $v );
              $label = NAWS_Helpers::heatmap_label( $v, $src );
              $date  = sprintf( '%04d-%02d-%02d', $year, $m, $d );
          ?>
            <td class="naws-hm-c"
                style="background:<?php echo esc_attr( $color ); ?>"
                data-d="<?php echo esc_attr( $date ); ?>"
                data-day="<?php echo esc_attr( (string) $d ); ?>"
                data-v="<?php echo esc_attr( $v === null ? '' : (string) $v ); ?>"
                data-l="<?php echo esc_attr( $label ); ?>"
                data-src="<?php echo esc_attr( (string) $src ); ?>">
              <span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
            </td>
          <?php endfor; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <?php if ( ( $atts['legend'] ?? 'yes' ) !== 'no' ) :
      $scale = NAWS_Colors::heatmap_scale();
      $last  = count( $scale ) - 1;
      $stops = [];
      foreach ( $scale as $i => $s ) {
          $stops[] = $s[1] . ' ' . round( $i / $last * 100 ) . '%';
      }
  ?>
  <div class="naws-hm-legend">
    <span class="naws-hm-legend-min"><?php echo esc_html( NAWS_Helpers::heatmap_label( $scale[0][0], 'avg' ) ); ?></span>
    <span class="naws-hm-legend-bar" style="background:linear-gradient(90deg,<?php echo esc_attr( implode( ',', $stops ) ); ?>)"></span>
    <span class="naws-hm-legend-max"><?php echo esc_html( NAWS_Helpers::heatmap_label( $scale[ $last ][0], 'avg' ) ); ?></span>
  </div>
  <?php endif; ?>
</div>
```

- [x] **Step 4: Shortcode registrieren und rendern**

In `includes/class-naws-shortcodes.php`, im Konstruktor nach `add_shortcode( 'naws_history', … );`:

```php
        add_shortcode( 'naws_heatmap',   [ $this, 'sc_heatmap' ] );
```

In `enqueue_frontend_assets()`, nach der Registrierung von `naws-history-boot`:

```php
        wp_register_script( 'naws-heatmap-boot',
            NAWS_PLUGIN_URL . 'assets/js/heatmap-boot.js',
            [ 'naws-frontend' ], NAWS_VERSION, true );
```

Und die Shortcode-Methode, direkt nach `sc_history()`:

```php
    // ----------------------------------------------------------------
    // [naws_heatmap year="" title="" legend="yes"]
    // ----------------------------------------------------------------

    public function sc_heatmap( $atts ) {
        $this->enqueue_frontend();
        wp_enqueue_script( 'naws-heatmap-boot' );

        $atts = shortcode_atts( [
            'year'   => '',
            'title'  => __( 'Daily Average Temperature', 'xtx-integration-for-netatmo' ),
            'legend' => 'yes',
        ], $atts, 'naws_heatmap' );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/heatmap.php';
        return ob_get_clean();
    }
```

- [x] **Step 5: Test laufen lassen**

Run: `php tests/test-heatmap-render.php`
Expected: PASS

Der Fall `naws-hm-x` verdient Nachrechnen, falls er fehlschlägt: 2026 hat vier Monate mit 30 Tagen (April, Juni, September, November) — je eine leere Zelle — und einen Februar mit 28 — drei leere Zellen. Zusammen sieben.

- [x] **Step 6: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe

- [x] **Step 7: Commit**

```bash
git add templates/heatmap.php includes/class-naws-shortcodes.php tests/test-heatmap-render.php
git commit -m "Lay a year of daily means out as a calendar"
```

---

## Task 5: Das Aussehen

**Files:**
- Modify: `assets/css/frontend.css` (ans Ende anhängen)

**Interfaces:**
- Consumes: die Klassen aus Task 4 und die CSS-Variablen `--naws-surface`, `--naws-text`, `--naws-text-muted`, `--naws-border`, `--naws-radius`, die `NAWS_Colors::get_inline_css()` auf `.naws-wrap, .naws-wx` setzt
- Produces: nichts, was ein späterer Task konsumiert

**Zu prüfen vor dem Schreiben:** Welche Variablennamen `get_inline_css()` tatsächlich ausgibt — `grep -o '\--naws-[a-z0-9-]*' includes/class-naws-colors.php | sort -u`. Nur benutzen, was dort steht; ein erfundener Name fällt auf eine leere Farbe zurück.

- [x] **Step 1: Die Stile schreiben**

Ans Ende von `assets/css/frontend.css`:

```css
/* ══════════════════════════════════════════════════════════════════
   [naws_heatmap] — Kalenderraster des Tagesdurchschnitts
   ══════════════════════════════════════════════════════════════════ */

.naws-hm {
    position: relative;
    background: var(--naws-surface, #fff);
    border: 1px solid var(--naws-border, #e0eeee);
    border-radius: 12px;
    padding: 14px;
    color: var(--naws-text, #427272);
}

.naws-hm-hdr {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.naws-hm-title { font-weight: 600; font-size: 15px; }

.naws-hm-years { display: flex; flex-wrap: wrap; gap: 4px; }

.naws-hm-year {
    border: 1px solid var(--naws-border, #e0eeee);
    background: transparent;
    color: inherit;
    font: inherit;
    font-size: 13px;
    line-height: 1;
    padding: 5px 10px;
    border-radius: 999px;
    cursor: pointer;
}
.naws-hm-year:hover { border-color: var(--naws-primary, #2d5252); }
.naws-hm-year.is-active {
    background: var(--naws-primary, #2d5252);
    border-color: var(--naws-primary, #2d5252);
    color: #fff;
}

/* Waagerecht scrollen statt schrumpfen: eine Kachel bleibt gross genug,
   um sie mit dem Finger zu treffen. */
.naws-hm-scroll { overflow-x: auto; overflow-y: hidden; }

.naws-hm-grid { border-collapse: separate; border-spacing: 2px; width: auto; }

.naws-hm-grid th {
    font-weight: 500;
    font-size: 11px;
    color: var(--naws-text-muted, #7aa0a0);
    padding: 0;
    background: var(--naws-surface, #fff);
}

.naws-hm-dh { min-width: 14px; text-align: center; }

/* Die Monatsspalte bleibt beim Scrollen stehen. */
.naws-hm-mh,
.naws-hm-corner {
    position: sticky;
    left: 0;
    z-index: 2;
    text-align: right;
    padding-right: 8px;
    white-space: nowrap;
}

.naws-hm-c,
.naws-hm-x {
    width: 14px;
    min-width: 14px;
    height: 14px;
    padding: 0;
    border-radius: 3px;
}

.naws-hm-c { cursor: pointer; transition: background-color 250ms ease; }
.naws-hm-c:hover { outline: 2px solid var(--naws-text, #427272); outline-offset: 1px; }

.naws-hm-x { background: none; }

/* Aufbau: eine Welle von links nach rechts. Sie haengt am Setzen von
   --naws-hm-d durch heatmap-boot.js — ohne JavaScript traegt keine Zelle
   die Klasse und die Karte steht sofort vollstaendig da. */
.naws-hm.is-animating .naws-hm-c {
    animation: naws-hm-in 320ms ease both;
    animation-delay: var(--naws-hm-d, 0ms);
}

@keyframes naws-hm-in {
    from { opacity: 0; transform: scale(0.4); }
    to   { opacity: 1; transform: scale(1); }
}

@media (prefers-reduced-motion: reduce) {
    .naws-hm.is-animating .naws-hm-c { animation: none; }
    .naws-hm-c { transition: none; }
}

.naws-hm-tip {
    position: absolute;
    z-index: 10;
    pointer-events: none;
    background: var(--naws-text-darkest, #1a3535);
    color: #fff;
    font-size: 12px;
    line-height: 1.4;
    padding: 5px 8px;
    border-radius: 6px;
    white-space: nowrap;
    transform: translate(-50%, -100%);
}

.naws-hm-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    font-size: 11px;
    color: var(--naws-text-muted, #7aa0a0);
}
.naws-hm-legend-bar { flex: 1; height: 8px; border-radius: 4px; }

.naws-hm-empty {
    padding: 10px 0;
    font-size: 13px;
    color: var(--naws-text-muted, #7aa0a0);
}
```

- [x] **Step 2: Die benutzten Variablen gegen die Wirklichkeit prüfen**

Run: `grep -o '\--naws-[a-z0-9-]*' includes/class-naws-colors.php | sort -u > /tmp/have.txt; grep -o 'var(--naws-[a-z0-9-]*' assets/css/frontend.css | sed 's/var(//' | sort -u > /tmp/used.txt; comm -13 /tmp/have.txt /tmp/used.txt`
Expected: nur Namen, die auch vorher schon in `frontend.css` standen. Taucht einer der oben neu benutzten dort auf, ihn durch einen vorhandenen ersetzen oder den Fallback als endgültig hinnehmen.

- [x] **Step 3: Commit**

```bash
git add assets/css/frontend.css
git commit -m "Dress the heatmap, and let it wipe in from the left"
```

---

## Task 6: Tooltip, Jahreswechsel, Animation

**Files:**
- Create: `assets/js/heatmap-boot.js`

**Interfaces:**
- Consumes: `nawsFrontend.i18n.js_load_failed` (aus `enqueue_frontend()`), das Markup aus Task 4, die Klassen aus Task 5, den Endpunkt aus Task 7
- Produces: nichts für spätere Tasks

**Reihenfolge-Hinweis:** Dieser Task wird vor Task 7 geschrieben, also gibt es den Endpunkt beim ersten Prüfen noch nicht. Der Jahreswechsel schlägt dann mit einer Fehlermeldung fehl — das ist der erwartete Zwischenstand, und die Fehlerbehandlung wird dabei gleich mitgeprüft.

- [x] **Step 1: Das Skript schreiben**

Neue Datei `assets/js/heatmap-boot.js`:

```js
/**
 * [naws_heatmap] — Tooltip, Jahreswechsel, Aufbau.
 *
 * Das Skript rechnet nichts. Farben und Beschriftungen kommen fertig vom
 * Server, hier werden sie gesetzt. So sieht ein nachgeladenes Jahr genau
 * so aus wie das serverseitig gerenderte, ohne dass die Farbrechnung ein
 * zweites Mal existiert.
 *
 * @package NAWS
 */
(function () {
    'use strict';

    var CFG = window.nawsFrontend || {};
    var I18N = CFG.i18n || {};

    function loadFailedText(status) {
        var tpl = I18N.js_load_failed || 'Could not load data (HTTP %s)';
        return tpl.replace('%s', status);
    }

    /** Die Verzoegerung je Kachel: eine Welle von links nach rechts. */
    function stagger(root) {
        var cells = root.querySelectorAll('.naws-hm-c');
        for (var i = 0; i < cells.length; i++) {
            var day = parseInt(cells[i].getAttribute('data-day') || '1', 10);
            cells[i].style.setProperty('--naws-hm-d', ((day - 1) * 22) + 'ms');
        }
        root.classList.add('is-animating');
    }

    function makeTip(root) {
        var tip = document.createElement('div');
        tip.className = 'naws-hm-tip';
        tip.style.display = 'none';
        root.appendChild(tip);
        return tip;
    }

    function bindTip(root, tip) {
        root.addEventListener('mouseover', function (e) {
            var cell = e.target.closest ? e.target.closest('.naws-hm-c') : null;
            if (!cell || !root.contains(cell)) { return; }
            var date = cell.getAttribute('data-d') || '';
            var label = cell.getAttribute('data-l') || '';
            tip.textContent = date + ' — ' + label;
            tip.style.display = '';
            var box = cell.getBoundingClientRect();
            var host = root.getBoundingClientRect();
            tip.style.left = (box.left - host.left + box.width / 2) + 'px';
            tip.style.top = (box.top - host.top - 6) + 'px';
        });

        root.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    }

    /** Traegt ein geholtes Jahr in die vorhandenen Kacheln ein. */
    function paint(root, data) {
        var rows = root.querySelectorAll('.naws-hm-row');
        for (var m = 0; m < rows.length; m++) {
            var cells = rows[m].querySelectorAll('.naws-hm-c, .naws-hm-x');
            var vals = (data.months && data.months[m]) || [];
            var cols = (data.colors && data.colors[m]) || [];
            var labs = (data.labels && data.labels[m]) || [];
            var srcs = (data.sources && data.sources[m]) || [];

            for (var d = 0; d < cells.length; d++) {
                var cell = cells[d];
                var exists = d < vals.length;

                cell.className = exists ? 'naws-hm-c' : 'naws-hm-x';
                if (!exists) {
                    cell.removeAttribute('style');
                    cell.setAttribute('aria-hidden', 'true');
                    cell.textContent = '';
                    continue;
                }

                cell.removeAttribute('aria-hidden');
                cell.style.background = cols[d] || '';
                cell.setAttribute('data-day', String(d + 1));
                cell.setAttribute('data-v', vals[d] === null ? '' : String(vals[d]));
                cell.setAttribute('data-l', labs[d] || '');
                cell.setAttribute('data-src', srcs[d] || '');
                cell.setAttribute(
                    'data-d',
                    data.year + '-' + ('0' + (m + 1)).slice(-2) + '-' + ('0' + (d + 1)).slice(-2)
                );

                var sr = cell.querySelector('.screen-reader-text');
                if (!sr) {
                    sr = document.createElement('span');
                    sr.className = 'screen-reader-text';
                    cell.appendChild(sr);
                }
                sr.textContent = labs[d] || '';
            }
        }
        root.setAttribute('data-year', String(data.year));
    }

    function bindYears(root) {
        var cache = {};
        var buttons = root.querySelectorAll('.naws-hm-year');

        function activate(year) {
            for (var i = 0; i < buttons.length; i++) {
                var on = buttons[i].getAttribute('data-year') === String(year);
                buttons[i].classList.toggle('is-active', on);
            }
        }

        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', function () {
                var year = this.getAttribute('data-year');
                if (!year || root.getAttribute('data-year') === year) { return; }

                // Der Aufbau laeuft genau einmal. Ein Jahreswechsel blendet
                // um, statt dieselbe Welle ein drittes Mal zu zeigen.
                root.classList.remove('is-animating');
                activate(year);

                if (cache[year]) { paint(root, cache[year]); return; }

                var body = new URLSearchParams();
                body.append('action', 'naws_get_heatmap_data');
                body.append('nonce', root.getAttribute('data-nonce') || '');
                body.append('year', year);

                var empty = root.querySelector('.naws-hm-empty');
                if (empty) { empty.textContent = ''; }

                fetch(root.getAttribute('data-ajax'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                    .then(function (res) {
                        if (!res.ok) { throw new Error(String(res.status)); }
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json || !json.success || !json.data) { throw new Error('0'); }
                        cache[year] = json.data;
                        paint(root, json.data);
                    })
                    .catch(function (err) {
                        activate(root.getAttribute('data-year'));
                        var box = root.querySelector('.naws-hm-empty');
                        if (!box) {
                            box = document.createElement('div');
                            box.className = 'naws-hm-empty';
                            root.appendChild(box);
                        }
                        box.textContent = loadFailedText(err.message || '0');
                    });
            });
        }
    }

    function boot() {
        var maps = document.querySelectorAll('.naws-hm');
        for (var i = 0; i < maps.length; i++) {
            var root = maps[i];
            if (root.getAttribute('data-naws-booted')) { continue; }
            root.setAttribute('data-naws-booted', '1');
            bindTip(root, makeTip(root));
            bindYears(root);
            stagger(root);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
```

- [x] **Step 2: Auf Syntaxfehler prüfen**

**Auf dieser Maschine gibt es kein `node`** — geprüft am 2026-09-02, `command -v node` findet nichts. Der Syntaxcheck fällt damit auf den Browser: in Task 9, Schritt 6, die Karte auf dev öffnen und die Konsole ansehen. Ein Syntaxfehler zeigt sich dort sofort, weil dann gar keine Kachel eine Verzögerung bekommt und die Animation ausbleibt.

Wer Node hat: `node --check assets/js/heatmap-boot.js`, erwartet wird keine Ausgabe.

- [x] **Step 3: Prüfen, dass keine deutschen Anzeigetexte im Skript stehen**

Run: `php tests/test-frontend-i18n.php`
Expected: PASS. Dieser Test schlägt bei jedem String-Literal mit deutschem Umlaut in den Frontend-Skripten fehl. Falls er `heatmap-boot.js` noch nicht kennt, die Datei in seiner Dateiliste ergänzen — das ist der Sinn des Tests.

- [x] **Step 4: Commit**

```bash
git add assets/js/heatmap-boot.js
git commit -m "Let the heatmap answer the pointer and change its year"
```

---

## Task 7: Der Endpunkt

**Files:**
- Modify: `includes/class-naws-ajax.php` (Registrierung ~Zeile 43, Methode nach `get_history_data()`)

**Interfaces:**
- Consumes: `NAWS_Database::get_heatmap_year()`, `NAWS_Database::get_daily_data_range()`, `NAWS_Colors::heatmap_color()`, `NAWS_Helpers::heatmap_label()`
- Produces: `action=naws_get_heatmap_data` mit `year`, Antwort `{ year, months, sources, colors, labels }`

- [x] **Step 1: Registrieren**

In `includes/class-naws-ajax.php`, im Konstruktor nach den `naws_get_history_data`-Zeilen:

```php
        add_action( 'wp_ajax_naws_get_heatmap_data',        [ $this, 'get_heatmap_data' ] );
        add_action( 'wp_ajax_nopriv_naws_get_heatmap_data', [ $this, 'get_heatmap_data' ] );
```

- [x] **Step 2: Die Methode schreiben**

Nach `get_history_data()`:

```php
    /**
     * Ein Jahr fuer die Heatmap.
     *
     * Farben und Beschriftungen werden hier gerechnet und nicht im
     * Browser: dieselbe Rechnung wie im Template, also sieht ein
     * nachgeladenes Jahr aus wie ein gerendertes. Die Beschriftung haengt
     * ausserdem an der Einheiteneinstellung, und die im JavaScript
     * nachzubauen hiesse, format_value() ein zweites Mal zu schreiben.
     */
    public function get_heatmap_data() {
        check_ajax_referer( 'naws_public_nonce', 'nonce' );
        nocache_headers();

        $year = isset( $_POST['year'] ) ? (int) $_POST['year'] : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to int

        $range   = NAWS_Database::get_daily_data_range();
        $y_first = ! empty( $range['date_begin'] ) ? (int) substr( $range['date_begin'], 0, 4 ) : (int) gmdate( 'Y' );
        $y_last  = ! empty( $range['date_end'] )   ? (int) substr( $range['date_end'],   0, 4 ) : (int) gmdate( 'Y' );
        if ( $y_first < 2000 || $y_first > $y_last ) {
            $y_first = $y_last;
        }

        // Ein Jahr ausserhalb des Bereichs bekommt einen Fehler und keine
        // Antwort, die wie ein leeres Jahr aussieht — dieselbe Haltung wie
        // bei der unbekannten Modulreferenz in requested_module_id().
        if ( $year < $y_first || $year > $y_last ) {
            wp_send_json_error( [ 'message' => 'Year out of range.' ], 400 );
            return;
        }

        $data   = NAWS_Database::get_heatmap_year( $year );
        $colors = [];
        $labels = [];

        foreach ( $data['values'] as $mi => $days ) {
            $colors[ $mi ] = [];
            $labels[ $mi ] = [];
            foreach ( $days as $di => $v ) {
                $colors[ $mi ][ $di ] = NAWS_Colors::heatmap_color( $v );
                $labels[ $mi ][ $di ] = NAWS_Helpers::heatmap_label( $v, $data['sources'][ $mi ][ $di ] ?? null );
            }
        }

        wp_send_json_success( [
            'year'    => $year,
            'months'  => $data['values'],
            'sources' => $data['sources'],
            'colors'  => $colors,
            'labels'  => $labels,
        ] );
    }
```

- [x] **Step 3: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe

- [x] **Step 4: Commit**

```bash
git add includes/class-naws-ajax.php
git commit -m "Serve one year of the heatmap, and refuse the years there are none of"
```

---

## Task 8: Die Farben einstellbar machen

**Files:**
- Modify: `admin/views/appearance.php` (`$tabs` ~Zeile 64, `$color_labels` ~Zeile 11, neuer Pane nach dem `history`-Pane)

**Interfaces:**
- Consumes: `$groups['heatmap']['keys']` aus Task 1, `$colors`, `$defaults`
- Produces: nichts für spätere Tasks

- [x] **Step 1: Den Tab eintragen**

In `admin/views/appearance.php`, im Array `$tabs` nach `'history'`:

```php
    'heatmap'   => __( 'Heatmap Scale', 'xtx-integration-for-netatmo' ),
```

- [x] **Step 2: Die Beschriftungen eintragen**

In `$color_labels`, ans Ende:

```php
    // Heatmap
    'heatmap_t_m10'   => __( '−10 °C and below', 'xtx-integration-for-netatmo' ),
    'heatmap_t_m5'    => __( '−5 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_0'     => __( '0 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_5'     => __( '5 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_10'    => __( '10 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_15'    => __( '15 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_20'    => __( '20 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_25'    => __( '25 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_30'    => __( '30 °C', 'xtx-integration-for-netatmo' ),
    'heatmap_t_35'    => __( '35 °C and above', 'xtx-integration-for-netatmo' ),
    'heatmap_no_data' => __( 'Day without a reading', 'xtx-integration-for-netatmo' ),
```

Die Stützpunkte stehen bewusst in Celsius, auch wenn die Anzeige auf Fahrenheit steht: die Skala hängt an Celsius (Task 1), und ein Feld „95 °F" zu beschriften, das intern 35 bedeutet, wäre eine Falle.

- [x] **Step 3: Den Pane schreiben**

Nach dem schließenden `</div>` des Panes `data-pane="history"`:

```php
        <!-- ============================================================
             Tab 6: Heatmap-Skala
             ============================================================ -->
        <div class="naws-appearance-pane" data-pane="heatmap">
            <p class="description"><?php esc_html_e( 'Colour scale for [naws_heatmap]. The stops are degrees Celsius; values in between are interpolated. They stay in Celsius even when the display unit is Fahrenheit, because the colour is taken from the stored value.', 'xtx-integration-for-netatmo' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
                    <table class="form-table naws-color-table">
                        <tbody>
                        <?php foreach ( $groups['heatmap']['keys'] as $key ) : ?>
                            <tr>
                                <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                                <td>
                                    <input type="text"
                                           id="naws-<?php echo esc_attr( $key ); ?>"
                                           name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                           value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                           class="naws-color-picker"
                                           data-preview="heatmap"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label">Live-Vorschau — Temperaturskala</div>
                    <div id="naws-preview-heatmap" class="naws-pv-heatmap">
                        <?php foreach ( NAWS_Colors::HEATMAP_KEYS as $i => $key ) : ?>
                        <div class="naws-pv-heatmap-stop" data-key="<?php echo esc_attr( $key ); ?>">
                            <span class="naws-pv-heatmap-swatch"
                                  style="display:block;width:100%;height:26px;border-radius:4px;background:<?php echo esc_attr( $colors[ $key ] ); ?>"></span>
                            <span class="naws-pv-heatmap-deg"><?php echo esc_html( NAWS_Colors::HEATMAP_STOPS[ $i ] . ' °C' ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
```

- [x] **Step 4: Prüfen, dass die Seite rendert**

Run: `php -l admin/views/appearance.php`
Expected: `No syntax errors detected`

- [x] **Step 5: Commit**

```bash
git add admin/views/appearance.php
git commit -m "Put the heatmap's ten stops on the appearance screen"
```

---

## Task 9: Dokumentation, Katalog, Prüfung auf dev

**Files:**
- Modify: `admin/views/shortcodes.php`, `readme.txt`, `CHANGELOG.md`, `languages/xtx-integration-for-netatmo.pot`

**Interfaces:**
- Consumes: alles Vorherige
- Produces: nichts

- [x] **Step 1: Den Shortcode in die Referenz aufnehmen**

In `admin/views/shortcodes.php`, direkt nach dem schließenden `</div>` der Karte `[naws_history]` (die endet nach dem Block `naws-inline-examples`, ungefähr Zeile 231):

```php
    <div class="naws-sc-card">
        <h3><code>[naws_heatmap]</code></h3>
        <p><?php esc_html_e( 'One year of outdoor daily average temperature as a calendar grid: months down, days across, one coloured tile per day.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_heatmap]</pre><button class="naws-copy-btn" data-copy='[naws_heatmap]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>year</code></td><td><?php esc_html_e( 'Year to open with. A year with no readings falls back to the current one.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'current year', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Title above the grid', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'Daily Average Temperature', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>legend</code></td><td><?php esc_html_e( 'Show the colour scale below the grid (yes/no)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">yes</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_heatmap year="2025"]</code> &rarr; startet mit 2025</div>
            <div class="naws-inline-ex"><code>[naws_heatmap legend="no"]</code> &rarr; ohne Farbskala</div>
        </div>
    </div>
```

Die Farben stellt man unter **Darstellung → Heatmap Scale** ein; das steht in der Beschreibung des Tabs und muss hier nicht wiederholt werden.

- [x] **Step 2: readme.txt ergänzen**

In `readme.txt`, in der Shortcode-Aufzählung des Abschnitts `== Description ==`, direkt nach der Zeile für `[naws_history]` (Zeile 50):

```
* `[naws_heatmap]` – One year of outdoor daily average temperature as a calendar grid, one tile per day, with a year selector (`year`, `title`, `legend`)
```

- [x] **Step 3: CHANGELOG.md ergänzen**

`## [Unreleased]` ist mit 1.9.10 verbraucht worden, der Abschnitt wird also neu angelegt — zwischen der Einleitungszeile und `## [1.9.10]`:

```markdown
## [Unreleased]

Merged and waiting for the release it will ship in. Nothing here is published yet.

### Added

- **`[naws_heatmap]` — a year of daily means as a calendar.** Twelve rows of months, thirty-one columns of days, one coloured tile per day, and a row of buttons to page through the years. What a curve makes you read, a grid lets you see: the cold fortnight in February and the hot week in July are shapes on the page rather than wiggles on a line.

  It reads the same column of the same table as the annual-average chart — `temp_avg` in `naws_daily_summary`, queried without a module filter. That detail matters more than it looks: the outdoor daily mean does not live under the outdoor module's MAC but on the base station's row, where `module_id` equals `station_id`. The chart never noticed because it never filtered; anything that does filter comes back empty.

  The ten colour stops are settings, under Appearance → Heatmap Scale, and values between two stops are interpolated so twelve and thirteen degrees do not collapse into one shade. They are anchored in Celsius even when the display is set to Fahrenheit: the colour comes from the stored value, the tooltip from the unit you chose.

  Where a day has no stored average but does have a minimum and a maximum, the tile shows `(min + max) / 2` — the definition climate series have used since the nineteenth century. It is not the same number as the stored average, though: across the 861 days that carry both, the two differ by 0.44 K on average and by 3.20 K at worst. The tooltip and the screen reader text therefore say which of the two a tile is showing.

  The grid is a `<table>` rather than a drawing, so the month column stays put while the days scroll sideways, and a screen reader reads "March, 14th, 8.2 °C" instead of "image". Colours are rendered server-side, so the map is complete without JavaScript; the wipe it builds in with is an addition, and it stands still for anyone who asked their system for reduced motion.
```

- [x] **Step 4: Den Katalog neu erzeugen**

Run: `php docs/i18n/catalog/makepot.php`
Expected: `.pot` enthält die neuen Strings. Prüfen:

Run: `grep -c 'msgid' languages/xtx-integration-for-netatmo.pot && grep -n 'No reading\|computed from min and max\|Daily Average Temperature\|Heatmap Scale' languages/xtx-integration-for-netatmo.pot`
Expected: die vier Strings sind da.

- [x] **Step 5: Die ganze Suite laufen lassen**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done`
Expected: keine Ausgabe

- [x] **Step 6: Auf dev prüfen**

```bash
powershell -ExecutionPolicy Bypass -File .\build-zip.ps1
```

Dann über den MCP-Server `novamira-dev`: hochladen, nach `WP_PLUGIN_DIR` entpacken, **`opcache_reset()`** — ohne das misst man das alte Verhalten. Anschließend über `execute-php` prüfen:

- `do_shortcode( '[naws_heatmap]' )` rendert, enthält keine MAC-Adresse, keinen `<style>`- und keinen `<script>`-Block.
- Zwölf `naws-hm-row`, die richtige Zahl `naws-hm-x` für das gewählte Jahr.
- `debug.log` wächst dabei um **0 Bytes**.
- Den Endpunkt über die Handler-Methode aufrufen wie in den Prüfungen zu 1.9.10: gültiges Jahr → `success:true`; 1998 → `success:false`.
- `naws_settings['temperature_unit'] = 'F'` setzen, neu rendern: die Beschriftungen stehen in °F, **die Farben sind unverändert**. Danach zurückstellen.
- Eine Farbe in `naws_appearance` ändern, `NAWS_Colors::flush_cache()`, neu rendern: die Kacheln folgen.

- [x] **Step 7: Commit**

```bash
git add admin/views/shortcodes.php readme.txt CHANGELOG.md languages/xtx-integration-for-netatmo.pot
git commit -m "Write the heatmap down where people look for it"
```

---

## Was danach noch aussteht

Kein Task, aber vor einem Release fällig:

1. **Die vier neuen Strings übersetzen** — Deutsch und Norwegisch auf translate.wordpress.org. Jedes Release ohne diesen Schritt senkt die Quote; bei 1.9.8 und 1.9.9 ist genau das passiert.
2. **Version schneiden** nach `docs/superpowers/specs/` → dem Ablauf in der Release-Routine: drei Stellen, Changelog in beiden Dateien, ZIP, annotierter Tag, GitHub-Release mit angehängtem ZIP, dann wp.org.
3. **Branch mergen** — `heatmap-shortcode` nach `main`.
