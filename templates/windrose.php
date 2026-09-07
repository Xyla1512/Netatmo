<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_windrose period="90d" measure="wind" sectors="16" from="" to="" show="legend,summary" switcher="yes" size="" title=""]
 *
 * Where the wind comes from, how often, and how hard: one ray per compass
 * sector, its length the share of readings from there, stacked from the
 * centre outwards by Beaufort class. Calm has no direction and sits in the
 * hub as a percentage. Rendered on the server as inline SVG; every period
 * of the switcher is rendered too and hidden, so windrose-boot.js only
 * swaps panels and never fetches. Without the script the page shows the
 * period the shortcode asked for, complete.
 *
 * Geometry (viewBox 660 × 660): centre (330,330), hub radius 40, longest
 * ray 248; a share s draws to r = 40 + s / ring · 208 where ring is the
 * scale's top (the longest ray rounded up to 5 %). Sector i spans i·w ± w/2
 * with w = 360 / sectors, 0° north, clockwise. Direction codes sit at
 * r = 278, the shares of the three commonest directions at r = 300, the
 * ring labels at 107° where a mid-latitude rose is usually quiet.
 *
 * Expected variables:
 * @var array      $atts       Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_roses Roses keyed "measure|period", or one under '*' for every
 *                             panel; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.12
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Attributes: cast and whitelist first; nothing raw survives this block ──
$measure  = in_array( $atts['measure'] ?? '', [ 'wind', 'gust', 'both' ], true ) ? (string) $atts['measure'] : 'wind';
$sectors  = absint( $atts['sectors'] ?? 16 );
$sectors  = in_array( $sectors, NAWS_Windrose::SECTORS, true ) ? $sectors : 16;
$show     = array_values( array_intersect( [ 'legend', 'summary', 'table' ], array_map( 'trim', explode( ',', strtolower( (string) ( $atts['show'] ?? '' ) ) ) ) ) );
$size     = absint( $atts['size'] ?? 0 );
$size     = ( $size >= 200 && $size <= 1200 ) ? $size : 0;
$title    = sanitize_text_field( (string) ( $atts['title'] ?? '' ) );
$range    = NAWS_Windrose::range( $atts );
$switch   = ( strtolower( (string) ( $atts['switcher'] ?? 'yes' ) ) === 'no' ) ? [] : NAWS_Windrose::switch_keys( $atts );
$keys     = $switch ? $switch : [ $range['key'] ];
$measures = $measure === 'both' ? [ 'wind', 'gust' ] : [ $measure ];

$ranges = [];
foreach ( $keys as $k ) {
    $ranges[ $k ] = ( $k === $range['key'] ) ? $range : NAWS_Windrose::period_range( $k );
}
$roses = [];
foreach ( $measures as $m ) {
    foreach ( $keys as $k ) {
        $roses[ "$m|$k" ] = $naws_roses[ "$m|$k" ] ?? $naws_roses['*'] ?? NAWS_Windrose::rose( $m, $ranges[ $k ], $sectors );
    }
}

$unit     = NAWS_Windrose::unit();
$date_fmt = get_option( 'date_format', 'j. F Y' );
$classes  = count( NAWS_Windrose::BINS );
$w        = 360 / $sectors;
$C        = 330.0;
$R0       = 40.0;
$RMAX     = 248.0;
$f        = static fn( float $v ): string => number_format( $v, 2, '.', '' );
$pct      = static fn( float $share ): string => number_format_i18n( $share * 100, 1 ) . ' %';

/** One sector's line for the <title>: "NNE · North-northeast: 47.9 %, 3,380 readings, Mean 3.0 km/h, Max 22 km/h". */
$naws_wr_tip = static function ( int $i, array $s ) use ( $sectors, $unit, $pct ): string {
    $line = NAWS_Windrose::compass( $i, $sectors ) . ' · ' . NAWS_Windrose::compass_long( $i, $sectors ) . ': ' . $pct( (float) $s['share'] ) . ', ' . sprintf( naws_label( 'wr_readings' ), number_format_i18n( $s['n'] ) );
    if ( $s['n'] > 0 ) {
        $line .= ', ' . naws_label( 'wr_mean' ) . ' ' . NAWS_Windrose::speed( (float) $s['mean'], 1 ) . ' ' . $unit
               . ', ' . __( 'Max', 'xtx-integration-for-netatmo' ) . ' ' . NAWS_Windrose::speed( (float) $s['max'] ) . ' ' . $unit;
    }
    return $line;
};
?>
<div class="naws-wrap naws-wr" data-naws-windrose<?php if ( $size ) : ?> style="max-width:<?php echo esc_attr( (string) $size ); ?>px"<?php endif; ?>>
<?php if ( $title !== '' ) : ?>
  <h3 class="naws-wr-title"><?php echo esc_html( $title ); ?></h3>
