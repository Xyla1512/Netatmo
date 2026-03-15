<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array $colors   All current colors (merged with defaults) */
/** @var array $defaults All default colors */
/** @var array $groups   Group definitions from NAWS_Colors */

// Sensor labels for display
$sensor_labels = [
    'temp'     => naws__( 'appearance_sensor_temp' ),
    'humidity' => naws__( 'appearance_sensor_humidity' ),
    'pressure' => naws__( 'appearance_sensor_pressure' ),
    'co2'      => naws__( 'appearance_sensor_co2' ),
    'noise'    => naws__( 'appearance_sensor_noise' ),
    'wind'     => naws__( 'appearance_sensor_wind' ),
    'rain'     => naws__( 'appearance_sensor_rain' ),
    'health'   => naws__( 'appearance_sensor_health' ),
];

$property_labels = [
    'gradient_start' => naws__( 'appearance_gradient_start' ),
    'gradient_end'   => naws__( 'appearance_gradient_end' ),
    'bg'             => naws__( 'appearance_bg' ),
    'text'           => naws__( 'appearance_text' ),
];

// Labels for all non-sensor color keys
$color_labels = [
    // Theme
    'theme_bg'            => naws__( 'appearance_theme_bg' ),
    'theme_surface'       => naws__( 'appearance_theme_surface' ),
    'theme_surface_alt'   => naws__( 'appearance_theme_surface_alt' ),
    'theme_text'          => naws__( 'appearance_theme_text' ),
    'theme_text_dark'     => naws__( 'appearance_theme_text_dark' ),
    'theme_text_darkest'  => naws__( 'appearance_theme_text_darkest' ),
    'theme_text_muted'    => naws__( 'appearance_theme_text_muted' ),
    'theme_text_light'    => naws__( 'appearance_theme_text_light' ),
    'theme_border'        => naws__( 'appearance_theme_border' ),
    'theme_shadow'        => naws__( 'appearance_theme_shadow' ),
    // Accent
    'accent_primary'      => naws__( 'appearance_accent_primary' ),
    'accent_secondary'    => naws__( 'appearance_accent_secondary' ),
    'accent_success'      => naws__( 'appearance_accent_success' ),
    'accent_warning'      => naws__( 'appearance_accent_warning' ),
    'accent_danger'       => naws__( 'appearance_accent_danger' ),
    // Chart 24h
    'chart_temp_outdoor'     => naws__( 'appearance_chart_temp_outdoor' ),
    'chart_humidity_outdoor'  => naws__( 'appearance_chart_humidity_outdoor' ),
    'chart_temp_indoor'      => naws__( 'appearance_chart_temp_indoor' ),
    'chart_pressure'         => naws__( 'appearance_chart_pressure' ),
    'chart_co2'              => naws__( 'appearance_chart_co2' ),
    'chart_noise'            => naws__( 'appearance_chart_noise' ),
    'chart_wind'             => naws__( 'appearance_chart_wind' ),
    'chart_gusts'            => naws__( 'appearance_chart_gusts' ),
    'chart_rain'             => naws__( 'appearance_chart_rain' ),
    'chart_module4_temp'     => naws__( 'appearance_chart_module4_temp' ),
    'chart_module4_humidity' => naws__( 'appearance_chart_module4_humidity' ),
    'chart_module4_co2'      => naws__( 'appearance_chart_module4_co2' ),
    // Chart theme
    'chart_grid'           => naws__( 'appearance_chart_grid' ),
    'chart_tick'           => naws__( 'appearance_chart_tick' ),
    'chart_tooltip_bg'     => naws__( 'appearance_chart_tooltip_bg' ),
    'chart_tooltip_title'  => naws__( 'appearance_chart_tooltip_title' ),
    'chart_tooltip_text'   => naws__( 'appearance_chart_tooltip_text' ),
    'chart_axis_title'     => naws__( 'appearance_chart_axis_title' ),
];
?>

