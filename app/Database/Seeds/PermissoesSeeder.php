<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\AuthGroups;

class PermissoesSeeder extends Seeder
{
    /**
     * Popula as permissões administrativas iniciais de sistema de forma idempotente.
     */
    public function run(): void
    {
        $permissoesCodigo = config(AuthGroups::class)->permissions;
        $tabela           = $this->db->table('permissoes');
        $agora            = date('Y-m-d H:i:s');

        foreach ($permissoesCodigo as $identificador => $descricao) {
            $existente = $tabela->where('identificador', $identificador)->get()->getRowArray();

            if ($existente === null) {
                $tabela->insert([
                    'identificador' => $identificador,
                    'descricao'     => $descricao,
                    'origem'        => 'sistema',
                    'created_at'    => $agora,
                    'updated_at'    => $agora,
                ]);
            }
        }
    }
}
