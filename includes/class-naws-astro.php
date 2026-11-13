<?php
/**
 * NAWS_Astro – Weather calculations + astronomical data
 *
 * All methods are pure PHP, no external API needed.
 * Requires latitude + longitude.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Astro {

    // ── Station coordinates ──────────────────────────────────────────────────

    /**
     * Get station coordinates from DB (main module = NAMain).
     * Returns [ 'lat' => float, 'lng' => float ] or null.
     */
    public static function get_coords() {
        global $wpdb;
        $table = $wpdb->prefix . NAWS_TABLE_MODULES;

        // Ensure columns exist (migration may not have run on update)
        $col = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s', 'latitude' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- table name from constant+prefix; LIKE value via prepare()
        if ( ! $col ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN latitude  DOUBLE DEFAULT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time migration; table name from constant
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN longitude DOUBLE DEFAULT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time migration; table name from constant
            return null; // no data yet, will be filled on next sync
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from constant
        $row = $wpdb->get_row(
            "SELECT latitude, longitude FROM {$table}
             WHERE module_type IN ('NAMain','NAOldModule')
               AND latitude IS NOT NULL
             LIMIT 1",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( ! $row || $row['latitude'] === null ) return null;
        return [ 'lat' => floatval( $row['latitude'] ), 'lng' => floatval( $row['longitude'] ) ];
    }

    // ── Weather derivations ──────────────────────────────────────────────────

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
     * is defined for cold moving air, the heat index for hot humid air, and
     * Steadman's apparent temperature for the broad middle. Which of them
     * reads coldest varies with temperature and wind speed — the point is
     * that each regime is now evaluated by the model built for it.
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

    /**
     * Is the heat index defined for these conditions?
     *
     * The Rothfusz regression is fitted for hot weather and diverges badly
     * below roughly 25 °C — at -5 °C and 80 % it returns 103.9, which is not
     * a temperature anyone should be shown. Callers must gate on this rather
     * than publish whatever the polynomial produces.
     *
     * The threshold matches templates/infobar.php, which has always gated
     * its own heat-index display at 25 °C.
     */
    public static function heat_index_applies( float $temp_c ): bool {
        return $temp_c >= 25.0;
    }

    /**
     * Heat index (Rothfusz regression, °C).
     */
    public static function heat_index( float $t, float $rh ): float {
        // Convert to °F for formula
        $tf = $t * 9 / 5 + 32;
        $hi = -42.379
            + 2.04901523  * $tf
            + 10.14333127 * $rh
            - 0.22475541  * $tf * $rh
            - 0.00683783  * $tf * $tf
            - 0.05481717  * $rh * $rh
            + 0.00122874  * $tf * $tf * $rh
            + 0.00085282  * $tf * $rh * $rh
            - 0.00000199  * $tf * $tf * $rh * $rh;
        return round( ( $hi - 32 ) * 5 / 9, 1 );
    }

    /**
     * Dew point (Magnus formula, °C).
     */
    public static function dew_point( float $temp_c, float $humidity_pct ): float {
        $a  = 17.625;
        $b  = 243.04;
        $al = log( $humidity_pct / 100 ) + ( $a * $temp_c ) / ( $b + $temp_c );
        return round( $b * $al / ( $a - $al ), 1 );
    }

    /**
     * Wet-bulb temperature (Stull approximation, °C).
     *
     * This — not the air temperature — decides whether precipitation reaches
     * the ground as rain or as snow. A falling flake cools itself by
     * evaporation, so in dry air snow arrives at 3–4 °C air temperature,
     * while at 1 °C and saturated air it already rains. The transition sits
     * fairly stable around a wet-bulb temperature of 1 °C.
     *
     * Valid for roughly 5–99 % RH and −20…+50 °C, error below about 1 K.
     * Inputs are clamped so log() never receives an argument <= 0.
     *
     * @param  float $temp_c       Air temperature in °C.
     * @param  float $humidity_pct Relative humidity in % (0–100).
     * @return float               Wet-bulb temperature in °C.
     *
     * @see Stull, R. (2011). "Wet-Bulb Temperature from Relative Humidity
     *      and Air Temperature", J. Appl. Meteor. Climatol. 50, 2267–2269.
     */
    public static function wet_bulb( float $temp_c, float $humidity_pct ): float {
        $rh = max( 1.0, min( 100.0, $humidity_pct ) );

        $tw = $temp_c * atan( 0.151977 * sqrt( $rh + 8.313659 ) )
            + atan( $temp_c + $rh )
            - atan( $rh - 1.676331 )
            + 0.00391838 * pow( $rh, 1.5 ) * atan( 0.023101 * $rh )
            - 4.686035;

        return round( $tw, 1 );
    }

    // ── Sunrise / Sunset ─────────────────────────────────────────────────────

    /**
     * Returns [ 'rise' => 'HH:MM', 'set' => 'HH:MM' ] in local WP timezone.
     * Uses PHP's built-in date_sun_info() for accurate results.
     */
    public static function sun_times( float $lat, float $lng, ?int $timestamp = null ): array {
        $timestamp = $timestamp ?? time();
        $tz        = wp_timezone();

        // date_sun_info() returns UTC unix timestamps
        $info = date_sun_info( $timestamp, $lat, $lng );

        $fmt = function( $ts ) use ( $tz ) {
            if ( ! $ts || $ts === true || $ts === false ) return '--:--';
            $dt = new DateTime( 'now', $tz );
            $dt->setTimestamp( intval( $ts ) );
            return $dt->format( 'H:i' );
        };

        return [
            'rise' => $fmt( $info['sunrise'] ),
            'set'  => $fmt( $info['sunset']  ),
        ];
    }

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

    // _sun_event() is no longer needed (replaced by date_sun_info)

    // ── Moon phase ───────────────────────────────────────────────────────────

    /**
     * Returns moon data for a given timestamp (defaults to now).
     * [
     *   'phase'      => 0.0–1.0   (0 = new, 0.5 = full)
     *   'phase_pct'  => 0–100
     *   'name'       => string    (translated)
     *   'next_full'  => string    (formatted date+time)
     *   'next_full_ts' => int
     * ]
     */
    public static function moon_data( ?int $timestamp = null ): array {
        $timestamp = $timestamp ?? time();

        // Reference new moon: 2000-01-06 18:14 UTC (Julian day 2451549.5)
        $known_new = 2451549.5 + 0.75972; // JD of reference new moon
        $synodic   = 29.53058770;

        $jd    = ( $timestamp / 86400.0 ) + 2440587.5;
        $phase = fmod( ( $jd - $known_new ) / $synodic, 1.0 );
        if ( $phase < 0 ) $phase += 1.0;

        // Illumination percentage (0% = new moon, 100% = full moon)
        // Formula: (1 - cos(2π × phase)) / 2
        // phase 0.0 → cos(0) = 1    → illum = 0%
        // phase 0.5 → cos(π) = -1   → illum = 100%
        $pct  = round( ( 1 - cos( 2 * M_PI * $phase ) ) / 2 * 100 );

        // Next full moon: the true instant, not the mean one. The mean
        // synodic month drifts up to ~14 h from the real full moon, which
        // is what put this row a day away from the supermoon row before.
        $next_full_ts = self::next_full_moon( $timestamp );

        [ $name ] = self::_moon_name( $phase );

        return [
            'phase'         => $phase,
            'phase_pct'     => $pct,
            'name'          => $name,
            'next_full'     => self::format_event( $next_full_ts ),
            'next_full_ts'  => $next_full_ts,
        ];
    }

    /**
     * The first true full moon strictly after a moment.
     *
     * Every row that names a full moon — the next full moon, the supermoon —
     * goes through here, so they cannot disagree about the same lunation.
     *
     * @param int|null $from_ts Unix timestamp; defaults to time().
     * @return int              Unix timestamp (UTC) of the full moon.
     */
    public static function next_full_moon( ?int $from_ts = null ): int {
        $from_ts = $from_ts ?? time();
        $k       = (int) floor( ( $from_ts / 86400.0 + 2440587.5 - 2451550.09766 ) / 29.530588861 ) - 1;
        do {
            $ts = self::full_moon_ts( $k );
            $k++;
        } while ( $ts <= $from_ts );
        return $ts;
    }

    /**
     * True full moon of lunation $k (Meeus, Astronomical Algorithms, 2nd ed.,
     * ch. 49): the mean phase at k + 0.5, the periodic corrections for a full
     * moon and the planetary corrections A1–A14. Meeus gives the error as a
     * few seconds over several centuries around 2000; the ΔT step below adds
     * well under a minute.
     *
     * Lunation 0 is the one that starts with the new moon of 6 January 2000.
     *
     * @param int $k Lunation number.
     * @return int   Unix timestamp (UTC).
     */
    public static function full_moon_ts( int $k ): int {
        $k  = $k + 0.5;
        $T  = $k / 1236.85;
        $T2 = $T * $T;
        $T3 = $T2 * $T;
        $T4 = $T3 * $T;

        $jde = 2451550.09766 + 29.530588861 * $k + 0.00015437 * $T2 - 0.000000150 * $T3 + 0.00000000073 * $T4;

        $E  = 1 - 0.002516 * $T - 0.0000074 * $T2;
        $M  = deg2rad( 2.5534 + 29.10535670 * $k - 0.0000014 * $T2 - 0.00000011 * $T3 );
        $Mp = deg2rad( 201.5643 + 385.81693528 * $k + 0.0107582 * $T2 + 0.00001238 * $T3 - 0.000000058 * $T4 );
        $F  = deg2rad( 160.7108 + 390.67050284 * $k - 0.0016118 * $T2 - 0.00000227 * $T3 + 0.000000011 * $T4 );
        $Om = deg2rad( 124.7746 - 1.56375588 * $k + 0.0020672 * $T2 + 0.00000215 * $T3 );

        $jde += -0.40614 * sin( $Mp )
            + 0.17302 * $E * sin( $M )
            + 0.01614 * sin( 2 * $Mp )
            + 0.01043 * sin( 2 * $F )
            + 0.00734 * $E * sin( $Mp - $M )
            - 0.00515 * $E * sin( $Mp + $M )
            + 0.00209 * $E * $E * sin( 2 * $M )
            - 0.00111 * sin( $Mp - 2 * $F )
            - 0.00057 * sin( $Mp + 2 * $F )
            + 0.00056 * $E * sin( 2 * $Mp + $M )
            - 0.00042 * sin( 3 * $Mp )
            + 0.00042 * $E * sin( $M + 2 * $F )
            + 0.00038 * $E * sin( $M - 2 * $F )
            - 0.00024 * $E * sin( 2 * $Mp - $M )
            - 0.00017 * sin( $Om )
            - 0.00007 * sin( $Mp + 2 * $M )
            + 0.00004 * sin( 2 * $Mp - 2 * $F )
            + 0.00004 * sin( 3 * $M )
            + 0.00003 * sin( $Mp + $M - 2 * $F )
            + 0.00003 * sin( 2 * $Mp + 2 * $F )
            - 0.00003 * sin( $Mp + $M + 2 * $F )
            + 0.00003 * sin( $Mp - $M + 2 * $F )
            - 0.00002 * sin( $Mp - $M - 2 * $F )
            - 0.00002 * sin( 3 * $Mp + $M )
            + 0.00002 * sin( 4 * $Mp );

        // Additional (planetary) corrections A1–A14: [ coefficient, A0, rate ].
        $planetary = [
            [ 0.000325, 299.77, 0.107408 ],
            [ 0.000165, 251.88, 0.016321 ],
            [ 0.000164, 251.83, 26.651886 ],
            [ 0.000126, 349.42, 36.412478 ],
            [ 0.000110, 84.66, 18.206239 ],
            [ 0.000062, 141.74, 53.303771 ],
            [ 0.000060, 207.14, 2.453732 ],
            [ 0.000056, 154.84, 7.306860 ],
            [ 0.000047, 34.52, 27.261239 ],
            [ 0.000042, 207.19, 0.121824 ],
            [ 0.000040, 291.34, 1.844379 ],
            [ 0.000037, 161.72, 24.198154 ],
            [ 0.000035, 239.56, 25.513099 ],
            [ 0.000023, 331.55, 3.592518 ],
        ];
        foreach ( $planetary as $i => $p ) {
            $a = $p[1] + $p[2] * $k;
            if ( 0 === $i ) {
                $a -= 0.009173 * $T2; // A1 carries a T² term.
            }
            $jde += $p[0] * sin( deg2rad( $a ) );
        }

        // JDE is Terrestrial Time; Unix time is UTC.
        $year = 2000 + $k / 12.3685;
        return (int) round( ( $jde - 2440587.5 ) * 86400 - self::delta_t( $year ) );
    }

    /**
     * ΔT = TT − UT in seconds (Espenak & Meeus polynomial for 2005–2050,
     * used outside that range too — the error there is minutes at worst,
     * still far below what a dated row on a weather page shows).
     */
    private static function delta_t( float $year ): float {
        $t = $year - 2000;
        return 62.92 + 0.32217 * $t + 0.005589 * $t * $t;
    }

    private static function _moon_name( float $phase ): array {
        // phase 0–1
        if ( $phase < 0.0625 || $phase >= 0.9375 ) return [ __( 'New Moon', 'xtx-integration-for-netatmo' ) ];
        if ( $phase < 0.1875 )                      return [ __( 'Waxing Crescent', 'xtx-integration-for-netatmo' ) ];
        if ( $phase < 0.3125 )                      return [ __( 'First Quarter', 'xtx-integration-for-netatmo' ) ];
        if ( $phase < 0.4375 )                      return [ __( 'Waxing Gibbous', 'xtx-integration-for-netatmo' ) ];
        if ( $phase < 0.5625 )                      return [ __( 'Full Moon', 'xtx-integration-for-netatmo' ) ];
        if ( $phase < 0.6875 )                      return [ __( 'Waning Gibbous', 'xtx-integration-for-netatmo' ) ];
        if ( $phase < 0.8125 )                      return [ __( 'Last Quarter', 'xtx-integration-for-netatmo' ) ];
        return                                             [ __( 'Waning Crescent', 'xtx-integration-for-netatmo' ) ];
    }

    // ── Moonrise / Moonset ───────────────────────────────────────────────────

    /**
     * Returns [ 'rise' => 'HH:MM', 'set' => 'HH:MM' ] or '--:--' if not visible.
     * Algorithm: iterative altitude search (Meeus-based), accurate to ~1 min.
     */
    public static function moon_rise_set( float $lat, float $lng, ?int $timestamp = null ): array {
        $timestamp = $timestamp ?? time();
        $tz        = wp_timezone();

        // Start from local midnight (not UTC midnight) to correctly catch events across day boundary
        $local_dt  = new DateTime( 'now', $tz );
        $local_dt->setTimestamp( $timestamp );
        $local_dt->setTime( 0, 0, 0 );
        $day_start_unix = $local_dt->getTimestamp();
        $jd0       = 2440587.5 + $day_start_unix / 86400.0;

        $rise = null;
        $set  = null;
        $prev_alt = self::_moon_altitude( $jd0, $lat, $lng );

        // Search 36h to handle events that cross midnight
        for ( $h = 1; $h <= 36; $h++ ) {
            $jd  = $jd0 + $h / 24.0;
            $alt = self::_moon_altitude( $jd, $lat, $lng );

            if ( $rise === null && $prev_alt < 0 && $alt >= 0 ) {
                $a = $jd - 1 / 24.0; $b = $jd;
                for ( $i = 0; $i < 20; $i++ ) {
                    $m  = ( $a + $b ) / 2;
                    $ma = self::_moon_altitude( $m, $lat, $lng );
                    if ( $ma < 0 ) $a = $m; else $b = $m;
                }
                $rise = ( $a + $b ) / 2;
            }
            if ( $set === null && $prev_alt >= 0 && $alt < 0 ) {
                $a = $jd - 1 / 24.0; $b = $jd;
                for ( $i = 0; $i < 20; $i++ ) {
                    $m  = ( $a + $b ) / 2;
                    $ma = self::_moon_altitude( $m, $lat, $lng );
                    if ( $ma >= 0 ) $a = $m; else $b = $m;
                }
                $set = ( $a + $b ) / 2;
            }
            $prev_alt = $alt;
        }

        $fmt = function( $jd ) use ( $tz, $day_start_unix ) {
            if ( $jd === null ) return '--:--';
            $unix = intval( ( $jd - 2440587.5 ) * 86400 );
            $d = new DateTime( 'now', $tz );
            $d->setTimestamp( $unix );
            // If time falls on next calendar day, append "+1"
            $same_day = ( $d->format( 'Y-m-d' ) === date_create_from_format( 'U', $day_start_unix )->setTimezone( $tz )->format( 'Y-m-d' ) );
            return $d->format( 'H:i' ) . ( $same_day ? '' : ' (+1)' );
        };

        return [ 'rise' => $fmt( $rise ), 'set' => $fmt( $set ) ];
    }

    /** Julian Day Number for midnight UTC */
    private static function _jd( int $y, int $m, int $d ): float {
        if ( $m <= 2 ) { $y--; $m += 12; }
        $A = intval( $y / 100 );
        $B = 2 - $A + intval( $A / 4 );
        return intval( 365.25 * ( $y + 4716 ) )
             + intval( 30.6001 * ( $m + 1 ) )
             + $d + $B - 1524.5;
    }

    /** Moon altitude above horizon at given Julian Day for lat/lng (degrees) */
    private static function _moon_altitude( float $jd, float $lat, float $lng ): float {
        $T  = ( $jd - 2451545.0 ) / 36525.0;

        // Moon's longitude, latidude, parallax (low-precision, Meeus ch.47)
        $L0 = fmod( 218.3164477 + 481267.88123421 * $T, 360 );
        $M  = deg2rad( fmod( 357.5291092 + 35999.0502909 * $T, 360 ) );
        $Mm = deg2rad( fmod( 134.9633964 + 477198.8675055 * $T, 360 ) );
        $D  = deg2rad( fmod( 297.8501921 + 445267.1114034 * $T, 360 ) );
        $F  = deg2rad( fmod( 93.2720950  + 483202.0175233 * $T, 360 ) );

        $lon = $L0
            + 6.288774 * sin( $Mm )
            + 1.274027 * sin( 2 * $D - $Mm )
            + 0.658314 * sin( 2 * $D )
            + 0.213618 * sin( 2 * $Mm )
            - 0.185116 * sin( $M )
            - 0.114332 * sin( 2 * $F );

        $lat_moon = 5.127832 * sin( $F )
            + 0.280602 * sin( $Mm + $F )
            + 0.277693 * sin( $Mm - $F )
            + 0.173238 * sin( 2 * $D - $F );

        // Equatorial coords
        $ep  = 23.4393 - 0.0000004 * $T; // obliquity
        $lon_r = deg2rad( $lon );
        $lat_r = deg2rad( $lat_moon );
        $ep_r  = deg2rad( $ep );

        $ra  = atan2( sin( $lon_r ) * cos( $ep_r ) - tan( $lat_r ) * sin( $ep_r ), cos( $lon_r ) );
        $dec = asin( sin( $lat_r ) * cos( $ep_r ) + cos( $lat_r ) * sin( $ep_r ) * sin( $lon_r ) );

        // Greenwich Sidereal Time → Local Hour Angle
        $GMST = fmod( 280.46061837 + 360.98564736629 * ( $jd - 2451545.0 ), 360 );
        $LST  = deg2rad( fmod( $GMST + $lng, 360 ) );
        $HA   = $LST - $ra;

        // Altitude
        $alt = asin(
            sin( deg2rad( $lat ) ) * sin( $dec )
            + cos( deg2rad( $lat ) ) * cos( $dec ) * cos( $HA )
        );
        return rad2deg( $alt ) - 0.5667; // correct for refraction + semi-diameter
    }

    // ── Supermoon ────────────────────────────────────────────────────────────

    /**
     * A dated astronomical event, in the site's own date and time format.
     *
     * Both event finders used to hand back 'd.m.Y – H:i' plus the word "Uhr",
     * which is German whatever the language setting says — and this plugin
     * ships a complete Norwegian translation. wp_date() with the site's own
     * format options is the same route NAWS_Calc already takes for the start
     * of the growing season, so the two agree.
     *
     * The time is kept, not trimmed to the date: an eclipse at 20:11 and one
     * at 04:00 are a different proposition for whoever wants to watch it.
     */
    private static function format_event( int $ts ): string {
        $format = get_option( 'date_format', 'd.m.Y' ) . ' – ' . get_option( 'time_format', 'H:i' );
        return wp_date( $format, $ts );
    }

    /**
     * Next supermoon: the first true full moon (see full_moon_ts()) at which
     * the Moon is closer than 360,000 km. The threshold is the one this
     * plugin has always used; the distance is now taken at the real full
     * moon instead of a mean one up to a day off.
     *
     * Returns [ 'date' => string, 'ts' => int, 'distance_km' => int ] or null.
     */
    public static function next_supermoon( ?int $from_ts = null ): ?array {
        $from_ts = $from_ts ?? time();

        $ts = $from_ts;
        for ( $i = 0; $i < 25; $i++ ) { // a little over two years
            $ts   = self::next_full_moon( $ts );
            $jde  = 2440587.5 + ( $ts + self::delta_t( 1970 + $ts / 31557600.0 ) ) / 86400.0;
            $dist = self::_moon_distance_km( $jde );
            if ( $dist < 360000 ) {
                return [
                    'date'        => self::format_event( $ts ),
                    'ts'          => $ts,
                    'distance_km' => intval( round( $dist ) ),
                ];
            }
        }
        return null;
    }

    /**
     * Moon–Earth distance in km (centre to centre) at a Julian Ephemeris Day.
     *
     * Meeus ch. 47, the distance column of table 47.A down to terms of about
     * 10 km. The first eleven terms alone ran 200–400 km long at perigee,
     * which is the whole margin a supermoon threshold works with.
     */
    private static function _moon_distance_km( float $jd ): float {
        $T  = ( $jd - 2451545.0 ) / 36525.0;
        $E  = 1 - 0.002516 * $T - 0.0000074 * $T * $T;
        $Mm = deg2rad( fmod( 134.9633964 + 477198.8675055 * $T, 360 ) );
        $D  = deg2rad( fmod( 297.8501921 + 445267.1114034 * $T, 360 ) );
        $M  = deg2rad( fmod( 357.5291092 +  35999.0502909 * $T, 360 ) );
        $F  = deg2rad( fmod(  93.2720950 + 483202.0175233 * $T, 360 ) );

        // [ D, M, M', F, coefficient in km ]
        $terms = [
            [ 0,  0,  1,  0, -20905.355 ],
            [ 2,  0, -1,  0,  -3699.111 ],
            [ 2,  0,  0,  0,  -2955.968 ],
            [ 0,  0,  2,  0,   -569.925 ],
            [ 0,  1,  0,  0,     48.888 ],
            [ 0,  0,  0,  2,     -3.149 ],
            [ 2,  0, -2,  0,    246.158 ],
            [ 2, -1, -1,  0,   -152.138 ],
            [ 2,  0,  1,  0,   -170.733 ],
            [ 2, -1,  0,  0,   -204.586 ],
            [ 0,  1, -1,  0,   -129.620 ],
            [ 1,  0,  0,  0,    108.743 ],
            [ 0,  1,  1,  0,    104.755 ],
            [ 2,  0,  0, -2,     10.321 ],
            [ 0,  0,  1, -2,     79.661 ],
            [ 4,  0, -1,  0,    -34.782 ],
            [ 0,  0,  3,  0,    -23.210 ],
            [ 4,  0, -2,  0,    -21.636 ],
            [ 2,  1, -1,  0,     24.208 ],
            [ 2,  1,  0,  0,     30.824 ],
            [ 1,  0, -1,  0,     -8.379 ],
            [ 1,  1,  0,  0,    -16.675 ],
            [ 2, -1,  1,  0,    -12.831 ],
            [ 2,  0,  2,  0,    -10.445 ],
            [ 4,  0,  0,  0,    -11.650 ],
            [ 2,  0, -3,  0,     14.403 ],
        ];

        $r = 385000.56;
        foreach ( $terms as $t ) {
            $arg = $t[0] * $D + $t[1] * $M + $t[2] * $Mm + $t[3] * $F;
            $e   = 0 === $t[1] ? 1.0 : ( 1 === abs( $t[1] ) ? $E : $E * $E );
            $r  += $t[4] * $e * cos( $arg );
        }
        return $r;
    }

    // ── Lunar Eclipse ────────────────────────────────────────────────────────

    /**
     * Lunar eclipses 2024–2040 from NASA's Five Millennium Canon of Lunar
     * Eclipses (Espenak & Meeus): time of greatest eclipse in UTC and type.
     *
     * A table, not a calculation: the old classifier guessed the type from
     * the Moon's latitude at a mean full moon and invented total eclipses
     * that do not happen. Every entry was checked against Meeus ch. 54 (all
     * within two minutes, same type, none missing); tests/test-moon.php
     * checks each against full_moon_ts(). When the table runs out, there is
     * simply no eclipse row.
     *
     * @return array<int, array{ts: int, type: string}> Sorted by time.
     */
    public static function lunar_eclipses(): array {
        $canon = [
            '2024-03-25 07:13' => 'penumbral',
            '2024-09-18 02:44' => 'partial',
            '2025-03-14 06:59' => 'total',
            '2025-09-07 18:12' => 'total',
            '2026-03-03 11:34' => 'total',
            '2026-08-28 04:14' => 'partial',
            '2027-02-20 23:14' => 'penumbral',
            '2027-07-18 16:04' => 'penumbral',
            '2027-08-17 07:14' => 'penumbral',
            '2028-01-12 04:14' => 'partial',
            '2028-07-06 18:20' => 'partial',
            '2028-12-31 16:53' => 'total',
            '2029-06-26 03:23' => 'total',
            '2029-12-20 22:43' => 'total',
            '2030-06-15 18:34' => 'partial',
            '2030-12-09 22:28' => 'penumbral',
            '2031-05-07 03:52' => 'penumbral',
            '2031-06-05 11:45' => 'penumbral',
            '2031-10-30 07:46' => 'penumbral',
            '2032-04-25 15:14' => 'total',
            '2032-10-18 19:03' => 'total',
            '2033-04-14 19:13' => 'total',
            '2033-10-08 10:56' => 'total',
            '2034-04-03 19:06' => 'penumbral',
            '2034-09-28 02:47' => 'partial',
            '2035-02-22 09:05' => 'penumbral',
            '2035-08-19 01:12' => 'partial',
            '2036-02-11 22:13' => 'total',
            '2036-08-07 02:52' => 'total',
            '2037-01-31 14:01' => 'total',
            '2037-07-27 04:09' => 'partial',
            '2038-01-21 03:49' => 'penumbral',
            '2038-06-17 02:45' => 'penumbral',
            '2038-07-16 11:35' => 'penumbral',
            '2038-12-11 17:45' => 'penumbral',
            '2039-06-06 18:54' => 'partial',
            '2039-11-30 16:56' => 'partial',
            '2040-05-26 11:46' => 'total',
            '2040-11-18 19:04' => 'total',
        ];

        $out = [];
        foreach ( $canon as $when => $type ) {
            $out[] = [
                'ts'   => gmmktime( (int) substr( $when, 11, 2 ), (int) substr( $when, 14, 2 ), 0, (int) substr( $when, 5, 2 ), (int) substr( $when, 8, 2 ), (int) substr( $when, 0, 4 ) ),
                'type' => $type,
            ];
        }
        return $out;
    }

    /**
     * Next lunar eclipse (penumbral, partial or total) after a moment.
     * Returns [ 'date' => string, 'type' => string, 'ts' => int ] or null
     * once the table has run out.
     */
    public static function next_lunar_eclipse( ?int $from_ts = null ): ?array {
        $from_ts = $from_ts ?? time();

        foreach ( self::lunar_eclipses() as $ecl ) {
            if ( $ecl['ts'] > $from_ts ) {
                return [
                    'date' => self::format_event( $ecl['ts'] ),
                    'type' => $ecl['type'],
                    'ts'   => $ecl['ts'],
                ];
            }
        }
        return null;
    }
}
