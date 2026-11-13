## Inclusão Progressiva de Itens no Menu Lateral

Este documento descreve a arquitetura do menu lateral do **Planifica Hub** e o passo a passo que deve ser seguido em futuras issues ao adicionar novas páginas e módulos ao sistema.

## 1. Visão Geral da Arquitetura

A exibição dos itens do menu lateral é desacoplada do HTML e controlada por permissões efetivas do **CodeIgniter Shield** (`$usuario->can()`), dividida em 4 camadas:

1. **Catálogo Estático (`app/Config/Menu.php`):** Centraliza a lista de todos os itens do menu na propriedade `$itens`, definindo seção (`categoria`), rótulo (`titulo`), alias de rota (`rota`), padrão de URL ativa (`padraoAtivo`), ícone (`icone`) e permissão exigida (`permissao`).
2. **Serviço de Filtragem (`app/Services/MenuService.php`):** Acessível via `service('menu')`, valida a estrutura de cada item, filtra apenas os atalhos que o usuário autenticado possui permissão para ver, resolve a URL via `url_to()` (com fallback seguro para `site_url()` em caso de `RouterException`) e deduplica cabeçalhos de categorias consecutivas.
3. **Injeção Global (`app/Controllers/BaseController.php`):** No método `initController()`, injeta automaticamente a variável `$itensMenu` no serviço de renderização (`service('renderer')->setVar('itensMenu', service('menu')->itensVisiveis())`).
4. **Renderização Limpa (`app/Views/partials/menu.php` / `app/Views/parciais/menu.php`):** A View partial não contém lógica de permissão; ela apenas percorre `$itensMenu` com um único `foreach`, aplicando escape contextual (`esc()`) e exibindo os cabeçalhos de seção (`nav-category`) e links (`menu-items`).

> **Importante (Segurança no Back-end):** Ocultar um item no menu lateral é apenas conveniência visual de interface (UX) e **não substitui** o controle de acesso no servidor. Toda rota restrita deve obrigatoriamente estar protegida pelo filtro `permission:contexto.acao` em `app/Config/Routes.php`.

## 2. Estrutura de um item em `app/Config/Menu.php`

| Chave | Tipo | Obrigatório | Descrição |
| :--- | :--- | :---: | :--- |
| `categoria` | `string\|null` | Não | Cabeçalho de seção exibido imediatamente antes do item (ex.: `'Administração'`). |
| `titulo` | `string` | Sim | Rótulo visível do atalho no menu lateral. |
| `rota` | `string` | Sim | Nome da rota (`as`) declarada em `app/Config/Routes.php` ou caminho relativo usado em `site_url()`. |
| `padraoAtivo` | `string` | Sim | Padrão de URI avaliado por `url_is()` para aplicar a classe `active` na página atual (ex.: `'contas*'`). |
| `icone` | `string` | Sim | Classes CSS do ícone Material Design Icons (ex.: `'mdi mdi-account-multiple text-info'`). |
| `permissao` | `string\|list<string>\|null` | Não | Permissão do Shield exigida para exibir o item (ex.: `'acessos.administrar'`). Se for um array, exibe o item caso o usuário possua **ao menos uma** das permissões listadas. Use `null` (ou omita a chave) para itens visíveis a qualquer usuário autenticado. |

*Nota: Itens cadastrados sem os campos obrigatórios (`titulo`, `rota`, `padraoAtivo` ou `icone`) são ignorados pelo `MenuService` e geram um aviso em `writable/logs/`.*

## 3. Passo a Passo: Como Adicionar um Novo Item ao Menu:

Sempre que uma nova tela for criada em futuras issues, siga os **4 passos** abaixo sem alterar a View do menu nem o `MenuService`:

### Passo 1: Cadastrar a permissão
 Caso a página seja restrita, Abra `app/Config/AuthGroups.php`e confirme que a permissão está declarada em `$permissions` e vinculada aos respectivos grupos em `$matrix`.

```php
public array $permissions = [
    // ...
    'contas.consultar' => 'Permite consultar a listagem de contas do sistema',
];
```

### Passo 2: Rota nomeada e protegida: 
Dentro do grupo protegido pelo filtro `session` em `app/Config/Routes.php`, registre a rota com alias (`as`) e com o filtro `permission:` correspondente:
   ```php
   $routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    // ... outras rotas ...

    $routes->group('admin/contas', ['filter' => 'permission:contas.consultar'], static function (RouteCollection $routes): void {$routes->get('/', 'ContaController::index', ['as' => 'contas.index']);
    });
});
```

### Passo 3: Adicionar o item ao catálogo
Abra app/Config/Menu.php e adicione o novo item ao array $itens, utilizando em 'rota' exatamente o mesmo alias ('as') declarado no Routes.php:

```
    [
        'categoria'   => 'Administração',
        'titulo'      => 'Contas',
        'rota'        => 'contas.index',
        'padraoAtivo' => 'admin/contas*',
        'icone'       => 'mdi mdi-account-multiple text-info',
        'permissao'   => 'contas.consultar',
    ],
```

### Agrupamento de Categorias: 
Para que vários itens fiquem sob o mesmo cabeçalho de seção (por exemplo, 'Administração'), posicione-os em sequência no array $itens com o mesmo valor em 'categoria'. O MenuService exibirá o cabeçalho apenas no primeiro item visível para aquele usuário.*

> **Verificação:** Ao adicionar novos atalhos restritos, inclua a cobertura correspondente em `tests/feature/MenuPermissoesTest.php` garantindo que contas sem permissão não visualizem o item no menu e continuem bloqueadas no acesso direto à rota.
