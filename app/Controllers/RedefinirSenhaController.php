<?php

namespace App\Controllers;

use App\Services\RedefinirSenhaService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class RedefinirSenhaController extends BaseController
{
    private RedefinirSenhaService $redefinirSenhaService;

    public function __construct()
    {
        /** @var RedefinirSenhaService $redefinirSenhaService */
        $redefinirSenhaService       = service('redefinirSenhaService');
        $this->redefinirSenhaService = $redefinirSenhaService;
    }

    public function solicitar(): string
    {
        return view('redefinirsenha/solicitar', ['rotaEnvio' => 'enviar-redefinicao']);
    }

    public function enviar(): RedirectResponse
    {
        if (! $this->validate(['email' => 'required|valid_email'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->redefinirSenhaService->solicitarRedefinicao((string) $this->request->getPost('email'));

        return redirect()->route('confirmacao-redefinicao');
    }

    public function confirmacao(): string
    {
        return view('redefinirsenha/solicitacao_recebida');
    }

    public function verificar(): RedirectResponse
    {
        $resultado = $this->redefinirSenhaService->validarTokenRedefinicao(
            $this->request->getGet('token')
        );

        if (! $resultado['sucesso']) {
            return redirect()
                ->route('solicitar-redefinicao')
                ->with('error', 'Não foi possível concluir a redefinição de senha. Solicite um novo link.');
        }

        /** @var \CodeIgniter\Shield\Entities\User $usuario */
        $usuario = $resultado['usuario'];
        session()->setTempdata(
            'redefinicaoSenhaUsuarioId',
            (int) $usuario->id,
            setting('Auth.magicLinkLifetime')
        );

        return redirect()->to(site_url('reset-password'));
    }

    public function redefinir(): string|RedirectResponse
    {
        if ($this->buscarUsuarioRedefinicao() === null) {
            $this->removerAutorizacaoTemporaria();

            return redirect()->route('solicitar-redefinicao');
        }

        return view('redefinirsenha/redefinir');
    }

    public function cancelar(): RedirectResponse
    {
        if (session()->getTempdata('redefinicaoSenhaUsuarioId') === null) {
            return redirect()->to(site_url('/'));
        }

        $this->removerAutorizacaoTemporaria();

        return redirect()->route('login');
    }

    public function atualizar(): RedirectResponse
    {
        $usuario = $this->buscarUsuarioRedefinicao();

        if ($usuario === null) {
            $this->removerAutorizacaoTemporaria();

            return redirect()
                ->route('solicitar-redefinicao')
                ->with('error', 'Não foi possível concluir a redefinição de senha. Solicite um novo link.');
        }

        $resultado = $this->redefinirSenhaService->redefinir(
            $usuario,
            (string) $this->request->getPost('senha-nova'),
            (string) $this->request->getPost('confirmar-senha-nova')
        );

        if (!$resultado['sucesso']) {
            return redirect()->back()->with('error', $resultado['mensagem']);
        }

        $this->removerAutorizacaoTemporaria();

        return redirect()
            ->route('login')
            ->with('success', 'Senha redefinida com sucesso. Acesse com sua nova senha.');
    }

    public function atualizarPeloPerfil(): ResponseInterface
    {
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

    private function buscarUsuarioRedefinicao(): ?User
    {
        $usuarioId = session()->getTempdata('redefinicaoSenhaUsuarioId');

        if (! is_int($usuarioId) || $usuarioId < 1) {
            return null;
        }

        /** @var UserModel $modeloUsuario */
        $modeloUsuario = model(UserModel::class);

        $usuario = $modeloUsuario->findById($usuarioId);

        if ($usuario === null || (int) $usuario->active !== 1) {
            return null;
        }

        return $usuario;
    }

    private function removerAutorizacaoTemporaria(): void
    {
        session()->removeTempdata('redefinicaoSenhaUsuarioId');
    }
}
