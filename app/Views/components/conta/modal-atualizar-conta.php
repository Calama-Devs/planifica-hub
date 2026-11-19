<div class="modal fade" id="modal-atualizar-conta" tabindex="-1" aria-labelledby="titulo-modal-atualizar-conta" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form-atualizar-conta" method="post">
                <span data-csrf-field><?= csrf_field() ?></span>
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo-modal-atualizar-conta">Atualizar usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="erro-atualizar-conta" role="alert"></div>
                    <div class="mb-3">
                        <label for="conta-email-atual" class="form-label">E-mail institucional</label>
                        <input type="email" id="conta-email-atual" class="form-control" maxlength="254" readonly>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="alterar-email-excepcional">
                        <label class="form-check-label" for="alterar-email-excepcional">Alterar e-mail (exceção)</label>
                    </div>
                    <div class="alert alert-warning d-none" id="aviso-alteracao-email" role="alert">A alteração de e-mail é excepcional. Ela muda o login do usuário e será registrada em auditoria com a sua justificativa.</div>
                    <div class="mb-3 d-none" id="grupo-justificativa-email">
                        <label for="justificativa-email" class="form-label">Justificativa</label>
                        <textarea id="justificativa-email" class="form-control" maxlength="500" rows="3"></textarea>
                        <div class="form-text"><span id="contador-justificativa">0</span>/500 caracteres (mínimo 15).</div>
                    </div>
                    <div class="mb-3">
                        <label for="conta-nome" class="form-label">Nome completo</label>
                        <input type="text" id="conta-nome" name="nome" class="form-control" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="senha-admin-conta" class="form-label">Sua senha de administrador</label>
                        <input type="password" id="senha-admin-conta" name="senha_admin" class="form-control" autocomplete="current-password" required>
                        <div class="form-text">Necessária para confirmar esta alteração.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-salvar-conta"><i class="fa fa-save" aria-hidden="true"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
