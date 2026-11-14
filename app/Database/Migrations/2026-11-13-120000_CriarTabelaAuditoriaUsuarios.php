<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Trilha de auditoria das operações administrativas de usuários. */
class CriarTabelaAuditoriaUsuarios extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'operador_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'operacao' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'resultado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'grupo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('usuario_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'SET NULL');
        $this->forge->addForeignKey('operador_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'RESTRICT');
        $this->forge->createTable('auditoria_usuarios');
    }

    public function down(): void
    {
        $this->forge->dropTable('auditoria_usuarios', true);
    }
}
