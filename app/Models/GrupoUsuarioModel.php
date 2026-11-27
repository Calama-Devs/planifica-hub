<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use RuntimeException;

/**
 * Consulta de vínculos entre usuários e grupos na tabela do Shield (`auth_groups_users`).
 */
class GrupoUsuarioModel extends Model
{
    protected $table      = 'auth_groups_users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    /**
     * Conta quantos usuários não removidos estão vinculados a cada grupo.
     *
     * @return array<string, int>
     */
    public function contarUsuariosPorGrupo(): array
    {
        if (! $this->db->tableExists('auth_groups_users') || ! $this->db->tableExists('users')) {
            throw new RuntimeException('As tabelas de vínculos de grupos não estão disponíveis.');
        }

        $linhas = $this->db->table('auth_groups_users agu')
            ->select('agu.group')
            ->select('COUNT(DISTINCT agu.user_id) AS total', false)
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->where('u.deleted_at', null)
            ->groupBy('agu.group')
            ->get()
            ->getResultArray();

        $contagens = [];

        foreach ($linhas as $linha) {
            $grupo             = (string) ($linha['group'] ?? '');
            $contagens[$grupo] = (int) ($linha['total'] ?? 0);
        }

        return $contagens;
    }
}
