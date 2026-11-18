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
        $campos = [csrf_token() => csrf_hash(), 'permissoes' => ['contas.consultar']];

        $resposta = $this->withSession($_SESSION)->post($this->rotaPermissoes($alvo), $campos);

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

        // Marcar uma caixa a mais e desmarcar a que a conta tem: as duas mudanças seriam recusadas.
        $concessao = $this->enviar(
            $admin,
            $this->rotaPermissoes($alvo),
            ['permissoes' => ['contas.criar', 'contas.consultar']],
        );
        $revogacao = $this->enviar($admin, $this->rotaPermissoes($alvo));

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

    public function testPaginaMostraAsCaixasComHerdadaDiretaEEfetiva(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'admin', ['contas.consultar', 'acessos.administrar']);

        $resposta = $this->abrir($this->superadmin, $this->rotaPermissoes($alvo));
        $corpo    = $this->textoDaResposta($resposta);

        $resposta->assertOK();
        $resposta->assertSee('Permissões da conta');
        $resposta->assertSee('conta.alvo');
        $resposta->assertSee('Administrador de contas');
        $resposta->assertSee('Como ler esta tela');
        // A caixa marcada é o vínculo direto: contas.consultar (também herdada) e acessos.administrar.
        // contas.criar a conta só herda, então a caixa fica desmarcada.
        $this->assertTrue($this->caixaMarcada($corpo, 'contas.consultar'));
        $this->assertTrue($this->caixaMarcada($corpo, 'acessos.administrar'));
        $this->assertFalse($this->caixaMarcada($corpo, 'contas.criar'));
        $this->assertSame(2, substr_count($corpo, 'Herdada de Administrador de contas'));
        // As três valem de fato: duas pelo grupo (uma delas também direta) e uma só direta.
        $this->assertSame(3, substr_count($corpo, 'Acesso efetivo</span>'));
        // O ícone de acesso efetivo aparece nas mesmas três e em nenhuma outra.
        $this->assertSame(3, substr_count($corpo, 'title="Acesso efetivo"'));
    }

    public function testPaginaTemMigalhaETituloComOIdentificadorDaConta(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');

        $corpo = $this->textoDaResposta($this->abrir($this->superadmin, $this->rotaPermissoes($alvo)));

        $this->assertStringContainsString('Conta: conta.alvo</h1>', $corpo);
        $this->assertMatchesRegularExpression(
            '#<li class="breadcrumb-item"><a href="[^"]*admin/acessos"[^>]*>Acessos</a></li>\s*'
            . '<li class="breadcrumb-item active" aria-current="page">conta\.alvo</li>#',
            $corpo,
        );
    }

    public function testIdentificadorFicaNoTooltipEACaixaApontaParaSuaDescricao(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'admin', ['contas.consultar']);

        $corpo = $this->textoDaResposta($this->abrir($this->superadmin, $this->rotaPermissoes($alvo)));

        // O identificador não fica solto na lista: vai no tooltip do ícone de informação, um por permissão.
        $this->assertSame(1, substr_count($corpo, 'title="Identificador: contas.consultar"'));
        $this->assertSame(3, substr_count($corpo, 'data-bs-toggle="tooltip"'));
        // Quem usa leitor de tela ouve a descrição (identificador, herança e acesso efetivo) junto da caixa.
        $this->assertStringContainsString(
            'aria-describedby="detalhe-contas.consultar"',
            $this->tagDaCaixa($corpo, 'contas.consultar'),
        );
        $this->assertSame(1, substr_count($corpo, 'id="detalhe-contas.consultar"'));
        $this->assertStringContainsString('Identificador contas.consultar.', $corpo);
    }

    public function testPaginaAgrupaPorCategoriaEMarcaAPermissaoCritica(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');

        $corpo = $this->textoDaResposta($this->abrir($this->superadmin, $this->rotaPermissoes($alvo)));

        $this->assertSame(2, substr_count($corpo, 'class="categoria-permissoes"'));
        $this->assertLessThan(
            strpos($corpo, '>Contas</legend>'),
            strpos($corpo, '>Acessos</legend>'),
            'As categorias seguem a ordem alfabética do catálogo.',
        );
        // Só acessos.administrar é crítica: um aviso para quem lê e um atributo para o "marcar todas" pular.
        $this->assertSame(1, substr_count($corpo, 'Permissão crítica:'));
        $this->assertSame(1, substr_count($corpo, 'data-critica'));
        $this->assertSame(2, substr_count($corpo, 'id="marcar-todas"') + substr_count($corpo, 'id="desmarcar-todas"'));
        // Os atalhos agem só nas caixas do catálogo, que levam a classe `permissao-catalogo`: as três do catálogo.
        $this->assertSame(3, substr_count($corpo, 'permissao-catalogo'));
        // O script vai com a data do arquivo na URL, para o navegador não reaproveitar uma versão antiga em cache.
        $this->assertMatchesRegularExpression('#assets/js/acesso-permissoes\.js\?v=\d+#', $corpo);
    }

    public function testConcedeEAuditaPelaTelaSemMexerEmGruposNemNaMatriz(): void
    {
        $alvo        = $this->criarConta('conta.alvo', 'user');
        $matrizAntes = setting('AuthGroups.matrix');

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo),
            ['permissoes' => ['contas.consultar']],
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

        // Todas as caixas desmarcadas: o navegador não envia o campo `permissoes`.
        $resposta = $this->enviar($this->superadmin, $this->rotaPermissoes($alvo));

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

    public function testSalvaVariasMudancasDeUmaVezEAuditaCadaUma(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo),
            ['permissoes' => ['contas.criar', 'acessos.administrar']],
        );

        $resposta->assertSessionHas('success', 'Permissões diretas salvas: 2 concedidas e 1 revogada.');
        $this->seeNumRecords(2, 'auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $alvo->id, 'permission' => 'contas.criar']);
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $alvo->id, 'permission' => 'acessos.administrar']);
        $this->dontSeeInDatabase('auth_permissions_users', [
            'user_id'    => $alvo->id,
            'permission' => 'contas.consultar',
        ]);
        $this->seeNumRecords(3, 'auditoria_permissoes', [
            'usuario_id' => $alvo->id,
            'created_by' => $this->superadmin->id,
        ]);
    }

    public function testSalvarSemMudancaNaoGravaNada(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo),
            ['permissoes' => ['contas.consultar']],
        );

        $resposta->assertSessionHas('success', 'Nenhuma alteração: as permissões diretas da conta já estavam assim.');
        $this->seeNumRecords(1, 'auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    public function testAutorDaAuditoriaEhSempreQuemEstaLogado(): void
    {
        $outro = $this->criarConta('outra.conta', 'superadmin');
        $alvo  = $this->criarConta('conta.alvo', 'user');

        // Campos extras tentando apontar outro autor: o formulário não tem esse dado, e o Controller o ignora.
        $this->enviar($this->superadmin, $this->rotaPermissoes($alvo), [
            'permissoes'  => ['contas.consultar'],
            'operador_id' => (string) $outro->id,
            'created_by'  => (string) $outro->id,
        ])->assertSessionHas('success');

        $this->seeInDatabase('auditoria_permissoes', [
            'usuario_id' => $alvo->id,
            'created_by' => $this->superadmin->id,
        ]);
        $this->dontSeeInDatabase('auditoria_permissoes', ['created_by' => $outro->id]);
    }

    public function testPaginaMostraVinculoForaDoCatalogoEOSalvarRevogaQuandoDesmarcado(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->hasInDatabase('auth_permissions_users', [
            'user_id'    => $alvo->id,
            'permission' => 'relatorios.antigo',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $corpo = $this->textoDaResposta($this->abrir($this->superadmin, $this->rotaPermissoes($alvo)));

        $this->assertStringContainsString('Fora do catálogo', $corpo);
        $this->assertTrue($this->caixaMarcada($corpo, 'relatorios.antigo'));
        // "marcar todas" e "desmarcar" não mexem nela: só as caixas do catálogo levam a classe dos atalhos.
        $this->assertStringNotContainsString('permissao-catalogo', $this->tagDaCaixa($corpo, 'relatorios.antigo'));
        $this->assertStringContainsString('permissao-catalogo', $this->tagDaCaixa($corpo, 'contas.consultar'));

        // Salvar com a caixa marcada mantém o vínculo; sem ela, revoga.
        $this->enviar($this->superadmin, $this->rotaPermissoes($alvo), ['permissoes' => ['relatorios.antigo']])
            ->assertSessionHas('success', 'Nenhuma alteração: as permissões diretas da conta já estavam assim.');
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $alvo->id, 'permission' => 'relatorios.antigo']);

        $this->enviar($this->superadmin, $this->rotaPermissoes($alvo))->assertSessionHas('success');
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
    }

    // Efeito real: a conta passa a acessar a rota depois da concessão e perde o acesso depois da revogação.

    public function testContaGanhaEPerdeAcessoARotaConformeAsAlteracoes(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $rota = $this->rotaPermissoes($alvo);

        $this->abrir($alvo, 'admin/acessos')->assertRedirectTo(rtrim(site_url('/'), '/'));

        $this->enviar($this->superadmin, $rota, ['permissoes' => ['acessos.administrar']])
            ->assertSessionHas('success');
        $this->abrir($alvo, 'admin/acessos')->assertOK();

        $this->enviar($this->superadmin, $rota)->assertSessionHas('success');
        $this->abrir($alvo, 'admin/acessos')->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testDesmarcarNaoTiraOAcessoQueVemDoGrupo(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'superadmin', ['acessos.administrar']);

        $resposta = $this->enviar($this->superadmin, $this->rotaPermissoes($alvo));

        $resposta->assertSessionHas('warning');
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeInDatabase('auth_groups_users', ['user_id' => $alvo->id, 'group' => 'superadmin']);
        $this->abrir($alvo, 'admin/acessos')->assertOK();

        $pagina = $this->abrir($this->superadmin, $this->rotaPermissoes($alvo));
        $pagina->assertSee('Administrador geral');
        $this->assertFalse($this->caixaMarcada($this->textoDaResposta($pagina), 'acessos.administrar'));
    }

    // Validação de entrada

    public function testRecusaPermissaoForaDoCatalogo(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');

        // Uma permissão válida junto com uma que não existe: nada é gravado (tudo ou nada).
        $resposta = $this->enviar(
            $this->superadmin,
            $this->rotaPermissoes($alvo),
            ['permissoes' => ['contas.consultar', 'contas.excluir']],
        );

        $resposta->assertSessionHas('error', 'Há permissão informada que não está cadastrada.');
        $this->dontSeeInDatabase('auth_permissions_users', ['user_id' => $alvo->id]);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    /**
     * @param array<string, mixed> $campos
     */
    #[DataProvider('selecoesInvalidas')]
    public function testRecusaSelecaoComFormatoInvalido(array $campos): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user', ['contas.consultar']);

        $resposta = $this->enviar($this->superadmin, $this->rotaPermissoes($alvo), $campos);

        $resposta->assertSessionHas('error', 'A seleção de permissões é inválida.');
        $this->seeInDatabase('auth_permissions_users', ['user_id' => $alvo->id, 'permission' => 'contas.consultar']);
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function selecoesInvalidas(): array
    {
        return [
            'texto em vez de lista'      => [['permissoes' => 'contas.consultar']],
            'item em branco'             => [['permissoes' => ['']]],
            'item que também é lista'    => [['permissoes' => [['contas.consultar']]]],
            'item maior que o permitido' => [['permissoes' => [str_repeat('a', 101)]]],
        ];
    }

    public function testRecusaPostSemTokenCsrf(): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->novaRequisicao();
        $this->expectException(SecurityException::class);

        $this->actingAs($this->superadmin)
            ->post($this->rotaPermissoes($alvo), ['permissoes' => ['contas.consultar']]);
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
            'admin/acessos/999999/permissoes',
            ['permissoes' => ['contas.consultar']],
        );

        $resposta->assertSessionHas('error');
        $this->seeNumRecords(0, 'auditoria_permissoes', []);
    }

    #[DataProvider('rotasRemovidas')]
    public function testRotasAntigasDeConcessaoEDeRevogacaoDevolvem404(string $acao): void
    {
        $alvo = $this->criarConta('conta.alvo', 'user');
        $this->expectException(PageNotFoundException::class);

        $rota = $this->rotaPermissoes($alvo) . '/' . $acao;

        $this->enviar($this->superadmin, $rota, ['permissao' => 'contas.consultar']);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rotasRemovidas(): array
    {
        return [
            'conceder' => ['conceder'],
            'revogar'  => ['revogar'],
        ];
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
     * @param array<string, mixed> $campos
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

    /**
     * Diz se a caixa da permissão veio marcada na página (`$corpo` vem de `textoDaResposta()`).
     */
    private function caixaMarcada(string $corpo, string $permissao): bool
    {
        return preg_match('/\schecked(\s|>)/', $this->tagDaCaixa($corpo, $permissao)) === 1;
    }

    /**
     * A tag `<input>` da caixa da permissão, para conferir seus atributos (`$corpo` vem de `textoDaResposta()`).
     */
    private function tagDaCaixa(string $corpo, string $permissao): string
    {
        $achou = preg_match('/<input[^>]*value="' . preg_quote($permissao, '/') . '"[^>]*>/', $corpo, $caixa);

        $this->assertSame(1, $achou, "A caixa de {$permissao} não está na página.");

        return $caixa[0];
    }
}
