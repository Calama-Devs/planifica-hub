<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CriarTabelaConvites extends Migration
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
            'email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'email_pendente' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'status' => ['type' => 'ENUM', 'constraint' => ['pendente', 'expirado', 'consumido']],
            'expira_em' => ['type' => 'DATETIME'],
            'utilizado_em' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email_pendente');
        $this->forge->addKey(['email', 'expira_em']);
        $this->forge->createTable('convites', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('convites', true);
    }
}
