<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The fonts the plugin may offer for its own output.
 *
 * One rule governs the whole class: only offer what the page already
 * serves. The plugin enqueues no font file — doing so would mean an
 * external request and a disclosure obligation — so a family nobody else
 * loaded would fall back in the browser and the setting would be a lie.
 * Everything listed here is therefore either generic (resolved by the
 * browser without a download) or demonstrably present on the site.
 *
 * The slug is the contract: naws_appearance stores it, so it has to keep
 * meaning the same family after an update, and one that disappeared has
 * to fall back rather than write a dangling name into the stylesheet.
 */
class NAWS_Fonts {

    /** @var array<string, array{label:string, stack:string, origin:string}>|null */
    private static $cache = null;

    /**
     * Generic stacks. No download, resolved by every browser.
     *
     * @return array<string, array{label:string, stack:string, origin:string}>
     */
    private static function generic(): array {
        return [
            'system' => [
                'label'  => 'System-UI',
                'stack'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                'origin' => 'generic',
            ],
            'sans-serif' => [
                'label'  => 'Sans-Serif',
                'stack'  => 'Helvetica, Arial, sans-serif',
                'origin' => 'generic',
            ],
            'serif' => [
                'label'  => 'Serif',
                'stack'  => 'Georgia, "Times New Roman", Times, serif',
                'origin' => 'generic',
            ],
            'monospace' => [
                'label'  => 'Monospace',
                'stack'  => 'Consolas, Monaco, "Courier New", monospace',
                'origin' => 'generic',
            ],
        ];
    }

    /**
     * The fonts WordPress itself knows about: those declared in the
     * theme's theme.json and those installed through the font library.
     * Both arrive in the same node and WordPress prints @font-face for
     * both, so both really are on the page.
     *
     * @return array<string, array{label:string, stack:string, origin:string}>
     */
    private static function from_wordpress(): array {
        if ( ! function_exists( 'wp_get_global_settings' ) ) {
            return [];
        }
        $settings = wp_get_global_settings();
        $families = $settings['typography']['fontFamilies'] ?? null;
        if ( ! is_array( $families ) ) {
            return [];
        }

        $fonts = [];
        foreach ( [ 'theme', 'custom' ] as $origin ) {
            foreach ( (array) ( $families[ $origin ] ?? [] ) as $family ) {
                if ( ! is_array( $family ) || empty( $family['fontFamily'] ) ) {
                    continue; // A preset without a family cannot be applied.
                }
                $label = (string) ( $family['name'] ?? $family['slug'] ?? '' );
                $slug  = self::slug( 'wp', (string) ( $family['slug'] ?? $label ) );
                if ( '' === $slug || '' === $label ) {
                    continue;
                }
                $fonts[ $slug ] = [
                    'label'  => $label,
                    'stack'  => (string) $family['fontFamily'],
                    'origin' => 'wp',
                ];
            }
        }
        return $fonts;
    }

    /**
     * The fonts Elementor actually puts on the page.
     *
     * Deliberately not \Elementor\Fonts::get_fonts() — that is a catalogue
     * of more than 1600 families, nearly all of them Google Fonts that
     * Elementor fetches only where a widget uses one. Offering those would
     * promise a typeface the browser never receives. What Elementor really
     * enqueued for the site is recorded in the kit's compiled CSS.
     *
     * @return array<string, array{label:string, stack:string, origin:string}>
     */
    private static function from_elementor(): array {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return [];
        }

        $names = [];

        $kit = (int) get_option( 'elementor_active_kit' );
        if ( $kit > 0 ) {
            $css = get_post_meta( $kit, '_elementor_css', true );
            if ( is_array( $css ) ) {
                foreach ( (array) ( $css['fonts'] ?? [] ) as $name ) {
                    $names[] = $name;
                }
            }
        }

        // Elementor Pro keeps uploaded families as posts. Those are served
        // from the site itself, so they belong on the list. Without Pro the
        // post type does not exist and there is nothing to look for.
        if ( post_type_exists( 'elementor_font' ) ) {
            $ids = get_posts( [
                'post_type'      => 'elementor_font',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'fields'         => 'ids',
            ] );
            foreach ( (array) $ids as $id ) {
                $names[] = get_the_title( $id );
            }
        }

