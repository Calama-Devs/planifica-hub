<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Cadastrar usuário<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 col-lg-8 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Cadastrar usuário</h4>
                <p class="card-description">A conta será criada sem registro público e receberá somente o grupo selecionado.</p>

                <form action="<?= url_to('usuarios.criar') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="campo-nome" class="form-label">Nome de usuário</label>
                        <input type="text" id="campo-nome" name="nome"
                               class="form-control <?= erro_campo('nome') ?>" maxlength="30"
                               value="<?= old('nome') ?>" autocomplete="username" required>
                        <?= view('partials/erros_validacao', ['campo' => 'nome']) ?>
                    </div>

                    <div class="mb-3">
                        <label for="campo-email" class="form-label">E-mail</label>
                        <input type="email" id="campo-email" name="email"
                               class="form-control <?= erro_campo('email') ?>" maxlength="254"
                               value="<?= old('email') ?>" autocomplete="email" required>
                        <?= view('partials/erros_validacao', ['campo' => 'email']) ?>
                    </div>

                    <div class="mb-3">
                        <label for="campo-senha" class="form-label">Senha inicial</label>
                        <div class="position-relative">
                            <input type="password" id="campo-senha" name="senha"
                                   class="form-control campo-com-botao <?= erro_campo('senha') ?>" minlength="8" maxlength="72"
                                   autocomplete="new-password" required>
                            <div class="campo-acoes">
                                <button type="button" class="btn btn-dica-senha"
                                        data-dica-alvo="dica-senha"
                                        aria-label="Ver requisitos da senha" aria-expanded="false"
                                        aria-controls="dica-senha" title="Requisitos da senha">
                                    <i class="mdi mdi-lightbulb-outline" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-alternar-senha"
                                        data-alternar-senha="campo-senha"
                                        aria-label="Mostrar senha" aria-pressed="false" title="Mostrar senha">
                                    <i class="mdi mdi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="dica-senha" class="dica-senha" role="note" hidden>
                                <strong class="d-block mb-1">Requisitos da senha</strong>
                                <ul class="mb-0 ps-3">
                                    <li>Ter no mínimo 8 caracteres.</li>
                                    <li>Não ser semelhante ao seu nome ou endereço de e-mail.</li>
                                    <li>Não utilizar senhas comuns ou facilmente previsíveis.</li>
                                </ul>
                            </div>
                        </div>
                        <?= view('partials/erros_validacao', ['campo' => 'senha']) ?>
                        <small class="form-text text-muted">A senha não será exibida novamente após o cadastro.</small>
                    </div>

                    <div class="mb-4">
                        <label for="campo-grupo" class="form-label">Grupo</label>
                        <select id="campo-grupo" name="grupo" class="form-control <?= erro_campo('grupo') ?>" required>
                            <option value="">Selecione um grupo</option>
                            <?php foreach ($grupos as $identificador => $titulo) : ?>
                                <option value="<?= esc($identificador, 'attr') ?>"
                                    <?= old('grupo', config('AuthGroups')->defaultGroup) === $identificador ? 'selected' : '' ?>>
                                    <?= esc($titulo) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <?= view('partials/erros_validacao', ['campo' => 'grupo']) ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save" aria-hidden="true"></i> Cadastrar
                        </button>
                        <a href="<?= url_to('usuarios.index') ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/alternar-senha.js') ?>" defer></script>
<?= $this->endSection() ?>