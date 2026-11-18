<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filters\ContaAtivaFilter;
use App\Models\EstadoContaModel;
use App\Services\AutenticacaoSessaoService;
use App\Services\EstadoContaService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\AuthenticationException;
use CodeIgniter\Shield\Entities\User;
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

    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings'];

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

        // SQLite cobre a transação e a autenticação reais; apenas o FOR UPDATE exige MySQL.
        $this->contas = $this->getMockBuilder(EstadoContaModel::class)
            ->onlyMethods(['bloquearAlteracoesDeEstado'])->getMock();
        $this->servico = new EstadoContaService($this->contas);
    }

    public function testConfiguraIntegracaoDaSessaoComOEstadoAdministrativo(): void
    {
        $this->assertSame(ContaAtivaFilter::class, config('Filters')->aliases['session']);
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
        $this->seeInDatabase('auth_identities', ['user_id' => $this->conta->id, 'type' => 'magic-link-reset']);
        $this->assertSame($hashAnterior, $this->recarregar($this->conta)->password_hash);
    }

    public function testNaoAutenticaPorLinkMagicoEmitidoParaContaInativa(): void
    {
        $this->desativarConta();
        $this->criarToken('magic-link', 'login-inativo');
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionCode(403);

        try {
            $this->get('login/verify-magic-link?token=login-inativo');
        } finally {
            $this->assertFalse(auth()->loggedIn());
            $this->assertNull(session(setting('Auth.sessionConfig')['field']));
            $this->dontSeeInDatabase('auth_identities', ['secret' => 'login-inativo']);
        }
    }

    public function testPreservaLoginPorLinkMagicoDeContaAtiva(): void
    {
        $this->criarToken('magic-link', 'login-ativo');

        $this->get('login/verify-magic-link?token=login-ativo')
            ->assertRedirectTo(rtrim(site_url('/'), '/'));

        $this->assertTrue(auth()->loggedIn());
        $this->assertSame($this->conta->id, auth()->user()?->id);
        $this->dontSeeInDatabase('auth_identities', ['secret' => 'login-ativo']);
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
        $this->assertTrue((bool) $this->recarregar($this->conta)->active);
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

    public function testRecuperacaoRecusaContaInativaEFuncionaAposReativacaoSemAutenticar(): void
    {
        $this->criarToken('magic-link-reset', 'recuperacao-inativa');
        $this->desativarConta();

        $this->get('recuperar-senha/verificar?token=recuperacao-inativa')
            ->assertRedirectTo('/recuperar-senha');
        $this->assertNull(session()->getTempdata('redefinicaoSenhaUsuarioId'));
        $this->assertFalse(auth()->loggedIn());

        $this->servico->reativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);
        $this->criarToken('magic-link-reset', 'recuperacao-ativa');
        $this->withSession($_SESSION)->get('recuperar-senha/verificar?token=recuperacao-ativa')
            ->assertRedirectTo('/reset-password');
        $this->assertFalse(auth()->loggedIn());
        $this->withSession($_SESSION)->post('reset-password', [
            'senha-nova' => 'NovaSenha@123',
            'confirmar-senha-nova' => 'NovaSenha@123',
            csrf_token() => csrf_hash(),
        ])->assertRedirectTo('/login');

        $this->assertFalse(auth()->loggedIn());
        $this->assertNull(session()->getTempdata('redefinicaoSenhaUsuarioId'));
        $this->assertFalse(auth()->attempt($this->credenciais())->isOK());
        $this->assertTrue(auth()->attempt([
            'email' => 'conta.estado@example.com',
            'password' => 'NovaSenha@123',
        ])->isOK());
    }

    private function desativarConta(): void
    {
        $resultado = $this->servico->desativar((int) $this->conta->id, (int) $this->operador->id, $this->senha);

        $this->assertTrue($resultado['sucesso'], $resultado['mensagem']);
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
