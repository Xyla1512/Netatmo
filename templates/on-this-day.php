<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_on_this_day date="" title=""]
 *
 * This calendar day in every earlier year: low, high, mean and rain, with
 * the day's record marked in each column. The running year is left out —
 * its row for today is written at the end of the day.
 *
 * Expected variables:
 * @var array      $atts      Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_rows Daily rows; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.11
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The day: MM-DD or YYYY-MM-DD; anything else is today. The year of the
// attribute is only used to know which years count as "earlier".
$raw   = trim( (string) ( $atts['date'] ?? '' ) );
$today = wp_date( 'Y-m-d' );
if ( preg_match( '/^(\d{4})-(\d{2}-\d{2})$/', $raw, $m ) && checkdate( (int) substr( $m[2], 0, 2 ), (int) substr( $m[2], 3, 2 ), (int) $m[1] ) ) {
    $before    = (int) $m[1];
    $month_day = $m[2];
} elseif ( preg_match( '/^(\d{2})-(\d{2})$/', $raw, $m ) && checkdate( (int) $m[1], (int) $m[2], 2024 ) ) {
    $before    = (int) substr( $today, 0, 4 );
    $month_day = $raw;
} else {
    $before    = (int) substr( $today, 0, 4 );
    $month_day = substr( $today, 5 );
}

$rows = $naws_rows ?? NAWS_Records::rows( $atts );
$hits = NAWS_Records::on_this_day( $rows, $month_day, $before );
if ( empty( $hits ) ) {
    return;
}

$title = (string) ( $atts['title'] ?? '' );
$temp  = static fn( $v ) => $v === null ? '–' : number_format_i18n( (float) NAWS_Helpers::format_value( 'Temperature', $v ), 1 );
$rain  = static fn( $v ) => $v === null ? '–' : number_format_i18n( (float) NAWS_Helpers::format_value( 'Rain', $v ), 1 );
// The four value cells, in order: text and whether it is the day's record.
$cells = static fn( array $hit ): array => [
    [ $temp( $hit['temp_min'] ), $hit['record']['temp_min'] ],
    [ $temp( $hit['temp_max'] ), $hit['record']['temp_max'] ],
    [ $temp( $hit['temp_avg'] ), false ],
    [ $rain( $hit['rain_sum'] ), $hit['record']['rain_sum'] ],
];
?>
<section class="naws-otd">
  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-otd-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>
  <table class="naws-otd-table">
    <thead>
      <tr>
        <th><?php echo esc_html( naws_label( 'otd_col_year' ) ); ?></th>
        <th><?php echo esc_html( naws_label( 'otd_col_min' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span></th>
        <th><?php echo esc_html( naws_label( 'otd_col_max' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span></th>
        <th><?php echo esc_html( naws_label( 'otd_col_avg' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Temperature' ) ); ?></span></th>
        <th><?php echo esc_html( naws_label( 'otd_col_rain' ) ); ?> <span class="naws-otd-unit"><?php echo esc_html( NAWS_Helpers::get_unit( 'Rain' ) ); ?></span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $hits as $hit ) : ?>
        <tr class="naws-otd-row">
          <td><?php echo esc_html( (string) $hit['year'] ); ?></td>
          <?php foreach ( $cells( $hit ) as [ $text, $is_record ] ) : ?>
            <?php if ( $is_record ) : ?>
              <td class="naws-otd-record" title="<?php echo esc_attr( naws_label( 'otd_record' ) ); ?>"><?php echo esc_html( $text ); ?></td>
            <?php else : ?>
              <td><?php echo esc_html( $text ); ?></td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
