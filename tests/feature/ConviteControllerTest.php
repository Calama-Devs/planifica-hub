<?php

declare(strict_types=1);

namespace Tests\Feature;

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
