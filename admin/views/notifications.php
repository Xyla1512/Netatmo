<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array  $settings    NAWS_Notifications::get_settings() */
/** @var array  $catalog     NAWS_Notify_Rules::catalog() */
/** @var array  $units       NAWS_Notifications::units() */
/** @var array  $rows        NAWS_Notifications::status_rows() */
/** @var array  $log         NAWS_Notifications::get_log() */
/** @var string $admin_email get_option( 'admin_email' ) */

$groups = [
    'station' => __( 'Station', 'xtx-integration-for-netatmo' ),
    'weather' => __( 'Weather', 'xtx-integration-for-netatmo' ),
];
?>
<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title"><span class="naws-title-icon">✉️</span> <?php esc_html_e( 'Notifications', 'xtx-integration-for-netatmo' ); ?></h1>
    <p class="description"><?php esc_html_e( 'After every fetch the plugin checks the rules switched on below and sends one e-mail per state change; all changes of one fetch go into one mail.', 'xtx-integration-for-netatmo' ); ?></p>

    <?php if ( isset( $_GET['updated'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'xtx-integration-for-netatmo' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['dropped'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) ) :
        $dropped = absint( wp_unslash( $_GET['dropped'] ) ); ?>
        <div class="notice notice-warning is-dismissible"><p><?php
            /* translators: %d: number of addresses that were not valid e-mail addresses */
            echo esc_html( sprintf( _n( '%d invalid address was dropped.', '%d invalid addresses were dropped.', $dropped, 'xtx-integration-for-netatmo' ), $dropped ) ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['test'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'naws_notifications_notice' ) ) : ?>
        <?php if ( absint( wp_unslash( $_GET['test'] ) ) === 1 ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test mail sent.', 'xtx-integration-for-netatmo' ); ?></p></div>
        <?php else : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The test mail could not be sent: wp_mail() returned false. The server sends no mail — an SMTP plugin or your host can fix that.', 'xtx-integration-for-netatmo' ); ?></p></div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'naws_save_notifications' ); ?>
        <input type="hidden" name="action" value="naws_save_notifications">

        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2><?php esc_html_e( 'Recipients', 'xtx-integration-for-netatmo' ); ?></h2></div>
            <div style="padding:1rem 1.25rem;">
                <textarea name="naws_notifications[recipients]" rows="3" cols="60" class="large-text code" placeholder="<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_textarea( implode( "\n", $settings['recipients'] ) ); ?></textarea>
                <p class="description"><?php esc_html_e( "One address per line. Leave empty to use the site's admin address.", 'xtx-integration-for-netatmo' ); ?></p>
            </div>
        </div>

        <?php foreach ( $groups as $group => $group_label ) : ?>
        <div class="naws-admin-panel" style="margin-top:1rem;">
            <div class="naws-panel-header"><h2><?php echo esc_html( $group_label ); ?></h2></div>
            <table class="wp-list-table widefat striped naws-list-table">
                <thead>
                    <tr>
                        <th style="width:38%;"><?php esc_html_e( 'Rule', 'xtx-integration-for-netatmo' ); ?></th>
                        <th style="width:22%;"><?php esc_html_e( 'Threshold', 'xtx-integration-for-netatmo' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $catalog as $id => $def ) :
                    if ( $def['group'] !== $group ) continue;
                    $cfg  = $settings['rules'][ $id ];
                    $name = 'naws_notifications[rules][' . $id . ']'; ?>
                    <tr>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="0">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
                                <strong><?php echo esc_html( naws_label( 'ntf_rule_' . $id ) ); ?></strong>
                            </label>
                        </td>
                        <td>
                            <?php if ( $def['param'] === 'threshold' ) :
                                $shown = in_array( $def['kind'], [ 'temp', 'wind', 'rain' ], true )
                                    ? NAWS_Notify_Rules::to_display( $def['kind'], (float) $cfg['threshold'], $units )
                                    : (int) $cfg['threshold'];
                                $step  = $def['kind'] === 'percent' ? '1' : 'any'; ?>
                                <input type="number" step="<?php echo esc_attr( $step ); ?>" name="<?php echo esc_attr( $name ); ?>[threshold]" value="<?php echo esc_attr( $shown ); ?>" class="small-text">
                                <?php echo esc_html( NAWS_Notify_Rules::unit_label( $def['kind'], $units ) ); ?>
                            <?php elseif ( $def['param'] === 'level' ) : ?>
                                <select name="<?php echo esc_attr( $name ); ?>[level]">
                                    <?php foreach ( array_keys( $def['levels'] ) as $level ) : ?>
                                        <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $cfg['level'], $level ); ?>><?php echo esc_html( naws_label( 'ntf_level_' . $level ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ( $def['param'] === 'minutes' ) : ?>
                                <input type="number" step="1" min="10" max="1440" name="<?php echo esc_attr( $name ); ?>[minutes]" value="<?php echo esc_attr( (int) $cfg['minutes'] ); ?>" class="small-text">
                                <?php echo esc_html( NAWS_Notify_Rules::unit_label( 'minutes', $units ) ); ?>
                            <?php elseif ( $id === 'sync_failed' ) : ?>
                                <span class="description"><?php esc_html_e( 'after 3 errors in a row', 'xtx-integration-for-netatmo' ); ?></span>
                            <?php else : ?>
                                <span class="description">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="description"><?php echo esc_html( naws_label( 'ntf_desc_' . $id ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <?php submit_button( __( 'Save Notifications', 'xtx-integration-for-netatmo' ) ); ?>
    </form>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:-0.5rem 0 1.5rem;">
        <?php wp_nonce_field( 'naws_test_notification' ); ?>
        <input type="hidden" name="action" value="naws_test_notification">
        <button type="submit" class="button"><?php esc_html_e( 'Send test mail', 'xtx-integration-for-netatmo' ); ?></button>
        <span class="description" style="margin-left:0.5rem;"><?php esc_html_e( 'The test mail goes to the saved recipients.', 'xtx-integration-for-netatmo' ); ?></span>
    </form>

    <div class="naws-admin-panel">
        <div class="naws-panel-header"><h2><?php esc_html_e( 'Current state', 'xtx-integration-for-netatmo' ); ?></h2></div>
        <?php if ( empty( $rows ) ) : ?>
            <p style="padding:1rem;"><?php esc_html_e( 'No active modules.', 'xtx-integration-for-netatmo' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Rule', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php echo esc_html( naws_label( 'ntf_module' ) ); ?></th>
                    <th><?php esc_html_e( 'State', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'xtx-integration-for-netatmo' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                switch ( $row['status'] ) {
                    case 'active':    $state = sprintf( naws_label( 'ntf_status_active' ), NAWS_Notifications::stamp( (int) $row['since'] ) ); $badge = 'naws-badge-error'; break;
                    case 'pending':   $state = sprintf( naws_label( 'ntf_status_pending' ), NAWS_Notifications::stamp( (int) $row['since'] ) ); $badge = 'naws-badge-warning'; break;
                    case 'suspended': $state = naws_label( 'ntf_status_suspended' ) . ' (' . naws_label( 'ntf_reason_' . $row['reason'] ) . ')'; $badge = ''; break;
                    default:          $state = naws_label( 'ntf_status_ok' ); $badge = 'naws-badge-success';
                } ?>
                <tr>
                    <td><?php echo esc_html( naws_label( 'ntf_rule_' . $row['rule'] ) ); ?></td>
                    <td><?php echo $row['module_name'] !== '' ? esc_html( $row['module_name'] . ' (' . NAWS_Helpers::module_type_label( $row['module_type'] ) . ')' ) : '—'; ?></td>
                    <td><span class="naws-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $state ); ?></span></td>
                    <td><?php echo esc_html( NAWS_Notifications::format_measure( $row['rule'], $row['value'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="naws-admin-panel" style="margin-top:1rem;">
        <div class="naws-panel-header"><h2><?php esc_html_e( 'Recent notifications', 'xtx-integration-for-netatmo' ); ?></h2></div>
        <?php if ( empty( $log ) ) : ?>
            <p style="padding:1rem;"><?php esc_html_e( 'No notifications sent yet.', 'xtx-integration-for-netatmo' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th><?php echo esc_html( _x( 'Time', 'time', 'xtx-integration-for-netatmo' ) ); ?></th>
                    <th><?php esc_html_e( 'Status', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'To', 'xtx-integration-for-netatmo' ); ?></th>
                    <th><?php esc_html_e( 'Subject', 'xtx-integration-for-netatmo' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $log as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( NAWS_Notifications::stamp( (int) ( $entry['time'] ?? 0 ) ) ); ?></td>
                    <td><span class="naws-badge <?php echo esc_attr( ! empty( $entry['sent'] ) ? 'naws-badge-success' : 'naws-badge-error' ); ?>"><?php echo ! empty( $entry['sent'] ) ? esc_html__( 'sent', 'xtx-integration-for-netatmo' ) : esc_html__( 'failed', 'xtx-integration-for-netatmo' ); ?></span></td>
                    <td><?php echo esc_html( implode( ', ', (array) ( $entry['to'] ?? [] ) ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $entry['subject'] ?? '' ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
