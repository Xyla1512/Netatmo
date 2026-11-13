<?php
/**
 * Module comparison: two daily series from any modules on one continuous
 * time axis — [naws_compare].
 *
 * The arithmetic is pure (catalogue, parsing the shortcode's series,
 * the period, merging and thinning, the axes) and tested on hand-built
 * rows. Only the WordPress layer further down touches the database.
 *
 * @package NAWS
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NAWS_Compare {

    /** Beyond this many days a series is thinned to ISO weeks. */
    const MAX_DAYS = 3650;

    /** Seconds per day (no WordPress constant in the pure part). */
    const DAY = 86400;

    /** Bounds of the months attribute. */
    const MONTHS_MAX = 120;

    /**
     * Every series that can be compared, grouped by the module reference
     * it belongs to. 'in' stands for every indoor module (in-<slug>).
     *
     * row   station — the base station's daily row (outdoor, rain, wind and
     *                 the base's own indoor values live there)
     *       own     — the module's own daily row (NAModule4)
     * col   column of the daily summary
     * param what NAWS_Helpers::get_unit()/format_value() convert with
     * chart line or bar
     * label naws_label() key of the quantity
     */
    public static function series_catalogue(): array {
        $e = static function ( string $type, string $row, string $col, string $param, string $label, string $chart = 'line' ): array {
            return [ 'type' => $type, 'row' => $row, 'col' => $col, 'param' => $param, 'chart' => $chart, 'label' => $label ];
        };
        return [
            'outdoor' => [
                'temp'     => $e( 'NAModule1', 'station', 'temp_avg', 'Temperature', 'cmp_q_temp' ),
                'temp_min' => $e( 'NAModule1', 'station', 'temp_min', 'Temperature', 'cmp_q_temp_min' ),
                'temp_max' => $e( 'NAModule1', 'station', 'temp_max', 'Temperature', 'cmp_q_temp_max' ),
                'humidity' => $e( 'NAModule1', 'station', 'humidity_avg', 'Humidity', 'cmp_q_humidity' ),
            ],
            'indoor'  => [
                'temp'     => $e( 'NAMain', 'station', 'indoor_temp_avg', 'Temperature', 'cmp_q_temp' ),
                'humidity' => $e( 'NAMain', 'station', 'indoor_humidity_avg', 'Humidity', 'cmp_q_humidity' ),
                'co2'      => $e( 'NAMain', 'station', 'co2_avg', 'CO2', 'cmp_q_co2' ),
                'noise'    => $e( 'NAMain', 'station', 'noise_avg', 'Noise', 'cmp_q_noise' ),
                'pressure' => $e( 'NAMain', 'station', 'pressure_avg', 'Pressure', 'cmp_q_pressure' ),
            ],
            'rain'    => [
                'rain' => $e( 'NAModule3', 'station', 'rain_sum', 'Rain', 'cmp_q_rain', 'bar' ),
            ],
            'wind'    => [
                'wind' => $e( 'NAModule2', 'station', 'wind_avg', 'WindStrength', 'cmp_q_wind' ),
                'gust' => $e( 'NAModule2', 'station', 'gust_max', 'GustStrength', 'cmp_q_gust' ),
            ],
            'in'      => [
                'temp'     => $e( 'NAModule4', 'own', 'indoor_temp_avg', 'Temperature', 'cmp_q_temp' ),
                'humidity' => $e( 'NAModule4', 'own', 'indoor_humidity_avg', 'Humidity', 'cmp_q_humidity' ),
                'co2'      => $e( 'NAModule4', 'own', 'co2_avg', 'CO2', 'cmp_q_co2' ),
            ],
        ];
    }

    /** The catalogue group a module reference belongs to, or null. */
    public static function group_of( string $ref ): ?string {
        if ( preg_match( '/^in-[a-z0-9]+$/', $ref ) ) {
            return 'in';
        }
        return isset( self::series_catalogue()[ $ref ] ) && $ref !== 'in' ? $ref : null;
    }

    /**
     * One series of the shortcode, "module:quantity". Checked against the
     * catalogue only — whether the module exists is the WordPress layer's
     * question.
     *
     * @return array{ok: bool, ref: string, qty: string, error: string}
     */
    public static function parse_series( string $spec ): array {
        $spec = strtolower( trim( $spec ) );
        $out  = [ 'ok' => false, 'ref' => '', 'qty' => '', 'error' => 'syntax' ];
        if ( substr_count( $spec, ':' ) !== 1 ) {
            return $out;
        }
        [ $ref, $qty ] = array_map( 'trim', explode( ':', $spec ) );
        if ( $ref === '' || $qty === '' ) {
            return $out;
        }
        $out['ref'] = $ref;
        $out['qty'] = $qty;
        $group      = self::group_of( $ref );
        if ( $group === null ) {
            $out['error'] = 'unknown_module';
            return $out;
        }
        if ( ! isset( self::series_catalogue()[ $group ][ $qty ] ) ) {
            $out['error'] = 'unknown_quantity';
            return $out;
        }
        $out['ok']    = true;
        $out['error'] = '';
        return $out;
    }

    /**
     * The period to draw, as Y-m-d.
     *
     * from/to take JJJJ-MM (whole month) or JJJJ-MM-TT; months=N reaches N
     * calendar months back from to's month and yields to an explicit from.
     * Without from/to the period runs from the first day with data to
     * yesterday, and auto_* tells build() to trim it to the data.
     *
     * @param array       $atts      from, to, months (strings).
     * @param string|null $first_day First day of the daily summary, null without data.
     * @param string      $yesterday Yesterday in the site's timezone.
     */
    public static function period( array $atts, ?string $first_day, string $yesterday ): array {
        $from = self::date_of( (string) ( $atts['from'] ?? '' ), false );
        $to   = self::date_of( (string) ( $atts['to'] ?? '' ), true );

        $auto_to = $to === null;
        if ( $to === null || $to > $yesterday ) {
            $to = $yesterday;
        }

        $months = (int) ( $atts['months'] ?? 0 );
        if ( $from === null && $months > 0 ) {
            $months = min( self::MONTHS_MAX, $months );
            $from   = gmdate( 'Y-m-01', strtotime( substr( $to, 0, 7 ) . '-01 -' . ( $months - 1 ) . ' months' ) );
        }

        $auto_from = $from === null;
        if ( $from === null ) {
            $from = $first_day;
        }

        if ( $from !== null && $from > $to ) {
            [ $from, $to ] = [ $to, $from ];
            // A swapped explicit pair keeps the whole of both months.
            if ( preg_match( '/^\d{4}-\d{2}$/', (string) ( $atts['from'] ?? '' ) ) ) {
                $to = gmdate( 'Y-m-t', strtotime( $to ) );
            }
            if ( preg_match( '/^\d{4}-\d{2}$/', (string) ( $atts['to'] ?? '' ) ) ) {
                $from = substr( $from, 0, 8 ) . '01';
            }
        }

        return [ 'from' => $from, 'to' => $to, 'auto_from' => $auto_from, 'auto_to' => $auto_to ];
    }

    /**
     * The series on one shared day axis.
     *
     * Every calendar day from..to is a point; a day without a value is null,
     * so the chart breaks the line there instead of drawing across a gap.
     * auto_from/auto_to trim the axis to the first/last day on which any
     * series has a value. Beyond MAX_DAYS the series are thinned to ISO
     * weeks (Monday as the date): lines the mean of the week's values,
     * bars their sum, a week without values null.
     *
     * @param array $series List of ['values' => ['Y-m-d' => ?float], 'chart' => 'line'|'bar'].
     * @return array{dates: string[], values: array, weekly: bool}
     */
    public static function build( array $series, string $from, string $to, bool $auto_from, bool $auto_to ): array {
        $first = null;
        $last  = null;
        foreach ( $series as $s ) {
            foreach ( $s['values'] as $day => $v ) {
                if ( $v === null || $day < $from || $day > $to ) {
                    continue;
                }
                $first = ( $first === null || $day < $first ) ? $day : $first;
                $last  = ( $last === null || $day > $last ) ? $day : $last;
            }
        }
        if ( $first === null ) {
            return [ 'dates' => [], 'values' => array_fill( 0, count( $series ), [] ), 'weekly' => false ];
        }
        if ( $auto_from ) {
            $from = $first;
        }
        if ( $auto_to ) {
            $to = $last;
        }

        $dates = [];
        for ( $t = strtotime( $from . ' 12:00:00 UTC' ), $end = strtotime( $to . ' 12:00:00 UTC' ); $t <= $end; $t += self::DAY ) {
            $dates[] = gmdate( 'Y-m-d', $t );
        }

        if ( count( $dates ) <= self::MAX_DAYS ) {
            $values = [];
            foreach ( $series as $s ) {
                $row = [];
                foreach ( $dates as $day ) {
                    $row[] = isset( $s['values'][ $day ] ) ? (float) $s['values'][ $day ] : null;
                }
                $values[] = $row;
            }
            return [ 'dates' => $dates, 'values' => $values, 'weekly' => false ];
        }

        // ISO weeks, keyed by their Monday.
        $weeks = [];
        foreach ( $dates as $day ) {
            $t      = strtotime( $day . ' 12:00:00 UTC' );
            $monday = gmdate( 'Y-m-d', $t - ( (int) gmdate( 'N', $t ) - 1 ) * self::DAY );
            $weeks[ $monday ][] = $day;
        }
        $values = [];
        foreach ( $series as $s ) {
            $row = [];
            foreach ( $weeks as $days ) {
                $vals = [];
                foreach ( $days as $day ) {
                    if ( isset( $s['values'][ $day ] ) ) {
                        $vals[] = (float) $s['values'][ $day ];
                    }
                }
                if ( $vals === [] ) {
                    $row[] = null;
                } elseif ( ( $s['chart'] ?? 'line' ) === 'bar' ) {
                    $row[] = round( array_sum( $vals ), 3 );
                } else {
                    $row[] = round( array_sum( $vals ) / count( $vals ), 3 );
                }
            }
            $values[] = $row;
        }
        return [ 'dates' => array_keys( $weeks ), 'values' => $values, 'weekly' => true ];
    }

    /**
     * Which axis each series draws on: one shared axis for one unit, a
     * second one on the right for a second unit.
     */
    public static function axes( string $unit_a, ?string $unit_b ): array {
        if ( $unit_b === null ) {
            return [ 'a' => 'y', 'b' => null, 'right' => false ];
        }
        if ( $unit_a === $unit_b ) {
            return [ 'a' => 'y', 'b' => 'y', 'right' => false ];
        }
        return [ 'a' => 'y', 'b' => 'y1', 'right' => true ];
    }

    /** JJJJ-MM or JJJJ-MM-TT to Y-m-d; a month gives its first or last day. Invalid → null. */
    private static function date_of( string $value, bool $end ): ?string {
        $value = trim( $value );
        if ( preg_match( '/^(\d{4})-(\d{2})$/', $value, $m ) ) {
            if ( ! checkdate( (int) $m[2], 1, (int) $m[1] ) ) {
                return null;
            }
            return $end ? gmdate( 'Y-m-t', strtotime( $value . '-01' ) ) : $value . '-01';
        }
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : null;
        }
        return null;
    }

    // ── WordPress layer ──────────────────────────────────────────────────

    /** Decimals a value of this parameter is shown with, in the site's units. */
    public static function decimals( string $param ): int {
        $options = get_option( 'naws_settings', [] );
        switch ( $param ) {
            case 'Humidity':
            case 'CO2':
            case 'Noise':
                return 0;
            case 'Rain':
                return ( $options['rain_unit'] ?? 'mm' ) === 'in' ? 3 : 1;
            case 'Pressure':
                return ( $options['pressure_unit'] ?? 'mbar' ) === 'inHg' ? 2 : 1;
        }
        return 1;
    }

    /** The module row behind a reference, active or not; null when unknown. */
    private static function module_of( string $ref ): ?array {
        $id = NAWS_Helpers::resolve_module_ref( $ref );
        if ( $id === null ) {
            return null;
        }
        foreach ( NAWS_Database::get_modules( false ) as $m ) {
            if ( ( $m['module_id'] ?? '' ) === $id ) {
                return $m;
            }
        }
        return null;
    }

    /**
     * "Quantity · Module" for the legend. The module part is the display
     * name set on the modules screen; without one, outdoor/base/wind/rain
     * read as their type (like the live cards), indoor modules as their name.
     */
    public static function series_name( string $ref, string $qty ): string {
        $group = self::group_of( $ref );
        $entry = $group !== null ? ( self::series_catalogue()[ $group ][ $qty ] ?? null ) : null;
        $label = $entry !== null ? naws_label( $entry['label'] ) : $qty;
        return sprintf( naws_label( 'cmp_series_name' ), $label, self::module_label( $ref ) );
    }

    /** The module part of a series name, the same in the legend and the builder. */
    public static function module_label( string $ref ): string {
        $group = self::group_of( $ref );
        $m     = self::module_of( $ref );
        if ( $group === 'in' || $group === null ) {
            return $m !== null ? NAWS_Helpers::display_name( $m ) : $ref;
        }
        $custom = $m !== null ? (string) ( $m['custom_name'] ?? '' ) : '';
        return $custom !== '' ? $custom : naws_label( 'cmp_mod_' . $group );
    }

    /**
     * What the shortcode builder offers: the active modules of the station,
     * each with its quantities and how well the daily summary covers them.
     * empty — not a single day; sparse — fewer than half the days of that
     * row (the base station's indoor temperature, today).
     *
     * @return array<int, array{ref: string, name: string, qtys: array}>
     */
    public static function catalogue_for_admin(): array {
        $coverage = NAWS_Database::daily_coverage();
        $station  = NAWS_Calc::station_row_id( [] );
        $cat      = self::series_catalogue();
        $out      = [];
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            $ref   = NAWS_Helpers::module_ref( (string) $m['module_id'] );
            $group = $ref !== '' ? self::group_of( $ref ) : null;
            if ( $group === null ) {
                continue;
            }
            $qtys = [];
            foreach ( $cat[ $group ] as $qty => $entry ) {
                $row   = $entry['row'] === 'own' ? (string) $m['module_id'] : (string) $station;
                $days  = (int) ( $coverage[ $row ]['_days'] ?? 0 );
                $have  = (int) ( $coverage[ $row ][ $entry['col'] ] ?? 0 );
                $state = $have === 0 ? 'empty' : ( $have * 2 < $days ? 'sparse' : 'ok' );
                $qtys[] = [ 'qty' => $qty, 'label' => naws_label( $entry['label'] ), 'state' => $state ];
            }
            $out[] = [ 'ref' => $ref, 'name' => self::module_label( $ref ), 'qtys' => $qtys ];
        }
        return $out;
    }

    /**
     * One series' daily values in the site's units, or ['error' => reason].
     *
     * @param array $parsed parse_series() result with ok = true.
     */
    public static function rows_for( array $parsed, string $from, string $to, bool $no_cache ): array {
        $group = self::group_of( $parsed['ref'] );
        $entry = self::series_catalogue()[ $group ][ $parsed['qty'] ];
        $m     = self::module_of( $parsed['ref'] );
        if ( $m === null || ( $m['module_type'] ?? '' ) !== $entry['type'] ) {
            return [ 'error' => 'unknown_module' ];
        }
        if ( empty( $m['is_active'] ) ) {
            return [ 'error' => 'inactive_module' ];
        }

        $row_id = $entry['row'] === 'own' ? (string) $m['module_id'] : NAWS_Calc::station_row_id( [] );
        if ( $row_id === null ) {
            return [ 'error' => 'unknown_module' ];
        }

        $rows   = NAWS_Database::get_daily_summaries( [
            'module_id' => $row_id,
            'date_from' => $from,
            'date_to'   => $to,
            'fields'    => [ $entry['col'] ],
            'group_by'  => 'day',
            'no_cache'  => $no_cache,
        ] );
        $values = [];
        foreach ( (array) $rows as $row ) {
            $v = $row[ $entry['col'] ] ?? null;
            if ( $v !== null && $v !== '' ) {
                $values[ (string) $row['day_date'] ] = (float) NAWS_Helpers::format_value( $entry['param'], (float) $v );
            }
        }
        if ( $values === [] ) {
            return [ 'error' => 'no_data' ];
        }
        return [
            'values'   => $values,
            'chart'    => $entry['chart'],
            'unit'     => (string) NAWS_Helpers::get_unit( $entry['param'] ),
            'decimals' => self::decimals( $entry['param'] ),
            'name'     => self::series_name( $parsed['ref'], $parsed['qty'] ),
        ];
    }

    /**
     * The whole block. Visitors get '' when nothing can be drawn; editors
     * see why, also next to a chart that lost one of its two series.
     *
     * @param array $atts     a, b, from, to, months, title, height (after shortcode_atts).
     * @param bool  $no_cache True for the admin preview.
     */
    public static function render( array $atts, bool $no_cache = false ): string {
        $today     = wp_date( 'Y-m-d' );
        $yesterday = gmdate( 'Y-m-d', strtotime( $today . ' 12:00:00 UTC' ) - self::DAY );
        $range     = NAWS_Database::get_daily_data_range();
        $first_day = ! empty( $range['date_begin'] ) ? (string) $range['date_begin'] : null;
        $period    = self::period( $atts, $first_day, $yesterday );

        $notices = '';
        $series  = [];
        foreach ( [ 'a', 'b' ] as $key ) {
            $spec = trim( (string) ( $atts[ $key ] ?? '' ) );
            if ( $spec === '' ) {
                continue;
            }
            $parsed = self::parse_series( $spec );
            $data   = ! $parsed['ok']
                ? [ 'error' => $parsed['error'] ]
                : ( $period['from'] === null ? [ 'error' => 'no_data' ] : self::rows_for( $parsed, $period['from'], $period['to'], $no_cache ) );
            if ( isset( $data['error'] ) ) {
                $notices .= NAWS_Records::notice( 'naws_compare', $data['error'], $spec );
                continue;
            }
            $series[] = $data;
        }
        if ( $series === [] ) {
            return $notices;
        }

        $built = self::build( $series, (string) $period['from'], $period['to'], $period['auto_from'], $period['auto_to'] );
        if ( $built['dates'] === [] ) {
            return $notices;
        }
        $axes = self::axes( $series[0]['unit'], isset( $series[1] ) ? $series[1]['unit'] : null );

        $palette = NAWS_Colors::get_history_palette();
        $out     = [];
        foreach ( $series as $i => $s ) {
            $out[] = [
                'name'     => $s['name'],
                'unit'     => $s['unit'],
                'chart'    => $s['chart'],
                'axis'     => $i === 0 ? $axes['a'] : $axes['b'],
                'decimals' => $s['decimals'],
                'values'   => $built['values'][ $i ],
            ];
        }
        $naws_cmp = [
            'dates'   => $built['dates'],
            'weekly'  => $built['weekly'],
            'series'  => $out,
            'right'   => $axes['right'],
            'locale'  => str_replace( '_', '-', get_locale() ),
            'decimal' => substr( number_format_i18n( 1.5, 1 ), 1, 1 ),
            'colors'  => [ $palette[0] ?? '#427272', $palette[1] ?? '#c08a3e' ],
            'theme'   => NAWS_Colors::get_chart_theme(),
            'labels'  => [ 'close' => naws_label( 'cmp_close' ) ],
        ];
        $naws_cmp_id     = wp_unique_id( 'naws-cmp-' );
        $naws_cmp_title  = trim( (string) ( $atts['title'] ?? '' ) );
        $naws_cmp_height = max( 200, min( 900, (int) ( $atts['height'] ?? 360 ) ?: 360 ) );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/compare.php';
        return $notices . ob_get_clean();
    }
}
