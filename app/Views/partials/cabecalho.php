<?php $usuario = auth()->user(); ?>
<nav class="navbar p-0 fixed-top d-flex flex-row" aria-label="Barra superior">
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
                        <h6 class="p-3 mb-0">Perfil</h6>
                        <div class="dropdown-divider"></div>
                        <form action="<?= url_to('alterar-senha-perfil') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" id="botao-alterar-senha" class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <div class="preview-icon bg-dark rounded-circle">
                                        <i class="mdi mdi-lock-reset text-primary" aria-hidden="true"></i>
                                    </div>
                                </div>
                                <div class="preview-item-content">
                                    <p class="preview-subject mb-1">Alterar Senha</p>
                                </div>
                            </button>
                        </form>
                        <div class="dropdown-divider"></div>
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
