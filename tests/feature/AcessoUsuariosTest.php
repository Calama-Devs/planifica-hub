<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Traits\CriaContasTrait;

/**
 * Cobertura da tela de Gerenciamento de Usuários (PLAN-44): autorização,
 * botão condicional de convite, listagem/busca/paginação server-side e modais.
 */
final class AcessoUsuariosTest extends CIUnitTestCase
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

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testVisitanteEhRedirecionadoParaOLogin(): void
    {
        $this->get('admin/acessos')->assertRedirectTo(site_url('login'));
        $this->get('admin/acessos/listar')->assertRedirectTo(site_url('login'));
    }

    public function testQuemNaoPodeAdministrarAcessosNaoAcessaATelaNemAListagem(): void
    {
        $comum = $this->criarConta('comum', 'user');
        $admin = $this->criarConta('admin.contas', 'admin');

        foreach ([$comum, $admin] as $conta) {
            $this->actingAs($conta)->get('admin/acessos')->assertRedirect();
            $this->actingAs($conta)->get('admin/acessos/listar')->assertRedirect();
        }
    }

    public function testSuperadminVeATelaComBotaoAdicionarUsuarioEModais(): void
    {
        $superadmin = $this->criarConta('super.admin', 'superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/acessos');

        $resposta->assertOK();
        $resposta->assertSee('Gerenciamento de Usuários');
        $resposta->assertSee('Adicionar Usuário');
        $resposta->assertSee('Usuários Inativos');

        $html = (string) $resposta->getBody();
        $this->assertStringContainsString('id="modal-adicionar-usuario"', $html);
        $this->assertStringContainsString('id="modal-atualizar-usuario"', $html);
        $this->assertStringContainsString('id="modal-resetar-senha"', $html);
        $this->assertStringContainsString('id="modal-desativar-usuario"', $html);
        $this->assertStringContainsString('id="modal-reativar-usuario"', $html);
        $this->assertStringContainsString('id="modal-alterar-grupos"', $html);
        $this->assertStringContainsString('id="modal-confirmar-senha-admin"', $html);
        $this->assertStringContainsString('data-url="' . url_to('acessos.listar') . '"', $html);
        $this->assertStringNotContainsString('id="contas-data"', $html);
    }

    public function testOperadorSemPermissaoContasCriarNaoVeBotaoNemModalAdicionarUsuario(): void
    {
        $operador = $this->criarConta('operador.somente.acesso', 'user', ['acessos.administrar']);

        $resposta = $this->actingAs($operador)->get('admin/acessos');

        $resposta->assertOK();
        $resposta->assertSee('Gerenciamento de Usuários');
        $resposta->assertDontSee('Adicionar Usuário');

        $html = (string) $resposta->getBody();
        $this->assertStringNotContainsString('id="modal-adicionar-usuario"', $html);
        $this->assertStringContainsString('id="modal-atualizar-usuario"', $html);
    }

    public function testAlternaBotaoEntreAtivosEInativosConformeSituacao(): void
    {
        $superadmin = $this->criarConta('super.situacao', 'superadmin');

        $ativos = $this->actingAs($superadmin)->get('admin/acessos?situacao=ativos');
        $ativos->assertOK();
        $ativos->assertSee('Usuários Inativos');
        $this->assertStringContainsString('data-situacao="ativos"', (string) $ativos->getBody());

        $inativos = $this->actingAs($superadmin)->get('admin/acessos?situacao=inativos');
        $inativos->assertOK();
        $inativos->assertSee('Usuários Ativos');
        $this->assertStringContainsString('data-situacao="inativos"', (string) $inativos->getBody());
    }

    public function testListagemServerSideRetornaPaginacaoEEstruturaDataTablesSemExporSegredos(): void
    {
        $superadmin = $this->criarConta('super.lista', 'superadmin');

        for ($i = 1; $i <= 12; $i++) {
            $this->criarContaComEmail(sprintf('usuario.%02d', $i), sprintf('usuario%02d@ifro.edu.br', $i), 'user');
        }

        $resposta = $this->actingAs($superadmin)->get('admin/acessos/listar?draw=2&start=0&length=10&situacao=ativos');
        $resposta->assertOK();

        $json = json_decode($resposta->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertSame(2, $json['draw']);
        $this->assertSame(13, $json['recordsTotal']);
        $this->assertSame(13, $json['recordsFiltered']);
        $this->assertCount(10, $json['data']);

        $primeiro = $json['data'][0];
        $this->assertArrayHasKey('id', $primeiro);
        $this->assertArrayHasKey('rotulo', $primeiro);
        $this->assertArrayHasKey('email', $primeiro);
        $this->assertArrayHasKey('grupos', $primeiro);
        $this->assertArrayHasKey('raw_grupos', $primeiro);
        $this->assertArrayHasKey('ativo', $primeiro);
        $this->assertArrayNotHasKey('secret2', $primeiro);
        $this->assertArrayNotHasKey('identities', $primeiro);

        $pagina2 = $this->actingAs($superadmin)->get('admin/acessos/listar?draw=3&start=10&length=10&situacao=ativos');
        $pagina2->assertOK();
        $json2 = json_decode($pagina2->getJSON(), true);
        $this->assertIsArray($json2);
        $this->assertCount(3, $json2['data']);
    }

    public function testListagemServerSideFiltraPorSituacaoAtivosEInativos(): void
    {
        $superadmin = $this->criarConta('super.filtro', 'superadmin');
        $ativo      = $this->criarContaComEmail('conta.ativa', 'ativa@ifro.edu.br', 'user', true);
        $inativo    = $this->criarContaComEmail('conta.inativa', 'inativa@ifro.edu.br', 'user', false);

        $respAtivos = $this->actingAs($superadmin)->get('admin/acessos/listar?situacao=ativos&length=50');
        $respAtivos->assertOK();
        $idsAtivos = array_column(json_decode($respAtivos->getJSON(), true)['data'], 'id');

        $this->assertContains((int) $ativo->id, $idsAtivos);
        $this->assertNotContains((int) $inativo->id, $idsAtivos);

        $respInativos = $this->actingAs($superadmin)->get('admin/acessos/listar?situacao=inativos&length=50');
        $respInativos->assertOK();
        $idsInativos = array_column(json_decode($respInativos->getJSON(), true)['data'], 'id');

        $this->assertContains((int) $inativo->id, $idsInativos);
        $this->assertNotContains((int) $ativo->id, $idsInativos);
    }

    public function testListagemServerSidePesquisaPorNomeOuEmail(): void
    {
        $superadmin = $this->criarConta('super.busca', 'superadmin');
        $alvo       = $this->criarContaComEmail('maria.silva', 'maria.silva@ifro.edu.br', 'admin');
        $this->criarContaComEmail('joao.souza', 'joao.souza@ifro.edu.br', 'user');

        $porNome = $this->actingAs($superadmin)->get('admin/acessos/listar?search[value]=maria&situacao=ativos');
        $porNome->assertOK();
        $jsonNome = json_decode($porNome->getJSON(), true);

        $this->assertSame(3, $jsonNome['recordsTotal']);
        $this->assertSame(1, $jsonNome['recordsFiltered']);
        $this->assertSame((int) $alvo->id, $jsonNome['data'][0]['id']);

        $porEmail = $this->actingAs($superadmin)->get(
            'admin/acessos/listar?search[value]=joao.souza@ifro&situacao=ativos'
        );
        $porEmail->assertOK();
        $jsonEmail = json_decode($porEmail->getJSON(), true);

        $this->assertSame(1, $jsonEmail['recordsFiltered']);
        $this->assertSame('joao.souza', $jsonEmail['data'][0]['username']);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    #[DataProvider('provedorParametrosListagemInvalidos')]
    public function testListagemServerSideRecusaParametrosInvalidos(array $parametros): void
    {
        $superadmin = $this->criarConta('super.validacao', 'superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/acessos/listar', $parametros);
        $resposta->assertStatus(422);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function provedorParametrosListagemInvalidos(): array
    {
        return [
            'limite acima de 100'  => [['length' => '101']],
            'limite zero'          => [['length' => '0']],
            'inicio negativo'      => [['start' => '-1']],
            'situacao inexistente' => [['situacao' => 'bloqueados']],
            'pesquisa muito longa' => [['search' => ['value' => str_repeat('a', 101)]]],
            'search malformado'    => [['search' => 'texto-simples']],
        ];
    }

    public function testRotasRemovidasDePermissoesDiretasContinuamRetornando404(): void
    {
        $superadmin = $this->criarConta('super.404', 'superadmin');

        $this->expectException(PageNotFoundException::class);
        $this->actingAs($superadmin)->get('admin/acessos/' . $superadmin->id . '/permissoes');
    }

    public function testMenuOferecePaginaDeAcessosSoParaQuemPodeAdministrar(): void
    {
        $superadmin = $this->criarConta('super.menu', 'superadmin');
        $comum      = $this->criarConta('comum.menu', 'user');

        $this->actingAs($superadmin)->get('/')->assertSee(url_to('acessos.index'));
        $this->actingAs($comum)->get('/')->assertDontSee(url_to('acessos.index'));
    }

    private function criarContaComEmail(string $username, string $email, string $grupo, bool $ativo = true): User
    {
        $contas = model(UserModel::class);
        $contas->save(new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'SenhaForte123!',
            'active'   => $ativo ? 1 : 0,
        ]));

        /** @var User $conta */
        $conta = $contas->findById($contas->getInsertID());
        $conta->addGroup($grupo);

        return $conta;
    }
}
