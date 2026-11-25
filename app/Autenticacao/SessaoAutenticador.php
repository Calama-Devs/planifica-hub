<?php

namespace App\Autenticacao;

use CodeIgniter\Shield\Authentication\AuthenticationException;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;

class SessaoAutenticador extends Session
{
    public function check(array $credentials): Result
    {
        $resultado = parent::check($credentials);
        $usuario   = $resultado->extraInfo();

        if ($resultado->isOK() && $usuario instanceof User && ! $usuario->active) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.badAttempt'),
            ]);
        }

        return $resultado;
    }

    public function login(User $user): void
    {
        if (! $user->active) {
            throw AuthenticationException::forInvalidUser();
        }

        parent::login($user);
    }

    public function loggedIn(): bool
    {
        if (! parent::loggedIn()) {
            return false;
        }

        $usuario = parent::getUser();

        return $usuario instanceof User && $usuario->active;
    }
}
