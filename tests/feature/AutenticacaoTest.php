<?php

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

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

    public function testExibeTelaDeLoginComIdentidadeVisualELabels(): void
    {
        $resposta = $this->get('login');

        $resposta->assertOK();
        $resposta->assertSee('PlanIFica Hub');
        $resposta->assertSee('Entrar');
        $resposta->assertSeeElement('label[for=email]');
        $resposta->assertSeeElement('label[for=password]');
        $resposta->assertSeeElement('input[name=email]');
        $resposta->assertSeeElement('input[name=password]');
        $resposta->assertSee('assets/vendors/mdi/css/materialdesignicons.min.css');
        $resposta->assertSee('assets/css/modern-vertical/style.css');
        $resposta->assertSee('assets/css/custom.css');
    }

    public function testExibeCadastroComTokenCsrf(): void
    {
        $resposta = $this->get('register');

        $resposta->assertOK();
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');
    }

    public function testRecusaLoginSemTokenCsrf(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('login', ['email' => 'teste@example.com', 'password' => $this->senha]);
    }

    public function testCadastraUsuarioComSenhaProtegida(): void
    {
        $resposta = $this->cadastrarUsuario();

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->seeInDatabase('users', ['username' => 'usuario.teste']);
        $identidade = $this->db->table('auth_identities')->where('secret', 'teste@example.com')->get()->getRowArray();

        $this->assertNotNull($identidade);
        $this->assertTrue(password_verify($this->senha, $identidade['secret2']));
        $this->assertTrue(auth()->loggedIn());
    }

    public function testRecusaCadastroInvalido(): void
    {
        $resposta = $this->enviarFormulario('register', [
            'email' => 'email-invalido',
            'username' => 'usuario.teste',
            'password' => $this->senha,
            'password_confirm' => $this->senha,
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->dontSeeInDatabase('users', ['username' => 'usuario.teste']);
    }

    public function testAutenticaUsuarioCadastrado(): void
    {
        $this->cadastrarUsuario();
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
        $this->cadastrarUsuario();
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
        $this->cadastrarUsuario();

        $resposta = $this->enviarFormulario('logout', []);

        $resposta->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testRecusaLogoutSemTokenCsrf(): void
    {
        $this->cadastrarUsuario();
        $this->expectException(SecurityException::class);

        $this->withSession($_SESSION)->post('logout');
    }

    public function testNaoPermiteLogoutPorGet(): void
    {
        $this->cadastrarUsuario();
        $this->expectException(PageNotFoundException::class);

        $this->withSession($_SESSION)->get('logout');
    }

    private function cadastrarUsuario(): TestResponse
    {
        return $this->enviarFormulario('register', [
            'email' => 'teste@example.com',
            'username' => 'usuario.teste',
            'password' => $this->senha,
            'password_confirm' => $this->senha,
        ]);
    }

    private function enviarFormulario(string $rota, array $campos): TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->withSession($_SESSION)->post($rota, $campos);
    }
}
