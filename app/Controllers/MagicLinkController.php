<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Controllers\MagicLinkController as ShieldMagicLinkController;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;

class MagicLinkController extends ShieldMagicLinkController
{
    public function verify(): RedirectResponse
    {
        $token = $this->request->getGet('token');

        if (is_string($token) && $token !== '') {
            $identidades = model(UserIdentityModel::class);
            $identidade  = $identidades->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

            if ($identidade !== null && ! Time::now()->isAfter($identidade->expires)) {
                $usuario = $this->provider->findById($identidade->user_id);

                if (! $usuario instanceof User || ! $usuario->active) {
                    $identidades->delete($identidade->id);

                    return redirect()->route('magic-link')->with('error', lang('Auth.badAttempt'));
                }
            }
        }

        return parent::verify();
    }
}
