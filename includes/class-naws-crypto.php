<?php
/**
 * NAWS Crypto – AES-256-GCM encryption for sensitive data at rest.
 *
 * Uses AUTH_KEY from wp-config.php as key derivation input.
 * Encrypted values carry a 'naws_enc:' prefix so plaintext
 * (pre-migration) values are detected and silently upgraded.
 *
 * @since 0.9.97
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NAWS_Crypto {

    /** Prefix that marks a value as encrypted */
    const PREFIX = 'naws_enc:';

    /** Cipher algorithm */
    const CIPHER = 'aes-256-gcm';

    /** GCM tag length in bytes */
    const TAG_LEN = 16;

    /* ================================================================
     * Core encrypt / decrypt
     * ================================================================*/

    /**
     * Encrypt a plaintext string.
     *
     * @param  string $plaintext
     * @return string  Prefixed base64: "naws_enc:<base64(iv.tag.ciphertext)>"
     */
    public static function encrypt( string $plaintext ): string {
        if ( $plaintext === '' ) return '';

        $key = self::derive_key();
        $iv  = random_bytes( 12 ); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',           // AAD
            self::TAG_LEN
        );

        if ( $ciphertext === false ) {
            error_log( 'NAWS Crypto: encryption failed – ' . openssl_error_string() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return $plaintext; // Fallback: return plaintext rather than lose data
        }

        // Pack: IV (12) + tag (16) + ciphertext
        return self::PREFIX . base64_encode( $iv . $tag . $ciphertext );
    }

    /**
     * Decrypt an encrypted string.
     *
     * Returns plaintext if the value is not encrypted (migration path).
     *
     * @param  string $value
     * @return string
     */
    public static function decrypt( $value ): string {
        if ( ! is_string( $value ) || $value === '' ) return is_string( $value ) ? $value : '';

        // Not encrypted? Return as-is (plaintext migration path)
        if ( ! self::is_encrypted( $value ) ) {
            return $value;
        }

        $raw = base64_decode( substr( $value, strlen( self::PREFIX ) ), true );
        if ( $raw === false || strlen( $raw ) < 12 + self::TAG_LEN + 1 ) {
            error_log( 'NAWS Crypto: invalid encrypted payload (decode failed)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        $iv         = substr( $raw, 0, 12 );
        $tag        = substr( $raw, 12, self::TAG_LEN );
        $ciphertext = substr( $raw, 12 + self::TAG_LEN );
        $key        = self::derive_key();

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ( $plaintext === false ) {
            error_log( 'NAWS Crypto: decryption failed – tag mismatch or corrupt data' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        return $plaintext;
    }

    /**
     * Check if a value is already encrypted (carries our prefix).
     */
    public static function is_encrypted( $value ): bool {
        if ( ! is_string( $value ) || $value === '' ) return false;
        return strpos( $value, self::PREFIX ) === 0;
    }

    /* ================================================================
     * wp_options convenience helpers
     * ================================================================*/

    /**
     * Store a secret in wp_options (encrypted).
     *
     * @param string $option_name  The option key.
     * @param string $plaintext    The value to store.
     * @param bool   $autoload     WordPress autoload flag.
     */
    public static function save_option( string $option_name, string $plaintext, bool $autoload = true ): void {
        $encrypted = self::encrypt( $plaintext );
        update_option( $option_name, $encrypted, $autoload );
    }

    /**
     * Read a secret from wp_options (transparently decrypted).
     *
     * If the stored value is plaintext (pre-migration), it is decrypted
     * as-is and then silently re-saved as encrypted (auto-upgrade).
     *
     * @param  string $option_name
     * @param  string $default
     * @return string  Plaintext value.
     */
    public static function get_option( string $option_name, string $default = '' ): string {
        $raw = \get_option( $option_name, $default );
        if ( $raw === '' || $raw === $default ) return $default;

        // Already encrypted → decrypt
        if ( self::is_encrypted( $raw ) ) {
            return self::decrypt( $raw );
        }

        // Plaintext (legacy) → auto-upgrade: re-save encrypted
        self::save_option( $option_name, $raw );
        return $raw;
    }

    /**
     * Delete a secret option.
     */
    public static function delete_option( string $option_name ): void {
        \delete_option( $option_name );
    }

    /* ================================================================
     * Array field helpers (for settings arrays like naws_settings)
     * ================================================================*/

    /**
     * Encrypt specific fields within an associative array.
     *
     * @param  array    $data   The settings array.
     * @param  string[] $fields Keys to encrypt.
     * @return array            Modified array with encrypted fields.
     */
    public static function encrypt_fields( array $data, array $fields ): array {
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) && $data[ $field ] !== '' ) {
                $data[ $field ] = self::encrypt( $data[ $field ] );
            }
        }
        return $data;
    }

    /**
     * Decrypt specific fields within an associative array.
     * Handles plaintext values transparently (migration).
     *
     * @param  array    $data   The settings array.
     * @param  string[] $fields Keys to decrypt.
     * @return array            Modified array with decrypted fields.
     */
    public static function decrypt_fields( array $data, array $fields ): array {
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) && $data[ $field ] !== '' ) {
                $data[ $field ] = self::decrypt( $data[ $field ] );
            }
        }
        return $data;
    }

    /* ================================================================
     * One-time migration: encrypt all existing plaintext secrets
     * ================================================================*/

    /**
     * Migrate all plaintext secrets to encrypted storage.
     * Safe to call multiple times – skips already-encrypted values.
     */
    public static function migrate() {
        // 1. Individual token options
        $secret_options = [ 'naws_access_token', 'naws_refresh_token' ];
        foreach ( $secret_options as $opt ) {
            $val = \get_option( $opt, '' );
            if ( $val !== '' && ! self::is_encrypted( $val ) ) {
                self::save_option( $opt, $val );
            }
        }

        // 2. Settings array: encrypt client_id/secret if still plaintext
        $settings = \get_option( 'naws_settings', [] );
        if ( is_array( $settings ) ) {
            $needs_save = false;
            foreach ( [ 'client_id', 'client_secret' ] as $field ) {
                $val = $settings[ $field ] ?? '';
                if ( $val !== '' && ! self::is_encrypted( $val ) ) {
                    $settings[ $field ] = self::encrypt( $val );
                    $needs_save = true;
                }
            }
            if ( $needs_save ) {
                \update_option( 'naws_settings', $settings );
            }
        }

        // 3. REST API key
        $rest_cfg = \get_option( 'naws_rest_api', [] );
        if ( is_array( $rest_cfg ) ) {
            $key = $rest_cfg['api_key'] ?? '';
            if ( $key !== '' && ! self::is_encrypted( $key ) ) {
                $rest_cfg['api_key'] = self::encrypt( $key );
                \update_option( 'naws_rest_api', $rest_cfg );
            }
        }

        \update_option( 'naws_crypto_migrated', NAWS_VERSION, false );
    }

    /* ================================================================
     * Key derivation
     * ================================================================*/

    /**
     * Derive a 256-bit encryption key from WordPress AUTH_KEY.
     *
     * Uses HKDF (HMAC-based Key Derivation) with a fixed salt
     * to produce a stable, high-entropy key. AUTH_KEY lives in
     * wp-config.php (filesystem), not in the database.
     *
     * @return string  32-byte binary key.
     */
    private static function derive_key(): string {
        // Primary source: AUTH_KEY from wp-config.php
        $source = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'naws-fallback-key-' . DB_NAME;

        // HKDF: extract-then-expand (RFC 5869)
        // hash_hkdf is available since PHP 7.1.2
        if ( function_exists( 'hash_hkdf' ) ) {
            return hash_hkdf( 'sha256', $source, 32, 'naws-encryption-v1', 'naws-salt-2025' );
        }

        // Fallback for edge cases: manual HKDF
        $prk = hash_hmac( 'sha256', $source, 'naws-salt-2025', true );
        return substr( hash_hmac( 'sha256', $prk . chr(1), 'naws-encryption-v1', true ), 0, 32 );
    }
}
