<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array $colors   All current colors (merged with defaults) */
/** @var array $defaults All default colors */
/** @var array $groups   Group definitions from NAWS_Colors */

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
    'theme_compass_needle'=> naws__( 'appearance_theme_compass_needle' ),
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

// Short labels for 24h chart preview legend
$chart_short_labels = [
    'chart_temp_outdoor'     => 'Temp Outdoor',
    'chart_humidity_outdoor' => 'Humidity',
    'chart_temp_indoor'      => 'Temp Indoor',
    'chart_pressure'         => 'Pressure',
    'chart_co2'              => 'CO2',
    'chart_noise'            => 'Noise',
    'chart_wind'             => 'Wind',
    'chart_gusts'            => 'Gusts',
    'chart_rain'             => 'Rain',
    'chart_module4_temp'     => 'Module4 Temp',
    'chart_module4_humidity' => 'Module4 Hum.',
    'chart_module4_co2'      => 'Module4 CO2',
];

// Tab definitions
$tabs = [
    'theme'     => naws__( 'appearance_group_theme' ),
    'icons'     => naws__( 'appearance_group_icons' ),
    'chart24h'  => naws__( 'appearance_group_chart_24h' ),
    'charttheme'=> naws__( 'appearance_group_chart_theme' ),
    'history'   => naws__( 'appearance_group_history' ),
];

