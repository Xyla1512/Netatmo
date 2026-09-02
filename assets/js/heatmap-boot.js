/**
 * [naws_heatmap] — Tooltip, Jahreswechsel, Aufbau.
 *
 * Das Skript rechnet nichts. Farben und Beschriftungen kommen fertig vom
 * Server, hier werden sie gesetzt. So sieht ein nachgeladenes Jahr genau
 * so aus wie das serverseitig gerenderte, ohne dass die Farbrechnung ein
 * zweites Mal existiert.
 *
 * @package NAWS
 */
(function () {
    'use strict';

    var CFG = window.nawsFrontend || {};
    var I18N = CFG.i18n || {};

    function loadFailedText(status) {
        var tpl = I18N.js_load_failed || 'Could not load data (HTTP %s)';
        // fetch() rejects outright on a network failure (offline, DNS) with
        // the browser's own message text rather than a status code. Fall
        // back to 0, the XHR convention for "no HTTP status", instead of
        // splicing that text into the sentence.
        var code = /^\d+$/.test(String(status)) ? status : '0';
        return tpl.replace('%s', code);
    }

    /** Die Verzoegerung je Kachel: eine Welle von links nach rechts. */
    function stagger(root) {
        var cells = root.querySelectorAll('.naws-hm-c');
        for (var i = 0; i < cells.length; i++) {
            var day = parseInt(cells[i].getAttribute('data-day') || '1', 10);
            cells[i].style.setProperty('--naws-hm-d', ((day - 1) * 22) + 'ms');
        }
        root.classList.add('is-animating');
    }

    function makeTip(root) {
        var tip = document.createElement('div');
        tip.className = 'naws-hm-tip';
        tip.style.display = 'none';
        root.appendChild(tip);
        return tip;
    }

    function bindTip(root, tip) {
        root.addEventListener('mouseover', function (e) {
            var cell = e.target.closest ? e.target.closest('.naws-hm-c') : null;
            if (!cell || !root.contains(cell)) { return; }
            var date = cell.getAttribute('data-d') || '';
            var label = cell.getAttribute('data-l') || '';
            tip.textContent = date + ' — ' + label;
            tip.style.display = '';
            var box = cell.getBoundingClientRect();
            var host = root.getBoundingClientRect();
            tip.style.left = (box.left - host.left + box.width / 2) + 'px';
            tip.style.top = (box.top - host.top - 6) + 'px';
        });

        root.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    }

    /** Traegt ein geholtes Jahr in die vorhandenen Kacheln ein. */
    function paint(root, data) {
        var rows = root.querySelectorAll('.naws-hm-row');
        for (var m = 0; m < rows.length; m++) {
            var cells = rows[m].querySelectorAll('.naws-hm-c, .naws-hm-x');
            var vals = (data.months && data.months[m]) || [];
            var cols = (data.colors && data.colors[m]) || [];
            var labs = (data.labels && data.labels[m]) || [];
            var srcs = (data.sources && data.sources[m]) || [];

            for (var d = 0; d < cells.length; d++) {
                var cell = cells[d];
                var exists = d < vals.length;

                cell.className = exists ? 'naws-hm-c' : 'naws-hm-x';
                if (!exists) {
                    cell.removeAttribute('style');
                    cell.setAttribute('aria-hidden', 'true');
                    cell.removeAttribute('data-d');
                    cell.removeAttribute('data-day');
                    cell.removeAttribute('data-v');
                    cell.removeAttribute('data-l');
                    cell.removeAttribute('data-src');
                    cell.textContent = '';
                    continue;
                }

                cell.removeAttribute('aria-hidden');
                cell.style.background = cols[d] || '';
                cell.setAttribute('data-day', String(d + 1));
                cell.setAttribute('data-v', vals[d] === null ? '' : String(vals[d]));
                cell.setAttribute('data-l', labs[d] || '');
                cell.setAttribute('data-src', srcs[d] || '');
                cell.setAttribute(
                    'data-d',
                    data.year + '-' + ('0' + (m + 1)).slice(-2) + '-' + ('0' + (d + 1)).slice(-2)
                );

                var sr = cell.querySelector('.screen-reader-text');
                if (!sr) {
                    sr = document.createElement('span');
                    sr.className = 'screen-reader-text';
                    cell.appendChild(sr);
                }
                sr.textContent = labs[d] || '';
            }
        }
        root.setAttribute('data-year', String(data.year));
    }

    function bindYears(root) {
        var cache = {};
        var buttons = root.querySelectorAll('.naws-hm-year');

        function activate(year) {
            for (var i = 0; i < buttons.length; i++) {
                var on = buttons[i].getAttribute('data-year') === String(year);
                buttons[i].classList.toggle('is-active', on);
                // Die Chart-Pillen blenden den abgeschalteten Zustand aus, statt ihn
                // umzufaerben — .naws-leg-pill.hidden macht das.
                buttons[i].classList.toggle('hidden', !on);
            }
        }

        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', function () {
                var year = this.getAttribute('data-year');
                if (!year || root.getAttribute('data-year') === year) { return; }

                // Der Aufbau laeuft genau einmal. Ein Jahreswechsel blendet
                // um, statt dieselbe Welle ein drittes Mal zu zeigen.
                root.classList.remove('is-animating');
                activate(year);

                if (cache[year]) { paint(root, cache[year]); return; }

                var body = new URLSearchParams();
                body.append('action', 'naws_get_heatmap_data');
                body.append('nonce', root.getAttribute('data-nonce') || '');
                body.append('year', year);

                var empty = root.querySelector('.naws-hm-empty');
                if (empty) { empty.textContent = ''; }

                fetch(root.getAttribute('data-ajax'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                    .then(function (res) {
                        if (!res.ok) { throw new Error(String(res.status)); }
                        return res.json();
                    })
                    .then(function (json) {
                        if (!json || !json.success || !json.data) { throw new Error('0'); }
                        cache[year] = json.data;
                        paint(root, json.data);
                    })
                    .catch(function (err) {
                        activate(root.getAttribute('data-year'));
                        var box = root.querySelector('.naws-hm-empty');
                        if (!box) {
                            box = document.createElement('div');
                            box.className = 'naws-hm-empty';
                            root.appendChild(box);
                        }
                        box.textContent = loadFailedText(err.message || '0');
                    });
            });
        }
    }

    function boot() {
        var maps = document.querySelectorAll('.naws-hm');
        for (var i = 0; i < maps.length; i++) {
            var root = maps[i];
            if (root.getAttribute('data-naws-booted')) { continue; }
            root.setAttribute('data-naws-booted', '1');
            bindTip(root, makeTip(root));
            bindYears(root);
            stagger(root);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
