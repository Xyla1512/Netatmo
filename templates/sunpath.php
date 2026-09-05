<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_sunpath title=""]
 *
 * The sun on its arc over the station: horizon, a dashed semicircle, the
 * part already travelled drawn solid, and the sun where it stands at the
 * moment the page is built. At night it sits below the horizon on a
 * smaller arc. No script: the picture is right when it is made, and a
 * cached page shows the sun where it was when the cache was filled — the
 * shortcode's documentation says so.
 *
 * Geometry (viewBox 400 × 220): horizon y = 170 from x = 30 to 370, arc
 * centre (200,170), radius 140, so the zenith is at y = 30. Sun angle
 * θ = π·(1 − progress): x = 200 + 140·cos θ, y = 170 − 140·sin θ. Night arc:
 * radius 60 below the line, θ = π·night_progress, moving from the west
 * (right) to the east (left), where it will rise.
 *
 * Expected variables:
 * @var array      $atts        Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_coords [lat, lng]; set by the tests, null on a real page
 * @var int|null   $naws_now    The moment; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.11
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$coords = $naws_coords ?? NAWS_Astro::get_coords();
if ( ! $coords ) {
    return;
}

$now = $naws_now ?? time();
// Local noon of the site's calendar day, so "today" is the site's today.
$noon_dt = new DateTime( 'now', wp_timezone() );
$noon_dt->setTimestamp( $now );
$noon_dt->setTime( 12, 0, 0 );
$sun = NAWS_Astro::sun_path( (float) $coords['lat'], (float) $coords['lng'], $now, $noon_dt->getTimestamp() );
if ( $sun === null ) {
    return;
}

$title    = (string) ( $atts['title'] ?? '' );
$time_fmt = get_option( 'time_format', 'H:i' );
$clock    = static fn( int $ts ): string => wp_date( $time_fmt, $ts );
$hm       = static fn( int $s ): string => sprintf( '%d:%02d', intdiv( $s, 3600 ), intdiv( $s % 3600, 60 ) );

$rise = $clock( $sun['sunrise'] );
$set  = $clock( $sun['sunset'] );
$peak = $clock( $sun['transit'] );

$is_day = $sun['progress'] !== null;
if ( $is_day ) {
    $theta = M_PI * ( 1 - $sun['progress'] );
    $sx    = 200 + 140 * cos( $theta );
    $sy    = 170 - 140 * sin( $theta );
} else {
    $theta = M_PI * $sun['night_progress'];
    $sx    = 200 + 60 * cos( $theta );
    $sy    = 170 + 60 * sin( $theta );
}
$fmt = static fn( float $v ): string => number_format( $v, 1, '.', '' );

$delta_min = (int) round( abs( $sun['delta_day'] ) / 60 );
if ( abs( $sun['delta_day'] ) <= 30 ) {
    $delta_text = naws_label( 'sun_same' );
} else {
    $delta_text = sprintf( naws_label( $sun['delta_day'] < 0 ? 'sun_shorter' : 'sun_longer' ), sprintf( naws_label( 'sun_minutes' ), $delta_min ) );
}
$text = sprintf( naws_label( 'sun_day_length' ), $hm( $sun['day_length'] ) )
      . ' · ' . $delta_text
      . ' · ' . sprintf( naws_label( 'sun_extremes' ), $hm( $sun['longest'] ), $hm( $sun['shortest'] ) );
$aria = sprintf( naws_label( 'sun_aria' ), $rise, $set, $hm( $sun['day_length'] ) );
?>
<section class="naws-sun">
  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-sun-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>
  <svg class="naws-sun-svg" viewBox="0 0 400 220" role="img" aria-label="<?php echo esc_attr( $aria ); ?>">
    <line class="naws-sun-horizon" x1="30" y1="170" x2="370" y2="170"/>
    <path class="naws-sun-arc" d="M 60 170 A 140 140 0 0 1 340 170"/>
    <?php if ( $is_day ) : ?>
      <path class="naws-sun-done" d="M 60 170 A 140 140 0 0 1 <?php echo esc_attr( $fmt( $sx ) ); ?> <?php echo esc_attr( $fmt( $sy ) ); ?>"/>
      <circle class="naws-sun-disc" cx="<?php echo esc_attr( $fmt( $sx ) ); ?>" cy="<?php echo esc_attr( $fmt( $sy ) ); ?>" r="10"/>
    <?php else : ?>
      <path class="naws-sun-arc naws-sun-arc--night" d="M 260 170 A 60 60 0 0 1 140 170"/>
      <circle class="naws-sun-disc naws-sun-disc--night" cx="<?php echo esc_attr( $fmt( $sx ) ); ?>" cy="<?php echo esc_attr( $fmt( $sy ) ); ?>" r="8"/>
    <?php endif; ?>
    <text class="naws-sun-label" x="30" y="192"><?php echo esc_html( $rise ); ?></text>
    <text class="naws-sun-label" x="200" y="18" text-anchor="middle"><?php echo esc_html( $peak ); ?></text>
    <text class="naws-sun-label" x="370" y="192" text-anchor="end"><?php echo esc_html( $set ); ?></text>
  </svg>
  <p class="naws-sun-text"><?php echo esc_html( $text ); ?></p>
</section>
