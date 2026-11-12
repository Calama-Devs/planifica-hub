<?php

namespace Tests\Feature;

use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Menu;

class MenuPermissoesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
    ];

    protected function setUp(): void
    {
        $this->resetServices();
        Factories::injectMock('config', Menu::class, new Menu());
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Factories::injectMock('config', Menu::class, new Menu());
        parent::tearDown();
    }

    public function testExibeApenasItensPublicosParaPerfilSemPermissaoAdministrativa(): void
    {
        $this->adicionarItemProtegidoAoMenu();
        $usuarioComum = $this->criarUsuarioNoGrupo('user');

        $resposta = $this->comRotasDeTeste()->actingAs($usuarioComum)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('Dashboard');
        $resposta->assertSee('Sobre');
        $resposta->assertDontSee('Contas');
        $resposta->assertDontSee('Permissões');
    }

    public function testExibeItemRestritoParaPerfilComPermissaoEfetiva(): void
    {
        $this->adicionarItemProtegidoAoMenu();
        $admin = $this->criarUsuarioNoGrupo('admin');

        $resposta = $this->comRotasDeTeste()->actingAs($admin)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('Dashboard');
        $resposta->assertSee('Sobre');
        $resposta->assertSee('Contas');
    }

    public function testExibeItemRestritoQuandoUsuarioPossuiPermissaoDireta(): void
    {
        $this->adicionarItemProtegidoAoMenu();
        $usuario = $this->criarUsuarioNoGrupo('user');
        $usuario->addPermission('contas.consultar');

        $resposta = $this->comRotasDeTeste()->actingAs($usuario)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('Contas');
    }

    public function testNaoDuplicaCabecalhoDeCategoriaEmItensConsecutivos(): void
    {
        $this->adicionarItemProtegidoAoMenu();
        $superadmin = $this->criarUsuarioNoGrupo('superadmin');

        $itens = service('menu')->filtrarItensVisiveis($superadmin);

        $categoriasRenderizadas = array_values(array_filter(
            array_column($itens, 'categoria'),
            static fn (?string $categoria): bool => $categoria === 'Administração'
        ));

        $this->assertCount(1, $categoriasRenderizadas);
    }

    public function testUrlDiretaContinuaProtegidaParaUsuarioSemPermissao(): void
    {
        $this->adicionarItemProtegidoAoMenu();
        $usuarioComum = $this->criarUsuarioNoGrupo('user');

        $resposta = $this->comRotasDeTeste()->actingAs($usuarioComum)->get('contas');

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error');
    }

    private function adicionarItemProtegidoAoMenu(): void
    {
        $menu = config(Menu::class);
        $menu->itens[] = [
            'categoria'   => 'Administração',
            'titulo'      => 'Contas',
            'rota'        => 'contas',
            'padraoAtivo' => 'contas*',
            'icone'       => 'mdi mdi-account-multiple text-warning',
            'permissao'   => 'contas.consultar',
        ];
    }

    private function comRotasDeTeste(): self
    {
        return $this->withRoutes([
            ['GET', '/', 'Home::index', ['as' => 'dashboard', 'filter' => 'session']],
            ['GET', 'sobre', 'Home::sobre', ['as' => 'sobre', 'filter' => 'session']],
            [
                'GET',
                'admin/permissoes',
                'PermissaoController::index',
                ['as' => 'permissoes.index', 'filter' => 'permission:acessos.administrar'],
            ],
            [
                'GET',
                'contas',
                static fn (): string => 'Gestão de Contas',
                ['as' => 'contas', 'filter' => 'permission:contas.consultar'],
            ],
        ]);
    }

    private function criarUsuarioNoGrupo(string $grupo): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User([
            'username' => 'menu.' . $grupo . '.' . uniqid(),
        ]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
