(function () {
    'use strict';

    // 1. Alternância de visibilidade de senha (mostrar/ocultar)
    const botoesToggleSenha = document.querySelectorAll('[data-toggle-senha]');

    botoesToggleSenha.forEach(function (botao) {
        botao.addEventListener('click', function () {
            const idAlvo = this.getAttribute('data-toggle-senha');
            const inputAlvo = document.getElementById(idAlvo);

            if (!inputAlvo) {
                return;
            }

            const senhaOculta = inputAlvo.type === 'password';
            inputAlvo.type = senhaOculta ? 'text' : 'password';

            const icone = this.querySelector('i');
            if (icone) {
                icone.classList.toggle('mdi-eye', !senhaOculta);
                icone.classList.toggle('mdi-eye-off', senhaOculta);
            }

            this.setAttribute(
                'aria-label',
                senhaOculta ? 'Ocultar senha' : 'Mostrar senha'
            );
        });
    });

    // 2. Proteção de envio único e validações visuais
    const formulario = document.querySelector('[data-envio-unico]');
    if (!formulario) {
        return;
    }

    const botaoSubmit = formulario.querySelector('button[type="submit"]');
    const inputSenha = document.getElementById('senha');
    const inputConfirmar = document.getElementById('confirmar-senha');
    const feedbackDivergencia = document.getElementById('erro-divergencia-senha');
    const textoOriginal = botaoSubmit ? botaoSubmit.innerHTML : '';

    formulario.addEventListener('submit', function (evento) {
        // Limpar estados visuais prévios de divergência
        if (inputConfirmar && feedbackDivergencia) {
            inputConfirmar.classList.remove('is-invalid');
            feedbackDivergencia.classList.add('d-none');
        }

        // Validação de correspondência de senhas no front-end
        if (inputSenha && inputConfirmar && inputSenha.value !== inputConfirmar.value) {
            evento.preventDefault();
            inputConfirmar.classList.add('is-invalid');
            if (feedbackDivergencia) {
                feedbackDivergencia.classList.remove('d-none');
            }
            inputConfirmar.focus();
            return;
        }

        // Prevenção de múltiplos envios acidentais
        if (formulario.dataset.enviando === 'true') {
            evento.preventDefault();
            return;
        }

        if (formulario.checkValidity && !formulario.checkValidity()) {
            return;
        }

        if (botaoSubmit) {
            formulario.dataset.enviando = 'true';
            botaoSubmit.disabled = true;

            const textoProcessando = botaoSubmit.getAttribute('data-texto-processando') || 'Processando...';
            botaoSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + textoProcessando;
        }
    });

    // Reativação dos botões ao navegar pelo histórico (pageshow)
    window.addEventListener('pageshow', function () {
        formulario.dataset.enviando = 'false';
        if (botaoSubmit) {
            botaoSubmit.disabled = false;
            botaoSubmit.innerHTML = textoOriginal;
        }
    });
}());
