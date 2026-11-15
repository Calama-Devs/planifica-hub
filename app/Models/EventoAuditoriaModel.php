<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EventoAuditoriaModel extends Model
{
    protected $table            = 'eventos_auditoria';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'modulo',
        'usuario_id',
        'acao',
        'entidade_tipo',
        'entidade_id',
        'descricao',
        'resultado',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    protected $validationRules = [
        'modulo'        => 'required|string|max_length[50]',
        'usuario_id'    => 'permit_empty|is_natural_no_zero',
        'acao'          => 'required|string|max_length[50]',
        'entidade_tipo' => 'required|string|max_length[50]',
        'entidade_id'   => 'permit_empty|string|max_length[100]',
        'descricao'     => 'required|string',
        'resultado'     => 'required|string|max_length[20]|in_list[concluida,recusada,erro]',
    ];
}
