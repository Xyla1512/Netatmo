<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Template: [naws_heatmap year="" title="" legend="yes"]
 *
 * Ein Jahr Aussen-Tagesdurchschnitt als Kalenderraster. Zeilen sind
 * Monate, Spalten Tage. Eine <table> und keine Grafik: die klebende
 * Monatsspalte ist damit eine Zeile CSS statt einer Zeile JavaScript, und
 * ein Screenreader liest "Maerz, 14., 8,2 °C" statt "Grafik".
 *
 * Die Farben stehen fertig im style-Attribut jeder Zelle, gerechnet von
 * NAWS_Colors::heatmap_color(). Ohne JavaScript ist die Karte deshalb
 * vollstaendig da — das Skript ergaenzt Tooltip, Jahreswechsel und die
 * Animation, es baut nichts auf.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$widget_id = 'naws-hm-' . wp_unique_id();
$nonce     = wp_create_nonce( 'naws_public_nonce' );
$ajax_url  = admin_url( 'admin-ajax.php' );

// Welche Jahre es gibt. MIN()/MAX() liefern immer eine Zeile — auf einer
// leeren Tabelle sind beide Spalten NULL, deshalb die Werte pruefen und
// nicht die Zeile (substr(null) ist seit PHP 8.1 deprecated).
$range   = NAWS_Database::get_daily_data_range();
$y_first = ! empty( $range['date_begin'] ) ? (int) substr( $range['date_begin'], 0, 4 ) : (int) gmdate( 'Y' );
$y_last  = ! empty( $range['date_end'] )   ? (int) substr( $range['date_end'],   0, 4 ) : (int) gmdate( 'Y' );
if ( $y_first < 2000 || $y_first > $y_last ) $y_first = $y_last;
$years = range( $y_last, $y_first ); // neuestes zuerst

$wanted = (int) ( $atts['year'] ?? 0 );
$now    = (int) gmdate( 'Y' );
$year   = in_array( $wanted, $years, true ) ? $wanted
        : ( in_array( $now, $years, true ) ? $now : $y_last );

$data   = NAWS_Database::get_heatmap_year( $year );
$values = $data['values'];
$srcs   = $data['sources'];
$grey   = NAWS_Colors::heatmap_color( null );

$has_any = false;
foreach ( $values as $month ) {
    foreach ( $month as $v ) { if ( $v !== null ) { $has_any = true; break 2; } }
}
?>
<div id="<?php echo esc_attr( $widget_id ); ?>" class="naws-hm"
     data-nonce="<?php echo esc_attr( $nonce ); ?>"
     data-ajax="<?php echo esc_attr( $ajax_url ); ?>"
     data-year="<?php echo esc_attr( (string) $year ); ?>">

  <div class="naws-hm-hdr">
    <div class="naws-hm-title"><?php echo esc_html( $atts['title'] ?? '' ); ?></div>
    <div class="naws-hm-years">
      <?php foreach ( $years as $y ) :
          // Dieselben Pillen, die die Charts als Jahres-Umschalter benutzen,
          // samt dem Punkt in der Farbe dieses Jahres. history-boot.js waehlt
          // ihn als PALETTE[(Jahr - aeltestes Jahr) % 15]; dieselbe Rechnung
          // hier haelt ein Jahr in beiden Ansichten in einer Farbe.
          $dot = NAWS_Colors::get( 'history_year_' . ( ( ( $y - $y_first ) % 15 ) + 1 ) );
      ?>
        <button type="button"
                class="naws-leg-pill naws-hm-year<?php echo $y === $year ? ' is-active' : ' hidden'; ?>"
                data-year="<?php echo esc_attr( (string) $y ); ?>"><span class="naws-leg-pill-dot" style="background:<?php echo esc_attr( $dot ); ?>"></span><?php echo esc_html( (string) $y ); ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ( ! $has_any ) : ?>
    <div class="naws-hm-empty"><?php esc_html_e( 'No data for this period.', 'xtx-integration-for-netatmo' ); ?></div>
  <?php endif; ?>

  <div class="naws-hm-scroll">
    <table class="naws-hm-grid">
      <thead>
        <tr>
          <th class="naws-hm-corner"><span class="screen-reader-text"><?php esc_html_e( 'Month', 'xtx-integration-for-netatmo' ); ?></span></th>
          <?php for ( $d = 1; $d <= 31; $d++ ) : ?>
            <th class="naws-hm-dh" scope="col"><?php echo esc_html( (string) $d ); ?></th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php for ( $m = 1; $m <= 12; $m++ ) :
            $days  = count( $values[ $m - 1 ] ?? [] );
            $mname = wp_date( 'F', gmmktime( 12, 0, 0, $m, 1, $year ) );
        ?>
        <tr class="naws-hm-row">
          <th class="naws-hm-mh" scope="row"><?php echo esc_html( $mname ); ?></th>
          <?php for ( $d = 1; $d <= 31; $d++ ) :
              if ( $d > $days ) : ?>
                <td class="naws-hm-x" aria-hidden="true"></td>
              <?php continue; endif;

              $v     = $values[ $m - 1 ][ $d - 1 ] ?? null;
              $src   = $srcs[ $m - 1 ][ $d - 1 ] ?? null;
              $color = $v === null ? $grey : NAWS_Colors::heatmap_color( $v );
              $label = NAWS_Helpers::heatmap_label( $v, $src );
              $date  = sprintf( '%04d-%02d-%02d', $year, $m, $d );
          ?>
            <td class="naws-hm-c"
                style="background:<?php echo esc_attr( $color ); ?>"
                data-d="<?php echo esc_attr( $date ); ?>"
                data-day="<?php echo esc_attr( (string) $d ); ?>"
                data-v="<?php echo esc_attr( $v === null ? '' : (string) $v ); ?>"
                data-l="<?php echo esc_attr( $label ); ?>"
                data-src="<?php echo esc_attr( (string) $src ); ?>">
              <span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
            </td>
          <?php endfor; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <?php if ( ( $atts['legend'] ?? 'yes' ) !== 'no' ) :
      $scale = NAWS_Colors::heatmap_scale();
      $last  = count( $scale ) - 1;
      $stops = [];
      foreach ( $scale as $i => $s ) {
          $stops[] = $s[1] . ' ' . round( $i / $last * 100 ) . '%';
      }
  ?>
  <div class="naws-hm-legend">
    <span class="naws-hm-legend-min"><?php echo esc_html( NAWS_Helpers::heatmap_label( $scale[0][0], 'avg' ) ); ?></span>
    <span class="naws-hm-legend-bar" style="background:linear-gradient(90deg,<?php echo esc_attr( implode( ',', $stops ) ); ?>)"></span>
    <span class="naws-hm-legend-max"><?php echo esc_html( NAWS_Helpers::heatmap_label( $scale[ $last ][0], 'avg' ) ); ?></span>
  </div>
  <?php endif; ?>
</div>
