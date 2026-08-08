<?php
/**
 * NAWS_Weather_Icons – renders the twelve animated weather icons.
 *
 * Kept separate from NAWS_Icons on purpose: that class serves small,
 * single-colour sensor symbols in four selectable sets (emoji, outline,
 * filled, minimal). These icons are multi-colour, animated and follow none
 * of those sets. Merging the two would make both unclear.
 *
 * The markup itself lives in templates/weather-icon.php as literal SVG —
 * see the comment there for why it must not travel as a PHP string.
 *
 * @package NAWS
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Weather_Icons {

    /** Smallest size the design supports. Below this the states blur together. */
    const MIN_SIZE     = 64;
    const DEFAULT_SIZE = 96;

    /** True once the shared <defs> block has been queued for the footer. */
    private static $defs_queued = false;

    /**
     * Render one icon.
     *
     * Also queues the shared <defs> block for wp_footer on first call, so a
     * page with both the shortcode and the dashboard icon still emits the
     * gradient definitions exactly once.
     *
     * @param  string $state   One of NAWS_Weather_State::STATES.
     * @param  int    $size    Edge length in px, clamped to MIN_SIZE.
     * @return string          Icon markup, or '' for an unknown state.
     */
    public static function render( string $state, int $size = self::DEFAULT_SIZE ): string {
        if ( ! in_array( $state, NAWS_Weather_State::STATES, true ) ) {
            return '';
        }

        self::queue_defs();

        $naws_wx_state = $state;
        $naws_wx_size  = max( self::MIN_SIZE, $size );
        $naws_wx_label = self::label( $state );

        ob_start();
        include NAWS_PLUGIN_DIR . 'templates/weather-icon.php';
        return ob_get_clean();
    }

    /**
     * Translated aria-label for a state.
     *
     * There is no visible caption anywhere — the label is the only textual
     * representation the icon has, so it carries the whole meaning for
     * screen readers.
     */
    public static function label( string $state ): string {
        $key = 'wx_state_' . $state;
        $out = function_exists( 'naws__' ) ? naws__( $key ) : $key;

        // naws__() echoes the key back when a translation is missing.
        return $out === $key ? ucfirst( str_replace( '_', ' ', $state ) ) : $out;
    }

    /**
     * Queue the shared gradient definitions for the page footer.
     *
     * Every icon references these by id, and ids must be unique within a
     * document. Emitting them per icon would produce duplicates as soon as
     * two icons share a page.
     */
    public static function queue_defs(): void {
        if ( self::$defs_queued ) {
            return;
        }
        self::$defs_queued = true;

        add_action( 'wp_footer', [ __CLASS__, 'print_defs' ], 5 );
    }

    /** Print the shared <defs> block. Hooked to wp_footer, never called directly. */
    public static function print_defs(): void {
        include NAWS_PLUGIN_DIR . 'templates/weather-icon-defs.php';
    }
}
