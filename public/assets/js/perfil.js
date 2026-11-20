(function ($) {
    'use strict';

    // Inicializa tooltips nos campos bloqueados
    var elementosTooltip = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    elementosTooltip.forEach(function (elemento) {
        new bootstrap.Tooltip(elemento);
    });

    // Rotaciona ícone de seta ao expandir/recolher seção de senha
    var secaoSenha = document.getElementById('secao-trocar-senha');
    var botaoSecao = document.getElementById('botao-secao-senha');
    if (secaoSenha && botaoSecao) {
        secaoSenha.addEventListener('show.bs.collapse', function () {
            botaoSecao.classList.add('secao-expandida');
        });
        secaoSenha.addEventListener('hide.bs.collapse', function () {
            botaoSecao.classList.remove('secao-expandida');
        });
    }

    // Mostrar/ocultar senha
    $(document).on('click', '.btn-toggle-senha', function () {
        var alvo = $($(this).data('alvo'));
        var icone = $(this).find('.mdi');
        var labelAtual = $(this).attr('aria-label') || '';

        if (alvo.attr('type') === 'password') {
            alvo.attr('type', 'text');
            icone.removeClass('mdi-eye').addClass('mdi-eye-off');
            $(this).attr('aria-label', labelAtual.replace('Mostrar', 'Ocultar'));
        } else {
            alvo.attr('type', 'password');
            icone.removeClass('mdi-eye-off').addClass('mdi-eye');
            $(this).attr('aria-label', labelAtual.replace('Ocultar', 'Mostrar'));
        }
    });

})(jQuery);
