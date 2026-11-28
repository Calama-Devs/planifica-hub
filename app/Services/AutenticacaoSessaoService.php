<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponsableInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\AuthenticationException;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;
use RuntimeException;

/**
 * Integra a suspensão administrativa ao Shield sem confundi-la com ativação por e-mail.
 */
class AutenticacaoSessaoService extends Session
{
    private const CHAVE_VERSAO_SESSAO = 'versao_sessao';

    /** @param array<string, string> $credentials */
    public function check(array $credentials): Result
    {
        $resultado = parent::check($credentials);

        if (! $resultado->isOK()) {
            return $resultado;
        }

        /** @var User $conta */
        $conta = $resultado->extraInfo();

        if (! $conta->active) {
            return new Result(['success' => false, 'reason' => lang('Auth.badAttempt')]);
        }

        return $resultado;
    }

    public function startLogin(User $user): void
    {
        $conta = $this->provider->findById($user->id);
        $versao = (int) ($conta->versao_sessao ?? 0);

        if ($conta === null || ! $conta->active || $conta->isBanned() || $versao < 1) {
            throw AuthenticationException::forInvalidUser();
        }

        parent::startLogin($conta);
        $this->setSessionUserKey(self::CHAVE_VERSAO_SESSAO, $versao);
    }

    public function loggedIn(): bool
    {
        try {
            return $this->validarSessaoExistente() && parent::loggedIn() && $this->user !== null;
        } catch (AuthenticationException $exception) {
            $this->responderFalhaDeRememberMe($exception);
        }
    }

    public function getUser(): ?User
    {
        try {
            return parent::getUser();
        } catch (AuthenticationException $exception) {
            $this->responderFalhaDeRememberMe($exception);
        }
    }

    private function responderFalhaDeRememberMe(AuthenticationException $exception): never
    {
        $requisicao = service('request');

        if (! $requisicao instanceof IncomingRequest) {
            throw $exception;
        }

        $nomeCookie = setting('Cookie.prefix') . setting('Auth.sessionConfig')['rememberCookieName'];
        $cookie     = $requisicao->getCookie($nomeCookie);

        if (! is_string($cookie) || ! str_contains($cookie, ':')) {
            throw $exception;
        }

        [$seletor] = explode(':', $cookie, 2);
        $token     = $this->rememberModel->getRememberToken($seletor);

        if ($token === null) {
            throw $exception;
        }

        $conta = $this->provider->findById($token->user_id);

        if ($conta instanceof User) {
            $this->forget($conta);
        }

        $this->encerrarSessaoRevogada();
        $inativa = ! $conta instanceof User || ! $conta->active;

        $this->responderSessaoEncerrada($requisicao->isAJAX(), $inativa);
    }

    private function validarSessaoExistente(): bool
    {
        $usuarioId = $this->getSessionUserKey('id');

        if ($usuarioId === null) {
            return true;
        }

        $conta = $this->provider->findById($usuarioId);
        $inativa = $conta === null || ! $conta->active;

        if (! $inativa && $this->sessaoTemVersaoAtual($conta)) {
            return true;
        }

        $this->encerrarSessaoRevogada();

        // O filtro nativo só redireciona ao receber false e não distingue sessão revogada de visitante.
        $requisicao = service('request');
        if ($requisicao instanceof IncomingRequest && in_array('session', service('router')->getFilters(), true)) {
            $this->responderSessaoEncerrada($requisicao->isAJAX(), $inativa);
        }

        return false;
    }

    private function sessaoTemVersaoAtual(User $conta): bool
    {
        $versaoAtual = (int) $conta->versao_sessao;

        return $versaoAtual > 0 && $this->getSessionUserKey(self::CHAVE_VERSAO_SESSAO) === $versaoAtual;
    }

    private function encerrarSessaoRevogada(): void
    {
        $sessao = session();
        $sessao->remove(array_keys($sessao->get()));
        $sessao->regenerate(true);

        $this->user = null;
    }

    private function responderSessaoEncerrada(bool $ajax, bool $inativa): never
    {
        throw new class ($ajax, $inativa) extends RuntimeException implements ResponsableInterface {
            public function __construct(private readonly bool $ajax, private readonly bool $inativa)
            {
                parent::__construct('Sessão encerrada.');
            }

            public function getResponse(): ResponseInterface
            {
                $mensagem = $this->inativa
                    ? 'Sua conta está inativa e sua sessão foi encerrada. '
                        . 'Entre em contato com o administrador para reativação do acesso.'
                    : 'Sua sessão foi encerrada. Entre novamente para continuar.';

                session()->setFlashdata('error', $mensagem);

                if ($this->ajax) {
                    return service('response')->setStatusCode(403)->setJSON([
                        'sucesso' => false,
                        'tipo' => 'permissao',
                        'codigo' => $this->inativa ? 'USUARIO_INDISPONIVEL' : 'SESSAO_REVOGADA',
                        'mensagem' => $mensagem,
                        'redirecionamento' => url_to('login'),
                    ]);
                }

                return redirect()->to(config('Auth')->logoutRedirect());
            }
        };
    }

    public function isPending(): bool
    {
        return parent::isPending() && $this->user !== null;
    }

    public function isAnonymous(): bool
    {
        return parent::isAnonymous() || $this->user === null;
    }
}
