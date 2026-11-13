<?php
/**
 * Module comparison: two daily series from any modules on one continuous
 * time axis — [naws_compare].
 *
 * The arithmetic is pure (catalogue, parsing the shortcode's series,
 * the period, merging and thinning, the axes) and tested on hand-built
 * rows. Only the WordPress layer further down touches the database.
 *
 * @package NAWS
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Compare {

    /** Beyond this many days a series is thinned to ISO weeks. */
    const MAX_DAYS = 3650;

    /** Seconds per day (no WordPress constant in the pure part). */
    const DAY = 86400;

    /** Bounds of the months attribute. */
    const MONTHS_MAX = 120;

    /**
     * Every series that can be compared, grouped by the module reference
     * it belongs to. 'in' stands for every indoor module (in-<slug>).
     *
     * row   station — the base station's daily row (outdoor, rain, wind and
     *                 the base's own indoor values live there)
     *       own     — the module's own daily row (NAModule4)
     * col   column of the daily summary
     * param what NAWS_Helpers::get_unit()/format_value() convert with
     * chart line or bar
     * label naws_label() key of the quantity
     */
    public static function series_catalogue(): array {
        $e = static function ( string $type, string $row, string $col, string $param, string $label, string $chart = 'line' ): array {
            return [ 'type' => $type, 'row' => $row, 'col' => $col, 'param' => $param, 'chart' => $chart, 'label' => $label ];
        };
        return [
            'outdoor' => [
                'temp'     => $e( 'NAModule1', 'station', 'temp_avg', 'Temperature', 'cmp_q_temp' ),
                'temp_min' => $e( 'NAModule1', 'station', 'temp_min', 'Temperature', 'cmp_q_temp_min' ),
                'temp_max' => $e( 'NAModule1', 'station', 'temp_max', 'Temperature', 'cmp_q_temp_max' ),
                'humidity' => $e( 'NAModule1', 'station', 'humidity_avg', 'Humidity', 'cmp_q_humidity' ),
            ],
            'indoor'  => [
                'temp'     => $e( 'NAMain', 'station', 'indoor_temp_avg', 'Temperature', 'cmp_q_temp' ),
                'humidity' => $e( 'NAMain', 'station', 'indoor_humidity_avg', 'Humidity', 'cmp_q_humidity' ),
                'co2'      => $e( 'NAMain', 'station', 'co2_avg', 'CO2', 'cmp_q_co2' ),
                'noise'    => $e( 'NAMain', 'station', 'noise_avg', 'Noise', 'cmp_q_noise' ),
                'pressure' => $e( 'NAMain', 'station', 'pressure_avg', 'Pressure', 'cmp_q_pressure' ),
            ],
            'rain'    => [
                'rain' => $e( 'NAModule3', 'station', 'rain_sum', 'Rain', 'cmp_q_rain', 'bar' ),
            ],
            'wind'    => [
                'wind' => $e( 'NAModule2', 'station', 'wind_avg', 'WindStrength', 'cmp_q_wind' ),
                'gust' => $e( 'NAModule2', 'station', 'gust_max', 'GustStrength', 'cmp_q_gust' ),
            ],
            'in'      => [
                'temp'     => $e( 'NAModule4', 'own', 'indoor_temp_avg', 'Temperature', 'cmp_q_temp' ),
                'humidity' => $e( 'NAModule4', 'own', 'indoor_humidity_avg', 'Humidity', 'cmp_q_humidity' ),
                'co2'      => $e( 'NAModule4', 'own', 'co2_avg', 'CO2', 'cmp_q_co2' ),
            ],
        ];
    }

    /** The catalogue group a module reference belongs to, or null. */
    public static function group_of( string $ref ): ?string {
        if ( preg_match( '/^in-[a-z0-9]+$/', $ref ) ) {
            return 'in';
        }
        return isset( self::series_catalogue()[ $ref ] ) && $ref !== 'in' ? $ref : null;
    }

    /**
     * One series of the shortcode, "module:quantity". Checked against the
     * catalogue only — whether the module exists is the WordPress layer's
     * question.
     *
     * @return array{ok: bool, ref: string, qty: string, error: string}
     */
    public static function parse_series( string $spec ): array {
        $spec = strtolower( trim( $spec ) );
        $out  = [ 'ok' => false, 'ref' => '', 'qty' => '', 'error' => 'syntax' ];
        if ( substr_count( $spec, ':' ) !== 1 ) {
            return $out;
        }
        [ $ref, $qty ] = array_map( 'trim', explode( ':', $spec ) );
        if ( $ref === '' || $qty === '' ) {
            return $out;
        }
        $out['ref'] = $ref;
        $out['qty'] = $qty;
        $group      = self::group_of( $ref );
        if ( $group === null ) {
            $out['error'] = 'unknown_module';
            return $out;
        }
        if ( ! isset( self::series_catalogue()[ $group ][ $qty ] ) ) {
            $out['error'] = 'unknown_quantity';
            return $out;
        }
        $out['ok']    = true;
        $out['error'] = '';
        return $out;
    }

    /**
     * The period to draw, as Y-m-d.
     *
     * from/to take JJJJ-MM (whole month) or JJJJ-MM-TT; months=N reaches N
     * calendar months back from to's month and yields to an explicit from.
     * Without from/to the period runs from the first day with data to
     * yesterday, and auto_* tells build() to trim it to the data.
     *
     * @param array       $atts      from, to, months (strings).
     * @param string|null $first_day First day of the daily summary, null without data.
     * @param string      $yesterday Yesterday in the site's timezone.
     */
    public static function period( array $atts, ?string $first_day, string $yesterday ): array {
        $from = self::date_of( (string) ( $atts['from'] ?? '' ), false );
        $to   = self::date_of( (string) ( $atts['to'] ?? '' ), true );

        $auto_to = $to === null;
        if ( $to === null || $to > $yesterday ) {
            $to = $yesterday;
        }

        $months = (int) ( $atts['months'] ?? 0 );
        if ( $from === null && $months > 0 ) {
            $months = min( self::MONTHS_MAX, $months );
            $from   = gmdate( 'Y-m-01', strtotime( substr( $to, 0, 7 ) . '-01 -' . ( $months - 1 ) . ' months' ) );
        }

        $auto_from = $from === null;
        if ( $from === null ) {
            $from = $first_day;
        }

        if ( $from !== null && $from > $to ) {
            [ $from, $to ] = [ $to, $from ];
            // A swapped explicit pair keeps the whole of both months.
            if ( preg_match( '/^\d{4}-\d{2}$/', (string) ( $atts['from'] ?? '' ) ) ) {
                $to = gmdate( 'Y-m-t', strtotime( $to ) );
            }
            if ( preg_match( '/^\d{4}-\d{2}$/', (string) ( $atts['to'] ?? '' ) ) ) {
                $from = substr( $from, 0, 8 ) . '01';
            }
        }

        return [ 'from' => $from, 'to' => $to, 'auto_from' => $auto_from, 'auto_to' => $auto_to ];
    }

    /**
     * The series on one shared day axis.
     *
     * Every calendar day from..to is a point; a day without a value is null,
     * so the chart breaks the line there instead of drawing across a gap.
     * auto_from/auto_to trim the axis to the first/last day on which any
     * series has a value. Beyond MAX_DAYS the series are thinned to ISO
     * weeks (Monday as the date): lines the mean of the week's values,
     * bars their sum, a week without values null.
     *
     * @param array $series List of ['values' => ['Y-m-d' => ?float], 'chart' => 'line'|'bar'].
     * @return array{dates: string[], values: array, weekly: bool}
     */
    public static function build( array $series, string $from, string $to, bool $auto_from, bool $auto_to ): array {
        $first = null;
        $last  = null;
        foreach ( $series as $s ) {
            foreach ( $s['values'] as $day => $v ) {
                if ( $v === null || $day < $from || $day > $to ) {
                    continue;
                }
                $first = ( $first === null || $day < $first ) ? $day : $first;
                $last  = ( $last === null || $day > $last ) ? $day : $last;
            }
        }
        if ( $first === null ) {
            return [ 'dates' => [], 'values' => array_fill( 0, count( $series ), [] ), 'weekly' => false ];
        }
        if ( $auto_from ) {
            $from = $first;
        }
        if ( $auto_to ) {
            $to = $last;
        }

        $dates = [];
        for ( $t = strtotime( $from . ' 12:00:00 UTC' ), $end = strtotime( $to . ' 12:00:00 UTC' ); $t <= $end; $t += self::DAY ) {
            $dates[] = gmdate( 'Y-m-d', $t );
        }

        if ( count( $dates ) <= self::MAX_DAYS ) {
            $values = [];
            foreach ( $series as $s ) {
                $row = [];
                foreach ( $dates as $day ) {
                    $row[] = isset( $s['values'][ $day ] ) ? (float) $s['values'][ $day ] : null;
                }
                $values[] = $row;
            }
            return [ 'dates' => $dates, 'values' => $values, 'weekly' => false ];
        }

        // ISO weeks, keyed by their Monday.
        $weeks = [];
        foreach ( $dates as $day ) {
            $t      = strtotime( $day . ' 12:00:00 UTC' );
            $monday = gmdate( 'Y-m-d', $t - ( (int) gmdate( 'N', $t ) - 1 ) * self::DAY );
            $weeks[ $monday ][] = $day;
        }
        $values = [];
        foreach ( $series as $s ) {
            $row = [];
            foreach ( $weeks as $days ) {
                $vals = [];
                foreach ( $days as $day ) {
                    if ( isset( $s['values'][ $day ] ) ) {
                        $vals[] = (float) $s['values'][ $day ];
                    }
                }
                if ( $vals === [] ) {
                    $row[] = null;
                } elseif ( ( $s['chart'] ?? 'line' ) === 'bar' ) {
                    $row[] = round( array_sum( $vals ), 3 );
                } else {
                    $row[] = round( array_sum( $vals ) / count( $vals ), 3 );
                }
            }
            $values[] = $row;
        }
        return [ 'dates' => array_keys( $weeks ), 'values' => $values, 'weekly' => true ];
    }

    /**
     * Which axis each series draws on: one shared axis for one unit, a
     * second one on the right for a second unit.
     */
    public static function axes( string $unit_a, ?string $unit_b ): array {
        if ( $unit_b === null ) {
            return [ 'a' => 'y', 'b' => null, 'right' => false ];
        }
        if ( $unit_a === $unit_b ) {
            return [ 'a' => 'y', 'b' => 'y', 'right' => false ];
        }
        return [ 'a' => 'y', 'b' => 'y1', 'right' => true ];
    }

    /** JJJJ-MM or JJJJ-MM-TT to Y-m-d; a month gives its first or last day. Invalid → null. */
    private static function date_of( string $value, bool $end ): ?string {
        $value = trim( $value );
        if ( preg_match( '/^(\d{4})-(\d{2})$/', $value, $m ) ) {
            if ( ! checkdate( (int) $m[2], 1, (int) $m[1] ) ) {
                return null;
            }
            return $end ? gmdate( 'Y-m-t', strtotime( $value . '-01' ) ) : $value . '-01';
        }
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : null;
        }
        return null;
    }
}