<?php endif; ?>
<?php if ( $switch || count( $measures ) > 1 ) : ?>
  <div class="naws-wr-switch" hidden>
<?php if ( $switch ) : ?>
    <div class="naws-wr-group" role="group" aria-label="<?php echo esc_attr( naws_label( 'wr_switch_period' ) ); ?>">
<?php foreach ( $keys as $k ) : $on = ( $k === $range['key'] ); ?>
      <button type="button" class="naws-leg-pill naws-wr-btn<?php if ( $on ) : ?> is-active<?php endif; ?>" data-period="<?php echo esc_attr( $k ); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html( NAWS_Windrose::button_label( $k ) ); ?></button>
<?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ( count( $measures ) > 1 ) : ?>
    <div class="naws-wr-group" role="group" aria-label="<?php echo esc_attr( naws_label( 'wr_switch_measure' ) ); ?>">
<?php foreach ( $measures as $m ) : $on = ( $m === $measures[0] ); ?>
      <button type="button" class="naws-leg-pill naws-wr-btn<?php if ( $on ) : ?> is-active<?php endif; ?>" data-measure="<?php echo esc_attr( $m ); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html( $m === 'gust' ? __( 'Gusts', 'xtx-integration-for-netatmo' ) : __( 'Wind', 'xtx-integration-for-netatmo' ) ); ?></button>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
<?php endif; ?>
<?php foreach ( $measures as $m ) : foreach ( $keys as $k ) :
    $rose   = $roses[ "$m|$k" ];
    $r      = $ranges[ $k ];
    $active = ( $m === $measures[0] && $k === $range['key'] );
    $n      = (int) $rose['n'];
    $period = NAWS_Windrose::period_label( $r );
    $main   = $rose['top'][0] ?? null;
    $second = $rose['top'][1] ?? null;
    $ring   = (float) $rose['ring'];
    $rad    = static fn( float $share ): float => $R0 + ( $ring > 0 ? $share / $ring : 0.0 ) * ( $RMAX - $R0 );
    $meta   = NAWS_Windrose::meta_label( $m ) . ' · ' . $period . ' · ' . sprintf( naws_label( 'wr_readings' ), number_format_i18n( $n ) );
    $aria   = $main === null ? '' : sprintf( naws_label( 'wr_aria' ), $period, number_format_i18n( $n ), NAWS_Windrose::compass_long( $main, $sectors ), $pct( (float) $rose['sectors'][ $main ]['share'] ) );
?>
  <div class="naws-wr-panel" data-period="<?php echo esc_attr( $k ); ?>" data-measure="<?php echo esc_attr( $m ); ?>"<?php if ( ! $active ) : ?> hidden<?php endif; ?>>
    <p class="naws-wr-meta"><?php echo esc_html( $meta ); ?></p>
<?php if ( $n === 0 || $main === null ) : ?>
    <p class="naws-wr-empty"><?php echo esc_html( naws_label( 'wr_empty' ) ); ?></p>
<?php else : ?>
    <figure class="naws-wr-figure">
      <svg class="naws-wr-svg" viewBox="0 0 660 660" role="img" aria-label="<?php echo esc_attr( $aria ); ?>">
<?php for ( $ri = 1; $ri * 0.05 <= $ring + 1e-9; $ri++ ) :
        $rr = $rad( $ri * 0.05 );
        [ $lx, $ly ] = NAWS_Windrose::point( 107.0, $rr, $C ); ?>
        <circle class="naws-wr-ring" cx="330" cy="330" r="<?php echo esc_attr( $f( $rr ) ); ?>"/>
        <text class="naws-wr-ringlbl" x="<?php echo esc_attr( $f( $lx + 4 ) ); ?>" y="<?php echo esc_attr( $f( $ly + 4 ) ); ?>" aria-hidden="true"><?php echo esc_html( ( $ri * 5 ) . ' %' ); ?></text>
