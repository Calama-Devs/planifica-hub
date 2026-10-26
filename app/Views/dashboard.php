<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?= csrf_meta() ?>
    <title><?= esc($title ?? 'PlanIFica Hub - by Calama Devs') ?></title>

    <link rel="stylesheet" href="<?= base_url('assets/vendors/mdi/css/materialdesignicons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendors/css/vendor.bundle.base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/modern-vertical/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
    <?= $this->renderSection('estilos') ?>

    <link rel="shortcut icon" href="<?= base_url('assets/images/logo-ifro-mini.png') ?>">

    <style>
        html, body {
            height: 100%;
        }
        .container-scroller {
            min-height: 100%;
        }
        .sidebar .nav .nav-item .nav-link .menu-title {
            font-size: 12px;
        }
        .sidebar .nav.sub-menu .nav-item .nav-link {
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container-scroller">
        <!-- Sidebar / Menu de Navegação -->
        <nav class="sidebar sidebar-offcanvas" id="sidebar">
            <div class="sidebar-brand-wrapper d-none d-lg-flex align-items-center justify-content-center fixed-top">
                <a href="<?= base_url('/') ?>">
                    <img src="<?= base_url('assets/images/logo-ifro.png') ?>" class="sidebar-brand brand-logo" alt="logo" />
                    <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" class="sidebar-brand brand-logo-mini" alt="logo" />
                </a>
            </div>

            <ul class="nav">
                <?php $uri = uri_string(); ?>
                <li class="nav-item nav-category">
                    <span class="nav-link">Menu</span>
                </li>

                <li class="nav-item menu-items <?= ($uri === '' || $uri === '/') ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= site_url('/') ?>">
                        <span class="menu-icon">
                            <i class="mdi mdi-home text-primary" aria-hidden="true"></i>
                        </span>
                        <span class="menu-title">Dashboard</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="container-fluid page-body-wrapper">
            <!-- Navbar / Cabeçalho Superior -->
            <nav class="navbar p-0 fixed-top d-flex flex-row">
                <div class="navbar-brand-wrapper d-flex d-lg-none align-items-center justify-content-center">
                    <a class="navbar-brand brand-logo-mini" href="<?= site_url('/') ?>">
                        <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" alt="logo" />
                    </a>
                </div>
                <div class="navbar-menu-wrapper flex-grow d-flex align-items-stretch">
                    <button class="navbar-toggler align-self-center" type="button" data-toggle="minimize" aria-label="Recolher ou expandir o menu">
                        <span class="mdi mdi-menu" aria-hidden="true"></span>
                    </button>

                    <ul class="navbar-nav navbar-nav-right">
                        <?php $usuario = auth()->user(); ?>
                        <?php if ($usuario !== null) : ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link" id="profileDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="navbar-profile">
                                        <i class="mdi mdi-account-circle mdi-24px me-1" aria-hidden="true"></i>
                                        <p class="mb-0 d-none d-sm-block navbar-profile-name"><?= esc($usuario->username ?? $usuario->email) ?></p>
                                        <i class="mdi mdi-menu-down d-none d-sm-block" aria-hidden="true"></i>
                                    </div>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end navbar-dropdown preview-list" aria-labelledby="profileDropdown">
                                    <form action="<?= url_to('logout') ?>" method="post">
                                        <?= csrf_field() ?>
                                        <button type="submit" id="botao-sair" class="dropdown-item preview-item">
                                            <div class="preview-thumbnail">
                                                <div class="preview-icon bg-dark rounded-circle">
                                                    <i class="mdi mdi-logout text-danger" aria-hidden="true"></i>
                                                </div>
                                            </div>
                                            <div class="preview-item-content">
                                                <p class="preview-subject mb-1">Sair</p>
                                            </div>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endif ?>
                    </ul>

                    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas" aria-label="Abrir menu lateral">
                        <span class="mdi mdi-format-line-spacing" aria-hidden="true"></span>
                    </button>
                </div>
            </nav>

            <!-- Painel Principal de Conteúdo -->
            <div class="main-panel">
                <main class="content-wrapper">
                    <!-- Mensagens de Alerta Flash -->
                    <?php
                    $tiposMensagem = [
                        'sucesso' => 'success',
                        'erro'    => 'danger',
                        'aviso'   => 'warning',
                        'info'    => 'info',
                    ];
                    ?>
                    <?php foreach ($tiposMensagem as $chave => $classe) : ?>
                        <?php if (session()->has($chave)) : ?>
                            <div class="alert alert-<?= $classe ?> alert-dismissible fade show" role="alert">
                                <?= esc(session($chave)) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif ?>
                    <?php endforeach ?>

                    <?php if (isset($content)) : ?>
                        <?= $content ?>
                    <?php else : ?>
                        <p>Nenhum conteúdo definido.</p>
                    <?php endif; ?>
                </main>

                <!-- Rodapé Compartilhado -->
                <footer class="footer">
                    <div class="d-sm-flex justify-content-center justify-content-sm-between">
                        <span class="text-muted text-center text-sm-start d-block d-sm-inline-block">
                            &copy; <?= date('Y') ?> PlanIFica Hub &middot; Calama Devs
                        </span>
                        <span class="text-muted float-none float-sm-end d-block mt-1 mt-sm-0 text-center">
                            IFRO Campus Porto Velho Calama
                        </span>
                    </div>
                </footer>
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
