<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

/**
 * DESCARTÁVEL: remove os usuários criados por UsuariosTesteSeeder.
 * Apaga somente contas cujo nome começa com "teste.usuario". Não versionar.
 *
 * Executar: php spark db:seed LimparUsuariosTesteSeeder
 */
class LimparUsuariosTesteSeeder extends Seeder
{
    private const PREFIXO = 'teste.usuario';

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Seeder de teste não pode rodar em produção.');
        }

        $usuarios = model(UserModel::class);
        $linhas   = $this->db->table('users')
            ->select('id')
            ->like('username', self::PREFIXO, 'after')
            ->get()
            ->getResultArray();
        $removidos = 0;

        foreach ($linhas as $linha) {
            if ($usuarios->delete((int) $linha['id'], true)) {
                $removidos++;
            }
        }

        CLI::write(sprintf('Usuários de teste removidos: %d.', $removidos), 'green');
    }
}
