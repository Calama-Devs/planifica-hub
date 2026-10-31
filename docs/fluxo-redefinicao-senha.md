# Redefinição de Senha

## O que foi implementado

O sistema oferece dois fluxos distintos para definir uma nova senha.

No perfil, o usuário autenticado acessa `/perfil/alterar-senha`, informa a senha atual, a nova senha e a confirmação. A senha atual é validada antes da alteração e a sessão é encerrada somente após a troca ser concluída com sucesso.

Quando não possui acesso à conta, o usuário solicita um magic link em `/forgot-password`. O link cria uma sessão temporária na qual não é exigida a senha atual. Durante essa sessão, o acesso fica restrito à tela de redefinição e ao logout. Após definir a nova senha, o usuário retorna ao login.

A nova senha passa pelas validações do Shield e também deve conter letra maiúscula, letra minúscula, número e caractere especial. Emojis não são permitidos.

## Dívida técnica: finalidade do Magic Link

O Shield usa a marca temporária `magicLogin` para qualquer autenticação realizada por Magic Link. No estado atual do PlanificaHub, todo Magic Link é tratado intencionalmente como recuperação de senha e direcionado para `/reset-password`.

Isso atende ao fluxo atualmente disponível, mas impede usar Magic Link no futuro como uma forma independente de login sem senha. Caso esse segundo uso seja necessário, a finalidade do link deverá ser persistida e diferenciada no próprio fluxo ou token, com ajustes em `loginRedirect()` e no filtro de redefinição. Uma flag temporária criada antes do envio do e-mail não é suficiente, pois o usuário pode abrir o link em outro navegador ou dispositivo.

## Onde estão os arquivos

| Arquivo | Finalidade |
| --- | --- |
| `app/Config/Routes.php` | Define as rotas do perfil, da solicitação e da redefinição de senha. |
| `app/Config/Auth.php` | Configura o magic link, os redirecionamentos e as views utilizadas pelo Shield. |
| `app/Config/Services.php` | Registra a factory compartilhada do serviço de redefinição de senha. |
| `app/Filters/RedefinicaoSenhaFilter.php` | Restringe a navegação durante a sessão temporária. |
| `app/Controllers/RedefinirSenhaController.php` | Coordena a troca pelo perfil e a redefinição por magic link. |
| `app/Services/RedefinirSenhaService.php` | Valida a senha atual quando exigida, aplica as regras da nova senha e atualiza a credencial. |
| `app/Views/partials/cabecalho.php` | Exibe a opção **Alterar Senha** no menu do perfil. |
| `app/Views/redefinir_senha/solicitar.php` | Formulário para solicitar o magic link. |
| `app/Views/redefinir_senha/solicitacao_recebida.php` | Confirma o envio do link. |
| `app/Views/redefinir_senha/redefinir.php` | Formulário para informar a nova senha. |
| `app/Views/redefinir_senha/mensagem_email.php` | Modelo do e-mail enviado ao usuário. |
| `public/assets/css/recuperacao-senha.css` | Estilos das telas de recuperação de senha. |
| `tests/feature/AutenticacaoTest.php` | Testes automatizados do fluxo de autenticação e redefinição. |
| `tests/feature/HomeTest.php` | Testa a opção do perfil e o acesso ao formulário de alteração de senha. |

## Validação realizada

Os testes cobrem a troca pelo perfil, a validação da senha atual, o acesso por magic link, a restrição da sessão temporária, as regras da nova senha, a proteção CSRF, a atualização da credencial e o encerramento da sessão após sucesso.

```powershell
php vendor/bin/phpunit tests/feature/AutenticacaoTest.php
```
