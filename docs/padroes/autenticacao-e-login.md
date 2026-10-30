# Padrão de Autenticação e Design da Tela de Login

Este documento descreve o novo design da página de login do **PlanIFica**, sua conformidade com o **Manual Calama Devs** (capítulos 6, 9, 10, 11 e 15), a integração com o template oficial **Corona (Login-2 split-screen)** e o pacote **CodeIgniter Shield**.

---

## 1. Arquitetura da Solução e Estrutura de Views

A interface segue o padrão split-screen do template oficial Corona (`login-2`), dividindo a tela entre o formulário de login e a apresentação institucional do Calama Devs:

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

A view customizada é conectada ao Shield via configuração em `app/Config/Auth.php`:

```php
public array $views = [
    'login'    => 'autenticacao/login',
    'register' => '\CodeIgniter\Shield\Views\register',
    'layout'   => '\CodeIgniter\Shield\Views\layout',
    // ...
];
```

Mantendo o layout padrão do Shield intacto para telas não customizadas (como `register`), garantimos que não haja regressão funcional ou quebra de seções em outros fluxos.

---

## 3. Design Visual: Template Corona Login-2

A tela adota a variante **login-2** do template oficial Corona:

1. **Coluna Esquerda (Card de Login):**
   - Ocupa `col-12 col-md-5 col-lg-4` com altura de 100vh (`min-height: 100vh;`) e fundo escuro (`#191c24`).
   - Logomarca institucional do IFRO (`logo-ifro-mini.png`) e identificação "PlanIFica".
   - Campo identificador dinâmico: adapta-se a `Auth.validFields` (`email`, `username` ou `login`).
   - Campo de senha seguro (sem repopulação por `old()`).
   - Opção de "Lembrar-me" sempre visível e habilitada.
   - Botão de submissão principal com ícone MDI (`mdi-login`).
   - Link para o modal informativo sobre o projeto.

2. **Coluna Direita (Informações do Projeto e Calama Devs):**
   - Ocupa `col-12 col-md-7 col-lg-8` em telas médias e grandes (`d-none d-md-flex`).
   - Exibe como plano de fundo a imagem oficial de pôr-do-sol da ponte (`Login_bg.jpg`) via classe `.auth.login-bg`.
   - Sobreposto à imagem, um card informativo simples e direto (`backdrop-filter: blur(8px)`) apresentando:
     - Identificação institucional do **IFRO Calama**.
     - Descrição do propósito do sistema: gestão e consulta de horários acadêmicos e ensalamento.
     - Informação sobre o desenvolvimento por alunos e professores do IFRO Campus Porto Velho Calama (time Calama Devs).

3. **Modal Informativo (Acessível em Qualquer Dispositivo):**
   - Modal com informações básicas sobre o desenvolvimento do PlanIFica pela equipe de estudantes e docentes.

---

## 4. Segurança e Acessibilidade (Manual 9.9, 11.6, 11.6.1 e 15)

- **Proteção CSRF:** Toda requisição POST inclui `<?= csrf_field() ?>` e meta tag `<?= csrf_meta() ?>`.
- **Prevenção de XSS:** Toda saída de dados utiliza `esc()`.
- **Proteção de Senha:** Campos de senha nunca reutilizam valores prévios (`old('password')` jamais é utilizado).
- **Sem Enumeração de Contas:** Mensagens de erro de autenticação utilizam os textos genéricos providos pela sessão do Shield.
- **Acessibilidade Semântica:** Todos os campos possuem `id`, `name` e `<label for="...">` associados, com indicadores claros de campos obrigatórios (`*`).

---

## 5. Seleção Mínima de Assets e Versionamento (Manual 9.12, 9.13 e 25.4)

A interface reutiliza os assets homologados no projeto e adiciona estritamente o necessário para o design login-2:

| Asset | Localização | Finalidade |
| :--- | :--- | :--- |
| **Imagem de Fundo** | `public/assets/images/auth/Login_bg.jpg` | Background oficial do template Corona Login-2. |
| **CSS de Ícones** | `public/assets/vendors/mdi/css/materialdesignicons.min.css` | Ícones de interface e ações. |
| **CSS do Template** | `public/assets/css/modern-vertical/style.css` | Folha de estilos base do Corona Admin. |
| **CSS Customizado**| `public/assets/css/custom.css` | Ajustes e classes do PlanIFica Hub. |
| **JS Base** | `public/assets/vendors/js/vendor.bundle.base.js` | Bootstrap e utilitários para modais e interações. |

> **Versionamento contra Cache:** Conforme o Manual Calama Devs (`docs/padroes/frontend.md`), os arquivos de assets contam com sufixo dinâmico `?v=<?= filemtime(...) ?>` para prevenir que os clientes utilizem versões obsoletas em cache.

