<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EstadoContaModel;
use App\Services\EstadoContaService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Traits\CriaContasTrait;

final class EstadoContaRotasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use CriaContasTrait;

    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private string $senha;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        // O limite de tentativas usa o cache: cada teste começa com o contador zerado e sem tocar o cache real.
        Services::injectMock('cache', new MockCache());
        $this->senha = 'Inicial@9' . bin2hex(random_bytes(16));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function permissoesDaOutraAcao(): array
    {
        return [
            'desativação' => ['desativar', 'contas.reativar', true],
            'reativação' => ['reativar', 'contas.desativar', false],
        ];
    }

    /** @return array<string, array{string, string, bool, string}> */
    public static function permissoesEspecificas(): array
    {
        return [
            'desativação' => ['desativar', 'contas.desativar', true, 'CONTA_DESATIVADA'],
            'reativação' => ['reativar', 'contas.reativar', false, 'CONTA_REATIVADA'],
        ];
    }

    #[DataProvider('permissoesDaOutraAcao')]
    public function testRecusaPermissaoDaOutraAcaoAntesDoService(
        string $acao,
        string $outraPermissao,
        bool $estadoInicial,
    ): void {
        $operador = $this->criarOperador($outraPermissao);
        $conta = $this->criarAlvo($estadoInicial);
        $servico = $this->createMock(EstadoContaService::class);
        $servico->expects(self::never())->method('desativar');
        $servico->expects(self::never())->method('reativar');
        Services::injectMock('estadoContaService', $servico);

        $resposta = $this->enviarAcao($operador, $conta, $acao);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error', lang('Auth.notEnoughPrivilege'));
        self::assertSame($estadoInicial, (bool) $this->recarregar($conta)->active);
    }

    #[DataProvider('permissoesEspecificas')]
    public function testAceitaPermissaoEspecificaDaAcao(
        string $acao,
        string $permissao,
        bool $estadoInicial,
        string $codigoSucesso,
    ): void {
        $operador = $this->criarOperador($permissao);
        $conta = $this->criarAlvo($estadoInicial);

        $resposta = $this->enviarAcao($operador, $conta, $acao);

        $resposta->assertOK();
        $resposta->assertJSONFragment(['sucesso' => true, 'codigo' => $codigoSucesso]);
        self::assertSame(! $estadoInicial, (bool) $this->recarregar($conta)->active);
    }

    public function testOperacaoContinuaExigindoSessao(): void
    {
        $conta = $this->criarAlvo(true);

        $this->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/desativar', [
            'senha_admin' => $this->senha,
            csrf_token() => csrf_hash(),
        ])->assertRedirectTo('/login');

        self::assertTrue((bool) $this->recarregar($conta)->active);
    }

    private function criarOperador(string $permissao): User
    {
        $operador = $this->criarConta('operador.estado.rota', 'user', [$permissao]);
        $operador->createEmailIdentity(['email' => 'operador.estado.rota@example.com', 'password' => $this->senha]);

        return $operador;
    }

    private function criarAlvo(bool $ativo): User
    {
        $conta = $this->criarConta('alvo.estado.rota', 'user');

        if (! $ativo) {
            self::assertTrue(model(EstadoContaModel::class)->update($conta->id, ['active' => false]));
        }

        return $conta;
    }

    private function enviarAcao(User $operador, User $conta, string $acao): TestResponse
    {
        auth()->login($operador);

        return $this->withSession($_SESSION)->post('admin/contas/' . $conta->id . '/' . $acao, [
            'senha_admin' => $this->senha,
            csrf_token() => csrf_hash(),
        ]);
    }
}
