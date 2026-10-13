# Regenradar `[naws_radar]` — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein neuer Shortcode `[naws_radar]` zeigt ein animiertes Regenradar um die Station: in Deutschland DWD (1 h zurück + 2 h Vorschau, 15-min-Schritte), sonst RainViewer (bis 2 h zurück, 10-min-Schritte), auf einer gedämpften OpenStreetMap-Karte. Alle Bilder holt der Server und legt sie unter `uploads/naws-radar/` ab; der Browser des Besuchers spricht nur mit der eigenen Website.

**Architecture:** Vier Klassen mit je einer Aufgabe: `NAWS_Radar_Geo` (reine Mercator-Rechnung), `NAWS_Radar_Source` (welche Quelle, welche Zeiten, welche URLs), `NAWS_Radar_Store` (Dateien, Manifest, Sperre, Sperrfrist, Aufräumen über `WP_Filesystem`), `NAWS_Radar` (Hintergrundlauf, Shortcode-Ausgabe, öffentliche REST-Route mit dem Manifest). Der Shortcode gibt sofort den gespeicherten Stand aus und plant bei veraltetem Stand einen einmaligen Hintergrundlauf (`wp_schedule_single_event`). `assets/js/radar.js` spielt die Bilder ab und fragt das Manifest über die eigene REST-Route nach.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, kein Build-Schritt, keine Bibliothek. Tests sind eigenständige PHP-Dateien (`php tests/test-x.php`), Player-Logik per Node 24 (über `proc_open`, Skript per stdin). PHP 8.4 liegt im WinGet-Pfad (`command -v php` in Git Bash).

**Spec:** `docs/superpowers/specs/2026-10-13-regenradar-design.md` (Commit `41fc3b2`) — Abweichungen siehe „Nachtrag zur Spec“ unten; Task 0 schreibt sie in die Spec.

**Branch:** `regenradar` (existiert, von `main` = `a9e449d`); am Ende `git merge --no-ff` nach `main` erst auf Franks Ansage. **Nichts pushen** ohne Franks Ansage.

## Nachtrag zur Spec (vor dem Bau mit Frank abzustimmen)

1. **Kein Einstellungs-Reiter:** Die Seite „Einstellungen“ hat keine Reiter. Schalter „Regenradar erlauben“, Liste der Bildsätze und Knopf „Zwischenspeicher leeren“ kommen als **Block „Regenradar“ auf die Einstellungsseite** (rechte Spalte, nach „Wetter-Icon“). Die **Farben** kommen als **Reiter „Regenradar“ ins Erscheinungsbild** (dort gibt es Reiter, Farben gehören in `naws_appearance`).
2. **DWD-Vergangenheitsbilder werden wiederverwendet:** Ein gemessenes Bild ändert sich nicht mehr. Pro Erneuerung gehen deshalb nur 1 neues Vergangenheitsbild + 8 Vorschau-Bilder hinaus (9 statt 13 Abrufe); beim ersten Lauf 13.
3. **Karte:** Für 150 km sind das bei Zoom 10 rund 49 Kacheln (einmalig, ca. 0,5–1 MB), bei 50 km 25 Kacheln (Zoom 11).
4. **Gespeichert wird `@since 2.2.0`** (neues Feature → Minor-Version); der Schnitt selbst ist nicht Teil dieses Plans.

## Global Constraints

- Text-Domain `xtx-integration-for-netatmo`; jeder sichtbare Text über `__()`/`esc_html_e()`/`naws_label()`. Laufzeit-Schlüssel `rd_*` stehen in `includes/class-naws-labels.php`. Platzhalter-Strings tragen `/* translators: … */` direkt davor.
- Jedes `echo` in Templates/Views escaped (`esc_html`/`esc_attr`/`esc_url`/`absint`).
- **Datenschutz:** Kein HTML, CSS oder JS des Radars nennt einen fremden Host. Der Browser lädt nur `uploads/naws-radar/…` und `wp-json/naws/v1/radar/…` der eigenen Site.
- **Externe Abrufe nur, wenn `naws_settings['radar_enabled']` = 1** (Vorgabe 0). Ohne Schalter: keine Abrufe, keine Ausgabe (Admins sehen einen Hinweis).
- Dateizugriffe nur über `WP_Filesystem` (`$wp_filesystem->put_contents/get_contents/exists/delete/dirlist`) und `wp_mkdir_p()`; kein `file_put_contents`/`unlink`/`rmdir` (Plugin Check).
- Server-Abrufe: `wp_remote_get( $url, [ 'timeout' => 15, 'user-agent' => 'XTX-Integration-for-Netatmo/<NAWS_VERSION> (+<home_url>)' ] )`.
- Zahlen in URLs mit `sprintf( '%.2F' … )` (nie Gebietsschema-abhängig).
- Grenzen: `height` 200–1200 (Vorgabe 420), `km` DWD 50–600 / RainViewer 150–1500 (Vorgabe 150), `width` `^\d{1,4}(px|%)?$` sonst `100%`, `source` `auto|dwd|rainviewer`.
- Zeiten: Erneuerung wenn Manifest älter als 900 s; „nicht verfügbar“ wenn älter als 10 800 s; vorheriger Satz bleibt 1 800 s; unbenutzter Satz wird nach 7 Tagen gelöscht; Sperre 300 s.
- Farbvorgaben: `radar_station` `#c0392b`, `radar_time_bg` `#2d5252`, `radar_time_text` `#ffffff`, `radar_forecast` `#dd9933`, `radar_bar_bg` `#f4fafa`, `radar_button` `#427272`, `radar_button_text` `#ffffff`.
- Knöpfe (Franks Regel 10.09.): eigene `:hover`/`:focus`-Regeln mit **zwei Klassen** im Selektor, Farben aus `--naws-radar-*`.
- Neue PHP-Dateien beginnen mit `if ( ! defined( 'ABSPATH' ) ) exit;`; Templates mit den zwei `phpcs:disable`-Zeilen für Variablennamen.
- Version bleibt 2.1.2 in diesem Plan.

## Definition of Done — gilt für jeden Task

