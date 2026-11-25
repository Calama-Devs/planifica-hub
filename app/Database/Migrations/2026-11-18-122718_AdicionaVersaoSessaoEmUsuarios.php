<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Auth;

class AdicionaVersaoSessaoEmUsuarios extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn(config(Auth::class)->tables['users'], [
            'versao_sessao' => ['type' => 'BIGINT', 'default' => 1, 'null' => false],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn(config(Auth::class)->tables['users'], 'versao_sessao');
    }
}
