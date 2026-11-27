<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PermissaoModel;
use App\Services\PermissaoService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;

final class PermissaoServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private PermissaoService $service;
    private PermissaoModel $model;
    private int $usuarioId;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->model   = model(PermissaoModel::class);
        $this->service = new PermissaoService($this->model);

        $this->seed(\App\Database\Seeds\PermissoesSeeder::class);

        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'admin.teste']));
        $this->usuarioId = (int) $usuarios->getInsertID();

        /** @var User $usuario */
        $usuario = $usuarios->findById($this->usuarioId);
        $usuario->addGroup('superadmin');
    }

    public function testSincronizarCatalogoGarantePermissoesDeCodigoNoBancoESemPoluirSettings(): void
    {
        $catalogo = $this->service->sincronizarCatalogo();

        $this->assertArrayHasKey('contas.consultar', $catalogo);
        $this->assertArrayHasKey('contas.criar', $catalogo);
        $this->assertArrayHasKey('acessos.administrar', $catalogo);

        $this->dontSeeInDatabase('settings', ['key' => 'permissions']);

        $authGroups = config(AuthGroups::class);
        $this->assertArrayHasKey('contas.consultar', $authGroups->permissions);
    }

    public function testListarPermissoesRetornaTodasAtivasESincronizaComCodigo(): void
    {
        $permissoes = $this->service->listarPermissoes();

        $this->assertNotEmpty($permissoes);
        $identificadores = array_column($permissoes, 'identificador');

        $this->assertContains('contas.consultar', $identificadores);
        $this->assertContains('contas.criar', $identificadores);
        $this->assertContains('acessos.administrar', $identificadores);
    }

    public function testBuscarPorIdRetornaRegistroCorretoOuNulo(): void
    {
        $registro = $this->model->buscarPorIdentificador('contas.consultar');
        $this->assertNotNull($registro);

        $encontrado = $this->service->buscarPorId((int) $registro['id']);
        $this->assertNotNull($encontrado);
        $this->assertSame('contas.consultar', $encontrado['identificador']);

        $naoEncontrado = $this->service->buscarPorId(999999);
        $this->assertNull($naoEncontrado);
    }

    public function testSincronizacaoCatalogoExecutaComSegurancaSemLancarExcecao(): void
    {
        $catalogo = $this->service->sincronizarCatalogo();
        $this->assertNotEmpty($catalogo);

        setting('AuthGroups.permissions', ['dummy.permissao' => 'Teste']);
        $this->seeInDatabase('settings', ['key' => 'permissions']);

        $catalogoAposLimpeza = $this->service->sincronizarCatalogo();
        $this->assertNotEmpty($catalogoAposLimpeza);
        $this->dontSeeInDatabase('settings', ['key' => 'permissions']);
    }

    public function testAuthGroupsCarregaPermissaoAtivaDoBancoMesmoComOrigemSistema(): void
    {
        $this->model->insert([
            'identificador' => 'auditoria.consultar',
            'descricao'     => 'Permite consultar logs de auditoria institucional',
            'origem'        => 'sistema',
            'created_by'    => $this->usuarioId,
            'updated_by'    => $this->usuarioId,
        ]);

        $authGroups = new AuthGroups();
        $this->assertArrayHasKey('auditoria.consultar', $authGroups->permissions);
        $this->assertSame(
            'Permite consultar logs de auditoria institucional',
            $authGroups->permissions['auditoria.consultar']
        );
    }

    public function testAuthGroupsNuncaSobrescreveDescricaoDePermissaoNativaVersionada(): void
    {
        $registro = $this->model->buscarPorIdentificador('contas.consultar');
        $this->assertNotNull($registro);

        $this->model->update((int) $registro['id'], [
            'descricao' => 'Tentativa de sobrescrever descrição nativa pelo banco',
        ]);

        $authGroups = new AuthGroups();
        $this->assertSame(
            'Consultar a lista e os dados das contas de usuário',
            $authGroups->permissions['contas.consultar']
        );
    }

    public function testListarGruposRetornaContagemDeUsuariosVinculadosEIndicacaoDeProtecao(): void
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'operador.admin']));
        /** @var User $admin */
        $admin = $usuarios->findById($usuarios->getInsertID());
        $admin->addGroup('admin');

        $resultado = $this->service->listarGrupos(0, 10);

        $this->assertSame(5, $resultado['total']);
        $this->assertSame(5, $resultado['filtrados']);
        $this->assertCount(5, $resultado['grupos']);

        $porSlug = array_column($resultado['grupos'], null, 'identificador');
        $this->assertSame(1, $porSlug['superadmin']['usuarios_vinculados']);
        $this->assertTrue($porSlug['superadmin']['protegido']);
        $this->assertTrue($porSlug['user']['protegido']);
        $this->assertSame(1, $porSlug['admin']['usuarios_vinculados']);
        $this->assertFalse($porSlug['admin']['protegido']);
        $this->assertSame('ativo', $porSlug['admin']['situacao']);
    }

    public function testListarGruposAplicaBuscaEPaginacaoCorretamente(): void
    {
        $buscaNome = $this->service->listarGrupos(0, 10, 'beta');
        $this->assertSame(5, $buscaNome['total']);
        $this->assertSame(1, $buscaNome['filtrados']);
        $this->assertSame('beta', $buscaNome['grupos'][0]['identificador']);

        $semResultado = $this->service->listarGrupos(0, 10, 'grupo-inexistente-xyz');
        $this->assertSame(5, $semResultado['total']);
        $this->assertSame(0, $semResultado['filtrados']);
        $this->assertSame([], $semResultado['grupos']);

        $pagina = $this->service->listarGrupos(1, 2);
        $this->assertSame(5, $pagina['total']);
        $this->assertSame(5, $pagina['filtrados']);
        $this->assertCount(2, $pagina['grupos']);
    }

    public function testListarGruposFiltraAtivosEInativosEReativaGrupoComSucesso(): void
    {
        service('settings')->set('AuthGroups.groups', [
            'alfa' => ['title' => 'Grupo Alfa', 'description' => 'Primeiro'],
            'beta' => ['title' => 'Grupo Beta', 'description' => 'Segundo'],
        ]);
        service('settings')->set('AuthGroups.gruposInativos', ['beta']);

        $ativos = $this->service->listarGrupos(0, 10, '', 'ativos');
        $this->assertCount(1, $ativos['grupos']);
        $this->assertSame('alfa', $ativos['grupos'][0]['identificador']);

        $inativos = $this->service->listarGrupos(0, 10, '', 'inativos');
        $this->assertCount(1, $inativos['grupos']);
        $this->assertSame('beta', $inativos['grupos'][0]['identificador']);
        $this->assertFalse($inativos['grupos'][0]['ativo']);

        $ator = auth()->getProvider()->findById($this->usuarioId);
        $this->assertNotNull($ator);
        $ator->addPermission('acessos.administrar');

        $auditoriaMock = $this->createMock(\App\Services\AuditoriaService::class);
        $auditoriaMock->expects($this->once())
            ->method('registrar')
            ->with($this->callback(static function (array $evento): bool {
                return ($evento['modulo'] ?? null) === 'permissoes'
                    && ($evento['acao'] ?? null) === 'reativar_grupo'
                    && ($evento['entidade'] ?? null) === 'grupo'
                    && ($evento['entidade_id'] ?? null) === 'beta'
                    && ($evento['resultado'] ?? null) === 'sucesso';
            }));

        $serviceComAuditoria = new \App\Services\PermissaoService(null, null, $auditoriaMock);

        $resultado = $serviceComAuditoria->reativarGrupo('beta', $this->usuarioId);
        $this->assertTrue($resultado['sucesso'], $resultado['mensagem'] ?? '');
        $this->assertSame('GRUPO_REATIVADO', $resultado['codigo']);

        $inativosApos = $serviceComAuditoria->listarGrupos(0, 10, '', 'inativos');
        $this->assertCount(0, $inativosApos['grupos']);

        $ativosApos = $serviceComAuditoria->listarGrupos(0, 10, '', 'ativos');
        $this->assertCount(2, $ativosApos['grupos']);
    }

    public function testReativarGrupoFalhaSemPermissaoOuParaGrupoJaAtivo(): void
    {
        service('settings')->set('AuthGroups.groups', [
            'alfa' => ['title' => 'Grupo Alfa', 'description' => 'Primeiro'],
        ]);
        service('settings')->set('AuthGroups.gruposInativos', []);

        $semPermissao = $this->service->reativarGrupo('alfa', 0);
        $this->assertFalse($semPermissao['sucesso']);
        $this->assertSame('permissao', $semPermissao['tipo']);
        $this->assertSame('GRUPO_REATIVACAO_NAO_AUTORIZADA', $semPermissao['codigo']);

        $ator = auth()->getProvider()->findById($this->usuarioId);
        $this->assertNotNull($ator);
        $ator->addPermission('acessos.administrar');

        $jaAtivo = $this->service->reativarGrupo('alfa', $this->usuarioId);
        $this->assertFalse($jaAtivo['sucesso']);
        $this->assertSame('regra_negocio', $jaAtivo['tipo']);
        $this->assertSame('GRUPO_JA_ATIVO', $jaAtivo['codigo']);

        $inexistente = $this->service->reativarGrupo('nao-existe', $this->usuarioId);
        $this->assertFalse($inexistente['sucesso']);
        $this->assertSame('nao_encontrado', $inexistente['tipo']);
        $this->assertSame('GRUPO_NAO_ENCONTRADO', $inexistente['codigo']);
    }
}
