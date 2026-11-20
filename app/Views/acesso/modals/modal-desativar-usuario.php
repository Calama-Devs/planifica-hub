<!-- Modal Desativar Usuário -->
<div class="modal fade" id="modal-desativar-usuario" tabindex="-1" aria-labelledby="modal-desativar-usuario-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-desativar-usuario-label">
                    <i class="mdi mdi-account-off me-2"></i>Desativar Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-white mb-2">
                    Tem certeza de que deseja desativar a conta do seguinte usuário?
                </p>
                <div class="card bg-dark border border-secondary mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="fw-bold text-white" id="desativar-nome-usuario">-</div>
                        <div class="text-muted small" id="desativar-email-usuario">-</div>
                    </div>
                </div>
                <div class="alert alert-danger py-2 mb-2">
                    <small>
                        <i class="mdi mdi-alert me-1"></i>
                        <strong>Efeito imediato:</strong> O usuário terá seu acesso revogado e não conseguirá mais entrar no sistema até ser reativado manualmente por um administrador.
                    </small>
                </div>
                <p class="text-muted small mb-0">
                    A confirmação da senha do administrador será solicitada para efetivar esta alteração.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-prosseguir-desativacao">
                    Continuar para confirmação
                </button>
            </div>
        </div>
    </div>
</div>
