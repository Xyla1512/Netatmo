<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * NAWS_Lang – lightweight plugin-level i18n with file-based language loading.
 *
 * Language files live in /languages/{code}.php and return an associative array.
 * Adding a new language = adding a new file. No core code changes needed.
 *
 * Language is determined once per request:
 *   1. Plugin setting  naws_settings['language']  ('de' | 'en' | 'no' | 'auto')
 *   2. 'auto' → map WP locale to closest available language
 *   3. Fallback: 'en'
 */
class NAWS_Lang {

    /** @var string|null Cached active language code */
    private static $lang = null;

    /** @var array<string, array<string, string>> Loaded translations keyed by language */
    private static $strings = [];

    /** @var array<string, string>|null Available languages cache */
    private static $available = null;

    // ────────────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────────────

    /** Reset cached language (call after changing the language option) */
    public static function reset(): void {
        self::$lang = null;
    }

    /** Determine active language (cached per request) */
    public static function lang(): string {
        if ( self::$lang !== null ) return self::$lang;

        $options = get_option( 'naws_settings', [] );
        $setting = $options['language'] ?? 'auto';

        $available = self::get_available_languages();

        if ( $setting !== 'auto' && isset( $available[ $setting ] ) ) {
            self::$lang = $setting;
        } else {
            self::$lang = self::detect_from_locale( $available );
        }

        return self::$lang;
    }

    /** Translate a key, optional sprintf args */
    public static function t( string $key, array $args = [] ): string {
        $lang    = self::lang();
        $strings = self::load( $lang );
        $str     = $strings[ $key ] ?? null;

        // Fallback to English if key not found in active language
        if ( $str === null && $lang !== 'en' ) {
            $en  = self::load( 'en' );
            $str = $en[ $key ] ?? $key;
        }

        if ( $str === null ) {
            $str = $key;
        }

        return $args ? vsprintf( $str, $args ) : $str;
    }

    /** Echo translated string (HTML-escaped) */
    public static function e( string $key, array $args = [] ): void {
        echo esc_html( self::t( $key, $args ) );
    }

    /** Echo translated string raw (for HTML content with allowed tags) */
    public static function r( string $key, array $args = [] ): void {
        echo wp_kses_post( self::t( $key, $args ) );
    }

    /** Return full translation array for current language (for JS) */
    public static function js_strings(): array {
        return self::load( self::lang() );
    }

    /**
     * Get available languages.
     * Returns [ 'en' => 'English', 'de' => 'Deutsch', ... ]
     * Auto-discovered from /languages/*.php files.
     */
    public static function get_available_languages(): array {
        if ( self::$available !== null ) return self::$available;

        self::$available = [];
        $dir = NAWS_PLUGIN_DIR . 'languages/';

        // Map of language code → native name
        $native_names = [
            'en' => 'English',
            'de' => 'Deutsch',
            'no' => 'Norsk',
            'fr' => 'Français',
            'es' => 'Español',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'sv' => 'Svenska',
            'da' => 'Dansk',
            'fi' => 'Suomi',
            'pl' => 'Polski',
            'cs' => 'Čeština',
            'pt' => 'Português',
        ];

        // Use scandir as fallback if glob() is disabled on the server
        $files = function_exists( 'glob' ) ? glob( $dir . '*.php' ) : false;
        if ( ! is_array( $files ) ) {
            $files = [];
            if ( is_dir( $dir ) && ( $dh = opendir( $dir ) ) ) {
                while ( ( $entry = readdir( $dh ) ) !== false ) {
                    if ( substr( $entry, -4 ) === '.php' ) {
                        $files[] = $dir . $entry;
                    }
                }
                closedir( $dh );
            }
        }

        foreach ( $files as $file ) {
            $code = basename( $file, '.php' );
            if ( $code === 'index' ) continue;
            self::$available[ $code ] = $native_names[ $code ] ?? strtoupper( $code );
        }

        // Ensure English is always first
        if ( isset( self::$available['en'] ) ) {
            $en = self::$available['en'];
            unset( self::$available['en'] );
            self::$available = [ 'en' => $en ] + self::$available;
        }

        return self::$available;
    }

    // ────────────────────────────────────────────────────────────────
    // Internal
    // ────────────────────────────────────────────────────────────────

    /** Load and cache a language file */
    private static function load( string $code ): array {
        if ( isset( self::$strings[ $code ] ) ) return self::$strings[ $code ];

        $file = NAWS_PLUGIN_DIR . 'languages/' . $code . '.php';

        if ( file_exists( $file ) ) {
            $data = include $file;
            self::$strings[ $code ] = is_array( $data ) ? $data : [];
        } else {
            self::$strings[ $code ] = [];
        }

        return self::$strings[ $code ];
    }

    /** Auto-detect language from WordPress locale */
    private static function detect_from_locale( array $available ): string {
        $locale = get_locale(); // e.g. 'de_DE', 'nb_NO', 'nn_NO', 'en_US'

        // Map WP locale prefixes to our language codes
        $locale_map = [
            'de' => 'de',
            'nb' => 'no',
            'nn' => 'no',
            'no' => 'no',
            'fr' => 'fr',
            'es' => 'es',
            'it' => 'it',
            'nl' => 'nl',
            'sv' => 'sv',
            'da' => 'da',
            'fi' => 'fi',
            'pl' => 'pl',
            'cs' => 'cs',
            'pt' => 'pt',
        ];

        $prefix = substr( $locale, 0, 2 );
        $code   = $locale_map[ $prefix ] ?? 'en';

        return isset( $available[ $code ] ) ? $code : 'en';
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Global helper functions
// ─────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'naws__' ) ) {
    function naws__( string $key, array $args = [] ): string {
        return NAWS_Lang::t( $key, $args );
    }
}

if ( ! function_exists( 'naws_e' ) ) {
    function naws_e( string $key, array $args = [] ): void {
        NAWS_Lang::e( $key, $args );
    }
}
