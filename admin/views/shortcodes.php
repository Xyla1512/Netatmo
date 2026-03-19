<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Gather real module data for examples
$module_map = [];
foreach ( $modules as $m ) {
    $module_map[ $m['module_type'] ] = $m;
}
$outdoor_id = $module_map['NAModule1']['module_id'] ?? 'YOUR_MODULE_ID';
$indoor_id  = $module_map['NAMain']['module_id']    ?? 'YOUR_MODULE_ID';
$wind_id    = $module_map['NAModule2']['module_id'] ?? null;
$rain_id    = $module_map['NAModule3']['module_id'] ?? null;

// Fetch live values for the preview column
$latest   = NAWS_Database::get_latest_readings();
$_mod_map = [];
foreach ( $modules as $m ) { $_mod_map[ $m['module_id'] ] = $m['module_type']; }
$live_map = [];
foreach ( $latest as $r ) {
    $mtype = $_mod_map[ $r['module_id'] ] ?? '';
    if ( $mtype ) $live_map[ $mtype . '_' . $r['parameter'] ] = $r;
}
function naws_live_val( $map, $type, $param ) {
    $key = $type . '_' . $param;
    if ( ! isset( $map[ $key ] ) ) return '--';
    $val  = NAWS_Helpers::format_value( $param, floatval( $map[ $key ]['value'] ) );
    $unit = NAWS_Helpers::get_unit( $param );
    return esc_html( $val . ( $unit ? ' ' . $unit : '' ) );
}

