<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\AuditoriaPermissaoModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * A trilha de auditoria das permissões diretas continua disponível (tabela,
 * campos e Model), mesmo sem uma tela que a alimente no momento.
 */
final class AuditoriaPermissaoModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    public function testTabelaDeAuditoriaMantemSeusCampos(): void
    {
        $this->assertTrue($this->db->tableExists('auditoria_permissoes'));

        foreach (['id', 'usuario_id', 'permissao', 'acao', 'created_by', 'created_at'] as $campo) {
            $this->assertTrue($this->db->fieldExists($campo, 'auditoria_permissoes'), 'Falta o campo ' . $campo);
        }
    }

    public function testRegistraConcessaoERevogacaoDeUmaPermissaoDireta(): void
    {
        $contaId    = $this->criarUsuario();
        $operadorId = $this->criarUsuario();
        $auditoria  = model(AuditoriaPermissaoModel::class);

        $this->assertTrue(
            $auditoria->registrar($contaId, 'contas.consultar', AuditoriaPermissaoModel::ACAO_CONCEDIDA, $operadorId)
        );
        $this->assertTrue(
            $auditoria->registrar($contaId, 'contas.consultar', AuditoriaPermissaoModel::ACAO_REVOGADA, $operadorId)
        );

        $registros = $auditoria->where('usuario_id', $contaId)->orderBy('id')->findAll();

        $this->assertCount(2, $registros);
        $this->assertSame(AuditoriaPermissaoModel::ACAO_CONCEDIDA, $registros[0]['acao']);
        $this->assertSame(AuditoriaPermissaoModel::ACAO_REVOGADA, $registros[1]['acao']);

        foreach ($registros as $registro) {
            $this->assertSame('contas.consultar', $registro['permissao']);
            $this->assertSame($operadorId, (int) $registro['created_by']);
            $this->assertNotEmpty($registro['created_at']);
        }
    }

    private function criarUsuario(): int
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.' . bin2hex(random_bytes(4))]));

        return (int) $usuarios->getInsertID();
    }
}
