<?php
/**
 * Wind rose: where the wind comes from, how often, and how hard.
 *
 * The arithmetic is pure — grouped rows in, a rose out — so it is tested on
 * hand-built rows. Only query(), peak_at() and rose() touch the database,
 * and rose() is the one the template calls.
 *
 * Angles: 0° is north and they grow clockwise, as Netatmo reports them and
 * as a compass reads. On the drawing x = c + r·sin(a), y = c − r·cos(a),
 * so north points up and east right with the SVG's y axis pointing down.
 *
 * @package NAWS
 * @since   1.9.12
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Windrose {

    /**
     * Lower bounds of the five classes in km/h, Beaufort 1 to 5; below the
     * first is calm. Beaufort 5 and everything above share the last class:
     * four gusts over 39 km/h in five months do not earn their own colour,
     * and a six-step ramp of one hue failed the contrast check.
     */
    const BINS = [ 1, 6, 12, 20, 29 ];

    /** The periods the switcher offers, in this order. */
    const SWITCH = [ '7d', '30d', '90d', 'year', 'all' ];

    /** Sector counts the shortcode accepts. */
    const SECTORS = [ 8, 16 ];

    /** Compass codes clockwise from north; the naws_label() key is 'compass_' . strtolower( code ). */
    const COMPASS_16 = [ 'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW' ];
    const COMPASS_8  = [ 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' ];

    /** Seconds a rose stays cached; the sync flushes it anyway after each run. */
    const CACHE_TTL = 3600;

    // ── Pure arithmetic ─────────────────────────────────────────────

    /** Class index 0–4 of a speed in km/h, −1 for calm. */
    public static function bin( float $kmh ): int {
        if ( $kmh < self::BINS[0] ) {
            return -1;
        }
        $b = 0;
        foreach ( self::BINS as $i => $lo ) {
            if ( $kmh >= $lo ) {
                $b = $i;
            }
        }
        return $b;
    }

    /**
     * Sector index of a direction in degrees, or null outside 0–360.
     * Sector i spans i·w ± w/2 with w = 360 / sectors, so north is centred
     * on 0° and the seam lies at 360 − w/2.
     */
    public static function sector( float $deg, int $sectors ): ?int {
        if ( $deg < 0 || $deg > 360 ) {
            return null;
        }
        $w = 360 / $sectors;
        return ( (int) floor( fmod( $deg + $w / 2, 360 ) / $w ) ) % $sectors;
    }

    /** @return string[] */
    public static function codes( int $sectors ): array {
        return $sectors === 8 ? self::COMPASS_8 : self::COMPASS_16;
    }

    /** The translated short code of sector i, e.g. "NNE" — or "NNO" in German. */
    public static function compass( int $i, int $sectors ): string {
        $codes = self::codes( $sectors );
        return naws_label( 'compass_' . strtolower( $codes[ $i % count( $codes ) ] ) );
    }

    /** The translated long name of sector i, e.g. "North-northeast". */
    public static function compass_long( int $i, int $sectors ): string {
        $codes = self::codes( $sectors );
        return naws_label( 'compass_long_' . strtolower( $codes[ $i % count( $codes ) ] ) );
    }

    /** A point on the drawing: degrees clockwise from north, radius, centre. */
    public static function point( float $deg, float $r, float $c ): array {
        return [ $c + $r * sin( deg2rad( $deg ) ), $c - $r * cos( deg2rad( $deg ) ) ];
    }

    /**
     * The path of a ring sector from a0 to a1 degrees between radii r0 and
     * r1: outer arc clockwise, inner arc back. Sectors are narrower than
     * 180°, so the large-arc flag is always 0.
     */
    public static function arc( float $a0, float $a1, float $r0, float $r1, float $c ): string {
        $f = static fn( float $v ): string => number_format( $v, 2, '.', '' );
        [ $x0, $y0 ] = self::point( $a0, $r1, $c );
        [ $x1, $y1 ] = self::point( $a1, $r1, $c );
        [ $x2, $y2 ] = self::point( $a1, $r0, $c );
        [ $x3, $y3 ] = self::point( $a0, $r0, $c );
        return 'M' . $f( $x0 ) . ' ' . $f( $y0 )
            . 'A' . $f( $r1 ) . ' ' . $f( $r1 ) . ' 0 0 1 ' . $f( $x1 ) . ' ' . $f( $y1 )
            . 'L' . $f( $x2 ) . ' ' . $f( $y2 )
            . 'A' . $f( $r0 ) . ' ' . $f( $r0 ) . ' 0 0 0 ' . $f( $x3 ) . ' ' . $f( $y3 ) . 'Z';
    }

    /**
     * Folds the grouped rows of query() into the rose.
     *
     * A row is [ sector, bin, n, sum_v, max_v, first_at ]; sector −1 is
     * calm (no direction), −2 a reading without a valid direction. Both
     * count towards n and the mean, neither towards a ray. 'ring' is the
     * scale's top: the longest ray rounded up to the next 5 %, at least 5 %.
     */
    public static function shape( array $rows, int $sectors, int $max_at = 0 ): array {
        $sectors = in_array( $sectors, self::SECTORS, true ) ? $sectors : 16;
        $classes = count( self::BINS );
        $n = 0; $calm = 0; $invalid = 0; $sum = 0.0; $max = 0.0; $first = 0;
        $sec = [];
        for ( $i = 0; $i < $sectors; $i++ ) {
            $sec[ $i ] = [ 'n' => 0, 'share' => 0.0, 'mean' => 0.0, 'max' => 0.0, 'bins' => array_fill( 0, $classes, 0 ), 'sum' => 0.0 ];
        }
        foreach ( $rows as $r ) {
            $cnt = (int) ( $r['n'] ?? 0 );
            $s   = (int) ( $r['sector'] ?? -2 );
            $b   = (int) ( $r['bin'] ?? -1 );
            $n  += $cnt;
            $sum += (float) ( $r['sum_v'] ?? 0 );
            $max  = max( $max, (float) ( $r['max_v'] ?? 0 ) );
            $fa   = (int) ( $r['first_at'] ?? 0 );
            if ( $fa > 0 && ( $first === 0 || $fa < $first ) ) {
                $first = $fa;
            }
            if ( $s === -1 || $b < 0 ) {
                $calm += $cnt;
                continue;
            }
            if ( $s < 0 || $s >= $sectors ) {
                $invalid += $cnt;
                continue;
            }
            $b = min( $b, $classes - 1 );
            $sec[ $s ]['n']         += $cnt;
            $sec[ $s ]['bins'][ $b ] += $cnt;
            $sec[ $s ]['sum']       += (float) ( $r['sum_v'] ?? 0 );
            $sec[ $s ]['max']        = max( $sec[ $s ]['max'], (float) ( $r['max_v'] ?? 0 ) );
        }
        $top_share = 0.0;
        foreach ( $sec as $i => $x ) {
            $sec[ $i ]['share'] = (float) ( $n > 0 ? $x['n'] / $n : 0.0 );
            $sec[ $i ]['mean']  = (float) ( $x['n'] > 0 ? round( $x['sum'] / $x['n'], 1 ) : 0.0 );
            unset( $sec[ $i ]['sum'] );
            $top_share = max( $top_share, $sec[ $i ]['share'] );
        }
        $order = array_keys( $sec );
        usort( $order, static function ( $a, $b ) use ( $sec ) {
            return ( $sec[ $b ]['n'] <=> $sec[ $a ]['n'] ) ?: ( $a <=> $b );
        } );
        $top = [];
        foreach ( $order as $i ) {
            if ( $sec[ $i ]['n'] > 0 && count( $top ) < 3 ) {
                $top[] = $i;
            }
        }
        return [
            'n'       => $n,
            'calm'    => $calm,
            'invalid' => $invalid,
            'mean'    => $n > 0 ? round( $sum / $n, 1 ) : 0.0,
            'max'     => $max,
            'max_at'  => $n > 0 ? $max_at : 0,
            'first'   => $first,
            'sectors' => $sec,
            'top'     => $top,
            'ring'    => round( max( 0.05, ceil( $top_share / 0.05 - 1e-9 ) * 0.05 ), 2 ),
        ];
    }
}
