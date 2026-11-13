<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class HomeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
    ];

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
    }

    public function testRedirecionaInicioParaLoginQuandoNaoAutenticado(): void
    {
        $resposta = $this->get('/');

        $resposta->assertRedirectTo(site_url('login'));
    }

    public function testRedirecionaSobreParaLoginQuandoNaoAutenticado(): void
    {
        $resposta = $this->get('sobre');

        $resposta->assertRedirectTo(site_url('login'));
    }

    public function testExibeInicioQuandoAutenticado(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('/');

        $resposta->assertOK();
        $resposta->assertSee('PlanIFica Hub');
        $resposta->assertSee('Dashboard');
    }

    public function testExibeSobreReutilizandoLayoutQuandoAutenticado(): void
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->actingAs($usuario)->get('sobre');

        $resposta->assertOK();
        $resposta->assertSee('Sobre o PlanIFica Hub');
        $resposta->assertSee('Dashboard');
    }

    public function testUsaAssetsExistentesNoLayoutDasPaginasAutenticadas(): void
    {
        $usuario = $this->criarUsuario();

        foreach (['/', 'sobre'] as $rota) {
            $resposta = $this->actingAs($usuario)->get($rota);

            $resposta->assertOK();
            $this->assertStringContainsString(
                '<link rel="stylesheet" href="' . base_url('assets/vendor/mdi/css/materialdesignicons.min.css') . '">',
                $resposta->getBody()
            );
            $this->assertStringContainsString(
                '<script src="' . base_url('assets/vendor/js/vendor.bundle.base.js') . '"></script>',
                $resposta->getBody()
            );

            preg_match_all('/(?:src|href)="([^"]*\/assets\/[^"]+)"/', $resposta->getBody(), $referencias);
            $this->assertNotEmpty($referencias[1]);

            foreach ($referencias[1] as $url) {
                $prefixo = base_url('assets/');
                $this->assertStringStartsWith($prefixo, $url);
                $this->assertFileExists(FCPATH . 'assets/' . substr($url, strlen($prefixo)));
            }
        }

        $this->assertDirectoryDoesNotExist(FCPATH . 'assets/vendors');
    }

    public function testFontesUtilizadasPeloLayoutExistem(): void
    {
        $caminhos = [
            'assets/vendor/mdi/css/materialdesignicons.min.css' => '../fonts/',
            'assets/css/modern-vertical/style.css' => '../../fonts/Rubik/',
        ];

        foreach ($caminhos as $caminho => $prefixoFontes) {
            $this->assertFileExists(FCPATH . $caminho);
            $conteudo = file_get_contents(FCPATH . $caminho);
            $this->assertNotFalse($conteudo);
            $padrao = '/url\(["\']?(' . preg_quote($prefixoFontes, '/') . '[^)"\'?#]+)[^)]*\)/';
            preg_match_all($padrao, $conteudo, $referencias);
            $this->assertNotEmpty($referencias[1]);

            foreach ($referencias[1] as $fonte) {
                $this->assertFileExists(dirname(FCPATH . $caminho) . '/' . $fonte);
            }
        }
    }

    private function criarUsuario(): User
    {
        $users = model(UserModel::class);
        $user  = new User([
            'username' => 'usuario.layout',
            'email'    => 'layout@example.com',
            'password' => 'SenhaForte123!',
        ]);

        $users->save($user);

        /** @var User $usuarioCriado */
        $usuarioCriado = $users->findById($users->getInsertID());

        return $usuarioCriado;
    }
}
