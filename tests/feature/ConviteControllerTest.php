<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ConviteControllerTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

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

    public function testRotaRecusaUsuarioSemPermissaoContasCriar(): void
    {
        $usuario = $this->criarUsuario('user');

        $resposta = $this->actingAs($usuario)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'sem-permissao@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testRotaRecusaEmissaoSemCsrf(): void
    {
        $operador = $this->criarUsuario('admin');

        $this->expectException(SecurityException::class);

        $this->actingAs($operador)->post('admin/convites', [
            'email' => 'sem-csrf@ifro.edu.br',
        ]);
    }

    public function testRotaRecusaGruposForaDoFormatoDeLista(): void
    {
        $operador = $this->criarUsuario('admin');

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'      => 'grupos@ifro.edu.br',
                'grupos'     => 'admin',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['codigo' => 'CONVITE_DADOS_INVALIDOS']);
    }

    public function testRotasDeFinalizacaoNaoEstaoDisponiveis(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('finalizar-cadastro');
    }

    private function criarUsuario(string $grupo): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.convite.' . bin2hex(random_bytes(8))]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
