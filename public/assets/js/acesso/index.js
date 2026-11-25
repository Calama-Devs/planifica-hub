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
            badgeBusca = $('<span id="badge-termo-pesquisa" class="highlight-search ms-2 d-none"></span>');
            searchInput.parent().append(badgeBusca);
        }

        if (termo && termo.trim().length >= 1) {
            badgeBusca.text(termo.trim()).removeClass('d-none');
            corpoTabela.forEach(function (celula) {
                destacarTermoNoElemento(celula, termo.trim());
            });
        } else {
            badgeBusca.addClass('d-none');
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

    var tableElement = $('#tabela-usuarios');

    // Página e pesquisa ficam na URL: recarregar ou voltar mantém o estado da listagem.
    var parametrosUrl = new URLSearchParams(window.location.search);
    var tamanhoInicial = parseInt(parametrosUrl.get('por_pagina'), 10) || 10;
    var paginaInicial = Math.max((parseInt(parametrosUrl.get('pagina'), 10) || 1) - 1, 0);

    function guardarEstadoNaUrl(dt) {
        var info = dt.page.info();
        var url = new URL(window.location.href);
        var termo = dt.search().trim();

        url.searchParams.set('pagina', info.page + 1);
        url.searchParams.set('por_pagina', info.length);
        if (termo) {
            url.searchParams.set('pesquisa', termo);
        } else {
            url.searchParams.delete('pesquisa');
        }
        window.history.replaceState(null, '', url);
    }

    if (tableElement.length && $.fn.DataTable) {
        tableElement.DataTable({
            serverSide: true,
            processing: true,
            searchDelay: 400,
            ordering: false,
            autoWidth: false,
            pageLength: tamanhoInicial,
            displayStart: paginaInicial * tamanhoInicial,
            search: { search: parametrosUrl.get('pesquisa') || '' },
            lengthMenu: [10, 25, 50, 100],
            ajax: {
                url: tableElement.data('url'),
                data: function (d) {
                    d.situacao = tableElement.data('situacao');
                }
            },
            columns: [
                {
                    data: 'rotulo',
                    render: function (data) {
                        var nome = escapeHtml(data);
                        return `<span class="fw-medium text-truncate d-inline-block texto-celula" title="${nome}">${nome}</span>`;
                    }
                },
                {
                    data: 'email',
                    render: function (data) {
                        if (!data) {
                            return '<span class="text-muted">—</span>';
                        }
                        var email = escapeHtml(data);
                        return `<span class="text-truncate d-inline-block texto-celula" title="${email}">${email}</span>`;
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
                            ? '<span class="text-success small fw-medium"><i class="mdi mdi-circle me-1 indicador-situacao"></i>Ativo</span>'
                            : '<span class="text-danger small fw-medium"><i class="mdi mdi-circle me-1 indicador-situacao"></i>Inativo</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center text-nowrap',
                    render: function (data, type, row) {
                        var userId = row.id;
                        var nome = escapeHtml(row.rotulo);
                        var email = escapeHtml(row.email || '');
                        var rawGrupos = escapeHtml(JSON.stringify(row.raw_grupos || []));

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
                                <!-- Rota /admin/acessos/{id}/permissoes removida na PLAN-55; botão mantido desabilitado até nova integração -->
                                <span data-bs-toggle="tooltip" title="Consulta de permissões pendente de nova integração">
                                    <button type="button" class="btn btn-inverse-primary button-trans-primary btn-icon"
                                        disabled aria-disabled="true" aria-label="Consulta de permissões indisponível para ${nome}">
                                        <i class="mdi mdi-shield-account"></i>
                                    </button>
                                </span>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                url: tableElement.data('traducao'),
                emptyTable: 'Nenhum usuário encontrado.'
            },
            drawCallback: function () {
                var api = this.api();
                initTooltips();
                aplicarDestaqueTabela(api);
                guardarEstadoNaUrl(api);
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

    var configuracaoContas = $('#configuracao-contas');

    function urlConta(template, userId) {
        return template.replace('__ID__', encodeURIComponent(userId));
    }

    function exibirAlerta(selector, mensagem, sucesso) {
        $(selector).removeClass('d-none alert-success alert-danger')
            .addClass(sucesso ? 'alert-success' : 'alert-danger').text(mensagem);
    }

    function atualizarTokenCsrf(resposta) {
        if (resposta && resposta.csrf_hash) {
            $('meta[name="X-CSRF-TOKEN"]').attr('content', resposta.csrf_hash);
        }
    }

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
        $('#editar-usuario-id').val(userId);
        $('#editar-usuario-nome, #editar-usuario-email, #editar-usuario-justificativa').val('').removeClass('is-invalid');
        $('#grupo-justificativa-email').addClass('d-none');
        $('#alerta-atualizar-usuario').addClass('d-none').text('');

        $.ajax({
            url: urlConta(configuracaoContas.data('consultar-url'), userId),
            type: 'GET',
            dataType: 'json'
        }).done(function (resposta) {
            if (!resposta.sucesso || !resposta.conta) {
                exibirAlerta('#alerta-atualizar-usuario', resposta.mensagem || 'Não foi possível carregar os dados da conta.', false);
                return;
            }

            $('#editar-usuario-nome').val(resposta.conta.nome);
            $('#editar-usuario-email').val(resposta.conta.email).data('email-original', resposta.conta.email);
        }).fail(function (xhr) {
            exibirAlerta('#alerta-atualizar-usuario', (xhr.responseJSON || {}).mensagem || 'Não foi possível carregar os dados da conta.', false);
        });
    });

    $('#editar-usuario-email').on('input', function () {
        var emailOriginal = String($(this).data('email-original') || '').trim().toLowerCase();
        var alterado = $(this).val().trim().toLowerCase() !== emailOriginal;
        $('#grupo-justificativa-email').toggleClass('d-none', !alterado);
        if (!alterado) {
            $('#editar-usuario-justificativa').val('').removeClass('is-invalid');
        }
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
        var justificativa = $('#editar-usuario-justificativa').val().trim();

        if (email.toLowerCase() !== String(emailInput.data('email-original') || '').trim().toLowerCase()
            && (justificativa.length < 15 || justificativa.length > 500)) {
            $('#editar-usuario-justificativa').addClass('is-invalid');
            return;
        }

        abrirConfirmacaoAdmin(
            'atualizar',
            userId,
            nome,
            email,
            'Salvar alterações cadastrais do usuário (nome completo e e-mail).',
            'modal-atualizar-usuario',
            { nome: nome, email: email, justificativa: justificativa }
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
    // Sem simulação de sucesso: exibe estado explícito até integração com endpoint real.
    $('#form-confirmar-senha-admin').on('submit', function (e) {
        e.preventDefault();
        var senhaAdmin = $('#admin-password-confirm').val();

        if (!senhaAdmin || senhaAdmin.trim() === '') {
            $('#admin-password-confirm').addClass('is-invalid');
            return;
        }
        $('#admin-password-confirm').removeClass('is-invalid');
        if (acaoPendente.tipo === 'atualizar') {
            var botao = $('#btn-executar-confirmacao');
            var dados = $.extend({}, acaoPendente.dadosExtras, { senha_admin: senhaAdmin });
            botao.prop('disabled', true);
            $('#spinner-confirmar-senha').removeClass('d-none');

            $.ajax({
                url: urlConta(configuracaoContas.data('atualizar-url'), acaoPendente.userId),
                type: 'POST',
                data: dados,
                dataType: 'json'
            }).done(function (resposta) {
                atualizarTokenCsrf(resposta);
                exibirAlerta('#alerta-confirmar-senha', resposta.mensagem || 'Não foi possível atualizar a conta.', resposta.sucesso);
                if (resposta.sucesso) {
                    tableElement.DataTable().ajax.reload(null, false);
                }
            }).fail(function (xhr) {
                var resposta = xhr.responseJSON || {};
                atualizarTokenCsrf(resposta);
                exibirAlerta('#alerta-confirmar-senha', resposta.mensagem || 'Não foi possível atualizar a conta.', false);
            }).always(function () {
                $('#admin-password-confirm').val('');
                $('#spinner-confirmar-senha').addClass('d-none');
                botao.prop('disabled', false);
            });
            return;
        }
        $('#admin-password-confirm').val('');

        $('#alerta-confirmar-senha')
            .removeClass('d-none alert-danger alert-success')
            .addClass('alert-warning')
            .text('Esta ação administrativa ainda aguarda integração com o servidor. Nenhuma alteração foi realizada.');
    });

    // Adicionar Usuário (Modal de Convite)
    $('#modal-adicionar-usuario').on('show.bs.modal', function () {
        $('#alerta-adicionar-usuario').addClass('d-none').text('');
        $('#convite-email').removeClass('is-invalid');
    });

    $('#form-adicionar-usuario').on('submit', function (e) {
        e.preventDefault();
        var emailInput = $('#convite-email');

        if (!emailInput.val().trim() || !emailInput[0].checkValidity()) {
            emailInput.addClass('is-invalid');
            return;
        }
        emailInput.removeClass('is-invalid');

        $('#alerta-adicionar-usuario')
            .removeClass('d-none alert-danger alert-success')
            .addClass('alert-warning')
            .text('O envio de convite ainda aguarda integração com o servidor. Nenhum convite foi enviado.');
    });
});
