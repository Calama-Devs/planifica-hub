<?php

namespace App\Controllers;

use App\Services\RedefinirSenhaService;
use CodeIgniter\HTTP\RedirectResponse;

class RedefinirSenhaController extends BaseController
{
    private RedefinirSenhaService $redefinirSenhaService;

    public function __construct()
    {
        $this->redefinirSenhaService = new RedefinirSenhaService();
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

        return view('redefinir_senha/redefinir');
    }

    public function iniciarPeloPerfil(): RedirectResponse
    {
        session()->removeTempdata('magicLogin');
        session()->removeTempdata('beforeLoginUrl');
        auth()->logout();

        return redirect()->to(site_url('forgot-password'));
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
