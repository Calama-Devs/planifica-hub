<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\AuditoriaPermissaoModel;
use App\Services\PermissaoDiretaService;
use App\Services\PermissaoService;
use CodeIgniter\Pager\Pager;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testNaoConfirmaATransacaoExternaDoChamador(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $this->db->transBegin();
        $resultado = $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);

        // O commit de dentro do serviço só baixa a profundidade: a transação do chamador continua aberta.
        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(1, $this->db->transDepth);

        $this->db->transRollback();

        $this->assertSame(0, $this->db->transDepth);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->dontSeeInDatabase('auditoria_permissoes', ['usuario_id' => $conta->id]);
    }

    public function testRevogacaoNaoConfirmaATransacaoExternaDoChamador(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $this->db->transBegin();
        $resultado = $this->servico->revogar($conta->id, 'contas.consultar', $this->operador->id);
        $this->db->transRollback();

        $this->assertTrue($resultado['sucesso']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
        $this->dontSeeInDatabase('auditoria_permissoes', ['usuario_id' => $conta->id]);
    }

    public function testConfirmaOsDadosQuandoOChamadorConfirmaASuaTransacao(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $this->db->transBegin();
        $this->servico->conceder($conta->id, 'contas.consultar', $this->operador->id);
        $this->db->transCommit();

        // Com o par transBegin/transCommit equilibrado, o commit do chamador é o de verdade e a profundidade zera.
        $this->assertSame(0, $this->db->transDepth);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
        $this->seeNumRecords(1, 'auditoria_permissoes', ['usuario_id' => $conta->id]);
    }

    public function testRollbackDoChamadorDesfazTudoMesmoComFalhaNoMeio(): void
    {
        $primeira = $this->criarConta('conta.alvo', 'user');
        $segunda  = $this->criarConta('conta.outra', 'user');
        $comFalha = new PermissaoDiretaService(null, $this->auditoriaQueLancaExcecao());

        $this->db->transBegin();
        $primeiro = $this->servico->conceder($primeira->id, 'contas.consultar', $this->operador->id);
        $falha    = $comFalha->conceder($segunda->id, 'contas.consultar', $this->operador->id);

        // O serviço só informa a falha: quem abriu a transação continua com ela e decide desfazer tudo.
        $this->assertTrue($primeiro['sucesso']);
        $this->assertSame('PERMISSAO_DIRETA_ERRO_PERSISTENCIA', $falha['codigo']);
        $this->assertSame(1, $this->db->transDepth);

        $this->db->transRollback();

        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $primeira->id]);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $segunda->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testSincronizarConcedeERevogaDeUmaVezEAuditaCadaMudanca(): void
    {
        $conta       = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);
        $matrizAntes = setting('AuthGroups.matrix');

        $marcadas  = ['contas.criar', 'acessos.administrar'];
        $resultado = $this->servico->sincronizar($conta->id, $marcadas, $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSOES_DIRETAS_SALVAS', $resultado['codigo']);
        $this->assertSame(['contas.criar', 'acessos.administrar'], $resultado['concedidas']);
        $this->assertSame(['contas.consultar'], $resultado['revogadas']);
        $this->assertSame([], $resultado['mantidas_por_heranca']);
        $this->assertSame('Permissões diretas salvas: 2 concedidas e 1 revogada.', $resultado['mensagem']);
        $this->assertSame(0, $this->db->transDepth);

        $this->seeNumRecords(2, 'auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.criar']);
        $this->seeInDatabase('auth_permissions_users', [
            'user_id'    => $conta->id,
            'permission' => 'acessos.administrar',
        ]);
        $this->seeNumRecords(2, 'auditoria_permissoes', [
            'usuario_id' => $conta->id,
            'acao'       => AuditoriaPermissaoModel::ACAO_CONCEDIDA,
            'created_by' => $this->operador->id,
        ]);
        $this->seeNumRecords(1, 'auditoria_permissoes', [
            'usuario_id' => $conta->id,
            'permissao'  => 'contas.consultar',
            'acao'       => AuditoriaPermissaoModel::ACAO_REVOGADA,
            'created_by' => $this->operador->id,
        ]);
        // Grupos da conta e matriz ficam como estavam.
        $this->seeNumRecords(1, 'auth_groups_users', ['user_id' => $conta->id, 'group' => 'user']);
        $this->assertSame($matrizAntes, setting('AuthGroups.matrix'));
    }

    public function testSincronizarSemMudancaNaoGravaNada(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resultado = $this->servico->sincronizar($conta->id, ['contas.consultar'], $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSOES_DIRETAS_SEM_ALTERACAO', $resultado['codigo']);
        $this->assertSame([], $resultado['concedidas']);
        $this->assertSame([], $resultado['revogadas']);
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testSincronizarComListaVaziaRevogaTodasAsDiretas(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user', ['contas.consultar', 'contas.criar']);

        $resultado = $this->servico->sincronizar($conta->id, [], $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('Permissões diretas salvas: 2 revogadas.', $resultado['mensagem']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(2, 'auditoria_permissoes', [
            'usuario_id' => $conta->id,
            'acao'       => AuditoriaPermissaoModel::ACAO_REVOGADA,
        ]);
    }

    public function testSincronizarNormalizaOsIdentificadoresEIgnoraRepetidos(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $resultado = $this->servico->sincronizar(
            $conta->id,
            ['  Contas.Consultar ', 'contas.consultar'],
            $this->operador->id,
        );

        $this->assertSame(['contas.consultar'], $resultado['concedidas']);
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(1, 'auditoria_permissoes', ['usuario_id' => $conta->id]);
    }

    public function testSincronizarRecusaPermissaoForaDoCatalogoSemGravarNada(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user', ['contas.criar']);

        // Uma válida e uma inexistente na mesma lista: nem a válida entra, e a que a conta já tinha fica.
        $marcadas  = ['contas.consultar', 'contas.excluir'];
        $resultado = $this->servico->sincronizar($conta->id, $marcadas, $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('validacao', $resultado['tipo']);
        $this->assertSame('PERMISSAO_NAO_CADASTRADA', $resultado['codigo']);
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.criar']);
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testSincronizarMantemOVinculoForaDoCatalogoMarcadoERevogaODesmarcado(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');
        $this->hasInDatabase('auth_permissions_users', [
            'user_id'    => $conta->id,
            'permission' => 'relatorios.antigo',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $mantido = $this->servico->sincronizar($conta->id, ['relatorios.antigo'], $this->operador->id);

        $this->assertSame('PERMISSOES_DIRETAS_SEM_ALTERACAO', $mantido['codigo']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'relatorios.antigo']);

        $revogado = $this->servico->sincronizar($conta->id, [], $this->operador->id);

        $this->assertSame(['relatorios.antigo'], $revogado['revogadas']);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
    }

    public function testSincronizarAvisaQuandoOAcessoContinuaPorHerancaDoGrupo(): void
    {
        $conta = $this->criarConta('conta.alvo', 'admin', ['contas.consultar']);

        $resultado = $this->servico->sincronizar($conta->id, [], $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(['contas.consultar'], $resultado['revogadas']);
        $this->assertSame(['contas.consultar'], $resultado['mantidas_por_heranca']);
        $this->assertStringContainsString('contas.consultar (Administrador de contas)', $resultado['mensagem']);
        $this->assertTrue($this->recarregar($conta)->can('contas.consultar'));
        $this->seeInDatabase('auth_groups_users', ['user_id' => $conta->id, 'group' => 'admin']);
    }

    public function testSincronizarRecusaOperadorSemPermissaoParaAdministrarAcessos(): void
    {
        $semPermissao = $this->criarConta('operador.admin', 'admin');
        $conta        = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resultado = $this->servico->sincronizar($conta->id, ['contas.criar'], $semPermissao->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('permissao', $resultado['tipo']);
        $this->assertSame('ACESSOS_SEM_PERMISSAO', $resultado['codigo']);
        $this->seeNumRecords(1, 'auth_permissions_users', [
            'user_id'    => $conta->id,
            'permission' => 'contas.consultar',
        ]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testSincronizarRecusaContaInexistente(): void
    {
        $resultado = $this->servico->sincronizar(999999, ['contas.consultar'], $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('CONTA_NAO_ENCONTRADA', $resultado['codigo']);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    #[DataProvider('falhasNaSegundaAuditoria')]
    public function testSincronizarEhTudoOuNadaQuandoUmaMudancaFalhaNoMeio(bool $violandoChaveEstrangeira): void
    {
        $conta     = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);
        $auditoria = $this->auditoriaQueFalhaNaSegundaChamada($violandoChaveEstrangeira);
        $servico   = new PermissaoDiretaService(null, $auditoria);
        $marcadas  = ['contas.criar', 'acessos.administrar'];

        // Três mudanças: a primeira concessão passa, a segunda falha na auditoria e desfaz tudo, inclusive a primeira.
        $resultado = $servico->sincronizar($conta->id, $marcadas, $this->operador->id);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame('PERMISSAO_DIRETA_ERRO_PERSISTENCIA', $resultado['codigo']);
        $this->assertSame(0, $this->db->transDepth);
        $this->assertTrue($this->db->transStatus());
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $conta->id, 'permission' => 'contas.consultar']);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);

        // A conexão segue utilizável: a mesma operação funciona normalmente em seguida.
        $repetida = $this->servico->sincronizar($conta->id, $marcadas, $this->operador->id);

        $this->assertTrue($repetida['sucesso']);
        $this->seeNumRecords(2, 'auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(3, 'auditoria_permissoes', ['usuario_id' => $conta->id]);
    }

    /**
     * @return array<string, list<bool>>
     */
    public static function falhasNaSegundaAuditoria(): array
    {
        return [
            'exceção na auditoria'                 => [false],
            'violação de chave estrangeira no banco' => [true],
        ];
    }

    public function testSincronizarNaoConfirmaATransacaoExternaDoChamador(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $this->db->transBegin();
        $resultado = $this->servico->sincronizar($conta->id, ['contas.consultar', 'contas.criar'], $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(1, $this->db->transDepth);

        $this->db->transRollback();

        $this->assertSame(0, $this->db->transDepth);
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $conta->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testPermissaoCadastradaNaTelaDePermissoesEntraNaCategoriaDoSeuContexto(): void
    {
        $cadastro = (new PermissaoService())->cadastrarPermissao([
            'identificador' => 'relatorios_gerais.emitir',
            'descricao'     => 'Permite emitir os relatorios gerais',
        ], $this->operador->id);
        $this->assertTrue($cadastro['sucesso']);

        $conta      = $this->criarConta('conta.alvo', 'user');
        $categorias = array_column($this->servico->consultar($conta->id)['categorias'], null, 'titulo');

        $this->assertArrayHasKey('Relatorios gerais', $categorias);
        $this->assertSame(
            ['relatorios_gerais.emitir'],
            array_column($categorias['Relatorios gerais']['permissoes'], 'identificador'),
        );

        // E ela entra na seleção como qualquer outra permissão do catálogo.
        $resultado = $this->servico->sincronizar($conta->id, ['relatorios_gerais.emitir'], $this->operador->id);

        $this->assertTrue($resultado['sucesso']);
        $this->seeInDatabase('auth_permissions_users', [
            'user_id'    => $conta->id,
            'permission' => 'relatorios_gerais.emitir',
        ]);
    }

    public function testConsultarAgrupaPorContextoEMarcaAPermissaoCritica(): void
    {
        $conta = $this->criarConta('conta.alvo', 'user');

        $visao = $this->servico->consultar($conta->id);

        $this->assertSame(['Acessos', 'Contas'], array_column($visao['categorias'], 'titulo'));
        $this->assertSame(
            ['acessos.administrar'],
            array_column($visao['categorias'][0]['permissoes'], 'identificador'),
        );
        $this->assertSame(
            ['contas.consultar', 'contas.criar'],
            array_column($visao['categorias'][1]['permissoes'], 'identificador'),
        );

        $criticas = array_filter($visao['permissoes'], static fn (array $permissao): bool => $permissao['critica']);

        $this->assertSame(['acessos.administrar'], array_values(array_column($criticas, 'identificador')));
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

    /**
     * Auditoria que funciona na primeira chamada e falha na segunda: simula uma falha no meio de várias mudanças.
     */
    private function auditoriaQueFalhaNaSegundaChamada(bool $violandoChaveEstrangeira): AuditoriaPermissaoModel
    {
        return new class ($violandoChaveEstrangeira) extends AuditoriaPermissaoModel {
            private int $chamadas = 0;

            public function __construct(private readonly bool $violandoChaveEstrangeira)
            {
                parent::__construct();
            }

            public function registrar(int $usuarioId, string $permissao, string $acao, int $operadorId): bool
            {
                $this->chamadas++;

                if ($this->chamadas < 2) {
                    return parent::registrar($usuarioId, $permissao, $acao, $operadorId);
                }

                if ($this->violandoChaveEstrangeira) {
                    // Falha de verdade no banco: `created_by` aponta para uma conta que não existe.
                    return parent::registrar($usuarioId, $permissao, $acao, 999999);
                }

                throw new RuntimeException('Falha simulada na segunda auditoria.');
            }
        };
    }
}
