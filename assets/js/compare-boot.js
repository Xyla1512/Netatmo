/**
 * [naws_compare]: draws two daily series on one time axis with Chart.js.
 *
 * The data come from the page itself (script.naws-cmp-data, JSON written by
 * NAWS_Compare::render()); nothing is fetched. The same function draws the
 * preview of the shortcode builder in the admin, where frontend.js and its
 * reveal-in-view helpers are not loaded.
 *
 * @package NAWS
 * @since   2.2.0
 */
(function () {
    'use strict';

    function readData(root) {
        var el = root.querySelector('script.naws-cmp-data');
        if (!el) { return null; }
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }

    function fmt(locale, decimals) {
        try {
            return new Intl.NumberFormat(locale, { minimumFractionDigits: 0, maximumFractionDigits: decimals });
        } catch (e) {
            return { format: function (v) { return String(v); } };
        }
    }

    function dateFmt(locale, opts) {
        try { return new Intl.DateTimeFormat(locale, opts); } catch (e) { return new Intl.DateTimeFormat(undefined, opts); }
    }

    function config(d) {
        var theme = d.theme || {};
        var datasets = d.series.map(function (s, i) {
            var c = d.colors[i] || d.colors[0];
            return {
                type: s.chart === 'bar' ? 'bar' : 'line',
                label: s.name,
                data: s.values,
                yAxisID: s.axis,
                borderColor: c,
                backgroundColor: s.chart === 'bar' ? c + '99' : c,
                borderWidth: s.chart === 'bar' ? 0 : 1.6,
                pointRadius: 0,
                pointHoverRadius: 4,
                spanGaps: false,
                tension: 0.15,
                order: s.chart === 'bar' ? 2 : 1,
                naws: { unit: s.unit, nf: fmt(d.locale, s.decimals) }
            };
        });

        function axis(id, s, i, right) {
            var c = d.colors[i] || d.colors[0];
            return {
                type: 'linear',
                position: right ? 'right' : 'left',
                beginAtZero: s.chart === 'bar',
                grid: { color: theme.grid || 'rgba(0,0,0,.06)', drawOnChartArea: !right },
                ticks: {
                    color: d.right ? c : (theme.tick || '#777'),
                    callback: function (v) { return fmt(d.locale, s.decimals).format(v); }
                },
                title: { display: true, text: s.unit, color: d.right ? c : (theme.axis_title || '#555') }
            };
        }

        var scales = {
            x: {
                type: 'time',
                time: { unit: d.dates.length > 120 ? 'month' : 'day' },
                grid: { color: theme.grid || 'rgba(0,0,0,.06)' },
                ticks: {
                    color: theme.tick || '#777', maxRotation: 0, autoSkip: true,
                    // Month names in the site's language, not the adapter's English.
                    callback: function (v) { return dateFmt(d.locale, d.dates.length > 120 ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' }).format(new Date(v)); }
                }
            },
            y: axis('y', d.series[0], 0, false)
        };
        if (d.right && d.series[1]) {
            scales.y1 = axis('y1', d.series[1], 1, true);
        } else if (d.series[1] && d.series[1].chart === 'bar') {
            scales.y.beginAtZero = true;
        }

        return {
            data: { labels: d.dates, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                locale: d.locale,
                scales: scales,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, boxHeight: 12 } },
                    tooltip: {
                        backgroundColor: theme.tooltip_bg || undefined,
                        titleColor: theme.tooltip_title || undefined,
                        bodyColor: theme.tooltip_text || undefined,
                        callbacks: {
                            title: function (items) {
                                if (!items.length) { return ''; }
                                return dateFmt(d.locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(items[0].parsed.x));
                            },
                            label: function (ctx) {
                                var n = ctx.dataset.naws;
                                if (ctx.parsed.y === null || ctx.parsed.y === undefined) { return null; }
                                return ctx.dataset.label + ': ' + n.nf.format(ctx.parsed.y) + ' ' + n.unit;
                            }
                        }
                    }
                }
            }
        };
    }

    function draw(canvas, d) {
        var cfg = config(d);
        return new window.Chart(canvas.getContext('2d'), { type: 'line', data: cfg.data, options: cfg.options });
    }

    function openDialog(root, d) {
        var dlg = document.createElement('dialog');
        dlg.className = 'naws-cmp-dialog';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'naws-cmp-close';
        close.setAttribute('aria-label', (d.labels && d.labels.close) || 'Close');
        close.textContent = '×';
        var box = document.createElement('div');
        box.className = 'naws-cmp-dialog-canvas';
        var canvas = document.createElement('canvas');
        box.appendChild(canvas);
        var title = root.querySelector('.naws-cmp-title');
        if (title && title.textContent.trim() !== '') {
            var h = document.createElement('div');
            h.className = 'naws-cmp-dialog-title';
            h.textContent = title.textContent;
            dlg.appendChild(h);
        }
        dlg.appendChild(close);
        dlg.appendChild(box);
        document.body.appendChild(dlg);
        var chart = null;
        function done() { if (chart) { chart.destroy(); } dlg.remove(); }
        close.addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('close', done);
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        chart = draw(canvas, d);
    }

    function init(root) {
        if (!root || root._nawsCmp || !window.Chart) { return; }
        var d = readData(root);
        var canvas = root.querySelector('canvas');
        if (!d || !canvas || !d.series || !d.series.length) { return; }
        root._nawsCmp = true;

        var btn = root.querySelector('.naws-cmp-expand');
        if (btn) { btn.addEventListener('click', function () { openDialog(root, d); }); }

        var run = function () { draw(canvas, d); };
        if (typeof window.NAWS_whenInView === 'function' && !root.closest('.wp-admin, #wpbody')) {
            window.NAWS_whenInView(root, run);
        } else {
            run();
        }
    }

    window.NAWS_compareInit = init;

    function boot() {
        document.querySelectorAll('figure[data-naws-cmp]').forEach(init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
