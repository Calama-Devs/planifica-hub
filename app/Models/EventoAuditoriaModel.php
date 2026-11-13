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
        'created_by',
        'modulo',
        'acao',
        'alvo_tipo',
        'alvo_id',
        'resultado',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    protected $validationRules = [
        'created_by' => 'permit_empty|is_natural_no_zero',
        'modulo'     => 'required|string|max_length[50]',
        'acao'       => 'required|string|max_length[100]',
        'alvo_tipo'  => 'required|string|max_length[50]',
        'alvo_id'    => 'permit_empty|string|max_length[100]',
        'resultado'  => 'required|string|max_length[20]|in_list[concluida,recusada,erro]',
    ];

    public function registrar(array $evento): bool
    {
        return $this->insert($evento) !== false;
    }

    public function registrarVarios(array $eventos): bool
    {
        if ($eventos === []) {
            return true;
        }

        return $this->insertBatch($eventos) !== false;
    }
}
