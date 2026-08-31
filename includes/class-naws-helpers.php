<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns the wp_kses() allowlist for SVG output.
 * Use with: echo wp_kses( $svg, naws_svg_kses_args() );
 *
 * @return array<string, array<string, bool>>
 */
function naws_svg_kses_args(): array {
    static $allowed = null;
    if ( null === $allowed ) {
        $allowed = [
            'svg'      => [ 'xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true,
                            'fill' => true, 'stroke' => true, 'stroke-width' => true,
                            'stroke-linecap' => true, 'stroke-linejoin' => true ],
            'path'     => [ 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true,
                            'stroke-linecap' => true, 'stroke-linejoin' => true, 'opacity' => true ],
            'circle'   => [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true,
                            'stroke' => true, 'stroke-width' => true, 'opacity' => true ],
            'line'     => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
                            'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ],
            'polyline' => [ 'points' => true, 'fill' => true, 'stroke' => true,
                            'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ],
            'polygon'  => [ 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ],
            'g'        => [ 'fill' => true, 'stroke' => true, 'stroke-width' => true,
                            'stroke-linecap' => true, 'stroke-linejoin' => true, 'opacity' => true ],
        ];
    }
    return $allowed;
}

/**
 * Sanitize SVG markup using wp_kses() with a strict allowlist.
 *
 * @param  string $svg  Raw SVG markup.
 * @return string       Sanitized SVG markup safe for output via wp_kses().
 */
function naws_kses_svg( string $svg ): string {
    return wp_kses( $svg, naws_svg_kses_args() );
}

/**
 * The timezone all local day boundaries and hour-of-day checks are based on.
 *
 * This is the site timezone, matching what wp_date() already formats with.
 * Everything in this plugin that turns a calendar date into a timestamp — daily
 * summaries, the importer, night mode — has to agree on one zone, or the day
 * boundaries drift apart between components.
 *
 * @return DateTimeZone
 */
function naws_timezone(): DateTimeZone {
    return wp_timezone();
}

class NAWS_Helpers {

    public static function get_label( $parameter ) {
        $labels = [
            'Temperature'       => __( 'Temperature', 'xtx-integration-for-netatmo' ),
            'CO2'               => __( 'CO₂ Concentration', 'xtx-integration-for-netatmo' ),
            'Humidity'          => _x( 'Humidity', 'param_humidity', 'xtx-integration-for-netatmo' ),
            'Noise'             => __( 'Noise Level', 'xtx-integration-for-netatmo' ),
            'Pressure'          => __( 'Pressure (relative)', 'xtx-integration-for-netatmo' ),
            'AbsolutePressure'  => __( 'Pressure (absolute)', 'xtx-integration-for-netatmo' ),
            'Rain'              => __( 'Total last hour', 'xtx-integration-for-netatmo' ),
            'sum_rain_1'        => __( 'Total last hour', 'xtx-integration-for-netatmo' ),
            'sum_rain_24'       => __( 'Total last 24h', 'xtx-integration-for-netatmo' ),
            'WindStrength'      => __( 'Wind Speed', 'xtx-integration-for-netatmo' ),
            'WindAngle'         => __( 'Wind Direction (compass)', 'xtx-integration-for-netatmo' ),
            'GustStrength'      => __( 'Gust Speed', 'xtx-integration-for-netatmo' ),
            'GustAngle'         => __( 'Gust Direction', 'xtx-integration-for-netatmo' ),
            'min_temp'          => __( 'Min Temperature (day)', 'xtx-integration-for-netatmo' ),
            'max_temp'          => __( 'Max Temperature (day)', 'xtx-integration-for-netatmo' ),
            'date_min_temp'     => __( 'Min Temperature (day)', 'xtx-integration-for-netatmo' ),
            'date_max_temp'     => __( 'Max Temperature (day)', 'xtx-integration-for-netatmo' ),
            'health_idx'        => 'Health Index',
        ];
        return $labels[ $parameter ] ?? ucfirst( str_replace( '_', ' ', $parameter ) );
    }

    public static function get_icon( $parameter ) {
        // Map parameter names to icon set keys
        $param_to_key = [
            'Temperature'      => 'temp',
            'CO2'              => 'co2',
            'Humidity'         => 'humid',
            'Noise'            => 'noise',
            'Pressure'         => 'press',
            'AbsolutePressure' => 'press',
            'Rain'             => 'rain',
            'sum_rain_1'       => 'rain',
            'sum_rain_24'      => 'rain',
            'WindStrength'     => 'wind',
            'WindAngle'        => 'wind',
            'GustStrength'     => 'wind',
            'GustAngle'        => 'wind',
            'health_idx'       => 'temp',
        ];

        if ( class_exists( 'NAWS_Icons' ) ) {
            $key = $param_to_key[ $parameter ] ?? null;
            if ( $key ) {
                $icons = NAWS_Icons::get_set();
                return $icons[ $key ] ?? '📊';
            }
        }

        // Fallback to emojis if NAWS_Icons not loaded
        $emoji_fallback = [
            'Temperature' => '🌡️', 'CO2' => '💨', 'Humidity' => '💧',
            'Noise' => '🔊', 'Pressure' => '⏲️', 'AbsolutePressure' => '⏲️',
            'Rain' => '🌧️', 'sum_rain_1' => '🌧️', 'sum_rain_24' => '🌧️',
            'WindStrength' => '🌬️', 'WindAngle' => '🧭',
            'GustStrength' => '🌪️', 'GustAngle' => '🧭', 'health_idx' => '❤️',
        ];
        return $emoji_fallback[ $parameter ] ?? '📊';
    }

    public static function get_css_class( $parameter ) {
        $classes = [
            'Temperature'      => 'naws-temp',
            'CO2'              => 'naws-co2',
            'Humidity'         => 'naws-humidity',
            'Noise'            => 'naws-noise',
            'Pressure'         => 'naws-pressure',
            'AbsolutePressure' => 'naws-pressure',
            'Rain'             => 'naws-rain',
            'sum_rain_1'       => 'naws-rain',
            'sum_rain_24'      => 'naws-rain',
            'WindStrength'     => 'naws-wind',
            'GustStrength'     => 'naws-wind',
            'health_idx'       => 'naws-health',
        ];
        return $classes[ $parameter ] ?? 'naws-other';
    }

    /**
     * Puts a list of ids into a saved order.
     *
     * The order comes from an option the user arranged by hand; the ids come
     * from what actually exists right now. The two drift apart on their own:
     * an indoor module gets renamed or removed and takes its ids with it, a
     * new one appears, an update adds a card that was not there at the last
     * save. So the order decides position only — never membership. Ids the
     * order does not mention keep their original relative order at the end,
     * which is what makes a new module show up instead of quietly vanishing.
     *
     * @param array<int,string> $ids   Ids that exist, in their default order.
     * @param array<int,string> $order Saved order; unknown entries ignored.
     * @return array<int,string> Every id from $ids exactly once.
     */
    public static function apply_order( array $ids, array $order ): array {
        $sorted = [];
        foreach ( $order as $id ) {
            if ( in_array( $id, $ids, true ) && ! in_array( $id, $sorted, true ) ) {
                $sorted[] = $id;
            }
        }
        foreach ( $ids as $id ) {
            if ( ! in_array( $id, $sorted, true ) ) {
                $sorted[] = $id;
            }
        }
        return $sorted;
    }

    /**
     * Cleans a submitted sort order before it is stored.
     *
     * Deliberately not sanitize_key(): that lowercases, and half of these ids
     * are Netatmo parameter names — Temperature, WindStrength, CO2. Folded to
     * lowercase they would never match a card again, and sorting would
     * silently stop working. Letters, digits, underscore and hyphen are
     * exactly what an id is made of, so everything else goes.
     *
     * @param array<mixed> $order Raw ids as submitted.
     * @return array<int,string>
     */
    public static function sanitize_order( array $order ): array {
        $clean = [];

        foreach ( $order as $id ) {
            if ( ! is_scalar( $id ) ) {
                continue;
            }
            $id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id );
            if ( $id !== '' ) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    /**
     * Reorders a list of definitions by the order stored in an option.
     *
     * @param array<int,array<string,mixed>> $defs   Definitions with an id key.
     * @param string                         $option Option holding the order.
     * @return array<int,array<string,mixed>>
     */
    private static function ordered_by_option( array $defs, string $option ): array {
        $by_id = array_column( $defs, null, 'id' );
        $order = self::apply_order( array_keys( $by_id ), (array) get_option( $option, [] ) );

        return array_values( array_map(
            static function ( $id ) use ( $by_id ) { return $by_id[ $id ]; },
            $order
        ) );
    }

    /**
     * Every yearly-comparison chart, in the order the front end draws it.
     *
     * Five fixed charts for the station itself, then two per indoor module.
     * The history template and the settings screen read this one list, so
     * the switches there and the charts here can never disagree about which
     * charts exist or what they are called.
     *
     * @return array<int,array<string,string>> id, label, icon.
     */
    public static function history_chart_defs(): array {
        $defs = [
            [ 'id' => 'temp_minmax', 'label' => __( 'Temperature Min / Max', 'xtx-integration-for-netatmo' ), 'icon' => '🌡️' ],
            [ 'id' => 'temp_avg',    'label' => __( 'Annual Average Temperature', 'xtx-integration-for-netatmo' ),    'icon' => '🌡️' ],
            [ 'id' => 'pressure',    'label' => __( 'Pressure (Annual Mean)', 'xtx-integration-for-netatmo' ),    'icon' => '🔵' ],
            [ 'id' => 'rain',        'label' => __( 'Annual Precipitation', 'xtx-integration-for-netatmo' ),        'icon' => '🌧️' ],
            [ 'id' => 'humidity',    'label' => __( 'Outdoor Humidity (Annual Mean)', 'xtx-integration-for-netatmo' ),    'icon' => '💧' ],
        ];

        foreach ( self::indoor_chart_defs() as $indoor ) {
            $defs[] = [
                'id'    => $indoor['id'],
                'label' => $indoor['label'],
                'icon'  => $indoor['icon'],
            ];
        }

        return self::ordered_by_option( $defs, 'naws_history_chart_order' );
    }

    /**
     * Which readings belong to which module.
     *
     * Switching a module off hides everything it measures, so this is what
     * the master toggle expands to. The live template used to carry its own
     * copy of this list, and that copy was missing Humidity_indoor: turning
     * the base station off hid all of its readings except that one, which
     * stayed on the dashboard with nothing around it.
     *
     * Not every entry is a card — AbsolutePressure and the gust readings
     * feed cards rather than being one. They belong here anyway: this maps
     * readings, and hiding a module has to hide the readings a card falls
     * back to as well.
     *
     * @return array<string,array<int,string>> module type => parameter keys.
     */
    public static function module_param_map(): array {
        $map = [
            'NAMain'    => [ 'Temperature_indoor', 'Humidity_indoor', 'Pressure', 'AbsolutePressure', 'CO2', 'Noise' ],
            'NAModule1' => [ 'Temperature', 'min_temp', 'max_temp', 'Humidity' ],
            'NAModule2' => [ 'WindStrength', 'GustStrength', 'WindAngle', 'GustAngle' ],
            'NAModule3' => [ 'Rain', 'sum_rain_1', 'sum_rain_24' ],
        ];

        foreach ( self::indoor_module_slugs() as $module ) {
            $slug = $module['slug'];
            $map[ 'NAModule4_' . $slug ] = [
                'Temperature_' . $slug,
                'Humidity_' . $slug,
                'CO2_' . $slug,
                'Noise_' . $slug,
            ];
        }

        return $map;
    }

    /**
     * Every card the live dashboard draws, in the order it draws them.
     *
     * A card is not the same thing as a reading. The absolute pressure only
     * stands in when the relative one is missing, the gust lives inside the
     * wind gauge, and the rain sums are sub-rows of the rain card — none of
     * them is a box of its own, so none of them can be dragged. Listing them
     * would offer the user positions that do not exist on the page.
     *
     * Label and group stay apart: the label is what the card is called in
     * the front end, the group says where it comes from. Temperature exists
     * outdoors, in the base station and in every indoor module, and the
     * sorting list has to tell those three apart.
     *
     * `stands_in_for` names a card this one only appears in place of. The
     * daily minimum and maximum are sub-rows of the temperature card and move
     * with it; buildLive() promotes them to cards of their own only when the
     * temperature card is switched off. Listing them alongside it would offer
     * two positions for one box.
     *
     * @return array<int,array<string,string>> id, stands_in_for, module,
     *         label, group.
     */
    public static function live_card_defs(): array {
        $defs = [
            [ 'id' => 'Temperature',        'stands_in_for' => '', 'module' => 'NAModule1', 'label' => __( 'Temperature', 'xtx-integration-for-netatmo' ), 'group' => __( 'Outdoor', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'min_temp',           'stands_in_for' => 'Temperature', 'module' => 'NAModule1', 'label' => __( 'Temp. Min', 'xtx-integration-for-netatmo' ),    'group' => __( 'Outdoor', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'max_temp',           'stands_in_for' => 'Temperature', 'module' => 'NAModule1', 'label' => __( 'Temp. Max', 'xtx-integration-for-netatmo' ),    'group' => __( 'Outdoor', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'Humidity',           'stands_in_for' => '', 'module' => 'NAModule1', 'label' => _x( 'Humidity', 'card_humidity', 'xtx-integration-for-netatmo' ),    'group' => __( 'Outdoor', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'Pressure',           'stands_in_for' => '', 'module' => 'NAMain',    'label' => __( 'Air Pressure', 'xtx-integration-for-netatmo' ),    'group' => __( 'Base', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'CO2',                'stands_in_for' => '', 'module' => 'NAMain',    'label' => __( 'CO₂', 'xtx-integration-for-netatmo' ),         'group' => __( 'Base', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'Noise',              'stands_in_for' => '', 'module' => 'NAMain',    'label' => _x( 'Noise', 'card_noise', 'xtx-integration-for-netatmo' ),       'group' => __( 'Base', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'Temperature_indoor', 'stands_in_for' => '', 'module' => 'NAMain',    'label' => __( 'Temperature', 'xtx-integration-for-netatmo' ), 'group' => __( 'Base', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'Humidity_indoor',    'stands_in_for' => '', 'module' => 'NAMain',    'label' => _x( 'Humidity', 'card_humidity', 'xtx-integration-for-netatmo' ),    'group' => __( 'Base', 'xtx-integration-for-netatmo' ) ],
            [ 'id' => 'Rain',               'stands_in_for' => '', 'module' => 'NAModule3', 'label' => __( 'Precipitation', 'xtx-integration-for-netatmo' ),        'group' => '' ],
            [ 'id' => 'WindStrength',       'stands_in_for' => '', 'module' => 'NAModule2', 'label' => __( 'Wind &amp; Gusts', 'xtx-integration-for-netatmo' ),  'group' => '' ],
            [ 'id' => 'WindAngle',          'stands_in_for' => '', 'module' => 'NAModule2', 'label' => __( 'Wind Direction', 'xtx-integration-for-netatmo' ),    'group' => '' ],
        ];

        // Four cards per indoor module, keyed the way buildLive() keys them.
        foreach ( self::indoor_module_slugs() as $module ) {
            [ 'slug' => $slug, 'name' => $name ] = $module;
            foreach ( [
                [ 'Temperature_', 'card_temperature' ],
                [ 'Humidity_',    'card_humidity' ],
                [ 'CO2_',         'card_co2' ],
                [ 'Noise_',       'card_noise' ],
            ] as [ $prefix, $lang_key ] ) {
                $defs[] = [
                    'id'            => $prefix . $slug,
                    'stands_in_for' => '',
                    'module'        => 'NAModule4_' . $slug,
                    'label'         => naws_label( $lang_key ),
                    'group'         => $name,
                ];
            }
        }

        // The front end puts these labels into markup, so some carry an HTML
        // entity. The settings screen escapes on its own and would show the
        // entity twice over; hand it plain text and let it do that.
        foreach ( $defs as &$def ) {
            $def['label'] = html_entity_decode( $def['label'], ENT_QUOTES, 'UTF-8' );
        }
        unset( $def );

        return self::ordered_by_option( $defs, 'naws_live_card_order' );
    }

    /**
     * The indoor modules, keyed by the slug their ids are built from.
     *
     * The slug turns a module name into something that survives being part
     * of an id and a param key: lowercase, letters and digits only, capped
     * at sixteen characters, falling back to the tail of the MAC when a name
     * has nothing usable in it at all. Every place that names an indoor
     * module — charts, live cards, param keys — has to derive it the same
     * way, or the switches stop matching the things they switch.
     *
     * Two modules sharing a name is a configuration mistake on the user's
     * side, and it does collapse their ids. This returns a list rather than
     * a map so it does not quietly drop the second one on top of that —
     * whatever the callers make of the collision, they see both modules.
     *
     * @return array<int,array<string,string>> slug, name, module_id.
     */
    public static function indoor_module_slugs(): array {
        $slugs = [];

        foreach ( NAWS_Database::get_modules( true ) as $module ) {
            if ( $module['module_type'] !== 'NAModule4' ) {
                continue;
            }

            $slug = preg_replace( '/[^a-z0-9]/', '', strtolower( $module['module_name'] ) );
            if ( $slug === '' ) {
                $slug = 'indoor' . substr( str_replace( ':', '', $module['module_id'] ), -4 );
            }

            $slugs[] = [
                'slug'      => substr( $slug, 0, 16 ),
                'name'      => $module['module_name'],
                'module_id' => $module['module_id'],
            ];
        }

        return $slugs;
    }

    /**
     * Yearly-comparison chart definitions for the indoor modules.
     *
     * An NAModule4 does not store its readings in the temp_* columns — those
     * carry the station's outdoor values — but in indoor_temp_avg and
     * indoor_humidity_avg, on its own row per day. So each indoor module
     * needs its own pair of charts, and the history template and the
     * visibility switches in the settings both need the same list. They used
     * to build it twice, each with its own copy of the slug rule.
     *
     * The temperature chart comes first: it is what the module is mainly
     * for. The humidity id is unchanged from when it was the only one, so a
     * chart somebody switched off stays switched off.
     *
     * @return array<int,array<string,string>> id, module_id, module_name,
     *         field, param, label, icon, unit.
     */
    public static function indoor_chart_defs(): array {
        $defs = [];

        foreach ( self::indoor_module_slugs() as $module ) {
            foreach ( [
                [ 'indoor_temp_',     'indoor_temp_avg',     'Temperature', 'param_temperature', '🌡️' ],
                [ 'indoor_humidity_', 'indoor_humidity_avg', 'Humidity',    'param_humidity',    '💧' ],
            ] as [ $prefix, $field, $param, $lang_key, $icon ] ) {
                $defs[] = [
                    'id'          => $prefix . $module['slug'],
                    'module_id'   => $module['module_id'],
                    'module_name' => $module['name'],
                    'field'       => $field,
                    'param'       => $param,
                    'label'       => $module['name'] . ' – ' . naws_label( $lang_key ),
                    'icon'        => $icon,
                    'unit'        => self::get_unit( $param ),
                ];
            }
        }

        return $defs;
    }

    public static function get_unit( $parameter ) {
        $options  = get_option( 'naws_settings', [] );
        $temp_u   = $options['temperature_unit'] ?? 'C';
        $wind_u   = $options['wind_unit']        ?? 'kmh';
        $press_u  = $options['pressure_unit']    ?? 'mbar';
        $rain_u   = $options['rain_unit']        ?? 'mm';

        $temp_label = $temp_u === 'F' ? '°F' : '°C';
        $units = [
            'Temperature'      => $temp_label,
            'min_temp'         => $temp_label,
            'max_temp'         => $temp_label,
            'date_min_temp'    => $temp_label,
            'date_max_temp'    => $temp_label,
            'CO2'              => 'ppm',
            'Humidity'         => '%',
            'Noise'            => 'dB',
            'Pressure'         => $press_u === 'inHg' ? 'inHg' : ( $press_u === 'mmHg' ? 'mmHg' : 'mbar' ),
            'AbsolutePressure' => $press_u === 'inHg' ? 'inHg' : ( $press_u === 'mmHg' ? 'mmHg' : 'mbar' ),
            'Rain'             => $rain_u === 'in' ? 'in' : 'mm',
            'sum_rain_1'       => $rain_u === 'in' ? 'in' : 'mm',
            'sum_rain_24'      => $rain_u === 'in' ? 'in' : 'mm',
            'WindStrength'     => self::wind_unit_label( $wind_u ),
            'GustStrength'     => self::wind_unit_label( $wind_u ),
            'WindAngle'        => '°',
            'GustAngle'        => '°',
            'health_idx'       => '',
        ];
        return $units[ $parameter ] ?? '';
    }

    public static function wind_unit_label_public( $unit ) {
        return self::wind_unit_label( $unit );
    }

    private static function wind_unit_label( $unit ) {
        $labels = [ 'kmh' => 'km/h', 'ms' => 'm/s', 'mph' => 'mph', 'kn' => 'kn' ];
        return $labels[ $unit ] ?? 'km/h';
    }

    public static function format_value( $parameter, $value ) {
        $options = get_option( 'naws_settings', [] );

        $temp_params = [ 'Temperature', 'min_temp', 'max_temp', 'date_min_temp', 'date_max_temp' ];
        if ( in_array( $parameter, $temp_params, true ) ) {
            if ( ( $options['temperature_unit'] ?? 'C' ) === 'F' ) {
                $value = $value * 9 / 5 + 32;
            }
            return round( $value, 1 );
        }

        if ( in_array( $parameter, [ 'Rain', 'sum_rain_1', 'sum_rain_24' ], true ) ) {
            if ( ( $options['rain_unit'] ?? 'mm' ) === 'in' ) {
                return round( $value / 25.4, 3 );
            }
            return round( $value, 1 );
        }

        if ( in_array( $parameter, [ 'Pressure', 'AbsolutePressure' ], true ) ) {
            $unit = $options['pressure_unit'] ?? 'mbar';
            if ( $unit === 'inHg' ) return round( $value * 0.02953, 2 );
            if ( $unit === 'mmHg' ) return round( $value * 0.75006, 1 );
            return round( $value, 1 );
        }

        if ( in_array( $parameter, [ 'WindStrength', 'GustStrength' ], true ) ) {
            $unit = $options['wind_unit'] ?? 'kmh';
            if ( $unit === 'ms' )  return round( $value / 3.6, 1 );
            if ( $unit === 'mph' ) return round( $value * 0.62137, 1 );
            if ( $unit === 'kn' )  return round( $value * 0.53996, 1 );
            return round( $value, 1 );
        }

        if ( $parameter === 'CO2' )      return intval( $value );
        if ( $parameter === 'Noise' )    return intval( $value );
        if ( $parameter === 'Humidity' ) return intval( $value );

        return round( $value, 2 );
    }

    public static function get_co2_level( $ppm ) {
        if ( $ppm < 800 )  return [ 'level' => 'excellent', 'color' => '#10b981', 'label' => __( 'Excellent', 'xtx-integration-for-netatmo' ) ];
        if ( $ppm < 1000 ) return [ 'level' => 'good',      'color' => '#84cc16', 'label' => __( 'Good', 'xtx-integration-for-netatmo' ) ];
        if ( $ppm < 1500 ) return [ 'level' => 'fair',      'color' => '#f59e0b', 'label' => _x( 'Fair', 'co2_fair', 'xtx-integration-for-netatmo' ) ];
        if ( $ppm < 2000 ) return [ 'level' => 'poor',      'color' => '#f97316', 'label' => __( 'Poor', 'xtx-integration-for-netatmo' ) ];
        return                    [ 'level' => 'unhealthy',  'color' => '#ef4444', 'label' => __( 'Unhealthy', 'xtx-integration-for-netatmo' ) ];
    }

    public static function degrees_to_compass( $deg ) {
        $directions = [ 'N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW' ];
        return $directions[ round( $deg / 22.5 ) % 16 ];
    }

    public static function get_all_parameters() {
        return [
            'Temperature'      => __( 'Temperature', 'xtx-integration-for-netatmo' ),
            'CO2'              => __( 'CO₂ Concentration', 'xtx-integration-for-netatmo' ),
            'Humidity'         => _x( 'Humidity', 'param_humidity', 'xtx-integration-for-netatmo' ),
            'Noise'            => __( 'Noise Level', 'xtx-integration-for-netatmo' ),
            'Pressure'         => __( 'Pressure (relative)', 'xtx-integration-for-netatmo' ),
            'AbsolutePressure' => __( 'Pressure (absolute)', 'xtx-integration-for-netatmo' ),
            'Rain'             => __( 'Total last hour', 'xtx-integration-for-netatmo' ),
            'sum_rain_1'       => __( 'Total last hour', 'xtx-integration-for-netatmo' ),
            'sum_rain_24'      => __( 'Total last 24h', 'xtx-integration-for-netatmo' ),
            'min_temp'         => __( 'Min Temperature (day)', 'xtx-integration-for-netatmo' ),
            'max_temp'         => __( 'Max Temperature (day)', 'xtx-integration-for-netatmo' ),
            'WindStrength'     => __( 'Wind Speed', 'xtx-integration-for-netatmo' ),
            'WindAngle'        => __( 'Wind Direction (compass)', 'xtx-integration-for-netatmo' ),
            'GustStrength'     => __( 'Gust Speed', 'xtx-integration-for-netatmo' ),
            'GustAngle'        => __( 'Gust Direction', 'xtx-integration-for-netatmo' ),
            'health_idx'       => 'Health Index',
        ];
    }

    public static function module_type_label( $type ) {
        $labels = [
            'NAMain'    => __( 'Base station (Indoor)', 'xtx-integration-for-netatmo' ),
            'NAModule1' => __( 'Outdoor Module', 'xtx-integration-for-netatmo' ),
            'NAModule2' => __( 'Wind Gauge', 'xtx-integration-for-netatmo' ),
            'NAModule3' => __( 'Rain Gauge', 'xtx-integration-for-netatmo' ),
            'NAModule4' => __( 'Indoor module (NAModule4)', 'xtx-integration-for-netatmo' ),
            'NHC'       => 'Home Coach',
        ];
        return $labels[ $type ] ?? $type;
    }
    /**
     * Start of a period written the way the shortcodes document it.
     *
     * The reference tells people to write 24h, 7d, 30d, and PHP does not
     * read those as durations at all: strtotime( '-24h' ) lands tomorrow
     * rather than yesterday and strtotime( '-365d' ) fails outright. Both
     * put the start of the range behind its end, which returns nothing and
     * looks like a station with no data. The shorthand is therefore spelled
     * out here before it reaches the parser.
     *
     * A bare "m" is deliberately not a unit: it reads as either minutes or
     * months depending on who is looking, and neither guess is safe. Write
     * "months" for months.
     *
     * Anything strtotime does understand still works. A value it cannot
     * place, or one that would start in the future, falls back to the
     * documented default of 24 hours rather than to the epoch: a range
     * beginning at 0 quietly returns the oldest rows instead of the newest.
     */
    public static function period_start( string $period, ?int $now = null ): int {
        $now    = $now ?? time();
        $period = strtolower( trim( ltrim( trim( $period ), '-' ) ) );

        $units = [
            'h' => 'hours',  'hour'  => 'hours',  'hours'  => 'hours',
            'd' => 'days',   'day'   => 'days',   'days'   => 'days',
            'w' => 'weeks',  'week'  => 'weeks',  'weeks'  => 'weeks',
            'y' => 'years',  'year'  => 'years',  'years'  => 'years',
            'month' => 'months', 'months' => 'months',
        ];

        if ( preg_match( '/^(\d+)\s*([a-z]+)$/', $period, $parts ) && isset( $units[ $parts[2] ] ) ) {
            $ts = strtotime( '-' . $parts[1] . ' ' . $units[ $parts[2] ], $now );
            if ( $ts !== false && $ts <= $now ) return $ts;
        }

        $ts = strtotime( '-' . $period, $now );
        if ( $ts !== false && $ts <= $now ) return $ts;

        return (int) strtotime( '-24 hours', $now );
    }
    /**
     * The format a locale writes a clock time in.
     *
     * German appends "Uhr" after the time, English appends nothing — which
     * leaves the English format as the bare placeholder. There is no English
     * word here to translate, so the string has to look like this, and the
     * i18n sniff is answered once here instead of at every call site.
     */
    public static function clock_format(): string {
        /* translators: %s is a clock time such as 06:12. Add a suffix if your language uses one; German renders "06:12 Uhr". */
        return _x( '%s', 'clock time', 'xtx-integration-for-netatmo' ); // phpcs:ignore WordPress.WP.I18n.NoEmptyStrings -- the English format is a bare placeholder by nature, see the docblock above.
    }

    /** A clock time rendered the way the locale writes it. */
    public static function clock_time( string $time ): string {
        return sprintf( self::clock_format(), $time );
    }
}
