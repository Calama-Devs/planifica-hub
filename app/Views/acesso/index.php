<?= $this->extend('layouts/principal') ?>
<?= $this->section('titulo') ?>Gerenciamento de Usuários<?= $this->endSection() ?>
<?= $this->section('conteudo') ?>
<?= $this->include('acesso/modals/modal-adicionar-usuario') ?>
<?= $this->include('acesso/modals/modal-atualizar-usuario') ?>
<?= $this->include('acesso/modals/modal-resetar-senha') ?>
<?= $this->include('acesso/modals/modal-desativar-usuario') ?>
<?= $this->include('acesso/modals/modal-reativar-usuario') ?>
<?= $this->include('acesso/modals/modal-alterar-grupos') ?>
<?= $this->include('acesso/modals/modal-confirmar-senha-admin') ?>

<div class="page-header mb-3">
    <h3 class="page-title">Gerenciamento de Usuários</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Gerenciamento de Usuários</li>
        </ol>
    </nav>
</div>

<p class="text-muted mb-3">
    Acessos das contas, identificação de grupos e administração geral de usuários do PlanIFica.
</p>

<div class="row mb-3">
    <div class="col-12 col-md-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Ações</h4>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#modal-adicionar-usuario">
                        <i class="mdi mdi-account-plus btn-icon-prepend"></i> Adicionar Usuário
                    </button>

                    <?php if ($situacao === 'inativos'): ?>
                        <a href="<?= url_to('acessos.index') ?>?situacao=ativos" class="btn btn-secondary btn-icon-text">
                            <i class="mdi mdi-account-check btn-icon-prepend"></i> Usuários Ativos
                        </a>
                    <?php else: ?>
                        <a href="<?= url_to('acessos.index') ?>?situacao=inativos" class="btn btn-secondary btn-icon-text">
                            <i class="mdi mdi-account-off btn-icon-prepend"></i> Usuários Inativos
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabela-acessos" id="tabela-usuarios"
                           aria-label="Listagem e Gerenciamento de Usuários">
                        <thead>
                            <tr>
                                <th scope="col" style="min-width: 180px;"><strong>Nome</strong></th>
                                <th scope="col" style="min-width: 200px;"><strong>E-mail</strong></th>
                                <th scope="col" style="min-width: 140px;"><strong>Grupo(s)</strong></th>
                                <th scope="col" class="text-center" style="min-width: 100px;"><strong>Situação</strong></th>
                                <th scope="col" class="text-center text-nowrap" style="min-width: 180px;"><strong>Ações</strong></th>
                            </tr>
                        </thead>
                        <tbody id="contas-encontradas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="contas-data"><?= json_encode($contas ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>

