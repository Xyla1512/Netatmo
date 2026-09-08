/**
 * [naws_windrose] — switching and tooltips.
 *
 * The script computes nothing and fetches nothing. Every period the
 * switcher offers is already in the page as a hidden panel; this only
 * shows the one asked for. The tooltips take their text from the <title>
 * each sector carries for the no-script case, and move it to data-tip so
 * the browser's own tooltip does not double ours.
 *
 * @package NAWS
 */
(function () {
    'use strict';

    function panels(root) { return root.querySelectorAll('.naws-wr-panel'); }

    function current(root) {
        var p = root.querySelector('.naws-wr-panel:not([hidden])') || panels(root)[0];
        return {
            period:  p ? p.getAttribute('data-period') : '',
            measure: p ? p.getAttribute('data-measure') : 'wind'
        };
    }

    function show(root, period, measure) {
        var list = panels(root), i;
        for (i = 0; i < list.length; i++) {
            var on = list[i].getAttribute('data-period') === period && list[i].getAttribute('data-measure') === measure;
            if (on) { list[i].removeAttribute('hidden'); } else { list[i].setAttribute('hidden', ''); }
        }
        var btns = root.querySelectorAll('.naws-wr-btn');
        for (i = 0; i < btns.length; i++) {
            var b = btns[i];
            var pressed = b.hasAttribute('data-period')
                ? b.getAttribute('data-period') === period
                : b.getAttribute('data-measure') === measure;
            b.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            b.classList.toggle('is-active', pressed);
        }
    }

    function initSwitch(root) {
        var sw = root.querySelector('.naws-wr-switch');
        if (!sw) { return; }
        sw.removeAttribute('hidden');
        sw.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.naws-wr-btn') : null;
            if (!b) { return; }
            var now = current(root);
            show(root,
                b.getAttribute('data-period') || now.period,
                b.getAttribute('data-measure') || now.measure);
        });
    }

    function initTips(root) {
        var sectors = root.querySelectorAll('.naws-wr-sector'), i;
        for (i = 0; i < sectors.length; i++) {
            var t = sectors[i].querySelector('title');
            if (t) {
                sectors[i].setAttribute('data-tip', t.textContent);
                t.parentNode.removeChild(t);
            }
        }
        var tip = document.createElement('div');
        tip.className = 'naws-wr-tip';
        tip.hidden = true;
        root.appendChild(tip);

        function move(e) {
            var r = root.getBoundingClientRect();
            tip.style.left = (e.clientX - r.left) + 'px';
            tip.style.top  = (e.clientY - r.top - 12) + 'px';
        }
        root.addEventListener('mouseover', function (e) {
            var g = e.target.closest ? e.target.closest('.naws-wr-sector') : null;
            if (!g) { return; }
            tip.textContent = g.getAttribute('data-tip') || '';
            tip.hidden = tip.textContent === '';
            move(e);
            g.classList.add('is-on');
            root.classList.add('naws-wr-hover');
        });
        root.addEventListener('mousemove', function (e) {
            if (!tip.hidden) { move(e); }
        });
        root.addEventListener('mouseout', function (e) {
            var g = e.target.closest ? e.target.closest('.naws-wr-sector') : null;
            if (!g) { return; }
            tip.hidden = true;
            g.classList.remove('is-on');
            root.classList.remove('naws-wr-hover');
        });
    }

    function init() {
        var roots = document.querySelectorAll('[data-naws-windrose]');
        for (var i = 0; i < roots.length; i++) {
            initSwitch(roots[i]);
            initTips(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
