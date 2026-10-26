<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Recuperação de senha<?= $this->endSection() ?>

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
                <div class="recuperacao-senha-marca">PLANIFICAHUB</div>
                <h1 id="titulo-recuperacao" class="recuperacao-senha-titulo">Recuperação de senha</h1>
            </div>
        </header>

        <div class="recuperacao-senha-corpo">
            <p class="recuperacao-senha-texto mb-4">
                Informe o e-mail da sua conta para receber um link temporário de redefinição de senha.
            </p>

            <?php $erros = (array) (session('errors') ?? session('error') ?? []); ?>
            <?php if ($erros !== []) : ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($erros as $erro) : ?>
                        <div><?= esc($erro) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form action="<?= url_to('magic-link') ?>" method="post" data-envio-unico="true">
                <?= csrf_field() ?>

                <div class="form-floating mb-4">
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        inputmode="email"
                        autocomplete="email"
                        placeholder="E-mail"
                        value="<?= old('email') ?>"
                        required
                    >
                    <label for="email">E-mail</label>
                </div>

                <div class="d-grid">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        data-texto-processando="Enviando..."
                    >
                        Enviar link de redefinição
                    </button>
                </div>
            </form>

            <div class="recuperacao-senha-aviso">
                Por segurança, o link enviado será temporário e poderá ser utilizado somente para autenticar
                esta redefinição.
            </div>
        </div>

        <footer class="recuperacao-senha-rodape">
            Lembrou sua senha?
            <a class="recuperacao-senha-link" href="<?= url_to('login') ?>">Voltar para o login</a>
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
