# planifica-hub
PlanIFica Hub

## Estrutura do projeto

Consulte [docs/estrutura-inicial.md](docs/estrutura-inicial.md) para o mapa de
diretórios e namespaces, as convenções e a política de uso seletivo do template
oficial. O desenvolvimento usa `develop`, a homologação usa `homolog` e a
produção usa `main`.

## Desenvolvimento local com Docker no Linux

Siga o [guia de instalação e execução local com Docker no Linux](docs/ambiente/docker-linux.md).
Ele cobre a configuração do `.env`, o uso do `./scripts/hub.sh` ou do Docker
Compose, as migrations, o primeiro login e a solução de problemas.

No Windows, é possível usar o Docker Desktop com contêineres Linux e seguir
os comandos manuais do Docker Compose. Para a cópia inicial no PowerShell,
use `Copy-Item .env.example .env` somente se o `.env` ainda não existir.

## Autenticação com CodeIgniter Shield

O projeto utiliza o Shield `^1.4`, instalado via Composer. A configuração segue a
[documentação oficial](https://shield.codeigniter.com/latest/getting_started/install/).

### Instalação local sem Docker

Requisitos: PHP 8.2 ou superior, Composer e MySQL com suporte a InnoDB.
As verificações e os testes também precisam da extensão `sqlite3` do PHP.

1. Execute `composer install` para instalar as versões do `composer.lock`.
2. Caso ainda não exista um `.env` local, copie `.env.example` para `.env`.
3. Configure nesse arquivo a URL da aplicação e a conexão com um banco MySQL
   **local**, criado previamente. Não versione nem compartilhe as credenciais.
4. Execute `php spark migrate --all` para aplicar as migrations da aplicação
   e dos pacotes instalados, incluindo Shield e Settings.
5. Execute `php spark serve` e acesse `http://localhost:8080/index.php/login`.
   Se configurar outra URL ou remover `index.php`, ajuste o endereço.
6. Em um banco novo, consulte o
   [guia de primeiro acesso administrativo](docs/autenticacao/primeiro-administrador.md).

O setup já está versionado em `app/Config/Auth.php`, `AuthGroups.php`,
`AuthToken.php`, `Autoload.php`, `Security.php` e `Routes.php`.
Não é necessário executar novamente `php spark shield:setup`.
Os nomes e tipos exigidos pelo pacote são mantidos para compatibilidade,
incluindo suas tabelas, entidades e configurações.

### Fluxos disponíveis

- O cadastro público em `/register` está desativado.
- `GET /login` e `POST /login`: formulário e autenticação por e-mail e senha.
- `POST /logout`: encerramento da sessão. Envie o token CSRF em um formulário
  com `csrf_field()`; `GET /logout` não é permitido.

O CSRF é global e utiliza sessão (manual 11.3). A página inicial exige
autenticação pelo filtro `session`; futuras rotas restritas devem utilizar os
Filters de autenticação e autorização. O catálogo de permissões, a convenção
de nomes e os exemplos de checagem estão em `docs/padroes/permissoes.md`.
Links mágicos e outros fluxos por e-mail exigem configurar o remetente e o
transporte de e-mail no `.env` antes de utilizá-los.

### Verificações no Windows

```powershell
.\vendor\bin\phpcs.bat
php .\vendor\bin\phpstan analyse --no-progress --memory-limit=1G
.\vendor\bin\phpunit
```

O comando direto do PHPUnit gera relatórios de cobertura e precisa de Xdebug
ou PCOV habilitado no PHP da linha de comando. Para executar a mesma suíte
sem cobertura, use `composer test`, já configurado no projeto.

Os testes de autenticação usam SQLite em memória, com migrations dos pacotes,
sem acessar o MySQL da aplicação ou serviços externos. Cobrem bloqueio do
cadastro público, login válido e inválido, logout e proteção CSRF.

Após configurar o MySQL local, confira no navegador: bloqueio do cadastro
público, login com senha correta e incorreta, logout por formulário POST e
nova tentativa de acesso após sair (manual 16.1). Ao criar páginas restritas,
teste também o acesso direto sem autenticação e com perfis sem permissão.
