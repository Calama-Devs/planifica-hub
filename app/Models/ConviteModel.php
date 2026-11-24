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
        'utilizado_em',
        'grupos',
    ];

    protected $validationRules = [
        'email'          => 'required|valid_email|max_length[254]',
        'email_pendente' => 'permit_empty|valid_email|max_length[254]',
        'token_hash'     => 'required|regex_match[/^[a-f0-9]{64}$/]',
        'status'         => 'required|in_list[pendente,expirado,consumido]',
        'expira_em'      => 'required|valid_date[Y-m-d H:i:s]',
        'utilizado_em'   => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'grupos'         => 'permit_empty',
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
    protected $updatedField  = '';

    /** @return array<string, mixed>|null */
    public function buscarPendenteValidoPorEmail(string $email, string $agora): ?array
    {
        return $this->where('email_pendente', $email)
            ->where('status', 'pendente')
            ->where('expira_em >', $agora)
            ->first();
    }

    public function expirarPendentes(string $email, string $agora): bool
    {
        return $this->builder()
            ->where('email_pendente', $email)
            ->where('status', 'pendente')
            ->where('expira_em <=', $agora)
            ->update([
                'status'         => 'expirado',
                'email_pendente' => null,
            ]);
    }
}
