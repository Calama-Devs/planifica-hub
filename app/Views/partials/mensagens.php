<?php
$tiposMensagem = [
    'sucesso' => 'success',
    'erro'    => 'danger',
    'aviso'   => 'warning',
    'info'    => 'info',
];
?>
<?php foreach ($tiposMensagem as $chave => $classe) : ?>
    <?php if (session()->has($chave)) : ?>
        <div class="alert alert-<?= $classe ?> alert-dismissible fade show" role="alert">
            <?= esc(session($chave)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif ?>
<?php endforeach ?>
