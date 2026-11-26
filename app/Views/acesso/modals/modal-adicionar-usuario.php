<!-- Modal Adicionar Usuário (Convite) -->
<div class="modal fade" id="modal-adicionar-usuario" tabindex="-1" aria-labelledby="modal-adicionar-usuario-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-adicionar-usuario-label">
                    <i class="mdi mdi-account-plus me-2"></i>Adicionar Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="form-adicionar-usuario" action="<?= esc(url_to('convites.emitir'), 'attr') ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        O destinatário receberá um e-mail com o link de convite para definir seu nome e senha no primeiro acesso.
                    </p>

                    <div id="alerta-adicionar-usuario" class="alert d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="convite-email" class="form-label">E-mail institucional <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="convite-email" name="email"
                               placeholder="nome.sobrenome@ifro.edu.br" required autocomplete="off">
                        <div class="invalid-feedback">Informe um e-mail válido para envio do convite.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Grupos atribuídos (opcional)</label>
                        <small class="text-muted d-block mb-2">
                            Se nenhum grupo for selecionado, a nova conta receberá automaticamente o grupo padrão <strong>user</strong> (Usuário).
                        </small>
                        <div class="border border-secondary rounded p-2 custom-scroll lista-grupos">
                            <?php if (! empty($gruposDisponiveis)) : ?>
                                <?php foreach ($gruposDisponiveis as $slugGrupo => $dadosGrupo) : ?>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="grupos[]"
                                               value="<?= esc($slugGrupo) ?>" id="grupo-add-<?= esc($slugGrupo) ?>">
                                        <label class="form-check-label text-white" for="grupo-add-<?= esc($slugGrupo) ?>">
                                            <span class="fw-medium"><?= esc($dadosGrupo['title'] ?? $slugGrupo) ?></span>
                                            <small class="text-muted d-block descricao-grupo"><?= esc($dadosGrupo['description'] ?? '') ?></small>
                                        </label>
                                    </div>
                                <?php endforeach ?>
                            <?php else : ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="grupos[]" value="user" id="grupo-add-user-default" checked disabled>
                                    <label class="form-check-label text-white" for="grupo-add-user-default">
                                        <span class="fw-medium">Usuário (user)</span>
                                        <small class="text-muted d-block descricao-grupo">Grupo padrão sem privilégios administrativos.</small>
                                    </label>
                                </div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-enviar-convite">
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        Enviar convite
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
