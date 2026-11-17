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

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
    ];

    private string $senha;

    private int $contadorUsuarios = 0;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->senha = bin2hex(random_bytes(24));
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
            'nome'        => 'nome.atualizado',
            'id'          => (string) $outroUsuario->id,
            'email'       => 'adulterado@example.com',
            'groups'      => ['admin'],
            'permissions' => ['acessos.administrar'],
        ]);

        $resposta->assertRedirect();
        $this->seeInDatabase('users', [
            'id'       => $usuarioAtual->id,
            'username' => 'nome.atualizado',
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
        $this->assertStringContainsString(
            'navbar-profile-name">nome.atualizado</p>',
            $paginaPerfil->getBody(),
        );
    }

    public function testRejeitaNomeForaDasRegrasConfiguradas(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => 'ab']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
        ]);
    }

    public function testRejeitaNomeQueJaPertenceAOutroUsuario(): void
    {
        $usuarioAtual = $this->criarUsuario('usuario.atual');
        $outroUsuario = $this->criarUsuario('usuario.existente');
        auth()->login($usuarioAtual);

        $resposta = $this->enviarFormulario(['nome' => 'usuario.existente']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', [
            'id'       => $usuarioAtual->id,
            'username' => 'usuario.atual',
        ]);
        $this->seeInDatabase('users', [
            'id'       => $outroUsuario->id,
            'username' => 'usuario.existente',
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
        ]);
    }

    public function testRejeitaNomeAtualComEspacosExtras(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => ' usuario.teste ']);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('errors');
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'usuario.teste',
        ]);
    }

    public function testRemoveEspacosExtrasDeNomeValidoAntesDeSalvar(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario(['nome' => ' nome.atualizado ']);

        $resposta->assertRedirect();
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'nome.atualizado',
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

    public function testAceitaRegrasConfiguradasComClosures(): void
    {
        $usuario = $this->criarUsuario('usuario.teste');
        auth()->login($usuario);

        $configuracaoAuth = config('Auth');
        $regrasOriginais   = $configuracaoAuth->usernameValidationRules;
        $configuracaoAuth->usernameValidationRules['rules'][] = static fn ($valor): bool => is_string($valor);

        try {
            $resposta = $this->enviarFormulario(['nome' => 'nome.dinamico']);

            $resposta->assertRedirect();
            $this->seeInDatabase('users', [
                'id'       => $usuario->id,
                'username' => 'nome.dinamico',
            ]);
        } finally {
            $configuracaoAuth->usernameValidationRules = $regrasOriginais;
        }
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
