<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Trilha de auditoria das concessões e revogações de permissões diretas.
 * Só de inclusão: não há atualização nem exclusão de registros.
 */
class AuditoriaPermissaoModel extends Model
{
    public const ACAO_CONCEDIDA = 'concedida';
    public const ACAO_REVOGADA  = 'revogada';

    protected $table            = 'auditoria_permissoes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id',
        'permissao',
        'acao',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    /**
     * Registra uma alteração de permissão direta.
     *
     * @param string $acao Uma das constantes ACAO_* desta classe.
     *
     * @return bool Falso quando o registro não foi gravado.
     */
    public function registrar(int $usuarioId, string $permissao, string $acao, int $operadorId): bool
    {
        $id = $this->insert([
            'usuario_id' => $usuarioId,
            'permissao'  => $permissao,
            'acao'       => $acao,
            'created_by' => $operadorId,
        ]);

        return $id !== false;
    }
}
