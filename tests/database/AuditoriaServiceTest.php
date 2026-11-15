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

        $servico->registrar($this->evento(['usuario_id' => 999999]));

        $this->seeNumRecords(1, 'eventos_auditoria', [
            'usuario_id' => $this->usuario->id,
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
            'usuario_id' => $this->usuario->id,
        ]);
        $this->seeInDatabase('eventos_auditoria', ['resultado' => 'recusada']);
        $this->seeInDatabase('eventos_auditoria', ['resultado' => 'erro']);
    }

    public function testFalhaNaAuditoriaReverteAOperacaoPrincipal(): void
    {
        $servico = new AuditoriaService(new EventoAuditoriaModel($this->db));

        $this->db->transBegin();

        try {
            $this->db->table('permissoes')->insert([
                'identificador' => 'auditoria.rollback',
                'descricao'     => 'Permissão usada no teste de rollback da auditoria.',
                'origem'        => 'customizada',
            ]);
            $servico->registrar($this->evento(['descricao' => '']));
            $this->fail('A falha da auditoria deveria interromper a operação.');
        } catch (RuntimeException) {
            $this->db->transRollback();
        }

        $this->seeNumRecords(0, 'permissoes', ['identificador' => 'auditoria.rollback']);
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testPropagaFalhaDoRegistroEmLote(): void
    {
        $servico = new AuditoriaService(new EventoAuditoriaModel($this->db));

        $this->expectException(RuntimeException::class);
        $servico->registrarVarios([$this->evento(['descricao' => ''])]);
    }

    public function testIgnoraLoteVazioSemPersistir(): void
    {
        $servico = new AuditoriaService(new EventoAuditoriaModel($this->db));

        $servico->registrarVarios([]);

        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testRegistraProcessoAutomaticoSemUsuario(): void
    {
        auth()->logout();
        $servico = new AuditoriaService(new EventoAuditoriaModel($this->db));

        $servico->registrar($this->evento(['usuario_id' => 999999]));

        $this->seeInDatabase('eventos_auditoria', ['usuario_id' => null]);
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
            'entidade_tipo' => 'usuario',
            'entidade_id'   => '42',
            'descricao'     => 'Permissão de acesso do usuário 42 alterada de visualização para edição.',
            'resultado'  => 'concluida',
        ], $sobrescritas);
    }
}
