<?php

namespace Tests\Unit;

use CodeIgniter\Config\DotEnv;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Database;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\DataProvider;

final class AmbienteDockerTest extends CIUnitTestCase
{
    private const CHAVES = [
        'app.baseURL',
        'database.default.hostname',
        'database.default.database',
        'database.default.username',
        'database.default.password',
        'database.default.DBDriver',
        'database.default.port',
    ];

    private array $ambienteOriginal = [];
    private array $variaveisOriginais = [];
    private array $servidorOriginal = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->variaveisOriginais = $_ENV;
        $this->servidorOriginal = $_SERVER;

        foreach (self::CHAVES as $chaveComPontos) {
            foreach ([$chaveComPontos, str_replace('.', '_', $chaveComPontos)] as $chave) {
                $this->ambienteOriginal[$chave] = getenv($chave);
                putenv($chave);
                unset($_ENV[$chave]);
                service('superglobals')->unsetServer($chave);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->ambienteOriginal as $chave => $valor) {
            putenv($valor === false ? $chave : $chave . '=' . $valor);
        }

        $_ENV = $this->variaveisOriginais;
        service('superglobals')->setServerArray($this->servidorOriginal);

        parent::tearDown();
    }

    public static function formatosDeChave(): iterable
    {
        yield 'pontos' => [false];
        yield 'underscores' => [true];
    }

    #[DataProvider('formatosDeChave')]
    public function testPreservaExecucaoLocalNasDuasNotacoes(bool $usarUnderscores): void
    {
        $configuracaoLocal = $this->criarConfiguracaoLocal();
        $this->carregarArquivo($configuracaoLocal, $usarUnderscores);

        $this->conferirConfiguracao($configuracaoLocal);
    }

    #[DataProvider('formatosDeChave')]
    public function testPriorizaConexaoDoComposeNasDuasNotacoes(bool $usarUnderscores): void
    {
        $configuracaoLocal = $this->criarConfiguracaoLocal();
        $configuracaoLocal['database.default.DBDriver'] = 'SQLite3';
        $configuracaoDocker = $this->criarConfiguracaoDocker();

        $this->definirAmbiente($configuracaoDocker, true);
        $this->carregarArquivo($configuracaoLocal, $usarUnderscores);
        $this->prepararAmbienteDocker();

        $this->conferirConfiguracao($configuracaoDocker);
    }

    #[DataProvider('formatosDeChave')]
    public function testPreservaUrlDoArquivoSemSobrescritaExterna(bool $usarUnderscores): void
    {
        $configuracaoLocal = $this->criarConfiguracaoLocal();
        $configuracaoDocker = $this->criarConfiguracaoDocker();
        unset($configuracaoDocker['app.baseURL']);

        // Uma variável externa vazia não deve apagar a URL válida do arquivo.
        putenv('app_baseURL=');
        $_ENV['app_baseURL'] = '';
        service('superglobals')->setServer('app_baseURL', '');

        $this->definirAmbiente($configuracaoDocker, true);
        $this->carregarArquivo($configuracaoLocal, $usarUnderscores);
        $this->prepararAmbienteDocker();

        $this->conferirConfiguracao(['app.baseURL' => $configuracaoLocal['app.baseURL']] + $configuracaoDocker);
    }

    public function testPreservaArquivoComPontosSemVariaveisDoCompose(): void
    {
        $configuracaoLocal = $this->criarConfiguracaoLocal();
        $this->carregarArquivo($configuracaoLocal, false);
        $this->prepararAmbienteDocker();

        $this->conferirConfiguracao($configuracaoLocal);
        $this->assertFalse(getenv('app_baseURL'));
    }

    public function testUsaUrlPadraoQuandoNaoHaConfiguracaoDeUrl(): void
    {
        $configuracaoLocal = $this->criarConfiguracaoLocal();
        unset($configuracaoLocal['app.baseURL']);

        $this->carregarArquivo($configuracaoLocal, false);
        $this->prepararAmbienteDocker();

        $this->assertSame('http://localhost:8080/', (new App())->baseURL);
    }

    public function testPriorizaUnderscoresQuandoAsDuasFormasEstaoNoAmbiente(): void
    {
        $configuracaoDocker = $this->criarConfiguracaoDocker();
        $this->definirAmbiente($this->criarConfiguracaoLocal(), false);
        $this->definirAmbiente($configuracaoDocker, true);
        $this->prepararAmbienteDocker();

        $this->conferirConfiguracao($configuracaoDocker);
    }

    public function testNaoTrataSenhaZeroComoVariavelAusente(): void
    {
        $configuracaoDocker = $this->criarConfiguracaoDocker();
        $configuracaoDocker['database.default.password'] = '0';

        $this->definirAmbiente($configuracaoDocker, true);
        $this->carregarArquivo($this->criarConfiguracaoLocal(), false);
        $this->prepararAmbienteDocker();

        $this->conferirConfiguracao($configuracaoDocker);
    }

    private function criarConfiguracaoLocal(): array
    {
        return [
            'app.baseURL' => 'http://local.example.test:8080/',
            'database.default.hostname' => '127.0.0.1',
            'database.default.database' => 'planifica_teste_local',
            'database.default.username' => 'usuario_teste_local',
            'database.default.password' => bin2hex(random_bytes(24)),
            'database.default.DBDriver' => 'MySQLi',
            'database.default.port' => '3307',
        ];
    }

    private function criarConfiguracaoDocker(): array
    {
        return [
            'app.baseURL' => 'http://docker.example.test:8080/',
            'database.default.hostname' => 'banco',
            'database.default.database' => 'planifica_teste_docker',
            'database.default.username' => 'usuario_teste_docker',
            'database.default.password' => bin2hex(random_bytes(24)),
            'database.default.DBDriver' => 'MySQLi',
            'database.default.port' => '3306',
        ];
    }

    private function carregarArquivo(array $configuracao, bool $usarUnderscores): void
    {
        $linhas = [];

        foreach ($configuracao as $chave => $valor) {
            $nome = $usarUnderscores ? str_replace('.', '_', $chave) : $chave;
            $linhas[] = $nome . " = '" . $valor . "'";
        }

        $diretorio = vfsStream::setup('ambiente', null, ['.env' => implode(PHP_EOL, $linhas)]);
        $this->assertTrue((new DotEnv($diretorio->url()))->load());
    }

    private function definirAmbiente(array $configuracao, bool $usarUnderscores): void
    {
        foreach ($configuracao as $chave => $valor) {
            $nome = $usarUnderscores ? str_replace('.', '_', $chave) : $chave;
            putenv($nome . '=' . $valor);

            // O PHP disponibiliza as variáveis externas com nomes normalizados.
            $nomeNormalizado = str_replace('.', '_', $nome);
            $_ENV[$nomeNormalizado] = $valor;
            service('superglobals')->setServer($nomeNormalizado, $valor);
        }
    }

    private function prepararAmbienteDocker(): void
    {
        require ROOTPATH . 'docker/php/preparar_ambiente.php';
    }

    private function conferirConfiguracao(array $esperada): void
    {
        $this->assertSame($esperada['app.baseURL'], (new App())->baseURL);
        $banco = (new Database())->default;

        foreach ($esperada as $chave => $valor) {
            if (str_starts_with($chave, 'database.default.')) {
                $campo = substr($chave, strlen('database.default.'));
                $this->assertSame($campo === 'port' ? (int) $valor : $valor, $banco[$campo]);
            }
        }
    }
}
