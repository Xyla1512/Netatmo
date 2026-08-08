<?php
/**
 * NAWS_Weather_State – decides which weather state the icon shows.
 *
 * Split in two on purpose:
 *
 *   get_current()  reads WordPress, the database and the forecast API
 *   decide()       pure function, no WordPress, no I/O
 *
 * That split is what makes the whole precedence table testable without a
 * framework: feed decide() a set of readings, assert the resulting state.
 *
 * Rendering lives in NAWS_Weather_Icons. This class produces no markup.
 *
 * @package NAWS
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Weather_State {

    /** The twelve states. Also the language-file suffixes (wx_state_*). */
    const STATES = [
        'clear_day', 'clear_night', 'fair', 'partly', 'overcast', 'fog',
        'rain', 'rain_heavy', 'snow', 'sleet', 'thunder', 'storm',
    ];

    /** Threshold defaults. Mirrors the settings in the admin UI. */
    const DEFAULTS = [
        'rain_heavy' => 4.0,   // mm/h
        'snow_tw'    => 1.0,   // °C wet-bulb
        'fog_rh'     => 97.0,  // %
        'fog_spread' => 0.5,   // K
        'storm_wind' => 75.0,  // km/h
    ];

    /** WMO codes grouped by what they mean for the icon. */
    const WMO_SNOW       = [ 71, 73, 75, 77, 85, 86 ];
    const WMO_RAIN       = [ 51, 53, 55, 56, 57, 61, 63, 80, 81 ];
    const WMO_RAIN_HEAVY = [ 65, 82 ];
    const WMO_SLEET      = [ 66, 67, 68, 69 ];
    const WMO_FOG        = [ 45, 48 ];

    /* ================================================================
     * WordPress-facing entry point
     * ================================================================*/

    /**
     * Current weather state, ready for rendering.
     *
     * @return array{
     *     state: string, wmo: ?int, source: string,
     *     is_day: bool, stale: bool, ts: int
     * }  state is '' when nothing can be determined.
     */
    public static function get_current(): array {
        $opts = get_option( 'naws_settings', [] );

        $conditions = class_exists( 'NAWS_Forecast' )
            ? NAWS_Forecast::get_current_conditions()
            : [];

        $station = self::read_station();

        $is_day = $conditions['is_day'] ?? null;
        if ( $is_day === null ) {
            $is_day = self::is_daytime();
        }

        return self::decide( [
            'rain'       => $station['rain'],
            'wind'       => $station['wind'],
            'temp'       => $station['temp'],
            'humidity'   => $station['humidity'],
            'wmo'        => $conditions['wmo']      ?? null,
            'snowfall'   => $conditions['snowfall'] ?? null,
            'is_day'     => (bool) $is_day,
            'stale'      => (bool) ( $conditions['stale'] ?? true ),
            'ts'         => time(),
            'thresholds' => self::thresholds( $opts ),
        ] );
    }

    /**
     * Read the station values the precedence table needs.
     *
     * Every value may be null, and null is meaningful: it means "this
     * module is absent or has not reported", which is different from a
     * measured zero. The API fallback ranks hinge on that difference.
     *
     * @return array{rain: ?float, wind: ?float, temp: ?float, humidity: ?float}
     */
    private static function read_station(): array {
        $out = [ 'rain' => null, 'wind' => null, 'temp' => null, 'humidity' => null ];

        if ( ! class_exists( 'NAWS_Database' ) ) {
            return $out;
        }

        $modules = NAWS_Database::get_modules( true );
        $by_type = [];
        foreach ( $modules as $m ) {
            $by_type[ $m['module_type'] ][] = $m['module_id'];
        }

        // Temperature and humidity come from the OUTDOOR module only.
        // The base station sits indoors; its readings say nothing about
        // whether precipitation outside falls as rain or snow.
        $outdoor = $by_type['NAModule1'][0] ?? null;
        if ( $outdoor ) {
            foreach ( NAWS_Database::get_latest_readings( $outdoor ) as $r ) {
                if ( $r['parameter'] === 'Temperature' ) $out['temp']     = (float) $r['value'];
                if ( $r['parameter'] === 'Humidity' )    $out['humidity'] = (float) $r['value'];
            }
        }

        $rain_mod = $by_type['NAModule3'][0] ?? null;
        if ( $rain_mod ) {
            foreach ( NAWS_Database::get_latest_readings( $rain_mod ) as $r ) {
                if ( $r['parameter'] === 'Rain' ) $out['rain'] = (float) $r['value'];
            }
        }

        $wind_mod = $by_type['NAModule2'][0] ?? null;
        if ( $wind_mod ) {
            foreach ( NAWS_Database::get_latest_readings( $wind_mod ) as $r ) {
                // Gusts drive the storm rule: a gale is felt in the gusts,
                // not in the ten-minute mean.
                if ( $r['parameter'] === 'GustStrength' ) $out['wind'] = (float) $r['value'];
                if ( $r['parameter'] === 'WindStrength' && $out['wind'] === null ) {
                    $out['wind'] = (float) $r['value'];
                }
            }
        }

        return $out;
    }

    /**
     * Day or night at the station.
     *
     * NAWS_Astro::sun_times() formats to 'HH:MM' strings for display and
     * cannot be compared, so date_sun_info() is used directly — the same
     * function sun_times() builds on. Without coordinates (station never
     * synced) daytime is assumed: a sun icon is the less wrong guess.
     */
    private static function is_daytime(): bool {
        if ( ! class_exists( 'NAWS_Astro' ) ) {
            return true;
        }
        $coords = NAWS_Astro::get_coords();
        if ( ! $coords ) {
            return true;
        }

        $now  = time();
        $info = date_sun_info( $now, $coords['lat'], $coords['lng'] );

        // Polar day/night: sunrise and sunset are booleans, not timestamps.
        if ( ! is_int( $info['sunrise'] ) || ! is_int( $info['sunset'] ) ) {
            return (bool) ( $info['sunrise'] ?? true );
        }

        return $now >= $info['sunrise'] && $now < $info['sunset'];
    }

    /** Merge configured thresholds over the defaults, clamped. */
    private static function thresholds( array $opts ): array {
        return [
            'rain_heavy' => self::clamp( $opts['wx_rain_heavy'] ?? null, self::DEFAULTS['rain_heavy'],   0.1,  50.0 ),
            'snow_tw'    => self::clamp( $opts['wx_snow_tw']    ?? null, self::DEFAULTS['snow_tw'],    -20.0,   5.0 ),
            'fog_rh'     => self::clamp( $opts['wx_fog_rh']     ?? null, self::DEFAULTS['fog_rh'],      80.0, 100.0 ),
            'fog_spread' => self::clamp( $opts['wx_fog_spread'] ?? null, self::DEFAULTS['fog_spread'],   0.1,   5.0 ),
            'storm_wind' => self::clamp( $opts['wx_storm_wind'] ?? null, self::DEFAULTS['storm_wind'],  20.0, 200.0 ),
        ];
    }

    private static function clamp( $value, float $default, float $min, float $max ): float {
        if ( $value === null || $value === '' || ! is_numeric( $value ) ) {
            return $default;
        }
        return max( $min, min( $max, (float) $value ) );
    }

    /* ================================================================
     * The precedence table – pure function
     * ================================================================*/

    /**
     * Decide the weather state from a set of readings.
     *
     * No WordPress, no database, no HTTP. Every input is explicit so the
     * whole table can be exercised by a plain PHP script.
     *
     * Null and 0.0 are NOT interchangeable for 'rain': null means the gauge
     * is absent or silent (the API may fill in), 0.0 means the gauge
     * measured no precipitation (the API may not override it).
     *
     * @param array $in {
     *     @type ?float $rain       mm/h, null = no gauge / no reading
     *     @type ?float $wind       km/h gust, null = no wind module
     *     @type ?float $temp       outdoor °C
     *     @type ?float $humidity   outdoor %
     *     @type ?int   $wmo        current WMO code, null = no API data
     *     @type ?float $snowfall   cm in the last hour, null = unavailable
     *     @type bool   $is_day
     *     @type bool   $stale      WMO code is a last-known value
     *     @type int    $ts
     *     @type array  $thresholds
     * }
     * @return array{state: string, wmo: ?int, source: string, is_day: bool, stale: bool, ts: int}
     */
    public static function decide( array $in ): array {
        $t        = ( $in['thresholds'] ?? [] ) + self::DEFAULTS;
        $rain     = self::nf( $in['rain'] ?? null );
        $wind     = self::nf( $in['wind'] ?? null );
        $temp     = self::nf( $in['temp'] ?? null );
        $humidity = self::nf( $in['humidity'] ?? null );
        $snowfall = self::nf( $in['snowfall'] ?? null );
        $wmo      = isset( $in['wmo'] ) && $in['wmo'] !== null ? (int) $in['wmo'] : null;
        $is_day   = (bool) ( $in['is_day'] ?? true );
        $stale    = (bool) ( $in['stale'] ?? false );
        $ts       = (int) ( $in['ts'] ?? 0 );

        $out = function ( string $state, string $source ) use ( $wmo, $is_day, $stale, $ts ): array {
            return [
                'state'  => $state,
                'wmo'    => $wmo,
                'source' => $state === '' ? '' : $source,
                'is_day' => $is_day,
                'stale'  => $stale,
                'ts'     => $ts,
            ];
        };

        // Wet-bulb temperature is the phase criterion throughout.
        $tw = ( $temp !== null && $humidity !== null )
            ? NAWS_Astro::wet_bulb( $temp, $humidity )
            : null;

        $snow_code = $wmo !== null && in_array( $wmo, self::WMO_SNOW, true );

        // ── Pre-filter F: meltwater ──────────────────────────────────
        // Liquid cannot fall this far below freezing. The tip comes from
        // thawing old snow or a frozen funnel, so the value is discarded
        // and the cascade proceeds as if the gauge had never reported.
        // Must run BEFORE the cascade: rank 4 covers the same condition
        // and would otherwise consume it first.
        if ( $rain !== null && $rain > 0 && $tw !== null && $tw < ( $t['snow_tw'] - 1.0 ) && ! $snow_code ) {
            $rain = null;
        }

        // ── Rank 1: storm ───────────────────────────────────────────
        if ( $wind !== null && $wind >= $t['storm_wind'] ) {
            return $out( 'storm', 'station' );
        }

        // ── Rank 2: thunder and hail – the station cannot see these ──
        if ( $wmo === 95 ) {
            return $out( 'thunder', 'api' );
        }
        if ( $wmo === 96 || $wmo === 99 ) {
            return $out( 'sleet', 'api' );
        }

        // ── Rank 3: snow ────────────────────────────────────────────
        // Occurrence from the API (the tipping bucket is blind to snow),
        // phase from the station's own wet-bulb temperature.
        if ( ( $snowfall !== null && $snowfall > 0 ) || $snow_code ) {
            if ( $tw === null || $tw < $t['snow_tw'] ) {
                return $out( 'snow', $tw === null ? 'api' : 'hybrid' );
            }
        }

        // ── Rank 4: snow from station values alone ──────────────────
        if ( $rain !== null && $rain > 0 && $tw !== null && $tw < $t['snow_tw'] ) {
            return $out( 'snow', 'station' );
        }

        // ── Ranks 5 and 6: rain from the gauge ──────────────────────
        if ( $rain !== null && $rain >= $t['rain_heavy'] ) {
            return $out( 'rain_heavy', 'station' );
        }
        if ( $rain !== null && $rain > 0 ) {
            return $out( 'rain', 'station' );
        }

        // ── Rank 7: fog from humidity and dew-point spread ──────────
        if ( $temp !== null && $humidity !== null && $humidity >= $t['fog_rh'] ) {
            $spread = $temp - NAWS_Astro::dew_point( $temp, $humidity );
            if ( $spread <= $t['fog_spread'] ) {
                return $out( 'fog', 'station' );
            }
        }

        // ── Rank 8: API precipitation, only where the gauge is silent ──
        if ( $rain === null && $wmo !== null ) {
            if ( in_array( $wmo, self::WMO_RAIN_HEAVY, true ) ) return $out( 'rain_heavy', 'api' );
            if ( in_array( $wmo, self::WMO_SLEET, true ) )      return $out( 'sleet', 'api' );
            if ( in_array( $wmo, self::WMO_RAIN, true ) )       return $out( 'rain', 'api' );
        }

        // ── Rank 9: API fog, only without outdoor humidity ──────────
        if ( $humidity === null && $wmo !== null && in_array( $wmo, self::WMO_FOG, true ) ) {
            return $out( 'fog', 'api' );
        }

        // ── Rank 10: cloudiness – only the API can supply this ──────
        if ( $wmo === 0 ) return $out( $is_day ? 'clear_day' : 'clear_night', 'api' );
        if ( $wmo === 1 ) return $out( 'fair', 'api' );
        if ( $wmo === 2 ) return $out( 'partly', 'api' );
        if ( $wmo === 3 ) return $out( 'overcast', 'api' );

        // Any precipitation code that reaches this line was contradicted by
        // the station: the gauge measured 0.0, so the rain ranks were not
        // allowed to fire. The cloud statement inside the code still holds
        // though — it does not precipitate out of a clear sky. Falling back
        // to 'overcast' is the honest remainder of that code.
        if ( $wmo !== null && $wmo >= 45 ) {
            return $out( 'overcast', 'api' );
        }

        // ── Rank 11: nothing applies – show nothing, never guess ────
        return $out( '', '' );
    }

    /** Nullable float cast: preserves null, converts numeric strings. */
    private static function nf( $v ): ?float {
        return ( $v === null || $v === '' || ! is_numeric( $v ) ) ? null : (float) $v;
    }
}
