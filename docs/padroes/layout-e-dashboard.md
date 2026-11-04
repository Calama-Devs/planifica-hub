# Padrão de Layout e Views

Este documento descreve a arquitetura do layout interno (`layouts/principal.php`) e orienta a criação e extensão de novas páginas no PlanIFica Hub, em total conformidade com o **Manual Calama Devs** (capítulos 6, 9 e 10).

O mapa central de diretórios e namespaces, a localização da autenticação e o
registro do template oficial com sua política de uso seletivo estão em
[estrutura inicial](../estrutura-inicial.md). Aqui ficam as instruções de
composição das telas e o inventário dos assets usados pelo layout.

---

## 1. Estrutura de Views (Manual 9.3 e 10.3)

O projeto adota a convenção de pastas por Controller em letras minúsculas e arquivos por método em `snake_case`, além da separação de componentes em partials e layout base:

```text
app/Views/
  layouts/
    principal.php       # Layout base reutilizável com regiões: titulo, estilos, conteudo, scripts
  partials/             # Componentes reutilizáveis compartilhados do layout
    menu.php            # Menu de navegação lateral (sidebar Corona)
    cabecalho.php       # Barra superior com perfil e logout com proteção CSRF
    mensagens.php       # Alertas de sessão (sucesso, erro, aviso, info)
    rodape.php          # Rodapé institucional oficial e ano dinâmico
  home/
    index.php           # View do método index() (Dashboard/Início) estendendo o layout
    sobre.php           # View do método sobre() (Segunda tela de demonstração do reuso)
  <controller>/
    <metodo>.php        # Telas dos futuros controllers do sistema
```

---

## 2. Como criar uma nova página

Para criar uma tela e integrá-la ao layout reutilizável:

1. Crie o arquivo da view no diretório do controller correspondente, ex.: `app/Views/<controller>/<metodo>.php`.
2. Estenda o layout principal através da diretiva `$this->extend('layouts/principal')` e preencha as seções necessárias:

```php
<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?>Nome da Página<?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Título do Módulo</h4>
                <!-- Conteúdo da página -->
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Scripts específicos desta tela (se houver) -->
<?= $this->endSection() ?>
```

3. No Controller, retorne a view correspondente de forma direta:

```php
namespace App\Controllers;

class ExemploController extends BaseController
{
    public function index(): string
    {
        $dados = [
            // Regra de negócio...
        ];

        return view('exemplo/index', $dados);
    }
}
```

---

## 3. Como adicionar novos itens ao menu

Quando uma nova rota autenticada for criada:

1. Abra o arquivo `app/Views/partials/menu.php`.
2. Adicione o item dentro da lista `<ul class="nav">`, indicando o caminho da rota e a verificação ativa com a função `url_is()`:

```php
<li class="nav-item menu-items <?= url_is('exemplo*') ? 'active' : '' ?>">
    <a class="nav-link" href="<?= site_url('exemplo') ?>">
        <span class="menu-icon">
            <i class="mdi mdi-view-dashboard text-primary" aria-hidden="true"></i>
        </span>
        <span class="menu-title">Exemplo</span>
    </a>
</li>
```

---

## 4. Regras do Manual

- **Sem rotas fictícias (manual 19):** Não cadastrar links no menu para páginas inexistentes (o menu possui apenas as rotas reais ativas `/` e `/sobre`).
- **CSRF obrigatório (manual 11.3):** Formulários POST contêm `<?= csrf_field() ?>`. Chamadas AJAX enviam o token configurado em `public/assets/js/custom.js`, que lê e valida o meta tag `X-CSRF-TOKEN` gerado por `<?= csrf_meta() ?>` no layout base e o mantém atualizado em cada requisição (`ajaxSend`), sem nomes de cookie hardcoded e impedindo envio de headers com valores indefinidos.
- **Proteção de Rotas Internas:** As páginas que utilizam `layouts/principal` são protegidas pelo filtro de autenticação `session` em `app/Config/Routes.php`, impedindo acesso indevido por usuários não autenticados.
- **Prevenção de XSS (manual 11.6):** Toda informação dinâmica impressa em HTML deve usar `esc()`.
- **Mensagens de Sessão:** O partial `partials/mensagens.php` aceita as chaves padrão do CodeIgniter 4 (`success`, `error`, `errors`, `warning`, `info`, `message`) e equivalentes em português (`sucesso`, `erro`, `aviso`), com ícones MDI e atributos semânticos de acessibilidade.
- **CSS e JS em arquivos externos (manual 9.4):** Não utilizar tags `<style>` ou eventos inline nas views; estilos ficam em `public/assets/css/custom.css`.

---

## 5. Seleção Mínima de Assets (Manual 15.4 e 25.4)

A entrega foi reduzida ao conjunto estritamente essencial para o funcionamento do layout base compartilhado, garantindo ausência de erros 404 e sem dependências órfãs de plugins ausentes em runtime:

