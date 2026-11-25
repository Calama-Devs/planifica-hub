<!DOCTYPE html>
<html lang="pt-BR">
<body style="background-color: #000000; color: #ffffff; margin: 0; padding: 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #000000; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 48px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #191c24; border: 1px solid #2c303a; border-collapse: separate; border-radius: 12px; max-width: 600px; overflow: hidden;">
                    <tr><td style="background-color: #0090e7; font-size: 0; height: 5px; line-height: 0;">&nbsp;</td></tr>
                    <tr>
                        <td style="border-bottom: 1px solid #2c303a; font-family: Arial, Helvetica, sans-serif; padding: 28px 36px 24px;">
                            <img src="<?= esc(base_url('assets/images/logo-ifro-mini.png'), 'attr') ?>" alt="IFRO" width="36" style="display: block; height: auto; margin-bottom: 16px;">
                            <div style="color: #0090e7; font-size: 13px; font-weight: 700; letter-spacing: 2px; margin-bottom: 9px;">PLANIFICA-HUB</div>
                            <div style="color: #ffffff; font-size: 25px; font-weight: 700; line-height: 1.3;">Convite para cadastro</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: #e8e9ee; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; padding: 30px 36px 28px;">
                            <p style="color: #c2c6d2; margin: 0 0 27px;">Você recebeu um convite para cadastro no PlanIFica Hub.</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr><td align="center" bgcolor="#0090e7" style="border-radius: 6px;"><a href="<?= esc($linkFinalizacao, 'attr') ?>" style="background-color: #0090e7; border: 1px solid #0090e7; border-radius: 6px; color: #ffffff; display: block; font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight: 700; padding: 14px 20px; text-align: center; text-decoration: none;">Finalizar cadastro</a></td></tr>
                            </table>
                            <p style="color: #aeb4c4; font-size: 13px; margin: 24px 0 0;">Este convite é válido por <?= esc((string) $validadeMinutos) ?> minutos. Se você não solicitou este convite, desconsidere esta mensagem.</p>
                        </td>
                    </tr>
                </table>
                <p style="color: #778096; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.5; margin: 20px 0 0; text-align: center;">Mensagem automática do PlanIFica Hub · Não responda a este e-mail.</p>
            </td>
        </tr>
    </table>
</body>
</html>
