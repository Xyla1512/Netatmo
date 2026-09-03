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
<?php // Styles moved to assets/css/admin.css ?>

<div class="wrap naws-admin-wrap">
<h1 class="naws-admin-page-title"><span class="naws-title-icon">&#x1F4DD;</span> <?php esc_html_e( 'Shortcode Reference', 'xtx-integration-for-netatmo' ); ?></h1>

<?php /* ── naws_value ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php esc_html_e( '⚡ [naws_value] — Single Inline Value', 'xtx-integration-for-netatmo' ); ?></h2></div>
    <div class="naws-panel-body">
    <p class="naws-section-intro"><?php esc_html_e( 'Returns a single sensor value — ideal for embedding readings directly in text, headings or custom HTML layouts.', 'xtx-integration-for-netatmo' ); ?></p>

    <div class="naws-sc-card">
        <h3><code>[naws_value]</code> &ndash; <?php esc_html_e( '[naws_value] – Attributes', 'xtx-integration-for-netatmo' ); ?></h3>
        <table class="naws-attr-table">
            <tr>
                <th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Possible values', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th>
            </tr>
            <tr><td><code>param</code></td><td><?php esc_html_e( 'Which measurement', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'See table below', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">Temperature</span></td></tr>
            <tr><td><code>module</code></td><td><?php esc_html_e( 'Which module', 'xtx-integration-for-netatmo' ); ?></td><td><code>outdoor</code> &middot; <code>indoor</code> &middot; <code>wind</code> &middot; <code>rain</code> &middot; MAC</td><td><span class="naws-tag-default">outdoor</span></td></tr>
            <tr><td><code>unit</code></td><td><?php esc_html_e( 'Append unit', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( '1 = yes · 0 = number only', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">1</span></td></tr>
            <tr><td><code>decimals</code></td><td><?php esc_html_e( 'Decimal places', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( '0 · 1 · 2 · -1 = default', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">-1</span></td></tr>
            <tr><td><code>fallback</code></td><td><?php esc_html_e( 'Text when no value available', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'Any text', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">--</span></td></tr>
            <tr><td><code>tag</code></td><td><?php esc_html_e( 'HTML wrapper tag', 'xtx-integration-for-netatmo' ); ?></td><td><code>span</code> &middot; <code>div</code> &middot; <code>p</code> &middot; <code>strong</code> &middot; <code>none</code></td><td><span class="naws-tag-default">span</span></td></tr>
            <tr><td><code>class</code></td><td><?php esc_html_e( 'CSS class on wrapper', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'Any CSS class(es)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_value param="Temperature" module="outdoor"]</code> &rarr; <strong><?php echo esc_html( naws_live_val( $live_map, 'NAModule1', 'Temperature' ) ); ?></strong></div>
            <div class="naws-inline-ex"><code>[naws_value param="Humidity" module="outdoor" unit="0"]</code> <?php esc_html_e( '→ number only without unit', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_value param="Pressure" decimals="0"]</code> <?php esc_html_e( '→ integer', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_value param="Temperature" tag="strong" class="my-temp"]</code> <?php esc_html_e( '→ with HTML wrapper', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_value param="rain_rolling_24h" fallback="&ndash;"]</code> <?php esc_html_e( '→ rolling 24h sum', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
    </div>

    <h3 style="font-size:14px;margin:20px 0 10px;color:#1e293b;"><?php esc_html_e( 'All available parameters for [naws_value]', 'xtx-integration-for-netatmo' ); ?></h3>
    <table class="naws-ref-table">
        <thead>
            <tr>
                <th>param=</th>
                <th>module=</th>
                <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Unit', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Live value now', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Example shortcode', 'xtx-integration-for-netatmo' ); ?></th>
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
            <td><?php echo esc_html( naws_label( $lang_key ) ); ?></td>
            <td><?php echo esc_html( $unit ?: '&ndash;' ); ?></td>
            <td><span class="naws-live-badge"><?php echo esc_html( $live ); ?></span></td>
            <td>
                <div class="naws-copy-wrap">
                    <pre><?php echo esc_html( $sc ); ?></pre>
                    <button class="naws-copy-btn" data-copy="<?php echo esc_attr( $sc ); ?>"><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php /* ── naws_calc ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php esc_html_e( 'Computed values', 'xtx-integration-for-netatmo' ); ?></h2></div>
    <div class="naws-panel-body">
    <p class="naws-section-intro"><?php echo wp_kses_post( __( 'One shortcode for every computed value. The value goes in the <code>value</code> attribute — which of the other attributes apply depends on its kind (instant value, day class, sum or index); see the table below.', 'xtx-integration-for-netatmo' ) ); ?></p>

    <div class="naws-sc-card">
        <h3><?php esc_html_e( 'Additional attributes', 'xtx-integration-for-netatmo' ); ?></h3>
        <table class="naws-attr-table">
            <tr>
                <th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Possible values', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Applies to', 'xtx-integration-for-netatmo' ); ?></th>
            </tr>
            <tr><td><code>module</code></td><td><?php esc_html_e( 'Which module', 'xtx-integration-for-netatmo' ); ?></td><td><code>outdoor</code> &middot; <code>indoor</code> &middot; <code>wind</code> &middot; <code>rain</code> &middot; MAC</td><td><?php esc_html_e( 'instant values', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>station</code></td><td><?php esc_html_e( 'Which station to read, by module or station ID.', 'xtx-integration-for-netatmo' ); ?></td><td><code>module_id</code> / <code>station_id</code></td><td><?php esc_html_e( 'day classes, sums, index', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>period</code></td><td><?php esc_html_e( 'The date range to count or sum over.', 'xtx-integration-for-netatmo' ); ?></td><td><code>year</code> &middot; <code>month</code> &middot; <code>Nd</code> &middot; <code>all</code></td><td><?php esc_html_e( 'day classes, sums, index', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>year</code></td><td><?php esc_html_e( 'A single calendar year; overrides period when set.', 'xtx-integration-for-netatmo' ); ?></td><td><code>2024</code></td><td><?php esc_html_e( 'day classes, sums, index', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>note</code></td><td><?php esc_html_e( 'Appends a note stating how many days of the period actually carry data.', 'xtx-integration-for-netatmo' ); ?></td><td><code>1</code></td><td><?php esc_html_e( 'day classes, sums, index', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>mode</code></td><td><?php esc_html_e( 'How day classes are counted.', 'xtx-integration-for-netatmo' ); ?></td><td><code>count</code> &middot; <code>streak</code> &middot; <code>max_streak</code></td><td><?php esc_html_e( 'day classes only', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>base</code></td><td><?php esc_html_e( 'The threshold temperature the sum is measured against.', 'xtx-integration-for-netatmo' ); ?></td><td>°C</td><td><?php esc_html_e( 'sums (hdd, cdd, gdd)', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>cap</code></td><td><?php esc_html_e( 'The upper temperature the sum is capped at.', 'xtx-integration-for-netatmo' ); ?></td><td>°C</td><td><?php esc_html_e( 'sums (gdd)', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>months</code></td><td><?php esc_html_e( 'The window length of the index, in months.', 'xtx-integration-for-netatmo' ); ?></td><td><code>1</code> &middot; <code>3</code> &middot; <code>6</code> &middot; <code>12</code></td><td><?php esc_html_e( 'index only', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>unit</code></td><td><?php esc_html_e( 'Append unit', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( '1 = yes · 0 = number only', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'everything', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>decimals</code></td><td><?php esc_html_e( 'Decimal places', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( '0 · 1 · 2 · -1 = default', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'everything', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>fallback</code></td><td><?php esc_html_e( 'Text when no value available', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'Any text', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'everything', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>tag</code></td><td><?php esc_html_e( 'HTML wrapper tag', 'xtx-integration-for-netatmo' ); ?></td><td><code>span</code> &middot; <code>div</code> &middot; <code>p</code> &middot; <code>strong</code> &middot; <code>none</code></td><td><?php esc_html_e( 'everything', 'xtx-integration-for-netatmo' ); ?></td></tr>
            <tr><td><code>class</code></td><td><?php esc_html_e( 'CSS class on wrapper', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'Any CSS class(es)', 'xtx-integration-for-netatmo' ); ?></td><td><?php esc_html_e( 'everything', 'xtx-integration-for-netatmo' ); ?></td></tr>
        </table>
    </div>

    <table class="wp-list-table widefat striped naws-list-table">
        <thead>
            <tr>
                <th><?php echo esc_html( _x( 'Shortcode', 'sc_calc_col_key', 'xtx-integration-for-netatmo' ) ); ?></th>
                <th><?php esc_html_e( 'Meaning', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Right now', 'xtx-integration-for-netatmo' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( NAWS_Calc::catalogue() as $calc_key => $calc_entry ) : ?>
            <tr>
                <td><code>[naws_calc value="<?php echo esc_attr( $calc_key ); ?>"]</code></td>
                <td><?php echo esc_html( naws_label( $calc_entry['label'] ) ); ?></td>
                <td><?php echo do_shortcode( '[naws_calc value="' . esc_attr( $calc_key ) . '" tag="none"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sc_calc() escapes its own output ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php /* Spec 8.3: the index is the one value whose worth depends on how
             long the record is, so the page says how long this record is. */ ?>
    <?php $naws_spi_basis = NAWS_Calc::spi_basis(); ?>
    <p class="naws-section-intro"><?php
        echo esc_html( sprintf(
            /* translators: 1: number of complete months of rain data, 2: the same span in full years. */ __( 'SPI data basis: %1$d complete months of gap-free rain measurement (%2$d full years). It computes from 24 months on; the customary reference is about 30 years — below that the index is a tendency rather than a measurement.', 'xtx-integration-for-netatmo' ),
            $naws_spi_basis['months'],
            $naws_spi_basis['years']
        ) );
    ?></p>
    </div><!-- /.naws-panel-body -->
