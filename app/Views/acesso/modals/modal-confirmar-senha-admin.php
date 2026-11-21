<!-- Modal Reutilizável de Confirmação da Senha Administrativa -->
<div class="modal fade" id="modal-confirmar-senha-admin" tabindex="-1" aria-labelledby="modal-confirmar-senha-admin-label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-confirmar-senha-admin-label">
                    <i class="mdi mdi-shield-lock me-2"></i>Confirmar Ação Administrativa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-confirmar-senha-admin" novalidate>
                <?= csrf_field() ?>
                <div class="modal-body">
                    <p class="text-white mb-2" id="confirmar-senha-descricao">
                        Para executar esta ação sensível, confirme sua senha de administrador:
                    </p>

                    <div class="card bg-dark border border-secondary mb-3">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small">Conta afetada:</span>
                                <span class="fw-bold text-white" id="confirmar-senha-alvo">-</span>
                            </div>
                            <div class="text-muted small text-end" id="confirmar-senha-alvo-email">-</div>
                        </div>
                    </div>

                    <div id="alerta-confirmar-senha" class="alert alert-danger d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="admin-password-confirm" class="form-label">Sua senha de administrador <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="admin-password-confirm" name="admin_password"
                                   placeholder="Digite sua senha de acesso" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" id="btn-toggle-admin-password" title="Mostrar/ocultar senha">
                                <i class="mdi mdi-eye-outline" id="icone-toggle-admin-password"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback" id="admin-password-feedback">
                            A senha de administrador é obrigatória.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn-cancelar-confirmacao">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-executar-confirmacao">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="spinner-confirmar-senha" role="status" aria-hidden="true"></span>
                        <span id="texto-btn-confirmar">Confirmar ação</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
