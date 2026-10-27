// Menu button on small screens
(function () {
    var nav = document.getElementById('pubNav');
    var btn = document.getElementById('pubMenuBtn');
    if (!nav || !btn) { return; }
    btn.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('open')) {
            nav.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            btn.focus();
        }
    });
})();
