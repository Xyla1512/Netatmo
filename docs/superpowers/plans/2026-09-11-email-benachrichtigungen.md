# E-Mail-Benachrichtigungen — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nach jedem Netatmo-Abruf prüft das Plugin zehn Regeln (Batterie, Funk, WLAN, stille Basis, stilles Modul, gescheiterter Abruf, verfallene Zugangsdaten, Frost, Böe, Regen) und schickt bei jedem Zustandswechsel eine Klartext-Mail an eine Empfängerliste — verwaltet auf einer neuen Backend-Seite „Benachrichtigungen".

**Architecture:** Eine reine Klasse `NAWS_Notify_Rules` (Katalog, Bedingungen, Zustandsmaschine mit Beharrung und Entwarnung; Schnappschuss + Einstellungen + alter Zustand + Uhrzeit rein, neuer Zustand + Wechsel + Statuszeilen raus) und eine Anbindungsklasse `NAWS_Notifications` (Option, Schnappschuss aus der Datenbank, Lock, `wp_mail()`, Protokoll), eingehängt an die bestehende Aktion `naws_data_synced` und eine neue Aktion `naws_sync_failed` im Cron. Fünf Statusfelder, die Netatmo schon liefert, wandern als Schema 1.5 in `naws_modules`.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, kein Build-Schritt, keine neue Bibliothek, kein JavaScript. Tests sind eigenständige PHP-Dateien ohne Runner (`php tests/test-x.php`), wie die 44 vorhandenen. PHP 8.4 liegt im WinGet-Pfad (`command -v php` in Git Bash).

**Spec:** `docs/superpowers/specs/2026-09-11-email-benachrichtigungen-design.md`

**Branch:** `notifications` von `main` (`b5c5230` = Spec-Commit); am Ende `git merge --no-ff` nach `main`, wie `windrose`.

## Global Constraints

- Text-Domain überall `xtx-integration-for-netatmo`; jeder sichtbare Text durch `__()`/`_x()`/`_n()`/`esc_html_e()` oder `naws_label()`. Laufzeit-Schlüssel (`ntf_*`) gehören nach `includes/class-naws-labels.php`, sonst findet `makepot.php` sie nicht.
- Jedes `echo` in der View trägt `esc_html()`/`esc_attr()`/`esc_url()`/`esc_textarea()` sichtbar. Kein Custom-Wrapper, kein nacktes `echo $var`, kein `ob_start()`, kein `<script>`/`<style>`.
- Beide `admin_post_*`-Handler: erste Zeile `check_admin_referer( '<action>' )`, zweite `if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );`. `$_POST` wird sichtbar sanitiert (`sanitize_textarea_field()` für die Empfänger, `map_deep( wp_unslash( … ), 'sanitize_text_field' )` für die Regeln). Rückleitung nur per `wp_safe_redirect()` + `exit`.
- Die View liest `$_GET` nur in einer Bedingung, die `wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' )` direkt enthält; Werte durch `absint()`.
- Datenbank: `save_module()` bleibt eine `prepare()`-Abfrage; die fünf Statusfelder gehen über `$wpdb->update()` mit Format-Array (schreibt echtes `NULL`). Migration nach dem Muster in `maybe_migrate()`: `SHOW COLUMNS` per `get_col()`, `ALTER TABLE` als vollständiges Literal, Tabellenname über `esc_sql()`, `phpcs:ignore`-Kommentar mit Begründung. Keine neue SQL-Abfrage außerhalb von `class-naws-database.php`.
- Schwellen werden in Basiseinheiten gespeichert (°C, km/h, mm); nur `from_form()` und die View rechnen um. Faktoren wie in `NAWS_Helpers::format_value()`: °F = C×9/5+32; m/s = km/h÷3,6; mph = km/h×0,62137; kn = km/h×0,53996; in = mm÷25,4.
- Skalen (Netatmo-Doku): WLAN `bad` ab 86, `average` ab 71 (Entwarnung −15); Funk `low` ab 90, `medium` ab 80 (Entwarnung −10). Kleiner ist besser.
- Optionen `naws_notify_state`, `naws_notify_log`, `naws_notify_lock` ohne Autoload. Alle Regeln bei Auslieferung aus, Empfängerliste leer = Admin-Adresse.
- Version bleibt 1.9.13, `NAWS_DB_VERSION` wird `1.5`. Der Schnitt auf 2.0.0 (Header, `Stable tag`, readme-Changelog-Fenster, Upgrade Notice) ist nicht Teil dieses Plans; CHANGELOG.md bekommt den Eintrag `## [2.0.0]` schon hier.
- Neue PHP-Dateien beginnen mit `if ( ! defined( 'ABSPATH' ) ) { exit; }`; die View trägt die zwei `phpcs:disable`-Zeilen für Variablennamen wie `admin/views/appearance.php`.

## Definition of Done — gilt für jeden Task ohne Ausnahme

1. **PHPCS ohne Befund:** `vendor/bin/phpcs --report=full` (Git Bash). `.phpcs.xml.dist` ist das Gate für WordPress.org.
2. **Ein `phpcs:ignore`/`phpcs:disable` wird begründet oder gar nicht gesetzt.**
3. **Die ganze Suite ist grün:** `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done` — erwartet wird keine Ausgabe.
4. **`php -l` auf jeder angefassten PHP-Datei.**

Wer einen der vier Punkte nicht erfüllen kann, meldet das und umgeht ihn nicht.

## Dateien

| Datei | Verantwortung |
|---|---|
| `includes/class-naws-notify-rules.php` (neu) | Katalog, Vorgaben, Einheitenumrechnung, Bedingung je Regel, Zustandsmaschine (`evaluate()`) — rein, ohne WordPress |
| `includes/class-naws-notifications.php` (neu) | Option und `sanitize()`/`from_form()`, Schnappschuss, Hook-Callbacks mit Lock, `compose()`, `send()`, Testmail, Protokoll, Statuszeilen |
| `admin/views/notifications.php` (neu) | Die Seite: Empfänger, Regeln, Testmail, Zustand, Protokoll |
| `includes/class-naws-database.php` | Schema 1.5, Migration, Statusfelder in `save_module()` |
| `includes/class-naws-cron.php` | `base_interval()` öffentlich, Aktion `naws_sync_failed` an drei Stellen |
| `includes/class-naws-admin.php` | Untermenü, `page_notifications()`, zwei Handler |
| `admin/views/modules.php` | Batterie aus `battery_percent` |
| `includes/class-naws-labels.php` | `ntf_*`-Labels |
| `xtx-integration-for-netatmo.php` | zwei `naws_require()`, `NAWS_Notifications::init()`, `NAWS_DB_VERSION` |
| `tests/test-database-module-status.php`, `tests/test-notify-rules.php`, `tests/test-notifications-sanitize.php`, `tests/test-notifications-mail.php`, `tests/test-notify-structure.php` (neu) | Datenbank, Regeln, Säuberung, Mailtext, Struktur/Review-Regeln |
| `CHANGELOG.md`, `readme.txt`, `docs/site/website.{de,en}.json`, `languages/*`, `docs/i18n/catalog/*.po` | Doku, Kataloge |

---

### Task 0: Zweig anlegen

- [ ] **Step 1:** `cd "C:/Users/xyla1/Documents/GitHub/Netatmo" && git checkout -b notifications main && git log --oneline -1` — erwartet `b5c5230 Spec: e-mail notifications for outages and weather events (2.0.0)`.

---

### Task 1: Schema 1.5 — fünf Statusfelder in `naws_modules`, Batterie aus Prozent

**Files:**
- Modify: `includes/class-naws-database.php:85-103` (CREATE), `:166-216` (`maybe_migrate()`), `:229-290` (`save_module()`)
- Modify: `xtx-integration-for-netatmo.php:27` (`NAWS_DB_VERSION`)
- Modify: `admin/views/modules.php:43-46`
- Test: `tests/test-database-module-status.php`

**Interfaces:**
- Produces: Spalten `battery_percent`, `wifi_status`, `reachable`, `last_status_store`, `last_message` in `naws_modules`; `NAWS_Database::save_module( array $data )` schreibt sie; `NAWS_Database::status_fields( array $data ): array` (öffentlich, statisch, rein) liefert `[ 'battery_percent' => ?int, 'wifi_status' => ?int, 'reachable' => ?int, 'last_status_store' => ?int, 'last_message' => ?int ]` aus einer Netatmo-Modulantwort.

- [ ] **Step 1: Test schreiben**

```php
<?php
/**
 * Tests fuer die fuenf Statusfelder, die Netatmo je Modul liefert und die
 * seit Schema 1.5 in naws_modules liegen: battery_percent, wifi_status,
 * reachable, last_status_store, last_message. Die Benachrichtigungen
 * lesen sie; die Modulseite zeigt die Batterie daraus.
 *
 *   php tests/test-database-module-status.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'NAWS_TABLE_MODULES',  'naws_modules' );
define( 'NAWS_TABLE_READINGS', 'naws_readings' );
define( 'NAWS_TABLE_DAILY',    'naws_daily_summary' );
define( 'NAWS_DB_VERSION',     '1.5' );

$GLOBALS['naws_test_transients'] = [];
$GLOBALS['naws_test_options']    = [];
function get_transient( $k )               { return $GLOBALS['naws_test_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['naws_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k )            { unset( $GLOBALS['naws_test_transients'][ $k ] ); return true; }
function get_option( $k, $d = false )      { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ){ $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function sanitize_text_field( $s )         { return is_string( $s ) ? trim( $s ) : $s; }
function wp_json_encode( $v )              { return json_encode( $v ); }
function wp_parse_args( $a, $d )           { return array_merge( $d, (array) $a ); }
function wp_date( $f, $t = null )          { return gmdate( $f, $t ?? time() ); }
function wp_cache_flush_group( $g )        { return true; }
function esc_sql( $s )                     { return $s; }
function dbDelta( $sql )                   { $GLOBALS['naws_test_ddl'][] = $sql; return []; }
class NAWS_Logger {
    public static $errors = [];
    public static function error( $ctx, $msg, $extra = [] ) { self::$errors[] = $msg; }
    public static function warning( ...$a ) {}
    public static function info( ...$a ) {}
}
class NAWS_Cron { public static function schedule() {} }
class WP_Error { public function __construct( ...$a ) {} }

/** Ein wpdb, der Upsert und update() festhaelt und SHOW COLUMNS beantwortet. */
class NAWS_Test_WPDB {
    public $prefix     = 'wp_';
    public $last_error = '';
    public $queries    = [];   // [ sql, params ] je prepare()
    public $raw        = [];   // jede SQL an query()
    public $updates    = [];   // [ table, data, where, format ] je update()
    public $columns    = [];   // Antwort auf SHOW COLUMNS (get_col)
    public function prepare( $q, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
        $this->queries[] = [ $q, $args ];
        return $q;
    }
    public function get_charset_collate() { return ''; }
    // SHOW COLUMNS … LIKE %s fragt nur nach is_active: vorhanden, wenn es in $columns steht.
    public function get_results( $q, $out = null ) { return ( str_contains( $q, 'SHOW COLUMNS' ) && in_array( 'is_active', $this->columns, true ) ) ? [ 1 ] : []; }
    public function get_var( $q ) { return 'x'; }          // latitude vorhanden: kein ALTER dafuer
    public function get_col( $q ) { return $this->columns; }
    public function query( $q ) { $this->raw[] = $q; return 1; }
    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        $this->updates[] = [ $table, $data, $where, $format ];
        return 1;
    }
}
$wpdb = new NAWS_Test_WPDB();
require_once dirname( __DIR__ ) . '/includes/class-naws-database.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}

$base = [ '_id' => '70:ee:50:a9:5a:08', 'station_id' => '70:ee:50:a9:5a:08', 'type' => 'NAMain', 'station_name' => 'HOME (Basis)',
          'wifi_status' => 60, 'reachable' => true, 'last_status_store' => 1789144822, 'firmware' => 300 ];
$gast = [ '_id' => '03:00:00:0d:aa:ca', 'station_id' => '70:ee:50:a9:5a:08', 'type' => 'NAModule4', 'module_name' => 'Gast',
          'battery_percent' => 23, 'battery_vp' => 4612, 'rf_status' => 74, 'reachable' => true,
          'last_seen' => 1789144813, 'last_message' => 1789144820, 'firmware' => 53 ];

echo "\nstatus_fields(): was aus der Netatmo-Antwort wird\n" . str_repeat( '-', 74 ) . "\n";
$f = NAWS_Database::status_fields( $gast );
check( 'Modul: battery_percent, rf bleibt draussen, wifi null',
    [ $f['battery_percent'], $f['wifi_status'], $f['reachable'], $f['last_status_store'], $f['last_message'] ],
    [ 23, null, 1, null, 1789144820 ] );
$f = NAWS_Database::status_fields( $base );
check( 'Basis: wifi_status und last_status_store, kein battery_percent',
    [ $f['battery_percent'], $f['wifi_status'], $f['reachable'], $f['last_status_store'], $f['last_message'] ],
    [ null, 60, 1, 1789144822, null ] );
check( 'reachable false wird 0',            NAWS_Database::status_fields( [ 'reachable' => false ] )['reachable'], 0 );
check( 'fehlendes reachable bleibt null',   NAWS_Database::status_fields( [] )['reachable'], null );
check( 'battery_percent wird auf 0..100 geklemmt', [ NAWS_Database::status_fields( [ 'type' => 'NAModule1', 'battery_percent' => 140 ] )['battery_percent'], NAWS_Database::status_fields( [ 'type' => 'NAModule1', 'battery_percent' => -3 ] )['battery_percent'] ], [ 100, 0 ] );
check( 'wifi_status nur an der Basis',      NAWS_Database::status_fields( [ 'type' => 'NAModule1', 'wifi_status' => 60 ] )['wifi_status'], null );

echo "\nsave_module(): Upsert unveraendert, Statusfelder per update()\n" . str_repeat( '-', 74 ) . "\n";
$wpdb->queries = []; $wpdb->updates = [];
NAWS_Database::save_module( $gast );
[ $sql, $params ] = $wpdb->queries[0] ?? [ '', [] ];
check( 'Upsert nennt is_active nicht im UPDATE-Teil', preg_match( '/ON DUPLICATE KEY UPDATE(?:(?!is_active).)*$/s', $sql ) === 1, true );
check( 'ein update() auf naws_modules',              [ count( $wpdb->updates ), $wpdb->updates[0][0] ?? '' ], [ 1, 'wp_naws_modules' ] );
check( 'update(): die fuenf Felder, NULL bleibt NULL', $wpdb->updates[0][1] ?? [], [ 'battery_percent' => 23, 'wifi_status' => null, 'reachable' => 1, 'last_status_store' => null, 'last_message' => 1789144820 ] );
check( 'update(): WHERE module_id',                   $wpdb->updates[0][2] ?? [], [ 'module_id' => '03:00:00:0d:aa:ca' ] );
check( 'update(): Formate %d fuer alle fuenf',        $wpdb->updates[0][3] ?? [], [ '%d', '%d', '%d', '%d', '%d' ] );

echo "\ninstall()/maybe_migrate(): Spalten anlegen, vorhandene in Ruhe lassen\n" . str_repeat( '-', 74 ) . "\n";
$wpdb->raw = []; $wpdb->columns = [ 'id', 'module_id', 'rf_status', 'is_active' ];
NAWS_Database::install();
$ddl = implode( "\n", $GLOBALS['naws_test_ddl'] ?? [] );
check( 'CREATE TABLE nennt alle fuenf Spalten',
    [ str_contains( $ddl, 'battery_percent' ), str_contains( $ddl, 'wifi_status' ), str_contains( $ddl, 'reachable' ), str_contains( $ddl, 'last_status_store' ), str_contains( $ddl, 'last_message' ) ],
    [ true, true, true, true, true ] );
$alters = array_values( array_filter( $wpdb->raw, fn( $q ) => str_contains( $q, 'naws_modules' ) && str_contains( $q, 'ADD COLUMN' ) ) );
check( 'fuenf ALTER auf naws_modules, wenn alle fehlen', count( $alters ), 5 );
check( 'ALTER-Reihenfolge nach rf_status', str_contains( $alters[0] ?? '', 'ADD COLUMN battery_percent TINYINT UNSIGNED DEFAULT NULL AFTER rf_status' ), true );
check( 'db_version wird 1.5',               $GLOBALS['naws_test_options']['naws_db_version'] ?? '', '1.5' );
$wpdb->raw = []; $wpdb->columns = [ 'id', 'module_id', 'rf_status', 'battery_percent', 'wifi_status', 'reachable', 'last_status_store', 'last_message', 'is_active' ];
NAWS_Database::install();
$alters = array_filter( $wpdb->raw, fn( $q ) => str_contains( $q, 'naws_modules' ) && str_contains( $q, 'ADD COLUMN' ) );
check( 'kein ALTER, wenn alle da sind',     count( $alters ), 0 );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-database-module-status.php`
Expected: `Call to undefined method NAWS_Database::status_fields()` (Fatal) oder FAILs.

- [ ] **Step 3: Datenbankklasse ändern**

In `includes/class-naws-database.php`, CREATE-Definition (Zeile ~94, nach `rf_status     INT          DEFAULT NULL,`) einfügen:

```php
            battery_percent   TINYINT UNSIGNED DEFAULT NULL,
            wifi_status       INT          DEFAULT NULL,
            reachable         TINYINT(1)   DEFAULT NULL,
            last_status_store BIGINT       DEFAULT NULL,
            last_message      BIGINT       DEFAULT NULL,
```

In `maybe_migrate()` ans Ende (nach dem `is_active`-Block, vor der schließenden Klammer der Methode):

```php
        // v1.5: the five status fields Netatmo sends with every module. Every
        // ALTER is a complete SQL literal; the table name comes from the
        // constant plus prefix. Read by the notifications, shown on Modules.
        $mcols = $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $t_mod ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- schema check; table name from constant+prefix
        $mcols = is_array( $mcols ) ? $mcols : [];
        $v15   = [
            'battery_percent'   => 'ADD COLUMN battery_percent TINYINT UNSIGNED DEFAULT NULL AFTER rf_status',
            'wifi_status'       => 'ADD COLUMN wifi_status INT DEFAULT NULL AFTER battery_percent',
            'reachable'         => 'ADD COLUMN reachable TINYINT(1) DEFAULT NULL AFTER wifi_status',
            'last_status_store' => 'ADD COLUMN last_status_store BIGINT DEFAULT NULL AFTER reachable',
            'last_message'      => 'ADD COLUMN last_message BIGINT DEFAULT NULL AFTER last_status_store',
        ];
        foreach ( $v15 as $col => $ddl ) {
            if ( ! in_array( $col, $mcols, true ) ) {
                $wpdb->query( 'ALTER TABLE `' . esc_sql( $t_mod ) . '` ' . $ddl ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- DDL from the literal list above; table name from constant+prefix
            }
        }
```

Vor `save_module()` die neue Methode:

```php
    /**
     * The five status fields Netatmo sends with a module, as columns.
     *
     * NULL where the answer has no such field: a base station has no
     * battery, a module no Wi-Fi. reachable arrives as a boolean and is
     * stored as 0/1; battery_percent is clamped to 0..100.
     *
     * @param  array $data One device or module from getstationsdata.
     * @return array{battery_percent:?int,wifi_status:?int,reachable:?int,last_status_store:?int,last_message:?int}
     */
    public static function status_fields( array $data ): array {
        $is_main = ( $data['type'] ?? 'NAMain' ) === 'NAMain';
        return [
            'battery_percent'   => ( ! $is_main && isset( $data['battery_percent'] ) ) ? max( 0, min( 100, intval( $data['battery_percent'] ) ) ) : null,
            'wifi_status'       => ( $is_main && isset( $data['wifi_status'] ) ) ? intval( $data['wifi_status'] ) : null,
            'reachable'         => isset( $data['reachable'] ) ? ( $data['reachable'] ? 1 : 0 ) : null,
            'last_status_store' => isset( $data['last_status_store'] ) ? intval( $data['last_status_store'] ) : null,
            'last_message'      => isset( $data['last_message'] ) ? intval( $data['last_message'] ) : null,
        ];
    }
```

In `save_module()` nach dem `if ( $result === false ) { … }`-Block und vor `return true;`:

```php
        // Status fields as a second, small write: wpdb::update() writes a real
        // NULL where prepare()'s %d would write 0 — and 0 would mean
        // "unreachable" for reachable.
        $status = self::status_fields( $data );
        $wpdb->update( $table, $status, [ 'module_id' => $module_id ], [ '%d', '%d', '%d', '%d', '%d' ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- module upsert, no caching applicable
```

In `xtx-integration-for-netatmo.php:27`: `define( 'NAWS_DB_VERSION',     '1.5' );`

- [ ] **Step 4: Test grün, Suite grün**

Run: `php tests/test-database-module-status.php && php tests/test-database-active-modules.php && php tests/test-module-cache.php`
Expected: `… 0 fehlgeschlagen` dreimal. Dann `php -l includes/class-naws-database.php` und `vendor/bin/phpcs includes/class-naws-database.php`.

- [ ] **Step 5: Modulseite — Batterie aus Prozent**

`admin/views/modules.php:43-46` ersetzen durch:

```php
                    $batt_vp    = $m['battery_vp'] ?? null;
                    $batt_api   = $m['battery_percent'] ?? null;
                    // Netatmo's own percentage since schema 1.5; the voltage
                    // estimate stays as the fallback for rows not synced since.
                    $batt_pct   = ( $m['module_type'] !== 'NAMain' && $batt_api !== null && $batt_api !== '' )
                                ? max( 0, min( 100, intval( $batt_api ) ) )
                                : ( ( $batt_vp && $m['module_type'] !== 'NAMain' )
                                    ? max( 0, min( 100, round( ( $batt_vp - 3500 ) / 2500 * 100 ) ) )
                                    : null );
```

Run: `php -l admin/views/modules.php && vendor/bin/phpcs admin/views/modules.php`

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-database.php xtx-integration-for-netatmo.php admin/views/modules.php tests/test-database-module-status.php
git commit -m "Schema 1.5: keep the five status fields Netatmo sends with every module"
```

---

### Task 2: `NAWS_Notify_Rules` — Katalog, Vorgaben, Einheiten, Bedingung je Regel

**Files:**
- Create: `includes/class-naws-notify-rules.php`
- Modify: `xtx-integration-for-netatmo.php:55` (eine `naws_require()`-Zeile nach `class-naws-windrose.php`)
- Test: `tests/test-notify-rules.php`

**Interfaces:**
- Produces: `NAWS_Notify_Rules::MODULE_TYPES`; `catalog(): array` (Kennung → `group, scope, types, field|reading, param, kind, default, min, max, levels, hold_on, hold_off, clears`); `defaults(): array` (`[ 'recipients' => [], 'rules' => [ id => [ 'enabled' => 0, <param> => default ] ] ]`); `to_base( string $kind, float $v, array $units ): float`; `to_display( string $kind, float $v, array $units ): float`; `unit_label( string $kind, array $units ): string`; `condition( string $rule, array $module, array $cfg, bool $active, int $now, int $stale_after ): ?bool`; `value_of( string $rule, array $module ): int|float|null`.
- `$units` ist immer `[ 'temperature_unit' => 'C'|'F', 'wind_unit' => 'kmh'|'ms'|'mph'|'kn', 'rain_unit' => 'mm'|'in' ]`.
- Ein Modul im Schnappschuss: `[ 'module_id', 'station_id', 'module_name', 'module_type', 'battery_percent', 'rf_status', 'wifi_status', 'reachable', 'last_status_store', 'last_seen', 'last_message', 'readings' => [ '<Parameter>' => [ 'value' => float, 'at' => int ] ] ]`.

- [ ] **Step 1: Test schreiben (Teil 1 der Datei; Task 3 fügt Teil 2 an der markierten Stelle ein)**

```php
<?php
/**
 * Tests fuer NAWS_Notify_Rules: Katalog, Einheiten, Bedingungen und die
 * Zustandsmaschine der E-Mail-Benachrichtigungen. Alles rein, ohne
 * WordPress; die Uhrzeit wird hineingereicht.
 *
 *   php tests/test-notify-rules.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-naws-notify-rules.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$NOW = 1789145173;
$U   = [ 'temperature_unit' => 'C', 'wind_unit' => 'kmh', 'rain_unit' => 'mm' ];
function mod( string $type, array $extra = [] ): array {
    return $extra + [ 'module_id' => 'id-' . $type, 'station_id' => 'base', 'module_name' => 'M ' . $type, 'module_type' => $type,
        'battery_percent' => null, 'rf_status' => null, 'wifi_status' => null, 'reachable' => null,
        'last_status_store' => null, 'last_seen' => null, 'last_message' => null, 'readings' => [] ];
}

echo "\nKatalog\n" . str_repeat( '-', 74 ) . "\n";
$cat = NAWS_Notify_Rules::catalog();
check( 'zehn Regeln in dieser Reihenfolge', array_keys( $cat ), [ 'battery', 'rf', 'wifi', 'station_silent', 'module_silent', 'sync_failed', 'auth_required', 'frost', 'gust', 'rain' ] );
$complete = true;
foreach ( $cat as $id => $d ) {
    foreach ( [ 'group', 'scope', 'types', 'param', 'kind', 'default', 'hold_on', 'hold_off', 'clears' ] as $k ) {
        if ( ! array_key_exists( $k, $d ) ) { $complete = false; echo "    fehlt: $id.$k\n"; }
    }
}
check( 'jede Regel hat alle Schluessel', $complete, true );
check( 'Gruppen',        array_map( fn( $d ) => $d['group'],    $cat ), [ 'battery' => 'station', 'rf' => 'station', 'wifi' => 'station', 'station_silent' => 'station', 'module_silent' => 'station', 'sync_failed' => 'station', 'auth_required' => 'station', 'frost' => 'weather', 'gust' => 'weather', 'rain' => 'weather' ] );
check( 'Scopes',         array_map( fn( $d ) => $d['scope'],    $cat ), [ 'battery' => 'module', 'rf' => 'module', 'wifi' => 'station', 'station_silent' => 'station', 'module_silent' => 'module', 'sync_failed' => 'site', 'auth_required' => 'site', 'frost' => 'module', 'gust' => 'module', 'rain' => 'module' ] );
check( 'Vorgaben',       array_map( fn( $d ) => $d['default'],  $cat ), [ 'battery' => 20, 'rf' => 'low', 'wifi' => 'bad', 'station_silent' => 60, 'module_silent' => 60, 'sync_failed' => null, 'auth_required' => null, 'frost' => 0.0, 'gust' => 60.0, 'rain' => 20.0 ] );
check( 'Beharrungen an', array_map( fn( $d ) => $d['hold_on'],  $cat ), [ 'battery' => 0, 'rf' => 1800, 'wifi' => 1800, 'station_silent' => 0, 'module_silent' => 0, 'sync_failed' => 0, 'auth_required' => 0, 'frost' => 0, 'gust' => 0, 'rain' => 0 ] );
check( 'Beharrungen aus',array_map( fn( $d ) => $d['hold_off'], $cat ), [ 'battery' => 0, 'rf' => 1800, 'wifi' => 1800, 'station_silent' => 0, 'module_silent' => 0, 'sync_failed' => 0, 'auth_required' => 0, 'frost' => 3600, 'gust' => 3600, 'rain' => 0 ] );
check( 'nur Regen ohne Entwarnung', array_keys( array_filter( $cat, fn( $d ) => ! $d['clears'] ) ), [ 'rain' ] );
check( 'Stufen Funk und WLAN', [ $cat['rf']['levels'], $cat['wifi']['levels'] ], [ [ 'low' => [ 90, 80 ], 'medium' => [ 80, 70 ] ], [ 'bad' => [ 86, 71 ], 'average' => [ 71, 56 ] ] ] );
$def = NAWS_Notify_Rules::defaults();
check( 'defaults(): alles aus, Empfaenger leer', [ $def['recipients'], $def['rules']['battery'], $def['rules']['rf'], $def['rules']['sync_failed'], $def['rules']['gust'] ], [ [], [ 'enabled' => 0, 'threshold' => 20 ], [ 'enabled' => 0, 'level' => 'low' ], [ 'enabled' => 0 ], [ 'enabled' => 0, 'threshold' => 60.0 ] ] );

echo "\nEinheiten\n" . str_repeat( '-', 74 ) . "\n";
$F = [ 'temperature_unit' => 'F', 'wind_unit' => 'mph', 'rain_unit' => 'in' ];
check( 'to_base: 32 F = 0 C',            round( NAWS_Notify_Rules::to_base( 'temp', 32.0, $F ), 3 ), 0.0 );
check( 'to_base: 37.28 mph = 60 km/h',   round( NAWS_Notify_Rules::to_base( 'wind', 37.28, $F ), 1 ), 60.0 );
check( 'to_base: 10 m/s = 36 km/h',      round( NAWS_Notify_Rules::to_base( 'wind', 10.0, [ 'wind_unit' => 'ms' ] + $U ), 1 ), 36.0 );
check( 'to_base: 20 kn = 37 km/h',       round( NAWS_Notify_Rules::to_base( 'wind', 20.0, [ 'wind_unit' => 'kn' ] + $U ), 1 ), 37.0 );
check( 'to_base: 1 in = 25.4 mm',        round( NAWS_Notify_Rules::to_base( 'rain', 1.0, $F ), 2 ), 25.4 );
check( 'to_base: Prozent unveraendert',  NAWS_Notify_Rules::to_base( 'percent', 20.0, $F ), 20.0 );
foreach ( [ [ 'temp', -7.5 ], [ 'wind', 60.0 ], [ 'rain', 20.0 ] ] as [ $k, $v ] ) {
    check( "hin und zurueck $k", round( NAWS_Notify_Rules::to_base( $k, NAWS_Notify_Rules::to_display( $k, $v, $F ), $F ), 2 ), $v );
}
check( 'unit_label', [ NAWS_Notify_Rules::unit_label( 'temp', $U ), NAWS_Notify_Rules::unit_label( 'temp', $F ), NAWS_Notify_Rules::unit_label( 'wind', $U ), NAWS_Notify_Rules::unit_label( 'wind', $F ), NAWS_Notify_Rules::unit_label( 'rain', $F ), NAWS_Notify_Rules::unit_label( 'percent', $U ), NAWS_Notify_Rules::unit_label( 'minutes', $U ), NAWS_Notify_Rules::unit_label( 'level', $U ) ], [ '°C', '°F', 'km/h', 'mph', 'in', '%', 'min', '' ] );

echo "\nBedingungen\n" . str_repeat( '-', 74 ) . "\n";
$c = fn( string $r, array $m, array $cfg, bool $active = false ) => NAWS_Notify_Rules::condition( $r, $m, $cfg, $active, $GLOBALS['NOW'], 1200 );
check( 'battery: 23 < 25 warnt',              $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 23 ] ), [ 'threshold' => 25 ] ), true );
check( 'battery: 25 warnt nicht',             $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 25 ] ), [ 'threshold' => 25 ] ), false );
check( 'battery aktiv: 30 haelt (Hysterese)', $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 30 ] ), [ 'threshold' => 25 ], true ), true );
check( 'battery aktiv: 35 entwarnt',          $c( 'battery', mod( 'NAModule4', [ 'battery_percent' => 35 ] ), [ 'threshold' => 25 ], true ), false );
check( 'battery: Wert fehlt -> ausgesetzt',   $c( 'battery', mod( 'NAModule4' ), [ 'threshold' => 25 ] ), null );
check( 'battery: Basis -> ausgesetzt',        $c( 'battery', mod( 'NAMain', [ 'battery_percent' => 5 ] ), [ 'threshold' => 25 ] ), null );
check( 'rf low: 90 warnt, 89 nicht',          [ $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 90 ] ), [ 'level' => 'low' ] ), $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 89 ] ), [ 'level' => 'low' ] ) ], [ true, false ] );
check( 'rf low aktiv: 81 haelt, 80 entwarnt', [ $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 81 ] ), [ 'level' => 'low' ], true ), $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 80 ] ), [ 'level' => 'low' ], true ) ], [ true, false ] );
check( 'rf medium: 80 warnt',                 $c( 'rf', mod( 'NAModule1', [ 'rf_status' => 80 ] ), [ 'level' => 'medium' ] ), true );
check( 'wifi bad: 86 warnt, 60 nicht',        [ $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 86 ] ), [ 'level' => 'bad' ] ), $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 60 ] ), [ 'level' => 'bad' ] ) ], [ true, false ] );
check( 'wifi bad aktiv: 72 haelt, 71 entwarnt', [ $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 72 ] ), [ 'level' => 'bad' ], true ), $c( 'wifi', mod( 'NAMain', [ 'wifi_status' => 71 ] ), [ 'level' => 'bad' ], true ) ], [ true, false ] );
check( 'wifi: Modul -> ausgesetzt',           $c( 'wifi', mod( 'NAModule1', [ 'wifi_status' => 99 ] ), [ 'level' => 'bad' ] ), null );
check( 'station_silent: reachable 0 warnt',   $c( 'station_silent', mod( 'NAMain', [ 'reachable' => 0, 'last_status_store' => $NOW - 60 ] ), [ 'minutes' => 60 ] ), true );
check( 'station_silent: 61 min alt warnt',    $c( 'station_silent', mod( 'NAMain', [ 'reachable' => 1, 'last_status_store' => $NOW - 61 * 60 ] ), [ 'minutes' => 60 ] ), true );
check( 'station_silent: 59 min alt nicht',    $c( 'station_silent', mod( 'NAMain', [ 'reachable' => 1, 'last_status_store' => $NOW - 59 * 60 ] ), [ 'minutes' => 60 ] ), false );
check( 'station_silent: nur Zeitstempel',     $c( 'station_silent', mod( 'NAMain', [ 'last_status_store' => $NOW - 10 * 60 ] ), [ 'minutes' => 60 ] ), false );
check( 'station_silent: nichts da -> ausgesetzt', $c( 'station_silent', mod( 'NAMain' ), [ 'minutes' => 60 ] ), null );
check( 'module_silent: last_message zaehlt',  $c( 'module_silent', mod( 'NAModule3', [ 'reachable' => 1, 'last_message' => $NOW - 90 * 60, 'last_seen' => $NOW ] ), [ 'minutes' => 60 ] ), true );
check( 'module_silent: Rueckfall last_seen',  $c( 'module_silent', mod( 'NAModule3', [ 'last_seen' => $NOW - 5 * 60 ] ), [ 'minutes' => 60 ] ), false );
$out = fn( float $t, int $age = 0 ) => mod( 'NAModule1', [ 'readings' => [ 'Temperature' => [ 'value' => $t, 'at' => $GLOBALS['NOW'] - $age ] ] ] );
check( 'frost: 0.0 warnt, 0.1 nicht',         [ $c( 'frost', $out( 0.0 ), [ 'threshold' => 0.0 ] ), $c( 'frost', $out( 0.1 ), [ 'threshold' => 0.0 ] ) ], [ true, false ] );
check( 'frost aktiv: 0.9 haelt, 1.1 entwarnt', [ $c( 'frost', $out( 0.9 ), [ 'threshold' => 0.0 ], true ), $c( 'frost', $out( 1.1 ), [ 'threshold' => 0.0 ], true ) ], [ true, false ] );
check( 'frost: Messwert 21 min alt -> ausgesetzt', $c( 'frost', $out( -3.0, 21 * 60 ), [ 'threshold' => 0.0 ] ), null );
check( 'frost: Messwert 19 min alt zaehlt',   $c( 'frost', $out( -3.0, 19 * 60 ), [ 'threshold' => 0.0 ] ), true );
check( 'frost: kein Messwert -> ausgesetzt',  $c( 'frost', mod( 'NAModule1' ), [ 'threshold' => 0.0 ] ), null );
$wind = fn( float $g ) => mod( 'NAModule2', [ 'readings' => [ 'GustStrength' => [ 'value' => $g, 'at' => $GLOBALS['NOW'] ] ] ] );
check( 'gust: 60 warnt, 59.9 nicht',          [ $c( 'gust', $wind( 60.0 ), [ 'threshold' => 60.0 ] ), $c( 'gust', $wind( 59.9 ), [ 'threshold' => 60.0 ] ) ], [ true, false ] );
check( 'gust aktiv: 59.9 entwarnt',           $c( 'gust', $wind( 59.9 ), [ 'threshold' => 60.0 ], true ), false );
$rain = fn( float $r ) => mod( 'NAModule3', [ 'readings' => [ 'sum_rain_24' => [ 'value' => $r, 'at' => $GLOBALS['NOW'] ] ] ] );
check( 'rain: 20 warnt, 19.9 nicht',          [ $c( 'rain', $rain( 20.0 ), [ 'threshold' => 20.0 ] ), $c( 'rain', $rain( 19.9 ), [ 'threshold' => 20.0 ] ) ], [ true, false ] );
check( 'Site-Regel per condition() -> null',  $c( 'sync_failed', mod( 'NAMain' ), [] ), null );
check( 'value_of: Batterie, Frost, Zeitstempel', [ NAWS_Notify_Rules::value_of( 'battery', mod( 'NAModule4', [ 'battery_percent' => 23 ] ) ), NAWS_Notify_Rules::value_of( 'frost', $out( -2.5 ) ), NAWS_Notify_Rules::value_of( 'station_silent', mod( 'NAMain', [ 'last_status_store' => 5 ] ) ), NAWS_Notify_Rules::value_of( 'module_silent', mod( 'NAModule1', [ 'last_seen' => 7 ] ) ) ], [ 23, -2.5, 5, 7 ] );

// ---- Teil 2 (Task 3: Zustandsmaschine) wird hier eingefuegt ----

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-notify-rules.php`
Expected: Fatal `Failed opening required '…/includes/class-naws-notify-rules.php'`.

- [ ] **Step 3: Klasse anlegen (ohne `evaluate()`, die kommt in Task 3)**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rules and state machine behind the e-mail notifications.
 *
 * Pure: no WordPress call, no database, no clock. The snapshot, the
 * settings, the previous state and the time come in; the new state, the
 * changes to mail and the status rows for the admin page go out. Everything
 * here is testable with `php tests/test-notify-rules.php` alone.
 *
 * Thresholds are always in base units — °C, km/h, mm — the units Netatmo
 * delivers in and the tables store. Only the admin page converts.
 *
 * @package NAWS
 * @since 2.0.0
 */
final class NAWS_Notify_Rules {

    /** Battery-powered module types (everything but the base station). */
    const MODULE_TYPES = [ 'NAModule1', 'NAModule2', 'NAModule3', 'NAModule4' ];

