<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\EventoAuditoriaModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class EventoAuditoriaModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private EventoAuditoriaModel $eventos;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->eventos = new EventoAuditoriaModel($this->db);
    }

    protected function tearDown(): void
    {
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testRegistraIndividualmenteTodosOsResultadosPermitidos(): void
    {
        foreach (['concluida', 'recusada', 'erro'] as $resultado) {
            $this->assertTrue($this->eventos->registrar($this->evento(['resultado' => $resultado])));
        }

        $this->seeNumRecords(3, 'eventos_auditoria', []);
    }

    public function testRegistraLoteComTodosOsResultadosPermitidos(): void
    {
        $registros = [
            $this->evento(['resultado' => 'concluida']),
            $this->evento(['resultado' => 'recusada']),
            $this->evento(['resultado' => 'erro']),
        ];

        $this->assertTrue($this->eventos->registrarVarios($registros));
        $this->seeNumRecords(1, 'eventos_auditoria', ['resultado' => 'concluida']);
        $this->seeNumRecords(1, 'eventos_auditoria', ['resultado' => 'recusada']);
        $this->seeNumRecords(1, 'eventos_auditoria', ['resultado' => 'erro']);
    }

    public function testRecusaCamposObrigatoriosEResultadoInvalidoNoInsert(): void
    {
        $this->assertFalse($this->eventos->registrar($this->evento(['modulo' => ''])));
        $this->assertArrayHasKey('modulo', $this->eventos->errors());

        $this->assertFalse($this->eventos->registrar($this->evento(['resultado' => 'pendente'])));
        $this->assertArrayHasKey('resultado', $this->eventos->errors());
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testRecusaLoteQuandoUmaLinhaEInvalida(): void
    {
        $registros = [
            $this->evento(['resultado' => 'concluida']),
            $this->evento(['acao' => str_repeat('a', 101)]),
        ];

        $this->assertFalse($this->eventos->registrarVarios($registros));
        $this->assertArrayHasKey('acao', $this->eventos->errors());
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testAceitaCamposOpcionaisEProtegeCamposNaoPermitidos(): void
    {
        $this->assertTrue($this->eventos->registrar($this->evento([
            'created_by'      => null,
            'alvo_id'         => null,
            'created_at'      => '2000-01-01 00:00:00',
            'dado_sensivel'   => 'nao-persistir',
        ])));

        $registro = $this->eventos->find($this->eventos->getInsertID());

        $this->assertNull($registro['created_by']);
        $this->assertNull($registro['alvo_id']);
        $this->assertNotSame('2000-01-01 00:00:00', $registro['created_at']);
        $this->assertNotEmpty($registro['created_at']);
    }

    /**
     * @param array<string, mixed> $sobrescritas
     *
     * @return array<string, mixed>
     */
    private function evento(array $sobrescritas = []): array
    {
        return array_replace([
            'modulo'     => 'acessos',
            'acao'       => 'alterar_permissao',
            'alvo_tipo'  => 'usuario',
            'alvo_id'    => '42',
            'resultado'  => 'concluida',
        ], $sobrescritas);
    }
}
