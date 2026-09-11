<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rules and state machine behind the e-mail notifications.
 *
 * Pure: no WordPress call, no database, no clock. The snapshot, the
 * settings, the previous state and the time come in; the new state, the
 * changes to mail and the status rows for the admin page go out. Everything
 * here is testable with `php tests/test-notify-rules.php` alone.
 *
 * Thresholds are always in base units — °C, km/h, mm — the units Netatmo
 * delivers in and the tables store. Only the admin page converts.
 *
 * @package NAWS
 * @since 2.0.0
 */
final class NAWS_Notify_Rules {

    /** Battery-powered module types (everything but the base station). */
    const MODULE_TYPES = [ 'NAModule1', 'NAModule2', 'NAModule3', 'NAModule4' ];

    /**
     * The rule catalogue. Keys are the ids used in the option and the state.
     *
     * group    'station' | 'weather'         — the two sections of the page
     * scope    'site' | 'station' | 'module' — what one state entry stands for
     * types    module types the rule applies to (empty for site rules)
     * field    column of naws_modules the rule reads, or
     * reading  parameter of the latest readings the rule reads
     * param    the one setting the rule has: 'threshold' | 'level' | 'minutes' | ''
     * kind     how to sanitize/format it: percent | level | minutes | temp | wind | rain | ''
     * levels   for level rules: name => [ raise at >=, clear at <= ]
     * hold_on  seconds the warning condition must hold before it counts
     * hold_off seconds the clear condition must hold before it counts
     * clears   whether the rule sends an all-clear mail
     */
    public static function catalog(): array {
        return [
            'battery' => [
                'group' => 'station', 'scope' => 'module', 'types' => self::MODULE_TYPES,
                'field' => 'battery_percent', 'param' => 'threshold', 'kind' => 'percent',
                'default' => 20, 'min' => 1, 'max' => 99,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'rf' => [
                'group' => 'station', 'scope' => 'module', 'types' => self::MODULE_TYPES,
                'field' => 'rf_status', 'param' => 'level', 'kind' => 'level',
                'default' => 'low', 'levels' => [ 'low' => [ 90, 80 ], 'medium' => [ 80, 70 ] ],
                'hold_on' => 1800, 'hold_off' => 1800, 'clears' => true,
            ],
            'wifi' => [
                'group' => 'station', 'scope' => 'station', 'types' => [ 'NAMain' ],
                'field' => 'wifi_status', 'param' => 'level', 'kind' => 'level',
                'default' => 'bad', 'levels' => [ 'bad' => [ 86, 71 ], 'average' => [ 71, 56 ] ],
                'hold_on' => 1800, 'hold_off' => 1800, 'clears' => true,
            ],
            'station_silent' => [
                'group' => 'station', 'scope' => 'station', 'types' => [ 'NAMain' ],
                'field' => 'last_status_store', 'param' => 'minutes', 'kind' => 'minutes',
                'default' => 60, 'min' => 10, 'max' => 1440,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'module_silent' => [
                'group' => 'station', 'scope' => 'module', 'types' => self::MODULE_TYPES,
                'field' => 'last_message', 'param' => 'minutes', 'kind' => 'minutes',
                'default' => 60, 'min' => 10, 'max' => 1440,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'sync_failed' => [
                'group' => 'station', 'scope' => 'site', 'types' => [],
                'field' => '', 'param' => '', 'kind' => '', 'default' => null,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'auth_required' => [
                'group' => 'station', 'scope' => 'site', 'types' => [],
                'field' => '', 'param' => '', 'kind' => '', 'default' => null,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => true,
            ],
            'frost' => [
                'group' => 'weather', 'scope' => 'module', 'types' => [ 'NAModule1' ],
                'reading' => 'Temperature', 'param' => 'threshold', 'kind' => 'temp',
                'default' => 0.0, 'min' => -50.0, 'max' => 50.0,
                'hold_on' => 0, 'hold_off' => 3600, 'clears' => true,
            ],
            'gust' => [
                'group' => 'weather', 'scope' => 'module', 'types' => [ 'NAModule2' ],
                'reading' => 'GustStrength', 'param' => 'threshold', 'kind' => 'wind',
                'default' => 60.0, 'min' => 1.0, 'max' => 300.0,
                'hold_on' => 0, 'hold_off' => 3600, 'clears' => true,
            ],
            'rain' => [
                'group' => 'weather', 'scope' => 'module', 'types' => [ 'NAModule3' ],
                'reading' => 'sum_rain_24', 'param' => 'threshold', 'kind' => 'rain',
                'default' => 20.0, 'min' => 0.1, 'max' => 500.0,
                'hold_on' => 0, 'hold_off' => 0, 'clears' => false,
            ],
        ];
    }

    /** Settings as shipped: no recipients, every rule off with its default parameter. */
    public static function defaults(): array {
        $rules = [];
        foreach ( self::catalog() as $id => $def ) {
            $rule = [ 'enabled' => 0 ];
            if ( $def['param'] !== '' ) {
                $rule[ $def['param'] ] = $def['default'];
            }
            $rules[ $id ] = $rule;
        }
        return [ 'recipients' => [], 'rules' => $rules ];
    }

    /** A value entered in the display unit, in the base unit (°C, km/h, mm). */
    public static function to_base( string $kind, float $v, array $units ): float {
        switch ( $kind ) {
            case 'temp':
                return ( $units['temperature_unit'] ?? 'C' ) === 'F' ? ( $v - 32 ) * 5 / 9 : $v;
            case 'wind':
                switch ( $units['wind_unit'] ?? 'kmh' ) {
                    case 'ms':  return $v * 3.6;
                    case 'mph': return $v / 0.62137;
                    case 'kn':  return $v / 0.53996;
                }
                return $v;
            case 'rain':
                return ( $units['rain_unit'] ?? 'mm' ) === 'in' ? $v * 25.4 : $v;
        }
        return $v;
    }

    /** A stored base-unit value in the display unit; the factors of NAWS_Helpers::format_value(). */
    public static function to_display( string $kind, float $v, array $units ): float {
        switch ( $kind ) {
            case 'temp':
                return ( $units['temperature_unit'] ?? 'C' ) === 'F' ? round( $v * 9 / 5 + 32, 1 ) : round( $v, 1 );
            case 'wind':
                switch ( $units['wind_unit'] ?? 'kmh' ) {
                    case 'ms':  return round( $v / 3.6, 1 );
                    case 'mph': return round( $v * 0.62137, 1 );
                    case 'kn':  return round( $v * 0.53996, 1 );
                }
                return round( $v, 1 );
            case 'rain':
                return ( $units['rain_unit'] ?? 'mm' ) === 'in' ? round( $v / 25.4, 2 ) : round( $v, 1 );
        }
        return $v;
    }

    /** The unit shown next to a threshold of this kind. */
    public static function unit_label( string $kind, array $units ): string {
        switch ( $kind ) {
            case 'percent': return '%';
            case 'minutes': return 'min';
            case 'temp':    return ( $units['temperature_unit'] ?? 'C' ) === 'F' ? '°F' : '°C';
            case 'wind':
                $labels = [ 'kmh' => 'km/h', 'ms' => 'm/s', 'mph' => 'mph', 'kn' => 'kn' ];
                return $labels[ $units['wind_unit'] ?? 'kmh' ] ?? 'km/h';
            case 'rain':    return ( $units['rain_unit'] ?? 'mm' ) === 'in' ? 'in' : 'mm';
        }
        return '';
    }

    /**
     * The value a rule looks at, as stored: a column, a reading, or for the
     * two silence rules the timestamp of the last message. null when absent.
     *
     * @return int|float|null
     */
    public static function value_of( string $rule, array $module ) {
        $def = self::catalog()[ $rule ] ?? null;
        if ( ! $def ) {
            return null;
        }
        if ( $rule === 'station_silent' ) {
            return isset( $module['last_status_store'] ) ? (int) $module['last_status_store'] : null;
        }
        if ( $rule === 'module_silent' ) {
            $ts = $module['last_message'] ?? $module['last_seen'] ?? null;
            return $ts === null ? null : (int) $ts;
        }
        if ( ! empty( $def['reading'] ) ) {
            $r = $module['readings'][ $def['reading'] ] ?? null;
            return ( is_array( $r ) && isset( $r['value'] ) ) ? (float) $r['value'] : null;
        }
        if ( ! empty( $def['field'] ) ) {
            $v = $module[ $def['field'] ] ?? null;
            return ( $v === null || $v === '' ) ? null : (int) $v;
        }
        return null;
    }

    /**
     * Is the rule in its warning state for this module right now?
     *
     * true  — warning condition holds (or, while $active, the clear condition
     *         does not hold yet: hysteresis)
     * false — clear condition holds (or the warning condition does not)
     * null  — suspended: wrong module type, no value, stale reading
     *
     * @param array $cfg          The rule's settings: threshold | level | minutes.
     * @param bool  $active       Whether the state entry is currently active.
     * @param int   $now          Unix time of this run.
     * @param int   $stale_after  Seconds after which a reading no longer counts.
     */
    public static function condition( string $rule, array $module, array $cfg, bool $active, int $now, int $stale_after ): ?bool {
        $def = self::catalog()[ $rule ] ?? null;
        if ( ! $def || $def['scope'] === 'site' ) {
            return null;
        }
        if ( ! in_array( $module['module_type'] ?? '', $def['types'], true ) ) {
            return null;
        }
        if ( ! empty( $def['reading'] ) ) {
            $r = $module['readings'][ $def['reading'] ] ?? null;
            if ( ! is_array( $r ) || ! isset( $r['value'], $r['at'] ) || ( $now - (int) $r['at'] ) > $stale_after ) {
                return null;
            }
        }
        $v = self::value_of( $rule, $module );

        switch ( $rule ) {
            case 'battery':
                if ( $v === null ) { return null; }
                $t = (int) ( $cfg['threshold'] ?? $def['default'] );
                return $active ? ! ( $v >= $t + 10 ) : ( $v < $t );

            case 'rf':
            case 'wifi':
                if ( $v === null ) { return null; }
                $level = $cfg['level'] ?? $def['default'];
                [ $on, $off ] = $def['levels'][ $level ] ?? $def['levels'][ $def['default'] ];
                return $active ? ! ( $v <= $off ) : ( $v >= $on );

            case 'station_silent':
            case 'module_silent':
                $reachable = $module['reachable'] ?? null;
                if ( $v === null && $reachable === null ) { return null; }
                $minutes = (int) ( $cfg['minutes'] ?? $def['default'] );
                $silent  = ( $reachable !== null && (int) $reachable === 0 );
                if ( $v !== null && ( $now - $v ) > $minutes * 60 ) { $silent = true; }
                return $silent;

            case 'frost':
                $t = (float) ( $cfg['threshold'] ?? $def['default'] );
                return $active ? ! ( $v > $t + 1 ) : ( $v <= $t );

            case 'gust':
            case 'rain':
                $t = (float) ( $cfg['threshold'] ?? $def['default'] );
                return $v >= $t;
        }
        return null;
    }
}
