<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap naws-admin-wrap">
<h1 class="naws-admin-page-title">
    <span class="naws-title-icon">📦</span>
    <?php naws_e( 'export_title' ); ?>
</h1>

<?php
// phpcs:disable PluginCheck.CodeAnalysis.VariableAnalysis.NonPrefixedVariableFound

// Show import result message if redirected back (nonce protects against URL manipulation)
$naws_notice_valid = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'naws_notice' );

if ( $naws_notice_valid && isset( $_GET['import_error'] ) ) :
    $error_msg = sanitize_text_field( wp_unslash( $_GET['import_error'] ) );
    ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<?php if ( $naws_notice_valid && isset( $_GET['import_done'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( naws__( 'import_complete' ) ); ?></p></div>
<?php endif; ?>

<div class="naws-admin-two-col">

<!-- ── LEFT: EXPORT ──────────────────────────────────────────── -->
<div>

  <!-- Weather Data Export -->
  <div class="naws-admin-panel" style="margin-bottom:1.5rem;">
    <div class="naws-panel-header">
        <h2>📊 <?php naws_e( 'export_weather_title' ); ?></h2>
    </div>
    <div class="naws-panel-body">
        <p style="font-size:0.85rem; color:#94a3b8; margin:0 0 0.75rem;">
            <?php naws_e( 'export_weather_desc' ); ?>
        </p>

        <?php if ( $daily_count > 0 ) : ?>
            <table class="naws-info-table" style="margin-bottom:1rem;">
                <tr>
                    <td><?php naws_e( 'daily_table' ); ?>:</td>
                    <td><strong><?php echo esc_html( number_format( $daily_count ) ); ?></strong> <?php echo esc_html( naws__( 'export_rows', [ number_format( $daily_count ) ] ) ); ?></td>
                </tr>
                <?php if ( $daily_range && $daily_range['date_begin'] ) : ?>
                <tr>
                    <td><?php naws_e( 'import_date_range' ); ?>:</td>
                    <td><?php echo esc_html( $daily_range['date_begin'] . ' — ' . $daily_range['date_end'] ); ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'naws_export_weather' ); ?>
                <input type="hidden" name="action" value="naws_export_weather">
                <button type="submit" class="button button-primary">
                    📥 <?php naws_e( 'export_btn_weather' ); ?>
                </button>
            </form>
        <?php else : ?>
            <p style="color:#64748b;"><?php naws_e( 'export_no_data' ); ?></p>
        <?php endif; ?>
    </div>
  </div>

  <!-- Full Backup Export -->
  <div class="naws-admin-panel">
    <div class="naws-panel-header">
        <h2>💾 <?php naws_e( 'export_full_title' ); ?></h2>
    </div>
    <div class="naws-panel-body">
        <p style="font-size:0.85rem; color:#94a3b8; margin:0 0 0.75rem;">
            <?php naws_e( 'export_full_desc' ); ?>
        </p>

        <div class="naws-info-box naws-info-box-info" style="margin:0 0 1rem;">
            <strong>🔒</strong> <?php naws_e( 'export_full_note' ); ?>
        </div>

        <table class="naws-info-table" style="margin-bottom:1rem;">
            <tr>
                <td><?php naws_e( 'menu_modules' ); ?>:</td>
                <td><strong><?php echo esc_html( count( $modules ) ); ?></strong> <?php echo esc_html( naws__( 'export_modules_count', [ count( $modules ) ] ) ); ?></td>
            </tr>
            <tr>
                <td><?php naws_e( 'daily_table' ); ?>:</td>
                <td><strong><?php echo esc_html( number_format( $daily_count ) ); ?></strong> <?php naws_e( 'export_rows_label' ); ?></td>
            </tr>
        </table>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'naws_export_full' ); ?>
            <input type="hidden" name="action" value="naws_export_full">
            <button type="submit" class="button button-primary">
                💾 <?php naws_e( 'export_btn_full' ); ?>
            </button>
        </form>
    </div>
  </div>

</div>

<!-- ── RIGHT: IMPORT ─────────────────────────────────────────── -->
<div>

  <div class="naws-admin-panel" style="margin-bottom:1.5rem;">
    <div class="naws-panel-header">
        <h2>📤 <?php naws_e( 'import_file_title' ); ?></h2>
    </div>
    <div class="naws-panel-body">
        <p style="font-size:0.85rem; color:#94a3b8; margin:0 0 0.75rem;">
            <?php naws_e( 'import_file_desc' ); ?>
        </p>

        <div class="naws-info-box" style="margin:0 0 1rem; border-color:rgba(245,158,11,0.4); background:rgba(245,158,11,0.05);">
            <strong>⚠️</strong> <?php naws_e( 'import_overwrite_warn' ); ?>
        </div>

        <form id="naws-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field( 'naws_import_file' ); ?>
            <input type="hidden" name="action" value="naws_import_file">

            <div style="margin-bottom:1rem;">
                <label for="naws-import-file" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.4rem;">
                    <?php naws_e( 'import_file_label' ); ?>
                </label>
                <input type="file" id="naws-import-file" name="naws_import_file" accept=".json"
                       style="font-size:0.85rem;">
            </div>

            <div style="margin-bottom:1rem;">
                <label style="font-size:0.85rem;">
                    <input type="checkbox" name="naws_overwrite_settings" value="1">
                    <?php naws_e( 'import_overwrite_settings' ); ?>
                </label>
            </div>

            <button type="submit" class="button button-primary" id="naws-import-btn">
                📤 <?php naws_e( 'import_file_btn' ); ?>
            </button>
        </form>
    </div>
  </div>

  <!-- Import Progress (hidden until import starts) -->
  <div id="naws-import-progress-panel" class="naws-admin-panel" style="display:none;">
    <div class="naws-panel-header">
        <h2>⏳ <?php naws_e( 'import_processing' ); ?></h2>
    </div>
    <div class="naws-panel-body">
        <!-- Progress bar -->
        <div style="margin-bottom:0.75rem;">
            <div style="display:flex; justify-content:space-between; font-size:0.78rem; color:#94a3b8; margin-bottom:0.25rem;">
                <span id="naws-ei-progress-label">...</span>
                <span id="naws-ei-progress-pct">0 %</span>
            </div>
            <div style="background:#1e293b; border-radius:4px; height:8px; overflow:hidden;">
                <div id="naws-ei-progress-fill" style="width:0%; height:100%;
                     background:linear-gradient(90deg,#00d4ff,#10b981); transition:width .4s;"></div>
            </div>
        </div>

        <!-- Log area -->
        <div id="naws-ei-log"
             style="max-height:300px; overflow-y:auto; padding:0.6rem 1rem;
                    font-size:0.78rem; font-family:monospace; background:#0a0e1a;
                    border-radius:0.5rem; line-height:1.7;">
            <span style="color:#475569;">Import-Log erscheint hier...</span>
        </div>
    </div>
  </div>

</div>

</div><!-- .naws-admin-two-col -->
</div>

<?php
ob_start();
?>
(function($){
'use strict';

const NONCE   = '<?php echo esc_js( wp_create_nonce( 'naws_admin_nonce' ) ); ?>';
const AJAXURL = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

<?php
// Check if we have a pending import (redirected back after file upload)
$import_ready = false;
$import_meta  = null;
$import_file  = get_transient( 'naws_import_temp_file' );
$import_meta  = get_transient( 'naws_import_meta' );
if ( $import_file && $import_meta && isset( $_GET['import_ready'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $import_ready = true;
?>
const IMPORT_READY = true;
const IMPORT_META  = <?php echo wp_json_encode( $import_meta ); ?>;
<?php else : ?>
const IMPORT_READY = false;
const IMPORT_META  = null;
<?php endif; ?>

const logEl = document.getElementById('naws-ei-log');

function log(msg, type) {
    type = type || 'info';
    const colors = {ok:'#10b981', error:'#ef4444', info:'#94a3b8', warn:'#f59e0b'};
    const ts = new Date().toLocaleTimeString('de-DE');
    logEl.insertAdjacentHTML('beforeend',
        '<div style="color:' + (colors[type]||'#94a3b8') + '"><span style="color:#334155">' + ts + '</span>  ' + msg + '</div>');
    logEl.scrollTop = logEl.scrollHeight;
}

function progress(label, pct) {
    $('#naws-ei-progress-label').text(label);
    $('#naws-ei-progress-pct').text(Math.round(pct) + ' %');
    $('#naws-ei-progress-fill').css('width', Math.round(pct) + '%');
}

// ── Auto-start chunked import if file was uploaded ──────────
if (IMPORT_READY && IMPORT_META) {
    $('#naws-import-progress-panel').show();
    logEl.innerHTML = '';

    var totalImported = 0;
    var totalSkipped  = 0;
    var exportType    = IMPORT_META.export_type || 'weather_data';
    var metaDone      = false;

    log('Import gestartet: ' + exportType + ' (' + (IMPORT_META.row_count||'?') + ' Zeilen)', 'info');
    log('Exportiert am: ' + (IMPORT_META.export_date||'?') + ' von ' + (IMPORT_META.site_url||'?'), 'info');

    // Step 1: If full_backup, import modules + settings first
    if (exportType === 'full_backup') {
        log('Importiere Module und Einstellungen...', 'info');
        $.post(AJAXURL, {
            action: 'naws_import_meta',
            nonce:  NONCE,
            overwrite_settings: <?php echo isset( $_GET['overwrite_settings'] ) ? '1' : ( $import_meta && ! empty( $import_meta['overwrite_settings'] ) ? '1' : '0' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        }).done(function(resp) {
            if (resp.success) {
                var d = resp.data;
                log('Module importiert: ' + d.modules_imported, 'ok');
                if (d.settings_imported) {
                    log('Einstellungen importiert', 'ok');
                }
            } else {
                log('Fehler: ' + (resp.data?.message || 'Unbekannt'), 'error');
            }
            // Now import daily_summary data
            runImportChunk(0);
        }).fail(function(xhr) {
            log('HTTP Fehler: ' + xhr.status, 'error');
            runImportChunk(0);
        });
    } else {
        runImportChunk(0);
    }

    function runImportChunk(offset) {
        var total = IMPORT_META.row_count || 0;
        var pct   = total > 0 ? Math.min(99, Math.round(offset / total * 100)) : 0;
        progress('Importiere Zeile ' + offset + '/' + total + '...', pct);

        $.post(AJAXURL, {
            action: 'naws_import_process_chunk',
            nonce:  NONCE,
            offset: offset
        }).done(function(resp) {
            if (!resp.success) {
                log('Fehler: ' + (resp.data?.message || 'Unbekannt'), 'error');
                cleanupImport();
                return;
            }

            var d = resp.data;
            totalImported += d.imported;
            totalSkipped  += d.skipped;

            if (d.imported > 0 || d.skipped > 0) {
                log('Batch: ' + d.imported + ' importiert, ' + d.skipped + ' übersprungen (Zeile ' + offset + '-' + d.offset + ')', d.skipped > 0 ? 'warn' : 'ok');
            }

            if (d.done) {
                progress('Fertig!', 100);
                log('Import abgeschlossen: ' + totalImported + ' importiert, ' + totalSkipped + ' übersprungen.', 'ok');
                cleanupImport();
            } else {
                setTimeout(function() { runImportChunk(d.offset); }, 200);
            }
        }).fail(function(xhr) {
            log('HTTP Fehler: ' + xhr.status, 'error');
            cleanupImport();
        });
    }

    function cleanupImport() {
        $.post(AJAXURL, { action: 'naws_import_cleanup', nonce: NONCE });
    }
}

})(jQuery);
<?php
wp_add_inline_script( 'naws-admin', ob_get_clean() );
?>
