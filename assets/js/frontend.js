/**
 * Netatmo Weather Station Pro - Frontend JS
 */
(function($) {
    'use strict';

    // Guard: nawsFrontend must be injected by wp_localize_script
    if (typeof nawsFrontend === 'undefined') {
        console.warn('NAWS: nawsFrontend config not found. wp_localize_script may not have run.');
        return;
    }

    /* ============================================================
       Responsive chart font size helper
       ============================================================ */
    function nawsChartFontSize() {
        var w = window.innerWidth;
        return w < 480 ? 9 : w < 768 ? 10 : 11;
    }

    /* ============================================================
       Utility: AJAX with retry logic for transient network errors
       ============================================================ */
    function nawsAjaxPost(data, onSuccess, onFail, retries) {
        retries = (typeof retries === 'number') ? retries : 2;
        $.post(nawsFrontend.ajax_url, data, function(resp) {
            onSuccess(resp);
        }).fail(function(xhr) {
            if (retries > 0 && (xhr.status === 0 || xhr.status >= 500)) {
                // Retry with exponential backoff (500ms, 1500ms)
                var delay = (3 - retries) * 1000 + 500;
                setTimeout(function() {
                    nawsAjaxPost(data, onSuccess, onFail, retries - 1);
                }, delay);
            } else if (onFail) {
                onFail(xhr);
            }
        });
    }

    /**
     * Show a user-facing error message inside a chart wrapper.
     */
    function nawsShowError(wrapEl, message) {
        if (!wrapEl) return;
        // Remove any previous error/no-data message
        var old = wrapEl.querySelector('.naws-no-data-msg');
        if (old) old.remove();
        var msg = document.createElement('div');
        msg.className = 'naws-no-data-msg naws-error-msg';
        msg.textContent = message;
        wrapEl.appendChild(msg);
    }

    /* ============================================================
       Utility: Animated Number Counter
       ============================================================ */
    function animateNumber(el, end, duration) {
        const start = parseFloat(el.textContent) || 0;
        const range = end - start;
        const startTime = performance.now();
        const decimals = (String(end).split('.')[1] || '').length;

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = (start + range * eased).toFixed(decimals);
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    }

    /* ============================================================
       Intersection Observer: trigger animations on scroll
       ============================================================ */
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    const valueEl = card.querySelector('.naws-count-up');
                    if (valueEl && card.dataset.nawsAnimated !== '1') {
                        card.dataset.nawsAnimated = '1';
                        const val = parseFloat(valueEl.dataset.value || valueEl.textContent) || 0;
                        animateNumber(valueEl, val, 1200);
                    }
                    observer.unobserve(card);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.naws-card').forEach(c => observer.observe(c));
    }

    /* ============================================================
       Chart Builder
       ============================================================ */
    window.NAWS_Chart = function(config) {
        this.el          = document.getElementById(config.chartId);
        this.moduleId    = config.moduleId || '';
        this.parameters  = config.parameters || ['Temperature'];
        this.period      = config.period || '7d';
        this.chartType   = config.chartType || 'line';
        this.theme       = config.theme || 'dark';
        this.chart       = null;

        if (!this.el) return;

        this.loadingEl = this.el.closest('.naws-chart-canvas-wrap')?.querySelector('.naws-chart-loading');

        this.init();
    };

    window.NAWS_Chart.prototype = {
        init: function() {
            this.bindRangePicker();
            this.load();
        },

        bindRangePicker: function() {
            const self   = this;
            const wrap   = this.el.closest('.naws-chart-wrap');
            if (!wrap) return;

            wrap.querySelectorAll('.naws-range-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    wrap.querySelectorAll('.naws-range-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    self.period = this.dataset.period;
                    self.load();
                });
            });
        },

        getDateRange: function() {
            const now   = Math.floor(Date.now() / 1000);
            const map   = { '1d': 86400, '7d': 604800, '30d': 2592000, '90d': 7776000, '365d': 31536000 };
            const secs  = map[this.period] || 604800;
            return { date_from: now - secs, date_to: now };
        },

        getGroupBy: function() {
            const map = { '1d': 'raw', '7d': 'hour', '30d': 'day', '90d': 'day', '365d': 'day' };
            return map[this.period] || 'raw';
        },

        load: function() {
            const self = this;
            if (this.loadingEl) this.loadingEl.style.display = 'flex';

            // Remove previous error/no-data messages
            const wrap = this.el?.closest('.naws-chart-canvas-wrap');
            if (wrap) {
                var oldMsg = wrap.querySelector('.naws-no-data-msg');
                if (oldMsg) oldMsg.remove();
            }

            const range = this.getDateRange();

            nawsAjaxPost({
                action:    'naws_get_chart_data',
                nonce:     nawsFrontend.nonce,
                module_id: this.moduleId,
                parameter: this.parameters,
                date_from: range.date_from,
                date_to:   range.date_to,
                group_by:  this.getGroupBy()
            }, function(resp) {
                if (self.loadingEl) self.loadingEl.style.display = 'none';
                if (resp && resp.success && resp.data.datasets && resp.data.datasets.length > 0) {
                    try {
                        self.render(resp.data.datasets);
                    } catch (e) {
                        console.error('NAWS Chart render error:', e);
                        nawsShowError(wrap, 'Chart konnte nicht gerendert werden.');
                    }
                } else {
                    // Server returned an error response
                    if (resp && !resp.success && resp.data && resp.data.message) {
                        nawsShowError(wrap, resp.data.message);
                    } else {
                        nawsShowError(wrap, 'Keine Daten für diesen Zeitraum.');
                    }
                }
            }, function(xhr) {
                if (self.loadingEl) self.loadingEl.style.display = 'none';
                console.error('NAWS AJAX error:', xhr.status, xhr.responseText);
                nawsShowError(wrap, 'Daten konnten nicht geladen werden (HTTP ' + xhr.status + ')');
            });
        },

        render: function(datasets) {
            // Read chart theme colors from CSS custom properties
            var cs = getComputedStyle(this.wrap || document.documentElement);
            const gridColor  = cs.getPropertyValue('--naws-chart-grid').trim() || 'rgba(218,240,240,0.4)';
            const tickColor  = cs.getPropertyValue('--naws-chart-tick').trim() || '#7aa0a0';
            const tooltipBg  = cs.getPropertyValue('--naws-chart-tooltip-bg').trim() || 'rgba(45,82,82,0.92)';
            const tooltipClr = cs.getPropertyValue('--naws-chart-tooltip-text').trim() || '#ffffff';

            // Fill datasets for area chart
            if (this.chartType === 'area') {
                datasets = datasets.map(d => ({ ...d, fill: true, backgroundColor: d.borderColor + '20' }));
            }

            const config = {
                type: this.chartType === 'area' ? 'line' : this.chartType,
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                    },
                    plugins: {
                        legend: {
                            display: datasets.length > 1,
                            labels: {
                                color: tickColor,
                                usePointStyle: true,
                                pointStyleWidth: 10,
                                font: { size: nawsChartFontSize() }
                            }
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleColor: tooltipClr,
                            bodyColor: tooltipClr,
                            borderColor: gridColor,
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12,
                            callbacks: {
                                title: function(ctx) {
                                    const d = new Date(ctx[0].parsed.x);
                                    return d.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'dd.MM.yyyy HH:mm',
                                displayFormats: {
                                    minute: 'HH:mm', hour: 'dd.MM HH:mm',
                                    day: 'dd.MM.yyyy', week: 'dd.MM', month: 'MMM yyyy'
                                }
                            },
                            grid:  { color: gridColor },
                            ticks: { color: tickColor, maxRotation: 0, font: { size: nawsChartFontSize() } }
                        },
                        y: {
                            grid:  { color: gridColor },
                            ticks: { color: tickColor, font: { size: nawsChartFontSize() } },
                            beginAtZero: false
                        }
                    }
                }
            };

            try {
                if (this.chart) {
                    this.chart.data.datasets = datasets;
                    this.chart.update('active');
                } else {
                    this.chart = new Chart(this.el, config);
                }
            } catch (e) {
                console.error('NAWS Chart.js error:', e);
                nawsShowError(
                    this.el?.closest('.naws-chart-canvas-wrap'),
                    'Chart konnte nicht gerendert werden.'
                );
            }
        }
    };

    /* ============================================================
       Gauge Builder (using canvas fallback if gauge.js unavailable)
       ============================================================ */
    window.NAWS_Gauge = function(canvasId, value, min, max, theme) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        if (typeof Gauge !== 'undefined') {
            var gcs = getComputedStyle(canvas.closest('.naws-wrap') || document.documentElement);
            var gPrimary = gcs.getPropertyValue('--naws-primary').trim() || '#00d4ff';
            var gAccent  = gcs.getPropertyValue('--naws-accent').trim() || '#7c3aed';
            var gText    = gcs.getPropertyValue('--naws-text').trim() || '#427272';
            var gBorder  = gcs.getPropertyValue('--naws-border').trim() || '#e0eeee';
            const g = new Gauge(canvas).setOptions({
                angle:           -0.2,
                lineWidth:       0.2,
                radiusScale:     1,
                pointer:         { length: 0.6, strokeWidth: 0.035, color: gText },
                limitMax:        false,
                limitMin:        false,
                colorStart:      gPrimary,
                colorStop:       gAccent,
                strokeColor:     gBorder,
                generateGradient: true,
                highDpiSupport:  true,
            });
            g.maxValue        = max;
            g.minValue        = min;
            g.animationSpeed  = 32;
            g.set(value);
        } else {
            // Fallback: draw arc manually
            NAWS_DrawGaugeFallback(canvas, value, min, max, theme);
        }
    };

    function NAWS_DrawGaugeFallback(canvas, value, min, max, theme) {
        const ctx     = canvas.getContext('2d');
        const w       = canvas.width || 200;
        const h       = canvas.height || 120;
        const cx      = w / 2, cy = h * 0.85;
        const r       = Math.min(w * 0.42, h * 0.75);
        const start   = Math.PI, end = 2 * Math.PI;
        const pct     = Math.max(0, Math.min(1, (value - min) / (max - min)));
        const angle   = start + pct * Math.PI;

        var gcs = getComputedStyle(canvas.closest('.naws-wrap') || document.documentElement);
        var gPrimary = gcs.getPropertyValue('--naws-primary').trim() || '#00d4ff';
        var gAccent  = gcs.getPropertyValue('--naws-accent').trim() || '#7c3aed';
        var gText    = gcs.getPropertyValue('--naws-text').trim() || '#427272';
        var gBorder  = gcs.getPropertyValue('--naws-border').trim() || '#e0eeee';

        ctx.clearRect(0, 0, w, h);

        // Background arc
        ctx.beginPath();
        ctx.arc(cx, cy, r, start, end);
        ctx.strokeStyle = gBorder;
        ctx.lineWidth   = r * 0.22;
        ctx.lineCap     = 'round';
        ctx.stroke();

        // Value arc
        const grad = ctx.createLinearGradient(cx - r, cy, cx + r, cy);
        grad.addColorStop(0, gPrimary);
        grad.addColorStop(1, gAccent);
        ctx.beginPath();
        ctx.arc(cx, cy, r, start, angle);
        ctx.strokeStyle = grad;
        ctx.stroke();

        // Needle
        const nx = cx + (r * 0.75) * Math.cos(angle);
        const ny = cy + (r * 0.75) * Math.sin(angle);
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(nx, ny);
        ctx.strokeStyle = gText;
        ctx.lineWidth   = 3;
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(cx, cy, 5, 0, 2 * Math.PI);
        ctx.fillStyle = gPrimary;
        ctx.fill();
    }

    /* ============================================================
       Dashboard Tabs
       ============================================================ */
    $(document).on('click', '.naws-tab-btn', function() {
        const tabId = $(this).data('tab');
        const parent = $(this).closest('.naws-wrap');
        parent.find('.naws-tab-btn').removeClass('active');
        parent.find('.naws-tab-content').removeClass('active');
        $(this).addClass('active');
        parent.find('#' + tabId).addClass('active');
    });

    /* ============================================================
       Accordion
       ============================================================ */
    $(document).on('click', '.naws-accordion-header', function() {
        const $item = $(this).closest('.naws-accordion-item');
        const wasOpen = $item.hasClass('is-open');

        // Optionally close siblings (uncomment for "only one open at a time"):
        // $item.siblings('.naws-accordion-item').removeClass('is-open')
        //      .find('.naws-accordion-header').attr('aria-expanded','false');

        $item.toggleClass('is-open', !wasOpen);
        $(this).attr('aria-expanded', wasOpen ? 'false' : 'true');

        // Animate count-up values when opened
        if (!wasOpen) {
            $item.find('.naws-count-up').each(function() {
                const el  = this;
                const val = parseFloat(el.dataset.value || el.textContent) || 0;
                if (el.dataset.nawsAnimated !== '1') {
                    el.dataset.nawsAnimated = '1';
                    animateNumber(el, val, 900);
                }
            });
        }
    });

    /* ============================================================
       Battery fill widths
       ============================================================ */
    document.querySelectorAll('.naws-battery-fill').forEach(el => {
        const pct = parseFloat(el.dataset.pct || 0);
        el.style.width = pct + '%';
        if (pct < 20) el.style.background = '#ef4444';
        else if (pct < 50) el.style.background = '#f59e0b';
    });

    /* ============================================================
       Wind compass rotation
       ============================================================ */
    document.querySelectorAll('.naws-compass-needle').forEach(el => {
        const angle = parseFloat(el.dataset.angle || 0);
        el.style.setProperty('--naws-wind-angle', angle + 'deg');
    });

})(jQuery);

