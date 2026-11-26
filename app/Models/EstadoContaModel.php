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
    protected function initialize(): void
    {
        parent::initialize();

        $this->allowedFields[] = 'versao_sessao';
    }

    public function obterConexao(): BaseConnection
    {
        return $this->db;
    }

    public function bloquearAlteracoesDeEstado(): void
    {
        if ($this->db->transDepth < 1) {
            throw new RuntimeException('O bloqueio exige uma transação aberta.');
        }

        if ($this->db->DBDriver === 'SQLite3' && ENVIRONMENT === 'testing') {
            if (! $this->builder()->where('1 = 0', null, false)->set('id', 'id', false)->update()) {
                throw new RuntimeException('Não foi possível reservar a escrita.');
            }
            return;
        }
        if ($this->db->DBDriver !== 'MySQLi') {
            throw new RuntimeException('O bloqueio de contas exige uma transação MySQL/InnoDB.');
        }

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
