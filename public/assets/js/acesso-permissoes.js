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
})(jQuery);
