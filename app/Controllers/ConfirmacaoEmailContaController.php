<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ConfirmacaoEmailContaService;
use CodeIgniter\HTTP\ResponseInterface;

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

    public function confirmar(): ResponseInterface
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
        $codigosLinkInvalido = ['TOKEN_EMAIL_INVALIDO', 'TOKEN_EMAIL_EXPIRADO', 'SOLICITACAO_EMAIL_DESATUALIZADA'];
        $codigosEmailNaoAceito = ['EMAIL_DUPLICADO', 'EMAIL_DOMINIO_NAO_PERMITIDO', 'EMAIL_INVALIDO'];
        $status = match (true) {
            $resultado['sucesso'] => 200,
            in_array($resultado['codigo'], $codigosLinkInvalido, true) => 410,
            in_array($resultado['codigo'], $codigosEmailNaoAceito, true) => 422,
            $resultado['tipo'] === 'inesperado' => 500,
            default => 422,
        };

        if ($status === 410) {
            $resultado['mensagem'] = 'O link de confirmação é inválido ou expirou.';
        }
        $this->response->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');

        return $this->response->setBody(view('conta/confirmar_email', ['token' => '', 'resultado' => $resultado]));
    }
}
