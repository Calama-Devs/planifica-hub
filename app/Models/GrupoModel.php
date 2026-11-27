<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class GrupoModel extends Model
{
    protected $table = 'grupos';

    protected $primaryKey = 'identificador';

    protected $useAutoIncrement = false;

    protected $returnType = 'array';

    protected $protectFields = true;

    protected $allowedFields = [
        'identificador',
        'ativo',
    ];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function obterMapaGrupos(): array
    {
        $grupos = $this->findAll();

        $mapa = [];

        foreach ($grupos as $grupo) {
            $mapa[$grupo['identificador']] = $grupo;
        }

        return $mapa;
    }
}