<?php endfor; ?>
<?php foreach ( $rose['sectors'] as $i => $s ) :
        $a0 = $i * $w - $w / 2;
        $a1 = $i * $w + $w / 2;
        $cum = 0; ?>
        <g class="naws-wr-sector" data-i="<?php echo esc_attr( (string) $i ); ?>">
          <title><?php echo esc_html( $naws_wr_tip( $i, $s ) ); ?></title>
<?php   foreach ( $s['bins'] as $b => $cnt ) :
            if ( $cnt <= 0 ) { continue; }
            $r0   = $rad( $cum / $n );
            $cum += $cnt;
            $r1   = $rad( $cum / $n ); ?>
          <path class="naws-wr-seg naws-wr-b<?php echo esc_attr( (string) ( $b + 1 ) ); ?>" d="<?php echo esc_attr( NAWS_Windrose::arc( $a0, $a1, $r0, $r1, $C ) ); ?>"/>
<?php   endforeach; ?>
          <path class="naws-wr-hit" d="<?php echo esc_attr( NAWS_Windrose::arc( $a0, $a1, $R0, $RMAX, $C ) ); ?>"/>
        </g>
<?php endforeach; ?>
<?php for ( $i = 0; $i < $sectors; $i++ ) :
        $step = (int) ( $sectors / 4 );
        $cls  = ( $i % $step === 0 ) ? 'naws-wr-dir naws-wr-dir--p' : ( ( $sectors === 8 || $i % 2 === 0 ) ? 'naws-wr-dir' : 'naws-wr-dir naws-wr-dir--i' );
        [ $dx, $dy ] = NAWS_Windrose::point( $i * $w, 278.0, $C ); ?>
        <text class="<?php echo esc_attr( $cls ); ?>" x="<?php echo esc_attr( $f( $dx ) ); ?>" y="<?php echo esc_attr( $f( $dy ) ); ?>" aria-hidden="true"><?php echo esc_html( NAWS_Windrose::compass( $i, $sectors ) ); ?></text>
<?php endfor; ?>
<?php foreach ( $rose['top'] as $i ) :
        [ $sx, $sy ] = NAWS_Windrose::point( $i * $w, 300.0, $C );
        $sn     = sin( deg2rad( $i * $w ) );
        $anchor = $sn > 0.3 ? 'start' : ( $sn < -0.3 ? 'end' : 'middle' ); ?>
        <text class="naws-wr-share" text-anchor="<?php echo esc_attr( $anchor ); ?>" x="<?php echo esc_attr( $f( $sx ) ); ?>" y="<?php echo esc_attr( $f( $sy ) ); ?>" aria-hidden="true"><?php echo esc_html( $pct( (float) $rose['sectors'][ $i ]['share'] ) ); ?></text>
<?php endforeach; ?>
        <circle class="naws-wr-hub" cx="330" cy="330" r="37"/>
        <text class="naws-wr-hublbl" x="330" y="325" aria-hidden="true"><?php echo esc_html( naws_label( 'wr_calm' ) ); ?></text>
        <text class="naws-wr-hubval" x="330" y="342" aria-hidden="true"><?php echo esc_html( $pct( $rose['calm'] / $n ) ); ?></text>
      </svg>
<?php if ( (int) $rose['first'] - (int) $r['from'] > 86400 ) : ?>
      <figcaption class="naws-wr-caption"><?php echo esc_html( sprintf( naws_label( 'wr_from' ), wp_date( $date_fmt, (int) $rose['first'] ) ) ); ?></figcaption>
<?php endif; ?>
    </figure>
<?php if ( in_array( 'summary', $show, true ) ) : ?>
    <dl class="naws-wr-summary">
      <div><dt><?php echo esc_html( naws_label( 'wr_main' ) ); ?></dt><dd><?php echo esc_html( NAWS_Windrose::compass( $main, $sectors ) ); ?> <small><?php echo esc_html( NAWS_Windrose::compass_long( $main, $sectors ) . ' · ' . $pct( (float) $rose['sectors'][ $main ]['share'] ) ); ?></small></dd></div>
<?php if ( $second !== null ) : ?>
      <div><dt><?php echo esc_html( naws_label( 'wr_second' ) ); ?></dt><dd><?php echo esc_html( NAWS_Windrose::compass( $second, $sectors ) ); ?> <small><?php echo esc_html( $pct( (float) $rose['sectors'][ $second ]['share'] ) ); ?></small></dd></div>
