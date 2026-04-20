<?php
/**
 * NAWS REST API
 *
 * Provides read-only JSON endpoints for external consumers
 * (Google Charts, Grafana, custom dashboards, etc.).
 *
 * Namespace : naws/v1
 * Auth      : API key via X-NAWS-Key header or ?api_key= query param
 * Rate limit: configurable, default 60 req/min per key
 *
 * @since 0.9.96
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Rest_API {

    /** REST namespace */
    const API_NS = 'naws/v1';

    /** Settings option key */
    const OPT = 'naws_rest_api';

    /** Default rate limit (requests per minute) */
    const DEFAULT_RATE_LIMIT = 60;

    /* ================================================================
     * Bootstrap
     * ================================================================*/

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /* ================================================================
     * Route registration
     * ================================================================*/

    public static function register_routes() {

        if ( ! self::is_enabled() ) return;

        // GET /naws/v1/station
        register_rest_route( self::API_NS, '/station', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'endpoint_station' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /naws/v1/modules
        register_rest_route( self::API_NS, '/modules', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'endpoint_modules' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /naws/v1/current
        register_rest_route( self::API_NS, '/current', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'endpoint_current' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'module_id' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // GET /naws/v1/readings
        register_rest_route( self::API_NS, '/readings', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'endpoint_readings' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'module_id' => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'parameter' => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'from'      => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'to'        => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'group_by'  => [
                    'type'    => 'string',
                    'enum'    => [ 'raw', 'hour', 'day', 'week', 'month' ],
                    'default' => 'raw',
                ],
                'limit'   => [ 'type' => 'integer', 'default' => 1000, 'minimum' => 1, 'maximum' => 5000 ],
                'convert' => [ 'type' => 'boolean', 'default' => true ],
            ],
        ] );

        // GET /naws/v1/daily
        register_rest_route( self::API_NS, '/daily', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'endpoint_daily' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'from'     => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'to'       => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'fields'   => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                'group_by' => [
                    'type'    => 'string',
                    'enum'    => [ 'day', 'week', 'month', 'year' ],
                    'default' => 'day',
                ],
                'convert' => [ 'type' => 'boolean', 'default' => true ],
            ],
        ] );
    }

    /* ================================================================
     * Authentication
     * ================================================================*/

    /**
     * Validate API key and enforce rate limit.
     *
     * @param  WP_REST_Request $request
     * @return true|WP_Error
     */
    public static function authenticate( WP_REST_Request $request ) {

        $stored_key = self::get_api_key();
        if ( empty( $stored_key ) ) {
            return new WP_Error(
                'naws_api_not_configured',
                'REST API key has not been generated yet.',
                [ 'status' => 503 ]
            );
        }

        // Accept key from header or query parameter
        $provided = $request->get_header( 'X-NAWS-Key' );
        if ( empty( $provided ) ) {
            $provided = $request->get_param( 'api_key' );
        }

        if ( empty( $provided ) || ! hash_equals( $stored_key, $provided ) ) {
            return new WP_Error(
                'naws_unauthorized',
                'Invalid or missing API key. Supply via X-NAWS-Key header or api_key parameter.',
                [ 'status' => 401 ]
            );
        }

        // Rate limiting
        if ( ! self::check_rate_limit() ) {
            return new WP_Error(
                'naws_rate_limited',
                'Rate limit exceeded. Try again in 60 seconds.',
                [ 'status' => 429 ]
            );
        }

        return true;
    }

    /* ================================================================
     * Endpoints
     * ================================================================*/

    /**
     * GET /station – Station metadata.
     */
    public static function endpoint_station( WP_REST_Request $request ) {
        $opts    = get_option( 'naws_settings', [] );
        $modules = NAWS_Database::get_modules( true );

        if ( empty( $modules ) ) {
            return new WP_Error(
                'naws_no_modules',
                'No active modules found. Ensure the station has been synced at least once.',
                [ 'status' => 404 ]
            );
        }

        // Count module types
        $types = [];
        foreach ( $modules as $m ) {
            $types[ $m['module_type'] ] = ( $types[ $m['module_type'] ] ?? 0 ) + 1;
        }

        return rest_ensure_response( [
            'station_id'   => $modules[0]['station_id'] ?? null,
            'latitude'     => (float) ( $opts['latitude']  ?? 0 ),
            'longitude'    => (float) ( $opts['longitude'] ?? 0 ),
            'altitude'     => (float) ( $opts['altitude']  ?? 0 ),
            'timezone'     => $opts['timezone'] ?? get_option( 'timezone_string', 'UTC' ),
            'modules'      => count( $modules ),
            'module_types' => $types,
            'units'        => [
                'temperature' => ( $opts['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C',
                'rain'        => $opts['rain_unit']     ?? 'mm',
                'wind'        => NAWS_Helpers::wind_unit_label_public( $opts['wind_unit'] ?? 'kmh' ),
                'pressure'    => NAWS_Helpers::get_unit( 'Pressure' ),
            ],
            'last_sync'    => get_option( 'naws_last_sync_time', null ),
        ] );
    }

    /**
     * GET /modules – Active module list.
     */
    public static function endpoint_modules( WP_REST_Request $request ) {
        $modules = NAWS_Database::get_modules( true );
        $out     = [];

        foreach ( $modules as $m ) {
            $out[] = [
                'module_id'   => $m['module_id'],
                'module_name' => $m['module_name'],
                'module_type' => $m['module_type'],
                'data_types'  => array_filter( array_map( 'trim', explode( ',', $m['data_types'] ?? '' ) ) ),
                'last_seen'   => ! empty( $m['last_seen'] ) ? (int) $m['last_seen'] : null,
                'firmware'    => $m['firmware'] ?? null,
                'battery_vp'  => $m['battery_vp'] ?? null,
            ];
        }

        return rest_ensure_response( [ 'modules' => $out ] );
    }

    /**
     * GET /current – Latest sensor readings.
     */
    public static function endpoint_current( WP_REST_Request $request ) {
        $module_id = $request->get_param( 'module_id' ) ?: null;
        $readings  = NAWS_Database::get_latest_readings( $module_id );
        $modules   = NAWS_Database::get_modules( true );

        $module_map = [];
        foreach ( $modules as $m ) {
            $module_map[ $m['module_id'] ] = $m;
        }

        $out = [];
        foreach ( $readings as $row ) {
            $out[] = [
                'module_id'   => $row['module_id'],
                'module_name' => $module_map[ $row['module_id'] ]['module_name'] ?? '',
                'module_type' => $module_map[ $row['module_id'] ]['module_type'] ?? '',
                'parameter'   => $row['parameter'],
                'value'       => NAWS_Helpers::format_value( $row['parameter'], (float) $row['value'] ),
                'raw_value'   => round( (float) $row['value'], 4 ),
                'unit'        => NAWS_Helpers::get_unit( $row['parameter'] ),
                'recorded_at' => (int) $row['recorded_at'],
                'recorded_at_iso' => gmdate( 'c', (int) $row['recorded_at'] ),
            ];
        }

        // Append rolling 24h rain sum
        $rain_module = null;
        foreach ( $module_map as $mid => $mod ) {
            if ( ( $mod['module_type'] ?? '' ) === 'NAModule3' ) {
                $rain_module = $mid;
                break;
            }
        }
        if ( $rain_module ) {
            $rolling = NAWS_Database::get_rain_rolling_24h( $rain_module );
            if ( $rolling !== null ) {
                $out[] = [
                    'module_id'       => $rain_module,
                    'module_name'     => $module_map[ $rain_module ]['module_name'] ?? '',
                    'module_type'     => 'NAModule3',
                    'parameter'       => 'rain_rolling_24h',
                    'value'           => NAWS_Helpers::format_value( 'Rain', $rolling ),
                    'raw_value'       => round( $rolling, 4 ),
                    'unit'            => NAWS_Helpers::get_unit( 'Rain' ),
                    'recorded_at'     => time(),
                    'recorded_at_iso' => gmdate( 'c' ),
                ];
            }
        }

        return rest_ensure_response( [
            'count'    => count( $out ),
            'readings' => $out,
        ] );
    }

    /**
     * GET /readings – Raw sensor readings with filtering and grouping.
     */
    public static function endpoint_readings( WP_REST_Request $request ) {
        $module_id = $request->get_param( 'module_id' ) ?: null;
        $parameter = $request->get_param( 'parameter' );
        $from      = $request->get_param( 'from' );
        $to        = $request->get_param( 'to' );
        $group_by  = $request->get_param( 'group_by' ) ?: 'raw';
        $limit     = (int) ( $request->get_param( 'limit' ) ?: 1000 );
        $convert   = $request->get_param( 'convert' ) !== false;

        // Parse date strings (ISO 8601 or Unix timestamp)
        $date_from = $from ? self::parse_timestamp( $from ) : strtotime( '-24 hours' );
        $date_to   = $to   ? self::parse_timestamp( $to )   : time();

        if ( ! $date_from || ! $date_to ) {
            return new WP_Error( 'naws_invalid_date', 'Invalid date format. Use ISO 8601 or Unix timestamp.', [ 'status' => 400 ] );
        }

        $params = $parameter ? array_map( 'trim', explode( ',', $parameter ) ) : null;

        $rows = NAWS_Database::get_readings( [
            'module_id'  => $module_id,
            'parameter'  => $params,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'group_by'   => $group_by,
            'limit'      => min( $limit, 5000 ),
        ] );

        $out = [];
        foreach ( $rows as $row ) {
            $val   = (float) $row['value'];
            $entry = [
                'module_id'       => $row['module_id'],
                'parameter'       => $row['parameter'],
                'value'           => $convert ? NAWS_Helpers::format_value( $row['parameter'], $val ) : round( $val, 4 ),
                'unit'            => NAWS_Helpers::get_unit( $row['parameter'] ),
                'recorded_at'     => (int) $row['recorded_at'],
                'recorded_at_iso' => gmdate( 'c', (int) $row['recorded_at'] ),
            ];
            // Include aggregation fields when grouped
            if ( $group_by !== 'raw' ) {
                $entry['min_value']   = isset( $row['min_value'] ) ? round( (float) $row['min_value'], 4 ) : null;
                $entry['max_value']   = isset( $row['max_value'] ) ? round( (float) $row['max_value'], 4 ) : null;
                $entry['data_points'] = isset( $row['data_points'] ) ? (int) $row['data_points'] : null;
            }
            $out[] = $entry;
        }

        return rest_ensure_response( [
            'count'    => count( $out ),
            'from'     => gmdate( 'c', $date_from ),
            'to'       => gmdate( 'c', $date_to ),
            'group_by' => $group_by,
            'readings' => $out,
        ] );
    }

    /**
     * GET /daily – Daily summary data.
     */
    public static function endpoint_daily( WP_REST_Request $request ) {
        $from     = $request->get_param( 'from' );
        $to       = $request->get_param( 'to' );
        $fields   = $request->get_param( 'fields' );
        $group_by = $request->get_param( 'group_by' ) ?: 'day';
        $convert  = $request->get_param( 'convert' ) !== false;

        $date_from = $from ?: wp_date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to   = $to   ?: wp_date( 'Y-m-d' );

        // Validate date format
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ||
             ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            return new WP_Error( 'naws_invalid_date', 'Use YYYY-MM-DD format for from/to.', [ 'status' => 400 ] );
        }

        $allowed = [ 'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum',
                     'humidity_avg', 'co2_avg', 'noise_avg', 'wind_avg', 'gust_max' ];
        $req_fields = $fields
            ? array_intersect( array_map( 'trim', explode( ',', $fields ) ), $allowed )
            : [ 'temp_min', 'temp_max', 'temp_avg', 'pressure_avg', 'rain_sum' ];

        if ( empty( $req_fields ) ) {
            return new WP_Error( 'naws_invalid_fields', 'No valid fields. Allowed: ' . implode( ', ', $allowed ), [ 'status' => 400 ] );
        }

        $rows = NAWS_Database::get_daily_summaries( [
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'fields'    => $req_fields,
            'group_by'  => $group_by,
        ] );

        // Unit conversion mapping
        $field_param = [
            'temp_min'     => 'Temperature',
            'temp_max'     => 'Temperature',
            'temp_avg'     => 'Temperature',
            'pressure_avg' => 'Pressure',
            'rain_sum'     => 'Rain',
            'wind_avg'     => 'WindStrength',
            'gust_max'     => 'GustStrength',
        ];

        $out = [];
        foreach ( $rows as $row ) {
            $entry = [ 'date' => $row['day_date'] ];
            foreach ( $req_fields as $f ) {
                $val = $row[ $f ] ?? null;
                if ( $val === null ) {
                    $entry[ $f ] = null;
                    continue;
                }
                $fval = (float) $val;
                $entry[ $f ] = ( $convert && isset( $field_param[ $f ] ) )
                    ? NAWS_Helpers::format_value( $field_param[ $f ], $fval )
                    : round( $fval, 2 );
            }
            $out[] = $entry;
        }

        // Build units info for the requested fields
        $units = [];
        foreach ( $req_fields as $f ) {
            $units[ $f ] = isset( $field_param[ $f ] ) ? NAWS_Helpers::get_unit( $field_param[ $f ] ) : self::field_unit( $f );
        }

        return rest_ensure_response( [
            'count'    => count( $out ),
            'from'     => $date_from,
            'to'       => $date_to,
            'group_by' => $group_by,
            'units'    => $units,
            'data'     => $out,
        ] );
    }

    /* ================================================================
     * Settings helpers
     * ================================================================*/

    /** Is the REST API enabled? */
    public static function is_enabled() {
        $cfg = get_option( self::OPT, [] );
        return ! empty( $cfg['enabled'] );
    }

    /** Get API key (decrypted, or empty string). */
    public static function get_api_key() {
        $cfg = get_option( self::OPT, [] );
        $raw = $cfg['api_key'] ?? '';
        return $raw !== '' ? NAWS_Crypto::decrypt( $raw ) : '';
    }

    /** Generate a new cryptographically secure API key. */
    public static function generate_api_key() {
        return 'naws_' . bin2hex( random_bytes( 24 ) );
    }

    /** Get config with defaults. */
    public static function get_config() {
        return wp_parse_args( get_option( self::OPT, [] ), [
            'enabled'    => false,
            'api_key'    => '',
            'rate_limit' => self::DEFAULT_RATE_LIMIT,
        ] );
    }

    /** Save config (encrypts api_key before storing). */
    public static function save_config( array $cfg ) {
        // Encrypt the API key before saving
        if ( isset( $cfg['api_key'] ) && $cfg['api_key'] !== '' && ! NAWS_Crypto::is_encrypted( $cfg['api_key'] ) ) {
            $cfg['api_key'] = NAWS_Crypto::encrypt( $cfg['api_key'] );
        }
        update_option( self::OPT, $cfg );
    }

    /* ================================================================
     * Rate limiting (transient-based, per API key)
     * ================================================================*/

    private static function check_rate_limit() {
        $cfg   = self::get_config();
        $limit = max( 1, (int) ( $cfg['rate_limit'] ?? self::DEFAULT_RATE_LIMIT ) );
        $key   = 'naws_rl_' . substr( md5( $cfg['api_key'] ), 0, 12 );
        $data  = get_transient( $key );

        $now = time();

        if ( $data === false ) {
            set_transient( $key, [ 'count' => 1, 'window_start' => $now ], 120 );
            return true;
        }

        // Reset window after 60 seconds
        if ( ( $now - $data['window_start'] ) >= 60 ) {
            set_transient( $key, [ 'count' => 1, 'window_start' => $now ], 120 );
            return true;
        }

        if ( $data['count'] >= $limit ) {
            return false;
        }

        $data['count']++;
        set_transient( $key, $data, 120 );
        return true;
    }

    /* ================================================================
     * Utility
     * ================================================================*/

    /** Parse ISO 8601 or Unix timestamp to int. */
    private static function parse_timestamp( $str ) {
        if ( is_numeric( $str ) ) return (int) $str;
        $ts = strtotime( $str );
        return $ts !== false ? $ts : null;
    }

    /** Unit string for fields without a parameter mapping. */
    private static function field_unit( $field ) {
        $map = [
            'humidity_avg' => '%',
            'co2_avg'      => 'ppm',
            'noise_avg'    => 'dB',
            'wind_angle'   => '°',
        ];
        return $map[ $field ] ?? '';
    }
}
