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

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetServices();
        Factories::injectMock('config', Menu::class, new Menu());
    }

    protected function tearDown(): void
    {
        Factories::injectMock('config', Menu::class, new Menu());
        parent::tearDown();
    }

    public function testExibeApenasItensPublicosParaPerfilSemPermissaoAdministrativa(): void
    {
        $usuarioComum = $this->criarUsuarioNoGrupo('user');

        $resposta = $this->actingAs($usuarioComum)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('Dashboard');
        $resposta->assertSee('Sobre');
        $resposta->assertDontSee('Contas');
        $resposta->assertDontSee('Permissões');
    }

    public function testExibeItemRestritoParaPerfilComPermissaoEfetiva(): void
    {
        $superadmin = $this->criarUsuarioNoGrupo('superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/permissoes');

        $resposta->assertOK();
        $resposta->assertSee('Dashboard');
        $resposta->assertSee('Sobre');
        $resposta->assertSee('Administração');
        $resposta->assertSee('Permissões');
    }

    public function testExibeItemRestritoQuandoUsuarioPossuiPermissaoDireta(): void
    {
        $usuario = $this->criarUsuarioNoGrupo('user');
        $usuario->addPermission('acessos.administrar');

        $resposta = $this->actingAs($usuario)->get('admin/permissoes');

        $resposta->assertOK();
        $resposta->assertSee('Administração');
        $resposta->assertSee('Permissões');
    }

    public function testDestacaItemAtivoCorrespondenteARotaAtual(): void
    {
        $usuarioComum = $this->criarUsuarioNoGrupo('user');

        $resposta = $this->actingAs($usuarioComum)->get('sobre');

        $resposta->assertOK();
        $itens = service('menu')->filtrarItensVisiveis($usuarioComum);

        $mapaAtivos = array_column($itens, 'ativo', 'titulo');

        $this->assertFalse($mapaAtivos['Dashboard'] ?? true);
        $this->assertTrue($mapaAtivos['Sobre'] ?? false);
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
        $usuarioComum = $this->criarUsuarioNoGrupo('user');

        $resposta = $this->actingAs($usuarioComum)->get('admin/permissoes');

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
