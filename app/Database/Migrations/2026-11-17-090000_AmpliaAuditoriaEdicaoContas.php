<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\BaseConnection;

class AmpliaAuditoriaEdicaoContas extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('auditoria_edicao_contas', [
            'nome_anterior' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'campos_alterados',
            ],
            'nome_novo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'nome_anterior'],
            'email_anterior' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true, 'after' => 'nome_novo'],
            'email_novo' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true, 'after' => 'email_anterior'],
            'ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true, 'after' => 'resultado'],
        ]);
    }

    public function down(): void
    {
        $campos = ['nome_anterior', 'nome_novo', 'email_anterior', 'email_novo', 'ip'];

        if ($this->db->getPlatform() === 'SQLite3' && $this->db instanceof BaseConnection) {
            $tabela = $this->db->protectIdentifiers(
                $this->db->prefixTable('auditoria_edicao_contas')
            );

            foreach ($campos as $campo) {
                if ($this->db->fieldExists($campo, 'auditoria_edicao_contas')) {
                    $this->db->query(
                        'ALTER TABLE ' . $tabela . ' DROP COLUMN ' . $this->db->protectIdentifiers($campo)
                    );
                }
            }

            return;
        }

        $this->forge->dropColumn('auditoria_edicao_contas', $campos);
    }
}
