<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Where the rain radar keeps its images: wp-content/uploads/naws-radar/
 * <key>/ with map/ (tiles, kept), frames/ (radar, replaced) and
 * manifest.json. Also the lock against parallel runs, the pause after a
 * failed request, and every kind of clean-up.
 *
 * All file access goes through WP_Filesystem.
 *
 * @package NAWS
 * @since   2.2.0
 */
class NAWS_Radar_Store {

    const DIR        = 'naws-radar';
    const OPTION     = 'naws_radar_sets';
    const LOCK       = 'naws_radar_lock_';
    const BACKOFF    = 'naws_radar_backoff_';
    const SOURCES    = [ 'dwd', 'rainviewer', 'osm' ];
    const LOCK_TTL   = 300;
    const GRACE      = 1800;     // an open player may still show the previous set
    const UNUSED     = 604800;   // 7 days without a page showing the set
    const SEEN_EVERY = 3600;     // write last_seen at most once an hour

    /** @return string[] [ base dir, base url ] without trailing slash */
    private static function base(): array {
        $u = wp_upload_dir( null, false );
        return [ trailingslashit( $u['basedir'] ) . self::DIR, trailingslashit( $u['baseurl'] ) . self::DIR ];
    }

    public static function key( float $lat, float $lon, int $km, string $source ): string {
        return substr( md5( sprintf( '%.4F|%.4F|%d|%s', $lat, $lon, $km, $source ) ), 0, 12 );
    }

    public static function dir( string $key ): string {
        return self::base()[0] . '/' . $key;
    }

    public static function url( string $key, string $rel ): string {
        return self::base()[1] . '/' . $key . '/' . $rel;
    }

    /** A set key is exactly 12 lowercase hex characters (see key()). */
    private static function valid_key( string $key ): bool {
        return 1 === preg_match( '/^[a-f0-9]{12}\z/', $key );
    }

    /** A file inside a set: the manifest, or a png in map/ or frames/. */
    private static function valid_rel( string $rel ): bool {
        return 'manifest.json' === $rel || 1 === preg_match( '/^(?:map|frames)\/[A-Za-z0-9_-]+\.png\z/', $rel );
    }

