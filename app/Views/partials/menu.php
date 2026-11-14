<?php
$itensMenu = $itensMenu ?? [];
?>
<nav class="sidebar sidebar-offcanvas" id="sidebar" aria-label="Menu principal de navegação">
    <div class="sidebar-brand-wrapper d-none d-lg-flex align-items-center justify-content-center fixed-top">
        <a href="<?= base_url('/') ?>">
            <img src="<?= base_url('assets/images/logo-ifro.png') ?>" class="sidebar-brand brand-logo" alt="logo" />
            <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" class="sidebar-brand brand-logo-mini" alt="logo" />
        </a>
    </div>

    <ul class="nav">
        <?php foreach ($itensMenu as $item) : ?>
            <?php if ($item['categoria'] !== null) : ?>
                <li class="nav-item nav-category">
                    <span class="nav-link"><?= esc($item['categoria']) ?></span>
                </li>
            <?php endif ?>
            
            <li class="nav-item menu-items <?= $item['ativo'] ? 'active' : '' ?>">
                <a class="nav-link" href="<?= esc($item['url'], 'attr')?>">
                    <span class="menu-icon">
                        <i class="<?= esc($item['icone'], 'attr') ?>" aria-hidden="true"></i>
                    </span>
                    <span class="menu-title"><?= esc($item['titulo']) ?></span>
                </a>
            </li>
        <?php endforeach?>
    </ul>
</nav>
