<?php
/**
 * Centralized color & appearance management for NAWS.
 *
 * Stores all configurable colors in a single option `naws_appearance`.
 * Provides CSS variable overrides via inline styles and getter methods
 * for chart colors used in PHP templates.
 *
 * @package NAWS
 * @since   1.4.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Colors {

    private static $instance = null;

    /** @var array Cached merged colors (defaults + user overrides). */
    private $colors = null;

    /** WordPress option key. */
    const OPTION_KEY = 'naws_appearance';

    /**
     * All color defaults, grouped logically.
     * Keys map 1:1 to form field names and CSS variable suffixes.
     */
    const DEFAULTS = [
        // ── Gruppe 1: Basis-Theme ──────────────────────────────────────
        'theme_bg'            => '#ffffff',
        'theme_surface'       => '#ffffff',
        'theme_surface_alt'   => '#f8fafc',
        'theme_text'          => '#427272',
        'theme_text_dark'     => '#2d5252',
        'theme_text_darkest'  => '#1a3535',
        'theme_text_muted'    => '#7aa0a0',
        'theme_text_light'    => '#a0b8b8',
        'theme_border'        => '#e0eeee',
        'theme_shadow'        => '#28484814', // rgba(40,72,72,0.08) as 8-digit hex
        'theme_compass_needle'=> '#ef4444',

        // ── Gruppe 2: Akzent-Farben ────────────────────────────────────
        'accent_primary'      => '#00d4ff',
        'accent_secondary'    => '#7c3aed',
        'accent_success'      => '#10b981',
        'accent_warning'      => '#f59e0b',
        'accent_danger'       => '#ef4444',

        // ── Gruppe 3: Sensor-Kachel-Farben ─────────────────────────────
        // Temperature
        'temp_gradient_start'   => '#f59e0b',
        'temp_gradient_end'     => '#ef4444',
        'temp_bg'               => '',
        'temp_text'             => '',
        // Humidity
        'humidity_gradient_start' => '#3b82f6',
        'humidity_gradient_end'   => '#7c3aed',
        'humidity_bg'             => '',
        'humidity_text'           => '',
        // Pressure
        'pressure_gradient_start' => '#06b6d4',
        'pressure_gradient_end'   => '#3b82f6',
        'pressure_bg'             => '',
        'pressure_text'           => '',
        // CO2
        'co2_gradient_start'    => '#10b981',
        'co2_gradient_end'      => '#06b6d4',
        'co2_bg'                => '',
        'co2_text'              => '',
        // Noise
        'noise_gradient_start'  => '#8b5cf6',
        'noise_gradient_end'    => '#ec4899',
        'noise_bg'              => '',
        'noise_text'            => '',
        // Wind
        'wind_gradient_start'   => '#14b8a6',
        'wind_gradient_end'     => '#22d3ee',
        'wind_bg'               => '',
        'wind_text'             => '',
        // Rain
        'rain_gradient_start'   => '#0ea5e9',
        'rain_gradient_end'     => '#6366f1',
        'rain_bg'               => '',
        'rain_text'             => '',
        // Health
        'health_gradient_start' => '#10b981',
        'health_gradient_end'   => '#84cc16',
        'health_bg'             => '',
        'health_text'           => '',

        // ── Gruppe 4: 24h-Chart-Farben ─────────────────────────────────
        'chart_temp_outdoor'      => '#50a882',
        'chart_humidity_outdoor'  => '#3d82bf',
        'chart_temp_indoor'       => '#6491d2',
        'chart_pressure'          => '#785fc8',
        'chart_co2'               => '#4a9848',
        'chart_noise'             => '#b88030',
        'chart_wind'              => '#427272',
        'chart_gusts'             => '#649b9b',
        'chart_rain'              => '#3585b0',
        'chart_module4_temp'      => '#b464c8',
        'chart_module4_humidity'  => '#8264c8',
        'chart_module4_co2'       => '#64aa64',

        // ── Gruppe 5: Chart-Theming ────────────────────────────────────
        'chart_grid'              => '#daf0f066', // rgba(218,240,240,0.4)
        'chart_tick'              => '#7aa0a0',
        'chart_tooltip_bg'        => '#2d5252eb', // rgba(45,82,82,0.92)
        'chart_tooltip_title'     => '#a0c8c8',
        'chart_tooltip_text'      => '#ffffff',
        'chart_axis_title'        => '#a0b8b8',

        // ── Gruppe 6: Jahresvergleich-Palette ──────────────────────────
        'history_year_1'  => '#3d9e74',
        'history_year_2'  => '#3585b0',
        'history_year_3'  => '#7055c0',
        'history_year_4'  => '#c0392b',
        'history_year_5'  => '#e67e22',
        'history_year_6'  => '#16a085',
        'history_year_7'  => '#8e44ad',
        'history_year_8'  => '#2980b9',
        'history_year_9'  => '#27ae60',
        'history_year_10' => '#d35400',
        'history_year_11' => '#1abc9c',
        'history_year_12' => '#e74c3c',
        'history_year_13' => '#f39c12',
        'history_year_14' => '#6c5ce7',
        'history_year_15' => '#00b894',

        // ── Gruppe 7: Icon-Set ─────────────────────────────────────────
        'icon_set' => 'emoji',
    ];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get a single color value. Returns user-set value or default.
     */
    public static function get( $key ) {
        $all = self::get_all();
        return $all[ $key ] ?? ( self::DEFAULTS[ $key ] ?? '' );
    }

    /**
     * Get all colors, merged: user overrides + defaults for missing keys.
     */
    public static function get_all() {
        $inst = self::instance();
        if ( $inst->colors !== null ) {
            return $inst->colors;
        }
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        $inst->colors = array_merge( self::DEFAULTS, $saved );
        return $inst->colors;
    }

    /**
     * Get only the default values.
     */
    public static function get_defaults() {
        return self::DEFAULTS;
    }

    /**
     * Sanitize appearance input from the admin form.
     */
    public static function sanitize( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        $clean = [];
        foreach ( self::DEFAULTS as $key => $default ) {
            if ( $key === 'icon_set' ) {
                $valid = [ 'emoji', 'outline', 'filled', 'minimal' ];
                $clean['icon_set'] = in_array( $input['icon_set'] ?? 'emoji', $valid, true )
                    ? $input['icon_set'] : 'emoji';
                continue;
            }
            if ( ! isset( $input[ $key ] ) ) {
                continue; // not submitted = keep default
            }
            $val = sanitize_text_field( $input[ $key ] );
            // Allow empty string (= inherit from theme)
            if ( $val === '' ) {
                $clean[ $key ] = '';
                continue;
            }
            // Validate hex color (3, 4, 6, or 8 digit)
            if ( preg_match( '/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $val ) ) {
                $clean[ $key ] = $val;
            }
            // else: invalid value, skip (will use default)
        }
        return $clean;
    }

    /**
     * Clear the internal cache (call after saving).
     */
    public static function flush_cache() {
        $inst = self::instance();
        $inst->colors = null;
    }

    // ================================================================
    // CSS Output
    // ================================================================

    /**
     * Generate inline CSS with all CSS custom properties.
     * Applied via wp_add_inline_style().
     */
    public static function get_inline_css() {
        $c = self::get_all();
        $sensors = [ 'temp', 'humidity', 'pressure', 'co2', 'noise', 'wind', 'rain', 'health' ];

        $css = ".naws-wrap, .naws-wx {\n";

        // Basis-Theme
        $css .= "  --naws-bg: {$c['theme_bg']};\n";
        $css .= "  --naws-surface: {$c['theme_surface']};\n";
        $css .= "  --naws-surface-2: {$c['theme_surface_alt']};\n";
        $css .= "  --naws-text: {$c['theme_text']};\n";
        $css .= "  --naws-text-dark: {$c['theme_text_dark']};\n";
        $css .= "  --naws-text-darkest: {$c['theme_text_darkest']};\n";
        $css .= "  --naws-text-muted: {$c['theme_text_muted']};\n";
        $css .= "  --naws-text-light: {$c['theme_text_light']};\n";
        $css .= "  --naws-border: {$c['theme_border']};\n";
        $css .= "  --naws-shadow-color: {$c['theme_shadow']};\n";
        $css .= "  --naws-compass-needle: {$c['theme_compass_needle']};\n";

        // Akzent-Farben
        $css .= "  --naws-primary: {$c['accent_primary']};\n";
        $css .= "  --naws-accent: {$c['accent_secondary']};\n";
        $css .= "  --naws-success: {$c['accent_success']};\n";
        $css .= "  --naws-warning: {$c['accent_warning']};\n";
        $css .= "  --naws-danger: {$c['accent_danger']};\n";

        // Sensor-Gradient-Farben
        foreach ( $sensors as $s ) {
            $css .= "  --naws-{$s}-g1: {$c[$s . '_gradient_start']};\n";
            $css .= "  --naws-{$s}-g2: {$c[$s . '_gradient_end']};\n";
            if ( ! empty( $c[ $s . '_bg' ] ) ) {
                $css .= "  --naws-{$s}-bg: {$c[$s . '_bg']};\n";
            }
            if ( ! empty( $c[ $s . '_text' ] ) ) {
                $css .= "  --naws-{$s}-text: {$c[$s . '_text']};\n";
            }
        }

        // Chart-Theming
        $css .= "  --naws-chart-grid: {$c['chart_grid']};\n";
        $css .= "  --naws-chart-tick: {$c['chart_tick']};\n";
        $css .= "  --naws-chart-tooltip-bg: {$c['chart_tooltip_bg']};\n";
        $css .= "  --naws-chart-tooltip-title: {$c['chart_tooltip_title']};\n";
        $css .= "  --naws-chart-tooltip-text: {$c['chart_tooltip_text']};\n";
        $css .= "  --naws-chart-axis-title: {$c['chart_axis_title']};\n";

        $css .= "}\n";

        // Live-Dashboard mapping: old vars → new unified vars
        $css .= ".naws-wx {\n";
        $css .= "  --ink: {$c['theme_text']};\n";
        $css .= "  --ink2: {$c['theme_text_dark']};\n";
        $css .= "  --ink3: {$c['theme_text_darkest']};\n";
        $css .= "  --muted: {$c['theme_text_muted']};\n";
        $css .= "  --light: {$c['theme_text_light']};\n";
        $css .= "  --line: {$c['theme_border']};\n";
        $css .= "  --bg: {$c['theme_bg']};\n";
        $css .= "  --card: {$c['theme_surface']};\n";
        $css .= "  --sh: {$c['theme_shadow']};\n";

        // Live-Dashboard sensor accent colors
        $css .= "  --c-temp: {$c['chart_temp_outdoor']};\n";
        $css .= "  --c-humid: {$c['chart_humidity_outdoor']};\n";
        $css .= "  --c-press: {$c['chart_pressure']};\n";
        $css .= "  --c-co2: {$c['chart_co2']};\n";
        $css .= "  --c-noise: {$c['chart_noise']};\n";
        $css .= "  --c-wind: {$c['chart_wind']};\n";
        $css .= "  --c-rain: {$c['chart_rain']};\n";
        $css .= "}\n";

        return $css;
    }

    // ================================================================
    // Getter helpers for PHP templates
    // ================================================================

    /**
     * Get sensor chart colors for live.php 24h charts.
     * Returns associative array: key => hex color.
     */
    public static function get_sensor_colors() {
        $c = self::get_all();
        return [
            'Temperature'           => $c['chart_temp_outdoor'],
            'Humidity'              => $c['chart_humidity_outdoor'],
            'Temperature_indoor'    => $c['chart_temp_indoor'],
            'Pressure'              => $c['chart_pressure'],
            'CO2'                   => $c['chart_co2'],
            'Noise'                 => $c['chart_noise'],
            'WindStrength'          => $c['chart_wind'],
            'GustStrength'          => $c['chart_gusts'],
            'Rain'                  => $c['chart_rain'],
            'Temperature_module4'   => $c['chart_module4_temp'],
            'Humidity_module4'      => $c['chart_module4_humidity'],
            'CO2_module4'           => $c['chart_module4_co2'],
        ];
    }

    /**
     * Get a single sensor chart color by parameter key.
     */
    public static function get_sensor_color( $key ) {
        $colors = self::get_sensor_colors();
        return $colors[ $key ] ?? $colors['Temperature'] ?? '#427272';
    }

    /**
     * Get the 15-color year comparison palette for history.php.
     * Returns a numeric array of hex colors.
     */
    public static function get_history_palette() {
        $c = self::get_all();
        $palette = [];
        for ( $i = 1; $i <= 15; $i++ ) {
            $palette[] = $c[ "history_year_{$i}" ];
        }
        return $palette;
    }

    /**
     * Get chart theming colors (grid, tick, tooltip etc.).
     * Returns associative array.
     */
    public static function get_chart_theme() {
        $c = self::get_all();
        return [
            'grid'          => $c['chart_grid'],
            'tick'          => $c['chart_tick'],
            'tooltip_bg'    => $c['chart_tooltip_bg'],
            'tooltip_title' => $c['chart_tooltip_title'],
            'tooltip_text'  => $c['chart_tooltip_text'],
            'axis_title'    => $c['chart_axis_title'],
        ];
    }

    /**
     * Get the selected icon set.
     */
    public static function get_icon_set() {
        return self::get( 'icon_set' );
    }

    // ================================================================
    // Group definitions for admin UI
    // ================================================================

    /**
     * Get color groups with their keys, for rendering the admin form.
     */
    public static function get_groups() {
        return [
            'theme' => [
                'label' => 'appearance_group_theme',
                'keys'  => [
                    'theme_bg', 'theme_surface', 'theme_surface_alt',
                    'theme_text', 'theme_text_dark', 'theme_text_darkest',
                    'theme_text_muted', 'theme_text_light',
                    'theme_border', 'theme_shadow',
                    'theme_compass_needle',
                ],
            ],
            'accent' => [
                'label' => 'appearance_group_accent',
                'keys'  => [
                    'accent_primary', 'accent_secondary',
                    'accent_success', 'accent_warning', 'accent_danger',
                ],
            ],
            'sensors' => [
                'label'    => 'appearance_group_sensors',
                'keys'     => [], // built dynamically
                'sensors'  => [ 'temp', 'humidity', 'pressure', 'co2', 'noise', 'wind', 'rain', 'health' ],
                'per_sensor' => [ 'gradient_start', 'gradient_end', 'bg', 'text' ],
            ],
            'chart_24h' => [
                'label' => 'appearance_group_chart_24h',
                'keys'  => [
                    'chart_temp_outdoor', 'chart_humidity_outdoor',
                    'chart_temp_indoor', 'chart_pressure',
                    'chart_co2', 'chart_noise',
                    'chart_wind', 'chart_gusts', 'chart_rain',
                    'chart_module4_temp', 'chart_module4_humidity', 'chart_module4_co2',
                ],
            ],
            'chart_theme' => [
                'label' => 'appearance_group_chart_theme',
                'keys'  => [
                    'chart_grid', 'chart_tick',
                    'chart_tooltip_bg', 'chart_tooltip_title', 'chart_tooltip_text',
                    'chart_axis_title',
                ],
            ],
            'history_palette' => [
                'label' => 'appearance_group_history',
                'keys'  => array_map( function( $i ) { return "history_year_{$i}"; }, range( 1, 15 ) ),
            ],
        ];
    }
}
