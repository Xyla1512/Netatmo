<?php
/**
 * Template: [naws_weather_widget days="3|5"]
 *
 * The head icon is literal markup because the multi-colour set does not
 * survive naws_svg_kses_args(); see templates/weather-icon.php. Everything
 * else on this page is escaped normally.
 *
 * Expected variables:
 * @var array   $naws_wgt       Result of NAWS_Widget_Data::build()
 * @var string  $naws_wgt_state Current weather state, '' if unknown
 * @var string  $naws_wgt_place Location name, '' to omit
 * @var string  $naws_wgt_time  Formatted time of last fetch, '' to omit
 * @var int     $naws_wgt_width Widget width in px, 250–500; optional
 *
 * @package NAWS
 * @since   1.8.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! empty( $naws_wgt['empty'] ) ) {
    return; // Nothing determinable – render nothing, not an empty frame.
}
$naws_wgt_cols = count( $naws_wgt['days'] );

// Travels as a custom property, like --naws-wgt-cols below. The stylesheet
// applies it as a max-width, never a width: in a container narrower than the
// setting the widget has to shrink rather than overflow.
$naws_wgt_max = NAWS_Widget_Data::normalise_width( $naws_wgt_width ?? null );
?>
<div class="naws-wgt" style="--naws-wgt-max:<?php echo absint( $naws_wgt_max ); ?>px">

  <div class="naws-wgt-head">
    <?php if ( $naws_wgt_state !== '' ) : ?>
      <?php
      // render_head(), not render_inline(): this is the widget's statement
      // icon and animates. 64 px is the floor for a 250 px widget; the
      // stylesheet scales it up to 96 px as the container grows.
      echo NAWS_Weather_Icons::render_head( $naws_wgt_state, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG
      ?>
    <?php endif; ?>
    <div class="naws-wgt-head-txt">
      <?php if ( $naws_wgt['temp'] !== null ) : ?>
        <div class="naws-wgt-temp"><?php echo esc_html( $naws_wgt['temp']['value'] ); ?><span class="naws-wgt-deg"> <?php echo esc_html( $naws_wgt['temp']['unit'] ); ?></span></div>
      <?php endif; ?>
      <?php if ( $naws_wgt_state !== '' ) : ?>
        <div class="naws-wgt-cond"><?php echo esc_html( NAWS_Weather_Icons::label( $naws_wgt_state ) ); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ( $naws_wgt['tiles'] ) : ?>
    <div class="naws-wgt-chips">
      <?php foreach ( $naws_wgt['tiles'] as $naws_wgt_tile ) : ?>
        <div class="naws-wgt-chip">
          <span class="naws-wgt-k"><?php echo esc_html( naws__( 'wgt_' . $naws_wgt_tile['key'] ) ); ?></span>
          <span class="naws-wgt-v"><?php echo esc_html( $naws_wgt_tile['value'] ); ?><span class="naws-wgt-sub"> <?php echo esc_html( $naws_wgt_tile['unit'] ); ?></span></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( $naws_wgt_cols > 0 ) : ?>
    <div class="naws-wgt-strip" style="--naws-wgt-cols:<?php echo absint( $naws_wgt_cols ); ?>">
      <?php foreach ( $naws_wgt['days'] as $naws_wgt_day ) : ?>
        <div class="naws-wgt-day">
          <span class="naws-wgt-dow"><?php echo esc_html( NAWS_Forecast::weekday_short( $naws_wgt_day['date'] ) ); ?></span>
          <?php if ( $naws_wgt_day['state'] !== '' ) : ?>
            <?php echo NAWS_Weather_Icons::render_inline( $naws_wgt_day['state'], 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- compile-time constant SVG ?>
          <?php endif; ?>
          <span class="naws-wgt-t">
            <?php echo esc_html( null === $naws_wgt_day['max'] ? '–' : round( $naws_wgt_day['max'] ) . '°' ); ?><br>
            <span class="naws-wgt-lo"><?php echo esc_html( null === $naws_wgt_day['min'] ? '–' : round( $naws_wgt_day['min'] ) . '°' ); ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( $naws_wgt_place !== '' || $naws_wgt_time !== '' ) : ?>
    <div class="naws-wgt-foot">
      <span><?php echo esc_html( $naws_wgt_place ); ?></span>
      <span><?php echo esc_html( $naws_wgt_time ); ?></span>
    </div>
  <?php endif; ?>

</div>
