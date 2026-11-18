<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

/** Mantém as entidades e a persistência nativas do Shield. */
class EstadoContaModel extends UserModel
{
    public function obterConexao(): BaseConnection
    {
        return $this->db;
    }

    public function bloquearAlteracoesDeEstado(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || $this->db->transDepth < 1) {
            throw new RuntimeException('O bloqueio de contas exige uma transação MySQL/InnoDB.');
        }

        // O Query Builder não oferece FOR UPDATE. A consulta não contém entradas externas.
        // A ordem é única para que operações sobre contas diferentes compartilhem o mesmo bloqueio.
        $consulta = $this->builder()
                         ->select('id')
                         ->orderBy('id')
                         ->getCompiledSelect();

        if ($this->db->query($consulta . ' FOR UPDATE') === false) {
            throw new RuntimeException('Não foi possível bloquear as contas.');
        }
    }

    /** @return list<User> */
    public function listarContasOperacionais(): array
    {
        return $this->withGroups()->withPermissions()->withIdentities()
            ->where('active', 1)
            ->findAll();
    }
}
