<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Geometry of the rain radar: a square box around the station in Web
 * Mercator (EPSG:3857), the map zoom level and the tiles that cover it.
 * Pure arithmetic, no requests and no WordPress.
 *
 * Every position is in the SIZE x SIZE pixel space of the radar image;
 * the station sits in its centre.
 *
 * @package NAWS
 * @since   2.1.3
 */
class NAWS_Radar_Geo {

    const SIZE        = 1024;
    const EARTH       = 6378137.0;
    const MAX_ZOOM    = 12;
    const RV_MAX_ZOOM = 7;
    const RV_TILES    = 9;
    const MAP_TILE    = 300;  // map tile size in the image that keeps the labels readable
    const KM_DEFAULT  = 150;

    /** Width of the box in km, per source: [ min, max ]. */
    const KM_LIMITS = [
        'dwd'        => [ 50, 600 ],
        'rainviewer' => [ 150, 1500 ],
    ];

    /**
     * The km attribute as a whole number within the limits of the source.
     * RainViewer stops at zoom 7, so a small box there would be a blur.
     */
    public static function clamp_km( $km, string $source ): int {
        [ $min, $max ] = self::KM_LIMITS[ $source ] ?? self::KM_LIMITS['rainviewer'];
        $km            = is_numeric( $km ) ? (int) round( (float) $km ) : self::KM_DEFAULT;
        return max( $min, min( $max, $km ) );
    }

    /**
     * Square box of $km on the ground, centred on the station. Mercator
     * stretches by 1/cos(lat), so the box does too.
     *
     * @return float[] [ minx, miny, maxx, maxy ] in metres.
     */
    public static function box( float $lat, float $lon, int $km ): array {
        $lat  = max( -85.0, min( 85.0, $lat ) );
        $half = $km * 500 / cos( deg2rad( $lat ) );
        $mx   = deg2rad( $lon ) * self::EARTH;
        $my   = self::EARTH * log( tan( M_PI / 4 + deg2rad( $lat ) / 2 ) );
        return [ $mx - $half, $my - $half, $mx + $half, $my + $half ];
    }

    /** Smallest zoom whose tiles give the box at least SIZE pixels. */
    public static function zoom_for( array $box, int $max = self::MAX_ZOOM ): int {
        $world = 2 * M_PI * self::EARTH;
        for ( $z = 0; $z < $max; $z++ ) {
            if ( ( $box[2] - $box[0] ) / ( $world / ( 256 * ( 2 ** $z ) ) ) >= self::SIZE ) {
                return $z;
            }
        }
        return $max;
    }

    /**
     * Map zoom: OSM draws its labels for 256 px tiles, but at zoom_for()
     * a tile may take only half of that in the image, and the labels
     * become too small to read. One zoom coarser when its tile size is
     * nearer (on a log scale) to MAP_TILE pixels in the image.
     */
    public static function map_zoom( array $box ): int {
        $z = self::zoom_for( $box );
        if ( $z < 1 ) {
            return $z;
        }
        $tile = self::SIZE * ( 2 * M_PI * self::EARTH / ( 2 ** $z ) ) / ( $box[2] - $box[0] );
        // Coarser tiles are twice the size: closer to MAP_TILE when tile * 2 / MAP_TILE < MAP_TILE / tile.
        return 2 * $tile * $tile < self::MAP_TILE * self::MAP_TILE ? $z - 1 : $z;
    }

    /**
     * RainViewer zoom: at most RV_MAX_ZOOM and at most RV_TILES tiles per
     * frame, so thirteen frames stay within the request budget of a run.
     * A wide box far north gets a coarser picture instead.
     */
    public static function rv_zoom( array $box ): int {
        $z = min( self::zoom_for( $box ), self::RV_MAX_ZOOM );
        while ( $z > 0 && count( self::tiles( $box, $z ) ) > self::RV_TILES ) {
            --$z;
        }
        return $z;
    }

    /**
     * The tiles of zoom $z that cover the box, each with its place in the
     * SIZE x SIZE image. x wraps at the date line, left keeps counting.
     *
     * @return array<int, array{z:int, x:int, y:int, left:float, top:float, size:float}>
     */
    public static function tiles( array $box, int $z ): array {
        $world = 2 * M_PI * self::EARTH;
        $n     = 2 ** $z;
        $tm    = $world / $n;
        $scale = self::SIZE / ( $box[2] - $box[0] );
        $x0    = (int) floor( ( $box[0] + $world / 2 ) / $tm );
        $x1    = (int) floor( ( $box[2] + $world / 2 - 1e-6 ) / $tm );
        $y0    = max( 0, (int) floor( ( $world / 2 - $box[3] ) / $tm ) );
        $y1    = min( $n - 1, (int) floor( ( $world / 2 - $box[1] - 1e-6 ) / $tm ) );
        $out   = [];
        for ( $y = $y0; $y <= $y1; $y++ ) {
            for ( $x = $x0; $x <= $x1; $x++ ) {
                $out[] = [
                    'z'    => $z,
                    'x'    => ( ( $x % $n ) + $n ) % $n,
                    'y'    => $y,
                    'left' => round( ( ( $x * $tm - $world / 2 ) - $box[0] ) * $scale, 2 ),
                    'top'  => round( ( $box[3] - ( $world / 2 - $y * $tm ) ) * $scale, 2 ),
                    'size' => round( $tm * $scale, 2 ),
                ];
            }
        }
        return $out;
    }
}