<style>
    .table-responsive {
        overflow-y: hidden;
    }

    #tabela-usuarios {
        width: 100% !important;
    }

    #tabela-usuarios th,
    #tabela-usuarios td {
        vertical-align: middle;
    }

    .link-usuario {
        color: #58a6ff;
        text-decoration: none;
        border-bottom: 1px dashed rgba(88, 166, 255, 0.45);
        padding-bottom: 1px;
        transition: color 0.15s ease, border-color 0.15s ease;
    }

    .link-usuario:hover {
        color: #79c0ff;
        border-bottom-style: solid;
        border-bottom-color: #79c0ff;
        text-decoration: none;
    }

    .dataTables_wrapper .dataTables_filter {
        text-align: right;
        margin-bottom: 1rem;
    }

    .dataTables_wrapper .dataTables_filter input {
        color: #fff;
        background-color: #2a3038;
        border: 1px solid #2c2e33;
        border-radius: 4px;
        padding: 0.375rem 0.75rem;
        margin-left: 0.5rem;
    }

    .dataTables_wrapper .dataTables_length {
        margin-bottom: 1rem;
    }

    .dataTables_wrapper .dataTables_length select {
        color: #fff;
        background-color: #2a3038;
        border: 1px solid #2c2e33;
        border-radius: 4px;
        padding: 0.25rem 0.5rem;
        margin: 0 0.25rem;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        color: #6c7293 !important;
        padding-top: 0.75rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: #6c7293 !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #0090e7 !important;
        color: #fff !important;
        border-color: #0090e7 !important;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }

    .modal-dialog-top {
        margin-top: 3.5rem !important;
    }

    .modal-header .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.85;
    }

    .modal-header .btn-close-white:hover {
        opacity: 1;
    }

    .badge.bg-light,
    .badge.bg-white,
    .badge-light {
        background-color: #f8f9fa !important;
        color: #12151c !important;
    }

    .highlight-search {
        background-color: rgba(40, 167, 69, 0.2) !important;
        color: #71dd37 !important;
        font-weight: bold;
        padding: 0 4px;
        border-radius: 4px;
        border-bottom: 1px solid rgba(40, 167, 69, 0.5);
    }

    .modal .custom-scroll {
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: #6c7293 transparent;
    }

    .modal .custom-scroll::-webkit-scrollbar {
        width: 6px;
    }

    .modal .custom-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .modal .custom-scroll::-webkit-scrollbar-thumb {
        background: #2c2e33;
        border-radius: 10px;
        transition: background 0.3s ease;
    }

    .modal .custom-scroll::-webkit-scrollbar-thumb:hover {
        background: #6c7293;
    }
</style>

