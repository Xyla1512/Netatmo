<?php
/**
 * Month review: which months can be looked at, and what one month adds up to.
 *
 * The arithmetic is pure -- daily rows in, numbers out -- so it is tested on
 * hand-built months. Nothing here talks to WordPress.
 *
 * @package NAWS
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Month_Review {

    /** Trend thresholds: a change smaller than this reads as "unchanged". */
    const TREND_TEMP     = 0.5;  // Kelvin.
    const TREND_RAIN_PCT = 0.10; // Share of the comparison value ...
    const TREND_RAIN_MM  = 2.0;  // ... and millimetres; both must be exceeded.
    const TREND_GUST     = 5.0;  // km/h.

    /** Years of comparison by default, and the most a shortcode may ask for. */
    const YEARS_DEFAULT = 5;
    const YEARS_MAX     = 10;

    /**
     * Months with at least one row, ascending, as 'YYYY-MM'.
     *
     * The month of $today and later ones are left out: a month under way is
     * not yet a review.
     *
     * @param string $today 'Y-m-d'.
     */
    public static function months( array $rows, string $today ): array {
        $current = substr( $today, 0, 7 );
        $found   = [];
        foreach ( $rows as $row ) {
            $ym = substr( (string) ( $row['day_date'] ?? '' ), 0, 7 );
            if ( $ym !== '' && $ym < $current ) {
                $found[ $ym ] = true;
            }
        }
        $months = array_keys( $found );
        sort( $months, SORT_STRING );
        return $months;
    }

    /** The wanted month if it is listed, else the latest one; null for none. */
    public static function resolve_month( string $wanted, array $months ): ?string {
        if ( $months === [] ) {
            return null;
        }
        return in_array( $wanted, $months, true ) ? $wanted : (string) end( $months );
    }

    /**
     * The next listed month before and after $ym; gaps are skipped.
     *
     * @return array{prev:?string,next:?string}
     */
    public static function neighbours( string $ym, array $months ): array {
        $prev = null;
        $next = null;
        foreach ( $months as $m ) {
            if ( $m < $ym ) {
                $prev = $m;
            } elseif ( $m > $ym && $next === null ) {
                $next = $m;
            }
        }
        return [ 'prev' => $prev, 'next' => $next ];
    }

    /**
     * What one month adds up to.
     *
     * Extremes are [value, date] or null; a tie goes to the first day. The
     * counts are null when the month has no value for their column at all.
     * 'days' says how many days carry each column, 'complete' whether that
     * is enough for each figure: averages and extremes need MONTH_MIN_DAYS
     * days, sums and counts need every day of the month.
     *
     * @param string $ym 'YYYY-MM'.
     */
    public static function summary( array $rows, string $ym ): array {
        $length = (int) gmdate( 't', (int) strtotime( $ym . '-01 00:00:00 UTC' ) );

        $month = [];
        foreach ( $rows as $row ) {
            if ( substr( (string) ( $row['day_date'] ?? '' ), 0, 7 ) === $ym ) {
                $month[] = $row;
            }
        }

        $days = [];
        foreach ( [ 'temp_avg', 'temp_max', 'temp_min', 'rain_sum', 'gust_max' ] as $field ) {
            $days[ $field ] = 0;
            foreach ( $month as $row ) {
                if ( isset( $row[ $field ] ) ) {
                    $days[ $field ]++;
                }
            }
        }

        $mean = null;
        if ( $days['temp_avg'] > 0 ) {
            $sum = 0.0;
            foreach ( $month as $row ) {
                $sum += isset( $row['temp_avg'] ) ? (float) $row['temp_avg'] : 0.0;
            }
            $mean = $sum / $days['temp_avg'];
        }

        $rain = null;
        if ( $days['rain_sum'] > 0 ) {
            $rain = 0.0;
            foreach ( $month as $row ) {
                $rain += isset( $row['rain_sum'] ) ? (float) $row['rain_sum'] : 0.0;
            }
        }

        $frost  = NAWS_Calc::catalogue()['frost_days'];
        $summer = NAWS_Calc::catalogue()['summer_days'];

        $min_days = NAWS_Records::MONTH_MIN_DAYS;

        return [
            'ym'          => $ym,
            'length'      => $length,
            'mean'        => $mean,
            'max'         => self::extreme( $month, 'temp_max', 'max' ),
            'min'         => self::extreme( $month, 'temp_min', 'min' ),
            'rain'        => $rain,
            'rain_days'   => self::count( $month, 'rain_sum', '>=', NAWS_Records::RAIN_DAY_MM ),
            'wettest_day' => self::extreme( $month, 'rain_sum', 'max' ),
            'gust'        => self::extreme( $month, 'gust_max', 'max' ),
            'frost_days'  => self::count( $month, (string) $frost['field'], (string) $frost['op'], (float) $frost['threshold'] ),
            'summer_days' => self::count( $month, (string) $summer['field'], (string) $summer['op'], (float) $summer['threshold'] ),
            'days'        => [
                'temp_avg' => $days['temp_avg'],
                'temp_max' => $days['temp_max'],
                'temp_min' => $days['temp_min'],
                'rain_sum' => $days['rain_sum'],
                'gust_max' => $days['gust_max'],
            ],
            'complete'    => [
                'mean'        => $days['temp_avg'] >= $min_days,
                'max'         => $days['temp_max'] >= $min_days,
                'min'         => $days['temp_min'] >= $min_days,
                'rain'        => $days['rain_sum'] === $length,
                'rain_days'   => $days['rain_sum'] === $length,
                'wettest_day' => $days['rain_sum'] >= $min_days,
                'gust'        => $days['gust_max'] >= $min_days,
                'frost_days'  => $days['temp_min'] === $length,
                'summer_days' => $days['temp_max'] === $length,
            ],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Strict comparison, rows in date order: a tie goes to the earlier day. */
    private static function extreme( array $rows, string $field, string $dir ): ?array {
        $best = null;
        foreach ( $rows as $row ) {
            if ( ! isset( $row[ $field ] ) ) {
                continue;
            }
            $v = (float) $row[ $field ];
            if ( $best === null || ( $dir === 'max' ? $v > $best['value'] : $v < $best['value'] ) ) {
                $best = [ 'value' => $v, 'date' => (string) $row['day_date'] ];
            }
        }
        return $best;
    }

    /** Days matching field/op/limit; null when no day carries the field. */
    private static function count( array $rows, string $field, string $op, float $limit ): ?int {
        $carrying = 0;
        $hits     = 0;
        foreach ( $rows as $row ) {
            if ( ! isset( $row[ $field ] ) ) {
                continue;
            }
            $carrying++;
            $v = (float) $row[ $field ];
            switch ( $op ) {
                case '<':  $match = $v <  $limit; break;
                case '<=': $match = $v <= $limit; break;
                case '>':  $match = $v >  $limit; break;
                default:   $match = $v >= $limit;
            }
            if ( $match ) {
                $hits++;
            }
        }
        return $carrying === 0 ? null : $hits;
    }
}
