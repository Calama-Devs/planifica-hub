<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoService;
use CodeIgniter\HTTP\RedirectResponse;

class PermissaoController extends BaseController
{
    private PermissaoService $permissaoService;

    public function __construct()
    {
        $this->permissaoService = service('permissaoService');
    }

    /**
     * Exibe a listagem de permissões do sistema.
     */
    public function index(): string
    {
        $permissoes = $this->permissaoService->listarPermissoes();

        return view('permissoes/index', [
            'permissoes' => $permissoes,
        ]);
    }

    /**
     * Exibe o formulário de cadastro de nova permissão.
     */
    public function nova(): string
    {
        return view('permissoes/formulario', [
            'modo'      => 'cadastro',
            'permissao' => null,
        ]);
    }

    /**
     * Processa a criação de uma nova permissão.
     */
    public function salvar(): RedirectResponse
    {
        $regras = [
            'identificador' => [
                'rules'  => 'required|regex_match[/^[a-z]+(_[a-z]+)*\.[a-z]+(_[a-z]+)*$/]|max_length[100]',
                'errors' => [
                    'required'    => 'O identificador é obrigatório.',
                    'regex_match' => 'O identificador deve seguir o formato contexto.acao, '
                        . 'em minúsculas e sem acentos (ex.: relatorios.emitir).',
                    'max_length'  => 'O identificador não pode exceder 100 caracteres.',
                ],
            ],
            'descricao' => [
                'rules'  => 'required|min_length[5]|max_length[255]',
                'errors' => [
                    'required'   => 'A descrição é obrigatória.',
                    'min_length' => 'A descrição deve ter no mínimo 5 caracteres.',
                    'max_length' => 'A descrição não pode exceder 255 caracteres.',
                ],
            ],
        ];

        if (! $this->validate($regras)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $usuarioId = (int) auth()->id();
        $resultado = $this->permissaoService->cadastrarPermissao($this->validator->getValidated(), $usuarioId);

        if (! $resultado['sucesso']) {
            return redirect()->back()->withInput()->with('error', $resultado['mensagem']);
        }

        return redirect()->to(url_to('permissoes.index'))->with('success', $resultado['mensagem']);
    }

    /**
     * Exibe o formulário para edição da descrição de uma permissão.
     */
    public function editar(int $id): string|RedirectResponse
    {
        $permissao = $this->permissaoService->buscarPorId($id);

        if ($permissao === null) {
            return redirect()->to(url_to('permissoes.index'))->with('error', 'Permissão não encontrada.');
        }

        return view('permissoes/formulario', [
            'modo'      => 'edicao',
            'permissao' => $permissao,
        ]);
    }

    /**
     * Processa a atualização da descrição de uma permissão.
     */
    public function atualizar(int $id): RedirectResponse
    {
        $regras = [
            'descricao' => [
                'rules'  => 'required|min_length[5]|max_length[255]',
                'errors' => [
                    'required'   => 'A descrição é obrigatória.',
                    'min_length' => 'A descrição deve ter no mínimo 5 caracteres.',
                    'max_length' => 'A descrição não pode exceder 255 caracteres.',
                ],
            ],
        ];

        if (! $this->validate($regras)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dados = [
            'descricao' => (string) $this->request->getPost('descricao'),
        ];

        // Se o identificador foi enviado para permissão customizada
        $identificador = $this->request->getPost('identificador');
        if ($identificador !== null) {
            $dados['identificador'] = (string) $identificador;
        }

        $usuarioId = (int) auth()->id();
        $resultado = $this->permissaoService->atualizarPermissao($id, $dados, $usuarioId);

        if (! $resultado['sucesso']) {
            return redirect()->back()->withInput()->with('error', $resultado['mensagem']);
        }

        return redirect()->to(url_to('permissoes.index'))->with('success', $resultado['mensagem']);
    }
}
