<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_month_review month="" years="5" title=""]
 *
 * One finished month as five tiles (mean, highest, lowest, rain, gust),
 * each against the same month of earlier years, a headline and up to three
 * sentences. Rendered on the server; the script only swaps the block for
 * another month. Everything is computed in NAWS_Month_Review::render().
 *
 * Expected variables:
 * @var array  $atts       [ 'years' => int, 'title' => string ]
 * @var string $ym         The month shown, 'YYYY-MM'
 * @var int    $years      Years of comparison, 0-10
 * @var string $title      Heading above the bar, may be ''
 * @var array  $cmp        NAWS_Month_Review::compare()
 * @var array  $headline   NAWS_Month_Review::headline()
 * @var array  $sentences  NAWS_Month_Review::sentences()
 * @var array  $neighbours NAWS_Month_Review::neighbours()
 * @var bool   $nav        Paging bar and script hook, or a plain heading
 *
 * @package NAWS
 * @since   2.2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Dates are anchored on noon: WordPress runs PHP in UTC, so a bare date is
// midnight UTC and wp_date() would show the day before on a site with a
// negative UTC offset (same pattern as templates/records.php).
$naws_mr_month = static fn( string $month_ym, string $fmt ): string => wp_date( $fmt, strtotime( $month_ym . '-15 12:00:00' ) );
$naws_mr_day   = static fn( string $ymd ): string => wp_date( naws_label( 'mr_date_format' ), strtotime( $ymd . ' 12:00:00' ) );

$naws_mr_name       = $naws_mr_month( $ym, 'F' );
$naws_mr_name_year  = $naws_mr_month( $ym, 'F Y' );
$naws_mr_catalogue  = NAWS_Records::catalogue();

/** value and unit as one string, e.g. "24.0 °C". */
$naws_mr_text = static function ( string $param, float $value, int $decimals ): string {
    $parts = NAWS_Records::display_value( $param, $value, $decimals );
    return trim( $parts['value'] . ' ' . $parts['unit'] );
};

// The five tiles: what to read, how to show it, which column carries it.
$naws_mr_tiles = [
    'mean' => [ 'param' => 'Temperature',  'field' => 'temp_avg', 'decimals' => (int) $naws_mr_catalogue['hottest_day']['decimals'] ],
    'max'  => [ 'param' => 'Temperature',  'field' => 'temp_max', 'decimals' => (int) $naws_mr_catalogue['hottest_day']['decimals'] ],
    'min'  => [ 'param' => 'Temperature',  'field' => 'temp_min', 'decimals' => (int) $naws_mr_catalogue['hottest_day']['decimals'] ],
    'rain' => [ 'param' => 'Rain',         'field' => 'rain_sum', 'decimals' => (int) $naws_mr_catalogue['wettest_day']['decimals'] ],
    'gust' => [ 'param' => 'GustStrength', 'field' => 'gust_max', 'decimals' => (int) $naws_mr_catalogue['strongest_gust']['decimals'] ],
];

// The headline: one sentence, month name only or with the year.
$naws_mr_head_key = (string) $headline['key'];
if ( in_array( $naws_mr_head_key, [ 'mr_head_hottest_day', 'mr_head_coldest_night', 'mr_head_gust' ], true ) ) {
    $naws_mr_head = sprintf( naws_label( $naws_mr_head_key ), $naws_mr_name_year, $naws_mr_name );
} elseif ( $naws_mr_head_key === 'mr_head_neutral' ) {
    $naws_mr_head = sprintf( naws_label( $naws_mr_head_key ), $naws_mr_name_year );
} else {
    $naws_mr_head = sprintf( naws_label( $naws_mr_head_key ), $naws_mr_name );
}

