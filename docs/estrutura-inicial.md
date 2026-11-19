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
| `app/Controllers/` | `App\Controllers` | Existente: `BaseController.php`, `Home.php` e `AcessoController.php` |
| `app/Models/` | `App\Models` | Existente: `AuditoriaPermissaoModel.php`; a autenticação usa as Models do Shield |
| `app/Services/` | `App\Services` | Existente: `PermissaoDiretaService.php`; criar novos somente quando houver regra de negócio que justifique |
| `app/Filters/` | `App\Filters` | Existente, sem Filters próprios; autenticação usa o Shield |
| `app/Config/` | `Config` | Configurações do CodeIgniter e do Shield |
| `app/Views/layouts/` | Layouts compartilhados | Existente: `principal.php` |
| `app/Views/partials/` | Componentes reutilizáveis | Existente: menu, cabeçalho, mensagens, paginação e rodapé |
| `app/Views/home/` | Telas do contexto inicial | Existente: `index.php` e `sobre.php` |
| `app/Views/<contexto>/` | Telas de cada contexto | Existente: `acesso/`; criar novas somente com demanda aprovada |
| `public/assets/` | Arquivos públicos estáticos | Organização descrita abaixo |

Os diretórios de suporte do appstarter, como `Database/`, `Helpers/`,
`Libraries/`, `Language/`, `ThirdParty/` e as Views de erro, são preservados.
Não criar classes vazias ou diretórios preventivos. Usar `.gitkeep` apenas
quando um diretório vazio realmente precisar ser entregue.

## Responsabilidades e nomes (manual 6, 7, 7.3 e 10.3)

| Camada | Responsabilidade |
|---|---|
| Controller | Receber a requisição, validar entradas e coordenar a resposta; delegar regras não triviais ao Service |
| Service | Concentrar regras de negócio, acessando dados pelas Models; criar somente quando necessário |
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
  com o filtro `session`. As rotas de `admin/acessos` ficam no mesmo grupo e
  somam o filtro `permission:acessos.administrar`. A tela `/admin/contas` e
  suas operações usam `permission:contas.editar`. O logout permanece
  exclusivamente por POST.
  `app/Config/Filters.php` e `Security.php` mantêm a proteção CSRF global
  baseada em sessão.

### Rotas atuais

| Método | Caminho | Comportamento existente |
|---|---|---|
| GET | `/` | `Home::index`, exige autenticação e apresenta o dashboard |
| GET | `/sobre` | `Home::sobre`, exige autenticação e reutiliza o layout |
| GET | `/admin/acessos` | `AcessoController::index`, exige a permissão `acessos.administrar` e lista as contas |
| GET | `/admin/acessos/{id}/permissoes` | `AcessoController::permissoes`, exige `acessos.administrar` e mostra as permissões da conta em caixas de seleção, com o que ela herda dos grupos e o acesso efetivo |
| POST | `/admin/acessos/{id}/permissoes` | `AcessoController::salvar`, exige `acessos.administrar` e CSRF, e concede ou revoga só permissões diretas, conforme as caixas marcadas |
| GET | `/admin/contas` | `ContaController::index`, exige `contas.editar` e lista as contas para manutenção do nome completo |
| GET | `/admin/contas/{id}` | `ContaController::consultar`, exige `contas.editar` e carrega nome completo e e-mail da conta identificada pela rota |
| POST | `/admin/contas/{id}` | `ContaController::atualizar`, exige `contas.editar` e CSRF; atualiza `nome_completo` e, em fluxo excepcional, a identidade de e-mail após confirmação de senha do operador e audita na mesma transação |
| GET / POST | `/register` | Formulário e cadastro pelo Shield |
| GET / POST | `/login` | Formulário e autenticação pelo Shield |
| POST | `/logout` | Encerra a sessão pelo Shield, com CSRF; GET não é permitido |
| GET / POST | `/login/magic-link` | Fluxo de link mágico do Shield |
| GET | `/login/verify-magic-link` | Verificação do link mágico pelo Shield |
| GET | `/auth/a/show` | Exibição de ação de autenticação do Shield |
| POST | `/auth/a/handle`, `/auth/a/verify` | Processamento e verificação de ações pelo Shield |

