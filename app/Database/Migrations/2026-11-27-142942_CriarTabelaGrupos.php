<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CriarTabelaGrupos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'identificador' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],

            'ativo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('identificador', true);

        $this->forge->createTable('grupos');
    }

    public function down(): void
    {
        $this->forge->dropTable('grupos');
    }
}
