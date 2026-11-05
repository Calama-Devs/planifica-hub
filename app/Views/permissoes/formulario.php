<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?><?= $modo === 'edicao' ? 'Editar Permissão' : 'Nova Permissão' ?><?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 col-lg-8 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title"><?= $modo === 'edicao' ? 'Editar Permissão' : 'Cadastrar Nova Permissão' ?></h4>
                <p class="card-description">
                    <?= $modo === 'edicao'
                        ? 'Atualize a descrição da permissão para manter o catálogo do Shield consistente.'
                        : 'Preencha o identificador padronizado e a descrição para cadastrar a permissão no catálogo.' ?>
                </p>

                <?php
                $actionUrl = $modo === 'edicao'
                    ? site_url('admin/permissoes/atualizar/' . (int) $permissao['id'])
                    : url_to('permissoes.salvar');
                ?>

                <form action="<?= $actionUrl ?>" method="post">
                    <?= csrf_field() ?>

                    <?php if ($modo === 'edicao') : ?>
                        <div class="mb-3">
                            <label class="form-label d-block text-muted mb-1">Identificador da Permissão</label>
                            <div class="d-flex align-items-center gap-2">
                                <code class="fs-6"><?= esc($permissao['identificador'] ?? '') ?></code>
                                <?php if (($permissao['origem'] ?? '') === 'sistema') : ?>
                                    <span class="badge badge-outline-info">Sistema</span>
                                <?php else : ?>
                                    <span class="badge badge-outline-primary">Customizada</span>
                                <?php endif ?>
                            </div>
                            <small class="form-text text-muted d-block mt-1">
                                Identificadores são estáveis e integrados aos acessos do sistema. Apenas a descrição pode ser alterada.
                            </small>
                        </div>
                    <?php else : ?>
                        <div class="mb-3">
                            <label for="campo-identificador" class="form-label">Identificador da Permissão</label>
                            <input type="text"
                                   id="campo-identificador"
                                   name="identificador"
                                   class="form-control <?= erro_campo('identificador') ?>"
                                   placeholder="ex.: relatorios.emitir"
                                   maxlength="100"
                                   value="<?= old('identificador') ?>"
                                   required>
                            <?= view('partials/erros_validacao', ['campo' => 'identificador']) ?>
                            <small class="form-text text-muted">
                                Formato obrigatório: <code>contexto.acao</code> em minúsculas e sem acentos (ex.: <code>contas.consultar</code>, <code>relatorios.emitir</code>).
                            </small>
                        </div>
                    <?php endif ?>

                    <div class="mb-4">
                        <label for="campo-descricao" class="form-label">Descrição</label>
                        <textarea id="campo-descricao"
                                  name="descricao"
                                  class="form-control <?= erro_campo('descricao') ?>"
                                  rows="3"
                                  maxlength="255"
                                  placeholder="Descreva de forma clara e objetiva o que esta permissão autoriza"
                                  required><?= old('descricao', $permissao['descricao'] ?? '') ?></textarea>
                        <?= view('partials/erros_validacao', ['campo' => 'descricao']) ?>
                        <small class="form-text text-muted">
                            Descrição em português, curta, começando com verbo no infinitivo (de 5 a 255 caracteres).
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save btn-icon-prepend" aria-hidden="true"></i> Salvar
                        </button>
                        <a href="<?= url_to('permissoes.index') ?>" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

