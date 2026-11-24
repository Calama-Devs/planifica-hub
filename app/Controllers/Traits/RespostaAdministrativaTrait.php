<?php

declare(strict_types=1);

namespace App\Controllers\Traits;

use CodeIgniter\HTTP\ResponseInterface;
use Config\ConfirmacaoAdministrativa;

/**
 * Resposta JSON padrão dos endpoints de operações administrativas que exigem a confirmação de senha.
 *
 * Todos os endpoints devolvem o mesmo formato à modal de confirmação: o retorno estruturado do Service
 * (`sucesso`, `tipo`, `codigo`, `mensagem`), o novo token CSRF e cabeçalhos que impedem o cache. A senha nunca
 * é devolvida.
 */
trait RespostaAdministrativaTrait
{
    /**
     * O status das recusas da confirmação (sessão, permissão, senha, limite de tentativas) vem do contrato em
     * `Config\ConfirmacaoAdministrativa`; as demais falhas usam `$statusDaFalha`, definido pela operação.
     *
     * @param array<string, mixed> $resultado
     */
    protected function responderOperacaoAdministrativa(array $resultado, int $statusDaFalha): ResponseInterface
    {
        if (($resultado['sucesso'] ?? false) === true) {
            $status = 200;
        } else {
            $codigo = (string) ($resultado['codigo'] ?? '');
            $status = config(ConfirmacaoAdministrativa::class)->statusDaRecusa($codigo) ?? $statusDaFalha;
        }

        // O token é regenerado a cada envio: a modal precisa do novo valor para a próxima requisição.
        $resultado['csrf'] = ['nome' => csrf_token(), 'hash' => csrf_hash()];
        $resultado['csrf_hash'] = csrf_hash();

        $resposta = $this->response->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($resultado);

        if (isset($resultado['espera_segundos'])) {
            $resposta->setHeader('Retry-After', (string) $resultado['espera_segundos']);
        }

        return $resposta;
    }
}