    /** @return WP_Filesystem_Base|null */
    private static function fs() {
        global $wp_filesystem;
        if ( ! $wp_filesystem && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem ? $wp_filesystem : null;
    }

    public static function sets(): array {
        $s = get_option( self::OPTION, [] );
        return is_array( $s ) ? $s : [];
    }

    /** Note that a page showed this set; at most one option write an hour. */
    public static function remember( string $key, array $params, int $now ): void {
        $sets = self::sets();
        if ( isset( $sets[ $key ] ) && $now - (int) ( $sets[ $key ]['last_seen'] ?? 0 ) < self::SEEN_EVERY ) {
            return;
        }
        $sets[ $key ] = array_merge( $params, [ 'last_seen' => $now ] );
        update_option( self::OPTION, $sets, false );
    }

    public static function manifest( string $key ): ?array {
        $fs   = self::fs();
        $path = self::dir( $key ) . '/manifest.json';
        if ( ! $fs || ! $fs->exists( $path ) ) {
            return null;
        }
        $m = json_decode( (string) $fs->get_contents( $path ), true );
        return is_array( $m ) ? $m : null;
    }

    public static function save_manifest( string $key, array $m ): bool {
        return self::put( $key, 'manifest.json', (string) wp_json_encode( $m ) );
    }

    public static function put( string $key, string $rel, string $bytes ): bool {
        $fs = self::fs();
        if ( ! $fs || ! self::valid_key( $key ) || ! self::valid_rel( $rel ) ) {
            return false;
        }
        $path = self::dir( $key ) . '/' . $rel;
        wp_mkdir_p( dirname( $path ) );
        return (bool) $fs->put_contents( $path, $bytes, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : false );
    }

    public static function has( string $key, string $rel ): bool {
        $fs = self::fs();
        return $fs && self::valid_key( $key ) && self::valid_rel( $rel ) && $fs->exists( self::dir( $key ) . '/' . $rel );
    }

    /** The radar files a manifest refers to (the map is never pruned). */
    public static function files_of( array $m ): array {
        $out = [];
        foreach ( $m['frames'] ?? [] as $f ) {
            if ( isset( $f['file'] ) ) {
                $out[] = $f['file'];
            }
            foreach ( $f['tiles'] ?? [] as $t ) {
                $out[] = $t['file'];
            }
        }
        return $out;
    }

    /**
     * Retire what the new manifest no longer uses and delete what has been
     * retired for GRACE seconds. Returns the new retired list.
     */
    public static function prune( string $key, ?array $old, array $new, int $now ): array {
        $fs      = self::fs();
        $keep    = self::files_of( $new );
        $retired = is_array( $old['retired'] ?? null ) ? $old['retired'] : [];
        foreach ( array_diff( self::files_of( $old ?? [] ), $keep ) as $file ) {
            $retired[ $file ] = $retired[ $file ] ?? $now;
        }
        foreach ( $retired as $file => $since ) {
            if ( ! self::valid_key( $key ) || ! self::valid_rel( (string) $file ) ) {
                unset( $retired[ $file ] ); // never trust names read back from disk
                continue;
            }
            if ( in_array( $file, $keep, true ) ) {
                unset( $retired[ $file ] );
                continue;
            }
            if ( $now - (int) $since >= self::GRACE ) {
                if ( $fs ) {
                    $fs->delete( self::dir( $key ) . '/' . $file );
                }
                unset( $retired[ $file ] );
            }
        }
        return $retired;
    }

    public static function delete_set( string $key ): void {
        if ( ! self::valid_key( $key ) ) {
            return;
        }
        $fs = self::fs();
        if ( $fs ) {
            $fs->delete( self::dir( $key ), true );
        }
        $sets = self::sets();
        unset( $sets[ $key ] );
        update_option( self::OPTION, $sets, false );
    }

    /**
     * Daily: delete sets no page has shown for UNUSED seconds, and folders
     * no set knows. Returns how many folders went.
     *
     * The orphan pass relies on callers remembering a set (remember())
     * before any file of it is written: NAWS_Radar::render() remembers
     * before scheduling, and refresh() refuses keys not in the option.
     */
    public static function cleanup( int $now ): int {
        $gone = 0;
        $sets = self::sets();
        foreach ( $sets as $key => $set ) {
            if ( $now - (int) ( $set['last_seen'] ?? 0 ) >= self::UNUSED ) {
                self::delete_set( (string) $key );
                ++$gone;
            }
        }
        $fs   = self::fs();
        $list = $fs ? $fs->dirlist( self::base()[0] ) : false;
        $sets = self::sets();
        foreach ( is_array( $list ) ? $list : [] as $name => $entry ) {
            if ( 'd' === ( $entry['type'] ?? '' ) && ! isset( $sets[ $name ] ) ) {
                $fs->delete( self::base()[0] . '/' . $name, true );
                ++$gone;
            }
        }
        return $gone;
    }

    /** Everything: folder, list of sets, pauses. */
    public static function clear_all(): void {
        $fs = self::fs();
        if ( $fs ) {
            $fs->delete( self::base()[0], true );
        }
        delete_option( self::OPTION );
        foreach ( self::SOURCES as $source ) {
            delete_transient( self::BACKOFF . $source );
        }
    }

    /** Bytes on disk for one set. */
    public static function usage( string $key ): int {
        $fs   = self::fs();
        $list = $fs ? $fs->dirlist( self::dir( $key ), true, true ) : false;
        return is_array( $list ) ? self::sum( $list ) : 0;
    }

    private static function sum( array $list ): int {
        $bytes = 0;
        foreach ( $list as $e ) {
            $bytes += 'd' === ( $e['type'] ?? '' ) ? self::sum( (array) ( $e['files'] ?? [] ) ) : (int) ( $e['size'] ?? 0 );
        }
        return $bytes;
    }

    public static function lock( string $key ): bool {
        if ( false !== get_transient( self::LOCK . $key ) ) {
            return false;
        }
        set_transient( self::LOCK . $key, 1, self::LOCK_TTL );
        return true;
    }

    public static function unlock( string $key ): void {
        delete_transient( self::LOCK . $key );
    }

    public static function in_backoff( string $source ): bool {
        return false !== get_transient( self::BACKOFF . $source );
    }

    /** Same pause rules as the forecast (NAWS_Forecast::backoff_seconds). */
    public static function start_backoff( string $source, $response ): void {
        $code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $retry   = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'retry-after' );
        $seconds = NAWS_Forecast::backoff_seconds( $code, $retry );
        set_transient(
            self::BACKOFF . $source,
            [
                'code'  => $code,
                'until' => time() + $seconds,
            ],
            $seconds
        );
        NAWS_Logger::warning(
            'radar',
            sprintf( '%s request failed (%s), next attempt in %d minute(s)', $source, $code ? $code : 'no response', (int) ceil( $seconds / 60 ) )
        );
    }
}
