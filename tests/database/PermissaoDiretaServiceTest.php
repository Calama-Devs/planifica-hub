<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\AuditoriaPermissaoModel;
use App\Services\PermissaoDiretaService;
use CodeIgniter\Pager\Pager;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;
use RuntimeException;
use Tests\Support\Traits\CriaContasTrait;

final class PermissaoDiretaServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CriaContasTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private PermissaoDiretaService $servico;
    private User $operador;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->servico  = new PermissaoDiretaService();
        $this->operador = $this->criarConta('operador.acessos', 'superadmin');
    }

    public function testConcedeSomenteOVinculoDireto(): void
    {
        $conta       = $this->criarConta('conta.alvo', 'user');
        $matrizAntes = setting('AuthGroups.matrix');

        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSAO_DIRETA_CONCEDIDA', $resultado['codigo']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
        $this->seeNumRecords(1, 'auth_groups_users', ['user_id' => $conta->id, 'group' => 'user']);
        $this->seeNumRecords(1, 'auth_groups_users', ['user_id' => $conta->id]);
        $this->assertSame($matrizAntes, setting('AuthGroups.matrix'));
    }

    public function testConcessaoRecarregaOAcessoEfetivo(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertTrue($resultado['acesso_efetivo']);
        $this->assertTrue($this->recarregar($conta)->can('contas.consultar'));
    }

    public function testAuditaAConcessao(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->seeNumRecords(1, 'auditoria_permissoes', [
            'usuario_id' => $conta->id,
            'permissao'  => 'contas.consultar',
            'acao'       => AuditoriaPermissaoModel::ACAO_CONCEDIDA,
            'created_by' => $this->operador->id,
        ]);
    }

    public function testRevogaOVinculoDiretoEAudita(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resultado = $this->servico->revogar($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSAO_DIRETA_REVOGADA', $resultado['codigo']);
        $this->assertFalse($resultado['acesso_efetivo']);
        $this->assertStringContainsString('não tem mais acesso', $resultado['mensagem']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->assertFalse($this->recarregar($conta)->can('contas.consultar'));
        $this->seeNumRecords(1, 'auditoria_permissoes', [
            'usuario_id' => $conta->id,
            'permissao'  => 'contas.consultar',
            'acao'       => AuditoriaPermissaoModel::ACAO_REVOGADA,
            'created_by' => $this->operador->id,
        ]);
    }

    public function testRevogarPreservaAPermissaoHerdadaDoGrupo(): void
    {
        $conta = $this->criarConta('conta.alvo', 'admin', ['contas.consultar']);

        $resultado = $this->servico->revogar($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertTrue($resultado['acesso_efetivo']);
        $this->assertStringContainsString('Administrador de contas', $resultado['mensagem']);

        $recarregada = $this->recarregar($conta);
        $this->assertFalse($recarregada->hasPermission('contas.consultar'));
        $this->assertTrue($recarregada->can('contas.consultar'));
        $this->seeInDatabase('auth_groups_users', ['user_id' => $conta->id, 'group' => 'admin']);
    }

    public function testConcederPermissaoJaHerdadaMantemOAcessoEInforma(): void
    {
        $conta = $this->criarConta('conta.alvo', 'admin');

        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertTrue($resultado['acesso_efetivo']);
        $this->assertStringContainsString('Administrador de contas', $resultado['mensagem']);
        $this->assertStringContainsString('não mudou', $resultado['mensagem']);
        $this->assertTrue($this->recarregar($conta)->hasPermission('contas.consultar'));
    }

    public function testRecusaPermissaoForaDoCatalogo(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $resultado = $this->servico->conceder($conta->id, 'contas.excluir', $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('validacao', $resultado['tipo']);
        $this->assertSame('PERMISSAO_NAO_CADASTRADA', $resultado['codigo']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testRecusaOperadorSemPermissaoParaAdministrarAcessos(): void
    {
        $semPermissao = $this->criarConta('operador.admin', 'admin');
        $conta        = $this->criarConta('conta.alvo', 'user');

        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', $semPermissao->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('permissao', $resultado['tipo']);
        $this->assertSame('ACESSOS_SEM_PERMISSAO', $resultado['codigo']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testRecusaRevogacaoDeOperadorSemPermissao(): void
    {
        $semPermissao = $this->criarConta('operador.admin', 'admin');
        $conta        = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resultado = $this->servico->revogar($conta->id, 'contas.consultar', $semPermissao->id);

        $this->assertSame('ACESSOS_SEM_PERMISSAO', $resultado['codigo']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
    }

    public function testRecusaOperadorInexistente(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', 999999);

        $this->assertSame('ACESSOS_SEM_PERMISSAO', $resultado['codigo']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
    }

    public function testRecusaContaInexistente(): void
    {
        $concessao = $this->servico->conceder(999999, 'contas.consultar', $this->operador->id);
        $revogacao = $this->servico->revogar(999999, 'contas.consultar', $this->operador->id);

        $this->assertSame('CONTA_NAO_ENCONTRADA', $concessao['codigo']);
        $this->assertSame('CONTA_NAO_ENCONTRADA', $revogacao['codigo']);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testRecusaConcessaoRepetida(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');
        $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('PERMISSAO_DIRETA_JA_CONCEDIDA', $resultado['codigo']);
        $this->seeNumRecords(1, 'auditoria_permissoes', ['usuario_id' => $conta->id]);
    }

    public function testRecusaRevogarQuemSoTemAPermissaoPeloGrupo(): void
    {
        $conta = $this->criarConta('conta.alvo', 'admin');

        $resultado = $this->servico->revogar($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('PERMISSAO_DIRETA_NAO_CONCEDIDA', $resultado['codigo']);
        $this->assertTrue($this->recarregar($conta)->can('contas.consultar'));
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testNormalizaOIdentificadorInformado(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $resultado = $this->servico->conceder($conta->id, '  Contas.Consultar ', $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
        $this->seeInDatabase('auditoria_permissoes', ['usuario_id' => $conta->id, 'permissao' => 'contas.consultar']);
    }

    public function testFalhaNaAuditoriaDesfazAConcessao(): void
    {
        $conta   = $this->criarConta('conta.alvo', 'user');
        $servico = new PermissaoDiretaService(null, $this->auditoriaQueNaoGrava());

        $resultado = $servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame('PERMISSAO_DIRETA_ERRO_PERSISTENCIA', $resultado['codigo']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
    }

    public function testFalhaNaAuditoriaDesfazARevogacao(): void
    {
        $conta   = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);
        $servico = new PermissaoDiretaService(null, $this->auditoriaQueLancaExcecao());

        $resultado = $servico->revogar($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('PERMISSAO_DIRETA_ERRO_PERSISTENCIA', $resultado['codigo']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
    }

    public function testViolacaoDeChaveEstrangeiraNaAuditoriaDesfazAConcessaoEMantemABaseUtilizavel(): void
    {
        $conta   = $this->criarConta('conta.alvo', 'user');
        $servico = new PermissaoDiretaService(null, $this->auditoriaComOperadorInexistente());

        $resultado = $servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertSame('PERMISSAO_DIRETA_ERRO_PERSISTENCIA', $resultado['codigo']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);

        // Depois da falha a conexão não fica marcada como "com falha" (modo estrito do CodeIgniter) e a
        // mesma operação funciona normalmente.
        $this->assertTrue($this->db->transStatus());

        $repetida = $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        $this->assertTrue($repetida['sucesso']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
    }

    public function testConsultarSeparaHerdadasDiretasEEfetivas(): void
    {
        $conta = $this->criarConta('conta.alvo', 'admin', ['acessos.administrar']);

        $visao = $this->servico->consultar($conta->id);

        $this->assertNotNull($visao);
        $this->assertSame(['Administrador de contas'], $visao['conta']['grupos']);

        $porIdentificador = array_column($visao['permissoes'], null, 'identificador');

        $this->assertSame(
            ['acessos.administrar', 'contas.consultar', 'contas.criar'],
            array_column($visao['permissoes'], 'identificador'),
        );
        $this->assertSame(
            ['direta' => true, 'herdada_de' => [], 'efetiva' => true],
            $this->situacao($porIdentificador['acessos.administrar']),
        );
        $this->assertSame(
            ['direta' => false, 'herdada_de' => ['Administrador de contas'], 'efetiva' => true],
            $this->situacao($porIdentificador['contas.consultar']),
        );
        $this->assertSame([], $visao['foraDoCatalogo']);
    }

    public function testConsultarContaSemNenhumAcesso(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $visao = $this->servico->consultar($conta->id);

        foreach ($visao['permissoes'] as $permissao) {
            $this->assertSame(['direta' => false, 'herdada_de' => [], 'efetiva' => false], $this->situacao($permissao));
        }
    }

    public function testConsultarContaInexistenteDevolveNulo(): void
    {
        $this->assertNull($this->servico->consultar(999999));
    }

    public function testEfetivaCoincideComODecididoPeloShieldEmTodasAsCombinacoes(): void
    {
        $catalogo = array_keys(config(AuthGroups::class)->permissions);
        $grupos   = array_keys(config(AuthGroups::class)->groups);

        foreach ($grupos as $indice => $grupo) {
            foreach ([[], $catalogo] as $variante => $diretas) {
                $conta = $this->criarConta("conta.{$indice}.{$variante}", $grupo, $diretas);
                $visao = $this->servico->consultar($conta->id);

                foreach ($visao['permissoes'] as $permissao) {
                    $esperada = $permissao['direta'] || $permissao['herdada_de'] !== [];

                    $this->assertSame($esperada, $permissao['efetiva'], "{$grupo}: {$permissao['identificador']}");
                    $this->assertSame(
                        $this->recarregar($conta)->can($permissao['identificador']),
                        $permissao['efetiva'],
                        "{$grupo}: {$permissao['identificador']}",
                    );
                }
            }
        }
    }

    public function testConsultarMostraVinculoDiretoForaDoCatalogoERevogaEle(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');
        $this->hasInDatabase('auth_permissions_users', [
            'user_id'    => $conta->id,
            'permission' => 'relatorios.antigo',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $visao = $this->servico->consultar($conta->id);
        $this->assertSame(['relatorios.antigo'], $visao['foraDoCatalogo']);

        $resultado = $this->servico->revogar($conta->id, 'relatorios.antigo', $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->assertSame([], $this->servico->consultar($conta->id)['foraDoCatalogo']);
    }

    public function testListarContasTrazGruposETotalDeDiretasEmOrdemAlfabetica(): void
    {
        $this->criarConta('zeca', 'user', ['contas.consultar', 'contas.criar']);
        $this->criarConta('ana', 'admin');
        $this->criarConta('sem.grupo');

        $listagem = $this->servico->listarContas();

        $this->assertInstanceOf(Pager::class, $listagem['paginador']);
        $this->assertSame(
            ['ana', 'operador.acessos', 'sem.grupo', 'zeca'],
            array_column($listagem['contas'], 'rotulo'),
        );

        $porRotulo = array_column($listagem['contas'], null, 'rotulo');
        $this->assertSame(['Administrador de contas'], $porRotulo['ana']['grupos']);
        $this->assertSame(0, $porRotulo['ana']['total_diretas']);
        $this->assertSame(['Usuário'], $porRotulo['zeca']['grupos']);
        $this->assertSame(2, $porRotulo['zeca']['total_diretas']);
        $this->assertSame([], $porRotulo['sem.grupo']['grupos']);
    }

    public function testListarContasMostraOEmailDaConta(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');
        $conta->createEmailIdentity([
            'email'    => 'conta.alvo@example.com',
            'password' => bin2hex(random_bytes(12)),
        ]);

        $porRotulo = array_column($this->servico->listarContas()['contas'], null, 'rotulo');

        $this->assertSame('conta.alvo@example.com', $porRotulo['conta.alvo']['email']);
    }

    public function testListarContasUsaOEmailQuandoNaoHaNomeDeUsuario(): void
    {
        $contas = model(UserModel::class);
        $contas->save(new User(['active' => true]));
        /** @var User $semNome */
        $semNome = $contas->findById($contas->getInsertID());
        $semNome->createEmailIdentity([
            'email'    => 'sem.nome@example.com',
            'password' => bin2hex(random_bytes(12)),
        ]);

        $rotulos = array_column($this->servico->listarContas()['contas'], 'rotulo');

        $this->assertContains('sem.nome@example.com', $rotulos);
    }

    /**
     * @param array<string, mixed> $permissao
     *
     * @return array{direta: bool, herdada_de: list<string>, efetiva: bool}
     */
    private function situacao(array $permissao): array
    {
        return [
            'direta'     => $permissao['direta'],
            'herdada_de' => $permissao['herdada_de'],
            'efetiva'    => $permissao['efetiva'],
        ];
    }

    private function auditoriaQueNaoGrava(): AuditoriaPermissaoModel
    {
        return new class () extends AuditoriaPermissaoModel {
            public function registrar(int $usuarioId, string $permissao, string $acao, int $operadorId): bool
            {
                return false;
            }
        };
    }

    private function auditoriaComOperadorInexistente(): AuditoriaPermissaoModel
    {
        return new class () extends AuditoriaPermissaoModel {
            public function registrar(int $usuarioId, string $permissao, string $acao, int $operadorId): bool
            {
                // Falha de verdade no banco: `created_by` aponta para uma conta que não existe (chave estrangeira).
                return parent::registrar($usuarioId, $permissao, $acao, 999999);
            }
        };
    }

    private function auditoriaQueLancaExcecao(): AuditoriaPermissaoModel
    {
        return new class () extends AuditoriaPermissaoModel {
            public function registrar(int $usuarioId, string $permissao, string $acao, int $operadorId): bool
            {
                throw new RuntimeException('Falha simulada na auditoria.');
            }
        };
    }
}
