<?php
/**
 * GitHub-based auto-updater for XTX Netatmo.
 *
 * Checks GitHub Releases for a newer version and feeds it into
 * the native WordPress plugin update mechanism.
 *
 * Requirements:
 *  - GitHub releases must use tags like "v1.5.0" or "1.5.0".
 *  - The release must have a ZIP asset attached, OR GitHub's
 *    auto-generated source ZIP is used as fallback.
 *  - The repo can be public (no token) or private (token required).
 *
 * @package NAWS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Updater {

    /** GitHub owner/repo. */
    private const REPO = 'Xyla1512/Netatmo';

    /** How long to cache the GitHub response (seconds). */
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /** Transient key for cached release data. */
    private const CACHE_KEY = 'naws_github_release';

    /** @var self|null */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'post_install' ], 10, 3 );
    }

    /**
     * Inject update data into the WordPress update transient.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        $plugin_data = (object) [
            'slug'        => dirname( NAWS_PLUGIN_BASENAME ),
            'plugin'      => NAWS_PLUGIN_BASENAME,
            'new_version' => $remote_version,
            'url'         => $release['html_url'],
            'package'     => $this->get_download_url( $release ),
            'icons'       => [],
            'banners'     => [],
        ];

        if ( version_compare( $remote_version, NAWS_VERSION, '>' ) ) {
            $transient->response[ NAWS_PLUGIN_BASENAME ] = $plugin_data;
        } else {
            // Register in no_update so WordPress shows the "Enable auto-updates" toggle.
            $transient->no_update[ NAWS_PLUGIN_BASENAME ] = $plugin_data;
        }

        return $transient;
    }

    /**
     * Provide rich plugin info for the "View details" popup.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || dirname( NAWS_PLUGIN_BASENAME ) !== $args->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        return (object) [
            'name'            => 'XTX Netatmo',
            'slug'            => dirname( NAWS_PLUGIN_BASENAME ),
            'version'         => $remote_version,
            'author'          => '<a href="https://frank-neumann.de">Frank Neumann</a>',
            'homepage'        => 'https://github.com/' . self::REPO,
            'requires'        => '5.8',
            'tested'          => '6.9.4',
            'requires_php'    => '7.4',
            'download_link'   => $this->get_download_url( $release ),
            'trunk'           => $this->get_download_url( $release ),
            'last_updated'    => $release['published_at'] ?? '',
            'sections'        => [
                'description'  => 'Connects to the Netatmo API, stores all sensor data locally and displays live dashboards, charts, history and forecasts via shortcodes.',
                'changelog'    => nl2br( esc_html( $release['body'] ?? '' ) ),
            ],
        ];
    }

    /**
     * After install: rename the extracted folder to match the expected plugin directory name.
     *
     * GitHub ZIPs extract to "Netatmo-main/" or "Netatmo-v1.5.0/" etc.
     * WordPress expects the folder to stay the same as the current plugin directory.
     */
    public function post_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || NAWS_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
            return $result;
        }

        global $wp_filesystem;

        $plugin_dir  = dirname( NAWS_PLUGIN_DIR ); // .../wp-content/plugins
        $proper_name = dirname( NAWS_PLUGIN_BASENAME ); // e.g. "Netatmo"
        $proper_dest = trailingslashit( $plugin_dir ) . $proper_name;

        // Move from extracted folder to correct name
        if ( $result['destination'] !== $proper_dest ) {
            $wp_filesystem->move( $result['destination'], $proper_dest );
            $result['destination']      = $proper_dest;
            $result['destination_name'] = $proper_name;
            $result['remote_destination'] = $proper_dest;
        }

        // Re-activate after update
        activate_plugin( NAWS_PLUGIN_BASENAME );

        return $result;
    }

    // ── Internal helpers ───────────────────────────────────────────────

    /**
     * Fetch the latest release from GitHub (cached).
     *
     * @return array|null Release data or null on failure.
     */
    private function get_latest_release() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';

        $args = [
            'timeout'    => 10,
            'headers'    => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'NAWS/' . NAWS_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
            ],
        ];

        // Optional: private repo support via token in settings.
        $settings = get_option( 'naws_settings', [] );
        if ( ! empty( $settings['github_token'] ) ) {
            $args['headers']['Authorization'] = 'token ' . $settings['github_token'];
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // Cache failure briefly to avoid hammering GitHub.
            set_transient( self::CACHE_KEY, null, 5 * MINUTE_IN_SECONDS );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['tag_name'] ) ) {
            return null;
        }

        set_transient( self::CACHE_KEY, $body, self::CACHE_TTL );

        return $body;
    }

    /**
     * Get the best download URL from a release.
     *
     * Prefers a .zip asset attached to the release, falls back to the
     * auto-generated source ZIP from GitHub.
     */
    private function get_download_url( $release ) {
        // Check for an explicitly attached .zip asset first.
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( ! empty( $asset['browser_download_url'] ) && str_ends_with( $asset['name'], '.zip' ) ) {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fallback: GitHub auto-generated source ZIP.
        return $release['zipball_url'] ?? '';
    }
}
