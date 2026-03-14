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

      <div class="naws-fc-grid" style="--naws-fc-days:<?php echo count( $days ); ?>">
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

<!-- Styles moved to assets/css/frontend.css (.naws-fc-wrap scope) -->
