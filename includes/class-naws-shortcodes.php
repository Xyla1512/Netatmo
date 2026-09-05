<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing
if ( ! defined( 'ABSPATH' ) ) exit;

require_once NAWS_PLUGIN_DIR . 'includes/class-naws-helpers.php';

class NAWS_Shortcodes {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'naws_current',   [ $this, 'sc_current' ] );
        add_shortcode( 'naws_table',     [ $this, 'sc_table' ] );
        add_shortcode( 'naws_history',   [ $this, 'sc_history' ] );
        add_shortcode( 'naws_heatmap',   [ $this, 'sc_heatmap' ] );
        add_shortcode( 'naws_records',     [ $this, 'sc_records' ] );
        add_shortcode( 'naws_on_this_day', [ $this, 'sc_on_this_day' ] );
        add_shortcode( 'naws_sunpath',     [ $this, 'sc_sunpath' ] );
        add_shortcode( 'naws_live',      [ $this, 'sc_live' ] );
        add_shortcode( 'naws_infobar',   [ $this, 'sc_infobar' ] );
        add_shortcode( 'naws_value',     [ $this, 'sc_value' ] );
        add_shortcode( 'naws_calc',      [ $this, 'sc_calc' ] );
        add_shortcode( 'naws_forecast',  [ $this, 'sc_forecast' ] );
        add_shortcode( 'naws_weather_icon', [ $this, 'sc_weather_icon' ] );
        add_shortcode( 'naws_weather_widget', [ $this, 'sc_weather_widget' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    public function enqueue_frontend_assets() {
        // Register scripts/styles (not enqueued yet – done per-shortcode)
        wp_register_style(  'naws-frontend', NAWS_PLUGIN_URL . 'assets/css/frontend.css', [], NAWS_VERSION );
        wp_register_script( 'naws-chartjs',
            NAWS_PLUGIN_URL . 'assets/vendor/chart.umd.min.js',
            [], '4.5.1', true );
        wp_register_script( 'naws-chartjs-adapter',
            NAWS_PLUGIN_URL . 'assets/vendor/chartjs-adapter-date-fns.bundle.min.js',
            [ 'naws-chartjs' ], '3.0.0', true );
        wp_register_script( 'naws-frontend',
            NAWS_PLUGIN_URL . 'assets/js/frontend.js',
            [ 'jquery','naws-chartjs','naws-chartjs-adapter' ], NAWS_VERSION, true );

        // Boot routines for the two chart-bearing shortcodes. Until 1.9.5 both
        // were printed into the page as inline <script> blocks on wp_footer,
        // because wp_add_inline_script() turned out to be silently dropped on
        // some installations and left every chart blank. A registered file
        // cannot be dropped that way, and it keeps the plugin free of inline
        // script output. Each file locates its own payload through the
        // <script type="application/json" data-naws="…"> element the template
        // prints, so any number of shortcodes on a page share one copy.
        wp_register_script( 'naws-live-boot',
            NAWS_PLUGIN_URL . 'assets/js/live-boot.js',
            [ 'naws-frontend' ], NAWS_VERSION, true );
        wp_register_script( 'naws-history-boot',
            NAWS_PLUGIN_URL . 'assets/js/history-boot.js',
            [ 'naws-frontend', 'naws-chartjs-adapter' ], NAWS_VERSION, true );
        wp_register_script( 'naws-heatmap-boot',
            NAWS_PLUGIN_URL . 'assets/js/heatmap-boot.js',
            [ 'naws-frontend' ], NAWS_VERSION, true );
    }

    private function enqueue_frontend() {
        $this->enqueue_frontend_styles();
        wp_enqueue_script( 'naws-frontend' );

        // Inject config once via wp_add_inline_script (more reliable than wp_localize_script)
        static $localized = false;
        if ( ! $localized ) {
            wp_add_inline_script( 'naws-frontend',
                'var nawsFrontend = ' . wp_json_encode( [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'naws_public_nonce' ),
                    'options'  => get_option( 'naws_settings', [] ),
                    // The three sentences frontend.js shows when something
                    // fails. They stood in the script itself, in German,
                    // which made them the only strings in the plugin that no
                    // translation could reach - an English visitor read them
                    // in German and no setting changed that. Migrating the
                    // PHP to gettext did not move them, because they were
                    // never in the PHP.
                    //
                    // They travel with this config rather than through a
                    // second mechanism, because it is already injected on
                    // exactly the pages that load the script.
                    'i18n'     => [
                        'js_chart_failed'   => __( 'Chart could not be rendered.', 'xtx-integration-for-netatmo' ),
                        'js_no_data_period' => __( 'No data for this period.', 'xtx-integration-for-netatmo' ),
                        /* translators: %s is the HTTP status code the request came back with. */
                        'js_load_failed'    => __( 'Could not load data (HTTP %s)', 'xtx-integration-for-netatmo' ),
                    ],
                ] ) . ';',
                'before'
            );
            $localized = true;
        }
    }

    /**
     * Style-only counterpart to enqueue_frontend().
     *
     * Used by shortcodes that render no chart and need no JS at all (the
     * sidebar widget and the single weather icon), so a page carrying only
     * those never pulls in jquery/Chart.js/frontend.js. The colour-CSS
     * guard is shared with enqueue_frontend() (not duplicated) so a page
     * that combines a full dashboard shortcode with the widget/icon still
     * gets the inline CSS variables exactly once, regardless of which
     * shortcode runs first.
     */
    private function enqueue_frontend_styles() {
        wp_enqueue_style( 'naws-frontend' );

        static $css_injected = false;
        if ( ! $css_injected ) {
            wp_add_inline_style( 'naws-frontend', NAWS_Colors::get_inline_css() );
            $css_injected = true;
        }
    }

    // ----------------------------------------------------------------
    // [naws_current module_id="" parameters="" layout="grid|list"]
    // ----------------------------------------------------------------
    public function sc_current( $atts ) {
        $this->enqueue_frontend();

        $atts = shortcode_atts( [
            'module_id'  => '',
            'parameters' => '',
            'layout'     => 'grid',
            'title'      => '',
            'animate'    => 'true',
        ], $atts, 'naws_current' );

        $modules  = NAWS_Database::get_modules();
        $latest   = NAWS_Database::get_latest_readings( $atts['module_id'] ?: null );
        $filter   = $atts['parameters'] ? explode( ',', str_replace( ' ', '', $atts['parameters'] ) ) : [];

        $readings_by_module = [];
        foreach ( $latest as $r ) {
            if ( $filter && ! in_array( $r['parameter'], $filter, true ) ) continue;
            $readings_by_module[ $r['module_id'] ][ $r['parameter'] ] = [
                'raw'      => $r['value'],
                'value'    => NAWS_Helpers::format_value( $r['parameter'], floatval( $r['value'] ) ),
                'unit'     => NAWS_Helpers::get_unit( $r['parameter'] ),
                'icon'     => NAWS_Helpers::get_icon( $r['parameter'] ),
                'label'    => NAWS_Helpers::get_label( $r['parameter'] ),
                'css_class'=> NAWS_Helpers::get_css_class( $r['parameter'] ),
                'time'     => $r['recorded_at'],
            ];
        }

        $module_map = [];
        foreach ( $modules as $m ) {
            $module_map[ $m['module_id'] ] = $m;
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/current.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // ----------------------------------------------------------------
    // ----------------------------------------------------------------
    // [naws_table module_id="" period="24h" parameters="Temperature,Humidity"]
    // ----------------------------------------------------------------
    public function sc_table( $atts ) {
        $this->enqueue_frontend();

        $atts = shortcode_atts( [
            'module_id'  => '',
            'parameters' => '',
            'period'     => '24h',
            'limit'      => '100',
            'group_by'   => 'hour',
            'title'      => '',
        ], $atts, 'naws_table' );

        $date_from = NAWS_Helpers::period_start( $atts['period'] );
        // Without an explicit list, ask only for what the table can present.
        // Netatmo also stores bookkeeping values — max_wind_angle and
        // max_wind_str carry neither a name nor a unit — and those would sit
        // in the table as raw keys next to a bare number. Restricting the
        // query rather than the output also keeps 'limit' honest: it counts
        // rows the reader actually gets. Naming one explicitly still returns
        // it, because an explicit request beats a default.
        $filter = $atts['parameters']
            ? explode( ',', str_replace( ' ', '', $atts['parameters'] ) )
            : array_keys( NAWS_Helpers::get_all_parameters() );

        $readings = NAWS_Database::get_readings( [
            'module_id' => $atts['module_id'] ?: null,
            'parameter' => $filter,
            'date_from' => $date_from,
            'date_to'   => time(),
            'group_by'  => $atts['group_by'],
            'limit'     => intval( $atts['limit'] ),
        ] );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/table.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_history module_id="" fields="temp_min,temp_max,temp_avg,pressure_avg,rain_sum"
    //               date_from="2020-01-01" date_to="" group_by="day|week|month|year"
    //               title="" theme="dark" height="400" show_range_picker="true"]
    // ----------------------------------------------------------------
    public function sc_history( $atts ) {
        $this->enqueue_frontend();
        wp_enqueue_script( 'naws-chartjs-adapter' );
        wp_enqueue_script( 'naws-history-boot' );

        $atts = shortcode_atts( [
            'module_id'         => '',
            'fields'            => 'temp_min,temp_max,temp_avg',
            'date_from'         => '',
            'date_to'           => '',
            'group_by'          => 'day',
            'title'             => __( 'Historical Weather Data', 'xtx-integration-for-netatmo' ),
            'height'            => '420',
            'show_range_picker' => 'true',
            'year'              => '',
        ], $atts, 'naws_history' );

        $chart_id   = 'naws-hist-' . wp_unique_id();
        $fields     = array_map( 'trim', explode( ',', $atts['fields'] ) );
        $show_picker = $atts['show_range_picker'] !== 'false';

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/history.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_heatmap year="" title="" legend="yes"]
    // ----------------------------------------------------------------

    public function sc_heatmap( $atts ) {
        $this->enqueue_frontend();
        wp_enqueue_script( 'naws-heatmap-boot' );

        $atts = shortcode_atts( [
            'year'   => '',
            'title'  => __( 'Daily Average Temperature', 'xtx-integration-for-netatmo' ),
            'legend' => 'yes',
        ], $atts, 'naws_heatmap' );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/heatmap.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_records year="" records="" layout="cards" title=""]
    // Fifteen records from the daily summary, since 1.9.11
    // ----------------------------------------------------------------
    public function sc_records( $atts ) {
        $this->enqueue_frontend_styles();

        $atts = shortcode_atts( [
            'year'    => '',
            'records' => '',
            'layout'  => 'cards',
            'title'   => null,
        ], $atts, 'naws_records' );

        // The default title names the year when there is one; an explicit
        // empty title="" leaves the heading out.
        if ( $atts['title'] === null ) {
            $year = intval( $atts['year'] );
            // Same 1900-2999 window as NAWS_Calc::period_range(): a value
            // outside it falls back to the whole record, so the title must too.
            $atts['title'] = ( $year >= 1900 && $year <= 2999 ) ? sprintf( naws_label( 'rec_title_year' ), $year ) : naws_label( 'rec_title' );
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/records.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_on_this_day date="" title=""]
    // ----------------------------------------------------------------
    public function sc_on_this_day( $atts ) {
        $this->enqueue_frontend_styles();

        $atts = shortcode_atts( [
            'date'  => '',
            'title' => null,
        ], $atts, 'naws_on_this_day' );

        if ( $atts['title'] === null ) {
            $atts['title'] = naws_label( 'otd_title' );
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/on-this-day.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_sunpath title=""]
    // The sun on its arc over the station, since 1.9.11
    // ----------------------------------------------------------------
    public function sc_sunpath( $atts ) {
        $this->enqueue_frontend_styles();

        $atts = shortcode_atts( [
            'title' => null,
        ], $atts, 'naws_sunpath' );

        if ( $atts['title'] === null ) {
            $atts['title'] = naws_label( 'sun_title' );
        }

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/sunpath.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_live title="" refresh="60"]
    // Live dashboard with animated wind rose, light mode
    // ----------------------------------------------------------------
    public function sc_live( $atts ) {
        $this->enqueue_frontend();
        wp_enqueue_script( 'naws-live-boot' );

        $atts = shortcode_atts( [
            'title'   => '',
            'refresh' => '60',
        ], $atts, 'naws_live' );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/live.php';
        return ob_get_clean();
    }


    /** [naws_infobar] – weather derivations + astronomical data */
    public function sc_infobar( $atts ) {
        $atts = shortcode_atts( [], $atts, 'naws_infobar' );

        $this->enqueue_frontend();

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/infobar.php';
        return ob_get_clean();
    }


    /**
     * [naws_value] – single inline sensor value
     *
     * Attributes:
     *   param    – parameter key (Temperature, Humidity, Pressure, WindStrength,
     *              GustStrength, Rain, sum_rain_24, CO2, Noise)
     *   module   – outdoor | indoor | wind | rain | or MAC address
     *   unit     – 1 = append unit (default), 0 = value only
     *   decimals – override decimal places (-1 = use default)
     *   fallback – text when no value available (default: --)
     *   tag      – HTML wrapper tag (default: span, use "none" for no wrapper)
     *   class    – extra CSS class on wrapper
     */
    public function sc_value( $atts ) {
        $atts = shortcode_atts( [
            'param'    => 'Temperature',
            'module'   => 'outdoor',
            'unit'     => '1',
            'decimals' => '-1',
            'fallback' => '--',
            'tag'      => 'span',
            'class'    => '',
        ], $atts, 'naws_value' );

        $param    = sanitize_text_field( $atts['param'] );
        $show_unit = $atts['unit'] !== '0';
        $fallback  = esc_html( $atts['fallback'] );

        // Resolve module alias → module_id. NAWS_Calc owns the alias table;
        // this shortcode used to keep a second copy of it.
        $modules   = NAWS_Database::get_modules( true );
        $module_id = NAWS_Calc::module_id( sanitize_text_field( $atts['module'] ) );

        // Fetch latest readings
        $readings = NAWS_Database::get_latest_readings( $module_id );

        // Find matching parameter
        $value = null;
        foreach ( $readings as $row ) {
            if ( $row['parameter'] === $param ) {
                $value = NAWS_Helpers::format_value( $param, floatval( $row['value'] ) );
                break;
            }
        }

        // Special: rolling 24h rain
        if ( $value === null && $param === 'rain_rolling_24h' ) {
            foreach ( $modules as $m ) {
                if ( $m['module_type'] === 'NAModule3' ) {
                    $rolling = NAWS_Database::get_rain_rolling_24h( $m['module_id'] );
                    if ( $rolling !== null ) {
                        $value = NAWS_Helpers::format_value( 'Rain', $rolling );
                    }
                    break;
                }
            }
        }

        if ( $value === null ) {
            return $fallback;
        }

        // Override decimals
        $dec = intval( $atts['decimals'] );
        if ( $dec >= 0 ) {
            $value = round( floatval( $value ), $dec );
        }

        $unit_str = $show_unit ? ' ' . NAWS_Helpers::get_unit( $param ) : '';
        $output   = esc_html( $value . $unit_str );

        $tag = sanitize_key( $atts['tag'] );
        if ( $tag === 'none' || $tag === '' ) {
            return $output;
        }
        $class = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        return "<{$tag}{$class}>{$output}</{$tag}>";
    }

    // ----------------------------------------------------------------
    // [naws_calc value="dewpoint" module="outdoor"]
    // A single computed value, for dropping into running text or a table.
    // ----------------------------------------------------------------
    public function sc_calc( $atts ) {
        $atts = shortcode_atts( [
            'value'    => '',
            'module'   => 'outdoor',
            'unit'     => '1',
            'decimals' => '-1',
            'fallback' => '--',
            'tag'      => 'span',
            'class'    => '',
            'station'  => '',
            'period'   => 'year',
            'year'     => '',
            'mode'     => 'count',
            'note'     => '0',
            'base'     => '',
            'cap'      => '',
            'months'   => '3',
        ], $atts, 'naws_calc' );

        $key      = sanitize_key( $atts['value'] );
        $fallback = esc_html( $atts['fallback'] );

        if ( $key === '' || ! NAWS_Calc::has( $key ) ) {
            static $logged = [];
            if ( ! isset( $logged[ $key ] ) ) {
                $logged[ $key ] = true;
                NAWS_Logger::warning( 'calc', 'Unknown or missing value attribute on [naws_calc]: ' . $key );
            }
            return $fallback;
        }

        $entry = NAWS_Calc::catalogue()[ $key ];
        $raw   = NAWS_Calc::raw( $key, [
            'module'  => sanitize_text_field( $atts['module'] ),
            'station' => sanitize_text_field( $atts['station'] ),
            'period'  => sanitize_text_field( $atts['period'] ),
            'year'    => sanitize_text_field( $atts['year'] ),
            'mode'    => sanitize_key( $atts['mode'] ),
            'base'    => sanitize_text_field( $atts['base'] ),
            'cap'     => sanitize_text_field( $atts['cap'] ),
            'months'  => sanitize_text_field( $atts['months'] ),
        ] );

        if ( $raw === null ) {
            return $fallback;
        }

        // Text values carry no unit and need no conversion.
        if ( is_string( $raw ) ) {
            $output = esc_html( $raw );
        } else {
            $param = $entry['param'];
            $value = $param ? NAWS_Helpers::format_value( $param, floatval( $raw ) ) : $raw;

            $dec = intval( $atts['decimals'] );
            if ( $dec < 0 ) {
                $dec = intval( $entry['decimals'] );
            }
            $value = round( floatval( $value ), $dec );

            $unit_label = NAWS_Calc::unit_for( $key );
            $unit_str   = ( $atts['unit'] !== '0' && $unit_label !== '' ) ? ' ' . $unit_label : '';
            $output   = esc_html( $value . $unit_str );
        }

        if ( $atts['note'] === '1' ) {
            $cov = NAWS_Calc::coverage( $key, [
                'station' => sanitize_text_field( $atts['station'] ),
                'period'  => sanitize_text_field( $atts['period'] ),
                'year'    => sanitize_text_field( $atts['year'] ),
            ] );
            if ( $cov !== null && $cov['days'] > 0 ) {
                $output .= ' ' . esc_html( sprintf( /* translators: 1: number of days with data, 2: number of days in the period. */ __( '(from %1$d of %2$d days)', 'xtx-integration-for-netatmo' ), $cov['rows'], $cov['days'] ) );
            }
        }

        $tag = sanitize_key( $atts['tag'] );
        if ( $tag === 'none' || $tag === '' ) {
            return $output;
        }
        $class = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        return "<{$tag}{$class}>{$output}</{$tag}>";
    }

    // ----------------------------------------------------------------
    // [naws_forecast days="5" title="" theme="light"]
    // 5-Day weather forecast via Open-Meteo API
    // ----------------------------------------------------------------
    public function sc_forecast( $atts ) {
        $this->enqueue_frontend();

        $opts     = get_option( 'naws_settings', [] );
        $def_days = max( 1, min( 7, intval( $opts['forecast_days'] ?? 5 ) ) );

        $atts = shortcode_atts( [
            'days'  => (string) $def_days,
            'title' => '',
        ], $atts, 'naws_forecast' );

        $days = max( 1, min( 7, intval( $atts['days'] ) ) );

        // Dynamic default title: "5-Tage-Vorhersage" / "5-Day Forecast"
        if ( $atts['title'] === '' ) {
            $atts['title'] = sprintf( /* translators: %d: number of forecast days. */ __( '%d-Day Forecast', 'xtx-integration-for-netatmo' ), $days );
        }

        $forecast = NAWS_Forecast::get_forecast( $days );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/forecast.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_weather_icon size="96"]
    // Current weather as one animated icon. No caption by design – the
    // state is carried by the aria-label only.
    // ----------------------------------------------------------------
    public function sc_weather_icon( $atts ) {
        $atts = shortcode_atts( [
            'size' => (string) NAWS_Weather_Icons::DEFAULT_SIZE,
        ], $atts, 'naws_weather_icon' );

        $state = NAWS_Weather_State::get_current();

        // Nothing determinable – render nothing at all. A placeholder would
        // claim a weather state the plugin does not actually know.
        if ( $state['state'] === '' ) {
            return '';
        }

        // Only enqueue once we know something will be drawn, so a page
        // without a usable state does not pull in the stylesheet. Styles
        // only – this shortcode draws no chart and needs no script.
        $this->enqueue_frontend_styles();

        return NAWS_Weather_Icons::render( $state['state'], intval( $atts['size'] ) );
    }

    // ----------------------------------------------------------------
    // [naws_weather_widget days="3|5" width="250..500"]
    // Compact sidebar widget: icon and temperature, rain and wind,
    // three or five forecast days.
    // ----------------------------------------------------------------
    public function sc_weather_widget( $atts ) {
        $opts = get_option( 'naws_settings', [] );

        $atts = shortcode_atts( [
            'days'   => (string) ( $opts['wgt_days'] ?? 5 ),
            'width'  => (string) ( $opts['wgt_width'] ?? NAWS_Widget_Data::DEFAULT_WIDTH ),
            'scheme' => (string) ( $opts['wgt_scheme'] ?? NAWS_Widget_Data::SCHEMES[0] ),
        ], $atts, 'naws_weather_widget' );

        $station = NAWS_Weather_State::read_station();
        $state   = NAWS_Weather_State::get_current();
        $days    = intval( $atts['days'] );

        // Values are formatted here so NAWS_Widget_Data::build() stays free
        // of WordPress and remains testable without a framework.
        $fmt = static function ( ?float $raw, string $param ): ?array {
            if ( $raw === null ) {
                return null;
            }
            return [
                'value' => (string) NAWS_Helpers::format_value( $param, $raw ),
                'unit'  => NAWS_Helpers::get_unit( $param ),
            ];
        };

        $forecast = NAWS_Forecast::get_forecast( $days < 4 ? 3 : 5 );

        $naws_wgt = NAWS_Widget_Data::build(
            [
                'temp' => $fmt( $station['temp'], 'Temperature' ),
                'rain' => $fmt( $station['rain'], 'Rain' ),
                'wind' => $fmt( $station['wind_avg'], 'WindStrength' ),
            ],
            $forecast,
            $days
        );

        if ( $naws_wgt['empty'] ) {
            return '';
        }

        // Styles only – the sidebar widget draws no chart and does not
        // need jquery/Chart.js, which enqueue_frontend() would drag in.
        $this->enqueue_frontend_styles();

        $naws_wgt_state  = $state['state'];
        $naws_wgt_width  = $atts['width'];
        $naws_wgt_scheme = NAWS_Widget_Data::normalise_scheme( $atts['scheme'] );
        $naws_wgt_place = (string) ( $forecast['location_name'] ?? '' );
        // The station's newest measurement, not the forecast fetch. The
        // forecast is cached for three hours, so printing its fetch time put
        // a number in the footer that looked like a clock and stood still.
        $naws_wgt_time  = empty( $station['ts'] )
            ? ''
            : wp_date( get_option( 'time_format', 'H:i' ), (int) $station['ts'] );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/weather-widget.php';
        return ob_get_clean();
    }

}