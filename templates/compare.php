<?php
/**
 * [naws_compare]: two daily series on one time axis.
 *
 * The data travel as JSON inside the block (no request, works on cached
 * pages); compare-boot.js draws them with Chart.js. No module_id in here —
 * the series carry names, not addresses.
 *
 * Expects from NAWS_Compare::render(): $naws_cmp (array), $naws_cmp_id,
 * $naws_cmp_title, $naws_cmp_height.
 *
 * @package NAWS
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<figure class="naws-cmp" data-naws-cmp>
  <div class="naws-cmp-bar">
    <figcaption class="naws-cmp-title"><?php echo esc_html( $naws_cmp_title ); ?></figcaption>
    <button type="button" class="naws-cmp-expand" aria-label="<?php echo esc_attr( naws_label( 'cmp_expand' ) ); ?>">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
    </button>
  </div>
  <div class="naws-cmp-canvas" style="height:<?php echo (int) $naws_cmp_height; ?>px"><canvas id="<?php echo esc_attr( $naws_cmp_id ); ?>"></canvas></div>
  <script type="application/json" class="naws-cmp-data"><?php echo wp_json_encode( $naws_cmp, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & hex-escaped, read by compare-boot.js ?></script>
</figure>
