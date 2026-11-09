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

### Atalho opcional com Bash

O wrapper `scripts/hub.sh` utiliza o **mesmo `compose.yaml` e Dockerfile** dos
comandos manuais abaixo. Requer Linux, Bash 4 ou superior e o plugin
`docker compose` com suporte a `up --wait` e `config --environment`. Execute a
partir da raiz do projeto:

A cada comando, uma das três artes de **PLANIFICA** é sorteada para o banner.
As cores do banner são utilizadas somente no terminal, sem códigos ANSI na saída redirecionada.
A arte isométrica precisa de um terminal largo
(até 120 colunas) para não quebrar as linhas. Não é necessário instalar fontes
ou ferramentas adicionais: as artes estão incluídas no script.

```bash
./scripts/hub.sh ajuda
# Somente se o .env ainda não existir; o wrapper não cria nem altera esse arquivo:
cp -n .env.example .env
nano .env
./scripts/hub.sh verificar
./scripts/hub.sh construir
./scripts/hub.sh iniciar
./scripts/hub.sh status
```

Preencha as variáveis da tabela de configuração abaixo, incluindo senhas locais
distintas no lugar dos textos de exemplo. O wrapper rejeita `.env` ausente,
ilegível, sem `CI_ENVIRONMENT = development` ou inválido para o Compose, sem
imprimir seu conteúdo. Também recusa valores obrigatórios compostos apenas de
espaços, usuário `root` para a aplicação e senhas iguais ou ainda com o texto do
exemplo. Use valores em uma única linha para `CI_ENVIRONMENT` e `DOCKER_BANCO_*`.
A verificação usa os valores resolvidos pelo Compose: variáveis exportadas no
terminal têm prioridade sobre o `.env`. Nunca utilize `source .env`: ele não é
um script Bash. `verificar` não testa a conexão com um banco já inicializado;
preserve as credenciais originais de seus volumes.

| Comando | Operação |
|---|---|
| `verificar` | Confere Docker local disponível, plugin Compose e configuração, sem iniciar serviços |
| `construir` | Executa o build de `app` com o Dockerfile existente |
| `iniciar` | Inicia `app` e sua dependência `banco` em segundo plano, aguardando até 150 segundos |
| `phpmyadmin` | Inicia a ferramenta opcional e o banco, se necessário; acesso padrão em `http://localhost:8082` |
| `status` | Lista os containers do projeto, inclusive os parados |
| `logs` | Acompanha os últimos 100 registros de `app`; saia com `Ctrl + C` sem parar a aplicação |
| `logs --sem-acompanhar` | Exibe os últimos 100 registros e termina |
| `parar` | Para os serviços, incluindo o phpMyAdmin se ativo, sem remover containers ou volumes |
| `cs-fixer [fix] [argumentos]` | Alias para `vendor/bin/phpcbf`, com correções nos arquivos PHP locais |
| `stan [argumentos]` | Executa `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` |
| `check` | Executa PHPCBF, PHPCS e PHPStan, interrompendo na primeira falha |
| `ajuda` | Mostra uso e comandos; funciona mesmo sem Docker ou `.env` |

`iniciar` constrói a imagem se ela não existir. Após mudanças no Dockerfile, execute
`construir` e `iniciar` novamente. Repetir `iniciar` reutiliza os serviços do mesmo
projeto Compose, sem criar containers duplicados. Estar em execução não garante
que as tabelas já existam: **migrations e seeds nunca são executados pelo wrapper**.
Para preparar o banco local, siga a etapa manual de migrations abaixo.

#### Atalhos de qualidade PHP

Os atalhos seguem as ferramentas existentes do projeto (manual 10.2):
**PHPCS/PHPCBF e PHPStan**. O nome `cs-fixer` é apenas um alias de conveniência;
o pacote PHP-CS-Fixer não foi adicionado. Use opções do **PHPCBF**, não do
PHP-CS-Fixer. O argumento inicial `fix` é opcional, para compatibilidade com o modelo:

```bash
./scripts/hub.sh cs-fixer
./scripts/hub.sh cs-fixer fix app/Controllers
./scripts/hub.sh stan
./scripts/hub.sh stan app/Controllers --memory-limit=512M
./scripts/hub.sh check
```

`check` não recebe argumentos e executa a configuração completa do projeto.
**`cs-fixer` e `check` podem modificar arquivos PHP locais**; revise o diff antes
de fazer commit. No PHPCBF 4 do projeto, uma correção bem-sucedida retorna zero;
qualquer código diferente de zero interrompe a sequência, sem esconder falhas.
O wrapper preserva o código de saída de cada ferramenta. PHPUnit não faz parte
de `check`; execute a suíte separadamente conforme as instruções do projeto.

Cada etapa usa `docker compose run --rm --no-deps`, com o mesmo serviço `app`,
sem iniciar o banco, publicar portas ou exigir a aplicação em execução. Somente
o container temporário é removido; volumes persistentes são preservados.
PHPCBF/PHPCS utilizam seu UID/GID para respeitar a propriedade dos arquivos;
PHPStan usa `www-data` para acessar o cache no volume `writable`. Fora de um
terminal interativo, o wrapper desabilita a alocação de TTY automaticamente.
Nenhum desses atalhos executa migrations, seeds ou instala dependências.
Se as ferramentas estiverem ausentes, execute `construir`; se já houver um volume
`dependencias`, siga também a instrução de sincronização do Composer abaixo.

#### Escopo da execução local

