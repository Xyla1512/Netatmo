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

    /** Directions in which a month counts as a record, per tile figure. */
    const RECORD_DIRS = [
        'mean' => [ 'high', 'low' ],
        'max'  => [ 'high' ],
        'min'  => [ 'low' ],
        'rain' => [ 'high', 'low' ],
        'gust' => [ 'high' ],
    ];

    /**
     * One month against the same month of earlier years.
     *
     * Only rows up to the end of $ym count ("since records began" means up
     * to that month). 'previous' lists at most $years earlier years, newest
     * first, that have rows for the month -- also incomplete ones (the template
     * skips those per figure). 'record' is high|low when the figure beats
     * every complete earlier year (all of them, whatever $years is) strictly.
     * 'trend' is up|down against the mean of the complete years shown, and
     * only where there is no record. $years = 0 switches all of it off.
     *
     * @param string $ym 'YYYY-MM'.
     */
    public static function compare( array $rows, string $ym, int $years ): array {
        $years = max( 0, $years );
        $year  = (int) substr( $ym, 0, 4 );
        $name  = substr( $ym, 5, 2 );

        $earlier = [];
        foreach ( $rows as $row ) {
            $date = (string) ( $row['day_date'] ?? '' );
            if ( substr( $date, 5, 2 ) !== $name ) {
                continue;
            }
            $y = (int) substr( $date, 0, 4 );
            if ( $y < $year ) {
                $earlier[ $y ] = true;
            }
        }
        krsort( $earlier, SORT_NUMERIC );

        $all = [];
        foreach ( array_keys( $earlier ) as $y ) {
            $all[ sprintf( '%04d', $y ) ] = self::summary( $rows, sprintf( '%04d-%s', $y, $name ) );
        }

        $current  = self::summary( $rows, $ym );
        $previous = $years > 0 ? array_slice( $all, 0, $years, true ) : [];

        $record = [];
        $trend  = [];
        foreach ( self::RECORD_DIRS as $kpi => $dirs ) {
            $record[ $kpi ] = null;
            $trend[ $kpi ]  = null;
            $now            = self::figure( $current, $kpi );
            if ( $years === 0 || $now === null || ! $current['complete'][ $kpi ] ) {
                continue;
            }

            $others = self::complete_figures( $all, $kpi );
            if ( $others !== [] ) {
                if ( in_array( 'high', $dirs, true ) && round( $now - max( $others ), 6 ) > 0 ) {
                    $record[ $kpi ] = 'high';
                } elseif ( in_array( 'low', $dirs, true ) && round( $now - min( $others ), 6 ) < 0 ) {
                    $record[ $kpi ] = 'low';
                }
            }
            if ( $record[ $kpi ] !== null ) {
                continue;
            }

            $shown = self::complete_figures( $previous, $kpi );
            if ( $shown === [] ) {
                continue;
            }
            $avg   = array_sum( $shown ) / count( $shown );
            $delta = round( $now - $avg, 6 );
            if ( abs( $delta ) >= round( self::trend_limit( $kpi, $avg ), 6 ) ) {
                $trend[ $kpi ] = $delta > 0 ? 'up' : 'down';
            }
        }

        return [ 'current' => $current, 'previous' => $previous, 'record' => $record, 'trend' => $trend ];
    }

    /**
     * Whether the month beats the station's whole history before it.
     *
     * Strictly: a tie is no record. Without rows before the month nothing
     * can be a record.
     *
     * @param string $ym 'YYYY-MM'.
     * @return array{hottest_day:bool,coldest_night:bool,strongest_gust:bool,wettest_day:bool}
     */
    public static function station_records( array $rows, string $ym ): array {
        $out    = [ 'hottest_day' => false, 'coldest_night' => false, 'strongest_gust' => false, 'wettest_day' => false ];
        $before = [];
        foreach ( $rows as $row ) {
            if ( (string) ( $row['day_date'] ?? '' ) < $ym . '-01' ) {
                $before[] = $row;
            }
        }
        if ( $before === [] ) {
            return $out;
        }

        $month = self::summary( $rows, $ym );
        $map   = [
            'hottest_day'    => [ 'max', 'high' ],
            'coldest_night'  => [ 'min', 'low' ],
            'strongest_gust' => [ 'gust', 'high' ],
            'wettest_day'    => [ 'wettest_day', 'high' ],
        ];
        foreach ( $map as $key => $spec ) {
            $now  = $month[ $spec[0] ];
            $best = NAWS_Records::compute( $before, $key );
            if ( $now === null || $best === null ) {
                continue;
            }
            $diff        = round( (float) $now['value'] - (float) $best['value'], 6 );
            $out[ $key ] = $spec[1] === 'high' ? $diff > 0 : $diff < 0;
        }
        return $out;
    }

    /**
     * The one headline sentence: the first record that applies, else neutral.
     *
     * @return array{key:string,args:array}
     */
    public static function headline( array $cmp ): array {
        $rec   = $cmp['record'];
        $rules = [
            [ 'rain', 'high', 'mr_head_wettest' ],
            [ 'rain', 'low', 'mr_head_driest' ],
            [ 'mean', 'high', 'mr_head_warmest' ],
            [ 'mean', 'low', 'mr_head_coldest' ],
            [ 'max', 'high', 'mr_head_hottest_day' ],
            [ 'min', 'low', 'mr_head_coldest_night' ],
            [ 'gust', 'high', 'mr_head_gust' ],
        ];
        $key = 'mr_head_neutral';
        foreach ( $rules as $rule ) {
            if ( ( $rec[ $rule[0] ] ?? null ) === $rule[1] ) {
                $key = $rule[2];
                break;
            }
        }
        return [ 'key' => $key, 'args' => [ 'month' => (string) $cmp['current']['ym'] ] ];
    }

    /**
     * Up to three supporting sentences, in a fixed order: frost days, summer
     * days, wettest day, the first station record that applies.
     *
     * @param array $station Result of station_records().
     * @return array<int,array{key:string,args:array}>
     */
    public static function sentences( array $cmp, array $station ): array {
        $cur = $cmp['current'];
        $out = [];

        foreach ( [ 'frost_days' => 'mr_frost', 'summer_days' => 'mr_summer' ] as $field => $label ) {
            $n = $cur[ $field ];
            if ( $n === null || $n <= 0 || ! $cur['complete'][ $field ] ) {
                continue;
            }
            $prev      = null;
            $prev_year = null;
            foreach ( $cmp['previous'] as $y => $summary ) {
                if ( $summary['complete'][ $field ] && $summary[ $field ] !== null ) {
                    $prev      = (int) $summary[ $field ];
                    $prev_year = (string) $y;
                    break;
                }
            }
            $out[] = [ 'key' => $label, 'args' => [ 'n' => (int) $n, 'prev' => $prev, 'prev_year' => $prev_year ] ];
        }

        $wet = $cur['wettest_day'];
        if ( $wet !== null && $wet['value'] >= 1.0 ) {
            $out[] = [ 'key' => 'mr_wettest_day', 'args' => [ 'date' => $wet['date'], 'value' => $wet['value'] ] ];
        }

        $fields = [ 'hottest_day' => 'max', 'coldest_night' => 'min', 'strongest_gust' => 'gust', 'wettest_day' => 'wettest_day' ];
        foreach ( $fields as $key => $field ) {
            if ( ! empty( $station[ $key ] ) && $cur[ $field ] !== null ) {
                $out[] = [ 'key' => 'mr_station_record_' . $key, 'args' => [ 'date' => $cur[ $field ]['date'], 'value' => $cur[ $field ]['value'] ] ];
                break;
            }
        }

        return array_slice( $out, 0, 3 );
    }


    // ── Output ───────────────────────────────────────────────────────────

    /** The address parameter's format, also for a fixed month="" attribute. */
    const MONTH_PATTERN = '/^\d{4}-(0[1-9]|1[0-2])$/';

    /** $value if it is a valid 'YYYY-MM', else null. */
    public static function valid_month( $value ): ?string {
        $value = is_string( $value ) ? trim( $value ) : '';
        return preg_match( self::MONTH_PATTERN, $value ) === 1 ? $value : null;
    }

    /**
     * The station's daily rows, the whole record. The only place the
     * arithmetic above meets WordPress.
     */
    public static function rows( array $atts ): array {
        return NAWS_Records::rows( [ 'period' => 'all' ] );
    }

    /**
     * The review as HTML.
     *
     * @param array       $atts  [ 'years' => int, 'title' => string ], already cleaned.
     * @param string|null $month Wanted 'YYYY-MM'; null or unknown means the latest finished month.
     * @param bool        $nav   Show the paging bar (and the hook for the script).
     */
    public static function render( array $atts, ?string $month, bool $nav ): string {
        $rows = self::rows( $atts );
        if ( $rows === [] ) {
            return NAWS_Records::notice( 'naws_month_review', NAWS_Records::empty_reason( [ 'period' => 'all' ] ) );
        }

        $months = self::months( $rows, wp_date( 'Y-m-d' ) );
        $ym     = self::resolve_month( (string) $month, $months );
        if ( $ym === null ) {
            return NAWS_Records::notice( 'naws_month_review', 'no_complete_month' );
        }

        $years      = max( 0, min( self::YEARS_MAX, (int) ( $atts['years'] ?? self::YEARS_DEFAULT ) ) );
        $title      = (string) ( $atts['title'] ?? '' );
        $cmp        = self::compare( $rows, $ym, $years );
        $headline   = self::headline( $cmp );
        $station    = $years > 0 ? self::station_records( $rows, $ym ) : [];
        $sentences  = self::sentences( $cmp, $station );
        $neighbours = self::neighbours( $ym, $months );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/month-review.php';
        return (string) ob_get_clean();
    }
    /** The number a tile compares: the scalar or an extreme's value. */
    public static function figure( array $summary, string $kpi ): ?float {
        $v = $summary[ $kpi ];
        if ( is_array( $v ) ) {
            $v = $v['value'];
        }
        return $v === null ? null : (float) $v;
    }

    /** The figure of every summary that is complete for it. */
    private static function complete_figures( array $summaries, string $kpi ): array {
        $out = [];
        foreach ( $summaries as $summary ) {
            $v = self::figure( $summary, $kpi );
            if ( $v !== null && $summary['complete'][ $kpi ] ) {
                $out[] = $v;
            }
        }
        return $out;
    }

    /** How far a figure must move from the comparison value to count as a trend. */
    private static function trend_limit( string $kpi, float $avg ): float {
        switch ( $kpi ) {
            case 'rain':
                return max( self::TREND_RAIN_MM, self::TREND_RAIN_PCT * $avg );
            case 'gust':
                return self::TREND_GUST;
            default:
                return self::TREND_TEMP;
        }
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
