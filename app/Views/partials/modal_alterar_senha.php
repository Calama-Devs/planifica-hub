<div class="modal fade" id="modal-alterar-senha" tabindex="-1" aria-labelledby="titulo-alterar-senha" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formulario-alterar-senha" action="<?= esc(url_to('atualizar-senha-perfil')) ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="titulo-alterar-senha">Alterar senha</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="erro-alterar-senha" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
                    <div class="mb-3">
                        <label for="perfil-senha-atual" class="form-label">Senha atual</label>
                        <input type="password" class="form-control" id="perfil-senha-atual" name="senha-atual"
                            autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="perfil-senha-nova" class="form-label">Nova senha</label>
                        <input type="password" class="form-control" id="perfil-senha-nova" name="senha-nova"
                            autocomplete="new-password" minlength="<?= esc((string) config('Auth')->minimumPasswordLength) ?>"
                            aria-describedby="regras-senha-perfil" required>
                    </div>
                    <div class="mb-3">
                        <label for="perfil-confirmar-senha" class="form-label">Confirmar nova senha</label>
                        <input type="password" class="form-control" id="perfil-confirmar-senha" name="confirmar-senha-nova"
                            autocomplete="new-password" required>
                    </div>
                    <p id="regras-senha-perfil" class="text-muted small">
                        Use pelo menos <?= esc((string) config('Auth')->minimumPasswordLength) ?> caracteres, com letra
                        maiúscula, minúscula, número e caractere especial. Não use emojis, senhas comuns nem dados pessoais.
                    </p>
                    <p class="mb-0">Após a alteração, você deverá entrar com a nova senha.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Alterar senha</button>
                </div>
            </form>
        </div>
    </div>
</div>
