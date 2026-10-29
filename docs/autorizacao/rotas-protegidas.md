# Rotas protegidas (autenticação e autorização)

Base: Manual Calama Devs, capítulo 6 (Filters), itens 11.2 e 11.3 (autenticação e permissões), item 11.10 (revisão de segurança) e item 16.1 (testes de permissão). Em caso de dúvida, o manual prevalece.

Este guia mostra **como proteger uma rota** e **como provar que ela está protegida**. O catálogo de permissões, a convenção de nomes e o passo a passo para acrescentar uma permissão ficam em `docs/padroes/permissoes.md` e não se repetem aqui.

## Regra de ouro

Quem decide se a pessoa pode acessar é o **servidor**. Esconder menu ou botão não protege nada (regra 4 de `seguranca.md`): qualquer pessoa pode digitar a URL ou enviar a requisição direto. Por isso a proteção tem camadas, e cada uma é provada pelo teste de URL direta.

| Camada | O que faz | Protege? |
|---|---|---|
| Filter na rota (`app/Config/Routes.php`) | Barra a requisição antes de ela chegar ao Controller | Sim, é a barreira principal |
| Service | Repete a checagem em operação sensível e devolve `tipo => permissao` | Sim, é a segunda barreira |
| View | Mostra ou esconde botão e menu | Não, é só conveniência |

## Como a URL chega ao Controller

O projeto usa **rotas explícitas**: só responde a URL declarada em `app/Config/Routes.php`.

- `Config\Routing::$autoRoute` é `false`. Um Controller sem rota declarada não é alcançável pela URL (404), mesmo com método `public`.
- `Config\Feature::$autoRoutesImproved` é `true`. Por isso, **trocar `$autoRoute` para `true` liga o Auto Routing (Improved)**: a URL passa a virar Controller e método por convenção (método `public` com o verbo HTTP no nome, como `getLista()` ou `postSalvar()`), e esses métodos não passam pelos filtros de acesso declarados nas rotas, porque não têm rota.
- **Não ligue o auto routing.** Qualquer mudança nessas duas configurações é uma issue própria e passa por revisão de segurança (manual 11.10).
- Métodos de apoio do Controller são `protected` ou `private`. Só o método de ação, que tem rota declarada, é `public`.
- Filtro por padrão de URI em `Config\Filters::$filters` (vazio no projeto) não é usado para autorização. O CodeIgniter avisa que um Controller pode ser alcançado por URLs diferentes das previstas, e um padrão de URI pode deixar essa brecha. O filtro vai **na rota**.
- O framework também aceita filtros por atributo no Controller (como `#[Filter]`; a opção `Config\Routing::$useControllerAttributes` está ligada). Este guia usa o filtro declarado na rota, onde todas as proteções ficam à vista em um só arquivo.

## Estado atual das rotas

A coluna "Before Filters" de `php spark routes` mostra os filtros efetivos de cada rota. Resumo das rotas existentes:

| Rotas | Filtros | Quem acessa |
|---|---|---|
| `GET /` (nome `dashboard`) e `GET sobre` | `csrf` (global) e `session` (grupo em `Routes.php`) | Quem fez login |
| `GET` e `POST` de `login`, `register` e `login/magic-link`; `GET login/verify-magic-link`; `auth/a/*` (rotas do Shield) | `csrf` (global) | Qualquer pessoa: são as portas de entrada |
| `POST logout` | `csrf` (global) | Quem está logado, para encerrar a própria sessão |

O logout só existe como POST com token: `GET /logout` devolve 404 (cenário 7 do teste de URL direta).

## Qual filtro usar

Os filtros `session`, `group` e `permission` são registrados pelo Shield; não é preciso declará-los em `app/Config/Filters.php`.

| Situação | Filtro na rota | Sem acesso |
|---|---|---|
| Página pública (login, cadastro) | nenhum | não se aplica |
| Página para quem fez login | `session` | vai para `/login` |
| Área ou ação administrativa | `permission:contexto.acao` | sem login, vai para `/login`; sem a permissão, vai para `/` com a mensagem em `error` |
| Área inteira de um grupo (exceção justificada) | `group:nome` | igual ao anterior |

O que cada filtro faz (Shield 1.4 e CodeIgniter 4.7):

