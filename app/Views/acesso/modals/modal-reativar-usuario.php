<!-- Modal Reativar Usuário -->
<div class="modal fade" id="modal-reativar-usuario" tabindex="-1" aria-labelledby="modal-reativar-usuario-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-reativar-usuario-label">
                    <i class="mdi mdi-account-check me-2"></i>Reativar Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-white mb-2">
                    Deseja reativar o acesso da seguinte conta?
                </p>
                <div class="card bg-dark border border-secondary mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="fw-bold text-white" id="reativar-nome-usuario">-</div>
                        <div class="text-muted small" id="reativar-email-usuario">-</div>
                    </div>
                </div>
                <div class="alert alert-success py-2 mb-2">
                    <small>
                        <i class="mdi mdi-check-circle-outline me-1"></i>
                        <strong>Efeito imediato:</strong> O usuário voltará a ter permissão para autenticar-se e utilizar as funções atribuídas aos seus grupos e permissões.
                    </small>
                </div>
                <p class="text-muted small mb-0">
                    A confirmação da senha do administrador será solicitada para efetivar esta alteração.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-prosseguir-reativacao">
                    Continuar para confirmação
                </button>
            </div>
        </div>
    </div>
</div>
