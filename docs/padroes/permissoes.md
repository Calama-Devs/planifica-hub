# Permissões (CodeIgniter Shield)

Base: Manual Calama Devs, capítulo 6 (camadas e Filters) e item 11.3 (permissões). Em caso de dúvida, o manual prevalece.

## Onde ficam e Política de Precedência

| O quê | Onde | Finalidade e Precedência |
|---|---|---|
| Catálogo base do sistema (código versionado) | `app/Config/AuthGroups.php`, propriedade `$permissions` | **Fonte primária de verdade** para permissões nativas e estáveis (`origem = 'sistema'`). Identificadores integrados são soberanos e imutáveis. |
| Persistência e catálogo completo | tabela `permissoes` | Armazena permissões de sistema e permissões customizadas (`origem = 'customizada'`) criadas pela área administrativa, com auditoria de autoria e soft deletes. |
| Carga inicial de permissões | `app/Database/Seeds/PermissoesSeeder.php` | Seeder idempotente que popula a tabela `permissoes` a partir do código versionado. |
| Catálogo ativo em runtime (Shield) | `Config\AuthGroups` (merge em memória) | Resolvido dinamicamente no construtor de `Config\AuthGroups`, mantendo o código versionado como base soberana e adicionando permissões customizadas da tabela `permissoes`. **Nunca grave `AuthGroups.permissions` via `setting()`**, evitando sobreposição indevida do arquivo versionado e prevenindo drift entre deploys. |
| Matriz de grupos | `app/Config/AuthGroups.php`, propriedade `$matrix` | Concessão explícita por grupo; permissões customizadas recém-criadas não entram em grupos automaticamente (menor privilégio). |
| Vínculos de usuário | tabelas do Shield `auth_groups_users` e `auth_permissions_users` | Associação individual ou por grupo. |

### Processo de Reconciliação e Prevenção de Inconsistências (Anti-drift)

O Shield recusa (`AuthorizationException`) qualquer permissão atribuída a um usuário que não conste em seu catálogo ativo. Para conciliar segurança, versionamento e flexibilidade administrativa:
1. **Prioridade absoluta do código:** Toda permissão definida em `AuthGroups.php` é soberana. O processo de reconciliação (`PermissaoService::sincronizarCatalogo()`) garante que permissões de código ausentes na tabela sejam cadastradas como `sistema`, sem jamais sobrepor as definições versionadas nem permitir alteração de identificadores.
2. **Merge em memória sem poluição de settings:** O catálogo ativo em runtime é mantido na própria classe `Config\AuthGroups`. Em tempo de execução, o construtor lê as permissões ativas de `permissoes` e mescla em `$permissions` sem persistir no banco via `setting()`. O `sincronizarCatalogo()` assegura a limpeza de qualquer chave residual via `setting()->forget('AuthGroups.permissions')`.
3. **Imutabilidade total e gerenciamento controlado:** Os identificadores e definições de permissão são estritamente controlados pelo sistema. A criação e a manutenção de permissões ocorrem por configurações em código (`Config\AuthGroups`), migrations e seeds, preservando permanentemente os vínculos de autorização e evitando bypass acidental ou inconsistências com regras de rotas.

## Área administrativa de permissões

- **Acesso restrito:** `/admin/permissoes` exige autenticação ativa (`session`) e a permissão administrativa `acessos.administrar` no servidor (filtro de rota do Shield).
- **Interface exclusiva de consulta:** A tela opera exclusivamente como catálogo informativo das permissões ativas no sistema, listando identificador, descrição e badge de origem (`sistema` ou `customizada`). Não há formulários ou botões de criação, edição ou exclusão manual.
- **Blindagem de rotas:** Requisições diretas a rotas de alteração manual retornam `404 Not Found`. A alteração do catálogo de permissões é restrita aos mecanismos de desenvolvimento do sistema.
- **Sincronização de catálogo:** Ao listar as permissões (`PermissaoService::listarPermissoes()`), o sistema concilia automaticamente permissões definidas em código (`AuthGroups.php`) ausentes na tabela `permissoes`, registrando-as como `origem = 'sistema'`.

## Convenção de nomes

- Formato `contexto.acao`: o contexto é o recurso, no plural (`contas`, `acessos`), e a ação é um verbo no infinitivo (`consultar`, `criar`, `administrar`).
- Português, minúsculas, sem acento; `snake_case` se uma parte tiver mais de uma palavra. O Shield converte a permissão consultada para minúsculas, então um identificador com maiúscula nunca seria reconhecido.
- Um identificador por ação. Não reaproveite uma permissão existente para outra ação.
- Descrição em português, curta, começando com verbo e dizendo o que a permissão libera.
- Identificador integrado é **estável**: não renomeie nem remova. O código e os vínculos gravados no banco dependem dele. Se precisar, abra uma issue própria com plano para atualizar `auth_permissions_users`.
- Só declare a permissão na issue que entrega a funcionalidade que a usa. Não declare permissões de módulos futuros.

## Catálogo atual

