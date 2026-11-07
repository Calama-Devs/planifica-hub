<?php
/**
 * Navegação entre páginas de uma listagem paginada, com as classes do Bootstrap e textos em português.
 *
 * Uso: <?= view('partials/paginacao', ['paginador' => $paginador]) ?>
 *
 * @var \CodeIgniter\Pager\Pager $paginador Paginador devolvido pelo Service.
 */
$totalPaginas = $paginador->getPageCount();
$paginaAtual  = $paginador->getCurrentPage();
$anterior     = $paginador->getPreviousPageURI();
$proxima      = $paginador->getNextPageURI();
?>
<?php if ($totalPaginas > 1) : ?>
    <nav aria-label="Navegação entre páginas">
        <ul class="pagination mb-0 align-items-center">
            <li class="page-item <?= $anterior === null ? 'disabled' : '' ?>">
                <?php if ($anterior !== null) : ?>
                    <a class="page-link" href="<?= esc($anterior, 'attr') ?>">Anterior</a>
                <?php else : ?>
                    <span class="page-link">Anterior</span>
                <?php endif ?>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Página <?= (int) $paginaAtual ?> de <?= (int) $totalPaginas ?></span>
            </li>
            <li class="page-item <?= $proxima === null ? 'disabled' : '' ?>">
                <?php if ($proxima !== null) : ?>
                    <a class="page-link" href="<?= esc($proxima, 'attr') ?>">Próxima</a>
                <?php else : ?>
                    <span class="page-link">Próxima</span>
                <?php endif ?>
            </li>
        </ul>
    </nav>
<?php endif ?>
