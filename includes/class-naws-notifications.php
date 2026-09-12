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
        $wind = $o['wind_unit'] ?? 'kmh';
        return [
            'temperature_unit' => ( $o['temperature_unit'] ?? 'C' ) === 'F' ? 'F' : 'C',
            'wind_unit'        => in_array( $wind, [ 'kmh', 'ms', 'mph', 'kn' ], true ) ? $wind : 'kmh',
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

    // ── Hooks ───────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'naws_data_synced', [ __CLASS__, 'on_synced' ] );
        add_action( 'naws_sync_failed', [ __CLASS__, 'on_failed' ], 10, 2 );
    }

    /** After a successful fetch: fresh snapshot, every rule. */
    public static function on_synced( $saved = 0 ): void {
        self::run( [ 'sync' => 'ok', 'consecutive_errors' => 0, 'auth_required' => (bool) get_option( 'naws_auth_required' ), 'error' => '' ] );
    }

    /** After a failed fetch: no snapshot, only the two site rules move. */
    public static function on_failed( $message = '', $errors = 0 ): void {
        self::run( [
            'sync'               => 'failed',
            'consecutive_errors' => (int) $errors,
            'auth_required'      => (bool) get_option( 'naws_auth_required' ) || (string) $message === 'auth_required',
            'error'              => (string) $message,
        ] );
    }

    /** One evaluation run. Never throws: the cron callback must survive it. */
    private static function run( array $ctx ): void {
        $locked = false;
        try {
            $settings = self::get_settings();
            $state    = get_option( self::STATE_KEY, [] );
            $state    = is_array( $state ) ? $state : [];
            if ( ! self::any_enabled( $settings ) && ! $state ) {
                return;
            }
            $locked = self::lock();
            if ( ! $locked ) {
                return;
            }
            $ctx['interval'] = self::interval();
            $snapshot        = ( $ctx['sync'] ?? 'ok' ) === 'ok' ? self::snapshot() : [ 'modules' => [] ];
            $result          = NAWS_Notify_Rules::evaluate( $snapshot, $settings, $state, time(), $ctx );
            update_option( self::STATE_KEY, $result['state'], false );
            if ( $result['events'] ) {
                $mail = self::compose( $result['events'] );
                $to   = self::recipients();
                $sent = self::send( $to, $mail['subject'], $mail['body'] );
                self::log( [
                    'time'    => time(),
                    'subject' => $mail['subject'],
                    'to'      => $to,
                    'sent'    => $sent,
                    'events'  => array_map( static fn( $e ) => [ 'rule' => $e['rule'], 'kind' => $e['kind'], 'module_name' => $e['module_name'], 'value' => $e['value'] ], $result['events'] ),
                ] );
            }
        } catch ( \Throwable $e ) {
            NAWS_Logger::error( 'notify', 'Notification run failed: ' . $e->getMessage() );
        } finally {
            if ( $locked ) {
                self::unlock();
            }
        }
    }

    private static function any_enabled( array $settings ): bool {
        foreach ( $settings['rules'] as $r ) {
            if ( ! empty( $r['enabled'] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * add_option() is the one check WordPress offers that a second request
     * cannot overtake without an object cache: it fails when the row exists.
     * A lock older than LOCK_TTL is an orphan from a crashed run.
     */
    private static function lock(): bool {
        $stamp = (int) get_option( self::LOCK_KEY, 0 );
        if ( $stamp && time() - $stamp > self::LOCK_TTL ) {
            delete_option( self::LOCK_KEY );
        }
        return (bool) add_option( self::LOCK_KEY, time(), '', false );
    }

    private static function unlock(): void {
        delete_option( self::LOCK_KEY );
    }

    /** The effective fetch interval in seconds; night mode skips every other run. */
    private static function interval(): int {
        $base = (int) NAWS_Cron::base_interval();
        return NAWS_Cron::is_night_mode() ? 2 * $base : $base;
    }

    // ── Snapshot ────────────────────────────────────────────────────

    /** Active modules with their status columns and latest readings, keyed by module id. */
    public static function snapshot(): array {
        $out = [];
        foreach ( NAWS_Database::get_modules( true ) as $m ) {
            $id = (string) ( $m['module_id'] ?? '' );
            if ( $id === '' ) {
                continue;
            }
            $out[ $id ] = [
                'module_id'         => $id,
                'station_id'        => (string) ( $m['station_id'] ?? '' ),
                'module_name'       => (string) ( $m['module_name'] ?? '' ),
                'module_type'       => (string) ( $m['module_type'] ?? '' ),
                'battery_percent'   => self::int_or_null( $m['battery_percent'] ?? null ),
                'rf_status'         => self::int_or_null( $m['rf_status'] ?? null ),
                'wifi_status'       => self::int_or_null( $m['wifi_status'] ?? null ),
                'reachable'         => self::int_or_null( $m['reachable'] ?? null ),
                'last_status_store' => self::int_or_null( $m['last_status_store'] ?? null ),
                'last_seen'         => self::int_or_null( $m['last_seen'] ?? null ),
                'last_message'      => self::int_or_null( $m['last_message'] ?? null ),
                'readings'          => [],
            ];
        }
        foreach ( NAWS_Database::get_latest_readings() as $r ) {
            $id = (string) ( $r['module_id'] ?? '' );
            if ( isset( $out[ $id ] ) && isset( $r['parameter'], $r['value'], $r['recorded_at'] ) ) {
                $out[ $id ]['readings'][ (string) $r['parameter'] ] = [ 'value' => (float) $r['value'], 'at' => (int) $r['recorded_at'] ];
            }
        }
        return [ 'modules' => $out ];
    }

    private static function int_or_null( $v ): ?int {
        return ( $v === null || $v === '' ) ? null : (int) $v;
    }

    /** The status rows for the admin page: a dry run, nothing saved, nothing sent. */
    public static function status_rows(): array {
        $state = get_option( self::STATE_KEY, [] );
        $poll  = NAWS_Cron::get_polling_state();
        $ctx   = [
            'interval'           => self::interval(),
            'sync'               => 'ok',
            'consecutive_errors' => (int) ( $poll['consecutive_errors'] ?? 0 ),
            'auth_required'      => (bool) get_option( 'naws_auth_required' ),
            'error'              => '',
        ];
        return NAWS_Notify_Rules::evaluate( self::snapshot(), self::get_settings(), is_array( $state ) ? $state : [], time(), $ctx )['rows'];
    }

    // ── Mail ────────────────────────────────────────────────────────

    /** Subject and plain-text body for a list of changes from one run. */
    public static function compose( array $events ): array {
        $site   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
        $raises = count( array_filter( $events, static fn( $e ) => ( $e['kind'] ?? '' ) === 'raise' ) );
        $clears = count( $events ) - $raises;

        if ( count( $events ) === 1 ) {
            $e     = $events[0];
            $title = naws_label( 'ntf_rule_' . $e['rule'] );
            if ( $e['module_name'] !== '' ) {
                $title .= ' – ' . $e['module_name'];
            }
            $shown = self::format_measure( $e['rule'], $e['value'] );
            if ( $shown !== '' ) {
                $title .= ' (' . $shown . ')';
            }
            $what = $e['kind'] === 'clear' ? naws_label( 'ntf_all_clear' ) . ': ' . $title : $title;
        } else {
            $parts = [];
            if ( $raises ) {
                /* translators: %d: number of warnings in one mail */
                $parts[] = sprintf( _n( '%d warning', '%d warnings', $raises, 'xtx-integration-for-netatmo' ), $raises );
            }
            if ( $clears ) {
                /* translators: %d: number of all-clears in one mail */
                $parts[] = sprintf( _n( '%d all-clear', '%d all-clears', $clears, 'xtx-integration-for-netatmo' ), $clears );
            }
            $what = implode( ', ', $parts );
        }

        $subject = sanitize_text_field( sprintf( '[%s] Netatmo: %s', $site, $what ) );
        $blocks  = [];
        foreach ( $events as $e ) {
            $blocks[] = self::event_block( $e );
        }
        $body = implode( "\n\n", $blocks ) . "\n\n" . naws_label( 'ntf_manage' ) . ': ' . admin_url( 'admin.php?page=naws-notifications' ) . "\n";
        return [ 'subject' => $subject, 'body' => $body ];
    }

    /** One paragraph of the body: what, which module, value and threshold, since when. */
    private static function event_block( array $e ): string {
        $lines   = [];
        $lines[] = ( $e['kind'] === 'clear' ? naws_label( 'ntf_all_clear' ) : naws_label( 'ntf_warning' ) ) . ': ' . naws_label( 'ntf_rule_' . $e['rule'] );
        if ( $e['module_name'] !== '' ) {
            $lines[] = naws_label( 'ntf_module' ) . ': ' . $e['module_name'] . ' (' . NAWS_Helpers::module_type_label( $e['module_type'] ) . ')';
        }
        if ( $e['rule'] === 'sync_failed' ) {
            $lines[] = naws_label( 'ntf_measure_sync_failed' ) . ': ' . (int) $e['value'];
            if ( (string) $e['error'] !== '' ) {
                $lines[] = sprintf( naws_label( 'ntf_sync_error' ), $e['error'] );
            }
        } elseif ( $e['rule'] === 'auth_required' ) {
            $lines[] = naws_label( 'ntf_auth_hint' );
        } else {
            $shown = self::format_measure( $e['rule'], $e['value'] );
            $thr   = self::format_threshold( $e['rule'], $e['threshold'] );
            if ( $shown !== '' ) {
                $lines[] = naws_label( 'ntf_measure_' . $e['rule'] ) . ': ' . $shown . ( $thr !== '' ? ' (' . sprintf( naws_label( 'ntf_threshold' ), $thr ) . ')' : '' );
            }
        }
        $lines[] = $e['kind'] === 'clear'
            ? sprintf( naws_label( 'ntf_from_to' ), self::stamp( (int) $e['since'] ), self::stamp( (int) $e['now'] ) )
            : sprintf( naws_label( 'ntf_since' ), self::stamp( (int) $e['since'] ) );
        return implode( "\n", $lines );
    }

    /** A measured value the way the mail and the page show it; '' when there is none. */
    public static function format_measure( string $rule, $value ): string {
        if ( $value === null || $value === '' ) {
            return '';
        }
        $def = NAWS_Notify_Rules::catalog()[ $rule ] ?? null;
        if ( ! $def ) {
            return '';
        }
        switch ( $def['kind'] ) {
            case 'percent': return (int) $value . ' %';
            case 'level':   return (string) (int) $value;
            case 'minutes': return self::stamp( (int) $value );
            case 'temp':
            case 'wind':
            case 'rain':
                $p = $def['reading'];
                return NAWS_Helpers::format_value( $p, (float) $value ) . ' ' . NAWS_Helpers::get_unit( $p );
        }
        return (string) $value;
    }

    /** A threshold with its unit or level name; '' for rules without one. */
    public static function format_threshold( string $rule, $threshold ): string {
        if ( $threshold === null || $threshold === '' ) {
            return '';
        }
        $def = NAWS_Notify_Rules::catalog()[ $rule ] ?? null;
        if ( ! $def || $def['param'] === '' ) {
            return '';
        }
        switch ( $def['kind'] ) {
            case 'percent': return (int) $threshold . ' %';
            case 'minutes': return (int) $threshold . ' min';
            case 'level':
                $on = $def['levels'][ $threshold ][0] ?? $def['levels'][ $def['default'] ][0];
                return naws_label( 'ntf_level_' . $threshold ) . ' (≥ ' . (int) $on . ')';
            case 'temp':
            case 'wind':
            case 'rain':
                $p = $def['reading'];
                return NAWS_Helpers::format_value( $p, (float) $threshold ) . ' ' . NAWS_Helpers::get_unit( $p );
        }
        return (string) $threshold;
    }

    /** Date and time in the site's format and timezone. */
    public static function stamp( int $ts ): string {
        return wp_date( (string) get_option( 'date_format', 'd.m.Y' ) . ', ' . (string) get_option( 'time_format', 'H:i' ), $ts );
    }

    public static function send( array $to, string $subject, string $body ): bool {
        if ( ! $to ) {
            NAWS_Logger::error( 'notify', 'No recipient for: ' . $subject );
            return false;
        }
        $ok = (bool) wp_mail( $to, $subject, $body );
        if ( ! $ok ) {
            NAWS_Logger::error( 'notify', 'wp_mail() returned false for: ' . $subject );
        }
        return $ok;
    }

    /** The test mail from the admin page, in the site's language, listing the rules switched on. */
    public static function send_test(): bool {
        $switched = ( determine_locale() !== get_locale() ) ? (bool) switch_to_locale( get_locale() ) : false;
        try {
            $settings = self::get_settings();
            $site     = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
            $lines    = [ sprintf( naws_label( 'ntf_test_intro' ), $site ), self::stamp( time() ), '', naws_label( 'ntf_test_rules' ) ];
            $any      = false;
            foreach ( NAWS_Notify_Rules::catalog() as $id => $def ) {
                $cfg = $settings['rules'][ $id ];
                if ( empty( $cfg['enabled'] ) ) {
                    continue;
                }
                $any     = true;
                $thr     = $def['param'] !== '' ? self::format_threshold( $id, $cfg[ $def['param'] ] ) : '';
                $lines[] = '- ' . naws_label( 'ntf_rule_' . $id ) . ( $thr !== '' ? ' (' . $thr . ')' : '' );
            }
            if ( ! $any ) {
                $lines[] = naws_label( 'ntf_test_none' );
            }
            $subject = sanitize_text_field( sprintf( '[%s] Netatmo: %s', $site, naws_label( 'ntf_test_subject' ) ) );
            $body    = implode( "\n", $lines ) . "\n\n" . naws_label( 'ntf_manage' ) . ': ' . admin_url( 'admin.php?page=naws-notifications' ) . "\n";
            $to      = self::recipients();
            $sent    = self::send( $to, $subject, $body );
            self::log( [ 'time' => time(), 'subject' => $subject, 'to' => $to, 'sent' => $sent, 'events' => [] ] );
            return $sent;
        } finally {
            if ( $switched ) {
                restore_previous_locale();
            }
        }
    }

    // ── Log ─────────────────────────────────────────────────────────

    public static function log( array $entry ): void {
        $log = get_option( self::LOG_KEY, [] );
        $log = is_array( $log ) ? $log : [];
        array_unshift( $log, $entry );
        update_option( self::LOG_KEY, array_slice( $log, 0, self::LOG_MAX ), false );
    }

    public static function get_log(): array {
        $log = get_option( self::LOG_KEY, [] );
        return is_array( $log ) ? $log : [];
    }
}
