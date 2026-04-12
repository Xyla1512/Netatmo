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
        $col = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'latitude'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant; schema introspection query
        if ( ! $col ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN latitude  DOUBLE DEFAULT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time migration; table name from constant
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN longitude DOUBLE DEFAULT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time migration; table name from constant
            return null; // no data yet, will be filled on next sync
        }

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant
            "SELECT latitude, longitude FROM {$table}
             WHERE module_type IN ('NAMain','NAOldModule')
               AND latitude IS NOT NULL
             LIMIT 1",
            ARRAY_A
        );
        if ( ! $row || $row['latitude'] === null ) return null;
        return [ 'lat' => floatval( $row['latitude'] ), 'lng' => floatval( $row['longitude'] ) ];
    }

    // ── Weather derivations ──────────────────────────────────────────────────

    /**
     * Feels-like / Apparent Temperature (Steadman, Australian Bureau of Meteorology).
     *
     * AT = Ta + 0.33 × e − 0.70 × ws − 4.00
     *
     * Works across the full temperature range (−40 °C to +50 °C) without gaps.
     * At low temps + wind the wind-chill effect dominates,
     * at high temps + humidity the vapour-pressure effect dominates.
     *
     * @param float $temp_c       Air temperature in °C.
     * @param float $humidity_pct Relative humidity in % (0–100).
     * @param float $wind_kmh     Wind speed in km/h.
     * @return float              Apparent temperature in °C.
     *
     * @see https://en.wikipedia.org/wiki/Apparent_temperature
     * @see Steadman, R. G. (1984). "A Universal Scale of Apparent Temperature"
     */
    public static function feels_like( float $temp_c, float $humidity_pct, float $wind_kmh ): float {
        // Water vapour pressure (hPa) via Magnus formula
        $e = ( $humidity_pct / 100.0 ) * 6.105 * exp( ( 17.27 * $temp_c ) / ( 237.7 + $temp_c ) );

        // Wind speed in m/s (Netatmo delivers km/h)
        $ws = $wind_kmh / 3.6;

        // Apparent temperature (Steadman / BOM)
        $at = $temp_c + 0.33 * $e - 0.70 * $ws - 4.00;

        return round( $at, 1 );
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

    // _sun_event() is no longer needed (replaced by date_sun_info)

    // ── Moon phase ───────────────────────────────────────────────────────────

    /**
     * Returns moon data for a given timestamp (defaults to now).
     * [
     *   'phase'      => 0.0–1.0   (0 = new, 0.5 = full)
     *   'phase_pct'  => 0–100
     *   'name'       => string    (translated)
     *   'emoji'      => string
     *   'next_full'  => string    (formatted date+time)
     *   'next_full_ts' => int
     * ]
     */
    public static function moon_data( ?int $timestamp = null ): array {
        $timestamp = $timestamp ?? time();
        $tz        = wp_timezone();

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

        // Next full moon
        $days_to_full = ( 0.5 - $phase );
        if ( $days_to_full < 0 ) $days_to_full += 1.0;
        $next_full_ts  = intval( $timestamp + $days_to_full * $synodic * 86400 );
        $next_full_dt  = new DateTime( 'now', $tz );
        $next_full_dt->setTimestamp( $next_full_ts );

        [ $name, $emoji ] = self::_moon_name( $phase );

        return [
            'phase'         => $phase,
            'phase_pct'     => $pct,
            'name'          => $name,
            'emoji'         => $emoji,
            'next_full'     => $next_full_dt->format( 'd.m.Y – H:i' ) . ' Uhr',
            'next_full_ts'  => $next_full_ts,
        ];
    }

    private static function _moon_name( float $phase ): array {
        // phase 0–1
        if ( $phase < 0.0625 || $phase >= 0.9375 ) return [ naws__( 'moon_new' ),            '🌑' ];
        if ( $phase < 0.1875 )                      return [ naws__( 'moon_waxing_crescent' ), '🌒' ];
        if ( $phase < 0.3125 )                      return [ naws__( 'moon_first_quarter' ),  '🌓' ];
        if ( $phase < 0.4375 )                      return [ naws__( 'moon_waxing_gibbous' ), '🌔' ];
        if ( $phase < 0.5625 )                      return [ naws__( 'moon_full' ),            '🌕' ];
        if ( $phase < 0.6875 )                      return [ naws__( 'moon_waning_gibbous' ), '🌖' ];
        if ( $phase < 0.8125 )                      return [ naws__( 'moon_last_quarter' ),   '🌗' ];
        return                                             [ naws__( 'moon_waning_crescent' ), '🌘' ];
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
     * Next supermoon: full moon where distance < 360,000 km (Nolle definition).
     * Returns [ 'date' => 'DD.MM.YYYY – HH:MM', 'distance_km' => int ] or null.
     */
    public static function next_supermoon( ?int $from_ts = null ): ?array {
        $from_ts = $from_ts ?? time();
        $tz      = wp_timezone();

        // Iterate full moons: synodic = 29.53058770 days
        // Reference full moon: 2000-01-20 04:41 UTC
        $ref_full  = 2451563.694444; // JD of reference full moon
        $synodic   = 29.53058770;

        $jd_now    = 2440587.5 + $from_ts / 86400.0;
        $n         = ceil( ( $jd_now - $ref_full ) / $synodic );

        for ( $i = 0; $i < 24; $i++ ) { // search up to 2 years
            $jd_full = $ref_full + ( $n + $i ) * $synodic;
            if ( $jd_full <= $jd_now ) continue;

            $dist = self::_moon_distance_km( $jd_full );
            if ( $dist < 360000 ) {
                $unix = intval( ( $jd_full - 2440587.5 ) * 86400 );
                $dt   = new DateTime( 'now', $tz );
                $dt->setTimestamp( $unix );
                return [
                    'date'        => $dt->format( 'd.m.Y – H:i' ) . ' Uhr',
                    'distance_km' => intval( $dist ),
                ];
            }
        }
        return null;
    }

    /** Moon–Earth distance in km at given Julian Day */
    private static function _moon_distance_km( float $jd ): float {
        $T  = ( $jd - 2451545.0 ) / 36525.0;
        $Mm = deg2rad( fmod( 134.9633964 + 477198.8675055 * $T, 360 ) );
        $D  = deg2rad( fmod( 297.8501921 + 445267.1114034 * $T, 360 ) );
        $M  = deg2rad( fmod( 357.5291092 +  35999.0502909 * $T, 360 ) );
        $F  = deg2rad( fmod(  93.2720950 + 483202.0175233 * $T, 360 ) );

        $r = 385000.56
            - 20905.355 * cos( $Mm )
            -  3699.111 * cos( 2*$D - $Mm )
            -  2955.968 * cos( 2*$D )
            -   569.925 * cos( 2*$Mm )
            +    48.888 * cos( $M )
            -    3.149  * cos( 2*$F )
            +  246.158  * cos( 2*$D - 2*$Mm )
            -  152.138  * cos( 2*$D - $M - $Mm )
            -  170.733  * cos( 2*$D + $Mm )
            -  204.586  * cos( 2*$D - $M )
            -   129.620 * cos( $Mm - $M );

        return $r;
    }

    // ── Lunar Eclipse ────────────────────────────────────────────────────────

    /**
     * Next lunar eclipse (penumbral, partial or total).
     * Returns [ 'date' => string, 'type' => string ] or null.
     *
     * Algorithm: at each full moon, check if Moon is close enough to ecliptic
     * to pass through Earth's shadow (umbra/penumbra).
     */
    public static function next_lunar_eclipse( ?int $from_ts = null ): ?array {
        $from_ts  = $from_ts ?? time();
        $tz       = wp_timezone();

        $ref_full = 2451563.694444;
        $synodic  = 29.53058770;
        $jd_now   = 2440587.5 + $from_ts / 86400.0;
        $n        = ceil( ( $jd_now - $ref_full ) / $synodic );

        for ( $i = 0; $i < 48; $i++ ) {
            $jd_full = $ref_full + ( $n + $i ) * $synodic;
            if ( $jd_full <= $jd_now ) continue;

            $T   = ( $jd_full - 2451545.0 ) / 36525.0;
            $F   = fmod( 93.2720950 + 483202.0175233 * $T, 360 );
            // F is argument of latitude – eclipse possible when |sin(F)| is small
            $sinF = abs( sin( deg2rad( $F ) ) );

            // Penumbral limit: sinF < 0.36, Umbral/partial: sinF < 0.26, Total: sinF < 0.09
            if ( $sinF < 0.36 ) {
                $type = $sinF < 0.09 ? 'total' : ( $sinF < 0.26 ? 'partial' : 'penumbral' );
                $unix = intval( ( $jd_full - 2440587.5 ) * 86400 );
                $dt   = new DateTime( 'now', $tz );
                $dt->setTimestamp( $unix );
                return [
                    'date'  => $dt->format( 'd.m.Y – H:i' ) . ' Uhr',
                    'type'  => $type,
                    'ts'    => $unix,
                ];
            }
        }
        return null;
    }
}
