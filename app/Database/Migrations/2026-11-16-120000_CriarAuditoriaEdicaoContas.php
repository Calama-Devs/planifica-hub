<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CriarAuditoriaEdicaoContas extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conta_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'operador_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'campos_alterados' => ['type' => 'VARCHAR', 'constraint' => 100],
            'justificativa' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'resultado' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME'],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('conta_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'RESTRICT');
        $this->forge->addForeignKey('operador_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'RESTRICT');
        $this->forge->createTable('auditoria_edicao_contas');
    }

    public function down(): void
    {
        $this->forge->dropTable('auditoria_edicao_contas', true);
    }
}
