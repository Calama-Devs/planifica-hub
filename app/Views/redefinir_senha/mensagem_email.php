<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Redefinição de senha • PlanificaHub</title>
</head>
<body style="background-color: #000000; color: #ffffff; margin: 0; padding: 0;">
    <table
        role="presentation"
        width="100%"
        border="0"
        cellpadding="0"
        cellspacing="0"
        bgcolor="#000000"
        style="background-color: #000000; border-collapse: collapse;"
    >
        <tr>
            <td align="center" style="padding: 48px 14px;">
                <table
                    role="presentation"
                    width="100%"
                    border="0"
                    cellpadding="0"
                    cellspacing="0"
                    style="background-color: #191c24; border: 1px solid #2c303a; border-collapse: separate;
                        border-radius: 12px; max-width: 600px; overflow: hidden;"
                >
                    <tr>
                        <td style="background-color: #0090e7; font-size: 0; height: 5px; line-height: 0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td
                            style="border-bottom: 1px solid #2c303a; font-family: Arial, Helvetica, sans-serif;
                                padding: 32px 36px 24px;"
                        >
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="middle">
                                        <div
                                            style="color: #0090e7; font-size: 13px; font-weight: 700;
                                                letter-spacing: 2px; margin-bottom: 9px;"
                                        >
                                            PLANIFICA-HUB
                                        </div>
                                        <div
                                            style="color: #ffffff; font-size: 25px; font-weight: 700; line-height: 1.3;"
                                        >
                                            Redefinição de senha
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td
                            style="color: #e8e9ee; font-family: Arial, Helvetica, sans-serif; font-size: 15px;
                                line-height: 1.7; padding: 30px 36px 28px;"
                        >
                            <p style="color: #ffffff; font-size: 17px; font-weight: 700; margin: 0 0 15px;">
                                Olá, <?= esc($user->username) ?>.
                            </p>
                            <p style="color: #c2c6d2; margin: 0 0 27px;">
                                Recebemos uma solicitação para redefinir a senha da sua conta no PlanificaHub.
                                Para continuar, utilize o botão abaixo.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" bgcolor="#0090e7" style="border-radius: 6px;">
                                        <a
                                            href="<?= esc(url_to('verify-magic-link') . '?token=' . rawurlencode($token), 'attr') ?>"
                                            style="background-color: #0090e7; border: 1px solid #0090e7;
                                                border-radius: 6px; color: #ffffff; display: block;
                                                font-family: Arial, Helvetica, sans-serif; font-size: 15px;
                                                font-weight: 700; padding: 14px 20px; text-align: center;
                                                text-decoration: none;"
                                        >
                                            Redefinir minha senha
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                style="background-color: #11151d; border-left: 3px solid #0090e7;
                                    border-radius: 4px; margin-top: 24px;"
                            >
                                <tr>
                                    <td
                                        style="color: #aeb4c4; font-family: Arial, Helvetica, sans-serif;
                                            font-size: 13px; line-height: 1.6; padding: 14px 16px;"
                                    >
                                        Este link é temporário. Se você não solicitou a redefinição, ignore esta
                                        mensagem.<br>
                                        Solicitação registrada em <?= esc($date) ?>.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p
                    style="color: #778096; font-family: Arial, Helvetica, sans-serif; font-size: 12px;
                        line-height: 1.5; margin: 20px 0 0; text-align: center;"
                >
                    Mensagem automática do PlanificaHub · Não responda a este e-mail.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
