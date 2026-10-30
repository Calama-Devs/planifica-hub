<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc($titulo) ?><?= $this->endSection() ?>

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
                <h1 id="titulo-recuperacao" class="recuperacao-senha-titulo"><?= esc($titulo) ?></h1>
            </div>
        </header>

        <div class="recuperacao-senha-corpo">
            <p class="recuperacao-senha-texto mb-4">
                <?= esc($textoOrientacao) ?>
            </p>

            <?php $erros = (array) (session('errors') ?? session('error') ?? []); ?>
            <?php if ($erros !== []) : ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($erros as $erro) : ?>
                        <div><?= esc($erro) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form action="<?= esc($acaoFormulario, 'attr') ?>" method="post" data-envio-unico="true">
                <?= csrf_field() ?>

                <?php if ($exigirSenhaAtual) : ?>
                    <div class="form-floating mb-3">
                        <input
                            type="password"
                            class="form-control"
                            id="senha-atual"
                            name="senha-atual"
                            autocomplete="current-password"
                            placeholder="Senha atual"
                            required
                        >
                        <label for="senha-atual">Senha atual</label>
                    </div>
                <?php endif ?>

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
                    Use pelo menos <?= esc((string) config('Auth')->minimumPasswordLength) ?> caracteres, incluindo
                    letra maiúscula, letra minúscula, número e caractere especial. Não use emojis, senhas comuns
                    nem informações do seu usuário ou e-mail.
                </p>

                <div class="d-grid">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        data-texto-processando="Salvando..."
                    >
                        <?= esc($textoBotao) ?>
                    </button>
                </div>
            </form>

            <div class="recuperacao-senha-aviso">
                Após a alteração, sua sessão será encerrada e você deverá entrar com a nova senha.
            </div>
        </div>

        <footer class="recuperacao-senha-rodape">
            Proteja sua conta e não compartilhe sua senha.
        </footer>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script data-script-envio-unico="true">
    (function () {
        'use strict';

        const formulario = document.querySelector('[data-envio-unico]');
        const botao = formulario.querySelector('button[type="submit"]');
        const textoOriginal = botao.textContent;

        formulario.addEventListener('submit', function (evento) {
            if (formulario.dataset.enviando === 'true') {
                evento.preventDefault();
                return;
            }

            formulario.dataset.enviando = 'true';
            botao.disabled = true;
            botao.textContent = botao.dataset.textoProcessando;
        });

        window.addEventListener('pageshow', function () {
            formulario.dataset.enviando = 'false';
            botao.disabled = false;
            botao.textContent = textoOriginal;
        });
    }());
</script>
<?= $this->endSection() ?>
