(function ($) {
    'use strict';

    var $caixas = $('#form-permissoes-diretas .permissao-direta');

    // "marcar todas" deixa de fora as permissões críticas: elas precisam ser marcadas uma a uma.
    $('#marcar-todas').on('click', function () {
        $caixas.not('[data-critica]').prop('checked', true);
    });

    $('#desmarcar-todas').on('click', function () {
        $caixas.prop('checked', false);
    });
})(jQuery);
