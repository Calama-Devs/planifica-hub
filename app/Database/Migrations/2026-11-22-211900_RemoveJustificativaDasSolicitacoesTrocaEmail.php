<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;
use RuntimeException;

class RemoveJustificativaDasSolicitacoesTrocaEmail extends Migration
{
    public function up(): void
    {
        $this->forge->dropColumn('solicitacoes_troca_email', 'justificativa');
    }

    public function down(): void
    {
        $conexao = $this->db;
        if (! $conexao instanceof BaseConnection) {
            throw new RuntimeException('A conexão não suporta identificação de tabelas com prefixo.');
        }

        $this->forge->addColumn('solicitacoes_troca_email', [
            'justificativa' => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
        ]);

        $solicitacoes = $conexao->escapeIdentifiers($conexao->prefixTable('solicitacoes_troca_email'));
        $auditoria = $conexao->escapeIdentifiers($conexao->prefixTable('auditoria_edicao_contas'));
        $conexao->table('solicitacoes_troca_email')
            ->set(
                'justificativa',
                "COALESCE((SELECT justificativa FROM {$auditoria} WHERE id = {$solicitacoes}.auditoria_id), '')",
                false,
            )
            ->update();
    }
}