| Tipo | Caminho do Arquivo | Justificativa de Uso Estrito |
| :--- | :--- | :--- |
| **CSS** | `public/assets/vendor/mdi/css/materialdesignicons.min.css` | Ícones oficiais da barra de navegação, menu lateral e botões de ação. |
| **Fontes** | `public/assets/vendor/mdi/fonts/*` | Arquivos de fonte web (`.woff2`, `.woff`, `.ttf`, `.eot`) para renderizar os ícones MDI sem 404. |
| **CSS** | `public/assets/css/modern-vertical/style.css` | Framework visual Corona (Bootstrap e grid responsivo do layout). |
| **Fontes** | `public/assets/fonts/Rubik/*` | Tipografia institucional consumida diretamente pelo `style.css`. |
| **CSS** | `public/assets/css/custom.css` | Adaptações institucionais do IFRO, tamanho da logo e regras globais do projeto. |
| **Imagens**| `public/assets/images/logo-ifro.png` | Logomarca oficial IFRO (menu expandido). |
| **Imagens**| `public/assets/images/logo-ifro-mini.png` | Logomarca reduzida IFRO (menu recolhido) e favicon. |
| **JS** | `public/assets/vendor/js/vendor.bundle.base.js` | jQuery e Bootstrap bundle necessários para o funcionamento de dropdowns e collapse do menu. |
| **JS** | `public/assets/js/off-canvas.js` | Controle de abertura/fechamento do menu lateral em telas móveis. |
| **JS** | `public/assets/js/hoverable-collapse.js` | Expansão de submenus ao passar o mouse com a barra minimizada. |
| **JS** | `public/assets/js/misc.js` | Inicialização dos comportamentos visuais de navegação (sem dependência de plugins órfãos como PerfectScrollbar). |
| **JS** | `public/assets/js/custom.js` | Configuração global e resiliente de CSRF token e scripts personalizados. |

> **Nota de Exclusão de Excedentes:** Arquivos desnecessários como `vendor.bundle.base.css` (estilos legados de PerfectScrollbar) e plugins pesados de terceiros (gráficos, datatables) foram excluídos da entrega desta etapa para manter acoplamento mínimo e conformidade com o escopo de layout compartilhado. Qualquer chamada ao `PerfectScrollbar` em `misc.js` foi eliminada, preservando a estabilidade da interface.

---

## 6. Mensagens de Sessão e Validação por Campo (Manual 9.9, 11.6.1 e 6.10.2)

### 6.1 Como disparar mensagens no Controller

No Controller, utilize o redirecionamento com flash data através de `with()`:

```php
// Mensagem de sucesso
return redirect()->to('/dashboard')->with('success', 'Cadastro realizado com sucesso.');

// Mensagem de erro simples
return redirect()->back()->withInput()->with('error', 'Não foi possível concluir a ação.');

// Mensagem de aviso / atenção
return redirect()->back()->with('warning', 'Atenção aos prazos de entrega informados.');

// Mensagem informativa
return redirect()->to('/dashboard')->with('info', 'Os dados foram sincronizados.');

// Erros múltiplos de validação
if (! $this->validate($regras)) {
    return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
}
```

O layout base (`layouts/principal.php`) já inclui automaticamente o partial `partials/mensagens.php` no topo de cada página interna, renderizando o alerta correspondente com ícone MDI e escape seguro de strings e listas. O componente agrupa as mensagens por categoria semântica (`success`/`sucesso`, `warning`/`aviso`, `info`/`message`), com ordem de precedência entre termos sinônimos. Para falhas, trata de forma combinada a mensagem geral (`error`/`erro`) e a lista de validação (`errors`/`erros`), garantindo que erros de validação nunca sejam mascarados caso uma mensagem geral de erro também seja enviada na sessão.

### 6.2 Como criar formulários com validação por campo

Para dar feedback próximo a cada campo quando ocorrer erro de validação:

1. Adicione a classe `<?= erro_campo('nome_campo') ?>` na tag do `<input>`, `<select>` ou `<textarea>`. O helper adiciona automaticamente `is-invalid` se o campo possuir erro registrado.
2. Renderize o partial `partials/erros_validacao` logo abaixo do campo para exibir o feedback textual: `<?= view('partials/erros_validacao', ['campo' => 'nome_campo']) ?>`.
3. Preserve os dados digitados usando `old('nome_campo')`.

Exemplo de referência completo:

```php
<form action="<?= site_url('planejamento/salvar') ?>" method="post">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="campo-titulo" class="form-label">Título</label>
        <input type="text" id="campo-titulo" name="titulo"
               class="form-control <?= erro_campo('titulo') ?>"
               maxlength="150" value="<?= old('titulo') ?>" required>
        <?= view('partials/erros_validacao', ['campo' => 'titulo']) ?>
    </div>

    <div class="mb-3">
        <label for="campo-email" class="form-label">E-mail de Contato</label>
        <input type="email" id="campo-email" name="email"
               class="form-control <?= erro_campo('email') ?>"
               maxlength="254" value="<?= old('email') ?>" required>
        <?= view('partials/erros_validacao', ['campo' => 'email']) ?>
    </div>

    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
```
