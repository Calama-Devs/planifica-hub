<?php
/**
 * Componente parcial para exibição padronizada de mensagens de sessão (flash data).
 *
 * Suporta mensagens individuais e listas de mensagens (ex.: múltiplos erros de validação),
 * exibindo ícones MDI contextuais e garantindo acessibilidade e proteção contra XSS (esc()).
 * Evita alertas duplicados definindo precedência de chaves por categoria semântica,
 * garantindo que mensagens gerais de erro e listas de validação não se mascarem mutuamente.
 */

// Resolução de sinônimos com precedência para mensagens simples
$obterPrimeiraMensagem = static function (array $chaves) {
    foreach ($chaves as $chave) {
        if (session()->has($chave)) {
            $valor = session($chave);
            if ($valor !== null && $valor !== '' && $valor !== []) {
                return $valor;
            }
        }
    }

    return null;
};

$sucesso = $obterPrimeiraMensagem(['success', 'sucesso']);
$aviso   = $obterPrimeiraMensagem(['warning', 'aviso']);
$info    = $obterPrimeiraMensagem(['info', 'message']);

// Resolução de erros: separa mensagem descritiva de lista de validação para evitar ocultação
$erroGeral      = $obterPrimeiraMensagem(['error', 'erro']);
$errosValidacao = $obterPrimeiraMensagem(['errors', 'erros']);

$erroTexto = null;
$erroLista = [];

if ($erroGeral !== null) {
    if (is_array($erroGeral)) {
        $erroLista = array_merge($erroLista, $erroGeral);
    } else {
        $erroTexto = (string) $erroGeral;
    }
}

if ($errosValidacao !== null) {
    if (is_array($errosValidacao)) {
        $erroLista = array_merge($erroLista, $errosValidacao);
    } else {
        if ($erroTexto === null) {
            $erroTexto = (string) $errosValidacao;
        } else {
            $erroLista[] = (string) $errosValidacao;
        }
    }
}

$temErro = ($erroTexto !== null) || (! empty($erroLista));

$alertas = [
    [
        'exibir' => $sucesso !== null,
        'classe' => 'success',
        'icone'  => 'mdi-check-circle',
        'texto'  => is_array($sucesso) ? null : (string) $sucesso,
        'lista'  => is_array($sucesso) ? $sucesso : [],
    ],
    [
        'exibir' => $temErro,
        'classe' => 'danger',
        'icone'  => 'mdi-alert-circle',
        'texto'  => $erroTexto,
        'lista'  => $erroLista,
    ],
    [
        'exibir' => $aviso !== null,
        'classe' => 'warning',
        'icone'  => 'mdi-alert',
        'texto'  => is_array($aviso) ? null : (string) $aviso,
        'lista'  => is_array($aviso) ? $aviso : [],
    ],
    [
        'exibir' => $info !== null,
        'classe' => 'info',
        'icone'  => 'mdi-information-outline',
        'texto'  => is_array($info) ? null : (string) $info,
        'lista'  => is_array($info) ? $info : [],
    ],
];
?>
<?php foreach ($alertas as $alerta) : ?>
    <?php if ($alerta['exibir']) : ?>
        <div class="alert alert-<?= $alerta['classe'] ?> alert-dismissible fade show d-flex align-items-center" role="alert" aria-live="polite">
            <i class="mdi <?= $alerta['icone'] ?> me-2 fs-5" aria-hidden="true"></i>
            <div class="flex-grow-1">
                <?php if ($alerta['texto'] !== null && $alerta['texto'] !== '') : ?>
                    <?php if (! empty($alerta['lista'])) : ?>
                        <div class="mb-1"><?= esc($alerta['texto']) ?></div>
                    <?php else : ?>
                        <?= esc($alerta['texto']) ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (! empty($alerta['lista'])) : ?>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($alerta['lista'] as $item) : ?>
                            <li><?= esc($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
