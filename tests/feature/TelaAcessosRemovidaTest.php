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

/**
 * A antiga tela de Acessos não deixa rotas nem links no sistema.
 * Se uma nova tela voltar a usar estes caminhos, troque este teste pelos da nova tela.
 */
final class TelaAcessosRemovidaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testNenhumaRotaRegistradaApontaParaATelaRemovida(): void
    {
        $rotas = service('routes')->loadRoutes();

        $this->assertArrayHasKey('admin/permissoes', $rotas->getRoutes('GET'));

        foreach (['GET', 'POST'] as $verbo) {
            foreach ($rotas->getRoutes($verbo) as $caminho => $destino) {
                $this->assertStringNotContainsString('admin/acessos', (string) $caminho);

                if (is_string($destino)) {
                    $this->assertStringNotContainsString('AcessoController', $destino);
                }
            }
        }
    }

    public function testListagemDeAcessosRetorna404ParaSuperadmin(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->get('admin/acessos');
    }

    public function testEdicaoDePermissoesDaContaRetorna404ParaSuperadmin(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->get('admin/acessos/' . $superadmin->id . '/permissoes');
    }

    public function testGravacaoDePermissoesDaContaRetorna404ParaSuperadmin(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->post('admin/acessos/' . $superadmin->id . '/permissoes', [
            'permissoes' => ['contas.consultar'],
            csrf_token() => csrf_hash(),
        ]);
    }

    public function testPesquisaDeContasRetorna404ParaSuperadmin(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($superadmin)->get('admin/acessos/pesquisa', ['pesquisa' => 'super']);
    }

    public function testMenuDoSuperadminTemPermissoesESemAcessos(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $resposta = $this->actingAs($superadmin)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('Permissões', '.menu-title');
        $resposta->assertDontSee('Acessos', '.menu-title');
        $resposta->assertDontSee('admin/acessos');
    }

    public function testMenuDeUsuarioComumNaoTemBlocoDeAdministracao(): void
    {
        $usuarioComum = $this->criarUsuario('user');

        $resposta = $this->actingAs($usuarioComum)->get('/');

        $resposta->assertOK();
        $resposta->assertDontSee('admin/permissoes');
        $resposta->assertDontSee('admin/acessos');
    }

    private function criarUsuario(string $grupo): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.' . $grupo . '.' . bin2hex(random_bytes(4))]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
