<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\IdentidadeRecuperacaoModel;
use App\Models\UsuarioAutenticacaoModel;
use App\Services\AuditoriaService;
use App\Services\PerfilService;
use App\Services\RedefinirSenhaService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;
use Tests\Support\Traits\CriaContasTrait;

/**
 * Falhas da escrita e da auditoria nas alterações do Meu Perfil e na redefinição de senha:
 * nada é confirmado sem o evento.
 */
final class AuditoriaPerfilTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CriaContasTrait;

    protected $namespace = null;

    private User $usuario;

    private string $senha;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->senha   = 'Inicial@9' . bin2hex(random_bytes(24));
        $this->usuario = $this->criarConta('conta.perfil', 'user');
        $this->usuario->createEmailIdentity([
            'email'    => 'perfil.auditoria@example.com',
            'password' => $this->senha,
        ]);
        auth()->login($this->usuario);
    }

    protected function tearDown(): void
    {
        auth()->logout();
        $this->db->table('eventos_auditoria')->emptyTable();

        parent::tearDown();
    }

    public function testNaoConfirmaNomeQuandoAuditoriaFalha(): void
    {
        $servico = new PerfilService(null, $this->auditoriaComFalha());

        $resultado = $servico->atualizarNome($this->usuario, 'Nome Novo');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->seeInDatabase('users', ['id' => $this->usuario->id, 'nome_completo' => null]);
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testNaoRegistraEventoQuandoGravacaoDoNomeFalha(): void
    {
        $usuarios = $this->getMockBuilder(UsuarioAutenticacaoModel::class)->onlyMethods(['update'])->getMock();
        $usuarios->method('update')->willReturn(false);
        $servico = new PerfilService($usuarios);

        $resultado = $servico->atualizarNome($this->usuario, 'Nome Novo');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->seeInDatabase('users', ['id' => $this->usuario->id, 'nome_completo' => null]);
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testNaoConfirmaSenhaQuandoAuditoriaFalha(): void
    {
        $hashAnterior = $this->buscarHashDaSenha();
        $servico      = new RedefinirSenhaService(null, $this->auditoriaComFalha());

        $resultado = $servico->alterarPeloPerfil($this->usuario, $this->senha, 'NovaSenha@123', 'NovaSenha@123');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testNaoRegistraEventoQuandoGravacaoDaSenhaFalha(): void
    {
        $hashAnterior = $this->buscarHashDaSenha();
        $identidades  = $this->getMockBuilder(IdentidadeRecuperacaoModel::class)
            ->onlyMethods(['atualizarSenhaDaContaAtiva'])
            ->getMock();
        $identidades->method('atualizarSenhaDaContaAtiva')->willReturn(false);
        $servico = new RedefinirSenhaService($identidades);

        $resultado = $servico->alterarPeloPerfil($this->usuario, $this->senha, 'NovaSenha@123', 'NovaSenha@123');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    public function testNaoConfirmaRedefinicaoPorLinkQuandoAuditoriaFalha(): void
    {
        auth()->logout();
        $hashAnterior = $this->buscarHashDaSenha();
        $servico      = new RedefinirSenhaService(null, $this->auditoriaComFalha());

        $resultado = $servico->redefinir($this->usuario, 'NovaSenha@123', 'NovaSenha@123');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha());
        $this->seeNumRecords(0, 'eventos_auditoria', []);
    }

    private function auditoriaComFalha(): AuditoriaService
    {
        $auditoria = $this->createMock(AuditoriaService::class);
        $auditoria->method('registrar')->willThrowException(new RuntimeException('Falha simulada da auditoria.'));

        return $auditoria;
    }

    private function buscarHashDaSenha(): string
    {
        $identidade = $this->db->table('auth_identities')
            ->select('secret2')
            ->where('user_id', $this->usuario->id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        $this->assertNotNull($identidade);

        return $identidade['secret2'];
    }
}
