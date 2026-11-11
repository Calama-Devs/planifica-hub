(function ($) {
    'use strict';

    // Leva a permissão escolhida para o modal de confirmação da revogação.
    // Os dados vêm do servidor: texto sempre com .text() (manual 9.7 e 11.6).
    $('#modal-revogar-permissao').on('show.bs.modal', function (evento) {
        var permissao = String($(evento.relatedTarget).data('permissao') || '');

        $('#revogar-permissao-valor').val(permissao);
        $('#revogar-permissao-nome').text(permissao);
    });
})(jQuery);
