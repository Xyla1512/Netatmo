<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: the month block, rendered by NAWS_Sparkline::month_markup().
 *
 * As in the demo: a row for the temperature (the daily mean of the last
 * day large, the band from low to high with the mean line), a row for
 * the rain per day, and the first and last day under the curves. The
 * rain row is left out when the station has no rain gauge.
 *
 * Expected variables:
 * @var array $naws_slm mean_value, mean_label, rain_value, rain_label,
 *                      axis_from, axis_to (strings), band and rain
 *                      (markup from NAWS_Sparkline::markup(), rain '' for none)
 *
 * @package NAWS
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="naws-sl-card naws-sl-month">
<div class="naws-sl-month-row naws-sl-month-row--band">
<div class="naws-sl-month-k"><b><?php echo esc_html( $naws_slm['mean_value'] ); ?></b><?php echo esc_html( $naws_slm['mean_label'] ); ?></div>
<div class="naws-sl-month-plot"><?php echo $naws_slm['band']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
</div>
<?php if ( $naws_slm['rain'] !== '' ) : ?>
<div class="naws-sl-month-row">
<div class="naws-sl-month-k"><b><?php echo esc_html( $naws_slm['rain_value'] ); ?></b><?php echo esc_html( $naws_slm['rain_label'] ); ?></div>
<div class="naws-sl-month-plot"><?php echo $naws_slm['rain']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
</div>
<?php endif; ?>
<div class="naws-sl-month-axis"><div></div><div><span><?php echo esc_html( $naws_slm['axis_from'] ); ?></span><span><?php echo esc_html( $naws_slm['axis_to'] ); ?></span></div></div>
</div>