    /**
     * The rule catalogue. Keys are the ids used in the option and the state.
     *
     * group    'station' | 'weather'         — the two sections of the page
     * scope    'site' | 'station' | 'module' — what one state entry stands for
     * types    module types the rule applies to (empty for site rules)
     * field    column of naws_modules the rule reads, or
     * reading  parameter of the latest readings the rule reads
     * param    the one setting the rule has: 'threshold' | 'level' | 'minutes' | ''
     * kind     how to sanitize/format it: percent | level | minutes | temp | wind | rain | ''
     * levels   for level rules: name => [ raise at >=, clear at <= ]
     * hold_on  seconds the warning condition must hold before it counts
     * hold_off seconds the clear condition must hold before it counts
     * clears   whether the rule sends an all-clear mail
     */
    public static function catalog(): array {
        return [
            'battery' => [
                'group' => 'station', 'scope' => 'module', 'types' => self::MODULE_TYPES,
                'field' => 'battery_percent', 'param' => 'threshold', 'kind' => 'percent',
                'default' => 20, 'min' => 1, 'max' => 99,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'rf' => [
                'group' => 'station', 'scope' => 'module', 'types' => self::MODULE_TYPES,
                'field' => 'rf_status', 'param' => 'level', 'kind' => 'level',
                'default' => 'low', 'levels' => [ 'low' => [ 90, 80 ], 'medium' => [ 80, 70 ] ],
                'hold_on' => 1800, 'hold_off' => 1800, 'clears' => true,
            ],
            'wifi' => [
                'group' => 'station', 'scope' => 'station', 'types' => [ 'NAMain' ],
                'field' => 'wifi_status', 'param' => 'level', 'kind' => 'level',
                'default' => 'bad', 'levels' => [ 'bad' => [ 86, 71 ], 'average' => [ 71, 56 ] ],
                'hold_on' => 1800, 'hold_off' => 1800, 'clears' => true,
            ],
            'station_silent' => [
                'group' => 'station', 'scope' => 'station', 'types' => [ 'NAMain' ],
                'field' => 'last_status_store', 'param' => 'minutes', 'kind' => 'minutes',
                'default' => 60, 'min' => 10, 'max' => 1440,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'module_silent' => [
                'group' => 'station', 'scope' => 'module', 'types' => self::MODULE_TYPES,
                'field' => 'last_message', 'param' => 'minutes', 'kind' => 'minutes',
                'default' => 60, 'min' => 10, 'max' => 1440,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'sync_failed' => [
                'group' => 'station', 'scope' => 'site', 'types' => [],
                'field' => '', 'param' => '', 'kind' => '', 'default' => null,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'auth_required' => [
                'group' => 'station', 'scope' => 'site', 'types' => [],
                'field' => '', 'param' => '', 'kind' => '', 'default' => null,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'frost' => [
                'group' => 'weather', 'scope' => 'module', 'types' => [ 'NAModule1' ],
                'reading' => 'Temperature', 'param' => 'threshold', 'kind' => 'temp',
                'default' => 0.0, 'min' => -50.0, 'max' => 50.0,
                'hold_on' => 0, 'hold_off' => 3600, 'clears' => true,
            ],
            'gust' => [
                'group' => 'weather', 'scope' => 'module', 'types' => [ 'NAModule2' ],
                'reading' => 'GustStrength', 'param' => 'threshold', 'kind' => 'wind',
                'default' => 60.0, 'min' => 1.0, 'max' => 300.0,
                'hold_on' => 0, 'hold_off' => 3600, 'clears' => true,
            ],
            'rain' => [
                'group' => 'weather', 'scope' => 'module', 'types' => [ 'NAModule3' ],
                'reading' => 'sum_rain_24', 'param' => 'threshold', 'kind' => 'rain',
                'default' => 20.0, 'min' => 0.1, 'max' => 500.0,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => false,
            ],
        ];
    }

    /** Settings as shipped: no recipients, every rule off with its default parameter. */
    public static function defaults(): array {
        $rules = [];
        foreach ( self::catalog() as $id => $def ) {
            $rule = [ 'enabled' => 0 ];
            if ( $def['param'] !== '' ) {
                $rule[ $def['param'] ] = $def['default'];
            }
            $rules[ $id ] = $rule;
        }
        return [ 'recipients' => [], 'rules' => $rules ];
    }

    /** A value entered in the display unit, in the base unit (°C, km/h, mm). */
    public static function to_base( string $kind, float $v, array $units ): float {
        switch ( $kind ) {
            case 'temp':
                return ( $units['temperature_unit'] ?? 'C' ) === 'F' ? ( $v - 32 ) * 5 / 9 : $v;
            case 'wind':
                switch ( $units['wind_unit'] ?? 'kmh' ) {
                    case 'ms':  return $v * 3.6;
                    case 'mph': return $v / 0.62137;
                    case 'kn':  return $v / 0.53996;
                }
                return $v;
            case 'rain':
                return ( $units['rain_unit'] ?? 'mm' ) === 'in' ? $v * 25.4 : $v;
        }
        return $v;
    }

    /** A stored base-unit value in the display unit; the factors of NAWS_Helpers::format_value(). */
    public static function to_display( string $kind, float $v, array $units ): float {
        switch ( $kind ) {
            case 'temp':
                return ( $units['temperature_unit'] ?? 'C' ) === 'F' ? round( $v * 9 / 5 + 32, 1 ) : round( $v, 1 );
            case 'wind':
                switch ( $units['wind_unit'] ?? 'kmh' ) {
                    case 'ms':  return round( $v / 3.6, 1 );
                    case 'mph': return round( $v * 0.62137, 1 );
                    case 'kn':  return round( $v * 0.53996, 1 );
                }
                return round( $v, 1 );
            case 'rain':
                return ( $units['rain_unit'] ?? 'mm' ) === 'in' ? round( $v / 25.4, 2 ) : round( $v, 1 );
        }
        return $v;
    }

    /** The unit shown next to a threshold of this kind. */
    public static function unit_label( string $kind, array $units ): string {
        switch ( $kind ) {
            case 'percent': return '%';
            case 'minutes': return 'min';
            case 'temp':    return ( $units['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
            case 'wind':
                $labels = [ 'kmh' => 'km/h', 'ms' => 'm/s', 'mph' => 'mph', 'kn' => 'kn' ];
                return $labels[ $units['wind_unit'] ?? 'kmh' ] ?? 'km/h';
            case 'rain':    return ( $units['rain_unit'] ?? 'mm' ) === 'in' ? 'in' : 'mm';
        }
        return '';
    }

    /**
     * The value a rule looks at, as stored: a column, a reading, or for the
     * two silence rules the timestamp of the last message. null when absent.
     *
     * @return int|float|null
     */
    public static function value_of( string $rule, array $module ) {
        $def = self::catalog()[ $rule ] ?? null;
        if ( ! $def ) {
            return null;
        }
        if ( $rule === 'station_silent' ) {
            return isset( $module['last_status_store'] ) ? (int) $module['last_status_store'] : null;
        }
        if ( $rule === 'module_silent' ) {
            $ts = $module['last_message'] ?? $module['last_seen'] ?? null;
            return $ts === null ? null : (int) $ts;
        }
        if ( ! empty( $def['reading'] ) ) {
            $r = $module['readings'][ $def['reading'] ] ?? null;
            return ( is_array( $r ) && isset( $r['value'] ) ) ? (float) $r['value'] : null;
        }
        if ( ! empty( $def['field'] ) ) {
            $v = $module[ $def['field'] ] ?? null;
            return ( $v === null || $v === '' ) ? null : (int) $v;
        }
        return null;
    }

    /**
     * Is the rule in its warning state for this module right now?
     *
     * true  — warning condition holds (or, while $active, the clear condition
     *         does not hold yet: hysteresis)
     * false — clear condition holds (or the warning condition does not)
     * null  — suspended: wrong module type, no value, stale reading
     *
     * @param array $cfg          The rule's settings: threshold | level | minutes.
     * @param bool  $active       Whether the state entry is currently active.
     * @param int   $now          Unix time of this run.
     * @param int   $stale_after  Seconds after which a reading no longer counts.
     */
    public static function condition( string $rule, array $module, array $cfg, bool $active, int $now, int $stale_after ): ?bool {
        $def = self::catalog()[ $rule ] ?? null;
        if ( ! $def || $def['scope'] === 'site' ) {
            return null;
        }
        if ( ! in_array( $module['module_type'] ?? '', $def['types'], true ) ) {
            return null;
        }
        if ( ! empty( $def['reading'] ) ) {
            $r = $module['readings'][ $def['reading'] ] ?? null;
            if ( ! is_array( $r ) || ! isset( $r['value'], $r['at'] ) || ( $now - (int) $r['at'] ) > $stale_after ) {
                return null;
            }
        }
        $v = self::value_of( $rule, $module );

        switch ( $rule ) {
            case 'battery':
                if ( $v === null ) { return null; }
                $t = (int) ( $cfg['threshold'] ?? $def['default'] );
                return $active ? ! ( $v >= $t + 10 ) : ( $v < $t );

            case 'rf':
            case 'wifi':
                if ( $v === null ) { return null; }
                $level = $cfg['level'] ?? $def['default'];
                [ $on, $off ] = $def['levels'][ $level ] ?? $def['levels'][ $def['default'] ];
                return $active ? ! ( $v <= $off ) : ( $v >= $on );

            case 'station_silent':
            case 'module_silent':
                $reachable = $module['reachable'] ?? null;
                if ( $v === null && $reachable === null ) { return null; }
                $minutes = (int) ( $cfg['minutes'] ?? $def['default'] );
                $silent  = ( $reachable !== null && (int) $reachable === 0 );
                if ( $v !== null && ( $now - $v ) > $minutes * 60 ) { $silent = true; }
                return $silent;

            case 'frost':
                $t = (float) ( $cfg['threshold'] ?? $def['default'] );
                return $active ? ! ( $v > $t + 1 ) : ( $v <= $t );

            case 'gust':
            case 'rain':
                $t = (float) ( $cfg['threshold'] ?? $def['default'] );
                return $v >= $t;
        }
        return null;
    }
}
```

In `xtx-integration-for-netatmo.php` nach der Zeile `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-windrose.php' );`:

```php
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-notify-rules.php' );
```

- [ ] **Step 4: Test grün**

Run: `php tests/test-notify-rules.php && php tests/test-main-requires.php && php -l includes/class-naws-notify-rules.php && vendor/bin/phpcs includes/class-naws-notify-rules.php`
Expected: `… 0 fehlgeschlagen`, beide; kein phpcs-Befund.

- [ ] **Step 5: Commit**

```bash
git add includes/class-naws-notify-rules.php xtx-integration-for-netatmo.php tests/test-notify-rules.php
git commit -m "Notifications: rule catalogue, units and the condition of every rule"
```

---

### Task 3: `NAWS_Notify_Rules::evaluate()` — die Zustandsmaschine

**Files:**
- Modify: `includes/class-naws-notify-rules.php` (Methoden anfügen)
- Test: `tests/test-notify-rules.php` (Teil 2 an der markierten Stelle)

**Interfaces:**
- Consumes: `catalog()`, `defaults()`, `condition()`, `value_of()` aus Task 2.
- Produces: `evaluate( array $snapshot, array $settings, array $state, int $now, array $ctx ): array{state: array, events: array, rows: array}`.
  - `$ctx = [ 'interval' => int Sekunden, 'sync' => 'ok'|'failed', 'consecutive_errors' => int, 'auth_required' => bool, 'error' => string ]`.
  - Zustand: `'<rule>|<module_id>'` bzw. `'<rule>|site'` → `[ 'active' => bool, 'since' => int, 'pending_since' => ?int, 'value' => mixed ]`; es werden nur aktive oder wartende Einträge zurückgegeben.
  - Wechsel: `[ 'rule', 'kind' => 'raise'|'clear', 'module_id', 'module_name', 'module_type', 'value', 'threshold', 'since', 'now', 'error' ]` (Site-Regeln: die drei Modulfelder leer).
  - Statuszeile: `[ 'rule', 'module_id', 'module_name', 'module_type', 'status' => 'ok'|'active'|'pending'|'suspended', 'reason' => ''|'disabled'|'missing'|'stale'|'station_silent'|'no_sync', 'since' => int, 'value', 'threshold', 'kind' ]`.
  - `rule_cfg( string $rule, array $settings ): array` (öffentlich): die Einstellungen einer Regel, mit Vorgaben aufgefüllt.

- [ ] **Step 1: Test-Teil 2 an der Stelle `// ---- Teil 2 (Task 3: Zustandsmaschine) wird hier eingefuegt ----` einfügen**

```php
echo "\nZustandsmaschine\n" . str_repeat( '-', 74 ) . "\n";
$on   = fn( array $over = [] ) => [ 'recipients' => [], 'rules' => array_replace_recursive( NAWS_Notify_Rules::defaults()['rules'], $over ) ];
$ctx  = [ 'interval' => 600, 'sync' => 'ok', 'consecutive_errors' => 0, 'auth_required' => false, 'error' => '' ];
$base = mod( 'NAMain',    [ 'module_id' => 'base', 'station_id' => 'base', 'module_name' => 'Basis', 'reachable' => 1, 'last_status_store' => $NOW - 300, 'wifi_status' => 60 ] );
$gast = mod( 'NAModule4', [ 'module_id' => 'gast', 'module_name' => 'Gast', 'battery_percent' => 23, 'rf_status' => 74, 'reachable' => 1, 'last_message' => $NOW - 300 ] );
$snap = fn( array ...$ms ) => [ 'modules' => array_combine( array_map( fn( $m ) => $m['module_id'], $ms ), $ms ) ];
$ev   = fn( array $r ) => array_map( fn( $e ) => [ $e['rule'], $e['kind'], $e['module_name'], $e['value'], $e['threshold'] ], $r['events'] );
$row  = function ( array $r, string $rule, string $id ): ?array { foreach ( $r['rows'] as $x ) { if ( $x['rule'] === $rule && $x['module_id'] === $id ) { return [ $x['status'], $x['reason'] ]; } } return null; };

$sb = $on( [ 'battery' => [ 'enabled' => 1, 'threshold' => 25 ] ] );
$r  = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sb, [], $NOW, $ctx );
check( 'Eintritt ohne Beharrung: ein raise fuer Gast', $ev( $r ), [ [ 'battery', 'raise', 'Gast', 23, 25 ] ] );
check( 'Zustand: aktiv seit jetzt, nur dieser Eintrag', [ array_keys( $r['state'] ), $r['state']['battery|gast']['active'], $r['state']['battery|gast']['since'], $r['state']['battery|gast']['pending_since'] ], [ [ 'battery|gast' ], true, $NOW, null ] );
check( 'Zeile: aktiv',                       $row( $r, 'battery', 'gast' ), [ 'active', '' ] );
check( 'Zeile: ausgeschaltete Regel',        $row( $r, 'rf', 'gast' ), [ 'suspended', 'disabled' ] );
check( 'Zeile: Site-Regel aus',              $row( $r, 'sync_failed', '' ), [ 'suspended', 'disabled' ] );
$r2 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sb, $r['state'], $NOW + 600, $ctx );
check( 'gleicher Zustand: kein Wechsel, since bleibt', [ $r2['events'], $r2['state']['battery|gast']['since'] ], [ [], $NOW ] );
$r3 = NAWS_Notify_Rules::evaluate( $snap( $base, array_merge( $gast, [ 'battery_percent' => 30 ] ) ), $sb, $r['state'], $NOW + 1200, $ctx );
check( 'Hysterese: 30 entwarnt nicht',       [ $r3['events'], $r3['state']['battery|gast']['active'] ], [ [], true ] );
$r4 = NAWS_Notify_Rules::evaluate( $snap( $base, array_merge( $gast, [ 'battery_percent' => 100 ] ) ), $sb, $r['state'], $NOW + 1800, $ctx );
check( 'Entwarnung: clear, Zustand leer',    [ $ev( $r4 ), $r4['state'] ], [ [ [ 'battery', 'clear', 'Gast', 100, 25 ] ], [] ] );
check( 'Entwarnung traegt Beginn und Ende', [ $r4['events'][0]['since'], $r4['events'][0]['now'] ], [ $NOW, $NOW + 1800 ] );

$sr   = $on( [ 'rf' => [ 'enabled' => 1, 'level' => 'low' ] ] );
$weak = array_merge( $gast, [ 'rf_status' => 92 ] );
$a = NAWS_Notify_Rules::evaluate( $snap( $base, $weak ), $sr, [], $NOW, $ctx );
check( 'Beharrung: erster Lauf wartet',      [ $a['events'], $a['state']['rf|gast']['pending_since'], $a['state']['rf|gast']['active'], $row( $a, 'rf', 'gast' ) ], [ [], $NOW, false, [ 'pending', '' ] ] );
$b = NAWS_Notify_Rules::evaluate( $snap( $base, $weak ), $sr, $a['state'], $NOW + 1800, $ctx );
check( 'Beharrung: nach 30 min raise',       $ev( $b ), [ [ 'rf', 'raise', 'Gast', 92, 'low' ] ] );
$c1 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sr, $a['state'], $NOW + 600, $ctx );
check( 'Flattern: gut dazwischen setzt zurueck', [ $c1['events'], $c1['state'] ], [ [], [] ] );
$c2 = NAWS_Notify_Rules::evaluate( $snap( $base, $weak ), $sr, $c1['state'], $NOW + 1200, $ctx );
check( 'Flattern: Beharrung beginnt neu',    $c2['state']['rf|gast']['pending_since'], $NOW + 1200 );

$sg   = $on( [ 'gust' => [ 'enabled' => 1, 'threshold' => 60.0 ] ] );
$wm   = fn( float $g, int $at ) => mod( 'NAModule2', [ 'module_id' => 'wind', 'module_name' => 'Wind', 'reachable' => 1, 'last_message' => $at, 'readings' => [ 'GustStrength' => [ 'value' => $g, 'at' => $at ] ] ] );
$g1 = NAWS_Notify_Rules::evaluate( $snap( $base, $wm( 70.0, $NOW ) ), $sg, [], $NOW, $ctx );
check( 'Boe: raise sofort',                  $ev( $g1 ), [ [ 'gust', 'raise', 'Wind', 70.0, 60.0 ] ] );
$g2 = NAWS_Notify_Rules::evaluate( $snap( $base, $wm( 40.0, $NOW + 600 ) ), $sg, $g1['state'], $NOW + 600, $ctx );
check( 'Boe: unter Schwelle wartet 60 min', [ $g2['events'], $g2['state']['gust|wind']['active'], $row( $g2, 'gust', 'wind' ) ], [ [], true, [ 'pending', '' ] ] );
$g3 = NAWS_Notify_Rules::evaluate( $snap( $base, $wm( 40.0, $NOW + 4200 ) ), $sg, $g2['state'], $NOW + 4200, $ctx );
check( 'Boe: nach 60 min clear',             [ $ev( $g3 ), $g3['state'] ], [ [ [ 'gust', 'clear', 'Wind', 40.0, 60.0 ] ], [] ] );

$sn = $on( [ 'rain' => [ 'enabled' => 1, 'threshold' => 20.0 ] ] );
$rm = fn( float $r, int $at ) => mod( 'NAModule3', [ 'module_id' => 'rain', 'module_name' => 'Regen', 'reachable' => 1, 'last_message' => $at, 'readings' => [ 'sum_rain_24' => [ 'value' => $r, 'at' => $at ] ] ] );
$n1 = NAWS_Notify_Rules::evaluate( $snap( $base, $rm( 25.0, $NOW ) ), $sn, [], $NOW, $ctx );
$n2 = NAWS_Notify_Rules::evaluate( $snap( $base, $rm( 10.0, $NOW + 600 ) ), $sn, $n1['state'], $NOW + 600, $ctx );
check( 'Regen: raise, dann stilles Ende',    [ $ev( $n1 ), $n2['events'], $n2['state'] ], [ [ [ 'rain', 'raise', 'Regen', 25.0, 20.0 ] ], [], [] ] );

$ss   = $on( [ 'battery' => [ 'enabled' => 1, 'threshold' => 25 ], 'station_silent' => [ 'enabled' => 1, 'minutes' => 60 ], 'wifi' => [ 'enabled' => 1, 'level' => 'bad' ] ] );
$dead = array_merge( $base, [ 'reachable' => 0 ] );
$d = NAWS_Notify_Rules::evaluate( $snap( $dead, $gast ), $ss, [], $NOW, $ctx );
check( 'Basis still: nur station_silent meldet', $ev( $d ), [ [ 'station_silent', 'raise', 'Basis', $NOW - 300, 60 ] ] );
check( 'Basis still: Batterie ausgesetzt, WLAN nicht', [ $row( $d, 'battery', 'gast' ), $row( $d, 'wifi', 'base' ) ], [ [ 'suspended', 'station_silent' ], [ 'ok', '' ] ] );
check( 'Basis still: Zustand nur die Basis', array_keys( $d['state'] ), [ 'station_silent|base' ] );
$d2 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $ss, $d['state'], $NOW + 600, $ctx );
check( 'Basis zurueck: clear und Batterie meldet', $ev( $d2 ), [ [ 'battery', 'raise', 'Gast', 23, 25 ], [ 'station_silent', 'clear', 'Basis', $NOW - 300, 60 ] ] );

$was = [ 'battery|gast' => [ 'active' => true, 'since' => $NOW - 100, 'pending_since' => null, 'value' => 23 ] ];
$e = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $on(), $was, $NOW, $ctx );
check( 'Regel aus: Eintrag weg, kein Wechsel', [ $e['events'], $e['state'] ], [ [], [] ] );
$f = NAWS_Notify_Rules::evaluate( $snap( $base ), $sb, $was, $NOW, $ctx );
check( 'Modul deaktiviert: Eintrag weg',     [ $f['events'], $f['state'] ], [ [], [] ] );

$sf   = $on( [ 'sync_failed' => [ 'enabled' => 1 ], 'battery' => [ 'enabled' => 1, 'threshold' => 25 ] ] );
$ctxF = [ 'sync' => 'failed', 'consecutive_errors' => 3, 'error' => 'Resolving timed out' ] + $ctx;
$h = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $sf, $was, $NOW, $ctxF );
check( 'Abruf scheitert: raise mit Fehlerzahl', [ $ev( $h ), $h['events'][0]['error'], $h['events'][0]['module_id'] ], [ [ [ 'sync_failed', 'raise', '', 3, null ] ], 'Resolving timed out', '' ] );
check( 'Abruf scheitert: Moduleintrag eingefroren', [ $h['state']['battery|gast'], $row( $h, 'battery', 'gast' ) ], [ $was['battery|gast'], [ 'suspended', 'no_sync' ] ] );
$h0 = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $sf, [], $NOW, [ 'consecutive_errors' => 2 ] + $ctxF );
check( 'Abruf scheitert: zwei Fehler reichen nicht', $h0['events'], [] );
$h2 = NAWS_Notify_Rules::evaluate( $snap( $base, $gast ), $sf, $h['state'], $NOW + 600, $ctx );
check( 'naechster Erfolg: clear, Batterie bleibt aktiv ohne Wechsel', [ $ev( $h2 ), $h2['state']['battery|gast']['active'] ], [ [ [ 'sync_failed', 'clear', '', 0, null ] ], true ] );

$sa = $on( [ 'auth_required' => [ 'enabled' => 1 ] ] );
$i1 = NAWS_Notify_Rules::evaluate( [ 'modules' => [] ], $sa, [], $NOW, [ 'auth_required' => true ] + $ctxF );
$i2 = NAWS_Notify_Rules::evaluate( $snap( $base ), $sa, $i1['state'], $NOW + 600, $ctx );
check( 'Zugangsdaten: raise, dann clear',    [ $ev( $i1 ), $ev( $i2 ) ], [ [ [ 'auth_required', 'raise', '', null, null ] ], [ [ 'auth_required', 'clear', '', null, null ] ] ] );

$sfr = $on( [ 'frost' => [ 'enabled' => 1, 'threshold' => 0.0 ] ] );
$aus = mod( 'NAModule1', [ 'module_id' => 'aus', 'module_name' => 'Aussen', 'reachable' => 1, 'last_message' => $NOW, 'readings' => [ 'Temperature' => [ 'value' => -2.0, 'at' => $NOW - 1500 ] ] ] );
$j = NAWS_Notify_Rules::evaluate( $snap( $base, $aus ), $sfr, [], $NOW, $ctx );
check( 'veralteter Messwert: ausgesetzt, kein Wechsel', [ $j['events'], $row( $j, 'frost', 'aus' ) ], [ [], [ 'suspended', 'stale' ] ] );
$k = NAWS_Notify_Rules::evaluate( $snap( $base, array_merge( $aus, [ 'readings' => [] ] ) ), $sfr, [], $NOW, $ctx );
check( 'fehlender Messwert: ausgesetzt',     $row( $k, 'frost', 'aus' ), [ 'suspended', 'missing' ] );
$l = NAWS_Notify_Rules::evaluate( $snap( $base, $aus ), $sfr, [], $NOW, [ 'interval' => 1800 ] + $ctx );
check( 'Intervall 30 min: 25 min alt zaehlt noch', $ev( $l ), [ [ 'frost', 'raise', 'Aussen', -2.0, 0.0 ] ] );
check( 'rule_cfg fuellt Vorgaben auf',        NAWS_Notify_Rules::rule_cfg( 'battery', [ 'rules' => [ 'battery' => [ 'enabled' => 1 ] ] ] ), [ 'enabled' => 1, 'threshold' => 20 ] );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-notify-rules.php`
Expected: Fatal `Call to undefined method NAWS_Notify_Rules::evaluate()`.

- [ ] **Step 3: Methoden anfügen (vor der schließenden Klammer der Klasse)**

```php
    /** A rule's settings with the catalogue defaults filled in. */
    public static function rule_cfg( string $rule, array $settings ): array {
        $defaults = self::defaults()['rules'][ $rule ] ?? [ 'enabled' => 0 ];
        $cfg      = $settings['rules'][ $rule ] ?? [];
        return ( is_array( $cfg ) ? $cfg : [] ) + $defaults;
    }

    /**
     * Run every rule over the snapshot and the previous state.
     *
     * A failed fetch ($ctx['sync'] !== 'ok') carries no snapshot: module and
     * station entries are carried over untouched, only the two site rules
     * are judged. A silent base station freezes the module rules of its
     * modules — their values would be stale. Only active or pending entries
     * are returned, so the option stays small.
     *
     * @return array{state: array, events: array, rows: array}
     */
    public static function evaluate( array $snapshot, array $settings, array $state, int $now, array $ctx ): array {
        $catalog     = self::catalog();
        $modules     = is_array( $snapshot['modules'] ?? null ) ? $snapshot['modules'] : [];
        $interval    = max( 60, (int) ( $ctx['interval'] ?? 600 ) );
        $stale_after = max( 1200, 2 * $interval );
        $sync_ok     = ( $ctx['sync'] ?? 'ok' ) === 'ok';
        $new = []; $events = []; $rows = [];

        // Base stations silent this run: the raw condition, whatever the switch says.
        $silent = [];
        $cfg_ss = self::rule_cfg( 'station_silent', $settings );
        foreach ( $modules as $id => $m ) {
            if ( ( $m['module_type'] ?? '' ) === 'NAMain' && self::condition( 'station_silent', $m, $cfg_ss, false, $now, $stale_after ) === true ) {
                $silent[ (string) $id ] = true;
            }
        }

        foreach ( $catalog as $rule => $def ) {
            $cfg     = self::rule_cfg( $rule, $settings );
            $enabled = ! empty( $cfg['enabled'] );

            if ( $def['scope'] === 'site' ) {
                $key = $rule . '|site';
                if ( ! $enabled ) {
                    $rows[] = self::row( $rule, null, 'suspended', 'disabled', null, $cfg, null );
                    continue;
                }
                $value = $rule === 'sync_failed' ? (int) ( $ctx['consecutive_errors'] ?? 0 ) : null;
                $cond  = $rule === 'sync_failed' ? ( $value >= 3 ) : ! empty( $ctx['auth_required'] );
                [ $entry, $kind ] = self::step( $def, $state[ $key ] ?? null, $cond, $now, $value );
                if ( $kind ) {
                    $events[] = self::event( $rule, $kind, null, $entry, $value, $cfg, $now, (string) ( $ctx['error'] ?? '' ) );
                }
                if ( self::keep( $entry ) ) {
                    $new[ $key ] = $entry;
                }
                $rows[] = self::row( $rule, null, self::status_of( $entry ), '', $value, $cfg, $entry );
                continue;
            }

            if ( ! $sync_ok ) {
                foreach ( $state as $k => $e ) {
                    if ( is_array( $e ) && str_starts_with( (string) $k, $rule . '|' ) && self::keep( $e ) ) {
                        $new[ $k ] = $e;
                        $rows[]    = self::row( $rule, [ 'module_id' => substr( (string) $k, strlen( $rule ) + 1 ), 'module_name' => '', 'module_type' => '' ], 'suspended', 'no_sync', $e['value'] ?? null, $cfg, $e );
                    }
                }
                continue;
            }

            foreach ( $modules as $id => $m ) {
                if ( ! in_array( $m['module_type'] ?? '', $def['types'], true ) ) {
                    continue;
                }
                $key   = $rule . '|' . $id;
                $entry = $state[ $key ] ?? null;
                $entry = is_array( $entry ) ? $entry : null;
                $value = self::value_of( $rule, $m );

                if ( ! $enabled ) {
                    $rows[] = self::row( $rule, $m, 'suspended', 'disabled', $value, $cfg, null );
                    continue;
                }
                if ( $def['scope'] === 'module' && isset( $silent[ (string) ( $m['station_id'] ?? '' ) ] ) ) {
                    if ( $entry && self::keep( $entry ) ) {
                        $new[ $key ] = $entry;
                    }
                    $rows[] = self::row( $rule, $m, 'suspended', 'station_silent', $value, $cfg, $entry );
                    continue;
                }
                $active = ! empty( $entry['active'] );
                $cond   = self::condition( $rule, $m, $cfg, $active, $now, $stale_after );
                if ( $cond === null ) {
                    if ( $entry && self::keep( $entry ) ) {
                        $new[ $key ] = $entry;
                    }
                    $has_reading = ! empty( $def['reading'] ) && isset( $m['readings'][ $def['reading'] ] );
                    $rows[]      = self::row( $rule, $m, 'suspended', $has_reading ? 'stale' : 'missing', $value, $cfg, $entry );
                    continue;
                }
                [ $entry, $kind ] = self::step( $def, $entry, $cond, $now, $value );
                if ( $kind ) {
                    $events[] = self::event( $rule, $kind, $m, $entry, $value, $cfg, $now, '' );
                }
                if ( self::keep( $entry ) ) {
                    $new[ $key ] = $entry;
                }
                $rows[] = self::row( $rule, $m, self::status_of( $entry ), '', $value, $cfg, $entry );
            }
        }

        return [ 'state' => $new, 'events' => $events, 'rows' => $rows ];
    }

    /**
     * One transition of one entry. Returns the entry and 'raise', 'clear'
     * or null. `since` is set when the entry becomes active and kept through
     * the all-clear, so the clear event can say how long it lasted.
     *
     * @return array{0: array, 1: ?string}
     */
    private static function step( array $def, ?array $entry, bool $cond, int $now, $value ): array {
        $entry = ( is_array( $entry ) ? $entry : [] ) + [ 'active' => false, 'since' => 0, 'pending_since' => null, 'value' => null ];
        $entry['value'] = $value;
        $kind = null;

        if ( ! $entry['active'] ) {
            if ( $cond ) {
                $entry['pending_since'] = $entry['pending_since'] ?? $now;
                if ( $now - $entry['pending_since'] >= $def['hold_on'] ) {
                    $entry['active']        = true;
                    $entry['since']         = $now;
                    $entry['pending_since'] = null;
                    $kind                   = 'raise';
                }
            } else {
                $entry['pending_since'] = null;
            }
        } elseif ( ! $cond ) {
            $entry['pending_since'] = $entry['pending_since'] ?? $now;
            if ( $now - $entry['pending_since'] >= $def['hold_off'] ) {
                $entry['active']        = false;
                $entry['pending_since'] = null;
                $kind                   = $def['clears'] ? 'clear' : null;
            }
        } else {
            $entry['pending_since'] = null;
        }
        return [ $entry, $kind ];
    }

    private static function keep( array $entry ): bool {
        return ! empty( $entry['active'] ) || isset( $entry['pending_since'] );
    }

    private static function status_of( array $entry ): string {
        if ( ! empty( $entry['active'] ) ) {
            return isset( $entry['pending_since'] ) ? 'pending' : 'active';
        }
        return isset( $entry['pending_since'] ) ? 'pending' : 'ok';
    }

    private static function event( string $rule, string $kind, ?array $m, array $entry, $value, array $cfg, int $now, string $error ): array {
        $def = self::catalog()[ $rule ];
        return [
            'rule'        => $rule,
            'kind'        => $kind,
            'module_id'   => (string) ( $m['module_id'] ?? '' ),
            'module_name' => (string) ( $m['module_name'] ?? '' ),
            'module_type' => (string) ( $m['module_type'] ?? '' ),
            'value'       => $value,
            'threshold'   => $def['param'] !== '' ? ( $cfg[ $def['param'] ] ?? $def['default'] ) : null,
            'since'       => (int) ( $entry['since'] ?? $now ),
            'now'         => $now,
            'error'       => $error,
        ];
    }

    private static function row( string $rule, ?array $m, string $status, string $reason, $value, array $cfg, ?array $entry ): array {
        $def = self::catalog()[ $rule ];
        return [
            'rule'        => $rule,
            'module_id'   => (string) ( $m['module_id'] ?? '' ),
            'module_name' => (string) ( $m['module_name'] ?? '' ),
            'module_type' => (string) ( $m['module_type'] ?? '' ),
            'status'      => $status,
            'reason'      => $reason,
            'since'       => (int) ( ( ! empty( $entry['active'] ) ? $entry['since'] : ( $entry['pending_since'] ?? 0 ) ) ?? 0 ),
            'value'       => $value,
            'threshold'   => $def['param'] !== '' ? ( $cfg[ $def['param'] ] ?? $def['default'] ) : null,
            'kind'        => $def['kind'],
        ];
    }
```

- [ ] **Step 4: Test grün**

Run: `php tests/test-notify-rules.php && php -l includes/class-naws-notify-rules.php && vendor/bin/phpcs includes/class-naws-notify-rules.php`
Expected: `… 0 fehlgeschlagen`; kein phpcs-Befund. Schlägt eine Erwartung mit einem Ein-Tick-Unterschied fehl (Beharrung ≥ statt >), gilt die Erwartung des Tests; die Spec sagt „mindestens so lange".

- [ ] **Step 5: Commit**

```bash
git add includes/class-naws-notify-rules.php tests/test-notify-rules.php
git commit -m "Notifications: the state machine — hold times, hysteresis, all-clear, frozen modules"
```

---

### Task 4: `NAWS_Notifications` — Option, Säuberung, Empfänger, Einheiten

**Files:**
- Create: `includes/class-naws-notifications.php`
- Modify: `xtx-integration-for-netatmo.php:56` (eine `naws_require()`-Zeile nach `class-naws-notify-rules.php`)
- Test: `tests/test-notifications-sanitize.php`

**Interfaces:**
- Consumes: `NAWS_Notify_Rules::catalog()`, `defaults()`, `to_base()`.
- Produces: Konstanten `OPTION_KEY = 'naws_notifications'`, `STATE_KEY = 'naws_notify_state'`, `LOG_KEY = 'naws_notify_log'`, `LOCK_KEY = 'naws_notify_lock'`, `LOG_MAX = 50`, `MAX_RECIPIENTS = 20`, `LOCK_TTL = 120`; `units(): array`; `get_settings(): array`; `sanitize( array $input ): array` (Schwellen in Basiseinheit); `from_form( string $recipients, array $rules, array $units ): array` (Schwellen in Anzeigeeinheit → Basis → `sanitize()`); `split_recipients( string $text ): array` (nicht-leere Token); `recipients(): array` (leer → Admin-Adresse).

- [ ] **Step 1: Test schreiben**

```php
<?php
/**
 * Tests fuer NAWS_Notifications::sanitize(), from_form(), split_recipients(),
 * recipients() und units(): die Option ist eine Whitelist ueber den Katalog,
 * Adressen gehen durch sanitize_email()+is_email(), Zahlen werden geklemmt,
 * Schwellen liegen in Basiseinheiten.
 *
 *   php tests/test-notifications-sanitize.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['naws_test_options'] = [];
function get_option( $k, $d = false ) { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function sanitize_email( $e )         { return preg_replace( '/[^a-z0-9._%+\-@]/i', '', trim( (string) $e ) ); }
function is_email( $e )               { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', (string) $e ); }
function absint( $v )                 { return abs( (int) $v ); }
function add_action( ...$a )          {}
require_once __DIR__ . '/i18n-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-notify-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-notifications.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$D = NAWS_Notify_Rules::defaults();

echo "\nsanitize(): Whitelist ueber den Katalog\n" . str_repeat( '-', 74 ) . "\n";
check( 'leer -> Vorgaben',                        NAWS_Notifications::sanitize( [] ), $D );
check( 'unbekannte Regel und unbekanntes Feld fallen weg', NAWS_Notifications::sanitize( [ 'rules' => [ 'moon' => [ 'enabled' => 1 ], 'battery' => [ 'enabled' => 1, 'colour' => 'red' ] ] ] )['rules']['battery'], [ 'enabled' => 1, 'threshold' => 20 ] );
check( 'enabled: "1" -> 1, fehlt -> 0, "0" -> 0', [ NAWS_Notifications::sanitize( [ 'rules' => [ 'frost' => [ 'enabled' => '1' ] ] ] )['rules']['frost']['enabled'], NAWS_Notifications::sanitize( [ 'rules' => [ 'frost' => [] ] ] )['rules']['frost']['enabled'], NAWS_Notifications::sanitize( [ 'rules' => [ 'frost' => [ 'enabled' => '0' ] ] ] )['rules']['frost']['enabled'] ], [ 1, 0, 0 ] );
$t = fn( string $rule, $v ) => NAWS_Notifications::sanitize( [ 'rules' => [ $rule => [ 'threshold' => $v ] ] ] )['rules'][ $rule ]['threshold'];
check( 'battery: 150 -> 99, 0 -> 1, "abc" -> 20, "35" -> 35', [ $t( 'battery', '150' ), $t( 'battery', '0' ), $t( 'battery', 'abc' ), $t( 'battery', '35' ) ], [ 99, 1, 20, 35 ] );
check( 'frost: -60 -> -50.0, "2.55" -> 2.6 (eine Stelle)', [ $t( 'frost', '-60' ), $t( 'frost', '2.55' ) ], [ -50.0, 2.6 ] );
check( 'gust: 0.5 -> 1.0, 999 -> 300.0',            [ $t( 'gust', '0.5' ), $t( 'gust', '999' ) ], [ 1.0, 300.0 ] );
check( 'rain: 600 -> 500.0, 0 -> 0.1',              [ $t( 'rain', '600' ), $t( 'rain', '0' ) ], [ 500.0, 0.1 ] );
$l = fn( string $rule, $v ) => NAWS_Notifications::sanitize( [ 'rules' => [ $rule => [ 'level' => $v ] ] ] )['rules'][ $rule ]['level'];
check( 'rf: medium bleibt, xxx -> low',            [ $l( 'rf', 'medium' ), $l( 'rf', 'xxx' ) ], [ 'medium', 'low' ] );
check( 'wifi: average bleibt, low -> bad',         [ $l( 'wifi', 'average' ), $l( 'wifi', 'low' ) ], [ 'average', 'bad' ] );
$m = fn( string $rule, $v ) => NAWS_Notifications::sanitize( [ 'rules' => [ $rule => [ 'minutes' => $v ] ] ] )['rules'][ $rule ]['minutes'];
check( 'minutes: 5 -> 10, 99999 -> 1440, "45" -> 45', [ $m( 'station_silent', '5' ), $m( 'module_silent', '99999' ), $m( 'station_silent', '45' ) ], [ 10, 1440, 45 ] );
check( 'Site-Regel kennt nur enabled',             NAWS_Notifications::sanitize( [ 'rules' => [ 'sync_failed' => [ 'enabled' => 1, 'threshold' => 5 ] ] ] )['rules']['sync_failed'], [ 'enabled' => 1 ] );

echo "\nEmpfaenger\n" . str_repeat( '-', 74 ) . "\n";
check( 'Text: Zeilen, Komma, Semikolon, Dublette, Ungueltiges', NAWS_Notifications::sanitize( [ 'recipients' => "a@x.de\r\nb@x.de, c@x.de; a@x.de\nkein-mail\n <d@x.de> " ] )['recipients'], [ 'a@x.de', 'b@x.de', 'c@x.de', 'd@x.de' ] );
check( 'Array (gespeichert) bleibt',              NAWS_Notifications::sanitize( [ 'recipients' => [ 'a@x.de', 'nope', 'b@x.de' ] ] )['recipients'], [ 'a@x.de', 'b@x.de' ] );
$many = implode( "\n", array_map( fn( $i ) => "u$i@x.de", range( 1, 25 ) ) );
check( 'hoechstens 20',                           count( NAWS_Notifications::sanitize( [ 'recipients' => $many ] )['recipients'] ), 20 );
check( 'split_recipients: nicht-leere Token',     NAWS_Notifications::split_recipients( "a@x.de\n\nnope,b@x.de; " ), [ 'a@x.de', 'nope', 'b@x.de' ] );
$GLOBALS['naws_test_options'] = [ 'admin_email' => 'admin@x.de' ];
check( 'recipients(): leer -> Admin-Adresse',     NAWS_Notifications::recipients(), [ 'admin@x.de' ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = [ 'recipients' => [ 'f@x.de' ] ];
check( 'recipients(): gespeicherte Liste',        NAWS_Notifications::recipients(), [ 'f@x.de' ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = 'kaputt';
check( 'get_settings(): beschaedigte Option -> Vorgaben', NAWS_Notifications::get_settings(), $D );

echo "\nfrom_form(): Anzeigeeinheit -> Basis\n" . str_repeat( '-', 74 ) . "\n";
$F = [ 'temperature_unit' => 'F', 'wind_unit' => 'mph', 'rain_unit' => 'in' ];
$s = NAWS_Notifications::from_form( "f@x.de", [ 'frost' => [ 'enabled' => '1', 'threshold' => '32' ], 'gust' => [ 'threshold' => '37.28' ], 'rain' => [ 'threshold' => '1' ], 'battery' => [ 'threshold' => '30' ] ], $F );
check( 'frost 32 F -> 0.0 C',                     $s['rules']['frost'], [ 'enabled' => 1, 'threshold' => 0.0 ] );
check( 'gust 37.28 mph -> 60.0 km/h',             $s['rules']['gust']['threshold'], 60.0 );
check( 'rain 1 in -> 25.4 mm',                    $s['rules']['rain']['threshold'], 25.4 );
check( 'Prozent bleibt',                          $s['rules']['battery']['threshold'], 30 );
check( 'Empfaenger aus dem Text',                 $s['recipients'], [ 'f@x.de' ] );
$GLOBALS['naws_test_options']['naws_settings'] = [ 'temperature_unit' => 'F', 'wind_unit' => 'kn' ];
check( 'units(): aus naws_settings mit Vorgaben', NAWS_Notifications::units(), [ 'temperature_unit' => 'F', 'wind_unit' => 'kn', 'rain_unit' => 'mm' ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-notifications-sanitize.php`
Expected: Fatal `Failed opening required '…/includes/class-naws-notifications.php'`.

- [ ] **Step 3: Klasse anlegen (erster Teil; Task 5 fügt Versand, Schnappschuss und Hooks an)**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * E-mail notifications: the WordPress side.
 *
 * Reads and cleans the option, builds the snapshot from the database, runs
 * NAWS_Notify_Rules::evaluate() after every fetch, sends the mail, keeps a
 * short log. The rules themselves live in NAWS_Notify_Rules and know
 * nothing about WordPress.
 *
 * @package NAWS
 * @since 2.0.0
 */
final class NAWS_Notifications {

    const OPTION_KEY     = 'naws_notifications';
    const STATE_KEY      = 'naws_notify_state';
    const LOG_KEY        = 'naws_notify_log';
    const LOCK_KEY       = 'naws_notify_lock';
    const LOG_MAX        = 50;
    const MAX_RECIPIENTS = 20;
    const LOCK_TTL       = 120;

    /** The display units from the plugin settings, always all three keys. */
    public static function units(): array {
        $o = get_option( 'naws_settings', [] );
        $o = is_array( $o ) ? $o : [];
        return [
            'temperature_unit' => ( $o['temperature_unit'] ?? 'C' ) === 'F' ? 'F' : 'C',
            'wind_unit'        => in_array( $o['wind_unit'] ?? 'kmh', [ 'kmh', 'ms', 'mph', 'kn' ], true ) ? $o['wind_unit'] : 'kmh',
            'rain_unit'        => ( $o['rain_unit'] ?? 'mm' ) === 'in' ? 'in' : 'mm',
        ];
    }

    /** The stored settings, cleaned and filled with defaults; a broken option yields the defaults. */
    public static function get_settings(): array {
        $raw = get_option( self::OPTION_KEY, [] );
        return self::sanitize( is_array( $raw ) ? $raw : [] );
    }

    /** The non-empty tokens of the recipients field: newline, comma or semicolon separated. */
    public static function split_recipients( string $text ): array {
        $parts = preg_split( '/[\r\n,;]+/', $text ) ?: [];
        return array_values( array_filter( array_map( 'trim', $parts ), static fn( $s ) => $s !== '' ) );
    }

    /**
     * Whitelist over the catalogue. Thresholds are taken as base units
     * (°C, km/h, mm); a form posts display units and goes through
     * from_form() first.
     */
    public static function sanitize( array $input ): array {
        $out     = NAWS_Notify_Rules::defaults();
        $catalog = NAWS_Notify_Rules::catalog();

        $raw = $input['recipients'] ?? [];
        if ( is_string( $raw ) ) {
            $raw = self::split_recipients( $raw );
        }
        $list = [];
        foreach ( (array) $raw as $addr ) {
            $addr = sanitize_email( trim( (string) $addr ) );
            if ( $addr !== '' && is_email( $addr ) && ! in_array( $addr, $list, true ) ) {
                $list[] = $addr;
            }
            if ( count( $list ) >= self::MAX_RECIPIENTS ) {
                break;
            }
        }
        $out['recipients'] = $list;

        $rules = is_array( $input['rules'] ?? null ) ? $input['rules'] : [];
        foreach ( $catalog as $id => $def ) {
            $r = is_array( $rules[ $id ] ?? null ) ? $rules[ $id ] : [];
            $out['rules'][ $id ]['enabled'] = empty( $r['enabled'] ) ? 0 : 1;
            switch ( $def['param'] ) {
                case 'threshold':
                    if ( isset( $r['threshold'] ) && is_numeric( $r['threshold'] ) ) {
                        if ( $def['kind'] === 'percent' ) {
                            $out['rules'][ $id ]['threshold'] = (int) max( $def['min'], min( $def['max'], absint( $r['threshold'] ) ) );
                        } else {
                            $out['rules'][ $id ]['threshold'] = round( max( (float) $def['min'], min( (float) $def['max'], (float) $r['threshold'] ) ), 1 );
                        }
                    }
                    break;
                case 'level':
                    if ( isset( $r['level'] ) && is_string( $r['level'] ) && isset( $def['levels'][ $r['level'] ] ) ) {
                        $out['rules'][ $id ]['level'] = $r['level'];
                    }
                    break;
                case 'minutes':
                    if ( isset( $r['minutes'] ) && is_numeric( $r['minutes'] ) ) {
                        $out['rules'][ $id ]['minutes'] = (int) max( $def['min'], min( $def['max'], absint( $r['minutes'] ) ) );
                    }
                    break;
            }
        }
        return $out;
    }

    /** What the admin form posts: recipients as text, thresholds in display units. */
    public static function from_form( string $recipients, array $rules, array $units ): array {
        $catalog = NAWS_Notify_Rules::catalog();
        foreach ( $rules as $id => $r ) {
            $def = $catalog[ $id ] ?? null;
            if ( $def && $def['param'] === 'threshold' && in_array( $def['kind'], [ 'temp', 'wind', 'rain' ], true )
                && is_array( $r ) && isset( $r['threshold'] ) && is_numeric( $r['threshold'] ) ) {
                $rules[ $id ]['threshold'] = NAWS_Notify_Rules::to_base( $def['kind'], (float) $r['threshold'], $units );
            }
        }
        return self::sanitize( [ 'recipients' => $recipients, 'rules' => $rules ] );
    }

    /** Where the mail goes: the stored list, or the site's admin address when it is empty. */
    public static function recipients(): array {
        $list = self::get_settings()['recipients'];
        if ( $list ) {
            return $list;
        }
        $admin = sanitize_email( (string) get_option( 'admin_email', '' ) );
        return ( $admin !== '' && is_email( $admin ) ) ? [ $admin ] : [];
    }
}
```

In `xtx-integration-for-netatmo.php` nach der Zeile `naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-notify-rules.php' );`:

```php
naws_require( NAWS_PLUGIN_DIR . 'includes/class-naws-notifications.php' );
```

- [ ] **Step 4: Test grün**

Run: `php tests/test-notifications-sanitize.php && php tests/test-main-requires.php && php -l includes/class-naws-notifications.php && vendor/bin/phpcs includes/class-naws-notifications.php`
Expected: `… 0 fehlgeschlagen`, beide; kein phpcs-Befund.

- [ ] **Step 5: Commit**

```bash
git add includes/class-naws-notifications.php xtx-integration-for-netatmo.php tests/test-notifications-sanitize.php
git commit -m "Notifications: the option — recipients, per-rule switches and thresholds, cleaned as a whitelist"
```

---

### Task 5: `NAWS_Notifications` — Labels, Mailtext, Schnappschuss, Lauf mit Lock, Protokoll, Testmail

**Files:**
- Modify: `includes/class-naws-notifications.php` (Methoden anfügen)
- Modify: `includes/class-naws-labels.php:268` (Block vor der schließenden Klammer des `switch`)
- Test: `tests/test-notifications-mail.php`

**Interfaces:**
- Consumes: `NAWS_Notify_Rules::evaluate()`, `catalog()`; `NAWS_Database::get_modules( true )`, `get_latest_readings()`; `NAWS_Cron::base_interval()` (wird in Task 6 öffentlich — bis dahin läuft nur der Test mit Stub), `NAWS_Cron::is_night_mode()`, `NAWS_Cron::get_polling_state()`; `NAWS_Helpers::format_value()`, `get_unit()`, `module_type_label()`; `naws_label()`.
- Produces: `init()`, `on_synced( $saved = 0 )`, `on_failed( $message = '', $errors = 0 )`, `snapshot(): array`, `compose( array $events ): array{subject: string, body: string}`, `format_measure( string $rule, $value ): string`, `format_threshold( string $rule, $threshold ): string`, `stamp( int $ts ): string`, `send( array $to, string $subject, string $body ): bool`, `send_test(): bool`, `status_rows(): array`, `log( array $entry ): void`, `get_log(): array`.
- Labels `ntf_*` in `naws_label()` (Liste in Step 3).

- [ ] **Step 1: Test schreiben**

```php
<?php
/**
 * Tests fuer den Mailtext, das Protokoll, die Testmail und den Lauf nach
 * einem gescheiterten Abruf: Betreff fuer einen und fuer mehrere Wechsel,
 * ein Absatz je Wechsel, Site-Regeln ohne Modulzeile, Link am Ende, kein
 * Zeilenumbruch im Betreff, hoechstens 50 Protokolleintraege, ein Lock
 * gegen Doppelmails.
 *
 *   php tests/test-notifications-mail.php
 *
 * @package NAWS
 */
define( 'ABSPATH', __DIR__ );
define( 'ENT_QUOTES_STUB', ENT_QUOTES );
$GLOBALS['naws_test_options'] = [ 'date_format' => 'd.m.Y', 'time_format' => 'H:i', 'admin_email' => 'admin@x.de' ];
$GLOBALS['naws_test_mails']   = [];
function get_option( $k, $d = false )        { return $GLOBALS['naws_test_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null )  { $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function add_option( $k, $v, $dep = '', $a = null ) { if ( isset( $GLOBALS['naws_test_options'][ $k ] ) ) { return false; } $GLOBALS['naws_test_options'][ $k ] = $v; return true; }
function delete_option( $k )                 { unset( $GLOBALS['naws_test_options'][ $k ] ); return true; }
function get_bloginfo( $k )                  { return 'Wetterstation &amp; Garten'; }
function wp_specialchars_decode( $s, $q = 0 ){ return html_entity_decode( $s, ENT_QUOTES ); }
function sanitize_text_field( $s )           { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_email( $e )                { return preg_replace( '/[^a-z0-9._%+\-@]/i', '', trim( (string) $e ) ); }
function is_email( $e )                      { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', (string) $e ); }
function absint( $v )                        { return abs( (int) $v ); }
function admin_url( $p = '' )                { return 'https://x.de/wp-admin/' . $p; }
function wp_date( $f, $t = null )            { return gmdate( $f, $t ?? time() ); }
function wp_mail( $to, $subject, $body )     { $GLOBALS['naws_test_mails'][] = [ $to, $subject, $body ]; return $GLOBALS['naws_test_mail_ok'] ?? true; }
function add_action( ...$a )                 {}
function get_locale()                        { return 'de_DE'; }
function determine_locale()                  { return 'de_DE'; }
require_once __DIR__ . '/i18n-stubs.php';
class NAWS_Helpers {
    public static function format_value( $p, $v ) { return round( $v, 1 ); }
    public static function get_unit( $p ) { return [ 'Temperature' => '°C', 'GustStrength' => 'km/h', 'sum_rain_24' => 'mm' ][ $p ] ?? ''; }
    public static function module_type_label( $t ) { return [ 'NAModule1' => 'Outdoor Module', 'NAModule4' => 'Indoor module' ][ $t ] ?? $t; }
}
class NAWS_Logger { public static $errors = []; public static function error( $c, $m, $x = [] ) { self::$errors[] = $m; } public static function warning( ...$a ) {} public static function info( ...$a ) {} }
class NAWS_Cron {
    public static function base_interval() { return 600; }
    public static function is_night_mode() { return false; }
    public static function get_polling_state() { return [ 'consecutive_errors' => 0 ]; }
}
require_once dirname( __DIR__ ) . '/includes/class-naws-notify-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-naws-notifications.php';

$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$T0 = 1789117800; $T1 = $T0 + 5 * 3600 + 600;
$ev = fn( array $o ) => $o + [ 'rule' => 'battery', 'kind' => 'raise', 'module_id' => 'gast', 'module_name' => 'Gast', 'module_type' => 'NAModule4', 'value' => 23, 'threshold' => 25, 'since' => $T0, 'now' => $T0, 'error' => '' ];
$LINK = 'Adjust rules: https://x.de/wp-admin/admin.php?page=naws-notifications';

echo "\ncompose(): ein Wechsel\n" . str_repeat( '-', 74 ) . "\n";
$m = NAWS_Notifications::compose( [ $ev( [] ) ] );
check( 'Betreff: Site-Name entschluesselt, Regel, Modul, Wert', $m['subject'], '[Wetterstation & Garten] Netatmo: Battery low – Gast (23 %)' );
check( 'Body: Absatz + Link', explode( "\n", trim( $m['body'] ) ), [ 'Warning: Battery low', 'Module: Gast (Indoor module)', 'Battery: 23 % (threshold 25 %)', 'Since: ' . gmdate( 'd.m.Y, H:i', $T0 ), '', $LINK ] );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'frost', 'kind' => 'clear', 'module_id' => 'aus', 'module_name' => 'Aussen', 'module_type' => 'NAModule1', 'value' => 2.4, 'threshold' => 0.0, 'now' => $T1 ] ) ] );
check( 'Entwarnung: Betreff',              $m['subject'], '[Wetterstation & Garten] Netatmo: All clear: Frost – Aussen (2.4 °C)' );
check( 'Entwarnung: von/bis',              explode( "\n", trim( $m['body'] ) ), [ 'All clear: Frost', 'Module: Aussen (Outdoor Module)', 'Temperature: 2.4 °C (threshold 0 °C)', 'From ' . gmdate( 'd.m.Y, H:i', $T0 ) . ' to ' . gmdate( 'd.m.Y, H:i', $T1 ), '', $LINK ] );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'sync_failed', 'module_id' => '', 'module_name' => '', 'module_type' => '', 'value' => 3, 'threshold' => null, 'error' => 'Resolving timed out' ] ) ] );
check( 'Site-Regel: keine Modulzeile, Fehlertext', explode( "\n", trim( $m['body'] ) ), [ 'Warning: Fetch failing', 'Errors in a row: 3', 'Error: Resolving timed out', 'Since: ' . gmdate( 'd.m.Y, H:i', $T0 ), '', $LINK ] );
check( 'Site-Regel: Betreff ohne Modul',   $m['subject'], '[Wetterstation & Garten] Netatmo: Fetch failing (3)' );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'rf', 'value' => 92, 'threshold' => 'low' ] ) ] );
check( 'Stufe: Wert roh, Schwelle benannt', explode( "\n", trim( $m['body'] ) )[2], 'Radio: 92 (threshold weak (≥ 90))' );
$m = NAWS_Notifications::compose( [ $ev( [ 'rule' => 'station_silent', 'module_id' => 'base', 'module_name' => 'Basis', 'module_type' => 'NAMain', 'value' => $T0 - 4000, 'threshold' => 60 ] ) ] );
check( 'Stille Basis: letzte Meldung als Zeit, Schwelle in Minuten', explode( "\n", trim( $m['body'] ) )[2], 'Last report: ' . gmdate( 'd.m.Y, H:i', $T0 - 4000 ) . ' (threshold 60 min)' );
$m = NAWS_Notifications::compose( [ $ev( [ 'module_name' => "Gast\nBcc: x@y.de" ] ) ] );
check( 'Betreff ohne Zeilenumbruch',       str_contains( $m['subject'], "\n" ), false );

echo "\ncompose(): mehrere Wechsel\n" . str_repeat( '-', 74 ) . "\n";
$m = NAWS_Notifications::compose( [ $ev( [] ), $ev( [ 'module_name' => 'Aussen', 'value' => 18 ] ), $ev( [ 'rule' => 'frost', 'kind' => 'clear', 'module_type' => 'NAModule1', 'value' => 2.0, 'threshold' => 0.0 ] ) ] );
check( 'Betreff zaehlt',                   $m['subject'], '[Wetterstation & Garten] Netatmo: 2 warnings, 1 all-clear' );
check( 'drei Absaetze, Leerzeile dazwischen', substr_count( $m['body'], "\n\n" ), 3 );

echo "\nformat_measure() / format_threshold()\n" . str_repeat( '-', 74 ) . "\n";
check( 'Prozent, Stufe, Temperatur, leer', [ NAWS_Notifications::format_measure( 'battery', 23 ), NAWS_Notifications::format_measure( 'rf', 92 ), NAWS_Notifications::format_measure( 'frost', -2.25 ), NAWS_Notifications::format_measure( 'frost', null ) ], [ '23 %', '92', '-2.3 °C', '' ] );
check( 'Schwellen',                        [ NAWS_Notifications::format_threshold( 'battery', 25 ), NAWS_Notifications::format_threshold( 'wifi', 'average' ), NAWS_Notifications::format_threshold( 'module_silent', 60 ), NAWS_Notifications::format_threshold( 'gust', 60.0 ), NAWS_Notifications::format_threshold( 'sync_failed', null ) ], [ '25 %', 'average or worse (≥ 71)', '60 min', '60 km/h', '' ] );

echo "\nProtokoll und Testmail\n" . str_repeat( '-', 74 ) . "\n";
for ( $i = 1; $i <= 52; $i++ ) { NAWS_Notifications::log( [ 'time' => $i, 'subject' => "s$i", 'to' => [], 'sent' => true, 'events' => [] ] ); }
$log = NAWS_Notifications::get_log();
check( 'hoechstens 50, neueste zuerst',    [ count( $log ), $log[0]['subject'], $log[49]['subject'] ], [ 50, 's52', 's3' ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOG_KEY ] = [];
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = [ 'recipients' => [ 'f@x.de', 'g@x.de' ], 'rules' => [ 'battery' => [ 'enabled' => 1, 'threshold' => 30 ], 'frost' => [ 'enabled' => 1 ] ] ];
$GLOBALS['naws_test_mails'] = [];
check( 'send_test(): gesendet',            NAWS_Notifications::send_test(), true );
[ $to, $subject, $body ] = $GLOBALS['naws_test_mails'][0];
check( 'Testmail: an die Liste, Betreff',  [ $to, $subject ], [ [ 'f@x.de', 'g@x.de' ], '[Wetterstation & Garten] Netatmo: Test mail' ] );
check( 'Testmail: eingeschaltete Regeln mit Schwelle', [ str_contains( $body, '- Battery low (30 %)' ), str_contains( $body, '- Frost (0 °C)' ), str_contains( $body, 'Gust' ), str_contains( $body, $LINK ) ], [ true, true, false, true ] );
check( 'Testmail im Protokoll ohne Wechsel', [ count( NAWS_Notifications::get_log() ), NAWS_Notifications::get_log()[0]['events'] ], [ 1, [] ] );
$GLOBALS['naws_test_mail_ok'] = false;
check( 'send(): false wird protokolliert', [ NAWS_Notifications::send( [ 'f@x.de' ], 'x', 'y' ), count( NAWS_Logger::$errors ) > 0 ], [ false, true ] );
$GLOBALS['naws_test_mail_ok'] = true;

echo "\nLauf nach gescheitertem Abruf, mit Lock\n" . str_repeat( '-', 74 ) . "\n";
$GLOBALS['naws_test_options'][ NAWS_Notifications::OPTION_KEY ] = [ 'recipients' => [ 'f@x.de' ], 'rules' => [ 'sync_failed' => [ 'enabled' => 1 ] ] ];
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOG_KEY ] = [];
$GLOBALS['naws_test_mails'] = [];
NAWS_Notifications::on_failed( 'Resolving timed out', 3 );
check( 'dritter Fehler: eine Mail, Zustand aktiv', [ count( $GLOBALS['naws_test_mails'] ), array_keys( $GLOBALS['naws_test_options'][ NAWS_Notifications::STATE_KEY ] ), $GLOBALS['naws_test_mails'][0][1] ], [ 1, [ 'sync_failed|site' ], '[Wetterstation & Garten] Netatmo: Fetch failing (3)' ] );
NAWS_Notifications::on_failed( 'Resolving timed out', 4 );
check( 'vierter Fehler: keine zweite Mail', count( $GLOBALS['naws_test_mails'] ), 1 );
check( 'Lock wieder frei',                 isset( $GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] ), false );
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] = time();
NAWS_Notifications::on_failed( 'x', 9 );
check( 'frischer Lock: Lauf uebersprungen, Lock bleibt', [ count( $GLOBALS['naws_test_mails'] ), isset( $GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] ) ], [ 1, true ] );
$GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] = time() - 500;
$GLOBALS['naws_test_options'][ NAWS_Notifications::STATE_KEY ] = [];
NAWS_Notifications::on_failed( 'x', 3 );
check( 'verwaister Lock (>120 s) wird uebernommen', [ count( $GLOBALS['naws_test_mails'] ), isset( $GLOBALS['naws_test_options'][ NAWS_Notifications::LOCK_KEY ] ) ], [ 2, false ] );

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-notifications-mail.php`
Expected: Fatal `Call to undefined method NAWS_Notifications::compose()`.

- [ ] **Step 3: Labels in `includes/class-naws-labels.php` — vor der schließenden `}` des `switch` (nach `case 'card_temp_max'`)**

```php

        // E-mail notifications (since 2.0.0): rule names, descriptions, status and mail texts.
        case 'ntf_rule_battery':                   return __( 'Battery low', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_rf':                        return __( 'Weak radio link to the base station', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_wifi':                      return __( 'Poor Wi-Fi at the base station', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_station_silent':            return __( 'Base station not reporting', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_module_silent':             return __( 'Module not reporting', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_sync_failed':               return __( 'Fetch failing', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_auth_required':             return __( 'Credentials expired', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_frost':                     return _x( 'Frost', 'notification rule', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_gust':                      return _x( 'Gust', 'notification rule', 'xtx-integration-for-netatmo' );
        case 'ntf_rule_rain':                      return __( 'Rain in 24 hours', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_battery':                   return __( 'The battery of a module is below the threshold. Applies to every active module.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_rf':                        return __( 'The radio signal from a module to the base station is at or below the chosen level for 30 minutes.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_wifi':                      return __( 'The Wi-Fi of the base station is at or below the chosen level for 30 minutes.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_station_silent':            return __( 'The base station is unreachable or has not reported to Netatmo for longer than the set minutes.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_module_silent':             return __( 'A module is unreachable or has not reported to the base station for longer than the set minutes.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_sync_failed':               return __( 'Three fetches in a row have failed.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_auth_required':             return __( 'The Netatmo connection needs a new login under Settings.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_frost':                     return __( 'The outdoor temperature is at or below the threshold.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_gust':                      return __( 'A gust at or above the threshold.', 'xtx-integration-for-netatmo' );
        case 'ntf_desc_rain':                      return __( 'The rain of the last 24 hours is at or above the threshold. No all-clear: one mail per rain event.', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_battery':                return _x( 'Battery', 'notification measure', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_rf':                     return __( 'Radio', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_wifi':                   return __( 'Wi-Fi', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_station_silent':         return __( 'Last report', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_module_silent':          return __( 'Last message', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_sync_failed':            return __( 'Errors in a row', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_auth_required':          return '';
        case 'ntf_measure_frost':                  return _x( 'Temperature', 'notification measure', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_gust':                   return _x( 'Gust', 'notification measure', 'xtx-integration-for-netatmo' );
        case 'ntf_measure_rain':                   return __( 'Rain (24 h)', 'xtx-integration-for-netatmo' );
        case 'ntf_level_low':                      return _x( 'weak', 'radio level', 'xtx-integration-for-netatmo' );
        case 'ntf_level_medium':                   return _x( 'medium or worse', 'radio level', 'xtx-integration-for-netatmo' );
        case 'ntf_level_bad':                      return _x( 'poor', 'wifi level', 'xtx-integration-for-netatmo' );
        case 'ntf_level_average':                  return _x( 'average or worse', 'wifi level', 'xtx-integration-for-netatmo' );
        case 'ntf_status_ok':                      return _x( 'ok', 'notification status', 'xtx-integration-for-netatmo' );
        case 'ntf_status_active':                  return /* translators: %s: date and time */ __( 'Warning since %s', 'xtx-integration-for-netatmo' );
        case 'ntf_status_pending':                 return /* translators: %s: date and time */ __( 'Hold running since %s', 'xtx-integration-for-netatmo' );
        case 'ntf_status_suspended':               return __( 'suspended', 'xtx-integration-for-netatmo' );
        case 'ntf_reason_disabled':                return __( 'rule off', 'xtx-integration-for-netatmo' );
        case 'ntf_reason_missing':                 return __( 'no value yet', 'xtx-integration-for-netatmo' );
        case 'ntf_reason_stale':                   return __( 'reading outdated', 'xtx-integration-for-netatmo' );
        case 'ntf_reason_station_silent':          return __( 'base station silent', 'xtx-integration-for-netatmo' );
        case 'ntf_reason_no_sync':                 return __( 'no fetch', 'xtx-integration-for-netatmo' );
        case 'ntf_warning':                        return _x( 'Warning', 'notification mail', 'xtx-integration-for-netatmo' );
        case 'ntf_all_clear':                      return __( 'All clear', 'xtx-integration-for-netatmo' );
        case 'ntf_module':                         return _x( 'Module', 'notification mail', 'xtx-integration-for-netatmo' );
        case 'ntf_threshold':                      return /* translators: %s: the threshold with its unit */ __( 'threshold %s', 'xtx-integration-for-netatmo' );
        case 'ntf_since':                          return /* translators: %s: date and time */ __( 'Since: %s', 'xtx-integration-for-netatmo' );
        case 'ntf_from_to':                        return /* translators: 1: start date and time, 2: end date and time */ __( 'From %1$s to %2$s', 'xtx-integration-for-netatmo' );
        case 'ntf_manage':                         return __( 'Adjust rules', 'xtx-integration-for-netatmo' );
        case 'ntf_sync_error':                     return /* translators: %s: the error message of the failed fetch */ __( 'Error: %s', 'xtx-integration-for-netatmo' );
        case 'ntf_auth_hint':                      return __( 'Please connect to Netatmo again under XTX Netatmo → Settings.', 'xtx-integration-for-netatmo' );
        case 'ntf_test_subject':                   return __( 'Test mail', 'xtx-integration-for-netatmo' );
        case 'ntf_test_intro':                     return /* translators: %s: the site name */ __( 'This mail was sent by hand from %s to check the delivery.', 'xtx-integration-for-netatmo' );
        case 'ntf_test_rules':                     return __( 'Rules switched on:', 'xtx-integration-for-netatmo' );
        case 'ntf_test_none':                      return __( '(none)', 'xtx-integration-for-netatmo' );
```

- [ ] **Step 4: Methoden an `NAWS_Notifications` anfügen (vor der schließenden Klammer der Klasse)**

```php
    // ── Hooks ───────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'naws_data_synced', [ __CLASS__, 'on_synced' ] );
        add_action( 'naws_sync_failed', [ __CLASS__, 'on_failed' ], 10, 2 );
    }

    /** After a successful fetch: fresh snapshot, every rule. */
    public static function on_synced( $saved = 0 ): void {
        self::run( [ 'sync' => 'ok', 'consecutive_errors' => 0, 'auth_required' => (bool) get_option( 'naws_auth_required' ), 'error' => '' ] );
    }

    /** After a failed fetch: no snapshot, only the two site rules move. */
    public static function on_failed( $message = '', $errors = 0 ): void {
        self::run( [
            'sync'               => 'failed',
            'consecutive_errors' => (int) $errors,
            'auth_required'      => (bool) get_option( 'naws_auth_required' ) || (string) $message === 'auth_required',
            'error'              => (string) $message,
        ] );
    }

    /** One evaluation run. Never throws: the cron callback must survive it. */
    private static function run( array $ctx ): void {
        $locked = false;
        try {
            $settings = self::get_settings();
            $state    = get_option( self::STATE_KEY, [] );
            $state    = is_array( $state ) ? $state : [];
            if ( ! self::any_enabled( $settings ) && ! $state ) {
                return;
            }
            $locked = self::lock();
            if ( ! $locked ) {
                return;
            }
            $ctx['interval'] = self::interval();
            $snapshot        = ( $ctx['sync'] ?? 'ok' ) === 'ok' ? self::snapshot() : [ 'modules' => [] ];
            $result          = NAWS_Notify_Rules::evaluate( $snapshot, $settings, $state, time(), $ctx );
            update_option( self::STATE_KEY, $result['state'], false );
            if ( $result['events'] ) {
                $mail = self::compose( $result['events'] );
                $to   = self::recipients();
                $sent = self::send( $to, $mail['subject'], $mail['body'] );
                self::log( [
                    'time'    => time(),
                    'subject' => $mail['subject'],
                    'to'      => $to,
                    'sent'    => $sent,
                    'events'  => array_map( static fn( $e ) => [ 'rule' => $e['rule'], 'kind' => $e['kind'], 'module_name' => $e['module_name'], 'value' => $e['value'] ], $result['events'] ),
                ] );
            }
        } catch ( \Throwable $e ) {
            NAWS_Logger::error( 'notify', 'Notification run failed: ' . $e->getMessage() );
        } finally {
            if ( $locked ) {
                self::unlock();
            }
        }
    }

    private static function any_enabled( array $settings ): bool {
        foreach ( $settings['rules'] as $r ) {
            if ( ! empty( $r['enabled'] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * add_option() is the one check WordPress offers that a second request
     * cannot overtake without an object cache: it fails when the row exists.
     * A lock older than LOCK_TTL is an orphan from a crashed run.
     */
    private static function lock(): bool {
        $stamp = (int) get_option( self::LOCK_KEY, 0 );
        if ( $stamp && time() - $stamp > self::LOCK_TTL ) {
            delete_option( self::LOCK_KEY );
        }
        return (bool) add_option( self::LOCK_KEY, time(), '', false );
    }

    private static function unlock(): void {
        delete_option( self::LOCK_KEY );
    }

    /** The effective fetch interval in seconds; night mode skips every other run. */
    private static function interval(): int {
        $base = (int) NAWS_Cron::base_interval();
        return NAWS_Cron::is_night_mode() ? 2 * $base : $base;
    }

    // ── Snapshot ────────────────────────────────────────────────────

    /** Active modules with their status columns and latest readings, keyed by module id. */
    public static function snapshot(): array {
        $out = [];
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            $id = (string) ( $m['module_id'] ?? '' );
            if ( $id === '' ) {
                continue;
            }
            $out[ $id ] = [
                'module_id'         => $id,
                'station_id'        => (string) ( $m['station_id'] ?? '' ),
                'module_name'       => (string) ( $m['module_name'] ?? '' ),
                'module_type'       => (string) ( $m['module_type'] ?? '' ),
                'battery_percent'   => self::int_or_null( $m['battery_percent'] ?? null ),
                'rf_status'         => self::int_or_null( $m['rf_status'] ?? null ),
                'wifi_status'       => self::int_or_null( $m['wifi_status'] ?? null ),
                'reachable'         => self::int_or_null( $m['reachable'] ?? null ),
                'last_status_store' => self::int_or_null( $m['last_status_store'] ?? null ),
                'last_seen'         => self::int_or_null( $m['last_seen'] ?? null ),
                'last_message'      => self::int_or_null( $m['last_message'] ?? null ),
                'readings'          => [],
            ];
        }
        foreach ( NAWS_Database::get_latest_readings() as $r ) {
            $id = (string) ( $r['module_id'] ?? '' );
            if ( isset( $out[ $id ] ) && isset( $r['parameter'], $r['value'], $r['recorded_at'] ) ) {
                $out[ $id ]['readings'][ (string) $r['parameter'] ] = [ 'value' => (float) $r['value'], 'at' => (int) $r['recorded_at'] ];
            }
        }
        return [ 'modules' => $out ];
    }

    private static function int_or_null( $v ): ?int {
        return ( $v === null || $v === '' ) ? null : (int) $v;
    }

    /** The status rows for the admin page: a dry run, nothing saved, nothing sent. */
    public static function status_rows(): array {
        $state = get_option( self::STATE_KEY, [] );
        $poll  = NAWS_Cron::get_polling_state();
        $ctx   = [
            'interval'           => self::interval(),
            'sync'               => 'ok',
            'consecutive_errors' => (int) ( $poll['consecutive_errors'] ?? 0 ),
            'auth_required'      => (bool) get_option( 'naws_auth_required' ),
            'error'              => '',
        ];
        return NAWS_Notify_Rules::evaluate( self::snapshot(), self::get_settings(), is_array( $state ) ? $state : [], time(), $ctx )['rows'];
    }

    // ── Mail ────────────────────────────────────────────────────────

    /** Subject and plain-text body for a list of changes from one run. */
    public static function compose( array $events ): array {
        $site   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
        $raises = count( array_filter( $events, static fn( $e ) => ( $e['kind'] ?? '' ) === 'raise' ) );
        $clears = count( $events ) - $raises;

        if ( count( $events ) === 1 ) {
            $e     = $events[0];
            $title = naws_label( 'ntf_rule_' . $e['rule'] );
            if ( $e['module_name'] !== '' ) {
                $title .= ' – ' . $e['module_name'];
            }
            $shown = self::format_measure( $e['rule'], $e['value'] );
            if ( $shown !== '' ) {
                $title .= ' (' . $shown . ')';
            }
            $what = $e['kind'] === 'clear' ? naws_label( 'ntf_all_clear' ) . ': ' . $title : $title;
        } else {
            $parts = [];
            if ( $raises ) {
                /* translators: %d: number of warnings in one mail */
                $parts[] = sprintf( _n( '%d warning', '%d warnings', $raises, 'xtx-integration-for-netatmo' ), $raises );
            }
            if ( $clears ) {
                /* translators: %d: number of all-clears in one mail */
                $parts[] = sprintf( _n( '%d all-clear', '%d all-clears', $clears, 'xtx-integration-for-netatmo' ), $clears );
            }
            $what = implode( ', ', $parts );
        }

        $subject = sanitize_text_field( sprintf( '[%s] Netatmo: %s', $site, $what ) );
        $blocks  = [];
        foreach ( $events as $e ) {
            $blocks[] = self::event_block( $e );
        }
        $body = implode( "\n\n", $blocks ) . "\n\n" . naws_label( 'ntf_manage' ) . ': ' . admin_url( 'admin.php?page=naws-notifications' ) . "\n";
        return [ 'subject' => $subject, 'body' => $body ];
    }

    /** One paragraph of the body: what, which module, value and threshold, since when. */
    private static function event_block( array $e ): string {
        $lines   = [];
        $lines[] = ( $e['kind'] === 'clear' ? naws_label( 'ntf_all_clear' ) : naws_label( 'ntf_warning' ) ) . ': ' . naws_label( 'ntf_rule_' . $e['rule'] );
        if ( $e['module_name'] !== '' ) {
            $lines[] = naws_label( 'ntf_module' ) . ': ' . $e['module_name'] . ' (' . NAWS_Helpers::module_type_label( $e['module_type'] ) . ')';
        }
        if ( $e['rule'] === 'sync_failed' ) {
            $lines[] = naws_label( 'ntf_measure_sync_failed' ) . ': ' . (int) $e['value'];
            if ( (string) $e['error'] !== '' ) {
                $lines[] = sprintf( naws_label( 'ntf_sync_error' ), $e['error'] );
            }
        } elseif ( $e['rule'] === 'auth_required' ) {
            $lines[] = naws_label( 'ntf_auth_hint' );
        } else {
            $shown = self::format_measure( $e['rule'], $e['value'] );
            $thr   = self::format_threshold( $e['rule'], $e['threshold'] );
            if ( $shown !== '' ) {
                $lines[] = naws_label( 'ntf_measure_' . $e['rule'] ) . ': ' . $shown . ( $thr !== '' ? ' (' . sprintf( naws_label( 'ntf_threshold' ), $thr ) . ')' : '' );
            }
        }
        $lines[] = $e['kind'] === 'clear'
            ? sprintf( naws_label( 'ntf_from_to' ), self::stamp( (int) $e['since'] ), self::stamp( (int) $e['now'] ) )
            : sprintf( naws_label( 'ntf_since' ), self::stamp( (int) $e['since'] ) );
        return implode( "\n", $lines );
    }

    /** A measured value the way the mail and the page show it; '' when there is none. */
    public static function format_measure( string $rule, $value ): string {
        if ( $value === null || $value === '' ) {
            return '';
        }
        $def = NAWS_Notify_Rules::catalog()[ $rule ] ?? null;
        if ( ! $def ) {
            return '';
        }
        switch ( $def['kind'] ) {
            case 'percent': return (int) $value . ' %';
            case 'level':   return (string) (int) $value;
            case 'minutes': return self::stamp( (int) $value );
            case 'temp':
            case 'wind':
            case 'rain':
                $p = $def['reading'];
                return NAWS_Helpers::format_value( $p, (float) $value ) . ' ' . NAWS_Helpers::get_unit( $p );
        }
        return (string) $value;
    }

    /** A threshold with its unit or level name; '' for rules without one. */
    public static function format_threshold( string $rule, $threshold ): string {
        if ( $threshold === null || $threshold === '' ) {
            return '';
        }
        $def = NAWS_Notify_Rules::catalog()[ $rule ] ?? null;
        if ( ! $def || $def['param'] === '' ) {
            return '';
        }
        switch ( $def['kind'] ) {
            case 'percent': return (int) $threshold . ' %';
            case 'minutes': return (int) $threshold . ' min';
            case 'level':
                $on = $def['levels'][ $threshold ][0] ?? $def['levels'][ $def['default'] ][0];
                return naws_label( 'ntf_level_' . $threshold ) . ' (≥ ' . (int) $on . ')';
            case 'temp':
            case 'wind':
            case 'rain':
                $p = $def['reading'];
                return NAWS_Helpers::format_value( $p, (float) $threshold ) . ' ' . NAWS_Helpers::get_unit( $p );
        }
        return (string) $threshold;
    }

    /** Date and time in the site's format and timezone. */
    public static function stamp( int $ts ): string {
        return wp_date( (string) get_option( 'date_format', 'd.m.Y' ) . ', ' . (string) get_option( 'time_format', 'H:i' ), $ts );
    }

    public static function send( array $to, string $subject, string $body ): bool {
        if ( ! $to ) {
            NAWS_Logger::error( 'notify', 'No recipient for: ' . $subject );
            return false;
        }
        $ok = (bool) wp_mail( $to, $subject, $body );
        if ( ! $ok ) {
            NAWS_Logger::error( 'notify', 'wp_mail() returned false for: ' . $subject );
        }
        return $ok;
    }

    /** The test mail from the admin page, in the site's language, listing the rules switched on. */
    public static function send_test(): bool {
        $switched = ( determine_locale() !== get_locale() ) ? (bool) switch_to_locale( get_locale() ) : false;
        try {
            $settings = self::get_settings();
            $site     = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
            $lines    = [ sprintf( naws_label( 'ntf_test_intro' ), $site ), self::stamp( time() ), '', naws_label( 'ntf_test_rules' ) ];
            $any      = false;
            foreach ( NAWS_Notify_Rules::catalog() as $id => $def ) {
                $cfg = $settings['rules'][ $id ];
                if ( empty( $cfg['enabled'] ) ) {
                    continue;
                }
                $any     = true;
                $thr     = $def['param'] !== '' ? self::format_threshold( $id, $cfg[ $def['param'] ] ) : '';
                $lines[] = '- ' . naws_label( 'ntf_rule_' . $id ) . ( $thr !== '' ? ' (' . $thr . ')' : '' );
            }
            if ( ! $any ) {
                $lines[] = naws_label( 'ntf_test_none' );
            }
            $subject = sanitize_text_field( sprintf( '[%s] Netatmo: %s', $site, naws_label( 'ntf_test_subject' ) ) );
            $body    = implode( "\n", $lines ) . "\n\n" . naws_label( 'ntf_manage' ) . ': ' . admin_url( 'admin.php?page=naws-notifications' ) . "\n";
            $to      = self::recipients();
            $sent    = self::send( $to, $subject, $body );
            self::log( [ 'time' => time(), 'subject' => $subject, 'to' => $to, 'sent' => $sent, 'events' => [] ] );
            return $sent;
        } finally {
            if ( $switched ) {
                restore_previous_locale();
            }
        }
    }

    // ── Log ─────────────────────────────────────────────────────────

    public static function log( array $entry ): void {
        $log = get_option( self::LOG_KEY, [] );
        $log = is_array( $log ) ? $log : [];
        array_unshift( $log, $entry );
        update_option( self::LOG_KEY, array_slice( $log, 0, self::LOG_MAX ), false );
    }

    public static function get_log(): array {
        $log = get_option( self::LOG_KEY, [] );
        return is_array( $log ) ? $log : [];
    }
```

- [ ] **Step 5: Test grün**

Run: `php tests/test-notifications-mail.php && php tests/test-notifications-sanitize.php && php -l includes/class-naws-notifications.php && php -l includes/class-naws-labels.php && vendor/bin/phpcs includes/class-naws-notifications.php includes/class-naws-labels.php`
Expected: `… 0 fehlgeschlagen`; kein phpcs-Befund. Falls phpcs `_n()` ohne `translators`-Kommentar bemängelt: der Kommentar steht direkt über dem `sprintf( _n( … ) )`, wie oben.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-notifications.php includes/class-naws-labels.php tests/test-notifications-mail.php
git commit -m "Notifications: the mail — one paragraph per change, all-clears, test mail, log, lock"
```

---

### Task 6: Cron-Anbindung und Bootstrap — `naws_sync_failed`, `base_interval()` öffentlich, `init()`

**Files:**
- Modify: `includes/class-naws-cron.php:88` (`base_interval()`), `:341-350` (`run_fetch()` catch), `:366-370` (Zweig „Neuanmeldung nötig"), `:382-386` (Fehlerzweig)
- Modify: `xtx-integration-for-netatmo.php:182-185` (`init()`: nach `NAWS_Rest_API::init();`)
- Test: `tests/test-notify-structure.php`

**Interfaces:**
- Produces: Aktion `do_action( 'naws_sync_failed', string $message, int $consecutive_errors )` an allen drei Stellen, an denen `record_error()` steht; `NAWS_Cron::base_interval()` ist `public static`; `NAWS_Notifications::init()` läuft in `NAWS_Plugin::init()` bei jedem Request (auch Cron, auch Frontend).

- [ ] **Step 1: Test schreiben**

```php
<?php
/**
 * Strukturtests fuer die Benachrichtigungen: was sich mit Stubs nicht
 * pruefen laesst, wird am Quelltext geprueft — dass der Cron die neue
 * Aktion an jeder Fehlerstelle feuert, dass der Bootstrap die Klasse
 * einhaengt, und (ab Task 7) dass Handler und View die Review-Regeln
 * einhalten: Nonce + Capability, sichtbare Sanitierung, Nonce im
 * $_GET-Vergleich, Escaping an jedem echo, kein ob_start, kein Inline-Skript.
 *
 *   php tests/test-notify-structure.php
 *
 * @package NAWS
 */
$PLUGIN = dirname( __DIR__ ) . '/';
$passed = 0; $failed = 0;
function check( string $name, $got, $want ): void {
    global $passed, $failed;
    if ( $got === $want ) { $passed++; printf( "  ok    %s\n", $name ); return; }
    $failed++;
    printf( "  FAIL  %s\n          erwartet %s, ist %s\n", $name, var_export( $want, true ), var_export( $got, true ) );
}
$cron = file_get_contents( $PLUGIN . 'includes/class-naws-cron.php' );
$main = file_get_contents( $PLUGIN . 'xtx-integration-for-netatmo.php' );

echo "\nCron und Bootstrap\n" . str_repeat( '-', 74 ) . "\n";
check( 'naws_sync_failed an drei Stellen',          preg_match_all( "/do_action\(\s*'naws_sync_failed'/", $cron ), 3 );
check( 'jede Stelle direkt nach record_error()',    preg_match_all( "/self::record_error\(\);\s*\n\s*do_action\(\s*'naws_sync_failed'/", $cron ), 3 );
check( 'base_interval() ist oeffentlich',           preg_match( '/public static function base_interval\(\)/', $cron ), 1 );
check( 'Bootstrap haengt die Benachrichtigungen ein', preg_match( '/NAWS_Rest_API::init\(\);\s*\n\s*NAWS_Notifications::init\(\);/', $main ), 1 );
check( 'beide Klassen werden geladen',              [ str_contains( $main, "includes/class-naws-notify-rules.php'" ), str_contains( $main, "includes/class-naws-notifications.php'" ) ], [ true, true ] );

// ---- Teil 2 (Task 7: Admin und View) wird hier eingefuegt ----

printf( "\n%d ok, %d fehlgeschlagen\n", $passed, $failed );
exit( $failed ? 1 : 0 );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-notify-structure.php`
Expected: vier FAILs (nur „beide Klassen werden geladen" ist grün).

- [ ] **Step 3: Cron ändern**

`includes/class-naws-cron.php:88`: `private static function base_interval()` → `public static function base_interval()`.

`run_fetch()` — der `catch`-Block wird zu:

```php
        } catch ( \Throwable $e ) {
            // NEVER let an uncaught exception kill the cron callback.
            $this->log( 'error', 'Uncaught exception: ' . $e->getMessage() );
            NAWS_Logger::error( 'cron', 'Uncaught exception in run_fetch: ' . $e->getMessage() );
            self::record_error();
            do_action( 'naws_sync_failed', 'Uncaught exception: ' . $e->getMessage(), (int) self::get_polling_state()['consecutive_errors'] );
        }
```

`do_fetch()` — Zweig „Neuanmeldung nötig":

```php
        if ( get_option( 'naws_auth_required' ) ) {
            $this->log( 'error', 'Re-authentication required. Please visit XTX Netatmo → Settings.' );
            self::record_error();
            do_action( 'naws_sync_failed', 'auth_required', (int) self::get_polling_state()['consecutive_errors'] );
            return;
        }
```

`do_fetch()` — Fehlerzweig nach `sync_current_data()`:

```php
        if ( is_wp_error( $result ) ) {
            $this->log( 'error', $result->get_error_message() );
            NAWS_Logger::error( 'cron', 'Sync failed: ' . $result->get_error_message() );
            self::record_error();
            do_action( 'naws_sync_failed', $result->get_error_message(), (int) self::get_polling_state()['consecutive_errors'] );
        } else {
```

Direkt über der Konstante `HOOK_FETCH` einen Satz Doku (die Aktion ist öffentliche Schnittstelle):

```php
    /**
     * Actions fired by the fetch: `naws_data_synced` (int $readings_saved)
     * after a successful sync, `naws_sync_failed` (string $message,
     * int $consecutive_errors) after a failed one — including the
     * "re-authentication required" case, where $message is 'auth_required'.
     */
```

- [ ] **Step 4: Bootstrap ändern**

`xtx-integration-for-netatmo.php`, in `init()`:

```php
        // Always boot cron and shortcodes
        NAWS_Cron::instance();
        NAWS_Shortcodes::instance();
        NAWS_Ajax::instance();
        NAWS_Rest_API::init();
        NAWS_Notifications::init();
```

- [ ] **Step 5: Test grün, Suite grün**

Run: `php tests/test-notify-structure.php && php tests/test-cron-polling.php && php tests/test-main-requires.php && php -l includes/class-naws-cron.php && php -l xtx-integration-for-netatmo.php && vendor/bin/phpcs includes/class-naws-cron.php xtx-integration-for-netatmo.php`
Expected: `… 0 fehlgeschlagen` dreimal; kein phpcs-Befund.

- [ ] **Step 6: Commit**

```bash
git add includes/class-naws-cron.php xtx-integration-for-netatmo.php tests/test-notify-structure.php
git commit -m "Cron: fire naws_sync_failed wherever a fetch fails; boot the notifications"
```

---

### Task 7: Backend-Seite „Benachrichtigungen" — Untermenü, zwei Handler, View

**Files:**
- Modify: `includes/class-naws-admin.php:16-30` (Konstruktor), `:52` (Untermenü nach `naws-modules`), Methoden nach `page_modules()` (Zeile ~506)
- Create: `admin/views/notifications.php`
- Test: `tests/test-notify-structure.php` (Teil 2 an der markierten Stelle)

**Interfaces:**
- Consumes: `NAWS_Notifications::get_settings()`, `units()`, `status_rows()`, `get_log()`, `from_form()`, `split_recipients()`, `send_test()`, `format_measure()`, `stamp()`, `OPTION_KEY`; `NAWS_Notify_Rules::catalog()`, `to_display()`, `unit_label()`; `naws_label()`; `NAWS_Helpers::module_type_label()`.
- Produces: Seite `admin.php?page=naws-notifications`; Aktionen `admin_post_naws_save_notifications` (Nonce `naws_save_notifications`) und `admin_post_naws_test_notification` (Nonce `naws_test_notification`); Rückmeldungen `updated=1`, `dropped=<n>`, `test=1|0`, jeweils mit `_wpnonce` für `naws_notifications_notice`.

- [ ] **Step 1: Test-Teil 2 an der Stelle `// ---- Teil 2 (Task 7: Admin und View) wird hier eingefuegt ----` einfügen**

```php
echo "\nAdmin-Klasse\n" . str_repeat( '-', 74 ) . "\n";
$admin = file_get_contents( $PLUGIN . 'includes/class-naws-admin.php' );
check( 'Untermenue direkt nach Modules',            preg_match( "/'naws-modules',\s*\[ \\\$this, 'page_modules' \] \);\s*\n\s*add_submenu_page\( 'naws-dashboard', __\( 'Notifications', 'xtx-integration-for-netatmo' \),.*'naws-notifications',\s*\[ \\\$this, 'page_notifications' \] \);/", $admin ), 1 );
check( 'beide Handler registriert',                  [ str_contains( $admin, "add_action( 'admin_post_naws_save_notifications', [ \$this, 'handle_save_notifications' ] );" ), str_contains( $admin, "add_action( 'admin_post_naws_test_notification', [ \$this, 'handle_test_notification' ] );" ) ], [ true, true ] );
foreach ( [ 'handle_save_notifications' => 'naws_save_notifications', 'handle_test_notification' => 'naws_test_notification' ] as $fn => $nonce ) {
    check( "$fn: Nonce, dann Capability",            preg_match( "/function $fn\(\) \{\s*\n\s*check_admin_referer\( '$nonce' \);\s*\n\s*if \( ! current_user_can\( 'manage_options' \) \) wp_die\( 'Unauthorized' \);/", $admin ), 1 );
}
check( 'Empfaenger sichtbar per sanitize_textarea_field', str_contains( $admin, "sanitize_textarea_field( wp_unslash( \$_POST['naws_notifications']['recipients'] ) )" ), true );
check( 'Regeln sichtbar per map_deep',               str_contains( $admin, "map_deep( wp_unslash( \$_POST['naws_notifications']['rules'] ), 'sanitize_text_field' )" ), true );
check( 'Rueckleitung per wp_safe_redirect + exit in beiden Handlern', preg_match_all( '/wp_safe_redirect\( \$url \);\s*\n\s*exit;/', $admin ) >= 2, true );

echo "\nView\n" . str_repeat( '-', 74 ) . "\n";
$view  = file_get_contents( $PLUGIN . 'admin/views/notifications.php' );
$lines = explode( "\n", $view );
check( 'kein ob_start, kein script, kein style',      [ str_contains( $view, 'ob_start' ), stripos( $view, '<script' ) !== false, stripos( $view, '<style' ) !== false ], [ false, false, false ] );
check( 'zwei Formulare mit Nonce',                   [ substr_count( $view, 'method="post"' ), str_contains( $view, "wp_nonce_field( 'naws_save_notifications' )" ), str_contains( $view, "wp_nonce_field( 'naws_test_notification' )" ) ], [ 2, true, true ] );
check( 'jeder $_GET-Zugriff steht bei einem Nonce-Check', preg_match_all( "/isset\( \\\$_GET\['(updated|dropped|test)'\] \) && wp_verify_nonce\( sanitize_text_field\( wp_unslash\( \\\$_GET\['_wpnonce'\] \?\? '' \) \), 'naws_notifications_notice' \)/", $view ), 3 );
$naked = [];
foreach ( $lines as $n => $l ) {
    if ( preg_match( '/\becho\b/', $l ) && ! preg_match( '/esc_html|esc_attr|esc_url|esc_textarea|wp_kses_post/', $l ) ) { $naked[] = $n + 1; }
}
check( 'jedes echo traegt eine Escaping-Funktion',   $naked, [] );
check( 'Hidden 0 direkt vor der Checkbox (eine Schleife fuer alle zehn Regeln)', preg_match( '/type="hidden" name="<\?php echo esc_attr\( \$name \); \?>\[enabled\]" value="0">\s*<label>\s*<input type="checkbox" name="<\?php echo esc_attr\( \$name \); \?>\[enabled\]" value="1"/', $view ), 1 );
check( 'Empfaengerfeld per esc_textarea',            str_contains( $view, 'esc_textarea( implode( "\n", $settings[\'recipients\'] ) )' ), true );
check( 'View beginnt mit ABSPATH-Wache',             str_contains( substr( $view, 0, 300 ), "if ( ! defined( 'ABSPATH' ) ) exit;" ), true );
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `php tests/test-notify-structure.php`
Expected: Warnung `file_get_contents(…/notifications.php): Failed to open stream` und FAILs im Admin-Block.

- [ ] **Step 3: Admin-Klasse ändern**

Konstruktor, nach `add_action( 'admin_post_naws_reset_appearance', … );`:

```php
        add_action( 'admin_post_naws_save_notifications', [ $this, 'handle_save_notifications' ] );
        add_action( 'admin_post_naws_test_notification', [ $this, 'handle_test_notification' ] );
```

`add_menu()`, direkt nach der `naws-modules`-Zeile:

```php
        add_submenu_page( 'naws-dashboard', __( 'Notifications', 'xtx-integration-for-netatmo' ), __( 'Notifications', 'xtx-integration-for-netatmo' ), 'manage_options', 'naws-notifications', [ $this, 'page_notifications' ] );
```

Nach `page_modules()`:

```php
    public function page_notifications() {
        $settings    = NAWS_Notifications::get_settings();
        $catalog     = NAWS_Notify_Rules::catalog();
        $units       = NAWS_Notifications::units();
        $rows        = NAWS_Notifications::status_rows();
        $log         = NAWS_Notifications::get_log();
        $admin_email = (string) get_option( 'admin_email', '' );
        include NAWS_PLUGIN_DIR . 'admin/views/notifications.php';
    }

    public function handle_save_notifications() {
        check_admin_referer( 'naws_save_notifications' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        // The recipients are a textarea: sanitize_text_field() would strip
        // the line breaks and merge the addresses into one. The rules are
        // scalar fields and go through map_deep(). Both sanitized right
        // here, where the scanner looks for it.
        $recipients = isset( $_POST['naws_notifications']['recipients'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['naws_notifications']['recipients'] ) )
            : '';
        $rules = isset( $_POST['naws_notifications']['rules'] ) && is_array( $_POST['naws_notifications']['rules'] )
            ? map_deep( wp_unslash( $_POST['naws_notifications']['rules'] ), 'sanitize_text_field' )
            : [];

        $clean   = NAWS_Notifications::from_form( $recipients, $rules, NAWS_Notifications::units() );
        $dropped = count( NAWS_Notifications::split_recipients( $recipients ) ) - count( $clean['recipients'] );
        update_option( NAWS_Notifications::OPTION_KEY, $clean );

        // add_query_arg(), not wp_nonce_url(): the latter escapes for HTML
        // and would put &amp; into a Location header.
        $url = add_query_arg( [
            'page'     => 'naws-notifications',
            'updated'  => 1,
            'dropped'  => $dropped > 0 ? (int) $dropped : false,
            '_wpnonce' => wp_create_nonce( 'naws_notifications_notice' ),
        ], admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }

    public function handle_test_notification() {
        check_admin_referer( 'naws_test_notification' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $sent = NAWS_Notifications::send_test();
        $url  = add_query_arg( [
            'page'     => 'naws-notifications',
            'test'     => $sent ? 1 : 0,
            '_wpnonce' => wp_create_nonce( 'naws_notifications_notice' ),
        ], admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }
```

- [ ] **Step 4: View anlegen — `admin/views/notifications.php`**

```php
<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array  $settings    NAWS_Notifications::get_settings() */
/** @var array  $catalog     NAWS_Notify_Rules::catalog() */
/** @var array  $units       NAWS_Notifications::units() */
/** @var array  $rows        NAWS_Notifications::status_rows() */
/** @var array  $log         NAWS_Notifications::get_log() */
/** @var string $admin_email get_option( 'admin_email' ) */

$groups = [
    'station' => __( 'Station', 'xtx-integration-for-netatmo' ),
    'weather' => __( 'Weather', 'xtx-integration-for-netatmo' ),
];
?>
<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title"><span class="naws-title-icon">✉️</span> <?php esc_html_e( 'Notifications', 'xtx-integration-for-netatmo' ); ?></h1>
    <p class="description"><?php esc_html_e( 'After every fetch the plugin checks the rules switched on below and sends one e-mail per state change; all changes of one fetch go into one mail.', 'xtx-integration-for-netatmo' ); ?></p>

    <?php if ( isset( $_GET['updated'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'xtx-integration-for-netatmo' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['dropped'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) ) :
        $dropped = absint( wp_unslash( $_GET['dropped'] ) ); ?>
        <div class="notice notice-warning is-dismissible"><p><?php
            /* translators: %d: number of addresses that were not valid e-mail addresses */
            echo esc_html( sprintf( _n( '%d invalid address was dropped.', '%d invalid addresses were dropped.', $dropped, 'xtx-integration-for-netatmo' ), $dropped ) ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['test'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) ) : ?>
        <?php if ( absint( wp_unslash( $_GET['test'] ) ) === 1 ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test mail sent.', 'xtx-integration-for-netatmo' ); ?></p></div>
        <?php else : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The test mail could not be sent: wp_mail() returned false. The server sends no mail — an SMTP plugin or your host can fix that.', 'xtx-integration-for-netatmo' ); ?></p></div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'naws_save_notifications' ); ?>
        <input type="hidden" name="action" value="naws_save_notifications">

        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2><?php esc_html_e( 'Recipients', 'xtx-integration-for-netatmo' ); ?></h2></div>
            <div style="padding:1rem 1.25rem;">
                <textarea name="naws_notifications[recipients]" rows="3" cols="60" class="large-text code" placeholder="<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_textarea( implode( "\n", $settings['recipients'] ) ); ?></textarea>
                <p class="description"><?php esc_html_e( "One address per line. Leave empty to use the site's admin address.", 'xtx-integration-for-netatmo' ); ?></p>
            </div>
        </div>

        <?php foreach ( $groups as $group => $group_label ) : ?>
        <div class="naws-admin-panel" style="margin-top:1rem;">
            <div class="naws-panel-header"><h2><?php echo esc_html( $group_label ); ?></h2></div>
            <table class="wp-list-table widefat striped naws-list-table">
                <thead>
                    <tr>
                        <th style="width:38%;"><?php esc_html_e( 'Rule', 'xtx-integration-for-netatmo' ); ?></th>
                        <th style="width:22%;"><?php esc_html_e( 'Threshold', 'xtx-integration-for-netatmo' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $catalog as $id => $def ) :
                    if ( $def['group'] !== $group ) continue;
                    $cfg  = $settings['rules'][ $id ];
                    $name = 'naws_notifications[rules][' . $id . ']'; ?>
                    <tr>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="0">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
                                <strong><?php echo esc_html( naws_label( 'ntf_rule_' . $id ) ); ?></strong>
                            </label>
                        </td>
                        <td>
                            <?php if ( $def['param'] === 'threshold' ) :
                                $shown = in_array( $def['kind'], [ 'temp', 'wind', 'rain' ], true )
                                    ? NAWS_Notify_Rules::to_display( $def['kind'], (float) $cfg['threshold'], $units )
                                    : (int) $cfg['threshold'];
                                $step  = $def['kind'] === 'percent' ? '1' : '0.1'; ?>
                                <input type="number" step="<?php echo esc_attr( $step ); ?>" name="<?php echo esc_attr( $name ); ?>[threshold]" value="<?php echo esc_attr( $shown ); ?>" class="small-text">
                                <?php echo esc_html( NAWS_Notify_Rules::unit_label( $def['kind'], $units ) ); ?>
                            <?php elseif ( $def['param'] === 'level' ) : ?>
                                <select name="<?php echo esc_attr( $name ); ?>[level]">
                                    <?php foreach ( array_keys( $def['levels'] ) as $level ) : ?>
                                        <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $cfg['level'], $level ); ?>><?php echo esc_html( naws_label( 'ntf_level_' . $level ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ( $def['param'] === 'minutes' ) : ?>
                                <input type="number" step="1" min="10" max="1440" name="<?php echo esc_attr( $name ); ?>[minutes]" value="<?php echo esc_attr( (int) $cfg['minutes'] ); ?>" class="small-text">
                                <?php echo esc_html( NAWS_Notify_Rules::unit_label( 'minutes', $units ) ); ?>
                            <?php elseif ( $id === 'sync_failed' ) : ?>
                                <span class="description"><?php esc_html_e( 'after 3 errors in a row', 'xtx-integration-for-netatmo' ); ?></span>
                            <?php else : ?>
                                <span class="description">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="description"><?php echo esc_html( naws_label( 'ntf_desc_' . $id ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <?php submit_button( __( 'Save Notifications', 'xtx-integration-for-netatmo' ) ); ?>
    </form>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:-0.5rem 0 1.5rem;">
        <?php wp_nonce_field( 'naws_test_notification' ); ?>
        <input type="hidden" name="action" value="naws_test_notification">
        <button type="submit" class="button"><?php esc_html_e( 'Send test mail', 'xtx-integration-for-netatmo' ); ?></button>
        <span class="description" style="margin-left:0.5rem;"><?php esc_html_e( 'The test mail goes to the saved recipients.', 'xtx-integration-for-netatmo' ); ?></span>
    </form>

    <div class="naws-admin-panel">
        <div class="naws-panel-header"><h2><?php esc_html_e( 'Current state', 'xtx-integration-for-netatmo' ); ?></h2></div>
        <?php if ( empty( $rows ) ) : ?>
            <p style="padding:1rem;"><?php esc_html_e( 'No active modules.', 'xtx-integration-for-netatmo' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Rule', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php echo esc_html( naws_label( 'ntf_module' ) ); ?></th>
                    <th><?php esc_html_e( 'State', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'xtx-integration-for-netatmo' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                switch ( $row['status'] ) {
                    case 'active':    $state = sprintf( naws_label( 'ntf_status_active' ), NAWS_Notifications::stamp( (int) $row['since'] ) ); $badge = 'naws-badge-error'; break;
                    case 'pending':   $state = sprintf( naws_label( 'ntf_status_pending' ), NAWS_Notifications::stamp( (int) $row['since'] ) ); $badge = 'naws-badge-warning'; break;
                    case 'suspended': $state = naws_label( 'ntf_status_suspended' ) . ' (' . naws_label( 'ntf_reason_' . $row['reason'] ) . ')'; $badge = ''; break;
                    default:          $state = naws_label( 'ntf_status_ok' ); $badge = 'naws-badge-success';
                } ?>
                <tr>
                    <td><?php echo esc_html( naws_label( 'ntf_rule_' . $row['rule'] ) ); ?></td>
                    <td><?php echo $row['module_name'] !== '' ? esc_html( $row['module_name'] . ' (' . NAWS_Helpers::module_type_label( $row['module_type'] ) . ')' ) : '—'; ?></td>
                    <td><span class="naws-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $state ); ?></span></td>
                    <td><?php echo esc_html( NAWS_Notifications::format_measure( $row['rule'], $row['value'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="naws-admin-panel" style="margin-top:1rem;">
        <div class="naws-panel-header"><h2><?php esc_html_e( 'Recent notifications', 'xtx-integration-for-netatmo' ); ?></h2></div>
        <?php if ( empty( $log ) ) : ?>
            <p style="padding:1rem;"><?php esc_html_e( 'No notifications sent yet.', 'xtx-integration-for-netatmo' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th><?php echo esc_html( _x( 'Time', 'time', 'xtx-integration-for-netatmo' ) ); ?></th>
                    <th><?php esc_html_e( 'Status', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'To', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'Subject', 'xtx-integration-for-netatmo' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $log as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( NAWS_Notifications::stamp( (int) ( $entry['time'] ?? 0 ) ) ); ?></td>
                    <td><span class="naws-badge <?php echo esc_attr( ! empty( $entry['sent'] ) ? 'naws-badge-success' : 'naws-badge-error' ); ?>"><?php echo ! empty( $entry['sent'] ) ? esc_html__( 'sent', 'xtx-integration-for-netatmo' ) : esc_html__( 'failed', 'xtx-integration-for-netatmo' ); ?></span></td>
                    <td><?php echo esc_html( implode( ', ', (array) ( $entry['to'] ?? [] ) ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $entry['subject'] ?? '' ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
```

Gibt es die Klasse `naws-badge-warning` im Admin-Stylesheet nicht, ist das in Ordnung: das Badge fällt auf die Grundform zurück. Kein neues CSS.

- [ ] **Step 5: Test grün, phpcs sauber**

Run: `php tests/test-notify-structure.php && php -l includes/class-naws-admin.php && php -l admin/views/notifications.php && vendor/bin/phpcs includes/class-naws-admin.php admin/views/notifications.php`
Expected: `… 0 fehlgeschlagen`; kein phpcs-Befund. Meldet phpcs `NonceVerification.Recommended` an den `$_GET`-Zeilen trotz des Checks in derselben Bedingung, den Nonce-Check **nicht** in eine Variable ziehen; stattdessen die Bedingung so lassen und die Meldung mit `// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in this very condition` begründen.

- [ ] **Step 6: Auf dev ansehen (Controller, nicht Subagent)** — dieser Schritt gehört zu Task 10; hier nur `php -l` und phpcs.

- [ ] **Step 7: Commit**

```bash
git add includes/class-naws-admin.php admin/views/notifications.php tests/test-notify-structure.php
git commit -m "Admin: the Notifications page — recipients, rules with thresholds, test mail, current state, log"
```

---

### Task 8: Übersetzungen — Kataloge de/nb nachziehen

**Files:**
- Modify: `languages/xtx-integration-for-netatmo.pot`, `docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po`, `…-nb_NO.po`, `languages/xtx-integration-for-netatmo-de_DE.mo`, `…-nb_NO.mo` (alle durch Werkzeuge erzeugt)
- Create (Scratchpad, nicht committen): `fill-de.php`, `fill-nb.php`
- Test: `tests/test-mo-files.php`, `tests/test-frontend-i18n.php` (bestehend)

**Interfaces:**
- Consumes: alle `__()`/`_x()`/`_n()`-Aufrufe aus Task 5 und 7. Die Kette aus `docs/i18n/README.md`: `makepot.php` → `merge_po.php <lang>` → `fill_po.php <lang> <liste>` → `make_mo.php <po> <mo>`.

- [ ] **Step 1: .pot neu bauen und die beiden .po nachtragen**

Run:
```bash
php docs/i18n/catalog/makepot.php
php docs/i18n/catalog/merge_po.php de_DE
php docs/i18n/catalog/merge_po.php nb_NO
```
Expected: `merge_po.php` nennt unter „offen" die neuen Strings (etwa 80 je Sprache: die `ntf_*`-Labels, die View-Texte, zwei Plurale) und unter „weg" nichts.

- [ ] **Step 2: Deutsche Liste anlegen — `<scratchpad>/fill-de.php`**

```php
<?php
return [
    'Battery low'                                    => 'Batterie niedrig',
    'Weak radio link to the base station'            => 'Schwache Funkverbindung zur Basisstation',
    'Poor Wi-Fi at the base station'                 => 'Schlechtes WLAN an der Basisstation',
    'Base station not reporting'                     => 'Basisstation meldet sich nicht',
    'Module not reporting'                           => 'Modul meldet sich nicht',
    'Fetch failing'                                  => 'Abruf scheitert',
    'Credentials expired'                            => 'Zugangsdaten verfallen',
    "notification rule\x04Frost"                     => 'Frost',
    "notification rule\x04Gust"                      => 'Böe',
    'Rain in 24 hours'                               => 'Regen in 24 Stunden',
    'The battery of a module is below the threshold. Applies to every active module.' => 'Die Batterie eines Moduls liegt unter der Schwelle. Gilt für jedes aktive Modul.',
    'The radio signal from a module to the base station is at or below the chosen level for 30 minutes.' => 'Das Funksignal eines Moduls zur Basisstation liegt 30 Minuten lang auf oder unter der gewählten Stufe.',
    'The Wi-Fi of the base station is at or below the chosen level for 30 minutes.' => 'Das WLAN der Basisstation liegt 30 Minuten lang auf oder unter der gewählten Stufe.',
    'The base station is unreachable or has not reported to Netatmo for longer than the set minutes.' => 'Die Basisstation ist nicht erreichbar oder hat sich länger als die eingestellten Minuten nicht bei Netatmo gemeldet.',
    'A module is unreachable or has not reported to the base station for longer than the set minutes.' => 'Ein Modul ist nicht erreichbar oder hat sich länger als die eingestellten Minuten nicht bei der Basisstation gemeldet.',
    'Three fetches in a row have failed.'            => 'Drei Abrufe in Folge sind gescheitert.',
    'The Netatmo connection needs a new login under Settings.' => 'Die Netatmo-Verbindung braucht unter Einstellungen eine neue Anmeldung.',
    'The outdoor temperature is at or below the threshold.' => 'Die Außentemperatur liegt auf oder unter der Schwelle.',
    'A gust at or above the threshold.'              => 'Eine Böe auf oder über der Schwelle.',
    'The rain of the last 24 hours is at or above the threshold. No all-clear: one mail per rain event.' => 'Der Regen der letzten 24 Stunden liegt auf oder über der Schwelle. Keine Entwarnung: eine Mail je Regenereignis.',
    "notification measure\x04Battery"                => 'Batterie',
    'Radio'                                          => 'Funk',
    'Wi-Fi'                                          => 'WLAN',
    'Last report'                                    => 'Letzte Meldung',
    'Last message'                                   => 'Letzte Nachricht',
    'Errors in a row'                                => 'Fehler in Folge',
    "notification measure\x04Temperature"            => 'Temperatur',
    "notification measure\x04Gust"                   => 'Böe',
    'Rain (24 h)'                                    => 'Regen (24 h)',
    "radio level\x04weak"                            => 'schwach',
    "radio level\x04medium or worse"                 => 'mittel oder schlechter',
    "wifi level\x04poor"                             => 'schlecht',
    "wifi level\x04average or worse"                 => 'mittel oder schlechter',
    "notification status\x04ok"                      => 'ok',
    'Warning since %s'                               => 'Warnung seit %s',
    'Hold running since %s'                          => 'Beharrung läuft seit %s',
    'suspended'                                      => 'ausgesetzt',
    'rule off'                                       => 'Regel aus',
    'no value yet'                                   => 'noch kein Wert',
    'reading outdated'                               => 'Messwert veraltet',
    'base station silent'                            => 'Basisstation still',
    'no fetch'                                       => 'kein Abruf',
    "notification mail\x04Warning"                   => 'Warnung',
    'All clear'                                      => 'Entwarnung',
    "notification mail\x04Module"                    => 'Modul',
    'threshold %s'                                   => 'Schwelle %s',
    'Since: %s'                                      => 'Seit: %s',
    'From %1$s to %2$s'                              => 'Von %1$s bis %2$s',
    'Adjust rules'                                   => 'Regeln anpassen',
    'Error: %s'                                      => 'Fehler: %s',
    'Please connect to Netatmo again under XTX Netatmo → Settings.' => 'Bitte verbinde dich unter XTX Netatmo → Einstellungen erneut mit Netatmo.',
    'Test mail'                                      => 'Testmail',
    'This mail was sent by hand from %s to check the delivery.' => 'Diese Mail wurde von Hand von %s verschickt, um die Zustellung zu prüfen.',
    'Rules switched on:'                             => 'Eingeschaltete Regeln:',
    '(none)'                                         => '(keine)',
    '%d warning'                                     => [ '%d Warnung', '%d Warnungen' ],
    '%d all-clear'                                   => [ '%d Entwarnung', '%d Entwarnungen' ],
    'Notifications'                                  => 'Benachrichtigungen',
    'After every fetch the plugin checks the rules switched on below and sends one e-mail per state change; all changes of one fetch go into one mail.' => 'Nach jedem Abruf prüft das Plugin die unten eingeschalteten Regeln und schickt je Zustandswechsel eine E-Mail; alle Wechsel eines Abrufs kommen in eine Mail.',
    'Recipients'                                     => 'Empfänger',
    "One address per line. Leave empty to use the site's admin address." => 'Eine Adresse je Zeile. Leer lassen, um die Admin-Adresse der Site zu verwenden.',
    'Station'                                        => 'Station',
    'Weather'                                        => 'Wetter',
    'Rule'                                           => 'Regel',
    'Threshold'                                      => 'Schwelle',
    'Description'                                    => 'Beschreibung',
    'Save Notifications'                             => 'Benachrichtigungen speichern',
    'Send test mail'                                 => 'Testmail senden',
    'The test mail goes to the saved recipients.'    => 'Die Testmail geht an die gespeicherten Empfänger.',
    'Current state'                                  => 'Aktueller Zustand',
    'State'                                          => 'Zustand',
    'Value'                                          => 'Wert',
    'No active modules.'                             => 'Keine aktiven Module.',
    'Recent notifications'                           => 'Letzte Benachrichtigungen',
    'No notifications sent yet.'                     => 'Noch keine Benachrichtigung verschickt.',
    'sent'                                           => 'gesendet',
    'failed'                                         => 'fehlgeschlagen',
    'To'                                             => 'An',
    'Subject'                                        => 'Betreff',
    '%d invalid address was dropped.'                => [ '%d ungültige Adresse wurde verworfen.', '%d ungültige Adressen wurden verworfen.' ],
    'Test mail sent.'                                => 'Testmail gesendet.',
    'The test mail could not be sent: wp_mail() returned false. The server sends no mail — an SMTP plugin or your host can fix that.' => 'Die Testmail konnte nicht gesendet werden: wp_mail() hat false zurückgegeben. Der Server verschickt keine Mail — ein SMTP-Plugin oder dein Hoster hilft.',
    'after 3 errors in a row'                        => 'nach 3 Fehlern in Folge',
];
```

- [ ] **Step 3: Norwegische Liste anlegen — `<scratchpad>/fill-nb.php`**

```php
<?php
return [
    'Battery low'                                    => 'Lavt batteri',
    'Weak radio link to the base station'            => 'Svakt radiosignal til basestasjonen',
    'Poor Wi-Fi at the base station'                 => 'Dårlig Wi-Fi ved basestasjonen',
    'Base station not reporting'                     => 'Basestasjonen rapporterer ikke',
    'Module not reporting'                           => 'Modulen rapporterer ikke',
    'Fetch failing'                                  => 'Henting feiler',
    'Credentials expired'                            => 'Påloggingen er utløpt',
    "notification rule\x04Frost"                     => 'Frost',
    "notification rule\x04Gust"                      => 'Vindkast',
    'Rain in 24 hours'                               => 'Regn på 24 timer',
    'The battery of a module is below the threshold. Applies to every active module.' => 'Batteriet i en modul er under terskelen. Gjelder alle aktive moduler.',
    'The radio signal from a module to the base station is at or below the chosen level for 30 minutes.' => 'Radiosignalet fra en modul til basestasjonen er på eller under valgt nivå i 30 minutter.',
    'The Wi-Fi of the base station is at or below the chosen level for 30 minutes.' => 'Wi-Fi-signalet til basestasjonen er på eller under valgt nivå i 30 minutter.',
    'The base station is unreachable or has not reported to Netatmo for longer than the set minutes.' => 'Basestasjonen er utilgjengelig eller har ikke rapportert til Netatmo på lengre tid enn de angitte minuttene.',
    'A module is unreachable or has not reported to the base station for longer than the set minutes.' => 'En modul er utilgjengelig eller har ikke rapportert til basestasjonen på lengre tid enn de angitte minuttene.',
    'Three fetches in a row have failed.'            => 'Tre hentinger på rad har feilet.',
    'The Netatmo connection needs a new login under Settings.' => 'Netatmo-tilkoblingen trenger ny pålogging under Innstillinger.',
    'The outdoor temperature is at or below the threshold.' => 'Utetemperaturen er på eller under terskelen.',
    'A gust at or above the threshold.'              => 'Et vindkast på eller over terskelen.',
    'The rain of the last 24 hours is at or above the threshold. No all-clear: one mail per rain event.' => 'Regnet de siste 24 timene er på eller over terskelen. Ingen friskmelding: én e-post per regnhendelse.',
    "notification measure\x04Battery"                => 'Batteri',
    'Radio'                                          => 'Radio',
    'Wi-Fi'                                          => 'Wi-Fi',
    'Last report'                                    => 'Siste rapport',
    'Last message'                                   => 'Siste melding',
    'Errors in a row'                                => 'Feil på rad',
    "notification measure\x04Temperature"            => 'Temperatur',
    "notification measure\x04Gust"                   => 'Vindkast',
    'Rain (24 h)'                                    => 'Regn (24 t)',
    "radio level\x04weak"                            => 'svakt',
    "radio level\x04medium or worse"                 => 'middels eller dårligere',
    "wifi level\x04poor"                             => 'dårlig',
    "wifi level\x04average or worse"                 => 'middels eller dårligere',
    "notification status\x04ok"                      => 'ok',
    'Warning since %s'                               => 'Varsel siden %s',
    'Hold running since %s'                          => 'Ventetid løper siden %s',
    'suspended'                                      => 'satt på vent',
    'rule off'                                       => 'regel av',
    'no value yet'                                   => 'ingen verdi ennå',
    'reading outdated'                               => 'måling utdatert',
    'base station silent'                            => 'basestasjonen er stille',
    'no fetch'                                       => 'ingen henting',
    "notification mail\x04Warning"                   => 'Varsel',
    'All clear'                                      => 'Friskmelding',
    "notification mail\x04Module"                    => 'Modul',
    'threshold %s'                                   => 'terskel %s',
    'Since: %s'                                      => 'Siden: %s',
    'From %1$s to %2$s'                              => 'Fra %1$s til %2$s',
    'Adjust rules'                                   => 'Juster regler',
    'Error: %s'                                      => 'Feil: %s',
    'Please connect to Netatmo again under XTX Netatmo → Settings.' => 'Koble til Netatmo på nytt under XTX Netatmo → Innstillinger.',
    'Test mail'                                      => 'Test-e-post',
    'This mail was sent by hand from %s to check the delivery.' => 'Denne e-posten ble sendt manuelt fra %s for å sjekke leveringen.',
    'Rules switched on:'                             => 'Regler som er slått på:',
    '(none)'                                         => '(ingen)',
    '%d warning'                                     => [ '%d varsel', '%d varsler' ],
    '%d all-clear'                                   => [ '%d friskmelding', '%d friskmeldinger' ],
    'Notifications'                                  => 'Varsler',
    'After every fetch the plugin checks the rules switched on below and sends one e-mail per state change; all changes of one fetch go into one mail.' => 'Etter hver henting sjekker utvidelsen reglene som er slått på nedenfor og sender én e-post per tilstandsendring; alle endringer fra én henting samles i én e-post.',
    'Recipients'                                     => 'Mottakere',
    "One address per line. Leave empty to use the site's admin address." => 'Én adresse per linje. La stå tom for å bruke nettstedets administratoradresse.',
    'Station'                                        => 'Stasjon',
    'Weather'                                        => 'Vær',
    'Rule'                                           => 'Regel',
    'Threshold'                                      => 'Terskel',
    'Description'                                    => 'Beskrivelse',
    'Save Notifications'                             => 'Lagre varsler',
    'Send test mail'                                 => 'Send test-e-post',
    'The test mail goes to the saved recipients.'    => 'Test-e-posten går til de lagrede mottakerne.',
    'Current state'                                  => 'Nåværende tilstand',
    'State'                                          => 'Tilstand',
    'Value'                                          => 'Verdi',
    'No active modules.'                             => 'Ingen aktive moduler.',
    'Recent notifications'                           => 'Siste varsler',
    'No notifications sent yet.'                     => 'Ingen varsler sendt ennå.',
    'sent'                                           => 'sendt',
    'failed'                                         => 'mislyktes',
    'To'                                             => 'Til',
    'Subject'                                        => 'Emne',
    '%d invalid address was dropped.'                => [ '%d ugyldig adresse ble forkastet.', '%d ugyldige adresser ble forkastet.' ],
    'Test mail sent.'                                => 'Test-e-post sendt.',
    'The test mail could not be sent: wp_mail() returned false. The server sends no mail — an SMTP plugin or your host can fix that.' => 'Test-e-posten kunne ikke sendes: wp_mail() returnerte false. Serveren sender ingen e-post – en SMTP-utvidelse eller leverandøren din kan ordne det.',
    'after 3 errors in a row'                        => 'etter 3 feil på rad',
];
```

Einige Schlüssel gibt es schon übersetzt (z. B. `Station`, `Weather`, `Rule`, `Value`, `State`, `Description`, `Subject`, `To`, `sent`, `failed`, `Settings saved.`): `fill_po.php` lässt gefüllte `msgstr` in Ruhe und meldet nur Schlüssel, die die `.po` **nicht kennt** — jede solche Meldung ist ein Tippfehler gegenüber dem Quelltext und wird in der Liste korrigiert, nicht im Code.

- [ ] **Step 4: Füllen, kompilieren, prüfen**

Run:
```bash
php docs/i18n/catalog/fill_po.php de_DE "<scratchpad>/fill-de.php"
php docs/i18n/catalog/fill_po.php nb_NO "<scratchpad>/fill-nb.php"
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po languages/xtx-integration-for-netatmo-de_DE.mo
php docs/i18n/catalog/make_mo.php docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po languages/xtx-integration-for-netatmo-nb_NO.mo
php tests/test-mo-files.php && php tests/test-frontend-i18n.php
grep -c 'msgstr ""' docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po
```
Expected: `fill_po.php` meldet keine unbekannten Schlüssel; beide Tests `0 fehlgeschlagen`; der `grep` zählt genau 1 (den Kopf) — jede weitere leere `msgstr` ist ein vergessener String und wird in die Listen nachgetragen.

- [ ] **Step 5: Commit**

```bash
git add languages/ docs/i18n/catalog/xtx-integration-for-netatmo-de_DE.po docs/i18n/catalog/xtx-integration-for-netatmo-nb_NO.po
git commit -m "i18n: catalogues for the notifications — German and Norwegian complete"
```

---

### Task 9: Doku — CHANGELOG, readme, Website-JSONs

**Files:**
- Modify: `CHANGELOG.md:5-13`, `readme.txt:17-34` (Key Features) und `:72-105` (FAQ), `docs/site/website.de.json:2-9`, `docs/site/website.en.json:2-9`, `README.md` (falls dort eine Feature-Liste steht)

- [ ] **Step 1: CHANGELOG.md** — `## [1.9.14]` wird `## [2.0.0]`; direkt darunter, vor `### Fixed`, ein neuer Abschnitt:

```markdown
### Added
- **E-mail notifications.** After every fetch the plugin checks up to ten rules and mails every change of state — once when it begins, once when it is over, all changes of one fetch in one mail. Seven rules watch the station: battery of a module below a percentage, weak radio link of a module, poor Wi-Fi of the base station, base station not reporting to Netatmo, module not reporting to the base station, three failed fetches in a row, expired credentials. Three watch the weather: frost, a gust above a threshold, rain of the last 24 hours above a threshold. Every rule has a switch, most a threshold; radio, Wi-Fi and the two silence rules hold for a while before they speak, and frost and gust wait an hour below the threshold before the all-clear. All rules ship switched off. The new page XTX Netatmo → Notifications holds the recipients, the rules, a test-mail button, a table of what every rule sees right now — including why one is suspended — and the last fifty mails.
- The five status fields Netatmo sends with every module are stored now: battery percentage, Wi-Fi of the base station, whether a device is reachable, when the base station last reported to Netatmo and when a module last spoke to the base station (schema 1.5). The Modules page shows Netatmo's own battery percentage instead of an estimate from the voltage, which put a module at 44 % that Netatmo reports at 23 %.
- Two actions for other code: `naws_sync_failed( string $message, int $consecutive_errors )` fires wherever a fetch fails; `naws_data_synced( int $readings )` existed before and is documented now.
```

- [ ] **Step 2: readme.txt, Key Features** — nach der Zeile `* **Export / Import** …` einfügen:

```
* **E-Mail Notifications** – battery, radio and Wi-Fi, station offline, failed syncs, frost, gusts, rain; per-rule thresholds, one mail per state change with an all-clear
```

- [ ] **Step 3: readme.txt, FAQ** — nach dem Eintrag `= Which forecast providers are supported? =` (vor `== Screenshots ==`):

```
= I don't receive notification e-mails =

Open XTX Netatmo → Notifications and press "Send test mail". If the page reports that the mail could not be sent, your server sends no mail at all: an SMTP plugin or your hosting provider fixes that, not the plugin. If the test mail arrives but no notifications do, check that the rule is switched on and look at the "Current state" table on the same page — it says what every rule sees right now and why one is suspended. Notifications go out only when a state changes; a battery that has been low since before you switched the rule on is reported at the next fetch, not again afterwards.
```

- [ ] **Step 4: Website-JSONs** — in beiden Dateien `"ab": null` des Vorhabens `email-benachrichtigungen` durch `"ab": "2.0.0"` ersetzen und `"aktualisiert"` auf das Datum des Commits setzen. `satz` bleibt; das Wort „wie oft" darin ist mit „einmal je Wechsel" abgedeckt.

- [ ] **Step 5: README.md** — steht dort eine Feature-Liste mit einem Windrosen-Punkt, darunter derselbe Satz wie in Step 2 (Markdown-Aufzählung). Gibt es keine Liste, entfällt der Schritt.

- [ ] **Step 6: Prüfen und committen**

Run: `php -r 'json_decode(file_get_contents("docs/site/website.de.json"), false, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("docs/site/website.en.json"), false, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
Expected: `json ok`.

```bash
git add CHANGELOG.md readme.txt README.md docs/site/website.de.json docs/site/website.en.json
git commit -m "Docs: notifications in the changelog, readme and the site's plan list"
```

---

### Task 10: Abnahme auf dev und Merge (Controller, nicht Subagent)

**Files:** keine Codeänderung; Deploy-Weg per ZIP wie am 10.09. (siehe Projektgedächtnis), Sicherung `wp-content/naws-backup-vor-notifications-<ts>`.

- [ ] **Step 1: Suite und phpcs auf dem ganzen Zweig**

Run: `for t in tests/test-*.php; do php "$t" >/dev/null 2>&1 || echo "FAIL $t"; done; vendor/bin/phpcs --report=summary`
Expected: keine `FAIL`-Zeile; phpcs ohne Fehler.

- [ ] **Step 2: Auf dev einspielen** — Dateien: `includes/class-naws-notify-rules.php`, `includes/class-naws-notifications.php`, `includes/class-naws-database.php`, `includes/class-naws-cron.php`, `includes/class-naws-admin.php`, `includes/class-naws-labels.php`, `admin/views/notifications.php`, `admin/views/modules.php`, `xtx-integration-for-netatmo.php`, `languages/*.mo`. Danach `opcache_reset()`, Startseite 200, `get_option('naws_db_version') === '1.5'`, Spalten in `naws_modules` per `SHOW COLUMNS` vorhanden, ein Cron-Lauf (`NAWS_Cron::instance()->run_fetch()`) füllt sie.

- [ ] **Step 3: Seite und Regeln** — `admin.php?page=naws-notifications` öffnen (Plugin Check auf dev ohne neue Meldung). Empfänger eintragen, Batterie-Regel mit Schwelle 30 einschalten, speichern → Tabelle „Aktueller Zustand" zeigt „Gast" mit 23 % als `ok` (noch kein Lauf) → `run_fetch()` → Mail „Batterie niedrig – Gast (23 %)" kommt an, Tabelle zeigt „Warnung seit …", Protokoll eine Zeile. Zweiter Lauf → keine zweite Mail. Testmail-Knopf → Mail kommt an, Protokoll zwei Zeilen.

- [ ] **Step 4: Basis-Ausfall** — `UPDATE naws_modules SET last_status_store = last_status_store - 7200 WHERE module_type = 'NAMain'`, Regel „Basis meldet sich nicht" einschalten, `run_fetch()` — Achtung: der Sync schreibt `last_status_store` sofort zurück; daher stattdessen die Regelminuten auf 10 setzen und den Cron 15 Minuten aussetzen, oder den Lauf mit `on_synced()` direkt nach dem UPDATE auslösen (`NAWS_Notifications::on_synced()` per execute-php). Erwartet: Mail „Basisstation meldet sich nicht", Batteriezeile `ausgesetzt (Basisstation still)`. Nächster echter Lauf → Entwarnung.

- [ ] **Step 5: Frank nimmt die Seite ab** (Knöpfe hovern: WordPress-Standardknöpfe, kein Plugin-CSS betroffen).

- [ ] **Step 6: Merge**

```bash
git checkout main && git merge --no-ff notifications -m "Merge notifications: e-mail notifications for outages and weather events (2.0.0)" && git push origin main
```

Der Schnitt auf 2.0.0 (Header, `Stable tag`, readme-Changelog-Fenster 1.9.10–2.0.0, Upgrade Notice, Release-Notizen, ZIP, SVN, GitHub-Release, GlotPress) folgt dem Ritual aus dem Projektgedächtnis und ist nicht Teil dieses Plans.
