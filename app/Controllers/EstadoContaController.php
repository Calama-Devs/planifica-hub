<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EstadoContaService;
use CodeIgniter\HTTP\ResponseInterface;

class EstadoContaController extends BaseController
{
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
        $senhaAtual = $this->request->getPost('senha_atual');
        $entrada = ['conta_id' => $contaId, 'senha_atual' => $senhaAtual];
        $regras = [
            'conta_id' => 'required|regex_match[/^[1-9][0-9]{0,9}$/]|less_than_equal_to[4294967295]',
            'senha_atual' => 'required|max_length[4096]',
        ];

        if (! is_string($senhaAtual) || ! $this->validateData($entrada, $regras)) {
            return $this->responder([
                'sucesso' => false,
                'tipo' => 'validacao',
                'codigo' => 'CONTA_ENTRADA_INVALIDA',
                'mensagem' => 'Informe um identificador válido e sua senha atual.',
            ]);
        }

        /** @var EstadoContaService $servico */
        $servico = service('estadoContaService');
        $resultado = $ativar
            ? $servico->reativar((int) $contaId, (int) auth()->id(), $senhaAtual)
            : $servico->desativar((int) $contaId, (int) auth()->id(), $senhaAtual);

        return $this->responder($resultado);
    }

    /** @param array{sucesso: bool, tipo: string, codigo: string, mensagem: string} $resultado */
    private function responder(array $resultado): ResponseInterface
    {
        $status = $resultado['sucesso'] ? 200 : match (true) {
            $resultado['codigo'] === 'CONTA_NAO_ENCONTRADA' => 404,
            $resultado['tipo'] === 'validacao' => 422,
            $resultado['tipo'] === 'permissao' => 403,
            $resultado['tipo'] === 'regra_negocio' => 409,
            default => 500,
        };

        $resultado['csrf'] = ['nome' => csrf_token(), 'hash' => csrf_hash()];

        return $this->response->setStatusCode($status)->setJSON($resultado);
    }
}
