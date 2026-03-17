<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
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
            <div class="naws-panel-header"><h2><?php naws_e( 'appearance_group_theme' ); ?></h2></div>
            <div class="naws-panel-body">
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

                        <!-- Sensor-Kachel: Temperatur -->
                        <div class="naws-pv-card" style="background:<?php echo esc_attr( $colors['theme_surface'] ); ?>; border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>;">
                            <div class="naws-pv-card-label" style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">🌡️ TEMPERATUR</div>
                            <div class="naws-pv-card-value" style="color:<?php echo esc_attr( $colors['theme_text'] ); ?>;">21.3 <span style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">°C</span></div>
                            <div class="naws-pv-card-meta" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">Außensensor · vor 2 Min.</div>
                        </div>

                        <!-- Sensor-Kachel: Luftdruck -->
                        <div class="naws-pv-card" style="background:<?php echo esc_attr( $colors['theme_surface_alt'] ); ?>; border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>;">
                            <div class="naws-pv-card-label" style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">📊 LUFTDRUCK</div>
                            <div class="naws-pv-card-value" style="color:<?php echo esc_attr( $colors['theme_text'] ); ?>;">1018 <span style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">hPa</span></div>
                            <div class="naws-pv-card-sub" style="color:<?php echo esc_attr( $colors['theme_text_dark'] ); ?>;">Stabil ↔</div>
                        </div>

                        <!-- Windrose -->
                        <div class="naws-pv-wind-section" style="background:<?php echo esc_attr( $colors['theme_surface'] ); ?>; border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>;">
                            <div class="naws-pv-card-label" style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;">💨 WIND</div>
                            <div class="naws-pv-wind-row">
                                <div class="naws-pv-compass" style="border-color:<?php echo esc_attr( $colors['theme_border'] ); ?>;">
                                    <span class="naws-pv-compass-dir naws-pv-dir-n" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">N</span>
                                    <span class="naws-pv-compass-dir naws-pv-dir-e" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">O</span>
                                    <span class="naws-pv-compass-dir naws-pv-dir-s" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">S</span>
                                    <span class="naws-pv-compass-dir naws-pv-dir-w" style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>;">W</span>
                                    <div class="naws-pv-needle" style="transform: translate(-50%, -100%) rotate(225deg);">
                                        <div class="naws-pv-needle-top" style="background:<?php echo esc_attr( $colors['theme_compass_needle'] ); ?>;"></div>
                                        <div class="naws-pv-needle-bottom" style="background:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;"></div>
                                    </div>
                                    <div class="naws-pv-compass-dot" style="background:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>;"></div>
                                </div>
                                <div class="naws-pv-wind-info">
                                    <div style="color:<?php echo esc_attr( $colors['theme_text_darkest'] ); ?>; font-size:1.3rem; font-weight:800;">12 <span style="color:<?php echo esc_attr( $colors['theme_text_muted'] ); ?>; font-size:0.75rem; font-weight:400;">km/h</span></div>
                                    <div style="color:<?php echo esc_attr( $colors['theme_text_dark'] ); ?>; font-size:0.75rem;">SW · 225°</div>
                                    <div style="color:<?php echo esc_attr( $colors['theme_text_light'] ); ?>; font-size:0.65rem; margin-top:2px;">Böen: 18 km/h</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- ============================================================
             Gruppe 2: 24h-Chart-Farben
             ============================================================ -->
        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2><?php naws_e( 'appearance_group_chart_24h' ); ?></h2></div>
            <div class="naws-panel-body">
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
        </div>

        <!-- ============================================================
             Gruppe 3: Chart-Theming
             ============================================================ -->
        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2><?php naws_e( 'appearance_group_chart_theme' ); ?></h2></div>
            <div class="naws-panel-body">
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
                            <!-- Grid lines -->
                            <line class="pv-grid" x1="40" y1="20" x2="280" y2="20" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="55" x2="280" y2="55" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="90" x2="280" y2="90" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="125" x2="280" y2="125" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <line class="pv-grid" x1="40" y1="155" x2="280" y2="155" stroke="<?php echo esc_attr( $colors['chart_grid'] ); ?>" stroke-width="1"/>
                            <!-- Axis ticks -->
                            <text class="pv-tick" x="36" y="24" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">30°</text>
                            <text class="pv-tick" x="36" y="59" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">25°</text>
                            <text class="pv-tick" x="36" y="94" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">20°</text>
                            <text class="pv-tick" x="36" y="129" text-anchor="end" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">15°</text>
                            <text class="pv-tick" x="80" y="170" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">06:00</text>
                            <text class="pv-tick" x="160" y="170" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">12:00</text>
                            <text class="pv-tick" x="240" y="170" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_tick'] ); ?>">18:00</text>
                            <!-- Axis title -->
                            <text class="pv-axis-title" x="8" y="90" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $colors['chart_axis_title'] ); ?>" transform="rotate(-90 8 90)">°C</text>
                            <!-- Example line -->
                            <path d="M50,100 Q90,60 130,80 T210,45 T270,65" fill="none" stroke="#50a882" stroke-width="2.5" stroke-linecap="round"/>
                            <!-- Tooltip mock -->
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
        </div>

        <!-- ============================================================
             Gruppe 4: Jahresvergleich-Palette
             ============================================================ -->
        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2><?php naws_e( 'appearance_group_history' ); ?></h2></div>
            <div class="naws-panel-body">
            <p class="description"><?php naws_e( 'appearance_group_history_desc' ); ?></p>

            <!-- Preview: year bars -->
            <div class="naws-preview-label">Live-Vorschau — Jahresvergleich</div>
            <div id="naws-preview-history" class="naws-pv-history-bars">
                <?php for ( $i = 1; $i <= 15; $i++ ) :
                    $key = "history_year_{$i}";
                    $width = max(25, rand(40, 95));
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
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php naws_e( 'save_settings' ); ?></button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
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
            var map = {
                'theme_bg':           function(v){ box.css('background', v); },
                'theme_surface':      function(v){ box.find('.naws-pv-card').first().css('background', v); box.find('.naws-pv-wind-section').css('background', v); },
                'theme_surface_alt':  function(v){ box.find('.naws-pv-card').last().css('background', v); },
                'theme_border':       function(v){ box.css('border-color', v); box.find('.naws-pv-card, .naws-pv-wind-section, .naws-pv-compass').css('border-color', v); },
                'theme_text':         function(v){ box.find('.naws-pv-card-value').css('color', v); },
                'theme_text_dark':    function(v){ box.find('.naws-pv-card-sub').css('color', v); box.find('.naws-pv-wind-info div').eq(1).css('color', v); },
                'theme_text_darkest': function(v){ box.find('.naws-pv-wind-info div').first().css('color', v); },
                'theme_text_muted':   function(v){ box.find('.naws-pv-card-label').css('color', v); box.find('.naws-pv-card-value span').css('color', v); box.find('.naws-pv-needle-bottom, .naws-pv-compass-dot').css('background', v); },
                'theme_text_light':   function(v){ box.find('.naws-pv-card-meta').css('color', v); box.find('.naws-pv-compass-dir').css('color', v); box.find('.naws-pv-wind-info div').eq(2).css('color', v); },
                'theme_shadow':       function(v){ box.css('box-shadow', '0 2px 10px ' + v); },
                'theme_compass_needle': function(v){ box.find('.naws-pv-needle-top').css('background', v); },
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
            // Update legend dots
            var legend = $('#naws-preview-charttheme .naws-pv-chart-theme-legend');
            if (key === 'chart_grid')       legend.find('.naws-pv-dot').eq(0).css('background', val);
            if (key === 'chart_tick')        legend.find('.naws-pv-dot').eq(1).css('background', val);
            if (key === 'chart_axis_title') legend.find('.naws-pv-dot').eq(2).css('background', val);
        }

        // ── History year bars preview ──
        if (group === 'history') {
            $('#naws-pv-bar-' + key).css('background', val);
        }
    }

    $('.naws-color-picker').each(function() {
        var $input = $(this);
        $input.wpColorPicker({
            defaultColor: $input.data('default-color') || '',
            change: function(event, ui) {
                // wpColorPicker sets the value after this callback, so defer
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
</script>

<style>
/* ── Layout: controls left, preview right ── */
.naws-appearance-row {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
    padding: 0 1rem 1rem;
    align-items: start;
}
@media (max-width: 1100px) {
    .naws-appearance-row { grid-template-columns: 1fr; }
}
.naws-appearance-controls { min-width: 0; }
.naws-appearance-preview { min-width: 0; }
.naws-preview-sticky { position: sticky; top: 48px; }

.naws-color-table th { width: 200px; }
.naws-color-table td .wp-picker-container { display: inline-block; }

.naws-preview-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #646970;
    margin-bottom: 0.6rem;
}

/* ── Theme preview box ── */
.naws-preview-theme-box {
    border: 1.5px solid #e0eeee;
    border-radius: 12px;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    transition: all 0.2s;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
/* Sensor card preview */
.naws-pv-card {
    border: 1px solid #e0eeee;
    border-radius: 10px;
    padding: 0.75rem 0.85rem;
    position: relative;
    overflow: hidden;
}
.naws-pv-card-label {
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.2rem;
}
.naws-pv-card-value { font-size: 1.5rem; font-weight: 800; line-height: 1.2; }
.naws-pv-card-value span { font-size: 0.8rem; font-weight: 400; }
.naws-pv-card-meta { font-size: 0.62rem; margin-top: 0.2rem; }
.naws-pv-card-sub { font-size: 0.72rem; font-weight: 600; margin-top: 0.15rem; }

/* Wind section with compass */
.naws-pv-wind-section {
    border: 1px solid #e0eeee;
    border-radius: 10px;
    padding: 0.75rem 0.85rem;
}
.naws-pv-wind-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-top: 0.3rem;
}
.naws-pv-compass {
    width: 64px; height: 64px;
    border-radius: 50%;
    border: 2px solid #e0eeee;
    position: relative;
    flex-shrink: 0;
}
.naws-pv-compass-dir {
    position: absolute;
    font-size: 0.5rem;
    font-weight: 700;
}
.naws-pv-dir-n { top: 3px; left: 50%; transform: translateX(-50%); }
.naws-pv-dir-s { bottom: 3px; left: 50%; transform: translateX(-50%); }
.naws-pv-dir-e { right: 5px; top: 50%; transform: translateY(-50%); }
.naws-pv-dir-w { left: 5px; top: 50%; transform: translateY(-50%); }
.naws-pv-needle {
    width: 4px; height: 26px;
    position: absolute;
    top: 50%; left: 50%;
    transform-origin: bottom center;
    z-index: 2;
}
.naws-pv-needle-top {
    width: 100%; height: 50%;
    background: #ef4444;
    border-radius: 2px 2px 0 0;
}
.naws-pv-needle-bottom {
    width: 100%; height: 50%;
    border-radius: 0 0 2px 2px;
}
.naws-pv-compass-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 3;
}
.naws-pv-wind-info { flex: 1; }

/* ── 24h chart line preview ── */
.naws-pv-chart-lines {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.naws-pv-chart-line-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.2rem 0;
}
.naws-pv-chart-line-label {
    font-size: 0.75rem;
    color: #555;
}

/* ── Chart theme preview ── */
.naws-pv-chart-theme {
    background: #fff;
    border: 1.5px solid #e0eeee;
    border-radius: 10px;
    padding: 0.5rem;
    overflow: hidden;
}
.naws-pv-chart-theme svg { display: block; }
.naws-pv-chart-theme-legend {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.5rem 0.25rem 0.25rem;
    font-size: 0.7rem;
    color: #646970;
}
.naws-pv-chart-theme-legend span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.naws-pv-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

/* ── History year bars preview ── */
.naws-pv-history-bars {
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-height: 320px;
    overflow-y: auto;
}
.naws-pv-history-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.naws-pv-history-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #646970;
    width: 36px;
    text-align: right;
    flex-shrink: 0;
}
.naws-pv-history-bar {
    height: 14px;
    border-radius: 4px;
    transition: background 0.15s, width 0.3s;
    min-width: 20px;
}

/* ── Palette grid ── */
.naws-palette-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
}
.naws-palette-item label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.3rem;
    color: var(--naws-admin-text, #1d2327);
}
</style>
