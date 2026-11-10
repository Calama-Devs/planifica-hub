<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissaoModel;
use App\Models\UsersModel;

class AcessoContaService
{
    private const REGEX_IDENTIFICADOR = '/^[a-z]+(_[a-z]+)*\.[a-z]+(_[a-z]+)*$/';

    private PermissaoModel $permissaoModel;
    private UsersModel $usersModel;

    public function contas(): array
    {
        $usuarios = $this->usersModel->listarUsers();
        var_dump($usuarios);

        return $usuarios;
    }
}
