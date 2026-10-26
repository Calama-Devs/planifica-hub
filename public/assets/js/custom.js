(function ($) {
    'use strict';

    function obterTokenCsrf() {
        var metaTag = $('meta[name="X-CSRF-TOKEN"]');
        var metaContent = metaTag.attr('content');

        if (metaContent && typeof metaContent === 'string' && metaContent.trim() !== '') {
            return metaContent.trim();
        }

        return null;
    }

    // Configura o cabeçalho CSRF em requisições AJAX com base no meta tag gerado por csrf_meta() (manual 11.3)
    var tokenInicial = obterTokenCsrf();
    if (tokenInicial) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': tokenInicial
            }
        });
    }

    // Garante o envio do token atualizado da meta tag mesmo após regenerações
    $(document).ajaxSend(function (event, jqXHR) {
        var tokenAtual = obterTokenCsrf();
        if (tokenAtual) {
            jqXHR.setRequestHeader('X-CSRF-TOKEN', tokenAtual);
        }
    });
})(jQuery);
