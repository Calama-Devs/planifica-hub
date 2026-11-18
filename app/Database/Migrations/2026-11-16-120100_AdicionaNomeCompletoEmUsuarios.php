<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AdicionaNomeCompletoEmUsuarios extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'nome_completo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'username',
            ],
        ]);

        $this->db->table('users')
            ->set('nome_completo', 'username', false)
            ->where('nome_completo', null)
            ->update();
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'nome_completo');
    }
}
