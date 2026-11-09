<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PermissaoModel;
use App\Services\PermissaoService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;

final class PermissaoServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    private PermissaoService $service;
    private PermissaoModel $model;
    private int $usuarioId;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->model   = model(PermissaoModel::class);
        $this->service = new PermissaoService($this->model);

        $this->seed(\App\Database\Seeds\PermissoesSeeder::class);

        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'admin.teste']));
        $this->usuarioId = (int) $usuarios->getInsertID();

        /** @var User $usuario */
        $usuario = $usuarios->findById($this->usuarioId);
        $usuario->addGroup('superadmin');
    }

    public function testSincronizarCatalogoGarantePermissoesDeCodigoNoBancoESemPoluirSettings(): void
    {
        $catalogo = $this->service->sincronizarCatalogo();

        $this->assertArrayHasKey('contas.consultar', $catalogo);
        $this->assertArrayHasKey('contas.criar', $catalogo);
        $this->assertArrayHasKey('acessos.administrar', $catalogo);

        $this->dontSeeInDatabase('settings', ['key' => 'permissions']);

        $authGroups = config(AuthGroups::class);
        $this->assertArrayHasKey('contas.consultar', $authGroups->permissions);
    }

    public function testCadastrarPermissaoComSucesso(): void
    {
        $dados = [
            'identificador' => 'relatorios.emitir',
            'descricao'     => 'Permite emitir relatórios institucionais consolidados',
        ];

        $resultado = $this->service->cadastrarPermissao($dados, $this->usuarioId);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSAO_CADASTRADA', $resultado['codigo']);
        $this->assertArrayHasKey('id', $resultado);

        $registro = $this->model->buscarPorIdentificador('relatorios.emitir');
        $this->assertNotNull($registro);
        $this->assertSame('customizada', $registro['origem']);
        $this->assertSame($this->usuarioId, (int) $registro['created_by']);

        $this->dontSeeInDatabase('settings', ['key' => 'permissions']);
        $authGroups = new AuthGroups();
        $this->assertArrayHasKey('relatorios.emitir', $authGroups->permissions);
    }

    public function testCadastrarPermissaoRecusaFormatoInvalido(): void
    {
        $formatosInvalidos = [
            'INVALIDO',
            'relatorios',
            'relatorios.',
            '.emitir',
            'relatorios.emitir_relatorio_com_espaco extra',
            'relatorios.emitirAcaoCamelCase',
            'relatório.emitir',
        ];

        foreach ($formatosInvalidos as $formato) {
            $resultado = $this->service->cadastrarPermissao([
                'identificador' => $formato,
                'descricao'     => 'Descrição de teste com tamanho adequado',
            ], $this->usuarioId);

            $this->assertFalse($resultado['sucesso']);
            $this->assertSame('PERMISSAO_FORMATO_INVALIDO', $resultado['codigo']);
        }
    }

    public function testCadastrarPermissaoRecusaDescricaoInvalida(): void
    {
        $descricoesInvalidas = [
            '',
            'abc', // menos de 5 caracteres
            str_repeat('a', 256), // mais de 255 caracteres
        ];

        foreach ($descricoesInvalidas as $descricao) {
            $resultado = $this->service->cadastrarPermissao([
                'identificador' => 'documentos.validar',
                'descricao'     => $descricao,
            ], $this->usuarioId);

            $this->assertFalse($resultado['sucesso']);
            $this->assertSame('PERMISSAO_DESCRICAO_INVALIDA', $resultado['codigo']);
        }
    }

    public function testCadastrarPermissaoRecusaDuplicidade(): void
    {
        $dados = [
            'identificador' => 'certificados.emitir',
            'descricao'     => 'Permite emissão de certificados oficiais',
        ];

        $primeiro = $this->service->cadastrarPermissao($dados, $this->usuarioId);
        $this->assertTrue($primeiro['sucesso']);

        $segundo = $this->service->cadastrarPermissao($dados, $this->usuarioId);
        $this->assertFalse($segundo['sucesso']);
        $this->assertSame('PERMISSAO_DUPLICADA', $segundo['codigo']);
    }

    public function testAtualizarDescricaoComSucesso(): void
    {
        $permissaoSistema = $this->model->buscarPorIdentificador('acessos.administrar');
        $this->assertNotNull($permissaoSistema);

        $resultado = $this->service->atualizarDescricao(
            (int) $permissaoSistema['id'],
            'Nova descrição administrativa atualizada',
            $this->usuarioId
        );

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSAO_ATUALIZADA', $resultado['codigo']);

        /** @var array<string, mixed>|null $atualizado */
        $atualizado = $this->model->find((int) $permissaoSistema['id']);
        $this->assertNotNull($atualizado);
        $this->assertSame('Nova descrição administrativa atualizada', $atualizado['descricao']);
        $this->assertSame($this->usuarioId, (int) $atualizado['updated_by']);
    }

    public function testAtualizarDescricaoPermissaoCustomizadaRefleteNoAuthGroupsEmMemoria(): void
    {
        $dados = [
            'identificador' => 'projetos.avaliar',
            'descricao'     => 'Descrição original de avaliação de projetos',
        ];

        $cadastro = $this->service->cadastrarPermissao($dados, $this->usuarioId);
        $this->assertTrue($cadastro['sucesso']);
        $id = (int) $cadastro['id'];

        $resultado = $this->service->atualizarDescricao(
            $id,
            'Descrição atualizada e sincronizada com shield em runtime',
            $this->usuarioId
        );

        $this->assertTrue($resultado['sucesso']);

        $authGroups = config(AuthGroups::class);
        $this->assertSame(
            'Descrição atualizada e sincronizada com shield em runtime',
            $authGroups->permissions['projetos.avaliar']
        );
    }

    public function testAtualizarDescricaoRecusaDescricaoInvalida(): void
    {
        $permissao = $this->model->buscarPorIdentificador('contas.consultar');
        $this->assertNotNull($permissao);

        $resultado = $this->service->atualizarDescricao(
            (int) $permissao['id'],
            'abc',
            $this->usuarioId
        );

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('PERMISSAO_DESCRICAO_INVALIDA', $resultado['codigo']);
    }

    public function testAtualizarDescricaoRetornaErroQuandoModelFalha(): void
    {
        $mockModel = $this->createMock(PermissaoModel::class);
        $mockModel->method('find')->willReturn([
            'id'            => 1,
            'identificador' => 'contas.consultar',
            'descricao'     => 'Descrição existente',
            'origem'        => 'sistema',
        ]);
        $mockModel->method('update')->willReturn(false);

        $serviceComFalha = new PermissaoService($mockModel);

        $resultado = $serviceComFalha->atualizarDescricao(1, 'Nova descrição válida para teste', $this->usuarioId);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame('PERMISSAO_ERRO_ATUALIZACAO', $resultado['codigo']);
    }

    public function testCadastrarPermissaoRecusaUsuarioSemPermissaoAcessosAdministrar(): void
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.comum']));
        $usuarioComumId = (int) $usuarios->getInsertID();

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarioComumId);
        $usuario->addGroup('user');

        $resultado = $this->service->cadastrarPermissao([
            'identificador' => 'relatorios.exportar',
            'descricao'     => 'Permite exportar relatórios para planilhas',
        ], $usuarioComumId);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('permissao', $resultado['tipo']);
        $this->assertSame('ACESSOS_SEM_PERMISSAO', $resultado['codigo']);
    }

    public function testAtualizarDescricaoRecusaUsuarioSemPermissaoAcessosAdministrar(): void
    {
        $usuarios = model(UserModel::class);
        $usuarios->save(new User(['username' => 'usuario.sem_permissao']));
        $usuarioSemPermissaoId = (int) $usuarios->getInsertID();

        /** @var User $usuario */
        $usuario = $usuarios->findById($usuarioSemPermissaoId);
        $usuario->addGroup('user');

        $permissao = $this->model->buscarPorIdentificador('contas.consultar');
        $this->assertNotNull($permissao);

        $resultado = $this->service->atualizarDescricao(
            (int) $permissao['id'],
            'Nova descrição não autorizada',
            $usuarioSemPermissaoId
        );

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('permissao', $resultado['tipo']);
        $this->assertSame('ACESSOS_SEM_PERMISSAO', $resultado['codigo']);
    }

    public function testSincronizacaoCatalogoExecutaComSegurancaSemLancarExcecao(): void
    {
        $catalogo = $this->service->sincronizarCatalogo();
        $this->assertNotEmpty($catalogo);

        setting('AuthGroups.permissions', ['dummy.permissao' => 'Teste']);
        $this->seeInDatabase('settings', ['key' => 'permissions']);

        $catalogoAposLimpeza = $this->service->sincronizarCatalogo();
        $this->assertNotEmpty($catalogoAposLimpeza);
        $this->dontSeeInDatabase('settings', ['key' => 'permissions']);
    }

    public function testAuthGroupsCarregaPermissaoAtivaDoBancoMesmoComOrigemSistema(): void
    {
        $this->model->insert([
            'identificador' => 'auditoria.consultar',
            'descricao'     => 'Permite consultar logs de auditoria institucional',
            'origem'        => 'sistema',
            'created_by'    => $this->usuarioId,
            'updated_by'    => $this->usuarioId,
        ]);

        $authGroups = new AuthGroups();
        $this->assertArrayHasKey('auditoria.consultar', $authGroups->permissions);
        $this->assertSame(
            'Permite consultar logs de auditoria institucional',
            $authGroups->permissions['auditoria.consultar']
        );
    }

    public function testCadastrarPermissaoTrataExcecaoDeChaveUnicaComoDuplicada(): void
    {
        $mockModel = $this->createMock(PermissaoModel::class);
        $mockModel->method('buscarPorIdentificador')->willReturn(null);
        $mockModel->method('insert')->willThrowException(
            new DatabaseException('Duplicate entry for key identificador', 1062)
        );

        $service = new PermissaoService($mockModel);
        $resultado = $service->cadastrarPermissao([
            'identificador' => 'concorrente.inserir',
            'descricao'     => 'Teste de colisão concorrente de duplicidade',
        ], $this->usuarioId);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('regra_negocio', $resultado['tipo']);
        $this->assertSame('PERMISSAO_DUPLICADA', $resultado['codigo']);
        $this->assertSame('Já existe uma permissão cadastrada com este identificador.', $resultado['mensagem']);
    }

    public function testAuthGroupsNuncaSobrescreveDescricaoDePermissaoNativaVersionada(): void
    {
        $registro = $this->model->buscarPorIdentificador('contas.consultar');
        $this->assertNotNull($registro);

        $this->model->update((int) $registro['id'], [
            'descricao' => 'Tentativa de sobrescrever descrição nativa pelo banco',
        ]);

        $authGroups = new AuthGroups();
        $this->assertSame(
            'Consultar a lista e os dados das contas de usuário',
            $authGroups->permissions['contas.consultar']
        );
    }
}
