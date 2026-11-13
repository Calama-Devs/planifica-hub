<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class RodapeTest extends CIUnitTestCase
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

    public function testRenderizaRodapeDiretamenteComAcessibilidadeEClassesBootstrap(): void
    {
        $html = view('partials/rodape');

        $this->assertStringContainsString('<footer class="footer" aria-label="Rodapé institucional">', $html);
        $this->assertStringContainsString('d-sm-flex justify-content-center justify-content-sm-between', $html);
        $this->assertStringContainsString('text-muted text-center text-sm-start d-block d-sm-inline-block', $html);
        $this->assertStringContainsString('text-muted float-none float-sm-end d-block mt-1 mt-sm-0 text-center', $html);
        $this->assertStringContainsString('&copy; ' . date('Y') . ' PlanIFica Hub &middot; Calama Devs', $html);
        $this->assertStringContainsString('IFRO Campus Porto Velho Calama', $html);
    }

    public function testGaranteUsoDeEscapeSeguroNoAnoDinamico(): void
    {
        $caminhoView = APPPATH . 'Views/partials/rodape.php';
        $this->assertFileExists($caminhoView);

        $conteudo = (string) file_get_contents($caminhoView);

        $this->assertMatchesRegularExpression(
            '/<\?=\s*esc\(date\([\'"]Y[\'"]\)\)\s*\?>/',
            $conteudo,
            'O ano dinâmico deve ser escapado com esc(date(\'Y\')) conforme Manual Calama Devs 11.6'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<\?=\s*date\([\'"]Y[\'"]\)\s*\?>/',
            $conteudo,
            'O ano dinâmico não pode ser impresso sem esc()'
        );
    }

    public function testRodapeContemApenasInformacoesInstitucionaisOficiaisSemConteudoInventado(): void
    {
        $html = view('partials/rodape');

        $this->assertStringContainsString('PlanIFica Hub &middot; Calama Devs', $html);
        $this->assertStringContainsString('IFRO Campus Porto Velho Calama', $html);

        $this->assertDoesNotMatchRegularExpression('/<a[\s>]/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\bhref\s*=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<script[\s>]/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<iframe[\s>]/i', $html);
    }

    public function testRodapeEstaPresenteNaPaginaInicialAutenticada(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('/');

        $resposta->assertOK();
        $resposta->assertSeeElement('footer.footer');
        $corpo = (string) $resposta->response()->getBody();

        $this->assertStringContainsString('<footer class="footer" aria-label="Rodapé institucional">', $corpo);
        $this->assertStringContainsString('PlanIFica Hub &middot; Calama Devs', $corpo);
        $this->assertStringContainsString('IFRO Campus Porto Velho Calama', $corpo);
        $this->assertStringContainsString(date('Y'), $corpo);
    }

    public function testRodapeEstaPresenteNaPaginaSobreAutenticada(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('sobre');

        $resposta->assertOK();
        $resposta->assertSeeElement('footer.footer');
        $corpo = (string) $resposta->response()->getBody();

        $this->assertStringContainsString('<footer class="footer" aria-label="Rodapé institucional">', $corpo);
        $this->assertStringContainsString('PlanIFica Hub &middot; Calama Devs', $corpo);
        $this->assertStringContainsString('IFRO Campus Porto Velho Calama', $corpo);
        $this->assertStringContainsString(date('Y'), $corpo);
    }

    public function testRodapeNaoERenderizadoParaUsuarioNaoAutenticado(): void
    {
        $resposta = $this->get('/');

        $resposta->assertRedirectTo(site_url('login'));
        $corpo = (string) $resposta->response()->getBody();
        $this->assertStringNotContainsString('aria-label="Rodapé institucional"', $corpo);
    }

    public function testAnoExibidoNoRodapeEDinamicamenteAtual(): void
    {
        $html = view('partials/rodape');
        $anoAtual = date('Y');

        $this->assertStringContainsString('&copy; ' . $anoAtual, $html);
    }

    private function criarUsuario(): User
    {
        $users = model(UserModel::class);
        $user  = new User([
            'username' => 'usuario.rodape',
            'email'    => 'rodape@example.com',
            'password' => 'SenhaForte123!',
        ]);

        $users->save($user);

        /** @var User $usuarioCriado */
        $usuarioCriado = $users->findById($users->getInsertID());

        return $usuarioCriado;
    }
}
