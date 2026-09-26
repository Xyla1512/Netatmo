<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: one sparkline, rendered by NAWS_Sparkline::markup().
 *
 * Every coordinate went through NAWS_Sparkline::num() and is a plain
 * decimal; texts are escaped here. Colours come from classes and CSS
 * variables only, so nothing in the markup carries a colour — that is
 * what lets the Appearance page, the widget's schemes and the theme
 * restyle a curve without touching it. No ids: a page may carry any
 * number of sparklines.
 *
 * Dots (low, high, ring, end) are zero-length paths with round caps. A
 * <circle> would stretch into an ellipse under preserveAspectRatio="none";
 * a stroke with vector-effect keeps its size and its roundness.
 *
 * Expected variables:
 * @var array $naws_sl kind (line|bars), mod (line|bars|band), w, h, style,
 *                     geo (geometry() or bar_geometry()), minmax (bool),
 *                     hover ([ 'x' => float[], 't' => string[] ]), aria,
 *                     value ('' for none)
 *
 * @package NAWS
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$naws_sl_geo = $naws_sl['geo'];
$naws_sl_dot = static fn( array $xy ): string => 'M' . $xy[0] . ' ' . $xy[1] . ' h0';
?>
<span class="naws-sl naws-sl--<?php echo esc_attr( $naws_sl['mod'] ); ?>"<?php if ( $naws_sl['style'] !== '' ) : ?> style="<?php echo esc_attr( $naws_sl['style'] ); ?>"<?php endif; ?>><svg viewBox="0 0 <?php echo absint( $naws_sl['w'] ); ?> <?php echo absint( $naws_sl['h'] ); ?>" preserveAspectRatio="none" role="img" focusable="false" aria-label="<?php echo esc_attr( $naws_sl['aria'] ); ?>" data-naws-sl="<?php echo esc_attr( (string) wp_json_encode( $naws_sl['hover'] ) ); ?>">
<?php if ( $naws_sl['kind'] === 'bars' ) : ?>
<path class="naws-sl-base" d="M0 <?php echo esc_attr( $naws_sl_geo['base'] ); ?> H<?php echo absint( $naws_sl['w'] ); ?>" vector-effect="non-scaling-stroke"/>
<?php foreach ( $naws_sl_geo['rects'] as $naws_sl_r ) : ?>
<rect class="naws-sl-bar" x="<?php echo esc_attr( $naws_sl_r[0] ); ?>" y="<?php echo esc_attr( $naws_sl_r[1] ); ?>" width="<?php echo esc_attr( $naws_sl_r[2] ); ?>" height="<?php echo esc_attr( $naws_sl_r[3] ); ?>"/>
<?php endforeach; ?>
<?php else : ?>
<?php if ( $naws_sl_geo['band'] !== '' ) : ?>
<path class="naws-sl-band" d="<?php echo esc_attr( $naws_sl_geo['band'] ); ?>"/>
<?php else : ?>
<path class="naws-sl-area" d="<?php echo esc_attr( $naws_sl_geo['area'] ); ?>"/>
<?php endif; ?>
<path class="naws-sl-line" d="<?php echo esc_attr( $naws_sl_geo['line'] ); ?>" vector-effect="non-scaling-stroke"/>
<?php if ( $naws_sl['minmax'] ) : ?>
<path class="naws-sl-mm" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['lo'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<path class="naws-sl-mm" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['hi'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<?php endif; ?>
<path class="naws-sl-ring" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['end'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<path class="naws-sl-end" d="<?php echo esc_attr( $naws_sl_dot( $naws_sl_geo['end'] ) ); ?>" vector-effect="non-scaling-stroke"/>
<?php endif; ?>
</svg><?php if ( $naws_sl['value'] !== '' ) : ?><span class="naws-sl-val"><?php echo esc_html( $naws_sl['value'] ); ?></span><?php endif; ?></span>
