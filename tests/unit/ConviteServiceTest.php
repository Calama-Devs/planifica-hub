<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ConviteModel;
use App\Services\ConviteService;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Email\Email;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

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

    public function testRegistraHashDoTokenComValidadeDeTrintaMinutos(): void
    {
        $mensagem = '';
        $this->configurarEmail(true, $mensagem);

        $resultado = (new ConviteService())->emitirConvite('token@ifro.edu.br', $this->operadorId);
        $convite   = $this->conviteModel->where('email', 'token@ifro.edu.br')->first();

        preg_match('/token=([^"&]+)/', html_entity_decode($mensagem, ENT_QUOTES, 'UTF-8'), $partes);
        $token = rawurldecode($partes[1] ?? '');

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('CONVITE_ENVIO_ACEITO', $resultado['codigo']);
        $this->assertNotSame('', $token);
        $this->assertSame(hash('sha256', $token), $convite['token_hash']);
        $this->assertNotSame($token, $convite['token_hash']);
        $this->assertSame('enviado', $convite['status']);
        $this->assertSame(1800, strtotime($convite['expira_em']) - strtotime($convite['created_at']));
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

    private function configurarEmail(bool $aceito, string &$mensagem): void
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
        $email->method('send')->willReturn($aceito);

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
