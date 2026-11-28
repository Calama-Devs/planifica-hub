# Guia de execução e uso do PlanIFica Hub

Este guia reúne os passos para preparar o ambiente local, iniciar a aplicação,
acessar as telas autenticadas e executar as verificações do projeto. Use somente
um banco local de desenvolvimento.

Para o mapa de diretórios e rotas, consulte [estrutura-inicial.md](estrutura-inicial.md).
Para o ambiente Docker no Linux, consulte também a seção correspondente do
[README](../README.md#desenvolvimento-local-com-docker-no-linux).

## Requisitos

### Execução nativa no Windows

- PHP 8.2 ou superior, com as extensões requeridas pelo CodeIgniter e `sqlite3`;
- Composer;
- MySQL local com suporte a InnoDB.

### Docker no Linux

Instale Docker Engine e o plugin Docker Compose. O ambiente do projeto fornece
PHP, Composer, MySQL e SQLite nos containers. Os comandos detalhados do wrapper
estão no README e em `scripts/hub.sh ajuda`.

## Preparar e iniciar no Windows

Execute os comandos a partir da raiz do repositório, no PowerShell.

1. Instale as dependências definidas em `composer.lock`:

   ```powershell
   composer install
   ```

2. Se ainda não existir um `.env` local, crie-o a partir do exemplo:

   ```powershell
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   ```

   Edite o `.env` local com a URL da aplicação e os dados de conexão do MySQL
   **local**. Não versione nem compartilhe esse arquivo. Este guia não precisa
   do conteúdo do `.env`.

3. Crie o banco local indicado na configuração e aplique as migrations:

   ```powershell
   php spark migrate --all
   ```

4. Inicie o servidor de desenvolvimento do CodeIgniter:

   ```powershell
   php spark serve
   ```

5. Abra `http://localhost:8080/index.php/login` (ou o endereço configurado no
   `app.baseURL`).

O setup do Shield já está versionado no projeto. Não execute novamente
`php spark shield:setup`.

## Preparar e iniciar com Docker no Linux

Na raiz do projeto, o atalho recomendado é:

```bash
cp -n .env.example .env
# Edite .env localmente e configure valores próprios de desenvolvimento.
./scripts/hub.sh verificar
./scripts/hub.sh construir
./scripts/hub.sh iniciar
docker compose exec --user www-data app php spark migrate --all
```

O wrapper não executa migrations automaticamente. O comando de migration acima
usa o banco do container local. Acesse a aplicação em `http://localhost:8080/`;
o Apache do container atende a aplicação, então não inicie `php spark serve`.

Para encerrar os serviços sem remover volumes:

```bash
./scripts/hub.sh parar
```

Consulte o README para configurar as variáveis Docker, acompanhar logs e resolver
conflitos de porta. Não apague volumes para corrigir problemas de inicialização.

## Criar a primeira conta administrativa

Depois de aplicar as migrations, crie a conta local com o comando interativo do
Shield. Substitua os exemplos por valores locais; não passe a senha como
argumento:

```powershell
php spark shield:user create -n nome-de-usuario -e email@exemplo.com -g superadmin
```

No container Linux, use:

```bash
docker compose exec app php spark shield:user create -n nome-de-usuario -e email@exemplo.com -g superadmin
```

O Shield solicitará a senha no terminal. O procedimento completo e as opções de
recuperação estão em [primeiro-administrador.md](autenticacao/primeiro-administrador.md).

## Entrar e acessar as telas

Autentique-se em `/login` com o e-mail e a senha da conta. Em uma instalação
nativa, anteponha `/index.php` aos caminhos abaixo se a URL base do servidor o
exigir.

| Tela | Caminho | Quem pode acessar | O que permite |
|---|---|---|---|
| Meu Perfil | `/perfil` | Qualquer pessoa autenticada | Atualizar o próprio nome completo |
| Atualização administrativa de conta | `/admin/contas/{id}` | Conta com `contas.editar` | Atualizar nome e solicitar troca excepcional de e-mail com confirmação no novo endereço |
| Acessos | `/admin/acessos` | Conta com `acessos.administrar` | Conceder/remover permissões diretas das contas |
| Permissões | `/admin/permissoes` | Conta com `acessos.administrar` | Consultar o catálogo de permissões |

O grupo `superadmin` recebe `contas.editar` por padrão. O grupo `admin` não
recebe essa permissão automaticamente. Um operador autorizado a administrar
acessos pode conceder `contas.editar` individualmente pela tela Acessos. A
permissão é verificada no servidor; esconder ou exibir um item no menu não é a
barreira de segurança.

A área Acessos deve consultar o endpoint JSON pelo identificador real antes de iniciar a edição. `contas.editar` é conferida no servidor. Na primeira alteração, o operador informa a própria senha; a confirmação pode ser reutilizada por até 300 segundos para o mesmo operador, operação e conta. A consulta informa `confirmacao_recente` e `confirmacao_expira_em`. Todas as alterações geram auditoria em `auditoria_edicao_contas` com IP minimizado.

A troca excepcional exige justificativa, endereço válido e único, de qualquer domínio. O sistema envia um link de uso único ao endereço novo; o login atual permanece válido até a confirmação. O link expira em 24 horas. Após a confirmação, o login passa ao endereço novo, links pendentes de recuperação e login mágico são invalidados e o endereço anterior recebe um aviso.

## Executar verificações e testes

Execute os comandos na raiz do repositório.

### Windows nativo

```powershell
.\vendor\bin\phpcs.bat
php .\vendor\bin\phpstan analyse --no-progress --memory-limit=1G
composer test
```

`composer test` executa PHPUnit sem cobertura. Para executar um conjunto
específico:

```powershell
.\vendor\bin\phpunit --no-coverage --filter ContaTest
.\vendor\bin\phpunit --no-coverage --filter PerfilTest
.\vendor\bin\phpunit --no-coverage --filter AcessoPermissoesTest
```

O PHPUnit direto pode avisar que não há driver de cobertura. Se esse aviso
interromper a execução, use `composer test` ou acrescente `--no-coverage` ao
comando PHPUnit.

Para corrigir automaticamente problemas de PSR-12, o PHPCBF pode modificar os
arquivos PHP locais; revise o diff depois:

```powershell
.\vendor\bin\phpcbf.bat --standard=PSR12 app
```

### Linux nativo

```bash
vendor/bin/phpcs
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
composer test
vendor/bin/phpunit --no-coverage --filter ContaTest
vendor/bin/phpunit --no-coverage --filter PerfilTest
```

### Docker no Linux

```bash
docker compose exec app vendor/bin/phpcs
docker compose exec app php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker compose exec app composer test
docker compose exec app vendor/bin/phpunit --no-coverage --filter ContaTest
```

Os testes automatizados usam o banco de testes configurado pelo PHPUnit; não
substitua essa configuração pelo banco de produção nem execute migrations de
teste em homologação ou produção.

## Testes manuais recomendados

Depois de iniciar a aplicação local, confira:

1. Login correto e incorreto, logout e bloqueio de páginas internas sem sessão.
2. Em Meu Perfil, altere o nome com acentos e espaços; envie também um campo
   `email` diretamente e confirme que a requisição é rejeitada, o nome não muda
   e a identidade de e-mail permanece igual.
3. Na área Acessos, teste o fluxo de solicitação, confirmação pelo endereço novo, troca efetiva do login, aviso ao endereço anterior e expiração do link.
4. Execute `.\vendor\bin\phpunit --no-coverage --filter ContaTest` e confira
   os registros da auditoria localmente com
   `SELECT * FROM auditoria_edicao_contas ORDER BY id DESC;`. Use somente o
   banco de desenvolvimento local.
5. Salve duas alterações seguidas na mesma sessão para conferir a renovação do
   token CSRF. Pelo DevTools, adicione ao formulário
   `<input type="hidden" name="teste" value="x">`, salve e confirme que o
   valor `x` permanece.
6. Salve um nome válido e confirme que nomes repetidos são aceitos; depois
   tente nome inválido e senha incorreta. Confirme que erros mantêm o formulário
   utilizável.
7. Faça chamadas diretas aos endpoints `GET` e `POST /admin/contas/{id}` com e sem `contas.editar` e confira o status e o JSON de autorização.
8. Envie propriedades extras (`groups`, `permissions`, `password` ou `active`) e confirme que a requisição é recusada e esses dados não mudam.
9. Provoque falha de auditoria no ambiente de testes e confirme que o nome não
   é persistido sem o respectivo registro de auditoria.

## Referências de implementação

- Rotas e filtros: `app/Config/Routes.php`.
- Catálogo e matriz de permissões: `app/Config/AuthGroups.php`.
- Controller administrativo: `app/Controllers/ContaController.php`.
- Regras e persistência: `app/Services/EdicaoContaService.php`.
- Confirmação recente de senha: `app/Services/ConfirmacaoOperacaoService.php`. A conferência da senha e o limite de tentativas ficam em `app/Services/ConfirmacaoAdministrativaService.php`.
- Confirmação do novo endereço: `ConfirmacaoEmailContaController`, `ConfirmacaoEmailContaService` e Views em `app/Views/conta/`.
- Campo separado de nome completo e auditoria: migrations em `app/Database/Migrations/`.
- Testes da gestão de contas: `tests/feature/ContaTest.php`.

