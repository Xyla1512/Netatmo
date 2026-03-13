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
        add_shortcode( 'naws_live',      [ $this, 'sc_live' ] );
        add_shortcode( 'naws_infobar',   [ $this, 'sc_infobar' ] );
        add_shortcode( 'naws_value',     [ $this, 'sc_value' ] );
        add_shortcode( 'naws_forecast',  [ $this, 'sc_forecast' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    public function enqueue_frontend_assets() {
        // Register scripts/styles (not enqueued yet – done per-shortcode)
        wp_register_style(  'naws-frontend', NAWS_PLUGIN_URL . 'assets/css/frontend.css', [], NAWS_VERSION );
        wp_register_script( 'chartjs',
            NAWS_PLUGIN_URL . 'assets/vendor/chart.umd.min.js',
            [], '4.4.0', true );
        wp_register_script( 'chartjs-adapter-date-fns',
            NAWS_PLUGIN_URL . 'assets/vendor/chartjs-adapter-date-fns.bundle.min.js',
            [ 'chartjs' ], '3.0.0', true );
        wp_register_script( 'naws-frontend',
            NAWS_PLUGIN_URL . 'assets/js/frontend.js',
            [ 'jquery','chartjs','chartjs-adapter-date-fns' ], NAWS_VERSION, true );
    }

    private function enqueue_frontend() {
        wp_enqueue_style(  'naws-frontend' );
        wp_enqueue_script( 'naws-frontend' );

        // wp_localize_script MUST be called AFTER wp_enqueue_script
        // and only once – use a static flag
        static $localized = false;
        if ( ! $localized ) {
            wp_localize_script( 'naws-frontend', 'nawsFrontend', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'naws_public_nonce' ),
                'options'  => get_option( 'naws_settings', [] ),
            ] );
            $localized = true;
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
            'theme'      => get_option( 'naws_settings', [] )['chart_theme'] ?? 'light',
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

        $date_from = strtotime( '-' . ltrim( $atts['period'], '-' ) );
        $filter    = $atts['parameters'] ? explode( ',', str_replace( ' ', '', $atts['parameters'] ) ) : null;

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
        wp_enqueue_script( 'chartjs-adapter-date-fns' );

        $atts = shortcode_atts( [
            'module_id'         => '',
            'fields'            => 'temp_min,temp_max,temp_avg',
            'date_from'         => '',
            'date_to'           => '',
            'group_by'          => 'day',
            'title'             => naws__( 'hc_history_title' ),
            'theme'             => get_option( 'naws_settings', [] )['chart_theme'] ?? 'light',
            'height'            => '420',
            'show_range_picker' => 'true',
        ], $atts, 'naws_history' );

        $chart_id   = 'naws-hist-' . wp_unique_id();
        $fields     = array_map( 'trim', explode( ',', $atts['fields'] ) );
        $show_picker = $atts['show_range_picker'] !== 'false';

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/history.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [naws_live title="" refresh="60"]
    // Live dashboard with animated wind rose, light mode
    // ----------------------------------------------------------------
    public function sc_live( $atts ) {
        $this->enqueue_frontend();

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
        $atts = shortcode_atts( [
            'theme' => get_option( 'naws_settings', [] )['chart_theme'] ?? 'light',
        ], $atts, 'naws_infobar' );

        wp_enqueue_style(
            'naws-frontend',
            NAWS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            NAWS_VERSION
        );

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

        // Resolve module alias → module_id
        $module_id = null;
        $modules   = NAWS_Database::get_modules( true );
        $type_map  = [
            'outdoor' => 'NAModule1',
            'indoor'  => 'NAMain',
            'wind'    => 'NAModule2',
            'rain'    => 'NAModule3',
        ];
        $alias = strtolower( $atts['module'] );
        if ( isset( $type_map[ $alias ] ) ) {
            foreach ( $modules as $m ) {
                if ( $m['module_type'] === $type_map[ $alias ] ) {
                    $module_id = $m['module_id'];
                    break;
                }
            }
        } else {
            // Treat as direct MAC address
            $module_id = sanitize_text_field( $atts['module'] );
        }

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
            $atts['title'] = sprintf( naws__( 'forecast_title' ), $days );
        }

        $forecast = NAWS_Forecast::get_forecast( $days );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/forecast.php';
        return ob_get_clean();
    }

}