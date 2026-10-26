(function ($) {
    'use strict';

    function getCookie(name) {
        var value = '; ' + document.cookie;
        var parts = value.split('; ' + name + '=');
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    }

    function obterTokenCsrf() {
        var metaTag = $('meta[name="X-CSRF-TOKEN"]');
        var metaContent = metaTag.attr('content');
        if (metaContent && typeof metaContent === 'string' && metaContent.trim() !== '') {
            return metaContent.trim();
        }

        var cookie = getCookie('csrf_cookie_name');
        if (cookie && cookie.trim() !== '') {
            return decodeURIComponent(cookie.trim());
        }

        return null;
    }

    // Configura o envio global do token CSRF em requisições AJAX (manual 11.3)
    var tokenCsrf = obterTokenCsrf();
    if (tokenCsrf) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': tokenCsrf
            }
        });
    } else if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Token CSRF não localizado no meta tag nem no cookie. Cabeçalho X-CSRF-TOKEN omitido.');
    }
})(jQuery);
