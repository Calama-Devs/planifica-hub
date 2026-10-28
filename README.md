# planifica-hub
PlanIFica Hub

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

Este ambiente é exclusivo para desenvolvimento, não para homologação ou produção.
Ele fornece PHP 8.3 com Apache, Composer, MySQL 8.4, SQLite e PCOV. Instale Docker
Engine e o plugin Docker Compose 2.24.0 ou superior no Linux; prefira uma versão
atualizada. O comando legado `docker-compose` V1 não é suportado. Confira a versão
do plugin e se o daemon está acessível:

```bash
docker compose version
docker version
```

Não é necessário instalar PHP, Composer ou MySQL no computador. O fluxo principal
abaixo é para Linux; a compatibilidade opcional com Windows está descrita ao final.

### Configuração e primeira execução

1. Escolha como fornecer a configuração: arquivo `.env` **ou** variáveis exportadas.
   Nos dois casos são obrigatórias `DOCKER_BANCO_NOME`, `DOCKER_BANCO_USUARIO`,
   `DOCKER_BANCO_SENHA` e `DOCKER_BANCO_SENHA_ROOT`. Não use `root` como usuário da
   aplicação e escolha senhas locais distintas e não vazias.

   **Com arquivo `.env`:** caso ainda não exista, copie o exemplo:

   ```bash
   cp .env.example .env
   ```

   Não sobrescreva um `.env` existente.
   Se já tiver um, acrescente as variáveis `DOCKER_BANCO_*` presentes no exemplo
   e ajuste os nomes das configurações conforme a seção de migração abaixo,
   preservando seus valores locais.

   Preencha essas quatro variáveis no `.env` local. Para senhas com `$`, use aspas
   simples para evitar interpolação pelo Compose. Não compartilhe esse arquivo.

   **Sem arquivo `.env`:** no Bash interativo, exporte as configurações. Os comandos
   abaixo solicitam as senhas sem exibi-las nem gravar seus valores no histórico:

   ```bash
   export DOCKER_BANCO_NOME=planifica_hub
   export DOCKER_BANCO_USUARIO=planifica
   read -r -s -p 'Senha local do usuario MySQL: ' DOCKER_BANCO_SENHA
   printf '\n'
   read -r -s -p 'Senha local do root MySQL: ' DOCKER_BANCO_SENHA_ROOT
   printf '\n'
   export DOCKER_BANCO_SENHA DOCKER_BANCO_SENHA_ROOT
   ```

   Execute os comandos Compose no mesmo terminal, mantendo essas variáveis
   disponíveis. Em automações locais, injete-as pelo mecanismo seguro do ambiente,
   sem os prompts interativos e sem imprimir seus valores. Não é preciso criar um
   arquivo `.env`, nem mesmo vazio.

   O Compose configura a conexão `database_default_*` da aplicação com esses mesmos
   valores e o hostname `banco`, pela rede interna. Se existir um `.env`, ele fica
   acessível pela montagem do projeto, somente para leitura. Usando os mesmos nomes
   com underscores, as variáveis externas prevalecem e os valores de banco usados
   fora do Docker podem ser mantidos. Configurações adicionais, como e-mail, podem
   continuar nesse arquivo; variáveis do shell só chegam ao container quando
   declaradas em `environment` no Compose ou em um override explícito.

2. Construa a imagem e inicie os serviços:

   ```bash
   docker compose config --quiet
   docker compose build app
   docker compose up -d --wait
   ```

   O Compose aguarda o MySQL aceitar uma consulta com o usuário da aplicação antes
   de iniciar o Apache. O banco não publica uma porta no computador; a aplicação
   fica disponível apenas na interface local, na porta 8080.

3. Prepare as dependências do volume e aplique as migrations no banco Docker local:

   ```bash
   docker compose exec app composer install --no-interaction --prefer-dist
   docker compose exec --user www-data app php spark migrate --all
   ```

   A imagem já instala o `composer.lock`, incluindo as ferramentas de desenvolvimento.
   A instalação explícita mantém o volume `vendor/` sincronizado, inclusive quando
   ele já existe de uma execução anterior. Não execute `shield:setup` novamente.
   As migrations não são executadas automaticamente na construção ou inicialização.

4. Acesse `http://localhost:8080/index.php/login`. O Apache substitui
   `php spark serve`; não inicie os dois na mesma porta. O Compose define
   `CI_ENVIRONMENT=development` e `app_baseURL=http://localhost:8080/` dentro do container.

### Migração de um `.env` existente

O CodeIgniter 4 aceita os nomes abaixo com underscores. Se seu `.env` ainda usa
pontos nessas configurações, renomeie as chaves, preservando os valores:

| Nome anterior | Nome atual |
|---|---|
| `app.baseURL` | `app_baseURL` |
| `database.default.hostname` | `database_default_hostname` |
| `database.default.database` | `database_default_database` |
| `database.default.username` | `database_default_username` |
| `database.default.password` | `database_default_password` |
| `database.default.DBDriver` | `database_default_DBDriver` |
| `database.default.port` | `database_default_port` |

Não mantenha as duas formas dessas chaves: o CodeIgniter consulta a forma com pontos
primeiro, o que pode fazer um valor antigo do arquivo prevalecer sobre a configuração
externa do Docker. Preserve a capitalização de `baseURL` e `DBDriver`.
As configurações de e-mail e as variáveis `DOCKER_BANCO_*` não precisam ser renomeadas.
Essa nomenclatura também funciona na execução sem Docker, por meio das classes de
configuração do CodeIgniter. Não compartilhe o `.env` nem seus valores.

### phpMyAdmin opcional

