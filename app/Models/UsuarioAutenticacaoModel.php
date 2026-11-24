<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel;

/** Persiste o nome completo junto aos atributos da conta do Shield. */
class UsuarioAutenticacaoModel extends UserModel
{
    protected $allowedFields = [
        'username',
        'nome_completo',
        'status',
        'status_message',
        'active',
        'last_active',
    ];
}
