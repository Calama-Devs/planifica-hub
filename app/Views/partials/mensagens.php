<?php
$tiposMensagem = [
    'success' => 'success',
    'sucesso' => 'success',
    'error'   => 'danger',
    'erro'    => 'danger',
    'errors'  => 'danger',
    'warning' => 'warning',
    'aviso'   => 'warning',
    'info'    => 'info',
    'message' => 'info',
];
?>
<?php foreach ($tiposMensagem as $chave => $classe) : ?>
    <?php if (session()->has($chave)) : ?>
        <?php $conteudo = session($chave); ?>
        <div class="alert alert-<?= $classe ?> alert-dismissible fade show" role="alert">
            <?php if (is_array($conteudo)) : ?>
                <ul class="mb-0 ps-3">
                    <?php foreach ($conteudo as $item) : ?>
                        <li><?= esc($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <?= esc($conteudo) ?>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif ?>
<?php endforeach ?>
