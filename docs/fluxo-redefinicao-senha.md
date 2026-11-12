# Redefinição de Senha

## O que foi implementado

O sistema oferece dois fluxos distintos para definir uma nova senha.

No perfil, o usuário autenticado acessa `/perfil/alterar-senha`, informa a senha atual, a nova senha e a confirmação. A senha atual é validada antes da alteração e a sessão é encerrada somente após a troca ser concluída com sucesso.

Quando não possui acesso à conta, o usuário solicita a recuperação em `/recuperar-senha`. O sistema cria um token `magic-link-reset`, separado do Magic Link nativo do Shield, e envia um link temporário de uso único. Após validá-lo, o ID do usuário fica em um `tempdata` temporário, utilizado exclusivamente pela redefinição e pelo cancelamento. Esse acesso não cria uma sessão autenticada nem libera outras funcionalidades. Após definir a nova senha, o usuário retorna ao login.

A nova senha passa pelas validações do Shield e deve ser diferente da senha anterior.

> A rota `POST /recuperar-senha` utiliza o filtro nativo `auth-rates` do Shield para limitar as solicitações por endereço IP. Essa medida reduz tentativas automatizadas e o abuso do envio de e-mails, mas não elimina completamente a possibilidade de inferir contas por diferenças no tempo de resposta. A mensagem exibida permanece genérica para não revelar se o e-mail está cadastrado.

## Finalidade dos tokens

O Magic Link nativo do Shield mantém o tipo `magic-link` e o redirecionamento normal de login. A recuperação usa exclusivamente `magic-link-reset`, persistido em `auth_identities` com a mesma expiração configurada para o Magic Link. A finalidade é validada no token antes de criar a autorização temporária, para que o link funcione em outro navegador ou dispositivo sem liberar outras funcionalidades.

## Onde estão os arquivos

| Arquivo | Finalidade |
| --- | --- |
| `app/Config/Routes.php` | Define as rotas do perfil, da solicitação e da redefinição de senha. |
| `app/Config/Auth.php` | Configura as views e as regras de senha utilizadas pelo Shield. |
| `app/Config/Services.php` | Registra a factory compartilhada do serviço de redefinição de senha. |
| `app/Controllers/RedefinirSenhaController.php` | Coordena a troca pelo perfil e a redefinição por magic link. |
| `app/Services/RedefinirSenhaService.php` | Valida a senha atual quando exigida, aplica as regras da nova senha e atualiza a credencial. |
| `app/Views/partials/cabecalho.php` | Exibe a opção **Alterar Senha** no menu do perfil. |
| `app/Views/redefinirsenha/solicitar.php` | Formulário para solicitar o magic link. |
| `app/Views/redefinirsenha/solicitacao_recebida.php` | Confirma o envio do link. |
| `app/Views/redefinirsenha/redefinir.php` | Formulário para informar a nova senha. |
| `app/Views/partials/modal_alterar_senha.php` | Modal de alteração de senha pelo perfil. |
| `app/Views/redefinirsenha/mensagem_email.php` | Modelo do e-mail para links nativos e de recuperação. |
| `public/assets/css/recuperacao-senha.css` | Estilos das telas de recuperação de senha. |
| `tests/feature/AutenticacaoTest.php` | Testes automatizados do fluxo de autenticação e redefinição. |
| `tests/feature/HomeTest.php` | Testa a opção do perfil e o acesso ao formulário de alteração de senha. |

## Validação realizada

Os testes cobrem a troca pelo perfil, a validação da senha atual, o acesso por magic link, a autorização temporária sem autenticação, as regras da nova senha, a proteção CSRF e a atualização da credencial.

```powershell
php vendor/bin/phpunit tests/feature/AutenticacaoTest.php
```
