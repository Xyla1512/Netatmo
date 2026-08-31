<?php
/**
 * Settings screen.
 *
 * Layout (since 1.9.1): the connection card spans the full width because it
 * is the first thing anyone does here and needs no neighbour. Everything
 * below sits in two explicitly assigned columns rather than a flowing grid —
 * a flowing grid makes every row as tall as its tallest panel, which is what
 * previously left ~2000 px of dead space and pushed the forecast far down.
 *
 * Forms (since 1.9.1): the credentials keep their own small form; every other
 * setting lives in ONE form with ONE save button. Before 1.9.1 each of the
 * three forms carried hidden mirror copies of the fields it did not own —
 * the pre-1.7.0 workaround for the reset bug. Merge semantics in
 * sanitize_settings() replaced that in 1.7.0, but the mirrors stayed and
 * quietly defeated it: every save wrote every key back, stale values
 * included, and the decrypted client secret was rendered into the page three
 * times instead of once. They are gone. Do not reintroduce them.
 *
 * @package NAWS
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title">
        <span class="naws-title-icon">⚙️</span>
        <?php esc_html_e( 'XTX Netatmo — Settings', 'xtx-integration-for-netatmo' ); ?>
    </h1>

    <?php // A save that could not encrypt a credential redirects with this
          // flag instead of `updated`: the value was rejected, so saying
          // "saved" would be the one wrong answer here. ?>
    <?php if ( isset( $_GET['naws_crypto_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no data processed ?>
        <div class="notice notice-error"><p><?php esc_html_e( 'The credentials were NOT saved: they could not be stored encrypted. The previously stored value is unchanged.', 'xtx-integration-for-netatmo' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['updated'] ) && ! isset( $_GET['naws_crypto_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flags, no data processed ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'xtx-integration-for-netatmo' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no data processed ?>
        <div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Disconnected. You can now reconnect.', 'xtx-integration-for-netatmo' ); ?></p></div>
    <?php endif; ?>

    <?php
    $naws_crypto = NAWS_Crypto::health();
    if ( $naws_crypto['status'] !== 'ok' ) : ?>
        <div class="notice notice-warning">
            <?php foreach ( $naws_crypto['issues'] as $naws_issue ) : ?>
                <p>
                    <?php echo esc_html( naws_label( 'crypto_' . $naws_issue ) ); ?>
                    <?php if ( $naws_issue === 'weak_key' ) : ?>
                        <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Generate new salts', 'xtx-integration-for-netatmo' ); ?></a>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( get_option( 'naws_crypto_write_failed' ) ) : ?>
        <div class="notice notice-error">
            <p><strong><?php esc_html_e( 'The connection could not be stored securely. See the notices above for the reason.', 'xtx-integration-for-netatmo' ); ?></strong></p>
        </div>
    <?php endif; ?>

    <?php if ( get_option( 'naws_auth_required' ) ) : ?>
        <div class="notice notice-error">
            <p><strong>🔴 <?php esc_html_e( 'Netatmo Refresh Token expired!', 'xtx-integration-for-netatmo' ); ?></strong><br>
            <?php esc_html_e( 'The refresh token has been revoked or expired. Cron sync is paused until reconnected. Please click "Connect to Netatmo" below.', 'xtx-integration-for-netatmo' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $access_token  = NAWS_Crypto::get_option( 'naws_access_token', '' );
    $refresh_token = NAWS_Crypto::get_option( 'naws_refresh_token', '' );
    $token_expiry  = (int) get_option( 'naws_token_expiry', 0 );
    $oauth_debug   = get_option( 'naws_oauth_debug', null );

    if ( ! empty( $access_token ) && empty( $refresh_token ) ) : ?>
        <div class="notice notice-error">
            <p><strong>⚠️ <?php esc_html_e( 'No Refresh Token – this is causing the cron error!', 'xtx-integration-for-netatmo' ); ?></strong><br>
            <?php esc_html_e( 'Please click "Connect to Netatmo" below and reauthorize the app. This one-time step saves the refresh token permanently.', 'xtx-integration-for-netatmo' ); ?></p>
        </div>
    <?php elseif ( empty( $access_token ) && empty( $refresh_token ) ) : ?>
        <div class="notice notice-warning">
            <p><strong>🔌 <?php esc_html_e( 'Not yet connected to Netatmo.', 'xtx-integration-for-netatmo' ); ?></strong>
            <?php esc_html_e( 'Enter Client ID and Secret and click "Connect to Netatmo".', 'xtx-integration-for-netatmo' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $oauth_debug && is_array( $oauth_debug ) ) : ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>🔍 <?php esc_html_e( 'OAuth Debug', 'xtx-integration-for-netatmo' ); ?>:</strong>
            HTTP <?php echo esc_html( $oauth_debug['http_code'] ?? '?' ); ?> –
            <code><?php echo esc_html( wp_json_encode( $oauth_debug['body'] ?? [] ) ); ?></code></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $refresh_token ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ <strong><?php esc_html_e( 'Refresh Token present.', 'xtx-integration-for-netatmo' ); ?></strong>
            <?php if ( $token_expiry > time() ) : ?>
                <?php echo esc_html( sprintf( /* translators: %s: date and time the token expires. */ __( 'Access Token valid until: %s', 'xtx-integration-for-netatmo' ), wp_date( 'Y-m-d H:i', $token_expiry ) ) ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Access Token expired – will be renewed automatically on next sync.', 'xtx-integration-for-netatmo' ); ?>
            <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php /* ── Connection: full width, its own form, its own button ────── */ ?>
    <div class="naws-admin-panel naws-settings-connection">
        <div class="naws-panel-header">
            <h2><?php esc_html_e( 'Netatmo API Connection', 'xtx-integration-for-netatmo' ); ?></h2>
            <?php if ( $is_connected ) : ?>
                <span class="naws-badge naws-badge-success">✓ <?php esc_html_e( 'Connected', 'xtx-integration-for-netatmo' ); ?></span>
            <?php else : ?>
                <span class="naws-badge naws-badge-error">✗ <?php esc_html_e( 'Not connected', 'xtx-integration-for-netatmo' ); ?></span>
            <?php endif; ?>
        </div>

        <div class="naws-panel-body">
            <div class="naws-info-box naws-info-box--flush">
                <p><?php esc_html_e( 'To connect, you need a Netatmo Developer account. Create an app at', 'xtx-integration-for-netatmo' ); ?>
                   <a href="https://dev.netatmo.com" target="_blank" rel="noopener noreferrer">dev.netatmo.com</a> <?php esc_html_e( 'and copy the Client ID and Secret below.', 'xtx-integration-for-netatmo' ); ?></p>
                <p><strong><?php esc_html_e( 'Redirect URI to add in your Netatmo App:', 'xtx-integration-for-netatmo' ); ?></strong><br>
                   <code><?php echo esc_html( admin_url( 'admin.php?page=naws-settings' ) ); ?></code></p>
            </div>

            <?php // The only place the decrypted secret is rendered. Keep it that way. ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'naws_save_settings' ); ?>
                <input type="hidden" name="action" value="naws_save_settings">

                <table class="form-table naws-form-table">
                    <tr>
                        <th><?php esc_html_e( 'Client ID', 'xtx-integration-for-netatmo' ); ?></th>
                        <td>
                            <input type="text" name="naws_settings[client_id]" value="<?php echo esc_attr( $options['client_id'] ?? '' ); ?>"
                                   class="regular-text" placeholder="<?php echo esc_attr( __( 'Your Netatmo Client ID', 'xtx-integration-for-netatmo' ) ); ?>" autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Client Secret', 'xtx-integration-for-netatmo' ); ?></th>
                        <td>
                            <input type="password" name="naws_settings[client_secret]" value="<?php echo esc_attr( $options['client_secret'] ?? '' ); ?>"
                                   class="regular-text" id="naws-client-secret" autocomplete="off">
                            <button type="button" class="button" id="naws-toggle-secret"><?php esc_html_e( 'Show', 'xtx-integration-for-netatmo' ); ?></button>
                        </td>
                    </tr>
                </table>

                <div class="naws-actions">
                    <button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Credentials', 'xtx-integration-for-netatmo' ); ?></button>
                </div>
            </form>

            <hr class="naws-rule">

            <h3><?php esc_html_e( 'Connect via OAuth2', 'xtx-integration-for-netatmo' ); ?></h3>
            <p><?php esc_html_e( 'After saving your Client ID and Secret, click the button below to authorize:', 'xtx-integration-for-netatmo' ); ?></p>

            <?php // A div, not a p: this row contains the disconnect <form>, and a <p>
                  // may only hold phrasing content — the parser would close it before
                  // the form and the flex row would fall apart. ?>
            <div class="naws-actions">
                <?php if ( ! empty( $options['client_id'] ) && ! empty( $options['client_secret'] ) ) : ?>
                    <a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary naws-btn-connect">
                        🔑 <?php esc_html_e( 'Connect to Netatmo', 'xtx-integration-for-netatmo' ); ?>
                    </a>
                <?php else : ?>
                    <span class="description"><?php esc_html_e( 'Save Client ID and Secret first.', 'xtx-integration-for-netatmo' ); ?></span>
                <?php endif; ?>

                <?php if ( $is_connected ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="naws-inline-form">
                        <?php wp_nonce_field( 'naws_disconnect' ); ?>
                        <input type="hidden" name="action" value="naws_disconnect">
                        <button type="submit" class="button button-secondary"
                                onclick="return confirm('<?php echo esc_js( __( 'Disconnect from Netatmo?', 'xtx-integration-for-netatmo' ) ); ?>')">
                            🔌 <?php esc_html_e( 'Disconnect', 'xtx-integration-for-netatmo' ); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php /* ── Everything else: one form, one button ───────────────────── */ ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'naws_save_settings' ); ?>
        <input type="hidden" name="action" value="naws_save_settings">

        <?php // Columns are assigned by hand so neither ends far short of the other. ?>
        <div class="naws-settings-grid">
            <div class="naws-settings-col">

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php esc_html_e( 'Language & station', 'xtx-integration-for-netatmo' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php esc_html_e( 'Station Name', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="text" name="naws_settings[station_name]"
                                           value="<?php echo esc_attr( $options['station_name'] ?? '' ); ?>"
                                           placeholder="<?php echo esc_attr( __( 'e.g. My Weather Station Leipzig', 'xtx-integration-for-netatmo' ) ); ?>"
                                           class="regular-text">
                                    <p class="description"><?php esc_html_e( 'Displayed as default title in the live dashboard and shortcodes. Leave empty to use the WordPress site title.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php esc_html_e( 'Operation', 'xtx-integration-for-netatmo' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php esc_html_e( 'Cron Interval (minutes)', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <?php $naws_interval = NAWS_Cron::normalise_interval( $options['cron_interval'] ?? NAWS_Cron::DEFAULT_INTERVAL ); ?>
                                    <select name="naws_settings[cron_interval]">
                                        <?php foreach ( NAWS_Cron::INTERVALS as $naws_opt ) : ?>
                                            <option value="<?php echo esc_attr( $naws_opt ); ?>" <?php selected( $naws_interval, $naws_opt ); ?>>
                                                <?php echo esc_html( sprintf( /* translators: %d: polling interval in minutes. */ __( 'Every %d minutes', 'xtx-integration-for-netatmo' ), $naws_opt ) ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Minimum: 5 minutes. Note: Netatmo updates sensors every 5 minutes.', 'xtx-integration-for-netatmo' ); ?></p>
                                    <p class="description"><?php esc_html_e( 'A backoff kicks in automatically on trouble: after 3 consecutive errors the interval doubles (up to 120 minutes) and resets on the first success. This applies whether or not night mode is on.', 'xtx-integration-for-netatmo' ); ?></p>
                                    <?php // Raw output: the text carries <code> markup, so it goes through wp_kses_post(). ?>
                                    <p class="description"><?php echo wp_kses_post( __( 'WP-Cron is triggered by page views, not by a clock: if nobody opens the site, no fetch happens — during quiet nights possibly for hours. Night mode then has nothing left to slow down, and a gap appears in the readings; the daily summary retrieves the missing values from the Netatmo API later. The schedule only becomes reliable once <code>define( \'DISABLE_WP_CRON\', true );</code> is set in <code>wp-config.php</code> and a real server cron calls <code>wp-cron.php</code> at the interval you want.', 'xtx-integration-for-netatmo' ) ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Night Mode', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <?php // Hidden 0 first – see the note on the weather-icon checkbox. ?>
                                    <input type="hidden" name="naws_settings[night_mode]" value="0">
                                    <label>
                                        <input type="checkbox" name="naws_settings[night_mode]" value="1"
                                            <?php checked( ! empty( $options['night_mode'] ) ); ?>>
                                        <?php esc_html_e( 'Enable reduced polling between 23:00 and 06:00', 'xtx-integration-for-netatmo' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'During night hours every second fetch is skipped, doubling the interval. The window follows the timezone configured in WordPress.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Limit temperatures', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <p>
                                        <label><?php esc_html_e( 'Heating limit (°C)', 'xtx-integration-for-netatmo' ); ?><br>
                                        <input type="number" step="0.5" min="-10" max="30" name="naws_settings[heating_limit]"
                                            value="<?php echo esc_attr( $options['heating_limit'] ?? 15 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php esc_html_e( 'A day counts as a heating day when its mean falls below this. Germany 15 °C (VDI 2067), Austria and Switzerland 12 °C.', 'xtx-integration-for-netatmo' ); ?></p>
                                    <p>
                                        <label><?php esc_html_e( 'Room temperature (°C)', 'xtx-integration-for-netatmo' ); ?><br>
                                        <input type="number" step="0.5" min="10" max="30" name="naws_settings[room_temp]"
                                            value="<?php echo esc_attr( $options['room_temp'] ?? 20 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php esc_html_e( 'Reference temperature for heating degree days. 20 °C in every standard named here.', 'xtx-integration-for-netatmo' ); ?></p>
                                    <p>
                                        <label><?php esc_html_e( 'Cooling limit (°C)', 'xtx-integration-for-netatmo' ); ?><br>
                                        <input type="number" step="0.5" min="0" max="40" name="naws_settings[cooling_limit]"
                                            value="<?php echo esc_attr( $options['cooling_limit'] ?? 18 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php esc_html_e( 'A day counts as a cooling day when its mean rises above this. There is no single standard here — 18 °C and 21 °C are both in common use.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Data Retention', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <p><span class="naws-text-ok"><?php esc_html_e( '✅ All data is stored permanently.', 'xtx-integration-for-netatmo' ); ?></span><br>
                                    <span class="description"><?php esc_html_e( 'No automatic deletion. You can manually purge old data below if needed.', 'xtx-integration-for-netatmo' ); ?></span></p>
                                    <details class="naws-danger-details">
                                        <summary><?php esc_html_e( '⚠️ Manual Data Purge (Caution!)', 'xtx-integration-for-netatmo' ); ?></summary>
                                        <div class="naws-danger-body">
                                            <label><?php esc_html_e( 'Delete entries older than:', 'xtx-integration-for-netatmo' ); ?>
                                            <input type="number" id="naws-purge-days" value="365" min="30" class="small-text"> <?php esc_html_e( 'Days', 'xtx-integration-for-netatmo' ); ?></label>
                                            <button type="button" id="naws-purge-btn" class="button naws-btn-danger"><?php esc_html_e( 'Purge now', 'xtx-integration-for-netatmo' ); ?></button>
                                            <span id="naws-purge-result"></span>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php esc_html_e( 'Units', 'xtx-integration-for-netatmo' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php esc_html_e( 'Temperature', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <select name="naws_settings[temperature_unit]">
                                        <option value="C" <?php selected( $options['temperature_unit'] ?? 'C', 'C' ); ?>>°C – Celsius</option>
                                        <option value="F" <?php selected( $options['temperature_unit'] ?? 'C', 'F' ); ?>>°F – Fahrenheit</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Wind Speed', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <select name="naws_settings[wind_unit]">
                                        <option value="kmh" <?php selected( $options['wind_unit'] ?? 'kmh', 'kmh' ); ?>>km/h</option>
                                        <option value="ms"  <?php selected( $options['wind_unit'] ?? 'kmh', 'ms' ); ?>>m/s</option>
                                        <option value="mph" <?php selected( $options['wind_unit'] ?? 'kmh', 'mph' ); ?>>mph</option>
                                        <option value="kn"  <?php selected( $options['wind_unit'] ?? 'kmh', 'kn' ); ?>><?php esc_html_e( 'Knots', 'xtx-integration-for-netatmo' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Pressure', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <select name="naws_settings[pressure_unit]">
                                        <option value="mbar" <?php selected( $options['pressure_unit'] ?? 'mbar', 'mbar' ); ?>>mbar / hPa</option>
                                        <option value="inHg" <?php selected( $options['pressure_unit'] ?? 'mbar', 'inHg' ); ?>>inHg</option>
                                        <option value="mmHg" <?php selected( $options['pressure_unit'] ?? 'mbar', 'mmHg' ); ?>>mmHg</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html( _x( 'Rain', 'unit_rain', 'xtx-integration-for-netatmo' ) ); ?></th>
                                <td>
                                    <select name="naws_settings[rain_unit]">
                                        <option value="mm" <?php selected( $options['rain_unit'] ?? 'mm', 'mm' ); ?>>mm</option>
                                        <option value="in" <?php selected( $options['rain_unit'] ?? 'mm', 'in' ); ?>>inch</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div><!-- /left column -->

            <div class="naws-settings-col">

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php esc_html_e( 'Weather Forecast', 'xtx-integration-for-netatmo' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <p class="description naws-panel-intro"><?php esc_html_e( 'The 5-day forecast uses the free Open-Meteo API. Choose whether the location is determined automatically from your Netatmo station or entered manually.', 'xtx-integration-for-netatmo' ); ?></p>

                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php esc_html_e( 'Forecast Provider', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <select name="naws_settings[forecast_provider]">
                                        <option value="open_meteo" <?php selected( $options['forecast_provider'] ?? 'open_meteo', 'open_meteo' ); ?>><?php esc_html_e( 'Open-Meteo (global, open-source)', 'xtx-integration-for-netatmo' ); ?></option>
                                        <option value="yr_no"      <?php selected( $options['forecast_provider'] ?? 'open_meteo', 'yr_no' ); ?>><?php esc_html_e( 'Yr.no / MET Norway (accurate for Northern Europe)', 'xtx-integration-for-netatmo' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Select the weather API for the forecast. All providers are free and do not require an API key.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html( _x( 'Forecast days', 'forecast_days_label', 'xtx-integration-for-netatmo' ) ); ?></th>
                                <td>
                                    <select name="naws_settings[forecast_days]">
                                        <?php for ( $d = 1; $d <= 7; $d++ ) : ?>
                                            <option value="<?php echo intval( $d ); ?>" <?php selected( $options['forecast_days'] ?? 5, $d ); ?>>
                                                <?php echo intval( $d ); ?> <?php echo esc_html( $d === 1 ? __( 'Day', 'xtx-integration-for-netatmo' ) : __( 'Days', 'xtx-integration-for-netatmo' ) ); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'How many days the forecast should display (1–7). This is the default for the shortcode and live dashboard.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Location source', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <div class="naws-radio-list">
                                        <label>
                                            <input type="radio" name="naws_settings[forecast_location]" value="auto"
                                                <?php checked( $options['forecast_location'] ?? 'auto', 'auto' ); ?>>
                                            <?php esc_html_e( 'Automatic (from Netatmo station coordinates)', 'xtx-integration-for-netatmo' ); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="naws_settings[forecast_location]" value="manual"
                                                <?php checked( $options['forecast_location'] ?? 'auto', 'manual' ); ?>>
                                            <?php esc_html_e( 'Manual (enter city / postal code)', 'xtx-integration-for-netatmo' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php $naws_manual = ( $options['forecast_location'] ?? 'auto' ) === 'manual' ? '' : ' naws-row-dimmed'; ?>
                            <tr id="naws-forecast-manual-row" class="<?php echo esc_attr( ltrim( $naws_manual ) ); ?>">
                                <th><?php esc_html_e( 'City / Postal code', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="text" name="naws_settings[forecast_city]"
                                           value="<?php echo esc_attr( $options['forecast_city'] ?? '' ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( __( 'e.g. Leipzig', 'xtx-integration-for-netatmo' ) ); ?>">
                                    <p class="description"><?php esc_html_e( 'Enter a city name, optionally with postal code for better accuracy.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr id="naws-forecast-country-row" class="<?php echo esc_attr( ltrim( $naws_manual ) ); ?>">
                                <th><?php esc_html_e( 'Country code', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="text" name="naws_settings[forecast_country]"
                                           value="<?php echo esc_attr( $options['forecast_country'] ?? '' ); ?>"
                                           class="small-text naws-input-country" maxlength="2"
                                           placeholder="DE">
                                    <span class="description"><?php esc_html_e( 'ISO 3166 code (e.g. DE, AT, CH, US, GB)', 'xtx-integration-for-netatmo' ); ?></span>
                                </td>
                            </tr>
                        </table>

                        <?php
                        // Where the forecast is actually being fetched for — the one
                        // answer that tells you whether the settings above worked.
                        $fc_location = NAWS_Forecast::resolve_location();
                        if ( ! isset( $fc_location['error'] ) ) : ?>
                            <p class="naws-result naws-result--ok">
                                <strong>📍 <?php esc_html_e( 'Resolved location', 'xtx-integration-for-netatmo' ); ?>:</strong>
                                <?php echo esc_html( $fc_location['name'] ?? '' ); ?>
                                <small>(<?php echo esc_html( $fc_location['lat'] . '°, ' . $fc_location['lon'] . '°' ); ?>)</small>
                            </p>
                        <?php else : ?>
                            <p class="naws-result naws-result--error">
                                <strong>⚠️</strong> <?php echo esc_html( $fc_location['error'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php esc_html_e( 'Weather icon', 'xtx-integration-for-netatmo' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <p class="description naws-panel-intro"><?php esc_html_e( 'The icon shows current weather. Station readings take precedence over the forecast; the forecast only fills in where the station cannot measure — cloud cover, thunderstorms, and precipitation when no rain gauge is present.', 'xtx-integration-for-netatmo' ); ?></p>

                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php esc_html_e( 'Above the dashboard', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <?php // Hidden 0 first: an unchecked box submits nothing, and the
                                          // sanitize callback merges, so without this the key would look
                                          // "not managed by this form" and could never be switched off. ?>
                                    <input type="hidden" name="naws_settings[wx_show_on_dashboard]" value="0">
                                    <label>
                                        <input type="checkbox" name="naws_settings[wx_show_on_dashboard]" value="1"
                                            <?php checked( $options['wx_show_on_dashboard'] ?? '1', '1' ); ?>>
                                        <?php esc_html_e( 'Show the icon above the live dashboard. The [naws_weather_icon] shortcode is unaffected by this.', 'xtx-integration-for-netatmo' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Heavy rain from', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="number" step="0.1" min="0.1" max="50"
                                           name="naws_settings[wx_rain_heavy]"
                                           value="<?php echo esc_attr( $options['wx_rain_heavy'] ?? 4.0 ); ?>" class="small-text"> mm/h
                                    <p class="description"><?php esc_html_e( 'At or above this rain rate the icon shows heavy rain instead of rain.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Snow below', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="number" step="0.1" min="-20" max="5"
                                           name="naws_settings[wx_snow_tw]"
                                           value="<?php echo esc_attr( $options['wx_snow_tw'] ?? 1.0 ); ?>" class="small-text"> °C
                                    <p class="description"><?php esc_html_e( 'Wet-bulb temperature, not air temperature. It decides whether precipitation arrives as rain or snow: in dry air it still snows at 3–4 °C air temperature, while in saturated air it already rains at 1 °C. Computed from outdoor temperature and outdoor humidity.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Fog from humidity', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="number" step="0.1" min="80" max="100"
                                           name="naws_settings[wx_fog_rh]"
                                           value="<?php echo esc_attr( $options['wx_fog_rh'] ?? 97.0 ); ?>" class="small-text"> %
                                    &nbsp;<?php esc_html_e( 'and dew-point spread up to', 'xtx-integration-for-netatmo' ); ?>&nbsp;
                                    <input type="number" step="0.1" min="0.1" max="5"
                                           name="naws_settings[wx_fog_spread]"
                                           value="<?php echo esc_attr( $options['wx_fog_spread'] ?? 0.5 ); ?>" class="small-text"> K
                                    <p class="description"><?php esc_html_e( 'Both conditions must hold. The dew-point spread is the difference between air temperature and dew point — the smaller it is, the closer the air is to saturation.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Storm from', 'xtx-integration-for-netatmo' ); ?></th>
                                <td>
                                    <input type="number" step="1" min="20" max="200"
                                           name="naws_settings[wx_storm_wind]"
                                           value="<?php echo esc_attr( $options['wx_storm_wind'] ?? 75.0 ); ?>" class="small-text"> km/h
                                    <p class="description"><?php esc_html_e( 'Measured on the wind gauge gust peak. Without a wind module the rule is skipped, with no substitute.', 'xtx-integration-for-netatmo' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div><!-- /right column -->
        </div>

        <?php submit_button( __( 'Save Settings', 'xtx-integration-for-netatmo' ) ); ?>
    </form>

    <?php
    wp_add_inline_script( 'naws-admin', <<<'EOJS'
(function(){
    var radios = document.querySelectorAll('input[name="naws_settings[forecast_location]"]');
    var rows   = [ document.getElementById('naws-forecast-manual-row'),
                   document.getElementById('naws-forecast-country-row') ];
    function toggle(){
        var picked = document.querySelector('input[name="naws_settings[forecast_location]"]:checked');
        var manual = picked && picked.value === 'manual';
        rows.forEach(function(r){ if(r) r.classList.toggle('naws-row-dimmed', !manual); });
    }
    radios.forEach(function(r){ r.addEventListener('change', toggle); });
})();
EOJS
    );
    ?>
</div>
