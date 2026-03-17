<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Helpers {

    public static function get_label( $parameter ) {
        $labels = [
            'Temperature'       => naws__( 'param_temperature' ),
            'CO2'               => naws__( 'param_co2' ),
            'Humidity'          => naws__( 'param_humidity' ),
            'Noise'             => naws__( 'param_noise' ),
            'Pressure'          => naws__( 'param_pressure_rel' ),
            'AbsolutePressure'  => naws__( 'param_pressure_abs' ),
            'Rain'              => naws__( 'param_rain_1h' ),
            'sum_rain_1'        => naws__( 'param_rain_1h' ),
            'sum_rain_24'       => naws__( 'param_rain_24h' ),
            'WindStrength'      => naws__( 'param_wind_speed' ),
            'WindAngle'         => naws__( 'param_wind_dir' ),
            'GustStrength'      => naws__( 'param_gust_speed' ),
            'GustAngle'         => naws__( 'param_gust_dir' ),
            'min_temp'          => naws__( 'param_temp_min' ),
            'max_temp'          => naws__( 'param_temp_max' ),
            'date_min_temp'     => naws__( 'param_temp_min' ),
            'date_max_temp'     => naws__( 'param_temp_max' ),
            'health_idx'        => 'Health Index',
        ];
        return $labels[ $parameter ] ?? ucfirst( str_replace( '_', ' ', $parameter ) );
    }

    public static function get_icon( $parameter ) {
        // Map parameter names to icon set keys
        $param_to_key = [
            'Temperature'      => 'temp',
            'CO2'              => 'co2',
            'Humidity'         => 'humid',
            'Noise'            => 'noise',
            'Pressure'         => 'press',
            'AbsolutePressure' => 'press',
            'Rain'             => 'rain',
            'sum_rain_1'       => 'rain',
            'sum_rain_24'      => 'rain',
            'WindStrength'     => 'wind',
            'WindAngle'        => 'wind',
            'GustStrength'     => 'wind',
            'GustAngle'        => 'wind',
            'health_idx'       => 'temp',
        ];

        if ( class_exists( 'NAWS_Icons' ) ) {
            $key = $param_to_key[ $parameter ] ?? null;
            if ( $key ) {
                $icons = NAWS_Icons::get_set();
                return $icons[ $key ] ?? '📊';
            }
        }

        // Fallback to emojis if NAWS_Icons not loaded
        $emoji_fallback = [
            'Temperature' => '🌡️', 'CO2' => '💨', 'Humidity' => '💧',
            'Noise' => '🔊', 'Pressure' => '🔵', 'AbsolutePressure' => '🔵',
            'Rain' => '🌧️', 'sum_rain_1' => '🌧️', 'sum_rain_24' => '🌧️',
            'WindStrength' => '🌬️', 'WindAngle' => '🧭',
            'GustStrength' => '🌪️', 'GustAngle' => '🧭', 'health_idx' => '❤️',
        ];
        return $emoji_fallback[ $parameter ] ?? '📊';
    }

    public static function get_css_class( $parameter ) {
        $classes = [
            'Temperature'      => 'naws-temp',
            'CO2'              => 'naws-co2',
            'Humidity'         => 'naws-humidity',
            'Noise'            => 'naws-noise',
            'Pressure'         => 'naws-pressure',
            'AbsolutePressure' => 'naws-pressure',
            'Rain'             => 'naws-rain',
            'sum_rain_1'       => 'naws-rain',
            'sum_rain_24'      => 'naws-rain',
            'WindStrength'     => 'naws-wind',
            'GustStrength'     => 'naws-wind',
            'health_idx'       => 'naws-health',
        ];
        return $classes[ $parameter ] ?? 'naws-other';
    }

    public static function get_unit( $parameter ) {
        $options  = get_option( 'naws_settings', [] );
        $temp_u   = $options['temperature_unit'] ?? 'C';
        $wind_u   = $options['wind_unit']        ?? 'kmh';
        $press_u  = $options['pressure_unit']    ?? 'mbar';
        $rain_u   = $options['rain_unit']        ?? 'mm';

        $temp_label = $temp_u === 'F' ? '°F' : '°C';
        $units = [
            'Temperature'      => $temp_label,
            'min_temp'         => $temp_label,
            'max_temp'         => $temp_label,
            'date_min_temp'    => $temp_label,
            'date_max_temp'    => $temp_label,
            'CO2'              => 'ppm',
            'Humidity'         => '%',
            'Noise'            => 'dB',
            'Pressure'         => $press_u === 'inHg' ? 'inHg' : ( $press_u === 'mmHg' ? 'mmHg' : 'mbar' ),
            'AbsolutePressure' => $press_u === 'inHg' ? 'inHg' : ( $press_u === 'mmHg' ? 'mmHg' : 'mbar' ),
            'Rain'             => $rain_u === 'in' ? 'in' : 'mm',
            'sum_rain_1'       => $rain_u === 'in' ? 'in' : 'mm',
            'sum_rain_24'      => $rain_u === 'in' ? 'in' : 'mm',
            'WindStrength'     => self::wind_unit_label( $wind_u ),
            'GustStrength'     => self::wind_unit_label( $wind_u ),
            'WindAngle'        => '°',
            'GustAngle'        => '°',
            'health_idx'       => '',
        ];
        return $units[ $parameter ] ?? '';
    }

    public static function wind_unit_label_public( $unit ) {
        return self::wind_unit_label( $unit );
    }

    private static function wind_unit_label( $unit ) {
        $labels = [ 'kmh' => 'km/h', 'ms' => 'm/s', 'mph' => 'mph', 'kn' => 'kn' ];
        return $labels[ $unit ] ?? 'km/h';
    }

    public static function format_value( $parameter, $value ) {
        $options = get_option( 'naws_settings', [] );

        $temp_params = [ 'Temperature', 'min_temp', 'max_temp', 'date_min_temp', 'date_max_temp' ];
        if ( in_array( $parameter, $temp_params, true ) ) {
            if ( ( $options['temperature_unit'] ?? 'C' ) === 'F' ) {
                $value = $value * 9 / 5 + 32;
            }
            return round( $value, 1 );
        }

        if ( in_array( $parameter, [ 'Rain', 'sum_rain_1', 'sum_rain_24' ], true ) ) {
            if ( ( $options['rain_unit'] ?? 'mm' ) === 'in' ) {
                return round( $value / 25.4, 3 );
            }
            return round( $value, 1 );
        }

        if ( in_array( $parameter, [ 'Pressure', 'AbsolutePressure' ], true ) ) {
            $unit = $options['pressure_unit'] ?? 'mbar';
            if ( $unit === 'inHg' ) return round( $value * 0.02953, 2 );
            if ( $unit === 'mmHg' ) return round( $value * 0.75006, 1 );
            return round( $value, 1 );
        }

        if ( in_array( $parameter, [ 'WindStrength', 'GustStrength' ], true ) ) {
            $unit = $options['wind_unit'] ?? 'kmh';
            if ( $unit === 'ms' )  return round( $value / 3.6, 1 );
            if ( $unit === 'mph' ) return round( $value * 0.62137, 1 );
            if ( $unit === 'kn' )  return round( $value * 0.53996, 1 );
            return round( $value, 1 );
        }

        if ( $parameter === 'CO2' )      return intval( $value );
        if ( $parameter === 'Noise' )    return intval( $value );
        if ( $parameter === 'Humidity' ) return intval( $value );

        return round( $value, 2 );
    }

    public static function get_co2_level( $ppm ) {
        if ( $ppm < 800 )  return [ 'level' => 'excellent', 'color' => '#10b981', 'label' => naws__( 'co2_excellent' ) ];
        if ( $ppm < 1000 ) return [ 'level' => 'good',      'color' => '#84cc16', 'label' => naws__( 'co2_good' ) ];
        if ( $ppm < 1500 ) return [ 'level' => 'fair',      'color' => '#f59e0b', 'label' => naws__( 'co2_fair' ) ];
        if ( $ppm < 2000 ) return [ 'level' => 'poor',      'color' => '#f97316', 'label' => naws__( 'co2_poor' ) ];
        return                    [ 'level' => 'unhealthy',  'color' => '#ef4444', 'label' => naws__( 'co2_unhealthy' ) ];
    }

    public static function degrees_to_compass( $deg ) {
        $directions = [ 'N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW' ];
        return $directions[ round( $deg / 22.5 ) % 16 ];
    }

    public static function get_all_parameters() {
        return [
            'Temperature'      => naws__( 'param_temperature' ),
            'CO2'              => naws__( 'param_co2' ),
            'Humidity'         => naws__( 'param_humidity' ),
            'Noise'            => naws__( 'param_noise' ),
            'Pressure'         => naws__( 'param_pressure_rel' ),
            'AbsolutePressure' => naws__( 'param_pressure_abs' ),
            'Rain'             => naws__( 'param_rain_1h' ),
            'sum_rain_24'      => naws__( 'param_rain_24h' ),
            'WindStrength'     => naws__( 'param_wind_speed' ),
            'WindAngle'        => naws__( 'param_wind_dir' ),
            'GustStrength'     => naws__( 'param_gust_speed' ),
            'GustAngle'        => naws__( 'param_gust_dir' ),
            'health_idx'       => 'Health Index',
        ];
    }

    public static function module_type_label( $type ) {
        $labels = [
            'NAMain'    => naws__( 'mod_base_sub' ),
            'NAModule1' => naws__( 'mod_outdoor' ),
            'NAModule2' => naws__( 'mod_wind' ),
            'NAModule3' => naws__( 'mod_rain' ),
            'NAModule4' => naws__( 'mod_indoor4_sub' ),
            'NHC'       => 'Home Coach',
        ];
        return $labels[ $type ] ?? $type;
    }
}
