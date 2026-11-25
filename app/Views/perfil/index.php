<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Meu Perfil<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 col-lg-8 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Meu Perfil</h4>

                <dl class="row mb-4">
                    <dt class="col-sm-3">Nome</dt>
                    <dd class="col-sm-9"><?= esc($nomeAtual) ?></dd>
                    <dt class="col-sm-3">E-mail institucional</dt>
                    <dd class="col-sm-9">
                        <?= esc($emailAtual) ?>
                        <small class="d-block text-muted">Somente consulta. A alteração é feita pela administração de contas.</small>
                    </dd>
                    <dt class="col-sm-3">Grupos</dt>
                    <dd class="col-sm-9">
                        <?php if ($grupos === []) : ?>
                            Nenhum grupo vinculado à conta.
                        <?php else : ?>
                            <ul class="list-inline mb-0">
                                <?php foreach ($grupos as $grupo) : ?>
                                    <li class="list-inline-item"><span class="badge badge-outline-primary"><?= esc($grupo) ?></span></li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                    </dd>
                    <dt class="col-sm-3">Permissões</dt>
                    <dd class="col-sm-9">
                        <?php if ($permissoesEfetivas === []) : ?>
                            Nenhuma permissão administrativa.
                        <?php else : ?>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($permissoesEfetivas as $permissao) : ?>
                                    <li><?= esc($permissao) ?></li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                    </dd>
                </dl>

                <hr class="mb-4">

                <h5 class="card-title">Editar nome</h5>
                <p class="card-description">Altere o nome exibido na sua conta.</p>

                <form action="<?= url_to('perfil.atualizar') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="campo-nome" class="form-label">Nome</label>
                        <input type="text" id="campo-nome" name="nome"
                               class="form-control <?= erro_campo('nome') ?>"
                               value="<?= old('nome') ?? esc($nomeAtual) ?>"
                               minlength="<?= esc((string) $comprimentoMinimo) ?>"
                               maxlength="<?= esc((string) $comprimentoMaximo) ?>"
                               aria-describedby="ajuda-nome"
                               required>
                        <small id="ajuda-nome" class="form-text text-muted">
                            Use seu nome, nome completo ou apelido, com <?= esc((string) $comprimentoMinimo) ?> a
                            <?= esc((string) $comprimentoMaximo) ?> caracteres: letras, acentos e espaços.
                        </small>
                        <?= view('partials/erros_validacao', ['campo' => 'nome']) ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Salvar nome</button>
                </form>

                <hr class="my-4">

                <h5 class="card-title">Alterar senha</h5>
                <p class="card-description">Confirme a senha atual para definir uma nova.</p>

                <button type="button" id="botao-alterar-senha-perfil" class="btn btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#modal-alterar-senha">
                    <i class="mdi mdi-lock-reset" aria-hidden="true"></i>
                    Alterar senha
                </button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
