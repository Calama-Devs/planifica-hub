# Padrão de Layout e Dashboard

Este documento orienta a estrutura do layout interno (`dashboard.php`) e como estender novas páginas e itens de menu de forma padronizada, seguindo exatamente a arquitetura dos projetos **RoBoto** e **RIF** do Calama Devs.

---

## 1. Estrutura de Views

```text
app/Views/
  dashboard.php       # Layout interno com sidebar, navbar, mensagens e footer
  home.php            # Conteúdo da tela inicial (Dashboard)
  components/         # Modais e blocos de componentes reutilizáveis (quando houver)
  sys/                # Telas de funcionalidades internas futuras (ex: sys/usuarios.php)
```

---

## 2. Como criar uma nova página

1. Crie o arquivo da tela em `app/Views/home.php` (para visão geral) ou em `app/Views/sys/<modulo>.php` para módulos específicos.
2. No Controller correspondente, renderize a tela na chave `$data['content']` e retorne `view('dashboard', $data)`:

```php
namespace App\Controllers;

class ExemploController extends BaseController
{
    public function index(): string
    {
        $data = [
            'title' => 'Exemplo | PlanIFica Hub',
            // Dados da regra de negócio...
        ];

        $data['content'] = view('sys/exemplo', $data);

        return view('dashboard', $data);
    }
}
```

---

## 3. Como adicionar novo item no menu

Quando a rota e a tela estiverem prontas:

1. Abra `app/Views/dashboard.php`.
2. Localize a `<ul class="nav">` dentro da `<nav class="sidebar">`.
3. Adicione o item definindo a rota e a checagem ativa via `uri_string()`:

```php
<li class="nav-item menu-items <?= ($uri === 'sys/exemplo') ? 'active' : '' ?>">
    <a class="nav-link" href="<?= site_url('sys/exemplo') ?>">
        <span class="menu-icon">
            <i class="mdi mdi-view-dashboard text-info" aria-hidden="true"></i>
        </span>
        <span class="menu-title">Exemplo</span>
    </a>
</li>
```

---

## 4. Regras a observar

- **Sem rotas fictícias:** Não adicione itens de menu para páginas ou rotas inexistentes.
- **CSRF:** Formulários e requisições POST/AJAX que alteram dados devem usar `csrf_field()` ou o header configurado em `public/assets/js/custom.js`.
- **Escape de dados:** Qualquer informação dinâmica na view deve usar `esc()`.
