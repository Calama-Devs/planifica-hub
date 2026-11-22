<?php

declare(strict_types=1);

namespace Tests\Unit;

use Closure;
use App\Models\ConviteModel;
use App\Models\EventoAuditoriaModel;
use App\Services\AuditoriaService;
use App\Services\ConviteService;
use Config\Auth;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Email\Email;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class ConviteServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private ConviteModel $conviteModel;
    private int $operadorId;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->conviteModel = model(ConviteModel::class);
        $this->operadorId   = $this->criarUsuario('admin');
    }

    protected function tearDown(): void
    {
        auth()->logout();
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testRecusaDominioInvalido(): void
    {
        $resultado = (new ConviteService())->emitirConvite('pessoa@ifro.edu.br.exemplo', $this->operadorId);

        $this->assertSame('CONVITE_EMAIL_INVALIDO', $resultado['codigo']);
    }

    public function testConviteModelRejeitaDadosEstruturalmenteInvalidos(): void
    {
        $resultado = $this->conviteModel->insert([
            'email'          => 'invalido@ifro.edu.br',
            'email_pendente' => 'invalido@ifro.edu.br',
            'token_hash'     => str_repeat('z', 64),
            'status'         => 'estado_inexistente',
            'expira_em'      => 'data inválida',
        ]);

        $this->assertFalse($resultado);
        $this->assertNotEmpty($this->conviteModel->errors());
    }

    public function testRecusaContaJaCadastrada(): void
    {
        $usuarioId = $this->criarUsuario('user');
        $usuario   = model(UserModel::class)->findById($usuarioId);
        $usuario->createEmailIdentity(['email' => 'existente@ifro.edu.br', 'password' => 'SenhaFicticia123!']);

        $resultado = (new ConviteService())->emitirConvite('existente@ifro.edu.br', $this->operadorId);

        $this->assertSame('CONVITE_CONTA_EXISTENTE', $resultado['codigo']);
    }

    public function testRecusaOperadorSemPermissao(): void
    {
        $resultado = (new ConviteService())->emitirConvite(
            'sem-permissao@ifro.edu.br',
            $this->criarUsuario('user')
        );

        $this->assertSame('CONTAS_SEM_PERMISSAO', $resultado['codigo']);
    }

    public function testRegistraGruposPadraoEHashDoTokenComValidadeDeTrintaMinutos(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('token@ifro.edu.br', $this->operadorId);
        $convite   = $this->conviteModel->where('email', 'token@ifro.edu.br')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('CONVITE_ENVIO_ACEITO', $resultado['codigo']);
        $this->assertSame(64, strlen($convite['token_hash']));
        $this->assertSame(['user'], json_decode($convite['grupos'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('enviado', $convite['status']);
        $this->assertSame(1800, strtotime($convite['expira_em']) - strtotime($convite['created_at']));
    }

    public function testPermiteSelecionarGruposValidosParaAdministradorDeAcessos(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);
        $superadminId = $this->criarUsuario('superadmin');

        $resultado = (new ConviteService())->emitirConvite(
            'grupos-validos@ifro.edu.br',
            $superadminId,
            ['admin', 'beta']
        );
        $convite = $this->conviteModel->where('email', 'grupos-validos@ifro.edu.br')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(['admin', 'beta'], json_decode($convite['grupos'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRecusaGrupoInexistente(): void
    {
        $resultado = (new ConviteService())->emitirConvite(
            'grupo-invalido@ifro.edu.br',
            $this->criarUsuario('superadmin'),
            ['inexistente']
        );

        $this->assertSame('CONVITE_GRUPO_INVALIDO', $resultado['codigo']);
        $this->assertSame(0, $this->conviteModel->countAllResults());
    }

    public function testImpedeAdministradorDeContasDeElevarPrivilegiosPorGrupo(): void
    {
        $resultado = (new ConviteService())->emitirConvite(
            'elevacao@ifro.edu.br',
            $this->operadorId,
            ['admin', 'superadmin']
        );

        $this->assertSame('CONVITE_GRUPOS_SEM_PERMISSAO', $resultado['codigo']);
        $this->assertSame(0, $this->conviteModel->countAllResults());
    }

    public function testMantemProviderPadraoDoShieldParaAutenticacao(): void
    {
        $this->assertSame(UserModel::class, config(Auth::class)->userProvider);
        $this->assertInstanceOf(UserModel::class, auth()->getProvider());
    }

    public function testAuditaEmissaoComOperadorSemDadosSensiveis(): void
    {
        $operador = auth()->getProvider()->findById($this->operadorId);
        auth()->login($operador);
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('auditado@ifro.edu.br', $this->operadorId);
        $convite   = $this->conviteModel->where('email', 'auditado@ifro.edu.br')->first();
        $evento    = model(EventoAuditoriaModel::class)->where('acao', 'emitir_convite')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertNotNull($evento);
        $this->assertSame($this->operadorId, (int) $evento['usuario_id']);
        $this->assertSame((string) $convite['id'], $evento['entidade_id']);
        $this->assertSame('concluida', $evento['resultado']);
        $this->assertStringNotContainsString('auditado@ifro.edu.br', $evento['descricao']);
        $this->assertStringNotContainsString('auditado@ifro.edu.br', $mensagem);

        auth()->logout();
    }

    public function testFalhaNaAuditoriaDaEmissaoReverteEstadoDoConvite(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);
        $auditoria = $this->createMock(AuditoriaService::class);
        $auditoria->expects($this->once())
            ->method('registrar')
            ->willThrowException(new RuntimeException('Falha simulada na auditoria.'));

        $resultado = (new ConviteService($auditoria))->emitirConvite('rollback@ifro.edu.br', $this->operadorId);
        $convite   = $this->conviteModel->where('email', 'rollback@ifro.edu.br')->first();

        $this->assertSame('CONVITE_STATUS_FALHOU', $resultado['codigo']);
        $this->assertSame('preparado', $convite['status']);
        $this->assertSame(0, model(EventoAuditoriaModel::class)->countAllResults());
    }

    public function testRecusaConvitePendenteValidoParaMesmoEmail(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);
        $service = new ConviteService();

        $service->emitirConvite('duplicado@ifro.edu.br', $this->operadorId);
        $resultado = $service->emitirConvite('DUPLICADO@ifro.edu.br', $this->operadorId);

        $this->assertSame('CONVITE_PENDENTE', $resultado['codigo']);
        $this->assertCount(1, $this->conviteModel->findAll());
    }

    public function testPermiteNovoConviteAposExpiracao(): void
    {
        $agora = date('Y-m-d H:i:s');
        $this->conviteModel->insert([
            'email'          => 'expirado@ifro.edu.br',
            'email_pendente' => 'expirado@ifro.edu.br',
            'token_hash'      => hash('sha256', 'expirado'),
            'status'         => 'enviado',
            'expira_em'      => date('Y-m-d H:i:s', strtotime('-1 minute')),
        ]);
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('expirado@ifro.edu.br', $this->operadorId);
        $convites  = $this->conviteModel->orderBy('id')->findAll();

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('expirado', $convites[0]['status']);
        $this->assertNull($convites[0]['email_pendente']);
        $this->assertSame('enviado', $convites[1]['status']);
        $this->assertGreaterThanOrEqual(strtotime($agora), strtotime($convites[1]['expira_em']) - 1800);
    }

    public function testRegistraFalhaQuandoTransporteRecusaEnvio(): void
    {
        $mensagem = '';
        $this->configurarEmail(false, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('falha@ifro.edu.br', $this->operadorId);
        $convite   = $this->conviteModel->where('email', 'falha@ifro.edu.br')->first();

        $this->assertSame('CONVITE_ENVIO_FALHOU', $resultado['codigo']);
        $this->assertSame('falha_envio', $convite['status']);
        $this->assertNull($convite['email_pendente']);
        $this->assertNotNull($convite['falhou_em']);
    }

    public function testRegistraMomentoDoResultadoDoEnvio(): void
    {
        $mensagem         = '';
        $momentoResultado = '';
        $this->configurarEmail(true, $mensagem, static function () use (&$momentoResultado): void {
            usleep(1_200_000);
            $momentoResultado = date('Y-m-d H:i:s');
        });

        $resultado = (new ConviteService())->emitirConvite('momento@ifro.edu.br', $this->operadorId);
        $convite   = $this->conviteModel->where('email', 'momento@ifro.edu.br')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertNotSame('', $momentoResultado);
        $this->assertGreaterThanOrEqual(
            strtotime($momentoResultado),
            strtotime($convite['enviado_em'])
        );
    }

    public function testUnicidadeNoBancoImpedeConvitesPendentesSimultaneos(): void
    {
        $dados = [
            'email'          => 'concorrente@ifro.edu.br',
            'email_pendente' => 'concorrente@ifro.edu.br',
            'token_hash'      => hash('sha256', 'primeiro'),
            'status'         => 'preparado',
            'expira_em'      => date('Y-m-d H:i:s', time() + 1800),
        ];

        $this->assertNotFalse($this->conviteModel->insert($dados));
        $dados['token_hash'] = hash('sha256', 'segundo');
        $this->expectException(DatabaseException::class);

        $this->conviteModel->insert($dados);
    }

    private function configurarEmail(bool $aceito, string &$mensagem, ?Closure $aoEnviar = null): void
    {
        $email = $this->createMock(Email::class);
        $email->method('clear')->willReturnSelf();
        $email->method('setMailType')->willReturnSelf();
        $email->method('setFrom')->willReturnSelf();
        $email->method('setTo')->willReturnSelf();
        $email->method('setSubject')->willReturnSelf();
        $email->method('setMessage')->willReturnCallback(
            static function (string $conteudo) use (&$mensagem, $email): Email {
                $mensagem = $conteudo;

                return $email;
            }
        );
        $email->method('setAltMessage')->willReturnSelf();
        $email->method('send')->willReturnCallback(static function () use ($aceito, $aoEnviar): bool {
            $aoEnviar?->__invoke();

            return $aceito;
        });

        BaseService::injectMock('email', $email);
    }

    private function criarUsuario(string $grupo): int
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.' . bin2hex(random_bytes(8))]));
        $usuarioId = (int) $usuarios->getInsertID();
        $usuario   = $usuarios->findById($usuarioId);
        $usuario->addGroup($grupo);

        return $usuarioId;
    }
}
