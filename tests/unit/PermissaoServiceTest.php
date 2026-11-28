<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\IdentificadorGrupoInvalidoException;
use App\Models\PermissaoModel;
use App\Models\EventoAuditoriaModel;
use App\Models\GrupoModel;
use App\Models\GrupoUsuarioModel;
use App\Services\AuditoriaService;
use App\Services\PermissaoService;
use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

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
        $usuarios->save(new User(['username' => 'admin.teste', 'active' => true]));
        $this->usuarioId = (int) $usuarios->getInsertID();

        /** @var User $usuario */
        $usuario = $usuarios->findById($this->usuarioId);
        $usuario->addGroup('superadmin');
        auth()->login($usuario);
    }

    protected function tearDown(): void
    {
        auth()->logout();

        if ($this->db->tableExists('eventos_auditoria')) {
            $this->db->table('eventos_auditoria')
                ->where('modulo', 'permissoes')
                ->whereIn('acao', ['listar_grupos', 'consultar_grupo'])
                ->delete();
        }

        if ($this->db->tableExists('grupos')) {
            $this->db->table('grupos')->emptyTable();
        }

        parent::tearDown();
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
        $this->assertSame(0, $porSlug['developer']['usuarios_vinculados']);
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

    public function testConsultaIndividualRetornaDadosPersistidosEAuditaOperador(): void
    {
        $grupoModel = model(GrupoModel::class);
        $grupoModel->insert(['identificador' => 'admin', 'ativo' => false]);

        $grupo = $this->service->buscarGrupoPorIdentificador('admin');
        $listagem = $this->service->listarGrupos();
        $grupoListagem = array_column($listagem['grupos'], null, 'identificador')['admin'];

        $this->assertNotNull($grupo);
        $this->assertSame($grupoListagem, $grupo);
        $this->assertSame('admin', $grupo['identificador']);
        $this->assertSame('bloqueado', $grupo['situacao']);
        $this->assertFalse($grupo['ativo']);
        $this->assertArrayHasKey('usuarios_vinculados', $grupo);
        $this->assertArrayHasKey('protegido', $grupo);
        $this->seeInDatabase('eventos_auditoria', [
            'modulo'        => 'permissoes',
            'acao'          => 'consultar_grupo',
            'entidade_tipo' => 'grupo',
            'entidade_id'   => 'admin',
            'usuario_id'    => $this->usuarioId,
            'resultado'     => 'concluida',
        ]);
    }

    public function testGrupoInexistenteRetornaNuloEAuditaRecusa(): void
    {
        $this->assertNull($this->service->buscarGrupoPorIdentificador('inexistente'));

        $this->seeInDatabase('eventos_auditoria', [
            'acao'          => 'consultar_grupo',
            'entidade_tipo' => 'grupo',
            'entidade_id'   => 'inexistente',
            'resultado'     => 'recusada',
        ]);
    }

    public function testListagemContaUsuariosDistintosEIgnoraSoftDelete(): void
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'admin.vinculo.ativo']));
        /** @var User $usuarioAtivo */
        $usuarioAtivo = $usuarios->findById($usuarios->getInsertID());
        $usuarioAtivo->addGroup('admin');

        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $usuarioAtivo->id,
            'group'      => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $usuarios->save(new User(['username' => 'admin.vinculo.removido']));
        $usuarioRemovidoId = (int) $usuarios->getInsertID();
        /** @var User $usuarioRemovido */
        $usuarioRemovido = $usuarios->findById($usuarioRemovidoId);
        $usuarioRemovido->addGroup('admin');
        $usuarios->delete($usuarioRemovidoId);

        $resultado = $this->service->listarGrupos();
        $porIdentificador = array_column($resultado['grupos'], null, 'identificador');

        $this->assertSame(1, $porIdentificador['admin']['usuarios_vinculados']);
    }

    public function testOrdenacaoPorNomeEmpatadoUsaIdentificador(): void
    {
        /** @var array<string, array<string, string>> $grupos */
        $grupos = setting('AuthGroups.groups');
        $grupos['zeta-personalizado'] = [
            'title'       => 'Grupo repetido',
            'description' => 'Grupo personalizado de teste.',
        ];
        $grupos['alfa-personalizado'] = [
            'title'       => 'Grupo repetido',
            'description' => 'Outro grupo personalizado de teste.',
        ];
        setting('AuthGroups.groups', $grupos);

        $resultado = $this->service->listarGrupos();
        $ids = array_column($resultado['grupos'], 'identificador');
        $posicaoAlfa = array_search('alfa-personalizado', $ids, true);
        $posicaoZeta = array_search('zeta-personalizado', $ids, true);

        $this->assertIsInt($posicaoAlfa);
        $this->assertIsInt($posicaoZeta);
        $this->assertLessThan($posicaoZeta, $posicaoAlfa);
    }

    public function testRecusaConsultaSemPermissaoEAuditaOperador(): void
    {
        auth()->logout();

        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.sem.permissao', 'active' => true]));
        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->addGroup('user');
        auth()->login($usuario);

        try {
            $this->service->listarGrupos();
            $this->fail('A consulta sem permissão deveria ser recusada.');
        } catch (AuthorizationException) {
            $this->seeInDatabase('eventos_auditoria', [
                'acao'       => 'listar_grupos',
                'usuario_id' => $usuario->id,
                'resultado'  => 'recusada',
            ]);
        }
    }

    public function testFalhaAoAuditarConsultaNaoRetornaSucesso(): void
    {
        $auditoria = new class extends AuditoriaService {
            public function registrar(array $evento): void
            {
                throw new RuntimeException('Falha simulada de auditoria.');
            }
        };
        $service = new PermissaoService(
            $this->model,
            model(GrupoUsuarioModel::class),
            model(GrupoModel::class),
            $auditoria
        );

        try {
            $service->listarGrupos();
            $this->fail('A falha de auditoria deveria impedir o sucesso.');
        } catch (RuntimeException $erro) {
            $this->assertSame('Falha simulada de auditoria.', $erro->getMessage());
        }

        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testFalhaNaConsultaGeraEventoErroEAExcecao(): void
    {
        $grupoUsuarioModel = new class extends GrupoUsuarioModel {
            public function contarUsuariosPorGrupo(): array
            {
                throw new RuntimeException('Falha simulada na consulta.');
            }
        };
        $service = new PermissaoService(
            $this->model,
            $grupoUsuarioModel,
            model(GrupoModel::class)
        );

        try {
            $service->listarGrupos();
            $this->fail('A falha de consulta deveria ser propagada.');
        } catch (RuntimeException $erro) {
            $this->assertSame('Falha simulada na consulta.', $erro->getMessage());
        }

        $this->seeInDatabase('eventos_auditoria', [
            'acao'       => 'listar_grupos',
            'usuario_id' => $this->usuarioId,
            'resultado'  => 'erro',
        ]);
    }

    #[DataProvider('provedorIdentificadoresInvalidos')]
    public function testIdentificadorInvalidoEhRecusadoEAuditado(string $identificador): void
    {
        try {
            $this->service->buscarGrupoPorIdentificador($identificador);
            $this->fail('O identificador inválido deveria ser recusado.');
        } catch (IdentificadorGrupoInvalidoException) {
            $this->seeInDatabase('eventos_auditoria', [
                'acao'       => 'consultar_grupo',
                'usuario_id' => $this->usuarioId,
                'resultado'  => 'recusada',
            ]);
        }
    }

    public static function provedorIdentificadoresInvalidos(): array
    {
        return [
            'somente espaços'       => ['   '],
            'acima do limite'       => [str_repeat('a', 256)],
            'caractere de controle' => ["grupo\x7F"],
        ];
    }

    public function testIdentificadorPersonalizadoCompativelComShieldNaoEhAlterado(): void
    {
        /** @var array<string, array<string, string>> $grupos */
        $grupos = setting('AuthGroups.groups');
        $identificador = str_repeat('grupo-', 30);
        $grupos[$identificador] = [
            'title'       => 'Grupo personalizado longo',
            'description' => 'Identificador compatível com a coluna do Shield.',
        ];
        setting('AuthGroups.groups', $grupos);

        $grupo = $this->service->buscarGrupoPorIdentificador($identificador);

        $this->assertNotNull($grupo);
        $this->assertSame($identificador, $grupo['identificador']);
        $this->seeInDatabase('eventos_auditoria', [
            'acao'        => 'consultar_grupo',
            'entidade_id' => null,
            'resultado'   => 'concluida',
        ]);
    }
}
