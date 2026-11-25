<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

final class PerfilTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private string $senha;

    private int $contadorUsuarios = 0;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->senha = bin2hex(random_bytes(24));

        $banco = db_connect();
        $tabelaUsuarios = $banco->prefixTable(config('Auth')->tables['users']);

        if (! $banco->fieldExists('nome_completo', $tabelaUsuarios)) {
            $banco->query(
                'ALTER TABLE '
                . $banco->escapeIdentifiers($tabelaUsuarios)
                . ' ADD COLUMN nome_completo VARCHAR(255) NULL',
            );
        }
    }

    public function testBloqueiaVisitanteSemSessao(): void
    {
        $resposta = $this->get('perfil');

        $resposta->assertRedirect();
    }

    public function testExibeNomeEEmailDaContaAutenticada(): void
    {
        $usuario = $this->criarUsuario('usuario.consulta');
        auth()->login($usuario);

        $resposta = $this->withSession($_SESSION)->get('perfil');

        $resposta->assertOK();
        $resposta->assertSee('usuario.consulta');
        $resposta->assertSee('perfil1@example.com');
    }

    public function testExibeNomeCompletoComFallbackParaUsername(): void
    {
        $usuario = $this->criarUsuario('usuario.nome');
        $this->db->table('users')->where('id', $usuario->id)->update(['nome_completo' => 'Nome Completo Atual']);
        auth()->login(model(UserModel::class)->findById($usuario->id));

        $resposta = $this->withSession($_SESSION)->get('perfil');

        $resposta->assertOK();
        $resposta->assertSee('Nome Completo Atual');
    }

    public function testExibePerfilComNomeAtualEProtecaoCsrf(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->withSession($_SESSION)->get('perfil');

        $resposta->assertOK();
        $resposta->assertSee('usuario.teste');
        $resposta->assertSeeElement('label[for=campo-nome]');
        $resposta->assertSeeElement('input[name=nome]');
        $resposta->assertSeeElement('input[name=' . csrf_token() . ']');
    }

    public function testAtualizaNomeDaContaAutenticadaSemAlterarIdentidadeOuOutraConta(): void
    {
        $usuarioAtual = $this->criarUsuario('usuario.atual');
        $outroUsuario = $this->criarUsuario('usuario.outro');
        auth()->login($usuarioAtual);

        $resposta = $this->enviarFormulario([
            'nome'        => 'José da Silva',
        ]);

        $resposta->assertRedirect();
        $this->seeInDatabase('users', [
            'id'       => $usuarioAtual->id,
            'username' => 'usuario.atual',
            'nome_completo' => 'José da Silva',
        ]);
        $this->seeInDatabase('users', [
            'id'       => $outroUsuario->id,
            'username' => 'usuario.outro',
        ]);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $usuarioAtual->id,
            'secret'  => 'perfil1@example.com',
        ]);
        auth()->logout();
        $usuarioAtualizado = model(UserModel::class)->findById($usuarioAtual->id);
        $paginaPerfil      = $this->actingAs($usuarioAtualizado)->get('perfil');
        $paginaPerfil->assertOK();
        $this->assertStringContainsString('>Jos&eacute; da Silva</span>', $paginaPerfil->getBody());
    }

    public function testRejeitaEmailEnviadoDiretamenteNoMeuPerfil(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['email' => 'adulterado@example.com']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', ['id' => $usuario->id, 'nome_completo' => null]);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $usuario->id,
            'type' => 'email_password',
            'secret' => 'perfil1@example.com',
        ]);
        $this->dontSeeInDatabase('auth_identities', [
            'user_id' => $usuario->id,
            'secret' => 'adulterado@example.com',
        ]);
    }

    public function testRejeitaEmailComNomeValidoSemAlterarDadosDoPerfil(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario([
            'nome' => 'Nome nao deve ser salvo',
            'email' => 'adulterado@example.com',
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', ['id' => $usuario->id, 'nome_completo' => null]);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $usuario->id,
            'type' => 'email_password',
            'secret' => 'perfil1@example.com',
        ]);
    }

    public function testRejeitaCampoExtraNoPerfilSemAtualizarNome(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $camposSensiveis = [
            ['groups' => ['admin']],
            ['active' => '0'],
            ['status' => 'banned'],
            ['status_message' => 'Bloqueado'],
            ['permissions' => ['contas.editar']],
            ['username' => 'outro.usuario'],
            ['password' => 'senha-nao-permitida'],
            ['Password' => 'senha-nao-permitida'],
            ['PASSWORD' => 'senha-nao-permitida'],
            ['EMAIL' => 'outro@ifro.edu.br'],
            ['Permissions' => ['contas.editar']],
            ['permissions[]' => 'contas.editar'],
            ['campo_extra' => 'valor nao permitido'],
        ];

        foreach ($camposSensiveis as $campoSensivel) {
            $resposta = $this->enviarFormulario([
                'nome' => 'Nome nao deve ser salvo',
                ...$campoSensivel,
            ]);

            $resposta->assertRedirect();
            $resposta->assertSessionHas('errors');
            $this->seeInDatabase('users', [
                'id' => $usuario->id,
                'nome_completo' => null,
                'username' => 'usuario.teste',
                'active' => 1,
                'status' => null,
                'status_message' => null,
            ]);
        }

        $this->dontSeeInDatabase('auth_groups_users', ['user_id' => $usuario->id, 'group' => 'admin']);
        $this->dontSeeInDatabase('auth_permissions_users', [
            'user_id' => $usuario->id,
            'permission' => 'contas.editar',
        ]);
    }

    public function testIgnoraCamposAuxiliaresInofensivosAoAtualizarNome(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario([
            'nome' => 'Nome Atualizado',
            '_method' => 'POST',
            'botao_salvar' => 'Salvar nome',
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('success');
        $this->seeInDatabase('users', [
            'id' => $usuario->id,
            'username' => 'usuario.teste',
            'nome_completo' => 'Nome Atualizado',
            'active' => 1,
            'status' => null,
            'status_message' => null,
        ]);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $usuario->id,
            'type' => 'email_password',
            'secret' => 'perfil1@example.com',
        ]);
    }

    public function testRejeitaNomeForaDasRegrasConfiguradas(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => 'a']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
        ]);
    }

    public function testAceitaNomeQueJaPertenceAOutroUsuario(): void
    {
        $usuarioAtual = $this->criarUsuario('usuario.atual');
        $outroUsuario = $this->criarUsuario('usuario.existente');
        auth()->login($usuarioAtual);

        model(\App\Models\UsuarioAutenticacaoModel::class)->update(
            $outroUsuario->id,
            ['nome_completo' => 'Nome Compartilhado'],
        );
        $resposta = $this->enviarFormulario(['nome' => 'Nome Compartilhado']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('success');
        $this->seeInDatabase('users', [
            'id'       => $usuarioAtual->id,
            'username' => 'usuario.atual',
            'nome_completo' => 'Nome Compartilhado',
        ]);
        $this->seeInDatabase('users', [
            'id'       => $outroUsuario->id,
            'username' => 'usuario.existente',
            'nome_completo' => 'Nome Compartilhado',
        ]);
    }

    public function testRejeitaNomeIgualAoNomeAtual(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => 'usuario.teste']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
            'nome_completo' => null,
        ]);
    }

    public function testRemoveEspacosExtrasEmNomeAtualizado(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => ' José da Silva ']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('success');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
            'nome_completo' => 'José da Silva',
        ]);
    }

    public function testRemoveEspacosExtrasDeNomeValidoAntesDeSalvar(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => ' Maria de Souza ']);

        $resposta->assertRedirect();
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
            'nome_completo' => 'Maria de Souza',
        ]);
    }

    public function testRejeitaNomeComApenasEspacos(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => '   ']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
        ]);
    }

    public function testAceitaNomeComAcentos(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => 'João D’Ávila']);

        $resposta->assertRedirect();
        $this->seeInDatabase('users', [
            'id' => $usuario->id,
            'username' => 'usuario.teste',
            'nome_completo' => 'João D’Ávila',
        ]);
    }

    public function testUsuarioComumConsultaGruposSemPermissoesAdministrativas(): void
    {
        $usuario = $this->criarUsuario('usuario.comum', 'user');
        auth()->login($usuario);

        $resposta = $this->withSession($_SESSION)->get('perfil');

        $resposta->assertOK();
        $resposta->assertSee('E-mail institucional');
        $resposta->assertSee('perfil1@example.com');
        $resposta->assertSee(config('AuthGroups')->groups['user']['title']);
        $resposta->assertSee('Nenhuma permissão administrativa.');
        $resposta->assertDontSeeElement('input[name=email]');
    }

    public function testExibeGruposEPermissoesEfetivasDaContaAdministrativa(): void
    {
        $usuario = $this->criarUsuario('usuario.admin', 'admin');
        auth()->login($usuario);
        $configuracao = config('AuthGroups');

        $resposta = $this->withSession($_SESSION)->get('perfil');

        $resposta->assertOK();
        $resposta->assertSee($configuracao->groups['admin']['title']);
        $resposta->assertSee($configuracao->permissions['contas.consultar']);
        $resposta->assertSee($configuracao->permissions['contas.criar']);
        $resposta->assertDontSee($configuracao->permissions['acessos.administrar']);
    }

    public function testExibeOpcoesDeEditarNomeEAlterarSenha(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->withSession($_SESSION)->get('perfil');

        $resposta->assertOK();
        $resposta->assertSee('Editar nome');
        $resposta->assertSeeElement('button[id=botao-alterar-senha-perfil]');
        $resposta->assertSeeElement('input[id=mostrar-senhas-perfil]');
        $resposta->assertSee('Atualizar senha');
        $resposta->assertSee('Cancelar');
    }

    public function testListaGruposPeloTituloConfigurado(): void
    {
        $usuario = $this->criarUsuario('usuario.grupos', 'user');
        $usuario->addGroup('admin');
        $grupos = config('AuthGroups')->groups;

        $this->assertSame(
            [$grupos['user']['title'], $grupos['admin']['title']],
            service('perfilService')->listarGrupos($usuario),
        );
    }

    public function testListaPermissoesEfetivasHerdadasDoGrupoEConcedidasDiretamente(): void
    {
        $usuario = $this->criarUsuario('usuario.direto', 'user');
        $usuario->addPermission('acessos.administrar');
        $permissoes = config('AuthGroups')->permissions;

        $this->assertSame(
            [$permissoes['acessos.administrar']],
            service('perfilService')->listarPermissoesEfetivas($usuario),
        );

        $usuario->addGroup('admin');

        $this->assertSame(
            [$permissoes['contas.consultar'], $permissoes['contas.criar'], $permissoes['acessos.administrar']],
            service('perfilService')->listarPermissoesEfetivas($usuario),
        );
    }

    public function testRecusaAlteracaoDeNomeSemSessao(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');

        $resposta = $this->withSession($_SESSION)->post('perfil', [
            csrf_token() => csrf_hash(),
            'nome'       => 'Apelido',
        ]);

        $resposta->assertRedirectTo(site_url('login'));
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
        ]);
    }

    public function testAlteracaoDeNomePreservaCredenciaisGruposEPermissoes(): void
    {
        $usuario = $this->criarUsuario('usuario.atual', 'admin');
        $usuario->addPermission('acessos.administrar');
        $this->ativarUsuario($usuario);
        auth()->login($usuario);
        $hashAnterior = $this->buscarHashDaSenha($usuario);

        $resposta = $this->enviarFormulario([
            'nome'        => 'Apelido',
            'password'    => 'SenhaAdulterada@123',
            'email'       => 'adulterado@example.com',
            'groups'      => ['superadmin'],
            'permissions' => ['contas.criar'],
            'active'      => '0',
        ]);

        $resposta->assertRedirectTo(url_to('perfil'));
        $resposta->assertSessionHas('success');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'Apelido',
        ]);
        $this->assertSame($hashAnterior, $this->buscarHashDaSenha($usuario));
        $this->assertContaPreservada($usuario, 'perfil1@example.com', ['admin'], ['acessos.administrar']);
        $this->assertTrue(auth()->loggedIn());
    }

    public function testAlteracaoDeSenhaNaoModificaOutrosDadosNemOutraConta(): void
    {
        $usuario      = $this->criarUsuario('conta.principal', 'admin');
        $outroUsuario = $this->criarUsuario('conta.vizinha');
        $usuario->addPermission('acessos.administrar');
        $this->ativarUsuario($usuario);
        auth()->login($usuario);
        $hashOutraConta = $this->buscarHashDaSenha($outroUsuario);

        $resposta = $this->withSession($_SESSION)->post('perfil/alterar-senha', [
            csrf_token()           => csrf_hash(),
            'senha-atual'          => $this->senha,
            'senha-nova'           => 'NovaSenha@123',
            'confirmar-senha-nova' => 'NovaSenha@123',
            'id'                   => (string) $outroUsuario->id,
            'user_id'              => (string) $outroUsuario->id,
            'nome'                 => 'Apelido',
            'username'             => 'Apelido',
            'email'                => 'adulterado@example.com',
            'groups'               => ['superadmin'],
            'permissions'          => ['contas.criar'],
            'active'               => '0',
        ]);

        $resposta->assertOK();
        $resposta->assertJSONFragment(['sucesso' => true]);
        $this->assertTrue(password_verify('NovaSenha@123', $this->buscarHashDaSenha($usuario)));
        $this->assertSame($hashOutraConta, $this->buscarHashDaSenha($outroUsuario));
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'conta.principal',
        ]);
        $this->assertContaPreservada($usuario, 'perfil1@example.com', ['admin'], ['acessos.administrar']);
    }

    private function criarUsuario(string $nome, ?string $grupo = null): User
    {
        $usuarios = model(UserModel::class);
        $usuario  = new User(['username' => $nome, 'active' => true]);
        $usuarios->save($usuario);

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->createEmailIdentity([
            'email'    => 'perfil' . ++$this->contadorUsuarios . '@example.com',
            'password' => $this->senha,
        ]);

        if ($grupo !== null) {
            $usuario->addGroup($grupo);
        }

        return $usuario;
    }

    private function ativarUsuario(User $usuario): void
    {
        $usuario->active = true;
        $this->db->table('users')->where('id', $usuario->id)->update(['active' => 1]);
    }

    private function buscarHashDaSenha(User $usuario): string
    {
        $identidade = $this->db->table('auth_identities')
            ->select('secret2')
            ->where('user_id', $usuario->id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        $this->assertNotNull($identidade);

        return $identidade['secret2'];
    }

    /**
     * @param list<string> $grupos
     * @param list<string> $permissoesDiretas
     */
    private function assertContaPreservada(User $usuario, string $email, array $grupos, array $permissoesDiretas): void
    {
        /** @var User $conta */
        $conta = model(UserModel::class)->findById($usuario->id);

        $this->seeInDatabase('users', ['id' => $usuario->id, 'active' => 1]);
        $this->seeInDatabase('auth_identities', [
            'user_id' => $usuario->id,
            'type'    => 'email_password',
            'secret'  => $email,
        ]);
        $this->dontSeeInDatabase('auth_identities', ['secret' => 'adulterado@example.com']);
        $this->assertSame($grupos, $conta->getGroups());
        $this->assertSame($permissoesDiretas, $conta->getPermissions());
        $this->assertTrue($conta->can('contas.consultar'));
        $this->assertFalse($conta->inGroup('superadmin'));
    }

    private function enviarFormulario(array $campos): TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->withSession($_SESSION)->post('perfil', $campos);
    }
}
