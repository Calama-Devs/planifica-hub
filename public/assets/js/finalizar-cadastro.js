$(function () {
    'use strict';

    // 1. Alternância de visibilidade de senha (mostrar/ocultar)
    $(document).on('click', '[data-toggle-senha]', function () {
        var $botao = $(this);
        var idAlvo = $botao.attr('data-toggle-senha');
        var $inputAlvo = $('#' + idAlvo);

        if ($inputAlvo.length === 0) {
            return;
        }

        var senhaOculta = $inputAlvo.attr('type') === 'password';
        $inputAlvo.attr('type', senhaOculta ? 'text' : 'password');

        var $icone = $botao.find('i');
        if ($icone.length > 0) {
            $icone.toggleClass('mdi-eye', !senhaOculta)
                  .toggleClass('mdi-eye-off', senhaOculta);
        }

        $botao.attr('aria-label', senhaOculta ? 'Ocultar senha' : 'Mostrar senha');
    });

    // 2. Proteção de envio único e validações visuais
    var $formulario = $('form[data-envio-unico]');
    if ($formulario.length === 0) {
        return;
    }

    var $botaoSubmit = $formulario.find('button[type="submit"]');
    var $inputSenha = $('#senha');
    var $inputConfirmar = $('#confirmar-senha');
    var $feedbackDivergencia = $('#erro-divergencia-senha');
    var textoOriginal = $botaoSubmit.html() || '';

    $formulario.on('submit', function (evento) {
        // Limpar estados visuais prévios de divergência
        $inputConfirmar.removeClass('is-invalid');
        $feedbackDivergencia.addClass('d-none');

        // Validação de correspondência de senhas no front-end
        if ($inputSenha.length > 0 && $inputConfirmar.length > 0 && $inputSenha.val() !== $inputConfirmar.val()) {
            evento.preventDefault();
            $inputConfirmar.addClass('is-invalid');
            $feedbackDivergencia.removeClass('d-none');
            $inputConfirmar.trigger('focus');
            return;
        }

        // Prevenção de múltiplos envios acidentais
        if ($formulario.data('enviando') === true) {
            evento.preventDefault();
            return;
        }

        var formEl = $formulario[0];
        if (formEl && formEl.checkValidity && !formEl.checkValidity()) {
            return;
        }

        if ($botaoSubmit.length > 0) {
            $formulario.data('enviando', true);
            $botaoSubmit.prop('disabled', true);

            var textoProcessando = $botaoSubmit.attr('data-texto-processando') || 'Processando...';
            $botaoSubmit.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + textoProcessando);
        }
    });

    // Reativação dos botões ao navegar pelo histórico (pageshow)
    $(window).on('pageshow', function () {
        $formulario.data('enviando', false);
        if ($botaoSubmit.length > 0) {
            $botaoSubmit.prop('disabled', false);
            $botaoSubmit.html(textoOriginal);
        }
    });
});
