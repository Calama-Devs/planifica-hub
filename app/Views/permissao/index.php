<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Grupos e permissões<?= $this->endSection() ?>

<?= $this->section('estilos') ?>
<link rel="stylesheet" href="<?= esc(base_url('assets/vendor/datatables.net-bs4/dataTables.bootstrap4.css'), 'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('assets/css/permissao/index.css'), 'attr') ?>?v=<?= esc($versaoCss, 'attr') ?>">
<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="page-header mb-3">
    <h3 class="page-title">Grupos e permissões</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= esc(base_url(), 'attr') ?>">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Grupos e permissões</li>
        </ol>
    </nav>
</div>

<p class="text-muted mb-3">
    Consulte os grupos cadastrados, a quantidade de usuários vinculados e os pontos de entrada administrativos.
</p>

<div class="row">
    <div class="col-12 col-md-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body acoes-cabecalho">
                <h4 class="card-title">Ações</h4>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= esc($destinoCriarGrupo, 'attr') ?>"
                       class="btn btn-primary btn-icon-text"
                       data-destino="<?= esc($destinoCriarGrupo, 'attr') ?>"
                       data-bs-toggle="tooltip" title="Criar grupo">
                        <i class="mdi mdi-account-multiple-plus btn-icon-prepend" aria-hidden="true"></i>
                        Criar grupo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div id="alerta-erro-grupos" class="alert alert-danger d-none d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert" aria-live="polite">
                    <span id="mensagem-erro-grupos">Não foi possível carregar os grupos. Tente novamente.</span>
                    <button type="button" id="btn-tentar-novamente-grupos" class="btn btn-sm btn-outline-light">
                        Tentar novamente
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabela-grupos" id="tabela-grupos"
                           aria-label="Listagem de grupos e permissões"
                           data-url="<?= esc($urlListar, 'attr') ?>"
                           data-traducao="<?= esc(base_url('assets/js/traducao-dataTable/pt_br.json'), 'attr') ?>">
                        <thead>
                            <tr>
                                <th scope="col" class="coluna-grupo">Grupo</th>
                                <th scope="col" class="coluna-descricao">Descrição</th>
                                <th scope="col" class="text-center coluna-usuarios">Usuários vinculados</th>
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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('assets/vendor/datatables.net/jquery.dataTables.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/vendor/datatables.net-bs4/dataTables.bootstrap4.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('assets/js/permissao/index.js'), 'attr') ?>?v=<?= esc($versaoJs, 'attr') ?>"></script>
<?= $this->endSection() ?>
