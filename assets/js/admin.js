/**
 * Netatmo Weather Station Pro - Admin JS
 */
(function($) {
    'use strict';

    // Color pickers
    $(document).ready(function() {

        // Toggle API secret visibility
        $(document).on('click', '#naws-toggle-secret', function() {
            const field = $('#naws-client-secret');
            const type  = field.attr('type') === 'password' ? 'text' : 'password';
            field.attr('type', type);
            $(this).text(type === 'password' ? 'Show' : 'Hide');
        });

        // AJAX Sync Now button (if present in dashboard)
        $(document).on('click', '#naws-ajax-sync', function() {
            const btn = $(this);
            btn.prop('disabled', true).text(nawsAdmin.strings.syncing);

            $.post(nawsAdmin.ajax_url, {
                action: 'naws_sync_now',
                nonce:  nawsAdmin.nonce
            }, function(resp) {
                if (resp.success) {
                    btn.text(nawsAdmin.strings.sync_done);
                    setTimeout(() => btn.prop('disabled', false).text('🔄 Sync Now'), 3000);
                    showNotice(resp.data.message, 'success');
                } else {
                    showNotice(resp.data?.message || nawsAdmin.strings.error, 'error');
                    btn.prop('disabled', false).text('🔄 Sync Now');
                }
            }).fail(function() {
                showNotice(nawsAdmin.strings.error, 'error');
                btn.prop('disabled', false).text('🔄 Sync Now');
            });
        });

        // Module select -> show matching device in import
        $('#naws-import-module').on('change', function() {
            const station = $(this).find(':selected').data('station') || '';
            if (station) $('#naws-import-device').val(station);
        });
    });

    function showNotice(message, type) {
        const cls   = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $(`<div class="notice ${cls} is-dismissible"><p>${message}</p></div>`);
        $('.naws-admin-wrap h1').after(notice);
        setTimeout(() => notice.fadeOut(() => notice.remove()), 5000);
    }

})(jQuery);

    // Manual purge button in settings
    $(document).on('click', '#naws-purge-btn', function() {
        const days = parseInt($('#naws-purge-days').val(), 10);
        if (!days || days < 30) { alert('Bitte mindestens 30 Tage eingeben.'); return; }
        if (!confirm(`Wirklich alle Daten löschen, die älter als ${days} Tage sind? Diese Aktion kann nicht rückgängig gemacht werden!`)) return;

        const btn = $(this).prop('disabled', true).text('...');
        $.post(nawsAdmin.ajax_url, {
            action: 'naws_delete_readings',
            nonce:  nawsAdmin.nonce,
            days:   days
        }, function(resp) {
            btn.prop('disabled', false).text('Jetzt bereinigen');
            if (resp.success) {
                $('#naws-purge-result').css('color', '#10b981').text(`✅ ${resp.data.deleted} Einträge gelöscht.`);
            } else {
                $('#naws-purge-result').css('color', '#ef4444').text('Fehler: ' + (resp.data?.message || '?'));
            }
        });
    });

    // Manual daily summary trigger
    $(document).on('click', '#naws-run-daily-btn', function() {
        const btn  = $(this).prop('disabled', true).text('...');
        const date = $('#naws-daily-date').val();
        $.post(nawsAdmin.ajax_url, {
            action: 'naws_run_daily_summary',
            nonce:  nawsAdmin.nonce,
            date:   date
        }, function(resp) {
            btn.prop('disabled', false).text('Jetzt berechnen');
            if (resp.success) {
                $('#naws-daily-result').css('color','#10b981').text('✅ ' + resp.data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                $('#naws-daily-result').css('color','#ef4444').text('❌ Fehler');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('Jetzt berechnen');
            $('#naws-daily-result').css('color','#ef4444').text('❌ Request fehlgeschlagen');
        });
    });
