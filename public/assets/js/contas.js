'use strict';

$(function () {
    const modal = $('#modal-atualizar-conta');
    const form = $('#form-atualizar-conta');
    let urlSalvar = '';
    let emailOriginal = '';

    function restaurarEstadoEmail() {
        $('#alterar-email-excepcional').prop('checked', false);
        $('#conta-email-atual').prop('disabled', true).val(emailOriginal);
        $('#justificativa-email').prop('disabled', true);
        $('#aviso-alteracao-email, #grupo-justificativa-email').addClass('d-none');
        $('#justificativa-email').val('');
        $('#contador-justificativa').text('0');
    }

    $('#alterar-email-excepcional').on('change', function () {
        const habilitado = $(this).is(':checked');
        $('#conta-email-atual, #justificativa-email').prop('disabled', !habilitado);
        $('#aviso-alteracao-email').toggleClass('d-none', !habilitado);
        $('#grupo-justificativa-email').toggleClass('d-none', !habilitado || $('#conta-email-atual').val() === emailOriginal);
        if (!habilitado) {
            $('#conta-email-atual').val(emailOriginal);
            $('#justificativa-email').val('');
            $('#contador-justificativa').text('0');
        }
    });

    $('#conta-email-atual').on('input', function () {
        const alterado = $(this).val().trim() !== emailOriginal;
        $('#grupo-justificativa-email').toggleClass('d-none', !$('#alterar-email-excepcional').is(':checked') || !alterado);
        if (!alterado) {
            $('#justificativa-email').val('');
            $('#contador-justificativa').text('0');
        }
    });

    $('#justificativa-email').on('input', function () {
        $('#contador-justificativa').text($(this).val().length);
    });

    function atualizarTokenCsrf(resposta) {
        if (!resposta.csrf_hash) {
            return;
        }

        $('meta[name="X-CSRF-TOKEN"]').attr('content', resposta.csrf_hash);
        form.find('[data-csrf-field] input[type="hidden"]')
            .val(resposta.csrf_hash)
            .attr('value', resposta.csrf_hash);
    }

    $('#tabela-usuarios').on('click', '.btn-editar-conta', function () {
        const botao = $(this);
        urlSalvar = botao.data('salvar-url');
        $('#erro-atualizar-conta').addClass('d-none').text('');
        $('#senha-admin-conta').val('');
        $('#conta-nome').val('');
        $('#conta-email-atual').val('');
        emailOriginal = '';
        restaurarEstadoEmail();

        $.getJSON(botao.data('consultar-url'))
            .done(function (resposta) {
                $('#conta-nome').val(resposta.conta.nome);
                $('#conta-email-atual').val(resposta.conta.email);
                emailOriginal = resposta.conta.email;
                restaurarEstadoEmail();
            })
            .fail(function () {
                $('#erro-atualizar-conta').removeClass('d-none').text('Não foi possível carregar os dados desta conta.');
            });
    });

    form.on('submit', function (evento) {
        evento.preventDefault();
        const botao = $('#btn-salvar-conta');
        botao.prop('disabled', true);
        $('#erro-atualizar-conta').addClass('d-none').text('');

        const emailNovo = $('#conta-email-atual').val().trim();
        const alteracaoEmail = $('#alterar-email-excepcional').is(':checked') && emailNovo !== emailOriginal;
        const justificativa = $('#justificativa-email').val().trim();
        if (alteracaoEmail && justificativa.length < 15) {
            const mensagem = 'Informe uma justificativa com pelo menos 15 caracteres.';
            $('#erro-atualizar-conta').removeClass('d-none').text(mensagem);
            $.toast({ heading: 'Erro', text: mensagem, position: 'top-center', icon: 'error' });
            botao.prop('disabled', false);
            return;
        }

        $.ajax({
            url: urlSalvar,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json'
        }).done(function (resposta) {
            atualizarTokenCsrf(resposta);

            table.ajax.reload(null, false);

            bootstrap.Modal.getOrCreateInstance(modal[0]).hide();
            $.toast({ heading: 'Sucesso', text: resposta.mensagem, position: 'top-center', icon: 'success' });
        }).fail(function (xhr) {
            const resposta = xhr.responseJSON || {};
            atualizarTokenCsrf(resposta);
            $('#erro-atualizar-conta').removeClass('d-none').text(resposta.mensagem || 'Não foi possível atualizar o usuário.');
            $.toast({
                heading: 'Erro',
                text: resposta.mensagem || 'Não foi possível atualizar o usuário.',
                position: 'top-center',
                icon: 'error'
            });
        }).always(function () {
            botao.prop('disabled', false);
        });
    });

    modal.on('hidden.bs.modal', function () {
        form[0].reset();
        emailOriginal = '';
        restaurarEstadoEmail();
    });
});
