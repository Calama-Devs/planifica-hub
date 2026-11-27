<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ConviteService;
use App\Models\ConviteModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class FinalizarCadastroTest extends CIUnitTestCase
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

    protected function tearDown(): void
    {
        $this->resetServices();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAcessoSemTokenExibeEstadoInvalido(): void
    {
        $resposta = $this->get('finalizar-cadastro');

        $resposta->assertOK();
        $resposta->assertSee('Finalize seu cadastro');
        $resposta->assertSee('Convite Inválido');
        $resposta->assertSee('Este link de convite não está disponível para utilização');
        $resposta->assertSee('Voltar ao login');
        $this->assertStringNotContainsString('<form', $resposta->getBody());
    }

    public function testAcessoComConviteValidoExibeFormularioCompletoEEmailSomenteLeitura(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'estado'   => 'valido',
            'mensagem' => 'Convite válido.',
            'dados'    => [
                'token'  => 'token-valido-123456',
                'email'  => 'convidado.teste@example.com',
                'grupos' => [
                    ['codigo' => 'user', 'rotulo' => 'Usuário (user)'],
                ],
            ],
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->get('finalizar-cadastro?token=token-valido-123456');

        $resposta->assertOK();
        $resposta->assertSee('Finalize seu cadastro');
        $this->assertStringContainsString('data-envio-unico="true"', $resposta->getBody());
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');

        // Campo Nome Completo
        $resposta->assertSeeElement('label[for=nome-completo]');
        $resposta->assertSeeElement('input[id=nome-completo]');

        // Campo E-mail em somente leitura e acessível por teclado (sem tabindex="-1")
        $resposta->assertSeeElement('label[for=email-institucional]');
        $resposta->assertSeeElement('input[id=email-institucional]');
        $this->assertStringContainsString('readonly', $resposta->getBody());
        $this->assertStringNotContainsString('tabindex="-1"', $resposta->getBody());
        $resposta->assertSee('convidado.teste@example.com');

        // Grupo padrão user
        $resposta->assertSee('Usuário (user)');

        // Campos de senha
        $resposta->assertSeeElement('label[for=senha]');
        $resposta->assertSeeElement('input[id=senha]');
        $resposta->assertSeeElement('label[for=confirmar-senha]');
        $resposta->assertSeeElement('input[id=confirmar-senha]');

        // Botões de alternância de senha
        $this->assertStringContainsString('data-toggle-senha="senha"', $resposta->getBody());
        $this->assertStringContainsString('data-toggle-senha="confirmar-senha"', $resposta->getBody());

        // Botões de submissão e retorno
        $resposta->assertSeeElement('button[id=btn-finalizar-cadastro]');
        $resposta->assertSee('Finalizar cadastro');
        $resposta->assertSee('Voltar ao login');
    }

    public function testAcessoComConviteValidoExibeMultiplosGruposSomenteLeitura(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'estado'   => 'valido',
            'mensagem' => 'Convite válido.',
            'dados'    => [
                'token'  => 'token-valido-multiplos',
                'email'  => 'coordenador.teste@example.com',
                'grupos' => [
                    ['codigo' => 'user', 'rotulo' => 'Usuário (user)'],
                    ['codigo' => 'admin', 'rotulo' => 'Administrador (admin)'],
                    ['codigo' => 'beta', 'rotulo' => 'Testador Beta (beta)'],
                ],
            ],
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->get('finalizar-cadastro?token=token-valido-multiplos');

        $resposta->assertOK();
        $resposta->assertSee('Usuário (user)');
        $resposta->assertSee('Administrador (admin)');
        $resposta->assertSee('Testador Beta (beta)');
        // Garante que não existem campos de edição de grupo
        $this->assertStringNotContainsString('<select', $resposta->getBody());
        $this->assertStringNotContainsString('name="grupos"', $resposta->getBody());
    }

    public function testAcessoComConviteExpiradoExibeCardAvisoESemFormulario(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => 'CONVITE_EXPIRADO',
            'estado'   => 'expirado',
            'mensagem' => 'O prazo deste convite terminou.',
            'dados'    => null,
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->get('finalizar-cadastro?token=token-expirado');

        $resposta->assertOK();
        $resposta->assertSee('Convite Expirado');
        $resposta->assertSee('solicite um novo convite ao responsável');
        $resposta->assertSee('Voltar ao login');
        $this->assertStringNotContainsString('<form', $resposta->getBody());
        $this->assertStringNotContainsString('name="password"', $resposta->getBody());
    }

    public function testAcessoComConviteJaUtilizadoExibeCardComBotaoIrParaLogin(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => 'CONVITE_UTILIZADO',
            'estado'   => 'utilizado',
            'mensagem' => 'Este convite já foi utilizado.',
            'dados'    => null,
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->get('finalizar-cadastro?token=token-utilizado');

        $resposta->assertOK();
        $resposta->assertSee('Convite Já Utilizado');
        $resposta->assertSee('Ir para o login');
        $this->assertStringNotContainsString('<form', $resposta->getBody());
        $this->assertStringNotContainsString('name="password"', $resposta->getBody());
    }

    public function testAcessoComErroTemporarioExibeMensagemSemDetalhesTecnicos(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => false,
            'tipo'     => 'inesperado',
            'codigo'   => 'ERRO_TEMPORARIO',
            'estado'   => 'erro_temporario',
            'mensagem' => 'Instabilidade momentânea.',
            'dados'    => null,
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->get('finalizar-cadastro?token=token-erro');

        $resposta->assertOK();
        $resposta->assertSee('Instabilidade Temporária');
        $resposta->assertDontSee('Exception');
        $resposta->assertDontSee('Stack trace');
        $this->assertStringNotContainsString('name="password"', $resposta->getBody());
    }

    public function testSubmissaoComCamposVaziosFalhaValidacao(): void
    {
        $resposta = $this->post('finalizar-cadastro', [
            'token'            => '',
            'nome_completo'    => '',
            'password'         => '',
            'password_confirm' => '',
            csrf_token()       => csrf_hash(),
        ]);

        $resposta->assertSessionHas('errors');
        $erros = session('errors');
        $this->assertArrayHasKey('nome_completo', $erros);
        $this->assertArrayHasKey('password', $erros);
        $this->assertArrayHasKey('password_confirm', $erros);
    }

    public function testSubmissaoComNomeInvalidoFalhaValidacao(): void
    {
        $resposta = $this->post('finalizar-cadastro', [
            'token'            => 'token-valido-123456',
            'nome_completo'    => 'Nome <script>alert(1)</script>',
            'password'         => 'SenhaForte@123',
            'password_confirm' => 'SenhaForte@123',
            csrf_token()       => csrf_hash(),
        ]);

        $resposta->assertSessionHas('errors');
        $erros = session('errors');
        $this->assertArrayHasKey('nome_completo', $erros);
        $this->assertStringContainsString('caracteres inválidos', $erros['nome_completo']);
    }

    public function testSubmissaoComSenhasDivergentesFalhaValidacao(): void
    {
        $resposta = $this->post('finalizar-cadastro', [
            'token'            => 'token-valido-123456',
            'nome_completo'    => 'Nome Válido Com Acentuação É',
            'password'         => 'SenhaForte@123',
            'password_confirm' => 'SenhaDiferente@999',
            csrf_token()       => csrf_hash(),
        ]);

        $resposta->assertSessionHas('errors');
        $erros = session('errors');
        $this->assertArrayHasKey('password_confirm', $erros);
        $this->assertSame('As senhas informadas não correspondem.', $erros['password_confirm']);
    }

    public function testFalhaNaConclusaoRetornaMensagemDoServicoSemSucesso(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'estado'   => ConviteService::ESTADO_VALIDO,
            'mensagem' => 'Convite válido.',
            'dados'    => null,
        ]);
        $mockService->method('concluirCadastro')->willReturn([
            'sucesso'  => false,
            'tipo'     => 'inesperado',
            'codigo'   => 'CADASTRO_FALHOU',
            'mensagem' => 'Não foi possível concluir o cadastro. Tente novamente.',
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->post('finalizar-cadastro', [
            'token'            => 'token-valido-123456',
            'nome_completo'    => 'Maria da Silva Araújo',
            'password'         => 'SenhaSegura@2026',
            'password_confirm' => 'SenhaSegura@2026',
            csrf_token()       => csrf_hash(),
        ]);

        $this->assertNull(session('message'));
        $resposta->assertSessionHas('error');
        $this->assertStringContainsString('Não foi possível concluir', session('error'));
    }

    public function testSubmissaoComTokenIndisponivelRetornaErroDoServico(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => 'CONVITE_INDISPONIVEL',
            'estado'   => ConviteService::ESTADO_INVALIDO,
            'mensagem' => 'Este convite não está disponível para utilização.',
            'dados'    => null,
        ]);
        $mockService->expects($this->once())->method('concluirCadastro')->willReturn([
            'sucesso' => false,
            'tipo' => 'regra_negocio',
            'codigo' => 'CONVITE_INDISPONIVEL',
            'mensagem' => 'Este convite não está disponível para utilização.',
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->post('finalizar-cadastro', [
            'token'            => 'token-indisponivel-123',
            'nome_completo'    => 'Maria da Silva Araújo',
            'password'         => 'SenhaSegura@2026',
            'password_confirm' => 'SenhaSegura@2026',
            csrf_token()       => csrf_hash(),
        ]);

        $resposta->assertRedirectTo(site_url('finalizar-cadastro?token=token-indisponivel-123'));
        $resposta->assertSessionHas('error');
        $this->assertSame('Este convite não está disponível para utilização.', session('error'));
    }

    public function testSubmissaoComSucessoRedirecionaParaLoginComMensagem(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'estado'   => ConviteService::ESTADO_VALIDO,
            'mensagem' => 'Convite válido.',
            'dados'    => null,
        ]);
        $mockService->method('concluirCadastro')->willReturn([
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CADASTRO_CONCLUIDO',
            'mensagem' => 'Cadastro concluído com sucesso! Acesse sua conta.',
        ]);
        Services::injectMock('conviteService', $mockService);

        $resposta = $this->post('finalizar-cadastro', [
            'token'            => 'token-valido-123456',
            'nome_completo'    => 'Maria da Silva Araújo',
            'password'         => 'SenhaSegura@2026',
            'password_confirm' => 'SenhaSegura@2026',
            csrf_token()       => csrf_hash(),
        ]);

        $resposta->assertRedirectTo(site_url('login'));
        $this->assertSame('Cadastro concluído com sucesso! Acesse sua conta.', session('success'));
    }

    public function testAcessoComConvitePersistidoExibeFormularioValido(): void
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'emissor-teste', 'active' => true]));
        $this->assertNotFalse(model(ConviteModel::class)->insert([
            'emissor_id' => $usuarios->getInsertID(),
            'email' => 'convidado.teste@ifro.edu.br',
            'email_pendente' => 'convidado.teste@ifro.edu.br',
            'token_hash' => hash('sha256', 'token-adaptado-valido-123'),
            'status' => 'pendente',
            'expira_em' => date('Y-m-d H:i:s', time() + 1800),
            'grupos' => json_encode(['user'], JSON_THROW_ON_ERROR),
        ]));

        $resposta = $this->get('finalizar-cadastro?token=token-adaptado-valido-123');

        $resposta->assertOK();
        $resposta->assertSee('Finalize seu cadastro');
        $resposta->assertSee('convidado.teste@ifro.edu.br');
        $resposta->assertSee('Usuário (user)');
        $resposta->assertSeeElement('input[id=senha]');
        $resposta->assertSeeElement('input[id=confirmar-senha]');
        $this->assertStringNotContainsString('tabindex="-1"', $resposta->getBody());
    }

    public function testAcessoComTokenNaoEncontradoExibeEstadoInvalido(): void
    {
        $resposta = $this->get('finalizar-cadastro?token=token-sem-integracao-123');

        $resposta->assertOK();
        $resposta->assertSee('Convite Inválido');
    }

    public function testAliasConviteFinalizarCarregaMesmaInterface(): void
    {
        $resposta = $this->get('convite/finalizar');

        $resposta->assertOK();
        $resposta->assertSee('Finalize seu cadastro');
    }

    public function testFalhaDeValidacaoNaoRepopulaSenhas(): void
    {
        $mockService = $this->createMock(ConviteService::class);
        $mockService->method('validarConvite')->willReturn([
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'estado'   => 'valido',
            'mensagem' => 'Convite válido.',
            'dados'    => [
                'token'  => 'token-valido-123456',
                'email'  => 'convidado.teste@example.com',
                'grupos' => [['codigo' => 'user', 'rotulo' => 'Usuário (user)']],
            ],
        ]);
        Services::injectMock('conviteService', $mockService);

        // Submissão com erro
        $this->post('finalizar-cadastro', [
            'token'            => 'token-valido-123456',
            'nome_completo'    => '',
            'password'         => 'SegredoDigitado123',
            'password_confirm' => 'SegredoDigitado123',
            csrf_token()       => csrf_hash(),
        ]);

        // Carrega a tela com o erro na sessão
        $resposta = $this->get('finalizar-cadastro?token=token-valido-123456');

        $resposta->assertOK();
        $this->assertStringNotContainsString('SegredoDigitado123', $resposta->getBody());
    }
}
