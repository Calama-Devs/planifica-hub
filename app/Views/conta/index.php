<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Gerenciar usuários<?= $this->endSection() ?>

<?= $this->section('estilos') ?>
<link rel="stylesheet" href="<?= esc(base_url('assets/vendors/font-awesome/css/font-awesome.min.css'), 'attr') ?>">
<link rel="stylesheet"
    href="<?= esc(base_url('assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css'), 'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('assets/vendors/jquery-toast-plugin/jquery.toast.min.css'), 'attr') ?>">
<style>
    /* Aviso de e-mail: fundo escuro translúcido com borda e texto âmbar */
    #modal-atualizar-conta .alert-warning {
        background-color: rgba(255, 175, 0, 0.12);
        border: 1px solid rgba(255, 175, 0, 0.55);
        color: #ffd166;
    }

    /* Textos auxiliares e rótulo do checkbox */
    #modal-atualizar-conta .form-text,
    #modal-atualizar-conta .form-check-label {
        color: #c4c9d4;
    }

    /* Mensagem de erro do modal */
    #modal-atualizar-conta .alert-danger {
        background-color: rgba(252, 66, 74, 0.12);
        border: 1px solid rgba(252, 66, 74, 0.55);
        color: #ff8a90;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<?php $jsonEncodeFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; ?>
<?= view('components/conta/modal-atualizar-conta') ?>

<div class="page-header">
    <h3 class="page-title">GERENCIAR USUÁRIOS</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url_to('dashboard') ?>">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Lista de Usuários</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Filtros</h4>
                <div class="row">
                    <div class="col-12">
                        <label for="filtro-grupo" class="form-label">Grupo de usuários</label>
                        <select id="filtro-grupo" class="form-control">
                            <option value="">Todos os grupos</option>
                            <?php foreach ($grupos as $chaveGrupo => $grupo) : ?>
                                <option value="<?= esc($chaveGrupo, 'attr') ?>"><?= esc($grupo['title']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <table class="table table-hover table-striped mb-4" id="tabela-usuarios" aria-label="Lista de usuários">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Grupo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-12 mt-4 d-flex justify-content-end">Legenda</div>
            <div class="col-12 mt-4 d-flex justify-content-end gap-3">
                <p class="card-description text-end">
                    <i class="fa fa-edit text-success me-2"></i>Editar dados da conta:
                    atualizar nome e, excepcionalmente, e-mail
                </p>
                <p class="card-description text-end">
                    <i class="fa fa-user text-info me-2"></i>Meu Perfil: acessar a própria conta
                </p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('assets/vendors/datatables.net/jquery.dataTables.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/vendors/datatables.net-bs4/dataTables.bootstrap4.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/vendors/jquery-toast-plugin/jquery.toast.min.js'), 'attr') ?>"></script>
<script>
    var table;

    function escapeHtml(valor) {
        if (valor === null || valor === undefined) {
            return '';
        }

        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.planificaTableScrollDom = window.planificaTableScrollDom || [
        "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>",
        "<'row'<'col-sm-12't>>",
        "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    ].join('');

    $(document).ready(function() {
        var operadorId = <?= (int) $operadorId ?>;
        var urlTabela = <?= json_encode(url_to('contas.tabela'), $jsonEncodeFlags) ?>;
        var urlBaseConta = <?= json_encode(site_url('admin/contas'), $jsonEncodeFlags) ?>;
        var urlPerfil = escapeHtml(<?= json_encode(site_url('perfil'), $jsonEncodeFlags) ?>);

        table = $('#tabela-usuarios').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: urlTabela,
                dataSrc: 'data',
                data: function(dados) {
                    dados.grupo = $('#filtro-grupo').val();
                }
            },
            columns: [
                { data: 'username', render: $.fn.dataTable.render.text() },
                { data: 'email', searchable: false, render: $.fn.dataTable.render.text() },
                { data: 'grupo', searchable: false, render: $.fn.dataTable.render.text() },
                { data: null }
            ],
            columnDefs: [{
                orderable: false,
                searchable: false,
                targets: 3,
                render: function(dados, tipo, linha) {
                    if (tipo !== 'display') {
                        return '';
                    }

                    if (Number(linha.id) === operadorId) {
                        return `
                            <div class="d-flex">
                                <span data-bs-toggle="tooltip" data-placement="top" title="Acessar Meu Perfil">
                                    <a href="${urlPerfil}"
                                        class="btn button-trans-info btn-icon me-1
                                            d-flex align-items-center justify-content-center"
                                        aria-label="Acessar Meu Perfil">
                                        <i class="fa fa-user"></i>
                                    </a>
                                </span>
                            </div>
                        `;
                    }

                    var id = escapeHtml(linha.id);
                    var nome = escapeHtml(linha.username);
                    var url = escapeHtml(urlBaseConta + '/' + encodeURIComponent(linha.id));

                    return `
                        <div class="d-flex">
                            <span data-bs-toggle="tooltip" data-placement="top" title="Atualizar dados do usuário">
                                <button type="button"
                                    class="btn button-trans-success btn-icon me-1 btn-editar-conta
                                        d-flex align-items-center justify-content-center"
                                    data-bs-toggle="modal" data-bs-target="#modal-atualizar-conta"
                                    data-conta-id="${id}" data-consultar-url="${url}" data-salvar-url="${url}"
                                    aria-label="Editar dados de ${nome}">
                                    <i class="fa fa-edit"></i>
                                </button>
                            </span>
                        </div>
                    `;
                }
            }],
            dom: window.planificaTableScrollDom,
            language: {
                search: 'Pesquisar:',
                url: <?= json_encode(base_url('assets/js/traducao-dataTable/pt_br.json'), $jsonEncodeFlags) ?>,
                emptyTable: 'Nenhum usuário cadastrado.'
            },
            aLengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            pageLength: 10,
            stateSave: true,
            stateLoadParams: function(settings, estado) {
                if (estado.length < 1 || estado.length > 100) {
                    estado.length = 10;
                }
            },
            order: [[0, 'asc']],
            drawCallback: function() {
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        });

        $('#filtro-grupo').on('change', function() {
            table.ajax.reload();
        });
    });
</script>
<script src="<?= esc(base_url('assets/js/contas.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>