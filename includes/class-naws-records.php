<?php
/**
 * Records from the daily summary: the hottest day, the longest dry spell,
 * the wettest month, and what this calendar day looked like in earlier
 * years.
 *
 * The arithmetic is pure — daily rows in, numbers out — so it is tested on
 * a hand-built year. Only rows() and delta_parts() touch WordPress.
 *
 * @package NAWS
 * @since   1.9.11
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Records {

    /**
     * A month counts only with this many days that carry the column.
     * Without it the month the record began in — three days of March —
     * would win "coldest month" against every full one.
     */
    const MONTH_MIN_DAYS = 20;

    /** The WMO line for a day with precipitation, in millimetres. */
    const RAIN_DAY_MM = 0.1;

    /**
     * The fifteen records, in display order.
     *
     * kind    extreme — one row, field and direction
     *         month   — rows grouped by month, agg (avg|sum), then direction
     *         streak  — longest run of days matching field/op/threshold, or
     *                   the dayclass of the same name in NAWS_Calc
     * param   what NAWS_Helpers::get_unit()/format_value() convert with;
     *         'delta' for a temperature difference, null for a day count
     * label   the naws_label() key
     */
    public static function catalogue(): array {
        return [
            'hottest_day'        => [ 'kind' => 'extreme', 'field' => 'temp_max', 'dir' => 'max', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_hottest_day' ],
            'coldest_night'      => [ 'kind' => 'extreme', 'field' => 'temp_min', 'dir' => 'min', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_coldest_night' ],
            'warmest_night'      => [ 'kind' => 'extreme', 'field' => 'temp_min', 'dir' => 'max', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_warmest_night' ],
            'coldest_day'        => [ 'kind' => 'extreme', 'field' => 'temp_max', 'dir' => 'min', 'param' => 'Temperature',  'decimals' => 1, 'label' => 'rec_coldest_day' ],
            'widest_range'       => [ 'kind' => 'extreme', 'field' => 'range',    'dir' => 'max', 'param' => 'delta',        'decimals' => 1, 'label' => 'rec_widest_range' ],
            'warmest_month'      => [ 'kind' => 'month',   'field' => 'temp_avg', 'agg' => 'avg', 'dir' => 'max', 'param' => 'Temperature', 'decimals' => 1, 'label' => 'rec_warmest_month' ],
            'coldest_month'      => [ 'kind' => 'month',   'field' => 'temp_avg', 'agg' => 'avg', 'dir' => 'min', 'param' => 'Temperature', 'decimals' => 1, 'label' => 'rec_coldest_month' ],
            'wettest_day'        => [ 'kind' => 'extreme', 'field' => 'rain_sum', 'dir' => 'max', 'param' => 'Rain',         'decimals' => 1, 'label' => 'rec_wettest_day' ],
            'wettest_month'      => [ 'kind' => 'month',   'field' => 'rain_sum', 'agg' => 'sum', 'dir' => 'max', 'param' => 'Rain',        'decimals' => 1, 'label' => 'rec_wettest_month' ],
            'longest_dry_spell'  => [ 'kind' => 'streak',  'field' => 'rain_sum', 'op' => '<',  'threshold' => self::RAIN_DAY_MM, 'param' => null, 'decimals' => 0, 'label' => 'rec_longest_dry_spell' ],
            'longest_wet_spell'  => [ 'kind' => 'streak',  'field' => 'rain_sum', 'op' => '>=', 'threshold' => self::RAIN_DAY_MM, 'param' => null, 'decimals' => 0, 'label' => 'rec_longest_wet_spell' ],
            'strongest_gust'     => [ 'kind' => 'extreme', 'field' => 'gust_max', 'dir' => 'max', 'param' => 'GustStrength', 'decimals' => 1, 'label' => 'rec_strongest_gust' ],
            'longest_frost'      => [ 'kind' => 'streak',  'dayclass' => 'frost_days',  'param' => null, 'decimals' => 0, 'label' => 'rec_longest_frost' ],
            'longest_heat_wave'  => [ 'kind' => 'streak',  'dayclass' => 'hot_days',    'param' => null, 'decimals' => 0, 'label' => 'rec_longest_heat_wave' ],
            'longest_summer_run' => [ 'kind' => 'streak',  'dayclass' => 'summer_days', 'param' => null, 'decimals' => 0, 'label' => 'rec_longest_summer_run' ],
        ];
    }

    /**
     * One record over the given daily rows (ascending by day_date).
     *
     * @return array|null extreme: [value, date]; month: [value, month];
     *                    streak: [value (days), from, to]; null when no row
     *                    carries what the record needs — or for a key the
     *                    catalogue does not know.
     */
    public static function compute( array $rows, string $key ): ?array {
        $entry = self::catalogue()[ $key ] ?? null;
        if ( $entry === null ) {
            return null;
        }
        switch ( $entry['kind'] ) {
            case 'extreme':
                return self::extreme( $rows, $entry['field'], $entry['dir'] );
            case 'month':
                return self::month( $rows, $entry['field'], $entry['agg'], $entry['dir'] );
            case 'streak':
                return self::streak( $rows, self::matcher( $entry ), self::field_of( $entry ) );
        }
        return null;
    }

    /**
     * Every record that can be computed, without the ones that cannot.
     *
     * @param array $keys Subset in display order; empty = the whole catalogue.
     *                    Unknown keys are skipped, not reported: a typo in a
     *                    shortcode costs one tile, not the page.
     */
    public static function all( array $rows, array $keys = [] ): array {
        $wanted = $keys === [] ? array_keys( self::catalogue() ) : $keys;
        $out    = [];
        foreach ( $wanted as $key ) {
            $key    = (string) $key;
            $result = self::compute( $rows, $key );
            if ( $result !== null ) {
                $out[ $key ] = $result;
            }
        }
        return $out;
    }

    /**
     * This calendar day in earlier years, newest first.
     *
     * The running year is left out: its row for today is written at the end
     * of the day, and "in earlier years" is then also true as a heading.
     * Each row is marked where it holds the day's record — warmest maximum,
     * coldest minimum, wettest — with a strict comparison walked from the
     * earliest year, so a tie goes to the earlier year.
     *
     * @param string $month_day   'MM-DD'.
     * @param int    $before_year Years >= this one are excluded.
     */
    public static function on_this_day( array $rows, string $month_day, int $before_year ): array {
        $hits = [];
        foreach ( $rows as $row ) {
            $date = (string) ( $row['day_date'] ?? '' );
            if ( substr( $date, 5 ) !== $month_day ) {
                continue;
            }
            $year = (int) substr( $date, 0, 4 );
            if ( $year >= $before_year ) {
                continue;
            }
            $hits[] = [
                'year'     => $year,
                'day_date' => $date,
                'temp_min' => isset( $row['temp_min'] ) ? (float) $row['temp_min'] : null,
                'temp_max' => isset( $row['temp_max'] ) ? (float) $row['temp_max'] : null,
                'temp_avg' => isset( $row['temp_avg'] ) ? (float) $row['temp_avg'] : null,
                'rain_sum' => isset( $row['rain_sum'] ) ? (float) $row['rain_sum'] : null,
                'record'   => [ 'temp_max' => false, 'temp_min' => false, 'rain_sum' => false ],
            ];
        }
        usort( $hits, static fn( $a, $b ) => $a['year'] <=> $b['year'] ); // ascending for the tie rule
        foreach ( [ 'temp_max' => 'max', 'temp_min' => 'min', 'rain_sum' => 'max' ] as $field => $dir ) {
            $best = null;
            foreach ( $hits as $i => $hit ) {
                $v = $hit[ $field ];
                if ( $v === null ) {
                    continue;
                }
                if ( $best === null || ( $dir === 'max' ? $v > $hits[ $best ][ $field ] : $v < $hits[ $best ][ $field ] ) ) {
                    $best = $i;
                }
            }
            if ( $best !== null ) {
                $hits[ $best ]['record'][ $field ] = true;
            }
        }
        return array_reverse( $hits );
    }

    /**
     * A temperature difference in the site's unit.
     *
     * A difference converts with the factor alone: 10 K are 18 °F, not
     * 50 °F. NAWS_Helpers::format_value() would add the offset, which is
     * right for a temperature and wrong for a span.
     *
     * @return array{value:float,unit:string}
     */
    public static function delta_parts( float $kelvin ): array {
        $unit = get_option( 'naws_settings', [] )['temperature_unit'] ?? 'C';
        if ( $unit === 'F' ) {
            return [ 'value' => round( $kelvin * 1.8, 1 ), 'unit' => '°F' ];
        }
        return [ 'value' => round( $kelvin, 1 ), 'unit' => '°C' ];
    }

    /**
     * First day with data and the number of days that carry at least one
     * of the five columns — for the footer under the tiles.
     *
     * $rows must already be ascending by day_date, as get_daily_summaries()
     * returns them, for 'first' to actually be the first day.
     *
     * @return array{first:?string,days:int}
     */
    public static function coverage( array $rows ): array {
        $first = null;
        $days  = 0;
        foreach ( $rows as $row ) {
            foreach ( [ 'temp_min', 'temp_max', 'temp_avg', 'rain_sum', 'gust_max' ] as $field ) {
                if ( isset( $row[ $field ] ) ) {
                    $days++;
                    $first = $first ?? (string) $row['day_date'];
                    break;
                }
            }
        }
        return [ 'first' => $first, 'days' => $days ];
    }

    /**
     * The station's daily rows for the requested period — the only place
     * this class talks to WordPress.
     *
     * Records default to the whole record: `period` is set to 'all' before
     * NAWS_Calc::period_range() sees it, which keeps that function
     * untouched. year="2025" still narrows to one year, as it does for
     * [naws_calc].
     */
    public static function rows( array $atts ): array {
        if ( ! isset( $atts['period'] ) || $atts['period'] === '' ) {
            $atts['period'] = 'all';
        }
        $station = NAWS_Calc::station_row_id( $atts );
        if ( $station === null ) {
            return [];
        }
        $range = NAWS_Calc::period_range( $atts );
        return NAWS_Database::get_daily_summaries( [
            'module_id' => $station,
            'date_from' => $range['from'],
            'date_to'   => $range['to'],
            'fields'    => [ 'temp_min', 'temp_max', 'temp_avg', 'rain_sum', 'gust_max' ],
            'group_by'  => 'day',
        ] );
    }

    // ── The three kinds ──────────────────────────────────────────────────

    /** Strict comparison, rows in date order: a tie goes to the earlier day. */
    private static function extreme( array $rows, string $field, string $dir ): ?array {
        $best = null;
        foreach ( $rows as $row ) {
            $v = self::value_of( $row, $field );
            if ( $v === null ) {
                continue;
            }
            if ( $best === null || ( $dir === 'max' ? $v > $best['value'] : $v < $best['value'] ) ) {
                $best = [ 'value' => $v, 'date' => (string) $row['day_date'] ];
            }
        }
        return $best;
    }

    /** Months with fewer than MONTH_MIN_DAYS carrying days do not compete. */
    private static function month( array $rows, string $field, string $agg, string $dir ): ?array {
        $by_month = [];
        foreach ( $rows as $row ) {
            $v = self::value_of( $row, $field );
            if ( $v === null ) {
                continue;
            }
            $by_month[ substr( (string) $row['day_date'], 0, 7 ) ][] = $v;
        }
        ksort( $by_month );
        $best = null;
        foreach ( $by_month as $month => $values ) {
            if ( count( $values ) < self::MONTH_MIN_DAYS ) {
                continue;
            }
            $v = $agg === 'sum' ? array_sum( $values ) : array_sum( $values ) / count( $values );
            if ( $best === null || ( $dir === 'max' ? $v > $best['value'] : $v < $best['value'] ) ) {
                $best = [ 'value' => (float) $v, 'month' => (string) $month ];
            }
        }
        return $best;
    }

    /**
     * Rows that do not carry the field are dropped first, so a gap in the
     * column is a gap in the calendar — and breaks the run, as it should.
     */
    private static function streak( array $rows, callable $matches, string $field ): ?array {
        $carrying = array_values( array_filter( $rows, static function ( $row ) use ( $field ) {
            return self::value_of( $row, $field ) !== null;
        } ) );
        $run = NAWS_Climate::longest_run( $carrying, $matches );
        if ( $run === null ) {
            return null;
        }
        return [ 'value' => $run['length'], 'from' => $run['from'], 'to' => $run['to'] ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** 'range' is the daily span and needs both temperatures. */
    private static function value_of( array $row, string $field ): ?float {
        if ( $field === 'range' ) {
            if ( ! isset( $row['temp_max'], $row['temp_min'] ) ) {
                return null;
            }
            return (float) $row['temp_max'] - (float) $row['temp_min'];
        }
        return isset( $row[ $field ] ) ? (float) $row[ $field ] : null;
    }

    /** The field a streak looks at, from the entry or its dayclass. */
    private static function field_of( array $entry ): string {
        if ( isset( $entry['dayclass'] ) ) {
            return (string) NAWS_Calc::catalogue()[ $entry['dayclass'] ]['field'];
        }
        return (string) $entry['field'];
    }

    /**
     * The matcher for a streak. A dayclass reference reads field, operator
     * and threshold from NAWS_Calc, so "heat wave" and "hot days" can never
     * mean different temperatures.
     */
    private static function matcher( array $entry ): callable {
        if ( isset( $entry['dayclass'] ) ) {
            $entry = NAWS_Calc::catalogue()[ $entry['dayclass'] ];
        }
        $field = (string) $entry['field'];
        $op    = (string) $entry['op'];
        $limit = (float) $entry['threshold'];
        return static function ( array $row ) use ( $field, $op, $limit ): bool {
            $v = (float) $row[ $field ];
            switch ( $op ) {
                case '<':  return $v <  $limit;
                case '<=': return $v <= $limit;
                case '>':  return $v >  $limit;
                default:   return $v >= $limit;
            }
        };
    }
}