1. `vendor/bin/phpcs --report=full <angefasste PHP-Dateien>` ohne Befund (Git Bash, Repo-Wurzel).
2. Ein `phpcs:ignore` nur mit Begründung im selben Kommentar.
3. Ganze Suite grün: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done` — keine Ausgabe.
4. `php -l` auf jeder angefassten PHP-Datei, `node --check` auf jeder angefassten JS-Datei.

## Review Focus

1. **Deutsches Gebietsschema auf dem Server** (`LC_NUMERIC=de_DE`): ein Komma in der WMS-`bbox` liefert leere Bilder. Erwartung: nur Punkte. Test in Task 2.
2. **DWD liefert für eine Zeit kein Bild** (HTTP 200 mit XML-`ServiceException`, z. B. Vorschau noch nicht gerechnet): Erwartung: Bild wird übersprungen, keine Sperrfrist, Animation läuft mit den übrigen. Test in Task 4.
3. **Abbruch mitten im Lauf** (429 nach 3 Bildern): Erwartung: Sperrfrist, bisheriges Manifest bleibt nutzbar, keine verwaisten Dateien außerhalb von Manifest/`retired`. Test in Task 4.
4. **Zwei Radare mit verschiedenem `km` auf einer Seite**: Erwartung: zwei getrennte Bildsätze, zwei Player, keine doppelten `id`. Tests in Task 3 (Schlüssel) und Task 6 (kein `id=` im Markup).
5. **Station nahe der Datumsgrenze / hoher Norden** (Tromsø): Erwartung: Kacheln mit gültigen x-Nummern 0…2^z−1, Box quadratisch. Test in Task 1.

## Dateien

| Datei | Verantwortung |
|---|---|
| `includes/class-naws-radar-geo.php` (neu) | Box, Zoomstufe, Kacheln, `clamp_km()` |
| `includes/class-naws-radar-source.php` (neu) | Deutschland-Umriss, `pick()`, DWD-Zeiten/URLs, RainViewer-Index, Kachel-URLs |
| `includes/class-naws-radar-store.php` (neu) | Pfade/URLs, Schlüssel, Manifest, Sätze-Option, Sperre, Sperrfrist, `prune()`, `cleanup()`, `clear_all()`, `usage()` |
| `includes/class-naws-radar.php` (neu) | `init()`, `enabled()`, `refresh()`, `maybe_schedule()`, `payload()`, `render()`, REST, Tagesaufräumen, Datenschutztext |
| `templates/radar.php` (neu) | Rahmen, Karte, Standbild „jetzt“, Leiste, Quellen, JSON |
| `assets/js/radar.js` (neu) | Player (`core` per Node testbar) |
| `uninstall.php` (neu) | nur Radar-Ordner + Radar-Optionen löschen |
| `tests/radar-stubs.php` (neu) | gemeinsame Stubs: Optionen, Transients, Uploads, In-Memory-`WP_Filesystem`, HTTP-Routen |
| `tests/test-radar-geo.php`, `test-radar-source.php`, `test-radar-store.php`, `test-radar-refresh.php`, `test-radar-player.php`, `test-radar-render.php`, `test-radar-colors.php` (neu) | Tests |
| `xtx-integration-for-netatmo.php` | vier `naws_require()`-Zeilen, `NAWS_Radar::init()` |
| `includes/class-naws-shortcodes.php` | `TAGS`, Skript-Registrierung, `sc_radar()` |
| `includes/class-naws-colors.php` | 7 Schlüssel, `RADAR_KEYS`, Gruppe, Reiter, CSS-Block `.naws-radar` |
| `includes/class-naws-labels.php` | `rd_*` |
| `includes/class-naws-cron.php` | Tagesaufräumen, `deactivate()` |
| `includes/class-naws-admin.php` | `radar_enabled` sanitieren, Strings für den Leeren-Knopf |
| `includes/class-naws-ajax.php` | `wp_ajax_naws_radar_clear` |
| `admin/views/settings.php` | Block „Regenradar“ |
| `admin/views/appearance.php` | Reiter „Regenradar“ mit Vorschau |
| `admin/views/shortcodes.php` | Karte `[naws_radar]` |
| `assets/css/frontend.css`, `assets/css/admin.css`, `assets/js/admin.js` | Stil, Vorschau, Knopf |
| `tests/test-asset-version.php`, `test-shortcode-atts.php`, `test-appearance.php`, `test-settings-merge.php`, `test-readme-sections.php`, `test-admin-js.php` | erweitert |
| `readme.txt`, `README.md`, `CHANGELOG.md`, `docs/site/website.{de,en}.json`, `docs/i18n/catalog/*.po`, `languages/*.mo` | Doku, Kataloge |

---

### Task 0: Spec-Nachtrag

- [ ] **Step 1:** `cd "C:/Users/xyla1/Documents/GitHub/Netatmo" && git branch --show-current` — erwartet `regenradar`.
- [ ] **Step 2:** An das Ende von `docs/superpowers/specs/2026-10-13-regenradar-design.md` anhängen:

```markdown

## 10. Nachtrag beim Planen (13.10.2026)

- Die Einstellungsseite hat keine Reiter: Schalter, Liste der Bildsätze und „Zwischenspeicher leeren“ stehen als Block „Regenradar“ auf der Einstellungsseite; die Farben als Reiter „Regenradar“ im Erscheinungsbild.
- DWD-Vergangenheitsbilder werden wiederverwendet (ein gemessenes Bild ändert sich nicht): pro Erneuerung 1 neues Vergangenheitsbild + 8 Vorschau-Bilder.
- Karte bei 150 km: Zoom 10, ~49 Kacheln einmalig; bei 50 km Zoom 11, 25 Kacheln.
- RainViewer-Bilder nehmen das 10-min-Raster der Quelle (bis 13 Bilder in 2 h).
```

- [ ] **Step 3:** `git add docs/superpowers/specs/2026-10-13-regenradar-design.md docs/superpowers/plans/2026-10-13-regenradar.md && git commit -m "Docs: rain radar plan and spec addendum"`

---

### Task 1: `NAWS_Radar_Geo`

**Files:**
- Create: `includes/class-naws-radar-geo.php`
- Modify: `xtx-integration-for-netatmo.php` (nach der Zeile `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-sparkline.php' );`)
- Test: `tests/test-radar-geo.php`

**Interfaces:**
- Produces: `NAWS_Radar_Geo::SIZE` = 1024, `::RV_MAX_ZOOM` = 7, `::KM_DEFAULT` = 150; `clamp_km( $km, string $source ): int`; `box( float $lat, float $lon, int $km ): array` (`[minx, miny, maxx, maxy]` in Metern, EPSG:3857); `zoom_for( array $box, int $max = 12 ): int`; `tiles( array $box, int $z ): array` mit Einträgen `[ 'z'=>int, 'x'=>int (0…2^z−1), 'y'=>int, 'left'=>float, 'top'=>float, 'size'=>float ]` in Bildpixeln (0…1024).

- [ ] **Step 1: Test schreiben** — `tests/test-radar-geo.php`:

```php
<?php
/**
 * Tests fuer NAWS_Radar_Geo: Ausschnitt, Zoomstufe und Kacheln des
 * Regenradars. Reine Rechnung, keine Abrufe.
 *
 * Die Sollwerte stammen aus einer unabhaengigen Rechnung (Node, 13.10.2026).
 *
 *   php tests/test-radar-geo.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-geo.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function covers( array $tiles ): bool {
    $minl = min( array_column( $tiles, 'left' ) );
    $mint = min( array_column( $tiles, 'top' ) );
    $maxr = max( array_map( fn( $t ) => $t['left'] + $t['size'], $tiles ) );
    $maxb = max( array_map( fn( $t ) => $t['top'] + $t['size'], $tiles ) );
    return $minl <= 0 && $mint <= 0 && $maxr >= 1024 && $maxb >= 1024;
}

echo "\nclamp_km()\n" . str_repeat( '-', 74 ) . "\n";
check( 'DWD: 150 bleibt',              NAWS_Radar_Geo::clamp_km( '150', 'dwd' ), 150 );
check( 'DWD: 10 wird 50',              NAWS_Radar_Geo::clamp_km( '10', 'dwd' ), 50 );
check( 'DWD: 5000 wird 600',           NAWS_Radar_Geo::clamp_km( '5000', 'dwd' ), 600 );
check( 'RainViewer: 50 wird 150',      NAWS_Radar_Geo::clamp_km( '50', 'rainviewer' ), 150 );
check( 'RainViewer: 5000 wird 1500',   NAWS_Radar_Geo::clamp_km( '5000', 'rainviewer' ), 1500 );
check( 'Unsinn wird Vorgabe 150',      NAWS_Radar_Geo::clamp_km( 'abc', 'dwd' ), 150 );
check( 'Kommazahl wird gerundet',      NAWS_Radar_Geo::clamp_km( '99.6', 'dwd' ), 100 );

echo "\nbox() und zoom_for(): Leipzig, 150 km\n" . str_repeat( '-', 74 ) . "\n";
$b = NAWS_Radar_Geo::box( 51.35, 12.37, 150 );
check( 'minx',                         (int) round( $b[0] ), 1256938 );
check( 'miny',                         (int) round( $b[1] ), 6563355 );
check( 'maxx',                         (int) round( $b[2] ), 1497106 );
check( 'maxy',                         (int) round( $b[3] ), 6803524 );
check( 'Box ist quadratisch',          abs( ( $b[2] - $b[0] ) - ( $b[3] - $b[1] ) ) < 0.001, true );
check( 'Zoomstufe 10',                 NAWS_Radar_Geo::zoom_for( $b ), 10 );

$t = NAWS_Radar_Geo::tiles( $b, 10 );
check( '49 Kacheln',                   count( $t ), 49 );
check( 'x 544 bis 550',                [ min( array_column( $t, 'x' ) ), max( array_column( $t, 'x' ) ) ], [ 544, 550 ] );
check( 'y 338 bis 344',                [ min( array_column( $t, 'y' ) ), max( array_column( $t, 'y' ) ) ], [ 338, 344 ] );
check( 'erste Kachel links/oben',      [ $t[0]['left'], $t[0]['top'] ], [ -19.59, -26.0 ] );
check( 'Kachelgroesse',                $t[0]['size'], 166.86 );
check( 'Leipzig liegt in Kachel 547/341', count( array_filter( $t, fn( $q ) => 547 === $q['x'] && 341 === $q['y'] ) ), 1 );
check( 'Kacheln decken das Bild ganz', covers( $t ), true );

echo "\nKleiner Ausschnitt, hoher Norden, Datumsgrenze, RainViewer\n" . str_repeat( '-', 74 ) . "\n";
$b50 = NAWS_Radar_Geo::box( 51.35, 12.37, 50 );
check( '50 km: Zoomstufe 11',          NAWS_Radar_Geo::zoom_for( $b50 ), 11 );
check( '50 km: 25 Kacheln',            count( NAWS_Radar_Geo::tiles( $b50, 11 ) ), 25 );

$bt = NAWS_Radar_Geo::box( 69.65, 18.96, 150 );
check( 'Tromsoe: Zoomstufe 9',         NAWS_Radar_Geo::zoom_for( $bt ), 9 );
$tt = NAWS_Radar_Geo::tiles( $bt, 9 );
check( 'Tromsoe: 36 Kacheln',          count( $tt ), 36 );
check( 'Tromsoe: deckt das Bild',      covers( $tt ), true );

$bd = NAWS_Radar_Geo::box( 0.0, 179.9, 600 );
$td = NAWS_Radar_Geo::tiles( $bd, NAWS_Radar_Geo::zoom_for( $bd ) );
$xs = array_values( array_unique( array_column( $td, 'x' ) ) );
check( 'Datumsgrenze: x laeuft 508..511, 0..3', $xs, [ 508, 509, 510, 511, 0, 1, 2, 3 ] );
check( 'Datumsgrenze: deckt das Bild', covers( $td ), true );

$rv = NAWS_Radar_Geo::tiles( $b, min( NAWS_Radar_Geo::zoom_for( $b ), NAWS_Radar_Geo::RV_MAX_ZOOM ) );
check( 'RainViewer Leipzig: Zoom 7, 2 Kacheln', [ $rv[0]['z'], count( $rv ) ], [ 7, 2 ] );
check( 'RainViewer: Kachel 68/42 zuerst', [ $rv[0]['x'], $rv[0]['y'] ], [ 68, 42 ] );
check( 'RainViewer: deckt das Bild',   covers( $rv ), true );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2:** `php tests/test-radar-geo.php` — erwartet: Fatal error, Datei `class-naws-radar-geo.php` fehlt.
- [ ] **Step 3: Implementieren** — `includes/class-naws-radar-geo.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Geometry of the rain radar: a square box around the station in Web
 * Mercator (EPSG:3857), the map zoom level and the tiles that cover it.
 * Pure arithmetic, no requests and no WordPress.
 *
 * Every position is in the SIZE x SIZE pixel space of the radar image;
 * the station sits in its centre.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar_Geo {

    const SIZE        = 1024;
    const EARTH       = 6378137.0;
    const MAX_ZOOM    = 12;
    const RV_MAX_ZOOM = 7;
    const KM_DEFAULT  = 150;

    /** Width of the box in km, per source: [ min, max ]. */
    const KM_LIMITS = [
        'dwd'        => [ 50, 600 ],
        'rainviewer' => [ 150, 1500 ],
    ];

    /**
     * The km attribute as a whole number within the limits of the source.
     * RainViewer stops at zoom 7, so a small box there would be a blur.
     */
    public static function clamp_km( $km, string $source ): int {
        [ $min, $max ] = self::KM_LIMITS[ $source ] ?? self::KM_LIMITS['rainviewer'];
        $km            = is_numeric( $km ) ? (int) round( (float) $km ) : self::KM_DEFAULT;
        return max( $min, min( $max, $km ) );
    }

    /**
     * Square box of $km on the ground, centred on the station. Mercator
     * stretches by 1/cos(lat), so the box does too.
     *
     * @return float[] [ minx, miny, maxx, maxy ] in metres.
     */
    public static function box( float $lat, float $lon, int $km ): array {
        $lat  = max( -85.0, min( 85.0, $lat ) );
        $half = $km * 500 / cos( deg2rad( $lat ) );
        $mx   = deg2rad( $lon ) * self::EARTH;
        $my   = self::EARTH * log( tan( M_PI / 4 + deg2rad( $lat ) / 2 ) );
        return [ $mx - $half, $my - $half, $mx + $half, $my + $half ];
    }

    /** Smallest zoom whose tiles give the box at least SIZE pixels. */
    public static function zoom_for( array $box, int $max = self::MAX_ZOOM ): int {
        $world = 2 * M_PI * self::EARTH;
        for ( $z = 0; $z < $max; $z++ ) {
            if ( ( $box[2] - $box[0] ) / ( $world / ( 256 * ( 2 ** $z ) ) ) >= self::SIZE ) {
                return $z;
            }
        }
        return $max;
    }

    /**
     * The tiles of zoom $z that cover the box, each with its place in the
     * SIZE x SIZE image. x wraps at the date line, left keeps counting.
     *
     * @return array<int, array{z:int, x:int, y:int, left:float, top:float, size:float}>
     */
    public static function tiles( array $box, int $z ): array {
        $world = 2 * M_PI * self::EARTH;
        $n     = 2 ** $z;
        $tm    = $world / $n;
        $scale = self::SIZE / ( $box[2] - $box[0] );
        $x0    = (int) floor( ( $box[0] + $world / 2 ) / $tm );
        $x1    = (int) floor( ( $box[2] + $world / 2 - 1e-6 ) / $tm );
        $y0    = max( 0, (int) floor( ( $world / 2 - $box[3] ) / $tm ) );
        $y1    = min( $n - 1, (int) floor( ( $world / 2 - $box[1] - 1e-6 ) / $tm ) );
        $out   = [];
        for ( $y = $y0; $y <= $y1; $y++ ) {
            for ( $x = $x0; $x <= $x1; $x++ ) {
                $out[] = [
                    'z'    => $z,
                    'x'    => ( ( $x % $n ) + $n ) % $n,
                    'y'    => $y,
                    'left' => round( ( ( $x * $tm - $world / 2 ) - $box[0] ) * $scale, 2 ),
                    'top'  => round( ( $box[3] - ( $world / 2 - $y * $tm ) ) * $scale, 2 ),
                    'size' => round( $tm * $scale, 2 ),
                ];
            }
        }
        return $out;
    }
}
```

- [ ] **Step 4:** In `xtx-integration-for-netatmo.php` nach `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-sparkline.php' );` einfügen: `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-radar-geo.php' );` (gleiche Einrückung).
- [ ] **Step 5:** `php tests/test-radar-geo.php` — erwartet „… bestanden, 0 fehlgeschlagen“; `php tests/test-main-requires.php` grün.
- [ ] **Step 6:** DoD, dann `git add includes/class-naws-radar-geo.php tests/test-radar-geo.php xtx-integration-for-netatmo.php && git commit -m "Radar: geometry — box, zoom and tiles around the station"`

---

### Task 2: `NAWS_Radar_Source`

**Files:**
- Create: `includes/class-naws-radar-source.php`
- Modify: `xtx-integration-for-netatmo.php` (nach `class-naws-radar-geo.php`)
- Test: `tests/test-radar-source.php`

**Interfaces:**
- Consumes: `NAWS_Radar_Geo::SIZE`.
- Produces: `NAWS_Radar_Source::STEP` = 900, `::PAST` = 4, `::AHEAD` = 8, `::RV_WINDOW` = 7200, `::RV_MAX` = 13; `in_germany( float $lat, float $lon ): bool`; `pick( string $wanted, float $lat, float $lon ): string` (`'dwd'|'rainviewer'`); `dwd_times( int $now ): array` (13 × `[ 't'=>int, 'forecast'=>bool ]`, `forecast` false für die ersten 5); `dwd_url( array $box, int $t, bool $forecast ): string`; `rv_host( array $index ): ?string`; `rv_frames( array $index, int $now ): array` (`[ 't'=>int, 'path'=>string ]`, aufsteigend, höchstens 13); `rv_tile_url( string $host, string $path, int $z, int $x, int $y ): string`; `osm_tile_url( int $z, int $x, int $y ): string`.

- [ ] **Step 1: Test schreiben** — `tests/test-radar-source.php`:

```php
<?php
/**
 * Tests fuer NAWS_Radar_Source: Deutschland oder nicht, die Zeiten der
 * DWD-Animation, die WMS-Adresse und die Auswahl aus dem RainViewer-Index.
 *
 *   php tests/test-radar-source.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-geo.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-source.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

echo "\nin_germany() und pick()\n" . str_repeat( '-', 74 ) . "\n";
$in  = [ 'Leipzig' => [ 51.35, 12.37 ], 'Flensburg' => [ 54.79, 9.43 ], 'Aachen' => [ 50.78, 6.08 ], 'Goerlitz' => [ 51.15, 14.99 ],
         'Konstanz' => [ 47.66, 9.17 ], 'Passau' => [ 48.57, 13.43 ], 'Saarbruecken' => [ 49.24, 6.99 ], 'Stralsund' => [ 54.31, 13.09 ] ];
$out = [ 'Zuerich' => [ 47.37, 8.54 ], 'Basel' => [ 47.56, 7.59 ], 'Strassburg' => [ 48.58, 7.75 ], 'Prag' => [ 50.08, 14.42 ],
         'Salzburg' => [ 47.80, 13.04 ], 'Amsterdam' => [ 52.37, 4.90 ], 'Kopenhagen' => [ 55.68, 12.57 ], 'Oslo' => [ 59.91, 10.75 ] ];
foreach ( $in as $name => [ $lat, $lon ] )  { check( "$name liegt in Deutschland", NAWS_Radar_Source::in_germany( $lat, $lon ), true ); }
foreach ( $out as $name => [ $lat, $lon ] ) { check( "$name liegt nicht in Deutschland", NAWS_Radar_Source::in_germany( $lat, $lon ), false ); }
check( 'auto in Leipzig: DWD',            NAWS_Radar_Source::pick( 'auto', 51.35, 12.37 ), 'dwd' );
check( 'auto in Oslo: RainViewer',        NAWS_Radar_Source::pick( 'auto', 59.91, 10.75 ), 'rainviewer' );
check( 'dwd in Oslo faellt zurueck',      NAWS_Radar_Source::pick( 'dwd', 59.91, 10.75 ), 'rainviewer' );
check( 'rainviewer in Leipzig erzwungen', NAWS_Radar_Source::pick( 'rainviewer', 51.35, 12.37 ), 'rainviewer' );
check( 'Unsinn gilt als auto',            NAWS_Radar_Source::pick( 'xyz', 51.35, 12.37 ), 'dwd' );

echo "\ndwd_times()\n" . str_repeat( '-', 74 ) . "\n";
$now   = gmmktime( 15, 26, 0, 10, 13, 2026 );       // 15:26 UTC
$times = NAWS_Radar_Source::dwd_times( $now );
check( '13 Bilder',                       count( $times ), 13 );
check( 'erstes Bild 14:15 UTC',           gmdate( 'H:i', $times[0]['t'] ), '14:15' );
check( 'Bild "jetzt" 15:15 UTC',          gmdate( 'H:i', $times[4]['t'] ), '15:15' );
check( 'letztes Bild 17:15 UTC',          gmdate( 'H:i', $times[12]['t'] ), '17:15' );
check( '5 gemessen, 8 Vorschau',          array_count_values( array_map( fn( $t ) => $t['forecast'] ? 'f' : 'p', $times ) ), [ 'p' => 5, 'f' => 8 ] );
check( 'um 15:24 ist "jetzt" noch 15:00', gmdate( 'H:i', NAWS_Radar_Source::dwd_times( $now - 120 )[4]['t'] ), '15:00' );

echo "\ndwd_url()\n" . str_repeat( '-', 74 ) . "\n";
$box = NAWS_Radar_Geo::box( 51.35, 12.37, 150 );
$u   = NAWS_Radar_Source::dwd_url( $box, $times[4]['t'], false );
check( 'Host ist maps.dwd.de',            strpos( $u, 'https://maps.dwd.de/geoserver/dwd/wms?' ), 0 );
check( 'Ebene Niederschlagsradar',        str_contains( $u, 'layers=dwd%3ANiederschlagsradar' ), true );
check( 'Vorschau-Ebene',                  str_contains( NAWS_Radar_Source::dwd_url( $box, $times[5]['t'], true ), 'layers=dwd%3ARadar_wn-product_1x1km_ger' ), true );
check( 'Zeit im ISO-Format',              str_contains( $u, 'time=2026-10-13T15%3A15%3A00Z' ), true );
check( '1024 x 1024 px',                  str_contains( $u, 'width=1024&height=1024' ), true );
check( 'Web-Mercator',                    str_contains( $u, 'srs=EPSG%3A3857' ), true );
setlocale( LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'deu' );
$ul = NAWS_Radar_Source::dwd_url( $box, $times[4]['t'], false );
setlocale( LC_NUMERIC, 'C' );
preg_match( '/bbox=([^&]+)/', $ul, $m );
check( 'bbox auch unter de_DE mit Punkten', rawurldecode( $m[1] ?? '' ), '1256937.82,6563355.48,1497106.38,6803524.03' );

echo "\nRainViewer\n" . str_repeat( '-', 74 ) . "\n";
$now   = 1791904826;
$past  = [];
for ( $i = 14; $i >= 0; $i-- ) { $past[] = [ 'time' => $now - $i * 600, 'path' => '/v2/radar/abc' . $i ]; }
$past[] = [ 'time' => $now - 9000, 'path' => '/v2/radar/zualt' ];
$past[] = [ 'time' => $now - 1200, 'path' => '/v2/radar/../../etc' ];
$index = [ 'host' => 'https://tilecache.rainviewer.com', 'radar' => [ 'past' => $past ] ];
check( 'Host wird angenommen',            NAWS_Radar_Source::rv_host( $index ), 'https://tilecache.rainviewer.com' );
check( 'fremder Host wird verworfen',     NAWS_Radar_Source::rv_host( [ 'host' => 'https://evil.example' ] ), null );
check( 'http wird verworfen',             NAWS_Radar_Source::rv_host( [ 'host' => 'http://tilecache.rainviewer.com' ] ), null );
$fr = NAWS_Radar_Source::rv_frames( $index, $now );
check( 'hoechstens 13 Bilder',            count( $fr ), 13 );
check( 'aufsteigend',                     $fr[0]['t'] < $fr[12]['t'], true );
check( 'juengstes zuletzt',               $fr[12]['path'], '/v2/radar/abc0' );
check( 'zu alte und kaputte Pfade fehlen', array_filter( $fr, fn( $f ) => in_array( $f['path'], [ '/v2/radar/zualt', '/v2/radar/../../etc' ], true ) ), [] );
check( 'leerer Index: keine Bilder',      NAWS_Radar_Source::rv_frames( [], $now ), [] );
check( 'Kachel-URL',                      NAWS_Radar_Source::rv_tile_url( 'https://tilecache.rainviewer.com', '/v2/radar/abc0', 7, 68, 42 ), 'https://tilecache.rainviewer.com/v2/radar/abc0/256/7/68/42/2/1_1.png' );
check( 'OSM-Kachel-URL',                  NAWS_Radar_Source::osm_tile_url( 10, 547, 341 ), 'https://tile.openstreetmap.org/10/547/341.png' );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2:** `php tests/test-radar-source.php` — erwartet: Fatal error, Klasse fehlt.
- [ ] **Step 3: Implementieren** — `includes/class-naws-radar-source.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Where the rain radar comes from: the Deutscher Wetterdienst inside
 * Germany (measured plus a two-hour nowcast), RainViewer everywhere else
 * (measured only). Knows the times of an animation and the addresses of
 * every image. Requests are made by NAWS_Radar, not here.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar_Source {

    const DWD_WMS   = 'https://maps.dwd.de/geoserver/dwd/wms';
    const DWD_PAST  = 'dwd:Niederschlagsradar';
    const DWD_AHEAD = 'dwd:Radar_wn-product_1x1km_ger';
    const RV_INDEX  = 'https://api.rainviewer.com/public/weather-maps.json';
    const OSM_TILES = 'https://tile.openstreetmap.org/';

    const STEP      = 900;   // 15 minutes between DWD frames
    const PAST      = 4;     // frames before "now"
    const AHEAD     = 8;     // nowcast frames after "now" (2 hours)
    const DWD_DELAY = 600;   // a composite is ready about 10 minutes late
    const RV_WINDOW = 7200;  // RainViewer keeps two hours
    const RV_MAX    = 13;

    /**
     * Germany, coarsely: [ lon, lat ] clockwise from the Dutch border. The
     * DWD composite reaches beyond the border, so a few kilometres of
     * error either way do no harm.
     */
    const GERMANY = [
        [ 5.87, 51.05 ], [ 6.23, 51.36 ], [ 5.95, 51.80 ], [ 6.83, 51.97 ], [ 7.06, 52.24 ], [ 6.73, 52.48 ],
        [ 7.05, 52.65 ], [ 7.21, 53.24 ], [ 6.90, 53.70 ], [ 8.00, 54.05 ], [ 8.20, 54.95 ], [ 8.40, 55.06 ],
        [ 9.90, 54.80 ], [ 10.90, 54.60 ], [ 13.00, 54.80 ], [ 14.30, 54.20 ], [ 14.40, 53.30 ], [ 14.15, 52.90 ],
        [ 14.60, 52.60 ], [ 14.75, 52.00 ], [ 15.05, 51.30 ], [ 15.05, 51.00 ], [ 14.80, 50.85 ], [ 14.30, 50.90 ],
        [ 13.40, 50.60 ], [ 12.50, 50.40 ], [ 12.10, 50.30 ], [ 12.45, 49.75 ], [ 13.00, 49.30 ], [ 13.85, 48.80 ],
        [ 13.50, 48.55 ], [ 13.00, 48.25 ], [ 12.85, 47.90 ], [ 13.05, 47.50 ], [ 12.20, 47.60 ], [ 11.20, 47.40 ],
        [ 10.50, 47.30 ], [ 10.00, 47.50 ], [ 9.55, 47.55 ], [ 8.60, 47.65 ], [ 7.60, 47.58 ], [ 7.55, 47.75 ],
        [ 7.60, 48.10 ], [ 7.80, 48.60 ], [ 8.20, 48.97 ], [ 7.10, 49.15 ], [ 6.40, 49.46 ], [ 6.10, 50.10 ],
        [ 6.40, 50.30 ], [ 6.00, 50.75 ],
    ];

    /** Ray casting against GERMANY. */
    public static function in_germany( float $lat, float $lon ): bool {
        $inside = false;
        $p      = self::GERMANY;
        $n      = count( $p );
        for ( $i = 0, $j = $n - 1; $i < $n; $j = $i++ ) {
            [ $xi, $yi ] = $p[ $i ];
            [ $xj, $yj ] = $p[ $j ];
            if ( ( $yi > $lat ) !== ( $yj > $lat ) && $lon < ( $xj - $xi ) * ( $lat - $yi ) / ( $yj - $yi ) + $xi ) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }

    /** 'dwd' or 'rainviewer'. DWD only where it has data. */
    public static function pick( string $wanted, float $lat, float $lon ): string {
        if ( 'rainviewer' === $wanted ) {
            return 'rainviewer';
        }
        return self::in_germany( $lat, $lon ) ? 'dwd' : 'rainviewer';
    }

    /**
     * Thirteen frame times on the quarter hour: four measured, "now" (the
     * newest composite that should be ready), eight nowcast frames.
     *
     * @return array<int, array{t:int, forecast:bool}>
     */
    public static function dwd_times( int $now ): array {
        $base = (int) ( floor( ( $now - self::DWD_DELAY ) / self::STEP ) * self::STEP );
        $out  = [];
        for ( $i = -self::PAST; $i <= self::AHEAD; $i++ ) {
            $out[] = [
                't'        => $base + $i * self::STEP,
                'forecast' => $i > 0,
            ];
        }
        return $out;
    }

    /** WMS GetMap for exactly the box, transparent PNG. */
    public static function dwd_url( array $box, int $t, bool $forecast ): string {
        $args = [
            'service'     => 'WMS',
            'version'     => '1.1.1',
            'request'     => 'GetMap',
            'layers'      => $forecast ? self::DWD_AHEAD : self::DWD_PAST,
            'styles'      => '',
            'srs'         => 'EPSG:3857',
            'bbox'        => implode( ',', array_map( static fn( $v ) => sprintf( '%.2F', $v ), $box ) ),
            'width'       => NAWS_Radar_Geo::SIZE,
            'height'      => NAWS_Radar_Geo::SIZE,
            'format'      => 'image/png',
            'transparent' => 'true',
            'time'        => gmdate( 'Y-m-d\TH:i:s\Z', $t ),
        ];
        return self::DWD_WMS . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
    }

    /** The tile host named in the index, only if it is RainViewer's over https. */
    public static function rv_host( array $index ): ?string {
        $host = isset( $index['host'] ) && is_string( $index['host'] ) ? $index['host'] : '';
        return preg_match( '#^https://[a-z0-9.-]+\.rainviewer\.com$#', $host ) ? $host : null;
    }

    /**
     * The past frames of the index within the last two hours, oldest
     * first, at most RV_MAX. Paths that do not look like RainViewer's are
     * dropped, so nothing from the index can steer a request elsewhere.
     *
     * @return array<int, array{t:int, path:string}>
     */
    public static function rv_frames( array $index, int $now ): array {
        $past = $index['radar']['past'] ?? [];
        if ( ! is_array( $past ) ) {
            return [];
        }
        $out = [];
        foreach ( $past as $f ) {
            $t    = (int) ( $f['time'] ?? 0 );
            $path = (string) ( $f['path'] ?? '' );
            if ( $t < $now - self::RV_WINDOW - 300 || $t > $now + 300 || ! preg_match( '#^/v2/radar/[A-Za-z0-9]+$#', $path ) ) {
                continue;
            }
            $out[] = [
                't'    => $t,
                'path' => $path,
            ];
        }
        usort( $out, static fn( $a, $b ) => $a['t'] <=> $b['t'] );
        return array_slice( $out, -self::RV_MAX );
    }

    /** Colour scheme 2 ("Universal Blue", the only one left), smoothed, with snow. */
    public static function rv_tile_url( string $host, string $path, int $z, int $x, int $y ): string {
        return "{$host}{$path}/256/{$z}/{$x}/{$y}/2/1_1.png";
    }

    public static function osm_tile_url( int $z, int $x, int $y ): string {
        return self::OSM_TILES . "{$z}/{$x}/{$y}.png";
    }
}
```

- [ ] **Step 4:** `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-radar-source.php' );` nach der Geo-Zeile in `xtx-integration-for-netatmo.php`.
- [ ] **Step 5:** `php tests/test-radar-source.php` grün. Falls die bbox-Prüfung unter Windows `setlocale` nicht greift, bleibt der Test trotzdem gültig (er prüft dann nur das C-Gebietsschema) — nicht abschwächen.
- [ ] **Step 6:** DoD, `git add includes/class-naws-radar-source.php tests/test-radar-source.php xtx-integration-for-netatmo.php && git commit -m "Radar: sources — Germany outline, DWD frame times and WMS URL, RainViewer index"`

---

### Task 3: `NAWS_Radar_Store` und gemeinsame Test-Stubs

**Files:**
- Create: `tests/radar-stubs.php`, `includes/class-naws-radar-store.php`
- Modify: `xtx-integration-for-netatmo.php` (nach `class-naws-radar-source.php`)
- Test: `tests/test-radar-store.php`

**Interfaces:**
- Consumes: `NAWS_Forecast::backoff_seconds( int $code, string $retry_after ): int` (public, bestehend), `NAWS_Logger::warning( $ctx, $msg )`.
- Produces: `NAWS_Radar_Store::OPTION` = `'naws_radar_sets'`, `::GRACE` = 1800, `::UNUSED` = 604800; `key( float $lat, float $lon, int $km, string $source ): string` (12 Hex-Zeichen); `dir( string $key ): string`; `url( string $key, string $rel ): string`; `sets(): array` (`key => [ 'lat','lon','km','source','last_seen' ]`); `remember( string $key, array $params, int $now ): void`; `manifest( string $key ): ?array`; `save_manifest( string $key, array $m ): bool`; `put( string $key, string $rel, string $bytes ): bool`; `has( string $key, string $rel ): bool`; `files_of( array $m ): array`; `prune( string $key, ?array $old, array $new, int $now ): array` (neue `retired`-Liste `file => seit`); `delete_set( string $key ): void`; `cleanup( int $now ): int` (Zahl gelöschter Sätze); `clear_all(): void`; `usage( string $key ): int` (Bytes); `lock( string $key ): bool`; `unlock( string $key ): void`; `in_backoff( string $source ): bool`; `start_backoff( string $source, $response ): void`.
- Manifest-Form: `[ 'v'=>1, 'source'=>string, 'updated'=>int, 'map'=>[ 'tiles'=>[ [ 'file','left','top','size' ] … ] ], 'frames'=>[ [ 't'=>int, 'forecast'=>bool, 'file'=>string ] | [ 't', 'forecast', 'tiles'=>[ [ 'file','left','top','size' ] … ] ] … ], 'retired'=>[ file => int ] ]`. Dateinamen relativ zum Satz-Ordner: `map/{z}-{x}-{y}.png`, `frames/{t}.png` (DWD gemessen), `frames/f{t}-{base}.png` (DWD Vorschau), `frames/{t}-{z}-{x}-{y}.png` (RainViewer).

