/* Mode tampilan: siang (bawaan), malam, hitam-putih. File eksternal agar sesuai CSP (script-src 'self'). */
(function () {
    var root = document.documentElement, KEY = 'ehibah-theme';
    function saved() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
    function apply(t) {
        if (t === 'night' || t === 'mono') root.setAttribute('data-theme', t); else root.removeAttribute('data-theme');
    }
    apply(saved());
    try { if (localStorage.getItem('ehibah-nav') === '1') root.classList.add('nav-collapsed'); } catch (e) {}

    function mark(t) {
        document.querySelectorAll('[data-theme-set]').forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-theme-set') === t ? 'true' : 'false');
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        mark(saved() || 'day');
        document.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-theme-set]');
            if (!b) return;
            var t = b.getAttribute('data-theme-set');
            apply(t);
            try { localStorage.setItem(KEY, t); } catch (e) {}
            mark(t);
        });
    });
})();