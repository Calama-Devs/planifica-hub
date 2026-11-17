<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\Config as Database;
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
            'modulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'acao' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'entidade_tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'entidade_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'descricao' => [
                'type' => 'TEXT',
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
        // Eventos automáticos usam usuario_id nulo; eventos de usuários preservam o vínculo histórico.
        $this->forge->addForeignKey('usuario_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'RESTRICT');
        $this->forge->createTable('eventos_auditoria');
    }

    public function down(): void
    {
        if (! Database::connect($this->DBGroup)->tableExists('eventos_auditoria')) {
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
