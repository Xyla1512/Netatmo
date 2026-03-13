<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap naws-admin-wrap">
<h1 class="naws-admin-page-title">
    <span class="naws-title-icon">📥</span>
    <?php naws_e( 'import_title' ); ?>
</h1>

<div class="naws-admin-two-col">

<!-- ── LEFT ───────────────────────────────────────────────────── -->
<div>
  <div class="naws-admin-panel">
    <div class="naws-panel-header">
        <h2><?php naws_e( 'import_config' ); ?></h2>
    </div>

    <div class="naws-panel-body" style="padding-bottom:0"><div class="naws-info-box naws-info-box-info" style="margin:0;">
        <strong>ℹ️ <?php naws_e( 'import_what' ); ?></strong>
        <ul style="margin:0.4rem 0 0 1rem; padding:0; font-size:0.85rem;">
            <li>🌡️ <strong><?php naws_e( 'mod_outdoor' ); ?> (NAModule1)</strong> → Temp Min/Max</li>
            <li>🔵 <strong><?php naws_e( 'mod_base_sub' ); ?> (NAMain)</strong> → <?php naws_e( 'param_pressure_rel' ); ?></li>
            <li>🌧️ <strong><?php naws_e( 'mod_rain' ); ?> (NAModule3)</strong> → <?php naws_e( 'param_rain_24h' ); ?></li>
            <li>💨 <strong><?php naws_e( 'mod_wind' ); ?> (NAModule2)</strong> → <?php naws_e( 'param_wind_speed' ); ?></li>
        </ul>
        <p style="margin:0.4rem 0 0; color:#94a3b8; font-size:0.78rem;">
            <?php naws_e( 'import_date_hint' ); ?>
        </p>
    </div></div>

    <?php if ( empty($modules) ) : ?>
        <p class="naws-panel-body"><?php naws_e( 'import_no_modules' ); ?></p>
    <?php else : ?>

    <!-- Module badges -->
    <div class="naws-panel-body" style="padding-bottom:0;">
        <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.75rem;">
        <?php foreach($modules as $m):
            $types = NAWS_Importer::get_import_types($m['module_type']);
            $skip  = empty($types);
        ?>
            <span class="naws-module-badge<?php echo $skip ? ' naws-module-badge-skip' : ''; ?>">
                <?php echo $skip ? '⏭' : '✅'; ?>
                <?php echo esc_html($m['module_name']); ?>
                <small>(<?php echo esc_html($m['module_type']); ?>)</small>
                <?php if(!$skip): ?>
                <small style="color:#94a3b8;">→ <?php echo esc_html(implode(', ',$types)); ?></small>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Date range -->
    <div class="naws-panel-body" style="padding-top:0.5rem; padding-bottom:0;">
    <table class="form-table naws-form-table">
        <tr>
            <th><label for="naws-import-from"><?php naws_e( 'import_date_from' ); ?></label></th>
            <td>
                <input type="date" id="naws-import-from" class="regular-text"
                    value="<?php echo esc_attr(gmdate( 'Y-01-01', strtotime('-2 years'))); ?>"
                    max="<?php echo esc_attr(gmdate( 'Y-m-d', strtotime('-1 day'))); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="naws-import-to"><?php naws_e( 'import_date_to' ); ?></label></th>
            <td>
                <input type="date" id="naws-import-to" class="regular-text"
                    value="<?php echo esc_attr(gmdate( 'Y-m-d', strtotime('-1 day'))); ?>"
                    max="<?php echo esc_attr(gmdate( 'Y-m-d', strtotime('-1 day'))); ?>">
            </td>
        </tr>
    </table>
    </div>

    <!-- Progress bar -->
    <div id="naws-import-progress" class="naws-panel-body" style="display:none; padding-top:0.5rem; padding-bottom:0.5rem;">
        <div style="display:flex; justify-content:space-between; font-size:0.78rem; color:#94a3b8; margin-bottom:0.25rem;">
            <span id="naws-progress-label">Starte…</span>
            <span id="naws-progress-pct">0 %</span>
        </div>
        <div style="background:#1e293b; border-radius:4px; height:8px; overflow:hidden;">
            <div id="naws-progress-fill" style="width:0%; height:100%;
                 background:linear-gradient(90deg,#00d4ff,#10b981); transition:width .4s;"></div>
        </div>
    </div>

    <!-- Buttons -->
    <div class="naws-panel-body" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
        <button id="naws-start-import" class="button button-primary button-large">
            ⬇️ <?php naws_e( 'import_btn' ); ?>
        </button>
        <button id="naws-stop-import"  class="button button-large" style="display:none;">
            ⏹ Abbrechen
        </button>
        <select id="naws-debug-module" style="font-size:0.8rem;padding:4px 8px;background:#1e293b;color:#94a3b8;border:1px solid #334155;border-radius:4px;display:none;">
            <option value="0">Modul wählen…</option>
        </select>
        <button id="naws-debug-btn" class="button button-small" title="Rohdaten der API-Anfrage für gewähltes Modul anzeigen">
            🔍 Debug
        </button>
        <button id="naws-db-check-btn" class="button button-small" title="Zeige DB-Einträge für das Import-Datum">
            🗄️ DB prüfen
        </button>
        <span id="naws-import-summary" style="font-size:0.85rem; color:#94a3b8;"></span>
    </div>

    <?php endif; ?>
  </div><!-- .naws-admin-panel -->
</div>

<!-- ── RIGHT ──────────────────────────────────────────────────── -->
<div>
    <!-- Stats -->
    <div class="naws-admin-panel" style="margin-bottom:1.5rem;">
        <div class="naws-panel-header"><h2>📆 <?php naws_e( 'existing_data' ); ?></h2></div>
        <?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound
            $dc = NAWS_Database::count_daily_summaries();
            $dr = NAWS_Database::get_daily_data_range();
        ?>
        <div class="naws-panel-body">
        <table class="naws-info-table">
            <tr><td><?php naws_e( 'daily_table' ); ?>:</td><td><strong><?php echo number_format($dc); ?></strong></td></tr>
            <?php if($dr && $dr['date_begin']): ?>
            <tr><td><?php naws_e( 'date' ); ?>:</td><td><?php echo esc_html($dr['date_begin']); ?></td></tr>
            <tr><td><?php naws_e( 'date' ); ?>:</td><td><?php echo esc_html($dr['date_end']); ?></td></tr>
            <?php else: ?>
            <tr><td colspan="2" style="color:#64748b;"><?php naws_e( 'no_data_yet' ); ?></td></tr>
            <?php endif; ?>
        </table>
        </div>
    </div>

    <!-- Clear summary table panel – always visible -->
    <?php $summary_count = NAWS_Database::count_daily_summaries(); ?>
    <div class="naws-admin-panel" style="margin-bottom:1.5rem; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.04);">
        <div class="naws-panel-header" style="border-color:rgba(239,68,68,0.2);">
            <h2>🗑️ Tabelle zurücksetzen</h2>
        </div>
        <div class="naws-panel-body">
            <p style="font-size:0.85rem;color:#94a3b8;margin:0 0 0.75rem;">
                <?php if ( $summary_count > 0 ) : ?>
                    <strong style="color:#ef4444;"><?php echo number_format($summary_count); ?> Einträge</strong>
                    in <code>naws_daily_summary</code> vorhanden – vor einem Neuimport empfehlen wir die Tabelle zu leeren.
                <?php else : ?>
                    <code>naws_daily_summary</code> ist leer – bereit für den Import.
                <?php endif; ?>
            </p>
            <button id="naws-clear-summary-btn" class="button" style="color:#ef4444;border-color:#ef4444;"
                <?php echo $summary_count === 0 ? 'disabled' : ''; ?>>
                🗑️ Alle Einträge löschen
            </button>
            <span id="naws-clear-summary-msg" style="font-size:0.85rem;margin-left:0.75rem;"></span>
        </div>
    </div>

    <!-- Migration panel -->
    <?php
    $readings_range = NAWS_Database::get_data_range();
    $readings_count = NAWS_Database::count_readings();
    if ( $readings_count > 0 ) : ?>
    <div class="naws-admin-panel" style="margin-bottom:1.5rem; border:1px solid rgba(245,158,11,0.4); background:rgba(245,158,11,0.05);">
        <div class="naws-panel-header" style="border-color:rgba(245,158,11,0.2);">
            <h2>⚡ Readings → Summary migrieren</h2>
        </div>
        <div class="naws-panel-body">
            <p style="font-size:0.85rem; color:#94a3b8; margin:0 0 0.75rem;">
                Du hast <strong style="color:#f59e0b;"><?php echo number_format($readings_count); ?> Rohwerte</strong>
                in <code>naws_readings</code>
                (<?php echo esc_html(date_i18n('d.m.Y', $readings_range['date_begin'])); ?> –
                 <?php echo esc_html(date_i18n('d.m.Y', $readings_range['date_end'])); ?>).
                Alle <strong>abgeschlossenen Tage bis gestern</strong> werden in die Tagesdaten-Tabelle übertragen.
                Der heutige Tag wird automatisch um 00:01 Uhr vom Cron-Job verarbeitet.
            </p>
            <div id="naws-migrate-progress" style="display:none; margin-bottom:0.75rem;">
                <div style="background:#1e293b; border-radius:4px; height:6px; overflow:hidden;">
                    <div id="naws-migrate-fill" style="width:0%; height:100%; background:linear-gradient(90deg,#f59e0b,#10b981); transition:width .3s;"></div>
                </div>
                <p id="naws-migrate-status" style="font-size:0.78rem; color:#94a3b8; margin:0.3rem 0 0;"></p>
            </div>
            <button id="naws-migrate-btn" class="button button-primary">
                ⚡ Jetzt migrieren
            </button>
            <span id="naws-migrate-summary" style="font-size:0.85rem; color:#94a3b8; margin-left:0.75rem;"></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Log -->
    <div class="naws-admin-panel">
        <div class="naws-panel-header">
            <h2>📋 Import Log</h2>
            <button id="naws-clear-log" class="button button-small" style="margin-left:auto;">Clear</button>
        </div>
        <div id="naws-import-log"
             style="max-height:450px; overflow-y:auto; padding:0.6rem 1rem;
                    font-size:0.78rem; font-family:monospace; background:#0a0e1a;
                    border-radius:0 0 0.5rem 0.5rem; line-height:1.7;">
            <span style="color:#475569;">Log erscheint hier…</span>
        </div>
    </div>
</div>

</div><!-- .naws-admin-two-col -->
</div>

<style>
.naws-module-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;
    border-radius:2rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);
    font-size:.78rem; color:#1d2327;}
