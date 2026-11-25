<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Gerenciamento de Usuários<?= $this->endSection() ?>

<?= $this->section('estilos') ?>
<link rel="stylesheet" href="<?= esc(base_url('assets/vendor/datatables.net-bs4/dataTables.bootstrap4.css'), 'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('assets/css/acesso/index.css'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/css/acesso/index.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<?php if ($podeCriarConta) : ?>
    <?= $this->include('acesso/modals/modal-adicionar-usuario') ?>
<?php endif ?>
<?php if ($podeAdministrarAcessos) : ?>
<?= $this->include('acesso/modals/modal-atualizar-usuario') ?>
<?= $this->include('acesso/modals/modal-resetar-senha') ?>
<?= $this->include('acesso/modals/modal-desativar-usuario') ?>
<?= $this->include('acesso/modals/modal-reativar-usuario') ?>
<?= $this->include('acesso/modals/modal-alterar-grupos') ?>
<?= $this->include('acesso/modals/modal-confirmar-senha-admin') ?>
<?php endif ?>

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

<div class="row">
    <div class="col-12 col-md-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Ações</h4>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($podeCriarConta) : ?>
                        <button type="button" class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#modal-adicionar-usuario">
                            <i class="mdi mdi-account-plus btn-icon-prepend" aria-hidden="true"></i> Adicionar Usuário
                        </button>
                    <?php endif ?>

                    <?php if ($podeAdministrarAcessos) : ?>
                        <?php if ($situacao === 'inativos') : ?>
                            <a href="<?= esc(url_to('acessos.index'), 'attr') ?>?situacao=ativos" class="btn btn-secondary btn-icon-text">
                                <i class="mdi mdi-account-check btn-icon-prepend" aria-hidden="true"></i> Usuários Ativos
                            </a>
                        <?php else : ?>
                            <a href="<?= esc(url_to('acessos.index'), 'attr') ?>?situacao=inativos" class="btn btn-secondary btn-icon-text">
                                <i class="mdi mdi-account-off btn-icon-prepend" aria-hidden="true"></i> Usuários Inativos
                            </a>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($podeAdministrarAcessos) : ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabela-acessos" id="tabela-usuarios"
                           aria-label="Listagem e gerenciamento de usuários"
                           data-url="<?= esc(url_to('acessos.listar'), 'attr') ?>"
                           data-situacao="<?= esc($situacao, 'attr') ?>"
                           data-traducao="<?= esc(base_url('assets/js/traducao-dataTable/pt_br.json'), 'attr') ?>">
                        <thead>
                            <tr>
                                <th scope="col" class="coluna-nome">Nome</th>
                                <th scope="col" class="coluna-email">E-mail</th>
                                <th scope="col" class="coluna-grupos">Grupo(s)</th>
                                <th scope="col" class="text-center coluna-situacao">Situação</th>
                                <th scope="col" class="text-center coluna-acoes">Ações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($podeAdministrarAcessos) : ?>
<script src="<?= esc(base_url('assets/vendor/datatables.net/jquery.dataTables.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/vendor/datatables.net-bs4/dataTables.bootstrap4.js'), 'attr') ?>"></script>
<?php endif ?>
<script src="<?= esc(base_url('assets/js/acesso/index.js'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/js/acesso/index.js') ?>"></script>
<?= $this->endSection() ?>
