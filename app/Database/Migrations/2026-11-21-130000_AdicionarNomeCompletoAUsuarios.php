<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AdicionarNomeCompletoAUsuarios extends Migration
{
    public function up(): void
    {
        $tabelas = config('Auth')->tables;

        $this->forge->addColumn($tabelas['users'], [
            'nome_completo' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'username',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn(config('Auth')->tables['users'], 'nome_completo');
    }
}