- **Prefira `permission:`.** A permissão descreve a ação (`contas.consultar`) e continua valendo se um grupo mudar de nome ou de papel. `group:` amarra a rota a um papel: use só quando a área inteira é daquele papel e a issue justificar.
- **Sempre informe o argumento.** `['filter' => 'permission']`, sem o valor, não protege nada: o Shield deixa todo mundo passar.
- **Erro de digitação nega para todos.** Uma permissão que não existe (`permission:contas.consultr`) não gera erro: o Shield apenas nega para qualquer pessoa, até para o `superadmin`. Por isso o teste com usuário autorizado é obrigatório.
- **Vários valores no mesmo filtro liberam quem tem um deles.** `permission:contas.consultar,contas.criar` libera quem tem pelo menos uma das duas. Para exigir **as duas**, empilhe filtros: `['filter' => ['permission:contas.consultar', 'permission:contas.criar']]`. Quem tem só uma é barrado.
- **Sem login**, os três filtros redirecionam para `/login` e guardam a URL pedida por 5 minutos; depois do login, o Shield leva a pessoa de volta a ela. O `session` também barra conta banida ou inativa, por isso ele fica no grupo das rotas autenticadas.
- **Sem permissão**, o destino é `Auth::$redirects['permission_denied']` (`group_denied`, para `group:`; no projeto, os dois apontam para `/`), com a mensagem na chave `error`. O layout principal exibe essa mensagem pelo partial `mensagens`.
- **O `csrf` global roda antes dos filtros da rota.** Um POST sem token é recusado com `SecurityException` antes da checagem de permissão; por isso os testes de POST enviam o token.

## Como declarar a proteção em `Routes.php`

As rotas autenticadas ficam em um grupo com o filtro `session`. Uma rota administrativa entra **nesse mesmo grupo**, com o filtro de permissão na própria rota. O CodeIgniter 4.7 junta a lista de filtros do grupo com a da rota, então a rota passa por `session` e por `permission:`. Cada ação tem a sua permissão (consultar e criar são ações diferentes; menor privilégio, manual 11.3):

```php
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => 'dashboard']);
    $routes->get('sobre', 'Home::sobre', ['as' => 'sobre']);

    // Exemplo: rotas da tela de contas
    $routes->get('contas', 'ContaController::index', ['as' => 'contas', 'filter' => 'permission:contas.consultar']);
    $routes->post('contas', 'ContaController::salvar', ['filter' => 'permission:contas.criar']);
});
```

Regras de declaração:

- Declare o `permission:` em cada rota. Os filtros do grupo e os da rota **somam**, inclusive o mesmo filtro com argumentos diferentes: a pessoa precisa passar por todos. Por isso não ponha no grupo uma permissão que nem toda rota dele exige.
- Dê nome (`as`) às rotas usadas por View ou redirecionamento e use `url_to('contas')` em vez de escrever a URL.
- Ação que altera dados é POST (ou PUT, PATCH, DELETE), com `csrf_field()` no formulário e o token no AJAX. Nunca GET: é por isso que `GET /logout` não existe e devolve 404.

## Repetir a checagem no Service

O filtro barra a URL. Quando a operação é sensível (altera dados, concede acesso, exporta dados pessoais), o Service **repete** a checagem e devolve o retorno padronizado (manual 6.10.2). O Controller só traduz o resultado, e a View recebe apenas um booleano para mostrar ou esconder o botão. Os exemplos do Service e da View estão em `docs/padroes/permissoes.md`, seção "Como checar", itens 2 e 3.

```php
// Controller: traduz o retorno do Service
$resultado = service('ContaService')->criarConta($this->validator->getValidated());

if (! $resultado['sucesso']) {
    return redirect()->back()->withInput()->with('error', $resultado['mensagem']);
}

return redirect()->to(url_to('contas'))->with('success', $resultado['mensagem']);
```

## O que não fazer

- Proteger só escondendo o botão ou o item de menu.
- Alterar dados por GET.
- Devolver um registro pela `id` da URL sem conferir se ele pertence ao escopo de quem pediu (manual 11.4).
- Deixar `public` um método de Controller que não é ação.
- Usar permissão fora do catálogo (`AuthGroups::$permissions`) ou criar tabela paralela de perfis e permissões.
- Pular o teste de URL direta porque "o botão já está escondido".

## Teste de URL direta

Teste de URL direta é acessar a página ou a ação **sem passar pela interface** (colando a URL ou enviando a requisição), para provar que a barreira está no servidor. É o teste de permissões do manual 16.1 e entra no checklist de entrega do manual (16.2). Toda rota protegida precisa passar por estes cenários:

| # | Quem acessa | Resultado esperado |
|---|---|---|
| 1 | Sem login | Redireciona para `/login`; o conteúdo não aparece |
| 2 | Logado, sem a permissão (grupo `user`, o padrão de quem se cadastra) | Redireciona para `/` com a mensagem em `error`; o conteúdo não aparece |
| 3 | Logado, com a permissão (ex.: grupo `admin`) | Responde 200 com o conteúdo |
| 4 | Ação (POST) sem a permissão, com token CSRF válido | Redireciona; a ação não é executada e nada é gravado |
| 5 | Ação (POST) com a permissão | Executa (prova que o cenário 4 foi barrado pela permissão e não por outro motivo) |
| 6 | URL sem rota declarada (ex.: `/home/sobre`), mesmo logado | 404 |
| 7 | Verbo não declarado (ex.: `POST /sobre`, `GET /logout`) | 404 |
| 8 | `id` de outro usuário na URL, quando a rota tem `id` | Não devolve o registro: nega ou 404 |

