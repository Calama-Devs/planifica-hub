<?php

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
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

    public function testExibeCadastroComTokenCsrf(): void
    {
        $resposta = $this->get('register');

        $resposta->assertOK();
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');
    }

    public function testRecusaLoginSemTokenCsrf(): void
    {
        $resposta = $this->post('login', [
            'email' => 'teste@example.com',
            'password' => $this->senha,
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error');
        $this->assertFalse(auth()->loggedIn());
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

        $resposta = $this->withSession($_SESSION)->post('logout');

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error');
        $this->assertTrue(auth()->loggedIn());
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

    private function executarRedefinicao(string $senhaNova, string $confirmacao): TestResponse
    {
        return $this->enviarFormulario('reset-password', [
            'senha-nova' => $senhaNova,
            'confirmar-senha-nova' => $confirmacao,
        ]);
    }

    private function buscarHashDaSenha(): string
    {
        $identidade = $this->db
            ->table('auth_identities')
            ->select('secret2')
            ->where('secret', 'teste@example.com')
            ->get()
            ->getRowArray();

        $this->assertNotNull($identidade);

        return $identidade['secret2'];
    }

    private function ativarUsuarioAutenticado(): void
    {
        $usuario = auth()->user();

        $this->assertNotNull($usuario);

        $usuario->active = 1;
        $this->db
            ->table('users')
            ->where('id', $usuario->id)
            ->update(['active' => 1]);
    }

    public function testExibeSolicitacaoDeRedefinicaoComTokenCsrf(): void
    {
        $resposta = $this->get('forgot-password');

        $resposta->assertOK();
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');
        $resposta->assertSeeElement('input[name=email]');
        $resposta->assertSeeElement('form[data-envio-unico=true]');
        $resposta->assertSeeElement('button[data-texto-processando=Enviando...]');
        $resposta->assertSeeElement('script[data-script-envio-unico=true]');
    }

    public function testRedirecionaLoginPorMagicLinkParaRedefinicao(): void
    {
        session()->setTempdata('magicLogin', true);

        $redirecionamento = config(\Config\Auth::class)->loginRedirect();

        $this->assertStringEndsWith('/reset-password', $redirecionamento);
    }

    public function testBloqueiaRedefinicaoSemAutenticacaoPorMagicLink(): void
    {
        $this->cadastrarUsuario();
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarRedefinicao('NovaSenha@123', 'NovaSenha@123');

        $resposta->assertRedirectTo('/login/magic-link');
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testRecusaNovaSenhaInvalida(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarRedefinicao('curta', 'curta');

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error');
        $resposta->assertSessionMissing('_ci_old_input');
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testRecusaSenhaComInformacaoPessoalPeloShield(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();
        $senhaPessoal = 'Usuario.teste@123';

        $resposta = $this->executarRedefinicao($senhaPessoal, $senhaPessoal);

        $resposta->assertRedirect();
        $resposta->assertSessionHas(
            'error',
            'A senha nao pode conter informacoes do nome de usuario ou do e-mail.'
        );
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testRecusaSenhaComumPeloShield(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarRedefinicao('password', 'password');

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error', 'A senha informada e muito comum. Escolha outra senha.');
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testMantemValidacaoAdicionalDeLetraMaiuscula(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarRedefinicao('nova-senha@123', 'nova-senha@123');

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error', 'A senha deve conter pelo menos uma letra maiuscula.');
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testRecusaConfirmacaoDeSenhaDivergente(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarRedefinicao('NovaSenha@123', 'OutraSenha@123');

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error');
        $resposta->assertSessionMissing('_ci_old_input');
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testRecusaRedefinicaoSemTokenCsrf(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->withSession($_SESSION)->post('reset-password', [
            'senha-nova' => 'NovaSenha@123',
            'confirmar-senha-nova' => 'NovaSenha@123',
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error');
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testImpedeSolicitarMagicLinkComSessaoAutenticada(): void
    {
        $this->cadastrarUsuario();

        $resposta = $this->withSession($_SESSION)->get('forgot-password');

        $resposta->assertRedirectTo(site_url('/'));
        $resposta->assertSessionHas('error');
    }

    public function testImpedeVerificarMagicLinkComSessaoAutenticada(): void
    {
        $this->cadastrarUsuario();

        $resposta = $this->withSession($_SESSION)->get('login/verify-magic-link?token=invalido');

        $resposta->assertRedirectTo(site_url('/'));
        $resposta->assertSessionHas('error');
    }

    public function testRestringeSessaoDoMagicLinkARedefinicao(): void
    {
        $this->cadastrarUsuario();
        session()->setTempdata('magicLogin', true);

        $resposta = $this->withSession($_SESSION)->get('/');

        $resposta->assertRedirectTo(site_url('reset-password'));
        $resposta->assertSessionHas('error');
    }

    public function testPermiteTelaDeRedefinicaoNaSessaoDoMagicLink(): void
    {
        $this->cadastrarUsuario();
        session()->setTempdata('magicLogin', true);

        $resposta = $this->withSession($_SESSION)->get('reset-password');

        $resposta->assertOK();
        $resposta->assertSeeElement('input[name=senha-nova]');
        $resposta->assertSeeElement('form[data-envio-unico=true]');
        $resposta->assertSeeElement('button[data-texto-processando=Salvando...]');
        $resposta->assertSeeElement('script[data-script-envio-unico=true]');
    }

    public function testRedefineSenhaEEncerraSessao(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $senhaNova = 'NovaSenha@123';

        $resposta = $this->executarRedefinicao($senhaNova, $senhaNova);

        $resposta->assertRedirectTo('/login');
        $this->assertTrue(password_verify($senhaNova, $this->buscarHashDaSenha()));
        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session()->getTempdata('magicLogin'));
        $resposta->assertSessionHas('success');
    }
}
