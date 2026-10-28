<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Sobre<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Sobre o PlanIFica Hub</h4>
                <p class="text-muted mb-0">Sistema institucional de planejamento e gestão.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Padrão Visual de Mensagens</h4>
                <p class="card-description">Apresentação padronizada de mensagens e alertas do sistema.</p>

                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="mdi mdi-check-circle me-2 fs-5" aria-hidden="true"></i>
                    <div>Exemplo de confirmação ou operação realizada com sucesso.</div>
                </div>

                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="mdi mdi-alert-circle me-2 fs-5" aria-hidden="true"></i>
                    <div>Exemplo de mensagem de erro ou falha ao executar a ação.</div>
                </div>

                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="mdi mdi-alert me-2 fs-5" aria-hidden="true"></i>
                    <div>Exemplo de aviso ou recomendação de atenção ao usuário.</div>
                </div>

                <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
                    <i class="mdi mdi-information-outline me-2 fs-5" aria-hidden="true"></i>
                    <div>Exemplo de notificação informativa do sistema.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Demonstração de Validação por Campo</h4>
                <p class="card-description">Estrutura de campos com feedback visual e mensagens de validação.</p>

                <form>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="campo-nome-exemplo" class="form-label">Nome Completo</label>
                        <input type="text" id="campo-nome-exemplo" name="nome" class="form-control" placeholder="Ex.: Maria da Silva" value="Maria da Silva" readonly>
                        <div class="form-text text-muted">Campo preenchido corretamente.</div>
                    </div>

                    <div class="mb-3">
                        <label for="campo-email-exemplo" class="form-label">E-mail Institucional</label>
                        <input type="email" id="campo-email-exemplo" name="email" class="form-control is-invalid" placeholder="nome@exemplo.com" value="email-invalido" readonly aria-describedby="feedback-email-exemplo">
                        <div id="feedback-email-exemplo" class="invalid-feedback d-block" role="alert">
                            Informe um endereço de e-mail institucional válido.
                        </div>
                    </div>

                    <div class="mb-0">
                        <?= $this->include('partials/erros_validacao', ['campo' => 'exemplo_dinamico']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