<?php endif; ?>
      <div><dt><?php echo esc_html( naws_label( 'wr_mean' ) ); ?></dt><dd><?php echo esc_html( NAWS_Windrose::speed( (float) $rose['mean'], 1 ) . ' ' . $unit ); ?></dd></div>
      <div><dt><?php echo esc_html( naws_label( $m === 'gust' ? 'wr_peak_gust' : 'wr_peak_wind' ) ); ?></dt><dd><?php echo esc_html( NAWS_Windrose::speed( (float) $rose['max'] ) . ' ' . $unit ); ?><?php if ( (int) $rose['max_at'] > 0 ) : ?> <small><?php echo esc_html( sprintf( naws_label( 'wr_on' ), wp_date( $date_fmt, (int) $rose['max_at'] ) ) ); ?></small><?php endif; ?></dd></div>
      <div><dt><?php echo esc_html( naws_label( 'wr_calm' ) ); ?></dt><dd><?php echo esc_html( $pct( $rose['calm'] / $n ) ); ?> <small><?php echo esc_html( sprintf( naws_label( 'wr_calm_note' ), NAWS_Windrose::speed( (float) NAWS_Windrose::BINS[0] ) . ' ' . $unit ) ); ?></small></dd></div>
      <div><dt><?php echo esc_html( naws_label( 'wr_readings_label' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( $n ) ); ?></dd></div>
    </dl>
<?php endif; ?>
<?php if ( in_array( 'legend', $show, true ) ) : ?>
    <ul class="naws-wr-legend" aria-label="<?php echo esc_attr( naws_label( 'wr_legend' ) ); ?>">
<?php for ( $b = 0; $b < $classes; $b++ ) :
        $tot = 0;
        foreach ( $rose['sectors'] as $s ) { $tot += (int) $s['bins'][ $b ]; } ?>
      <li<?php if ( $tot === 0 ) : ?> class="is-none"<?php endif; ?>><i class="naws-wr-sw naws-wr-b<?php echo esc_attr( (string) ( $b + 1 ) ); ?>"></i><span><?php echo esc_html( NAWS_Windrose::bin_label( $b ) ); ?></span><em><?php echo esc_html( $tot > 0 ? $pct( $tot / $n ) : naws_label( 'wr_none' ) ); ?></em></li>
<?php endfor; ?>
    </ul>
<?php endif; ?>
    <table class="naws-wr-table<?php if ( ! in_array( 'table', $show, true ) ) : ?> naws-wr-sr<?php endif; ?>">
      <caption><?php echo esc_html( $meta ); ?></caption>
      <thead><tr><th scope="col"><?php echo esc_html( naws_label( 'wr_col_dir' ) ); ?></th><th scope="col"><?php echo esc_html( naws_label( 'wr_col_share' ) ); ?></th><th scope="col"><?php echo esc_html( naws_label( 'wr_readings_label' ) ); ?></th><th scope="col"><?php echo esc_html( naws_label( 'wr_mean' ) . ' (' . $unit . ')' ); ?></th><th scope="col"><?php echo esc_html( __( 'Max', 'xtx-integration-for-netatmo' ) . ' (' . $unit . ')' ); ?></th><?php for ( $b = 0; $b < $classes; $b++ ) : ?><th scope="col"><?php echo esc_html( sprintf( naws_label( 'wr_bft' ), $b + 1 ) . ( $b === $classes - 1 ? '+' : '' ) ); ?></th><?php endfor; ?></tr></thead>
      <tbody>
<?php foreach ( $rose['sectors'] as $i => $s ) : ?>
        <tr><th scope="row"><?php echo esc_html( NAWS_Windrose::compass( $i, $sectors ) ); ?> <small><?php echo esc_html( NAWS_Windrose::compass_long( $i, $sectors ) ); ?></small></th><td><?php echo esc_html( $pct( (float) $s['share'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $s['n'] ) ); ?></td><td><?php echo esc_html( $s['n'] > 0 ? NAWS_Windrose::speed( (float) $s['mean'], 1 ) : '–' ); ?></td><td><?php echo esc_html( $s['n'] > 0 ? NAWS_Windrose::speed( (float) $s['max'] ) : '–' ); ?></td><?php foreach ( $s['bins'] as $cnt ) : ?><td><?php echo esc_html( $cnt > 0 ? number_format_i18n( $cnt ) : '·' ); ?></td><?php endforeach; ?></tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </div>
<?php endforeach; endforeach; ?>
</div>
