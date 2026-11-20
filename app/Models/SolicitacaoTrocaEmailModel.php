<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SolicitacaoTrocaEmailModel extends Model
{
    protected $table            = 'solicitacoes_troca_email';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'conta_id',
        'operador_id',
        'auditoria_id',
        'email_anterior',
        'email_novo',
        'justificativa',
        'token_hash',
        'expira_em',
        'created_at',
    ];
    protected $useTimestamps = false;
}
