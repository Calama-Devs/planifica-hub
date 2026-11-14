<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Usuários<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                    <div>
                        <h4 class="card-title mb-1">Usuários</h4>
                        <p class="text-muted mb-0">Consulte as contas cadastradas e seus grupos.</p>
                    </div>
                    <?php if (auth()->user()->can('contas.criar')) : ?>
                        <a href="<?= url_to('usuarios.novo') ?>" class="btn btn-primary mt-3 mt-md-0">
                            <i class="mdi mdi-account-plus" aria-hidden="true"></i> Cadastrar usuário
                        </a>
                    <?php endif ?>
                </div>

                <form action="<?= url_to('usuarios.index') ?>" method="get" class="mb-4">
                    <label for="campo-busca" class="form-label">Pesquisar por nome ou e-mail</label>
                    <div class="row g-2">
                        <div class="col-12 col-md-8 col-lg-6">
                            <input type="search" id="campo-busca" name="busca" class="form-control"
                                   maxlength="100" value="<?= esc($busca) ?>">
                        </div>
                        <div class="col-auto d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-magnify" aria-hidden="true"></i> Pesquisar
                            </button>
                            <?php if ($busca !== '') : ?>
                                <a href="<?= url_to('usuarios.index') ?>" class="btn btn-info"
                                   title="Limpar pesquisa" aria-label="Limpar pesquisa">
                                    <i class="mdi mdi-close" aria-hidden="true"></i> Limpar
                                </a>
                            <?php endif ?>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" aria-label="Usuários cadastrados">
                        <thead>
                            <tr>
                                <th scope="col">Identificador</th>
                                <th scope="col">Nome</th>
                                <th scope="col">E-mail</th>
                                <th scope="col">Grupos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($usuarios === []) : ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <?= $busca === '' ? 'Nenhum usuário cadastrado.' : 'Nenhum usuário corresponde à pesquisa.' ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($usuarios as $usuario) : ?>
                                    <tr>
                                        <td><?= (int) $usuario['id'] ?></td>
                                        <td><?= esc($usuario['nome']) ?></td>
                                        <td><?= esc($usuario['email']) ?></td>
                                        <td>
                                            <?php if ($usuario['grupos'] === []) : ?>
                                                <span class="text-muted">Nenhum</span>
                                            <?php endif ?>
                                            <?php foreach ($usuario['grupos'] as $grupo) : ?>
                                                <span class="badge badge-outline-info"><?= esc($grupo) ?></span>
                                            <?php endforeach ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <?= view('partials/paginacao', ['paginador' => $paginador]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>