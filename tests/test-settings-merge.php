<?php
/**
 * Merge-semantics tests for NAWS_Admin::sanitize_settings().
 *
 * The settings screen is split across several forms and each posts only the
 * fields it owns. Before 1.7.0 the callback rebuilt the options array from
 * scratch, so saving the credentials form reset the units, the cron
 * interval and every forecast setting to their defaults.
 *
 * These cases pin the merge behaviour down, including the hidden-zero
 * pattern that keeps checkboxes switchable.
 *
 *   php tests/test-settings-merge.php
 *
 * @package NAWS
 * @since   1.7.0
 */

define( 'ABSPATH', __DIR__ );

// ── Stored settings the user already has ─────────────────────────────────
$GLOBALS['naws_stored'] = [
    'client_id'        => 'ENC:abc',
    'client_secret'    => 'ENC:xyz',
    'cron_interval'    => 15,
    'temperature_unit' => 'F',
    'wind_unit'        => 'ms',
    'pressure_unit'    => 'inHg',
    'rain_unit'        => 'in',
    'station_name'     => 'Gartenhaus',
    'night_mode'       => 1,
    'forecast_provider'  => 'yr_no',
    'forecast_days'      => 7,
    'forecast_location'  => 'manual',
    'forecast_city'      => 'Muenster',
    'forecast_country'   => 'DE',
    'forecast_auto_name' => '',
    'wx_rain_heavy' => 6.5,
    'wx_snow_tw'    => 0.5,
    'wx_storm_wind' => 90.0,
    'wx_fog_rh'     => 98.0,
    'wx_fog_spread' => 0.4,
    'wx_show_on_dashboard' => '1',
    'heating_limit' => 16.5,
    'room_temp'     => 21.0,
    'cooling_limit' => 19.0,
];

// ── Minimal WordPress surface ────────────────────────────────────────────
function get_option( $key, $default = false ) {
    return $key === 'naws_settings' ? $GLOBALS['naws_stored'] : $default;
}
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function do_action( ...$a ) {}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function esc_html( ...$a ) {}
require_once __DIR__ . '/i18n-stubs.php';

class NAWS_Crypto {
    public static function is_encrypted( $s ) { return str_starts_with( (string) $s, 'ENC:' ); }
    public static function encrypt( $s ) { return 'ENC:' . $s; }
    public static function migrate() {}
}
class NAWS_Forecast {
    public static function flush_cache() {}
}

// sanitize_settings() snaps cron_interval onto a real WP-Cron schedule.
require_once __DIR__ . '/../includes/class-naws-cron.php';
require_once __DIR__ . '/../includes/class-naws-admin.php';

// The constructor only registers hooks; bypass it.
$admin  = ( new ReflectionClass( 'NAWS_Admin' ) )->newInstanceWithoutConstructor();
$failed = 0;

/**
 * @param string $name       What the case demonstrates.
 * @param array  $post       The slice one form would submit.
 * @param array  $untouched  Keys that must survive unchanged.
 * @param array  $changed    Keys that must take the submitted value.
 */
function scenario( string $name, array $post, array $untouched, array $changed ): void {
    global $admin, $failed;

    $out      = $admin->sanitize_settings( $post );
    $problems = [];

    foreach ( $untouched as $k ) {
        $was = $GLOBALS['naws_stored'][ $k ] ?? null;
        $is  = $out[ $k ] ?? null;
        if ( $was != $is ) {
            $problems[] = sprintf( '%s verloren (%s -> %s)', $k, var_export( $was, true ), var_export( $is, true ) );
        }
    }
    foreach ( $changed as $k => $want ) {
        $is = $out[ $k ] ?? null;
        if ( $want != $is ) {
            $problems[] = sprintf( '%s nicht uebernommen (erwartet %s, ist %s)', $k, var_export( $want, true ), var_export( $is, true ) );
        }
    }

    if ( $problems ) {
        $failed++;
        echo "  FAIL  {$name}\n";
        foreach ( $problems as $p ) { echo "          - {$p}\n"; }
        return;
    }
    echo "  ok    {$name}\n";
}

echo "\nNAWS_Admin::sanitize_settings() – Merge-Semantik\n";
echo str_repeat( '-', 70 ) . "\n";

// Form 1 – credentials. Carries the wx_* mirror fields, nothing else.
scenario(
    'Zugangsdaten speichern laesst alles andere stehen',
    [
        'client_id' => 'newid', 'client_secret' => 'newsecret',
        'wx_show_on_dashboard' => '1', 'wx_rain_heavy' => 6.5, 'wx_snow_tw' => 0.5,
        'wx_fog_rh' => 98.0, 'wx_fog_spread' => 0.4, 'wx_storm_wind' => 90.0,
    ],
    [ 'cron_interval', 'temperature_unit', 'wind_unit', 'pressure_unit',
      'rain_unit', 'station_name', 'night_mode', 'forecast_provider', 'forecast_days',
      'forecast_city', 'wx_snow_tw' ],
    [ 'client_id' => 'ENC:newid' ]
);

// Form 2 – general. No forecast fields at all.
scenario(
    'Allgemein speichern laesst Forecast + wx stehen',
    [
        'temperature_unit' => 'C', 'wind_unit' => 'kmh',
        'pressure_unit' => 'mbar', 'rain_unit' => 'mm', 'cron_interval' => 10,
        'night_mode' => '0', 'data_retention' => 365,
    ],
    [ 'forecast_provider', 'forecast_days', 'forecast_city', 'wx_snow_tw',
      'wx_storm_wind', 'client_id' ],
    [ 'night_mode' => 0 ]
);

