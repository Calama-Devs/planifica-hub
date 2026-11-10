<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Permissões da conta<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start mb-4">
                    <div>
                        <h4 class="card-title mb-1">Permissões da conta</h4>
                        <p class="mb-1"><strong><?= esc($conta['rotulo']) ?></strong></p>
                        <?php if ($conta['email'] !== '') : ?>
                            <p class="text-muted mb-2"><?= esc($conta['email']) ?></p>
                        <?php endif ?>
                        <p class="mb-0">
                            <span class="text-muted">Grupos:</span>
                            <?php if (empty($conta['grupos'])) : ?>
                                <span class="text-muted">nenhum</span>
                            <?php endif ?>
                            <?php foreach ($conta['grupos'] as $grupo) : ?>
                                <span class="badge badge-outline-info"><?= esc($grupo) ?></span>
                            <?php endforeach ?>
                        </p>
                    </div>
                    <a href="<?= url_to('acessos.index') ?>" class="btn btn-outline-secondary mt-3 mt-sm-0">
                        <i class="mdi mdi-arrow-left" aria-hidden="true"></i> Voltar às contas
                    </a>
                </div>

                <div class="alert alert-info" role="note">
                    <p class="mb-1"><strong>Como ler esta tela</strong></p>
                    <ul class="mb-0 ps-3">
                        <li><strong>Herdada:</strong> a conta recebe de um grupo. Não muda aqui: só alterando o grupo da conta ou a matriz de permissões do grupo.</li>
                        <li><strong>Direta:</strong> vínculo gravado só para esta conta. É o que esta tela concede e revoga.</li>
                        <li><strong>Acesso efetivo:</strong> o que a conta realmente pode fazer, somando herdadas e diretas.</li>
                    </ul>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabela-acessos"
                           aria-label="Permissões do catálogo e a origem do acesso desta conta">
                        <thead>
                            <tr>
                                <th scope="col">Permissão</th>
                                <th scope="col">Herdada de</th>
                                <th scope="col" class="text-center">Direta</th>
                                <th scope="col" class="text-center">Acesso efetivo</th>
                                <th scope="col" class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($permissoes)) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma permissão cadastrada no catálogo.</td>
                                </tr>
                            <?php endif ?>
                            <?php foreach ($permissoes as $permissao) : ?>
                                <tr>
                                    <td>
                                        <code><?= esc($permissao['identificador']) ?></code>
                                        <small class="d-block text-muted"><?= esc($permissao['descricao']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (empty($permissao['herdada_de'])) : ?>
                                            <span class="text-muted">Nenhum grupo</span>
                                        <?php endif ?>
                                        <?php foreach ($permissao['herdada_de'] as $grupo) : ?>
                                            <span class="badge badge-outline-info"><?= esc($grupo) ?></span>
                                        <?php endforeach ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($permissao['direta']) : ?>
                                            <span class="badge badge-outline-success">Sim</span>
                                            <?php if (! empty($permissao['herdada_de'])) : ?>
                                                <small class="d-block text-muted">também herdada</small>
                                            <?php endif ?>
                                        <?php else : ?>
                                            <span class="badge badge-outline-secondary">Não</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($permissao['efetiva']) : ?>
                                            <span class="badge badge-outline-success">Sim</span>
                                        <?php else : ?>
                                            <span class="badge badge-outline-secondary">Não</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($permissao['direta']) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#modal-revogar-permissao"
                                                    data-permissao="<?= esc($permissao['identificador'], 'attr') ?>"
                                                    aria-label="Revogar <?= esc($permissao['identificador'], 'attr') ?> diretamente">
                                                <i class="mdi mdi-close-circle-outline" aria-hidden="true"></i> Revogar
                                            </button>
                                        <?php else : ?>
                                            <form action="<?= url_to('acessos.permissoes.conceder', $conta['id']) ?>" method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="permissao" value="<?= esc($permissao['identificador'], 'attr') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary"
                                                        aria-label="Conceder <?= esc($permissao['identificador'], 'attr') ?> diretamente">
                                                    <i class="mdi mdi-plus-circle-outline" aria-hidden="true"></i> Conceder
                                                </button>
                                            </form>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <?php if (! empty($foraDoCatalogo)) : ?>
                    <h5 class="mt-5">Vínculos diretos fora do catálogo</h5>
                    <p class="text-muted">A conta ainda tem estas permissões diretas, mas o catálogo não as lista mais. Elas continuam valendo até serem revogadas.</p>
                    <ul class="list-group">
                        <?php foreach ($foraDoCatalogo as $identificador) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <code><?= esc($identificador) ?></code>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#modal-revogar-permissao"
                                        data-permissao="<?= esc($identificador, 'attr') ?>"
                                        aria-label="Revogar <?= esc($identificador, 'attr') ?> diretamente">
                                    <i class="mdi mdi-close-circle-outline" aria-hidden="true"></i> Revogar
                                </button>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-revogar-permissao" tabindex="-1" aria-labelledby="titulo-modal-revogar-permissao" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="<?= url_to('acessos.permissoes.revogar', $conta['id']) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="permissao" id="revogar-permissao-valor" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="titulo-modal-revogar-permissao">Revogar permissão direta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Revogar a permissão <code id="revogar-permissao-nome"></code> concedida diretamente a <strong><?= esc($conta['rotulo']) ?></strong>?</p>
                <p class="text-muted mb-0">Só o vínculo direto é removido. Se a conta recebe a permissão de um grupo, o acesso continua.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Revogar permissão</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/acesso-permissoes.js') ?>"></script>
<?= $this->endSection() ?>
