<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Trilha de auditoria das concessões e revogações de permissões diretas
 * (manual 8.4 e 11.3).
 *
 * Tabela só de inclusão: um registro nunca é alterado nem excluído, por isso
 * não tem `updated_at` nem `deleted_at`. A autoria fica em `created_by` (quem
 * operou) e a conta afetada em `usuario_id`. As chaves estrangeiras usam
 * RESTRICT para que a trilha nunca perca o vínculo com as contas.
 */
class CriarTabelaAuditoriaPermissoes extends Migration
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
            ],
            'permissao' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'acao' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('usuario_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('auditoria_permissoes');
    }

    public function down(): void
    {
        $this->forge->dropTable('auditoria_permissoes', true);
    }
}