// The hidden-zero pattern: an unchecked box must actually switch off.
scenario(
    'Icon-Checkbox laesst sich ausschalten',
    [
        'wx_show_on_dashboard' => '0', 'wx_rain_heavy' => 6.5, 'wx_snow_tw' => 0.5,
        'wx_fog_rh' => 98.0, 'wx_fog_spread' => 0.4, 'wx_storm_wind' => 90.0,
        'forecast_provider' => 'yr_no', 'forecast_days' => 7, 'forecast_location' => 'manual',
        'forecast_city' => 'Muenster', 'forecast_country' => 'DE',
    ],
    [ 'station_name', 'client_id' ],
    [ 'wx_show_on_dashboard' => '0' ]
);

// Clamping still applies on top of the merge.
scenario(
    'Schwellen werden weiter geklemmt',
    [ 'wx_rain_heavy' => 999, 'wx_snow_tw' => -99, 'wx_storm_wind' => 5 ],
    [ 'station_name', 'forecast_city' ],
    [ 'wx_rain_heavy' => 50.0, 'wx_snow_tw' => -20.0, 'wx_storm_wind' => 20.0 ]
);

scenario(
    'Widget-Tage werden auf 3 oder 5 gezogen',
    [ 'wgt_days' => 4 ],
    [ 'station_name', 'forecast_city' ],
    [ 'wgt_days' => 5 ]
);
scenario(
    'Widget-Tage 2 wird zu 3',
    [ 'wgt_days' => 2 ],
    [ 'station_name' ],
    [ 'wgt_days' => 3 ]
);

// ── The 1.9.1 form shapes ────────────────────────────────────────────────
// Until 1.9.1 every form carried hidden mirror copies of the fields it did
// not own. That was the pre-1.7.0 workaround for the reset bug, and it
// outlived its cause: with mirrors, every save wrote every key back, which
// is precisely what merge semantics exist to avoid. The mirrors are gone, so
// each form now posts a genuinely narrow slice — these cases pin that down.

scenario(
    'Zugangsdaten-Formular postet nur noch die Zugangsdaten',
    [ 'client_id' => 'newid', 'client_secret' => 'newsecret' ],
    [ 'cron_interval', 'temperature_unit', 'wind_unit', 'pressure_unit',
      'rain_unit', 'station_name', 'night_mode', 'forecast_provider', 'forecast_days',
      'forecast_location', 'forecast_city', 'forecast_country',
      'wx_rain_heavy', 'wx_snow_tw', 'wx_fog_rh', 'wx_fog_spread', 'wx_storm_wind',
      'wx_show_on_dashboard' ],
    [ 'client_id' => 'ENC:newid', 'client_secret' => 'ENC:newsecret' ]
);

scenario(
    'Einstellungs-Formular fasst die Zugangsdaten nicht an',
    [
        'station_name' => 'Balkon',
        'cron_interval' => 10, 'night_mode' => '0',
        'temperature_unit' => 'C', 'wind_unit' => 'kmh',
        'pressure_unit' => 'mbar', 'rain_unit' => 'mm',
        'forecast_provider' => 'open_meteo', 'forecast_days' => 5,
        'forecast_location' => 'auto', 'forecast_city' => '', 'forecast_country' => '',
        'wx_show_on_dashboard' => '1', 'wx_rain_heavy' => 4.0, 'wx_snow_tw' => 1.0,
        'wx_fog_rh' => 97.0, 'wx_fog_spread' => 0.5, 'wx_storm_wind' => 75.0,
    ],
    [ 'client_id', 'client_secret' ],
    [ 'station_name' => 'Balkon', 'forecast_days' => 5 ]
);

// The point of dropping the mirrors: a key absent from the POST must keep
// its stored value rather than being rewritten from a stale hidden field.
scenario(
    'Nicht gepostete Schluessel bleiben unangetastet',
    [ 'station_name' => 'Nordseite' ],
    [ 'client_id', 'client_secret', 'cron_interval', 'night_mode',
      'temperature_unit', 'wind_unit', 'pressure_unit', 'rain_unit',
      'forecast_provider', 'forecast_days', 'forecast_location', 'forecast_city',
      'forecast_country', 'wx_rain_heavy', 'wx_snow_tw', 'wx_fog_rh',
      'wx_fog_spread', 'wx_storm_wind', 'wx_show_on_dashboard' ],
    [ 'station_name' => 'Nordseite' ]
);

// ── Stufe 2: die drei neuen Limit-Einstellungen ─────────────────────────
// Genau die Regression, wegen der die Merge-Semantik existiert: vor 1.7.0
// hat das Speichern eines Formulars jedes andere Setting zurueckgesetzt.
scenario(
    'Zugangsdaten speichern laesst die Limits stehen',
    [ 'client_id' => 'newid', 'client_secret' => 'newsecret' ],
    [ 'heating_limit', 'room_temp', 'cooling_limit' ],
    [ 'client_id' => 'ENC:newid' ]
);

scenario(
    'Heizgrenze wird auf 30 geklemmt',
    [ 'heating_limit' => 99 ],
    [ 'room_temp', 'cooling_limit' ],
    [ 'heating_limit' => 30.0 ]
);

// Fix 1: a cleared field must fall back to the default, not to floatval('') = 0.0.
scenario(
    'Leere Heizgrenze faellt auf den Standardwert zurueck, nicht auf 0',
    [ 'heating_limit' => '' ],
    [ 'room_temp', 'cooling_limit' ],
    [ 'heating_limit' => 15.0 ]
);

echo str_repeat( '-', 70 ) . "\n";
echo $failed === 0 ? "alle Szenarien bestanden\n\n" : "{$failed} Szenarien fehlgeschlagen\n\n";

exit( $failed > 0 ? 1 : 0 );
