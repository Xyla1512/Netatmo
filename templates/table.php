<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/* templates/table.php */
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Rendered by NAWS_Shortcodes::sc_table(), which hands over $atts and the
 * $readings rows from NAWS_Database::get_readings().
 *
 * A row carries module_id, parameter, recorded_at and value. When group_by
 * names a bucket — hour, day, week, month, year — the query aggregates, and
 * the row also carries min_value, max_value and data_points; value is then
 * the average of the bucket. Grouping 'raw' returns single measurements and
 * none of those three, so the extra columns only appear when they mean
 * something.
 */

$naws_grouped = isset( $readings[0]['min_value'] );

// Buckets of a day or longer have no meaningful time of day.
$naws_date_only = in_array( $atts['group_by'], [ 'day', 'week', 'month', 'year' ], true );
$naws_fmt = $naws_date_only
    ? get_option( 'date_format', 'Y-m-d' )
    : get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );

$naws_module_names = [];
foreach ( NAWS_Database::get_modules() as $naws_mod ) {
    $naws_module_names[ $naws_mod['module_id'] ] = $naws_mod['module_name'];
}

$naws_param_labels = NAWS_Helpers::get_all_parameters();
?>
<div class="naws-wrap">

    <?php if ( ! empty( $atts['title'] ) ) : ?>
    <div class="naws-header">
        <h2 class="naws-header-title"><?php echo esc_html( $atts['title'] ); ?></h2>
    </div>
    <?php endif; ?>

    <?php if ( empty( $readings ) ) : ?>
        <p style="color:var(--naws-text-muted)"><?php naws_e( 'no_data' ); ?></p>
    <?php else : ?>
    <div class="naws-table-wrap">
        <table class="naws-table">
            <thead>
                <tr>
                    <th><?php naws_e( 'table_col_time' ); ?></th>
                    <th><?php naws_e( 'table_col_module' ); ?></th>
                    <th><?php naws_e( 'table_col_parameter' ); ?></th>
                    <th><?php naws_e( $naws_grouped ? 'table_col_avg' : 'value' ); ?></th>
                    <?php if ( $naws_grouped ) : ?>
                        <th><?php naws_e( 'lbl_min' ); ?></th>
                        <th><?php naws_e( 'lbl_max' ); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $readings as $naws_row ) :
                $naws_param = $naws_row['parameter'];
                $naws_unit  = NAWS_Helpers::get_unit( $naws_param );
            ?>
                <tr>
                    <td><?php echo esc_html( wp_date( $naws_fmt, intval( $naws_row['recorded_at'] ) ) ); ?></td>
                    <td><?php echo esc_html( $naws_module_names[ $naws_row['module_id'] ] ?? $naws_row['module_id'] ); ?></td>
                    <td><?php echo esc_html( $naws_param_labels[ $naws_param ] ?? $naws_param ); ?></td>
                    <td><?php echo esc_html( NAWS_Helpers::format_value( $naws_param, $naws_row['value'] ) . ' ' . $naws_unit ); ?></td>
                    <?php if ( $naws_grouped ) : ?>
                        <td><?php echo esc_html( NAWS_Helpers::format_value( $naws_param, $naws_row['min_value'] ) . ' ' . $naws_unit ); ?></td>
                        <td><?php echo esc_html( NAWS_Helpers::format_value( $naws_param, $naws_row['max_value'] ) . ' ' . $naws_unit ); ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