- [ ] **Step 1: Stubs schreiben** — `tests/radar-stubs.php`:

```php
<?php
/**
 * Gemeinsame Stubs fuer die Regenradar-Tests: Optionen, Transients,
 * Uploads, ein WP_Filesystem im Speicher (keine Datei auf der Platte),
 * HTTP-Antworten nach URL-Anfang und Zeitplan.
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ ); }
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' )   || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' )    || define( 'DAY_IN_SECONDS', 86400 );
defined( 'NAWS_VERSION' )      || define( 'NAWS_VERSION', 'test' );

$GLOBALS['opts']        = [ 'naws_settings' => [ 'radar_enabled' => 1 ], 'time_format' => 'H:i' ];
$GLOBALS['transients']  = [];
$GLOBALS['http_routes'] = [];
$GLOBALS['calls']       = [];
$GLOBALS['logged']      = [];
$GLOBALS['scheduled']   = [];
$GLOBALS['loc']         = [ 'lat' => 51.35, 'lon' => 12.37, 'name' => 'Leipzig' ];
$GLOBALS['is_admin']    = false;

function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ][0] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = [ $v, $ttl ]; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function wp_upload_dir( $time = null, $create = true ) { return [ 'basedir' => '/up', 'baseurl' => 'https://example.org/wp-content/uploads' ]; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_mkdir_p( $d ) { return true; }
function home_url( $p = '' ) { return 'https://example.org' . $p; }
function rest_url( $p = '' ) { return 'https://example.org/wp-json/' . ltrim( $p, '/' ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); }
function current_user_can( $cap ) { return $GLOBALS['is_admin']; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function absint( $v ) { return abs( (int) $v ); }
function admin_url( $p = '' ) { return 'https://example.org/wp-admin/' . $p; }
function wp_next_scheduled( $hook, $args = [] ) { return $GLOBALS['scheduled'][ $hook . json_encode( $args ) ] ?? false; }
function wp_schedule_single_event( $ts, $hook, $args = [] ) { $GLOBALS['scheduled'][ $hook . json_encode( $args ) ] = $ts; return true; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {
    public $code; public $msg; public $data;
    public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->msg = $m; $this->data = $d; }
    public function get_error_message() { return $this->msg; }
    public function get_error_data() { return $this->data; }
}
function wp_remote_get( $url, $args = [] ) {
    $GLOBALS['calls'][] = $url;
    foreach ( $GLOBALS['http_routes'] as $prefix => $resp ) {
        if ( 0 === strpos( $url, $prefix ) ) { return is_callable( $resp ) ? $resp( $url ) : $resp; }
    }
    return new WP_Error( 'none', 'no route for ' . $url );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? ( $r['headers'][ strtolower( $h ) ] ?? '' ) : ''; }
function resp( int $code, string $body, array $headers = [] ): array { return [ 'code' => $code, 'body' => $body, 'headers' => $headers ]; }
function png( string $tag = '' ): array { return resp( 200, "\x89PNG\r\n\x1a\n" . $tag, [ 'content-type' => 'image/png' ] ); }
function calls_to( string $prefix ): int { return count( array_filter( $GLOBALS['calls'], fn( $u ) => 0 === strpos( $u, $prefix ) ) ); }

class NAWS_Logger {
    public static function error( $c, $m, $d = [] ) { $GLOBALS['logged'][] = $m; }
    public static function warning( $c, $m, $d = [] ) { $GLOBALS['logged'][] = $m; }
    public static function info( $c, $m, $d = [] ) { $GLOBALS['logged'][] = $m; }
}
class NAWS_Forecast {
    public static function backoff_seconds( int $code, string $retry_after ): int {
        if ( 429 === $code ) { return is_numeric( $retry_after ) ? max( 60, min( 21600, (int) $retry_after ) ) : 900; }
        return 300;
    }
    public static function resolve_location(): array { return $GLOBALS['loc']; }
}

/** WP_Filesystem im Speicher: Pfad => Inhalt. Ordner gibt es nur implizit. */
class Fake_FS {
    public $files = [];
    public function put_contents( $f, $c, $mode = false ) { $this->files[ $f ] = (string) $c; return true; }
    public function get_contents( $f ) { return $this->files[ $f ] ?? false; }
    public function exists( $f ) {
        if ( isset( $this->files[ $f ] ) ) { return true; }
        $p = rtrim( $f, '/' ) . '/';
        foreach ( array_keys( $this->files ) as $k ) { if ( 0 === strpos( $k, $p ) ) { return true; } }
        return false;
    }
    public function delete( $f, $recursive = false, $type = false ) {
        unset( $this->files[ $f ] );
        if ( $recursive ) {
            $p = rtrim( $f, '/' ) . '/';
            foreach ( array_keys( $this->files ) as $k ) { if ( 0 === strpos( $k, $p ) ) { unset( $this->files[ $k ] ); } }
        }
        return true;
    }
    public function dirlist( $path, $hidden = true, $recursive = false ) {
        $p   = rtrim( $path, '/' ) . '/';
        $out = [];
        foreach ( $this->files as $k => $c ) {
            if ( 0 !== strpos( $k, $p ) ) { continue; }
            $parts = explode( '/', substr( $k, strlen( $p ) ) );
            $name  = array_shift( $parts );
            if ( ! $parts ) { $out[ $name ] = [ 'name' => $name, 'type' => 'f', 'size' => strlen( $c ) ]; continue; }
            $out[ $name ] = $out[ $name ] ?? [ 'name' => $name, 'type' => 'd', 'size' => 0, 'files' => [] ];
        }
        if ( $recursive ) {
            foreach ( $out as $name => $e ) { if ( 'd' === $e['type'] ) { $out[ $name ]['files'] = $this->dirlist( $p . $name, $hidden, true ); } }
        }
        return $out ?: false;
    }
}
$GLOBALS['wp_filesystem'] = new Fake_FS();
function fs(): Fake_FS { return $GLOBALS['wp_filesystem']; }

require_once __DIR__ . '/i18n-stubs.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function done(): void {
    global $passed, $failed;
    echo "\n" . str_repeat( '-', 74 ) . "\n";
    printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
    exit( $failed > 0 ? 1 : 0 );
}
```

Hinweis: `tests/radar-stubs.php` beginnt nicht mit `test-` und wird deshalb von der Suite-Schleife nicht allein ausgeführt.

- [ ] **Step 2: Test schreiben** — `tests/test-radar-store.php`:

```php
<?php
/**
 * Tests fuer NAWS_Radar_Store: Schluessel, Ablage, Manifest, Sperre,
 * Sperrfrist und das Loeschen alter Bilder (Frank, 13.10.2026: "alte Bilder
 * sollten auch wieder vom Server geloescht werden").
 *
 *   php tests/test-radar-store.php
 *
 * @package NAWS
 */
require_once __DIR__ . '/radar-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-radar-store.php';

echo "\nSchluessel, Pfade, Saetze\n" . str_repeat( '-', 74 ) . "\n";
$k1 = NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' );
$k2 = NAWS_Radar_Store::key( 51.35, 12.37, 100, 'dwd' );
check( 'Schluessel: 12 Hex-Zeichen',       (bool) preg_match( '/^[a-f0-9]{12}$/', $k1 ), true );
check( 'anderes km, anderer Satz',          $k1 !== $k2, true );
check( 'gleiche Eingabe, gleicher Schluessel', NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' ), $k1 );
check( 'Ordner',                            NAWS_Radar_Store::dir( $k1 ), '/up/naws-radar/' . $k1 );
check( 'URL',                               NAWS_Radar_Store::url( $k1, 'frames/1.png' ), 'https://example.org/wp-content/uploads/naws-radar/' . $k1 . '/frames/1.png' );

$now = 1791900000;
NAWS_Radar_Store::remember( $k1, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now );
check( 'Satz gemerkt',                      NAWS_Radar_Store::sets()[ $k1 ]['last_seen'] ?? null, $now );
NAWS_Radar_Store::remember( $k1, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now + 600 );
check( 'innerhalb einer Stunde nicht neu geschrieben', NAWS_Radar_Store::sets()[ $k1 ]['last_seen'], $now );
NAWS_Radar_Store::remember( $k1, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now + 3700 );
check( 'nach einer Stunde aufgefrischt',    NAWS_Radar_Store::sets()[ $k1 ]['last_seen'], $now + 3700 );

echo "\nAblage und Manifest\n" . str_repeat( '-', 74 ) . "\n";
check( 'noch kein Manifest',                NAWS_Radar_Store::manifest( $k1 ), null );
check( 'Bild ablegen',                      NAWS_Radar_Store::put( $k1, 'frames/1.png', 'PNG' ), true );
check( 'Bild ist da',                       NAWS_Radar_Store::has( $k1, 'frames/1.png' ), true );
$m = [ 'v' => 1, 'source' => 'dwd', 'updated' => $now, 'map' => [ 'tiles' => [ [ 'file' => 'map/10-1-2.png', 'left' => 0, 'top' => 0, 'size' => 10 ] ] ],
       'frames' => [ [ 't' => 1, 'forecast' => false, 'file' => 'frames/1.png' ], [ 't' => 2, 'forecast' => false, 'tiles' => [ [ 'file' => 'frames/2-7-1-1.png', 'left' => 0, 'top' => 0, 'size' => 1 ] ] ] ],
       'retired' => [] ];
check( 'Manifest schreiben',                NAWS_Radar_Store::save_manifest( $k1, $m ), true );
check( 'Manifest lesen',                    NAWS_Radar_Store::manifest( $k1 ), $m );
check( 'files_of: Bilder ohne Karte',       NAWS_Radar_Store::files_of( $m ), [ 'frames/1.png', 'frames/2-7-1-1.png' ] );
fs()->put_contents( NAWS_Radar_Store::dir( $k1 ) . '/manifest.json', '{kaputt' );
check( 'kaputtes Manifest gilt als keins',  NAWS_Radar_Store::manifest( $k1 ), null );

echo "\nprune(): voriger Satz bleibt 30 Minuten\n" . str_repeat( '-', 74 ) . "\n";
foreach ( [ 'frames/a.png', 'frames/b.png', 'frames/c.png' ] as $f ) { NAWS_Radar_Store::put( $k1, $f, 'x' ); }
$old = [ 'frames' => [ [ 't' => 1, 'file' => 'frames/a.png' ], [ 't' => 2, 'file' => 'frames/b.png' ] ], 'retired' => [] ];
$new = [ 'frames' => [ [ 't' => 2, 'file' => 'frames/b.png' ], [ 't' => 3, 'file' => 'frames/c.png' ] ] ];
$ret = NAWS_Radar_Store::prune( $k1, $old, $new, $now );
check( 'a ist ausgemustert, nicht geloescht', [ $ret, NAWS_Radar_Store::has( $k1, 'frames/a.png' ) ], [ [ 'frames/a.png' => $now ], true ] );
$ret = NAWS_Radar_Store::prune( $k1, $new + [ 'retired' => $ret ], $new, $now + 900 );
check( 'nach 15 min noch da',               NAWS_Radar_Store::has( $k1, 'frames/a.png' ), true );
$ret = NAWS_Radar_Store::prune( $k1, $new + [ 'retired' => $ret ], $new, $now + 1800 );
check( 'nach 30 min geloescht',             [ $ret, NAWS_Radar_Store::has( $k1, 'frames/a.png' ) ], [ [], false ] );
check( 'b und c bleiben',                   [ NAWS_Radar_Store::has( $k1, 'frames/b.png' ), NAWS_Radar_Store::has( $k1, 'frames/c.png' ) ], [ true, true ] );
$ret = NAWS_Radar_Store::prune( $k1, [ 'frames' => [ [ 'file' => 'frames/b.png' ] ], 'retired' => [ 'frames/c.png' => $now ] ], [ 'frames' => [ [ 'file' => 'frames/c.png' ] ] ], $now + 99999 );
check( 'wieder benutztes Bild wird nicht geloescht', [ NAWS_Radar_Store::has( $k1, 'frames/c.png' ), array_key_exists( 'frames/c.png', $ret ) ], [ true, false ] );

echo "\nSperre und Sperrfrist\n" . str_repeat( '-', 74 ) . "\n";
check( 'erste Sperre klappt',               NAWS_Radar_Store::lock( $k1 ), true );
check( 'zweite nicht',                      NAWS_Radar_Store::lock( $k1 ), false );
NAWS_Radar_Store::unlock( $k1 );
check( 'nach unlock wieder',                NAWS_Radar_Store::lock( $k1 ), true );
NAWS_Radar_Store::unlock( $k1 );
check( 'keine Sperrfrist',                  NAWS_Radar_Store::in_backoff( 'dwd' ), false );
NAWS_Radar_Store::start_backoff( 'dwd', resp( 429, '', [ 'retry-after' => '120' ] ) );
check( '429: Sperrfrist',                   NAWS_Radar_Store::in_backoff( 'dwd' ), true );
check( '429: Retry-After 120 s',            $GLOBALS['transients']['naws_radar_backoff_dwd'][1], 120 );
check( 'andere Quelle frei',                NAWS_Radar_Store::in_backoff( 'rainviewer' ), false );
NAWS_Radar_Store::start_backoff( 'osm', new WP_Error( 'x', 'timeout' ) );
check( 'kein Netz: 5 min',                  $GLOBALS['transients']['naws_radar_backoff_osm'][1], 300 );
check( 'ins Protokoll',                     str_contains( implode( "\n", $GLOBALS['logged'] ), 'dwd request failed (429)' ), true );

echo "\nAufraeumen\n" . str_repeat( '-', 74 ) . "\n";
NAWS_Radar_Store::remember( $k2, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 100, 'source' => 'dwd' ], $now );
NAWS_Radar_Store::put( $k2, 'frames/z.png', 'zz' );
fs()->put_contents( '/up/naws-radar/verwaist/frames/q.png', 'q' );
check( 'Platzbedarf k2',                    NAWS_Radar_Store::usage( $k2 ), 2 );
check( 'nach 6 Tagen nichts geloescht',     NAWS_Radar_Store::cleanup( $now + 6 * DAY_IN_SECONDS ), 1 ); // nur der verwaiste Ordner
check( 'verwaister Ordner weg',             fs()->exists( '/up/naws-radar/verwaist' ), false );
check( 'k2 noch da',                        NAWS_Radar_Store::has( $k2, 'frames/z.png' ), true );
check( 'nach 7 Tagen: k2 weg (k1 war spaeter gesehen)', NAWS_Radar_Store::cleanup( $now + 7 * DAY_IN_SECONDS ), 1 );
check( 'k2 aus der Option',                 isset( NAWS_Radar_Store::sets()[ $k2 ] ), false );
check( 'k2 Dateien weg',                    NAWS_Radar_Store::has( $k2, 'frames/z.png' ), false );
check( 'k1 bleibt',                         isset( NAWS_Radar_Store::sets()[ $k1 ] ), true );
NAWS_Radar_Store::clear_all();
check( 'clear_all: Ordner leer',            fs()->exists( '/up/naws-radar' ), false );
check( 'clear_all: Option weg',             get_option( NAWS_Radar_Store::OPTION, 'weg' ), 'weg' );
check( 'clear_all: Sperrfristen weg',       NAWS_Radar_Store::in_backoff( 'dwd' ), false );

done();
```

- [ ] **Step 3:** `php tests/test-radar-store.php` — erwartet Fatal error (Klasse fehlt).
- [ ] **Step 4: Implementieren** — `includes/class-naws-radar-store.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Where the rain radar keeps its images: wp-content/uploads/naws-radar/
 * <key>/ with map/ (tiles, kept), frames/ (radar, replaced) and
 * manifest.json. Also the lock against parallel runs, the pause after a
 * failed request, and every kind of clean-up.
 *
 * All file access goes through WP_Filesystem.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar_Store {

    const DIR        = 'naws-radar';
    const OPTION     = 'naws_radar_sets';
    const LOCK       = 'naws_radar_lock_';
    const BACKOFF    = 'naws_radar_backoff_';
    const SOURCES    = [ 'dwd', 'rainviewer', 'osm' ];
    const LOCK_TTL   = 300;
    const GRACE      = 1800;     // an open player may still show the previous set
    const UNUSED     = 604800;   // 7 days without a page showing the set
    const SEEN_EVERY = 3600;     // write last_seen at most once an hour

    /** @return string[] [ base dir, base url ] without trailing slash */
    private static function base(): array {
        $u = wp_upload_dir( null, false );
        return [ trailingslashit( $u['basedir'] ) . self::DIR, trailingslashit( $u['baseurl'] ) . self::DIR ];
    }

    public static function key( float $lat, float $lon, int $km, string $source ): string {
        return substr( md5( sprintf( '%.4F|%.4F|%d|%s', $lat, $lon, $km, $source ) ), 0, 12 );
    }

    public static function dir( string $key ): string {
        return self::base()[0] . '/' . $key;
    }

    public static function url( string $key, string $rel ): string {
        return self::base()[1] . '/' . $key . '/' . $rel;
    }

    /** @return WP_Filesystem_Base|null */
    private static function fs() {
        global $wp_filesystem;
        if ( ! $wp_filesystem && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem ? $wp_filesystem : null;
    }

    public static function sets(): array {
        $s = get_option( self::OPTION, [] );
        return is_array( $s ) ? $s : [];
    }

    /** Note that a page showed this set; at most one option write an hour. */
    public static function remember( string $key, array $params, int $now ): void {
        $sets = self::sets();
        if ( isset( $sets[ $key ] ) && $now - (int) ( $sets[ $key ]['last_seen'] ?? 0 ) < self::SEEN_EVERY ) {
            return;
        }
        $sets[ $key ] = array_merge( $params, [ 'last_seen' => $now ] );
        update_option( self::OPTION, $sets, false );
    }

    public static function manifest( string $key ): ?array {
        $fs   = self::fs();
        $path = self::dir( $key ) . '/manifest.json';
        if ( ! $fs || ! $fs->exists( $path ) ) {
            return null;
        }
        $m = json_decode( (string) $fs->get_contents( $path ), true );
        return is_array( $m ) ? $m : null;
    }

    public static function save_manifest( string $key, array $m ): bool {
        return self::put( $key, 'manifest.json', (string) wp_json_encode( $m ) );
    }

    public static function put( string $key, string $rel, string $bytes ): bool {
        $fs = self::fs();
        if ( ! $fs ) {
            return false;
        }
        $path = self::dir( $key ) . '/' . $rel;
        wp_mkdir_p( dirname( $path ) );
        return (bool) $fs->put_contents( $path, $bytes, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : false );
    }

    public static function has( string $key, string $rel ): bool {
        $fs = self::fs();
        return $fs && $fs->exists( self::dir( $key ) . '/' . $rel );
    }

    /** The radar files a manifest refers to (the map is never pruned). */
    public static function files_of( array $m ): array {
        $out = [];
        foreach ( $m['frames'] ?? [] as $f ) {
            if ( isset( $f['file'] ) ) {
                $out[] = $f['file'];
            }
            foreach ( $f['tiles'] ?? [] as $t ) {
                $out[] = $t['file'];
            }
        }
        return $out;
    }

    /**
     * Retire what the new manifest no longer uses and delete what has been
     * retired for GRACE seconds. Returns the new retired list.
     */
    public static function prune( string $key, ?array $old, array $new, int $now ): array {
        $fs      = self::fs();
        $keep    = self::files_of( $new );
        $retired = is_array( $old['retired'] ?? null ) ? $old['retired'] : [];
        foreach ( array_diff( self::files_of( $old ?? [] ), $keep ) as $file ) {
            $retired[ $file ] = $retired[ $file ] ?? $now;
        }
        foreach ( $retired as $file => $since ) {
            if ( in_array( $file, $keep, true ) ) {
                unset( $retired[ $file ] );
                continue;
            }
            if ( $now - (int) $since >= self::GRACE ) {
                if ( $fs ) {
                    $fs->delete( self::dir( $key ) . '/' . $file );
                }
                unset( $retired[ $file ] );
            }
        }
        return $retired;
    }

    public static function delete_set( string $key ): void {
        $fs = self::fs();
        if ( $fs ) {
            $fs->delete( self::dir( $key ), true );
        }
        $sets = self::sets();
        unset( $sets[ $key ] );
        update_option( self::OPTION, $sets, false );
    }

    /**
     * Daily: delete sets no page has shown for UNUSED seconds, and folders
     * no set knows. Returns how many folders went.
     */
    public static function cleanup( int $now ): int {
        $gone = 0;
        $sets = self::sets();
        foreach ( $sets as $key => $set ) {
            if ( $now - (int) ( $set['last_seen'] ?? 0 ) >= self::UNUSED ) {
                self::delete_set( (string) $key );
                ++$gone;
            }
        }
        $fs   = self::fs();
        $list = $fs ? $fs->dirlist( self::base()[0] ) : false;
        $sets = self::sets();
        foreach ( is_array( $list ) ? $list : [] as $name => $entry ) {
            if ( 'd' === ( $entry['type'] ?? '' ) && ! isset( $sets[ $name ] ) ) {
                $fs->delete( self::base()[0] . '/' . $name, true );
                ++$gone;
            }
        }
        return $gone;
    }

    /** Everything: folder, list of sets, pauses. */
    public static function clear_all(): void {
        $fs = self::fs();
        if ( $fs ) {
            $fs->delete( self::base()[0], true );
        }
        delete_option( self::OPTION );
        foreach ( self::SOURCES as $source ) {
            delete_transient( self::BACKOFF . $source );
        }
    }

    /** Bytes on disk for one set. */
    public static function usage( string $key ): int {
        $fs   = self::fs();
        $list = $fs ? $fs->dirlist( self::dir( $key ), true, true ) : false;
        return is_array( $list ) ? self::sum( $list ) : 0;
    }

    private static function sum( array $list ): int {
        $bytes = 0;
        foreach ( $list as $e ) {
            $bytes += 'd' === ( $e['type'] ?? '' ) ? self::sum( (array) ( $e['files'] ?? [] ) ) : (int) ( $e['size'] ?? 0 );
        }
        return $bytes;
    }

    public static function lock( string $key ): bool {
        if ( false !== get_transient( self::LOCK . $key ) ) {
            return false;
        }
        set_transient( self::LOCK . $key, 1, self::LOCK_TTL );
        return true;
    }

    public static function unlock( string $key ): void {
        delete_transient( self::LOCK . $key );
    }

    public static function in_backoff( string $source ): bool {
        return false !== get_transient( self::BACKOFF . $source );
    }

    /** Same pause rules as the forecast (NAWS_Forecast::backoff_seconds). */
    public static function start_backoff( string $source, $response ): void {
        $code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $retry   = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'retry-after' );
        $seconds = NAWS_Forecast::backoff_seconds( $code, $retry );
        set_transient(
            self::BACKOFF . $source,
            [
                'code'  => $code,
                'until' => time() + $seconds,
            ],
            $seconds
        );
        NAWS_Logger::warning(
            'radar',
            sprintf( '%s request failed (%s), next attempt in %d minute(s)', $source, $code ? $code : 'no response', (int) ceil( $seconds / 60 ) )
        );
    }
}
```

