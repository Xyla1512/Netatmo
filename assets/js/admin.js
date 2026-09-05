/**
 * XTX Netatmo - Admin JS
 *
 * One block, one jQuery. Everything below lives inside the IIFE: from 1.6.4
 * to 1.9.10 two handlers stood behind its closing line, where $ is not
 * defined, and the file died on every admin page with "$ is not a function"
 * before either of them was registered — the purge button in the settings
 * did nothing for four minor versions.
 *
 * No user-facing text is written here. Every sentence comes from
 * nawsAdmin.strings, filled by wp_localize_script() in NAWS_Admin, so it is
 * translated like the rest of the plugin. A button's own label is read from
 * the button before it is replaced and put back afterwards, rather than
 * typed a second time in one language.
 */
(function($) {
    'use strict';

    // %d in a translated sentence, filled the way sprintf would.
    function fill(text, n) {
        return String(text).replace('%d', n);
    }

    $(document).ready(function() {

        // Toggle API secret visibility
        $(document).on('click', '#naws-toggle-secret', function() {
            const field = $('#naws-client-secret');
            const type  = field.attr('type') === 'password' ? 'text' : 'password';
            field.attr('type', type);
            $(this).text(type === 'password' ? nawsAdmin.strings.show : nawsAdmin.strings.hide);
        });

        // AJAX Sync Now button (if present in dashboard)
        $(document).on('click', '#naws-ajax-sync', function() {
            const btn   = $(this);
            const label = btn.text();
            btn.prop('disabled', true).text(nawsAdmin.strings.syncing);

            $.post(nawsAdmin.ajax_url, {
                action: 'naws_sync_now',
                nonce:  nawsAdmin.nonce
            }, function(resp) {
                if (resp.success) {
                    btn.text(nawsAdmin.strings.sync_done);
                    setTimeout(() => btn.prop('disabled', false).text(label), 3000);
                    showNotice(resp.data.message, 'success');
                } else {
                    showNotice(resp.data?.message || nawsAdmin.strings.error, 'error');
                    btn.prop('disabled', false).text(label);
                }
            }).fail(function() {
                showNotice(nawsAdmin.strings.error, 'error');
                btn.prop('disabled', false).text(label);
            });
        });

        // Module select -> show matching device in import
        $('#naws-import-module').on('change', function() {
            const station = $(this).find(':selected').data('station') || '';
            if (station) $('#naws-import-device').val(station);
        });

        // Manual purge button in settings. The daily-summary button has its
        // own handler in admin/views/dashboard.php and is not repeated here.
        $(document).on('click', '#naws-purge-btn', function() {
            const days = parseInt($('#naws-purge-days').val(), 10);
            if (!days || days < 30) { alert(nawsAdmin.strings.purge_min_days); return; }
            if (!confirm(fill(nawsAdmin.strings.purge_confirm, days))) return;

            const btn    = $(this);
            const label  = btn.text();
            const result = $('#naws-purge-result');
            btn.prop('disabled', true).text('…');
            $.post(nawsAdmin.ajax_url, {
                action: 'naws_delete_readings',
                nonce:  nawsAdmin.nonce,
                days:   days
            }, function(resp) {
                btn.prop('disabled', false).text(label);
                if (resp.success) {
                    result.css('color', '#10b981').text(fill(nawsAdmin.strings.purge_done, resp.data.deleted));
                } else {
                    result.css('color', '#ef4444').text(resp.data?.message || nawsAdmin.strings.error);
                }
            }).fail(function() {
                btn.prop('disabled', false).text(label);
                result.css('color', '#ef4444').text(nawsAdmin.strings.request_failed);
            });
        });
    });

    function showNotice(message, type) {
        const cls   = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $(`<div class="notice ${cls} is-dismissible"><p>${message}</p></div>`);
        $('.naws-admin-wrap h1').after(notice);
        setTimeout(() => notice.fadeOut(() => notice.remove()), 5000);
    }

})(jQuery);
