<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [naws_radar]: the rain radar around the station.
 *
 * The visitor's browser only ever talks to this site. The server fetches
 * map tiles once and radar images every fifteen minutes - but only after a
 * page showed the radar, in a single background run - and keeps them in
 * uploads/naws-radar/. Nothing goes out unless "Allow rain radar" is on.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar {

    const HOOK_REFRESH = 'naws_radar_refresh';
    const MAX_AGE      = 900;     // renew a set older than this
    const STALE        = 10800;   // older than this: "temporarily unavailable"

    public static function enabled(): bool {
        $o = get_option( 'naws_settings', [] );
        return ! empty( $o['radar_enabled'] );
    }

    public static function user_agent(): string {
        return 'XTX-Integration-for-Netatmo/' . NAWS_VERSION . ' (+' . home_url( '/' ) . ')';
    }

    private static function get( string $url ) {
        return wp_remote_get(
            $url,
            [
                'timeout'    => 15,
                'user-agent' => self::user_agent(),
            ]
        );
    }

    /**
     * 'png' with the bytes, 'skip' (no image for this one, try the next) or
     * 'fail' (the service is in trouble: pause it and stop).
     *
     * @return array{0:string, 1:string}
     */
    private static function classify( $response ): array {
        if ( is_wp_error( $response ) ) {
            return [ 'fail', '' ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 429 === $code || $code >= 500 ) {
            return [ 'fail', '' ];
        }
        $body = (string) wp_remote_retrieve_body( $response );
        if ( 200 !== $code || 0 !== strncmp( $body, "\x89PNG", 4 ) ) {
            return [ 'skip', '' ];
        }
        return [ 'png', $body ];
    }

    /** Background run for one set. True when a new manifest was written. */
    public static function refresh( string $key, int $now = 0 ): bool {
        $now = $now ? $now : time();
        $set = NAWS_Radar_Store::sets()[ $key ] ?? null;
        if ( ! self::enabled() || ! is_array( $set ) ) {
            return false;
        }
        $source = 'dwd' === ( $set['source'] ?? '' ) ? 'dwd' : 'rainviewer';
        if ( NAWS_Radar_Store::in_backoff( $source ) || ! NAWS_Radar_Store::lock( $key ) ) {
            return false;
        }
        try {
            $old    = NAWS_Radar_Store::manifest( $key );
            $box    = NAWS_Radar_Geo::box( (float) $set['lat'], (float) $set['lon'], (int) $set['km'] );
            $map    = self::fetch_map( $key, $box );
            $frames = 'dwd' === $source ? self::fetch_dwd( $key, $box, $now ) : self::fetch_rainviewer( $key, $box, $now );
            if ( ! $frames ) {
                return false;
            }
            $new            = [
                'v'       => 1,
                'source'  => $source,
                'updated' => $now,
                'map'     => [ 'tiles' => $map ],
                'frames'  => $frames,
            ];
            $new['retired'] = NAWS_Radar_Store::prune( $key, $old, $new, $now );
            return NAWS_Radar_Store::save_manifest( $key, $new );
        } finally {
            NAWS_Radar_Store::unlock( $key );
        }
    }

    /** Map tiles: fetched once, kept on disk. Missing ones are tried again next run. */
    private static function fetch_map( string $key, array $box ): array {
        $out  = [];
        $ok   = ! NAWS_Radar_Store::in_backoff( 'osm' );
        foreach ( NAWS_Radar_Geo::tiles( $box, NAWS_Radar_Geo::zoom_for( $box ) ) as $t ) {
            $file  = sprintf( 'map/%d-%d-%d.png', $t['z'], $t['x'], $t['y'] );
            $entry = [
                'file' => $file,
                'left' => $t['left'],
                'top'  => $t['top'],
                'size' => $t['size'],
            ];
            if ( NAWS_Radar_Store::has( $key, $file ) ) {
                $out[] = $entry;
                continue;
            }
            if ( ! $ok ) {
                continue;
            }
            $r            = self::get( NAWS_Radar_Source::osm_tile_url( $t['z'], $t['x'], $t['y'] ) );
            [ $kind, $png ] = self::classify( $r );
            if ( 'fail' === $kind ) {
                NAWS_Radar_Store::start_backoff( 'osm', $r );
                $ok = false;
                continue;
            }
            if ( 'png' === $kind && NAWS_Radar_Store::put( $key, $file, $png ) ) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /** DWD: measured frames are reused, nowcast frames belong to their run. */
    private static function fetch_dwd( string $key, array $box, int $now ): array {
        $times = NAWS_Radar_Source::dwd_times( $now );
        $base  = $times[ NAWS_Radar_Source::PAST ]['t'];
        $out   = [];
        foreach ( $times as $f ) {
            $file = $f['forecast'] ? sprintf( 'frames/f%d-%d.png', $f['t'], $base ) : sprintf( 'frames/%d.png', $f['t'] );
            if ( ! NAWS_Radar_Store::has( $key, $file ) ) {
                $r              = self::get( NAWS_Radar_Source::dwd_url( $box, $f['t'], $f['forecast'] ) );
                [ $kind, $png ] = self::classify( $r );
                if ( 'fail' === $kind ) {
                    NAWS_Radar_Store::start_backoff( 'dwd', $r );
                    break;
                }
                if ( 'skip' === $kind || ! NAWS_Radar_Store::put( $key, $file, $png ) ) {
                    continue;
                }
            }
            $out[] = [
                't'        => $f['t'],
                'forecast' => $f['forecast'],
                'file'     => $file,
            ];
        }
        return $out;
    }

    /** RainViewer: the index, then the tiles of each frame (kept once fetched). */
    private static function fetch_rainviewer( string $key, array $box, int $now ): array {
        $r = self::get( NAWS_Radar_Source::RV_INDEX );
        if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
            NAWS_Radar_Store::start_backoff( 'rainviewer', $r );
            return [];
        }
        $index = json_decode( (string) wp_remote_retrieve_body( $r ), true );
        $host  = is_array( $index ) ? NAWS_Radar_Source::rv_host( $index ) : null;
        if ( null === $host ) {
            NAWS_Radar_Store::start_backoff( 'rainviewer', $r );
            return [];
        }
        $tiles = NAWS_Radar_Geo::tiles( $box, min( NAWS_Radar_Geo::zoom_for( $box ), NAWS_Radar_Geo::RV_MAX_ZOOM ) );
        $out   = [];
        foreach ( NAWS_Radar_Source::rv_frames( $index, $now ) as $f ) {
            $parts = [];
            $mine  = [];
            foreach ( $tiles as $t ) {
                $file = sprintf( 'frames/%d-%d-%d-%d.png', $f['t'], $t['z'], $t['x'], $t['y'] );
                if ( ! NAWS_Radar_Store::has( $key, $file ) ) {
                    $tr             = self::get( NAWS_Radar_Source::rv_tile_url( $host, $f['path'], $t['z'], $t['x'], $t['y'] ) );
                    [ $kind, $png ] = self::classify( $tr );
                    if ( 'fail' === $kind ) {
                        NAWS_Radar_Store::start_backoff( 'rainviewer', $tr );
                        NAWS_Radar_Store::delete_files( $key, $mine );
                        return $out;
                    }
                    if ( 'skip' === $kind || ! NAWS_Radar_Store::put( $key, $file, $png ) ) {
                        NAWS_Radar_Store::delete_files( $key, $mine );
                        continue 2;
                    }
                    $mine[] = $file;
                }
                $parts[] = [
                    'file' => $file,
                    'left' => $t['left'],
                    'top'  => $t['top'],
                    'size' => $t['size'],
                ];
            }
            $out[] = [
                't'        => $f['t'],
                'forecast' => false,
                'tiles'    => $parts,
            ];
        }
        return $out;
    }
}
