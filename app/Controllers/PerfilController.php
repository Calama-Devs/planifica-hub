<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;

class PerfilController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return redirect()->to(url_to('login'));
        }

        return view('perfil/index', [
            'nomeAtual'         => $usuario->username ?? '',
            'emailAtual'        => $usuario->email ?? '',
            'comprimentoMaximo' => service('perfilService')->obterComprimentoMaximoNome(),
        ]);
    }

    public function atualizar(): RedirectResponse
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return redirect()->to(url_to('login'));
        }

        $nome = $this->request->getPost('nome');

        if (! is_string($nome)) {
            return redirect()->back()->with('errors', [
                'nome' => 'Informe um nome válido.',
            ]);
        }

        $resultado = service('perfilService')->atualizarNome($usuario, $nome);

        if (! $resultado['sucesso']) {
            $redirecionamento = redirect()->back()->withInput();

            if (isset($resultado['erros'])) {
                return $redirecionamento->with('errors', $resultado['erros']);
            }

            return $redirecionamento->with('error', $resultado['mensagem']);
        }

        return redirect()->to(url_to('perfil'))->with('success', $resultado['mensagem']);
    }
}
