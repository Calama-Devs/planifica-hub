<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Authentication\AuthenticationException;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;

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
            if ($this->getSessionUserKey('id') === null) {
                $this->removeSessionUserInfo();
                $this->user = null;
            }

            throw new AuthenticationException(lang('Auth.badAttempt'));
        }

        parent::startLogin($conta);
        $this->setSessionUserKey(self::CHAVE_VERSAO_SESSAO, $versao);
    }

    public function sessaoTemVersaoAtual(User $conta): bool
    {
        $versaoAtual = (int) $conta->versao_sessao;

        return $versaoAtual > 0 && $this->getSessionUserKey(self::CHAVE_VERSAO_SESSAO) === $versaoAtual;
    }

    public function encerrarSessaoRevogada(): void
    {
        $sessao = session();
        $sessao->remove(array_keys($sessao->get()));
        $sessao->regenerate(true);

        $this->user = null;
    }

    public function loggedIn(): bool
    {
        return parent::loggedIn() && $this->user !== null;
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
