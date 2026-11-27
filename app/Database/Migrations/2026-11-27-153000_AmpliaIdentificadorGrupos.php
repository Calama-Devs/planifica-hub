<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AmpliaIdentificadorGrupos extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('grupos', [
            'identificador' => [
                'name'       => 'identificador',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ]);
    }

    public function down(): void
    {
        if (! $this->db->tableExists('grupos')) {
            return;
        }

        if ($this->db->table('grupos')->countAllResults() > 0) {
            throw new RuntimeException(
                'Reversão bloqueada: ela removeria os metadados dos grupos.'
            );
        }

        $this->forge->modifyColumn('grupos', [
            'identificador' => [
                'name'       => 'identificador',
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
        ]);
    }
}
