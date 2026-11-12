<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class CriarEventosAuditoria extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'modulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'acao' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'alvo_tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'alvo_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'resultado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_by');
        $this->forge->addKey(['alvo_tipo', 'alvo_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('created_by', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'RESTRICT');
        $this->forge->createTable('eventos_auditoria');
    }

    public function down(): void
    {
        if (!$this->db->tableExists('eventos_auditoria')) {
            return;
        }

        $quantidade = $this->db
            ->table('eventos_auditoria')
            ->countAllResults();

        if ($quantidade > 0) {
            throw new RuntimeException(
                'Reversão bloqueada: existem eventos de auditoria registrados.'
            );
        }

        $this->forge->dropTable('eventos_auditoria');
    }
}