<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_live title="" refresh="60"]
 * v0.9.28 – per-sensor tiles, NAModule4 namespacing, correct outdoor temp
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$widget_id      = 'naws-live-' . wp_unique_id();
$hidden         = (array) get_option( 'naws_live_hidden_params',  [] );
$hidden_modules = (array) get_option( 'naws_live_hidden_modules', [] );

// ── Public module references ────────────────────────────────────────────────
// A module_id is the module's MAC address, and nothing in the browser needs
// to know it: a chart only has to say which module it means. So what goes
// into the markup — and comes back with every AJAX call — is the reference
// NAWS_Helpers builds, and the AJAX handlers resolve it back.
$module_refs = NAWS_Helpers::module_ref_map();

// ── NAModule4 slug map: reference → slug (e.g. "gast", "sleeping") ──────────
// Passed to JS so indexReadings() can namespace their parameters correctly.
// The slug rule lives in NAWS_Helpers; this template used to carry its own
// copy of it, which is how such rules drift apart.
$module4_slugs = []; // [ reference => slug ]
$module4_info  = []; // [ slug => ['name'=>…] ]
$module4_list  = NAWS_Helpers::indoor_module_slugs();
foreach ( $module4_list as $m4 ) {
    $module4_slugs[ NAWS_Helpers::module_ref( $m4['module_id'] ) ] = $m4['slug'];
    $module4_info[ $m4['slug'] ] = [ 'name' => $m4['name'] ];
}

// ── Hidden-params expansion: module toggle → hide all its params ─────────────
// The list lives in NAWS_Helpers because the settings screen needs the same
// one: it shows which cards a module switch takes with it, and a second copy
// here would drift. The copy that used to stand here had already drifted —
// it left Humidity_indoor out, so switching the base station off hid all of
// its readings but that one.
$module_param_map = NAWS_Helpers::module_param_map();
foreach ( $hidden_modules as $hmod ) {
    if ( isset( $module_param_map[ $hmod ] ) ) {
        $hidden = array_unique( array_merge( $hidden, $module_param_map[ $hmod ] ) );
    }
}

$nonce    = wp_create_nonce( 'naws_public_nonce' );
$ajax_url = admin_url( 'admin-ajax.php' );

// ── Charts: which sensors get a 24h graph? ───────────────────────────────────
$hidden_charts = (array) get_option( 'naws_live_hidden_charts', [] );

// Master sensor→chart config: display_key, db_param, module reference, label, unit, type, color
// Units are always read from settings via NAWS_Helpers::get_unit()
$sensor_chart_configs = [];

