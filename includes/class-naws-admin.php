<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',               [ $this, 'add_menu' ] );
        add_action( 'admin_init',               [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts',    [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',               [ $this, 'handle_oauth_callback' ] );
        add_action( 'admin_notices',            [ $this, 'admin_notices' ] );
        add_action( 'admin_post_naws_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_naws_manual_sync',   [ $this, 'handle_manual_sync' ] );
        add_action( 'admin_post_naws_import_historical', [ $this, 'handle_import_historical' ] );
        add_action( 'admin_post_naws_disconnect',    [ $this, 'handle_disconnect' ] );
        add_action( 'admin_post_naws_export_weather', [ $this, 'handle_export_weather' ] );
        add_action( 'admin_post_naws_export_full',    [ $this, 'handle_export_full' ] );
        add_action( 'admin_post_naws_import_file',    [ $this, 'handle_import_upload' ] );
        add_action( 'admin_post_naws_save_appearance', [ $this, 'handle_save_appearance' ] );
        add_action( 'admin_post_naws_reset_appearance', [ $this, 'handle_reset_appearance' ] );
    }

    public function add_menu() {
        // add_menu_page() takes its icon as a data URI; base64 is the encoding
        // that format requires, applied to a string literal right here.
        $icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI, literal SVG visible above

        add_menu_page(
            naws__( 'plugin_name' ),
            naws__( 'plugin_name' ),
            'manage_options',
            'naws-dashboard',
            [ $this, 'page_dashboard' ],
            $icon,
            30
        );

        add_submenu_page( 'naws-dashboard', naws__( 'menu_dashboard' ), naws__( 'menu_dashboard' ), 'manage_options', 'naws-dashboard',      [ $this, 'page_dashboard' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_settings' ),  naws__( 'menu_settings' ),  'manage_options', 'naws-settings',       [ $this, 'page_settings' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_import' ),    naws__( 'menu_import' ),    'manage_options', 'naws-import',         [ $this, 'page_import' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_export' ),    naws__( 'menu_export' ),    'manage_options', 'naws-export',         [ $this, 'page_export' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_modules' ),   naws__( 'menu_modules' ),   'manage_options', 'naws-modules',        [ $this, 'page_modules' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_cron_log' ),  naws__( 'menu_cron_log' ),  'manage_options', 'naws-cron-log',       [ $this, 'page_cron_log' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_live' ),      naws__( 'menu_live' ),      'manage_options', 'naws-live-settings',  [ $this, 'page_live_settings' ] );
        add_submenu_page( 'naws-dashboard', naws__( 'menu_appearance' ),  naws__( 'menu_appearance' ), 'manage_options', 'naws-appearance',     [ $this, 'page_appearance' ] );
        add_submenu_page( 'naws-dashboard', 'Shortcodes',               'Shortcodes',               'manage_options', 'naws-shortcodes',     [ $this, 'page_shortcodes' ] );
        add_submenu_page( 'naws-dashboard', 'REST API',                  'REST API',                 'manage_options', 'naws-rest-api',       [ $this, 'page_rest_api' ] );
    }

    public function register_settings() {
        register_setting( 'naws_settings_group', 'naws_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    /**
     * Sanitize the settings array.
     *
     * The settings screen is split across several forms, and each one posts
     * only the fields it owns. This callback therefore MERGES over the
     * stored options instead of rebuilding them: a key absent from $input
     * means "this form does not manage that setting", not "reset it".
     *
     * Before 1.7.0 the array was rebuilt from scratch, so saving the
     * credentials form silently reset language, units, cron interval and
     * every forecast setting to their defaults. Some forms worked around it
     * with hidden mirror fields; those are now redundant but harmless,
     * since they submit exactly the value the merge would have kept.
     *
     * Checkboxes need care under merge semantics: an unchecked box is not
     * submitted at all and would otherwise be indistinguishable from "not
     * managed here". Every checkbox is therefore preceded in the markup by
     * a hidden input of the same name with value 0, so the key is always
     * present and the checkbox only overrides it when ticked.
     *
     * @param  array $input  Raw $_POST['naws_settings'] slice.
     * @return array         Full, sanitized settings array.
     */
    public function sanitize_settings( $input ) {
        $input    = is_array( $input ) ? $input : [];
        $old_opts = get_option( 'naws_settings', [] );
        $clean    = is_array( $old_opts ) ? $old_opts : [];

        // True only when the submitted form actually carried this field.
        $sent = static function ( string $key ) use ( $input ): bool {
            return array_key_exists( $key, $input );
        };

        if ( $sent( 'client_id' ) ) {
            $raw = sanitize_text_field( $input['client_id'] );
            // Encrypt if plaintext; skip if already encrypted (safety guard).
            // On a failed encrypt the key is left out of $clean entirely, so
            // the stored value survives untouched and no plaintext is written.
            if ( $raw !== '' && ! NAWS_Crypto::is_encrypted( $raw ) ) {
                $encrypted = NAWS_Crypto::encrypt( $raw );
                if ( $encrypted === null ) {
                    add_settings_error( 'naws', 'naws_crypto_failed', naws__( 'crypto_save_failed' ) );
                } else {
                    $clean['client_id'] = $encrypted;
                }
            } else {
                $clean['client_id'] = $raw;
            }
        }
        if ( $sent( 'client_secret' ) ) {
            $raw = sanitize_text_field( $input['client_secret'] );
            if ( $raw !== '' && ! NAWS_Crypto::is_encrypted( $raw ) ) {
                $encrypted = NAWS_Crypto::encrypt( $raw );
                if ( $encrypted === null ) {
                    add_settings_error( 'naws', 'naws_crypto_failed_secret', naws__( 'crypto_save_failed' ) );
                } else {
                    $clean['client_secret'] = $encrypted;
                }
            } else {
                $clean['client_secret'] = $raw;
            }
        }

        // Snap to a real WP-Cron schedule: an unlisted value such as 45 would
        // make wp_schedule_event() fail silently and stop polling altogether.
        if ( $sent( 'cron_interval' ) )  $clean['cron_interval']  = NAWS_Cron::normalise_interval( $input['cron_interval'] );
        if ( $sent( 'data_retention' ) ) $clean['data_retention'] = max( 30, intval( $input['data_retention'] ) );

        if ( $sent( 'language' ) ) {
            $valid_langs       = array_merge( [ 'auto' ], array_keys( NAWS_Lang::get_available_languages() ) );
            $clean['language'] = in_array( $input['language'], $valid_langs, true ) ? $input['language'] : 'auto';
        }
        if ( $sent( 'temperature_unit' ) ) $clean['temperature_unit'] = in_array( $input['temperature_unit'], ['C','F'], true ) ? $input['temperature_unit'] : 'C';
        if ( $sent( 'wind_unit' ) )        $clean['wind_unit']        = in_array( $input['wind_unit'], ['kmh','ms','mph','kn'], true ) ? $input['wind_unit'] : 'kmh';
        if ( $sent( 'pressure_unit' ) )    $clean['pressure_unit']    = in_array( $input['pressure_unit'], ['mbar','inHg','mmHg'], true ) ? $input['pressure_unit'] : 'mbar';
        if ( $sent( 'rain_unit' ) )        $clean['rain_unit']        = in_array( $input['rain_unit'], ['mm','in'], true ) ? $input['rain_unit'] : 'mm';
        if ( $sent( 'station_name' ) )     $clean['station_name']     = sanitize_text_field( $input['station_name'] );
        if ( $sent( 'night_mode' ) )       $clean['night_mode']       = ! empty( $input['night_mode'] ) ? 1 : 0;

        if ( $sent( 'heating_limit' ) ) $clean['heating_limit'] = self::clamp_float( $input['heating_limit'], 15.0, -10.0, 30.0 );
        if ( $sent( 'room_temp' ) )     $clean['room_temp']     = self::clamp_float( $input['room_temp'],     20.0,  10.0, 30.0 );
        if ( $sent( 'cooling_limit' ) ) $clean['cooling_limit'] = self::clamp_float( $input['cooling_limit'], 18.0,   0.0, 40.0 );

        // ── Forecast settings ─────────────────────────────────────────
        if ( $sent( 'forecast_provider' ) ) $clean['forecast_provider'] = in_array( $input['forecast_provider'], ['open_meteo','yr_no'], true ) ? $input['forecast_provider'] : 'open_meteo';
        if ( $sent( 'forecast_location' ) ) $clean['forecast_location'] = in_array( $input['forecast_location'], ['auto','manual'], true ) ? $input['forecast_location'] : 'auto';
        if ( $sent( 'forecast_days' ) )     $clean['forecast_days']     = max( 1, min( 7, intval( $input['forecast_days'] ) ) );
        if ( $sent( 'forecast_city' ) )     $clean['forecast_city']     = sanitize_text_field( $input['forecast_city'] );
        if ( $sent( 'forecast_country' ) )  $clean['forecast_country']  = sanitize_text_field( $input['forecast_country'] );

        // ── Weather icon thresholds (since 1.7.0) ─────────────────────
        // These belong in the settings and not hard-coded: a station on the
        // North German plain needs different values from one in the Alps.
        // Each is clamped to a physically sensible band so a typo cannot
        // disable a rule outright.
        if ( $sent( 'wx_show_on_dashboard' ) ) $clean['wx_show_on_dashboard'] = ! empty( $input['wx_show_on_dashboard'] ) ? '1' : '0';
        if ( $sent( 'wx_rain_heavy' ) ) $clean['wx_rain_heavy'] = self::clamp_float( $input['wx_rain_heavy'], 4.0,   0.1,  50.0 );
        if ( $sent( 'wx_snow_tw' ) )    $clean['wx_snow_tw']    = self::clamp_float( $input['wx_snow_tw'],    1.0, -20.0,   5.0 );
        if ( $sent( 'wx_fog_rh' ) )     $clean['wx_fog_rh']     = self::clamp_float( $input['wx_fog_rh'],    97.0,  80.0, 100.0 );
        if ( $sent( 'wx_fog_spread' ) ) $clean['wx_fog_spread'] = self::clamp_float( $input['wx_fog_spread'], 0.5,   0.1,   5.0 );
        if ( $sent( 'wx_storm_wind' ) ) $clean['wx_storm_wind'] = self::clamp_float( $input['wx_storm_wind'],75.0,  20.0, 200.0 );

        // Sidebar widget: only two lengths are offered, so anything else
        // is pulled to the nearer one rather than rejected.
        if ( $sent( 'wgt_days' ) ) {
            $clean['wgt_days'] = intval( $input['wgt_days'] ) < 4 ? 3 : 5;
        }

        // Same idea for the width: clamped into 250–500 by the one function
        // the shortcode and the template also use, so the stored value can
        // never disagree with what gets rendered.
        if ( $sent( 'wgt_width' ) ) {
            $clean['wgt_width'] = NAWS_Widget_Data::normalise_width( $input['wgt_width'] );
        }

        // Auto-resolved location name is written by NAWS_Forecast, never by
        // a form, so it is carried over untouched.
        $clean['forecast_auto_name'] = $old_opts['forecast_auto_name'] ?? '';

        // If location or provider changed, flush forecast cache.
        // Both sides are read defensively: under merge semantics a key can
        // be absent from both the old options and this submission.
        if ( ( ( $clean['forecast_provider'] ?? 'open_meteo' ) !== ( $old_opts['forecast_provider'] ?? 'open_meteo' ) )
          || ( ( $clean['forecast_location'] ?? 'auto' )       !== ( $old_opts['forecast_location'] ?? 'auto' ) )
          || ( ( $clean['forecast_city']     ?? '' )           !== ( $old_opts['forecast_city'] ?? '' ) )
          || ( ( $clean['forecast_country']  ?? '' )           !== ( $old_opts['forecast_country'] ?? '' ) )
        ) {
            $clean['forecast_auto_name'] = ''; // reset auto name
            NAWS_Forecast::flush_cache();
        }

        do_action( 'naws_settings_saved' );
        NAWS_Lang::reset();
        return $clean;
    }

    /**
     * Float from user input, clamped to a band, falling back to a default.
     *
     * An empty field means "use the default", not "use zero" — so empty and
     * non-numeric input both fall back instead of clamping to the minimum.
     */
    private static function clamp_float( $value, float $default, float $min, float $max ): float {
        if ( $value === null || $value === '' || ! is_numeric( $value ) ) {
            return $default;
        }
        return max( $min, min( $max, (float) $value ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'naws-' ) === false ) return;

        wp_enqueue_style( 'naws-admin', NAWS_PLUGIN_URL . 'assets/css/admin.css', [], NAWS_VERSION );

        $js_deps = [ 'jquery' ];
        // Load WP Color Picker on appearance page
        if ( strpos( $hook, 'naws-appearance' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            $js_deps[] = 'wp-color-picker';
        }

        // The shortcodes page previews the live weather icon and the
        // appearance page previews the sidebar widget; both need the
        // frontend stylesheet for keyframes and layout. The
        // 'naws-frontend' handle is registered on wp_enqueue_scripts and
        // does not exist in the admin, so the file gets its own handle.
        if ( strpos( $hook, 'naws-shortcodes' ) !== false || strpos( $hook, 'naws-appearance' ) !== false ) {
            wp_enqueue_style( 'naws-weather-icon', NAWS_PLUGIN_URL . 'assets/css/frontend.css', [], NAWS_VERSION );
        }

        wp_enqueue_script( 'naws-admin', NAWS_PLUGIN_URL . 'assets/js/admin.js', $js_deps, NAWS_VERSION, true );

        wp_localize_script( 'naws-admin', 'nawsAdmin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'naws_admin_nonce' ),
            'strings'  => [
                'syncing'          => naws__( 'syncing' ),
                'sync_done'        => naws__( 'sync_complete' ),
                'importing'        => naws__( 'importing' ),
                'import_done'      => naws__( 'import_complete' ),
                'error'            => naws__( 'error_occurred' ),
                'inactive'         => naws__( 'inactive' ),
                'toggle_error'     => naws__( 'toggle_error' ),
                'request_failed'   => naws__( 'request_failed' ),
                'sc_copy'          => naws__( 'sc_copy' ),
                'sc_copied'        => naws__( 'sc_copied' ),
                'daily_summary'    => naws__( 'daily_summary' ),
                'ls_mod_deactivate'=> naws__( 'ls_mod_deactivate' ),
                'ls_mod_activate'  => naws__( 'ls_mod_activate' ),
                'ls_count_active'  => naws__( 'ls_count_active' ),
                'ls_chart_disable' => naws__( 'ls_chart_disable' ),
                'ls_chart_enable'  => naws__( 'ls_chart_enable' ),
                'ls_saving'        => naws__( 'ls_saving' ),
                'ls_saved'         => naws__( 'ls_saved' ),
                'ls_error'         => naws__( 'ls_error' ),
            ],
        ] );
    }

    // ----------------------------------------------------------------
    // OAuth Callback handler
    // ----------------------------------------------------------------
    public function handle_oauth_callback() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'naws-settings' ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state used instead
        if ( ! isset( $_GET['code'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- origin proven by the OAuth state below, permission by current_user_can() beneath this line

        // Who may connect an account is a question the OAuth state cannot
        // answer. The state proves the request belongs to a flow started on
        // this site; it says nothing about who is following the redirect.
        // Netatmo returns the code to one fixed URL, so until now anyone
        // logged in who reached that URL stored credentials and triggered a
        // sync.
        //
        // This stands before the state is read, not after: a request without
        // the capability must not consume the pending authorization either,
        // or a subscriber could make every connection attempt fail.
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Validate state token against stored option
        $state          = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $expected_state = get_option( 'naws_oauth_state', '' );
        $state_time     = (int) get_option( 'naws_oauth_state_time', 0 );

        // Every part is required and none of it optional: a state came back,
        // one was stored, the two are the same, and the flow is younger than
        // ten minutes. A second way in used to sit below this - the value was
        // also accepted as wp_verify_nonce( $state, 'naws_oauth' ). Nothing in
        // the plugin ever created that nonce, so it was an acceptance path
        // without a producer, and it turned one plain condition into a
        // compound one whose failing branch still let the request through.
        $state_valid = ! empty( $state )
                    && ! empty( $expected_state )
                    && hash_equals( $expected_state, $state )
                    && ( time() - $state_time ) < 600;

        if ( ! $state_valid ) {
            add_settings_error( 'naws', 'naws_oauth_invalid',
                'Invalid OAuth state. Please try connecting again.' );
            return;
        }

        delete_option( 'naws_oauth_state' );
        delete_option( 'naws_oauth_state_time' );

        $api      = new NAWS_API();
        $redirect = admin_url( 'admin.php?page=naws-settings' );
        $result   = $api->exchange_code( sanitize_text_field( wp_unslash( $_GET['code']  ) ), $redirect ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the OAuth state was verified above and consumed; a nonce cannot ride along on Netatmo's redirect

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'naws', 'naws_oauth_error', $result->get_error_message() );
        } else {
            // Clear re-auth flag - we now have fresh tokens
            delete_option( 'naws_auth_required' );
            delete_option( 'naws_oauth_debug' );
            $api->sync_current_data();
            add_settings_error( 'naws', 'naws_oauth_ok',
                'Successfully connected to Netatmo!', 'success' );
        }
    }

    public function handle_save_settings() {
        check_admin_referer( 'naws_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $input = isset( $_POST['naws_settings'] ) ? wp_unslash( $_POST['naws_settings'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_settings()
        update_option( 'naws_settings', $this->sanitize_settings( $input ) );

        // sanitize_settings() reports a failed encrypt through
        // add_settings_error(), and that alone reaches nobody here: this form
        // posts to admin-post.php, not to options.php, so nothing writes the
        // transient the settings screen reads back, and admin_notices never
        // fires on this request. The redirect below would drop the message and
        // the target page would answer a discarded secret with "settings
        // saved". The state therefore travels as a query argument.
        $crypto_failed = false;
        foreach ( get_settings_errors( 'naws' ) as $naws_error ) {
            if ( in_array( $naws_error['code'], [ 'naws_crypto_failed', 'naws_crypto_failed_secret' ], true ) ) {
                $crypto_failed = true;
                break;
            }
        }

        // Did this request actually hand a credential to encrypt()? Only then
        // does "no error" mean an encrypt succeeded. The same action also
        // serves the appearance page's widget form, which carries no
        // credentials and may not clear a warning it knows nothing about.
        $encrypted_something = false;
        foreach ( [ 'client_id', 'client_secret' ] as $naws_field ) {
            if ( isset( $input[ $naws_field ] ) && is_string( $input[ $naws_field ] ) && $input[ $naws_field ] !== '' ) {
                $encrypted_something = true;
            }
        }

        // A failed token write leaves a red notice behind that is cleared on
        // the next successful write. Encrypting the credentials is such a
        // write, so the notice does not outlive the condition it describes.
        if ( $encrypted_something && ! $crypto_failed ) {
            delete_option( 'naws_crypto_write_failed' );
        }

        // naws_save_settings is now posted from two different admin pages
        // (the settings page and the appearance page's widget form), so a
        // fixed redirect target sends the appearance-page save to the wrong
        // screen. wp_nonce_field() already emits the standard referer hidden
        // field, so wp_get_referer() recovers the page the form actually
        // lives on; it falls back to the settings page if the referer is
        // missing or fails WordPress's validation. Both pages' success
        // notices key off `updated`, so it is re-added explicitly instead of
        // assuming the referer URL still carries it.
        $redirect_to = wp_get_referer() ?: admin_url( 'admin.php?page=naws-settings' );

        if ( $crypto_failed ) {
            // The referer is the page a previous save already redirected to,
            // so it can still carry `updated` from then. Dropping it keeps the
            // green notice from standing next to the red one.
            $redirect_to = remove_query_arg( 'updated', $redirect_to );
            wp_safe_redirect( add_query_arg( 'naws_crypto_failed', '1', $redirect_to ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'updated', '1', $redirect_to ) );
        exit;
    }

    public function handle_manual_sync() {
        check_admin_referer( 'naws_manual_sync' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $api    = new NAWS_API();
        $result = $api->sync_current_data();

        // Always reschedule cron after manual sync so it doesn't stay stuck
        NAWS_Cron::instance()->reschedule();

        $msg = is_wp_error( $result ) ? '&error=' . rawurlencode( $result->get_error_message() ) : '&synced=1';
        wp_safe_redirect( wp_nonce_url( admin_url( 'admin.php?page=naws-dashboard' . $msg ), 'naws_notice' ) );
        exit;
    }

    public function handle_import_historical() {
        check_admin_referer( 'naws_import_historical' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        wp_safe_redirect( admin_url( 'admin.php?page=naws-import&started=1' ) );
        exit;
    }

    public function handle_disconnect() {
        check_admin_referer( 'naws_disconnect' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        delete_option( 'naws_access_token' );
        delete_option( 'naws_refresh_token' );
        delete_option( 'naws_token_expiry' );
        delete_option( 'naws_oauth_debug' );

        wp_safe_redirect( admin_url( 'admin.php?page=naws-settings&disconnected=1' ) );
        exit;
    }

    public function admin_notices() {
        settings_errors( 'naws' );
    }

    // ----------------------------------------------------------------
    // Admin Pages
    // ----------------------------------------------------------------

    public function page_dashboard() {
        $modules       = NAWS_Database::get_modules();
        $latest        = NAWS_Database::get_latest_readings();
        $total         = NAWS_Database::count_readings();
        $last_sync     = get_option( 'naws_last_sync', 0 );
        $last_error    = get_option( 'naws_last_sync_error', '' );
        $next_run      = NAWS_Cron::get_next_run();
        $options       = get_option( 'naws_settings', [] );

        // Organize latest readings by module
        $readings_by_module = [];
        foreach ( $latest as $r ) {
            $readings_by_module[ $r['module_id'] ][ $r['parameter'] ] = $r['value'];
        }

        include NAWS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function page_settings() {
        $options      = get_option( 'naws_settings', [] );
        // Transparent decrypt if values were encrypted by an older version
        foreach ( [ 'client_id', 'client_secret' ] as $k ) {
            if ( isset( $options[ $k ] ) && NAWS_Crypto::is_encrypted( $options[ $k ] ) ) {
                $options[ $k ] = NAWS_Crypto::decrypt( $options[ $k ] );
            }
        }
        $is_connected = NAWS_Crypto::get_option( 'naws_access_token' ) !== ''
                     && NAWS_Crypto::get_option( 'naws_refresh_token' ) !== '';
        $redirect_uri = admin_url( 'admin.php?page=naws-settings' );
        $api          = new NAWS_API();
        // Don't regenerate auth URL during OAuth callback (would overwrite state)
        $auth_url     = isset( $_GET['code'] ) ? '' : $api->get_auth_url( $redirect_uri ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        include NAWS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function page_import() {
        $modules     = NAWS_Database::get_modules( true ); // active only for import
        $range       = NAWS_Database::get_data_range();
        $daily_range = NAWS_Database::get_daily_data_range();
        include NAWS_PLUGIN_DIR . 'admin/views/import.php';
    }

    public function page_modules() {
        $modules = NAWS_Database::get_modules();
        include NAWS_PLUGIN_DIR . 'admin/views/modules.php';
    }

    public function page_cron_log() {
        $log      = get_option( 'naws_cron_log', [] );
        $next_run = NAWS_Cron::get_next_run();
        include NAWS_PLUGIN_DIR . 'admin/views/cron-log.php';
    }

    public function page_live_settings() {
        include NAWS_PLUGIN_DIR . 'admin/views/live-settings.php';
    }

    public function page_shortcodes() {
        $modules = NAWS_Database::get_modules();
        include NAWS_PLUGIN_DIR . 'admin/views/shortcodes.php';
    }

    public function page_rest_api() {
        include NAWS_PLUGIN_DIR . 'admin/views/rest-api-docs.php';
    }

    public function page_appearance() {
        $colors   = NAWS_Colors::get_all();
        $defaults = NAWS_Colors::get_defaults();
        $groups   = NAWS_Colors::get_groups();
        include NAWS_PLUGIN_DIR . 'admin/views/appearance.php';
    }

    public function handle_save_appearance() {
        check_admin_referer( 'naws_save_appearance' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        // Sanitize the superglobal directly rather than through an intermediate
        // $raw variable. Neither PHPCS nor the plugin review scanner tracks
        // sanitization across an assignment, so the indirect form gets flagged
        // as unsanitized input even though it is not.
        $input = isset( $_POST['naws_appearance'] ) && is_array( $_POST['naws_appearance'] )
            ? map_deep( wp_unslash( $_POST['naws_appearance'] ), 'sanitize_text_field' )
            : [];
        update_option( NAWS_Colors::OPTION_KEY, NAWS_Colors::sanitize( $input ) );
        NAWS_Colors::flush_cache();

        wp_safe_redirect( admin_url( 'admin.php?page=naws-appearance&updated=1' ) );
        exit;
    }

    public function handle_reset_appearance() {
        check_admin_referer( 'naws_reset_appearance' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        delete_option( NAWS_Colors::OPTION_KEY );
        NAWS_Colors::flush_cache();

        wp_safe_redirect( admin_url( 'admin.php?page=naws-appearance&reset=1' ) );
        exit;
    }

    public function page_export() {
        $daily_count = NAWS_Database::count_daily_summaries();
        $daily_range = NAWS_Database::get_daily_data_range();
        $modules     = NAWS_Database::get_modules();
        include NAWS_PLUGIN_DIR . 'admin/views/export.php';
    }

    // ----------------------------------------------------------------
    // Export / Import Handlers
    // ----------------------------------------------------------------

    public function handle_export_weather() {
        check_admin_referer( 'naws_export_weather' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        NAWS_Export::export_weather_data();
        // export_weather_data() calls exit, so nothing runs after this
    }

    public function handle_export_full() {
        check_admin_referer( 'naws_export_full' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        NAWS_Export::export_full_backup();
        // export_full_backup() calls exit, so nothing runs after this
    }

    public function handle_import_upload() {
        check_admin_referer( 'naws_import_file' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $redirect_url = admin_url( 'admin.php?page=naws-export' );
        $nonce_url    = function( $url ) { return wp_nonce_url( $url, 'naws_notice' ); };

        // Validate file upload
        if ( empty( $_FILES['naws_import_file'] ) || ( $_FILES['naws_import_file']['error'] ?? -1 ) !== UPLOAD_ERR_OK ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- error code is integer, not user-controlled string
            wp_safe_redirect( $nonce_url( $redirect_url . '&import_error=' . rawurlencode( naws__( 'import_file_invalid' ) ) ) );
            exit;
        }

        // The entry is handed to wp_handle_upload() below - the WordPress API
        // for this - which performs the real validation (upload error, MIME
        // type, move_uploaded_file). It needs the array intact, so the array
        // itself cannot be passed through a sanitizer. Every field read
        // directly in this method is sanitized at its point of use.
        $file      = $_FILES['naws_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see comment above; $_FILES is not slashed
        $safe_name = sanitize_file_name( $file['name'] ?? '' );

        // Check extension
        $ext = strtolower( pathinfo( $safe_name, PATHINFO_EXTENSION ) );
        if ( 'json' !== $ext ) {
            wp_safe_redirect( $nonce_url( $redirect_url . '&import_error=' . rawurlencode( naws__( 'import_file_invalid' ) ) ) );
            exit;
        }

        // Check file size (max 100 MB)
        if ( intval( $file['size'] ?? 0 ) > 100 * MB_IN_BYTES ) {
            wp_safe_redirect( $nonce_url( $redirect_url . '&import_error=' . rawurlencode( naws__( 'import_file_too_large' ) ) ) );
            exit;
        }

        // Move to safe location in uploads dir via wp_handle_upload()
        $overrides = [
            'test_form' => false,
            'test_type' => false,
            'mimes'     => [ 'json' => 'application/json' ],
        ];
        $uploaded = wp_handle_upload( $file, $overrides );
        if ( isset( $uploaded['error'] ) ) {
            wp_safe_redirect( $nonce_url( $redirect_url . '&import_error=' . rawurlencode( 'Could not save uploaded file.' ) ) );
            exit;
        }
        $temp_path = $uploaded['file'];

        // Validate JSON structure
        $validation = NAWS_Export::validate_import_file( $temp_path );
        if ( ! $validation['valid'] ) {
            wp_delete_file( $temp_path );
            wp_safe_redirect( $nonce_url( $redirect_url . '&import_error=' . rawurlencode( $validation['error'] ) ) );
            exit;
        }

        // Store temp path and meta for chunked AJAX processing
        $meta = $validation['meta'];
        $overwrite = ! empty( $_POST['naws_overwrite_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $meta['overwrite_settings'] = $overwrite;

        set_transient( 'naws_import_temp_file', $temp_path, HOUR_IN_SECONDS );
        set_transient( 'naws_import_meta', $meta, HOUR_IN_SECONDS );

        NAWS_Logger::info( 'export', 'Import file uploaded', [
            'type'      => $meta['export_type'],
            'row_count' => $meta['row_count'] ?? 0,
            'size'      => intval( $file['size'] ?? 0 ),
        ] );

        wp_safe_redirect( $redirect_url . '&import_ready=1' );
        exit;
    }

}
