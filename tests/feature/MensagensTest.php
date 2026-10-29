<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class MensagensTest extends CIUnitTestCase
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
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testExibeMensagemSucessoComIconeEEsc(): void
    {
        $_SESSION['success'] = 'Operação realizada com <strong>sucesso</strong>.';
        $html = view('partials/mensagens');

        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringContainsString('mdi-check-circle', $html);
        $this->assertStringContainsString('&lt;strong&gt;sucesso&lt;/strong&gt;', $html);
        $this->assertStringNotContainsString('<strong>sucesso</strong>', $html);
    }

    public function testExibeMensagemErroComIconeEEsc(): void
    {
        $_SESSION['error'] = 'Falha ao executar a <em>operação</em>.';
        $html = view('partials/mensagens');

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('mdi-alert-circle', $html);
        $this->assertStringContainsString('&lt;em&gt;operação&lt;/em&gt;', $html);
        $this->assertStringNotContainsString('<em>operação</em>', $html);
    }

    public function testExibeMensagemAvisoComIconeEEsc(): void
    {
        $_SESSION['warning'] = 'Atenção aos dados informados.';
        $html = view('partials/mensagens');

        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringContainsString('mdi-alert', $html);
        $this->assertStringContainsString('Atenção aos dados informados.', $html);
    }

    public function testExibeMensagemInfoComIconeEEsc(): void
    {
        $_SESSION['info'] = 'Notificação informativa.';
        $html = view('partials/mensagens');

        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringContainsString('mdi-information-outline', $html);
        $this->assertStringContainsString('Notificação informativa.', $html);
    }

    public function testExibeMultiplasMensagensComoListaComEscape(): void
    {
        $_SESSION['errors'] = [
            'O campo nome é obrigatório.',
            'O <script>alert("xss")</script> e-mail é inválido.',
        ];
        $html = view('partials/mensagens');

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('<ul class="mb-0 ps-3">', $html);
        $this->assertStringContainsString('<li>O campo nome é obrigatório.</li>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
    }

    public function testPrevencaoDeXssComEscapeTotal(): void
    {
        $_SESSION['error'] = '<img src=x onerror=alert(1)>';
        $html = view('partials/mensagens');

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function testPrecedenciaEvitaAlertasDuplicadosParaSinonimosDaMesmaCategoria(): void
    {
        $_SESSION['success'] = 'Mensagem principal de sucesso.';
        $_SESSION['sucesso'] = 'Mensagem sinônima de sucesso que não deve duplicar.';

        $html = view('partials/mensagens');

        $this->assertSame(1, substr_count($html, 'alert-success'));
        $this->assertStringContainsString('Mensagem principal de sucesso.', $html);
        $this->assertStringNotContainsString('Mensagem sinônima de sucesso que não deve duplicar.', $html);
    }

    public function testPrecedenciaEvitaAlertasDuplicadosParaErros(): void
    {
        $_SESSION['error']  = 'Erro individual prioritário.';
        $_SESSION['erro']   = 'Erro sinônimo.';
        $_SESSION['errors'] = ['Erro em lista.'];

        $html = view('partials/mensagens');

        $this->assertSame(1, substr_count($html, 'alert-danger'));
        $this->assertStringContainsString('Erro individual prioritário.', $html);
        $this->assertStringNotContainsString('Erro sinônimo.', $html);
        $this->assertStringNotContainsString('Erro em lista.', $html);
    }

    public function testExibeAlertasDistintosParaCategoriasDiferentes(): void
    {
        $_SESSION['success'] = 'Sucesso na ação.';
        $_SESSION['warning'] = 'Aviso importante.';

        $html = view('partials/mensagens');

        $this->assertSame(1, substr_count($html, 'alert-success'));
        $this->assertSame(1, substr_count($html, 'alert-warning'));
    }

    public function testRenderizaErrosValidacaoPorCampoComEscape(): void
    {
        $html = view('partials/erros_validacao', [
            'campo' => 'email',
            'erros' => ['email' => 'E-mail <b>inválido</b>.'],
        ]);

        $this->assertStringContainsString('invalid-feedback', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('E-mail &lt;b&gt;inválido&lt;/b&gt;.', $html);
        $this->assertStringNotContainsString('<b>inválido</b>', $html);
    }

    public function testErrosValidacaoNaoRenderizaQuandoCampoSemErro(): void
    {
        $html = view('partials/erros_validacao', [
            'campo' => 'nome',
            'erros' => ['email' => 'Outro erro.'],
        ]);

        $this->assertStringNotContainsString('invalid-feedback', $html);
    }

    public function testExibeFormularioFuncionalNaTelaSobre(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('sobre');

        $resposta->assertOK();
        $resposta->assertSee('Exemplo Funcional de Validação e Mensagens');
        $resposta->assertSee('Testar Envio');
        $resposta->assertSeeElement('input[name=nome]');
        $resposta->assertSeeElement('input[name=email]');
    }

    public function testProcessaFormularioSobreComSucessoQuandoDadosValidos(): void
    {
        $usuario = $this->criarUsuario();

        $dados = [
            'nome'                => 'Servidor IFRO',
            'email'               => 'servidor@ifro.edu.br',
            csrf_token()          => csrf_hash(),
        ];

        $resposta = $this->actingAs($usuario)->withSession($_SESSION)->post('sobre', $dados);

        $resposta->assertRedirectTo(site_url('sobre'));
        $resposta->assertSessionHas('success', 'Formulário validado e enviado com sucesso!');
    }

    public function testProcessaFormularioSobreComErrosQuandoDadosInvalidos(): void
    {
        $usuario = $this->criarUsuario();

        $dados = [
            'nome'       => '',
            'email'      => 'email-invalido',
            csrf_token() => csrf_hash(),
        ];

        $resposta = $this->actingAs($usuario)->withSession($_SESSION)->post('sobre', $dados);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
    }

    public function testRecusaEnvioDeFormularioSobreSemCsrf(): void
    {
        $usuario = $this->criarUsuario();

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);

        $this->actingAs($usuario)->post('sobre', [
            'nome'  => 'Teste',
            'email' => 'teste@ifro.edu.br',
        ]);
    }

    public function testRenderizaErrosEClassesInvalidasNaTelaSobreAposFalha(): void
    {
        $usuario = $this->criarUsuario();

        $_SESSION['errors'] = [
            'email' => 'Informe um endereço de e-mail institucional válido.',
        ];
        $_SESSION['_ci_old_input'] = [
            'post' => [
                'nome'  => 'Carlos Silva',
                'email' => 'email-invalido',
            ],
        ];

        $resposta = $this->actingAs($usuario)->withSession($_SESSION)->get('sobre');

        $resposta->assertOK();
        $this->assertStringContainsString('is-invalid', $resposta->getBody());
        $this->assertStringContainsString('invalid-feedback', $resposta->getBody());
        $this->assertStringContainsString(
            htmlentities('Informe um endereço de e-mail institucional válido.'),
            $resposta->getBody()
        );
        $this->assertStringContainsString('Carlos Silva', $resposta->getBody());
    }

    private function criarUsuario(): User
    {
        $users = model(UserModel::class);
        $user  = new User([
            'username' => 'usuario.mensagens',
            'email'    => 'mensagens@example.com',
            'password' => 'SenhaForte123!',
        ]);

        $users->save($user);

        /** @var User $usuarioCriado */
        $usuarioCriado = $users->findById($users->getInsertID());

        return $usuarioCriado;
    }
}
