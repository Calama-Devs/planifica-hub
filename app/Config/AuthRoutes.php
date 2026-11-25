<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthRoutes as ShieldAuthRoutes;

class AuthRoutes extends ShieldAuthRoutes
{
    public function __construct()
    {
        parent::__construct();

        $this->routes['magic-link'][2][2] = '\\App\\Controllers\\MagicLinkController::verify';
    }
}
