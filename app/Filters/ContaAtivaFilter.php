<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Filters\SessionAuth;

class ContaAtivaFilter extends SessionAuth
{
    /** @return ResponseInterface|null */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        /** @var Session $autenticador */
        $autenticador = auth('session')->getAuthenticator();
        $usuario = $autenticador->getUser() ?? $autenticador->getPendingUser();

        if ($usuario !== null) {
            $conta = auth()->getProvider()->findById($usuario->id);

            if ($conta === null || ! $conta->active) {
                $mensagem = 'Sua conta está inativa e sua sessão foi encerrada. '
                    . 'Entre em contato com o administrador para reativação do acesso.';
                $autenticador->logout();
                session()->setFlashdata('error', $mensagem);

                if ($request->isAJAX()) {
                    return service('response')->setStatusCode(403)->setJSON([
                        'sucesso' => false,
                        'tipo' => 'permissao',
                        'codigo' => 'USUARIO_INDISPONIVEL',
                        'mensagem' => $mensagem,
                        'redirecionamento' => url_to('login'),
                    ]);
                }

                return redirect()->to(config('Auth')->logoutRedirect());
            }
        }

        return parent::before($request, $arguments);
    }
}
