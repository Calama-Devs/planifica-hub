<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Services\ConfirmacaoAdministrativaService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestLogger;
use CodeIgniter\Throttle\Throttler;
use Config\ConfirmacaoAdministrativa;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\Traits\CriaContasTrait;

final class ConfirmacaoAdministrativaServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CriaContasTrait;

    private const INSTANTE_INICIAL = 1_700_000_000;

    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private MockCache $cache;
    private Throttler $limitador;
    private ConfirmacaoAdministrativa $config;
    private ConfirmacaoAdministrativaService $servico;
    private User $operador;
    private User $alvo;
    private string $senhaDoOperador;
    private string $senhaDoAlvo;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->cache = new MockCache();
        $this->limitador = (new Throttler($this->cache))->setTestTime(self::INSTANTE_INICIAL);
        $this->config = new ConfirmacaoAdministrativa();
        $this->config->maxTentativas = 3;
        $this->servico = new ConfirmacaoAdministrativaService($this->config, $this->limitador);

        $this->senhaDoOperador = 'Operador@1' . bin2hex(random_bytes(8));
        $this->senhaDoAlvo = 'Alvo@1' . bin2hex(random_bytes(8));
        $this->operador = $this->criarContaComSenha(
            'operador.confirmacao',
            $this->senhaDoOperador,
            ['contas.desativar', 'contas.editar'],
        );
        $this->alvo = $this->criarContaComSenha('conta.alvo', $this->senhaDoAlvo);

        auth()->login($this->operador);
    }

    protected function tearDown(): void
    {
        auth()->logout();

        parent::tearDown();
    }

    public function testConfirmaComASenhaDoOperadorEDevolveOOperadorDaSessao(): void
    {
        $resultado = $this->confirmar('desativar_conta', $this->senhaDoOperador);

        self::assertInstanceOf(User::class, $resultado);
        self::assertSame((int) $this->operador->id, (int) $resultado->id);
    }

    public function testRecusaSenhaIncorretaSemLiberarAOperacao(): void
    {
        $resultado = $this->confirmar('desativar_conta', 'senha-incorreta');

        $this->assertRecusa($resultado, 'CONFIRMACAO_INVALIDA', 'permissao');
    }

    public function testUsaSomenteASenhaDoOperadorNuncaADaContaAfetada(): void
    {
        $comSenhaDoAlvo = $this->confirmar('desativar_conta', $this->senhaDoAlvo);

        $this->assertRecusa($comSenhaDoAlvo, 'CONFIRMACAO_INVALIDA', 'permissao');
        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));
    }

    public function testRecusaSenhaAusenteSemConsumirTentativas(): void
    {
        for ($tentativa = 0; $tentativa < 10; $tentativa++) {
            $this->assertRecusa($this->confirmar('desativar_conta', ''), 'CONFIRMACAO_INVALIDA', 'permissao');
        }

        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));
    }

    public function testRecusaSenhaGrandeDemaisSemConferirOHash(): void
    {
        $resultado = $this->confirmar('desativar_conta', str_repeat('a', 5000));

        $this->assertRecusa($resultado, 'CONFIRMACAO_INVALIDA', 'permissao');
        self::assertNull($this->cache->get('throttler_confirmacao_administrativa_' . $this->operador->id));
    }

    public function testRecusaQuandoNaoHaSessaoSemConferirSenhaNemContarTentativa(): void
    {
        auth()->logout();

        $resultado = $this->confirmar('desativar_conta', $this->senhaDoOperador);

        $this->assertRecusa($resultado, 'AUTENTICACAO_NECESSARIA', 'permissao');
        self::assertNull($this->cache->get('throttler_confirmacao_administrativa_' . $this->operador->id));
    }

    public function testSenhaCorretaNaoConcedePermissaoQueOOperadorNaoTem(): void
    {
        $this->entrarComo($this->criarContaComSenha('sem.permissao', $this->senhaDoOperador));

        $resultado = $this->confirmar('desativar_conta', $this->senhaDoOperador);

        $this->assertRecusa($resultado, 'CONFIRMACAO_SEM_PERMISSAO', 'permissao');
    }

    public function testRecusaSemPermissaoNaoPermiteDescobrirSenhaPorTentativa(): void
    {
        $this->entrarComo($this->criarContaComSenha('sem.permissao', $this->senhaDoOperador));

        for ($tentativa = 0; $tentativa < 10; $tentativa++) {
            $this->assertRecusa(
                $this->confirmar('desativar_conta', 'chute-' . $tentativa),
                'CONFIRMACAO_SEM_PERMISSAO',
                'permissao',
            );
        }
    }

    public function testPermissaoVemDoGrupoOuDaConcessaoIndividual(): void
    {
        $this->entrarComo($this->criarContaComSenha('pelo.grupo', $this->senhaDoOperador, [], 'superadmin'));
        self::assertInstanceOf(User::class, $this->confirmar('reativar_conta', $this->senhaDoOperador));

        $this->entrarComo($this->criarContaComSenha('direta', $this->senhaDoOperador, ['contas.reativar']));
        self::assertInstanceOf(User::class, $this->confirmar('reativar_conta', $this->senhaDoOperador));
    }

    public function testPermissaoDeUmaAcaoNaoAutorizaOutraAcao(): void
    {
        // O operador padrão tem contas.desativar, mas não contas.reativar.
        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));

        $outraAcao = $this->confirmar('reativar_conta', $this->senhaDoOperador);

        $this->assertRecusa($outraAcao, 'CONFIRMACAO_SEM_PERMISSAO', 'permissao');
    }

    public function testRecusaAcaoQueNaoEstaCadastrada(): void
    {
        $resultado = $this->confirmar('apagar_banco', $this->senhaDoOperador);

        $this->assertRecusa($resultado, 'CONFIRMACAO_OPERACAO_INDISPONIVEL', 'regra_negocio');
    }

    public function testRecusaContaAfetadaInvalida(): void
    {
        foreach ([0, -1] as $alvoId) {
            $resultado = $this->servico->confirmar('desativar_conta', $alvoId, $this->senhaDoOperador);

            $this->assertRecusa($resultado, 'CONFIRMACAO_CONTEXTO_INVALIDO', 'validacao');
        }
    }

    public function testConfirmacaoAnteriorNaoAutorizaNovaChamada(): void
    {
        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));

        // Mesma operação e mesma conta, sem senha.
        $this->assertRecusa($this->confirmar('desativar_conta', ''), 'CONFIRMACAO_INVALIDA', 'permissao');

        // Mesma operação em outra conta, com senha errada.
        $outraConta = $this->servico->confirmar('desativar_conta', (int) $this->alvo->id + 1, 'senha-incorreta');
        $this->assertRecusa($outraConta, 'CONFIRMACAO_INVALIDA', 'permissao');

        // Outra operação sobre a mesma conta, com senha errada.
        $this->assertRecusa($this->confirmar('editar_conta', 'senha-incorreta'), 'CONFIRMACAO_INVALIDA', 'permissao');
    }

    public function testAcaoNovaCadastradaUsaOMesmoMecanismoSemCodigoNovo(): void
    {
        $this->config->acoes['alterar_grupos'] = 'contas.editar';

        self::assertInstanceOf(User::class, $this->confirmar('alterar_grupos', $this->senhaDoOperador));
        $this->assertRecusa($this->confirmar('alterar_grupos', 'senha-incorreta'), 'CONFIRMACAO_INVALIDA', 'permissao');
    }

    public function testBloqueiaAposExcederTentativasMesmoComSenhaCorreta(): void
    {
        $this->errarSenhaVezes(3);

        $comSenhaCorreta = $this->confirmar('desativar_conta', $this->senhaDoOperador);

        $this->assertRecusa($comSenhaCorreta, 'CONFIRMACAO_LIMITE_EXCEDIDO', 'permissao');
        self::assertIsArray($comSenhaCorreta);
        self::assertSame(300, $comSenhaCorreta['espera_segundos']);
        self::assertStringContainsString('5 minutos', $comSenhaCorreta['mensagem']);
    }

    public function testLimiteVaiAlemDaAcaoEDaContaEscolhidas(): void
    {
        $this->assertRecusa($this->confirmar('desativar_conta', 'errada-1'), 'CONFIRMACAO_INVALIDA', 'permissao');
        $this->assertRecusa($this->confirmar('editar_conta', 'errada-2'), 'CONFIRMACAO_INVALIDA', 'permissao');
        $outraConta = $this->servico->confirmar('desativar_conta', (int) $this->alvo->id + 7, 'errada-3');
        $this->assertRecusa($outraConta, 'CONFIRMACAO_INVALIDA', 'permissao');

        $this->assertRecusa(
            $this->confirmar('editar_conta', $this->senhaDoOperador),
            'CONFIRMACAO_LIMITE_EXCEDIDO',
            'permissao',
        );
        $this->assertRecusa(
            $this->servico->confirmar('desativar_conta', (int) $this->alvo->id + 7, $this->senhaDoOperador),
            'CONFIRMACAO_LIMITE_EXCEDIDO',
            'permissao',
        );
    }

    public function testLimiteDeUmOperadorNaoAfetaOutro(): void
    {
        $this->errarSenhaVezes(3);
        $this->assertRecusa(
            $this->confirmar('desativar_conta', $this->senhaDoOperador),
            'CONFIRMACAO_LIMITE_EXCEDIDO',
            'permissao',
        );

        $outro = $this->criarContaComSenha('outro.operador', $this->senhaDoOperador, ['contas.desativar']);
        $this->entrarComo($outro);

        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));
    }

    public function testSenhaCorretaZeraAsTentativasIncorretasAnteriores(): void
    {
        $this->errarSenhaVezes(2);
        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));

        $this->errarSenhaVezes(2);

        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));
    }

    public function testBloqueioTerminaQuandoUmaTentativaEhLiberada(): void
    {
        $this->errarSenhaVezes(3);
        $this->assertRecusa(
            $this->confirmar('desativar_conta', $this->senhaDoOperador),
            'CONFIRMACAO_LIMITE_EXCEDIDO',
            'permissao',
        );

        $this->limitador->setTestTime(self::INSTANTE_INICIAL + 299);
        $this->assertRecusa(
            $this->confirmar('desativar_conta', $this->senhaDoOperador),
            'CONFIRMACAO_LIMITE_EXCEDIDO',
            'permissao',
        );

        $this->limitador->setTestTime(self::INSTANTE_INICIAL + 301);
        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));
    }

    public function testSenhaNaoFicaEmSessaoCacheLogNemResposta(): void
    {
        $recusa = $this->confirmar('desativar_conta', $this->senhaDoAlvo);
        self::assertInstanceOf(User::class, $this->confirmar('desativar_conta', $this->senhaDoOperador));

        $armazenamento = (new ReflectionProperty(MockCache::class, 'cache'))->getValue($this->cache);
        $conteudos = [
            'sessão' => serialize($_SESSION ?? []),
            'cache' => serialize($armazenamento),
            'resposta' => json_encode($recusa, JSON_THROW_ON_ERROR),
        ];

        foreach ([$this->senhaDoOperador, $this->senhaDoAlvo] as $senha) {
            foreach ($conteudos as $onde => $conteudo) {
                self::assertStringNotContainsString($senha, $conteudo, 'A senha apareceu na ' . $onde . '.');
            }

            foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $nivel) {
                self::assertFalse(TestLogger::didLog($nivel, $senha, false), 'A senha apareceu em log.');
            }
        }

        $this->assertLogContains('warning', 'senha incorreta. Operador ' . $this->operador->id);
    }

    public function testSenhaNaoApareceNoRastroDeExcecaoDaConfirmacao(): void
    {
        $anterior = ini_set('zend.exception_ignore_args', '0');
        $rastro = '';
        $limitador = $this->createMock(Throttler::class);
        $limitador->method('check')->willReturnCallback(static function () use (&$rastro): bool {
            $rastro = (new RuntimeException())->getTraceAsString();

            return true;
        });

        try {
            $servico = new ConfirmacaoAdministrativaService($this->config, $limitador);
            $servico->confirmar('desativar_conta', (int) $this->alvo->id, $this->senhaDoOperador);
        } finally {
            ini_set('zend.exception_ignore_args', $anterior === false ? '1' : $anterior);
        }

        self::assertStringContainsString('SensitiveParameterValue', $rastro);
        self::assertStringNotContainsString($this->senhaDoOperador, $rastro);
    }

    public function testFalhaInesperadaNaoLiberaAOperacaoNemExpoeDetalhes(): void
    {
        $limitador = $this->createMock(Throttler::class);
        $limitador->method('check')->willThrowException(new RuntimeException('detalhe-interno-do-cache'));
        $servico = new ConfirmacaoAdministrativaService($this->config, $limitador);

        $resultado = $servico->confirmar('desativar_conta', (int) $this->alvo->id, $this->senhaDoOperador);

        $this->assertRecusa($resultado, 'CONFIRMACAO_ERRO', 'inesperado');
        self::assertIsArray($resultado);
        self::assertStringNotContainsString('detalhe-interno', json_encode($resultado, JSON_THROW_ON_ERROR));
        $this->assertLogContains('error', 'exceção ' . RuntimeException::class);
        self::assertFalse(TestLogger::didLog('error', 'detalhe-interno-do-cache', false));
    }

    public function testTodaRecusaPossuiStatusHttpNoContrato(): void
    {
        $recusas = [
            $this->confirmar('inexistente', $this->senhaDoOperador),
            $this->servico->confirmar('desativar_conta', 0, $this->senhaDoOperador),
            $this->confirmar('reativar_conta', $this->senhaDoOperador),
            $this->confirmar('desativar_conta', ''),
            $this->confirmar('desativar_conta', 'incorreta'),
        ];
        $this->errarSenhaVezes(2);
        $recusas[] = $this->confirmar('desativar_conta', $this->senhaDoOperador);
        auth()->logout();
        $recusas[] = $this->confirmar('desativar_conta', $this->senhaDoOperador);

        $esperados = [
            'CONFIRMACAO_OPERACAO_INDISPONIVEL' => 501,
            'CONFIRMACAO_CONTEXTO_INVALIDO' => 422,
            'CONFIRMACAO_SEM_PERMISSAO' => 403,
            'CONFIRMACAO_INVALIDA' => 422,
            'CONFIRMACAO_LIMITE_EXCEDIDO' => 429,
            'AUTENTICACAO_NECESSARIA' => 401,
        ];

        foreach ($recusas as $recusa) {
            self::assertIsArray($recusa);
            self::assertSame($esperados[$recusa['codigo']], $this->config->statusDaRecusa($recusa['codigo']));
        }

        self::assertSame(500, $this->config->statusDaRecusa('CONFIRMACAO_ERRO'));
        self::assertNull($this->config->statusDaRecusa('CONTA_NAO_ENCONTRADA'));
    }

    /** @return User|array<string, mixed> */
    private function confirmar(string $acao, string $senha): User|array
    {
        return $this->servico->confirmar($acao, (int) $this->alvo->id, $senha);
    }

    private function errarSenhaVezes(int $vezes): void
    {
        for ($tentativa = 1; $tentativa <= $vezes; $tentativa++) {
            $this->assertRecusa(
                $this->confirmar('desativar_conta', 'incorreta-' . $tentativa),
                'CONFIRMACAO_INVALIDA',
                'permissao',
            );
        }
    }

    /** @param User|array<string, mixed> $resultado */
    private function assertRecusa(User|array $resultado, string $codigo, string $tipo): void
    {
        self::assertIsArray($resultado, 'A confirmação deveria ter sido recusada.');
        self::assertFalse($resultado['sucesso']);
        self::assertSame($codigo, $resultado['codigo']);
        self::assertSame($tipo, $resultado['tipo']);
        self::assertNotSame('', $resultado['mensagem']);
    }

    private function entrarComo(User $conta): void
    {
        auth()->logout();
        auth()->login($conta);
    }

    /** @param list<string> $permissoes */
    private function criarContaComSenha(
        string $username,
        string $senha,
        array $permissoes = [],
        string $grupo = 'user',
    ): User {
        $conta = $this->criarConta($username, $grupo, $permissoes);
        $conta->createEmailIdentity(['email' => $username . '@example.com', 'password' => $senha]);

        return $conta;
    }
}
