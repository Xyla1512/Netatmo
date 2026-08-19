<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_history title=""]
 * v1.0.0 – Historical yearly charts: pressure, temp min/max/avg, rain
 * Per-year toggle legend, full-width, click-to-enlarge modal (1920px)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$widget_id  = 'naws-hist-' . wp_unique_id();
$nonce      = wp_create_nonce('naws_public_nonce');
$ajax_url   = admin_url('admin-ajax.php');
$outdoor_id = '';
$indoor_id  = '';
foreach ( NAWS_Database::get_modules( true ) as $m ) {
    if ( $m['module_type'] === 'NAModule1' ) $outdoor_id = $m['module_id'];
    if ( $m['module_type'] === 'NAMain'    ) $indoor_id  = $m['module_id'];
}
// MIN()/MAX() always return a row – on an empty table both columns are NULL,
// so check the values, not just the row (passing null to substr() is
// deprecated as of PHP 8.1).
$range = NAWS_Database::get_daily_data_range();
$year_from = ! empty( $range['date_begin'] ) ? (int) substr( $range['date_begin'], 0, 4 ) : (int) gmdate( 'Y' );
$year_to   = ! empty( $range['date_end']   ) ? (int) substr( $range['date_end'],   0, 4 ) : (int) gmdate( 'Y' );
if ( $year_from < 2000 || $year_from > $year_to ) $year_from = $year_to; // guard against malformed day_date
$years     = range($year_from, $year_to);

// Shortcode year="2025" or year="2023,2025" → filter to specific year(s)
$year_param = trim( $atts['year'] ?? '' );
if ( $year_param !== '' ) {
    $requested = array_map( 'intval', explode( ',', $year_param ) );
    $requested = array_filter( $requested, function( $y ) use ( $years ) {
        return in_array( $y, $years, true );
    } );
    if ( ! empty( $requested ) ) {
        sort( $requested );
        $years     = $requested;
        $year_from = $years[0];
        $year_to   = end( $years );
    }
}
$hidden_history_charts = (array) get_option( 'naws_history_hidden_charts', [] );

// Dynamic NAModule4 charts — indoor temperature and humidity per module
$_naws_m4_charts = NAWS_Helpers::indoor_chart_defs();
// 5 static charts + two per NAModule4
$_naws_total_history_charts = 5 + count( $_naws_m4_charts );
?>
<?php // Chart.js loaded via wp_enqueue_script( 'chartjs' ) — see class-naws-shortcodes.php ?>

<div id="<?php echo esc_attr($widget_id); ?>" class="naws-hist"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-ajax="<?php echo esc_attr($ajax_url); ?>"
     data-outdoor="<?php echo esc_attr($outdoor_id); ?>"
     data-indoor="<?php echo esc_attr($indoor_id); ?>"
     data-years="<?php echo esc_attr(implode(',', $years)); ?>">

  <div class="naws-hist-hdr">
    <div class="naws-hist-title"><?php echo esc_html($atts['title'] ?? 'Historische Wetterdaten'); ?></div>
    <div class="naws-hist-range"><?php echo esc_html( $year_from === $year_to ? (string) $year_from : $year_from . ' – ' . $year_to ); ?></div>
  </div>

  <div class="naws-hist-body">
    <?php if ( count( $hidden_history_charts ) < $_naws_total_history_charts ) : ?>
    <div class="naws-hist-loading"><div class="naws-hist-spin"></div></div>
    <?php else : ?>
    <div class="naws-hist-all-hidden">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      <?php naws_e( 'hc_all_hidden' ); ?>
    </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($widget_id); ?>-charts" style="display:none">

      <?php if ( ! in_array( 'temp_minmax', $hidden_history_charts, true ) ) : ?>
      <!-- Temperatur Min/Max -->
      <div class="naws-hc-wrap" data-chart="temp_minmax">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_temp_minmax' ); ?></div>
          <button class="naws-hc-expand" data-target="temp_minmax" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-temp_minmax" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-temp_minmax"></div>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'temp_avg', $hidden_history_charts, true ) ) : ?>
      <!-- Durchschnittstemperatur -->
      <div class="naws-hc-wrap" data-chart="temp_avg">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_temp_avg' ); ?></div>
          <button class="naws-hc-expand" data-target="temp_avg" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-temp_avg" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-temp_avg"></div>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'pressure', $hidden_history_charts, true ) ) : ?>
      <!-- Luftdruck -->
      <div class="naws-hc-wrap" data-chart="pressure">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_pressure' ); ?></div>
          <button class="naws-hc-expand" data-target="pressure" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-pressure" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-pressure"></div>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'rain', $hidden_history_charts, true ) ) : ?>
      <!-- Jahresregen -->
      <div class="naws-hc-wrap" data-chart="rain">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_rain' ); ?></div>
          <button class="naws-hc-expand" data-target="rain" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-rain" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-rain"></div>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'humidity', $hidden_history_charts, true ) ) : ?>
      <!-- Außenluftfeuchte -->
      <div class="naws-hc-wrap" data-chart="humidity">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_humidity' ); ?></div>
          <button class="naws-hc-expand" data-target="humidity" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-humidity" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-humidity"></div>
      </div>
      <?php endif; ?>

      <?php foreach ( $_naws_m4_charts as $_m4c ) : ?>
      <?php if ( ! in_array( $_m4c['id'], $hidden_history_charts, true ) ) : ?>
      <!-- NAModule4: Innentemperatur bzw. Innenluftfeuchte -->
      <div class="naws-hc-wrap" data-chart="<?php echo esc_attr( $_m4c['id'] ); ?>">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php echo esc_html( $_m4c['label'] ); ?></div>
          <button class="naws-hc-expand" data-target="<?php echo esc_attr( $_m4c['id'] ); ?>" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr( $widget_id . '-' . $_m4c['id'] ); ?>" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr( $widget_id . '-leg-' . $_m4c['id'] ); ?>"></div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>

    </div>
  </div>
