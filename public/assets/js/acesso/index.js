(function ($) {
    'use strict';

    $(function () {
        const campo = document.getElementById('pesquisa');
        const mensagem = $('#pesquisa-mensagem');
        const resultado = $('#resultado-pesquisa');
        const corpoTabela = $('#contas-encontradas');
        const paginacao = $('#paginacao-contas');
        let requisicao = null;
        let temporizador = null;
        let versaoPesquisa = 0;
        let termoAtual = '';
        let carregando = false;
        let erroPesquisa = false;

        const seletor = new TomSelect(campo, {
            create: false,
            maxItems: 1,
            valueField: 'id',
            labelField: 'rotulo',
            searchField: [],
            refreshThrottle: 0,
            closeAfterSelect: false,
            controlClass: 'ts-control text-light py-2 shadow-none',
            dropdownClass: 'ts-dropdown text-light',
            controlInput: '<input type="text" class="form-control text-light h-auto" autocomplete="off" size="1">',
            onType: function (texto) {
                agendarPesquisa(texto);
            },
            onChange: function (valor) {
                if (valor) {
                    cancelarPesquisa();
                    mostrarContas([this.options[valor]]);
                    paginacao.empty();
                    mensagem.text('1 conta selecionada.');
                    this.clear(true);
                    this.close(false);
                    this.setTextboxValue(termoAtual);
                } else {
                    limparPesquisa();
                }
            },
            onFocus: function () {
                $(this.wrapper).addClass('border-success');
                if (!this.getValue() && !Object.keys(this.options).length && !carregando) {
                    agendarPesquisa(this.inputValue());
                }
            },
            onBlur: function () {
                $(this.wrapper).removeClass('border-success');
                this.setTextboxValue(termoAtual);
            },
            onDropdownClose: function () {
                if (!this.getValue()) {
                    this.setTextboxValue(termoAtual);
                }
            },
            render: {
                option: function (conta) {
                    return $('<div>').text(conta.rotulo + (conta.email ? ' — ' + conta.email : ''))[0];
                },
                item: function (conta) {
                    return $('<div>').text(conta.rotulo)[0];
                },
                no_results: function () {
                    let texto = 'Nenhuma conta encontrada.';
                    if (carregando) {
                        texto = 'Pesquisando contas...';
                    } else if (erroPesquisa) {
                        texto = 'Não foi possível pesquisar. Tente novamente.';
                    }
                    return $('<div>').addClass('no-results').text(texto)[0];
                },
                not_loading: function () {
                    return $('<div>').addClass('no-results').text('Digite um nome ou e-mail.')[0];
                }
            }
        });

        $(seletor.control_input).attr({ 'maxlength': 254, 'aria-describedby': 'pesquisa-ajuda' });
        $(seletor.control).on('mousedown', function (evento) {
            if (seletor.control_input.value !== '') {
                evento.stopPropagation();
                seletor.focus();
            }
        });

        $('#limpar-pesquisa').on('click', function () {
            limparPesquisa();
            seletor.focus();
        });

        paginacao.on('click', 'a.page-link', function (evento) {
            evento.preventDefault();
            const pagina = Number(new URL(this.href).searchParams.get('page') || 1);
            cancelarPesquisa();
            consultarContas(termoAtual, pagina);
        });

        function cancelarPesquisa() {
            versaoPesquisa++;
            window.clearTimeout(temporizador);
            if (requisicao) {
                requisicao.abort();
                requisicao = null;
            }
            carregando = false;
            resultado.attr('aria-busy', 'false');
        }

        function limparPesquisa() {
            seletor.clear(true);
            seletor.setTextboxValue('');
            agendarPesquisa('', 0);
        }

        function agendarPesquisa(texto, espera = 300) {
            cancelarPesquisa();
            termoAtual = texto.trim();
            seletor.clearOptions();
            erroPesquisa = false;
            carregando = true;
            seletor.refreshOptions(false);
            resultado.attr('aria-busy', 'true');
            mensagem.removeClass('text-danger').addClass('text-muted').text('Pesquisando contas...');
            temporizador = window.setTimeout(function () {
                consultarContas(termoAtual, 1);
            }, espera);
        }

        function consultarContas(pesquisa, pagina) {
            const versao = versaoPesquisa;
            carregando = true;
            resultado.attr('aria-busy', 'true');
            mensagem.removeClass('text-danger').addClass('text-muted').text('Pesquisando contas...');

            requisicao = $.ajax({
                url: campo.dataset.url,
                method: 'GET',
                dataType: 'json',
                data: { pesquisa: pesquisa, page: pagina }
            }).done(function (resposta) {
                if (versao !== versaoPesquisa) {
                    return;
                }
                if (!resposta.sucesso || !Array.isArray(resposta.contas)) {
                    mostrarErro('Não foi possível pesquisar as contas. Tente novamente.');
                    return;
                }
                carregando = false;
                seletor.clearOptions();
                seletor.addOptions(resposta.contas);
                seletor.refreshOptions(seletor.isFocused);
                mostrarContas(resposta.contas);
                mostrarPaginacao(resposta.paginacao);
                const total = resposta.paginacao.total_contas;
                mensagem.text(total === 0 ? resposta.mensagem : total + (total === 1 ? ' conta encontrada.' : ' contas encontradas.'));
            }).fail(function (resposta, estado) {
                if (versao === versaoPesquisa && estado !== 'abort') {
                    const texto = resposta.responseJSON && resposta.responseJSON.mensagem;
                    mostrarErro(texto || 'Não foi possível pesquisar as contas. Tente novamente.');
                }
            }).always(function () {
                if (versao === versaoPesquisa) {
                    carregando = false;
                    requisicao = null;
                    resultado.attr('aria-busy', 'false');
                }
            });
        }

        function mostrarErro(texto) {
            carregando = false;
            erroPesquisa = true;
            seletor.clearOptions();
            seletor.refreshOptions(false);
            corpoTabela.empty().append($('<tr>').append(
                $('<td>').attr('colspan', 5).addClass('text-center py-4').text('Pesquisa indisponível.')
            ));
            paginacao.empty();
            mensagem.removeClass('text-muted').addClass('text-danger').text(texto);
        }

        function mostrarContas(contas) {
            corpoTabela.empty();
            if (!contas.length) {
                corpoTabela.append($('<tr>').append(
                    $('<td>').attr('colspan', 5).addClass('text-center text-muted py-4').text('Nenhuma conta encontrada.')
                ));
                return;
            }
            contas.forEach(function (conta) {
                const linha = $('<tr>');
                const grupos = $('<td>');
                if (!conta.grupos.length) {
                    grupos.append($('<span>').addClass('text-muted').text('Nenhum'));
                }
                conta.grupos.forEach(function (grupo) {
                    grupos.append($('<span>').addClass('badge badge-outline-info me-1').text(grupo));
                });
                const acao = $('<a>').attr({
                    href: conta.url_permissoes,
                    'aria-label': 'Ver permissões de ' + conta.rotulo
                }).addClass('btn btn-sm btn-outline-primary').text(' Permissões');
                acao.prepend($('<i>').addClass('mdi mdi-key-variant').attr('aria-hidden', 'true'));
                linha.append(
                    $('<td>').append($('<a>').attr('href', conta.url_permissoes).text(conta.rotulo)),
                    $('<td>').text(conta.email),
                    grupos,
                    $('<td>').addClass('text-center').text(conta.total_diretas),
                    $('<td>').addClass('text-end').append(acao)
                );
                corpoTabela.append(linha);
            });
        }

        function mostrarPaginacao(paginas) {
            paginacao.empty();
            if (paginas.total_paginas <= 1) {
                return;
            }
            const lista = $('<ul>').addClass('pagination mb-0 align-items-center');
            lista.append(criarLinkPagina('Anterior', paginas.pagina_atual - 1, paginas.pagina_atual > 1));
            lista.append($('<li>').addClass('page-item disabled').append(
                $('<span>').addClass('page-link').text('Página ' + paginas.pagina_atual + ' de ' + paginas.total_paginas)
            ));
            lista.append(criarLinkPagina('Próxima', paginas.pagina_atual + 1, paginas.pagina_atual < paginas.total_paginas));
            paginacao.append($('<nav>').attr('aria-label', 'Navegação entre páginas').append(lista));
        }

        function criarLinkPagina(texto, pagina, habilitado) {
            const item = $('<li>').addClass('page-item');
            if (!habilitado) {
                return item.addClass('disabled').append($('<span>').addClass('page-link').text(texto));
            }
            const endereco = new URL(campo.dataset.url);
            endereco.searchParams.set('page', pagina);
            return item.append($('<a>').addClass('page-link').attr('href', endereco.href).text(texto));
        }
    });
})(jQuery);
