<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\HTTP\RedirectResponse;

/** Apresentação da administração de contas de usuário. */
class UsuarioController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $busca = $this->request->getGet('busca');

        if ($busca !== null && ! is_string($busca)) {
            return redirect()->to(url_to('usuarios.index'))->with('error', 'O filtro de pesquisa é inválido.');
        }

        $busca = is_string($busca) ? trim($busca) : '';

        if (mb_strlen($busca) > 100) {
            return redirect()->to(url_to('usuarios.index'))
                ->with('error', 'A pesquisa deve ter no máximo 100 caracteres.');
        }

        $dados = service('usuarioService')->listar((int) auth()->id(), $busca);
        $dados['busca'] = $busca;

        return view('usuario/index', $dados);
    }

    public function novo(): string
    {
        return view('usuario/novo', [
            'grupos' => service('usuarioService')->gruposConcediveis((int) auth()->id()),
        ]);
    }

    public function criar(): RedirectResponse
    {
        $configuracaoAuth = config('Auth');
        $regraTamanhoSenha = Passwords::getMaxLengthRule();
        $regras = [
            'nome' => [
                'rules'  => $configuracaoAuth->usernameValidationRules['rules'],
                'errors' => [
                    'required'    => 'Informe o nome de usuário.',
                    'min_length'  => 'O nome deve ter pelo menos 3 caracteres.',
                    'max_length'  => 'O nome deve ter no máximo 30 caracteres.',
                    'regex_match' => 'Use somente letras, números e ponto no nome de usuário.',
                ],
            ],
            'email' => [
                'rules'  => $configuracaoAuth->emailValidationRules['rules'],
                'errors' => [
                    'required'    => 'Informe o e-mail.',
                    'valid_email' => 'Informe um e-mail válido.',
                    'max_length'  => 'O e-mail deve ter no máximo 254 caracteres.',
                ],
            ],
            'senha' => [
                'rules'  => array_merge(
                    ['required', 'min_length[' . $configuracaoAuth->minimumPasswordLength . ']'],
                    explode('|', $regraTamanhoSenha),
                ),
                'errors' => [
                    'required'   => 'Informe uma senha.',
                    'min_length' => 'A senha deve ter pelo menos 8 caracteres.',
                    'max_byte'   => 'A senha excede o tamanho aceito pelo sistema.',
                    'max_length' => 'A senha excede o tamanho aceito pelo sistema.',
                ],
            ],
            'grupo' => [
                'rules'  => 'required|max_length[50]',
                'errors' => [
                    'required'   => 'Selecione um grupo.',
                    'max_length' => 'O grupo informado é inválido.',
                ],
            ],
        ];

        if (! $this->validate($regras)) {
            if (array_key_exists('grupo', $this->validator->getErrors())) {
                service('usuarioService')->registrarGrupoRecusado((int) auth()->id());
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $resultado = service('usuarioService')->criar(
            $this->validator->getValidated(),
            (int) auth()->id(),
        );

        if (! $resultado['sucesso']) {
            $redirecionamento = redirect()->back()->withInput();

            if ($resultado['codigo'] === 'CONTA_DUPLICADA') {
                return $redirecionamento->with('errors', [
                    'nome'  => 'Já existe uma conta com este nome ou e-mail.',
                    'email' => 'Já existe uma conta com este nome ou e-mail.',
                ]);
            }

            if ($resultado['codigo'] === 'GRUPO_NAO_CONCEDIVEL') {
                return $redirecionamento->with('errors', ['grupo' => $resultado['mensagem']]);
            }

            if (isset($resultado['erros'])) {
                return $redirecionamento->with('errors', $resultado['erros']);
            }

            return $redirecionamento->with('error', $resultado['mensagem']);
        }

        return redirect()->to(url_to('usuarios.index'))->with('success', $resultado['mensagem']);
    }
}
