<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap naws-admin-wrap">
    <h1 class="naws-admin-page-title">
        <span class="naws-title-icon">⚙️</span>
        <?php naws_e( 'settings_title' ); ?>
    </h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php naws_e( 'settings_saved' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['disconnected'] ) ) : ?>
        <div class="notice notice-info is-dismissible"><p><?php naws_e( 'disconnected_msg' ); ?></p></div>
    <?php endif; ?>

    <?php if ( get_option( 'naws_auth_required' ) ) : ?>
        <div class="notice notice-error">
            <p><strong>🔴 <?php naws_e( 'token_revoked' ); ?></strong><br>
            <?php naws_e( 'token_revoked_desc' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
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
            <code><?php echo esc_html( json_encode( $oauth_debug['body'] ?? [] ) ); ?></code></p>
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

    <div class="naws-settings-grid">

        <!-- OAuth Connection -->
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
            <div class="naws-info-box" style="margin:0 0 1rem;">
                <p><?php naws_e( 'api_desc' ); ?>
                   <a href="https://dev.netatmo.com" target="_blank">dev.netatmo.com</a> <?php naws_e( 'api_desc2' ); ?></p>
                <p><strong><?php naws_e( 'redirect_uri' ); ?></strong><br>
                   <code><?php echo esc_html( admin_url( 'admin.php?page=naws-settings' ) ); ?></code></p>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'naws_save_settings' ); ?>
                <input type="hidden" name="action" value="naws_save_settings">

                <table class="form-table naws-form-table">
                    <tr>
                        <th><?php naws_e( 'client_id' ); ?></th>
                        <td>
                            <input type="text" name="naws_settings[client_id]" value="<?php echo esc_attr( $options['client_id'] ?? '' ); ?>"
                                   class="regular-text" placeholder="Your Netatmo Client ID">
                        </td>
                    </tr>
                    <tr>
                        <th><?php naws_e( 'client_secret' ); ?></th>
                        <td>
                            <input type="password" name="naws_settings[client_secret]" value="<?php echo esc_attr( $options['client_secret'] ?? '' ); ?>"
                                   class="regular-text" id="naws-client-secret">
                            <button type="button" class="button" id="naws-toggle-secret"><?php naws_e( 'show' ); ?></button>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-secondary"><?php naws_e( 'save_credentials' ); ?></button>
                </p>
            </form>

            <hr style="border-color: var(--naws-border, #2d3748); margin:1.5rem 0;">

            <h3><?php naws_e( 'connect_oauth' ); ?></h3>
            <p><?php naws_e( 'connect_oauth_desc' ); ?></p>

            <?php if ( ! empty( $options['client_id'] ) && ! empty( $options['client_secret'] ) ) : ?>
                <a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary naws-btn-connect">
                    🔑 <?php naws_e( 'connect_netatmo' ); ?>
                </a>
            <?php else : ?>
                <p class="description"><?php naws_e( 'save_first' ); ?></p>
            <?php endif; ?>

            <?php if ( $is_connected ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-left:0.5rem;">
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

        <!-- General Settings -->
        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2><?php naws_e( 'general_settings' ); ?></h2></div>

            <div class="naws-panel-body">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'naws_save_settings' ); ?>
                <input type="hidden" name="action" value="naws_save_settings">

                <!-- Preserve credentials -->
                <input type="hidden" name="naws_settings[client_id]"     value="<?php echo esc_attr( $options['client_id'] ?? '' ); ?>">
                <input type="hidden" name="naws_settings[client_secret]" value="<?php echo esc_attr( $options['client_secret'] ?? '' ); ?>">

                <!-- Preserve forecast settings -->
                <input type="hidden" name="naws_settings[station_name]"       value="<?php echo esc_attr( $options['station_name'] ?? '' ); ?>">
                <input type="hidden" name="naws_settings[forecast_provider]"  value="<?php echo esc_attr( $options['forecast_provider'] ?? 'open_meteo' ); ?>">
                <input type="hidden" name="naws_settings[forecast_location]" value="<?php echo esc_attr( $options['forecast_location'] ?? 'auto' ); ?>">
                <input type="hidden" name="naws_settings[forecast_days]"     value="<?php echo esc_attr( $options['forecast_days'] ?? 5 ); ?>">
                <input type="hidden" name="naws_settings[forecast_city]"     value="<?php echo esc_attr( $options['forecast_city'] ?? '' ); ?>">
                <input type="hidden" name="naws_settings[forecast_country]"  value="<?php echo esc_attr( $options['forecast_country'] ?? '' ); ?>">

                <h3><?php naws_e( 'language' ); ?></h3>
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

                <h3><?php naws_e( 'general_settings' ); ?></h3>
                <table class="form-table naws-form-table">
                    <tr>
                        <th><?php naws_e( 'cron_interval' ); ?></th>
                        <td>
                            <input type="number" name="naws_settings[cron_interval]" value="<?php echo esc_attr( $options['cron_interval'] ?? 10 ); ?>"
                                   min="5" max="1440" class="small-text">
                            <p class="description"><?php naws_e( 'cron_interval_desc' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php naws_e( 'data_retention' ); ?></th>
                        <td>
                            <p><span style="color:#10b981; font-weight:600;"><?php naws_e( 'data_kept' ); ?></span><br>
                            <span class="description"><?php naws_e( 'data_kept_desc' ); ?></span></p>
                            <details>
                            <summary style="cursor:pointer; color:#ef4444;"><?php naws_e( 'manual_purge' ); ?></summary>
                            <div style="margin-top:0.75rem;">
                                <label><?php naws_e( 'purge_older_than' ); ?>
                                <input type="number" id="naws-purge-days" value="365" min="30" class="small-text"> <?php naws_e( 'days' ); ?></label>
                                <button type="button" id="naws-purge-btn" class="button" style="margin-left:0.5rem; color:#ef4444; border-color:#ef4444;"><?php naws_e( 'purge_now' ); ?></button>
                                <span id="naws-purge-result" style="margin-left:0.5rem;"></span>
                            </div>
                            </details>
                        </td>
                    </tr>
                </table>

                <h3><?php naws_e( 'units' ); ?></h3>
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

                <?php submit_button( naws__( 'save_settings' ) ); ?>
            </form>
            </div>
        </div>

        <!-- Forecast Settings -->
        <div class="naws-admin-panel">
            <div class="naws-panel-header"><h2>🌤️ <?php naws_e( 'forecast_settings_title' ); ?></h2></div>

            <div class="naws-panel-body">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'naws_save_settings' ); ?>
                <input type="hidden" name="action" value="naws_save_settings">

                <!-- Preserve all other settings -->
                <input type="hidden" name="naws_settings[client_id]"       value="<?php echo esc_attr( $options['client_id'] ?? '' ); ?>">
                <input type="hidden" name="naws_settings[client_secret]"   value="<?php echo esc_attr( $options['client_secret'] ?? '' ); ?>">
                <input type="hidden" name="naws_settings[cron_interval]"   value="<?php echo esc_attr( $options['cron_interval'] ?? 10 ); ?>">
                <input type="hidden" name="naws_settings[language]"        value="<?php echo esc_attr( $options['language'] ?? 'auto' ); ?>">
                <input type="hidden" name="naws_settings[temperature_unit]" value="<?php echo esc_attr( $options['temperature_unit'] ?? 'C' ); ?>">
                <input type="hidden" name="naws_settings[wind_unit]"       value="<?php echo esc_attr( $options['wind_unit'] ?? 'kmh' ); ?>">
                <input type="hidden" name="naws_settings[pressure_unit]"   value="<?php echo esc_attr( $options['pressure_unit'] ?? 'mbar' ); ?>">
                <input type="hidden" name="naws_settings[rain_unit]"       value="<?php echo esc_attr( $options['rain_unit'] ?? 'mm' ); ?>">
                <input type="hidden" name="naws_settings[chart_theme]"     value="<?php echo esc_attr( $options['chart_theme'] ?? 'light' ); ?>">
                <input type="hidden" name="naws_settings[station_name]"    value="<?php echo esc_attr( $options['station_name'] ?? '' ); ?>">
                <input type="hidden" name="naws_settings[forecast_provider]" value="<?php echo esc_attr( $options['forecast_provider'] ?? 'open_meteo' ); ?>">

                <p class="description" style="margin-bottom:1rem;"><?php naws_e( 'forecast_settings_desc' ); ?></p>

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
                            <label style="display:block;margin-bottom:0.5rem;">
                                <input type="radio" name="naws_settings[forecast_location]" value="auto"
                                    <?php checked( $options['forecast_location'] ?? 'auto', 'auto' ); ?>>
                                <?php naws_e( 'forecast_location_auto' ); ?>
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="naws_settings[forecast_location]" value="manual"
                                    <?php checked( $options['forecast_location'] ?? 'auto', 'manual' ); ?>>
                                <?php naws_e( 'forecast_location_manual' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr id="naws-forecast-manual-row" style="<?php echo ( $options['forecast_location'] ?? 'auto' ) !== 'manual' ? 'opacity:0.5' : ''; ?>">
                        <th><?php naws_e( 'forecast_city_label' ); ?></th>
                        <td>
                            <input type="text" name="naws_settings[forecast_city]"
                                   value="<?php echo esc_attr( $options['forecast_city'] ?? '' ); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr( naws__( 'forecast_city_placeholder' ) ); ?>">
                            <p class="description"><?php naws_e( 'forecast_city_desc' ); ?></p>
                        </td>
                    </tr>
                    <tr id="naws-forecast-country-row" style="<?php echo ( $options['forecast_location'] ?? 'auto' ) !== 'manual' ? 'opacity:0.5' : ''; ?>">
                        <th><?php naws_e( 'forecast_country_label' ); ?></th>
                        <td>
                            <input type="text" name="naws_settings[forecast_country]"
                                   value="<?php echo esc_attr( $options['forecast_country'] ?? '' ); ?>"
                                   class="small-text" maxlength="2" style="width:60px;text-transform:uppercase"
                                   placeholder="DE">
                            <span class="description"><?php naws_e( 'forecast_country_desc' ); ?></span>
                        </td>
                    </tr>
                </table>

                <?php
                // Show current resolved location as info
                $fc_location = NAWS_Forecast::resolve_location();
                if ( ! isset( $fc_location['error'] ) ) : ?>
                    <div class="naws-info-box" style="margin:0.5rem 0 1rem;padding:0.6rem 1rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                        <strong>📍 <?php naws_e( 'forecast_resolved' ); ?>:</strong>
                        <?php echo esc_html( $fc_location['name'] ?? '' ); ?>
                        <small style="color:#64748b">(<?php echo esc_html( $fc_location['lat'] . '°, ' . $fc_location['lon'] . '°' ); ?>)</small>
                    </div>
                <?php elseif ( isset( $fc_location['error'] ) ) : ?>
                    <div class="naws-info-box" style="margin:0.5rem 0 1rem;padding:0.6rem 1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
                        <strong>⚠️</strong> <?php echo esc_html( $fc_location['error'] ); ?>
                    </div>
                <?php endif; ?>

                <?php submit_button( naws__( 'save_settings' ) ); ?>
            </form>

            <script>
            (function(){
                var radios = document.querySelectorAll('input[name="naws_settings[forecast_location]"]');
                var manual = document.getElementById('naws-forecast-manual-row');
                var country = document.getElementById('naws-forecast-country-row');
                function toggle(){
                    var isManual = document.querySelector('input[name="naws_settings[forecast_location]"]:checked').value === 'manual';
                    if(manual) manual.style.opacity = isManual ? '1' : '0.5';
                    if(country) country.style.opacity = isManual ? '1' : '0.5';
                }
                radios.forEach(function(r){ r.addEventListener('change', toggle); });
            })();
            </script>
            </div>
        </div>
    </div>
</div>
