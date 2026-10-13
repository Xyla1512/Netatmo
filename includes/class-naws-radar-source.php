<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Where the rain radar comes from: the Deutscher Wetterdienst inside
 * Germany (measured plus a two-hour nowcast), RainViewer everywhere else
 * (measured only). Knows the times of an animation and the addresses of
 * every image. Requests are made by NAWS_Radar, not here.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar_Source {

    const DWD_WMS   = 'https://maps.dwd.de/geoserver/dwd/wms';
    const DWD_PAST  = 'dwd:Niederschlagsradar';
    const DWD_AHEAD = 'dwd:Radar_wn-product_1x1km_ger';
    const RV_INDEX  = 'https://api.rainviewer.com/public/weather-maps.json';
    const OSM_TILES = 'https://tile.openstreetmap.org/';

    const STEP      = 900;   // 15 minutes between DWD frames
    const PAST      = 4;     // frames before "now"
    const AHEAD     = 8;     // nowcast frames after "now" (2 hours)
    const DWD_DELAY = 600;   // a composite is ready about 10 minutes late
    const RV_WINDOW = 7200;  // RainViewer keeps two hours
    const RV_MAX    = 13;

    /**
     * Germany, coarsely: [ lon, lat ] clockwise from the Dutch border. The
     * DWD composite reaches beyond the border, so a few kilometres of
     * error either way do no harm.
     */
    const GERMANY = [
        [ 5.87, 51.05 ], [ 6.23, 51.36 ], [ 5.95, 51.80 ], [ 6.83, 51.97 ], [ 7.06, 52.24 ], [ 6.73, 52.48 ],
        [ 7.05, 52.65 ], [ 7.21, 53.24 ], [ 6.90, 53.70 ], [ 8.00, 54.05 ], [ 8.20, 54.95 ], [ 8.40, 55.06 ],
        [ 9.90, 54.80 ], [ 10.90, 54.60 ], [ 13.00, 54.80 ], [ 14.30, 54.20 ], [ 14.40, 53.30 ], [ 14.15, 52.90 ],
        [ 14.60, 52.60 ], [ 14.75, 52.00 ], [ 15.05, 51.30 ], [ 15.05, 51.00 ], [ 14.80, 50.85 ], [ 14.30, 50.90 ],
        [ 13.40, 50.60 ], [ 12.50, 50.40 ], [ 12.10, 50.30 ], [ 12.45, 49.75 ], [ 13.00, 49.30 ], [ 13.85, 48.80 ],
        [ 13.50, 48.55 ], [ 13.00, 48.25 ], [ 12.85, 47.90 ], [ 13.05, 47.50 ], [ 12.20, 47.60 ], [ 11.20, 47.40 ],
        [ 10.50, 47.30 ], [ 10.00, 47.50 ], [ 9.55, 47.55 ], [ 8.60, 47.65 ], [ 7.60, 47.58 ], [ 7.55, 47.75 ],
        [ 7.60, 48.10 ], [ 7.80, 48.60 ], [ 8.20, 48.97 ], [ 7.10, 49.15 ], [ 6.40, 49.46 ], [ 6.10, 50.10 ],
        [ 6.40, 50.30 ], [ 6.00, 50.75 ],
    ];

    /** Ray casting against GERMANY. */
    public static function in_germany( float $lat, float $lon ): bool {
        $inside = false;
        $p      = self::GERMANY;
        $n      = count( $p );
        for ( $i = 0, $j = $n - 1; $i < $n; $j = $i++ ) {
            [ $xi, $yi ] = $p[ $i ];
            [ $xj, $yj ] = $p[ $j ];
            if ( ( $yi > $lat ) !== ( $yj > $lat ) && $lon < ( $xj - $xi ) * ( $lat - $yi ) / ( $yj - $yi ) + $xi ) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }

    /** 'dwd' or 'rainviewer'. DWD only where it has data. */
    public static function pick( string $wanted, float $lat, float $lon ): string {
        if ( 'rainviewer' === $wanted ) {
            return 'rainviewer';
        }
        return self::in_germany( $lat, $lon ) ? 'dwd' : 'rainviewer';
    }

    /**
     * Thirteen frame times on the quarter hour: four measured, "now" (the
     * newest composite that should be ready), eight nowcast frames.
     *
     * @return array<int, array{t:int, forecast:bool}>
     */
    public static function dwd_times( int $now ): array {
        $base = (int) ( floor( ( $now - self::DWD_DELAY ) / self::STEP ) * self::STEP );
        $out  = [];
        for ( $i = -self::PAST; $i <= self::AHEAD; $i++ ) {
            $out[] = [
                't'        => $base + $i * self::STEP,
                'forecast' => $i > 0,
            ];
        }
        return $out;
    }

    /** WMS GetMap for exactly the box, transparent PNG. */
    public static function dwd_url( array $box, int $t, bool $forecast ): string {
        $args = [
            'service'     => 'WMS',
            'version'     => '1.1.1',
            'request'     => 'GetMap',
            'layers'      => $forecast ? self::DWD_AHEAD : self::DWD_PAST,
            'styles'      => '',
            'srs'         => 'EPSG:3857',
            'bbox'        => implode( ',', array_map( static fn( $v ) => sprintf( '%.2F', $v ), $box ) ),
            'width'       => NAWS_Radar_Geo::SIZE,
            'height'      => NAWS_Radar_Geo::SIZE,
            'format'      => 'image/png',
            'transparent' => 'true',
            'time'        => gmdate( 'Y-m-d\TH:i:s\Z', $t ),
        ];
        return self::DWD_WMS . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
    }

    /** The tile host named in the index, only if it is RainViewer's over https. */
    public static function rv_host( array $index ): ?string {
        $host = isset( $index['host'] ) && is_string( $index['host'] ) ? $index['host'] : '';
        return preg_match( '#^https://[a-z0-9.-]+\.rainviewer\.com$#', $host ) ? $host : null;
    }

    /**
     * The past frames of the index within the last two hours, oldest
     * first, at most RV_MAX. Paths that do not look like RainViewer's are
     * dropped, so nothing from the index can steer a request elsewhere.
     *
     * @return array<int, array{t:int, path:string}>
     */
    public static function rv_frames( array $index, int $now ): array {
        $past = $index['radar']['past'] ?? [];
        if ( ! is_array( $past ) ) {
            return [];
        }
        $out = [];
        foreach ( $past as $f ) {
            $t    = (int) ( $f['time'] ?? 0 );
            $path = (string) ( $f['path'] ?? '' );
            if ( $t < $now - self::RV_WINDOW - 300 || $t > $now + 300 || ! preg_match( '#^/v2/radar/[A-Za-z0-9]+$#', $path ) ) {
                continue;
            }
            $out[] = [
                't'    => $t,
                'path' => $path,
            ];
        }
        usort( $out, static fn( $a, $b ) => $a['t'] <=> $b['t'] );
        return array_slice( $out, -self::RV_MAX );
    }

    /** Colour scheme 2 ("Universal Blue", the only one left), smoothed, with snow. */
    public static function rv_tile_url( string $host, string $path, int $z, int $x, int $y ): string {
        return "{$host}{$path}/256/{$z}/{$x}/{$y}/2/1_1.png";
    }

    public static function osm_tile_url( int $z, int $x, int $y ): string {
        return self::OSM_TILES . "{$z}/{$x}/{$y}.png";
    }
}
