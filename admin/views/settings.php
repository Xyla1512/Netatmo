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
        <?php naws_e( 'settings_title' ); ?>
    </h1>

    <?php // A save that could not encrypt a credential redirects with this
          // flag instead of `updated`: the value was rejected, so saying
          // "saved" would be the one wrong answer here. ?>
    <?php if ( isset( $_GET['naws_crypto_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no data processed ?>
        <div class="notice notice-error"><p><?php naws_e( 'crypto_save_failed' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['updated'] ) && ! isset( $_GET['naws_crypto_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flags, no data processed ?>
        <div class="notice notice-success is-dismissible"><p><?php naws_e( 'settings_saved' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no data processed ?>
        <div class="notice notice-info is-dismissible"><p><?php naws_e( 'disconnected_msg' ); ?></p></div>
    <?php endif; ?>

    <?php
    $naws_crypto = NAWS_Crypto::health();
    if ( $naws_crypto['status'] !== 'ok' ) : ?>
        <div class="notice notice-warning">
            <?php foreach ( $naws_crypto['issues'] as $naws_issue ) : ?>
                <p>
                    <?php echo esc_html( naws__( 'crypto_' . $naws_issue ) ); ?>
                    <?php if ( $naws_issue === 'weak_key' ) : ?>
                        <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank" rel="noopener noreferrer"><?php naws_e( 'crypto_salt_link' ); ?></a>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( get_option( 'naws_crypto_write_failed' ) ) : ?>
        <div class="notice notice-error">
            <p><strong><?php naws_e( 'crypto_connect_failed' ); ?></strong></p>
        </div>
    <?php endif; ?>

    <?php if ( get_option( 'naws_auth_required' ) ) : ?>
        <div class="notice notice-error">
            <p><strong>🔴 <?php naws_e( 'token_revoked' ); ?></strong><br>
            <?php naws_e( 'token_revoked_desc' ); ?></p>
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
            <p><strong>⚠️ <?php naws_e( 'no_refresh_token' ); ?></strong><br>
            <?php naws_e( 'no_refresh_token_desc' ); ?></p>
        </div>
    <?php elseif ( empty( $access_token ) && empty( $refresh_token ) ) : ?>
        <div class="notice notice-warning">
            <p><strong>🔌 <?php naws_e( 'not_connected_warn' ); ?></strong>
            <?php naws_e( 'not_connected_desc' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $oauth_debug && is_array( $oauth_debug ) ) : ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>🔍 <?php naws_e( 'oauth_debug' ); ?>:</strong>
            HTTP <?php echo esc_html( $oauth_debug['http_code'] ?? '?' ); ?> –
            <code><?php echo esc_html( wp_json_encode( $oauth_debug['body'] ?? [] ) ); ?></code></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $refresh_token ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ <strong><?php naws_e( 'token_ok' ); ?></strong>
            <?php if ( $token_expiry > time() ) : ?>
                <?php echo esc_html( naws__( 'token_valid_until', [ wp_date( 'Y-m-d H:i', $token_expiry ) ] ) ); ?>
            <?php else : ?>
                <?php naws_e( 'token_expired_auto' ); ?>
            <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php /* ── Connection: full width, its own form, its own button ────── */ ?>
    <div class="naws-admin-panel naws-settings-connection">
        <div class="naws-panel-header">
            <h2><?php naws_e( 'netatmo_api' ); ?></h2>
            <?php if ( $is_connected ) : ?>
                <span class="naws-badge naws-badge-success">✓ <?php naws_e( 'connected' ); ?></span>
            <?php else : ?>
                <span class="naws-badge naws-badge-error">✗ <?php naws_e( 'not_connected' ); ?></span>
            <?php endif; ?>
        </div>

        <div class="naws-panel-body">
            <div class="naws-info-box naws-info-box--flush">
                <p><?php naws_e( 'api_desc' ); ?>
                   <a href="https://dev.netatmo.com" target="_blank" rel="noopener noreferrer">dev.netatmo.com</a> <?php naws_e( 'api_desc2' ); ?></p>
                <p><strong><?php naws_e( 'redirect_uri' ); ?></strong><br>
                   <code><?php echo esc_html( admin_url( 'admin.php?page=naws-settings' ) ); ?></code></p>
            </div>

            <?php // The only place the decrypted secret is rendered. Keep it that way. ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'naws_save_settings' ); ?>
                <input type="hidden" name="action" value="naws_save_settings">

                <table class="form-table naws-form-table">
                    <tr>
                        <th><?php naws_e( 'client_id' ); ?></th>
                        <td>
                            <input type="text" name="naws_settings[client_id]" value="<?php echo esc_attr( $options['client_id'] ?? '' ); ?>"
                                   class="regular-text" placeholder="<?php echo esc_attr( naws__( 'client_id_placeholder' ) ); ?>" autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <th><?php naws_e( 'client_secret' ); ?></th>
                        <td>
                            <input type="password" name="naws_settings[client_secret]" value="<?php echo esc_attr( $options['client_secret'] ?? '' ); ?>"
                                   class="regular-text" id="naws-client-secret" autocomplete="off">
                            <button type="button" class="button" id="naws-toggle-secret"><?php naws_e( 'show' ); ?></button>
                        </td>
                    </tr>
                </table>

                <div class="naws-actions">
                    <button type="submit" class="button button-secondary"><?php naws_e( 'save_credentials' ); ?></button>
                </div>
            </form>

            <hr class="naws-rule">

            <h3><?php naws_e( 'connect_oauth' ); ?></h3>
            <p><?php naws_e( 'connect_oauth_desc' ); ?></p>

            <?php // A div, not a p: this row contains the disconnect <form>, and a <p>
                  // may only hold phrasing content — the parser would close it before
                  // the form and the flex row would fall apart. ?>
            <div class="naws-actions">
                <?php if ( ! empty( $options['client_id'] ) && ! empty( $options['client_secret'] ) ) : ?>
                    <a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary naws-btn-connect">
                        🔑 <?php naws_e( 'connect_netatmo' ); ?>
                    </a>
                <?php else : ?>
                    <span class="description"><?php naws_e( 'save_first' ); ?></span>
                <?php endif; ?>

                <?php if ( $is_connected ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="naws-inline-form">
                        <?php wp_nonce_field( 'naws_disconnect' ); ?>
                        <input type="hidden" name="action" value="naws_disconnect">
                        <button type="submit" class="button button-secondary"
                                onclick="return confirm('<?php echo esc_js( naws__( 'disconnect_confirm' ) ); ?>')">
                            🔌 <?php naws_e( 'disconnect' ); ?>
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
                    <div class="naws-panel-header"><h2><?php naws_e( 'settings_group_station' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php naws_e( 'language' ); ?></th>
                                <td>
                                    <select name="naws_settings[language]">
                                        <option value="auto" <?php selected( $options['language'] ?? 'auto', 'auto' ); ?>><?php naws_e( 'language_auto' ); ?></option>
                                        <?php foreach ( NAWS_Lang::get_available_languages() as $code => $native_name ) : ?>
                                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $options['language'] ?? 'auto', $code ); ?>><?php echo esc_html( $native_name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php naws_e( 'language_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'station_name_label' ); ?></th>
                                <td>
                                    <input type="text" name="naws_settings[station_name]"
                                           value="<?php echo esc_attr( $options['station_name'] ?? '' ); ?>"
                                           placeholder="<?php echo esc_attr( naws__( 'station_name_placeholder' ) ); ?>"
                                           class="regular-text">
                                    <p class="description"><?php naws_e( 'station_name_desc' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php naws_e( 'settings_group_operation' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php naws_e( 'cron_interval' ); ?></th>
                                <td>
                                    <?php $naws_interval = NAWS_Cron::normalise_interval( $options['cron_interval'] ?? NAWS_Cron::DEFAULT_INTERVAL ); ?>
                                    <select name="naws_settings[cron_interval]">
                                        <?php foreach ( NAWS_Cron::INTERVALS as $naws_opt ) : ?>
                                            <option value="<?php echo esc_attr( $naws_opt ); ?>" <?php selected( $naws_interval, $naws_opt ); ?>>
                                                <?php echo esc_html( sprintf( naws__( 'cron_interval_minutes' ), $naws_opt ) ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php naws_e( 'cron_interval_desc' ); ?></p>
                                    <p class="description"><?php naws_e( 'cron_backoff_desc' ); ?></p>
                                    <?php // Raw output: the text carries <code> markup. See NAWS_Lang::r(). ?>
                                    <p class="description"><?php NAWS_Lang::r( 'cron_wpcron_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'night_mode' ); ?></th>
                                <td>
                                    <?php // Hidden 0 first – see the note on the weather-icon checkbox. ?>
                                    <input type="hidden" name="naws_settings[night_mode]" value="0">
                                    <label>
                                        <input type="checkbox" name="naws_settings[night_mode]" value="1"
                                            <?php checked( ! empty( $options['night_mode'] ) ); ?>>
                                        <?php naws_e( 'night_mode_label' ); ?>
                                    </label>
                                    <p class="description"><?php naws_e( 'night_mode_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'deg_limits' ); ?></th>
                                <td>
                                    <p>
                                        <label><?php naws_e( 'heating_limit' ); ?><br>
                                        <input type="number" step="0.5" min="-10" max="30" name="naws_settings[heating_limit]"
                                            value="<?php echo esc_attr( $options['heating_limit'] ?? 15 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php naws_e( 'heating_limit_desc' ); ?></p>
                                    <p>
                                        <label><?php naws_e( 'room_temp' ); ?><br>
                                        <input type="number" step="0.5" min="10" max="30" name="naws_settings[room_temp]"
                                            value="<?php echo esc_attr( $options['room_temp'] ?? 20 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php naws_e( 'room_temp_desc' ); ?></p>
                                    <p>
                                        <label><?php naws_e( 'cooling_limit' ); ?><br>
                                        <input type="number" step="0.5" min="0" max="40" name="naws_settings[cooling_limit]"
                                            value="<?php echo esc_attr( $options['cooling_limit'] ?? 18 ); ?>"></label>
                                    </p>
                                    <p class="description"><?php naws_e( 'cooling_limit_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'data_retention' ); ?></th>
                                <td>
                                    <p><span class="naws-text-ok"><?php naws_e( 'data_kept' ); ?></span><br>
                                    <span class="description"><?php naws_e( 'data_kept_desc' ); ?></span></p>
                                    <details class="naws-danger-details">
                                        <summary><?php naws_e( 'manual_purge' ); ?></summary>
                                        <div class="naws-danger-body">
                                            <label><?php naws_e( 'purge_older_than' ); ?>
                                            <input type="number" id="naws-purge-days" value="365" min="30" class="small-text"> <?php naws_e( 'days' ); ?></label>
                                            <button type="button" id="naws-purge-btn" class="button naws-btn-danger"><?php naws_e( 'purge_now' ); ?></button>
                                            <span id="naws-purge-result"></span>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="naws-admin-panel">
                    <div class="naws-panel-header"><h2><?php naws_e( 'units' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php naws_e( 'unit_temperature' ); ?></th>
                                <td>
                                    <select name="naws_settings[temperature_unit]">
                                        <option value="C" <?php selected( $options['temperature_unit'] ?? 'C', 'C' ); ?>>°C – Celsius</option>
                                        <option value="F" <?php selected( $options['temperature_unit'] ?? 'C', 'F' ); ?>>°F – Fahrenheit</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'unit_wind' ); ?></th>
                                <td>
                                    <select name="naws_settings[wind_unit]">
                                        <option value="kmh" <?php selected( $options['wind_unit'] ?? 'kmh', 'kmh' ); ?>>km/h</option>
                                        <option value="ms"  <?php selected( $options['wind_unit'] ?? 'kmh', 'ms' ); ?>>m/s</option>
                                        <option value="mph" <?php selected( $options['wind_unit'] ?? 'kmh', 'mph' ); ?>>mph</option>
                                        <option value="kn"  <?php selected( $options['wind_unit'] ?? 'kmh', 'kn' ); ?>><?php naws_e( 'knots' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'unit_pressure' ); ?></th>
                                <td>
                                    <select name="naws_settings[pressure_unit]">
                                        <option value="mbar" <?php selected( $options['pressure_unit'] ?? 'mbar', 'mbar' ); ?>>mbar / hPa</option>
                                        <option value="inHg" <?php selected( $options['pressure_unit'] ?? 'mbar', 'inHg' ); ?>>inHg</option>
                                        <option value="mmHg" <?php selected( $options['pressure_unit'] ?? 'mbar', 'mmHg' ); ?>>mmHg</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'unit_rain' ); ?></th>
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
                    <div class="naws-panel-header"><h2><?php naws_e( 'forecast_settings_title' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <p class="description naws-panel-intro"><?php naws_e( 'forecast_settings_desc' ); ?></p>

                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php naws_e( 'forecast_provider_label' ); ?></th>
                                <td>
                                    <select name="naws_settings[forecast_provider]">
                                        <option value="open_meteo" <?php selected( $options['forecast_provider'] ?? 'open_meteo', 'open_meteo' ); ?>><?php naws_e( 'forecast_provider_open_meteo' ); ?></option>
                                        <option value="yr_no"      <?php selected( $options['forecast_provider'] ?? 'open_meteo', 'yr_no' ); ?>><?php naws_e( 'forecast_provider_yr_no' ); ?></option>
                                    </select>
                                    <p class="description"><?php naws_e( 'forecast_provider_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'forecast_days_label' ); ?></th>
                                <td>
                                    <select name="naws_settings[forecast_days]">
                                        <?php for ( $d = 1; $d <= 7; $d++ ) : ?>
                                            <option value="<?php echo intval( $d ); ?>" <?php selected( $options['forecast_days'] ?? 5, $d ); ?>>
                                                <?php echo intval( $d ); ?> <?php echo esc_html( $d === 1 ? naws__( 'forecast_day_singular' ) : naws__( 'forecast_day_plural' ) ); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description"><?php naws_e( 'forecast_days_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'forecast_location_label' ); ?></th>
                                <td>
                                    <div class="naws-radio-list">
                                        <label>
                                            <input type="radio" name="naws_settings[forecast_location]" value="auto"
                                                <?php checked( $options['forecast_location'] ?? 'auto', 'auto' ); ?>>
                                            <?php naws_e( 'forecast_location_auto' ); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="naws_settings[forecast_location]" value="manual"
                                                <?php checked( $options['forecast_location'] ?? 'auto', 'manual' ); ?>>
                                            <?php naws_e( 'forecast_location_manual' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php $naws_manual = ( $options['forecast_location'] ?? 'auto' ) === 'manual' ? '' : ' naws-row-dimmed'; ?>
                            <tr id="naws-forecast-manual-row" class="<?php echo esc_attr( ltrim( $naws_manual ) ); ?>">
                                <th><?php naws_e( 'forecast_city_label' ); ?></th>
                                <td>
                                    <input type="text" name="naws_settings[forecast_city]"
                                           value="<?php echo esc_attr( $options['forecast_city'] ?? '' ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( naws__( 'forecast_city_placeholder' ) ); ?>">
                                    <p class="description"><?php naws_e( 'forecast_city_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr id="naws-forecast-country-row" class="<?php echo esc_attr( ltrim( $naws_manual ) ); ?>">
                                <th><?php naws_e( 'forecast_country_label' ); ?></th>
                                <td>
                                    <input type="text" name="naws_settings[forecast_country]"
                                           value="<?php echo esc_attr( $options['forecast_country'] ?? '' ); ?>"
                                           class="small-text naws-input-country" maxlength="2"
                                           placeholder="DE">
                                    <span class="description"><?php naws_e( 'forecast_country_desc' ); ?></span>
                                </td>
                            </tr>
                        </table>

                        <?php
                        // Where the forecast is actually being fetched for — the one
                        // answer that tells you whether the settings above worked.
                        $fc_location = NAWS_Forecast::resolve_location();
                        if ( ! isset( $fc_location['error'] ) ) : ?>
                            <p class="naws-result naws-result--ok">
                                <strong>📍 <?php naws_e( 'forecast_resolved' ); ?>:</strong>
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
                    <div class="naws-panel-header"><h2><?php naws_e( 'wx_icon_heading' ); ?></h2></div>
                    <div class="naws-panel-body">
                        <p class="description naws-panel-intro"><?php naws_e( 'wx_icon_desc' ); ?></p>

                        <table class="form-table naws-form-table">
                            <tr>
                                <th><?php naws_e( 'wx_show_dashboard_label' ); ?></th>
                                <td>
                                    <?php // Hidden 0 first: an unchecked box submits nothing, and the
                                          // sanitize callback merges, so without this the key would look
                                          // "not managed by this form" and could never be switched off. ?>
                                    <input type="hidden" name="naws_settings[wx_show_on_dashboard]" value="0">
                                    <label>
                                        <input type="checkbox" name="naws_settings[wx_show_on_dashboard]" value="1"
                                            <?php checked( $options['wx_show_on_dashboard'] ?? '1', '1' ); ?>>
                                        <?php naws_e( 'wx_show_dashboard_desc' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'wx_rain_heavy_label' ); ?></th>
                                <td>
                                    <input type="number" step="0.1" min="0.1" max="50"
                                           name="naws_settings[wx_rain_heavy]"
                                           value="<?php echo esc_attr( $options['wx_rain_heavy'] ?? 4.0 ); ?>" class="small-text"> mm/h
                                    <p class="description"><?php naws_e( 'wx_rain_heavy_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'wx_snow_tw_label' ); ?></th>
                                <td>
                                    <input type="number" step="0.1" min="-20" max="5"
                                           name="naws_settings[wx_snow_tw]"
                                           value="<?php echo esc_attr( $options['wx_snow_tw'] ?? 1.0 ); ?>" class="small-text"> °C
                                    <p class="description"><?php naws_e( 'wx_snow_tw_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'wx_fog_rh_label' ); ?></th>
                                <td>
                                    <input type="number" step="0.1" min="80" max="100"
                                           name="naws_settings[wx_fog_rh]"
                                           value="<?php echo esc_attr( $options['wx_fog_rh'] ?? 97.0 ); ?>" class="small-text"> %
                                    &nbsp;<?php naws_e( 'wx_fog_spread_label' ); ?>&nbsp;
                                    <input type="number" step="0.1" min="0.1" max="5"
                                           name="naws_settings[wx_fog_spread]"
                                           value="<?php echo esc_attr( $options['wx_fog_spread'] ?? 0.5 ); ?>" class="small-text"> K
                                    <p class="description"><?php naws_e( 'wx_fog_desc' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php naws_e( 'wx_storm_wind_label' ); ?></th>
                                <td>
                                    <input type="number" step="1" min="20" max="200"
                                           name="naws_settings[wx_storm_wind]"
                                           value="<?php echo esc_attr( $options['wx_storm_wind'] ?? 75.0 ); ?>" class="small-text"> km/h
                                    <p class="description"><?php naws_e( 'wx_storm_wind_desc' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div><!-- /right column -->
        </div>

        <?php submit_button( naws__( 'save_settings' ) ); ?>
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
