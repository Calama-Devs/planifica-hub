<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AuditoriaEdicaoContaModel extends Model
{
    protected $table            = 'auditoria_edicao_contas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'conta_id',
        'operador_id',
        'campos_alterados',
        'nome_anterior',
        'nome_novo',
        'email_anterior',
        'email_novo',
        'justificativa',
        'resultado',
        'ip',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;
}
