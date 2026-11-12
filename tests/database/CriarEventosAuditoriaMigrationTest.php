<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Migrations\CriarEventosAuditoria;
use App\Models\EventoAuditoriaModel;
use Config\Database;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class CriarEventosAuditoriaMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected function tearDown(): void
    {
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testDownBloqueiaARemocaoQuandoHaHistorico(): void
    {
        $eventos = new EventoAuditoriaModel($this->db);
        $eventos->registrar([
            'modulo'     => 'acessos',
            'acao'       => 'alterar_permissao',
            'alvo_tipo'  => 'usuario',
            'resultado'  => 'concluida',
        ]);

        $migration = new CriarEventosAuditoria(Database::forge($this->db));

        $this->expectException(RuntimeException::class);
        $migration->down();
    }
}
