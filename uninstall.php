<?php
/**
 * Runs when the plugin is deleted in WordPress (not on deactivation).
 *
 * Removes only what the rain radar stored: its folder in uploads and its
 * options. Weather data and every other setting stay, as before 2.2.0 -
 * deleting them is a decision the plugin does not make for the user.
 *
 * @package NAWS
 * @since   2.2.0
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wp_filesystem;
require_once ABSPATH . 'wp-admin/includes/file.php';
if ( WP_Filesystem() && $wp_filesystem ) {
    $naws_uploads = wp_upload_dir( null, false );
    $wp_filesystem->delete( untrailingslashit( $naws_uploads['basedir'] ) . '/naws-radar', true );
}
delete_option( 'naws_radar_sets' );
foreach ( [ 'dwd', 'rainviewer', 'osm' ] as $naws_source ) {
    delete_transient( 'naws_radar_backoff_' . $naws_source );
}
wp_clear_scheduled_hook( 'naws_radar_refresh' );
