<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Catálogo de Permissões<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4">
                    <div>
                        <h4 class="card-title mb-1">Catálogo de Permissões</h4>
                        <p class="text-muted mb-0">Consulta e gerenciamento dos identificadores autorizados para verificação no Shield.</p>
                    </div>
                    <div class="mt-3 mt-sm-0">
                        <a href="<?= url_to('permissoes.nova') ?>" class="btn btn-primary btn-icon-text">
                            <i class="mdi mdi-plus btn-icon-prepend" aria-hidden="true"></i> Nova Permissão
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Identificador</th>
                                <th scope="col">Descrição</th>
                                <th scope="col">Origem</th>
                                <th scope="col" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($permissoes)) : ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Nenhuma permissão cadastrada no catálogo.
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($permissoes as $permissao) : ?>
                                    <tr>
                                        <td>
                                            <code><?= esc($permissao['identificador']) ?></code>
                                        </td>
                                        <td>
                                            <?= esc($permissao['descricao']) ?>
                                        </td>
                                        <td>
                                            <?php if ($permissao['origem'] === 'sistema') : ?>
                                                <span class="badge badge-outline-info">Sistema</span>
                                            <?php else : ?>
                                                <span class="badge badge-outline-primary">Customizada</span>
                                            <?php endif ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= site_url('admin/permissoes/editar/' . (int) $permissao['id']) ?>"
                                               class="btn btn-sm btn-outline-warning"
                                               title="Editar descrição da permissão">
                                                <i class="mdi mdi-pencil" aria-hidden="true"></i> Editar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

