<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Services\UsuarioService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\CriaContasTrait;

final class UsuarioServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CriaContasTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private UsuarioService $servico;
    private User $superadmin;
    private User $admin;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->servico   = new UsuarioService();
        $this->superadmin = $this->criarConta('operador.superadmin', 'superadmin');
        $this->admin      = $this->criarConta('operador.admin', 'admin');
    }

    public function testListaPesquisandoPorNomeOuEmail(): void
    {
        $alvo = $this->criarContaComEmail('ana.silva', 'ana@example.com', 'user');

        $porNome = $this->servico->listar((int) $this->admin->id, 'ana.silva');
        $porEmail = $this->servico->listar((int) $this->admin->id, 'ana@example.com');

        $this->assertSame((int) $alvo->id, $porNome['usuarios'][0]['id']);
        $this->assertSame('ana@example.com', $porEmail['usuarios'][0]['email']);
    }

    public function testPesquisaSemCorrespondenciaRetornaListaVazia(): void
    {
        $resultado = $this->servico->listar((int) $this->admin->id, 'inexistente@example.com');

        $this->assertSame([], $resultado['usuarios']);
    }

    public function testListaSemFiltroEAplicaPaginacao(): void
    {
        for ($indice = 1; $indice <= 16; $indice++) {
            $nome = sprintf('pagina.usuario.%02d', $indice);
            $this->criarContaComEmail($nome, $nome . '@example.com', 'user');
        }

        $resultado = $this->servico->listar((int) $this->admin->id);

        $this->assertCount(15, $resultado['usuarios']);
        $this->assertSame(2, $resultado['paginador']->getPageCount());
        $this->assertContains('operador.admin', array_column($resultado['usuarios'], 'nome'));
    }

    public function testRecusaListagemSemPermissao(): void
    {
        $operador = $this->criarConta('operador.comum', 'user');

        $resultado = $this->servico->listar((int) $operador->id);

        $this->assertSame([], $resultado['usuarios']);
    }

    public function testCriaContaPeloShieldERegistraGrupo(): void
    {
        $resultado = $this->servico->criar($this->dadosValidos('nova.conta'), (int) $this->admin->id);

        $this->assertTrue($resultado['sucesso']);
        $this->assertArrayHasKey('id', $resultado);
        $this->seeInDatabase('users', ['id' => $resultado['id'], 'username' => 'nova.conta']);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $resultado['id'],
            'type'    => 'email_password',
            'secret'  => 'nova.conta@example.com',
        ]);
        $this->seeInDatabase('auth_groups_users', ['user_id' => $resultado['id'], 'group' => 'user']);
        $this->seeInDatabase('auditoria_usuarios', [
            'usuario_id'  => $resultado['id'],
            'operador_id' => $this->admin->id,
            'operacao'    => 'cadastro',
            'resultado'   => 'sucesso',
            'grupo'       => 'user',
        ]);
    }

    public function testAdminPodeAtribuirSomenteGruposSemPermissoesSuperiores(): void
    {
        $this->assertArrayHasKey('user', $this->servico->gruposConcediveis((int) $this->admin->id));
        $this->assertArrayNotHasKey('superadmin', $this->servico->gruposConcediveis((int) $this->admin->id));

        $resultado = $this->servico->criar(
            $this->dadosValidos('tentativa.superadmin', 'superadmin'),
            (int) $this->admin->id,
        );

        $this->assertSame('GRUPO_NAO_CONCEDIVEL', $resultado['codigo']);
        $this->dontSeeInDatabase('users', ['username' => 'tentativa.superadmin']);
        $this->seeInDatabase('auditoria_usuarios', [
            'operador_id' => $this->admin->id,
            'operacao'    => 'cadastro',
            'resultado'   => 'recusada',
        ]);
    }

    public function testSuperadminPodeConcederGrupoAdministrativo(): void
    {
        $resultado = $this->servico->criar($this->dadosValidos('nova.admin', 'admin'), (int) $this->superadmin->id);

        $this->assertTrue($resultado['sucesso']);
        $this->seeInDatabase('auth_groups_users', ['user_id' => $resultado['id'], 'group' => 'admin']);
    }

    public function testRecusaEmailDuplicado(): void
    {
        $this->criarContaComEmail('conta.existente', 'existente@example.com', 'user');

        $resultado = $this->servico->criar(
            $this->dadosValidos('nova.conta', 'user', 'existente@example.com'),
            (int) $this->admin->id,
        );

        $this->assertSame('CONTA_DUPLICADA', $resultado['codigo']);
    }

    public function testRecusaOperadorSemPermissaoDeCadastro(): void
    {
        $operador = $this->criarConta('operador.comum', 'user');

        $resultado = $this->servico->criar($this->dadosValidos('tentativa.conta'), (int) $operador->id);

        $this->assertSame('CONTAS_SEM_PERMISSAO', $resultado['codigo']);
        $this->dontSeeInDatabase('users', ['username' => 'tentativa.conta']);
        $this->seeInDatabase('auditoria_usuarios', [
            'operador_id' => $operador->id,
            'operacao'    => 'cadastro',
            'resultado'   => 'recusada',
        ]);
    }

    public function testFalhaNaPersistenciaNaoDeixaContaParcial(): void
    {
        $modelo = $this->createMock(UserModel::class);
        $modelo->method('findById')->willReturn($this->admin);
        $modelo->expects($this->once())->method('save')->willReturn(false);
        $servico = new UsuarioService($modelo);

        $resultado = $servico->criar($this->dadosValidos('falha.persistencia'), (int) $this->admin->id);

        $this->assertSame('CONTAS_ERRO_PERSISTENCIA', $resultado['codigo']);
        $this->dontSeeInDatabase('users', ['username' => 'falha.persistencia']);
        $this->seeInDatabase('auditoria_usuarios', [
            'operador_id' => $this->admin->id,
            'operacao'    => 'cadastro',
            'resultado'   => 'falha',
        ]);
        $this->assertSame(0, $this->db->transDepth);
    }

    /** @return array{nome: string, email: string, senha: string, grupo: string} */
    private function dadosValidos(string $nome, string $grupo = 'user', ?string $email = null): array
    {
        return [
            'nome'  => $nome,
            'email' => $email ?? $nome . '@example.com',
            'senha' => 'T9!x' . bin2hex(random_bytes(16)),
            'grupo' => $grupo,
        ];
    }

    private function criarContaComEmail(string $nome, string $email, string $grupo): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => $nome, 'active' => true]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->createEmailIdentity([
            'email'    => $email,
            'password' => 'T9!x' . bin2hex(random_bytes(16)),
        ]);
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
