<?php

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

final class AutenticacaoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
    ];

    private string $senha;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->senha = bin2hex(random_bytes(24));
    }

    public function testExibeLoginComTokenCsrf(): void
    {
        $resposta = $this->get('login');

        $resposta->assertOK();
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');
        $resposta->assertSeeElement('input[name=password]');
    }

    public function testRecusaExibicaoDePaginaDeCadastro(): void
    {
        $resposta = $this->get('register');

        $resposta->assertRedirect();
    }

    public function testRecusaLoginSemTokenCsrf(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('login', ['email' => 'teste@example.com', 'password' => $this->senha]);
    }

    public function testRecusaCadastroPublico(): void
    {
        $resposta = $this->enviarFormulario('register', [
            'email' => 'teste@example.com',
            'username' => 'usuario.teste',
            'password' => $this->senha,
            'password_confirm' => $this->senha,
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error');
        $this->dontSeeInDatabase('users', ['username' => 'usuario.teste']);
    }

    public function testAutenticaUsuarioCadastrado(): void
    {
        $this->criarUsuario();
        auth()->logout();

        $resposta = $this->enviarFormulario('login', [
            'email' => 'teste@example.com',
            'password' => $this->senha,
        ]);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->assertTrue(auth()->loggedIn());
    }

    public function testRecusaSenhaIncorreta(): void
    {
        $this->criarUsuario();
        auth()->logout();

        $resposta = $this->enviarFormulario('login', [
            'email' => 'teste@example.com',
            'password' => bin2hex(random_bytes(24)),
        ]);

        $resposta->assertRedirectTo('/login');
        $resposta->assertSessionHas('error');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testEncerraSessaoComTokenCsrf(): void
    {
        $usuario = $this->criarUsuario();
        auth()->login($usuario);

        $resposta = $this->enviarFormulario('logout', []);

        $resposta->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testRecusaLogoutSemTokenCsrf(): void
    {
        $usuario = $this->criarUsuario();
        auth()->login($usuario);
        $this->expectException(SecurityException::class);

        $this->withSession($_SESSION)->post('logout');
    }

    public function testNaoPermiteLogoutPorGet(): void
    {
        $usuario = $this->criarUsuario();
        auth()->login($usuario);
        $this->expectException(PageNotFoundException::class);

        $this->withSession($_SESSION)->get('logout');
    }

    private function criarUsuario(): User
    {
        $usuarios = model(UserModel::class);
        $usuario  = new User(['username' => 'usuario.teste']);
        $usuarios->save($usuario);

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->createEmailIdentity([
            'email'    => 'teste@example.com',
            'password' => $this->senha,
        ]);

        return $usuario;
    }

    private function enviarFormulario(string $rota, array $campos): TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->withSession($_SESSION)->post($rota, $campos);
    }
}
