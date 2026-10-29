<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\AuthGroups;

final class PermissoesAdministrativasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    private const PERMISSOES_ADMINISTRATIVAS = [
        'contas.consultar',
        'contas.criar',
        'acessos.administrar',
    ];

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
    ];

    private AuthGroups $configuracao;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->configuracao = config(AuthGroups::class);
    }

    public function testCatalogoDeclaraPermissoesAdministrativasComDescricao(): void
    {
        foreach (self::PERMISSOES_ADMINISTRATIVAS as $permissao) {
            $this->assertArrayHasKey($permissao, $this->configuracao->permissions);
            $this->assertNotSame('', trim($this->configuracao->permissions[$permissao]));
        }
    }

    public function testCadaPermissaoDescreveUmaAcaoDistinta(): void
    {
        $descricoes = array_values($this->configuracao->permissions);

        $this->assertSame($descricoes, array_values(array_unique($descricoes)));
    }

    public function testIdentificadoresSeguemConvencaoContextoAcao(): void
    {
        foreach (array_keys($this->configuracao->permissions) as $permissao) {
            $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*\.[a-z]+(_[a-z]+)*$/', $permissao);
        }
    }

    public function testMatrizConcedeSomentePermissoesDoCatalogo(): void
    {
        foreach ($this->configuracao->matrix as $grupo => $permissoes) {
            $this->assertArrayHasKey($grupo, $this->configuracao->groups);

            foreach ($permissoes as $permissao) {
                $this->assertArrayHasKey($permissao, $this->configuracao->permissions);
            }
        }
    }

    public function testShieldAceitaPermissoesDoCatalogo(): void
    {
        $usuario = $this->criarUsuario();

        $usuario->addPermission(...self::PERMISSOES_ADMINISTRATIVAS);

        foreach (self::PERMISSOES_ADMINISTRATIVAS as $permissao) {
            $this->assertTrue($usuario->hasPermission($permissao));
        }
    }

    public function testShieldRecusaPermissaoForaDoCatalogo(): void
    {
        $usuario = $this->criarUsuario();

        $this->expectException(AuthorizationException::class);

        $usuario->addPermission('contas.excluir');
    }

    public function testSuperadminPossuiTodasAsPermissoesAdministrativas(): void
    {
        $usuario = $this->criarUsuario('superadmin');

        foreach (self::PERMISSOES_ADMINISTRATIVAS as $permissao) {
            $this->assertTrue($usuario->can($permissao));
        }
    }

    public function testAdminConsultaECriaContasSemAdministrarAcessos(): void
    {
        $usuario = $this->criarUsuario('admin');

        $this->assertTrue($usuario->can('contas.consultar'));
        $this->assertTrue($usuario->can('contas.criar'));
        $this->assertFalse($usuario->can('acessos.administrar'));
    }

    public function testGrupoPadraoNaoPossuiPermissoesAdministrativas(): void
    {
        $usuario = $this->criarUsuario($this->configuracao->defaultGroup);

        $this->assertFalse($usuario->can(...self::PERMISSOES_ADMINISTRATIVAS));
    }

    public function testFiltroDePermissaoLiberaUsuarioAutorizado(): void
    {
        $usuario = $this->criarUsuario('admin');

        $resposta = $this->comRotaProtegida()->actingAs($usuario)->get('rota-protegida');

        $resposta->assertOK();
        $resposta->assertSee('Conteúdo restrito');
    }

    public function testFiltroDePermissaoBloqueiaUsuarioSemPermissao(): void
    {
        $usuario = $this->criarUsuario($this->configuracao->defaultGroup);

        $resposta = $this->comRotaProtegida()->actingAs($usuario)->get('rota-protegida');

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error');
    }

    private function comRotaProtegida(): self
    {
        return $this->withRoutes([
            [
                'GET',
                'rota-protegida',
                static fn (): string => 'Conteúdo restrito',
                ['filter' => 'permission:contas.consultar'],
            ],
        ]);
    }

    private function criarUsuario(?string $grupo = null): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.permissoes']));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());

        if ($grupo !== null) {
            $usuario->addGroup($grupo);
        }

        return $usuario;
    }
}
