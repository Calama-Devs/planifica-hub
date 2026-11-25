<!-- Modal Atualizar Dados do Usuário -->
<div class="modal fade" id="modal-atualizar-usuario" tabindex="-1" aria-labelledby="modal-atualizar-usuario-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-atualizar-usuario-label">
                    <i class="mdi mdi-pencil me-2"></i>Atualizar Dados do Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-atualizar-usuario" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="editar-usuario-id">
                <div class="modal-body">
                    <div id="alerta-atualizar-usuario" class="alert d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="editar-usuario-nome" class="form-label">Nome completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editar-usuario-nome" name="nome"
                               placeholder="Nome completo do usuário" required>
                        <div class="invalid-feedback">Informe o nome completo do usuário.</div>
                    </div>

                    <div class="mb-3">
                        <label for="editar-usuario-email" class="form-label">E-mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="editar-usuario-email" name="email"
                               placeholder="email@exemplo.com" required>
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="mb-3 d-none" id="grupo-justificativa-email">
                        <label for="editar-usuario-justificativa" class="form-label">Justificativa para troca de e-mail <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="editar-usuario-justificativa" name="justificativa"
                                  minlength="15" maxlength="500" rows="3"></textarea>
                        <div class="invalid-feedback">Informe uma justificativa entre 15 e 500 caracteres.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-salvar-atualizacao">
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