.naws-module-badge-skip{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.2);opacity:.45;}
.naws-module-badge-info{background:rgba(99,102,241,.07);border-color:rgba(99,102,241,.25);opacity:.85;}

/* form-table inside panel-body: reset WP defaults to match our spacing */
.naws-admin-panel .naws-form-table { margin: 0 !important; width: 100%; }
.naws-admin-panel .naws-form-table th { padding: 0.55rem 1rem 0.55rem 0 !important; font-weight: 500; color: #646970; font-size: 0.875rem; }
.naws-admin-panel .naws-form-table td { padding: 0.55rem 0 !important; }
</style>

<script>
(function($){
'use strict';

const NONCE   = '<?php echo esc_js( wp_create_nonce('naws_admin_nonce') ); ?>';
const AJAXURL = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

// Modules list for debug / job iteration
const MODULES = <?php
    $mod_json = [];
    foreach($modules as $m){
        $t = NAWS_Importer::get_import_types($m['module_type']);
        if(empty($t)) continue;
        $mod_json[] = [
            'module_id'   => $m['module_id'],
            'module_name' => $m['module_name'],
            'module_type' => $m['module_type'],
            'device_id'   => $m['station_id'],
            'types'       => $t,
        ];
    }
    echo json_encode($mod_json);
?>;

// Pre-populate debug dropdown on page load
(function(){
    const sel = document.getElementById('naws-debug-module');
    if(!sel) return;
    MODULES.forEach(function(m, i){
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = m.module_name + ' (' + m.module_type + ')';
        sel.appendChild(opt);
    });
    if(MODULES.length) sel.style.display = '';
})();

let stopped      = false;
let insertedDays = 0;
const logEl      = document.getElementById('naws-import-log');

/* ── Logging ────────────────────────────────────────────────── */
function log(msg, type = 'info'){
    const colors = {ok:'#10b981', error:'#ef4444', info:'#94a3b8', warn:'#f59e0b', skip:'#475569'};
    const ts     = new Date().toLocaleTimeString('de-DE');
    logEl.insertAdjacentHTML('beforeend',
        `<div style="color:${colors[type]||'#94a3b8'}"><span style="color:#334155">${ts}</span>  ${msg}</div>`);
    logEl.scrollTop = logEl.scrollHeight;
}
$('#naws-clear-log').on('click', () => { logEl.innerHTML = ''; });

/* ── Progress ───────────────────────────────────────────────── */
function progress(label, pct){
    $('#naws-import-progress').show();
    $('#naws-progress-label').text(label);
    $('#naws-progress-pct').text(Math.round(pct) + ' %');
    $('#naws-progress-fill').css('width', Math.round(pct) + '%');
}

/* ── Import flow ────────────────────────────────────────────── */
$('#naws-start-import').on('click', function(){
    const from = $('#naws-import-from').val();
    const to   = $('#naws-import-to').val();
    if(!from || !to || from >= to){ alert('Gültigen Datumsbereich wählen.'); return; }

    stopped      = false;
    insertedDays = 0;
    logEl.innerHTML = '';
    progress('Starte…', 0);

    const dateBegin = Math.floor(new Date(from + 'T00:00:00').getTime() / 1000);
    const dateEnd   = Math.floor(new Date(to   + 'T23:59:59').getTime() / 1000);
    const totalDays = Math.ceil((dateEnd - dateBegin) / 86400);

    $('#naws-start-import').hide();
    $('#naws-stop-import').show();
    $('#naws-import-summary').text('');

    log(`Import: ${from} → ${to} (${totalDays} Tage, ${MODULES.length} Module)`, 'info');
    if(!MODULES.length){ log('Keine importierbaren Module.','warn'); resetUI(); return; }

    MODULES.forEach(m => log(`  📋 ${m.module_name} (${m.module_type}) → ${m.types.join(', ')}`, 'info'));

    // Populate debug module dropdown
    const sel = $('#naws-debug-module');
    sel.empty();
    MODULES.forEach(function(m, i){
        sel.append(`<option value="${i}">${m.module_name} (${m.module_type})</option>`);
    });
    sel.show();

    runModules(MODULES, 0, dateBegin, dateEnd, totalDays, dateBegin);
});

function runModules(mods, idx, dateBegin, dateEnd, totalDays, originalDateBegin){
    if(stopped || idx >= mods.length){ done(); return; }
    const mod = mods[idx];
    log(`\n▶ ${mod.module_name} (${mod.module_type})`, 'info');
    const orig = originalDateBegin !== undefined ? originalDateBegin : dateBegin;
    runChunk(mods, idx, mod, orig, dateEnd, totalDays, orig);
}

function runChunk(mods, modIdx, mod, chunkBegin, dateEnd, totalDays, originalDateBegin){
    if(stopped){ done(); return; }

    const elapsed  = chunkBegin - (dateEnd - totalDays * 86400);
    const modFrac  = modIdx / mods.length;
    const chunkFrac= Math.min(1, elapsed / (totalDays * 86400));
    const pct      = (modFrac + chunkFrac / mods.length) * 100;
    const dateStr  = new Date(chunkBegin * 1000).toLocaleDateString('de-DE');
    progress(`${mod.module_name}: ab ${dateStr}`, pct);

    $.post(AJAXURL, {
        action:      'naws_import_chunk',
        nonce:       NONCE,
        device_id:   mod.device_id,
        module_id:   mod.module_id,
        module_type: mod.module_type,
        date_begin:  chunkBegin,
        date_end:    dateEnd,
    })
    .done(function(resp){
        if(!resp.success){
            const msg = resp.data?.message || 'Unbekannter Fehler';
            log(`  ❌ ${msg}`, 'error');
            setTimeout(() => runModules(mods, modIdx + 1, originalDateBegin, dateEnd, totalDays, originalDateBegin), 600);
            return;
        }
        const d = resp.data;
        if(d.skipped){
            log(`  ⏭ ${d.message}`, 'skip');
            runModules(mods, modIdx + 1, originalDateBegin, dateEnd, totalDays, originalDateBegin);
            return;
        }

        insertedDays += d.inserted || 0;
        const note   = d.debug?.note    ? ` ⚠️ ${d.debug.note}`  : '';
        const range  = d.debug?.first   ? ` [${d.debug.first} → ${d.debug.last}]` : '';
        const color  = d.rows_fetched > 0 ? 'ok' : 'warn';
        log(`  ${d.rows_fetched > 0 ? '✅' : '⚠️'} ${d.rows_fetched} Tage gelesen, ${d.inserted} gespeichert${range}${note}`, color);

        $('#naws-import-summary').text(`${insertedDays} Tagesdaten gespeichert`);

        if(d.next_begin && d.next_begin < dateEnd && !stopped){
            setTimeout(() => runChunk(mods, modIdx, mod, d.next_begin, dateEnd, totalDays, originalDateBegin), 400);
        } else {
            runModules(mods, modIdx + 1, originalDateBegin, dateEnd, totalDays, originalDateBegin);
        }
    })
    .fail(function(xhr){
        log(`  ❌ HTTP ${xhr.status} — ${xhr.responseText?.substring(0,300)}`, 'error');
        setTimeout(() => runModules(mods, modIdx + 1, originalDateBegin, dateEnd, totalDays, originalDateBegin), 800);
    });
}

function done(){
    progress('Fertig', 100);
    const msg = stopped ? '⏹ Abgebrochen.' : `✅ Fertig – ${insertedDays} Tagesdaten gespeichert.`;
    log('\n' + msg, stopped ? 'warn' : 'ok');
    $('#naws-import-summary').text(msg);
    resetUI();
}

function resetUI(){
    $('#naws-start-import').show();
    $('#naws-stop-import').hide();
}

$('#naws-stop-import').on('click', () => { stopped = true; });

/* ── Populate debug module dropdown ─────────────────────────── */
function populateDebugDropdown() {
    const sel = $('#naws-debug-module');
    sel.find('option:not(:first)').remove();
    MODULES.forEach(function(m, i) {
        sel.append(`<option value="${i}">${m.module_name} (${m.module_type}) → ${m.types.join(',')}</option>`);
    });
}

/* ── Debug: show raw API response for selected module ───────── */
$('#naws-debug-btn').on('click', function(){
    if(!MODULES.length){ log('Keine Module für Debug.','warn'); return; }
    const idx  = parseInt($('#naws-debug-module').val()) || 0;
    const m    = MODULES[idx] || MODULES[0];
    const from = $('#naws-import-from').val();
    const to   = new Date(from);
    to.setDate(to.getDate() + 3);
    const toStr = to.toISOString().slice(0,10);

    const debugBegin = Math.floor(new Date(from + 'T00:00:00').getTime() / 1000);
    const debugEnd   = Math.floor(new Date(toStr + 'T23:59:59').getTime() / 1000);

    log(`\n🔍 Debug: ${m.module_name} | ${m.module_type} | ${from} → ${toStr}`, 'info');
    log(`   device_id: ${m.device_id} | module_id: ${m.module_id}`, 'info');
    log(`   types: ${m.types.join(', ')}`, 'info');
    log(`   date_begin Unix: ${debugBegin} | date_end Unix: ${debugEnd}`, 'info');

    $.post(AJAXURL, {
        action:      'naws_import_debug',
        nonce:       NONCE,
        device_id:   m.device_id,
        module_id:   m.module_id,
        module_type: m.module_type,
        date_begin:  debugBegin,
        date_end:    debugEnd,
        types:       m.types.join(','),
    })
    .done(function(resp){
        // This endpoint always returns success=true, raw details inside resp.data
        const d = resp.data;
        const ok = d.http_code === 200;
        log(`   HTTP ${d.http_code}  |  Token: ${d.token_present ? '✅' : '❌'}  |  ${d.token_expires_in}`, ok ? 'ok' : 'error');
        log(`   Zeitraum: ${d.date_begin_human} → ${d.date_end_human}  |  module_id gesendet: ${d.sends_module_id}`, 'info');
        log(`   POST body: ${JSON.stringify(d.post_body_sent)}`, 'info');

        if(!ok || d.wp_error){
            log(`   ❌ Fehler: ${d.wp_error || ''}`, 'error');
            log(`   Raw Netatmo Response: ${d.raw_body}`, 'error');
            return;
        }

        const body = d.parsed?.body;
        if(!body || (Array.isArray(body) && body.length === 0)){
            log(`   ⚠️ Kein body in Antwort – Raw: ${d.raw_body}`, 'warn');
            return;
        }

        // Always show the complete raw response for debugging
        log(`   📄 Raw body: ${JSON.stringify(d.parsed?.body).substring(0, 500)}`, 'info');

        const entries = Array.isArray(body) ? body : [body];
        log(`   ✅ ${entries.length} Einträge empfangen`, 'ok');
        entries.slice(0,3).forEach((entry, i) => {
            const dt  = entry.beg_time ? new Date(entry.beg_time * 1000).toLocaleString('de-DE') : 'kein beg_time';
            const cnt = (entry.value||[]).length;
            const v0  = (entry.value||[[]])[0];
            log(`   [${i}] beg_time: ${dt}  step: ${entry.step_time}s  ${cnt} Werte  erste: ${JSON.stringify(v0)}`, 'ok');
            log(`   [${i}] alle Keys: ${Object.keys(entry).join(', ')}`, 'info');
        });
    })
    .fail(function(xhr){
        log('   ❌ HTTP ' + xhr.status + ' — ' + xhr.responseText.substring(0,300), 'error');
    });
});

/* ── Clear daily_summary table ──────────────────────────────── */
$('#naws-clear-summary-btn').on('click', function(){
    if(!confirm('Wirklich alle ' + $(this).closest('.naws-admin-panel').find('strong').text() + ' Einträge löschen? Dies kann nicht rückgängig gemacht werden.')) return;
    const btn = $(this);
    btn.prop('disabled', true).text('Lösche…');
    $.post(AJAXURL, { action: 'naws_clear_daily_summary', nonce: NONCE })
    .done(function(resp){
        if(resp.success){
            $('#naws-clear-summary-msg').css('color','#10b981').text('✅ ' + resp.data.deleted + ' Einträge gelöscht. Seite wird neu geladen…');
            setTimeout(() => location.reload(), 1500);
        } else {
            $('#naws-clear-summary-msg').css('color','#ef4444').text('Fehler: ' + (resp.data?.message || 'Unbekannt'));
            btn.prop('disabled', false).text('🗑️ Alle Einträge löschen');
        }
    });
});

/* ── DB check: show what's actually stored for the date range ── */
$('#naws-db-check-btn').on('click', function(){
    const from = $('#naws-import-from').val();
    const to   = $('#naws-import-to').val();
    log(`\n🗄️ DB-Check: ${from} → ${to}`, 'info');
    $.post(AJAXURL, {
        action: 'naws_db_check',
        nonce:  NONCE,
        date_from: from,
        date_to:   to,
    }).done(function(resp){
        if(!resp.success){ log('Fehler: ' + (resp.data?.message || '?'), 'error'); return; }
        const rows = resp.data.rows;
        if(!rows.length){ log('  Keine Einträge gefunden.', 'warn'); return; }
        log(`  ${rows.length} Einträge:`, 'ok');
        rows.forEach(function(r){
            log(`  ${r.day_date} | temp: ${r.temp_min??'—'}/${r.temp_max??'—'}°C | druck: ${r.pressure_avg??'—'} hPa | regen: ${r.rain_sum??'—'} mm`, 'info');
        });
    });
});

/* ── Migrate readings → daily_summary ───────────────────────── */
$('#naws-migrate-btn').on('click', function(){
    $(this).prop('disabled', true).text('Läuft…');
    $('#naws-migrate-progress').show();
    $('#naws-migrate-summary').text('');

    const dateRange = <?php
        $rr       = NAWS_Database::get_data_range();
        $yesterday = gmdate( 'Y-m-d', strtotime('yesterday'));
        echo json_encode([
            'from' => $rr['date_begin'] ? gmdate( 'Y-m-d', $rr['date_begin']) : '',
            'to'   => $yesterday,  // never include today - cron handles it at 00:01
        ]);
    ?>;

    let totalSaved = 0;
    let totalDays  = 0;

    function runBatch(dateFrom) {
        $('#naws-migrate-status').text('Verarbeite ab ' + dateFrom + '…');

        $.post(AJAXURL, {
            action:    'naws_migrate_readings',
            nonce:     NONCE,
            date_from: dateFrom,
            date_to:   dateRange.to,
            batch:     30,
        })
        .done(function(resp) {
            if (!resp.success) {
                $('#naws-migrate-status').text('Fehler: ' + (resp.data?.message || 'Unbekannt'));
                $('#naws-migrate-btn').prop('disabled', false).text('⚡ Jetzt migrieren');
                return;
            }
            const d = resp.data;
            totalSaved += d.saved  || 0;
            totalDays  += d.processed || 0;

            if (d.processed > 0) {
                $('#naws-migrate-status').text(`${totalDays} Tage verarbeitet, ${totalSaved} Einträge gespeichert (${d.first} → ${d.last})`);
                // Rough progress based on date range
                const start  = new Date(dateRange.from).getTime();
                const end    = new Date(dateRange.to).getTime();
                const cur    = new Date(d.last).getTime();
                const pct    = Math.min(100, Math.round((cur - start) / (end - start) * 100));
                $('#naws-migrate-fill').css('width', pct + '%');
            }

            if (d.next_from) {
                setTimeout(() => runBatch(d.next_from), 300);
            } else {
                $('#naws-migrate-fill').css('width', '100%');
                $('#naws-migrate-status').text(`✅ Fertig! ${totalDays} Tage → ${totalSaved} Einträge in daily_summary`);
                $('#naws-migrate-summary').text(`✅ ${totalSaved} Tagesdaten migriert`);
                $('#naws-migrate-btn').prop('disabled', false).text('⚡ Nochmals migrieren');
                // Reload page to update stats
                setTimeout(() => location.reload(), 2000);
            }
        })
        .fail(function(xhr) {
            $('#naws-migrate-status').text('HTTP Fehler: ' + xhr.status);
            $('#naws-migrate-btn').prop('disabled', false).text('⚡ Jetzt migrieren');
        });
    }

    runBatch(dateRange.from);
});

})(jQuery);
</script>
