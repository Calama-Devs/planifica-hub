<!-- Modal Resetar Senha -->
<div class="modal fade" id="modal-resetar-senha" tabindex="-1" aria-labelledby="modal-resetar-senha-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-resetar-senha-label">
                    <i class="mdi mdi-key-variant me-2"></i>Resetar Senha da Conta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-white mb-2">
                    Você está solicitando o envio de link para redefinição de senha da seguinte conta:
                </p>
                <div class="card bg-dark border border-secondary mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="fw-bold text-white" id="reset-nome-usuario">-</div>
                        <div class="text-muted small" id="reset-email-usuario">-</div>
                    </div>
                </div>
                <div class="alert alert-warning py-2 mb-2">
                    <small>
                        <i class="mdi mdi-alert-circle-outline me-1"></i>
                        Um link seguro e temporário será enviado ao e-mail da pessoa para que ela mesma defina sua nova senha.
                        A senha atual não será exposta e não é possível definir a nova senha diretamente pelo painel administrativo.
                    </small>
                </div>
                <p class="text-muted small mb-0">
                    Por segurança, esta operação exigirá a confirmação da sua senha de administrador no próximo passo.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-prosseguir-reset">
                    Solicitar redefinição
                </button>
            </div>
        </div>
    </div>
</div>
