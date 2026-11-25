<?php

declare(strict_types=1);

namespace Tests\Unit;

use Closure;
use App\Models\ConviteModel;
use App\Models\EventoAuditoriaModel;
use App\Models\UsuarioAutenticacaoModel;
use App\Services\AuditoriaService;
use App\Services\ConviteService;
use Config\Auth;
use Config\Email as EmailConfig;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Config\Factories;
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
        auth()->login(auth()->getProvider()->findById($this->operadorId));

        $configuracaoEmail            = config(EmailConfig::class);
        $configuracaoEmail->fromEmail = 'nao-responda@ifro.edu.br';
        $configuracaoEmail->fromName  = 'PlanIFica Hub';
    }

    protected function tearDown(): void
    {
        auth()->logout();
        Factories::reset('models');
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testRecusaDominioInvalido(): void
    {
        $resultado = (new ConviteService())->emitirConvite('pessoa@ifro.edu.br.exemplo');

        $this->assertSame('CONVITE_EMAIL_INVALIDO', $resultado['codigo']);
    }

    public function testConviteModelRejeitaDadosEstruturalmenteInvalidos(): void
    {
        $resultado = $this->conviteModel->insert([
            'emissor_id'    => $this->operadorId,
            'email'          => 'invalido@ifro.edu.br',
            'email_pendente' => 'invalido@ifro.edu.br',
            'token_hash'     => str_repeat('z', 64),
            'status'         => 'preparado',
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

        $resultado = (new ConviteService())->emitirConvite('existente@ifro.edu.br');

        $this->assertSame('CONVITE_CONTA_EXISTENTE', $resultado['codigo']);
    }

    public function testRecusaOperadorSemPermissao(): void
    {
        $usuarioId = $this->criarUsuario('user');
        auth()->logout();
        auth()->login(auth()->getProvider()->findById($usuarioId));

        $resultado = (new ConviteService())->emitirConvite('sem-permissao@ifro.edu.br');

        $this->assertSame('CONTAS_SEM_PERMISSAO', $resultado['codigo']);
    }

    public function testRecusaEmissaoQuandoRemetenteNaoEstaConfigurado(): void
    {
        config(EmailConfig::class)->fromEmail = '';

        $resultado = (new ConviteService())->emitirConvite('sem-remetente@ifro.edu.br');

        $this->assertSame('CONVITE_REMETENTE_INVALIDO', $resultado['codigo']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame(0, $this->conviteModel->countAllResults());
    }

    public function testUsaRemetenteDaConfiguracaoEmailCarregada(): void
    {
        $mensagem          = '';
        $remetenteRecebido = [];
        $this->configurarEmail(
            true,
            $mensagem,
            null,
            static function (string $email, string $nome) use (&$remetenteRecebido): void {
                $remetenteRecebido = [$email, $nome];
            }
        );

        $resultado = (new ConviteService())->emitirConvite('remetente@ifro.edu.br');

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(['nao-responda@ifro.edu.br', 'PlanIFica Hub'], $remetenteRecebido);
    }

    public function testRegistraGruposPadraoEHashDoTokenComValidadeDeTrintaMinutos(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('token@ifro.edu.br');
        $convite   = $this->conviteModel->where('email', 'token@ifro.edu.br')->first();
        preg_match('/href="([^"]*token=([^"&]+))"/', html_entity_decode($mensagem, ENT_QUOTES, 'UTF-8'), $partes);
        $link  = $partes[1] ?? '';
        $token = rawurldecode($partes[2] ?? '');

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('CONVITE_ENVIO_ACEITO', $resultado['codigo']);
        $this->assertNotSame('', $link);
        $this->assertStringContainsString($link, $mensagem);
        $this->assertSame(hash('sha256', $token), $convite['token_hash']);
        $this->assertStringNotContainsString($token, json_encode($convite, JSON_THROW_ON_ERROR));
        $this->assertSame(64, strlen($convite['token_hash']));
        $this->assertSame($this->operadorId, (int) $convite['emissor_id']);
        $this->assertSame(['user'], json_decode($convite['grupos'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('pendente', $convite['status']);
        $this->assertSame(1800, strtotime($convite['expira_em']) - strtotime($convite['created_at']));
    }

    public function testPermiteSelecionarGruposValidosParaAdministradorDeAcessos(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);
        $superadminId = $this->criarUsuario('superadmin');
        auth()->logout();
        auth()->login(auth()->getProvider()->findById($superadminId));

        $resultado = (new ConviteService())->emitirConvite(
            'grupos-validos@ifro.edu.br',
            ['admin', 'beta']
        );
        $convite = $this->conviteModel->where('email', 'grupos-validos@ifro.edu.br')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame(['admin', 'beta'], json_decode($convite['grupos'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRecusaGrupoInexistente(): void
    {
        $superadminId = $this->criarUsuario('superadmin');
        auth()->logout();
        auth()->login(auth()->getProvider()->findById($superadminId));

        $resultado = (new ConviteService())->emitirConvite(
            'grupo-invalido@ifro.edu.br',
            ['inexistente']
        );

        $this->assertSame('CONVITE_GRUPO_INVALIDO', $resultado['codigo']);
        $this->assertSame(0, $this->conviteModel->countAllResults());
    }

    public function testImpedeAdministradorDeContasDeElevarPrivilegiosPorGrupo(): void
    {
        $resultado = (new ConviteService())->emitirConvite(
            'elevacao@ifro.edu.br',
            ['admin', 'superadmin']
        );

        $this->assertSame('CONVITE_GRUPOS_SEM_PERMISSAO', $resultado['codigo']);
        $this->assertSame(0, $this->conviteModel->countAllResults());
    }

    public function testUsaProviderDeAutenticacaoCompativelComShield(): void
    {
        $this->assertSame(UsuarioAutenticacaoModel::class, config(Auth::class)->userProvider);
        $this->assertInstanceOf(UsuarioAutenticacaoModel::class, auth()->getProvider());
        $this->assertInstanceOf(UserModel::class, auth()->getProvider());
    }

    public function testAuditaEmissaoComOperadorSemDadosSensiveis(): void
    {
        $mensagem = '';
        $auditoriaRegistradaNoEnvio = false;
        $conviteEnviadoNoEnvio      = false;
        $this->configurarEmail(
            true,
            $mensagem,
            function () use (&$auditoriaRegistradaNoEnvio, &$conviteEnviadoNoEnvio): void {
                $auditoriaRegistradaNoEnvio = model(EventoAuditoriaModel::class)
                    ->where('acao', 'emitir_convite')
                    ->countAllResults() > 0;
                $conviteEnviadoNoEnvio = model(ConviteModel::class)
                    ->where('email', 'auditado@ifro.edu.br')
                    ->first()['status'] === 'pendente';
            }
        );

        $resultado = (new ConviteService())->emitirConvite('auditado@ifro.edu.br');
        $convite   = $this->conviteModel->where('email', 'auditado@ifro.edu.br')->first();
        $evento    = model(EventoAuditoriaModel::class)->where('acao', 'emitir_convite')->first();

        $this->assertTrue($resultado['sucesso']);
        $this->assertNotNull($evento);
        $this->assertSame($this->operadorId, (int) $evento['usuario_id']);
        $this->assertSame((string) $convite['id'], $evento['entidade_id']);
        $this->assertSame('concluida', $evento['resultado']);
        $this->assertTrue($auditoriaRegistradaNoEnvio);
        $this->assertTrue($conviteEnviadoNoEnvio);
        $this->assertStringNotContainsString('auditado@ifro.edu.br', $evento['descricao']);
        $this->assertStringNotContainsString('auditado@ifro.edu.br', $mensagem);

        auth()->logout();
    }

    public function testFalhaNaAuditoriaImpedeEnvioDoConvite(): void
    {
        $mensagem = '';
        $envios = 0;
        $this->configurarEmail(true, $mensagem, static function () use (&$envios): void {
            $envios++;
        });
        $auditoria = $this->createMock(AuditoriaService::class);
        $auditoria->expects($this->once())
            ->method('registrar')
            ->willThrowException(new RuntimeException('Falha simulada na auditoria.'));

        $resultado = (new ConviteService($auditoria))->emitirConvite('rollback@ifro.edu.br');
        $convite   = $this->conviteModel->where('email', 'rollback@ifro.edu.br')->first();

        $this->assertSame('CONVITE_PREPARO_FALHOU', $resultado['codigo']);
        $this->assertNull($convite);
        $this->assertSame(0, model(EventoAuditoriaModel::class)->countAllResults());
        $this->assertSame(0, $envios);
    }

    public function testPreservaConviteQuandoTransporteRetornaResultadoIncerto(): void
    {
        $mensagem = '';
        $this->configurarEmail(false, $mensagem);
        $auditoria = $this->createMock(AuditoriaService::class);
        $eventos = [];
        $auditoria->expects($this->once())
            ->method('registrar')
            ->willReturnCallback(static function (array $evento) use (&$eventos): void {
                $eventos[] = $evento;
            });

        $resultado = (new ConviteService($auditoria))->emitirConvite('email-falhou@ifro.edu.br');
        $convite   = $this->conviteModel->where('email', 'email-falhou@ifro.edu.br')->first();

        $this->assertSame('CONVITE_ENVIO_FALHOU', $resultado['codigo']);
        $this->assertSame('pendente', $convite['status']);
        $this->assertSame(0, model(EventoAuditoriaModel::class)->countAllResults());
        $this->assertSame('concluida', $eventos[0]['resultado']);
        $this->assertArrayNotHasKey('email', $eventos[0]);
        $this->assertArrayNotHasKey('token', $eventos[0]);
    }

    public function testRecusaConvitePendenteValidoParaMesmoEmail(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);
        $service = new ConviteService();

        $service->emitirConvite('duplicado@ifro.edu.br');
        $resultado = $service->emitirConvite('DUPLICADO@ifro.edu.br');

        $this->assertSame('CONVITE_PENDENTE', $resultado['codigo']);
        $this->assertCount(1, $this->conviteModel->findAll());
    }

    public function testPermiteNovoConviteAposExpiracao(): void
    {
        $agora = date('Y-m-d H:i:s');
        $email = 'expirado@ifro.edu.br';
        $tokenHash = hash('sha256', 'expirado');
        $expiraEm = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $this->conviteModel->insert([
            'emissor_id'    => $this->operadorId,
            'email'          => $email,
            'email_pendente' => $email,
            'token_hash'      => $tokenHash,
            'status'         => 'pendente',
            'expira_em'      => $expiraEm,
        ]);
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);

        $resultado = (new ConviteService())->emitirConvite($email);
        $convites  = $this->conviteModel->orderBy('id')->findAll();

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('expirado', $convites[0]['status']);
        $this->assertNull($convites[0]['email_pendente']);
        $this->assertSame($email, $convites[0]['email']);
        $this->assertSame($tokenHash, $convites[0]['token_hash']);
        $this->assertSame($expiraEm, $convites[0]['expira_em']);
        $this->assertSame('pendente', $convites[1]['status']);
        $this->assertGreaterThanOrEqual(strtotime($agora), strtotime($convites[1]['expira_em']) - 1800);
    }

    public function testPreservaConviteEAuditoriaQuandoTransporteRetornaFalse(): void
    {
        $mensagem = '';
        $this->configurarEmail(false, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('falha@ifro.edu.br');
        $convite   = $this->conviteModel->where('email', 'falha@ifro.edu.br')->first();

        $this->assertSame('CONVITE_ENVIO_FALHOU', $resultado['codigo']);
        $this->assertSame('pendente', $convite['status']);
        $this->assertSame(1, model(EventoAuditoriaModel::class)->countAllResults());
    }

    public function testPreservaConviteQuandoTransporteLancaExcecao(): void
    {
        $arquivoLog = WRITEPATH . 'logs/log-' . date('Y-m-d') . '.log';
        $tamanhoLogInicial = is_file($arquivoLog) ? filesize($arquivoLog) : 0;

        $mensagem = '';
        $this->configurarEmail(true, $mensagem, static function (): void {
            throw new RuntimeException('Falha simulada no transporte de e-mail.');
        });

        $resultado = (new ConviteService())->emitirConvite('transporte-excecao@ifro.edu.br');
        $convite = $this->conviteModel->where('email', 'transporte-excecao@ifro.edu.br')->first();

        $this->assertSame('CONVITE_ENVIO_FALHOU', $resultado['codigo']);
        $this->assertSame('pendente', $convite['status']);
        $this->assertSame(1, model(EventoAuditoriaModel::class)->countAllResults());
        $conteudoLog = file_get_contents($arquivoLog);
        $this->assertIsString($conteudoLog);
        $log = substr($conteudoLog, $tamanhoLogInicial);
        $this->assertStringContainsString('Operador ' . $this->operadorId, $log);
        $this->assertStringContainsString(RuntimeException::class, $log);
        $this->assertStringNotContainsString('transporte-excecao@ifro.edu.br', $log);
        $this->assertStringNotContainsString($convite['token_hash'], $log);
    }

    public function testNaoPersisteConviteNemAuditoriaQuandoPersistenciaFalha(): void
    {
        $mensagem = '';
        $envios = 0;
        $this->configurarEmail(true, $mensagem, static function () use (&$envios): void {
            $envios++;
        });
        $convites = new class ($this->db) extends ConviteModel {
            public function insert($row = null, bool $returnID = true)
            {
                return false;
            }
        };
        Factories::injectMock('models', ConviteModel::class, $convites);

        $resultado = (new ConviteService())->emitirConvite('persistencia-falhou@ifro.edu.br');

        $this->assertSame('CONVITE_PREPARO_FALHOU', $resultado['codigo']);
        $this->assertSame(0, $this->db->table('convites')->countAllResults());
        $this->assertSame(0, model(EventoAuditoriaModel::class)->countAllResults());
        $this->assertSame(0, $envios);
    }

    public function testUnicidadeNoBancoImpedeConvitesPendentesSimultaneos(): void
    {
        $dados = [
            'emissor_id'    => $this->operadorId,
            'email'          => 'concorrente@ifro.edu.br',
            'email_pendente' => 'concorrente@ifro.edu.br',
            'token_hash'      => hash('sha256', 'primeiro'),
            'status'         => 'pendente',
            'expira_em'      => date('Y-m-d H:i:s', time() + 1800),
        ];

        $this->assertNotFalse($this->conviteModel->insert($dados));
        $dados['token_hash'] = hash('sha256', 'segundo');
        $this->expectException(DatabaseException::class);

        $this->conviteModel->insert($dados);
    }

    private function configurarEmail(
        bool $aceito,
        string &$mensagem,
        ?Closure $aoEnviar = null,
        ?Closure $aoConfigurarRemetente = null
    ): void {
        $email = $this->createMock(Email::class);
        $email->method('clear')->willReturnSelf();
        $email->method('setMailType')->willReturnSelf();
        $email->method('setFrom')->willReturnCallback(
            static function (string $remetente, string $nome) use ($email, $aoConfigurarRemetente): Email {
                $aoConfigurarRemetente?->__invoke($remetente, $nome);

                return $email;
            }
        );
        $email->method('setTo')->willReturnSelf();
        $email->method('setSubject')->willReturnSelf();
        $email->method('setMessage')->willReturnCallback(
            static function (string $conteudo) use (&$mensagem, $email): Email {
                $mensagem = $conteudo;

                return $email;
            }
        );
        $email->method('setAltMessage')->willReturnCallback(
            static function (string $conteudo) use (&$mensagem, $email): Email {
                $mensagem .= "\n" . $conteudo;

                return $email;
            }
        );
        $email->method('send')->willReturnCallback(static function () use ($aceito, $aoEnviar): bool {
            $aoEnviar?->__invoke();

            return $aceito;
        });

        BaseService::injectMock('email', $email);
    }

    private function criarUsuario(string $grupo): int
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.' . bin2hex(random_bytes(8)), 'active' => true]));
        $usuarioId = (int) $usuarios->getInsertID();
        $usuario   = $usuarios->findById($usuarioId);
        $usuario->addGroup($grupo);

        return $usuarioId;
    }
}
