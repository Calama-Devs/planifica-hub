<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;

class ContaAtivaFilter implements FilterInterface
{
    /** @return ResponseInterface|null */
    public function before(RequestInterface $request, $arguments = null)
    {
        $filtroSessao = new \CodeIgniter\Shield\Filters\SessionAuth();
        $resposta     = $filtroSessao->before($request, $arguments);

        if ($resposta instanceof ResponseInterface) {
            return $resposta;
        }

        /** @var Session $autenticador */
        $autenticador = auth('session')->getAuthenticator();
        $usuario      = $autenticador->getUser();

        if (! $usuario instanceof User) {
            return null;
        }

        $usuarioAtual = auth()->getProvider()->findById($usuario->id);

        if ($usuarioAtual instanceof User && $usuarioAtual->active) {
            return null;
        }

        $mensagem = 'Sua conta está inativa e sua sessão foi encerrada. '
            . 'Entre em contato com o administrador para reativação do acesso.';

        $autenticador->logout();

        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            session()->setFlashdata('error', $mensagem);

            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'sucesso'         => false,
                    'tipo'            => 'permissao',
                    'codigo'          => 'USUARIO_INDISPONIVEL',
                    'mensagem'        => $mensagem,
                    'redirecionamento' => url_to('login'),
                ]);
        }

        return redirect()->to(config('Auth')->logoutRedirect())
            ->with('error', $mensagem);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
