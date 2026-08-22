<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_API {

    const TOKEN_URL      = 'https://api.netatmo.com/oauth2/token';
    const STATIONS_URL   = 'https://api.netatmo.com/api/getstationsdata';
    const MEASURE_URL    = 'https://api.netatmo.com/api/getmeasure';
    const HOMESDATA_URL  = 'https://api.netatmo.com/api/homesdata';

    /** How many times one polling cycle asks again after a transient failure. */
    const MAX_FETCH_ATTEMPTS = 3;

    /** Wait used when the server names no Retry-After we can act on. */
    const RETRY_WAIT_DEFAULT = 5;

    /** Longest wait we are willing to sit out inside a single request. */
    const RETRY_WAIT_MAX = 15;

    private string $client_id;
    private string $client_secret;
    private string $access_token;
    private string $refresh_token;
    private int    $token_expiry;

    public function __construct() {
        $options = get_option( 'naws_settings', [] );
        // Transparent decrypt if values were encrypted by an older version
        $cid = $options['client_id']     ?? '';
        $cse = $options['client_secret'] ?? '';
        $this->client_id     = NAWS_Crypto::is_encrypted( $cid ) ? NAWS_Crypto::decrypt( $cid ) : $cid;
        $this->client_secret = NAWS_Crypto::is_encrypted( $cse ) ? NAWS_Crypto::decrypt( $cse ) : $cse;
        $this->access_token  = NAWS_Crypto::get_option( 'naws_access_token', '' );
        $this->refresh_token = NAWS_Crypto::get_option( 'naws_refresh_token', '' );
        $this->token_expiry  = (int) get_option( 'naws_token_expiry', 0 );
    }

    // ----------------------------------------------------------------
    // Authentication
    // ----------------------------------------------------------------

    /**
     * Check if we have a valid access token
     */
    public function is_authenticated() {
        return ! empty( $this->access_token ) && time() < $this->token_expiry - 60;
    }

    /**
     * Exchange an authorization code for tokens (OAuth2 flow)
     */
    public function exchange_code( $code, $redirect_uri ) {
        $response = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
                'scope'         => 'read_station',
            ],
            'timeout' => 30,
        ] );

        return $this->handle_token_response( $response );
    }

