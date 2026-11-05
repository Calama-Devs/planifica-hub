<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PermissaoModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PermissoesAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private UserModel $userModel;
    private PermissaoModel $permissaoModel;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        $_SESSION = [];

        $this->userModel      = model(UserModel::class);
        $this->permissaoModel = model(PermissaoModel::class);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAcessoNegadoParaUsuarioDeslogado(): void
    {
        $resposta = $this->get('admin/permissoes');

        $resposta->assertRedirectTo(route_to('login'));
    }

    public function testAcessoNegadoParaUsuarioSemPermissaoAcessosAdministrar(): void
    {
        $usuarioComum = $this->criarUsuario('user');

        $resposta = $this->actingAs($usuarioComum)->get('admin/permissoes');

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $resposta->assertSessionHas('error');
    }

    public function testSuperadminAcessaListagemDePermissoes(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/permissoes');

        $resposta->assertOK();
        $resposta->assertSee('Catálogo de Permissões');
        $resposta->assertSee('Nova Permissão');
        $resposta->assertSee('contas.consultar');
        $resposta->assertSee('acessos.administrar');
    }

    public function testSuperadminAcessaTelaDeNovaPermissao(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $resposta = $this->actingAs($superadmin)->get('admin/permissoes/nova');

        $resposta->assertOK();
        $resposta->assertSee('Cadastrar Nova Permissão');
        $resposta->assertSee('Identificador da Permissão');
        $resposta->assertSee('Salvar');
    }

    public function testSalvarNovaPermissaoComSucessoEAtualizaShield(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $dados = [
            'identificador' => 'projetos.aprovar',
            'descricao'     => 'Permite aprovar projetos institucionais submetidos',
            csrf_token()    => csrf_hash(),
        ];

        $resposta = $this->actingAs($superadmin)
            ->withSession($_SESSION)
            ->post('admin/permissoes/salvar', $dados);

        $resposta->assertRedirectTo(url_to('permissoes.index'));
        $resposta->assertSessionHas('success');

        $registro = $this->permissaoModel->buscarPorIdentificador('projetos.aprovar');
        $this->assertNotNull($registro);
        $this->assertSame('customizada', $registro['origem']);
        $this->assertSame((int) $superadmin->id, (int) $registro['created_by']);

        // Verifica que o Shield aceita a nova permissão diretamente no usuário
        $superadmin->addPermission('projetos.aprovar');
        $this->assertTrue($superadmin->can('projetos.aprovar'));
    }

    public function testSalvarNovaPermissaoRecusaFormatoInvalido(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $dados = [
            'identificador' => 'PROJETO_INVALIDO',
            'descricao'     => 'Descrição válida com tamanho adequado',
            csrf_token()    => csrf_hash(),
        ];

        $resposta = $this->actingAs($superadmin)
            ->withSession($_SESSION)
            ->post('admin/permissoes/salvar', $dados);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');

        $registro = $this->permissaoModel->buscarPorIdentificador('PROJETO_INVALIDO');
        $this->assertNull($registro);
    }

    public function testSalvarNovaPermissaoRecusaDuplicidade(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $dados = [
            'identificador' => 'contas.consultar',
            'descricao'     => 'Tentativa de duplicar permissão existente',
            csrf_token()    => csrf_hash(),
        ];

        $resposta = $this->actingAs($superadmin)
            ->withSession($_SESSION)
            ->post('admin/permissoes/salvar', $dados);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('error', 'Já existe uma permissão cadastrada com este identificador.');
    }

    public function testRecusaEnvioSemCsrf(): void
    {
        $superadmin = $this->criarUsuario('superadmin');

        $this->expectException(SecurityException::class);

        $this->actingAs($superadmin)->post('admin/permissoes/salvar', [
            'identificador' => 'teste.sem_csrf',
            'descricao'     => 'Tentativa sem token CSRF',
        ]);
    }

    public function testEditarEAtualizarDescricaoPermissao(): void
    {
        $superadmin = $this->criarUsuario('superadmin');
        $permissao  = $this->permissaoModel->buscarPorIdentificador('contas.criar');
        $this->assertNotNull($permissao);

        $respostaForm = $this->actingAs($superadmin)->get('admin/permissoes/editar/' . (int) $permissao['id']);
        $respostaForm->assertOK();
        $respostaForm->assertSee('Editar Permissão');
        $respostaForm->assertSee('contas.criar');

        $respostaAtualizar = $this->actingAs($superadmin)
            ->withSession($_SESSION)
            ->post('admin/permissoes/atualizar/' . (int) $permissao['id'], [
                'descricao'  => 'Descrição administrativa revisada para criação de contas',
                csrf_token() => csrf_hash(),
            ]);

        $respostaAtualizar->assertRedirectTo(url_to('permissoes.index'));
        $respostaAtualizar->assertSessionHas('success');

        /** @var array<string, mixed>|null $registroAtualizado */
        $registroAtualizado = $this->permissaoModel->find((int) $permissao['id']);
        $this->assertNotNull($registroAtualizado);
        $this->assertSame('Descrição administrativa revisada para criação de contas', $registroAtualizado['descricao']);
        $this->assertSame((int) $superadmin->id, (int) $registroAtualizado['updated_by']);
    }

    private function criarUsuario(string $grupo): User
    {
        $this->userModel->save(new User(['username' => 'usuario.' . $grupo . '.' . bin2hex(random_bytes(4))]));

        /** @var User $usuario */
        $usuario = $this->userModel->findById($this->userModel->getInsertID());
        $usuario->addGroup($grupo);

        return $usuario;
    }
}
