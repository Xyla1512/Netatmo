<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
/**
 * Template: [naws_live title="" refresh="60"]
 * v0.9.28 – per-sensor tiles, NAModule4 namespacing, correct outdoor temp
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$widget_id      = 'naws-live-' . wp_unique_id();
$hidden         = (array) get_option( 'naws_live_hidden_params',  [] );
$hidden_modules = (array) get_option( 'naws_live_hidden_modules', [] );

// Resolve all active modules
$all_modules = NAWS_Database::get_modules( true );
$outdoor_id  = '';
$indoor_id   = '';
foreach ( $all_modules as $m ) {
    if ( $m['module_type'] === 'NAModule1' ) { $outdoor_id = $m['module_id']; }
    if ( $m['module_type'] === 'NAMain'    ) { $indoor_id  = $m['module_id']; }
}
$humidity_id = $outdoor_id ?: $indoor_id;

// ── NAModule4 slug map: module_id → slug (e.g. "gast", "sleeping") ──────────
// Slug is derived from module_name: lowercase, only [a-z0-9], max 16 chars.
// Passed to JS so indexReadings() can namespace their parameters correctly.
$module4_slugs = []; // [ module_id => slug ]
$module4_info  = []; // [ slug => ['id'=>…, 'name'=>…, 'params'=>[…]] ]
foreach ( $all_modules as $m ) {
    if ( $m['module_type'] !== 'NAModule4' ) continue;
    $slug = preg_replace( '/[^a-z0-9]/', '', strtolower( $m['module_name'] ) );
    if ( $slug === '' ) $slug = 'indoor' . substr( str_replace( ':', '', $m['module_id'] ), -4 );
    $slug = substr( $slug, 0, 16 );
    $module4_slugs[ $m['module_id'] ] = $slug;
    $module4_info[ $slug ] = [
        'id'     => $m['module_id'],
        'name'   => $m['module_name'],
        'params' => [ "Temperature_{$slug}", "Humidity_{$slug}", "CO2_{$slug}", "Noise_{$slug}" ],
    ];
}

// ── Hidden-params expansion: module toggle → hide all its params ─────────────
$module_param_map = [
    'NAMain'    => [ 'Temperature_indoor', 'Pressure', 'AbsolutePressure', 'CO2', 'Noise' ],
    'NAModule1' => [ 'Temperature', 'min_temp', 'max_temp', 'Humidity' ],
    'NAModule2' => [ 'WindStrength', 'GustStrength', 'WindAngle', 'GustAngle' ],
    'NAModule3' => [ 'Rain', 'sum_rain_1', 'sum_rain_24' ],
];
// Add NAModule4 slugs dynamically
foreach ( $module4_info as $slug => $info ) {
    $module_param_map[ "NAModule4_{$slug}" ] = $info['params'];
}
foreach ( $hidden_modules as $hmod ) {
    if ( isset( $module_param_map[ $hmod ] ) ) {
        $hidden = array_unique( array_merge( $hidden, $module_param_map[ $hmod ] ) );
    }
}

$nonce    = wp_create_nonce( 'naws_public_nonce' );
$ajax_url = admin_url( 'admin-ajax.php' );

// ── Charts: which sensors get a 24h graph? ───────────────────────────────────
$hidden_charts = (array) get_option( 'naws_live_hidden_charts', [] );

// Resolve module IDs we need for chart queries
$wind_id = '';
$rain_id = '';
foreach ( $all_modules as $m ) {
    if ( $m['module_type'] === 'NAModule2' ) $wind_id = $m['module_id'];
    if ( $m['module_type'] === 'NAModule3' ) $rain_id = $m['module_id'];
}

// Master sensor→chart config: display_key, db_param, module_id, label, unit, type, color
// Units are always read from settings via NAWS_Helpers::get_unit()
$sensor_chart_configs = [];

// NAModule1 – outdoor
if ( $outdoor_id ) {
    $sensor_chart_configs[] = [ 'key'=>'Temperature',    'param'=>'Temperature', 'module_id'=>$outdoor_id, 'label'=>naws__( 'chart_temp_out' ),        'unit'=>NAWS_Helpers::get_unit('Temperature'),   'type'=>'line', 'color'=>NAWS_Colors::get('chart_temp_outdoor') ];
    $sensor_chart_configs[] = [ 'key'=>'Humidity',       'param'=>'Humidity',    'module_id'=>$outdoor_id, 'label'=>naws__( 'chart_humid_out' ),       'unit'=>'%',                                    'type'=>'line', 'color'=>NAWS_Colors::get('chart_humidity_outdoor') ];
}
// NAMain – indoor base
if ( $indoor_id ) {
    $sensor_chart_configs[] = [ 'key'=>'Temperature_indoor', 'param'=>'Temperature', 'module_id'=>$indoor_id, 'label'=>naws__( 'chart_temp_base' ),   'unit'=>NAWS_Helpers::get_unit('Temperature'),   'type'=>'line', 'color'=>NAWS_Colors::get('chart_temp_indoor') ];
    $sensor_chart_configs[] = [ 'key'=>'Pressure',           'param'=>'Pressure',    'module_id'=>$indoor_id, 'label'=>naws__( 'chart_pressure' ),     'unit'=>NAWS_Helpers::get_unit('Pressure'),      'type'=>'line', 'color'=>NAWS_Colors::get('chart_pressure') ];
    $sensor_chart_configs[] = [ 'key'=>'CO2',                'param'=>'CO2',         'module_id'=>$indoor_id, 'label'=>naws__( 'chart_co2_base' ),     'unit'=>'ppm',                                  'type'=>'line', 'color'=>NAWS_Colors::get('chart_co2') ];
    $sensor_chart_configs[] = [ 'key'=>'Noise',              'param'=>'Noise',       'module_id'=>$indoor_id, 'label'=>naws__( 'chart_noise_base' ),   'unit'=>'dB',                                   'type'=>'line', 'color'=>NAWS_Colors::get('chart_noise') ];
}
// NAModule2 – wind
if ( $wind_id ) {
    $sensor_chart_configs[] = [ 'key'=>'WindStrength', 'param'=>'WindStrength', 'module_id'=>$wind_id, 'label'=>naws__( 'chart_wind' ),  'unit'=>NAWS_Helpers::get_unit('WindStrength'), 'type'=>'line', 'color'=>NAWS_Colors::get('chart_wind') ];
    $sensor_chart_configs[] = [ 'key'=>'GustStrength', 'param'=>'GustStrength', 'module_id'=>$wind_id, 'label'=>naws__( 'chart_gusts' ), 'unit'=>NAWS_Helpers::get_unit('GustStrength'), 'type'=>'line', 'color'=>NAWS_Colors::get('chart_gusts') ];
}
// NAModule3 – rain
if ( $rain_id ) {
    $sensor_chart_configs[] = [ 'key'=>'Rain', 'param'=>'sum_rain_1', 'module_id'=>$rain_id, 'label'=>naws__( 'chart_rain_hourly' ), 'unit'=>NAWS_Helpers::get_unit('Rain'), 'type'=>'bar', 'color'=>NAWS_Colors::get('chart_rain') ];
}
// NAModule4 – dynamic
foreach ( $module4_info as $slug => $info ) {
    $mid = $info['id'];
    $name = $info['name'];
    $sensor_chart_configs[] = [ 'key'=>"Temperature_{$slug}", 'param'=>'Temperature', 'module_id'=>$mid, 'label'=>naws__( 'chart_temp_prefix' )." {$name}",   'unit'=>NAWS_Helpers::get_unit('Temperature'), 'type'=>'line', 'color'=>NAWS_Colors::get('chart_module4_temp') ];
    $sensor_chart_configs[] = [ 'key'=>"Humidity_{$slug}",    'param'=>'Humidity',    'module_id'=>$mid, 'label'=>naws__( 'chart_humid_prefix' )." {$name}", 'unit'=>'%',                                   'type'=>'line', 'color'=>NAWS_Colors::get('chart_module4_humidity') ];
    $sensor_chart_configs[] = [ 'key'=>"CO2_{$slug}",         'param'=>'CO2',         'module_id'=>$mid, 'label'=>naws__( 'chart_co2_prefix' )." {$name}",   'unit'=>'ppm',                                 'type'=>'line', 'color'=>NAWS_Colors::get('chart_module4_co2') ];
}

// Filter: only sensors that are visible (not hidden_params) AND chart is active (not hidden_charts)
$chart_configs = [];
foreach ( $sensor_chart_configs as $c ) {
    if ( in_array( $c['key'], $hidden,        true ) ) continue; // sensor kachel hidden
    if ( in_array( $c['key'], $hidden_charts, true ) ) continue; // chart explicitly hidden
    $chart_configs[] = $c;
}

// ── Pressure trend: value 3 hours ago vs current live reading ──
if ( ! function_exists( 'naws_calc_pressure_trend' ) ) :
function naws_calc_pressure_trend() {
    global $wpdb;
    $t_read     = $wpdb->prefix . NAWS_TABLE_READINGS;
    $three_h_ago = time() - ( 3 * HOUR_IN_SECONDS );

    // Helper: get latest reading for a parameter
    $latest = function( $param ) use ( $wpdb, $t_read ) {
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT value FROM {$t_read}
             WHERE parameter = %s
             ORDER BY recorded_at DESC LIMIT 1",
            $param
        ) );
    };

    // Helper: get reading closest to 3 hours ago for a parameter
    $three_hours = function( $param ) use ( $wpdb, $t_read, $three_h_ago ) {
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT value FROM {$t_read}
             WHERE parameter = %s
               AND recorded_at <= %d
             ORDER BY recorded_at DESC LIMIT 1",
            $param, $three_h_ago
        ) );
    };

    // Prefer sea-level Pressure, fallback to AbsolutePressure
    $now_val  = $latest( 'Pressure' )       ?: $latest( 'AbsolutePressure' );
    $then_val = $three_hours( 'Pressure' )  ?: $three_hours( 'AbsolutePressure' );

    if ( ! $now_val || ! $then_val ) {
        return array( 'trend' => 'stable', 'diff' => 0.0 );
    }

    $diff = round( floatval( $now_val ) - floatval( $then_val ), 1 );
    if      ( $diff >  1.5 ) $trend = 'up';
    elseif  ( $diff < -1.5 ) $trend = 'down';
    else                     $trend = 'stable';

    return array( 'trend' => $trend, 'diff' => $diff );
}
endif; // naws_calc_pressure_trend
$_pt           = naws_calc_pressure_trend();
$pressure_trend = $_pt['trend'];
$pressure_diff  = $_pt['diff'];

// $chart_configs already built above from $sensor_chart_configs
?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- must load synchronously before inline Chart.js code ?>
<script src="<?php echo esc_url( NAWS_PLUGIN_URL . 'assets/vendor/chart.umd.min.js' ); ?>"></script>

<div id="<?php echo esc_attr($widget_id); ?>" class="naws-wx" data-icon-set="<?php echo esc_attr( NAWS_Icons::get_current_set() ); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-ajax="<?php echo esc_attr($ajax_url); ?>"
     data-refresh="<?php echo esc_attr($atts['refresh'] ?? '60'); ?>"
     data-hidden="<?php echo esc_attr(implode(',', $hidden)); ?>"
     data-indoor="<?php echo esc_attr($indoor_id); ?>"
     data-module4="<?php echo esc_attr(wp_json_encode($module4_slugs)); ?>">

  <div class="naws-hdr">
    <div class="naws-hdr-name"><?php
        $station_title = $atts['title'];
        if ( ! $station_title ) {
            $opts = get_option( 'naws_settings', [] );
            $station_title = ! empty( $opts['station_name'] ) ? $opts['station_name'] : get_bloginfo( 'name' );
        }
        echo esc_html( $station_title );
    ?></div>
    <div class="naws-hdr-meta">
      <span class="naws-pulse" id="<?php echo esc_attr($widget_id); ?>-pulse"
            <?php if ( get_option('naws_auth_required') || empty(get_option('naws_access_token')) ) echo 'style="background:#e57373;animation:none;"'; ?>
      ></span><?php
        if ( get_option('naws_auth_required') || empty(get_option('naws_access_token')) ) {
            echo '<span style="color:#e57373;font-size:11px;font-weight:600;">'.esc_html( naws__( 'live_disconnected' ) ).'</span>';
        } else {
            echo esc_html( naws__( 'live_connected' ) );
        }
      ?>&nbsp;·&nbsp; <span class="naws-ts">—</span>
    </div>
  </div>

  <div class="naws-body">
    <div id="<?php echo esc_attr($widget_id); ?>-live">
      <div class="naws-loading"><div class="naws-spin"></div></div>
    </div>

    <?php if ( ! empty($chart_configs) ) : ?>
    <div id="<?php echo esc_attr($widget_id); ?>-charts" style="display:none">
      <div class="naws-section-title"><?php naws_e( 'daily_range_title' ); ?></div>
      <div class="naws-charts-grid">
        <?php foreach ($chart_configs as $cfg) :
            $cid = esc_attr( $widget_id . '-' . preg_replace('/[^a-z0-9]/i','-',$cfg['key']) );
        ?>
        <div class="naws-chart-card" data-chart-id="<?php echo esc_attr( $cid ); ?>" data-chart-label="<?php echo esc_attr($cfg['label']); ?>">
          <div class="naws-chart-hdr">
            <div class="naws-chart-lbl"><?php echo esc_html($cfg['label']); ?></div>
            <button class="naws-chart-expand" aria-label="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>" data-chart-id="<?php echo esc_attr( $cid ); ?>" data-label="<?php echo esc_attr($cfg['label']); ?>">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
            </button>
          </div>
          <canvas id="<?php echo esc_attr( $cid ); ?>" height="110"></canvas>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php
    // ── 5-Day Forecast Section (server-rendered, cached) ────────────────
    $forecast = NAWS_Forecast::get_forecast( max( 1, min( 7, intval( get_option( 'naws_settings', [] )['forecast_days'] ?? 5 ) ) ) );
    if ( ! isset( $forecast['error'] ) && ! empty( $forecast['days'] ) ) :
        $fc_options    = get_option( 'naws_settings', [] );
        $fc_temp_unit  = ( $fc_options['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
        $fc_wind_u     = $fc_options['wind_unit'] ?? 'kmh';
        $fc_wind_label = NAWS_Helpers::wind_unit_label_public( $fc_wind_u );
        $fc_rain_unit  = ( $fc_options['rain_unit'] ?? 'mm' ) === 'in' ? 'in' : 'mm';
        $fc_loc_name   = $forecast['location_name'] ?? '';
        $fc_day_count  = count( $forecast['days'] );
        $fc_title      = sprintf( naws__( 'forecast_title' ), $fc_day_count );
    ?>
    <div style="margin-top:16px;">
      <!-- Forecast Header -->
      <div class="naws-fc-header">
        <div class="naws-fc-header-title"><?php echo esc_html( $fc_title ); ?></div>
        <div class="naws-fc-header-meta">
          <?php if ( $fc_loc_name ) : ?>
            <span>📍 <?php echo esc_html( $fc_loc_name ); ?></span>
          <?php endif; ?>
          <?php if ( ! empty( $forecast['fetched_at'] ) ) : ?>
            <span><?php printf( esc_html( naws__( 'forecast_updated' ) ), esc_html( wp_date( 'H:i', $forecast['fetched_at'] ) ) ); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Forecast Body -->
      <div class="naws-fc-body-wrap">
        <div class="naws-live-forecast-grid" style="--naws-fc-cols:<?php echo intval( $fc_day_count ); ?>">
          <?php foreach ( $forecast['days'] as $fc_day ) :
              $fc_wmo    = NAWS_Forecast::wmo_description( $fc_day['weathercode'] );
              $fc_today  = NAWS_Forecast::is_today( $fc_day['date'] );
              $fc_wd     = $fc_today ? naws__( 'forecast_today' ) : NAWS_Forecast::weekday_short( $fc_day['date'] );
              $fc_dt     = NAWS_Forecast::date_short( $fc_day['date'] );
              $fc_tmax   = $fc_day['temp_max'];
              $fc_tmin   = $fc_day['temp_min'];
              if ( $fc_temp_unit === '°F' ) {
                  $fc_tmax = $fc_tmax !== null ? round( $fc_tmax * 9 / 5 + 32, 1 ) : null;
                  $fc_tmin = $fc_tmin !== null ? round( $fc_tmin * 9 / 5 + 32, 1 ) : null;
              }
              $fc_wmax = $fc_day['wind_max'];
              if ( $fc_wind_u === 'ms' )  $fc_wmax = $fc_wmax !== null ? round( $fc_wmax / 3.6, 1 ) : null;
              if ( $fc_wind_u === 'mph' ) $fc_wmax = $fc_wmax !== null ? round( $fc_wmax * 0.62137, 1 ) : null;
              if ( $fc_wind_u === 'kn' )  $fc_wmax = $fc_wmax !== null ? round( $fc_wmax * 0.53996, 1 ) : null;
              $fc_precip = $fc_day['precip_sum'];
              if ( $fc_rain_unit === 'in' && $fc_precip !== null ) $fc_precip = round( $fc_precip / 25.4, 2 );
              $fc_compass = NAWS_Helpers::degrees_to_compass( $fc_day['wind_dir'] );
          ?>
          <div class="naws-fcc<?php echo $fc_today ? ' naws-fcc-today' : ''; ?>">
            <div class="naws-fcc-day"><?php echo esc_html( $fc_wd ); ?></div>
            <div class="naws-fcc-date"><?php echo esc_html( $fc_dt ); ?></div>
            <div class="naws-fcc-svg"><?php echo NAWS_Forecast::get_weather_svg( $fc_wmo['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from trusted internal method ?></div>
            <div class="naws-fcc-cond"><?php echo esc_html( $fc_wmo['label'] ); ?></div>
            <div class="naws-fcc-temps">
              <span class="naws-fcc-tmax"><?php echo $fc_tmax !== null ? esc_html( $fc_tmax ) : '--'; ?></span>
              <span class="naws-fcc-sep">/ <?php echo $fc_tmin !== null ? esc_html( $fc_tmin ) : '--'; ?></span>
              <span class="naws-fcc-tunit"><?php echo esc_html( $fc_temp_unit ); ?></span>
            </div>
            <div class="naws-fcc-meta">
              <span title="<?php echo esc_attr( naws__( 'forecast_precip' ) ); ?>">🌧️ <?php echo $fc_precip !== null ? esc_html( $fc_precip . ' ' . $fc_rain_unit ) : '0'; ?></span>
              <span title="<?php echo esc_attr( naws__( 'forecast_precip_prob' ) ); ?>">💧 <?php echo esc_html( $fc_day['precip_prob'] . '%' ); ?></span>
              <span title="<?php echo esc_attr( naws__( 'forecast_wind' ) ); ?>">🌬️ <?php echo $fc_wmax !== null ? esc_html( $fc_wmax . ' ' . $fc_wind_label ) : '--'; ?></span>
              <span title="<?php echo esc_attr( naws__( 'forecast_wind_dir' ) ); ?>">🧭 <?php echo esc_html( $fc_compass ); ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="naws-fcc-gust" style="text-align:center;margin-top:8px">
          <?php
          $provider_label = ( $forecast['provider'] ?? 'open_meteo' ) === 'yr_no'
              ? 'Yr.no / MET Norway'
              : 'Open-Meteo.com';
          echo esc_html( naws__( 'forecast_source' ) ) . ': ' . esc_html( $provider_label );
          ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal overlay -->
<div id="<?php echo esc_attr($widget_id); ?>-modal" class="naws-modal" style="display:none" role="dialog" aria-modal="true">
  <div class="naws-modal-backdrop"></div>
  <div class="naws-modal-box">
    <div class="naws-modal-hdr">
      <span class="naws-modal-title"></span>
      <button class="naws-modal-close" aria-label="<?php echo esc_attr( naws__( 'close_modal' ) ); ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="naws-modal-body">
      <canvas id="<?php echo esc_attr($widget_id); ?>-modal-canvas"></canvas>
    </div>
  </div>
</div>

<!-- Styles moved to assets/css/frontend.css (.naws-wx scope) -->

<script>
(function(){
var WID    = <?php echo wp_json_encode($widget_id); ?>;
var NAWS_FONT = getComputedStyle(document.documentElement).fontFamily || 'sans-serif';
var TIME_SUFFIX = <?php echo wp_json_encode( naws__( 'time_suffix' ) ); ?>;
var AJAX   = <?php echo wp_json_encode($ajax_url); ?>;
var NONCE  = document.getElementById(WID).dataset.nonce;
var RFSH   = (parseInt(document.getElementById(WID).dataset.refresh,10)||60)*1000;
var HIDE   = document.getElementById(WID).dataset.hidden ? document.getElementById(WID).dataset.hidden.split(',').filter(Boolean) : [];
// Map of module_id → slug for NAModule4 modules (e.g. {"70:ee:50:xx:xx:xx": "gast"})
var MODULE4_SLUGS = JSON.parse(document.getElementById(WID).dataset.module4||'{}');
// Info about each NAModule4 slug (from PHP)
var MODULE4_INFO  = <?php echo wp_json_encode( $module4_info ); ?>;
<?php
// Build pressure trend HTML server-side – no AJAX needed
$t_icons = [
    'up'     => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
    'down'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
    'stable' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
];
$t_labels  = [ 'up' => naws__( 'trend_up' ), 'down' => naws__( 'trend_down' ), 'stable' => naws__( 'trend_stable' ) ];

// ── i18n strings for JavaScript ──────────────────────────────────────────────
$_i18n = [
    'lbl_outdoor'     => naws__( 'lbl_outdoor' ),
    'lbl_base'        => naws__( 'lbl_base' ),
    'card_temperature'=> naws__( 'card_temperature' ),
    'card_humidity'   => naws__( 'card_humidity' ),
    'card_pressure'   => naws__( 'card_pressure' ),
    'card_co2'        => naws__( 'card_co2' ),
    'card_noise'      => naws__( 'card_noise' ),
    'card_rain'       => naws__( 'card_rain' ),
    'card_wind_gusts' => naws__( 'card_wind_gusts' ),
    'card_wind'       => naws__( 'card_wind' ),
    'card_gusts'      => naws__( 'card_gusts' ),
    'card_wind_dir'   => naws__( 'card_wind_dir' ),
    'card_temp_min'   => naws__( 'card_temp_min' ),
    'card_temp_max'   => naws__( 'card_temp_max' ),
    'stale_data'      => naws__( 'stale_data' ),
    'no_live_data'    => naws__( 'no_live_data' ),
    'sync_inactive'   => naws__( 'sync_inactive_hint' ),
];
$t_sign     = $pressure_diff > 0 ? '+' : '';
$t_diff_str = $pressure_diff !== 0.0 ? " ({$t_sign}{$pressure_diff} hPa)" : '';
$trend_html = '<div class="naws-press-trend naws-trend-' . $pressure_trend . '">'
    . $t_icons[ $pressure_trend ]
    . '<span>' . $t_labels[ $pressure_trend ] . $t_diff_str . '</span>'
    . '</div>';
?>
var NAWS_I18N = <?php echo wp_json_encode( $_i18n ); ?>;
var PRESS_TREND_HTML = <?php echo wp_json_encode( $trend_html ); ?>;
var liveEl = document.getElementById(WID+'-live');
var chartsEl= document.getElementById(WID+'-charts');
var built  = false;
var charts = {};
var chartData = {}; // store raw data for modal re-render

// Chart configs from PHP
var CHART_CONFIGS = <?php echo wp_json_encode($chart_configs); ?>;

/* ── HELPERS ─────────────────────────── */
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmt(v){
  if(!v||v==='0000-00-00 00:00:00') return '—';
  var d=/^\d+$/.test(String(v))?new Date(+v*1000):new Date(String(v).replace(' ','T'));
  if(isNaN(d)) return String(v);
  var p=function(n){return String(n).padStart(2,'0');};
  return p(d.getDate())+'.'+p(d.getMonth()+1)+'.'+d.getFullYear()+' · '+p(d.getHours())+':'+p(d.getMinutes())+(TIME_SUFFIX?' '+TIME_SUFFIX:'');
}
function sfmt(v){
  if(!v) return '';
  var d=/^\d+$/.test(String(v))?new Date(+v*1000):new Date(String(v).replace(' ','T'));
  if(isNaN(d)) return '';
  var p=function(n){return String(n).padStart(2,'0');};
  return p(d.getHours())+':'+p(d.getMinutes())+(TIME_SUFFIX?' '+TIME_SUFFIX:'');
}
function hhmm(ms){
  var d=new Date(ms); var p=function(n){return String(n).padStart(2,'0');};
  return p(d.getHours())+':'+p(d.getMinutes());
}
function cdir(deg){
  var d=['N','NNO','NO','ONO','O','OSO','SO','SSO','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  return d[Math.round(((+deg%360)+360)%360/22.5)%16];
}
function post(params,cb){
  var xhr=new XMLHttpRequest();
  xhr.open('POST',AJAX);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onload=function(){if(xhr.status===200){try{cb(JSON.parse(xhr.responseText));}catch(e){cb(null);}}else cb(null);};
  var body='nonce='+encodeURIComponent(NONCE);
  Object.keys(params).forEach(function(k){
    var v=params[k];
    if(Array.isArray(v)) v.forEach(function(vi){body+='&'+encodeURIComponent(k)+'[]='+encodeURIComponent(vi);});
    else body+='&'+encodeURIComponent(k)+'='+encodeURIComponent(v);
  });
  xhr.send(body);
}

/* ── ICONS ───────────────────────────── */
var ICO=<?php echo NAWS_Icons::get_js_object(); ?>;
var NAWS_ICON_SET=<?php echo wp_json_encode( NAWS_Icons::get_current_set() ); ?>;

/* ── COMPASS ─────────────────────────── */
var ROSE='<svg style="position:absolute;top:0;left:0;width:100%;height:100%" viewBox="-4 -4 168 168" xmlns="http://www.w3.org/2000/svg">'
  +'<circle cx="80" cy="80" r="72" fill="#f4fafa" stroke="#c0d4d4" stroke-width="1.5"/>'
  +'<circle cx="80" cy="80" r="54" fill="none" stroke="#daeaea" stroke-width="1"/>'
  +'<circle cx="80" cy="80" r="34" fill="none" stroke="#e5f0f0" stroke-width="1" stroke-dasharray="3 4"/>'
  +'<polygon points="80,8 88,80 80,92 72,80" fill="#427272"/>'
  +'<polygon points="80,8 80,92 88,80" fill="#c0d8d8"/>'
  +'<polygon points="80,152 72,80 80,68 88,80" fill="#427272"/>'
  +'<polygon points="80,152 80,68 72,80" fill="#c0d8d8"/>'
  +'<polygon points="152,80 80,72 68,80 80,88" fill="#427272"/>'
  +'<polygon points="152,80 68,80 80,88" fill="#c0d8d8"/>'
  +'<polygon points="8,80 80,88 92,80 80,72" fill="#427272"/>'
  +'<polygon points="8,80 92,80 80,72" fill="#c0d8d8"/>'
  +'<polygon points="129,31 76,76 80,80" fill="#7aa0a0"/>'
  +'<polygon points="129,31 84,84 80,80" fill="#c0d8d8"/>'
  +'<polygon points="129,129 84,76 80,80" fill="#7aa0a0"/>'
  +'<polygon points="129,129 76,84 80,80" fill="#c0d8d8"/>'
  +'<polygon points="31,129 84,84 80,80" fill="#7aa0a0"/>'
  +'<polygon points="31,129 76,76 80,80" fill="#c0d8d8"/>'
  +'<polygon points="31,31 76,84 80,80" fill="#7aa0a0"/>'
  +'<polygon points="31,31 84,76 80,80" fill="#c0d8d8"/>'
  +'<circle cx="80" cy="80" r="9" fill="#427272" stroke="#fff" stroke-width="2.5"/>'
  +'<text x="80" y="9" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="#2d5252">N</text>'
  +'<text x="80" y="153" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="#2d5252">S</text>'
  +'<text x="153" y="80" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="#2d5252">E</text>'
  +'<text x="7" y="80" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="#2d5252">W</text>'
  +'<text x="133" y="27" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="#7aa0a0">NE</text>'
  +'<text x="133" y="136" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="#7aa0a0">SE</text>'
  +'<text x="27" y="136" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="#7aa0a0">SW</text>'
  +'<text x="27" y="27" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="#7aa0a0">NW</text>'
  +'</svg>';
function arrowSVG(deg){
  return '<svg id="'+WID+'-arr" style="position:absolute;top:0;left:0;width:100%;height:100%;transform:rotate('+deg+'deg);transform-origin:50% 50%;transition:transform 1.2s ease" viewBox="-4 -4 168 168" xmlns="http://www.w3.org/2000/svg">'
    +'<polygon points="80,18 87,38 80,32 73,38" fill="#c0392b"/>'
    +'<line x1="80" y1="32" x2="80" y2="88" stroke="#c0392b" stroke-width="5" stroke-linecap="round"/>'
    +'<line x1="80" y1="88" x2="80" y2="106" stroke="#7aa0a0" stroke-width="3" stroke-linecap="round" opacity=".4"/>'
    +'</svg>';
}

/* ── GAUGE ───────────────────────────── */
function gaugeSVG(wv,gv){
  // Dynamic scale based on actual wind/gust values
  var rawMax=Math.max(+wv||0,+gv||0);
  var steps=[10,15,20,30,40,60,80,100,120,150];
  var maxVal=steps[0];
  for(var i=0;i<steps.length;i++){if(steps[i]>=rawMax){maxVal=steps[i];break;}}
  if(rawMax>150) maxVal=Math.ceil(rawMax/10)*10;

  wv=Math.max(0,Math.min(maxVal,+wv||0));
  gv=Math.max(0,Math.min(maxVal,+gv||0));

  var numTicks=(maxVal<=20)?5:(maxVal<=60)?6:5;
  var tickStep=maxVal/numTicks;

  var CX=100,CY=98,R=78;
  function pt(v){
    var a=Math.PI+(v/maxVal)*Math.PI;
    return{x:(CX+R*Math.cos(a)).toFixed(1),y:(CY+R*Math.sin(a)).toFixed(1)};
  }
  var w=pt(wv),g=pt(gv);
  var s='<svg class="naws-gauge-svg" viewBox="14 12 172 86" xmlns="http://www.w3.org/2000/svg">';
  s+='<path d="M'+(CX-R)+','+CY+' A'+R+','+R+',0,0,1,'+(CX+R)+','+CY+'" fill="none" stroke="#e0eeee" stroke-width="9" stroke-linecap="round"/>';
  if(gv>0) s+='<path d="M'+(CX-R)+','+CY+' A'+R+','+R+',0,0,1,'+g.x+','+g.y+'" fill="none" stroke="#7aa0a0" stroke-width="5" stroke-linecap="round" opacity=".45" stroke-dasharray="5 3"/>';
  if(wv>0) s+='<path d="M'+(CX-R)+','+CY+' A'+R+','+R+',0,0,1,'+w.x+','+w.y+'" fill="none" stroke="#427272" stroke-width="9" stroke-linecap="round" opacity=".7"/>';
  for(var i=0;i<=numTicks;i++){
    var val=i*tickStep;
    var a=Math.PI+(val/maxVal)*Math.PI;
    var r1=R-10,r2=R-20;
    s+='<line x1="'+(CX+r1*Math.cos(a)).toFixed(1)+'" y1="'+(CY+r1*Math.sin(a)).toFixed(1)+'"'
      +' x2="'+(CX+r2*Math.cos(a)).toFixed(1)+'" y2="'+(CY+r2*Math.sin(a)).toFixed(1)+'"'
      +' stroke="#7aa0a0" stroke-width="1.8"/>';
    var lx=(CX+(R-29)*Math.cos(a)).toFixed(1),ly=(CY+(R-29)*Math.sin(a)).toFixed(1);
    s+='<text x="'+lx+'" y="'+ly+'" text-anchor="middle" dominant-baseline="middle"'
      +' font-family="sans-serif" font-size="9" font-weight="700" fill="#7aa0a0">'+Math.round(val)+'</text>';
  }
  s+='<line x1="'+CX+'" y1="'+CY+'" x2="'+w.x+'" y2="'+w.y+'" stroke="#2d5252" stroke-width="3.5" stroke-linecap="round"/>';
  if(gv>0) s+='<line x1="'+CX+'" y1="'+CY+'" x2="'+g.x+'" y2="'+g.y+'" stroke="#7aa0a0" stroke-width="2.5" stroke-linecap="round" opacity=".55" stroke-dasharray="4 3"/>';
  s+='<circle cx="'+CX+'" cy="'+CY+'" r="7" fill="#427272" stroke="#fff" stroke-width="2.5"/>';
  s+='</svg>';
  return s;
}

/* ── INDEX READINGS ──────────────────── */
function indexReadings(rows){
  var p={};
  var isOutdoor=function(r){return r.module_type==='NAModule1';};
  var isNAMain =function(r){return r.module_type==='NAMain'||r.module_type==='NAOldModule';};
  var isModule4=function(r){return r.module_type==='NAModule4';};
  rows.forEach(function(r){
    var key=r.parameter;
    if(isNAMain(r)){
      // NAMain: prefix shared param names so they never overwrite outdoor readings
      if(r.parameter==='Temperature') key='Temperature_indoor';
      if(r.parameter==='Humidity')    key='Humidity_indoor';
      if(r.parameter==='min_temp')    key='min_temp_indoor';
      if(r.parameter==='max_temp')    key='max_temp_indoor';
    } else if(isModule4(r)){
      // NAModule4: append slug so Gast/Sleeping params are unique
      var slug=MODULE4_SLUGS[r.module_id]||('m4_'+String(r.module_id).replace(/:/g,'').slice(-4));
      key=r.parameter+'_'+slug;
    }
    if(!p[key]) p[key]=Object.assign({},r,{_key:key});
    else if(isNAMain(p[key])&&isOutdoor(r)) p[key]=Object.assign({},r,{_key:key});
  });
  return p;
}

/* ── CARD HTML ───────────────────────── */
function mkCard(cls,icoKey,lbl,param,val,unit,ts,subs,extra){
  var h='<div class="naws-card '+cls+'">'
    +'<div class="naws-ico">'+ICO[icoKey]+'</div>'
    +'<div class="naws-lbl">'+lbl+'</div>'
    +'<div class="naws-val" data-param="'+esc(param)+'">'+esc(String(val??'—'))+'</div>'
    +'<div class="naws-unit">'+esc(unit)+'</div>'
    +(extra||'');
  if(subs&&subs.length){
    h+='<div class="naws-subs">';
    subs.forEach(function(s){
      h+='<div class="naws-sub"><div class="naws-sub-lbl">'+esc(s.l)+'</div>'
        +'<div class="naws-sub-val">'+esc(String(s.v??'—'))+'<span class="naws-sub-u"> '+esc(s.u||'')+'</span></div>';
      if(s.t) h+='<div class="naws-sub-time">'+sfmt(s.t)+'</div>';
      h+='</div>';
    });
    h+='</div>';
  }
  h+='<div class="naws-time">'+fmt(ts)+'</div></div>';
  return h;
}

/* ── BUILD LIVE ──────────────────────── */
function buildLive(rows){
  var p=indexReadings(rows);
  var wv=p.WindStrength?parseFloat(p.WindStrength.value)||0:0;
  var gv=p.GustStrength?parseFloat(p.GustStrength.value)||0:0;
  var wDeg=p.WindAngle?parseFloat(p.WindAngle.value)||0:0;
  var wu=esc((p.WindStrength||{}).unit||'km/h');
  var gu=esc((p.GustStrength||{}).unit||'km/h');
  var h='<div class="naws-grid">';

  // ── Außentemperatur (NAModule1) ─────────────────────────────────────────
  if(HIDE.indexOf('Temperature')<0&&p.Temperature){
    var r=p.Temperature,subs=[];
    if(p.min_temp) subs.push({l:'Min',v:p.min_temp.value,u:p.min_temp.unit||'°C'});
    if(p.max_temp) subs.push({l:'Max',v:p.max_temp.value,u:p.max_temp.unit||'°C'});
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temperature+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','Temperature',r.value,r.unit||'°C',r.recorded_at,subs);
  }
  if(HIDE.indexOf('min_temp')<0&&p.min_temp&&HIDE.indexOf('Temperature')>=0)
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temp_min+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','min_temp',p.min_temp.value,p.min_temp.unit||'°C',p.min_temp.recorded_at,[]);
  if(HIDE.indexOf('max_temp')<0&&p.max_temp&&HIDE.indexOf('Temperature')>=0)
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temp_max+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','max_temp',p.max_temp.value,p.max_temp.unit||'°C',p.max_temp.recorded_at,[]);

  // ── Außen-Luftfeuchtigkeit ─────────────────────────────────────────────
  if(HIDE.indexOf('Humidity')<0&&p.Humidity)
    h+=mkCard('c-humid','humid',NAWS_I18N.card_humidity+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_outdoor+'</span>','Humidity',p.Humidity.value,p.Humidity.unit||'%',p.Humidity.recorded_at,[]);

  // ── Luftdruck (NAMain) ─────────────────────────────────────────────────
  var pr=p.Pressure||p.AbsolutePressure;
  if(HIDE.indexOf('Pressure')<0&&pr)
    h+=mkCard('c-press','press',NAWS_I18N.card_pressure,pr.parameter,pr.value,pr.unit||'hPa',pr.recorded_at,[],PRESS_TREND_HTML);

  // ── CO₂ Basis (NAMain) ────────────────────────────────────────────────
  if(HIDE.indexOf('CO2')<0&&p.CO2)
    h+=mkCard('c-co2','co2',NAWS_I18N.card_co2+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','CO2',p.CO2.value,p.CO2.unit||'ppm',p.CO2.recorded_at,[]);

  // ── Lärm Basis (NAMain) ───────────────────────────────────────────────
  if(HIDE.indexOf('Noise')<0&&p.Noise)
    h+=mkCard('c-noise','noise',NAWS_I18N.card_noise+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','Noise',p.Noise.value,p.Noise.unit||'dB',p.Noise.recorded_at,[]);

  // ── Innentemperatur Basis (NAMain) ─────────────────────────────────────
  if(HIDE.indexOf('Temperature_indoor')<0&&p.Temperature_indoor)
    h+=mkCard('c-temp','temp',NAWS_I18N.card_temperature+'<span class="naws-lbl-badge">'+NAWS_I18N.lbl_base+'</span>','Temperature_indoor',p.Temperature_indoor.value,p.Temperature_indoor.unit||'°C',p.Temperature_indoor.recorded_at,[]);

  // ── Regen (NAModule3) ─────────────────────────────────────────────────
  if(HIDE.indexOf('Rain')<0&&(p.Rain||p.sum_rain_1||p.sum_rain_24||p.rain_rolling_24h)){
    var rm=p.Rain||p.sum_rain_1||p.sum_rain_24||p.rain_rolling_24h,rs=[];
    if(p.sum_rain_1) rs.push({l:'1h', v:p.sum_rain_1.value, u:p.sum_rain_1.unit||'mm'});
    // Use our DB-computed rolling 24h; fall back to Netatmo sum_rain_24 (resets at midnight)
    var r24=p.rain_rolling_24h||p.sum_rain_24;
    if(r24&&r24!==rm) rs.push({l:'24h', v:r24.value, u:r24.unit||'mm'});
    h+=mkCard('c-rain','rain',NAWS_I18N.card_rain,'Rain',rm.value,rm.unit||'mm',rm.recorded_at,rs);
  }

  // ── Wind-Gauge (NAModule2) ────────────────────────────────────────────
  if(HIDE.indexOf('WindStrength')<0&&(p.WindStrength||p.GustStrength)){
    h+='<div class="naws-card c-wind">'
      +'<div class="naws-ico">'+ICO.wind+'</div>'
      +'<div class="naws-lbl">'+NAWS_I18N.card_wind_gusts+'</div>'
      +'<div id="'+WID+'-gauge" style="width:100%;display:flex;justify-content:center">'+gaugeSVG(wv,gv)+'</div>'
      +'<div class="naws-wvrow">'
      +'<div class="naws-wvblk"><div class="naws-wv-lbl">'+NAWS_I18N.card_wind+'</div><div class="naws-wv-num" id="'+WID+'-wv" style="color:var(--ink2)">'+esc(String(wv))+'</div><div class="naws-wv-unit">'+wu+'</div></div>'
      +'<div class="naws-wvblk"><div class="naws-wv-lbl">'+NAWS_I18N.card_gusts+'</div><div class="naws-wv-num" id="'+WID+'-gv" style="color:var(--muted)">'+esc(String(gv))+'</div><div class="naws-wv-unit">'+gu+'</div></div>'
      +'</div>';
    if(p.WindStrength&&p.WindStrength.recorded_at)
      h+='<div class="naws-time" style="text-align:center;margin-top:7px">'+fmt(p.WindStrength.recorded_at)+'</div>';
    h+='</div>';
  }

  // ── Windrichtung / Kompass (NAModule2) ────────────────────────────────
  if(HIDE.indexOf('WindAngle')<0&&p.WindAngle){
    h+='<div class="naws-card c-wind">'
      +'<div class="naws-ico"><svg viewBox="0 0 24 24" style="width:23px;height:23px;stroke:var(--ca,var(--ink));fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="10"/><polygon points="16.24,7.76 14.12,14.12 7.76,16.24 9.88,9.88" stroke="none" fill="var(--ca,var(--ink))"/></svg></div>'
      +'<div class="naws-lbl">'+NAWS_I18N.card_wind_dir+'</div>'
      +'<div class="naws-rose-wrap" id="'+WID+'-rose">'+ROSE+arrowSVG(wDeg)+'</div>'
      +'<div class="naws-rose-dir" id="'+WID+'-dir">'+Math.round(wDeg)+'° &nbsp;·&nbsp; '+cdir(wDeg)+'</div>';
    if(p.WindAngle.recorded_at) h+='<div class="naws-time" style="text-align:center;margin-top:5px">'+fmt(p.WindAngle.recorded_at)+'</div>';
    h+='</div>';
  }

  // ── NAModule4: je ein eigener Kachel-Block pro Sensor pro Modul ────────
  Object.keys(MODULE4_INFO).forEach(function(slug){
    var info=MODULE4_INFO[slug];
    var modLabel=esc(info.name);

    var kTemp='Temperature_'+slug, kHum='Humidity_'+slug, kCO2='CO2_'+slug, kNoise='Noise_'+slug;

    if(HIDE.indexOf(kTemp)<0&&p[kTemp])
      h+=mkCard('c-temp','temp',NAWS_I18N.card_temperature+'<span class="naws-lbl-badge">'+modLabel+'</span>',kTemp,p[kTemp].value,p[kTemp].unit||'°C',p[kTemp].recorded_at,[]);

    if(HIDE.indexOf(kHum)<0&&p[kHum])
      h+=mkCard('c-humid','humid',NAWS_I18N.card_humidity+'<span class="naws-lbl-badge">'+modLabel+'</span>',kHum,p[kHum].value,p[kHum].unit||'%',p[kHum].recorded_at,[]);

    if(HIDE.indexOf(kCO2)<0&&p[kCO2])
      h+=mkCard('c-co2','co2',NAWS_I18N.card_co2+'<span class="naws-lbl-badge">'+modLabel+'</span>',kCO2,p[kCO2].value,p[kCO2].unit||'ppm',p[kCO2].recorded_at,[]);

    if(HIDE.indexOf(kNoise)<0&&p[kNoise])
      h+=mkCard('c-noise','noise',NAWS_I18N.card_noise+'<span class="naws-lbl-badge">'+modLabel+'</span>',kNoise,p[kNoise].value,p[kNoise].unit||'dB',p[kNoise].recorded_at,[]);
  });

  h+='</div>';
  return h;
}

/* ── SOFT UPDATE ─────────────────────── */
function softUpdate(rows){
  var p=indexReadings(rows);
  document.querySelectorAll('#'+WID+' .naws-val[data-param]').forEach(function(el){
    var k=el.dataset.param; if(!k||!p[k]) return;
    var nv=String(p[k].value??'—');
    if(el.textContent!==nv){el.textContent=nv;el.classList.remove('naws-flash');void el.offsetWidth;el.classList.add('naws-flash');}
    var c=el.closest('.naws-card');if(c){var t=c.querySelector('.naws-time');if(t)t.textContent=fmt(p[k].recorded_at);}
  });
  var wv=p.WindStrength?parseFloat(p.WindStrength.value)||0:null;
  var gv=p.GustStrength?parseFloat(p.GustStrength.value)||0:null;
  var wDeg=p.WindAngle?parseFloat(p.WindAngle.value)||0:null;
  var gauge=document.getElementById(WID+'-gauge'); if(gauge&&wv!==null) gauge.innerHTML=gaugeSVG(wv,gv||0);
  var wvEl=document.getElementById(WID+'-wv'); if(wvEl&&wv!==null) wvEl.textContent=String(wv);
  var gvEl=document.getElementById(WID+'-gv'); if(gvEl&&gv!==null) gvEl.textContent=String(gv);
  var arr=document.getElementById(WID+'-arr'); if(arr&&wDeg!==null) arr.style.transform='rotate('+wDeg+'deg)';
  var dir=document.getElementById(WID+'-dir'); if(dir&&wDeg!==null) dir.innerHTML=Math.round(wDeg)+'° &nbsp;·&nbsp; '+cdir(wDeg);
}

// Chart.js plugin: fill canvas background to match card color
var canvasBgPlugin={
  id:'canvasBg',
  beforeDraw:function(chart){
    var ctx=chart.canvas.getContext('2d');
    ctx.save();
    ctx.globalCompositeOperation='destination-over';
    ctx.fillStyle='#ffffff';
    ctx.fillRect(0,0,chart.canvas.width,chart.canvas.height);
    ctx.restore();
  }
};
Chart.register(canvasBgPlugin);

/* ── CHART.JS CONFIG ─────────────────── */
function nawsLiveFontSize(){ var w=window.innerWidth; return w<480?9:w<768?10:11; }

function chartOpts(unit, type){
  var fs = nawsLiveFontSize();
  return {
    responsive:true, maintainAspectRatio:true,
    animation:{duration:900,easing:'easeInOutQuart'},
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:'rgba(45,82,82,.92)',
        titleColor:'#a0c8c8',bodyColor:'#fff',
        titleFont:{family:NAWS_FONT,size:fs+1},
        bodyFont:{family:NAWS_FONT,size:fs+3,weight:'bold'},
        padding:10,cornerRadius:8,displayColors:false,
        callbacks:{label:function(c){return (Math.round(c.parsed.y*10)/10)+' '+unit;}}
      }
    },
    scales:{
      x:{
        grid:{color:'rgba(218,240,240,.5)'},
        ticks:{color:'#7aa0a0',font:{family:NAWS_FONT,size:fs},maxRotation:0,maxTicksLimit:12}
      },
      y:{
        grid:{color:'rgba(218,240,240,.5)'},
        ticks:{
          color:'#7aa0a0',font:{family:NAWS_FONT,size:fs},
          callback:function(v){return Math.round(v*10)/10;}
        },
        title:{display:true,text:unit,color:'#a0b8b8',font:{family:NAWS_FONT,size:fs,weight:'600'}}
      }
    }
  };
}
function hexToRgba(hex, alpha){
  var r=0,g=0,b=0;
  if(hex.length===4){r=parseInt(hex[1]+hex[1],16);g=parseInt(hex[2]+hex[2],16);b=parseInt(hex[3]+hex[3],16);}
  else if(hex.length===7){r=parseInt(hex.substring(1,3),16);g=parseInt(hex.substring(3,5),16);b=parseInt(hex.substring(5,7),16);}
  return 'rgba('+r+','+g+','+b+','+alpha+')';
}
function colorToRgba(c, alpha){
  if(c.charAt(0)==='#') return hexToRgba(c, alpha);
  return c.replace('rgb(','rgba(').replace(')',', '+alpha+')');
}
function makeDataset(cfg, pts, canvasCtx){
  var c=cfg.color;
  var bg;
  if(cfg.type==='bar'){
    bg=colorToRgba(c, 0.45);
  } else if(canvasCtx){
    var grad=canvasCtx.createLinearGradient(0,0,0,canvasCtx.canvas.height||300);
    grad.addColorStop(0, colorToRgba(c, 0.28));
    grad.addColorStop(0.6, colorToRgba(c, 0.08));
    grad.addColorStop(1, colorToRgba(c, 0.01));
    bg=grad;
  } else {
    bg=colorToRgba(c, 0.08);
  }
  return {
    data:pts,
    borderColor:c, backgroundColor:bg,
    borderWidth:cfg.type==='bar'?1.5:2.5,
    pointRadius:0, pointHoverRadius:4,
    tension:0.35, fill:cfg.type!=='bar',
    borderRadius:cfg.type==='bar'?5:0,
  };
}

function renderChart(canvasId, cfg, labels, vals, animate){
  var el=document.getElementById(canvasId); if(!el) return;
  if(charts[canvasId]){charts[canvasId].destroy();delete charts[canvasId];}
  var ctx2d=el.getContext('2d');
  var opts=chartOpts(cfg.unit, cfg.type);
  if(!animate) opts.animation={duration:0};
  charts[canvasId]=new Chart(el,{
    type:cfg.type,
    data:{labels:labels, datasets:[makeDataset(cfg,vals,ctx2d)]},
    options:opts,
  });
}

/* ── LOAD CHARTS ─────────────────────── */
function chartCanvasId(key){ return WID+'-'+key.replace(/[^a-zA-Z0-9]/g,'-'); }

function loadCharts(){
  if(!CHART_CONFIGS||!CHART_CONFIGS.length) return;
  var now=Math.floor(Date.now()/1000);
  var dayStart=now-86400;

  CHART_CONFIGS.forEach(function(cfg){
    var params={action:'naws_get_chart_data',date_from:dayStart,date_to:now,parameter:[cfg.param],group_by:'hour'};
    if(cfg.module_id) params.module_id=cfg.module_id;
    post(params,function(r){
      if(!r||!r.success||!r.data||!r.data.datasets||!r.data.datasets.length) return;
      var ds=r.data.datasets[0]; if(!ds||!ds.data||!ds.data.length) return;
      var labels=ds.data.map(function(p){return hhmm(p.x);});
      var vals=ds.data.map(function(p){return p.y;});
      chartData[cfg.key]={cfg:cfg,labels:labels,vals:vals};
      renderChart(chartCanvasId(cfg.key), cfg, labels, vals, true);
      if(chartsEl) chartsEl.style.display='';
    });
  });
}

/* ── MODAL ───────────────────────────── */
var modal=document.getElementById(WID+'-modal');
var modalTitle=modal?modal.querySelector('.naws-modal-title'):null;
var modalCanvasId=WID+'-modal-canvas';

function openModal(cfgId, label){
  // cfgId is the canvas element id (WID-key-with-dashes), convert back to key
  var cfgKey=cfgId.replace(WID+'-','').replace(/-/g,'_');
  // Try exact match first, then try replacing dashes back to underscores
  var cd=chartData[cfgId]||chartData[cfgKey];
  if(!modal||!cd) return;
  modalTitle.textContent=label||cd.cfg.label;
  modal.style.display='flex';
  document.body.style.overflow='hidden';
  // destroy previous modal chart
  if(charts[modalCanvasId]){charts[modalCanvasId].destroy();delete charts[modalCanvasId];}
  // Need to re-get canvas after display:flex
  setTimeout(function(){
    var opts=chartOpts(cd.cfg.unit, cd.cfg.type);
    opts.animation={duration:600,easing:'easeInOutQuart'};
    opts.maintainAspectRatio=false;
    var mEl=document.getElementById(modalCanvasId); if(!mEl) return;
    mEl.style.height='340px';
    var mCtx=mEl.getContext('2d');
    charts[modalCanvasId]=new Chart(mEl,{
      type:cd.cfg.type,
      data:{labels:cd.labels, datasets:[makeDataset(cd.cfg, cd.vals, mCtx)]},
      options:opts,
    });
  },30);
}
function closeModal(){
  if(!modal) return;
  modal.style.display='none';
  document.body.style.overflow='';
  if(charts[modalCanvasId]){charts[modalCanvasId].destroy();delete charts[modalCanvasId];}
}

// Bind modal events
if(modal){
  modal.querySelector('.naws-modal-backdrop').addEventListener('click', closeModal);
  modal.querySelector('.naws-modal-close').addEventListener('click', closeModal);
  document.addEventListener('keydown',function(e){if(e.key==='Escape') closeModal();});
}
// Bind expand buttons (delegated – charts built after page load)
document.addEventListener('click',function(e){
  var btn=e.target.closest('.naws-chart-expand');
  if(!btn) return;
  var cid=btn.dataset.chartId; // e.g. "naws-live-1-ct"
  var cfgId=cid.replace(WID+'-',''); // "ct"
  openModal(cfgId, btn.dataset.label);
});
// Also click on card itself
document.addEventListener('click',function(e){
  var card=e.target.closest('.naws-chart-card');
  if(!card||e.target.closest('.naws-chart-expand')) return;
  var cid=card.dataset.chartId;
  var cfgId=cid.replace(WID+'-','');
  openModal(cfgId, card.dataset.chartLabel);
});




var _liveRetries = 0;
var _liveRetryMax = 3; // retry up to 3× with 5s intervals if first load returns empty

function loadLive(){
  post({action:'naws_get_latest'},function(r){
    if(r&&r.success&&r.data&&r.data.length){
      _liveRetries = 0;
      var maxTs=r.data.reduce(function(m,x){return x.recorded_at>m?x.recorded_at:m;},'');
      var tsEl=document.querySelector('#'+WID+' .naws-ts'); if(tsEl) tsEl.textContent=fmt(maxTs);
      var pulseEl=document.getElementById(WID+'-pulse');
      if(pulseEl){
        var ageMin=(Date.now()/1000 - parseInt(maxTs))/60;
        if(ageMin > 30){
          pulseEl.style.background='#e0a000';
          pulseEl.style.animation='none';
          pulseEl.title=NAWS_I18N.stale_data.replace('%d',Math.round(ageMin));
        } else {
          pulseEl.style.background='';
          pulseEl.style.animation='';
          pulseEl.title='';
        }
      }
      if(!built){
        liveEl.innerHTML=buildLive(r.data);
        built=true;
        loadCharts();
      } else {
        softUpdate(r.data);
      }
      // Schedule next normal refresh
      setTimeout(loadLive, RFSH);
    } else if(!built && _liveRetries < _liveRetryMax){
      // Data not yet available – retry quickly, don't start the normal RFSH loop yet
      _liveRetries++;
      setTimeout(loadLive, 5000);
    } else {
      if(!built){
        liveEl.innerHTML='<div class="naws-error">'+NAWS_I18N.no_live_data+'<br><small>'+NAWS_I18N.sync_inactive+'</small></div>';
        var pulseEl=document.getElementById(WID+'-pulse');
        if(pulseEl){ pulseEl.style.background='#e57373'; pulseEl.style.animation='none'; }
      }
      // Continue polling even after error
      setTimeout(loadLive, RFSH);
    }
  });
}
loadLive();

/* ── RESPONSIVE: update chart fonts on resize ── */
var _nawsLiveResizeTimer;
window.addEventListener('resize', function(){
  clearTimeout(_nawsLiveResizeTimer);
  _nawsLiveResizeTimer = setTimeout(function(){
    var fs = nawsLiveFontSize();
    Object.keys(charts).forEach(function(id){
      var ch = charts[id];
      if(!ch) return;
      if(ch.options.scales && ch.options.scales.x && ch.options.scales.x.ticks) ch.options.scales.x.ticks.font.size = fs;
      if(ch.options.scales && ch.options.scales.y && ch.options.scales.y.ticks) ch.options.scales.y.ticks.font.size = fs;
      if(ch.options.scales && ch.options.scales.y && ch.options.scales.y.title) ch.options.scales.y.title.font.size = fs;
      ch.update('none');
    });
  }, 250);
});
})();
</script>
