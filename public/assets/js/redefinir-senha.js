(function () {
    'use strict';

    const formulario = document.querySelector('[data-envio-unico]');

    if (!formulario) {
        return;
    }

    const botao = formulario.querySelector('button[type="submit"]');

    if (!botao) {
        return;
    }

    const textoOriginal = botao.textContent;

    formulario.addEventListener('submit', function (evento) {
        if (formulario.dataset.enviando === 'true') {
            evento.preventDefault();
            return;
        }

        formulario.dataset.enviando = 'true';
        botao.disabled = true;
        botao.textContent = botao.dataset.textoProcessando;
    });

    window.addEventListener('pageshow', function () {
        formulario.dataset.enviando = 'false';
        botao.disabled = false;
        botao.textContent = textoOriginal;
    });
}());