// NAModule1 – outdoor
if ( isset( $module_refs['outdoor'] ) ) {
    $sensor_chart_configs[] = [ 'key'=>'Temperature',    'param'=>'Temperature', 'module_ref'=>'outdoor', 'label'=>__( 'Temp. Outdoor', 'xtx-integration-for-netatmo' ),        'unit'=>NAWS_Helpers::get_unit('Temperature'),   'type'=>'line', 'color'=>NAWS_Colors::get('chart_temp_outdoor') ];
    $sensor_chart_configs[] = [ 'key'=>'Humidity',       'param'=>'Humidity',    'module_ref'=>'outdoor', 'label'=>__( 'Humidity Outdoor', 'xtx-integration-for-netatmo' ),       'unit'=>'%',                                    'type'=>'line', 'color'=>NAWS_Colors::get('chart_humidity_outdoor') ];
}
// NAMain – indoor base
if ( isset( $module_refs['indoor'] ) ) {
    $sensor_chart_configs[] = [ 'key'=>'Temperature_indoor', 'param'=>'Temperature', 'module_ref'=>'indoor', 'label'=>__( 'Temp. Base', 'xtx-integration-for-netatmo' ),   'unit'=>NAWS_Helpers::get_unit('Temperature'),   'type'=>'line', 'color'=>NAWS_Colors::get('chart_temp_indoor') ];
    $sensor_chart_configs[] = [ 'key'=>'Pressure',           'param'=>'Pressure',    'module_ref'=>'indoor', 'label'=>__( 'Pressure', 'xtx-integration-for-netatmo' ),     'unit'=>NAWS_Helpers::get_unit('Pressure'),      'type'=>'line', 'color'=>NAWS_Colors::get('chart_pressure') ];
    $sensor_chart_configs[] = [ 'key'=>'CO2',                'param'=>'CO2',         'module_ref'=>'indoor', 'label'=>__( 'CO₂ Base', 'xtx-integration-for-netatmo' ),     'unit'=>'ppm',                                  'type'=>'line', 'color'=>NAWS_Colors::get('chart_co2') ];
    $sensor_chart_configs[] = [ 'key'=>'Noise',              'param'=>'Noise',       'module_ref'=>'indoor', 'label'=>__( 'Noise Base', 'xtx-integration-for-netatmo' ),   'unit'=>'dB',                                   'type'=>'line', 'color'=>NAWS_Colors::get('chart_noise') ];
}
// NAModule2 – wind
if ( isset( $module_refs['wind'] ) ) {
    $sensor_chart_configs[] = [ 'key'=>'WindStrength', 'param'=>'WindStrength', 'module_ref'=>'wind', 'label'=>__( 'Wind', 'xtx-integration-for-netatmo' ),  'unit'=>NAWS_Helpers::get_unit('WindStrength'), 'type'=>'line', 'color'=>NAWS_Colors::get('chart_wind') ];
    $sensor_chart_configs[] = [ 'key'=>'GustStrength', 'param'=>'GustStrength', 'module_ref'=>'wind', 'label'=>__( 'Gusts', 'xtx-integration-for-netatmo' ), 'unit'=>NAWS_Helpers::get_unit('GustStrength'), 'type'=>'line', 'color'=>NAWS_Colors::get('chart_gusts') ];
}
// NAModule3 – rain
if ( isset( $module_refs['rain'] ) ) {
    $sensor_chart_configs[] = [ 'key'=>'Rain', 'param'=>'sum_rain_1', 'module_ref'=>'rain', 'label'=>__( 'Rain (hourly)', 'xtx-integration-for-netatmo' ), 'unit'=>NAWS_Helpers::get_unit('Rain'), 'type'=>'bar', 'color'=>NAWS_Colors::get('chart_rain') ];
}
// NAModule4 – dynamic
foreach ( $module4_list as $m4 ) {
    $slug = $m4['slug'];
    $name = $m4['name'];
    $ref  = NAWS_Helpers::module_ref( $m4['module_id'] );
    $sensor_chart_configs[] = [ 'key'=>"Temperature_{$slug}", 'param'=>'Temperature', 'module_ref'=>$ref, 'label'=>__( 'Temp.', 'xtx-integration-for-netatmo' )." {$name}",   'unit'=>NAWS_Helpers::get_unit('Temperature'), 'type'=>'line', 'color'=>NAWS_Colors::get('chart_module4_temp') ];
    $sensor_chart_configs[] = [ 'key'=>"Humidity_{$slug}",    'param'=>'Humidity',    'module_ref'=>$ref, 'label'=>_x( 'Humidity', 'chart_humid_prefix', 'xtx-integration-for-netatmo' )." {$name}", 'unit'=>'%',                                   'type'=>'line', 'color'=>NAWS_Colors::get('chart_module4_humidity') ];
    $sensor_chart_configs[] = [ 'key'=>"CO2_{$slug}",         'param'=>'CO2',         'module_ref'=>$ref, 'label'=>__( 'CO₂', 'xtx-integration-for-netatmo' )." {$name}",   'unit'=>'ppm',                                 'type'=>'line', 'color'=>NAWS_Colors::get('chart_module4_co2') ];
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
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t_read from constant; live display, no caching appropriate
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
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

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
<?php // Chart.js loaded via wp_enqueue_script( 'chartjs' ) — see class-naws-shortcodes.php ?>

<div id="<?php echo esc_attr($widget_id); ?>" class="naws-wx" data-icon-set="<?php echo esc_attr( NAWS_Icons::get_current_set() ); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-ajax="<?php echo esc_attr($ajax_url); ?>"
     data-refresh="<?php echo esc_attr($atts['refresh'] ?? '60'); ?>"
     data-hidden="<?php echo esc_attr(implode(',', $hidden)); ?>"
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
            echo '<span style="color:#e57373;font-size:11px;font-weight:600;">'.esc_html( __( 'Disconnected', 'xtx-integration-for-netatmo' ) ).'</span>';
        } else {
            echo esc_html( __( 'Live', 'xtx-integration-for-netatmo' ) );
        }
      ?>&nbsp;·&nbsp; <span class="naws-ts">—</span>
    </div>
  </div>

  <div class="naws-body">
    <?php
    // Weather icon above the dashboard. Switchable in the backend; when it
    // is off the host element is not rendered at all, so applyWeatherIcon()
    // finds nothing and does no work. The first paint is server-side so the
    // icon is there before the first AJAX cycle returns.
    $naws_wx_opts = get_option( 'naws_settings', [] );
    if ( ( $naws_wx_opts['wx_show_on_dashboard'] ?? '1' ) !== '0' && class_exists( 'NAWS_Weather_State' ) ) :
        $naws_wx_now = NAWS_Weather_State::get_current();
        ?>
        <div class="naws-live-weather-icon" id="<?php echo esc_attr( $widget_id ); ?>-wx"
             data-state="<?php echo esc_attr( $naws_wx_now['state'] ); ?>">
            <?php
            if ( $naws_wx_now['state'] !== '' ) {
                // Literal template markup, never a kses-filtered string.
                echo NAWS_Weather_Icons::render( $naws_wx_now['state'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG, see templates/weather-icon.php
            }
            ?>
        </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($widget_id); ?>-live">
      <div class="naws-loading"><div class="naws-spin"></div></div>
    </div>

    <?php if ( ! empty($chart_configs) ) : ?>
    <div id="<?php echo esc_attr($widget_id); ?>-charts" style="display:none">
      <div class="naws-section-title"><?php esc_html_e( 'Daily trend (last 24h)', 'xtx-integration-for-netatmo' ); ?></div>
      <div class="naws-charts-grid">
        <?php foreach ($chart_configs as $cfg) :
            $cid = esc_attr( $widget_id . '-' . preg_replace('/[^a-z0-9]/i','-',$cfg['key']) );
        ?>
        <div class="naws-chart-card" data-chart-id="<?php echo esc_attr( $cid ); ?>" data-chart-label="<?php echo esc_attr($cfg['label']); ?>">
          <div class="naws-chart-hdr">
            <div class="naws-chart-lbl"><?php echo esc_html($cfg['label']); ?></div>
            <button class="naws-chart-expand" aria-label="<?php echo esc_attr( __( 'Expand', 'xtx-integration-for-netatmo' ) ); ?>" data-chart-id="<?php echo esc_attr( $cid ); ?>" data-label="<?php echo esc_attr($cfg['label']); ?>">
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
        $fc_title      = sprintf( /* translators: %d: number of forecast days. */ __( '%d-Day Forecast', 'xtx-integration-for-netatmo' ), $fc_day_count );
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
            <span><?php printf( esc_html( /* translators: %s: time the forecast was last fetched. */ __( 'Updated: %s', 'xtx-integration-for-netatmo' ) ), esc_html( wp_date( 'H:i', $forecast['fetched_at'] ) ) ); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Forecast Body -->
      <div class="naws-fc-body-wrap">
        <div class="naws-live-forecast-grid" style="--naws-fc-cols:<?php echo intval( $fc_day_count ); ?>">
          <?php foreach ( $forecast['days'] as $fc_day ) :
              $fc_wmo    = NAWS_Forecast::wmo_description( $fc_day['weathercode'] );
              $fc_today  = NAWS_Forecast::is_today( $fc_day['date'] );
              $fc_wd     = $fc_today ? __( 'Today', 'xtx-integration-for-netatmo' ) : NAWS_Forecast::weekday_short( $fc_day['date'] );
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
            <div class="naws-fcc-svg"><?php
            $fcc_state = NAWS_Weather_State::wmo_to_state( (int) $fc_day['weathercode'], true );
            if ( $fcc_state !== '' ) {
                // The 40 below is only the fallback size when no CSS rule matches;
                // the actual rendered size is governed by
                // .naws-wx .naws-fcc-svg svg { width:100% } in frontend.css.
                echo NAWS_Weather_Icons::render_inline( $fcc_state, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG, see templates/weather-icon.php
            }
            ?></div>
            <div class="naws-fcc-cond"><?php echo esc_html( $fc_wmo['label'] ); ?></div>
            <div class="naws-fcc-temps">
              <span class="naws-fcc-tmax"><?php echo $fc_tmax !== null ? esc_html( $fc_tmax ) : '--'; ?></span>
              <span class="naws-fcc-sep">/ <?php echo $fc_tmin !== null ? esc_html( $fc_tmin ) : '--'; ?></span>
              <span class="naws-fcc-tunit"><?php echo esc_html( $fc_temp_unit ); ?></span>
            </div>
            <div class="naws-fcc-meta">
              <span title="<?php echo esc_attr( __( 'Precipitation', 'xtx-integration-for-netatmo' ) ); ?>">🌧️ <?php echo $fc_precip !== null ? esc_html( $fc_precip . ' ' . $fc_rain_unit ) : '0'; ?></span>
              <span title="<?php echo esc_attr( __( 'Precipitation probability', 'xtx-integration-for-netatmo' ) ); ?>">💧 <?php echo esc_html( $fc_day['precip_prob'] . '%' ); ?></span>
              <span title="<?php echo esc_attr( __( 'Max. wind speed', 'xtx-integration-for-netatmo' ) ); ?>">🌬️ <?php echo $fc_wmax !== null ? esc_html( $fc_wmax . ' ' . $fc_wind_label ) : '--'; ?></span>
              <span title="<?php echo esc_attr( __( 'Wind direction', 'xtx-integration-for-netatmo' ) ); ?>">🧭 <?php echo esc_html( $fc_compass ); ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="naws-fcc-gust" style="text-align:center;margin-top:8px">
          <?php
          $provider_label = ( $forecast['provider'] ?? 'open_meteo' ) === 'yr_no'
              ? 'Yr.no / MET Norway'
              : 'Open-Meteo.com';
          echo esc_html( __( 'Source', 'xtx-integration-for-netatmo' ) ) . ': ' . esc_html( $provider_label );
          ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  <!-- Modal overlay -->
  <div id="<?php echo esc_attr($widget_id); ?>-modal" class="naws-modal" style="display:none" role="dialog" aria-modal="true">
    <div class="naws-modal-backdrop"></div>
    <div class="naws-modal-box">
      <div class="naws-modal-hdr">
        <span class="naws-modal-title"></span>
        <button class="naws-modal-close" aria-label="<?php echo esc_attr( __( 'Close', 'xtx-integration-for-netatmo' ) ); ?>">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="naws-modal-body">
        <canvas id="<?php echo esc_attr($widget_id); ?>-modal-canvas"></canvas>
      </div>
    </div>
  </div>

  </div>
</div>

<!-- Styles moved to assets/css/frontend.css (.naws-wx scope) -->

<?php
// Build pressure trend HTML server-side – no AJAX needed
$t_icons = [
    'up'     => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
    'down'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
    'stable' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
];
$t_labels  = [ 'up' => __( 'rising', 'xtx-integration-for-netatmo' ), 'down' => __( 'falling', 'xtx-integration-for-netatmo' ), 'stable' => __( 'stable', 'xtx-integration-for-netatmo' ) ];

// ── i18n strings for JavaScript ──────────────────────────────────────────────
$_i18n = [
    'lbl_outdoor'     => __( 'Outdoor', 'xtx-integration-for-netatmo' ),
    'lbl_base'        => __( 'Base', 'xtx-integration-for-netatmo' ),
    'card_temperature'=> __( 'Temperature', 'xtx-integration-for-netatmo' ),
    'card_humidity'   => _x( 'Humidity', 'card_humidity', 'xtx-integration-for-netatmo' ),
    'card_pressure'   => __( 'Air Pressure', 'xtx-integration-for-netatmo' ),
    'card_co2'        => __( 'CO₂', 'xtx-integration-for-netatmo' ),
    'card_noise'      => _x( 'Noise', 'card_noise', 'xtx-integration-for-netatmo' ),
    'card_rain'       => __( 'Precipitation', 'xtx-integration-for-netatmo' ),
    'card_wind_gusts' => __( 'Wind &amp; Gusts', 'xtx-integration-for-netatmo' ),
    'card_wind'       => __( 'Wind', 'xtx-integration-for-netatmo' ),
    'card_gusts'      => __( 'Gusts', 'xtx-integration-for-netatmo' ),
    'card_wind_dir'   => __( 'Wind Direction', 'xtx-integration-for-netatmo' ),
    'card_temp_min'   => __( 'Temp. Min', 'xtx-integration-for-netatmo' ),
    'card_temp_max'   => __( 'Temp. Max', 'xtx-integration-for-netatmo' ),
    'stale_data'      => /* translators: %d: age of the data in minutes. */ __( 'Data outdated (%d min.)', 'xtx-integration-for-netatmo' ),
    'no_live_data'    => __( 'No live data available.', 'xtx-integration-for-netatmo' ),
    'sync_inactive'   => __( 'Make sure the sync function is active.', 'xtx-integration-for-netatmo' ),
];
$t_sign     = $pressure_diff > 0 ? '+' : '';
$t_diff_str = $pressure_diff !== 0.0 ? " ({$t_sign}{$pressure_diff} hPa)" : '';
$trend_html = '<div class="naws-press-trend naws-trend-' . $pressure_trend . '">'
    . $t_icons[ $pressure_trend ]
    . '<span>' . $t_labels[ $pressure_trend ] . $t_diff_str . '</span>'
    . '</div>';

// Sanitize icon SVGs for JSON serialization
$_naws_icons_safe = array_map(
    function ( $svg ) { return wp_kses( $svg, naws_svg_kses_args() ); },
    NAWS_Icons::get_set()
);

// ── Data container: non-executable JSON element, always output reliably ──────
// The data-naws attribute is how assets/js/live-boot.js finds its payloads;
// the history template prints the same kind of element with data-naws="history".
// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- type=application/json is not executable; pure data container
echo '<script type="application/json" data-naws="live" id="' . esc_attr( $widget_id ) . '-data">'
    . wp_json_encode( [
        'WID'              => $widget_id,
        'TIME_FMT'         => NAWS_Helpers::clock_format(),
        'AJAX'             => $ajax_url,
        'MODULE4_INFO'     => $module4_info,
        'I18N'             => $_i18n,
        'PRESS_TREND_HTML' => $trend_html,
        'CHART_CONFIGS'    => $chart_configs,
        'ICO'              => $_naws_icons_safe,
        'ICON_SET'         => NAWS_Icons::get_current_set(),
        // Card order as arranged in the settings screen. buildLive() keeps
        // printing the cards in its own fixed order; the grid then lays them
        // out along this list. Ids the list does not mention keep their
        // place at the end, so a new indoor module simply appears.
        'CARD_ORDER'       => array_column( NAWS_Helpers::live_card_defs(), 'id' ),
    ] )
    . '</script>';

// The boot routine lives in assets/js/live-boot.js and is enqueued by
// NAWS_Shortcodes::sc_live(). It picks up the JSON element above by its
// data-naws attribute, so several [naws_live] shortcodes on one page all
// boot from the single registered file.
