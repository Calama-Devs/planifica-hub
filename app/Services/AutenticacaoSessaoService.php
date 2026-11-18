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

        if ($conta === null || ! $conta->active || $conta->isBanned()) {
            if ($this->getSessionUserKey('id') === null) {

                $this->removeSessionUserInfo();
                $this->user = null;
            }

            throw new AuthenticationException(lang('Auth.badAttempt'));
        }

        parent::startLogin($conta);
    }
}
