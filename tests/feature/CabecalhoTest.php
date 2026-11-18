<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CabecalhoService;
use CodeIgniter\Config\Services;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Cobre o cabeçalho compartilhado e a identificação da conta.
 *
 * @internal
 */
final class CabecalhoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null; // roda as migrations do app e do Shield

    private const PAGINA_INTERNA = '/';

    protected function setUp(): void
    {
        parent::setUp();

        helper('url');
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    /**
     * Cria um usuário do Shield com e-mail e username controlados.
     * O username é gravado direto no banco para permitir null, vazio
     * e caracteres especiais sem passar pela validação do cadastro.
     */
    private function criarUsuario(?string $username, string $email = 'docente@ifro.edu.br'): User
    {
        /** @var User $usuario */
        $usuario = fake(UserModel::class);
        $usuario->createEmailIdentity([
            'email'    => $email,
            'password' => 'senha-segura-123',
        ]);

        $this->db->table('users')
            ->where('id', $usuario->id)
            ->update(['username' => $username]);

        return model(UserModel::class)->findById($usuario->id);
    }

    /**
     * Substitui o service do cabeçalho para controlar a URL de Perfil,
     * independentemente de a rota existir na branch.
     */
    private function simularContaComPerfil(?string $urlPerfil): void
    {
        Services::injectMock('contaCabecalho', new class ($urlPerfil) extends CabecalhoService {
            public function __construct(private readonly ?string $urlPerfil)
            {
            }

            public function obter(): array
            {
                return [
                    'rotulo'    => 'Conta de Teste',
                    'email'     => 'teste@ifro.edu.br',
                    'urlPerfil' => $this->urlPerfil,
                ];
            }
        });
    }

    private function corpoDaPagina(User $usuario, string $uri = self::PAGINA_INTERNA): string
    {
        $resultado = $this->actingAs($usuario)->get($uri);

        $resultado->assertOK();

        return (string) $resultado->getBody();
    }

    private function rotuloExibido(string $corpo): ?string
    {
        $encontrou = preg_match(
            '/<span class="navbar-profile-name[^"]*"[^>]*>(.*?)<\/span>/s',
            $corpo,
            $partes,
        );

        return $encontrou === 1 ? trim($partes[1]) : null;
    }

    // Procedimentos 2 e 3: identificação vinda da sessão, com escape e fallback

    public function testExibeUsernameQuandoPreenchido(): void
    {
        $usuario = $this->criarUsuario('maria.silva');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertSame('maria.silva', $this->rotuloExibido($corpo));
    }

    public function testUsaEmailQuandoUsernameForNulo(): void
    {
         $usuario = $this->criarUsuario(null, 'sem.username@ifro.edu.br');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertSame('sem.username@ifro.edu.br', $this->rotuloExibido($corpo));
    }

    public function testUsaEmailQuandoUsernameForVazio(): void
    {
        $usuario = $this->criarUsuario('', 'username.vazio@ifro.edu.br');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertSame('username.vazio@ifro.edu.br', $this->rotuloExibido($corpo));
    }

    public function testEscapaCaracteresHtmlDoUsername(): void
    {
        $usuario = $this->criarUsuario('<script>alert(1)</script>');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $corpo);
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $this->rotuloExibido($corpo));
    }

    public function testIgnoraParametrosForjadosNaUrl(): void
    {
        $usuario = $this->criarUsuario('conta.real', 'real@ifro.edu.br');

        $corpo = $this->corpoDaPagina(
            $usuario,
            self::PAGINA_INTERNA . '?user=admin&email=outro@ifro.edu.br',
        );

        $this->assertSame('conta.real', $this->rotuloExibido($corpo));
        $this->assertStringNotContainsString('outro@ifro.edu.br', $corpo);
    }

    public function testServiceRetornaNuloSemUsuarioAutenticado(): void
    {
        $this->assertNull(service('contaCabecalho')->obter());
    }

    public function testVisitanteNaoVeCabecalhoDaConta(): void
    {
        $resultado = $this->get(self::PAGINA_INTERNA);

        $resultado->assertRedirect();
        $this->assertStringNotContainsString('profileDropdown', $resultado->getBody());
    }


    // Procedimento 4: ações Perfil e Sair sem links quebrados

    public function testExibeAcaoSairComoFormularioPostComCsrf(): void
    {
        $usuario = $this->criarUsuario('maria.silva');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertStringContainsString(
            '<form action="' . url_to('logout') . '" method="post">',
            $corpo,
        );
        $this->assertStringContainsString('name="' . csrf_token() . '"', $corpo);
        $this->assertStringContainsString('id="botao-sair"', $corpo);
    }

    public function testLogoutPorGetNaoExiste(): void
    {
        $usuario = $this->criarUsuario('maria.silva');

        $this->expectException(PageNotFoundException::class);

        $this->actingAs($usuario)->get('logout');
    }

    public function testExibeAcaoPerfilQuandoARotaExiste(): void
    {
        $this->simularContaComPerfil('/perfil-teste');
        $usuario = $this->criarUsuario('maria.silva');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertStringContainsString('href="/perfil-teste"', $corpo);
        $this->assertStringContainsString('<p class="preview-subject mb-1">Meu perfil</p>', $corpo);
    }

    public function testOcultaAcaoPerfilQuandoARotaNaoExiste(): void
    {
        $this->simularContaComPerfil(null);
        $usuario = $this->criarUsuario('maria.silva');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertStringNotContainsString('<p class="preview-subject mb-1">Meu perfil</p>', $corpo);
        $this->assertStringContainsString('id="botao-sair"', $corpo); // Sair continua disponível
    }

    public function testServiceNaoExpoeUrlDePerfilParaRotaInexistente(): void
    {
        $usuario = $this->criarUsuario('maria.silva');
        $this->actingAs($usuario);

        $conta = service('contaCabecalho')->obter();

        $this->assertNotNull($conta);
        // Null (rota ausente) ou uma URL real (rota presente): nunca um link fictício.
        $this->assertTrue(
            $conta['urlPerfil'] === null || str_contains($conta['urlPerfil'], 'perfil'),
        );
        $this->assertNotSame('#', $conta['urlPerfil']);
    }

    // ------------------------------------------------------------------
    // Procedimento 5: semântica, acessibilidade e nome do Hub
    // ------------------------------------------------------------------

    public function testGatilhoDoMenuDaContaEUmBotaoAcessivel(): void
    {
        $usuario = $this->criarUsuario('maria.silva');

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertMatchesRegularExpression('/<button[^>]*id="profileDropdown"[^>]*aria-expanded="false"/s', $corpo,);
        $this->assertStringContainsString('aria-label="Menu da conta de maria.silva"', $corpo);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*id="profileDropdown"/s', $corpo);
    }

    public function testNomeLongoRecebeClasseDeTruncamento(): void
    {
        $usuario = $this->criarUsuario(str_repeat('nome.muito.longo.', 6));

        $corpo = $this->corpoDaPagina($usuario);

        $this->assertStringContainsString('<span class="navbar-profile-name text-truncate', $corpo);
    }
}
