# Padrão de Layout e Views

Este documento descreve a arquitetura do layout interno (`layouts/principal.php`) e orienta a criação e extensão de novas páginas no PlanIFica Hub, em total conformidade com o **Manual Calama Devs** (capítulos 6, 9 e 10).

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
    index.php           # View do método index() do HomeController
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
2. Adicione o item dentro da lista `<ul class="nav">`, indicando o caminho da rota e a verificação ativa com `uri_string()`:

```php
<li class="nav-item menu-items <?= (uri_string() === 'exemplo') ? 'active' : '' ?>">
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

- **Sem rotas fictícias (manual 19):** Não cadastrar links no menu para páginas inexistentes.
- **CSRF obrigatório (manual 11.3):** Formulários POST devem conter `<?= csrf_field() ?>`. Chamadas AJAX enviam o token configurado globalmente em `public/assets/js/custom.js`.
- **Prevenção de XSS (manual 11.6):** Toda informação dinâmica impressa em HTML deve usar `esc()`.
- **CSS e JS em arquivos externos (manual 9.4):** Não utilizar tags `<style>` ou eventos inline nas views; estilos ficam em `public/assets/css/custom.css`.
