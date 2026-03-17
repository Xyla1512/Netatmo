<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
/* templates/current.php */
if ( ! defined( 'ABSPATH' ) ) exit;
$animate = $atts['animate'] !== 'false';
?>
<div class="naws-wrap">

    <?php if (!empty($atts['title'])) : ?>
    <div class="naws-header">
        <h2 class="naws-header-title"><?php echo esc_html($atts['title']); ?></h2>
    </div>
    <?php endif; ?>

    <?php foreach ($readings_by_module as $mod_id => $readings) :
        $module = $module_map[$mod_id] ?? null;
        if (!$module && !empty($atts['module_id'])) continue;
    ?>
        <?php if (!empty($module)) : ?>
        <div class="naws-module-header">
            <span class="naws-module-badge"><?php echo esc_html(NAWS_Helpers::module_type_label($module['module_type'] ?? '')); ?></span>
            <h3 class="naws-module-name"><?php echo esc_html($module['module_name']); ?></h3>
        </div>
        <?php endif; ?>

        <div class="naws-grid <?php echo $atts['layout'] === 'list' ? 'naws-grid-list' : 'naws-grid-3'; ?>">
            <?php foreach ($readings as $param => $data) : ?>
            <div class="naws-card <?php echo esc_attr($data['css_class']); ?>">
                <span class="naws-card-icon"><?php echo esc_html($data['icon']); ?></span>
                <div class="naws-card-label"><?php echo esc_html($data['label']); ?></div>
                <div class="naws-card-value">
                    <?php if ($animate) : ?>
                        <span class="naws-count-up" data-value="<?php echo esc_attr($data['value']); ?>">0</span>
                    <?php else : ?>
                        <span><?php echo esc_html($data['value']); ?></span>
                    <?php endif; ?>
                    <span class="naws-card-unit"><?php echo esc_html($data['unit']); ?></span>
                </div>
                <?php if ($param === 'CO2') :
                    $co2l = NAWS_Helpers::get_co2_level($data['raw']);
                ?>
                    <span class="naws-co2-indicator naws-co2-<?php echo esc_attr($co2l['level']); ?>">
                        ● <?php echo esc_html($co2l['label']); ?>
                    </span>
                <?php endif; ?>
                <div class="naws-card-meta">
                    <?php echo esc_html(human_time_diff($data['time']) . ' ago'); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($readings_by_module)) : ?>
        <p style="color:var(--naws-text-muted)"><?php echo 'No data available yet.'; ?></p>
    <?php endif; ?>
</div>
