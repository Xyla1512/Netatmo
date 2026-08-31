<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title"><span class="naws-title-icon">📋</span> <?php esc_html_e( 'Cron Log', 'xtx-integration-for-netatmo' ); ?></h1>
    <div class="naws-admin-panel">
        <div class="naws-panel-header">
            <h2><?php esc_html_e( 'Recent Sync Events', 'xtx-integration-for-netatmo' ); ?></h2>
            <div>
                <strong><?php esc_html_e( 'Next run:', 'xtx-integration-for-netatmo' ); ?></strong>
                <?php echo $next_run ? esc_html( wp_date( 'Y-m-d H:i:s', $next_run ) . ' (' . human_time_diff( $next_run ) . ')' ) : '—'; ?>
            </div>
        </div>

        <?php if ( empty( $log ) ) : ?>
            <p style="padding:1rem;"><?php esc_html_e( 'No log entries yet.', 'xtx-integration-for-netatmo' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th><?php echo esc_html( _x( 'Time', 'time', 'xtx-integration-for-netatmo' ) ); ?></th>
                    <th><?php esc_html_e( 'Status', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'xtx-integration-for-netatmo' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $log as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['time'] ) ); ?></td>
                    <td>
                        <span class="naws-badge <?php echo esc_attr( $entry['status'] === 'ok' ? 'naws-badge-success' : 'naws-badge-error' ); ?>">
                            <?php echo esc_html( $entry['status'] ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $entry['message'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php // Explains gaps in the table above. Raw output: the text carries <code> markup. ?>
        <p class="description" style="padding:0 1.25rem 1rem;"><?php echo wp_kses_post( __( 'WP-Cron is triggered by page views, not by a clock: if nobody opens the site, no fetch happens — during quiet nights possibly for hours. Night mode then has nothing left to slow down, and a gap appears in the readings; the daily summary retrieves the missing values from the Netatmo API later. The schedule only becomes reliable once <code>define( \'DISABLE_WP_CRON\', true );</code> is set in <code>wp-config.php</code> and a real server cron calls <code>wp-cron.php</code> at the interval you want.', 'xtx-integration-for-netatmo' ) ); ?></p>
    </div>

    <div class="naws-admin-panel" style="margin-top:1rem;">
        <div class="naws-panel-header">
            <h2><?php esc_html_e( 'Daily Evaluation', 'xtx-integration-for-netatmo' ); ?></h2>
        </div>
        <p style="padding:0 1.25rem 1rem;"><?php esc_html_e( 'The daily cron runs at 00:01 and computes daily summaries for temperature, pressure and precipitation.', 'xtx-integration-for-netatmo' ); ?></p>
    </div>
</div>
