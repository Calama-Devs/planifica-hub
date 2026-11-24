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

    // Confirmação de senha: a senha viaja no mesmo POST que executa a operação. O servidor confere a sessão, a
    // permissão, a senha do próprio operador e o limite de tentativas; só depois altera algo. Cancelar a modal
    // não envia nada.
    var modalConfirmacao = $('#modal-confirmar-senha-admin');
    var campoSenhaAdmin = $('#admin-password-confirm');
    var enviandoConfirmacao = false;

    // Endpoint de cada tipo de ação da modal. Tipos sem endpoint ainda não existem no servidor: a senha não é enviada.
    function montarRequisicao(acao) {
        var base = String(tableElement.data('url-contas')).replace(/\/+$/, '') + '/' + encodeURIComponent(acao.userId);

        if (acao.tipo === 'desativar' || acao.tipo === 'reativar') {
            return { url: base + '/' + acao.tipo, dados: {} };
        }

        if (acao.tipo === 'atualizar' && acao.dadosExtras) {
            return { url: base, dados: { nome: acao.dadosExtras.nome, email: acao.dadosExtras.email } };
        }

        return null;
    }

    function exibirAlertaConfirmacao(classe, mensagem) {
        $('#alerta-confirmar-senha')
            .removeClass('d-none alert-danger alert-success alert-warning')
            .addClass(classe)
            .text(mensagem);
    }

    function definirEnvioConfirmacao(emAndamento) {
        enviandoConfirmacao = emAndamento;
        $('#btn-executar-confirmacao').prop('disabled', emAndamento);
        $('#btn-cancelar-confirmacao').prop('disabled', emAndamento);
        $('#spinner-confirmar-senha').toggleClass('d-none', !emAndamento);
    }

    // O token CSRF é regenerado a cada envio: a meta tag e os campos ocultos precisam do valor novo.
    function atualizarCsrf(resposta) {
        var csrf = resposta && resposta.csrf ? resposta.csrf : null;
        var hash = csrf ? csrf.hash : (resposta ? resposta.csrf_hash : null);

        if (!hash) {
            return;
        }

        var nome = csrf && csrf.nome ? csrf.nome : $('#form-confirmar-senha-admin input[type="hidden"]').first().attr('name');

        $('meta[name="X-CSRF-TOKEN"]').attr('content', hash);
        $('input[type="hidden"]').filter(function () {
            return this.name === nome;
        }).val(hash);
    }

    function aoConcluirConfirmacao(resposta) {
        exibirAlertaConfirmacao('alert-success', resposta.mensagem || 'Operação concluída.');
        $('#btn-cancelar-confirmacao').text('Fechar');

        if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().ajax.reload(null, false);
        }
    }

    function exibirFalhaConfirmacao(resposta) {
        exibirAlertaConfirmacao(
            'alert-danger',
            resposta && resposta.mensagem ? resposta.mensagem : 'Não foi possível concluir. Recarregue a página e tente novamente.'
        );
    }

    function aoFalharConfirmacao(requisicao, situacao) {
        var resposta = requisicao.responseJSON;
        atualizarCsrf(resposta);

        // Sessão encerrada: o servidor indica o destino; sem JSON, a página protegida leva ao login.
        if (resposta && resposta.redirecionamento) {
            window.location.assign(resposta.redirecionamento);
            return;
        }

        if (requisicao.status === 401 || situacao === 'parsererror') {
            window.location.reload();
            return;
        }

        exibirFalhaConfirmacao(resposta);
    }

    $('#form-confirmar-senha-admin').on('submit', function (e) {
        e.preventDefault();
        if (enviandoConfirmacao) {
            return;
        }

        var senhaAdmin = campoSenhaAdmin.val();

        if (!senhaAdmin || senhaAdmin.trim() === '') {
            campoSenhaAdmin.addClass('is-invalid');
            return;
        }
        campoSenhaAdmin.removeClass('is-invalid').val('');

        var requisicao = acaoPendente.tipo ? montarRequisicao(acaoPendente) : null;

        if (!requisicao) {
            senhaAdmin = null;
            exibirAlertaConfirmacao('alert-warning', 'Esta operação ainda não está disponível. Nenhuma alteração foi realizada.');
            return;
        }

        $('#alerta-confirmar-senha').addClass('d-none').text('');
        definirEnvioConfirmacao(true);
        var concluida = false;

        $.ajax({
            url: requisicao.url,
            type: 'POST',
            data: $.extend({ senha_admin: senhaAdmin }, requisicao.dados),
            dataType: 'json'
        }).done(function (resposta) {
            atualizarCsrf(resposta);

            // HTTP 2xx não basta: só o retorno estruturado com sucesso verdadeiro conclui a operação.
            if (!resposta || resposta.sucesso !== true) {
                exibirFalhaConfirmacao(resposta);
                return;
            }

            concluida = true;
            aoConcluirConfirmacao(resposta);
        }).fail(aoFalharConfirmacao).always(function () {
            senhaAdmin = null;
            campoSenhaAdmin.val('');
            definirEnvioConfirmacao(false);

            if (concluida) {
                $('#btn-executar-confirmacao').prop('disabled', true);
            } else {
                campoSenhaAdmin.trigger('focus');
            }
        });
    });

    modalConfirmacao.on('shown.bs.modal', function () {
        campoSenhaAdmin.trigger('focus');
    }).on('hide.bs.modal', function (evento) {
        if (enviandoConfirmacao) {
            evento.preventDefault();
        }
    }).on('hidden.bs.modal', function () {
        // Cancelar ou fechar não envia nada ao servidor: só limpa o campo e o contexto da ação pendente.
        campoSenhaAdmin.val('').removeClass('is-invalid');
        $('#alerta-confirmar-senha').addClass('d-none').text('');
        $('#btn-cancelar-confirmacao').text('Cancelar');
        acaoPendente.tipo = null;
        acaoPendente.userId = null;
        acaoPendente.dadosExtras = null;
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
