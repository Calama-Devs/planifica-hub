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
        $eventos->insert([
            'modulo'        => 'acessos',
            'acao'          => 'alterar_permissao',
            'entidade_tipo' => 'usuario',
            'descricao'     => 'Permissão de acesso do usuário 42 alterada de visualização para edição.',
            'resultado'     => 'concluida',
        ]);

        $migration = new CriarEventosAuditoria(Database::forge($this->db));

        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function testPossuiSomenteAsNoveColunasDaAuditoria(): void
    {
        $this->assertSame([
            'id',
            'modulo',
            'usuario_id',
            'acao',
            'entidade_tipo',
            'entidade_id',
            'descricao',
            'resultado',
            'created_at',
        ], $this->db->getFieldNames('eventos_auditoria'));
    }

    public function testUsuarioIdReferenciaUsuariosSemImpedirProcessosAutomaticos(): void
    {
        $chavesEstrangeiras = $this->db->getForeignKeyData('eventos_auditoria');
        $chaveUsuario       = array_values(array_filter(
            $chavesEstrangeiras,
            static fn (object $chave): bool => $chave->column_name === ['usuario_id'],
        ));

        $this->assertCount(1, $chaveUsuario);
        $this->assertSame($this->db->getPrefix() . 'users', $chaveUsuario[0]->foreign_table_name);
        $this->assertSame(['id'], $chaveUsuario[0]->foreign_column_name);
        $this->assertSame('RESTRICT', strtoupper($chaveUsuario[0]->on_delete));
        $this->assertSame('CASCADE', strtoupper($chaveUsuario[0]->on_update));
    }
}
