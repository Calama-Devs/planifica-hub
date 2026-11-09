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

        $this->senha = 'Inicial@9' . bin2hex(random_bytes(24));
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
        $camposValidos = setting('Auth.validFields') ?? ['email'];
        if (count($camposValidos) > 1) {
            $campoIdentificador = 'login';
        } elseif ($camposValidos === ['username']) {
            $campoIdentificador = 'username';
        } else {
            $campoIdentificador = 'email';
        }

        $resposta->assertSeeElement('label[for=' . $campoIdentificador . ']');
        $resposta->assertSeeElement('input[name=' . $campoIdentificador . ']');
        $resposta->assertSeeElement('label[for=password]');
        $resposta->assertSeeElement('input[name=password]');
        $resposta->assertSee('assets/vendor/mdi/css/materialdesignicons.min.css');
        $resposta->assertSee('assets/css/modern-vertical/style.css');
        $resposta->assertSee('assets/css/custom.css');
    }

    public function testRenderizaCampoUsuarioQuandoConfiguradoApenasParaUsername(): void
    {
        service('settings')->set('Auth.validFields', ['username']);

        $resposta = $this->get('login');

        $resposta->assertOK();
        $resposta->assertSeeElement('label[for=username]');
        $resposta->assertSeeElement('input[name=username]');

        service('settings')->forget('Auth.validFields');
    }

    public function testRenderizaCampoLoginQuandoConfiguradoParaMultiplosValidFields(): void
    {
        service('settings')->set('Auth.validFields', ['email', 'username']);

        $resposta = $this->get('login');

        $resposta->assertOK();
        $resposta->assertSeeElement('label[for=login]');
        $resposta->assertSeeElement('input[name=login]');
        $resposta->assertSee('E-mail ou Usuário');

        service('settings')->forget('Auth.validFields');
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

    private function cadastrarUsuario(): User
    {
        $usuario = $this->criarUsuario();
        auth()->login($usuario);

        return $usuario;
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

    private function executarAlteracaoPeloPerfil(
        string $senhaAtual,
        string $senhaNova,
        string $confirmacao
    ): TestResponse {
        return $this->enviarFormulario('perfil/alterar-senha', [
            'senha-atual' => $senhaAtual,
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
            'A senha não pode conter informações do nome de usuário ou do e-mail.'
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
        $resposta->assertSessionHas('error', 'A senha informada é muito comum. Escolha outra senha.');
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
        $resposta->assertSessionHas('error', 'A senha deve conter pelo menos uma letra maiúscula.');
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
        $this->expectException(SecurityException::class);

        $this->withSession($_SESSION)->post('reset-password', [
            'senha-nova' => 'NovaSenha@123',
            'confirmar-senha-nova' => 'NovaSenha@123',
        ]);
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
        $resposta->assertDontSeeElement('input[name=senha-atual]');
        $resposta->assertDontSeeElement('div[id=modal-alterar-senha]');
        $resposta->assertSee('Voltar para o login');
        $resposta->assertSeeElement('input[name=senha-nova]');
        $resposta->assertSeeElement('form[data-envio-unico=true]');
        $resposta->assertSeeElement('button[data-texto-processando=Salvando...]');
        $resposta->assertSeeElement('script[data-script-envio-unico=true]');
    }

    public function testExibeAlteracaoPeloPerfilComSenhaAtual(): void
    {
        $this->cadastrarUsuario();

        $resposta = $this->withSession($_SESSION)->get('/');

        $resposta->assertOK();
        $resposta->assertSeeElement('div[id=modal-alterar-senha]');
        $resposta->assertSeeElement('input[name=senha-atual]');
        $resposta->assertSeeElement('input[name=senha-nova]');
        $resposta->assertSeeElement('input[name=confirmar-senha-nova]');
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');
    }

    public function testRecusaAlteracaoPeloPerfilSemAutenticacao(): void
    {
        $resposta = $this->executarAlteracaoPeloPerfil('Atual@123', 'NovaSenha@123', 'NovaSenha@123');

        $resposta->assertRedirectTo('/login');
    }

    public function testRecusaSenhaAtualIncorretaEMantemCampoNaTela(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarAlteracaoPeloPerfil(
            'SenhaAtualIncorreta@123',
            'NovaSenha@123',
            'NovaSenha@123'
        );

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['codigo' => 'SENHA_ATUAL_INCORRETA', 'sucesso' => false]);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->assertTrue(auth()->loggedIn());

        $tela = $this->withSession($_SESSION)->get('/');

        $tela->assertOK();
        $tela->assertSeeElement('input[name=senha-atual]');
    }

    public function testRecusaNovaSenhaIgualASenhaAtual(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarAlteracaoPeloPerfil($this->senha, $this->senha, $this->senha);

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['codigo' => 'NOVA_SENHA_IGUAL_ATUAL', 'sucesso' => false]);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->assertTrue(auth()->loggedIn());
    }

    public function testAlteraSenhaPeloPerfilEExigeNovaSenhaNoProximoLogin(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        $senhaAnterior = $this->senha;
        $senhaNova = 'NovaSenha@123';

        $resposta = $this->executarAlteracaoPeloPerfil($senhaAnterior, $senhaNova, $senhaNova);

        $resposta->assertOK();
        $resposta->assertJSONFragment(['sucesso' => true, 'redirecionamento' => url_to('login')]);
        $resposta->assertSessionHas('success');
        $this->assertFalse(password_verify($senhaAnterior, $this->buscarHashDaSenha()));
        $this->assertTrue(password_verify($senhaNova, $this->buscarHashDaSenha()));
        $this->assertFalse(auth()->loggedIn());

        $loginComSenhaAnterior = $this->enviarFormulario('login', [
            'email' => 'teste@example.com',
            'password' => $senhaAnterior,
        ]);

        $loginComSenhaAnterior->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());

        $loginComSenhaNova = $this->enviarFormulario('login', [
            'email' => 'teste@example.com',
            'password' => $senhaNova,
        ]);

        $loginComSenhaNova->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->assertTrue(auth()->loggedIn());
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

    public function testRemoveRotaGetDeAlteracaoPeloPerfil(): void
    {
        $this->cadastrarUsuario();
        $this->expectException(PageNotFoundException::class);

        $this->withSession($_SESSION)->get('perfil/alterar-senha');
    }

    public function testRecusaAlteracaoPeloPerfilSemCsrf(): void
    {
        $this->cadastrarUsuario();
        $this->expectException(SecurityException::class);

        $this->withSession($_SESSION)->post('perfil/alterar-senha', ['senha-atual' => $this->senha]);
    }

    public function testMagicLinkNaoPodeAlterarPeloPerfil(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        session()->setTempdata('magicLogin', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarAlteracaoPeloPerfil($this->senha, 'NovaSenha@123', 'NovaSenha@123');

        $resposta->assertRedirectTo(site_url('reset-password'));
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
    }

    public function testRetornaAoLoginELimpaSessaoTemporariaSemMudarSenha(): void
    {
        $this->cadastrarUsuario();
        session()->setTempdata('magicLogin', true);
        session()->setTempdata('beforeLoginUrl', site_url('sobre'));
        session()->set('redefinicaoSenhaPendente', true);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->enviarFormulario('reset-password/cancelar', []);

        $resposta->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session()->getTempdata('magicLogin'));
        $this->assertNull(session()->getTempdata('beforeLoginUrl'));
        $this->assertNull(session()->get('redefinicaoSenhaPendente'));
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->withSession($_SESSION)->get('sobre')->assertRedirectTo('/login');
    }

    public function testRetornoAoLoginExigeCsrf(): void
    {
        $this->cadastrarUsuario();
        session()->setTempdata('magicLogin', true);
        $this->expectException(SecurityException::class);

        $this->withSession($_SESSION)->post('reset-password/cancelar');
    }

    public function testRetornoAoLoginNaoAceitaGet(): void
    {
        $this->cadastrarUsuario();
        session()->setTempdata('magicLogin', true);
        $this->expectException(PageNotFoundException::class);

        $this->withSession($_SESSION)->get('reset-password/cancelar');
    }

    public function testPerfilRecusaTokenCsrfInvalidoEmAjax(): void
    {
        $this->cadastrarUsuario();
        $this->expectException(SecurityException::class);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->withSession($_SESSION)->post('perfil/alterar-senha', [csrf_token() => 'invalido']);
    }

    public function testRetornoAoLoginNaoEncerraSessaoNormal(): void
    {
        $this->cadastrarUsuario();

        $this->enviarFormulario('reset-password/cancelar', [])->assertRedirectTo(site_url('/'));

        $this->assertTrue(auth()->loggedIn());
    }

    public function testExpiracaoDoMagicLinkNaoLiberaPaginasInternas(): void
    {
        $this->cadastrarUsuario();
        session()->setTempdata('magicLogin', true);
        config('Auth')->loginRedirect();
        session()->removeTempdata('magicLogin');

        $this->withSession($_SESSION)->get('sobre')->assertRedirectTo(site_url('login'));

        $this->assertFalse(auth()->loggedIn());
    }

    public function testErrosDoPerfilRetornamJsonECsrfRenovado(): void
    {
        $this->cadastrarUsuario();
        $this->ativarUsuarioAutenticado();
        $hashAnterior = $this->buscarHashDaSenha();

        foreach (
            [
            ['', 'NovaSenha@123', 'NovaSenha@123', 'SENHA_ATUAL_OBRIGATORIA'],
            [$this->senha, 'NovaSenha@123', 'OutraSenha@123', 'CONFIRMACAO_DIVERGENTE'],
            [$this->senha, 'curta', 'curta', 'SENHA_RECUSADA_PELO_SHIELD'],
            ] as [$atual, $nova, $confirmacao, $codigo]
        ) {
            $tokenAnterior = csrf_hash();
            $resposta = $this->executarAlteracaoPeloPerfil($atual, $nova, $confirmacao);

            $resposta->assertStatus(422);
            $resposta->assertJSONFragment(['sucesso' => false, 'codigo' => $codigo]);
            $json = json_decode($resposta->getJSON(), true);
            $this->assertSame(csrf_hash(), $json['csrf']['hash']);
            $this->assertNotSame($tokenAnterior, $json['csrf']['hash']);
            $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
            $this->assertTrue(auth()->loggedIn());
            $resposta->assertSessionMissing('_ci_old_input');
        }
    }

    public function testPerfilRecusaUsuarioDesativado(): void
    {
        $usuario = $this->cadastrarUsuario();
        $usuario->active = 0;
        $this->db->table('users')->where('id', $usuario->id)->update(['active' => 0]);
        $hashAnterior = $this->buscarHashDaSenha();

        $resposta = $this->executarAlteracaoPeloPerfil($this->senha, 'NovaSenha@123', 'NovaSenha@123');

        $resposta->assertStatus(403);
        $resposta->assertJSONFragment(['codigo' => 'USUARIO_INDISPONIVEL']);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->assertFalse(auth()->loggedIn());
    }
}
