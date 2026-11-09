<?php

namespace App\Controllers;

use App\Services\RedefinirSenhaService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

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
        return view('redefinirsenha/solicitar');
    }

    public function redefinir(): string|RedirectResponse
    {
        if (!$this->possuiAcessoTemporario()) {
            return redirect()->route('magic-link');
        }

        return view('redefinirsenha/redefinir');
    }

    public function cancelar(): RedirectResponse
    {
        if (!$this->possuiAcessoTemporario()) {
            return redirect()->to(site_url('/'));
        }

        $this->encerrarSessaoTemporaria();

        return redirect()->route('login');
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
                ->with('error', 'Usuário não encontrado ou desativado.');
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

    public function atualizarPeloPerfil(): ResponseInterface
    {
        if ($this->possuiAcessoTemporario() || session()->get('redefinicaoSenhaPendente') === true) {
            return $this->responderPerfil([
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => 'SESSAO_TEMPORARIA',
                'mensagem' => 'Utilize a página de recuperação de senha.',
            ], 403);
        }

        $usuario = auth()->user();

        if ($usuario === null || (int) $usuario->active !== 1) {
            auth()->logout();

            return $this->responderPerfil([
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => 'USUARIO_INDISPONIVEL',
                'mensagem' => 'Sessão indisponível. Entre novamente.',
            ], 403);
        }

        $resultado = $this->redefinirSenhaService->alterarPeloPerfil(
            $usuario,
            (string) $this->request->getPost('senha-atual'),
            (string) $this->request->getPost('senha-nova'),
            (string) $this->request->getPost('confirmar-senha-nova')
        );

        if (!$resultado['sucesso']) {
            return $this->responderPerfil($resultado, $resultado['tipo'] === 'inesperado' ? 500 : 422);
        }

        auth()->logout();

        session()->setFlashdata('success', 'Senha alterada com sucesso. Acesse com sua nova senha.');

        return $this->responderPerfil($resultado + ['redirecionamento' => url_to('login')]);
    }

    /** @param array<string, mixed> $resultado */
    private function responderPerfil(array $resultado, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($resultado + [
            'csrf' => ['nome' => csrf_token(), 'hash' => csrf_hash()],
        ]);
    }

    private function possuiAcessoTemporario(): bool
    {
        return auth()->loggedIn() && session()->getTempdata('magicLogin') === true;
    }

    private function encerrarSessaoTemporaria(): void
    {
        session()->removeTempdata('magicLogin');
        session()->removeTempdata('beforeLoginUrl');
        session()->remove('redefinicaoSenhaPendente');
        auth()->logout();
    }
}
