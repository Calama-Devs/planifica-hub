<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

final class PerfilTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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
            'nome' => 'nome.atualizado',
            'id'   => (string) $outroUsuario->id,
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
