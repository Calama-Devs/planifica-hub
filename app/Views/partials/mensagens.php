<?php
/**
 * Componente parcial para exibição padronizada de mensagens de sessão (flash data).
 *
 * Suporta mensagens individuais e listas de mensagens (ex.: múltiplos erros de validação),
 * exibindo ícones MDI contextuais e garantindo acessibilidade e proteção contra XSS (esc()).
 * Evita alertas duplicados definindo precedência de chaves por categoria semântica.
 */
$categorias = [
    [
        'chaves' => ['success', 'sucesso'],
        'classe' => 'success',
        'icone'  => 'mdi-check-circle',
    ],
    [
        'chaves' => ['error', 'erro', 'errors'],
        'classe' => 'danger',
        'icone'  => 'mdi-alert-circle',
    ],
    [
        'chaves' => ['warning', 'aviso'],
        'classe' => 'warning',
        'icone'  => 'mdi-alert',
    ],
    [
        'chaves' => ['info', 'message'],
        'classe' => 'info',
        'icone'  => 'mdi-information-outline',
    ],
];
?>
<?php foreach ($categorias as $categoria) : ?>
    <?php
    $conteudo = null;
    foreach ($categoria['chaves'] as $chave) {
        if (session()->has($chave)) {
            $conteudo = session($chave);
            break;
        }
    }
    ?>
    <?php if ($conteudo !== null && $conteudo !== '' && $conteudo !== []) : ?>
        <div class="alert alert-<?= $categoria['classe'] ?> alert-dismissible fade show d-flex align-items-center" role="alert" aria-live="polite">
            <i class="mdi <?= $categoria['icone'] ?> me-2 fs-5" aria-hidden="true"></i>
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
<?php endforeach; ?>
