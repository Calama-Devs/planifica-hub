<?php
/**
 * Componente parcial para exibição padronizada de mensagens de sessão (flash data).
 *
 * Suporta mensagens individuais e listas de mensagens (ex.: múltiplos erros de validação),
 * exibindo ícones MDI contextuais e garantindo acessibilidade e proteção contra XSS (esc()).
 */
$tiposMensagem = [
    'success' => ['classe' => 'success', 'icone' => 'mdi-check-circle'],
    'sucesso' => ['classe' => 'success', 'icone' => 'mdi-check-circle'],
    'error'   => ['classe' => 'danger',  'icone' => 'mdi-alert-circle'],
    'erro'    => ['classe' => 'danger',  'icone' => 'mdi-alert-circle'],
    'errors'  => ['classe' => 'danger',  'icone' => 'mdi-alert-circle'],
    'warning' => ['classe' => 'warning', 'icone' => 'mdi-alert'],
    'aviso'   => ['classe' => 'warning', 'icone' => 'mdi-alert'],
    'info'    => ['classe' => 'info',    'icone' => 'mdi-information-outline'],
    'message' => ['classe' => 'info',    'icone' => 'mdi-information-outline'],
];
?>
<?php foreach ($tiposMensagem as $chave => $config) : ?>
    <?php if (session()->has($chave)) : ?>
        <?php $conteudo = session($chave); ?>
        <div class="alert alert-<?= $config['classe'] ?> alert-dismissible fade show d-flex align-items-center" role="alert" aria-live="polite">
            <i class="mdi <?= $config['icone'] ?> me-2 fs-5" aria-hidden="true"></i>
            <div class="flex-grow-1">
                <?php if (is_array($conteudo)) : ?>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($conteudo as $item) : ?>
                            <li><?= esc($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <?= esc($conteudo) ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif ?>
<?php endforeach ?>
