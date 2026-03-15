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
        $icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>' );

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

    public function sanitize_settings( $input ) {
        $clean = [];
        $clean['client_id']       = sanitize_text_field( $input['client_id']     ?? '' );
        $clean['client_secret']   = sanitize_text_field( $input['client_secret'] ?? '' );
        $clean['cron_interval']   = max( 5, intval( $input['cron_interval'] ?? 10 ) );
        $clean['data_retention']  = max( 30, intval( $input['data_retention'] ?? 365 ) );
        $valid_langs              = array_merge( ['auto'], array_keys( NAWS_Lang::get_available_languages() ) );
        $clean['language']        = in_array( $input['language'] ?? 'auto', $valid_langs, true ) ? $input['language'] : 'auto';
        $clean['temperature_unit'] = in_array( $input['temperature_unit'] ?? 'C', ['C','F'], true ) ? $input['temperature_unit'] : 'C';
        $clean['wind_unit']       = in_array( $input['wind_unit'] ?? 'kmh', ['kmh','ms','mph','kn'], true ) ? $input['wind_unit'] : 'kmh';
        $clean['pressure_unit']   = in_array( $input['pressure_unit'] ?? 'mbar', ['mbar','inHg','mmHg'], true ) ? $input['pressure_unit'] : 'mbar';
        $clean['rain_unit']       = in_array( $input['rain_unit'] ?? 'mm', ['mm','in'], true ) ? $input['rain_unit'] : 'mm';
        $clean['station_name']    = sanitize_text_field( $input['station_name'] ?? '' );
        $clean['night_mode']      = ! empty( $input['night_mode'] ) ? 1 : 0;

        // ── Forecast settings ─────────────────────────────────────────
        $clean['forecast_provider'] = in_array( $input['forecast_provider'] ?? 'open_meteo', ['open_meteo','yr_no'], true )
            ? $input['forecast_provider'] : 'open_meteo';
        $clean['forecast_location'] = in_array( $input['forecast_location'] ?? 'auto', ['auto','manual'], true )
            ? $input['forecast_location'] : 'auto';
        $clean['forecast_days']     = max( 1, min( 7, intval( $input['forecast_days'] ?? 5 ) ) );
        $clean['forecast_city']     = sanitize_text_field( $input['forecast_city'] ?? '' );
        $clean['forecast_country']  = sanitize_text_field( $input['forecast_country'] ?? '' );

        // Preserve auto-resolved name (set by Forecast class, not user)
        $old_opts = get_option( 'naws_settings', [] );
        $clean['forecast_auto_name'] = $old_opts['forecast_auto_name'] ?? '';

        // If location or provider changed, flush forecast cache
        if ( ( $clean['forecast_provider'] !== ( $old_opts['forecast_provider'] ?? 'open_meteo' ) )
          || ( $clean['forecast_location'] !== ( $old_opts['forecast_location'] ?? 'auto' ) )
          || ( $clean['forecast_city']     !== ( $old_opts['forecast_city'] ?? '' ) )
          || ( $clean['forecast_country']  !== ( $old_opts['forecast_country'] ?? '' ) )
        ) {
            $clean['forecast_auto_name'] = ''; // reset auto name
            NAWS_Forecast::flush_cache();
        }

        do_action( 'naws_settings_saved' );
        NAWS_Lang::reset();
        return $clean;
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

        wp_enqueue_script( 'naws-admin', NAWS_PLUGIN_URL . 'assets/js/admin.js', $js_deps, NAWS_VERSION, true );

        wp_localize_script( 'naws-admin', 'nawsAdmin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'naws_admin_nonce' ),
            'strings'  => [
                'syncing'       => naws__( 'syncing' ),
                'sync_done'     => naws__( 'sync_complete' ),
                'importing'     => naws__( 'importing' ),
                'import_done'   => naws__( 'import_complete' ),
                'error'         => naws__( 'error_occurred' ),
            ],
        ] );
    }

    // ----------------------------------------------------------------
    // OAuth Callback handler
    // ----------------------------------------------------------------
    public function handle_oauth_callback() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'naws-settings' ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state used instead
        if ( ! isset( $_GET['code'] ) ) return;

        // Validate state token against stored option
        $state          = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $expected_state = get_option( 'naws_oauth_state', '' );
        $state_time     = (int) get_option( 'naws_oauth_state_time', 0 );

        // State must match AND not be older than 10 minutes
        $state_valid = ! empty( $state )
                    && ! empty( $expected_state )
                    && hash_equals( $expected_state, $state )
                    && ( time() - $state_time ) < 600;

        if ( ! $state_valid ) {
            // Fallback: also accept valid wp nonce for backwards compat
            if ( ! wp_verify_nonce( $state, 'naws_oauth' ) ) {
                add_settings_error( 'naws', 'naws_oauth_invalid',
                    'Invalid OAuth state. Please try connecting again.' );
                return;
            }
        }

        delete_option( 'naws_oauth_state' );
        delete_option( 'naws_oauth_state_time' );

        $api      = new NAWS_API();
        $redirect = admin_url( 'admin.php?page=naws-settings' );
        $result   = $api->exchange_code( sanitize_text_field( wp_unslash( $_GET['code']  ) ), $redirect );

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

        wp_safe_redirect( admin_url( 'admin.php?page=naws-settings&updated=1' ) );
        exit;
    }

    public function handle_manual_sync() {
        check_admin_referer( 'naws_manual_sync' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $api    = new NAWS_API();
        $result = $api->sync_current_data();

        // Always reschedule cron after manual sync so it doesn't stay stuck
        NAWS_Cron::instance()->reschedule();

        $msg = is_wp_error( $result ) ? '&error=' . urlencode( $result->get_error_message() ) : '&synced=1';
        wp_safe_redirect( admin_url( 'admin.php?page=naws-dashboard' . $msg ) );
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

        $input = isset( $_POST['naws_appearance'] ) ? wp_unslash( $_POST['naws_appearance'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in NAWS_Colors::sanitize()
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

        // Validate file upload
        if ( empty( $_FILES['naws_import_file'] ) || $_FILES['naws_import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_safe_redirect( $redirect_url . '&import_error=' . urlencode( naws__( 'import_file_invalid' ) ) );
            exit;
        }

        $file = $_FILES['naws_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Check extension
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( 'json' !== $ext ) {
            wp_safe_redirect( $redirect_url . '&import_error=' . urlencode( naws__( 'import_file_invalid' ) ) );
            exit;
        }

        // Check file size (max 100 MB)
        if ( $file['size'] > 100 * MB_IN_BYTES ) {
            wp_safe_redirect( $redirect_url . '&import_error=' . urlencode( naws__( 'import_file_too_large' ) ) );
            exit;
        }

        // Move to safe location in uploads dir
        $upload_dir = wp_upload_dir();
        $temp_path  = $upload_dir['basedir'] . '/naws-import-temp-' . wp_generate_password( 8, false ) . '.json';

        if ( ! move_uploaded_file( $file['tmp_name'], $temp_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            wp_safe_redirect( $redirect_url . '&import_error=' . urlencode( 'Could not save uploaded file.' ) );
            exit;
        }

        // Validate JSON structure
        $validation = NAWS_Export::validate_import_file( $temp_path );
        if ( ! $validation['valid'] ) {
            wp_delete_file( $temp_path );
            wp_safe_redirect( $redirect_url . '&import_error=' . urlencode( $validation['error'] ) );
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
            'size'      => $file['size'],
        ] );

        wp_safe_redirect( $redirect_url . '&import_ready=1' );
        exit;
    }

}
