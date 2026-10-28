# Padrão de Autenticação e Tela de Login

Este documento orienta a arquitetura visual e técnica da tela de login do **PlanIFica Hub**, integrada nativamente ao **CodeIgniter Shield** e em total conformidade com o **Manual Calama Devs** (capítulos 6, 9, 10 e 11).

---

## 1. Estrutura de Arquivos (Manual 9.3 e 10.3)

A interface de autenticação é composta por um layout isolado de tela cheia e uma View específica para o formulário de login:

```text
app/Views/
  layouts/
    autenticacao.php     # Layout base para fluxos públicos/autenticação (sem sidebar interna)
  autenticacao/
    login.php            # View de formulário de login (substitui a view padrão do Shield)
```

---

## 2. Integração com o CodeIgniter Shield

A substituição da view padrão do Shield é feita via configuração em `app/Config/Auth.php`, **sem qualquer alteração em arquivos do diretório `vendor/`**:

```php
// app/Config/Auth.php
public array $views = [
    'login'  => 'autenticacao/login',
    'layout' => 'layouts/autenticacao',
    // ...
];
```

Dessa forma, todo o ciclo de vida da autenticação (validação de credenciais, geração de hash de sessão, cookies de `remember me` e controle de tentativas de login) é preservado de forma nativa pelo Shield.

---

## 3. Segurança e Boas Práticas (Manual 11 e 16)

- **Proteção CSRF (manual 11.3):** O formulário inclui obrigatoriamente `<?= csrf_field() ?>` e o cabeçalho do layout disponibiliza `<?= csrf_meta() ?>`.
- **Prevenção de Enumeração de Contas:** As mensagens de erro e falha de login exibem retorno genérico do Shield (ex.: "Credenciais inválidas") sem detalhar se o e-mail informado existe ou não na base de dados.
- **Não Repopulação de Senha:** Em caso de erro na submissão, apenas o campo de e-mail pode ser repopulado (`value="<?= old('email') ?>"`). O campo de senha **nunca** repopula valor digitado anteriormente (`old('password')` nunca é usado).
- **Escape de Saída (manual 11.6):** Todas as mensagens de erro e alertas de sessão utilizam `esc()` (`<?= esc(session('error')) ?>`).
- **Acessibilidade e Semântica (manual 9.9):** Todos os campos de formulário possuem `<label>` explícito associado via atributo `for` ao `id` do respectivo `<input>`, além de tipos semânticos (`type="email"` e `type="password"`).

---

## 4. Seleção Mínima de Assets (Manual 15.4 e 25.4)

A tela de autenticação reutiliza estritamente os assets já homologados e versionados no projeto, evitando qualquer dependência excedente ou risco de erro HTTP 404:

| Tipo | Caminho do Arquivo | Finalidade |
| :--- | :--- | :--- |
| **CSS** | `public/assets/vendors/mdi/css/materialdesignicons.min.css` | Ícones do sistema. |
| **CSS** | `public/assets/css/modern-vertical/style.css` | Grid e componentes base do Bootstrap/Corona. |
| **CSS** | `public/assets/css/custom.css` | Adaptações de contraste, fundo escuro (`.auth-bg-custom`) e card centralizado. |
| **Fontes** | `public/assets/fonts/Rubik/*` | Tipografia padrão do projeto. |
| **Imagens**| `public/assets/images/logo-ifro-mini.png` | Logomarca institucional do IFRO e favicon. |
| **JS** | `public/assets/vendors/js/vendor.bundle.base.js` | jQuery e bundle do Bootstrap para comportamentos dinâmicos. |
| **JS** | `public/assets/js/custom.js` | Inicialização padronizada de CSRF e scripts locais. |

> **Sem Imagens Externas / Fictícias:** Para evitar erros 404 em ambiente limpo, o fundo da tela utiliza a classe utilitária `.auth-bg-custom` em `custom.css` em vez de referenciar imagens locais ou externas ausentes.

