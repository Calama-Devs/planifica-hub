<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class UserModelShieldCompatibilityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private string $senha = 'SenhaInicial@123';

    public function testProviderDoShieldMantemLoginComIdentidadeDeEmail(): void
    {
        $this->criarUsuario('login@ifro.edu.br');
        auth()->logout();

        $resposta = $this->enviarFormulario('login', [
            'email'    => 'login@ifro.edu.br',
            'password' => $this->senha,
        ]);

        $resposta->assertRedirectTo(rtrim(site_url('/'), '/'));
        $this->assertTrue(auth()->loggedIn());
        $this->assertInstanceOf(UserModel::class, auth()->getProvider());
    }

    public function testProviderDoShieldPermiteRedefinirSenhaPorTokenTemporario(): void
    {
        $usuario = $this->criarUsuario('redefinicao@ifro.edu.br');
        $token   = 'token-regressao-redefinicao';

        $this->db->table('users')->where('id', $usuario->id)->update(['active' => 1]);
        model(UserIdentityModel::class)->insert([
            'user_id' => $usuario->id,
            'type'    => 'magic-link-reset',
            'secret'  => $token,
            'expires' => Time::now()->addMinutes(10),
        ]);

        $this->get('recuperar-senha/verificar?token=' . rawurlencode($token))
            ->assertRedirectTo('/reset-password');

        $senhaNova = 'SenhaNova@123';
        $resposta  = $this->enviarFormulario('reset-password', [
            'senha-nova'          => $senhaNova,
            'confirmar-senha-nova' => $senhaNova,
        ]);

        $resposta->assertRedirectTo('/login');
        $identidade = model(UserIdentityModel::class)
            ->where('user_id', $usuario->id)
            ->where('type', 'email_password')
            ->first();

        $this->assertNotNull($identidade);
        $this->assertTrue(password_verify($senhaNova, (string) $identidade->secret2));
    }

    public function testProviderDoShieldPermiteAtualizarPerfilDaContaAutenticada(): void
    {
        $usuario = $this->criarUsuario('perfil@ifro.edu.br');
        auth()->login($usuario);

        $resposta = $this->enviarFormulario('perfil', [
            'nome' => 'perfil.atualizado',
        ]);

        $resposta->assertRedirect();
        $this->seeInDatabase('users', [
            'id'       => $usuario->id,
            'username' => 'perfil.atualizado',
        ]);
    }

    private function criarUsuario(string $email): User
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User([
            'username' => 'usuario.regressao.' . bin2hex(random_bytes(4)),
        ]));

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarios->getInsertID());
        $usuario->createEmailIdentity([
            'email'    => $email,
            'password' => $this->senha,
        ]);

        return $usuario;
    }

    private function enviarFormulario(string $rota, array $campos): \CodeIgniter\Test\TestResponse
    {
        $campos[csrf_token()] = csrf_hash();

        return $this->withSession($_SESSION)->post($rota, $campos);
    }
}
