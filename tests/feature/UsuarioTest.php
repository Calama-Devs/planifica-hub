<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Traits\CriaContasTrait;

final class UsuarioTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use CriaContasTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private User $admin;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->admin      = $this->criarConta('usuario.teste.admin', 'admin');
    }

    public function testBloqueiaListagemSemPermissao(): void
    {
        $comum = $this->criarConta('usuario.teste.comum', 'user');

        $resposta = $this->abrir($comum, 'admin/usuarios');

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->seeInDatabase('auditoria_usuarios', [
            'operador_id' => $comum->id,
            'operacao'    => 'consulta',
            'resultado'   => 'recusada',
        ]);
    }

    public function testAdminAcessaListagemECadastroEFiltra(): void
    {
        $this->criarContaComEmail('ana.teste', 'ana.teste@example.com');

        $resposta = $this->abrir($this->admin, 'admin/usuarios', ['busca' => 'ana.teste']);

        $resposta->assertOK();
        $resposta->assertSee('ana.teste');
        $resposta->assertSee('ana.teste@example.com');
        $resposta->assertSee('Cadastrar usuário');
    }

    public function testExibeEstadoVazioParaPesquisaSemResultado(): void
    {
        $resposta = $this->abrir($this->admin, 'admin/usuarios', ['busca' => 'nao-encontrado']);

        $resposta->assertOK();
        $resposta->assertSee('Nenhum usuário corresponde à pesquisa.');
    }

    public function testPaginacaoPreservaFiltroDePesquisa(): void
    {
        for ($indice = 1; $indice <= 16; $indice++) {
            $nome = sprintf('paginacao.usuario.%02d', $indice);
            $this->criarContaComEmail($nome, $nome . '@example.com');
        }

        $resposta = $this->abrir($this->admin, 'admin/usuarios', ['busca' => 'paginacao']);

        $resposta->assertOK();
        $resposta->assertSee('Página 1 de 2');
        $this->assertStringContainsString('busca=paginacao', $resposta->getBody());
        $this->assertStringContainsString('page=2', $resposta->getBody());
    }

    public function testCriaUsuarioERejeitaGrupoManipulado(): void
    {
        $sucesso = $this->enviar($this->admin, 'admin/usuarios/criar', [
            'nome'  => 'novo.usuario',
            'email' => 'novo.usuario@example.com',
            'senha' => 'T9!x' . bin2hex(random_bytes(16)),
            'grupo' => 'user',
        ]);
        $sucesso->assertRedirectTo(url_to('usuarios.index'));
        $this->seeInDatabase('users', ['username' => 'novo.usuario']);

        $rejeitado = $this->enviar($this->admin, 'admin/usuarios/criar', [
            'nome'  => 'grupo.proibido',
            'email' => 'grupo.proibido@example.com',
            'senha' => 'T9!x' . bin2hex(random_bytes(16)),
            'grupo' => 'superadmin',
        ]);
        $rejeitado->assertRedirect();
        $this->dontSeeInDatabase('users', ['username' => 'grupo.proibido']);
    }

    public function testUsuarioSemPermissaoNaoConsegueCadastrarPorUrlDireta(): void
    {
        $comum = $this->criarConta('usuario.teste.sem.permissao', 'user');
        $resposta = $this->enviar($comum, 'admin/usuarios/criar', [
            'nome'  => 'tentativa.direta',
            'email' => 'tentativa.direta@example.com',
            'senha' => 'T9!x' . bin2hex(random_bytes(16)),
            'grupo' => 'user',
        ]);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->dontSeeInDatabase('users', ['username' => 'tentativa.direta']);
        $this->seeInDatabase('auditoria_usuarios', [
            'operador_id' => $comum->id,
            'operacao'    => 'cadastro',
            'resultado'   => 'recusada',
        ]);
    }

    public function testFormulariosTemTokenCsrfEValidacaoProximaAoCampo(): void
    {
        $formulario = $this->abrir($this->admin, 'admin/usuarios/novo');
        $formulario->assertSeeElement('input[name=' . csrf_token() . ']');
        $formulario->assertSeeElement('label[for=campo-email]');

        $resposta = $this->enviar($this->admin, 'admin/usuarios/criar', [
            'nome'  => '',
            'email' => 'email-invalido',
            'senha' => '',
            'grupo' => 'user',
        ]);
        $resposta->assertRedirect();
        $this->dontSeeInDatabase('users', ['username' => '']);
    }

    public function testTentativaBloqueadaPorFiltroNaoGeraAuditoriaDuplicada(): void
    {
        $comum = $this->criarConta('usuario.teste.duplicidade.filtro', 'user');
        $contagemInicial = $this->db->table('auditoria_usuarios')->countAllResults();

        $resposta = $this->enviar($comum, 'admin/usuarios/criar', [
            'nome'  => 'tentativa.duplicidade',
            'email' => 'tentativa.duplicidade@example.com',
            'senha' => 'T9!x' . bin2hex(random_bytes(16)),
            'grupo' => 'user',
        ]);

        $resposta->assertRedirect();
        $contagemFinal = $this->db->table('auditoria_usuarios')->countAllResults();
        $this->assertSame($contagemInicial + 1, $contagemFinal, 'O bloqueio pelo filtro deve gerar apenas 1 registro.');
    }

    public function testValidacaoDeFormularioInvalidoNaoGeraRegistroDeAuditoria(): void
    {
        $contagemInicial = $this->db->table('auditoria_usuarios')->countAllResults();

        $resposta = $this->enviar($this->admin, 'admin/usuarios/criar', [
            'nome'  => '',
            'email' => 'invalido',
            'senha' => '',
            'grupo' => 'user',
        ]);

        $resposta->assertRedirect();
        $contagemFinal = $this->db->table('auditoria_usuarios')->countAllResults();
        $this->assertSame(
            $contagemInicial,
            $contagemFinal,
            'Falha de validação comum não deve gerar evento de auditoria.',
        );
    }

    public function testGrupoAdulteradoOuAusenteGeraAuditoriaDeRecusa(): void
    {
        $contagemInicial = $this->db->table('auditoria_usuarios')->countAllResults();

        $resposta = $this->enviar($this->admin, 'admin/usuarios/criar', [
            'nome'  => 'nome.valido',
            'email' => 'email.valido@example.com',
            'senha' => 'T9!x' . bin2hex(random_bytes(16)),
            'grupo' => '',
        ]);

        $resposta->assertRedirect();
        $contagemFinal = $this->db->table('auditoria_usuarios')->countAllResults();
        $this->assertSame(
            $contagemInicial + 1,
            $contagemFinal,
            'Grupo ausente ou adulterado deve gerar exatamente 1 registro de recusa.',
        );
        $this->seeInDatabase('auditoria_usuarios', [
            'operador_id' => $this->admin->id,
            'operacao'    => 'cadastro',
            'resultado'   => 'recusada',
        ]);
    }

    private function criarContaComEmail(string $nome, string $email): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => $nome, 'active' => true]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->createEmailIdentity([
            'email'    => $email,
            'password' => 'T9!x' . bin2hex(random_bytes(16)),
        ]);
        $usuario->addGroup('user');

        return $usuario;
    }

    private function abrir(User $usuario, string $rota, ?array $parametros = null): TestResponse
    {
        $_SESSION = [];
        $this->resetServices();

        return $this->actingAs($this->recarregar($usuario))->get($rota, $parametros);
    }

    /** @param array<string, string> $campos */
    private function enviar(User $usuario, string $rota, array $campos): TestResponse
    {
        $_SESSION = [];
        $this->resetServices();
        $campos[csrf_token()] = csrf_hash();

        return $this->actingAs($this->recarregar($usuario))->withSession($_SESSION)->post($rota, $campos);
    }
}
