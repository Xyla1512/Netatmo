<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
/**
 * Template: [naws_forecast days="5" title=""]
 *
 * Variables: $forecast (array), $atts (shortcode attrs)
 * @since 0.9.94
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$days      = $forecast['days'] ?? [];
$has_error = isset( $forecast['error'] );
$title     = $atts['title'] ?: sprintf( naws__( 'forecast_title' ), count( $forecast['days'] ?? [] ) );
$loc_name  = $forecast['location_name'] ?? '';

// Unit settings
$options   = get_option( 'naws_settings', [] );
$temp_unit = ( $options['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
$wind_unit = NAWS_Helpers::wind_unit_label_public( $options['wind_unit'] ?? 'kmh' );
$rain_unit = ( $options['rain_unit'] ?? 'mm' ) === 'in' ? 'in' : 'mm';
$wu        = $options['wind_unit'] ?? 'kmh';

$fc_id = 'naws-fc-' . wp_unique_id();
?>


<div id="<?php echo esc_attr( $fc_id ); ?>" class="naws-fc-wrap">

  <!-- ── Header (like Live dashboard) ──────────────────────────────── -->
  <div class="naws-fc-hdr">
    <div class="naws-fc-hdr-title"><?php echo esc_html( $title ); ?></div>
    <div class="naws-fc-hdr-meta">
      <?php if ( $loc_name ) : ?>
        <span class="naws-fc-hdr-loc">📍 <?php echo esc_html( $loc_name ); ?></span>
      <?php endif; ?>
      <?php if ( ! empty( $forecast['fetched_at'] ) ) : ?>
        <span class="naws-fc-hdr-time">
          <?php printf( esc_html( naws__( 'forecast_updated' ) ), esc_html( wp_date( get_option( 'time_format', 'H:i' ), $forecast['fetched_at'] ) ) ); ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Body ──────────────────────────────────────────────────────── -->
  <div class="naws-fc-body">
    <?php if ( $has_error ) : ?>
      <div class="naws-fc-error"><?php echo esc_html( $forecast['error'] ); ?></div>
    <?php elseif ( empty( $days ) ) : ?>
      <div class="naws-fc-error"><?php echo esc_html( naws__( 'forecast_no_data' ) ); ?></div>
    <?php else : ?>

      <div class="naws-fc-grid">
        <?php foreach ( $days as $day ) :
            $wmo      = NAWS_Forecast::wmo_description( $day['weathercode'] );
            $is_today = NAWS_Forecast::is_today( $day['date'] );
            $weekday  = $is_today ? naws__( 'forecast_today' ) : NAWS_Forecast::weekday_short( $day['date'] );
            $date_str = NAWS_Forecast::date_short( $day['date'] );

            $t_max = $day['temp_max'];
            $t_min = $day['temp_min'];
            if ( $temp_unit === '°F' ) {
                $t_max = $t_max !== null ? round( $t_max * 9 / 5 + 32, 1 ) : null;
                $t_min = $t_min !== null ? round( $t_min * 9 / 5 + 32, 1 ) : null;
            }

            $w_max = $day['wind_max'];
            $g_max = $day['gust_max'];
            if ( $wu === 'ms' )  { $w_max = $w_max !== null ? round( $w_max / 3.6, 1 ) : null; $g_max = $g_max !== null ? round( $g_max / 3.6, 1 ) : null; }
            if ( $wu === 'mph' ) { $w_max = $w_max !== null ? round( $w_max * 0.62137, 1 ) : null; $g_max = $g_max !== null ? round( $g_max * 0.62137, 1 ) : null; }
            if ( $wu === 'kn' )  { $w_max = $w_max !== null ? round( $w_max * 0.53996, 1 ) : null; $g_max = $g_max !== null ? round( $g_max * 0.53996, 1 ) : null; }

            $precip = $day['precip_sum'];
            if ( $rain_unit === 'in' && $precip !== null ) $precip = round( $precip / 25.4, 2 );

            $compass = NAWS_Helpers::degrees_to_compass( $day['wind_dir'] );
        ?>
        <div class="naws-fc-card<?php echo $is_today ? ' naws-fc-card-today' : ''; ?>">
          <div class="naws-fc-day"><?php echo esc_html( $weekday ); ?></div>
          <div class="naws-fc-date"><?php echo esc_html( $date_str ); ?></div>
          <div class="naws-fc-svg"><?php echo NAWS_Forecast::get_weather_svg( $wmo['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from trusted internal method ?></div>
          <div class="naws-fc-cond"><?php echo esc_html( $wmo['label'] ); ?></div>
          <div class="naws-fc-temps">
            <span class="naws-fc-tmax"><?php echo $t_max !== null ? esc_html( $t_max ) : '--'; ?></span>
            <span class="naws-fc-sep">/</span>
            <span class="naws-fc-tmin"><?php echo $t_min !== null ? esc_html( $t_min ) : '--'; ?></span>
            <span class="naws-fc-tunit"><?php echo esc_html( $temp_unit ); ?></span>
          </div>
          <div class="naws-fc-meta">
            <span title="<?php echo esc_attr( naws__( 'forecast_precip' ) ); ?>">🌧️ <?php echo $precip !== null ? esc_html( $precip . ' ' . $rain_unit ) : '0 ' . esc_html( $rain_unit ); ?></span>
            <span title="<?php echo esc_attr( naws__( 'forecast_precip_prob' ) ); ?>">💧 <?php echo esc_html( $day['precip_prob'] . '%' ); ?></span>
            <span title="<?php echo esc_attr( naws__( 'forecast_wind' ) ); ?>">🌬️ <?php echo $w_max !== null ? esc_html( $w_max . ' ' . $wind_unit ) : '--'; ?></span>
            <span title="<?php echo esc_attr( naws__( 'forecast_wind_dir' ) ); ?>">🧭 <?php echo esc_html( $compass ); ?></span>
          </div>
          <?php if ( $g_max !== null && $g_max > 0 ) : ?>
          <div class="naws-fc-gust">🌪️ <?php echo esc_html( naws__( 'forecast_gusts' ) . ': ' . $g_max . ' ' . $wind_unit ); ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="naws-fc-footer">
        <?php echo esc_html( naws__( 'forecast_source' ) ); ?>: Open-Meteo.com (DWD ICON, ECMWF)
      </div>

    <?php endif; ?>
  </div>
</div>

<style>
#<?php echo esc_attr( $fc_id ); ?> {
  font-family: inherit;
  max-width: 100%;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-hdr {
  background: #2d5252;
  border-radius: 16px 16px 0 0;
  padding: 14px 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-hdr-title {
  font-size: 17px; font-weight: 800; font-style: italic; color: #fff;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-hdr-meta {
  display: flex; align-items: center; gap: 12px;
  color: rgba(255,255,255,.75); font-size: 12px; font-weight: 600;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-hdr-loc { white-space: nowrap; }
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-body {
  background: #ffffff;
  border: 1.5px solid #e0eeee;
  border-top: none;
  border-radius: 0 0 16px 16px;
  padding: 16px;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-grid {
  display: grid;
  grid-template-columns: repeat(<?php echo count( $days ); ?>, 1fr);
  gap: 10px;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-card {
  background: #ffffff;
  border: 1.5px solid #e0eeee;
  border-radius: 14px;
  padding: 18px 10px 14px;
  text-align: center;
  transition: transform .2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  box-shadow: 0 2px 10px rgba(40,72,72,.10);
  position: relative;
  overflow: hidden;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0;
  height: 3.5px;
  background: #427272;
  border-radius: 14px 14px 0 0;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 16px rgba(40,72,72,.15);
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-card-today {
  border-color: #427272;
  box-shadow: 0 2px 12px rgba(45,82,82,.18);
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-card-today::before {
  height: 4px;
  background: #3d9e74;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-day {
  font-weight: 800; font-size: 13px; color: #2d5252;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-card-today .naws-fc-day { color: #3d9e74; }
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-date {
  font-size: 10px; color: #7aa0a0; margin-bottom: 4px;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-svg {
  width: 44px; height: 44px; margin: 2px 0;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-svg svg { width: 100%; height: 100%; }
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-cond {
  font-size: 10px; color: #7aa0a0; font-weight: 600;
  min-height: 2.4em; line-height: 1.2;
  display: flex; align-items: center;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-temps {
  margin: 4px 0;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-tmax {
  font-size: 22px; font-weight: 800; font-style: italic; color: #2d5252;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-sep {
  font-size: 13px; color: #7aa0a0; margin: 0 2px;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-tmin {
  font-size: 14px; font-weight: 600; color: #7aa0a0;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-tunit {
  font-size: 10px; color: #7aa0a0;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3px 8px;
  font-size: 10px;
  color: #7aa0a0;
  padding-top: 6px;
  border-top: 1px solid #e0eeee;
  width: 100%;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-gust {
  font-size: 9px; color: #a0b8b8; margin-top: 3px;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-footer {
  text-align: center; margin-top: 8px;
  font-size: 10px; color: #a0b8b8;
}
#<?php echo esc_attr( $fc_id ); ?> .naws-fc-error {
  text-align: center; padding: 40px 20px; color: #7aa0a0; font-style: italic;
}
/* Responsive */
@media (max-width: 700px) {
  #<?php echo esc_attr( $fc_id ); ?> .naws-fc-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 450px) {
  #<?php echo esc_attr( $fc_id ); ?> .naws-fc-grid { grid-template-columns: repeat(2, 1fr); }
  #<?php echo esc_attr( $fc_id ); ?> .naws-fc-hdr { border-radius: 12px 12px 0 0; padding: 12px 16px; }
  #<?php echo esc_attr( $fc_id ); ?> .naws-fc-body { border-radius: 0 0 12px 12px; }
}
</style>
