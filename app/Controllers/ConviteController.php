<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class ConviteController extends BaseController
{
    public function emitir(): ResponseInterface
    {
        $grupos = $this->request->getPost('grupos');
        $email  = trim((string) $this->request->getPost('email'));

        if (
            (! is_null($grupos) && ! is_array($grupos))
            || ! $this->validateData(['email' => $email], ['email' => 'required|valid_email|max_length[254]'])
        ) {
            return $this->responder([
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_DADOS_INVALIDOS',
                'mensagem' => 'Verifique os dados informados.',
            ], 422);
        }

        $resultado = service('conviteService')->emitirConvite(
            $email,
            is_array($grupos) ? $grupos : []
        );

        $statusHttp = $resultado['sucesso'] ? 201 : match ($resultado['tipo']) {
            'permissao' => 403,
            'inesperado' => 500,
            default => 422,
        };

        return $this->responder($resultado, $statusHttp);
    }

    /** @param array<string, mixed> $resultado */
    private function responder(array $resultado, int $statusHttp): ResponseInterface
    {
        $resultado['csrf'] = [
            'nome' => csrf_token(),
            'hash' => csrf_hash(),
        ];

        return $this->response->setStatusCode($statusHttp)->setJSON($resultado);
    }
}
