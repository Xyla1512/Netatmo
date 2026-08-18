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
        $best    = 0;
        $current = 0;
        $prev    = null;

        foreach ( $rows as $row ) {
            $date = $row['day_date'] ?? '';
            if ( ! $matches( $row ) ) {
                $current = 0;
                $prev    = $date;
                continue;
            }
            $current = ( $prev !== null && self::is_next_day( $prev, $date ) ) ? $current + 1 : 1;
            $best    = max( $best, $current );
            $prev    = $date;
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
     * @param string $direction 'heating' or 'cooling'.
     */
    public static function degree_days( array $rows, float $threshold, float $reference, string $direction ): float {
        $sum = 0.0;
        foreach ( $rows as $row ) {
            $avg = $row['temp_avg'] ?? null;
            if ( $avg === null ) {
                continue;
            }
            $avg = (float) $avg;
            if ( $direction === 'cooling' ) {
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
