$(document).ready(function () {
    var $tabela = $('#tabela-grupos');
    var $alertaErro = $('#alerta-erro-grupos');
    var $mensagemErro = $('#mensagem-erro-grupos');

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initTooltips() {
        $('[data-bs-toggle="tooltip"]').each(function () {
            var atual = bootstrap.Tooltip.getInstance(this);
            if (atual) atual.dispose();
            new bootstrap.Tooltip(this, { container: 'body' });
        });
    }

    function destacarBusca(dt) {
        var termo = (dt.search() || '').trim();
        var $badge = $('#badge-termo-pesquisa-grupos');

        if (!$badge.length && $('.dataTables_filter input').length) {
            $badge = $('<span id="badge-termo-pesquisa-grupos" class="highlight-search ms-2 d-none"></span>')
                .appendTo('.dataTables_filter label');
        }

        document.querySelectorAll('#tabela-grupos tbody td:nth-child(1), #tabela-grupos tbody td:nth-child(2)')
            .forEach(function (celula) {
                celula.querySelectorAll('span.highlight-search').forEach(function (m) {
                    m.replaceWith(document.createTextNode(m.textContent));
                });
                celula.normalize();

                if (!termo) return;
                var regex = new RegExp('(' + termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                var walker = document.createTreeWalker(celula, NodeFilter.SHOW_TEXT);
                var nos = [];
                while (walker.nextNode()) nos.push(walker.currentNode);

                nos.forEach(function (no) {
                    var partes = no.nodeValue.split(regex);
                    if (partes.length < 2) return;
                    var frag = document.createDocumentFragment();
                    partes.forEach(function (parte, i) {
                        if (!parte) return;
                        if (i % 2 === 1) {
                            var span = document.createElement('span');
                            span.className = 'highlight-search';
                            span.textContent = parte;
                            frag.appendChild(span);
                        } else {
                            frag.appendChild(document.createTextNode(parte));
                        }
                    });
                    no.parentNode.replaceChild(frag, no);
                });
            });

        $badge.text(termo).toggleClass('d-none', !termo);
    }

    function botaoAcao(titulo, classeCor, icone, ariaLabel, destino) {
        var attrDestino = destino ? ` data-destino="${escapeHtml(destino)}"` : '';
        return `<span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="${escapeHtml(titulo)}">
            <button type="button" class="btn btn-inverse-${classeCor} button-trans-${classeCor} btn-icon"
                disabled aria-disabled="true"${attrDestino} aria-label="${escapeHtml(ariaLabel)}">
                <i class="mdi ${icone}" aria-hidden="true"></i>
            </button>
        </span>`;
    }

    function guardarEstadoNaUrl(dt) {
        var info = dt.page.info();
        var url = new URL(window.location.href);
        var termo = dt.search().trim();

        url.searchParams.set('pagina', info.page + 1);
        url.searchParams.set('por_pagina', info.length);
        termo ? url.searchParams.set('pesquisa', termo) : url.searchParams.delete('pesquisa');
        window.history.replaceState(null, '', url);
    }

    if (!$tabela.length || !$.fn.DataTable) {
        initTooltips();
        return;
    }

    $.fn.dataTable.ext.errMode = 'none';

    var params = new URLSearchParams(window.location.search);
    var porPagina = parseInt(params.get('por_pagina'), 10) || 10;
    var pagina = Math.max((parseInt(params.get('pagina'), 10) || 1) - 1, 0);

    var dt = $tabela.DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        ordering: false,
        autoWidth: false,
        pageLength: porPagina,
        displayStart: pagina * porPagina,
        search: { search: params.get('pesquisa') || '' },
        lengthMenu: [10, 25, 50, 100],
        ajax: {
            url: $tabela.data('url'),
            error: function (xhr) {
                var msg = xhr?.responseJSON?.mensagem || 'Não foi possível carregar os grupos. Tente novamente.';
                $mensagemErro.text(msg);
                $alertaErro.removeClass('d-none');
            }
        },
        columns: [
            {
                data: 'nome',
                render: function (data, type, row) {
                    var protegido = row.protegido
                        ? ' <i class="mdi mdi-shield-lock-outline text-warning icone-protegido" data-bs-toggle="tooltip" title="Grupo protegido do sistema" aria-label="Grupo protegido do sistema"></i>'
                        : '';
                    var status = row.ativo
                        ? '<span class="text-success small"><i class="mdi mdi-circle me-1 indicador-situacao" aria-hidden="true"></i>Ativo</span>'
                        : '<span class="text-danger small"><i class="mdi mdi-circle me-1 indicador-situacao" aria-hidden="true"></i>Bloqueado</span>';
                    return `<div><div class="fw-medium text-white">${escapeHtml(data)}${protegido}</div><div>${status}</div></div>`;
                }
            },
            {
                data: 'descricao',
                render: function (data) {
                    return data ? escapeHtml(data) : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'usuarios_vinculados',
                className: 'text-center',
                render: function (data, type, row) {
                    var total = parseInt(data, 10) || 0;
                    var texto = total === 0 ? 'Nenhum usuário' : (total === 1 ? '1 usuário' : total + ' usuários');
                    var destino = escapeHtml(row.destinos?.integrantes || '');
                    return `<span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Ver integrantes">
                        <button type="button" class="btn btn-link p-0 link-integrantes" disabled aria-disabled="true"
                            data-destino="${destino}" aria-label="${escapeHtml(texto)} em ${escapeHtml(row.nome)}">
                            ${escapeHtml(texto)}
                        </button>
                    </span>`;
                }
            },
            {
                data: null,
                className: 'text-center text-nowrap',
                render: function (data, type, row) {
                    var nome = row.nome || '';
                    var bloqueio = row.ativo ? 'Bloquear grupo' : 'Desbloquear grupo';
                    var iconeBloqueio = row.ativo ? 'mdi-lock-outline' : 'mdi-lock-open-outline';
                    return `<div class="d-flex align-items-center justify-content-center">
                        ${botaoAcao('Editar grupo', 'success', 'mdi-pencil', 'Editar grupo ' + nome, row.destinos?.editar)}
                        ${botaoAcao('Ver integrantes', 'info', 'mdi-account-group', 'Ver integrantes do grupo ' + nome, row.destinos?.integrantes)}
                        ${botaoAcao(bloqueio, 'warning', iconeBloqueio, bloqueio + ' ' + nome)}
                        ${botaoAcao('Excluir grupo', 'danger', 'mdi-delete-outline', 'Excluir grupo ' + nome)}
                    </div>`;
                }
            }
        ],
        language: {
            url: $tabela.data('traducao'),
            searchPlaceholder: 'Nome ou descrição...',
            processing: 'Carregando grupos...',
            emptyTable: 'Nenhum grupo cadastrado.',
            zeroRecords: 'Nenhum grupo encontrado para a busca informada.'
        },
        drawCallback: function () {
            var api = this.api();
            initTooltips();
            destacarBusca(api);
            guardarEstadoNaUrl(api);
        }
    });

    $tabela.on('xhr.dt', function (e, settings, json) {
        if (json && Array.isArray(json.data)) $alertaErro.addClass('d-none');
    });

    $('#btn-tentar-novamente-grupos').on('click', function () {
        $alertaErro.addClass('d-none');
        dt.ajax.reload(null, false);
    });
});
