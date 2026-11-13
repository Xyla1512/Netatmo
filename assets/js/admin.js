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

        // Automatic deletion in settings: switching it on asks once, and says
        // what is gone for good and which shortcodes lose their reach. The
        // sentence comes from PHP like every other. "No" puts the box back.
        $(document).on('change', 'input[name="naws_settings[retention_enabled]"]', function() {
            if (this.checked && !confirm(nawsAdmin.strings.retention_confirm)) {
                this.checked = false;
            }
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

        // Settings → Rain radar → Clear storage (since 2.1.3).
        $(document).on('click', '#naws-radar-clear', function() {
            if (!confirm(nawsAdmin.strings.radar_clear_confirm)) return;
            const btn    = $(this);
            const result = $('#naws-radar-clear-result');
            btn.prop('disabled', true);
            $.post(nawsAdmin.ajax_url, { action: 'naws_radar_clear', nonce: nawsAdmin.nonce }, function(resp) {
                btn.prop('disabled', false);
                if (resp.success) {
                    result.css('color', '#10b981').text(nawsAdmin.strings.radar_cleared);
                    $('.naws-radar-sets').remove();
                } else {
                    result.css('color', '#ef4444').text(resp.data?.message || nawsAdmin.strings.error);
                }
            }).fail(function() {
                btn.prop('disabled', false);
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


    /*
     * [naws_compare] builder on the Shortcodes page: two module/quantity pickers,
     * the period, title and height turn into a shortcode to copy, and a preview
     * drawn by compare-boot.js (window.NAWS_compareInit) from the same HTML the
     * shortcode gives. Since 2.2.0.
     */
    (function () {
        'use strict';
        var root = document.querySelector('.naws-cmp-builder');
        var catEl = document.getElementById('naws-cmp-catalogue');
        if (!root || !catEl || typeof nawsAdmin === 'undefined') { return; }
        var cat;
        try { cat = JSON.parse(catEl.textContent) || []; } catch (e) { return; }
        if (!cat.length) { return; }
        var S = nawsAdmin.strings || {};
        var code = root.querySelector('.naws-cmp-code');
        var copy = root.querySelector('.naws-cmp-copy');
        var preview = root.querySelector('.naws-cmp-preview');
        var bOff = root.querySelector('.naws-cmp-b-off');
        var timer = null;
        var seq = 0;

        function rowOf(key) { return root.querySelector('.naws-cmp-row[data-series="' + key + '"]'); }
        function mod(ref) { for (var i = 0; i < cat.length; i++) { if (cat[i].ref === ref) { return cat[i]; } } return null; }

        function fillQty(key, wanted) {
            var row = rowOf(key);
            var m = mod(row.querySelector('.naws-cmp-module').value);
            var sel = row.querySelector('.naws-cmp-qty');
            sel.innerHTML = '';
            if (!m) { return; }
            var picked = false;
            m.qtys.forEach(function (q) {
                var o = document.createElement('option');
                o.value = q.qty;
                o.textContent = q.label + (q.state === 'sparse' ? ' (' + (S.cmp_sparse || 'patchy') + ')' : q.state === 'empty' ? ' (' + (S.cmp_empty || 'no data') + ')' : '');
                o.disabled = q.state === 'empty';
                if (!picked && !o.disabled && (wanted === undefined || q.qty === wanted)) { o.selected = true; picked = true; }
                sel.appendChild(o);
            });
            if (!picked) {
                var first = sel.querySelector('option:not([disabled])');
                if (first) { first.selected = true; }
            }
        }

        function fillModules(key, ref, qty) {
            var sel = rowOf(key).querySelector('.naws-cmp-module');
            cat.forEach(function (m) {
                var o = document.createElement('option');
                o.value = m.ref;
                o.textContent = m.name;
                sel.appendChild(o);
            });
            if (mod(ref)) { sel.value = ref; }
            fillQty(key, qty);
        }

        function spec(key) {
            var row = rowOf(key);
            return row.querySelector('.naws-cmp-module').value + ':' + row.querySelector('.naws-cmp-qty').value;
        }

        function atts() {
            var period = (root.querySelector('input[name="naws-cmp-period"]:checked') || {}).value || 'all';
            var a = { a: spec('a'), b: bOff.checked ? '' : spec('b') };
            var from = root.querySelector('.naws-cmp-from').value;
            var months = parseInt(root.querySelector('.naws-cmp-months').value, 10);
            if (period === 'from' && /^\d{4}-\d{2}$/.test(from)) { a.from = from; }
            if (period === 'months' && months > 0) { a.months = String(Math.min(120, months)); }
            var title = root.querySelector('.naws-cmp-title-in').value.trim();
            if (title !== '') { a.title = title; }
            var height = parseInt(root.querySelector('.naws-cmp-height').value, 10);
            if (height && height !== 360) { a.height = String(Math.max(200, Math.min(900, height))); }
            return a;
        }

        function shortcode(a) {
            var out = '[naws_compare';
            Object.keys(a).forEach(function (k) { out += ' ' + k + '="' + String(a[k]).replace(/[\[\]"]/g, '') + '"'; });
            return out + ']';
        }

        function refreshPreview(a) {
            var mine = ++seq;
            var body = new URLSearchParams({ action: 'naws_compare_preview', nonce: nawsAdmin.nonce });
            ['a', 'b', 'from', 'to', 'months', 'title', 'height'].forEach(function (k) { body.append(k, a[k] !== undefined ? a[k] : ''); });
            preview.setAttribute('aria-busy', 'true');
            fetch(nawsAdmin.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (mine !== seq) { return; }
                    var old = preview.querySelector('canvas');
                    if (old && window.Chart && window.Chart.getChart(old)) { window.Chart.getChart(old).destroy(); }
                    preview.innerHTML = (j && j.success && j.data) ? j.data.html : '';
                    var fig = preview.querySelector('figure[data-naws-cmp]');
                    if (fig && typeof window.NAWS_compareInit === 'function') { window.NAWS_compareInit(fig); }
                })
                .catch(function () { if (mine === seq) { preview.textContent = S.request_failed || ''; } })
                .then(function () { if (mine === seq) { preview.removeAttribute('aria-busy'); } });
        }

        function update() {
            rowOf('b').querySelectorAll('select').forEach(function (s) { s.disabled = bOff.checked; });
            var a = atts();
            var sc = shortcode(a);
            code.textContent = sc;
            copy.dataset.copy = sc;
            clearTimeout(timer);
            timer = setTimeout(function () { refreshPreview(a); }, 400);
        }

        var hasOutdoor = !!mod('outdoor');
        fillModules('a', 'indoor', 'humidity');
        fillModules('b', hasOutdoor ? 'outdoor' : cat[0].ref, 'humidity');
        ['a', 'b'].forEach(function (k) {
            rowOf(k).querySelector('.naws-cmp-module').addEventListener('change', function () { fillQty(k); update(); });
        });
        root.querySelectorAll('.naws-cmp-form input, .naws-cmp-form select').forEach(function (el) {
            el.addEventListener('input', update);
            el.addEventListener('change', update);
        });
        update();
    })();

})(jQuery);
