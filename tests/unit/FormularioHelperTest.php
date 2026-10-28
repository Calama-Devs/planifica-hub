<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FormularioHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        helper(['form', 'formulario']);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testErroCampoRetornaVazioQuandoNaoHaErros(): void
    {
        $this->assertSame('', erro_campo('email'));
    }

    public function testErroCampoRetornaIsInvalidQuandoHaErroNaSessao(): void
    {
        $_SESSION['errors'] = [
            'email' => 'O campo e-mail é obrigatório.',
        ];

        $this->assertSame('is-invalid', erro_campo('email'));
    }

    public function testErroCampoRetornaVazioParaCampoSemErroMesmoComOutrosErros(): void
    {
        $_SESSION['errors'] = [
            'nome' => 'O campo nome é obrigatório.',
        ];

        $this->assertSame('', erro_campo('email'));
    }

    public function testErroCampoRetornaIsInvalidComErrosDeValidacaoNativos(): void
    {
        $_SESSION['_ci_validation_errors'] = [
            'titulo' => 'O título deve ter no mínimo 5 caracteres.',
        ];

        $this->assertSame('is-invalid', erro_campo('titulo'));
    }
}

