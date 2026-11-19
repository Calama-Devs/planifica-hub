<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UsuarioAutenticacaoModel;
use App\Services\EdicaoContaService;
use CodeIgniter\Config\Services;
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

        $banco = $this->db;
        $tabelaUsuarios = $banco->prefixTable(config('Auth')->tables['users']);
        if (! $banco->fieldExists('nome_completo', $tabelaUsuarios)) {
            $banco->query(
                'ALTER TABLE ' . $banco->escapeIdentifiers($tabelaUsuarios)
                . ' ADD COLUMN nome_completo VARCHAR(255) NULL',
            );
        }
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

    public function testRecusaTrocaDeEmailQuandoIdentidadeEstaAusenteSemAlterarDados(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuarioSemIdentidadeEmail('conta.sem.email');

        $consulta = $this->actingAs($operador)->get('admin/contas/' . $alvo->id);
        $consulta->assertOK();
        self::assertSame('', json_decode($consulta->getJSON(), true)['conta']['email']);

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Não Gravado',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Solicitação de troca registrada.',
        ]);

        $resposta->assertStatus(422);
        $dados = json_decode($resposta->getJSON(), true);
        self::assertSame('validacao', $dados['tipo']);
        self::assertSame('IDENTIDADE_EMAIL_AUSENTE', $dados['codigo']);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $alvo->id, 'type' => 'email_password']);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testPermiteEdicaoDoNomeSemIdentidadeEmail(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuarioSemIdentidadeEmail('conta.sem.email');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Atualizado',
            'senha_admin' => $this->senha,
        ]);

        $resposta->assertOK();
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => 'Nome Atualizado']);
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $alvo->id, 'type' => 'email_password']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'campos_alterados' => 'nome_completo',
            'resultado' => 'sucesso',
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

    public function testViolacaoDeUnicidadeDuranteGravacaoDesfazNomeEAuditoria(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $conflito = $this->criarUsuario('conta.conflito');
        $emailNovo = 'concorrente@ifro.edu.br';
        $tabelaIdentidades = $this->db->prefixTable(config('Auth')->tables['identities']);
        $this->db->query(
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_auth_identities_type_secret '
            . 'ON ' . $this->db->escapeIdentifiers($tabelaIdentidades) . ' (type, secret)',
        );

        Services::injectMock('edicaoContaService', new class ($conflito, $emailNovo) extends EdicaoContaService {
            private bool $conflitoInserido = false;

            public function __construct(
                private readonly User $conflito,
                private readonly string $emailNovo,
            ) {
                parent::__construct();
            }

            protected function emailEmUso(string $email, int $contaId): bool
            {
                if (! $this->conflitoInserido) {
                    $this->conflito->createEmailIdentity([
                        'email' => $this->emailNovo,
                        'password' => 'senha-de-teste',
                    ]);
                    $this->conflitoInserido = true;

                    return false;
                }

                return parent::emailEmUso($email, $contaId);
            }
        });

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome não deve gravar',
            'senha_admin' => $this->senha,
            'email' => $emailNovo,
            'justificativa' => 'Solicitação administrativa registrada.',
        ]);

        $resposta->assertStatus(422);
        $dados = json_decode($resposta->getJSON(), true);
        self::assertSame('validacao', $dados['tipo']);
        self::assertSame('EMAIL_DUPLICADO', $dados['codigo']);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
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

    public function testListaEBuscaUsamNomeCompletoComFallbackParaUsername(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $editado = $this->criarUsuario('usuario.editado');
        $semNome = $this->criarUsuario('usuario.sem.nome');
        model(UsuarioAutenticacaoModel::class)->update(
            $editado->id,
            ['nome_completo' => 'Nome Exibido Atualizado'],
        );

        $resposta = $this->actingAs($operador)->get('admin/contas/tabela?draw=1&start=0&length=100');
        $resposta->assertOK();
        $linhas = json_decode($resposta->getJSON(), true)['data'];
        $porId = array_column($linhas, null, 'id');
        self::assertSame('Nome Exibido Atualizado', $porId[$editado->id]['username']);
        self::assertSame('usuario.sem.nome', $porId[$semNome->id]['username']);

        $busca = $this->actingAs($operador)->get(
            'admin/contas/tabela?draw=2&start=0&length=10&search%5Bvalue%5D=Nome%20Exibido%20Atualizado',
        );
        $busca->assertOK();
        $dadosBusca = json_decode($busca->getJSON(), true);
        self::assertSame(1, $dadosBusca['recordsFiltered']);
        self::assertSame('Nome Exibido Atualizado', $dadosBusca['data'][0]['username']);
    }

    public function testEdicaoAdministrativaCompartilhaRegrasDeNome(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        foreach (['Ana-Maria', 'João D’Ávila'] as $nomeValido) {
            $this->enviarAtualizacao($operador, (int) $alvo->id, [
                'nome' => $nomeValido,
                'senha_admin' => $this->senha,
            ])->assertOK();
        }

        $invalido = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome 2',
            'senha_admin' => $this->senha,
        ]);
        $invalido->assertStatus(422);
        self::assertSame('CONTA_NOME_INVALIDO', json_decode($invalido->getJSON(), true)['codigo']);
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

    private function criarUsuarioSemIdentidadeEmail(string $nome): User
    {
        $modelo = model(UserModel::class);
        $modelo->save(new User(['username' => $nome]));
        return $modelo->findById($modelo->getInsertID());
    }

    /** @param array<string, mixed> $campos */
    private function enviarAtualizacao(User $operador, int $contaId, array $campos): TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->actingAs($operador)->post('admin/contas/' . $contaId, $campos);
    }
}
