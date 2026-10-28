# Redefinição de Senha

## O que foi implementado

Foi criado o fluxo de redefinição de senha por magic link do CodeIgniter Shield e adicionada a opção **Alterar Senha** ao menu do perfil.

Ao selecionar essa opção, a sessão atual é encerrada e o usuário é redirecionado para `/forgot-password`. Em seguida, solicita o link pelo e-mail, acessa uma sessão temporária, informa a nova senha e, após a alteração, retorna ao login. Durante essa sessão temporária, o acesso fica restrito à tela de redefinição e ao logout.

A nova senha passa pelas validações do Shield e também deve conter letra maiúscula, letra minúscula, número e caractere especial. Emojis não são permitidos.

## Onde estão os arquivos

| Arquivo | Finalidade |
| --- | --- |
| `app/Config/Routes.php` | Define as rotas do perfil, da solicitação e da redefinição de senha. |
| `app/Config/Auth.php` | Configura o magic link, os redirecionamentos e as views utilizadas pelo Shield. |
| `app/Filters/RedefinicaoSenhaFilter.php` | Restringe a navegação durante a sessão temporária. |
| `app/Controllers/RedefinirSenhaController.php` | Inicia o fluxo pelo perfil, recebe a nova senha e coordena a redefinição. |
| `app/Services/RedefinirSenhaService.php` | Valida a senha e atualiza o hash da conta. |
| `app/Views/partials/cabecalho.php` | Exibe a opção **Alterar Senha** no menu do perfil. |
| `app/Views/redefinir_senha/solicitar.php` | Formulário para solicitar o magic link. |
| `app/Views/redefinir_senha/solicitacao_recebida.php` | Confirma o envio do link. |
| `app/Views/redefinir_senha/redefinir.php` | Formulário para informar a nova senha. |
| `app/Views/redefinir_senha/mensagem_email.php` | Modelo do e-mail enviado ao usuário. |
| `public/assets/css/recuperacao-senha.css` | Estilos das telas de recuperação de senha. |
| `tests/feature/AutenticacaoTest.php` | Testes automatizados do fluxo de autenticação e redefinição. |
| `tests/feature/HomeTest.php` | Testa a opção do perfil e o redirecionamento para a solicitação. |

## Validação realizada

Os testes cobrem o acesso por magic link, a restrição da sessão temporária, as regras da nova senha, a proteção CSRF, a atualização do hash e o encerramento da sessão.

```powershell
php vendor/phpunit/phpunit/phpunit --filter AutenticacaoTest
```

Resultado da validação local: **24 testes e 98 asserções aprovados**.
