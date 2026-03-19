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
                'label' => naws__( 'icon_set_emoji' ),
                'desc'  => naws__( 'icon_set_emoji_desc' ),
                'icons' => self::set_emoji(),
            ],
            'outline' => [
                'label' => naws__( 'icon_set_outline' ),
                'desc'  => naws__( 'icon_set_outline_desc' ),
                'icons' => self::set_outline(),
            ],
            'filled'  => [
                'label' => naws__( 'icon_set_filled' ),
                'desc'  => naws__( 'icon_set_filled_desc' ),
                'icons' => self::set_filled(),
            ],
            'minimal' => [
                'label' => naws__( 'icon_set_minimal' ),
                'desc'  => naws__( 'icon_set_minimal_desc' ),
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
            // Escape single quotes in SVG and output as JS string
            $escaped = str_replace( "'", "\\'", $svg );
            $parts[] = "  {$key}: '{$escaped}'";
        }
        return "{\n" . implode( ",\n", $parts ) . "\n}";
    }

    // ================================================================
    // Icon Set Definitions
    // ================================================================

    /**
     * Emoji icon set — the original default icons.
     */
    private static function set_emoji() {
        return [
            'temp'  => '🌡️',
            'humid' => '💧',
            'press' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 1 0 20 10 10 0 0 1 0-20z"/><path d="M12 6v6l4 2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 6.34l1.41-1.41"/></svg>',
            'wind'  => '🌬️',
            'rain'  => '🌧️',
            'co2'   => '💨',
            'noise' => '🔊',
        ];
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