- [ ] **Step 5:** `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-radar-store.php' );` nach der Source-Zeile.
- [ ] **Step 6:** `php tests/test-radar-store.php` grün.
- [ ] **Step 7:** DoD, `git add tests/radar-stubs.php tests/test-radar-store.php includes/class-naws-radar-store.php xtx-integration-for-netatmo.php && git commit -m "Radar: store — files, manifest, lock, pause and clean-up"`

---

### Task 4: `NAWS_Radar::refresh()` — der Hintergrundlauf

**Files:**
- Create: `includes/class-naws-radar.php` (erster Teil)
- Modify: `xtx-integration-for-netatmo.php` (nach `class-naws-radar-store.php`)
- Test: `tests/test-radar-refresh.php`

**Interfaces:**
- Consumes: Task 1–3.
- Produces: `NAWS_Radar::HOOK_REFRESH` = `'naws_radar_refresh'`, `::MAX_AGE` = 900, `::STALE` = 10800; `enabled(): bool`; `user_agent(): string`; `refresh( string $key, int $now = 0 ): bool`.

- [ ] **Step 1: Test schreiben** — `tests/test-radar-refresh.php`:

```php
<?php
/**
 * Tests fuer NAWS_Radar::refresh(): was der Hintergrundlauf holt, was er
 * wiederverwendet, was er bei Fehlern tut — alles ohne echte Abrufe.
 *
 *   php tests/test-radar-refresh.php
 *
 * @package NAWS
 */
require_once __DIR__ . '/radar-stubs.php';
$P = dirname( __DIR__ ) . '/includes/';
require_once $P . 'class-naws-radar-geo.php';
require_once $P . 'class-naws-radar-source.php';
require_once $P . 'class-naws-radar-store.php';
require_once $P . 'class-naws-radar.php';

function reset_http(): void { $GLOBALS['calls'] = []; }
$GLOBALS['http_routes'] = [
    'https://tile.openstreetmap.org/' => png( 'osm' ),
    'https://maps.dwd.de/'            => png( 'dwd' ),
];
$key = NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' );
$now = gmmktime( 15, 26, 0, 10, 13, 2026 );
NAWS_Radar_Store::remember( $key, [ 'lat' => 51.35, 'lon' => 12.37, 'km' => 150, 'source' => 'dwd' ], $now );

echo "\nDWD: erster Lauf\n" . str_repeat( '-', 74 ) . "\n";
check( 'Lauf klappt',                       NAWS_Radar::refresh( $key, $now ), true );
$m = NAWS_Radar_Store::manifest( $key );
check( '13 Bilder',                         count( $m['frames'] ), 13 );
check( '8 davon Vorschau',                  count( array_filter( $m['frames'], fn( $f ) => $f['forecast'] ) ), 8 );
check( '49 Kartenkacheln',                  count( $m['map']['tiles'] ), 49 );
check( '13 DWD-Abrufe',                     calls_to( 'https://maps.dwd.de/' ), 13 );
check( '49 OSM-Abrufe',                     calls_to( 'https://tile.openstreetmap.org/' ), 49 );
check( 'Stand',                             $m['updated'], $now );
check( 'gemessenes Bild heisst frames/<t>.png', $m['frames'][4]['file'], 'frames/' . gmmktime( 15, 15, 0, 10, 13, 2026 ) . '.png' );
check( 'Sperre wieder frei',                NAWS_Radar_Store::lock( $key ), true );
NAWS_Radar_Store::unlock( $key );

echo "\nDWD: 15 Minuten spaeter\n" . str_repeat( '-', 74 ) . "\n";
reset_http();
check( 'Lauf klappt',                       NAWS_Radar::refresh( $key, $now + 900 ), true );
check( 'nur 1 gemessenes + 8 Vorschau',     calls_to( 'https://maps.dwd.de/' ), 9 );
check( 'Karte nicht neu geholt',            calls_to( 'https://tile.openstreetmap.org/' ), 0 );
$m2 = NAWS_Radar_Store::manifest( $key );
check( 'alte Vorschau ausgemustert (8) + aeltestes gemessenes (1)', count( $m2['retired'] ), 9 );
reset_http();
NAWS_Radar::refresh( $key, $now + 2700 );
$m3 = NAWS_Radar_Store::manifest( $key );
$first_forecast = $m['frames'][5]['file'];
check( 'nach 30 min: erste Vorschau-Dateien geloescht', NAWS_Radar_Store::has( $key, $first_forecast ), false );
check( 'keine verwaiste Datei', count( array_diff(
    array_keys( array_filter( fs()->files, fn( $c, $p ) => str_contains( $p, "/$key/frames/" ), ARRAY_FILTER_USE_BOTH ) ),
    array_map( fn( $f ) => NAWS_Radar_Store::dir( $key ) . '/' . $f, array_merge( NAWS_Radar_Store::files_of( $m3 ), array_keys( $m3['retired'] ) ) )
) ), 0 );

echo "\nFehler\n" . str_repeat( '-', 74 ) . "\n";
// Eine Vorschau noch nicht gerechnet: 200 mit XML statt PNG.
$GLOBALS['http_routes'] = [
    'https://maps.dwd.de/' => function ( $url ) { return str_contains( $url, 'Radar_wn' ) && str_contains( $url, 'T17%3A' ) ? resp( 200, '<ServiceExceptionReport/>' ) : png( 'dwd' ); },
] + $GLOBALS['http_routes'];
reset_http();
check( 'Lauf klappt trotzdem',              NAWS_Radar::refresh( $key, $now + 3600 ), true );
check( 'fehlende Bilder uebersprungen, keine Sperrfrist', [ count( NAWS_Radar_Store::manifest( $key )['frames'] ) < 13, NAWS_Radar_Store::in_backoff( 'dwd' ) ], [ true, false ] );

// 429 nach drei Bildern.
$n = 0;
$GLOBALS['http_routes'] = [ 'https://maps.dwd.de/' => function () use ( &$n ) { return ++$n > 3 ? resp( 429, '', [ 'retry-after' => '600' ] ) : png( 'dwd' ); } ] + $GLOBALS['http_routes'];
$before = NAWS_Radar_Store::manifest( $key );
reset_http();
NAWS_Radar::refresh( $key, $now + 4500 );
check( '429: Sperrfrist gesetzt',           NAWS_Radar_Store::in_backoff( 'dwd' ), true );
check( '429: nach dem Fehler keine weiteren Abrufe', calls_to( 'https://maps.dwd.de/' ), 4 );
$after = NAWS_Radar_Store::manifest( $key );
check( '429: Manifest bleibt nutzbar',      count( $after['frames'] ) >= 1, true );
reset_http();
check( 'waehrend der Sperrfrist kein Lauf', [ NAWS_Radar::refresh( $key, $now + 4600 ), count( $GLOBALS['calls'] ) ], [ false, 0 ] );

echo "\nSchalter, unbekannter Satz, Sperre\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['transients'] = [];
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 0;
reset_http();
check( 'Schalter aus: kein Lauf, kein Abruf', [ NAWS_Radar::refresh( $key, $now + 5400 ), count( $GLOBALS['calls'] ) ], [ false, 0 ] );
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 1;
check( 'unbekannter Satz: kein Lauf',       NAWS_Radar::refresh( 'ffffffffffff', $now ), false );
NAWS_Radar_Store::lock( $key );
check( 'gesperrt: kein Lauf',               NAWS_Radar::refresh( $key, $now + 5400 ), false );
NAWS_Radar_Store::unlock( $key );

echo "\nRainViewer\n" . str_repeat( '-', 74 ) . "\n";
$rk  = NAWS_Radar_Store::key( 59.91, 10.75, 150, 'rainviewer' );
$rnow = 1791904826;
NAWS_Radar_Store::remember( $rk, [ 'lat' => 59.91, 'lon' => 10.75, 'km' => 150, 'source' => 'rainviewer' ], $rnow );
$past = [];
for ( $i = 12; $i >= 0; $i-- ) { $past[] = [ 'time' => $rnow - $i * 600, 'path' => '/v2/radar/p' . $i ]; }
$GLOBALS['http_routes'] = [
    'https://api.rainviewer.com/'      => resp( 200, json_encode( [ 'host' => 'https://tilecache.rainviewer.com', 'radar' => [ 'past' => $past ] ] ) ),
    'https://tilecache.rainviewer.com/' => png( 'rv' ),
    'https://tile.openstreetmap.org/'  => png( 'osm' ),
];
reset_http();
check( 'Lauf klappt',                       NAWS_Radar::refresh( $rk, $rnow ), true );
$rm = NAWS_Radar_Store::manifest( $rk );
check( '13 Bilder, keine Vorschau',         [ count( $rm['frames'] ), count( array_filter( $rm['frames'], fn( $f ) => $f['forecast'] ) ) ], [ 13, 0 ] );
check( 'jedes Bild aus Kacheln',            isset( $rm['frames'][0]['tiles'][0]['file'] ), true );
$per = count( $rm['frames'][0]['tiles'] );
check( 'Kacheln = Bilder x Kacheln je Bild', calls_to( 'https://tilecache.rainviewer.com/' ), 13 * $per );
reset_http();
NAWS_Radar::refresh( $rk, $rnow + 900 );
check( 'zweiter Lauf: nur der Index, keine alten Kacheln', calls_to( 'https://tilecache.rainviewer.com/' ), 0 );
check( 'User-Agent nennt Plugin und Site',  NAWS_Radar::user_agent(), 'XTX-Integration-for-Netatmo/test (+https://example.org/)' );

done();
```

