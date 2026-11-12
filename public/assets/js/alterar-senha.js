(function ($) {
    'use strict';

    var modal = $('#modal-alterar-senha');
    var formulario = $('#formulario-alterar-senha');
    var alerta = $('#erro-alterar-senha');
    var enviando = false;

    function atualizarCsrf(resposta) {
        if (!resposta || !resposta.csrf) {
            return;
        }

        $('meta[name="X-CSRF-TOKEN"]').attr('content', resposta.csrf.hash);
        $('input[type="hidden"]').filter(function () {
            return this.name === resposta.csrf.nome;
        }).each(function () {
            this.value = resposta.csrf.hash;
            this.defaultValue = resposta.csrf.hash;
        });
    }

    function exibirErro(mensagem) {
        alerta.text(mensagem).removeClass('d-none').trigger('focus');
    }

    function redirecionarSeNecessario(resposta) {
        if (!resposta || !resposta.redirecionamento) {
            return false;
        }

        formulario[0].reset();
        window.location.assign(resposta.redirecionamento);

        return true;
    }

    modal.on('shown.bs.modal', function () {
        $('#perfil-senha-atual').trigger('focus');
    }).on('hide.bs.modal', function (evento) {
        if (enviando) {
            evento.preventDefault();
        }
    }).on('hidden.bs.modal', function () {
        formulario[0].reset();
        alerta.text('').addClass('d-none');
    });

    formulario.on('submit', function (evento) {
        evento.preventDefault();
        if (enviando) {
            return;
        }

        enviando = true;
        alerta.text('').addClass('d-none');
        formulario.find('button').prop('disabled', true);

        $.ajax({
            url: formulario.attr('action'),
            type: 'POST',
            data: formulario.serialize(),
            dataType: 'json'
        }).done(function (resposta) {
            if (redirecionarSeNecessario(resposta)) {
                return;
            }

            atualizarCsrf(resposta);
            exibirErro(resposta.mensagem || 'Não foi possível alterar a senha.');
        }).fail(function (requisicao) {
            var resposta = requisicao.responseJSON;

            if (redirecionarSeNecessario(resposta)) {
                return;
            }

            atualizarCsrf(resposta);
            exibirErro(resposta && resposta.mensagem ? resposta.mensagem :
                'Não foi possível concluir. Recarregue a página e tente novamente.');
        }).always(function () {
            enviando = false;
            formulario.find('button').prop('disabled', false);
        });
    });
})(jQuery);
