<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PermissaoModel;
use App\Services\PermissaoService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

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
    }

    public function testSincronizarCatalogoGarantePermissoesDeCodigoNoBancoENoShield(): void
    {
        $catalogo = $this->service->sincronizarCatalogo();

        $this->assertArrayHasKey('contas.consultar', $catalogo);
        $this->assertArrayHasKey('contas.criar', $catalogo);
        $this->assertArrayHasKey('acessos.administrar', $catalogo);

        // Confere que no storage do Shield o catálogo reflete os identificadores
        $configShield = setting('AuthGroups.permissions');
        $this->assertIsArray($configShield);
        $this->assertArrayHasKey('contas.consultar', $configShield);
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

        // Verifica que o catálogo do Shield foi sincronizado com a nova permissão
        $catalogoShield = setting('AuthGroups.permissions');
        $this->assertArrayHasKey('relatorios.emitir', $catalogoShield);
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

    public function testAtualizarPermissaoDeSistemaNaoPermiteAlterarIdentificador(): void
    {
        $permissaoSistema = $this->model->buscarPorIdentificador('acessos.administrar');
        $this->assertNotNull($permissaoSistema);

        $resultado = $this->service->atualizarPermissao((int) $permissaoSistema['id'], [
            'identificador' => 'acessos.gerenciar_novo_nome',
            'descricao'     => 'Nova descrição válida para o teste',
        ], $this->usuarioId);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('PERMISSAO_SISTEMA_IDENTIFICADOR_FIXO', $resultado['codigo']);
    }

    public function testAtualizarPermissaoComSucesso(): void
    {
        $permissaoSistema = $this->model->buscarPorIdentificador('acessos.administrar');
        $this->assertNotNull($permissaoSistema);

        $resultado = $this->service->atualizarPermissao((int) $permissaoSistema['id'], [
            'descricao' => 'Nova descrição administrativa atualizada',
        ], $this->usuarioId);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('PERMISSAO_ATUALIZADA', $resultado['codigo']);

        /** @var array<string, mixed>|null $atualizado */
        $atualizado = $this->model->find((int) $permissaoSistema['id']);
        $this->assertNotNull($atualizado);
        $this->assertSame('Nova descrição administrativa atualizada', $atualizado['descricao']);
        $this->assertSame($this->usuarioId, (int) $atualizado['updated_by']);
    }

    public function testAtualizarPermissaoRetornaErroQuandoModelFalha(): void
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

        $resultado = $serviceComFalha->atualizarPermissao(1, [
            'descricao' => 'Nova descrição válida para teste',
        ], $this->usuarioId);

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('inesperado', $resultado['tipo']);
        $this->assertSame('PERMISSAO_ERRO_ATUALIZACAO', $resultado['codigo']);
    }
}
