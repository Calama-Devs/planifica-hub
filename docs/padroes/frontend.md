# Front-end

Base: Manual Calama Devs, capítulo 9 e itens 6.8, 11.6 e 11.6.1. Em caso de dúvida, o manual prevalece.

## Princípios

- Priorizar acessibilidade e usabilidade, e dar feedback claro após cada ação.
- Validação no front-end melhora a experiência, mas **nunca substitui** a validação no back-end.
- Manter o padrão visual do template do projeto (cores, componentes, layout). Não criar estilos novos para algo que o template já resolve.

## Organização (manual 9.3)

```
app/Views/
  layouts/          # layout base do projeto
  partials/         # componentes reutilizáveis (menus, alertas, cards)
  aluno/
    index.php
    cadastrar.php
public/assets/
  css/   js/   img/   vendor/   # vendor = Bootstrap, jQuery, DataTables, ícones
```

- Uma pasta por controller (minúsculas) e um arquivo por método, em `snake_case`.
- CSS e JS próprios em arquivos dentro de `public/assets/`, nunca inline.

## HTML e componentes (manual 9.4)

- Evitar `style="..."` e eventos inline (`onclick="..."`); usar só com justificativa.
- Reutilizar componentes existentes (botões, modais, tabelas, formulários, cards, alertas) antes de criar novos.
- Views com o mínimo de PHP: só `if`, `foreach` e `esc()`; os dados chegam prontos do Controller.

```php
<!-- Correto -->
<div class="alert alert-danger alerta-erro">Atenção! Ocorreu um erro.</div>
<h1><?= esc($aluno['nome']) ?></h1>

<!-- Incorreto -->
<div style="background-color: red" onclick="fecharAviso()">...</div>
<?php $alunos = (new AlunoModel())->findAll(); ?>
```

## Bootstrap e CSS (manual 9.5 e 9.6)

- Priorizar classes do Bootstrap (grid, `btn`, `form-control`, `card`, `alert`, `table`).
- CSS próprio só quando necessário, em arquivo separado, com classe específica que complementa o Bootstrap (`btn btn-primary botao-sistema`).

## JavaScript e jQuery (manual 9.7 e 9.8)

- O projeto usa jQuery para manipulação de DOM, eventos e AJAX. Não adicionar outro framework JS sem decisão registrada (ADR).
- Scripts em `public/assets/js/`, ligados por eventos (`$('#botao').on('click', ...)`), não por atributos inline.
- Ao inserir dados do servidor na página, usar `.text()` e não `.html()`, para não abrir brecha de XSS.
- Requisições que alteram dados (POST, PUT, DELETE) enviam o token CSRF. Exemplo:

```php
<!-- no layout base: gera <meta name="X-CSRF-TOKEN" content="..."> -->
<?= csrf_meta() ?>
```

```js
// public/assets/js/app.js: envia o token em todo AJAX
$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="X-CSRF-TOKEN"]').attr('content') }
});

// public/assets/js/aluno.js
$.ajax({
    url: '/alunos/salvar',
    type: 'POST',
    data: { nome: $('#nome').val() },
    success: function (resposta) { $('#mensagem').text(resposta.mensagem); },
    error: function () { $('#mensagem').text('Não foi possível concluir a operação.'); }
});
```

- No CI4 o token CSRF é regenerado a cada envio por padrão (`$regenerate = true` em `app/Config/Security.php`). Em telas com vários AJAX, devolva o novo `csrf_hash()` na resposta JSON e atualize a meta tag e o cabeçalho, ou combine com a liderança o uso de `$regenerate = false`.
- O Controller responde AJAX com `return $this->response->setJSON([...]);`, usando o retorno padronizado (`sucesso`, `tipo`, `codigo`, `mensagem`).
- Não deixar `console.log` de depuração no código entregue.

## Formulários (manual 9.9 e 11.6.1)

- Todo formulário que envia dados tem `<?= csrf_field() ?>`.
- Todo campo tem `name`, `id` e `<label for>` correspondente.
- Atributos de validação (`required`, `maxlength`, `type="email"`) ajudam, mas a mesma regra precisa existir no back-end.
- Em erro de validação, manter os dados digitados com `old('campo')` (que já aplica escape HTML, então não envolva em `esc()`) e mostrar a mensagem perto do campo.

```php
<form action="/alunos/salvar" method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="nome" class="form-label">Nome</label>
        <input type="text" id="nome" name="nome" class="form-control" maxlength="100" value="<?= old('nome') ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
```

## Tabelas, modais e feedback (manual 9.10)

- Tabelas exibem só o necessário; dados pessoais apenas quando a funcionalidade exige.
- Toda ação do usuário tem retorno visual (alerta de sucesso, erro ou aviso).
- Confirmação em modal antes de ações destrutivas.

## Acessibilidade e responsividade (manual 9.11)

- Elementos semânticos, `alt` em imagens, contraste suficiente, `label` em todos os campos.
- Layout com grid do Bootstrap (`col-12 col-md-6`), testado em celular e desktop.

## Dependências e cache (manual 9.12 e 9.13)

- Bibliotecas externas só quando necessárias, sem duplicar versões; ficam em `public/assets/vendor/`.
- Assets alterados recebem versionamento contra cache:

```php
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(FCPATH . 'assets/css/style.css') ?>">
```

## Sinais de alerta para o revisor

- Variável impressa sem `esc()` (bloqueante: XSS).
- Formulário POST sem `csrf_field()` ou AJAX sem token (bloqueante).
- `.html(resposta.algo)` com dado vindo do servidor.
- Estilo inline, CSS duplicado, componente recriado do zero.
- Validação só no front-end.
