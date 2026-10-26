<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?><?= esc($title ?? 'Dashboard') ?><?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title"><?= esc($title ?? 'Dashboard') ?></h4>
                <p class="text-muted mb-0"><?= esc($description ?? 'Visão geral do sistema.') ?></p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
