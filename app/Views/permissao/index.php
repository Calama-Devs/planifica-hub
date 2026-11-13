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
                        <p class="text-muted mb-0">Consulta dos identificadores autorizados para verificação no Shield.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Identificador</th>
                                <th scope="col">Descrição</th>
                                <th scope="col">Origem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($permissoes)) : ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
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
