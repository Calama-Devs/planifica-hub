<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?= csrf_meta() ?>
    <title><?= $this->renderSection('titulo') ?: $this->renderSection('title') ?> | PlanIFica Hub</title>

    <link rel="stylesheet" href="<?= base_url('assets/vendors/mdi/css/materialdesignicons.min.css?v=' . (file_exists(FCPATH . 'assets/vendors/mdi/css/materialdesignicons.min.css') ? filemtime(FCPATH . 'assets/vendors/mdi/css/materialdesignicons.min.css') : '1')) ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/modern-vertical/style.css?v=' . (file_exists(FCPATH . 'assets/css/modern-vertical/style.css') ? filemtime(FCPATH . 'assets/css/modern-vertical/style.css') : '1')) ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css?v=' . (file_exists(FCPATH . 'assets/css/custom.css') ? filemtime(FCPATH . 'assets/css/custom.css') : '1')) ?>">
    <?= $this->renderSection('estilos') ?>
    <?= $this->renderSection('pageStyles') ?>

    <link rel="shortcut icon" href="<?= base_url('assets/images/logo-ifro-mini.png') ?>">
</head>
<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="row w-100 m-0">
                <main class="content-wrapper full-page-wrapper d-flex align-items-center auth auth-bg-custom">
                    <?= $this->renderSection('conteudo') ?>
                    <?= $this->renderSection('main') ?>
                </main>
            </div>
        </div>
    </div>

    <?= $this->renderSection('scripts') ?>
    <?= $this->renderSection('pageScripts') ?>
</body>
</html>
