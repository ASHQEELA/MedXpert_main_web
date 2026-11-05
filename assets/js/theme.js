/* Light and dark theme. The early script in <head> sets data-theme before paint.
   This file adds the toggle button and the smooth fade between the two themes. */
(function () {
    'use strict';
    var root = document.documentElement;
    var KEY = 'mxTheme';

    function t(text) { return typeof window.__ === 'function' ? window.__(text) : text; }
    function current() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }

    function save(value) {
        try { localStorage.setItem(KEY, value); } catch (e) { /* storage blocked */ }
    }

    function paint(btn) {
        var dark = current() === 'dark';
        btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        btn.setAttribute('aria-label', t(dark ? 'Switch to light theme' : 'Switch to dark theme'));
        btn.setAttribute('title', t(dark ? 'Switch to light theme' : 'Switch to dark theme'));
        btn.innerHTML = '<i class="fas ' + (dark ? 'fa-sun' : 'fa-moon') + '" aria-hidden="true"></i>';
    }

    function apply(value, btns) {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduce) {
            root.classList.add('theme-anim');
            clearTimeout(apply.timer);
            apply.timer = setTimeout(function () { root.classList.remove('theme-anim'); }, 450);
        }
        root.setAttribute('data-theme', value);
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) { meta.setAttribute('content', value === 'dark' ? '#0a1230' : '#0C2490'); }
        btns.forEach(paint);
    }

    function build() {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'theme-toggle';
        return btn;
    }

    function init() {
        var btns = [];
        var bar = document.querySelector('.top-bar');
        var pub = document.querySelector('.pub-nav-actions');
        var btn = build();

        if (pub) {
            btn.classList.add('theme-toggle-pub');
            pub.insertBefore(btn, pub.firstChild);
        } else if (bar) {
            btn.classList.add('theme-toggle-app');
            bar.appendChild(btn);
        } else {
            btn.classList.add('theme-toggle-float');
            document.body.appendChild(btn);
        }
        btns.push(btn);
        paint(btn);

        btn.addEventListener('click', function () {
            var next = current() === 'dark' ? 'light' : 'dark';
            save(next);
            apply(next, btns);
        });

        // Follow the system setting until the visitor picks a theme
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var follow = function (e) {
                var stored = null;
                try { stored = localStorage.getItem(KEY); } catch (err) { stored = null; }
                if (!stored) { apply(e.matches ? 'dark' : 'light', btns); }
            };
            if (mq.addEventListener) { mq.addEventListener('change', follow); }
        }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
