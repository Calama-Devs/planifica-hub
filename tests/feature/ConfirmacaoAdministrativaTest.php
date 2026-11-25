<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\EstadoContaController;
use App\Models\EstadoContaModel;
use App\Services\ConfirmacaoAdministrativaService;
use App\Services\EstadoContaService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestLogger;
use CodeIgniter\Test\TestResponse;
use Config\ConfirmacaoAdministrativa;
use Config\Services;
use RuntimeException;
use Tests\Support\Traits\CriaContasTrait;

/**
 * Contrato HTTP da confirmação de senha, exercitado pelas rotas administrativas reais que a usam.
 */
final class ConfirmacaoAdministrativaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use CriaContasTrait;

    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private string $senha;
    private User $operador;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        // O limite de tentativas usa o cache: cada teste começa com o contador zerado e sem tocar o cache real.
        Services::injectMock('cache', new MockCache());
        $this->senha = 'Operador@9' . bin2hex(random_bytes(12));
        $this->operador = $this->criarConta('operador.http', 'superadmin');
        $this->operador->createEmailIdentity(['email' => 'operador.http@example.com', 'password' => $this->senha]);
        auth()->login($this->operador);
    }

    public function testSenhaCorretaExecutaAOperacaoEDevolveOContratoCompleto(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');

        $resposta = $this->desativarPelaRota($conta, $this->senha);

        $resposta->assertOK();
        $resposta->assertJSONFragment(['sucesso' => true, 'tipo' => 'sucesso', 'codigo' => 'CONTA_DESATIVADA']);
        self::assertFalse((bool) $this->recarregar($conta)->active);

        $json = json_decode($resposta->getJSON(), true);
        self::assertSame(csrf_hash(), $json['csrf']['hash']);
        self::assertSame(csrf_token(), $json['csrf']['nome']);
        self::assertStringContainsString('no-store', $resposta->response()->getHeaderLine('Cache-Control'));
        self::assertStringNotContainsString($this->senha, (string) $resposta->getJSON());
    }

    public function testSenhaIncorretaRetorna422SemAlterarNada(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');

        $resposta = $this->desativarPelaRota($conta, 'senha-incorreta');

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['sucesso' => false, 'tipo' => 'permissao', 'codigo' => 'CONFIRMACAO_INVALIDA']);
        self::assertTrue((bool) $this->recarregar($conta)->active);
        self::assertStringNotContainsString('senha-incorreta', (string) $resposta->getJSON());
    }

    public function testSenhaAusenteOuForaDoFormatoEhRecusada(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');

        foreach ([null, '', ['lista']] as $senha) {
            $campos = [csrf_token() => csrf_hash()];

            if ($senha !== null) {
                $campos['senha_admin'] = $senha;
            }

            $resposta = $this->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/desativar', $campos);

            $resposta->assertStatus(422);
            $resposta->assertJSONFragment(['codigo' => 'CONFIRMACAO_INVALIDA']);
        }

        self::assertTrue((bool) $this->recarregar($conta)->active);
    }

    public function testSenhaEnviadaPelaUrlNaoEhAceita(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');

        $resposta = $this->withSession($_SESSION)->post(
            'admin/contas/' . $conta->id . '/desativar?senha_admin=' . rawurlencode($this->senha),
            [csrf_token() => csrf_hash()],
        );

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['codigo' => 'CONFIRMACAO_INVALIDA']);
        self::assertTrue((bool) $this->recarregar($conta)->active);
    }

    public function testRecusaIdentificadorDeContaInvalido(): void
    {
        foreach (['0', 'abc', '99999999999'] as $contaId) {
            $resposta = $this->withSession($_SESSION)->post('admin/contas/' . $contaId . '/desativar', [
                'senha_admin' => $this->senha,
                csrf_token() => csrf_hash(),
            ]);

            $resposta->assertStatus(422);
            $resposta->assertJSONFragment(['codigo' => 'CONTA_ENTRADA_INVALIDA']);
        }
    }

    public function testOperadorVemDaSessaoENuncaDoCorpoDaRequisicao(): void
    {
        $senhaDoOutro = 'Outro@Admin9' . bin2hex(random_bytes(12));
        $outroAdmin = $this->criarConta('outro.admin', 'superadmin');
        $outroAdmin->createEmailIdentity(['email' => 'outro.admin@example.com', 'password' => $senhaDoOutro]);
        $conta = $this->criarConta('conta.afetada', 'user');

        $resposta = $this->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/desativar', [
            'senha_admin' => $senhaDoOutro,
            'operador_id' => $outroAdmin->id,
            'user_id' => $outroAdmin->id,
            csrf_token() => csrf_hash(),
        ]);

        $resposta->assertStatus(422);
        $resposta->assertJSONFragment(['codigo' => 'CONFIRMACAO_INVALIDA']);
        self::assertTrue((bool) $this->recarregar($conta)->active);
    }

    public function testContaAfetadaVemSoDaRotaNuncaDoCorpoDaRequisicao(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');
        $outraConta = $this->criarConta('outra.conta', 'user');

        $resposta = $this->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/desativar', [
            'senha_admin' => $this->senha,
            'conta_id' => $outraConta->id,
            'acao' => 'reativar_conta',
            csrf_token() => csrf_hash(),
        ]);

        $resposta->assertOK();
        self::assertFalse((bool) $this->recarregar($conta)->active);
        self::assertTrue((bool) $this->recarregar($outraConta)->active);
    }

    public function testConfirmacaoDeUmaOperacaoNaoAutorizaAProximaRequisicao(): void
    {
        $primeira = $this->criarConta('primeira.conta', 'user');
        $segunda = $this->criarConta('segunda.conta', 'user');

        $this->desativarPelaRota($primeira, $this->senha)->assertOK();

        $semSenha = $this->desativarPelaRota($segunda, '');
        $semSenha->assertStatus(422);
        $semSenha->assertJSONFragment(['codigo' => 'CONFIRMACAO_INVALIDA']);
        self::assertTrue((bool) $this->recarregar($segunda)->active);
    }

    public function testLimiteDeTentativasValeParaTodasAsRotasAdministrativas(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');
        $outraConta = $this->criarConta('outra.conta', 'user');
        $tentativas = config(ConfirmacaoAdministrativa::class)->maxTentativas;

        // As senhas incorretas se alternam entre duas rotas e duas contas, e todas contam no mesmo limite.
        for ($tentativa = 0; $tentativa < $tentativas; $tentativa++) {
            $resposta = $tentativa % 2 === 0
                ? $this->desativarPelaRota($conta, 'incorreta-' . $tentativa)
                : $this->editarPelaRota($outraConta, 'incorreta-' . $tentativa);

            $resposta->assertStatus(422);
            $resposta->assertJSONFragment(['codigo' => 'CONFIRMACAO_INVALIDA']);
        }

        // Com o limite excedido, nem a senha correta libera a operação, em qualquer das rotas.
        $bloqueadas = [
            $this->desativarPelaRota($conta, $this->senha),
            $this->editarPelaRota($outraConta, $this->senha),
        ];

        foreach ($bloqueadas as $resposta) {
            $resposta->assertStatus(429);
            $resposta->assertJSONFragment(['sucesso' => false, 'codigo' => 'CONFIRMACAO_LIMITE_EXCEDIDO']);
            self::assertGreaterThan(0, (int) $resposta->response()->getHeaderLine('Retry-After'));
            self::assertStringContainsString('Aguarde', json_decode($resposta->getJSON(), true)['mensagem']);
        }

        self::assertTrue((bool) $this->recarregar($conta)->active);
        $this->seeInDatabase('users', ['id' => $outraConta->id, 'nome_completo' => null]);
    }

    public function testSenhaNaoApareceEmLogNemNaResposta(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');
        $senhaIncorreta = 'Incorreta@' . bin2hex(random_bytes(12));

        $recusada = $this->desativarPelaRota($conta, $senhaIncorreta);
        $aceita = $this->desativarPelaRota($conta, $this->senha);

        foreach ([$recusada, $aceita] as $resposta) {
            foreach ([$senhaIncorreta, $this->senha] as $senha) {
                self::assertStringNotContainsString($senha, (string) $resposta->getJSON());
            }
        }

        foreach ([$senhaIncorreta, $this->senha] as $senha) {
            foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $nivel) {
                self::assertFalse(TestLogger::didLog($nivel, $senha, false), 'A senha apareceu em log.');
            }
        }
    }

    public function testSenhaNaoApareceNoRastroDeExcecaoLancadaDentroDaOperacao(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');
        // Confirmação que falha por exceção, com o mesmo atributo do serviço real: o rastro atravessa o Controller,
        // o Service da operação e a confirmação.
        $confirmacao = new class extends ConfirmacaoAdministrativaService {
            public function confirmar(string $acao, int $alvoId, #[\SensitiveParameter] string $senha): User|array
            {
                throw new RuntimeException('Falha simulada na confirmação.');
            }
        };
        Services::injectMock('estadoContaService', new EstadoContaService(null, null, null, $confirmacao));
        $anterior = ini_set('zend.exception_ignore_args', '0');
        $quadros = [];

        try {
            $this->desativarPelaRota($conta, $this->senha);
        } catch (RuntimeException $erro) {
            // Só os quadros do código do aplicativo: os do teste e do FeatureTestTrait recebem a senha do teste.
            $quadros = array_values(array_filter(
                $erro->getTrace(),
                static fn (array $quadro): bool => str_starts_with($quadro['class'] ?? '', 'App\\'),
            ));
        } finally {
            ini_set('zend.exception_ignore_args', $anterior === false ? '1' : $anterior);
        }

        $rastro = print_r($quadros, true);
        $classes = array_column($quadros, 'class');
        self::assertContains(EstadoContaController::class, $classes, 'A exceção deveria passar pelo Controller.');
        self::assertContains(EstadoContaService::class, $classes, 'A exceção deveria passar pelo Service.');
        self::assertStringContainsString('SensitiveParameterValue', $rastro);
        self::assertStringNotContainsString($this->senha, $rastro);
    }

    public function testTelaDeAcessosEntregaAsUrlsDasOperacoesPelasRotasNomeadas(): void
    {
        $resposta = $this->withSession($_SESSION)->get('admin/acessos');

        $resposta->assertOK();
        $corpo = html_entity_decode((string) $resposta->response()->getBody(), ENT_QUOTES | ENT_HTML5);
        self::assertStringContainsString('data-marcador-conta="999999999"', $corpo);

        foreach (['atualizar', 'desativar', 'reativar'] as $operacao) {
            $url = url_to('contas.' . $operacao, 999999999);
            self::assertStringContainsString('data-url-' . $operacao . '="' . $url . '"', $corpo);
        }
    }

    public function testSessaoRevogadaNaRotaDaModalDevolveORedirecionamento(): void
    {
        $conta = $this->criarConta('conta.afetada', 'user');
        self::assertTrue(model(EstadoContaModel::class)->update($this->operador->id, ['versao_sessao' => 2]));

        $resposta = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/desativar', [
                'senha_admin' => $this->senha,
                csrf_token() => csrf_hash(),
            ]);

        $resposta->assertStatus(403);
        $resposta->assertJSONFragment([
            'sucesso' => false,
            'codigo' => 'SESSAO_REVOGADA',
            'redirecionamento' => url_to('login'),
        ]);
        self::assertTrue((bool) $this->recarregar($conta)->active);
    }

    private function desativarPelaRota(User $conta, string $senha): TestResponse
    {
        return $this->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/desativar', [
            'senha_admin' => $senha,
            csrf_token() => csrf_hash(),
        ]);
    }

    private function editarPelaRota(User $conta, string $senha): TestResponse
    {
        return $this->withSession($_SESSION)->post('admin/contas/' . $conta->id, [
            'nome' => 'Maria da Silva Souza',
            'senha_admin' => $senha,
            csrf_token() => csrf_hash(),
        ]);
    }
}
