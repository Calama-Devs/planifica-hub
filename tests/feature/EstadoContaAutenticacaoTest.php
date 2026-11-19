<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EstadoContaModel;
use App\Services\AutenticacaoSessaoService;
use App\Services\EstadoContaService;
use App\Services\RedefinirSenhaService;
use CodeIgniter\Email\Email;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Actions\Email2FA;
use CodeIgniter\Shield\Authentication\AuthenticationException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Filters\SessionAuth;
use CodeIgniter\Shield\Models\RememberModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
use Tests\Support\Traits\CriaContasTrait;

final class EstadoContaAutenticacaoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use CriaContasTrait;

    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private EstadoContaService $servico;
    private User $operador;
    private User $conta;
    private string $senha;
    private EstadoContaModel $contas;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->senha = 'Inicial@9' . bin2hex(random_bytes(16));
        $this->operador = $this->criarConta('operador.estado', 'superadmin');
        $this->conta = $this->criarConta('conta.estado', 'user');

        foreach ([$this->operador, $this->conta] as $conta) {
            $conta->createEmailIdentity([
                'email' => $conta->username . '@example.com',
                'password' => $this->senha,
            ]);
        }

        $this->contas = new EstadoContaModel();
        $this->servico = new EstadoContaService($this->contas);
    }

    public function testConfiguraIntegracaoDaSessaoComOEstadoAdministrativo(): void
    {
        $this->assertSame(SessionAuth::class, config('Filters')->aliases['session']);
        $this->assertInstanceOf(AutenticacaoSessaoService::class, auth()->getAuthenticator());
        $this->assertNull(setting('Auth.actions')['register']);
    }

    public function testDesativacaoBloqueiaSenhaMesmoSemAtivacaoPorEmail(): void
    {
        $this->desativarConta();

        $conta = $this->recarregar($this->conta);
        $this->assertFalse((bool) $conta->active);
        $this->assertTrue($conta->isActivated());
        $resultado = auth()->attempt($this->credenciais());

        $this->assertFalse($resultado->isOK());
        $this->assertSame(lang('Auth.badAttempt'), $resultado->reason());
        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session(setting('Auth.sessionConfig')['field']));
    }

    public function testLoginHttpRecusaContaDesativada(): void
    {
        $this->desativarConta();

        $resposta = $this->withSession($_SESSION)->post('login', $this->credenciais() + [
            csrf_token() => csrf_hash(),
        ]);

        $resposta->assertRedirectTo('/login');
        $resposta->assertSessionHas('error', lang('Auth.badAttempt'));
        $this->assertFalse(auth()->loggedIn());
    }

    public function testEncerraSessaoAnteriorAoAcessarPaginaProtegida(): void
    {
        auth()->login($this->conta);
        $this->assertTrue(auth()->loggedIn());
        $this->desativarConta();

        // A instância autenticada ainda tem active=true, como em outra aba/dispositivo.
        $this->assertTrue((bool) $this->conta->active);
        $resposta = $this->withSession($_SESSION)->get('sobre');

        $resposta->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session(setting('Auth.sessionConfig')['field']));
    }

    public function testRecarregaEstadoAoRetomarSessaoDeRequisicaoAnterior(): void
    {
        auth()->login($this->conta);
        $this->desativarConta();
        $sessaoAnterior = $_SESSION;
        $this->resetServices();

        $resposta = $this->withSession($sessaoAnterior)->get('/');

        $resposta->assertRedirectTo('/login');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testSessaoOciosaContinuaRevogadaAposReativacaoSemAfetarNovoLogin(): void
    {
        $this->assertTrue(auth()->attempt($this->credenciais())->isOK());
        $sessaoAnterior = $_SESSION;
        $versaoInicial = (int) $this->recarregar($this->conta)->versao_sessao;
        $this->assertSame(1, $versaoInicial);

        $this->desativarConta();
        $this->assertSame($versaoInicial + 1, (int) $this->recarregar($this->conta)->versao_sessao);
        $resultado = $this->servico->reativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);
        $this->assertTrue($resultado['sucesso']);
        $this->assertSame($versaoInicial + 1, (int) $this->recarregar($this->conta)->versao_sessao);

        // A primeira requisição dessa sessão ocorre só depois da reativação.
        $this->reiniciarRequisicao();
        $resposta = $this->withSession($sessaoAnterior)->get('sobre');
        $resposta->assertRedirectTo('/login');
        $resposta->assertSessionHas('error', 'Sua sessão foi encerrada. Entre novamente para continuar.');
        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session(setting('Auth.sessionConfig')['field']));

        $this->reiniciarRequisicao();
        $this->assertTrue(auth()->remember()->attempt($this->credenciais())->isOK());
        $sessaoNova = $_SESSION;
        $this->seeInDatabase('auth_remember_tokens', ['user_id' => $this->conta->id]);

        // Outra requisição com a sessão antiga não pode invalidar nem o login nem o token novos.
        $this->reiniciarRequisicao();
        $resposta = $this->withSession($sessaoAnterior)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('sobre');
        $resposta->assertStatus(403);
        $resposta->assertJSONFragment(['sucesso' => false, 'codigo' => 'SESSAO_REVOGADA']);
        $this->seeInDatabase('auth_remember_tokens', ['user_id' => $this->conta->id]);

        $this->reiniciarRequisicao();
        $this->withSession($sessaoNova)->withHeaders([])->get('sobre')->assertOK();
        $this->assertSame($this->conta->id, auth()->id());
    }

    public function testExigeNovoLoginDeSessaoSemVersao(): void
    {
        $campo = setting('Auth.sessionConfig')['field'];

        $this->withSession([$campo => ['id' => $this->conta->id]])->get('sobre')->assertRedirectTo('/login');

        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session($campo));
    }

    public function testSessaoPendenteDesativadaNaoSegueParaAcaoDoShield(): void
    {
        auth()->login($this->conta);
        $campo = setting('Auth.sessionConfig')['field'];
        $sessaoPendente = $_SESSION;
        $sessaoPendente[$campo]['auth_action'] = Email2FA::class;
        $this->desativarConta();
        $this->reiniciarRequisicao();

        $resposta = $this->withSession($sessaoPendente)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('admin/permissoes');

        $resposta->assertStatus(403);
        $resposta->assertJSONFragment(['codigo' => 'USUARIO_INDISPONIVEL']);
        $this->assertNull(session($campo));
    }

    public function testConsultaDiretaDaSessaoRevogadaRetornaNaoAutenticado(): void
    {
        auth()->login($this->conta);
        $this->desativarConta();

        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session(setting('Auth.sessionConfig')['field']));
    }

    public function testRecusaOperacaoAjaxDeSessaoAnteriorSemAlterarSenha(): void
    {
        auth()->login($this->conta);
        $hashAnterior = $this->recarregar($this->conta)->password_hash;
        $this->desativarConta();

        $resposta = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->withSession($_SESSION)->post('perfil/alterar-senha', [
                'senha-atual' => $this->senha,
                'senha-nova' => 'OutraSenha@456',
                'confirmar-senha-nova' => 'OutraSenha@456',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertStatus(403);
        $resposta->assertJSONFragment(['sucesso' => false, 'codigo' => 'USUARIO_INDISPONIVEL']);
        $this->assertSame($hashAnterior, $this->recarregar($this->conta)->password_hash);
        $this->assertFalse(auth()->loggedIn());
    }

    public function testRevogaLembrarMeELinkDeLoginSemRemoverIdentidadeDeSenha(): void
    {
        (new RememberModel())->rememberUser(
            $this->conta,
            'seletor-teste',
            hash('sha256', 'validador-teste'),
            Time::now()->addDays(1)->toDateTimeString(),
        );
        $this->criarToken('magic-link', 'login-anterior');
        $this->criarToken('magic-link-reset', 'recuperacao-anterior');
        $hashAnterior = $this->recarregar($this->conta)->password_hash;

        $this->desativarConta();

        $this->dontSeeInDatabase('auth_remember_tokens', ['user_id' => $this->conta->id]);
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $this->conta->id, 'type' => 'magic-link']);
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $this->conta->id, 'type' => 'magic-link-reset']);
        $this->assertSame($hashAnterior, $this->recarregar($this->conta)->password_hash);
    }

    public function testNaoEmiteLinkMagicoDeLoginParaContaAtiva(): void
    {
        $this->assertFalse(setting('Auth.allowMagicLinkLogins'));

        $resposta = $this->withSession($_SESSION)->post('login/magic-link', [
            'email' => $this->credenciais()['email'],
            csrf_token() => csrf_hash(),
        ]);

        $resposta->assertRedirectTo('/login');
        $resposta->assertSessionHas('error', lang('Auth.magicLinkDisabled'));
        $this->dontSeeInDatabase('auth_identities', [
            'user_id' => $this->conta->id,
            'type' => 'magic-link',
        ]);
    }

    public function testRecusaLinkMagicoDeLoginJaEmitido(): void
    {
        $token = bin2hex(random_bytes(10));
        $this->criarToken('magic-link', $token);

        $resposta = $this->get('login/verify-magic-link?token=' . $token);

        $resposta->assertRedirectTo('/login');
        $resposta->assertSessionHas('error', lang('Auth.magicLinkDisabled'));
        $this->assertFalse(auth()->loggedIn());
    }

    public function testCookieAnteriorNaoRestauraLoginMesmoAposReativacao(): void
    {
        $lembrancas = new RememberModel();
        $lembrancas->rememberUser(
            $this->conta,
            'seletor-anterior',
            hash('sha256', 'validador-anterior'),
            Time::now()->addDays(1)->toDateTimeString(),
        );
        $this->desativarConta();
        $this->servico->reativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);
        $nomeCookie = setting('Cookie.prefix') . setting('Auth.sessionConfig')['rememberCookieName'];
        $requisicao = service('request');
        $requisicao->setGlobal('cookie', [$nomeCookie => 'seletor-anterior:validador-anterior']);

        try {
            $sessao = new AutenticacaoSessaoService(new UserModel());

            $this->assertFalse($sessao->loggedIn());
            $this->assertNull($sessao->getUser());
        } finally {
            $requisicao->setGlobal('cookie', []);
        }
    }

    public function testCookieValidoDeContaAtivaAindaRestauraLogin(): void
    {
        (new RememberModel())->rememberUser(
            $this->conta,
            'seletor-ativo',
            hash('sha256', 'validador-ativo'),
            Time::now()->addDays(1)->toDateTimeString(),
        );
        $nomeCookie = setting('Cookie.prefix') . setting('Auth.sessionConfig')['rememberCookieName'];
        $requisicao = service('request');
        $requisicao->setGlobal('cookie', [$nomeCookie => 'seletor-ativo:validador-ativo']);

        try {
            $sessao = new AutenticacaoSessaoService(new UserModel());

            $this->assertTrue($sessao->loggedIn());
            $this->assertSame($this->conta->id, $sessao->getUser()?->id);
        } finally {
            $requisicao->setGlobal('cookie', []);
        }
    }

    public function testFalhaNaRevogacaoDesfazDesativacaoETokensNaMesmaTransacao(): void
    {
        $versaoAnterior = (int) $this->recarregar($this->conta)->versao_sessao;
        (new RememberModel())->rememberUser(
            $this->conta,
            'seletor-preservado',
            hash('sha256', 'validador-preservado'),
            Time::now()->addDays(1)->toDateTimeString(),
        );
        $identidades = $this->createMock(UserIdentityModel::class);
        $identidades->expects($this->once())->method('deleteIdentitiesByType')
            ->willThrowException(new RuntimeException('Falha simulada na revogação.'));
        $servico = new EstadoContaService($this->contas, null, $identidades);

        $resultado = $servico->desativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('CONTA_ERRO_PERSISTENCIA', $resultado['codigo']);
        $conta = $this->recarregar($this->conta);
        $this->assertTrue((bool) $conta->active);
        $this->assertSame($versaoAnterior, (int) $conta->versao_sessao);
        $this->seeInDatabase('auth_remember_tokens', ['user_id' => $this->conta->id]);
        $this->assertTrue($this->db->transStatus());
        $this->assertTrue(auth()->attempt($this->credenciais())->isOK());
    }

    public function testLoginDiretoNaoAceitaEntidadeAnteriorADesativacao(): void
    {
        $this->desativarConta();
        $this->expectException(AuthenticationException::class);

        try {
            auth()->login($this->conta);
        } finally {
            $this->assertFalse(auth()->loggedIn());
        }
    }

    public function testReativacaoRestauraLoginSemAlterarGruposPermissoesOuBanimento(): void
    {
        $this->conta->addPermission('contas.consultar');
        $this->desativarConta();
        $resultado = $this->servico->reativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);

        $this->assertTrue($resultado['sucesso']);
        $this->assertTrue(auth()->attempt($this->credenciais())->isOK());
        $this->assertTrue(auth()->loggedIn());
        $this->seeInDatabase('auth_groups_users', ['user_id' => $this->conta->id, 'group' => 'user']);
        $this->seeInDatabase('auth_permissions_users', [
            'user_id' => $this->conta->id,
            'permission' => 'contas.consultar',
        ]);
        $this->assertFalse($this->recarregar($this->conta)->isBanned());
        $this->dontSeeInDatabase('auth_remember_tokens', ['user_id' => $this->conta->id]);
    }

    public function testReativacaoNaoRemoveBanimentoIndependente(): void
    {
        model(UserModel::class)->update($this->conta->id, [
            'status' => 'banned',
            'status_message' => 'Bloqueio independente.',
        ]);
        $this->desativarConta();
        $resultado = $this->servico->reativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);

        $this->assertTrue($resultado['sucesso']);
        $this->assertTrue($this->recarregar($this->conta)->isBanned());
        $this->assertFalse(auth()->attempt($this->credenciais())->isOK());
    }

    public function testReativacaoExigeNovoLinkDeRecuperacao(): void
    {
        $tokenAnterior = $this->solicitarLinkDeRecuperacao();
        $this->desativarConta();
        $this->withSession($_SESSION)->post('recuperar-senha', [
            'email' => $this->credenciais()['email'],
            csrf_token() => csrf_hash(),
        ])->assertRedirectTo('/recuperar-senha/enviado');
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $this->conta->id, 'type' => 'magic-link-reset']);
        $this->reativarConta();

        $this->get('recuperar-senha/verificar?token=' . $tokenAnterior)
            ->assertRedirectTo('/recuperar-senha');
        $this->assertNull(session()->getTempdata('autorizacaoRedefinicaoSenha'));
        $this->assertFalse(auth()->loggedIn());

        $tokenNovo = $this->solicitarLinkDeRecuperacao();
        $this->withSession($_SESSION)->get('recuperar-senha/verificar?token=' . $tokenNovo)
            ->assertRedirectTo('/reset-password');
        $this->assertFalse(auth()->loggedIn());
        $this->withSession($_SESSION)->post('reset-password', [
            'senha-nova' => 'NovaSenha@123',
            'confirmar-senha-nova' => 'NovaSenha@123',
            csrf_token() => csrf_hash(),
        ])->assertRedirectTo('/login');

        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session()->getTempdata('autorizacaoRedefinicaoSenha'));
        $this->assertFalse(auth()->attempt($this->credenciais())->isOK());
        $this->assertTrue(auth()->attempt([
            'email' => 'conta.estado@example.com',
            'password' => 'NovaSenha@123',
        ])->isOK());
    }

    public function testReativacaoNaoAutorizaFormularioDeRecuperacaoJaAberto(): void
    {
        $token = $this->solicitarLinkDeRecuperacao();
        $this->get('recuperar-senha/verificar?token=' . $token)->assertRedirectTo('/reset-password');
        $this->withSession($_SESSION)->get('reset-password')->assertOK();
        $hashAnterior = $this->recarregar($this->conta)->password_hash;

        $this->desativarConta();
        $this->reativarConta();

        $this->withSession($_SESSION)->post('reset-password', [
            'senha-nova' => 'NovaSenha@123',
            'confirmar-senha-nova' => 'NovaSenha@123',
            csrf_token() => csrf_hash(),
        ])->assertRedirectTo('/recuperar-senha');

        $this->assertNull(session()->getTempdata('autorizacaoRedefinicaoSenha'));
        $this->assertSame($hashAnterior, $this->recarregar($this->conta)->password_hash);
        $this->assertFalse(auth()->loggedIn());
    }

    public function testRecusaGravacaoIniciadaComVersaoAnteriorDaConta(): void
    {
        $contaAnterior = $this->recarregar($this->conta);
        $hashAnterior = $contaAnterior->password_hash;
        $this->desativarConta();
        $this->reativarConta();

        $resultado = (new RedefinirSenhaService())->redefinir($contaAnterior, 'NovaSenha@123', 'NovaSenha@123');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame($hashAnterior, $this->recarregar($this->conta)->password_hash);
    }

    private function solicitarLinkDeRecuperacao(): string
    {
        $email = $this->getMockBuilder(Email::class)->onlyMethods(['send'])->getMock();
        $email->expects($this->once())->method('send')->willReturn(true);
        \Config\Services::injectMock('email', $email);

        $this->withSession($_SESSION)->post('recuperar-senha', [
            'email' => $this->credenciais()['email'],
            csrf_token() => csrf_hash(),
        ])->assertRedirectTo('/recuperar-senha/enviado');
        $identidade = $this->db->table('auth_identities')->where([
            'user_id' => $this->conta->id,
            'type' => 'magic-link-reset',
        ])->get()->getRowArray();

        $this->assertNotNull($identidade);

        return $identidade['secret'];
    }

    private function reativarConta(): void
    {
        $resultado = $this->servico->reativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);
        $this->assertTrue($resultado['sucesso'], $resultado['mensagem']);
    }

    private function desativarConta(): void
    {
        $resultado = $this->servico->desativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);

        $this->assertTrue($resultado['sucesso'], $resultado['mensagem']);
    }

    private function reiniciarRequisicao(): void
    {
        $this->resetServices();
        $this->mockSession();
    }

    /** @return array{email: string, password: string} */
    private function credenciais(): array
    {
        return ['email' => 'conta.estado@example.com', 'password' => $this->senha];
    }

    private function criarToken(string $tipo, string $token): void
    {
        model(UserIdentityModel::class)->insert([
            'user_id' => $this->conta->id,
            'type' => $tipo,
            'secret' => $token,
            'expires' => Time::now()->addHours(1),
        ]);
    }
}
