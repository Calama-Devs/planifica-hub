<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Criar grupo<?= $this->endSection() ?>

<?= $this->section('estilos') ?>
<link rel="stylesheet" href="<?= esc(base_url('assets/css/permissao/formulario_grupo.css'), 'attr') ?>?v=<?= esc($versaoCss, 'attr') ?>">
<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="page-header mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="icone-cabecalho-grupo d-flex align-items-center justify-content-center">
            <i class="mdi mdi-account-group" aria-hidden="true"></i>
        </div>
        <div>
            <h3 class="page-title mb-1">Criar grupo</h3>
            <p class="text-muted small mb-0">Defina o nome, a descrição e as permissões do grupo.</p>
        </div>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?= esc(base_url(), 'attr') ?>" class="link-saida-protegida">Início</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= esc($urlVoltar, 'attr') ?>" class="link-saida-protegida">Grupos e permissões</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Criar grupo</li>
        </ol>
    </nav>
</div>

<?= $this->include('permissao/partials/_formulario_grupo') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('assets/js/permissao/formulario_grupo.js'), 'attr') ?>?v=<?= esc($versaoJs, 'attr') ?>"></script>
<?= $this->endSection() ?>
