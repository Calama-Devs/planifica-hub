# Padrão de Autenticação e Tela de Login

Este documento orienta a arquitetura visual e técnica da tela de login do **PlanIFica Hub**, integrada nativamente ao **CodeIgniter Shield** e em total conformidade com o **Manual Calama Devs** (capítulos 6, 9, 10 e 11).

---

## 1. Estrutura de Arquivos (Manual 9.3 e 10.3)

A interface de autenticação é composta por um layout isolado de tela cheia e uma View específica para o formulário de login:

```text
app/Views/
  layouts/
    autenticacao.php     # Layout base para fluxos de autenticação (sem sidebar interna)
  autenticacao/
    login.php            # View de formulário de login (estende layouts/autenticacao)
```

---

## 2. Integração com o CodeIgniter Shield

A substituição da view padrão de login do Shield é feita via configuração em `app/Config/Auth.php`, **sem qualquer alteração em arquivos do diretório `vendor/`**:

```php
// app/Config/Auth.php
public array $views = [
    'login'    => 'autenticacao/login',
    'register' => '\CodeIgniter\Shield\Views\register',
    'layout'   => '\CodeIgniter\Shield\Views\layout',
    // ...
];
```

A view `app/Views/autenticacao/login.php` estende diretamente `layouts/autenticacao`, preservando o layout padrão do Shield para os demais fluxos (como `register`) e evitando regressões funcionais.

---

## 3. Segurança e Boas Práticas (Manual 11 e 16)

- **Identificador Dinâmico do Shield:** O campo de acesso adapta-se dinamicamente às credenciais válidas configuradas em `setting('Auth.validFields')`. Quando configurado apenas para `email`, gera campo semântico `type="email"` com rótulo "E-mail"; caso configurado para `username`, gera campo textual com rótulo "Usuário", mantendo `name`, `id`, `type` e repopulação coerentes.
- **Proteção CSRF (manual 11.3):** O formulário inclui obrigatoriamente `<?= csrf_field() ?>` e o cabeçalho do layout disponibiliza `<?= csrf_meta() ?>`.
- **Prevenção de Enumeração de Contas:** As mensagens de erro e falha de login exibem retorno genérico do Shield (ex.: "Credenciais inválidas") sem detalhar se o e-mail ou usuário informado existe ou não na base de dados.
- **Não Repopulação de Senha:** Em caso de erro na submissão, apenas o identificador de acesso pode ser repopulado. O campo de senha **nunca** repopula valor digitado anteriormente (`old('password')` nunca é usado).
- **Escape de Saída Explícito (manual 11.6):** Todas as saídas dinâmicas utilizam escape explícito com `esc()`, incluindo o valor repopulado do identificador (`value="<?= esc(old($fieldName)) ?>"`) e os alertas de sessão.
- **Acessibilidade e Semântica (manual 9.9):** Todos os campos de formulário possuem `<label>` explícito associado via atributo `for` ao `id` do respectivo `<input>`, além de tipos semânticos adequados.

---

## 4. Seleção Mínima de Assets e Performance (Manual 15.4 e 25.4)

A tela de autenticação reutiliza estritamente os assets já homologados e versionados no projeto, com payload mínimo e sem carregar bundles JS desnecessários na tela de login:

| Tipo | Caminho do Arquivo | Finalidade |
| :--- | :--- | :--- |
| **CSS** | `public/assets/vendors/mdi/css/materialdesignicons.min.css` | Ícones do sistema. |
| **CSS** | `public/assets/css/modern-vertical/style.css` | Grid e componentes base do Bootstrap/Corona. |
| **CSS** | `public/assets/css/custom.css` | Adaptações de contraste, fundo escuro (`.auth-bg-custom`) e card centralizado. |
| **Fontes** | `public/assets/fonts/Rubik/*` | Tipografia padrão do projeto. |
| **Imagens**| `public/assets/images/logo-ifro-mini.png` | Logomarca institucional do IFRO e favicon. |

> **Performance e Payload Mínimo:** Por se tratar de um formulário POST padrão sem interações no cliente, o layout de autenticação não carrega scripts pesados de terceiros globalmente, disponibilizando as seções `scripts` e `pageScripts` apenas quando uma tela específica demandar código JS.
> **Sem Imagens Externas / Fictícias:** Para evitar erros 404 em ambiente limpo, o fundo da tela utiliza a classe utilitária `.auth-bg-custom` em `custom.css` em vez de referenciar imagens locais ou externas ausentes.