/** One supporting sentence as text. */
$naws_mr_sentence = static function ( array $s ) use ( $naws_mr_day, $naws_mr_text, $naws_mr_catalogue ): string {
    $a = $s['args'];
    if ( $s['key'] === 'mr_frost' || $s['key'] === 'mr_summer' ) {
        $text = sprintf( naws_label_n( $s['key'], (int) $a['n'] ), (int) $a['n'] );
        if ( $a['prev'] !== null ) {
            $text .= ' ' . sprintf( naws_label( 'mr_prev_count' ), (string) $a['prev_year'], (int) $a['prev'] );
        }
        return $text;
    }
    if ( $s['key'] === 'mr_wettest_day' ) {
        return sprintf( naws_label( 'mr_wettest_day' ), $naws_mr_day( (string) $a['date'] ), $naws_mr_text( 'Rain', (float) $a['value'], (int) $naws_mr_catalogue['wettest_day']['decimals'] ) );
    }
    $param = [
        'mr_station_record_hottest_day'    => 'Temperature',
        'mr_station_record_coldest_night'  => 'Temperature',
        'mr_station_record_strongest_gust' => 'GustStrength',
        'mr_station_record_wettest_day'    => 'Rain',
    ][ $s['key'] ] ?? 'Temperature';
    return sprintf( naws_label( $s['key'] ), $naws_mr_text( $param, (float) $a['value'], 1 ), $naws_mr_day( (string) $a['date'] ) );
};

$naws_mr_cur = $cmp['current'];

