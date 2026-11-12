<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

class RedefinicaoSenhaFilter implements FilterInterface
{
    private const ROTAS_RECUPERACAO = [
        'recuperar-senha',
        'recuperar-senha/verificar',
    ];

    private const ROTAS_PERMITIDAS_DURANTE_REDEFINICAO = [
        'reset-password',
        'reset-password/cancelar',
        'logout',
    ];

    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $uri = $request->getUri();

        if (!$uri instanceof SiteURI) {
            return null;
        }

        $caminho      = trim($uri->getRoutePath(), '/');
        $autenticador = auth('session')->getAuthenticator();

        if (!$autenticador instanceof Session) {
            return null;
        }

        $temSessao             = $autenticador->loggedIn() || $autenticador->isPending();
        $redefinicaoPendente = session()->get('redefinicaoSenhaPendente') === true;
        $acessoTemporario     = session()->getTempdata('acessoRedefinicaoSenha') === true;

        if ($redefinicaoPendente && (! $acessoTemporario || ! $autenticador->loggedIn())) {
            auth()->logout();
            session()->remove('redefinicaoSenhaPendente');
            session()->removeTempdata('acessoRedefinicaoSenha');
            session()->removeTempdata('beforeLoginUrl');

            return redirect()->to(site_url('login'));
        }

        if ($redefinicaoPendente && ! in_array($caminho, self::ROTAS_PERMITIDAS_DURANTE_REDEFINICAO, true)) {
            return redirect()
                ->to(site_url('reset-password'))
                ->with('error', 'Conclua a redefinicao da senha antes de acessar outra pagina.');
        }

        if (! $redefinicaoPendente && $temSessao && in_array($caminho, self::ROTAS_RECUPERACAO, true)) {
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
}
