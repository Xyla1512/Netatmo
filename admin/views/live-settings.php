<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$hidden_params  = (array) get_option( 'naws_live_hidden_params',   [] );
$hidden_modules = (array) get_option( 'naws_live_hidden_modules',  [] );
$hidden_charts         = (array) get_option( 'naws_live_hidden_charts',         [] );
$hidden_history_charts = (array) get_option( 'naws_history_hidden_charts',        [] );

// Available yearly comparison charts
$history_chart_defs = [
    'temp_minmax' => [ 'label' => naws__( 'hc_temp_minmax' ), 'icon' => '🌡️' ],
    'temp_avg'    => [ 'label' => naws__( 'hc_temp_avg' ),    'icon' => '🌡️' ],
    'pressure'    => [ 'label' => naws__( 'hc_pressure' ),    'icon' => '🔵' ],
    'rain'        => [ 'label' => naws__( 'hc_rain' ),        'icon' => '🌧️' ],
];
$admin_nonce    = wp_create_nonce( 'naws_admin_nonce' );
$all_modules    = NAWS_Database::get_modules( true );

// Build a lookup: type → first module of that type
$mod_by_type = [];
foreach ( $all_modules as $m ) {
    $mod_by_type[ $m['module_type'] ] = $m;
}

// ── NAModule4: generate slug + namespaced params from actual DB modules ──────
// Slug: module_name lowercased, only [a-z0-9], max 16 chars
$extra_module4_defs = [];
$m4_colors = [ '#7c3aed', '#d97706', '#059669', '#dc2626', '#0891b2' ];
$m4_color_idx = 0;
foreach ( $all_modules as $m ) {
    if ( $m['module_type'] !== 'NAModule4' ) continue;
    $slug  = preg_replace( '/[^a-z0-9]/', '', strtolower( $m['module_name'] ) );
    if ( $slug === '' ) $slug = 'indoor' . substr( str_replace( ':', '', $m['module_id'] ), -4 );
    $slug  = substr( $slug, 0, 16 );
    $color = $m4_colors[ $m4_color_idx % count( $m4_colors ) ];
    $m4_color_idx++;
    // Param keys are namespaced: Temperature_gast, Humidity_gast, etc.
    $extra_module4_defs[] = [
        'type'      => 'NAModule4_' . $slug,
        'label'     => $m['module_name'],
        'sub'       => naws__( 'mod_indoor4_sub' ),
        'color'     => $color,
        'db_module' => $m,
        'params'    => [
            "Temperature_{$slug}" => [ 'label' => naws__( 'param_temperature' ), 'unit' => '°C'  ],
            "Humidity_{$slug}"    => [ 'label' => naws__( 'param_humidity' ), 'unit' => '%'   ],
            "CO2_{$slug}"         => [ 'label' => naws__( 'param_co2' ), 'unit' => 'ppm' ],
        ],
    ];
}

