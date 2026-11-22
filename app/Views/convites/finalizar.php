<?= $this->extend('layouts/autenticacao') ?>

<?= $this->section('titulo') ?>Finalizar cadastro<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<main class="content-wrapper full-page-wrapper auth login-2 login-bg p-0 d-flex flex-wrap align-items-stretch">
    <div class="card col-12 col-md-5 col-lg-4 d-flex flex-column justify-content-between p-4 p-xl-5 border-0 shadow-lg">
        <div class="my-auto w-100">
            <h3 class="card-title mb-2 text-light">Finalizar cadastro</h3>

            <?php if (! $resultado['sucesso']) : ?>
                <div class="alert alert-danger" role="alert"><?= esc($resultado['mensagem']) ?></div>
                <a class="btn btn-primary" href="<?= esc(route_to('login'), 'attr') ?>">Ir para o login</a>
            <?php else : ?>
                <p class="text-muted">Defina uma senha para o e-mail institucional abaixo.</p>
                <div class="form-group mb-3">
                    <label for="email" class="form-label text-light">E-mail institucional</label>
                    <input class="form-control p_input text-light" id="email" type="email" value="<?= esc($resultado['dados']['email']) ?>" readonly>
                </div>

                <form action="<?= esc(route_to('finalizar_cadastro.concluir'), 'attr') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

                    <?php if (session('error') !== null) : ?>
                        <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
                    <?php endif ?>
                    <?php if (session('errors') !== null) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach (session('errors') as $erro) : ?>
                                <div><?= esc($erro) ?></div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>

                    <div class="form-group mb-3">
                        <label for="nome_completo" class="form-label text-light">Nome completo</label>
                        <input class="form-control p_input text-light" id="nome_completo" name="nome_completo" type="text" autocomplete="name" maxlength="150" value="<?= old('nome_completo') ?>" required autofocus>
                    </div>
                    <div class="form-group mb-3">
                        <label for="password" class="form-label text-light">Senha</label>
                        <input class="form-control p_input text-light" id="password" name="password" type="password" autocomplete="new-password" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="password_confirm" class="form-label text-light">Confirmar senha</label>
                        <input class="form-control p_input text-light" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Concluir cadastro</button>
                </form>
            <?php endif ?>
        </div>
    </div>
</main>
<?= $this->endSection() ?>
