<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Migrations\CriarTabelaGrupos;
use App\Models\GrupoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

final class CriarTabelaGruposMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected function tearDown(): void
    {
        if ($this->db->tableExists('grupos')) {
            $this->db->table('grupos')->emptyTable();
        }

        parent::tearDown();
    }

    public function testEstruturaDaTabelaPreservaIdentificadorDoShield(): void
    {
        $this->assertSame(
            ['identificador', 'ativo', 'created_at', 'updated_at'],
            $this->db->getFieldNames('grupos')
        );

        $grupos = new GrupoModel($this->db);
        $grupos->insert(['identificador' => 'grupo-personalizado', 'ativo' => true]);

        $this->seeInDatabase('grupos', [
            'identificador' => 'grupo-personalizado',
            'ativo'         => 1,
        ]);
    }
}
