<?php
/**
 * Climate indices over daily summary rows.
 *
 * Every function here is pure: it takes finished rows and returns a number.
 * No options, no database, no clock — which is what lets
 * tests/test-climate-indices.php run without a WordPress bootstrap, and
 * what lets a reviewer check the arithmetic against a textbook instead of
 * against a fixture.
 *
 * Rows arrive sorted ascending by day_date, shaped:
 *   [ 'day_date' => 'Y-m-d', 'temp_min' => ?float, 'temp_max' => ?float, 'temp_avg' => ?float ]
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Climate {

    /** Grassland temperature sum: the threshold that marks the start of the growing season. */
    const GRASSLAND_THRESHOLD = 200.0;

    /** The index refuses to compute below two complete years of months. */
    const SPI_MIN_MONTHS = 24;

    /** And below a dozen windows, which is the arithmetic floor for a fit. */
    const SPI_MIN_WINDOWS = 12;

    /**
     * How many days match.
     */
    public static function count_days( array $rows, callable $matches ): int {
        $n = 0;
        foreach ( $rows as $row ) {
            if ( $matches( $row ) ) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Longest run of consecutive matching days.
     *
     * A missing calendar day breaks the run. That is the cautious reading:
     * nothing is known about a day nobody measured, so claiming it continued
     * a frost period would be an invention. Two frost days either side of a
     * data gap are two runs of two, not one run of four.
     */
    public static function max_streak( array $rows, callable $matches ): int {
        $run = self::longest_run( $rows, $matches );
        return $run === null ? 0 : $run['length'];
    }

    /**
     * The longest run, with its dates.
     *
     * Same walk as max_streak() used to do on its own — one loop, two
     * answers, so the number on a [naws_calc] and the dates on a record can
     * never disagree. A tie goes to the earlier run: the comparison is
     * strict, and the rows are walked in date order.
     *
     * @return array{length:int,from:string,to:string}|null Null when no day matches.
     */
    public static function longest_run( array $rows, callable $matches ): ?array {
        $best    = null;
        $current = 0;
        $start   = null;
        $prev    = null;
        foreach ( $rows as $row ) {
            $date = (string) ( $row['day_date'] ?? '' );
            if ( ! $matches( $row ) ) {
                $current = 0;
                $prev    = $date;
                continue;
            }
            if ( $current > 0 && $prev !== null && self::is_next_day( $prev, $date ) ) {
                $current++;
            } else {
                $current = 1;
                $start   = $date;
            }
            if ( $best === null || $current > $best['length'] ) {
                $best = [ 'length' => $current, 'from' => $start, 'to' => $date ];
            }
            $prev = $date;
        }
        return $best;
    }

    /**
     * Run of matching days ending on the last row.
     *
     * Counts backwards from the end of the range, so it answers "how many in
     * a row right now" for an open range and "how many in a row at the end"
     * for a closed one.
     */
    public static function current_streak( array $rows, callable $matches ): int {
        $n    = 0;
        $next = null;

        foreach ( array_reverse( $rows ) as $row ) {
            $date = $row['day_date'] ?? '';
            if ( ! $matches( $row ) ) {
                break;
            }
            if ( $next !== null && ! self::is_next_day( $date, $next ) ) {
                break;
            }
            $n++;
            $next = $date;
        }

        return $n;
    }

    /**
     * Heating or cooling degree days, in Kelvin-days.
     *
     * The two directions are not symmetric, and that is the standard, not an
     * oversight. Heating counts days below the heating threshold and sums the
     * distance from the ROOM temperature (20 °C), because that is the gap the
     * heating has to close. Cooling counts days above the cooling threshold
     * and sums the distance from that same THRESHOLD.
     *
     * @param float  $threshold Heating or cooling limit temperature.
     * @param float  $reference Room temperature; used by 'heating' only.
     * @param string $direction 'cooling' picks the cooling reading; ANY other
     *                          value, including a misspelt one, is treated as
     *                          heating. Deliberate: both callers pass a
     *                          literal, and a fourth branch that returned 0.0
     *                          for an unknown direction would answer a typo
     *                          with a plausible number instead of an obvious
     *                          one. tests/test-climate-indices.php pins it.
     */
    public static function degree_days( array $rows, float $threshold, float $reference, string $direction ): float {
        $cooling = ( $direction === 'cooling' );
        $sum     = 0.0;
        foreach ( $rows as $row ) {
            $avg = $row['temp_avg'] ?? null;
            if ( $avg === null ) {
                continue;
            }
            $avg = (float) $avg;
            if ( $cooling ) {
                if ( $avg > $threshold ) {
                    $sum += $avg - $threshold;
                }
                continue;
            }
            if ( $avg < $threshold ) {
                $sum += $reference - $avg;
            }
        }
        return $sum;
    }

    /**
     * Growing degree days (simple average method).
     *
     * WGT = (min(Tmax, cap) + Tmin) / 2 - base, negative contributions clipped
     * to zero. Base and cap are crop-dependent — 10 °C and 30 °C are the usual
     * pair, but the caller decides.
     */
    public static function growing_degree_days( array $rows, float $base, float $cap ): float {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $min = $row['temp_min'] ?? null;
            $max = $row['temp_max'] ?? null;
            if ( $min === null || $max === null ) {
                continue;
            }
            $mean = ( min( (float) $max, $cap ) + (float) $min ) / 2.0;
            $sum += max( 0.0, $mean - $base );
        }
        return $sum;
    }

    /**
     * Grassland temperature sum.
     *
     * Sums daily means above 0 °C from the first of January, weighting
     * January by 0.5 and February by 0.75 — early warmth is worth less to
     * grassland than the same warmth in spring.
     */
    public static function grassland_sum( array $rows ): float {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $sum += self::grassland_contribution( $row );
        }
        return $sum;
    }

    /**
     * The day the grassland sum first passed 200 °C, or null if it has not.
     *
     * That crossing is the point of the index: it marks the sustained start
     * of the growing season. A sum below it is not a broken value, only a
     * season that has not started.
     */
    public static function grassland_start( array $rows ): ?string {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $sum += self::grassland_contribution( $row );
            if ( $sum > self::GRASSLAND_THRESHOLD ) {
                return isset( $row['day_date'] ) ? (string) $row['day_date'] : null;
            }
        }
        return null;
    }

    /**
     * SPI: how the newest window compares with every window before it.
     *
     * The index fits a gamma distribution to all windows of the same length
     * the record holds, then asks where the newest one falls, expressed as a
     * standard normal deviate: 0 is the median, -1.5 dry, +1.5 wet.
     *
     * Two departures from the textbook, both forced by what a single station
     * can offer:
     *
     * 1. The windows are POOLED across the calendar instead of grouped by
     *    their end month. The classic index compares a June-August sum only
     *    with other June-August sums, which needs decades before any month
     *    has a sample worth fitting. Pooling buys a usable index from a few
     *    years at the price of seasonality: where the year has a marked dry
     *    season, a dry-season window is measured against the whole year's
     *    spread and reads lower than it deserves.
     * 2. Thirty years is the customary reference length. This refuses below
     *    two complete years and below a dozen windows — the arithmetic floor
     *    for a fit, not a statistical blessing. Everything in between is a
     *    tendency, and the documentation page says so with the
     *    installation's own numbers.
     *
     * @param array<string,float> $monthly_sums 'Y-m' => sum, complete months only.
     * @param int                 $months       Window length in months.
     * @return float|null  null when the record cannot carry the index.
     */
    public static function spi( array $monthly_sums, int $months ): ?float {
        if ( $months < 1 ) {
            return null;
        }
        ksort( $monthly_sums );
        $keys  = array_keys( $monthly_sums );
        $count = count( $keys );
        if ( $count < self::SPI_MIN_MONTHS || $count < $months ) {
            return null;
        }

        // Only windows whose months run consecutively — a missing month is a
        // hole in the sum, not a shorter window.
        $windows = [];
        for ( $end = $months - 1; $end < $count; $end++ ) {
            $start = $end - $months + 1;
            $sum   = 0.0;
            $whole = true;
            for ( $i = $start; $i <= $end; $i++ ) {
                if ( $i > $start && ! self::is_next_month( $keys[ $i - 1 ], $keys[ $i ] ) ) {
                    $whole = false;
                    break;
                }
                $sum += (float) $monthly_sums[ $keys[ $i ] ];
            }
            if ( $whole ) {
                $windows[ $keys[ $end ] ] = $sum;
            }
        }

        // The value being classified is the newest window. If the newest
        // months have a hole, there is nothing to classify.
        $newest = $keys[ $count - 1 ];
        if ( ! isset( $windows[ $newest ] ) || count( $windows ) < self::SPI_MIN_WINDOWS ) {
            return null;
        }

        return self::spi_of( array_values( $windows ), $windows[ $newest ] );
    }

    /**
     * The index for one value against a sample of windows.
     *
     * Dry windows are why this is not a plain gamma fit: the distribution
     * has no mass at zero, so the zeroes are held out as their own
     * probability q and the fit runs on the wet windows alone, after Thom's
     * approximate maximum likelihood. H(x) = q + (1-q)G(x) is the mixed
     * distribution the deviate is finally taken from.
     */
    private static function spi_of( array $sample, float $value ): ?float {
        $wet   = [];
        $zeros = 0;
        foreach ( $sample as $v ) {
            if ( (float) $v > 0.0 ) {
                $wet[] = (float) $v;
            } else {
                $zeros++;
            }
        }
        $n_wet = count( $wet );
        if ( $n_wet < 2 ) {
            return null;
        }

        $mean     = array_sum( $wet ) / $n_wet;
        $log_mean = 0.0;
        foreach ( $wet as $v ) {
            $log_mean += log( $v );
        }
        $log_mean /= $n_wet;

        // Thom's statistic. It is zero when every wet window is identical,
        // and the estimator divides by it.
        $a = log( $mean ) - $log_mean;
        if ( $a <= 1e-12 ) {
            return null;
        }
        $alpha = ( 1.0 + sqrt( 1.0 + 4.0 * $a / 3.0 ) ) / ( 4.0 * $a );
        $beta  = $mean / $alpha;
        if ( $alpha <= 0.0 || $beta <= 0.0 ) {
            return null;
        }

        $q = $zeros / count( $sample );
        $g = $value > 0.0 ? self::gamma_p( $alpha, $value / $beta ) : 0.0;
        $h = $q + ( 1.0 - $q ) * $g;

        // The deviate is unbounded at both ends; hold H away from 0 and 1 so
        // a record dry spell returns a number instead of an infinity.
        $h = min( 1.0 - 1e-10, max( 1e-10, $h ) );

        return self::inverse_normal( $h );
    }

    /**
     * Regularized lower incomplete gamma P(a, x).
     *
     * Series below the crossover, continued fraction above — the split
     * Numerical Recipes uses, because each form converges quickly only on
     * its own side of a+1.
     */
    private static function gamma_p( float $a, float $x ): float {
        if ( $a <= 0.0 || $x <= 0.0 ) {
            return 0.0;
        }
        $lead = -$x + $a * log( $x ) - self::log_gamma( $a );

        if ( $x < $a + 1.0 ) {
            $ap  = $a;
            $del = 1.0 / $a;
            $sum = $del;
            for ( $i = 0; $i < 500; $i++ ) {
                $ap  += 1.0;
                $del *= $x / $ap;
                $sum += $del;
                if ( abs( $del ) < abs( $sum ) * 1e-15 ) {
                    break;
                }
            }
            return $sum * exp( $lead );
        }

        $tiny = 1e-300;
        $b    = $x + 1.0 - $a;
        $c    = 1.0 / $tiny;
        $d    = 1.0 / $b;
        $h    = $d;
        for ( $i = 1; $i <= 500; $i++ ) {
            $an = -$i * ( $i - $a );
            $b += 2.0;
            $d  = $an * $d + $b;
            if ( abs( $d ) < $tiny ) {
                $d = $tiny;
            }
            $c = $b + $an / $c;
            if ( abs( $c ) < $tiny ) {
                $c = $tiny;
            }
            $d   = 1.0 / $d;
            $del = $d * $c;
            $h  *= $del;
            if ( abs( $del - 1.0 ) < 1e-15 ) {
                break;
            }
        }
        return 1.0 - exp( $lead ) * $h;
    }

    /**
     * log Gamma(x), Lanczos approximation with g = 7.
     */
    private static function log_gamma( float $x ): float {
        static $c = [
            676.5203681218851, -1259.1392167224028, 771.32342877765313,
            -176.61502916214059, 12.507343278686905, -0.13857109526572012,
            9.9843695780195716e-6, 1.5056327351493116e-7,
        ];

        if ( $x < 0.5 ) {
            // Reflection, so the series only ever runs on x >= 0.5.
            return log( M_PI / abs( sin( M_PI * $x ) ) ) - self::log_gamma( 1.0 - $x );
        }

        $x  -= 1.0;
        $sum = 0.99999999999980993;
        foreach ( $c as $i => $ci ) {
            $sum += $ci / ( $x + $i + 1.0 );
        }
        $t = $x + 7.5;
        return 0.5 * log( 2.0 * M_PI ) + ( $x + 0.5 ) * log( $t ) - $t + log( $sum );
    }

    /**
     * Inverse standard normal CDF, Abramowitz & Stegun 26.2.23.
     *
     * Absolute error below 4.5e-4 — the approximation the original SPI
     * program uses, and two orders below the two decimals the index is ever
     * shown with.
     */
    private static function inverse_normal( float $p ): float {
        $lower = $p <= 0.5;
        $pp    = $lower ? $p : 1.0 - $p;
        $t     = sqrt( -2.0 * log( $pp ) );

        $z = $t - ( ( 0.010328 * $t + 0.802853 ) * $t + 2.515517 )
                / ( ( ( 0.001308 * $t + 0.189269 ) * $t + 1.432788 ) * $t + 1.0 );

        return $lower ? -$z : $z;
    }

    /**
     * Monthly sums of one column, complete months only.
     *
     * A month counts only when every one of its days carries the column. A
     * rain sum over a month with three days missing is not a drier month, it
     * is an unknown one — and handing it to the index as if it were dry
     * turns a gauge outage into a drought.
     *
     * @return array<string,float> 'Y-m' => sum, ascending.
     */
    public static function monthly_sums( array $rows, string $field ): array {
        $sums = [];
        $days = [];
        foreach ( $rows as $row ) {
            $date  = (string) ( $row['day_date'] ?? '' );
            $value = $row[ $field ] ?? null;
            if ( strlen( $date ) < 7 || $value === null ) {
                continue;
            }
            $month          = substr( $date, 0, 7 );
            $sums[ $month ] = ( $sums[ $month ] ?? 0.0 ) + (float) $value;
            $days[ $month ] = ( $days[ $month ] ?? 0 ) + 1;
        }

        $out = [];
        foreach ( $sums as $month => $sum ) {
            if ( $days[ $month ] === self::days_in_month( $month ) ) {
                $out[ $month ] = $sum;
            }
        }
        ksort( $out );
        return $out;
    }

    /**
     * Days in a 'Y-m' month, leap years included.
     */
    private static function days_in_month( string $month ): int {
        $d = DateTimeImmutable::createFromFormat( 'Y-m-d', $month . '-01', new DateTimeZone( 'UTC' ) );
        return $d ? (int) $d->format( 't' ) : 0;
    }

    /**
     * Is $b the calendar month directly after $a? Both 'Y-m'.
     */
    private static function is_next_month( string $a, string $b ): bool {
        $da = DateTimeImmutable::createFromFormat( 'Y-m-d', $a . '-01', new DateTimeZone( 'UTC' ) );
        if ( ! $da ) {
            return false;
        }
        return $da->modify( 'first day of next month' )->format( 'Y-m' ) === $b;
    }

    /**
     * One day's weighted contribution to the grassland sum.
     */
    private static function grassland_contribution( array $row ): float {
        $avg = $row['temp_avg'] ?? null;
        if ( $avg === null || (float) $avg <= 0.0 ) {
            return 0.0;
        }
        $month  = (int) substr( (string) ( $row['day_date'] ?? '' ), 5, 2 );
        $weight = 1.0;
        if ( $month === 1 ) {
            $weight = 0.5;
        } elseif ( $month === 2 ) {
            $weight = 0.75;
        }
        return (float) $avg * $weight;
    }

    /**
     * Is $b the calendar day directly after $a? Both 'Y-m-d'.
     *
     * Uses DateTimeImmutable rather than string arithmetic so month ends,
     * year ends and leap days are the calendar's problem, not ours.
     */
    private static function is_next_day( string $a, string $b ): bool {
        $da = DateTimeImmutable::createFromFormat( 'Y-m-d', $a, new DateTimeZone( 'UTC' ) );
        $db = DateTimeImmutable::createFromFormat( 'Y-m-d', $b, new DateTimeZone( 'UTC' ) );
        if ( ! $da || ! $db ) {
            return false;
        }
        return $da->modify( '+1 day' )->format( 'Y-m-d' ) === $db->format( 'Y-m-d' );
    }
}