O wrapper resolve os caminhos pelo diretório do script e pode ser chamado de outra
pasta. Ele usa como nome de projeto o nome da pasta raiz, como o Compose padrão,
ignorando `COMPOSE_PROJECT_NAME`, `COMPOSE_FILE`, `COMPOSE_PROFILES` e
`COMPOSE_ENV_FILES`. Não o misture com uma execução manual que utilize `-p` ou
arquivos alternativos: gerencie essa execução pelos mesmos comandos manuais.
Somente contextos Docker com socket Unix local são aceitos; conexões TCP/SSH são
recusadas. Não use o wrapper para homologação ou produção.

Se ocorrer erro:

- **Docker ausente ou indisponível:** instale/inicie o Docker Engine e confira a
  permissão do usuário no socket; o wrapper não usa `sudo` nem altera permissões.
- **Configuração incompleta:** revise a tabela abaixo e a sintaxe do `.env`.
  A validação não exibe valores de configuração nem saída bruta do Compose.
- **Porta indisponível:** confira `ports` no `compose.yaml` (padrão `127.0.0.1:8080`)
  e encerre manualmente o serviço conflitante. A URL no `.env` não altera a porta.
- **Falha ao iniciar/banco não saudável:** use `status` e `logs --sem-acompanhar`.
  Para investigar o banco, use localmente `docker compose logs --tail=100 banco`.
  Se o volume já existia, mantenha as credenciais originais; não apague os volumes.
- **Serviços ativos, mas erro de tabela inexistente no navegador:** em um banco
  novo, siga a etapa manual de migrations abaixo. O wrapper não prepara as tabelas.

Após uma falha por porta ocupada, algumas versões do Docker podem deixar o
container `app` sem conexão à rede interna. Se liberar a porta e repetir `iniciar`
não recuperar o acesso ao banco, recrie **manualmente apenas a aplicação**, na
raiz do projeto, usando a mesma configuração e preservando os volumes:

```bash
docker compose up --detach --wait --no-deps --force-recreate app
```

Faça isso somente com o banco local em execução e saudável. O container `app`
será substituído; arquivos fora dos volumes ou da pasta montada não são
preservados. Não remova os volumes nem recrie o banco para resolver esse erro.
O wrapper não executa essa recuperação automaticamente.

Os logs são os registros produzidos pela aplicação: não registre credenciais no
código nem compartilhe logs sem revisão (manual 10.6 e 11.9).

#### Manutenção do wrapper

Para adicionar uma operação, registre-a em `COMANDOS`, implemente
`comando_<nome>` e inclua-a na lista permitida de `principal`. Preserve a validação
silenciosa, a restrição ao Docker local e a ausência de operações automáticas de
banco. Não adicione outra configuração de execução. Ao alterar serviços ou portas,
atualize as mensagens e os testes correspondentes.

Verifique a sintaxe do script, sem depender de um Docker instalado:

```bash
bash -n scripts/hub.sh
```

Os testes automatizados do wrapper usam o parser real do Docker Compose, com o
acesso ao daemon simulado. Não exigem serviços em execução, não usam seu `.env`
e não criam containers ou volumes:

```bash
bash tests/scripts/hub_test.sh
```

Esses testes não substituem a execução real do fluxo abaixo: construção da
imagem, disponibilidade da aplicação, persistência e conflito de porta precisam
ser conferidos com o Docker local.

Para a validação manual em Linux, siga o fluxo acima, repita `iniciar` e confira que existe
um único container `app`; consulte os logs, execute `parar` e confirme com `status`
que os containers estão parados. Inicie novamente e confira que os volumes foram
preservados. Teste também uma porta ocupada em um ambiente local descartável,
sem encerrar processos de outros integrantes. A execução direta via Compose e a
instalação sem Docker continuam disponíveis.

Confira também as mensagens para Docker indisponível, `.env` ausente e
configurações incompletas em um ambiente local descartável, sem alterar suas
configurações de trabalho. Inclua variáveis obrigatórias ausentes, vazias ou só
com espaços, senhas de exemplo ou iguais, usuário `root`, ambiente diferente de
`development` e variáveis exportadas que substituam os valores do arquivo.
Confirme que o `.env` não é alterado e que nenhuma mensagem expõe credenciais.
Para os atalhos de qualidade, valide a correção de
um arquivo PHP fictício com `cs-fixer`, a análise com `stan` e a sequência de
`check`, incluindo a interrupção em caso de falha. Registre os resultados e
eventuais pendências no DevCore, sem incluir credenciais.

Para o phpMyAdmin, execute `phpmyadmin` duas vezes, confira que existe apenas um
container da ferramenta e abra a tela de login em `http://localhost:8082`.
Valide também o tratamento da porta ocupada em ambiente descartável e confirme
que `parar` encerra a ferramenta sem remover os volumes.

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
Para habilitar pelo wrapper:

```bash
./scripts/hub.sh phpmyadmin
```

O comando aguarda o banco ficar saudável e reutiliza os containers do mesmo
projeto ao ser repetido, sem iniciar a aplicação PHP. Caso a porta `8082` esteja
ocupada, o wrapper orienta a resolver o conflito, sem encerrar outros processos.
As verificações de Docker local e `.env` também são aplicadas.

Alternativa direta pelo Compose, usando a mesma configuração:

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

`./scripts/hub.sh parar` encerra todos os serviços, incluindo o phpMyAdmin,
preservando containers e volumes. Mudar o perfil no Compose não encerra um
container que já estava ativo.

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
