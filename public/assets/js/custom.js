(function ($) {
    'use strict';

    // Envia o token CSRF em toda requisição AJAX (manual 11.3).
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="X-CSRF-TOKEN"]').attr('content')
        }
    });
})(jQuery);
