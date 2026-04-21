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
$range = NAWS_Database::get_daily_data_range();
$year_from = $range ? (int) substr($range['date_begin'], 0, 4) : (int) gmdate( 'Y');
$year_to   = $range ? (int) substr($range['date_end'],   0, 4) : (int) gmdate( 'Y');
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

// Dynamic NAModule4 indoor humidity charts — one per indoor module
$_naws_m4_charts = [];
foreach ( NAWS_Database::get_modules( true ) as $_m ) {
    if ( $_m['module_type'] !== 'NAModule4' ) continue;
    $_slug = preg_replace( '/[^a-z0-9]/', '', strtolower( $_m['module_name'] ) );
    if ( $_slug === '' ) $_slug = 'indoor' . substr( str_replace( ':', '', $_m['module_id'] ), -4 );
    $_slug = substr( $_slug, 0, 16 );
    $_naws_m4_charts[] = [
        'id'        => 'indoor_humidity_' . $_slug,
        'module_id' => $_m['module_id'],
        'title'     => esc_html( $_m['module_name'] ) . ' – ' . naws__( 'param_humidity' ),
    ];
}
// 5 static charts + one per NAModule4
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
          <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-temp_minmax"></div>
          <button class="naws-hc-expand" data-target="temp_minmax" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-temp_minmax" height="90"></canvas>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'temp_avg', $hidden_history_charts, true ) ) : ?>
      <!-- Durchschnittstemperatur -->
      <div class="naws-hc-wrap" data-chart="temp_avg">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_temp_avg' ); ?></div>
          <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-temp_avg"></div>
          <button class="naws-hc-expand" data-target="temp_avg" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-temp_avg" height="90"></canvas>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'pressure', $hidden_history_charts, true ) ) : ?>
      <!-- Luftdruck -->
      <div class="naws-hc-wrap" data-chart="pressure">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_pressure' ); ?></div>
          <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-pressure"></div>
          <button class="naws-hc-expand" data-target="pressure" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-pressure" height="90"></canvas>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'rain', $hidden_history_charts, true ) ) : ?>
      <!-- Jahresregen -->
      <div class="naws-hc-wrap" data-chart="rain">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_rain' ); ?></div>
          <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-rain"></div>
          <button class="naws-hc-expand" data-target="rain" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-rain" height="90"></canvas>
      </div>
      <?php endif; ?>

      <?php if ( ! in_array( 'humidity', $hidden_history_charts, true ) ) : ?>
      <!-- Außenluftfeuchte -->
      <div class="naws-hc-wrap" data-chart="humidity">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php naws_e( 'hc_humidity' ); ?></div>
          <div class="naws-hc-legend" id="<?php echo esc_attr($widget_id); ?>-leg-humidity"></div>
          <button class="naws-hc-expand" data-target="humidity" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr($widget_id); ?>-humidity" height="90"></canvas>
      </div>
      <?php endif; ?>

      <?php foreach ( $_naws_m4_charts as $_m4c ) : ?>
      <?php if ( ! in_array( $_m4c['id'], $hidden_history_charts, true ) ) : ?>
      <!-- Innenluftfeuchte NAModule4 -->
      <div class="naws-hc-wrap" data-chart="<?php echo esc_attr( $_m4c['id'] ); ?>">
        <div class="naws-hc-bar">
          <div class="naws-hc-title"><?php echo wp_kses_post( $_m4c['title'] ); ?></div>
          <div class="naws-hc-legend" id="<?php echo esc_attr( $widget_id . '-leg-' . $_m4c['id'] ); ?>"></div>
          <button class="naws-hc-expand" data-target="<?php echo esc_attr( $_m4c['id'] ); ?>" title="<?php echo esc_attr( naws__( 'expand_chart' ) ); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
        </div>
        <canvas id="<?php echo esc_attr( $widget_id . '-' . $_m4c['id'] ); ?>" height="90"></canvas>
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
      <div class="naws-hist-modal-leg"></div>
      <button class="naws-hist-modal-close">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="naws-hist-modal-body">
      <canvas id="<?php echo esc_attr($widget_id); ?>-modal-canvas"></canvas>
    </div>
  </div>
