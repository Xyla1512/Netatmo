<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_radar]
 *
 * @var array  $naws_rd        payload, see NAWS_Radar::payload()
 * @var string $naws_rd_state  ready | preparing | unavailable
 * @var string $naws_rd_width  CSS width
 * @var int    $naws_rd_height px
 * @var string $naws_rd_place  name of the location
 * @var bool   $naws_rd_asof   show "As of" (newest image older than NAWS_Radar::AS_OF)
 *
 * @package NAWS
 * @since   2.2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$naws_rd_frames = $naws_rd['frames'];
$naws_rd_now    = -1;
foreach ( $naws_rd_frames as $naws_rd_i => $naws_rd_f ) {
    if ( ! $naws_rd_f['forecast'] ) {
        $naws_rd_now = $naws_rd_i;
    }
}
$naws_rd_now   = $naws_rd_now < 0 ? count( $naws_rd_frames ) - 1 : $naws_rd_now;
$naws_rd_shown = $naws_rd_frames[ $naws_rd_now ] ?? null;
$naws_rd_clock = (string) ( $naws_rd_shown['label'] ?? '' );
$naws_rd_pct   = static fn( $v ) => sprintf( '%.4F%%', $v / 1024 * 100 );
$naws_rd_n     = count( $naws_rd_frames );
$naws_rd_aria  = '' === trim( $naws_rd_place ) ? naws_label( 'rd_aria_plain' ) : sprintf( naws_label( 'rd_aria' ), $naws_rd_place );

/**
 * A label with one %s, the %s replaced by a link to the source's own site.
 * These are links a visitor may follow, not resources the page loads.
 */
$naws_rd_credit = static function ( string $label, string $url, string $text ): void {
    $naws_rd_parts = array_pad( explode( '%s', $label, 2 ), 2, '' );
    echo esc_html( $naws_rd_parts[0] );
    echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
    echo esc_html( $naws_rd_parts[1] );
};
?>
<div class="naws-radar naws-radar--<?php echo esc_attr( $naws_rd_state ); ?>" data-naws-radar
     role="group" aria-label="<?php echo esc_attr( $naws_rd_aria ); ?>"
     style="width:<?php echo esc_attr( $naws_rd_width ); ?>;--naws-radar-h:<?php echo absint( $naws_rd_height ); ?>px">
    <div class="naws-radar-view">
        <div class="naws-radar-stage">
            <div class="naws-radar-map" aria-hidden="true">
                <?php foreach ( $naws_rd['map'] as $naws_rd_t ) : ?>
                <img src="<?php echo esc_url( $naws_rd_t[0] ); ?>" alt="" loading="lazy" decoding="async"
                     style="left:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[1] ) ); ?>;top:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[2] ) ); ?>;width:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[3] ) ); ?>">
                <?php endforeach; ?>
            </div>
            <div class="naws-radar-layer" aria-hidden="true">
                <?php if ( $naws_rd_shown ) : ?>
                <div class="naws-radar-frame is-on">
                    <?php if ( isset( $naws_rd_shown['img'] ) ) : ?>
                    <img src="<?php echo esc_url( $naws_rd_shown['img'] ); ?>" alt="" decoding="async" style="left:0;top:0;width:100%">
                    <?php endif; ?>
                    <?php foreach ( $naws_rd_shown['tiles'] ?? [] as $naws_rd_t ) : ?>
                    <img src="<?php echo esc_url( $naws_rd_t[0] ); ?>" alt="" decoding="async"
                         style="left:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[1] ) ); ?>;top:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[2] ) ); ?>;width:<?php echo esc_attr( $naws_rd_pct( $naws_rd_t[3] ) ); ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="naws-radar-station" aria-hidden="true"></span>
        </div>
        <div class="naws-radar-time"<?php echo '' === $naws_rd_clock ? ' hidden' : ''; ?>>
            <span class="naws-radar-clock"><?php echo esc_html( $naws_rd_clock ); ?></span>
            <span class="naws-radar-fc"<?php echo ( $naws_rd_shown['forecast'] ?? false ) ? '' : ' hidden'; ?>><?php echo esc_html( naws_label( 'rd_forecast' ) ); ?></span>
        </div>
        <?php if ( 'ready' !== $naws_rd_state ) : ?>
        <p class="naws-radar-msg" data-unavailable="<?php echo esc_attr( naws_label( 'rd_unavailable' ) ); ?>"><?php echo esc_html( naws_label( 'preparing' === $naws_rd_state ? 'rd_preparing' : 'rd_unavailable' ) ); ?></p>
        <?php else : ?>
        <p class="naws-radar-msg" data-unavailable="<?php echo esc_attr( naws_label( 'rd_unavailable' ) ); ?>" hidden></p>
        <?php endif; ?>
    </div>
    <div class="naws-radar-bar">
        <button type="button" class="naws-radar-btn naws-radar-play" aria-pressed="false"
                aria-label="<?php echo esc_attr( naws_label( 'rd_play' ) ); ?>"<?php disabled( count( $naws_rd_frames ) < 2 ); ?>>
            <span class="naws-radar-icon" aria-hidden="true"></span>
        </button>
        <span class="naws-radar-track">
            <input type="range" class="naws-radar-range" min="0" max="<?php echo absint( max( 0, $naws_rd_n - 1 ) ); ?>"
                   value="<?php echo absint( max( 0, $naws_rd_now ) ); ?>" step="1"
                   aria-label="<?php echo esc_attr( naws_label( 'rd_time' ) ); ?>"<?php disabled( $naws_rd_n < 2 ); ?>>
            <span class="naws-radar-now" aria-hidden="true"
                  style="left:<?php echo esc_attr( sprintf( '%.4F%%', $naws_rd_n > 1 ? max( 0, $naws_rd_now ) / ( $naws_rd_n - 1 ) * 100 : 0 ) ); ?>"<?php echo $naws_rd_n < 2 ? ' hidden' : ''; ?>></span>
        </span>
        <span class="naws-radar-legend" aria-hidden="true">
            <span><?php echo esc_html( naws_label( 'rd_light' ) ); ?></span><i></i><span><?php echo esc_html( naws_label( 'rd_heavy' ) ); ?></span>
        </span>
    </div>
    <p class="naws-radar-attr">
        <?php
        if ( 'dwd' === $naws_rd['source'] ) {
            $naws_rd_credit( naws_label( 'rd_src_dwd' ), 'https://www.dwd.de', 'Deutscher Wetterdienst' );
        } else {
            $naws_rd_credit( 'Weather data by %s', 'https://www.rainviewer.com', 'RainViewer' );
        }
        ?>
        · <?php $naws_rd_credit( naws_label( 'rd_src_osm' ), 'https://www.openstreetmap.org/copyright', 'OpenStreetMap' ); ?>
        <span class="naws-radar-asof" data-format="<?php echo esc_attr( naws_label( 'rd_as_of' ) ); ?>"<?php echo $naws_rd_asof ? '' : ' hidden'; ?>><?php echo esc_html( $naws_rd['updatedLabel'] ? sprintf( naws_label( 'rd_as_of' ), $naws_rd['updatedLabel'] ) : '' ); ?></span>
    </p>
    <?php
    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- type=application/json is not executable; pure data container
    echo '<script type="application/json" data-naws="radar">' . wp_json_encode( $naws_rd ) . '</script>';
    ?>
</div>