</div>

<?php /* ── Layout Shortcodes ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php esc_html_e( '🧩 Layout Shortcodes', 'xtx-integration-for-netatmo' ); ?></h2></div>
    <p class="naws-section-intro"><?php esc_html_e( 'Ready-made, complete widgets with Chart.js visualizations and automatic data refresh.', 'xtx-integration-for-netatmo' ); ?></p>

    <div class="naws-sc-card">
        <h3><code>[naws_live]</code></h3>
        <p><?php esc_html_e( 'Full live dashboard with tiles, daily charts and wind rose. Automatic AJAX refresh.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_live refresh="60"]</pre><button class="naws-copy-btn" data-copy='[naws_live refresh="60"]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Station title', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'Station name from Netatmo', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>refresh</code></td><td><?php esc_html_e( 'Auto-refresh in seconds (0 = disabled)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">60</span></td></tr>
        </table>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_history]</code></h3>
        <p><?php esc_html_e( 'Interactive annual comparison charts for temperature, pressure and precipitation.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_history years="3"]</pre><button class="naws-copy-btn" data-copy='[naws_history years="3"]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>years</code></td><td><?php esc_html_e( 'Number of comparison years', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">3</span></td></tr>
            <tr><td><code>year</code></td><td><?php esc_html_e( 'Specific year(s), e.g. "2025" or "2023,2025". Empty = all available years.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>fields</code></td><td><?php esc_html_e( 'Comma-separated fields: temp_min, temp_max, temp_avg, pressure_avg, rain_sum', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">temp_min,temp_max,temp_avg</span></td></tr>
            <tr><td><code>group_by</code></td><td><?php esc_html_e( 'Grouping: day, week, month, year', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">day</span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Title above the chart', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'Historical Weather Data', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>height</code></td><td><?php esc_html_e( 'Chart height in px', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">420</span></td></tr>
            <tr><td><code>show_range_picker</code></td><td><?php esc_html_e( 'Show range picker (true/false)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">true</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_history year="2025"]</code> &rarr; nur 2025</div>
            <div class="naws-inline-ex"><code>[naws_history year="2023,2025"]</code> &rarr; 2023 &amp; 2025</div>
            <div class="naws-inline-ex"><code>[naws_history fields="rain_sum" group_by="month"]</code> &rarr; Niederschlag/Monat</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_heatmap]</code></h3>
        <p><?php esc_html_e( 'One year of outdoor daily average temperature as a calendar grid: months down, days across, one coloured tile per day.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_heatmap]</pre><button class="naws-copy-btn" data-copy='[naws_heatmap]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>year</code></td><td><?php esc_html_e( 'Year to open with. A year with no readings falls back to the current one.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'current year', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Title above the grid', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php esc_html_e( 'Daily Average Temperature', 'xtx-integration-for-netatmo' ); ?></span></td></tr>
            <tr><td><code>legend</code></td><td><?php esc_html_e( 'Show the colour scale below the grid (yes/no)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">yes</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_heatmap year="2025"]</code> &rarr; startet mit 2025</div>
            <div class="naws-inline-ex"><code>[naws_heatmap legend="no"]</code> &rarr; ohne Farbskala</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_current]</code></h3>
        <p><?php esc_html_e( 'Shows animated metric cards with the latest sensor values from all or specific modules.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_current]</pre><button class="naws-copy-btn" data-copy='[naws_current]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>module_id</code></td><td><?php esc_html_e( 'Module MAC (empty = all modules)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>parameters</code></td><td><?php esc_html_e( 'Comma-separated parameters to filter', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>layout</code></td><td><?php esc_html_e( 'Layout: grid or list', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">grid</span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Optional title', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>animate</code></td><td><?php esc_html_e( 'Enable animation (true/false)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">true</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_current layout="list"]</code> &rarr; Listenansicht</div>
            <div class="naws-inline-ex"><code>[naws_current parameters="Temperature,Humidity"]</code> &rarr; nur Temp. &amp; Feuchte</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_table]</code></h3>
        <p><?php esc_html_e( 'Tabular view of historical readings with configurable time period and grouping.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_table period="24h"]</pre><button class="naws-copy-btn" data-copy='[naws_table period="24h"]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr><th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th><th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th></tr>
            <tr><td><code>module_id</code></td><td><?php esc_html_e( 'Module MAC (empty = all modules)', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>parameters</code></td><td><?php esc_html_e( 'Comma-separated parameters to filter', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
            <tr><td><code>period</code></td><td><?php esc_html_e( 'Time period, e.g. 24h, 7d, 30d', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">24h</span></td></tr>
            <tr><td><code>limit</code></td><td><?php esc_html_e( 'Max. number of records', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">100</span></td></tr>
            <tr><td><code>group_by</code></td><td><?php esc_html_e( 'Grouping: hour, day, week, month, year. Any other value lists single readings.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">hour</span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Optional table title', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">&ndash;</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_table period="7d" group_by="day"]</code> &rarr; 7 Tage, pro Tag</div>
            <div class="naws-inline-ex"><code>[naws_table parameters="Temperature,Pressure" period="30d"]</code> &rarr; 30 Tage gefiltert</div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_infobar]</code></h3>
        <p><?php esc_html_e( 'Compact info bar with calculated values (feels like, dew point) and astronomical data (rise/set, moon phase, supermoon, lunar eclipse).', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_infobar]</pre><button class="naws-copy-btn" data-copy='[naws_infobar]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_forecast]</code> &ndash; <?php esc_html_e( 'Shows a weather forecast based on station location.', 'xtx-integration-for-netatmo' ); ?></h3>
        <p><?php esc_html_e( 'Displays a multi-day weather forecast with temperature, precipitation, wind and weather condition. The number of days and location are configurable in the plugin settings.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_forecast]</pre><button class="naws-copy-btn" data-copy='[naws_forecast]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr>
                <th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th>
            </tr>
            <tr><td><code>days</code></td><td><?php esc_html_e( 'Number of forecast days (1–7). Defaults to value from settings.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo esc_html( get_option('naws_settings', [])['forecast_days'] ?? 5 ); ?></span></td></tr>
            <tr><td><code>title</code></td><td><?php esc_html_e( 'Custom title for the forecast header.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo esc_html( sprintf( /* translators: %d: number of forecast days. */ __( '%d-Day Forecast', 'xtx-integration-for-netatmo' ), intval( get_option('naws_settings', [])['forecast_days'] ?? 5 ) ) ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_forecast]</code> <?php esc_html_e( '→ Uses days and location from settings', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_forecast days="3" title="Weekend"]</code> <?php esc_html_e( '→ 3 days with custom title', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_weather_icon]</code> &ndash; <?php esc_html_e( 'Current weather as an animated icon', 'xtx-integration-for-netatmo' ); ?></h3>
        <p><?php esc_html_e( 'Shows the current weather state as an animated icon. Deliberately without a visible caption — the state is carried by the aria-label for screen readers. If the icon sits on the same page as the live dashboard it refreshes along with that page\'s polling cycle. When no state can be determined, nothing is output.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_weather_icon]</pre><button class="naws-copy-btn" data-copy='[naws_weather_icon]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr>
                <th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th>
            </tr>
            <tr><td><code>size</code></td><td><?php esc_html_e( 'Edge length in pixels. Values below 64 are raised to 64; below that the states are no longer distinguishable.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default">96</span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_weather_icon]</code> <?php esc_html_e( '96 px, centred', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_weather_icon size="140"]</code> <?php esc_html_e( 'larger, e.g. as a header graphic', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
        <?php
        // Live preview: show what the icon looks like right now, so the page
        // also answers "is it working at all" without leaving the backend.
        if ( class_exists( 'NAWS_Weather_State' ) ) :
            $naws_sc_wx = NAWS_Weather_State::get_current();
            ?>
            <div style="margin-top:14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <?php if ( $naws_sc_wx['state'] !== '' ) : ?>
                    <?php
                    // The stylesheet is enqueued by NAWS_Admin::enqueue_assets()
                    // for this page; 'naws-frontend' itself is only registered
                    // on wp_enqueue_scripts and does not exist in the admin.
                    // Literal template markup, never a kses-filtered string.
                    echo NAWS_Weather_Icons::render( $naws_sc_wx['state'], 72 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG, see templates/weather-icon.php
                    ?>
                    <div>
                        <strong><?php echo esc_html( NAWS_Weather_Icons::label( $naws_sc_wx['state'] ) ); ?></strong><br>
                        <small style="color:#64748b">
                            <?php esc_html_e( 'Source', 'xtx-integration-for-netatmo' ); ?>:
                            <code><?php echo esc_html( $naws_sc_wx['source'] ); ?></code>
                            <?php if ( $naws_sc_wx['wmo'] !== null ) : ?>
                                &middot; WMO <?php echo intval( $naws_sc_wx['wmo'] ); ?>
                            <?php endif; ?>
                            <?php if ( $naws_sc_wx['stale'] ) : ?>
                                &middot; <?php esc_html_e( 'forecast stale, last known value', 'xtx-integration-for-netatmo' ); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php else : ?>
                    <small style="color:#64748b"><?php esc_html_e( 'No state determinable right now — neither station readings nor forecast are available. Nothing would be output.', 'xtx-integration-for-netatmo' ); ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="naws-sc-card">
        <h3><code>[naws_weather_widget]</code> &ndash; <?php esc_html_e( 'Compact weather widget for sidebars', 'xtx-integration-for-netatmo' ); ?></h3>
        <p><?php esc_html_e( 'Icon and outdoor temperature, rain and wind below that, then the forecast. Width adjustable between 250 and 500 pixels, with the contents scaling to match. A missing add-on module drops its value entirely. Rendered on page load and does not refresh by itself — the time in the footer shows its age.', 'xtx-integration-for-netatmo' ); ?></p>
        <div class="naws-copy-wrap"><pre>[naws_weather_widget]</pre><button class="naws-copy-btn" data-copy='[naws_weather_widget]'><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button></div>
        <table class="naws-attr-table" style="margin-top:10px">
            <tr>
                <th><?php esc_html_e( 'Attribute', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Default', 'xtx-integration-for-netatmo' ); ?></th>
            </tr>
            <tr><td><code>days</code></td><td><?php esc_html_e( 'Forecast length. Only 3 or 5; other values are pulled to the nearer one.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo intval( get_option('naws_settings', [])['wgt_days'] ?? 5 ); ?></span></td></tr>
            <tr><td><code>width</code></td><td><?php esc_html_e( 'Width in pixels, 250 to 500. Values outside that range are pulled to the nearer bound.', 'xtx-integration-for-netatmo' ); ?></td><td><span class="naws-tag-default"><?php echo absint( NAWS_Widget_Data::normalise_width( get_option('naws_settings', [])['wgt_width'] ?? null ) ); ?></span></td></tr>
        </table>
        <div class="naws-inline-examples">
            <div class="naws-inline-ex"><code>[naws_weather_widget]</code> <?php esc_html_e( 'uses the backend setting', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_weather_widget days="3"]</code> <?php esc_html_e( 'shorter, for very narrow columns', 'xtx-integration-for-netatmo' ); ?></div>
            <div class="naws-inline-ex"><code>[naws_weather_widget width="400"]</code> <?php esc_html_e( 'wider, with a larger icon and larger figures', 'xtx-integration-for-netatmo' ); ?></div>
        </div>
    </div>
    </div><!-- /.naws-panel-body -->
</div>

<?php /* ── Module IDs ── */ ?>
<div class="naws-admin-panel naws-ref-section">
    <div class="naws-panel-header"><h2><?php esc_html_e( '📡 Available Modules (current installation)', 'xtx-integration-for-netatmo' ); ?></h2></div>
    <div class="naws-panel-body">
    <p class="naws-section-intro"><?php esc_html_e( 'These module IDs can be used directly in shortcodes as module_id= or module="MAC-address".', 'xtx-integration-for-netatmo' ); ?></p>
    <table class="naws-ref-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Modules', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Type', 'xtx-integration-for-netatmo' ); ?></th>
                <th><?php esc_html_e( 'Alias', 'xtx-integration-for-netatmo' ); ?></th>
                <th>module_id (MAC)</th>
                <th><?php esc_html_e( 'Active', 'xtx-integration-for-netatmo' ); ?></th>
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
                    <button class="naws-copy-btn" data-copy="<?php echo esc_attr( $m['module_id'] ); ?>"><?php echo esc_html( _x( 'Copy', 'sc_copy', 'xtx-integration-for-netatmo' ) ); ?></button>
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

<?php
wp_add_inline_script( 'naws-admin', <<<'EOJS'
(function(){
    var copyLabel   = nawsAdmin.strings.sc_copy;
    var copiedLabel = nawsAdmin.strings.sc_copied;
    document.querySelectorAll('.naws-copy-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
            navigator.clipboard.writeText(this.dataset.copy).then(function(){
                btn.textContent=copiedLabel; btn.classList.add('copied');
                setTimeout(function(){ btn.textContent=copyLabel; btn.classList.remove('copied'); },2000);
            });
        });
    });
}());
EOJS
);
?>
