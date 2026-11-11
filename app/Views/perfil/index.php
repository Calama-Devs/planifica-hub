<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Meu Perfil<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 col-lg-8 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Meu Perfil</h4>
                <p class="card-description">Altere o nome exibido na sua conta.</p>

                <form action="<?= url_to('perfil.atualizar') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="campo-nome" class="form-label">Nome</label>
                        <input type="text" id="campo-nome" name="nome"
                               class="form-control <?= erro_campo('nome') ?>"
                               value="<?= old('nome') ?? esc($nomeAtual) ?>"
                               <?= $comprimentoMaximo !== null ? 'maxlength="' . esc((string) $comprimentoMaximo) . '"' : '' ?>
                               required>
                        <?= view('partials/erros_validacao', ['campo' => 'nome']) ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Salvar nome</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
