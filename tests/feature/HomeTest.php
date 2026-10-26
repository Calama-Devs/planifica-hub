<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class HomeTest extends CIUnitTestCase
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
        parent::setUp();
    }

    public function testRedirecionaInicioParaLoginQuandoNaoAutenticado(): void
    {
        $resposta = $this->get('/');

        $resposta->assertRedirectTo(site_url('login'));
    }

    public function testRedirecionaSobreParaLoginQuandoNaoAutenticado(): void
    {
        $resposta = $this->get('sobre');

        $resposta->assertRedirectTo(site_url('login'));
    }

    public function testExibeInicioQuandoAutenticado(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('PlanIFica Hub');
        $resposta->assertSee('Dashboard');
    }

    public function testExibeSobreReutilizandoLayoutQuandoAutenticado(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('sobre');

        $resposta->assertOK();
        $resposta->assertSee('Sobre o PlanIFica Hub');
        $resposta->assertSee('Dashboard');
    }

    private function criarUsuario(): User
    {
        $users = model(UserModel::class);
        $user  = new User([
            'username' => 'usuario.layout',
            'email'    => 'layout@example.com',
            'password' => 'SenhaForte123!',
        ]);

        $users->save($user);

        /** @var User $usuarioCriado */
        $usuarioCriado = $users->findById($users->getInsertID());

        return $usuarioCriado;
    }
}