</div>

<!-- Styles moved to assets/css/frontend.css (.naws-hist scope) -->

<?php
// Inject all PHP-derived values as a JS data object (runs before the main script).
wp_add_inline_script( 'naws-frontend', 'var NAWS_HIST = ' . wp_json_encode( [
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
] ) . ';', 'before' );

// Inject ALL_CHART_DEFS as PHP-derived data before the main script.
$_naws_hist_opts      = get_option( 'naws_settings', [] );
$_naws_hist_temp_unit = ( $_naws_hist_opts['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
$_naws_hist_pres_unit = NAWS_Helpers::get_unit( 'Pressure' );
$_naws_hist_rain_unit = $_naws_hist_opts['rain_unit'] ?? 'mm';
wp_add_inline_script( 'naws-frontend', 'var ALL_CHART_DEFS = ' . wp_json_encode( [
    [ 'id' => 'temp_minmax', 'title' => naws__( 'hc_temp_minmax' ), 'type' => 'line', 'unit' => $_naws_hist_temp_unit, 'fields' => [ 'temp_min', 'temp_max' ], 'moduleId' => '' ],
    [ 'id' => 'temp_avg',    'title' => naws__( 'hc_temp_avg' ),    'type' => 'line', 'unit' => $_naws_hist_temp_unit, 'fields' => [ 'temp_avg' ],             'moduleId' => '' ],
    [ 'id' => 'pressure',    'title' => naws__( 'hc_pressure' ),    'type' => 'line', 'unit' => $_naws_hist_pres_unit, 'fields' => [ 'pressure_avg' ],         'moduleId' => '' ],
    [ 'id' => 'rain',        'title' => naws__( 'hc_rain' ),        'type' => 'bar',  'unit' => $_naws_hist_rain_unit, 'fields' => [ 'rain_sum' ],             'moduleId' => '' ],
    [ 'id' => 'humidity',    'title' => naws__( 'hc_humidity' ),    'type' => 'line', 'unit' => '%',                   'fields' => [ 'humidity_avg' ],         'moduleId' => '' ],
    // Dynamic: one entry per NAModule4 indoor module
    ...array_map( function( $_m4c ) {
        return [ 'id' => $_m4c['id'], 'title' => $_m4c['title'], 'type' => 'line', 'unit' => '%', 'fields' => [ 'indoor_humidity_avg' ], 'moduleId' => $_m4c['module_id'] ];
    }, $_naws_m4_charts ),
] ) . ';', 'before' );

wp_add_inline_script( 'naws-frontend', <<<'EOJS'
(function(){
var WID     = NAWS_HIST.WID;
var NAWS_FONT = getComputedStyle(document.documentElement).fontFamily || 'sans-serif';
var AJAX    = NAWS_HIST.AJAX;
var NONCE   = document.getElementById(WID).dataset.nonce;
var OUTDOOR = document.getElementById(WID).dataset.outdoor;
var INDOOR  = document.getElementById(WID).dataset.indoor;
var YEARS   = document.getElementById(WID).dataset.years.split(',').map(Number).filter(Boolean);

var chartsEl = document.getElementById(WID+'-charts');
var loadEl   = document.querySelector('#'+WID+' .naws-hist-loading');

// Store per-chart: { config, yearDatasets:{year:{labels,data}}, hiddenYears:Set, chartObj }
var CHARTS = {};
var modalChart = null;

/* ── COLOURS ─────────────────────────────── */
// One colour per year, consistent palette (configurable via Admin > Appearance)
var PALETTE = NAWS_HIST.PALETTE;
function yearColor(yr){ return PALETTE[(yr - YEARS[0]) % PALETTE.length]; }

/* ── AJAX ────────────────────────────────── */
function post(params, cb){
  var xhr=new XMLHttpRequest();
  xhr.open('POST',AJAX);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onload=function(){try{cb(JSON.parse(xhr.responseText));}catch(e){cb(null);}};
  var body='nonce='+encodeURIComponent(NONCE);
  Object.keys(params).forEach(function(k){
    var v=params[k];
    if(Array.isArray(v)) v.forEach(function(vi){body+='&'+encodeURIComponent(k)+'[]='+encodeURIComponent(vi);});
    else body+='&'+encodeURIComponent(k)+'='+encodeURIComponent(v);
  });
  xhr.send(body);
}

/* ── MONTH LABELS ───────────────────────── */
var MONTHS = NAWS_HIST.MONTHS;
function monthLabel(iso){ return MONTHS[parseInt(iso.slice(5,7),10)-1]; }

/* ── CHART.JS BASE OPTIONS ──────────────── */
/* Fixed Jan-Dec x-axis labels */
var MONTH_LABELS = MONTHS.slice();
var MONTH_TICKS  = ['01-01','02-01','03-01','04-01','05-01','06-01',
                    '07-01','08-01','09-01','10-01','11-01','12-01'];

/* Aggregate daily {x:'MM-DD',y:val} data to 12 monthly sums */
function aggregateMonthly(dailyData){
  var sums = [0,0,0,0,0,0,0,0,0,0,0,0];
  for(var i=0;i<dailyData.length;i++){
    var pt = dailyData[i];
    var mi = parseInt(pt.x.slice(0,2),10)-1;
    if(mi>=0 && mi<12 && pt.y!==null && !isNaN(pt.y)) sums[mi]+=parseFloat(pt.y);
  }
  var out=[];
  for(var m=0;m<12;m++) out.push({x:MONTH_LABELS[m], y:Math.round(sums[m]*100)/100});
  return out;
}

function nawsHistFontSize(){ var w=window.innerWidth; return w<480?8:w<768?9:10; }

/* Chart theme colors (configurable via Admin > Appearance) */
var CHART_THEME = NAWS_HIST.CHART_THEME;

function baseOpts(unit, type, isModal, isMonthly){
  var fs = nawsHistFontSize();
  var xConfig;
  if(isMonthly){
    xConfig = {
      type:'category',
      labels: MONTH_LABELS,
      grid:{color:CHART_THEME.grid},
      ticks:{
        color:CHART_THEME.tick, font:{family:NAWS_FONT,size:fs},
        maxRotation:0, autoSkip:false
      }
    };
  } else {
    xConfig = {
      type:'category',
      labels: (function(){
        var arr=[];
        var days=[31,28,31,30,31,30,31,31,30,31,30,31];
        for(var m=0;m<12;m++){
          for(var d=1;d<=days[m];d++){
            arr.push((m+1<10?'0':'')+(m+1)+'-'+(d<10?'0':'')+d);
          }
        }
        return arr;
      })(),
      grid:{color:CHART_THEME.grid},
      ticks:{
        color:CHART_THEME.tick, font:{family:NAWS_FONT,size:fs},
        maxRotation:0, autoSkip:false,
        callback:function(val,idx){
          var lbl = this.getLabelForValue(idx);
          return lbl && lbl.slice(3)==='01' ? MONTH_LABELS[parseInt(lbl.slice(0,2),10)-1] : '';
        }
      }
    };
  }
  return {
    responsive:true, maintainAspectRatio:!isModal,
    animation:{duration:600,easing:'easeInOutQuart'},
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:CHART_THEME.tooltip_bg,
        titleColor:CHART_THEME.tooltip_title, bodyColor:CHART_THEME.tooltip_text,
        titleFont:{family:NAWS_FONT,size:fs+1},
        bodyFont:{family:NAWS_FONT,size:fs+2,weight:'bold'},
        padding:10, cornerRadius:8, displayColors:true, boxWidth:10, boxHeight:10,
        callbacks:{
          title:function(items){
            var x = items[0] ? items[0].label : '';
            if(isMonthly) return x;
            var parts = x.split('-');
            if(parts.length===2){
              var mo = parseInt(parts[0],10)-1;
              return MONTH_LABELS[mo]+' '+parseInt(parts[1],10)+'.';
            }
            return x;
          },
          label:function(c){return ' '+c.dataset.label+': '+c.parsed.y+' '+unit;}
        }
      }
    },
    scales:{
      x: xConfig,
      y:{
        grid:{color:CHART_THEME.grid},
        ticks:{
          color:CHART_THEME.tick,font:{family:NAWS_FONT,size:fs},
          callback:function(v){return v;}
        },
        title:{display:true,text:unit,color:CHART_THEME.axis_title,font:{family:NAWS_FONT,size:fs,weight:'600'}}
      }
    }
  };
}

/* ── BUILD DATASET FOR ONE YEAR ─────────── */
function buildDataset(chartId, year, data, type){
  var c = yearColor(year);
  var r=parseInt(c.slice(1,3),16),g=parseInt(c.slice(3,5),16),b=parseInt(c.slice(5,7),16);
  var bg = type==='bar' ? 'rgba('+r+','+g+','+b+',.45)' : 'rgba('+r+','+g+','+b+',.12)';
  return {
    label: String(year),
    data: data,           // array of {x:'MM-DD', y:val}
    borderColor: c,
    backgroundColor: bg,
    borderWidth: type==='bar' ? 1.5 : 2,
    pointRadius: 0, pointHoverRadius: 4,
    tension: 0.35,
    fill: false,
    borderRadius: type==='bar' ? 4 : 0,
    barPercentage: 0.9,
    categoryPercentage: 0.8,
    hidden: CHARTS[chartId] ? CHARTS[chartId].hiddenYears.has(year) : false,
    parsing: { xAxisKey:'x', yAxisKey:'y' },
  };
}

/* ── RENDER CHART ───────────────────────── */
function renderChart(chartId, isModal){
  var cfg = CHARTS[chartId]; if(!cfg) return;
  var canvasId = isModal ? WID+'-modal-canvas' : WID+'-'+chartId;
  var ctx = document.getElementById(canvasId); if(!ctx) return;

  if(isModal && modalChart){ modalChart.destroy(); modalChart=null; }
  if(!isModal && cfg.chartObj){ cfg.chartObj.destroy(); cfg.chartObj=null; }

  var isMonthly = cfg.type === 'bar';
  var opts = baseOpts(cfg.unit, cfg.type, isModal, isMonthly);
  var datasets = [];

  YEARS.forEach(function(yr){
    var yd = cfg.yearData[yr]; if(!yd) return;
    datasets.push(buildDataset(chartId, yr, yd.values, cfg.type));
  });

  // For minmax: rebuild datasets with min=dashed, max=solid
  if(chartId==='temp_minmax'){
    datasets = [];
    YEARS.forEach(function(yr){
      var yd = cfg.yearData[yr]; if(!yd) return;
      var c = yearColor(yr);
      var ri=parseInt(c.slice(1,3),16),gi=parseInt(c.slice(3,5),16),bi=parseInt(c.slice(5,7),16);
      datasets.push({
        label: yr+' '+NAWS_HIST.LBL_MIN, data: yd.values_min||[],
        borderColor:'rgba('+ri+','+gi+','+bi+',.55)',
        backgroundColor:'rgba('+ri+','+gi+','+bi+',.06)',
        borderWidth:1.5, borderDash:[4,3],
        pointRadius:0, pointHoverRadius:3, tension:0.35, fill:false,
        parsing:{xAxisKey:'x',yAxisKey:'y'},
        hidden: cfg.hiddenYears.has(yr),
      });
      datasets.push({
        label: yr+' '+NAWS_HIST.LBL_MAX, data: yd.values_max||[],
        borderColor:c, backgroundColor:'rgba('+ri+','+gi+','+bi+',.12)',
        borderWidth:2, pointRadius:0, pointHoverRadius:4, tension:0.35, fill:false,
        parsing:{xAxisKey:'x',yAxisKey:'y'},
        hidden: cfg.hiddenYears.has(yr),
      });
    });
  }

  var chartObj = new Chart(ctx, {
    type: cfg.type==='bar' ? 'bar' : 'line',
    data:{ datasets:datasets },
    options: opts,
  });

  if(isModal) modalChart = chartObj;
  else cfg.chartObj = chartObj;
}

/* ── BUILD LEGEND ───────────────────────── */
function buildLegend(chartId, containerId){
  var cfg = CHARTS[chartId]; if(!cfg) return;
  var el = document.getElementById(containerId); if(!el) return;
  el.innerHTML = '';

  YEARS.forEach(function(yr){
    if(!cfg.yearData[yr]) return; // no data for this year
    var c = yearColor(yr);
    var pill = document.createElement('span');
    pill.className = 'naws-leg-pill' + (cfg.hiddenYears.has(yr)?' hidden':'');
    pill.dataset.year = yr;
    pill.dataset.chart = chartId;
    pill.style.borderColor = c;
    pill.style.background = cfg.hiddenYears.has(yr) ? 'transparent' : c+'18';

    // For rain chart: show yearly total in the pill
    var extra = '';
    if(chartId === 'rain'){
      try{
        var vals = cfg.yearData[yr] && cfg.yearData[yr].values;
        if(vals && vals.length){
          var total = 0;
          for(var i=0;i<vals.length;i++){
            var v = vals[i];
            var n = (v && typeof v==='object') ? v.y : v;
            if(n !== null && n !== undefined && !isNaN(n)) total += parseFloat(n);
          }
          extra = '<span class="naws-leg-rain-total">'+Math.round(total)+' '+cfg.unit+'</span>';
        }
      }catch(e){}
    }

    pill.innerHTML = '<span class="naws-leg-pill-dot" style="background:'+c+'"></span>'+yr+extra;
    pill.addEventListener('click', function(e){
      e.stopPropagation();
      toggleYear(chartId, yr, containerId);
    });
    el.appendChild(pill);
  });
}

function toggleYear(chartId, year, legendId){
  var cfg = CHARTS[chartId]; if(!cfg) return;
  if(cfg.hiddenYears.has(year)) cfg.hiddenYears.delete(year);
  else cfg.hiddenYears.add(year);

  // Update chart datasets visibility
  if(cfg.chartObj){
    cfg.chartObj.data.datasets.forEach(function(ds){
      var dsYear = parseInt(ds.label);
      if(dsYear===year) ds.hidden = cfg.hiddenYears.has(year);
      // minmax: label is "2023 Min" / "2023 Max"
      if(ds.label.startsWith(year+' ')) ds.hidden = cfg.hiddenYears.has(year);
    });
    cfg.chartObj.update();
  }
  // Also update modal if open for same chart
  if(modalChart && currentModalChart===chartId){
    modalChart.data.datasets.forEach(function(ds){
      var dsYear = parseInt(ds.label);
      if(dsYear===year) ds.hidden = cfg.hiddenYears.has(year);
      if(ds.label.startsWith(year+' ')) ds.hidden = cfg.hiddenYears.has(year);
    });
    modalChart.update();
    // Sync modal legend
    buildLegend(chartId, WID+'-modal-leg-'+chartId);
  }

  buildLegend(chartId, legendId);
  buildLegend(chartId, WID+'-modal-leg-'+chartId);
}

/* ── MODAL ───────────────────────────────── */
var modal = document.getElementById(WID+'-modal');
var currentModalChart = null;

function openModal(chartId){
  if(!modal||!CHARTS[chartId]) return;
  currentModalChart = chartId;
  var cfg = CHARTS[chartId];

  modal.querySelector('.naws-hist-modal-title').textContent = cfg.title;

  // Build modal legend
  var mleg = modal.querySelector('.naws-hist-modal-leg');
  var legId = WID+'-modal-leg-'+chartId;
  mleg.id = legId;
  mleg.innerHTML = '';
  buildLegend(chartId, legId);

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  setTimeout(function(){ renderChart(chartId, true); }, 30);
}
function closeModal(){
  if(!modal) return;
  modal.style.display = 'none';
  document.body.style.overflow = '';
  if(modalChart){ modalChart.destroy(); modalChart=null; }
  currentModalChart = null;
}

modal.querySelector('.naws-hist-modal-bg').addEventListener('click', closeModal);
modal.querySelector('.naws-hist-modal-close').addEventListener('click', closeModal);
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeModal(); });

// Expand buttons + card click
document.addEventListener('click', function(e){
  var btn = e.target.closest('.naws-hc-expand');
  if(btn){ openModal(btn.dataset.target); return; }
  var wrap = e.target.closest('.naws-hc-wrap');
  if(wrap && !e.target.closest('.naws-leg-pill')){ openModal(wrap.dataset.chart); }
});

/* ── LOAD DATA ───────────────────────────── */
// Chart definitions
/* ── CHART DEFINITIONS ──────────────────── */
// Each chart fetches one request per year spanning Jan-Dec, group_by=month
// The daily_data endpoint returns datasets keyed by field label (e.g. "Temp Min (°C)")
// We identify fields by checking which field key was requested
// All possible chart definitions
// ALL_CHART_DEFS is injected by PHP via wp_add_inline_script() before this script.

// Only initialize charts whose canvas is actually in the DOM (not disabled by admin)
var CHART_DEFS = ALL_CHART_DEFS.filter(function(def){
  return !!document.getElementById(WID+'-'+def.id);
});

CHART_DEFS.forEach(function(def){
  CHARTS[def.id] = {
    title:def.title, type:def.type, unit:def.unit,
    fields:def.fields, yearData:{}, hiddenYears:new Set(), chartObj:null,
  };
});



/* One request per chart: fetch all years at once from dedicated history endpoint */
var pending = CHART_DEFS.length;
var loaded  = 0;

// If all charts are disabled by admin, skip spinner immediately
if(pending === 0){
  if(loadEl) loadEl.style.display = 'none';
}

function checkDone(){
  if(++loaded >= pending){
    loadEl.style.display = 'none';
    chartsEl.style.display = '';
    CHART_DEFS.forEach(function(def){
      renderChart(def.id, false);
      buildLegend(def.id, WID+'-leg-'+def.id);
    });
  }
}

CHART_DEFS.forEach(function(def){
  var body = 'nonce='+encodeURIComponent(NONCE)
    +'&action=naws_get_history_data'
    +'&year_from='+YEARS[0]
    +'&year_to='+YEARS[YEARS.length-1];
  def.fields.forEach(function(f){ body+='&fields[]='+encodeURIComponent(f); });
  if(def.moduleId) body+='&module_id='+encodeURIComponent(def.moduleId);

  var xhr=new XMLHttpRequest();
  xhr.open('POST',AJAX);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onload=function(){
    try{
      var r=JSON.parse(xhr.responseText);
      if(r&&r.success&&r.data&&r.data.series&&r.data.series.length){
        // series = [{year, field, data:[{x:'Jan',y:val},...]}]
        r.data.series.forEach(function(s){
          var yr = s.year;
          if(!CHARTS[def.id].yearData[yr]){
            CHARTS[def.id].yearData[yr]={labels:[],values:[],values_min:[],values_max:[]};
          }
          var yd = CHARTS[def.id].yearData[yr];
          // labels: use x value; fill empty strings with previous non-empty label
          // Store as {x:'MM-DD', y:val} objects for scatter-style category mapping
          if(s.field==='temp_min')      yd.values_min = s.data;
          else if(s.field==='temp_max') yd.values_max = s.data;
          else yd.values = def.type==='bar' ? aggregateMonthly(s.data) : s.data;
        });
      } else {
        console.warn('naws history NO DATA', def.id, r&&r.data||'no data');
      }
    }catch(e){ console.warn('naws history error',e,xhr.responseText.substr(0,300)); }
    checkDone();
  };
  xhr.onerror=function(){ checkDone(); };
  xhr.send(body);
});

/* ── RESPONSIVE: update chart fonts on resize ── */
var _nawsHistResizeTimer;
window.addEventListener('resize', function(){
  clearTimeout(_nawsHistResizeTimer);
  _nawsHistResizeTimer = setTimeout(function(){
    var fs = nawsHistFontSize();
    Object.keys(CHARTS).forEach(function(id){
      var ch = CHARTS[id].chartObj;
      if(!ch) return;
      if(ch.options.scales && ch.options.scales.x && ch.options.scales.x.ticks) ch.options.scales.x.ticks.font.size = fs;
      if(ch.options.scales && ch.options.scales.y && ch.options.scales.y.ticks) ch.options.scales.y.ticks.font.size = fs;
      if(ch.options.scales && ch.options.scales.y && ch.options.scales.y.title) ch.options.scales.y.title.font.size = fs;
      ch.update('none');
    });
  }, 250);
});

})();
EOJS
);
