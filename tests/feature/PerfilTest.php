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

    public function testAtualizaNomeDaContaAutenticadaMesmoComIdDeOutraContaNoFormulario(): void
    {
        $usuarioAtual = $this->criarUsuario('usuario.atual');
        $outroUsuario = $this->criarUsuario('usuario.outro');
        auth()->login($usuarioAtual);

        $resposta = $this->enviarFormulario([
            'nome'        => 'José da Silva',
            'id'          => (string) $outroUsuario->id,
            'email'       => 'adulterado@example.com',
            'groups'      => ['admin'],
            'permissions' => ['acessos.administrar'],
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
        $this->dontSeeInDatabase('auth_identities', [
            'user_id' => $usuarioAtual->id,
            'secret'  => 'adulterado@example.com',
        ]);
        auth()->logout();
        $usuarioAtualizado = model(UserModel::class)->findById($usuarioAtual->id);
        $paginaPerfil      = $this->actingAs($usuarioAtualizado)->get('perfil');
        $paginaPerfil->assertOK();
        $this->assertStringContainsString('>Jos&eacute; da Silva</span>', $paginaPerfil->getBody());
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

    private function criarUsuario(string $nome): User
    {
        $usuarios = model(UserModel::class);
        $usuario  = new User(['username' => $nome]);
        $usuarios->save($usuario);

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->createEmailIdentity([
            'email'    => 'perfil' . ++$this->contadorUsuarios . '@example.com',
            'password' => $this->senha,
        ]);

        return $usuario;
    }

    private function enviarFormulario(array $campos): TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->withSession($_SESSION)->post('perfil', $campos);
    }
}
