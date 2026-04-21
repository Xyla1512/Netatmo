<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * NAWS_Forecast – 5-day weather forecast via Open-Meteo API.
 *
 * Supports two location modes:
 *   - 'auto'   → Uses Netatmo station coordinates from naws_modules table
 *   - 'manual' → Uses city/PLZ entered in plugin settings, geocoded via
 *                 Open-Meteo Geocoding API
 *
 * Results are cached as a WP transient (default 3 h) to minimise API calls.
 *
 * @since 0.9.94
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Forecast {

    const CACHE_KEY      = 'naws_forecast_data';
    const CACHE_KEY_GEO  = 'naws_forecast_geocode';
    const CACHE_TTL      = 3 * HOUR_IN_SECONDS;
    const GEO_CACHE_TTL  = 7 * DAY_IN_SECONDS;

    const API_URL     = 'https://api.open-meteo.com/v1/forecast';
    const GEOCODE_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    const DAILY_VARS = [
        'weathercode',
        'temperature_2m_max',
        'temperature_2m_min',
        'apparent_temperature_max',
        'apparent_temperature_min',
        'precipitation_sum',
        'precipitation_probability_max',
        'windspeed_10m_max',
        'windgusts_10m_max',
        'winddirection_10m_dominant',
        'sunrise',
        'sunset',
    ];

    /* ==================================================================
     * Public API
     * ================================================================*/

    public static function get_forecast( int $days = 5 ): array {
        $days = max( 1, min( 7, $days ) );

        // Dispatch to provider
        $opts     = get_option( 'naws_settings', [] );
        $provider = $opts['forecast_provider'] ?? 'open_meteo';
        $cache_key = self::CACHE_KEY . '_' . $provider;

        $cached = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) && ! isset( $cached['error'] ) ) {
            $cached['days'] = array_slice( $cached['days'], 0, $days );
            return $cached;
        }

        $location = self::resolve_location();
        if ( isset( $location['error'] ) ) {
            return $location;
        }

        switch ( $provider ) {
            case 'yr_no':
                $result = self::fetch_yr_no( $location );
                break;
            default:
                $result = self::fetch_open_meteo( $location );
        }

        if ( isset( $result['error'] ) ) {
            return $result;
        }

        set_transient( $cache_key, $result, self::CACHE_TTL );
        $result['days'] = array_slice( $result['days'], 0, $days );
        return $result;
    }

    public static function flush_cache(): void {
        delete_transient( self::CACHE_KEY . '_open_meteo' );
        delete_transient( self::CACHE_KEY . '_yr_no' );
        delete_transient( self::CACHE_KEY_GEO );
    }

    /* ==================================================================
     * Provider: Open-Meteo
     * ================================================================*/

    private static function fetch_open_meteo( array $location ): array {
        $url = add_query_arg( [
            'latitude'       => $location['lat'],
            'longitude'      => $location['lon'],
            'daily'          => implode( ',', self::DAILY_VARS ),
            'timezone'       => wp_timezone_string() ?: 'Europe/Berlin',
            'forecast_days'  => 7,
            'windspeed_unit' => 'kmh',
        ], self::API_URL );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'NAWS/' . NAWS_VERSION . ' (WordPress Plugin)',
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'NAWS Forecast: Open-Meteo HTTP error - ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [ 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 || empty( $body ) ) {
            error_log( "NAWS Forecast: Open-Meteo HTTP {$code}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [ 'error' => sprintf( 'Open-Meteo API HTTP %d', $code ) ];
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) || isset( $json['error'] ) ) {
            $reason = $json['reason'] ?? 'Unknown API error';
            error_log( 'NAWS Forecast: Open-Meteo error - ' . $reason ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [ 'error' => $reason ];
        }

        return self::normalise( $json, $location );
    }

    /* ==================================================================
     * Provider: Yr.no (MET Norway Locationforecast 2.0)
     * ================================================================*/

    private static function fetch_yr_no( array $location ): array {
        $url = sprintf(
            'https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=%.4f&lon=%.4f',
            $location['lat'],
            $location['lon']
        );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'NAWS/' . NAWS_VERSION . ' github.com/naws-plugin (WordPress Weather Plugin)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'NAWS Forecast: Yr.no HTTP error - ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [ 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 || empty( $body ) ) {
            error_log( "NAWS Forecast: Yr.no HTTP {$code}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [ 'error' => sprintf( 'Yr.no API HTTP %d', $code ) ];
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) || ! isset( $json['properties']['timeseries'] ) ) {
            error_log( 'NAWS Forecast: Yr.no invalid response structure' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [ 'error' => 'Yr.no returned unexpected data format' ];
        }

        return self::normalise_yr( $json, $location );
    }

    /**
     * Normalise Yr.no timeseries to the same daily format as Open-Meteo.
     */
    private static function normalise_yr( array $json, array $location ): array {
        $timeseries = $json['properties']['timeseries'] ?? [];
        $by_date    = [];

        // Group hourly entries by date
        foreach ( $timeseries as $entry ) {
            $time = $entry['time'] ?? '';
            $date = substr( $time, 0, 10 ); // YYYY-MM-DD
            if ( ! $date ) continue;

            $instant = $entry['data']['instant']['details'] ?? [];
            $next6   = $entry['data']['next_6_hours']  ?? [];
            $next12  = $entry['data']['next_12_hours'] ?? [];
            $next1   = $entry['data']['next_1_hours']  ?? [];

            if ( ! isset( $by_date[ $date ] ) ) {
                $by_date[ $date ] = [
                    'temps'     => [],
                    'wind'      => [],
                    'precip'    => 0,
                    'symbol'    => '',
                    'wind_dirs' => [],
                ];
            }

            $d = &$by_date[ $date ];
            if ( isset( $instant['air_temperature'] ) )     $d['temps'][]   = $instant['air_temperature'];
            if ( isset( $instant['wind_speed'] ) )          $d['wind'][]    = $instant['wind_speed'] * 3.6; // m/s → km/h
            if ( isset( $instant['wind_from_direction'] ) ) $d['wind_dirs'][] = $instant['wind_from_direction'];

            // Precipitation: prefer next_6_hours, fallback to next_1_hours
            if ( isset( $next6['details']['precipitation_amount'] ) ) {
                $d['precip'] += (float) $next6['details']['precipitation_amount'];
            } elseif ( isset( $next1['details']['precipitation_amount'] ) ) {
                $d['precip'] += (float) $next1['details']['precipitation_amount'];
            }

            // Symbol: use the midday (12:00) symbol, or the first available
            $hour = (int) substr( $time, 11, 2 );
            $sym  = $next6['summary']['symbol_code']  ?? $next12['summary']['symbol_code'] ?? $next1['summary']['symbol_code'] ?? '';
            if ( $sym && ( $hour === 12 || empty( $d['symbol'] ) ) ) {
                $d['symbol'] = $sym;
            }
        }

        $days = [];
        $i    = 0;
        foreach ( $by_date as $date => $d ) {
            if ( $i >= 7 ) break;
            $temps = $d['temps'];
            $winds = $d['wind'];
            $dirs  = $d['wind_dirs'];

            $days[] = [
                'date'         => $date,
                'weathercode'  => self::yr_symbol_to_wmo( $d['symbol'] ),
                'temp_max'     => ! empty( $temps ) ? round( max( $temps ), 1 ) : null,
                'temp_min'     => ! empty( $temps ) ? round( min( $temps ), 1 ) : null,
                'feels_max'    => null, // Yr.no doesn't provide feels-like
                'feels_min'    => null,
                'precip_sum'   => round( $d['precip'], 1 ),
                'precip_prob'  => 0, // Yr.no compact doesn't include probability
                'wind_max'     => ! empty( $winds ) ? round( max( $winds ), 1 ) : null,
                'gust_max'     => null, // Not in compact format
                'wind_dir'     => ! empty( $dirs ) ? (int) round( array_sum( $dirs ) / count( $dirs ) ) : 0,
                'sunrise'      => '',
                'sunset'       => '',
            ];
            $i++;
        }

        return [
            'days'          => $days,
            'location'      => [ 'lat' => $location['lat'], 'lon' => $location['lon'] ],
            'location_name' => $location['name'] ?? '',
            'fetched_at'    => time(),
            'provider'      => 'yr_no',
        ];
    }

    /**
     * Map Yr.no symbol codes to WMO weather codes (used by wmo_description).
     */
    private static function yr_symbol_to_wmo( string $symbol ): int {
        // Strip _day/_night/_polartwilight suffix
        $base = preg_replace( '/_(?:day|night|polartwilight)$/', '', $symbol );
        $map  = [
            'clearsky'              => 0,
            'fair'                  => 1,
            'partlycloudy'          => 2,
            'cloudy'                => 3,
            'fog'                   => 45,
            'lightrainshowers'      => 61,
            'lightrain'             => 61,
            'rainshowers'           => 63,
            'rain'                  => 63,
            'heavyrainshowers'      => 65,
            'heavyrain'             => 65,
            'lightsleetshowers'     => 68,
            'lightsleet'            => 68,
            'sleetshowers'          => 68,
            'sleet'                 => 68,
            'heavysleetshowers'     => 68,
            'heavysleet'            => 68,
            'lightsnowshowers'      => 71,
            'lightsnow'             => 71,
            'snowshowers'           => 73,
            'snow'                  => 73,
            'heavysnowshowers'      => 75,
            'heavysnow'             => 75,
            'rainandthunder'        => 95,
            'lightrainandthunder'   => 95,
            'heavyrainandthunder'   => 95,
            'sleetandthunder'       => 95,
            'lightsleetandthunder'  => 95,
            'heavysleetandthunder'  => 95,
            'snowandthunder'        => 95,
            'lightsnowandthunder'   => 95,
            'heavysnowandthunder'   => 95,
            'lightrainshowersandthunder' => 95,
            'rainshowersandthunder' => 95,
            'heavyrainshowersandthunder' => 95,
        ];
        return $map[ $base ] ?? 3; // Default: cloudy
    }

    /* ==================================================================
     * Location resolution
     * ================================================================*/

    public static function resolve_location(): array {
        $opts = get_option( 'naws_settings', [] );
        $mode = $opts['forecast_location'] ?? 'auto';

        if ( $mode === 'manual' ) {
            return self::resolve_manual_location( $opts );
        }
        return self::resolve_auto_location();
    }

    private static function resolve_auto_location(): array {
        $modules = NAWS_Database::get_modules( false );

        foreach ( $modules as $m ) {
            if ( ! empty( $m['latitude'] ) && ! empty( $m['longitude'] ) ) {
                return [
                    'lat'  => round( (float) $m['latitude'], 4 ),
                    'lon'  => round( (float) $m['longitude'], 4 ),
                    'name' => self::get_auto_location_name(
                        (float) $m['latitude'],
                        (float) $m['longitude']
                    ),
                ];
            }
        }
        return [ 'error' => naws__( 'forecast_no_coords' ) ];
    }

    private static function resolve_manual_location( array $opts ): array {
        $city    = trim( $opts['forecast_city'] ?? '' );
        $country = trim( $opts['forecast_country'] ?? '' );

        if ( $city === '' ) {
            return [ 'error' => naws__( 'forecast_no_city' ) ];
        }

        // Clean up city input: strip postcodes, slashes, commas
        // "Leipzig / 04105" → "Leipzig", "04105 Berlin" → "Berlin"
        $clean_city = preg_replace( '/[\d\/,]+/', ' ', $city );       // Remove digits, slashes, commas
        $clean_city = preg_replace( '/\s{2,}/', ' ', $clean_city );   // Collapse multiple spaces
        $clean_city = trim( $clean_city, ' -–' );                     // Trim dashes and spaces

        if ( $clean_city === '' ) {
            // Input was only numbers (PLZ only) – try original input
            $clean_city = $city;
        }

        // Check geocode cache
        $cache_input = md5( $clean_city . '|' . $country );
        $cached = get_transient( self::CACHE_KEY_GEO );
        if ( $cached !== false && is_array( $cached ) && ( $cached['_hash'] ?? '' ) === $cache_input ) {
            return $cached;
        }

        // Geocode via Open-Meteo (only city name, no PLZ – API doesn't support postcodes)
        $search = $clean_city;
        if ( $country !== '' ) {
            $search .= ' ' . $country;
        }

        $geo_url = add_query_arg( [
            'name'     => $search,
            'count'    => 5,
            'language' => NAWS_Lang::lang(),
            'format'   => 'json',
        ], self::GEOCODE_URL );

        $response = wp_remote_get( $geo_url, [
            'timeout'    => 10,
            'user-agent' => 'NAWS/' . NAWS_VERSION . ' (WordPress Plugin)',
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => 'Geocoding: ' . $response->get_error_message() ];
        }

        $json    = json_decode( wp_remote_retrieve_body( $response ), true );
        $results = $json['results'] ?? [];

        if ( empty( $results ) ) {
            return [ 'error' => naws__( 'forecast_geocode_fail' ) ];
        }

        // Prefer matching country code
        $best = $results[0];
        if ( $country !== '' ) {
            $cc = strtoupper( $country );
            foreach ( $results as $r ) {
                if ( strtoupper( $r['country_code'] ?? '' ) === $cc ) {
                    $best = $r;
                    break;
                }
            }
        }

        $location = [
            'lat'   => round( (float) $best['latitude'], 4 ),
            'lon'   => round( (float) $best['longitude'], 4 ),
            'name'  => self::build_location_name( $best ),
            '_hash' => $cache_input,
        ];

        set_transient( self::CACHE_KEY_GEO, $location, self::GEO_CACHE_TTL );
        return $location;
    }

    private static function build_location_name( array $geo ): string {
        $parts   = [];
        $parts[] = $geo['name'] ?? '';
        if ( ! empty( $geo['admin1'] ) && ( $geo['admin1'] ?? '' ) !== ( $geo['name'] ?? '' ) ) {
            $parts[] = $geo['admin1'];
        }
        $parts[] = $geo['country'] ?? '';
        return implode( ', ', array_filter( $parts ) );
    }

    /**
     * Get location name for auto mode (cached in option).
     */
    private static function get_auto_location_name( float $lat, float $lon ): string {
        $opts = get_option( 'naws_settings', [] );
        $name = $opts['forecast_auto_name'] ?? '';
        if ( $name !== '' ) {
            return $name;
        }

        // Try geocoding API to find nearest city
        $geo_url = add_query_arg( [
            'name'     => round( $lat, 2 ) . ',' . round( $lon, 2 ),
            'count'    => 1,
            'language' => NAWS_Lang::lang(),
            'format'   => 'json',
        ], self::GEOCODE_URL );

        $response = wp_remote_get( $geo_url, [ 'timeout' => 5 ] );
        if ( ! is_wp_error( $response ) ) {
            $json = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $json['results'] ) ) {
                $name = self::build_location_name( $json['results'][0] );
                $opts['forecast_auto_name'] = $name;
                update_option( 'naws_settings', $opts );
                return $name;
            }
        }
        return naws__( 'forecast_station_location' );
    }

    public static function get_location_name(): string {
        $location = self::resolve_location();
        return $location['name'] ?? naws__( 'forecast_station_location' );
    }

    /* ==================================================================
     * Data normalisation
     * ================================================================*/

    private static function normalise( array $json, array $location ): array {
        $daily = $json['daily'] ?? [];
        $count = count( $daily['time'] ?? [] );
        $days  = [];

        for ( $i = 0; $i < $count; $i++ ) {
            $days[] = [
                'date'         => $daily['time'][ $i ]                            ?? '',
                'weathercode'  => (int) ( $daily['weathercode'][ $i ]             ?? 0 ),
                'temp_max'     => self::sf( $daily['temperature_2m_max'][ $i ]    ?? null ),
                'temp_min'     => self::sf( $daily['temperature_2m_min'][ $i ]    ?? null ),
                'feels_max'    => self::sf( $daily['apparent_temperature_max'][$i]?? null ),
                'feels_min'    => self::sf( $daily['apparent_temperature_min'][$i]?? null ),
                'precip_sum'   => self::sf( $daily['precipitation_sum'][ $i ]     ?? null ),
                'precip_prob'  => (int)($daily['precipitation_probability_max'][$i]?? 0 ),
                'wind_max'     => self::sf( $daily['windspeed_10m_max'][ $i ]     ?? null ),
                'gust_max'     => self::sf( $daily['windgusts_10m_max'][ $i ]     ?? null ),
                'wind_dir'     => (int)($daily['winddirection_10m_dominant'][$i]  ?? 0 ),
                'sunrise'      => $daily['sunrise'][ $i ]                         ?? '',
                'sunset'       => $daily['sunset'][ $i ]                          ?? '',
            ];
        }

        return [
            'days'          => $days,
            'location'      => [ 'lat' => $location['lat'], 'lon' => $location['lon'] ],
            'location_name' => $location['name'] ?? '',
            'fetched_at'    => time(),
            'provider'      => 'open_meteo',
        ];
    }

    private static function sf( $v ): ?float {
        return ( $v !== null && is_numeric( $v ) ) ? round( (float) $v, 1 ) : null;
    }

    /* ==================================================================
     * WMO Weather Code mapping
     * ================================================================*/

    public static function wmo_description( int $code, string $lang = '' ): array {
        if ( $lang === '' ) $lang = NAWS_Lang::lang();

        $de = [
            0=>['Klar','clear'],1=>['Überwiegend klar','partly'],2=>['Teilweise bewölkt','partly'],
            3=>['Bedeckt','cloudy'],45=>['Nebel','fog'],48=>['Raureif-Nebel','fog'],
            51=>['Leichter Nieselregen','drizzle'],53=>['Nieselregen','drizzle'],55=>['Starker Nieselregen','drizzle'],
            56=>['Gefrierender Niesel','freezing'],57=>['Starker gefr. Niesel','freezing'],
            61=>['Leichter Regen','rain-light'],63=>['Regen','rain'],65=>['Starker Regen','rain-heavy'],
            66=>['Gefrierender Regen','freezing'],67=>['Starker gefr. Regen','freezing'],
            71=>['Leichter Schneefall','snow-light'],73=>['Schneefall','snow'],75=>['Starker Schneefall','snow-heavy'],
            77=>['Schneegriesel','snow-light'],
            80=>['Leichte Regenschauer','shower'],81=>['Regenschauer','shower'],82=>['Starke Regenschauer','shower-heavy'],
            85=>['Schneeschauer','snow'],86=>['Starke Schneeschauer','snow-heavy'],
            95=>['Gewitter','thunder'],96=>['Gewitter mit Hagel','thunder'],99=>['Starkes Gewitter/Hagel','thunder'],
        ];
        $en = [
            0=>['Clear sky','clear'],1=>['Mainly clear','partly'],2=>['Partly cloudy','partly'],
            3=>['Overcast','cloudy'],45=>['Fog','fog'],48=>['Depositing rime fog','fog'],
            51=>['Light drizzle','drizzle'],53=>['Drizzle','drizzle'],55=>['Dense drizzle','drizzle'],
            56=>['Light freezing drizzle','freezing'],57=>['Dense freezing drizzle','freezing'],
            61=>['Light rain','rain-light'],63=>['Rain','rain'],65=>['Heavy rain','rain-heavy'],
            66=>['Light freezing rain','freezing'],67=>['Heavy freezing rain','freezing'],
            71=>['Light snow','snow-light'],73=>['Snow','snow'],75=>['Heavy snow','snow-heavy'],
            77=>['Snow grains','snow-light'],
            80=>['Light rain showers','shower'],81=>['Rain showers','shower'],82=>['Heavy rain showers','shower-heavy'],
            85=>['Snow showers','snow'],86=>['Heavy snow showers','snow-heavy'],
            95=>['Thunderstorm','thunder'],96=>['Thunderstorm with hail','thunder'],99=>['Heavy thunderstorm','thunder'],
        ];

        $map  = ( $lang === 'de' ) ? $de : $en;
        $info = $map[$code] ?? (($lang==='de') ? ['Unbekannt','clear'] : ['Unknown','clear']);
        return [ 'label' => $info[0], 'icon' => $info[1] ];
    }

    public static function get_weather_svg( string $id ): string {
        $s = [
            'clear'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><circle cx="24" cy="24" r="10" fill="#f59e0b"/><g stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"><line x1="24" y1="2" x2="24" y2="8"/><line x1="24" y1="40" x2="24" y2="46"/><line x1="2" y1="24" x2="8" y2="24"/><line x1="40" y1="24" x2="46" y2="24"/><line x1="8.4" y1="8.4" x2="12.6" y2="12.6"/><line x1="35.4" y1="35.4" x2="39.6" y2="39.6"/><line x1="8.4" y1="39.6" x2="12.6" y2="35.4"/><line x1="35.4" y1="12.6" x2="39.6" y2="8.4"/></g></svg>',
            'partly'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><circle cx="18" cy="16" r="8" fill="#f59e0b"/><g stroke="#f59e0b" stroke-width="2" stroke-linecap="round"><line x1="18" y1="2" x2="18" y2="6"/><line x1="6" y1="16" x2="2" y2="16"/><line x1="8" y1="6" x2="10.5" y2="8.5"/><line x1="28" y1="6" x2="25.5" y2="8.5"/></g><path d="M14 30a8 8 0 0 1 8-8h8a7 7 0 0 1 7 7v1a5 5 0 0 1-5 5H18a6 6 0 0 1-4-10Z" fill="#94a3b8" opacity=".85"/></svg>',
            'cloudy'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 34a8 8 0 0 1 4-15 10 10 0 0 1 19-3 8 8 0 0 1 5 14Z" fill="#94a3b8"/></svg>',
            'fog'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><g stroke="#94a3b8" stroke-width="3" stroke-linecap="round"><line x1="8" y1="16" x2="40" y2="16"/><line x1="6" y1="24" x2="42" y2="24"/><line x1="10" y1="32" x2="38" y2="32"/></g></svg>',
            'drizzle'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 26a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#94a3b8"/><g stroke="#60a5fa" stroke-width="2" stroke-linecap="round"><line x1="15" y1="32" x2="14" y2="36"/><line x1="24" y1="32" x2="23" y2="36"/><line x1="33" y1="32" x2="32" y2="36"/></g></svg>',
            'rain-light'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 26a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#94a3b8"/><g stroke="#3b82f6" stroke-width="2" stroke-linecap="round"><line x1="15" y1="31" x2="13" y2="38"/><line x1="24" y1="31" x2="22" y2="38"/><line x1="33" y1="31" x2="31" y2="38"/></g></svg>',
            'rain'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 24a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#64748b"/><g stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"><line x1="14" y1="30" x2="11" y2="40"/><line x1="21" y1="30" x2="18" y2="40"/><line x1="28" y1="30" x2="25" y2="40"/><line x1="35" y1="30" x2="32" y2="40"/></g></svg>',
            'rain-heavy'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 22a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#475569"/><g stroke="#2563eb" stroke-width="3" stroke-linecap="round"><line x1="12" y1="28" x2="8" y2="42"/><line x1="20" y1="28" x2="16" y2="42"/><line x1="28" y1="28" x2="24" y2="42"/><line x1="36" y1="28" x2="32" y2="42"/></g></svg>',
            'freezing'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 24a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#94a3b8"/><g stroke="#38bdf8" stroke-width="2" stroke-linecap="round"><line x1="14" y1="30" x2="12" y2="38"/><line x1="24" y1="30" x2="22" y2="38"/><line x1="34" y1="30" x2="32" y2="38"/></g><g fill="#38bdf8"><circle cx="12" cy="40" r="2"/><circle cx="22" cy="40" r="2"/><circle cx="32" cy="40" r="2"/></g></svg>',
            'snow-light'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 24a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#94a3b8"/><g fill="#bfdbfe"><circle cx="16" cy="33" r="2"/><circle cx="24" cy="36" r="2"/><circle cx="32" cy="33" r="2"/></g></svg>',
            'snow'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 22a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#64748b"/><g fill="#93c5fd"><circle cx="14" cy="30" r="2.5"/><circle cx="24" cy="33" r="2.5"/><circle cx="34" cy="30" r="2.5"/><circle cx="19" cy="38" r="2.5"/><circle cx="29" cy="38" r="2.5"/></g></svg>',
            'snow-heavy'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 20a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#475569"/><g fill="#60a5fa"><circle cx="12" cy="28" r="3"/><circle cx="24" cy="28" r="3"/><circle cx="36" cy="28" r="3"/><circle cx="18" cy="36" r="3"/><circle cx="30" cy="36" r="3"/></g></svg>',
            'shower'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><circle cx="16" cy="12" r="6" fill="#f59e0b" opacity=".7"/><path d="M12 26a7 7 0 0 1 3-11 8 8 0 0 1 15-2 6 6 0 0 1 3 10Z" fill="#94a3b8"/><g stroke="#3b82f6" stroke-width="2" stroke-linecap="round"><line x1="18" y1="31" x2="16" y2="38"/><line x1="27" y1="31" x2="25" y2="38"/></g></svg>',
            'shower-heavy'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 24a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#64748b"/><g stroke="#2563eb" stroke-width="2.5" stroke-linecap="round"><line x1="14" y1="30" x2="11" y2="40"/><line x1="22" y1="30" x2="19" y2="40"/><line x1="30" y1="30" x2="27" y2="40"/><line x1="38" y1="30" x2="35" y2="40"/></g></svg>',
            'thunder'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path d="M10 22a7 7 0 0 1 3-13 9 9 0 0 1 17-2 7 7 0 0 1 4 12Z" fill="#475569"/><polygon points="22,26 18,34 23,34 20,44 32,32 26,32 29,26" fill="#facc15"/><g stroke="#3b82f6" stroke-width="2" stroke-linecap="round"><line x1="12" y1="28" x2="10" y2="36"/><line x1="38" y1="28" x2="36" y2="36"/></g></svg>',
        ];
        return $s[$id] ?? $s['clear'];
    }

    /* ==================================================================
     * Date/Weekday helpers
     * ================================================================*/

    public static function weekday_short( string $date ): string {
        $dow = (int) gmdate( 'w', strtotime( $date ) );
        $de  = [ 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' ];
        $en  = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
        return ( NAWS_Lang::lang() === 'de' ) ? $de[$dow] : $en[$dow];
    }

    public static function weekday_full( string $date ): string {
        $dow = (int) gmdate( 'w', strtotime( $date ) );
        $de  = [ 'Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag' ];
        $en  = [ 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday' ];
        return ( NAWS_Lang::lang() === 'de' ) ? $de[$dow] : $en[$dow];
    }

    public static function date_short( string $date ): string {
        $ts = strtotime( $date );
        if ( NAWS_Lang::lang() === 'de' ) {
            $m = ['','Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
            return gmdate( 'd',$ts).'. '.$m[(int)gmdate( 'n',$ts)];
        }
        return wp_date( 'M d', $ts );
    }

    public static function is_today( string $date ): bool {
        return $date === wp_date( 'Y-m-d' );
    }
}
