<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?= csrf_meta() ?>
    <title><?= $this->renderSection('titulo') ?> | PlanIFica Hub</title>

    <link rel="stylesheet" href="<?= base_url('assets/vendors/mdi/css/materialdesignicons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendors/css/vendor.bundle.base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/modern-vertical/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
    <?= $this->renderSection('estilos') ?>

    <link rel="shortcut icon" href="<?= base_url('assets/images/logo-ifro-mini.png') ?>">
</head>
<body>
    <div class="container-scroller">
        <?= $this->include('partials/menu') ?>

        <div class="container-fluid page-body-wrapper">
            <?= $this->include('partials/cabecalho') ?>

            <div class="main-panel">
                <main class="content-wrapper">
                    <?= $this->include('partials/mensagens') ?>
                    <?= $this->renderSection('conteudo') ?>
                </main>

                <?= $this->include('partials/rodape') ?>
            </div>
        </div>
    </div>

    <script src="<?= base_url('assets/vendors/js/vendor.bundle.base.js') ?>"></script>
    <script src="<?= base_url('assets/js/off-canvas.js') ?>"></script>
    <script src="<?= base_url('assets/js/hoverable-collapse.js') ?>"></script>
    <script src="<?= base_url('assets/js/misc.js') ?>"></script>
    <script src="<?= base_url('assets/js/custom.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
