<?php
/**
 * Centralized logging for the NAWS plugin.
 *
 * Stores structured log entries in wp_options with a rolling window.
 * Replaces scattered error_log() calls with a unified interface.
 *
 * @package NAWS
 * @since   1.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Logger {

	/** Option key for the general error/warning log. */
	const LOG_OPTION = 'naws_error_log';

	/** Maximum number of log entries kept. */
	const MAX_ENTRIES = 200;

	/** Severity levels. */
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_INFO    = 'info';

	/**
	 * Log an error.
	 *
	 * @param string $context  Component name (e.g. 'database', 'api', 'cron').
	 * @param string $message  Human-readable message.
	 * @param array  $data     Optional extra data (never includes secrets).
	 */
	public static function error( $context, $message, $data = [] ) {
		self::add( self::LEVEL_ERROR, $context, $message, $data );
	}

	/**
	 * Log a warning.
	 *
	 * @param string $context  Component name.
	 * @param string $message  Human-readable message.
	 * @param array  $data     Optional extra data.
	 */
	public static function warning( $context, $message, $data = [] ) {
		self::add( self::LEVEL_WARNING, $context, $message, $data );
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $context  Component name.
	 * @param string $message  Human-readable message.
	 * @param array  $data     Optional extra data.
	 */
	public static function info( $context, $message, $data = [] ) {
		self::add( self::LEVEL_INFO, $context, $message, $data );
	}

	/**
	 * Internal: add a log entry.
	 *
	 * @param string $level    Severity level.
	 * @param string $context  Component name.
	 * @param string $message  Message text.
	 * @param array  $data     Extra data.
	 */
	private static function add( $level, $context, $message, $data ) {
		// Sanitise data: never log sensitive values
		$safe_data = self::sanitise_data( $data );

		$entry = [
			'time'    => time(),
			'level'   => $level,
			'context' => sanitize_text_field( $context ),
			'message' => sanitize_text_field( $message ),
		];

		if ( ! empty( $safe_data ) ) {
			$entry['data'] = $safe_data;
		}

		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_ENTRIES );

		update_option( self::LOG_OPTION, $log, false );

		// Also log to PHP error_log for server-side debugging (errors and warnings only)
		if ( $level === self::LEVEL_ERROR || $level === self::LEVEL_WARNING ) {
			error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				'[NAWS %s] %s: %s%s',
				strtoupper( $level ),
				$context,
				$message,
				! empty( $safe_data ) ? ' | ' . wp_json_encode( $safe_data ) : ''
			) );
		}
	}

	/**
	 * Get the full log (newest first).
	 *
	 * @param string|null $level   Optional: filter by level.
	 * @param int         $limit   Max entries to return.
	 * @return array
	 */
	public static function get_log( $level = null, $limit = 50 ) {
		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			return [];
		}

		if ( $level !== null ) {
			$log = array_filter( $log, function( $entry ) use ( $level ) {
				return ( $entry['level'] ?? '' ) === $level;
			} );
			$log = array_values( $log );
		}

		return array_slice( $log, 0, $limit );
	}

	/**
	 * Count entries by level.
	 *
	 * @return array  [ 'error' => int, 'warning' => int, 'info' => int ]
	 */
	public static function count_by_level() {
		$log    = get_option( self::LOG_OPTION, [] );
		$counts = [
			self::LEVEL_ERROR   => 0,
			self::LEVEL_WARNING => 0,
			self::LEVEL_INFO    => 0,
		];

		if ( ! is_array( $log ) ) {
			return $counts;
		}

		foreach ( $log as $entry ) {
			$lvl = $entry['level'] ?? '';
			if ( isset( $counts[ $lvl ] ) ) {
				$counts[ $lvl ]++;
			}
		}

		return $counts;
	}

	/**
	 * Count recent errors (last N minutes).
	 *
	 * @param int $minutes  Time window.
	 * @return int
	 */
	public static function count_recent_errors( $minutes = 60 ) {
		$log       = get_option( self::LOG_OPTION, [] );
		$threshold = time() - ( $minutes * MINUTE_IN_SECONDS );
		$count     = 0;

		if ( ! is_array( $log ) ) {
			return 0;
		}

		foreach ( $log as $entry ) {
			if ( ( $entry['level'] ?? '' ) !== self::LEVEL_ERROR ) {
				continue;
			}
			if ( ( $entry['time'] ?? 0 ) < $threshold ) {
				break; // Entries are newest-first, so stop when we pass the threshold
			}
			$count++;
		}

		return $count;
	}

	/**
	 * Clear the entire log.
	 */
	public static function clear() {
		update_option( self::LOG_OPTION, [], false );
	}

	/**
	 * Remove sensitive keys from data before logging.
	 *
	 * @param array $data  Raw data.
	 * @return array       Sanitised data.
	 */
	private static function sanitise_data( $data ) {
		if ( ! is_array( $data ) ) {
			return [];
		}

		$sensitive_keys = [
			'access_token',
			'refresh_token',
			'client_secret',
			'api_key',
			'password',
			'token',
			'secret',
			'auth',
			'authorization',
		];

		$safe = [];
		foreach ( $data as $key => $value ) {
			$lower_key = strtolower( $key );
			$is_sensitive = false;
			foreach ( $sensitive_keys as $sk ) {
				if ( strpos( $lower_key, $sk ) !== false ) {
					$is_sensitive = true;
					break;
				}
			}

			if ( $is_sensitive ) {
				$safe[ $key ] = '***REDACTED***';
			} elseif ( is_array( $value ) ) {
				$safe[ $key ] = self::sanitise_data( $value );
			} elseif ( is_string( $value ) && strlen( $value ) > 500 ) {
				$safe[ $key ] = substr( $value, 0, 200 ) . '...[truncated]';
			} else {
				$safe[ $key ] = $value;
			}
		}

		return $safe;
	}
}