// ── Static module definitions ──────────────────────────────────────────────
$module_defs = [
    [
        'type'   => 'NAMain',
        'label'  => naws__( 'mod_base' ),
        'sub'    => naws__( 'mod_base_sub' ),
        'color'  => '#2271b1',
        'params' => [
            'Temperature_indoor' => [ 'label' => naws__( 'param_temp_indoor' ), 'unit' => '°C'  ],
            'Pressure'           => [ 'label' => 'Luftdruck relativ',      'unit' => 'hPa' ],
            'AbsolutePressure'   => [ 'label' => 'Luftdruck absolut',      'unit' => 'hPa' ],
            'CO2'                => [ 'label' => 'CO₂-Konzentration',      'unit' => 'ppm' ],
            'Noise'              => [ 'label' => naws__( 'param_noise' ), 'unit' => 'dB'  ],
        ],
    ],
    [
        'type'   => 'NAModule1',
        'label'  => naws__( 'mod_outdoor' ),
        'sub'    => naws__( 'mod_outdoor_sub' ),
        'color'  => '#d4541a',
        'params' => [
            'Temperature'  => [ 'label' => naws__( 'param_temp_out' ), 'unit' => '°C' ],
            'min_temp'     => [ 'label' => 'Min-Temperatur (Tag)',       'unit' => '°C' ],
            'max_temp'     => [ 'label' => 'Max-Temperatur (Tag)',       'unit' => '°C' ],
            'Humidity'     => [ 'label' => naws__( 'param_humidity' ), 'unit' => '%'  ],
        ],
    ],
    [
        'type'   => 'NAModule2',
        'label'  => naws__( 'mod_wind' ),
        'sub'    => 'Wind-Modul',
        'color'  => '#0a9272',
        'params' => [
            'WindStrength' => [ 'label' => 'Windgeschwindigkeit',   'unit' => 'km/h' ],
            'GustStrength' => [ 'label' => naws__( 'param_gust_speed' ), 'unit' => 'km/h' ],
            'WindAngle'    => [ 'label' => naws__( 'param_wind_dir' ), 'unit' => '°'    ],
            'GustAngle'    => [ 'label' => naws__( 'param_gust_dir' ), 'unit' => '°'    ],
        ],
    ],
    [
        'type'   => 'NAModule3',
        'label'  => 'Regenmesser',
        'sub'    => naws__( 'mod_rain_sub' ),
        'color'  => '#0579b0',
        'params' => [
            'Rain'         => [ 'label' => 'Regen aktuell',       'unit' => 'mm' ],
            'sum_rain_1'   => [ 'label' => naws__( 'param_rain_1h' ), 'unit' => 'mm' ],
            'sum_rain_24'  => [ 'label' => 'Summe letzte 24h',     'unit' => 'mm' ],
        ],
    ],
];

// Enrich static defs with DB module data
foreach ( $module_defs as &$md ) {
    if ( isset( $mod_by_type[ $md['type'] ] ) ) {
        $md['db_module'] = $mod_by_type[ $md['type'] ];
    }
}
unset( $md );

