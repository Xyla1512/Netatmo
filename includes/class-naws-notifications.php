<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * E-mail notifications: the WordPress side.
 *
 * Reads and cleans the option, builds the snapshot from the database, runs
 * NAWS_Notify_Rules::evaluate() after every fetch, sends the mail, keeps a
 * short log. The rules themselves live in NAWS_Notify_Rules and know
 * nothing about WordPress.
 *
 * @package NAWS
 * @since 2.0.0
 */
final class NAWS_Notifications {

    const OPTION_KEY     = 'naws_notifications';
    const STATE_KEY      = 'naws_notify_state';
    const LOG_KEY        = 'naws_notify_log';
    const LOCK_KEY       = 'naws_notify_lock';
    const LOG_MAX        = 50;
    const MAX_RECIPIENTS = 20;
    const LOCK_TTL       = 120;

    /** The display units from the plugin settings, always all three keys. */
    public static function units(): array {
        $o = get_option( 'naws_settings', [] );
        $o = is_array( $o ) ? $o : [];
        return [
            'temperature_unit' => ( $o['temperature_unit'] ?? 'C' ) === 'F' ? 'F' : 'C',
            'wind_unit'        => in_array( $o['wind_unit'] ?? 'kmh', [ 'kmh', 'ms', 'mph', 'kn' ], true ) ? $o['wind_unit'] : 'kmh',
            'rain_unit'        => ( $o['rain_unit'] ?? 'mm' ) === 'in' ? 'in' : 'mm',
        ];
    }

    /** The stored settings, cleaned and filled with defaults; a broken option yields the defaults. */
    public static function get_settings(): array {
        $raw = get_option( self::OPTION_KEY, [] );
        return self::sanitize( is_array( $raw ) ? $raw : [] );
    }

    /** The non-empty tokens of the recipients field: newline, comma or semicolon separated. */
    public static function split_recipients( string $text ): array {
        $parts = preg_split( '/[\r\n,;]+/', $text ) ?: [];
        return array_values( array_filter( array_map( 'trim', $parts ), static fn( $s ) => $s !== '' ) );
    }

    /**
     * Whitelist over the catalogue. Thresholds are taken as base units
     * (°C, km/h, mm); a form posts display units and goes through
     * from_form() first.
     */
    public static function sanitize( array $input ): array {
        $out     = NAWS_Notify_Rules::defaults();
        $catalog = NAWS_Notify_Rules::catalog();

        $raw = $input['recipients'] ?? [];
        if ( is_string( $raw ) ) {
            $raw = self::split_recipients( $raw );
        }
        $list = [];
        foreach ( (array) $raw as $addr ) {
            $addr = sanitize_email( trim( (string) $addr ) );
            if ( $addr !== '' && is_email( $addr ) && ! in_array( $addr, $list, true ) ) {
                $list[] = $addr;
            }
            if ( count( $list ) >= self::MAX_RECIPIENTS ) {
                break;
            }
        }
        $out['recipients'] = $list;

        $rules = is_array( $input['rules'] ?? null ) ? $input['rules'] : [];
        foreach ( $catalog as $id => $def ) {
            $r = is_array( $rules[ $id ] ?? null ) ? $rules[ $id ] : [];
            $out['rules'][ $id ]['enabled'] = empty( $r['enabled'] ) ? 0 : 1;
            switch ( $def['param'] ) {
                case 'threshold':
                    if ( isset( $r['threshold'] ) && is_numeric( $r['threshold'] ) ) {
                        if ( $def['kind'] === 'percent' ) {
                            $out['rules'][ $id ]['threshold'] = (int) max( $def['min'], min( $def['max'], absint( $r['threshold'] ) ) );
                        } else {
                            $out['rules'][ $id ]['threshold'] = round( max( (float) $def['min'], min( (float) $def['max'], (float) $r['threshold'] ) ), 1 );
                        }
                    }
                    break;
                case 'level':
                    if ( isset( $r['level'] ) && is_string( $r['level'] ) && isset( $def['levels'][ $r['level'] ] ) ) {
                        $out['rules'][ $id ]['level'] = $r['level'];
                    }
                    break;
                case 'minutes':
                    if ( isset( $r['minutes'] ) && is_numeric( $r['minutes'] ) ) {
                        $out['rules'][ $id ]['minutes'] = (int) max( $def['min'], min( $def['max'], absint( $r['minutes'] ) ) );
                    }
                    break;
            }
        }
        return $out;
    }

    /** What the admin form posts: recipients as text, thresholds in display units. */
    public static function from_form( string $recipients, array $rules, array $units ): array {
        $catalog = NAWS_Notify_Rules::catalog();
        foreach ( $rules as $id => $r ) {
            $def = $catalog[ $id ] ?? null;
            if ( $def && $def['param'] === 'threshold' && in_array( $def['kind'], [ 'temp', 'wind', 'rain' ], true )
                && is_array( $r ) && isset( $r['threshold'] ) && is_numeric( $r['threshold'] ) ) {
                $rules[ $id ]['threshold'] = NAWS_Notify_Rules::to_base( $def['kind'], (float) $r['threshold'], $units );
            }
        }
        return self::sanitize( [ 'recipients' => $recipients, 'rules' => $rules ] );
    }

    /** Where the mail goes: the stored list, or the site's admin address when it is empty. */
    public static function recipients(): array {
        $list = self::get_settings()['recipients'];
        if ( $list ) {
            return $list;
        }
        $admin = sanitize_email( (string) get_option( 'admin_email', '' ) );
        return ( $admin !== '' && is_email( $admin ) ) ? [ $admin ] : [];
    }
}