### Teste automatizado

Os modelos do projeto são `tests/feature/HomeTest.php` (acesso sem login) e `tests/feature/PermissoesAdministrativasTest.php` (`withRoutes()`, `actingAs()` e `permission:`). O exemplo cobre os cenários 1 a 4. O `withRoutes()` declara uma rota de teste com o mesmo filtro da rota real; ao testar uma rota que já existe, apague o `withRoutes()` e use a URL real. O cenário 8 depende da regra de cada tela e entra no teste dela.

No POST, gere o token antes de `actingAs()` e passe `withSession($_SESSION)`, como em `tests/feature/AutenticacaoTest.php`. Sem o token, o `csrf` recusa antes da permissão e o teste não prova nada sobre autorização.

```php
<?php

namespace Tests\Feature;

use ArrayObject;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ContasAcessoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
    ];

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
    }

    public function testRedirecionaParaLoginQuandoNaoAutenticado(): void
    {
        $resposta = $this->comRotasDeContas()->get('contas');

        $resposta->assertRedirectTo(site_url('login'));
    }

    public function testBloqueiaUsuarioSemPermissao(): void
    {
        $usuario = $this->criarUsuario('user');

        $resposta = $this->comRotasDeContas()->actingAs($usuario)->get('contas');

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error');
    }

    public function testLiberaUsuarioComPermissao(): void
    {
        $usuario = $this->criarUsuario('admin');

        $resposta = $this->comRotasDeContas()->actingAs($usuario)->get('contas');

        $resposta->assertOK();
        $resposta->assertSee('Lista de contas');
    }

    public function testNaoExecutaAcaoSemPermissao(): void
    {
        $execucoes = new ArrayObject();
        $usuario   = $this->criarUsuario('user');
        $campos    = [csrf_token() => csrf_hash()];

        $resposta = $this->comRotasDeContas($execucoes)
            ->actingAs($usuario)
            ->withSession($_SESSION)
            ->post('contas', $campos);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->assertCount(0, $execucoes);
    }

    private function comRotasDeContas(ArrayObject $execucoes = new ArrayObject()): self
    {
        return $this->withRoutes([
            ['GET', 'contas', static fn (): string => 'Lista de contas', ['filter' => 'permission:contas.consultar']],
            [
                'POST',
                'contas',
                static function () use ($execucoes): string {
                    $execucoes[] = 'criou';

                    return 'Conta criada';
                },
                ['filter' => 'permission:contas.criar'],
            ],
        ]);
    }

    private function criarUsuario(string $grupo): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.acesso']));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
```

Os cenários 5 a 7 seguem o mesmo molde:

- **5:** igual ao 4, com `criarUsuario('admin')`; espere `assertOK()` e `assertCount(1, $execucoes)`.
- **6:** `$this->expectException(PageNotFoundException::class);` seguido de `$this->actingAs($usuario)->get('home/sobre');` (importe `CodeIgniter\Exceptions\PageNotFoundException`).
- **7:** igual ao 6, com `$this->actingAs($usuario)->post('sobre');`.

### Roteiro manual (navegador)

1. Suba o MySQL do XAMPP e o servidor (`php spark serve`) e abra `http://localhost:8080/index.php/login`.
2. Prepare duas contas com dados fictícios (manual 11.9): cadastre as duas em `/register` (elas caem no grupo `user`) e promova a segunda com `php spark shield:user addgroup -n <username> -g admin`.
3. Em **janela anônima**, cole a URL da rota (por exemplo, `/sobre`): deve abrir `/login`. Entre: o Shield leva a pessoa de volta à página pedida.
4. Em uma rota com `permission:`, entre com a conta **sem** permissão e cole a URL: deve cair em `/` com a mensagem de erro, sem mostrar o conteúdo.
5. Entre com a conta **com** permissão e cole a mesma URL: a página abre.
6. Repita para a ação (formulário ou requisição direta) e para as URLs alternativas dos cenários 4 a 8.
7. Descreva na entrega quais contas e quais URLs foram testadas.

## Referências

- `docs/padroes/permissoes.md`: catálogo de permissões, convenção de nomes e como acrescentar uma permissão.
- `docs/padroes/seguranca.md` (regra 4; manual 11.2, 11.3 e 11.10), `docs/padroes/arquitetura-backend.md` (Filters) e `docs/padroes/validacao-e-testes.md` (testes de permissão).
- CodeIgniter 4, Auto Routing (Improved): https://codeigniter.com/user_guide/incoming/auto_routing_improved.html
- CodeIgniter 4, Controller Filters: https://codeigniter.com/user_guide/incoming/filters.html
- CodeIgniter Shield, autorização: https://shield.codeigniter.com/latest/quick_start_guide/using_authorization/
- CodeIgniter Shield, filtros de rota (`session`, `group` e `permission`): https://shield.codeigniter.com/latest/references/controller_filters/
