<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Labels that are addressed by key at runtime.
 *
 * Most of the interface translates through plain __() calls at the point of
 * use. These do not: the key is assembled or passed through at runtime, so the
 * string cannot sit at the call site where the .pot scanner would find it.
 * They are listed here instead, one literal __() per key, which keeps them
 * extractable and keeps the lookup a closed set.
 *
 * Returns an empty string for an unknown key; callers decide what to fall back to.
 *
 * @package NAWS
 */
function naws_label( string $key ): string {
    switch ( $key ) {

        // Font origin groups in the Appearance page.
        case 'appearance_font_group_wp':           return __( 'Theme fonts', 'xtx-integration-for-netatmo' );
        case 'appearance_font_group_elementor':    return __( 'Loaded by Elementor', 'xtx-integration-for-netatmo' );
        case 'appearance_font_group_generic':      return __( 'Standard fonts', 'xtx-integration-for-netatmo' );

        // Encryption state messages on the dashboard and settings page.
        case 'crypto_state_label':                 return __( 'Credentials', 'xtx-integration-for-netatmo' );
        case 'crypto_state_ok':                    return __( 'Stored encrypted', 'xtx-integration-for-netatmo' );
        case 'crypto_state_warn':                  return __( 'Needs attention', 'xtx-integration-for-netatmo' );
        case 'crypto_no_openssl':                  return __( 'The PHP openssl extension is missing on this server. Credentials cannot be stored encrypted, so they are not stored at all.', 'xtx-integration-for-netatmo' );
        case 'crypto_no_gcm':                      return __( 'This server does not know the aes-256-gcm cipher. Credentials cannot be stored encrypted, so they are not stored at all.', 'xtx-integration-for-netatmo' );
        case 'crypto_weak_key':                    return __( 'AUTH_KEY in wp-config.php is the sample value or too short. Your credentials are encrypted, but with a key anyone can reconstruct.', 'xtx-integration-for-netatmo' );
        case 'crypto_key_changed':                 return __( 'The WordPress salts have changed since the credentials were stored, so they can no longer be read. Please connect to Netatmo again.', 'xtx-integration-for-netatmo' );
        case 'crypto_save_failed':                 return __( 'The credentials were NOT saved: they could not be stored encrypted. The previously stored value is unchanged.', 'xtx-integration-for-netatmo' );
        case 'crypto_connect_failed':              return __( 'The connection could not be stored securely. See the notices above for the reason.', 'xtx-integration-for-netatmo' );
        case 'crypto_salt_link':                   return __( 'Generate new salts', 'xtx-integration-for-netatmo' );

        // Lunar eclipse types.
        case 'eclipse_total':                      return __( 'Total', 'xtx-integration-for-netatmo' );
        case 'eclipse_partial':                    return __( 'Partial', 'xtx-integration-for-netatmo' );
        case 'eclipse_penumbral':                  return __( 'Penumbral', 'xtx-integration-for-netatmo' );

        // Sidebar widget labels.
        case 'wgt_rain':                           return _x( 'Rain', 'wgt_rain', 'xtx-integration-for-netatmo' );
        case 'wgt_wind':                           return __( 'Wind', 'xtx-integration-for-netatmo' );
        case 'wgt_heading':                        return __( 'Sidebar widget', 'xtx-integration-for-netatmo' );
        case 'wgt_desc':                           return __( 'Compact weather widget for narrow columns. Shows the icon and outdoor temperature, rain and wind below that, then the forecast. Placed with the [naws_weather_widget] shortcode.', 'xtx-integration-for-netatmo' );
        case 'wgt_days_label':                     return _x( 'Forecast days', 'wgt_days_label', 'xtx-integration-for-netatmo' );
        case 'wgt_days_3':                         return __( '3 days', 'xtx-integration-for-netatmo' );
        case 'wgt_days_5':                         return __( '5 days', 'xtx-integration-for-netatmo' );
        case 'wgt_days_desc':                      return __( 'Three days gives each column 77 pixels, five gives only 46. On narrow sidebars three reads considerably better.', 'xtx-integration-for-netatmo' );
        case 'wgt_width_label':                    return __( 'Width', 'xtx-integration-for-netatmo' );
        case 'wgt_scheme_label':                   return __( 'Colour scheme', 'xtx-integration-for-netatmo' );
        case 'wgt_scheme_light':                   return _x( 'Light', 'widget colour scheme', 'xtx-integration-for-netatmo' );
        case 'wgt_scheme_dark':                    return _x( 'Dark', 'widget colour scheme', 'xtx-integration-for-netatmo' );
        case 'wgt_scheme_transparent':             return _x( 'Transparent', 'widget colour scheme', 'xtx-integration-for-netatmo' );
        case 'wgt_scheme_desc':                    return __( 'Light is the white card. Dark is the same card in dark colours, for a dark sidebar. Transparent draws no card at all: the text takes the sidebar’s own colour and the lines and chips are a faint shade of it, so the widget blends into whatever it is placed on. A single placement can override this with scheme="dark" on the shortcode.', 'xtx-integration-for-netatmo' );
        case 'wgt_width_desc':                     return __( 'Between 250 and 500 pixels. Icon, figures and spacing grow with it — at 500 pixels the weather icon is 96 pixels instead of 64. Where the column is narrower than the setting, the widget shrinks with it rather than overflowing.', 'xtx-integration-for-netatmo' );
        case 'wgt_preview_none':                   return __( 'Nothing displayable right now — neither station readings nor forecast are available. The widget would output nothing.', 'xtx-integration-for-netatmo' );

        // Sensor parameter names.
        case 'param_temp_indoor':                  return __( 'Indoor Temperature', 'xtx-integration-for-netatmo' );
        case 'param_pressure_rel':                 return __( 'Pressure (relative)', 'xtx-integration-for-netatmo' );
        case 'param_pressure_abs':                 return __( 'Pressure (absolute)', 'xtx-integration-for-netatmo' );
        case 'param_co2':                          return __( 'CO₂ Concentration', 'xtx-integration-for-netatmo' );
        case 'param_noise':                        return __( 'Noise Level', 'xtx-integration-for-netatmo' );
        case 'param_temp_out':                     return __( 'Outdoor Temperature (current)', 'xtx-integration-for-netatmo' );
        case 'param_temp_min':                     return __( 'Min Temperature (day)', 'xtx-integration-for-netatmo' );
        case 'param_temp_avg':                     return __( 'Temp Ø (°C)', 'xtx-integration-for-netatmo' );
        case 'param_pressure_avg':                 return __( 'Pressure Ø (hPa)', 'xtx-integration-for-netatmo' );
        case 'param_temp_max':                     return __( 'Max Temperature (day)', 'xtx-integration-for-netatmo' );
        case 'param_humidity':                     return _x( 'Humidity', 'param_humidity', 'xtx-integration-for-netatmo' );
        case 'param_wind_speed':                   return __( 'Wind Speed', 'xtx-integration-for-netatmo' );
        case 'param_gust_speed':                   return __( 'Gust Speed', 'xtx-integration-for-netatmo' );
        case 'param_wind_dir':                     return __( 'Wind Direction (compass)', 'xtx-integration-for-netatmo' );
        case 'param_gust_dir':                     return __( 'Gust Direction', 'xtx-integration-for-netatmo' );
        case 'param_rain_current':                 return __( 'Rain (current)', 'xtx-integration-for-netatmo' );
        case 'param_rain_1h':                      return __( 'Total last hour', 'xtx-integration-for-netatmo' );
        case 'param_rain_24h':                     return __( 'Total last 24h', 'xtx-integration-for-netatmo' );
        case 'param_temperature':                  return __( 'Temperature', 'xtx-integration-for-netatmo' );
        case 'param_co2_conc':                     return __( 'CO₂ Concentration', 'xtx-integration-for-netatmo' );

        // Labels of the [naws_calc] catalogue entries.
        case 'calc_dewpoint':                      return __( 'Dew point', 'xtx-integration-for-netatmo' );
        case 'calc_feels_like':                    return _x( 'Feels like', 'calc_feels_like', 'xtx-integration-for-netatmo' );
        case 'calc_heat_index':                    return __( 'Heat index', 'xtx-integration-for-netatmo' );
        case 'calc_wet_bulb':                      return __( 'Wet-bulb temperature', 'xtx-integration-for-netatmo' );
        case 'calc_bioclimate':                    return __( 'Thermal sensation', 'xtx-integration-for-netatmo' );
        case 'calc_wind_compass':                  return __( 'Wind direction', 'xtx-integration-for-netatmo' );
        case 'calc_co2_level':                     return __( 'CO₂ rating', 'xtx-integration-for-netatmo' );
        case 'calc_sunrise':                       return __( 'Sunrise', 'xtx-integration-for-netatmo' );
        case 'calc_sunset':                        return __( 'Sunset', 'xtx-integration-for-netatmo' );
        case 'calc_daylength':                     return __( 'Length of day', 'xtx-integration-for-netatmo' );
        case 'calc_moon_phase':                    return __( 'Moon phase', 'xtx-integration-for-netatmo' );
        case 'calc_moon_illumination':             return __( 'Moon illumination', 'xtx-integration-for-netatmo' );
        case 'calc_next_supermoon':                return __( 'Next supermoon', 'xtx-integration-for-netatmo' );
        case 'calc_next_lunar_eclipse':            return __( 'Next lunar eclipse', 'xtx-integration-for-netatmo' );
        case 'calc_ice_days':                      return __( 'Ice days', 'xtx-integration-for-netatmo' );
        case 'calc_frost_days':                    return __( 'Frost days', 'xtx-integration-for-netatmo' );
        case 'calc_summer_days':                   return __( 'Summer days', 'xtx-integration-for-netatmo' );
        case 'calc_hot_days':                      return __( 'Hot days', 'xtx-integration-for-netatmo' );
        case 'calc_tropical_nights':               return __( 'Tropical nights', 'xtx-integration-for-netatmo' );
        case 'calc_heating_days':                  return __( 'Heating days', 'xtx-integration-for-netatmo' );
        case 'calc_cooling_days':                  return __( 'Cooling days', 'xtx-integration-for-netatmo' );
        case 'calc_hdd':                           return __( 'Heating degree days', 'xtx-integration-for-netatmo' );
        case 'calc_cdd':                           return __( 'Cooling degree days', 'xtx-integration-for-netatmo' );
        case 'calc_gdd':                           return __( 'Growing degree days', 'xtx-integration-for-netatmo' );
        case 'calc_glts':                          return __( 'Grassland temperature sum', 'xtx-integration-for-netatmo' );
        case 'calc_glts_start':                    return __( 'Start of the growing season', 'xtx-integration-for-netatmo' );
        case 'calc_glts_pending':                  return __( 'not yet reached', 'xtx-integration-for-netatmo' );
        case 'calc_note':                          return /* translators: 1: number of days with data, 2: number of days in the period. */ __( '(from %1$d of %2$d days)', 'xtx-integration-for-netatmo' );
        case 'calc_spi':                           return __( 'Standardized Precipitation Index (SPI)', 'xtx-integration-for-netatmo' );

        // [naws_records] and [naws_on_this_day] (since 1.9.11).
        case 'rec_title':                          return __( 'Records', 'xtx-integration-for-netatmo' );
        case 'rec_title_year':                     return /* translators: %d: the year the records are from. */ __( 'Records %d', 'xtx-integration-for-netatmo' );
        case 'rec_hottest_day':                    return __( 'Hottest day', 'xtx-integration-for-netatmo' );
        case 'rec_coldest_night':                  return __( 'Coldest night', 'xtx-integration-for-netatmo' );
        case 'rec_warmest_night':                  return __( 'Warmest night', 'xtx-integration-for-netatmo' );
        case 'rec_coldest_day':                    return __( 'Coldest day', 'xtx-integration-for-netatmo' );
        case 'rec_widest_range':                   return __( 'Largest daily range', 'xtx-integration-for-netatmo' );
        case 'rec_warmest_month':                  return __( 'Warmest month', 'xtx-integration-for-netatmo' );
        case 'rec_coldest_month':                  return __( 'Coldest month', 'xtx-integration-for-netatmo' );
        case 'rec_wettest_day':                    return __( 'Wettest day', 'xtx-integration-for-netatmo' );
        case 'rec_wettest_month':                  return __( 'Wettest month', 'xtx-integration-for-netatmo' );
        case 'rec_longest_dry_spell':              return __( 'Longest dry spell', 'xtx-integration-for-netatmo' );
        case 'rec_longest_wet_spell':              return __( 'Longest wet spell', 'xtx-integration-for-netatmo' );
        case 'rec_strongest_gust':                 return __( 'Strongest gust', 'xtx-integration-for-netatmo' );
        case 'rec_longest_frost':                  return __( 'Longest frost period', 'xtx-integration-for-netatmo' );
        case 'rec_longest_heat_wave':              return __( 'Longest heat wave', 'xtx-integration-for-netatmo' );
        case 'rec_longest_summer_run':             return __( 'Longest run of summer days', 'xtx-integration-for-netatmo' );
        case 'rec_col_record':                     return _x( 'Record', 'table column', 'xtx-integration-for-netatmo' );
        case 'rec_col_value':                      return _x( 'Value', 'table column', 'xtx-integration-for-netatmo' );
        case 'rec_col_when':                       return _x( 'When', 'table column', 'xtx-integration-for-netatmo' );
        case 'rec_since':                          return /* translators: 1: first date with readings, 2: "365 days" or similar. */ __( 'Since %1$s · %2$s with readings', 'xtx-integration-for-netatmo' );
        case 'otd_title':                          return __( 'This day in earlier years', 'xtx-integration-for-netatmo' );
        case 'otd_col_year':                       return __( 'Year', 'xtx-integration-for-netatmo' );
        case 'otd_col_min':                        return _x( 'Low', 'daily minimum', 'xtx-integration-for-netatmo' );
        case 'otd_col_max':                        return _x( 'High', 'daily maximum', 'xtx-integration-for-netatmo' );
        case 'otd_col_avg':                        return _x( 'Mean', 'daily average', 'xtx-integration-for-netatmo' );
        case 'otd_col_rain':                       return _x( 'Rain', 'daily sum', 'xtx-integration-for-netatmo' );
        case 'otd_record':                         return __( 'record for this day', 'xtx-integration-for-netatmo' );

        // [naws_sunpath] (since 1.9.11).
        case 'sun_title':                          return __( 'Sun path', 'xtx-integration-for-netatmo' );
        case 'sun_aria':                           return /* translators: 1: sunrise time, 2: sunset time, 3: day length. */ __( 'Sun path: sunrise %1$s, sunset %2$s, day length %3$s.', 'xtx-integration-for-netatmo' );
        case 'sun_day_length':                     return /* translators: %s: the day length, e.g. "13:19". */ __( 'Day length %s', 'xtx-integration-for-netatmo' );
        case 'sun_shorter':                        return /* translators: %s: minutes shorter than yesterday, e.g. "3 min". */ __( '%s shorter than yesterday', 'xtx-integration-for-netatmo' );
        case 'sun_longer':                         return /* translators: %s: minutes longer than yesterday, e.g. "3 min". */ __( '%s longer than yesterday', 'xtx-integration-for-netatmo' );
        case 'sun_same':                           return __( 'as long as yesterday', 'xtx-integration-for-netatmo' );
        case 'sun_extremes':                       return /* translators: 1: longest day of the year, 2: shortest day of the year. */ __( 'longest day %1$s, shortest %2$s', 'xtx-integration-for-netatmo' );
        case 'sun_minutes':                        return /* translators: %d: number of minutes. */ __( '%d min', 'xtx-integration-for-netatmo' );

        // Thermal sensation classes returned by NAWS_Astro.
        case 'sens_very_cold':                     return __( 'very cold', 'xtx-integration-for-netatmo' );
        case 'sens_cold':                          return __( 'cold', 'xtx-integration-for-netatmo' );
        case 'sens_cool':                          return __( 'cool', 'xtx-integration-for-netatmo' );
        case 'sens_pleasantly_cool':               return __( 'pleasantly cool', 'xtx-integration-for-netatmo' );
        case 'sens_pleasant':                      return __( 'pleasant', 'xtx-integration-for-netatmo' );
        case 'sens_warm':                          return __( 'warm', 'xtx-integration-for-netatmo' );
        case 'sens_hot':                           return __( 'hot', 'xtx-integration-for-netatmo' );
        case 'sens_extremely_hot':                 return __( 'extremely hot', 'xtx-integration-for-netatmo' );

        // Weather icon states, used as the icon aria-label.
        case 'wx_state_clear_day':                 return __( 'Clear', 'xtx-integration-for-netatmo' );
        case 'wx_state_clear_night':               return __( 'Clear night', 'xtx-integration-for-netatmo' );
        case 'wx_state_fair':                      return _x( 'Fair', 'wx_state_fair', 'xtx-integration-for-netatmo' );
        case 'wx_state_partly':                    return __( 'Partly cloudy', 'xtx-integration-for-netatmo' );
        case 'wx_state_overcast':                  return __( 'Overcast', 'xtx-integration-for-netatmo' );
        case 'wx_state_fog':                       return __( 'Fog', 'xtx-integration-for-netatmo' );
        case 'wx_state_rain':                      return _x( 'Rain', 'wx_state_rain', 'xtx-integration-for-netatmo' );
        case 'wx_state_rain_heavy':                return __( 'Heavy rain', 'xtx-integration-for-netatmo' );
        case 'wx_state_snow':                      return __( 'Snow', 'xtx-integration-for-netatmo' );
        case 'wx_state_sleet':                     return __( 'Sleet or hail', 'xtx-integration-for-netatmo' );
        case 'wx_state_thunder':                   return __( 'Thunderstorm', 'xtx-integration-for-netatmo' );
        case 'wx_state_storm':                     return __( 'Storm', 'xtx-integration-for-netatmo' );

        // Live card labels, chosen by module type at runtime.
        case 'card_temperature':                   return __( 'Temperature', 'xtx-integration-for-netatmo' );
        case 'card_humidity':                      return _x( 'Humidity', 'card_humidity', 'xtx-integration-for-netatmo' );
        case 'card_pressure':                      return __( 'Air Pressure', 'xtx-integration-for-netatmo' );
        case 'card_co2':                           return __( 'CO₂', 'xtx-integration-for-netatmo' );
        case 'card_noise':                         return _x( 'Noise', 'card_noise', 'xtx-integration-for-netatmo' );
        case 'card_rain':                          return __( 'Precipitation', 'xtx-integration-for-netatmo' );
        case 'card_wind_gusts':                    return __( 'Wind &amp; Gusts', 'xtx-integration-for-netatmo' );
        case 'card_wind':                          return __( 'Wind', 'xtx-integration-for-netatmo' );
        case 'card_gusts':                         return __( 'Gusts', 'xtx-integration-for-netatmo' );
        case 'card_wind_dir':                      return __( 'Wind Direction', 'xtx-integration-for-netatmo' );
        case 'card_temp_min':                      return __( 'Temp. Min', 'xtx-integration-for-netatmo' );
        case 'card_temp_max':                      return __( 'Temp. Max', 'xtx-integration-for-netatmo' );
    }

    return '';
}
