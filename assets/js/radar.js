/**
 * [naws_radar] player: shows the images the server stored, one after the
 * other. It only ever asks this site - the frame URLs and the REST route
 * in the payload point here - never a weather or map service.
 *
 * The logic lives in `core`, which Node can load for the tests.
 */
(function (root) {
    'use strict';

    var core = {
        STEP: 600,
        HOLD: 1500,
        POLL_FIRST: 20000,
        POLL_TRIES: 10,
        POLL_EVERY: 900000,

        /** The newest measured frame, or the last one; -1 without frames. */
        nowIndex: function (frames) {
            var i, last = -1;
            for (i = 0; i < frames.length; i++) {
                if (!frames[i].forecast) { last = i; }
            }
            return last < 0 ? frames.length - 1 : last;
        },

        /** The next frame that did not fail to load; stays put if all did. */
        nextOk: function (i, n, broken) {
            var k, j;
            if (!n) { return 0; }
            for (k = 1; k <= n; k++) {
                j = (i + k) % n;
                if (!broken[j]) { return j; }
            }
            return i;
        },

        /** Where the viewer was, found again by the frame's time in the new list. */
        keepIndex: function (oldFrames, oldIdx, newFrames) {
            var i, t;
            if (!oldFrames || !oldFrames.length || oldIdx < 0 || oldIdx >= oldFrames.length) {
                return core.nowIndex(newFrames);
            }
            t = oldFrames[oldIdx].t;
            for (i = 0; i < newFrames.length; i++) {
                if (newFrames[i].t === t) { return i; }
            }
            return core.nowIndex(newFrames);
        },

        delay: function (i, n) { return i === n - 1 ? core.HOLD : core.STEP; },

        autoplay: function (frames, reduced) { return !reduced && frames.length >= 2; },

        /** A position in the 1024 px image space as a percentage. */
        pct: function (v) { return (v / 1024 * 100).toFixed(4) + '%'; }
    };

    if (typeof module === 'object' && module.exports) {
        module.exports = core;
        return;
    }

    function img(src, l, t, s, onerror) {
        var el = document.createElement('img');
        el.src = src;
        el.alt = '';
        el.decoding = 'async';
        el.style.left = core.pct(l);
        el.style.top = core.pct(t);
        el.style.width = core.pct(s);
        if (onerror) { el.addEventListener('error', onerror); }
        return el;
    }

    function boot(el) {
        var node = el.querySelector('script[type="application/json"][data-naws="radar"]');
        var data;
        if (!node) { return; }
        try { data = JSON.parse(node.textContent); } catch (e) { return; }

        var layer = el.querySelector('.naws-radar-layer'),
            map = el.querySelector('.naws-radar-map'),
            clock = el.querySelector('.naws-radar-clock'),
            fc = el.querySelector('.naws-radar-fc'),
            btn = el.querySelector('.naws-radar-play'),
            range = el.querySelector('.naws-radar-range'),
            reduced = !!(root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches),
            frames = [], nodes = [], broken = {}, idx = 0, timer = null, playing = false, tries = 0;

        function show(i) {
            if (!nodes.length || i < 0) { return; }
            if (nodes[idx]) { nodes[idx].classList.remove('is-on'); }
            idx = i;
            nodes[idx].classList.add('is-on');
            range.value = String(idx);
            clock.textContent = frames[idx].label;
            fc.hidden = !frames[idx].forecast;
            range.setAttribute('aria-valuetext', frames[idx].label + (frames[idx].forecast ? ' ' + fc.textContent : ''));
        }

        function build(d) {
            frames = (d.stale ? [] : d.frames) || [];
            broken = {};
            layer.textContent = '';
            nodes = frames.map(function (f, i) {
                var box = document.createElement('div');
                var mark = function () { broken[i] = true; };
                box.className = 'naws-radar-frame';
                if (f.img) { box.appendChild(img(f.img, 0, 0, 1024, mark)); }
                (f.tiles || []).forEach(function (t) { box.appendChild(img(t[0], t[1], t[2], t[3], mark)); });
                layer.appendChild(box);
                return box;
            });
            if (map && !map.children.length && d.map) {
                d.map.forEach(function (t) { map.appendChild(img(t[0], t[1], t[2], t[3])); });
            }
            range.max = String(Math.max(0, frames.length - 1));
            range.disabled = frames.length < 2;
            btn.disabled = frames.length < 2;
            idx = 0;
            show(core.nowIndex(frames));
            if (frames.length) {
                el.classList.remove('naws-radar--preparing', 'naws-radar--unavailable');
                var msg = el.querySelector('.naws-radar-msg');
                if (msg) { msg.hidden = true; }
            }
        }

        function tick() {
            show(core.nextOk(idx, frames.length, broken));
            timer = root.setTimeout(tick, core.delay(idx, frames.length));
        }

        function play(on) {
            playing = on && frames.length >= 2;
            root.clearTimeout(timer);
            btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
            btn.classList.toggle('is-playing', playing);
            if (playing) { timer = root.setTimeout(tick, core.STEP); }
        }

        function poll(wait) {
            root.setTimeout(function () {
                if (document.hidden) { poll(wait); return; }
                root.fetch(data.rest, { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (d && d.frames && d.frames.length && !d.stale && d.updated !== data.updated) {
                            var had = frames.length > 0, was = playing,
                                oldFrames = frames, oldIdx = idx;
                            data = d;
                            play(false);
                            build(d);
                            if (had) { show(core.keepIndex(oldFrames, oldIdx, frames)); }
                            play(had ? was : core.autoplay(frames, reduced));
                            poll(core.POLL_EVERY);
                        } else if (!frames.length && ++tries < core.POLL_TRIES) {
                            poll(core.POLL_FIRST);
                        } else {
                            poll(core.POLL_EVERY);
                        }
                    })
                    .catch(function () { poll(core.POLL_EVERY); });
            }, wait);
        }

        btn.addEventListener('click', function () { play(!playing); });
        range.addEventListener('input', function () {
            play(false);
            show(parseInt(range.value, 10) || 0);
        });

        build(data);
        play(core.autoplay(frames, reduced));
        poll(frames.length ? core.POLL_EVERY : core.POLL_FIRST);
    }

    function start() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-naws-radar]'), boot);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(typeof window !== 'undefined' ? window : this);
