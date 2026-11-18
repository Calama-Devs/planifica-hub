<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Acessos das contas<?= $this->endSection() ?>

<?= $this->section('estilos') ?>
<link rel="stylesheet" href="<?= esc(base_url('assets/vendor/tom-select/tom-select.bootstrap5.min.css'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/vendor/tom-select/tom-select.bootstrap5.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Acessos das contas</h4>
                <p class="text-muted">Escolha uma conta para ver e administrar as permissões concedidas diretamente a ela.</p>

                <div class="mb-3" data-bs-theme="dark">
                    <label for="pesquisa" class="form-label">Buscar por nome ou e-mail</label>
                    <div class="row g-2 align-items-start">
                        <div class="col-12 col-md-9">
                            <select id="pesquisa" name="pesquisa" class="form-control"
                                    data-url="<?= esc(url_to('acessos.pesquisa'), 'attr') ?>"
                                    aria-describedby="pesquisa-ajuda">
                                <option value="">Digite um nome ou e-mail</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="button" id="limpar-pesquisa" class="btn btn-outline-secondary">Limpar busca</button>
                        </div>
                    </div>
                    <small id="pesquisa-ajuda" class="text-muted">Digite parte do nome ou e-mail para filtrar a lista.</small>
                </div>
                <p id="pesquisa-mensagem" class="text-muted" role="status" aria-live="polite"></p>

                <div id="resultado-pesquisa" class="table-responsive" aria-busy="false">
                    <table class="table table-hover align-middle mb-0 tabela-acessos"
                           aria-label="Contas, grupos e quantidade de permissões diretas">
                        <thead>
                            <tr>
                                <th scope="col">Conta</th>
                                <th scope="col">E-mail</th>
                                <th scope="col">Grupos</th>
                                <th scope="col" class="text-center">Permissões diretas</th>
                                <th scope="col" class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="contas-encontradas">
                            <?php if (empty($contas)) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma conta encontrada.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($contas as $conta) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?= esc(url_to('acessos.permissoes', $conta['id']), 'attr') ?>"><?= esc($conta['rotulo']) ?></a>
                                        </td>
                                        <td><?= esc($conta['email']) ?></td>
                                        <td>
                                            <?php if (empty($conta['grupos'])) : ?>
                                                <span class="text-muted">Nenhum</span>
                                            <?php endif ?>
                                            <?php foreach ($conta['grupos'] as $grupo) : ?>
                                                <span class="badge badge-outline-info"><?= esc($grupo) ?></span>
                                            <?php endforeach ?>
                                        </td>
                                        <td class="text-center"><?= (int) $conta['total_diretas'] ?></td>
                                        <td class="text-end">
                                            <a href="<?= esc(url_to('acessos.permissoes', $conta['id']), 'attr') ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               aria-label="Ver permissões de <?= esc($conta['rotulo'], 'attr') ?>">
                                                <i class="mdi mdi-key-variant" aria-hidden="true"></i> Permissões
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>

                <div id="paginacao-contas" class="mt-3">
                    <?= view('partials/paginacao', ['paginador' => $paginador]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
    <script src="<?= esc(base_url('assets/vendor/tom-select/tom-select.base.min.js'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/vendor/tom-select/tom-select.base.min.js') ?>"></script>
    <script src="<?= esc(base_url('assets/js/acesso/index.js'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/js/acesso/index.js') ?>"></script>
<?= $this->endSection() ?>
