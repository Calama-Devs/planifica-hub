<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class ConviteController extends BaseController
{
    public function index(): string
    {
        $token = $this->request->getGet('token');
        $resultado = service('conviteService')->validarConvite(is_string($token) ? $token : null);
        return view('finalizar_cadastro/index', [
            'estado'       => $resultado['estado'],
            'mensagem'     => $resultado['mensagem'],
            'dadosConvite' => $resultado['dados'],
        ]);
    }

    public function concluir(): RedirectResponse
    {
        $usuarios = model(\App\Models\UsuarioAutenticacaoModel::class);
        $senhas = new \CodeIgniter\Shield\Validation\ValidationRules();
        $dados = $this->request->getPost(['token', 'nome_completo', 'password', 'password_confirm']);
        if (is_string($dados['nome_completo'])) {
            $dados['nome_completo'] = trim($dados['nome_completo']);
        }
        $regras = $usuarios->getValidationRules(['only' => ['nome_completo']]);
        $regras += [
            'token' => 'required|regex_match[/^[a-zA-Z0-9_-]{8,128}$/]',
            'password' => $senhas->getPasswordRules(),
            'password_confirm' => $senhas->getPasswordConfirmRules(),
        ];
        if (
            ! $this->validateData($dados, $regras, $usuarios->getValidationMessages() + [
            'password_confirm' => ['matches' => 'As senhas informadas não correspondem.'],
            ])
        ) {
            $nomeCompleto = $dados['nome_completo'] ?? '';
            $this->request->setGlobal('post', [
                'nome_completo' => is_string($nomeCompleto) ? $nomeCompleto : '',
            ]);

            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }
        $dados = $this->validator->getValidated();
        $resultado = service('conviteService')->concluirCadastro($dados);
        if ($resultado['sucesso']) {
            return redirect()->route('login')->with('success', $resultado['mensagem']);
        }

        $this->request->setGlobal('post', ['nome_completo' => $dados['nome_completo']]);

        return redirect()->to(url_to('finalizar_cadastro') . '?token=' . urlencode($dados['token']))
            ->withInput()
            ->with('error', $resultado['mensagem']);
    }

    public function emitir(): ResponseInterface
    {
        $grupos = $this->request->getPost('grupos');
        $email  = trim((string) $this->request->getPost('email'));
        $convites = model(\App\Models\ConviteModel::class);

        if (
            (! is_null($grupos) && ! is_array($grupos))
            || ! $this->validateData(
                ['email' => $email],
                $convites->getValidationRules(['only' => ['email']]),
                $convites->getValidationMessages()
            )
        ) {
            return $this->responder([
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_DADOS_INVALIDOS',
                'mensagem' => 'Verifique os dados informados.',
            ], 422);
        }

        $resultado = service('conviteService')->emitirConvite(
            $this->validator->getValidated()['email'],
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
