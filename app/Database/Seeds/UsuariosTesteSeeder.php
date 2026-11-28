<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\AuthGroups;
use RuntimeException;

/**
 * DESCARTÁVEL: cria usuários fictícios para testar listagem, busca e paginação.
 * Uso apenas em ambiente local. Não versionar.
 *
 * Executar: php spark db:seed UsuariosTesteSeeder
 */
class UsuariosTesteSeeder extends Seeder
{
    private const QUANTIDADE = 20;
    private const PREFIXO    = 'teste.usuario';
    private const DOMINIO    = 'exemplo.test';

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Seeder de teste não pode rodar em produção.');
        }

        $usuarios = model(UserModel::class);
        $grupo    = config(AuthGroups::class)->defaultGroup;
        // Senha aleatória e descartada: estas contas existem só para aparecer na listagem.
        $senha  = bin2hex(random_bytes(12));
        $criados = 0;

        for ($i = 1; $i <= self::QUANTIDADE; $i++) {
            $nome  = sprintf('%s%02d', self::PREFIXO, $i);
            $email = $nome . '@' . self::DOMINIO;

            if ($this->db->table('users')->where('username', $nome)->countAllResults() > 0) {
                continue;
            }

            if (! $usuarios->save(new User(['username' => $nome, 'active' => true]))) {
                continue;
            }

            $conta = $usuarios->findById((int) $usuarios->getInsertID());

            if (! $conta instanceof User) {
                continue;
            }

            $conta->createEmailIdentity(['email' => $email, 'password' => $senha]);
            $conta->addGroup($grupo);
            $criados++;
        }

        CLI::write(sprintf('Usuários de teste criados: %d (grupo "%s").', $criados, $grupo), 'green');
    }
}
