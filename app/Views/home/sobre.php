<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Sobre<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Sobre o PlanIFica Hub</h4>
                <p class="text-muted mb-0">Sistema institucional de planejamento e gestão.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-8 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Exemplo Funcional de Validação e Mensagens</h4>
                <p class="card-description">Demonstração integrada dos componentes compartilhados de validação por campo e mensagens de sessão.</p>

                <form action="<?= url_to('sobre.testar') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="campo-nome" class="form-label">Nome Completo</label>
                        <input type="text" id="campo-nome" name="nome"
                               class="form-control <?= erro_campo('nome') ?>"
                               placeholder="Ex.: Maria da Silva"
                               value="<?= old('nome') ?>">
                        <?= view('partials/erros_validacao', ['campo' => 'nome']) ?>
                    </div>

                    <div class="mb-3">
                        <label for="campo-email" class="form-label">E-mail Institucional</label>
                        <input type="email" id="campo-email" name="email"
                               class="form-control <?= erro_campo('email') ?>"
                               placeholder="nome@exemplo.com"
                               value="<?= old('email') ?>">
                        <?= view('partials/erros_validacao', ['campo' => 'email']) ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Testar Envio</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
