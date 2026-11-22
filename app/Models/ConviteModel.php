<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ConviteModel extends Model
{
    protected $table            = 'convites';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'email',
        'email_pendente',
        'token_hash',
        'status',
        'expira_em',
        'enviado_em',
        'falhou_em',
        'utilizado_em',
    ];

    protected $validationRules = [
        'email'          => 'required|valid_email|max_length[254]',
        'email_pendente' => 'permit_empty|valid_email|max_length[254]',
        'token_hash'     => 'required|regex_match[/^[a-f0-9]{64}$/]',
        'status'         => 'required|in_list[preparado,enviado,falha_envio,expirado,utilizado]',
        'expira_em'      => 'required|valid_date[Y-m-d H:i:s]',
        'enviado_em'     => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'falhou_em'      => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'utilizado_em'   => 'permit_empty|valid_date[Y-m-d H:i:s]',
    ];

    protected $validationMessages = [
        'token_hash' => [
            'regex_match' => 'O hash do token deve possuir 64 caracteres hexadecimais.',
        ],
        'status' => [
            'in_list' => 'O estado do convite não é válido.',
        ],
    ];

    protected $cleanValidationRules = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @return array<string, mixed>|null */
    public function buscarPendenteValidoPorEmail(string $email, string $agora): ?array
    {
        return $this->where('email_pendente', $email)
            ->whereIn('status', ['preparado', 'enviado'])
            ->where('expira_em >', $agora)
            ->first();
    }

    public function expirarPendentes(string $email, string $agora): bool
    {
        return $this->builder()
            ->where('email_pendente', $email)
            ->whereIn('status', ['preparado', 'enviado'])
            ->where('expira_em <=', $agora)
            ->update([
                'status'         => 'expirado',
                'email_pendente' => null,
                'updated_at'     => $agora,
            ]);
    }

    public function marcarEnvioAceito(int $conviteId, string $agora): bool
    {
        return $this->update($conviteId, [
            'status'     => 'enviado',
            'enviado_em' => $agora,
        ]);
    }

    public function marcarFalhaEnvio(int $conviteId, string $agora): bool
    {
        return $this->update($conviteId, [
            'status'         => 'falha_envio',
            'falhou_em'      => $agora,
            'email_pendente' => null,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function buscarPorTokenHash(string $tokenHash): ?array
    {
        return $this->where('token_hash', $tokenHash)->first();
    }

    public function consumirValido(int $conviteId, string $agora): bool
    {
        $this->builder()
            ->where('id', $conviteId)
            ->whereIn('status', ['preparado', 'enviado'])
            ->where('expira_em >', $agora)
            ->update([
                'status'         => 'utilizado',
                'utilizado_em'   => $agora,
                'email_pendente' => null,
                'updated_at'     => $agora,
            ]);

        return $this->db->affectedRows() === 1;
    }
}
