<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\AutenticacaoSessaoService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Filters\SessionAuth;

class ContaAtivaFilter extends SessionAuth
{
    /** @return ResponseInterface|null */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        /** @var AutenticacaoSessaoService $autenticador */
        $autenticador = auth('session')->getAuthenticator();
        $usuario = $autenticador->getUser() ?? $autenticador->getPendingUser();

        if ($usuario !== null) {
            $conta = auth()->getProvider()->findById($usuario->id);
            $inativa = $conta === null || ! $conta->active;

            if ($inativa || ! $autenticador->sessaoTemVersaoAtual($conta)) {
                $autenticador->encerrarSessaoRevogada();

                return $this->responderSessaoEncerrada($request, $inativa);
            }
        }

        return parent::before($request, $arguments);
    }

    private function responderSessaoEncerrada(IncomingRequest $request, bool $inativa): ResponseInterface
    {
        $mensagem = $inativa
            ? 'Sua conta está inativa e sua sessão foi encerrada. '
                . 'Entre em contato com o administrador para reativação do acesso.'
            : 'Sua sessão foi encerrada. Entre novamente para continuar.';
        session()->setFlashdata('error', $mensagem);

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON([
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => $inativa ? 'USUARIO_INDISPONIVEL' : 'SESSAO_REVOGADA',
                'mensagem' => $mensagem,
                'redirecionamento' => url_to('login'),
            ]);
        }

        return redirect()->to(config('Auth')->logoutRedirect());
    }
}
