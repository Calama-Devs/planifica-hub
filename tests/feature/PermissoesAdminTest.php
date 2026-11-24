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
use PHPUnit\Framework\Attributes\DataProvider;

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
        $this->get('admin/permissoes')->assertRedirectTo(route_to('login'));
        $this->get('admin/permissoes/listar')->assertRedirectTo(route_to('login'));
    }

    public function testAcessoNegadoParaUsuarioSemPermissaoAcessosAdministrar(): void
    {
        $usuarioComum = $this->criarUsuario('user');

        $resposta = $this->actingAs($usuarioComum)->get('admin/permissoes');
        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error');

        $this->actingAs($usuarioComum)->get('admin/permissoes/listar')
            ->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testSuperadminAcessaPaginaGruposEPermissoesComControlesPreparados(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/permissoes');

        $resposta->assertOK();
        $resposta->assertSee('Grupos e permissões');
        $resposta->assertSee('Criar grupo');
        $resposta->assertSee('Grupo');
        $resposta->assertSee('Descrição');
        $resposta->assertSee('Usuários vinculados');
        $resposta->assertSee('Ações');

        $html = (string) $resposta->getBody();
        $this->assertStringContainsString('id="tabela-grupos"', $html);
        $this->assertStringContainsString('id="alerta-erro-grupos"', $html);
        $this->assertStringContainsString('data-url="' . url_to('permissoes.grupos.listar') . '"', $html);
        $this->assertStringContainsString('href="' . site_url('admin/permissoes/grupos/novo') . '"', $html);
        $this->assertStringContainsString('data-destino="' . site_url('admin/permissoes/grupos/novo') . '"', $html);
    }

    public function testListagemServerSideRetornaGruposComUsuariosVinculadosEDestinos(): void
    {
        $superadmin = $this->criarUsuario('superadmin');
        $this->criarUsuario('admin');
        $this->criarUsuario('admin');

        $resposta = $this->actingAs($superadmin)->get('admin/permissoes/listar?draw=2&start=0&length=10');
        $resposta->assertOK();

        $json = json_decode($resposta->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertSame(2, $json['draw']);
        $this->assertSame(5, $json['recordsTotal']);
        $this->assertSame(5, $json['recordsFiltered']);
        $this->assertCount(5, $json['data']);

        $porSlug = array_column($json['data'], null, 'identificador');
        $this->assertArrayHasKey('superadmin', $porSlug);
        $this->assertArrayHasKey('admin', $porSlug);
        $this->assertSame(1, $porSlug['superadmin']['usuarios_vinculados']);
        $this->assertTrue($porSlug['superadmin']['protegido']);
        $this->assertSame(2, $porSlug['admin']['usuarios_vinculados']);
        $this->assertFalse($porSlug['admin']['protegido']);
        $this->assertSame(
            site_url('admin/permissoes/grupos/admin/editar'),
            $porSlug['admin']['destinos']['editar']
        );
        $this->assertSame(
            site_url('admin/permissoes/grupos/admin/integrantes'),
            $porSlug['admin']['destinos']['integrantes']
        );
    }

    public function testListagemServerSideFiltraPorNomeOuDescricaoEPagina(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $porNome = $this->actingAs($superadmin)->get('admin/permissoes/listar?search[value]=Desenvolvedor');
        $porNome->assertOK();
        $jsonNome = json_decode($porNome->getJSON(), true);
        $this->assertSame(5, $jsonNome['recordsTotal']);
        $this->assertSame(1, $jsonNome['recordsFiltered']);
        $this->assertSame('developer', $jsonNome['data'][0]['identificador']);

        $porDescricao = $this->actingAs($superadmin)->get('admin/permissoes/listar?search[value]=criar%20contas');
        $porDescricao->assertOK();
        $jsonDescricao = json_decode($porDescricao->getJSON(), true);
        $this->assertSame(1, $jsonDescricao['recordsFiltered']);
        $this->assertSame('admin', $jsonDescricao['data'][0]['identificador']);

        $paginado = $this->actingAs($superadmin)->get('admin/permissoes/listar?start=2&length=2');
        $paginado->assertOK();
        $jsonPaginado = json_decode($paginado->getJSON(), true);
        $this->assertSame(5, $jsonPaginado['recordsTotal']);
        $this->assertCount(2, $jsonPaginado['data']);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    #[DataProvider('provedorParametrosInvalidos')]
    public function testListagemServerSideRecusaParametrosInvalidos(array $parametros): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->actingAs($superadmin)->get('admin/permissoes/listar', $parametros)
            ->assertStatus(422);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function provedorParametrosInvalidos(): array
    {
        return [
            'limite acima de 100'  => [['length' => '101']],
            'limite zero'          => [['length' => '0']],
            'inicio negativo'      => [['start' => '-1']],
            'pesquisa muito longa' => [['search' => ['value' => str_repeat('a', 101)]]],
            'search malformado'    => [['search' => 'texto-simples']],
        ];
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
        $this->userModel->save(new User([
            'username' => 'usuario.' . $grupo . '.' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]));

        /** @var User $usuario */
        $usuario = $this->userModel->findById($this->userModel->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
