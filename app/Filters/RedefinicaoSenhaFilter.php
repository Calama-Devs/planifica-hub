<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

class RedefinicaoSenhaFilter implements FilterInterface
{
    private const ROTAS_MAGIC_LINK = [
        'forgot-password',
        'login/magic-link',
        'login/verify-magic-link',
    ];

    private const ROTAS_PERMITIDAS_DURANTE_REDEFINICAO = [
        'reset-password',
        'logout',
    ];

    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $caminho      = $this->normalizarCaminho($request->getUri()->getPath());
        $autenticador = auth('session')->getAuthenticator();

        if (!$autenticador instanceof Session) {
            return null;
        }

        $temSessao  = $autenticador->loggedIn() || $autenticador->isPending();
        $magicLogin = session()->getTempdata('magicLogin') === true;

        if ($magicLogin && ! $autenticador->loggedIn()) {
            session()->removeTempdata('magicLogin');
            $magicLogin = false;
        }

        if ($magicLogin && ! in_array($caminho, self::ROTAS_PERMITIDAS_DURANTE_REDEFINICAO, true)) {
            return redirect()
                ->to(site_url('reset-password'))
                ->with('error', 'Conclua a redefinicao da senha antes de acessar outra pagina.');
        }

        if (!$magicLogin && $temSessao && in_array($caminho, self::ROTAS_MAGIC_LINK, true)) {
            return redirect()
                ->to(site_url('/'))
                ->with('error', 'Encerre a sessao atual antes de solicitar outro link de redefinicao.');
        }

        return null;
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ): ?ResponseInterface {
        return null;
    }

    private function normalizarCaminho(string $caminho): string
    {
        $caminho = trim($caminho, '/');

        if (str_starts_with($caminho, 'index.php/')) {
            return substr($caminho, strlen('index.php/'));
        }

        return $caminho;
    }
}
