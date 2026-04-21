<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Handle form actions ─────────────────────────────────────────────────────
$cfg     = NAWS_Rest_API::get_config();
$message = '';
$msg_type = 'updated';

if ( isset( $_POST['naws_rest_action'] ) && check_admin_referer( 'naws_rest_api_settings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by check_admin_referer
    $action = sanitize_key( wp_unslash( $_POST['naws_rest_action']  ) );

    if ( $action === 'save_settings' ) {
        $cfg['enabled']    = ! empty( $_POST['rest_enabled'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- boolean check
        $cfg['rate_limit'] = max( 1, min( 600, intval( $_POST['rate_limit'] ?? 60 ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- cast to int
        NAWS_Rest_API::save_config( $cfg );
        $message = naws__( 'rest_saved' );
    }

    if ( $action === 'generate_key' ) {
        $cfg['api_key'] = NAWS_Rest_API::generate_api_key();
        NAWS_Rest_API::save_config( $cfg );
        $message = naws__( 'rest_key_generated' );
    }

    if ( $action === 'revoke_key' ) {
        $cfg['api_key'] = '';
        NAWS_Rest_API::save_config( $cfg );
        $message  = naws__( 'rest_key_revoked' );
        $msg_type = 'notice-warning';
    }
}

$api_key    = NAWS_Rest_API::get_api_key();
$is_enabled = ! empty( $cfg['enabled'] );
$rate_limit = (int) ( $cfg['rate_limit'] ?? 60 );
$base_url   = rest_url( 'naws/v1' );
?>
<?php // Styles moved to assets/css/admin.css ?>

<div class="wrap naws-admin-wrap naws-api-wrap">
<h1 class="naws-admin-page-title"><span class="naws-title-icon">🔌</span> <?php naws_e( 'rest_page_title' ); ?></h1>

<?php if ( $message ): ?>
<div class="notice <?php echo esc_attr( $msg_type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
<?php endif; ?>

<!-- ━━ Configuration ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="naws-api-panel">
<h2>⚙️ <?php naws_e( 'rest_config_title' ); ?></h2>

<form method="post">
<?php wp_nonce_field( 'naws_rest_api_settings' ); ?>
<input type="hidden" name="naws_rest_action" value="save_settings">

<table class="form-table">
<tr>
    <th scope="row"><?php naws_e( 'rest_status' ); ?></th>
    <td>
        <label>
            <input type="checkbox" name="rest_enabled" value="1" <?php checked( $is_enabled ); ?>>
            <?php naws_e( 'rest_enable_label' ); ?>
        </label>
        <span class="naws-status <?php echo $is_enabled ? 'naws-status-on' : 'naws-status-off'; ?>" style="margin-left:12px">
            <span class="naws-dot <?php echo $is_enabled ? 'naws-dot-on' : 'naws-dot-off'; ?>"></span>
            <?php echo esc_html( $is_enabled ? naws__( 'rest_active' ) : naws__( 'rest_inactive' ) ); ?>
        </span>
    </td>
</tr>
<tr>
    <th scope="row"><?php naws_e( 'rest_rate_limit' ); ?></th>
    <td>
        <input type="number" name="rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" min="1" max="600" style="width:80px">
        <span class="description"><?php naws_e( 'rest_rate_limit_desc' ); ?></span>
    </td>
</tr>
</table>

<?php submit_button( naws__( 'rest_save' ), 'primary', 'submit', false ); ?>
</form>

<hr style="margin:20px 0;border-color:#e2e8f0">

<h3>🔑 API-Key</h3>

<?php if ( $api_key ): ?>
<div class="naws-key-box">
    <input type="text" value="<?php echo esc_attr( $api_key ); ?>" readonly id="naws-api-key-display">
    <button type="button" class="button" onclick="nawsCopyKey()"><?php naws_e( 'rest_copy' ); ?></button>
</div>
<p class="naws-hint">⚠️ <?php naws_e( 'rest_key_warning' ); ?></p>

<div style="display:flex;gap:8px;margin-top:12px">
    <form method="post" style="margin:0">
        <?php wp_nonce_field( 'naws_rest_api_settings' ); ?>
        <input type="hidden" name="naws_rest_action" value="generate_key">
        <button type="submit" class="button" onclick="return confirm('<?php echo esc_js( naws__( 'rest_regenerate_confirm' ) ); ?>')">
            🔄 <?php naws_e( 'rest_regenerate' ); ?>
        </button>
    </form>
    <form method="post" style="margin:0">
        <?php wp_nonce_field( 'naws_rest_api_settings' ); ?>
        <input type="hidden" name="naws_rest_action" value="revoke_key">
        <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( naws__( 'rest_revoke_confirm' ) ); ?>')">
            🗑️ <?php naws_e( 'rest_revoke' ); ?>
        </button>
    </form>
</div>

<?php else: ?>
<p class="naws-hint"><?php naws_e( 'rest_no_key' ); ?></p>
<form method="post" style="margin:8px 0 0">
    <?php wp_nonce_field( 'naws_rest_api_settings' ); ?>
    <input type="hidden" name="naws_rest_action" value="generate_key">
    <button type="submit" class="button button-primary">🔑 <?php naws_e( 'rest_generate' ); ?></button>
</form>
<?php endif; ?>

</div><!-- /config panel -->


<!-- ━━ Base URL ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="naws-api-panel">
<h2>🌐 Base-URL</h2>
<div class="naws-code-block" id="naws-base-url"><?php echo esc_html( $base_url ); ?></div>
<p class="naws-hint"><?php naws_e( 'rest_base_url_hint' ); ?></p>
</div>


<!-- ━━ Authentication ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="naws-api-panel">
<h2>🔐 <?php naws_e( 'rest_auth_title' ); ?></h2>
<p style="color:#475569;font-size:13px;margin:0 0 12px"><?php naws_e( 'rest_auth_intro' ); ?></p>

<h3>Option A: HTTP-Header (<?php naws_e( 'rest_recommended' ); ?>)</h3>
<div class="naws-code-block">X-NAWS-Key: <?php echo esc_html( $api_key ?: 'naws_xxxxxxxxxxxx' ); ?></div>

<h3>Option B: Query-Parameter</h3>
<div class="naws-code-block"><?php echo esc_html( $base_url ); ?>/current?api_key=<?php echo esc_html( $api_key ?: 'naws_xxxxxxxxxxxx' ); ?></div>

<p class="naws-hint">💡 <?php naws_e( 'rest_auth_header_hint' ); ?></p>
</div>


<!-- ━━ Endpoint Reference ━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="naws-api-panel">
<h2>📖 <?php naws_e( 'rest_endpoints_title' ); ?></h2>

<div class="naws-tab-bar">
    <button class="naws-tab active" data-tab="ep-overview">Übersicht</button>
    <button class="naws-tab" data-tab="ep-station">Station</button>
    <button class="naws-tab" data-tab="ep-modules">Module</button>
    <button class="naws-tab" data-tab="ep-current">Aktuell</button>
    <button class="naws-tab" data-tab="ep-readings">Messwerte</button>
    <button class="naws-tab" data-tab="ep-daily">Tagesdaten</button>
</div>

<!-- Tab: Overview -->
<div class="naws-tab-content active" id="ep-overview">
<table class="naws-ep-table">
<thead><tr><th style="width:80px">Methode</th><th>Endpunkt</th><th>Beschreibung</th></tr></thead>
<tbody>
<tr><td><span class="naws-method">GET</span></td><td><code>/station</code></td><td><?php naws_e( 'rest_ep_station_desc' ); ?></td></tr>
<tr><td><span class="naws-method">GET</span></td><td><code>/modules</code></td><td><?php naws_e( 'rest_ep_modules_desc' ); ?></td></tr>
<tr><td><span class="naws-method">GET</span></td><td><code>/current</code></td><td><?php naws_e( 'rest_ep_current_desc' ); ?></td></tr>
<tr><td><span class="naws-method">GET</span></td><td><code>/readings</code></td><td><?php naws_e( 'rest_ep_readings_desc' ); ?></td></tr>
<tr><td><span class="naws-method">GET</span></td><td><code>/daily</code></td><td><?php naws_e( 'rest_ep_daily_desc' ); ?></td></tr>
</tbody>
</table>
<p class="naws-hint"><?php naws_e( 'rest_all_readonly' ); ?></p>
</div>

<!-- Tab: Station -->
<div class="naws-tab-content" id="ep-station">
<h3><span class="naws-method">GET</span> <code>/station</code></h3>
<p style="color:#475569;font-size:13px"><?php naws_e( 'rest_ep_station_detail' ); ?></p>
<p style="color:#64748b;font-size:12.5px"><strong>Parameter:</strong> —</p>
<h3>Beispiel-Antwort</h3>
<div class="naws-code-block">{
  "<span class="s">station_id</span>":   "<span class="n">70:ee:50:xx:xx:xx</span>",
  "<span class="s">latitude</span>":     <span class="n">51.34</span>,
  "<span class="s">longitude</span>":    <span class="n">12.37</span>,
  "<span class="s">altitude</span>":     <span class="n">120</span>,
  "<span class="s">timezone</span>":     "<span class="n">Europe/Berlin</span>",
  "<span class="s">modules</span>":      <span class="n">4</span>,
  "<span class="s">units</span>": {
    "<span class="s">temperature</span>": "<span class="n">°C</span>",
    "<span class="s">rain</span>":        "<span class="n">mm</span>",
    "<span class="s">wind</span>":        "<span class="n">km/h</span>",
    "<span class="s">pressure</span>":    "<span class="n">hPa</span>"
  },
  "<span class="s">last_sync</span>":    <span class="n">1741420800</span>
}</div>
</div>

<!-- Tab: Modules -->
<div class="naws-tab-content" id="ep-modules">
<h3><span class="naws-method">GET</span> <code>/modules</code></h3>
<p style="color:#475569;font-size:13px"><?php naws_e( 'rest_ep_modules_detail' ); ?></p>
<h3>Beispiel-Antwort</h3>
<div class="naws-code-block">{
  "<span class="s">modules</span>": [
    {
      "<span class="s">module_id</span>":   "<span class="n">02:00:00:xx:xx:xx</span>",
      "<span class="s">module_name</span>": "<span class="n">Außen</span>",
      "<span class="s">module_type</span>": "<span class="n">NAModule1</span>",
      "<span class="s">data_types</span>":  ["<span class="n">Temperature</span>", "<span class="n">Humidity</span>"],
      "<span class="s">last_seen</span>":   <span class="n">1741420500</span>,
      "<span class="s">firmware</span>":    <span class="n">50</span>,
      "<span class="s">battery_vp</span>":  <span class="n">5432</span>
    }
  ]
}</div>
<h3>Modul-Typen</h3>
<table class="naws-ep-table">
<thead><tr><th>Typ</th><th>Beschreibung</th><th>Messwerte</th></tr></thead>
<tbody>
<tr><td><code>NAMain</code></td><td>Basisstation (Innen)</td><td>Temperature, Humidity, CO2, Noise, Pressure</td></tr>
<tr><td><code>NAModule1</code></td><td>Außenmodul</td><td>Temperature, Humidity</td></tr>
<tr><td><code>NAModule2</code></td><td>Windmesser</td><td>WindStrength, WindAngle, GustStrength, GustAngle</td></tr>
<tr><td><code>NAModule3</code></td><td>Regenmesser</td><td>Rain, sum_rain_1, sum_rain_24</td></tr>
<tr><td><code>NAModule4</code></td><td>Zusatzmodul (Innen)</td><td>Temperature, Humidity, CO2</td></tr>
</tbody>
</table>
</div>

<!-- Tab: Current -->
<div class="naws-tab-content" id="ep-current">
<h3><span class="naws-method">GET</span> <code>/current</code></h3>
<p style="color:#475569;font-size:13px"><?php naws_e( 'rest_ep_current_detail' ); ?></p>

<table class="naws-ep-table">
<thead><tr><th>Parameter</th><th>Typ</th><th>Beschreibung</th></tr></thead>
<tbody>
<tr><td><span class="naws-param-tag naws-opt-tag">module_id</span></td><td>string</td><td><?php naws_e( 'rest_param_module_id' ); ?></td></tr>
</tbody>
</table>

<h3>Beispiel</h3>
<div class="naws-code-block"><span class="k">GET</span> <?php echo esc_html( $base_url ); ?>/current</div>
<div class="naws-code-block">{
  "<span class="s">count</span>": <span class="n">12</span>,
  "<span class="s">readings</span>": [
    {
      "<span class="s">module_id</span>":       "<span class="n">02:00:00:xx:xx:xx</span>",
      "<span class="s">module_name</span>":     "<span class="n">Außen</span>",
      "<span class="s">module_type</span>":     "<span class="n">NAModule1</span>",
      "<span class="s">parameter</span>":       "<span class="n">Temperature</span>",
      "<span class="s">value</span>":           <span class="n">18.5</span>,
      "<span class="s">raw_value</span>":       <span class="n">18.5</span>,
      "<span class="s">unit</span>":            "<span class="n">°C</span>",
      "<span class="s">recorded_at</span>":     <span class="n">1741420500</span>,
      "<span class="s">recorded_at_iso</span>": "<span class="n">2025-03-08T10:15:00+00:00</span>"
    }
  ]
}</div>
</div>

<!-- Tab: Readings -->
<div class="naws-tab-content" id="ep-readings">
<h3><span class="naws-method">GET</span> <code>/readings</code></h3>
<p style="color:#475569;font-size:13px"><?php naws_e( 'rest_ep_readings_detail' ); ?></p>

<table class="naws-ep-table">
<thead><tr><th>Parameter</th><th>Typ</th><th>Standard</th><th>Beschreibung</th></tr></thead>
<tbody>
<tr><td><span class="naws-param-tag naws-opt-tag">module_id</span></td><td>string</td><td>—</td><td><?php naws_e( 'rest_param_module_id' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">parameter</span></td><td>string</td><td>—</td><td><?php naws_e( 'rest_param_parameter' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">from</span></td><td>string</td><td>-24h</td><td><?php naws_e( 'rest_param_from' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">to</span></td><td>string</td><td>jetzt</td><td><?php naws_e( 'rest_param_to' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">group_by</span></td><td>string</td><td>raw</td><td><?php naws_e( 'rest_param_group_by' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">limit</span></td><td>integer</td><td>1000</td><td><?php naws_e( 'rest_param_limit' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">convert</span></td><td>boolean</td><td>true</td><td><?php naws_e( 'rest_param_convert' ); ?></td></tr>
</tbody>
</table>

<h3>Beispiele</h3>
<div class="naws-code-block"><span class="c">// Außentemperatur der letzten 7 Tage, stündlich gruppiert</span>
<span class="k">GET</span> /readings?parameter=Temperature&amp;from=2025-03-01T00:00:00Z&amp;group_by=hour

<span class="c">// Alle Sensordaten eines Moduls (letzte 24h)</span>
<span class="k">GET</span> /readings?module_id=02:00:00:xx:xx:xx

<span class="c">// Rohdaten in Originaleinheiten (ohne Umrechnung)</span>
<span class="k">GET</span> /readings?parameter=Temperature&amp;convert=false</div>

<h3>Gruppierte Antwort</h3>
<p class="naws-hint"><?php naws_e( 'rest_grouped_hint' ); ?></p>
<div class="naws-code-block">{
  "<span class="s">count</span>": <span class="n">168</span>,
  "<span class="s">from</span>": "<span class="n">2025-03-01T00:00:00+00:00</span>",
  "<span class="s">to</span>":   "<span class="n">2025-03-08T00:00:00+00:00</span>",
  "<span class="s">group_by</span>": "<span class="n">hour</span>",
  "<span class="s">readings</span>": [
    {
      "<span class="s">module_id</span>":       "<span class="n">02:00:00:xx:xx:xx</span>",
      "<span class="s">parameter</span>":       "<span class="n">Temperature</span>",
      "<span class="s">value</span>":           <span class="n">12.3</span>,
      "<span class="s">unit</span>":            "<span class="n">°C</span>",
      "<span class="s">recorded_at</span>":     <span class="n">1740787200</span>,
      "<span class="s">recorded_at_iso</span>": "<span class="n">2025-03-01T00:00:00+00:00</span>",
      "<span class="s">min_value</span>":       <span class="n">11.8</span>,
      "<span class="s">max_value</span>":       <span class="n">12.9</span>,
      "<span class="s">data_points</span>":     <span class="n">12</span>
    }
  ]
}</div>
</div>

<!-- Tab: Daily -->
<div class="naws-tab-content" id="ep-daily">
<h3><span class="naws-method">GET</span> <code>/daily</code></h3>
<p style="color:#475569;font-size:13px"><?php naws_e( 'rest_ep_daily_detail' ); ?></p>

<table class="naws-ep-table">
<thead><tr><th>Parameter</th><th>Typ</th><th>Standard</th><th>Beschreibung</th></tr></thead>
<tbody>
<tr><td><span class="naws-param-tag naws-opt-tag">from</span></td><td>string</td><td>-30 Tage</td><td>Startdatum (YYYY-MM-DD)</td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">to</span></td><td>string</td><td>heute</td><td>Enddatum (YYYY-MM-DD)</td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">fields</span></td><td>string</td><td>temp_min, temp_max, temp_avg, pressure_avg, rain_sum</td><td><?php naws_e( 'rest_param_fields' ); ?></td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">group_by</span></td><td>string</td><td>day</td><td>day, week, month, year</td></tr>
<tr><td><span class="naws-param-tag naws-opt-tag">convert</span></td><td>boolean</td><td>true</td><td><?php naws_e( 'rest_param_convert' ); ?></td></tr>
</tbody>
</table>

<h3>Verfügbare Felder</h3>
<p>
<span class="naws-param-tag naws-field-tag">temp_min</span>
<span class="naws-param-tag naws-field-tag">temp_max</span>
<span class="naws-param-tag naws-field-tag">temp_avg</span>
<span class="naws-param-tag naws-field-tag">pressure_avg</span>
<span class="naws-param-tag naws-field-tag">rain_sum</span>
<span class="naws-param-tag naws-field-tag">humidity_avg</span>
<span class="naws-param-tag naws-field-tag">co2_avg</span>
<span class="naws-param-tag naws-field-tag">noise_avg</span>
<span class="naws-param-tag naws-field-tag">wind_avg</span>
<span class="naws-param-tag naws-field-tag">gust_max</span>
</p>

<h3>Beispiel: Monatliche Daten 2025</h3>
<div class="naws-code-block"><span class="k">GET</span> /daily?from=2025-01-01&amp;to=2025-12-31&amp;fields=temp_avg,rain_sum&amp;group_by=month</div>
<div class="naws-code-block">{
  "<span class="s">count</span>": <span class="n">3</span>,
  "<span class="s">from</span>": "<span class="n">2025-01-01</span>",
  "<span class="s">to</span>": "<span class="n">2025-12-31</span>",
  "<span class="s">group_by</span>": "<span class="n">month</span>",
  "<span class="s">units</span>": { "<span class="s">temp_avg</span>": "<span class="n">°C</span>", "<span class="s">rain_sum</span>": "<span class="n">mm</span>" },
  "<span class="s">data</span>": [
    { "<span class="s">date</span>": "<span class="n">2025-01-01</span>", "<span class="s">temp_avg</span>": <span class="n">1.8</span>, "<span class="s">rain_sum</span>": <span class="n">48.2</span> },
    { "<span class="s">date</span>": "<span class="n">2025-02-01</span>", "<span class="s">temp_avg</span>": <span class="n">3.5</span>, "<span class="s">rain_sum</span>": <span class="n">35.1</span> },
    { "<span class="s">date</span>": "<span class="n">2025-03-01</span>", "<span class="s">temp_avg</span>": <span class="n">8.2</span>, "<span class="s">rain_sum</span>": <span class="n">22.6</span> }
  ]
}</div>
</div>

</div><!-- /endpoints panel -->


<!-- ━━ Examples ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="naws-api-panel">
<h2>🧪 <?php naws_e( 'rest_examples_title' ); ?></h2>

<div class="naws-tab-bar">
    <button class="naws-tab active" data-tab="ex-curl">cURL</button>
    <button class="naws-tab" data-tab="ex-js">JavaScript</button>
    <button class="naws-tab" data-tab="ex-php">PHP</button>
    <button class="naws-tab" data-tab="ex-google">Chart.js</button>
    <button class="naws-tab" data-tab="ex-python">Python</button>
</div>

<!-- cURL -->
<div class="naws-tab-content active" id="ex-curl">
<div class="naws-code-block"><span class="c"># Aktuelle Messwerte abrufen</span>
curl -H "<span class="s">X-NAWS-Key: <?php echo esc_html( $api_key ?: 'DEIN_API_KEY' ); ?></span>" \
     "<span class="s"><?php echo esc_html( $base_url ); ?>/current</span>"

<span class="c"># Tagesdaten für März 2025</span>
curl -H "<span class="s">X-NAWS-Key: <?php echo esc_html( $api_key ?: 'DEIN_API_KEY' ); ?></span>" \
     "<span class="s"><?php echo esc_html( $base_url ); ?>/daily?from=2025-03-01&amp;to=2025-03-31</span>"</div>
</div>

<!-- JavaScript -->
<div class="naws-tab-content" id="ex-js">
<div class="naws-code-block"><span class="k">const</span> API_BASE = '<span class="s"><?php echo esc_js( $base_url ); ?></span>';
<span class="k">const</span> API_KEY  = '<span class="s"><?php echo esc_js( $api_key ?: 'DEIN_API_KEY' ); ?></span>';

<span class="k">async function</span> <span class="n">fetchWeather</span>(endpoint, params = {}) {
  <span class="k">const</span> url = <span class="k">new</span> URL(API_BASE + endpoint);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

  <span class="k">const</span> res = <span class="k">await</span> fetch(url, {
    headers: { '<span class="s">X-NAWS-Key</span>': API_KEY }
  });

  <span class="k">if</span> (!res.ok) <span class="k">throw new</span> Error(<span class="s">`HTTP ${res.status}`</span>);
  <span class="k">return</span> res.json();
}

<span class="c">// Aktuelle Werte</span>
<span class="k">const</span> current = <span class="k">await</span> <span class="n">fetchWeather</span>('<span class="s">/current</span>');
console.log(current.readings);

<span class="c">// Temperaturverlauf letzte 7 Tage</span>
<span class="k">const</span> temps = <span class="k">await</span> <span class="n">fetchWeather</span>('<span class="s">/readings</span>', {
  parameter: '<span class="s">Temperature</span>',
  from: '<span class="s">2025-03-01</span>',
  group_by: '<span class="s">hour</span>'
});</div>
</div>

<!-- PHP -->
<div class="naws-tab-content" id="ex-php">
<div class="naws-code-block"><span class="k">&lt;?php</span>
<span class="c">// NAWS REST API – PHP-Beispiel</span>

<span class="k">$base</span> = '<span class="s"><?php echo esc_html( $base_url ); ?></span>';
<span class="k">$key</span>  = '<span class="s"><?php echo esc_html( $api_key ?: 'DEIN_API_KEY' ); ?></span>';

<span class="k">function</span> <span class="n">naws_api_get</span>(<span class="k">$endpoint</span>, <span class="k">$params</span> = []) {
    <span class="k">global $base, $key</span>;
    <span class="k">$url</span> = <span class="k">$base</span> . <span class="k">$endpoint</span>;
    <span class="k">if</span> (!empty(<span class="k">$params</span>)) {
        <span class="k">$url</span> .= '<span class="s">?</span>' . http_build_query(<span class="k">$params</span>);
    }

    <span class="k">$ch</span> = curl_init(<span class="k">$url</span>);
    curl_setopt_array(<span class="k">$ch</span>, [
        CURLOPT_RETURNTRANSFER => <span class="k">true</span>,
        CURLOPT_HTTPHEADER     => ["<span class="s">X-NAWS-Key: {$key}</span>"],
        CURLOPT_TIMEOUT        => <span class="n">10</span>,
    ]);
    <span class="k">$body</span> = curl_exec(<span class="k">$ch</span>);
    curl_close(<span class="k">$ch</span>);
    <span class="k">return</span> json_decode(<span class="k">$body</span>, <span class="k">true</span>);
}

<span class="c">// Aktuelle Messwerte</span>
<span class="k">$data</span> = <span class="n">naws_api_get</span>('<span class="s">/current</span>');
<span class="k">foreach</span> (<span class="k">$data</span>['readings'] <span class="k">as $r</span>) {
    echo "<span class="s">{$r['parameter']}: {$r['value']} {$r['unit']}\n</span>";
}

<span class="c">// Tagesdaten monatlich</span>
<span class="k">$monthly</span> = <span class="n">naws_api_get</span>('<span class="s">/daily</span>', [
    '<span class="s">from</span>'     => '<span class="s">2025-01-01</span>',
    '<span class="s">to</span>'       => '<span class="s">2025-12-31</span>',
    '<span class="s">fields</span>'   => '<span class="s">temp_avg,rain_sum</span>',
    '<span class="s">group_by</span>' => '<span class="s">month</span>',
]);</div>
</div>

<!-- Chart.js (vanilla) -->
<div class="naws-tab-content" id="ex-google">
<div class="naws-code-block"><span class="c">&lt;!-- Chart.js: Temperaturverlauf (kein externes CDN) --&gt;</span>
&lt;canvas id="<span class="s">myChart</span>" width="<span class="s">800</span>" height="<span class="s">300</span>"&gt;&lt;/canvas&gt;

&lt;script&gt;
<span class="k">const</span> API = '<span class="s"><?php echo esc_js( $base_url ); ?></span>';
<span class="k">const</span> KEY = '<span class="s"><?php echo esc_js( $api_key ?: 'DEIN_API_KEY' ); ?></span>';

fetch(API + '<span class="s">/daily?fields=temp_min,temp_max&amp;from=2025-02-01&amp;to=2025-03-01</span>',
  { headers: { '<span class="s">X-NAWS-Key</span>': KEY } }
)
.<span class="k">then</span>(r => r.json())
.<span class="k">then</span>(json => {
  <span class="k">const</span> labels = json.data.<span class="k">map</span>(r => r.date);
  <span class="k">const</span> minT   = json.data.<span class="k">map</span>(r => r.temp_min);
  <span class="k">const</span> maxT   = json.data.<span class="k">map</span>(r => r.temp_max);

  <span class="k">new</span> Chart(document.getElementById('<span class="s">myChart</span>'), {
    type: '<span class="s">line</span>',
    data: {
      labels,
      datasets: [
        { label: '<span class="s">Min °C</span>', data: minT, borderColor: '<span class="s">#3b82f6</span>', fill: <span class="k">false</span> },
        { label: '<span class="s">Max °C</span>', data: maxT, borderColor: '<span class="s">#ef4444</span>', fill: <span class="k">false</span> },
      ],
    },
  });
});
&lt;/script&gt;</div>
</div>

<!-- Python -->
<div class="naws-tab-content" id="ex-python">
<div class="naws-code-block"><span class="k">import</span> requests

API_BASE = '<span class="s"><?php echo esc_html( $base_url ); ?></span>'
API_KEY  = '<span class="s"><?php echo esc_html( $api_key ?: 'DEIN_API_KEY' ); ?></span>'
HEADERS  = { '<span class="s">X-NAWS-Key</span>': API_KEY }

<span class="c"># Aktuelle Messwerte</span>
r = requests.get(f'<span class="s">{API_BASE}/current</span>', headers=HEADERS)
<span class="k">for</span> reading <span class="k">in</span> r.json()['readings']:
    <span class="k">print</span>(f"<span class="s">{reading['parameter']}: {reading['value']} {reading['unit']}</span>")

<span class="c"># Tagesdaten als DataFrame (pandas)</span>
<span class="k">import</span> pandas <span class="k">as</span> pd

r = requests.get(f'<span class="s">{API_BASE}/daily</span>', headers=HEADERS, params={
    '<span class="s">from</span>': '<span class="s">2024-01-01</span>',
    '<span class="s">to</span>':   '<span class="s">2025-03-08</span>',
    '<span class="s">fields</span>': '<span class="s">temp_avg,rain_sum</span>',
})
df = pd.DataFrame(r.json()['data'])
df['date'] = pd.to_datetime(df['date'])
<span class="k">print</span>(df.describe())</div>
</div>

</div><!-- /examples panel -->


<!-- ━━ Error Codes ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="naws-api-panel">
<h2>⚠️ <?php naws_e( 'rest_errors_title' ); ?></h2>
<table class="naws-ep-table">
<thead><tr><th style="width:80px">Code</th><th style="width:200px">Fehler</th><th>Beschreibung</th></tr></thead>
<tbody>
<tr><td><code>401</code></td><td><code>naws_unauthorized</code></td><td><?php naws_e( 'rest_err_401' ); ?></td></tr>
<tr><td><code>400</code></td><td><code>naws_invalid_date</code></td><td><?php naws_e( 'rest_err_400' ); ?></td></tr>
<tr><td><code>429</code></td><td><code>naws_rate_limited</code></td><td><?php naws_e( 'rest_err_429' ); ?></td></tr>
<tr><td><code>503</code></td><td><code>naws_api_not_configured</code></td><td><?php naws_e( 'rest_err_503' ); ?></td></tr>
</tbody>
</table>
</div>

</div><!-- /.wrap -->

<?php
wp_add_inline_script( 'naws-admin', '(function(){
    /* Tab switching */
    document.querySelectorAll(\'.naws-tab\').forEach(function(tab){
        tab.addEventListener(\'click\', function(){
            var panel = this.closest(\'.naws-api-panel\');
            panel.querySelectorAll(\'.naws-tab\').forEach(function(t){ t.classList.remove(\'active\'); });
            panel.querySelectorAll(\'.naws-tab-content\').forEach(function(c){ c.classList.remove(\'active\'); });
            this.classList.add(\'active\');
            var target = document.getElementById(this.dataset.tab);
            if(target) target.classList.add(\'active\');
        });
    });
})();
/* Copy API key */
function nawsCopyKey(){
    var inp = document.getElementById(\'naws-api-key-display\');
    if(!inp) return;
    inp.select();
    navigator.clipboard.writeText(inp.value).then(function(){
        inp.style.borderColor=\'#10b981\';
        setTimeout(function(){ inp.style.borderColor=\'\'; }, 1500);
    });
}' );
?>
