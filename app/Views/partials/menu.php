<?php
$itensMenu = config(\Config\Menu::class)->itensVisiveis();
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar" aria-label="Menu principal de navegação">
    <div class="sidebar-brand-wrapper d-none d-lg-flex align-items-center justify-content-center fixed-top">
        <a href="<?= base_url('/') ?>">
            <img src="<?= base_url('assets/images/logo-ifro.png') ?>" class="sidebar-brand brand-logo" alt="logo" />
            <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" class="sidebar-brand brand-logo-mini" alt="logo" />
        </a>
    </div>

    <ul class="nav">
        <li class="nav-item nav-category">
            <span class="nav-link">Menu</span>
        </li>

        <?php foreach ($itensMenu as $item) : ?>
            <li class="nav-item menu-items <?= $item['ativo'] ? 'active' : '' ?>">
                <a class="nav-link" href="<?= esc($item['url'], 'attr')?>">
                    <span class="menu-icon">
                        <i class="<?= esc($item['icone'], 'attr') ?>" aria-hidden="true"></i>
                    </span>
                    <span class="menu-title"><?= esc($item['titulo']) ?></span>
                </a>
            </li>
        <?php endforeach?>

        <?php if (auth()->loggedIn() && auth()->user()->can('acessos.administrar')) : ?>
            <li class="nav-item nav-category">
                <span class="nav-link">Administração</span>
            </li>

            <li class="nav-item menu-items <?= url_is('admin/permissoes*') ? 'active' : '' ?>">
                <a class="nav-link" href="<?= site_url('admin/permissoes') ?>">
                    <span class="menu-icon">
                        <i class="mdi mdi-shield-key-outline text-warning" aria-hidden="true"></i>
                    </span>
                    <span class="menu-title">Permissões</span>
                </a>
            </li>
        <?php endif ?>
    </ul>
</nav>
