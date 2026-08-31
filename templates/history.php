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
// Every chart there is, in the order the settings screen arranged them.
$_naws_history_charts       = NAWS_Helpers::history_chart_defs();
$_naws_total_history_charts = count( $_naws_history_charts );
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
      <?php esc_html_e( 'All annual comparisons are currently disabled.', 'xtx-integration-for-netatmo' ); ?>
    </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($widget_id); ?>-charts" style="display:none">
      <?php
      // One block per chart, in the order the settings screen arranged them.
      // The canvases carry the chart id, and history-boot.js finds them by
      // that id — so the order here is the whole of what the reader sees.
      foreach ( $_naws_history_charts as $_hc ) :
          if ( in_array( $_hc['id'], $hidden_history_charts, true ) ) {
              continue;
          }
      ?>
      <div class="naws-hc-wrap" data-chart="<?php echo esc_attr( $_hc['id'] ); ?>">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php echo esc_html( $_hc['label'] ); ?></div>
          <button class="naws-hc-expand" data-target="<?php echo esc_attr( $_hc['id'] ); ?>" title="<?php echo esc_attr( __( 'Expand', 'xtx-integration-for-netatmo' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr( $widget_id . '-' . $_hc['id'] ); ?>" height="90"></canvas>
        <div class="naws-hc-legend" id="<?php echo esc_attr( $widget_id . '-leg-' . $_hc['id'] ); ?>"></div>
      </div>
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
            __( 'Jan', 'xtx-integration-for-netatmo' ), __( 'Feb', 'xtx-integration-for-netatmo' ), __( 'Mar', 'xtx-integration-for-netatmo' ), __( 'Apr', 'xtx-integration-for-netatmo' ),
            __( 'May', 'xtx-integration-for-netatmo' ), __( 'Jun', 'xtx-integration-for-netatmo' ), __( 'Jul', 'xtx-integration-for-netatmo' ), __( 'Aug', 'xtx-integration-for-netatmo' ),
            __( 'Sep', 'xtx-integration-for-netatmo' ), __( 'Oct', 'xtx-integration-for-netatmo' ), __( 'Nov', 'xtx-integration-for-netatmo' ), __( 'Dec', 'xtx-integration-for-netatmo' ),
        ],
        'CHART_THEME' => NAWS_Colors::get_chart_theme(),
        'LBL_MIN'     => __( 'Min', 'xtx-integration-for-netatmo' ),
        'LBL_MAX'     => __( 'Max', 'xtx-integration-for-netatmo' ),
        // One chart definition per canvas (5 static + one per NAModule4 module)
        'DEFS'        => [
            [ 'id' => 'temp_minmax', 'title' => __( 'Temperature Min / Max', 'xtx-integration-for-netatmo' ), 'type' => 'line', 'unit' => $_naws_hist_temp_unit, 'fields' => [ 'temp_min', 'temp_max' ], 'moduleId' => '' ],
            [ 'id' => 'temp_avg',    'title' => __( 'Annual Average Temperature', 'xtx-integration-for-netatmo' ),    'type' => 'line', 'unit' => $_naws_hist_temp_unit, 'fields' => [ 'temp_avg' ],             'moduleId' => '' ],
            [ 'id' => 'pressure',    'title' => __( 'Pressure (Annual Mean)', 'xtx-integration-for-netatmo' ),    'type' => 'line', 'unit' => $_naws_hist_pres_unit, 'fields' => [ 'pressure_avg' ],         'moduleId' => '' ],
            [ 'id' => 'rain',        'title' => __( 'Annual Precipitation', 'xtx-integration-for-netatmo' ),        'type' => 'bar',  'unit' => $_naws_hist_rain_unit, 'fields' => [ 'rain_sum' ],             'moduleId' => '' ],
            [ 'id' => 'humidity',    'title' => __( 'Outdoor Humidity (Annual Mean)', 'xtx-integration-for-netatmo' ),    'type' => 'line', 'unit' => '%',                   'fields' => [ 'humidity_avg' ],         'moduleId' => '' ],
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
