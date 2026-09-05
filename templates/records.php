<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_records year="" records="" layout="cards" title=""]
 *
 * Fifteen records from the daily summary as tiles or as a table. Rendered
 * on the server, no script: a record does not change between two page
 * loads. Colours come from the theme variables, so the Appearance page
 * reaches these tiles like every other block.
 *
 * Expected variables:
 * @var array      $atts      Shortcode attributes, already through shortcode_atts()
 * @var array|null $naws_rows Daily rows; set by the tests, null on a real page
 *
 * @package NAWS
 * @since   1.9.11
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$rows = $naws_rows ?? NAWS_Records::rows( $atts );
if ( empty( $rows ) ) {
    return;
}

$wanted = array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $atts['records'] ?? '' ) ) ) );
$found  = NAWS_Records::all( $rows, array_values( $wanted ) );
if ( empty( $found ) ) {
    return;
}

$catalogue = NAWS_Records::catalogue();
$layout    = ( $atts['layout'] ?? 'cards' ) === 'table' ? 'table' : 'cards';
$title     = (string) ( $atts['title'] ?? '' );
$coverage  = NAWS_Records::coverage( $rows );
$date_fmt  = get_option( 'date_format', 'j. F Y' );

/**
 * Value and unit of one result, in the site's units.
 * @return array{value:string,unit:string}
 */
$naws_rec_parts = static function ( array $entry, array $result ): array {
    if ( $entry['kind'] === 'streak' ) {
        $n = (int) $result['value'];
        /* translators: %d: number of days */
        return [ 'value' => sprintf( _n( '%d day', '%d days', $n, 'xtx-integration-for-netatmo' ), $n ), 'unit' => '' ];
    }
    if ( $entry['param'] === 'delta' ) {
        $d = NAWS_Records::delta_parts( (float) $result['value'] );
        return [ 'value' => number_format_i18n( $d['value'], $entry['decimals'] ), 'unit' => $d['unit'] ];
    }
    // format_value() already returns three decimals for rain in inches
    // (26.4 mm -> 1.039 in); the catalogue's one decimal is right for mm
    // and would round that away.
    $decimals = $entry['decimals'];
    if ( $entry['param'] === 'Rain' ) {
        $options = get_option( 'naws_settings', [] );
        if ( ( $options['rain_unit'] ?? 'mm' ) === 'in' ) {
            $decimals = 3;
        }
    }
    return [
        'value' => number_format_i18n( (float) NAWS_Helpers::format_value( $entry['param'], (float) $result['value'] ), $decimals ),
        'unit'  => (string) NAWS_Helpers::get_unit( $entry['param'] ),
    ];
};

// Anchored on noon: WordPress runs PHP in UTC, so strtotime('Y-m-d') is
// midnight UTC and wp_date() would render the previous day on any site
// with a negative UTC offset (same pattern as NAWS_Calc::raw_sum()).
$naws_rec_day = static fn( string $ymd ): string => wp_date( $date_fmt, strtotime( $ymd . ' 12:00:00' ) );

/** When it happened: a day, a month, or a span. */
$naws_rec_when = static function ( array $entry, array $result ) use ( $date_fmt, $naws_rec_day ): string {
    if ( $entry['kind'] === 'month' ) {
        return wp_date( 'F Y', strtotime( $result['month'] . '-15' ) );
    }
    if ( $entry['kind'] === 'streak' ) {
        return $naws_rec_day( $result['from'] ) . ' – ' . $naws_rec_day( $result['to'] );
    }
    return $naws_rec_day( $result['date'] );
};
?>
<section class="naws-rec">
  <?php if ( $title !== '' ) : ?>
    <h3 class="naws-rec-title"><?php echo esc_html( $title ); ?></h3>
  <?php endif; ?>

  <?php if ( $layout === 'table' ) : ?>
    <table class="naws-rec-table">
      <thead>
        <tr>
          <th><?php echo esc_html( naws_label( 'rec_col_record' ) ); ?></th>
          <th><?php echo esc_html( naws_label( 'rec_col_value' ) ); ?></th>
          <th><?php echo esc_html( naws_label( 'rec_col_when' ) ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $found as $key => $result ) : $entry = $catalogue[ $key ]; $parts = $naws_rec_parts( $entry, $result ); ?>
          <tr class="naws-rec-row naws-rec-<?php echo esc_attr( $key ); ?>">
            <td><?php echo esc_html( naws_label( $entry['label'] ) ); ?></td>
            <td><?php echo esc_html( $parts['value'] ); ?><?php if ( $parts['unit'] !== '' ) : ?> <span class="naws-rec-unit"><?php echo esc_html( $parts['unit'] ); ?></span><?php endif; ?></td>
            <td><?php echo esc_html( $naws_rec_when( $entry, $result ) ); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else : ?>
    <div class="naws-rec-grid">
      <?php foreach ( $found as $key => $result ) : $entry = $catalogue[ $key ]; $parts = $naws_rec_parts( $entry, $result ); ?>
        <div class="naws-rec-tile naws-rec-<?php echo esc_attr( $key ); ?>">
          <span class="naws-rec-label"><?php echo esc_html( naws_label( $entry['label'] ) ); ?></span>
          <span class="naws-rec-value"><?php echo esc_html( $parts['value'] ); ?><?php if ( $parts['unit'] !== '' ) : ?> <span class="naws-rec-unit"><?php echo esc_html( $parts['unit'] ); ?></span><?php endif; ?></span>
          <span class="naws-rec-when"><?php echo esc_html( $naws_rec_when( $entry, $result ) ); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( $coverage['first'] !== null ) : ?>
    <p class="naws-rec-foot"><?php
      /* translators: 1: first date with readings, 2: "365 days" */
      echo esc_html( sprintf(
          naws_label( 'rec_since' ),
          $naws_rec_day( $coverage['first'] ),
          /* translators: %d: number of days */
          sprintf( _n( '%d day', '%d days', $coverage['days'], 'xtx-integration-for-netatmo' ), $coverage['days'] )
      ) );
    ?></p>
  <?php endif; ?>
</section>
