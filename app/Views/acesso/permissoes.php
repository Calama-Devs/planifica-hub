<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Permissões da conta<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<form action="<?= url_to('acessos.permissoes.salvar', $conta['id']) ?>" method="post" id="form-permissoes-diretas">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-lg-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-1">Permissões da conta</h4>
                    <p class="mb-1"><strong><?= esc($conta['rotulo']) ?></strong></p>
                    <?php if ($conta['email'] !== '') : ?>
                        <p class="text-muted mb-2"><?= esc($conta['email']) ?></p>
                    <?php endif ?>
                    <p class="mb-4">
                        <span class="text-muted">Grupos:</span>
                        <?php if (empty($conta['grupos'])) : ?>
                            <span class="text-muted">nenhum</span>
                        <?php endif ?>
                        <?php foreach ($conta['grupos'] as $grupo) : ?>
                            <span class="badge badge-outline-info"><?= esc($grupo) ?></span>
                        <?php endforeach ?>
                    </p>

                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <button type="submit" class="btn btn-success">
                            <i class="mdi mdi-check" aria-hidden="true"></i> Salvar permissões
                        </button>
                        <a href="<?= url_to('acessos.index') ?>">Voltar às contas</a>
                    </div>
                    <p class="text-muted small mt-3 mb-4">As alterações valem imediatamente para esta conta e ficam registradas na auditoria.</p>

                    <div class="alert alert-info mb-0" role="note">
                        <p class="mb-1"><strong>Como ler esta tela</strong></p>
                        <ul class="mb-0 ps-3">
                            <li><strong>Caixa marcada:</strong> permissão direta, gravada só para esta conta. É o que esta tela altera.</li>
                            <li><strong>Herdada de:</strong> a conta recebe de um grupo. Não muda aqui: só alterando o grupo da conta ou a matriz de permissões do grupo.</li>
                            <li><strong>Acesso efetivo:</strong> o que a conta realmente pode fazer, somando herdadas e diretas.</li>
                            <li><strong>Crítica:</strong> quem a tem pode administrar o acesso de outras contas. "Marcar todas" não a inclui.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <h4 class="card-title mb-0">Permissões</h4>
                        <div class="small">
                            <button type="button" class="btn btn-link p-0 align-baseline" id="marcar-todas">marcar todas</button>
                            <span class="text-muted" aria-hidden="true">·</span>
                            <button type="button" class="btn btn-link p-0 align-baseline" id="desmarcar-todas">desmarcar</button>
                        </div>
                    </div>

                    <?php if (empty($categorias)) : ?>
                        <p class="text-center text-muted py-4">Nenhuma permissão cadastrada no catálogo.</p>
                    <?php endif ?>

                    <?php foreach ($categorias as $categoria) : ?>
                        <fieldset class="mb-4">
                            <legend class="categoria-permissoes"><?= esc($categoria['titulo']) ?></legend>
                            <div class="row">
                                <?php foreach ($categoria['permissoes'] as $permissao) : ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permissao-catalogo"
                                                   id="permissao-<?= esc($permissao['identificador'], 'attr') ?>"
                                                   name="permissoes[]" value="<?= esc($permissao['identificador'], 'attr') ?>"
                                                   <?= $permissao['direta'] ? 'checked' : '' ?>
                                                   <?= $permissao['critica'] ? 'data-critica' : '' ?>>
                                            <label class="form-check-label" for="permissao-<?= esc($permissao['identificador'], 'attr') ?>">
                                                <?php if ($permissao['critica']) : ?>
                                                    <i class="mdi mdi-alert-circle-outline text-danger" aria-hidden="true"></i>
                                                    <span class="visually-hidden">Permissão crítica:</span>
                                                <?php endif ?>
                                                <?= esc($permissao['descricao']) ?>
                                            </label>
                                            <div class="ps-4">
                                                <small class="d-block text-muted"><code><?= esc($permissao['identificador']) ?></code></small>
                                                <?php foreach ($permissao['herdada_de'] as $grupo) : ?>
                                                    <span class="badge badge-outline-info">Herdada de <?= esc($grupo) ?></span>
                                                <?php endforeach ?>
                                                <?php if ($permissao['efetiva']) : ?>
                                                    <span class="badge badge-outline-success">Acesso efetivo</span>
                                                <?php endif ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </fieldset>
                    <?php endforeach ?>

                    <?php if (! empty($foraDoCatalogo)) : ?>
                        <fieldset class="mb-0">
                            <legend class="categoria-permissoes">Fora do catálogo</legend>
                            <p class="text-muted">A conta ainda tem estas permissões diretas, mas o catálogo não as lista mais. Elas continuam valendo até serem desmarcadas e salvas. Os atalhos "marcar todas" e "desmarcar" não mexem nelas.</p>
                            <div class="row">
                                <?php foreach ($foraDoCatalogo as $identificador) : ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input"
                                                   id="permissao-<?= esc($identificador, 'attr') ?>"
                                                   name="permissoes[]" value="<?= esc($identificador, 'attr') ?>" checked>
                                            <label class="form-check-label" for="permissao-<?= esc($identificador, 'attr') ?>">
                                                <code><?= esc($identificador) ?></code>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </fieldset>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/acesso-permissoes.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/acesso-permissoes.js') ?>"></script>
<?= $this->endSection() ?>
