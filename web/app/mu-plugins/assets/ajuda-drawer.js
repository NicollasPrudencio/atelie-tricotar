(function () {
    function fecharPainel(overlay) {
        if (overlay) {
            overlay.hidden = true;
            document.body.classList.remove('atelie-ajuda-aberta');
        }
    }

    document.addEventListener('click', function (evento) {
        var botaoAbrir = evento.target.closest('[data-atelie-ajuda-abrir]');
        if (botaoAbrir) {
            var id = botaoAbrir.getAttribute('data-atelie-ajuda-abrir');
            var overlay = document.querySelector('[data-atelie-ajuda="' + id + '"]');
            if (overlay) {
                overlay.hidden = false;
                document.body.classList.add('atelie-ajuda-aberta');
            }
            return;
        }

        var botaoFechar = evento.target.closest('[data-atelie-ajuda-fechar]');
        if (botaoFechar) {
            fecharPainel(botaoFechar.closest('.atelie-ajuda-overlay'));
            return;
        }

        if (evento.target.classList.contains('atelie-ajuda-overlay')) {
            fecharPainel(evento.target);
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            var aberto = document.querySelector('.atelie-ajuda-overlay:not([hidden])');
            fecharPainel(aberto);
        }
    });
})();
