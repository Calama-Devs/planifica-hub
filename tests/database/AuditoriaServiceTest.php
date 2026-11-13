<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\EventoAuditoriaModel;
use App\Services\AuditoriaService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;
use Tests\Support\Traits\CriaContasTrait;

final class AuditoriaServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CriaContasTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private User $usuario;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->usuario = $this->criarConta('operador.auditoria');
        auth()->login($this->usuario);
    }

    protected function tearDown(): void
    {
        auth()->logout();
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testRegistraOUsuarioAutenticado(): void
    {
        $servico = new AuditoriaService(new EventoAuditoriaModel($this->db));

        $servico->registrar($this->evento(['created_by' => 999999]));

        $this->seeNumRecords(1, 'eventos_auditoria', [
            'created_by' => $this->usuario->id,
            'resultado'  => 'concluida',
        ]);
    }

    public function testRegistraVariosComUsuarioAutenticado(): void
    {
        $servico = new AuditoriaService(new EventoAuditoriaModel($this->db));

        $servico->registrarVarios([
            $this->evento(['resultado' => 'recusada']),
            $this->evento(['resultado' => 'erro']),
        ]);

        $this->seeNumRecords(2, 'eventos_auditoria', [
            'created_by' => $this->usuario->id,
        ]);
        $this->seeInDatabase('eventos_auditoria', ['resultado' => 'recusada']);
        $this->seeInDatabase('eventos_auditoria', ['resultado' => 'erro']);
    }

    public function testFalhaNaAuditoriaNaoInterrompeATransacaoPrincipal(): void
    {
        $modelo = new class () extends EventoAuditoriaModel {
            public bool $registrarChamado = false;
            public bool $loteChamado      = false;

            public function registrar(array $evento): bool
            {
                $this->registrarChamado = true;

                throw new RuntimeException('Falha simulada.');
            }

            public function registrarVarios(array $eventos): bool
            {
                $this->loteChamado = true;

                return false;
            }
        };
        $servico = new AuditoriaService($modelo);

        $this->db->transBegin();
        $servico->registrar($this->evento());
        $servico->registrarVarios([$this->evento(['resultado' => 'erro'])]);

        $this->assertTrue($modelo->registrarChamado);
        $this->assertTrue($modelo->loteChamado);
        $this->assertSame(1, $this->db->transDepth);

        $this->db->transCommit();
        $this->assertTrue($this->db->transStatus());
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
