(function ($) {
    'use strict';

    // Os atalhos só mexem nas permissões do catálogo. O vínculo "fora do catálogo" não pode ser concedido,
    // só mantido ou revogado, e isso fica por conta de quem clica na caixa dele, uma a uma.
    var $caixasDoCatalogo = $('#form-permissoes-diretas .permissao-catalogo');

    // "marcar todas" também deixa de fora as permissões críticas: elas precisam ser marcadas uma a uma.
    $('#marcar-todas').on('click', function () {
        $caixasDoCatalogo.not('[data-critica]').prop('checked', true);
    });

    $('#desmarcar-todas').on('click', function () {
        $caixasDoCatalogo.prop('checked', false);
    });

    // O identificador de cada permissão fica no ícone de informação, como tooltip do Bootstrap.
    if (window.bootstrap && window.bootstrap.Tooltip) {
        $('#form-permissoes-diretas [data-bs-toggle="tooltip"]').each(function () {
            new window.bootstrap.Tooltip(this);
        });
    }
})(jQuery);
