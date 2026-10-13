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
$naws_uploads = wp_upload_dir( null, false );
$naws_dir     = untrailingslashit( $naws_uploads['basedir'] ) . '/naws-radar';
if ( WP_Filesystem() && $wp_filesystem ) {
    $wp_filesystem->delete( $naws_dir, true );
} else {
    // No usable filesystem method (e.g. FTP credentials required): the
    // folder is ours inside uploads, so the direct method is safe here.
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    $naws_direct = new WP_Filesystem_Direct( null );
    $naws_direct->delete( $naws_dir, true );
}
delete_option( 'naws_radar_sets' );
foreach ( [ 'dwd', 'rainviewer', 'osm' ] as $naws_source ) {
    delete_transient( 'naws_radar_backoff_' . $naws_source );
}
wp_unschedule_hook( 'naws_radar_refresh' );
