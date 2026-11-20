<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar e-mail</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/modern-vertical/style.css'), 'attr') ?>?v=<?= filemtime(FCPATH . 'assets/css/modern-vertical/style.css') ?>">
</head>
<body>
<main class="container py-5">
    <section class="card col-12 col-md-8 col-lg-6 mx-auto">
        <div class="card-body">
            <?php if (is_array($resultado)) : ?>
                <h1 class="h4">Confirmação de e-mail</h1>
                <p role="status" class="<?= $resultado['sucesso'] ? 'text-success' : 'text-danger' ?>">
                    <?= esc($resultado['mensagem']) ?>
                </p>
            <?php elseif ($token !== '') : ?>
                <h1 class="h4">Confirmar novo e-mail</h1>
                <p>Ao confirmar, este endereço passará a ser o login da conta. O endereço atual continuará ativo até esta etapa.</p>
                <form method="post" action="<?= esc(url_to('contas.email.confirmar'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
                    <button type="submit" class="btn btn-primary">Confirmar novo e-mail</button>
                </form>
            <?php else : ?>
                <h1 class="h4">Link de confirmação indisponível</h1>
                <p role="status" class="text-danger">O link de confirmação é inválido ou expirou.</p>
            <?php endif ?>
        </div>
    </section>
</main>
</body>
</html>
