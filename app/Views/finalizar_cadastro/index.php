<?= $this->extend('layouts/autenticacao') ?>

<?= $this->section('titulo') ?>Finalize seu cadastro<?= $this->endSection() ?>
<?= $this->section('title') ?>Finalize seu cadastro<?= $this->endSection() ?>

<?= $this->section('estilos') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/finalizar-cadastro.css?v=' . (file_exists(FCPATH . 'assets/css/finalizar-cadastro.css') ? filemtime(FCPATH . 'assets/css/finalizar-cadastro.css') : '1')) ?>">
<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<main class="content-wrapper full-page-wrapper auth login-2 login-bg p-0 d-flex flex-wrap align-items-stretch">
    <!-- Coluna Esquerda: Formulário ou Estado do Convite -->
    <div class="card col-12 col-md-5 col-lg-4 d-flex flex-column justify-content-between p-4 p-xl-5 border-0 shadow-lg finalizar-cadastro-col-card">
        <div class="my-auto w-100">
            <!-- Identidade Visual Institucional -->
            <div class="mb-4 text-center">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" alt="Logo IFRO" class="me-2 finalizar-cadastro-logo">
                    <div class="text-start">
                        <h4 class="text-white mb-0 fw-bold">PlanIFica</h4>
                        <small class="text-muted">Acesso ao Sistema</small>
                    </div>
                </div>

                <h3 class="card-title mb-1 text-light">Finalize seu cadastro</h3>

                <?php if ($estado === 'valido') : ?>
                    <p class="text-muted small mb-3">Informe seus dados para concluir a ativação da sua conta institucional.</p>
                <?php endif ?>

                <!-- Alertas e Mensagens de Feedback -->
                <?php if (session('error') !== null) : ?>
                    <div class="alert alert-danger py-2 small mb-3" role="alert">
                        <?= esc(session('error')) ?>
                    </div>
                <?php elseif (session('errors') !== null) : ?>
                    <div class="alert alert-danger py-2 small mb-3" role="alert">
                        <?php if (is_array(session('errors'))) : ?>
                            <?php foreach (session('errors') as $erroItem) : ?>
                                <div><?= esc($erroItem) ?></div>
                            <?php endforeach ?>
                        <?php else : ?>
                            <?= esc(session('errors')) ?>
                        <?php endif ?>
                    </div>
                <?php endif ?>

                <?php if (session('success') !== null) : ?>
                    <div class="alert alert-success py-2 small mb-3" role="alert">
                        <?= esc(session('success')) ?>
                    </div>
                <?php endif ?>
            </div>

            <!-- Corpo da Página: Conforme o Estado do Convite -->
            <?php if ($estado === 'valido') : ?>
                <form action="<?= esc(url_to('finalizar-cadastro.concluir'), 'attr') ?>" method="post" data-envio-unico="true" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($dadosConvite['token'] ?? '') ?>">

                    <!-- Campo: Nome Completo -->
                    <div class="form-group mb-3 text-start">
                        <label for="nome-completo" class="form-label text-light fw-medium">
                            Nome completo <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control p_input text-light <?= session('errors.nome_completo') ? 'is-invalid' : '' ?>"
                               id="nome-completo"
                               name="nome_completo"
                               autocomplete="name"
                               placeholder="Informe seu nome completo"
                               value="<?= old('nome_completo') ?>"
                               required
                               autofocus>
                        <?php if (session('errors.nome_completo')) : ?>
                            <div class="invalid-feedback d-block">
                                <?= esc(session('errors.nome_completo')) ?>
                            </div>
                        <?php endif ?>
                    </div>

                    <!-- Campo: E-mail Institucional (Somente Leitura) -->
                    <div class="form-group mb-3 text-start">
                        <label for="email-institucional" class="form-label text-light fw-medium">
                            E-mail institucional
                        </label>
                        <input type="email"
                               class="form-control p_input finalizar-cadastro-readonly"
                               id="email-institucional"
                               name="email_institucional"
                               value="<?= esc($dadosConvite['email'] ?? '') ?>"
                               readonly
                               aria-readonly="true">
                        <small class="text-muted d-block mt-1">Endereço de e-mail associado ao convite recebido.</small>
                    </div>

                    <!-- Seção: Grupos Atribuídos (Somente Leitura) -->
                    <div class="form-group mb-3 text-start">
                        <label class="form-label text-light fw-medium d-block">
                            Grupos atribuídos
                        </label>
                        <div class="finalizar-cadastro-grupos-container d-flex flex-wrap gap-2 align-items-center">
                            <?php $gruposLista = $dadosConvite['grupos'] ?? [['codigo' => 'user', 'rotulo' => 'Usuário (user)']]; ?>
                            <?php foreach ($gruposLista as $grupoItem) : ?>
                                <span class="badge rounded-pill finalizar-cadastro-badge-grupo">
                                    <i class="mdi mdi-account-group me-1" aria-hidden="true"></i>
                                    <?= esc($grupoItem['rotulo'] ?? $grupoItem) ?>
                                </span>
                            <?php endforeach ?>
                        </div>
                        <small class="text-muted d-block mt-1">Perfis de acesso concedidos pela administração para sua conta.</small>
                    </div>

                    <!-- Campo: Senha -->
                    <div class="form-group mb-3 text-start">
                        <label for="senha" class="form-label text-light fw-medium">
                            Senha <span class="text-danger">*</span>
                        </label>
                        <div class="finalizar-cadastro-senha-wrapper">
                            <input type="password"
                                   class="form-control p_input text-light pe-5 <?= session('errors.password') ? 'is-invalid' : '' ?>"
                                   id="senha"
                                   name="password"
                                   autocomplete="new-password"
                                   placeholder="Defina sua senha de acesso"
                                   minlength="<?= esc((string) config('Auth')->minimumPasswordLength) ?>"
                                   aria-describedby="orientacoes-senha"
                                   required>
                            <button type="button"
                                    class="btn btn-link finalizar-cadastro-btn-toggle-senha"
                                    data-toggle-senha="senha"
                                    aria-label="Mostrar senha">
                                <i class="mdi mdi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="orientacoes-senha" class="form-text text-muted small mt-1">
                            Use pelo menos <?= esc((string) config('Auth')->minimumPasswordLength) ?> caracteres. A senha não pode ser comum nem conter dados pessoais.
                        </div>
                        <?php if (session('errors.password')) : ?>
                            <div class="invalid-feedback d-block">
                                <?= esc(session('errors.password')) ?>
                            </div>
                        <?php endif ?>
                    </div>

                    <!-- Campo: Confirmar Senha -->
                    <div class="form-group mb-4 text-start">
                        <label for="confirmar-senha" class="form-label text-light fw-medium">
                            Confirmar senha <span class="text-danger">*</span>
                        </label>
                        <div class="finalizar-cadastro-senha-wrapper">
                            <input type="password"
                                   class="form-control p_input text-light pe-5 <?= session('errors.password_confirm') ? 'is-invalid' : '' ?>"
                                   id="confirmar-senha"
                                   name="password_confirm"
                                   autocomplete="new-password"
                                   placeholder="Repita a senha informada"
                                   minlength="<?= esc((string) config('Auth')->minimumPasswordLength) ?>"
                                   required>
                            <button type="button"
                                    class="btn btn-link finalizar-cadastro-btn-toggle-senha"
                                    data-toggle-senha="confirmar-senha"
                                    aria-label="Mostrar senha">
                                <i class="mdi mdi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="erro-divergencia-senha" class="invalid-feedback d-none">
                            As senhas informadas não correspondem.
                        </div>
                        <?php if (session('errors.password_confirm')) : ?>
                            <div class="invalid-feedback d-block">
                                <?= esc(session('errors.password_confirm')) ?>
                            </div>
                        <?php endif ?>
                    </div>

                    <!-- Ações: Botão de Finalização e Retorno -->
                    <div class="d-grid gap-2 mb-3">
                        <button type="submit"
                                id="btn-finalizar-cadastro"
                                class="btn btn-primary btn-block enter-btn py-2 fw-semibold"
                                data-texto-processando="Finalizando cadastro...">
                            <i class="mdi mdi-check-circle me-1" aria-hidden="true"></i> Finalizar cadastro
                        </button>
                        <a href="<?= esc(url_to('login'), 'attr') ?>" class="btn btn-outline-secondary py-2">
                            <i class="mdi mdi-arrow-left me-1" aria-hidden="true"></i> Voltar ao login
                        </a>
                    </div>
                </form>

            <?php elseif ($estado === 'expirado') : ?>
                <!-- Estado: Convite Expirado -->
                <div class="finalizar-cadastro-status-card">
                    <i class="mdi mdi-clock-alert-outline text-warning finalizar-cadastro-status-icon" aria-hidden="true"></i>
                    <h4 class="finalizar-cadastro-status-titulo">Convite Expirado</h4>
                    <p class="finalizar-cadastro-status-texto">
                        O prazo de validade deste convite terminou. Por questões de segurança, os convites possuem tempo limite para utilização.<br><br>
                        Por favor, solicite um novo convite ao responsável institucional para concluir o seu cadastro.
                    </p>
                    <a href="<?= esc(url_to('login'), 'attr') ?>" class="btn btn-primary w-100 py-2">
                        <i class="mdi mdi-login me-1" aria-hidden="true"></i> Voltar ao login
                    </a>
                </div>

            <?php elseif (in_array($estado, ['cancelado', 'invalido', 'invalido_ou_cancelado'], true)) : ?>
                <!-- Estado Combinado: Convite Inválido ou Cancelado -->
                <div class="finalizar-cadastro-status-card">
                    <i class="mdi mdi-alert-circle-outline text-danger finalizar-cadastro-status-icon" aria-hidden="true"></i>
                    <h4 class="finalizar-cadastro-status-titulo">Convite Inválido ou Cancelado</h4>
                    <p class="finalizar-cadastro-status-texto">
                        Este link de convite não está disponível para utilização. Ele pode ter sido cancelado pela administração ou conter um endereço incorreto.<br><br>
                        Verifique se o link foi copiado integralmente ou solicite um novo convite ao responsável institucional.
                    </p>
                    <a href="<?= esc(url_to('login'), 'attr') ?>" class="btn btn-primary w-100 py-2">
                        <i class="mdi mdi-arrow-left me-1" aria-hidden="true"></i> Voltar ao login
                    </a>
                </div>

            <?php elseif ($estado === 'utilizado') : ?>
                <!-- Estado: Convite Já Utilizado -->
                <div class="finalizar-cadastro-status-card">
                    <i class="mdi mdi-account-check-outline text-info finalizar-cadastro-status-icon" aria-hidden="true"></i>
                    <h4 class="finalizar-cadastro-status-titulo">Convite Já Utilizado</h4>
                    <p class="finalizar-cadastro-status-texto">
                        Este convite já foi utilizado anteriormente para a criação de uma conta de acesso.<br><br>
                        Você já pode acessar o PlanIFica Hub com suas credenciais cadastradas.
                    </p>
                    <a href="<?= esc(url_to('login'), 'attr') ?>" class="btn btn-primary w-100 py-2">
                        <i class="mdi mdi-login me-1" aria-hidden="true"></i> Ir para o login
                    </a>
                </div>

            <?php elseif ($estado === 'erro_temporario') : ?>
                <!-- Estado: Erro Temporário -->
                <div class="finalizar-cadastro-status-card">
                    <i class="mdi mdi-cloud-alert text-secondary finalizar-cadastro-status-icon" aria-hidden="true"></i>
                    <h4 class="finalizar-cadastro-status-titulo">Instabilidade Temporária</h4>
                    <p class="finalizar-cadastro-status-texto">
                        Não foi possível consultar os dados do convite no momento devido a uma instabilidade temporária.<br><br>
                        Por favor, aguarde alguns instantes e tente acessar o link novamente.
                    </p>
                    <a href="<?= esc(url_to('login'), 'attr') ?>" class="btn btn-outline-secondary w-100 py-2">
                        <i class="mdi mdi-arrow-left me-1" aria-hidden="true"></i> Voltar ao login
                    </a>
                </div>

            <?php else : ?>
                <!-- Estado Padrão: Convite Inválido ou Cancelado -->
                <div class="finalizar-cadastro-status-card">
                    <i class="mdi mdi-alert-circle-outline text-danger finalizar-cadastro-status-icon" aria-hidden="true"></i>
                    <h4 class="finalizar-cadastro-status-titulo">Convite Inválido ou Cancelado</h4>
                    <p class="finalizar-cadastro-status-texto">
                        Este link de convite não está disponível para utilização. Verifique se o endereço foi copiado integralmente ou solicite um novo convite ao responsável institucional.
                    </p>
                    <a href="<?= esc(url_to('login'), 'attr') ?>" class="btn btn-primary w-100 py-2">
                        <i class="mdi mdi-arrow-left me-1" aria-hidden="true"></i> Voltar ao login
                    </a>
                </div>
            <?php endif ?>
        </div>

        <!-- Rodapé Institucional -->
        <div class="mt-4 pt-3 border-top border-secondary text-center">
            <p class="text-muted small mb-0">
                © <?= date('Y') ?> IFRO Campus Porto Velho Calama
            </p>
        </div>
    </div>

    <!-- Coluna Direita: Identidade Visual e Informações do Campus -->
    <div class="col-12 col-md-7 col-lg-8 d-none d-md-flex flex-column justify-content-center align-items-center p-4 p-xl-5">
        <div class="p-4 p-xl-5 text-white shadow-lg finalizar-cadastro-lateral-box">
            <h2 class="display-6 fw-bold text-white mb-2">PlanIFica</h2>
            <p class="text-muted mb-4 finalizar-cadastro-subtitulo-lateral">
                Plataforma que integra sistemas e serviços do IFRO.
            </p>
            <div class="p-3 rounded mb-4 finalizar-cadastro-descricao-box">
                <p class="text-light mb-0 small finalizar-cadastro-descricao-texto">
                    Sistema construído por alunos do curso de Análise e Desenvolvimento de Sistemas para o IFRO Campus Porto Velho Calama, desenvolvido pelo time Calama Devs.
                </p>
            </div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/finalizar-cadastro.js?v=' . (file_exists(FCPATH . 'assets/js/finalizar-cadastro.js') ? filemtime(FCPATH . 'assets/js/finalizar-cadastro.js') : '1')) ?>"></script>
<?= $this->endSection() ?>
