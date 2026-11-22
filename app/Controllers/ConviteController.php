<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class ConviteController extends BaseController
{
    public function emitir(): ResponseInterface
    {
        $grupos = $this->request->getPost('grupos');

        if (
            (! is_null($grupos) && ! is_array($grupos))
            || ! $this->validate(['email' => 'required|valid_email|max_length[254]'])
        ) {
            return $this->response->setStatusCode(422)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_DADOS_INVALIDOS',
                'mensagem' => 'Verifique os dados informados.',
            ]);
        }

        $resultado = service('conviteService')->emitirConvite(
            (string) $this->request->getPost('email'),
            (int) auth()->id(),
            is_array($grupos) ? $grupos : []
        );

        $statusHttp = $resultado['sucesso'] ? 201 : match ($resultado['tipo']) {
            'permissao' => 403,
            'inesperado' => 500,
            default => 422,
        };

        $resultado['csrf'] = [
            'nome' => csrf_token(),
            'hash' => csrf_hash(),
        ];

        return $this->response->setStatusCode($statusHttp)->setJSON($resultado);
    }
}
