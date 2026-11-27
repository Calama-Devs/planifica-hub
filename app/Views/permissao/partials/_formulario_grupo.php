<?php
$errosCampos            = is_array($errosCampos ?? null) ? $errosCampos : [];
$permissoesSelecionadas = is_array($permissoesSelecionadas ?? null) ? $permissoesSelecionadas : [];
$categoriasPermissoes   = is_array($categoriasPermissoes ?? null) ? $categoriasPermissoes : [];
$rotuloBotaoSalvar      = $rotuloBotaoSalvar ?? 'Salvar grupo';
?>

<form id="form-grupo" action="<?= esc($urlSalvar, 'attr') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="row g-3 align-items-start">
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title d-flex align-items-center gap-2 mb-4">
                        <i class="mdi mdi-file-document-outline text-primary" aria-hidden="true"></i>
                        <span>Informações do grupo</span>
                    </h4>

                    <div class="mb-3">
                        <label for="grupo-nome" class="form-label">
                            Nome do grupo <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text"
                               id="grupo-nome"
                               name="nome"
                               class="form-control campo-grupo <?= isset($errosCampos['nome']) ? 'is-invalid' : '' ?>"
                               placeholder="Ex.: Coordenador"
                               maxlength="80"
                               value="<?= old('nome', $grupo['nome'] ?? '') ?>"
                               required>
                        <div class="invalid-feedback">
                            <?= esc((string) ($errosCampos['nome'] ?? 'Informe o nome do grupo (mínimo 3 caracteres).')) ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="grupo-descricao" class="form-label">Descrição</label>
                        <textarea id="grupo-descricao"
                                  name="descricao"
                                  rows="4"
                                  maxlength="255"
                                  class="form-control campo-grupo <?= isset($errosCampos['descricao']) ? 'is-invalid' : '' ?>"
                                  placeholder="Descreva a finalidade do grupo..."><?= old('descricao', $grupo['descricao'] ?? '') ?></textarea>
                        <?php if (isset($errosCampos['descricao'])) : ?>
                            <div class="invalid-feedback"><?= esc((string) $errosCampos['descricao']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <a href="<?= esc($urlVoltar, 'attr') ?>" id="btn-cancelar-grupo" class="btn btn-secondary link-saida-protegida">
                            Cancelar
                        </a>
                        <button type="submit" id="btn-salvar-grupo" class="btn btn-primary btn-icon-text">
                            <span id="spinner-salvar-grupo" class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                            <i id="icone-salvar-grupo" class="mdi mdi-content-save-outline btn-icon-prepend" aria-hidden="true"></i>
                            <span id="texto-salvar-grupo"><?= esc($rotuloBotaoSalvar) ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h4 class="card-title d-flex align-items-center gap-2 mb-0">
                            <i class="mdi mdi-shield-check-outline text-primary" aria-hidden="true"></i>
                            <span>Permissões</span>
                        </h4>

                        <?php if ($categoriasPermissoes !== []) : ?>
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <button type="button" id="btn-marcar-todas" class="btn btn-link btn-sm p-0 acao-selecao-permissoes">
                                    Marcar todas
                                </button>
                                <button type="button" id="btn-desmarcar-todas" class="btn btn-link btn-sm p-0 acao-selecao-permissoes">
                                    Desmarcar todas
                                </button>
                                <button type="button" id="btn-alternar-categorias" class="btn btn-outline-secondary btn-sm py-0 px-2"
                                        aria-expanded="false">
                                    <i class="mdi mdi-unfold-more-horizontal me-1" aria-hidden="true"></i>
                                    <span>Expandir todas</span>
                                </button>
                            </div>
                        <?php endif ?>
                    </div>

                    <?php if (isset($errosCampos['permissoes'])) : ?>
                        <div class="alert alert-danger py-2 small" role="alert">
                            <?= esc((string) $errosCampos['permissoes']) ?>
                        </div>
                    <?php endif ?>

                    <?php if ($categoriasPermissoes === []) : ?>
                        <p class="text-muted mb-0">Nenhuma permissão disponível no catálogo.</p>
                    <?php else : ?>
                        <div class="accordion accordion-permissoes" id="accordion-permissoes">
                            <?php foreach ($categoriasPermissoes as $indice => $categoria) : ?>
                                <?php
                                $chave       = (string) $categoria['chave'];
                                $collapseId  = 'categoria-permissoes-' . $chave;
                                $headingId   = 'cabecalho-categoria-' . $chave;
                                $expandida   = $indice === 0;
                                $totalCat    = 0;
                                $marcadasCat = 0;

                                foreach ($categoria['permissoes'] as $itemPermissao) {
                                    if (! empty($itemPermissao['habilitada'])) {
                                        $totalCat++;
                                        if (in_array($itemPermissao['identificador'], $permissoesSelecionadas, true)) {
                                            $marcadasCat++;
                                        }
                                    }
                                }
                                ?>
                                <div class="accordion-item categoria-permissoes-item mb-2" data-categoria="<?= esc($chave, 'attr') ?>">
                                    <h5 class="accordion-header" id="<?= esc($headingId, 'attr') ?>">
                                        <button class="accordion-button <?= $expandida ? '' : 'collapsed' ?>"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#<?= esc($collapseId, 'attr') ?>"
                                                aria-expanded="<?= $expandida ? 'true' : 'false' ?>"
                                                aria-controls="<?= esc($collapseId, 'attr') ?>">
                                            <span class="d-flex align-items-center gap-2 flex-grow-1">
                                                <i class="mdi <?= esc($categoria['icone'], 'attr') ?> text-primary" aria-hidden="true"></i>
                                                <span class="fw-medium"><?= esc($categoria['titulo']) ?></span>
                                            </span>
                                            <span class="badge rounded-pill contador-categoria me-3"
                                                  data-total="<?= $totalCat ?>"
                                                  aria-live="polite">
                                                <?= $marcadasCat ?> de <?= $totalCat ?> selecionadas
                                            </span>
                                        </button>
                                    </h5>
                                    <div id="<?= esc($collapseId, 'attr') ?>"
                                         class="accordion-collapse collapse <?= $expandida ? 'show' : '' ?>"
                                         aria-labelledby="<?= esc($headingId, 'attr') ?>">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-end gap-3 mb-2">
                                                <button type="button" class="btn btn-link btn-sm p-0 acao-selecao-permissoes btn-marcar-categoria">
                                                    Marcar categoria
                                                </button>
                                                <button type="button" class="btn btn-link btn-sm p-0 acao-selecao-permissoes btn-desmarcar-categoria">
                                                    Desmarcar categoria
                                                </button>
                                            </div>
                                            <div class="row g-2">
                                                <?php $somenteVisual = ! empty($categoria['somenteVisual']); ?>
                                                <?php foreach ($categoria['permissoes'] as $permissao) : ?>
                                                    <?php
                                                    $identificador = (string) $permissao['identificador'];
                                                    $checkboxId    = 'permissao-' . str_replace('.', '-', $identificador);
                                                    $habilitada    = (bool) $permissao['habilitada'];
                                                    $marcada       = ! $somenteVisual
                                                        && in_array($identificador, $permissoesSelecionadas, true);
                                                    ?>
                                                    <div class="col-12 col-sm-6">
                                                        <div class="form-check item-permissao mb-1">
                                                            <input class="form-check-input checkbox-permissao"
                                                                   type="checkbox"
                                                                   <?= $somenteVisual ? '' : 'name="permissoes[]"' ?>
                                                                   id="<?= esc($checkboxId, 'attr') ?>"
                                                                   value="<?= esc($identificador, 'attr') ?>"
                                                                   <?= $marcada ? 'checked' : '' ?>
                                                                   <?= $habilitada ? '' : 'disabled aria-disabled="true"' ?>>
                                                            <label class="form-check-label <?= $habilitada ? 'text-white' : 'text-muted' ?>"
                                                                   for="<?= esc($checkboxId, 'attr') ?>">
                                                                <span class="d-block"><?= esc($permissao['descricao']) ?></span>
                                                                <?php if (! $somenteVisual) : ?>
                                                                    <small class="text-muted codigo-permissao"><?= esc($identificador) ?></small>
                                                                <?php endif ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="modal-sair-sem-salvar" tabindex="-1" aria-labelledby="modal-sair-sem-salvar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-sair-sem-salvar-titulo">Alterações não salvas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Você tem alterações não salvas neste formulário. Deseja sair mesmo assim?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continuar editando</button>
                <a href="<?= esc($urlVoltar, 'attr') ?>" id="btn-confirmar-saida" class="btn btn-danger">Sair sem salvar</a>
            </div>
        </div>
    </div>
</div>