if ( $nav ) {
    printf(
        '<section class="naws-mr" data-naws-mr data-month="%s" data-years="%d" data-title="%s" data-ajax="%s" data-nonce="%s">',
        esc_attr( $ym ),
        (int) $years,
        esc_attr( $title ),
        esc_url( admin_url( 'admin-ajax.php' ) ),
        esc_attr( wp_create_nonce( 'naws_public_nonce' ) )
    );
} else {
    echo '<section class="naws-mr">';
}
?>

  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-mr-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>

  <?php if ( $nav ) : ?>
    <nav class="naws-mr-nav">
      <?php if ( $neighbours['prev'] !== null ) :
          $naws_mr_prev_name = $naws_mr_month( $neighbours['prev'], 'F Y' ); ?>
        <a class="naws-mr-step naws-mr-prev" href="<?php echo esc_url( add_query_arg( 'naws_month', $neighbours['prev'] ) ); ?>" data-month="prev" aria-label="<?php echo esc_attr( sprintf( naws_label( 'mr_nav_prev' ), $naws_mr_prev_name ) ); ?>"><span aria-hidden="true">&lsaquo;</span> <span class="naws-mr-step-text"><?php echo esc_html( $naws_mr_prev_name ); ?></span></a>
      <?php else : ?>
        <span class="naws-mr-step naws-mr-step--none" aria-hidden="true"></span>
      <?php endif; ?>
      <span class="naws-mr-month"><?php echo esc_html( $naws_mr_name_year ); ?></span>
      <?php if ( $neighbours['next'] !== null ) :
          $naws_mr_next_name = $naws_mr_month( $neighbours['next'], 'F Y' ); ?>
        <a class="naws-mr-step naws-mr-next" href="<?php echo esc_url( add_query_arg( 'naws_month', $neighbours['next'] ) ); ?>" data-month="next" aria-label="<?php echo esc_attr( sprintf( naws_label( 'mr_nav_next' ), $naws_mr_next_name ) ); ?>"><span class="naws-mr-step-text"><?php echo esc_html( $naws_mr_next_name ); ?></span> <span aria-hidden="true">&rsaquo;</span></a>
      <?php else : ?>
        <span class="naws-mr-step naws-mr-step--none" aria-hidden="true"></span>
      <?php endif; ?>
    </nav>
  <?php else : ?>
    <h3 class="naws-mr-month"><?php echo esc_html( $naws_mr_name_year ); ?></h3>
  <?php endif; ?>

  <div class="naws-mr-body" aria-live="polite">
    <h3 class="naws-mr-head" tabindex="-1"><?php echo esc_html( $naws_mr_head ); ?></h3>

    <div class="naws-mr-tiles">
      <?php foreach ( $naws_mr_tiles as $naws_mr_kpi => $naws_mr_tile ) :
          $naws_mr_now = NAWS_Month_Review::figure( $naws_mr_cur, $naws_mr_kpi );
          if ( $naws_mr_now === null ) {
              continue; // No readings for this column at all: no tile.
          }
          $naws_mr_parts = NAWS_Records::display_value( $naws_mr_tile['param'], $naws_mr_now, $naws_mr_tile['decimals'] );
          $naws_mr_field = $naws_mr_tile['field'];

          // Record star or trend arrow, each with a text for screen readers.
          $naws_mr_mark = '';
          if ( ( $cmp['record'][ $naws_mr_kpi ] ?? null ) !== null ) {
              $naws_mr_mark = 'record';
          } elseif ( ( $cmp['trend'][ $naws_mr_kpi ] ?? null ) !== null ) {
              $naws_mr_mark = $cmp['trend'][ $naws_mr_kpi ];
          }
          ?>
        <div class="naws-mr-tile naws-mr-tile--<?php echo esc_attr( $naws_mr_kpi ); ?>">
          <span class="naws-mr-tile-name"><?php echo esc_html( naws_label( 'mr_tile_' . $naws_mr_kpi ) ); ?></span>
          <span class="naws-mr-tile-val"><?php echo esc_html( $naws_mr_parts['value'] ); ?> <small><?php echo esc_html( $naws_mr_parts['unit'] ); ?></small>
            <?php if ( $naws_mr_mark === 'record' ) : ?>
              <span class="naws-mr-mark naws-mr-mark--record"><span aria-hidden="true">&#9733;</span><span class="screen-reader-text"><?php echo esc_html( naws_label( 'mr_record_sr' ) ); ?></span></span>
            <?php elseif ( $naws_mr_mark === 'up' ) : ?>
              <span class="naws-mr-mark naws-mr-mark--up"><span aria-hidden="true">&#9650;</span><span class="screen-reader-text"><?php echo esc_html( naws_label( 'mr_up_sr' ) ); ?></span></span>
            <?php elseif ( $naws_mr_mark === 'down' ) : ?>
              <span class="naws-mr-mark naws-mr-mark--down"><span aria-hidden="true">&#9660;</span><span class="screen-reader-text"><?php echo esc_html( naws_label( 'mr_down_sr' ) ); ?></span></span>
            <?php endif; ?>
          </span>

          <?php if ( ! $naws_mr_cur['complete'][ $naws_mr_kpi ] ) : ?>
            <span class="naws-mr-tile-sub naws-mr-incomplete"><?php echo esc_html( sprintf( naws_label( 'mr_incomplete' ), (int) $naws_mr_cur['days'][ $naws_mr_field ], (int) $naws_mr_cur['length'] ) ); ?></span>
          <?php endif; ?>

          <?php if ( is_array( $naws_mr_cur[ $naws_mr_kpi ] ) ) : ?>
            <span class="naws-mr-tile-sub"><?php echo esc_html( sprintf( naws_label( 'mr_on_date' ), $naws_mr_day( (string) $naws_mr_cur[ $naws_mr_kpi ]['date'] ) ) ); ?></span>
          <?php endif; ?>

          <?php if ( $naws_mr_kpi === 'rain' && $naws_mr_cur['rain_days'] !== null && $naws_mr_cur['complete']['rain_days'] ) : ?>
            <span class="naws-mr-tile-sub"><?php echo esc_html( sprintf( naws_label_n( 'mr_rain_days', (int) $naws_mr_cur['rain_days'] ), (int) $naws_mr_cur['rain_days'] ) ); ?></span>
          <?php endif; ?>

          <?php foreach ( $cmp['previous'] as $naws_mr_year => $naws_mr_prev ) :
              $naws_mr_then = NAWS_Month_Review::figure( $naws_mr_prev, $naws_mr_kpi );
              if ( $naws_mr_then === null || ! $naws_mr_prev['complete'][ $naws_mr_kpi ] ) {
                  continue;
              }
              $naws_mr_then_parts = NAWS_Records::display_value( $naws_mr_tile['param'], $naws_mr_then, $naws_mr_tile['decimals'] );
              ?>
            <span class="naws-mr-prev-year"><?php echo esc_html( (string) $naws_mr_year . ': ' . $naws_mr_then_parts['value'] ); ?></span>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ( $sentences ) : ?>
      <ul class="naws-mr-notes">
        <?php foreach ( $sentences as $naws_mr_s ) : ?>
          <li><?php echo esc_html( $naws_mr_sentence( $naws_mr_s ) ); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
