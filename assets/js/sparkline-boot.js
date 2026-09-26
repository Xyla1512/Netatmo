/**
 * [naws_sparkline] — the hover bubble.
 *
 * The curves are complete without this file. Every <svg data-naws-sl>
 * carries the x position of each point, in viewBox units, and the text for
 * it, formatted on the server in the site's language and units; this only
 * finds the point nearest the pointer and shows its text. One bubble and
 * three listeners serve every sparkline on the page, however many there are.
 *
 * Mouse: the bubble follows the pointer and goes when it leaves the curve.
 * Touch: a tap shows it, a tap anywhere else or a scroll hides it.
 *
 * @package NAWS
 */
(function () {
    'use strict';

    var tip = null;
    var cache = typeof WeakMap === 'function' ? new WeakMap() : null;

    function data(svg) {
        var d = cache ? cache.get(svg) : null;
        if (d) { return d; }
        try {
            d = JSON.parse(svg.getAttribute('data-naws-sl') || '{}');
        } catch (e) {
            d = {};
        }
        if (!d || !d.x || !d.t) { d = { x: [], t: [] }; }
        if (cache) { cache.set(svg, d); }
        return d;
    }

    function bubble() {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'naws-sl-tip';
            tip.setAttribute('role', 'tooltip');
            tip.hidden = true;
            document.body.appendChild(tip);
        }
        return tip;
    }

    function hide() {
        if (tip) { tip.hidden = true; }
    }

    function show(svg, clientX) {
        var d = data(svg);
        var vb = svg.viewBox && svg.viewBox.baseVal ? svg.viewBox.baseVal.width : 0;
        var r = svg.getBoundingClientRect();
        if (!d.x.length || !vb || !r.width) { hide(); return; }

        var fx = (clientX - r.left) / r.width * vb, best = 0, bd = Infinity, i;
        for (i = 0; i < d.x.length; i++) {
            var dd = Math.abs(d.x[i] - fx);
            if (dd < bd) { bd = dd; best = i; }
        }

        var t = bubble();
        t.textContent = d.t[best] || '';
        if (t.textContent === '') { t.hidden = true; return; }
        t.hidden = false;

        // Above the point, caught at the edges of the window.
        var half = t.offsetWidth / 2;
        var vw = document.documentElement.clientWidth;
        var x = r.left + d.x[best] / vb * r.width;
        t.style.left = Math.max(half + 4, Math.min(vw - half - 4, x)) + 'px';
        if (r.top - t.offsetHeight - 6 < 4) {
            t.classList.add('is-below');
            t.style.top = r.bottom + 'px';
        } else {
            t.classList.remove('is-below');
            t.style.top = r.top + 'px';
        }
    }

    function target(e) {
        return e.target && e.target.closest ? e.target.closest('svg[data-naws-sl]') : null;
    }

    document.addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') { return; }
        var svg = target(e);
        if (svg) { show(svg, e.clientX); } else { hide(); }
    });
    document.addEventListener('pointerdown', function (e) {
        var svg = target(e);
        if (svg) { show(svg, e.clientX); } else { hide(); }
    });
    window.addEventListener('scroll', hide, { passive: true });
    // Leaving the window sends pointerout without a relatedTarget.
    document.addEventListener('pointerout', function (e) {
        if (!e.relatedTarget) { hide(); }
    });
    window.addEventListener('blur', hide);
})();
