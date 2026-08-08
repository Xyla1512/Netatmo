<?php
/**
 * The twelve weather icons as literal markup.
 *
 * Why a template and not a PHP string constant: naws_svg_kses_args()
 * allows neither <defs>, <linearGradient>, <filter>, <rect>, <ellipse> nor
 * the class/style/transform/role/aria-label attributes, so running these
 * icons through naws_kses_svg() would leave a torso of each one. Widening
 * that allowlist is the worse fix, because wp_kses() pushes style through
 * safecss_filter_attr(), which strips CSS custom properties — exactly the
 * --d delays that stagger the drops and flakes.
 *
 * The icons are compile-time constants with no user input, so they are
 * emitted as literal markup instead. Nothing is interpolated here except
 * the aria-label, which is escaped. See section 9 of the design spec.
 *
 * SVG transform vs CSS transform: the two are mutually exclusive and CSS
 * wins. Anything positioned with transform="translate(...)" therefore has
 * its animation applied to an INNER group (see the snow icon), otherwise
 * the element snaps to the coordinate origin the moment the animation
 * starts. Elements rotating about themselves additionally need
 * transform-box: fill-box, which the stylesheet sets.
 *
 * Expected variables:
 * @var string $naws_wx_state  One of NAWS_Weather_State::STATES.
 * @var int    $naws_wx_size   Edge length in px (>= 64).
 * @var string $naws_wx_label  Translated aria-label.
 *
 * @package NAWS
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="naws-weather-icon" style="--naws-wx-size:<?php echo absint( $naws_wx_size ); ?>px">
<?php switch ( $naws_wx_state ) :

	case 'clear_day': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="rays" stroke="url(#naws-g-sun)" stroke-width="3.4" stroke-linecap="round">
				<line x1="32" y1="6"  x2="32" y2="13"/><line x1="32" y1="51" x2="32" y2="58"/>
				<line x1="6"  y1="32" x2="13" y2="32"/><line x1="51" y1="32" x2="58" y2="32"/>
				<line x1="13.6" y1="13.6" x2="18.6" y2="18.6"/><line x1="45.4" y1="45.4" x2="50.4" y2="50.4"/>
				<line x1="50.4" y1="13.6" x2="45.4" y2="18.6"/><line x1="18.6" y1="45.4" x2="13.6" y2="50.4"/>
			</g>
			<circle class="core" cx="32" cy="32" r="13" fill="url(#naws-g-sun)"/>
		</svg>
	<?php break;

	case 'clear_night': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<path class="core" d="M44 12 A22 22 0 1 0 44 52 A17 17 0 1 1 44 12 Z" fill="url(#naws-g-moon)"/>
			<circle class="star" style="--d:0s"   cx="15" cy="17" r="2"   fill="#FFF3C9"/>
			<circle class="star" style="--d:1.2s" cx="52" cy="20" r="1.6" fill="#FFF3C9"/>
			<circle class="star" style="--d:2.1s" cx="19" cy="47" r="1.4" fill="#FFF3C9"/>
		</svg>
	<?php break;

	case 'fair': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="rays" style="--o:22px 22px" stroke="url(#naws-g-sun)" stroke-width="3" stroke-linecap="round">
				<line x1="22" y1="4"  x2="22" y2="10"/><line x1="4"  y1="22" x2="10" y2="22"/>
				<line x1="9.5" y1="9.5" x2="13.5" y2="13.5"/><line x1="34.5" y1="9.5" x2="30.5" y2="13.5"/>
			</g>
			<circle class="core" style="--o:22px 22px" cx="22" cy="22" r="9.5" fill="url(#naws-g-sun)"/>
			<g class="cloud" fill="url(#naws-g-cloud)">
				<circle cx="28" cy="40" r="10"/><circle cx="40" cy="37" r="12"/>
				<circle cx="47" cy="43" r="7.5"/><rect x="21" y="43" width="30" height="9" rx="4.5"/>
			</g>
		</svg>
	<?php break;

	case 'partly': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<circle class="core" style="--o:21px 21px" cx="21" cy="21" r="9" fill="url(#naws-g-sun)"/>
			<g class="cloud" fill="url(#naws-g-cloud2)">
				<circle cx="25" cy="38" r="11"/><circle cx="38" cy="34" r="13"/>
				<circle cx="47" cy="41" r="8"/><rect x="17" y="41" width="34" height="10" rx="5"/>
			</g>
		</svg>
	<?php break;

	case 'overcast': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" style="animation-duration:9s" fill="url(#naws-g-cloud2)" opacity=".72">
				<circle cx="20" cy="26" r="9"/><circle cx="32" cy="23" r="11"/>
				<circle cx="41" cy="28" r="7"/><rect x="13" y="28" width="30" height="8" rx="4"/>
			</g>
			<g class="cloud" fill="url(#naws-g-cloud)">
				<circle cx="25" cy="40" r="11"/><circle cx="38" cy="36" r="13"/>
				<circle cx="47" cy="43" r="8"/><rect x="17" y="43" width="34" height="10" rx="5"/>
			</g>
		</svg>
	<?php break;

	case 'fog': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" fill="url(#naws-g-cloud)" opacity=".9">
				<circle cx="24" cy="28" r="10"/><circle cx="37" cy="25" r="12"/>
				<circle cx="45" cy="31" r="7.5"/><rect x="17" y="31" width="32" height="9" rx="4.5"/>
			</g>
			<g stroke="#B3C2D2" stroke-width="3.6" stroke-linecap="round">
				<line class="band" style="--d:0s"   x1="15" y1="45" x2="45" y2="45"/>
				<line class="band" style="--d:.9s"  x1="20" y1="52" x2="50" y2="52"/>
				<line class="band" style="--d:1.8s" x1="16" y1="58" x2="40" y2="58"/>
			</g>
		</svg>
	<?php break;

	case 'rain': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" fill="url(#naws-g-cloud2)">
				<circle cx="24" cy="27" r="10"/><circle cx="37" cy="24" r="12"/>
				<circle cx="45" cy="30" r="7.5"/><rect x="17" y="30" width="32" height="9" rx="4.5"/>
			</g>
			<g fill="url(#naws-g-rain)">
				<ellipse class="drop" style="--d:0s"   cx="23" cy="47" rx="2.1" ry="3.4"/>
				<ellipse class="drop" style="--d:.42s" cx="32" cy="47" rx="2.1" ry="3.4"/>
				<ellipse class="drop" style="--d:.84s" cx="41" cy="47" rx="2.1" ry="3.4"/>
			</g>
		</svg>
	<?php break;

	case 'rain_heavy': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" fill="url(#naws-g-cloud-dark)">
				<circle cx="24" cy="27" r="10"/><circle cx="37" cy="24" r="12"/>
				<circle cx="45" cy="30" r="7.5"/><rect x="17" y="30" width="32" height="9" rx="4.5"/>
			</g>
			<g fill="url(#naws-g-rain)">
				<ellipse class="drop-f" style="--d:0s"   cx="20" cy="46" rx="2" ry="3.6"/>
				<ellipse class="drop-f" style="--d:.17s" cx="27" cy="46" rx="2" ry="3.6"/>
				<ellipse class="drop-f" style="--d:.34s" cx="34" cy="46" rx="2" ry="3.6"/>
				<ellipse class="drop-f" style="--d:.51s" cx="41" cy="46" rx="2" ry="3.6"/>
				<ellipse class="drop-f" style="--d:.68s" cx="47" cy="46" rx="2" ry="3.6"/>
			</g>
		</svg>
	<?php break;

	case 'snow': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" fill="url(#naws-g-cloud)">
				<circle cx="24" cy="27" r="10"/><circle cx="37" cy="24" r="12"/>
				<circle cx="45" cy="30" r="7.5"/><rect x="17" y="30" width="32" height="9" rx="4.5"/>
			</g>
			<g stroke="#F2F9FF" stroke-width="2" stroke-linecap="round">
				<g transform="translate(23,44)"><g class="flake" style="--d:0s" filter="url(#naws-f-snow)">
					<line x1="-3.6" y1="0" x2="3.6" y2="0"/><line x1="-1.8" y1="-3.1" x2="1.8" y2="3.1"/><line x1="1.8" y1="-3.1" x2="-1.8" y2="3.1"/>
				</g></g>
				<g transform="translate(33,44)"><g class="flake" style="--d:1.05s" filter="url(#naws-f-snow)">
					<line x1="-3.6" y1="0" x2="3.6" y2="0"/><line x1="-1.8" y1="-3.1" x2="1.8" y2="3.1"/><line x1="1.8" y1="-3.1" x2="-1.8" y2="3.1"/>
				</g></g>
				<g transform="translate(43,44)"><g class="flake" style="--d:2.1s" filter="url(#naws-f-snow)">
					<line x1="-3.6" y1="0" x2="3.6" y2="0"/><line x1="-1.8" y1="-3.1" x2="1.8" y2="3.1"/><line x1="1.8" y1="-3.1" x2="-1.8" y2="3.1"/>
				</g></g>
			</g>
		</svg>
	<?php break;

	case 'sleet': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" fill="url(#naws-g-cloud-dark)">
				<circle cx="24" cy="27" r="10"/><circle cx="37" cy="24" r="12"/>
				<circle cx="45" cy="30" r="7.5"/><rect x="17" y="30" width="32" height="9" rx="4.5"/>
			</g>
			<g fill="url(#naws-g-hail)">
				<circle class="pellet" style="--d:0s"   cx="23" cy="46" r="3"/>
				<circle class="pellet" style="--d:.45s" cx="32" cy="46" r="3.4"/>
				<circle class="pellet" style="--d:.9s"  cx="41" cy="46" r="3"/>
			</g>
		</svg>
	<?php break;

	case 'thunder': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g class="cloud" fill="url(#naws-g-cloud-dark)">
				<circle cx="24" cy="26" r="10"/><circle cx="37" cy="23" r="12"/>
				<circle cx="45" cy="29" r="7.5"/><rect x="17" y="29" width="32" height="9" rx="4.5"/>
			</g>
			<path class="bolt" d="M35 36 L26 50 L32 50 L29 60 L41 44 L34.5 44 L39 36 Z" fill="url(#naws-g-bolt)"/>
		</svg>
	<?php break;

	case 'storm': ?>
		<svg class="naws-wxi" viewBox="0 0 64 64" role="img" aria-label="<?php echo esc_attr( $naws_wx_label ); ?>">
			<g fill="none" stroke="#7E93AB" stroke-width="3.8" stroke-linecap="round">
				<path class="gust" style="--d:0s"  d="M8 22 H34 a6 6 0 1 0 -6 -6"/>
				<path class="gust" style="--d:.5s" d="M8 34 H44 a7 7 0 1 1 -7 7"/>
				<path class="gust" style="--d:1s"  d="M8 46 H28 a5 5 0 1 0 -5 -5"/>
			</g>
		</svg>
	<?php break;

endswitch; ?>
</div>
