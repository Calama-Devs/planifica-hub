<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PermissaoModel extends Model
{
    protected $table            = 'permissoes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'identificador',
        'descricao',
        'origem',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /**
     * Busca uma permissão ativa pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorIdentificador(string $identificador): ?array
    {
        return $this->where('identificador', strtolower(trim($identificador)))
            ->first();
    }

    /**
     * Retorna todas as permissões ativas ordenadas por identificador.
     *
     * @return list<array<string, mixed>>
     */
    public function listarTodasAtivas(): array
    {
        return $this->orderBy('identificador', 'ASC')
            ->findAll();
    }

    /**
     * Retorna mapa associativo no formato [identificador => descricao] para o catálogo do Shield.
     *
     * @return array<string, string>
     */
    public function obterMapaCatalogo(): array
    {
        $permissoes = $this->select('identificador, descricao')
            ->orderBy('identificador', 'ASC')
            ->findAll();

        $mapa = [];
        foreach ($permissoes as $permissao) {
            $mapa[$permissao['identificador']] = (string) $permissao['descricao'];
        }

        return $mapa;
    }
}