        $fonts = [];
        foreach ( $names as $name ) {
            if ( ! is_string( $name ) ) {
                continue;
            }
            $name = trim( $name );
            $slug = self::slug( 'el', $name );
            if ( '' === $name || '' === $slug ) {
                continue;
            }
            $fonts[ $slug ] = [
                'label'  => $name,
                'stack'  => self::stack_from_name( $name ),
                'origin' => 'elementor',
            ];
        }
        return $fonts;
    }

    /**
     * Turn a bare family name into a usable stack. Quoted, because names
     * with spaces need it and quoting one without does no harm; and with a
     * fallback, because the name alone leaves the browser nothing to do
     * when the family is missing.
     */
    private static function stack_from_name( string $name ): string {
        $name = str_replace( [ '"', "'", ';', '{', '}' ], '', $name );
        return '"' . $name . '", sans-serif';
    }

    /**
     * Build a stable, safe slug for a discovered font.
     *
     * @param string $prefix Source marker, keeps the namespaces apart.
     * @param string $raw    Whatever the source called the family.
     */
    private static function slug( string $prefix, string $raw ): string {
        $slug = strtolower( $raw );
        $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
        $slug = trim( (string) $slug, '-' );
        return '' === $slug ? '' : $prefix . '-' . $slug;
    }

    /**
     * Every font the site can actually render, keyed by slug.
     *
     * @return array<string, array{label:string, stack:string, origin:string}>
     */
    public static function available(): array {
        if ( null !== self::$cache ) {
            return self::$cache;
        }

        $fonts = [
            'inherit' => [
                'label'  => naws__( 'appearance_font_inherit' ),
                'stack'  => 'inherit',
                'origin' => 'inherit',
            ],
        ];

        $fonts += self::from_wordpress();
        $fonts += self::from_elementor();
        $fonts += self::generic();

        self::$cache = self::drop_duplicates( $fonts );
        return self::$cache;
    }

    /**
     * Two sources can know the same family — a theme.json font that
     * Elementor also enqueues, say. Two identical lines in the dropdown
     * would only invite the question which one is the real one, so the
     * earlier source wins.
     *
     * @param array<string, array{label:string, stack:string, origin:string}> $fonts
     * @return array<string, array{label:string, stack:string, origin:string}>
     */
    private static function drop_duplicates( array $fonts ): array {
        $seen = [];
        $out  = [];
        foreach ( $fonts as $slug => $font ) {
            $key = strtolower( trim( $font['label'] ) );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $out[ $slug ] = $font;
        }
        return $out;
    }

    /**
     * The CSS font stack for a stored setting.
     *
     * Anything the list no longer knows falls back to inheritance rather
     * than writing a family the browser cannot resolve — a slug survives
     * a theme change, the font behind it does not.
     *
     * @param string $slug   What naws_appearance stored.
     * @param string $custom The hand-entered family, used only for 'custom'.
     */
    public static function stack( string $slug, string $custom = '' ): string {
        if ( 'custom' === $slug ) {
            $family = self::sanitize_family( $custom );
            return '' === $family ? 'inherit' : $family;
        }
        $fonts = self::available();
        return $fonts[ $slug ]['stack'] ?? 'inherit';
    }

    /**
     * Accept a hand-entered font family, or nothing.
     *
     * The value goes into the stylesheet unchanged, so this is the door it
     * has to pass. A font family is letters, digits, spaces, commas,
     * quotes, hyphens, underscores and dots — nothing else. Anything
     * carrying a semicolon, a brace or a parenthesis is not a family that
     * was typed slightly wrong; it is CSS, and it is discarded whole
     * rather than trimmed into something that still runs.
     */
    public static function sanitize_family( string $raw ): string {
        $value = trim( sanitize_text_field( $raw ) );
        if ( '' === $value || strlen( $value ) > 120 ) {
            return '';
        }
        return preg_match( '/^[A-Za-z0-9 ,\'"\-_.]+$/', $value ) ? $value : '';
    }

    /**
     * The list arranged for the settings dropdown: group key to a map of
     * slug and label. Groups without a member are left out — an empty
     * optgroup only raises the question what is missing.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array {
        $groups = [ 'inherit' => [], 'wp' => [], 'elementor' => [], 'generic' => [] ];
        foreach ( self::available() as $slug => $font ) {
            $groups[ $font['origin'] ][ $slug ] = $font['label'];
        }
        return array_filter( $groups );
    }

    /**
     * Drop the assembled list. The sources are read once per request;
     * tests and the settings screen need a way to start over.
     */
    public static function flush_cache(): void {
        self::$cache = null;
    }
}
