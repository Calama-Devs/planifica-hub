$(document).ready(function () {
    var $form = $('#form-grupo');
    if (!$form.length) return;

    var $btnSalvar = $('#btn-salvar-grupo');
    var $spinnerSalvar = $('#spinner-salvar-grupo');
    var $iconeSalvar = $('#icone-salvar-grupo');
    var $textoSalvar = $('#texto-salvar-grupo');
    var $modalSair = $('#modal-sair-sem-salvar');
    var $btnConfirmarSaida = $('#btn-confirmar-saida');

    var alteracoesPendentes = false;
    var enviando = false;

    function atualizarContadores() {
        $('.categoria-permissoes-item').each(function () {
            var $item = $(this);
            var total = $item.find('.checkbox-permissao:not(:disabled)').length;
            var marcadas = $item.find('.checkbox-permissao:not(:disabled):checked').length;
            $item.find('.contador-categoria').text(marcadas + ' de ' + total + ' selecionadas');
        });
    }

    function marcarAlterado() {
        alteracoesPendentes = true;
    }

    $form.on('input change', 'input, textarea', function () {
        marcarAlterado();
        if ($(this).hasClass('checkbox-permissao')) {
            atualizarContadores();
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    $('#btn-marcar-todas').on('click', function () {
        var $habilitadas = $form.find('.checkbox-permissao:not(:disabled)');
        if (!$habilitadas.length) return;
        $habilitadas.prop('checked', true);
        atualizarContadores();
        marcarAlterado();
    });

    $('#btn-desmarcar-todas').on('click', function () {
        var $habilitadas = $form.find('.checkbox-permissao:not(:disabled)');
        if (!$habilitadas.length) return;
        $habilitadas.prop('checked', false);
        atualizarContadores();
        marcarAlterado();
    });

    $form.on('click', '.btn-marcar-categoria', function () {
        var $habilitadas = $(this).closest('.categoria-permissoes-item').find('.checkbox-permissao:not(:disabled)');
        if (!$habilitadas.length) return;
        $habilitadas.prop('checked', true);
        atualizarContadores();
        marcarAlterado();
    });

    $form.on('click', '.btn-desmarcar-categoria', function () {
        var $habilitadas = $(this).closest('.categoria-permissoes-item').find('.checkbox-permissao:not(:disabled)');
        if (!$habilitadas.length) return;
        $habilitadas.prop('checked', false);
        atualizarContadores();
        marcarAlterado();
    });

    var $btnAlternar = $('#btn-alternar-categorias');

    function atualizarBotaoAlternar() {
        var $paineis = $('#accordion-permissoes .accordion-collapse');
        if (!$paineis.length || !$btnAlternar.length) return;
        var todasAbertas = $paineis.filter('.show').length === $paineis.length;
        $btnAlternar.attr('aria-expanded', todasAbertas ? 'true' : 'false');
        $btnAlternar.find('i')
            .toggleClass('mdi-unfold-more-horizontal', !todasAbertas)
            .toggleClass('mdi-unfold-less-horizontal', todasAbertas);
        $btnAlternar.find('span').text(todasAbertas ? 'Retrair todas' : 'Expandir todas');
    }

    $btnAlternar.on('click', function () {
        var $paineis = $('#accordion-permissoes .accordion-collapse');
        var abrir = $paineis.filter('.show').length < $paineis.length;
        $paineis.each(function () {
            var instancia = bootstrap.Collapse.getOrCreateInstance(this, { toggle: false });
            abrir ? instancia.show() : instancia.hide();
        });
    });

    $('#accordion-permissoes').on('shown.bs.collapse hidden.bs.collapse', atualizarBotaoAlternar);

    $('.link-saida-protegida').on('click', function (e) {
        if (!alteracoesPendentes || enviando) return;
        e.preventDefault();
        var destino = $(this).attr('href');
        $btnConfirmarSaida.attr('href', destino);
        bootstrap.Modal.getOrCreateInstance($modalSair[0]).show();
    });

    $btnConfirmarSaida.on('click', function () {
        alteracoesPendentes = false;
    });

    $(window).on('beforeunload', function (e) {
        if (!alteracoesPendentes || enviando) return undefined;
        e.preventDefault();
        return '';
    });

    $form.on('submit', function (e) {
        if (enviando) {
            e.preventDefault();
            return;
        }

        var $nome = $('#grupo-nome');
        if ($nome.val().trim().length < 3) {
            e.preventDefault();
            $nome.addClass('is-invalid').trigger('focus');
            return;
        }

        enviando = true;
        alteracoesPendentes = false;
        $btnSalvar.prop('disabled', true).attr('aria-busy', 'true');
        $iconeSalvar.addClass('d-none');
        $spinnerSalvar.removeClass('d-none');
        $textoSalvar.text('Salvando...');
    });

    atualizarContadores();
    atualizarBotaoAlternar();
});
