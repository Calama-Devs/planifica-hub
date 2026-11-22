<!-- Modal Alterar Grupos -->
<div class="modal fade" id="modal-alterar-grupos" tabindex="-1" aria-labelledby="modal-alterar-grupos-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-alterar-grupos-label">
                    <i class="mdi mdi-account-multiple me-2"></i>Alterar Grupos do Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-alterar-grupos" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="grupos-usuario-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small d-block mb-1">Usuário selecionado</label>
                        <div class="fw-bold text-white fs-6" id="grupos-nome-usuario">-</div>
                        <div class="text-muted small" id="grupos-email-usuario">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small d-block mb-1">Grupos atribuídos atualmente</label>
                        <div id="grupos-atuais-container" class="d-flex flex-wrap gap-1">
                            <!-- Inserido dinamicamente via JS -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block text-white">Selecione os novos grupos para esta conta</label>
                        <small class="text-muted d-block mb-2">
                            Marque os grupos que a conta passará a integrar. Toda conta deve pertencer a pelo menos um grupo.
                        </small>
                        <div class="border border-secondary rounded p-2 custom-scroll lista-grupos">
                            <?php if (!empty($gruposDisponiveis)): ?>
                                <?php foreach ($gruposDisponiveis as $slugGrupo => $dadosGrupo): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input check-grupo-item" type="checkbox" name="grupos[]"
                                               value="<?= esc($slugGrupo) ?>" id="grupo-check-<?= esc($slugGrupo) ?>">
                                        <label class="form-check-label text-white" for="grupo-check-<?= esc($slugGrupo) ?>">
                                            <span class="badge bg-light text-dark me-1"><?= esc($dadosGrupo['title'] ?? $slugGrupo) ?></span>
                                            <small class="text-muted d-block descricao-grupo"><?= esc($dadosGrupo['description'] ?? '') ?></small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Nenhum grupo adicional configurado.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="alerta-alterar-grupos" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-prosseguir-grupos">
                        Continuar para confirmação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