</div>

<!-- Modal -->
<div id="<?php echo esc_attr($widget_id); ?>-modal" class="naws-hist-modal" style="display:none">
  <div class="naws-hist-modal-bg"></div>
  <div class="naws-hist-modal-box">
    <div class="naws-hist-modal-hdr">
      <span class="naws-hist-modal-title"></span>
      <button class="naws-hist-modal-close">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="naws-hist-modal-body">
      <canvas id="<?php echo esc_attr($widget_id); ?>-modal-canvas"></canvas>
    </div>
    <div class="naws-hist-modal-leg"></div>
  </div>
</div>

<!-- Styles moved to assets/css/frontend.css (.naws-hist scope) -->

<?php
$_naws_hist_opts      = get_option( 'naws_settings', [] );
$_naws_hist_temp_unit = ( $_naws_hist_opts['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
$_naws_hist_pres_unit = NAWS_Helpers::get_unit( 'Pressure' );
$_naws_hist_rain_unit = $_naws_hist_opts['rain_unit'] ?? 'mm';

// ── Data container: non-executable JSON element, always output reliably ──────
// Same pattern as live.php: wp_add_inline_script() is silently dropped on some
// WP setups (the inline fragment never reaches the page), which left every
// history chart blank. A type="application/json" element is plain markup and
// always survives.
// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- type=application/json is not executable; pure data container
echo '<script type="application/json" data-naws="history" id="' . esc_attr( $widget_id ) . '-data">'
    . wp_json_encode( [
        'WID'         => $widget_id,
        'AJAX'        => $ajax_url,
        'PALETTE'     => NAWS_Colors::get_history_palette(),
        'MONTHS'      => [
            naws__( 'month_jan' ), naws__( 'month_feb' ), naws__( 'month_mar' ), naws__( 'month_apr' ),
            naws__( 'month_may' ), naws__( 'month_jun' ), naws__( 'month_jul' ), naws__( 'month_aug' ),
            naws__( 'month_sep' ), naws__( 'month_oct' ), naws__( 'month_nov' ), naws__( 'month_dec' ),
        ],
        'CHART_THEME' => NAWS_Colors::get_chart_theme(),
        'LBL_MIN'     => naws__( 'lbl_min' ),
        'LBL_MAX'     => naws__( 'lbl_max' ),
        // One chart definition per canvas (5 static + one per NAModule4 module)
        'DEFS'        => [
            [ 'id' => 'temp_minmax', 'title' => naws__( 'hc_temp_minmax' ), 'type' => 'line', 'unit' => $_naws_hist_temp_unit, 'fields' => [ 'temp_min', 'temp_max' ], 'moduleId' => '' ],
            [ 'id' => 'temp_avg',    'title' => naws__( 'hc_temp_avg' ),    'type' => 'line', 'unit' => $_naws_hist_temp_unit, 'fields' => [ 'temp_avg' ],             'moduleId' => '' ],
            [ 'id' => 'pressure',    'title' => naws__( 'hc_pressure' ),    'type' => 'line', 'unit' => $_naws_hist_pres_unit, 'fields' => [ 'pressure_avg' ],         'moduleId' => '' ],
            [ 'id' => 'rain',        'title' => naws__( 'hc_rain' ),        'type' => 'bar',  'unit' => $_naws_hist_rain_unit, 'fields' => [ 'rain_sum' ],             'moduleId' => '' ],
            [ 'id' => 'humidity',    'title' => naws__( 'hc_humidity' ),    'type' => 'line', 'unit' => '%',                   'fields' => [ 'humidity_avg' ],         'moduleId' => '' ],
            ...array_map( function( $_m4c ) {
                // Unit and field come from the definition now: the pair is
                // temperature and humidity, and only one of them is a percent.
                return [ 'id' => $_m4c['id'], 'title' => $_m4c['label'], 'type' => 'line', 'unit' => $_m4c['unit'], 'fields' => [ $_m4c['field'] ], 'moduleId' => $_m4c['module_id'] ];
            }, $_naws_m4_charts ),
        ],
    ] )
    . '</script>';

// The chart code lives in assets/js/history-boot.js and is enqueued by
// NAWS_Shortcodes::sc_history(). It picks up the JSON element above by its
// data-naws attribute, so several [naws_history] shortcodes on one page all
// boot from the single registered file.
