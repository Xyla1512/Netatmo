<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title"><span class="naws-title-icon">📋</span> <?php naws_e( 'cron_log_title' ); ?></h1>
    <div class="naws-admin-panel">
        <div class="naws-panel-header">
            <h2><?php naws_e( 'recent_sync' ); ?></h2>
            <div>
                <strong><?php naws_e( 'next_run' ); ?></strong>
                <?php echo $next_run ? esc_html( wp_date( 'Y-m-d H:i:s', $next_run ) . ' (' . human_time_diff( $next_run ) . ')' ) : '—'; ?>
            </div>
        </div>

        <?php if ( empty( $log ) ) : ?>
            <p style="padding:1rem;"><?php naws_e( 'no_log_entries' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th><?php naws_e( 'time' ); ?></th>
                    <th><?php naws_e( 'status' ); ?></th>
                    <th><?php naws_e( 'message' ); ?></th>
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
    </div>

    <div class="naws-admin-panel" style="margin-top:1rem;">
        <div class="naws-panel-header">
            <h2><?php naws_e( 'cron_daily_title' ); ?></h2>
        </div>
        <p style="padding:0 1.25rem 1rem;"><?php naws_e( 'cron_daily_desc' ); ?></p>
    </div>
</div>
