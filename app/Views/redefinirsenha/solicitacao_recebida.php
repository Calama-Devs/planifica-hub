<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Verifique seu e-mail<?= $this->endSection() ?>

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
                <h1 id="titulo-recuperacao" class="recuperacao-senha-titulo">Verifique seu e-mail</h1>
            </div>
        </header>

        <div class="recuperacao-senha-corpo">
            <p class="recuperacao-senha-texto mb-0">
                Enviamos um link temporário para o e-mail informado. Abra a mensagem e siga as instruções para
                definir uma nova senha.
            </p>

            <div class="recuperacao-senha-aviso">
                O link será válido por <?= esc((string) (setting('Auth.magicLinkLifetime') / 60)) ?> minutos.
                Se a mensagem não aparecer, verifique a caixa de spam.
            </div>
        </div>

        <footer class="recuperacao-senha-rodape">
            <a class="recuperacao-senha-link" href="<?= url_to('magic-link') ?>">Enviar outro link</a>
            <span aria-hidden="true"> · </span>
            <a class="recuperacao-senha-link" href="<?= url_to('login') ?>">Voltar para o login</a>
        </footer>
    </section>
</div>
<?= $this->endSection() ?>
