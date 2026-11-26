<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Traits\RespostaAdministrativaTrait;
use App\Services\EstadoContaService;
use CodeIgniter\HTTP\ResponseInterface;

class EstadoContaController extends BaseController
{
    use RespostaAdministrativaTrait;

    public function desativar(string $contaId): ResponseInterface
    {
        return $this->alterarEstado($contaId, false);
    }

    public function reativar(string $contaId): ResponseInterface
    {
        return $this->alterarEstado($contaId, true);
    }

    private function alterarEstado(string $contaId, bool $ativar): ResponseInterface
    {
        $regras = ['conta_id' => 'required|regex_match[/^[1-9][0-9]{0,9}$/]|less_than_equal_to[4294967295]'];

        if (! $this->validateData(['conta_id' => $contaId], $regras)) {
            return $this->responder([
                'sucesso' => false,
                'tipo' => 'validacao',
                'codigo' => 'CONTA_ENTRADA_INVALIDA',
                'mensagem' => 'Informe um identificador de conta válido.',
            ]);
        }

        // A senha vem só do corpo do POST, nunca da URL. Ausente ou fora do formato, a confirmação a recusa.
        $senhaAdmin = $this->request->getPost('senha_admin');
        $senhaAdmin = is_string($senhaAdmin) ? $senhaAdmin : '';

        /** @var EstadoContaService $servico */
        $servico = service('estadoContaService');
        $resultado = $ativar
            ? $servico->reativar((int) $contaId, $senhaAdmin)
            : $servico->desativar((int) $contaId, $senhaAdmin);

        return $this->responder($resultado);
    }

    /** @param array{sucesso: bool, tipo: string, codigo: string, mensagem: string} $resultado */
    private function responder(array $resultado): ResponseInterface
    {
        return $this->responderOperacaoAdministrativa($resultado, match (true) {
            $resultado['codigo'] === 'CONTA_NAO_ENCONTRADA' => 404,
            $resultado['tipo'] === 'validacao' => 422,
            $resultado['tipo'] === 'permissao' => 403,
            $resultado['tipo'] === 'regra_negocio' => 409,
            default => 500,
        });
    }
}
