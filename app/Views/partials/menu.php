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

        <li class="nav-item menu-items <?= url_is('/') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= site_url('/') ?>">
                <span class="menu-icon">
                    <i class="mdi mdi-home text-primary" aria-hidden="true"></i>
                </span>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>

        <li class="nav-item menu-items <?= url_is('sobre*') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= site_url('sobre') ?>">
                <span class="menu-icon">
                    <i class="mdi mdi-information-outline text-info" aria-hidden="true"></i>
                </span>
                <span class="menu-title">Sobre</span>
            </a>
        </li>
    </ul>
</nav>
