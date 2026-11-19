<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FinalizarCadastroService;
use CodeIgniter\HTTP\RedirectResponse;

class FinalizarCadastroController extends BaseController
{
    private FinalizarCadastroService $finalizarCadastroService;

    public function __construct()
    {
        /** @var FinalizarCadastroService $finalizarCadastroService */
        $finalizarCadastroService       = service('finalizarCadastroService');
        $this->finalizarCadastroService = $finalizarCadastroService;
    }

    /**
     * Exibe a interface de finalização de cadastro conforme o estado do convite.
     */
    public function index(): string
    {
        $tokenParam = $this->request->getGet('token');
        $token      = is_string($tokenParam) ? trim($tokenParam) : null;

        $resultado = $this->finalizarCadastroService->validarConvite($token);

        return view('finalizarcadastro/index', [
            'estado'       => $resultado['estado'],
            'mensagem'     => $resultado['mensagem'],
            'dadosConvite' => $resultado['dados'],
        ]);
    }

    /**
     * Processa o formulário de finalização de cadastro.
     */
    public function concluir(): RedirectResponse
    {
        $comprimentoMinimo = (string) config('Auth')->minimumPasswordLength;

        $regras = [
            'token'            => 'required',
            'nome_completo'    => 'required|min_length[3]|max_length[100]|regex_match[/^[\p{L}\s\.\'\-]+$/u]',
            'password'         => 'required|min_length[' . $comprimentoMinimo . ']',
            'password_confirm' => 'required|matches[password]',
        ];

        $mensagens = [
            'nome_completo' => [
                'required'    => 'O campo Nome completo é obrigatório.',
                'min_length'  => 'O Nome completo deve ter pelo menos 3 caracteres.',
                'max_length'  => 'O Nome completo não pode ultrapassar 100 caracteres.',
                'regex_match' => 'O Nome completo contém caracteres inválidos. '
                    . 'Utilize apenas letras, espaços e acentos.',
            ],
            'password' => [
                'required'   => 'O campo Senha é obrigatório.',
                'min_length' => 'A senha deve ter pelo menos ' . $comprimentoMinimo . ' caracteres.',
            ],
            'password_confirm' => [
                'required' => 'O campo Confirmar senha é obrigatório.',
                'matches'  => 'As senhas informadas não correspondem.',
            ],
            'token' => [
                'required' => 'O identificador do convite é obrigatório.',
            ],
        ];

        if (! $this->validate($regras, $mensagens)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dadosValidados = [
            'token'            => (string) $this->request->getPost('token'),
            'nome_completo'    => trim((string) $this->request->getPost('nome_completo')),
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];

        $resultado = $this->finalizarCadastroService->concluirCadastro($dadosValidados);

        if (! $resultado['sucesso']) {
            return redirect()->back()->withInput()->with('error', $resultado['mensagem']);
        }

        return redirect()->route('login')->with('message', $resultado['mensagem']);
    }
}
