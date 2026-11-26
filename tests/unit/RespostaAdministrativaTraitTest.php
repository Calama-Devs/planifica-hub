<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fakes\ControladorAdministrativoFake;

final class RespostaAdministrativaTraitTest extends CIUnitTestCase
{
    private ControladorAdministrativoFake $controlador;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->controlador = new ControladorAdministrativoFake(new Response(config('App')));
    }

    public function testSucessoSaiComStatus200ECorpoDoContrato(): void
    {
        $resposta = $this->controlador->responder(
            ['sucesso' => true, 'tipo' => 'sucesso', 'codigo' => 'OPERACAO_CONCLUIDA', 'mensagem' => 'Feito.'],
            500,
        );

        $json = $this->corpo($resposta);
        self::assertSame(200, $resposta->getStatusCode());
        self::assertTrue($json['sucesso']);
        self::assertSame(csrf_token(), $json['csrf']['nome']);
        self::assertSame(csrf_hash(), $json['csrf']['hash']);
        self::assertSame(csrf_hash(), $json['csrf_hash']);
        self::assertStringContainsString('no-store', $resposta->getHeaderLine('Cache-Control'));
    }

    /** @return array<string, array{int}> */
    public static function statusQueNaoSaoDeErro(): array
    {
        return [
            'status 200' => [200],
            'status 201' => [201],
            'status 204' => [204],
            'status 302' => [302],
            'status 0' => [0],
        ];
    }

    #[DataProvider('statusQueNaoSaoDeErro')]
    public function testRecusaNuncaSaiComStatusDeSucessoOuRedirecionamento(int $statusDaFalha): void
    {
        $resposta = $this->controlador->responder([
            'sucesso' => false,
            'tipo' => 'regra_negocio',
            'codigo' => 'CODIGO_NAO_PREVISTO',
            'mensagem' => 'Recusada.',
        ], $statusDaFalha);

        self::assertSame(500, $resposta->getStatusCode());
        self::assertFalse($this->corpo($resposta)['sucesso']);
        $this->assertLogContains('error', 'Código CODIGO_NAO_PREVISTO');
    }

    #[DataProvider('statusQueNaoSaoDeErro')]
    public function testSucessoQueNaoEhBooleanoVerdadeiroEhTratadoComoRecusa(int $statusDaFalha): void
    {
        foreach ([1, 'true', null, []] as $sucesso) {
            $resposta = $this->controlador->responder(
                ['sucesso' => $sucesso, 'codigo' => 'RESULTADO_AMBIGUO', 'mensagem' => 'Sem confirmação.'],
                $statusDaFalha,
            );

            self::assertGreaterThanOrEqual(400, $resposta->getStatusCode());
        }
    }

    public function testResultadoSemCamposEhTratadoComoRecusa(): void
    {
        $resposta = $this->controlador->responder([], 200);

        self::assertSame(500, $resposta->getStatusCode());
    }

    public function testRecusaUsaOStatusDeErroDefinidoPelaOperacao(): void
    {
        $resposta = $this->controlador->responder([
            'sucesso' => false,
            'tipo' => 'regra_negocio',
            'codigo' => 'CONTA_JA_INATIVA',
            'mensagem' => 'Já inativa.',
        ], 409);

        self::assertSame(409, $resposta->getStatusCode());
    }

    public function testRecusaDaConfirmacaoUsaOStatusDoContratoEAvisaAEspera(): void
    {
        // O status do contrato prevalece sobre o que a operação sugeriu, inclusive um status de sucesso.
        $resposta = $this->controlador->responder([
            'sucesso' => false,
            'tipo' => 'permissao',
            'codigo' => 'CONFIRMACAO_LIMITE_EXCEDIDO',
            'mensagem' => 'Muitas tentativas incorretas.',
            'espera_segundos' => 120,
        ], 200);

        self::assertSame(429, $resposta->getStatusCode());
        self::assertSame('120', $resposta->getHeaderLine('Retry-After'));
        self::assertSame(120, $this->corpo($resposta)['espera_segundos']);
    }

    /** @return array<string, mixed> */
    private function corpo(ResponseInterface $resposta): array
    {
        return json_decode((string) $resposta->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
