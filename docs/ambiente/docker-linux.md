# PlanIFica Hub: instalação e execução local com Docker no Linux

Este guia é para desenvolvimento local. O [Dockerfile](../../Dockerfile) prepara
PHP 8.3, Apache, Composer, SQLite e PCOV. O [Compose](../../compose.yaml) executa
a aplicação (`app`) e o MySQL 8.4 (`banco`). O [wrapper](../../scripts/hub.sh) usa
esses mesmos arquivos: ele não cria uma configuração separada.

## 1. Verifique os pré-requisitos

- Linux, Git e Bash 4 ou superior para usar o wrapper.
- [Docker Engine](https://docs.docker.com/engine/install/) em execução e
  [plugin Docker Compose](https://docs.docker.com/compose/install/linux/) com
  suporte a `up --wait`, `up --wait-timeout` e `config --environment`. Use
  `docker compose`, não o executável antigo `docker-compose`.
- Acesso ao Docker com seu usuário, por socket Unix local. O wrapper recusa
  conexões remotas por TCP ou SSH.
- Internet na primeira construção, para baixar imagens e dependências. Não é
  necessário instalar PHP, Apache, Composer nem MySQL no computador.

Baixe o repositório e use a branch indicada no DevCore. Na raiz do projeto, onde
estão `Dockerfile` e `compose.yaml`, execute:

```bash
bash --version
docker --version
docker compose version
docker info --format '{{.OSType}}'
```

O último comando deve retornar `linux`. Se o Docker não estiver disponível, veja
[Solução de problemas](#solução-de-problemas) antes de continuar.

## 2. Configure o `.env`

```bash
if [ ! -e .env ]; then cp .env.example .env; fi
nano .env
```

A cópia não sobrescreve um `.env` existente. Você pode usar outro editor no lugar
do Nano. Complete os campos de [`.env.example`](../../.env.example):

| Variável | Valor para desenvolvimento local |
|---|---|
| `CI_ENVIRONMENT` | `development` |
| `app.baseURL` ou `app_baseURL` | `'http://localhost:8080/'`, com a barra final. Use apenas uma das formas. |
| `DOCKER_BANCO_NOME` | Nome do banco, por exemplo `planifica_hub`. |
| `DOCKER_BANCO_USUARIO` | Usuário da aplicação, por exemplo `planifica`. Não use `root`. |
| `DOCKER_BANCO_SENHA` | Senha local do usuário da aplicação. Substitua o texto de exemplo. |
| `DOCKER_BANCO_SENHA_ROOT` | Senha local de administração do MySQL, diferente da senha da aplicação. Substitua o texto de exemplo. |

- Use valores não vazios e em uma linha para `CI_ENVIRONMENT` e `DOCKER_BANCO_*`.
  Mantenha as senhas entre aspas simples, especialmente se contiverem `$`.
- As variáveis exportadas no terminal têm prioridade na interpolação do Compose.
  Não execute `source .env`: o arquivo não é um script Bash.
- Em Docker, o Compose configura a conexão da aplicação com `banco:3306`,
  `MySQLi` e o nome, usuário e senha da aplicação da tabela acima. Deixe os
  campos `database.default.*` como vieram no exemplo. Fora do Docker, esses
  campos configuram o MySQL local.
  O CodeIgniter também aceita a forma com underscores (por exemplo,
  `database_default_hostname`). Não mantenha as duas formas para a mesma chave.
- O MySQL só usa as variáveis de inicialização na primeira criação do banco.
  Se já existir um volume `dados_mysql`, mantenha os valores usados para
  inicializá-lo. Alterar o `.env` não muda usuários, senhas ou bancos já criados.

Não versione nem compartilhe o `.env`. Prefira `./scripts/hub.sh verificar`,
que não exibe os valores do arquivo. Se validar manualmente, use
`docker compose config --quiet` e revise eventuais erros antes de compartilhá-los.
`docker compose config` sem `--quiet` e `docker compose config --environment`
podem expor credenciais (manual 10.5 e 11.8).

## 3. Construa e inicie

### Com o wrapper (recomendado)

```bash
./scripts/hub.sh verificar
./scripts/hub.sh construir
./scripts/hub.sh iniciar
./scripts/hub.sh status
```

`verificar` confere o Docker e a configuração sem iniciar serviços nem testar a
conexão ao MySQL. `construir` gera a imagem da aplicação e instala as dependências
do `composer.lock`. `iniciar` também constrói se a imagem não existir. Ele inicia
`app` e `banco` em segundo plano e aguarda os serviços por até 150 segundos.
Repita `iniciar` nas próximas sessões: ele reutiliza os serviços do mesmo projeto.

### Alternativa sem o wrapper

Execute na raiz do projeto, usando o `compose.yaml` original:

```bash
docker compose config --quiet
docker compose build app
docker compose up -d --wait app
docker compose ps
```

`config --quiet` valida o Compose, mas não aplica todas as regras do wrapper:
substitua as senhas de exemplo e use valores distintos mesmo nesse fluxo.
Se usar `-p` ou um arquivo Compose alternativo, mantenha a mesma opção em todos os
comandos manuais e não misture essa execução com o wrapper. O wrapper usa o nome
da pasta raiz como nome do projeto, resolve os arquivos pelo caminho do script e
ignora `COMPOSE_PROJECT_NAME`, `COMPOSE_FILE`, `COMPOSE_PROFILES` e `COMPOSE_ENV_FILES`.

Nos dois fluxos, confirme que `app` está em execução com a porta
`127.0.0.1:8080->80/tcp` e que `banco` está saudável (`healthy`). O MySQL não
publica porta no computador. O código é montado a partir do repositório;
dependências (`vendor/`), arquivos de `writable/`, relatórios (`build/`) e dados
do MySQL ficam em volumes separados.

## 4. Prepare o banco e faça o primeiro acesso

1. **Aplique as migrations no banco local.** Ter os serviços em execução não
   significa que as tabelas já existem. Nem o wrapper nem o Compose executam
   migrations ou seeds automaticamente. Rode:

   ```bash
   docker compose exec --user www-data app php spark migrate --all
   ```

   Aguarde `Migrations complete.`. O aviso de PCNTL indisponível não indica,
   por si só, falha nas migrations. Se houver erro, não avance para o login.
   Não execute `shield:setup`: a configuração já está no repositório.
2. **Prepare uma conta de teste.** O cadastro público está desativado, então não
   use `/register`. Em um banco já populado, use uma conta de teste existente.
   Em um banco novo, verifique as contas com o comando abaixo. A criação do
   primeiro administrador deve ser feita por um responsável autorizado,
   seguindo o [guia de primeiro acesso administrativo](../autenticacao/primeiro-administrador.md)
   e usando apenas dados fictícios no banco local.

   ```bash
   docker compose exec --user www-data app php spark shield:user list
   ```

   Se for o responsável pelo banco local e ainda não existir conta
   administrativa, este é o comando do guia adaptado ao contêiner:

   ```bash
   docker compose exec --user www-data app php spark shield:user create -n admin.local -e admin-local@example.com -g superadmin
   ```

   Digite e confirme a senha da conta de teste no terminal. Não a passe como
   argumento nem reutilize as senhas do MySQL. O comando confirma a inclusão
   no grupo `superadmin`; `shield:user list` confirma apenas que a conta existe.
3. **Acesse [http://localhost:8080/](http://localhost:8080/).** Sem sessão, o Hub
   redireciona para `http://localhost:8080/index.php/login`. Entre com o e-mail e
   a senha da conta de teste e confira a página inicial. Se criou a conta
   administrativa, confirme que ela acessa `/index.php/admin/acessos`. Depois,
   use a opção de sair e confira se a página volta a exigir login. O Apache já
   serve a aplicação; não execute `php spark serve` dentro do contêiner.

Não é preciso configurar e-mail para esse login por senha. Fluxos que enviam
e-mail, como recuperação de senha, exigem as configurações `email.*` no `.env`.

## Comandos do wrapper

Execute `./scripts/hub.sh <comando>` na raiz do repositório.

| Comando | O que faz |
|---|---|
| `ajuda` | Lista os comandos; funciona mesmo sem Docker ou `.env`. Também aceita `-h` e `--help`. |
| `verificar` | Confere Docker local, Docker Compose e `.env` sem iniciar serviços. |
| `construir` | Constrói a imagem do serviço `app`. |
| `iniciar` | Inicia `app` e `banco` em segundo plano. |
| `status` | Lista os contêineres do projeto, inclusive os parados. |
| `logs` | Mostra as últimas 100 linhas de `app` e acompanha novas mensagens. `Ctrl+C` encerra a consulta sem parar a aplicação. |
| `logs --sem-acompanhar` | Mostra as últimas 100 linhas de `app` e termina. |
| `phpmyadmin` | Inicia o phpMyAdmin opcional e o banco, se necessário. Não inicia `app`. |
| `parar` | Para os serviços, inclusive o phpMyAdmin, sem remover contêineres ou volumes. |
| `cs-fixer [fix] [argumentos]` | Executa PHPCBF, não PHP-CS-Fixer. `fix` é opcional; os demais argumentos são do PHPCBF. |
| `stan [argumentos]` | Executa `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`; aceita caminhos e opções. |
| `check` | Executa PHPCBF, PHPCS e PHPStan, nessa ordem, sem argumentos e interrompendo na primeira falha. |

`cs-fixer` e `check` **podem modificar os arquivos PHP locais**. Revise o
`git diff` depois de rodá-los. Os atalhos de qualidade usam contêineres
temporários (`docker compose run --rm --no-deps`) sem iniciar o banco.
PHPUnit não faz parte de `check`. Com `app` em execução, rode a suíte à parte:

```bash
docker compose exec --user www-data app vendor/bin/phpunit
```

Para verificar o próprio wrapper sem iniciar serviços nem usar seu `.env`, rode
`bash -n scripts/hub.sh` e `bash tests/scripts/hub_test.sh`. A suíte usa o parser
do Compose instalado e simula o acesso ao Docker Engine.

## phpMyAdmin opcional

```bash
./scripts/hub.sh phpmyadmin
```

A alternativa manual é `docker compose --profile ferramentas up -d --wait phpmyadmin`.
Abra [http://localhost:8082/](http://localhost:8082/) e entre com
`DOCKER_BANCO_USUARIO` e `DOCKER_BANCO_SENHA`. O servidor `banco:3306` já está
configurado. Não exponha a porta à rede, não use `root` para consultas rotineiras
e altere a estrutura do banco por migrations, não pelo phpMyAdmin (manual 8.5).

Para parar só a ferramenta, use `docker compose --profile ferramentas stop phpmyadmin`.

## Atualize ou encerre o ambiente

- Se mudar o Dockerfile ou a configuração do PHP em `docker/php/`, execute
  `./scripts/hub.sh construir` e `./scripts/hub.sh iniciar` novamente.
- Um volume `dependencias` vazio recebe o `vendor/` da imagem. Se reutilizar o
  volume após mudanças no `composer.lock`, sincronize as dependências com `app` em
  execução: `docker compose exec app composer install --no-interaction --prefer-dist`.
- No fim do trabalho, use `./scripts/hub.sh parar` ou
  `docker compose --profile ferramentas stop`. Os dados ficam disponíveis para a
  próxima execução. Para remover contêineres e a rede, preservando os volumes,
  use `docker compose --profile ferramentas down`. **Não adicione `-v` ou
  `--volumes`**: isso apagaria os volumes, inclusive o banco local.

## Solução de problemas

| Sintoma | Como investigar e corrigir |
|---|---|
| Docker ausente ou sem acesso ao socket | Confira `docker info` e `docker context ls`. Inicie o Docker local conforme a documentação da sua distribuição, verifique o contexto e siga as [orientações oficiais de acesso com seu usuário](https://docs.docker.com/engine/install/linux-postinstall/). O wrapper não usa `sudo` nem muda permissões. |
| `.env` ausente, inválido ou com valores inesperados | Confira se `.env` é um arquivo legível e compare as chaves com `.env.example`. Revise a sintaxe das aspas, senhas de exemplo, `CI_ENVIRONMENT` e variáveis exportadas no terminal. Após corrigir, rode `./scripts/hub.sh verificar` de novo. |
| A imagem não constrói | Confira a rede e a etapa indicada por `./scripts/hub.sh construir`. A primeira construção precisa acessar os repositórios de imagens, pacotes PHP e dependências do Composer. |
| Porta `8080` ou `8082` ocupada | Identifique qual serviço local usa a porta e libere-a se for seguro. Se precisar mudar a porta de `app`, ajuste `ports` no Compose e a `app.baseURL` no `.env`, mantendo a publicação em `127.0.0.1`. Mudar apenas a URL não libera nem publica uma porta. |
| Banco não fica saudável ou MySQL retorna `Access denied` | Confira `./scripts/hub.sh status` e `docker compose logs --tail=100 banco`. Verifique se nome, usuário e senha da aplicação correspondem aos valores com que o volume foi inicializado e se não há variáveis exportadas sobrescrevendo-os. Restaure a configuração correta; não apague os volumes. |
| Aplicação não conecta ao banco | Confira `./scripts/hub.sh logs --sem-acompanhar` e a saúde do `banco`. Dentro do Compose use `banco:3306`, não `localhost:3306`. O `localhost` da aplicação aponta para o próprio contêiner `app`. |
| Erro de tabela inexistente | Com o banco saudável, rode `php spark migrate --all` no serviço `app` conforme a etapa de primeiro acesso. |
| Dependência ou ferramenta PHP não encontrada | Reconstrua a imagem, inicie o ambiente e sincronize o volume `dependencias` com `composer install` no serviço `app`, como descrito acima. |

Se a subida falhou por porta ocupada e, mesmo após liberar a porta e repetir
`iniciar`, a aplicação continua sem acesso ao banco, verifique se `banco` está
ativo e saudável. Só então recrie **apenas** `app`, preservando os volumes:

```bash
docker compose up --detach --wait --no-deps --force-recreate app
```

O contêiner substituído perde arquivos gravados fora dos volumes ou da pasta
montada. Não recrie o banco para corrigir esse problema.

Revise logs antes de compartilhá-los. Não inclua senhas, tokens nem dados
pessoais em registros, capturas de tela ou mensagens no DevCore (manual 10.6 e 11.9).