// Append dynamic NAModule4 entries
$module_defs = array_merge( $module_defs, $extra_module4_defs );
?>
<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title">
        <span class="naws-title-icon">🖥️</span>
        <?php naws_e( 'live_settings_title' ); ?>
    </h1>

    <div class="naws-ls-layout">

        <!-- Left: Accordion modules -->
        <div class="naws-ls-main">

            <div class="naws-section-label" style="margin-bottom:.5rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <?php naws_e( 'ls_modules_sensors' ); ?>
            </div>
            <p class="naws-ls-hint"><?php NAWS_Lang::r( 'ls_hint_toggles' ); ?></p>

            <div class="naws-ls-accordion">
            <?php foreach ( $module_defs as $idx => $md ) :
                $mod_type   = $md['type'];
                $mod_hidden = in_array( $mod_type, $hidden_modules, true );
                $has_params = ! empty( $md['params'] );
                $is_open    = ( $idx === 0 );
                $enabled    = 0;
                $total      = count( $md['params'] );
                foreach ( $md['params'] as $param => $pdef ) {
                    if ( ! in_array( $param, $hidden_params, true ) ) $enabled++;
                }
            ?>
            <div class="naws-ls-mod <?php echo $is_open ? 'is-open' : ''; echo $mod_hidden ? ' is-mod-off' : ''; ?>"
                 data-mod="<?php echo esc_attr($mod_type); ?>">

                <div class="naws-ls-mod-header">

                    <!-- Master toggle -->
                    <button type="button"
                            class="naws-ls-mod-toggle <?php echo $mod_hidden ? '' : 'is-on'; ?>"
                            title="<?php echo $mod_hidden ? esc_attr( naws__( 'ls_mod_activate' ) ) : esc_attr( naws__( 'ls_mod_deactivate' ) ); ?>">
                        <span class="naws-ls-mod-knob"></span>
                        <input type="checkbox" class="naws-mod-cb"
                               value="<?php echo esc_attr($mod_type); ?>"
                               <?php checked($mod_hidden); ?> style="display:none">
                    </button>

                    <!-- Accordion trigger -->
                    <button type="button" class="naws-ls-mod-trigger"
                            aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                        <div class="naws-ls-mod-dot" style="background:<?php echo esc_attr($md['color']); ?>"></div>
                        <div class="naws-ls-mod-meta">
                            <span class="naws-ls-mod-name"><?php echo esc_html($md['label']); ?>
                                <?php if ( isset($md['db_module']) ) : ?>
                                    <span class="naws-ls-mod-realname"><?php echo esc_html($md['db_module']['module_name']); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="naws-ls-mod-sub">
                                <?php echo esc_html($md['sub']); ?>
                                <?php if ( $has_params ) : ?>
                                &nbsp;·&nbsp;<span class="naws-ls-mod-count"><?php echo esc_html( $enabled . '/' . $total ); ?> <?php naws_e( 'ls_count_active' ); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <svg class="naws-ls-chevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                </div><!-- /header -->

                <div class="naws-ls-mod-body">
                    <div class="naws-ls-mod-body-inner">
                    <?php if ( ! $has_params ) : ?>
                        <div class="naws-ls-empty">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <?php naws_e( 'ls_no_data_module' ); ?>
                        </div>
                    <?php else : ?>
                        <div class="naws-ls-params">
                        <?php foreach ( $md['params'] as $param => $pdef ) :
                            $vis        = ! in_array( $param, $hidden_params, true );
                            $chart_vis  = ! in_array( $param, $hidden_charts, true );
                        ?>
                        <div class="naws-ls-param-row">
                            <!-- Kachel-Toggle -->
                            <label class="naws-ls-toggle <?php echo $vis ? 'is-on' : 'is-off'; ?>"
                                   data-param="<?php echo esc_attr($param); ?>">
                                <div class="naws-ls-tgl-info">
                                    <span class="naws-ls-tgl-label"><?php echo esc_html($pdef['label']); ?></span>
                                    <span class="naws-ls-tgl-meta">
                                        <code><?php echo esc_html($param); ?></code>
                                        <span class="naws-ls-tgl-unit"><?php echo esc_html($pdef['unit']); ?></span>
                                    </span>
                                </div>
                                <span class="naws-ls-sw">
                                    <span class="naws-ls-sw-knob"></span>
                                </span>
                                <input type="checkbox" name="visible_params[]" value="<?php echo esc_attr($param); ?>"
                                       <?php checked($vis); ?> style="display:none">
                            </label>
                            <!-- Chart-Toggle -->
                            <label class="naws-ls-chart-toggle <?php echo $chart_vis ? 'is-on' : 'is-off'; ?>"
                                   data-chart="<?php echo esc_attr($param); ?>"
                                   title="24h-Verlauf <?php echo $chart_vis ? 'deaktivieren' : 'aktivieren'; ?>">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                <input type="checkbox" class="naws-chart-cb" value="<?php echo esc_attr($param); ?>"
                                       <?php checked($chart_vis); ?> style="display:none">
                            </label>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
            </div><!-- /.naws-ls-accordion -->

            <!-- ── Jahresvergleich: Chart-Schalter ─────────────────────────── -->
            <div class="naws-section-label" style="margin:1.4rem 0 .5rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <?php naws_e( 'ls_year_charts' ); ?>
            </div>
            <p class="naws-ls-hint"><?php NAWS_Lang::r( 'ls_year_hint' ); ?></p>
            <div class="naws-ls-history-charts">
            <?php foreach ( $history_chart_defs as $chart_key => $cdef ) :
                $hc_on = ! in_array( $chart_key, $hidden_history_charts, true );
            ?>
            <label class="naws-ls-hc-toggle <?php echo $hc_on ? 'is-on' : 'is-off'; ?>"
                   data-hc="<?php echo esc_attr($chart_key); ?>">
                <div class="naws-ls-tgl-info">
                    <span class="naws-ls-tgl-label"><?php echo esc_html($cdef['label']); ?></span>
                    <span class="naws-ls-tgl-meta"><code><?php echo esc_html($chart_key); ?></code></span>
                </div>
                <span class="naws-ls-sw"><span class="naws-ls-sw-knob"></span></span>
                <input type="checkbox" class="naws-hc-cb" value="<?php echo esc_attr($chart_key); ?>"
                       <?php checked($hc_on); ?> style="display:none">
            </label>
            <?php endforeach; ?>
            </div>

            <div class="naws-ls-actions">
                <button id="naws-save-live" class="button button-primary naws-ls-save-btn">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Einstellungen speichern
                </button>
                <span id="naws-ls-status"></span>
            </div>
        </div><!-- /.naws-ls-main -->

        <!-- Right: Info sidebar -->
        <div class="naws-ls-side">
            <div class="naws-section-label" style="margin-bottom:.5rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <?php naws_e( 'ls_shortcode' ); ?>
            </div>
            <div class="naws-admin-panel">
                <div style="padding:1rem 1.1rem;">
                    <code class="naws-ls-sc">[naws_live title="Meine Wetterstation" refresh="60"]</code>
                    <p class="naws-ls-sc-desc">
                        <strong>title</strong> – <?php naws_e( 'ls_sc_title_desc' ); ?><br>
                        <strong>refresh</strong> – <?php naws_e( 'ls_sc_refresh_desc' ); ?>
                    </p>
                </div>
            </div>

            <div class="naws-section-label" style="margin-top:1.1rem; margin-bottom:.5rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php naws_e( 'ls_notes' ); ?>
            </div>
            <div class="naws-admin-panel">
                <div style="padding:.85rem 1rem; font-size:.78rem; color:#4a5568; line-height:1.6;">
                    <p style="margin:0 0 .5rem;"><?php NAWS_Lang::r( 'ls_mod_note_master' ); ?></p>
                    <p style="margin:0 0 .5rem;"><?php NAWS_Lang::r( 'ls_mod_note_sensor' ); ?></p>
                    <p style="margin:0;"><?php NAWS_Lang::r( 'ls_mod_note_wind' ); ?></p>
                </div>
            </div>
        </div>

    </div><!-- /.naws-ls-layout -->
