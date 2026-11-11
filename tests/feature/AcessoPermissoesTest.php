<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Traits\CriaContasTrait;

/**
 * Teste de URL direta (docs/autorizacao/rotas-protegidas.md, seção 8) e do efeito real
 * da concessão e da revogação de permissões diretas pela tela de acessos.
 */
final class AcessoPermissoesTest extends CIUnitTestCase
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

    private User $superadmin;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->superadmin = $this->criarConta('super.admin', 'superadmin');
    }

    // Cenário 1: sem login

    public function testVisitanteEhRedirecionadoParaOLogin(): void
    {
        $this->novaRequisicao();

        $this->get('admin/acessos')->assertRedirectTo(site_url('login'));
    }

    public function testVisitanteNaoAlteraPermissoes(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->novaRequisicao();
        $campos = [csrf_token() => csrf_hash(), 'permissao' => 'contas.consultar'];

        $resposta = $this->withSession($_SESSION)->post($this->rotaPermissoes($alvo) . '/conceder', $campos);

        $resposta->assertRedirectTo(site_url('login'));
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
    }

    // Cenários 2 e 4: logado sem a permissão (o grupo admin tem contas.*, mas não acessos.administrar)

    public function testQuemNaoPodeAdministrarAcessosNaoAbreAsTelas(): void
    {
        $admin = $this->criarConta('conta.admin', 'admin');
        $alvo  = $this->criarConta('conta.alvo', 'user');

        foreach (['admin/acessos', $this->rotaPermissoes($alvo)] as $rota) {
            $resposta = $this->abrir($admin, $rota);

            $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
            $resposta->assertSessionHas('error');
            $this->assertStringNotContainsString('conta.alvo', $resposta->getBody());
        }
    }

    public function testQuemNaoPodeAdministrarAcessosNaoGravaNada(): void
    {
        $admin = $this->criarConta('conta.admin', 'admin');
        $alvo  = $this->criarConta('conta.alvo', 'user', ['contas.criar']);

        $concessao = $this->enviar(
            $admin,
            $this->rotaPermissoes($alvo) . '/conceder',
            ['permissao' => 'contas.consultar'],
        );
        $revogacao = $this->enviar(
            $admin,
            $this->rotaPermissoes($alvo) . '/revogar',
            ['permissao' => 'contas.criar'],
        );

        $concessao->assertRedirectTo(rtrim(site_url('/'), '/'));
        $revogacao->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $alvo->id, 'permission' => 'contas.criar']);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    // Cenários 3 e 5: logado com a permissão

    public function testSuperadminVeAListaDeContas(): void
    {
        $this->criarConta('conta.alvo', 'admin');

        $resposta = $this->abrir($this->superadmin, 'admin/acessos');

        $resposta->assertOK();
        $resposta->assertSee('Acessos das contas');
        $resposta->assertSee('conta.alvo');
        $resposta->assertSee('Administrador de contas');
    }

    public function testPaginaMostraPermissoesHerdadasDiretasEEfetivas(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'admin', ['contas.consultar', 'acessos.administrar']);

        $resposta = $this->abrir($this->superadmin, $this->rotaPermissoes($alvo));
        $corpo    = $this->textoDaResposta($resposta);

        $resposta->assertOK();
        $resposta->assertSee('Permissões da conta');
        $resposta->assertSee('conta.alvo');
        $resposta->assertSee('Administrador de contas');
        $resposta->assertSee('contas.criar');
        // contas.consultar é direta e também herdada; acessos.administrar é só direta.
        $this->assertSame(1, substr_count($corpo, 'também herdada'));
        // As duas diretas oferecem "Revogar"; contas.criar, que a conta só herda, oferece "Conceder".
        $this->assertSame(2, substr_count($corpo, 'data-bs-target="#modal-revogar-permissao"'));
        $this->assertStringContainsString('name="permissao" value="contas.criar"', $corpo);
        $this->assertStringNotContainsString('name="permissao" value="contas.consultar"', $corpo);
    }

    public function testConcedeEAuditaPelaTelaSemMexerEmGruposNemNaMatriz(): void
    {
        $alvo        = $this->criarConta('conta.alvo', 'user');
        $matrizAntes = setting('AuthGroups.matrix');

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo) . '/conceder',
            ['permissao' => 'contas.consultar'],
        );

        $resposta->assertRedirectTo(url_to('acessos.permissoes', $alvo->id));
        $resposta->assertSessionHas('success');
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $alvo->id, 'permission' => 'contas.consultar']);
        $this->seeInDatabase('auditoria_permissoes', [
            'usuario_id' => $alvo->id,
            'permissao'  => 'contas.consultar',
            'acao'       => 'concedida',
            'created_by' => $this->superadmin->id,
        ]);
        $this->seeNumRecords(1, 'auth_groups_users', ['user_id' => $alvo->id, 'group' => 'user']);
        $this->seeNumRecords(1, 'auth_groups_users', ['user_id' => $alvo->id]);
        $this->assertSame($matrizAntes, setting('AuthGroups.matrix'));
    }

    public function testRevogaEAuditaPelaTela(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo) . '/revogar',
            ['permissao' => 'contas.consultar'],
        );

        $resposta->assertRedirectTo(url_to('acessos.permissoes', $alvo->id));
        $resposta->assertSessionHas('success');
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeInDatabase('auditoria_permissoes', [
            'usuario_id' => $alvo->id,
            'permissao'  => 'contas.consultar',
            'acao'       => 'revogada',
            'created_by' => $this->superadmin->id,
        ]);
    }

    // Efeito real: a conta passa a acessar a rota depois da concessão e perde o acesso depois da revogação.

    public function testContaGanhaEPerdeAcessoARotaConformeAsAlteracoes(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $rota = $this->rotaPermissoes($alvo);

        $this->abrir($alvo, 'admin/acessos')->assertRedirectTo(rtrim(site_url('/'), '/'));

        $this->enviar($this->superadmin, $rota . '/conceder', ['permissao' => 'acessos.administrar'])
            ->assertSessionHas('success');
        $this->abrir($alvo, 'admin/acessos')->assertOK();

        $this->enviar($this->superadmin, $rota . '/revogar', ['permissao' => 'acessos.administrar'])
            ->assertSessionHas('success');
        $this->abrir($alvo, 'admin/acessos')->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testRevogarNaoTiraOAcessoQueVemDoGrupo(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'superadmin', ['acessos.administrar']);

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo) . '/revogar',
            ['permissao' => 'acessos.administrar'],
        );

        $resposta->assertSessionHas('warning');
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeInDatabase('auth_groups_users', ['user_id' => $alvo->id, 'group' => 'superadmin']);
        $this->abrir($alvo, 'admin/acessos')->assertOK();

        $pagina = $this->abrir($this->superadmin, $this->rotaPermissoes($alvo));
        $pagina->assertSee('Administrador geral');
        $this->assertSame(0, substr_count($pagina->getBody(), 'data-bs-target="#modal-revogar-permissao"'));
    }

    // Validação de entrada

    public function testRecusaPermissaoForaDoCatalogo(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo) . '/conceder',
            ['permissao' => 'contas.excluir'],
        );

        $resposta->assertSessionHas('error');
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testRecusaPermissaoEmBranco(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');

        $resposta = $this->enviar($this->superadmin, $this->rotaPermissoes($alvo) . '/conceder', ['permissao' => '']);

        $resposta->assertSessionHas('error', 'Informe a permissão que deseja alterar.');
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testRecusaPostSemTokenCsrf(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->novaRequisicao();
        $this->expectException(SecurityException::class);

        $this->actingAs($this->superadmin)
            ->post($this->rotaPermissoes($alvo) . '/conceder', ['permissao' => 'contas.consultar']);
    }

    // Cenários 6, 7 e 8: URLs e verbos não declarados, conta que não existe

    public function testContaInexistenteDevolve404(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->abrir($this->superadmin, 'admin/acessos/999999/permissoes');
    }

    public function testAcaoSobreContaInexistenteNaoGravaNada(): void
    {
        $resposta = $this->enviar(
            $this->superadmin,
            'admin/acessos/999999/permissoes/conceder',
            ['permissao' => 'contas.consultar'],
        );

        $resposta->assertSessionHas('error');
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testAlterarPorGetDevolve404(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->expectException(PageNotFoundException::class);

        $this->abrir($this->superadmin, $this->rotaPermissoes($alvo) . '/conceder');
    }

    public function testUrlSemRotaDeclaradaDevolve404(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->expectException(PageNotFoundException::class);

        $this->abrir($this->superadmin, 'admin/acessos/' . $alvo->id);
    }

    #[DataProvider('identificadoresInvalidos')]
    public function testIdentificadorInvalidoNaUrlDevolve404(string $identificador): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->abrir($this->superadmin, "admin/acessos/{$identificador}/permissoes");
    }

    /**
     * @return array<string, list<string>>
     */
    public static function identificadoresInvalidos(): array
    {
        return [
            'zero'                       => ['0'],
            'zero à esquerda'            => ['007'],
            'maior que o limite da rota' => ['99999999999999999999'],
            'texto'                      => ['abc'],
        ];
    }

    // Saída segura, menu e paginação

    public function testEscapaOsDadosDaContaNasTelas(): void
    {
        $alvo = $this->criarConta('<script>alert(1)</script>', 'user', ['contas.consultar']);

        foreach (['admin/acessos', $this->rotaPermissoes($alvo)] as $rota) {
            $corpo = $this->abrir($this->superadmin, $rota)->getBody();

            $this->assertStringNotContainsString('<script>alert(1)</script>', $corpo);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $corpo);
        }
    }

    public function testMenuOferecePaginaDeAcessosSoParaQuemPodeAdministrar(): void
    {
        $admin = $this->criarConta('conta.admin', 'admin');
        $link  = 'href="' . site_url('admin/acessos') . '"';

        $this->assertStringContainsString($link, $this->abrir($this->superadmin, '/')->getBody());
        $this->assertStringNotContainsString($link, $this->abrir($admin, '/')->getBody());
    }

    public function testListaDividePorPaginas(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->criarConta(sprintf('conta.%02d', $i), 'user');
        }

        $primeira = $this->abrir($this->superadmin, 'admin/acessos');
        $primeira->assertSee('Página 1 de 2');
        $primeira->assertSee('Próxima');

        $segunda = $this->abrir($this->superadmin, 'admin/acessos', ['page' => '2']);
        $segunda->assertSee('Página 2 de 2');
        $segunda->assertSee('Anterior');
    }

    /**
     * Começa uma requisição nova, como no navegador: sessão e serviços zerados.
     */
    private function novaRequisicao(): void
    {
        $_SESSION = [];
        $this->resetServices();
    }

    /**
     * @param array<string, mixed>|null $parametros
     */
    private function abrir(User $conta, string $rota, ?array $parametros = null): TestResponse
    {
        $this->novaRequisicao();

        return $this->actingAs($this->recarregar($conta))->get($rota, $parametros);
    }

    /**
     * Envia um formulário com o token CSRF, gerado antes do actingAs() (docs/autorizacao/rotas-protegidas.md, 8.2).
     *
     * @param array<string, string> $campos
     */
    private function enviar(User $conta, string $rota, array $campos = []): TestResponse
    {
        $this->novaRequisicao();

        $campos[csrf_token()] = csrf_hash();

        return $this->actingAs($this->recarregar($conta))->withSession($_SESSION)->post($rota, $campos);
    }

    private function rotaPermissoes(User $conta): string
    {
        return 'admin/acessos/' . $conta->id . '/permissoes';
    }

    /**
     * Corpo da resposta com as entidades HTML decodificadas (os acentos saem como `&eacute;`), para
     * comparar o texto que a pessoa lê. Para checar escape de HTML, use o corpo cru (`getBody()`).
     */
    private function textoDaResposta(TestResponse $resposta): string
    {
        return html_entity_decode($resposta->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
