<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PermissoesAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private UserModel $userModel;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        $_SESSION = [];

        $this->userModel = model(UserModel::class);

        $this->seed(\App\Database\Seeds\PermissoesSeeder::class);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAcessoNegadoParaUsuarioDeslogado(): void
    {
        $resposta = $this->get('admin/permissoes');

        $resposta->assertRedirectTo(route_to('login'));
    }

    public function testAcessoNegadoParaUsuarioSemPermissaoAcessosAdministrar(): void
    {
        $usuarioComum = $this->criarUsuario('user');

        $resposta = $this->actingAs($usuarioComum)->get('admin/permissoes');

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error');
    }

    public function testSuperadminAcessaListagemDePermissoesComLayoutExclusivoDeConsulta(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/permissoes');

        $resposta->assertOK();
        $resposta->assertSee('Catálogo de Permissões');
        $resposta->assertSee('contas.consultar');
        $resposta->assertSee('contas.criar');
        $resposta->assertSee('acessos.administrar');

        // Garante ausência total de opções manuais de CRUD na interface (Critério 2)
        $resposta->assertDontSee('Nova Permissão');
        $resposta->assertDontSee('Editar');
        $resposta->assertDontSee('Excluir');
        $resposta->assertDontSee('admin/permissoes/nova');
        $resposta->assertDontSee('admin/permissoes/editar');
    }

    public function testRotaNovaPermissaoRetorna404ParaRequisicaoDireta(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->get('admin/permissoes/nova');
    }

    public function testRotaSalvarPermissaoRetorna404ParaRequisicaoDireta(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->post('admin/permissoes/salvar', [
            'identificador' => 'teste.salvar',
            'descricao'     => 'Tentativa direta de persistência manual',
            csrf_token()    => csrf_hash(),
        ]);
    }

    public function testRotaEditarPermissaoRetorna404ParaRequisicaoDireta(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->get('admin/permissoes/editar/1');
    }

    public function testRotaAtualizarPermissaoRetorna404ParaRequisicaoDireta(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->post('admin/permissoes/atualizar/1', [
            'descricao'  => 'Tentativa direta de atualização manual',
            csrf_token() => csrf_hash(),
        ]);
    }

    private function criarUsuario(string $grupo): User
    {
        $this->userModel->save(new User(['username' => 'usuario.' . $grupo . '.' . bin2hex(random_bytes(4))]));

        /** @var User $usuario */
        $usuario = $this->userModel->findById($this->userModel->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
