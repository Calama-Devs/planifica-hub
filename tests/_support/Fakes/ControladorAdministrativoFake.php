<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Controllers\Traits\RespostaAdministrativaTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Expõe o método protegido do trait de resposta administrativa, para testá-lo sem depender de uma rota.
 */
final class ControladorAdministrativoFake
{
    use RespostaAdministrativaTrait;

    public function __construct(public ResponseInterface $response)
    {
    }

    /** @param array<string, mixed> $resultado */
    public function responder(array $resultado, int $statusDaFalha): ResponseInterface
    {
        return $this->responderOperacaoAdministrativa($resultado, $statusDaFalha);
    }
}
