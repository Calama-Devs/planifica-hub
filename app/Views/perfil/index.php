<?php
/**
 * @var string        $nomeAtual          Nome atual do usuário autenticado.
 * @var string        $emailAtual         E-mail da conta autenticada.
 * @var string[]      $grupos             Títulos dos grupos associados à conta.
 * @var int|null      $comprimentoMinimo  Comprimento mínimo permitido para o nome.
 * @var int|null      $comprimentoMaximo  Comprimento máximo permitido para o nome.
 * @var bool          $podeEditarEmail    Indica se o usuário tem autorização para editar o e-mail.
 * @var bool          $podeEditarGrupos   Indica se o usuário tem autorização para editar os grupos.
 */
?>
<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Meu Perfil<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 col-lg-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">

                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="perfil-avatar bg-primary rounded-3 d-flex align-items-center justify-content-center" aria-hidden="true">
                        <i class="mdi mdi-account mdi-36px text-white"></i>
                    </div>
                    <div>
                        <h4 class="card-title mb-1">Meu perfil</h4>
                        <p class="text-muted mb-0">Gerencie suas informações pessoais e de acesso ao sistema.</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold">Informações da conta</span>
                </div>
                <hr class="mt-0 mb-4">

                <form id="formulario-perfil" action="<?= url_to('perfil.atualizar') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="row mb-3 align-items-center">
                        <label for="campo-nome" class="col-sm-3 col-form-label">
                            Nome <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <div class="col-sm-9">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="mdi mdi-account" aria-hidden="true"></i>
                                </span>
                                <input type="text" id="campo-nome" name="nome"
                                       class="form-control <?= erro_campo('nome') ?>"
                                       value="<?= old('nome') ?? esc($nomeAtual) ?>"
                                       minlength="<?= esc((string) $comprimentoMinimo) ?>"
                                       maxlength="<?= esc((string) $comprimentoMaximo) ?>"
                                       aria-describedby="regras-nome-perfil"
                                       required>
                            </div>
                            <p id="regras-nome-perfil" class="text-muted small mt-1 mb-0">
                                O nome deve ter pelo menos <?= esc((string) $comprimentoMinimo) ?> caracteres.
                            </p>
                            <?= view('partials/erros_validacao', ['campo' => 'nome']) ?>
                        </div>
                    </div>

                    <div class="row mb-3 align-items-center">
                        <label for="campo-email" class="col-sm-3 col-form-label">E-mail</label>
                        <div class="col-sm-9">
                            <div class="input-group"
                                 <?php if (! $podeEditarEmail): ?>
                                 tabindex="0"
                                 data-bs-toggle="tooltip"
                                 data-bs-placement="top"
                                 title="A alteração de e-mail requer autorização administrativa."
                                 aria-label="Campo bloqueado — a alteração de e-mail requer autorização administrativa."
                                 <?php endif ?>
                                 >
                                <span class="input-group-text">
                                    <i class="mdi mdi-email-outline" aria-hidden="true"></i>
                                </span>
                                <input type="email" id="campo-email" name="email" class="form-control"
                                       value="<?= esc($emailAtual) ?>"
                                       <?= $podeEditarEmail ? '' : 'disabled' ?>>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4 align-items-center">
                        <label for="campo-grupos" class="col-sm-3 col-form-label">Grupo(s)</label>
                        <div class="col-sm-9">
                            <div class="input-group"
                                 <?php if (! $podeEditarGrupos): ?>
                                 tabindex="0"
                                 data-bs-toggle="tooltip"
                                 data-bs-placement="top"
                                 title="A alteração de grupos requer autorização administrativa."
                                 aria-label="Campo bloqueado — a alteração de grupos requer autorização administrativa."
                                 <?php endif ?>
                                 >
                                <span class="input-group-text">
                                    <i class="mdi mdi-account-multiple" aria-hidden="true"></i>
                                </span>
                                <input type="text" id="campo-grupos" class="form-control"
                                       value="<?= esc($grupos !== [] ? implode(', ', $grupos) : 'Sem grupo atribuído') ?>"
                                       <?= $podeEditarGrupos ? '' : 'disabled' ?>>
                            </div>
                        </div>
                    </div>
                </form>

                <hr class="mb-0">

                <button type="button"
                        class="btn bg-transparent border-0 text-body w-100 text-start px-0 py-2 d-flex align-items-center gap-2"
                        id="botao-secao-senha"
                        data-bs-toggle="collapse"
                        data-bs-target="#secao-trocar-senha"
                        aria-expanded="false"
                        aria-controls="secao-trocar-senha">
                    <i class="mdi mdi-lock" aria-hidden="true"></i>
                    <span>Trocar senha</span>
                    <i class="mdi mdi-chevron-down ms-auto icone-secao" aria-hidden="true"></i>
                </button>

                <div class="collapse" id="secao-trocar-senha">
                    <hr class="mt-0 mb-3">

                    <div id="alerta-trocar-senha" class="alert alert-danger d-none"
                         role="alert" tabindex="-1"></div>

                    <form id="formulario-trocar-senha"
                          action="<?= esc(url_to('atualizar-senha-perfil')) ?>"
                          method="post">
                        <?= csrf_field() ?>

                        <div class="row mb-3 align-items-center">
                            <label for="perfil-senha-atual" class="col-sm-3 col-form-label">Senha atual</label>
                            <div class="col-sm-9">
                                <div class="position-relative">
                                    <input type="password" class="form-control pe-5" id="perfil-senha-atual"
                                           name="senha-atual" autocomplete="current-password" required>
                                    <button type="button"
                                            class="btn btn-toggle-senha position-absolute top-50 end-0 translate-middle-y border-0"
                                            data-alvo="#perfil-senha-atual"
                                            aria-label="Mostrar senha atual">
                                        <i class="mdi mdi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="perfil-senha-nova" class="col-sm-3 col-form-label">Nova senha</label>
                            <div class="col-sm-9">
                                <div class="position-relative">
                                    <input type="password" class="form-control pe-5" id="perfil-senha-nova"
                                           name="senha-nova" autocomplete="new-password"
                                           minlength="<?= esc((string) config('Auth')->minimumPasswordLength) ?>"
                                           aria-describedby="regras-senha-perfil" required>
                                    <button type="button"
                                            class="btn btn-toggle-senha position-absolute top-50 end-0 translate-middle-y border-0"
                                            data-alvo="#perfil-senha-nova"
                                            aria-label="Mostrar nova senha">
                                        <i class="mdi mdi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="perfil-confirmar-senha" class="col-sm-3 col-form-label">Confirmar nova senha</label>
                            <div class="col-sm-9">
                                <div class="position-relative">
                                    <input type="password" class="form-control pe-5" id="perfil-confirmar-senha"
                                           name="confirmar-senha-nova" autocomplete="new-password" required>
                                    <button type="button"
                                            class="btn btn-toggle-senha position-absolute top-50 end-0 translate-middle-y border-0"
                                            data-alvo="#perfil-confirmar-senha"
                                            aria-label="Mostrar confirmação de senha">
                                        <i class="mdi mdi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-9 offset-sm-3">
                                <p id="regras-senha-perfil" class="text-muted small mb-1">
                                    Use pelo menos <?= esc((string) config('Auth')->minimumPasswordLength) ?> caracteres.
                                    A nova senha deve ser diferente da atual e não pode ser comum nem conter dados pessoais.
                                </p>
                                <p class="text-muted small mb-3">Após a alteração, você deverá entrar com a nova senha.</p>
                                <button type="submit" class="btn btn-primary">Alterar senha</button>
                            </div>
                        </div>
                    </form>
                </div>

                <hr class="mt-0">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url_to('perfil') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" form="formulario-perfil" class="btn btn-primary">
                        <i class="mdi mdi-content-save me-1" aria-hidden="true"></i>Salvar alterações
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('assets/js/alterar-senha.js'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/js/alterar-senha.js') ?>"></script>
<script src="<?= esc(base_url('assets/js/perfil.js'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/js/perfil.js') ?>"></script>
<?= $this->endSection() ?>