- [ ] **Step 2:** `php tests/test-radar-refresh.php` — erwartet Fatal error (Klasse fehlt).
- [ ] **Step 3: Implementieren** — `includes/class-naws-radar.php` (Task 6 ergänzt Ausgabe und REST):

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [naws_radar]: the rain radar around the station.
 *
 * The visitor's browser only ever talks to this site. The server fetches
 * map tiles once and radar images every fifteen minutes - but only after a
 * page showed the radar, in a single background run - and keeps them in
 * uploads/naws-radar/. Nothing goes out unless "Allow rain radar" is on.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar {

    const HOOK_REFRESH = 'naws_radar_refresh';
    const MAX_AGE      = 900;     // renew a set older than this
    const STALE        = 10800;   // older than this: "temporarily unavailable"

    public static function enabled(): bool {
        $o = get_option( 'naws_settings', [] );
        return ! empty( $o['radar_enabled'] );
    }

    public static function user_agent(): string {
        return 'XTX-Integration-for-Netatmo/' . NAWS_VERSION . ' (+' . home_url( '/' ) . ')';
    }

    private static function get( string $url ) {
        return wp_remote_get(
            $url,
            [
                'timeout'    => 15,
                'user-agent' => self::user_agent(),
            ]
        );
    }

    /**
     * 'png' with the bytes, 'skip' (no image for this one, try the next) or
     * 'fail' (the service is in trouble: pause it and stop).
     *
     * @return array{0:string, 1:string}
     */
    private static function classify( $response ): array {
        if ( is_wp_error( $response ) ) {
            return [ 'fail', '' ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 429 === $code || $code >= 500 ) {
            return [ 'fail', '' ];
        }
        $body = (string) wp_remote_retrieve_body( $response );
        if ( 200 !== $code || 0 !== strncmp( $body, "\x89PNG", 4 ) ) {
            return [ 'skip', '' ];
        }
        return [ 'png', $body ];
    }

    /** Background run for one set. True when a new manifest was written. */
    public static function refresh( string $key, int $now = 0 ): bool {
        $now = $now ? $now : time();
        $set = NAWS_Radar_Store::sets()[ $key ] ?? null;
        if ( ! self::enabled() || ! is_array( $set ) ) {
            return false;
        }
        $source = 'dwd' === ( $set['source'] ?? '' ) ? 'dwd' : 'rainviewer';
        if ( NAWS_Radar_Store::in_backoff( $source ) || ! NAWS_Radar_Store::lock( $key ) ) {
            return false;
        }
        try {
            $old    = NAWS_Radar_Store::manifest( $key );
            $box    = NAWS_Radar_Geo::box( (float) $set['lat'], (float) $set['lon'], (int) $set['km'] );
            $map    = self::fetch_map( $key, $box, $old['map']['tiles'] ?? [] );
            $frames = 'dwd' === $source ? self::fetch_dwd( $key, $box, $now ) : self::fetch_rainviewer( $key, $box, $now );
            if ( ! $frames ) {
                return false;
            }
            $new            = [
                'v'       => 1,
                'source'  => $source,
                'updated' => $now,
                'map'     => [ 'tiles' => $map ],
                'frames'  => $frames,
            ];
            $new['retired'] = NAWS_Radar_Store::prune( $key, $old, $new, $now );
            return NAWS_Radar_Store::save_manifest( $key, $new );
        } finally {
            NAWS_Radar_Store::unlock( $key );
        }
    }

    /** Map tiles: fetched once, kept. Missing ones are tried again next run. */
    private static function fetch_map( string $key, array $box, array $known ): array {
        $have = array_column( $known, null, 'file' );
        $out  = [];
        $ok   = ! NAWS_Radar_Store::in_backoff( 'osm' );
        foreach ( NAWS_Radar_Geo::tiles( $box, NAWS_Radar_Geo::zoom_for( $box ) ) as $t ) {
            $file  = sprintf( 'map/%d-%d-%d.png', $t['z'], $t['x'], $t['y'] );
            $entry = [
                'file' => $file,
                'left' => $t['left'],
                'top'  => $t['top'],
                'size' => $t['size'],
            ];
            if ( isset( $have[ $file ] ) && NAWS_Radar_Store::has( $key, $file ) ) {
                $out[] = $entry;
                continue;
            }
            if ( ! $ok ) {
                continue;
            }
            $r            = self::get( NAWS_Radar_Source::osm_tile_url( $t['z'], $t['x'], $t['y'] ) );
            [ $kind, $png ] = self::classify( $r );
            if ( 'fail' === $kind ) {
                NAWS_Radar_Store::start_backoff( 'osm', $r );
                $ok = false;
                continue;
            }
            if ( 'png' === $kind && NAWS_Radar_Store::put( $key, $file, $png ) ) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /** DWD: measured frames are reused, nowcast frames belong to their run. */
    private static function fetch_dwd( string $key, array $box, int $now ): array {
        $times = NAWS_Radar_Source::dwd_times( $now );
        $base  = $times[ NAWS_Radar_Source::PAST ]['t'];
        $out   = [];
        foreach ( $times as $f ) {
            $file = $f['forecast'] ? sprintf( 'frames/f%d-%d.png', $f['t'], $base ) : sprintf( 'frames/%d.png', $f['t'] );
            if ( ! NAWS_Radar_Store::has( $key, $file ) ) {
                $r              = self::get( NAWS_Radar_Source::dwd_url( $box, $f['t'], $f['forecast'] ) );
                [ $kind, $png ] = self::classify( $r );
                if ( 'fail' === $kind ) {
                    NAWS_Radar_Store::start_backoff( 'dwd', $r );
                    break;
                }
                if ( 'skip' === $kind || ! NAWS_Radar_Store::put( $key, $file, $png ) ) {
                    continue;
                }
            }
            $out[] = [
                't'        => $f['t'],
                'forecast' => $f['forecast'],
                'file'     => $file,
            ];
        }
        return $out;
    }

    /** RainViewer: the index, then the tiles of each frame (kept once fetched). */
    private static function fetch_rainviewer( string $key, array $box, int $now ): array {
        $r = self::get( NAWS_Radar_Source::RV_INDEX );
        if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
            NAWS_Radar_Store::start_backoff( 'rainviewer', $r );
            return [];
        }
        $index = json_decode( (string) wp_remote_retrieve_body( $r ), true );
        $host  = is_array( $index ) ? NAWS_Radar_Source::rv_host( $index ) : null;
        if ( null === $host ) {
            return [];
        }
        $tiles = NAWS_Radar_Geo::tiles( $box, min( NAWS_Radar_Geo::zoom_for( $box ), NAWS_Radar_Geo::RV_MAX_ZOOM ) );
        $out   = [];
        foreach ( NAWS_Radar_Source::rv_frames( $index, $now ) as $f ) {
            $parts = [];
            foreach ( $tiles as $t ) {
                $file = sprintf( 'frames/%d-%d-%d-%d.png', $f['t'], $t['z'], $t['x'], $t['y'] );
                if ( ! NAWS_Radar_Store::has( $key, $file ) ) {
                    $tr             = self::get( NAWS_Radar_Source::rv_tile_url( $host, $f['path'], $t['z'], $t['x'], $t['y'] ) );
                    [ $kind, $png ] = self::classify( $tr );
                    if ( 'fail' === $kind ) {
                        NAWS_Radar_Store::start_backoff( 'rainviewer', $tr );
                        return $out;
                    }
                    if ( 'skip' === $kind || ! NAWS_Radar_Store::put( $key, $file, $png ) ) {
                        continue 2;
                    }
                }
                $parts[] = [
                    'file' => $file,
                    'left' => $t['left'],
                    'top'  => $t['top'],
                    'size' => $t['size'],
                ];
            }
            $out[] = [
                't'        => $f['t'],
                'forecast' => false,
                'tiles'    => $parts,
            ];
        }
        return $out;
    }
}
```

Hinweis zu „keine verwaisten Dateien“ (Review Focus 3): Bricht ein Lauf ab, bevor auch nur ein Bild ins neue Manifest kam, gibt `fetch_*` `[]` zurück und `refresh()` schreibt nichts — dann gibt es auch keine neue Datei. Bricht er später ab, kommen die bis dahin geholten Bilder ins neue Manifest; die übrigen alten wandern über `prune()` in `retired`. Bei RainViewer kann ein abgebrochenes Bild einzelne Kacheln hinterlassen, die in keinem Manifest stehen: Sie liegen unter dem Namen, den der nächste Lauf wieder benutzt (`has()` → wiederverwendet), oder fallen mit dem Satz beim 7-Tage-Aufräumen weg.

- [ ] **Step 4:** `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-radar.php' );` nach der Store-Zeile.
- [ ] **Step 5:** `php tests/test-radar-refresh.php` grün. Wenn „keine verwaiste Datei“ fehlschlägt, liegt der Fehler in `prune()` oder in den Dateinamen — nicht den Test lockern.
- [ ] **Step 6:** DoD, `git add includes/class-naws-radar.php tests/test-radar-refresh.php xtx-integration-for-netatmo.php && git commit -m "Radar: background run — fetch, reuse, pause on errors, retire old images"`

---

### Task 5: Player `assets/js/radar.js`

**Files:**
- Create: `assets/js/radar.js`
- Modify: `includes/class-naws-shortcodes.php` (Registrierung in `enqueue_frontend_assets()` nach `naws-sparkline-boot`), `tests/test-asset-version.php` (Liste)
- Test: `tests/test-radar-player.php`

**Interfaces:**
- Consumes: JSON-Nutzlast aus Task 6: `{ key, source, updated, updatedLabel, stale, rest, frames:[{t,label,forecast,img?|tiles?:[[url,l,t,s]]}], map:[[url,l,t,s]] }`; DOM-Klassen aus Task 6 (`.naws-radar-layer`, `.naws-radar-map`, `.naws-radar-clock`, `.naws-radar-fc`, `.naws-radar-play`, `.naws-radar-range`, `.naws-radar-msg`, Wurzel `[data-naws-radar]`).
- Produces: Skript-Handle `naws-radar`; Node-Export `core` mit `STEP` 600, `HOLD` 1500, `POLL_FIRST` 20000, `POLL_TRIES` 10, `POLL_EVERY` 900000, `nowIndex(frames)`, `nextOk(i, n, broken)`, `delay(i, n)`, `autoplay(frames, reduced)`, `pct(v)`.

- [ ] **Step 1: Test schreiben** — `tests/test-radar-player.php`:

```php
<?php
/**
 * Tests fuer die Logik des Radar-Players (assets/js/radar.js) in Node,
 * dazu: das Skript nennt keinen fremden Host.
 *
 *   php tests/test-radar-player.php
 *
 * @package NAWS
 */
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$file = dirname( __DIR__ ) . '/assets/js/radar.js';
$js   = <<<'JS'
const c = require(process.argv[2]); // argv[1] is "-" (script on stdin)
const f = (fc) => fc.map((x) => ({ forecast: x }));
const p = f([false, false, false, false, false, true, true, true, true, true, true, true, true]);
console.log(JSON.stringify({
  now: c.nowIndex(p), nowAllPast: c.nowIndex(f([false, false, false])), nowEmpty: c.nowIndex([]),
  next: c.nextOk(3, 13, {}), wrap: c.nextOk(12, 13, {}), skip: c.nextOk(3, 13, { 4: true, 5: true }),
  allBroken: c.nextOk(0, 2, { 0: true, 1: true }), zero: c.nextOk(0, 0, {}),
  hold: c.delay(12, 13), step: c.delay(3, 13),
  auto: c.autoplay(p, false), reduced: c.autoplay(p, true), single: c.autoplay(f([false]), false),
  pct: c.pct(512), pctNeg: c.pct(-19.59)
}));
JS;
$proc = proc_open( [ 'node', '-', $file ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
fwrite( $pipes[0], $js );
fclose( $pipes[0] );
$out = stream_get_contents( $pipes[1] );
$err = stream_get_contents( $pipes[2] );
proc_close( $proc );
$r = json_decode( (string) $out, true );
if ( ! is_array( $r ) ) { echo "Node-Fehler: $err\n"; exit( 1 ); }

echo "\nradar.js core\n" . str_repeat( '-', 74 ) . "\n";
check( '"jetzt" ist das letzte gemessene Bild', $r['now'], 4 );
check( 'nur gemessene: das letzte',      $r['nowAllPast'], 2 );
check( 'leer: -1',                       $r['nowEmpty'], -1 );
check( 'naechstes Bild',                 $r['next'], 4 );
check( 'nach dem letzten wieder vorn',   $r['wrap'], 0 );
check( 'kaputte Bilder uebersprungen',   $r['skip'], 6 );
check( 'alle kaputt: bleibt stehen',     $r['allBroken'], 0 );
check( 'keine Bilder: 0',                $r['zero'], 0 );
check( 'am Ende 1,5 s Halt',             $r['hold'], 1500 );
check( 'sonst 600 ms',                   $r['step'], 600 );
check( 'spielt von selbst',              $r['auto'], true );
check( 'nicht bei reduzierter Bewegung', $r['reduced'], false );
check( 'nicht bei nur einem Bild',       $r['single'], false );
check( 'Prozent',                        $r['pct'], '50.0000%' );
check( 'Prozent negativ',                $r['pctNeg'], '-1.9131%' );

echo "\nDatenschutz\n" . str_repeat( '-', 74 ) . "\n";
$src = (string) file_get_contents( $file );
check( 'keine Adresse mit http im Skript', preg_match( '#https?://#', $src ), 0 );
check( 'kein fremder Dienst genannt',      preg_match( '/rainviewer|dwd\.de|openstreetmap/i', $src ), 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2:** `php tests/test-radar-player.php` — erwartet „Node-Fehler: … Cannot find module“.
- [ ] **Step 3: Implementieren** — `assets/js/radar.js`:

```js
/**
 * [naws_radar] player: shows the images the server stored, one after the
 * other. It only ever asks this site - the frame URLs and the REST route
 * in the payload point here - never a weather or map service.
 *
 * The logic lives in `core`, which Node can load for the tests.
 */
(function (root) {
    'use strict';

    var core = {
        STEP: 600,
        HOLD: 1500,
        POLL_FIRST: 20000,
        POLL_TRIES: 10,
        POLL_EVERY: 900000,

        /** The newest measured frame, or the last one; -1 without frames. */
        nowIndex: function (frames) {
            var i, last = -1;
            for (i = 0; i < frames.length; i++) {
                if (!frames[i].forecast) { last = i; }
            }
            return last < 0 ? frames.length - 1 : last;
        },

        /** The next frame that did not fail to load; stays put if all did. */
        nextOk: function (i, n, broken) {
            var k, j;
            if (!n) { return 0; }
            for (k = 1; k <= n; k++) {
                j = (i + k) % n;
                if (!broken[j]) { return j; }
            }
            return i;
        },

        delay: function (i, n) { return i === n - 1 ? core.HOLD : core.STEP; },

        autoplay: function (frames, reduced) { return !reduced && frames.length >= 2; },

        /** A position in the 1024 px image space as a percentage. */
        pct: function (v) { return (v / 1024 * 100).toFixed(4) + '%'; }
    };

    if (typeof module === 'object' && module.exports) {
        module.exports = core;
        return;
    }

    function img(src, l, t, s, onerror) {
        var el = document.createElement('img');
        el.src = src;
        el.alt = '';
        el.decoding = 'async';
        el.style.left = core.pct(l);
        el.style.top = core.pct(t);
        el.style.width = core.pct(s);
        if (onerror) { el.addEventListener('error', onerror); }
        return el;
    }

    function boot(el) {
        var node = el.querySelector('script[type="application/json"][data-naws="radar"]');
        var data;
        if (!node) { return; }
        try { data = JSON.parse(node.textContent); } catch (e) { return; }

        var layer = el.querySelector('.naws-radar-layer'),
            map = el.querySelector('.naws-radar-map'),
            clock = el.querySelector('.naws-radar-clock'),
            fc = el.querySelector('.naws-radar-fc'),
            btn = el.querySelector('.naws-radar-play'),
            range = el.querySelector('.naws-radar-range'),
            reduced = !!(root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches),
            frames = [], nodes = [], broken = {}, idx = 0, timer = null, playing = false, tries = 0;

        function show(i) {
            if (!nodes.length || i < 0) { return; }
            if (nodes[idx]) { nodes[idx].classList.remove('is-on'); }
            idx = i;
            nodes[idx].classList.add('is-on');
            range.value = String(idx);
            clock.textContent = frames[idx].label;
            fc.hidden = !frames[idx].forecast;
            range.setAttribute('aria-valuetext', frames[idx].label + (frames[idx].forecast ? ' ' + fc.textContent : ''));
        }

        function build(d) {
            frames = (d.stale ? [] : d.frames) || [];
            broken = {};
            layer.textContent = '';
            nodes = frames.map(function (f, i) {
                var box = document.createElement('div');
                var mark = function () { broken[i] = true; };
                box.className = 'naws-radar-frame';
                if (f.img) { box.appendChild(img(f.img, 0, 0, 1024, mark)); }
                (f.tiles || []).forEach(function (t) { box.appendChild(img(t[0], t[1], t[2], t[3], mark)); });
                layer.appendChild(box);
                return box;
            });
            if (map && !map.children.length && d.map) {
                d.map.forEach(function (t) { map.appendChild(img(t[0], t[1], t[2], t[3])); });
            }
            range.max = String(Math.max(0, frames.length - 1));
            range.disabled = frames.length < 2;
            btn.disabled = frames.length < 2;
            idx = 0;
            show(core.nowIndex(frames));
            if (frames.length) {
                el.classList.remove('naws-radar--preparing', 'naws-radar--unavailable');
                var msg = el.querySelector('.naws-radar-msg');
                if (msg) { msg.hidden = true; }
            }
        }

        function tick() {
            show(core.nextOk(idx, frames.length, broken));
            timer = root.setTimeout(tick, core.delay(idx, frames.length));
        }

        function play(on) {
            playing = on && frames.length >= 2;
            root.clearTimeout(timer);
            btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
            btn.classList.toggle('is-playing', playing);
            if (playing) { timer = root.setTimeout(tick, core.STEP); }
        }

        function poll(wait) {
            root.setTimeout(function () {
                if (document.hidden) { poll(wait); return; }
                root.fetch(data.rest, { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (d && d.frames && d.frames.length && !d.stale && d.updated !== data.updated) {
                            var was = playing;
                            data = d;
                            play(false);
                            build(d);
                            play(was || core.autoplay(frames, reduced));
                            poll(core.POLL_EVERY);
                        } else if (!frames.length && ++tries < core.POLL_TRIES) {
                            poll(core.POLL_FIRST);
                        } else {
                            poll(core.POLL_EVERY);
                        }
                    })
                    .catch(function () { poll(core.POLL_EVERY); });
            }, wait);
        }

        btn.addEventListener('click', function () { play(!playing); });
        range.addEventListener('input', function () {
            play(false);
            show(parseInt(range.value, 10) || 0);
        });

        build(data);
        play(core.autoplay(frames, reduced));
        poll(frames.length ? core.POLL_EVERY : core.POLL_FIRST);
    }

    function start() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-naws-radar]'), boot);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(typeof window !== 'undefined' ? window : this);
```

- [ ] **Step 4:** In `includes/class-naws-shortcodes.php`, `enqueue_frontend_assets()`, nach dem Block für `naws-sparkline-boot` einfügen:

```php
        // [naws_radar]: the player. Loads images from this site only.
        wp_register_script( 'naws-radar',
            NAWS_PLUGIN_URL . 'assets/js/radar.js',
            [], NAWS_Helpers::asset_version( 'assets/js/radar.js' ), true );
```

- [ ] **Step 5:** `tests/test-asset-version.php`: in der Liste nach `'naws-sparkline-boot' => 'assets/js/sparkline-boot.js',` die Zeile `'naws-radar'          => 'assets/js/radar.js',` ergänzen.
- [ ] **Step 6:** `node --check assets/js/radar.js && php tests/test-radar-player.php && php tests/test-asset-version.php` — grün.
- [ ] **Step 7:** DoD, `git add assets/js/radar.js includes/class-naws-shortcodes.php tests/test-radar-player.php tests/test-asset-version.php && git commit -m "Radar: player script — frames, slider, pause, reduced motion, own site only"`

---

### Task 6: Shortcode-Ausgabe, Template, REST-Route, Hooks

**Files:**
- Create: `templates/radar.php`
- Modify: `includes/class-naws-radar.php` (Methoden anhängen), `includes/class-naws-labels.php` (`rd_*`), `includes/class-naws-shortcodes.php` (`TAGS`, `sc_radar()`), `xtx-integration-for-netatmo.php` (`NAWS_Radar::init();` nach `NAWS_Notifications::init();`), `assets/css/frontend.css` (Block am Ende), `tests/test-shortcode-atts.php` (17 → 18)
- Test: `tests/test-radar-render.php`

**Interfaces:**
- Consumes: Task 1–5; `NAWS_Forecast::resolve_location(): array` (`lat`, `lon` oder `error`).
- Produces: `NAWS_Radar::init(): void`; `normalise_atts( array $atts ): array` (`width` string, `height` int, `km` mixed, `source` string); `maybe_schedule( string $key, ?array $m, int $now ): void`; `payload( string $key, ?array $m, string $source, int $now ): array`; `render( array $atts, int $now = 0 ): string`; `register_routes(): void`; `rest_manifest( $request, int $now = 0 )`; `daily_cleanup( int $now = 0 ): int`; `privacy_text(): void`; Labels `rd_*`.

- [ ] **Step 1: Labels** — in `includes/class-naws-labels.php` vor dem abschließenden `}` des `switch` einen Block anhängen:

```php
        // ── [naws_radar] (since 2.2.0) ─────────────────────────────────
        case 'rd_aria':
            /* translators: %s: name of the place the radar is centred on */
            return __( 'Rain radar around %s', 'xtx-integration-for-netatmo' );
        case 'rd_forecast':
            return _x( 'Forecast', 'rain radar frame', 'xtx-integration-for-netatmo' );
        case 'rd_play':
            return __( 'Play or pause the rain radar', 'xtx-integration-for-netatmo' );
        case 'rd_time':
            return __( 'Time of the radar image', 'xtx-integration-for-netatmo' );
        case 'rd_preparing':
            return __( 'The rain radar is being prepared. It will appear in a moment.', 'xtx-integration-for-netatmo' );
        case 'rd_unavailable':
            return __( 'The rain radar is temporarily unavailable.', 'xtx-integration-for-netatmo' );
        case 'rd_as_of':
            /* translators: %s: time of the newest radar image */
            return __( 'As of %s', 'xtx-integration-for-netatmo' );
        case 'rd_light':
            return _x( 'light', 'rain intensity', 'xtx-integration-for-netatmo' );
        case 'rd_moderate':
            return _x( 'moderate', 'rain intensity', 'xtx-integration-for-netatmo' );
        case 'rd_heavy':
            return _x( 'heavy', 'rain intensity', 'xtx-integration-for-netatmo' );
        case 'rd_src_dwd':
            return __( 'Radar: Deutscher Wetterdienst', 'xtx-integration-for-netatmo' );
        case 'rd_src_osm':
            return __( 'Map: © OpenStreetMap contributors', 'xtx-integration-for-netatmo' );
        case 'rd_admin_off':
            return __( 'The rain radar is switched off. Admins can switch it on under Settings → Rain radar. Only you see this note.', 'xtx-integration-for-netatmo' );
        case 'rd_admin_noloc':
            return __( 'The rain radar needs the station coordinates or a forecast location. Only you see this note.', 'xtx-integration-for-netatmo' );
```

(„Weather data by RainViewer“ bleibt unübersetzt — RainViewer verlangt genau diesen Wortlaut.) Wenn `i18n-stubs.php` kein `_x` liefert: es liefert es (siehe Stubs-Liste: `__`, `_x`, …).

- [ ] **Step 2: Test schreiben** — `tests/test-radar-render.php`:

```php
<?php
/**
 * Tests fuer die Ausgabe von [naws_radar]: Zustaende, Planung des
 * Hintergrundlaufs, Nutzlast, REST-Antwort, Datenschutz (kein fremder Host
 * im HTML) und keine id-Attribute (zwei Radare auf einer Seite).
 *
 *   php tests/test-radar-render.php
 *
 * @package NAWS
 */
require_once __DIR__ . '/radar-stubs.php';
define( 'NAWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; return true; }
function rest_ensure_response( $d ) { return new WP_REST_Response( $d ); }
class WP_REST_Response { public $data; public $headers = []; public function __construct( $d ) { $this->data = $d; } public function header( $k, $v ) { $this->headers[ $k ] = $v; } }
$P = NAWS_PLUGIN_DIR . 'includes/';
require_once $P . 'class-naws-radar-geo.php';
require_once $P . 'class-naws-radar-source.php';
require_once $P . 'class-naws-radar-store.php';
require_once $P . 'class-naws-radar.php';

$now = gmmktime( 15, 26, 0, 10, 13, 2026 );
$key = NAWS_Radar_Store::key( 51.35, 12.37, 150, 'dwd' );

echo "\nnormalise_atts()\n" . str_repeat( '-', 74 ) . "\n";
$a = NAWS_Radar::normalise_atts( [] );
check( 'Vorgaben',                         $a, [ 'width' => '100%', 'height' => 420, 'km' => '150', 'source' => 'auto' ] );
check( 'Breite 600 wird 600px',            NAWS_Radar::normalise_atts( [ 'width' => '600' ] )['width'], '600px' );
check( 'Breite 80% bleibt',                NAWS_Radar::normalise_atts( [ 'width' => '80%' ] )['width'], '80%' );
check( 'Breite mit CSS-Unsinn wird 100%',  NAWS_Radar::normalise_atts( [ 'width' => '100%;background:red' ] )['width'], '100%' );
check( 'Hoehe 50 wird 200',                NAWS_Radar::normalise_atts( [ 'height' => '50' ] )['height'], 200 );
check( 'Hoehe 5000 wird 1200',             NAWS_Radar::normalise_atts( [ 'height' => '5000' ] )['height'], 1200 );
check( 'Quelle Unsinn wird auto',          NAWS_Radar::normalise_atts( [ 'source' => 'x' ] )['source'], 'auto' );

echo "\nZustaende\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 0;
check( 'aus: Besucher sehen nichts',       NAWS_Radar::render( [], $now ), '' );
$GLOBALS['is_admin'] = true;
check( 'aus: Admin sieht Hinweis',         str_contains( NAWS_Radar::render( [], $now ), 'naws-radar-hint' ), true );
$GLOBALS['is_admin'] = false;
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 1;
$GLOBALS['loc'] = [ 'error' => 'kein Ort' ];
check( 'ohne Ort: nichts',                 NAWS_Radar::render( [], $now ), '' );
$GLOBALS['loc'] = [ 'lat' => 51.35, 'lon' => 12.37, 'name' => 'Leipzig' ];

$html = NAWS_Radar::render( [], $now );
check( 'erster Aufruf: wird vorbereitet',  str_contains( $html, 'naws-radar--preparing' ), true );
check( 'Satz gemerkt',                     isset( NAWS_Radar_Store::sets()[ $key ] ), true );
check( 'Hintergrundlauf geplant',          wp_next_scheduled( NAWS_Radar::HOOK_REFRESH, [ $key ] ), $now );
check( 'Rahmenhoehe als Variable',         str_contains( $html, '--naws-radar-h:420px' ), true );

$m = [ 'v' => 1, 'source' => 'dwd', 'updated' => $now - 60, 'map' => [ 'tiles' => [ [ 'file' => 'map/10-547-341.png', 'left' => 0, 'top' => 0, 'size' => 166.86 ] ] ],
       'frames' => [ [ 't' => $now - 1800, 'forecast' => false, 'file' => 'frames/a.png' ], [ 't' => $now - 900, 'forecast' => false, 'file' => 'frames/b.png' ], [ 't' => $now + 900, 'forecast' => true, 'file' => 'frames/f.png' ] ],
       'retired' => [] ];
NAWS_Radar_Store::save_manifest( $key, $m );
$GLOBALS['scheduled'] = [];
$html = NAWS_Radar::render( [ 'height' => '300' ], $now );
check( 'frisch: bereit',                   str_contains( $html, 'naws-radar--ready' ), true );
check( 'frisch: kein neuer Lauf geplant',  $GLOBALS['scheduled'], [] );
check( 'Standbild "jetzt" ist b.png',      str_contains( $html, '/frames/b.png' ), true );
check( 'Uhrzeit von "jetzt"',              str_contains( $html, gmdate( 'H:i', $now - 900 ) ), true );
check( 'Karte im HTML',                    str_contains( $html, '/map/10-547-341.png' ), true );
check( 'Quellenangabe DWD + OSM',          str_contains( $html, 'Deutscher Wetterdienst' ) && str_contains( $html, 'OpenStreetMap' ), true );
check( 'Regler mit 3 Bildern',             str_contains( $html, 'max="2"' ), true );
check( 'kein id-Attribut',                 preg_match( '/\sid="/', $html ), 0 );
preg_match_all( '#https?://([^/"\s]+)#', $html, $hosts );
check( 'nur die eigene Site im HTML',      array_values( array_unique( $hosts[1] ) ), [ 'example.org' ] );
preg_match( '#<script type="application/json" data-naws="radar">(.*?)</script>#s', $html, $j );
$pl = json_decode( $j[1] ?? '', true );
check( 'JSON: 3 Bilder',                   count( $pl['frames'] ?? [] ), 3 );
check( 'JSON: Vorschau markiert',          $pl['frames'][2]['forecast'] ?? null, true );
check( 'JSON: REST-Adresse',               $pl['rest'] ?? '', 'https://example.org/wp-json/naws/v1/radar/' . $key );

NAWS_Radar_Store::save_manifest( $key, [ 'updated' => $now - 1000 ] + $m );
NAWS_Radar::render( [], $now );
check( 'aelter als 15 min: Lauf geplant',  wp_next_scheduled( NAWS_Radar::HOOK_REFRESH, [ $key ] ), $now );
NAWS_Radar_Store::save_manifest( $key, [ 'updated' => $now - 4 * HOUR_IN_SECONDS ] + $m );
$html = NAWS_Radar::render( [], $now );
check( 'aelter als 3 h: nicht verfuegbar', str_contains( $html, 'naws-radar--unavailable' ), true );
check( 'aelter als 3 h: keine Bilder im JSON', str_contains( $html, '"frames":[]' ), true );

echo "\nREST\n" . str_repeat( '-', 74 ) . "\n";
NAWS_Radar::register_routes();
$route = $GLOBALS['routes']['naws/v1/radar/(?P<key>[a-f0-9]{12})'] ?? null;
check( 'Route registriert, oeffentlich',   $route['permission_callback'] ?? null, '__return_true' );
NAWS_Radar_Store::save_manifest( $key, $m );
$r = NAWS_Radar::rest_manifest( [ 'key' => $key ], $now );
check( 'bekannter Satz: Nutzlast',         count( $r->data['frames'] ?? [] ), 3 );
check( 'nicht zwischenspeichern',          $r->headers['Cache-Control'] ?? '', 'no-store' );
check( 'unbekannter Satz: 404',            NAWS_Radar::rest_manifest( [ 'key' => 'ffffffffffff' ] )->get_error_data()['status'] ?? 0, 404 );
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 0;
check( 'Schalter aus: 404',                NAWS_Radar::rest_manifest( [ 'key' => $key ] ) instanceof WP_Error, true );
$GLOBALS['opts']['naws_settings']['radar_enabled'] = 1;

echo "\nTagesaufraeumen\n" . str_repeat( '-', 74 ) . "\n";
check( 'nach 8 Tagen: Satz weg',           NAWS_Radar::daily_cleanup( $now + 8 * DAY_IN_SECONDS ), 1 );
check( 'Ordner leer',                      NAWS_Radar_Store::manifest( $key ), null );

done();
```

- [ ] **Step 3:** `php tests/test-radar-render.php` — erwartet Fehler „Call to undefined method NAWS_Radar::normalise_atts()“.
- [ ] **Step 4: Methoden anhängen** — in `includes/class-naws-radar.php` vor der schließenden `}` der Klasse:

```php
    /** Hooks: the background run, the REST route, clean-up when switched off, privacy text. */
    public static function init(): void {
        add_action( self::HOOK_REFRESH, [ __CLASS__, 'refresh' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'admin_init', [ __CLASS__, 'privacy_text' ] );
        add_action(
            'update_option_naws_settings',
            static function ( $old, $new ) {
                if ( ! empty( $old['radar_enabled'] ) && empty( $new['radar_enabled'] ) ) {
                    NAWS_Radar_Store::clear_all();
                }
            },
            10,
            2
        );
    }

    /** @return array{width:string, height:int, km:mixed, source:string} */
    public static function normalise_atts( array $atts ): array {
        $w = trim( (string) ( $atts['width'] ?? '100%' ) );
        if ( ! preg_match( '/^\d{1,4}(px|%)?$/', $w ) ) {
            $w = '100%';
        } elseif ( ctype_digit( $w ) ) {
            $w .= 'px';
        }
        $h = is_numeric( $atts['height'] ?? null ) ? (int) $atts['height'] : 420;
        $s = (string) ( $atts['source'] ?? 'auto' );
        return [
            'width'  => $w,
            'height' => max( 200, min( 1200, $h ) ),
            'km'     => $atts['km'] ?? (string) NAWS_Radar_Geo::KM_DEFAULT,
            'source' => in_array( $s, [ 'auto', 'dwd', 'rainviewer' ], true ) ? $s : 'auto',
        ];
    }

    /** One background run when the set is missing or older than MAX_AGE. */
    public static function maybe_schedule( string $key, ?array $m, int $now ): void {
        if ( $m && $now - (int) ( $m['updated'] ?? 0 ) < self::MAX_AGE ) {
            return;
        }
        if ( wp_next_scheduled( self::HOOK_REFRESH, [ $key ] ) ) {
            return;
        }
        wp_schedule_single_event( $now, self::HOOK_REFRESH, [ $key ] );
    }

    /** What the player gets: own URLs and times only. */
    public static function payload( string $key, ?array $m, string $source, int $now ): array {
        $fmt     = (string) get_option( 'time_format', 'H:i' );
        $updated = (int) ( $m['updated'] ?? 0 );
        $stale   = ! $m || $now - $updated > self::STALE;
        $frames  = [];
        foreach ( $stale ? [] : (array) ( $m['frames'] ?? [] ) as $f ) {
            $e = [
                't'        => (int) $f['t'],
                'label'    => wp_date( $fmt, (int) $f['t'] ),
                'forecast' => ! empty( $f['forecast'] ),
            ];
            if ( isset( $f['file'] ) ) {
                $e['img'] = NAWS_Radar_Store::url( $key, $f['file'] );
            } else {
                $e['tiles'] = array_map(
                    static fn( $t ) => [ NAWS_Radar_Store::url( $key, $t['file'] ), $t['left'], $t['top'], $t['size'] ],
                    (array) ( $f['tiles'] ?? [] )
                );
            }
            $frames[] = $e;
        }
        return [
            'key'          => $key,
            'source'       => $source,
            'updated'      => $updated,
            'updatedLabel' => $updated ? wp_date( $fmt, $updated ) : '',
            'stale'        => $stale,
            'rest'         => rest_url( 'naws/v1/radar/' . $key ),
            'frames'       => $frames,
            'map'          => array_map(
                static fn( $t ) => [ NAWS_Radar_Store::url( $key, $t['file'] ), $t['left'], $t['top'], $t['size'] ],
                (array) ( $m['map']['tiles'] ?? [] )
            ),
        ];
    }

    private static function admin_hint( string $label ): string {
        return current_user_can( 'manage_options' )
            ? '<p class="naws-radar-hint">' . esc_html( naws_label( $label ) ) . '</p>'
            : '';
    }

    public static function render( array $atts, int $now = 0 ): string {
        $now = $now ? $now : time();
        if ( ! self::enabled() ) {
            return self::admin_hint( 'rd_admin_off' );
        }
        $loc = NAWS_Forecast::resolve_location();
        if ( ! isset( $loc['lat'], $loc['lon'] ) ) {
            return self::admin_hint( 'rd_admin_noloc' );
        }
        $a      = self::normalise_atts( $atts );
        $lat    = (float) $loc['lat'];
        $lon    = (float) $loc['lon'];
        $source = NAWS_Radar_Source::pick( $a['source'], $lat, $lon );
        $km     = NAWS_Radar_Geo::clamp_km( $a['km'], $source );
        $key    = NAWS_Radar_Store::key( $lat, $lon, $km, $source );
        NAWS_Radar_Store::remember(
            $key,
            [
                'lat'    => $lat,
                'lon'    => $lon,
                'km'     => $km,
                'source' => $source,
            ],
            $now
        );
        $m = NAWS_Radar_Store::manifest( $key );
        self::maybe_schedule( $key, $m, $now );

        $naws_rd        = self::payload( $key, $m, $source, $now );
        $naws_rd_state  = ! $m ? 'preparing' : ( $naws_rd['stale'] ? 'unavailable' : 'ready' );
        $naws_rd_width  = $a['width'];
        $naws_rd_height = $a['height'];
        $naws_rd_place  = (string) ( $loc['name'] ?? '' );
        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/radar.php';
        return (string) ob_get_clean();
    }

    public static function register_routes(): void {
        register_rest_route(
            'naws/v1',
            '/radar/(?P<key>[a-f0-9]{12})',
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'rest_manifest' ],
                // Public on purpose, unlike the key-protected routes of
                // NAWS_Rest_API: it answers only for sets this site has
                // shown on a page, and hands out times and URLs of images
                // on this site - what the page itself already contains.
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * @param WP_REST_Request|array $request
     * @param int                   $now     for tests; 0 = time()
     */
    public static function rest_manifest( $request, int $now = 0 ) {
        $key = (string) $request['key'];
        $set = NAWS_Radar_Store::sets()[ $key ] ?? null;
        if ( ! self::enabled() || ! is_array( $set ) ) {
            return new WP_Error( 'naws_radar_unknown', 'Unknown radar.', [ 'status' => 404 ] );
        }
        $now = $now ? $now : time();
        $m   = NAWS_Radar_Store::manifest( $key );
        self::maybe_schedule( $key, $m, $now );
        $res = rest_ensure_response( self::payload( $key, $m, (string) $set['source'], $now ) );
        $res->header( 'Cache-Control', 'no-store' );
        return $res;
    }

    /** From the daily cron run. */
    public static function daily_cleanup( int $now = 0 ): int {
        return NAWS_Radar_Store::cleanup( $now ? $now : time() );
    }

    /** Suggested text for the site's privacy policy page. */
    public static function privacy_text(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }
        wp_add_privacy_policy_content(
            'XTX Integration for Netatmo',
            '<p>' . esc_html__( 'If the rain radar is switched on, this website fetches radar images from the Deutscher Wetterdienst (maps.dwd.de) or RainViewer (rainviewer.com) and map tiles from OpenStreetMap (tile.openstreetmap.org) and stores them on its own server. Only the map section around the weather station and the time are sent. Visitors\' browsers load the images from this website only; no visitor data, IP address or cookie reaches these services.', 'xtx-integration-for-netatmo' ) . '</p>'
        );
    }
```

Test-Stubs brauchen `wp_add_privacy_policy_content` nicht (Funktion wird geprüft), `esc_html__` liefert `i18n-stubs.php`.

- [ ] **Step 5: Template** — `templates/radar.php`:

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_radar]
 *
 * @var array  $naws_rd        payload, see NAWS_Radar::payload()
 * @var string $naws_rd_state  ready | preparing | unavailable
 * @var string $naws_rd_width  CSS width
 * @var int    $naws_rd_height px
 * @var string $naws_rd_place  name of the location
 *
 * @package NAWS
 * @since   2.2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$naws_rd_frames = $naws_rd['frames'];
$naws_rd_now    = -1;
foreach ( $naws_rd_frames as $naws_rd_i => $naws_rd_f ) {
    if ( ! $naws_rd_f['forecast'] ) {
        $naws_rd_now = $naws_rd_i;
    }
}
$naws_rd_now   = $naws_rd_now < 0 ? count( $naws_rd_frames ) - 1 : $naws_rd_now;
$naws_rd_shown = $naws_rd_frames[ $naws_rd_now ] ?? null;
$naws_rd_pct   = static fn( $v ) => sprintf( '%.4F%%', $v / 1024 * 100 );
?>
<div class="naws-radar naws-radar--<?php echo esc_attr( $naws_rd_state ); ?>" data-naws-radar
     role="group" aria-label="<?php echo esc_attr( sprintf( naws_label( 'rd_aria' ), $naws_rd_place ) ); ?>"
     style="width:<?php echo esc_attr( $naws_rd_width ); ?>;--naws-radar-h:<?php echo absint( $naws_rd_height ); ?>px">
    <div class="naws-radar-view">
        <div class="naws-radar-stage">
            <div class="naws-radar-map" aria-hidden="true">
                <?php foreach ( $naws_rd['map'] as $naws_rd_t ) : ?>
                <img src="<?php echo esc_url( $naws_rd_t[0] ); ?>" alt="" loading="lazy" decoding="async"
                     style="left:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[1] ) ); ?>;top:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[2] ) ); ?>;width:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[3] ) ); ?>">
                <?php endforeach; ?>
            </div>
            <div class="naws-radar-layer" aria-hidden="true">
                <?php if ( $naws_rd_shown ) : ?>
                <div class="naws-radar-frame is-on">
                    <?php if ( isset( $naws_rd_shown['img'] ) ) : ?>
                    <img src="<?php echo esc_url( $naws_rd_shown['img'] ); ?>" alt="" decoding="async" style="left:0;top:0;width:100%">
                    <?php endif; ?>
                    <?php foreach ( $naws_rd_shown['tiles'] ?? [] as $naws_rd_t ) : ?>
                    <img src="<?php echo esc_url( $naws_rd_t[0] ); ?>" alt="" decoding="async"
                         style="left:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[1] ) ); ?>;top:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[2] ) ); ?>;width:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[3] ) ); ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="naws-radar-station" aria-hidden="true"></span>
        </div>
        <div class="naws-radar-time">
            <span class="naws-radar-clock"><?php echo esc_html( $naws_rd_shown['label'] ?? '' ); ?></span>
            <span class="naws-radar-fc"<?php echo ( $naws_rd_shown['forecast'] ?? false ) ? '' : ' hidden'; ?>><?php echo esc_html( naws_label( 'rd_forecast' ) ); ?></span>
        </div>
        <?php if ( 'ready' !== $naws_rd_state ) : ?>
        <p class="naws-radar-msg"><?php echo esc_html( naws_label( 'preparing' === $naws_rd_state ? 'rd_preparing' : 'rd_unavailable' ) ); ?></p>
        <?php endif; ?>
    </div>
    <div class="naws-radar-bar">
        <button type="button" class="naws-radar-btn naws-radar-play" aria-pressed="false"
                aria-label="<?php echo esc_attr( naws_label( 'rd_play' ) ); ?>"<?php disabled( count( $naws_rd_frames ) < 2 ); ?>>
            <span class="naws-radar-icon" aria-hidden="true"></span>
        </button>
        <input type="range" class="naws-radar-range" min="0" max="<?php echo absint( max( 0, count( $naws_rd_frames ) - 1 ) ); ?>"
               value="<?php echo absint( max( 0, $naws_rd_now ) ); ?>" step="1"
               aria-label="<?php echo esc_attr( naws_label( 'rd_time' ) ); ?>"<?php disabled( count( $naws_rd_frames ) < 2 ); ?>>
        <span class="naws-radar-legend" aria-hidden="true">
            <span><?php echo esc_html( naws_label( 'rd_light' ) ); ?></span><i></i><span><?php echo esc_html( naws_label( 'rd_heavy' ) ); ?></span>
        </span>
    </div>
    <p class="naws-radar-attr">
        <?php echo esc_html( 'dwd' === $naws_rd['source'] ? naws_label( 'rd_src_dwd' ) : 'Weather data by RainViewer' ); ?>
        · <?php echo esc_html( naws_label( 'rd_src_osm' ) ); ?>
        <?php if ( $naws_rd['updatedLabel'] && 'unavailable' === $naws_rd_state ) : ?>
        · <?php echo esc_html( sprintf( naws_label( 'rd_as_of' ), $naws_rd['updatedLabel'] ) ); ?>
        <?php endif; ?>
    </p>
    <?php
    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- type=application/json is not executable; pure data container
    echo '<script type="application/json" data-naws="radar">' . wp_json_encode( $naws_rd ) . '</script>';
    ?>
</div>
```

- [ ] **Step 5b: Stub für das Template** — WordPress' `disabled()` gibt das Attribut selbst aus (`echo`), das Template ruft es ohne `echo` auf. In `tests/radar-stubs.php` nach der Zeile `function absint( … )` ergänzen:

```php
function disabled( $d ) { if ( $d ) { echo ' disabled="disabled"'; } }
```

`naws_label()` kommt aus `i18n-stubs.php` (bindet `class-naws-labels.php` ein), dort stehen nach Step 1 die `rd_*`-Schlüssel.

- [ ] **Step 6: Shortcode** — in `includes/class-naws-shortcodes.php`:
  - in `TAGS` nach `'naws_forecast'       => 'sc_forecast',` die Zeile `'naws_radar'          => 'sc_radar',` einfügen;
  - den Kommentar „One wrapper for all seventeen“ auf „all eighteen“ ändern;
  - nach `sc_forecast()` die Methode:

```php
    // [naws_radar width="100%" height="420" km="150" source="auto"]
    // Animated rain radar around the station, since 2.2.0. Images come from
    // this site only; see NAWS_Radar.
    public function sc_radar( $atts ) {
        $atts = shortcode_atts(
            [
                'width'  => '100%',
                'height' => '420',
                'km'     => '150',
                'source' => 'auto',
            ],
            $atts,
            'naws_radar'
        );
        $html = NAWS_Radar::render( $atts );
        if ( false !== strpos( $html, 'data-naws-radar' ) ) {
            $this->enqueue_frontend_styles();
            wp_enqueue_script( 'naws-radar' );
        }
        return $html;
    }
```

- [ ] **Step 7:** `tests/test-shortcode-atts.php`: `check( 'alle 17 Shortcodes sind registriert', count( … ), 17 );` → `'alle 18 Shortcodes sind registriert'`, `18`; danach `check( 'Regenradar ist registriert', isset( $GLOBALS['naws_test_shortcodes']['naws_radar'] ), true );`.
- [ ] **Step 8:** `xtx-integration-for-netatmo.php`: nach `NAWS_Notifications::init();` die Zeile `NAWS_Radar::init();`.
- [ ] **Step 9: CSS** — ans Ende von `assets/css/frontend.css`:

```css
/* ==========================================================================
   [naws_radar] — rain radar (since 2.2.0)
   The square image (1024 units) covers the frame like object-fit: cover,
   centred on the station. Colours come from --naws-radar-* (Appearance →
   Rain radar); the literals are only fallbacks.
   ========================================================================== */
.naws-radar { max-width: 100%; margin: 0 0 1em; font-size: 14px; }
.naws-radar-view { position: relative; height: var(--naws-radar-h, 420px); overflow: hidden; border-radius: 10px; background: var(--naws-radar-bar-bg, #f4fafa); }
.naws-radar-stage { position: absolute; left: 50%; top: 50%; width: max(100%, var(--naws-radar-h, 420px)); aspect-ratio: 1 / 1; transform: translate(-50%, -50%); }
.naws-radar-map, .naws-radar-layer, .naws-radar-frame { position: absolute; inset: 0; }
.naws-radar-map { filter: grayscale(1) contrast(.85) brightness(1.1); opacity: .7; }
.naws-radar-layer { opacity: .85; }
.naws-radar-frame { visibility: hidden; }
.naws-radar-frame.is-on { visibility: visible; }
.naws-radar .naws-radar-map img, .naws-radar .naws-radar-layer img { position: absolute; max-width: none; height: auto; margin: 0; border: 0; box-shadow: none; }
.naws-radar-station { position: absolute; left: 50%; top: 50%; width: 12px; height: 12px; margin: -6px 0 0 -6px; border-radius: 50%; background: var(--naws-radar-station, #c0392b); box-shadow: 0 0 0 2px var(--naws-radar-time-text, #ffffff), 0 0 0 8px rgba(0, 0, 0, .12); }
.naws-radar-time { position: absolute; left: 10px; top: 10px; display: flex; gap: 6px; align-items: center; padding: 4px 10px; border-radius: 6px; background: var(--naws-radar-time-bg, #2d5252); color: var(--naws-radar-time-text, #ffffff); font-size: 1.15em; font-weight: 700; font-variant-numeric: tabular-nums; }
.naws-radar-fc { font-size: .75em; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 1px 6px; border-radius: 4px; background: var(--naws-radar-forecast, #dd9933); }
.naws-radar-fc[hidden] { display: none; }
.naws-radar-msg { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); max-width: 80%; margin: 0; padding: 10px 14px; border-radius: 8px; background: var(--naws-radar-bar-bg, #f4fafa); color: var(--naws-radar-time-bg, #2d5252); text-align: center; }
.naws-radar-msg[hidden] { display: none; }
.naws-radar-bar { display: flex; gap: 10px; align-items: center; margin-top: 8px; padding: 6px 8px; border-radius: 8px; background: var(--naws-radar-bar-bg, #f4fafa); }
.naws-radar-range { flex: 1 1 auto; min-width: 0; accent-color: var(--naws-radar-button, #427272); }
.naws-radar .naws-radar-btn { flex: 0 0 auto; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 50%; background: var(--naws-radar-button, #427272); color: var(--naws-radar-button-text, #ffffff); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.naws-radar .naws-radar-btn:hover, .naws-radar .naws-radar-btn:focus { background: var(--naws-radar-button, #427272); color: var(--naws-radar-button-text, #ffffff); filter: brightness(1.12); }
.naws-radar .naws-radar-btn:focus-visible { outline: 2px solid var(--naws-radar-time-bg, #2d5252); outline-offset: 2px; }
.naws-radar .naws-radar-btn:disabled { opacity: .45; cursor: default; filter: none; }
.naws-radar-icon { width: 0; height: 0; border-style: solid; border-width: 7px 0 7px 12px; border-color: transparent transparent transparent currentColor; margin-left: 3px; }
.naws-radar-btn.is-playing .naws-radar-icon { width: 12px; height: 14px; border-width: 0 4px; border-color: currentColor; border-style: solid; margin-left: 0; }
.naws-radar-legend { flex: 0 0 auto; display: inline-flex; gap: 6px; align-items: center; font-size: .8em; color: var(--naws-radar-time-bg, #2d5252); }
.naws-radar-legend i { display: inline-block; width: 56px; height: 8px; border-radius: 4px; background: linear-gradient(90deg, #9be7ff, #2f80ed, #f2c94c, #eb5757); }
.naws-radar-attr { margin: 4px 0 0; font-size: .75em; opacity: .75; }
.naws-radar-hint { padding: 8px 12px; border-left: 3px solid var(--naws-radar-forecast, #dd9933); background: var(--naws-radar-bar-bg, #f4fafa); }
@media (max-width: 480px) { .naws-radar-legend { display: none; } }
```

Die Legende zeigt bewusst eine allgemeine Skala (leicht → stark) ohne Zahlen: DWD und RainViewer färben unterschiedlich, die Zahlen wären bei einer der beiden falsch.

- [ ] **Step 10:** `php tests/test-radar-render.php && php tests/test-shortcode-atts.php && php tests/test-main-requires.php` — grün.
- [ ] **Step 11:** DoD, `git add templates/radar.php includes/class-naws-radar.php includes/class-naws-labels.php includes/class-naws-shortcodes.php xtx-integration-for-netatmo.php assets/css/frontend.css tests/radar-stubs.php tests/test-radar-render.php tests/test-shortcode-atts.php && git commit -m "Radar: [naws_radar] output, template, public manifest route, styles"`

---

### Task 7: Farben — Reiter „Regenradar“ im Erscheinungsbild

**Files:**
- Modify: `includes/class-naws-colors.php`, `admin/views/appearance.php`, `tests/test-appearance.php`
- Test: `tests/test-radar-colors.php`

**Interfaces:**
- Produces: `NAWS_Colors::RADAR_KEYS`; Gruppe `radar`; Reiter `radar`; CSS-Block `.naws-radar { --naws-radar-station: …; --naws-radar-time-bg: …; --naws-radar-time-text: …; --naws-radar-forecast: …; --naws-radar-bar-bg: …; --naws-radar-button: …; --naws-radar-button-text: …; }`.

- [ ] **Step 1: Test schreiben** — `tests/test-radar-colors.php`:

```php
<?php
/**
 * Tests fuer die Farben des Regenradars: Vorgaben, Gruppe, Reiter,
 * CSS-Block, sanitize, feste Farben in frontend.css nur als Rueckfall,
 * Reiter im Erscheinungsbild mit Vorschau.
 *
 *   php tests/test-radar-colors.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['naws_test_options']         = [];
$GLOBALS['naws_test_global_settings'] = [];
function get_option( $key, $default = false ) { return $GLOBALS['naws_test_options'][ $key ] ?? $default; }
function get_post_meta( $post_id, $key = '', $single = false ) { return ''; }
function post_type_exists( $type ) { return false; }
function wp_get_global_settings() { return $GLOBALS['naws_test_global_settings']; }
require_once __DIR__ . '/i18n-stubs.php';
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
$PLUGIN = dirname( __DIR__ ) . '/';
require_once $PLUGIN . 'includes/class-naws-fonts.php';
require_once $PLUGIN . 'includes/class-naws-colors.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
function saved( array $o ): void { $GLOBALS['naws_test_options']['naws_appearance'] = $o; NAWS_Colors::flush_cache(); NAWS_Fonts::flush_cache(); }

$KEYS = [
    'radar_station'     => [ '#c0392b', '--naws-radar-station' ],
    'radar_time_bg'     => [ '#2d5252', '--naws-radar-time-bg' ],
    'radar_time_text'   => [ '#ffffff', '--naws-radar-time-text' ],
    'radar_forecast'    => [ '#dd9933', '--naws-radar-forecast' ],
    'radar_bar_bg'      => [ '#f4fafa', '--naws-radar-bar-bg' ],
    'radar_button'      => [ '#427272', '--naws-radar-button' ],
    'radar_button_text' => [ '#ffffff', '--naws-radar-button-text' ],
];

echo "\nNAWS_Colors\n" . str_repeat( '-', 74 ) . "\n";
saved( [] );
$d = NAWS_Colors::get_defaults();
foreach ( $KEYS as $k => [ $def ] ) { check( "Vorgabe $k", $d[ $k ] ?? null, $def ); }
check( 'Gruppe radar',  NAWS_Colors::get_groups()['radar']['keys'] ?? null, array_keys( $KEYS ) );
check( 'Reiter radar',  array_key_exists( 'radar', NAWS_Colors::appearance_tabs() ), true );
$set = []; $i = 0;
foreach ( $KEYS as $k => $_ ) { $set[ $k ] = sprintf( '#%06x', 0x101010 * ( ++$i ) ); }
saved( $set );
preg_match( '/\.naws-radar \{(.*?)\}/s', NAWS_Colors::get_inline_css(), $m );
foreach ( $KEYS as $k => [ $_, $var ] ) {
    check( "$var traegt den Wert", (bool) preg_match( '/' . preg_quote( $var, '/' ) . ':\s*' . $set[ $k ] . ';/', $m[1] ?? '' ), true );
}
check( 'sanitize nimmt an',    array_intersect_key( NAWS_Colors::sanitize( $set ), $KEYS ) == $set, true );
check( 'sanitize verwirft',    isset( NAWS_Colors::sanitize( [ 'radar_station' => 'red;x' ] )['radar_station'] ), false );

echo "\nfrontend.css\n" . str_repeat( '-', 74 ) . "\n";
$css = (string) file_get_contents( $PLUGIN . 'assets/css/frontend.css' );
preg_match( '/\[naws_radar\] — rain radar.*$/s', $css, $m );
$block = $m[0] ?? '';
check( 'Block gefunden', $block !== '', true );
$block_wo_legend = preg_replace( '/\.naws-radar-legend i \{[^}]*\}/', '', $block );
preg_match_all( '/(?<!var\(--[a-z0-9-]{1,40}, )#[0-9a-fA-F]{3,8}\b/', $block_wo_legend, $hex );
check( 'Hex nur als Rueckfall (ausser Legendenverlauf)', $hex[0], [] );
check( 'Knopf hover/focus mit zwei Klassen', str_contains( $block, '.naws-radar .naws-radar-btn:hover, .naws-radar .naws-radar-btn:focus' ), true );

echo "\nErscheinungsbild\n" . str_repeat( '-', 74 ) . "\n";
$view = (string) file_get_contents( $PLUGIN . 'admin/views/appearance.php' );
check( 'Bereich radar',             str_contains( $view, 'data-pane="radar"' ), true );
check( 'Felder aus der Gruppe',     str_contains( $view, "\$groups['radar']['keys']" ), true );
check( 'Vorschau radar',            str_contains( $view, 'data-preview="radar"' ), true );
check( 'Vorschau-Rahmen mit Variablen', (bool) preg_match( '/id="naws-preview-radar"[^>]*style="[^"]*--naws-radar-station:/', $view ), true );
check( 'Skript kennt radar',        (bool) preg_match( "/group === 'radar'/", $view ), true );
foreach ( array_keys( $KEYS ) as $k ) { check( "Beschriftung $k", (bool) preg_match( "/'{$k}'\s*=>\s*__\(/", $view ), true ); }

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2:** `php tests/test-radar-colors.php` — erwartet FAILs (Vorgaben fehlen).
- [ ] **Step 3:** `includes/class-naws-colors.php`:
  - in `DEFAULTS` nach dem Forecast-Block:

```php
        // [naws_radar] (since 2.2.0)
        'radar_station'     => '#c0392b',
        'radar_time_bg'     => '#2d5252',
        'radar_time_text'   => '#ffffff',
        'radar_forecast'    => '#dd9933',
        'radar_bar_bg'      => '#f4fafa',
        'radar_button'      => '#427272',
        'radar_button_text' => '#ffffff',
```

  - nach `const FORECAST_KEYS = […];`:

```php
    /** [naws_radar]: station dot, time label, forecast tag, control bar (since 2.2.0). */
    const RADAR_KEYS = [ 'radar_station', 'radar_time_bg', 'radar_time_text', 'radar_forecast', 'radar_bar_bg', 'radar_button', 'radar_button_text' ];
```

  - in `appearance_tabs()` nach `'forecast' => …,`: `'radar'      => __( 'Rain radar', 'xtx-integration-for-netatmo' ),`
  - in `get_groups()` nach dem `'forecast'`-Eintrag:

```php
            'radar'      => [
                'label' => 'appearance_group_radar',
                'keys'  => self::RADAR_KEYS,
            ],
```

  - in `get_inline_css()` direkt vor `// [naws_sparkline]: its own rule, see sparkline_css().`:

```php
        // [naws_radar] (since 2.2.0): radar_time_bg -> --naws-radar-time-bg.
        $css .= ".naws-radar {\n";
        foreach ( self::RADAR_KEYS as $key ) {
            $css .= '  --naws-radar-' . str_replace( '_', '-', substr( $key, 6 ) ) . ": {$c[ $key ]};\n";
        }
        $css .= "}\n";
```

- [ ] **Step 4:** `admin/views/appearance.php`:
  - in `$color_labels` nach den Forecast-Einträgen:

```php
    // Rain radar
    'radar_station'     => __( 'Station dot', 'xtx-integration-for-netatmo' ),
    'radar_time_bg'     => __( 'Time label – background', 'xtx-integration-for-netatmo' ),
    'radar_time_text'   => __( 'Time label – text', 'xtx-integration-for-netatmo' ),
    'radar_forecast'    => __( 'Forecast tag', 'xtx-integration-for-netatmo' ),
    'radar_bar_bg'      => __( 'Control bar – background', 'xtx-integration-for-netatmo' ),
    'radar_button'      => __( 'Play button', 'xtx-integration-for-netatmo' ),
    'radar_button_text' => __( 'Play button – symbol', 'xtx-integration-for-netatmo' ),
```

  - nach dem schließenden `</div>` des Forecast-Bereichs (Kommentar „Tab 10: Forecast“) einen neuen Bereich:

```php
        <!-- ============================================================
             Tab 11: Rain radar (since 2.2.0)
             ============================================================ -->
        <div class="naws-appearance-pane<?php echo esc_attr( $naws_pane_class( 'radar' ) ); ?>" data-pane="radar">
            <p class="description"><?php esc_html_e( 'Colours for [naws_radar]: the station dot, the time label with its forecast tag and the control bar below the map. The map itself is always grey so that the rain stands out; the rain colours are those of the radar service.', 'xtx-integration-for-netatmo' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
                    <table class="form-table naws-color-table">
                        <tbody>
                        <?php foreach ( $groups['radar']['keys'] as $key ) : ?>
                            <tr>
                                <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                                <td>
                                    <input type="text"
                                           id="naws-<?php echo esc_attr( $key ); ?>"
                                           name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                           value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                           class="naws-color-picker"
                                           data-preview="radar"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label"><?php esc_html_e( 'Live preview — rain radar', 'xtx-integration-for-netatmo' ); ?></div>
                    <div id="naws-preview-radar" style="--naws-radar-station:<?php echo esc_attr( $colors['radar_station'] ); ?>;--naws-radar-time-bg:<?php echo esc_attr( $colors['radar_time_bg'] ); ?>;--naws-radar-time-text:<?php echo esc_attr( $colors['radar_time_text'] ); ?>;--naws-radar-forecast:<?php echo esc_attr( $colors['radar_forecast'] ); ?>;--naws-radar-bar-bg:<?php echo esc_attr( $colors['radar_bar_bg'] ); ?>;--naws-radar-button:<?php echo esc_attr( $colors['radar_button'] ); ?>;--naws-radar-button-text:<?php echo esc_attr( $colors['radar_button_text'] ); ?>;max-width:340px;">
                        <div style="position:relative;height:190px;border-radius:10px;overflow:hidden;background:repeating-linear-gradient(45deg,#e6eaea 0 12px,#eef1f1 12px 24px);">
                            <span style="position:absolute;left:30%;top:22%;width:46%;height:38%;border-radius:45%;background:radial-gradient(circle at 40% 50%,#2f80ed 0 22%,#9be7ff 23% 60%,transparent 61%);opacity:.85;"></span>
                            <span style="position:absolute;left:50%;top:50%;width:12px;height:12px;margin:-6px 0 0 -6px;border-radius:50%;background:var(--naws-radar-station);box-shadow:0 0 0 2px var(--naws-radar-time-text),0 0 0 8px rgba(0,0,0,.12);"></span>
                            <span style="position:absolute;left:10px;top:10px;display:flex;gap:6px;align-items:center;padding:4px 10px;border-radius:6px;background:var(--naws-radar-time-bg);color:var(--naws-radar-time-text);font-weight:700;">16:45 <span style="font-size:.7em;text-transform:uppercase;letter-spacing:.05em;padding:1px 6px;border-radius:4px;background:var(--naws-radar-forecast);"><?php echo esc_html( naws_label( 'rd_forecast' ) ); ?></span></span>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;margin-top:8px;padding:6px 8px;border-radius:8px;background:var(--naws-radar-bar-bg);">
                            <span style="width:32px;height:32px;border-radius:50%;background:var(--naws-radar-button);display:inline-flex;align-items:center;justify-content:center;"><span style="width:0;height:0;border-style:solid;border-width:6px 0 6px 10px;border-color:transparent transparent transparent var(--naws-radar-button-text);margin-left:3px;"></span></span>
                            <span style="flex:1;height:4px;border-radius:2px;background:var(--naws-radar-button);opacity:.5;"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
```

  - im Vorschau-Skript direkt nach dem `if (group === 'forecast') { … }`-Block:

```js
        // ── Rain radar: radar_time_bg -> --naws-radar-time-bg on the frame.
        if (group === 'radar') {
            var rd = document.getElementById('naws-preview-radar');
            if (rd) { rd.style.setProperty('--naws-radar-' + String(key).replace('radar_', '').replace(/_/g, '-'), val); }
        }
```

- [ ] **Step 5:** `tests/test-appearance.php`: `check( 'zehn Reiter, Basis zuerst', … [ …, 'sparkline', 'forecast', ] )` → Name `'elf Reiter, Basis zuerst'`, Liste um `'radar'` am Ende ergänzen.
- [ ] **Step 6:** `php tests/test-radar-colors.php && php tests/test-appearance.php && php tests/test-forecast-colors.php` — grün.
- [ ] **Step 7:** DoD, `git add includes/class-naws-colors.php admin/views/appearance.php tests/test-radar-colors.php tests/test-appearance.php && git commit -m "Radar: colours tab with live preview"`

---

### Task 8: Einstellungen, Leeren-Knopf, Cron, Deinstallation

**Files:**
- Create: `uninstall.php`
- Modify: `includes/class-naws-admin.php` (`sanitize_settings()`, `nawsAdmin.strings`), `includes/class-naws-ajax.php` (Handler), `admin/views/settings.php` (Block), `assets/js/admin.js` (Knopf), `includes/class-naws-cron.php` (`run_daily_summary()`, `deactivate()`), `tests/test-settings-merge.php`
- Test: `tests/test-radar-admin.php`

**Interfaces:**
- Consumes: `NAWS_Radar_Store::sets()`, `usage()`, `clear_all()`, `NAWS_Radar::daily_cleanup()`, `NAWS_Radar::HOOK_REFRESH`.
- Produces: Einstellung `naws_settings['radar_enabled']` (0/1); AJAX-Aktion `naws_radar_clear` (Nonce `naws_admin_nonce`, `manage_options`); Strings `radar_clear_confirm`, `radar_cleared`.

- [ ] **Step 1: Test schreiben** — `tests/test-radar-admin.php` (Quelltextprüfungen, wie `test-admin-js.php`):

```php
<?php
/**
 * Regenradar im Backend: Schalter, Leeren-Knopf, Tagesaufraeumen,
 * Deinstallation, Offenlegung. Prueft den Quelltext, der Ablauf selbst
 * steht in test-radar-store.php und test-radar-render.php.
 *
 *   php tests/test-radar-admin.php
 *
 * @package NAWS
 */
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$R     = dirname( __DIR__ ) . '/';
$admin = (string) file_get_contents( $R . 'includes/class-naws-admin.php' );
$ajax  = (string) file_get_contents( $R . 'includes/class-naws-ajax.php' );
$view  = (string) file_get_contents( $R . 'admin/views/settings.php' );
$js    = (string) file_get_contents( $R . 'assets/js/admin.js' );
$cron  = (string) file_get_contents( $R . 'includes/class-naws-cron.php' );
$un    = (string) @file_get_contents( $R . 'uninstall.php' );

echo "\nSchalter und Knopf\n" . str_repeat( '-', 74 ) . "\n";
check( 'Schalter wird sanitiert (versteckte 0)', str_contains( $admin, "\$clean['radar_enabled'] = empty( \$input['radar_enabled'] ) ? 0 : 1;" ), true );
check( 'Schalter im Formular mit versteckter 0',  str_contains( $view, '<input type="hidden" name="naws_settings[radar_enabled]" value="0">' ), true );
check( 'Schalter-Haken',                          str_contains( $view, 'name="naws_settings[radar_enabled]" value="1"' ), true );
check( 'Knopf im Block',                          str_contains( $view, 'id="naws-radar-clear"' ), true );
check( 'AJAX-Aktion registriert',                 str_contains( $ajax, "add_action( 'wp_ajax_naws_radar_clear'" ), true );
check( 'Handler prueft Nonce',                    (bool) preg_match( '/function radar_clear\(\).*?check_ajax_referer\( \'naws_admin_nonce\', \'nonce\' \)/s', $ajax ), true );
check( 'Handler prueft Recht',                    (bool) preg_match( '/function radar_clear\(\).*?current_user_can\( \'manage_options\' \)/s', $ajax ), true );
check( 'Handler leert',                           (bool) preg_match( '/function radar_clear\(\).*?NAWS_Radar_Store::clear_all\(\)/s', $ajax ), true );
check( 'JS ruft die Aktion',                      str_contains( $js, "action: 'naws_radar_clear'" ), true );
check( 'JS-Strings vorhanden',                    str_contains( $admin, "'radar_clear_confirm'" ) && str_contains( $admin, "'radar_cleared'" ), true );

echo "\nCron und Deinstallation\n" . str_repeat( '-', 74 ) . "\n";
check( 'Tagesaufraeumen nach der Aufbewahrung',   (bool) preg_match( '/\$this->run_retention\(\);.*?NAWS_Radar::daily_cleanup\(\)/s', $cron ), true );
check( 'deactivate raeumt geplante Laeufe',      str_contains( $cron, "wp_clear_scheduled_hook( 'naws_radar_refresh' )" ), true );
check( 'uninstall.php existiert',                 $un !== '', true );
check( 'uninstall prueft WP_UNINSTALL_PLUGIN',    str_contains( $un, "defined( 'WP_UNINSTALL_PLUGIN' )" ), true );
check( 'uninstall loescht nur den Radar-Ordner',  str_contains( $un, "'/naws-radar'" ), true );
check( 'uninstall loescht die Radar-Option',      str_contains( $un, "delete_option( 'naws_radar_sets' )" ), true );
check( 'uninstall laesst Messdaten in Ruhe',      preg_match( '/DROP TABLE|naws_readings|naws_settings/', $un ), 0 );

echo "\n" . str_repeat( '-', 74 ) . "\n";
printf( "%d bestanden, %d fehlgeschlagen\n\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
```

- [ ] **Step 2:** `php tests/test-radar-admin.php` — erwartet FAILs.
- [ ] **Step 3: Sanitieren** — `includes/class-naws-admin.php`, `sanitize_settings()`, direkt nach dem `wgt_sparklines`-Block:

```php
        // Rain radar (since 2.2.0): a hidden-zero checkbox. Off means no
        // request to DWD, RainViewer or OpenStreetMap at all.
        if ( $sent( 'radar_enabled' ) ) {
            $clean['radar_enabled'] = empty( $input['radar_enabled'] ) ? 0 : 1;
        }
```

  und in `wp_localize_script( 'naws-admin', 'nawsAdmin', [ … 'strings' => [` zwei Einträge:

```php
                'radar_clear_confirm' => __( 'Delete all stored rain radar images and map tiles? They are fetched again the next time a page shows the radar.', 'xtx-integration-for-netatmo' ),
                'radar_cleared'       => __( 'Rain radar storage cleared.', 'xtx-integration-for-netatmo' ),
```

- [ ] **Step 4: AJAX** — `includes/class-naws-ajax.php`: im Konstruktor neben `add_action( 'wp_ajax_naws_delete_readings', … )` die Zeile `add_action( 'wp_ajax_naws_radar_clear', [ $this, 'radar_clear' ] );` und nach `delete_readings()`:

```php
    /** Settings → Rain radar → "Clear storage" (since 2.2.0). */
    public function radar_clear() {
        check_ajax_referer( 'naws_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }
        NAWS_Radar_Store::clear_all();
        wp_send_json_success();
    }
```

- [ ] **Step 5: Block in den Einstellungen** — `admin/views/settings.php`, vor `</div><!-- /right column -->` (nach dem schließenden `</div>` des Wetter-Icon-Blocks):

```php
                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php esc_html_e( 'Rain radar', 'xtx-integration-for-netatmo' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php esc_html_e( 'Allow rain radar', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="hidden" name="naws_settings[radar_enabled]" value="0">
                                    <label><input type="checkbox" name="naws_settings[radar_enabled]" value="1" <?php checked( ! empty( $options['radar_enabled'] ) ); ?>>
                                        <?php esc_html_e( 'Show [naws_radar] and fetch its images', 'xtx-integration-for-netatmo' ); ?></label>
                                    <p class="description"><?php esc_html_e( 'When on, this server fetches radar images from the Deutscher Wetterdienst (in Germany) or RainViewer (elsewhere) and map tiles from OpenStreetMap, at most every 15 minutes and only after a page showed the radar. Visitors load the images from this site only. Switching off deletes the stored images.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Stored radars', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <?php $naws_rd_sets = class_exists( 'NAWS_Radar_Store' ) ? NAWS_Radar_Store::sets() : []; ?>
                                    <?php if ( ! $naws_rd_sets ) : ?>
                                        <p class="description"><?php esc_html_e( 'None yet.', 'xtx-integration-for-netatmo' ); ?></p>
                                    <?php else : ?>
                                        <ul class="naws-radar-sets">
                                        <?php foreach ( $naws_rd_sets as $naws_rd_key => $naws_rd_set ) : ?>
                                            <li><?php
                                                echo esc_html( sprintf(
                                                    /* translators: 1: width of the map section in km, 2: DWD or RainViewer, 3: date and time last shown, 4: size on disk */
                                                    __( '%1$d km, %2$s, last shown %3$s, %4$s', 'xtx-integration-for-netatmo' ),
                                                    (int) $naws_rd_set['km'],
                                                    'dwd' === $naws_rd_set['source'] ? 'DWD' : 'RainViewer',
                                                    wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $naws_rd_set['last_seen'] ),
                                                    size_format( NAWS_Radar_Store::usage( (string) $naws_rd_key ) )
                                                ) );
                                            ?></li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <p><button type="button" class="button" id="naws-radar-clear"><?php esc_html_e( 'Clear storage', 'xtx-integration-for-netatmo' ); ?></button>
                                        <span id="naws-radar-clear-result"></span></p>
                                    <p class="description"><?php esc_html_e( 'Every renewal deletes the old images; a radar no page has shown for 7 days is deleted with its map.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
```

(Die `id`-Attribute hier sind Admin-Seite, einmalig — kein Konflikt mit dem Frontend-Test.)

- [ ] **Step 6: JS** — `assets/js/admin.js`, nach dem Handler für `#naws-purge-btn` (innerhalb desselben `$(function(){ … })`-Blocks):

```js
        // Settings → Rain radar → Clear storage (since 2.2.0).
        $(document).on('click', '#naws-radar-clear', function() {
            if (!confirm(nawsAdmin.strings.radar_clear_confirm)) return;
            const btn    = $(this);
            const result = $('#naws-radar-clear-result');
            btn.prop('disabled', true);
            $.post(nawsAdmin.ajax_url, { action: 'naws_radar_clear', nonce: nawsAdmin.nonce }, function(resp) {
                btn.prop('disabled', false);
                if (resp.success) {
                    result.css('color', '#10b981').text(nawsAdmin.strings.radar_cleared);
                    $('.naws-radar-sets').remove();
                } else {
                    result.css('color', '#ef4444').text(resp.data?.message || nawsAdmin.strings.error);
                }
            }).fail(function() {
                btn.prop('disabled', false);
                result.css('color', '#ef4444').text(nawsAdmin.strings.request_failed);
            });
        });
```

- [ ] **Step 7: Cron** — `includes/class-naws-cron.php`:
  - in `run_daily_summary()` direkt nach `$this->run_retention();`:

```php
        // Rain radar: sets no page has shown for 7 days go, with their map.
        if ( class_exists( 'NAWS_Radar' ) ) {
            NAWS_Radar::daily_cleanup();
        }
```

  - in `deactivate()` nach den vorhandenen `wp_clear_scheduled_hook(…)`-Zeilen: `wp_clear_scheduled_hook( 'naws_radar_refresh' );`

- [ ] **Step 8: Deinstallation** — `uninstall.php`:

```php
<?php
/**
 * Runs when the plugin is deleted in WordPress (not on deactivation).
 *
 * Removes only what the rain radar stored: its folder in uploads and its
 * options. Weather data and every other setting stay, as before 2.2.0 -
 * deleting them is a decision the plugin does not make for the user.
 *
 * @package NAWS
 * @since   2.2.0
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wp_filesystem;
require_once ABSPATH . 'wp-admin/includes/file.php';
if ( WP_Filesystem() && $wp_filesystem ) {
    $naws_uploads = wp_upload_dir( null, false );
    $wp_filesystem->delete( untrailingslashit( $naws_uploads['basedir'] ) . '/naws-radar', true );
}
delete_option( 'naws_radar_sets' );
foreach ( [ 'dwd', 'rainviewer', 'osm' ] as $naws_source ) {
    delete_transient( 'naws_radar_backoff_' . $naws_source );
}
wp_clear_scheduled_hook( 'naws_radar_refresh' );
```

  `test-radar-admin.php` prüft auf `'/naws-radar'` — der String steht so in der Datei.

- [ ] **Step 9: Settings-Merge** — `tests/test-settings-merge.php`: in `$GLOBALS['naws_stored']` nach `'wgt_sparklines' => 1,` die Zeile `'radar_enabled'     => 1,`; nach dem Sparkline-Szenario:

```php
scenario(
    'Zugangsdaten speichern laesst den Radar-Schalter stehen',
    [ 'client_id' => 'newid' ],
    [ 'radar_enabled' ],
    [ 'client_id' => 'ENC:newid' ]
);
```

- [ ] **Step 10:** `php tests/test-radar-admin.php && php tests/test-settings-merge.php && php tests/test-admin-js.php` — grün; `node --check assets/js/admin.js`.
- [ ] **Step 11:** DoD (phpcs auch auf `uninstall.php`), `git add uninstall.php includes/class-naws-admin.php includes/class-naws-ajax.php admin/views/settings.php assets/js/admin.js includes/class-naws-cron.php tests/test-radar-admin.php tests/test-settings-merge.php && git commit -m "Radar: settings switch, clear button, daily clean-up, uninstall"`

---

### Task 9: Doku, Offenlegung, Kataloge

**Files:**
- Modify: `readme.txt`, `README.md`, `CHANGELOG.md`, `admin/views/shortcodes.php`, `admin/views/rest-api-docs.php`, `docs/site/website.de.json`, `docs/site/website.en.json`, `tests/test-readme-sections.php`, Kataloge.

- [ ] **Step 1:** `tests/test-readme-sections.php`: Host-Liste um `'maps.dwd.de', 'api.rainviewer.com', 'tile.openstreetmap.org'` erweitern. `php tests/test-readme-sections.php` — erwartet 3 FAILs.
- [ ] **Step 2: readme.txt**
  - Zeile `* **17 Shortcodes** – …` → `* **18 Shortcodes** – …` und in derselben Aufzählung „rain radar“ ergänzen;
  - in `= Shortcodes =` nach der `[naws_forecast]`-Zeile: ``* `[naws_radar]` – Animated rain radar around the station: Deutscher Wetterdienst in Germany (last hour plus two-hour nowcast), RainViewer elsewhere, on a grey OpenStreetMap map. Images are fetched by your server and served from your site (`width`, `height`, `km`, `source`). Must be switched on under Settings → Rain radar.``
  - in `== Privacy & External Services ==` vor dem Schlussabsatz drei Einträge:

```
= Deutscher Wetterdienst (maps.dwd.de) =

* **Purpose:** Radar images (measured and two-hour nowcast) for the rain radar `[naws_radar]` when the station is in Germany
* **Data sent:** The map section around your station (as a bounding box) and the time of each image. No visitor data: your server fetches the images and your site serves them.
* **When:** Only if "Allow rain radar" is on, after a page showed the radar, at most every 15 minutes
* **Terms and privacy:** [https://www.dwd.de/EN/service/copyright/copyright_node.html](https://www.dwd.de/EN/service/copyright/copyright_node.html), [https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html](https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html)

= RainViewer (api.rainviewer.com, tilecache.rainviewer.com) =

* **Purpose:** Radar images for the rain radar `[naws_radar]` outside Germany
* **Data sent:** The tile numbers of the map section around your station and the time of each image. No visitor data: your server fetches the images and your site serves them.
* **When:** Only if "Allow rain radar" is on, after a page showed the radar, at most every 15 minutes
* **Terms and privacy:** [https://www.rainviewer.com/terms.html](https://www.rainviewer.com/terms.html), [https://www.rainviewer.com/privacy.html](https://www.rainviewer.com/privacy.html)

= OpenStreetMap tiles (tile.openstreetmap.org) =

* **Purpose:** The background map of the rain radar `[naws_radar]`
* **Data sent:** The tile numbers of the map section around your station. Fetched once per map section and kept on your server.
* **When:** Only if "Allow rain radar" is on, the first time a page shows a radar with that map section
* **Terms and privacy:** [https://operations.osmfoundation.org/policies/tiles/](https://operations.osmfoundation.org/policies/tiles/), [https://osmfoundation.org/wiki/Privacy_Policy](https://osmfoundation.org/wiki/Privacy_Policy)
```

- [ ] **Step 3: Shortcode-Karte** — `admin/views/shortcodes.php` nach der Karte `[naws_forecast]`:

```php
        <div class="naws-sc-card">
            <h3><code>[naws_radar]</code></h3>
            <p><?php esc_html_e( 'Animated rain radar around the station: in Germany the Deutscher Wetterdienst with the last hour and a two-hour nowcast, elsewhere RainViewer with the last two hours. Your server fetches the images and your site serves them — visitors never contact a weather service. Switch it on under Settings → Rain radar.', 'xtx-integration-for-netatmo' ); ?></p>
            <div class="naws-copy-wrap"><pre>[naws_radar]</pre><button class="naws-copy-btn" data-copy='[naws_radar]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
            <table class="naws-attr-table" style="margin-top:10px">
                <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
                <tr><td><code>width</code></td><td><?php esc_html_e( 'Width on the page, in px or %', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">100%</span></td></tr>
                <tr><td><code>height</code></td><td><?php esc_html_e( 'Height in px (200–1200)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">420</span></td></tr>
                <tr><td><code>km</code></td><td><?php esc_html_e( 'Width of the map section in km (DWD 50–600, RainViewer 150–1500). Applies to the longer side of the frame.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">150</span></td></tr>
                <tr><td><code>source</code></td><td><?php esc_html_e( 'auto (DWD in Germany, RainViewer elsewhere), dwd or rainviewer', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">auto</span></td></tr>
            </table>
            <div class="naws-inline-examples">
                <div class="naws-inline-ex"><code>[naws_radar height="320" km="80"]</code> &rarr; <?php esc_html_e( 'a lower frame, closer around the station', 'xtx-integration-for-netatmo' ); ?></div>
            </div>
        </div>
```

- [ ] **Step 4: REST-Doku** — `admin/views/rest-api-docs.php`: in der Übersichtstabelle eine Zeile nach dem letzten Endpunkt:

```php
<tr><td><span class="naws-method">GET</span></td><td><code>/radar/{key}</code></td><td><?php esc_html_e( 'Rain radar frames of a [naws_radar] on this site (public, no key; used by the radar player).', 'xtx-integration-for-netatmo' ); ?></td></tr>
```

- [ ] **Step 5:** `README.md` Tabelle `## Shortcodes`: ``| `[naws_radar]` | Animated rain radar around the station (DWD in Germany with nowcast, RainViewer elsewhere), served from your own site |``. `CHANGELOG.md`: neuer Abschnitt `## [Unreleased]` vor dem neuesten Versionsblock mit `### Added` → `- [naws_radar]: animated rain radar around the station …` (zwei Sätze: Quellen; Datenschutz/Server-Abruf; Schalter, Aufräumen). `docs/site/website.{de,en}.json`: in `"vorhaben"` einen Eintrag `{ "id": "regenradar", "titel": "Regenradar" | "Rain radar", "satz": "Animiertes Regenradar um die Station – die Bilder liefert die eigene Website, kein Besucher spricht mit einem Wetterdienst." | "Animated rain radar around the station – your own site serves the images, no visitor talks to a weather service.", "ab": null, "bild": null }`, `"aktualisiert"` auf das Datum. JSON prüfen: `for f in docs/site/website.de.json docs/site/website.en.json; do php -r 'json_decode(file_get_contents($argv[1]), false, 512, JSON_THROW_ON_ERROR); echo "ok\n";' "$f"; done`.
- [ ] **Step 6: Kataloge** (Scratchpad = `$SCRATCH`):

```bash
php docs/i18n/catalog/makepot.php
php docs/i18n/catalog/merge_po.php de_DE
php docs/i18n/catalog/merge_po.php nb_NO
grep -B1 '^msgstr ""$' docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po | grep '^msgid' 
```

  Für jeden neuen String eine Übersetzung in `$SCRATCH/radar-de_DE.php` und `$SCRATCH/radar-nb_NO.php` (`return [ 'English msgid' => 'Übersetzung', "ctx\x04msgid" => '…' ];`) — de im Ton der vorhandenen Texte (z. B. „Regenradar“, „Vorschau“, „Stand %s“, „leicht“/„mäßig“/„stark“, „Zwischenspeicher leeren“), nb entsprechend („Nedbørsradar“, „Prognose“, „Per %s“, „lett“/„moderat“/„kraftig“, „Tøm mellomlager“). Dann:

```bash
php docs/i18n/catalog/fill_po.php de_DE "$SCRATCH/radar-de_DE.php"
php docs/i18n/catalog/fill_po.php nb_NO "$SCRATCH/radar-nb_NO.php"
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po languages/xtx-integration-for-netatmo-de_DE.mo
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po languages/xtx-integration-for-netatmo-nb_NO.mo
grep -c '^msgstr ""$' docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po   # erwartet 1
grep -c '^msgstr ""$' docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po   # erwartet 1
php tests/test-mo-files.php && php tests/test-makepot-comments.php
```

- [ ] **Step 7:** `php tests/test-readme-sections.php` grün, ganze Suite grün.
- [ ] **Step 8:** `git add readme.txt README.md CHANGELOG.md admin/views/shortcodes.php admin/views/rest-api-docs.php docs/site/website.de.json docs/site/website.en.json tests/test-readme-sections.php docs/i18n/catalog/ languages/ && git commit -m "Radar: docs, external services disclosure, translations"`

---

### Task 10: Abnahme auf dev (nicht auf www, nicht auf der Produktseite)

- [ ] **Step 1:** Ganze Suite, `vendor/bin/phpcs` auf alle angefassten PHP-Dateien, `node --check assets/js/radar.js assets/js/admin.js`.
- [ ] **Step 2:** Deploy-ZIP mit allen geänderten/neuen Dateien (LF-normalisiert, Muster `scratchpad/build-deploy-zip.php` aus früheren Sitzungen; neue Dateien: 4 Klassen, `templates/radar.php`, `assets/js/radar.js`, `uninstall.php`) auf dev per `novamira-dev` (`novamira/create-upload-link` + curl), Sicherung `wp-content/naws-backup-vor-radar-<ts>`, Opcache leeren. **Nie** PHP in `novamira-sandbox/` ablegen.
- [ ] **Step 3:** Auf dev: Einstellungen → „Regenradar erlauben“ an, Hilfsseite (Entwurf, nicht veröffentlicht) mit `[naws_radar]` und `[naws_radar height="300" km="80"]` anlegen; Seite aufrufen → „wird vorbereitet“; WP-Cron per `wp_remote_get( site_url('wp-cron.php') )` anstoßen oder `do_action( 'naws_radar_refresh', $key )` per execute-php; Seite neu laden → Animation.
- [ ] **Step 4:** Playwright Desktop 1280 × 900 und Handy 390 × 844: Screenshot, Abspielen/Pause, Regler ziehen, Knopf hovern (Farbe darf nicht rot werden), Konsole ohne Fehler, **Netzwerkprotokoll: nur Anfragen an dev.frank-neumann.de** (alles andere = Fehler).
- [ ] **Step 5:** `uploads/naws-radar/` auf dev ansehen: zwei Sätze, je ~13 Bilder + Karte. Nach 45 min erneut aufrufen → alte Bilder weg (≤ 26 Radarbilder je Satz).
- [ ] **Step 6:** Schalter aus → Ordner leer, Seite zeigt Besuchern nichts, Admin den Hinweis.
- [ ] **Step 7:** Hilfsseite löschen, Franks Abnahme abwarten. Merge/Push/Schnitt nur auf Franks Ansage.
```
