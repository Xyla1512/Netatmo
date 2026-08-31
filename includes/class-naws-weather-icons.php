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
        return self::render_icon( $state, max( self::MIN_SIZE, $size ), true, false );
    }

    /**
     * Render a small, still icon for use inside a row of them.
     *
     * Differs from render() on three counts, all deliberate:
     *   - no minimum size: the 64 px floor exists for the standalone state
     *     icon, which carries the whole statement on its own. In a forecast
     *     column the icon sits beside a weekday and two temperatures.
     *   - no wrapper element: the caller owns the layout.
     *   - no animation: five to seven moving icons in a row pull attention
     *     away from the numbers they sit next to.
     *
     * @param  string $state  One of NAWS_Weather_State::STATES.
     * @param  int    $size   Edge length in px.
     * @return string         Icon markup, or '' for an unknown state.
     */
    public static function render_inline( string $state, int $size ): string {
        return self::render_icon( $state, max( 1, $size ), false, true );
    }

    /**
     * Render an animated icon that leaves the layout to its caller.
     *
     * The third of the three combinations, and the one the sidebar widget's
     * head needs: it is the statement icon of that widget, so it animates —
     * but it sits in a flex row the widget owns, so it brings no wrapper.
     *
     * render() cannot serve this. It writes --naws-wx-size as an inline
     * style on its wrapper, and an inline declaration beats every stylesheet
     * rule, so the fluid clamp() sizing in .naws-wgt-head would never apply.
     *
     * The size given here is the floor for the 250 px layout; CSS scales the
     * icon up from there with the container. It is also what a reader with
     * the stylesheet blocked gets, which is why it is not left at 1.
     *
     * @param  string $state  One of NAWS_Weather_State::STATES.
     * @param  int    $size   Edge length in px.
     * @return string         Icon markup, or '' for an unknown state.
     */
    public static function render_head( string $state, int $size ): string {
        return self::render_icon( $state, max( 1, $size ), false, false );
    }

    /**
     * Shared body of the three public renderers.
     *
     * Private on purpose: the boolean pair is unreadable at a call site, so
     * callers pick a named method and the switches stay in here.
     *
     * @param  string $state   One of NAWS_Weather_State::STATES.
     * @param  int    $size    Edge length in px, already clamped by the caller.
     * @param  bool   $wrapper Emit the .naws-weather-icon wrapper div.
     * @param  bool   $still   Suppress the animations.
     * @return string          Icon markup, or '' for an unknown state.
     */
    private static function render_icon( string $state, int $size, bool $wrapper, bool $still ): string {
        if ( ! in_array( $state, NAWS_Weather_State::STATES, true ) ) {
            return '';
        }

        self::queue_defs();

        $naws_wx_state   = $state;
        $naws_wx_size    = $size;
        $naws_wx_label   = self::label( $state );
        $naws_wx_still   = $still;
        $naws_wx_wrapper = $wrapper;

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
        $out = function_exists( 'naws_label' ) ? naws_label( 'wx_state_' . $state ) : '';

        // naws_label() returns an empty string for a state it does not know.
        return $out !== '' ? $out : ucfirst( str_replace( '_', ' ', $state ) );
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

        // wp_footer never fires in the admin, so the backend preview on the
        // shortcodes page would render icons whose gradients resolve to
        // nothing.
        $hook = is_admin() ? 'admin_footer' : 'wp_footer';
        add_action( $hook, [ __CLASS__, 'print_defs' ], 5 );
    }

    /** Print the shared <defs> block. Hooked to wp_footer, never called directly. */
    public static function print_defs(): void {
        include NAWS_PLUGIN_DIR . 'templates/weather-icon-defs.php';
    }
}
