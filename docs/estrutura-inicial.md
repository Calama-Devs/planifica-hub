# Estrutura inicial do PlanIFica Hub

Os padrões gerais continuam em [padroes/](padroes/); para criar telas e
reutilizar componentes, consulte o [padrão de layout e dashboard](padroes/layout-e-dashboard.md).

## Diretórios e namespaces

O namespace da aplicação permanece `App\`. A fonte de verdade do mapeamento
PSR-4 é `composer.json`, que associa `App\` a `app/` e `Config\` a
`app/Config/`. O arquivo `app/Config/Autoload.php` contém a configuração
complementar de autoload do CodeIgniter; não é necessário alterá-lo.

| Diretório | Namespace ou finalidade | Situação atual |
|---|---|---|
| `app/Controllers/` | `App\Controllers` | Existente: `BaseController.php`, `Home.php`, `PerfilController.php`, `PermissaoController.php` e `RedefinirSenhaController.php` |
| `app/Models/` | `App\Models` | Existente: `AuditoriaPermissaoModel.php`; a autenticação usa as Models do Shield |
| `app/Services/` | `App\Services` | Camada obrigatória para novas funcionalidades; reaproveitar Services do domínio quando possível, conforme o [padrão de back-end](padroes/arquitetura-backend.md) |
| `app/Filters/` | `App\Filters` | Existente, sem Filters próprios; autenticação usa o Shield |
| `app/Config/` | `Config` | Configurações do CodeIgniter e do Shield |
| `app/Views/layouts/` | Layouts compartilhados | Existente: `principal.php` |
| `app/Views/partials/` | Componentes reutilizáveis | Existente: menu, cabeçalho, mensagens, paginação e rodapé |
| `app/Views/home/` | Telas do contexto inicial | Existente: `index.php` e `sobre.php` |
| `app/Views/<contexto>/` | Telas de cada contexto | Existente: `autenticacao/`, `perfil/`, `permissao/` e `redefinirsenha/`; criar novas somente com demanda aprovada |
| `public/assets/` | Arquivos públicos estáticos | Organização descrita abaixo |

Os diretórios de suporte do appstarter, como `Database/`, `Helpers/`,
`Libraries/`, `Language/`, `ThirdParty/` e as Views de erro, são preservados.
Não criar classes vazias ou diretórios preventivos. Usar `.gitkeep` apenas
quando um diretório vazio realmente precisar ser entregue.

## Responsabilidades e nomes (manual 6, 7, 7.3 e 10.3)

| Camada | Responsabilidade |
|---|---|
| Controller | Receber a requisição, validar entradas, delegar o processamento ao Service e montar a resposta |
| Service | Executar regras de negócio, acessar dados pelas Models e coordenar as escritas com a [auditoria centralizada](auditoria.md) |
| Model | Acessar e persistir dados com os recursos do CodeIgniter; Models próprias retornam arrays |
| View | Apresentar os dados recebidos, sem consultas ou decisões de acesso |
| Filter | Verificar autenticação, autorização e condições gerais das rotas no servidor |

Classes próprias usam PascalCase com sufixo de tipo, como `PerfilController`,
`UsuarioModel`, `PerfilService` e `PermissaoFilter`. Métodos e variáveis usam
camelCase em português, como `atualizarPerfil()` e `$usuarioAtual`. Views usam
pastas do contexto em minúsculas e arquivos snake_case, como
`perfil/editar.php`. As exceções impostas pelo framework e pelos pacotes
permanecem conforme o [padrão de escrita](padroes/escrita-de-codigo.md).

`app/Controllers/Home.php` é uma classe existente sem o sufixo `Controller`.
As rotas atuais utilizam seus métodos `index()` e `sobre()`. Novas classes
seguem a convenção de sufixos descrita acima.

## Autenticação e autorização existentes

- O Shield é instalado via Composer em `vendor/codeigniter4/shield/`, no
  namespace `CodeIgniter\Shield`. Seus Controllers, Models, entidades,
  Filters, Views e migrations continuam no pacote, sem cópias locais.
- `app/Config/Auth.php` configura autenticação, redirecionamentos e o mapa
  de Views. Atualmente login e cadastro usam as Views oficiais do Shield.
  `app/Config/AuthToken.php` mantém as configurações de tokens do pacote.
- `app/Config/AuthGroups.php` contém grupo padrão, grupos, permissões e
  matriz de permissões do Shield. Essas configurações não implicam a
  existência de telas administrativas.
- `app/Config/Routes.php` registra as rotas do Shield e protege `/` e `/sobre`
  com o filtro `session`. As telas de `admin/acessos` e `admin/permissoes` usam
  `session` e `permission:acessos.administrar`. Os endpoints JSON de consulta
  de grupos usam o filtro `permissao-grupos-json`, que devolve 401/403 em JSON
  e audita recusas. Os endpoints JSON `/admin/contas/{id}`
  exigem `contas.editar`, com resposta JSON do filtro e conferência repetida no
  Controller e no Service. O logout permanece exclusivamente por POST.
  `app/Config/Filters.php` e `Security.php` mantêm a proteção CSRF global
  baseada em sessão.

### Rotas atuais

| Método | Caminho | Comportamento existente |
|---|---|---|
| GET | `/` | `Home::index`, exige autenticação e apresenta o dashboard |
| GET | `/sobre` | `Home::sobre`, exige autenticação e reutiliza o layout |
| GET | `/admin/permissoes` | Catálogo de permissões protegido por `acessos.administrar` |
| GET | `/admin/permissoes/listar` | Lista grupos com busca e paginação DataTables; respostas JSON usam 401/403 e são auditadas |
| GET | `/admin/permissoes/grupos/{identificador}` | Consulta individual de grupo; exige `acessos.administrar` e registra auditoria |
| GET | `/admin/acessos` | Tela de gerenciamento de usuários protegida por `acessos.administrar` |
| GET | `/admin/acessos/listar` | Listagem de contas protegida por `acessos.administrar` |
| GET | `/admin/contas/{id}` | `ContaController::consultar`, exige `contas.editar` no Controller e carrega nome completo e e-mail pelo identificador da rota |
| POST | `/admin/contas/{id}` | `ContaController::atualizar`, exige `contas.editar` e CSRF; atualiza o nome e, para e-mail, cria solicitação pendente de confirmação no novo endereço |
| GET | `/confirmar-troca-email?token={token}` | `ConfirmacaoEmailContaController::formulario`, apresenta a confirmação sem consumir o token |
| POST | `/confirmar-troca-email` | `ConfirmacaoEmailContaController::confirmar`, exige CSRF e confirma a posse do novo endereço antes de atualizar a identidade do Shield |
| GET / POST | `/register` | Formulário e cadastro pelo Shield |
| GET / POST | `/login` | Formulário e autenticação pelo Shield |
| POST | `/logout` | Encerra a sessão pelo Shield, com CSRF; GET não é permitido |
| GET / POST | `/login/magic-link` | Fluxo de link mágico do Shield |
| GET | `/login/verify-magic-link` | Verificação do link mágico pelo Shield |
| GET | `/auth/a/show` | Exibição de ação de autenticação do Shield |
| POST | `/auth/a/handle`, `/auth/a/verify` | Processamento e verificação de ações pelo Shield |

Os identificadores, nomes e descrições dos grupos continuam vindo de
`setting('AuthGroups.groups')`, que usa `Config\AuthGroups` como padrão e
respeita sobrescritas persistidas em Settings. A tabela `grupos` complementa
essa fonte apenas com a situação (`ativo`) e timestamps; não substitui nem
reescreve os grupos, vínculos do Shield ou a matriz de permissões. A situação
ausente na tabela é tratada como ativa. Consultas não sincronizam nem gravam
grupos. `GrupoUsuarioModel` conta usuários distintos em
`auth_groups_users`, desconsiderando contas com soft delete.

Na edição administrativa, `nome_completo` é o nome de exibição alterado; o campo `username` permanece como identificador do Shield e não é modificado.

Os fluxos por e-mail dependem da configuração local descrita no
[README](../README.md).

### Edição administrativa de contas e política de auditoria

Os endpoints JSON `GET` e `POST /admin/contas/{id}` são destinados à integração pela área Acessos. A atualização exige `contas.editar`; os grupos `superadmin` e `admin` recebem essa permissão pela matriz do Shield. A confirmação da senha do operador autenticado (`senha_admin`) pode ser reutilizada por até 300 segundos, restrita ao operador, operação e conta afetada, somente para edições sem o campo `email`. A consulta inclui `confirmacao_recente` e `confirmacao_expira_em`.
- **Campos aceitos:** `nome` (2 a 255 caracteres), `senha_admin` (obrigatória para toda solicitação que inclua `email`, mesmo que a confirmação recente esteja ativa; para edição sem `email`, é obrigatória apenas sem confirmação recente) e, exclusivamente no fluxo excepcional de alteração de e-mail, `email` e `justificativa` (15 a 500 caracteres). Propriedades adicionais como `groups`, `permissions`, `active` e `password` são rejeitadas com HTTP 400 (`DADOS_CONTA_INVALIDOS`).
- **Autorização:** filtro JSON, Controller e Service conferem `contas.editar`; a senha correta não substitui a permissão. A autorização exige que o grupo mais alto do operador seja estritamente superior ao do alvo na ordem configurada em `Config\Contas`. A confirmação do novo e-mail revalida a permissão e a hierarquia do operador original. Uma permissão exclusiva para troca excepcional de e-mail pode ser avaliada futuramente.
- **Política de e-mail institucional:** somente os domínios configurados em `Config\Contas` são aceitos, com comparação exata e sem curingas. Formato e domínio são revalidados na confirmação. Se o domínio deixar de ser permitido, a solicitação é cancelada e auditada como `dominio_bloqueado`; a identidade de login e o endereço anterior permanecem inalterados, e a página apresenta uma mensagem genérica. A solicitação expira em 24 horas e só altera a identidade Shield depois da confirmação no novo endereço. Então links pendentes `magic-link-reset` e `magic-link` são invalidados e o endereço anterior recebe uma notificação. Solicitações pendentes da mesma conta são substituídas; tokens são armazenados somente como hash.
- **Auditoria transacional e minimização de dados:** todas as edições são persistidas na tabela `auditoria_edicao_contas` atomicamente com os dados da conta; falhas no registro de auditoria causam rollback imediato da operação. `created_at` registra a criação do evento e permanece imutável; `updated_at` registra a data e hora em que o resultado da solicitação de troca de e-mail muda. Em observância ao princípio da minimização de dados (LGPD), o IP do operador é armazenado em formato minimizado (IPv4 com último octeto zerado e IPv6 truncado após os 3 primeiros grupos; endereços inválidos ou vazios são gravados como nulos). A finalidade é a rastreabilidade administrativa sem coleta excessiva de dados de rede. Registros de auditoria antigos anteriores a esta política permanecem inalterados como estão.
- **Confirmação recente de senha:** uma senha válida autoriza novas edições por até 300 segundos, somente para o mesmo operador, a operação de edição de conta e a conta afetada. A confirmação pode ser reutilizada durante esse período, somente na edição sem troca de e-mail; não é armazenada senha ou hash e o logout encerra a janela com a sessão. `consultar` acrescenta `confirmacao_recente` (booleano) e `confirmacao_expira_em` (segundos restantes ou `null`). Sem confirmação válida e com senha ausente/incorreta, a atualização retorna HTTP 422 e `CONFIRMACAO_INVALIDA`.

## Convenções para perfil e administração

As localizações abaixo são convenções para funcionalidades ainda não
implementadas. Os diretórios e arquivos devem ser criados somente quando
necessários a uma demanda aprovada.

| Contexto | Localização convencional, ainda não implementada |
|---|---|
| Perfil | `app/Controllers/PerfilController.php` e `app/Views/perfil/` |
| Administração | `app/Controllers/AdministracaoController.php` e `app/Views/administracao/` |
| Regras de negócio não triviais desses contextos | `app/Services/`, reaproveitando um Service existente quando adequado |
| Persistência própria | `app/Models/`, sem duplicar as Models de autenticação do Shield |
| Permissões | Configurações do Shield em `app/Config/AuthGroups.php` e verificação no servidor pelos Filters do pacote; Filters próprios em `app/Filters/` somente se necessários |

Esses caminhos são referências de organização e não exigem classes vazias.
A edição administrativa de contas usa `app/Controllers/ContaController.php`,
`app/Services/EdicaoContaService.php` e `app/Filters/PermissaoJsonFilter.php`.
Os endpoints JSON são destinados à integração pela área Acessos.
Telas futuras devem reaproveitar `layouts/` e `partials/`, seguindo o
[padrão de layout](padroes/layout-e-dashboard.md) e o
[padrão de segurança](padroes/seguranca.md).

## Assets públicos (manual 9.3 e 9.12)

```text
public/assets/
├── css/       # Corona em modern-vertical/style.css e adaptações em custom.css
├── js/        # Comportamentos do layout e custom.js
├── vendor/    # Bundle jQuery/Bootstrap e MDI com suas fontes relativas
├── images/    # Logos IFRO utilizados pelo layout e favicon
└── fonts/     # Rubik consumida pelo CSS do Corona
```

Bibliotecas de terceiros ficam em `public/assets/vendor/`. Os demais
caminhos existentes são mantidos, incluindo as fontes MDI em
`vendor/mdi/fonts/`, relativas ao CSS de ícones. O inventário de arquivos
utilizados e as instruções de carregamento ficam no
[padrão de layout e dashboard](padroes/layout-e-dashboard.md#5-seleção-mínima-de-assets-manual-154-e-254).

### Template oficial e uso seletivo

- **Template:** BootstrapDash Corona Admin.
- **Versão identificada no pacote local:** 3.0.0, conforme seu `package.json`.
- **Variante atualmente utilizada:** `modern-vertical`.
- **Link oficial:** https://drive.google.com/file/d/1u3wd2hjeTKAV8TyZ3rcZmGpOIaLqVIGi/view?usp=sharing

Somente arquivos efetivamente utilizados e suas dependências entram no
repositório. O pacote completo, demonstrações e plugins não utilizados
ficam fora. Imagens e fontes devem ter seu uso verificado antes da inclusão;
não devem ser copiadas preventivamente por aparecerem no pacote ou em
estilos de componentes que a aplicação ainda não utiliza.

A instalação segue o README, com os assets selecionados já versionados,
sem exigir a importação do pacote completo, npm ou Gulp.

## Alcance deste mapa

Este mapa descreve o ponto de partida e as convenções locais do CodeIgniter.
Não decide entre monorepositório e multirrepositório, não define a
arquitetura definitiva dos módulos futuros nem trata de integrações externas.