/**
     * Refresh access token using refresh_token.
     * Retries up to 3 times on transient network errors (not on auth errors).
     */
    public function refresh_access_token() {
        if ( empty( $this->refresh_token ) ) {
            return new WP_Error(
                'no_refresh_token',
                'No refresh token available. Please re-authenticate via Settings.'
            );
        }

        $max_attempts = 3;
        $last_error   = null;

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            if ( $attempt > 1 ) {
                sleep( ( $attempt - 1 ) * 3 );
            }

            $response = wp_remote_post( self::TOKEN_URL, [
                'body' => [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'refresh_token' => $this->refresh_token,
                ],
                'timeout' => 30,
            ] );

            if ( is_wp_error( $response ) ) {
                $last_error = $response;
                continue;
            }

            $http_code = wp_remote_retrieve_response_code( $response );
            $body      = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $http_code >= 500 ) {
                $last_error = new WP_Error( 'server_error', "Netatmo server error (HTTP {$http_code})" );
                continue;
            }

            if ( isset( $body['error'] ) ) {
                $msg = $body['error_description'] ?? $body['error'];
                if ( in_array( $body['error'], [ 'invalid_grant', 'invalid_token' ], true ) ) {
                    delete_option( 'naws_access_token' );
                    delete_option( 'naws_refresh_token' );
                    delete_option( 'naws_token_expiry' );
                    update_option( 'naws_auth_required', true );
                    return new WP_Error( 'token_expired',
                        'Refresh token expired or revoked. Please re-authenticate in Settings.'
                    );
                }
                return new WP_Error( 'netatmo_auth_error', $msg );
            }

            return $this->handle_token_response( $response );
        }

        return $last_error ?? new WP_Error( 'refresh_failed', 'Token refresh failed after 3 attempts.' );
    }

    /**
     * Process token response and store tokens
     */
    private function handle_token_response( $response ) {
        if ( is_wp_error( $response ) ) {
            update_option( 'naws_oauth_debug', 'WP_Error: ' . $response->get_error_message() );
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $body      = json_decode( $raw_body, true );

        // Store debug info (helpful for support, auto-cleared on success)
        update_option( 'naws_oauth_debug', [
            'http_code' => $http_code,
            'body'      => $body,
            'time'      => time(),
        ] );

        if ( isset( $body['error'] ) ) {
            $msg = $body['error_description'] ?? $body['error'];
            return new WP_Error( 'netatmo_auth_error', $msg );
        }

        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'invalid_response',
                sprintf(
                    'Invalid response from Netatmo (HTTP %d). Raw: %s',
                    $http_code,
                    substr( $raw_body, 0, 200 )
                )
            );
        }

        $this->access_token  = $body['access_token'];
        $this->token_expiry  = time() + intval( $body['expires_in'] ?? 10800 );

        // Netatmo ALWAYS returns a refresh_token on initial authorization.
        // On refresh_token grants it may or may not rotate it - keep old one if missing.
        $stored_ok = true;
        if ( ! empty( $body['refresh_token'] ) ) {
            $this->refresh_token = $body['refresh_token'];
            $stored_ok = NAWS_Crypto::save_option( 'naws_refresh_token', $this->refresh_token );
        }

        if ( ! NAWS_Crypto::save_option( 'naws_access_token', $this->access_token ) ) {
            $stored_ok = false;
        }

        // The refresh token cannot be fetched again, so a failure here is
        // worth remembering past this request - the settings page turns it
        // into a sentence. It is cleared as soon as a write succeeds.
        if ( ! $stored_ok ) {
            error_log( 'NAWS Crypto: could not store the Netatmo tokens encrypted' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            update_option( 'naws_crypto_write_failed', time(), false );

            // No expiry either: it would promise three hours of validity for a
            // token that was never stored, and ensure_token() would sit out
            // those hours before trying again. The debug record stays for the
            // same reason - a write that failed is when someone needs to read
            // it. Reporting this as an error is what keeps the OAuth return
            // from putting a green "connected" next to the red notice.
            return new WP_Error( 'crypto_write_failed',
                'Connected to Netatmo, but the tokens could not be stored encrypted. See the encryption notice on the settings page.'
            );
        }

        delete_option( 'naws_crypto_write_failed' );

        update_option( 'naws_token_expiry',  $this->token_expiry );

        // Clear debug log on success
        delete_option( 'naws_oauth_debug' );

        return true;
    }

    /**
     * Ensure we have a valid token, refreshing proactively 15 min before expiry.
     * Also blocks execution if re-authentication is required.
     */
    private function ensure_token() {
        // If re-auth is required (e.g. refresh token was revoked) stop immediately
        if ( get_option( 'naws_auth_required' ) ) {
            return new WP_Error( 'auth_required',
                'Re-authentication required. Please visit Settings and reconnect.'
            );
        }

        // Proactive renewal: refresh 15 minutes BEFORE the token actually expires
        // This avoids race conditions where cron fires just as the token expires
        $buffer = 15 * MINUTE_IN_SECONDS;
        if ( ! empty( $this->access_token ) && time() < ( $this->token_expiry - $buffer ) ) {
            return true;
        }

        return $this->refresh_access_token();
    }

    // ----------------------------------------------------------------
    // API Requests
    // ----------------------------------------------------------------

    /**
     * Decide whether a failed Netatmo answer deserves another attempt.
     *
     * Pure function: no clock, no options, no HTTP. Same shape as
     * NAWS_Cron::backoff_interval(), and covered by tests/test-api-retry.php.
     *
     * @param  int      $http_code    HTTP status, or 0 when no response arrived.
     * @param  int|null $error_code   Netatmo's error.code, when the body had one.
     * @param  mixed    $retry_after  Raw Retry-After header value.
     * @param  int      $attempt      Which attempt just failed, 1-based.
     * @return int|null               Seconds to wait, or null to give up.
     */
    public static function retry_delay( $http_code, $error_code, $retry_after, $attempt ) {
        if ( $attempt >= self::MAX_FETCH_ATTEMPTS ) {
            return null;
        }

        $http_code  = (int) $http_code;
        $error_code = ( null === $error_code ) ? null : (int) $error_code;

        // 0 means the request never reached an answer at all (timeout, DNS).
        // Code 27 is Netatmo's "service temporarily unavailable"; it usually
        // rides along with a 503, but the body is the more reliable of the two.
        $transient = ( 0 === $http_code )
            || ( $http_code >= 500 )
            || ( 27 === $error_code );

        if ( ! $transient ) {
            return null;
        }

        // Only the plain-integer form of Retry-After is acted on. The HTTP-date
        // form is legal too, but reading it needs a clock, and this decision
        // stays pure. A server asking for longer than we may sit out in one
        // request is not shortened to fit -- the next polling cycle is the
        // answer to that, not a call it never asked for.
        if ( is_numeric( $retry_after ) ) {
            $wait = (int) $retry_after;
            if ( $wait > self::RETRY_WAIT_MAX ) {
                return null;
            }
            if ( $wait >= 1 ) {
                return $wait;
            }
        }

        return self::RETRY_WAIT_DEFAULT;
    }

    /**
     * Get all station data with modules and dashboard_data.
     *
     * Netatmo answers this endpoint with HTTP 503, error code 27 and
     * "Retry-After: 5" when it is briefly out of breath. Measured in the cron
     * context of the reference installation on 2026-08-22, and visible in the
     * log well before that: bursts of one to three cycles, several times an
     * hour, heaviest overnight. Treating that as a final answer threw away a
     * whole ten-minute cycle over an outage the server itself expected to last
     * five seconds -- while refresh_access_token(), against the very same host,
     * has always knocked three times. That asymmetry was the bug. Netatmo's
     * hiccup is not something this plugin can fix.
     *
     * @param  bool $_refreshed  Internal: true once a token refresh has been
     *                           tried, so an expired token cannot recurse.
     * @return array|WP_Error    The devices, or the final error.
     */
    public function get_stations_data( $_refreshed = false ) {
        $refresh = $this->ensure_token();
        if ( is_wp_error( $refresh ) ) return $refresh;

        $url = add_query_arg( [ 'get_favorites' => 'false' ], self::STATIONS_URL );

        for ( $attempt = 1; $attempt <= self::MAX_FETCH_ATTEMPTS; $attempt++ ) {
            $response = wp_remote_get( $url, [
                'headers' => [ 'Authorization' => 'Bearer ' . $this->access_token ],
                'timeout' => 30,
            ] );

            $no_answer  = is_wp_error( $response );
            $http_code  = $no_answer ? 0 : (int) wp_remote_retrieve_response_code( $response );
            $data       = $no_answer ? null : json_decode( wp_remote_retrieve_body( $response ), true );
            $error_code = isset( $data['error']['code'] ) ? (int) $data['error']['code'] : null;

            // An expired token is not a server fault, so it must not eat a
            // retry. Refresh once and start over -- the guard mirrors the one
            // get_measure() has always carried against endless recursion.
            if ( 3 === $error_code && ! $_refreshed ) {
                $refresh = $this->refresh_access_token();
                if ( is_wp_error( $refresh ) ) return $refresh;
                return $this->get_stations_data( true );
            }

            $wait = self::retry_delay(
                $http_code,
                $error_code,
                $no_answer ? '' : wp_remote_retrieve_header( $response, 'retry-after' ),
                $attempt
            );

            if ( null !== $wait ) {
                sleep( $wait );
                continue;
            }

            if ( $no_answer ) {
                return $response;
            }

            // Gate on the error key, not on the code: Netatmo always sends one,
            // but an error without a code is still an error, and reading the
            // code alone would hand it back as an empty device list.
            if ( isset( $data['error'] ) ) {
                // The code and the status belong in the message. Without them
                // the cron log cannot tell a Netatmo outage from a bad token,
                // which is exactly the question one asks while reading it.
                return new WP_Error( 'api_error', sprintf(
                    '%s (Netatmo code %s, HTTP %d)',
                    $data['error']['message'] ?? 'Unknown API error',
                    null === $error_code ? '?' : $error_code,
                    $http_code
                ) );
            }

            return $data['body']['devices'] ?? [];
        }

        // Unreachable while retry_delay() gives up at MAX_FETCH_ATTEMPTS. Kept
        // so that changing either constant alone cannot silently return null.
        return new WP_Error( 'api_error', 'Netatmo request gave up without an answer.' );
    }

    /**
     * Get historical measure data
     *
     * @param string $device_id   MAC address of main station
     * @param string $module_id   MAC address of module (or same as device_id for main)
     * @param array  $types       e.g. ['Temperature','Humidity']
     * @param int    $date_begin  Unix timestamp
     * @param int    $date_end    Unix timestamp
     * @param string $scale       '30min','1hour','3hours','1day','1week','1month','max'
     * @param bool   $optimize    Optimize data (bool)
     * @param int    $limit       Max 1024 per call
     */
    public function get_measure( $device_id, $module_id, $types, $date_begin, $date_end, $scale = '30min', $optimize = false, $limit = 1024, $real_time = false, $_retry = false ) {
        $refresh = $this->ensure_token();
        if ( is_wp_error( $refresh ) ) return $refresh;

        // Build POST body
        // IMPORTANT: for the main station (NAMain), device_id == module_id.
        // Sending module_id identical to device_id causes HTTP 400.
        // Solution: omit module_id when they are the same.
        $body = [
            'device_id'  => $device_id,
            'type'       => implode( ',', (array) $types ),
            'scale'      => $scale,
            'date_begin' => $date_begin,
            'date_end'   => $date_end,
            'optimize'   => $optimize ? 'true' : 'false',
            'real_time'  => $real_time ? 'true' : 'false',
            'limit'      => min( $limit, 1024 ),
        ];

        // Only add module_id when it differs from device_id (i.e. external modules)
        if ( ! empty( $module_id ) && $module_id !== $device_id ) {
            $body['module_id'] = $module_id;
        }

        // Same transient answers as getstationsdata, same treatment. This path
        // feeds the daily summary and the historical import, where giving up
        // costs a day of a module rather than a single reading.
        for ( $attempt = 1; $attempt <= self::MAX_FETCH_ATTEMPTS; $attempt++ ) {
            $response = wp_remote_post( self::MEASURE_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body'    => $body,
                'timeout' => 60,
            ] );

            $no_answer  = is_wp_error( $response );
            $http_code  = $no_answer ? 0 : (int) wp_remote_retrieve_response_code( $response );
            $raw        = $no_answer ? '' : wp_remote_retrieve_body( $response );
            $data       = $no_answer ? null : json_decode( $raw, true );
            $error_code = isset( $data['error']['code'] ) ? (int) $data['error']['code'] : null;

            // Error 3 = expired token. Refresh and start over once; not a
            // server fault, so it must not consume one of the retries.
            if ( 3 === $error_code && ! $_retry ) {
                $this->refresh_access_token();
                return $this->get_measure( $device_id, $module_id, $types, $date_begin, $date_end, $scale, $optimize, $limit, $real_time, true );
            }

            $wait = self::retry_delay(
                $http_code,
                $error_code,
                $no_answer ? '' : wp_remote_retrieve_header( $response, 'retry-after' ),
                $attempt
            );

            if ( null !== $wait ) {
                sleep( $wait );
                continue;
            }

            if ( $no_answer ) {
                return $response;
            }

            // A 503 often answers with an HTML page rather than JSON. That is
            // caught above by the status; what reaches here is a body that is
            // broken for some other reason and will not mend itself.
            if ( ! is_array( $data ) ) {
                return new WP_Error( 'api_error', 'Invalid JSON response: ' . substr( $raw, 0, 200 ) );
            }

            // Gate on the error key, not on the code: an error without a code
            // is still an error and must not be mistaken for an empty result.
            if ( isset( $data['error'] ) ) {
                return new WP_Error( 'api_error', sprintf(
                    'Netatmo API error %s: %s (HTTP %d)',
                    null === $error_code ? '?' : $error_code,
                    $data['error']['message'] ?? 'Unknown error',
                    $http_code
                ) );
            }

            return $data['body'] ?? [];
        }

        // Unreachable while retry_delay() gives up at MAX_FETCH_ATTEMPTS. Kept
        // so that changing either constant alone cannot silently return null.
        return new WP_Error( 'api_error', 'Netatmo request gave up without an answer.' );
    }

    // ----------------------------------------------------------------
    // Data import helpers
    // ----------------------------------------------------------------

    /**
     * Fetch current readings and save to DB
     */
    public function sync_current_data() {
        $devices = $this->get_stations_data();

        if ( is_wp_error( $devices ) ) {
            update_option( 'naws_last_sync_error', $devices->get_error_message() );
            return $devices;
        }

        $total_saved = 0;
        $rows        = [];

        // These fields in dashboard_data are Unix timestamps or metadata, NOT sensor values.
        // Storing them as readings would pollute the DB with nonsensical large integers.
        $skip_fields = [
            'time_utc',
            'date_min_temp',
            'date_max_temp',
            'date_min_pressure',
            'date_max_pressure',
            'date_max_wind_str',
            'date_max_gust',
        ];

        foreach ( $devices as $device ) {
            $station_id = $device['_id'];

            // Save main module metadata
            $device['station_id'] = $station_id;
            NAWS_Database::save_module( $device );

            // Save readings from dashboard_data (only if module is active)
            if ( ! empty( $device['dashboard_data'] ) ) {
                if ( NAWS_Database::is_module_active( $station_id ) ) {
                    $ts = $device['dashboard_data']['time_utc'] ?? time();
                    foreach ( $device['dashboard_data'] as $key => $val ) {
                        if ( in_array( $key, $skip_fields, true ) ) continue;
                        if ( ! is_numeric( $val ) ) continue;
                        $rows[] = [
                            'module_id'   => $station_id,
                            'station_id'  => $station_id,
                            'recorded_at' => $ts,
                            'parameter'   => $key,
                            'value'       => $val,
                        ];
                    }
                }
            }

            // Process additional modules
            foreach ( $device['modules'] ?? [] as $module ) {
                $module['station_id'] = $station_id;
                NAWS_Database::save_module( $module );

                if ( ! empty( $module['dashboard_data'] ) ) {
                    if ( ! NAWS_Database::is_module_active( $module['_id'] ) ) continue;

                    $ts = $module['dashboard_data']['time_utc'] ?? time();
                    foreach ( $module['dashboard_data'] as $key => $val ) {
                        if ( in_array( $key, $skip_fields, true ) ) continue;
                        if ( ! is_numeric( $val ) ) continue;
                        $rows[] = [
                            'module_id'   => $module['_id'],
                            'station_id'  => $station_id,
                            'recorded_at' => $ts,
                            'parameter'   => $key,
                            'value'       => $val,
                        ];
                    }
                }
            }
        }

        if ( ! empty( $rows ) ) {
            $total_saved = NAWS_Database::bulk_insert_readings( $rows );
        }

        update_option( 'naws_last_sync', time() );
        update_option( 'naws_last_sync_error', '' );

        return $total_saved;
    }

    /**
     * Import historical data for a module/parameter range.
     * Returns [ 'inserted' => int, 'next_date_begin' => int|null ]
     * next_date_begin is null if all data fetched, or a timestamp for next batch.
     */
    public function import_historical_chunk( $device_id, $module_id, $types, $date_begin, $date_end, $scale = '30min' ) {
        $data = $this->get_measure( $device_id, $module_id, $types, $date_begin, $date_end, $scale );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        if ( empty( $data ) ) {
            return [ 'inserted' => 0, 'next_date_begin' => null ];
        }

        $rows         = [];
        $last_ts      = $date_begin;
        $type_keys    = array_values( (array) $types );

        foreach ( $data as $entry ) {
            $ts     = intval( $entry['beg_time'] );
            $step   = isset( $entry['step_time'] ) ? intval( $entry['step_time'] ) : 0;
            $values = $entry['value'] ?? [];

            foreach ( $values as $i => $val_array ) {
                $entry_ts = $ts + ( $i * $step );

                foreach ( $val_array as $j => $val ) {
                    if ( $val === null || ! isset( $type_keys[ $j ] ) ) continue;
                    $rows[] = [
                        'module_id'   => $module_id,
                        'station_id'  => $device_id,
                        'recorded_at' => $entry_ts,
                        'parameter'   => $type_keys[ $j ],
                        'value'       => floatval( $val ),
                    ];
                    $last_ts = max( $last_ts, $entry_ts );
                }
            }
        }

        $inserted = NAWS_Database::bulk_insert_readings( $rows );

        // Determine if there's more data to fetch
        $next = ( $last_ts < $date_end ) ? $last_ts + 1 : null;

        return [ 'inserted' => $inserted, 'next_date_begin' => $next, 'rows_processed' => count( $rows ) ];
    }

    /**
     * Get the OAuth2 authorization URL
     */
    public function get_auth_url( $redirect_uri ) {
        // Use a random state token stored as option (not transient – avoids cache issues)
        $state = bin2hex( random_bytes( 16 ) );
        update_option( 'naws_oauth_state', $state, false );
        update_option( 'naws_oauth_state_time', time(), false );

        return add_query_arg( [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'read_station',
            'response_type' => 'code',
            'state'         => $state,
        ], 'https://api.netatmo.com/oauth2/authorize' );
    }
}