<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title">
        <span class="naws-title-icon">🎨</span>
        <?php naws_e( 'appearance_title' ); ?>
    </h1>

    <?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="notice notice-success is-dismissible"><p><?php naws_e( 'settings_saved' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="notice notice-info is-dismissible"><p><?php naws_e( 'appearance_reset_done' ); ?></p></div>
    <?php endif; ?>

    <!-- Global Reset Button -->
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline; float:right; margin-top:-2.5rem;">
        <?php wp_nonce_field( 'naws_reset_appearance' ); ?>
        <input type="hidden" name="action" value="naws_reset_appearance">
        <button type="submit" class="button" onclick="return confirm('<?php echo esc_js( naws__( 'appearance_reset_confirm' ) ); ?>');">
            <?php naws_e( 'appearance_reset_all' ); ?>
        </button>
    </form>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'naws_save_appearance' ); ?>
        <input type="hidden" name="action" value="naws_save_appearance">

        <!-- ============================================================
             Gruppe 1: Basis-Theme
             ============================================================ -->
        <div class="naws-admin-panel">
            <h2><?php naws_e( 'appearance_group_theme' ); ?></h2>
            <p class="description"><?php naws_e( 'appearance_group_theme_desc' ); ?></p>
            <table class="form-table naws-color-table">
                <tbody>
                <?php foreach ( $groups['theme']['keys'] as $key ) : ?>
                    <tr>
                        <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                        <td>
                            <input type="text"
                                   id="naws-<?php echo esc_attr( $key ); ?>"
                                   name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                   class="naws-color-picker"
                                   data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================
             Gruppe 2: Akzent-Farben
             ============================================================ -->
        <div class="naws-admin-panel">
            <h2><?php naws_e( 'appearance_group_accent' ); ?></h2>
            <table class="form-table naws-color-table">
                <tbody>
                <?php foreach ( $groups['accent']['keys'] as $key ) : ?>
                    <tr>
                        <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                        <td>
                            <input type="text"
                                   id="naws-<?php echo esc_attr( $key ); ?>"
                                   name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                   class="naws-color-picker"
                                   data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================
             Gruppe 3: Sensor-Kachel-Farben
             ============================================================ -->
        <div class="naws-admin-panel">
            <h2><?php naws_e( 'appearance_group_sensors' ); ?></h2>
            <p class="description"><?php naws_e( 'appearance_group_sensors_desc' ); ?></p>

            <?php foreach ( $groups['sensors']['sensors'] as $sensor ) : ?>
                <h3 style="margin-top:1.5em; padding-bottom:0.3em; border-bottom:1px solid #e0eeee;">
                    <?php echo esc_html( $sensor_labels[ $sensor ] ?? ucfirst( $sensor ) ); ?>
                </h3>
                <table class="form-table naws-color-table">
                    <tbody>
                    <?php foreach ( $groups['sensors']['per_sensor'] as $prop ) :
                        $key = $sensor . '_' . $prop;
                    ?>
                        <tr>
                            <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $property_labels[ $prop ] ?? $prop ); ?></label></th>
                            <td>
                                <input type="text"
                                       id="naws-<?php echo esc_attr( $key ); ?>"
                                       name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( $colors[ $key ] ?? '' ); ?>"
                                       class="naws-color-picker"
                                       data-default-color="<?php echo esc_attr( $defaults[ $key ] ?? '' ); ?>"
                                       <?php if ( $prop === 'bg' || $prop === 'text' ) : ?>
                                           placeholder="<?php naws_e( 'appearance_inherit' ); ?>"
                                       <?php endif; ?>>
                                <?php if ( $prop === 'bg' || $prop === 'text' ) : ?>
                                    <p class="description"><?php naws_e( 'appearance_inherit_desc' ); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================
             Gruppe 4: 24h-Chart-Farben
             ============================================================ -->
        <div class="naws-admin-panel">
            <h2><?php naws_e( 'appearance_group_chart_24h' ); ?></h2>
            <p class="description"><?php naws_e( 'appearance_group_chart_24h_desc' ); ?></p>
            <table class="form-table naws-color-table">
                <tbody>
                <?php foreach ( $groups['chart_24h']['keys'] as $key ) : ?>
                    <tr>
                        <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                        <td>
                            <input type="text"
                                   id="naws-<?php echo esc_attr( $key ); ?>"
                                   name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                   class="naws-color-picker"
                                   data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================
             Gruppe 5: Chart-Theming
             ============================================================ -->
        <div class="naws-admin-panel">
            <h2><?php naws_e( 'appearance_group_chart_theme' ); ?></h2>
            <p class="description"><?php naws_e( 'appearance_group_chart_theme_desc' ); ?></p>
            <table class="form-table naws-color-table">
                <tbody>
                <?php foreach ( $groups['chart_theme']['keys'] as $key ) : ?>
                    <tr>
                        <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $color_labels[ $key ] ?? $key ); ?></label></th>
                        <td>
                            <input type="text"
                                   id="naws-<?php echo esc_attr( $key ); ?>"
                                   name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                   class="naws-color-picker"
                                   data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================
             Gruppe 6: Jahresvergleich-Palette
             ============================================================ -->
        <div class="naws-admin-panel">
            <h2><?php naws_e( 'appearance_group_history' ); ?></h2>
            <p class="description"><?php naws_e( 'appearance_group_history_desc' ); ?></p>
            <div class="naws-palette-grid">
                <?php for ( $i = 1; $i <= 15; $i++ ) :
                    $key = "history_year_{$i}";
                ?>
                    <div class="naws-palette-item">
                        <label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( sprintf( naws__( 'appearance_year_n' ), $i ) ); ?></label>
                        <input type="text"
                               id="naws-<?php echo esc_attr( $key ); ?>"
                               name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                               value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                               class="naws-color-picker"
                               data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php naws_e( 'save_settings' ); ?></button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize all color pickers
    $('.naws-color-picker').each(function() {
        var $input = $(this);
        $input.wpColorPicker({
            defaultColor: $input.data('default-color') || '',
            change: function() {},
            clear: function() {}
        });
    });
});
</script>

<style>
.naws-color-table th { width: 220px; }
.naws-color-table td .wp-picker-container { display: inline-block; }
.naws-palette-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem 0;
}
.naws-palette-item label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.3rem;
    color: var(--naws-admin-text, #1d2327);
}
</style>
