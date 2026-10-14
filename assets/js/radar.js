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
        POLL_FIRST: 20000,
        POLL_TRIES: 10,
        POLL_EVERY: 900000,
        MAX_AGE: 900,
        STALE: 10800,
        AS_OF: 1800,

        /** The page may come from a cache: are its images due for renewal? */
        needsPoll: function (updated, nowSec) { return nowSec - updated > core.MAX_AGE; },

        /** Images this old are not shown any more (same rule as the server). */
        isStale: function (updated, nowSec) { return nowSec - updated > core.STALE; },

        /**
         * Images too old: the visit has just started a renewal, so ask again
         * soon - unless it has failed POLL_TRIES times already.
         */
        goneWait: function (tries) { return tries < core.POLL_TRIES ? core.POLL_FIRST : core.POLL_EVERY; },

        /** Show "As of HH:MM" once the newest image is this old. */
        showAsOf: function (updated, nowSec) { return updated > 0 && nowSec - updated > core.AS_OF; },

        /** Position of the "now" mark over the slider, as a percentage. */
        markPct: function (i, n) { return (n > 1 ? Math.max(0, i) / (n - 1) * 100 : 0).toFixed(4) + '%'; },

        /** The newest measured frame, or the last one; -1 without frames. */
        nowIndex: function (frames) {
            var i, last = -1;
            for (i = 0; i < frames.length; i++) {
                if (!frames[i].forecast) { last = i; }
            }
            return last < 0 ? frames.length - 1 : last;
        },

        /**
         * The next frame after i that did not fail to load, -1 at the end:
         * a pass runs once, it does not start over. stepOn(-1, ...) is the
         * first frame of a pass.
         */
        stepOn: function (i, n, broken) {
            var j;
            for (j = i + 1; j < n; j++) {
                if (!broken[j]) { return j; }
            }
            return -1;
        },

        /** Zoom levels of the + / - buttons, around the station. */
        LEVELS: [1, 1.5, 2, 3, 4],
        /** A map tile this wide on screen keeps the labels readable. */
        READABLE: 200,

        /** First zoom level at which a map tile (px on screen at 1x) is readable. */
        startLevel: function (tilePx) {
            var i;
            if (!(tilePx > 0)) { return 1; }
            for (i = 0; i < core.LEVELS.length; i++) {
                if (tilePx * core.LEVELS[i] >= core.READABLE) { return core.LEVELS[i]; }
            }
            return core.LEVELS[core.LEVELS.length - 1];
        },

        /** The next level up (dir 1) or down (dir -1); stays at the ends. */
        stepLevel: function (s, dir) {
            var L = core.LEVELS, i;
            if (dir > 0) {
                for (i = 0; i < L.length; i++) { if (L[i] > s) { return L[i]; } }
                return L[L.length - 1];
            }
            for (i = L.length - 1; i >= 0; i--) { if (L[i] < s) { return L[i]; } }
            return L[0];
        },

        /**
         * The finer map layer (one zoom further in, tiles half the size)
         * once it is readable itself, i.e. the coarse one is blown up twice.
         */
        useFine: function (tilePx, s) { return tilePx * s >= 2 * core.READABLE; },

        /**
         * Offset of the zoomed stage (square, stageW wide) in the view,
         * kept so that it always fills the view. Nothing to move at 1x.
         */
        clampPan: function (x, y, s, viewW, viewH, stageW) {
            var mx, my;
            if (s <= 1) { return [0, 0]; }
            mx = Math.max(0, (s * stageW - viewW) / 2);
            my = Math.max(0, (s * stageW - viewH) / 2);
            return [Math.max(-mx, Math.min(mx, x)), Math.max(-my, Math.min(my, y))];
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
            nowMark = el.querySelector('.naws-radar-now'),
            msg = el.querySelector('.naws-radar-msg'),
            asof = el.querySelector('.naws-radar-asof'),
            view = el.querySelector('.naws-radar-view'),
            stage = el.querySelector('.naws-radar-stage'),
            zin = el.querySelector('.naws-radar-zin'),
            zout = el.querySelector('.naws-radar-zout'),
            frames = [], nodes = [], broken = {}, idx = 0, timer = null, playing = false, resume = false, tries = 0,
            scale = 1, pan = [0, 0], touched = false, fineBox = null, drag = null;

        function nowSec() { return Math.floor(Date.now() / 1000); }

        /** Data that was there once but is too old now (not "still preparing"). */
        function gone(d) { return !!d && d.updated > 0 && (d.stale || core.isStale(d.updated, nowSec())); }

        function show(i) {
            if (!nodes.length || i < 0) { return; }
            if (nodes[idx]) { nodes[idx].classList.remove('is-on'); }
            idx = i;
            nodes[idx].classList.add('is-on');
            range.value = String(idx);
            clock.textContent = frames[idx].label;
            clock.parentNode.hidden = false;
            fc.hidden = !frames[idx].forecast;
            range.setAttribute('aria-valuetext', frames[idx].label + (frames[idx].forecast ? ' ' + fc.textContent : ''));
        }

        function stamp(d) {
            if (!asof) { return; }
            if (d.updatedLabel) {
                asof.textContent = (asof.getAttribute('data-format') || '%s').replace('%s', d.updatedLabel);
            }
            asof.hidden = !d.updatedLabel || !core.showAsOf(d.updated, nowSec());
        }

        function build(d) {
            frames = gone(d) ? [] : (d.frames || []);
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
            if (nowMark) {
                nowMark.style.left = core.markPct(core.nowIndex(frames), frames.length);
                nowMark.hidden = frames.length < 2;
            }
            idx = 0;
            show(core.nowIndex(frames));
            if (!frames.length) { clock.parentNode.hidden = true; }
            stamp(d);
            if (frames.length) {
                el.classList.remove('naws-radar--preparing', 'naws-radar--unavailable');
                el.classList.add('naws-radar--ready');
                if (msg) { msg.hidden = true; }
            } else if (gone(d)) {
                el.classList.remove('naws-radar--ready', 'naws-radar--preparing');
                el.classList.add('naws-radar--unavailable');
                clock.textContent = '';
                clock.parentNode.hidden = true;
                fc.hidden = true;
                if (msg) {
                    msg.textContent = msg.getAttribute('data-unavailable') || msg.textContent;
                    msg.hidden = false;
                }
            }
        }

        /** Width of one map tile on screen at 1x, in px; 0 without a map. */
        function tilePx() {
            var t = data.map && data.map[0];
            return t && stage ? stage.offsetWidth * t[3] / 1024 : 0;
        }

        /** Show the finer map layer when it reads better; built on first use. */
        function fine() {
            var on = !!(map && data.fine && data.fine.length) && core.useFine(tilePx(), scale);
            if (on && !fineBox) {
                fineBox = document.createElement('div');
                fineBox.className = 'naws-radar-map naws-radar-map--fine';
                fineBox.setAttribute('aria-hidden', 'true');
                data.fine.forEach(function (t) { fineBox.appendChild(img(t[0], t[1], t[2], t[3])); });
                map.parentNode.insertBefore(fineBox, map.nextSibling);
            }
            if (fineBox) { fineBox.hidden = !on; }
        }

        function applyZoom() {
            if (!stage || !view) { return; }
            pan = core.clampPan(pan[0], pan[1], scale, view.clientWidth, view.clientHeight, stage.offsetWidth);
            stage.style.transform = 'translate(-50%, -50%) translate(' + pan[0] + 'px, ' + pan[1] + 'px) scale(' + scale + ')';
            el.style.setProperty('--naws-radar-zoom', String(scale));
            el.classList.toggle('naws-radar--zoomed', scale > 1);
            if (zin) { zin.disabled = scale >= core.LEVELS[core.LEVELS.length - 1]; }
            if (zout) { zout.disabled = scale <= 1; }
            fine();
        }

        /** Zoom around the middle of the view: the point there stays put. */
        function zoomTo(s) {
            pan = [pan[0] * s / scale, pan[1] * s / scale];
            scale = s;
            applyZoom();
        }

        /** Until the visitor zooms: start where the map labels are readable. */
        function autoZoom() {
            if (!touched) { zoomTo(core.startLevel(tilePx())); }
        }

        /** One pass from the first to the last frame, then it stops there. */
        function tick() {
            var j = core.stepOn(idx, frames.length, broken);
            if (j < 0) {
                play(false);
                return;
            }
            show(j);
            timer = root.setTimeout(tick, core.STEP);
        }

        function play(on) {
            playing = on && frames.length >= 2;
            root.clearTimeout(timer);
            btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
            btn.classList.toggle('is-playing', playing);
            if (playing) { timer = root.setTimeout(tick, core.STEP); }
        }

        /** Play: from the first frame, or on from where Pause stopped the pass. */
        function start() {
            var first = core.stepOn(-1, frames.length, broken);
            if (resume && core.stepOn(idx, frames.length, broken) < 0) { resume = false; } // paused on the last frame
            if (!resume && first >= 0) { show(first); }
            resume = false;
            play(true);
        }

        function poll(wait) {
            root.setTimeout(function () {
                if (document.hidden) { poll(wait); return; }
                root.fetch(data.rest, { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (gone(d)) {
                            // Too old now: no frames, the "unavailable" message.
                            data = d;
                            play(false);
                            build(d);
                            poll(core.goneWait(++tries));
                        } else if (d && d.frames && d.frames.length && d.updated !== data.updated) {
                            var had = frames.length > 0, was = playing,
                                oldFrames = frames, oldIdx = idx;
                            tries = 0;
                            data = d;
                            play(false);
                            build(d);
                            if (had) { show(core.keepIndex(oldFrames, oldIdx, frames)); }
                            play(had && was);
                            autoZoom();
                            fine();
                            poll(core.POLL_EVERY);
                        } else if ((!frames.length || core.needsPoll(data.updated, nowSec())) && ++tries < core.POLL_TRIES) {
                            // Still preparing, or the renewal is under way: ask again soon.
                            if (d) { stamp(data); }
                            poll(core.POLL_FIRST);
                        } else {
                            if (d) { stamp(data); }
                            poll(core.POLL_EVERY);
                        }
                    })
                    .catch(function () { poll(core.POLL_EVERY); });
            }, wait);
        }

        btn.addEventListener('click', function () {
            if (playing) {
                play(false);
                resume = true;
            } else {
                start();
            }
        });
        range.addEventListener('input', function () {
            play(false);
            resume = false;
            show(parseInt(range.value, 10) || 0);
        });

        function zoomBtn(dir) {
            return function () {
                touched = true;
                zoomTo(core.stepLevel(scale, dir));
            };
        }
        if (zin) { zin.addEventListener('click', zoomBtn(1)); }
        if (zout) { zout.addEventListener('click', zoomBtn(-1)); }

        // Drag to move the zoomed map (mouse, pen or finger). At 1x the
        // finger scrolls the page as before (touch-action in the CSS).
        if (view && stage) {
            view.addEventListener('pointerdown', function (e) {
                if (scale <= 1 || e.button !== 0 || (e.target.closest && e.target.closest('button'))) { return; }
                drag = { id: e.pointerId, x: e.clientX, y: e.clientY, px: pan[0], py: pan[1] };
                if (view.setPointerCapture) { view.setPointerCapture(e.pointerId); }
                el.classList.add('naws-radar--dragging');
                e.preventDefault();
            });
            view.addEventListener('pointermove', function (e) {
                if (!drag || e.pointerId !== drag.id) { return; }
                pan = [drag.px + e.clientX - drag.x, drag.py + e.clientY - drag.y];
                applyZoom();
            });
            var endDrag = function (e) {
                if (!drag || e.pointerId !== drag.id) { return; }
                drag = null;
                el.classList.remove('naws-radar--dragging');
            };
            view.addEventListener('pointerup', endDrag);
            view.addEventListener('pointercancel', endDrag);
            root.addEventListener('resize', applyZoom);
        }

        // No autoplay: the visitor starts the pass with the Play button.
        build(data);
        autoZoom();
        // A cached page can be hours old: ask at once when its images are due.
        if (data.updated > 0 && core.needsPoll(data.updated, nowSec())) {
            poll(0);
        } else {
            poll(frames.length ? core.POLL_EVERY : core.POLL_FIRST);
        }
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