Na administração de contas, `nome_completo` é o nome de exibição editado por
essa tela. O campo `username` continua sendo o identificador do Shield e não é
alterado pela edição administrativa.

Os fluxos por e-mail dependem da configuração local descrita no
[README](../README.md).

### Edição administrativa de contas e política de auditoria

A rota `POST /admin/contas/{id}` permite que operadores com a permissão `contas.editar` atualizem dados cadastrais de outras contas mediante confirmação de sua própria senha (`senha_admin`):
- **Campos aceitos:** `nome` (2 a 255 caracteres), `senha_admin` (senha do operador autenticado, obrigatória) e, exclusivamente no fluxo excepcional de alteração de e-mail, `email` e `justificativa` (15 a 500 caracteres). Qualquer propriedade adicional no corpo da requisição (como `groups`, `permissions`, `active`, `password`) é rejeitada com HTTP 400 (`DADOS_CONTA_INVALIDOS`).
- **Permissão e confirmação:** a operação exige a permissão `contas.editar`, validada no filtro de rota `permission:contas.editar` (sem a permissão, o filtro de rota redireciona; o serviço mantém verificação própria [403, `CONTA_SEM_PERMISSAO`] como defesa em profundidade). A confirmação de identidade do operador é intermediada pelo `ConfirmacaoOperacaoService`.
- **Política de domínio institucional:** no fluxo excepcional de troca de e-mail, são aceitos estritamente os domínios institucionais configurados em `Config\Contas` (`ifro.edu.br` e `estudante.ifro.edu.br`, comparados exatamente e em minúsculas após o caractere `@`). Tentativas com domínios externos ou subdomínios não permitidos retornam HTTP 422 com o código `EMAIL_DOMINIO_NAO_PERMITIDO`. Outros códigos de erro incluem `EMAIL_INVALIDO`, `EMAIL_DUPLICADO`, `JUSTIFICATIVA_EMAIL_INVALIDA`, `CONTA_NOME_INVALIDO`, `CONTA_SEM_ALTERACAO` e `IDENTIDADE_EMAIL_AUSENTE` (HTTP 422 quando se tenta trocar o e-mail de conta sem identidade `email_password`; a operação não cria identidade e não altera o nome). Contas sem identidade podem ter somente o nome editado normalmente. Contas preexistentes com domínio externo mantêm seus e-mails e podem ter seu nome editado normalmente sem restrição retroativa.
- **Auditoria transacional e minimização de dados:** todas as edições são persistidas na tabela `auditoria_edicao_contas` atomicamente com os dados da conta; falhas no registro de auditoria causam rollback imediato da operação. Em observância ao princípio da minimização de dados (LGPD), o IP do operador é armazenado em formato minimizado (IPv4 com último octeto zerado e IPv6 truncado após os 3 primeiros grupos; endereços inválidos ou vazios são gravados como nulos). A finalidade é a rastreabilidade administrativa sem coleta excessiva de dados de rede. Registros de auditoria antigos anteriores a esta política permanecem inalterados como estão.

## Convenções para perfil e administração

As localizações abaixo são convenções para funcionalidades ainda não
implementadas. Os diretórios e arquivos devem ser criados somente quando
necessários a uma demanda aprovada.

| Contexto | Localização convencional, ainda não implementada |
|---|---|
| Perfil | `app/Controllers/PerfilController.php` e `app/Views/perfil/` |
| Administração de contas | `app/Controllers/ContaController.php` e `app/Views/conta/` |
| Administração geral | `app/Controllers/AdministracaoController.php` e `app/Views/administracao/` |
| Regras não triviais desses contextos | `app/Services/`, somente conforme a necessidade real |
| Persistência própria | `app/Models/`, sem duplicar as Models de autenticação do Shield |
| Permissões | Configurações do Shield em `app/Config/AuthGroups.php` e verificação no servidor pelos Filters do pacote; Filters próprios em `app/Filters/` somente se necessários |

Esses caminhos não aprovam funcionalidades nem exigem classes vazias.
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
