<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: one sparkline tile, rendered by NAWS_Sparkline::tile_markup().
 *
 * The card from the demo: the name in small capitals, the figure large
 * with its unit small, one line under it (low and high, or the wettest
 * window), and the curve across the card. Colours come from the Base
 * Theme variables (card) and the Sparkline tab (curve); nothing here
 * carries a colour.
 *
 * Expected variables:
 * @var array $naws_slt name, value, unit, sub ('' for none), curve (SVG
 *                      markup from NAWS_Sparkline::markup(); every value in
 *                      it is escaped in templates/sparkline.php)
 *
 * @package NAWS
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="naws-sl-card naws-sl-tile">
<div class="naws-sl-tile-name"><?php echo esc_html( $naws_slt['name'] ); ?></div>
<div class="naws-sl-tile-val"><?php echo esc_html( $naws_slt['value'] ); ?><small><?php echo esc_html( $naws_slt['unit'] ); ?></small></div>
<?php if ( $naws_slt['sub'] !== '' ) : ?>
<div class="naws-sl-tile-sub"><?php echo esc_html( $naws_slt['sub'] ); ?></div>
<?php endif; ?>
<div class="naws-sl-tile-plot"><?php echo $naws_slt['curve']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from NAWS_Sparkline::markup(), every value escaped in templates/sparkline.php ?></div>
</div>
