<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AdicionaIndicesAuditoriaEdicaoContas extends Migration
{
    public function up(): void
    {
        $this->forge->addKey(['conta_id', 'created_at'], false, false, 'idx_auditoria_conta_created_at');
        $this->forge->addKey(['operador_id', 'created_at'], false, false, 'idx_auditoria_operador_created_at');
        $this->forge->processIndexes('auditoria_edicao_contas');
    }

    public function down(): void
    {
        $this->forge->dropKey('auditoria_edicao_contas', 'idx_auditoria_conta_created_at', false);
        $this->forge->dropKey('auditoria_edicao_contas', 'idx_auditoria_operador_created_at', false);
    }
}
