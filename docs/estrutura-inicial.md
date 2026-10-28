# Estrutura inicial do PlanIFica Hub

Os padrões gerais continuam em [padroes/](padroes/); para criar telas e
reutilizar componentes, consulte o [padrão de layout e dashboard](padroes/layout-e-dashboard.md).

## Diretórios e namespaces

O namespace da aplicação permanece `App\`, definido em
`app/Config/Constants.php`. O autoload PSR-4 de `composer.json` mapeia `App\`
para `app/` e `Config\` para `app/Config/`. O CodeIgniter mantém esse
mapeamento em `app/Config/Autoload.php`; não é necessário alterá-lo.

| Diretório | Namespace ou finalidade | Situação atual |
|---|---|---|
| `app/Controllers/` | `App\Controllers` | Existente: `BaseController.php` e `Home.php` |
| `app/Models/` | `App\Models` | Existente, sem Models próprias |
| `app/Services/` | `App\Services` | Criar somente quando houver regra de negócio que justifique |
| `app/Filters/` | `App\Filters` | Existente, sem Filters próprios; autenticação usa o Shield |
| `app/Config/` | `Config` | Configurações do CodeIgniter e do Shield |
| `app/Views/layouts/` | Layouts compartilhados | Existente: `principal.php` |
| `app/Views/partials/` | Componentes reutilizáveis | Existente: menu, cabeçalho, mensagens e rodapé |
| `app/Views/home/` | Telas do contexto inicial | Existente: `index.php` e `sobre.php` |
| `app/Views/<contexto>/` | Telas de cada contexto | Criar somente com demanda aprovada |
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
  com o filtro `session`. O logout permanece exclusivamente por POST.
  `app/Config/Filters.php` e `Security.php` mantêm a proteção CSRF global
  baseada em sessão.

### Rotas atuais

| Método | Caminho | Comportamento existente |
|---|---|---|
| GET | `/` | `Home::index`, exige autenticação e apresenta o dashboard |
| GET | `/sobre` | `Home::sobre`, exige autenticação e reutiliza o layout |
| GET / POST | `/register` | Formulário e cadastro pelo Shield |
| GET / POST | `/login` | Formulário e autenticação pelo Shield |
| POST | `/logout` | Encerra a sessão pelo Shield, com CSRF; GET não é permitido |
| GET / POST | `/login/magic-link` | Fluxo de link mágico do Shield |
| GET | `/login/verify-magic-link` | Verificação do link mágico pelo Shield |
| GET | `/auth/a/show` | Exibição de ação de autenticação do Shield |
| POST | `/auth/a/handle`, `/auth/a/verify` | Processamento e verificação de ações pelo Shield |

Os fluxos por e-mail dependem da configuração local descrita no
[README](../README.md).

## Convenções para perfil e administração

As localizações abaixo são convenções para funcionalidades ainda não
implementadas. Os diretórios e arquivos devem ser criados somente quando
necessários a uma demanda aprovada.

| Contexto | Localização convencional, ainda não implementada |
|---|---|
| Perfil | `app/Controllers/PerfilController.php` e `app/Views/perfil/` |
| Administração | `app/Controllers/AdministracaoController.php` e `app/Views/administracao/` |
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

O diretório `/home/geinfo/Downloads/corona` é apenas uma fonte local de
consulta, não um requisito de instalação. A instalação segue o README, com
os assets selecionados já versionados, sem exigir a importação do pacote
completo, npm ou Gulp.

## Alcance deste mapa

Este mapa descreve o ponto de partida e as convenções locais do CodeIgniter.
Não decide entre monorepositório e multirrepositório, não define a
arquitetura definitiva dos módulos futuros nem trata de integrações externas.
