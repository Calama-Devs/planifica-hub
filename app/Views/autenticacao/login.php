<?= $this->extend('layouts/autenticacao') ?>

<?= $this->section('titulo') ?>Entrar<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<?php
$validFields = setting('Auth.validFields') ?? ['email'];
$isUsernameOnly = $validFields === ['username'];
$fieldName = $isUsernameOnly ? 'username' : 'email';
$fieldType = $isUsernameOnly ? 'text' : 'email';
$fieldLabel = $isUsernameOnly ? 'Usuário' : (count($validFields) > 1 ? 'E-mail ou Usuário' : 'E-mail');
$fieldPlaceholder = $isUsernameOnly ? 'Digite seu usuário' : (count($validFields) > 1 ? 'nome@exemplo.com ou usuário' : 'nome@exemplo.com');
?>
<div class="card col-12 col-sm-8 col-md-6 col-lg-4 mx-auto shadow-sm">
    <div class="card-body px-4 py-5">
        <div class="text-center mb-4">
            <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" alt="Logo IFRO" class="img-fluid mb-2" width="48" height="48">
            <h3 class="card-title text-center mb-1">PlanIFica Hub</h3>
            <p class="text-muted small">Informe suas credenciais para acessar o sistema</p>
        </div>

        <?php if (session('error') !== null) : ?>
            <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
        <?php endif ?>

        <?php if (session('errors') !== null) : ?>
            <div class="alert alert-danger" role="alert">
                <?php if (is_array(session('errors'))) : ?>
                    <?php foreach (session('errors') as $error) : ?>
                        <?= esc($error) ?><br>
                    <?php endforeach ?>
                <?php else : ?>
                    <?= esc(session('errors')) ?>
                <?php endif ?>
            </div>
        <?php endif ?>

        <?php if (session('message') !== null) : ?>
            <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
        <?php endif ?>

        <form action="<?= url_to('login') ?>" method="post">
            <?= csrf_field() ?>

            <!-- Identificador de Acesso -->
            <div class="form-group mb-3">
                <label for="<?= esc($fieldName) ?>" class="form-label"><?= esc($fieldLabel) ?></label>
                <input type="<?= esc($fieldType) ?>" class="form-control" id="<?= esc($fieldName) ?>" name="<?= esc($fieldName) ?>" inputmode="<?= esc($fieldType) ?>" autocomplete="<?= esc($fieldName) ?>" placeholder="<?= esc($fieldPlaceholder) ?>" value="<?= esc(old($fieldName)) ?>" required autofocus>
            </div>

            <!-- Senha -->
            <div class="form-group mb-3">
                <label for="password" class="form-label">Senha</label>
                <input type="password" class="form-control" id="password" name="password" inputmode="text" autocomplete="current-password" placeholder="Digite sua senha" required>
            </div>

            <!-- Lembrar de mim -->
            <?php if (setting('Auth.sessionConfig')['allowRemembering']) : ?>
                <div class="form-group d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                        <label class="form-check-label" for="remember">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember" <?php if (old('remember')) : ?> checked<?php endif ?>>
                            Lembrar-me
                        </label>
                    </div>
                </div>
            <?php endif ?>

            <div class="text-center d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-block enter-btn">Entrar</button>
            </div>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">IFRO Campus Porto Velho Calama &middot; Calama Devs</small>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