</div>

<style>
.naws-ls-layout{display:grid;grid-template-columns:1fr 280px;gap:1.5rem;align-items:start;max-width:980px;}
@media(max-width:860px){.naws-ls-layout{grid-template-columns:1fr;}}
.naws-ls-hint{font-size:.77rem;color:#646970;margin:0 0 .85rem;line-height:1.55;}

/* Accordion */
.naws-ls-accordion{border:1px solid #e2e4e7;border-radius:10px;overflow:hidden;background:#fff;}
.naws-ls-mod{border-bottom:1px solid #e2e4e7;}
.naws-ls-mod:last-child{border-bottom:none;}

/* Header row */
.naws-ls-mod-header{display:flex;align-items:center;}
.naws-ls-mod.is-open>.naws-ls-mod-header{background:#f8f9fa;}
.naws-ls-mod.is-mod-off>.naws-ls-mod-header{background:#fafafa;}

/* Module master toggle */
.naws-ls-mod-toggle{
    flex-shrink:0;width:40px;height:22px;border-radius:11px;
    background:#d1d5db;border:none;cursor:pointer;position:relative;
    margin:0 .7rem 0 .9rem;transition:background .2s;
}
.naws-ls-mod-toggle.is-on{background:#2271b1;}
.naws-ls-mod-knob{
    position:absolute;top:3px;left:3px;width:16px;height:16px;
    border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);
    transition:transform .2s;pointer-events:none;
}
.naws-ls-mod-toggle.is-on .naws-ls-mod-knob{transform:translateX(18px);}

/* Accordion trigger */
.naws-ls-mod-trigger{
    flex:1;display:flex;align-items:center;gap:.65rem;
    padding:.75rem .9rem .75rem .1rem;
    background:none;border:none;cursor:pointer;text-align:left;min-width:0;
    transition:opacity .15s;
}
.naws-ls-mod-trigger:hover{opacity:.8;}

.naws-ls-mod-dot{
    width:10px;height:10px;border-radius:50%;flex-shrink:0;
}
.naws-ls-mod.is-mod-off .naws-ls-mod-dot{opacity:.3;}

.naws-ls-mod-meta{flex:1;min-width:0;display:flex;flex-direction:column;gap:.08rem;}
.naws-ls-mod-name{
    font-size:.87rem;font-weight:600;color:#1d2327;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    display:flex;align-items:center;gap:.45rem;
}
.naws-ls-mod.is-mod-off .naws-ls-mod-name{color:#9ca3af;}
.naws-ls-mod-realname{
    font-size:.7rem;font-weight:400;color:#8d959e;
    background:#f1f3f5;padding:.05rem .4rem;border-radius:4px;
}
.naws-ls-mod-sub{font-size:.68rem;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.naws-ls-mod-count{color:#2271b1;font-weight:600;}

.naws-ls-chevron{flex-shrink:0;color:#b0b8c1;transition:transform .22s ease;}
.naws-ls-mod.is-open .naws-ls-chevron{transform:rotate(180deg);}

/* Accordion body */
.naws-ls-mod-body{display:grid;grid-template-rows:0fr;transition:grid-template-rows .24s ease;}
.naws-ls-mod.is-open .naws-ls-mod-body{grid-template-rows:1fr;}
.naws-ls-mod-body-inner{overflow:hidden;border-top:1px solid transparent;transition:border-color .2s;}
.naws-ls-mod.is-open .naws-ls-mod-body-inner{border-top-color:#e2e4e7;}

/* Empty notice */
.naws-ls-empty{
    display:flex;align-items:center;gap:.4rem;
    padding:.8rem 1rem;font-size:.76rem;color:#9ca3af;font-style:italic;
}

/* Sensor param list */
.naws-ls-params{padding:.35rem .7rem .55rem;display:flex;flex-direction:column;gap:2px;}

.naws-ls-toggle{
    display:flex;align-items:center;justify-content:space-between;gap:.75rem;
    padding:.55rem .7rem;border-radius:7px;cursor:pointer;
    border:1px solid transparent;transition:background .13s,border-color .13s;
    user-select:none;
}
.naws-ls-toggle:hover{background:#f8f9fa;}
.naws-ls-toggle.is-on{background:#f0f6ff;border-color:#c7ddf5;}
.naws-ls-toggle.is-off{opacity:.5;}
.naws-ls-mod.is-mod-off .naws-ls-toggle{opacity:.3!important;pointer-events:none;}

.naws-ls-tgl-info{display:flex;flex-direction:column;gap:.08rem;min-width:0;}
.naws-ls-tgl-label{font-size:.82rem;font-weight:600;color:#1d2327;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.naws-ls-tgl-meta{display:flex;align-items:center;gap:.35rem;}
.naws-ls-tgl-meta code{font-size:.67rem;color:#7a9e9b;background:none;padding:0;}
.naws-ls-tgl-unit{font-size:.67rem;color:#b0b8c1;}

/* Sensor switch */
.naws-ls-sw{
    width:34px;height:19px;border-radius:10px;background:#d1d5db;
    flex-shrink:0;position:relative;transition:background .2s;
}
.naws-ls-toggle.is-on .naws-ls-sw,
.naws-ls-hc-toggle.is-on .naws-ls-sw{background:#2271b1;}
.naws-ls-sw-knob{
    position:absolute;top:2px;left:2px;width:15px;height:15px;
    border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);
    transition:transform .2s;pointer-events:none;
}
.naws-ls-toggle.is-on .naws-ls-sw-knob,
.naws-ls-hc-toggle.is-on .naws-ls-sw-knob{transform:translateX(15px);}

/* Actions */
.naws-ls-actions{display:flex;align-items:center;gap:.9rem;margin-top:1rem;}
.naws-ls-save-btn{display:inline-flex!important;align-items:center;gap:.4rem;height:35px!important;padding:0 1rem!important;font-size:.84rem!important;}
#naws-ls-status{font-size:.8rem;color:#646970;}

/* <?php naws_e( 'ls_shortcode' ); ?> */
.naws-ls-sc{display:block;background:#f5f8fb;padding:.6rem .85rem;border-radius:7px;font-size:.78rem;border:1px solid #dae6ef;word-break:break-all;}
.naws-ls-sc-desc{margin:.55rem 0 0;font-size:.75rem;color:#5a7a8a;line-height:1.65;}

/* Param row: Kachel-toggle + Chart-toggle side by side */
.naws-ls-param-row{display:flex;align-items:stretch;gap:4px;}
.naws-ls-param-row .naws-ls-toggle{flex:1;}
.naws-ls-chart-toggle{
    flex-shrink:0;width:34px;display:flex;align-items:center;justify-content:center;
    border:1px solid transparent;border-radius:7px;cursor:pointer;
    color:#b0b8c1;transition:background .13s,color .13s,border-color .13s;
    user-select:none;
}
.naws-ls-chart-toggle:hover{background:#f0f6ff;border-color:#c7ddf5;color:#2271b1;}
.naws-ls-chart-toggle.is-on{background:#edf7f0;border-color:#a7dfc0;color:#1a7a50;}
.naws-ls-chart-toggle.is-off{opacity:.5;}
.naws-ls-mod.is-mod-off .naws-ls-chart-toggle{opacity:.3!important;pointer-events:none;}

/* Jahresvergleich chart-row toggles */
.naws-ls-history-charts{display:flex;flex-direction:column;gap:4px;margin-bottom:.5rem;}
.naws-ls-hc-toggle{
    display:flex;align-items:center;gap:10px;padding:9px 12px;
    border:1px solid #e2e4e7;border-radius:8px;background:#fff;
    cursor:pointer;user-select:none;transition:border-color .13s,background .13s;
}
.naws-ls-hc-toggle:hover{border-color:#c3c4c7;background:#f9f9f9;}
.naws-ls-hc-toggle.is-on{border-color:#a7dfc0;background:#edf7f0;}
.naws-ls-hc-toggle.is-off{opacity:.65;}
.naws-ls-hc-toggle .naws-ls-tgl-info{flex:1;}
.naws-ls-hc-toggle .naws-ls-sw{flex-shrink:0;}
</style>

<script>
(function(){
'use strict';

/* Accordion */
document.querySelectorAll('.naws-ls-mod-trigger').forEach(function(btn){
    btn.addEventListener('click',function(){
        var mod=this.closest('.naws-ls-mod');
        var wasOpen=mod.classList.contains('is-open');
        document.querySelectorAll('.naws-ls-mod').forEach(function(el){
            el.classList.remove('is-open');
            el.querySelector('.naws-ls-mod-trigger').setAttribute('aria-expanded','false');
        });
        if(!wasOpen){
            mod.classList.add('is-open');
            btn.setAttribute('aria-expanded','true');
        }
    });
});

/* Module master toggle */
document.querySelectorAll('.naws-ls-mod-toggle').forEach(function(btn){
    btn.addEventListener('click',function(e){
        e.stopPropagation();
        var isOn=this.classList.toggle('is-on');
        var mod=this.closest('.naws-ls-mod');
        var cb=this.querySelector('.naws-mod-cb');
        cb.checked=!isOn; // checked = hidden
        mod.classList.toggle('is-mod-off',!isOn);
        this.title=isOn?<?php echo wp_json_encode( naws__('ls_mod_deactivate' ) ); ?>:<?php echo wp_json_encode( naws__('ls_mod_activate' ) ); ?>;
        refreshCount(mod);
    });
});

/* Individual sensor toggle */
document.querySelectorAll('.naws-ls-toggle').forEach(function(lbl){
    lbl.addEventListener('click',function(){
        var cb=this.querySelector('input[type=checkbox]');
        var on=cb.checked=!cb.checked;
        this.classList.toggle('is-on',on);
        this.classList.toggle('is-off',!on);
        refreshCount(this.closest('.naws-ls-mod'));
    });
});

function refreshCount(mod){
    if(!mod) return;
    var t=mod.querySelectorAll('.naws-ls-toggle').length;
    var e=mod.querySelectorAll('.naws-ls-toggle.is-on').length;
    var el=mod.querySelector('.naws-ls-mod-count');
    if(el) el.textContent=e+'/'+t+' '+<?php echo wp_json_encode( naws__('ls_count_active' ) ); ?>;
}

/* 24h Chart toggle */
document.querySelectorAll('.naws-ls-chart-toggle').forEach(function(lbl){
    lbl.addEventListener('click',function(e){
        e.stopPropagation();
        var cb=this.querySelector('.naws-chart-cb');
        var on=cb.checked=!cb.checked;
        this.classList.toggle('is-on',on);
        this.classList.toggle('is-off',!on);
        this.title=(on?<?php echo wp_json_encode( naws__('ls_chart_disable' ) ); ?>:<?php echo wp_json_encode( naws__('ls_chart_enable' ) ); ?>);
    });
});

/* Jahresvergleich Chart toggle */
document.querySelectorAll('.naws-ls-hc-toggle').forEach(function(lbl){
    lbl.addEventListener('click',function(){
        var cb=this.querySelector('.naws-hc-cb');
        var on=cb.checked=!cb.checked;
        this.classList.toggle('is-on',on);
        this.classList.toggle('is-off',!on);
    });
});

/* Save */
document.getElementById('naws-save-live').addEventListener('click',function(){
    var btn=this, status=document.getElementById('naws-ls-status');
    btn.disabled=true;
    btn.innerHTML='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> '+<?php echo wp_json_encode( naws__('ls_saving' ) ); ?>;

    var hParams=[], hMods=[], hCharts=[];
    document.querySelectorAll('.naws-ls-toggle input[type=checkbox]').forEach(function(cb){
        if(!cb.checked) hParams.push(cb.value);
    });
    document.querySelectorAll('.naws-mod-cb').forEach(function(cb){
        if(cb.checked) hMods.push(cb.value);
    });
    document.querySelectorAll('.naws-chart-cb').forEach(function(cb){
        if(!cb.checked) hCharts.push(cb.value); // unchecked = chart hidden
    });

    var body='action=naws_save_live_settings&nonce=<?php echo esc_js($admin_nonce); ?>';
    hParams.forEach(function(p){body+='&hidden[]='+encodeURIComponent(p);});
    hMods.forEach(function(m){body+='&hidden_modules[]='+encodeURIComponent(m);});
    hCharts.forEach(function(c){body+='&hidden_charts[]='+encodeURIComponent(c);});
    // History chart toggles (unchecked = hidden)
    var hHistCharts=[];
    document.querySelectorAll('.naws-hc-cb').forEach(function(cb){
        if(!cb.checked) hHistCharts.push(cb.value);
    });
    hHistCharts.forEach(function(c){body+='&hidden_history_charts[]='+encodeURIComponent(c);});

    var xhr=new XMLHttpRequest();
    xhr.open('POST','<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.onload=function(){
        btn.disabled=false;
        btn.innerHTML='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Einstellungen speichern';
        try{
            var r=JSON.parse(xhr.responseText);
            if(r.success){status.textContent=<?php echo wp_json_encode( naws__('ls_saved' ) ); ?>;status.style.color='#1a7a50';}
            else{status.textContent=<?php echo wp_json_encode( naws__('ls_error' ) ); ?>;status.style.color='#c0392b';}
        }catch(e){status.textContent=<?php echo wp_json_encode( naws__('ls_error' ) ); ?>;status.style.color='#c0392b';}
        setTimeout(function(){status.textContent='';},3000);
    };
    xhr.send(body);
});

})();
</script>
