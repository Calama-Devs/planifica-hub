<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConviteModel;
use App\Models\EventoAuditoriaModel;
use App\Services\AuditoriaService;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Email\Email;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Email as EmailConfig;
use RuntimeException;

final class ConviteControllerTest extends CIUnitTestCase
{
    use AuthenticationTesting;
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

        $configuracaoEmail            = config(EmailConfig::class);
        $configuracaoEmail->fromEmail = 'nao-responda@ifro.edu.br';
        $configuracaoEmail->fromName  = 'PlanIFica Hub';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->db->table('eventos_auditoria')->emptyTable();
        parent::tearDown();
    }

    public function testRotaRecusaUsuarioSemPermissaoContasCriar(): void
    {
        $usuario = $this->criarUsuario('user');

        $resposta = $this->actingAs($usuario)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'sem-permissao@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testRotaRecusaAdministradorDeAcessosSemPermissaoContasCriar(): void
    {
        $usuario = $this->criarUsuario('user');
        $usuario->addPermission('acessos.administrar');

        $resposta = $this->actingAs($usuario)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'sem-contas-criar@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testTelaExclusivaDeConvitesNaoEstaDisponivel(): void
    {
        $operador = $this->criarUsuario('admin');

        $this->expectException(PageNotFoundException::class);
        $this->actingAs($operador)->get('admin/convites');
    }

    public function testRotaRecusaEmissaoSemCsrf(): void
    {
        $operador = $this->criarUsuario('admin');

        $this->expectException(SecurityException::class);

        $this->actingAs($operador)->post('admin/convites', [
            'email' => 'sem-csrf@ifro.edu.br',
        ]);
    }

    public function testRotaRecusaGruposForaDoFormatoDeLista(): void
    {
        $operador = $this->criarUsuario('admin');
        $csrfAnterior = csrf_hash();

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'      => 'grupos@ifro.edu.br',
                'grupos'     => 'admin',
                csrf_token() => $csrfAnterior,
            ]);

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['codigo' => 'CONVITE_DADOS_INVALIDOS']);
        $json = json_decode($resposta->getJSON(), true);
        $this->assertSame(csrf_token(), $json['csrf']['nome']);
        $this->assertSame(csrf_hash(), $json['csrf']['hash']);
        $this->assertNotSame($csrfAnterior, $json['csrf']['hash']);
    }

    public function testEndpointRecusaEmailJaCadastradoSemCriarConvite(): void
    {
        $operador = $this->criarUsuario('admin');
        $existente = $this->criarUsuario('user');
        $existente->createEmailIdentity([
            'email'    => 'existente@ifro.edu.br',
            'password' => 'SenhaFicticia123!',
        ]);

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'existente@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment([
            'sucesso' => false,
            'codigo'  => 'CONVITE_CONTA_EXISTENTE',
        ]);
        $this->assertSame(0, model(ConviteModel::class)->countAllResults());
    }

    public function testEndpointRecusaConvitePendenteParaMesmoDestinatario(): void
    {
        $operador = $this->criarUsuario('admin');
        model(ConviteModel::class)->insert([
            'emissor_id'    => (int) $operador->id,
            'email'          => 'pendente@ifro.edu.br',
            'email_pendente' => 'pendente@ifro.edu.br',
            'token_hash'      => hash('sha256', 'token-pendente'),
            'status'         => 'pendente',
            'expira_em'      => date('Y-m-d H:i:s', time() + 1800),
            'grupos'         => json_encode(['user'], JSON_THROW_ON_ERROR),
        ]);

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'PENDENTE@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment([
            'sucesso' => false,
            'codigo'  => 'CONVITE_PENDENTE',
        ]);
        $this->assertSame(1, model(ConviteModel::class)->countAllResults());
    }

    public function testEndpointNormalizaEspacosDoEmailAntesDaValidacao(): void
    {
        $operador = $this->criarUsuario('admin');
        $this->configurarEmail(true);

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => '  normalizado@ifro.edu.br  ',
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertStatus(201);
        $convite = model(ConviteModel::class)->where('email', 'normalizado@ifro.edu.br')->first();
        $this->assertNotNull($convite);
        $this->assertSame((int) $operador->id, (int) $convite['emissor_id']);
    }

    public function testEndpointRegistraFalhaQuandoTransporteRecusaEmail(): void
    {
        $operador = $this->criarUsuario('admin');
        $this->configurarEmail(false);

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'transporte-falhou@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);
        $convite = model(ConviteModel::class)
            ->where('email', 'transporte-falhou@ifro.edu.br')
            ->first();

        $resposta->assertStatus(500);
        $resposta->assertJSONFragment([
            'sucesso' => false,
            'codigo'  => 'CONVITE_ENVIO_FALHOU',
        ]);
        $this->assertSame('pendente', $convite['status']);
        $this->assertSame(1, model(EventoAuditoriaModel::class)->countAllResults());
    }

    public function testEndpointImpedeEnvioQuandoAuditoriaEstaIndisponivel(): void
    {
        $operador = $this->criarUsuario('admin');
        $this->configurarEmail(true);
        $auditoria = $this->createMock(AuditoriaService::class);
        $auditoria->expects($this->once())
            ->method('registrar')
            ->willThrowException(new RuntimeException('Falha simulada na auditoria.'));
        BaseService::injectMock('auditoriaService', $auditoria);

        $resposta = $this->actingAs($operador)
            ->withSession($_SESSION)
            ->post('admin/convites', [
                'email'     => 'auditoria-falhou@ifro.edu.br',
                csrf_token() => csrf_hash(),
            ]);
        $convite = model(ConviteModel::class)
            ->where('email', 'auditoria-falhou@ifro.edu.br')
            ->first();

        $resposta->assertStatus(500);
        $resposta->assertJSONFragment([
            'sucesso' => false,
            'codigo'  => 'CONVITE_PREPARO_FALHOU',
        ]);
        $this->assertNull($convite);
        $this->assertSame(0, model(EventoAuditoriaModel::class)->countAllResults());
    }

    public function testRotasDeFinalizacaoNaoEstaoDisponiveis(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('finalizar-cadastro');
    }

    private function criarUsuario(string $grupo): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.convite.' . bin2hex(random_bytes(8))]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }

    private function configurarEmail(bool $aceito): void
    {
        $email = $this->createMock(Email::class);
        $email->method('clear')->willReturnSelf();
        $email->method('setMailType')->willReturnSelf();
        $email->method('setFrom')->willReturnSelf();
        $email->method('setTo')->willReturnSelf();
        $email->method('setSubject')->willReturnSelf();
        $email->method('setMessage')->willReturnSelf();
        $email->method('setAltMessage')->willReturnSelf();
        $email->method('send')->willReturn($aceito);

        BaseService::injectMock('email', $email);
    }
}
