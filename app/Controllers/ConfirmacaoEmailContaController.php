<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ConfirmacaoEmailContaService;

class ConfirmacaoEmailContaController extends BaseController
{
    private ConfirmacaoEmailContaService $confirmacaoEmailContaService;

    public function __construct()
    {
        $this->confirmacaoEmailContaService = service('confirmacaoEmailContaService');
    }

    public function formulario(): string
    {
        $token = $this->request->getGet('token');
        $token = is_string($token) && mb_strlen($token) <= 128 ? $token : '';
        $this->response->setHeader('Cache-Control', 'no-store')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');

        return view('conta/confirmar_email', ['token' => $token, 'resultado' => null]);
    }

    public function confirmar(): string
    {
        $token = $this->request->getPost('token');
        $resultado = is_string($token)
            ? $this->confirmacaoEmailContaService->confirmar($token)
            : [
                'sucesso' => false,
                'tipo' => 'validacao',
                'codigo' => 'TOKEN_EMAIL_INVALIDO',
                'mensagem' => 'O link de confirmação é inválido ou expirou.',
            ];
        $status = match ([$resultado['sucesso'], $resultado['tipo']]) {
            [true, 'sucesso'] => 200,
            [false, 'inesperado'] => 500,
            [false, 'regra_negocio'] => 409,
            default => 422,
        };
        $this->response->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');

        return view('conta/confirmar_email', ['token' => '', 'resultado' => $resultado]);
    }
}
