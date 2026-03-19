<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// get_modules(false) = ALL modules including inactive ones
$modules = NAWS_Database::get_modules( false );
?>
<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title">
        <span class="naws-title-icon">📡</span>
        <?php naws_e( 'modules_title' ); ?>
    </h1>

    <div class="naws-admin-panel">
        <div class="naws-panel-header">
            <h2><?php naws_e( 'all_modules' ); ?></h2>
            <p class="description" style="margin:0;">
                <?php naws_e( 'module_active_desc' ); ?>
            </p>
        </div>

        <?php if ( empty( $modules ) ) : ?>
            <div style="padding:2rem 1.25rem;">
                <p><?php naws_e( 'no_modules' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=naws-dashboard' ) ); ?>" class="button button-primary">
                    <?php naws_e( 'go_to_dashboard' ); ?>
                </a>
            </div>
        <?php else : ?>
        <table class="wp-list-table widefat striped naws-list-table">
            <thead>
                <tr>
                    <th style="width:60px;"><?php naws_e( 'active' ); ?></th>
                    <th><?php naws_e( 'name' ); ?></th>
                    <th><?php naws_e( 'type' ); ?></th>
                    <th><?php naws_e( 'module_id_mac' ); ?></th>
                    <th><?php naws_e( 'data_types' ); ?></th>
                    <th><?php naws_e( 'battery' ); ?></th>
                    <th><?php naws_e( 'last_seen' ); ?></th>
                </tr>
            </thead>
            <tbody id="naws-modules-tbody">
                <?php foreach ( $modules as $m ) :
                    $is_active  = (bool) $m['is_active'];
                    $batt_vp    = $m['battery_vp'] ?? null;
                    $batt_pct   = ( $batt_vp && $m['module_type'] !== 'NAMain' )
                                ? max( 0, min( 100, round( ( $batt_vp - 3500 ) / 2500 * 100 ) ) )
                                : null;
                    $row_style  = $is_active ? '' : 'opacity:0.45;';
                ?>
                <tr id="naws-module-row-<?php echo esc_attr( sanitize_html_class( $m['module_id'] ) ); ?>"
                    style="<?php echo esc_attr( $row_style ); ?> transition:opacity 0.3s;">
                    <td>
                        <label class="naws-toggle" title="<?php echo esc_attr( $is_active ? 'Deactivate' : 'Activate' ); ?>">
                            <input type="checkbox"
                                   class="naws-module-toggle"
                                   data-module-id="<?php echo esc_attr( $m['module_id'] ); ?>"
                                   <?php checked( $is_active ); ?>>
                            <span class="naws-toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <strong><?php echo esc_html( $m['module_name'] ); ?></strong>
                        <?php if ( ! $is_active ) : ?>
                            <span class="naws-badge naws-badge-error" style="margin-left:0.4rem;">
                                <?php echo 'Inactive'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="naws-module-type-badge">
                            <?php echo esc_html( NAWS_Helpers::module_type_label( $m['module_type'] ) ); ?>
                        </span>
                    </td>
                    <td><code><?php echo esc_html( $m['module_id'] ); ?></code></td>
                    <td><?php echo esc_html( implode( ', ', $m['data_types'] ) ); ?></td>
                    <td>
                        <?php if ( $batt_pct !== null ) : ?>
                            <div style="display:flex;align-items:center;gap:0.4rem;">
                                <div class="naws-battery-bar">
                                    <div class="naws-battery-fill" style="width:<?php echo esc_attr($batt_pct); ?>%;background:<?php echo esc_attr( $batt_pct < 20 ? '#ef4444' : ($batt_pct < 50 ? '#f59e0b' : '#10b981') ); ?>"></div>
                                </div>
                                <?php echo esc_html( $batt_pct ); ?>%
                            </div>
                        <?php else : ?>
                            <span class="description"><?php echo 'N/A (powered)'; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $m['last_seen']
                            ? esc_html( wp_date('d.m.Y H:i', $m['last_seen'] ) . ' (' . human_time_diff( $m['last_seen'] ) . ' ago)' )
                            : '—'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<style>
/* Toggle switch */
.naws-toggle {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    cursor: pointer;
}
.naws-toggle input { opacity: 0; width: 0; height: 0; }
.naws-toggle-slider {
    position: absolute;
    inset: 0;
    background: #d1d5db;
    border-radius: 24px;
    transition: 0.3s;
}
.naws-toggle-slider::before {
    content: '';
    position: absolute;
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.naws-toggle input:checked + .naws-toggle-slider { background: #10b981; }
.naws-toggle input:checked + .naws-toggle-slider::before { transform: translateX(20px); }
.naws-toggle input:disabled + .naws-toggle-slider { opacity: 0.5; cursor: not-allowed; }
</style>

<script>
(function($){
    $(document).on('change', '.naws-module-toggle', function() {
        const checkbox  = $(this);
        const moduleId  = checkbox.data('module-id');
        const isActive  = checkbox.is(':checked') ? 1 : 0;
        const row       = $('#naws-module-row-' + moduleId.replace(/:/g, '-').replace(/[^a-zA-Z0-9-_]/g, '_'));

        checkbox.prop('disabled', true);

        $.post(ajaxurl, {
            action:    'naws_toggle_module',
            nonce:     '<?php echo esc_attr( wp_create_nonce( 'naws_admin_nonce' ) ); ?>',
            module_id: moduleId,
            is_active: isActive
        }, function(resp) {
            checkbox.prop('disabled', false);
            if (resp.success) {
                row.css('opacity', isActive ? '1' : '0.45');
                // Update inactive badge
                const badge = row.find('.naws-badge-error');
                if (isActive) {
                    badge.remove();
                } else {
                    if (!badge.length) {
                        row.find('strong').after('<span class="naws-badge naws-badge-error" style="margin-left:0.4rem;"><?php echo esc_js( naws__("inactive") ); ?></span>');
                    }
                }
            } else {
                // Revert
                checkbox.prop('checked', !isActive);
                alert('<?php echo esc_js( naws__("toggle_error") ); ?>');
            }
        }).fail(function() {
            checkbox.prop('disabled', false).prop('checked', !isActive);
            alert('<?php echo esc_js( naws__("request_failed") ); ?>');
        });
    });
})(jQuery);
</script>
