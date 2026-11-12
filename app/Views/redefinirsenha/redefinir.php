<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Redefinição de senha<?= $this->endSection() ?>

<?= $this->section('pageStyles') ?>
<link
    rel="stylesheet"
    href="<?= base_url('assets/css/recuperacao-senha.css?v=' . filemtime(FCPATH . 'assets/css/recuperacao-senha.css')) ?>"
>
<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="recuperacao-senha">
    <section class="recuperacao-senha-card" aria-labelledby="titulo-recuperacao">
        <div class="recuperacao-senha-destaque"></div>

        <header class="recuperacao-senha-cabecalho">
            <div>
                <div class="recuperacao-senha-marca">PLANIFICA-HUB</div>
                <h1 id="titulo-recuperacao" class="recuperacao-senha-titulo">Redefinição de senha</h1>
            </div>
        </header>

        <div class="recuperacao-senha-corpo">
            <p class="recuperacao-senha-texto mb-4">
                Defina uma nova senha para concluir a recuperação da sua conta.
            </p>

            <?php $erros = (array) (session('errors') ?? session('error') ?? []); ?>
            <?php if ($erros !== []) : ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($erros as $erro) : ?>
                        <div><?= esc($erro) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form action="<?= esc(site_url('reset-password'), 'attr') ?>" method="post" data-envio-unico="true">
                <?= csrf_field() ?>

                <div class="form-floating mb-3">
                    <input
                        type="password"
                        class="form-control"
                        id="senha-nova"
                        name="senha-nova"
                        autocomplete="new-password"
                        placeholder="Nova senha"
                        minlength="<?= esc((string) config('Auth')->minimumPasswordLength) ?>"
                        required
                    >
                    <label for="senha-nova">Nova senha</label>
                </div>

                <div class="form-floating mb-3">
                    <input
                        type="password"
                        class="form-control"
                        id="confirmar-senha-nova"
                        name="confirmar-senha-nova"
                        autocomplete="new-password"
                        placeholder="Confirmar nova senha"
                        minlength="<?= esc((string) config('Auth')->minimumPasswordLength) ?>"
                        required
                    >
                    <label for="confirmar-senha-nova">Confirmar nova senha</label>
                </div>

                <p class="recuperacao-senha-regras mb-4">
                    Use pelo menos <?= esc((string) config('Auth')->minimumPasswordLength) ?> caracteres. A nova senha
                    deve ser diferente da anterior e não pode ser comum nem conter informações do seu usuário ou e-mail.
                </p>

                <div class="d-grid">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        data-texto-processando="Salvando..."
                    >
                        Salvar nova senha
                    </button>
                </div>
            </form>

            <form action="<?= esc(site_url('reset-password/cancelar')) ?>" method="post" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary w-100">Voltar para o login</button>
            </form>

            <div class="recuperacao-senha-aviso">
                Após a alteração, entre com sua nova senha no próximo acesso.
            </div>
        </div>

        <footer class="recuperacao-senha-rodape">
            Proteja sua conta e não compartilhe sua senha.
        </footer>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= esc(base_url('assets/js/redefinir-senha.js')) ?>" data-script-envio-unico="true"></script>
<?= $this->endSection() ?>
