<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ConviteModel;
use App\Models\EventoAuditoriaModel;
use App\Services\AuditoriaService;
use App\Services\ConviteService;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class ConviteServiceFinalizacaoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private ConviteModel $convites;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->convites = model(ConviteModel::class);
    }

    protected function tearDown(): void
    {
        auth()->logout();
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testValidaConvitePendenteComTokenCorreto(): void
    {
        $token = $this->criarConvite('convidado@ifro.edu.br');

        $resultado = (new ConviteService())->validarConvite($token);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('convidado@ifro.edu.br', $resultado['dados']['email']);
    }

    public function testRecusaTokenExpirado(): void
    {
        $token = $this->criarConvite('expirado@ifro.edu.br', 'enviado', '-1 minute');

        $resultado = (new ConviteService())->validarConvite($token);

        $this->assertSame('CONVITE_EXPIRADO', $resultado['codigo']);
    }

    public function testConcluiCadastroConsomeConviteECriaContaNoShield(): void
    {
        $token = $this->criarConvite('novo@ifro.edu.br');

        $resultado = (new ConviteService())->concluirCadastro($token, 'SenhaForte@2026', 'Maria da Silva');
        $convite  = $this->convites->where('email', 'novo@ifro.edu.br')->first();
        $usuario  = model(UserModel::class)->findByCredentials(['email' => 'novo@ifro.edu.br']);
        $evento   = model(EventoAuditoriaModel::class)->where('acao', 'concluir_cadastro')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('CADASTRO_CONCLUIDO', $resultado['codigo']);
        $this->assertSame('utilizado', $convite['status']);
        $this->assertNull($convite['email_pendente']);
        $this->assertNotNull($convite['utilizado_em']);
        $this->assertNotNull($usuario);
        $this->assertSame('Maria da Silva', $usuario->nome_completo);
        $this->assertNotNull($evento);
        $this->assertNull($evento['usuario_id']);
        $this->assertSame((string) $usuario->id, $evento['entidade_id']);
        $this->assertStringNotContainsString($token, $evento['descricao']);
        $this->assertStringNotContainsString('SenhaForte@2026', $evento['descricao']);
    }

    public function testFalhaNaAuditoriaDaConclusaoReverteCadastroEConvite(): void
    {
        $token = $this->criarConvite('rollback@ifro.edu.br');
        $auditoria = $this->createMock(AuditoriaService::class);
        $auditoria->expects($this->once())
            ->method('registrar')
            ->willThrowException(new RuntimeException('Falha simulada na auditoria.'));

        $resultado = (new ConviteService($auditoria))->concluirCadastro(
            $token,
            'SenhaForte@2026',
            'Maria da Silva'
        );
        $convite = $this->convites->where('email', 'rollback@ifro.edu.br')->first();

        $this->assertSame('CADASTRO_FALHOU', $resultado['codigo']);
        $this->assertSame('enviado', $convite['status']);
        $this->assertNull(model(UserModel::class)->findByCredentials(['email' => 'rollback@ifro.edu.br']));
        $this->assertSame(0, model(EventoAuditoriaModel::class)->countAllResults());
    }

    public function testNaoPermiteUsarConviteDuasVezes(): void
    {
        $token   = $this->criarConvite('unico@ifro.edu.br');
        $service = new ConviteService();

        $service->concluirCadastro($token, 'SenhaForte@2026', 'Maria da Silva');
        $resultado = $service->concluirCadastro($token, 'OutraSenha@2026', 'Maria da Silva');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('CONVITE_UTILIZADO', $resultado['codigo']);
        $this->assertCount(1, model(UserModel::class)->findAll());
    }

    public function testRecusaSenhaQueNaoAtendeAoShieldSemConsumirConvite(): void
    {
        $token = $this->criarConvite('senha@ifro.edu.br');

        $resultado = (new ConviteService())->concluirCadastro($token, 'curta', 'Maria da Silva');
        $convite   = $this->convites->where('email', 'senha@ifro.edu.br')->first();

        $this->assertSame('SENHA_INVALIDA', $resultado['codigo']);
        $this->assertSame('enviado', $convite['status']);
    }

    public function testPermiteNomesIguaisParaEmailsDistintos(): void
    {
        $primeiroToken = $this->criarConvite('primeiro@ifro.edu.br');
        $segundoToken  = $this->criarConvite('segundo@ifro.edu.br');
        $service       = new ConviteService();

        $service->concluirCadastro($primeiroToken, 'SenhaForte@2026', 'João da Silva');
        $service->concluirCadastro($segundoToken, 'SenhaForte@2026', 'João da Silva');

        $usuarios = auth()->getProvider()->where('nome_completo', 'João da Silva')->findAll();

        $this->assertCount(2, $usuarios);
    }

    private function criarConvite(string $email, string $status = 'enviado', string $expiracao = '+30 minutes'): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->convites->insert([
            'email'          => $email,
            'email_pendente' => $email,
            'token_hash'      => hash('sha256', $token),
            'status'         => $status,
            'expira_em'      => date('Y-m-d H:i:s', strtotime($expiracao)),
        ]);

        return $token;
    }
}
