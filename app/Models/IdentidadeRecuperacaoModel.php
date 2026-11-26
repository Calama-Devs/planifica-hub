<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;

class IdentidadeRecuperacaoModel extends UserIdentityModel
{
    public const TIPO_TOKEN = 'magic-link-reset';

    public function consumirToken(int $identidadeId): bool
    {
        return $this->where('type', self::TIPO_TOKEN)->delete($identidadeId) && $this->db->affectedRows() === 1;
    }

    public function atualizarSenhaDaContaAtiva(User $usuario, int $identidadeId, string $hashSenha): bool
    {

        $contaAutorizada = $this->db->table($this->tables['users'])
            ->select('id')
            ->where('id', $usuario->id)
            ->where('active', 1)
            ->where('deleted_at', null)
            ->where('versao_sessao', (int) $usuario->versao_sessao);

        return $this->whereIn('user_id', $contaAutorizada)->update($identidadeId, [
            'secret2'     => $hashSenha,
            'force_reset' => 0,
        ]) && $this->db->affectedRows() === 1;
    }
}
