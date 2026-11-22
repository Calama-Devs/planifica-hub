<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class ConviteController extends BaseController
{
    public function emitir(): ResponseInterface
    {
        if (! $this->validate(['email' => 'required|valid_email|max_length[254]'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_DADOS_INVALIDOS',
                'mensagem' => 'Verifique os dados informados.',
            ]);
        }

        $resultado = service('conviteService')->emitirConvite(
            (string) $this->request->getPost('email'),
            (int) auth()->id()
        );

        $statusHttp = $resultado['sucesso'] ? 201 : match ($resultado['tipo']) {
            'permissao' => 403,
            'inesperado' => 500,
            default => 422,
        };

        return $this->response->setStatusCode($statusHttp)->setJSON($resultado);
    }

    public function finalizarCadastro(): string
    {
        $token     = $this->request->getGet('token');
        $resultado = service('conviteService')->validarConvite(is_string($token) ? $token : null);

        return view('convites/finalizar', [
            'token'     => is_string($token) ? trim($token) : '',
            'resultado' => $resultado,
        ]);
    }

    public function concluirCadastro(): RedirectResponse
    {
        $comprimentoMinimo = (string) config('Auth')->minimumPasswordLength;
        $regras            = [
            'token'            => 'required|regex_match[/^[A-Za-z0-9_-]{43}$/]',
            'nome_completo'    => "required|max_length[150]|regex_match[/^\\p{L}+(?:[ '\\-]\\p{L}+)+$/u]",
            'password'         => 'required|min_length[' . $comprimentoMinimo . ']',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($regras)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $token     = (string) $this->request->getPost('token');
        $resultado = service('conviteService')->concluirCadastro(
            $token,
            (string) $this->request->getPost('password'),
            (string) $this->request->getPost('nome_completo')
        );

        if (! $resultado['sucesso']) {
            return redirect()->to(route_to('finalizar_cadastro') . '?token=' . rawurlencode($token))
                ->with('error', $resultado['mensagem']);
        }

        return redirect()->route('login')->with('success', $resultado['mensagem']);
    }
}
