<?php

namespace App\Controllers;

use App\Services\RedefinirSenhaService;
use CodeIgniter\HTTP\RedirectResponse;

class RedefinirSenhaController extends BaseController
{
    private RedefinirSenhaService $redefinirSenhaService;

    public function __construct()
    {
        /** @var RedefinirSenhaService $redefinirSenhaService */
        $redefinirSenhaService       = service('redefinirSenhaService');
        $this->redefinirSenhaService = $redefinirSenhaService;
    }

    public function solicitar(): string|RedirectResponse
    {
        return view('redefinir_senha/solicitar');
    }

    public function redefinir(): string|RedirectResponse
    {
        if (!$this->possuiAcessoTemporario()) {
            return redirect()->route('magic-link');
        }

        return view('redefinir_senha/redefinir', [
            'acaoFormulario'   => site_url('reset-password'),
            'exigirSenhaAtual' => false,
            'titulo'            => 'Redefinição de senha',
            'textoOrientacao'   => 'Defina uma nova senha para concluir a recuperação da sua conta.',
            'textoBotao'        => 'Salvar nova senha',
        ]);
    }

    public function alterarPeloPerfil(): string
    {
        return view('redefinir_senha/redefinir', [
            'acaoFormulario'   => site_url('perfil/alterar-senha'),
            'exigirSenhaAtual' => true,
            'titulo'            => 'Alteração de senha',
            'textoOrientacao'   => 'Confirme sua senha atual e defina uma nova senha para sua conta.',
            'textoBotao'        => 'Alterar senha',
        ]);
    }

    public function atualizar(): RedirectResponse
    {
        if (!$this->possuiAcessoTemporario()) {
            return redirect()->route('magic-link');
        }

        $usuario = auth()->user();

        if ($usuario === null || (int) $usuario->active !== 1) {
            $this->encerrarSessaoTemporaria();

            return redirect()
                ->route('magic-link')
                ->with('error', 'Usuario nao encontrado ou desativado.');
        }

        $resultado = $this->redefinirSenhaService->redefinir(
            $usuario,
            (string) $this->request->getPost('senha-nova'),
            (string) $this->request->getPost('confirmar-senha-nova')
        );

        if (!$resultado['sucesso']) {
            return redirect()->back()->with('error', $resultado['mensagem']);
        }

        $this->encerrarSessaoTemporaria();

        return redirect()
            ->route('login')
            ->with('success', 'Senha redefinida com sucesso. Acesse com sua nova senha.');
    }

    public function atualizarPeloPerfil(): RedirectResponse
    {
        $usuario = auth()->user();

        if ($usuario === null || (int) $usuario->active !== 1) {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('error', 'Usuario não encontrado ou desativado.');
        }

        $resultado = $this->redefinirSenhaService->alterarPeloPerfil(
            $usuario,
            (string) $this->request->getPost('senha-atual'),
            (string) $this->request->getPost('senha-nova'),
            (string) $this->request->getPost('confirmar-senha-nova')
        );

        if (!$resultado['sucesso']) {
            return redirect()->back()->with('error', $resultado['mensagem']);
        }

        auth()->logout();

        return redirect()
            ->route('login')
            ->with('success', 'Senha alterada com sucesso. Acesse com sua nova senha.');
    }

    private function possuiAcessoTemporario(): bool
    {
        return auth()->loggedIn() && session()->getTempdata('magicLogin') === true;
    }

    private function encerrarSessaoTemporaria(): void
    {
        session()->removeTempdata('magicLogin');
        session()->removeTempdata('beforeLoginUrl');
        auth()->logout();
    }
}
