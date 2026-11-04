# planifica-hub
PlanIFica Hub

## Estrutura do projeto

Consulte [docs/estrutura-inicial.md](docs/estrutura-inicial.md) para o mapa de
diretórios e namespaces, as convenções e a política de uso seletivo do template
oficial. O desenvolvimento usa `develop`, a homologação usa `homolog` e a
produção usa `main`.

## Autenticação com CodeIgniter Shield

O projeto utiliza o Shield `^1.4`, instalado via Composer. A configuração segue a
[documentação oficial](https://shield.codeigniter.com/latest/getting_started/install/).

### Instalação local

Requisitos: PHP 8.2 ou superior, Composer e MySQL com suporte a InnoDB.
As verificações e os testes também precisam da extensão `sqlite3` do PHP.

1. Execute `composer install` para instalar as versões do `composer.lock`.
2. Caso ainda não exista um `.env` local, copie `.env.example` para `.env`.
3. Configure nesse arquivo a URL da aplicação e a conexão com um banco MySQL
   **local**, criado previamente. Não versione nem compartilhe as credenciais.
4. Execute `php spark migrate --all`. O comando aplica as migrations oficiais
   do Shield e do pacote Settings, descobertas em `vendor/`.
5. Execute `php spark serve` e acesse `http://localhost:8080/index.php/login`.
   Se configurar outra URL ou remover `index.php`, ajuste o endereço.

O setup já está versionado em `app/Config/Auth.php`, `AuthGroups.php`,
`AuthToken.php`, `Autoload.php`, `Security.php` e `Routes.php`.
Não é necessário executar novamente `php spark shield:setup`.
Os nomes e tipos exigidos pelo pacote são mantidos para compatibilidade,
incluindo suas tabelas, entidades e configurações.

### Fluxos disponíveis

- `GET /register` e `POST /register`: formulário e cadastro de usuário.
- `GET /login` e `POST /login`: formulário e autenticação por e-mail e senha.
- `POST /logout`: encerramento da sessão. Envie o token CSRF em um formulário
  com `csrf_field()`; `GET /logout` não é permitido.

O CSRF é global e utiliza sessão (manual 11.3). As telas e os Controllers são
fornecidos pelo próprio Shield. A página inicial exige autenticação pelo filtro `session`;
futuras rotas restritas devem utilizar os Filters de autenticação e autorização.
O catálogo de permissões, a convenção de nomes e os exemplos de checagem estão em
`docs/padroes/permissoes.md`.
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
sem acessar o MySQL da aplicação ou serviços externos. Cobrem cadastro,
hash de senha, login válido e inválido, logout e proteção CSRF.

Após configurar o MySQL local, confira no navegador: cadastro válido e inválido,
login com senha correta e incorreta, logout por formulário POST e nova tentativa
de acesso após sair (manual 16.1). Ao criar páginas restritas, teste também o
acesso direto sem autenticação e com perfis sem permissão.

## Desenvolvimento local com Docker no Linux

Ambiente exclusivo para desenvolvimento: PHP 8.3, Apache, Composer, MySQL 8.4,
SQLite e PCOV. Instale Docker Engine e o plugin Docker Compose; não é necessário
instalar PHP, Composer ou MySQL no computador.

### Configuração e primeira execução

> **Crie e preencha o `.env` antes de construir ou iniciar o projeto.** Substitua os
> textos de orientação das senhas por senhas locais distintas. Não compartilhe nem
> versione o arquivo (manual 10.5 e 11.8).

Execute os comandos na raiz do projeto, usando o `compose.yaml` original.
Se iniciou com `-p planifica-primeiro`, repita essa opção em todos os comandos,
como em `docker compose -p planifica-primeiro ps`.

#### 1. Verifique os requisitos

```bash
docker --version
docker compose version
docker info
```

Confirme as versões e o acesso ao Docker, sem erros de conexão ou permissão.

#### 2. Crie e configure o `.env`

```bash
if [ ! -e .env ]; then cp .env.example .env; fi
nano .env
```

A cópia preserva um `.env` existente. Edite com Nano ou outro editor e confira:

| Variável | Como configurar |
|---|---|
| `CI_ENVIRONMENT` | `development` |
| `app.baseURL` ou `app_baseURL` | `'http://localhost:8080/'`, com a barra final |
| `DOCKER_BANCO_NOME` | Nome do banco local, por exemplo `planifica_hub` |
| `DOCKER_BANCO_USUARIO` | Usuário da aplicação, por exemplo `planifica`; não use `root` |
| `DOCKER_BANCO_SENHA` | Senha local não vazia |
| `DOCKER_BANCO_SENHA_ROOT` | Outra senha local não vazia |

Use aspas simples nas senhas, especialmente com `$`. No Nano, salve com `Ctrl + O`,
`Enter` e saia com `Ctrl + X`.

As configurações do CodeIgniter aceitam pontos ou underscores, sem migração
obrigatória. O Compose fornece a conexão com o hostname `banco` e os valores
`DOCKER_BANCO_*`; os campos `database.default.*` do exemplo podem permanecer como estão.
**Se o banco já existir, preserve os valores usados na criação do volume:** alterar
o `.env` não reconfigura automaticamente suas credenciais.

#### 3. Valide, construa e inicie

```bash
docker compose config --quiet
docker compose build app
docker compose up -d --wait
docker compose ps
```

- `config --quiet`: valida sem exibir credenciais; não deve produzir saída nem erro.
- `build app`: constrói a imagem e instala as dependências do `composer.lock`.
- `up -d --wait`: inicia em segundo plano e aguarda os serviços.
- `ps`: deve mostrar `app` em execução, `banco` saudável e `127.0.0.1:8080->80/tcp`.

Corrija qualquer erro antes de continuar. O banco não publica porta no computador.
O volume vazio `dependencias` recebe o `vendor/` da imagem na primeira execução.
Se reutilizar um volume e as dependências mudarem, sincronize antes das migrations:

```bash
docker compose exec app composer install --no-interaction --prefer-dist
```

#### 4. Aplique as migrations no banco local

```bash
docker compose exec --user www-data app php spark migrate --all
```

Prepara as tabelas com o usuário do Apache (manual 8.5). Espere `Migrations complete.`,
sem erro de conexão. O aviso de PCNTL indisponível não impede, por si só, a execução.
As migrations não são automáticas; não execute `shield:setup` novamente.

#### 5. Acesse a aplicação

Abra `http://localhost:8080/`. Sem sessão, o redirecionamento esperado é para
`http://localhost:8080/index.php/login`. O Apache já serve a aplicação;
não execute `php spark serve`.

### Compatibilidade do `.env` com e sem Docker

Não é necessário renomear as configurações existentes. O `.env.example` mantém
a notação padrão com pontos; as formas equivalentes com underscores também funcionam:

| Com pontos | Com underscores |
|---|---|
| `app.baseURL` | `app_baseURL` |
| `database.default.hostname` | `database_default_hostname` |
| `database.default.database` | `database_default_database` |
| `database.default.username` | `database_default_username` |
| `database.default.password` | `database_default_password` |
| `database.default.DBDriver` | `database_default_DBDriver` |
| `database.default.port` | `database_default_port` |

Use apenas uma forma por configuração e preserve a capitalização de `baseURL` e
`DBDriver`. Fora do Docker, o CodeIgniter carrega o arquivo normalmente, sem mudanças
no fluxo de instalação local.

No Docker, `DOCKER_AMBIENTE=1` ativa a compatibilidade na inicialização de
desenvolvimento, após o carregamento do `.env`. O script
`docker/php/preparar_ambiente.php` aplica os valores do ambiente de execução às duas
formas em memória, garantindo a prioridade da conexão fornecida pelo Compose sem
reescrever o arquivo ou registrar credenciais. As variáveis não vazias com
underscores fornecidas pelo Compose têm prioridade sobre o arquivo local.

A URL pode ser definida por `app.baseURL` ou `app_baseURL` no `.env`. Se nenhuma estiver
definida, vale o padrão de `app/Config/App.php`: `http://localhost:8080/`. A URL não muda a porta
publicada: se usar outra porta, ajuste também `ports` no Compose; domínios próprios
precisam resolver por DNS ou `/etc/hosts`. Configurações de e-mail não são alteradas.

Os testes de `tests/unit/AmbienteDockerTest.php` cobrem as duas notações, a execução
local sem o adaptador e a precedência do Docker, usando somente configurações fictícias:

```bash
docker compose exec --user www-data app vendor/bin/phpunit --filter AmbienteDockerTest
```

### phpMyAdmin opcional

O phpMyAdmin não inicia por padrão: é uma ferramenta opcional do perfil `ferramentas`.
Para habilitar:

```bash
docker compose --profile ferramentas up -d --wait phpmyadmin
```

Acesse `http://localhost:8082` com `DOCKER_BANCO_USUARIO` e `DOCKER_BANCO_SENHA` do
`.env`; a conexão com `banco:3306` já está configurada. Não use `root` habitualmente,
não compartilhe credenciais nem exponha essa porta a outras máquinas.
Altere a estrutura do banco por migrations, não pelo phpMyAdmin (manual 8.5).

Para parar apenas a ferramenta:

```bash
docker compose --profile ferramentas stop phpmyadmin
```

Mudar o perfil no Compose não encerra um container que já estava ativo.

### Atualizar ou encerrar o ambiente

Se mudar o Dockerfile ou o INI, reconstrua e reinicie:

```bash
docker compose up -d --build --wait
```

Para remover os containers e a rede, incluindo a ferramenta opcional, preservando
os volumes:

```bash
docker compose --profile ferramentas down
```

**Não acrescente `--volumes` ou `-v`**: isso apagaria os volumes, incluindo o banco local.

### Compatibilidade opcional com Windows

O foco desta configuração e das verificações é Linux. Como alternativa, pode-se usar
Docker Desktop com containers Linux e Docker Compose no Windows. Os comandos Docker
acima também podem ser usados no PowerShell; somente a cópia inicial do exemplo muda
para `Copy-Item .env.example .env`, caso o `.env` ainda não exista.
Os executáveis dentro do container continuam sendo Linux: use `/` e não adicione `.bat`.
