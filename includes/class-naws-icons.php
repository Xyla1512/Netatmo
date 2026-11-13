<?php
/**
 * Icon set management for NAWS frontend.
 *
 * Provides 4 icon sets: emoji, outline, filled, minimal.
 * Each set maps sensor keys (temp, humid, press, wind, rain, co2, noise)
 * to their respective icon markup.
 *
 * @package NAWS
 * @since   1.4.3
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Icons {

    /** All available icon set keys. */
    const SETS = [ 'emoji', 'outline', 'filled', 'minimal' ];

    /** Sensor keys used across icon sets. */
    const KEYS = [ 'temp', 'humid', 'press', 'wind', 'rain', 'co2', 'noise' ];

    /**
     * Get the currently configured icon set key.
     */
    public static function get_current_set() {
        return NAWS_Colors::get_icon_set();
    }

    /**
     * Get icons for a specific set (or current set if null).
     *
     * @param string|null $set  Icon set key (emoji|outline|filled|minimal).
     * @return array Associative array: key => icon HTML string.
     */
    public static function get_set( $set = null ) {
        if ( $set === null ) {
            $set = self::get_current_set();
        }
        switch ( $set ) {
            case 'filled':
                return self::set_filled();
            case 'minimal':
                return self::set_minimal();
            case 'emoji':
                return self::set_emoji();
            case 'outline':
            default:
                return self::set_outline();
        }
    }

    /**
     * Get all icon sets with their metadata.
     * Used for the admin selector UI.
     *
     * @return array [ set_key => [ 'label' => …, 'icons' => [ key => svg ] ] ]
     */
    public static function get_all_sets() {
        return [
            'emoji'   => [
                'label' => __( 'Emoji', 'xtx-integration-for-netatmo' ),
                'desc'  => __( 'Colourful pictograms in emoji style — the same on every device.', 'xtx-integration-for-netatmo' ),
                'icons' => self::set_emoji(),
            ],
            'outline' => [
                'label' => __( 'Outline', 'xtx-integration-for-netatmo' ),
                'desc'  => __( 'Clean line icons with thin strokes — the classic look.', 'xtx-integration-for-netatmo' ),
                'icons' => self::set_outline(),
            ],
            'filled'  => [
                'label' => __( 'Filled', 'xtx-integration-for-netatmo' ),
                'desc'  => __( 'Solid filled icons for a bolder, modern look.', 'xtx-integration-for-netatmo' ),
                'icons' => self::set_filled(),
            ],
            'minimal' => [
                'label' => __( 'Minimal', 'xtx-integration-for-netatmo' ),
                'desc'  => __( 'Ultra-thin, geometric icons for a reduced style.', 'xtx-integration-for-netatmo' ),
                'icons' => self::set_minimal(),
            ],
        ];
    }

    /**
     * Get icons as a JS object literal string for embedding in live.php.
     *
     * @param string|null $set  Icon set key.
     * @return string JS object source code.
     */
    public static function get_js_object( $set = null ) {
        $icons = self::get_set( $set );
        $parts = [];
        foreach ( $icons as $key => $svg ) {
            // Sanitize SVG and encode each value as a proper JSON string.
            $safe_svg = wp_kses( $svg, naws_svg_kses_args() );
            $parts[]  = '  ' . esc_js( $key ) . ': ' . wp_json_encode( $safe_svg );
        }
        return "{\n" . implode( ",\n", $parts ) . "\n}";
    }

    // ================================================================
    // Icon Set Definitions
    // ================================================================

    /**
     * Emoji icon set — colourful pictograms in emoji style.
     *
     * Until 2.2.0 these were system emojis. WordPress swaps emojis for
     * images from s.w.org in browsers without an emoji font, which sends
     * the visitor's IP address there; our own drawings look the same on
     * every device and load nothing from elsewhere.
     */
    private static function set_emoji() {
        $set = [];
        foreach ( self::KEYS as $key ) {
            $set[ $key ] = self::pictogram( $key );
        }
        return $set;
    }

    /**
     * One colourful pictogram as inline SVG, '' for an unknown key.
     *
     * Shared by the emoji icon set (temp … noise) and [naws_forecast]
     * (pin, prob, compass, gust and the weather keys). class="naws-picto"
     * exempts them from the line-icon rules in frontend.css, which would
     * otherwise paint them in one colour. Every child without its own
     * outline says stroke="none" for the same reason.
     *
     * @since 2.2.0
     */
    public static function pictogram( string $key ): string {
        $o = 'stroke="#3d4b52" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"';
        $n = 'stroke="none"';
        $shapes = [
            'temp'    => '<rect x="12.5" y="3" width="7" height="19" rx="3.5" fill="#f2f4f5" ' . $o . '/><circle cx="16" cy="24.5" r="5.2" fill="#e5484d" ' . $o . '/><rect x="14.6" y="9" width="2.8" height="13" rx="1.4" fill="#e5484d" ' . $n . '/><path d="M20 8h2.5M20 12h2.5M20 16h2.5" fill="none" ' . $o . '/>',
            'humid'   => '<path d="M16 3.5C16 3.5 7 13.5 7 19.5a9 9 0 0 0 18 0C25 13.5 16 3.5 16 3.5Z" fill="#4aa3df" ' . $o . '/><path d="M11.5 19.5a4.5 4.5 0 0 0 3 4.2" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" opacity=".85"/>',
            'press'   => '<rect x="14" y="2.5" width="4" height="3.2" rx="1" fill="#c7cdd1" ' . $o . '/><circle cx="16" cy="18" r="11" fill="#f2f4f5" ' . $o . '/><circle cx="16" cy="18" r="8" fill="#ffffff" stroke="#c7cdd1" stroke-width="1"/><path d="M16 18 21 13" fill="none" stroke="#e5484d" stroke-width="2" stroke-linecap="round"/><circle cx="16" cy="18" r="1.8" fill="#3d4b52" ' . $n . '/><path d="M10.5 18h1.6M20 18h1.5M16 12.2v1.5" fill="none" stroke="#8a969c" stroke-width="1.3" stroke-linecap="round"/>',
            'wind'    => '<path d="M4 11h15a4 4 0 1 0-4-4" fill="none" stroke="#7cb7d9" stroke-width="2.6" stroke-linecap="round"/><path d="M4 17h20a4 4 0 1 1-4 4" fill="none" stroke="#5a9cc4" stroke-width="2.6" stroke-linecap="round"/><path d="M4 23h9" fill="none" stroke="#a9d0e6" stroke-width="2.6" stroke-linecap="round"/>',
            'rain'    => '<path d="M9 19.5a5.5 5.5 0 0 1-.6-11 7.5 7.5 0 0 1 14.4 1.5A4.8 4.8 0 0 1 23 19.5Z" fill="#dfe6ea" ' . $o . '/><path d="M11 23l-1.5 3.5M16.5 23 15 26.5M22 23l-1.5 3.5" fill="none" stroke="#3b8fd1" stroke-width="2.2" stroke-linecap="round"/>',
            'co2'     => '<path d="M8.5 24a6 6 0 0 1-.7-12 8 8 0 0 1 15.6 1.8A5.2 5.2 0 0 1 23.5 24Z" fill="#e7ebee" ' . $o . '/><text x="16" y="21.2" text-anchor="middle" font-family="Arial, sans-serif" font-size="7.2" font-weight="700" fill="#4b5a61" ' . $n . '>CO₂</text>',
            'noise'   => '<path d="M5 12.5h5l7-6v19l-7-6H5Z" fill="#9aa6ad" ' . $o . '/><path d="M21 12a5.5 5.5 0 0 1 0 8M24.5 8.8a10 10 0 0 1 0 14.4" fill="none" stroke="#f0a43a" stroke-width="2.2" stroke-linecap="round"/>',
            'pin'     => '<path d="M16 29s9-9.5 9-16a9 9 0 0 0-18 0c0 6.5 9 16 9 16Z" fill="#e5484d" ' . $o . '/><circle cx="16" cy="13" r="3.4" fill="#ffffff" ' . $n . '/>',
            'prob'    => '<path d="M16 3.5C16 3.5 7 13.5 7 19.5a9 9 0 0 0 18 0C25 13.5 16 3.5 16 3.5Z" fill="#a9d4f2" ' . $o . '/><path d="M7.4 21h17.2a9 9 0 0 1-17.2 0Z" fill="#4aa3df" ' . $n . '/>',
            'compass' => '<circle cx="16" cy="16" r="12" fill="#f2f4f5" ' . $o . '/><path d="M16 6.5 19 16h-6Z" fill="#e5484d" ' . $n . '/><path d="M16 25.5 13 16h6Z" fill="#9aa6ad" ' . $n . '/><circle cx="16" cy="16" r="1.6" fill="#3d4b52" ' . $n . '/>',
            'gust'    => '<path d="M4 9h13a3.5 3.5 0 1 0-3.5-3.5" fill="none" stroke="#5a9cc4" stroke-width="2.4" stroke-linecap="round"/><path d="M4 15h21a4 4 0 1 1-4 4" fill="none" stroke="#3a7fb0" stroke-width="2.6" stroke-linecap="round"/><path d="M4 21h11a3 3 0 1 1-3 3" fill="none" stroke="#7cb7d9" stroke-width="2.4" stroke-linecap="round"/><path d="M24 5.5l2.5 3M27.5 9.5l1.5 1" fill="none" stroke="#f0a43a" stroke-width="2" stroke-linecap="round"/>',
        ];
        if ( ! isset( $shapes[ $key ] ) ) {
            return '';
        }
        return '<svg class="naws-picto" viewBox="0 0 32 32" width="1em" height="1em" aria-hidden="true" focusable="false">' . $shapes[ $key ] . '</svg>';
    }

    /**
     * Outline icon set — clean stroke-only SVGs (the current live.php default).
     */
    private static function set_outline() {
        return [
            'temp'  => '<svg viewBox="0 0 24 24"><path d="M14 14.76V3.5a2.5 2.5 0 00-5 0v11.26a4.5 4.5 0 105 0z"/></svg>',
            'humid' => '<svg viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 11-11.31 0z"/></svg>',
            'press' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 6.34l1.41-1.41"/></svg>',
            'wind'  => '<svg viewBox="0 0 24 24"><path d="M9.59 4.59A2 2 0 1111 8H2m10.59 11.41A2 2 0 1014 16H2m15.73-8.27A2.5 2.5 0 1119.5 12H2"/></svg>',
            'rain'  => '<svg viewBox="0 0 24 24"><path d="M20 17.58A5 5 0 0018 8h-1.26A8 8 0 104 15.25"/><line x1="8" y1="16" x2="8" y2="20"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="16" y1="16" x2="16" y2="20"/></svg>',
            'co2'   => '<svg viewBox="0 0 24 24"><path d="M17 8C8 10 5.9 16.17 3.82 20.8"/><path d="M9 9.14C9.23 10.5 11.34 14 16 14c3 0 6-2 6-5s-3-5-6-5c-1.5 0-3 .5-4 1.5"/></svg>',
            'noise' => '<svg viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 010 7.07"/><path d="M19.07 4.93a10 10 0 010 14.14"/></svg>',
        ];
    }

    /**
     * Filled icon set — SVGs with solid fills for a bolder look.
     */
    private static function set_filled() {
        return [
            'temp'  => '<svg viewBox="0 0 24 24"><path d="M14 14.76V3.5a2.5 2.5 0 00-5 0v11.26a4.5 4.5 0 105 0z" fill="currentColor" stroke="none"/></svg>',
            'humid' => '<svg viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 11-11.31 0z" fill="currentColor" stroke="none"/></svg>',
            'press' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="currentColor" stroke="none"/><path d="M12 6v6l4 2" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.93 4.93l1.41 1.41" stroke="#fff" stroke-width="1.5" fill="none"/><path d="M17.66 6.34l1.41-1.41" stroke="#fff" stroke-width="1.5" fill="none"/></svg>',
            'wind'  => '<svg viewBox="0 0 24 24"><path d="M9.59 4.59A2 2 0 1111 8H2m10.59 11.41A2 2 0 1014 16H2m15.73-8.27A2.5 2.5 0 1119.5 12H2" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'rain'  => '<svg viewBox="0 0 24 24"><path d="M20 17.58A5 5 0 0018 8h-1.26A8 8 0 104 15.25" fill="currentColor" stroke="none"/><line x1="8" y1="17" x2="8" y2="21" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="19" x2="12" y2="23" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="16" y1="17" x2="16" y2="21" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
            'co2'   => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="currentColor" stroke="none"/><text x="12" y="13" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="7" font-weight="800" fill="#fff">CO₂</text></svg>',
            'noise' => '<svg viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" stroke="none"/><path d="M15.54 8.46a5 5 0 010 7.07" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><path d="M19.07 4.93a10 10 0 010 14.14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
        ];
    }

    /**
     * Minimal icon set — ultra-thin, single-stroke geometric icons.
     */
    private static function set_minimal() {
        return [
            'temp'  => '<svg viewBox="0 0 24 24"><line x1="12" y1="3" x2="12" y2="17"/><circle cx="12" cy="19" r="3" fill="none"/></svg>',
            'humid' => '<svg viewBox="0 0 24 24"><path d="M12 4l-4 8a4.5 4.5 0 108 0z" fill="none"/></svg>',
            'press' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/><path d="M5.63 5.63l1 1"/><path d="M17.37 6.63l1-1"/></svg>',
            'wind'  => '<svg viewBox="0 0 24 24"><path d="M3 8h10a2 2 0 100-4M3 16h8a2 2 0 110 4M3 12h14a2.5 2.5 0 100-5" fill="none"/></svg>',
            'rain'  => '<svg viewBox="0 0 24 24"><path d="M4 14h16" fill="none"/><line x1="7" y1="17" x2="7" y2="20"/><line x1="12" y1="17" x2="12" y2="22"/><line x1="17" y1="17" x2="17" y2="20"/></svg>',
            'co2'   => '<svg viewBox="0 0 24 24"><circle cx="8" cy="12" r="4" fill="none"/><circle cx="18" cy="12" r="3" fill="none"/></svg>',
            'noise' => '<svg viewBox="0 0 24 24"><line x1="6" y1="8" x2="6" y2="16"/><line x1="10" y1="5" x2="10" y2="19"/><line x1="14" y1="9" x2="14" y2="15"/><line x1="18" y1="6" x2="18" y2="18"/></svg>',
        ];
    }
}