// [ param, module_alias, module_type, lang_key ]
$value_params = [
    [ 'Temperature',      'outdoor', 'NAModule1', 'sc_param_temp_out'    ],
    [ 'Humidity',         'outdoor', 'NAModule1', 'sc_param_hum_out'     ],
    [ 'Temperature',      'indoor',  'NAMain',    'sc_param_temp_in'     ],
    [ 'Humidity',         'indoor',  'NAMain',    'sc_param_hum_in'      ],
    [ 'Pressure',         'indoor',  'NAMain',    'sc_param_pressure'    ],
    [ 'CO2',              'indoor',  'NAMain',    'sc_param_co2'         ],
    [ 'Noise',            'indoor',  'NAMain',    'sc_param_noise'       ],
    [ 'WindStrength',     'wind',    'NAModule2', 'sc_param_wind'        ],
    [ 'GustStrength',     'wind',    'NAModule2', 'sc_param_gust'        ],
    [ 'WindAngle',        'wind',    'NAModule2', 'sc_param_windangle'   ],
    [ 'Rain',             'rain',    'NAModule3', 'sc_param_rain1h'      ],
    [ 'sum_rain_24',      'rain',    'NAModule3', 'sc_param_rain24_nt'   ],
    [ 'rain_rolling_24h', 'rain',    'NAModule3', 'sc_param_rain24_roll' ],
];
?>
<style>
.naws-ref-section{margin-bottom:32px}
.naws-ref-section h2{font-size:15px;font-weight:700;color:#1e293b;margin:0 0 14px;padding-bottom:8px;border-bottom:2px solid #e2e8f0}
.naws-ref-table{width:100%;border-collapse:collapse;font-size:13px}
.naws-ref-table th{background:#f1f5f9;padding:8px 12px;text-align:left;font-weight:600;color:#475569;border:1px solid #e2e8f0}
.naws-ref-table td{padding:8px 12px;border:1px solid #e2e8f0;vertical-align:middle;color:#334155}
.naws-ref-table tr:hover td{background:#f8fafc}
.naws-ref-table code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;color:#0f172a}
.naws-live-badge{display:inline-block;background:#ecfdf5;color:#059669;font-weight:700;font-size:12px;padding:2px 8px;border-radius:20px;border:1px solid #a7f3d0}
.naws-copy-wrap{display:flex;align-items:center;gap:6px}
.naws-copy-wrap pre{margin:0;flex:1;background:#1e293b;color:#7dd3fc;padding:7px 10px;border-radius:6px;font-size:11.5px;overflow-x:auto;white-space:pre}
.naws-copy-btn{flex-shrink:0;cursor:pointer;background:#3b82f6;color:#fff;border:none;padding:5px 10px;border-radius:5px;font-size:11px;font-weight:600;transition:background .15s}
.naws-copy-btn:hover{background:#2563eb}
.naws-copy-btn.copied{background:#10b981}
.naws-sc-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 22px;margin-bottom:20px}
.naws-sc-card h3{margin:0 0 6px;font-size:14px;color:#1e293b}
.naws-sc-card h3 code{font-size:14px;color:#7c3aed;background:#f5f3ff;padding:2px 8px;border-radius:4px}
.naws-sc-card p{margin:0 0 12px;color:#64748b;font-size:13px}
.naws-attr-table{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:10px}
.naws-attr-table th{background:#f8fafc;padding:6px 10px;text-align:left;color:#64748b;border:1px solid #e2e8f0;font-weight:600}
.naws-attr-table td{padding:6px 10px;border:1px solid #e2e8f0;color:#334155}
.naws-attr-table td:first-child code{color:#0ea5e9;background:#f0f9ff;padding:2px 6px;border-radius:3px}
.naws-tag-default{color:#94a3b8;font-size:11px}
.naws-inline-examples{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.naws-inline-ex{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-size:12px}
.naws-inline-ex code{color:#7c3aed}
.naws-section-intro{color:#64748b;font-size:13px;margin:0 0 16px}
</style>

<div class="wrap naws-admin-wrap">
<h1 class="naws-admin-page-title"><span class="naws-title-icon">&#x1F4DD;</span> <?php naws_e('sc_ref_title'); ?></h1>

<?php /* ── naws_value ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php naws_e('sc_section_value'); ?></h2></div>
    <div class="naws-panel-body">
    <p class="naws-section-intro"><?php naws_e('sc_value_desc'); ?></p>

    <div class="naws-sc-card">
        <h3><code>[naws_value]</code> &ndash; <?php naws_e('sc_attr_header'); ?></h3>
        <table class="naws-attr-table">
            <tr>
                <th><?php naws_e('sc_th_attribute'); ?></th>
                <th><?php naws_e('sc_th_description'); ?></th>
                <th><?php naws_e('sc_th_values'); ?></th>
                <th><?php naws_e('sc_th_default'); ?></th>
            </tr>
            <tr><td><code>param</code></td><td><?php naws_e('sc_attr_param_desc'); ?></td><td><?php naws_e('sc_attr_param_vals'); ?></td><td><span class="naws-tag-default">Temperature</span></td></tr>
            <tr><td><code>module</code></td><td><?php naws_e('sc_attr_module_desc'); ?></td><td><code>outdoor</code> &middot; <code>indoor</code> &middot; <code>wind</code> &middot; <code>rain</code> &middot; MAC</td><td><span class="naws-tag-default">outdoor</span></td></tr>
            <tr><td><code>unit</code></td><td><?php naws_e('sc_attr_unit_desc'); ?></td><td><?php naws_e('sc_attr_unit_vals'); ?></td><td><span class="naws-tag-default">1</span></td></tr>
            <tr><td><code>decimals</code></td><td><?php naws_e('sc_attr_decimals_desc'); ?></td><td><?php naws_e('sc_attr_decimals_vals'); ?></td><td><span class="naws-tag-default">-1</span></td></tr>
            <tr><td><code>fallback</code></td><td><?php naws_e('sc_attr_fallback_desc'); ?></td><td><?php naws_e('sc_attr_fallback_vals'); ?></td><td><span class="naws-tag-default">--</span></td></tr>
            <tr><td><code>tag</code></td><td><?php naws_e('sc_attr_tag_desc'); ?></td><td><code>span</code> &middot; <code>div</code> &middot; <code>p</code> &middot; <code>strong</code> &middot; <code>none</code></td><td><span class="naws-tag-default">span</span></td></tr>
            <tr><td><code>class</code></td><td><?php naws_e('sc_attr_class_desc'); ?></td><td><?php naws_e('sc_attr_class_vals'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_value param="Temperature" module="outdoor"]</code> &rarr; <strong><?php echo esc_html( naws_live_val( $live_map, 'NAModule1', 'Temperature' ) ); ?></strong></div>
            <div class="naws-inline-ex"><code>[naws_value param="Humidity" module="outdoor" unit="0"]</code> <?php naws_e('sc_ex_number_only'); ?></div>
            <div class="naws-inline-ex"><code>[naws_value param="Pressure" decimals="0"]</code> <?php naws_e('sc_ex_integer'); ?></div>
            <div class="naws-inline-ex"><code>[naws_value param="Temperature" tag="strong" class="my-temp"]</code> <?php naws_e('sc_ex_wrapper'); ?></div>
            <div class="naws-inline-ex"><code>[naws_value param="rain_rolling_24h" fallback="&ndash;"]</code> <?php naws_e('sc_ex_rolling'); ?></div>
        </div>
    </div>

    <h3 style="font-size:14px;margin:20px 0 10px;color:#1e293b;"><?php naws_e('sc_all_params_title'); ?></h3>
    <table class="naws-ref-table">
        <thead>
            <tr>
                <th>param=</th>
                <th>module=</th>
                <th><?php naws_e('sc_th_description'); ?></th>
                <th><?php naws_e('sc_th_unit_col'); ?></th>
                <th><?php naws_e('sc_th_live'); ?></th>
                <th><?php naws_e('sc_th_example'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $value_params as [ $param, $alias, $type, $lang_key ] ) :
            $unit = NAWS_Helpers::get_unit( in_array( $param, ['Rain','sum_rain_24','rain_rolling_24h'], true ) ? 'Rain' : $param );
            $live = esc_html( naws_live_val( $live_map, $type, $param ) );
            if ( $param === 'rain_rolling_24h' ) {
                foreach ( $modules as $mm ) {
                    if ( $mm['module_type'] === 'NAModule3' ) {
                        $rv   = NAWS_Database::get_rain_rolling_24h( $mm['module_id'] );
                        $live = $rv !== null ? esc_html( NAWS_Helpers::format_value( 'Rain', $rv ) . ' ' . $unit ) : '--';
                        break;
                    }
                }
            }
            if ( $alias === 'wind' && ! $wind_id ) continue;
            if ( $alias === 'rain' && ! $rain_id ) continue;
            $sc = '[naws_value param="' . $param . '" module="' . $alias . '"]';
        ?>
        <tr>
            <td><code><?php echo esc_html( $param ); ?></code></td>
            <td><code><?php echo esc_html( $alias ); ?></code></td>
            <td><?php naws_e( $lang_key ); ?></td>
            <td><?php echo esc_html( $unit ?: '&ndash;' ); ?></td>
            <td><span class="naws-live-badge"><?php echo esc_html( $live ); ?></span></td>
            <td>
                <div class="naws-copy-wrap">
                    <pre><?php echo esc_html( $sc ); ?></pre>
                    <button class="naws-copy-btn" data-copy="<?php echo esc_attr( $sc ); ?>"><?php naws_e('sc_copy'); ?></button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php /* ── Layout Shortcodes ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php naws_e('sc_section_layout'); ?></h2></div>
    <p class="naws-section-intro"><?php naws_e('sc_layout_intro'); ?></p>

    <div class="naws-sc-card">
        <h3><code>[naws_live]</code></h3>
        <p><?php naws_e('sc_live_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_live refresh="60"]</pre><button class="naws-copy-btn" data-copy='[naws_live refresh="60"]'><?php naws_e('sc_copy'); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php naws_e('sc_th_attribute'); ?></th><th><?php naws_e('sc_th_description'); ?></th><th><?php naws_e('sc_th_default'); ?></th></tr>
            <tr><td><code>title</code></td><td><?php naws_e('sc_live_attr_title'); ?></td><td><span class="naws-tag-default"><?php naws_e('sc_live_attr_title_def'); ?></span></td></tr>
            <tr><td><code>refresh</code></td><td><?php naws_e('sc_live_attr_refresh'); ?></td><td><span class="naws-tag-default">60</span></td></tr>
        </table>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_history]</code></h3>
        <p><?php naws_e('sc_history_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_history years="3"]</pre><button class="naws-copy-btn" data-copy='[naws_history years="3"]'><?php naws_e('sc_copy'); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php naws_e('sc_th_attribute'); ?></th><th><?php naws_e('sc_th_description'); ?></th><th><?php naws_e('sc_th_default'); ?></th></tr>
            <tr><td><code>years</code></td><td><?php naws_e('sc_history_attr_years'); ?></td><td><span class="naws-tag-default">3</span></td></tr>
            <tr><td><code>year</code></td><td><?php naws_e('sc_history_attr_year'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>fields</code></td><td><?php naws_e('sc_history_attr_fields'); ?></td><td><span class="naws-tag-default">temp_min,temp_max,temp_avg</span></td></tr>
            <tr><td><code>group_by</code></td><td><?php naws_e('sc_history_attr_group_by'); ?></td><td><span class="naws-tag-default">day</span></td></tr>
            <tr><td><code>title</code></td><td><?php naws_e('sc_history_attr_title'); ?></td><td><span class="naws-tag-default"><?php naws_e('hc_history_title'); ?></span></td></tr>
            <tr><td><code>height</code></td><td><?php naws_e('sc_history_attr_height'); ?></td><td><span class="naws-tag-default">420</span></td></tr>
            <tr><td><code>show_range_picker</code></td><td><?php naws_e('sc_history_attr_picker'); ?></td><td><span class="naws-tag-default">true</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_history year="2025"]</code> &rarr; nur 2025</div>
            <div class="naws-inline-ex"><code>[naws_history year="2023,2025"]</code> &rarr; 2023 &amp; 2025</div>
            <div class="naws-inline-ex"><code>[naws_history fields="rain_sum" group_by="month"]</code> &rarr; Niederschlag/Monat</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_current]</code></h3>
        <p><?php naws_e('sc_current_card_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_current]</pre><button class="naws-copy-btn" data-copy='[naws_current]'><?php naws_e('sc_copy'); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php naws_e('sc_th_attribute'); ?></th><th><?php naws_e('sc_th_description'); ?></th><th><?php naws_e('sc_th_default'); ?></th></tr>
            <tr><td><code>module_id</code></td><td><?php naws_e('sc_current_attr_mid'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>parameters</code></td><td><?php naws_e('sc_current_attr_params'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>layout</code></td><td><?php naws_e('sc_current_attr_layout'); ?></td><td><span class="naws-tag-default">grid</span></td></tr>
            <tr><td><code>title</code></td><td><?php naws_e('sc_current_attr_title'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>animate</code></td><td><?php naws_e('sc_current_attr_animate'); ?></td><td><span class="naws-tag-default">true</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_current layout="list"]</code> &rarr; Listenansicht</div>
            <div class="naws-inline-ex"><code>[naws_current parameters="Temperature,Humidity"]</code> &rarr; nur Temp. &amp; Feuchte</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_table]</code></h3>
        <p><?php naws_e('sc_table_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_table period="24h"]</pre><button class="naws-copy-btn" data-copy='[naws_table period="24h"]'><?php naws_e('sc_copy'); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php naws_e('sc_th_attribute'); ?></th><th><?php naws_e('sc_th_description'); ?></th><th><?php naws_e('sc_th_default'); ?></th></tr>
            <tr><td><code>module_id</code></td><td><?php naws_e('sc_table_attr_mid'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>parameters</code></td><td><?php naws_e('sc_table_attr_params'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>period</code></td><td><?php naws_e('sc_table_attr_period'); ?></td><td><span class="naws-tag-default">24h</span></td></tr>
            <tr><td><code>limit</code></td><td><?php naws_e('sc_table_attr_limit'); ?></td><td><span class="naws-tag-default">100</span></td></tr>
            <tr><td><code>group_by</code></td><td><?php naws_e('sc_table_attr_group_by'); ?></td><td><span class="naws-tag-default">hour</span></td></tr>
            <tr><td><code>title</code></td><td><?php naws_e('sc_table_attr_title'); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_table period="7d" group_by="day"]</code> &rarr; 7 Tage, pro Tag</div>
            <div class="naws-inline-ex"><code>[naws_table parameters="Temperature,Pressure" period="30d"]</code> &rarr; 30 Tage gefiltert</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_infobar]</code></h3>
        <p><?php naws_e('sc_infobar_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_infobar]</pre><button class="naws-copy-btn" data-copy='[naws_infobar]'><?php naws_e('sc_copy'); ?></button></div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_forecast]</code> &ndash; <?php naws_e('sc_forecast_desc'); ?></h3>
        <p><?php naws_e('sc_forecast_long_desc'); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_forecast]</pre><button class="naws-copy-btn" data-copy='[naws_forecast]'><?php naws_e('sc_copy'); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr>
                <th><?php naws_e('sc_th_attribute'); ?></th>
                <th><?php naws_e('sc_th_description'); ?></th>
                <th><?php naws_e('sc_th_default'); ?></th>
            </tr>
            <tr><td><code>days</code></td><td><?php naws_e('sc_forecast_attr_days'); ?></td><td><span class="naws-tag-default"><?php echo esc_html( get_option('naws_settings', [])['forecast_days'] ?? 5 ); ?></span></td></tr>
            <tr><td><code>title</code></td><td><?php naws_e('sc_forecast_attr_title'); ?></td><td><span class="naws-tag-default"><?php echo esc_html( sprintf( naws__('forecast_title'), intval( get_option('naws_settings', [])['forecast_days'] ?? 5 ) ) ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_forecast]</code> <?php naws_e('sc_forecast_ex_default'); ?></div>
            <div class="naws-inline-ex"><code>[naws_forecast days="3" title="Weekend"]</code> <?php naws_e('sc_forecast_ex_custom'); ?></div>
        </div>
    </div>
    </div><!-- /.naws-panel-body -->
</div>

<?php /* ── Module IDs ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php naws_e('sc_section_modules'); ?></h2></div>
    <div class="naws-panel-body">
    <p class="naws-section-intro"><?php naws_e('sc_modules_intro'); ?></p>
    <table class="naws-ref-table">
        <thead>
            <tr>
                <th><?php naws_e('menu_modules'); ?></th>
                <th><?php naws_e('sc_th_type'); ?></th>
                <th><?php naws_e('sc_th_alias'); ?></th>
                <th>module_id (MAC)</th>
                <th><?php naws_e('sc_th_active'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
        $alias_map = [ 'NAModule1'=>'outdoor', 'NAMain'=>'indoor', 'NAModule2'=>'wind', 'NAModule3'=>'rain', 'NAModule4'=>'(MAC)' ];
        foreach ( $modules as $m ) :
            $alias = $alias_map[ $m['module_type'] ] ?? '(MAC)';
        ?>
        <tr>
            <td><?php echo esc_html( $m['module_name'] ); ?></td>
            <td><code><?php echo esc_html( $m['module_type'] ); ?></code></td>
            <td><code><?php echo esc_html( $alias ); ?></code></td>
            <td>
                <div class="naws-copy-wrap">
                    <pre style="font-size:11px"><?php echo esc_html( $m['module_id'] ); ?></pre>
                    <button class="naws-copy-btn" data-copy="<?php echo esc_attr( $m['module_id'] ); ?>"><?php naws_e('sc_copy'); ?></button>
                </div>
            </td>
            <td><?php echo $m['is_active'] ? '&#x2705;' : '&#x274C;'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- /.naws-panel-body -->
</div>

</div>

<script>
(function(){
    var copyLabel   = <?php echo wp_json_encode( naws__('sc_copy') ); ?>;
    var copiedLabel = <?php echo wp_json_encode( naws__('sc_copied') ); ?>;
    document.querySelectorAll('.naws-copy-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
            navigator.clipboard.writeText(this.dataset.copy).then(function(){
                btn.textContent=copiedLabel; btn.classList.add('copied');
                setTimeout(function(){ btn.textContent=copyLabel; btn.classList.remove('copied'); },2000);
            });
        });
    });
}());
</script>
