# Permissões (CodeIgniter Shield)

Base: Manual Calama Devs, capítulo 6 (camadas e Filters) e item 11.3 (permissões). Em caso de dúvida, o manual prevalece.

## Onde ficam

| O quê | Onde |
|---|---|
| Catálogo de permissões (identificador e descrição) | `app/Config/AuthGroups.php`, propriedade `$permissions` |
| Permissões de cada grupo | `app/Config/AuthGroups.php`, propriedade `$matrix` |
| Vínculo de usuário com grupo ou permissão individual | tabelas do Shield `auth_groups_users` e `auth_permissions_users` |

O Shield recusa (`AuthorizationException`) qualquer permissão atribuída a um usuário que não esteja no catálogo. Catálogo e matriz são alterados **só no arquivo de configuração**, que é versionado. Não grave `AuthGroups.permissions` ou `AuthGroups.matrix` pelo `setting()`: o valor salvo no banco passa a prevalecer sobre o arquivo.

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
| `acessos.administrar` | Conceder e remover grupos e permissões das contas de usuário | `superadmin` |

- `acessos.administrar` fica num contexto separado de `contas` para que uma concessão a `contas` nunca libere, por engano, a administração de acessos.
- A matriz concede cada permissão de forma explícita, sem curinga (`contas.*`). Assim fica verificável quem tem cada permissão, e uma permissão nova só chega a um grupo por decisão registrada na entrega.
- Os grupos `developer`, `user` (grupo padrão de quem se cadastra) e `beta` não têm permissões administrativas (menor privilégio, manual 11.3).

## Como checar

### 1. Na rota, com o filtro `permission` (principal)

O filtro é registrado pelo Shield. Sem login, redireciona para `/login`. Sem a permissão, redireciona para `Auth::$redirects['permission_denied']` (hoje, `/`) com a mensagem na chave `error`.

```php
// app/Config/Routes.php (exemplo para a issue que criar a tela de contas)
$routes->get('contas', 'ContaController::index', ['filter' => 'permission:contas.consultar']);
$routes->post('contas', 'ContaController::salvar', ['filter' => 'permission:contas.criar']);
```

Com mais de uma permissão (`permission:contas.consultar,contas.criar`), basta o usuário ter **uma** delas.

### 2. No Service, em operação sensível

Esconder o botão não protege nada: repita a checagem no back-end (regra 4 de `seguranca.md`) e devolva o retorno padronizado (manual 6.10.2).

```php
if (! auth()->user()->can('acessos.administrar')) {
    return [
        'sucesso'  => false,
        'tipo'     => 'permissao',
        'codigo'   => 'ACESSOS_SEM_PERMISSAO',
        'mensagem' => 'Sem permissão para administrar acessos.',
    ];
}
```

### 3. Na View, só para exibir ou esconder

O Controller decide e entrega o resultado pronto. A View só usa `if` (manual 9.4).

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

## Como acrescentar uma permissão (issue futura)

1. Confirme que a issue entrega a funcionalidade que vai usar a permissão.
2. Escolha o identificador seguindo a convenção e confira no catálogo que ainda não existe permissão para a mesma ação.
3. Acrescente em `$permissions` (`app/Config/AuthGroups.php`), com descrição em português.
4. Conceda aos grupos em `$matrix`, explicitamente e pelo menor privilégio.
5. Proteja a rota com `permission:` e repita a checagem no Service quando a operação for sensível.
6. Teste:
   - automatizado: siga o modelo de `tests/feature/PermissoesAdministrativasTest.php`, com um usuário que tem a permissão e outro que não tem;
   - manual: entre com perfis diferentes e acesse a URL direto.
7. Atualize a tabela "Catálogo atual" deste documento.
8. Na descrição da entrega, marque "Altera permissões ou regras de acesso" e informe quais grupos receberam a permissão.

Não é preciso migration: as tabelas do Shield não mudam, e só o arquivo de configuração é alterado.
