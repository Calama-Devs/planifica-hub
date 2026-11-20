<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UsuarioAutenticacaoModel;
use App\Services\EdicaoContaService;
use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Fakes\EmailContaFake;

final class ContaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private string $senha = 'Senha-de-teste-123!';
    private int $contador = 0;
    private EmailContaFake $emailFake;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->emailFake = new EmailContaFake();
        Services::injectMock('email', $this->emailFake);

        $banco = $this->db;
        $tabelaUsuarios = $banco->prefixTable(config('Auth')->tables['users']);
        if (! $banco->fieldExists('nome_completo', $tabelaUsuarios)) {
            $banco->query(
                'ALTER TABLE ' . $banco->escapeIdentifiers($tabelaUsuarios)
                . ' ADD COLUMN nome_completo VARCHAR(255) NULL',
            );
        }
    }

    public function testGruposAdministrativosRecebemPermissaoDeEditarContas(): void
    {
        $superadmin = $this->criarUsuario('operador.superadmin');
        $superadmin->addGroup('superadmin');
        $admin = $this->criarUsuario('operador.admin');
        $admin->addGroup('admin');
        $usuario = $this->criarUsuario('usuario.comum');
        $usuario->addGroup('user');

        self::assertTrue($superadmin->can('contas.editar'));
        self::assertTrue($admin->can('contas.editar'));
        self::assertFalse($usuario->can('contas.editar'));
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

        $resposta->assertStatus(403);
        self::assertSame('CONTA_SEM_PERMISSAO', json_decode($resposta->getJSON(), true)['codigo']);
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
        $consulta = $this->actingAs($operador)->get('admin/contas/' . $alvo->id);
        $dadosConsulta = json_decode($consulta->getJSON(), true)['conta'];
        self::assertFalse($dadosConsulta['confirmacao_recente']);
        self::assertNull($dadosConsulta['confirmacao_expira_em']);
        self::assertTrue(db_connect()->transStatus());
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);

        $inexistente = $this->enviarAtualizacao($operador, 99999999, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
        ]);

        $inexistente->assertStatus(404);
        $consultaInexistente = $this->actingAs($operador)->get('admin/contas/99999999');
        $consultaInexistente->assertStatus(404);
        self::assertSame(
            'CONTA_NAO_ENCONTRADA',
            json_decode($consultaInexistente->getJSON(), true)['codigo'],
        );
    }

    public function testConfirmacaoRecentePodeSerReutilizadaSomenteNaMesmaContaEOperador(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $outroOperador = $this->criarUsuario('outro.operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $outraConta = $this->criarUsuario('conta.outra');

        $servico = service('edicaoContaService');
        $senhaIncorreta = $servico->atualizar((int) $alvo->id, 'Nome Não Gravado', 'incorreta', $operador);
        self::assertFalse($senhaIncorreta['sucesso']);
        self::assertNull($servico->consultar((int) $alvo->id, $operador)['confirmacao_expira_em']);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
        $primeiraAlteracao = $servico->atualizar((int) $alvo->id, 'Primeiro Nome', $this->senha, $operador);
        self::assertTrue($primeiraAlteracao['sucesso']);

        $confirmacao = $servico->consultar((int) $alvo->id, $operador);
        self::assertTrue($confirmacao['confirmacao_recente']);
        self::assertGreaterThan(0, $confirmacao['confirmacao_expira_em']);
        self::assertLessThanOrEqual(300, $confirmacao['confirmacao_expira_em']);

        $segundaAlteracao = $servico->atualizar((int) $alvo->id, 'Segundo Nome', '', $operador);
        self::assertTrue($segundaAlteracao['sucesso']);
        self::assertFalse($servico->atualizar((int) $outraConta->id, 'Nome Outra', '', $operador)['sucesso']);
        self::assertFalse($servico->atualizar((int) $alvo->id, 'Nome Outro Operador', '', $outroOperador)['sucesso']);
        $this->seeNumRecords(2, 'auditoria_edicao_contas', ['conta_id' => $alvo->id]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $outraConta->id]);
    }

    public function testTrocaDeEmailExigeSenhaMesmoComConfirmacaoRecente(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $servico = service('edicaoContaService');
        $primeiraEdicao = $servico->atualizar((int) $alvo->id, 'Nome Atualizado', $this->senha, $operador);

        self::assertTrue($primeiraEdicao['sucesso']);
        self::assertTrue($servico->consultar((int) $alvo->id, $operador)['confirmacao_recente']);

        $semSenha = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Atualizado',
            'email' => 'troca@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $semSenha->assertStatus(422);
        self::assertSame('CONFIRMACAO_INVALIDA', json_decode($semSenha->getJSON(), true)['codigo']);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => 'Nome Atualizado']);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo->id,
            'type' => 'email_password',
            'secret' => 'conta2@example.com',
        ]);
        $this->dontSeeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id, 'campos_alterados' => 'email']);

        $comSenha = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Atualizado',
            'senha_admin' => $this->senha,
            'email' => 'troca@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $comSenha->assertOK();
        $this->seeInDatabase('solicitacoes_troca_email', [
            'conta_id' => $alvo->id,
            'email_novo' => 'troca@ifro.edu.br',
        ]);
    }

    public function testConfirmacaoExpiradaExigeSenhaNovamente(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        Time::setTestNow('2026-11-19 12:00:00');

        try {
            $servico = service('edicaoContaService');
            self::assertTrue($servico->atualizar((int) $alvo->id, 'Primeiro Nome', $this->senha, $operador)['sucesso']);
            Time::setTestNow('2026-11-19 12:05:01');
            $expirada = $servico->atualizar((int) $alvo->id, 'Nome Expirado', '', $operador);
            self::assertFalse($expirada['sucesso']);
            self::assertSame('CONFIRMACAO_INVALIDA', $expirada['codigo']);
            $this->seeNumRecords(1, 'auditoria_edicao_contas', ['conta_id' => $alvo->id]);
            $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => 'Primeiro Nome']);
        } finally {
            Time::setTestNow();
        }
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

    public function testRejeitaEmailVazioOuJustificativaSemEmailSemPersistirDados(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $casosEmailVazio = [
            ['email' => ''],
            ['email' => '', 'justificativa' => 'Correção solicitada pelo titular.'],
            ['email' => '   ', 'justificativa' => 'Correção solicitada pelo titular.'],
        ];

        foreach ($casosEmailVazio as $dadosEmailVazio) {
            $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
                'nome' => 'Nome não deve ser salvo',
                'senha_admin' => $this->senha,
                ...$dadosEmailVazio,
            ]);

            $resposta->assertStatus(422);
            self::assertSame('EMAIL_INVALIDO', json_decode($resposta->getJSON(), true)['codigo']);
        }

        $justificativaSemEmail = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome não deve ser salvo',
            'senha_admin' => $this->senha,
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $justificativaSemEmail->assertStatus(422);
        self::assertSame(
            'JUSTIFICATIVA_EMAIL_INVALIDA',
            json_decode($justificativaSemEmail->getJSON(), true)['codigo'],
        );

        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testExigeConfirmacaoNoNovoEmailAntesDeAtualizarLogin(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $identidades = model(\CodeIgniter\Shield\Models\UserIdentityModel::class);
        $identidades->insert([
            'user_id' => $alvo->id,
            'type' => 'magic-link-reset',
            'secret' => 'token-recuperacao-pendente',
            'expires' => date('Y-m-d H:i:s', time() + 600),
        ]);
        $identidades->insert([
            'user_id' => $alvo->id,
            'type' => 'magic-link',
            'secret' => 'token-login-pendente',
            'expires' => date('Y-m-d H:i:s', time() + 600),
        ]);

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
            'secret' => 'conta2@example.com',
        ]);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => 'Nome Atualizado']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_anterior' => 'conta2@example.com',
            'email_novo' => 'novo.email@ifro.edu.br',
            'resultado' => 'pendente_confirmacao',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $auditoriaPendente = $this->db->table('auditoria_edicao_contas')
            ->where('conta_id', $alvo->id)
            ->where('email_novo', 'novo.email@ifro.edu.br')
            ->get()
            ->getRowArray();
        self::assertIsArray($auditoriaPendente);
        $createdAtOriginal = $auditoriaPendente['created_at'];
        $token = $this->tokenDaMensagem(0);
        $this->seeInDatabase('solicitacoes_troca_email', [
            'conta_id' => $alvo->id,
            'email_novo' => 'novo.email@ifro.edu.br',
            'token_hash' => hash('sha256', $token),
        ]);

        $pagina = $this->get('confirmar-troca-email?token=' . rawurlencode($token));
        $pagina->assertOK();
        self::assertStringContainsString('Confirmar novo e-mail', $pagina->getBody());
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);

        $confirmacao = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $token,
        ]);
        $confirmacao->assertOK();
        self::assertStringContainsString('Novo e-mail confirmado', $confirmacao->getBody());
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo->id,
            'type' => 'email_password',
            'secret' => 'novo.email@ifro.edu.br',
        ]);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_anterior' => 'conta2@example.com',
            'email_novo' => 'novo.email@ifro.edu.br',
            'resultado' => 'sucesso',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $auditoriaFinalizada = $this->db->table('auditoria_edicao_contas')
            ->where('conta_id', $alvo->id)
            ->where('email_novo', 'novo.email@ifro.edu.br')
            ->get()
            ->getRowArray();
        self::assertIsArray($auditoriaFinalizada);
        self::assertSame($createdAtOriginal, $auditoriaFinalizada['created_at']);
        self::assertNotEmpty($auditoriaFinalizada['updated_at']);
        $this->dontSeeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
        self::assertCount(2, $this->emailFake->corpos, 'O sistema confirma o endereço novo e avisa o anterior.');
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'token-recuperacao-pendente']);
        $this->dontSeeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'token-login-pendente']);

        $repeticao = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $token,
        ]);
        $repeticao->assertStatus(410);
        self::assertStringContainsString(
            'inválido ou expirou',
            html_entity_decode(strip_tags($repeticao->getBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }

    public function testPostPublicoDeConfirmacaoExigeTokenCsrf(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $solicitacao = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Conta Alvo',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $solicitacao->assertOK();
        $token = $this->tokenDaMensagem(0);

        $csrfRecusado = false;
        try {
            $this->post('confirmar-troca-email', ['token' => $token]);
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            $csrfRecusado = true;
        }

        self::assertTrue($csrfRecusado, 'O filtro CSRF deve interromper a requisição sem token válido.');
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo->id,
            'type' => 'email_password',
            'secret' => 'conta2@example.com',
        ]);
        $this->seeInDatabase('solicitacoes_troca_email', [
            'conta_id' => $alvo->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $tokenInvalido = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => 'token-invalido',
        ]);
        $tokenInvalido->assertStatus(410);
        self::assertStringContainsString(
            'O link de confirmação é inválido ou expirou.',
            html_entity_decode(strip_tags($tokenInvalido->getBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        $this->seeInDatabase('solicitacoes_troca_email', [
            'conta_id' => $alvo->id,
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function testFalhaNoEnvioDeConfirmacaoDesfazNomeESolicitacao(): void
    {
        $this->emailFake->lancarExcecao = true;
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome não deve gravar',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);

        $resposta->assertStatus(503);
        $dados = json_decode($resposta->getJSON(), true);
        self::assertFalse($dados['sucesso']);
        self::assertSame('regra_negocio', $dados['tipo']);
        self::assertSame('CONFIRMACAO_EMAIL_NAO_ENVIADA', $dados['codigo']);
        self::assertSame(503, $dados['status']);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->dontSeeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
        $this->dontSeeInDatabase('auditoria_edicao_contas', ['conta_id' => $alvo->id]);
    }

    public function testEmailQueSeTornaDuplicadoAntesDaConfirmacaoERecusado(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $outro = $this->criarUsuario('conta.outro');
        $emailNovo = 'concorrente@ifro.edu.br';

        $solicitacao = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Conta Alvo',
            'senha_admin' => $this->senha,
            'email' => $emailNovo,
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $solicitacao->assertOK();

        model(\CodeIgniter\Shield\Models\UserIdentityModel::class)->update(
            $outro->getEmailIdentity()->id,
            ['secret' => $emailNovo],
        );
        $confirmacao = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $this->tokenDaMensagem(0),
        ]);

        $confirmacao->assertStatus(422);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_novo' => $emailNovo,
            'resultado' => 'conflito',
        ]);
    }

    public function testDominioRevogadoAntesDaConfirmacaoCancelaSolicitacao(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $emailNovo = 'novo.email@ifro.edu.br';

        $solicitacao = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Conta Alvo',
            'senha_admin' => $this->senha,
            'email' => $emailNovo,
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $solicitacao->assertOK();

        $auditoria = $this->db->table('auditoria_edicao_contas')
            ->where('conta_id', $alvo->id)
            ->where('email_novo', $emailNovo)
            ->get()
            ->getRowArray();
        self::assertIsArray($auditoria);
        $createdAtOriginal = $auditoria['created_at'];
        $dominiosOriginais = config('Contas')->dominiosPermitidos;

        try {
            config('Contas')->dominiosPermitidos = ['outro.edu.br'];
            $confirmacao = $this->confirmarEmailDaMensagem(0);
        } finally {
            config('Contas')->dominiosPermitidos = $dominiosOriginais;
        }

        $confirmacao->assertStatus(422);
        self::assertStringContainsString(
            'Este link não pode ser confirmado.',
            html_entity_decode(strip_tags($confirmacao->getBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        self::assertStringNotContainsString('ifro.edu.br', $confirmacao->getBody());
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo->id,
            'type' => 'email_password',
            'secret' => 'conta2@example.com',
        ]);
        $this->dontSeeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
        $auditoriaFinalizada = $this->db->table('auditoria_edicao_contas')
            ->where('id', $auditoria['id'])
            ->get()
            ->getRowArray();
        self::assertIsArray($auditoriaFinalizada);
        self::assertSame('dominio_bloqueado', $auditoriaFinalizada['resultado']);
        self::assertSame($createdAtOriginal, $auditoriaFinalizada['created_at']);
        self::assertNotEmpty($auditoriaFinalizada['updated_at']);
        self::assertCount(
            1,
            $this->emailFake->corpos,
            'O endereço anterior não recebe aviso quando a confirmação é bloqueada.',
        );

        $repeticao = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $this->tokenDaMensagem(0),
        ]);
        $repeticao->assertStatus(410);
    }

    public function testLinkExpiradoNaoAtualizaIdentidade(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');

        $solicitacao = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Conta Alvo',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $solicitacao->assertOK();
        db_connect()->table('solicitacoes_troca_email')
            ->where('conta_id', $alvo->id)
            ->update(['expira_em' => date('Y-m-d H:i:s', time() - 1)]);

        $confirmacao = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $this->tokenDaMensagem(0),
        ]);

        $confirmacao->assertStatus(410);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_novo' => 'novo.email@ifro.edu.br',
            'resultado' => 'expirada',
        ]);
        $this->dontSeeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
    }

    public function testFalhaNaAuditoriaDaConfirmacaoMantemLoginAnterior(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $solicitacao = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Conta Alvo',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);
        $solicitacao->assertOK();

        $tabelaAuditoria = $this->db->prefixTable('auditoria_edicao_contas');
        $this->db->query(
            'CREATE TRIGGER falha_auditoria_confirmacao BEFORE UPDATE ON '
            . $this->db->escapeIdentifiers($tabelaAuditoria) . ' '
            . 'BEGIN SELECT RAISE(ABORT, "falha de auditoria"); END',
        );

        $confirmacao = $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $this->tokenDaMensagem(0),
        ]);

        $confirmacao->assertStatus(500);
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->seeInDatabase('auditoria_edicao_contas', [
            'conta_id' => $alvo->id,
            'email_novo' => 'novo.email@ifro.edu.br',
            'resultado' => 'pendente_confirmacao',
        ]);
        $this->seeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
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

    public function testFalhaDeAuditoriaDesfazAtualizacao(): void
    {
        $operador = $this->criarUsuario('operador', true);
        $alvo = $this->criarUsuario('conta.alvo');
        $tabelaAuditoria = db_connect()->prefixTable('auditoria_edicao_contas');
        db_connect()->query(
            'CREATE TRIGGER falha_auditoria_edicao BEFORE INSERT ON ' . $tabelaAuditoria . ' '
            . "WHEN NEW.campos_alterados = 'email' "
            . "BEGIN SELECT RAISE(ABORT, 'falha de auditoria'); END",
        );

        $resposta = $this->enviarAtualizacao($operador, (int) $alvo->id, [
            'nome' => 'Nome Novo',
            'senha_admin' => $this->senha,
            'email' => 'novo.email@ifro.edu.br',
            'justificativa' => 'Correção solicitada pelo titular.',
        ]);

        $resposta->assertStatus(500);
        $this->seeInDatabase('users', ['id' => $alvo->id, 'nome_completo' => null]);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $alvo->id,
            'type' => 'email_password',
            'secret' => 'conta2@example.com',
        ]);
        $this->dontSeeInDatabase('solicitacoes_troca_email', ['conta_id' => $alvo->id]);
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
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo1->id, 'secret' => 'conta2@example.com']);
        $this->confirmarEmailDaMensagem(0);
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
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo2->id, 'secret' => 'conta3@example.com']);
        $this->confirmarEmailDaMensagem(2);
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
        $this->seeInDatabase('auth_identities', ['user_id' => $alvo->id, 'secret' => 'conta2@example.com']);
        $this->confirmarEmailDaMensagem(0);
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

    public function testEndpointsJsonBloqueiamUsuarioSemPermissao(): void
    {
        $operador = $this->criarUsuario('operadorSemPermissao');
        $alvo = $this->criarUsuario('conta.alvo');

        $consulta = $this->actingAs($operador)->get('admin/contas/' . $alvo->id);
        $consulta->assertStatus(403);
        self::assertSame('CONTA_SEM_PERMISSAO', json_decode($consulta->getJSON(), true)['codigo']);

        $atualizacao = $this->actingAs($operador)->post('admin/contas/' . $alvo->id, [
            csrf_token() => csrf_hash(),
            'nome' => 'Tentativa Bloqueada',
            'senha_admin' => $this->senha,
        ]);
        $atualizacao->assertStatus(403);
        self::assertSame('CONTA_SEM_PERMISSAO', json_decode($atualizacao->getJSON(), true)['codigo']);
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

    private function confirmarEmailDaMensagem(int $indice): TestResponse
    {
        $token = $this->tokenDaMensagem($indice);

        return $this->post('confirmar-troca-email', [
            csrf_token() => csrf_hash(),
            'token' => $token,
        ]);
    }

    private function tokenDaMensagem(int $indice): string
    {
        $corpo = html_entity_decode($this->emailFake->corpos[$indice] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        self::assertSame(
            1,
            preg_match('/confirmar-troca-email\?token=([a-f0-9]{64})/i', $corpo, $correspondencias),
            $corpo,
        );

        return $correspondencias[1];
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
