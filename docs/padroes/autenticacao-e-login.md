# Padrão de Autenticação e Design da Tela de Login

Este documento orienta a arquitetura visual e técnica da tela de login do **PlanIFica**, integrada nativamente ao **CodeIgniter Shield** e em total conformidade com o **Manual Calama Devs** (capítulos 6, 9, 10, 11 e 15), adotando o padrão **Corona (Login-2 split-screen)**.

---

## 1. Estrutura de Arquivos (Manual 9.3 e 10.3)

A interface de autenticação é composta por um layout de tela cheia e uma View específica para o formulário de login no padrão split-screen:

```text
app/Views/
  layouts/
    autenticacao.php     # Layout minimalista full-page para autenticação (sem sidebar/navbar)
  autenticacao/
    login.php            # View de formulário de login 2 e hero institucional (estende layouts/autenticacao)
```

> **Exceção Arquitetural do Shield (Manual 10.3):**
> O fluxo de login é provido nativamente pelo pacote `CodeIgniter\Shield\Controllers\LoginController`, sem a existência de um controller intermediário em `app/Controllers/`.
> Para manter a coesão de domínio e aderência aos requisitos definidos na issue PLAN-37, as telas customizadas do Shield são agrupadas no diretório de domínio `app/Views/autenticacao/` e mapeadas em `app/Config/Auth.php`. Essa exceção arquitetural evita a criação de controladores casca vazios mantendo a integração oficial do framework.

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

## 3. Design Visual: Template Corona Login-2

A tela adota a variante **login-2** do template oficial Corona:

1. **Coluna Esquerda (Card de Login):**
   - Ocupa `col-12 col-md-5 col-lg-4` com altura de 100vh (`min-height: 100vh;`) e fundo escuro (`#191c24`).
   - Logomarca institucional do IFRO (`logo-ifro-mini.png`) e identificação "PlanIFica".
   - Campo identificador dinâmico: adapta-se a `Auth.validFields` (`email`, `username` ou `login`) via helper `autenticacao`.
   - Campo de senha seguro (sem repopulação por `old()`).
   - Opção de "Lembrar-me" respeitando a configuração `Auth.sessionConfig['allowRemembering']`.
   - Link "Esqueceu a senha?" direcionando para rota de recuperação do Shield.
   - Botão de submissão principal com ícone MDI (`mdi-login`).

2. **Coluna Direita (Informações do Projeto e Calama Devs):**
   - Ocupa `col-12 col-md-7 col-lg-8` em telas médias e grandes (`d-none d-md-flex`).
   - Exibe como plano de fundo a imagem oficial (`Login_bg.jpg`) via classe `.auth.login-bg`.
   - Sobreposto à imagem, um card informativo (`backdrop-filter: blur(8px)`) apresentando:
     - Identificação institucional do **IFRO Calama**.
     - Descrição do propósito do sistema: gestão e organização de horários acadêmicos e ensalamento.
     - Informação sobre o desenvolvimento por alunos do curso de Análise e Desenvolvimento de Sistemas para o IFRO Campus Porto Velho Calama (time Calama Devs).

---

## 4. Segurança e Acessibilidade (Manual 9.9, 11 e 16)

- **Identificador Dinâmico do Shield:** O campo de acesso adapta-se dinamicamente às credenciais válidas configuradas em `setting('Auth.validFields')`. Quando configurado apenas para `email`, gera campo semântico `type="email"` com rótulo "E-mail"; caso configurado para `username`, gera campo textual com rótulo "Usuário"; para múltiplos campos, gera identificador "login", mantendo `name`, `id`, `type` e repopulação coerentes.
- **Proteção CSRF (manual 11.3):** O formulário inclui obrigatoriamente `<?= csrf_field() ?>` e o cabeçalho do layout disponibiliza `<?= csrf_meta() ?>`.
- **Prevenção de Enumeração de Contas:** As mensagens de erro e falha de login exibem retorno genérico do Shield sem detalhar se o e-mail ou usuário informado existe ou não na base de dados.
- **Não Repopulação de Senha:** Em caso de erro na submissão, apenas o identificador de acesso pode ser repopulado. O campo de senha **nunca** repopula valor digitado anteriormente (`old('password')` nunca é usado).
- **Escape de Saída Explícito (manual 11.6):** Todas as saídas dinâmicas utilizam escape explícito com `esc()`, incluindo o valor repopulado do identificador e os alertas de sessão.
- **Acessibilidade Semântica (manual 9.9):** Todos os campos possuem `id`, `name` e `<label for="...">` associados, com indicadores claros de campos obrigatórios (`*`).

---

## 5. Seleção Mínima de Assets, Versionamento e Performance (Manual 9.12, 9.13, 15.4 e 25.4)

A interface reutiliza os assets homologados no projeto e adiciona estritamente o necessário para o design login-2:

| Tipo | Caminho do Arquivo | Finalidade |
| :--- | :--- | :--- |
| **Imagem de Fundo** | `public/assets/images/auth/Login_bg.jpg` | Background oficial do template Corona Login-2. |
| **CSS de Ícones** | `public/assets/vendor/mdi/css/materialdesignicons.min.css` | Ícones do sistema e ações. |
| **CSS do Template** | `public/assets/css/modern-vertical/style.css` | Grid e componentes base do Bootstrap/Corona. |
| **CSS Customizado**| `public/assets/css/custom.css` | Adaptações de contraste, checkbox acessível e fundo escuro. |
| **Fontes** | `public/assets/fonts/Rubik/*` | Tipografia padrão do projeto. |
| **Logomarca**| `public/assets/images/logo-ifro-mini.png` | Logomarca institucional do IFRO e favicon. |

> **Versionamento contra Cache (manual 9.12 e 9.13):** Conforme orientação do Manual Calama Devs (`docs/padroes/frontend.md`), os arquivos CSS são carregados com sufixo dinâmico baseado no timestamp de modificação (`?v=<?= filemtime(...) ?>`), prevenindo que clientes utilizem arquivos desatualizados em cache após novos deploys.
> **Performance e Payload Mínimo:** O layout de autenticação não carrega scripts desnecessários globalmente, disponibilizando as seções `scripts` e `pageScripts` apenas quando uma tela específica demandar código JS.