// Icon sets data
$icon_sets     = NAWS_Icons::get_all_sets();
$current_set   = NAWS_Icons::get_current_set();
$icon_color_keys = [
    'icon_color_temp'  => naws__( 'appearance_sensor_temp' ),
    'icon_color_humid' => naws__( 'appearance_sensor_humidity' ),
    'icon_color_press' => naws__( 'appearance_sensor_pressure' ),
    'icon_color_wind'  => naws__( 'appearance_sensor_wind' ),
    'icon_color_rain'  => naws__( 'appearance_sensor_rain' ),
    'icon_color_co2'   => naws__( 'appearance_sensor_co2' ),
    'icon_color_noise' => naws__( 'appearance_sensor_noise' ),
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

        <!-- ── Tab Navigation ── -->
        <div class="naws-appearance-tabs">
            <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                <button type="button" class="naws-appearance-tab<?php echo $tab_id === 'theme' ? ' active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================
             Tab 1: Basis-Theme
             ============================================================ -->
        <div class="naws-appearance-pane active" data-pane="theme">
            <p class="description"><?php naws_e( 'appearance_group_theme_desc' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
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
                                           data-preview="theme"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label">Live-Vorschau</div>
                    <div id="naws-preview-theme" class="naws-preview-theme-box"
                         style="background:<?php echo esc_attr( $colors['theme_bg'] ); ?>; border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>; box-shadow: 0 2px 10px <?php echo esc_attr( $colors['theme_shadow'] ); ?>;">

                        <!-- Temperatur-Karte (identisch mit Frontend .naws-card) -->
                        <div class="naws-pv-fcard" style="background:<?php echo esc_attr( $colors['theme_surface'] ); ?>; border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>;">
                            <div class="naws-pv-fcard-accent"></div>
                            <div class="naws-pv-fcard-icon" style="background:rgba(80,168,130,0.13);">
                                <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="#50a882" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
                            </div>
                            <div class="naws-pv-fcard-lbl" style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">TEMPERATUR<br><span style="font-size:8px;letter-spacing:.08em;opacity:.8;">OUTDOOR</span></div>
                            <div class="naws-pv-fcard-val" style="color:<?php echo esc_attr( $colors['theme_text_dark'] ); ?>;">21.3</div>
                            <div class="naws-pv-fcard-unit" style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">°C</div>
                            <div class="naws-pv-fcard-subs">
                                <div class="naws-pv-fcard-sub">
                                    <div class="naws-pv-fcard-sub-lbl" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">Min</div>
                                    <div class="naws-pv-fcard-sub-val" style="color:<?php echo esc_attr( $colors['theme_text'] ); ?>;">14.2 <span style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">°C</span></div>
                                </div>
                                <div class="naws-pv-fcard-sub">
                                    <div class="naws-pv-fcard-sub-lbl" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">Max</div>
                                    <div class="naws-pv-fcard-sub-val" style="color:<?php echo esc_attr( $colors['theme_text'] ); ?>;">24.8 <span style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">°C</span></div>
                                </div>
                            </div>
                            <div class="naws-pv-fcard-time" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">vor 2 Min.</div>
                        </div>

                        <!-- Windrose (echte SVG wie im Frontend) -->
                        <div class="naws-pv-wind-section" style="background:<?php echo esc_attr( $colors['theme_surface'] ); ?>; border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>;">
                            <div class="naws-pv-card-label" style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">💨 WIND</div>
                            <div class="naws-pv-wind-row">
                                <div class="naws-pv-rose-wrap">
                                    <svg class="naws-pv-rose-bg" viewBox="-4 -4 168 168" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="80" cy="80" r="72" fill="<?php echo esc_attr( $colors['theme_surface_alt'] ); ?>" stroke="<?php echo esc_attr( $colors['theme_border'] ); ?>" stroke-width="1.5"/>
                                        <circle cx="80" cy="80" r="54" fill="none" stroke="<?php echo esc_attr( $colors['theme_border'] ); ?>" stroke-width="1"/>
                                        <circle cx="80" cy="80" r="34" fill="none" stroke="<?php echo esc_attr( $colors['theme_border'] ); ?>" stroke-width="1" stroke-dasharray="3 4"/>
                                        <polygon points="80,8 88,80 80,92 72,80" fill="<?php echo esc_attr( $colors['theme_text'] ); ?>"/>
                                        <polygon points="80,8 80,92 88,80" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="80,152 72,80 80,68 88,80" fill="<?php echo esc_attr( $colors['theme_text'] ); ?>"/>
                                        <polygon points="80,152 80,68 72,80" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="152,80 80,72 68,80 80,88" fill="<?php echo esc_attr( $colors['theme_text'] ); ?>"/>
                                        <polygon points="152,80 68,80 80,88" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="8,80 80,88 92,80 80,72" fill="<?php echo esc_attr( $colors['theme_text'] ); ?>"/>
                                        <polygon points="8,80 92,80 80,72" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="129,31 76,76 80,80" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>"/>
                                        <polygon points="129,31 84,84 80,80" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="129,129 84,76 80,80" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>"/>
                                        <polygon points="129,129 76,84 80,80" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="31,129 84,84 80,80" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>"/>
                                        <polygon points="31,129 76,76 80,80" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <polygon points="31,31 76,84 80,80" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>"/>
                                        <polygon points="31,31 84,76 80,80" fill="<?php echo esc_attr( $colors['theme_border'] ); ?>"/>
                                        <circle cx="80" cy="80" r="9" fill="<?php echo esc_attr( $colors['theme_text'] ); ?>" stroke="<?php echo esc_attr( $colors['theme_surface'] ); ?>" stroke-width="2.5"/>
                                        <text x="80" y="9" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="<?php echo esc_attr( $colors['theme_text_darkest'] ); ?>">N</text>
                                        <text x="80" y="153" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="<?php echo esc_attr( $colors['theme_text_darkest'] ); ?>">S</text>
                                        <text x="153" y="80" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="<?php echo esc_attr( $colors['theme_text_darkest'] ); ?>">E</text>
                                        <text x="7" y="80" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="<?php echo esc_attr( $colors['theme_text_darkest'] ); ?>">W</text>
                                        <text x="133" y="27" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>">NE</text>
                                        <text x="133" y="136" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>">SE</text>
                                        <text x="27" y="136" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>">SW</text>
                                        <text x="27" y="27" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>">NW</text>
                                    </svg>
                                    <svg class="naws-pv-rose-arrow" viewBox="-4 -4 168 168" xmlns="http://www.w3.org/2000/svg" style="transform:rotate(225deg);">
                                        <polygon points="80,18 87,38 80,32 73,38" fill="<?php echo esc_attr( $colors['theme_compass_needle'] ); ?>"/>
                                        <line x1="80" y1="32" x2="80" y2="88" stroke="<?php echo esc_attr( $colors['theme_compass_needle'] ); ?>" stroke-width="5" stroke-linecap="round"/>
                                        <line x1="80" y1="88" x2="80" y2="106" stroke="<?php echo esc_attr( $colors['theme_text_muted'] ); ?>" stroke-width="3" stroke-linecap="round" opacity=".4"/>
                                    </svg>
                                </div>
                                <div class="naws-pv-wind-info">
                                    <div style="color:<?php echo esc_attr( $colors['theme_text_darkest'] ); ?>; font-size:1.3rem; font-weight:800; font-style:italic;">12 <span style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>; font-size:0.7rem; font-weight:400;">km/h</span></div>
                                    <div style="color:<?php echo esc_attr( $colors['theme_text_dark'] ); ?>; font-size:0.72rem; font-weight:700; font-style:italic; margin-top:2px;">225° · SW</div>
                                    <div style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>; font-size:0.62rem; margin-top:4px;">Böen: 18 km/h</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             Tab 2: Icon-Sets & Icon-Farben
             ============================================================ -->
        <div class="naws-appearance-pane" data-pane="icons">
            <p class="description"><?php naws_e( 'appearance_group_icons_desc' ); ?></p>

            <h3 style="margin:0 0 0.75rem;"><?php naws_e( 'appearance_icon_set_label' ); ?></h3>
            <div class="naws-icon-set-grid">
                <?php foreach ( $icon_sets as $set_key => $set_data ) : ?>
                <label class="naws-icon-set-card<?php echo $set_key === $current_set ? ' active' : ''; ?>">
                    <input type="radio" name="naws_appearance[icon_set]" value="<?php echo esc_attr( $set_key ); ?>"
                        <?php checked( $set_key, $current_set ); ?>>
                    <div class="naws-icon-set-header">
                        <span class="naws-icon-set-name"><?php echo esc_html( $set_data['label'] ); ?></span>
                    </div>
                    <div class="naws-icon-set-preview">
                        <?php foreach ( $set_data['icons'] as $ico_key => $ico_svg ) : ?>
                        <div class="naws-icon-set-ico" title="<?php echo esc_attr( $ico_key ); ?>">
                            <?php echo naws_kses_svg( $ico_svg ); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="naws-icon-set-desc"><?php echo esc_html( $set_data['desc'] ); ?></div>
                </label>
                <?php endforeach; ?>
            </div>

            <h3 style="margin:1.5rem 0 0.5rem;"><?php naws_e( 'appearance_icon_colors_label' ); ?></h3>
            <p class="description" style="margin-bottom:0.75rem;"><?php naws_e( 'appearance_icon_colors_desc' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
                    <table class="form-table naws-color-table">
                        <tbody>
                        <?php foreach ( $icon_color_keys as $key => $label ) : ?>
                            <tr>
                                <th><label for="naws-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                                <td>
                                    <input type="text"
                                           id="naws-<?php echo esc_attr( $key ); ?>"
                                           name="naws_appearance[<?php echo esc_attr( $key ); ?>]"
                                           value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                           class="naws-color-picker"
                                           data-preview="icons"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label"><?php naws_e( 'appearance_icon_preview' ); ?></div>
                    <div id="naws-preview-icons" class="naws-pv-icon-preview">
                        <?php
                        $preview_icons = NAWS_Icons::get_set( 'outline' );
                        foreach ( $icon_color_keys as $key => $label ) :
                            $sensor = str_replace( 'icon_color_', '', $key );
                        ?>
                        <div class="naws-pv-icon-item" data-key="<?php echo esc_attr( $key ); ?>" data-sensor="<?php echo esc_attr( $sensor ); ?>">
                            <div class="naws-pv-icon-circle" style="background:color-mix(in srgb, <?php echo esc_attr( $colors[ $key ] ); ?> 13%, white);">
                                <div class="naws-pv-icon-svg" style="color:<?php echo esc_attr( $colors[ $key ] ); ?>;">
                                    <?php echo $preview_icons[ $sensor ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            </div>
                            <span class="naws-pv-icon-label"><?php echo esc_html( $label ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             Tab 3: 24h-Chart-Farben
             ============================================================ -->
        <div class="naws-appearance-pane" data-pane="chart24h">
            <p class="description"><?php naws_e( 'appearance_group_chart_24h_desc' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
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
                                           data-preview="chart24h"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label">Live-Vorschau — Chart-Linienfarben</div>
                    <div id="naws-preview-chart24h" class="naws-pv-chart-lines">
                        <?php foreach ( $groups['chart_24h']['keys'] as $key ) : ?>
                        <div class="naws-pv-chart-line-item" data-key="<?php echo esc_attr( $key ); ?>">
                            <svg width="60" height="24" viewBox="0 0 60 24" style="flex-shrink:0;">
                                <defs>
                                    <linearGradient id="grad-<?php echo esc_attr( $key ); ?>" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="<?php echo esc_attr( $colors[ $key ] ); ?>" stop-opacity="0.3"/>
                                        <stop offset="100%" stop-color="<?php echo esc_attr( $colors[ $key ] ); ?>" stop-opacity="0.02"/>
                                    </linearGradient>
                                </defs>
                                <path class="naws-pv-chart-fill" d="M0,18 Q10,12 20,14 T40,8 T60,10 L60,24 L0,24 Z" fill="url(#grad-<?php echo esc_attr( $key ); ?>)"/>
                                <path class="naws-pv-chart-stroke" d="M0,18 Q10,12 20,14 T40,8 T60,10" fill="none" stroke="<?php echo esc_attr( $colors[ $key ] ); ?>" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <span class="naws-pv-chart-line-label"><?php echo esc_html( $chart_short_labels[ $key ] ?? $key ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             Tab 3: Chart-Theming
             ============================================================ -->
        <div class="naws-appearance-pane" data-pane="charttheme">
            <p class="description"><?php naws_e( 'appearance_group_chart_theme_desc' ); ?></p>
            <div class="naws-appearance-row">
                <div class="naws-appearance-controls">
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
                                           data-preview="charttheme"
                                           data-key="<?php echo esc_attr( $key ); ?>"
                                           data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="naws-appearance-preview naws-preview-sticky">
                    <div class="naws-preview-label">Live-Vorschau — Chart-Theming</div>
                    <div id="naws-preview-charttheme" class="naws-pv-chart-theme">
                        <svg width="100%" height="180" viewBox="0 0 300 180" preserveAspectRatio="xMidYMid meet">
                            <line class="pv-grid" x1="40" y1="20" x2="280" y2="20" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="55" x2="280" y2="55" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="90" x2="280" y2="90" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="125" x2="280" y2="125" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="155" x2="280" y2="155" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <text class="pv-tick" x="36" y="24" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">30°</text>
                            <text class="pv-tick" x="36" y="59" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">25°</text>
                            <text class="pv-tick" x="36" y="94" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">20°</text>
                            <text class="pv-tick" x="36" y="129" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">15°</text>
                            <text class="pv-tick" x="80" y="170" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">06:00</text>
                            <text class="pv-tick" x="160" y="170" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">12:00</text>
                            <text class="pv-tick" x="240" y="170" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">18:00</text>
                            <text class="pv-axis-title" x="8" y="90" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_axis_title'] ); ?>" transform="rotate(-90 8 90)">°C</text>
                            <path d="M50,100 Q90,60 130,80 T210,45 T270,65" fill="none" stroke="#50a882" stroke-width="2.5" stroke-linecap="round"/>
                            <rect class="pv-tooltip-bg" x="150" y="28" width="80" height="42" rx="6" fill="<?php echo esc_attr( $colors['chart_tooltip_bg'] ); ?>"/>
                            <text class="pv-tooltip-title" x="160" y="43" font-size="9" fill="<?php echo esc_attr( $colors['chart_tooltip_title'] ); ?>">14:00</text>
                            <text class="pv-tooltip-text" x="160" y="60" font-size="12" font-weight="bold" fill="<?php echo esc_attr( $colors['chart_tooltip_text'] ); ?>">22.4 °C</text>
                        </svg>
                        <div class="naws-pv-chart-theme-legend">
                            <span><span class="naws-pv-dot" style="background:<?php echo esc_attr( $colors['chart_grid'] ); ?>;"></span> Gitterlinien</span>
                            <span><span class="naws-pv-dot" style="background:<?php echo esc_attr( $colors['chart_tick'] ); ?>;"></span> Achsen-Labels</span>
                            <span><span class="naws-pv-dot" style="background:<?php echo esc_attr( $colors['chart_axis_title'] ); ?>;"></span> Achsentitel</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             Tab 4: Jahresvergleich-Palette
             ============================================================ -->
        <div class="naws-appearance-pane" data-pane="history">
            <p class="description"><?php naws_e( 'appearance_group_history_desc' ); ?></p>

            <div class="naws-preview-label">Live-Vorschau — Jahresvergleich</div>
            <div id="naws-preview-history" class="naws-pv-history-bars">
                <?php for ( $i = 1; $i <= 15; $i++ ) :
                    $key = "history_year_{$i}";
                    $width = max(25, wp_rand(40, 95));
                ?>
                <div class="naws-pv-history-row">
                    <span class="naws-pv-history-label"><?php echo esc_html( 2024 - $i + 1 ); ?></span>
                    <div class="naws-pv-history-bar" id="naws-pv-bar-<?php echo esc_attr( $key ); ?>" style="width:<?php echo esc_attr( $width ); ?>%; background:<?php echo esc_attr( $colors[ $key ] ); ?>;"></div>
                </div>
                <?php endfor; ?>
            </div>

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
                               data-preview="history"
                               data-key="<?php echo esc_attr( $key ); ?>"
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

<?php
ob_start();
?>
jQuery(document).ready(function($) {
    // ── Tab switching ──
    $('.naws-appearance-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.naws-appearance-tab').removeClass('active');
        $(this).addClass('active');
        $('.naws-appearance-pane').removeClass('active');
        $('.naws-appearance-pane[data-pane="'+tab+'"]').addClass('active');
        // Re-init color pickers in newly visible pane (WP Color Picker needs visible container)
        var pane = $('.naws-appearance-pane[data-pane="'+tab+'"]');
        pane.find('.naws-color-picker').each(function() {
            var $inp = $(this);
            if (!$inp.closest('.wp-picker-container').length) {
                $inp.wpColorPicker({
                    defaultColor: $inp.data('default-color') || '',
                    change: function(event, ui) {
                        var self = $(this);
                        setTimeout(function(){ updatePreview(self); }, 10);
                    },
                    clear: function() {
                        var self = $(this);
                        setTimeout(function(){ updatePreview(self); }, 10);
                    }
                });
            }
        });
    });

    // ── Initialize all color pickers with live preview callback ──
    function getPickerVal($input) {
        return $input.wpColorPicker('color') || $input.val() || $input.data('default-color') || '';
    }

    function updatePreview($input) {
        var key = $input.data('key');
        var group = $input.data('preview');
        var val = getPickerVal($input);
        if (!val) return;

        // ── Theme preview ──
        if (group === 'theme') {
            var box = $('#naws-preview-theme');
            var roseBg = box.find('.naws-pv-rose-bg');
            var roseArr = box.find('.naws-pv-rose-arrow');
            var fc = box.find('.naws-pv-fcard');
            var map = {
                'theme_bg':           function(v){ box.css('background', v); },
                'theme_surface':      function(v){
                    fc.css('background', v);
                    box.find('.naws-pv-wind-section').css('background', v);
                    roseBg.find('circle[stroke-width="2.5"]').attr('stroke', v);
                },
                'theme_surface_alt':  function(v){
                    roseBg.find('circle').first().attr('fill', v);
                },
                'theme_border':       function(v){
                    box.css('border-color', v);
                    fc.css('border-color', v);
                    box.find('.naws-pv-wind-section').css('border-color', v);
                    roseBg.find('circle').first().attr('stroke', v);
                    roseBg.find('circle').eq(1).attr('stroke', v);
                    roseBg.find('circle').eq(2).attr('stroke', v);
                    roseBg.find('polygon').each(function(i){ if(i % 2 === 1) $(this).attr('fill', v); });
                },
                'theme_text':         function(v){
                    fc.find('.naws-pv-fcard-sub-val').css('color', v);
                    roseBg.find('polygon').each(function(i){ if(i % 2 === 0 && i < 8) $(this).attr('fill', v); });
                    roseBg.find('circle[stroke-width="2.5"]').attr('fill', v);
                },
                'theme_text_dark':    function(v){
                    fc.find('.naws-pv-fcard-val').css('color', v);
                    box.find('.naws-pv-wind-info div').eq(1).css('color', v);
                },
                'theme_text_darkest': function(v){
                    box.find('.naws-pv-wind-info div').first().css('color', v);
                    roseBg.find('text[font-size="13"]').attr('fill', v);
                },
                'theme_text_muted':   function(v){
                    fc.find('.naws-pv-fcard-lbl, .naws-pv-fcard-unit').css('color', v);
                    fc.find('.naws-pv-fcard-sub-val span').css('color', v);
                    box.find('.naws-pv-card-label').css('color', v);
                    box.find('.naws-pv-wind-info span').css('color', v);
                    roseBg.find('polygon').each(function(i){ if(i % 2 === 0 && i >= 8) $(this).attr('fill', v); });
                    roseBg.find('text[font-size="10"]').attr('fill', v);
                    roseArr.find('line').last().attr('stroke', v);
                },
                'theme_text_light':   function(v){
                    fc.find('.naws-pv-fcard-time, .naws-pv-fcard-sub-lbl').css('color', v);
                    box.find('.naws-pv-wind-info div').eq(2).css('color', v);
                },
                'theme_shadow':       function(v){ box.css('box-shadow', '0 2px 10px ' + v); },
                'theme_compass_needle': function(v){
                    roseArr.find('polygon').attr('fill', v);
                    roseArr.find('line').first().attr('stroke', v);
                },
            };
            if (map[key]) map[key](val);
        }

        // ── 24h Chart line colors preview ──
        if (group === 'chart24h') {
            var item = $('.naws-pv-chart-line-item[data-key="'+key+'"]');
            item.find('.naws-pv-chart-stroke').attr('stroke', val);
            item.find('.naws-pv-chart-fill stop').attr('stop-color', val);
        }

        // ── Chart theme preview ──
        if (group === 'charttheme') {
            var svg = $('#naws-preview-charttheme svg');
            if (key === 'chart_grid')          svg.find('.pv-grid').attr('stroke', val);
            if (key === 'chart_tick')          svg.find('.pv-tick').attr('fill', val);
            if (key === 'chart_axis_title')    svg.find('.pv-axis-title').attr('fill', val);
            if (key === 'chart_tooltip_bg')    svg.find('.pv-tooltip-bg').attr('fill', val);
            if (key === 'chart_tooltip_title') svg.find('.pv-tooltip-title').attr('fill', val);
            if (key === 'chart_tooltip_text')  svg.find('.pv-tooltip-text').attr('fill', val);
            var legend = $('#naws-preview-charttheme .naws-pv-chart-theme-legend');
            if (key === 'chart_grid')       legend.find('.naws-pv-dot').eq(0).css('background', val);
            if (key === 'chart_tick')        legend.find('.naws-pv-dot').eq(1).css('background', val);
            if (key === 'chart_axis_title') legend.find('.naws-pv-dot').eq(2).css('background', val);
        }

        // ── Icon colors preview ──
        if (group === 'icons') {
            var item = $('.naws-pv-icon-item[data-key="'+key+'"]');
            item.find('.naws-pv-icon-svg').css('color', val);
            item.find('.naws-pv-icon-circle').css('background', 'color-mix(in srgb, ' + val + ' 13%, white)');
            item.find('svg').css('stroke', val);
        }

        // ── History year bars preview ──
        if (group === 'history') {
            $('#naws-pv-bar-' + key).css('background', val);
        }
    }

    // ── Icon set card selection ──
    $('.naws-icon-set-card input[type=radio]').on('change', function() {
        $('.naws-icon-set-card').removeClass('active');
        $(this).closest('.naws-icon-set-card').addClass('active');
    });

    // Init color pickers only in the active/visible pane first
    $('.naws-appearance-pane.active .naws-color-picker').each(function() {
        var $input = $(this);
        $input.wpColorPicker({
            defaultColor: $input.data('default-color') || '',
            change: function(event, ui) {
                var self = $(this);
                setTimeout(function(){ updatePreview(self); }, 10);
            },
            clear: function() {
                var self = $(this);
                setTimeout(function(){ updatePreview(self); }, 10);
            }
        });
    });
});
<?php
wp_add_inline_script( 'naws-admin', ob_get_clean() );
?>

<?php // Styles moved to assets/css/admin.css ?>
