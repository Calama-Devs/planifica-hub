<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CriaSolicitacoesTrocaEmail extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conta_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'operador_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'auditoria_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'email_anterior' => ['type' => 'VARCHAR', 'constraint' => 254],
            'email_novo' => ['type' => 'VARCHAR', 'constraint' => 254],
            'justificativa' => ['type' => 'VARCHAR', 'constraint' => 500],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expira_em' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('conta_id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['email_novo', 'expira_em']);
        $this->forge->addForeignKey('conta_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'CASCADE');
        $this->forge->addForeignKey('operador_id', 'users', 'id', onUpdate: 'CASCADE', onDelete: 'RESTRICT');
        $this->forge->addForeignKey(
            'auditoria_id',
            'auditoria_edicao_contas',
            'id',
            onUpdate: 'CASCADE',
            onDelete: 'RESTRICT',
        );
        $this->forge->createTable('solicitacoes_troca_email');
    }

    public function down(): void
    {
        $this->forge->dropTable('solicitacoes_troca_email', true);
    }
}
