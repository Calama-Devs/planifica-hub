<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConviteModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ConviteControllerFinalizacaoTest extends CIUnitTestCase
{
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

    public function testExibeFormularioSomenteParaConviteValido(): void
    {
        $token = $this->criarConvite('destinatario@ifro.edu.br');

        $resposta = $this->get('finalizar-cadastro?token=' . rawurlencode($token));

        $resposta->assertOK();
        $resposta->assertSee('destinatario@ifro.edu.br');
        $resposta->assertSeeElement('input[name=nome_completo]');
        $resposta->assertSeeElement('input[name=password]');
        $resposta->assertSeeElement('input[name=password_confirm]');
        $this->assertStringContainsString('readonly', $resposta->getBody());
        $this->assertStringNotContainsString('name="email"', $resposta->getBody());
    }

    public function testRecusaTokenInvalidoSemExibirFormulario(): void
    {
        $resposta = $this->get('finalizar-cadastro?token=invalido');

        $resposta->assertOK();
        $resposta->assertSee('Este convite não é válido.');
        $this->assertStringNotContainsString('name="password"', $resposta->getBody());
    }

    public function testPostExigeCsrf(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('finalizar-cadastro', [
            'token'            => str_repeat('a', 43),
            'password'         => 'SenhaForte@2026',
            'password_confirm' => 'SenhaForte@2026',
        ]);
    }

    private function criarConvite(string $email): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        model(ConviteModel::class)->insert([
            'email'          => $email,
            'email_pendente' => $email,
            'token_hash'      => hash('sha256', $token),
            'status'         => 'enviado',
            'expira_em'      => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ]);

        return $token;
    }
}
