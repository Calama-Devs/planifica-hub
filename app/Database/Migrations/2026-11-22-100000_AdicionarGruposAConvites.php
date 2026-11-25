<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AdicionarGruposAConvites extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('convites', [
            'grupos' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('convites', 'grupos');
    }
}
