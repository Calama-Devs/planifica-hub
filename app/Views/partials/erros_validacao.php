<?php
/**
 * Componente parcial para exibição de erro de validação por campo.
 *
 * Utilizado abaixo de inputs para exibir a mensagem de erro específica do campo.
 *
 * Exemplo de uso:
 *   <?= $this->include('partials/erros_validacao', ['campo' => 'email']) ?>
 *
 * @var string|null $campo Nome do campo a verificar.
 * @var array|null $erros Lista opcional de erros passada diretamente pela View.
 */
$campoNome = $campo ?? '';
$mensagemErro = '';

if ($campoNome !== '') {
    if (isset($erros) && is_array($erros) && isset($erros[$campoNome])) {
        $mensagemErro = (string) $erros[$campoNome];
    } elseif (function_exists('validation_errors')) {
        $todosErros = validation_errors();
        if (is_array($todosErros) && isset($todosErros[$campoNome])) {
            $mensagemErro = (string) $todosErros[$campoNome];
        }
    }

    if ($mensagemErro === '' && session()->has('errors')) {
        $errosSessao = session('errors');
        if (is_array($errosSessao) && isset($errosSessao[$campoNome])) {
            $mensagemErro = (string) $errosSessao[$campoNome];
        }
    }
}
?>
<?php if ($mensagemErro !== '') : ?>
    <div class="invalid-feedback d-block" role="alert">
        <?= esc($mensagemErro) ?>
    </div>
<?php endif ?>

