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
    const AS_OF        = 1800;    // older than this: "As of HH:MM" under the radar
    const MAX_REQUESTS = 100;     // external requests per background run
    const PAUSE        = 900;     // pause after a run without images or storage

    /** Requests this run may still make. */
    private static $left = 0;

    /** True once this run hit the request budget. */
    private static $capped = false;

    /** True once this run could not write to the uploads folder. */
    private static $put_failed = false;

    public static function enabled(): bool {
        $o = get_option( 'naws_settings', [] );
        return ! empty( $o['radar_enabled'] );
    }

    public static function user_agent(): string {
        return 'XTX-Integration-for-Netatmo/' . NAWS_VERSION . ' (+' . home_url( '/' ) . ')';
    }

    /** One request out of the run's budget; null when the budget is spent. */
    private static function get( string $url ) {
        if ( self::$left <= 0 ) {
            self::$capped = true;
            return null;
        }
        --self::$left;
        return wp_remote_get(
            $url,
            [
                'timeout'    => 15,
                'user-agent' => self::user_agent(),
            ]
        );
    }

    /** Store a file; a failure means the uploads folder is not writable. */
    private static function put( string $key, string $file, string $png ): bool {
        if ( NAWS_Radar_Store::put( $key, $file, $png ) ) {
            return true;
        }
        self::$put_failed = true;
        return false;
    }

    /**
     * 'png' with the bytes, 'skip' (no image for this one, try the next),
     * 'fail' (the service is in trouble or refuses us: pause it and stop)
     * or 'cap' (the run's request budget is spent: stop, no pause).
     *
     * @return array{0:string, 1:string}
     */
    private static function classify( $response ): array {
        if ( null === $response ) {
            return [ 'cap', '' ];
        }
        if ( is_wp_error( $response ) ) {
            return [ 'fail', '' ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( in_array( $code, [ 401, 403, 418, 429 ], true ) || $code >= 500 ) {
            return [ 'fail', '' ];
        }
        $body = (string) wp_remote_retrieve_body( $response );
        if ( 200 !== $code || 0 !== strncmp( $body, "\x89PNG", 4 ) ) {
            return [ 'skip', '' ];
        }
        return [ 'png', $body ];
    }

    /**
     * Background run for one set. True when a new manifest was written.
     *
     * Radar frames first, then the map with what is left of the budget;
     * missing map tiles follow in later runs.
     *
     * @param string $key    the set
     * @param int    $now    for tests; 0 = time()
     * @param int    $budget for tests; external requests this run may make
     */
    public static function refresh( string $key, int $now = 0, int $budget = self::MAX_REQUESTS ): bool {
        $now = $now ? $now : time();
        $set = NAWS_Radar_Store::sets()[ $key ] ?? null;
        if ( ! self::enabled() || ! is_array( $set ) ) {
            return false;
        }
        $source = 'dwd' === ( $set['source'] ?? '' ) ? 'dwd' : 'rainviewer';
        if ( NAWS_Radar_Store::in_backoff( $source ) || ! NAWS_Radar_Store::lock( $key ) ) {
            return false;
        }
        NAWS_Radar_Store::touch_try( $key, $now );
        if ( function_exists( 'set_time_limit' ) ) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- may be disabled on the host; then the default limit applies
            @set_time_limit( 120 );
        }
        self::$left       = max( 0, $budget );
        self::$capped     = false;
        self::$put_failed = false;
        try {
            $old    = NAWS_Radar_Store::manifest( $key );
            $box    = NAWS_Radar_Geo::box( (float) $set['lat'], (float) $set['lon'], (int) $set['km'] );
            $frames = 'dwd' === $source ? self::fetch_dwd( $key, $box, $now ) : self::fetch_rainviewer( $key, $box, $now );
            if ( ! $frames ) {
                if ( ! self::$put_failed && ! self::$capped && ! NAWS_Radar_Store::in_backoff( $source ) ) {
                    NAWS_Radar_Store::pause( $source, self::PAUSE, 'returned no radar image' );
                }
                return false;
            }
            $new            = [
                'v'       => 1,
                'source'  => $source,
                'updated' => $now,
                'map'     => [ 'tiles' => self::fetch_map( $key, $box ) ],
                'frames'  => $frames,
            ];
            $new['retired'] = NAWS_Radar_Store::prune( $key, $old, $new, $now );
            if ( ! NAWS_Radar_Store::save_manifest( $key, $new ) ) {
                self::$put_failed = true;
                return false;
            }
            // Map tiles of another zoom (an earlier rule) are not used any more.
            NAWS_Radar_Store::delete_files( $key, array_diff(
                array_column( (array) ( $old['map']['tiles'] ?? [] ), 'file' ),
                array_column( $new['map']['tiles'], 'file' )
            ) );
            return true;
        } finally {
            if ( self::$put_failed ) {
                NAWS_Logger::error( 'radar', 'The uploads folder is not writable, so the rain radar cannot store images.' );
                NAWS_Radar_Store::pause( $source, self::PAUSE, 'could not store images' );
            }
            NAWS_Radar_Store::unlock( $key );
        }
    }

    /** Map tiles: fetched once, kept on disk. Missing ones are tried again next run. */
    private static function fetch_map( string $key, array $box ): array {
        $out = [];
        $ok  = ! NAWS_Radar_Store::in_backoff( 'osm' );
        foreach ( NAWS_Radar_Geo::tiles( $box, NAWS_Radar_Geo::map_zoom( $box ) ) as $t ) {
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
            $r              = self::get( NAWS_Radar_Source::osm_tile_url( $t['z'], $t['x'], $t['y'] ) );
            [ $kind, $png ] = self::classify( $r );
            if ( 'fail' === $kind ) {
                NAWS_Radar_Store::start_backoff( 'osm', $r );
            }
            if ( 'fail' === $kind || 'cap' === $kind ) {
                $ok = false; // tiles already on disk still count
                continue;
            }
            if ( 'png' === $kind ) {
                if ( ! self::put( $key, $file, $png ) ) {
                    $ok = false;
                    continue;
                }
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
                if ( 'cap' === $kind ) {
                    break;
                }
                if ( 'skip' === $kind ) {
                    continue;
                }
                if ( ! self::put( $key, $file, $png ) ) {
                    break;
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

    /**
     * RainViewer: the index, then the tiles of each frame (kept once
     * fetched), newest frame first so a capped run still shows "now".
     */
    private static function fetch_rainviewer( string $key, array $box, int $now ): array {
        $r = self::get( NAWS_Radar_Source::RV_INDEX );
        if ( null === $r ) {
            return [];
        }
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
        $tiles = NAWS_Radar_Geo::tiles( $box, NAWS_Radar_Geo::rv_zoom( $box ) );
        $out   = [];
        foreach ( array_reverse( NAWS_Radar_Source::rv_frames( $index, $now ) ) as $f ) {
            $parts = [];
            $mine  = [];
            foreach ( $tiles as $t ) {
                $file = sprintf( 'frames/%d-%d-%d-%d.png', $f['t'], $t['z'], $t['x'], $t['y'] );
                if ( ! NAWS_Radar_Store::has( $key, $file ) ) {
                    $tr             = self::get( NAWS_Radar_Source::rv_tile_url( $host, $f['path'], $t['z'], $t['x'], $t['y'] ) );
                    [ $kind, $png ] = self::classify( $tr );
                    if ( 'cap' === $kind ) {
                        // Keep the tiles of this frame: the next run reuses them,
                        // the daily sweep removes them if it never does.
                        break 2;
                    }
                    if ( 'fail' === $kind ) {
                        NAWS_Radar_Store::start_backoff( 'rainviewer', $tr );
                        NAWS_Radar_Store::delete_files( $key, $mine );
                        break 2;
                    }
                    if ( 'png' === $kind && ! self::put( $key, $file, $png ) ) {
                        NAWS_Radar_Store::delete_files( $key, $mine );
                        break 2;
                    }
                    if ( 'skip' === $kind ) {
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
        usort( $out, static fn( $a, $b ) => $a['t'] <=> $b['t'] );
        return $out;
    }

    /** Hooks: the background run, the REST route, clean-up when switched off, privacy text. */
    public static function init(): void {
        add_action( self::HOOK_REFRESH, [ __CLASS__, 'refresh' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'admin_init', [ __CLASS__, 'privacy_text' ] );
        add_action(
            'update_option_naws_settings',
            static function ( $old, $new ) {
                if ( ! empty( $old['radar_enabled'] ) && empty( $new['radar_enabled'] ) ) {
                    NAWS_Radar_Store::clear_all();
                }
            },
            10,
            2
        );
    }

    /** @return array{width:string, height:int, km:mixed, source:string} */
    public static function normalise_atts( array $atts ): array {
        $w = trim( (string) ( $atts['width'] ?? '100%' ) );
        if ( ! preg_match( '/^\d{1,4}(px|%)?$/', $w ) ) {
            $w = '100%';
        } elseif ( ctype_digit( $w ) ) {
            $w .= 'px';
        }
        $h = is_numeric( $atts['height'] ?? null ) ? (int) $atts['height'] : 420;
        $s = (string) ( $atts['source'] ?? 'auto' );
        return [
            'width'  => $w,
            'height' => max( 200, min( 1200, $h ) ),
            'km'     => $atts['km'] ?? (string) NAWS_Radar_Geo::KM_DEFAULT,
            'source' => in_array( $s, [ 'auto', 'dwd', 'rainviewer' ], true ) ? $s : 'auto',
        ];
    }

    /**
     * One background run when the set is missing or older than MAX_AGE -
     * and the last attempt is too, so a failing run is not repeated at the
     * rate pages are viewed.
     */
    public static function maybe_schedule( string $key, ?array $m, int $now ): void {
        $set = NAWS_Radar_Store::sets()[ $key ] ?? [];
        if ( $now - (int) ( $m['updated'] ?? 0 ) < self::MAX_AGE || $now - (int) ( $set['last_try'] ?? 0 ) < self::MAX_AGE ) {
            return;
        }
        if ( wp_next_scheduled( self::HOOK_REFRESH, [ $key ] ) ) {
            return;
        }
        wp_schedule_single_event( $now, self::HOOK_REFRESH, [ $key ] );
    }

    /** What the player gets: own URLs and times only. */
    public static function payload( string $key, ?array $m, string $source, int $now ): array {
        $fmt     = (string) get_option( 'time_format', 'H:i' );
        $updated = (int) ( $m['updated'] ?? 0 );
        $stale   = ! $m || $now - $updated > self::STALE;
        $frames  = [];
        foreach ( $stale ? [] : (array) ( $m['frames'] ?? [] ) as $f ) {
            $e = [
                't'        => (int) $f['t'],
                'label'    => wp_date( $fmt, (int) $f['t'] ),
                'forecast' => ! empty( $f['forecast'] ),
            ];
            if ( isset( $f['file'] ) ) {
                $e['img'] = NAWS_Radar_Store::url( $key, $f['file'] );
            } else {
                $e['tiles'] = array_map(
                    static fn( $t ) => [ NAWS_Radar_Store::url( $key, $t['file'] ), $t['left'], $t['top'], $t['size'] ],
                    (array) ( $f['tiles'] ?? [] )
                );
            }
            $frames[] = $e;
        }
        return [
            'key'          => $key,
            'source'       => $source,
            'updated'      => $updated,
            'updatedLabel' => $updated ? wp_date( $fmt, $updated ) : '',
            'stale'        => $stale,
            'rest'         => rest_url( 'naws/v1/radar/' . $key ),
            'frames'       => $frames,
            'map'          => array_map(
                static fn( $t ) => [ NAWS_Radar_Store::url( $key, $t['file'] ), $t['left'], $t['top'], $t['size'] ],
                (array) ( $m['map']['tiles'] ?? [] )
            ),
        ];
    }

    private static function admin_hint( string $label ): string {
        return current_user_can( 'manage_options' )
            ? '<p class="naws-radar-hint">' . esc_html( naws_label( $label ) ) . '</p>'
            : '';
    }

    public static function render( array $atts, int $now = 0 ): string {
        $now = $now ? $now : time();
        if ( ! self::enabled() ) {
            return self::admin_hint( 'rd_admin_off' );
        }
        $loc = NAWS_Forecast::resolve_location();
        if ( ! isset( $loc['lat'], $loc['lon'] ) ) {
            return self::admin_hint( 'rd_admin_noloc' );
        }
        $a      = self::normalise_atts( $atts );
        // 0.01 degrees, about 1 km: the radar centre must not pinpoint the station.
        $lat    = round( (float) $loc['lat'], 2 );
        $lon    = round( (float) $loc['lon'], 2 );
        $source = NAWS_Radar_Source::pick( $a['source'], $lat, $lon );
        $km     = NAWS_Radar_Geo::clamp_km( $a['km'], $source );
        $key    = NAWS_Radar_Store::key( $lat, $lon, $km, $source );
        NAWS_Radar_Store::remember(
            $key,
            [
                'lat'    => $lat,
                'lon'    => $lon,
                'km'     => $km,
                'source' => $source,
            ],
            $now
        );
        $m = NAWS_Radar_Store::manifest( $key );
        self::maybe_schedule( $key, $m, $now );

        $naws_rd        = self::payload( $key, $m, $source, $now );
        $naws_rd_state  = ! $m ? 'preparing' : ( $naws_rd['stale'] ? 'unavailable' : 'ready' );
        $naws_rd_width  = $a['width'];
        $naws_rd_height = $a['height'];
        $naws_rd_place  = (string) ( $loc['name'] ?? '' );
        $naws_rd_asof   = $naws_rd['updated'] > 0 && $now - $naws_rd['updated'] > self::AS_OF;
        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/radar.php';
        return (string) ob_get_clean();
    }

    public static function register_routes(): void {
        register_rest_route(
            'naws/v1',
            '/radar/(?P<key>[a-f0-9]{12})',
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'rest_manifest' ],
                // Public on purpose, unlike the key-protected routes of
                // NAWS_Rest_API: it answers only for sets this site has
                // shown on a page, and hands out times and URLs of images
                // on this site - what the page itself already contains.
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * @param WP_REST_Request|array $request
     * @param int                   $now     for tests; 0 = time()
     */
    public static function rest_manifest( $request, int $now = 0 ) {
        $key = (string) $request['key'];
        $set = NAWS_Radar_Store::sets()[ $key ] ?? null;
        if ( ! self::enabled() || ! is_array( $set ) ) {
            return new WP_Error( 'naws_radar_unknown', 'Unknown radar.', [ 'status' => 404 ] );
        }
        $now = $now ? $now : time();
        // A page served from a cache never runs render(): the player's
        // question is then the only sign that the set is still shown.
        NAWS_Radar_Store::remember(
            $key,
            [
                'lat'    => (float) ( $set['lat'] ?? 0 ),
                'lon'    => (float) ( $set['lon'] ?? 0 ),
                'km'     => (int) ( $set['km'] ?? 0 ),
                'source' => (string) ( $set['source'] ?? '' ),
            ],
            $now
        );
        $m   = NAWS_Radar_Store::manifest( $key );
        self::maybe_schedule( $key, $m, $now );
        $res = rest_ensure_response( self::payload( $key, $m, (string) $set['source'], $now ) );
        $res->header( 'Cache-Control', 'no-store' );
        return $res;
    }

    /** From the daily cron run. */
    public static function daily_cleanup( int $now = 0 ): int {
        if ( ! self::enabled() && ! NAWS_Radar_Store::sets() ) {
            return 0; // never used or already cleared: nothing to look at
        }
        return NAWS_Radar_Store::cleanup( $now ? $now : time() );
    }

    /** Suggested text for the site's privacy policy page. */
    public static function privacy_text(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }
        wp_add_privacy_policy_content(
            'XTX Integration for Netatmo',
            '<p>' . esc_html__( 'If the rain radar is switched on, this website fetches radar images from the Deutscher Wetterdienst (maps.dwd.de) or RainViewer (rainviewer.com) and map tiles from OpenStreetMap (tile.openstreetmap.org) and stores them on its own server. Only the map section around the weather station and the time are sent. Visitors\' browsers load the images from this website only; no visitor data, IP address or cookie reaches these services.', 'xtx-integration-for-netatmo' ) . '</p>'
        );
    }
}
