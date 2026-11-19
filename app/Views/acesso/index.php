<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Acessos das contas<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Acessos das contas</h4>
                <p class="text-muted">Escolha uma conta para ver e administrar as permissões concedidas diretamente a ela.</p>

                <div class="table-responsive">
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
                        <tbody>
                            <?php if (empty($contas)) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma conta encontrada.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($contas as $conta) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url_to('acessos.permissoes', $conta['id']) ?>"><?= esc($conta['rotulo']) ?></a>
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
                                            <a href="<?= url_to('acessos.permissoes', $conta['id']) ?>"
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

                <div class="mt-3">
                    <?= view('partials/paginacao', ['paginador' => $paginador]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