| Identificador | Descrição | Grupos |
|---|---|---|
| `contas.consultar` | Consultar a lista e os dados das contas de usuário | `superadmin`, `admin` |
| `contas.criar` | Criar novas contas de usuário | `superadmin`, `admin` |
| `contas.editar` | Editar nome de outras contas e solicitar troca excepcional de e-mail com justificativa e confirmação no novo endereço | `superadmin`, `admin` |
| `acessos.administrar` | Conceder e remover grupos e permissões das contas de usuário | `superadmin` |

- `acessos.administrar` fica num contexto separado de `contas` para que uma concessão a `contas` nunca libere, por engano, a administração de acessos.
- A matriz concede cada permissão de forma explícita, sem curinga (`contas.*`). Assim fica verificável quem tem cada permissão, e uma permissão nova só chega a um grupo por decisão registrada na entrega.
- Os grupos `developer`, `user` (grupo padrão de quem se cadastra) e `beta` não têm permissões administrativas (menor privilégio, manual 11.3).

## Como checar

### 1. Na rota, com o filtro `permission` (principal)

Proteja cada rota restrita do Hub e dos módulos integrados com os filtros do Shield, inclusive endpoints de formulário, AJAX e API. Use o autenticador apropriado (nas rotas web do Hub, `session`) e a permissão específica da ação quando exigida. No fluxo web, o filtro `permission` redireciona para `/login` sem login e, sem a permissão, para `Auth::$redirects['permission_denied']` (hoje, `/`) com a mensagem na chave `error`.

```php
// app/Config/Routes.php (exemplo)
$routes->group('', ['filter' => 'session'], static function (\CodeIgniter\Router\RouteCollection $routes): void {
    $routes->get('contas', 'ContaController::index', ['filter' => 'permission:contas.consultar']);
    $routes->post('contas', 'ContaController::salvar', ['filter' => 'permission:contas.criar']);
});
```

Com mais de uma permissão (`permission:contas.consultar,contas.criar`), basta o usuário ter **uma** delas. Não use esse formato se a operação exigir todas: verifique cada permissão requerida no back-end.

### 2. No Service, em operação sensível

Em operações sensíveis, valide novamente no Service **antes de executar a regra de negócio ou escrever dados**. Use a permissão efetiva do Shield com `can()`: o usuário pode recebê-la pela matriz do grupo ou por concessão individual, desde que ela esteja no catálogo ativo. Não substitua essa verificação por uma checagem de nome de grupo, dado enviado pelo cliente ou flag da interface. Devolva o retorno padronizado (manual 6.10.2).

```php
if (! auth()->user()?->can('acessos.administrar')) {
    return [
        'sucesso'  => false,
        'tipo'     => 'permissao',
        'codigo'   => 'ACESSOS_SEM_PERMISSAO',
        'mensagem' => 'Sem permissão para administrar acessos.',
    ];
}
```

Permissão para a ação não garante acesso a todos os registros. Quando houver escopo por usuário, vínculo ou módulo, confira-o no servidor antes de consultar, alterar, excluir ou devolver o registro. Em ações que administram acessos, confira também se o ator pode alterar o alvo e conceder ou revogar os grupos e permissões solicitados; não permita autoelevação nem concessões além do escopo autorizado.

### 3. Na View, só para exibir ou esconder

O Controller entrega o resultado pronto para apresentação. A View só usa `if` (manual 9.4). Ocultar menu, botão ou formulário não protege a operação contra requisições diretas.

```php
// Controller
return view('contas/index', ['podeCriarConta' => auth()->user()->can('contas.criar')]);
```

```php
<!-- View -->
<?php if ($podeCriarConta) : ?>
    <a href="<?= site_url('contas/cadastrar') ?>" class="btn btn-primary">Nova conta</a>
<?php endif ?>
```

### 4. Para atribuir a um usuário

Prefira atribuir pelo grupo. Use a permissão individual só como exceção justificada.

```php
$usuario->addGroup('admin');
$usuario->addPermission('contas.consultar');
```

No ambiente local, também dá para usar o comando do Shield: `php spark shield:user addgroup -n nome.usuario -g admin`.

## Como acrescentar uma permissão

Todas as permissões são gerenciadas e versionadas de forma controlada pelo sistema. Não há criação ou edição manual via interface gráfica (a tela `/admin/permissoes` é estritamente para consulta do catálogo).

1. Confirme que a issue entrega a funcionalidade que vai usar a permissão.
2. Escolha o identificador seguindo a convenção e confira no catálogo que ainda não existe permissão para a mesma ação.
3. Acrescente em `$permissions` (`app/Config/AuthGroups.php`), com descrição em português.
4. Conceda aos grupos em `$matrix`, explicitamente e pelo menor privilégio.
5. Execute o seeder (`php spark db:seed PermissoesSeeder`) para atualizar a tabela local (a listagem em `/admin/permissoes` também reconcilia automaticamente permissões de código ausentes na base).
6. Proteja a rota com `permission:` e repita a checagem no Service quando a operação for sensível.
7. Teste (automatizado e manual), inclusive acesso direto negado, conforme o [roteiro de testes](validacao-e-testes.md), e atualize a tabela deste documento.
8. Na descrição da entrega, marque "Altera permissões ou regras de acesso" e informe quais grupos receberam a permissão.
