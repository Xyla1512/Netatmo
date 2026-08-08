<?php
/**
 * Shared gradient and filter definitions for the weather icons.
 *
 * Printed EXACTLY ONCE per page via wp_footer, guarded by a static flag in
 * NAWS_Weather_Icons. Every icon references these by id — url(#naws-g-cloud)
 * and so on — so a second copy would create duplicate ids in the document
 * whenever the shortcode and the dashboard icon appear on the same page.
 *
 * gradientUnits="userSpaceOnUse" is deliberate: it makes a cloud built from
 * several circles read as one body instead of each circle carrying its own
 * gradient.
 *
 * This is literal markup with no interpolation, so nothing here is escaped
 * and nothing passes through wp_kses(). See section 9 of the design spec.
 *
 * @package NAWS
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="naws-g-sun" gradientUnits="userSpaceOnUse" x1="20" y1="16" x2="46" y2="50">
      <stop offset="0" stop-color="#FFE066"/><stop offset="1" stop-color="#FF9A1C"/>
    </linearGradient>
    <linearGradient id="naws-g-moon" gradientUnits="userSpaceOnUse" x1="22" y1="12" x2="48" y2="52">
      <stop offset="0" stop-color="#FFF3C9"/><stop offset="1" stop-color="#F2C452"/>
    </linearGradient>
    <linearGradient id="naws-g-cloud" gradientUnits="userSpaceOnUse" x1="16" y1="18" x2="50" y2="48">
      <stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#BFCEDF"/>
    </linearGradient>
    <linearGradient id="naws-g-cloud2" gradientUnits="userSpaceOnUse" x1="14" y1="16" x2="52" y2="50">
      <stop offset="0" stop-color="#E9F0F8"/><stop offset="1" stop-color="#9DAFC3"/>
    </linearGradient>
    <linearGradient id="naws-g-cloud-dark" gradientUnits="userSpaceOnUse" x1="14" y1="16" x2="52" y2="50">
      <stop offset="0" stop-color="#8A99AC"/><stop offset="1" stop-color="#4E5C70"/>
    </linearGradient>
    <linearGradient id="naws-g-rain" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="64">
      <stop offset="0" stop-color="#6FC0FA"/><stop offset="1" stop-color="#1E6FCC"/>
    </linearGradient>
    <linearGradient id="naws-g-bolt" gradientUnits="userSpaceOnUse" x1="26" y1="24" x2="40" y2="50">
      <stop offset="0" stop-color="#FFD84A"/><stop offset="1" stop-color="#FF7A18"/>
    </linearGradient>
    <linearGradient id="naws-g-hail" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="64">
      <stop offset="0" stop-color="#F0F7FF"/><stop offset="1" stop-color="#A7BED5"/>
    </linearGradient>
    <!-- Soft rim so near-white flakes still read on a light background.
         The region is generous on purpose: a tight one clips the flake
         as it falls out of the filter area. -->
    <filter id="naws-f-snow" x="-40%" y="-40%" width="180%" height="180%">
      <feDropShadow dx="0" dy="0" stdDeviation="1" flood-color="#3B7FB4" flood-opacity=".95"/>
    </filter>
  </defs>
</svg>