<script>
$(document).ready(function () {
    // Inicializa Tooltips do Bootstrap
    function initTooltips() {
        $('[data-bs-toggle="tooltip"]').each(function () {
            var instance = bootstrap.Tooltip.getInstance(this);
            if (instance) {
                instance.dispose();
            }
            new bootstrap.Tooltip(this, {
                container: 'body'
            });
        });
    }

    // Funções de destaque de busca
    function destacarTermoNoElemento(el, termo) {
        if (!termo) return;
        var termoEscapado = termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var regex = new RegExp('(' + termoEscapado + ')', 'gi');

        function traverse(node) {
            if (node.nodeType === 3 && node.nodeValue) {
                var partes = node.nodeValue.split(regex);
                if (partes.length > 1) {
                    var fragment = document.createDocumentFragment();
                    partes.forEach(function (parte, i) {
                        if (parte === '') return;
                        if (i % 2 === 1) {
                            var mark = document.createElement('span');
                            mark.className = 'highlight-search';
                            mark.textContent = parte;
                            fragment.appendChild(mark);
                        } else {
                            fragment.appendChild(document.createTextNode(parte));
                        }
                    });
                    node.parentNode.replaceChild(fragment, node);
                }
            } else if (node.nodeType === 1 && !/(script|style|button|input|select)/i.test(node.tagName)) {
                Array.from(node.childNodes).forEach(traverse);
            }
        }
        traverse(el);
    }

    function limparDestaque(el) {
        el.querySelectorAll('span.highlight-search').forEach(function (mark) {
            mark.replaceWith(document.createTextNode(mark.textContent));
        });
        el.normalize();
    }

    function aplicarDestaqueTabela(dt) {
        if (!dt) return;
        var termo = dt.search();
        var corpoTabela = document.querySelectorAll('#tabela-usuarios tbody td:nth-child(1), #tabela-usuarios tbody td:nth-child(2), #tabela-usuarios tbody td:nth-child(3)');

        corpoTabela.forEach(function (celula) {
            limparDestaque(celula);
        });

        var searchInput = $('.dataTables_filter input');
        var badgeBusca = $('#badge-termo-pesquisa');

        if (!badgeBusca.length && searchInput.length) {
            badgeBusca = $('<span id="badge-termo-pesquisa" class="highlight-search ms-2" style="display:none; vertical-align: middle;"></span>');
            searchInput.parent().append(badgeBusca);
        }

        if (termo && termo.trim().length >= 1) {
            badgeBusca.text(termo.trim()).show();
            corpoTabela.forEach(function (celula) {
                destacarTermoNoElemento(celula, termo.trim());
            });
        } else {
            badgeBusca.hide();
        }
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var contasData = JSON.parse(document.getElementById('contas-data').textContent || '[]');

    var tableElement = $('#tabela-usuarios');
    var dataTableInstance = null;

    if (tableElement.length && $.fn.DataTable) {
        dataTableInstance = tableElement.DataTable({
            data: contasData,
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            order: [[0, 'asc']],
            columns: [
                {
                    data: 'rotulo',
                    render: function (data, type, row) {
                        var nome = escapeHtml(data || row.username || '');
                        var url = escapeHtml(row.url_permissoes || '#');
                        return `<a href="${url}" class="link-usuario fw-medium text-truncate d-inline-block" style="max-width: 220px;" title="Ver permissões de ${nome}">${nome}</a>`;
                    }
                },
                {
                    data: 'email',
                    render: function (data) {
                        if (!data) {
                            return '<span class="text-muted">—</span>';
                        }
                        var email = escapeHtml(data);
                        return `<span class="text-truncate d-inline-block" style="max-width: 220px;" title="${email}">${email}</span>`;
                    }
                },
                {
                    data: 'grupos',
                    render: function (data) {
                        if (!data || !data.length) {
                            return '<span class="text-muted small">Nenhum</span>';
                        }
                        return escapeHtml(data.join(', '));
                    }
                },
                {
                    data: 'ativo',
                    className: 'text-center',
                    render: function (data) {
                        return data
                            ? '<span class="text-success small fw-medium"><i class="mdi mdi-circle me-1" style="font-size: 8px; vertical-align: middle;"></i>Ativo</span>'
                            : '<span class="text-danger small fw-medium"><i class="mdi mdi-circle me-1" style="font-size: 8px; vertical-align: middle;"></i>Inativo</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center text-nowrap',
                    render: function (data, type, row) {
                        var userId = row.id;
                        var nome = escapeHtml(row.rotulo || row.username || '');
                        var email = escapeHtml(row.email || '');
                        var rawGrupos = escapeHtml(JSON.stringify(row.raw_grupos || []));
                        var urlPermissoes = escapeHtml(row.url_permissoes || '#');

                        var btnStatus = row.ativo
                            ? `<span data-bs-toggle="tooltip" title="Desativar usuário">
                                   <button type="button" class="btn btn-inverse-danger button-trans-danger btn-icon btn-desativar-usuario"
                                       data-bs-toggle="modal" data-bs-target="#modal-desativar-usuario"
                                       data-user-id="${userId}" data-username="${nome}" data-email="${email}" aria-label="Desativar usuário ${nome}">
                                       <i class="mdi mdi-account-off"></i>
                                   </button>
                               </span>`
                            : `<span data-bs-toggle="tooltip" title="Reativar usuário">
                                   <button type="button" class="btn btn-inverse-success button-trans-success btn-icon btn-reativar-usuario"
                                       data-bs-toggle="modal" data-bs-target="#modal-reativar-usuario"
                                       data-user-id="${userId}" data-username="${nome}" data-email="${email}" aria-label="Reativar usuário ${nome}">
                                       <i class="mdi mdi-account-check"></i>
                                   </button>
                               </span>`;

                        return `
                            <div class="d-flex align-items-center justify-content-center">
                                <span data-bs-toggle="tooltip" title="Atualizar dados">
                                    <button type="button" class="btn btn-inverse-success button-trans-success btn-icon btn-editar-usuario"
                                        data-bs-toggle="modal" data-bs-target="#modal-atualizar-usuario"
                                        data-user-id="${userId}" data-username="${nome}" data-email="${email}" aria-label="Atualizar dados de ${nome}">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                </span>
                                <span data-bs-toggle="tooltip" title="Resetar senha">
                                    <button type="button" class="btn btn-inverse-warning button-trans-warning btn-icon btn-resetar-senha"
                                        data-bs-toggle="modal" data-bs-target="#modal-resetar-senha"
                                        data-user-id="${userId}" data-username="${nome}" data-email="${email}" aria-label="Resetar senha de ${nome}">
                                        <i class="mdi mdi-key-variant"></i>
                                    </button>
                                </span>
                                ${btnStatus}
                                <span data-bs-toggle="tooltip" title="Alterar grupos">
                                    <button type="button" class="btn btn-inverse-info button-trans-info btn-icon btn-alterar-grupos"
                                        data-bs-toggle="modal" data-bs-target="#modal-alterar-grupos"
                                        data-user-id="${userId}" data-username="${nome}" data-email="${email}" data-grupos='${rawGrupos}' aria-label="Alterar grupos de ${nome}">
                                        <i class="mdi mdi-account-multiple"></i>
                                    </button>
                                </span>
                                <span data-bs-toggle="tooltip" title="Consultar permissões">
                                    <a href="${urlPermissoes}" class="btn btn-inverse-primary button-trans-primary btn-icon" aria-label="Ver permissões de ${nome}">
                                        <i class="mdi mdi-shield-account"></i>
                                    </a>
                                </span>
                            </div>
                        `;
                    }
                }
            ],
            aLengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'Todos']
            ],
            language: {
                url: <?= json_encode(base_url('assets/js/traducao-dataTable/pt_br.json')) ?>,
                emptyTable: 'Nenhum usuário cadastrado.'
            },
            initComplete: function () {
                initTooltips();
                aplicarDestaqueTabela(dataTableInstance);
            },
            drawCallback: function () {
                initTooltips();
                aplicarDestaqueTabela(dataTableInstance);
            }
        });
    }

    initTooltips();

    // Estado da ação administrativa aguardando confirmação de senha
    var acaoPendente = {
        tipo: null,
        userId: null,
        nome: '',
        email: '',
        dadosExtras: null,
        modalOrigem: null
    };

    function fecharModalPorId(modalId) {
        var el = document.getElementById(modalId);
        if (el) {
            var modal = bootstrap.Modal.getInstance(el);
            if (modal) {
                modal.hide();
            }
        }
    }

    function abrirConfirmacaoAdmin(tipo, userId, nome, email, descricao, modalOrigemId, dadosExtras) {
        acaoPendente.tipo = tipo;
        acaoPendente.userId = userId;
        acaoPendente.nome = nome;
        acaoPendente.email = email;
        acaoPendente.dadosExtras = dadosExtras || null;
        acaoPendente.modalOrigem = modalOrigemId || null;

        if (modalOrigemId) {
            fecharModalPorId(modalOrigemId);
        }

        $('#confirmar-senha-descricao').text(descricao);
        $('#confirmar-senha-alvo').text(nome || ('Conta #' + userId));
        $('#confirmar-senha-alvo-email').text(email || '');
        $('#admin-password-confirm').val('').removeClass('is-invalid');
        $('#alerta-confirmar-senha').addClass('d-none').text('');
        $('#spinner-confirmar-senha').addClass('d-none');
        $('#btn-executar-confirmacao').prop('disabled', false);

        var modalConfirmar = new bootstrap.Modal(document.getElementById('modal-confirmar-senha-admin'));
        modalConfirmar.show();
    }

    // Alternar visualização da senha administrativa (mostrar / ocultar)
    $('#btn-toggle-admin-password').on('click', function () {
        var campo = $('#admin-password-confirm');
        var icone = $('#icone-toggle-admin-password');
        if (campo.attr('type') === 'password') {
            campo.attr('type', 'text');
            icone.removeClass('mdi-eye-outline').addClass('mdi-eye-off-outline');
        } else {
            campo.attr('type', 'password');
            icone.removeClass('mdi-eye-off-outline').addClass('mdi-eye-outline');
        }
    });

    // Atualizar Dados do Usuário
    tableElement.on('click', '.btn-editar-usuario', function () {
        var btn = $(this);
        var userId = btn.data('user-id');
        var username = btn.data('username');
        var email = btn.data('email');

        $('#editar-usuario-id').val(userId);
        $('#editar-usuario-nome').val(username).removeClass('is-invalid');
        $('#editar-usuario-email').val(email).removeClass('is-invalid');
        $('#alerta-atualizar-usuario').addClass('d-none').text('');
    });

    $('#form-atualizar-usuario').on('submit', function (e) {
        e.preventDefault();
        var nomeInput = $('#editar-usuario-nome');
        var emailInput = $('#editar-usuario-email');
        var valido = true;

        if (!nomeInput.val().trim()) {
            nomeInput.addClass('is-invalid');
            valido = false;
        } else {
            nomeInput.removeClass('is-invalid');
        }

        if (!emailInput.val().trim() || !emailInput[0].checkValidity()) {
            emailInput.addClass('is-invalid');
            valido = false;
        } else {
            emailInput.removeClass('is-invalid');
        }

        if (!valido) {
            return;
        }

        var userId = $('#editar-usuario-id').val();
        var nome = nomeInput.val().trim();
        var email = emailInput.val().trim();

        abrirConfirmacaoAdmin(
            'atualizar',
            userId,
            nome,
            email,
            'Salvar alterações cadastrais do usuário (nome completo e e-mail).',
            'modal-atualizar-usuario',
            { nome: nome, email: email }
        );
    });

    // Resetar Senha
    var resetTarget = { userId: null, nome: '', email: '' };

    tableElement.on('click', '.btn-resetar-senha', function () {
        var btn = $(this);
        resetTarget.userId = btn.data('user-id');
        resetTarget.nome = btn.data('username');
        resetTarget.email = btn.data('email');

        $('#reset-nome-usuario').text(resetTarget.nome || 'Conta #' + resetTarget.userId);
        $('#reset-email-usuario').text(resetTarget.email || 'E-mail não informado');
    });

    $('#btn-prosseguir-reset').on('click', function () {
        abrirConfirmacaoAdmin(
            'resetar',
            resetTarget.userId,
            resetTarget.nome,
            resetTarget.email,
            'Enviar e-mail seguro com link de redefinição de senha para a conta.',
            'modal-resetar-senha'
        );
    });

    // Desativar Usuário
    var desativarTarget = { userId: null, nome: '', email: '' };

    tableElement.on('click', '.btn-desativar-usuario', function () {
        var btn = $(this);
        desativarTarget.userId = btn.data('user-id');
        desativarTarget.nome = btn.data('username');
        desativarTarget.email = btn.data('email');

        $('#desativar-nome-usuario').text(desativarTarget.nome || 'Conta #' + desativarTarget.userId);
        $('#desativar-email-usuario').text(desativarTarget.email || 'E-mail não informado');
    });

    $('#btn-prosseguir-desativacao').on('click', function () {
        abrirConfirmacaoAdmin(
            'desativar',
            desativarTarget.userId,
            desativarTarget.nome,
            desativarTarget.email,
            'Desativar o acesso da conta ao sistema.',
            'modal-desativar-usuario'
        );
    });

    // Reativar Usuário
    var reativarTarget = { userId: null, nome: '', email: '' };

    tableElement.on('click', '.btn-reativar-usuario', function () {
        var btn = $(this);
        reativarTarget.userId = btn.data('user-id');
        reativarTarget.nome = btn.data('username');
        reativarTarget.email = btn.data('email');

        $('#reativar-nome-usuario').text(reativarTarget.nome || 'Conta #' + reativarTarget.userId);
        $('#reativar-email-usuario').text(reativarTarget.email || 'E-mail não informado');
    });

    $('#btn-prosseguir-reativacao').on('click', function () {
        abrirConfirmacaoAdmin(
            'reativar',
            reativarTarget.userId,
            reativarTarget.nome,
            reativarTarget.email,
            'Restaurar o acesso da conta ao sistema.',
            'modal-reativar-usuario'
        );
    });

    // Alterar Grupos
    var gruposTarget = { userId: null, nome: '', email: '', grupos: [] };

    tableElement.on('click', '.btn-alterar-grupos', function () {
        var btn = $(this);
        gruposTarget.userId = btn.data('user-id');
        gruposTarget.nome = btn.data('username');
        gruposTarget.email = btn.data('email');

        var rawGrupos = btn.data('grupos');
        if (typeof rawGrupos === 'string') {
            try {
                gruposTarget.grupos = JSON.parse(rawGrupos);
            } catch (e) {
                gruposTarget.grupos = [];
            }
        } else if (Array.isArray(rawGrupos)) {
            gruposTarget.grupos = rawGrupos;
        } else {
            gruposTarget.grupos = [];
        }

        $('#grupos-usuario-id').val(gruposTarget.userId);
        $('#grupos-nome-usuario').text(gruposTarget.nome || 'Conta #' + gruposTarget.userId);
        $('#grupos-email-usuario').text(gruposTarget.email || 'E-mail não informado');

        var containerAtuais = $('#grupos-atuais-container');
        containerAtuais.empty();
        if (gruposTarget.grupos.length) {
            gruposTarget.grupos.forEach(function (grp) {
                containerAtuais.append($('<span>').addClass('badge bg-light text-dark').text(grp));
            });
        } else {
            containerAtuais.append($('<span>').addClass('text-muted small').text('Nenhum'));
        }

        $('.check-grupo-item').each(function () {
            var check = $(this);
            check.prop('checked', gruposTarget.grupos.indexOf(check.val()) !== -1);
        });

        $('#alerta-alterar-grupos').addClass('d-none').text('');
    });

    $('#btn-prosseguir-grupos').on('click', function () {
        var gruposSelecionados = [];
        $('.check-grupo-item:checked').each(function () {
            gruposSelecionados.push($(this).val());
        });

        if (!gruposSelecionados.length) {
            $('#alerta-alterar-grupos')
                .removeClass('d-none alert-success')
                .addClass('alert-danger')
                .text('Selecione pelo menos um grupo para o usuário.');
            return;
        }

        abrirConfirmacaoAdmin(
            'alterar_grupos',
            gruposTarget.userId,
            gruposTarget.nome,
            gruposTarget.email,
            'Alterar os grupos da conta para: ' + gruposSelecionados.join(', ') + '.',
            'modal-alterar-grupos',
            { grupos: gruposSelecionados }
        );
    });

    // Submissão do Formulário de Confirmação com Senha de Admin
    $('#form-confirmar-senha-admin').on('submit', function (e) {
        e.preventDefault();
        var senhaAdmin = $('#admin-password-confirm').val();

        if (!senhaAdmin || senhaAdmin.trim() === '') {
            $('#admin-password-confirm').addClass('is-invalid');
            return;
        }
        $('#admin-password-confirm').removeClass('is-invalid');

        $('#spinner-confirmar-senha').removeClass('d-none');
        $('#btn-executar-confirmacao').prop('disabled', true);

        setTimeout(function () {
            $('#spinner-confirmar-senha').addClass('d-none');
            $('#btn-executar-confirmacao').prop('disabled', false);
            fecharModalPorId('modal-confirmar-senha-admin');
            $('#admin-password-confirm').val('');
        }, 400);
    });

    // Adicionar Usuário (Modal de Convite)
    $('#form-adicionar-usuario').on('submit', function (e) {
        e.preventDefault();
        var emailInput = $('#convite-email');

        if (!emailInput.val().trim() || !emailInput[0].checkValidity()) {
            emailInput.addClass('is-invalid');
            return;
        }
        emailInput.removeClass('is-invalid');

        var btnSubmit = $('#btn-enviar-convite');
        var spinner = btnSubmit.find('.spinner-border');

        spinner.removeClass('d-none');
        btnSubmit.prop('disabled', true);

        setTimeout(function () {
            spinner.addClass('d-none');
            btnSubmit.prop('disabled', false);
            fecharModalPorId('modal-adicionar-usuario');
            $('#form-adicionar-usuario')[0].reset();
        }, 400);
    });
});
</script>
<?= $this->endSection() ?>
