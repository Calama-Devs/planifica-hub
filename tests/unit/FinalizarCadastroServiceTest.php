<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FinalizarCadastroService;
use CodeIgniter\Test\CIUnitTestCase;

final class FinalizarCadastroServiceTest extends CIUnitTestCase
{
    private FinalizarCadastroService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinalizarCadastroService();
    }

    public function testValidarConviteComTokenNuloOuVazioRetornaInvalido(): void
    {
        $resultadoNulo = $this->service->validarConvite(null);
        $this->assertFalse($resultadoNulo['sucesso']);
        $this->assertSame('invalido', $resultadoNulo['estado']);
        $this->assertSame('CONVITE_INVALIDO', $resultadoNulo['codigo']);

        $resultadoVazio = $this->service->validarConvite('   ');
        $this->assertFalse($resultadoVazio['sucesso']);
        $this->assertSame('invalido', $resultadoVazio['estado']);
    }

    public function testValidarConviteComFormatoInvalidoRetornaInvalido(): void
    {
        $resultado = $this->service->validarConvite('abc'); // menos de 8 caracteres
        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('invalido', $resultado['estado']);
        $this->assertSame('CONVITE_INVALIDO', $resultado['codigo']);

        $resultadoComEspaco = $this->service->validarConvite('token com espaco invalido');
        $this->assertFalse($resultadoComEspaco['sucesso']);
        $this->assertSame('invalido', $resultadoComEspaco['estado']);
    }

    public function testValidarConviteSemPersistenciaRetornaInvalido(): void
    {
        $token = 'token-valido-de-teste-12345';
        $resultado = $this->service->validarConvite($token);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('invalido', $resultado['estado']);
        $this->assertSame('CONVITE_NAO_ENCONTRADO', $resultado['codigo']);
    }

    public function testConcluirCadastroSemBackendRetornaServicoIndisponivel(): void
    {
        $dados = [
            'token'            => 'token-12345678',
            'nome_completo'    => 'Estudante Teste',
            'password'         => 'SenhaForte@123',
            'password_confirm' => 'SenhaForte@123',
        ];

        $resultado = $this->service->concluirCadastro($dados);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('SERVICO_INDISPONIVEL', $resultado['codigo']);
        $this->assertSame('regra_negocio', $resultado['tipo']);
    }

    public function testFormatarGruposComMultiplosGruposECodigosConhecidos(): void
    {
        $grupos = ['admin', 'superadmin', 'beta'];
        $formatados = $this->service->formatarGrupos($grupos);

        $this->assertCount(3, $formatados);
        $this->assertSame('admin', $formatados[0]['codigo']);
        $this->assertSame('Administrador (admin)', $formatados[0]['rotulo']);
        $this->assertSame('superadmin', $formatados[1]['codigo']);
        $this->assertSame('Superadministrador (superadmin)', $formatados[1]['rotulo']);
        $this->assertSame('beta', $formatados[2]['codigo']);
        $this->assertSame('Testador Beta (beta)', $formatados[2]['rotulo']);
    }

    public function testFormatarGruposVazioRetornaPadraoUser(): void
    {
        $formatados = $this->service->formatarGrupos([]);

        $this->assertCount(1, $formatados);
        $this->assertSame('user', $formatados[0]['codigo']);
        $this->assertSame('Usuário (user)', $formatados[0]['rotulo']);
    }

    public function testObterEstadosSuportadosContemplaCanceladoETodosOsCenarios(): void
    {
        $estados = $this->service->obterEstadosSuportados();

        $this->assertContains(FinalizarCadastroService::ESTADO_VALIDO, $estados);
        $this->assertContains(FinalizarCadastroService::ESTADO_EXPIRADO, $estados);
        $this->assertContains(FinalizarCadastroService::ESTADO_INVALIDO, $estados);
        $this->assertContains(FinalizarCadastroService::ESTADO_CANCELADO, $estados);
        $this->assertContains(FinalizarCadastroService::ESTADO_INVALIDO_OU_CANCELADO, $estados);
        $this->assertContains(FinalizarCadastroService::ESTADO_UTILIZADO, $estados);
        $this->assertContains(FinalizarCadastroService::ESTADO_ERRO_TEMPORARIO, $estados);
        $this->assertSame('cancelado', FinalizarCadastroService::ESTADO_CANCELADO);
        $this->assertSame('invalido_ou_cancelado', FinalizarCadastroService::ESTADO_INVALIDO_OU_CANCELADO);
    }

    public function testResolvedorCustomizadoPlugavelRetornaDadosConfigurados(): void
    {
        $this->service->definirResolvedorConvite(static function (string $token): array {
            return [
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'CONVITE_VALIDO',
                'estado'   => FinalizarCadastroService::ESTADO_VALIDO,
                'mensagem' => 'Convite customizado.',
                'dados'    => [
                    'token'  => $token,
                    'email'  => 'integracao.teste@example.com',
                    'grupos' => [['codigo' => 'user', 'rotulo' => 'Usuário (user)']],
                ],
            ];
        });

        $resultado = $this->service->validarConvite('token-customizado-1234');
        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(FinalizarCadastroService::ESTADO_VALIDO, $resultado['estado']);
        $this->assertSame('integracao.teste@example.com', $resultado['dados']['email']);
    }

    public function testTokenDemonstracaoLocalRetornaConviteValidoComDados(): void
    {
        $resultado = $this->service->validarConvite('dev-token-valido');
        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(FinalizarCadastroService::ESTADO_VALIDO, $resultado['estado']);
        $this->assertSame('convidado.teste@example.com', $resultado['dados']['email']);
        $this->assertCount(1, $resultado['dados']['grupos']);
    }
}
