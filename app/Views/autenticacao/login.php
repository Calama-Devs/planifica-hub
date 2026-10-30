<?= $this->extend('layouts/autenticacao') ?>

<?= $this->section('titulo') ?>Acesso ao Sistema<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="content-wrapper full-page-wrapper auth login-2 login-bg p-0 d-flex flex-wrap align-items-stretch">
    <!-- Formulário de Login-->
    <div class="card col-12 col-md-5 col-lg-4 d-flex flex-column justify-content-between p-4 p-xl-5 border-0 shadow-lg" style="min-height: 100vh; background-color: #191c24;">
        <div class="my-auto w-100">
            <!-- Identidade Visual do Sistema -->
            <div class="mb-4 text-center">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <img src="<?= base_url('assets/images/logo-ifro-mini.png') ?>" alt="Logo IFRO" class="me-2" style="width: 36px; height: auto;">
                    <div class="text-start">
                        <h4 class="text-white mb-0 fw-bold">PlanIFica</h4>
                        <small class="text-muted">Acesso ao Sistema</small>
                    </div>
                </div>
                <h3 class="card-title mb-1 text-light">Login</h3>
                <p class="text-muted small mb-0">Informe suas credenciais para entrar na plataforma.</p>
            </div>

            <!-- Formulário de Autenticação -->
            <form action="<?= url_to('login') ?>" method="post">
                <?= csrf_field() ?>

                <div class="form-group mb-3">
                    <label for="floatingEmailInput" class="form-label text-light fw-medium">
                        E-mail <span class="text-danger">*</span>
                    </label>
                    <input type="email"
                           class="form-control p_input text-light"
                           id="floatingEmailInput"
                           name="email"
                           inputmode="email"
                           autocomplete="email"
                           placeholder="Digite seu e-mail"
                           value="<?= old('email') ?>"
                           required
                           autofocus>
                </div>

                <div class="form-group mb-3">
                    <label for="floatingPasswordInput" class="form-label text-light fw-medium">
                        Senha <span class="text-danger">*</span>
                    </label>
                    <input type="password"
                           class="form-control p_input text-light"
                           id="floatingPasswordInput"
                           name="password"
                           inputmode="text"
                           autocomplete="current-password"
                           placeholder="Digite sua senha"
                           required>
                </div>

                <div class="form-group d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check m-0 d-flex align-items-center">
                        <input type="checkbox"
                               name="remember"
                               class="form-check-input"
                               id="rememberMe"
                               <?php if (old('remember')): ?> checked<?php endif ?>>
                        <label class="form-check-label text-muted mb-0" for="rememberMe">
                            Lembrar-me
                        </label>
                    </div>
                    <a href="<?= url_to('magic-link') ?>" class="forgot-pass text-primary small text-decoration-none">
                        Esqueceu a senha?
                    </a>
                </div>

                <div class="text-center d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-block enter-btn py-2 fw-semibold">
                        <i class="mdi mdi-login me-1" aria-hidden="true"></i> Entrar
                    </button>
                </div>

                <!-- Feedback de Sessão / Erros -->
                <?php if (session('error') !== null): ?>
                    <div class="alert alert-danger py-2 small mt-3 mb-0" role="alert">
                        <?= esc(session('error')) ?>
                    </div>
                <?php elseif (session('errors') !== null): ?>
                    <div class="alert alert-danger py-2 small mt-3 mb-0" role="alert">
                        <?php if (is_array(session('errors'))): ?>
                            <?php foreach (session('errors') as $error): ?>
                                <?= esc($error) ?><br>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?= esc(session('errors')) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (session('message') !== null): ?>
                    <div class="alert alert-success py-2 small mt-3 mb-0" role="alert">
                        <?= esc(session('message')) ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Rodapé do Card de Login -->
        <div class="mt-4 pt-3 border-top border-secondary text-center">
            <p class="text-muted small mb-1">
                © <?= date('Y') ?> • PlanIFica • IFRO Campus Calama
            </p>
        </div>
    </div>

    <!-- Coluna Direita: Informações sobre a Imagem de Fundo -->
    <div class="col-12 col-md-7 col-lg-8 d-none d-md-flex flex-column justify-content-center align-items-center p-4 p-xl-5">
        <div class="p-4 p-xl-5 text-white shadow-lg" style="background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 16px; max-width: 600px;">
            <h2 class="display-6 fw-bold text-white mb-2">PlanIFica</h2>
            <p class="text-muted mb-4" style="font-size: 1rem;">
                Gestão e organização de horários acadêmicos e ensalamento.
            </p>

            <div class="p-3 rounded mb-4" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);">
                <p class="text-light mb-0 small" style="line-height: 1.6;">
                    Sistema construído por alunos do curso de Análise e Desenvolvimento de Sistemas para o IFRO Campus Porto Velho Calama, desenvolvido pelo time Calama Devs.
                </p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
