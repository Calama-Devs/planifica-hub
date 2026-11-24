<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\BaseConnection;

class AdicionaNomeCompletoEmUsuarios extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'nome_completo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'username',
            ],
        ]);

        $this->db->table('users')
            ->set('nome_completo', 'username', false)
            ->where('nome_completo', null)
            ->update();
    }

    public function down(): void
    {
        if ($this->db->getPlatform() === 'SQLite3' && $this->db instanceof BaseConnection) {
            $tabela = $this->db->protectIdentifiers($this->db->prefixTable('users'));
            $campo  = $this->db->protectIdentifiers('nome_completo');

            $this->db->query('ALTER TABLE ' . $tabela . ' DROP COLUMN ' . $campo);

            return;
        }

        $this->forge->dropColumn('users', 'nome_completo');
    }
}
