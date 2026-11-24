<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Confirme seu novo e-mail</title>
</head>
<body>
    <p>Olá, <?= esc($nome) ?>.</p>
    <p>Um operador iniciou a atualização excepcional do e-mail institucional da sua conta.</p>
    <p>Confirme que você pode receber mensagens neste endereço. O login atual continuará ativo até a confirmação.</p>
    <p><a href="<?= esc($urlConfirmacao, 'attr') ?>">Confirmar novo e-mail</a></p>
    <p>Se você não esperava esta mensagem, não confirme o endereço. A solicitação expirará automaticamente.</p>
</body>
</html>
