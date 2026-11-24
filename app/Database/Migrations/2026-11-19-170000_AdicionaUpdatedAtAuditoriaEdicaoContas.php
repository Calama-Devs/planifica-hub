<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

class AdicionaUpdatedAtAuditoriaEdicaoContas extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('auditoria_edicao_contas', [
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down(): void
    {
        if ($this->db->getPlatform() === 'SQLite3' && $this->db instanceof BaseConnection) {
            $tabela = $this->db->protectIdentifiers(
                $this->db->prefixTable('auditoria_edicao_contas'),
            );

            if ($this->db->fieldExists('updated_at', 'auditoria_edicao_contas')) {
                $this->db->query(
                    'ALTER TABLE ' . $tabela . ' DROP COLUMN ' . $this->db->protectIdentifiers('updated_at'),
                );
            }

            return;
        }

        $this->forge->dropColumn('auditoria_edicao_contas', 'updated_at');
    }
}
