<!-- Modal Reativar Grupo -->
<div class="modal fade" id="modal-reativar-grupo" tabindex="-1" aria-labelledby="modal-reativar-grupo-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-reativar-grupo-label">
                    <i class="mdi mdi-account-multiple-check me-2" aria-hidden="true"></i>Reativar Grupo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-white mb-2">
                    Deseja reativar o seguinte grupo?
                </p>
                <div class="card bg-dark border border-secondary mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="fw-bold text-white" id="reativar-nome-grupo">-</div>
                        <div class="text-muted small" id="reativar-descricao-grupo">-</div>
                    </div>
                </div>
                <div class="alert alert-success py-2 mb-0">
                    <small>
                        <i class="mdi mdi-check-circle-outline me-1" aria-hidden="true"></i>
                        <strong>Efeito imediato:</strong> O grupo voltará a ficar ativo e disponível na listagem principal de Permissões e grupos.
                    </small>
                </div>
                <div id="alerta-erro-reativar-grupo" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-reativacao-grupo">
                    Reativar grupo
                </button>
            </div>
        </div>
    </div>
</div>
