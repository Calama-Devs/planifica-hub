<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
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
        'token_hash',
        'expira_em',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function existePendenteEmOutraConta(string $emailNormalizado, int $contaId): bool
    {
        return $this->builder()
            ->where('conta_id !=', $contaId)
            ->where('LOWER(email_novo) =', $emailNormalizado)
            ->where('expira_em >', Time::now()->toDateTimeString())
            ->countAllResults() > 0;
    }

    public function reivindicarToken(int $id, string $hashAtual, string $novoHash): bool
    {
        $atualizou = $this->builder()
            ->where('id', $id)
            ->where('token_hash', $hashAtual)
            ->where('expira_em >', Time::now()->toDateTimeString())
            ->update(['token_hash' => $novoHash]);

        return $atualizou && $this->db->affectedRows() === 1;
    }

    public function removerTokenExpirado(int $id, string $hash): bool
    {
        $removeu = $this->builder()
            ->where('id', $id)
            ->where('token_hash', $hash)
            ->where('expira_em <=', Time::now()->toDateTimeString())
            ->delete();

        return $removeu && $this->db->affectedRows() === 1;
    }
}
