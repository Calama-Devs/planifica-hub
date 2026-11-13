<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Permissões da conta<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="permissoes-conta">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb border-0 p-0 mb-1">
            <li class="breadcrumb-item"><a href="<?= url_to('acessos.index') ?>" class="link-success">Acessos</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= esc($conta['rotulo']) ?></li>
        </ol>
    </nav>
    <h1 class="fs-5 fw-semibold mb-4">Conta: <?= esc($conta['rotulo']) ?></h1>

    <form action="<?= url_to('acessos.permissoes.salvar', $conta['id']) ?>" method="post" id="form-permissoes-diretas">
        <?= csrf_field() ?>
        <div class="row">
            <div class="col-lg-4 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <dl class="ps-0 mb-0">
                            <dt class="fw-normal small texto-apoio">Conta</dt>
                            <dd class="text-break mb-3"><?= esc($conta['rotulo']) ?></dd>
                            <?php if ($conta['email'] !== '') : ?>
                                <dt class="fw-normal small texto-apoio">E-mail</dt>
                                <dd class="text-break mb-3"><?= esc($conta['email']) ?></dd>
                            <?php endif ?>
                            <dt class="fw-normal small texto-apoio">Grupos</dt>
                            <dd class="mb-0">
                                <?php if (empty($conta['grupos'])) : ?>
                                    <span class="texto-apoio">nenhum</span>
                                <?php endif ?>
                                <?php foreach ($conta['grupos'] as $grupo) : ?>
                                    <span class="badge badge-outline-info"><?= esc($grupo) ?></span>
                                <?php endforeach ?>
                            </dd>
                        </dl>

                        <div class="d-flex flex-wrap align-items-center gap-3 border-top pt-3 mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="mdi mdi-check" aria-hidden="true"></i> Salvar permissões
                            </button>
                            <a href="<?= url_to('acessos.index') ?>" class="link-success">Voltar</a>
                        </div>
                        <p class="small texto-apoio mt-3 mb-0">As alterações valem imediatamente para esta conta e ficam registradas na auditoria.</p>

                        <div class="legenda-permissoes bg-black bg-opacity-25 border rounded p-3 texto-apoio mt-4" role="note">
                            <p class="fw-semibold text-white mb-2">Como ler esta tela</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><strong>Caixa marcada:</strong> permissão direta, só desta conta. É o que esta tela altera.</li>
                                <li class="mb-2"><strong>Herdada de:</strong> vem de um grupo da conta e não muda aqui.</li>
                                <li class="mb-2">
                                    <i class="mdi mdi-check-circle-outline text-success" aria-hidden="true"></i>
                                    <strong>Acesso efetivo:</strong> o que a conta pode usar, somando heranças e diretas.
                                </li>
                                <li>
                                    <i class="mdi mdi-alert-circle-outline text-danger" aria-hidden="true"></i>
                                    <strong>Crítica:</strong> permite administrar o acesso de outras contas. "Marcar todas" não a inclui.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h2 class="h6 mb-0">Permissões</h2>
                            <div class="small">
                                <button type="button" class="btn btn-link link-success p-0 align-baseline" id="marcar-todas">marcar todas</button>
                                <span class="texto-apoio" aria-hidden="true">·</span>
                                <button type="button" class="btn btn-link link-success p-0 align-baseline" id="desmarcar-todas">desmarcar</button>
                            </div>
                        </div>

                        <?php if (empty($categorias)) : ?>
                            <p class="text-center texto-apoio border-top py-4 mb-0">Nenhuma permissão cadastrada no catálogo.</p>
                        <?php endif ?>

                        <?php foreach ($categorias as $categoria) : ?>
                            <fieldset class="border-top pt-3 pb-2 m-0">
                                <legend class="categoria-permissoes"><?= esc($categoria['titulo']) ?></legend>
                                <div class="row row-cols-1 row-cols-md-2 gx-4 gy-2">
                                    <?php foreach ($categoria['permissoes'] as $permissao) : ?>
                                        <div class="col">
                                            <div class="form-check d-flex align-items-start m-0 p-0">
                                                <input type="checkbox" class="form-check-input permissao-catalogo"
                                                       id="permissao-<?= esc($permissao['identificador'], 'attr') ?>"
                                                       name="permissoes[]" value="<?= esc($permissao['identificador'], 'attr') ?>"
                                                       aria-describedby="detalhe-<?= esc($permissao['identificador'], 'attr') ?>"
                                                       <?= $permissao['direta'] ? 'checked' : '' ?>
                                                       <?= $permissao['critica'] ? 'data-critica' : '' ?>>
                                                <div class="flex-grow-1">
                                                    <label class="form-check-label" for="permissao-<?= esc($permissao['identificador'], 'attr') ?>">
                                                        <?php if ($permissao['critica']) : ?>
                                                            <i class="mdi mdi-alert-circle-outline text-danger" aria-hidden="true"></i>
                                                            <span class="visually-hidden">Permissão crítica:</span>
                                                        <?php endif ?>
                                                        <?= esc($permissao['descricao']) ?>
                                                    </label>
                                                    <i class="mdi mdi-information-outline small texto-apoio permissao-info ms-1"
                                                       data-bs-toggle="tooltip"
                                                       title="Identificador: <?= esc($permissao['identificador'], 'attr') ?>"
                                                       aria-hidden="true"></i>
                                                    <?php if ($permissao['efetiva']) : ?>
                                                        <i class="mdi mdi-check-circle-outline small text-success ms-1" title="Acesso efetivo" aria-hidden="true"></i>
                                                    <?php endif ?>
                                                    <div id="detalhe-<?= esc($permissao['identificador'], 'attr') ?>">
                                                        <span class="visually-hidden">Identificador <?= esc($permissao['identificador']) ?>.</span>
                                                        <?php foreach ($permissao['herdada_de'] as $grupo) : ?>
                                                            <span class="d-block small texto-apoio">Herdada de <?= esc($grupo) ?></span>
                                                        <?php endforeach ?>
                                                        <?php if ($permissao['efetiva']) : ?>
                                                            <span class="visually-hidden">Acesso efetivo</span>
                                                        <?php endif ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            </fieldset>
                        <?php endforeach ?>

                        <?php if (! empty($foraDoCatalogo)) : ?>
                            <fieldset class="border-top pt-3 pb-2 m-0">
                                <legend class="categoria-permissoes">Fora do catálogo</legend>
                                <p class="small texto-apoio">A conta ainda tem estas permissões diretas, mas o catálogo não as lista mais. Elas continuam valendo até serem desmarcadas e salvas. Os atalhos "marcar todas" e "desmarcar" não mexem nelas.</p>
                                <div class="row row-cols-1 row-cols-md-2 gx-4 gy-2">
                                    <?php foreach ($foraDoCatalogo as $identificador) : ?>
                                        <div class="col">
                                            <div class="form-check d-flex align-items-start m-0 p-0">
                                                <input type="checkbox" class="form-check-input"
                                                       id="permissao-<?= esc($identificador, 'attr') ?>"
                                                       name="permissoes[]" value="<?= esc($identificador, 'attr') ?>" checked>
                                                <label class="form-check-label" for="permissao-<?= esc($identificador, 'attr') ?>">
                                                    <span class="font-monospace"><?= esc($identificador) ?></span>
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
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/acesso-permissoes.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/acesso-permissoes.js') ?>"></script>
<?= $this->endSection() ?>