Por padrão, `docker compose up` inicia somente a aplicação e o banco, sem publicar
a interface administrativa. O phpMyAdmin é uma ferramenta complementar, desativada
por padrão no perfil `ferramentas`; a aplicação não depende dele para funcionar.

Para habilitá-lo explicitamente quando necessário:

```bash
docker compose --profile ferramentas up -d --wait phpmyadmin
```

Acesse `http://localhost:8082`, disponível somente no próprio computador. Não exponha
essa porta por túneis ou redirecionamentos para outras máquinas.

Na tela de login, use o usuário definido em `DOCKER_BANCO_USUARIO` e a senha definida
em `DOCKER_BANCO_SENHA`, fornecidos pelo `.env` ou pelo ambiente. A conexão com o
serviço `banco`, na porta interna 3306, já está configurada. Não publique essas credenciais nem use `root`
nas operações habituais (manual 10.5 e 11.8).

Alterações na estrutura do banco devem continuar sendo feitas por migrations, não
manualmente pelo phpMyAdmin (manual 8.5).

Ao terminar, pare somente a ferramenta, mantendo aplicação e banco em execução:

```bash
docker compose --profile ferramentas stop phpmyadmin
```

Execute também esse comando se o phpMyAdmin já estava ativo antes da adoção do
perfil: a mudança de configuração não encerra containers existentes automaticamente.

### Verificações dentro do container

Confira primeiro o PHP, as extensões e os requisitos das dependências:

```bash
docker compose ps
docker compose exec app php -v
docker compose exec app php -m
docker compose exec app composer check-platform-reqs
```

Execute as verificações com o mesmo usuário do Apache, evitando
arquivos de cache e log sem permissão de escrita para a aplicação:

```bash
docker compose exec --user www-data app vendor/bin/phpcs
docker compose exec --user www-data app vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker compose exec --user www-data app vendor/bin/phpunit
```

O PCOV está habilitado para cobertura de `app/`. Para rodar a suíte sem cobertura:

```bash
docker compose exec --user www-data app composer test
```

Os testes continuam usando SQLite em memória, sem depender dos dados do MySQL.
Os relatórios ficam em `/var/www/html/build/logs`, em um volume separado. Para
copiá-los para o computador:

```bash
docker compose cp app:/var/www/html/build/. ./build
```

Confira também no navegador cadastro válido e inválido, login correto e incorreto,
logout por POST e bloqueio da página inicial após sair, como descrito acima.
Reinicie os serviços com `docker compose restart` e confira se o usuário cadastrado
continua conseguindo entrar. Use somente dados fictícios nos testes locais.

Para comprovar a configuração externa com arquivo, mantenha `database_default_hostname`
do `.env` como `127.0.0.1`, sem a chave antiga com pontos. Sem arquivo, use apenas
as quatro variáveis `DOCKER_BANCO_*` exportadas. Nos dois casos a conexão dentro do
container deve usar `banco`, injetado pelo Compose, e as migrations devem funcionar.
Confirme também o cadastro e o login no navegador:
essa precedência é validada pela execução local. As migrations já existentes preparam
o banco; esta issue não adiciona alterações de estrutura nem modifica os fluxos de autenticação.

### Arquivos, volumes e manutenção

- `Dockerfile`: instala extensões, Composer e dependências; serve somente `public/`.
  O build define a diretiva `DocumentRoot`, confere o vhost habilitado e valida a
  sintaxe do Apache. Se a configuração esperada não estiver presente, a construção falha.
- `.dockerignore`: impede que `.env`, dependências locais e dados de execução entrem
  na imagem (manual 10.5 e 11.8).
- `docker/php/desenvolvimento.ini`: configura memória, inicialização de `$_ENV`
  e cobertura com PCOV.
- O entrypoint é o oficial da imagem PHP. As configurações com underscores são
  reconhecidas nativamente pelo CodeIgniter, sem script adicional de inicialização.
- `compose.yaml`: conecta aplicação e banco, sem credenciais fixas no código.
  O phpMyAdmin só é iniciado mediante ativação explícita do perfil `ferramentas`.
- A imagem do MySQL está fixada por digest SHA-256 de um índice multi-arquitetura
  (Linux amd64 e arm64). O digest preserva a imagem validada mesmo se a tag `8.4`
  mudar. Para atualizar, valide a nova imagem em um banco local de teste e atualize
  o digest no Compose; não remova essa fixação nem apague volumes existentes.
- O código é montado do computador somente para leitura: edições pelo editor no
  host aparecem sem reconstruir a imagem, mas o container não altera o código nem
  um eventual `.env`. Comandos que geram arquivos no repositório, como os geradores
  do Spark, precisam ser executados no host ou em uma configuração específica com
  escrita deliberadamente habilitada, fora do fluxo de execução descrito aqui.
  Dependências, `writable/` e `build/` usam volumes próprios. Por isso, os logs e as
  sessões Docker não aparecem no `writable/` local.
- Se `composer.lock` mudar, execute novamente o `composer install` acima. Se o
  Dockerfile ou o INI mudar, execute novamente `docker compose up -d --build --wait`.
- Alterar senhas ou o nome do banco no `.env` ou no ambiente não reconfigura um
  volume MySQL já inicializado. Mantenha os valores usados na criação ou faça uma alteração local
  controlada das credenciais; não apague volumes para tentar corrigir isso.
- Não publique a saída completa de `docker compose config`, pois pode conter senhas.
- O `.env` é opcional. Quando existe, só fica acessível em execução pela montagem
  do projeto: não entra na imagem nem deve ser copiado pelo Dockerfile. Nunca coloque
  credenciais em instruções `ARG`, `ENV` ou `RUN`.

Para parar e remover todos os containers do projeto, incluindo a ferramenta opcional,
preservando banco, sessões e dependências:

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