/* ============================================================
   Year-comparison chart helper
   Loads each year as a separate dataset so lines overlay for comparison
   ============================================================ */
window.NAWS_YearCompare = function(config) {
    this.el         = document.getElementById(config.chartId);
    this.moduleId   = config.moduleId || '';
    this.parameter  = config.parameter || 'Temperature';
    this.years      = config.years || [];     // e.g. [2022,2023,2024]
    this.chartType  = config.chartType || 'line';
    this.theme      = config.theme || 'dark';
    this.chart      = null;
    this.loadingEl  = this.el?.closest('.naws-chart-canvas-wrap')?.querySelector('.naws-chart-loading');

    if (this.el) this.load();
};

window.NAWS_YearCompare.prototype = {
    load: function() {
        const self = this;
        if (!this.el) return;
        if (this.loadingEl) this.loadingEl.style.display = 'flex';

        // If no years specified, auto-detect from data range
        if (!this.years.length) {
            this.years = [];
            const currentYear = new Date().getFullYear();
            for (let y = currentYear - 4; y <= currentYear; y++) this.years.push(y);
        }

        const promises = this.years.map(year => {
            const from = Math.floor(new Date(year + '-01-01').getTime() / 1000);
            const to   = Math.floor(new Date(year + '-12-31 23:59:59').getTime() / 1000);

            return new Promise(resolve => {
                $.post(nawsFrontend.ajax_url, {
                    action:    'naws_get_chart_data',
                    nonce:     nawsFrontend.nonce,
                    module_id: self.moduleId,
                    parameter: [self.parameter],
                    date_from: from,
                    date_to:   to,
                    group_by:  'day'
                }, function(resp) {
                    resolve({ year, data: resp.success ? (resp.data.datasets[0]?.data || []) : [] });
                }).fail(() => resolve({ year, data: [] }));
            });
        });

        Promise.all(promises).then(results => {
            if (self.loadingEl) self.loadingEl.style.display = 'none';
            self.render(results);
        });
    },

    render: function(yearData) {
        var ycs = getComputedStyle(this.wrap || document.documentElement);
        const gridClr  = ycs.getPropertyValue('--naws-chart-grid').trim() || 'rgba(218,240,240,0.4)';
        const tickClr  = ycs.getPropertyValue('--naws-chart-tick').trim() || '#7aa0a0';
        const palette  = ['#00d4ff','#f59e0b','#10b981','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316'];

        // Normalise all points to a "day of year" x-axis (0..364)
        // so different years line up
        const datasets = yearData
            .filter(yd => yd.data.length > 0)
            .map((yd, i) => {
                const normalised = yd.data.map(pt => {
                    const d   = new Date(pt.x);
                    const jan = new Date(yd.year + '-01-01');
                    const doy = Math.round((d - jan) / 86400000); // day of year
                    return { x: doy, y: pt.y };
                });
                const color = palette[i % palette.length];
                return {
                    label:           String(yd.year),
                    data:            normalised,
                    borderColor:     color,
                    backgroundColor: color + '22',
                    borderWidth:     2,
                    pointRadius:     0,
                    tension:         0.4,
                    fill:            false,
                };
            });

        const config = {
            type: 'line',
            data: { datasets },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 600 },
                plugins: {
                    legend: {
                        display: true,
                        labels: { color: tickClr, usePointStyle: true, font: { size: nawsChartFontSize() } }
                    },
                    tooltip: {
                        backgroundColor: ycs.getPropertyValue('--naws-chart-tooltip-bg').trim() || 'rgba(45,82,82,0.92)',
                        titleColor: ycs.getPropertyValue('--naws-chart-tooltip-title').trim() || '#a0c8c8',
                        bodyColor:  ycs.getPropertyValue('--naws-chart-tooltip-text').trim() || '#ffffff',
                        callbacks: {
                            title: ctx => {
                                const doy = ctx[0].parsed.x;
                                // Convert day-of-year to a month/day label
                                const ref = new Date(2000, 0, 1 + doy);
                                return ref.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        min: 0, max: 364,
                        grid:  { color: gridClr },
                        ticks: {
                            color: tickClr,
                            maxTicksLimit: 12,
                            callback: val => {
                                const ref = new Date(2000, 0, 1 + val);
                                return ref.toLocaleDateString(undefined, { month: 'short' });
                            }
                        }
                    },
                    y: {
                        grid:  { color: gridClr },
                        ticks: { color: tickClr }
                    }
                }
            }
        };

        try {
            if (this.chart) {
                this.chart.data.datasets = datasets;
                this.chart.update('active');
            } else {
                this.chart = new Chart(this.el, config);
            }
        } catch (e) {
            console.error('NAWS YearCompare Chart.js error:', e);
            var wrap = this.el?.closest('.naws-chart-canvas-wrap');
            if (wrap) {
                var msg = document.createElement('div');
                msg.className = 'naws-no-data-msg naws-error-msg';
                msg.textContent = 'Chart konnte nicht gerendert werden.';
                wrap.appendChild(msg);
            }
        }
    }
};
