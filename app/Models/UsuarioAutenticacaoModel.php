<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel;

/** Persiste o nome completo junto aos atributos da conta do Shield. */
class UsuarioAutenticacaoModel extends UserModel
{
    protected $validationRules = [
        'nome_completo' => 'required|min_length[3]|max_length[100]|regex_match[/^[\p{L}\s\.\'’\-]+$/u]',
    ];

    protected $validationMessages = [
        'nome_completo' => [
            'regex_match' => 'O Nome completo contém caracteres inválidos. Utilize apenas letras, espaços e acentos.',
        ],
    ];

    protected $allowedFields = [
        'username',
        'nome_completo',
        'status',
        'status_message',
        'active',
        'last_active',
    ];
}
