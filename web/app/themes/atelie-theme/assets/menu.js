document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.site-header__menu-toggle');
    var nav = document.querySelector('.site-header__nav');

    if (!toggle || !nav) {
        return;
    }

    toggle.addEventListener('click', function () {
        var aberto = nav.classList.toggle('is-aberto');
        toggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
});
