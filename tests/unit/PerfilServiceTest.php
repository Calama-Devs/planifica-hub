<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PerfilService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class PerfilServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private PerfilService $service;
    private User $usuario;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->service = new PerfilService();

        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.teste']));

        /** @var User $usuario */
        $usuario       = $usuarios->findById((int) $usuarios->getInsertID());
        $this->usuario = $usuario;
    }

    public function testObterGruposDoUsuarioRetornaTitulosDoGrupo(): void
    {
        $this->usuario->addGroup('superadmin');

        $grupos = $this->service->obterGruposDoUsuario($this->usuario);

        $this->assertSame(['Administrador geral'], $grupos);
    }

    public function testObterGruposDoUsuarioRetornaArrayVazioSemGrupos(): void
    {
        $grupos = $this->service->obterGruposDoUsuario($this->usuario);

        $this->assertSame([], $grupos);
    }

    public function testObterGruposDoUsuarioUsaChaveComoFallbackParaGrupoDesconhecido(): void
    {
        db_connect()->table('auth_groups_users')->insert([
            'user_id'    => $this->usuario->id,
            'group'      => 'grupo-desconhecido',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $grupos = $this->service->obterGruposDoUsuario($this->usuario);

        $this->assertSame(['grupo-desconhecido'], $grupos);
    }

    public function testPodeEditarEmailRetornaFalso(): void
    {
        $this->assertFalse($this->service->podeEditarEmail());
    }

    public function testPodeEditarGruposRetornaFalso(): void
    {
        $this->assertFalse($this->service->podeEditarGrupos());
    }
}
