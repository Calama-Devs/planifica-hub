<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\UsuarioModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\CriaContasTrait;

final class UsuarioModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CriaContasTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private UsuarioModel $modelo;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->modelo = new UsuarioModel();
    }

    public function testNomeJaExisteRetornaTrueParaNomeExistente(): void
    {
        $this->criarConta('usuario.ativo', 'user');

        $this->assertTrue($this->modelo->nomeJaExiste('usuario.ativo'));
        $this->assertTrue($this->modelo->nomeJaExiste('  usuario.ativo  '));
        $this->assertFalse($this->modelo->nomeJaExiste('usuario.inexistente'));
        $this->assertFalse($this->modelo->nomeJaExiste(''));
    }

    public function testNomeJaExisteRetornaTrueMesmoParaContaComSoftDelete(): void
    {
        /** @var UserModel $usuarioModel */
        $usuarioModel = model(UserModel::class);
        $usuario      = new User(['username' => 'usuario.apagado', 'active' => true]);
        $usuarioModel->save($usuario);
        $id = (int) $usuarioModel->getInsertID();

        // Realiza o soft delete do usuário
        $usuarioModel->delete($id);

        // Confirma que o usuário está marcado como apagado (deleted_at preenchido)
        $this->seeInDatabase('users', ['id' => $id, 'deleted_at !=' => null]);

        // A consulta padrão do UserModel sem withDeleted não acha o usuário
        $this->assertNull($usuarioModel->findById($id));

        // A verificação semântica do UsuarioModel deve encontrar devido ao withDeleted()
        $this->assertTrue($this->modelo->nomeJaExiste('usuario.apagado'));
    }

    public function testEmailJaExisteRetornaTrueParaEmailExistente(): void
    {
        $usuario = $this->criarConta('usuario.com.email', 'user');
        $usuario->createEmailIdentity([
            'email'    => 'contato@example.com',
            'password' => 'T9!x' . bin2hex(random_bytes(16)),
        ]);

        $this->assertTrue($this->modelo->emailJaExiste('contato@example.com'));
        $this->assertTrue($this->modelo->emailJaExiste('CONTATO@EXAMPLE.COM'));
        $this->assertTrue($this->modelo->emailJaExiste('  contato@example.com  '));
        $this->assertFalse($this->modelo->emailJaExiste('outro@example.com'));
        $this->assertFalse($this->modelo->emailJaExiste(''));
    }
}

