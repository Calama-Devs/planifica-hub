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

final class ContaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private string $senha = 'Senha-de-teste-123!';
    private int $contador = 0;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
    }

    public function testAdministradorAtualizaNomeCompletoSemAlterarIdentidadeOuGrupo(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $alvo->addGroup('user');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'José da Silva',
            'senha_admin' => $this->senha,
        ]);

        self::assertSame(200, $resposta->response()->getStatusCode(), $resposta->getJSON());
        $this->seeInDatabase('users', [
            'id' => $alvo->id,
            'username' => 'conta.alvo',
            'nome_completo' => 'José da Silva',
        ]);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->seeNumRecords(1, 'auth_groups_users', ['user_id' => $alvo->id, 'group' => 'user']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'operador_id' => $operador->id,
            'campos_alterados' => 'nome_completo',
            'resultado' => 'sucesso',
        ]);

        $consulta = $this->actingAs($operador)->get('admin/contas/' . $alvo->id);
        $consulta->assertOK();
        $this->assertStringContainsString('conta2@example.com', $consulta->getJSON());
        $this->assertStringContainsString('José da Silva', $consulta->getJSON());

        $propriaConta = $this->enviarAtualizacao($operador, (int) $operador->id, [
            'nome' => 'Nome pelo painel',
            'senha_admin' => $this->senha,
        ]);
        $propriaConta->assertStatus(422);
        $this->seeInDatabase('users', ['id' => $operador->id, 'nome_completo' => null]);
    }

    public function testRecusaOperadorSemPermissao(): void
    {
        $operador = $this->criarUsuario('operador');
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->actingAs($operador)->post('admin/contas/' . $alvo->id, [
            csrf_token() => csrf_hash(),
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
        ]);

        $resposta->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
    }

    public function testServicoRecusaOperadorSemPermissaoComoDefesaEmProfundidade(): void
    {
        $operador = $this->criarUsuario('operadorSemPermissao');
        $alvo = $this->criarUsuario('conta.alvo');
        $servico = service('edicaoContaService');

        $resultado = $servico->atualizar(
            (int) $alvo->id,
            'Nome Novo',
            $this->senha,
            $operador,
        );

        self::assertFalse($resultado['sucesso']);
        self::assertSame('permissao', $resultado['tipo']);
        self::assertSame('CONTA_SEM_PERMISSAO', $resultado['codigo']);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testRecusaSenhaAdministrativaIncorreta(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Novo',
            'senha_admin' => 'incorreta',
        ]);

        $resposta->assertStatus(422);
        self::assertTrue(db_connect()->transStatus());
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);

        $inexistente = $this->enviarAtualizacao($operador, 99999999, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
        ]);

        $inexistente->assertStatus(404);
    }

    public function testRejeitaPropriedadesExtrasSemAlterarGrupoOuSenha(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
            'groups' => ['admin'],
            'active' => '0',
            'extra_field' => 'valor',
        ]);

        $resposta->assertStatus(400);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null, 'active' => 0]);
        $this->dontSeeInDatabase('auth_groups_users', ['user_id' => $alvo->id, 'group' => 'admin']);
    }

    public function testAlteraEmailComJustificativaERegistraValoresNaAuditoria(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Atualizado',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);

        $resposta->assertOK();
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo->id,
            'type' => 'email_password',
            'secret' => 'novo.email@ifro.edu.br',
        ]);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_anterior' => 'conta2@example.com',
            'email_novo' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
    }

    public function testRecusaEmailSemJustificativaSuficiente(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
        ]);

        $resposta->assertStatus(422);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testRecusaEmailDuplicadoEEmailInvalido(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $outro = $this->criarUsuario('conta.outro');
        model(\CodeIgniter\Shield\Models\UserIdentityModel::class)->update(
            $outro->getEmailIdentity()->id,
            ['secret' => 'conta.existente@ifro.edu.br'],
        );

        foreach (['conta.existente@ifro.edu.br', 'email-invalido'] as $email) {
            $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
                'nome' => 'Nome Novo',
                'senha_admin' => $this->senha,
                'email' => $email,
                'justificativa' => 'Solicitação administrativa registrada.',
            ]);
            $resposta->assertStatus(422);
        }

        $this->seeInDatabase('auth_identities', ['user_id' => $outro->id, 'secret' => 'conta.existente@ifro.edu.br']);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
    }

    public function testListaFiltraPorNomeEGrupoERejeitaGrupoInvalido(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $alvo->addGroup('user');
        model(\App\Models\UsuarioAutenticacaoModel::class)->update($alvo->id, ['nome_completo' => 'Conta Alvo']);

        $resposta = $this->actingAs($operador)->get(
            'admin/contas/tabela?draw=2&start=0&length=10&search%5Bvalue%5D=Conta%20Alvo&grupo=user',
        );
        $resposta->assertOK();
        $dados = json_decode($resposta->getJSON(), true);
        self::assertSame(1, $dados['recordsFiltered']);
        self::assertSame((int) $alvo->id, $dados['data'][0]['id']);

        $invalido = $this->actingAs($operador)->get('admin/contas/tabela?draw=3&start=0&length=10&grupo=nao-existe');
        $invalido->assertStatus(400);
    }

    public function testTelaDeContasUsaLayoutComEstilosEAssetsDoSistema(): void
    {
        $operador = $this->criarUsuario('operador', true);

        $resposta = $this->actingAs($operador)->get('admin/contas');

        $resposta->assertOK();
        self::assertStringContainsString('assets/css/modern-vertical/style.css', $resposta->getBody());
        self::assertStringContainsString('assets/css/custom.css', $resposta->getBody());
        self::assertStringContainsString('assets/vendor/js/vendor.bundle.base.js', $resposta->getBody());
    }

    public function testFalhaDeAuditoriaDesfazAtualizacao(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $tabelaAuditoria = db_connect()->prefixTable('auditoria_edicao_contas');
        db_connect()->query(
            'CREATE TRIGGER falha_auditoria_edicao BEFORE INSERT ON ' . $tabelaAuditoria . ' '
            . 'BEGIN SELECT RAISE(ABORT, "falha de auditoria"); END',
        );

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
        ]);

        $resposta->assertStatus(422);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testAceitaDominiosInstitucionaisPermitidos(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo1 = $this->criarUsuario('conta.alvo1');
        $alvo2 = $this->criarUsuario('conta.alvo2');

        $resposta1 = $this->enviarAtualizacao($operador, (int) $alvo1->id, [
            'nome' => 'Servidor IFRO',
            'senha_admin' => $this->senha,
            'email' => 'guilherme.guimaraes@ifro.edu.br',
            'justificativa' => 'Atualização para e-mail funcional.',
        ]);
        $resposta1->assertOK();
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo1->id,
            'secret' => 'guilherme.guimaraes@ifro.edu.br',
        ]);

        $resposta2 = $this->enviarAtualizacao($operador, (int) $alvo2->id, [
            'nome' => 'Estudante IFRO',
            'senha_admin' => $this->senha,
            'email' => 'guilherme.guimaraes@estudante.ifro.edu.br',
            'justificativa' => 'Atualização para e-mail discente.',
        ]);
        $resposta2->assertOK();
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo2->id,
            'secret' => 'guilherme.guimaraes@estudante.ifro.edu.br',
        ]);
    }

    public function testRecusaDominiosNaoPermitidosComCodigoEspecifico(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $emailOriginal = (string) $alvo->email;

        $dominiosInvalidos = [
            'x@gmail.com',
            'x@falsoifro.edu.br',
            'x@ifro.edu.br.exemplo.com',
            'x@outro.estudante.ifro.edu.br',
        ];

        foreach ($dominiosInvalidos as $emailInvalido) {
            $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
                'nome' => 'Nome Atualizado',
                'senha_admin' => $this->senha,
                'email' => $emailInvalido,
                'justificativa' => 'Tentativa de troca de e-mail.',
            ]);

            $resposta->assertStatus(422);
            $dados = json_decode($resposta->getJSON(), true);
            self::assertSame('EMAIL_DOMINIO_NAO_PERMITIDO', $dados['codigo'] ?? null);
            self::assertSame('validacao', $dados['tipo'] ?? null);
        }

        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => $emailOriginal]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testNormalizaMaiusculasEEspacosNoDominio(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
            'email' => 'X@IFRO.EDU.BR ',
            'justificativa' => 'Normalização de domínio de e-mail.',
        ]);

        $resposta->assertOK();
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'x@ifro.edu.br']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_novo' => 'x@ifro.edu.br',
        ]);
    }

    public function testEdicaoApenasDeNomeEmContaComEmailExternoContinuaFuncionando(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Alterado Sem Mudar Email',
            'senha_admin' => $this->senha,
        ]);

        $resposta->assertOK();
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => 'Nome Alterado Sem Mudar Email']);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
    }

    public function testFiltroDePermissaoBloqueiaUsuarioSemPermissaoNasQuatroRotas(): void
    {
        $operador = $this->criarUsuario('operadorSemPermissao');
        $alvo = $this->criarUsuario('conta.alvo');
        $redirectUrl = config('Auth')->permissionDeniedRedirect();

        $resp1 = $this->actingAs($operador)->get('admin/contas');
        $resp1->assertRedirectTo($redirectUrl);

        $resp2 = $this->actingAs($operador)->get('admin/contas/tabela');
        $resp2->assertRedirectTo($redirectUrl);

        $resp3 = $this->actingAs($operador)->get('admin/contas/' . $alvo->id);
        $resp3->assertRedirectTo($redirectUrl);

        $resp4 = $this->actingAs($operador)->post('admin/contas/' . $alvo->id, [
            csrf_token() => csrf_hash(),
            'nome' => 'Tentativa Bloqueada',
            'senha_admin' => $this->senha,
        ]);
        $resp4->assertRedirectTo($redirectUrl);
    }

    public function testMinimizacaoDeIpNaAuditoria(): void
    {
        $servico = service('edicaoContaService');
        $metodo = new \ReflectionMethod($servico, 'minimizarIp');

        self::assertSame('192.168.1.0', $metodo->invoke($servico, '192.168.1.150'));
        self::assertSame('10.0.0.0', $metodo->invoke($servico, '10.0.0.1'));
        self::assertSame('2001:db8:85a3', $metodo->invoke($servico, '2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
        self::assertSame('2001:db8:85a3', $metodo->invoke($servico, '2001:db8:85a3::1'));
        self::assertNull($metodo->invoke($servico, 'invalido'));
        self::assertNull($metodo->invoke($servico, ''));
        self::assertNull($metodo->invoke($servico, '0.0.0.0'));
    }

    private function criarUsuario(string $nome, bool $podeEditar = false): User
    {
        $modelo = model(UserModel::class);
        $usuario = new User(['username' => $nome]);
        $modelo->save($usuario);
        $usuario = $modelo->findById($modelo->getInsertID());
        $usuario->createEmailIdentity([
            'email' => 'conta' . ++$this->contador . '@example.com',
            'password' => $this->senha,
        ]);

        if ($podeEditar) {
            $usuario->addPermission('contas.editar');
        }

        return $usuario;
    }

    /** @param array<string, mixed> $campos */
    private function enviarAtualizacao(User $operador, int $contaId, array $campos): TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->actingAs($operador)->post('admin/contas/' . $contaId, $campos);
    }
}
